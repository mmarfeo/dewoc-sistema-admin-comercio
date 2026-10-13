<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Inventario Físico</h1>
        <p class="text-muted small mb-0">Ingrese las cantidades reales contadas. Solo se ajustan los ítems con diferencia.</p>
    </div>
    <a href="<?= url($volverUrl) ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<?php if ($solicitud): ?>
<div class="alert alert-info-soft border-0 small mb-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        📋 Estás contando el pedido de <strong><?= e($solicitud['creador_nombre']) ?></strong>:
        <?php foreach ($solicitud['criterios'] as $c): ?>
        <span class="badge bg-info-soft text-info me-1"><?= e($c['tipo_label']) ?>: <?= e($c['nombre']) ?></span>
        <?php endforeach; ?>
        <?php if ($solicitud['descripcion']): ?><div class="small text-muted mt-1"><?= e($solicitud['descripcion']) ?></div><?php endif; ?>
    </div>
    <a href="<?= url('stock/inventario') ?>" class="btn btn-sm btn-outline-secondary">Ver todos los ítems (sin filtro)</a>
</div>
<?php endif; ?>

<form id="formInventario" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="items_json" id="itemsJson" value="[]">
    <?php if ($solicitud): ?><input type="hidden" name="solicitud_id" value="<?= (int)$solicitud['id'] ?>"><?php endif; ?>

    <?php if (!$verTeorico): ?>
    <div class="alert alert-secondary small border-0 mb-3">
        🔒 Este conteo es a ciegas: no se muestra el stock que espera el sistema, para que
        cargues lo que contás físicamente y no lo que ya sabías que iba a decir.
    </div>
    <?php endif; ?>

    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tblInventario" class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th><th>Tipo</th><th>Unidad</th>
                            <th title="Última vez que se movió el stock: compra, venta/consumo o ajuste">Últ. actualización</th>
                            <?php if ($verTeorico): ?>
                            <th class="text-end">Stock sistema</th>
                            <?php endif; ?>
                            <th class="text-end" style="width:130px">Stock físico</th>
                            <?php if ($verTeorico): ?>
                            <th class="text-end">Diferencia</th>
                            <?php endif; ?>
                            <?php if (canEdit('lista_compras')): ?>
                            <th class="text-center" style="width:50px"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                        <tr data-idx="<?= $idx ?>" <?= $verTeorico ? 'data-teorico="'.$it['stock_actual'].'"' : '' ?>
                            data-tipo="<?= e($it['tipo']) ?>" data-id="<?= $it['id'] ?>">
                            <td class="fw-semibold small"><?= e($it['nombre']) ?></td>
                            <td><span class="badge bg-secondary-soft text-secondary" style="font-size:10px"><?= e(str_replace('_',' ',$it['tipo'])) ?></span></td>
                            <td class="small text-muted"><?= e($it['unidad'] ?? '') ?></td>
                            <td class="small text-muted"><?= !empty($it['ultima_actualizacion']) ? fechaHora($it['ultima_actualizacion']) : '—' ?></td>
                            <?php if ($verTeorico): ?>
                            <td class="text-end"><?= number_format($it['stock_actual'],3,',','.') ?></td>
                            <?php endif; ?>
                            <td>
                                <input type="number" class="form-control form-control-sm fisico-input"
                                    min="0" step="0.001"
                                    data-idx="<?= $idx ?>">
                            </td>
                            <?php if ($verTeorico): ?>
                            <td class="text-end fw-semibold dif-col small">—</td>
                            <?php endif; ?>
                            <?php if (canEdit('lista_compras')): ?>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-success py-0"
                                    onclick="agregarAListaCompras('<?= e($it['tipo']) ?>', <?= $it['id'] ?>, '<?= addslashes(e($it['nombre'])) ?>')"
                                    title="Agregar a Lista de Compras">🛒</button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="invError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardarInv">Guardar inventario</button>
        <a href="<?= url($volverUrl) ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

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

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const VOLVER_URL = '<?= url($volverUrl) ?>';
let inventario   = {};

$(document).ready(()=>$('#tblInventario').DataTable({pageLength:50,searching:true}));

document.querySelectorAll('.fisico-input').forEach(inp=>{
    inp.addEventListener('input', function(){
        const tr=this.closest('tr');
        const fisico=parseFloat(this.value);
        const idx=tr.dataset.idx;
        // El teórico (si esta cuenta puede verlo) es solo para la vista previa de "Diferencia"
        // en pantalla — el servidor recalcula el teórico real desde la base al guardar, nunca
        // confía en lo que se mande acá.
        const difCol=tr.querySelector('.dif-col');
        if(difCol){
            if(tr.dataset.teorico===undefined||isNaN(fisico)){
                difCol.textContent='—';difCol.className='text-end fw-semibold dif-col small';
            }else{
                const dif=fisico-(parseFloat(tr.dataset.teorico)||0);
                difCol.textContent=(dif>=0?'+':'')+dif.toLocaleString('es-AR',{maximumFractionDigits:3});
                difCol.className='text-end fw-semibold dif-col small '+(dif>0?'text-success':dif<0?'text-danger':'text-muted');
            }
        }
        if(isNaN(fisico)){delete inventario[idx];}
        else{
            inventario[idx]={
                tipo_item:tr.dataset.tipo,
                item_id:parseInt(tr.dataset.id),
                stock_fisico:fisico,
            };
        }
        sincronizar();
    });
});

function sincronizar(){
    document.getElementById('itemsJson').value=JSON.stringify(Object.values(inventario));
}

document.getElementById('formInventario').addEventListener('submit', async function(e){
    e.preventDefault();
    const errEl=document.getElementById('invError'); errEl.classList.add('d-none');
    const items=Object.values(inventario);
    if(!items.length){errEl.textContent='No ha ingresado ningún conteo físico.';errEl.classList.remove('d-none');return;}
    document.getElementById('btnGuardarInv').disabled=true;
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/stock/inventario/guardar`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){showToast(d.mensaje);setTimeout(()=>{window.location.href=VOLVER_URL;},1000);}
        else{errEl.textContent=d.error;errEl.classList.remove('d-none');}
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');}
    finally{document.getElementById('btnGuardarInv').disabled=false;}
});

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
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
