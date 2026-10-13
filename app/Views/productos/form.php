<?php $esEdicion = !empty($producto); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h1>
    <a href="<?= url('productos') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formProducto" method="POST" enctype="multipart/form-data" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?>
    <input type="hidden" name="id" value="<?= $producto['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">

        <!-- Columna izquierda -->
        <div class="col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Información general</h6>
                </div>
                <div class="card-body">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required maxlength="150"
                                value="<?= e($producto['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Código</label>
                            <input type="text" name="codigo" class="form-control" maxlength="30"
                                value="<?= e($producto['codigo'] ?? '') ?>" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" maxlength="500"><?= e($producto['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Precios e impuestos</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Precio de venta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_venta" id="inpPrecioVenta" class="form-control" min="0" step="0.01" required
                                    value="<?= $producto['precio_venta'] ?? '0' ?>">
                            </div>
                            <small class="text-muted">
                                <?= $preciosIncluyenIva
                                    ? 'Los precios cargados <strong>incluyen IVA</strong>: es el precio final que paga el cliente.'
                                    : 'Los precios cargados <strong>no incluyen IVA</strong>: el IVA se suma al vender.' ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">IVA <span class="text-danger">*</span></label>
                            <select name="iva" id="selIva" class="form-select" required>
                                <?php foreach ([0 => 'Exento (0%)', 10.5 => '10,5%', 21 => '21%'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= (float)($producto['iva'] ?? 21) === (float)$val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Precio online (MenuApp) — ver docs/integracion-menuapp-checklist.md, tarea 0.3 -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Precio online <span class="text-muted fw-normal small">(menú online)</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_online" id="inpPrecioOnline" class="form-control" min="0" step="0.01"
                                    placeholder="Igual al del local"
                                    value="<?= $producto['precio_online'] ?? '' ?>">
                            </div>
                            <small class="text-muted d-block">
                                <?= $preciosIncluyenIva
                                    ? 'Precio del menú online, <strong>con IVA incluido</strong>.'
                                    : 'Precio del menú online, <strong>sin IVA</strong>: se suma al vender.' ?>
                                Vacío = se usa el mismo precio del local.
                            </small>
                            <div class="small fw-semibold text-success mt-1" id="avisoPrecioCliente"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha -->
        <div class="col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Clasificación</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <option value="reventa"  <?= ($producto['tipo'] ?? '') === 'reventa'  ? 'selected' : '' ?>>Reventa</option>
                            <option value="elaborado" <?= ($producto['tipo'] ?? '') === 'elaborado' ? 'selected' : '' ?>>Elaborado (tiene receta)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoría</label>
                        <select name="categoria_id" class="form-select">
                            <option value="">— Sin categoría —</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($producto['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de gasto</label>
                        <select name="tipo_gasto" class="form-select">
                            <option value="costo_variable" <?= ($producto['tipo_gasto'] ?? 'costo_variable') === 'costo_variable' ? 'selected' : '' ?>>Gasto Variable</option>
                            <option value="costo_fijo"     <?= ($producto['tipo_gasto'] ?? '') === 'costo_fijo'     ? 'selected' : '' ?>>Gasto Fijo</option>
                            <option value="impuesto"       <?= ($producto['tipo_gasto'] ?? '') === 'impuesto'       ? 'selected' : '' ?>>Impuesto</option>
                            <option value="gasto"          <?= ($producto['tipo_gasto'] ?? '') === 'gasto'          ? 'selected' : '' ?>>Gasto (no es inventario real)</option>
                        </select>
                        <div class="form-text">Clasificación en el Estado de Resultados.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unidad de medida</label>
                        <select name="unidad_medida_id" class="form-select">
                            <option value="">— —</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($producto['unidad_medida_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="controla_stock" value="1" id="chkControlaStock"
                                <?= ($producto['controla_stock'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="chkControlaStock">Controla stock</label>
                        </div>
                        <div class="form-text">
                            Desmarcá esto para productos que no necesitan inventario (ej. café, algo hecho al
                            momento): el POS lo va a tratar como siempre disponible y no le va a descontar stock.
                        </div>
                    </div>
                    <div class="mb-3" id="bloqueStockMinimo">
                        <label class="form-label fw-semibold">Stock mínimo</label>
                        <input type="number" name="stock_minimo" id="inputStockMinimo" class="form-control" min="0" step="0.001"
                            value="<?= $producto['stock_minimo'] ?? '0' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Desvío %</label>
                        <input type="number" name="desvio_pct" id="inputDesvioPct" class="form-control" min="0" max="100" step="0.01"
                            value="<?= $producto['desvio_pct'] ?? '0' ?>">
                        <div class="form-text">
                            Pérdida no explicada por ninguna venta (robo, rotura, consumo interno, regalos) —
                            distinto de la merma de una receta. Se recalcula solo al hacer un conteo físico de
                            inventario; también se puede editar acá a mano.
                        </div>
                        <?php if (($producto['precio_costo'] ?? 0) > 0): ?>
                        <div class="d-flex justify-content-between small mt-2 p-2 bg-light rounded">
                            <span class="text-muted">Costo MP <strong id="previewCostoBase"></strong></span>
                            <span class="text-danger">+ Desvío <strong id="previewCostoSoloDesvio"></strong></span>
                            <span class="fw-bold">= Real <strong id="previewCostoDesvio"></strong></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($esEdicion && empty($tieneRecetaPropia)): ?>
                        <!-- Solo para reventa comprada directo (sin receta propia) — el costo acá
                             sale del historial de compras (facturas_compra_detalle), no de una
                             receta. Si tiene receta, el costo ya se sincroniza solo y este botón
                             no aparece (lo pisaría con el de compras, que puede ni existir). -->
                        <div class="mt-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="text-muted">Costo actual (compras)</span>
                                <strong id="previewCostoCompras"><?= moneda((float)($producto['precio_costo'] ?? 0)) ?></strong>
                            </div>
                            <button type="button" id="btnRecalcularCostoProducto" class="btn btn-sm btn-outline-secondary mt-1 w-100"
                                    data-id="<?= (int)$producto['id'] ?>"
                                    title="Ignora lo guardado y recalcula el costo repasando todo el historial real de compras de este producto (mismo cálculo que usa el sistema al editar una factura).">
                                ↻ Recalcular desde compras
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Imagen</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($producto['imagen'])): ?>
                        <img src="<?= url('uploads/productos/' . e($producto['imagen'])) ?>"
                            class="img-thumbnail mb-2" style="max-width:120px; max-height:120px; object-fit:cover">
                    <?php endif; ?>
                    <input type="file" name="imagen" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted d-block mt-1">JPG, PNG o WebP · Máx. 5MB</small>
                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="ecommerce" value="1" id="chkEcommerce"
                            <?= ($producto['ecommerce'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkEcommerce">Mostrar en el menú online (MenuApp)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="chkActivo"
                            <?= ($producto['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkActivo">Producto activo</label>
                    </div>
                </div>
            </div>

            <?php
            $recetasAsignadas = $recetasAsignadas ?? [];
            $recetasActivas   = $recetasActivas   ?? [];
            $_recetasPool = array_values(array_reduce(
                array_merge($recetasActivas, $recetasAsignadas),
                function($c, $r) { $c[(int)$r['id']] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']]; return $c; },
                []
            ));
            ?>
            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent pb-1">
                    <h6 class="fw-semibold mb-0 small">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1 text-info"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
                        Recetas de costo
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="position-relative mb-2" id="wrapperRecetaDropdown">
                        <div id="recetaDropdownToggle"
                             class="form-select form-select-sm"
                             style="cursor:pointer;user-select:none"
                             onclick="_toggleRecetaSearch()">
                            <span class="text-muted small">Buscar y agregar receta…</span>
                        </div>
                        <div id="recetaDropdownPanel" class="border rounded bg-white shadow-sm position-absolute w-100"
                             style="display:none;z-index:1055;top:calc(100% + 2px);left:0">
                            <div class="p-2 border-bottom">
                                <input type="text" id="searchRecetaInput" class="form-control form-control-sm"
                                    placeholder="Buscar receta…" autocomplete="off">
                            </div>
                            <div id="searchRecetaResults" style="max-height:200px;overflow-y:auto"></div>
                        </div>
                    </div>
                    <div id="listaRecetasSeleccionadas" class="mb-1"></div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <small class="text-muted" title="Ya divide el costo de cada tanda por su rendimiento — este es el costo por UNA unidad del producto">Costo MP total (por unidad):</small>
                        <strong id="totalCostoForm" class="text-success small">$ 0,00</strong>
                    </div>
                </div>
            </div>

            <!-- Grupos de opciones/adicionales (ver docs/tareas-pendientes.md #1) -->
            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent pb-1">
                    <h6 class="fw-semibold mb-0 small">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1 text-info"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        Opciones/adicionales
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <?php if (!$esEdicion): ?>
                    <p class="small text-muted mb-0">Guardá el producto primero para poder asignarle grupos de opciones (ej. "Gustos de helado").</p>
                    <?php elseif (!$gruposOpcionesActivos): ?>
                    <p class="small text-muted mb-0">No hay grupos de opciones creados todavía. <a href="<?= url('opciones-productos/nuevo') ?>" target="_blank">Crear uno</a>.</p>
                    <?php else: ?>
                    <div class="d-flex gap-2 mb-2">
                        <select id="selGrupoOpciones" class="form-select form-select-sm">
                            <option value="">— Elegir grupo —</option>
                            <?php foreach ($gruposOpcionesActivos as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= e($g['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" onclick="_agregarGrupoOpciones()">+ Agregar</button>
                    </div>
                    <div id="listaGruposOpciones"></div>
                    <?php endif; ?>
                    <div class="alert alert-info-soft py-2 px-2 small mt-2 mb-0">
                        💡 Si este producto es una <strong>promo</strong> y una parte se elige entre
                        varias alternativas (ej. "elegí 1 de estas tortas"), es acá — un grupo con
                        "Elegir entre 1 y 1, obligatorio" — no en "Componentes de la promo" de abajo
                        (esa es solo para lo que va fijo, sin elegir). Las dos secciones se combinan:
                        lo fijo + lo elegido forman la promo completa.
                    </div>
                </div>
            </div>

            <!-- Promos: combo de composición fija de 2+ productos (ver docs/promos-diseno.md) -->
            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent pb-1">
                    <h6 class="fw-semibold mb-0 small">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1 text-info"><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                        Componentes de la promo
                    </h6>
                    <p class="text-muted small mb-0">
                        Si este producto es una promo (ej. "1 Café + 2 Medialunas"), armá acá qué
                        productos y cantidades incluye — cada uno descuenta stock con su propia
                        regla de siempre. No hace falta si es un producto normal.
                    </p>
                    <p class="text-muted small mb-0 mt-1">
                        💡 Esto es solo para componentes <strong>fijos</strong> (van todos, sin elegir).
                        Si una parte de la promo se elige entre varias opciones (ej. "elegí 1 torta"),
                        usá "Opciones/adicionales" arriba en vez de esto — ahí el cliente elige y ese
                        producto puntual es el que descuenta stock.
                    </p>
                </div>
                <div class="card-body pt-0">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="chkEsPromo">
                        <label class="form-check-label fw-semibold" for="chkEsPromo">Es una promo</label>
                    </div>
                    <div id="wrapPromoContenido" class="d-none">
                        <div class="position-relative mb-2" id="wrapperPromoDropdown">
                            <div id="promoDropdownToggle"
                                 class="form-select form-select-sm"
                                 style="cursor:pointer;user-select:none"
                                 onclick="_togglePromoSearch()">
                                <span class="text-muted small">Buscar y agregar producto…</span>
                            </div>
                            <div id="promoDropdownPanel" class="border rounded bg-white shadow-sm position-absolute w-100"
                                 style="display:none;z-index:1055;top:calc(100% + 2px);left:0">
                                <div class="p-2 border-bottom">
                                    <input type="text" id="searchPromoInput" class="form-control form-control-sm"
                                        placeholder="Buscar producto…" autocomplete="off">
                                </div>
                                <div id="searchPromoResults" style="max-height:200px;overflow-y:auto"></div>
                            </div>
                        </div>
                        <div id="listaPromoComponentes"></div>

                        <!-- Reflejo de solo lectura de "Opciones/adicionales" (arriba): esos
                             grupos ya se aplican solos a este producto sea o no promo — esto NO
                             asigna nada nuevo, solo lo repite acá para que se vea de un vistazo,
                             sin tener que subir a la otra sección. Editar/quitar sigue siendo
                             desde "Opciones/adicionales". Ver _renderResumenGruposEnPromo(). -->
                        <div id="resumenGruposEnPromo" class="d-none mt-2 pt-2 border-top"></div>

                        <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                            <small class="text-muted" id="labelCostoPromo">Costo total (suma de componentes):</small>
                            <strong id="totalCostoPromo" class="text-success small">$ 0,00</strong>
                        </div>
                        <div id="avisoPromoStock" class="alert alert-secondary py-1 px-2 small mt-2 mb-0 d-none">
                            🔒 Como tiene componentes, "Controla stock" quedó apagado — el stock se
                            resuelve por cada componente, no por este producto.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4">
            <?= $esEdicion ? 'Actualizar producto' : 'Crear producto' ?>
        </button>
        <a href="<?= url('productos') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL    = '<?= BASE_URL ?>';
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const ES_EDICION  = <?= $esEdicion ? 'true' : 'false' ?>;
const PRODUCTO_ID = <?= $esEdicion ? (int)$producto['id'] : 'null' ?>;
const PRECIO_COSTO_BASE = <?= (float)($producto['precio_costo'] ?? 0) ?>;

// ── Recalcular costo desde historial de compras (solo reventa sin receta propia) ──
document.getElementById('btnRecalcularCostoProducto')?.addEventListener('click', async function () {
    const fd = new FormData();
    fd.append('id', this.dataset.id);
    fd.append('_csrf_token', CSRF_TOKEN);
    this.disabled = true;
    try {
        const r = await fetch(`${BASE_URL}/productos/recalcular-costo`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            document.getElementById('previewCostoCompras').textContent =
                '$ ' + (parseFloat(d.precio_costo) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
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

// _promoComponentes se declara ACÁ (antes del bloque de grupos de opciones de abajo) a
// propósito, aunque conceptualmente pertenece a "Componentes de la promo" más abajo: apenas
// el producto ya tiene un grupo asignado, _renderGruposOpciones() se auto-ejecuta ni bien
// carga la página (ver el final de su bloque) y en cascada llama a _renderResumenGruposEnPromo()
// → _actualizarTotalCostoPromo(), que lee _promoComponentes. Declararla más abajo (como estaba)
// dejaba una ventana de temporal dead zone: "Cannot access '_promoComponentes' before
// initialization" apenas la página cargaba en cualquier producto-promo con grupos asignados
// (ej. id=84) — típico de TDZ con let/const, no lo salva ni un try/catch en el fetch de más
// arriba, revienta antes de llegar ahí. Moviendo la declaración acá, arriba de todo lo que
// puede disparar ese llamado en cascada, la ventana desaparece.
let _promoComponentes = <?= json_encode($promoComponentes ?? [], JSON_UNESCAPED_UNICODE) ?>;

// ── Grupos de opciones/adicionales (ver docs/tareas-pendientes.md #1) ────────
<?php if ($esEdicion): ?>
let _gruposOpcionesAsignados = <?= json_encode($gruposOpcionesAsignados, JSON_UNESCAPED_UNICODE) ?>;

function _renderGruposOpciones() {
    const cont = document.getElementById('listaGruposOpciones');
    if (!cont) return;
    if (!_gruposOpcionesAsignados.length) {
        cont.innerHTML = '<p class="small text-muted mb-0">Sin grupos asignados.</p>';
        _renderResumenGruposEnPromo();
        return;
    }
    cont.innerHTML = _gruposOpcionesAsignados.map(g => `
        <div class="border rounded p-2 mb-2 small">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong>${esc(g.nombre)}</strong>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="_quitarGrupoOpciones(${g.id})" title="Quitar">✕</button>
            </div>
            <div class="row g-1 align-items-center">
                <div class="col-auto text-muted">Elegir entre</div>
                <div class="col-2"><input type="number" min="0" class="form-control form-control-sm" value="${g.minimo_selecciones}" onchange="_actualizarLimiteGrupo(${g.id},'minimo_selecciones',this.value)"></div>
                <div class="col-auto text-muted">y</div>
                <div class="col-2"><input type="number" min="0" class="form-control form-control-sm" value="${g.maximo_selecciones}" onchange="_actualizarLimiteGrupo(${g.id},'maximo_selecciones',this.value)"></div>
                <div class="col-auto form-check ms-2">
                    <input class="form-check-input" type="checkbox" ${+g.obligatorio ? 'checked' : ''} onchange="_actualizarLimiteGrupo(${g.id},'obligatorio',this.checked?1:0)">
                    <label class="form-check-label text-muted">obligatorio</label>
                </div>
            </div>
            <div class="row g-1 align-items-center mt-1">
                <div class="col-auto text-muted">Repartir</div>
                <div class="col-3">
                    <input type="number" min="0" step="0.001" class="form-control form-control-sm" placeholder="—"
                        value="${g.cantidad_a_repartir ?? ''}" onchange="_actualizarLimiteGrupo(${g.id},'cantidad_a_repartir',this.value)">
                </div>
                <div class="col-auto text-muted">
                    entre las opciones elegidas (ej: 1000 para un producto "1kg" — vacío = cada
                    opción descuenta su cantidad fija, para promos por unidad)
                </div>
            </div>
        </div>
    `).join('');
    _renderResumenGruposEnPromo();
}
function esc(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function _agregarGrupoOpciones() {
    const sel = document.getElementById('selGrupoOpciones');
    const grupoId = sel.value;
    if (!grupoId) return;
    await _guardarGrupoOpciones(grupoId, 1, 1, 1, '');
    sel.value = '';
}
async function _actualizarLimiteGrupo(grupoId, campo, valor) {
    const g = _gruposOpcionesAsignados.find(x => String(x.id) === String(grupoId));
    if (!g) return;
    const min = campo === 'minimo_selecciones' ? valor : g.minimo_selecciones;
    const max = campo === 'maximo_selecciones' ? valor : g.maximo_selecciones;
    const obl = campo === 'obligatorio' ? valor : (g.obligatorio ? 1 : 0);
    const rep = campo === 'cantidad_a_repartir' ? valor : (g.cantidad_a_repartir ?? '');
    await _guardarGrupoOpciones(grupoId, min, max, obl, rep);
}
async function _guardarGrupoOpciones(grupoId, minimo, maximo, obligatorio, cantidadARepartir) {
    const fd = new FormData();
    fd.append('producto_id', PRODUCTO_ID);
    fd.append('grupo_id', grupoId);
    fd.append('minimo_selecciones', minimo);
    fd.append('maximo_selecciones', maximo);
    fd.append('obligatorio', obligatorio);
    fd.append('cantidad_a_repartir', cantidadARepartir ?? '');
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/productos/asignar-opciones-grupo`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { _gruposOpcionesAsignados = d.grupos; _renderGruposOpciones(); }
        else showToast(d.error || 'No se pudo guardar.', 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}
async function _quitarGrupoOpciones(grupoId) {
    const fd = new FormData();
    fd.append('producto_id', PRODUCTO_ID);
    fd.append('grupo_id', grupoId);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/productos/quitar-opciones-grupo`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { _gruposOpcionesAsignados = d.grupos; _renderGruposOpciones(); }
        else showToast(d.error || 'No se pudo quitar.', 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
}
_renderGruposOpciones();
<?php endif; ?>

// ── Componentes de la promo (ver docs/promos-diseno.md) ──────────────────────
// Misma lógica que la carga de ingredientes en Recetas: la lista se arma en el cliente
// (funciona igual creando o editando); si ya existe el producto (ES_EDICION), cada cambio
// además pega al servidor de una — si es nuevo, se guarda recién al enviar el formulario
// (ver el loop en el submit, más abajo), igual que ya hace "Recetas de costo".
// (_promoComponentes se declara arriba de todo, ver el comentario sobre TDZ antes del
// bloque de grupos de opciones — acá seguiría siendo el lugar conceptual, pero el orden real
// de ejecución obliga a declararla antes.)

function _renderPromoComponentes() {
    const cont = document.getElementById('listaPromoComponentes');
    if (!_promoComponentes.length) {
        cont.innerHTML = '<p class="small text-muted mb-0">Sin componentes — es un producto normal.</p>';
        document.getElementById('avisoPromoStock').classList.add('d-none');
    } else {
        cont.innerHTML = _promoComponentes.map(c => `
            <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="fw-semibold small">${_escPr(c.nombre)} <span class="text-muted">${_escPr(c.unidad||'')}</span>
                    <span class="text-muted d-block" style="font-size:.72rem">$ ${(parseFloat(c.precio_costo)||0).toLocaleString('es-AR',{minimumFractionDigits:2})} c/u</span>
                </span>
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm" style="width:70px" min="0.001" step="0.001"
                        value="${c.cantidad}" onchange="_actualizarCantidadPromo(${c.producto_id}, this.value)">
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="_quitarPromoComponente(${c.producto_id})">✕</button>
                </div>
            </div>`).join('');
        document.getElementById('avisoPromoStock').classList.remove('d-none');
    }
    _bloquearControlaStockPorPromo();
    _actualizarTotalCostoPromo();
}

/**
 * Muestra en vivo la suma cantidad × costo de cada componente fijo MÁS el estimado de los
 * grupos de opciones asignados (costo promedio de sus opciones × cantidad mínima a elegir —
 * ver comentario de ProductoModel::sincronizarPrecioCostoPromo()). Si el producto ya existe
 * (ES_EDICION), es el mismo número que el servidor ya dejó persistido en productos.precio_costo
 * apenas se agregó/quitó/cambió un componente o un grupo (ver asignarOpcionesGrupo() /
 * quitarOpcionesGrupo() en ProductosController, que resincronizan cuando el producto ya es
 * promo). Si es un producto nuevo todavía sin guardar, esto es solo la vista previa.
 */
function _actualizarTotalCostoPromo() {
    const totalFijos = _promoComponentes.reduce((acc, c) => acc + (parseFloat(c.cantidad)||0) * (parseFloat(c.precio_costo)||0), 0);
    const chkPromo = document.getElementById('chkEsPromo');
    const grupos = (chkPromo && chkPromo.checked && typeof _gruposOpcionesAsignados !== 'undefined') ? _gruposOpcionesAsignados : [];
    const totalOpciones = grupos.reduce((acc, g) => acc + (parseInt(g.minimo_selecciones,10)||0) * (parseFloat(g.costo_promedio)||0), 0);
    const total = totalFijos + totalOpciones;
    const el = document.getElementById('totalCostoPromo');
    if (el) el.textContent = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits:2});
    const label = document.getElementById('labelCostoPromo');
    if (label) {
        label.textContent = totalOpciones > 0
            ? 'Costo total estimado (fijos + promedio de opciones):'
            : 'Costo total (suma de componentes):';
    }
}

function _bloquearControlaStockPorPromo() {
    const chk = document.getElementById('chkControlaStock');
    const tienePromo = _promoComponentes.length > 0;
    if (tienePromo) chk.checked = false;
    chk.disabled = tienePromo;
    _toggleStockMinimo();
}

async function _agregarPromoComponente(producto) {
    if (_promoComponentes.some(c => c.producto_id == producto.id)) { _hidePromoSearch(); return; }
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('producto_id', PRODUCTO_ID);
        fd.append('componente_producto_id', producto.id);
        fd.append('cantidad', 1);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/productos/asignar-promo-componente`, {method:'POST', body:fd})).json();
            if (d.ok) { _promoComponentes = d.componentes; _renderPromoComponentes(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _promoComponentes.push({producto_id: parseInt(producto.id, 10), nombre: producto.nombre, unidad: producto.unidad || '', cantidad: 1, precio_costo: parseFloat(producto.precio_costo) || 0});
        _renderPromoComponentes();
    }
    _hidePromoSearch();
    document.getElementById('searchPromoInput').value = '';
}

async function _actualizarCantidadPromo(componenteId, cantidad) {
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('producto_id', PRODUCTO_ID);
        fd.append('componente_producto_id', componenteId);
        fd.append('cantidad', cantidad);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/productos/asignar-promo-componente`, {method:'POST', body:fd})).json();
            if (d.ok) { _promoComponentes = d.componentes; _renderPromoComponentes(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        const c = _promoComponentes.find(x => x.producto_id == componenteId);
        if (c) c.cantidad = parseFloat(cantidad) || 1;
        _actualizarTotalCostoPromo();
    }
}

async function _quitarPromoComponente(componenteId) {
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('producto_id', PRODUCTO_ID);
        fd.append('componente_producto_id', componenteId);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/productos/quitar-promo-componente`, {method:'POST', body:fd})).json();
            if (d.ok) { _promoComponentes = d.componentes; _renderPromoComponentes(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _promoComponentes = _promoComponentes.filter(c => c.producto_id != componenteId);
        _renderPromoComponentes();
    }
}

const _pmSrchInput   = document.getElementById('searchPromoInput');
const _pmSrchResults = document.getElementById('searchPromoResults');
const _pmSrchPanel   = document.getElementById('promoDropdownPanel');
let _pmBuscTimeout;

function _togglePromoSearch() {
    if (_pmSrchPanel.style.display !== 'none') { _hidePromoSearch(); return; }
    _pmSrchPanel.style.display = '';
    _pmSrchInput.focus();
}
function _hidePromoSearch() {
    _pmSrchPanel.style.display = 'none';
    _pmSrchInput.value = '';
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#wrapperPromoDropdown')) _hidePromoSearch();
});

_pmSrchInput.addEventListener('input', function() {
    clearTimeout(_pmBuscTimeout);
    const q = this.value.trim();
    if (q.length < 1) { _pmSrchResults.innerHTML = ''; return; }
    _pmBuscTimeout = setTimeout(() => _buscarPromoProductos(q), 250);
});

async function _buscarPromoProductos(q) {
    try {
        const r = await fetch(`${BASE_URL}/productos/buscar-componentes-promo?q=${encodeURIComponent(q)}&excluir=${ES_EDICION ? PRODUCTO_ID : 0}`);
        const data = await r.json();
        if (!data.length) {
            _pmSrchResults.innerHTML = '<div class="px-3 py-2 text-muted small">Sin resultados.</div>';
            return;
        }
        _pmSrchResults.innerHTML = data.map(p =>
            `<button type="button" class="w-100 text-start border-0 bg-transparent px-3 py-2 small _pmOpt" data-pid="${p.id}" data-nombre="${_escPr(p.nombre)}" data-unidad="${_escPr(p.unidad||'')}">${_escPr(p.nombre)}</button>`
        ).join('');
        _pmSrchResults.querySelectorAll('._pmOpt').forEach(btn => {
            btn.addEventListener('mouseover', () => btn.style.background = '#f0f0f0');
            btn.addEventListener('mouseout',  () => btn.style.background = '');
            btn.addEventListener('click', () => _agregarPromoComponente({id: btn.dataset.pid, nombre: btn.dataset.nombre, unidad: btn.dataset.unidad}));
        });
    } catch { _pmSrchResults.innerHTML = '<div class="px-3 py-2 text-danger small">Error de conexión.</div>'; }
}

function _escPr(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Toggle "Es una promo": arranca apagado salvo que ya tenga componentes cargados
// (edición de una promo existente). Apagarlo con componentes cargados los borra todos,
// previa confirmación — si no, quedaría "escondido" pero seguiría funcionando como
// promo al vender, que es confuso.
const _chkEsPromo = document.getElementById('chkEsPromo');
const _wrapPromoContenido = document.getElementById('wrapPromoContenido');

function _mostrarContenidoPromo(mostrar) {
    _wrapPromoContenido.classList.toggle('d-none', !mostrar);
    _renderResumenGruposEnPromo();
}

// Repite los grupos ya asignados en "Opciones/adicionales" DENTRO de la tarjeta "Componentes
// de la promo" con la misma pinta de fila que un componente fijo (ej. "Cafe con leche") — a
// pedido, para que se note de un vistazo que la promo tiene una parte "a elección" sin tener
// que subir a la otra sección. No duplica la ASIGNACIÓN en la base (agregar/quitar/editar
// límites se sigue haciendo desde "Opciones/adicionales"), pero el COSTO que muestra sí es
// real: usa costo_promedio (de ProductoModel::getGruposOpciones(), ya viene calculado del
// servidor) × la cantidad mínima a elegir, y ProductosController::asignarOpcionesGrupo() /
// quitarOpcionesGrupo() ya dejan ese mismo número persistido en productos.precio_costo cuando
// el producto es una promo — ver sincronizarPrecioCostoPromo(). Se re-arma cada vez que
// cambia _gruposOpcionesAsignados (agregar/quitar/editar límites) o el toggle "Es una promo".
//
// Definida SIN el guard <?php if ($esEdicion) ?> a propósito: _mostrarContenidoPromo()
// (arriba) la llama siempre, incluso al crear un producto nuevo, donde
// _gruposOpcionesAsignados ni siquiera llega a declararse (la sección de opciones pide
// guardar el producto primero) — de ahí el "typeof" en vez de usarla directo.
//
// OJO con _chkEsPromo: esta función también se llama desde _renderGruposOpciones() (ver
// arriba), invocada al cargar la página ANTES de la línea "const _chkEsPromo = ..." de
// más abajo — referenciar esa const acá explotaría con "Cannot access before
// initialization" (temporal dead zone; ni typeof la salva para let/const, a diferencia de
// una variable nunca declarada). Por eso se lee el checkbox directo del DOM.
function _renderResumenGruposEnPromo() {
    const wrap = document.getElementById('resumenGruposEnPromo');
    const chkPromo = document.getElementById('chkEsPromo');
    if (!wrap || !chkPromo) return;
    const grupos = typeof _gruposOpcionesAsignados !== 'undefined' ? _gruposOpcionesAsignados : [];
    if (!chkPromo.checked || !grupos.length) {
        wrap.classList.add('d-none');
        wrap.innerHTML = '';
        _actualizarTotalCostoPromo();
        return;
    }
    wrap.classList.remove('d-none');
    // Misma pinta de fila que los componentes fijos de arriba (ver _renderPromoComponentes),
    // para que se note de un vistazo que también forman parte de la promo — con la diferencia
    // de que acá no hay cantidad para editar ni botón de quitar: eso se sigue haciendo desde
    // "Opciones/adicionales", esto es solo el espejo de lectura.
    wrap.innerHTML = `
        <small class="text-muted d-block mb-1">🔀 También tiene estas opciones a elegir (definidas arriba, en "Opciones/adicionales"):</small>
        ${grupos.map(g => {
            const min = parseInt(g.minimo_selecciones, 10) || 0;
            const max = parseInt(g.maximo_selecciones, 10) || 0;
            const promedio = parseFloat(g.costo_promedio) || 0;
            const totalOpciones = parseInt(g.total_opciones, 10) || 0;
            return `
            <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="fw-semibold small">${esc(g.nombre)}
                    <span class="badge bg-info-soft text-info ms-1" style="font-size:.65rem">
                        elegir ${min}-${max}${+g.obligatorio ? ', obligatorio' : ''}
                    </span>
                    <span class="text-muted d-block" style="font-size:.72rem">
                        $ ${promedio.toLocaleString('es-AR',{minimumFractionDigits:2})} c/u prom.${totalOpciones ? ` de ${totalOpciones} opción(es)` : ''} — varía según cuál elija el cliente
                    </span>
                </span>
                <div class="d-flex align-items-center gap-1">
                    <span class="text-muted small">× ${min}</span>
                </div>
            </div>`;
        }).join('')}
    `;
    _actualizarTotalCostoPromo();
}

_chkEsPromo.addEventListener('change', function() {
    if (this.checked) { _mostrarContenidoPromo(true); return; }

    if (!_promoComponentes.length) { _mostrarContenidoPromo(false); return; }

    // showConfirm() no tiene callback de cancelación — revertimos el toggle ya mismo,
    // así "cancelar" no hace nada (queda como estaba) y solo se apaga si confirma.
    this.checked = true;

    const desactivar = async () => {
        if (ES_EDICION) {
            for (const c of [..._promoComponentes]) {
                const fd = new FormData();
                fd.append('producto_id', PRODUCTO_ID);
                fd.append('componente_producto_id', c.producto_id);
                fd.append('_csrf_token', CSRF_TOKEN);
                await fetch(`${BASE_URL}/productos/quitar-promo-componente`, { method: 'POST', body: fd });
            }
        }
        _promoComponentes = [];
        _renderPromoComponentes();
        _chkEsPromo.checked = false;
        _mostrarContenidoPromo(false);
    };

    const msg = `¿Quitar los ${_promoComponentes.length} componente(s) cargados?`;
    if (typeof showConfirm === 'function') {
        showConfirm(msg, desactivar, { label: 'Quitar todos', sub: ES_EDICION ? 'Esta acción no se puede deshacer.' : '' });
    } else {
        if (confirm(msg)) desactivar();
    }
});

_mostrarContenidoPromo(_promoComponentes.length > 0);
_chkEsPromo.checked = _promoComponentes.length > 0;
_renderPromoComponentes();

// ── Stock mínimo no aplica si el producto no controla stock ──────────────────
function _toggleStockMinimo() {
    const activo = document.getElementById('chkControlaStock').checked;
    document.getElementById('inputStockMinimo').disabled = !activo;
    document.getElementById('bloqueStockMinimo').classList.toggle('opacity-50', !activo);
}
document.getElementById('chkControlaStock').addEventListener('change', _toggleStockMinimo);
_toggleStockMinimo();

// ── Preview en vivo del costo real, desglosado en base + desvío ───────────────
function _formatMonedaLocal(v) {
    return '$ ' + v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function _actualizarPreviewDesvio() {
    const preview = document.getElementById('previewCostoDesvio');
    if (!preview) return;
    const pct         = parseFloat(document.getElementById('inputDesvioPct').value) || 0;
    const costoDesvio = PRECIO_COSTO_BASE * (pct / 100);
    document.getElementById('previewCostoBase').textContent       = _formatMonedaLocal(PRECIO_COSTO_BASE);
    document.getElementById('previewCostoSoloDesvio').textContent = _formatMonedaLocal(costoDesvio);
    preview.textContent = _formatMonedaLocal(PRECIO_COSTO_BASE + costoDesvio);
}
document.getElementById('inputDesvioPct').addEventListener('input', _actualizarPreviewDesvio);
_actualizarPreviewDesvio();

// ── Pool de recetas disponibles ───────────────────────────────────────────────
const _RECETAS_POOL = <?= json_encode($_recetasPool, JSON_UNESCAPED_UNICODE) ?>;

// Map id → {id, nombre, costo, rendimiento} — inicializado con las ya asignadas (modo edición).
// "costo" es el costo de UNA TANDA completa de esa receta (ej. si rinde 9 unidades, es el
// costo de las 9 juntas) — se divide por "rendimiento" al mostrar/sumar, porque este producto
// vende de a 1 unidad. Sin esa división el total de acá no coincide con lo que realmente queda
// guardado en productos.precio_costo (que SÍ divide — ver
// ProductoModel::sincronizarPrecioCostoPorPivote()), un desfasaje real que se reportó como
// "la receta asignada dice $17.737 pero el resto de la ficha dice $1.970".
const _selectedRecetas = new Map(<?= json_encode(
    array_map(fn($r) => [(int)$r['id'], ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'costo' => (float)($r['costo'] ?? 0), 'rendimiento' => (float)($r['rendimiento'] ?? 1) ?: 1]],
    $recetasAsignadas),
    JSON_UNESCAPED_UNICODE
) ?>);

function _renderSelectedRecetas() {
    const container = document.getElementById('listaRecetasSeleccionadas');
    if (!_selectedRecetas.size) {
        container.innerHTML = '<p class="text-muted small mb-0">Sin recetas seleccionadas.</p>';
        document.getElementById('totalCostoForm').textContent = '$ 0,00';
        return;
    }
    // r.costo es el costo de UNA TANDA completa de la receta (ej. rinde 9 → costo de las 9
    // juntas); r.rendimiento cuántas unidades da esa tanda. Se divide acá porque este producto
    // se vende de a 1 — sin dividir, el total no coincidiría con productos.precio_costo (que
    // SÍ divide, ver ProductoModel::sincronizarPrecioCostoPorPivote()) y confunde (reportado:
    // "la receta asignada dice $17.737 pero el resto de la ficha dice $1.970" — mismo número,
    // solo que uno era el total de la tanda y el otro ya el costo por unidad).
    let total = 0, html = '';
    _selectedRecetas.forEach(r => {
        const rendimiento  = parseFloat(r.rendimiento) || 1;
        const costoPorUnidad = parseFloat(r.costo || 0) / rendimiento;
        total += costoPorUnidad;
        html += `<div class="d-flex align-items-center justify-content-between py-1 border-bottom">
            <div>
                <span class="fw-semibold small">${_escR(r.nombre)}</span>
                ${r.costo ? `<span class="text-muted small ms-1">$ ${costoPorUnidad.toLocaleString('es-AR',{minimumFractionDigits:2})} c/u${rendimiento > 1 ? ` <span title="Costo de la tanda completa: $${parseFloat(r.costo).toLocaleString('es-AR',{minimumFractionDigits:2})} ÷ rinde ${rendimiento}">(tanda: $${parseFloat(r.costo).toLocaleString('es-AR',{minimumFractionDigits:2})} ÷ ${rendimiento})</span>` : ''}</span>` : ''}
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ms-1"
                onclick="_removeReceta(${r.id})">✕</button>
        </div>`;
    });
    container.innerHTML = html;
    document.getElementById('totalCostoForm').textContent =
        '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits:2});
}

async function _addReceta(receta) {
    if (_selectedRecetas.has(receta.id)) { _hideSearch(); return; }
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('producto_id', PRODUCTO_ID);
        fd.append('receta_id', receta.id);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/productos/asignar-receta`, {method:'POST',body:fd})).json();
            if (d.ok) {
                const found = d.recetas.find(x => x.id == receta.id);
                _selectedRecetas.set(receta.id, {
                    id: receta.id, nombre: receta.nombre,
                    costo: found ? parseFloat(found.costo) : 0,
                    rendimiento: found ? (parseFloat(found.rendimiento) || 1) : 1,
                });
                _renderSelectedRecetas();
            } else { showToast(d.error, 'danger'); }
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _selectedRecetas.set(receta.id, {id: receta.id, nombre: receta.nombre, costo: 0, rendimiento: 1});
        _renderSelectedRecetas();
    }
    _hideSearch();
    document.getElementById('searchRecetaInput').value = '';
}

async function _removeReceta(recetaId) {
    if (ES_EDICION) {
        const fd = new FormData();
        fd.append('producto_id', PRODUCTO_ID);
        fd.append('receta_id', recetaId);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const d = await (await fetch(`${BASE_URL}/productos/quitar-receta`, {method:'POST',body:fd})).json();
            if (d.ok) { _selectedRecetas.delete(recetaId); _renderSelectedRecetas(); }
            else showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    } else {
        _selectedRecetas.delete(recetaId);
        _renderSelectedRecetas();
    }
}

// ── Dropdown de recetas ───────────────────────────────────────────────────────
const _srchInput   = document.getElementById('searchRecetaInput');
const _srchResults = document.getElementById('searchRecetaResults');
const _srchPanel   = document.getElementById('recetaDropdownPanel');

function _toggleRecetaSearch() {
    if (_srchPanel.style.display !== 'none') { _hideSearch(); return; }
    _srchPanel.style.display = '';
    _srchInput.focus();
    _filterRecetas('');
}

function _filterRecetas(q) {
    const matches = _RECETAS_POOL.filter(r => r.nombre.toLowerCase().includes(q) && !_selectedRecetas.has(r.id));
    if (!matches.length) {
        _srchResults.innerHTML = '<div class="px-3 py-2 text-muted small">Sin resultados.</div>';
        return;
    }
    _srchResults.innerHTML = matches.slice(0, 20).map(r =>
        `<button type="button" class="w-100 text-start border-0 bg-transparent px-3 py-2 small _rOpt" data-rid="${r.id}"
             style="cursor:pointer;display:block">${_escR(r.nombre)}</button>`
    ).join('');
    _srchResults.querySelectorAll('._rOpt').forEach(btn => {
        btn.addEventListener('mouseover', () => btn.style.background = '#f0f0f0');
        btn.addEventListener('mouseout',  () => btn.style.background = '');
        btn.addEventListener('click', () => {
            const r = _RECETAS_POOL.find(x => x.id == btn.dataset.rid);
            if (r) _addReceta(r);
        });
    });
}

_srchInput.addEventListener('input', function() {
    _filterRecetas(this.value.trim().toLowerCase());
});

function _hideSearch() {
    _srchPanel.style.display = 'none';
    _srchInput.value = '';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#wrapperRecetaDropdown')) _hideSearch();
});

function _escR(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Submit ────────────────────────────────────────────────────────────────────
document.getElementById('formProducto').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');
    const btn = this.querySelector('[type="submit"]');
    btn.disabled = true;

    const isEdit = !!document.querySelector('input[name="id"]')?.value;
    const url    = isEdit ? `${BASE_URL}/productos/actualizar` : `${BASE_URL}/productos/crear`;
    const fd     = new FormData(this);
    ['ecommerce', 'activo', 'controla_stock'].forEach(n => { if (!fd.has(n)) fd.set(n, '0'); });

    try {
        const resp = await fetch(url, { method: 'POST', body: fd });
        const data = await resp.json();
        if (!data.ok) {
            errEl.textContent = data.error;
            errEl.classList.remove('d-none');
            btn.disabled = false;
            return;
        }
        // Producto nuevo: asignar recetas seleccionadas en loop
        if (!isEdit && _selectedRecetas.size) {
            for (const [rid] of _selectedRecetas) {
                const f2 = new FormData();
                f2.append('producto_id', data.id);
                f2.append('receta_id', rid);
                f2.append('_csrf_token', CSRF_TOKEN);
                await fetch(`${BASE_URL}/productos/asignar-receta`, {method:'POST', body:f2});
            }
        }
        // Producto nuevo: asignar los componentes de promo armados antes de guardar
        if (!isEdit && _promoComponentes.length) {
            for (const c of _promoComponentes) {
                const f3 = new FormData();
                f3.append('producto_id', data.id);
                f3.append('componente_producto_id', c.producto_id);
                f3.append('cantidad', c.cantidad);
                f3.append('_csrf_token', CSRF_TOKEN);
                await fetch(`${BASE_URL}/productos/asignar-promo-componente`, {method:'POST', body:f3});
            }
        }
        showToast(data.mensaje, 'success');
        setTimeout(() => window.location.href = `${BASE_URL}/productos`, 900);
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
        btn.disabled = false;
    }
});

_renderSelectedRecetas();

// ── "El cliente va a ver: $ X" (tarea 0.3) ───────────────────────────────────
// Espeja en vivo el cálculo de PrecioHelper::precioFinalOnline(): base = precio online si
// tiene valor, si no el del local; y se le suma el IVA solo si los precios cargados no lo
// traen. PRECIOS_INCLUYEN_IVA viene de la configuración fiscal (opción D9), no de la
// condición fiscal del negocio.
const PRECIOS_INCLUYEN_IVA = <?= $preciosIncluyenIva ? 'true' : 'false' ?>;

function actualizarPrecioCliente() {
    const avisoEl = document.getElementById('avisoPrecioCliente');
    if (!avisoEl) return;

    const online = document.getElementById('inpPrecioOnline').value.trim();
    const local  = parseFloat(document.getElementById('inpPrecioVenta').value) || 0;
    const iva    = parseFloat(document.getElementById('selIva').value) || 0;

    const base  = online !== '' ? (parseFloat(online) || 0) : local;
    const final = PRECIOS_INCLUYEN_IVA ? base : base * (1 + iva / 100);

    if (base <= 0) { avisoEl.textContent = ''; return; }

    avisoEl.textContent = 'El cliente va a ver: $ ' +
        final.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
        (online === '' ? ' (usa el precio del local)' : '');
}

['inpPrecioOnline', 'inpPrecioVenta', 'selIva'].forEach(id => {
    const el = document.getElementById(id);
    el?.addEventListener('input', actualizarPrecioCliente);
    el?.addEventListener('change', actualizarPrecioCliente);
});
actualizarPrecioCliente();
</script>
