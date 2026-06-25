<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';

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
        $this->json(['ok' => true, 'mensaje' => 'Categoría eliminada.']);
    }
}
