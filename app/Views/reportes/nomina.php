<?php
$_exportQ = http_build_query(array_filter([
    'tipo'  => 'nomina',
    'desde' => $desde,
    'hasta' => $hasta,
], fn($v) => $v !== null && $v !== ''));
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Nómina</h1>
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
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="chkActivos" name="activos" value="1" <?= $soloActivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="chkActivos">Solo empleados activos</label>
                </div>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-primary-soft text-primary"><i class="ti ti-users fs-4"></i></div>
                <div>
                    <div class="fw-bold fs-5"><?= count($filas) ?></div>
                    <div class="text-muted small">Empleados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-success-soft text-success"><i class="ti ti-currency-dollar fs-4"></i></div>
                <div>
                    <div class="fw-bold fs-5"><?= moneda($totalPagado) ?></div>
                    <div class="text-muted small">Pagado en el período</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-warning-soft text-warning"><i class="ti ti-clock-hour-4 fs-4"></i></div>
                <div>
                    <div class="fw-bold fs-5"><?= moneda($totalPendiente) ?></div>
                    <div class="text-muted small">Pendiente de liquidar (hoy)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detalle -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Detalle por empleado</span>
        <span class="badge bg-secondary"><?= count($filas) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <table id="tblNomina" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th>Modalidad</th>
                    <th>Estado</th>
                    <th class="text-end">Pagado en el período</th>
                    <th class="text-end">Pendiente de liquidar</th>
                    <th class="d-print-none text-end">Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
            <tr>
                <td class="fw-semibold"><?= e($f['nombre']) ?></td>
                <td><span class="badge bg-primary-soft text-primary"><?= e($f['modalidad']) ?></span></td>
                <td>
                    <span class="badge <?= $f['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                        <?= $f['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td class="text-end">$<?= number_format($f['pagado_periodo'], 2, ',', '.') ?></td>
                <td class="text-end fw-semibold <?= $f['pendiente'] > 0 ? 'text-warning' : '' ?>">$<?= number_format($f['pendiente'], 2, ',', '.') ?></td>
                <td class="text-end d-print-none">
                    <a href="<?= url('empleados/ver?id=' . $f['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="ti ti-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <td colspan="3">Totales</td>
                    <td class="text-end">$<?= number_format($totalPagado, 2, ',', '.') ?></td>
                    <td class="text-end">$<?= number_format($totalPendiente, 2, ',', '.') ?></td>
                    <td class="d-print-none"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblNomina', {
        stateSave: true,
        scrollX: true,
        order: [[4, 'desc']],
        paging: false,
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
    });
});
</script>
