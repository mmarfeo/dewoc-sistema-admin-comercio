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
                    <label class="form-label fw-semibold">CBU <small class="text-muted">(22 dígitos)</small></label>
                    <input type="text" name="cbu" class="form-control" maxlength="22" value="<?= e($proveedor['cbu'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><?= $esEdicion ? 'Actualizar' : 'Crear proveedor' ?></button>
        <a href="<?= url('proveedores') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
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
</script>
