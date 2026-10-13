<!-- ── Título + botón ────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Opciones/Adicionales de Productos</h1>
        <p class="text-muted small mb-0">Grupos reutilizables de opciones (ej. "Gustos de helado") para asignar a productos desde su ficha.</p>
    </div>
    <?php if (canEdit('opciones_productos')): ?>
    <a href="<?= url('opciones-productos/nuevo') ?>" class="btn btn-primary">+ Nuevo grupo</a>
    <?php endif; ?>
</div>

<!-- ── Tabla ─────────────────────────────────────────────────── -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo de selección</th>
                        <th class="text-center">Opciones</th>
                        <th class="text-center">Productos que lo usan</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:140px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$grupos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay grupos de opciones creados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($grupos as $g): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($g['nombre']) ?></td>
                        <td>
                            <?= $g['tipo_seleccion'] === 'unica' ? 'Única (radio)' : 'Múltiple (checkbox)' ?>
                            <?php if ($g['tipo_seleccion'] === 'multiple' && $g['permite_repetir']): ?>
                                <span class="badge bg-info-soft text-info ms-1">permite repetir</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int)$g['total_opciones'] ?></td>
                        <td class="text-center"><?= (int)$g['total_productos'] ?></td>
                        <td>
                            <?php if ($g['activo']): ?>
                                <span class="badge bg-success-soft text-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-soft text-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('opciones-productos/editar?id=' . $g['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <?php if (canEdit('opciones_productos')): ?>
                                <?php if ($g['activo']): ?>
                                <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="toggleActivo(<?= $g['id'] ?>, false, '<?= e($g['nombre']) ?>')" title="Desactivar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-success py-0 ms-1" onclick="toggleActivo(<?= $g['id'] ?>, true, '<?= e($g['nombre']) ?>')" title="Reactivar">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';

function toggleActivo(id, activar, nombre) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const r = await fetch(`${BASE_URL}/opciones-productos/${activar ? 'activar' : 'eliminar'}`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) { showToast(d.mensaje, 'success'); setTimeout(() => location.reload(), 800); }
            else showToast(d.error || 'No se pudo completar.', 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    };
    const msg = activar ? `¿Reactivar "${nombre}"?` : `¿Desactivar "${nombre}"?`;
    const sub = activar ? '' : 'Deja de estar disponible para elegir en el POS, pero no se borra (las ventas que ya lo usaron no se ven afectadas).';
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, { label: activar ? 'Reactivar' : 'Desactivar', sub, type: activar ? 'success' : 'danger' });
    } else {
        if (confirm(msg + ' ' + sub)) ejecutar();
    }
}
</script>
