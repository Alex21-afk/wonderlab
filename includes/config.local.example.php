<?php
// Plantilla: copia este archivo como config.local.php y completa tus datos.
// config.local.php NO se sube a git porque contiene contraseñas y claves.

// ---- Base de datos (valores por defecto de XAMPP) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'wonderlab_citas');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Ruta del sitio dentro del servidor (http://localhost/Calendario) ----
define('BASE_URL', '/Calendario');

// ---- Google OAuth (Google Cloud Console > Clientes) ----
define('GOOGLE_CLIENT_ID', 'PEGA_AQUI_TU_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'PEGA_AQUI_TU_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/Calendario/oauth2callback.php');
define('GOOGLE_CALENDAR_ID', 'primary'); // 'primary' = calendario principal de la cuenta conectada
