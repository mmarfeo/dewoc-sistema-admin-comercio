/* ── Dewoc — App JS ─────────────────────────────────────────── */

// ── DataTables: config por defecto ──────────────────────────
$.extend(true, $.fn.dataTable.defaults, {
    language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-AR.json'
    },
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
    responsive: true,
    stateSave: true,
    dom: "<'row mb-2'<'col-sm-12 col-md-6 d-flex gap-2'B><'col-sm-12 col-md-6'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
        {
            extend: 'colvis',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Columnas',
            className: 'btn btn-sm btn-outline-secondary',
            columns: ':not(:last-child)'
        },
        {
            extend: 'excelHtml5',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Excel',
            className: 'btn btn-sm btn-outline-success',
            exportOptions: { columns: ':not(:last-child)' }
        },
        {
            extend: 'pdfHtml5',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> PDF',
            className: 'btn btn-sm btn-outline-danger',
            exportOptions: { columns: ':not(:last-child)' },
            orientation: 'landscape',
            pageSize: 'A4'
        }
    ]
});

// ── Red de seguridad contra backdrops de modal huérfanos ─────────────────────
// Bootstrap 5 no soporta oficialmente modales apilados (ver el comentario de
// 'shown.bs.modal' más abajo) y esta app tiene varios lugares que abren uno
// arriba de otro (showConfirm() sobre un modal ya abierto, o el modal de
// impresión sobre "Venta registrada" en el POS — ver
// _cerrarModalAlTerminarImprimir() en ventas/pos.php, que hasta tiene que
// cerrar ESE modal a mano, sin pasar por la API de Bootstrap, por lo fragil que
// es esto). Si Bootstrap llega a agregar un backdrop de más al abrir uno rápido
// justo después de otro (una carrera real y conocida de Bootstrap 5, no
// exclusiva de esta app), ese backdrop de más queda huérfano para siempre: no
// hay ningún modal visible que lo "reclame" al cerrarse, así que nadie lo saca
// nunca. El síntoma es una pantalla que queda oscurecida/bloqueada sin ningún
// cuadro encima, como si "no se hubiera cerrado" algo — aunque en rigor sí se
// cerró todo, solo que el fondo se quedó pegado.
//
// Esta función es la reconciliación genérica: compara cuántos `.modal.show`
// hay realmente visibles contra cuántos `.modal-backdrop` sobrevive en el DOM,
// y saca los que sobren (siempre los últimos agregados, que son los más
// recientes y por ende los más propensos a haber quedado colgados de una
// apertura fallida). Si no queda NINGÚN modal abierto, además limpia
// `body.modal-open` y el `overflow`/`padding-right` que Bootstrap le pone al
// body al abrir un modal — si esas quedan pegadas con cero modales visibles,
// la página se ve "trabada" (sin scroll, oscurecida) aunque no haya ningún
// backdrop de por medio.
function _reconciliarModales() {
    const abiertos  = document.querySelectorAll('.modal.show').length;
    const backdrops = Array.from(document.querySelectorAll('.modal-backdrop'));
    let sobran = backdrops.length - abiertos;
    if (sobran > 0) {
        // Prioridad 1: los que ya perdieron "show" (quedaron en solo ".fade") — un backdrop
        // legítimo de un modal TODAVÍA abierto siempre la tiene, así que uno sin ella casi
        // seguro es el huérfano real (reportado en producción: invisible por opacity:0, pero
        // sigue tapando toda la pantalla en position:fixed). Se sacan estos primero, sin
        // importar en qué posición del DOM estén.
        for (const b of backdrops) {
            if (sobran <= 0) break;
            if (!b.classList.contains('show')) { b.remove(); sobran--; }
        }
        // Prioridad 2 (fallback): si todavía sobran, se sacan del final — los últimos
        // agregados son los más propensos a ser el sobrante de una apertura fallida.
        if (sobran > 0) {
            const restantes = Array.from(document.querySelectorAll('.modal-backdrop'));
            while (sobran > 0 && restantes.length) {
                restantes.pop().remove();
                sobran--;
            }
        }
    }
    if (abiertos === 0) {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
}
// Además de que pos.php la llama a mano al final de su cierre manual (que no
// pasa por Bootstrap y por ende nunca dispara 'hidden.bs.modal'), se engancha
// acá como red de seguridad general para TODA la app: cualquier cierre de
// modal que sí pase por la API de Bootstrap dispara este evento, y de paso
// deja limpio cualquier huérfano que haya quedado de antes. El pequeño delay
// es para no pisarle la transición de salida a Bootstrap (que remueve SU
// propio backdrop recién al terminar el fade) — sin él, a veces se ejecuta
// esto mientras Bootstrap todavía está por sacar el suyo, y termina faltando
// contar ese que está a mitad de camino.
document.addEventListener('hidden.bs.modal', () => setTimeout(_reconciliarModales, 350));

// Además del evento de arriba, un chequeo periódico — reportado en producción: después de
// facturar e imprimir la comanda, quedaba un backdrop huérfano SIN la clase "show" (fade
// nomás), invisible (opacity:0 por CSS) pero igual bloqueando toda la pantalla por estar en
// position:fixed cubriendo el viewport encima de todo lo demás — y como tapa incluso los
// botones del modal "Venta registrada" que sigue abierto atrás, la persona queda sin poder
// clickear NADA, ni siquiera algo que dispare un cierre de modal real y active la limpieza de
// 'hidden.bs.modal' de arriba. O sea: el escape normal (cerrar cualquier modal) queda
// bloqueado por el propio bug, así que hace falta algo que se dispare solo, sin depender de
// que el usuario pueda interactuar con nada. 1.5s es lo bastante seguido para que la persona
// nunca llegue a notarlo trabado, y la función es una consulta al DOM baratísima (nada de red
// ni de recálculo pesado) — sin costo real corriendo todo el turno de caja.
setInterval(_reconciliarModales, 1500);

// ── Toggle dark/light theme ──────────────────────────────────
// El tema guardado ya se aplica antes (script inline en el <head> de
// layouts/main.php, para evitar el flash en claro al recargar en modo oscuro);
// acá solo se conecta el botón para cambiarlo.
document.addEventListener('DOMContentLoaded', () => {

    const btn  = document.getElementById('btnThemeToggle');
    const html = document.documentElement;

    if (btn) {
        btn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next    = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('dewoc-theme', next);
        });
    }

    // ── Sidebar toggle (mobile: off-canvas / escritorio: ocultar y ganar ancho) ──
    if (localStorage.getItem('dewoc-sidebar-collapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
    document.querySelectorAll('[data-bs-target="#sidebarCollapse"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('mainNavbar')?.classList.toggle('show');
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('dewoc-sidebar-collapsed', collapsed);
        });
    });

    // Cerrar sidebar al hacer clic fuera en mobile
    // (el botón hamburguesa del header vive FUERA de #mainNavbar, así que hay que
    // excluirlo explícitamente: si no, el mismo click que lo abre lo vuelve a cerrar
    // acá mismo al burbujear hasta document, y visualmente "no hace nada").
    document.addEventListener('click', e => {
        const nav = document.getElementById('mainNavbar');
        if (!nav || !nav.classList.contains('show')) return;
        if (nav.contains(e.target)) return;
        if (e.target.closest('[data-bs-target="#sidebarCollapse"]')) return;
        nav.classList.remove('show');
    });

    // ── Auto-cerrar alertas flash ────────────────────────────
    if (typeof bootstrap !== 'undefined') {
        setTimeout(() => {
            document.querySelectorAll('.alert.alert-dismissible').forEach(a => {
                bootstrap.Alert.getOrCreateInstance(a)?.close();
            });
        }, 5000);
    }

    // ── Modales apilados (ej. showConfirm() llamado desde DENTRO de otro modal
    // ya abierto, como el selector de opciones del POS) ─────────────────────
    // Bootstrap 5 no soporta esto out-of-the-box: el modal y el backdrop nuevos
    // se crean con el mismo z-index que los anteriores, así que el backdrop del
    // segundo modal termina tapando visualmente al primero (que sigue abierto,
    // solo que oculto detrás). Se sube el z-index del modal más nuevo y de su
    // backdrop cada vez que se abre uno habiendo otro ya visible.
    document.addEventListener('shown.bs.modal', function (e) {
        const abiertos = document.querySelectorAll('.modal.show');
        if (abiertos.length <= 1) return;
        const zBase = 1050 + (abiertos.length - 1) * 20;
        e.target.style.zIndex = zBase + 10;
        const backdrops = document.querySelectorAll('.modal-backdrop');
        const ultimoBackdrop = backdrops[backdrops.length - 1];
        if (ultimoBackdrop) ultimoBackdrop.style.zIndex = String(zBase);
    });

    // ── Confirm dialog: botón OK ─────────────────────────────
    document.getElementById('btnConfirmOk')?.addEventListener('click', () => {
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirm')).hide();
        } else {
            // Dashly bundlea Bootstrap sin exponer window.bootstrap; usamos data-bs-dismiss
            document.querySelector('#modalConfirm [data-bs-dismiss="modal"]')?.click();
        }
        if (typeof _confirmCb === 'function') { _confirmCb(); _confirmCb = null; }
    });

    // ── Web Push: botón en la campanita del header, disponible para todos los usuarios ───
    inicializarPush();

    // ── Bandeja de notificaciones (ver docs/tareas-pendientes.md #3) ─────────────
    inicializarNotificaciones();

});

// ── Reconexión automática ante sesión expirada (ver Controller::requireAuth() y
// AuthController::reconectar()) — evita perder una venta/formulario a medio hacer cuando
// la sesión vence: se engancha sobre window.fetch en vez de tocar cada fetch() de cada
// vista una por una, así funciona para toda la app sin cambiar nada más.
(function () {
    const _fetchNativo = window.fetch;
    let _modalAbierto = false;

    window.fetch = async function (...args) {
        const resp = await _fetchNativo.apply(this, args);
        if (resp.status === 401 && !_modalAbierto) {
            try {
                const datos = await resp.clone().json();
                if (datos && datos.error === 'sesion_expirada') {
                    mostrarModalReconexion(datos.csrf_token);
                }
            } catch (e) { /* body no era JSON (ej. 401 de otro origen) — ignorar */ }
        }
        return resp;
    };

    function mostrarModalReconexion(csrfToken) {
        _modalAbierto = true;
        if (csrfToken) window.APP_CSRF = csrfToken;

        const emailInput = document.getElementById('reconexionEmail');
        if (emailInput && !emailInput.value) emailInput.value = window.APP_USER_EMAIL || '';

        const modalEl = document.getElementById('modalReconexion');
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            // Dashly bundlea Bootstrap sin exponer window.bootstrap — mismo truco que el modal de confirmación.
            let trigger = document.getElementById('_reconexionTrigger');
            if (!trigger) {
                trigger = document.createElement('button');
                trigger.id = '_reconexionTrigger';
                trigger.type = 'button';
                trigger.hidden = true;
                trigger.setAttribute('data-bs-toggle', 'modal');
                trigger.setAttribute('data-bs-target', '#modalReconexion');
                document.body.appendChild(trigger);
            }
            trigger.click();
        }
        setTimeout(() => document.getElementById('reconexionPassword')?.focus(), 300);
    }

    function cerrarModalReconexion() {
        _modalAbierto = false;
        const modalEl = document.getElementById('modalReconexion');
        if (typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        } else {
            document.getElementById('_reconexionDismiss')?.click();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('formReconexion');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const errEl = document.getElementById('reconexionError');
            const btn   = document.getElementById('btnReconectar');
            errEl.classList.add('d-none');
            btn.disabled = true;
            btn.textContent = 'Reconectando…';

            try {
                const fd = new FormData(form);
                fd.append('_csrf_token', window.APP_CSRF);
                // fetch() nativo a propósito (no el wrapper de arriba): este request nunca
                // debería devolver 401 por sesión expirada, y si fallara igual no tiene
                // sentido reabrir este mismo modal sobre sí mismo.
                const r = await _fetchNativo(`${window.APP_BASE}/auth/reconectar`, { method: 'POST', body: fd });
                const d = await r.json();

                if (d.ok) {
                    window.APP_CSRF = d.csrf_token || window.APP_CSRF;
                    // Los formularios tradicionales (no-fetch) de la página ya cargada siguen
                    // teniendo el token viejo en su input oculto — se actualiza para que, si el
                    // usuario los envía, no vuelvan a fallar por CSRF con la sesión ya renovada.
                    document.querySelectorAll('input[name="_csrf_token"]').forEach(inp => { inp.value = window.APP_CSRF; });
                    form.reset();
                    cerrarModalReconexion();
                    showToast('Sesión renovada. Volvé a intentar lo que estabas haciendo.', 'success');
                } else {
                    errEl.textContent = d.error || 'No se pudo reconectar.';
                    errEl.classList.remove('d-none');
                }
            } catch (err) {
                errEl.textContent = 'Error de conexión. Reintentá.';
                errEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Reconectar';
            }
        });
    });
})();

// ── Web Push: suscripción del navegador para recibir notificaciones ─────────
function urlBase64ToUint8Array(base64Url) {
    const padding = '='.repeat((4 - base64Url.length % 4) % 4);
    const base64  = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

async function inicializarPush() {
    const btn = document.getElementById('btnPushSubscribe');
    const msg = document.getElementById('pushSubscribeMsg');
    if (!btn) return;

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        btn.textContent = 'Push no soportado en este navegador';
        btn.disabled = true;
        return;
    }

    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();

    function setEstado(suscripto) {
        btn.disabled = false;
        btn.textContent = suscripto ? '🔕 Desactivar notificaciones' : '🔔 Activar notificaciones push';
        btn.classList.toggle('btn-outline-primary', !suscripto);
        btn.classList.toggle('btn-outline-secondary', suscripto);
    }
    setEstado(!!sub);

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        msg.classList.add('d-none');
        try {
            const actual = await reg.pushManager.getSubscription();
            if (actual) {
                const endpoint = actual.endpoint;
                await actual.unsubscribe();
                const fd = new FormData();
                fd.append('_csrf_token', window.APP_CSRF);
                fd.append('endpoint', endpoint);
                await fetch(`${window.APP_BASE}/solicitudes/desuscribir`, { method: 'POST', body: fd });
                setEstado(false);
                return;
            }

            if (Notification.permission === 'denied') {
                msg.textContent = 'Los permisos de notificación están bloqueados en el navegador.';
                msg.classList.remove('d-none');
                setEstado(false);
                return;
            }
            const permiso = await Notification.requestPermission();
            if (permiso !== 'granted') { setEstado(false); return; }

            const r = await fetch(`${window.APP_BASE}/solicitudes/vapid-public-key`);
            const { publicKey } = await r.json();

            const nuevaSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });
            const keys = nuevaSub.toJSON().keys;

            const fd = new FormData();
            fd.append('_csrf_token', window.APP_CSRF);
            fd.append('endpoint', nuevaSub.endpoint);
            fd.append('p256dh', keys.p256dh);
            fd.append('auth', keys.auth);
            await fetch(`${window.APP_BASE}/solicitudes/suscribir`, { method: 'POST', body: fd });

            setEstado(true);
        } catch (e) {
            msg.textContent = 'No se pudo activar: ' + e.message;
            msg.classList.remove('d-none');
            setEstado(!!(await reg.pushManager.getSubscription()));
        }
    });
}

// ── Bandeja de notificaciones del header (ver docs/tareas-pendientes.md #3) ──
function _escNotif(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function _fechaNotif(mysqlDatetime) {
    if (!mysqlDatetime) return '';
    const f = new Date(mysqlDatetime.replace(' ', 'T'));
    if (isNaN(f)) return mysqlDatetime;
    return f.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function cargarNotificaciones() {
    const badge = document.getElementById('badgeNotifCount');
    const lista = document.getElementById('listaNotificaciones');
    if (!badge || !lista) return;

    try {
        const r = await fetch(`${window.APP_BASE}/notificaciones/listar`);
        const d = await r.json();
        if (!d.ok) return;

        if (d.no_leidas > 0) {
            badge.textContent = d.no_leidas > 9 ? '9+' : d.no_leidas;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }

        if (!d.recientes.length) {
            lista.innerHTML = '<div class="text-center text-body-secondary py-3 small">Sin notificaciones pendientes</div>';
            return;
        }

        lista.innerHTML = d.recientes.map(n => `
            <a href="#" class="d-block text-decoration-none text-body py-2 px-1 border-bottom notif-item ${+n.leido ? '' : 'fw-semibold'}"
               data-id="${n.id}" data-url="${_escNotif(n.url || '')}" onclick="return _abrirNotificacion(this)">
                <div class="small">${_escNotif(n.titulo)}</div>
                ${n.cuerpo ? `<div class="small text-muted">${_escNotif(n.cuerpo)}</div>` : ''}
                <div class="text-muted" style="font-size:.7rem">${_fechaNotif(n.created_at)}</div>
            </a>
        `).join('');
    } catch (e) {
        // Silencioso: un fallo de polling no debe interrumpir el resto de la app.
    }
}

function _abrirNotificacion(el) {
    const id  = el.dataset.id;
    const url = el.dataset.url;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', window.APP_CSRF);
    fetch(`${window.APP_BASE}/notificaciones/marcar-leida`, { method: 'POST', body: fd })
        .finally(() => { if (url) window.location.href = url; });
    return false;
}

function inicializarNotificaciones() {
    if (!document.getElementById('badgeNotifCount')) return;
    cargarNotificaciones();
    setInterval(cargarNotificaciones, 60000);
}

// ── Helper: confirm dialog personalizado ─────────────────────
let _confirmCb = null;

/**
 * Reemplaza al browser confirm() nativo con un modal Bootstrap.
 * @param {string}   message    Mensaje principal
 * @param {Function} onConfirm  Callback al confirmar
 * @param {object}   opts       { type: 'danger'|'warning'|'primary', label: 'Confirmar', sub: '' }
 */
function showConfirm(message, onConfirm, opts = {}) {
    const type    = opts.type  ?? 'danger';
    const label   = opts.label ?? 'Confirmar';
    const sub     = opts.sub   ?? '';
    const modalEl = document.getElementById('modalConfirm');

    if (!modalEl) {
        if (confirm(message + (sub ? '\n' + sub : ''))) onConfirm();
        return;
    }

    document.getElementById('confirmMsgEl').textContent = message;
    const subEl = document.getElementById('confirmSubEl');
    if (subEl) { subEl.textContent = sub; subEl.style.display = sub ? '' : 'none'; }

    const btnOk = document.getElementById('btnConfirmOk');
    btnOk.className   = `btn btn-${type}`;
    btnOk.textContent = label;

    _confirmCb = onConfirm;

    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
        // Dashly bundlea Bootstrap internamente sin exponer window.bootstrap.
        // data-bs-toggle funciona igual porque Bootstrap registra event delegation al importarse.
        let trigger = document.getElementById('_confirmTrigger');
        if (!trigger) {
            trigger = document.createElement('button');
            trigger.id   = '_confirmTrigger';
            trigger.type = 'button';
            trigger.hidden = true;
            trigger.setAttribute('data-bs-toggle', 'modal');
            trigger.setAttribute('data-bs-target', '#modalConfirm');
            document.body.appendChild(trigger);
        }
        trigger.click();
    }
}

// ── Helper: toast con ícono ───────────────────────────────────
const _toastIcons = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="flex-shrink-0 me-2"><polyline points="20 6 9 17 4 12"/></svg>',
    danger:  '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="flex-shrink-0 me-2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="flex-shrink-0 me-2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="flex-shrink-0 me-2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
};

function showToast(message, type = 'success') {
    const icon      = _toastIcons[type] ?? _toastIcons.info;
    const container = document.getElementById('toast-container') || _createToastContainer();
    const id        = 'toast-' + Date.now();
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0 shadow-sm"
             role="alert" data-bs-autohide="true" data-bs-delay="4500">
            <div class="d-flex align-items-center px-1">
                <div class="toast-body fw-semibold d-flex align-items-center gap-1">
                    ${icon}${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    const el = document.getElementById(id);
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Toast.getOrCreateInstance(el).show();
    } else {
        el.classList.add('show');
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 4500);
    }
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function _createToastContainer() {
    const div = document.createElement('div');
    div.id        = 'toast-container';
    div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    div.style.zIndex = '9999';
    document.body.appendChild(div);
    return div;
}

// ── Helper: formato moneda ───────────────────────────────────
function formatMoneda(valor) {
    return '$ ' + parseFloat(valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── Doble click en una fila de tabla → abre la edición ───────
// Reutiliza el botón/link "Editar" que ya existe en la columna de Acciones de
// cada tabla (sea que abra un modal via onclick o navegue a una página), así
// funciona en todas las tablas del sistema sin tocar cada vista una por una.
// Si la tabla no tiene acción de editar (ej. ventas, pagos — son registros que
// se consultan, no se editan), cae al botón "Ver" como equivalente más cercano.
// Si el doble click cae sobre un control con comportamiento propio (link,
// botón, checkbox, badge con onclick, etc.) se lo deja actuar normalmente.
document.addEventListener('dblclick', function (e) {
    const row = e.target.closest('tbody tr');
    if (!row) return;
    if (e.target.closest('a, button, input, select, textarea, label, [onclick]')) return;

    const trigger = row.querySelector('[title="Editar"], [title^="Editar "]')
                 || row.querySelector('[title="Ver"], [title^="Ver "]');
    if (trigger) trigger.click();
});
