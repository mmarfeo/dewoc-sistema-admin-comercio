<?php
$user        = Session::user();
$currentUrl  = trim($_GET['url'] ?? '', '/');
$currentMod  = explode('/', $currentUrl)[0] ?? '';

function sidebarActive(string $mod, string $current): string {
    return $mod === $current ? 'active' : '';
}

/**
 * Encabezado de sección del sidebar (ej. "Ventas", "Producción") convertido en toggle de
 * colapso: clickearlo expande/contrae los ítems de ese grupo. Los <li> de los ítems del
 * grupo llevan la clase "collapse show nav-group-{$groupSlug}" — Bootstrap soporta
 * data-bs-target como selector de clase (".nav-group-x") para togglear varios elementos
 * a la vez, así que no hace falta anidar un <ul> nuevo dentro del <li> (rompería el
 * buscador del sidebar, que recorre los <li> hijos directos de #sidebarNavList).
 */
function sidebarGroupLabel(string $groupSlug, string $label): void {
    ?>
    <li class="nav-item nav-item-label mt-3">
        <a href="#" class="nav-group-toggle d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target=".nav-group-<?= e($groupSlug) ?>" aria-expanded="true">
            <small class="text-uppercase text-body-secondary fw-semibold"><?= e($label) ?></small>
            <svg class="nav-group-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </a>
    </li>
    <?php
}
?>
<nav id="mainNavbar" class="navbar navbar-vertical navbar-expand-lg scrollbar">
    <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center mb-0 mb-lg-4" href="<?= url(homeUrl()) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--bs-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="fw-bold"><?= APP_NAME ?></span>
        </a>

        <!-- Toggler mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Buscador de funcionalidades -->
        <div class="sidebar-search-wrap mb-2">
            <svg class="sidebar-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="sidebarSearchInput" class="form-control sidebar-search-input" placeholder="Buscar función..." autocomplete="off">
        </div>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="sidebarCollapse">
            <ul class="navbar-nav mb-lg-7" id="sidebarNavList">

                <!-- Dashboard -->
                <?php if (canView('dashboard')): ?>
                <li class="nav-item <?= sidebarActive('dashboard', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('dashboard') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Dashboard
                    </a>
                </li>
                <?php endif; ?>

                <!-- RESUMEN EJECUTIVO -->
                <?php if (canView('resumen_ejecutivo')): ?>
                <li class="nav-item <?= sidebarActive('resumen-ejecutivo', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('resumen-ejecutivo') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Resumen Ejecutivo
                    </a>
                </li>
                <?php endif; ?>

                <!-- SOLICITUDES -->
                <?php if (canView('solicitudes')): ?>
                <li class="nav-item <?= sidebarActive('solicitudes', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('solicitudes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
                        Solicitudes
                    </a>
                </li>
                <?php endif; ?>

                <!-- VENTAS -->
                <?php if (canView('ventas_pos') || canView('ventas')): sidebarGroupLabel('ventas', 'Ventas'); endif; ?>

                <?php if (canView('ventas_pos')): ?>
                <li class="nav-item collapse show nav-group-ventas <?= sidebarActive('ventas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ventas/pos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        Punto de Venta
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('ventas')): ?>
                <li class="nav-item collapse show nav-group-ventas">
                    <a class="nav-link" href="<?= url('ventas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Listado de Ventas
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('ventas_anulaciones')): ?>
                <li class="nav-item collapse show nav-group-ventas">
                    <a class="nav-link" href="<?= url('ventas/solicitudes-anulacion') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Solicitudes de anulación
                    </a>
                </li>
                <?php endif; ?>

                <!-- FINANZAS -->
                <?php if (canView('resumen_economico') || canView('gastos') || canView('activos_fijos')): sidebarGroupLabel('finanzas', 'Finanzas'); endif; ?>

                <?php if (canView('resumen_economico')): ?>
                <li class="nav-item collapse show nav-group-finanzas <?= sidebarActive('resumen-economico', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('resumen-economico') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Resumen Económico
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('gastos')): ?>
                <li class="nav-item collapse show nav-group-finanzas <?= sidebarActive('gastos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('gastos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Gastos
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-finanzas <?= sidebarActive('gastos-recurrentes', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('gastos-recurrentes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.61"/><polyline points="21 3 21 9 15 9"/></svg>
                        Recurrentes
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('activos_fijos')): ?>
                <li class="nav-item collapse show nav-group-finanzas <?= sidebarActive('activos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('activos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v3"/></svg>
                        Activos Fijos
                    </a>
                </li>
                <?php endif; ?>

                <!-- CAJA -->
                <?php if (canView('caja')): sidebarGroupLabel('caja', 'Caja'); ?>

                <li class="nav-item collapse show nav-group-caja <?= sidebarActive('caja', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('caja') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Caja y Turnos
                    </a>
                </li>

                <?php if (canEdit('caja')): ?>
                <li class="nav-item collapse show nav-group-caja">
                    <a class="nav-link" href="<?= url('caja/diferencias') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3v18h18"/><path d="M7 15l4-4 4 4 5-5"/></svg>
                        Diferencias de Caja
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- COMPRAS -->
                <?php if (canView('compras') || canView('proveedores')): sidebarGroupLabel('compras', 'Compras'); endif; ?>

                <?php if (canView('compras')): ?>
                <li class="nav-item collapse show nav-group-compras <?= sidebarActive('compras', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('compras') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Facturas de Compra
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('proveedores')): ?>
                <li class="nav-item collapse show nav-group-compras <?= sidebarActive('proveedores', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('proveedores') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Proveedores
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('empleados')): ?>
                <li class="nav-item collapse show nav-group-compras <?= sidebarActive('empleados', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('empleados') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Empleados / Sueldos
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-compras <?= sidebarActive('feriados', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('feriados') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Feriados
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('pagos_proveedores')): ?>
                <li class="nav-item collapse show nav-group-compras <?= sidebarActive('pagos-proveedores', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('pagos-proveedores') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Pagos / Cta. Cte.
                    </a>
                </li>
                <?php endif; ?>

                <!-- STOCK -->
                <?php if (canView('stock') || canView('stock_inventario') || canView('lista_compras')): sidebarGroupLabel('stock', 'Stock'); ?>

                <?php if (canView('stock')): ?>
                <li class="nav-item collapse show nav-group-stock <?= sidebarActive('stock', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('stock') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        Control de Stock
                    </a>
                </li>
                <?php endif; ?>

                <!-- Para quien solo tiene stock_inventario (ej. cajero que cuenta a ciegas): entrada
                     propia al conteo físico, sin pasar por /stock (que muestra el stock del sistema). -->
                <?php if (canView('stock_inventario')): ?>
                <li class="nav-item collapse show nav-group-stock <?= sidebarActive('stock', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('stock/inventario') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Inventario físico
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('lista_compras')): ?>
                <li class="nav-item collapse show nav-group-stock <?= sidebarActive('lista-compras', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('lista-compras') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Lista de Compras
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- PRODUCCIÓN -->
                <?php if (canView('recetas')): sidebarGroupLabel('produccion', 'Producción'); ?>

                <li class="nav-item collapse show nav-group-produccion <?= sidebarActive('recetas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('recetas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>
                        Recetas
                    </a>
                </li>

                <li class="nav-item collapse show nav-group-produccion">
                    <a class="nav-link" href="<?= url('recetas/libro') ?>" target="_blank">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        Libro de Recetas
                    </a>
                </li>

                <li class="nav-item collapse show nav-group-produccion <?= sidebarActive('recetas-borrador', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('recetas-borrador') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11V6a3 3 0 0 1 6 0v5"/><path d="M5 11h14l-1.5 9a2 2 0 0 1-2 1.7H8.5a2 2 0 0 1-2-1.7L5 11z"/></svg>
                        Simulador de Recetas
                    </a>
                </li>

                <li class="nav-item collapse show nav-group-produccion">
                    <a class="nav-link" href="<?= url('recetas/ordenes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Órdenes de Producción
                    </a>
                </li>
                <?php endif; ?>

                <!-- MAESTROS -->
                <?php if (canView('productos') || canView('materias_primas') || canView('insumos') || canView('categorias') || canView('socios') || canView('clientes') || canView('opciones_productos')): sidebarGroupLabel('maestros', 'Maestros'); endif; ?>

                <?php if (canView('productos')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('productos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('productos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        Productos
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('materias_primas')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('materias-primas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('materias-primas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Materias Primas
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('materias_primas')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('ingredientes-genericos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ingredientes-genericos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Ingredientes Genéricos
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('insumos')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('insumos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('insumos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                        Insumos
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('categorias')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('categorias', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('categorias') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        Categorías
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('socios')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('socios', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('socios') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Socios
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('clientes')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('clientes', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('clientes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Clientes
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('cobros_clientes')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('cobros-clientes', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('cobros-clientes') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Cobros de Clientes
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canView('opciones_productos')): ?>
                <li class="nav-item collapse show nav-group-maestros <?= sidebarActive('opciones-productos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('opciones-productos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        Opciones/Adicionales
                    </a>
                </li>
                <?php endif; ?>

                <!-- REPORTES -->
                <?php
                // El encabezado de la sección se muestra si puede ver aunque sea un solo
                // reporte puntual — no solo cuando tiene el permiso general "reportes" en
                // bloque — para que asignar un reporte individual a un usuario no lo deje
                // sin encabezado bajo el cual aparecer en el menú.
                $puedeVerAlgunReporte = canView('reportes')
                    || canView('reportes_semanal') || canView('reportes_ventas') || canView('reportes_compras')
                    || canView('reportes_pagos') || canView('reportes_stock') || canView('reportes_caja')
                    || canView('reportes_produccion') || canView('reportes_nomina') || canView('reportes_mermas_desvios')
                    || canView('reportes_compras_precio_cero') || canView('reportes_costos_desviados')
                    || canView('reportes_ventas_sin_stock') || canView('reportes_proyecciones')
                    || canView('reportes_margen_producto');
                ?>
                <?php if ($puedeVerAlgunReporte): sidebarGroupLabel('reportes', 'Reportes'); ?>

                <?php if (canView('reportes_semanal')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/semanal') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Semanal
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_ventas')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/ventas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Ventas
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_compras')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/compras') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Compras
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_pagos')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/pagos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
                        Pagos
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_stock')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/stock') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Stock
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_caja')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/caja') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Caja
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_produccion')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/produccion') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        Producción
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_nomina')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/nomina') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Nómina
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_mermas_desvios')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/mermas-desvios') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Mermas y desvíos
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_compras_precio_cero')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/compras-precio-cero') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Compras a precio $0
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_costos_desviados')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/costos-desviados') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        Costos desviados
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_ventas_sin_stock')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/ventas-sin-stock') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Ventas sin stock
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_margen_producto')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes/margen-ventas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Margen de ventas
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_proyecciones')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('proyecciones') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.1-4-4L3 15.5"/></svg>
                        Proyecciones
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canView('reportes_ia')): ?>
                <li class="nav-item collapse show nav-group-reportes">
                    <a class="nav-link" href="<?= url('reportes-ia') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 8V4H8"/><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v4"/><path d="M9 13v4"/></svg>
                        Reportes IA
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- ECOMMERCE -->
                <?php if (canView('ecommerce')): sidebarGroupLabel('ecommerce', 'Ecommerce'); ?>

                <li class="nav-item collapse show nav-group-ecommerce <?= sidebarActive('ecommerce', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ecommerce/pedidos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Pedidos Online
                    </a>
                </li>
                <?php endif; ?>

                <!-- INTEGRACIONES (ver docs/integracion-menuapp-checklist.md) -->
                <?php if (canView('integraciones') && $user && $user['rol'] === 'admin'): sidebarGroupLabel('integraciones', 'Integraciones'); ?>

                <li class="nav-item collapse show nav-group-integraciones <?= sidebarActive('integraciones', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('integraciones/menuapp') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        MenuApp
                    </a>
                </li>
                <?php endif; ?>

                <!-- CHAT IA -->
                <?php if (canView('chat')): sidebarGroupLabel('chat', 'Asistente IA'); ?>

                <li class="nav-item collapse show nav-group-chat <?= sidebarActive('chat', $currentMod) ?: sidebarActive('', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('chat') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Chat con IA
                    </a>
                </li>
                <?php if (canView('galeria')): ?>
                <li class="nav-item collapse show nav-group-chat <?= sidebarActive('galeria', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('galeria') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        Galería
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($user && $user['rol'] === 'admin'): ?>
                <li class="nav-item collapse show nav-group-chat <?= sidebarActive('gemini-uso', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('gemini-uso') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Uso Gemini
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-chat <?= sidebarActive('gemini-log', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('gemini-log') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
                        Log Gemini
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-chat">
                    <a class="nav-link" href="<?= url('chat/accesos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Accesos directos
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- ADMIN -->
                <?php if ($user && $user['rol'] === 'admin'): sidebarGroupLabel('admin', 'Administración'); ?>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('usuarios', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('usuarios') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Usuarios
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('permisos', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('permisos') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Permisos
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('ayuda-temas', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('ayuda-temas') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Guías del Help Desk
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('audit-log', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('audit-log') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Log de Auditoría
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('analitica', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('analitica') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Analítica de Uso
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('admin/backup', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('admin/backup') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        Backup BD
                    </a>
                </li>
                <li class="nav-item collapse show nav-group-admin <?= sidebarActive('logs', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('logs') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Logs del Sistema
                    </a>
                </li>
                <?php endif; ?>

                <!-- DOCUMENTACIÓN -->
                <?php if (canView('docs')): sidebarGroupLabel('sistema', 'Sistema'); ?>
                <li class="nav-item collapse show nav-group-sistema <?= sidebarActive('docs', $currentMod) ?>">
                    <a class="nav-link" href="<?= url('docs') ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="18" width="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Documentación
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>

<script>
// Buscador del sidebar: filtra los links por su texto visible. Los <li> de
// tipo "nav-item-label" (los títulos de sección, ej. "Ventas", "Stock") se
// ocultan solos si ninguno de los ítems de ese grupo matcheó la búsqueda.
(function () {
    function normalizar(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    function filtrarSidebar(query) {
        const lista = document.getElementById('sidebarNavList');
        if (!lista) return;
        const q = normalizar(query);

        let grupoLabel = null;
        let grupoVisible = false;

        function cerrarGrupo() {
            if (grupoLabel) grupoLabel.style.display = grupoVisible ? '' : 'none';
        }

        Array.from(lista.children).forEach(li => {
            if (li.classList.contains('nav-item-label')) {
                cerrarGrupo();
                grupoLabel   = li;
                grupoVisible = false;
                return;
            }
            const visible = q === '' || normalizar(li.textContent).includes(q);
            // Los ítems ahora también son "collapse" (se pueden contraer clickeando el
            // título de su grupo) — con q==='' se limpia el inline style y el <li> vuelve
            // a depender de la clase .collapse/.show (lo que el usuario haya elegido). Con
            // una búsqueda activa, en cambio, hay que FORZAR "block" y no solo "" para
            // ganarle a ".collapse:not(.show){display:none}" si el grupo estaba contraído
            // — si no, buscar algo dentro de un grupo cerrado no mostraba nada.
            li.style.display = visible ? (q ? 'block' : '') : 'none';
            if (visible) grupoVisible = true;
        });
        cerrarGrupo();
    }

    const input = document.getElementById('sidebarSearchInput');
    if (input) input.addEventListener('input', () => filtrarSidebar(input.value));
})();

// Recordar qué secciones del sidebar quedaron contraídas, entre una página y otra.
// Esta es una app multi-página de toda la vida (cada link recarga), y el <li> de cada
// sección se renderiza siempre con "collapse show" desde el servidor — sin esto, apenas
// se navega a cualquier link (aunque no tenga nada que ver, ej. Dashboard) el sidebar se
// vuelve a pintar de cero con TODO expandido, así que cualquier grupo que el usuario
// hubiera contraído "se abre solo". Mismo patrón de localStorage que ya usa app.js para
// recordar si el sidebar entero está colapsado.
(function () {
    const STORAGE_KEY = 'dewoc-sidebar-grupos-cerrados';

    function gruposCerrados() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch { return []; }
    }
    function guardarGruposCerrados(lista) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(lista)); } catch {}
    }

    const cerrados = gruposCerrados();

    document.querySelectorAll('.nav-group-toggle').forEach(toggle => {
        const target = toggle.getAttribute('data-bs-target'); // ej. ".nav-group-ventas"
        const slug   = target.replace('.nav-group-', '');

        // Aplicar el estado guardado ANTES de que Bootstrap termine de inicializar sus
        // .collapse (evita el parpadeo de verlo abierto un instante y cerrarse solo).
        if (cerrados.includes(slug)) {
            toggle.classList.add('collapsed');
            toggle.setAttribute('aria-expanded', 'false');
            document.querySelectorAll(target).forEach(li => li.classList.remove('show'));
        }

        toggle.addEventListener('click', function () {
            // Al momento del click, Bootstrap todavía no actualizó .collapsed (lo hace
            // después) — por eso se guarda el estado que va a quedar tras este click, que
            // es el opuesto al que hay ahora mismo.
            const estabaAbierto = !toggle.classList.contains('collapsed');
            let lista = gruposCerrados();
            if (estabaAbierto) {
                if (!lista.includes(slug)) lista.push(slug);
            } else {
                lista = lista.filter(s => s !== slug);
            }
            guardarGruposCerrados(lista);
        });
    });
})();
</script>
