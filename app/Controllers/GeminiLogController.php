<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GeminiLlamadaLogModel.php';

/** Log detallado de cada llamada real a Gemini, para diagnosticar el límite de 20/min del plan gratuito. */
class GeminiLogController extends Controller
{
    private GeminiLlamadaLogModel $model;

    public function __construct()
    {
        $this->model = new GeminiLlamadaLogModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['rol'] !== 'admin') abort(403);

        $tablaExiste = $this->tablaExiste();

        $filtros = [
            'desde' => $this->param('desde') ?: date('Y-m-d'),
            'hasta' => $this->param('hasta') ?: date('Y-m-d'),
            'exito' => $this->param('exito', ''),
        ];

        $this->render('gemini-log.index', [
            'pageTitle'    => 'Log de llamadas a Gemini',
            'breadcrumb'   => ['Asistente IA' => 'chat', 'Log Gemini' => null],
            'tablaExiste'  => $tablaExiste,
            'filtros'      => $filtros,
            'llamadas'     => $tablaExiste ? $this->model->listar($filtros) : [],
            'topOfensores' => $tablaExiste ? $this->model->topMensajesConMasLlamadas() : [],
        ]);
    }

    private function tablaExiste(): bool
    {
        try {
            Database::getInstance()->query("SELECT 1 FROM gemini_llamadas_log LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
