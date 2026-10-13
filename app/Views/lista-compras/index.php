<?php
$totalSugeridos  = array_sum(array_map('count', $sugeridos));
$totalPendientes = array_sum(array_map('count', $pendientes));
$totalProduccion = count($produccion);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Lista de Compras</h1>
        <p class="text-muted small mb-0">
            Las recomendaciones se arman solas todos los días según el consumo real (días de stock restante).
            Lo que confirmás (o agregás a mano) pasa a la Orden de Compra — lo que realmente se va a comprar.
        </p>
    </div>
</div>

<!-- ── Navegación entre secciones ───────────────────────────────────────────── -->
<div class="btn-group btn-group-sm mb-3" role="group">
    <button type="button" class="btn btn-primary" id="btnTabSugeridos" onclick="mostrarSeccionLista('sugeridos')">
        🔎 Recomendaciones de compra<?= $totalSugeridos ? ' (' . $totalSugeridos . ')' : '' ?>
    </button>
    <button type="button" class="btn btn-outline-primary" id="btnTabPendientes" onclick="mostrarSeccionLista('pendientes')">
        🧾 Órdenes de Compra<?= $totalPendientes ? ' (' . $totalPendientes . ')' : '' ?>
    </button>
    <button type="button" class="btn btn-outline-primary" id="btnTabProduccion" onclick="mostrarSeccionLista('produccion')">
        🏭 Producción<?= $totalProduccion ? ' (' . $totalProduccion . ')' : '' ?>
    </button>
</div>

<!-- ── Recomendaciones de compra ──────────────────────────────────────────── -->
<div id="seccionSugeridos">
<?php if ($totalSugeridos === 0): ?>
<div class="card border-0 mb-4">
    <div class="card-body text-center text-muted py-4">
        No hay recomendaciones pendientes de confirmar por ahora.
    </div>
</div>
<?php else: ?>
<?php foreach ($sugeridos as $proveedor => $items): ?>
<div class="card border-0 mb-3">
    <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0">🏪 <?= e($proveedor) ?></h6>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary btn-descartar-lote" data-prov="<?= e($proveedor) ?>">Descartar seleccionados</button>
            <button class="btn btn-sm btn-success btn-confirmar-lote" data-prov="<?= e($proveedor) ?>">Agregar a orden de compra</button>
        </div>
    </div>
    <div class="card-body pt-2">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr data-grupo="<?= e($proveedor) ?>">
                    <td style="width:30px"><input type="checkbox" class="form-check-input chk-sugerido" value="<?= (int) $it['id'] ?>" checked></td>
                    <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                    <td class="text-muted small">
                        <?= $it['dias_stock_restante'] !== null ? 'quedan ~' . number_format((float) $it['dias_stock_restante'], 1, ',', '.') . ' días' : 'stock bajo el mínimo' ?>
                    </td>
                    <td class="text-end text-muted small">stock: <?= $it['stock_actual'] !== null ? number_format((float) $it['stock_actual'], 2, ',', '.') : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ── Órdenes de Compra ───────────────────────────────────────────────────── -->
<div id="seccionPendientes" style="display:none">
<div class="d-flex align-items-center justify-content-end mb-2">
    <button class="btn btn-sm btn-primary" id="btnAbrirAgregar">+ Agregar a la orden</button>
</div>
<?php if ($totalPendientes === 0): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-4">
        Todavía no hay nada cargado en la orden de compra — confirmá una recomendación de arriba o agregá algo a mano.
    </div>
</div>
<?php else: ?>
<?php foreach ($pendientes as $proveedor => $items): ?>
<div class="card border-0 mb-3">
    <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0">🏪 <?= e($proveedor) ?></h6>
        <span class="text-muted small"><?= count($items) ?> ítem<?= count($items) > 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body pt-2">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr class="fila-en-progreso">
                    <td style="width:30px">
                        <input type="checkbox" class="form-check-input chk-en-progreso" data-id="<?= (int) $it['id'] ?>" title="Ya lo estoy comprando">
                    </td>
                    <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                    <td class="text-muted small">
                        <?= $it['origen'] === 'manual' ? 'Agregado a mano' : ($it['dias_stock_restante'] !== null ? 'quedan ~' . number_format((float) $it['dias_stock_restante'], 1, ',', '.') . ' días' : 'stock bajo el mínimo') ?>
                    </td>
                    <td class="text-end text-muted small">stock: <?= $it['stock_actual'] !== null ? number_format((float) $it['stock_actual'], 2, ',', '.') : '—' ?></td>
                    <td class="text-end" style="width:220px">
                        <button class="btn btn-sm btn-outline-secondary btn-quitar" data-id="<?= (int) $it['id'] ?>">Quitar</button>
                        <button class="btn btn-sm btn-primary btn-comprado" data-id="<?= (int) $it['id'] ?>">✅ Comprado</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ── Producción ────────────────────────────────────────────────────────────── -->
<div id="seccionProduccion" style="display:none">
<?php if ($totalProduccion === 0): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-4">
        No hay productos elaborados por debajo del stock recomendado por ahora.
    </div>
</div>
<?php else: ?>
<div class="card border-0">
    <div class="card-header bg-white border-0 pt-3 pb-0">
        <p class="text-muted small mb-0">Productos que se elaboran acá adentro (con receta propia) y están por agotarse — no se compran, se producen.</p>
    </div>
    <div class="card-body pt-2">
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($produccion as $it): ?>
                <tr class="fila-en-progreso">
                    <td style="width:30px">
                        <input type="checkbox" class="form-check-input chk-produccion-en-progreso" data-id="<?= (int) $it['producto_id'] ?>" title="Ya lo estoy produciendo">
                    </td>
                    <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                    <td class="text-muted small">
                        <?= $it['dias_stock_restante'] !== null ? 'quedan ~' . number_format((float) $it['dias_stock_restante'], 1, ',', '.') . ' días' : 'stock bajo el mínimo' ?>
                    </td>
                    <td class="text-end text-muted small">stock: <?= number_format((float) $it['stock_actual'], 0, ',', '.') . ($it['unidad'] ? ' ' . e($it['unidad']) : '') ?></td>
                    <td class="text-end" style="width:140px">
                        <a href="<?= url('recetas/produccion?id=' . $it['receta_id']) ?>" class="btn btn-sm btn-primary">🏭 Producir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>

<style>
/* Checkbox "ya lo estoy comprando/produciendo" — pinta la fila para no perderla de vista
   ni repetir sin querer el mismo pedido/lote si alguien vuelve a mirar la lista después. */
.fila-en-progreso.en-progreso { background-color: var(--bs-info-bg-subtle, #cff4fc); }
.fila-en-progreso.en-progreso td { color: inherit; }
</style>

<!-- ── Modal: agregar a la orden de compra a mano ───────────────────────────── -->
<div class="modal fade" id="modalAgregarOrden" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Agregar a la orden de compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 position-relative">
                    <label class="form-label small fw-semibold mb-1">Producto / Materia prima / Insumo</label>
                    <input type="text" id="agItemBuscador" class="form-control form-control-sm" placeholder="Escribí para buscar..." autocomplete="off">
                    <input type="hidden" id="agItemTipo">
                    <input type="hidden" id="agItemId">
                    <div id="agItemDropdown" class="list-group position-absolute w-100 shadow-sm" style="z-index:1060; max-height:220px; overflow-y:auto; display:none;"></div>
                    <div id="agItemSeleccionado" class="small text-success mt-1" style="display:none;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1">Proveedor</label>
                    <select id="agProveedor" class="form-select form-select-sm">
                        <option value="">— Automático (proveedor habitual del ítem) —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-1">
                    <label class="form-label small fw-semibold mb-1">Notas (opcional)</label>
                    <input type="text" id="agNotas" class="form-control form-control-sm" placeholder="Ej: pedir la marca X">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAgregar" disabled>Agregar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';

// ── Navegación entre "Recomendaciones de compra", "Órdenes de Compra" y "Producción" ──
// El hash de la URL recuerda la pestaña activa entre los location.reload() que ya
// hacían confirmar/descartar/agregar — sin esto, cualquiera de esas acciones te
// devolvía siempre a "Recomendaciones" aunque estuvieras parado en otra pestaña.
const SECCIONES_LISTA = ['sugeridos', 'pendientes', 'produccion'];
function mostrarSeccionLista(seccion) {
    if (!SECCIONES_LISTA.includes(seccion)) seccion = 'sugeridos';

    SECCIONES_LISTA.forEach(s => {
        const idSeccion = 'seccion' + s.charAt(0).toUpperCase() + s.slice(1);
        const idBoton    = 'btnTab' + s.charAt(0).toUpperCase() + s.slice(1);
        document.getElementById(idSeccion).style.display = s === seccion ? '' : 'none';
        document.getElementById(idBoton).classList.toggle('btn-primary', s === seccion);
        document.getElementById(idBoton).classList.toggle('btn-outline-primary', s !== seccion);
    });

    history.replaceState(null, '', '#' + seccion);
}

if (SECCIONES_LISTA.includes(location.hash.slice(1))) mostrarSeccionLista(location.hash.slice(1));

async function postAccion(url, params) {
    const fd = new FormData();
    for (const k in params) fd.append(k, params[k]);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}${url}`, { method: 'POST', body: fd });
    return r.json();
}

document.querySelectorAll('.btn-comprado').forEach(btn => btn.addEventListener('click', async () => {
    btn.disabled = true;
    const d = await postAccion('/lista-compras/marcar-comprado', { id: btn.dataset.id });
    if (d.ok) btn.closest('tr').remove(); else { btn.disabled = false; alert('No se pudo marcar como comprado.'); }
}));

document.querySelectorAll('.btn-quitar').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('¿Quitar este ítem de la orden de compra?')) return;
    btn.disabled = true;
    const d = await postAccion('/lista-compras/quitar', { id: btn.dataset.id });
    if (d.ok) btn.closest('tr').remove(); else { btn.disabled = false; alert('No se pudo quitar.'); }
}));

document.querySelectorAll('.btn-confirmar-lote').forEach(btn => btn.addEventListener('click', async () => {
    const filas = document.querySelectorAll(`tr[data-grupo="${CSS.escape(btn.dataset.prov)}"] .chk-sugerido:checked`);
    if (!filas.length) return;
    btn.disabled = true;
    for (const chk of filas) {
        const d = await postAccion('/lista-compras/confirmar-sugerido', { id: chk.value });
        if (d.ok) chk.closest('tr').remove();
    }
    location.reload();
}));

document.querySelectorAll('.btn-descartar-lote').forEach(btn => btn.addEventListener('click', async () => {
    const filas = document.querySelectorAll(`tr[data-grupo="${CSS.escape(btn.dataset.prov)}"] .chk-sugerido:checked`);
    if (!filas.length) return;
    if (!confirm('¿Descartar los ítems seleccionados? No se van a volver a sugerir solos.')) return;
    btn.disabled = true;
    for (const chk of filas) {
        const d = await postAccion('/lista-compras/descartar-sugerido', { id: chk.value });
        if (d.ok) chk.closest('tr').remove();
    }
    location.reload();
}));

// ── Modal "Agregar a la orden de compra" ─────────────────────────────────────
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); return; }
    // Dashly bundlea Bootstrap sin exponer window.bootstrap — mismo truco que pagos-proveedores/index.php.
    let trig = document.getElementById('_trigger_' + id);
    if (!trig) {
        trig = document.createElement('button');
        trig.id = '_trigger_' + id; trig.type = 'button'; trig.hidden = true;
        trig.setAttribute('data-bs-toggle', 'modal'); trig.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(trig);
    }
    trig.click();
}

document.getElementById('btnAbrirAgregar').addEventListener('click', () => {
    document.getElementById('agItemBuscador').value = '';
    document.getElementById('agItemTipo').value = '';
    document.getElementById('agItemId').value = '';
    document.getElementById('agItemSeleccionado').style.display = 'none';
    document.getElementById('agProveedor').value = '';
    document.getElementById('agNotas').value = '';
    document.getElementById('btnConfirmarAgregar').disabled = true;
    _showModal('modalAgregarOrden');
});

let _agDebounce = null;
const TIPOS_LABEL = { materia_prima: 'MP', insumo: 'Insumo', producto: 'Producto' };

document.getElementById('agItemBuscador').addEventListener('input', function () {
    clearTimeout(_agDebounce);
    document.getElementById('agItemTipo').value = '';
    document.getElementById('agItemId').value = '';
    document.getElementById('btnConfirmarAgregar').disabled = true;
    const q = this.value.trim();
    const dd = document.getElementById('agItemDropdown');
    if (q.length < 2) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
    _agDebounce = setTimeout(async () => {
        try {
            const r = await fetch(`${BASE_URL}/compras/buscar-items?q=${encodeURIComponent(q)}`);
            const items = await r.json();
            dd.innerHTML = '';
            if (!Array.isArray(items) || !items.length) {
                dd.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                dd.style.display = 'block';
                return;
            }
            items.forEach(it => {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action small';
                a.innerHTML = `<span class="badge bg-secondary-soft text-secondary me-1">${TIPOS_LABEL[it.tipo] || it.tipo}</span> ${it.nombre}`;
                a.addEventListener('click', () => {
                    document.getElementById('agItemBuscador').value = it.nombre;
                    document.getElementById('agItemTipo').value = it.tipo;
                    document.getElementById('agItemId').value = it.id;
                    document.getElementById('agItemSeleccionado').textContent = `Seleccionado: ${it.nombre}`;
                    document.getElementById('agItemSeleccionado').style.display = 'block';
                    document.getElementById('btnConfirmarAgregar').disabled = false;
                    dd.style.display = 'none';
                });
                dd.appendChild(a);
            });
            dd.style.display = 'block';
        } catch {
            dd.style.display = 'none';
        }
    }, 250);
});

document.getElementById('btnConfirmarAgregar').addEventListener('click', async function () {
    const tipoItem = document.getElementById('agItemTipo').value;
    const itemId   = document.getElementById('agItemId').value;
    if (!tipoItem || !itemId) return;
    this.disabled = true;
    const d = await postAccion('/lista-compras/agregar', {
        tipo_item: tipoItem,
        item_id: itemId,
        proveedor_id: document.getElementById('agProveedor').value,
        notas: document.getElementById('agNotas').value,
    });
    if (d.ok) {
        location.reload();
    } else {
        this.disabled = false;
        alert(d.error || 'No se pudo agregar.');
    }
});

// ── Checkbox "ya lo estoy comprando" (Órdenes de Compra) / "ya lo estoy produciendo" (Producción) ──
// Puramente visual: pinta la fila y se recuerda en localStorage de ESTE navegador, no en
// el servidor — no hay tabla ni endpoint nuevo para esto a propósito.
const LS_KEY_EN_PROGRESO = 'listaCompras_enProgreso';

function _leerEnProgreso() {
    try { return JSON.parse(localStorage.getItem(LS_KEY_EN_PROGRESO) || '{}'); }
    catch { return {}; }
}
function _guardarEnProgreso(data) {
    try { localStorage.setItem(LS_KEY_EN_PROGRESO, JSON.stringify(data)); } catch {}
}

function _wireCheckEnProgreso(selector, grupo) {
    const marcados = new Set(_leerEnProgreso()[grupo] || []);

    document.querySelectorAll(selector).forEach(chk => {
        const id = chk.dataset.id;
        if (marcados.has(id)) {
            chk.checked = true;
            chk.closest('tr').classList.add('en-progreso');
        }
        chk.addEventListener('change', () => {
            chk.closest('tr').classList.toggle('en-progreso', chk.checked);
            const data = _leerEnProgreso();
            const set  = new Set(data[grupo] || []);
            chk.checked ? set.add(id) : set.delete(id);
            data[grupo] = Array.from(set);
            _guardarEnProgreso(data);
        });
    });
}
_wireCheckEnProgreso('.chk-en-progreso', 'pendiente');
_wireCheckEnProgreso('.chk-produccion-en-progreso', 'produccion');
</script>
