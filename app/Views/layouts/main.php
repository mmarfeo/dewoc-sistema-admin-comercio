<!DOCTYPE html>
<html lang="es" data-theme="light" data-sidebar-behaviour="default">
<head>
    <meta charset="UTF-8">
    <!-- Aplica el tema guardado ANTES de que se carguen las hojas de estilo, para
         que no se vea un flash en modo claro al recargar con el modo oscuro activo. -->
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('dewoc-theme') || 'light');</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#0d6efd">
    <title><?= e($pageTitle ?? 'Panel') ?> — <?= APP_NAME ?></title>

    <!-- PWA -->
    <link rel="manifest" href="<?= url('manifest.json') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/pwa-icon-192.png') ?>">

    <!-- Favicon -->
    <link rel="icon" href="<?= asset('favicon/favicon.ico') ?>" sizes="any">

    <!-- Fuente -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap">

    <!-- Dashly CSS -->
    <link rel="stylesheet" href="<?= asset('css/theme.bundle.css') ?>?v=<?= @filemtime(BASE_PATH.'/assets/css/theme.bundle.css') ?: 1 ?>">

    <!-- Tabler Icons: las vistas usan clases "ti ti-*" pero el theme.bundle.css de Dashly
         no las incluye, así que se cargan aparte (CDN) o quedan sin ícono visible. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">

    <!-- DataTables CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css">

    <!-- App CSS propio -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= @filemtime(BASE_PATH.'/assets/css/app.css') ?: 1 ?>">

    <!-- jQuery en <head> para que los scripts inline de las vistas lo encuentren disponible -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<?php require BASE_PATH . '/app/Views/layouts/sidebar.php'; ?>

<!-- ── Contenido principal ──────────────────────────────────── -->
<main class="main-content">

    <!-- Header -->
    <?php require BASE_PATH . '/app/Views/layouts/header.php'; ?>

    <!-- Página -->
    <div class="container-fluid py-4">
        <?= $content ?>
    </div>

</main>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<!-- Dashly JS -->
<script src="<?= asset('js/theme.bundle.js') ?>?v=<?= @filemtime(BASE_PATH.'/assets/js/theme.bundle.js') ?: 1 ?>"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<!-- App JS -->
<script src="<?= asset('js/app.js') ?>?v=<?= @filemtime(BASE_PATH.'/assets/js/app.js') ?: 1 ?>"></script>
<!-- Tour guiado del help desk del Chat IA (arranca solo, ver ?tour= en la URL) -->
<script src="<?= asset('js/tour.js') ?>?v=<?= @filemtime(BASE_PATH.'/assets/js/tour.js') ?: 1 ?>"></script>
<!-- Burbuja flotante del Chat IA (no en /chat, que ya tiene la experiencia completa) -->
<?php if ($currentMod !== 'chat'): ?>
<script src="<?= asset('js/chat-flotante.js') ?>?v=<?= @filemtime(BASE_PATH.'/assets/js/chat-flotante.js') ?: 1 ?>"></script>
<?php endif; ?>
<!-- PWA -->
<script>
window.APP_BASE      = '<?= rtrim(BASE_URL, '/') ?>';
window.APP_CSRF      = '<?= Csrf::token() ?>';
window.APP_IS_ADMIN  = <?= json_encode((Session::user()['rol'] ?? '') === 'admin') ?>;
window.APP_USER_EMAIL = <?= json_encode(Session::user()['email'] ?? '') ?>;
</script>
<script src="<?= url('sw-register.js') ?>"></script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

<!-- ── Modal de confirmación global ─────────────────────────── -->
<div class="modal fade" id="modalConfirm" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="mb-3" style="font-size:2.2rem;line-height:1">⚠️</div>
                <p id="confirmMsgEl" class="fw-semibold mb-1 fs-6"></p>
                <p id="confirmSubEl" class="small text-muted mb-0"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0 gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmOk">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal de reconexión (sesión expirada a mitad de una acción) ─────────── -->
<!-- No se cierra con click afuera/Escape ni tiene botón "cancelar": si el usuario
     estaba en medio de una venta, lo que corresponde es reconectar, no perder el
     estado quedándose con la sesión vencida. Ver Controller::requireAuth() y
     AuthController::reconectar(). -->
<div class="modal fade" id="modalReconexion" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formReconexion">
                <div class="modal-body p-4 text-center">
                    <div class="mb-2" style="font-size:2.2rem;line-height:1">🔒</div>
                    <p class="fw-semibold mb-1 fs-6">Tu sesión expiró</p>
                    <p class="small text-muted mb-3">Ingresá tu contraseña para seguir sin perder lo que estabas haciendo.</p>
                    <input type="email" class="form-control form-control-sm mb-2" id="reconexionEmail" name="email" placeholder="Email" autocomplete="username" required>
                    <input type="password" class="form-control form-control-sm" id="reconexionPassword" name="password" placeholder="Contraseña" autocomplete="current-password" required>
                    <div id="reconexionError" class="small text-danger mt-2 d-none"></div>
                </div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <button type="submit" class="btn btn-primary px-4" id="btnReconectar">Reconectar</button>
                </div>
            </form>
            <button type="button" id="_reconexionDismiss" data-bs-dismiss="modal" hidden></button>
        </div>
    </div>
</div>

<!-- ── Banner de instalación PWA ─────────────────────────────── -->
<div id="pwa-install-banner" class="d-none">
    <span>📲 Instalá Dewoc en tu dispositivo</span>
    <button type="button" id="btn-install-pwa" class="btn btn-light btn-sm fw-semibold">Instalar</button>
    <button type="button" id="btn-dismiss-pwa" class="btn-close btn-close-white" aria-label="Cerrar"></button>
</div>

<!-- ── Burbuja flotante del Chat IA (ver docs/tareas-pendientes.md, sección Help Desk) ── -->
<?php if ($currentMod !== 'chat'): ?>
<?php require BASE_PATH . '/app/Views/layouts/chat-flotante.php'; ?>
<?php endif; ?>

</body>
</html>
