<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/MateriaPrimaModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class MateriasPrimasController extends Controller
{
    private MateriaPrimaModel $model;

    public function __construct()
    {
        $this->model = new MateriaPrimaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $catModel  = new CategoriaModel();
        $provModel = new ProveedorModel();
        $this->render('materias-primas.index', [
            'pageTitle'      => 'Materias Primas',
            'breadcrumb'     => ['Maestros' => null, 'Materias Primas' => null],
            'items'          => $this->model->findAllConDetalle(),
            'categorias'     => $catModel->findAllConPadre('materia_prima'),
            'proveedores'    => $provModel->findAllActivos(),
            'unidades'       => Database::getInstance()->query('SELECT * FROM unidades_medida ORDER BY nombre')->fetchAll(),
        ]);
    }

    public function nuevo(): void { $this->index(); }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Materia prima creada.']);
    }

    public function editar(): void { $this->index(); }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Materia prima actualizada.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneUsoEnRecetas($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: está en uso en recetas.'], 409);
        }

        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Materia prima eliminada.']);
    }

    public function historialPrecios(): void
    {
        $this->requireAuth();

        $id = (int)$this->param('id');
        if (!$id) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $mp = $this->model->findById($id);
        if (!$mp) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $historial = $this->model->historialPrecios($id);

        $this->render('materias-primas.historial_precios', [
            'pageTitle'  => 'Historial de Precios — ' . $mp['nombre'],
            'breadcrumb' => ['Materias Primas' => 'materias-primas', $mp['nombre'] => null, 'Historial precios' => null],
            'mp'         => $mp,
            'historial'  => $historial,
        ]);
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
            'proveedor_id'     => (int)($this->input('proveedor_id', 0)) ?: null,
            'activo'           => (int)$this->input('activo', 1),
            'eliminado'        => 0,
        ];
    }
}
