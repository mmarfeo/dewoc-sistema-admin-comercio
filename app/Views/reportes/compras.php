<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Compras</h1>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        <i class="ti ti-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Proveedor</label>
                <select name="proveedor_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($proveedores as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= (int)$filtros['proveedor_id'] === (int)$prov['id'] ? 'selected' : '' ?>>
                        <?= e($prov['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Tipo comprobante</label>
                <select name="tipo_comprobante" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['factura_a','factura_b','factura_c','ticket','remito','otro'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filtros['tipo_comprobante'] === $t ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $t)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Facturas',         'valor' => number_format((int)($resumen['total_facturas'] ?? 0)), 'icon' => 'ti-file-invoice', 'color' => 'primary'],
        ['label' => 'Total comprado',   'valor' => '$' . number_format((float)($resumen['total_bruto'] ?? 0), 2, ',', '.'), 'icon' => 'ti-currency-dollar', 'color' => 'danger'],
        ['label' => 'IVA total',        'valor' => '$' . number_format((float)($resumen['total_iva'] ?? 0), 2, ',', '.'),   'icon' => 'ti-percentage', 'color' => 'warning'],
        ['label' => 'Promedio factura', 'valor' => '$' . number_format((float)($resumen['promedio'] ?? 0), 2, ',', '.'),   'icon' => 'ti-chart-bar', 'color' => 'info'],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-<?= $k['color'] ?>-subtle text-<?= $k['color'] ?>">
                    <i class="ti <?= $k['icon'] ?> fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5"><?= $k['valor'] ?></div>
                    <div class="text-muted small"><?= $k['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Por proveedor -->
<?php if ($porProveedor): ?>
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Compras por proveedor</div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Proveedor</th><th class="text-center">Facturas</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($porProveedor as $prov): ?>
            <tr>
                <td><?= e($prov['proveedor']) ?></td>
                <td class="text-center"><?= $prov['facturas'] ?></td>
                <td class="text-end fw-semibold">$<?= number_format((float)$prov['total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Detalle -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Detalle de facturas de compra</span>
        <span class="badge bg-secondary"><?= count($compras) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <table id="tblCompras" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Comprobante</th>
                    <th>Condición pago</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th class="d-print-none text-end">Ver</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
                <td><?= e($c['proveedor'] ?? '—') ?></td>
                <td>
                    <?= ucfirst(str_replace('_', ' ', e($c['tipo_comprobante']))) ?>
                    <?= $c['nro_comprobante'] ? '<small class="text-muted">#' . e($c['nro_comprobante']) . '</small>' : '' ?>
                </td>
                <td><?= ucfirst(str_replace('_', ' ', e($c['condicion_pago']))) ?></td>
                <td class="text-end">$<?= number_format((float)$c['total_iva'], 2, ',', '.') ?></td>
                <td class="text-end fw-semibold">$<?= number_format((float)$c['total_con_iva'], 2, ',', '.') ?></td>
                <td class="text-end d-print-none">
                    <a href="<?= url('compras/ver?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
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
    new DataTable('#tblCompras', {
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
