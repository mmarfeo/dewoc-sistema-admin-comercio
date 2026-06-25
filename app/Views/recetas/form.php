<?php $esEdicion = !empty($receta); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar receta' : 'Nueva receta' ?></h1>
    <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formReceta" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $receta['id'] ?>"><?php endif; ?>

    <div class="row g-3">
        <!-- Columna izquierda: datos generales -->
        <div class="col-lg-4">
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos de la receta</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150"
                            value="<?= e($receta['nombre'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Producto final</label>
                        <select name="producto_id" class="form-select">
                            <option value="">— Sin producto asociado —</option>
                            <?php foreach ($productos as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($receta['producto_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">El stock del producto sube con cada producción.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Rendimiento <span class="text-danger">*</span></label>
                            <input type="number" name="rendimiento" class="form-control" min="0.001" step="0.001" required
                                value="<?= $receta['rendimiento'] ?? 1 ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unidad</label>
                            <input type="text" name="unidad_rendimiento" class="form-control" maxlength="30"
                                placeholder="unidades / kg / L" value="<?= e($receta['unidad_rendimiento'] ?? 'unidades') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= e($receta['notas'] ?? '') ?></textarea>
                    </div>
                    <?php if ($esEdicion): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="fActivo"
                            <?= $receta['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fActivo">Receta activa</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Costo estimado -->
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Costo estimado por producción</span>
                        <span id="costoBadge" class="fw-bold text-primary">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: ingredientes -->
        <div class="col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <h6 class="fw-semibold mb-0">Ingredientes</h6>
                    <div class="input-group input-group-sm" style="max-width:300px">
                        <input type="text" id="buscadorIng" class="form-control" placeholder="Buscar materia prima o insumo...">
                        <button type="button" class="btn btn-outline-secondary" id="btnBuscarIng">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </div>
                </div>

                <div id="resultadosIng" class="card-body pt-0 pb-0 d-none">
                    <div class="list-group list-group-flush small" id="listaIng"></div>
                </div>

                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="tblIngredientes">
                            <thead class="table-light">
                                <tr>
                                    <th>Ingrediente</th><th>Tipo</th>
                                    <th style="width:100px">Cantidad</th>
                                    <th style="width:90px">Merma %</th>
                                    <th style="width:90px">Stock</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoIng"></tbody>
                            <tfoot>
                                <tr id="rowVacioIng">
                                    <td colspan="6" class="text-center text-muted py-3 small">
                                        Busque y agregue ingredientes arriba
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="items_json" id="itemsJson" value="[]">
    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar receta' : 'Guardar receta' ?>
        </button>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;

let ingredientes = <?= $esEdicion ? json_encode(array_map(fn($it) => [
    'tipo_ingrediente' => $it['tipo_ingrediente'] ?? ($it['materia_prima_id'] ? 'materia_prima' : 'insumo'),
    'item_id'          => $it['materia_prima_id'] ?? $it['insumo_id'],
    'nombre'           => $it['ingrediente_nombre'] ?? '',
    'cantidad'         => $it['cantidad'],
    'merma_pct'        => $it['merma_pct'] ?? 0,
    'stock_actual'     => $it['stock_actual'] ?? 0,
    'costo_unitario'   => $it['costo_unitario'] ?? 0,
    'unidad'           => $it['unidad'] ?? '',
], $receta['detalle'] ?? [])) : '[]' ?>;

// ── Búsqueda de ingredientes ───────────────────────────────────────────────────
let buscTimeout;
document.getElementById('buscadorIng').addEventListener('input', function(){
    clearTimeout(buscTimeout);
    if(this.value.length<2){ocultarIng();return;}
    buscTimeout=setTimeout(buscarIng,300);
});
document.getElementById('btnBuscarIng').addEventListener('click', buscarIng);

async function buscarIng(){
    const q=document.getElementById('buscadorIng').value.trim();
    if(q.length<2)return;
    const r=await fetch(`${BASE_URL}/recetas/buscar-ingredientes?q=${encodeURIComponent(q)}`);
    const data=await r.json();
    mostrarIng(data);
}

function mostrarIng(lista){
    const cont=document.getElementById('listaIng');
    cont.innerHTML='';
    if(!lista.length){cont.innerHTML='<div class="list-group-item text-muted small">Sin resultados.</div>';}
    lista.forEach(it=>{
        const btn=document.createElement('button');
        btn.type='button'; btn.className='list-group-item list-group-item-action py-2';
        btn.innerHTML=`<span class="fw-semibold">${esc(it.nombre)}</span>
            <span class="badge bg-secondary-soft text-secondary float-end">${esc(it.tipo.replace('_',' '))}</span>`;
        btn.addEventListener('click',()=>agregarIng(it));
        cont.appendChild(btn);
    });
    document.getElementById('resultadosIng').classList.remove('d-none');
}
function ocultarIng(){document.getElementById('resultadosIng').classList.add('d-none');}
function esc(v){const d=document.createElement('div');d.textContent=v||'';return d.innerHTML;}

// ── Ingredientes ──────────────────────────────────────────────────────────────
function agregarIng(it){
    ingredientes.push({tipo_ingrediente:it.tipo,item_id:it.id,nombre:it.nombre,cantidad:1,merma_pct:0,stock_actual:parseFloat(it.stock_actual)||0,costo_unitario:parseFloat(it.costo_unitario)||0,unidad:it.unidad||''});
    ocultarIng(); document.getElementById('buscadorIng').value='';
    renderIng();
}

function renderIng(){
    const tbody=document.getElementById('cuerpoIng');
    const rowV=document.getElementById('rowVacioIng');
    tbody.innerHTML='';
    if(!ingredientes.length){rowV.style.display='';actualizarCosto();sincronizarIng();return;}
    rowV.style.display='none';
    ingredientes.forEach((it,idx)=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td class="small fw-semibold">${esc(it.nombre)} <span class="text-muted">${esc(it.unidad)}</span></td>
            <td><span class="badge bg-secondary-soft text-secondary small">${esc(it.tipo_ingrediente.replace('_',' '))}</span></td>
            <td><input type="number" class="form-control form-control-sm" min="0.001" step="0.001" value="${it.cantidad}" onchange="updIng(${idx},'cantidad',this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" min="0" max="100" step="0.1" value="${it.merma_pct}" onchange="updIng(${idx},'merma_pct',this.value)"></td>
            <td class="small ${parseFloat(it.stock_actual)<parseFloat(it.cantidad)?'text-danger fw-bold':'text-success'}">${parseFloat(it.stock_actual).toFixed(3)}</td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="quitarIng(${idx})">✕</button></td>`;
        tbody.appendChild(tr);
    });
    actualizarCosto(); sincronizarIng();
}

function updIng(idx,campo,val){ingredientes[idx][campo]=parseFloat(val);renderIng();}
function quitarIng(idx){ingredientes.splice(idx,1);renderIng();}

function sincronizarIng(){
    document.getElementById('itemsJson').value=JSON.stringify(ingredientes);
}

function actualizarCosto(){
    let costo=0;
    ingredientes.forEach(it=>{costo+=it.cantidad*it.costo_unitario*(1+it.merma_pct/100);});
    document.getElementById('costoBadge').textContent=formatMoneda(costo);
}

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formReceta').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl=document.getElementById('formError'); errEl.classList.add('d-none');
    document.getElementById('btnGuardar').disabled=true;
    const fd=new FormData(this);
    if(!fd.has('activo'))fd.set('activo','0');
    const url=ES_EDICION?`${BASE_URL}/recetas/actualizar`:`${BASE_URL}/recetas/guardar`;
    try{
        const r=await fetch(url,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){showToast(d.mensaje);setTimeout(()=>{window.location.href=`${BASE_URL}/recetas`;},800);}
        else{errEl.textContent=d.error;errEl.classList.remove('d-none');}
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');}
    finally{document.getElementById('btnGuardar').disabled=false;}
});

document.addEventListener('click',function(e){
    if(!e.target.closest('#resultadosIng')&&!e.target.closest('#buscadorIng')&&!e.target.closest('#btnBuscarIng'))ocultarIng();
});

// Inicializar si hay ingredientes (modo edición)
renderIng();
</script>
