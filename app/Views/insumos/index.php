<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Insumos</h1>
        <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <button class="btn btn-primary" onclick="abrirNuevo()" data-bs-toggle="modal" data-bs-target="#modalItem">+ Nuevo insumo</button>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblItems" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th><th>Nombre</th><th>Categoría</th><th>Tipo gasto</th><th>Unidad</th>
                        <th>Stock actual</th><th>Stock mín.</th><th>Costo unit.</th><th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="<?= $item['stock_actual'] <= $item['stock_minimo'] ? 'table-warning' : '' ?>">
                        <td class="small text-muted"><?= e($item['codigo'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= e($item['nombre']) ?></td>
                        <td class="small"><?= e($item['categoria_nombre'] ?? '—') ?></td>
                        <td class="small">
                            <?php
                            $tgLabel = ['costo_variable' => 'Variable', 'costo_fijo' => 'Fijo', 'impuesto' => 'Impuesto', 'gasto' => 'Gasto'];
                            $tgColor = ['costo_variable' => 'warning',  'costo_fijo' => 'info',  'impuesto' => 'danger', 'gasto' => 'secondary'];
                            $tg      = $item['tipo_gasto'] ?? 'costo_variable';
                            ?>
                            <span class="badge bg-<?= $tgColor[$tg] ?? 'secondary' ?>-soft text-<?= $tgColor[$tg] ?? 'secondary' ?>">
                                <?= $tgLabel[$tg] ?? e($tg) ?>
                            </span>
                        </td>
                        <td class="small"><?= e($item['unidad'] ?? '—') ?></td>
                        <td>
                            <?php $cls = $item['stock_actual'] <= $item['stock_minimo'] ? 'text-danger fw-bold' : 'text-success fw-semibold'; ?>
                            <span class="<?= $cls ?>"><?= number_format($item['stock_actual'], 3, ',', '.') ?></span>
                        </td>
                        <td class="small text-muted"><?= number_format($item['stock_minimo'], 3, ',', '.') ?></td>
                        <td><?= moneda($item['costo_unitario']) ?></td>
                        <td><span class="badge <?= $item['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= $item['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0" onclick="abrirEditar(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminar(<?= $item['id'] ?>, '<?= e($item['nombre']) ?>')" title="Eliminar">
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

<!-- Modal ABM Insumo -->
<div class="modal fade" id="modalItem" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo insumo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formItem" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="itemId" name="id" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="fNombre" name="nombre" class="form-control" required maxlength="150"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Código</label>
                            <input type="text" id="fCodigo" name="codigo" class="form-control" maxlength="30"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Categoría</label>
                            <select id="fCategoria" name="categoria_id" class="form-select">
                                <option value="">— Sin categoría —</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Tipo de gasto</label>
                            <select id="fTipoGasto" name="tipo_gasto" class="form-select">
                                <option value="costo_variable">Gasto Variable</option>
                                <option value="costo_fijo">Gasto Fijo</option>
                                <option value="impuesto">Impuesto</option>
                                <option value="gasto">Gasto (no es inventario real)</option>
                            </select>
                            <div class="form-text">Clasificación en el Estado de Resultados.</div>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Unidad de medida</label>
                            <select id="fUnidad" name="unidad_medida_id" class="form-select">
                                <option value="">— —</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)</option>
                                <?php endforeach; ?>
                            </select></div>
                        <!-- Ingrediente genérico: agrupa variantes de marca/forma bajo un mismo costo
                             (ej. "Pote cuadrado" y "Pote redondo" son ambos "Pote 1/4") — mismo
                             mecanismo que ya usa /materias-primas, un <select> directo acá en vez del
                             buscador de /ingredientes-genericos. -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Ingrediente genérico
                                <a href="<?= url('ingredientes-genericos') ?>" target="_blank" class="small ms-1">(gestionar genéricos)</a>
                            </label>
                            <select id="fGenerico" name="ingrediente_generico_id" class="form-select">
                                <option value="">— Ninguno (insumo independiente) —</option>
                                <?php foreach ($genericos as $g): ?>
                                <option value="<?= $g['id'] ?>" data-unidad="<?= $g['unidad_medida_id'] ?>">
                                    <?= e($g['nombre']) ?> (<?= e($g['unidad'] ?? '—') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Si este insumo es una variante de marca/forma de algo que ya comprás con
                                otro nombre (ej. "Pote cuadrado" y "Pote redondo" son ambos "Pote 1/4"),
                                asignalo acá para que las recetas que usen el genérico siempre reflejen
                                el costo actualizado, compres la variante que compres. La unidad de
                                medida del insumo debe coincidir con la del genérico.
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Stock mínimo</label>
                            <input type="number" id="fStockMin" name="stock_minimo" class="form-control" min="0" step="0.001" value="0"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Costo unitario ($)</label>
                            <input type="number" id="fCosto" name="costo_unitario" class="form-control" min="0" step="0.01" value="0"></div>
                        <div id="rowActivo" class="col-12 d-none">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fActivo" name="activo" value="1" checked>
                                <label class="form-check-label" for="fActivo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fControlaStock" name="controla_stock" value="1" checked>
                                <label class="form-check-label" for="fControlaStock">Controla stock</label>
                            </div>
                            <div class="form-text">Desactivalo para ítems que no necesitás contar (no aparece en Control de Stock ni se marca bajo mínimo).</div>
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
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
const URL_CREAR = `${BASE_URL}/insumos/crear`, URL_EDIT = `${BASE_URL}/insumos/actualizar`, URL_DEL = `${BASE_URL}/insumos/eliminar`;
let editMode = false;
$(document).ready(() => { $('#tblItems').DataTable(); });
// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos/cerramos el modal por atributos data-bs-* en vez de la API de JS
// (Bootstrap sí registra esa delegación de eventos al importarse).
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
    document.querySelector('#' + id + ' [data-bs-dismiss="modal"]')?.click();
}
function abrirNuevo() { editMode=false; document.getElementById('formItem').reset(); document.getElementById('itemId').value=''; document.getElementById('rowActivo').classList.add('d-none'); document.getElementById('modalTitle').textContent='Nuevo insumo'; limpiarError(); }
function abrirEditar(item) {
    editMode = true;
    document.getElementById('modalTitle').textContent  = 'Editar: ' + item.nombre;
    document.getElementById('itemId').value            = item.id;
    document.getElementById('fNombre').value           = item.nombre;
    document.getElementById('fCodigo').value           = item.codigo || '';
    document.getElementById('fCategoria').value        = item.categoria_id || '';
    document.getElementById('fTipoGasto').value        = item.tipo_gasto || 'costo_variable';
    document.getElementById('fUnidad').value           = item.unidad_medida_id || '';
    document.getElementById('fGenerico').value         = item.ingrediente_generico_id || '';
    document.getElementById('fStockMin').value         = item.stock_minimo;
    document.getElementById('fCosto').value            = item.costo_unitario;
    document.getElementById('fActivo').checked         = item.activo == 1;
    document.getElementById('fControlaStock').checked  = (item.controla_stock ?? 1) == 1;
    document.getElementById('rowActivo').classList.remove('d-none');
    limpiarError();
    _showModal('modalItem');
}
document.getElementById('formItem').addEventListener('submit', async function(e) {
    e.preventDefault(); limpiarError();

    // La unidad del insumo debe coincidir con la del genérico elegido (si eligió uno) —
    // mismo chequeo que ya hace /materias-primas antes de mandar el form.
    const selGenerico = document.getElementById('fGenerico');
    const unidadGenerico = selGenerico.selectedOptions[0]?.dataset.unidad || '';
    if (selGenerico.value && unidadGenerico && unidadGenerico !== document.getElementById('fUnidad').value) {
        mostrarError('La unidad de medida de este insumo no coincide con la del ingrediente genérico elegido.');
        return;
    }

    document.getElementById('btnGuardar').disabled=true; const fd=new FormData(this); if(!fd.has('activo'))fd.set('activo','0'); if(!fd.has('controla_stock'))fd.set('controla_stock','0'); try { const r=await fetch(editMode?URL_EDIT:URL_CREAR,{method:'POST',body:fd}); const d=await r.json(); d.ok?(_hideModal('modalItem'),showToast(d.mensaje),setTimeout(()=>location.reload(),800)):mostrarError(d.error); } catch { mostrarError('Error de conexión.'); } finally { document.getElementById('btnGuardar').disabled=false; } });
function eliminar(id,nombre) {
    const ejecutar = async () => { const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN); const r=await fetch(URL_DEL,{method:'POST',body:fd}); const d=await r.json(); d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger'); };
    const msg = `¿Eliminar "${nombre}"?`;
    if (typeof showConfirm === 'function') { showConfirm(msg, ejecutar, { label: 'Eliminar' }); }
    else { if (confirm(msg)) ejecutar(); }
}
function mostrarError(msg){const e=document.getElementById('modalError');e.textContent=msg;e.classList.remove('d-none');}
function limpiarError(){document.getElementById('modalError').classList.add('d-none');}
</script>
<?php
$importUrl       = url('insumos/importar');
$importPlantilla = url('insumos/plantilla');
$importModulo    = 'Insumos';
$importColumnas  = ['nombre *', 'codigo', 'unidad', 'categoria', 'stock_actual', 'stock_minimo', 'costo_unitario'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
