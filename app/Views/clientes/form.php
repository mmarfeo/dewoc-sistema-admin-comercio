<?php $esEdicion = !empty($cliente); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h1>
    <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<form id="formCliente" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?>
    <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Datos del cliente</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Nombre / Razón social <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150"
                           value="<?= e($cliente['nombre'] ?? '') ?>" placeholder="Nombre completo o razón social">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="fActivo" value="1"
                               <?= ($cliente['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fActivo">Cliente activo</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipo de documento</label>
                    <select name="tipo_doc" class="form-select" id="fTipoDoc">
                        <option value="dni"  <?= ($cliente['tipo_doc'] ?? 'dni') === 'dni'  ? 'selected' : '' ?>>DNI</option>
                        <option value="cuit" <?= ($cliente['tipo_doc'] ?? '')    === 'cuit' ? 'selected' : '' ?>>CUIT</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Número de documento</label>
                    <input type="text" name="documento" class="form-control" maxlength="20"
                           value="<?= e($cliente['documento'] ?? '') ?>"
                           placeholder="DNI o XX-XXXXXXXX-X (CUIT)" id="fDocumento">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Condición IVA</label>
                    <select name="condicion_iva" class="form-select">
                        <?php
                        $condiciones = [
                            'consumidor_final'       => 'Consumidor Final',
                            'responsable_inscripto'  => 'Responsable Inscripto',
                            'monotributista'         => 'Monotributista',
                            'exento'                 => 'Exento',
                        ];
                        $condActual = $cliente['condicion_iva'] ?? 'consumidor_final';
                        foreach ($condiciones as $val => $label):
                        ?>
                        <option value="<?= $val ?>" <?= $condActual === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Límite de cuenta corriente</label>
                    <input type="number" name="limite_cuenta_corriente" class="form-control" min="0" step="0.01"
                           value="<?= e($cliente['limite_cuenta_corriente'] ?? '') ?>"
                           placeholder="$100.000 por defecto si se deja vacío">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Contacto</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" maxlength="30"
                           value="<?= e($cliente['telefono'] ?? '') ?>" placeholder="Ej: 011-4567-8901">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="150"
                           value="<?= e($cliente['email'] ?? '') ?>" placeholder="cliente@email.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Dirección</label>
                    <input type="text" name="direccion" class="form-control" maxlength="255"
                           value="<?= e($cliente['direccion'] ?? '') ?>" placeholder="Calle, número, localidad">
                </div>
            </div>
        </div>
    </div>

    <?php if ($esEdicion): ?>
    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Catálogo del Portal <small class="text-muted fw-normal">(qué productos ve este cliente, con precio propio opcional)</small></h6>
        </div>
        <div class="card-body">
            <div class="position-relative mb-3">
                <input type="text" id="catBuscador" class="form-control form-control-sm"
                       placeholder="Buscar producto para agregar al catálogo..." autocomplete="off">
                <div id="catDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none"></div>
            </div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Producto</th><th class="text-end">Precio especial</th><th style="width:40px"></th></tr></thead>
                <tbody id="catLista"><tr><td colspan="3" class="text-muted small">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Acceso al Portal de Clientes Mayoristas</h6>
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="portal_activo" id="fPortalActivo" value="1"
                       <?= !empty($cliente['portal_activo']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="fPortalActivo">
                    Cliente puede ingresar al portal (necesita email cargado arriba)
                </label>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contraseña</label>
                    <input type="password" name="portal_password" class="form-control" autocomplete="new-password"
                           placeholder="<?= !empty($cliente['password_hash']) ? 'Dejar vacío para no cambiarla' : 'Definir contraseña de acceso' ?>">
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar' : 'Crear cliente' ?>
        </button>
        <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
</div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;

// Placeholder dinámico según tipo de doc
document.getElementById('fTipoDoc').addEventListener('change', function () {
    const docInput = document.getElementById('fDocumento');
    docInput.placeholder = this.value === 'cuit' ? 'XX-XXXXXXXX-X' : 'Número de DNI';
});

<?php if ($esEdicion): ?>
// ── Catálogo del Portal ──────────────────────────────────────────────────────
const CLIENTE_ID = <?= (int)$cliente['id'] ?>;
const CSRF_TOKEN  = '<?= Csrf::token() ?>';

async function cargarCatalogo() {
    const r = await fetch(`${BASE_URL}/clientes/catalogo?cliente_id=${CLIENTE_ID}`);
    const d = await r.json();
    const tbody = document.getElementById('catLista');
    tbody.innerHTML = '';
    if (!d.items.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-muted small">Sin productos asignados todavía.</td></tr>';
        return;
    }
    d.items.forEach(it => {
        const precioActual = it.precio_especial != null ? parseFloat(it.precio_especial).toFixed(2) : '';
        const precioNormal = parseFloat(it.precio_venta).toLocaleString('es-AR', {minimumFractionDigits:2});
        tbody.innerHTML += `<tr>
            <td class="small">${escHtmlCat(it.nombre)}</td>
            <td class="text-end small">
                <div class="input-group input-group-sm ms-auto" style="max-width:170px">
                    <span class="input-group-text py-0">$</span>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                           value="${precioActual}" placeholder="Normal: ${precioNormal}"
                           onchange="guardarPrecioEspecial(${it.producto_id}, this.value)">
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="quitarDelCatalogo(${it.producto_id})" title="Quitar">✕</button>
            </td>
        </tr>`;
    });
}
function escHtmlCat(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Vacío = usa el precio normal de venta (mismo criterio que agregarAlCatalogo): el backend
// ya soportaba precio_especial en /clientes/catalogo/asignar (upsert por cliente+producto),
// solo faltaba conectarlo desde acá — antes el precio se mostraba fijo, sin forma de editarlo.
async function guardarPrecioEspecial(productoId, valor) {
    const fd = new FormData();
    fd.append('cliente_id', CLIENTE_ID);
    fd.append('producto_id', productoId);
    fd.append('precio_especial', valor.trim());
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/clientes/catalogo/asignar`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) cargarCatalogo(); else showToast(d.error, 'danger');
}

async function quitarDelCatalogo(productoId) {
    const fd = new FormData();
    fd.append('cliente_id', CLIENTE_ID);
    fd.append('producto_id', productoId);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/clientes/catalogo/quitar`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) cargarCatalogo(); else showToast(d.error, 'danger');
}

let catTimeout;
const catBuscador = document.getElementById('catBuscador');
const catDropdown = document.getElementById('catDropdown');
catBuscador.addEventListener('input', function () {
    clearTimeout(catTimeout);
    const q = this.value.trim();
    if (!q) { catDropdown.style.display = 'none'; return; }
    catTimeout = setTimeout(() => buscarProductoCatalogo(q), 250);
});

async function buscarProductoCatalogo(q) {
    const r = await fetch(`${BASE_URL}/productos/buscar-componentes-promo?q=${encodeURIComponent(q)}`);
    const items = await r.json();
    catDropdown.innerHTML = '';
    if (!items.length) { catDropdown.style.display = 'none'; return; }
    items.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action small py-2';
        btn.textContent = p.nombre;
        btn.addEventListener('click', () => agregarAlCatalogo(p.id));
        catDropdown.appendChild(btn);
    });
    catDropdown.style.display = 'block';
}

async function agregarAlCatalogo(productoId) {
    const fd = new FormData();
    fd.append('cliente_id', CLIENTE_ID);
    fd.append('producto_id', productoId);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/clientes/catalogo/asignar`, { method: 'POST', body: fd });
    const d = await r.json();
    catBuscador.value = '';
    catDropdown.style.display = 'none';
    if (d.ok) cargarCatalogo(); else showToast(d.error, 'danger');
}

document.addEventListener('click', e => {
    if (!e.target.closest('#catBuscador') && !e.target.closest('#catDropdown')) catDropdown.style.display = 'none';
});

cargarCatalogo();
<?php endif; ?>

document.getElementById('formCliente').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');
    document.getElementById('btnGuardar').disabled = true;

    const fd = new FormData(this);
    if (!fd.has('activo')) fd.set('activo', '0');
    if (!fd.has('portal_activo')) fd.set('portal_activo', '0');

    const endpoint = ES_EDICION ? `${BASE_URL}/clientes/actualizar` : `${BASE_URL}/clientes/crear`;

    try {
        const r = await fetch(endpoint, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/clientes`; }, 800);
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
