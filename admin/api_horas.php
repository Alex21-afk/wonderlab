<?php
// GET ?cita=ID&fecha=YYYY-MM-DD -> horas libres para mover esa cita (sin contarla a ella misma como ocupada)
require_once dirname(__DIR__) . '/includes/app.php';

if (!usuario_actual()) {
    responder_json(['error' => 'No autorizado'], 401);
}

$cita = cita_obtener((int)get('cita'));
$fecha = get('fecha');
if (!$cita || !fecha_valida($fecha)) {
    responder_json(['horas' => [], 'mensaje' => 'Datos no válidos.'], 422);
}

$duracion = (int)((strtotime($cita['fin']) - strtotime($cita['inicio'])) / 60);
$horas = horas_disponibles(['duracion_min' => $duracion], $fecha, (int)$cita['id'], true);

if (!$horas) {
    $mensaje = horario_del_dia((int)date('w', strtotime($fecha)))
        ? 'No quedan horas libres ese día (o están bloqueadas). Puedes escribir una hora manualmente.'
        : 'Ese día no hay horario de atención. Puedes escribir una hora manualmente.';
}
responder_json([
    'horas'   => array_map(fn($h) => ['valor' => $h, 'texto' => hora12($h)], $horas),
    'mensaje' => $mensaje ?? '',
]);
