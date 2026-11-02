<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'subir') {
        $subidas = 0;
        $stmt = db()->prepare('INSERT INTO galeria (imagen, titulo) VALUES (?, ?)');
        foreach (archivos_multiples('imagenes') as $archivo) {
            try {
                if ($ruta = guardar_subida($archivo)) {
                    $stmt->execute([$ruta, null]);
                    $subidas++;
                }
            } catch (RuntimeException $e) {
                flash('danger', $e->getMessage());
            }
        }
        if ($subidas) {
            flash('success', "$subidas imagen(es) agregada(s).");
        }
    } elseif ($accion === 'guardar') {
        db()->prepare('UPDATE galeria SET titulo = ?, orden = ? WHERE id = ?')
            ->execute([mb_substr(post('titulo'), 0, 150) ?: null, (int)post('orden'), (int)post('id')]);
        flash('success', 'Imagen actualizada.');
    } elseif ($accion === 'eliminar') {
        $stmt = db()->prepare('SELECT imagen FROM galeria WHERE id = ?');
        $stmt->execute([(int)post('id')]);
        if ($ruta = $stmt->fetchColumn()) {
            borrar_archivo($ruta);
            db()->prepare('DELETE FROM galeria WHERE id = ?')->execute([(int)post('id')]);
            flash('success', 'Imagen eliminada.');
        }
    }
    redirigir('galeria.php');
}

$imagenes = db()->query('SELECT * FROM galeria ORDER BY orden, id DESC')->fetchAll();

admin_inicio('Galería', 'galeria');
?>
<form method="post" enctype="multipart/form-data" class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="subir">
        <input type="file" class="form-control" style="max-width: 420px" name="imagenes[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
        <button class="btn btn-primario"><i class="bi bi-upload"></i> Subir fotos</button>
        <span class="form-text">Puedes elegir varias a la vez (hasta <?= (int)ini_get('max_file_uploads') ?>). Máximo <?= MAX_IMAGEN_MB ?> MB cada una.</span>
    </div>
</form>

<div class="row g-3 galeria-admin">
    <?php foreach ($imagenes as $img): ?>
        <div class="col-sm-6 col-lg-4 col-xxl-3">
            <div class="card h-100">
                <img src="<?= e(url($img['imagen'])) ?>" class="card-img-top" alt="">
                <div class="card-body">
                    <form method="post" class="row g-2">
                        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                        <div class="col-8"><input class="form-control form-control-sm" name="titulo" placeholder="Título (opcional)" value="<?= e($img['titulo']) ?>"></div>
                        <div class="col-4"><input type="number" class="form-control form-control-sm" name="orden" value="<?= (int)$img['orden'] ?>" title="Orden"></div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" name="accion" value="guardar">Guardar</button>
                            <button class="btn btn-sm btn-outline-danger" name="accion" value="eliminar" onclick="return confirm('¿Eliminar esta imagen?')"><i class="bi bi-trash"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$imagenes): ?><p class="text-muted">Aún no hay fotos. La sección Galería aparecerá en la web cuando subas la primera.</p><?php endif; ?>
</div>
<?php admin_fin(); ?>
