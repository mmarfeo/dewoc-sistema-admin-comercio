<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Plantillas de permisos</h1>
        <p class="text-muted small mb-0">
            Un conjunto de permisos reutilizable ("Permission Set") que se puede asignar como
            <strong>default de un Rol</strong> (lo heredan todos sus usuarios) y/o de forma
            <strong>puntual a un usuario</strong> concreto (manda por sobre la de su rol) —
            configurá cada usuario desde su ficha en <a href="<?= url('usuarios') ?>">Usuarios</a>.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('permisos') ?>" class="btn btn-outline-secondary btn-sm">← Permisos por rol (legacy)</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaPlantilla">+ Nueva plantilla</button>
    </div>
</div>

<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Plantillas</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Roles que la usan por defecto</th>
                        <th class="text-center">Usuarios con esta plantilla puntual</th>
                        <th>Estado</th>
                        <th class="text-center" style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$plantillas): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay plantillas — creá la primera con "+ Nueva plantilla".</td></tr>
                    <?php endif; ?>
                    <?php foreach ($plantillas as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'text-muted' ?>">
                        <td class="fw-semibold"><?= e($p['nombre']) ?></td>
                        <td class="small"><?= e($p['descripcion'] ?? '—') ?></td>
                        <td class="text-center"><?= (int)$p['roles_default'] ?></td>
                        <td class="text-center"><?= (int)$p['usuarios_asignados'] ?></td>
                        <td>
                            <?php if ($p['activo']): ?>
                            <span class="badge bg-success-soft text-success">Activa</span>
                            <?php else: ?>
                            <span class="badge bg-secondary-soft text-secondary">Desactivada</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('permisos/plantillas/editar?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0">Matriz</a>
                            <?php if ($p['activo']): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="_toggleActivo(<?= $p['id'] ?>, false)">Desactivar</button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-success py-0" onclick="_toggleActivo(<?= $p['id'] ?>, true)">Reactivar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold">Plantilla por defecto de cada rol</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Todo usuario de un rol hereda automáticamente la plantilla que le asignes acá, salvo
            que tenga una plantilla puntual propia elegida desde su ficha (eso manda por sobre esto).
        </p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Rol</th><th style="width:320px">Plantilla por defecto</th><th style="width:90px"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                    <tr>
                        <td class="fw-semibold text-capitalize"><?= e($rol['nombre']) ?></td>
                        <td>
                            <select class="form-select form-select-sm _selRolDefault" data-rol="<?= $rol['id'] ?>">
                                <option value="">— Sin plantilla (usa la matriz vieja por rol) —</option>
                                <?php foreach ($plantillas as $p): if (!$p['activo']) continue; ?>
                                <option value="<?= $p['id'] ?>" <?= (int)($rol['plantilla_permisos_id_default'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-primary py-0 _btnGuardarDefault" data-rol="<?= $rol['id'] ?>">Guardar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal nueva plantilla -->
<div class="modal fade" id="modalNuevaPlantilla" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form id="formNuevaPlantilla">
                <?= Csrf::field() ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Nueva plantilla de permisos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100" placeholder="Ej: Cajero turno mañana">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="255"></textarea>
                    </div>
                    <div id="errNuevaPlantilla" class="alert alert-danger py-2 small mt-2 d-none"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear y configurar matriz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

document.getElementById('formNuevaPlantilla').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('errNuevaPlantilla');
    errEl.classList.add('d-none');
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/permisos/plantillas/crear`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            // Directo a configurar su matriz — una plantilla recién creada sin ningún módulo
            // tildado no sirve de nada todavía.
            window.location.href = `${BASE_URL}/permisos/plantillas/editar?id=${d.id}`;
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    }
});

async function _toggleActivo(id, activar) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/permisos/plantillas/${activar ? 'activar' : 'desactivar'}`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { location.reload(); } else { showToast(d.error, 'danger'); }
    } catch { showToast('Error de conexión.', 'danger'); }
}

document.querySelectorAll('._btnGuardarDefault').forEach(btn => {
    btn.addEventListener('click', async () => {
        const rol = btn.dataset.rol;
        const sel = document.querySelector(`._selRolDefault[data-rol="${rol}"]`);
        const fd = new FormData();
        fd.append('rol_id', rol);
        fd.append('plantilla_id', sel.value);
        fd.append('_csrf_token', CSRF_TOKEN);
        btn.disabled = true;
        try {
            const r = await fetch(`${BASE_URL}/permisos/plantillas/asignar-default-rol`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) showToast(d.mensaje); else showToast(d.error, 'danger');
        } catch {
            showToast('Error de conexión.', 'danger');
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
