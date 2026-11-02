<?php
// Google redirige aquí después de autorizar (debe coincidir con GOOGLE_REDIRECT_URI)
require_once __DIR__ . '/includes/app.php';
requiere_login();

$state = get('state');
$esperado = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);

if (get('error') !== '') {
    flash('danger', 'Google canceló la conexión: ' . get('error'));
} elseif (get('code') === '' || $esperado === '' || !hash_equals($esperado, $state)) {
    flash('danger', 'Solicitud inválida. Intenta conectar de nuevo.');
} else {
    try {
        google_exchange_code(get('code'));
        flash('success', '¡Google Calendar conectado! Las nuevas citas aparecerán en tu calendario.');
    } catch (Throwable $e) {
        flash('danger', 'No se pudo conectar: ' . $e->getMessage());
    }
}
redirigir(url('admin/google.php'));
