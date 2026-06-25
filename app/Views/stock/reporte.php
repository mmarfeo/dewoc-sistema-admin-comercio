<?php
// Precalcular KPIs de stock teórico vs físico
$conDiscrepancia = 0;
$totalEntradas   = 0.0;
$totalSalidas    = 0.0;
$sinMovimientos  = 0;
foreach ($stockTeorico as $row) {
    if (abs($row['diferencia']) > 0.001) $conDiscrepancia++;
    $totalEntradas += $row['entradas'];
    $totalSalidas  += $row['salidas'];
    if ($row['entradas'] == 0 && $row['salidas'] == 0) $sinMovimientos++;
}
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte Comparativo de Stock</h1>
    <a href="<?= url('stock') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form method="GET" action="<?= url('stock/reporte') ?>" class="card border-0 mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>"></div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Generar reporte</button></div>
        </div>
    </div>
</form>

<!-- Productos vendidos -->
<h5 class="fw-bold mb-3">Productos vendidos en el período</h5>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblVentas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Producto</th><th>Código</th><th class="text-end">Cant. vendida</th>
                        <th class="text-end">Total vendido</th><th class="text-end">Stock actual</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte['ventas'] as $v): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($v['producto']) ?></td>
                        <td class="small text-muted"><?= e($v['codigo'] ?? '—') ?></td>
                        <td class="text-end"><?= number_format($v['cant_vendida'],3,',','.') ?></td>
                        <td class="text-end fw-semibold"><?= moneda($v['total_vendido']) ?></td>
                        <td class="text-end small"><?= number_format($v['stock_actual'],3,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$reporte['ventas']): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin ventas en el período</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Consumo de materias primas e insumos -->
<h5 class="fw-bold mb-3">Consumo de materias primas e insumos (producción)</h5>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMP" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Ingrediente</th><th>Tipo</th><th class="text-end">Cant. consumida</th>
                        <th>Unidad</th><th class="text-end">Stock actual</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_merge($reporte['consumo_mp'], $reporte['consumo_ins']) as $c): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($c['ingrediente']) ?></td>
                        <td><span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ',$c['tipo'])) ?></span></td>
                        <td class="text-end"><?= number_format($c['cant_consumida'],3,',','.') ?></td>
                        <td class="small text-muted"><?= e($c['unidad'] ?? '') ?></td>
                        <td class="text-end small"><?= number_format($c['stock_actual'],3,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$reporte['consumo_mp'] && !$reporte['consumo_ins']): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin producciones en el período</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Stock teórico vs físico ────────────────────────────────────────── -->
<h5 class="fw-bold mb-3 mt-5">Stock teórico vs físico por ítem</h5>
<div class="alert alert-info border-0 small mb-4 py-2">
    <strong>¿Cómo se calcula?</strong>
    Stock teórico = stock antes del primer movimiento del período + entradas − salidas registradas.
    La comparación es exacta cuando <em>Hasta</em> es la fecha de hoy.
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Ítems con discrepancia</p>
                <h4 class="fw-bold mb-0 <?= $conDiscrepancia > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $conDiscrepancia ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Total entradas del período</p>
                <h4 class="fw-bold mb-0"><?= number_format($totalEntradas, 3, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Total salidas del período</p>
                <h4 class="fw-bold mb-0"><?= number_format($totalSalidas, 3, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Ítems sin movimientos</p>
                <h4 class="fw-bold mb-0 text-muted"><?= $sinMovimientos ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Filtro rápido -->
<div class="d-flex align-items-center gap-3 mb-3">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="chkSoloDiscrepancia">
        <label class="form-check-label small" for="chkSoloDiscrepancia">Mostrar solo ítems con discrepancia</label>
    </div>
</div>

<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblTeorico" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ítem</th>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Unidad</th>
                        <th class="text-end">Stock inicio</th>
                        <th class="text-end">Entradas</th>
                        <th class="text-end">Salidas</th>
                        <th class="text-end">Stock teórico fin</th>
                        <th class="text-end">Stock físico actual</th>
                        <th class="text-end">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockTeorico as $row): ?>
                    <?php
                        $dif      = $row['diferencia'];
                        $difAbs   = abs($dif);
                        $hayDif   = $difAbs > 0.001;
                        $tipoLabel = match($row['tipo_item']) {
                            'materia_prima' => 'Materia prima',
                            'insumo'        => 'Insumo',
                            default         => 'Producto',
                        };
                        $tipoColor = match($row['tipo_item']) {
                            'materia_prima' => 'bg-warning-soft text-warning',
                            'insumo'        => 'bg-info-soft text-info',
                            default         => 'bg-primary-soft text-primary',
                        };
                    ?>
                    <tr data-discrepancia="<?= $hayDif ? '1' : '0' ?>">
                        <td class="fw-semibold"><?= e($row['nombre']) ?></td>
                        <td class="small text-muted"><?= e($row['codigo'] ?: '—') ?></td>
                        <td><span class="badge <?= $tipoColor ?> small"><?= $tipoLabel ?></span></td>
                        <td class="small"><?= e($row['unidad']) ?></td>
                        <td class="text-end"><?= number_format($row['stock_inicio'], 3, ',', '.') ?></td>
                        <td class="text-end text-success fw-semibold">
                            <?= $row['entradas'] > 0 ? '+'.number_format($row['entradas'], 3, ',', '.') : '—' ?>
                        </td>
                        <td class="text-end text-danger fw-semibold">
                            <?= $row['salidas'] > 0 ? '−'.number_format($row['salidas'], 3, ',', '.') : '—' ?>
                        </td>
                        <td class="text-end fw-bold"><?= number_format($row['stock_teorico_fin'], 3, ',', '.') ?></td>
                        <td class="text-end"><?= number_format($row['stock_fisico_actual'], 3, ',', '.') ?></td>
                        <td class="text-end">
                            <?php if (!$hayDif): ?>
                                <span class="badge bg-success-soft text-success">Sin diferencia</span>
                            <?php elseif ($dif > 0): ?>
                                <span class="badge bg-warning-soft text-warning">+<?= number_format($dif, 3, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger-soft text-danger"><?= number_format($dif, 3, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$stockTeorico): ?>
                    <tr><td colspan="10" class="text-center text-muted py-3">Sin ítems en stock</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Gráfico evolución de stock ──────────────────────────────────────── -->
<h5 class="fw-bold mb-3 mt-5">Evolución de stock por ítem</h5>
<div class="card border-0 mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Ítem</label>
                <select id="graficoItem" class="form-select">
                    <option value="">— Seleccioná un ítem —</option>
                    <?php
                    $tipoLabels = ['producto' => 'Producto', 'materia_prima' => 'Materia prima', 'insumo' => 'Insumo'];
                    foreach ($stockTeorico as $row):
                    ?>
                    <option value="<?= e($row['tipo_item']) ?>|<?= $row['id'] ?>">
                        [<?= $tipoLabels[$row['tipo_item']] ?? $row['tipo_item'] ?>] <?= e($row['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" id="graficoDesde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" id="graficoHasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" id="btnCargarGrafico">Ver evolución</button>
            </div>
        </div>
        <div id="graficoWrap" style="display:none; position:relative; height:320px">
            <canvas id="stockChart"></canvas>
        </div>
        <div id="graficoEmpty" class="text-center text-muted py-5" style="display:none">
            Sin movimientos en el período seleccionado para este ítem.
        </div>
        <div id="graficoPlaceholder" class="text-center text-muted py-5">
            Seleccioná un ítem y hacé clic en "Ver evolución" para ver el gráfico.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
let stockChart = null;

document.getElementById('btnCargarGrafico').addEventListener('click', async function () {
    const sel   = document.getElementById('graficoItem').value;
    const desde = document.getElementById('graficoDesde').value;
    const hasta = document.getElementById('graficoHasta').value;
    if (!sel) { alert('Seleccioná un ítem primero.'); return; }

    const [tipo, id] = sel.split('|');
    const url = `${BASE_URL}/stock/historial-item?tipo=${encodeURIComponent(tipo)}&id=${id}&desde=${desde}&hasta=${hasta}`;

    this.disabled = true;
    this.textContent = 'Cargando…';

    try {
        const r = await fetch(url);
        const d = await r.json();

        document.getElementById('graficoPlaceholder').style.display = 'none';

        if (!d.ok || !d.data.length) {
            document.getElementById('graficoWrap').style.display  = 'none';
            document.getElementById('graficoEmpty').style.display = 'block';
            return;
        }
        document.getElementById('graficoEmpty').style.display = 'none';
        document.getElementById('graficoWrap').style.display  = 'block';

        const labels = d.data.map(r => r.fecha);
        const values = d.data.map(r => parseFloat(r.stock_despues));

        if (stockChart) stockChart.destroy();
        stockChart = new Chart(document.getElementById('stockChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Stock',
                    data: values,
                    fill: true,
                    tension: 0.3,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.08)',
                    pointRadius: d.data.length > 60 ? 2 : 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterBody: (items) => {
                                const idx = items[0].dataIndex;
                                const row = d.data[idx];
                                return [
                                    `Movimiento: ${parseFloat(row.cantidad) >= 0 ? '+' : ''}${parseFloat(row.cantidad).toFixed(3)}`,
                                    `Motivo: ${row.motivo || '—'}`,
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 12, font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 11 } }, beginAtZero: false },
                }
            }
        });
    } catch (err) {
        alert('Error cargando datos del gráfico.');
        console.error(err);
    } finally {
        this.disabled = false;
        this.textContent = 'Ver evolución';
    }
});
</script>

<script>
$(document).ready(function(){
    $('#tblVentas').DataTable({
        pageLength: 25,
        language: dtEspanol,
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5','pdfHtml5'],
    });
    $('#tblMP').DataTable({
        pageLength: 25,
        language: dtEspanol,
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5','pdfHtml5'],
    });

    var tablaTeorico = $('#tblTeorico').DataTable({
        pageLength: 50,
        language: dtEspanol,
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5','pdfHtml5'],
        order: [[9, 'asc']],  // ordenar por diferencia por defecto
    });

    // Filtro rápido — mostrar solo discrepancias
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData, counter) {
        if (settings.nTable.id !== 'tblTeorico') return true;
        if (!document.getElementById('chkSoloDiscrepancia').checked) return true;
        var fila = $('#tblTeorico tbody tr').eq(counter);
        return fila.data('discrepancia') === 1 || fila.attr('data-discrepancia') === '1';
    });

    $('#chkSoloDiscrepancia').on('change', function(){
        tablaTeorico.draw();
    });
});
</script>
