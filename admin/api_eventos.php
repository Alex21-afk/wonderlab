<?php
// Eventos para el calendario del panel (FullCalendar envía ?start=...&end=...)
require_once dirname(__DIR__) . '/includes/app.php';

if (!usuario_actual()) {
    responder_json(['error' => 'No autorizado'], 401);
}

$fmt = fn(string $v) => date('Y-m-d H:i:s', strtotime($v) ?: time());
$desde = $fmt(get('start'));
$hasta = $fmt(get('end'));

$eventos = [];

$stmt = db()->prepare("SELECT * FROM citas WHERE estado <> 'cancelada' AND inicio < ? AND fin > ?");
$stmt->execute([$hasta, $desde]);
foreach ($stmt as $c) {
    $eventos[] = [
        'title' => $c['cliente_nombre'] . ' · ' . $c['servicio_nombre'],
        'start' => str_replace(' ', 'T', $c['inicio']),
        'end'   => str_replace(' ', 'T', $c['fin']),
        'url'   => 'cita.php?id=' . $c['id'],
        'color' => ESTADOS_CITA[$c['estado']][2],
    ];
}

$stmt = db()->prepare('SELECT * FROM bloqueos WHERE inicio < ? AND fin > ?');
$stmt->execute([$hasta, $desde]);
foreach ($stmt as $b) {
    $eventos[] = [
        'title'   => $b['motivo'] ?: 'Bloqueado',
        'start'   => str_replace(' ', 'T', $b['inicio']),
        'end'     => str_replace(' ', 'T', $b['fin']),
        'display' => 'background',
        'color'   => '#9aa0a6',
    ];
}

responder_json($eventos);
