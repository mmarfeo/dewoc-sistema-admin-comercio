// ── Burbuja flotante del Chat IA (ver docs/tareas-pendientes.md, sección Help Desk) ──
// Panel liviano y global: comparte la MISMA conversación que /chat (mismo endpoint de
// envío, POST chat/mensaje), pero solo sabe pintar respuestas "livianas" (texto, sql,
// acción, reporte, guia_ayuda). Si la respuesta es una tarjeta de compra/acción pendiente
// (con selects editables, adjuntos), no la intenta armar acá — muestra un placeholder que
// manda a /chat, donde vive esa UI completa (app/Views/chat/index.php).
// Se espera a DOMContentLoaded porque este <script> se incluye ANTES que el partial
// chat-flotante.php en app/Views/layouts/main.php (el partial va al final del <body>) —
// sin esto, el widget todavía no existiría en el DOM cuando corre el script.
document.addEventListener('DOMContentLoaded', function () {
    const STORAGE_KEY = 'dewoc-chat-flotante-estado';

    const widget   = document.getElementById('cfWidget');
    if (!widget) return; // no está en esta pantalla (ej. estamos en /chat)

    const bubble    = document.getElementById('cfBubble');
    const panel     = document.getElementById('cfPanel');
    const btnCerrar   = document.getElementById('cfBtnCerrar');
    const mensajesEl  = document.getElementById('cfMensajes');
    const form        = document.getElementById('cfForm');
    const input        = document.getElementById('cfInput');

    let historialCargado = false;

    function leerEstado() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch { return {}; }
    }
    function guardarEstado(parcial) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...leerEstado(), ...parcial }));
    }

    function _esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function abrir() {
        widget.classList.add('cf-open');
        panel.classList.remove('d-none');
        guardarEstado({ open: true });
        if (!historialCargado) cargarHistorial();
        input.focus();
    }
    function cerrar() {
        widget.classList.remove('cf-open');
        panel.classList.add('d-none');
        guardarEstado({ open: false });
    }
    bubble.addEventListener('click', abrir);
    btnCerrar.addEventListener('click', cerrar);

    // Restaurar estado guardado (sobrevive la recarga completa de página que implica
    // cada navegación del sidebar, ya que esta app no es una SPA).
    const estado = leerEstado();
    if (estado.open) abrir();

    function limpiarVacio() {
        const vacio = mensajesEl.querySelector('.cf-vacio');
        if (vacio) vacio.remove();
    }

    function agregarUsuario(texto) {
        limpiarVacio();
        const div = document.createElement('div');
        div.className = 'cf-msg cf-msg-user';
        div.innerHTML = `<div class="cf-msg-bubble">${_esc(texto)}</div>`;
        mensajesEl.appendChild(div);
    }

    function botonesGuia(payload) {
        const irAhora = `<a href="${payload.url}" class="btn btn-sm btn-outline-primary">🔗 Ir ahora</a>`;
        const tour = payload.tour_id
            ? `<a href="${payload.url}${payload.url.includes('?') ? '&' : '?'}tour=${encodeURIComponent(payload.tour_id)}" class="btn btn-sm btn-primary">🧭 Tour</a>`
            : '';
        return `<div class="cf-msg-botones">${irAhora}${tour}</div>`;
    }

    // tipo: 'texto' | 'sql' | 'accion' | 'reporte' | 'error' | 'guia_ayuda' | 'pesado'
    function agregarBot(tipo, texto, extra) {
        extra = extra || {};
        limpiarVacio();
        const div = document.createElement('div');
        div.className = 'cf-msg cf-msg-bot' + (tipo === 'error' ? ' cf-msg-error' : '');

        if (tipo === 'pesado') {
            div.innerHTML = `<div class="cf-msg-bubble">📎 Esto necesita la pantalla completa.</div>
                <div class="cf-msg-botones"><a href="${window.APP_BASE}/chat" class="btn btn-sm btn-primary">Abrir chat completo</a></div>`;
            mensajesEl.appendChild(div);
            return;
        }

        let html = `<div class="cf-msg-bubble">${_esc(texto || '').replace(/\n/g, '<br>')}</div>`;
        if (tipo === 'guia_ayuda' && extra.payload) {
            html += botonesGuia(extra.payload);
        } else if (tipo === 'reporte' && extra.reporteUrl) {
            html += `<div class="cf-msg-botones"><a href="${window.APP_BASE}${extra.reporteUrl}" class="btn btn-sm btn-success">Ver reporte</a></div>`;
        }
        div.innerHTML = html;
        mensajesEl.appendChild(div);
    }

    function scrollAbajo() {
        mensajesEl.scrollTop = mensajesEl.scrollHeight;
    }

    async function cargarHistorial() {
        historialCargado = true;
        try {
            const r = await fetch(`${window.APP_BASE}/chat/historial-reciente`);
            const d = await r.json();
            if (!d.ok || !d.mensajes.length) return;

            limpiarVacio();
            d.mensajes.forEach(m => {
                if (m.rol === 'user') agregarUsuario(m.contenido);
                else agregarBot(m.tipo, m.contenido, { payload: m.payload });
            });
            scrollAbajo();
        } catch {
            // Silencioso: si falla, el panel arranca vacío, no rompe el resto de la app.
        }
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const texto = input.value.trim();
        if (!texto) return;

        agregarUsuario(texto);
        input.value = '';
        input.disabled = true;
        scrollAbajo();

        try {
            const fd = new FormData();
            fd.append('mensaje', texto);
            fd.append('_csrf_token', window.APP_CSRF);
            const r = await fetch(`${window.APP_BASE}/chat/mensaje`, { method: 'POST', body: fd });
            const d = await r.json();

            if (!d.ok) {
                agregarBot('error', d.error || 'Error desconocido.');
            } else if (d.tipo === 'confirmacion_compra' || d.tipo === 'confirmacion_accion') {
                agregarBot('pesado', '');
            } else if (d.tipo === 'guia_ayuda') {
                agregarBot('guia_ayuda', d.respuesta, { payload: d.payload });
            } else if (d.tipo === 'reporte') {
                agregarBot('reporte', d.respuesta, { reporteUrl: d.reporte_url });
            } else {
                agregarBot(d.tipo, d.respuesta);
            }
        } catch {
            agregarBot('error', 'No se pudo conectar con el servidor.');
        } finally {
            input.disabled = false;
            input.focus();
            scrollAbajo();
        }
    });
});
