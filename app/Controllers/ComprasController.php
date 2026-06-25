<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class ComprasController extends Controller
{
    private FacturaCompraModel $model;
    private ProveedorModel     $provModel;

    public function __construct()
    {
        $this->model     = new FacturaCompraModel();
        $this->provModel = new ProveedorModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $filtros = [
            'proveedor_id' => (int)$this->param('proveedor_id'),
            'desde'        => $this->param('desde') ?: date('Y-m-01'),
            'hasta'        => $this->param('hasta') ?: date('Y-m-d'),
            'estado'       => $this->param('estado'),
        ];

        $this->render('compras.index', [
            'pageTitle'   => 'Facturas de Compra',
            'breadcrumb'  => ['Compras' => null, 'Facturas' => null],
            'facturas'    => $this->model->findAllConDetalle($filtros),
            'proveedores' => $this->provModel->findAllActivos(),
            'filtros'     => $filtros,
        ]);
    }

    public function nueva(): void
    {
        $this->requireAuth();

        $this->render('compras.form', [
            'pageTitle'   => 'Nueva factura de compra',
            'breadcrumb'  => ['Compras' => null, 'Facturas' => 'compras', 'Nueva' => null],
            'proveedores' => $this->provModel->findAllActivos(),
            'factura'     => null,
        ]);
    }

    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $errores = [];

        // Validar cabecera
        $proveedorId  = (int)$this->input('proveedor_id');
        $fecha        = $this->input('fecha') ?: date('Y-m-d');
        $tipoComprobante = $this->input('tipo_comprobante', 'factura_b');
        $nroComprobante  = $this->input('nro_comprobante', '');
        $condPago     = $this->input('condicion_pago', 'contado');
        $notas        = $this->input('notas', '');

        if (!$proveedorId) $errores[] = 'Seleccione un proveedor.';

        // Validar duplicado (mismo proveedor + número de comprobante)
        if ($proveedorId && $nroComprobante && $this->model->existeDuplicado($proveedorId, $nroComprobante)) {
            $errores[] = "Ya existe una factura del mismo proveedor con el número de comprobante \"{$nroComprobante}\".";
        }

        // Leer items del detalle enviados como JSON
        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) === 0) {
            $errores[] = 'Agregue al menos un ítem a la factura.';
        }

        if ($errores) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }

        // Calcular totales
        $subtotal   = 0.0;
        $totalIva   = 0.0;
        $detalleDB  = [];

        foreach ($items as $item) {
            $tipo     = $item['tipo_item'] ?? '';
            $cant     = (float)($item['cantidad'] ?? 0);
            $precio   = (float)($item['precio_unitario'] ?? 0);
            $ivaPct   = (float)($item['iva_porcentaje'] ?? 21);
            $sub      = round($cant * $precio, 4);
            $subIva   = round($sub * (1 + $ivaPct / 100), 4);

            if ($cant <= 0) { $errores[] = 'Cantidad inválida.'; continue; }

            $subtotal  += $sub;
            $totalIva  += $subIva - $sub;

            $detalleDB[] = [
                'tipo_item'        => $tipo,
                'materia_prima_id' => $tipo === 'materia_prima' ? (int)$item['item_id'] : null,
                'insumo_id'        => $tipo === 'insumo'        ? (int)$item['item_id'] : null,
                'producto_id'      => $tipo === 'producto'      ? (int)$item['item_id'] : null,
                'cantidad'         => $cant,
                'precio_unitario'  => $precio,
                'subtotal'         => $sub,
                'iva_porcentaje'   => $ivaPct,
                'subtotal_con_iva' => $subIva,
            ];
        }

        if ($errores) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }

        $totalConIva = round($subtotal + $totalIva, 2);

        $cabecera = [
            'proveedor_id'    => $proveedorId,
            'usuario_id'      => Session::user()['id'],
            'fecha'           => $fecha,
            'tipo_comprobante'=> $tipoComprobante,
            'nro_comprobante' => $nroComprobante ?: null,
            'condicion_pago'  => $condPago,
            'subtotal'        => round($subtotal, 2),
            'total_iva'       => round($totalIva, 2),
            'total_con_iva'   => $totalConIva,
            'estado'          => 'pagada',
            'notas'           => $notas ?: null,
            'eliminado'       => 0,
        ];

        try {
            $id = $this->model->guardar($cabecera, $detalleDB);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Factura guardada correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }
    }

    public function ver(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id');
        $factura = $this->model->findByIdConDetalle($id);
        if (!$factura) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('compras.ver', [
            'pageTitle'  => 'Factura #' . $id,
            'breadcrumb' => ['Compras' => null, 'Facturas' => 'compras', 'Ver' => null],
            'factura'    => $factura,
        ]);
    }

    public function anular(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        try {
            $ok = $this->model->anular($id, Session::user()['id']);
            $ok
                ? $this->json(['ok' => true, 'mensaje' => 'Factura anulada.'])
                : $this->json(['ok' => false, 'error' => 'No se pudo anular (ya anulada o no existe).'], 409);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Vista standalone para imprimir / exportar PDF */
    public function imprimir(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $factura = $this->model->findByIdConDetalle($id);
        if (!$factura) { http_response_code(404); echo '404 Not Found'; return; }

        $pageTitle = 'Factura #' . $id;
        require BASE_PATH . '/app/Views/compras/imprimir.php';
        exit;
    }

    /** AJAX: búsqueda de items (materias primas / insumos / productos) */
    public function buscarItems(): void
    {
        $this->requireAuth();
        $q    = $this->param('q', '');
        $tipo = $this->param('tipo', '');
        if (strlen($q) < 2) { $this->json([]); }
        $this->json($this->model->buscarItems($q, $tipo));
    }
}
