<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Pedido #<?= $pedido['id'] ?></h1>
    <a href="<?= url('ecommerce/pedidos') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-4">
    <!-- Datos del pedido -->
    <div class="col-lg-8">
        <!-- Estado -->
        <?php
        $colores = ['pendiente'=>'warning','pagado'=>'success','en_preparacion'=>'info','enviado'=>'primary','entregado'=>'secondary','cancelado'=>'danger'];
        $color   = $colores[$pedido['estado']] ?? 'secondary';
        ?>
        <div class="card border-0 mb-3">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="text-muted small">Estado actual:</span><br>
                    <span class="badge bg-<?= $color ?> fs-6 px-3 py-2"><?= ucfirst(str_replace('_', ' ', e($pedido['estado']))) ?></span>
                </div>
                <form id="formEstado" class="d-flex gap-2 align-items-center">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                    <select name="estado" class="form-select form-select-sm" style="min-width:160px">
                        <?php foreach (['pendiente','pagado','en_preparacion','enviado','entregado','cancelado'] as $est): ?>
                        <option value="<?= $est ?>" <?= $pedido['estado'] === $est ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $est)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                </form>
            </div>
        </div>

        <!-- Detalle de ítems -->
        <div class="card border-0 mb-3">
            <div class="card-header border-0 bg-transparent fw-semibold">Productos del pedido</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio unit.</th>
                            <th class="text-end">IVA %</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pedido['detalle'] as $it): ?>
                    <tr>
                        <td><?= e($it['producto_nombre']) ?></td>
                        <td class="text-center"><?= number_format((float)$it['cantidad'], 2, ',', '.') ?></td>
                        <td class="text-end">$<?= number_format((float)$it['precio_unitario'], 2, ',', '.') ?></td>
                        <td class="text-end"><?= $it['iva_porcentaje'] ?>%</td>
                        <td class="text-end fw-semibold">$<?= number_format((float)$it['subtotal_con_iva'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Total:</td>
                            <td class="text-end fw-bold">$<?= number_format((float)$pedido['total_con_iva'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar: datos del cliente y pago -->
    <div class="col-lg-4">
        <div class="card border-0 mb-3">
            <div class="card-header border-0 bg-transparent fw-semibold">Datos del cliente</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5 text-muted">Nombre</dt>
                    <dd class="col-sm-7"><?= e($pedido['cliente_nombre']) ?></dd>
                    <dt class="col-sm-5 text-muted">Email</dt>
                    <dd class="col-sm-7"><?= e($pedido['cliente_email']) ?></dd>
                    <dt class="col-sm-5 text-muted">Teléfono</dt>
                    <dd class="col-sm-7"><?= $pedido['cliente_telefono'] ? e($pedido['cliente_telefono']) : '—' ?></dd>
                    <dt class="col-sm-5 text-muted">Dirección</dt>
                    <dd class="col-sm-7"><?= $pedido['cliente_direccion'] ? e($pedido['cliente_direccion']) : '—' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 mb-3">
            <div class="card-header border-0 bg-transparent fw-semibold">Pago (Mercado Pago)</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-6 text-muted">Preferencia ID</dt>
                    <dd class="col-sm-6 text-truncate" title="<?= e($pedido['mp_preference_id'] ?? '') ?>"><?= $pedido['mp_preference_id'] ? e(substr($pedido['mp_preference_id'], 0, 15)) . '...' : '—' ?></dd>
                    <dt class="col-sm-6 text-muted">Payment ID</dt>
                    <dd class="col-sm-6"><?= $pedido['mp_payment_id'] ? e($pedido['mp_payment_id']) : '—' ?></dd>
                    <dt class="col-sm-6 text-muted">MP Status</dt>
                    <dd class="col-sm-6"><?= $pedido['mp_status'] ? e($pedido['mp_status']) : '—' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header border-0 bg-transparent fw-semibold">Totales</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-6 text-muted">Subtotal</dt>
                    <dd class="col-sm-6">$<?= number_format((float)$pedido['subtotal'], 2, ',', '.') ?></dd>
                    <dt class="col-sm-6 text-muted">IVA</dt>
                    <dd class="col-sm-6">$<?= number_format((float)$pedido['total_iva'], 2, ',', '.') ?></dd>
                    <dt class="col-sm-6 fw-semibold">Total</dt>
                    <dd class="col-sm-6 fw-semibold">$<?= number_format((float)$pedido['total_con_iva'], 2, ',', '.') ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($pedido['notas']): ?>
        <div class="card border-0 mt-3">
            <div class="card-header border-0 bg-transparent fw-semibold">Notas</div>
            <div class="card-body text-muted small"><?= nl2br(e($pedido['notas'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';

document.getElementById('formEstado').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/ecommerce/estado`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(d.error, 'danger');
        }
    } catch { showToast('Error de conexión.', 'danger'); }
});
</script>
