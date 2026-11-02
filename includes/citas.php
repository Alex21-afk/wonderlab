<?php
// Lógica de citas: disponibilidad y creación

const ESTADOS_ACTIVOS = ['pendiente', 'confirmada']; // ocupan horario

function servicio_obtener(int $id, bool $soloActivos = true): ?array
{
    $stmt = db()->prepare('SELECT * FROM servicios WHERE id = ?' . ($soloActivos ? ' AND activo = 1' : ''));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function cita_obtener(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM citas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function cita_por_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM citas WHERE token = ?');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function fecha_valida(string $fecha): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

/** Intervalos [inicio, fin] como timestamps a partir de una consulta que devuelve columnas inicio y fin. */
function intervalos(string $sql, array $params): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map(fn($r) => [strtotime($r['inicio']), strtotime($r['fin'])], $stmt->fetchAll());
}

function cuantos_se_cruzan(int $ini, int $fin, array $intervalos): int
{
    $n = 0;
    foreach ($intervalos as [$a, $b]) {
        if ($a < $fin && $b > $ini) {
            $n++;
        }
    }
    return $n;
}

function horario_del_dia(int $diaSemana): array
{
    $stmt = db()->prepare('SELECT hora_inicio, hora_fin FROM horarios WHERE dia_semana = ? ORDER BY hora_inicio');
    $stmt->execute([$diaSemana]);
    return $stmt->fetchAll();
}

/**
 * Horas ("H:i") en las que se puede reservar el servicio en la fecha dada.
 * $excluirCitaId: no contar esa cita como ocupada (al reagendarla).
 * $esAdmin: el panel ignora la anticipación mínima y el límite de días (pero no permite horas pasadas).
 */
function horas_disponibles(array $servicio, string $fecha, int $excluirCitaId = 0, bool $esAdmin = false): array
{
    if (!fecha_valida($fecha)) {
        return [];
    }
    $iniDia = strtotime($fecha);
    $finDia = strtotime('+1 day', $iniDia);
    $minimo = time() + ($esAdmin ? 0 : (int)config('anticipacion_horas', '0') * 3600);
    $maximo = $esAdmin ? PHP_INT_MAX : strtotime('today +' . ((int)config('dias_max', '60') + 1) . ' days');
    if ($finDia <= $minimo || $iniDia >= $maximo) {
        return [];
    }

    $rangos = horario_del_dia((int)date('w', $iniDia));
    if (!$rangos) {
        return [];
    }

    $duracion  = max(5, (int)$servicio['duracion_min']) * 60;
    $paso      = max(5, (int)config('intervalo_min', '30')) * 60;
    $capacidad = max(1, (int)config('capacidad', '1'));
    $desde = date('Y-m-d H:i:s', $iniDia);
    $hasta = date('Y-m-d H:i:s', $finDia);

    $citas = intervalos(
        "SELECT inicio, fin FROM citas WHERE estado IN ('pendiente','confirmada') AND inicio < ? AND fin > ? AND id <> ?",
        [$hasta, $desde, $excluirCitaId]
    );
    $bloqueados = intervalos('SELECT inicio, fin FROM bloqueos WHERE inicio < ? AND fin > ?', [$hasta, $desde]);

    if (config('google_bloquear', '1') === '1' && google_connected()) {
        try {
            $bloqueados = array_merge($bloqueados, google_ocupados($iniDia, $finDia));
        } catch (Throwable $e) {
            error_log('No se pudo consultar Google Calendar: ' . $e->getMessage()); // se sigue con los datos locales
        }
    }

    $horas = [];
    foreach ($rangos as $r) {
        $finRango = strtotime("$fecha {$r['hora_fin']}");
        for ($t = strtotime("$fecha {$r['hora_inicio']}"); $t + $duracion <= $finRango; $t += $paso) {
            if ($t < $minimo || $t >= $maximo) {
                continue;
            }
            if (cuantos_se_cruzan($t, $t + $duracion, $bloqueados) > 0) {
                continue;
            }
            if (cuantos_se_cruzan($t, $t + $duracion, $citas) >= $capacidad) {
                continue;
            }
            $horas[] = date('H:i', $t);
        }
    }
    return array_values(array_unique($horas));
}

/** Citas activas que se cruzan con un horario (para avisar en el panel). */
function citas_que_se_cruzan(string $inicio, string $fin, int $excluirId = 0): array
{
    $stmt = db()->prepare(
        "SELECT * FROM citas WHERE estado IN ('pendiente','confirmada') AND inicio < ? AND fin > ? AND id <> ? ORDER BY inicio"
    );
    $stmt->execute([$fin, $inicio, $excluirId]);
    return $stmt->fetchAll();
}

/**
 * Crea una cita. $d: servicio (array), fecha, hora, cliente_nombre, cliente_telefono, cliente_email,
 * notas, estado, origen y opcionalmente duracion_min / notas_internas.
 * Con $validar = true vuelve a comprobar la disponibilidad dentro de un bloqueo para evitar dobles reservas.
 */
function cita_crear(array $d, bool $validar = true): array
{
    $servicio = $d['servicio'];
    $duracion = (int)($d['duracion_min'] ?? $servicio['duracion_min']);
    $inicio = strtotime($d['fecha'] . ' ' . $d['hora']);
    $pdo = db();

    $pdo->query("SELECT GET_LOCK('wonderlab_reservas', 10)");
    try {
        if ($validar && !in_array($d['hora'], horas_disponibles($servicio, $d['fecha']), true)) {
            throw new DomainException('Ese horario acaba de ser ocupado. Por favor elige otro.');
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO citas (servicio_id, servicio_nombre, precio, inicio, fin, cliente_nombre, cliente_telefono,
                                cliente_email, notas, notas_internas, estado, origen, token, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $servicio['id'], $servicio['nombre'], $servicio['precio'],
            date('Y-m-d H:i:s', $inicio), date('Y-m-d H:i:s', $inicio + $duracion * 60),
            $d['cliente_nombre'], $d['cliente_telefono'], $d['cliente_email'] ?: null,
            $d['notas'] ?: null, $d['notas_internas'] ?? null,
            $d['estado'], $d['origen'], $token, ip_cliente(),
        ]);
        $id = (int)$pdo->lastInsertId();
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('wonderlab_reservas')");
    }

    return ['id' => $id, 'token' => $token, 'aviso' => google_sincronizar_cita($id)];
}

/** Bloqueos que se cruzan con un horario (para avisar en el panel). */
function bloqueos_que_se_cruzan(string $inicio, string $fin): array
{
    $stmt = db()->prepare('SELECT * FROM bloqueos WHERE inicio < ? AND fin > ? ORDER BY inicio');
    $stmt->execute([$fin, $inicio]);
    return $stmt->fetchAll();
}

/** Texto legible de un rango: "Lunes 9 de noviembre, 9:00 am – 12:00 pm" o "Lunes 9 … al Martes 10 … (días completos)". */
function rango_humano(string $inicio, string $fin): string
{
    $ini = strtotime($inicio);
    $f = strtotime($fin);
    if (date('H:i', $ini) === '00:00' && date('H:i', $f) === '00:00') {
        $ultimoDia = date('Y-m-d', $f - 86400);
        return date('Y-m-d', $ini) === $ultimoDia
            ? fecha_humana($inicio, false) . ' (todo el día)'
            : fecha_humana($inicio, false) . ' al ' . fecha_humana($ultimoDia, false) . ' (días completos)';
    }
    if (date('Y-m-d', $ini) === date('Y-m-d', $f)) {
        return fecha_humana($inicio) . ' – ' . hora12($fin);
    }
    return fecha_humana($inicio) . ' → ' . fecha_humana($fin);
}

/**
 * Cambia la fecha/hora de una cita conservando su duración y deja constancia en las notas internas.
 * Devuelve un aviso de Google Calendar o null.
 */
function cita_reagendar(array $cita, string $fecha, string $hora): ?string
{
    $duracion = strtotime($cita['fin']) - strtotime($cita['inicio']);
    $inicio = strtotime("$fecha $hora");
    $historial = trim(($cita['notas_internas'] ?? '') . "\n" . date('d/m/Y H:i') . ' – Reagendada de '
        . fecha_humana($cita['inicio']) . ' a ' . fecha_humana(date('Y-m-d H:i:s', $inicio)) . '.');

    db()->prepare('UPDATE citas SET inicio = ?, fin = ?, notas_internas = ? WHERE id = ?')->execute([
        date('Y-m-d H:i:s', $inicio), date('Y-m-d H:i:s', $inicio + $duracion), $historial, $cita['id'],
    ]);
    return google_sincronizar_cita((int)$cita['id']);
}

function cita_cambiar_estado(int $id, string $estado): ?string
{
    if (!isset(ESTADOS_CITA[$estado])) {
        return 'Estado no válido.';
    }
    db()->prepare('UPDATE citas SET estado = ? WHERE id = ?')->execute([$estado, $id]);
    return google_sincronizar_cita($id);
}

function cita_puede_cancelarse(array $cita): bool
{
    $limite = strtotime($cita['inicio']) - (int)config('cancelacion_horas', '0') * 3600;
    return in_array($cita['estado'], ESTADOS_ACTIVOS, true) && time() < $limite;
}

function mensaje_whatsapp_cita(array $cita): string
{
    return 'Hola, soy ' . $cita['cliente_nombre'] . '. Reservé una cita de ' . $cita['servicio_nombre']
        . ' para el ' . fecha_humana($cita['inicio']) . '.';
}
