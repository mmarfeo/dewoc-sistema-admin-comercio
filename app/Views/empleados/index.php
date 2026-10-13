<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Empleados</h1>
        <p class="text-muted small mb-0">Ficha de empleados y sueldos — separado de Compras/Proveedores</p>
    </div>
    <a href="<?= url('empleados/nuevo') ?>" class="btn btn-primary">+ Nuevo empleado</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblEmpleados" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Documento</th><th>Puesto</th>
                        <th class="text-end">Sueldo mensual</th><th>Ingreso</th>
                        <th>Teléfono</th><th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $emp): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($emp['nombre']) ?></td>
                        <td class="small"><?= e($emp['documento'] ?? '—') ?></td>
                        <td class="small"><?= e($emp['puesto'] ?? '—') ?></td>
                        <?php
                            $sueldoMostrado = $emp['modalidad_pago'] === 'por_hora'
                                ? (float)$emp['horas_mensuales'] * (float)$emp['valor_hora']
                                : (float)$emp['sueldo_mensual'];
                        ?>
                        <td class="text-end small"><?= $sueldoMostrado > 0 ? moneda($sueldoMostrado) : '—' ?></td>
                        <td class="small"><?= $emp['fecha_ingreso'] ? fecha($emp['fecha_ingreso']) : '—' ?></td>
                        <td class="small"><?= e($emp['telefono'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $emp['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"
                                  style="cursor:pointer"
                                  onclick="toggleActivo(<?= $emp['id'] ?>, this)"
                                  title="<?= $emp['activo'] ? 'Clic para desactivar' : 'Clic para activar' ?>">
                                <?= $emp['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('empleados/ver?id=' . $emp['id']) ?>" class="btn btn-sm btn-outline-secondary py-0" title="Ver ficha / historial de pagos">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="<?= url('empleados/editar?id=' . $emp['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminar(<?= $emp['id'] ?>, '<?= e($emp['nombre']) ?>')" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') {
        new DataTable('#tblEmpleados', { language: { emptyTable: 'Sin empleados registrados' } });
    }
});
async function _doEliminar(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/empleados/eliminar`, { method: 'POST', body: fd });
    const d = await r.json();
    d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
}
function eliminar(id, nombre) {
    if (typeof showConfirm === 'function') {
        showConfirm(`¿Eliminar empleado "${nombre}"?`, () => _doEliminar(id),
            { label: 'Eliminar', sub: 'Esta acción no se puede deshacer.' });
    } else {
        if (confirm(`¿Eliminar empleado "${nombre}"?`)) _doEliminar(id);
    }
}
async function toggleActivo(id, badge) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/empleados/toggle-activo`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        const on = d.activo === 1;
        badge.textContent = on ? 'Activo' : 'Inactivo';
        badge.className   = 'badge ' + (on ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary');
        badge.title       = on ? 'Clic para desactivar' : 'Clic para activar';
        showToast(d.mensaje);
    } else {
        showToast(d.error, 'danger');
    }
}
</script>
