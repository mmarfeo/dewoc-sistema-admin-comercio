<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Accesos directos del Chat IA</h1>
        <p class="text-muted mb-0 small">Catálogo de botones rápidos que se pueden mostrar debajo del chat. Asigná cuáles ve cada usuario desde su ficha en Usuarios.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('usuarios') ?>" class="btn btn-outline-primary" title="Recordá asignar los accesos nuevos desde la ficha de cada usuario">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Ir a asignar por usuario
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAcceso" onclick="abrirNuevo()">+ Nuevo acceso directo</button>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblAccesos" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">Orden</th>
                        <th>Botón</th>
                        <th>Prompt que envía</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accesos as $a): ?>
                    <tr>
                        <td class="small text-muted"><?= (int)$a['orden'] ?></td>
                        <td class="fw-semibold small"><?= e($a['label']) ?></td>
                        <td class="small text-muted"><?= e($a['texto']) ?></td>
                        <td>
                            <?php if ($a['activo']): ?>
                            <span class="badge bg-success-soft text-success">Visible</span>
                            <?php else: ?>
                            <span class="badge bg-secondary-soft text-secondary">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0"
                                onclick="abrirEditar(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)"
                                title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminar(<?= $a['id'] ?>, '<?= e($a['label']) ?>')"
                                title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$accesos): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Todavía no hay accesos directos cargados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal alta/edición -->
<div class="modal fade" id="modalAcceso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nuevo acceso directo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAcceso" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="aId" name="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                            <span>Texto del botón <span class="text-danger">*</span></span>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnToggleEmojis">😀 Ícono</button>
                        </label>
                        <input type="text" id="aLabel" name="label" class="form-control" required maxlength="60" placeholder="Ej: 📊 Ventas esta semana">
                        <div id="emojiPicker" class="d-none border rounded p-2 mt-2 d-flex flex-wrap gap-1"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prompt que se envía al chat <span class="text-danger">*</span></label>
                        <textarea id="aTexto" name="texto" class="form-control" rows="2" required maxlength="255" placeholder="Ej: ¿Cuánto generamos en ventas esta semana?"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Orden</label>
                            <input type="number" id="aOrden" name="orden" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Estado</label>
                            <select id="aActivo" name="activo" class="form-select">
                                <option value="1">Visible</option>
                                <option value="0">Oculto</option>
                            </select>
                        </div>
                    </div>
                    <div id="modalError" class="alert alert-danger d-none py-2 small mt-3"></div>
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
    $('#tblAccesos').DataTable({ order: [[0, 'asc']] });
});

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
    document.getElementById('modalTitle').textContent = 'Nuevo acceso directo';
    document.getElementById('formAcceso').reset();
    document.getElementById('aId').value = '';
    document.getElementById('aActivo').value = '1';
    document.getElementById('emojiPicker').classList.add('d-none');
    limpiarError();
}

function abrirEditar(a) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Editar acceso directo';
    document.getElementById('aId').value     = a.id;
    document.getElementById('aLabel').value  = a.label;
    document.getElementById('aTexto').value  = a.texto;
    document.getElementById('aOrden').value  = a.orden;
    document.getElementById('aActivo').value = a.activo;
    document.getElementById('emojiPicker').classList.add('d-none');
    limpiarError();
    _showModal('modalAcceso');
}

// ── Selector de íconos: grilla curada de emojis relevantes para un comercio,
// se inserta en la posición del cursor de "Texto del botón" (no reemplaza todo
// el campo, así se puede cambiar/agregar el ícono sin perder el texto ya escrito).
const EMOJIS_ACCESO = ['📊','💰','💵','💳','🏆','📦','🛒','🏧','👥','➕',
                        '🏢','🧾','📈','📉','🧮','📋','🔔','⚠️','✅','📅',
                        '⏰','🚚','🏪','👨‍🍳','🧑‍💼','🔍','🍰','🛍️','📤','📥'];

const emojiPicker = document.getElementById('emojiPicker');
EMOJIS_ACCESO.forEach(emoji => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary btn-sm py-1 px-2';
    btn.style.fontSize = '1.1rem';
    btn.textContent = emoji;
    btn.addEventListener('click', () => insertarEmoji(emoji));
    emojiPicker.appendChild(btn);
});

document.getElementById('btnToggleEmojis').addEventListener('click', () => {
    emojiPicker.classList.toggle('d-none');
});

function insertarEmoji(emoji) {
    const input = document.getElementById('aLabel');
    const inicio  = input.selectionStart ?? input.value.length;
    const fin     = input.selectionEnd   ?? input.value.length;
    const antes   = input.value.slice(0, inicio);
    const despues = input.value.slice(fin);
    const necesitaEspacio = despues !== '' && !despues.startsWith(' ');
    input.value = antes + emoji + (necesitaEspacio ? ' ' : '') + despues;
    const nuevaPos = inicio + emoji.length + (necesitaEspacio ? 1 : 0);
    input.focus();
    input.setSelectionRange(nuevaPos, nuevaPos);
}

document.getElementById('formAcceso').addEventListener('submit', async function(e) {
    e.preventDefault();
    limpiarError();
    document.getElementById('btnGuardar').disabled = true;

    const url = editMode ? `${BASE_URL}/chat/accesos/actualizar` : `${BASE_URL}/chat/accesos/crear`;
    const fd  = new FormData(this);

    try {
        const resp = await fetch(url, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            _hideModal('modalAcceso');
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

function eliminar(id, label) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);

        try {
            const resp = await fetch(`${BASE_URL}/chat/accesos/eliminar`, { method: 'POST', body: fd });
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
    const msg = `¿Eliminar el acceso directo "${label}"?`;
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
