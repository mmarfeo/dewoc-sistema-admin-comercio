<?php
// Vista reutilizable para Materias Primas — misma estructura que Insumos
$esMP    = true;
$titulo  = 'Materias Primas';
$urlBase = 'materias-primas';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $titulo ?></h1>
    <button class="btn btn-primary" onclick="abrirNuevo()" data-bs-toggle="modal" data-bs-target="#modalItem">
        + Nueva materia prima
    </button>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblItems" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Unidad</th>
                        <th>Stock actual</th>
                        <th>Stock mín.</th>
                        <th>Costo unit.</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="<?= $item['stock_actual'] <= $item['stock_minimo'] ? 'table-warning' : '' ?>">
                        <td class="small text-muted"><?= e($item['codigo'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= e($item['nombre']) ?></td>
                        <td class="small"><?= e($item['categoria_nombre'] ?? '—') ?></td>
                        <td class="small"><?= e($item['unidad'] ?? '—') ?></td>
                        <td>
                            <?php $cls = $item['stock_actual'] <= $item['stock_minimo'] ? 'text-danger fw-bold' : 'text-success fw-semibold'; ?>
                            <span class="<?= $cls ?>"><?= number_format($item['stock_actual'], 3, ',', '.') ?></span>
                        </td>
                        <td class="small text-muted"><?= number_format($item['stock_minimo'], 3, ',', '.') ?></td>
                        <td><?= moneda($item['costo_unitario']) ?></td>
                        <td class="small"><?= e($item['proveedor_nombre'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $item['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                                <?= $item['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0"
                                onclick="abrirEditar(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)"
                                title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminar(<?= $item['id'] ?>, '<?= e($item['nombre']) ?>')" title="Eliminar">
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

<!-- Modal ABM -->
<div class="modal fade" id="modalItem" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nueva materia prima</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formItem" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="itemId" name="id" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="fNombre" name="nombre" class="form-control" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Código</label>
                            <input type="text" id="fCodigo" name="codigo" class="form-control" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Categoría</label>
                            <select id="fCategoria" name="categoria_id" class="form-select">
                                <option value="">— Sin categoría —</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unidad de medida</label>
                            <select id="fUnidad" name="unidad_medida_id" class="form-select">
                                <option value="">— —</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock mínimo</label>
                            <input type="number" id="fStockMin" name="stock_minimo" class="form-control" min="0" step="0.001" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Costo unitario ($)</label>
                            <input type="number" id="fCosto" name="costo_unitario" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Proveedor habitual</label>
                            <select id="fProveedor" name="proveedor_id" class="form-select">
                                <option value="">— —</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>"><?= e($prov['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="rowActivo" class="col-12 d-none">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fActivo" name="activo" value="1" checked>
                                <label class="form-check-label" for="fActivo">Activo</label>
                            </div>
                        </div>
                    </div>
                    <div id="modalError" class="alert alert-danger mt-3 d-none py-2 small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const URL_CREAR  = `${BASE_URL}/materias-primas/crear`;
const URL_EDIT   = `${BASE_URL}/materias-primas/actualizar`;
const URL_DEL    = `${BASE_URL}/materias-primas/eliminar`;
let   editMode   = false;

$(document).ready(() => { $('#tblItems').DataTable(); });

function abrirNuevo() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Nueva materia prima';
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('rowActivo').classList.add('d-none');
    limpiarError();
}

function abrirEditar(item) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Editar: ' + item.nombre;
    document.getElementById('itemId').value     = item.id;
    document.getElementById('fNombre').value    = item.nombre;
    document.getElementById('fCodigo').value    = item.codigo || '';
    document.getElementById('fCategoria').value = item.categoria_id || '';
    document.getElementById('fUnidad').value    = item.unidad_medida_id || '';
    document.getElementById('fStockMin').value  = item.stock_minimo;
    document.getElementById('fCosto').value     = item.costo_unitario;
    document.getElementById('fProveedor').value = item.proveedor_id || '';
    document.getElementById('fActivo').checked  = item.activo == 1;
    document.getElementById('rowActivo').classList.remove('d-none');
    limpiarError();
    new bootstrap.Modal(document.getElementById('modalItem')).show();
}

document.getElementById('formItem').addEventListener('submit', async function(e) {
    e.preventDefault();
    limpiarError();
    document.getElementById('btnGuardar').disabled = true;
    const fd = new FormData(this);
    if (!fd.has('activo')) fd.set('activo', '0');
    try {
        const r = await fetch(editMode ? URL_EDIT : URL_CREAR, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalItem'))?.hide();
            showToast(d.mensaje);
            setTimeout(() => location.reload(), 800);
        } else { mostrarError(d.error); }
    } catch { mostrarError('Error de conexión.'); }
    finally { document.getElementById('btnGuardar').disabled = false; }
});

async function eliminar(id, nombre) {
    if (!confirm(`¿Eliminar "${nombre}"?`)) return;
    const fd = new FormData();
    fd.append('id', id); fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(URL_DEL, { method: 'POST', body: fd });
    const d = await r.json();
    d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
}

function mostrarError(msg) { const e = document.getElementById('modalError'); e.textContent = msg; e.classList.remove('d-none'); }
function limpiarError() { document.getElementById('modalError').classList.add('d-none'); }
</script>
