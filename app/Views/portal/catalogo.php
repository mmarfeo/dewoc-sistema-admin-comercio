<?php if (empty($negocioAbierto)): ?>
<!-- Cartel grande y persistente (no un modal que se cierra y se olvida) — el negocio
     sigue cerrado durante toda la visita, así que el aviso tiene que seguir a la vista
     todo el tiempo, no solo al entrar. -->
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 mb-4 py-3" role="alert">
    <div style="font-size:2.2rem;line-height:1">🔒</div>
    <div>
        <div class="fw-bold fs-5">El negocio está cerrado en este momento</div>
        <div class="small">Podés ver los productos y los gustos disponibles, pero <strong>no está disponible hacer pedidos</strong> hasta que reabramos.</div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <h1 class="h4 fw-bold mb-3">Catálogo</h1>
        <?php if (!$catalogo): ?>
        <div class="alert alert-info">Todavía no tenés productos habilitados. Consultá con quien te dio de alta.</div>
        <?php else: ?>
        <div class="d-flex gap-2 portal-catalogo-wrap">
            <div class="border rounded-3 overflow-auto flex-shrink-0 portal-categorias-panel" id="categoriasContainer" style="width:30%">
                <div class="list-group list-group-flush" id="categoriasLista"></div>
            </div>
            <div class="border rounded-3 overflow-auto flex-grow-1 portal-catalogo-panel" id="catalogoResultadosWrap">
                <div id="catalogoResultados" class="p-2 d-flex flex-wrap gap-2"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem">
            <div class="card-header border-0 bg-transparent fw-semibold">Tu pedido</div>
            <div class="card-body">
                <div id="carritoLista" class="mb-3"><p class="text-muted small mb-0">Todavía no agregaste nada.</p></div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas para el pedido</label>
                    <textarea id="notasPedido" class="form-control form-control-sm" rows="2" placeholder="Ej: sin crema, envolver por separado..."></textarea>
                </div>
                <div id="pedidoError" class="alert alert-danger d-none py-2 small"></div>
                <?php if (empty($negocioAbierto)): ?>
                <div class="alert alert-warning py-2 small mb-2">🔒 Negocio cerrado — no se pueden enviar pedidos por ahora.</div>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100" id="btnEnviarPedido" onclick="enviarPedido()" disabled>Enviar pedido</button>
            </div>
        </div>
    </div>
</div>

<!-- Aviso "negocio cerrado" (ningún turno de caja abierto ahora mismo) — se puede seguir
     viendo el catálogo y los gustos disponibles, pero NO enviar un pedido hasta que
     reabran (ver btnEnviarPedido/NEGOCIO_ABIERTO más abajo, y el chequeo de nuevo del
     lado del servidor en PortalController::crearPedido). -->
<?php if (empty($negocioAbierto)): ?>
<div class="modal fade" id="modalNegocioCerrado" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-center" style="border-top:6px solid #f0ad4e !important">
            <div class="modal-body p-4 bg-warning-subtle">
                <div class="mb-2" style="font-size:3.5rem;line-height:1">🔒</div>
                <h4 class="fw-bold mb-2">El negocio está cerrado en este momento</h4>
                <p class="mb-0">
                    Podés ver el catálogo y los gustos disponibles, pero <strong>no está disponible hacer
                    pedidos</strong> hasta que reabramos.
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-3 pb-4">
                <button type="button" class="btn btn-warning px-4 fw-semibold" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal de gustos/opciones — mismo patrón que ventas/pos.php -->
<div class="modal fade" id="modalOpcionesPortal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="opNombreProducto">—</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($negocioAbierto)): ?>
                <div class="alert alert-warning py-2 small mb-2">🔒 Negocio cerrado — podés ver los gustos disponibles, pero no seleccionarlos ni enviar pedidos.</div>
                <?php endif; ?>
                <div id="opGruposWrap"><div class="text-center text-muted py-4">Cargando…</div></div>
                <div id="opError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAgregarOpcionesPortal" onclick="confirmarOpcionesPortal()" <?= empty($negocioAbierto) ? 'disabled title="Negocio cerrado"' : '' ?>>Agregar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const CATALOGO   = <?= json_encode($catalogo, JSON_UNESCAPED_UNICODE) ?>;
// Criterio de stock configurable por el admin (ver PortalConfigModel, editable desde
// Clientes) — 'stock_minimo' (default): corta apenas entra en zona de reposición. 'stock_cero':
// recién corta sin stock físico, igual que el cajero en el POS.
const CRITERIO_STOCK = <?= json_encode($criterioStock ?? 'stock_minimo') ?>;
// Sin turno de caja abierto no hay quien reciba el pedido — se puede seguir viendo el
// catálogo, pero "Enviar pedido" queda deshabilitado (ver renderCarrito()). El backend
// (PortalController::crearPedido) vuelve a chequear esto igual, no se confía solo en el JS.
const NEGOCIO_ABIERTO = <?= $negocioAbierto ? 'true' : 'false' ?>;
let carrito = [];
let categoriaActivaId = null; // null = "Todos"

function esc(v) { const d = document.createElement('div'); d.textContent = v || ''; return d.innerHTML; }
function escJs(s) { return (s || '').toString().replace(/'/g, "\\'"); }
function formatMoneda(v) { return '$' + parseFloat(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); }

// "Mostrar pero deshabilitar" (decisión confirmada) — a diferencia del POS, acá no hay cajero
// que pueda confirmar "vender igual".
function productoSinStock(p) {
    if (!+p.controla_stock) return false;
    return CRITERIO_STOCK === 'stock_cero'
        ? parseFloat(p.stock_actual) <= 0
        : parseFloat(p.stock_actual) <= parseFloat(p.stock_minimo);
}

// ── Panel de categorías + grilla, mismo estilo visual que ventas/pos.php (categoriasLista +
// catalogoResultados, colores cat-color-N/prod-color-N cíclicos) — a diferencia del POS, acá
// el catálogo de este cliente ya viene completo del servidor (es chico, no hace falta pedirlo
// por categoría vía AJAX), así que se agrupa y filtra en el propio JS. ────────────────────────
function agruparCategorias() {
    const mapa = new Map();
    CATALOGO.forEach(p => {
        const id = p.categoria_id || 0;
        const nombre = p.categoria_nombre || 'Sin categoría';
        if (!mapa.has(id)) mapa.set(id, { id, nombre, total: 0 });
        mapa.get(id).total++;
    });
    return Array.from(mapa.values()).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
}

function renderCategorias() {
    const cont = document.getElementById('categoriasLista');
    cont.innerHTML = '';

    const btnTodos = document.createElement('button');
    btnTodos.type = 'button';
    btnTodos.className = 'list-group-item list-group-item-action py-2 small fw-semibold' + (categoriaActivaId === null ? ' active' : '');
    btnTodos.innerHTML = `Todos <span class="badge bg-secondary-soft text-secondary float-end">${CATALOGO.length}</span>`;
    btnTodos.addEventListener('click', () => seleccionarCategoria(null, btnTodos));
    cont.appendChild(btnTodos);

    agruparCategorias().forEach((c, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        const colorClase = 'cat-color-' + ((i % 8) + 1);
        btn.className = `list-group-item list-group-item-action py-2 small fw-semibold ${colorClase}` + (categoriaActivaId === c.id ? ' active' : '');
        btn.innerHTML = `${esc(c.nombre)} <span class="badge bg-secondary-soft text-secondary float-end">${c.total}</span>`;
        btn.addEventListener('click', () => seleccionarCategoria(c.id, btn));
        cont.appendChild(btn);
    });
}

function seleccionarCategoria(id, btnClickeado) {
    categoriaActivaId = id;
    document.querySelectorAll('#categoriasLista .list-group-item').forEach(b => b.classList.remove('active'));
    btnClickeado.classList.add('active');
    renderGrid();
}

function renderGrid() {
    const lista = categoriaActivaId === null ? CATALOGO : CATALOGO.filter(p => (p.categoria_id || 0) === categoriaActivaId);
    mostrarCatalogo(lista);
}

function mostrarCatalogo(lista) {
    const cont = document.getElementById('catalogoResultados');
    cont.innerHTML = '';
    if (!lista.length) {
        cont.innerHTML = '<div class="text-muted small text-center py-4 w-100">Sin productos en esta categoría.</div>';
        return;
    }
    lista.forEach((p, i) => {
        const sinStock = productoSinStock(p);
        const item = document.createElement('button');
        item.type = 'button';
        const colorClase = sinStock ? '' : ' prod-color-' + ((i % 8) + 1);
        item.className = 'btn catalogo-item text-start p-2' + (sinStock ? ' text-muted' : '') + colorClase;
        item.style.width = '31%';
        item.style.minHeight = '90px';
        const stockLinea = sinStock
            ? '<div class="small text-danger">Sin stock</div>'
            : (+p.controla_stock ? '<div class="small text-success">Disponible</div>' : '');
        item.innerHTML = `
            <div class="fw-semibold small catalogo-item-nombre">${esc(p.nombre)}</div>
            <div class="fw-bold text-primary small">${formatMoneda(p.precio_final ?? p.precio_venta)}</div>
            <div class="small text-muted">${esc(p.unidad || '')}</div>
            ${stockLinea}`;
        item.addEventListener('click', () => {
            if (sinStock) { showToast(`"${p.nombre}" no tiene stock disponible.`, 'warning'); return; }
            clickProducto(p);
        });
        cont.appendChild(item);
    });
}

function clickProducto(p) {
    if (+p.tiene_opciones) { abrirOpciones(p); return; } // igual se abre: se puede VER los gustos, ver renderGrupos()
    if (!NEGOCIO_ABIERTO) { showToast('El negocio está cerrado — no se pueden hacer pedidos por ahora.', 'warning'); return; }
    agregarSimple(p);
}

function agregarSimple(p) {
    const idx = carrito.findIndex(i => i.producto_id === p.id && !i.opciones);
    if (idx >= 0) carrito[idx].cantidad++;
    // precio_final: lo que paga el cliente (ver ClienteCatalogoModel::catalogoDeCliente).
    // Solo se usa para mostrar — el pedido viaja sin precio y el servidor lo resuelve.
    else carrito.push({ producto_id: p.id, nombre: p.nombre, cantidad: 1, precio_unitario: parseFloat(p.precio_final ?? p.precio_venta) });
    renderCarrito();
}

let _opProductoActual = null, _opGrupos = [], _opSeleccion = {};

async function abrirOpciones(p) {
    _opProductoActual = p; _opSeleccion = {};
    document.getElementById('opNombreProducto').textContent = p.nombre;
    document.getElementById('opGruposWrap').innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    document.getElementById('opError').classList.add('d-none');
    _showModal('modalOpcionesPortal');
    try {
        const r = await fetch(`${BASE_URL}/portal/opciones-producto?producto_id=${p.id}`);
        const d = await r.json();
        if (!d.ok) throw new Error();
        _opGrupos = d.grupos.map(g => ({
            ...g, id: +g.id, minimo_selecciones: +g.minimo_selecciones, maximo_selecciones: +g.maximo_selecciones,
            obligatorio: +g.obligatorio, permite_repetir: +g.permite_repetir,
            opciones: g.opciones.map(o => ({ ...o, id: +o.id, sin_stock: !!o.sin_stock })),
        }));
        renderGrupos();
    } catch {
        document.getElementById('opGruposWrap').innerHTML = '<div class="text-danger small text-center py-3">No se pudieron cargar los gustos.</div>';
    }
}

// Mismas tarjetas de color (catalogo-item + prod-color-N cíclico) que ventas/pos.php en
// vez de la lista de checkboxes/radios nativos de antes — ver CSS #opGruposWrap más abajo,
// que reusa las reglas ya portadas para #catalogoResultados. A diferencia del POS, acá no
// hay confirmación "vender igual sin stock": una opción sin stock queda deshabilitada sin
// vuelta atrás, porque no hay cajero del lado del cliente que pueda decidir eso.
// Con el negocio cerrado (!NEGOCIO_ABIERTO) todas las tarjetas quedan deshabilitadas —
// se puede VER el gusto pero no seleccionarlo (queda atenuado por el disabled de Bootstrap,
// sin usar la clase opcion-agotada, que es específica de "sin stock").
function renderGrupos() {
    document.getElementById('opGruposWrap').innerHTML = _opGrupos.map(g => {
        _opSeleccion[g.id] = _opSeleccion[g.id] || [];
        const tipoTexto = g.tipo_seleccion === 'unica' ? 'Elegí 1' : `Elegí entre ${g.minimo_selecciones} y ${g.maximo_selecciones}`;
        const tarjetas = g.opciones.map((o, i) => {
            const colorClase   = o.sin_stock ? '' : 'prod-color-' + ((i % 8) + 1);
            const sinStockLine = o.sin_stock ? '<div class="small">Sin stock</div>' : '';
            const bloqueada    = o.sin_stock || !NEGOCIO_ABIERTO;

            if (g.tipo_seleccion === 'unica') {
                const elegida = _opSeleccion[g.id].some(s => s.opcion_id === o.id);
                return `<button type="button"
                        class="btn catalogo-item text-start p-2 ${colorClase}${o.sin_stock ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                        style="width:31%;min-height:70px" ${bloqueada ? 'disabled' : ''}
                        onclick="opSeleccionarUnica(${g.id},${o.id},'${escJs(o.nombre)}')">
                    <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                    ${elegida ? '<div class="small">✓ Elegido</div>' : sinStockLine}
                </button>`;
            }

            const sel  = _opSeleccion[g.id].find(s => s.opcion_id === o.id);
            const cant = sel ? sel.cantidad_elegida : 0;

            if (g.permite_repetir) {
                return `<div class="catalogo-item p-2 ${colorClase}${o.sin_stock ? ' text-muted opcion-agotada' : ''}${cant > 0 ? ' opcion-elegida' : ''}" style="width:31%;min-height:70px">
                    <div class="fw-semibold small catalogo-item-nombre mb-1">${esc(o.nombre)}</div>
                    ${sinStockLine}
                    <div class="d-flex align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" ${bloqueada ? 'disabled' : ''} onclick="opCambiarCantidad(${g.id},${o.id},'${escJs(o.nombre)}',-1)">−</button>
                        <span class="fw-bold small">${cant}</span>
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" ${bloqueada ? 'disabled' : ''} onclick="opCambiarCantidad(${g.id},${o.id},'${escJs(o.nombre)}',1)">+</button>
                    </div>
                </div>`;
            }

            const elegida = cant > 0;
            return `<button type="button"
                    class="btn catalogo-item text-start p-2 ${colorClase}${o.sin_stock ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                    style="width:31%;min-height:70px" ${bloqueada ? 'disabled' : ''}
                    onclick="opToggleMultiple(${g.id},${o.id},'${escJs(o.nombre)}',${!elegida})">
                <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                ${elegida ? '<div class="small">✓ Elegido</div>' : sinStockLine}
            </button>`;
        }).join('');
        return `<div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">${esc(g.nombre)} ${g.obligatorio ? '' : '<span class="text-muted small fw-normal">(opcional)</span>'}</h6>
                <span class="small text-muted">${tipoTexto}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">${tarjetas}</div>
        </div>`;
    }).join('');
}

function opSeleccionarUnica(grupoId, opcionId, nombre) {
    _opSeleccion[grupoId] = [{ opcion_id: opcionId, nombre, cantidad_elegida: 1 }];
    renderGrupos();
}
function opToggleMultiple(grupoId, opcionId, nombre, checked) {
    const grupo = _opGrupos.find(g => g.id === grupoId);
    let lista = _opSeleccion[grupoId] || [];
    if (checked) {
        if (lista.length >= grupo.maximo_selecciones) {
            showToast(`"${grupo.nombre}": como máximo podés elegir ${grupo.maximo_selecciones}.`, 'warning');
            renderGrupos();
            return;
        }
        lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else {
        lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opSeleccion[grupoId] = lista;
    renderGrupos();
}
function opCambiarCantidad(grupoId, opcionId, nombre, delta) {
    const grupo = _opGrupos.find(g => g.id === grupoId);
    let lista = _opSeleccion[grupoId] || [];
    const totalActual = lista.reduce((s, x) => s + x.cantidad_elegida, 0);
    let sel = lista.find(s => s.opcion_id === opcionId);
    if (delta > 0) {
        if (totalActual >= grupo.maximo_selecciones) return;
        if (sel) sel.cantidad_elegida++; else lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else if (sel) {
        sel.cantidad_elegida--;
        if (sel.cantidad_elegida <= 0) lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opSeleccion[grupoId] = lista;
    renderGrupos();
}

function confirmarOpcionesPortal() {
    for (const g of _opGrupos) {
        const elegidos = (_opSeleccion[g.id] || []).reduce((s, x) => s + x.cantidad_elegida, 0);
        if (g.obligatorio && elegidos < g.minimo_selecciones) {
            const el = document.getElementById('opError');
            el.textContent = `"${g.nombre}": elegí al menos ${g.minimo_selecciones}.`;
            el.classList.remove('d-none');
            return;
        }
    }
    const opciones = [];
    Object.values(_opSeleccion).forEach(lista => lista.forEach(s => opciones.push({ ...s })));
    carrito.push({
        producto_id: _opProductoActual.id, nombre: _opProductoActual.nombre, cantidad: 1,
        precio_unitario: parseFloat(_opProductoActual.precio_final ?? _opProductoActual.precio_venta), opciones,
        opciones_resumen: opciones.map(o => (o.cantidad_elegida > 1 ? o.cantidad_elegida + 'x ' : '') + o.nombre).join(', '),
    });
    renderCarrito();
    _hideModal('modalOpcionesPortal');
}

function renderCarrito() {
    const wrap = document.getElementById('carritoLista');
    const btn  = document.getElementById('btnEnviarPedido');
    if (!carrito.length) {
        wrap.innerHTML = '<p class="text-muted small mb-0">Todavía no agregaste nada.</p>';
        btn.disabled = true;
        return;
    }
    let total = 0;
    wrap.innerHTML = carrito.map((it, idx) => {
        total += it.cantidad * it.precio_unitario;
        return `<div class="d-flex justify-content-between align-items-start border-bottom py-2 small">
            <div>
                <div class="fw-semibold">${esc(it.nombre)}</div>
                ${it.opciones_resumen ? `<div class="text-muted">${esc(it.opciones_resumen)}</div>` : ''}
                <div class="text-muted">${it.cantidad} x ${formatMoneda(it.precio_unitario)}</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="quitarDelCarrito(${idx})">✕</button>
        </div>`;
    }).join('') + `<div class="d-flex justify-content-between fw-bold mt-2 pt-2">
        <span>Total</span><span>${formatMoneda(total)}</span>
    </div>`;
    btn.disabled = !NEGOCIO_ABIERTO;
}

function quitarDelCarrito(idx) { carrito.splice(idx, 1); renderCarrito(); }

async function enviarPedido() {
    const errEl = document.getElementById('pedidoError');
    errEl.classList.add('d-none');
    document.getElementById('btnEnviarPedido').disabled = true;

    const fd = new FormData();
    fd.append('items_json', JSON.stringify(carrito));
    fd.append('notas', document.getElementById('notasPedido').value);
    fd.append('_csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(`${BASE_URL}/portal/pedido/crear`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/portal/pedidos`; }, 900);
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
            document.getElementById('btnEnviarPedido').disabled = false;
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
        document.getElementById('btnEnviarPedido').disabled = false;
    }
}

// Mismo helper que ya usa ventas/pos.php — Dashly bundlea Bootstrap sin exponer window.bootstrap.
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
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    let btn = el.querySelector('[data-bs-dismiss="modal"]');
    if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-dismiss', 'modal');
        el.appendChild(btn);
    }
    btn.click();
}

<?php if ($catalogo): ?>
renderCategorias();
renderGrid();
<?php endif; ?>
<?php if (empty($negocioAbierto)): ?>
_showModal('modalNegocioCerrado');
<?php endif; ?>
</script>

<?php if ($catalogo): ?>
<style>
/* Mismo sistema visual pastel que ventas/pos.php (categoriasLista/catalogoResultados,
   cat-color-N/prod-color-N cíclicos) — portado tal cual para que el portal se sienta
   consistente con el resto del sistema en vez de la grilla de tarjetas lisas de antes. */
.portal-categorias-panel { background-color: #f2edfa; }
.portal-catalogo-panel   { background-color: #f4f5f7; }
:root[data-theme="dark"] .portal-categorias-panel { background-color: #2a2440; }
:root[data-theme="dark"] .portal-catalogo-panel   { background-color: #26292d; }

.portal-catalogo-wrap { height: 70vh; min-height: 420px; }

.catalogo-item {
    background-color: #eef0f3;
    border: 1.5px solid #adb5bd;
}
.catalogo-item .catalogo-item-nombre { color: #000; }
.catalogo-item.text-muted .catalogo-item-nombre { color: inherit; }
:root[data-theme="dark"] .catalogo-item {
    background-color: #2b3035;
    border-color: #495057;
}
:root[data-theme="dark"] .catalogo-item .catalogo-item-nombre { color: #e9ecef; }
:root[data-theme="dark"] .catalogo-item.text-muted .catalogo-item-nombre { color: inherit; }

.prod-color-1 { --prod-bg:#fbdce6; --prod-vivo:#e64980; --prod-text:#7a1f42; }
.prod-color-2 { --prod-bg:#fde3cf; --prod-vivo:#e8590c; --prod-text:#7a3d0f; }
.prod-color-3 { --prod-bg:#fdf3c7; --prod-vivo:#d4a017; --prod-text:#6b5a12; }
.prod-color-4 { --prod-bg:#d3f5dc; --prod-vivo:#2f9e44; --prod-text:#1f5c22; }
.prod-color-5 { --prod-bg:#cdf5f0; --prod-vivo:#0ca678; --prod-text:#0b5548; }
.prod-color-6 { --prod-bg:#d3ecfb; --prod-vivo:#1971c2; --prod-text:#123a52; }
.prod-color-7 { --prod-bg:#e5dbf8; --prod-vivo:#7048e8; --prod-text:#4b2e73; }
.prod-color-8 { --prod-bg:#f6d6f0; --prod-vivo:#a61e4d; --prod-text:#6b1a3d; }
#catalogoResultados .btn.catalogo-item[class*="prod-color-"] {
    background-color: var(--prod-bg);
    border: 1.5px solid var(--prod-vivo);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"] .catalogo-item-nombre {
    color: var(--prod-text);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:hover {
    background-color: var(--prod-vivo);
    border-color: var(--prod-vivo);
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 .35rem .9rem rgba(0,0,0,.28);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:hover .catalogo-item-nombre,
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:hover .text-muted {
    color: #fff !important;
}
#catalogoResultados .btn {
    transition: transform .1s ease, box-shadow .15s ease, border-color .15s ease;
}
#catalogoResultados .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 .25rem .75rem rgba(0,0,0,.25);
    border-color: var(--bs-primary);
    z-index: 1;
}

/* Tarjetas de gustos/opciones dentro del modal (renderGrupos()) — mismas reglas que
   #opcionesGruposWrap en ventas/pos.php, para que se vea igual que en el POS. */
#opGruposWrap .catalogo-item[class*="prod-color-"] {
    background-color: var(--prod-bg);
    border: 1.5px solid var(--prod-vivo);
}
#opGruposWrap .catalogo-item[class*="prod-color-"] .catalogo-item-nombre {
    color: var(--prod-text);
}
#opGruposWrap .btn.catalogo-item[class*="prod-color-"]:not(.opcion-elegida):hover {
    background-color: var(--prod-vivo);
    border-color: var(--prod-vivo);
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 .35rem .9rem rgba(0,0,0,.28);
}
#opGruposWrap .btn.catalogo-item[class*="prod-color-"]:not(.opcion-elegida):hover .catalogo-item-nombre {
    color: #fff !important;
}
#opGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] {
    background-color: var(--prod-vivo);
    border-color: var(--prod-vivo);
}
#opGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] .catalogo-item-nombre,
#opGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] .small {
    color: #fff !important;
}
#opGruposWrap .catalogo-item.opcion-agotada {
    background-color: #ced4da;
    border-color: #868e96;
}
:root[data-theme="dark"] #opGruposWrap .catalogo-item.opcion-agotada {
    background-color: #16181a;
    border-color: #343a40;
}

#categoriasLista { border: 0; background: transparent; }
#categoriasLista .list-group-item {
    transition: background-color .15s ease, color .15s ease, transform .1s ease;
    cursor: pointer;
    border: 0 !important;
    border-radius: .6rem !important;
    margin: .25rem .4rem;
    padding: .5rem .65rem;
}
#categoriasLista .list-group-item:hover {
    background-color: var(--bs-primary);
    color: #fff;
    transform: translateX(2px);
}
#categoriasLista .list-group-item:hover .badge {
    background-color: rgba(255,255,255,.25) !important;
    color: #fff !important;
}
#categoriasLista .list-group-item.active { border-radius: .6rem !important; }
#categoriasLista .list-group-item.active:hover {
    background-color: var(--bs-primary);
    filter: brightness(1.1);
}
.cat-color-1 { --cat-bg:#fbdce6; --cat-vivo:#e64980; --cat-text:#7a1f42; }
.cat-color-2 { --cat-bg:#fde3cf; --cat-vivo:#e8590c; --cat-text:#7a3d0f; }
.cat-color-3 { --cat-bg:#fdf3c7; --cat-vivo:#d4a017; --cat-text:#6b5a12; }
.cat-color-4 { --cat-bg:#d3f5dc; --cat-vivo:#2f9e44; --cat-text:#1f5c22; }
.cat-color-5 { --cat-bg:#cdf5f0; --cat-vivo:#0ca678; --cat-text:#0b5548; }
.cat-color-6 { --cat-bg:#d3ecfb; --cat-vivo:#1971c2; --cat-text:#123a52; }
.cat-color-7 { --cat-bg:#e5dbf8; --cat-vivo:#7048e8; --cat-text:#4b2e73; }
.cat-color-8 { --cat-bg:#f6d6f0; --cat-vivo:#a61e4d; --cat-text:#6b1a3d; }
#categoriasLista .list-group-item[class*="cat-color-"] {
    background-color: var(--cat-bg);
    color: #000;
}
:root[data-theme="dark"] #categoriasLista .list-group-item[class*="cat-color-"] { color: #dee2e6; }
#categoriasLista .list-group-item[class*="cat-color-"]:hover {
    background-color: var(--cat-vivo);
    color: #fff;
    transform: translateX(2px) scale(1.03);
    box-shadow: 0 .2rem .5rem rgba(0,0,0,.2);
}
#categoriasLista .list-group-item[class*="cat-color-"].active,
#categoriasLista .list-group-item[class*="cat-color-"].active:hover {
    background-color: var(--cat-vivo);
    color: #fff;
    filter: none;
    box-shadow: 0 0 0 2px var(--cat-vivo), 0 .2rem .5rem rgba(0,0,0,.25);
}
</style>
<?php endif; ?>
