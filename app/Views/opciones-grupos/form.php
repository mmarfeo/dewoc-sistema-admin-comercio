<?php $esEdicion = $grupo !== null; ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar grupo: ' . e($grupo['nombre']) : 'Nuevo grupo de opciones' ?></h1>
    <a href="<?= url('opciones-productos') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formGrupo" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$grupo['id'] : '' ?>">
    <input type="hidden" name="opciones_json" id="opcionesJson" value="[]">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 mb-3">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-semibold mb-0">Datos del grupo</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="fNombre" class="form-control" maxlength="100" required
                                   value="<?= $esEdicion ? e($grupo['nombre']) : '' ?>" placeholder="Ej. Gustos de helado">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Tipo de selección <span class="text-danger">*</span></label>
                            <select name="tipo_seleccion" id="fTipoSeleccion" class="form-select" onchange="_actualizarVisibilidadRepetir()">
                                <option value="unica" <?= (!$esEdicion || $grupo['tipo_seleccion'] === 'unica') ? 'selected' : '' ?>>Única (radio)</option>
                                <option value="multiple" <?= ($esEdicion && $grupo['tipo_seleccion'] === 'multiple') ? 'selected' : '' ?>>Múltiple (checkbox)</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2" id="wrapPermiteRepetir">
                                <input class="form-check-input" type="checkbox" name="permite_repetir" id="fPermiteRepetir" value="1"
                                       <?= ($esEdicion && $grupo['permite_repetir']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fPermiteRepetir">Permite repetir la misma opción</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="fActivo" value="1"
                               <?= (!$esEdicion || $grupo['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="fActivo">Activo (disponible para elegir en el POS)</label>
                    </div>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0">Opciones</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarOpcion()">+ Agregar opción</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:28px"></th>
                                    <th style="width:30%">Nombre</th>
                                    <th style="width:20%">Descuenta stock de</th>
                                    <th style="width:25%">¿Cuál?</th>
                                    <th style="width:15%">Cantidad fija <span class="text-muted fw-normal" title="Opcional. Dejala vacía si el producto reparte la cantidad entre las opciones elegidas según su tamaño (se configura en la ficha del Producto, no acá).">(opcional) ⓘ</span></th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="opcionesBody"></tbody>
                        </table>
                    </div>
                    <div id="opcionesVacio" class="text-center text-muted py-4 d-none">Agregá al menos una opción.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0">
                <div class="card-body">
                    <p class="small text-muted">
                        Una opción puede no descontar stock de nada (dejando "Ninguno") si es puramente
                        informativa para la comanda, ej. "sin azúcar".
                    </p>
                    <p class="small text-muted mb-0">
                        El límite de cuántas opciones se pueden elegir (mínimo/máximo) se configura por
                        producto, no acá — un mismo grupo se puede reutilizar con límites distintos en
                        cada producto (ver la ficha del producto).
                    </p>
                </div>
            </div>
            <div id="formError" class="alert alert-danger d-none py-2 small mt-3"></div>
            <button type="submit" class="btn btn-primary w-100 mt-3" id="btnGuardar">
                <?= $esEdicion ? 'Guardar cambios' : 'Crear grupo' ?>
            </button>
        </div>
    </div>
</form>

<style>
    #opcionesBody tr.opcion-arrastrando { opacity: .4; }
    #opcionesBody tr.opcion-drop-target { box-shadow: inset 0 2px 0 var(--bs-primary); }
</style>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;
const MATERIAS_PRIMAS = <?= json_encode($ingredientesPool['materias_primas'], JSON_UNESCAPED_UNICODE) ?>;
const INSUMOS          = <?= json_encode($ingredientesPool['insumos'], JSON_UNESCAPED_UNICODE) ?>;
const PRODUCTOS         = <?= json_encode($ingredientesPool['productos'], JSON_UNESCAPED_UNICODE) ?>;

let opciones = <?= $esEdicion ? json_encode(array_map(fn($o) => [
    'nombre'             => $o['nombre'],
    'materia_prima_id'   => $o['materia_prima_id'],
    'insumo_id'          => $o['insumo_id'],
    'producto_id'        => $o['producto_id'],
    'cantidad_consumida' => $o['cantidad_consumida'],
], $grupo['opciones']), JSON_UNESCAPED_UNICODE) : '[]' ?>;
// _tipo es solo UI (no se manda al guardar): recuerda qué se eligió en "Descuenta
// stock de" mientras todavía no se eligió ningún ítem puntual en "¿Cuál?" — sin esto,
// renderOpciones() no tenía forma de saber que el usuario acababa de elegir "Materia
// prima" (recién elegís el ítem DESPUÉS de que aparezca ese selector), y volvía a
// mostrar "Ninguno" en cada redibujado.
opciones.forEach(o => { o._tipo = o.materia_prima_id ? 'materia_prima' : (o.insumo_id ? 'insumo' : (o.producto_id ? 'producto' : '')); });

function _actualizarVisibilidadRepetir() {
    const esMultiple = document.getElementById('fTipoSeleccion').value === 'multiple';
    document.getElementById('wrapPermiteRepetir').style.display = esMultiple ? '' : 'none';
    if (!esMultiple) document.getElementById('fPermiteRepetir').checked = false;
}
_actualizarVisibilidadRepetir();

function agregarOpcion() {
    opciones.push({ nombre: '', materia_prima_id: null, insumo_id: null, producto_id: null, cantidad_consumida: 0, _tipo: '' });
    renderOpciones();
}
function quitarOpcion(idx) {
    opciones.splice(idx, 1);
    renderOpciones();
}
function actualizarOpcion(idx, campo, valor) {
    opciones[idx][campo] = valor;
}
function cambiarTipoIngrediente(idx, tipo) {
    opciones[idx]._tipo = tipo;
    opciones[idx].materia_prima_id = null;
    opciones[idx].insumo_id = null;
    opciones[idx].producto_id = null;
    renderOpciones();
}

function renderOpciones() {
    const tbody = document.getElementById('opcionesBody');
    tbody.innerHTML = '';
    document.getElementById('opcionesVacio').classList.toggle('d-none', opciones.length > 0);

    opciones.forEach((op, idx) => {
        const tipoActual = op._tipo || (op.materia_prima_id ? 'materia_prima' : (op.insumo_id ? 'insumo' : (op.producto_id ? 'producto' : '')));
        const tr = document.createElement('tr');
        tr.draggable = true;
        tr.dataset.idx = idx;
        tr.addEventListener('dragstart', _onDragStart);
        tr.addEventListener('dragover', _onDragOver);
        tr.addEventListener('dragleave', _onDragLeave);
        tr.addEventListener('drop', _onDrop);
        tr.addEventListener('dragend', _onDragEnd);
        tr.innerHTML = `
            <td class="text-center text-muted" style="cursor:grab" title="Arrastrar para reordenar">⠿</td>
            <td><input type="text" class="form-control form-control-sm" value="${esc(op.nombre)}" oninput="actualizarOpcion(${idx},'nombre',this.value)" placeholder="Ej. Dulce de leche"></td>
            <td>
                <select class="form-select form-select-sm" onchange="cambiarTipoIngrediente(${idx}, this.value)">
                    <option value="" ${tipoActual===''?'selected':''}>Ninguno</option>
                    <option value="materia_prima" ${tipoActual==='materia_prima'?'selected':''}>Materia prima</option>
                    <option value="insumo" ${tipoActual==='insumo'?'selected':''}>Insumo</option>
                    <option value="producto" ${tipoActual==='producto'?'selected':''}>Producto</option>
                </select>
            </td>
            <td id="colIngrediente${idx}"></td>
            <td>
                <input type="number" class="form-control form-control-sm" min="0" step="0.0001" placeholder="0 (opcional)"
                    value="${op.cantidad_consumida || ''}" oninput="actualizarOpcion(${idx},'cantidad_consumida',parseFloat(this.value)||0)">
            </td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="quitarOpcion(${idx})" title="Quitar">✕</button></td>
        `;
        tbody.appendChild(tr);

        const col = tr.querySelector(`#colIngrediente${idx}`);
        if (tipoActual === '') {
            col.innerHTML = '<span class="text-muted small">—</span>';
        } else {
            const lista = tipoActual === 'materia_prima' ? MATERIAS_PRIMAS : (tipoActual === 'insumo' ? INSUMOS : PRODUCTOS);
            const actualId = tipoActual === 'materia_prima' ? op.materia_prima_id : (tipoActual === 'insumo' ? op.insumo_id : op.producto_id);
            const sel = document.createElement('select');
            sel.className = 'form-select form-select-sm';
            sel.innerHTML = '<option value="">— Elegir —</option>' +
                lista.map(it => `<option value="${it.id}" ${String(it.id)===String(actualId)?'selected':''}>${esc(it.nombre)}</option>`).join('');
            sel.addEventListener('change', () => {
                if (tipoActual === 'materia_prima') opciones[idx].materia_prima_id = sel.value || null;
                else if (tipoActual === 'insumo') opciones[idx].insumo_id = sel.value || null;
                else opciones[idx].producto_id = sel.value || null;
            });
            col.appendChild(sel);
        }
    });
}
function esc(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ── Reordenar opciones arrastrando filas (Drag and Drop API nativa, sin librería) ──
// El orden final de "opciones" al momento de guardar es lo que define orden en la
// base (OpcionesGrupoController::parsearForm() usa la posición en el array) — así que
// alcanza con reordenar este array en el drop y volver a dibujar la tabla.
let _dragIdx = null;

function _onDragStart(e) {
    _dragIdx = parseInt(e.currentTarget.dataset.idx, 10);
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(_dragIdx)); // Firefox exige setData para permitir el drag
    e.currentTarget.classList.add('opcion-arrastrando');
}
function _onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (parseInt(e.currentTarget.dataset.idx, 10) !== _dragIdx) e.currentTarget.classList.add('opcion-drop-target');
}
function _onDragLeave(e) {
    e.currentTarget.classList.remove('opcion-drop-target');
}
function _onDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('opcion-drop-target');
    const destino = parseInt(e.currentTarget.dataset.idx, 10);
    if (_dragIdx === null || _dragIdx === destino) return;
    const [movida] = opciones.splice(_dragIdx, 1);
    opciones.splice(destino, 0, movida);
    _dragIdx = null;
    renderOpciones();
}
function _onDragEnd() {
    _dragIdx = null;
    document.querySelectorAll('.opcion-arrastrando, .opcion-drop-target')
        .forEach(el => el.classList.remove('opcion-arrastrando', 'opcion-drop-target'));
}

renderOpciones();

document.getElementById('formGrupo').addEventListener('submit', async function (e) {
    e.preventDefault();
    document.getElementById('formError').classList.add('d-none');

    if (!opciones.length) {
        mostrarError('Agregá al menos una opción.');
        return;
    }
    document.getElementById('opcionesJson').value = JSON.stringify(opciones);
    document.getElementById('btnGuardar').disabled = true;

    const url = ES_EDICION ? `${BASE_URL}/opciones-productos/actualizar` : `${BASE_URL}/opciones-productos/guardar`;
    try {
        const fd = new FormData(this);
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje, 'success');
            setTimeout(() => location.href = `${BASE_URL}/opciones-productos`, 800);
        } else {
            mostrarError(d.error || 'No se pudo guardar.');
        }
    } catch {
        mostrarError('Error de conexión.');
    } finally {
        document.getElementById('btnGuardar').disabled = false;
    }
});
function mostrarError(msg) {
    const el = document.getElementById('formError');
    el.textContent = msg;
    el.classList.remove('d-none');
}
</script>
