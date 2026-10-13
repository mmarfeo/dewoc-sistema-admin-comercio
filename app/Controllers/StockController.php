<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/StockModel.php';
require_once BASE_PATH . '/app/Models/DesvioModel.php';
require_once BASE_PATH . '/app/Models/SolicitudModel.php';

class StockController extends Controller
{
    private StockModel  $model;
    private DesvioModel $desvioModel;

    public function __construct()
    {
        $this->model       = new StockModel();
        $this->desvioModel = new DesvioModel();
    }

    public function index(): void
    {
        $this->requireCanView('stock');
        $this->render('stock.index', [
            'pageTitle'   => 'Control de Stock',
            'breadcrumb'  => ['Stock' => null, 'Estado actual' => null],
            'items'       => $this->model->resumenStock(),
            'bajoMinimo'  => $this->model->itemsBajoMinimo(),
            // Sin este permiso, el usuario no ve el costo unitario de cada ítem (info
            // sensible: revela márgenes) — mismo criterio que productos_ver_costos.
            'verCostos'   => canView('stock_ver_costos'),
        ]);
    }

    public function ajuste(): void
    {
        $this->requireCanEdit('stock');
        Csrf::check();

        $tipoItem = $this->input('tipo_item', '');
        $itemId   = (int)$this->input('item_id', 0);
        // El campo es de texto (ver app/Views/stock/index.php) para que el teclado numérico
        // del celular muestre la tecla "-"; el JS ya normaliza coma a punto antes de mandar,
        // pero se repite acá por las dudas (ej. si llega sin pasar por ese JS).
        $cantidad = (float)str_replace(',', '.', trim((string)$this->input('cantidad', '0')));
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
        $this->requireCanView('stock');

        $desde  = $this->param('desde') ?: date('Y-m-01');
        $hasta  = $this->param('hasta') ?: date('Y-m-d');

        $stockTeorico = $this->model->stockTeoricoVsFisico($desde, $hasta);
        $reporte      = $this->model->reporteComparativo($desde, $hasta);

        // Búsqueda de consumo por nombre/familia de ítems (ej. "Helado en balde" agrupa
        // todos los sabores) — sección aparte, solo trae datos si se tipeó algo.
        $consumoQ        = trim($this->param('consumo_q', ''));
        $consumoTipoItem = $this->param('consumo_tipo', '');
        $consumoBusqueda = $consumoQ !== '' ? $this->model->consumoPorBusqueda($consumoQ, $consumoTipoItem, $desde, $hasta) : [];
        $movimientosDetalle = $consumoQ !== '' ? $this->model->movimientosDetalle($consumoQ, $consumoTipoItem, $desde, $hasta) : ['compras' => [], 'ventas' => []];

        // Cruzar "productos vendidos" con su fila de stock teórico/físico del mismo período
        // (ya calculada arriba) para que se vea en una sola tabla si lo vendido cuadra con
        // el stock, y cuánto vale esa diferencia — sin repetir el cálculo de valorización.
        $teoricoPorProducto = [];
        foreach ($stockTeorico as $row) {
            if ($row['tipo_item'] === 'producto') $teoricoPorProducto[$row['id']] = $row;
        }
        foreach ($reporte['ventas'] as &$v) {
            $t = $teoricoPorProducto[(int) $v['producto_id']] ?? null;
            $v['stock_teorico_fin'] = $t['stock_teorico_fin'] ?? null;
            $v['diferencia']        = $t['diferencia']        ?? null;
            $v['valor_diferencia']  = $t['valor_diferencia']  ?? null;
        }
        unset($v);

        $this->render('stock.reporte', [
            'pageTitle'    => 'Reporte Comparativo de Stock',
            'breadcrumb'   => ['Stock' => 'stock', 'Reporte comparativo' => null],
            'reporte'      => $reporte,
            'stockTeorico' => $stockTeorico,
            'desde'        => $desde,
            'hasta'        => $hasta,
            'consumoQ'        => $consumoQ,
            'consumoTipoItem' => $consumoTipoItem,
            'consumoBusqueda' => $consumoBusqueda,
            'movimientosDetalle' => $movimientosDetalle,
        ]);
    }

    public function inventario(): void
    {
        $this->requireAuth();
        // Acceso independiente del módulo "Control de Stock": un cajero puede tener
        // stock_inventario sin tener "stock" (así entra a contar pero no ve la tabla
        // completa de stock actual ni el modal de ajuste libre de /stock).
        if (!canView('stock') && !canView('stock_inventario')) {
            abort(403, 'No tenés permiso para acceder a esta sección.');
        }
        // Si no tiene acceso a "Control de Stock", ni el breadcrumb ni el botón "Volver"
        // pueden apuntar ahí (sería un link muerto que da 403) — vuelven al inicio.
        $tieneStock = canView('stock');
        $items      = $this->model->resumenStock();

        // Si se llega desde una solicitud de inventario (ver SolicitudesController),
        // filtramos a solo los ítems que pidió el admin — el resto de la pantalla
        // (guardado, cálculo de teórico, desvío) no cambia en nada.
        $solicitud   = null;
        $solicitudId = (int)$this->param('solicitud_id', 0);
        if ($solicitudId) {
            $solicitudModel = new SolicitudModel();
            $sol = $solicitudModel->findAllConDetalle(['id' => $solicitudId, 'tipo' => 'inventario'])[0] ?? null;
            if ($sol) {
                $permitidos = array_flip($solicitudModel->resolverItemsParaInventario($solicitudId));
                $items      = array_values(array_filter($items, fn($it) => isset($permitidos["{$it['tipo']}:{$it['id']}"])));
                $sol['criterios'] = $solicitudModel->getCriterios($solicitudId);
                $solicitud = $sol;
            }
        }

        $this->render('stock.inventario', [
            'pageTitle'  => 'Inventario físico',
            'breadcrumb' => $tieneStock ? ['Stock' => 'stock', 'Inventario' => null] : ['Inventario' => null],
            'volverUrl'  => $tieneStock ? 'stock' : homeUrl(),
            'items'      => $items,
            'solicitud'  => $solicitud,
            // Sin este permiso, el usuario cuenta "a ciegas": no ve el stock que el
            // sistema espera, para que no tipee ese número en vez de contar de verdad.
            'verTeorico' => canView('stock_ver_teorico'),
        ]);
    }

    public function guardarInventario(): void
    {
        $this->requireAuth();
        if (!canEdit('stock') && !canEdit('stock_inventario')) {
            abort(403, 'No tenés permiso para acceder a esta sección.');
        }
        Csrf::check();
        // Único punto donde se evalúa desvío: un conteo físico periódico real, no
        // cualquier ajuste manual suelto ni la carga inicial de apertura.
        $this->procesarInventario('Inventario físico', true);
    }

    /** Flujo de apertura: carga stock inicial para ítems en cero */
    public function apertura(): void
    {
        $this->requireCanView('stock');
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
        $this->requireCanEdit('stock');
        Csrf::check();
        $this->procesarInventario('Inventario inicial de apertura');
    }

    private function procesarInventario(string $motivo, bool $registrarDesvio = false): void
    {
        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'No hay datos de inventario.'], 422);
        }
        $solicitudId = (int)$this->input('solicitud_id', 0);

        // El stock teórico NUNCA se toma de lo que manda el cliente: se recalcula acá
        // desde la base. Así, si un usuario no tiene permiso para ver el teórico en el
        // formulario, tampoco puede mandarlo falseado (ej. editando el JSON a mano) para
        // forzar un ajuste o un desvío que no corresponde.
        $stockActualIdx = [];
        foreach ($this->model->resumenStock() as $r) {
            $stockActualIdx[$r['tipo'] . '_' . $r['id']] = (float)$r['stock_actual'];
        }

        $usuarioId = (int)Session::user()['id'];
        $ajustados = 0;

        try {
            foreach ($items as $it) {
                $tipoItem = $it['tipo_item'] ?? '';
                $itemId   = (int)($it['item_id'] ?? 0);
                $fisico   = (float)($it['stock_fisico'] ?? 0);

                if (!$itemId || !in_array($tipoItem, ['producto','materia_prima','insumo'])) continue;

                $teorico = $stockActualIdx[$tipoItem . '_' . $itemId] ?? null;
                if ($teorico === null) continue; // ítem inactivo/eliminado entre cargar el form y guardar
                $diff = $fisico - $teorico;

                if ($registrarDesvio) {
                    // Antes de ajustar el stock, y AUNQUE lo contado coincida con el sistema:
                    // el desvío compara contra el conteo físico anterior y los movimientos del
                    // período, no contra stock_actual, así que con diff = 0 igual puede haber
                    // pérdida (ej. un ajuste manual que compensó) — y el conteo tiene que quedar
                    // registrado como punto de partida del próximo. No modifica stock por sí solo.
                    $this->desvioModel->registrar($tipoItem, $itemId, $fisico, $usuarioId);
                }

                if (abs($diff) < 0.0001) continue;

                $this->model->ajustar([
                    'tipo_item'  => $tipoItem,
                    'item_id'    => $itemId,
                    'cantidad'   => $diff,
                    'motivo'     => $motivo,
                    // 'inventario'/'apertura' (ya existen en el enum): distingue un ajuste de
                    // conteo de un ajuste manual suelto, que el desvío deja adentro de la pérdida.
                    'origen'     => $registrarDesvio ? 'inventario' : 'apertura',
                    'usuario_id' => $usuarioId,
                ]);
                $ajustados++;
            }

            // Cierra el círculo con la solicitud que originó este conteo (si vino de una):
            // el usuario no tiene que volver a Solicitudes a marcarla resuelta a mano.
            if ($solicitudId) {
                (new SolicitudModel())->cambiarEstado($solicitudId, 'resuelta', $usuarioId);
            }

            $this->json(['ok' => true, 'ajustados' => $ajustados, 'mensaje' => "$ajustados ítems ajustados."]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** AJAX: historial de movimientos de stock de un ítem para gráfico */
    public function historialItem(): void
    {
        $this->requireCanView('stock');
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

    public function importar(): void
    {
        $this->requireCanEdit('stock');
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 422);
        }
        try {
            $rows = ImportHelper::parseUploadedFile($_FILES['archivo']);
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if (empty($rows)) {
            $this->json(['ok' => false, 'error' => 'El archivo está vacío o sin datos.'], 422);
        }

        // Cachear ítems para lookup por nombre o código
        $db = Database::getInstance();
        $buildIdx = function(string $tabla, string $tipo) use ($db): array {
            $rows = $db->query("SELECT id, nombre, codigo FROM `{$tabla}` WHERE eliminado=0 AND activo=1")->fetchAll(\PDO::FETCH_ASSOC);
            $idx = [];
            foreach ($rows as $r) {
                $idx[strtolower($r['nombre'])] = ['id' => $r['id'], 'tipo' => $tipo];
                if ($r['codigo']) { $idx[strtolower($r['codigo'])] = ['id' => $r['id'], 'tipo' => $tipo]; }
            }
            return $idx;
        };
        $idx = array_merge(
            $buildIdx('productos',      'producto'),
            $buildIdx('materias_primas','materia_prima'),
            $buildIdx('insumos',        'insumo')
        );

        $usuarioId  = (int)Session::user()['id'];
        $importados = 0;
        $errores    = [];

        foreach ($rows as $i => $row) {
            $fila     = $i + 2;
            $busqueda = strtolower(trim($row['nombre_o_codigo'] ?? ''));
            $cantidad = ImportHelper::toFloat($row['cantidad'] ?? '0');
            $motivo   = ImportHelper::nullIfEmpty($row['motivo'] ?? '');

            if (!$busqueda) { $errores[] = "Fila {$fila}: 'nombre_o_codigo' es obligatorio."; continue; }
            if ($cantidad == 0) { $errores[] = "Fila {$fila}: 'cantidad' no puede ser cero."; continue; }

            // Si viene tipo_item explícito lo respetamos, sino buscamos en el índice
            $tipoExplicito = strtolower(trim($row['tipo_item'] ?? ''));
            $item = null;

            if ($tipoExplicito && in_array($tipoExplicito, ['producto','materia_prima','insumo'], true)) {
                // Buscar solo en ese tipo
                $tabla = match($tipoExplicito) {
                    'materia_prima' => 'materias_primas',
                    'insumo'        => 'insumos',
                    default         => 'productos',
                };
                $r = $db->prepare("SELECT id FROM `{$tabla}` WHERE eliminado=0 AND activo=1 AND (LOWER(nombre)=? OR LOWER(codigo)=?)");
                $r->execute([$busqueda, $busqueda]);
                $found = $r->fetch(\PDO::FETCH_ASSOC);
                if ($found) { $item = ['id' => $found['id'], 'tipo' => $tipoExplicito]; }
            } else {
                $item = $idx[$busqueda] ?? null;
            }

            if (!$item) { $errores[] = "Fila {$fila}: No se encontró ítem '{$busqueda}'."; continue; }

            try {
                $this->model->ajustar([
                    'tipo_item'  => $item['tipo'],
                    'item_id'    => $item['id'],
                    'cantidad'   => $cantidad,
                    'motivo'     => $motivo ?? 'Importación masiva',
                    'usuario_id' => $usuarioId,
                ]);
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
            }
        }

        $this->json(['ok' => true, 'importados' => $importados, 'total' => count($rows), 'errores' => $errores]);
    }

    public function plantilla(): void
    {
        $this->requireCanView('stock');
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_ajuste_stock.csv',
            ['tipo_item', 'nombre_o_codigo', 'cantidad', 'motivo'],
            [
                ['producto',      'Facturas A4',  '50',  'Stock inicial'],
                ['materia_prima', 'Harina 000',   '100', 'Compra directa sin factura'],
                ['insumo',        'Papel film',   '-5',  'Merma detectada'],
                ['',              'HAR-000',       '20', 'Ajuste por código — tipo se detecta automáticamente'],
            ]
        );
    }
}
