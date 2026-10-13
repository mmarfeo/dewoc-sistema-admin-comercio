<?php
$esEdicion  = !empty($usuario);
$rolNombre  = '';
// Nombre de la plantilla por defecto del ROL de este usuario (para mostrar "— Heredar la
// plantilla de su rol (Nombre) —" en el select de abajo) — ver roles.plantilla_permisos_id_default
// / PlantillaPermisosModel. Ambas búsquedas son en PHP puro (arrays chicos, ~5 roles) para no
// necesitar una consulta aparte ni un JOIN — $roles ya viene con SELECT * (UsuarioModel::getRoles()),
// así que la columna nueva aparece sola una vez corrida la migración, sin romper antes de eso.
$rolPlantillaDefaultId     = null;
$rolPlantillaDefaultNombre = null;
if ($esEdicion) {
    foreach ($roles as $rol) {
        if ((int)$rol['id'] === (int)$usuario['rol_id']) {
            $rolNombre = $rol['nombre'];
            $rolPlantillaDefaultId = $rol['plantilla_permisos_id_default'] ?? null;
            break;
        }
    }
    if ($rolPlantillaDefaultId) {
        foreach (($plantillas ?? []) as $pl) {
            if ((int)$pl['id'] === (int)$rolPlantillaDefaultId) { $rolPlantillaDefaultNombre = $pl['nombre']; break; }
        }
    }
}
?>
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
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pantalla de inicio</label>
                    <select name="pantalla_inicio" class="form-select">
                        <option value="">— Chat IA (predeterminado) —</option>
                        <?php foreach (($modulos ?? []) as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>" <?= ($usuario['pantalla_inicio'] ?? '') === $clave ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">A qué sección entra este usuario apenas inicia sesión.</div>
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
                <div class="col-md-6 d-none" id="wrapVeTareasGenerales">
                    <label class="form-label fw-semibold">Tareas asignadas a "Administradores"</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="ve_tareas_generales" id="fVeTareasGenerales" value="1" <?= !empty($usuario['ve_tareas_generales']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fVeTareasGenerales">Ve y puede accionar las tareas generales</label>
                    </div>
                    <div class="form-text">
                        No todo admin tiene por qué ver esto — solo se aplica a usuarios con rol Admin,
                        y por defecto queda apagado aunque el usuario ya sea admin.
                    </div>
                </div>
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

<?php if ($esEdicion && $rolNombre !== 'admin'): ?>
<div class="card border-0 mt-3">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Permisos de este usuario</h6>
    </div>
    <div class="card-body">
        <!-- Plantilla puntual del usuario (ver docs de la conversación que armó esto —
             "Permission Sets" estilo Salesforce). Manda por sobre la plantilla por defecto
             de su Rol, que a su vez se configura desde /permisos/plantillas, no acá. -->
        <label class="form-label fw-semibold small">Plantilla de permisos</label>
        <div class="d-flex gap-2">
            <select id="selPlantillaUsuario" class="form-select form-select-sm">
                <option value="">
                    — Heredar la plantilla de su rol<?= $rolPlantillaDefaultNombre ? " ({$rolPlantillaDefaultNombre})" : ' (su rol todavía no tiene una plantilla por defecto asignada — usa la matriz vieja de Permisos por módulo)' ?> —
                </option>
                <?php foreach (($plantillas ?? []) as $pl): ?>
                <option value="<?= $pl['id'] ?>" <?= (int)($usuario['plantilla_permisos_id'] ?? 0) === (int)$pl['id'] ? 'selected' : '' ?>>
                    <?= e($pl['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btnGuardarPlantillaUsuario">Guardar</button>
        </div>
        <div class="form-text mb-3">
            Elegir una acá reemplaza, SOLO para este usuario, la plantilla que le tocaría por su
            rol. Administrá las plantillas y a qué rol se asignan por defecto desde
            <a href="<?= url('permisos/plantillas') ?>" target="_blank">Plantillas de permisos ↗</a>.
        </div>

        <hr>

        <h6 class="fw-semibold small mb-1">Excepciones puntuales</h6>
        <p class="small text-muted mb-2">
            Otorgan (o quitan) un permiso de UN módulo puntual para este usuario, por encima de
            lo que le dé su plantilla/rol — opcionalmente por tiempo limitado. El resto de los
            módulos sigue viniendo de su plantilla/rol sin tocarlos.
        </p>
        <div id="listaExcepcionesPermisos" class="mb-2"></div>

        <div class="row g-2 align-items-end border-top pt-3 mt-1">
            <div class="col-md-4">
                <label class="form-label small mb-1">Módulo</label>
                <select id="selNuevaExcepcionModulo" class="form-select form-select-sm">
                    <?php foreach ($modulos as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="chkNuevaExcepcionVer">
                    <label class="form-check-label small" for="chkNuevaExcepcionVer">Ver</label>
                </div>
            </div>
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="chkNuevaExcepcionEditar">
                    <label class="form-check-label small" for="chkNuevaExcepcionEditar">Editar</label>
                </div>
            </div>
            <div class="col-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="chkNuevaExcepcionTemporal">
                    <label class="form-check-label small" for="chkNuevaExcepcionTemporal">Temporal, hasta:</label>
                </div>
            </div>
            <div class="col-md-3">
                <input type="datetime-local" id="inputNuevaExcepcionVence" class="form-control form-control-sm" disabled>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-primary" id="btnAgregarExcepcion">+ Agregar</button>
            </div>
        </div>
        <div id="excepcionesError" class="alert alert-danger py-2 small mt-2 d-none"></div>
    </div>
</div>
<?php endif; ?>

<?php if ($esEdicion): ?>
<div class="card border-0 mt-3">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Accesos directos del Chat IA</h6>
    </div>
    <div class="card-body">
        <p class="small text-muted">
            Elegí qué botones rápidos ve este usuario debajo del chat. El catálogo se administra desde
            <a href="<?= url('chat/accesos') ?>">Chat IA → Accesos directos</a>.
        </p>
        <?php if (!$catalogoAccesosChat): ?>
        <div class="text-muted small">Todavía no hay accesos directos cargados en el catálogo.</div>
        <?php else: ?>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($catalogoAccesosChat as $acc): ?>
            <div class="form-check">
                <input class="form-check-input chk-acceso-chat" type="checkbox" id="acc<?= $acc['id'] ?>" value="<?= $acc['id'] ?>"
                    <?= in_array((int)$acc['id'], $accesosChatAsignados, true) ? 'checked' : '' ?>
                    <?= !$acc['activo'] ? 'disabled' : '' ?>>
                <label class="form-check-label small" for="acc<?= $acc['id'] ?>">
                    <?= e($acc['label']) ?>
                    <?php if (!$acc['activo']): ?><span class="text-muted">(oculto en el catálogo)</span><?php endif; ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2 mt-3">
            <button type="button" class="btn btn-sm btn-primary" id="btnGuardarAccesosChat">Guardar accesos directos</button>
            <span id="accesosChatMsg" class="small"></span>
        </div>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>'; const ES_EDICION=<?= $esEdicion?'true':'false' ?>;

// "Ve tareas generales" solo tiene sentido para rol Admin — se oculta para el resto.
(function(){
    const selRol = document.querySelector('select[name="rol_id"]');
    const wrap   = document.getElementById('wrapVeTareasGenerales');
    if (!selRol || !wrap) return;
    function actualizar(){
        const esAdmin = (selRol.options[selRol.selectedIndex]?.text || '').trim().toLowerCase() === 'admin';
        wrap.classList.toggle('d-none', !esAdmin);
    }
    selRol.addEventListener('change', actualizar);
    actualizar();
})();

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

// ── Plantilla de permisos + excepciones puntuales del usuario ───────────────────
// (ver PlantillaPermisosController/UsuarioModel::getPermisosEfectivos() — "Permission Sets")
const USUARIO_ID = <?= $esEdicion ? (int)$usuario['id'] : 'null' ?>;
const MODULOS_LABELS = <?= json_encode($modulos ?? [], JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('btnGuardarPlantillaUsuario')?.addEventListener('click', async function () {
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('usuario_id', USUARIO_ID);
    fd.append('plantilla_id', document.getElementById('selPlantillaUsuario').value);
    this.disabled = true;
    try {
        const r = await fetch(`${BASE_URL}/usuarios/asignar-plantilla`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) showToast(d.mensaje); else showToast(d.error, 'danger');
    } catch {
        showToast('Error de conexión.', 'danger');
    } finally {
        this.disabled = false;
    }
});

// ── Excepciones puntuales: lista + agregar/quitar una por una ───────────────────
let _excepciones = <?= json_encode($excepcionesPermisos ?? [], JSON_UNESCAPED_UNICODE) ?>;

function _formatVence(vence) {
    // 'Y-m-d H:i:s' (MySQL) → 'DD/MM HH:MM' — sin librería de fechas, ya viene en hora local del servidor.
    const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/.exec(vence || '');
    return m ? `${m[3]}/${m[2]} ${m[4]}:${m[5]}` : vence;
}

function _renderExcepciones() {
    const cont = document.getElementById('listaExcepcionesPermisos');
    if (!_excepciones.length) {
        cont.innerHTML = '<p class="small text-muted mb-0">Sin excepciones — este usuario usa exactamente lo que le da su plantilla/rol.</p>';
        return;
    }
    const ahora = new Date();
    cont.innerHTML = _excepciones.map(ex => {
        const vencida = ex.vence_en && new Date(ex.vence_en.replace(' ', 'T')) <= ahora;
        const label = MODULOS_LABELS[ex.modulo] || ex.modulo;
        const flags = [ex.ver == 1 ? 'Ver' : null, ex.editar == 1 ? 'Editar' : null].filter(Boolean);
        return `
        <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
            <span class="small">
                <strong>${esc(label)}</strong>
                — ${flags.length ? esc(flags.join(' + ')) : '<span class="text-danger">sin acceso (quita lo que le daría su plantilla/rol)</span>'}
                ${ex.vence_en ? `<span class="badge ${vencida ? 'bg-secondary-soft text-secondary' : 'bg-warning-soft text-warning'} ms-1">${vencida ? 'venció' : 'vence'} ${esc(_formatVence(ex.vence_en))}</span>` : ''}
            </span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="_quitarExcepcion('${ex.modulo}')">✕</button>
        </div>`;
    }).join('');
}
function esc(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

document.getElementById('chkNuevaExcepcionTemporal')?.addEventListener('change', function () {
    document.getElementById('inputNuevaExcepcionVence').disabled = !this.checked;
});

document.getElementById('btnAgregarExcepcion')?.addEventListener('click', async function () {
    const errEl = document.getElementById('excepcionesError');
    errEl.classList.add('d-none');

    const modulo  = document.getElementById('selNuevaExcepcionModulo').value;
    const ver     = document.getElementById('chkNuevaExcepcionVer').checked;
    const editar  = document.getElementById('chkNuevaExcepcionEditar').checked;
    const esTemporal = document.getElementById('chkNuevaExcepcionTemporal').checked;
    const vence   = esTemporal ? document.getElementById('inputNuevaExcepcionVence').value : '';

    if (esTemporal && !vence) {
        errEl.textContent = 'Elegí hasta cuándo, o destildá "Temporal".';
        errEl.classList.remove('d-none');
        return;
    }

    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('usuario_id', USUARIO_ID);
    fd.append('modulo', modulo);
    if (ver) fd.append('ver', '1');
    if (editar) fd.append('editar', '1');
    fd.append('vence_en', vence);

    this.disabled = true;
    try {
        const r = await fetch(`${BASE_URL}/usuarios/guardar-excepcion-permiso`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _excepciones = d.excepciones;
            _renderExcepciones();
            showToast(d.mensaje);
            document.getElementById('chkNuevaExcepcionVer').checked = false;
            document.getElementById('chkNuevaExcepcionEditar').checked = false;
            document.getElementById('chkNuevaExcepcionTemporal').checked = false;
            document.getElementById('inputNuevaExcepcionVence').value = '';
            document.getElementById('inputNuevaExcepcionVence').disabled = true;
        } else {
            errEl.textContent = d.error || 'No se pudo guardar.';
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        this.disabled = false;
    }
});

async function _quitarExcepcion(modulo) {
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('usuario_id', USUARIO_ID);
    fd.append('modulo', modulo);
    try {
        const r = await fetch(`${BASE_URL}/usuarios/quitar-excepcion-permiso`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { _excepciones = d.excepciones; _renderExcepciones(); showToast(d.mensaje); }
        else showToast(d.error || 'No se pudo quitar.', 'danger');
    } catch {
        showToast('Error de conexión.', 'danger');
    }
}

if (document.getElementById('listaExcepcionesPermisos')) _renderExcepciones();

// ── Accesos directos del Chat IA asignados a este usuario ───────────────────
const btnGuardarAccesosChat = document.getElementById('btnGuardarAccesosChat');
if (btnGuardarAccesosChat) {
    btnGuardarAccesosChat.addEventListener('click', async () => {
        const msg = document.getElementById('accesosChatMsg');
        btnGuardarAccesosChat.disabled = true;
        msg.textContent = '';
        msg.className = 'small';

        const fd = new FormData();
        fd.append('_csrf_token', '<?= Csrf::token() ?>');
        fd.append('usuario_id', '<?= (int)($usuario['id'] ?? 0) ?>');
        document.querySelectorAll('.chk-acceso-chat:checked').forEach(cb => {
            fd.append('accesos[]', cb.value);
        });

        try {
            const r = await fetch(`${BASE_URL}/usuarios/guardar-accesos-chat`, { method: 'POST', body: fd });
            const d = await r.json();
            msg.textContent = d.ok ? d.mensaje : (d.error || 'No se pudo guardar.');
            msg.className = 'small ' + (d.ok ? 'text-success' : 'text-danger');
        } catch {
            msg.textContent = 'Error de conexión.';
            msg.className = 'small text-danger';
        } finally {
            btnGuardarAccesosChat.disabled = false;
        }
    });
}
</script>
