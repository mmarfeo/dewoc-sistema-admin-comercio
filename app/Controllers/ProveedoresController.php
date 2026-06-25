<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class ProveedoresController extends Controller
{
    private ProveedorModel $model;

    public function __construct()
    {
        $this->model = new ProveedorModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('proveedores.index', [
            'pageTitle'   => 'Proveedores',
            'breadcrumb'  => ['Compras' => null, 'Proveedores' => null],
            'proveedores' => $this->model->findAllActivos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('proveedores.form', [
            'pageTitle'  => 'Nuevo proveedor',
            'breadcrumb' => ['Compras' => null, 'Proveedores' => 'proveedores', 'Nuevo' => null],
            'proveedor'  => null,
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data    = $this->recogerForm();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Proveedor creado.']);
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id        = (int)$this->param('id', 0);
        $proveedor = $this->model->findById($id);
        if (!$proveedor) abort(404);

        $this->render('proveedores.form', [
            'pageTitle'  => 'Editar proveedor',
            'breadcrumb' => ['Compras' => null, 'Proveedores' => 'proveedores', 'Editar' => null],
            'proveedor'  => $proveedor,
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data    = $this->recogerForm();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Proveedor actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if ($this->model->tieneCompras($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene facturas de compra asociadas.'], 409);
        }
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Proveedor eliminado.']);
    }

    private function recogerForm(): array
    {
        return [
            'razon_social'  => $this->input('razon_social', ''),
            'cuit'          => $this->input('cuit', '') ?: null,
            'condicion_iva' => $this->input('condicion_iva', 'responsable_inscripto'),
            'direccion'     => $this->input('direccion', '') ?: null,
            'telefono'      => $this->input('telefono', '') ?: null,
            'email'         => filter_var($this->input('email', ''), FILTER_VALIDATE_EMAIL) ?: null,
            'banco'         => $this->input('banco', '') ?: null,
            'cbu'           => $this->input('cbu', '') ?: null,
            'activo'        => 1,
            'eliminado'     => 0,
        ];
    }

    private function validar(array $data): array
    {
        $errores = [];
        if (empty($data['razon_social'])) $errores[] = 'La razón social es obligatoria.';
        if (!empty($data['cuit']) && !$this->model->validarCuit($data['cuit'])) {
            $errores[] = 'El CUIT no tiene formato válido (XX-XXXXXXXX-X).';
        }
        if (!empty($data['cbu']) && strlen(preg_replace('/\D/', '', $data['cbu'])) !== 22) {
            $errores[] = 'El CBU debe tener 22 dígitos.';
        }
        return $errores;
    }
}
