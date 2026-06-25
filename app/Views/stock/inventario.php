<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Inventario Físico</h1>
        <p class="text-muted small mb-0">Ingrese las cantidades reales contadas. Solo se ajustan los ítems con diferencia.</p>
    </div>
    <a href="<?= url('stock') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formInventario" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="items_json" id="itemsJson" value="[]">

    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tblInventario" class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th><th>Tipo</th><th>Unidad</th>
                            <th class="text-end">Stock sistema</th>
                            <th class="text-end" style="width:130px">Stock físico</th>
                            <th class="text-end">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                        <tr data-idx="<?= $idx ?>" data-teorico="<?= $it['stock_actual'] ?>"
                            data-tipo="<?= e($it['tipo']) ?>" data-id="<?= $it['id'] ?>">
                            <td class="fw-semibold small"><?= e($it['nombre']) ?></td>
                            <td><span class="badge bg-secondary-soft text-secondary" style="font-size:10px"><?= e(str_replace('_',' ',$it['tipo'])) ?></span></td>
                            <td class="small text-muted"><?= e($it['unidad'] ?? '') ?></td>
                            <td class="text-end"><?= number_format($it['stock_actual'],3,',','.') ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm fisico-input"
                                    min="0" step="0.001"
                                    placeholder="<?= number_format($it['stock_actual'],3,',','.') ?>"
                                    data-idx="<?= $idx ?>">
                            </td>
                            <td class="text-end fw-semibold dif-col small">—</td>
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
        <a href="<?= url('stock') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
let inventario   = {};

$(document).ready(()=>$('#tblInventario').DataTable({pageLength:50,searching:true}));

document.querySelectorAll('.fisico-input').forEach(inp=>{
    inp.addEventListener('input', function(){
        const tr=this.closest('tr');
        const teorico=parseFloat(tr.dataset.teorico)||0;
        const fisico=parseFloat(this.value);
        const dif=isNaN(fisico)?null:fisico-teorico;
        const difCol=tr.querySelector('.dif-col');
        if(dif===null){difCol.textContent='—';difCol.className='text-end fw-semibold dif-col small';}
        else{
            difCol.textContent=(dif>=0?'+':'')+dif.toFixed(3).replace('.',',');
            difCol.className='text-end fw-semibold dif-col small '+(dif>0?'text-success':dif<0?'text-danger':'text-muted');
        }
        const idx=tr.dataset.idx;
        if(dif===null){delete inventario[idx];}
        else{
            inventario[idx]={
                tipo_item:tr.dataset.tipo,
                item_id:parseInt(tr.dataset.id),
                stock_teorico:teorico,
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
        if(d.ok){showToast(d.mensaje);setTimeout(()=>{window.location.href=`${BASE_URL}/stock`;},1000);}
        else{errEl.textContent=d.error;errEl.classList.remove('d-none');}
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');}
    finally{document.getElementById('btnGuardarInv').disabled=false;}
});
</script>
