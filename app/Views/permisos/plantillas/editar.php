<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Plantilla: <?= e($plantilla['nombre']) ?></h1>
    <a href="<?= url('permisos/plantillas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="card border-0 mb-4">
    <div class="card-body">
        <form id="formCabecera" class="row g-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $plantilla['id'] ?>">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required maxlength="100" value="<?= e($plantilla['nombre']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Descripción</label>
                <input type="text" name="descripcion" class="form-control" maxlength="255" value="<?= e($plantilla['descripcion'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">Guardar</button>
            </div>
        </form>
    </div>
</div>

<form id="formMatriz" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $plantilla['id'] ?>">

    <div class="card border-0">
        <div class="card-header border-0 bg-transparent fw-semibold">Matriz de permisos</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width:260px">Módulo</th>
                            <th class="text-center" style="width:100px">Ver</th>
                            <th class="text-center" style="width:100px">Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulos as $key => $label): ?>
                        <?php $p = $plantilla['matriz'][$key] ?? ['ver' => false, 'editar' => false]; ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($label) ?></td>
                            <td class="text-center p-2">
                                <input type="checkbox" class="form-check-input chk-ver" name="p[<?= $key ?>][ver]" value="1" <?= $p['ver'] ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center p-2">
                                <input type="checkbox" class="form-check-input chk-editar" data-modulo="<?= $key ?>" name="p[<?= $key ?>][editar]" value="1" <?= $p['editar'] ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="small text-muted fw-semibold">Acciones rápidas</td>
                            <td class="text-center p-1"><button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnTodoVer">✓ Todo</button></td>
                            <td class="text-center p-1"><button type="button" class="btn btn-outline-secondary btn-sm py-0" id="btnTodoEditar">✓ Todo</button></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td colspan="2" class="text-center p-1"><button type="button" class="btn btn-outline-danger btn-sm py-0" id="btnNada">✕ Sin acceso</button></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div id="matrizError" class="alert alert-danger mt-3 d-none"></div>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardarMatriz">Guardar matriz</button>
    </div>
</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

document.getElementById('formCabecera').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/permisos/plantillas/actualizar-cabecera`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) showToast(d.mensaje); else showToast(d.error, 'danger');
    } catch { showToast('Error de conexión.', 'danger'); }
});

// Editar implica Ver — mismo criterio que /permisos por rol.
document.querySelectorAll('.chk-editar').forEach(chk => {
    chk.addEventListener('change', function () {
        if (this.checked) {
            this.closest('tr').querySelector('.chk-ver').checked = true;
        }
    });
});
document.getElementById('btnTodoVer').addEventListener('click', () => {
    document.querySelectorAll('.chk-ver').forEach(b => b.checked = true);
});
document.getElementById('btnTodoEditar').addEventListener('click', () => {
    document.querySelectorAll('.chk-ver,.chk-editar').forEach(b => b.checked = true);
});
document.getElementById('btnNada').addEventListener('click', () => {
    document.querySelectorAll('.chk-ver,.chk-editar').forEach(b => b.checked = false);
});

document.getElementById('formMatriz').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('matrizError');
    errEl.classList.add('d-none');
    document.getElementById('btnGuardarMatriz').disabled = true;
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/permisos/plantillas/guardar-matriz`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); } else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnGuardarMatriz').disabled = false;
    }
});
</script>
