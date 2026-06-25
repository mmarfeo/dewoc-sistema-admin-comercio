<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';

class CajaController extends Controller
{
    private CajaModel $model;

    public function __construct()
    {
        $this->model = new CajaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $usuario    = Session::user();
        $turnoActivo = $this->model->getTurnoActivo((int)$usuario['id']);
        $turnoDetalle = $turnoActivo ? $this->model->getTurnoConMovimientos((int)$turnoActivo['id']) : null;

        $this->render('caja.index', [
            'pageTitle'   => 'Caja',
            'breadcrumb'  => ['Caja' => null],
            'turnoActivo' => $turnoDetalle,
        ]);
    }

    public function abrir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuario = Session::user();
        $existe  = $this->model->getTurnoActivo((int)$usuario['id']);
        if ($existe) {
            $this->json(['ok' => false, 'error' => 'Ya tiene un turno abierto.'], 409);
        }

        $montoInicial = (float)$this->input('monto_inicial', 0);
        $obs          = $this->input('observaciones', '');

        $id = $this->model->abrir((int)$usuario['id'], $montoInicial, $obs);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Turno de caja abierto.']);
    }

    public function cerrar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $turnoId    = (int)$this->input('turno_id');
        $montoCierre = (float)$this->input('monto_cierre', 0);
        $obs        = $this->input('observaciones', '');

        $ok = $this->model->cerrar($turnoId, $montoCierre, $obs);
        $ok
            ? $this->json(['ok' => true, 'mensaje' => 'Turno cerrado correctamente.'])
            : $this->json(['ok' => false, 'error' => 'No se pudo cerrar el turno.'], 409);
    }

    public function movimiento(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuario  = Session::user();
        $turnoId  = (int)$this->input('turno_id');
        $tipo     = $this->input('tipo', 'egreso');
        $monto    = (float)$this->input('monto', 0);
        $desc     = $this->input('descripcion', '') ?: null;

        if (!in_array($tipo, ['ingreso','egreso'], true)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }
        if ($monto <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto debe ser mayor que 0.'], 422);
        }

        $this->model->registrarMovimiento([
            'turno_id'    => $turnoId,
            'tipo'        => $tipo,
            'monto'       => $monto,
            'descripcion' => $desc,
            'usuario_id'  => (int)$usuario['id'],
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Movimiento registrado.']);
    }

    public function historial(): void
    {
        $this->requireAuth();
        $this->render('caja.historial', [
            'pageTitle'  => 'Historial de Turnos',
            'breadcrumb' => ['Caja' => 'caja', 'Historial' => null],
            'turnos'     => $this->model->historial(),
        ]);
    }
}
