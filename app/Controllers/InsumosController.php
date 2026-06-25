<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/InsumoModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';

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
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

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

    private function recogerForm(): array
    {
        return [
            'nombre'           => $this->input('nombre', ''),
            'codigo'           => $this->input('codigo', '') ?: null,
            'categoria_id'     => (int)($this->input('categoria_id', 0)) ?: null,
            'unidad_medida_id' => (int)($this->input('unidad_medida_id', 0)) ?: null,
            'stock_minimo'     => (float)$this->input('stock_minimo', 0),
            'costo_unitario'   => (float)$this->input('costo_unitario', 0),
            'activo'           => (int)$this->input('activo', 1),
            'eliminado'        => 0,
        ];
    }
}
