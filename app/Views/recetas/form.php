<?php $esEdicion = !empty($receta); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar receta' : 'Nueva receta' ?></h1>
    <div class="d-flex gap-2">
        <?php if ($esEdicion): ?>
        <a href="<?= url('recetas/imprimir?id='.$receta['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Imprimir receta para cocina (sin costos)">
            🖨 Imprimir
        </a>
        <?php endif; ?>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<form id="formReceta" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $receta['id'] ?>"><?php endif; ?>

    <div class="row g-3">
        <!-- Columna izquierda: datos generales -->
        <div class="col-lg-3">
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos de la receta</h6></div>
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notas internas</label>
                        <textarea name="notas" class="form-control" rows="2"><?= e($receta['notas'] ?? '') ?></textarea>
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
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Costo de materia prima</span>
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

            <?php
            $productosAsignados = $productosAsignados ?? [];
            $productosActivos   = $productosActivos   ?? [];
            $_productosPool = array_values(array_reduce(
                array_merge($productosActivos, $productosAsignados),
                function($c, $p) { $c[(int)$p['id']] = ['id' => (int)$p['id'], 'nombre' => $p['nombre']]; return $c; },
                []
            ));
            ?>
            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent pb-1">
                    <h6 class="fw-semibold mb-0 small">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1 text-success"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        Productos que usan esta receta
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="position-relative mb-2">
                        <input type="text" id="searchProductoInput" class="form-control form-control-sm"
                            placeholder="Buscar producto por nombre…" autocomplete="off">
                        <div id="searchProductoResults" class="dropdown-menu w-100 shadow-sm py-0"
                            style="display:none;max-height:160px;overflow-y:auto;position:absolute;top:100%;left:0;z-index:1050"></div>
                    </div>
                    <div id="listaProductosSeleccionados" class="mb-1"></div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: ingredientes -->
        <div class="col-lg-9">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="fw-semibold mb-0">Ingredientes</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-success btn-sm" id="btnAgregarMp" title="Buscá acá al lado y elegí un resultado para agregarlo a la receta">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Agregar MP
                        </button>
                        <div class="input-group input-group-sm" style="max-width:280px">
                            <input type="text" id="buscadorIng" class="form-control" placeholder="Buscar materia prima o insumo...">
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
                                    <th>Ingrediente</th><th style="width:55px">Tipo</th>
                                    <th style="width:85px">Cantidad</th>
                                    <th style="width:70px">Merma %</th>
                                    <th style="width:95px">Costo MP</th>
                                    <th style="width:95px">Desvío $</th>
                                    <th style="width:55px">Stock</th>
                                    <th style="width:65px"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoIng"></tbody>
                            <tfoot>
                                <tr id="rowVacioIng">
                                    <td colspan="8" class="text-center text-muted py-3 small">
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

    <style>
        #tblIngredientes > tbody > tr > td { padding-left: .5rem; padding-right: .5rem; }
        /* Resalte breve del buscador de ingredientes al tocar "+ Agregar MP" (ver script). */
        .resaltar-buscador { box-shadow: 0 0 0 .25rem rgba(25,135,84,.5) !important; transition: box-shadow .2s; }
    </style>

    <!-- Dropdown de resultados del buscador inline de "cambiar ingrediente" (ver renderIng()/
         editarIngInline() en el script de abajo). Vive afuera de la tabla a propósito: la tabla
         está dentro de un .table-responsive con overflow-x:auto, que recorta cualquier dropdown
         que se arme adentro de una celda — position:fixed acá lo evita, posicionado por JS.
         Usa la clase "dropdown-menu" (no "list-group") a propósito: es la que ya trae fondo
         sólido resuelto para modo oscuro en el resto del sistema (ver #searchProductoResults
         más arriba en esta misma vista) — con "list-group" quedaba transparente y se veían las
         filas de la tabla por detrás, mezcladas con los resultados. -->
    <div id="inlineDropdownGlobal" class="dropdown-menu shadow-sm" style="position:fixed;z-index:2000;display:none;max-height:220px;overflow-y:auto"></div>

    <input type="hidden" name="items_json" id="itemsJson" value="[]">
    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar receta' : 'Guardar receta' ?>
        </button>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<!-- ===== MODAL ¿CÓMO SE CALCULÓ ESTE COSTO? ===== -->
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

<script>
const BASE_URL    = '<?= BASE_URL ?>';
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const ES_EDICION  = <?= $esEdicion ? 'true' : 'false' ?>;
const RECETA_ID   = <?= $esEdicion ? (int)$receta['id'] : 'null' ?>;

// ── Pool + selección de productos ─────────────────────────────────────────────
const _PRODUCTOS_POOL = <?= json_encode($_productosPool, JSON_UNESCAPED_UNICODE) ?>;

const _selectedProductos = new Map(<?= json_encode(
    array_map(fn($p) => [(int)$p['id'], ['id' => (int)$p['id'], 'nombre' => $p['nombre']]],
    $productosAsignados),
    JSON_UNESCAPED_UNICODE
) ?>);

function _renderSelectedProductos() {
    const container = document.getElementById('listaProductosSeleccionados');
    if (!_selectedProductos.size) {
        container.innerHTML = '<p class="text-muted small mb-0">Ningún producto asignado aún.</p>';
        return;
    }
    let html = '';
    _selectedProductos.forEach(p => {
        html += `<div class="d-flex align-items-center justify-content-between py-1 border-bottom">
            <span class="fw-semibold small">${_escP(p.nombre)}</span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ms-1"
                onclick="_removeProducto(${p.id})">✕</button>
        </div>`;
    });
    container.innerHTML = html;
}

async function _addProducto(producto) {
    if (_selectedProductos.has(producto.id)) { _hidePSearch(); return; }
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('receta_id', RECETA_ID);
        fd.append('producto_id', producto.id);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/recetas/asignar-producto`, {method:'POST',body:fd})).json();
            if (d.ok) { _selectedProductos.set(producto.id, {id: producto.id, nombre: producto.nombre}); _renderSelectedProductos(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _selectedProductos.set(producto.id, {id: producto.id, nombre: producto.nombre});
        _renderSelectedProductos();
    }
    _hidePSearch();
    document.getElementById('searchProductoInput').value = '';
}

async function _removeProducto(productoId) {
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('receta_id', RECETA_ID);
        fd.append('producto_id', productoId);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/recetas/quitar-producto`, {method:'POST',body:fd})).json();
            if (d.ok) { _selectedProductos.delete(productoId); _renderSelectedProductos(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _selectedProductos.delete(productoId);
        _renderSelectedProductos();
    }
}

const _pSrchInput   = document.getElementById('searchProductoInput');
const _pSrchResults = document.getElementById('searchProductoResults');

_pSrchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (!q) { _hidePSearch(); return; }
    const matches = _PRODUCTOS_POOL.filter(p => p.nombre.toLowerCase().includes(q) && !_selectedProductos.has(p.id));
    if (!matches.length) {
        _pSrchResults.innerHTML = '<div class="dropdown-item text-muted small py-2">Sin resultados.</div>';
        _pSrchResults.style.display = 'block';
        return;
    }
    _pSrchResults.innerHTML = matches.slice(0, 8).map(p =>
        `<button type="button" class="dropdown-item py-2 small" data-pid="${p.id}">${_escP(p.nombre)}</button>`
    ).join('');
    _pSrchResults.querySelectorAll('[data-pid]').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = _PRODUCTOS_POOL.find(x => x.id == btn.dataset.pid);
            if (p) _addProducto(p);
        });
    });
    _pSrchResults.style.display = 'block';
});

function _hidePSearch() { _pSrchResults.style.display = 'none'; }

document.addEventListener('click', function(e) {
    if (!e.target.closest('#searchProductoInput') && !e.target.closest('#searchProductoResults')) _hidePSearch();
});

function _escP(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── ─────────────────────────────────────────────────────────────────────────

let ingredientes = <?= $esEdicion ? json_encode(array_map(fn($it) => [
    'tipo_ingrediente' => $it['tipo_ingrediente'] ?? ($it['materia_prima_id'] ? 'materia_prima' : ($it['insumo_id'] ? 'insumo' : 'ingrediente_generico')),
    'item_id'          => $it['materia_prima_id'] ?? $it['insumo_id'] ?? $it['ingrediente_generico_id'] ?? $it['receta_ingrediente_id'],
    'nombre'           => $it['ingrediente_nombre'] ?? '',
    'cantidad'         => $it['cantidad'],
    'merma_pct'        => $it['merma_pct'] ?? 0,
    'stock_actual'     => $it['stock_actual'] ?? 0,
    'costo_unitario'   => $it['costo_unitario'] ?? 0,
    'desvio_pct'       => $it['desvio_pct'] ?? 0,
    'unidad'           => $it['unidad'] ?? '',
], $receta['detalle'] ?? [])) : '[]' ?>;

// ── Búsqueda de ingredientes ───────────────────────────────────────────────────
// El botón "Agregar MP" no agrega nada por sí solo (todavía no se sabe QUÉ
// ingrediente) — su función es dejar clarísimo dónde hay que escribir: enfoca el
// buscador y lo resalta un instante para que salte a la vista.
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
    const r=await fetch(`${BASE_URL}/recetas/buscar-ingredientes?q=${encodeURIComponent(q)}&receta_id=${RECETA_ID||''}`);
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
    ingredientes.push({tipo_ingrediente:it.tipo,item_id:it.id,nombre:it.nombre,cantidad:1,merma_pct:0,stock_actual:parseFloat(it.stock_actual)||0,costo_unitario:parseFloat(it.costo_unitario)||0,desvio_pct:parseFloat(it.desvio_pct)||0,unidad:it.unidad||''});
    ocultarIng(); document.getElementById('buscadorIng').value='';
    renderIng();
}

// ── Cambiar el ingrediente de una fila existente, buscando en el lugar ─────────
// (ej. cambiar la MP de "cruda" a "congelada") sin perder la cantidad/merma ya
// cargadas. A diferencia del buscador de arriba (que agrega una fila nueva), este
// buscador aparece DENTRO de la celda "Ingrediente" de la fila que se está editando.
//
// El dropdown de resultados (#inlineDropdownGlobal) es UNO SOLO, compartido por
// todas las filas, y vive fuera de la tabla (ver el <div> junto al card de
// Ingredientes) con position:fixed — la tabla está dentro de un .table-responsive
// con overflow-x:auto, que recorta cualquier hijo en position:absolute/relative que
// se salga del alto de la celda, así que un dropdown "adentro" quedaba invisible.
// _posicionarDropdownInline() lo ubica por JS pegado al input que esté activo.
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
        const r = await fetch(`${BASE_URL}/recetas/buscar-ingredientes?q=${encodeURIComponent(valor.trim())}&receta_id=${RECETA_ID||''}`);
        const data = await r.json();
        _mostrarInline(idx, data);
    }, 300);
}

function _mostrarInline(idx, lista){
    // La fila pudo haberse cerrado (o cambiado a otra) mientras esperábamos la respuesta.
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
    };
    _filaEditandoIdx = null;
    _ocultarDropdownInline();
    renderIng();
}

// Cancela la edición inline si se hace clic afuera de la tabla Y afuera del dropdown
// (que ahora vive fuera de la tabla, ver arriba). Ojo acá: no se puede chequear "¿el
// clic fue dentro de la celda que se estaba editando?" comparando contra e.target
// después de un clic en el ✏️ de OTRA fila, porque ese clic ya disparó renderIng()
// (que reconstruye el tbody) antes de que este listener corra — e.target quedaría
// huérfano y el chequeo fallaría siempre. Por eso se usa composedPath() (la cadena de
// ancestros al momento del clic, no la actual) contra dos elementos estables (la
// tabla y el dropdown, que nunca se destruyen) — así cualquier clic dentro de
// cualquiera de los dos queda cubierto por su propio manejador y no se auto-cancela.
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
        const stockCell = esSubReceta
            ? '<span class="text-muted" title="Se descuenta de sus propios ingredientes al producir">—</span>'
            : `${parseFloat(it.stock_actual)<parseFloat(it.cantidad)?'<span class="text-danger fw-bold">':'<span class="text-success">'}${parseFloat(it.stock_actual).toLocaleString('es-AR',{maximumFractionDigits:3})}</span>`;
        // Botón "¿cómo se calculó?": solo tiene sentido para tipos con un costo
        // explicable vía compras o promedio ponderado (ver RecetaModel::detalleCosto);
        // para una sub-receta el costo sale de recalcular esa otra receta, así que
        // en vez de un desglose acá se linkea directo a editarla.
        const btnCosto = esSubReceta
            ? `<a href="${BASE_URL}/recetas/editar?id=${it.item_id}" target="_blank" class="ms-1" title="Ver esa receta">↗</a>`
            : `<button type="button" class="btn btn-sm btn-link p-0 ms-1" title="¿Cómo se calculó este costo?" onclick="verDetalleCosto(${idx})">
                 <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
               </button>`;
        // Debajo del total, el detalle "cantidad unidad × costo/unidad" para que se
        // entienda de un vistazo a qué corresponde el número (ej. "45 gr × $10,00/gr").
        const unidadEsc = esc(it.unidad||'u');
        let detalleCalc = `${parseFloat(cantidadConMerma).toLocaleString('es-AR',{maximumFractionDigits:3})} ${unidadEsc} × ${formatMoneda(it.costo_unitario)}/${unidadEsc}`;
        if (it.merma_pct > 0) detalleCalc += ` <span title="Cantidad ya incluye ${it.merma_pct}% de merma">*</span>`;

        // Celda "Ingrediente": normalmente el nombre solo; mientras esta fila está en
        // modo edición (idx === _filaEditandoIdx), se reemplaza por un buscador con
        // autocompletado in situ (ver editarIngInline/_buscarInline). El dropdown de
        // resultados NO va acá adentro (ver _posicionarDropdownInline): la tabla vive
        // dentro de un .table-responsive con overflow-x:auto, que recorta cualquier
        // hijo posicionado en absolute que se salga del alto de la celda — por eso se
        // ancla como position:fixed sobre el body y se posiciona por JS.
        const celdaIngrediente = (idx === _filaEditandoIdx)
            ? `<td class="small" style="min-width:200px">
                   <div class="input-group input-group-sm">
                       <input type="text" class="form-control" id="inlineBusc_${idx}" placeholder="Buscar ingrediente..." autocomplete="off"
                           oninput="_buscarInline(${idx}, this.value)"
                           onkeydown="if(event.key==='Escape')cancelarEdicionInline()">
                       <button type="button" class="btn btn-outline-secondary" title="Cancelar" onclick="cancelarEdicionInline()">✕</button>
                   </div>
               </td>`
            : `<td class="small fw-semibold">${esc(it.nombre)} <span class="text-muted">${esc(it.unidad)}</span></td>`;

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
                <button type="button" class="btn btn-link p-0 me-1" style="line-height:1" title="Cambiar este ingrediente por otro (mantiene cantidad/merma)" onclick="editarIngInline(${idx})">
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

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// se abre el modal haciendo clic en un trigger oculto con data-bs-toggle.
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

// ── "¿Cómo se calculó este costo?" ─────────────────────────────────────────────
async function verDetalleCosto(idx){
    const it = ingredientes[idx];
    const body = document.getElementById('detalleCostoBody');
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
            <p class="small text-muted">
                <strong>${esc(d.nombre)}</strong> es un ingrediente genérico: no tiene un costo propio cargado, se calcula
                ${d.pondera_por_stock ? 'como el <strong>promedio ponderado por stock</strong> de todas las materias primas mapeadas a él' : 'como el promedio simple de las materias primas mapeadas (ninguna tiene stock cargado todavía)'}
                — la que tiene más stock es la que más pesa en el promedio final (resaltada si pesa 40% o más). El costo
                de cada materia prima es "precio por unidad de su propia unidad de medida" (por gramo, por cc, por unidad, etc.).
            </p>
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
            El costo actual de <strong>${esc(d.nombre)}</strong> es <strong>${formatMoneda(d.costo_unitario)} por ${u}</strong> —
            es el <strong>Costo Promedio Ponderado (CPP)</strong>: se recalcula con cada compra nueva, combinando el
            costo/stock que ya tenía con la cantidad y precio de la compra nueva. Compras más recientes primero:
        </p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Proveedor</th><th class="text-end">Cantidad</th><th class="text-end">Precio unitario</th></tr></thead>
                <tbody>${filasCompras}</tbody>
            </table>
        </div>
        ${compras.length ? '<p class="small text-muted mt-2 mb-0">Si el costo actual no coincide con lo que ves en este historial, puede haberse cargado manualmente o venir de una carga inicial (importación) previa a estas compras.</p>' : ''}`;
}

function sincronizarIng(){
    document.getElementById('itemsJson').value=JSON.stringify(ingredientes);
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

    // Costo individual: cuánto cuesta CADA unidad producida (costo total ÷ rendimiento),
    // ej. si la receta cuesta $10.000 y rinde 10 unidades, cada una sale $1.000.
    const rendimientoInput = document.querySelector('[name="rendimiento"]');
    const unidadInput      = document.querySelector('[name="unidad_rendimiento"]');
    const rendimiento = Math.max(parseFloat(rendimientoInput?.value) || 0, 0.001);
    document.getElementById('costoIndividualBadge').textContent = formatMoneda(costoTotal / rendimiento);
    document.getElementById('costoIndividualUnidad').textContent = (unidadInput?.value || 'unidad').trim() || 'unidad';
}

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formReceta').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl=document.getElementById('formError'); errEl.classList.add('d-none');
    const btn=document.getElementById('btnGuardar'); btn.disabled=true;
    const fd=new FormData(this);
    if(!fd.has('activo'))fd.set('activo','0');
    const url=ES_EDICION?`${BASE_URL}/recetas/actualizar`:`${BASE_URL}/recetas/guardar`;
    try{
        const r=await fetch(url,{method:'POST',body:fd});
        const d=await r.json();
        if(!d.ok){errEl.textContent=d.error;errEl.classList.remove('d-none');btn.disabled=false;return;}
        // Receta nueva: asignar productos seleccionados en loop
        if(!ES_EDICION && _selectedProductos.size){
            for(const [pid] of _selectedProductos){
                const f2=new FormData();
                f2.append('receta_id',d.id);
                f2.append('producto_id',pid);
                f2.append('_csrf_token',CSRF_TOKEN);
                await fetch(`${BASE_URL}/recetas/asignar-producto`,{method:'POST',body:f2});
            }
        }
        showToast(d.mensaje);
        setTimeout(()=>{window.location.href=`${BASE_URL}/recetas`;},800);
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');btn.disabled=false;}
});

document.addEventListener('click',function(e){
    if(!e.target.closest('#resultadosIng')&&!e.target.closest('#buscadorIng')&&!e.target.closest('#btnBuscarIng'))ocultarIng();
});

// Inicializar — esperar a DOMContentLoaded porque renderIng() usa formatMoneda(),
// definida en app.js, que se carga más abajo en el documento (después del
// contenido de esta vista); llamarla antes de tiempo revienta a mitad del
// primer ingrediente y deja la tabla vacía sin ningún aviso.
document.addEventListener('DOMContentLoaded', function () {
    _renderSelectedProductos();
    renderIng();
    // El costo individual depende de rendimiento/unidad, que no forman parte del array
    // "ingredientes" — sin este listener quedaba desactualizado hasta tocar algún ingrediente.
    document.querySelector('[name="rendimiento"]')?.addEventListener('input', actualizarCosto);
    document.querySelector('[name="unidad_rendimiento"]')?.addEventListener('input', actualizarCosto);
});
</script>
