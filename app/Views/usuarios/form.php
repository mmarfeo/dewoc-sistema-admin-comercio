<?php $esEdicion = !empty($usuario); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
    <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<form id="formUsuario" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $usuario['id'] ?>"><?php endif; ?>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos personales</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="100" value="<?= e($usuario['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="150" value="<?= e($usuario['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($roles as $rol): ?>
                        <option value="<?= $rol['id'] ?>" <?= ($usuario['rol_id'] ?? '') == $rol['id'] ? 'selected' : '' ?>>
                            <?= e(ucfirst($rol['nombre'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($esEdicion): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="fActivo" value="1" <?= $usuario['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fActivo">Usuario activo</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 mb-3">
        <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Contraseña <?= $esEdicion ? '<small class="text-muted fw-normal">(dejar vacío para no cambiar)</small>' : '' ?></h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nueva contraseña <?= !$esEdicion ? '<span class="text-danger">*</span>' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= !$esEdicion ? 'required' : '' ?> minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirmar contraseña</label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Repetir contraseña">
                </div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger d-none mb-3"></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <?= $esEdicion ? 'Actualizar' : 'Crear usuario' ?>
        </button>
        <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
</div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>'; const ES_EDICION=<?= $esEdicion?'true':'false' ?>;
document.getElementById('formUsuario').addEventListener('submit', async function(e){
    e.preventDefault();
    const errEl=document.getElementById('formError'); errEl.classList.add('d-none');
    document.getElementById('btnGuardar').disabled=true;
    const fd=new FormData(this);
    if(!fd.has('activo'))fd.set('activo','0');
    const url=ES_EDICION?`${BASE_URL}/usuarios/actualizar`:`${BASE_URL}/usuarios/crear`;
    try{
        const r=await fetch(url,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){showToast(d.mensaje);setTimeout(()=>{window.location.href=`${BASE_URL}/usuarios`;},800);}
        else{errEl.textContent=d.error;errEl.classList.remove('d-none');}
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');}
    finally{document.getElementById('btnGuardar').disabled=false;}
});
</script>
