<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$reglas = [
    'intervalo_min'      => ['Intervalo entre horarios (min)', 'Cada cuánto se ofrece una hora: 30 = 9:00, 9:30, 10:00…', 5, 240],
    'anticipacion_horas' => ['Anticipación mínima (horas)', 'Ej. 2 = no se puede reservar para dentro de menos de 2 horas.', 0, 720],
    'dias_max'           => ['Reservar hasta (días)', 'Con cuántos días de anticipación se puede reservar.', 1, 365],
    'capacidad'          => ['Citas simultáneas', 'Cuántas clientas se pueden atender a la misma hora.', 1, 50],
    'cancelacion_horas'  => ['Cancelación en línea (horas antes)', 'Hasta cuántas horas antes el cliente puede cancelar con su enlace.', 0, 720],
];

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'agregar_horario') {
        $dia = (int)post('dia_semana');
        $ini = post('hora_inicio');
        $fin = post('hora_fin');
        if ($dia < 0 || $dia > 6 || !preg_match('/^\d{2}:\d{2}$/', $ini) || !preg_match('/^\d{2}:\d{2}$/', $fin) || $fin <= $ini) {
            flash('danger', 'Revisa el horario: la hora de cierre debe ser posterior a la de apertura.');
        } else {
            $dias = post('todos_laborables') === '1' ? [1, 2, 3, 4, 5] : [$dia];
            $stmt = db()->prepare('INSERT INTO horarios (dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?)');
            foreach ($dias as $d) {
                $stmt->execute([$d, $ini, $fin]);
            }
            flash('success', 'Horario agregado.');
        }
    } elseif ($accion === 'borrar_horario') {
        db()->prepare('DELETE FROM horarios WHERE id = ?')->execute([(int)post('id')]);
        flash('success', 'Horario eliminado.');
    } elseif ($accion === 'reglas') {
        foreach ($reglas as $clave => [, , $min, $max]) {
            config_set($clave, (string)max($min, min($max, (int)post($clave))));
        }
        config_set('estado_inicial', post('estado_inicial') === 'confirmada' ? 'confirmada' : 'pendiente');
        flash('success', 'Reglas de reserva guardadas.');
    } elseif ($accion === 'agregar_bloqueo') {
        $fIni = post('fecha_inicio');
        $fFin = post('fecha_fin') ?: $fIni;
        $soloHoras = post('tipo') === 'horas';
        $hIni = post('hora_inicio');
        $hFin = post('hora_fin');
        $horaOk = fn($h) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);

        if (!fecha_valida($fIni) || !fecha_valida($fFin) || $fFin < $fIni) {
            flash('danger', 'Revisa las fechas: «Hasta» no puede ser anterior a «Desde».');
        } elseif ($soloHoras && (!$horaOk($hIni) || !$horaOk($hFin) || $hFin <= $hIni)) {
            flash('danger', 'Revisa las horas: la hora final debe ser posterior a la inicial.');
        } elseif ((strtotime($fFin) - strtotime($fIni)) / 86400 > 366) {
            flash('danger', 'El rango no puede superar un año.');
        } else {
            // Días completos: un solo bloque. Solo unas horas: esas horas en cada día del rango.
            $rangos = [];
            if ($soloHoras) {
                for ($d = $fIni; $d <= $fFin; $d = date('Y-m-d', strtotime("$d +1 day"))) {
                    $rangos[] = ["$d $hIni:00", "$d $hFin:00"];
                }
            } else {
                $rangos[] = ["$fIni 00:00:00", date('Y-m-d', strtotime("$fFin +1 day")) . ' 00:00:00'];
            }

            $motivo = mb_substr(post('motivo'), 0, 200) ?: null;
            $stmt = db()->prepare('INSERT INTO bloqueos (inicio, fin, motivo) VALUES (?, ?, ?)');
            $afectadas = [];
            foreach ($rangos as [$ini, $fin]) {
                $stmt->execute([$ini, $fin, $motivo]);
                foreach (citas_que_se_cruzan($ini, $fin) as $c) {
                    $afectadas[$c['id']] = $c;
                }
            }
            flash('success', 'Bloqueo agregado. Esas horas ya no se ofrecerán para reservar.');
            if ($afectadas) {
                $lista = implode('; ', array_map(fn($c) => $c['cliente_nombre'] . ' (' . fecha_humana($c['inicio']) . ')', $afectadas));
                flash('warning', 'Ojo: ya hay ' . count($afectadas) . ' cita(s) en ese tiempo que quizá debas reagendar: ' . $lista);
            }
        }
    } elseif ($accion === 'borrar_bloqueo') {
        db()->prepare('DELETE FROM bloqueos WHERE id = ?')->execute([(int)post('id')]);
        flash('success', 'Bloqueo eliminado.');
    }
    redirigir('horarios.php');
}

$horarios = [];
foreach (db()->query('SELECT * FROM horarios ORDER BY hora_inicio') as $h) {
    $horarios[(int)$h['dia_semana']][] = $h;
}
$bloqueos = db()->query('SELECT * FROM bloqueos WHERE fin >= NOW() ORDER BY inicio')->fetchAll();

admin_inicio('Horarios y reglas', 'horarios');
?>
<div class="row g-4">
    <div class="col-xl-6">
        <div class="card mb-4">
            <div class="card-header">Horario de atención semanal</div>
            <ul class="list-group list-group-flush">
                <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $dia): ?>
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                        <strong style="width: 90px"><?= DIAS_SEMANA[$dia] ?></strong>
                        <?php foreach ($horarios[$dia] ?? [] as $h): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                <span class="badge text-bg-light border fs-6 fw-normal">
                                    <?= e(hora12($h['hora_inicio'])) ?> – <?= e(hora12($h['hora_fin'])) ?>
                                    <button class="btn btn-link btn-sm p-0 ms-1 text-danger" name="accion" value="borrar_horario" title="Quitar"><i class="bi bi-x-circle"></i></button>
                                </span>
                            </form>
                        <?php endforeach; ?>
                        <?php if (empty($horarios[$dia])): ?><span class="text-muted small">Cerrado</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body border-top">
                <form method="post" class="row g-2 align-items-end">
                    <?= csrf_campo() ?><input type="hidden" name="accion" value="agregar_horario">
                    <div class="col-sm-4">
                        <label class="form-label small">Día</label>
                        <select class="form-select" name="dia_semana">
                            <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $dia): ?><option value="<?= $dia ?>"><?= DIAS_SEMANA[$dia] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3"><label class="form-label small">Abre</label><input type="time" class="form-control" name="hora_inicio" value="09:00" required></div>
                    <div class="col-6 col-sm-3"><label class="form-label small">Cierra</label><input type="time" class="form-control" name="hora_fin" value="18:00" required></div>
                    <div class="col-sm-2"><button class="btn btn-primario w-100">Agregar</button></div>
                    <div class="col-12">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="todos_laborables" value="1" id="todosLab">
                            <label class="form-check-label" for="todosLab">Agregar este horario de lunes a viernes</label>
                        </div>
                        <div class="form-text">Para un descanso a mediodía agrega dos rangos el mismo día (ej. 9:00–13:00 y 15:00–19:00).</div>
                    </div>
                </form>
            </div>
        </div>

        <form method="post" class="card">
            <div class="card-header">Reglas de reserva</div>
            <div class="card-body">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="reglas">
                <div class="row g-3">
                    <?php foreach ($reglas as $clave => [$texto, $ayuda, $min, $max]): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium"><?= $texto ?></label>
                            <input type="number" class="form-control" name="<?= $clave ?>" min="<?= $min ?>" max="<?= $max ?>" value="<?= e(config($clave, '0')) ?>">
                            <div class="form-text"><?= $ayuda ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Las reservas web llegan como</label>
                        <select class="form-select" name="estado_inicial">
                            <option value="pendiente" <?= config('estado_inicial') === 'pendiente' ? 'selected' : '' ?>>Pendientes (las confirmo yo)</option>
                            <option value="confirmada" <?= config('estado_inicial') === 'confirmada' ? 'selected' : '' ?>>Confirmadas automáticamente</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primario px-4">Guardar reglas</button></div>
        </form>
    </div>

    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">Cerrar la agenda en una fecha u horario <span class="text-muted fw-normal small">(vacaciones, feriados, trámites…)</span></div>
            <div class="card-body">
                <p class="small text-muted">Las horas bloqueadas simplemente no aparecen para reservar; la web no muestra ningún aviso de cierre.</p>
                <form method="post" class="row g-2">
                    <?= csrf_campo() ?><input type="hidden" name="accion" value="agregar_bloqueo">
                    <div class="col-12">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipo" value="horas" id="tipoHoras" checked>
                            <label class="btn btn-outline-secondary" for="tipoHoras"><i class="bi bi-clock"></i> Solo unas horas</label>
                            <input type="radio" class="btn-check" name="tipo" value="dias" id="tipoDias">
                            <label class="btn btn-outline-secondary" for="tipoDias"><i class="bi bi-calendar-x"></i> Días completos</label>
                        </div>
                    </div>
                    <div class="col-6"><label class="form-label small">Desde el día</label><input type="date" class="form-control" name="fecha_inicio" id="bloqDesde" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-6"><label class="form-label small">Hasta el día</label><input type="date" class="form-control" name="fecha_fin" id="bloqHasta" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6 horas-bloqueo"><label class="form-label small">De</label><input type="time" class="form-control" name="hora_inicio" value="09:00"></div>
                    <div class="col-6 horas-bloqueo"><label class="form-label small">A</label><input type="time" class="form-control" name="hora_fin" value="12:00"></div>
                    <div class="col-12 form-text horas-bloqueo mt-0">Esas horas se bloquean en cada día del rango. Para un solo día deja la misma fecha en ambos campos.</div>
                    <div class="col-12"><label class="form-label small">Motivo <span class="text-muted">(solo lo ves tú)</span></label><input class="form-control" name="motivo" maxlength="200"></div>
                    <div class="col-12 text-end"><button class="btn btn-primario">Bloquear</button></div>
                </form>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($bloqueos as $b): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small fw-medium"><?= e(rango_humano($b['inicio'], $b['fin'])) ?></div>
                            <?php if ($b['motivo']): ?><small class="text-muted"><?= e($b['motivo']) ?></small><?php endif; ?>
                        </div>
                        <form method="post">
                            <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="accion" value="borrar_bloqueo"><i class="bi bi-trash"></i></button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (!$bloqueos): ?><li class="list-group-item text-muted small">No hay bloqueos próximos.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php admin_fin('<script>
const tipoHoras = document.getElementById("tipoHoras");
const mostrarHoras = () => document.querySelectorAll(".horas-bloqueo").forEach(el => el.classList.toggle("d-none", !tipoHoras.checked));
document.querySelectorAll("input[name=tipo]").forEach(r => r.addEventListener("change", mostrarHoras));
// Si cambian "Desde" y "Hasta" quedó antes, se iguala
const desde = document.getElementById("bloqDesde"), hasta = document.getElementById("bloqHasta");
desde.addEventListener("change", () => { if (!hasta.value || hasta.value < desde.value) hasta.value = desde.value; });
</script>'); ?>
