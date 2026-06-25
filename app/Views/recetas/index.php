<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Recetas de Producción</h1>
    <a href="<?= url('recetas/nueva') ?>" class="btn btn-primary">+ Nueva receta</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblRecetas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Producto final</th><th>Rendimiento</th>
                        <th class="text-center">Ingredientes</th><th>Estado</th>
                        <th class="text-center" style="width:140px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recetas as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['nombre']) ?></td>
                        <td class="small"><?= e($r['producto_nombre'] ?? '—') ?></td>
                        <td class="small"><?= number_format($r['rendimiento'],3,',','.') ?> <?= e($r['unidad_rendimiento'] ?? '') ?></td>
                        <td class="text-center"><span class="badge bg-secondary-soft text-secondary"><?= $r['cant_ingredientes'] ?></span></td>
                        <td><span class="badge <?= $r['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= $r['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="text-center">
                            <a href="<?= url('recetas/produccion?id='.$r['id']) ?>" class="btn btn-sm btn-success py-0" title="Producir">
                                ▶ Producir
                            </a>
                            <a href="<?= url('recetas/versiones?id='.$r['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Historial de versiones">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </a>
                            <a href="<?= url('recetas/editar?id='.$r['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminar(<?= $r['id'] ?>, '<?= e($r['nombre']) ?>')" title="Eliminar">
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
$(document).ready(()=>$('#tblRecetas').DataTable());
async function eliminar(id,nombre){
    if(!confirm(`¿Eliminar receta "${nombre}"?`))return;
    const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
    const r=await fetch(`${BASE_URL}/recetas/eliminar`,{method:'POST',body:fd});
    const d=await r.json();
    d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
}
</script>
