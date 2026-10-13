<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
require_once BASE_PATH . '/app/Models/FeriadoModel.php';

class EmpleadosController extends Controller
{
    private EmpleadoModel $model;

    public function __construct()
    {
        $this->model = new EmpleadoModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('empleados.index', [
            'pageTitle'  => 'Empleados',
            'breadcrumb' => ['Compras' => null, 'Empleados' => null],
            'empleados'  => $this->model->findAll(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('empleados.form', [
            'pageTitle'  => 'Nuevo empleado',
            'breadcrumb' => ['Compras' => null, 'Empleados' => 'empleados', 'Nuevo' => null],
            'empleado'   => null,
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data    = $this->recogerForm();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $id = $this->model->insert($data);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Empleado creado.']);
        } catch (\Throwable $e) {
            logApp('Error al crear empleado: ' . $e->getMessage(), 'ERROR');
            $this->json(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id       = (int)$this->param('id', 0);
        $empleado = $this->model->findById($id);
        if (!$empleado) abort(404);

        $this->render('empleados.form', [
            'pageTitle'  => 'Editar empleado',
            'breadcrumb' => ['Compras' => null, 'Empleados' => 'empleados', 'Editar' => null],
            'empleado'   => $empleado,
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

        try {
            $this->model->update($id, $data);
            $this->json(['ok' => true, 'mensaje' => 'Empleado actualizado.']);
        } catch (\Throwable $e) {
            logApp('Error al actualizar empleado #' . $id . ': ' . $e->getMessage(), 'ERROR');
            $this->json(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if ($this->model->tienePagos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene pagos de sueldo registrados. Podés marcarlo como inactivo en su lugar.'], 409);
        }
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Empleado eliminado.']);
    }

    public function toggleActivo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $empleado = $this->model->findById($id);
        if (!$empleado) $this->json(['ok' => false, 'error' => 'Empleado no encontrado.'], 404);

        $nuevoEstado = $empleado['activo'] ? 0 : 1;
        $this->model->update($id, ['activo' => $nuevoEstado]);
        $this->json([
            'ok'      => true,
            'activo'  => $nuevoEstado,
            'mensaje' => $nuevoEstado ? 'Empleado activado.' : 'Empleado desactivado.',
        ]);
    }

    /** Ficha del empleado con historial de pagos de sueldo */
    public function ver(): void
    {
        $this->requireAuth();
        $id       = (int)$this->param('id', 0);
        $empleado = $this->model->findById($id);
        if (!$empleado) abort(404);

        $pagos = $this->model->getPagos($id);

        $anio = (int)$this->param('anio', 0) ?: (int)date('Y');
        $mes  = (int)$this->param('mes', 0) ?: (int)date('m');
        if ($mes < 1 || $mes > 12) $mes = (int)date('m');

        $this->render('empleados.ver', [
            'pageTitle'  => 'Empleado: ' . $empleado['nombre'],
            'breadcrumb' => ['Compras' => null, 'Empleados' => 'empleados', 'Ficha' => null],
            'empleado'   => $empleado,
            'pagos'      => $pagos,
            'totalAnio'  => $this->model->totalPagadoEnAnio($id, (int)date('Y')),
            'jornadas'      => $this->model->getJornadas($id),
            'conceptos'     => $this->model->getConceptos($id),
            'ausencias'     => $this->model->getAusencias($id),
            'deudaHoras'    => $this->model->getDeudaHoras($id),
            'deudaMonto'    => $this->model->getDeudaMonto($id),
            'resumen'       => $this->model->getResumenLiquidacion($id),
            'conceptosTipos' => $this->model->getConceptosTipos(),
            'feriadosFecha' => (new FeriadoModel())->findAllDates(),
            'calAnio'       => $anio,
            'calMes'        => $mes,
            'calendario'    => $this->model->getCalendarioMes($id, $anio, $mes),
        ]);
    }

    // ── Jornadas (planilla de asistencia) ───────────────────────────────────

    public function agregarJornada(): void
    {
        $this->requireAuth();
        Csrf::check();

        $empleadoId = (int)$this->input('empleado_id', 0);
        $fecha      = $this->input('fecha', '');
        $horas      = (float)$this->input('horas', 0);
        $esFeriado  = (int)$this->input('es_feriado', 0) === 1;
        $notas      = $this->input('notas', '') ?: null;

        if (!$empleadoId || !$fecha) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos.'], 422);
        }

        $r = $this->model->agregarJornada($empleadoId, $fecha, $horas, $esFeriado, $notas, (int)Session::user()['id']);
        if (!$r['ok']) $this->json(['ok' => false, 'error' => $r['error']], 422);

        $this->json(['ok' => true, 'id' => $r['id'], 'mensaje' => 'Jornada registrada.']);
    }

    public function eliminarJornada(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $ok = $this->model->eliminarJornada($id);
        $ok
            ? $this->json(['ok' => true, 'mensaje' => 'Jornada eliminada.'])
            : $this->json(['ok' => false, 'error' => 'No se pudo eliminar (¿ya fue liquidada?).'], 409);
    }

    // ── Conceptos (viáticos / premios / bonos) ──────────────────────────────

    public function agregarConcepto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $empleadoId  = (int)$this->input('empleado_id', 0);
        $fecha       = $this->input('fecha', '');
        $tipo        = $this->input('tipo', 'otro');
        $descripcion = $this->input('descripcion', '') ?: null;
        $monto       = (float)$this->input('monto', 0);

        if (!$empleadoId || !$fecha || $monto <= 0) {
            $this->json(['ok' => false, 'error' => 'Completá fecha y un monto mayor a cero.'], 422);
        }

        $id = $this->model->agregarConcepto($empleadoId, $fecha, $tipo, $descripcion, $monto, (int)Session::user()['id']);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Concepto registrado.']);
    }

    public function eliminarConcepto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $ok = $this->model->eliminarConcepto($id);
        $ok
            ? $this->json(['ok' => true, 'mensaje' => 'Concepto eliminado.'])
            : $this->json(['ok' => false, 'error' => 'No se pudo eliminar (¿ya fue liquidado?).'], 409);
    }

    // ── Catálogo de tipos de concepto (viático/premio/bono/aguinaldo/…) ─────

    public function conceptosTipos(): void
    {
        $this->requireAuth();
        $this->render('empleados.conceptos-tipos', [
            'pageTitle'  => 'Tipos de concepto',
            'breadcrumb' => ['Compras' => null, 'Empleados' => 'empleados', 'Tipos de concepto' => null],
            'tipos'      => $this->model->getConceptosTipos(),
        ]);
    }

    public function guardarConceptoTipo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $nombre = trim($this->input('nombre', ''));
        $color  = $this->input('color', '#6c757d') ?: '#6c757d';
        if ($nombre === '') $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        $id = $this->model->crearConceptoTipo($nombre, $color);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Tipo de concepto creado.']);
    }

    public function actualizarConceptoTipo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id     = (int)$this->input('id', 0);
        $nombre = trim($this->input('nombre', ''));
        $color  = $this->input('color', '#6c757d') ?: '#6c757d';
        if (!$id || $nombre === '') $this->json(['ok' => false, 'error' => 'Datos incompletos.'], 422);

        $this->model->actualizarConceptoTipo($id, $nombre, $color);
        $this->json(['ok' => true, 'mensaje' => 'Tipo de concepto actualizado.']);
    }

    public function eliminarConceptoTipo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $ok = $this->model->eliminarConceptoTipo($id);
        $ok
            ? $this->json(['ok' => true, 'mensaje' => 'Tipo de concepto eliminado.'])
            : $this->json(['ok' => false, 'error' => 'No se puede eliminar: está en uso en algún concepto o gasto recurrente.'], 409);
    }

    // ── Calendario de asistencia (feriados/extra/ausencias, desde la ficha) ──

    /** AJAX/POST: registra el día seleccionado en el calendario según el tipo elegido */
    public function guardarDia(): void
    {
        $this->requireAuth();
        Csrf::check();

        $empleadoId = (int)$this->input('empleado_id', 0);
        $fecha      = $this->input('fecha', '');
        $tipo       = $this->input('tipo_registro', '');
        $horas      = (float)$this->input('horas', 0);
        $notas      = $this->input('notas', '') ?: null;
        $usuarioId  = (int)Session::user()['id'];

        if (!$empleadoId || !$fecha) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos.'], 422);
        }

        $r = match ($tipo) {
            'normal'  => $this->model->agregarJornada($empleadoId, $fecha, $horas, false, $notas, $usuarioId),
            'feriado' => $this->model->agregarJornada($empleadoId, $fecha, $horas, true, $notas, $usuarioId),
            'extra'   => $this->model->agregarHoraExtra($empleadoId, $fecha, $horas, $notas, $usuarioId),
            'ausencia_descuento' => $this->model->agregarAusencia($empleadoId, $fecha, 'descuento', $horas, $notas, $usuarioId),
            'ausencia_recuperar' => $this->model->agregarAusencia($empleadoId, $fecha, 'recuperar', $horas, $notas, $usuarioId),
            default   => ['ok' => false, 'error' => 'Tipo de registro inválido.'],
        };

        if (!$r['ok']) $this->json(['ok' => false, 'error' => $r['error']], 422);
        $this->json(['ok' => true, 'mensaje' => 'Día registrado.']);
    }

    /** AJAX/POST: elimina un día del calendario (jornada o ausencia, según su origen) */
    public function eliminarDia(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id     = (int)$this->input('id', 0);
        $origen = $this->input('origen', '');
        if (!$id || !in_array($origen, ['jornada', 'ausencia'], true)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $ok = $origen === 'jornada' ? $this->model->eliminarJornada($id) : $this->model->eliminarAusencia($id);
        $ok
            ? $this->json(['ok' => true, 'mensaje' => 'Registro eliminado.'])
            : $this->json(['ok' => false, 'error' => 'No se pudo eliminar (¿ya fue liquidado?).'], 409);
    }

    /** AJAX/POST: marca horas como recuperadas de la deuda pendiente del empleado */
    public function recuperarHoras(): void
    {
        $this->requireAuth();
        Csrf::check();

        $empleadoId = (int)$this->input('empleado_id', 0);
        $horas      = (float)$this->input('horas', 0);
        $notas      = $this->input('notas', '') ?: null;

        if (!$empleadoId) $this->json(['ok' => false, 'error' => 'Empleado inválido.'], 422);

        $r = $this->model->recuperarHoras($empleadoId, $horas, $notas, (int)Session::user()['id']);
        if (!$r['ok']) $this->json(['ok' => false, 'error' => $r['error']], 422);

        $mensaje = sprintf(
            'Se registraron %s hs recuperadas (equivalen a %s), reduciendo la deuda del empleado.',
            number_format($r['horas_aplicadas'], 2, ',', '.'),
            moneda($r['monto_aplicado'])
        );
        $this->json(['ok' => true, 'mensaje' => $mensaje]);
    }

    /** AJAX: resumen de lo pendiente de liquidar (usado desde Registrar Pago) */
    public function pendientes(): void
    {
        $this->requireAuth();
        $empleadoId = (int)$this->param('empleado_id', 0);
        if (!$empleadoId) $this->json(['ok' => false, 'error' => 'Empleado inválido.'], 422);

        $this->json($this->model->getResumenLiquidacion($empleadoId));
    }

    private function recogerForm(): array
    {
        $modalidadesValidas = ['mensual', 'quincenal', 'diario', 'por_hora'];
        $modalidad = $this->input('modalidad_pago', 'mensual');
        if (!in_array($modalidad, $modalidadesValidas, true)) $modalidad = 'mensual';

        return [
            'nombre'             => $this->input('nombre', ''),
            'documento'          => $this->input('documento', '') ?: null,
            'puesto'             => $this->input('puesto', '') ?: null,
            'modalidad_pago'     => $modalidad,
            'fecha_ingreso'      => $this->input('fecha_ingreso', '') ?: null,
            'sueldo_mensual'     => (float)$this->input('sueldo_mensual', 0),
            'valor_hora'         => (float)$this->input('valor_hora', 0),
            'valor_dia'          => (float)$this->input('valor_dia', 0),
            'horas_mensuales'    => (float)$this->input('horas_mensuales', 0),
            'markup_feriado_pct' => (float)$this->input('markup_feriado_pct', 100),
            'telefono'           => $this->input('telefono', '') ?: null,
            'email'              => filter_var($this->input('email', ''), FILTER_VALIDATE_EMAIL) ?: null,
            'notas'              => $this->input('notas', '') ?: null,
            'activo'             => (int)($this->input('activo', '1') === '1'),
            'eliminado'          => 0,
        ];
    }

    private function validar(array $data): array
    {
        $errores = [];
        if (empty($data['nombre'])) $errores[] = 'El nombre es obligatorio.';
        if ($data['sueldo_mensual'] < 0) $errores[] = 'El sueldo mensual no puede ser negativo.';
        if ($data['valor_hora'] < 0)     $errores[] = 'El valor hora no puede ser negativo.';
        if ($data['valor_dia'] < 0)      $errores[] = 'El valor día no puede ser negativo.';
        if ($data['markup_feriado_pct'] < 0) $errores[] = 'El % de recargo por feriado no puede ser negativo.';
        if ($data['horas_mensuales'] < 0) $errores[] = 'Las horas mensuales no pueden ser negativas.';
        if ($data['modalidad_pago'] === 'por_hora' && $data['valor_hora'] <= 0) {
            $errores[] = 'Ingresá el valor hora para la modalidad "Por hora".';
        }
        if ($data['modalidad_pago'] === 'diario' && $data['valor_dia'] <= 0) {
            $errores[] = 'Ingresá el valor día para la modalidad "Por día".';
        }
        if (in_array($data['modalidad_pago'], ['mensual', 'quincenal'], true) && $data['sueldo_mensual'] <= 0) {
            $errores[] = 'Ingresá el sueldo mensual para esta modalidad.';
        }
        return $errores;
    }
}
