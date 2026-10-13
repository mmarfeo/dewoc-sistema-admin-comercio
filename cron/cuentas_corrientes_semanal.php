<?php
/**
 * Cron semanal: analiza las tres cuentas corrientes del sistema — lo que deben los
 * PROVEEDORES/gastos (PagoProveedorModel::getCuentaCorriente()), lo que deben los
 * CLIENTES (CobroClienteModel::getCuentaCorriente()) y lo que la empresa le debe a los
 * SOCIOS por aportes sin reembolsar (ReembolsoSocioModel::getDeudaPorSocio()) — y guarda
 * el resultado como foto fija en reportes_ia, igual que cron/reporte_semanal.php.
 *
 * A diferencia de reporte_semanal.php, el aviso (push + bandeja + Chat IA) SOLO se
 * dispara si hay algún saldo pendiente > 0 en cualquiera de las tres cuentas — si todo
 * está saldado no tiene sentido interrumpir a nadie. El snapshot en reportes_ia se
 * guarda siempre, haya o no saldos, para que Resumen Ejecutivo tenga "lo último" para
 * mostrar incluso cuando todo está al día.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, todos los lunes a la mañana (mismo
 * horario que reporte_semanal.php):
 *   - Si el hosting permite ejecutar PHP directo (recomendado):
 *       php /home/USUARIO/public_html/cron/cuentas_corrientes_semanal.php
 *   - Si el hosting solo permite disparar por URL:
 *       https://tudominio.com/cron/cuentas_corrientes_semanal.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/PagoProveedorModel.php';
require_once __DIR__ . '/../app/Models/CobroClienteModel.php';
require_once __DIR__ . '/../app/Models/ReembolsoSocioModel.php';
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
    logApp('[cron cuentas_corrientes_semanal] ' . $msg);
}

$cuentasProveedores = (new PagoProveedorModel())->getCuentaCorriente();
$cuentasClientes    = (new CobroClienteModel())->getCuentaCorriente();
$deudaSocios        = (new ReembolsoSocioModel())->getDeudaPorSocio();

$saldoProveedores = array_sum(array_column($cuentasProveedores, 'saldo'));
$saldoClientes    = array_sum(array_column($cuentasClientes, 'saldo'));
$saldoSocios      = array_sum(array_column($deudaSocios, 'saldo'));
$saldoTotal       = $saldoProveedores + $saldoClientes + $saldoSocios;
$haySaldos        = $saldoTotal > 0.009;

$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');

$columnas  = ['Métrica', 'Valor'];
$resultado = [
    ['Métrica' => 'Proveedores con saldo pendiente', 'Valor' => count($cuentasProveedores) . ' — $' . $fmt($saldoProveedores)],
    ['Métrica' => 'Clientes con saldo pendiente',     'Valor' => count($cuentasClientes)    . ' — $' . $fmt($saldoClientes)],
    ['Métrica' => 'Socios con saldo a devolver',       'Valor' => count(array_filter($deudaSocios, static fn($d) => $d['saldo'] > 0.009)) . ' — $' . $fmt($saldoSocios)],
    ['Métrica' => 'Saldo total pendiente',             'Valor' => '$' . $fmt($saldoTotal)],
];

$titulo = ($haySaldos ? '⚠️' : '✅') . ' Cuentas corrientes — semana del ' . date('d/m');
$body = sprintf(
    'Proveedores: $%s | Clientes: $%s | Socios: $%s | Total: $%s',
    $fmt($saldoProveedores),
    $fmt($saldoClientes),
    $fmt($saldoSocios),
    $fmt($saldoTotal)
);

$reporteModel = new ReporteIaModel();
$reporteId = $reporteModel->crear(
    null,
    $titulo,
    'Saldos pendientes de cuenta corriente al ' . date('d/m/Y'),
    'cuentas_corrientes',
    null,
    [],
    $columnas,
    $resultado,
    'cron'
);

if ($haySaldos) {
    NotificadorReportes::notificarReporteGenerado($reporteId, 'reportes_cuentas_corrientes', $titulo, $body);
    salida("Cuentas corrientes con saldo pendiente — $body — guardado como reportes_ia #$reporteId, avisado por permiso 'reportes_cuentas_corrientes'");
} else {
    salida("Sin saldos pendientes en ninguna cuenta corriente — guardado como reportes_ia #$reporteId (sin aviso, todo al día)");
}
