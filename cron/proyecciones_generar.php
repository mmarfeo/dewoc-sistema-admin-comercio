<?php
/**
 * Cron de Proyecciones (Fase 1 — ver docs/prediccion-ventas-compras.md): genera la
 * foto del día — ventas proyectadas por producto, consumo/compra proyectada de materia
 * prima e insumos, y tendencia de margen por producto — y la persiste en `proyecciones`.
 * No recalcula en vivo: la pantalla /proyecciones solo lee la última corrida guardada acá.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, todos los días (el horizonte es de días):
 *   - Si el hosting permite ejecutar PHP directo (recomendado):
 *       php /home/USUARIO/public_html/cron/proyecciones_generar.php
 *   - Si el hosting solo permite disparar por URL:
 *       https://tudominio.com/cron/proyecciones_generar.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/ProyeccionModel.php';
require_once __DIR__ . '/../app/Models/ProductoModel.php';
require_once __DIR__ . '/../app/Models/RecetaModel.php';
require_once __DIR__ . '/../app/Models/FeriadoModel.php';

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
    logApp('[cron proyecciones_generar] ' . $msg);
}

$proyeccionModel = new ProyeccionModel();
$productoModel   = new ProductoModel();
$recetaModel     = new RecetaModel();
$feriadoModel    = new FeriadoModel();

$horizonteDias = (int)$proyeccionModel->obtenerConfig()['horizonte_dias'];

// ── (a) Ventas proyectadas por producto ──────────────────────────────────────
$productosActivos = array_filter(
    $productoModel->findAllConDetalle(),
    fn($p) => (int)$p['activo'] === 1
);

$totalesPorProducto = [];
$productosProyectados = 0;
foreach ($productosActivos as $producto) {
    $productoId = (int)$producto['id'];
    $serie = $proyeccionModel->proyectarVentasProducto($productoId, $horizonteDias, $feriadoModel);

    $total = 0.0;
    foreach ($serie as $fecha => $cantidad) {
        $proyeccionModel->guardarProyeccion('venta', 'producto', $productoId, $fecha, $fecha, $cantidad, 'promedio_movil_ponderado');
        $total += $cantidad;
    }
    if ($total > 0.0) $productosProyectados++;
    $totalesPorProducto[$productoId] = $total;
}

// ── (b) Consumo/compra proyectada de materia prima ───────────────────────────
$consumo = $proyeccionModel->proyectarConsumoMateriaPrima($totalesPorProducto, $recetaModel);

$fechaDesdeHorizonte = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$fechaHastaHorizonte = (new DateTimeImmutable("+{$horizonteDias} days"))->format('Y-m-d');
$itemsAReponer = 0;
foreach ($consumo as $item) {
    $proyeccionModel->guardarProyeccion(
        'compra_mp',
        $item['entidad_tipo'],
        $item['entidad_id'],
        $fechaDesdeHorizonte,
        $fechaHastaHorizonte,
        $item['necesario_horizonte'],
        'promedio_movil_ponderado'
    );
    if ($item['stock_actual'] < $item['necesario_horizonte']) $itemsAReponer++;
}

// ── (c) Tendencia de margen por producto ─────────────────────────────────────
$proximoMesInicio = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
$proximoMesFin     = (new DateTimeImmutable('last day of next month'))->format('Y-m-d');
$productosConMargen = 0;
foreach ($productosActivos as $producto) {
    $productoId = (int)$producto['id'];
    $tendencia  = $proyeccionModel->tendenciaMargenProducto($productoId, (float)($producto['costo_mp'] ?? 0));
    if ($tendencia['proyeccion'] === null) continue;

    $proyeccionModel->guardarProyeccion(
        'margen',
        'producto',
        $productoId,
        $proximoMesInicio,
        $proximoMesFin,
        $tendencia['proyeccion'],
        'regresion_lineal'
    );
    $productosConMargen++;
}

salida(sprintf(
    'Horizonte %d días — %d/%d productos con ventas proyectadas, %d ítems de materia prima/insumo evaluados (%d a reponer), %d productos con tendencia de margen calculable.',
    $horizonteDias,
    $productosProyectados,
    count($productosActivos),
    count($consumo),
    $itemsAReponer,
    $productosConMargen
));
