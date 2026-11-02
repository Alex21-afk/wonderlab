<?php
// Reagendar: mover una cita a otra fecha/hora conservando su duración
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin_layout.php';
requiere_login();

$cita = cita_obtener((int)get('id'));
if (!$cita) {
    flash('danger', 'La cita no existe.');
    redirigir('citas.php');
}
if (!in_array($cita['estado'], ESTADOS_ACTIVOS, true)) {
    flash('warning', 'Solo se pueden reagendar citas pendientes o confirmadas. Cambia su estado primero si quieres moverla.');
    redirigir('cita.php?id=' . $cita['id']);
}

$duracion = (int)((strtotime($cita['fin']) - strtotime($cita['inicio'])) / 60);
$fecha = date('Y-m-d', strtotime($cita['inicio']));
$hora = '';
$errores = [];
$cruces = [];
$bloqueos = [];

if (es_post()) {
    csrf_verificar();
    $fecha = post('fecha');
    $hora = post('hora_manual') !== '' ? post('hora_manual') : post('hora');

    if (!fecha_valida($fecha)) {
        $errores[] = 'Elige una fecha válida.';
    } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
        $errores[] = 'Elige una de las horas disponibles o escribe una hora.';
    } elseif (strtotime("$fecha $hora") < time()) {
        $errores[] = 'No se puede reagendar a una hora que ya pasó.';
    } elseif ("$fecha $hora:00" === $cita['inicio']) {
        $errores[] = 'Es la misma fecha y hora que ya tiene la cita.';
    }

    if (!$errores && post('forzar') !== '1') {
        $nuevoIni = "$fecha $hora:00";
        $nuevoFin = date('Y-m-d H:i:s', strtotime($nuevoIni) + $duracion * 60);
        $cruces = citas_que_se_cruzan($nuevoIni, $nuevoFin, (int)$cita['id']);
        $bloqueos = bloqueos_que_se_cruzan($nuevoIni, $nuevoFin);
    }

    if (!$errores && !$cruces && !$bloqueos) {
        $aviso = cita_reagendar($cita, $fecha, $hora);
        flash($aviso ? 'warning' : 'success', $aviso ?: 'Cita reagendada.');
        redirigir('cita.php?id=' . $cita['id'] . '&reagendada=1');
    }
}

admin_inicio('Reagendar cita', 'citas');
?>
<div class="row g-4">
    <div class="col-xl-8">
        <form method="post" class="card" id="formReagendar">
            <?= csrf_campo() ?>
            <div class="card-body">
                <?php foreach ($errores as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

                <?php if ($cruces || $bloqueos): ?>
                    <div class="alert alert-warning">
                        <strong>El nuevo horario <?= e(fecha_humana("$fecha $hora")) ?> tiene conflictos:</strong>
                        <ul class="mb-2">
                            <?php foreach ($cruces as $x): ?>
                                <li>Cita de <?= e($x['cliente_nombre']) ?> (<?= e($x['servicio_nombre']) ?>), <?= e(hora12($x['inicio'])) ?> – <?= e(hora12($x['fin'])) ?></li>
                            <?php endforeach; ?>
                            <?php foreach ($bloqueos as $b): ?>
                                <li>Horario bloqueado: <?= e(rango_humano($b['inicio'], $b['fin'])) ?><?= $b['motivo'] ? ' – ' . e($b['motivo']) : '' ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button class="btn btn-sm btn-warning" name="forzar" value="1">Reagendar de todas formas</button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-md-5">
                        <label class="form-label fw-medium" for="fecha">Nuevo día</label>
                        <input type="date" class="form-control form-control-lg" name="fecha" id="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($fecha) ?>" required>
                        <label class="form-label fw-medium mt-4" for="horaManual">O escribe la hora</label>
                        <input type="time" class="form-control" name="hora_manual" id="horaManual" step="300" value="<?= e($cruces || $bloqueos ? $hora : '') ?>">
                        <div class="form-text">Útil para atender fuera del horario normal. Si hay cruces, te avisaré antes de guardar.</div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-medium">Horas libres <span class="text-muted small">(duración: <?= e(duracion_texto($duracion)) ?>)</span></label>
                        <div id="horas" class="horas-admin"><p class="text-muted small">Cargando…</p></div>
                        <input type="hidden" name="hora" id="hora">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="cita.php?id=<?= (int)$cita['id'] ?>" class="btn btn-link text-secondary">Volver</a>
                <button class="btn btn-primario px-4" id="btnGuardar" disabled><i class="bi bi-calendar-check"></i> Reagendar</button>
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Cita actual</div>
            <div class="card-body">
                <p class="fw-semibold mb-1"><?= e($cita['cliente_nombre']) ?></p>
                <p class="mb-1"><?= e($cita['servicio_nombre']) ?></p>
                <p class="mb-2 text-decoration-line-through text-muted"><?= e(fecha_humana($cita['inicio'])) ?></p>
                <?= estado_badge($cita['estado']) ?>
                <p class="small text-muted mt-3 mb-0">Al reagendar se actualiza Google Calendar y el cambio queda anotado en las notas internas. Luego podrás avisar al cliente por WhatsApp.</p>
            </div>
        </div>
    </div>
</div>

<?php admin_fin('<script>
const fecha = document.getElementById("fecha");
const horas = document.getElementById("horas");
const hora = document.getElementById("hora");
const manual = document.getElementById("horaManual");
const btn = document.getElementById("btnGuardar");
const actualizarBoton = () => { btn.disabled = !(hora.value || manual.value); };

function mensaje(texto) {
    horas.innerHTML = "";
    const p = document.createElement("p");
    p.className = "text-muted small";
    p.textContent = texto;
    horas.appendChild(p);
}

async function cargar() {
    hora.value = "";
    actualizarBoton();
    if (!fecha.value) return mensaje("Elige un día.");
    mensaje("Cargando…");
    try {
        const r = await fetch(`api_horas.php?cita=' . (int)$cita['id'] . '&fecha=${encodeURIComponent(fecha.value)}`);
        const data = await r.json();
        if (!data.horas.length) return mensaje(data.mensaje || "No hay horas libres.");
        horas.innerHTML = "";
        data.horas.forEach(h => {
            const b = document.createElement("button");
            b.type = "button";
            b.className = "btn btn-sm btn-outline-secondary";
            b.textContent = h.texto;
            b.addEventListener("click", () => {
                horas.querySelectorAll(".btn").forEach(x => x.classList.remove("active"));
                b.classList.add("active");
                hora.value = h.valor;
                manual.value = "";
                actualizarBoton();
            });
            horas.appendChild(b);
        });
    } catch (e) {
        mensaje("No se pudieron cargar las horas.");
    }
}

fecha.addEventListener("change", cargar);
manual.addEventListener("input", () => {
    if (manual.value) {
        hora.value = "";
        horas.querySelectorAll(".btn").forEach(x => x.classList.remove("active"));
    }
    actualizarBoton();
});
cargar();
</script>'); ?>
