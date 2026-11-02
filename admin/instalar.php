<?php
// Crea el primer usuario administrador. Deja de funcionar en cuanto existe un usuario.
require_once dirname(__DIR__) . '/includes/app.php';

try {
    if (hay_usuarios()) {
        redirigir('login.php');
    }
} catch (PDOException $e) {
    exit('<p style="font-family:sans-serif">La base de datos no está lista. Importa <b>schema.sql</b> en phpMyAdmin y recarga esta página.</p>');
}

$errores = [];
$nombre = $email = '';
if (es_post()) {
    csrf_verificar();
    $nombre = post('nombre');
    $email = post('email');
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($nombre) < 2) {
        $errores[] = 'Escribe tu nombre.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo no es válido.';
    }
    if ($msg = validar_password($password)) {
        $errores[] = $msg;
    }
    if ($password !== ($_POST['password2'] ?? '')) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (!$errores) {
        db()->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
        iniciar_sesion(['id' => db()->lastInsertId(), 'nombre' => $nombre, 'email' => $email]);
        flash('success', '¡Listo! Tu cuenta fue creada. Empieza configurando tu sitio web.');
        redirigir('sitio.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instalación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/admin.css') ?>" rel="stylesheet">
    <?= estilos_marca() ?>
</head>
<body class="login-fondo">
<div class="container">
    <div class="card mx-auto p-4" style="max-width: 440px">
        <div class="card-body">
            <h1 class="h5">Crear administrador</h1>
            <p class="text-muted small">Esta cuenta podrá entrar al panel para gestionar citas y el sitio web.</p>
            <?php foreach ($errores as $err): ?><div class="alert alert-danger small py-2"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post">
                <?= csrf_campo() ?>
                <div class="mb-3"><label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" value="<?= e($nombre) ?>" required></div>
                <div class="mb-3"><label class="form-label">Correo</label>
                    <input type="email" class="form-control" name="email" value="<?= e($email) ?>" required autocomplete="username"></div>
                <div class="mb-3"><label class="form-label">Contraseña</label>
                    <input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con letras y números.</div></div>
                <div class="mb-4"><label class="form-label">Repetir contraseña</label>
                    <input type="password" class="form-control" name="password2" required autocomplete="new-password"></div>
                <button class="btn btn-primario w-100">Crear cuenta</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
