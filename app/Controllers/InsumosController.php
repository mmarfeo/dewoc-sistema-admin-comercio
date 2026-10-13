<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/InsumoModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';

class InsumosController extends Controller
{
    private InsumoModel $model;

    public function __construct()
    {
        $this->model = new InsumoModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $catModel = new CategoriaModel();
        $this->render('insumos.index', [
            'pageTitle'  => 'Insumos',
            'breadcrumb' => ['Maestros' => null, 'Insumos' => null],
            'items'      => $this->model->findAllConDetalle(),
            'categorias' => $catModel->findAllConPadre('insumo'),
            'unidades'   => Database::getInstance()->query('SELECT * FROM unidades_medida ORDER BY nombre')->fetchAll(),
            // Ingrediente genérico (ver docs/... e IngredienteGenericoModel): mismo mecanismo
            // que ya usa /materias-primas — un <select> directo en este mismo formulario, en
            // vez del buscador con clic del modal de /ingredientes-genericos (que tuvo
            // problemas reportados de que el clic no asignaba nada).
            'genericos'  => (new IngredienteGenericoModel())->listar(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        $errorGenerico = $this->validarGenerico($data);
        if ($errorGenerico) $this->json(['ok' => false, 'error' => $errorGenerico], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Insumo creado.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->recogerForm();
        $errorGenerico = $this->validarGenerico($data);
        if ($errorGenerico) $this->json(['ok' => false, 'error' => $errorGenerico], 422);

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Insumo actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if ($this->model->tieneUsoEnRecetas($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: está en uso en recetas.'], 409);
        }
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Insumo eliminado.']);
    }

    public function importar(): void
    {
        $this->requireAuth();
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

        $db = Database::getInstance();
        $unidades   = $db->query("SELECT id, abreviatura FROM unidades_medida")->fetchAll(\PDO::FETCH_ASSOC);
        $categorias = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0 AND tipo='insumo'")->fetchAll(\PDO::FETCH_ASSOC);
        $unidadIdx    = array_column($unidades,   'id', 'abreviatura');
        $categoriaIdx = array_column($categorias, 'id', 'nombre');

        $importados = 0;
        $errores    = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');
            if (!$nombre) { $errores[] = "Fila {$fila}: 'nombre' es obligatorio."; continue; }

            $unidadAbrev = trim($row['unidad'] ?? '');
            $unidadId    = $unidadAbrev !== '' ? ($unidadIdx[$unidadAbrev] ?? null) : null;
            if ($unidadAbrev !== '' && $unidadId === null) {
                $errores[] = "Fila {$fila}: Unidad '{$unidadAbrev}' no encontrada."; continue;
            }
            $catNombre   = trim($row['categoria'] ?? '');
            $categoriaId = $catNombre !== '' ? ($categoriaIdx[$catNombre] ?? null) : null;

            try {
                $this->model->insert([
                    'nombre'           => $nombre,
                    'codigo'           => ImportHelper::nullIfEmpty($row['codigo'] ?? ''),
                    'categoria_id'     => $categoriaId,
                    'unidad_medida_id' => $unidadId,
                    'stock_actual'     => ImportHelper::toFloat($row['stock_actual'] ?? '0'),
                    'stock_minimo'     => ImportHelper::toFloat($row['stock_minimo'] ?? '0'),
                    'costo_unitario'   => ImportHelper::toFloat($row['costo_unitario'] ?? '0'),
                    'activo'           => 1,
                    'eliminado'        => 0,
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
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_insumos.csv',
            ['nombre', 'codigo', 'unidad', 'categoria', 'stock_actual', 'stock_minimo', 'costo_unitario'],
            [
                ['Papel film', 'PAP-FIL', 'm', 'Embalajes', '10', '2', '500'],
                ['Guantes descartables', 'GUA-DESC', 'caja', 'Limpieza', '5', '1', '1200'],
            ]
        );
    }

    private function recogerForm(): array
    {
        $tiposGasto = ['costo_variable', 'costo_fijo', 'impuesto', 'gasto'];
        $tipoGasto  = $this->input('tipo_gasto', 'costo_variable');
        return [
            'nombre'           => $this->input('nombre', ''),
            'codigo'           => $this->input('codigo', '') ?: null,
            'categoria_id'     => (int)($this->input('categoria_id', 0)) ?: null,
            'tipo_gasto'       => in_array($tipoGasto, $tiposGasto) ? $tipoGasto : 'costo_variable',
            'unidad_medida_id' => (int)($this->input('unidad_medida_id', 0)) ?: null,
            'stock_minimo'     => (float)$this->input('stock_minimo', 0),
            'costo_unitario'   => (float)$this->input('costo_unitario', 0),
            'ingrediente_generico_id' => (int)($this->input('ingrediente_generico_id', 0)) ?: null,
            'activo'           => (int)$this->input('activo', 1),
            'controla_stock'   => (int)$this->input('controla_stock', 1),
            'eliminado'        => 0,
        ];
    }

    /** Si se asigna un genérico, su unidad de medida base debe coincidir con la del insumo — mismo chequeo que MateriasPrimasController::validarGenerico(). */
    private function validarGenerico(array $data): ?string
    {
        if (empty($data['ingrediente_generico_id'])) return null;
        if (empty($data['unidad_medida_id'])) return 'Elegí una unidad de medida para poder asignar un ingrediente genérico.';

        $generico = new IngredienteGenericoModel();
        if (!$generico->unidadCompatible((int)$data['ingrediente_generico_id'], (int)$data['unidad_medida_id'])) {
            return 'La unidad de medida de este insumo no coincide con la del ingrediente genérico elegido. Deben ser la misma para que los costos sean comparables.';
        }
        return null;
    }
}
