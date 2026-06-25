<?php $esEdicion = !empty($cliente); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h1>
    <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<form id="formCliente" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?>
    <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Datos del cliente</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Nombre / Razón social <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150"
                           value="<?= e($cliente['nombre'] ?? '') ?>" placeholder="Nombre completo o razón social">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="fActivo" value="1"
                               <?= ($cliente['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fActivo">Cliente activo</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tipo de documento</label>
                    <select name="tipo_doc" class="form-select" id="fTipoDoc">
                        <option value="dni"  <?= ($cliente['tipo_doc'] ?? 'dni') === 'dni'  ? 'selected' : '' ?>>DNI</option>
                        <option value="cuit" <?= ($cliente['tipo_doc'] ?? '')    === 'cuit' ? 'selected' : '' ?>>CUIT</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Número de documento</label>
                    <input type="text" name="documento" class="form-control" maxlength="20"
                           value="<?= e($cliente['documento'] ?? '') ?>"
                           placeholder="DNI o XX-XXXXXXXX-X (CUIT)" id="fDocumento">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Condición IVA</label>
                    <select name="condicion_iva" class="form-select">
                        <?php
                        $condiciones = [
                            'consumidor_final'       => 'Consumidor Final',
                            'responsable_inscripto'  => 'Responsable Inscripto',
                            'monotributista'         => 'Monotributista',
                            'exento'                 => 'Exento',
                        ];
                        $condActual = $cliente['condicion_iva'] ?? 'consumidor_final';
                        foreach ($condiciones as $val => $label):
                        ?>
                        <option value="<?= $val ?>" <?= $condActual === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent">
            <h6 class="fw-semibold mb-0">Contacto</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" maxlength="30"
                           value="<?= e($cliente['telefono'] ?? '') ?>" placeholder="Ej: 011-4567-8901">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="150"
                           value="<?= e($cliente['email'] ?? '') ?>" placeholder="cliente@email.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Dirección</label>
                    <input type="text" name="direccion" class="form-control" maxlength="255"
                           value="<?= e($cliente['direccion'] ?? '') ?>" placeholder="Calle, número, localidad">
                </div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar' : 'Crear cliente' ?>
        </button>
        <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
</div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;

// Placeholder dinámico según tipo de doc
document.getElementById('fTipoDoc').addEventListener('change', function () {
    const docInput = document.getElementById('fDocumento');
    docInput.placeholder = this.value === 'cuit' ? 'XX-XXXXXXXX-X' : 'Número de DNI';
});

document.getElementById('formCliente').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');
    document.getElementById('btnGuardar').disabled = true;

    const fd = new FormData(this);
    if (!fd.has('activo')) fd.set('activo', '0');

    const endpoint = ES_EDICION ? `${BASE_URL}/clientes/actualizar` : `${BASE_URL}/clientes/crear`;

    try {
        const r = await fetch(endpoint, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/clientes`; }, 800);
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnGuardar').disabled = false;
    }
});
</script>
