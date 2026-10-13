<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/RecetaModel.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';

/**
 * Simulador de Recetas: recetas hipotéticas para costear posibles productos nuevos,
 * mezclando materias primas/insumos/recetas reales con ingredientes "inventados"
 * (nombre + costo estimado tipeados a mano, sin FK a ninguna tabla real). Reutiliza
 * casi toda la infraestructura de RecetaModel (recetas.es_borrador=1) para no duplicar
 * la lógica de costeo — ver el plan de esta sesión para el detalle.
 */
class RecetasBorradorController extends Controller
{
    private RecetaModel $model;

    public function __construct()
    {
        $this->model = new RecetaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $borradores = $this->model->findBorradores();
        foreach ($borradores as &$b) {
            $b['costo_total'] = $this->model->calcularCosto((int)$b['id']);
            $precio = (float)($b['precio_venta_estimado'] ?? 0);
            $b['margen_pct'] = $precio > 0 ? (($precio - $b['costo_total']) / $precio) * 100 : null;
        }
        unset($b);

        $this->render('recetas-borrador.index', [
            'pageTitle'  => 'Simulador de Recetas',
            'breadcrumb' => ['Producción' => null, 'Simulador de Recetas' => null],
            'borradores' => $borradores,
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('recetas-borrador.form', [
            'pageTitle'  => 'Nuevo borrador de receta',
            'breadcrumb' => ['Producción' => null, 'Simulador de Recetas' => 'recetas-borrador', 'Nuevo' => null],
            'receta'     => null,
            'categorias' => $this->categorias(),
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
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Borrador creado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta || !$receta['es_borrador']) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('recetas-borrador.form', [
            'pageTitle'  => 'Editar borrador: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Simulador de Recetas' => 'recetas-borrador', 'Editar' => null],
            'receta'     => $receta,
            'categorias' => $this->categorias(),
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
            $this->json(['ok' => true, 'mensaje' => 'Borrador actualizado.']);
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
        $this->json(['ok' => true, 'mensaje' => 'Borrador eliminado.']);
    }

    /** Mismo buscador que RecetasController::buscarIngredientes(), pero incluyendo otros borradores como posible sub-receta (una receta real NUNCA debe poder elegir un borrador — ver RecetaModel::buscarIngredientes()). */
    public function buscarIngredientes(): void
    {
        $this->requireAuth();
        $q         = $this->param('q', '');
        $excluirId = (int)$this->param('receta_id', 0) ?: null;
        if (strlen($q) < 2) { $this->json([]); }
        $this->json($this->model->buscarIngredientes($q, $excluirId, true));
    }

    /** POST: convierte un borrador ya sin ingredientes inventados en un producto + receta real. */
    public function promover(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $receta = $this->model->findById($id);
        if (!$receta || !$receta['es_borrador']) $this->json(['ok' => false, 'error' => 'Borrador no encontrado.'], 404);

        $precioVenta = (float)$this->input('precio_venta', 0);
        $iva         = (float)$this->input('iva', 21);
        if ($precioVenta < 0) $this->json(['ok' => false, 'error' => 'El precio de venta no puede ser negativo.'], 422);
        if (!in_array($iva, [0, 10.5, 21], true)) $iva = 21;

        $datosProducto = [
            'nombre'           => trim($this->input('nombre', '')) ?: $receta['nombre'],
            'codigo'           => trim($this->input('codigo', '')) ?: null,
            'descripcion'      => $receta['descripcion'] ?: null,
            'categoria_id'     => (int)$this->input('categoria_id', 0) ?: null,
            'tipo'             => 'elaborado',
            'precio_venta'     => $precioVenta,
            'iva'              => $iva,
            'unidad_medida_id' => null,
            'stock_minimo'     => 0,
            'controla_stock'   => 1,
            'desvio_pct'       => 0,
            'ecommerce'        => 0,
            'activo'           => 1,
            'eliminado'        => 0,
        ];

        try {
            $resultado = $this->model->promoverAProducto($id, $datosProducto);
            if (!$resultado['ok']) {
                $this->json([
                    'ok'         => false,
                    'error'      => 'Todavía hay ingredientes inventados sin reemplazar: ' . implode(', ', $resultado['pendientes']),
                    'pendientes' => $resultado['pendientes'],
                ], 422);
            }
            // Promover un borrador crea un producto real (ecommerce = 0): se avisa igual, así
            // MenuApp ya lo tiene en cuenta si lo marcan enseguida para el menú online.
            MenuAppNotifier::catalogoCambio('producto', (int)$resultado['producto_id']);

            $this->json(['ok' => true, 'producto_id' => $resultado['producto_id'], 'mensaje' => 'Receta promovida a producto real.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function categorias(): array
    {
        return Database::getInstance()
            ->query('SELECT id, nombre FROM categorias WHERE eliminado = 0 ORDER BY nombre')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre     = $this->input('nombre', '');
        $rend       = (float)$this->input('rendimiento', 1);
        $unidad     = $this->input('unidad_rendimiento', 'unidades');
        $notas      = $this->input('notas', '') ?: null;
        $descripcion   = $this->input('descripcion', '') ?: null;
        $instrucciones = $this->input('instrucciones', '') ?: null;
        $tiempoPre     = ($v = (int)$this->input('tiempo_preparacion', 0)) > 0 ? $v : null;
        $porciones     = ($v = (int)$this->input('porciones', 0)) > 0 ? $v : null;

        $precioVentaRaw      = trim($this->input('precio_venta_estimado', ''));
        $precioVentaEstimado = $precioVentaRaw !== '' ? (float)$precioVentaRaw : null;

        if (!$nombre) $errores[] = 'El nombre es obligatorio.';
        if ($rend <= 0) $errores[] = 'El rendimiento debe ser mayor que 0.';

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || !count($items)) {
            $errores[] = 'Agregue al menos un ingrediente.';
        }

        $cabecera = [
            'nombre'                => $nombre,
            'descripcion'           => $descripcion,
            'instrucciones'         => $instrucciones,
            'tiempo_preparacion'    => $tiempoPre,
            'porciones'             => $porciones,
            'producto_id'           => null,
            'rendimiento'           => $rend,
            'unidad_rendimiento'    => $unidad,
            'activo'                => 1,
            'es_borrador'           => 1,
            'precio_venta_estimado' => $precioVentaEstimado,
            'notas'                 => $notas,
            'eliminado'             => 0,
        ];

        $detalle = [];
        foreach ($items ?? [] as $it) {
            $tipo = $it['tipo_ingrediente'] ?? '';
            $cant = (float)($it['cantidad'] ?? 0);
            if ($cant <= 0) { $errores[] = 'Cantidad inválida en ingrediente.'; continue; }

            if ($tipo === 'ingrediente_libre') {
                $nombreLibre = trim((string)($it['ingrediente_libre_nombre'] ?? ''));
                if ($nombreLibre === '') { $errores[] = 'Falta el nombre de un ingrediente inventado.'; continue; }
                $detalle[] = [
                    'tipo_ingrediente'                 => 'ingrediente_libre',
                    'materia_prima_id'                 => null,
                    'insumo_id'                         => null,
                    'ingrediente_generico_id'           => null,
                    'receta_ingrediente_id'             => null,
                    'cantidad'                          => $cant,
                    'merma_pct'                         => (float)($it['merma_pct'] ?? 0),
                    'ingrediente_libre_nombre'          => $nombreLibre,
                    'ingrediente_libre_costo_unitario'  => (float)($it['ingrediente_libre_costo_unitario'] ?? 0),
                    'ingrediente_libre_unidad'          => trim((string)($it['ingrediente_libre_unidad'] ?? '')) ?: null,
                ];
                continue;
            }

            $detalle[] = [
                'tipo_ingrediente'        => $tipo,
                'materia_prima_id'        => $tipo === 'materia_prima'        ? (int)$it['item_id'] : null,
                'insumo_id'               => $tipo === 'insumo'               ? (int)$it['item_id'] : null,
                'ingrediente_generico_id' => $tipo === 'ingrediente_generico' ? (int)$it['item_id'] : null,
                'receta_ingrediente_id'   => $tipo === 'receta'               ? (int)$it['item_id'] : null,
                'cantidad'         => $cant,
                'merma_pct'        => (float)($it['merma_pct'] ?? 0),
            ];
        }

        return [$cabecera, $detalle, $errores];
    }
}
