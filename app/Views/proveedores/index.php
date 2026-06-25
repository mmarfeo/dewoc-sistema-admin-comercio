<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Proveedores</h1>
    <a href="<?= url('proveedores/nuevo') ?>" class="btn btn-primary">+ Nuevo proveedor</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblProveedores" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Razón social</th><th>CUIT</th><th>Cond. IVA</th>
                        <th>Teléfono</th><th>Email</th><th>CBU</th><th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($p['razon_social']) ?></td>
                        <td class="small"><?= e($p['cuit'] ?? '—') ?></td>
                        <td class="small"><?= e(ucfirst(str_replace('_', ' ', $p['condicion_iva']))) ?></td>
                        <td class="small"><?= e($p['telefono'] ?? '—') ?></td>
                        <td class="small"><?= e($p['email'] ?? '—') ?></td>
                        <td class="small text-muted"><?= $p['cbu'] ? '••• ' . substr(e($p['cbu']), -4) : '—' ?></td>
                        <td><span class="badge <?= $p['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= $p['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-center">
                            <a href="<?= url('compras?proveedor_id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0" title="Historial de compras">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
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
<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(() => { $('#tblProveedores').DataTable(); });
async function eliminar(id,nombre){if(!confirm(`¿Eliminar "${nombre}"?`))return;const fd=new FormData();fd.append('id',id);fd.append('_csrf_token',CSRF_TOKEN);const r=await fetch(`${BASE_URL}/proveedores/eliminar`,{method:'POST',body:fd});const d=await r.json();d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');}
</script>
