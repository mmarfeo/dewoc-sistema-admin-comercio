<?php
$_exportQ = http_build_query(array_filter([
    'tipo'         => 'pagos',
    'desde'        => $desde,
    'hasta'        => $hasta,
    'proveedor_id' => $filtros['proveedor_id'] ?: null,
    'estado'       => $filtros['estado']       ?: null,
], fn($v) => $v !== null && $v !== ''));
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Pagos</h1>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes/exportar?' . $_exportQ) ?>" class="btn btn-outline-success btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Exportar CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimir
        </button>
    </div>
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
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Proveedor</label>
                <select name="proveedor_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($proveedores as $prov): ?>
                    <option value="<?= $prov['id'] ?>" <?= (int)$filtros['proveedor_id'] === (int)$prov['id'] ? 'selected' : '' ?>>
                        <?= e($prov['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pagado"    <?= $filtros['estado'] === 'pagado'    ? 'selected' : '' ?>>Pagado</option>
                    <option value="pendiente" <?= $filtros['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
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
    $svgReceipt = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>';
    $svgDollar  = '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>';
    $svgCheck   = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
    $svgClock   = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';

    $kpis = [
        ['label' => 'Comprobantes',     'valor' => number_format((int)($resumen['total_comprobantes'] ?? 0)), 'svg' => $svgReceipt, 'color' => 'primary'],
        ['label' => 'Total del período','valor' => '$' . number_format((float)($resumen['total_general'] ?? 0), 2, ',', '.'), 'svg' => $svgDollar, 'color' => 'info'],
        ['label' => 'Pagado',           'valor' => '$' . number_format((float)($resumen['total_pagado'] ?? 0), 2, ',', '.'), 'svg' => $svgCheck, 'color' => 'success'],
        ['label' => 'Pendiente (' . number_format((int)($resumen['cant_pendientes'] ?? 0)) . ')', 'valor' => '$' . number_format((float)($resumen['total_pendiente'] ?? 0), 2, ',', '.'), 'svg' => $svgClock, 'color' => 'warning'],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-<?= $k['color'] ?>-soft text-<?= $k['color'] ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?= $k['svg'] ?></svg>
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

<!-- Comprobantes del período -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Comprobantes del período (facturas de compra + gastos)</span>
        <span class="badge bg-secondary"><?= count($comprobantes) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Comprobante</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th class="text-end">Saldo pendiente</th>
                        <th class="d-print-none text-end">Ver</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$comprobantes): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin comprobantes en el período.</td></tr>
                <?php endif; ?>
                <?php foreach ($comprobantes as $c): ?>
                    <tr>
                        <td>
                            <?php if ($c['tipo'] === 'factura'): ?>
                            <span class="badge bg-primary-soft text-primary">Factura</span>
                            <?php else: ?>
                            <span class="badge bg-secondary-soft text-secondary">Gasto</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
                        <td><?= e($c['proveedor'] ?? '—') ?></td>
                        <td><?= e($c['descripcion']) ?></td>
                        <td class="text-end fw-semibold">$<?= number_format((float)$c['total'], 2, ',', '.') ?></td>
                        <td>
                            <?php if ($c['estado_pago'] === 'pagado'): ?>
                            <span class="badge bg-success-soft text-success">Pagado</span>
                            <?php else: ?>
                            <span class="badge bg-warning-soft text-warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end <?= (float)$c['saldo_pendiente'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                            <?= (float)$c['saldo_pendiente'] > 0 ? '$' . number_format((float)$c['saldo_pendiente'], 2, ',', '.') : '—' ?>
                        </td>
                        <td class="text-end d-print-none">
                            <?php if ($c['tipo'] === 'factura'): ?>
                            <a href="<?= url('compras/ver?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php else: ?>
                            <a href="<?= url('gastos') . '?desde=' . e($c['fecha']) . '&hasta=' . e($c['fecha']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagos registrados en el período -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Pagos registrados en el período</span>
        <span class="badge bg-secondary"><?= count($pagos) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Beneficiario</th>
                        <th>Concepto</th>
                        <th class="text-end">Monto</th>
                        <th class="d-print-none text-end">Ver</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pagos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Sin pagos registrados en el período.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagos as $p): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                        <td><?= ucfirst(e($p['tipo'])) ?></td>
                        <td><?= e($p['beneficiario'] ?? '—') ?></td>
                        <td class="text-truncate" style="max-width:280px"><?= e($p['concepto'] ?? '') ?></td>
                        <td class="text-end fw-semibold">$<?= number_format((float)$p['monto_total'], 2, ',', '.') ?></td>
                        <td class="text-end d-print-none">
                            <a href="<?= url('pagos-proveedores/ver?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
