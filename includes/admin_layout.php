<?php
// Plantilla del panel: admin_inicio('Título', 'clave-menu') ... admin_fin()

const MENU_ADMIN = [
    'agenda'    => ['Agenda', 'bi-calendar3', 'index.php'],
    'citas'     => ['Citas', 'bi-list-check', 'citas.php'],
    'servicios' => ['Servicios', 'bi-stars', 'servicios.php'],
    'horarios'  => ['Horarios y reglas', 'bi-clock', 'horarios.php'],
    'galeria'   => ['Galería', 'bi-images', 'galeria.php'],
    'sitio'     => ['Sitio web', 'bi-brush', 'sitio.php'],
    'google'    => ['Google Calendar', 'bi-google', 'google.php'],
    'usuarios'  => ['Usuarios', 'bi-people', 'usuarios.php'],
];

function admin_inicio(string $titulo, string $activo, string $head = ''): void
{
    $nombre = config('nombre_sitio', 'Wonderlab Beauty Studio');
    $usuario = usuario_actual();
    $pendientes = (int)db()->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente' AND inicio >= NOW()")->fetchColumn();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($titulo) ?> | Panel <?= e($nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/admin.css') ?>" rel="stylesheet">
    <style>:root { --primario: <?= config_color('color_primario', '#b76e79') ?>; --oscuro: <?= config_color('color_secundario', '#2b2024') ?>; }</style>
    <?= $head ?>
</head>
<body>
<nav class="navbar navbar-dark barra-movil d-lg-none px-3">
    <button class="btn text-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#lateral" aria-label="Menú"><i class="bi bi-list fs-3"></i></button>
    <span class="navbar-brand mb-0"><?= e($nombre) ?></span>
</nav>
<div class="d-flex">
    <aside class="offcanvas-lg offcanvas-start lateral" tabindex="-1" id="lateral">
        <div class="offcanvas-header d-lg-none">
            <span class="text-white fw-semibold"><?= e($nombre) ?></span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#lateral"></button>
        </div>
        <div class="lateral-cuerpo">
            <a href="index.php" class="lateral-marca d-none d-lg-block">
                <?php if (config('logo')): ?>
                    <img src="<?= e(url(config('logo'))) ?>" alt="" class="lateral-logo">
                <?php endif; ?>
                <span><?= e($nombre) ?></span>
            </a>
            <ul class="nav flex-column">
                <?php foreach (MENU_ADMIN as $clave => [$texto, $icono, $enlace]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $clave === $activo ? 'active' : '' ?>" href="<?= $enlace ?>">
                            <i class="bi <?= $icono ?>"></i><?= $texto ?>
                            <?php if ($clave === 'citas' && $pendientes): ?><span class="badge text-bg-warning ms-auto"><?= $pendientes ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="lateral-pie">
                <a class="nav-link" href="<?= url() ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i>Ver sitio web</a>
                <a class="nav-link" href="logout.php?t=<?= csrf_token() ?>"><i class="bi bi-power"></i>Salir (<?= e($usuario['nombre']) ?>)</a>
            </div>
        </div>
    </aside>
    <main class="contenido flex-grow-1">
        <h1 class="h3 mb-4"><?= e($titulo) ?></h1>
        <?= flash_html() ?>
<?php
}

function admin_fin(string $scripts = ''): void
{
    ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirmación para formularios peligrosos: <form data-confirmar="¿Seguro?">
document.querySelectorAll('form[data-confirmar]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirmar)) e.preventDefault();
}));
</script>
<?= $scripts ?>
</body>
</html>
<?php
}
