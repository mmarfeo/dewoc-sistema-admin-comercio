<div class="row g-4">
    <!-- Sidebar categorías -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Categorías</div>
            <div class="list-group list-group-flush">
                <a href="<?= url('tienda') ?>" class="list-group-item list-group-item-action <?= empty($filtros['categoria_id']) ? 'active' : '' ?>">
                    Todos los productos
                </a>
                <?php foreach ($categorias as $cat): ?>
                <a href="<?= url('tienda?categoria_id=' . $cat['id']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                          <?= (int)$filtros['categoria_id'] === (int)$cat['id'] ? 'active' : '' ?>">
                    <?= e($cat['nombre']) ?>
                    <span class="badge bg-secondary rounded-pill"><?= $cat['total_productos'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Grilla de productos -->
    <div class="col-lg-9">
        <?php if (!empty($filtros['q'])): ?>
        <p class="text-muted mb-3">
            Resultados para "<strong><?= e($filtros['q']) ?></strong>" — <?= count($productos) ?> producto(s)
        </p>
        <?php endif; ?>

        <?php if (!$productos): ?>
        <div class="text-center py-5 text-muted">
            <div class="fs-1 mb-3">🔍</div>
            <h4>No se encontraron productos</h4>
            <p>Intenta con otra categoría o búsqueda.</p>
            <a href="<?= url('tienda') ?>" class="btn btn-outline-primary">Ver todos los productos</a>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($productos as $p): ?>
            <div class="col-sm-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <?php if ($p['imagen'] && file_exists(BASE_PATH . '/uploads/' . $p['imagen'])): ?>
                    <img src="<?= ASSETS_URL ?>/../uploads/<?= e($p['imagen']) ?>"
                         class="card-img-top" alt="<?= e($p['nombre']) ?>">
                    <?php else: ?>
                    <div class="card-img-placeholder">🛍️</div>
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <p class="text-muted small mb-1"><?= e($p['categoria'] ?? '') ?></p>
                        <h6 class="card-title fw-semibold mb-2"><?= e($p['nombre']) ?></h6>

                        <?php if ($p['descripcion']): ?>
                        <p class="card-text small text-muted flex-grow-1" style="overflow:hidden;max-height:3rem">
                            <?= e(substr($p['descripcion'], 0, 80)) . (strlen($p['descripcion']) > 80 ? '...' : '') ?>
                        </p>
                        <?php else: ?>
                        <div class="flex-grow-1"></div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <span class="precio">$<?= number_format((float)$p['precio_venta'], 2, ',', '.') ?></span>
                                <span class="badge bg-light text-muted badge-iva ms-1">IVA <?= $p['iva'] ?>%</span>
                            </div>
                            <?php if ($p['stock_actual'] <= 0): ?>
                            <span class="badge bg-danger">Sin stock</span>
                            <?php else: ?>
                            <button class="btn btn-primary btn-sm btn-agregar"
                                    data-id="<?= $p['id'] ?>"
                                    data-nombre="<?= e($p['nombre']) ?>"
                                    data-precio="<?= $p['precio_venta'] ?>"
                                    data-iva="<?= $p['iva'] ?>">
                                + Agregar
                            </button>
                            <?php endif; ?>
                        </div>

                        <a href="<?= url('tienda/producto?id=' . $p['id']) ?>"
                           class="btn btn-outline-secondary btn-sm mt-2">Ver detalle</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastCarrito" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg">Producto agregado al carrito</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-agregar').forEach(btn => {
    btn.addEventListener('click', function () {
        agregarAlCarrito({
            producto_id:     parseInt(this.dataset.id),
            nombre:          this.dataset.nombre,
            precio_unitario: parseFloat(this.dataset.precio),
            iva_porcentaje:  parseFloat(this.dataset.iva),
        });
        document.getElementById('toastMsg').textContent = `"${this.dataset.nombre}" agregado al carrito`;
        new bootstrap.Toast(document.getElementById('toastCarrito')).show();
    });
});
</script>
