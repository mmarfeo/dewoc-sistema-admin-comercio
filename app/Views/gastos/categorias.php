<?php
$tipoInfo = [
    'costo_fijo'     => ['label' => 'Costos Fijos',     'color' => '#0d6efd', 'badge' => 'bg-primary'],
    'costo_variable' => ['label' => 'Costos Variables', 'color' => '#198754', 'badge' => 'bg-success'],
    'impuesto'       => ['label' => 'Impuestos',        'color' => '#dc3545', 'badge' => 'bg-danger'],
];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Categorías de Gastos</h1>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCategoria" onclick="abrirModalNuevo()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva categoría
    </button>
</div>

<?php foreach ($categorias as $padre): ?>
<?php $ti = $tipoInfo[$padre['tipo']] ?? ['label' => $padre['tipo'], 'color' => '#6c757d', 'badge' => 'bg-secondary']; ?>
<div class="card border-0 mb-3">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between py-3">
        <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle d-inline-block" style="width:10px;height:10px;background:<?= e($padre['color']) ?>"></span>
            <h6 class="mb-0 fw-bold"><?= e($padre['nombre']) ?></h6>
            <span class="badge <?= $ti['badge'] ?> ms-1"><?= $ti['label'] ?></span>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                onclick="abrirModalEditar(<?= $padre['id'] ?>, '<?= e(addslashes($padre['nombre'])) ?>', '<?= $padre['tipo'] ?>', null, '<?= e($padre['color']) ?>')"
                title="Editar">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-cat" data-id="<?= $padre['id'] ?>" title="Eliminar">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </button>
        </div>
    </div>

    <?php if (!empty($padre['hijos'])): ?>
    <div class="card-body pt-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($padre['hijos'] as $hijo): ?>
            <li class="list-group-item border-0 py-2 px-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-block ms-3" style="width:8px;height:8px;background:<?= e($hijo['color']) ?>"></span>
                    <span class="small"><?= e($hijo['nombre']) ?></span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                        onclick="abrirModalEditar(<?= $hijo['id'] ?>, '<?= e(addslashes($hijo['nombre'])) ?>', '<?= $hijo['tipo'] ?>', <?= $hijo['parent_id'] ?>, '<?= e($hijo['color']) ?>')"
                        title="Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-cat" data-id="<?= $hijo['id'] ?>" title="Eliminar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="px-3 pb-2">
            <button class="btn btn-link btn-sm text-decoration-none p-0 text-muted"
                onclick="abrirModalSubcategoria(<?= $padre['id'] ?>, '<?= $padre['tipo'] ?>')">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar subcategoría
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body pt-0 px-3 pb-2">
        <button class="btn btn-link btn-sm text-decoration-none p-0 text-muted"
            onclick="abrirModalSubcategoria(<?= $padre['id'] ?>, '<?= $padre['tipo'] ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Agregar subcategoría
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($categorias)): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-5">Sin categorías configuradas. Ejecutá <code>gastos_setup.sql</code> para cargar las categorías base.</div>
</div>
<?php endif; ?>

<!-- Modal crear/editar categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formCategoria">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" id="catId">
                <input type="hidden" name="parent_id" id="catParentId">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalCatTitulo">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Nombre *</label>
                        <input type="text" name="nombre" id="catNombre" class="form-control" required maxlength="120">
                    </div>
                    <div class="mb-3" id="wrapTipo">
                        <label class="form-label small">Tipo *</label>
                        <select name="tipo" id="catTipo" class="form-select">
                            <option value="costo_fijo">Costo Fijo</option>
                            <option value="costo_variable">Costo Variable</option>
                            <option value="impuesto">Impuesto</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Color</label>
                        <input type="color" name="color" id="catColor" class="form-control form-control-color" value="#6c757d">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

function abrirModalNuevo() {
    document.getElementById('modalCatTitulo').textContent = 'Nueva Categoría';
    document.getElementById('catId').value       = '';
    document.getElementById('catParentId').value = '';
    document.getElementById('catNombre').value   = '';
    document.getElementById('catTipo').value     = 'costo_fijo';
    document.getElementById('catColor').value    = '#6c757d';
    document.getElementById('wrapTipo').style.display = '';
}

function abrirModalEditar(id, nombre, tipo, parentId, color) {
    document.getElementById('modalCatTitulo').textContent = 'Editar Categoría';
    document.getElementById('catId').value       = id;
    document.getElementById('catParentId').value = parentId || '';
    document.getElementById('catNombre').value   = nombre;
    document.getElementById('catTipo').value     = tipo;
    document.getElementById('catColor').value    = color;
    document.getElementById('wrapTipo').style.display = parentId ? 'none' : '';
    _showModal('modalCategoria');
}

function abrirModalSubcategoria(parentId, tipo) {
    document.getElementById('modalCatTitulo').textContent = 'Nueva Subcategoría';
    document.getElementById('catId').value       = '';
    document.getElementById('catParentId').value = parentId;
    document.getElementById('catNombre').value   = '';
    document.getElementById('catTipo').value     = tipo;
    document.getElementById('catColor').value    = '#6c757d';
    document.getElementById('wrapTipo').style.display = 'none';
    _showModal('modalCategoria');
}

document.getElementById('formCategoria').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id  = document.getElementById('catId').value;
    const url = id
        ? '<?= url('gastos/categorias/actualizar') ?>'
        : '<?= url('gastos/categorias/crear') ?>';
    const res  = await fetch(url, { method: 'POST', body: new FormData(this) });
    const json = await res.json();
    if (json.ok) {
        location.reload();
    } else {
        alert(json.error || 'Error al guardar.');
    }
});

document.querySelectorAll('.btn-eliminar-cat').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const ejecutar = async () => {
            const fd = new FormData();
            fd.append('csrf_token', '<?= Csrf::token() ?>');
            fd.append('id', id);
            const res  = await fetch('<?= url('gastos/categorias/eliminar') ?>', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.ok) {
                location.reload();
            } else {
                alert(json.error || 'No se pudo eliminar.');
            }
        };
        if (typeof showConfirm === 'function') {
            showConfirm('¿Eliminar esta categoría?', ejecutar, { label: 'Eliminar' });
        } else {
            if (confirm('¿Eliminar esta categoría?')) ejecutar();
        }
    });
});
</script>
