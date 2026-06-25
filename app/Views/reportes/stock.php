<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Movimientos de Stock</h1>
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
                <label class="form-label fw-semibold">Tipo de ítem</label>
                <select name="tipo_item" class="form-select">
                    <option value="">Todos</option>
                    <option value="producto"      <?= $filtros['tipo_item'] === 'producto'      ? 'selected' : '' ?>>Productos</option>
                    <option value="materia_prima" <?= $filtros['tipo_item'] === 'materia_prima' ? 'selected' : '' ?>>Materias Primas</option>
                    <option value="insumo"        <?= $filtros['tipo_item'] === 'insumo'        ? 'selected' : '' ?>>Insumos</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Origen</label>
                <select name="origen" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['compra','venta','produccion','ajuste_manual','inventario','apertura'] as $orig): ?>
                    <option value="<?= $orig ?>" <?= $filtros['origen'] === $orig ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $orig)) ?>
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

<!-- Totales rápidos -->
<?php
$totalEntradas = 0;
$totalSalidas  = 0;
foreach ($movimientos as $m) {
    if ($m['cantidad'] > 0) $totalEntradas += $m['cantidad'];
    else $totalSalidas += abs($m['cantidad']);
}
?>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-success-subtle text-success"><i class="ti ti-arrow-up fs-4"></i></div>
                <div><div class="fw-bold fs-5"><?= number_format($totalEntradas, 2, ',', '.') ?></div><div class="text-muted small">Total entradas</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-danger-subtle text-danger"><i class="ti ti-arrow-down fs-4"></i></div>
                <div><div class="fw-bold fs-5"><?= number_format($totalSalidas, 2, ',', '.') ?></div><div class="text-muted small">Total salidas</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-primary-subtle text-primary"><i class="ti ti-list fs-4"></i></div>
                <div><div class="fw-bold fs-5"><?= count($movimientos) ?></div><div class="text-muted small">Movimientos</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de movimientos -->
<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblStock" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Ítem</th>
                    <th>Tipo</th>
                    <th>Origen</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Anterior</th>
                    <th class="text-end">Nuevo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($movimientos as $m): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                <td><?= e($m['item_nombre'] ?? '—') ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($m['tipo_item']) ?></span></td>
                <td><?= ucfirst(str_replace('_', ' ', e($m['origen']))) ?></td>
                <td class="text-end fw-semibold <?= $m['cantidad'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $m['cantidad'] >= 0 ? '+' : '' ?><?= number_format((float)$m['cantidad'], 3, ',', '.') ?>
                </td>
                <td class="text-end text-muted"><?= number_format((float)$m['cantidad_anterior'], 3, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float)$m['cantidad_nueva'], 3, ',', '.') ?></td>
                <td><?= e($m['usuario'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblStock', {
        language: DT_LANG_ES,
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        order: [[0, 'desc']],
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
    });
});
</script>
