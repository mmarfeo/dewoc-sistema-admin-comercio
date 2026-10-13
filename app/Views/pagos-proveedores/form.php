<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Registrar Pago</h1>
    <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
</div>

<form id="formPago" enctype="multipart/form-data">
<?= Csrf::field() ?>

<div class="row g-4">

    <!-- ── Columna izquierda: cabecera ─────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Datos del pago</h6>

                <!-- Tipo -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo de pago</label>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php foreach (['proveedor'=>'Proveedor','sueldo'=>'Sueldo','otro'=>'Otro','aporte'=>'Aporte de socio'] as $val=>$lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_<?= $val ?>"
                                   value="<?= $val ?>" <?= $val==='proveedor' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tipo_<?= $val ?>"><?= $lbl ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- "Aporte de socio": plata que un socio puso en la empresa SIN estar pagando
                         una factura/gasto/sueldo puntual (ej. un aporte de capital). Genera deuda
                         de la empresa hacia ese socio igual que cualquier otro pago con "Aporte de
                         socio" tildado más abajo — se ve en "Saldos pendientes" y se salda con
                         "Reembolsar a socio". -->
                    <div class="form-text d-none" id="avisoAporte">
                        Un aporte siempre queda registrado a nombre de un socio, y la empresa le
                        queda debiendo ese monto hasta que se lo reembolse (ver "Saldos pendientes
                        de pago" en el listado).
                    </div>
                </div>

                <!-- Proveedor (visible solo tipo=proveedor) -->
                <div class="mb-3" id="wrapProveedor">
                    <label class="form-label small fw-semibold">Proveedor <span class="text-danger">*</span></label>
                    <select name="proveedor_id" id="proveedorId" class="form-select form-select-sm">
                        <option value="">— Seleccioná un proveedor —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($preselect == $p['id']) ? 'selected' : '' ?>><?= e($p['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Empleado (visible solo tipo=sueldo) -->
                <div class="mb-3 d-none" id="wrapEmpleado">
                    <label class="form-label small fw-semibold">Empleado <span class="text-danger">*</span></label>
                    <select name="empleado_id" id="empleadoId" class="form-select form-select-sm">
                        <option value="">— Seleccioná un empleado —</option>
                        <?php foreach ($empleados as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($empleadoPreselect == $emp['id']) ? 'selected' : '' ?>><?= e($emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">¿No está en la lista? <a href="<?= url('empleados/nuevo') ?>" target="_blank">Creá el empleado</a> y volvé a esta pantalla.</div>
                </div>

                <!-- Beneficiario libre (solo tipo=otro) -->
                <div class="mb-3 d-none" id="wrapBeneficiario">
                    <label class="form-label small fw-semibold">Beneficiario <span class="text-danger">*</span></label>
                    <input type="text" name="beneficiario" class="form-control form-control-sm"
                           placeholder="Nombre u otro beneficiario">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Concepto</label>
                    <input type="text" name="concepto" class="form-control form-control-sm"
                           placeholder="Ej: Factura mayo, Sueldo junio…">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control form-control-sm" rows="2"></textarea>
                </div>

                <div class="mb-3" id="wrapOrigenPago">
                    <label class="form-label small fw-semibold">Origen del pago</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="origen_pago" id="origen_empresa"
                                   value="empresa" checked>
                            <label class="form-check-label" for="origen_empresa">Empresa</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="origen_pago" id="origen_socio"
                                   value="socio">
                            <label class="form-check-label" for="origen_socio">Aporte de socio</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3 d-none" id="wrapSocio">
                    <label class="form-label small fw-semibold">Socio <span class="text-danger">*</span></label>
                    <select name="socio_id" class="form-select form-select-sm">
                        <option value="">— Seleccionar socio —</option>
                        <?php foreach ($socios as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($socios)): ?>
                    <div class="form-text text-warning">
                        No hay socios cargados — <a href="<?= url('socios') ?>" target="_blank">creá uno primero</a>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Columna derecha: métodos de pago ────────────────────────── -->
    <div class="col-lg-7">
        <!-- Pendiente de liquidar (solo tipo=sueldo con empleado seleccionado) -->
        <div class="card border-0 mb-3 d-none" id="cardPendientes">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Pendiente de liquidar</h6>
                <p class="small text-muted mb-2">Destildá lo que no quieras incluir en este pago — se recalcula el total sugerido.</p>
                <div id="pendientesResumen"></div>
                <div class="d-flex justify-content-between align-items-center mt-2 p-2 rounded" style="background:#f0f7ff">
                    <span class="fw-semibold small">Total sugerido:</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-primary" id="pendientesTotal">$ 0,00</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAplicarSugerido">Aplicar a Efectivo</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Métodos de pago</h6>

                <div class="row g-2">
                    <?php
                    $metodos = [
                        'mercado_pago'  => ['label' => 'Mercado Pago',   'color' => 'primary'],
                        'efectivo'      => ['label' => 'Efectivo',        'color' => 'success'],
                        'debito'        => ['label' => 'Débito',          'color' => 'info'],
                        'transferencia' => ['label' => 'Transferencia',   'color' => 'warning'],
                        'cheque'        => ['label' => 'Cheque',          'color' => 'secondary'],
                        'otro'          => ['label' => 'Otro',            'color' => 'dark'],
                    ];
                    foreach ($metodos as $key => $m):
                    ?>
                    <div class="col-6">
                        <label class="form-label small mb-1">
                            <span class="badge bg-<?= $m['color'] ?>-soft text-<?= $m['color'] ?> me-1"><?= $m['label'] ?></span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="monto_<?= $key ?>" id="monto_<?= $key ?>"
                                   class="form-control monto-metodo" min="0" step="0.01"
                                   placeholder="0,00" value="0">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3 p-3 rounded" style="background:#f0f7ff">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Total del pago:</span>
                        <span class="h5 mb-0 fw-bold text-primary" id="totalPago">$ 0,00</span>
                    </div>
                </div>

                <!-- Referencia MP + Comprobante -->
                <div class="row g-2 mt-2" id="wrapMp">
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Nro de operación MP</label>
                        <input type="text" name="referencia_mp" class="form-control form-control-sm"
                               placeholder="Ej: 123456789">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1">Comprobante MP (imagen/PDF)</label>
                        <input type="file" name="comprobante" id="inputComprobante"
                               accept="image/jpeg,image/png,image/webp,application/pdf"
                               class="form-control form-control-sm">
                    </div>
                </div>

                <!-- Preview comprobante -->
                <div id="comprobantePreview" class="d-none mt-2">
                    <img id="comprobanteImg" src="" alt="" class="rounded border"
                         style="max-height:120px;object-fit:contain">
                    <span id="comprobantePdf" class="small text-muted d-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="#dc3545" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        PDF adjunto
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Facturas pendientes (cargadas vía AJAX) ──────────────── -->
        <div class="card border-0 mb-3" id="cardFacturas" style="display:none!important">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Aplicar a facturas pendientes <small class="text-muted fw-normal">(opcional)</small></h6>
                <p class="small text-muted mb-2">Seleccioná las facturas que cubre este pago e ingresá el monto aplicado a cada una.</p>
                <div id="tablaFacturas"></div>
            </div>
        </div>

        <!-- ── Gastos pendientes (alquiler, servicios, etc. — cargados vía AJAX) ── -->
        <div class="card border-0" id="cardGastos" style="display:none!important">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Aplicar a gastos pendientes <small class="text-muted fw-normal">(opcional)</small></h6>
                <p class="small text-muted mb-2">Alquiler, servicios u otros gastos con este proveedor que estén pendientes de pago.</p>
                <div id="tablaGastos"></div>
            </div>
        </div>
    </div>
</div>

<!-- Botón guardar -->
<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= url('pagos-proveedores') ?>" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary" id="btnGuardar">
        Registrar Pago
    </button>
</div>

</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

// ── Origen del pago ──────────────────────────────────────────────────────────
document.querySelectorAll('input[name="origen_pago"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('wrapSocio').classList.toggle('d-none', r.value !== 'socio');
    });
});

// ── Tipo de pago (proveedor / sueldo / otro / aporte) ────────────────────────
document.querySelectorAll('input[name="tipo"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('wrapProveedor').classList.toggle('d-none', r.value !== 'proveedor');
        document.getElementById('wrapEmpleado').classList.toggle('d-none', r.value !== 'sueldo');
        document.getElementById('wrapBeneficiario').classList.toggle('d-none', r.value !== 'otro');
        if (r.value !== 'proveedor') cargarFacturas(0); // limpiar tabla
        if (r.value !== 'sueldo') ocultarPendientes(); else cargarPendientes(document.getElementById('empleadoId').value);

        // Aporte: siempre es plata de un socio, no tiene sentido elegir "Empresa" — se
        // fuerza y se oculta el selector de origen (el server igual lo fuerza, ver
        // PagosProveedoresController::guardar()). Setear ".checked" a mano no dispara el
        // 'change' del radio de origen_pago, así que wrapSocio se muestra/oculta acá
        // directo en vez de depender de ese otro listener.
        const esAporte = r.value === 'aporte';
        document.getElementById('avisoAporte').classList.toggle('d-none', !esAporte);
        document.getElementById('wrapOrigenPago').classList.toggle('d-none', esAporte);
        if (esAporte) {
            document.getElementById('origen_socio').checked = true;
            document.getElementById('wrapSocio').classList.remove('d-none');
        } else {
            document.getElementById('wrapSocio').classList.toggle('d-none', !document.getElementById('origen_socio').checked);
        }
    });
});

// ── Pendiente de liquidar (tipo=sueldo) ──────────────────────────────────────
document.getElementById('empleadoId').addEventListener('change', function () {
    cargarPendientes(this.value);
});

function ocultarPendientes() {
    document.getElementById('cardPendientes').classList.add('d-none');
    document.getElementById('pendientesResumen').innerHTML = '';
    document.getElementById('pendientesTotal').textContent = '$ 0,00';
}

async function cargarPendientes(empleadoId) {
    if (!empleadoId) { ocultarPendientes(); return; }

    const r = await fetch(`${BASE_URL}/empleados/pendientes?empleado_id=${empleadoId}`);
    const d = await r.json();
    if (!d.ok) { ocultarPendientes(); return; }

    const card = document.getElementById('cardPendientes');
    const wrap = document.getElementById('pendientesResumen');

    const filas = [];

    if (d.base_mensual > 0) {
        const lbl = d.modalidad_pago === 'quincenal' ? 'Sueldo quincenal (base)' : 'Sueldo mensual (base)';
        filas.push(`
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                <span>${lbl}</span>
                <span class="fw-semibold">${formatMoney(d.base_mensual)}</span>
            </div>`);
    }

    d.jornadas.forEach(j => {
        const etiqueta = j.es_feriado == 1 ? ' <span class="badge bg-warning-soft text-warning">Feriado</span>' : '';
        filas.push(`
            <div class="form-check d-flex justify-content-between align-items-center py-1 border-bottom small">
                <span>
                    <input class="form-check-input pendiente-chk me-2" type="checkbox" name="jornada_ids[]" value="${j.id}" data-monto="${j.monto}" checked onchange="recalcularPendientes()">
                    ${escHtml(j.fecha)}${etiqueta}
                </span>
                <span class="fw-semibold">${formatMoney(j.monto)}</span>
            </div>`);
    });

    d.conceptos.forEach(c => {
        filas.push(`
            <div class="form-check d-flex justify-content-between align-items-center py-1 border-bottom small">
                <span>
                    <input class="form-check-input pendiente-chk me-2" type="checkbox" name="concepto_ids[]" value="${c.id}" data-monto="${c.monto}" checked onchange="recalcularPendientes()">
                    ${escHtml(c.tipo)} — ${escHtml(c.descripcion || c.fecha)}
                </span>
                <span class="fw-semibold">${formatMoney(c.monto)}</span>
            </div>`);
    });

    (d.ausencias || []).forEach(a => {
        filas.push(`
            <div class="form-check d-flex justify-content-between align-items-center py-1 border-bottom small">
                <span>
                    <input class="form-check-input pendiente-chk me-2" type="checkbox" name="ausencia_ids[]" value="${a.id}" data-monto="${-a.monto_descuento}" checked onchange="recalcularPendientes()">
                    Ausencia — ${escHtml(a.fecha)}
                </span>
                <span class="fw-semibold text-danger">-${formatMoney(a.monto_descuento)}</span>
            </div>`);
    });

    wrap.innerHTML = filas.length ? filas.join('') : '<p class="text-muted small mb-0">Sin jornadas ni conceptos pendientes — solo se pagará el sueldo base.</p>';
    wrap.dataset.baseMensual = d.base_mensual;
    card.classList.remove('d-none');
    recalcularPendientes();
}

function recalcularPendientes() {
    const wrap = document.getElementById('pendientesResumen');
    let total = parseFloat(wrap.dataset.baseMensual || 0);
    wrap.querySelectorAll('.pendiente-chk:checked').forEach(chk => {
        total += parseFloat(chk.dataset.monto || 0);
    });
    wrap.dataset.totalSugerido = total;
    document.getElementById('pendientesTotal').textContent = formatMoney(total);
}

document.getElementById('btnAplicarSugerido').addEventListener('click', () => {
    const total = parseFloat(document.getElementById('pendientesResumen').dataset.totalSugerido || 0);
    document.getElementById('monto_efectivo').value = total.toFixed(2);
    calcularTotal();
});

<?php if (!empty($empleadoPreselect)): ?>
cargarPendientes(<?= (int)$empleadoPreselect ?>);
<?php endif; ?>

// ── Cálculo total ────────────────────────────────────────────────────────────
document.querySelectorAll('.monto-metodo').forEach(inp => {
    inp.addEventListener('input', calcularTotal);
});

function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.monto-metodo').forEach(i => { total += parseFloat(i.value || 0); });
    document.getElementById('totalPago').textContent = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });

    // Mostrar/ocultar sección MP
    const mp = parseFloat(document.getElementById('monto_mercado_pago').value || 0);
    document.getElementById('wrapMp').style.display = mp > 0 ? '' : 'none';
}

// ── Preview comprobante adjunto ──────────────────────────────────────────────
document.getElementById('inputComprobante').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const prev = document.getElementById('comprobantePreview');
    const img  = document.getElementById('comprobanteImg');
    const pdf  = document.getElementById('comprobantePdf');
    prev.classList.remove('d-none');
    if (file.type === 'application/pdf') {
        img.classList.add('d-none');
        pdf.classList.remove('d-none');
    } else {
        const reader = new FileReader();
        reader.onload = ev => { img.src = ev.target.result; img.classList.remove('d-none'); pdf.classList.add('d-none'); };
        reader.readAsDataURL(file);
    }
});

// ── Cargar facturas y gastos pendientes del proveedor ────────────────────────
document.getElementById('proveedorId').addEventListener('change', function () {
    cargarFacturas(this.value);
    cargarGastosPendientes(this.value);
});

async function cargarFacturas(proveedorId) {
    const card = document.getElementById('cardFacturas');
    const tbody = document.getElementById('tablaFacturas');
    if (!proveedorId) { card.style.setProperty('display','none','important'); return; }

    const r = await fetch(`${BASE_URL}/pagos-proveedores/facturas-pendientes?proveedor_id=${proveedorId}`);
    const facturas = await r.json();

    if (!facturas.length) { card.style.setProperty('display','none','important'); return; }

    card.style.removeProperty('display');
    tbody.innerHTML = `
    <table class="table table-sm table-bordered small mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:32px"></th>
                <th>Comprobante</th><th>Fecha</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pagado</th>
                <th class="text-end text-danger">Saldo</th>
                <th style="width:130px">Monto a aplicar</th>
            </tr>
        </thead>
        <tbody id="filasFacturas">
        ${facturas.map(f => `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="form-check-input chk-factura"
                       data-saldo="${f.saldo}" data-id="${f.id}" onchange="toggleFactura(this)">
                <input type="hidden" name="factura_ids[]" value="" data-for="${f.id}">
            </td>
            <td>${escHtml(f.tipo_comprobante.toUpperCase().replace('_',' '))} ${escHtml(f.nro_comprobante??'')}</td>
            <td>${escHtml(f.fecha)}</td>
            <td class="text-end">${formatMoney(f.total_con_iva)}</td>
            <td class="text-end text-muted">${formatMoney(f.pagado)}</td>
            <td class="text-end text-danger fw-semibold">${formatMoney(f.saldo)}</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" name="factura_montos[]" id="monto_f_${f.id}"
                           class="form-control monto-factura" min="0" step="0.01"
                           placeholder="${f.saldo}" disabled value="" oninput="actualizarMontoMP()">
                </div>
            </td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function toggleFactura(chk) {
    const id  = chk.dataset.id;
    const inp = document.getElementById(`monto_f_${id}`);
    const hid = document.querySelector(`input[name="factura_ids[]"][data-for="${id}"]`);
    inp.disabled = !chk.checked;
    if (chk.checked) {
        hid.value = id;
        if (!inp.value || inp.value === '0') inp.value = chk.dataset.saldo;
    } else {
        hid.value = '';
        inp.value = '';
    }
    actualizarMontoMP();
}

// Precarga el total de las facturas/gastos tildados en Mercado Pago (método de pago
// por default para saldar proveedores) — se recalcula en cada click o edición manual
// del monto a aplicar, así el usuario solo ajusta si pagó por otro medio.
function actualizarMontoMP() {
    let total = 0;
    document.querySelectorAll('.monto-factura').forEach(i => { if (!i.disabled) total += parseFloat(i.value || 0); });
    document.querySelectorAll('.monto-gasto').forEach(i => { if (!i.disabled) total += parseFloat(i.value || 0); });
    document.getElementById('monto_mercado_pago').value = total > 0 ? total.toFixed(2) : '0';
    calcularTotal();
}

// ── Cargar gastos pendientes del proveedor (alquiler, servicios, etc.) ───────
async function cargarGastosPendientes(proveedorId) {
    const card  = document.getElementById('cardGastos');
    const tbody = document.getElementById('tablaGastos');
    if (!proveedorId) { card.style.setProperty('display','none','important'); return; }

    const r = await fetch(`${BASE_URL}/pagos-proveedores/gastos-pendientes?proveedor_id=${proveedorId}`);
    const gastos = await r.json();

    if (!gastos.length) { card.style.setProperty('display','none','important'); return; }

    card.style.removeProperty('display');
    tbody.innerHTML = `
    <table class="table table-sm table-bordered small mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:32px"></th>
                <th>Descripción</th><th>Fecha</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pagado</th>
                <th class="text-end text-danger">Saldo</th>
                <th style="width:130px">Monto a aplicar</th>
            </tr>
        </thead>
        <tbody id="filasGastos">
        ${gastos.map(g => `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="form-check-input chk-gasto"
                       data-saldo="${g.saldo}" data-id="${g.id}" onchange="toggleGasto(this)">
                <input type="hidden" name="gasto_ids[]" value="" data-for="${g.id}">
            </td>
            <td>${escHtml(g.descripcion)}</td>
            <td>${escHtml(g.fecha)}</td>
            <td class="text-end">${formatMoney(g.monto)}</td>
            <td class="text-end text-muted">${formatMoney(g.pagado)}</td>
            <td class="text-end text-danger fw-semibold">${formatMoney(g.saldo)}</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" name="gasto_montos[]" id="monto_g_${g.id}"
                           class="form-control monto-gasto" min="0" step="0.01"
                           placeholder="${g.saldo}" disabled value="" oninput="actualizarMontoMP()">
                </div>
            </td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function toggleGasto(chk) {
    const id  = chk.dataset.id;
    const inp = document.getElementById(`monto_g_${id}`);
    const hid = document.querySelector(`input[name="gasto_ids[]"][data-for="${id}"]`);
    inp.disabled = !chk.checked;
    if (chk.checked) {
        hid.value = id;
        if (!inp.value || inp.value === '0') inp.value = chk.dataset.saldo;
    } else {
        hid.value = '';
        inp.value = '';
    }
    actualizarMontoMP();
}

function formatMoney(v) {
    return '$ ' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2 });
}
function escHtml(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Envío del formulario ─────────────────────────────────────────────────────
document.getElementById('formPago').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fd = new FormData(this);
    fd.set('_csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(`${BASE_URL}/pagos-proveedores/guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast('Pago registrado correctamente.');
            setTimeout(() => window.location.href = `${BASE_URL}/pagos-proveedores/ver?id=${d.id}`, 700);
        } else {
            showToast(d.error || 'Error al guardar.', 'danger');
            btn.disabled = false;
            btn.textContent = 'Registrar Pago';
        }
    } catch {
        showToast('Error de conexión.', 'danger');
        btn.disabled = false;
        btn.textContent = 'Registrar Pago';
    }
});

// Init
calcularTotal();
document.getElementById('wrapMp').style.display = 'none';
<?php if ($preselect): ?>
cargarFacturas(<?= (int)$preselect ?>);
cargarGastosPendientes(<?= (int)$preselect ?>);
<?php endif; ?>
<?php if (!empty($empleadoPreselect)): ?>
document.getElementById('tipo_sueldo').checked = true;
document.getElementById('tipo_sueldo').dispatchEvent(new Event('change'));
<?php endif; ?>
</script>
