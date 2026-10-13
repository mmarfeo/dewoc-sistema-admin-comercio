<?php
$_exportQ = http_build_query(['tipo' => 'caja', 'desde' => $desde, 'hasta' => $hasta]);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Caja</h1>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes/exportar?' . $_exportQ) ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-table-export me-1"></i> Exportar CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-4">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $diferencia     = (float)($resumen['diferencia_total'] ?? 0);
    $diferenciaColor = $diferencia >= 0 ? 'success' : 'danger';
    $kpis = [
        ['label' => 'Turnos cerrados',   'valor' => number_format((int)($resumen['total_turnos'] ?? 0)), 'icon' => 'ti-briefcase', 'color' => 'primary'],
        ['label' => 'Total calculado',   'valor' => '$' . number_format((float)($resumen['total_calculado'] ?? 0), 2, ',', '.'), 'icon' => 'ti-calculator', 'color' => 'success'],
        ['label' => 'Total declarado',   'valor' => '$' . number_format((float)($resumen['total_declarado'] ?? 0), 2, ',', '.'), 'icon' => 'ti-cash', 'color' => 'info'],
        ['label' => 'Diferencia total',  'valor' => '$' . number_format(abs($diferencia), 2, ',', '.') . ($diferencia < 0 ? ' (Faltante)' : ' (Sobrante)'), 'icon' => 'ti-scale', 'color' => $diferenciaColor],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-<?= $k['color'] ?>-soft text-<?= $k['color'] ?>">
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

<!-- Tabla de turnos -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Turnos de caja</span>
        <span class="badge bg-secondary"><?= count($turnos) ?> turnos</span>
    </div>
    <div class="card-body p-0">
        <table id="tblCaja" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Turno #</th>
                    <th>Cajero</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Estado</th>
                    <th class="text-end">Ingresos</th>
                    <th class="text-end">Egresos</th>
                    <th class="text-end">Calculado</th>
                    <th class="text-end">Declarado</th>
                    <th class="text-end">Diferencia</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($turnos as $t): ?>
            <?php $dif = (float)$t['diferencia']; ?>
            <tr>
                <td>#<?= $t['numero'] ?></td>
                <td><?= e($t['cajero'] ?? '—') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($t['apertura_at'])) ?></td>
                <td><?= $t['cierre_at'] ? date('d/m/Y H:i', strtotime($t['cierre_at'])) : '—' ?></td>
                <td>
                    <?php if ($t['estado'] === 'abierto'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Abierto</span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Cerrado</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-success">$<?= number_format((float)$t['total_ingresos'], 2, ',', '.') ?></td>
                <td class="text-end text-danger">$<?= number_format((float)$t['total_egresos'], 2, ',', '.') ?></td>
                <td class="text-end"><?= $t['monto_cierre_calculado'] !== null ? '$' . number_format((float)$t['monto_cierre_calculado'], 2, ',', '.') : '—' ?></td>
                <td class="text-end"><?= $t['monto_cierre_declarado'] !== null ? '$' . number_format((float)$t['monto_cierre_declarado'], 2, ',', '.') : '—' ?></td>
                <td class="text-end fw-semibold <?= $dif >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $t['diferencia'] !== null ? ($dif >= 0 ? '+' : '') . '$' . number_format(abs($dif), 2, ',', '.') : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblCaja', {
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        order: [[2, 'desc']],
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
    });
});
</script>
