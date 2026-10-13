<?php
// Precalcular KPIs de stock teórico vs físico
$conDiscrepancia   = 0;
$totalEntradas     = 0.0;
$totalSalidas      = 0.0;
$sinMovimientos    = 0;
$valorDiferenciaTotal = 0.0;
foreach ($stockTeorico as $row) {
    if (abs($row['diferencia']) > 0.001) $conDiscrepancia++;
    $totalEntradas += $row['entradas'];
    $totalSalidas  += $row['salidas'];
    if ($row['entradas'] == 0 && $row['salidas'] == 0) $sinMovimientos++;
    $valorDiferenciaTotal += $row['valor_diferencia'];
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
        <hr class="my-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Consumo de una familia de ítems (por nombre)</label>
                <input type="text" name="consumo_q" class="form-control" placeholder='ej. "Helado en balde"' value="<?= e($consumoQ) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="consumo_tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="materia_prima" <?= $consumoTipoItem === 'materia_prima' ? 'selected' : '' ?>>Materia prima</option>
                    <option value="insumo" <?= $consumoTipoItem === 'insumo' ? 'selected' : '' ?>>Insumo</option>
                    <option value="producto" <?= $consumoTipoItem === 'producto' ? 'selected' : '' ?>>Producto</option>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Buscar consumo</button></div>
        </div>
    </div>
</form>

<?php if ($consumoQ !== ''): ?>
<!-- ── Consumo de una familia de ítems (búsqueda por nombre) ──────────────── -->
<h5 class="fw-bold mb-3">Consumo de "<?= e($consumoQ) ?>" entre <?= fecha($desde) ?> y <?= fecha($hasta) ?></h5>
<div class="alert alert-info small">
    Agrupa todos los ítems cuyo nombre contiene el texto buscado (ej. "Helado en balde" trae cada sabor por
    separado). <strong>Salidas</strong> = lo realmente consumido en ventas/producción en el período — antes de esta
    corrección, ese consumo no se veía acá (quedaba solo la compra, del lado de Entradas). Pasá el mouse o
    tocá el ⓘ de cada columna para ver qué significa exactamente.
</div>
<?php
// Explicación de cada columna no obvia — mismo texto que ya te di por chat, para no
// tener que repetirlo. $consumoColInfo: id del botón => [título, html del popover].
$consumoColInfo = [
    'entradas' => ['Entradas', 'Suma de movimientos de tipo "entrada" de ese ítem en el período elegido (desde–hasta, ambos días incluidos). No es solo compras: también cuenta la <strong>reposición por anulación de venta</strong> (si se anuló una venta que ya había descontado el ítem, vuelve a entrar acá) y ajustes manuales positivos.'],
    'salidas'  => ['Salidas (consumido)', 'Suma de movimientos de tipo "salida" del mismo período: el <strong>consumo real de receta en cada venta</strong> (el sabor/variante puntual que se descontó), más ajustes manuales negativos. Es la respuesta directa a "cuánto se consumió".'],
    'neto'     => ['Neto', '<strong>Entradas − Salidas</strong> del período. Positivo = entró más de lo que se consumió (el stock creció neto esos días). Negativo = se consumió más de lo que entró (el stock bajó neto).'],
    'stock'    => ['Stock actual', 'El saldo <strong>corriente</strong> del sistema ahora mismo (no una foto del final del período filtrado) — el mismo campo que la tabla "Stock teórico vs físico" de más abajo llama "físico". Se sigue moviendo con cada compra/venta/ajuste después del período que estás mirando.'],
    'ultima_compra' => ['Última compra', 'Fecha del comprobante de compra más reciente de ese ítem (de cualquier fecha, no acotado al período filtrado) — para ver rápido si hace mucho que no se repone, sin ir a buscar el historial de precios.'],
];
$colBtn = function (string $label, string $key) use ($consumoColInfo): void {
    [$titulo, $html] = $consumoColInfo[$key];
    ?><?= e($label) ?> <button type="button" class="btn btn-link p-0 sep-cat-info-btn" data-col-info="<?= e($key) ?>" tabindex="0" aria-label="Qué muestra <?= e($titulo) ?>">ⓘ</button><?php
};
?>
<style>
.sep-cat-info-btn { font-size: .7rem; line-height: 1; padding: 0 .15rem; color: var(--bs-secondary); text-decoration: none; vertical-align: baseline; }
.sep-cat-info-btn:hover { color: var(--bs-primary); }
.popover.sep-cat-popover { max-width: min(360px, 90vw); }
.popover.sep-cat-popover .popover-body { font-size: .8rem; }
</style>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblConsumoBusqueda" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ítem</th><th>Tipo</th><th>Categoría</th>
                        <th class="text-end"><?php $colBtn('Entradas', 'entradas') ?></th>
                        <th class="text-end"><?php $colBtn('Salidas (consumido)', 'salidas') ?></th>
                        <th class="text-end"><?php $colBtn('Neto', 'neto') ?></th>
                        <th class="text-end"><?php $colBtn('Stock actual', 'stock') ?></th>
                        <th><?php $colBtn('Última compra', 'ultima_compra') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$consumoBusqueda): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos de ítems que coincidan con "<?= e($consumoQ) ?>" en este período.</td></tr>
                    <?php endif; ?>
                    <?php $totEntradas = $totSalidas = 0.0; ?>
                    <?php foreach ($consumoBusqueda as $c): $totEntradas += $c['entradas']; $totSalidas += $c['salidas']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($c['nombre']) ?></td>
                        <td class="small"><span class="badge bg-secondary-soft text-secondary"><?= str_replace('_', ' ', $c['tipo_item']) ?></span></td>
                        <td class="small text-muted"><?= e($c['categoria']) ?></td>
                        <td class="text-end small text-success"><?= number_format($c['entradas'], 3, ',', '.') ?> <?= e($c['unidad']) ?></td>
                        <td class="text-end fw-semibold"><?= number_format($c['salidas'], 3, ',', '.') ?> <?= e($c['unidad']) ?></td>
                        <td class="text-end small <?= $c['consumo_neto'] < 0 ? 'text-danger' : 'text-muted' ?>"><?= number_format($c['consumo_neto'], 3, ',', '.') ?></td>
                        <td class="text-end small"><?= number_format($c['stock_actual'], 3, ',', '.') ?></td>
                        <td class="small text-muted"><?= $c['ultima_compra'] ? fecha($c['ultima_compra']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($consumoBusqueda): ?>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3">TOTAL</td>
                        <td class="text-end text-success"><?= number_format($totEntradas, 3, ',', '.') ?></td>
                        <td class="text-end"><?= number_format($totSalidas, 3, ',', '.') ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const consumoColInfo = <?= json_encode(array_combine(
        array_keys($consumoColInfo),
        array_map(fn($v) => ['title' => $v[0], 'html' => $v[1]], $consumoColInfo)
    ), JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('#tblConsumoBusqueda [data-col-info]').forEach(btn => {
        const info = consumoColInfo[btn.dataset.colInfo];
        if (!info || !window.bootstrap || !bootstrap.Popover) return;
        new bootstrap.Popover(btn, {
            container: 'body', customClass: 'sep-cat-popover', html: true, sanitize: false,
            trigger: 'click focus', placement: 'bottom', title: info.title, content: info.html,
        });
    });
});
</script>

<!-- ── Compras que aumentaron el stock ─────────────────────────────────── -->
<h6 class="fw-bold mb-2 mt-4">Compras que aumentaron el stock</h6>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Ítem</th><th class="text-end">Cantidad</th><th>Comprobante</th><th>Proveedor</th></tr>
                </thead>
                <tbody>
                    <?php if (!$movimientosDetalle['compras']): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin compras de ítems que coincidan con "<?= e($consumoQ) ?>" en este período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($movimientosDetalle['compras'] as $mc): ?>
                    <tr>
                        <td class="small"><?= fechaHora($mc['fecha']) ?></td>
                        <td class="small fw-semibold"><?= e($mc['nombre']) ?></td>
                        <td class="text-end small text-success">+<?= number_format($mc['cantidad'], 3, ',', '.') ?></td>
                        <td class="small">
                            <?php if ($mc['factura_id']): ?>
                            <a href="<?= url('compras/ver?id=' . $mc['factura_id']) ?>"><?= e($mc['comprobante'] ?? ('#' . $mc['factura_id'])) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($mc['proveedor'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Ventas que bajaron el stock ──────────────────────────────────────── -->
<h6 class="fw-bold mb-2">Ventas que bajaron el stock</h6>
<div class="alert alert-secondary small">
    Solo aparecen ventas de <strong>a partir de ahora</strong>: el vínculo venta ↔ consumo de receta recién se
    empezó a guardar. El consumo de ventas anteriores a este cambio no se puede distinguir de forma confiable
    de una producción manual, así que no se lista acá (sigue estando incluido en el total de "Salidas" de la
    tabla de arriba).
</div>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Ítem</th><th class="text-end">Cantidad</th><th>Venta</th><th>Detalle</th></tr>
                </thead>
                <tbody>
                    <?php if (!$movimientosDetalle['ventas']): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin ventas (con vínculo guardado) de ítems que coincidan con "<?= e($consumoQ) ?>" en este período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($movimientosDetalle['ventas'] as $mv): ?>
                    <tr>
                        <td class="small"><?= fechaHora($mv['fecha']) ?></td>
                        <td class="small fw-semibold"><?= e($mv['nombre']) ?></td>
                        <td class="text-end small fw-bold">-<?= number_format($mv['cantidad'], 3, ',', '.') ?></td>
                        <td class="small">
                            <?php if ($mv['venta_id']): ?>
                            <a href="<?= url('ventas/ver?id=' . $mv['venta_id']) ?>">#<?= $mv['venta_id'] ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($mv['motivo'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Productos vendidos -->
<h5 class="fw-bold mb-3">Productos vendidos en el período</h5>
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblVentas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Producto</th><th>Código</th><th class="text-end">Cant. vendida</th>
                        <th class="text-end">Total vendido</th><th class="text-end">Stock actual</th>
                        <th class="text-end">Stock teórico</th><th class="text-end">Valor diferencia</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte['ventas'] as $v): ?>
                    <?php $tieneComparativo = $v['diferencia'] !== null; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($v['producto']) ?></td>
                        <td class="small text-muted"><?= e($v['codigo'] ?? '—') ?></td>
                        <td class="text-end"><?= number_format($v['cant_vendida'],3,',','.') ?></td>
                        <td class="text-end fw-semibold"><?= moneda($v['total_vendido']) ?></td>
                        <td class="text-end small"><?= number_format($v['stock_actual'],3,',','.') ?></td>
                        <td class="text-end small"><?= $tieneComparativo ? number_format($v['stock_teorico_fin'],3,',','.') : '—' ?></td>
                        <td class="text-end small <?= $tieneComparativo && abs($v['valor_diferencia']) > 0.009 ? ($v['valor_diferencia'] < 0 ? 'text-danger fw-semibold' : 'text-warning fw-semibold') : '' ?>">
                            <?= $tieneComparativo ? moneda($v['valor_diferencia']) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
    Valor teórico = stock teórico × costo unitario — es el valor físico que debería haber en stock.
    Valor diferencia = diferencia × costo unitario — negativo (rojo) es pérdida, positivo (amarillo) es sobrante sin explicar, ambos ameritan revisar.
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
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Valor de la diferencia (posible pérdida/robo)</p>
                <h4 class="fw-bold mb-0 <?= $valorDiferenciaTotal < -0.009 ? 'text-danger' : ($valorDiferenciaTotal > 0.009 ? 'text-warning' : 'text-success') ?>">
                    <?= moneda($valorDiferenciaTotal) ?>
                </h4>
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
                        <th class="text-end">Costo unit.</th>
                        <th class="text-end">Valor teórico</th>
                        <th class="text-end">Valor diferencia</th>
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
                        <td class="text-end small text-muted"><?= moneda($row['costo']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($row['valor_teorico']) ?></td>
                        <td class="text-end <?= $hayDif ? ($row['valor_diferencia'] < 0 ? 'text-danger fw-semibold' : 'text-warning fw-semibold') : 'text-muted' ?>">
                            <?= moneda($row['valor_diferencia']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
                                    `Movimiento: ${parseFloat(row.cantidad) >= 0 ? '+' : ''}${parseFloat(row.cantidad).toLocaleString('es-AR',{maximumFractionDigits:3})}`,
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
        language: { emptyTable: 'Sin ventas en el período' },
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5','pdfHtml5'],
    });
    $('#tblMP').DataTable({
        pageLength: 25,
        language: { emptyTable: 'Sin producciones en el período' },
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5','pdfHtml5'],
    });

    var tablaTeorico = $('#tblTeorico').DataTable({
        pageLength: 50,
        language: { emptyTable: 'Sin ítems en stock' },
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
