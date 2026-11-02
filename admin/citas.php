<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

// Cambio rápido de estado desde la lista
if (es_post()) {
    csrf_verificar();
    $aviso = cita_cambiar_estado((int)post('id'), post('estado'));
    flash($aviso ? 'warning' : 'success', $aviso ?: 'Estado actualizado.');
    redirigir('citas.php?' . http_build_query(array_intersect_key($_GET, array_flip(['estado', 'desde', 'hasta', 'q']))));
}

$estado = get('estado');
$desde  = get('desde', date('Y-m-d'));
$hasta  = get('hasta');
$q      = get('q');

$where = [];
$params = [];
if (isset(ESTADOS_CITA[$estado])) {
    $where[] = 'estado = ?';
    $params[] = $estado;
}
if (fecha_valida($desde)) {
    $where[] = 'inicio >= ?';
    $params[] = $desde . ' 00:00:00';
}
if (fecha_valida($hasta)) {
    $where[] = 'inicio < ?';
    $params[] = date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00';
}
if ($q !== '') {
    $where[] = '(cliente_nombre LIKE ? OR cliente_telefono LIKE ? OR cliente_email LIKE ? OR servicio_nombre LIKE ?)';
    array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
}
$sql = 'SELECT * FROM citas' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY inicio LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll();

admin_inicio('Citas', 'citas');
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small">Buscar</label>
                <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nombre, teléfono, servicio…">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (ESTADOS_CITA as $clave => [$texto]): ?>
                        <option value="<?= $clave ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= $texto ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small">Desde</label>
                <input type="date" class="form-control" name="desde" value="<?= e($desde) ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small">Hasta</label>
                <input type="date" class="form-control" name="hasta" value="<?= e($hasta) ?>">
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-dark flex-grow-1"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="citas.php?desde=" class="btn btn-outline-secondary" title="Ver todas">Todas</a>
                <a href="cita.php" class="btn btn-primario" title="Nueva cita"><i class="bi bi-plus-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Cliente</th><th>Servicio</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fecha_humana($c['inicio'])) ?></td>
                    <td>
                        <div class="fw-medium"><?= e($c['cliente_nombre']) ?></div>
                        <small class="text-muted"><?= e($c['cliente_telefono']) ?><?= $c['cliente_email'] ? ' · ' . e($c['cliente_email']) : '' ?></small>
                    </td>
                    <td><?= e($c['servicio_nombre']) ?><?php if ($c['origen'] === 'web'): ?> <i class="bi bi-globe2 text-muted" title="Reservada desde la web"></i><?php endif; ?></td>
                    <td><?= estado_badge($c['estado']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($wa = whatsapp_url($c['cliente_telefono'])): ?>
                            <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Estado</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach (ESTADOS_CITA as $clave => [$texto]): ?>
                                    <?php if ($clave === $c['estado']) continue; ?>
                                    <li>
                                        <form method="post">
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button class="dropdown-item" name="estado" value="<?= $clave ?>"><?= $texto ?></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if (in_array($c['estado'], ESTADOS_ACTIVOS, true)): ?>
                            <a href="reagendar.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Reagendar"><i class="bi bi-calendar-event"></i></a>
                        <?php endif; ?>
                        <a href="cita.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$citas): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No hay citas con esos filtros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_fin(); ?>
