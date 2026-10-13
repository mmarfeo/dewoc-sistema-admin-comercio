<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Usuarios</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <a href="<?= url('usuarios/nuevo') ?>" class="btn btn-primary">+ Nuevo usuario</a>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblUsuarios" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th>
                        <th>Último login</th><th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['nombre']) ?></td>
                        <td class="small text-muted"><?= e($u['email']) ?></td>
                        <td><span class="badge bg-primary-soft text-primary"><?= e($u['rol']) ?></span></td>
                        <td><span class="badge <?= $u['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="small text-muted"><?= $u['ultimo_login'] ? fechaHora($u['ultimo_login']) : '—' ?></td>
                        <td class="text-center">
                            <a href="<?= url('usuarios/editar?id='.$u['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="<?= url('chat/ver-usuario?id='.$u['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Ver historial del Chat IA">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </a>
                            <?php if ($u['id'] != Session::user()['id']): ?>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminar(<?= $u['id'] ?>, '<?= e($u['nombre']) ?>')" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
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
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblUsuarios').DataTable());
function eliminar(id,nombre){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/usuarios/eliminar`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };
    const msg = `¿Eliminar usuario "${nombre}"?`;
    if (typeof showConfirm === 'function') { showConfirm(msg, ejecutar, { label: 'Eliminar' }); }
    else { if (confirm(msg)) ejecutar(); }
}
</script>
<?php
$importUrl       = url('usuarios/importar');
$importPlantilla = url('usuarios/plantilla');
$importModulo    = 'Usuarios';
$importColumnas  = ['nombre *', 'email *', 'password * (mín. 8 caracteres)', 'rol * (admin/cajero/deposito/vendedor)'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
