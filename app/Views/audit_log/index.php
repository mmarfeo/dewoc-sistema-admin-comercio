<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Log de Auditoría</h1>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        <i class="ti ti-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Módulo</label>
                <select name="modulo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $m): ?>
                    <option value="<?= e($m) ?>" <?= $filtros['modulo'] === $m ? 'selected' : '' ?>><?= e(ucfirst($m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Acción</label>
                <select name="accion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$logs): ?>
<div class="alert alert-info">No se encontraron registros en el período seleccionado.</div>
<?php else: ?>
<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblAudit" class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Módulo</th>
                    <th>Acción</th>
                    <th>Descripción</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <?php
                $colorAccion = match($l['accion']) {
                    'login'      => 'success',
                    'logout'     => 'secondary',
                    'crear'      => 'primary',
                    'editar'     => 'warning',
                    'eliminar'   => 'danger',
                    'anular'     => 'danger',
                    'login_fail' => 'danger',
                    default      => 'info',
                };
                ?>
                <tr>
                    <td class="small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                    <td class="fw-semibold small"><?= e($l['usuario_nombre'] ?? '—') ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e($l['modulo']) ?></span></td>
                    <td><span class="badge bg-<?= $colorAccion ?>-subtle text-<?= $colorAccion ?>-emphasis"><?= e($l['accion']) ?></span></td>
                    <td class="small text-muted"><?= e($l['descripcion'] ?? '') ?></td>
                    <td class="small text-muted font-monospace"><?= e($l['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined' && document.getElementById('tblAudit')) {
        new DataTable('#tblAudit', {
            order: [[0, 'desc']],
            pageLength: 50,
            language: { url: '<?= ASSETS_URL ?>/vendor/datatables/es-AR.json' },
            dom: 'Bfrtip',
            buttons: [
                { extend: 'excelHtml5', text: 'Excel', className: 'btn btn-sm btn-outline-success' },
                { extend: 'pdfHtml5',   text: 'PDF',   className: 'btn btn-sm btn-outline-danger', orientation: 'landscape' },
                { extend: 'colvis',     text: 'Columnas', className: 'btn btn-sm btn-outline-secondary' },
            ],
            stateSave: true,
        });
    }
});
</script>
