<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Tipos de concepto</h1>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTipo" onclick="abrirModalNuevo()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo tipo
    </button>
</div>

<p class="text-muted small mb-4">
    Estos son los tipos que podés elegir al cargar un concepto (viático, premio, bono, aguinaldo, etc.) en la ficha de un empleado.
    Podés crear los que necesites — por ejemplo "Aguinaldo" o "Adelanto" — además de los que ya vienen cargados.
</p>

<div class="card border-0">
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($tipos as $t): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-block" style="width:10px;height:10px;background:<?= e($t['color']) ?>"></span>
                    <span class="fw-semibold"><?= e($t['nombre']) ?></span>
                    <span class="badge bg-light text-muted border ms-1"><?= e($t['codigo']) ?></span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                        onclick="abrirModalEditar(<?= $t['id'] ?>, '<?= e(addslashes($t['nombre'])) ?>', '<?= e($t['color']) ?>')"
                        title="Editar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-tipo" data-id="<?= $t['id'] ?>" title="Eliminar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($tipos)): ?>
        <div class="text-center text-muted py-5">Sin tipos configurados.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal crear/editar tipo -->
<div class="modal fade" id="modalTipo" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formTipo">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" id="tipoId">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalTipoTitulo">Nuevo Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Nombre *</label>
                        <input type="text" name="nombre" id="tipoNombre" class="form-control" required maxlength="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Color</label>
                        <input type="color" name="color" id="tipoColor" class="form-control form-control-color" value="#6c757d">
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
    document.getElementById('modalTipoTitulo').textContent = 'Nuevo Tipo';
    document.getElementById('tipoId').value     = '';
    document.getElementById('tipoNombre').value = '';
    document.getElementById('tipoColor').value  = '#6c757d';
}

function abrirModalEditar(id, nombre, color) {
    document.getElementById('modalTipoTitulo').textContent = 'Editar Tipo';
    document.getElementById('tipoId').value     = id;
    document.getElementById('tipoNombre').value = nombre;
    document.getElementById('tipoColor').value  = color;
    _showModal('modalTipo');
}

document.getElementById('formTipo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id  = document.getElementById('tipoId').value;
    const url = id
        ? '<?= url('empleados/conceptos-tipos/actualizar') ?>'
        : '<?= url('empleados/conceptos-tipos/guardar') ?>';
    const res  = await fetch(url, { method: 'POST', body: new FormData(this) });
    const json = await res.json();
    if (json.ok) {
        location.reload();
    } else {
        alert(json.error || 'Error al guardar.');
    }
});

document.querySelectorAll('.btn-eliminar-tipo').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const ejecutar = async () => {
            const fd = new FormData();
            fd.append('_csrf_token', '<?= Csrf::token() ?>');
            fd.append('id', id);
            const res  = await fetch('<?= url('empleados/conceptos-tipos/eliminar') ?>', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.ok) {
                location.reload();
            } else {
                alert(json.error || 'No se pudo eliminar.');
            }
        };
        if (typeof showConfirm === 'function') {
            showConfirm('¿Eliminar este tipo de concepto?', ejecutar, { label: 'Eliminar' });
        } else {
            if (confirm('¿Eliminar este tipo de concepto?')) ejecutar();
        }
    });
});
</script>
