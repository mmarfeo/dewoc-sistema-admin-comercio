<?php
/**
 * Script de instalación — Ejecutar UNA SOLA VEZ desde el navegador:
 * http://localhost/01-Dewoc-Sist-Admin-Comercio/install.php
 * ELIMINAR este archivo luego de instalar.
 */

// Protección básica: sólo desde localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Acceso denegado.');
}

require_once __DIR__ . '/config/config.php';

$errors = [];
$steps  = [];

try {
    // 1. Conexión sin base de datos seleccionada
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $steps[] = ['ok', 'Conexión a MySQL exitosa'];

    // 2. Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $steps[] = ['ok', 'Base de datos "' . DB_NAME . '" creada/verificada'];

    $pdo->exec("USE `" . DB_NAME . "`");

    // 3. Ejecutar schema
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    // Dividir por ; y ejecutar statement a statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $sql) {
        if (!empty($sql) && !str_starts_with(ltrim($sql), '--')) {
            $pdo->exec($sql);
        }
    }
    $steps[] = ['ok', 'Tablas creadas correctamente'];

    // 4. Ejecutar seed
    $seed = file_get_contents(__DIR__ . '/database/seed.sql');
    $stmts = array_filter(array_map('trim', explode(';', $seed)));
    foreach ($stmts as $sql) {
        if (!empty($sql) && !str_starts_with(ltrim($sql), '--')) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* ignorar duplicados */ }
        }
    }
    $steps[] = ['ok', 'Datos iniciales insertados'];

    // 5. Crear directorios de uploads
    foreach (['uploads/productos', 'uploads/usuarios', 'storage/logs'] as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            $steps[] = ['ok', "Directorio creado: {$dir}"];
        } else {
            $steps[] = ['info', "Directorio ya existe: {$dir}"];
        }
    }

    // 6. Generar iconos PWA placeholder si no existen
    $iconPaths = [
        __DIR__ . '/assets/images/pwa-icon-192.png',
        __DIR__ . '/assets/images/pwa-icon-512.png',
    ];
    foreach ($iconPaths as $iconPath) {
        if (!file_exists($iconPath)) {
            // Crear un PNG simple de 1x1 como placeholder
            $im = imagecreatetruecolor(str_contains($iconPath, '192') ? 192 : 512, str_contains($iconPath, '192') ? 192 : 512);
            $bg = imagecolorallocate($im, 13, 110, 253);
            imagefill($im, 0, 0, $bg);
            imagepng($im, $iconPath);
            imagedestroy($im);
            $steps[] = ['ok', 'Ícono PWA generado: ' . basename($iconPath)];
        }
    }

    $steps[] = ['success', '¡Instalación completada! Ya podés ingresar en: <a href="' . BASE_URL . '/auth/login">Ir al sistema</a>'];
    $steps[] = ['warning', 'IMPORTANTE: Eliminá este archivo (install.php) del servidor por seguridad.'];

} catch (PDOException $e) {
    $errors[] = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalación — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme.bundle.css">
</head>
<body class="bg-light d-flex justify-content-center align-items-start pt-5">
<div class="w-100" style="max-width:680px; padding:1rem;">
    <div class="text-center mb-4">
        <h1 class="h3 fw-bold">Instalación del Sistema</h1>
        <p class="text-muted"><?= APP_NAME ?> · v<?= APP_VERSION ?></p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Error durante la instalación:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as [$type, $msg]): ?>
                    <?php
                    $icon = match($type) {
                        'ok'      => '✅',
                        'info'    => 'ℹ️',
                        'success' => '🎉',
                        'warning' => '⚠️',
                        default   => '•',
                    };
                    $cls = match($type) {
                        'success' => 'fw-bold text-success',
                        'warning' => 'text-warning',
                        'ok'      => '',
                        default   => 'text-muted',
                    };
                    ?>
                    <li class="list-group-item border-0 py-2">
                        <?= $icon ?> <span class="<?= $cls ?>"><?= $msg ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="mt-4 text-center">
        <p class="text-muted small"><strong>Credenciales por defecto:</strong> admin@dewoc.com / admin1234</p>
        <a href="<?= BASE_URL ?>/auth/login" class="btn btn-primary">Ir al sistema →</a>
    </div>
</div>
</body>
</html>
