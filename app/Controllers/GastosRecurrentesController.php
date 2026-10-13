<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/RecurrenteModel.php';
require_once BASE_PATH . '/app/Models/GastoModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/app/Models/EmpleadoModel.php';

class GastosRecurrentesController extends Controller
{
    private RecurrenteModel $model;

    public function __construct()
    {
        $this->model = new RecurrenteModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $anio = (int)$this->param('anio', 0) ?: (int)date('Y');
        $mes  = (int)$this->param('mes', 0) ?: (int)date('m');
        if ($mes < 1 || $mes > 12) $mes = (int)date('m');

        $this->render('gastos-recurrentes.index', [
            'pageTitle'   => 'Costos Fijos Recurrentes',
            'breadcrumb'  => ['Finanzas' => null, 'Gastos' => url('gastos'), 'Recurrentes' => null],
            'recurrentes' => $this->model->findAllFiltrado(),
            'vencAnio'    => $anio,
            'vencMes'     => $mes,
            'vencimientos'=> $this->model->getVencimientosMes($anio, $mes),
            'catPlanas'   => (new GastoModel())->findCategoriasPlanas(),
            'proveedores' => (new ProveedorModel())->findAllActivos(),
            'empleados'   => array_filter(
                (new EmpleadoModel())->findAllActivos(),
                fn($e) => in_array($e['modalidad_pago'], ['mensual', 'quincenal'], true)
                    || ($e['modalidad_pago'] === 'por_hora' && (float)$e['horas_mensuales'] > 0)
            ),
            'empleadosViatico' => (new EmpleadoModel())->findAllActivos(),
            'conceptosTipos'   => (new EmpleadoModel())->getConceptosTipos(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->_recogerForm();
        if ($data === null) {
            $this->json(['ok' => false, 'error' => 'Completá los datos requeridos.'], 422);
        }

        $data['usuario_id'] = Session::user()['id'];
        $data['eliminado']  = 0;

        $id = $this->model->crear($data);
        $this->_generarAhoraSiCorresponde();
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Costo fijo recurrente creado.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->_recogerForm();
        if ($data === null) {
            $this->json(['ok' => false, 'error' => 'Completá los datos requeridos.'], 422);
        }

        $this->model->actualizar($id, $data);
        $this->_generarAhoraSiCorresponde();
        $this->json(['ok' => true, 'mensaje' => 'Costo fijo recurrente actualizado.']);
    }

    public function toggleActivo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->toggleActivo($id);
        $this->_generarAhoraSiCorresponde();
        $this->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
    }

    /**
     * Crear/editar/reactivar una plantilla es una acción deliberada del usuario: generamos
     * al toque en vez de esperar el chequeo perezoso de una vez por día (que puede haber
     * corrido más temprano hoy, antes de que esta plantilla existiera o tuviera el día
     * correcto, y quedarse "ya revisado" hasta mañana).
     */
    private function _generarAhoraSiCorresponde(): void
    {
        try {
            $this->model->generarPendientes((int)(Session::user()['id'] ?? 0));
        } catch (\Throwable $e) {
            logApp('Error generando gastos recurrentes (al guardar plantilla): ' . $e->getMessage());
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Costo fijo recurrente eliminado.']);
    }

    /** Valida y arma el array de datos según tipo (gasto | sueldo | viatico). Devuelve null si falta algo obligatorio. */
    private function _recogerForm(): ?array
    {
        $tipo           = $this->input('tipo', 'gasto');
        $descripcion    = trim($this->input('descripcion', ''));
        $monto          = (float)str_replace(',', '.', $this->input('monto', '0'));
        $diaGeneracion  = (int)$this->input('dia_generacion', 1);
        $diaVencimiento = (int)$this->input('dia_vencimiento', 0) ?: null;

        if (!in_array($tipo, ['gasto', 'sueldo', 'viatico'], true)) return null;
        if (empty($descripcion) || $monto <= 0) return null;
        if ($diaGeneracion < 1 || $diaGeneracion > 28) return null;
        if ($diaVencimiento !== null && ($diaVencimiento < 1 || $diaVencimiento > 31)) return null;

        $data = [
            'tipo'            => $tipo,
            'descripcion'     => $descripcion,
            'monto'           => $monto,
            'dia_generacion'  => $diaGeneracion,
            'dia_vencimiento' => $diaVencimiento,
            'notas'           => trim($this->input('notas', '')) ?: null,
        ];

        if ($tipo === 'sueldo') {
            $empleadoId = (int)$this->input('empleado_id', 0);
            if (!$empleadoId) return null;
            $data['empleado_id']  = $empleadoId;
            $data['categoria_id'] = null;
            $data['proveedor_id'] = null;
            $data['concepto_tipo'] = null;
        } elseif ($tipo === 'viatico') {
            $empleadoId = (int)$this->input('empleado_id_viatico', 0);
            if (!$empleadoId) return null;
            $conceptoTipo = $this->input('concepto_tipo', 'viatico');
            $tiposValidos = array_column((new EmpleadoModel())->getConceptosTipos(), 'codigo');
            if (!in_array($conceptoTipo, $tiposValidos, true)) $conceptoTipo = 'viatico';
            $data['empleado_id']   = $empleadoId;
            $data['concepto_tipo'] = $conceptoTipo;
            $data['categoria_id']  = null;
            $data['proveedor_id']  = null;
        } else {
            $categoriaId = (int)$this->input('categoria_id', 0);
            if (!$categoriaId) return null;
            $data['categoria_id'] = $categoriaId;
            $data['proveedor_id'] = (int)$this->input('proveedor_id', 0) ?: null;
            $data['empleado_id']  = null;
            $data['concepto_tipo'] = null;
        }

        return $data;
    }
}
