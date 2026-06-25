<div class="row justify-content-center">
    <div class="col-lg-6">
        <h1 class="h3 fw-bold mb-4">Mi Perfil</h1>

        <!-- Avatar + foto -->
        <div class="card border-0 mb-4">
            <div class="card-body d-flex align-items-center gap-4">
                <?php
                $fotoPath = !empty($usuario['foto']) ? BASE_PATH . '/' . $usuario['foto'] : null;
                $fotoUrl  = ($fotoPath && file_exists($fotoPath))
                    ? BASE_URL . '/' . e($usuario['foto']) . '?v=' . filemtime($fotoPath)
                    : null;
                ?>
                <img id="avatarImg"
                     src="<?= $fotoUrl ?: 'https://ui-avatars.com/api/?name=' . urlencode($usuario['nombre']) . '&size=200&background=0d6efd&color=fff' ?>"
                     alt="Avatar" width="90" height="90"
                     class="rounded-circle object-fit-cover border">
                <div>
                    <p class="fw-semibold mb-1"><?= e($usuario['nombre']) ?></p>
                    <p class="text-muted small mb-2"><?= e($usuario['email']) ?></p>
                    <form id="formFoto" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                        <?= Csrf::field() ?>
                        <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png,image/webp"
                               class="form-control form-control-sm" style="max-width:220px">
                        <button type="submit" class="btn btn-sm btn-outline-primary" id="btnSubirFoto">Subir</button>
                    </form>
                    <div id="fotoMsg" class="mt-2 small d-none"></div>
                </div>
            </div>
        </div>

        <!-- Datos del usuario -->
        <div class="card border-0 mb-4">
            <div class="card-header border-0 bg-transparent fw-semibold">Información personal</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Nombre</dt>
                    <dd class="col-sm-8"><?= e($usuario['nombre']) ?></dd>
                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8"><?= e($usuario['email']) ?></dd>
                    <dt class="col-sm-4 text-muted">Rol</dt>
                    <dd class="col-sm-8"><span class="badge bg-primary-subtle text-primary-emphasis"><?= e($usuario['rol'] ?? '—') ?></span></dd>
                    <dt class="col-sm-4 text-muted">Estado</dt>
                    <dd class="col-sm-8">
                        <?php if ($usuario['activo']): ?>
                        <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                        <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger-emphasis">Inactivo</span>
                        <?php endif; ?>
                    </dd>
                    <?php if (!empty($usuario['ultimo_login'])): ?>
                    <dt class="col-sm-4 text-muted">Último acceso</dt>
                    <dd class="col-sm-8"><?= date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Cambiar contraseña -->
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent fw-semibold">Cambiar contraseña</div>
            <div class="card-body">
                <form id="formPassword" novalidate>
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña actual</label>
                        <input type="password" name="password_actual" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva contraseña</label>
                        <input type="password" name="password_nueva" class="form-control" required autocomplete="new-password"
                               minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmar" class="form-control" required autocomplete="new-password">
                    </div>

                    <div id="perfilMsg" class="alert d-none mb-3 small"></div>

                    <button type="submit" class="btn btn-primary" id="btnGuardar">
                        Actualizar contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';

document.getElementById('formFoto').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msgEl = document.getElementById('fotoMsg');
    const btn   = document.getElementById('btnSubirFoto');
    msgEl.className = 'mt-2 small d-none';
    btn.disabled = true;

    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/usuarios/subir-foto`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            document.getElementById('avatarImg').src = d.url;
            msgEl.className = 'mt-2 small text-success';
            msgEl.textContent = d.mensaje;
        } else {
            msgEl.className = 'mt-2 small text-danger';
            msgEl.textContent = d.error;
        }
    } catch {
        msgEl.className = 'mt-2 small text-danger';
        msgEl.textContent = 'Error de conexión.';
    } finally {
        btn.disabled = false;
        msgEl.classList.remove('d-none');
    }
});

document.getElementById('formPassword').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msgEl = document.getElementById('perfilMsg');
    const btn   = document.getElementById('btnGuardar');
    msgEl.className = 'alert d-none mb-3 small';
    btn.disabled = true;

    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/usuarios/cambiar-password`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            msgEl.className = 'alert alert-success mb-3 small';
            msgEl.textContent = d.mensaje;
            this.reset();
        } else {
            msgEl.className = 'alert alert-danger mb-3 small';
            msgEl.textContent = d.error;
        }
    } catch {
        msgEl.className = 'alert alert-danger mb-3 small';
        msgEl.textContent = 'Error de conexión.';
    } finally {
        btn.disabled = false;
        msgEl.classList.remove('d-none');
    }
});
</script>
