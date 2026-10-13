<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
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
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h1 class="h3 fw-bold mb-1">Recuperar contraseña</h1>
            <p class="text-muted">Portal de Clientes — ingresá tu email y te enviaremos un enlace de restablecimiento.</p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <?php if ($msg): ?>
                    <div class="alert alert-<?= e($msg['tipo']) ?> py-2 small"><?= e($msg['texto']) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= url('portal/recuperar') ?>" novalidate>
                    <?= Csrf::field() ?>

                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        Enviar enlace de restablecimiento
                    </button>
                </form>

            </div>
        </div>

        <p class="text-center mt-4">
            <a href="<?= url('portal/login') ?>" class="text-muted small">← Volver al inicio de sesión</a>
        </p>

        <p class="text-center text-muted small mt-2">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> · v<?= APP_VERSION ?>
        </p>
    </div>

    <script src="<?= asset('js/theme.bundle.js') ?>"></script>
</body>
</html>
