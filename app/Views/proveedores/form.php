<?php $esEdicion = !empty($proveedor); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar proveedor' : 'Nuevo proveedor' ?></h1>
    <a href="<?= url('proveedores') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<form id="formProv" method="POST" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $proveedor['id'] ?>"><?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos fiscales</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Razón social <span class="text-danger">*</span></label>
                    <input type="text" name="razon_social" class="form-control" required maxlength="150" value="<?= e($proveedor['razon_social'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">CUIT</label>
                    <input type="text" name="cuit" class="form-control" maxlength="13" placeholder="XX-XXXXXXXX-X" value="<?= e($proveedor['cuit'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Condición IVA</label>
                    <select name="condicion_iva" class="form-select">
                        <?php foreach (['responsable_inscripto' => 'Responsable Inscripto', 'monotributista' => 'Monotributista', 'exento' => 'Exento', 'consumidor_final' => 'Consumidor Final'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($proveedor['condicion_iva'] ?? 'responsable_inscripto') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Rubro</label>
                    <input type="text" name="rubro" class="form-control" maxlength="100" placeholder="Ej: Alimentos, Bebidas, Tecnología…" value="<?= e($proveedor['rubro'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Dirección</label>
                    <input type="text" name="direccion" class="form-control" maxlength="255" value="<?= e($proveedor['direccion'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Contacto y datos bancarios</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" maxlength="30" value="<?= e($proveedor['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="150" value="<?= e($proveedor['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Banco</label>
                    <input type="text" name="banco" class="form-control" maxlength="100" value="<?= e($proveedor['banco'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">CBU / Alias <small class="text-muted">(22 dígitos o alias de transferencia)</small></label>
                    <input type="text" name="cbu" class="form-control" maxlength="50" value="<?= e($proveedor['cbu'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body py-3">
            <input type="hidden" name="activo" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="switchActivo" name="activo" value="1" <?= ($proveedor['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="switchActivo">Proveedor activo</label>
                <div class="text-muted small mt-1">Los proveedores inactivos no aparecen en las listas de selección de compras.</div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><?= $esEdicion ? 'Actualizar' : 'Crear proveedor' ?></button>
        <a href="<?= url('proveedores') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php if ($esEdicion): ?>
<!-- ── Cuentas / Alias para detección por IA ──────────────────────────────── -->
<div class="card border-0 mt-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between pb-0">
        <div>
            <h6 class="fw-semibold mb-0">Cuentas / Alias para el Chat IA</h6>
            <p class="text-muted small mb-0 mt-1">El asistente usa estos datos para identificar al proveedor en comprobantes de pago (MP, transferencias, etc.).</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="mostrarFormIdent()">+ Agregar</button>
    </div>
    <div class="card-body">
        <div id="listaIdentidades"></div>

        <div id="formIdentidad" class="d-none border rounded p-3 mt-2 bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Tipo</label>
                    <select id="idTipo" class="form-select form-select-sm">
                        <option value="alias_mp">Alias Mercado Pago</option>
                        <option value="cbu">CBU</option>
                        <option value="nombre">Nombre / Razón social alternativa</option>
                        <option value="cuit">CUIT alternativo</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Valor <span class="text-danger">*</span></label>
                    <input type="text" id="idValor" class="form-control form-control-sm" placeholder="Ej: mi.proveedor.mp">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Etiqueta</label>
                    <input type="text" id="idDesc" class="form-control form-control-sm" placeholder="Ej: Cuenta MP personal">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-primary" onclick="guardarIdentidad()">Guardar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ocultarFormIdent()">✕</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Ítems aprendidos por el Chat IA (proveedor_item_alias) ─────────────── -->
<div class="card border-0 mt-4">
    <div class="card-header border-0 bg-transparent pb-0">
        <h6 class="fw-semibold mb-0">Ítems aprendidos de este proveedor</h6>
        <p class="text-muted small mb-0 mt-1">Nombres de ticket que el Chat IA ya asoció a un ítem del sistema para no volver a preguntar. Si alguno quedó mal asignado, reasignalo o borralo.</p>
    </div>
    <div class="card-body">
        <div id="listaAliasItems"></div>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
document.getElementById('formProv').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');
    const isEdit = !!document.querySelector('input[name="id"]')?.value;
    const url    = isEdit ? `${BASE_URL}/proveedores/actualizar` : `${BASE_URL}/proveedores/crear`;
    const fd     = new FormData(this);
    try {
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => window.location.href=`${BASE_URL}/proveedores`, 900); }
        else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.textContent='Error de conexión.'; errEl.classList.remove('d-none'); }
});

<?php if ($esEdicion): ?>
const PROV_ID    = <?= (int)$proveedor['id'] ?>;
const CSRF_IDENT = '<?= Csrf::token() ?>';
const TIPO_LABELS = { alias_mp:'Alias MP', cbu:'CBU', nombre:'Nombre', cuit:'CUIT', otro:'Otro' };

async function cargarIdentidades() {
    const r     = await fetch(`${BASE_URL}/proveedores/identidades?id=${PROV_ID}`);
    const items = await r.json();
    const el    = document.getElementById('listaIdentidades');
    if (!items.length) {
        el.innerHTML = '<p class="small text-muted mb-0">Sin identidades registradas. Agregá un alias MP, CBU, teléfono/WhatsApp u otro dato fijo (tipo "Otro") para que el Chat IA reconozca automáticamente a este proveedor tanto en comprobantes de pago como en facturas de compra que no traen razón social impresa.</p>';
        return;
    }
    el.innerHTML = `<table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Tipo</th><th>Valor</th><th>Etiqueta</th><th></th></tr></thead>
        <tbody>` +
        items.map(i => `<tr>
            <td><span class="badge bg-secondary-soft text-secondary">${escHtmlI(TIPO_LABELS[i.tipo] || i.tipo)}</span></td>
            <td class="fw-semibold small">${escHtmlI(i.valor)}</td>
            <td class="small text-muted">${escHtmlI(i.descripcion || '—')}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-danger py-0" onclick="eliminarIdentidad(${i.id})">✕</button>
            </td>
        </tr>`).join('') +
        `</tbody></table>`;
}

function mostrarFormIdent()  { document.getElementById('formIdentidad').classList.remove('d-none'); document.getElementById('idValor').focus(); }
function ocultarFormIdent()  { document.getElementById('formIdentidad').classList.add('d-none'); }

async function guardarIdentidad() {
    const valor = document.getElementById('idValor').value.trim();
    if (!valor) { showToast('Ingresá un valor.', 'danger'); return; }
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_IDENT);
    fd.append('proveedor_id', PROV_ID);
    fd.append('tipo',  document.getElementById('idTipo').value);
    fd.append('valor', valor);
    fd.append('descripcion', document.getElementById('idDesc').value.trim());
    const r = await fetch(`${BASE_URL}/proveedores/identidades/agregar`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        ocultarFormIdent();
        document.getElementById('idValor').value = '';
        document.getElementById('idDesc').value  = '';
        cargarIdentidades();
        showToast('Identidad agregada.');
    } else {
        showToast(d.error, 'danger');
    }
}

function eliminarIdentidad(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_IDENT);
        fd.append('id', id);
        const r = await fetch(`${BASE_URL}/proveedores/identidades/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? cargarIdentidades() : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar esta identidad?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar esta identidad?')) ejecutar();
    }
}

function escHtmlI(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Ítems aprendidos (proveedor_item_alias) ─────────────────────────────────────
const TIPO_ITEM_LABELS = { materia_prima: 'Materia prima', insumo: 'Insumo', producto: 'Producto' };

async function cargarAliasItems() {
    const r     = await fetch(`${BASE_URL}/proveedores/alias-items?id=${PROV_ID}`);
    const items = await r.json();
    const el    = document.getElementById('listaAliasItems');
    if (!items.length) {
        el.innerHTML = '<p class="small text-muted mb-0">Todavía no aprendió ninguno. Se van a ir agregando solos a medida que confirmes compras de este proveedor con ítems ambiguos.</p>';
        return;
    }
    el.innerHTML = `<table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Nombre en el ticket</th><th>Asignado a</th><th></th><th></th></tr></thead>
        <tbody>` +
        items.map(i => `<tr data-alias-id="${i.id}">
            <td class="small">${escHtmlI(i.nombre_ticket)}</td>
            <td class="small">
                <span class="badge bg-secondary-soft text-secondary">${escHtmlI(TIPO_ITEM_LABELS[i.tipo_item] || i.tipo_item)}</span>
                ${escHtmlI(i.nombre_sistema || '(ítem eliminado)')}
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 btn-reasignar-alias" data-id="${i.id}">Reasignar</button>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="eliminarAliasItem(${i.id})">✕</button>
            </td>
        </tr>`).join('') +
        `</tbody></table>`;

    el.querySelectorAll('.btn-reasignar-alias').forEach(btn => {
        btn.addEventListener('click', () => abrirBuscadorAliasItem(btn));
    });
}

function eliminarAliasItem(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_IDENT);
        fd.append('id', id);
        const r = await fetch(`${BASE_URL}/proveedores/alias-items/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? cargarAliasItems() : showToast(d.error, 'danger');
    };
    const msg = '¿Eliminar esta relación aprendida?';
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, { label: 'Eliminar', sub: 'La próxima compra de este proveedor con ese nombre va a volver a preguntar.' });
    } else {
        if (confirm(msg + ' La próxima compra de este proveedor con ese nombre va a volver a preguntar.')) ejecutar();
    }
}

// Dropdown de búsqueda de ítems compartido (un solo elemento reposicionado bajo el
// input activo) — usa las clases nativas de Bootstrap (dropdown-menu/dropdown-item),
// que ya vienen tematizadas para claro/oscuro por el bundle de Dashly.
let _ITEM_DD = null;
function _itemDdEnsure() {
    if (_ITEM_DD) return _ITEM_DD;
    _ITEM_DD = document.createElement('div');
    _ITEM_DD.className = 'dropdown-menu p-0';
    _ITEM_DD.style.position = 'fixed';
    _ITEM_DD.style.maxHeight = '220px';
    _ITEM_DD.style.overflowY = 'auto';
    document.body.appendChild(_ITEM_DD);
    document.addEventListener('mousedown', e => {
        if (_ITEM_DD.style.display === 'none' || !_ITEM_DD.style.display) return;
        if (!_ITEM_DD.contains(e.target) && !e.target.closest('.input-buscar-alias-item')) {
            _ITEM_DD.style.display = 'none';
        }
    });
    return _ITEM_DD;
}

function abrirBuscadorAliasItem(btn) {
    const td = btn.closest('td');
    if (td.querySelector('.input-buscar-alias-item')) return; // ya está abierto

    const aliasId = btn.dataset.id;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm input-buscar-alias-item';
    input.placeholder = 'Buscar ítem...';
    input.style.minWidth = '180px';
    td.innerHTML = '';
    td.appendChild(input);
    input.focus();

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { _itemDdEnsure().style.display = 'none'; return; }
        timer = setTimeout(async () => {
            const r = await fetch(`${BASE_URL}/compras/buscar-items?q=${encodeURIComponent(q)}`);
            const resultados = await r.json();
            mostrarOpcionesAliasItem(input, resultados, aliasId);
        }, 250);
    });
}

function mostrarOpcionesAliasItem(input, resultados, aliasId) {
    const dd = _itemDdEnsure();
    const rect = input.getBoundingClientRect();
    dd.style.top   = (rect.bottom + 2) + 'px';
    dd.style.left  = rect.left + 'px';
    dd.style.width = rect.width + 'px';
    dd.style.display = 'block';

    if (!resultados.length) {
        dd.innerHTML = '<span class="dropdown-item-text text-muted small">Sin resultados</span>';
        return;
    }
    dd.innerHTML = resultados.map((r, idx) =>
        `<button type="button" class="dropdown-item small" data-idx="${idx}">${escHtmlI(r.nombre)} <span class="text-muted">(${escHtmlI(TIPO_ITEM_LABELS[r.tipo] || r.tipo)})</span></button>`
    ).join('');
    dd.querySelectorAll('.dropdown-item[data-idx]').forEach(el => {
        el.addEventListener('mousedown', ev => {
            ev.preventDefault();
            const r = resultados[parseInt(el.dataset.idx, 10)];
            dd.style.display = 'none';
            reasignarAliasItem(aliasId, r.tipo, r.id);
        });
    });
}

async function reasignarAliasItem(aliasId, tipoItem, itemId) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_IDENT);
    fd.append('id', aliasId);
    fd.append('tipo_item', tipoItem);
    fd.append('item_id', itemId);
    const r = await fetch(`${BASE_URL}/proveedores/alias-items/reasignar`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) { showToast('Reasignado.'); cargarAliasItems(); }
    else showToast(d.error, 'danger');
}

cargarIdentidades();
cargarAliasItems();
<?php endif; ?>
</script>
