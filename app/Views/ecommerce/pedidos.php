<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Pedidos Ecommerce</h1>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['pendiente','pagado','en_preparacion','enviado','entregado','cancelado'] as $est): ?>
                    <option value="<?= $est ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $est)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblPedidos" class="table table-hover align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>MP Status</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Ver</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $colores = [
                'pendiente'      => 'warning',
                'pagado'         => 'success',
                'en_preparacion' => 'info',
                'enviado'        => 'primary',
                'entregado'      => 'secondary',
                'cancelado'      => 'danger',
            ];
            foreach ($pedidos as $p):
            $color = $colores[$p['estado']] ?? 'secondary';
            ?>
            <tr>
                <td>#<?= $p['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                <td class="fw-semibold"><?= e($p['cliente_nombre']) ?></td>
                <td><?= e($p['cliente_email']) ?></td>
                <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><?= ucfirst(str_replace('_',' ', e($p['estado']))) ?></span></td>
                <td><?= $p['mp_status'] ? e($p['mp_status']) : '—' ?></td>
                <td class="text-end fw-semibold">$<?= number_format((float)$p['total_con_iva'], 2, ',', '.') ?></td>
                <td class="text-end">
                    <a href="<?= url('ecommerce/pedido?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblPedidos', {
        language: DT_LANG_ES,
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        order: [[1, 'desc']],
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
    });
});
</script>
