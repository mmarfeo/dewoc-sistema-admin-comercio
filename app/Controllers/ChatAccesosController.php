<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ChatAccesoModel.php';

/** Administración del catálogo de accesos directos ("chips") del Chat IA */
class ChatAccesosController extends Controller
{
    private ChatAccesoModel $model;

    public function __construct()
    {
        $this->model = new ChatAccesoModel();
    }

    public function index(): void
    {
        $this->requireRole('admin');

        $this->render('chat.accesos', [
            'pageTitle'  => 'Accesos directos del Chat IA',
            'breadcrumb' => ['Chat IA' => 'chat', 'Accesos directos' => null],
            'accesos'    => $this->model->listarCatalogo(),
        ]);
    }

    public function crear(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        [$data, $error] = $this->parsear();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $id = $this->model->crear($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Acceso directo creado.']);
    }

    public function actualizar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$data, $error] = $this->parsear();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $this->model->actualizar($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Acceso directo actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Acceso directo eliminado.']);
    }

    private function parsear(): array
    {
        $label  = trim($this->input('label', ''));
        $texto  = trim($this->input('texto', ''));
        $orden  = (int)$this->input('orden', 0);
        $activo = (int)$this->input('activo', 1);

        if (!$label) return [[], 'El texto del botón es obligatorio.'];
        if (!$texto) return [[], 'El prompt que se envía al chat es obligatorio.'];

        return [[
            'label'  => $label,
            'texto'  => $texto,
            'orden'  => $orden,
            'activo' => $activo,
        ], null];
    }
}
