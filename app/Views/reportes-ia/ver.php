<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><?= e($reporte['titulo']) ?></h1>
        <?php if ($reporte['descripcion']): ?><div class="text-muted"><?= e($reporte['descripcion']) ?></div><?php endif; ?>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes-ia/exportar?id=' . $reporte['id']) ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
        </a>
        <a href="<?= url('reportes-ia') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="d-flex flex-wrap gap-3 mb-4 text-muted small">
    <span><i class="ti ti-tag me-1"></i><?= e(ucfirst($reporte['tipo'])) ?></span>
    <span><i class="ti ti-user me-1"></i><?= e($reporte['usuario_nombre'] ?? '—') ?></span>
    <span><i class="ti ti-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($reporte['created_at'])) ?></span>
    <span><i class="ti ti-list-numbers me-1"></i><?= count($reporte['resultado']) ?> filas</span>
    <?php if (!empty($reporte['parametros'])): ?>
        <?php foreach ($reporte['parametros'] as $clave => $valor): ?>
            <?php if ($valor === '' || $valor === null) continue; ?>
            <span><i class="ti ti-filter me-1"></i><?= e($clave) ?>: <?= e($valor) ?></span>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($reporte['sql_ejecutado']): ?>
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <button class="btn btn-link btn-sm p-0" type="button" data-bs-toggle="collapse" data-bs-target="#sqlReporte">
            Ver consulta SQL ejecutada
        </button>
        <div class="collapse mt-2" id="sqlReporte">
            <pre class="bg-body-tertiary p-2 rounded small mb-0"><code><?= e($reporte['sql_ejecutado']) ?></code></pre>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0">
    <div class="card-body p-0">
        <?php if (empty($reporte['resultado'])): ?>
            <div class="p-4 text-center text-muted">El reporte no tiene filas.</div>
        <?php else: ?>
        <table id="tblReporteIa" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <?php foreach ($reporte['columnas'] as $col): ?>
                        <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reporte['resultado'] as $fila): ?>
                <tr>
                    <?php foreach ($reporte['columnas'] as $col): ?>
                        <td><?= e($fila[$col] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($reporte['resultado'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblReporteIa', {
        scrollX: true,
        fixedHeader: true,
        layout: { topStart: null, topEnd: 'search' },
    });
});
</script>
<?php endif; ?>
