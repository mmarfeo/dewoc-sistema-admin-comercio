<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 fw-bold">Cuenta Corriente</h1>
        <p class="text-muted small mb-0">Socio: <?= e($socio['nombre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('pagos-proveedores/reembolso-nuevo?socio_id=' . $socio['id']) ?>" class="btn btn-sm btn-primary">+ Reembolsar</a>
        <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
    </div>
</div>

<div class="btn-group btn-group-sm mb-4" role="group">
    <a href="<?= url('pagos-proveedores/cuenta-corriente-socio?socio_id=' . $socio['id']) ?>"
       class="btn <?= !$historico ? 'btn-primary' : 'btn-outline-primary' ?>">Solo pendiente</a>
    <a href="<?= url('pagos-proveedores/cuenta-corriente-socio?socio_id=' . $socio['id'] . '&historico=1') ?>"
       class="btn <?= $historico ? 'btn-primary' : 'btn-outline-primary' ?>">Histórico completo</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body py-3 text-center">
                <div class="small text-muted">Total Debe (aportado)</div>
                <div class="fw-bold fs-5"><?= moneda($totalDebe) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body py-3 text-center">
                <div class="small text-muted">Total Haber (reembolsado)</div>
                <div class="fw-bold fs-5 text-success"><?= moneda($totalHaber) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 h-100 <?= $saldo > 0.009 ? 'bg-danger-soft' : 'bg-success-soft' ?>">
            <div class="card-body py-3 text-center">
                <div class="small <?= $saldo > 0.009 ? 'text-danger' : 'text-success' ?>">Saldo actual (a devolver)</div>
                <div class="fw-bold fs-5 <?= $saldo > 0.009 ? 'text-danger' : 'text-success' ?>"><?= moneda($saldo) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 bg-white pb-0">
        <h6 class="fw-semibold mb-0">Extracto de movimientos<?= $historico ? ' — histórico completo' : '' ?></h6>
    </div>
    <div class="card-body p-0">
        <?php if (!$movimientos): ?>
        <p class="text-muted small mb-0 p-3"><?= $historico ? 'Este socio todavía no tiene ningún movimiento registrado.' : 'Este socio no tiene ningún aporte pendiente de reembolso — está al día. Probá "Histórico completo" para ver todo lo ya saldado.' ?></p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th><th>Tipo</th><th>Detalle</th>
                        <th class="text-end">Debe</th><th class="text-end">Haber</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td class="small"><?= fecha($m['fecha']) ?></td>
                    <td>
                        <?php
                        $badges = [
                            'aporte'    => ['Aporte',    'bg-primary-soft text-primary'],
                            'reembolso' => ['Reembolso', 'bg-success-soft text-success'],
                        ];
                        [$etiqueta, $cls] = $badges[$m['tipo']] ?? [ucfirst($m['tipo']), 'bg-secondary-soft text-secondary'];
                        ?>
                        <span class="badge <?= $cls ?>"><?= $etiqueta ?></span>
                    </td>
                    <td class="small">
                        <?php
                        $detalle = $m['referencia'] ?: '—';
                        $href = match ($m['tipo']) {
                            'aporte'    => url('pagos-proveedores/ver?id=' . $m['id']),
                            'reembolso' => url('pagos-proveedores/reembolso-ver?id=' . $m['id']),
                            default     => null,
                        };
                        ?>
                        <?php if ($href): ?>
                        <a href="<?= $href ?>"><?= e($detalle) ?></a>
                        <?php else: ?>
                        <?= e($detalle) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end small"><?= $m['debe'] > 0 ? moneda($m['debe']) : '—' ?></td>
                    <td class="text-end small text-success"><?= $m['haber'] > 0 ? moneda($m['haber']) : '—' ?></td>
                    <td class="text-end small fw-semibold <?= $m['saldo'] > 0.009 ? 'text-danger' : 'text-success' ?>"><?= moneda($m['saldo']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-semibold">Totales</td>
                        <td class="text-end fw-bold"><?= moneda($totalDebe) ?></td>
                        <td class="text-end fw-bold text-success"><?= moneda($totalHaber) ?></td>
                        <td class="text-end fw-bold <?= $saldo > 0.009 ? 'text-danger' : 'text-success' ?>"><?= moneda($saldo) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
