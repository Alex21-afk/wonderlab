<?php
// Contenido editable de la página pública (tabla configuracion)
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

// [clave, etiqueta, tipo, ayuda]
$secciones = [
    'general' => ['General', 'bi-gear', [
        ['nombre_sitio', 'Nombre del negocio', 'text', ''],
        ['eslogan', 'Eslogan', 'text', 'Aparece sobre el título de la portada.'],
        ['logo', 'Logo', 'imagen', 'Ideal PNG con fondo transparente. También se usa como ícono de la pestaña.'],
        ['color_primario', 'Color principal', 'color', 'Botones, enlaces y detalles.'],
        ['color_secundario', 'Color oscuro', 'color', 'Títulos, pie de página y menú del panel.'],
        ['moneda', 'Símbolo de moneda', 'text', 'Ej. $, S/, €'],
    ]],
    'portada' => ['Portada', 'bi-window', [
        ['hero_titulo', 'Título principal', 'text', ''],
        ['hero_subtitulo', 'Texto de la portada', 'textarea', ''],
        ['hero_boton', 'Texto del botón', 'text', ''],
        ['hero_imagen', 'Imagen de fondo', 'imagen', 'Horizontal, mínimo 1600 px de ancho. Si no subes ninguna, se usa el fondo ilustrado en tonos vino.'],
    ]],
    'nosotros' => ['Nosotros', 'bi-heart', [
        ['nosotros_titulo', 'Título', 'text', ''],
        ['nosotros_texto', 'Texto', 'textarea', 'Los saltos de línea se respetan.'],
        ['nosotros_imagen', 'Imagen', 'imagen', ''],
    ]],
    'video' => ['Video', 'bi-play-btn', [
        ['video_titulo', 'Título de la sección', 'text', ''],
        ['video_youtube', 'Enlace de YouTube', 'url', 'Ej. https://www.youtube.com/watch?v=… o un Short. Tiene prioridad sobre el archivo.'],
        ['video_archivo', 'O sube un video', 'video', 'MP4 o WEBM, máximo ' . MAX_VIDEO_MB . ' MB. Para videos largos usa YouTube.'],
    ]],
    'contacto' => ['Contacto y redes', 'bi-telephone', [
        ['telefono', 'Teléfono', 'text', ''],
        ['whatsapp', 'WhatsApp', 'text', 'Número que recibe mensajes. Activa el botón flotante.'],
        ['codigo_pais', 'Código de país', 'text', 'Se agrega a los números sin código para los enlaces de WhatsApp. Ej. 57 Colombia, 51 Perú, 593 Ecuador, 52 México.'],
        ['email', 'Correo', 'email', ''],
        ['direccion', 'Dirección', 'text', ''],
        ['mapa_url', 'Mapa de Google', 'textarea', 'En Google Maps: Compartir > Insertar un mapa > Copiar HTML, y pégalo aquí.'],
        ['instagram', 'Instagram', 'url', ''],
        ['facebook', 'Facebook', 'url', ''],
        ['tiktok', 'TikTok', 'url', ''],
    ]],
    'reservas' => ['Textos de reserva', 'bi-chat-heart', [
        ['politicas', 'Condiciones de la reserva', 'textarea', 'El cliente debe aceptarlas antes de confirmar. Déjalo vacío para no pedirlo.'],
        ['reserva_mensaje', 'Mensaje al confirmar', 'textarea', 'Se muestra al terminar la reserva.'],
        ['pie_texto', 'Texto del pie de página', 'text', ''],
    ]],
];

if (es_post()) {
    csrf_verificar();
    $errores = [];
    foreach ($secciones as [, , $campos]) {
        foreach ($campos as [$clave, $etiqueta, $tipo]) {
            if ($tipo === 'imagen' || $tipo === 'video') {
                try {
                    if ($nuevo = subir_archivo($clave, $tipo)) {
                        borrar_archivo(config($clave));
                        config_set($clave, $nuevo);
                    } elseif (post('quitar_' . $clave) === '1') {
                        borrar_archivo(config($clave));
                        config_set($clave, '');
                    }
                } catch (RuntimeException $e) {
                    $errores[] = "$etiqueta: " . $e->getMessage();
                }
                continue;
            }

            $valor = post($clave);
            if ($tipo === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
                $errores[] = "$etiqueta: color no válido.";
                continue;
            }
            if ($tipo === 'url' && $valor !== '' && !es_url_web($valor)) {
                $errores[] = "$etiqueta: el enlace debe empezar con https://";
                continue;
            }
            if ($clave === 'video_youtube' && $valor !== '' && !youtube_id($valor)) {
                $errores[] = 'No se reconoce el enlace de YouTube.';
                continue;
            }
            if ($tipo === 'email' && $valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "$etiqueta: correo no válido.";
                continue;
            }
            if ($clave === 'mapa_url' && $valor !== '') {
                $valor = mapa_src($valor);
                if ($valor === '') {
                    $errores[] = 'Mapa: pega el código de "Insertar un mapa" de Google Maps.';
                    continue;
                }
            }
            config_set($clave, $valor);
        }
    }
    foreach ($errores as $err) {
        flash('danger', $err);
    }
    if (!$errores) {
        flash('success', 'Cambios guardados. Revisa cómo se ve en «Ver sitio web».');
    }
    redirigir('sitio.php?tab=' . urlencode(post('tab', 'general')));
}

$tabActiva = isset($secciones[get('tab')]) ? get('tab') : 'general';

admin_inicio('Sitio web', 'sitio');
?>
<form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_campo() ?>
    <input type="hidden" name="tab" id="tab" value="<?= e($tabActiva) ?>">
    <div class="card-header pb-0 border-0">
        <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" role="tablist">
            <?php foreach ($secciones as $clave => [$titulo, $icono]): ?>
                <li class="nav-item">
                    <button type="button" class="nav-link text-nowrap <?= $clave === $tabActiva ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $clave ?>" data-tab="<?= $clave ?>">
                        <i class="bi <?= $icono ?> me-1"></i><?= $titulo ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body tab-content">
        <?php foreach ($secciones as $claveSeccion => [, , $campos]): ?>
            <div class="tab-pane fade <?= $claveSeccion === $tabActiva ? 'show active' : '' ?>" id="tab-<?= $claveSeccion ?>">
                <div class="row g-4">
                    <?php foreach ($campos as [$clave, $etiqueta, $tipo, $ayuda]): $valor = config($clave); ?>
                        <div class="<?= in_array($tipo, ['textarea', 'imagen', 'video'], true) ? 'col-12' : 'col-md-6' ?>">
                            <label class="form-label fw-medium" for="<?= $clave ?>"><?= e($etiqueta) ?></label>
                            <?php if ($tipo === 'textarea'): ?>
                                <textarea class="form-control" id="<?= $clave ?>" name="<?= $clave ?>" rows="4"><?= e($clave === 'mapa_url' && $valor ? '<iframe src="' . $valor . '"></iframe>' : $valor) ?></textarea>
                            <?php elseif ($tipo === 'color'): ?>
                                <input type="color" class="form-control form-control-color" id="<?= $clave ?>" name="<?= $clave ?>" value="<?= e(config_color($clave, '#b76e79')) ?>">
                            <?php elseif ($tipo === 'imagen' || $tipo === 'video'): ?>
                                <?php if ($valor): ?>
                                    <div class="mb-2">
                                        <?php if ($tipo === 'imagen'): ?>
                                            <img src="<?= e(url($valor)) ?>" class="vista-previa" alt="">
                                        <?php else: ?>
                                            <video src="<?= e(url($valor)) ?>" class="vista-previa" controls preload="metadata"></video>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="quitar_<?= $clave ?>" value="1" id="quitar_<?= $clave ?>">
                                        <label class="form-check-label small" for="quitar_<?= $clave ?>">Quitar</label>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="<?= $clave ?>" name="<?= $clave ?>"
                                       accept="<?= $tipo === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif' ?>">
                            <?php else: ?>
                                <input type="<?= $tipo ?>" class="form-control" id="<?= $clave ?>" name="<?= $clave ?>" value="<?= e($valor) ?>">
                            <?php endif; ?>
                            <?php if ($ayuda): ?><div class="form-text"><?= e($ayuda) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <a href="<?= url() ?>" target="_blank" class="small"><i class="bi bi-box-arrow-up-right"></i> Ver sitio web</a>
        <button class="btn btn-primario px-4"><i class="bi bi-check-lg"></i> Guardar cambios</button>
    </div>
</form>
<?php admin_fin('<script>
document.querySelectorAll("[data-tab]").forEach(b => b.addEventListener("shown.bs.tab", () => {
    document.getElementById("tab").value = b.dataset.tab;
}));
</script>'); ?>
