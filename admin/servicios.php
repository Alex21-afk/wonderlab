<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$editar = get('id') !== '' ? servicio_obtener((int)get('id'), false) : null;
$v = $editar ?: ['id' => 0, 'nombre' => '', 'categoria' => '', 'descripcion' => '', 'duracion_min' => 60, 'precio' => 0, 'imagen' => '', 'activo' => 1, 'orden' => 0];
$errores = [];

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');
    $id = (int)post('id');

    if ($accion === 'eliminar') {
        $s = servicio_obtener($id, false);
        if ($s) {
            borrar_archivo($s['imagen']);
            db()->prepare('DELETE FROM servicios WHERE id = ?')->execute([$id]); // las citas conservan el nombre
            flash('success', 'Servicio eliminado.');
        }
        redirigir('servicios.php');
    }

    if ($accion === 'activar') {
        db()->prepare('UPDATE servicios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        redirigir('servicios.php');
    }

    // Guardar (crear o editar)
    $actual = $id ? servicio_obtener($id, false) : null;
    $v = [
        'id'           => $id,
        'nombre'       => post('nombre'),
        'categoria'    => post('categoria'),
        'descripcion'  => post('descripcion'),
        'duracion_min' => (int)post('duracion_min'),
        'precio'       => post('precio', '0'),
        'imagen'       => $actual['imagen'] ?? '',
        'activo'       => post('activo') === '1' ? 1 : 0,
        'orden'        => (int)post('orden'),
    ];
    if (mb_strlen($v['nombre']) < 2) $errores[] = 'Escribe el nombre del servicio.';
    if ($v['duracion_min'] < 5 || $v['duracion_min'] > 720) $errores[] = 'La duración debe estar entre 5 y 720 minutos.';
    if (!is_numeric($v['precio']) || $v['precio'] < 0) $errores[] = 'El precio no es válido.';

    if (!$errores) {
        try {
            if ($nueva = subir_archivo('imagen')) {
                borrar_archivo($v['imagen']);
                $v['imagen'] = $nueva;
            } elseif (post('quitar_imagen') === '1') {
                borrar_archivo($v['imagen']);
                $v['imagen'] = '';
            }
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }

    if (!$errores) {
        $datos = [$v['nombre'], $v['categoria'] ?: null, $v['descripcion'] ?: null, $v['duracion_min'], $v['precio'], $v['imagen'] ?: null, $v['activo'], $v['orden']];
        if ($actual) {
            db()->prepare('UPDATE servicios SET nombre = ?, categoria = ?, descripcion = ?, duracion_min = ?, precio = ?, imagen = ?, activo = ?, orden = ? WHERE id = ?')
                ->execute([...$datos, $id]);
        } else {
            db()->prepare('INSERT INTO servicios (nombre, categoria, descripcion, duracion_min, precio, imagen, activo, orden) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute($datos);
        }
        flash('success', 'Servicio guardado.');
        redirigir('servicios.php');
    }
    $editar = $actual;
}

$servicios = db()->query('SELECT * FROM servicios ORDER BY orden, nombre')->fetchAll();
$categorias = db()->query('SELECT DISTINCT categoria FROM servicios WHERE categoria IS NOT NULL ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);

admin_inicio('Servicios', 'servicios');
?>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th></th><th>Servicio</th><th>Duración</th><th>Precio</th><th>Visible</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php foreach ($servicios as $s): ?>
                        <tr class="<?= $s['activo'] ? '' : 'opacity-50' ?>">
                            <td style="width: 70px">
                                <?php if ($s['imagen']): ?><img src="<?= e(url($s['imagen'])) ?>" class="miniatura" alt=""><?php else: ?><div class="miniatura bg-light d-flex align-items-center justify-content-center"><i class="bi bi-image text-muted"></i></div><?php endif; ?>
                            </td>
                            <td><div class="fw-medium"><?= e($s['nombre']) ?></div><small class="text-muted"><?= e($s['categoria']) ?></small></td>
                            <td class="text-nowrap"><?= duracion_texto((int)$s['duracion_min']) ?></td>
                            <td class="text-nowrap"><?= e(dinero($s['precio'])) ?></td>
                            <td>
                                <form method="post">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm <?= $s['activo'] ? 'btn-success' : 'btn-outline-secondary' ?>" name="accion" value="activar" title="Mostrar u ocultar en la web">
                                        <i class="bi <?= $s['activo'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" data-confirmar="¿Eliminar «<?= e($s['nombre']) ?>»? Las citas existentes se conservan.">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" name="accion" value="eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$servicios): ?><tr><td colspan="6" class="text-center text-muted py-4">Aún no hay servicios.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <form method="post" enctype="multipart/form-data" class="card" id="formulario">
            <div class="card-header d-flex justify-content-between">
                <span><?= $editar ? 'Editar servicio' : 'Nuevo servicio' ?></span>
                <?php if ($editar): ?><a href="servicios.php" class="small">+ Nuevo</a><?php endif; ?>
            </div>
            <div class="card-body">
                <?php foreach ($errores as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" maxlength="120" value="<?= e($v['nombre']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría <span class="text-muted small">(ej. Uñas, Pestañas)</span></label>
                    <input class="form-control" name="categoria" maxlength="80" list="categorias" value="<?= e($v['categoria']) ?>">
                    <datalist id="categorias"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"><?= e($v['descripcion']) ?></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label">Duración (min)</label>
                        <input type="number" class="form-control" name="duracion_min" min="5" max="720" step="5" value="<?= (int)$v['duracion_min'] ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Precio</label>
                        <input type="number" class="form-control" name="precio" min="0" step="0.01" value="<?= e((string)(float)$v['precio']) ?>">
                        <div class="form-text">0 = no mostrar</div>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Orden</label>
                        <input type="number" class="form-control" name="orden" value="<?= (int)$v['orden'] ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Imagen</label>
                    <?php if ($v['imagen']): ?>
                        <div class="mb-2"><img src="<?= e(url($v['imagen'])) ?>" class="vista-previa" alt=""></div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="quitar_imagen" value="1" id="quitarImg">
                            <label class="form-check-label small" for="quitarImg">Quitar imagen</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="form-text">JPG, PNG, WEBP o GIF. Máximo <?= MAX_IMAGEN_MB ?> MB.</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?= $v['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Visible y reservable en la web</label>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button class="btn btn-primario px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php admin_fin(); ?>
