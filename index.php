<?php
require_once __DIR__ . '/includes/app.php';

try {
    $servicios = db()->query('SELECT * FROM servicios WHERE activo = 1 ORDER BY orden, nombre')->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('<p style="font-family:sans-serif">La base de datos no está lista. Importa <b>schema.sql</b> en phpMyAdmin.</p>');
}
$galeria = db()->query('SELECT * FROM galeria ORDER BY orden, id DESC')->fetchAll();

$horarioSemana = [];
foreach (db()->query('SELECT * FROM horarios ORDER BY hora_inicio') as $h) {
    $horarioSemana[(int)$h['dia_semana']][] = hora12($h['hora_inicio']) . ' – ' . hora12($h['hora_fin']);
}

$nombre       = config('nombre_sitio', 'Wonderlab Beauty Studio');
$logo         = config('logo');
$youtube      = youtube_id(config('video_youtube'));
$videoArchivo = config('video_archivo');
$mapa         = mapa_src(config('mapa_url'));
$whatsapp     = whatsapp_url(config('whatsapp'), 'Hola, quisiera información sobre sus servicios.');
$redes = array_filter([
    'instagram' => config('instagram'),
    'facebook'  => config('facebook'),
    'tiktok'    => config('tiktok'),
], 'es_url_web');
$fechaMax = date('Y-m-d', strtotime('+' . (int)config('dias_max', '60') . ' days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($nombre) ?> | Reserva tu cita</title>
    <meta name="description" content="<?= e(config('eslogan')) ?>">
    <?php if ($logo): ?><link rel="icon" href="<?= e(url($logo)) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/sitio.css') ?>" rel="stylesheet">
    <?= estilos_marca() ?>
</head>
<body>

<!-- Navegación -->
<nav class="navbar navbar-expand-lg fixed-top navbar-sitio">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#inicio">
            <?php if ($logo): ?>
                <img src="<?= e(url($logo)) ?>" alt="<?= e($nombre) ?>" class="logo">
            <?php else: ?>
                <span class="marca"><?= e($nombre) ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menú">
            <i class="bi bi-list fs-1"></i>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#servicios">Servicios</a></li>
                <li class="nav-item"><a class="nav-link" href="#nosotros">Nosotros</a></li>
                <?php if ($galeria): ?><li class="nav-item"><a class="nav-link" href="#galeria">Galería</a></li><?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="#contacto">Contacto</a></li>
                <li class="nav-item ms-lg-2"><a class="btn btn-primario px-4" href="#reservar">Reservar</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Portada -->
<?php $heroFoto = config('hero_imagen'); // si suben una foto en el panel, reemplaza al fondo ilustrado ?>
<header id="inicio" class="hero <?= $heroFoto ? 'text-white' : 'hero-claro' ?>"
        style="background-image: url('<?= e($heroFoto ? url($heroFoto) : url('assets/img/fondo_wonderlab_vino.svg')) ?>')">
    <div class="hero-capa"></div>
    <div class="container position-relative text-center">
        <p class="hero-eslogan"><?= e(config('eslogan')) ?></p>
        <h1 class="hero-titulo"><?= e(config('hero_titulo', $nombre)) ?></h1>
        <p class="hero-texto"><?= nl2br(e(config('hero_subtitulo'))) ?></p>
        <a href="#reservar" class="btn btn-primario btn-lg px-5 mt-3"><i class="bi bi-calendar-heart me-2"></i><?= e(config('hero_boton', 'Reservar mi cita')) ?></a>
    </div>
</header>

<!-- Servicios -->
<section id="servicios" class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <span class="sobretitulo">Lo que hacemos</span>
            <h2 class="titulo-seccion">Nuestros servicios</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($servicios as $s): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card servicio-card h-100">
                        <?php if ($s['imagen']): ?>
                            <img src="<?= e(url($s['imagen'])) ?>" class="card-img-top" alt="<?= e($s['nombre']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="imagen-vacia"><i class="bi bi-stars"></i></div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <?php if ($s['categoria']): ?><span class="categoria"><?= e($s['categoria']) ?></span><?php endif; ?>
                            <h3 class="h5 mt-1"><?= e($s['nombre']) ?></h3>
                            <p class="text-muted small flex-grow-1"><?= nl2br(e($s['descripcion'])) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="small text-muted"><i class="bi bi-clock me-1"></i><?= duracion_texto((int)$s['duracion_min']) ?></span>
                                <?php if ((float)$s['precio'] > 0): ?><span class="precio"><?= e(dinero($s['precio'])) ?></span><?php endif; ?>
                            </div>
                            <a href="#reservar" class="btn btn-outline-primario mt-3 elegir-servicio" data-servicio="<?= (int)$s['id'] ?>">Reservar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Nosotros -->
<section id="nosotros" class="seccion bg-suave">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <?php if (config('nosotros_imagen')): ?>
                    <img src="<?= e(url(config('nosotros_imagen'))) ?>" class="img-fluid rounded-4 shadow nosotros-img" alt="<?= e(config('nosotros_titulo')) ?>" loading="lazy">
                <?php else: ?>
                    <div class="imagen-vacia rounded-4 nosotros-img"><i class="bi bi-flower1"></i></div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <span class="sobretitulo">Conócenos</span>
                <h2 class="titulo-seccion mb-4"><?= e(config('nosotros_titulo', 'Sobre nosotras')) ?></h2>
                <div class="texto-largo"><?= nl2br(e(config('nosotros_texto'))) ?></div>
                <a href="#reservar" class="btn btn-primario mt-4 px-4">Agenda tu visita</a>
            </div>
        </div>
    </div>
</section>

<?php if ($youtube || $videoArchivo): ?>
<!-- Video -->
<section id="video" class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="titulo-seccion"><?= e(config('video_titulo', 'Conoce nuestro estudio')) ?></h2>
        </div>
        <div class="video-marco mx-auto">
            <?php if ($youtube): ?>
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow">
                    <iframe src="https://www.youtube-nocookie.com/embed/<?= e($youtube) ?>" title="Video" loading="lazy"
                            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            <?php else: ?>
                <video class="w-100 rounded-4 shadow" controls preload="metadata" playsinline>
                    <source src="<?= e(url($videoArchivo)) ?>">
                </video>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($galeria): ?>
<!-- Galería -->
<section id="galeria" class="seccion <?= ($youtube || $videoArchivo) ? 'bg-suave' : '' ?>">
    <div class="container">
        <div class="text-center mb-5">
            <span class="sobretitulo">Nuestro trabajo</span>
            <h2 class="titulo-seccion">Galería</h2>
        </div>
        <div class="row g-3">
            <?php foreach ($galeria as $g): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="#" class="galeria-item" data-imagen="<?= e(url($g['imagen'])) ?>" data-titulo="<?= e($g['titulo']) ?>">
                        <img src="<?= e(url($g['imagen'])) ?>" alt="<?= e($g['titulo'] ?: $nombre) ?>" loading="lazy">
                        <?php if ($g['titulo']): ?><span><?= e($g['titulo']) ?></span><?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Reservar -->
<section id="reservar" class="seccion reservar">
    <div class="container">
        <div class="text-center mb-5">
            <span class="sobretitulo">En línea, en menos de un minuto</span>
            <h2 class="titulo-seccion">Reserva tu cita</h2>
        </div>

        <div class="card reserva-card mx-auto">
            <div class="card-body p-4 p-md-5">
                <?php if (!$servicios): ?>
                    <p class="text-center text-muted mb-0">Pronto podrás reservar en línea.</p>
                <?php else: ?>
                <ol class="pasos">
                    <li class="activo" data-paso-indicador="1"><span>1</span> Servicio</li>
                    <li data-paso-indicador="2"><span>2</span> Fecha y hora</li>
                    <li data-paso-indicador="3"><span>3</span> Tus datos</li>
                </ol>

                <form id="formReserva" novalidate data-api="<?= e(url('api')) ?>">
                    <!-- Paso 1 -->
                    <div class="paso" data-paso="1">
                        <div class="row g-3">
                            <?php foreach ($servicios as $s): ?>
                                <div class="col-md-6">
                                    <input type="radio" class="btn-check" name="servicio_id" id="srv<?= (int)$s['id'] ?>" value="<?= (int)$s['id'] ?>"
                                           data-nombre="<?= e($s['nombre']) ?>" data-detalle="<?= e(duracion_texto((int)$s['duracion_min']) . ((float)$s['precio'] > 0 ? ' · ' . dinero($s['precio']) : '')) ?>">
                                    <label class="opcion-servicio" for="srv<?= (int)$s['id'] ?>">
                                        <strong><?= e($s['nombre']) ?></strong>
                                        <small><?= e(duracion_texto((int)$s['duracion_min'])) ?><?= (float)$s['precio'] > 0 ? ' · ' . e(dinero($s['precio'])) : '' ?></small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-primario px-4" data-ir="2" disabled id="btnPaso2">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Paso 2 -->
                    <div class="paso d-none" data-paso="2">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <label for="fecha" class="form-label fw-medium">Elige el día</label>
                                <input type="date" class="form-control form-control-lg" id="fecha" name="fecha"
                                       min="<?= date('Y-m-d') ?>" max="<?= $fechaMax ?>">
                                <p class="small text-muted mt-2 mb-0" id="servicioElegido"></p>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-medium">Horarios disponibles</label>
                                <div id="horas" class="horas"><p class="text-muted small">Primero elige un día.</p></div>
                                <input type="hidden" name="hora" id="hora">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-link text-secondary" data-ir="1"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" class="btn btn-primario px-4" data-ir="3" disabled id="btnPaso3">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Paso 3 -->
                    <div class="paso d-none" data-paso="3">
                        <div class="resumen mb-4" id="resumen"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cliente_nombre" class="form-label">Nombre completo *</label>
                                <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre" maxlength="120" required autocomplete="name">
                            </div>
                            <div class="col-md-6">
                                <label for="cliente_telefono" class="form-label">Teléfono / WhatsApp *</label>
                                <input type="tel" class="form-control" id="cliente_telefono" name="cliente_telefono" maxlength="30" required autocomplete="tel">
                            </div>
                            <div class="col-12">
                                <label for="cliente_email" class="form-label">Correo electrónico <span class="text-muted">(opcional)</span></label>
                                <input type="email" class="form-control" id="cliente_email" name="cliente_email" maxlength="150" autocomplete="email">
                            </div>
                            <div class="col-12">
                                <label for="notas" class="form-label">¿Algo que debamos saber? <span class="text-muted">(opcional)</span></label>
                                <textarea class="form-control" id="notas" name="notas" rows="2" maxlength="500"></textarea>
                            </div>
                            <!-- Campo trampa para bots: las personas no lo ven -->
                            <div class="campo-oculto" aria-hidden="true">
                                <label for="sitio_web">No llenar</label>
                                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                            </div>
                            <?php if (config('politicas')): ?>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="acepto" name="acepto" value="1" required>
                                        <label class="form-check-label small" for="acepto"><?= nl2br(e(config('politicas'))) ?></label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="alert alert-danger d-none mt-3" id="errorReserva"></div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-link text-secondary" data-ir="2"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="submit" class="btn btn-primario px-4" id="btnConfirmar">
                                <span class="spinner-border spinner-border-sm d-none me-1"></span>Confirmar cita
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Contacto -->
<section id="contacto" class="seccion bg-suave">
    <div class="container">
        <div class="text-center mb-5">
            <span class="sobretitulo">Te esperamos</span>
            <h2 class="titulo-seccion">Contacto y horarios</h2>
        </div>
        <div class="row g-4">
            <div class="col-lg-<?= $mapa ? '5' : '12' ?>">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <ul class="list-unstyled contacto-lista mb-4">
                            <?php if (config('direccion')): ?><li><i class="bi bi-geo-alt"></i><?= e(config('direccion')) ?></li><?php endif; ?>
                            <?php if (config('telefono')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', config('telefono'))) ?>"><?= e(config('telefono')) ?></a></li><?php endif; ?>
                            <?php if ($whatsapp): ?><li><i class="bi bi-whatsapp"></i><a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></li><?php endif; ?>
                            <?php if (config('email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e(config('email')) ?>"><?= e(config('email')) ?></a></li><?php endif; ?>
                        </ul>
                        <h3 class="h6 text-uppercase fw-semibold mb-3">Horario de atención</h3>
                        <table class="table table-sm horario-tabla mb-0">
                            <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $dia): ?>
                                <tr class="<?= (int)date('w') === $dia ? 'hoy' : '' ?>">
                                    <td><?= DIAS_SEMANA[$dia] ?></td>
                                    <td class="text-end"><?= isset($horarioSemana[$dia]) ? e(implode(', ', $horarioSemana[$dia])) : '<span class="text-muted">Cerrado</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php if ($redes): ?>
                            <div class="redes mt-4">
                                <?php foreach ($redes as $red => $enlace): ?>
                                    <a href="<?= e($enlace) ?>" target="_blank" rel="noopener" aria-label="<?= e($red) ?>"><i class="bi bi-<?= $red ?>"></i></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($mapa): ?>
                <div class="col-lg-7">
                    <div class="mapa rounded-4 overflow-hidden shadow-sm h-100">
                        <iframe src="<?= e($mapa) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa"></iframe>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="pie">
    <div class="container text-center">
        <p class="marca mb-2"><?= e($nombre) ?></p>
        <p class="small mb-0">© <?= date('Y') ?> <?= e($nombre) ?>. <?= e(config('pie_texto')) ?></p>
    </div>
</footer>

<?php if ($whatsapp): ?>
    <a href="<?= e($whatsapp) ?>" class="whatsapp-flotante" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
<?php endif; ?>

<!-- Confirmación de reserva -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-3">
            <div class="modal-body">
                <div class="icono-ok"><i class="bi bi-check-lg"></i></div>
                <h3 class="h4 mt-3">¡Cita reservada!</h3>
                <p class="mb-1" id="confServicio"></p>
                <p class="fw-semibold" id="confFecha"></p>
                <p class="text-muted small" id="confEstado"></p>
                <p class="small"><?= nl2br(e(config('reserva_mensaje'))) ?></p>
                <div class="d-grid gap-2 mt-3">
                    <a href="#" class="btn btn-success d-none" id="confWhatsapp" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> Avisar por WhatsApp</a>
                    <a href="#" class="btn btn-link btn-sm text-secondary" id="confCancelar">¿Necesitas cancelar? Guarda este enlace</a>
                </div>
            </div>
            <button type="button" class="btn btn-primario mx-3 mb-2" data-bs-dismiss="modal">Listo</button>
        </div>
    </div>
</div>

<!-- Visor de galería -->
<div class="modal fade" id="modalGaleria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <button type="button" class="btn-close btn-close-white ms-auto mb-2" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <img src="" alt="" class="img-fluid rounded-3" id="galeriaImagen">
            <p class="text-white text-center mt-2" id="galeriaTitulo"></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/sitio.js') ?>"></script>
</body>
</html>
