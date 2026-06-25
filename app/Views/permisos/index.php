<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Permisos por módulo</h1>
        <p class="text-muted small mb-0">
            Define qué puede ver y editar cada rol. El rol <strong>admin</strong> siempre tiene acceso total.
            Si un rol no tiene ningún permiso configurado, conserva acceso completo (estado inicial).
        </p>
    </div>
</div>

<div class="alert alert-warning border-0 small py-2 mb-4">
    <strong>Atención:</strong> los cambios se aplican en la próxima sesión del usuario. Las sesiones activas mantienen sus permisos actuales hasta que vuelvan a iniciar sesión.
</div>

<form id="formPermisos" novalidate>
    <?= Csrf::field() ?>

    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle" style="min-width:600px">
                    <thead class="table-dark">
                        <tr>
                            <th rowspan="2" class="align-middle" style="min-width:180px">Módulo</th>
                            <?php foreach ($roles as $rol): ?>
                            <th colspan="2" class="text-center text-capitalize border-start">
                                <?= e($rol['nombre']) ?>
                                <?php if ($rol['descripcion']): ?>
                                <div class="fw-normal text-white-50" style="font-size:10px"><?= e($rol['descripcion']) ?></div>
                                <?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($roles as $rol): ?>
                            <th class="text-center small fw-normal border-start py-1" style="min-width:54px">
                                <span>Ver</span><br>
                                <a href="#" class="text-white-50 small chk-all-col" data-rol="<?= $rol['id'] ?>" data-campo="ver" title="Marcar/desmarcar todos">±</a>
                            </th>
                            <th class="text-center small fw-normal py-1" style="min-width:54px">
                                <span>Editar</span><br>
                                <a href="#" class="text-white-50 small chk-all-col" data-rol="<?= $rol['id'] ?>" data-campo="editar" title="Marcar/desmarcar todos">±</a>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulos as $key => $label): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($label) ?></td>
                            <?php foreach ($roles as $rol): ?>
                            <?php $p = $matrix[$rol['id']][$key] ?? ['ver' => false, 'editar' => false]; ?>
                            <td class="text-center border-start p-2">
                                <input type="checkbox"
                                    class="form-check-input chk-ver"
                                    name="p[<?= $rol['id'] ?>][<?= $key ?>][ver]"
                                    value="1"
                                    data-rol="<?= $rol['id'] ?>"
                                    <?= $p['ver'] ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center p-2">
                                <input type="checkbox"
                                    class="form-check-input chk-editar"
                                    name="p[<?= $rol['id'] ?>][<?= $key ?>][editar]"
                                    value="1"
                                    data-rol="<?= $rol['id'] ?>"
                                    <?= $p['editar'] ? 'checked' : '' ?>>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="small text-muted fw-semibold">Acciones rápidas</td>
                            <?php foreach ($roles as $rol): ?>
                            <td class="text-center border-start p-1">
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 small btn-all-ver" data-rol="<?= $rol['id'] ?>" title="Marcar todos Ver">✓ Ver</button>
                            </td>
                            <td class="text-center p-1">
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 small btn-all-edit" data-rol="<?= $rol['id'] ?>" title="Marcar todos Editar">✓ Editar</button>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td></td>
                            <?php foreach ($roles as $rol): ?>
                            <td class="text-center border-start p-1" colspan="2">
                                <button type="button" class="btn btn-outline-danger btn-xs py-0 px-1 small btn-none" data-rol="<?= $rol['id'] ?>">✕ Sin acceso</button>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div id="permError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardarPermisos">Guardar permisos</button>
    </div>
</form>

<style>
.btn-xs { font-size: 11px; }
.form-check-input { width: 1.1em; height: 1.1em; cursor: pointer; }
</style>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

// Toggle-all for a role+field column
document.querySelectorAll('.chk-all-col').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const rol   = link.dataset.rol;
        const campo = link.dataset.campo;
        const boxes = document.querySelectorAll(`.chk-${campo}[data-rol="${rol}"]`);
        const allChecked = [...boxes].every(b => b.checked);
        boxes.forEach(b => b.checked = !allChecked);
    });
});

// Mark all Ver for a role
document.querySelectorAll('.btn-all-ver').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll(`.chk-ver[data-rol="${btn.dataset.rol}"]`).forEach(b => b.checked = true);
    });
});

// Mark all Editar for a role (also marks Ver since edit implies view)
document.querySelectorAll('.btn-all-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        const rol = btn.dataset.rol;
        document.querySelectorAll(`.chk-ver[data-rol="${rol}"],.chk-editar[data-rol="${rol}"]`).forEach(b => b.checked = true);
    });
});

// Remove all for a role
document.querySelectorAll('.btn-none').forEach(btn => {
    btn.addEventListener('click', () => {
        const rol = btn.dataset.rol;
        document.querySelectorAll(`.chk-ver[data-rol="${rol}"],.chk-editar[data-rol="${rol}"]`).forEach(b => b.checked = false);
    });
});

// If editar is checked, auto-check ver (edit implies view)
document.querySelectorAll('.chk-editar').forEach(chk => {
    chk.addEventListener('change', function () {
        if (this.checked) {
            const row = this.closest('tr');
            const verChk = row.querySelector(`.chk-ver[data-rol="${this.dataset.rol}"]`);
            if (verChk) verChk.checked = true;
        }
    });
});

// Submit
document.getElementById('formPermisos').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('permError');
    errEl.classList.add('d-none');
    document.getElementById('btnGuardarPermisos').disabled = true;

    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/permisos/guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnGuardarPermisos').disabled = false;
    }
});
</script>
