<?php
// Contraseñas, claves y datos propios del servidor: includes/config.local.php (no se sube a git)
$configLocal = __DIR__ . '/config.local.php';
if (!is_file($configLocal)) {
    http_response_code(500);
    exit('Falta includes/config.local.php: copia includes/config.local.example.php con ese nombre y completa tus datos.');
}
require_once $configLocal;

// Zona horaria del negocio
define('APP_TIMEZONE', 'America/Bogota');

// Archivos subidos desde el panel
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');
define('MAX_IMAGEN_MB', 5);
define('MAX_VIDEO_MB', 40); // no puede superar upload_max_filesize de php.ini
