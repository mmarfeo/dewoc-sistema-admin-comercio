<?php
/**
 * Cron semanal: calcula el resumen de la semana anterior (lunes a domingo) —
 * compras (pagadas/pendientes), ventas por método de pago y margen bruto —,
 * lo guarda como foto fija en reportes_ia (antes se recalculaba en vivo cada
 * vez que se abría el link, ver docs/tareas-pendientes.md #3) y avisa por
 * push + bandeja de notificaciones + Chat IA a quien tenga permiso de verlo.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, todos los lunes a la mañana:
 *   - Si el hosting permite ejecutar PHP directo (recomendado):
 *       php /home/USUARIO/public_html/cron/reporte_semanal.php
 *   - Si el hosting solo permite disparar por URL:
 *       https://tudominio.com/cron/reporte_semanal.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/ReporteSemanalModel.php';
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
    logApp('[cron reporte_semanal] ' . $msg);
}

$desde = (new DateTime('monday last week'))->format('Y-m-d');
$hasta = (new DateTime('sunday last week'))->format('Y-m-d');

$modelo  = new ReporteSemanalModel();
$resumen = $modelo->generarResumen($desde, $hasta);

$c = $resumen['compras'];
$v = $resumen['ventas'];
$p = $resumen['pagos'];
$m = $resumen['margen'];

$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');

$body = sprintf(
    "Ventas: \$%s (%d) | Compras: \$%s (\$%s pend.) | Pagos: \$%s (%d) | Margen: \$%s (%s%%)",
    $fmt($v['total']),
    $v['cantidad'],
    $fmt($c['total']),
    $fmt($c['pendientes']['total']),
    $fmt($p['total']),
    $p['cantidad'],
    $fmt($m['margen']),
    $m['margen_pct']
);

$titulo = '📊 Resumen semanal ' . date('d/m', strtotime($desde)) . ' - ' . date('d/m', strtotime($hasta));

// Foto fija: se persiste en reportes_ia (reutilizada, con origen='cron' y usuario_id=null
// — no hay un autor humano) en vez de quedar solo en el cuerpo del push. Se aplana a
// filas Métrica/Valor para que la pantalla de detalle y el export a Excel de Reportes IA
// funcionen sin ningún cambio — son genéricos, no saben que este reporte no vino del chat.
// OJO: reportes-ia/ver.php lee cada fila por $fila[$col], o sea que $resultado tiene que
// ser un array ASOCIATIVO por nombre de columna, no indexado.
$columnas  = ['Métrica', 'Valor'];
$resultado = [
    ['Métrica' => 'Ventas',             'Valor' => $fmt($v['total'])],
    ['Métrica' => 'Cantidad de ventas', 'Valor' => $v['cantidad']],
    ['Métrica' => 'Compras',            'Valor' => $fmt($c['total'])],
    ['Métrica' => 'Compras pagadas',    'Valor' => $fmt($c['pagadas']['total'])],
    ['Métrica' => 'Compras pendientes', 'Valor' => $fmt($c['pendientes']['total'])],
    ['Métrica' => 'Pagos (cash real)',  'Valor' => $fmt($p['total'])],
    ['Métrica' => 'Cantidad de pagos',  'Valor' => $p['cantidad']],
    ['Métrica' => 'Margen bruto',       'Valor' => $fmt($m['margen'])],
    ['Métrica' => 'Margen %',           'Valor' => $m['margen_pct'] . '%'],
];

$reporteModel = new ReporteIaModel();
$reporteId = $reporteModel->crear(
    null,
    $titulo,
    "Semana del $desde al $hasta",
    'semanal',
    null,
    ['desde' => $desde, 'hasta' => $hasta],
    $columnas,
    $resultado,
    'cron'
);

NotificadorReportes::notificarReporteGenerado($reporteId, 'reportes_semanal', $titulo, $body);

salida("Reporte semanal $desde a $hasta — $body — guardado como reportes_ia #$reporteId, avisado por permiso 'reportes_semanal'");
