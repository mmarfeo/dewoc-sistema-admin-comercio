<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reembolsar a Socio</h1>
    <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
</div>

<p class="text-muted small mb-4">
    Registrá lo que la empresa le devuelve a un socio por la plata que puso de su bolsillo
    (aportes, facturas o compras pagadas con fondos propios). Se aplica contra sus aportes
    pendientes, igual que un pago a un proveedor se aplica contra sus facturas.
</p>

<form id="formReembolso" enctype="multipart/form-data">
<?= Csrf::field() ?>

<div class="row g-4">

    <!-- ── Columna izquierda: cabecera ─────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Datos del reembolso</h6>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Socio <span class="text-danger">*</span></label>
                    <select name="socio_id" id="socioId" class="form-select form-select-sm">
                        <option value="">— Seleccioná un socio —</option>
                        <?php foreach ($socios as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($socioPreselect == $s['id']) ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($socios)): ?>
                    <div class="form-text text-warning">
                        No hay socios cargados — <a href="<?= url('socios') ?>" target="_blank">creá uno primero</a>.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Concepto</label>
                    <input type="text" name="concepto" class="form-control form-control-sm"
                           placeholder="Ej: Devolución aportes agosto">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Columna derecha: métodos de pago + aportes a saldar ─────── -->
    <div class="col-lg-7">
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
                        <span class="fw-semibold">Total del reembolso:</span>
                        <span class="h5 mb-0 fw-bold text-primary" id="totalPago">$ 0,00</span>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="form-label small fw-semibold mb-1">Comprobante (imagen/PDF)</label>
                    <input type="file" name="comprobante" id="inputComprobante"
                           accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="form-control form-control-sm">
                </div>
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
    </div>
</div>

<!-- ── Aportes pendientes del socio (cargados vía AJAX) — fila completa de 12 columnas, la tabla necesita el ancho ── -->
<div class="row g-4 mt-0">
    <div class="col-12">
        <div class="card border-0" id="cardAportes" style="display:none!important">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Aplicar a aportes pendientes <small class="text-muted fw-normal">(opcional)</small></h6>
                <p class="small text-muted mb-2">Seleccioná qué aportes cubre este reembolso e ingresá el monto aplicado a cada uno. Si no aplicás nada, el reembolso queda igual guardado, pero sin descontar ningún aporte puntual.</p>
                <div id="tablaAportes"></div>
            </div>
        </div>
    </div>
</div>

<!-- Botón guardar -->
<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= url('pagos-proveedores') ?>" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary" id="btnGuardar">
        Registrar Reembolso
    </button>
</div>

</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

// ── Cálculo total ────────────────────────────────────────────────────────────
document.querySelectorAll('.monto-metodo').forEach(inp => {
    inp.addEventListener('input', calcularTotal);
});

function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.monto-metodo').forEach(i => { total += parseFloat(i.value || 0); });
    document.getElementById('totalPago').textContent = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });
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

// ── Cargar aportes pendientes del socio ──────────────────────────────────────
document.getElementById('socioId').addEventListener('change', function () {
    cargarAportes(this.value);
});

const TIPOS_APORTE = { proveedor: 'Proveedor', sueldo: 'Sueldo', otro: 'Otro', aporte: 'Aporte' };

async function cargarAportes(socioId) {
    const card  = document.getElementById('cardAportes');
    const tbody = document.getElementById('tablaAportes');
    if (!socioId) { card.style.setProperty('display','none','important'); return; }

    const r = await fetch(`${BASE_URL}/pagos-proveedores/aportes-pendientes?socio_id=${socioId}`);
    const aportes = await r.json();

    if (!aportes.length) { card.style.setProperty('display','none','important'); return; }

    card.style.removeProperty('display');
    tbody.innerHTML = `
    <table class="table table-sm table-bordered small mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:32px"></th>
                <th>Tipo</th><th>Concepto / Referencia</th><th>Fecha</th>
                <th class="text-end">Total</th>
                <th class="text-end">Reembolsado</th>
                <th class="text-end text-danger">Saldo</th>
                <th style="width:130px">Monto a aplicar</th>
            </tr>
        </thead>
        <tbody id="filasAportes">
        ${aportes.map(a => `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="form-check-input chk-aporte"
                       data-saldo="${a.saldo}" data-id="${a.id}" onchange="toggleAporte(this)">
                <input type="hidden" name="pago_ids[]" value="" data-for="${a.id}">
            </td>
            <td>${escHtml(TIPOS_APORTE[a.tipo] || a.tipo)}</td>
            <td>${escHtml(a.concepto || a.nombre_referencia || '—')}</td>
            <td>${escHtml(a.fecha)}</td>
            <td class="text-end">${formatMoney(a.monto_total)}</td>
            <td class="text-end text-muted">${formatMoney(a.reembolsado)}</td>
            <td class="text-end text-danger fw-semibold">${formatMoney(a.saldo)}</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" name="pago_montos[]" id="monto_p_${a.id}"
                           class="form-control monto-aporte" min="0" step="0.01"
                           placeholder="${a.saldo}" disabled value="" oninput="actualizarMontoMP()">
                </div>
            </td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function toggleAporte(chk) {
    const id  = chk.dataset.id;
    const inp = document.getElementById(`monto_p_${id}`);
    const hid = document.querySelector(`input[name="pago_ids[]"][data-for="${id}"]`);
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

// Precarga el total de los aportes tildados en Mercado Pago (método de pago por
// default) — se recalcula en cada click o edición manual del monto a aplicar.
function actualizarMontoMP() {
    let total = 0;
    document.querySelectorAll('.monto-aporte').forEach(i => { if (!i.disabled) total += parseFloat(i.value || 0); });
    document.getElementById('monto_mercado_pago').value = total > 0 ? total.toFixed(2) : '0';
    calcularTotal();
}

function formatMoney(v) {
    return '$ ' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2 });
}
function escHtml(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Envío del formulario ─────────────────────────────────────────────────────
document.getElementById('formReembolso').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fd = new FormData(this);
    fd.set('_csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(`${BASE_URL}/pagos-proveedores/reembolso-guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast('Reembolso registrado correctamente.');
            setTimeout(() => window.location.href = `${BASE_URL}/pagos-proveedores/reembolso-ver?id=${d.id}`, 700);
        } else {
            showToast(d.error || 'Error al guardar.', 'danger');
            btn.disabled = false;
            btn.textContent = 'Registrar Reembolso';
        }
    } catch {
        showToast('Error de conexión.', 'danger');
        btn.disabled = false;
        btn.textContent = 'Registrar Reembolso';
    }
});

// Init
calcularTotal();
<?php if ($socioPreselect): ?>
cargarAportes(<?= (int)$socioPreselect ?>);
<?php endif; ?>
</script>
