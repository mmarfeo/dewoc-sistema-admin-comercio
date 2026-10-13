<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 fw-bold">Cuenta Corriente</h1>
        <p class="text-muted small mb-0">
            <?= e($proveedor['razon_social']) ?><?= $proveedor['cuit'] ? ' · CUIT ' . e($proveedor['cuit']) : '' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('pagos-proveedores/nuevo?proveedor_id=' . $proveedor['id']) ?>" class="btn btn-sm btn-primary">+ Registrar Pago</a>
        <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
    </div>
</div>

<div class="btn-group btn-group-sm mb-4" role="group">
    <a href="<?= url('pagos-proveedores/cuenta-corriente?proveedor_id=' . $proveedor['id']) ?>"
       class="btn <?= !$historico ? 'btn-primary' : 'btn-outline-primary' ?>">Solo pendiente</a>
    <a href="<?= url('pagos-proveedores/cuenta-corriente?proveedor_id=' . $proveedor['id'] . '&historico=1') ?>"
       class="btn <?= $historico ? 'btn-primary' : 'btn-outline-primary' ?>">Histórico completo</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body py-3 text-center">
                <div class="small text-muted">Total Debe</div>
                <div class="fw-bold fs-5"><?= moneda($totalDebe) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body py-3 text-center">
                <div class="small text-muted">Total Haber (pagado)</div>
                <div class="fw-bold fs-5 text-success"><?= moneda($totalHaber) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 h-100 <?= $saldo > 0.009 ? 'bg-danger-soft' : 'bg-success-soft' ?>">
            <div class="card-body py-3 text-center">
                <div class="small <?= $saldo > 0.009 ? 'text-danger' : 'text-success' ?>">Saldo actual</div>
                <div class="fw-bold fs-5 <?= $saldo > 0.009 ? 'text-danger' : 'text-success' ?>"><?= moneda($saldo) ?></div>
            </div>
        </div>
    </div>
</div>

<?php
// Un color por cada factura/gasto (su propia fila de Debe, y la de cualquier Pago que se le
// haya aplicado, comparten el mismo 'grupo_color' — ver PagoProveedorModel::getExtracto()).
// Se asigna en el orden en que aparecen (por fecha, la deuda sale antes que su pago), rotando
// sobre una paleta chica — con muchos comprobantes en "Histórico completo" el color se puede
// repetir más adelante en la lista, es una ayuda visual aproximada, no un identificador único.
$paletaColores  = ['#ffe0e0', '#e0f0ff', '#e0ffe0', '#fff3cd', '#f3e0ff', '#ffe0f5', '#e0fff5', '#ffe8d0'];
$colorPorGrupo  = [];
foreach ($movimientos as $m) {
    $g = $m['grupo_color'] ?? null;
    if ($g && !isset($colorPorGrupo[$g])) {
        $colorPorGrupo[$g] = $paletaColores[count($colorPorGrupo) % count($paletaColores)];
    }
}
?>
<div class="card border-0">
    <div class="card-header border-0 bg-white pb-0">
        <h6 class="fw-semibold mb-0">Extracto de movimientos<?= $historico ? ' — histórico completo' : '' ?></h6>
        <?php if ($colorPorGrupo): ?>
        <p class="text-muted small mb-2">El Debe de cada factura/gasto y el Haber del pago que lo cubre comparten el mismo color de fondo.</p>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!$movimientos): ?>
        <p class="text-muted small mb-0 p-3"><?= $historico ? 'Este proveedor todavía no tiene ningún movimiento registrado.' : 'Este proveedor no tiene nada pendiente — probá "Histórico completo" para ver todo lo ya saldado.' ?></p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th><th>Tipo</th><th>ID</th><th>Detalle</th><th>Comprobante relacionado</th><th>Origen</th><th>Medio de pago</th>
                        <th class="text-end">Debe</th><th class="text-end">Haber</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movimientos as $m): ?>
                <?php $colorFila = $m['grupo_color'] ? ($colorPorGrupo[$m['grupo_color']] ?? null) : null; ?>
                <tr>
                    <td class="small"><?= fecha($m['fecha']) ?></td>
                    <td>
                        <?php
                        $badges = [
                            'factura' => ['Factura', 'bg-primary-soft text-primary'],
                            'gasto'   => ['Gasto',   'bg-secondary-soft text-secondary'],
                            'pago'    => ['Pago',    'bg-success-soft text-success'],
                        ];
                        [$etiqueta, $cls] = $badges[$m['tipo']] ?? [ucfirst($m['tipo']), 'bg-secondary-soft text-secondary'];
                        ?>
                        <span class="badge <?= $cls ?>"><?= $etiqueta ?></span>
                    </td>
                    <td class="small text-muted">#<?= (int) $m['id'] ?></td>
                    <td class="small">
                        <?php
                        $detalle = $m['referencia'] ?: '—';
                        $href = match ($m['tipo']) {
                            'factura' => url('compras/ver?id=' . $m['id']),
                            'pago'    => url('pagos-proveedores/ver?id=' . $m['id']),
                            default   => null,
                        };
                        ?>
                        <?php if ($href): ?>
                        <a href="<?= $href ?>"><?= e($detalle) ?></a>
                        <?php else: ?>
                        <?= e($detalle) ?>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($m['tipo'] === 'pago' && $m['comprobante_tipo']): ?>
                        <?php
                        $hrefComp = $m['comprobante_tipo'] === 'factura' ? url('compras/ver?id=' . $m['comprobante_id']) : null;
                        $etiquetaComp = ucfirst($m['comprobante_tipo']) . ' #' . (int) $m['comprobante_id'];
                        ?>
                        <?php if ($hrefComp): ?>
                        <a href="<?= $hrefComp ?>"><?= e($etiquetaComp) ?></a>
                        <?php else: ?>
                        <?= e($etiquetaComp) ?>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:11px"><?= e($m['comprobante_ref']) ?></div>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($m['origen']): ?>
                        <?php $esSocio = str_starts_with($m['origen'], 'Socio:'); ?>
                        <span class="badge <?= $esSocio ? 'bg-warning-soft text-warning' : 'bg-info-soft text-info' ?>"><?= e($m['origen']) ?></span>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($m['medio_pago'] ?: '—') ?></td>
                    <td class="text-end small" <?= ($colorFila && $m['debe'] > 0) ? 'style="background-color:' . $colorFila . '"' : '' ?>><?= $m['debe'] > 0 ? moneda($m['debe']) : '—' ?></td>
                    <td class="text-end small text-success" <?= ($colorFila && $m['haber'] > 0) ? 'style="background-color:' . $colorFila . '"' : '' ?>><?= $m['haber'] > 0 ? moneda($m['haber']) : '—' ?></td>
                    <td class="text-end small fw-semibold <?= $m['saldo'] > 0.009 ? 'text-danger' : 'text-success' ?>"><?= moneda($m['saldo']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="7" class="text-end fw-semibold">Totales</td>
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
