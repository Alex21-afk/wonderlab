<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$yo = usuario_actual();

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'crear') {
        $nombre = post('nombre');
        $email = post('email');
        $password = (string)($_POST['password'] ?? '');
        $existe = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
        $existe->execute([$email]);
        if (mb_strlen($nombre) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Revisa el nombre y el correo.');
        } elseif ($existe->fetchColumn()) {
            flash('danger', 'Ya existe un usuario con ese correo.');
        } elseif ($msg = validar_password($password)) {
            flash('danger', $msg);
        } else {
            db()->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Usuario creado.');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)post('id');
        if ($id === $yo['id']) {
            flash('danger', 'No puedes eliminar tu propio usuario.');
        } else {
            db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            flash('success', 'Usuario eliminado.');
        }
    } elseif ($accion === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $stmt->execute([$yo['id']]);
        $nueva = (string)($_POST['nueva'] ?? '');
        if (!password_verify((string)($_POST['actual'] ?? ''), (string)$stmt->fetchColumn())) {
            flash('danger', 'La contraseña actual no es correcta.');
        } elseif ($msg = validar_password($nueva)) {
            flash('danger', $msg);
        } elseif ($nueva !== ($_POST['nueva2'] ?? '')) {
            flash('danger', 'Las contraseñas nuevas no coinciden.');
        } else {
            db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $yo['id']]);
            flash('success', 'Contraseña actualizada.');
        }
    }
    redirigir('usuarios.php');
}

$usuarios = db()->query('SELECT id, nombre, email, creado_en FROM usuarios ORDER BY nombre')->fetchAll();

admin_inicio('Usuarios', 'usuarios');
?>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header">Usuarios con acceso al panel</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($usuarios as $u): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-medium"><?= e($u['nombre']) ?><?= (int)$u['id'] === $yo['id'] ? ' <span class="badge text-bg-light">Tú</span>' : '' ?></div>
                            <small class="text-muted"><?= e($u['email']) ?></small>
                        </div>
                        <?php if ((int)$u['id'] !== $yo['id']): ?>
                            <form method="post" data-confirmar="¿Quitar el acceso a <?= e($u['nombre']) ?>?">
                                <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" name="accion" value="eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="post" class="card">
            <div class="card-header">Agregar usuario</div>
            <div class="card-body row g-3">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
                <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                <div class="col-md-6"><label class="form-label">Correo</label><input type="email" class="form-control" name="email" required autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label">Contraseña</label><input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres, con letras y números.</div></div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primario">Agregar</button></div>
        </form>
    </div>
    <div class="col-xl-5">
        <form method="post" class="card">
            <div class="card-header">Cambiar mi contraseña</div>
            <div class="card-body">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="password">
                <div class="mb-3"><label class="form-label">Contraseña actual</label><input type="password" class="form-control" name="actual" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" name="nueva" required minlength="8" autocomplete="new-password"></div>
                <div class="mb-1"><label class="form-label">Repetir nueva contraseña</label><input type="password" class="form-control" name="nueva2" required autocomplete="new-password"></div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primario">Actualizar</button></div>
        </form>
    </div>
</div>
<?php admin_fin(); ?>
