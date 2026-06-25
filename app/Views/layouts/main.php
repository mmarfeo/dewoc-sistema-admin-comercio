<!DOCTYPE html>
<html lang="es" data-theme="light" data-sidebar-behaviour="default">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
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
    <link rel="stylesheet" href="<?= asset('css/theme.bundle.css') ?>">

    <!-- DataTables CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.bootstrap5.min.css">

    <!-- App CSS propio -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
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
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Dashly JS -->
<script src="<?= asset('js/theme.bundle.js') ?>"></script>
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
<script src="<?= asset('js/app.js') ?>"></script>
<!-- PWA -->
<script src="<?= url('sw-register.js') ?>"></script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

</body>
</html>
