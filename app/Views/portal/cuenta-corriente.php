<h1 class="h4 fw-bold mb-3">Mi cuenta corriente</h1>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-4">
                <div class="small text-muted">Compras</div>
                <div class="fs-4 fw-bold"><?= moneda($totalCompras) ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Pagado</div>
                <div class="fs-4 fw-bold text-success"><?= moneda($totalPagado) ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Saldo pendiente</div>
                <div class="fs-4 fw-bold <?= $saldoPendiente > 0 ? 'text-danger' : 'text-success' ?>"><?= moneda($saldoPendiente) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Ventas con saldo pendiente</div>
    <div class="card-body p-0">
        <?php if (!$ventasPendientes): ?>
        <p class="text-muted small mb-0 p-3">No tenés ventas con saldo pendiente.</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th class="text-end">Total</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($ventasPendientes as $v): ?>
                <tr>
                    <td class="small"><?= fecha($v['fecha']) ?></td>
                    <td class="text-end small"><?= moneda((float) $v['total_con_iva']) ?></td>
                    <td class="text-end small text-muted"><?= moneda((float) $v['pagado']) ?></td>
                    <td class="text-end small fw-semibold text-danger"><?= moneda((float) $v['saldo']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header border-0 bg-transparent fw-semibold">Historial de pagos</div>
    <div class="card-body p-0">
        <?php if (!$cobros): ?>
        <p class="text-muted small mb-0 p-3">Todavía no registraste ningún pago.</p>
        <?php else: ?>
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Concepto</th><th class="text-end">Monto</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($cobros as $c): ?>
                <tr class="<?= $c['estado'] === 'anulado' ? 'text-muted' : '' ?>">
                    <td class="small"><?= fecha($c['fecha']) ?></td>
                    <td class="small"><?= e($c['concepto'] ?? '—') ?></td>
                    <td class="text-end small fw-semibold"><?= moneda((float) $c['monto_total']) ?></td>
                    <td>
                        <?php $cls = $c['estado'] === 'anulado' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($c['estado']) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
