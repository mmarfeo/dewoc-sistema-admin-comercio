<?php
$metodoLabels = [
    'efectivo'      => 'Efectivo',
    'debito'        => 'Débito',
    'credito'       => 'Crédito',
    'transferencia' => 'Transferencia',
    'mercadopago'   => 'Mercado Pago',
];
$c = $resumen['compras'];
$v = $resumen['ventas'];
$m = $resumen['margen'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte Semanal</h1>
</div>

<!-- Filtro de semana -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="<?= url('reportes/semanal') ?>" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('reportes/semanal') ?>" class="btn btn-sm btn-outline-secondary ms-1">Semana pasada</a>
            </div>
            <div class="col-sm-auto ms-auto text-muted small">
                Período: <strong><?= fecha($desde) ?> — <?= fecha($hasta) ?></strong>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- Compras -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Compras de la semana</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">Total</span>
                    <span class="fw-bold fs-5"><?= moneda($c['total']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-success">Pagadas (<?= $c['pagadas']['cantidad'] ?>)</span>
                    <span class="fw-semibold"><?= moneda($c['pagadas']['total']) ?></span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-warning">Pendientes (<?= $c['pendientes']['cantidad'] ?>)</span>
                    <span class="fw-semibold"><?= moneda($c['pendientes']['total']) ?></span>
                </div>
                <?php $pctPagado = $c['total'] > 0.009 ? round($c['pagadas']['total'] / $c['total'] * 100, 1) : 0; ?>
                <div class="progress mt-3" style="height:8px">
                    <div class="progress-bar bg-success" style="width:<?= $pctPagado ?>%"></div>
                </div>
                <div class="text-muted text-center mt-1" style="font-size:.7rem"><?= $pctPagado ?>% pagado</div>
            </div>
        </div>
    </div>

    <!-- Ventas por método de pago -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Ventas de la semana</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">Total (<?= $v['cantidad'] ?> ventas)</span>
                    <span class="fw-bold fs-5"><?= moneda($v['total']) ?></span>
                </div>
                <?php if (!$v['por_metodo']): ?>
                <div class="text-muted small text-center py-2">Sin ventas en el período.</div>
                <?php endif; ?>
                <?php foreach ($v['por_metodo'] as $pm): ?>
                <?php $pct = $v['total'] > 0.009 ? round($pm['total'] / $v['total'] * 100, 1) : 0; ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= e($metodoLabels[$pm['metodo_pago']] ?? ucfirst($pm['metodo_pago'])) ?></span>
                    <span class="fw-semibold"><?= moneda($pm['total']) ?> <span class="text-muted fw-normal">(<?= $pct ?>%)</span></span>
                </div>
                <div class="progress mb-2" style="height:6px">
                    <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Margen bruto -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Margen bruto</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Ingresos (sin IVA)</span>
                    <span class="fw-semibold"><?= moneda($m['ingresos']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Costo de mercadería vendida</span>
                    <span class="fw-semibold text-danger">- <?= moneda($m['costo']) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Margen bruto</span>
                    <span class="fw-bold fs-5 <?= $m['margen'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= moneda($m['margen']) ?></span>
                </div>
                <div class="text-muted text-end small">(<?= $m['margen_pct'] ?>% sobre ingresos)</div>
            </div>
        </div>
    </div>
</div>
