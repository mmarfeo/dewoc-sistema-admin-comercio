<?php
/**
 * Cron de control: compras (facturas_compra) y pagos (pagos_proveedores) de los últimos 4
 * días, fila a fila, con qué usuario los cargó — para poder auditar rápido quién registró
 * qué. Se guarda como foto fija en reportes_ia y avisa por push + bandeja + Chat IA a quien
 * tenga el permiso 'reportes_compras_pagos_usuario' (mismo mecanismo que reporte_semanal.php).
 *
 * OJO con la programación: un cron estándar no puede expresar "cada 4 días" de forma
 * perfectamente periódica — un paso de 4 en el campo de día del mes (ej. "0 8 4 (paso) * *"
 * en la sintaxis de hPanel) corre los días 1, 5, 9, 13, 17, 21, 25, 29 de cada mes, y al
 * cambiar de mes esa distancia puede quedar en 4, 5 u 8 días según cuántos días tenga el mes
 * anterior. Se aceptó ese margen de error (decisión confirmada) — el script en sí SIEMPRE
 * mira "los últimos 4 días" al momento de correr, sin importar cuántos días pasaron desde la
 * corrida anterior.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, sugerido cada 4 días:
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/reporte_compras_pagos_usuario.php
 *   - Por URL: https://tudominio.com/cron/reporte_compras_pagos_usuario.php?token=EL_CRON_SECRET_DEL_.env
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
    logApp('[cron reporte_compras_pagos_usuario] ' . $msg);
}

$desde = (new DateTime('-4 days'))->format('Y-m-d');
$hasta = (new DateTime())->format('Y-m-d');

$filas = (new ReportesModel())->comprasYPagosPorUsuario($desde, $hasta);

$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');

$columnas  = ['Usuario', 'Fecha', 'Tipo', 'Detalle', 'Monto', 'Forma de pago'];
$resultado = array_map(fn($f) => [
    'Usuario'       => $f['usuario'],
    'Fecha'         => $f['fecha'],
    'Tipo'          => $f['tipo'],
    'Detalle'       => $f['detalle'],
    'Monto'         => $fmt((float)$f['monto']),
    'Forma de pago' => $f['forma_pago'] ?: '—',
], $filas);

$totalMonto = array_sum(array_column($filas, 'monto'));
$titulo = '🧾 Compras y pagos por usuario ' . date('d/m', strtotime($desde)) . ' - ' . date('d/m', strtotime($hasta));
$body   = sprintf('%d registros por $%s en total.', count($filas), $fmt($totalMonto));

$reporteModel = new ReporteIaModel();
$reporteId = $reporteModel->crear(
    null,
    $titulo,
    "Del $desde al $hasta",
    'compras_pagos_usuario',
    null,
    ['desde' => $desde, 'hasta' => $hasta],
    $columnas,
    $resultado,
    'cron'
);

NotificadorReportes::notificarReporteGenerado($reporteId, 'reportes_compras_pagos_usuario', $titulo, $body);

salida("Compras y pagos $desde a $hasta — $body — guardado como reportes_ia #$reporteId, avisado por permiso 'reportes_compras_pagos_usuario'");
