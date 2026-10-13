<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Login') ?> — <?= APP_NAME ?></title>
    <link rel="manifest" href="<?= url('manifest.json') ?>">
    <link rel="icon" href="<?= asset('favicon/favicon.ico') ?>" sizes="any">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/theme.bundle.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">

    <div class="w-100" style="max-width: 440px; padding: 1rem;">

        <!-- Logo + título -->
        <div class="text-center mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--bs-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-3">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <h1 class="h3 fw-bold mb-1"><?= APP_NAME ?></h1>
            <p class="text-muted">Ingresá con tus credenciales</p>
        </div>

        <!-- Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <?php if ($error): ?>
                    <div class="alert alert-<?= e($error['tipo']) ?> py-2 small"><?= e($error['texto']) ?></div>
                <?php endif; ?>

                <?php if ($webauthnHabilitado): ?>
                <div id="webauthnBox" class="d-none mb-4">
                    <button type="button" id="btnLoginBiometrico" class="btn btn-success w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 11c1.5 0 2.5 1 2.5 2.5S13.5 16 12 16s-2.5-1-2.5-2.5S10.5 11 12 11z"/><path d="M12 2a10 10 0 0 0-10 10c0 2 .5 3.5 1.5 5"/><path d="M12 2a10 10 0 0 1 10 10c0 2-.5 3.5-1.5 5"/><path d="M7 20.5A9.96 9.96 0 0 0 12 22a9.96 9.96 0 0 0 5-1.5"/></svg>
                        Ingresar con huella / Face ID / PIN
                    </button>
                    <div id="webauthnMsg" class="small text-danger mt-2 d-none"></div>
                    <div class="d-flex align-items-center gap-2 my-3 text-muted small">
                        <hr class="flex-grow-1"><span>o ingresá con tu contraseña</span><hr class="flex-grow-1">
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('auth/login') ?>" novalidate>
                    <?= Csrf::field() ?>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="usuario@comercio.com"
                            autocomplete="email"
                            required
                            value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>"
                        >
                    </div>

                    <!-- Contraseña -->
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="btn btn-outline-secondary" id="btnTogglePwd" tabindex="-1">
                                <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="recordarme" id="recordarme" value="1">
                            <label class="form-check-label small" for="recordarme">Recordarme 30 días</label>
                        </div>
                        <a href="<?= url('auth/recuperar') ?>" class="small text-muted">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        Ingresar
                    </button>

                    <!-- Solo se muestra si el navegador detecta que la PWA todavía no está instalada -->
                    <button type="button" id="btnDescargarApp" class="btn btn-outline-primary w-100 py-2 fw-semibold mt-2 d-none">
                        📲 Descargar app
                    </button>
                </form>

            </div>
        </div>

        <p class="text-center text-muted small mt-4">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> · v<?= APP_VERSION ?>
        </p>
    </div>

    <script src="<?= asset('js/theme.bundle.js') ?>"></script>
    <script>
        document.getElementById('btnTogglePwd').addEventListener('click', function() {
            const pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
        });

        window.APP_BASE = '<?= rtrim(BASE_URL, '/') ?>';
    </script>
    <?php if ($webauthnHabilitado): ?>
    <script src="<?= asset('js/webauthn.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            DewocWebauthn.iniciarBotonLogin({
                boxEl: document.getElementById('webauthnBox'),
                btnEl: document.getElementById('btnLoginBiometrico'),
                msgEl: document.getElementById('webauthnMsg'),
            });
        });
    </script>
    <?php endif; ?>
    <script src="<?= url('sw-register.js') ?>"></script>
    <script>
        // Botón "Descargar app": el evento beforeinstallprompt solo lo dispara el
        // navegador cuando la PWA todavía NO está instalada (así "analiza" el estado).
        (function () {
            const btn = document.getElementById('btnDescargarApp');
            const yaInstalada = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            if (yaInstalada) return;

            let deferredPrompt = null;

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                btn.classList.remove('d-none');
            });

            btn.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                btn.disabled = true;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
                btn.classList.add('d-none');
                btn.disabled = false;
            });

            window.addEventListener('appinstalled', () => btn.classList.add('d-none'));
        })();
    </script>
</body>
</html>
