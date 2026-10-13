<!-- ── Título + botones ───────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Categorías</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria" onclick="abrirNueva()">+ Nueva categoría</button>
    </div>
</div>

<!-- ── Tabla ─────────────────────────────────────────────────── -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblCategorias" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Categoría padre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td class="fw-semibold"><?= e($cat['nombre']) ?></td>
                        <td>
                            <?php
                            $tipoLabel = ['producto' => 'Producto', 'materia_prima' => 'Materia Prima', 'insumo' => 'Insumo'];
                            $tipoColor = ['producto' => 'primary', 'materia_prima' => 'success', 'insumo' => 'warning'];
                            ?>
                            <span class="badge bg-<?= $tipoColor[$cat['tipo']] ?? 'secondary' ?>-soft text-<?= $tipoColor[$cat['tipo']] ?? 'secondary' ?>">
                                <?= $tipoLabel[$cat['tipo']] ?? e($cat['tipo']) ?>
                            </span>
                        </td>
                        <td><?= $cat['padre_nombre'] ? e($cat['padre_nombre']) : '<span class="text-muted small">—</span>' ?></td>
                        <td class="text-muted small"><?= e($cat['descripcion'] ?? '') ?></td>
                        <td>
                            <?php if ($cat['activo']): ?>
                                <span class="badge bg-success-soft text-success">Activa</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-soft text-secondary">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0"
                                onclick="abrirEditar(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)"
                                title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="eliminar(<?= $cat['id'] ?>, '<?= e($cat['nombre']) ?>')"
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
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Nueva categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCategoria" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" id="catId" name="id" value="">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="catNombre" name="nombre" class="form-control" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                        <select id="catTipo" name="tipo" class="form-select" required onchange="filtrarPadres()">
                            <option value="producto">Producto</option>
                            <option value="materia_prima">Materia Prima</option>
                            <option value="insumo">Insumo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoría padre</label>
                        <select id="catPadre" name="padre_id" class="form-select">
                            <option value="">— Sin padre (raíz) —</option>
                            <?php foreach ($padres as $p): ?>
                            <option value="<?= $p['id'] ?>" data-tipo="<?= e($p['tipo']) ?>">
                                <?= e($p['nombre']) ?> (<?= e($p['tipo']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea id="catDesc" name="descripcion" class="form-control" rows="2" maxlength="255"></textarea>
                    </div>

                    <div id="campoActivo" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Estado</label>
                        <select id="catActivo" name="activo" class="form-select">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
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

// ── Inicializar DataTable ────────────────────────────────────
$(document).ready(function() {
    $('#tblCategorias').DataTable();
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

// ── Modal nueva ──────────────────────────────────────────────
function abrirNueva() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Nueva categoría';
    document.getElementById('formCategoria').reset();
    document.getElementById('catId').value = '';
    document.getElementById('campoActivo').classList.add('d-none');
    limpiarError();
}

// ── Modal editar ─────────────────────────────────────────────
function abrirEditar(cat) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Editar categoría';
    document.getElementById('catId').value      = cat.id;
    document.getElementById('catNombre').value  = cat.nombre;
    document.getElementById('catTipo').value    = cat.tipo;
    document.getElementById('catPadre').value   = cat.padre_id || '';
    document.getElementById('catDesc').value    = cat.descripcion || '';
    document.getElementById('catActivo').value  = cat.activo;
    document.getElementById('campoActivo').classList.remove('d-none');
    limpiarError();
    _showModal('modalCategoria');
}

// ── Filtrar padres según tipo ────────────────────────────────
function filtrarPadres() {
    const tipo = document.getElementById('catTipo').value;
    const opts = document.querySelectorAll('#catPadre option[data-tipo]');
    opts.forEach(o => {
        o.style.display = (o.dataset.tipo === tipo) ? '' : 'none';
    });
    document.getElementById('catPadre').value = '';
}

// ── Submit del form ──────────────────────────────────────────
document.getElementById('formCategoria').addEventListener('submit', async function(e) {
    e.preventDefault();
    limpiarError();
    document.getElementById('btnGuardar').disabled = true;

    const url    = editMode ? `${BASE_URL}/categorias/actualizar` : `${BASE_URL}/categorias/crear`;
    const fd     = new FormData(this);

    try {
        const resp = await fetch(url, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            _hideModal('modalCategoria');
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

// ── Eliminar ─────────────────────────────────────────────────
function eliminar(id, nombre) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);

        try {
            const resp = await fetch(`${BASE_URL}/categorias/eliminar`, { method: 'POST', body: fd });
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
    const msg = `¿Eliminar la categoría "${nombre}"?`;
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
<?php
$importUrl       = url('categorias/importar');
$importPlantilla = url('categorias/plantilla');
$importModulo    = 'Categorías';
$importColumnas  = ['nombre *', 'tipo * (producto/materia_prima/insumo)', 'padre', 'descripcion'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
