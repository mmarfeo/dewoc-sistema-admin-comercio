<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/RecetaModel.php';
require_once BASE_PATH . '/app/Models/ProductoModel.php';

class RecetasController extends Controller
{
    private RecetaModel  $model;
    private ProductoModel $prodModel;

    public function __construct()
    {
        $this->model     = new RecetaModel();
        $this->prodModel = new ProductoModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('recetas.index', [
            'pageTitle'  => 'Recetas de Producción',
            'breadcrumb' => ['Producción' => null, 'Recetas' => null],
            'recetas'    => $this->model->findAllConDetalle(),
        ]);
    }

    public function nueva(): void
    {
        $this->requireAuth();
        $this->render('recetas.form', [
            'pageTitle'  => 'Nueva receta',
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Nueva' => null],
            'receta'     => null,
            'productos'  => $this->prodModel->findAllConDetalle(),
        ]);
    }

    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        [$cabecera, $detalle, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $id = $this->model->guardar($cabecera, $detalle, (int)Session::user()['id']);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Receta creada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('recetas.form', [
            'pageTitle'  => 'Editar receta: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Editar' => null],
            'receta'     => $receta,
            'productos'  => $this->prodModel->findAllConDetalle(),
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$cabecera, $detalle, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $this->model->actualizarConDetalle($id, $cabecera, $detalle, (int)Session::user()['id']);
            $this->json(['ok' => true, 'mensaje' => 'Receta actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Receta eliminada.']);
    }

    /** Vista para ejecutar producción */
    public function produccion(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $costo = $this->model->calcularCosto($id);

        $this->render('recetas.produccion', [
            'pageTitle'  => 'Producir: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Producir' => null],
            'receta'     => $receta,
            'costo'      => $costo,
        ]);
    }

    /** POST: ejecutar producción */
    public function producir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id   = (int)$this->input('receta_id');
        $cant = (float)$this->input('cantidad', 1);
        if (!$id || $cant <= 0) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $result = $this->model->producir($id, $cant, Session::user()['id']);
            if (!$result['ok']) {
                $this->json([
                    'ok'       => false,
                    'error'    => 'Stock insuficiente.',
                    'faltantes'=> $result['faltantes'],
                ], 409);
            }
            $this->json([
                'ok'       => true,
                'mensaje'  => "Producción registrada: {$result['producido']} unidades.",
                'producido'=> $result['producido'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Listado de órdenes de producción */
    public function ordenes(): void
    {
        $this->requireAuth();

        $filtros = [
            'desde'     => $this->param('desde') ?: date('Y-m-01'),
            'hasta'     => $this->param('hasta') ?: date('Y-m-d'),
            'receta_id' => (int)$this->param('receta_id') ?: null,
            'estado'    => $this->param('estado') ?: '',
        ];

        $this->render('recetas.ordenes', [
            'pageTitle'  => 'Órdenes de Producción',
            'breadcrumb' => ['Producción' => null, 'Órdenes' => null],
            'ordenes'    => $this->model->findAllOrdenes($filtros),
            'recetas'    => $this->model->findAllConDetalle(),
            'filtros'    => $filtros,
        ]);
    }

    /** POST: planificar orden sin descontar stock */
    public function planificar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id   = (int)$this->input('receta_id');
        $cant = (float)$this->input('cantidad', 1);
        if (!$id || $cant <= 0) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $ordenId = $this->model->planificarOrden($id, $cant, (int)Session::user()['id']);
            $this->json(['ok' => true, 'id' => $ordenId, 'mensaje' => 'Orden planificada correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST: cambiar estado de una orden de producción */
    public function cambiarEstadoOrden(): void
    {
        $this->requireAuth();
        Csrf::check();

        $ordenId = (int)$this->input('orden_id');
        $estado  = $this->input('estado', '');
        if (!$ordenId || !$estado) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $result = $this->model->cambiarEstadoOrden($ordenId, $estado, (int)Session::user()['id']);
            if (!$result['ok']) {
                $this->json([
                    'ok'        => false,
                    'error'     => $result['error'] ?? 'Error al cambiar estado.',
                    'faltantes' => $result['faltantes'] ?? [],
                ], 409);
            }
            $this->json(['ok' => true, 'mensaje' => 'Estado actualizado a "' . $estado . '".']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Historial de versiones de una receta */
    public function versiones(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('recetas.versiones', [
            'pageTitle'  => 'Historial de versiones: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Historial' => null],
            'receta'     => $receta,
            'versiones'  => $this->model->findVersiones($id),
        ]);
    }

    /** AJAX: buscar ingredientes */
    public function buscarIngredientes(): void
    {
        $this->requireAuth();
        $q = $this->param('q', '');
        if (strlen($q) < 2) { $this->json([]); }
        $this->json($this->model->buscarIngredientes($q));
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre   = $this->input('nombre', '');
        $prodId   = (int)$this->input('producto_id') ?: null;
        $rend     = (float)$this->input('rendimiento', 1);
        $unidad   = $this->input('unidad_rendimiento', 'unidades');
        $activo   = (int)$this->input('activo', 1);
        $notas    = $this->input('notas', '') ?: null;

        if (!$nombre) $errores[] = 'El nombre de la receta es obligatorio.';
        if ($rend <= 0) $errores[] = 'El rendimiento debe ser mayor que 0.';

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || !count($items)) {
            $errores[] = 'Agregue al menos un ingrediente.';
        }

        $cabecera = [
            'nombre'             => $nombre,
            'producto_id'        => $prodId,
            'rendimiento'        => $rend,
            'unidad_rendimiento' => $unidad,
            'activo'             => $activo,
            'notas'              => $notas,
            'eliminado'          => 0,
        ];

        $detalle = [];
        foreach ($items ?? [] as $it) {
            $tipo = $it['tipo_ingrediente'] ?? '';
            $cant = (float)($it['cantidad'] ?? 0);
            if ($cant <= 0) { $errores[] = 'Cantidad inválida en ingrediente.'; continue; }
            $detalle[] = [
                'tipo_ingrediente' => $tipo,
                'materia_prima_id' => $tipo === 'materia_prima' ? (int)$it['item_id'] : null,
                'insumo_id'        => $tipo === 'insumo'        ? (int)$it['item_id'] : null,
                'cantidad'         => $cant,
                'merma_pct'        => (float)($it['merma_pct'] ?? 0),
            ];
        }

        return [$cabecera, $detalle, $errores];
    }
}
