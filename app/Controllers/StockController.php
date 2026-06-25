<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/StockModel.php';

class StockController extends Controller
{
    private StockModel $model;

    public function __construct()
    {
        $this->model = new StockModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('stock.index', [
            'pageTitle'   => 'Control de Stock',
            'breadcrumb'  => ['Stock' => null, 'Estado actual' => null],
            'items'       => $this->model->resumenStock(),
            'bajoMinimo'  => $this->model->itemsBajoMinimo(),
        ]);
    }

    public function ajuste(): void
    {
        $this->requireAuth();
        Csrf::check();

        $tipoItem = $this->input('tipo_item', '');
        $itemId   = (int)$this->input('item_id', 0);
        $cantidad = (float)$this->input('cantidad', 0);
        $motivo   = $this->input('motivo', '') ?: null;

        if (!in_array($tipoItem, ['producto','materia_prima','insumo'], true)) {
            $this->json(['ok' => false, 'error' => 'Tipo de ítem inválido.'], 422);
        }
        if (!$itemId) {
            $this->json(['ok' => false, 'error' => 'ID de ítem inválido.'], 422);
        }
        if ($cantidad == 0) {
            $this->json(['ok' => false, 'error' => 'La cantidad no puede ser cero.'], 422);
        }

        try {
            $this->model->ajustar([
                'tipo_item'  => $tipoItem,
                'item_id'    => $itemId,
                'cantidad'   => $cantidad,
                'motivo'     => $motivo,
                'usuario_id' => (int)Session::user()['id'],
            ]);
            $this->json(['ok' => true, 'mensaje' => 'Ajuste de stock registrado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function reporte(): void
    {
        $this->requireAuth();

        $desde  = $this->param('desde') ?: date('Y-m-01');
        $hasta  = $this->param('hasta') ?: date('Y-m-d');

        $stockTeorico = $this->model->stockTeoricoVsFisico($desde, $hasta);

        $this->render('stock.reporte', [
            'pageTitle'    => 'Reporte Comparativo de Stock',
            'breadcrumb'   => ['Stock' => 'stock', 'Reporte comparativo' => null],
            'reporte'      => $this->model->reporteComparativo($desde, $hasta),
            'stockTeorico' => $stockTeorico,
            'desde'        => $desde,
            'hasta'        => $hasta,
        ]);
    }

    public function inventario(): void
    {
        $this->requireAuth();
        $this->render('stock.inventario', [
            'pageTitle'  => 'Inventario físico',
            'breadcrumb' => ['Stock' => 'stock', 'Inventario' => null],
            'items'      => $this->model->resumenStock(),
        ]);
    }

    public function guardarInventario(): void
    {
        $this->requireAuth();
        Csrf::check();
        $this->procesarInventario('Inventario físico');
    }

    /** Flujo de apertura: carga stock inicial para ítems en cero */
    public function apertura(): void
    {
        $this->requireAuth();
        $todosItems = $this->model->resumenStock();
        $sinStock   = array_filter($todosItems, fn($i) => (float)$i['stock_actual'] <= 0);

        $this->render('stock.apertura', [
            'pageTitle'  => 'Inventario inicial de apertura',
            'breadcrumb' => ['Stock' => 'stock', 'Apertura' => null],
            'items'      => array_values($sinStock),
            'total'      => count($todosItems),
        ]);
    }

    public function guardarApertura(): void
    {
        $this->requireAuth();
        Csrf::check();
        $this->procesarInventario('Inventario inicial de apertura');
    }

    private function procesarInventario(string $motivo): void
    {
        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'No hay datos de inventario.'], 422);
        }

        $usuarioId = (int)Session::user()['id'];
        $ajustados = 0;

        try {
            foreach ($items as $it) {
                $tipoItem = $it['tipo_item'] ?? '';
                $itemId   = (int)($it['item_id'] ?? 0);
                $fisico   = (float)($it['stock_fisico'] ?? 0);
                $teorico  = (float)($it['stock_teorico'] ?? 0);
                $diff     = $fisico - $teorico;

                if (!$itemId || !in_array($tipoItem, ['producto','materia_prima','insumo'])) continue;
                if (abs($diff) < 0.0001) continue;

                $this->model->ajustar([
                    'tipo_item'  => $tipoItem,
                    'item_id'    => $itemId,
                    'cantidad'   => $diff,
                    'motivo'     => $motivo,
                    'usuario_id' => $usuarioId,
                ]);
                $ajustados++;
            }
            $this->json(['ok' => true, 'ajustados' => $ajustados, 'mensaje' => "$ajustados ítems ajustados."]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** AJAX: historial de movimientos de stock de un ítem para gráfico */
    public function historialItem(): void
    {
        $this->requireAuth();
        $tipo  = $this->param('tipo', '');
        $id    = (int)$this->param('id', 0);
        $desde = $this->param('desde') ?: date('Y-m-01');
        $hasta = $this->param('hasta') ?: date('Y-m-d');

        if (!$id || !in_array($tipo, ['producto','materia_prima','insumo'])) {
            $this->json(['ok' => false, 'data' => []]);
        }

        $this->json([
            'ok'   => true,
            'data' => $this->model->historialItem($tipo, $id, $desde, $hasta),
        ]);
    }
}
