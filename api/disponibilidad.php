<?php
// GET ?servicio=ID&fecha=YYYY-MM-DD  ->  { horas: ["09:00", ...], mensaje }
require_once dirname(__DIR__) . '/includes/app.php';

$servicio = servicio_obtener((int)get('servicio'));
$fecha = get('fecha');

if (!$servicio || !fecha_valida($fecha)) {
    responder_json(['horas' => [], 'mensaje' => 'Datos no válidos.'], 422);
}
if (!horario_del_dia((int)date('w', strtotime($fecha)))) {
    responder_json(['horas' => [], 'mensaje' => 'Ese día no atendemos. Elige otra fecha.']);
}

$horas = horas_disponibles($servicio, $fecha);
responder_json([
    'horas'   => array_map(fn($h) => ['valor' => $h, 'texto' => hora12($h)], $horas),
    'mensaje' => $horas ? '' : 'No quedan horarios libres para ese día. Prueba con otra fecha.',
]);
