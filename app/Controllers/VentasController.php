<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

class VentasController extends Controller
{
    private VentaModel $model;

    public function __construct()
    {
        $this->model = new VentaModel();
    }

    /** Listado de ventas */
    public function index(): void
    {
        $this->requireAuth();

        $filtros = [
            'desde'  => $this->param('desde') ?: date('Y-m-01'),
            'hasta'  => $this->param('hasta') ?: date('Y-m-d'),
            'estado' => $this->param('estado'),
        ];

        $this->render('ventas.index', [
            'pageTitle' => 'Ventas',
            'breadcrumb'=> ['Ventas' => null, 'Listado' => null],
            'ventas'    => $this->model->findAllConDetalle($filtros),
            'filtros'   => $filtros,
        ]);
    }

    /** POS (punto de venta) */
    public function pos(): void
    {
        $this->requireAuth();

        $usuario = Session::user();
        $turno   = $this->model->getTurnoAbierto((int)$usuario['id']);

        $this->render('ventas.pos', [
            'pageTitle' => 'Punto de Venta',
            'breadcrumb'=> ['Ventas' => 'ventas', 'POS' => null],
            'turno'     => $turno,
        ]);
    }

    /** POST: guardar venta desde el POS */
    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuario   = Session::user();
        $turno     = $this->model->getTurnoAbierto((int)$usuario['id']);

        $itemsJson  = $this->input('items_json', '[]');
        $items      = json_decode($itemsJson, true);
        $clienteId  = (int)$this->input('cliente_id') ?: null;
        $facturar   = (int)$this->input('facturar_afip', 0);
        $descGlobal = (float)$this->input('descuento_global', 0);
        $metodoPago = $this->input('metodo_pago', 'efectivo');
        $notasPos   = $this->input('notas', '') ?: null;
        $mesa       = trim($this->input('mesa', '')) ?: null;

        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'No hay productos en la venta.'], 422);
        }

        $subtotal = 0.0;
        $totalIva = 0.0;
        $detalle  = [];

        foreach ($items as $it) {
            $pid    = (int)($it['producto_id'] ?? 0);
            $cant   = (float)($it['cantidad'] ?? 0);
            $precio = (float)($it['precio_unitario'] ?? 0);
            $iva    = (float)($it['iva_porcentaje'] ?? 21);
            $desc   = (float)($it['descuento_pct'] ?? 0);

            if (!$pid || $cant <= 0) continue;

            $sub    = round($cant * $precio * (1 - $desc / 100), 4);
            $subIva = round($sub * (1 + $iva / 100), 4);

            $subtotal += $sub;
            $totalIva += $subIva - $sub;

            $detalle[] = [
                'producto_id'    => $pid,
                'cantidad'       => $cant,
                'precio_unitario'=> $precio,
                'descuento_pct'  => $desc,
                'subtotal'       => $sub,
                'iva_porcentaje' => $iva,
                'subtotal_con_iva'=> $subIva,
            ];
        }

        if (!count($detalle)) {
            $this->json(['ok' => false, 'error' => 'Items inválidos.'], 422);
        }

        // Aplicar descuento global
        if ($descGlobal > 0 && $descGlobal <= 100) {
            $factor    = 1 - $descGlobal / 100;
            $subtotal  = round($subtotal * $factor, 2);
            $totalIva  = round($totalIva * $factor, 2);
        }

        $totalConIva = round($subtotal + $totalIva, 2);

        $cabecera = [
            'usuario_id'     => (int)$usuario['id'],
            'turno_id'       => $turno ? (int)$turno['id'] : null,
            'cliente_id'     => $clienteId,
            'subtotal'       => round($subtotal, 2),
            'total_iva'      => round($totalIva, 2),
            'total_con_iva'  => $totalConIva,
            'descuento_global'=> $descGlobal,
            'metodo_pago'    => $metodoPago,
            'facturar_afip'  => $facturar,
            'estado'         => 'completada',
            'mesa'           => $mesa,
            'notas'          => $notasPos,
            'eliminado'      => 0,
        ];

        try {
            $id    = $this->model->registrar($cabecera, $detalle);
            $venta = $this->model->findByIdConDetalle($id);

            // AFIP: solicitar CAE si está configurado y se pidió facturar
            $afipData = null;
            if ($facturar && defined('AFIP_CUIT') && AFIP_CUIT) {
                try {
                    require_once BASE_PATH . '/core/AfipWsaa.php';
                    require_once BASE_PATH . '/core/AfipWsfe.php';
                    $afipData = AfipWsfe::solicitarCae($venta, $detalle);
                    if ($afipData['ok']) {
                        $this->model->updateAfip($id, $afipData);
                    }
                } catch (\Throwable $afipEx) {
                    $afipData = ['ok' => false, 'error' => $afipEx->getMessage()];
                    logApp('AFIP error en venta #' . $id . ': ' . $afipEx->getMessage(), 'ERROR');
                }
            }

            $this->json([
                'ok'      => true,
                'id'      => $id,
                'mensaje' => 'Venta registrada correctamente.',
                'ticket_url'       => url("ventas/ticket?id=$id"),
                'comanda_url'      => url("ventas/comanda?id=$id"),
                'total'            => (float)($venta['total_con_iva'] ?? 0),
                'cliente_telefono' => $venta['cliente_telefono'] ?? '',
                'afip'             => $afipData,
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Ver detalle de una venta */
    public function ver(): void
    {
        $this->requireAuth();
        $id    = (int)$this->param('id');
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('ventas.ver', [
            'pageTitle'  => 'Venta #' . $id,
            'breadcrumb' => ['Ventas' => 'ventas', 'Ver' => null],
            'venta'      => $venta,
        ]);
    }

    /** Anular venta */
    public function anular(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        try {
            $ok = $this->model->anular($id);
            if ($ok) {
                AuditLog::log('anular', 'ventas', "Venta #{$id} anulada");
                $this->json(['ok' => true, 'mensaje' => 'Venta anulada.']);
            } else {
                $this->json(['ok' => false, 'error' => 'No se pudo anular.'], 409);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Ticket para imprimir (80mm) */
    public function ticket(): void
    {
        $this->requireAuth();
        $id    = (int)$this->param('id');
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta) { http_response_code(404); echo '404 Not Found'; return; }

        // Vista minimal sin layout para imprimir
        $this->view('ventas.ticket', ['venta' => $venta]);
    }

    /** Comanda de cocina */
    public function comanda(): void
    {
        $this->requireAuth();
        $id    = (int)$this->param('id');
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta) { http_response_code(404); echo '404 Not Found'; return; }

        $this->view('ventas.comanda', ['venta' => $venta]);
    }

    /** AJAX: búsqueda de productos para el POS */
    public function buscarProductos(): void
    {
        $this->requireAuth();
        $q = $this->param('q', '');
        if (strlen($q) < 1) { $this->json([]); }
        $this->json($this->model->buscarProductos($q));
    }
}
