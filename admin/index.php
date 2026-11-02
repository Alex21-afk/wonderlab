<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$pdo = db();
$stats = [
    ['Citas hoy', 'bi-calendar-day', $pdo->query("SELECT COUNT(*) FROM citas WHERE DATE(inicio) = CURDATE() AND estado IN ('pendiente','confirmada')")->fetchColumn()],
    ['Por confirmar', 'bi-hourglass-split', $pdo->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente' AND inicio >= NOW()")->fetchColumn()],
    ['Próximos 7 días', 'bi-calendar-week', $pdo->query("SELECT COUNT(*) FROM citas WHERE inicio BETWEEN NOW() AND NOW() + INTERVAL 7 DAY AND estado IN ('pendiente','confirmada')")->fetchColumn()],
    ['Ingresos del mes', 'bi-cash-coin', dinero($pdo->query("SELECT COALESCE(SUM(precio), 0) FROM citas WHERE estado = 'completada' AND YEAR(inicio) = YEAR(CURDATE()) AND MONTH(inicio) = MONTH(CURDATE())")->fetchColumn())],
];
$citasHoy = $pdo->query("SELECT * FROM citas WHERE DATE(inicio) = CURDATE() AND estado <> 'cancelada' ORDER BY inicio")->fetchAll();
$rango = $pdo->query('SELECT MIN(hora_inicio) AS desde, MAX(hora_fin) AS hasta FROM horarios')->fetch();

admin_inicio('Agenda', 'agenda', '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>');
?>
<div class="row g-3 mb-4">
    <?php foreach ($stats as [$texto, $icono, $valor]): ?>
        <div class="col-6 col-xl-3">
            <div class="card h-100"><div class="card-body stat">
                <div class="stat-icono"><i class="bi <?= $icono ?>"></i></div>
                <div><div class="stat-valor"><?= e((string)$valor) ?></div><div class="stat-texto"><?= $texto ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Calendario</span>
                <a href="cita.php" class="btn btn-sm btn-primario"><i class="bi bi-plus-lg"></i> Nueva cita</a>
            </div>
            <div class="card-body"><div id="calendario"></div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Hoy, <?= e(fecha_humana(date('Y-m-d'), false)) ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($citasHoy as $c): ?>
                    <a href="cita.php?id=<?= (int)$c['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <strong><?= e(hora12($c['inicio'])) ?></strong><?= estado_badge($c['estado']) ?>
                        </div>
                        <div><?= e($c['cliente_nombre']) ?></div>
                        <small class="text-muted"><?= e($c['servicio_nombre']) ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$citasHoy): ?><li class="list-group-item text-muted small">No hay citas para hoy.</li><?php endif; ?>
            </ul>
        </div>
        <div class="card mt-3">
            <div class="card-body small">
                <?php foreach (ESTADOS_CITA as [$texto, , $color]): ?>
                    <span class="me-3 text-nowrap"><i class="bi bi-circle-fill" style="color: <?= $color ?>"></i> <?= $texto ?></span>
                <?php endforeach; ?>
                <span class="text-nowrap"><i class="bi bi-square-fill text-secondary opacity-25"></i> Bloqueado</span>
            </div>
        </div>
    </div>
</div>

<?php admin_fin('<script>
const movil = window.innerWidth < 768;
const cal = new FullCalendar.Calendar(document.getElementById("calendario"), {
    locale: "es",
    initialView: movil ? "listWeek" : "timeGridWeek",
    headerToolbar: movil
        ? { left: "prev,next", center: "title", right: "today" }
        : { left: "prev,next today", center: "title", right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek" },
    height: "auto",
    nowIndicator: true,
    allDaySlot: false,
    slotMinTime: ' . json_encode($rango['desde'] ?: '08:00:00') . ',
    slotMaxTime: ' . json_encode($rango['hasta'] ?: '20:00:00') . ',
    events: "api_eventos.php",
    dateClick: info => {
        const d = info.date;
        const p = n => String(n).padStart(2, "0");
        const fecha = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        location.href = `cita.php?fecha=${fecha}` + (info.allDay ? "" : `&hora=${p(d.getHours())}:${p(d.getMinutes())}`);
    },
});
cal.render();
</script>'); ?>
