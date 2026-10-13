<?php $esEdicion = $tema !== null; ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar guía: ' . e($tema['titulo']) : 'Nueva guía' ?></h1>
    <a href="<?= url('ayuda-temas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formTema" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$tema['id'] : '' ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 mb-3">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-semibold mb-0">Datos de la guía</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Título <span class="text-danger">*</span></label>
                            <input type="text" name="titulo" class="form-control" maxlength="150" required
                                   value="<?= $esEdicion ? e($tema['titulo']) : '' ?>" placeholder="Ej. Cómo facturar en el POS">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">
                                Clave "tema" <span class="text-danger">*</span>
                                <span class="text-muted fw-normal">(minúsculas, números y _ — no se puede cambiar después sin romper el historial del chat)</span>
                            </label>
                            <input type="text" name="tema" class="form-control" maxlength="60" required pattern="[a-z0-9_]+"
                                   value="<?= $esEdicion ? e($tema['tema']) : '' ?>" placeholder="Ej. pos_facturar">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Módulo <span class="text-danger">*</span></label>
                            <select name="modulo" class="form-select" required>
                                <option value="">— Elegir —</option>
                                <?php foreach ($modulos as $clave => $label): ?>
                                <option value="<?= e($clave) ?>" <?= ($esEdicion && $tema['modulo'] === $clave) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo se ofrece esta guía a usuarios que tengan permiso de ver este módulo.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">URL destino <span class="text-danger">*</span></label>
                            <input type="text" name="url" class="form-control" maxlength="150" required
                                   value="<?= $esEdicion ? e($tema['url']) : '' ?>" placeholder="Ej. ventas/pos">
                            <div class="form-text">Ruta relativa del sistema — a dónde lleva el botón "Ir ahora".</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tour guiado</label>
                            <select name="tour_id" class="form-select">
                                <option value="">— Sin tour —</option>
                                <?php foreach ($tourIds as $clave => $label): ?>
                                <option value="<?= e($clave) ?>" <?= ($esEdicion && $tema['tour_id'] === $clave) ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo aparecen acá los tours ya armados en el sistema (assets/js/tour.js).</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="activo" id="fActivo" value="1"
                                       <?= (!$esEdicion || $tema['activo']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="fActivo">Activa (el Chat IA la ofrece)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Manual (pasos) <span class="text-danger">*</span></label>
                            <textarea name="manual" class="form-control" rows="8" required
                                      placeholder="1. Andá a...&#10;2. Tocá...&#10;3. ..."><?= $esEdicion ? e($tema['manual']) : '' ?></textarea>
                            <div class="form-text">Se le inyecta a Gemini tal cual — el modelo responde en sus propias palabras basándose en estos pasos.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0">
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        Esta guía queda disponible en el Chat IA para cualquier usuario que tenga permiso de
                        ver el módulo elegido. Si el usuario pregunta por un tema al que no tiene acceso, el
                        asistente le avisa que no tiene permiso, sin explicarle los pasos.
                    </p>
                </div>
            </div>
            <div id="formError" class="alert alert-danger d-none py-2 small mt-3"></div>
            <button type="submit" class="btn btn-primary w-100 mt-3" id="btnGuardar">
                <?= $esEdicion ? 'Guardar cambios' : 'Crear guía' ?>
            </button>
        </div>
    </div>
</form>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';
const ES_EDICION = <?= $esEdicion ? 'true' : 'false' ?>;

function mostrarError(msg) {
    const el = document.getElementById('formError');
    el.textContent = msg;
    el.classList.remove('d-none');
}

document.getElementById('formTema').addEventListener('submit', async function (e) {
    e.preventDefault();
    document.getElementById('formError').classList.add('d-none');
    document.getElementById('btnGuardar').disabled = true;

    const url = ES_EDICION ? `${BASE_URL}/ayuda-temas/actualizar` : `${BASE_URL}/ayuda-temas/guardar`;
    try {
        const fd = new FormData(this);
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje, 'success');
            setTimeout(() => location.href = `${BASE_URL}/ayuda-temas`, 800);
        } else {
            mostrarError(d.error || 'No se pudo guardar.');
        }
    } catch {
        mostrarError('Error de conexión.');
    } finally {
        document.getElementById('btnGuardar').disabled = false;
    }
});
</script>
