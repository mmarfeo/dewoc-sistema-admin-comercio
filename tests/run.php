<?php
/**
 * Test runner — Dewoc
 * CLI:     php tests/run.php
 * Browser: http://localhost/01-Dewoc-Sist-Admin-Comercio/tests/run.php
 *
 * Protegido: solo accesible desde localhost
 */
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Acceso denegado.');
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/core/helpers.php';
require_once __DIR__ . '/TestCase.php';

$isCli     = PHP_SAPI === 'cli';
$suites    = [];
$totalPass = 0;
$totalFail = 0;
$startTime = microtime(true);

// Cargar y ejecutar todos los tests
$testFiles = array_merge(
    glob(__DIR__ . '/security/*.php'),
    glob(__DIR__ . '/unit/*.php'),
    glob(__DIR__ . '/integration/*.php'),
);

foreach ($testFiles as $file) {
    require_once $file;
    $className = basename($file, '.php');
    if (class_exists($className)) {
        try {
            $test = new $className();
            $test->run();
            $result      = $test->getResults();
            $suites[]    = $result;
            $totalPass  += $result['passed'];
            $totalFail  += $result['failed'];
        } catch (PDOException $e) {
            $suites[] = [
                'suite'   => $className,
                'passed'  => 0,
                'failed'  => 1,
                'results' => [['fail', 'BD no disponible — suite omitida: ' . $e->getMessage(), '']],
            ];
            $totalFail++;
        }
    }
}

$elapsed = round(microtime(true) - $startTime, 3);

// ── Output ────────────────────────────────────────────────────
if ($isCli) {
    // Terminal output
    $c = [
        'reset'  => "\033[0m",
        'green'  => "\033[32m",
        'red'    => "\033[31m",
        'yellow' => "\033[33m",
        'bold'   => "\033[1m",
        'cyan'   => "\033[36m",
    ];

    echo "\n{$c['bold']}{$c['cyan']} Dewoc — Test Runner{$c['reset']}\n";
    echo str_repeat('─', 60) . "\n\n";

    foreach ($suites as $suite) {
        $status = $suite['failed'] === 0 ? "{$c['green']}PASS{$c['reset']}" : "{$c['red']}FAIL{$c['reset']}";
        $total  = $suite['passed'] + $suite['failed'];
        echo "{$c['bold']}{$suite['suite']}{$c['reset']} [{$status}] {$suite['passed']}/{$total}\n";

        foreach ($suite['results'] as [$type, $msg, $detail]) {
            $icon = $type === 'pass' ? "{$c['green']}✓{$c['reset']}" : "{$c['red']}✗{$c['reset']}";
            echo "  {$icon} {$msg}\n";
            if ($detail) echo "    {$c['yellow']}→ {$detail}{$c['reset']}\n";
        }
        echo "\n";
    }

    $color = $totalFail === 0 ? $c['green'] : $c['red'];
    echo str_repeat('─', 60) . "\n";
    echo "{$color}{$c['bold']}Resultado: {$totalPass} pasaron, {$totalFail} fallaron{$c['reset']} ({$elapsed}s)\n\n";
    exit($totalFail > 0 ? 1 : 0);

} else {
    // HTML output
    $statusColor = $totalFail === 0 ? '#198754' : '#dc3545';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tests — Dewoc</title>
    <link rel="stylesheet" href="/01-Dewoc-Sist-Admin-Comercio/assets/css/theme.bundle.css">
    <style>
        body { background: #f8f9fa; }
        .pass { color: #198754; }
        .fail { color: #dc3545; }
        .detail { color: #6c757d; font-size: .82rem; padding-left: 1.2rem; font-family: monospace; }
        .suite-header { background: #e9ecef; padding: .5rem 1rem; border-radius: .4rem; margin-bottom: .5rem; }
        pre { margin: 0; }
    </style>
</head>
<body class="p-4">
<div class="container" style="max-width:860px">
    <h1 class="h3 fw-bold mb-1">Dewoc — Test Runner</h1>
    <p class="text-muted mb-4">Tiempo de ejecución: <?= $elapsed ?>s</p>

    <!-- Resumen -->
    <div class="alert mb-4" style="background:<?= $statusColor ?>22; border-color:<?= $statusColor ?>; color:<?= $statusColor ?>">
        <strong><?= $totalFail === 0 ? '✅ Todos los tests pasaron' : "❌ {$totalFail} test(s) fallaron" ?></strong>
        &nbsp;|&nbsp; <?= $totalPass ?> pasados &nbsp;·&nbsp; <?= $totalFail ?> fallidos &nbsp;·&nbsp; <?= $totalPass + $totalFail ?> total
    </div>

    <?php foreach ($suites as $suite): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header suite-header d-flex justify-content-between">
            <strong><?= htmlspecialchars($suite['suite']) ?></strong>
            <span>
                <span class="pass"><?= $suite['passed'] ?> ✓</span>
                <?php if ($suite['failed']): ?><span class="fail ms-2"><?= $suite['failed'] ?> ✗</span><?php endif; ?>
            </span>
        </div>
        <div class="card-body p-3">
            <?php foreach ($suite['results'] as [$type, $msg, $detail]): ?>
                <div class="<?= $type === 'pass' ? 'pass' : 'fail' ?> mb-1">
                    <?= $type === 'pass' ? '✓' : '✗' ?> <?= htmlspecialchars($msg) ?>
                    <?php if ($detail): ?>
                        <div class="detail">→ <?= htmlspecialchars($detail) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="text-muted small mt-3">Este runner es solo accesible desde localhost. Eliminarlo en producción.</p>
</div>
</body>
</html>
<?php
}
