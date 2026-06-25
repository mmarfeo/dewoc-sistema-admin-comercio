<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ClienteModel.php';

class ClientesController extends Controller
{
    private ClienteModel $model;

    public function __construct()
    {
        $this->model = new ClienteModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('clientes.index', [
            'pageTitle'  => 'Clientes',
            'breadcrumb' => ['Maestros' => null, 'Clientes' => null],
            'clientes'   => $this->model->findAll(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();

        $this->render('clientes.form', [
            'pageTitle'  => 'Nuevo cliente',
            'breadcrumb' => ['Maestros' => null, 'Clientes' => 'clientes', 'Nuevo' => null],
            'cliente'    => null,
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        [$data, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Cliente creado correctamente.']);
    }

    public function editar(): void
    {
        $this->requireAuth();

        $id      = (int)$this->param('id');
        $cliente = $this->model->findById($id);
        if (!$cliente || $cliente['eliminado']) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('clientes.form', [
            'pageTitle'  => 'Editar cliente',
            'breadcrumb' => ['Maestros' => null, 'Clientes' => 'clientes', 'Editar' => null],
            'cliente'    => $cliente,
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$data, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Cliente actualizado correctamente.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneVentas($id)) {
            $this->json(['ok' => false, 'error' => 'El cliente tiene ventas registradas y no puede eliminarse.'], 409);
        }

        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Cliente eliminado.']);
    }

    public function buscar(): void
    {
        $this->requireAuth();

        $q       = trim($this->param('q') ?? '');
        $results = $q ? $this->model->buscar($q) : [];
        $this->json(['ok' => true, 'items' => $results]);
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre      = trim($this->input('nombre', ''));
        $documento   = trim($this->input('documento', ''));
        $tipoDoc     = $this->input('tipo_doc', 'dni');
        $condIva     = $this->input('condicion_iva', 'consumidor_final');
        $telefono    = trim($this->input('telefono', ''));
        $email       = trim($this->input('email', ''));
        $direccion   = trim($this->input('direccion', ''));
        $activo      = (int)$this->input('activo', 1);

        if (!$nombre) $errores[] = 'El nombre es obligatorio.';
        if (strlen($nombre) > 150) $errores[] = 'El nombre no puede superar 150 caracteres.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';

        $condicionesValidas = ['responsable_inscripto', 'monotributista', 'exento', 'consumidor_final'];
        if (!in_array($condIva, $condicionesValidas, true)) $condIva = 'consumidor_final';

        $tiposDoc = ['dni', 'cuit'];
        if (!in_array($tipoDoc, $tiposDoc, true)) $tipoDoc = 'dni';

        $data = [
            'nombre'       => $nombre,
            'documento'    => $documento ?: null,
            'tipo_doc'     => $tipoDoc,
            'condicion_iva'=> $condIva,
            'telefono'     => $telefono ?: null,
            'email'        => $email ?: null,
            'direccion'    => $direccion ?: null,
            'activo'       => $activo,
            'eliminado'    => 0,
        ];

        return [$data, $errores];
    }
}
