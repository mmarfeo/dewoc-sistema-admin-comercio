<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= e($mp['nombre']) ?></h1>
        <p class="text-muted mb-0 small">Historial de precios de compra</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
        <a href="<?= url('materias-primas') ?>" class="btn btn-outline-secondary btn-sm d-print-none">← Volver</a>
    </div>
</div>

<?php if (!$historial): ?>
<div class="alert alert-info">No hay registros de compra para esta materia prima.</div>
<?php else: ?>

<!-- KPIs rápidos -->
<?php
$precios   = array_column($historial, 'precio_unitario');
$precioMin = min($precios);
$precioMax = max($precios);
$precioUlt = (float)$historial[0]['precio_unitario'];
$precioAvg = count($precios) > 0 ? array_sum($precios) / count($precios) : 0;
?>
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-primary mb-0">$<?= number_format($precioUlt, 4, ',', '.') ?></div>
                <div class="text-muted small">Último precio</div>
                <div class="text-muted smaller"><?= date('d/m/Y', strtotime($historial[0]['fecha'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-success mb-0">$<?= number_format($precioMin, 4, ',', '.') ?></div>
                <div class="text-muted small">Precio mínimo</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-danger mb-0">$<?= number_format($precioMax, 4, ',', '.') ?></div>
                <div class="text-muted small">Precio máximo</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-info mb-0">$<?= number_format($precioAvg, 4, ',', '.') ?></div>
                <div class="text-muted small">Precio promedio</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de evolución -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Evolución del precio unitario</h6>
    </div>
    <div class="card-body">
        <canvas id="chartPrecios" height="70"></canvas>
    </div>
</div>

<!-- Tabla de historial -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Detalle de compras</h6>
    </div>
    <div class="card-body p-0">
        <table id="tblHistorial" class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Comprobante</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Cant. contenida</th>
                    <th class="text-end">Precio unit.</th>
                    <th class="text-end">IVA %</th>
                    <th class="text-end">Subtotal c/IVA</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
                    <td class="fw-semibold"><?= e($h['proveedor']) ?></td>
                    <td class="text-muted small">
                        <a href="<?= url('compras/editar?id=' . (int)$h['factura_id']) ?>" title="Abrir la factura para corregir el precio">
                            <?= e(strtoupper($h['tipo_comprobante'])) ?> <?= e($h['nro_comprobante'] ?? '') ?>
                        </a>
                    </td>
                    <td class="text-end"><?= number_format((float)$h['cantidad'], 3, ',', '.') ?></td>
                    <td class="text-end text-muted"><?= $h['cantidad_contenida'] !== null ? number_format((float)$h['cantidad_contenida'], 3, ',', '.') : '—' ?></td>
                    <td class="text-end fw-semibold text-primary">$<?= number_format((float)$h['precio_unitario'], 4, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((float)$h['iva_porcentaje'], 1) ?>%</td>
                    <td class="text-end">$<?= number_format((float)$h['subtotal_con_iva'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Datos para el gráfico (orden cronológico)
    const rawDates  = <?= json_encode(array_column(array_reverse($historial), 'fecha')) ?>;
    const rawPrices = <?= json_encode(array_map('floatval', array_column(array_reverse($historial), 'precio_unitario'))) ?>;

    new Chart(document.getElementById('chartPrecios'), {
        type: 'line',
        data: {
            labels: rawDates.map(d => {
                const p = d.split('-');
                return p[2] + '/' + p[1] + '/' + p[0];
            }),
            datasets: [{
                label: 'Precio unitario ($)',
                data: rawPrices,
                borderColor: 'rgba(13, 110, 253, 0.8)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: v => '$' + v.toFixed(4)
                    }
                }
            }
        }
    });

    if (typeof DataTable !== 'undefined') {
        new DataTable('#tblHistorial', {
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '<?= ASSETS_URL ?>/vendor/datatables/es-AR.json' },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'excelHtml5', text: 'Excel', className: 'btn btn-sm btn-outline-success' },
                { extend: 'pdfHtml5',   text: 'PDF',   className: 'btn btn-sm btn-outline-danger' },
            ],
            stateSave: true,
        });
    }
});
</script>
<?php endif; ?>
