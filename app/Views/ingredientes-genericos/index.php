<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Ingredientes Genéricos</h1>
        <p class="text-muted small mb-0">Agrupá materias primas de distintas marcas/paquetes bajo un mismo ingrediente para que las recetas nunca queden atadas a una marca puntual.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirNuevo()" data-bs-toggle="modal" data-bs-target="#modalItem">+ Nuevo genérico</button>
</div>

<?php if ((Session::user()['rol'] ?? '') === 'admin'): ?>
<div class="card border-0 mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-1">Aviso de caída de margen</label>
                <div class="input-group">
                    <input type="number" id="fUmbralMargen" class="form-control" min="0" step="0.1"
                           value="<?= $configFiscal['umbral_alerta_margen'] !== null ? e($configFiscal['umbral_alerta_margen']) : '' ?>"
                           placeholder="5">
                    <span class="input-group-text">puntos %</span>
                </div>
                <div class="form-text">Si el margen de un producto cae más de esto tras una compra, se avisa por notificación push. Vacío = 5% por defecto.</div>
            </div>
            <div class="col-md-auto">
                <button type="button" class="btn btn-outline-primary" onclick="guardarUmbralMargen()">Guardar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblItems" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Unidad base</th>
                        <th>Categoría</th>
                        <th class="text-center">Variantes (MP)</th>
                        <th class="text-center" style="width:90px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($item['nombre']) ?></td>
                        <td class="small"><?= e($item['unidad'] ?? '—') ?></td>
                        <td class="small"><?= e($item['categoria_nombre'] ?? '—') ?></td>
                        <td class="text-center">
                            <button type="button" class="badge bg-primary-soft text-primary border-0"
                                    style="cursor:pointer"
                                    onclick="abrirVariantes(<?= (int) $item['id'] ?>, '<?= e(addslashes($item['nombre'])) ?>')"
                                    title="Ver/editar materias primas asignadas">
                                <?= (int) $item['variantes_count'] ?>
                            </button>
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
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo ingrediente genérico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formItem" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="itemId" name="id" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="fNombre" name="nombre" class="form-control" required maxlength="150" placeholder="ej: Jamón cocido">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unidad de medida base <span class="text-danger">*</span></label>
                            <select id="fUnidad" name="unidad_medida_id" class="form-select" required>
                                <option value="">— —</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="unidadHelp">Todas las materias primas que asignes a este genérico deberán cargar su "Cantidad contenida" en esta misma unidad.</div>
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

<!-- Modal: variantes (materias primas O insumos — ej. envases) asignadas a un genérico puntual -->
<div class="modal fade" id="modalVariantes" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Variantes de "<span id="varGenericoNombre"></span>"</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Costo unitario</th>
                                <th class="text-center" style="width:90px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="filasVariantes"></tbody>
                    </table>
                    <p id="sinVariantes" class="text-muted small text-center py-3 mb-0 d-none">Todavía no hay materias primas ni insumos asignados a este genérico.</p>
                </div>

                <hr>

                <h6 class="fw-semibold mb-2 small">+ Agregar materia prima o insumo existente</h6>
                <p class="small text-muted mb-2">
                    Solo aparecen materias primas e insumos con la misma unidad de medida base
                    que este genérico (ej. envases/potes también cuentan), y que todavía no
                    estén asignados a ningún otro.
                </p>
                <div class="position-relative mb-2" id="wrapperVariantesDropdown">
                    <div id="variantesDropdownToggle" class="form-select form-select-sm" style="cursor:pointer;user-select:none" onclick="_toggleVariantesSearch()">
                        <span class="text-muted small">Buscar materia prima o insumo…</span>
                    </div>
                </div>
                <div id="variantesError" class="alert alert-danger py-2 small mt-2 d-none"></div>
            </div>
            <div class="modal-footer border-0">
                <a href="<?= url('materias-primas') ?>" target="_blank" class="btn btn-outline-secondary btn-sm me-auto">Ir a Materias Primas ↗</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>

        <!-- Dropdown de resultados del buscador de "agregar materia prima" (ver abrirVariantes()/
             _buscarVariantesCandidatas() en el script de abajo). position:fixed a propósito:
             #modalVariantes usa modal-dialog-scrollable, que le pone overflow-y:auto al modal-body
             — eso recorta cualquier hijo en position:absolute que se salga del alto visible, sin
             importar el z-index (mismo problema ya resuelto en recetas/form.php con
             #inlineDropdownGlobal). position:fixed acá lo evita, posicionado por JS.
             OJO: tiene que quedar DENTRO de #modalVariantes en el HTML (acá, como hermano de
             .modal-content, no afuera de #modalVariantes) aunque su posición visual no dependa
             de dónde esté anidado — si no, el "atrapa-foco" de Bootstrap (que devuelve el foco
             al modal apenas detecta un focusin en un elemento que NO es descendiente del modal
             abierto) se lo roba al input de búsqueda apenas el usuario intenta escribir en él
             (bug real reportado: "no me deja escribir en el buscador del modal"). -->
        <div id="variantesDropdownPanel" class="dropdown-menu shadow-sm" style="position:fixed;z-index:2000;display:none;max-height:240px;overflow-y:auto">
            <div class="p-2 border-bottom">
                <input type="text" id="searchVarianteInput" class="form-control form-control-sm" placeholder="Buscar…" autocomplete="off">
            </div>
            <div id="searchVarianteResults" style="max-height:200px;overflow-y:auto"></div>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const URL_CREAR  = `${BASE_URL}/ingredientes-genericos/crear`;
const URL_EDIT   = `${BASE_URL}/ingredientes-genericos/actualizar`;
const URL_DEL    = `${BASE_URL}/ingredientes-genericos/eliminar`;
let   editMode   = false;

document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') new DataTable('#tblItems');
});

function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); }
    else {
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
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); }
    else { document.querySelector('#' + id + ' [data-bs-dismiss="modal"]')?.click(); }
}

function abrirNuevo() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Nuevo ingrediente genérico';
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('fUnidad').disabled = false;
    limpiarError();
}

function abrirEditar(item) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Editar: ' + item.nombre;
    document.getElementById('itemId').value     = item.id;
    document.getElementById('fNombre').value    = item.nombre;
    document.getElementById('fUnidad').value    = item.unidad_medida_id || '';
    document.getElementById('fCategoria').value = item.categoria_id || '';
    // La unidad es inmutable una vez creado (rompería la comparabilidad de costos entre variantes)
    document.getElementById('fUnidad').disabled = true;
    limpiarError();
    _showModal('modalItem');
}

document.getElementById('formItem').addEventListener('submit', async function (e) {
    e.preventDefault();
    limpiarError();
    document.getElementById('btnGuardar').disabled = true;
    const fd = new FormData(this);
    try {
        const r = await fetch(editMode ? URL_EDIT : URL_CREAR, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _hideModal('modalItem');
            showToast(d.mensaje);
            setTimeout(() => location.reload(), 800);
        } else { mostrarError(d.error); }
    } catch { mostrarError('Error de conexión.'); }
    finally { document.getElementById('btnGuardar').disabled = false; }
});

async function eliminar(id, nombre) {
    const doEliminar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(URL_DEL, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800))
              : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm(`¿Eliminar "${nombre}"?`, doEliminar, { label: 'Eliminar', sub: 'Esta acción no se puede deshacer.' });
    } else {
        if (confirm(`¿Eliminar "${nombre}"?`)) doEliminar();
    }
}

function mostrarError(msg) { const e = document.getElementById('modalError'); e.textContent = msg; e.classList.remove('d-none'); }
function limpiarError()    { document.getElementById('modalError').classList.add('d-none'); }

async function guardarUmbralMargen() {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('umbral_alerta_margen', document.getElementById('fUmbralMargen').value);
    const r = await fetch(`${BASE_URL}/ingredientes-genericos/guardar-umbral-margen`, { method: 'POST', body: fd });
    const d = await r.json();
    d.ok ? showToast(d.mensaje) : showToast(d.error, 'danger');
}

// ── Modal de variantes (materias primas asignadas a un genérico) ────────────────
let genericoActivoId = null;

function abrirVariantes(id, nombre) {
    genericoActivoId = id;
    document.getElementById('varGenericoNombre').textContent = nombre;
    document.getElementById('filasVariantes').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Cargando…</td></tr>';
    document.getElementById('sinVariantes').classList.add('d-none');
    _ocultarVariantesError();
    _hideVariantesSearch();
    _showModal('modalVariantes');
    cargarVariantes();
}

async function cargarVariantes() {
    try {
        const r = await fetch(`${BASE_URL}/ingredientes-genericos/variantes?id=${genericoActivoId}`);
        const d = await r.json();
        _renderVariantes(d.ok ? d.variantes : []);
    } catch {
        document.getElementById('filasVariantes').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error al cargar.</td></tr>';
    }
}

function _etiquetaTipo(tipo) { return tipo === 'insumo' ? 'Insumo' : 'Materia prima'; }

function _renderVariantes(lista) {
    const tbody = document.getElementById('filasVariantes');
    document.getElementById('sinVariantes').classList.toggle('d-none', lista.length > 0);
    tbody.innerHTML = lista.map(v => `
        <tr>
            <td class="fw-semibold small">${esc(v.nombre)}</td>
            <td class="small"><span class="badge ${v.tipo === 'insumo' ? 'bg-secondary-soft text-secondary' : 'bg-info-soft text-info'}">${_etiquetaTipo(v.tipo)}</span></td>
            <td class="text-end small">${parseFloat(v.stock_actual).toLocaleString('es-AR',{minimumFractionDigits:2})} ${esc(v.unidad||'')}</td>
            <td class="text-end small">$ ${parseFloat(v.costo_unitario).toLocaleString('es-AR',{minimumFractionDigits:2})}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="quitarVariante('${v.tipo}', ${v.id})" title="Quitar del genérico">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </button>
            </td>
        </tr>`).join('');
}

async function quitarVariante(tipo, itemId) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('generico_id', genericoActivoId);
        fd.append('tipo', tipo);
        fd.append('item_id', itemId);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/ingredientes-genericos/quitar-variante`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _renderVariantes(d.variantes);
            showToast(d.mensaje);
            // El contador de la tabla de atrás queda desactualizado hasta recargar — se
            // refleja recién al cerrar el modal, no hace falta bloquear la acción por eso.
        } else {
            showToast(d.error, 'danger');
        }
    };
    const etiqueta = _etiquetaTipo(tipo).toLowerCase();
    if (typeof showConfirm === 'function') {
        showConfirm(`¿Quitar esta ${etiqueta} del genérico?`, ejecutar, { label: 'Quitar', sub: 'Queda como variante suelta, no se elimina.' });
    } else if (confirm(`¿Quitar esta ${etiqueta} del genérico? Queda como variante suelta, no se elimina.`)) {
        ejecutar();
    }
}

// Buscador de "agregar" — a diferencia de otros buscadores del sistema (que filtran un
// pool ya cargado en el cliente), este pega al servidor en cada tecla porque el filtro
// (misma unidad de medida + sin genérico asignado) depende de datos que no tiene sentido
// traer todos de antemano.
const _varSrchInput   = document.getElementById('searchVarianteInput');
const _varSrchResults = document.getElementById('searchVarianteResults');
const _varSrchPanel   = document.getElementById('variantesDropdownPanel');

function _posicionarVariantesDropdown() {
    const toggle = document.getElementById('variantesDropdownToggle');
    const r = toggle.getBoundingClientRect();
    _varSrchPanel.style.top   = r.bottom + 'px';
    _varSrchPanel.style.left  = r.left + 'px';
    _varSrchPanel.style.width = r.width + 'px';
}

function _toggleVariantesSearch() {
    if (_varSrchPanel.style.display === 'block') { _hideVariantesSearch(); return; }
    _posicionarVariantesDropdown();
    _varSrchPanel.style.display = 'block';
    _varSrchInput.focus();
    _buscarVariantesCandidatas('');
}
function _hideVariantesSearch() {
    _varSrchPanel.style.display = 'none';
    _varSrchInput.value = '';
}
// composedPath (no e.target.closest): el panel ahora vive AFUERA de #wrapperVariantesDropdown
// (position:fixed, ver comentario arriba del <div>), así que closest() no lo encuentra —
// hay que chequear los dos elementos por separado en la cadena real del evento.
document.addEventListener('click', function (e) {
    const path = e.composedPath ? e.composedPath() : [e.target];
    if (path.some(el => el && el.id && (el.id === 'wrapperVariantesDropdown' || el.id === 'variantesDropdownPanel'))) return;
    _hideVariantesSearch();
});
// El modal-body es scrolleable (modal-dialog-scrollable) — si se scrollea con el dropdown
// abierto, la posición fija calculada ya no coincide con el toggle, mejor cerrarlo.
document.getElementById('modalVariantes').querySelector('.modal-body')?.addEventListener('scroll', _hideVariantesSearch);
window.addEventListener('resize', _hideVariantesSearch);
_varSrchInput.addEventListener('input', function () {
    _buscarVariantesCandidatas(this.value.trim());
});

async function _buscarVariantesCandidatas(q) {
    try {
        const r = await fetch(`${BASE_URL}/ingredientes-genericos/buscar-variantes?generico_id=${genericoActivoId}&q=${encodeURIComponent(q)}`);
        const d = await r.json();
        const candidatas = d.ok ? d.resultados : [];
        if (!candidatas.length) {
            _varSrchResults.innerHTML = '<div class="px-3 py-2 text-muted small">Sin resultados (revisá que tenga la misma unidad de medida y no esté ya asignada a otro genérico).</div>';
            return;
        }
        _varSrchResults.innerHTML = candidatas.map(v => `
            <button type="button" class="w-100 text-start border-0 bg-transparent px-3 py-2 small _varOpt" data-id="${v.id}" data-tipo="${v.tipo}" data-nombre="${esc(v.nombre)}"
                 style="cursor:pointer;display:block">${esc(v.nombre)}
                 <span class="badge ${v.tipo === 'insumo' ? 'bg-secondary-soft text-secondary' : 'bg-info-soft text-info'} ms-1" style="font-size:10px">${_etiquetaTipo(v.tipo)}</span>
                 <span class="text-muted">(stock: ${parseFloat(v.stock_actual).toLocaleString('es-AR')})</span></button>
        `).join('');
        _varSrchResults.querySelectorAll('._varOpt').forEach(btn => {
            btn.addEventListener('mouseover', () => btn.style.background = '#f0f0f0');
            btn.addEventListener('mouseout',  () => btn.style.background = '');
            btn.addEventListener('click', () => _agregarVariante(btn.dataset.tipo, parseInt(btn.dataset.id, 10)));
        });
    } catch {
        _varSrchResults.innerHTML = '<div class="px-3 py-2 text-danger small">Error al buscar.</div>';
    }
}

async function _agregarVariante(tipo, itemId) {
    _ocultarVariantesError();
    const fd = new FormData();
    fd.append('generico_id', genericoActivoId);
    fd.append('tipo', tipo);
    fd.append('item_id', itemId);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/ingredientes-genericos/asignar-variante`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _renderVariantes(d.variantes);
            showToast(d.mensaje);
        } else {
            _mostrarVariantesError(d.error || 'No se pudo asignar.');
        }
    } catch {
        _mostrarVariantesError('Error de conexión.');
    }
    _hideVariantesSearch();
}

function _mostrarVariantesError(msg) { const e = document.getElementById('variantesError'); e.textContent = msg; e.classList.remove('d-none'); }
function _ocultarVariantesError()    { document.getElementById('variantesError').classList.add('d-none'); }
function esc(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
