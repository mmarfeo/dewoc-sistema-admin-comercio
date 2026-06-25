<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Nueva factura de compra</h1>
    <a href="<?= url('compras') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formCompra" novalidate>
    <?= Csrf::field() ?>

    <div class="row g-3">
        <!-- Columna izquierda: cabecera -->
        <div class="col-lg-4">
            <div class="card border-0 mb-3">
                <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos del comprobante</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proveedor <span class="text-danger">*</span></label>
                        <select name="proveedor_id" id="fProveedor" class="form-select" required>
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($proveedores as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de comprobante</label>
                        <select name="tipo_comprobante" id="fTipo" class="form-select">
                            <option value="factura_a">Factura A</option>
                            <option value="factura_b" selected>Factura B</option>
                            <option value="factura_c">Factura C</option>
                            <option value="ticket">Ticket</option>
                            <option value="remito">Remito</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nro. comprobante</label>
                        <input type="text" name="nro_comprobante" class="form-control" placeholder="0001-00012345">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Condición de pago</label>
                        <select name="condicion_pago" class="form-select">
                            <option value="contado">Contado</option>
                            <option value="cuenta_corriente">Cuenta corriente</option>
                            <option value="cheque">Cheque</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notas</label>
                        <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Resumen totales -->
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Resumen</h6>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Subtotal sin IVA</span>
                        <span id="resSubtotal" class="fw-semibold">$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">IVA</span>
                        <span id="resIva" class="fw-semibold">$ 0,00</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">TOTAL</span>
                        <span id="resTotal" class="fw-bold text-primary fs-5">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: items -->
        <div class="col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <h6 class="fw-semibold mb-0">Ítems de la factura</h6>
                    <div class="input-group input-group-sm" style="max-width:340px">
                        <select id="filtroTipo" class="form-select form-select-sm" style="max-width:140px">
                            <option value="">Todos los tipos</option>
                            <option value="materia_prima">Materia prima</option>
                            <option value="insumo">Insumo</option>
                            <option value="producto">Producto reventa</option>
                        </select>
                        <input type="text" id="buscadorItem" class="form-control" placeholder="Buscar por nombre o código...">
                        <button class="btn btn-outline-secondary" type="button" id="btnBuscar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Resultados de búsqueda -->
                <div id="resultadosBusqueda" class="card-body pt-0 pb-2 d-none">
                    <div class="list-group list-group-flush small" id="listaResultados"></div>
                </div>

                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="tblItems">
                            <thead class="table-light">
                                <tr>
                                    <th>Ítem</th><th>Tipo</th><th style="width:90px">Cant.</th>
                                    <th style="width:110px">P. unit. ($)</th>
                                    <th style="width:90px">IVA %</th>
                                    <th style="width:100px">Subtotal</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoItems"></tbody>
                            <tfoot>
                                <tr id="rowVacio">
                                    <td colspan="7" class="text-center text-muted py-3 small">
                                        Busque y agregue ítems arriba
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Input oculto con JSON de items -->
    <input type="hidden" name="items_json" id="itemsJson" value="[]">

    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">Guardar factura</button>
        <a href="<?= url('compras') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

let items = [];  // [{tipo_item, item_id, nombre, cantidad, precio_unitario, iva_porcentaje, unidad}, ...]

// ── Búsqueda ──────────────────────────────────────────────────────────────────
let buscarTimeout;
document.getElementById('buscadorItem').addEventListener('input', function() {
    clearTimeout(buscarTimeout);
    if (this.value.length < 2) { ocultarResultados(); return; }
    buscarTimeout = setTimeout(buscar, 300);
});
document.getElementById('btnBuscar').addEventListener('click', buscar);

async function buscar() {
    const q    = document.getElementById('buscadorItem').value.trim();
    const tipo = document.getElementById('filtroTipo').value;
    if (q.length < 2) return;
    const r    = await fetch(`${BASE_URL}/compras/buscar-items?q=${encodeURIComponent(q)}&tipo=${tipo}`);
    const data = await r.json();
    mostrarResultados(data);
}

function mostrarResultados(lista) {
    const cont = document.getElementById('listaResultados');
    cont.innerHTML = '';
    if (!lista.length) {
        cont.innerHTML = '<div class="list-group-item text-muted small">Sin resultados.</div>';
        document.getElementById('resultadosBusqueda').classList.remove('d-none');
        return;
    }
    lista.forEach(it => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action py-2';
        btn.innerHTML = `<span class="fw-semibold">${e(it.nombre)}</span>
            <span class="text-muted small ms-2">${e(it.codigo||'')}</span>
            <span class="badge bg-secondary-soft text-secondary float-end">${e(it.tipo.replace('_',' '))}</span>`;
        btn.addEventListener('click', () => agregarItem(it));
        cont.appendChild(btn);
    });
    document.getElementById('resultadosBusqueda').classList.remove('d-none');
}

function ocultarResultados() {
    document.getElementById('resultadosBusqueda').classList.add('d-none');
}

function e(v) { const d=document.createElement('div'); d.textContent=v||''; return d.innerHTML; }

// ── Items ─────────────────────────────────────────────────────────────────────
function agregarItem(it) {
    items.push({
        tipo_item:       it.tipo,
        item_id:         it.id,
        nombre:          it.nombre,
        cantidad:        1,
        precio_unitario: parseFloat(it.costo_unitario) || 0,
        iva_porcentaje:  21,
        unidad:          it.unidad || '',
    });
    ocultarResultados();
    document.getElementById('buscadorItem').value = '';
    renderItems();
}

function renderItems() {
    const tbody = document.getElementById('cuerpoItems');
    const rowV  = document.getElementById('rowVacio');
    tbody.innerHTML = '';

    if (!items.length) { rowV.style.display=''; sincronizarJSON(); actualizarTotales(); return; }
    rowV.style.display = 'none';

    items.forEach((it, idx) => {
        const sub = it.cantidad * it.precio_unitario;
        const tr  = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-semibold small">${e(it.nombre)} <span class="text-muted">${e(it.unidad)}</span></td>
            <td><span class="badge bg-secondary-soft text-secondary small">${e(it.tipo_item.replace('_',' '))}</span></td>
            <td><input type="number" class="form-control form-control-sm" min="0.001" step="0.001" value="${it.cantidad}"
                onchange="updateItem(${idx},'cantidad',this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" min="0" step="0.01" value="${it.precio_unitario}"
                onchange="updateItem(${idx},'precio_unitario',this.value)"></td>
            <td>
                <select class="form-select form-select-sm" onchange="updateItem(${idx},'iva_porcentaje',this.value)">
                    <option value="0"  ${it.iva_porcentaje==0  ?'selected':''}>0% (Exento)</option>
                    <option value="10.5" ${it.iva_porcentaje==10.5?'selected':''}>10,5%</option>
                    <option value="21" ${it.iva_porcentaje==21 ?'selected':''}>21%</option>
                </select>
            </td>
            <td class="text-end fw-semibold small">${formatMoneda(sub)}</td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0"
                onclick="quitarItem(${idx})">✕</button></td>`;
        tbody.appendChild(tr);
    });

    sincronizarJSON();
    actualizarTotales();
}

function updateItem(idx, campo, val) {
    items[idx][campo] = campo === 'cantidad' || campo === 'precio_unitario' || campo === 'iva_porcentaje'
        ? parseFloat(val) : val;
    renderItems();
}

function quitarItem(idx) { items.splice(idx, 1); renderItems(); }

function sincronizarJSON() {
    document.getElementById('itemsJson').value = JSON.stringify(items);
}

function actualizarTotales() {
    let sub = 0, iva = 0;
    items.forEach(it => {
        const s = it.cantidad * it.precio_unitario;
        sub += s;
        iva += s * (it.iva_porcentaje / 100);
    });
    document.getElementById('resSubtotal').textContent = formatMoneda(sub);
    document.getElementById('resIva').textContent       = formatMoneda(iva);
    document.getElementById('resTotal').textContent     = formatMoneda(sub + iva);
}

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formCompra').addEventListener('submit', async function(ev) {
    ev.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');

    if (!document.getElementById('fProveedor').value) {
        errEl.textContent = 'Seleccione un proveedor.'; errEl.classList.remove('d-none'); return;
    }
    if (!items.length) {
        errEl.textContent = 'Agregue al menos un ítem.'; errEl.classList.remove('d-none'); return;
    }

    document.getElementById('btnGuardar').disabled = true;
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/compras/guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/compras/ver?id=${d.id}`; }, 800);
        } else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
    finally  { document.getElementById('btnGuardar').disabled = false; }
});

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('#resultadosBusqueda') && !e.target.closest('#buscadorItem') && !e.target.closest('#btnBuscar')) {
        ocultarResultados();
    }
});
</script>
