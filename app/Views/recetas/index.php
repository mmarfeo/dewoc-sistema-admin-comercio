<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Recetas de Producción</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <a href="<?= url('recetas/exportar') ?>" class="btn btn-outline-secondary btn-sm" title="Exportar el detalle completo (una fila por ingrediente)">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Exportar detalle
        </a>
        <a href="<?= url('recetas/nueva') ?>" class="btn btn-primary">+ Nueva receta</a>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblRecetas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Producto final</th><th>Rendimiento</th>
                        <th class="text-center">Ingredientes</th><th class="text-end">Costo total</th><th>Estado</th>
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
                        <td class="text-end fw-semibold" data-order="<?= $r['costo_total'] ?>"><?= moneda($r['costo_total']) ?></td>
                        <td><span class="badge <?= $r['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= $r['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="text-center">
                            <a href="<?= url('recetas/produccion?id='.$r['id']) ?>" class="btn btn-sm btn-success py-0" title="Producir">
                                ▶ Producir
                            </a>
                            <a href="<?= url('recetas/versiones?id='.$r['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Historial de versiones">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </a>
                            <a href="<?= url('recetas/historial-costo?id='.$r['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Historial de costo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </a>
                            <a href="<?= url('recetas/editar?id='.$r['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary py-0 ms-1" onclick="duplicar(<?= $r['id'] ?>, '<?= e($r['nombre']) ?>')" title="Duplicar (copia todos los ingredientes bajo un nombre nuevo)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                            <a href="<?= url('recetas/imprimir?id='.$r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Imprimir receta para cocina (sin costos)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
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

<!-- Modal duplicar receta -->
<div class="modal fade" id="modalDuplicarReceta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1 text-secondary"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Duplicar receta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formDuplicarReceta">
                <div class="modal-body">
                    <p class="small text-muted">
                        Se copian todos los ingredientes y cantidades de "<strong id="duplicarOrigenNombre"></strong>"
                        bajo el nombre nuevo. El producto final no se copia — se elige aparte al editar la copia.
                    </p>
                    <label class="form-label fw-semibold">Nombre de la nueva receta</label>
                    <input type="text" id="duplicarNombreInput" class="form-control" required maxlength="150" autocomplete="off">
                    <div id="duplicarError" class="alert alert-danger py-2 small d-none mt-3"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnConfirmarDuplicar">Duplicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblRecetas').DataTable());
function eliminar(id,nombre){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/recetas/eliminar`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };
    const msg = `¿Eliminar receta "${nombre}"?`;
    if (typeof showConfirm === 'function') { showConfirm(msg, ejecutar, { label: 'Eliminar' }); }
    else { if (confirm(msg)) ejecutar(); }
}

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible, se abre/cierra
// el modal por atributos data-bs-* en vez de la API de JS (Bootstrap registra esa delegación
// de eventos igual al importarse).
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    el.querySelector('[data-bs-dismiss="modal"]')?.click();
}

let _duplicarOrigenId = null;

function duplicar(id, nombre) {
    _duplicarOrigenId = id;
    document.getElementById('duplicarOrigenNombre').textContent = nombre;
    document.getElementById('duplicarNombreInput').value = `${nombre} (copia)`;
    document.getElementById('duplicarError').classList.add('d-none');
    _showModal('modalDuplicarReceta');
    setTimeout(() => document.getElementById('duplicarNombreInput').select(), 200);
}

document.getElementById('formDuplicarReceta').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('duplicarError');
    errEl.classList.add('d-none');
    const nuevoNombre = document.getElementById('duplicarNombreInput').value.trim();
    if (!nuevoNombre) return;

    const btn = document.getElementById('btnConfirmarDuplicar');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('id', _duplicarOrigenId);
    fd.append('nombre', nuevoNombre);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/recetas/duplicar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _hideModal('modalDuplicarReceta');
            showToast(d.mensaje);
            setTimeout(() => window.location.href = `${BASE_URL}/recetas/editar?id=${d.id}`, 600);
        } else {
            errEl.textContent = d.error || 'No se pudo duplicar.';
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});
</script>
<?php
$importUrl        = url('recetas/importar');
$importPreviewUrl = url('recetas/previsualizar');
$importPlantilla  = url('recetas/plantilla');
$importModulo     = 'Recetas';
$importColumnas   = ['nombre_receta *', 'descripcion', 'instrucciones', 'producto_resultante', 'tiempo_preparacion (min)', 'porciones', 'activo (1/0)', 'insumo_nombre *', 'cantidad *', 'merma_pct', 'unidad_medida'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
