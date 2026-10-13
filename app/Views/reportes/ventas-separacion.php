<?php
/**
 * Sub-vista incluida desde ventas.php dentro de la pestaña "Separación de Ingresos".
 * Variables disponibles por extract() en Controller::capture(): $desde, $hasta,
 * $filtros, $agrupacion, $mesesReferencia, $referencia, $separacion, $detalleRecompra.
 */
$pct = $referencia['porcentajes'];
$sinDatosRef = $referencia['sin_datos'];

/** Arma el HTML (tabla) de detalle para el popover "ⓘ" de una categoría */
$sepPopoverHtml = function (array $desglose, float $total, ?float $pct, string $intro): string {
    if (!$desglose) return '';
    $filas = array_map(
        fn($d) => '<tr><td>' . e($d['concepto']) . '</td><td class="text-end fw-semibold text-nowrap">' . moneda($d['monto']) . '</td></tr>',
        $desglose
    );
    $pctLinea = $pct !== null ? '<p class="small mb-2"><strong>% sobre ventas ref.:</strong> ' . number_format($pct, 2, ',', '.') . '%</p>' : '';
    return '<p class="small text-muted mb-2">' . e($intro) . '</p>' . $pctLinea
        . '<table class="table table-sm table-striped mb-0"><thead><tr><th>Concepto</th><th class="text-end">Monto</th></tr></thead><tbody>'
        . implode('', $filas)
        . '<tr class="table-light"><td class="text-end fw-semibold">Total referencia</td><td class="text-end fw-semibold">' . moneda($total) . '</td></tr>'
        . '</tbody></table>';
};
?>
<style>
.sep-cat-info-btn { font-size: .7rem; line-height: 1; padding: 0 .15rem; color: var(--bs-secondary); text-decoration: none; vertical-align: baseline; }
.sep-cat-info-btn:hover { color: var(--bs-primary); }
.popover.sep-cat-popover { max-width: min(480px, 92vw); }
.popover.sep-cat-popover .popover-body { padding: .5rem; max-height: 320px; overflow-y: auto; }
.popover.sep-cat-popover .table { font-size: .75rem; margin-bottom: 0; }
</style>
<p class="text-muted small mb-3">
    Los porcentajes se calculan sobre los <strong><?= $referencia['meses'] ?> meses completos</strong> anteriores
    (<?= fecha($referencia['desde']) ?> — <?= fecha($referencia['hasta']) ?>) y se aplican a las ventas del período
    filtrado arriba, agrupadas por día, semana o mes. Así sabés cuánto separar de lo vendido para Recompra de
    materia prima, Impuestos, Servicios y Sueldos, y cuánto queda Disponible como ganancia real.
</p>

<!-- Selector de agrupación / meses de referencia -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="desde" value="<?= e($desde) ?>">
            <input type="hidden" name="hasta" value="<?= e($hasta) ?>">
            <?php if ($filtros['metodo_pago']): ?><input type="hidden" name="metodo_pago" value="<?= e($filtros['metodo_pago']) ?>"><?php endif; ?>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Agrupar por</label>
                <select name="agrupacion" class="form-select">
                    <option value="dia"    <?= $agrupacion === 'dia'    ? 'selected' : '' ?>>Día</option>
                    <option value="semana" <?= $agrupacion === 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="mes"    <?= $agrupacion === 'mes'    ? 'selected' : '' ?>>Mes</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Meses de referencia</label>
                <select name="meses_referencia" class="form-select">
                    <?php foreach ([1, 3, 6, 12] as $m): ?>
                    <option value="<?= $m ?>" <?= $mesesReferencia === $m ? 'selected' : '' ?>><?= $m ?> <?= $m === 1 ? 'mes' : 'meses' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary w-100">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($sinDatosRef): ?>
<div class="alert alert-warning">
    No hay ventas registradas en el período de referencia (<?= fecha($referencia['desde']) ?> —
    <?= fecha($referencia['hasta']) ?>), así que no se pueden calcular porcentajes todavía. Probá con más meses de
    referencia o esperá a tener ventas cargadas en ese rango.
</div>
<?php else: ?>

<!-- Tarjetas de % de referencia -->
<div class="row g-3 mb-4">
    <?php
    $tarjetas = [
        [
            'id' => 'recompra', 'label' => 'Recompra', 'color' => 'success',
            'pct' => $pct['recompra'], 'monto' => $referencia['total_recompra'],
            'desglose' => $referencia['desglose_recompra'],
            'intro' => 'Costo de mercadería según lo vendido (recetas + materia prima + merma, a precio actual).',
        ],
        [
            'id' => 'impuestos', 'label' => 'Impuestos', 'color' => 'warning',
            'pct' => $pct['impuestos'], 'monto' => $referencia['total_impuestos'],
            'desglose' => $referencia['desglose_impuestos'],
            'intro' => 'Gastos categoría Impuesto + impuestos de compras del período de referencia.',
        ],
        [
            'id' => 'servicios', 'label' => 'Servicios', 'color' => 'primary',
            'pct' => $pct['servicios'], 'monto' => $referencia['total_servicios'],
            'desglose' => $referencia['desglose_servicios'],
            'intro' => 'Costos fijos activos en el período (alquiler, luz, gas, etc.), excluyendo sueldos.',
        ],
        [
            'id' => 'sueldos', 'label' => 'Sueldos', 'color' => 'info',
            'pct' => $pct['sueldos'], 'monto' => $referencia['total_sueldos'],
            'desglose' => $referencia['desglose_sueldos'],
            'intro' => 'Empleados/Nómina + costos fijos con categoría sueldos/nómina/personal.',
        ],
        ['id' => 'disponible', 'label' => 'Disponible', 'color' => 'secondary', 'pct' => $pct['disponible'], 'monto' => null, 'desglose' => [], 'intro' => ''],
    ];
    $popoversData = [];
    foreach ($tarjetas as $t):
        $btnId = 'sepInfo' . ucfirst($t['id']);
        $html  = $sepPopoverHtml($t['desglose'], (float)($t['monto'] ?? 0), $t['pct'], $t['intro']);
        if ($html !== '') $popoversData[$btnId] = ['html' => $html, 'title' => 'Detalle de ' . $t['label'] . ' (referencia)'];
    ?>
    <div class="col-6 col-lg">
        <div class="card border-0 border-start border-4 border-<?= $t['color'] ?> h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase text-muted fw-semibold mb-1 d-flex align-items-center gap-1" style="font-size:.7rem">
                    <?= $t['label'] ?>
                    <?php if ($html !== ''): ?>
                    <button type="button" id="<?= $btnId ?>" class="btn btn-link sep-cat-info-btn p-0" tabindex="0" aria-label="Ver detalle de <?= e($t['label']) ?>">ⓘ</button>
                    <?php endif; ?>
                </h6>
                <div class="fs-4 fw-bold"><?= number_format($t['pct'], 1, ',', '.') ?>%</div>
                <?php if ($t['monto'] !== null): ?>
                <div class="small text-muted"><?= moneda($t['monto']) ?> <span class="d-block" style="font-size:.65rem">ref. <?= $referencia['meses'] ?> meses</span></div>
                <?php else: ?>
                <div class="small text-muted">Ganancia / reserva</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-lg">
        <div class="card border-0 bg-light h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem">Ventas ref.</h6>
                <div class="fs-4 fw-bold"><?= moneda($referencia['total_ventas']) ?></div>
                <div class="small text-muted"><?= $referencia['meses'] ?> meses completos</div>
            </div>
        </div>
    </div>
</div>

<?php if ($popoversData): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const popoversData = <?= json_encode($popoversData, JSON_UNESCAPED_UNICODE) ?>;
    Object.entries(popoversData).forEach(([id, data]) => {
        const btn = document.getElementById(id);
        if (!btn || !window.bootstrap || !bootstrap.Popover) return;
        new bootstrap.Popover(btn, {
            container: 'body',
            customClass: 'sep-cat-popover',
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            placement: 'auto',
            title: data.title,
            content: data.html,
        });
    });
});
</script>
<?php endif; ?>

<!-- Tabla: cuánto separar por período -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Cuánto separar por período</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Período</th>
                        <th class="text-end">Ventas</th>
                        <th class="text-end text-success">Recompra</th>
                        <th class="text-end text-warning">Impuestos</th>
                        <th class="text-end text-primary">Servicios</th>
                        <th class="text-end text-info">Sueldos</th>
                        <th class="text-end">Disponible</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$separacion['filas']): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Sin ventas en el período filtrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($separacion['filas'] as $f): $s = $f['separar']; ?>
                <tr>
                    <td><?= e($f['label']) ?> <small class="text-muted">(<?= $f['cant_ventas'] ?> ventas)</small></td>
                    <td class="text-end"><?= moneda($f['ventas']) ?></td>
                    <td class="text-end text-success"><?= moneda($s['recompra']) ?></td>
                    <td class="text-end text-warning"><?= moneda($s['impuestos']) ?></td>
                    <td class="text-end text-primary"><?= moneda($s['servicios']) ?></td>
                    <td class="text-end text-info"><?= moneda($s['sueldos']) ?></td>
                    <td class="text-end fw-semibold"><?= moneda($s['disponible']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($separacion['filas']): $tot = $separacion['totales']; ?>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-end"><?= moneda($tot['ventas']) ?></td>
                        <td class="text-end text-success"><?= moneda($tot['recompra']) ?></td>
                        <td class="text-end text-warning"><?= moneda($tot['impuestos']) ?></td>
                        <td class="text-end text-primary"><?= moneda($tot['servicios']) ?></td>
                        <td class="text-end text-info"><?= moneda($tot['sueldos']) ?></td>
                        <td class="text-end"><?= moneda($tot['disponible']) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Detalle de recompra por producto (costo de MP en vivo, según lo vendido en el período filtrado) -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold">Detalle de Recompra — por producto vendido</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Unidades vendidas</th>
                        <th class="text-end">Costo unit. (MP, en vivo)</th>
                        <th class="text-end">Costo recompra</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$detalleRecompra): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Sin ventas con costo de MP en el período filtrado.</td></tr>
                <?php endif; ?>
                <?php foreach ($detalleRecompra as $p): ?>
                <tr>
                    <td><?= e($p['nombre']) ?></td>
                    <td class="text-end"><?= number_format((float)$p['unidades_vendidas'], 2, ',', '.') ?></td>
                    <td class="text-end"><?= moneda((float)$p['costo_unitario']) ?></td>
                    <td class="text-end fw-semibold"><?= moneda((float)$p['costo_total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
