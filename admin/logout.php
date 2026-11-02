<?php
require_once dirname(__DIR__) . '/includes/app.php';

// El token evita que otro sitio cierre la sesión con un simple enlace
if (hash_equals(csrf_token(), get('t'))) {
    cerrar_sesion();
}
redirigir('login.php');
