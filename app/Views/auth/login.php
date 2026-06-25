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
    </script>
</body>
</html>
