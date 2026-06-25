<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Productos</h1>
    <a href="<?= url('productos/nuevo') ?>" class="btn btn-primary">+ Nuevo producto</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblProductos" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Categoría</th>
                        <th>Precio venta</th>
                        <th>Precio costo</th>
                        <th>IVA %</th>
                        <th>Stock</th>
                        <th>Stock mín.</th>
                        <th>Ecommerce</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr class="<?= $p['stock_actual'] <= $p['stock_minimo'] ? 'table-warning' : '' ?>">
                        <td class="text-muted small"><?= e($p['codigo'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= e($p['nombre']) ?></td>
                        <td>
                            <span class="badge <?= $p['tipo'] === 'elaborado' ? 'bg-success-soft text-success' : 'bg-primary-soft text-primary' ?>">
                                <?= $p['tipo'] === 'elaborado' ? 'Elaborado' : 'Reventa' ?>
                            </span>
                        </td>
                        <td class="small"><?= e($p['categoria_nombre'] ?? '—') ?></td>
                        <td><?= moneda($p['precio_venta']) ?></td>
                        <td class="text-muted"><?= moneda($p['precio_costo']) ?></td>
                        <td class="small"><?= number_format($p['iva'], 1) ?>%</td>
                        <td>
                            <?php $stockClass = $p['stock_actual'] <= $p['stock_minimo'] ? 'text-danger fw-bold' : 'text-success fw-semibold'; ?>
                            <span class="<?= $stockClass ?>">
                                <?= number_format($p['stock_actual'], 2, ',', '.') ?>
                                <?= e($p['unidad'] ?? '') ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= number_format($p['stock_minimo'], 2, ',', '.') ?></td>
                        <td>
                            <?php if ($p['ecommerce']): ?>
                                <span class="badge bg-info-soft text-info">Sí</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $p['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                                <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('productos/qr?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0" title="QR / Código de barras" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M17 17h4v4h-4z"/><path d="M14 17v4"/><path d="M17 14h4"/></svg>
                            </a>
                            <a href="<?= url('productos/editar?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminarProducto(<?= $p['id'] ?>, '<?= e($p['nombre']) ?>')" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (count(array_filter($productos, fn($p) => $p['stock_actual'] <= $p['stock_minimo'])) > 0): ?>
<p class="mt-2 small text-warning">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Las filas en amarillo tienen stock por debajo del mínimo.
</p>
<?php endif; ?>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

$(document).ready(() => { $('#tblProductos').DataTable(); });

async function eliminarProducto(id, nombre) {
    if (!confirm(`¿Eliminar el producto "${nombre}"?`)) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/productos/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800))
              : showToast(d.error, 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}
</script>
