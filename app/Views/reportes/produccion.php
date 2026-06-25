<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Producción</h1>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        <i class="ti ti-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Receta</label>
                <select name="receta_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($recetas as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $filtros['receta_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
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
        ['label' => 'Órdenes completadas', 'valor' => number_format((int)($resumen['total_ordenes'] ?? 0)),         'icon' => 'ti-checklist',        'color' => 'primary'],
        ['label' => 'Unidades producidas',  'valor' => number_format((float)($resumen['total_unidades'] ?? 0), 1, ',', '.'), 'icon' => 'ti-package',          'color' => 'success'],
        ['label' => 'Recetas distintas',    'valor' => number_format((int)($resumen['recetas_distintas'] ?? 0)),    'icon' => 'ti-tools-kitchen-2',  'color' => 'info'],
        ['label' => 'Promedio por orden',   'valor' => number_format((float)($resumen['promedio_unidades'] ?? 0), 1, ',', '.'), 'icon' => 'ti-chart-bar',        'color' => 'warning'],
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

<!-- Gráfico producción por receta -->
<?php if ($porReceta): ?>
<div class="card border-0 mb-4 d-print-none">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Producción por receta (top 10)</h6>
    </div>
    <div class="card-body">
        <canvas id="chartProduccion" height="80"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Tabla de órdenes -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Detalle de órdenes</h6>
    </div>
    <div class="card-body p-0">
        <table id="tblProduccion" class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Receta</th>
                    <th>Producto resultante</th>
                    <th class="text-end">Cantidad</th>
                    <th>Operador</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td><?= e($o['receta_nombre']) ?></td>
                    <td class="text-muted small"><?= e($o['producto_nombre'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= number_format((float)$o['cantidad_producida'], 2, ',', '.') ?></td>
                    <td class="text-muted small"><?= e($o['usuario_nombre'] ?? '—') ?></td>
                    <td><span class="badge bg-success-subtle text-success">Completada</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($porReceta): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = <?= json_encode(array_column($porReceta, 'receta')) ?>;
    const data   = <?= json_encode(array_column($porReceta, 'total_producido')) ?>;

    new Chart(document.getElementById('chartProduccion'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Unidades producidas',
                data,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') {
        new DataTable('#tblProduccion', {
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '<?= ASSETS_URL ?>/vendor/datatables/es-AR.json' },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'excelHtml5', text: 'Excel', className: 'btn btn-sm btn-outline-success' },
                { extend: 'pdfHtml5',   text: 'PDF',   className: 'btn btn-sm btn-outline-danger',
                  orientation: 'landscape', pageSize: 'A4' },
                { extend: 'colvis',     text: 'Columnas', className: 'btn btn-sm btn-outline-secondary' },
            ],
            stateSave: true,
        });
    }
});
</script>
