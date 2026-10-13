<h1 class="h4 fw-bold mb-3">Cambiar contraseña</h1>

<div class="card border-0 shadow-sm" style="max-width: 480px;">
    <div class="card-body p-4">

        <?php if ($msg): ?>
            <div class="alert alert-<?= e($msg['tipo']) ?> py-2 small"><?= e($msg['texto']) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= url('portal/cambiar-password') ?>" novalidate>
            <?= Csrf::field() ?>

            <div class="mb-3">
                <label for="password_actual" class="form-label fw-semibold">Contraseña actual</label>
                <input
                    type="password"
                    id="password_actual"
                    name="password_actual"
                    class="form-control"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password_nueva" class="form-label fw-semibold">Nueva contraseña</label>
                <div class="input-group">
                    <input
                        type="password"
                        id="password_nueva"
                        name="password_nueva"
                        class="form-control"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        required
                        minlength="8"
                    >
                    <button type="button" class="btn btn-outline-secondary" id="btnToggleNueva" tabindex="-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirm" class="form-label fw-semibold">Confirmar nueva contraseña</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    class="form-control"
                    placeholder="Repetí la contraseña"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                Guardar contraseña
            </button>
        </form>

    </div>
</div>

<script>
document.getElementById('btnToggleNueva')?.addEventListener('click', function() {
    const pwd = document.getElementById('password_nueva');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
});
</script>
