<?php $esEdicion = !empty($empleado); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar empleado' : 'Nuevo empleado' ?></h1>
    <a href="<?= url('empleados') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<form id="formEmpleado" method="POST" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $empleado['id'] ?>"><?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos del empleado</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150" value="<?= e($empleado['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Documento (DNI/CUIL)</label>
                    <input type="text" name="documento" class="form-control" maxlength="20" value="<?= e($empleado['documento'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Puesto / Cargo</label>
                    <input type="text" name="puesto" class="form-control" maxlength="100" placeholder="Ej: Vendedor, Cajero, Producción…" value="<?= e($empleado['puesto'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Fecha de ingreso</label>
                    <input type="date" name="fecha_ingreso" class="form-control" value="<?= e($empleado['fecha_ingreso'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Modalidad de pago</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Modalidad</label>
                    <select name="modalidad_pago" id="fModalidad" class="form-select">
                        <?php $modActual = $empleado['modalidad_pago'] ?? 'mensual'; ?>
                        <option value="mensual"   <?= $modActual==='mensual'  ?'selected':'' ?>>Mensual</option>
                        <option value="quincenal" <?= $modActual==='quincenal'?'selected':'' ?>>Quincenal</option>
                        <option value="diario"    <?= $modActual==='diario'   ?'selected':'' ?>>Por día</option>
                        <option value="por_hora"  <?= $modActual==='por_hora' ?'selected':'' ?>>Por hora</option>
                    </select>
                </div>
                <div class="col-md-4" id="wrapSueldoMensual">
                    <label class="form-label fw-semibold">Sueldo mensual ($)</label>
                    <input type="number" name="sueldo_mensual" class="form-control" min="0" step="0.01" value="<?= e($empleado['sueldo_mensual'] ?? '0') ?>">
                    <div class="form-text">Quincenal = se paga la mitad en cada liquidación.</div>
                </div>
                <div class="col-md-4 d-none" id="wrapValorDia">
                    <label class="form-label fw-semibold">Valor día ($)</label>
                    <input type="number" name="valor_dia" class="form-control" min="0" step="0.01" value="<?= e($empleado['valor_dia'] ?? '0') ?>">
                </div>
                <div class="col-md-4 d-none" id="wrapValorHora">
                    <label class="form-label fw-semibold">Valor hora ($)</label>
                    <input type="number" name="valor_hora" id="fValorHora" class="form-control" min="0" step="0.01" value="<?= e($empleado['valor_hora'] ?? '0') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Recargo por feriado trabajado (%)</label>
                    <input type="number" name="markup_feriado_pct" class="form-control" min="0" step="1" value="<?= e($empleado['markup_feriado_pct'] ?? '100') ?>">
                    <div class="form-text">100% = se paga el doble ese día/hora feriado.</div>
                </div>
                <div class="col-md-4 d-none" id="wrapHorasMensuales">
                    <label class="form-label fw-semibold">Horas a trabajar por mes <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="number" name="horas_mensuales" id="fHorasMensuales" class="form-control" min="0" step="0.01" value="<?= e($empleado['horas_mensuales'] ?? '0') ?>">
                    <div class="form-text">
                        Si la cargás, se paga como sueldo fijo mensual (horas × valor hora) y en las jornadas solo se
                        cargan los feriados trabajados — igual que un empleado mensual. Dejala en 0 para seguir
                        cargando cada día trabajado como hasta ahora.
                    </div>
                </div>
                <div class="col-md-4 d-none" id="wrapValorMensualEstimado">
                    <label class="form-label fw-semibold">Valor mensual estimado</label>
                    <input type="text" id="fValorMensualEstimado" class="form-control" disabled>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Contacto</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" maxlength="30" value="<?= e($empleado['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="150" value="<?= e($empleado['email'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control" rows="2"><?= e($empleado['notas'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-body py-3">
            <input type="hidden" name="activo" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="switchActivo" name="activo" value="1" <?= ($empleado['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="switchActivo">Empleado activo</label>
                <div class="text-muted small mt-1">Los empleados inactivos no aparecen en el selector al registrar un pago de sueldo.</div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><?= $esEdicion ? 'Actualizar' : 'Crear empleado' ?></button>
        <a href="<?= url('empleados') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

// ── Mostrar solo los campos de tarifa relevantes según la modalidad ───────────
function _actualizarCamposModalidad() {
    const modalidad = document.getElementById('fModalidad').value;
    document.getElementById('wrapSueldoMensual').classList.toggle('d-none', !['mensual','quincenal'].includes(modalidad));
    document.getElementById('wrapValorDia').classList.toggle('d-none', modalidad !== 'diario');
    document.getElementById('wrapValorHora').classList.toggle('d-none', modalidad !== 'por_hora');
    document.getElementById('wrapHorasMensuales').classList.toggle('d-none', modalidad !== 'por_hora');
    document.getElementById('wrapValorMensualEstimado').classList.toggle('d-none', modalidad !== 'por_hora');
    if (modalidad === 'por_hora') _actualizarValorMensualEstimado();
}
document.getElementById('fModalidad').addEventListener('change', _actualizarCamposModalidad);

// ── Horas mensuales × valor hora = valor mensual estimado a pagar ─────────────
function _actualizarValorMensualEstimado() {
    const horas = parseFloat(document.getElementById('fHorasMensuales').value) || 0;
    const valorHora = parseFloat(document.getElementById('fValorHora').value) || 0;
    const total = horas * valorHora;
    document.getElementById('fValorMensualEstimado').value =
        '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
document.getElementById('fHorasMensuales').addEventListener('input', _actualizarValorMensualEstimado);
document.getElementById('fValorHora').addEventListener('input', _actualizarValorMensualEstimado);
_actualizarCamposModalidad();

document.getElementById('formEmpleado').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');
    const isEdit = !!document.querySelector('input[name="id"]')?.value;
    const url    = isEdit ? `${BASE_URL}/empleados/actualizar` : `${BASE_URL}/empleados/crear`;
    const fd     = new FormData(this);
    try {
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => window.location.href=`${BASE_URL}/empleados`, 900); }
        else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.textContent='Error de conexión.'; errEl.classList.remove('d-none'); }
});
</script>
