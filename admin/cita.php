<?php
// Crear o editar una cita desde el panel
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$id = (int)get('id');
$cita = $id ? cita_obtener($id) : null;
if ($id && !$cita) {
    flash('danger', 'La cita no existe.');
    redirigir('citas.php');
}
$servicios = db()->query('SELECT * FROM servicios ORDER BY activo DESC, orden, nombre')->fetchAll();

// Valores del formulario: los de la cita, o valores iniciales para una nueva
$v = $cita ? [
    'servicio_id'      => (string)$cita['servicio_id'],
    'fecha'            => date('Y-m-d', strtotime($cita['inicio'])),
    'hora'             => date('H:i', strtotime($cita['inicio'])),
    'duracion_min'     => (string)((strtotime($cita['fin']) - strtotime($cita['inicio'])) / 60),
    'precio'           => (string)(float)$cita['precio'],
    'cliente_nombre'   => $cita['cliente_nombre'],
    'cliente_telefono' => $cita['cliente_telefono'],
    'cliente_email'    => (string)$cita['cliente_email'],
    'notas'            => (string)$cita['notas'],
    'notas_internas'   => (string)$cita['notas_internas'],
    'estado'           => $cita['estado'],
] : [
    'servicio_id'      => (string)($servicios[0]['id'] ?? ''),
    'fecha'            => fecha_valida(get('fecha')) ? get('fecha') : date('Y-m-d'),
    'hora'             => preg_match('/^\d{2}:\d{2}$/', get('hora')) ? get('hora') : '09:00',
    'duracion_min'     => (string)($servicios[0]['duracion_min'] ?? 60),
    'precio'           => (string)(float)($servicios[0]['precio'] ?? 0),
    'cliente_nombre'   => '', 'cliente_telefono' => '', 'cliente_email' => '',
    'notas'            => '', 'notas_internas' => '',
    'estado'           => 'confirmada',
];

$errores = [];
$cruces = [];

if (es_post()) {
    csrf_verificar();

    if (post('accion') === 'eliminar' && $cita) {
        if ($cita['google_event_id'] && google_connected()) {
            try {
                google_eliminar_evento($cita['google_event_id'], google_parametros_envio($cita));
            } catch (Throwable $e) {
                flash('warning', 'No se pudo borrar de Google Calendar: ' . $e->getMessage());
            }
        }
        db()->prepare('DELETE FROM citas WHERE id = ?')->execute([$cita['id']]);
        flash('success', 'Cita eliminada.');
        redirigir('citas.php');
    }

    foreach (array_keys($v) as $campo) {
        $v[$campo] = post($campo);
    }
    $servicio = servicio_obtener((int)$v['servicio_id'], false);
    $duracion = (int)$v['duracion_min'];

    if (!$servicio) $errores[] = 'Elige un servicio.';
    if (!fecha_valida($v['fecha'])) $errores[] = 'La fecha no es válida.';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v['hora'])) $errores[] = 'La hora no es válida.';
    if ($duracion < 5 || $duracion > 720) $errores[] = 'La duración debe estar entre 5 y 720 minutos.';
    if (!is_numeric($v['precio']) || $v['precio'] < 0) $errores[] = 'El precio no es válido.';
    if (mb_strlen($v['cliente_nombre']) < 2) $errores[] = 'Escribe el nombre del cliente.';
    if ($v['cliente_telefono'] === '') $errores[] = 'Escribe el teléfono del cliente.';
    if ($v['cliente_email'] !== '' && !filter_var($v['cliente_email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo no es válido.';
    if (!isset(ESTADOS_CITA[$v['estado']])) $errores[] = 'Estado no válido.';

    if (!$errores) {
        $inicio = $v['fecha'] . ' ' . $v['hora'] . ':00';
        $fin = date('Y-m-d H:i:s', strtotime($inicio) + $duracion * 60);
        if (in_array($v['estado'], ESTADOS_ACTIVOS, true) && post('forzar') !== '1') {
            $cruces = citas_que_se_cruzan($inicio, $fin, $id);
        }
    }

    if (!$errores && !$cruces) {
        if ($cita) {
            db()->prepare(
                'UPDATE citas SET servicio_id = ?, servicio_nombre = ?, precio = ?, inicio = ?, fin = ?, cliente_nombre = ?,
                        cliente_telefono = ?, cliente_email = ?, notas = ?, notas_internas = ?, estado = ? WHERE id = ?'
            )->execute([
                $servicio['id'], $servicio['nombre'], $v['precio'], $inicio, $fin, $v['cliente_nombre'],
                $v['cliente_telefono'], $v['cliente_email'] ?: null, $v['notas'] ?: null, $v['notas_internas'] ?: null,
                $v['estado'], $cita['id'],
            ]);
            $aviso = google_sincronizar_cita((int)$cita['id']);
            $guardadaId = (int)$cita['id'];
        } else {
            $r = cita_crear([
                'servicio'         => ['precio' => $v['precio']] + $servicio,
                'fecha'            => $v['fecha'],
                'hora'             => $v['hora'],
                'duracion_min'     => $duracion,
                'cliente_nombre'   => $v['cliente_nombre'],
                'cliente_telefono' => $v['cliente_telefono'],
                'cliente_email'    => $v['cliente_email'],
                'notas'            => $v['notas'],
                'notas_internas'   => $v['notas_internas'],
                'estado'           => $v['estado'],
                'origen'           => 'admin',
            ], false);
            $aviso = $r['aviso'];
            $guardadaId = $r['id'];
        }
        flash($aviso ? 'warning' : 'success', $aviso ?: 'Cita guardada.');
        redirigir('cita.php?id=' . $guardadaId);
    }
}

admin_inicio($cita ? 'Editar cita' : 'Nueva cita', 'citas');
?>
<?php foreach ($errores as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

<?php
if ($cita && get('reagendada')) {
    $waCambio = whatsapp_url($cita['cliente_telefono'], 'Hola ' . $cita['cliente_nombre'] . ', te escribimos de ' . config('nombre_sitio')
        . '. Tu cita de ' . $cita['servicio_nombre'] . ' quedó reagendada para el ' . fecha_humana($cita['inicio']) . '. ¡Te esperamos!');
}
?>
<?php if (!empty($waCambio)): ?>
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-info-circle"></i> Nuevo horario: <strong><?= e(fecha_humana($cita['inicio'])) ?></strong>. ¿Le avisas al cliente?</span>
        <a href="<?= e($waCambio) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Avisar por WhatsApp</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <form method="post" class="card">
            <?= csrf_campo() ?>
            <div class="card-body">
                <?php if ($cruces): ?>
                    <div class="alert alert-warning">
                        <strong>Este horario se cruza con:</strong>
                        <ul class="mb-2">
                            <?php foreach ($cruces as $x): ?>
                                <li><?= e(hora12($x['inicio'])) ?> – <?= e(hora12($x['fin'])) ?>: <?= e($x['cliente_nombre']) ?> (<?= e($x['servicio_nombre']) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forzar" value="1" id="forzar">
                            <label class="form-check-label" for="forzar">Guardar de todas formas</label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Servicio</label>
                        <select class="form-select" name="servicio_id" id="servicio" required>
                            <?php foreach ($servicios as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" data-duracion="<?= (int)$s['duracion_min'] ?>" data-precio="<?= (float)$s['precio'] ?>"
                                    <?= $v['servicio_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['nombre']) ?><?= $s['activo'] ? '' : ' (inactivo)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Duración (min)</label>
                        <input type="number" class="form-control" name="duracion_min" id="duracion" min="5" max="720" step="5" value="<?= e($v['duracion_min']) ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Precio</label>
                        <input type="number" class="form-control" name="precio" id="precio" min="0" step="0.01" value="<?= e($v['precio']) ?>">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha" value="<?= e($v['fecha']) ?>" required>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Hora</label>
                        <input type="time" class="form-control" name="hora" step="300" value="<?= e($v['hora']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <?php foreach (ESTADOS_CITA as $clave => [$texto]): ?>
                                <option value="<?= $clave ?>" <?= $v['estado'] === $clave ? 'selected' : '' ?>><?= $texto ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre del cliente</label>
                        <input class="form-control" name="cliente_nombre" maxlength="120" value="<?= e($v['cliente_nombre']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono / WhatsApp</label>
                        <input class="form-control" name="cliente_telefono" maxlength="30" value="<?= e($v['cliente_telefono']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Correo <span class="text-muted">(opcional)</span></label>
                        <input type="email" class="form-control" name="cliente_email" maxlength="150" value="<?= e($v['cliente_email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notas del cliente</label>
                        <textarea class="form-control" name="notas" rows="3"><?= e($v['notas']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notas internas <span class="text-muted">(no las ve el cliente)</span></label>
                        <textarea class="form-control" name="notas_internas" rows="3"><?= e($v['notas_internas']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="citas.php" class="btn btn-link text-secondary">Volver</a>
                <button class="btn btn-primario px-4"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>

    <?php if ($cita): ?>
    <div class="col-xl-4">
        <?php if (in_array($cita['estado'], ESTADOS_ACTIVOS, true)): ?>
            <a href="reagendar.php?id=<?= (int)$cita['id'] ?>" class="btn btn-primario w-100 mb-3"><i class="bi bi-calendar-event"></i> Reagendar</a>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header">Detalles</div>
            <div class="card-body small">
                <p class="mb-1"><strong>Estado:</strong> <?= estado_badge($cita['estado']) ?></p>
                <p class="mb-1"><strong>Origen:</strong> <?= $cita['origen'] === 'web' ? 'Reservada desde la web' : 'Creada en el panel' ?></p>
                <p class="mb-1"><strong>Creada:</strong> <?= e(fecha_humana($cita['creado_en'])) ?></p>
                <p class="mb-0"><strong>Google Calendar:</strong> <?= $cita['google_event_id'] ? '<span class="text-success">Sincronizada</span>' : '<span class="text-muted">No sincronizada</span>' ?></p>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Contactar al cliente</div>
            <div class="card-body d-grid gap-2">
                <?php
                $recordatorio = 'Hola ' . $cita['cliente_nombre'] . ', te escribimos de ' . config('nombre_sitio') . ' para recordarte tu cita de '
                    . $cita['servicio_nombre'] . ' el ' . fecha_humana($cita['inicio']) . '. ¡Te esperamos!';
                $confirmacion = 'Hola ' . $cita['cliente_nombre'] . ', tu cita de ' . $cita['servicio_nombre'] . ' para el '
                    . fecha_humana($cita['inicio']) . ' está confirmada. ¡Te esperamos en ' . config('nombre_sitio') . '!';
                ?>
                <?php if ($wa = whatsapp_url($cita['cliente_telefono'], $confirmacion)): ?>
                    <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Enviar confirmación</a>
                    <a href="<?= e(whatsapp_url($cita['cliente_telefono'], $recordatorio)) ?>" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm"><i class="bi bi-bell"></i> Enviar recordatorio</a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="copiarEnlace" data-enlace="<?= e(url_absoluta('cancelar.php?token=' . $cita['token'])) ?>">
                    <i class="bi bi-link-45deg"></i> Copiar enlace de cancelación
                </button>
            </div>
        </div>
        <form method="post" data-confirmar="¿Eliminar esta cita definitivamente? Si solo no asistirá, mejor cámbiala a «Cancelada».">
            <?= csrf_campo() ?>
            <button class="btn btn-outline-danger btn-sm w-100" name="accion" value="eliminar"><i class="bi bi-trash"></i> Eliminar cita</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php admin_fin('<script>
document.getElementById("servicio").addEventListener("change", e => {
    const op = e.target.selectedOptions[0];
    document.getElementById("duracion").value = op.dataset.duracion;
    document.getElementById("precio").value = op.dataset.precio;
});
const copiar = document.getElementById("copiarEnlace");
if (copiar) copiar.addEventListener("click", async () => {
    await navigator.clipboard.writeText(copiar.dataset.enlace);
    copiar.innerHTML = "<i class=\"bi bi-check-lg\"></i> Enlace copiado";
});
</script>'); ?>
