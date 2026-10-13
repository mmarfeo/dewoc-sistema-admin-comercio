<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/FeriadoModel.php';

class FeriadosController extends Controller
{
    private FeriadoModel $model;

    public function __construct()
    {
        $this->model = new FeriadoModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $anio = (int)$this->param('anio', 0) ?: (int)date('Y');

        $this->render('feriados.index', [
            'pageTitle'  => 'Feriados',
            'breadcrumb' => ['Compras' => null, 'Feriados' => null],
            'anio'       => $anio,
            'feriados'   => $this->model->mapaPorAnio($anio),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $fecha  = $this->input('fecha', '');
        $nombre = trim($this->input('nombre', '')) ?: null;

        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->json(['ok' => false, 'error' => 'Fecha inválida.'], 422);
        }
        if ($this->model->esFeriado($fecha)) {
            $this->json(['ok' => false, 'error' => 'Esa fecha ya está marcada como feriado.'], 409);
        }

        $id = $this->model->agregar($fecha, $nombre);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Feriado agregado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Feriado eliminado.']);
    }
}
