<!-- ── Título + botón ─────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Socios</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSocio" onclick="abrirNuevo()">+ Nuevo socio</button>
</div>

<!-- ── Tabla ─────────────────────────────────────────────────── -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblSocios" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($socios as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td class="fw-semibold"><?= e($s['nombre']) ?></td>
                        <td>
                            <?php if ($s['activo']): ?>
                                <span class="badge bg-success-soft text-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-soft text-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('pagos-proveedores/cuenta-corriente-socio?socio_id=' . $s['id']) ?>"
                                class="btn btn-sm btn-outline-secondary py-0" title="Ver cuenta corriente (aportes y reembolsos)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-primary py-0 ms-1"
                                onclick="abrirEditar(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                                title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminar(<?= $s['id'] ?>, '<?= e($s['nombre']) ?>')"
                                title="Eliminar">
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

<!-- ── Modal alta/edición ────────────────────────────────────── -->
<div class="modal fade" id="modalSocio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo socio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSocio" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="socioId" name="id" value="">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="socioNombre" name="nombre" class="form-control" required maxlength="150">
                    </div>

                    <div id="campoActivo" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Estado</label>
                        <select id="socioActivo" name="activo" class="form-select">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>

                    <div id="modalError" class="alert alert-danger d-none py-2 small"></div>

                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';
let   editMode   = false;

$(document).ready(function() {
    $('#tblSocios').DataTable();
});

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
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    let btn = el.querySelector('[data-bs-dismiss="modal"]');
    if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-dismiss', 'modal');
        el.appendChild(btn);
    }
    btn.click();
}

function abrirNuevo() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Nuevo socio';
    document.getElementById('formSocio').reset();
    document.getElementById('socioId').value = '';
    document.getElementById('campoActivo').classList.add('d-none');
    limpiarError();
}

function abrirEditar(socio) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Editar socio';
    document.getElementById('socioId').value      = socio.id;
    document.getElementById('socioNombre').value  = socio.nombre;
    document.getElementById('socioActivo').value  = socio.activo;
    document.getElementById('campoActivo').classList.remove('d-none');
    limpiarError();
    _showModal('modalSocio');
}

document.getElementById('formSocio').addEventListener('submit', async function(e) {
    e.preventDefault();
    limpiarError();
    document.getElementById('btnGuardar').disabled = true;

    const url = editMode ? `${BASE_URL}/socios/actualizar` : `${BASE_URL}/socios/crear`;
    const fd  = new FormData(this);

    try {
        const resp = await fetch(url, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            _hideModal('modalSocio');
            showToast(data.mensaje, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            mostrarError(data.error || 'Error al guardar.');
        }
    } catch {
        mostrarError('Error de conexión. Intentá de nuevo.');
    } finally {
        document.getElementById('btnGuardar').disabled = false;
    }
});

function eliminar(id, nombre) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);

        try {
            const resp = await fetch(`${BASE_URL}/socios/eliminar`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                showToast(data.mensaje, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.error || 'No se pudo eliminar.', 'danger');
            }
        } catch {
            showToast('Error de conexión.', 'danger');
        }
    };
    const msg = `¿Eliminar el socio "${nombre}"?`;
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, { label: 'Eliminar', sub: 'Esta acción no se puede deshacer.' });
    } else {
        if (confirm(msg + ' Esta acción no se puede deshacer.')) ejecutar();
    }
}

function mostrarError(msg) {
    const el = document.getElementById('modalError');
    el.textContent = msg;
    el.classList.remove('d-none');
}

function limpiarError() {
    document.getElementById('modalError').classList.add('d-none');
}
</script>
