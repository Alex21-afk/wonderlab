<?php
require_once dirname(__DIR__) . '/includes/app.php';

if (usuario_actual()) {
    redirigir('index.php');
}
if (!hay_usuarios()) {
    redirigir('instalar.php');
}

$error = '';
$email = '';
if (es_post()) {
    csrf_verificar();
    $email = post('email');
    $resultado = intentar_login($email, (string)($_POST['password'] ?? ''));
    if ($resultado === true) {
        redirigir('index.php');
    }
    $error = $resultado;
}
$nombre = config('nombre_sitio', 'Wonderlab Beauty Studio');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Ingresar | <?= e($nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/admin.css') ?>" rel="stylesheet">
    <?= estilos_marca() ?>
</head>
<body class="login-fondo">
<div class="container">
    <div class="card mx-auto p-4" style="max-width: 400px">
        <div class="card-body">
            <div class="text-center mb-4">
                <?php if (config('logo')): ?>
                    <img src="<?= e(url(config('logo'))) ?>" alt="" style="max-height: 70px" class="mb-3">
                <?php endif; ?>
                <h1 class="h5 mb-1"><?= e($nombre) ?></h1>
                <p class="text-muted small mb-0">Panel de administración</p>
            </div>
            <?php if (get('expirada')): ?><div class="alert alert-info small">Tu sesión expiró. Ingresa de nuevo.</div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger small"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <?= csrf_campo() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Correo</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn btn-primario w-100">Ingresar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
