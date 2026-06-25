<?php
$user        = Session::user();
$currentUrl  = trim($_GET['url'] ?? '', '/');
$currentMod  = explode('/', $currentUrl)[0] ?? '';

function sidebarActive(string $mod, string $current): string {
    return $mod === $current ? 'active' : '';
}
?>
<nav id="mainNavbar" class="navbar navbar-vertical navbar-expand-lg scrollbar bg-white navbar-light">
    <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center mb-0 mb-lg-4" href="<?= url('dashboard') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--bs-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="fw-bold text-dark"><?= APP_NAME ?></span>
        </a>

        <!-- Toggler mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="sidebarCollapse">
            <ul class="navbar-nav mb-lg-7">

                <!-- Dashboard -->
                <li class="nav-item <?= sidebarActive('dashboard', $currentMod) ?: sidebarActive('', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('dashboard') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Dashboard
                    </a>
                </li>

                <!-- VENTAS -->
                <?php if (canView('ventas')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Ventas</small>
                </li>

                <li class="nav-item <?= sidebarActive('ventas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ventas/pos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        Punto de Venta
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= url('ventas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Listado de Ventas
                    </a>
                </li>
                <?php endif; ?>

                <!-- CAJA -->
                <?php if (canView('caja')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Caja</small>
                </li>

                <li class="nav-item <?= sidebarActive('caja', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('caja') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Caja y Turnos
                    </a>
                </li>
                <?php endif; ?>

                <!-- COMPRAS -->
                <?php if (canView('compras') || canView('proveedores')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Compras</small>
                </li>
                <?php endif; ?>

                <?php if (canView('compras')): ?>
                <li class="nav-item <?= sidebarActive('compras', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('compras') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Facturas de Compra
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('proveedores')): ?>
                <li class="nav-item <?= sidebarActive('proveedores', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('proveedores') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Proveedores
                    </a>
                </li>
                <?php endif; ?>

                <!-- STOCK -->
                <?php if (canView('stock')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Stock</small>
                </li>

                <li class="nav-item <?= sidebarActive('stock', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('stock') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        Control de Stock
                    </a>
                </li>
                <?php endif; ?>

                <!-- PRODUCCIÓN -->
                <?php if (canView('recetas')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Producción</small>
                </li>

                <li class="nav-item <?= sidebarActive('recetas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('recetas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>
                        Recetas
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= url('recetas/ordenes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Órdenes de Producción
                    </a>
                </li>
                <?php endif; ?>

                <!-- MAESTROS -->
                <?php if (canView('productos') || canView('materias_primas') || canView('insumos') || canView('categorias') || canView('clientes')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Maestros</small>
                </li>
                <?php endif; ?>

                <?php if (canView('productos')): ?>
                <li class="nav-item <?= sidebarActive('productos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('productos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        Productos
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('materias_primas')): ?>
                <li class="nav-item <?= sidebarActive('materias-primas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('materias-primas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Materias Primas
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('insumos')): ?>
                <li class="nav-item <?= sidebarActive('insumos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('insumos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                        Insumos
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('categorias')): ?>
                <li class="nav-item <?= sidebarActive('categorias', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('categorias') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        Categorías
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('clientes')): ?>
                <li class="nav-item <?= sidebarActive('clientes', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('clientes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Clientes
                    </a>
                </li>
                <?php endif; ?>

                <!-- REPORTES -->
                <?php if (canView('reportes')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Reportes</small>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= url('reportes/ventas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Ventas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('reportes/compras') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Compras
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('reportes/stock') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Stock
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('reportes/caja') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Caja
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('reportes/produccion') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Producción
                    </a>
                </li>
                <?php endif; ?>

                <!-- ECOMMERCE -->
                <?php if (canView('ecommerce')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Ecommerce</small>
                </li>

                <li class="nav-item <?= sidebarActive('ecommerce', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ecommerce/pedidos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Pedidos Online
                    </a>
                </li>
                <?php endif; ?>

                <!-- CHAT IA -->
                <?php if (canView('chat')): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Asistente IA</small>
                </li>

                <li class="nav-item <?= sidebarActive('chat', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('chat') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Chat con IA
                    </a>
                </li>
                <?php endif; ?>

                <!-- ADMIN -->
                <?php if ($user && $user['rol'] === 'admin'): ?>
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Administración</small>
                </li>
                <li class="nav-item <?= sidebarActive('usuarios', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('usuarios') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Usuarios
                    </a>
                </li>
                <li class="nav-item <?= sidebarActive('permisos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('permisos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Permisos
                    </a>
                </li>
                <li class="nav-item <?= sidebarActive('audit-log', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('audit-log') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Log de Auditoría
                    </a>
                </li>
                <li class="nav-item <?= sidebarActive('admin/backup', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('admin/backup') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Backup BD
                    </a>
                </li>
                <?php endif; ?>

                <!-- DOCUMENTACIÓN (todos los roles) -->
                <li class="nav-item nav-item-label mt-3">
                    <small class="text-uppercase text-body-secondary fw-semibold">Sistema</small>
                </li>
                <li class="nav-item <?= sidebarActive('docs', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('docs') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Documentación
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>
