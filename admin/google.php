<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

if (get('accion') === 'conectar' && google_configurado()) {
    redirigir(google_auth_url());
}

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'desconectar') {
        google_disconnect();
        flash('success', 'Google Calendar desconectado.');
    } elseif ($accion === 'opciones') {
        config_set('google_bloquear', post('google_bloquear') === '1' ? '1' : '0');
        config_set('google_invitar', post('google_invitar') === '1' ? '1' : '0');
        flash('success', 'Opciones guardadas.');
    } elseif ($accion === 'sincronizar') {
        // Envía a Google todas las citas futuras activas (crea las que falten y actualiza las demás)
        $ids = db()->query("SELECT id FROM citas WHERE inicio >= NOW() AND estado IN ('pendiente','confirmada')")->fetchAll(PDO::FETCH_COLUMN);
        $errores = 0;
        foreach ($ids as $id) {
            if ($error = google_sincronizar_cita((int)$id)) {
                $errores++;
                $ultimo = $error;
            }
        }
        flash($errores ? 'warning' : 'success', count($ids) . ' cita(s) procesadas' . ($errores ? ", $errores con error. Último: $ultimo" : '.'));
    }
    redirigir('google.php');
}

$conectado = google_connected();
$estadoConexion = null;
if ($conectado) {
    try {
        google_access_token();
        $estadoConexion = true;
    } catch (Throwable $e) {
        $estadoConexion = $e->getMessage();
    }
}
$sinSincronizar = (int)db()->query("SELECT COUNT(*) FROM citas WHERE inicio >= NOW() AND estado IN ('pendiente','confirmada') AND google_event_id IS NULL")->fetchColumn();

admin_inicio('Google Calendar', 'google');
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <?php if (!google_configurado()): ?>
                    <p class="mb-0">Faltan las credenciales de Google en <code>includes/config.php</code>.</p>
                <?php elseif (!$conectado): ?>
                    <h2 class="h5"><i class="bi bi-x-circle text-danger"></i> No conectado</h2>
                    <p class="text-muted">Conecta la cuenta de Google donde quieres ver las citas. Cada reserva aparecerá en tu calendario y, si quieres, tus eventos personales bloquearán esos horarios en la web.</p>
                    <a href="?accion=conectar" class="btn btn-primario"><i class="bi bi-google me-1"></i> Conectar con Google</a>
                <?php elseif ($estadoConexion !== true): ?>
                    <h2 class="h5"><i class="bi bi-exclamation-triangle text-warning"></i> La conexión falló</h2>
                    <p class="small text-muted"><?= e($estadoConexion) ?></p>
                    <p class="small">Si la app de Google está en modo «Prueba», el permiso caduca a los 7 días. Vuelve a conectar.</p>
                    <a href="?accion=conectar" class="btn btn-primario">Volver a conectar</a>
                <?php else: ?>
                    <h2 class="h5"><i class="bi bi-check-circle-fill text-success"></i> Conectado</h2>
                    <p class="text-muted">Las citas se envían automáticamente a tu Google Calendar al crearlas, editarlas o cancelarlas.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post">
                            <?= csrf_campo() ?>
                            <button class="btn btn-outline-primary" name="accion" value="sincronizar">
                                <i class="bi bi-arrow-repeat"></i> Sincronizar citas futuras<?= $sinSincronizar ? " ($sinSincronizar sin enviar)" : '' ?>
                            </button>
                        </form>
                        <form method="post" data-confirmar="¿Desconectar Google Calendar? Las citas ya creadas seguirán en tu calendario.">
                            <?= csrf_campo() ?>
                            <button class="btn btn-outline-danger" name="accion" value="desconectar">Desconectar</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <form method="post" class="card">
            <div class="card-header">Opciones</div>
            <div class="card-body">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="opciones">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="google_bloquear" value="1" id="gBloquear" <?= config('google_bloquear') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="gBloquear"><strong>Mis eventos de Google bloquean horarios</strong></label>
                    <div class="form-text">Si tienes un evento personal (marcado como «Ocupado») no se podrá reservar a esa hora.</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="google_invitar" value="1" id="gInvitar" <?= config('google_invitar') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="gInvitar"><strong>Invitar al cliente por correo</strong></label>
                    <div class="form-text">Si el cliente dejó su correo, Google le envía la invitación y le avisa si la cita cambia o se cancela.</div>
                </div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primario">Guardar</button></div>
        </form>
    </div>
</div>
<?php admin_fin(); ?>
