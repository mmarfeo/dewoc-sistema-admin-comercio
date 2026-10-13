<?php
// Vista reutilizable para Materias Primas — misma estructura que Insumos
$esMP    = true;
$titulo  = 'Materias Primas';
$urlBase = 'materias-primas';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $titulo ?></h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <button class="btn btn-primary" onclick="abrirNuevo()" data-bs-toggle="modal" data-bs-target="#modalItem">+ Nueva materia prima</button>
    </div>
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
                        <th>Tipo gasto</th>
                        <th>Unidad</th>
                        <th>Cant. contenida</th>
                        <th>Ingrediente genérico</th>
                        <th>Stock actual</th>
                        <th>Stock mín.</th>
                        <th>Costo unit.</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:80px">Acciones</th>
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
                            $tgColor = ['costo_variable' => 'warning',  'costo_fijo' => 'info',   'impuesto' => 'danger', 'gasto' => 'secondary'];
                            $tg      = $item['tipo_gasto'] ?? 'costo_variable';
                            ?>
                            <span class="badge bg-<?= $tgColor[$tg] ?? 'secondary' ?>-soft text-<?= $tgColor[$tg] ?? 'secondary' ?>">
                                <?= $tgLabel[$tg] ?? e($tg) ?>
                            </span>
                        </td>
                        <td class="small"><?= e($item['unidad'] ?? '—') ?></td>
                        <td class="small">
                            <?php if ($item['cantidad_contenida']): ?>
                                <?= number_format($item['cantidad_contenida'], 2, ',', '.') ?>
                                <?= $item['unidad'] ? (' ' . e($item['unidad'])) : '' ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($item['generico_nombre']): ?>
                            <span class="badge bg-info-soft text-info"><?= e($item['generico_nombre']) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php $cls = $item['stock_actual'] <= $item['stock_minimo'] ? 'text-danger fw-bold' : 'text-success fw-semibold'; ?>
                            <span class="<?= $cls ?>"><?= number_format($item['stock_actual'], 3, ',', '.') ?></span>
                        </td>
                        <td class="small text-muted"><?= number_format($item['stock_minimo'], 3, ',', '.') ?></td>
                        <td><?= moneda($item['costo_unitario']) ?></td>
                        <td class="small"><?= e($item['proveedor_nombre'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $item['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"
                                  style="cursor:pointer"
                                  onclick="toggleActivo(<?= $item['id'] ?>, this)"
                                  title="<?= $item['activo'] ? 'Clic para desactivar' : 'Clic para activar' ?>">
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
                        <!-- Nombre + Código -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="fNombre" name="nombre" class="form-control" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Código</label>
                            <input type="text" id="fCodigo" name="codigo" class="form-control" maxlength="30">
                        </div>
                        <!-- Categoría -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Categoría</label>
                            <select id="fCategoria" name="categoria_id" class="form-select">
                                <option value="">— Sin categoría —</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Tipo de gasto -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo de gasto</label>
                            <select id="fTipoGasto" name="tipo_gasto" class="form-select">
                                <option value="costo_variable">Gasto Variable</option>
                                <option value="costo_fijo">Gasto Fijo</option>
                                <option value="impuesto">Impuesto</option>
                                <option value="gasto">Gasto (no es inventario real)</option>
                            </select>
                            <div class="form-text">Cómo clasificar este ítem en el Estado de Resultados.</div>
                        </div>
                        <!-- Proveedor habitual -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Proveedor habitual</label>
                            <select id="fProveedor" name="proveedor_id" class="form-select">
                                <option value="">— —</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>"><?= e($prov['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Unidad de medida + Cantidad contenida -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unidad de medida</label>
                            <select id="fUnidad" name="unidad_medida_id" class="form-select">
                                <option value="">— —</option>
                                <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cantidad contenida</label>
                            <div class="input-group">
                                <input type="number" id="fCantContenida" name="cantidad_contenida"
                                       class="form-control" min="0" step="0.001" value=""
                                       placeholder="ej: 50">
                                <span class="input-group-text small text-muted" id="spanUnidadAbrev">—</span>
                            </div>
                            <div class="form-text">Cantidad por unidad de empaque (ej: 50 kg por bolsa).</div>
                        </div>
                        <!-- Ingrediente genérico: agrupa variantes de marca/paquete bajo un mismo costo -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">
                                Ingrediente genérico
                                <a href="<?= url('ingredientes-genericos') ?>" target="_blank" class="small ms-1">(gestionar genéricos)</a>
                            </label>
                            <select id="fGenerico" name="ingrediente_generico_id" class="form-select">
                                <option value="">— Ninguno (materia prima independiente) —</option>
                                <?php foreach ($genericos as $g): ?>
                                <option value="<?= $g['id'] ?>" data-unidad="<?= $g['unidad_medida_id'] ?>">
                                    <?= e($g['nombre']) ?> (<?= e($g['unidad'] ?? '—') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Si esta MP es una variante de marca/paquete de un ingrediente que ya comprás con otro nombre
                                (ej. "Jamón Delicato" y "Jamón Estancia" son ambos "Jamón cocido"), asignala acá para que
                                las recetas que usen el genérico siempre reflejen el costo actualizado, compre la marca que compre.
                                La unidad de medida de la MP debe coincidir con la del genérico.
                            </div>
                        </div>
                        <!-- Stock mínimo + Costo -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock mínimo</label>
                            <input type="number" id="fStockMin" name="stock_minimo" class="form-control" min="0" step="0.001" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" title="Precio de TODO el envase/paquete tal como lo comprás (ej. el balde de 6.400 gr entero), no el precio de una sola unidad/gramo suelto. El sistema lo divide solo por &quot;Cantidad contenida&quot; para guardar el costo por unidad base — no dividas vos a mano antes de cargarlo.">
                                Costo unitario ($) <span class="text-muted" style="cursor:help">ⓘ</span>
                            </label>
                            <input type="number" id="fCosto" name="costo_unitario" class="form-control" min="0" step="0.01" value="0"
                                title="Precio de TODO el envase/paquete (ej. el balde de 6.400 gr entero), no el de una unidad suelta. Se divide solo por &quot;Cantidad contenida&quot; al guardar.">
                            <div class="form-text fw-semibold text-info d-none" id="hintCostoPorUnidad"></div>
                            <button type="button" id="btnRecalcularCosto" class="btn btn-sm btn-outline-secondary mt-1 d-none"
                                title="Ignora lo cargado a mano y recalcula el costo repasando todo el historial real de compras de este ítem (mismo cálculo que usa el sistema al editar una factura).">
                                ↻ Recalcular desde compras
                            </button>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Desvío %</label>
                            <input type="number" id="fDesvioPct" name="desvio_pct" class="form-control" min="0" max="100" step="0.01" value="0">
                            <div class="form-text">
                                Pérdida no explicada (robo, rotura, consumo interno) — distinta de la merma de
                                receta. Se recalcula sola al hacer un conteo físico de inventario.
                            </div>
                        </div>
                        <!-- Estado (solo en edición) -->
                        <div id="rowActivo" class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Estado</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="fActivo" name="activo" value="1" checked>
                                <label class="form-check-label" for="fActivo">Activo</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Control de stock</label>
                            <div class="form-check form-switch mt-1">
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
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const URL_CREAR  = `${BASE_URL}/materias-primas/crear`;
const URL_EDIT   = `${BASE_URL}/materias-primas/actualizar`;
const URL_DEL    = `${BASE_URL}/materias-primas/eliminar`;
const URL_TOGGLE = `${BASE_URL}/materias-primas/toggle-activo`;
let   editMode   = false;

// Unidades: id → abreviatura para el label dinámico
const UNIDADES = <?= json_encode(array_column($unidades, 'abreviatura', 'id')) ?>;

document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') new DataTable('#tblItems');

    // Actualizar el label de abreviatura cuando cambia la unidad
    document.getElementById('fUnidad')?.addEventListener('change', function () {
        document.getElementById('spanUnidadAbrev').textContent = UNIDADES[this.value] || '—';
        actualizarCostoPorUnidad();
    });

    // Costo real por unidad = costo unitario ÷ cantidad contenida — se recalcula
    // en vivo para que quede claro que el "Costo unitario" cargado es el precio
    // de TODO el paquete (ej: $950,48 el paquete de 50), no el de una sola unidad.
    document.getElementById('fCosto')?.addEventListener('input', actualizarCostoPorUnidad);
    document.getElementById('fCantContenida')?.addEventListener('input', actualizarCostoPorUnidad);
    document.getElementById('fDesvioPct')?.addEventListener('input', actualizarCostoPorUnidad);
});

function actualizarCostoPorUnidad() {
    const hint = document.getElementById('hintCostoPorUnidad');
    const costo = parseFloat(document.getElementById('fCosto').value) || 0;
    const cantContenida = parseFloat(document.getElementById('fCantContenida').value) || 0;
    const desvioPct = parseFloat(document.getElementById('fDesvioPct').value) || 0;

    // Se muestra si hay conversión de unidad (paquete → unidad base) O si hay
    // desvío cargado — el desglose de desvío no depende de que exista esa conversión.
    if (costo <= 0 || (cantContenida <= 0 && desvioPct <= 0)) {
        hint.classList.add('d-none');
        return;
    }

    const unidad = document.getElementById('spanUnidadAbrev').textContent;
    const unidadTxt = unidad === '—' ? 'unidad' : unidad;
    const costoBase   = cantContenida > 0 ? costo / cantContenida : costo;
    const costoDesvio = costoBase * (desvioPct / 100);
    hint.textContent = desvioPct > 0
        ? `= ${formatMoneda(costoBase)} + ${formatMoneda(costoDesvio)} de desvío = ${formatMoneda(costoBase + costoDesvio)} por ${unidadTxt}`
        : `= ${formatMoneda(costoBase)} por ${unidadTxt}`;
    hint.classList.remove('d-none');
}

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

function abrirNuevo() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Nueva materia prima';
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('rowActivo').classList.add('d-none');
    document.getElementById('btnRecalcularCosto').classList.add('d-none');
    document.getElementById('spanUnidadAbrev').textContent = '—';
    actualizarCostoPorUnidad();
    limpiarError();
}

function abrirEditar(item) {
    editMode = true;
    document.getElementById('modalTitle').textContent  = 'Editar: ' + item.nombre;
    document.getElementById('itemId').value            = item.id;
    document.getElementById('fNombre').value           = item.nombre;
    document.getElementById('fCodigo').value           = item.codigo || '';
    document.getElementById('fCategoria').value        = item.categoria_id || '';
    document.getElementById('fTipoGasto').value        = item.tipo_gasto || 'costo_variable';
    document.getElementById('fUnidad').value           = item.unidad_medida_id || '';
    document.getElementById('fCantContenida').value    = item.cantidad_contenida > 0 ? item.cantidad_contenida : '';
    document.getElementById('fGenerico').value         = item.ingrediente_generico_id || '';
    document.getElementById('spanUnidadAbrev').textContent = UNIDADES[item.unidad_medida_id] || '—';
    document.getElementById('fStockMin').value         = item.stock_minimo;
    // item.costo_unitario viene de la base ya en precio POR UNIDAD BASE (lo que usan
    // recetas/compras). El campo "Costo unitario ($)" del formulario carga el precio del
    // ENVASE completo (ver actualizarCostoPorUnidad/recogerForm) — hay que reconstruirlo
    // multiplicando por cantidad_contenida, si no cada vez que se reabre para editar se
    // vería (y se volvería a guardar) el número chico por error.
    const cc = parseFloat(item.cantidad_contenida) > 0 ? parseFloat(item.cantidad_contenida) : 1;
    document.getElementById('fCosto').value            = (parseFloat(item.costo_unitario) || 0) * cc;
    document.getElementById('fDesvioPct').value        = item.desvio_pct || 0;
    document.getElementById('fProveedor').value        = item.proveedor_id || '';
    document.getElementById('fActivo').checked         = item.activo == 1;
    document.getElementById('fControlaStock').checked  = (item.controla_stock ?? 1) == 1;
    document.getElementById('rowActivo').classList.remove('d-none');
    document.getElementById('btnRecalcularCosto').classList.remove('d-none');
    document.getElementById('btnRecalcularCosto').dataset.id = item.id;
    actualizarCostoPorUnidad();
    limpiarError();
    _showModal('modalItem');
}

document.getElementById('btnRecalcularCosto').addEventListener('click', async function () {
    const id  = this.dataset.id;
    const cc  = parseFloat(document.getElementById('fCantContenida').value) > 0 ? parseFloat(document.getElementById('fCantContenida').value) : 1;
    const fd  = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    this.disabled = true;
    try {
        const r = await fetch(`${BASE_URL}/materias-primas/recalcular-costo`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            // Igual que al abrir para editar: el campo del formulario muestra precio del
            // envase completo, hay que reconstruirlo multiplicando por cantidad_contenida.
            document.getElementById('fCosto').value = (parseFloat(d.costo_unitario) || 0) * cc;
            actualizarCostoPorUnidad();
            showToast(d.mensaje);
        } else {
            showToast(d.error || 'No se pudo recalcular.', 'danger');
        }
    } catch {
        showToast('Error de conexión.', 'danger');
    } finally {
        this.disabled = false;
    }
});

document.getElementById('formItem').addEventListener('submit', async function(e) {
    e.preventDefault();
    limpiarError();

    // La unidad de la MP debe coincidir con la del genérico elegido (si eligió uno)
    const selGenerico = document.getElementById('fGenerico');
    const unidadGenerico = selGenerico.selectedOptions[0]?.dataset.unidad || '';
    if (selGenerico.value && unidadGenerico && unidadGenerico !== document.getElementById('fUnidad').value) {
        mostrarError('La unidad de medida de esta materia prima no coincide con la del ingrediente genérico elegido.');
        return;
    }

    document.getElementById('btnGuardar').disabled = true;
    const fd = new FormData(this);
    if (!fd.has('activo')) fd.set('activo', '0');
    if (!fd.has('controla_stock')) fd.set('controla_stock', '0');
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

async function toggleActivo(id, badge) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(URL_TOGGLE, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        const on = d.activo === 1;
        badge.textContent = on ? 'Activo' : 'Inactivo';
        badge.className   = 'badge ' + (on ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary');
        badge.title       = on ? 'Clic para desactivar' : 'Clic para activar';
        showToast(d.mensaje);
    } else {
        showToast(d.error, 'danger');
    }
}

function mostrarError(msg) { const e = document.getElementById('modalError'); e.textContent = msg; e.classList.remove('d-none'); }
function limpiarError()    { document.getElementById('modalError').classList.add('d-none'); }
</script>
<?php
$importUrl        = url('materias-primas/importar');
$importPreviewUrl = url('materias-primas/previsualizar');
$importPlantilla  = url('materias-primas/plantilla');
$importModulo     = 'Materias Primas';
$importColumnas   = ['nombre *', 'codigo', 'unidad', 'cantidad_contenida', 'categoria', 'proveedor', 'stock_actual', 'stock_minimo', 'costo_unitario', 'estado'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
