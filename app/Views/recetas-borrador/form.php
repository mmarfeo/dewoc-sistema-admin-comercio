<?php $esEdicion = !empty($receta); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar borrador' : 'Nuevo borrador de receta' ?></h1>
    <a href="<?= url('recetas-borrador') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formBorrador" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $receta['id'] ?>"><?php endif; ?>

    <div class="row g-3">
        <!-- Columna izquierda: datos generales -->
        <div class="col-lg-3">
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos del borrador</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150"
                            value="<?= e($receta['nombre'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"><?= e($receta['descripcion'] ?? '') ?></textarea>
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
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Tiempo de preparación</label>
                            <div class="input-group">
                                <input type="number" name="tiempo_preparacion" class="form-control" min="0" step="1"
                                    value="<?= $receta['tiempo_preparacion'] ?? '' ?>" placeholder="0">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Porciones</label>
                            <input type="number" name="porciones" class="form-control" min="0" step="1"
                                value="<?= $receta['porciones'] ?? '' ?>" placeholder="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Instrucciones</label>
                        <textarea name="instrucciones" class="form-control" rows="3"><?= e($receta['instrucciones'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notas internas</label>
                        <textarea name="notas" class="form-control" rows="2"><?= e($receta['notas'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Costo estimado -->
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Costo de ingredientes</span>
                        <span id="costoMpBadge" class="fw-semibold">$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Desvío (pérdida no explicada)</span>
                        <span id="costoDesvioBadge" class="fw-semibold text-danger">$ 0,00</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Costo estimado por producción</span>
                        <span id="costoBadge" class="fw-bold text-primary">$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted">Costo individual (por <span id="costoIndividualUnidad">unidad</span>)</span>
                        <span id="costoIndividualBadge" class="fw-bold text-success">$ 0,00</span>
                    </div>
                </div>
            </div>

            <!-- Análisis de margen -->
            <div class="card border-0 bg-light mt-3">
                <div class="card-header border-0 bg-transparent pb-0"><h6 class="fw-semibold mb-0 small">Análisis de venta</h6></div>
                <div class="card-body">
                    <label class="form-label small fw-semibold">Precio de venta estimado</label>
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text">$</span>
                        <input type="number" name="precio_venta_estimado" id="precioVentaEstimado" class="form-control"
                            min="0" step="0.01" value="<?= $receta['precio_venta_estimado'] ?? '' ?>" placeholder="Precio por unidad">
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Margen</span>
                        <span id="margenPctBadge" class="fw-bold">—</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Ganancia por unidad</span>
                        <span id="margenMontoBadge" class="fw-semibold">—</span>
                    </div>
                </div>
            </div>

            <?php if ($esEdicion): ?>
            <button type="button" class="btn btn-success w-100 mt-3" id="btnPromover" onclick="abrirModalPromover()" disabled>
                Promover a producto real
            </button>
            <div id="promoverAvisoLibres" class="form-text text-warning d-none">
                Reemplazá los ingredientes inventados por ingredientes reales del sistema para poder promover.
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna derecha: ingredientes -->
        <div class="col-lg-9">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="fw-semibold mb-0">Ingredientes</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="abrirModalIngredienteLibreNuevo()" title="Ingrediente que todavía no existe en el sistema: nombre + costo estimado a mano">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Ingrediente inventado
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="btnAgregarMp" title="Buscá acá al lado y elegí un resultado para agregarlo al borrador">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Agregar del sistema
                        </button>
                        <div class="input-group input-group-sm" style="max-width:280px">
                            <input type="text" id="buscadorIng" class="form-control" placeholder="Buscar materia prima, insumo o receta...">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarIng">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </button>
                        </div>
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
                                    <th>Ingrediente</th><th style="width:70px">Tipo</th>
                                    <th style="width:85px">Cantidad</th>
                                    <th style="width:70px">Merma %</th>
                                    <th style="width:95px">Costo</th>
                                    <th style="width:95px">Desvío $</th>
                                    <th style="width:55px">Stock</th>
                                    <th style="width:65px"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoIng"></tbody>
                            <tfoot>
                                <tr id="rowVacioIng">
                                    <td colspan="8" class="text-center text-muted py-3 small">
                                        Agregá ingredientes del sistema o inventados arriba
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #tblIngredientes > tbody > tr > td { padding-left: .5rem; padding-right: .5rem; }
        .resaltar-buscador { box-shadow: 0 0 0 .25rem rgba(25,135,84,.5) !important; transition: box-shadow .2s; }
    </style>

    <div id="inlineDropdownGlobal" class="dropdown-menu shadow-sm" style="position:fixed;z-index:2000;display:none;max-height:220px;overflow-y:auto"></div>

    <input type="hidden" name="items_json" id="itemsJson" value="[]">
    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar borrador' : 'Guardar borrador' ?>
        </button>
        <a href="<?= url('recetas-borrador') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<!-- ===== MODAL INGREDIENTE INVENTADO ===== -->
<div class="modal fade" id="modalIngredienteLibre" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Ingrediente inventado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Para un ingrediente que todavía no existe en el sistema: cargá un nombre y un costo estimado.
                    Antes de promover este borrador a un producto real vas a tener que reemplazarlo por un ingrediente real.
                </p>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="libreNombre" class="form-control" maxlength="120" autocomplete="off">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Costo unitario estimado <span class="text-danger">*</span></label>
                        <input type="number" id="libreCosto" class="form-control" min="0" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Unidad</label>
                        <input type="text" id="libreUnidad" class="form-control" maxlength="20" placeholder="gr / kg / unidad">
                    </div>
                </div>
                <div id="libreError" class="alert alert-danger py-2 small d-none mt-3"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarIngredienteLibre()">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL PROMOVER A PRODUCTO REAL ===== -->
<div class="modal fade" id="modalPromover" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Promover a producto real</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPromover">
                <div class="modal-body">
                    <p class="small text-muted">Esto crea un producto nuevo y convierte este borrador en su receta de producción.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre del producto <span class="text-danger">*</span></label>
                        <input type="text" id="promNombre" class="form-control" maxlength="150" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Código (opcional)</label>
                            <input type="text" id="promCodigo" class="form-control" maxlength="40">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Categoría</label>
                            <select id="promCategoria" class="form-select">
                                <option value="">— Sin categoría —</option>
                                <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Precio de venta <span class="text-danger">*</span></label>
                            <input type="number" id="promPrecio" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">IVA</label>
                            <select id="promIva" class="form-select">
                                <option value="21">21%</option>
                                <option value="10.5">10,5%</option>
                                <option value="0">0%</option>
                            </select>
                        </div>
                    </div>
                    <div id="promoverError" class="alert alert-danger py-2 small d-none mt-3"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Crear producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL    = '<?= BASE_URL ?>';
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const ES_EDICION  = <?= $esEdicion ? 'true' : 'false' ?>;
const RECETA_ID   = <?= $esEdicion ? (int)$receta['id'] : 'null' ?>;

let ingredientes = <?= $esEdicion ? json_encode(array_map(fn($it) => [
    'tipo_ingrediente' => $it['tipo_ingrediente'],
    'item_id'          => $it['materia_prima_id'] ?? $it['insumo_id'] ?? $it['ingrediente_generico_id'] ?? $it['receta_ingrediente_id'],
    'nombre'           => $it['ingrediente_nombre'] ?? '',
    'cantidad'         => $it['cantidad'],
    'merma_pct'        => $it['merma_pct'] ?? 0,
    'stock_actual'     => $it['stock_actual'] ?? 0,
    'costo_unitario'   => $it['costo_unitario'] ?? 0,
    'desvio_pct'       => $it['desvio_pct'] ?? 0,
    'unidad'           => $it['unidad'] ?? '',
    'ingrediente_libre_nombre'         => $it['ingrediente_libre_nombre'] ?? null,
    'ingrediente_libre_costo_unitario' => $it['ingrediente_libre_costo_unitario'] ?? null,
    'ingrediente_libre_unidad'         => $it['ingrediente_libre_unidad'] ?? null,
], $receta['detalle'] ?? [])) : '[]' ?>;

// ── Búsqueda de ingredientes del sistema ────────────────────────────────────────
document.getElementById('btnAgregarMp').addEventListener('click', function(){
    const buscador = document.getElementById('buscadorIng');
    buscador.focus();
    buscador.classList.add('resaltar-buscador');
    setTimeout(() => buscador.classList.remove('resaltar-buscador'), 1200);
});

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
    const r=await fetch(`${BASE_URL}/recetas-borrador/buscar-ingredientes?q=${encodeURIComponent(q)}&receta_id=${RECETA_ID||''}`);
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

function agregarIng(it){
    ingredientes.push({tipo_ingrediente:it.tipo,item_id:it.id,nombre:it.nombre,cantidad:1,merma_pct:0,stock_actual:parseFloat(it.stock_actual)||0,costo_unitario:parseFloat(it.costo_unitario)||0,desvio_pct:parseFloat(it.desvio_pct)||0,unidad:it.unidad||''});
    ocultarIng(); document.getElementById('buscadorIng').value='';
    renderIng();
}

// ── Ingrediente inventado (nombre + costo tipeados a mano) ─────────────────────
let _libreEditandoIdx = null;

function abrirModalIngredienteLibreNuevo(){
    _libreEditandoIdx = null;
    document.getElementById('libreNombre').value = '';
    document.getElementById('libreCosto').value  = '';
    document.getElementById('libreUnidad').value = '';
    document.getElementById('libreError').classList.add('d-none');
    _showModal('modalIngredienteLibre');
}

function abrirModalIngredienteLibreEditar(idx){
    _libreEditandoIdx = idx;
    const it = ingredientes[idx];
    document.getElementById('libreNombre').value = it.nombre || '';
    document.getElementById('libreCosto').value  = it.costo_unitario || '';
    document.getElementById('libreUnidad').value = it.unidad || '';
    document.getElementById('libreError').classList.add('d-none');
    _showModal('modalIngredienteLibre');
}

function guardarIngredienteLibre(){
    const nombre = document.getElementById('libreNombre').value.trim();
    const costo  = parseFloat(document.getElementById('libreCosto').value);
    const unidad = document.getElementById('libreUnidad').value.trim();
    const errEl  = document.getElementById('libreError');

    if (!nombre) { errEl.textContent = 'El nombre es obligatorio.'; errEl.classList.remove('d-none'); return; }
    if (isNaN(costo) || costo < 0) { errEl.textContent = 'Cargá un costo estimado válido.'; errEl.classList.remove('d-none'); return; }

    const datos = {
        tipo_ingrediente: 'ingrediente_libre', item_id: null,
        nombre, costo_unitario: costo, unidad,
        ingrediente_libre_nombre: nombre,
        ingrediente_libre_costo_unitario: costo,
        ingrediente_libre_unidad: unidad,
    };

    if (_libreEditandoIdx !== null) {
        ingredientes[_libreEditandoIdx] = { ...ingredientes[_libreEditandoIdx], ...datos };
    } else {
        ingredientes.push({ cantidad: 1, merma_pct: 0, stock_actual: 0, desvio_pct: 0, ...datos });
    }
    _hideModal('modalIngredienteLibre');
    renderIng();
}

function editarFila(idx){
    if (ingredientes[idx].tipo_ingrediente === 'ingrediente_libre') {
        abrirModalIngredienteLibreEditar(idx);
    } else {
        editarIngInline(idx);
    }
}

// ── Cambiar el ingrediente de una fila existente (solo para ítems del sistema) ──
let _filaEditandoIdx = null;
let _inlineBuscTimeout = null;

function _dropdownInline(){ return document.getElementById('inlineDropdownGlobal'); }

function _posicionarDropdownInline(inputEl){
    const dd = _dropdownInline();
    const r  = inputEl.getBoundingClientRect();
    dd.style.top   = r.bottom + 'px';
    dd.style.left  = r.left + 'px';
    dd.style.width = r.width + 'px';
}

function _ocultarDropdownInline(){
    const dd = _dropdownInline();
    dd.style.display = 'none';
    dd.innerHTML = '';
}

function editarIngInline(idx){
    _filaEditandoIdx = idx;
    renderIng();
    const inp = document.getElementById('inlineBusc_' + idx);
    if (inp) inp.focus();
}

function cancelarEdicionInline(){
    _filaEditandoIdx = null;
    _ocultarDropdownInline();
    renderIng();
}

function _buscarInline(idx, valor){
    clearTimeout(_inlineBuscTimeout);
    if (valor.trim().length < 2) { _ocultarDropdownInline(); return; }
    _inlineBuscTimeout = setTimeout(async () => {
        const r = await fetch(`${BASE_URL}/recetas-borrador/buscar-ingredientes?q=${encodeURIComponent(valor.trim())}&receta_id=${RECETA_ID||''}`);
        const data = await r.json();
        _mostrarInline(idx, data);
    }, 300);
}

function _mostrarInline(idx, lista){
    if (idx !== _filaEditandoIdx) return;
    const inp = document.getElementById('inlineBusc_' + idx);
    if (!inp) return;

    const dd = _dropdownInline();
    dd.innerHTML = '';
    if (!lista.length) {
        dd.innerHTML = '<span class="dropdown-item-text text-muted small py-2">Sin resultados.</span>';
    } else {
        lista.forEach(it => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dropdown-item py-2';
            btn.innerHTML = `<span class="fw-semibold">${esc(it.nombre)}</span>
                <span class="badge bg-secondary-soft text-secondary float-end">${esc(it.tipo.replace('_',' '))}</span>`;
            btn.addEventListener('click', () => _reemplazarIngInline(idx, it));
            dd.appendChild(btn);
        });
    }
    _posicionarDropdownInline(inp);
    dd.style.display = 'block';
}

function _reemplazarIngInline(idx, it){
    ingredientes[idx] = {
        ...ingredientes[idx],
        tipo_ingrediente: it.tipo,
        item_id: it.id,
        nombre: it.nombre,
        stock_actual: parseFloat(it.stock_actual) || 0,
        costo_unitario: parseFloat(it.costo_unitario) || 0,
        desvio_pct: parseFloat(it.desvio_pct) || 0,
        unidad: it.unidad || '',
        ingrediente_libre_nombre: null,
        ingrediente_libre_costo_unitario: null,
        ingrediente_libre_unidad: null,
    };
    _filaEditandoIdx = null;
    _ocultarDropdownInline();
    renderIng();
}

document.addEventListener('click', function(e){
    if (_filaEditandoIdx === null) return;
    const path = e.composedPath ? e.composedPath() : [e.target];
    if (path.some(el => el && (el.id === 'tblIngredientes' || el.id === 'inlineDropdownGlobal'))) return;
    cancelarEdicionInline();
});

window.addEventListener('resize', () => { if (_filaEditandoIdx !== null) _ocultarDropdownInline(); });
document.querySelector('#tblIngredientes')?.closest('.table-responsive')?.addEventListener('scroll', () => {
    if (_filaEditandoIdx !== null) _ocultarDropdownInline();
});

function renderIng(){
    const tbody=document.getElementById('cuerpoIng');
    const rowV=document.getElementById('rowVacioIng');
    tbody.innerHTML='';
    if(!ingredientes.length){rowV.style.display='';actualizarCosto();sincronizarIng();return;}
    rowV.style.display='none';
    ingredientes.forEach((it,idx)=>{
        const cantidadConMerma = it.cantidad*(1+it.merma_pct/100);
        const costoMp     = cantidadConMerma*it.costo_unitario;
        const costoDesvio = costoMp*(it.desvio_pct/100);
        const esSubReceta = it.tipo_ingrediente === 'receta';
        const esLibre      = it.tipo_ingrediente === 'ingrediente_libre';
        const stockCell = (esSubReceta || esLibre)
            ? '<span class="text-muted" title="Sin stock real: es una sub-receta o un ingrediente inventado">—</span>'
            : `${parseFloat(it.stock_actual)<parseFloat(it.cantidad)?'<span class="text-danger fw-bold">':'<span class="text-success">'}${parseFloat(it.stock_actual).toLocaleString('es-AR',{maximumFractionDigits:3})}</span>`;
        const btnCosto = esSubReceta
            ? `<a href="${BASE_URL}/recetas/editar?id=${it.item_id}" target="_blank" class="ms-1" title="Ver esa receta">↗</a>`
            : (esLibre ? '' : `<button type="button" class="btn btn-sm btn-link p-0 ms-1" title="¿Cómo se calculó este costo?" onclick="verDetalleCosto(${idx})">
                 <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
               </button>`);
        const unidadEsc = esc(it.unidad||'u');
        let detalleCalc = `${parseFloat(cantidadConMerma).toLocaleString('es-AR',{maximumFractionDigits:3})} ${unidadEsc} × ${formatMoneda(it.costo_unitario)}/${unidadEsc}`;
        if (it.merma_pct > 0) detalleCalc += ` <span title="Cantidad ya incluye ${it.merma_pct}% de merma">*</span>`;

        const celdaIngrediente = (idx === _filaEditandoIdx)
            ? `<td class="small" style="min-width:200px">
                   <div class="input-group input-group-sm">
                       <input type="text" class="form-control" id="inlineBusc_${idx}" placeholder="Buscar ingrediente..." autocomplete="off"
                           oninput="_buscarInline(${idx}, this.value)"
                           onkeydown="if(event.key==='Escape')cancelarEdicionInline()">
                       <button type="button" class="btn btn-outline-secondary" title="Cancelar" onclick="cancelarEdicionInline()">✕</button>
                   </div>
               </td>`
            : `<td class="small fw-semibold">${esc(it.nombre)} <span class="text-muted">${esc(it.unidad)}</span>${esLibre ? ' <span class="badge bg-warning-soft text-warning ms-1" style="font-size:.65rem">inventado</span>' : ''}</td>`;

        const tr=document.createElement('tr');
        tr.innerHTML=`
            ${celdaIngrediente}
            <td><span class="badge bg-secondary-soft text-secondary small">${esc(it.tipo_ingrediente.replace('_',' '))}</span></td>
            <td><input type="number" class="form-control form-control-sm" min="0.001" step="0.001" value="${it.cantidad}" onchange="updIng(${idx},'cantidad',this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" min="0" max="100" step="0.1" value="${it.merma_pct}" onchange="updIng(${idx},'merma_pct',this.value)"></td>
            <td class="small">
                <div class="fw-semibold">${formatMoneda(costoMp)}${btnCosto}</div>
                <div class="text-muted" style="font-size:.72rem;line-height:1.1">${detalleCalc}</div>
            </td>
            <td class="small ${it.desvio_pct>0?'text-danger fw-semibold':'text-muted'}">${formatMoneda(costoDesvio)}</td>
            <td class="small">${stockCell}</td>
            <td class="text-nowrap">
                <button type="button" class="btn btn-link p-0 me-1" style="line-height:1" title="${esLibre ? 'Editar este ingrediente inventado' : 'Cambiar este ingrediente por otro (mantiene cantidad/merma)'}" onclick="editarFila(${idx})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button type="button" class="btn btn-link text-danger p-0" style="font-size:1.35rem;line-height:1" title="Quitar" onclick="quitarIng(${idx})">✕</button>
            </td>`;
        tbody.appendChild(tr);
    });
    actualizarCosto(); sincronizarIng();
}

function updIng(idx,campo,val){ingredientes[idx][campo]=parseFloat(val);renderIng();}
function quitarIng(idx){ingredientes.splice(idx,1);renderIng();}

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
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    el.querySelector('[data-bs-dismiss="modal"]')?.click();
}

// ── "¿Cómo se calculó este costo?" (reusa el mismo endpoint que Recetas) ────────
async function verDetalleCosto(idx){
    const it = ingredientes[idx];
    const body = document.getElementById('detalleCostoBody');
    if (!body) return;
    body.innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    _showModal('modalDetalleCosto');
    try{
        const r = await fetch(`${BASE_URL}/recetas/detalle-costo?tipo=${encodeURIComponent(it.tipo_ingrediente)}&id=${it.item_id}`);
        const d = await r.json();
        if(!d.ok){ body.innerHTML = `<div class="alert alert-danger mb-0">${esc(d.error||'No se pudo obtener el detalle.')}</div>`; return; }
        body.innerHTML = renderDetalleCosto(d);
    }catch{
        body.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión.</div>';
    }
}

function renderDetalleCosto(d){
    if(d.tipo === 'ingrediente_generico'){
        const filas = (d.materias_primas||[]).map(mp => { const u = esc(mp.unidad||'u'); return `
            <tr class="${mp.peso_pct>=40?'table-warning':''}">
                <td class="small">${esc(mp.nombre)}</td>
                <td class="small text-end">${formatMoneda(mp.costo_unitario)} <span class="text-muted">/${u}</span></td>
                <td class="small text-end">${parseFloat(mp.desvio_pct).toFixed(1)}%</td>
                <td class="small text-end fw-semibold">${formatMoneda(mp.costo_con_desvio)} <span class="text-muted fw-normal">/${u}</span></td>
                <td class="small text-end">${Number(mp.stock_actual).toLocaleString('es-AR')} ${u}</td>
                <td class="small text-end fw-bold">${parseFloat(mp.peso_pct).toFixed(1)}%</td>
            </tr>`;}).join('') || '<tr><td colspan="6" class="text-center text-muted small py-3">No hay materias primas mapeadas a este ingrediente genérico.</td></tr>';
        return `
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Materia prima</th><th class="text-end">Costo</th><th class="text-end">Desvío</th><th class="text-end">Costo c/desvío</th><th class="text-end">Stock</th><th class="text-end">Peso en el promedio</th></tr></thead>
                    <tbody>${filas}</tbody>
                </table>
            </div>`;
    }

    const u = esc(d.unidad||'u');
    const compras = d.compras || [];
    const filasCompras = compras.length ? compras.map(c => `
        <tr>
            <td class="small">${esc(c.fecha).split('-').reverse().join('/')}</td>
            <td class="small">${esc(c.nro_comprobante)}</td>
            <td class="small">${esc(c.proveedor||'—')}</td>
            <td class="small text-end">${Number(c.cantidad).toLocaleString('es-AR')} ${u}</td>
            <td class="small text-end ${parseFloat(c.precio_unitario)<=0?'text-danger fw-bold':''}">${formatMoneda(c.precio_unitario)} /${u}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted small py-3">Sin compras registradas para este ítem.</td></tr>';

    return `
        <p class="small text-muted">
            El costo actual de <strong>${esc(d.nombre)}</strong> es <strong>${formatMoneda(d.costo_unitario)} por ${u}</strong>.
        </p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Proveedor</th><th class="text-end">Cantidad</th><th class="text-end">Precio unitario</th></tr></thead>
                <tbody>${filasCompras}</tbody>
            </table>
        </div>`;
}

function sincronizarIng(){
    document.getElementById('itemsJson').value=JSON.stringify(ingredientes);
}

function _hayIngredientesLibres(){
    return ingredientes.some(it => it.tipo_ingrediente === 'ingrediente_libre');
}

function _actualizarEstadoPromover(){
    const btn = document.getElementById('btnPromover');
    if (!btn) return;
    const bloqueado = _hayIngredientesLibres() || !ingredientes.length;
    btn.disabled = bloqueado;
    btn.title = bloqueado ? 'Reemplazá los ingredientes inventados por ingredientes reales antes de promover.' : '';
    document.getElementById('promoverAvisoLibres')?.classList.toggle('d-none', !_hayIngredientesLibres());
}

function actualizarCosto(){
    let costoMp=0, costoDesvio=0;
    ingredientes.forEach(it=>{
        const cm = it.cantidad*(1+it.merma_pct/100)*it.costo_unitario;
        costoMp     += cm;
        costoDesvio += cm*(it.desvio_pct/100);
    });
    const costoTotal = costoMp + costoDesvio;
    document.getElementById('costoMpBadge').textContent=formatMoneda(costoMp);
    document.getElementById('costoDesvioBadge').textContent=formatMoneda(costoDesvio);
    document.getElementById('costoBadge').textContent=formatMoneda(costoTotal);

    const rendimientoInput = document.querySelector('[name="rendimiento"]');
    const unidadInput      = document.querySelector('[name="unidad_rendimiento"]');
    const rendimiento = Math.max(parseFloat(rendimientoInput?.value) || 0, 0.001);
    const costoIndividual = costoTotal / rendimiento;
    document.getElementById('costoIndividualBadge').textContent = formatMoneda(costoIndividual);
    document.getElementById('costoIndividualUnidad').textContent = (unidadInput?.value || 'unidad').trim() || 'unidad';

    // Margen: compara el precio de venta (por unidad) contra el costo individual
    // (por unidad) — no contra el costo total del lote, que puede rendir muchas unidades.
    const precioInput   = document.getElementById('precioVentaEstimado');
    const margenPctEl   = document.getElementById('margenPctBadge');
    const margenMontoEl = document.getElementById('margenMontoBadge');
    if (precioInput && margenPctEl && margenMontoEl) {
        const precio = parseFloat(precioInput.value) || 0;
        if (precio > 0) {
            const margenPct = ((precio - costoIndividual) / precio) * 100;
            margenPctEl.textContent = margenPct.toFixed(1).replace('.', ',') + '%';
            margenPctEl.className = 'fw-bold ' + (margenPct >= 0 ? 'text-success' : 'text-danger');
            margenMontoEl.textContent = formatMoneda(precio - costoIndividual);
        } else {
            margenPctEl.textContent = '—';
            margenPctEl.className = 'fw-bold';
            margenMontoEl.textContent = '—';
        }
    }

    _actualizarEstadoPromover();
}

// ── Promover a producto real ────────────────────────────────────────────────────
function abrirModalPromover(){
    document.getElementById('promNombre').value = document.querySelector('[name="nombre"]').value;
    document.getElementById('promPrecio').value = document.getElementById('precioVentaEstimado').value || '';
    document.getElementById('promoverError').classList.add('d-none');
    _showModal('modalPromover');
}

document.getElementById('formPromover')?.addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl = document.getElementById('promoverError');
    errEl.classList.add('d-none');
    const fd = new FormData();
    fd.append('id', RECETA_ID);
    fd.append('nombre', document.getElementById('promNombre').value.trim());
    fd.append('codigo', document.getElementById('promCodigo').value.trim());
    fd.append('categoria_id', document.getElementById('promCategoria').value);
    fd.append('precio_venta', document.getElementById('promPrecio').value);
    fd.append('iva', document.getElementById('promIva').value);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/recetas-borrador/promover`, { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.ok) { errEl.textContent = d.error; errEl.classList.remove('d-none'); return; }
        showToast(d.mensaje);
        setTimeout(() => window.location.href = `${BASE_URL}/productos/editar?id=${d.producto_id}`, 800);
    } catch { errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formBorrador').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl=document.getElementById('formError'); errEl.classList.add('d-none');
    const btn=document.getElementById('btnGuardar'); btn.disabled=true;
    const fd=new FormData(this);
    const url=ES_EDICION?`${BASE_URL}/recetas-borrador/actualizar`:`${BASE_URL}/recetas-borrador/guardar`;
    try{
        const r=await fetch(url,{method:'POST',body:fd});
        const d=await r.json();
        if(!d.ok){errEl.textContent=d.error;errEl.classList.remove('d-none');btn.disabled=false;return;}
        showToast(d.mensaje);
        if (!ES_EDICION) {
            setTimeout(()=>{window.location.href=`${BASE_URL}/recetas-borrador/editar?id=${d.id}`;},800);
        } else {
            btn.disabled=false;
        }
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');btn.disabled=false;}
});

document.addEventListener('click',function(e){
    if(!e.target.closest('#resultadosIng')&&!e.target.closest('#buscadorIng')&&!e.target.closest('#btnBuscarIng'))ocultarIng();
});

document.addEventListener('DOMContentLoaded', function () {
    renderIng();
    document.querySelector('[name="rendimiento"]')?.addEventListener('input', actualizarCosto);
    document.querySelector('[name="unidad_rendimiento"]')?.addEventListener('input', actualizarCosto);
    document.getElementById('precioVentaEstimado')?.addEventListener('input', actualizarCosto);
});
</script>

<!-- ===== MODAL ¿CÓMO SE CALCULÓ ESTE COSTO? (reusa el mismo endpoint que Recetas) ===== -->
<div class="modal fade" id="modalDetalleCosto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">¿Cómo se calculó este costo?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCostoBody">
                <div class="text-center text-muted py-4">Cargando…</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
