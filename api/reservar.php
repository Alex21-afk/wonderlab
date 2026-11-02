<?php
// POST: crea una cita desde la web pública
require_once dirname(__DIR__) . '/includes/app.php';

const MAX_RESERVAS_POR_HORA = 5; // por IP, para frenar abusos

if (!es_post()) {
    responder_json(['error' => 'Método no permitido.'], 405);
}
if (!csrf_valido()) {
    responder_json(['error' => 'La página caducó. Recárgala e inténtalo de nuevo.'], 403);
}
if (post('sitio_web') !== '') { // campo trampa: solo lo llenan los bots
    responder_json(['error' => 'No se pudo procesar la reserva.'], 400);
}

$stmt = db()->prepare("SELECT COUNT(*) FROM citas WHERE ip = ? AND origen = 'web' AND creado_en > NOW() - INTERVAL 1 HOUR");
$stmt->execute([ip_cliente()]);
if ((int)$stmt->fetchColumn() >= MAX_RESERVAS_POR_HORA) {
    responder_json(['error' => 'Has hecho demasiadas reservas seguidas. Escríbenos por WhatsApp, por favor.'], 429);
}

$servicio = servicio_obtener((int)post('servicio_id'));
$fecha    = post('fecha');
$hora     = post('hora');
$nombre   = post('cliente_nombre');
$telefono = post('cliente_telefono');
$email    = post('cliente_email');
$notas    = post('notas');

$errores = [];
if (!$servicio) {
    $errores[] = 'Elige un servicio.';
}
if (!fecha_valida($fecha) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $errores[] = 'Elige fecha y hora.';
}
if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
    $errores[] = 'Escribe tu nombre completo.';
}
if (!preg_match('/^\+?[\d\s\-()]{7,30}$/', $telefono)) {
    $errores[] = 'Escribe un teléfono válido.';
}
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) {
    $errores[] = 'El correo no es válido.';
}
if (mb_strlen($notas) > 500) {
    $errores[] = 'Las notas son demasiado largas.';
}
if (config('politicas') && post('acepto') !== '1') {
    $errores[] = 'Debes aceptar las condiciones de la reserva.';
}
if ($errores) {
    responder_json(['error' => implode(' ', $errores)], 422);
}

try {
    $estado = config('estado_inicial', 'pendiente') === 'confirmada' ? 'confirmada' : 'pendiente';
    $r = cita_crear([
        'servicio'         => $servicio,
        'fecha'            => $fecha,
        'hora'             => $hora,
        'cliente_nombre'   => $nombre,
        'cliente_telefono' => $telefono,
        'cliente_email'    => $email,
        'notas'            => $notas,
        'estado'           => $estado,
        'origen'           => 'web',
    ]);
} catch (DomainException $e) {
    responder_json(['error' => $e->getMessage()], 409);
}

$cita = cita_obtener($r['id']);
responder_json([
    'ok'           => true,
    'servicio'     => $cita['servicio_nombre'],
    'fecha'        => fecha_humana($cita['inicio']),
    'estado'       => $estado === 'pendiente' ? 'Te confirmaremos la cita por WhatsApp.' : 'Tu cita está confirmada.',
    'cancelar_url' => url_absoluta('cancelar.php?token=' . $r['token']),
    'whatsapp_url' => whatsapp_url(config('whatsapp'), mensaje_whatsapp_cita($cita)),
]);
