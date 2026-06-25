<div class="row g-4">
    <div class="col-md-5">
        <?php if ($producto['imagen'] && file_exists(BASE_PATH . '/uploads/' . $producto['imagen'])): ?>
        <img src="<?= ASSETS_URL ?>/../uploads/<?= e($producto['imagen']) ?>"
             class="img-fluid rounded shadow-sm" alt="<?= e($producto['nombre']) ?>" style="max-height:400px;object-fit:cover;width:100%">
        <?php else: ?>
        <div class="rounded shadow-sm d-flex align-items-center justify-content-center bg-light" style="height:300px;font-size:5rem">🛍️</div>
        <?php endif; ?>
    </div>
    <div class="col-md-7">
        <p class="text-muted small mb-1"><?= e($producto['categoria'] ?? '') ?></p>
        <h1 class="h2 fw-bold mb-3"><?= e($producto['nombre']) ?></h1>

        <?php if ($producto['descripcion']): ?>
        <p class="text-muted mb-4"><?= nl2br(e($producto['descripcion'])) ?></p>
        <?php endif; ?>

        <div class="mb-4">
            <span class="precio fs-3">$<?= number_format((float)$producto['precio_venta'], 2, ',', '.') ?></span>
            <span class="badge bg-light text-muted ms-2">IVA <?= $producto['iva'] ?>% incluido</span>
        </div>

        <?php if ($producto['stock_actual'] <= 0): ?>
        <div class="alert alert-warning">Sin stock disponible</div>
        <?php else: ?>
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="input-group" style="max-width:130px">
                <button class="btn btn-outline-secondary" id="btnMenos">−</button>
                <input type="number" class="form-control text-center" id="cantidad" value="1" min="1" max="<?= min((int)$producto['stock_actual'], 99) ?>">
                <button class="btn btn-outline-secondary" id="btnMas">+</button>
            </div>
            <button class="btn btn-primary btn-lg" id="btnAgregar">
                🛒 Agregar al carrito
            </button>
        </div>
        <p class="text-muted small">Stock disponible: <?= number_format((float)$producto['stock_actual'], 0) ?> unidades</p>
        <?php endif; ?>

        <a href="<?= url('tienda') ?>" class="btn btn-link text-decoration-none ps-0">← Volver al catálogo</a>
    </div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastCarrito" class="toast text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">Producto agregado al carrito</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
const prod = {
    producto_id:     <?= (int)$producto['id'] ?>,
    nombre:          <?= json_encode($producto['nombre']) ?>,
    precio_unitario: <?= (float)$producto['precio_venta'] ?>,
    iva_porcentaje:  <?= (float)$producto['iva'] ?>,
};

const cantInput = document.getElementById('cantidad');
document.getElementById('btnMenos')?.addEventListener('click', () => { cantInput.value = Math.max(1, parseInt(cantInput.value) - 1); });
document.getElementById('btnMas')?.addEventListener('click',  () => { cantInput.value = Math.min(parseInt(cantInput.max), parseInt(cantInput.value) + 1); });

document.getElementById('btnAgregar')?.addEventListener('click', function () {
    const cant = parseInt(cantInput.value) || 1;
    for (let i = 0; i < cant; i++) agregarAlCarrito(prod);
    new bootstrap.Toast(document.getElementById('toastCarrito')).show();
});
</script>
