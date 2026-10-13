<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Proveedores</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <a href="<?= url('proveedores/nuevo') ?>" class="btn btn-primary">+ Nuevo proveedor</a>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblProveedores" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Razón social</th><th>Rubro</th><th>CUIT</th><th>Cond. IVA</th>
                        <th>Teléfono</th><th>Email</th><th>CBU / Alias</th><th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($p['razon_social']) ?></td>
                        <td class="small"><?= e($p['rubro'] ?? '—') ?></td>
                        <td class="small"><?= e($p['cuit'] ?? '—') ?></td>
                        <td class="small"><?= e(ucfirst(str_replace('_', ' ', $p['condicion_iva']))) ?></td>
                        <td class="small"><?= e($p['telefono'] ?? '—') ?></td>
                        <td class="small"><?= e($p['email'] ?? '—') ?></td>
                        <td class="small text-muted"><?php
                            if (!$p['cbu']) { echo '—'; }
                            elseif (preg_match('/^\d{22}$/', $p['cbu'])) { echo '••• ' . substr(e($p['cbu']), -4); }
                            else { echo e($p['cbu']); }
                        ?></td>
                        <td>
                            <span class="badge <?= $p['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"
                                  style="cursor:pointer"
                                  onclick="toggleActivo(<?= $p['id'] ?>, this)"
                                  title="<?= $p['activo'] ? 'Clic para desactivar' : 'Clic para activar' ?>">
                                <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('compras?proveedor_id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0" title="Historial de compras">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            </a>
                            <a href="<?= url('pagos-proveedores/cuenta-corriente?proveedor_id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Cuenta corriente">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </a>
                            <a href="<?= url('proveedores/editar?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminar(<?= $p['id'] ?>, '<?= e($p['razon_social']) ?>')" title="Eliminar">
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
<?php
$importUrl        = url('proveedores/importar');
$importPreviewUrl = url('proveedores/previsualizar');
$importPlantilla  = url('proveedores/plantilla');
$importModulo     = 'Proveedores';
$importColumnas   = ['razon_social *', 'cuit', 'condicion_iva', 'email', 'telefono', 'direccion', 'banco', 'cbu/alias', 'rubro', 'estado'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') {
        new DataTable('#tblProveedores');
    }
});
async function _doEliminar(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/proveedores/eliminar`, { method: 'POST', body: fd });
    const d = await r.json();
    d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
}
function eliminar(id, nombre) {
    if (typeof showConfirm === 'function') {
        showConfirm(`¿Eliminar proveedor "${nombre}"?`, () => _doEliminar(id),
            { label: 'Eliminar', sub: 'Esta acción no se puede deshacer.' });
    } else {
        if (confirm(`¿Eliminar proveedor "${nombre}"?`)) _doEliminar(id);
    }
}
async function toggleActivo(id, badge) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/proveedores/toggle-activo`, { method: 'POST', body: fd });
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
