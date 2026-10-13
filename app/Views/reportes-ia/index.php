<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reportes IA</h1>
</div>

<div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div>Pedile al <a href="<?= url('chat') ?>">Chat IA</a> un reporte (por ejemplo "generame el reporte de ventas del mes pasado" o "necesito un reporte de clientes que no compraron en los últimos 60 días") y va a aparecer acá, listo para ver o descargar en Excel.</div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblReportesIa" class="table table-hover align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Generado por</th>
                    <th>Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reportes as $r): ?>
            <tr>
                <td>
                    <a href="<?= url('reportes-ia/ver?id=' . $r['id']) ?>" class="fw-semibold text-decoration-none"><?= e($r['titulo']) ?></a>
                    <?php if ($r['descripcion']): ?><div class="text-muted small"><?= e($r['descripcion']) ?></div><?php endif; ?>
                </td>
                <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e(ucfirst($r['tipo'])) ?></span></td>
                <td><?= e($r['usuario_nombre'] ?? '—') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td class="text-end">
                    <a href="<?= url('reportes-ia/ver?id=' . $r['id']) ?>" class="btn btn-outline-primary btn-sm" title="Ver">
                        <i class="ti ti-eye"></i>
                    </a>
                    <a href="<?= url('reportes-ia/exportar?id=' . $r['id']) ?>" class="btn btn-outline-success btn-sm" title="Descargar Excel">
                        <i class="ti ti-file-spreadsheet"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm btnEliminarReporte" data-id="<?= $r['id'] ?>" title="Eliminar">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblReportesIa', {
        stateSave: true,
        order: [[3, 'desc']],
        columnDefs: [{ orderable: false, targets: 4 }],
        layout: { topStart: null, topEnd: 'search' },
    });

    document.querySelectorAll('.btnEliminarReporte').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const ejecutar = () => {
                const fd = new FormData();
                fd.append('id', btn.dataset.id);
                fd.append('_csrf_token', '<?= Csrf::token() ?>');
                fetch('<?= url('reportes-ia/eliminar') ?>', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) location.reload();
                        else alert(res.error || 'No se pudo eliminar.');
                    });
            };
            if (typeof showConfirm === 'function') {
                showConfirm('¿Eliminar este reporte?', ejecutar, { label: 'Eliminar' });
            } else {
                if (confirm('¿Eliminar este reporte?')) ejecutar();
            }
        });
    });
});
</script>
