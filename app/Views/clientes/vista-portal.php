<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Vista previa del portal — <?= e($cliente['nombre']) ?></h1>
        <p class="text-muted small mb-0">
            Así ve <?= e($cliente['nombre']) ?> su catálogo ahora mismo en <code>/portal</code> (mismo precio,
            mismo criterio de disponibilidad de stock). Es de solo lectura — no se puede seleccionar nada
            ni armar un pedido desde acá.
        </p>
    </div>
    <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary btn-sm">← Volver a Clientes</a>
</div>

<?php if (empty($cliente['portal_activo']) || empty($cliente['password_hash'])): ?>
<div class="alert alert-info-soft border-0 small">
    Este cliente todavía no tiene el acceso al portal habilitado (Portal Activo / contraseña, en su ficha).
    Igualmente podés ver acá qué vería si lo activaras.
</div>
<?php endif; ?>

<?php if (empty($negocioAbierto)): ?>
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 mb-3 py-2">
    <div style="font-size:1.6rem;line-height:1">🔒</div>
    <div class="small">
        <strong>El negocio figura cerrado</strong> (sin turno de caja abierto) — en el portal real, este
        cliente vería el aviso de "negocio cerrado" y no podría enviar un pedido.
    </div>
</div>
<?php endif; ?>

<?php if (!$catalogo): ?>
<div class="alert alert-info">Este cliente todavía no tiene productos habilitados en su catálogo (Clientes → editar → Catálogo del Portal).</div>
<?php else: ?>
<div class="d-flex gap-2 portal-catalogo-wrap">
    <div class="border rounded-3 overflow-auto flex-shrink-0 portal-categorias-panel" id="categoriasContainer" style="width:25%">
        <div class="list-group list-group-flush" id="categoriasLista"></div>
    </div>
    <div class="border rounded-3 overflow-auto flex-grow-1 portal-catalogo-panel" id="catalogoResultadosWrap">
        <div id="catalogoResultados" class="p-2 d-flex flex-wrap gap-2"></div>
    </div>
</div>
<?php endif; ?>

<!-- Modal de gustos/opciones — SOLO LECTURA (sin selección, sin botón de agregar; esto es
     una auditoría de lo que el cliente ve, no una forma de armar un pedido desde staff). -->
<div class="modal fade" id="modalOpcionesPortal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="opNombreProducto">—</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="opGruposWrap"><div class="text-center text-muted py-4">Cargando…</div></div>
                <div id="opError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CATALOGO   = <?= json_encode($catalogo, JSON_UNESCAPED_UNICODE) ?>;
const CRITERIO_STOCK = <?= json_encode($criterioStock ?? 'stock_minimo') ?>;
let categoriaActivaId = null;

function esc(v) { const d = document.createElement('div'); d.textContent = v || ''; return d.innerHTML; }
function formatMoneda(v) { return '$' + parseFloat(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); }

function productoSinStock(p) {
    if (!+p.controla_stock) return false;
    return CRITERIO_STOCK === 'stock_cero'
        ? parseFloat(p.stock_actual) <= 0
        : parseFloat(p.stock_actual) <= parseFloat(p.stock_minimo);
}

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
    btnTodos.className = 'list-group-item list-group-item-action py-2 small fw-semibold active';
    btnTodos.innerHTML = `Todos <span class="badge bg-secondary-soft text-secondary float-end">${CATALOGO.length}</span>`;
    btnTodos.addEventListener('click', () => seleccionarCategoria(null, btnTodos));
    cont.appendChild(btnTodos);

    agruparCategorias().forEach((c, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `list-group-item list-group-item-action py-2 small fw-semibold cat-color-${(i % 8) + 1}`;
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
            <div class="fw-bold text-primary small">${formatMoneda(p.precio_venta)}</div>
            <div class="small text-muted">${esc(p.unidad || '')}</div>
            ${stockLinea}
            ${+p.tiene_opciones ? '<div class="small text-muted mt-1">👁 Ver gustos</div>' : ''}`;
        if (+p.tiene_opciones) item.addEventListener('click', () => abrirOpcionesPreview(p));
        cont.appendChild(item);
    });
}

async function abrirOpcionesPreview(p) {
    document.getElementById('opNombreProducto').textContent = p.nombre;
    document.getElementById('opGruposWrap').innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    document.getElementById('opError').classList.add('d-none');
    _showModal('modalOpcionesPortal');
    try {
        const r = await fetch(`${BASE_URL}/clientes/opciones-producto-preview?producto_id=${p.id}`);
        const d = await r.json();
        if (!d.ok) throw new Error();
        renderGruposPreview(d.grupos);
    } catch {
        document.getElementById('opGruposWrap').innerHTML = '<div class="text-danger small text-center py-3">No se pudieron cargar los gustos.</div>';
    }
}

// Solo lectura: badges informativos, sin onclick — esta pantalla es para auditar
// disponibilidad, no para armar un pedido (eso ya lo puede hacer el cliente en /portal).
function renderGruposPreview(grupos) {
    document.getElementById('opGruposWrap').innerHTML = grupos.map(g => {
        const tipoTexto = g.tipo_seleccion === 'unica' ? 'Elegí 1' : `Elegí entre ${g.minimo_selecciones} y ${g.maximo_selecciones}`;
        const tarjetas = g.opciones.map((o, i) => {
            const colorClase   = o.sin_stock ? '' : 'prod-color-' + ((i % 8) + 1);
            return `<div class="catalogo-item p-2 ${colorClase}${o.sin_stock ? ' text-muted opcion-agotada' : ''}" style="width:31%;min-height:60px">
                <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                ${o.sin_stock ? '<div class="small">Sin stock</div>' : ''}
            </div>`;
        }).join('');
        return `<div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">${esc(g.nombre)} ${+g.obligatorio ? '' : '<span class="text-muted small fw-normal">(opcional)</span>'}</h6>
                <span class="small text-muted">${tipoTexto}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">${tarjetas}</div>
        </div>`;
    }).join('');
}

// Mismo helper que ya usa ventas/pos.php y portal/catalogo.php — Dashly bundlea Bootstrap sin exponer window.bootstrap.
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

<?php if ($catalogo): ?>
renderCategorias();
renderGrid();
<?php endif; ?>
</script>

<?php if ($catalogo): ?>
<style>
/* Mismo sistema visual pastel que portal/catalogo.php — para que la vista previa se vea
   pixel a pixel igual a lo que el cliente ve de verdad. */
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
#catalogoResultados .btn.catalogo-item[class*="prod-color-"],
#opGruposWrap .catalogo-item[class*="prod-color-"] {
    background-color: var(--prod-bg);
    border: 1.5px solid var(--prod-vivo);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"] .catalogo-item-nombre,
#opGruposWrap .catalogo-item[class*="prod-color-"] .catalogo-item-nombre {
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
#categoriasLista .list-group-item.active { border-radius: .6rem !important; }
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
}
#categoriasLista .list-group-item[class*="cat-color-"].active {
    background-color: var(--cat-vivo);
    color: #fff;
    box-shadow: 0 0 0 2px var(--cat-vivo);
}
</style>
<?php endif; ?>
