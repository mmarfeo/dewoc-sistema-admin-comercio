<?php
/**
 * Importa feriados desde un mirror de terceros de los datos oficiales de Argentina
 * (argentina.gob.ar/feriados no expone ninguna API pública — se revisó explícitamente,
 * ver docs/prediccion-ventas-compras.md). Filtra solo los tipos que usa Proyecciones
 * para ponderar (inamovible/trasladable/turistico — NO "día no laborable") e inserta en
 * la tabla `feriados` ya existente, respetando lo cargado a mano (ver
 * FeriadoModel::agregarDesdeImportacion()).
 *
 * OJO: no se pudo verificar en vivo desde este entorno (sin salida a internet) el nombre
 * ni los valores exactos del campo `tipo` que devuelve hoy esa API de terceros. Antes de
 * confiar en el filtro, correr esto una vez contra un año real, revisar en el log qué
 * valores de `tipo` llegaron realmente, y ajustar el mapeo de $tiposValidos si hace falta.
 *
 * Uso:
 *   php cron/feriados_importar.php [año]     (CLI, año opcional — default año que viene)
 *   /cron/feriados_importar.php?token=...&anio=2027   (HTTP, con CRON_SECRET)
 *
 * Sugerido: correr una vez al año, en diciembre, para importar el año siguiente.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/FeriadoModel.php';

$esCli = PHP_SAPI === 'cli';
if (!$esCli) {
    $token = $_GET['token'] ?? '';
    if (!CRON_SECRET || !hash_equals(CRON_SECRET, (string)$token)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function salida(string $msg): void
{
    echo $msg . "\n";
    logApp('[cron feriados_importar] ' . $msg);
}

$anio = $esCli
    ? (int)($argv[1] ?? (date('Y') + 1))
    : (int)($_GET['anio'] ?? (date('Y') + 1));

$tiposValidos = ['inamovible', 'trasladable', 'turistico'];

try {
    $url = "https://api.argentinadatos.com/v1/feriados/{$anio}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch) ?: null;
    curl_close($ch);

    if ($body === false || $error) {
        salida("Error de red consultando {$url}: " . ($error ?: 'sin respuesta'));
        exit(1);
    }
    if ($status < 200 || $status >= 300) {
        salida("Respuesta HTTP {$status} de {$url} — body: " . substr((string)$body, 0, 500));
        exit(1);
    }

    $feriados = json_decode($body, true);
    if (!is_array($feriados)) {
        salida("No se pudo parsear JSON de {$url} — body: " . substr((string)$body, 0, 500));
        exit(1);
    }

    $feriadoModel = new FeriadoModel();
    $insertados = $yaExistian = $descartados = 0;

    foreach ($feriados as $f) {
        $fecha  = $f['fecha']  ?? null;
        $nombre = $f['nombre'] ?? null;
        $tipo   = strtolower(trim((string)($f['tipo'] ?? '')));

        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !in_array($tipo, $tiposValidos, true)) {
            $descartados++;
            continue;
        }

        if ($feriadoModel->agregarDesdeImportacion($fecha, $nombre, $tipo)) {
            $insertados++;
        } else {
            $yaExistian++;
        }
    }

    salida(sprintf(
        'Año %d — %d feriados recibidos, %d filtrados (tipo no relevante o fecha inválida), %d nuevos insertados, %d ya existían (no se tocaron).',
        $anio, count($feriados), $descartados, $insertados, $yaExistian
    ));
} catch (\Throwable $e) {
    salida('Error inesperado: ' . $e->getMessage());
    exit(1);
}
