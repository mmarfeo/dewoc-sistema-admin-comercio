<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';

class CategoriasController extends Controller
{
    private CategoriaModel $model;

    public function __construct()
    {
        $this->model = new CategoriaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $categorias = $this->model->findAllConPadre();
        $this->render('categorias.index', [
            'pageTitle'  => 'Categorías',
            'breadcrumb' => ['Maestros' => null, 'Categorías' => null],
            'categorias' => $categorias,
            'padres'     => $this->model->findPadres(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $nombre    = $this->input('nombre', '');
        $tipo      = $this->input('tipo', 'producto');
        $padre_id  = (int)($this->input('padre_id', 0) ?: 0) ?: null;
        $descripcion = $this->input('descripcion', '');

        if (empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        }

        $tipos_validos = ['producto', 'materia_prima', 'insumo'];
        if (!in_array($tipo, $tipos_validos)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }

        $id = $this->model->insert([
            'nombre'      => $nombre,
            'tipo'        => $tipo,
            'padre_id'    => $padre_id,
            'descripcion' => $descripcion,
            'activo'      => 1,
            'eliminado'   => 0,
        ]);

        // Solo las categorías de producto llegan al menú online (ver GET /catalogo); las de
        // materia prima e insumo son internas y no se publican.
        if ($tipo === 'producto') MenuAppNotifier::catalogoCambio('categoria', $id);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Categoría creada correctamente.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id        = (int)$this->input('id', 0);
        $nombre    = $this->input('nombre', '');
        $tipo      = $this->input('tipo', 'producto');
        $padre_id  = (int)($this->input('padre_id', 0) ?: 0) ?: null;
        $descripcion = $this->input('descripcion', '');
        $activo    = (int)$this->input('activo', 1);

        if (!$id || empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        // Evitar que una categoría sea su propio padre
        if ($padre_id === $id) {
            $this->json(['ok' => false, 'error' => 'Una categoría no puede ser su propio padre.'], 422);
        }

        $this->model->update($id, [
            'nombre'      => $nombre,
            'tipo'        => $tipo,
            'padre_id'    => $padre_id,
            'descripcion' => $descripcion,
            'activo'      => $activo,
        ]);

        if ($tipo === 'producto') MenuAppNotifier::catalogoCambio('categoria', $id);

        $this->json(['ok' => true, 'mensaje' => 'Categoría actualizada.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        }

        if ($this->model->tieneHijos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene subcategorías asociadas.'], 409);
        }

        if ($this->model->tieneProductos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene productos/materias primas/insumos asociados.'], 409);
        }

        $this->model->softDelete($id);
        MenuAppNotifier::catalogoCambio('categoria', $id);
        $this->json(['ok' => true, 'mensaje' => 'Categoría eliminada.']);
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

        // Cachear padres para lookup
        $db     = Database::getInstance();
        $padres = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0 AND padre_id IS NULL")->fetchAll(\PDO::FETCH_ASSOC);
        $padreIdx = array_column($padres, 'id', 'nombre');

        $tiposValidos = ['producto', 'materia_prima', 'insumo'];
        $importados   = 0;
        $errores      = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');
            if (!$nombre) { $errores[] = "Fila {$fila}: 'nombre' es obligatorio."; continue; }

            $tipo = strtolower(trim($row['tipo'] ?? 'producto'));
            if (!in_array($tipo, $tiposValidos, true)) {
                $errores[] = "Fila {$fila}: tipo '{$tipo}' inválido (use: producto, materia_prima, insumo)."; continue;
            }

            $padreNombre = trim($row['padre'] ?? '');
            $padreId     = $padreNombre !== '' ? ($padreIdx[$padreNombre] ?? null) : null;

            try {
                $this->model->insert([
                    'nombre'      => $nombre,
                    'tipo'        => $tipo,
                    'padre_id'    => $padreId,
                    'descripcion' => ImportHelper::nullIfEmpty($row['descripcion'] ?? ''),
                    'activo'      => 1,
                    'eliminado'   => 0,
                ]);
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
            }
        }

        // Un solo aviso para toda la importación, como en Productos.
        if ($importados > 0) MenuAppNotifier::catalogoCambio('categoria', 0);

        $this->json(['ok' => true, 'importados' => $importados, 'total' => count($rows), 'errores' => $errores]);
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_categorias.csv',
            ['nombre', 'tipo', 'padre', 'descripcion'],
            [
                ['Harinas', 'materia_prima', '', 'Harinas de distintos tipos'],
                ['Harina 000', 'materia_prima', 'Harinas', 'Categoría hija'],
                ['Embalajes', 'insumo', '', ''],
                ['Bebidas', 'producto', '', ''],
            ]
        );
    }
}
