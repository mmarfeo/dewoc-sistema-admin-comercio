<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Portal de Clientes') ?> — <?= APP_NAME ?></title>
    <link rel="icon" href="<?= asset('favicon/favicon.ico') ?>" sizes="any">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/theme.bundle.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">

    <div class="w-100" style="max-width: 420px; padding: 1rem;">

        <div class="text-center mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--bs-primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-3">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <h1 class="h3 fw-bold mb-1">Portal de Clientes</h1>
            <p class="text-muted"><?= APP_NAME ?></p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= url('portal/login') ?>" novalidate>
                    <?= Csrf::field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                               autocomplete="email" required
                               value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control"
                               autocomplete="current-password" required>
                    </div>

                    <div class="mb-4 text-end">
                        <a href="<?= url('portal/recuperar') ?>" class="small text-muted">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Ingresar</button>
                </form>

            </div>
        </div>

        <p class="text-center text-muted small mt-4">
            ¿Problemas para entrar? Consultá con quien te dio de alta como cliente.
        </p>
    </div>

    <script src="<?= asset('js/theme.bundle.js') ?>"></script>
</body>
</html>
