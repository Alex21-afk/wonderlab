// Página pública: reserva en 3 pasos y visor de galería
(function () {
    const form = document.getElementById('formReserva');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // ---------- Galería ----------
    const modalGaleria = document.getElementById('modalGaleria');
    document.querySelectorAll('.galeria-item').forEach(a => a.addEventListener('click', ev => {
        ev.preventDefault();
        document.getElementById('galeriaImagen').src = a.dataset.imagen;
        document.getElementById('galeriaTitulo').textContent = a.dataset.titulo || '';
        bootstrap.Modal.getOrCreateInstance(modalGaleria).show();
    }));

    // Cerrar el menú móvil al elegir una sección
    document.querySelectorAll('#menu .nav-link, #menu .btn').forEach(a => a.addEventListener('click', () => {
        const menu = document.getElementById('menu');
        if (menu.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(menu).hide();
    }));

    if (!form) return;

    const api = form.dataset.api;
    const fecha = document.getElementById('fecha');
    const horas = document.getElementById('horas');
    const hora = document.getElementById('hora');
    const btnPaso2 = document.getElementById('btnPaso2');
    const btnPaso3 = document.getElementById('btnPaso3');
    const errorBox = document.getElementById('errorReserva');

    const servicioElegido = () => form.querySelector('input[name="servicio_id"]:checked');

    function irAPaso(n) {
        form.querySelectorAll('.paso').forEach(p => p.classList.toggle('d-none', p.dataset.paso !== String(n)));
        document.querySelectorAll('[data-paso-indicador]').forEach(li => {
            const i = Number(li.dataset.pasoIndicador);
            li.classList.toggle('activo', i === n);
            li.classList.toggle('hecho', i < n);
        });
        if (n === 2) {
            const s = servicioElegido();
            document.getElementById('servicioElegido').textContent = `${s.dataset.nombre} · ${s.dataset.detalle}`;
        }
        if (n === 3) mostrarResumen();
        document.getElementById('reservar').scrollIntoView({ behavior: 'smooth' });
    }

    form.querySelectorAll('[data-ir]').forEach(b => b.addEventListener('click', () => irAPaso(Number(b.dataset.ir))));

    function limpiarHoras(mensaje) {
        hora.value = '';
        btnPaso3.disabled = true;
        horas.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'text-muted small';
        p.textContent = mensaje;
        horas.appendChild(p);
    }

    form.querySelectorAll('input[name="servicio_id"]').forEach(r => r.addEventListener('change', () => {
        btnPaso2.disabled = false;
        if (fecha.value) cargarHoras(); else limpiarHoras('Primero elige un día.');
    }));

    // Botones "Reservar" de las tarjetas de servicios
    document.querySelectorAll('.elegir-servicio').forEach(b => b.addEventListener('click', () => {
        const radio = document.getElementById('srv' + b.dataset.servicio);
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
            irAPaso(1);
        }
    }));

    async function cargarHoras() {
        const s = servicioElegido();
        if (!s || !fecha.value) return;
        limpiarHoras('Buscando horarios…');
        try {
            const resp = await fetch(`${api}/disponibilidad.php?servicio=${encodeURIComponent(s.value)}&fecha=${encodeURIComponent(fecha.value)}`);
            const data = await resp.json();
            if (!data.horas.length) {
                limpiarHoras(data.mensaje || 'No hay horarios disponibles.');
                return;
            }
            horas.innerHTML = '';
            data.horas.forEach(h => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn';
                b.textContent = h.texto;
                b.addEventListener('click', () => {
                    horas.querySelectorAll('.btn').forEach(x => x.classList.remove('activa'));
                    b.classList.add('activa');
                    hora.value = h.valor;
                    hora.dataset.texto = h.texto;
                    btnPaso3.disabled = false;
                });
                horas.appendChild(b);
            });
        } catch (e) {
            limpiarHoras('No se pudieron cargar los horarios. Inténtalo de nuevo.');
        }
    }
    fecha.addEventListener('change', cargarHoras);

    function mostrarResumen() {
        const s = servicioElegido();
        const [a, m, d] = fecha.value.split('-').map(Number);
        const dia = new Date(a, m - 1, d).toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
        const resumen = document.getElementById('resumen');
        resumen.innerHTML = '';
        const titulo = document.createElement('strong');
        titulo.textContent = s.dataset.nombre;
        const detalle = document.createElement('div');
        detalle.className = 'small text-muted';
        detalle.textContent = `${dia.charAt(0).toUpperCase() + dia.slice(1)} · ${hora.dataset.texto} · ${s.dataset.detalle}`;
        resumen.append(titulo, detalle);
    }

    form.addEventListener('submit', async ev => {
        ev.preventDefault();
        errorBox.classList.add('d-none');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        const btn = document.getElementById('btnConfirmar');
        const spinner = btn.querySelector('.spinner-border');
        btn.disabled = true;
        spinner.classList.remove('d-none');
        try {
            const resp = await fetch(`${api}/reservar.php`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: new FormData(form),
            });
            const data = await resp.json();
            if (!resp.ok) {
                errorBox.textContent = data.error || 'No se pudo reservar.';
                errorBox.classList.remove('d-none');
                if (resp.status === 409) { // el horario se ocupó mientras tanto
                    irAPaso(2);
                    cargarHoras();
                }
                return;
            }
            mostrarConfirmacion(data);
        } catch (e) {
            errorBox.textContent = 'Error de conexión. Inténtalo de nuevo.';
            errorBox.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

    function mostrarConfirmacion(data) {
        document.getElementById('confServicio').textContent = data.servicio;
        document.getElementById('confFecha').textContent = data.fecha;
        document.getElementById('confEstado').textContent = data.estado;
        document.getElementById('confCancelar').href = data.cancelar_url;
        const wa = document.getElementById('confWhatsapp');
        wa.classList.toggle('d-none', !data.whatsapp_url);
        if (data.whatsapp_url) wa.href = data.whatsapp_url;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacion')).show();

        form.reset();
        form.classList.remove('was-validated');
        btnPaso2.disabled = true;
        limpiarHoras('Primero elige un día.');
        irAPaso(1);
    }
})();
