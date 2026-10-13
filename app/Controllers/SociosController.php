<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/SocioModel.php';

class SociosController extends Controller
{
    private SocioModel $model;

    public function __construct()
    {
        $this->model = new SocioModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('socios.index', [
            'pageTitle'  => 'Socios',
            'breadcrumb' => ['Maestros' => null, 'Socios' => null],
            'socios'     => $this->model->findAll(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $nombre = trim($this->input('nombre', ''));
        if (empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        }

        $id = $this->model->insert([
            'nombre'    => $nombre,
            'activo'    => 1,
            'eliminado' => 0,
        ]);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Socio creado correctamente.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id     = (int) $this->input('id', 0);
        $nombre = trim($this->input('nombre', ''));
        $activo = (int) $this->input('activo', 1);

        if (!$id || empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $this->model->update($id, [
            'nombre' => $nombre,
            'activo' => $activo,
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Socio actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int) $this->input('id', 0);
        if (!$id) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        }

        // No se borra: se desactiva y listo. Igual bloqueamos el soft-delete si tiene
        // pagos históricos vinculados, para no perder el nombre en pagos ya cargados.
        if ($this->model->tienePagos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene pagos registrados. Desactivalo en vez de eliminarlo.'], 409);
        }

        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Socio eliminado.']);
    }
}
