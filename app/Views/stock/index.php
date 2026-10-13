<?php
$bajosCount = count($bajoMinimo);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Control de Stock</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar ajuste masivo
        </button>
        <a href="<?= url('stock/inventario') ?>" class="btn btn-outline-primary btn-sm">📋 Inventario físico</a>
        <a href="<?= url('stock/reporte') ?>" class="btn btn-outline-secondary btn-sm">📊 Reporte comparativo</a>
    </div>
</div>

<?php if ($bajosCount): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <strong><?= $bajosCount ?> ítem<?= $bajosCount>1?'s':'' ?> con stock bajo el mínimo.</strong>
    <a href="#alertas" class="ms-auto small">Ver →</a>
</div>
<?php endif; ?>

<!-- Modal ajuste de stock -->
<div class="modal fade" id="modalAjuste" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Ajuste de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAjuste">
                <?= Csrf::field() ?>
                <input type="hidden" id="ajTipo" name="tipo_item">
                <input type="hidden" id="ajId"   name="item_id">
                <div class="modal-body">
                    <p id="ajNombre" class="fw-bold mb-3"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Stock actual</label>
                            <input type="number" id="ajStockActual" class="form-control" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ajuste (+/-)</label>
                            <input type="text" inputmode="decimal" pattern="-?[0-9]*[.,]?[0-9]*"
                                id="ajCantidad" name="cantidad" class="form-control" placeholder="Ej: -2 o +5">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Motivo</label>
                            <input type="text" name="motivo" class="form-control" maxlength="255" placeholder="Merma, rotura, diferencia de inventario...">
                        </div>
                    </div>
                    <div id="ajError" class="alert alert-danger mt-3 d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnAjustar">Aplicar ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal agregar a Lista de Compras -->
<div class="modal fade" id="modalAgregarListaCompras" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Agregar a Lista de Compras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">¿Dónde querés agregar <strong id="alcNombre">—</strong>?</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" id="btnAlcOrden">🧾 Orden de Compra <span class="fw-normal">— se va a comprar sí o sí</span></button>
                    <button type="button" class="btn btn-outline-primary" id="btnAlcRecomendacion">🔎 Recomendaciones <span class="fw-normal">— a confirmar después</span></button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblStock" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Tipo</th><th>Categoría</th><th>Código</th>
                        <th class="text-end">Stock actual</th>
                        <th class="text-end">Stock mín.</th>
                        <?php if ($verCostos): ?><th class="text-end">Costo unit.</th><?php endif; ?>
                        <th>Unidad</th>
                        <th title="Última vez que se movió el stock: compra, venta/consumo o ajuste">Últ. actualización</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <?php $bajo = $it['stock_actual'] <= $it['stock_minimo']; ?>
                    <tr class="<?= $bajo ? 'table-warning' : '' ?>" style="cursor:pointer" title="Doble click para ajustar stock"
                        ondblclick="abrirAjuste('<?= e($it['tipo']) ?>',<?= $it['id'] ?>,'<?= e($it['nombre']) ?>',<?= $it['stock_actual'] ?>)">
                        <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                        <td><span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ',$it['tipo'])) ?></span></td>
                        <td class="small"><?= e($it['categoria'] ?? '—') ?></td>
                        <td class="small text-muted"><?= e($it['codigo'] ?? '—') ?></td>
                        <td class="text-end <?= $bajo ? 'text-danger fw-bold' : 'text-success fw-semibold' ?>">
                            <?= number_format($it['stock_actual'],3,',','.') ?>
                        </td>
                        <td class="text-end small text-muted"><?= number_format($it['stock_minimo'],3,',','.') ?></td>
                        <?php if ($verCostos): ?><td class="text-end small"><?= moneda($it['costo']) ?></td><?php endif; ?>
                        <td class="small text-muted"><?= e($it['unidad'] ?? '') ?></td>
                        <td class="small text-muted"><?= !empty($it['ultima_actualizacion']) ? fechaHora($it['ultima_actualizacion']) : '—' ?></td>
                        <td>
                            <?php if ($bajo): ?>
                            <span class="badge bg-danger-soft text-danger">Bajo mínimo</span>
                            <?php else: ?>
                            <span class="badge bg-success-soft text-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning py-0"
                                onclick="abrirAjuste('<?= e($it['tipo']) ?>',<?= $it['id'] ?>,'<?= e($it['nombre']) ?>',<?= $it['stock_actual'] ?>)"
                                title="Ajustar stock">±</button>
                            <?php if (canEdit('lista_compras')): ?>
                            <button class="btn btn-sm btn-outline-success py-0 ms-1"
                                onclick="agregarAListaCompras('<?= e($it['tipo']) ?>',<?= $it['id'] ?>,'<?= addslashes(e($it['nombre'])) ?>')"
                                title="Agregar a Lista de Compras">🛒</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($bajosCount): ?>
<h5 class="fw-bold mt-4 mb-3" id="alertas">⚠ Ítems bajo el mínimo</h5>
<div class="row g-2">
    <?php foreach ($bajoMinimo as $b): ?>
    <div class="col-md-4">
        <div class="card border-danger border-opacity-25">
            <div class="card-body py-2">
                <div class="fw-semibold small"><?= e($b['nombre']) ?></div>
                <div class="d-flex justify-content-between small">
                    <span class="text-danger">Stock: <?= number_format($b['stock_actual'],3,',','.') ?></span>
                    <span class="text-muted">Mín: <?= number_format($b['stock_minimo'],3,',','.') ?></span>
                </div>
                <span class="badge bg-secondary-soft text-secondary" style="font-size:10px"><?= e(str_replace('_',' ',$b['tipo'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblStock').DataTable());

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos/cerramos el modal por atributos data-bs-* en vez de la API de JS
// (Bootstrap sí registra esa delegación de eventos al importarse).
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(el).show();
    } else {
        let btn = document.getElementById('_trigger_' + id);
        if (!btn) {
            btn = document.createElement('button');
            btn.id = '_trigger_' + id;
            btn.type = 'button';
            btn.hidden = true;
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#' + id);
            document.body.appendChild(btn);
        }
        btn.click();
    }
}
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getInstance(el)?.hide();
    } else {
        document.querySelector('#' + id + ' [data-bs-dismiss="modal"]')?.click();
    }
}

function abrirAjuste(tipo, id, nombre, stock){
    document.getElementById('ajTipo').value=tipo;
    document.getElementById('ajId').value=id;
    document.getElementById('ajNombre').textContent=nombre;
    document.getElementById('ajStockActual').value=stock;
    document.getElementById('ajCantidad').value='';
    document.getElementById('ajError').classList.add('d-none');
    _showModal('modalAjuste');
}

document.getElementById('formAjuste').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('ajError').classList.add('d-none');
    // El campo ahora es de texto (para que el teclado numérico del celular muestre la
    // tecla "-"), así que normalizamos coma decimal a punto antes de mandarlo.
    const ajCant = document.getElementById('ajCantidad');
    ajCant.value = ajCant.value.replace(',', '.');
    document.getElementById('btnAjustar').disabled=true;
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/stock/ajuste`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){
            _hideModal('modalAjuste');
            showToast(d.mensaje);
            setTimeout(()=>location.reload(),800);
        } else { const e2=document.getElementById('ajError'); e2.textContent=d.error; e2.classList.remove('d-none'); }
    }catch{ document.getElementById('ajError').textContent='Error de conexión.'; document.getElementById('ajError').classList.remove('d-none'); }
    finally{ document.getElementById('btnAjustar').disabled=false; }
});

// ── Agregar a Lista de Compras (Orden de Compra o Recomendaciones, a elección) ──
let _alcTipo = null, _alcId = null;
function agregarAListaCompras(tipo, id, nombre) {
    _alcTipo = tipo; _alcId = id;
    document.getElementById('alcNombre').textContent = nombre;
    _showModal('modalAgregarListaCompras');
}
async function _alcEnviar(estado) {
    const fd = new FormData();
    fd.append('tipo_item', _alcTipo);
    fd.append('item_id', _alcId);
    fd.append('estado', estado);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/lista-compras/agregar`, { method: 'POST', body: fd });
        const d = await r.json();
        showToast(d.ok ? d.mensaje : d.error, d.ok ? 'success' : 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}
document.getElementById('btnAlcOrden')?.addEventListener('click', () => _alcEnviar('pendiente'));
document.getElementById('btnAlcRecomendacion')?.addEventListener('click', () => _alcEnviar('sugerido'));
</script>
<?php
$importUrl       = url('stock/importar');
$importPlantilla = url('stock/plantilla');
$importModulo    = 'Ajuste masivo de Stock';
$importColumnas  = ['tipo_item (producto/materia_prima/insumo)', 'nombre_o_codigo *', 'cantidad * (negativa=salida)', 'motivo'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
