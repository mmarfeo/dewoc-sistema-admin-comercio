<?php $esEdicion = !empty($activo); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar activo fijo' : 'Nuevo activo fijo' ?></h1>
    <a href="<?= url('activos') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<form id="formActivo" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?>
    <input type="hidden" name="id" value="<?= $activo['id'] ?>">
    <?php endif; ?>

    <?php if (!$esEdicion): ?>
    <div class="card border-0 mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold d-block mb-2">¿Es una compra nueva, ya lo tenías, o viene de una compra ya cargada?</label>
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="origen" id="origenNueva" value="compra_nueva" checked>
                <label class="btn btn-outline-primary" for="origenNueva">Compra nueva</label>
                <input type="radio" class="btn-check" name="origen" id="origenPreexistente" value="preexistente">
                <label class="btn btn-outline-primary" for="origenPreexistente">Ya lo tenía</label>
                <input type="radio" class="btn-check" name="origen" id="origenCompraExistente" value="compra_existente">
                <label class="btn btn-outline-primary" for="origenCompraExistente">Viene de una compra ya cargada</label>
            </div>
            <div class="form-text mt-2" id="origenAyuda">Vas a cargar la fecha real en la que lo compraste.</div>
        </div>
    </div>

    <!-- "Viene de una compra ya cargada": convierte una línea de facturas_compra_detalle ya
         guardada (algo que se cargó por error como materia prima/insumo/producto normal) en
         activo fijo — le revierte el stock/costo y la saca de esa factura (ver
         ActivoFijoModel::crearDesdeLineaCompra()). A diferencia de "Compra nueva"/"Ya lo
         tenía" (que arman un activo desde cero), acá primero hay que ENCONTRAR la línea. -->
    <div class="card border-0 mb-3 d-none" id="cardBuscarCompra">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Buscar la compra ya cargada</h6>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">
                Buscá por el nombre con el que se cargó el ítem en la compra (materia prima,
                insumo o producto). Al elegirlo, se saca del detalle de esa factura y sus
                datos precargan el formulario de abajo (editables antes de confirmar).
            </p>
            <div class="position-relative" id="wrapperBuscarCompra">
                <input type="text" id="searchCompraInput" class="form-control" placeholder="Ej: cafetera, horno, notebook…" autocomplete="off">
                <div id="searchCompraResults" class="border rounded bg-white shadow-sm position-absolute w-100"
                     style="display:none;z-index:1055;top:calc(100% + 2px);left:0;max-height:260px;overflow-y:auto"></div>
            </div>
            <div id="compraElegidaInfo" class="alert alert-success-soft py-2 px-2 small mt-2 d-none"></div>
            <input type="hidden" name="factura_compra_detalle_id" id="facturaCompraDetalleId" value="">
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Datos del activo</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150"
                           value="<?= e($activo['nombre'] ?? '') ?>" placeholder="Ej: Horno pizzero, Cafetera espresso, Mostrador">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Categoría</label>
                    <input type="text" name="categoria" class="form-control" maxlength="80"
                           value="<?= e($activo['categoria'] ?? '') ?>" placeholder="Ej: Cocina, Mobiliario">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold" id="labelFecha">
                        <?= $esEdicion ? 'Fecha de alta' : 'Fecha de compra' ?>
                    </label>
                    <input type="date" name="fecha_alta" class="form-control" <?= $esEdicion ? 'disabled' : 'required' ?>
                           value="<?= e($activo['fecha_alta'] ?? date('Y-m-d')) ?>">
                    <?php if ($esEdicion): ?>
                    <div class="form-text">No se puede editar una vez cargado — si está mal, eliminá y volvé a cargar.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="valor" class="form-control" step="0.01" min="0.01" <?= $esEdicion ? 'disabled' : 'required' ?>
                               value="<?= e($activo['valor'] ?? '') ?>">
                    </div>
                    <?php if ($esEdicion): ?>
                    <div class="form-text">Costo histórico, fijo. El valor actual se calcula solo restando lo amortizado.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Vida útil (meses) <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <input type="number" name="vida_util_meses" class="form-control" min="1" <?= $esEdicion ? 'disabled' : '' ?>
                           value="<?= e($activo['vida_util_meses'] ?? '') ?>" placeholder="Ej: 36">
                    <?php if (!$esEdicion): ?>
                    <div class="form-text">Si la cargás, se genera solo un gasto mensual de amortización (valor ÷ meses) hasta cubrir el total.</div>
                    <?php else: ?>
                    <div class="form-text">
                        <?= $activo['gasto_recurrente_id'] ? 'Ya tiene amortización activa.' : 'Sin amortización.' ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Proveedor <span class="text-muted fw-normal">(opcional)</span></label>
                    <select name="proveedor_id" class="form-select">
                        <option value="">— Sin proveedor —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int)($activo['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['razon_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($esEdicion): ?>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="activo" <?= $activo['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="baja" <?= $activo['estado'] === 'baja' ? 'selected' : '' ?>>De baja</option>
                        <option value="vendido" <?= $activo['estado'] === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                    </select>
                    <div class="form-text">Dar de baja o vender detiene la amortización mensual (si tenía).</div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control" rows="2" maxlength="500"><?= e($activo['notas'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar' : 'Crear activo' ?>
        </button>
        <a href="<?= url('activos') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
</div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;

<?php if (!$esEdicion): ?>
document.querySelectorAll('input[name="origen"]').forEach(r => {
    r.addEventListener('change', function () {
        const ayuda       = document.getElementById('origenAyuda');
        const labelFecha  = document.getElementById('labelFecha');
        const cardBuscar  = document.getElementById('cardBuscarCompra');
        const esCompraExistente = this.value === 'compra_existente';

        cardBuscar.classList.toggle('d-none', !esCompraExistente);
        if (this.value === 'preexistente') {
            ayuda.textContent = 'La fecha de alta va a ser hoy — no hace falta que sea la fecha real de compra.';
            labelFecha.textContent = 'Fecha de alta';
        } else if (esCompraExistente) {
            ayuda.textContent = 'Elegí la línea de la compra más abajo — precarga el resto del formulario.';
            labelFecha.textContent = 'Fecha de compra';
        } else {
            ayuda.textContent = 'Vas a cargar la fecha real en la que lo compraste.';
            labelFecha.textContent = 'Fecha de compra';
        }
    });
});

// ── Buscar compra ya cargada ──────────────────────────────────────────────────
const _searchCompraInput   = document.getElementById('searchCompraInput');
const _searchCompraResults = document.getElementById('searchCompraResults');
let _searchCompraTimeout   = null;

function escActivo(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

_searchCompraInput?.addEventListener('input', function () {
    clearTimeout(_searchCompraTimeout);
    const q = this.value.trim();
    if (q.length < 2) { _searchCompraResults.style.display = 'none'; return; }
    _searchCompraTimeout = setTimeout(async () => {
        try {
            const r = await fetch(`${BASE_URL}/activos/buscar-compras?q=${encodeURIComponent(q)}`);
            const d = await r.json();
            const resultados = d.ok ? d.resultados : [];
            if (!resultados.length) {
                _searchCompraResults.innerHTML = '<div class="px-3 py-2 text-muted small">Sin resultados.</div>';
            } else {
                _searchCompraResults.innerHTML = resultados.map(res => `
                    <button type="button" class="w-100 text-start border-0 bg-transparent px-3 py-2 small _compraOpt"
                        data-json='${JSON.stringify(res).replace(/'/g, "&#39;")}' style="cursor:pointer;display:block">
                        <strong>${escActivo(res.nombre)}</strong>
                        <span class="text-muted d-block" style="font-size:11px">
                            ${escActivo(res.proveedor_nombre || 'Sin proveedor')} — ${escActivo(res.fecha)}${res.nro_comprobante ? ' — #' + escActivo(res.nro_comprobante) : ''}
                            — $${parseFloat(res.subtotal_con_iva).toLocaleString('es-AR',{minimumFractionDigits:2})}
                        </span>
                    </button>
                `).join('');
                _searchCompraResults.querySelectorAll('._compraOpt').forEach(btn => {
                    btn.addEventListener('mouseover', () => btn.style.background = '#f0f0f0');
                    btn.addEventListener('mouseout',  () => btn.style.background = '');
                    btn.addEventListener('click', () => _elegirLineaCompra(JSON.parse(btn.dataset.json)));
                });
            }
            _searchCompraResults.style.display = 'block';
        } catch {
            _searchCompraResults.innerHTML = '<div class="px-3 py-2 text-danger small">Error al buscar.</div>';
            _searchCompraResults.style.display = 'block';
        }
    }, 300);
});
document.addEventListener('click', function (e) {
    if (!document.getElementById('wrapperBuscarCompra')?.contains(e.target)) {
        _searchCompraResults.style.display = 'none';
    }
});

function _elegirLineaCompra(res) {
    document.getElementById('facturaCompraDetalleId').value = res.detalle_id;
    document.querySelector('input[name="nombre"]').value = res.nombre;
    document.querySelector('input[name="valor"]').value = parseFloat(res.subtotal_con_iva).toFixed(2);
    document.querySelector('input[name="fecha_alta"]').value = res.fecha;
    const selProv = document.querySelector('select[name="proveedor_id"]');
    if (selProv && res.proveedor_id) selProv.value = res.proveedor_id;

    const info = document.getElementById('compraElegidaInfo');
    info.classList.remove('d-none');
    info.textContent = `✓ Se va a convertir "${res.nombre}" de la compra #${res.factura_compra_id}${res.nro_comprobante ? ' (' + res.nro_comprobante + ')' : ''} — esa línea se saca de la factura original y su stock se revierte.`;

    _searchCompraInput.value = res.nombre;
    _searchCompraResults.style.display = 'none';
}
<?php endif; ?>

document.getElementById('formActivo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');

    const origenSel = document.querySelector('input[name="origen"]:checked');
    if (origenSel && origenSel.value === 'compra_existente' && !document.getElementById('facturaCompraDetalleId').value) {
        errEl.textContent = 'Buscá y elegí la línea de la compra antes de guardar.';
        errEl.classList.remove('d-none');
        return;
    }

    document.getElementById('btnGuardar').disabled = true;

    const fd = new FormData(this);
    const endpoint = ES_EDICION ? `${BASE_URL}/activos/actualizar` : `${BASE_URL}/activos/crear`;

    try {
        const r = await fetch(endpoint, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/activos`; }, 800);
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnGuardar').disabled = false;
    }
});
</script>
