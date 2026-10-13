<?php
/**
 * Cron semanal: qué se vendió la semana anterior (lunes a domingo), a qué costo, y cuánta
 * ganancia generó cada producto. Se guarda como foto fija en reportes_ia y avisa por push +
 * bandeja + Chat IA a quien tenga el permiso 'reportes_margen_producto' (mismo mecanismo que
 * reporte_semanal.php — mismo criterio de semana que ese cron, para que ambos reportes de la
 * semana queden alineados al mismo período).
 *
 * OJO: el costo usado es el vigente HOY (`productos.precio_costo` + desvío), aplicado
 * retroactivamente a lo vendido en toda la semana — no hay costo guardado al momento de cada
 * venta, así que es una aproximación, no un margen histórico real (ver
 * ReportesModel::margenPorProducto()).
 *
 * Programar en hPanel > Avanzado > Cron Jobs, todos los lunes a la mañana (mismo horario que
 * reporte_semanal.php):
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/reporte_margen_producto.php
 *   - Por URL: https://tudominio.com/cron/reporte_margen_producto.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/ReportesModel.php';
require_once __DIR__ . '/../app/Models/ReporteIaModel.php';
require_once __DIR__ . '/../core/NotificadorReportes.php';

// ── Seguridad: solo CLI, o HTTP con el token correcto ───────────────────────
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
    logApp('[cron reporte_margen_producto] ' . $msg);
}

$desde = (new DateTime('monday last week'))->format('Y-m-d');
$hasta = (new DateTime('sunday last week'))->format('Y-m-d');

$productos = (new ReportesModel())->margenPorProducto($desde, $hasta);

$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');

$columnas  = ['Producto', 'Cantidad vendida', 'Total vendido', 'Costo total', 'Ganancia', 'Margen %'];
$resultado = array_map(fn($p) => [
    'Producto'         => $p['nombre'],
    'Cantidad vendida' => number_format($p['cantidad_vendida'], 2, ',', '.'),
    'Total vendido'    => $fmt($p['total_vendido']),
    'Costo total'      => $fmt($p['costo_total']),
    'Ganancia'         => $fmt($p['ganancia']),
    'Margen %'         => $p['margen_pct'] . '%',
], $productos);

$gananciaTotal = array_sum(array_column($productos, 'ganancia'));
$masRentable   = $productos[0]['nombre'] ?? null;

$titulo = '💹 Margen por producto ' . date('d/m', strtotime($desde)) . ' - ' . date('d/m', strtotime($hasta));
$body   = $masRentable
    ? sprintf('Ganancia total: $%s. Más rentable: %s.', $fmt($gananciaTotal), $masRentable)
    : sprintf('Ganancia total: $%s.', $fmt($gananciaTotal));

$reporteModel = new ReporteIaModel();
$reporteId = $reporteModel->crear(
    null,
    $titulo,
    "Semana del $desde al $hasta",
    'margen_producto',
    null,
    ['desde' => $desde, 'hasta' => $hasta],
    $columnas,
    $resultado,
    'cron'
);

NotificadorReportes::notificarReporteGenerado($reporteId, 'reportes_margen_producto', $titulo, $body);

salida("Margen por producto $desde a $hasta — $body — guardado como reportes_ia #$reporteId, avisado por permiso 'reportes_margen_producto'");
