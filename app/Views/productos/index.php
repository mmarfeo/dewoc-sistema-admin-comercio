<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h1 class="h3 mb-0 fw-bold">Productos</h1>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <a href="<?= url('productos/exportar') ?>" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Exportar CSV
        </a>
        <a href="<?= url('stock') ?>" class="btn btn-outline-secondary btn-sm" title="Ver todo el stock, ajustes masivos e inventario">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Control de Stock
        </a>
        <a href="<?= url('productos/nuevo') ?>" class="btn btn-primary btn-sm">+ Nuevo producto</a>
    </div>
</div>

<div class="card border-0 mb-3">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1 d-block">Control de stock</label>
                <select id="fltControlaStock" class="form-select form-select-sm" style="min-width:180px">
                    <option value="">— Todos —</option>
                    <option value="1">Con control de stock</option>
                    <option value="0">Sin control de stock</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1 d-block">Recetas</label>
                <select id="fltTieneReceta" class="form-select form-select-sm" style="min-width:160px">
                    <option value="">— Todos —</option>
                    <option value="1">Con receta</option>
                    <option value="0">Sin receta</option>
                </select>
            </div>
            <div class="col-auto ms-md-auto">
                <div class="form-check form-switch p-3 rounded-3 border border-success bg-success-soft d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input flex-shrink-0" type="checkbox" role="switch" id="fltSoloActivos" checked style="width:2.5em;height:1.4em;cursor:pointer">
                    <label class="form-check-label fw-bold text-success mb-0" for="fltSoloActivos" style="cursor:pointer">
                        ✅ Mostrar solo productos activos
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblProductos" class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="d-none d-xl-table-cell">Código</th>
                        <th>Nombre</th>
                        <th class="d-none d-xl-table-cell">Categoría</th>
                        <th>Precio venta</th>
                        <?php if ($verCostos): ?><th>Recetas / Costo MP</th><?php endif; ?>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <?php $controlaStock = (int)($p['controla_stock'] ?? 1) === 1; ?>
                    <tr class="<?= ($controlaStock && $p['stock_actual'] <= $p['stock_minimo']) ? 'table-warning' : '' ?>"
                        data-controla-stock="<?= $controlaStock ? 1 : 0 ?>"
                        data-tiene-receta="<?= (int)$p['recetas_count'] > 0 ? 1 : 0 ?>"
                        data-activo="<?= (int)$p['activo'] === 1 ? 1 : 0 ?>">
                        <td class="text-muted small d-none d-xl-table-cell"><?= e($p['codigo'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= e($p['nombre']) ?></td>
                        <td class="small d-none d-xl-table-cell"><?= e($p['categoria_nombre'] ?? '—') ?></td>
                        <td><?= moneda($p['precio_venta']) ?></td>
                        <?php if ($verCostos): ?>
                        <td>
                            <?php if ((int)($p['es_promo'] ?? 0) === 1): ?>
                            <!-- Promo: precio_costo ya es la suma de sus componentes (ver
                                 ProductoModel::sincronizarPrecioCostoPromo()) — se prioriza sobre
                                 "Recetas de costo" a propósito, porque un producto puede tener las
                                 dos cosas configuradas (ej. una receta vieja que quedó de antes de
                                 convertirse en promo) y esa NO es la fuente de verdad del costo acá. -->
                            <span class="badge bg-primary-soft text-primary me-1" title="Costo = suma de los componentes de la promo">promo</span>
                            <span class="small text-success fw-semibold"><?= moneda($p['precio_costo']) ?></span>
                            <?php elseif ((int)$p['recetas_count'] > 0): ?>
                            <!-- OJO: acá se usa precio_costo (la fuente de verdad, ya sincronizada
                                 por RecetaModel::propagarCambioCosto()/ProductoModel::
                                 sincronizarPrecioCostoPorPivote() — YA divide por el rendimiento
                                 de cada receta), NO el costo_mp que trae esta misma fila: ese es
                                 un cálculo aparte, en SQL, que no sabe recorrer sub-recetas
                                 (receta_detalle.tipo_ingrediente='receta') ni divide por
                                 rendimiento — para un producto cuya receta incluye OTRA receta
                                 como ingrediente, costo_mp queda mudo en esa parte y da un número
                                 mucho más bajo que el real (bug reportado: recetas de costo mostraba
                                 $17.737 pero el listado $4.242 para el mismo producto). costo_mp
                                 sigue existiendo en la query por otros consumidores
                                 (ver cron/proyecciones_generar.php), no se tocó acá. -->
                            <span class="badge bg-info-soft text-info me-1"><?= (int)$p['recetas_count'] ?></span>
                            <span class="small text-success fw-semibold"><?= moneda($p['precio_costo']) ?></span>
                            <?php elseif ((float)($p['precio_costo'] ?? 0) > 0): ?>
                            <span class="badge bg-secondary-soft text-secondary me-1" title="Costo desde compra directa, no reconstruido por receta">compra</span>
                            <span class="small text-success fw-semibold"><?= moneda($p['precio_costo']) ?></span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if (!$controlaStock): ?>
                            <span class="badge bg-info-soft text-info">Sin control de stock</span>
                            <?php else: ?>
                            <?php $stockClass = $p['stock_actual'] <= $p['stock_minimo'] ? 'text-danger fw-bold' : 'text-success fw-semibold'; ?>
                            <span class="<?= $stockClass ?>">
                                <?= number_format($p['stock_actual'], 2, ',', '.') ?>
                                <?= e($p['unidad'] ?? '') ?>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-1 ms-1"
                                onclick="abrirAjusteStock('producto',<?= $p['id'] ?>,'<?= addslashes(e($p['nombre'])) ?>',<?= $p['stock_actual'] ?>)"
                                title="Ajustar stock">±</button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $p['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                                <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-info py-0"
                                onclick="gestionarRecetas(<?= $p['id'] ?>, '<?= addslashes(e($p['nombre'])) ?>')"
                                title="Gestionar recetas de costo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
                            </button>
                            <a href="<?= url('productos/qr?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="QR / Código de barras" target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3z"/><path d="M17 17h4v4h-4z"/><path d="M14 17v4"/><path d="M17 14h4"/></svg>
                            </a>
                            <a href="<?= url('productos/editar?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <?php if (canEdit('lista_compras')): ?>
                            <button class="btn btn-sm btn-outline-success py-0 ms-1"
                                onclick="agregarAListaCompras('producto', <?= $p['id'] ?>, '<?= addslashes(e($p['nombre'])) ?>')" title="Agregar a Lista de Compras">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminarProducto(<?= $p['id'] ?>, '<?= e($p['nombre']) ?>')" title="Eliminar">
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

<?php if (count(array_filter($productos, fn($p) => (int)($p['controla_stock'] ?? 1) === 1 && $p['stock_actual'] <= $p['stock_minimo'])) > 0): ?>
<p class="mt-2 small text-warning">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Las filas en amarillo tienen stock por debajo del mínimo.
</p>
<?php endif; ?>

<?php
$importUrl        = url('productos/importar');
$importPreviewUrl = url('productos/previsualizar');
$importPlantilla  = url('productos/plantilla');
$importModulo     = 'Productos';
// "menu_online" reemplaza al viejo "ecommerce" (decisión D2 de
// docs/integracion-menuapp-checklist.md). La importación sigue aceptando el nombre viejo,
// para no invalidar los archivos que el comercio ya tenía armados.
$importColumnas   = ['nombre *', 'codigo', 'descripcion', 'categoria', 'tipo (elaborado/reventa)', 'precio_venta', 'precio_online (vacío = igual al del local)', 'iva (0/10.5/21)', 'unidad_medida', 'stock_minimo', 'stock_actual', 'menu_online (1/0)', 'activo (1/0)'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>

<!-- ===== MODAL GESTIÓN DE RECETAS ===== -->
<div class="modal fade" id="modalGestionRecetas" tabindex="-1" aria-labelledby="tituloModalRecetas" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-semibold" id="tituloModalRecetas">Recetas de costo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Las recetas asignadas determinan el <strong>costo de materia prima</strong> del producto.</p>

                <div id="cuerpoRecetasModal">
                    <div class="text-center py-3"><span class="spinner-border spinner-border-sm text-secondary"></span></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <small class="text-muted fw-semibold">Costo total MP:</small>
                    <strong id="totalCostoModal" class="text-success fs-6">$ 0,00</strong>
                </div>

                <div class="input-group mt-3 input-group-sm">
                    <select id="selectRecetaModal" class="form-select">
                        <option value="">— Agregar receta —</option>
                        <?php foreach ($recetasActivas as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline-success" onclick="agregarRecetaModal()">+ Agregar</button>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL AJUSTE DE STOCK ===== -->
<div class="modal fade" id="modalAjusteStock" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Ajuste de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAjusteStock">
                <?= Csrf::field() ?>
                <input type="hidden" id="ajStkTipo" name="tipo_item">
                <input type="hidden" id="ajStkId"   name="item_id">
                <div class="modal-body">
                    <p id="ajStkNombre" class="fw-bold mb-3"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Stock actual</label>
                            <input type="number" id="ajStkActual" class="form-control" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ajuste (+/-)</label>
                            <input type="text" inputmode="decimal" pattern="-?[0-9]*[.,]?[0-9]*"
                                id="ajStkCantidad" name="cantidad" class="form-control" placeholder="Ej: -2 o +5">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Motivo</label>
                            <input type="text" name="motivo" class="form-control" maxlength="255" placeholder="Compra sin factura, merma, diferencia de inventario...">
                        </div>
                    </div>
                    <div id="ajStkError" class="alert alert-danger mt-3 d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnAjustarStock">Aplicar ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL AGREGAR A LISTA DE COMPRAS ===== -->
<div class="modal fade" id="modalAgregarListaCompras" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Agregar a Lista de Compras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">¿Dónde querés agregar <strong id="alcNombre">—</strong>?</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary" id="btnAlcOrden">🧾 Orden de Compra <span class="fw-normal">— se va a comprar sí o sí</span></button>
                    <button type="button" class="btn btn-outline-primary" id="btnAlcRecomendacion">🔎 Recomendaciones <span class="fw-normal">— a confirmar después</span></button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

function abrirAjusteStock(tipo, id, nombre, stock) {
    document.getElementById('ajStkTipo').value = tipo;
    document.getElementById('ajStkId').value = id;
    document.getElementById('ajStkNombre').textContent = nombre;
    document.getElementById('ajStkActual').value = stock;
    document.getElementById('ajStkCantidad').value = '';
    document.getElementById('ajStkError').classList.add('d-none');
    _showModal('modalAjusteStock');
}

document.getElementById('formAjusteStock').addEventListener('submit', async function (e) {
    e.preventDefault();
    document.getElementById('ajStkError').classList.add('d-none');
    // El campo ahora es de texto (para que el teclado numérico del celular muestre la
    // tecla "-"), así que normalizamos coma decimal a punto antes de mandarlo.
    const ajCant = document.getElementById('ajStkCantidad');
    ajCant.value = ajCant.value.replace(',', '.');
    document.getElementById('btnAjustarStock').disabled = true;
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/stock/ajuste`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => location.reload(), 800);
        } else {
            const errEl = document.getElementById('ajStkError');
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        const errEl = document.getElementById('ajStkError');
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnAjustarStock').disabled = false;
    }
});

let tablaProductos;

$(document).ready(() => {
    const headerH = document.querySelector('.navbar-header')?.offsetHeight || 64;

    tablaProductos = $('#tblProductos').DataTable({
        fixedHeader: {
            header: true,
            headerOffset: headerH
        }
    });

    // Filtros de "Control de stock" / "Recetas" / "Solo activos": leen los data-* que ya
    // trae cada <tr> (no hace falta ir a buscarlos, ya vienen calculados desde el modelo)
    // y se combinan con el buscador de texto de DataTables vía su hook de search custom.
    // Tiene que registrarse acá adentro (después de que $.fn.dataTable ya existe) y no en
    // el nivel superior del script, porque en esta página el plugin de DataTables se
    // termina de cargar más tarde — llamarlo antes tira "Cannot read properties of
    // undefined (reading 'ext')" y corta el resto del script, incluida la inicialización
    // de la tabla (por eso también desaparecían el buscador, columnas, Excel y PDF).
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblProductos') return true;
        const row = tablaProductos.row(dataIndex).node();
        if (!row) return true;

        const fControlaStock = document.getElementById('fltControlaStock').value;
        const fTieneReceta   = document.getElementById('fltTieneReceta').value;
        const soloActivos    = document.getElementById('fltSoloActivos').checked;

        if (fControlaStock !== '' && row.dataset.controlaStock !== fControlaStock) return false;
        if (fTieneReceta   !== '' && row.dataset.tieneReceta   !== fTieneReceta)   return false;
        if (soloActivos && row.dataset.activo !== '1') return false;

        return true;
    });

    $('#fltControlaStock, #fltTieneReceta, #fltSoloActivos').on('change', () => tablaProductos.draw());

    $('#tblProductos tbody').on('dblclick', 'tr', function () {
        const href = $(this).find('a[title="Editar"]').attr('href');
        if (href) window.location.href = href;
    });
});

// ── Gestión de recetas ───────────────────────────────────────────────────
let _productoActivoId = null;

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
// (Bootstrap sí registra esa delegación de eventos al importarse).
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

async function gestionarRecetas(productoId, nombre) {
    _productoActivoId = productoId;
    document.getElementById('tituloModalRecetas').textContent = 'Recetas — ' + nombre;
    document.getElementById('cuerpoRecetasModal').innerHTML =
        '<div class="text-center py-3"><span class="spinner-border spinner-border-sm text-secondary"></span></div>';
    document.getElementById('totalCostoModal').textContent = '$ 0,00';
    _showModal('modalGestionRecetas');
    await _cargarRecetasModal();
}

async function _cargarRecetasModal() {
    try {
        const r = await fetch(`${BASE_URL}/productos/recetas?id=${_productoActivoId}`);
        const d = await r.json();
        if (d.ok) _renderRecetasModal(d.recetas, d.costo_total);
        else document.getElementById('cuerpoRecetasModal').innerHTML = '<p class="text-danger small">Error al cargar.</p>';
    } catch { document.getElementById('cuerpoRecetasModal').innerHTML = '<p class="text-danger small">Error de conexión.</p>'; }
}

function _renderRecetasModal(recetas, total) {
    const cuerpo = document.getElementById('cuerpoRecetasModal');
    if (!recetas.length) {
        cuerpo.innerHTML = '<p class="text-muted small mb-0">Sin recetas asignadas. Agregá una con el selector de abajo.</p>';
        document.getElementById('totalCostoModal').textContent = '$ 0,00';
        return;
    }
    cuerpo.innerHTML = recetas.map(r =>
        `<div class="d-flex align-items-center justify-content-between mb-2 py-1 border-bottom">
            <div>
                <span class="fw-semibold small">${_esc(r.nombre)}</span>
                <span class="text-muted small ms-2">${monedaJS(r.costo)}</span>
            </div>
            <button class="btn btn-sm btn-outline-danger py-0 px-1 ms-2" onclick="_quitarRecetaModal(${r.id})" title="Quitar">✕</button>
        </div>`
    ).join('');
    document.getElementById('totalCostoModal').textContent = monedaJS(total);
}

async function agregarRecetaModal() {
    const sel = document.getElementById('selectRecetaModal');
    if (!sel.value) { showToast('Seleccioná una receta.', 'warning'); return; }
    const fd = new FormData();
    fd.append('producto_id', _productoActivoId);
    fd.append('receta_id', sel.value);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/productos/asignar-receta`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { _renderRecetasModal(d.recetas, d.costo_total); sel.value = ''; }
        else showToast(d.error, 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}

async function _quitarRecetaModal(recetaId) {
    const fd = new FormData();
    fd.append('producto_id', _productoActivoId);
    fd.append('receta_id', recetaId);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/productos/quitar-receta`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) _renderRecetasModal(d.recetas, d.costo_total);
        else showToast(d.error, 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}

function monedaJS(v) {
    return '$ ' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function _esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function eliminarProducto(id, nombre) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const r = await fetch(`${BASE_URL}/productos/eliminar`, { method: 'POST', body: fd });
            const d = await r.json();
            d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800))
                  : showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    };
    const msg = `¿Eliminar el producto "${nombre}"?`;
    if (typeof showConfirm === 'function') { showConfirm(msg, ejecutar, { label: 'Eliminar' }); }
    else { if (confirm(msg)) ejecutar(); }
}

// ── Agregar a Lista de Compras (Orden de Compra o Recomendaciones, a elección) ──
let _alcTipo = null, _alcId = null;
function agregarAListaCompras(tipo, id, nombre) {
    _alcTipo = tipo; _alcId = id;
    document.getElementById('alcNombre').textContent = nombre;
    _showModal('modalAgregarListaCompras');
}
async function _alcEnviar(estado) {
    const fd = new FormData();
    fd.append('tipo_item', _alcTipo);
    fd.append('item_id', _alcId);
    fd.append('estado', estado);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/lista-compras/agregar`, { method: 'POST', body: fd });
        const d = await r.json();
        showToast(d.ok ? d.mensaje : d.error, d.ok ? 'success' : 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}
document.getElementById('btnAlcOrden')?.addEventListener('click', () => _alcEnviar('pendiente'));
document.getElementById('btnAlcRecomendacion')?.addEventListener('click', () => _alcEnviar('sugerido'));
</script>
