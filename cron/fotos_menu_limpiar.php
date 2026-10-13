<?php
/**
 * Cron: borra del disco las fotos de producto descartadas con más de 30 días (ver
 * docs/integracion-menuapp-checklist.md, tarea 8.5).
 *
 * Al descartar una candidata NO se borra el archivo en el momento, a propósito: un descarte
 * por error se puede recuperar a mano desde la base durante esos 30 días. Pasado ese plazo
 * ya no aporta nada y solo ocupa disco, que en un hosting compartido es finito.
 *
 * Solo toca `estado_menu = 'descartada'`: una candidata viva, la oficial y la anterior
 * (la que hace posible "Volver a la foto anterior") nunca entran acá.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, una vez por día:
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/fotos_menu_limpiar.php
 *   - Por URL: https://tudominio.com/cron/fotos_menu_limpiar.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/FotoMenuModel.php';

// ── Seguridad: solo CLI, o HTTP con el token correcto ───────────────────────
// Mismo criterio que el resto de los crons. Con CRON_SECRET vacío (que ahora es el default,
// ver config/config.php) la vía HTTP queda cerrada y solo funciona por CLI.
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
    logApp('[cron fotos_menu_limpiar] ' . $msg);
}

$model = new FotoMenuModel();
$fotos = $model->descartadasParaLimpiar();

if (!$fotos) {
    salida('No hay fotos descartadas para limpiar.');
    exit;
}

$dir      = BASE_PATH . '/storage/galeria/';
$borradas = 0;
$faltantes = 0;

foreach ($fotos as $f) {
    $ruta = $dir . basename((string)$f['nombre_archivo']);

    if (is_file($ruta)) {
        if (@unlink($ruta)) {
            $borradas++;
        } else {
            salida("No se pudo borrar {$ruta} (¿permisos?).");
            continue;
        }
    } else {
        // El archivo ya no está: igual se marca la fila, para no volver a mirarla mañana.
        $faltantes++;
    }

    // Baja lógica de la fila: se conserva el registro (quién la subió, cuándo, si salió de
    // IA) para la auditoría, aunque el archivo ya no exista.
    $model->softDelete((int)$f['id']);
}

salida("Limpieza terminada: {$borradas} archivo(s) borrado(s), {$faltantes} ya no estaban en disco, "
    . count($fotos) . ' fila(s) marcada(s) como eliminadas.');
