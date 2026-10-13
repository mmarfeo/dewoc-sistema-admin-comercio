<?php
// Mismo criterio que layouts/sidebar.php para saber qué ítem marcar activo.
$currentUrlPortal = trim($_GET['url'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light" data-sidebar-behaviour="default">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d6efd">
    <title><?= e($pageTitle ?? 'Portal de Clientes') ?> — <?= APP_NAME ?></title>
    <link rel="icon" href="<?= asset('favicon/favicon.ico') ?>" sizes="any">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/theme.bundle.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<!-- ── Sidebar vertical — mismo componente de Dashly que usa el panel de staff
     (layouts/sidebar.php), acá con solo los 2 ítems que tiene el portal. OJO: el id
     tiene que ser "mainNavbar" a propósito (no "portalNavbar") — el posicionamiento
     fijo de 260px de ancho está en assets/css/app.css enganchado a ese id puntual,
     no a la clase; con otro id el sidebar no hereda el layout y queda roto. ── -->
<nav id="mainNavbar" class="navbar navbar-vertical navbar-expand-lg scrollbar">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center mb-0 mb-lg-4" href="<?= url('portal') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--bs-primary)" stroke-width="1.5" class="me-2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="fw-bold">Portal de Clientes</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#portalSidebarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="portalSidebarCollapse">
            <ul class="navbar-nav mb-lg-7">
                <li class="nav-item <?= $currentUrlPortal === 'portal' ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= url('portal') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Catálogo
                    </a>
                </li>
                <li class="nav-item <?= str_starts_with($currentUrlPortal, 'portal/pedido') ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= url('portal/pedidos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2h12v20l-6-4-6 4z"/></svg>
                        Mis pedidos
                    </a>
                </li>
                <li class="nav-item <?= $currentUrlPortal === 'portal/cuenta-corriente' ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= url('portal/cuenta-corriente') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Mi cuenta corriente
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav mt-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between" href="#" id="btnPushSubscribePortal" title="Activar notificaciones">
                        <span class="d-flex align-items-center"><svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Notificaciones</span>
                    </a>
                </li>
                <li class="nav-item <?= $currentUrlPortal === 'portal/cambiar-password' ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= url('portal/cambiar-password') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Cambiar contraseña
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('portal/logout') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Contenido principal ──────────────────────────────────── -->
<main class="main-content">
    <div class="d-flex align-items-center justify-content-between border-bottom bg-body px-3 py-2 mb-4">
        <span class="fw-semibold small"><?= e($clienteActual['nombre'] ?? '') ?></span>
    </div>
    <div id="pushSubscribeMsgPortal" class="alert alert-warning d-none py-2 small container-fluid"></div>

    <div class="container-fluid pb-5">
        <?= $content ?>
    </div>
</main>

<!-- Aviso de respuesta nueva en el chat de un pedido — no bloqueante, a diferencia del modal del cajero -->
<div class="modal fade" id="modalAvisoChatPortal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4">
                <div class="fs-1 mb-2">💬</div>
                <p class="mb-3">Tenés una respuesta nueva sobre uno de tus pedidos.</p>
                <!-- href se completa en pollEstadoPortal() con el pedido puntual que tiene la
                     respuesta — antes apuntaba siempre a /portal/pedidos, que no marca nada
                     como leído, así que este aviso volvía a aparecer en bucle sin salida. -->
                <a href="<?= url('portal/pedidos') ?>" id="btnVerPedidoAviso" class="btn btn-primary">Ver el mensaje</a>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/theme.bundle.js') ?>"></script>
<script src="<?= url('sw-register.js') ?>"></script>
<script>
const APP_BASE_PORTAL = '<?= rtrim(BASE_URL, '/') ?>';
const CSRF_TOKEN_PORTAL = '<?= Csrf::token() ?>';

// ── Sidebar en mobile (< 992px): #mainNavbar arranca corrido fuera de pantalla por
// CSS (assets/css/app.css) hasta que se le agrega .show — el panel de staff hace esto
// mismo desde assets/js/app.js, que acá no se carga (trae de más: chat flotante, dark
// mode, etc., nada de eso aplica al portal), así que se replica solo esta parte.
document.querySelectorAll('[data-bs-target="#portalSidebarCollapse"]').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('mainNavbar')?.classList.toggle('show'));
});
document.addEventListener('click', e => {
    const nav = document.getElementById('mainNavbar');
    if (!nav || !nav.classList.contains('show')) return;
    if (nav.contains(e.target)) return;
    if (e.target.closest('[data-bs-target="#portalSidebarCollapse"]')) return;
    nav.classList.remove('show');
});

// ── Toast (aviso flotante) — mismo componente que assets/js/app.js usa en el panel de
// staff, portado acá porque ese archivo no se carga en el portal (trae de más: chat
// flotante, dark mode, etc.). Antes portal/catalogo.php resolvía showToast() con un
// alert() nativo del navegador, que no tiene nada que ver con el diseño de la página.
const _toastIconsPortal = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    danger:  '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
};

function showToast(message, type = 'success') {
    const icon      = _toastIconsPortal[type] ?? _toastIconsPortal.info;
    const container = document.getElementById('toast-container') || _createToastContainerPortal();
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

function _createToastContainerPortal() {
    const div = document.createElement('div');
    div.id        = 'toast-container';
    div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    div.style.zIndex = '9999';
    document.body.appendChild(div);
    return div;
}

function _showModalPortal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}

// ── Push notifications del portal (mismo patrón que assets/js/app.js::inicializarPush()) ──
function _urlBase64ToUint8ArrayPortal(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

async function inicializarPushPortal() {
    const btn = document.getElementById('btnPushSubscribePortal');
    const msg = document.getElementById('pushSubscribeMsgPortal');
    if (!btn || !('serviceWorker' in navigator) || !('PushManager' in window)) { if (btn) btn.classList.add('disabled'); return; }

    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();

    function setEstado(suscripto) {
        btn.classList.remove('disabled');
        btn.title = suscripto ? 'Desactivar notificaciones' : 'Activar notificaciones';
    }
    setEstado(!!sub);

    btn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        btn.classList.add('disabled');
        msg.classList.add('d-none');
        try {
            const actual = await reg.pushManager.getSubscription();
            if (actual) {
                const endpoint = actual.endpoint;
                await actual.unsubscribe();
                const fd = new FormData();
                fd.append('_csrf_token', CSRF_TOKEN_PORTAL);
                fd.append('endpoint', endpoint);
                await fetch(`${APP_BASE_PORTAL}/portal/desuscribir`, { method: 'POST', body: fd });
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

            const r = await fetch(`${APP_BASE_PORTAL}/portal/vapid-public-key`);
            const { publicKey } = await r.json();
            const nuevaSub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: _urlBase64ToUint8ArrayPortal(publicKey) });
            const keys = nuevaSub.toJSON().keys;

            const fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN_PORTAL);
            fd.append('endpoint', nuevaSub.endpoint);
            fd.append('p256dh', keys.p256dh);
            fd.append('auth', keys.auth);
            await fetch(`${APP_BASE_PORTAL}/portal/suscribir`, { method: 'POST', body: fd });
            setEstado(true);
        } catch (e) {
            msg.textContent = 'No se pudo activar: ' + e.message;
            msg.classList.remove('d-none');
            setEstado(!!(await reg.pushManager.getSubscription()));
        }
    });
}
inicializarPushPortal();

// ── Poll de novedades en el chat (no bloqueante) — cada 30s, mismo patrón que ventas/pos.php ──
let _avisoChatMostrado = false;
async function pollEstadoPortal() {
    try {
        const r = await fetch(`${APP_BASE_PORTAL}/portal/pedido/estado-poll`);
        const d = await r.json();
        if (d.ok && d.hayNoLeidos && !_avisoChatMostrado) {
            _avisoChatMostrado = true;
            const btnAviso = document.getElementById('btnVerPedidoAviso');
            if (btnAviso && d.pedidoId) btnAviso.href = `${APP_BASE_PORTAL}/portal/pedido/chat?id=${d.pedidoId}`;
            _showModalPortal('modalAvisoChatPortal');
        }
    } catch { /* silencioso */ }
}
setInterval(pollEstadoPortal, 30000);
pollEstadoPortal();
</script>
</body>
</html>
