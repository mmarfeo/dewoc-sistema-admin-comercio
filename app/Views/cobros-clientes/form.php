<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Registrar Cobro</h1>
    <a href="<?= url('cobros-clientes') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
</div>

<form id="formCobro">
<?= Csrf::field() ?>

<div class="row g-4">

    <!-- ── Columna izquierda: cabecera ─────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Datos del cobro</h6>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Cliente <span class="text-danger">*</span></label>
                    <select name="cliente_id" id="clienteId" class="form-select form-select-sm">
                        <option value="">— Seleccioná un cliente —</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($preselect == $cl['id']) ? 'selected' : '' ?>><?= e($cl['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Concepto</label>
                    <input type="text" name="concepto" class="form-control form-control-sm"
                           placeholder="Ej: Cobro parcial cuenta corriente">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Columna derecha: métodos de cobro ───────────────────────── -->
    <div class="col-lg-7">
        <div class="card border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Métodos de cobro</h6>

                <div class="row g-2">
                    <?php
                    $metodos = [
                        'efectivo'      => ['label' => 'Efectivo',      'color' => 'success'],
                        'debito'        => ['label' => 'Débito',        'color' => 'info'],
                        'transferencia' => ['label' => 'Transferencia', 'color' => 'warning'],
                        'mercadopago'   => ['label' => 'Mercado Pago',  'color' => 'primary'],
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
                        <span class="fw-semibold">Total del cobro:</span>
                        <span class="h5 mb-0 fw-bold text-primary" id="totalCobro">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Ventas pendientes (cargadas vía AJAX) ──────────────── -->
        <div class="card border-0" id="cardVentas" style="display:none!important">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">Aplicar a ventas pendientes <small class="text-muted fw-normal">(opcional)</small></h6>
                <p class="small text-muted mb-2">Seleccioná las ventas que cubre este cobro e ingresá el monto aplicado a cada una.</p>
                <div id="tablaVentas"></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= url('cobros-clientes') ?>" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary" id="btnGuardar">Registrar Cobro</button>
</div>

</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

document.querySelectorAll('.monto-metodo').forEach(inp => {
    inp.addEventListener('input', calcularTotal);
});

function calcularTotal() {
    let total = 0;
    document.querySelectorAll('.monto-metodo').forEach(i => { total += parseFloat(i.value || 0); });
    document.getElementById('totalCobro').textContent = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });
}

document.getElementById('clienteId').addEventListener('change', function () {
    cargarVentas(this.value);
});

async function cargarVentas(clienteId) {
    const card  = document.getElementById('cardVentas');
    const tbody = document.getElementById('tablaVentas');
    if (!clienteId) { card.style.setProperty('display', 'none', 'important'); return; }

    const r = await fetch(`${BASE_URL}/cobros-clientes/ventas-pendientes?cliente_id=${clienteId}`);
    const ventas = await r.json();

    if (!ventas.length) {
        card.style.removeProperty('display');
        tbody.innerHTML = '<p class="small text-muted mb-0">Este cliente no tiene ventas con saldo de cuenta corriente pendiente.</p>';
        return;
    }

    card.style.removeProperty('display');
    tbody.innerHTML = `
    <table class="table table-sm table-bordered small mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:32px"></th>
                <th>Venta</th><th>Fecha</th>
                <th class="text-end">Total</th>
                <th class="text-end">Cobrado</th>
                <th class="text-end text-danger">Saldo</th>
                <th style="width:130px">Monto a aplicar</th>
            </tr>
        </thead>
        <tbody id="filasVentas">
        ${ventas.map(v => `
        <tr>
            <td class="text-center">
                <input type="checkbox" class="form-check-input chk-venta"
                       data-saldo="${v.saldo}" data-id="${v.id}" onchange="toggleVenta(this)">
                <input type="hidden" name="venta_ids[]" value="" data-for="${v.id}">
            </td>
            <td>#${v.id}</td>
            <td>${escHtml(v.fecha)}</td>
            <td class="text-end">${formatMoney(v.total_con_iva)}</td>
            <td class="text-end text-muted">${formatMoney(v.pagado)}</td>
            <td class="text-end text-danger fw-semibold">${formatMoney(v.saldo)}</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" name="venta_montos[]" id="monto_v_${v.id}"
                           class="form-control monto-venta" min="0" step="0.01"
                           placeholder="${v.saldo}" disabled value="">
                </div>
            </td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function toggleVenta(chk) {
    const id  = chk.dataset.id;
    const inp = document.getElementById(`monto_v_${id}`);
    const hid = document.querySelector(`input[name="venta_ids[]"][data-for="${id}"]`);
    inp.disabled = !chk.checked;
    if (chk.checked) {
        hid.value = id;
        if (!inp.value || inp.value === '0') inp.value = chk.dataset.saldo;
    } else {
        hid.value = '';
        inp.value = '';
    }
}

function formatMoney(v) {
    return '$ ' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2 });
}
function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

document.getElementById('formCobro').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fd = new FormData(this);
    fd.set('_csrf_token', CSRF_TOKEN);

    try {
        const r = await fetch(`${BASE_URL}/cobros-clientes/guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast('Cobro registrado correctamente.');
            setTimeout(() => window.location.href = `${BASE_URL}/cobros-clientes/ver?id=${d.id}`, 700);
        } else {
            showToast(d.error || 'Error al guardar.', 'danger');
            btn.disabled = false;
            btn.textContent = 'Registrar Cobro';
        }
    } catch {
        showToast('Error de conexión.', 'danger');
        btn.disabled = false;
        btn.textContent = 'Registrar Cobro';
    }
});

calcularTotal();
<?php if ($preselect): ?>
cargarVentas(<?= (int)$preselect ?>);
<?php endif; ?>
</script>
