<?php

require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CobroClienteModel.php';
require_once BASE_PATH . '/app/Models/ClienteModel.php';

/**
 * Cobros de cuenta corriente a clientes — espejo simplificado de PagosProveedoresController.
 * Módulo de permiso propio ('cobros_clientes', decisión confirmada) separado de 'ventas_pos':
 * un vendedor puede necesitar cobrar en el POS sin que eso implique que vea el saldo/historial
 * de cuenta corriente de cada cliente — antes ambas cosas compartían el mismo permiso.
 */
class CobrosClientesController extends Controller
{
    private CobroClienteModel $model;
    private ClienteModel      $clienteModel;

    public function __construct()
    {
        $this->model        = new CobroClienteModel();
        $this->clienteModel = new ClienteModel();
    }

    public function index(): void
    {
        $this->requireCanView('cobros_clientes');

        $filtros = [
            'cliente_id' => (int) ($_GET['cliente_id'] ?? 0) ?: null,
            'desde'      => $_GET['desde']  ?? '',
            'hasta'      => $_GET['hasta']  ?? '',
            'estado'     => $_GET['estado'] ?? '',
        ];

        $cobros     = $this->model->listar($filtros);
        $cuentas    = $this->model->getCuentaCorriente();
        $deudaTotal = array_sum(array_column($cuentas, 'saldo'));

        $this->render('cobros-clientes.index', [
            'pageTitle'  => 'Cobros de Clientes',
            'breadcrumb' => ['Cobros de Clientes' => null],
            'cobros'     => $cobros,
            'cuentas'    => $cuentas,
            'clientes'   => $this->clienteModel->listar(['activo' => 1]),
            'filtros'    => $filtros,
            'deudaTotal' => $deudaTotal,
        ]);
    }

    /** Ficha de cuenta corriente de UN cliente: extracto cronológico con saldo corrido (Debe = ventas con saldo, Haber = cobros aplicados). Mismo patrón que PagosProveedoresController::cuentaCorriente(). */
    public function cuentaCorriente(): void
    {
        $this->requireCanView('cobros_clientes');

        $clienteId = (int) ($_GET['cliente_id'] ?? 0);
        $cliente   = $clienteId ? $this->clienteModel->findById($clienteId) : false;
        if (!$cliente) {
            http_response_code(404);
            echo 'Cliente no encontrado.';
            exit;
        }

        $historico = !empty($_GET['historico']);
        $extracto  = $this->model->getExtracto($clienteId, $historico);

        $this->render('cobros-clientes.cuenta-corriente', [
            'pageTitle'   => 'Cuenta Corriente — ' . $cliente['nombre'],
            'breadcrumb'  => ['Cobros de Clientes' => url('cobros-clientes'), 'Cuenta corriente' => null],
            'cliente'     => $cliente,
            'movimientos' => $extracto['movimientos'],
            'totalDebe'   => $extracto['totalDebe'],
            'totalHaber'  => $extracto['totalHaber'],
            'saldo'       => $extracto['saldo'],
            'historico'   => $historico,
        ]);
    }

    public function nuevo(): void
    {
        $this->requireCanView('cobros_clientes');

        $preselect         = (int) ($_GET['cliente_id'] ?? 0) ?: null;
        $ventasPendientes  = $preselect ? $this->model->getVentasPendientesByCliente($preselect) : [];

        $this->render('cobros-clientes.form', [
            'pageTitle'         => 'Registrar Cobro',
            'breadcrumb'        => ['Cobros de Clientes' => url('cobros-clientes'), 'Nuevo' => null],
            'clientes'          => $this->clienteModel->listar(['activo' => 1]),
            'ventasPendientes'  => $ventasPendientes,
            'preselect'         => $preselect,
        ]);
    }

    public function guardar(): void
    {
        $this->requireCanView('cobros_clientes');
        Csrf::check();

        $clienteId = (int) ($_POST['cliente_id'] ?? 0) ?: null;
        if (!$clienteId) {
            $this->json(['ok' => false, 'error' => 'Seleccioná un cliente.']);
        }

        $montoEfec  = $this->parseDecimal($_POST['monto_efectivo']      ?? '0');
        $montoDebit = $this->parseDecimal($_POST['monto_debito']        ?? '0');
        $montoTrans = $this->parseDecimal($_POST['monto_transferencia'] ?? '0');
        $montoMp    = $this->parseDecimal($_POST['monto_mercadopago']   ?? '0');
        $montoTotal = round($montoEfec + $montoDebit + $montoTrans + $montoMp, 2);

        if ($montoTotal <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto total debe ser mayor a cero.']);
        }

        $ventaIds     = $_POST['venta_ids']    ?? [];
        $ventaMontos  = $_POST['venta_montos'] ?? [];
        $ventasAplicadas = [];
        foreach ($ventaIds as $k => $vid) {
            $vid   = (int) $vid;
            $monto = $this->parseDecimal($ventaMontos[$k] ?? '0');
            if ($vid > 0 && $monto > 0) {
                $ventasAplicadas[] = ['venta_id' => $vid, 'monto_aplicado' => $monto];
            }
        }

        try {
            $id = $this->model->guardar([
                'cliente_id'          => $clienteId,
                'fecha'               => $_POST['fecha'] ?? date('Y-m-d'),
                'concepto'            => trim($_POST['concepto'] ?? '') ?: null,
                'monto_efectivo'      => $montoEfec,
                'monto_debito'        => $montoDebit,
                'monto_transferencia' => $montoTrans,
                'monto_mercadopago'   => $montoMp,
                'monto_total'         => $montoTotal,
                'notas'               => trim($_POST['notas'] ?? '') ?: null,
                'usuario_id'          => (int) Session::user()['id'],
            ], $ventasAplicadas);

            $this->json(['ok' => true, 'id' => $id, 'total' => $montoTotal]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ver(): void
    {
        $this->requireCanView('cobros_clientes');
        $id    = (int) ($_GET['id'] ?? 0);
        $cobro = $this->model->findById($id);
        if (!$cobro) {
            http_response_code(404);
            echo 'Cobro no encontrado.';
            exit;
        }

        $ventas        = $this->model->getVentasAplicadas($id);
        $montoAplicado = array_sum(array_column($ventas, 'monto_aplicado'));

        $this->render('cobros-clientes.ver', [
            'pageTitle'       => 'Cobro #' . $id,
            'breadcrumb'      => ['Cobros de Clientes' => url('cobros-clientes'), 'Detalle' => null],
            'cobro'           => $cobro,
            'cliente'         => $this->clienteModel->findById((int) $cobro['cliente_id']),
            'ventas'          => $ventas,
            'montoDisponible' => round((float) $cobro['monto_total'] - $montoAplicado, 2),
        ]);
    }

    public function anular(): void
    {
        $this->requireCanView('cobros_clientes');
        Csrf::check();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->model->anular($id);
            $this->json(['ok' => true, 'mensaje' => 'Cobro anulado — el saldo de las ventas aplicadas vuelve a quedar pendiente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** AJAX: ventas con saldo de cuenta corriente pendiente de un cliente */
    public function ventasPendientes(): void
    {
        $this->requireCanView('cobros_clientes');
        $clienteId = (int) ($_GET['cliente_id'] ?? 0);
        $this->json($clienteId ? $this->model->getVentasPendientesByCliente($clienteId) : []);
    }

    private function parseDecimal(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') return 0.0;
        if (str_contains($v, ',') && str_contains($v, '.')) {
            return (float) str_replace(['.', ','], ['', '.'], $v);
        }
        return (float) str_replace(',', '.', $v);
    }
}
