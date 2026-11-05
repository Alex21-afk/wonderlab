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
            pintarMes();
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

    // ---------- Calendario ----------
    const fechaMin = form.dataset.min;
    const fechaMax = form.dataset.max;
    const diasAbiertos = JSON.parse(form.dataset.abiertos);
    const mesDias = document.getElementById('mesDias');
    const mesAnterior = document.getElementById('mesAnterior');
    const mesSiguiente = document.getElementById('mesSiguiente');
    const diaElegido = document.getElementById('diaElegido');
    const dos = n => String(n).padStart(2, '0');
    const aFecha = texto => { const [a, m, d] = texto.split('-').map(Number); return new Date(a, m - 1, d); };
    const clave = (a, m, d) => `${a}-${dos(m + 1)}-${dos(d)}`;
    const diaLargo = texto => aFecha(texto).toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });

    let mes = aFecha(fechaMin);
    mes.setDate(1);

    function pintarMes() {
        const a = mes.getFullYear();
        const m = mes.getMonth();
        document.getElementById('mesTitulo').textContent = mes.toLocaleDateString('es', { month: 'long', year: 'numeric' });
        mesAnterior.disabled = clave(a, m, 1) <= fechaMin;
        mesSiguiente.disabled = clave(a, m + 1, 1) > fechaMax;

        mesDias.innerHTML = '';
        for (let i = (mes.getDay() + 6) % 7; i > 0; i--) mesDias.appendChild(document.createElement('span')); // la semana empieza en lunes
        const total = new Date(a, m + 1, 0).getDate();
        for (let d = 1; d <= total; d++) {
            const valor = clave(a, m, d);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'dia';
            b.textContent = d;
            b.dataset.fecha = valor;
            b.setAttribute('aria-label', diaLargo(valor));
            b.setAttribute('aria-pressed', valor === fecha.value);
            b.disabled = valor < fechaMin || valor > fechaMax || !diasAbiertos.includes(new Date(a, m, d).getDay());
            b.classList.toggle('hoy', valor === fechaMin);
            b.classList.toggle('activo', valor === fecha.value);
            mesDias.appendChild(b);
        }
    }

    function elegirFecha(valor) {
        fecha.value = valor;
        mes = aFecha(valor);
        mes.setDate(1);
        pintarMes();
        cargarHoras();
    }

    mesDias.addEventListener('click', ev => {
        const b = ev.target.closest('.dia');
        if (!b) return;
        elegirFecha(b.dataset.fecha);
        mesDias.querySelector('.dia.activo').focus();
    });
    [[mesAnterior, -1], [mesSiguiente, 1]].forEach(([b, paso]) => b.addEventListener('click', () => {
        mes.setMonth(mes.getMonth() + paso);
        pintarMes();
    }));

    // Uñas de la portada: guardan el día y llevan a la reserva
    document.querySelectorAll('.una').forEach(a => a.addEventListener('click', () => {
        elegirFecha(a.dataset.fecha);
        if (servicioElegido()) {
            irAPaso(2);
        } else {
            diaElegido.textContent = `Reservarás el ${diaLargo(a.dataset.fecha)}. Elige el servicio para ver los horarios.`;
            diaElegido.classList.remove('d-none');
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

    function mostrarResumen() {
        const s = servicioElegido();
        const dia = diaLargo(fecha.value);
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
        fecha.value = ''; // reset() no limpia los campos ocultos
        diaElegido.classList.add('d-none');
        btnPaso2.disabled = true;
        limpiarHoras('Primero elige un día.');
        irAPaso(1);
    }
})();
