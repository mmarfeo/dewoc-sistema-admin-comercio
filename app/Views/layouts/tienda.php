<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? TIENDA_NOMBRE) ?></title>
    <meta name="description" content="<?= e(TIENDA_NOMBRE) ?> — Compras online">
    <link rel="icon" href="<?= ASSETS_URL ?>/img/favicon.ico">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/bootstrap.min.css">
    <style>
        .navbar-brand { font-weight: 700; }
        .card-img-top { height: 200px; object-fit: cover; background: #f8f9fa; }
        .card-img-placeholder { height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 3rem; }
        .badge-iva { font-size: .7rem; }
        .carrito-badge { position: relative; }
        .carrito-count { position: absolute; top: -8px; right: -8px; min-width: 18px; height: 18px; border-radius: 9px; background: #dc3545; color: #fff; font-size: .7rem; font-weight: 700; display: flex; align-items: center; justify-content: center; padding: 0 3px; }
        .precio { color: #198754; font-weight: 700; font-size: 1.2rem; }
        footer { border-top: 1px solid #dee2e6; }
        @media print { nav, footer { display: none; } }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= url('tienda') ?>"><?= e(TIENDA_NOMBRE) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navTienda">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navTienda">
            <form class="d-flex me-auto ms-3" method="get" action="<?= url('tienda') ?>">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Buscar productos..." value="<?= e($_GET['q'] ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="submit">🔍</button>
                </div>
            </form>
            <div class="d-flex align-items-center gap-3 ms-3">
                <a href="<?= url('tienda/carrito') ?>" class="btn btn-outline-primary position-relative" id="btnCarritoNav">
                    🛒 Carrito
                    <span class="carrito-count d-none" id="carritoCount">0</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Contenido -->
<main class="py-4">
    <div class="container">
        <?= $content ?>
    </div>
</main>

<!-- Footer -->
<footer class="py-4 mt-5 text-center text-muted small">
    <div class="container">
        <p class="mb-1">&copy; <?= date('Y') ?> <?= e(TIENDA_NOMBRE) ?>. Todos los derechos reservados.</p>
        <p class="mb-0">Precios con IVA incluido · Pagos seguros con <strong>Mercado Pago</strong></p>
    </div>
</footer>

<script src="<?= ASSETS_URL ?>/js/bootstrap.bundle.min.js"></script>
<script>
/* Carrito en localStorage */
function getCarrito() { try { return JSON.parse(localStorage.getItem('carrito') || '[]'); } catch { return []; } }
function setCarrito(c) { localStorage.setItem('carrito', JSON.stringify(c)); }
function actualizarBadge() {
    const c = getCarrito();
    const total = c.reduce((s, i) => s + i.cantidad, 0);
    const badge = document.getElementById('carritoCount');
    if (badge) {
        badge.textContent = total;
        badge.classList.toggle('d-none', total === 0);
    }
}
function agregarAlCarrito(producto) {
    const c = getCarrito();
    const idx = c.findIndex(i => i.producto_id === producto.producto_id);
    if (idx >= 0) c[idx].cantidad++;
    else c.push({ ...producto, cantidad: 1 });
    setCarrito(c);
    actualizarBadge();
}
document.addEventListener('DOMContentLoaded', actualizarBadge);
</script>
</body>
</html>
