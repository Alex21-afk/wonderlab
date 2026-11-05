<?php
// Página para que el cliente cancele su cita con el enlace que recibió al reservar
require_once __DIR__ . '/includes/app.php';

$cita = cita_por_token(get('token'));
$cancelada = false;
$error = '';

if ($cita && es_post()) {
    csrf_verificar();
    if (cita_puede_cancelarse($cita)) {
        cita_cambiar_estado((int)$cita['id'], 'cancelada');
        $cita = cita_obtener((int)$cita['id']);
        $cancelada = true;
    } else {
        $error = 'Esta cita ya no se puede cancelar en línea.';
    }
}

$nombre = config('nombre_sitio', 'Wonderlab Beauty Studio');
$whatsapp = whatsapp_url(config('whatsapp'), $cita ? 'Hola, necesito ayuda con mi cita del ' . fecha_humana($cita['inicio']) . '.' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Mi cita | <?= e($nombre) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Gloock&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/sitio.css') ?>" rel="stylesheet">
    <?= estilos_marca() ?>
</head>
<body class="pagina-cita d-flex align-items-center min-vh-100">
<div class="container py-5">
    <div class="card reserva-card mx-auto" style="max-width: 520px">
        <div class="card-body p-4 p-md-5 text-center">
            <a href="<?= url() ?>" class="marca d-block mb-4 text-decoration-none"><?= e($nombre) ?></a>

            <?php if (!$cita): ?>
                <p class="text-muted">El enlace no es válido o la cita no existe.</p>
            <?php else: ?>
                <?php if ($cancelada): ?>
                    <div class="alert alert-success">Tu cita fue cancelada. ¡Esperamos verte pronto!</div>
                <?php elseif ($error): ?>
                    <div class="alert alert-warning"><?= e($error) ?></div>
                <?php endif; ?>

                <h1 class="h4 mb-3"><?= e($cita['servicio_nombre']) ?></h1>
                <p class="fs-5 mb-1"><?= e(fecha_humana($cita['inicio'])) ?></p>
                <p class="mb-4">A nombre de <?= e($cita['cliente_nombre']) ?> · <?= estado_badge($cita['estado']) ?></p>

                <?php if (cita_puede_cancelarse($cita)): ?>
                    <form method="post" onsubmit="return confirm('¿Seguro que quieres cancelar tu cita?')">
                        <?= csrf_campo() ?>
                        <button class="btn btn-outline-danger px-4">Cancelar mi cita</button>
                    </form>
                <?php elseif (in_array($cita['estado'], ESTADOS_ACTIVOS, true)): ?>
                    <p class="small text-muted">Faltan menos de <?= (int)config('cancelacion_horas') ?> horas para tu cita, así que no se puede cancelar en línea. Escríbenos para ayudarte.</p>
                <?php endif; ?>

                <?php if ($whatsapp): ?>
                    <a href="<?= e($whatsapp) ?>" class="btn btn-success mt-3" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> Escribir por WhatsApp</a>
                <?php endif; ?>
            <?php endif; ?>

            <div class="mt-4"><a href="<?= url() ?>" class="small text-secondary">Volver al inicio</a></div>
        </div>
    </div>
</div>
</body>
</html>
