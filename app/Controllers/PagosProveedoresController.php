<?php

require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
require_once BASE_PATH . '/app/Models/SocioModel.php';
require_once BASE_PATH . '/app/Models/PagoProveedorAdjuntoModel.php';
require_once BASE_PATH . '/app/Models/ReembolsoSocioModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

class PagosProveedoresController extends Controller
{
    private PagoProveedorModel        $model;
    private EmpleadoModel             $empleadoModel;
    private SocioModel                $socioModel;
    private PagoProveedorAdjuntoModel $adjuntoModel;
    private ReembolsoSocioModel       $reembolsoModel;

    public function __construct()
    {
        $this->model          = new PagoProveedorModel();
        $this->empleadoModel  = new EmpleadoModel();
        $this->socioModel     = new SocioModel();
        $this->adjuntoModel   = new PagoProveedorAdjuntoModel();
        $this->reembolsoModel = new ReembolsoSocioModel();
    }

    public function index(): void
    {
        $this->requireCanView('pagos_proveedores');

        $filtros = [
            'proveedor_id' => (int) ($_GET['proveedor_id'] ?? 0) ?: null,
            'tipo'         => $_GET['tipo']   ?? '',
            'desde'        => $_GET['desde']  ?? '',
            'hasta'        => $_GET['hasta']  ?? '',
            'estado'       => $_GET['estado'] ?? '',
        ];

        $pagos        = $this->model->listar($filtros);
        $cuentas      = $this->model->getCuentaCorriente();
        $proveedores  = $this->getProveedores();
        $resumen      = $this->model->resumenPorOrigen($filtros);
        $deudaTotal   = array_sum(array_column($cuentas, 'saldo'));
        $deudaSocios  = $this->reembolsoModel->getDeudaPorSocio();
        $deudaSociosTotal = array_sum(array_column($deudaSocios, 'saldo'));
        $sinAplicarPorSocio = $this->reembolsoModel->getSinAplicarPorSocio();

        $this->render('pagos-proveedores.index', [
            'pageTitle'        => 'Pagos a Proveedores',
            'breadcrumb'       => ['Pagos' => null],
            'pagos'            => $pagos,
            'cuentas'          => $cuentas,
            'proveedores'      => $proveedores,
            'filtros'          => $filtros,
            'resumen'          => $resumen,
            'deudaTotal'       => $deudaTotal,
            'deudaSocios'      => $deudaSocios,
            'deudaSociosTotal' => $deudaSociosTotal,
            'sinAplicarPorSocio' => $sinAplicarPorSocio,
        ]);
    }

    /** Ficha de cuenta corriente de UN proveedor: extracto cronológico con saldo corrido (Debe = facturas/gastos, Haber = pagos). */
    public function cuentaCorriente(): void
    {
        $this->requireCanView('pagos_proveedores');

        $proveedorId = (int) ($_GET['proveedor_id'] ?? 0);
        $proveedor   = $proveedorId ? (new ProveedorModel())->findById($proveedorId) : false;
        if (!$proveedor) {
            http_response_code(404);
            echo 'Proveedor no encontrado.';
            exit;
        }

        $historico = !empty($_GET['historico']);
        $extracto  = $this->model->getExtracto($proveedorId, $historico);

        $this->render('pagos-proveedores.cuenta-corriente', [
            'pageTitle'   => 'Cuenta Corriente — ' . $proveedor['razon_social'],
            'breadcrumb'  => ['Pagos' => url('pagos-proveedores'), 'Cuenta corriente' => null],
            'proveedor'   => $proveedor,
            'movimientos' => $extracto['movimientos'],
            'totalDebe'   => $extracto['totalDebe'],
            'totalHaber'  => $extracto['totalHaber'],
            'saldo'       => $extracto['saldo'],
            'historico'   => $historico,
        ]);
    }

    /** Ficha de cuenta corriente de UN socio: extracto cronológico con saldo corrido (Debe = aportes suyos sin reembolsar, Haber = reembolsos aplicados). Mismo patrón que cuentaCorriente() para proveedores. */
    public function cuentaCorrienteSocio(): void
    {
        $this->requireCanView('pagos_proveedores');

        $socioId = (int) ($_GET['socio_id'] ?? 0);
        $socio   = $socioId ? $this->socioModel->findById($socioId) : false;
        if (!$socio) {
            http_response_code(404);
            echo 'Socio no encontrado.';
            exit;
        }

        $historico = !empty($_GET['historico']);
        $extracto  = $this->reembolsoModel->getExtracto($socioId, $historico);

        $this->render('pagos-proveedores.cuenta-corriente-socio', [
            'pageTitle'   => 'Cuenta Corriente — ' . $socio['nombre'],
            'breadcrumb'  => ['Pagos' => url('pagos-proveedores'), 'Cuenta corriente' => null],
            'socio'       => $socio,
            'movimientos' => $extracto['movimientos'],
            'totalDebe'   => $extracto['totalDebe'],
            'totalHaber'  => $extracto['totalHaber'],
            'saldo'       => $extracto['saldo'],
            'historico'   => $historico,
        ]);
    }

    public function nuevo(): void
    {
        $this->requireCanEdit('pagos_proveedores');

        $preselect          = (int) ($_GET['proveedor_id'] ?? 0) ?: null;
        $empleadoPreselect  = (int) ($_GET['empleado_id']  ?? 0) ?: null;
        $facturasPendientes = $preselect
            ? $this->model->getFacturasPendientesByProveedor($preselect)
            : [];
        $gastosPendientes   = $preselect
            ? $this->model->getGastosPendientesByProveedor($preselect)
            : [];

        $this->render('pagos-proveedores.form', [
            'pageTitle'          => 'Registrar Pago',
            'breadcrumb'         => ['Pagos' => url('pagos-proveedores'), 'Nuevo' => null],
            'proveedores'        => $this->getProveedores(),
            'empleados'          => $this->empleadoModel->findAllActivos(),
            'socios'             => $this->socioModel->findAllActivos(),
            'facturasPendientes' => $facturasPendientes,
            'gastosPendientes'   => $gastosPendientes,
            'preselect'          => $preselect,
            'empleadoPreselect'  => $empleadoPreselect,
        ]);
    }

    public function guardar(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $tipo         = $_POST['tipo'] ?? 'proveedor';
        $proveedorId  = $tipo === 'proveedor' ? ((int) ($_POST['proveedor_id'] ?? 0) ?: null) : null;
        $empleadoId   = $tipo === 'sueldo'     ? ((int) ($_POST['empleado_id']  ?? 0) ?: null) : null;
        $beneficiario = $tipo === 'otro'       ? (trim($_POST['beneficiario'] ?? '') ?: null) : null;

        $montoMp    = $this->parseDecimal($_POST['monto_mercado_pago']  ?? '0');
        $montoEfec  = $this->parseDecimal($_POST['monto_efectivo']      ?? '0');
        $montoDebit = $this->parseDecimal($_POST['monto_debito']        ?? '0');
        $montoTrans = $this->parseDecimal($_POST['monto_transferencia'] ?? '0');
        $montoCheq  = $this->parseDecimal($_POST['monto_cheque']        ?? '0');
        $montoOtro  = $this->parseDecimal($_POST['monto_otro']          ?? '0');
        $montoTotal = round($montoMp + $montoEfec + $montoDebit + $montoTrans + $montoCheq + $montoOtro, 2);

        if ($montoTotal <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto total debe ser mayor a cero.']);
        }
        if ($tipo === 'proveedor' && !$proveedorId) {
            $this->json(['ok' => false, 'error' => 'Seleccioná un proveedor.']);
        }
        if ($tipo === 'sueldo' && !$empleadoId) {
            $this->json(['ok' => false, 'error' => 'Seleccioná un empleado.']);
        }
        if ($tipo === 'otro' && !$beneficiario) {
            $this->json(['ok' => false, 'error' => 'Ingresá el beneficiario.']);
        }
        // Aporte de capital "libre" de un socio (no atado a pagar una factura/gasto/sueldo
        // puntual) — a diferencia de los otros 3 tipos, siempre es origen_pago='socio', por
        // eso se valida y fuerza acá antes de derivar $origenPago más abajo.
        if ($tipo === 'aporte' && empty($_POST['socio_id'])) {
            $this->json(['ok' => false, 'error' => 'Seleccioná el socio que hizo el aporte.']);
        }

        // Comprobante(s): el formulario estándar de Pagos manda un solo archivo en
        // 'comprobante' (comportamiento de siempre); el pago rápido desde una factura de
        // compra puede mandar varios en 'comprobantes[]' (ej. anverso/reverso, o varias
        // fotos del mismo ticket) — el primero queda en comprobante_path (columna legada,
        // de la que dependen otras pantallas como compras/ver.php), el resto se guarda
        // aparte en pagos_proveedores_adjuntos después de crear el pago (ver más abajo).
        $archivosComprobante = [];
        if (!empty($_FILES['comprobantes']['name'][0])) {
            foreach ($_FILES['comprobantes']['name'] as $i => $nombre) {
                if (($_FILES['comprobantes']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $archivosComprobante[] = [
                        'tmp_name' => $_FILES['comprobantes']['tmp_name'][$i],
                        'type'     => $_FILES['comprobantes']['type'][$i],
                        'size'     => $_FILES['comprobantes']['size'][$i],
                    ];
                }
            }
        } elseif (!empty($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $archivosComprobante[] = $_FILES['comprobante'];
        }

        $comprobantePath   = null;
        $comprobantesExtra = [];
        foreach ($archivosComprobante as $archivo) {
            $nombreGuardado = $this->_guardarComprobantePago($archivo);
            if (!$nombreGuardado) continue;
            if ($comprobantePath === null) $comprobantePath = $nombreGuardado;
            else $comprobantesExtra[] = $nombreGuardado;
        }

        // Facturas a aplicar
        $facturasIds    = $_POST['factura_ids']    ?? [];
        $facturaMontos  = $_POST['factura_montos'] ?? [];
        $facturasAplicadas = [];
        foreach ($facturasIds as $k => $fid) {
            $fid   = (int) $fid;
            $monto = $this->parseDecimal($facturaMontos[$k] ?? '0');
            if ($fid > 0 && $monto > 0) {
                $facturasAplicadas[] = ['factura_id' => $fid, 'monto_aplicado' => $monto];
            }
        }

        // Gastos a aplicar (alquiler, servicios, etc. con proveedor asignado)
        $gastoIds      = $_POST['gasto_ids']    ?? [];
        $gastoMontos   = $_POST['gasto_montos'] ?? [];
        $gastosAplicados = [];
        foreach ($gastoIds as $k => $gid) {
            $gid   = (int) $gid;
            $monto = $this->parseDecimal($gastoMontos[$k] ?? '0');
            if ($gid > 0 && $monto > 0) {
                $gastosAplicados[] = ['gasto_id' => $gid, 'monto_aplicado' => $monto];
            }
        }

        $origenPago = $tipo === 'aporte'
            ? 'socio' // un aporte siempre es plata puesta por un socio, nunca "empresa"
            : (in_array($_POST['origen_pago'] ?? '', ['empresa', 'socio'], true) ? $_POST['origen_pago'] : 'empresa');
        $socioId    = $origenPago === 'socio' ? ((int) ($_POST['socio_id'] ?? 0) ?: null) : null;
        // nombre_socio queda como snapshot legible del nombre al momento del pago
        // (por si el socio se renombra o se da de baja más adelante); socio_id es
        // la fuente de verdad para agrupar en reportes.
        $nombreSocio = $socioId ? ($this->socioModel->findById($socioId)['nombre'] ?? null) : null;

        try {
            $id = $this->model->guardar([
                'proveedor_id'        => $proveedorId,
                'empleado_id'         => $empleadoId,
                'beneficiario'        => $beneficiario,
                'tipo'                => $tipo,
                'fecha'               => $_POST['fecha'] ?? date('Y-m-d'),
                'concepto'            => trim($_POST['concepto'] ?? '') ?: null,
                'monto_mercado_pago'  => $montoMp,
                'monto_efectivo'      => $montoEfec,
                'monto_debito'        => $montoDebit,
                'monto_transferencia' => $montoTrans,
                'monto_cheque'        => $montoCheq,
                'monto_otro'          => $montoOtro,
                'monto_total'         => $montoTotal,
                'referencia_mp'       => trim($_POST['referencia_mp'] ?? '') ?: null,
                'comprobante_path'    => $comprobantePath,
                'notas'               => trim($_POST['notas'] ?? '') ?: null,
                'origen_pago'         => $origenPago,
                'nombre_socio'        => $nombreSocio,
                'socio_id'            => $socioId,
                'usuario_id'          => (int) Session::user()['id'],
            ], $facturasAplicadas, $gastosAplicados);

            if ($tipo === 'sueldo' && $empleadoId) {
                $this->empleadoModel->marcarLiquidado($id, $_POST['jornada_ids'] ?? [], $_POST['concepto_ids'] ?? [], $_POST['ausencia_ids'] ?? []);
            }

            $usuarioId = (int) Session::user()['id'];
            foreach ($comprobantesExtra as $nombreGuardado) {
                $this->adjuntoModel->crear($id, $nombreGuardado, $usuarioId);
            }

            $this->json(['ok' => true, 'id' => $id, 'total' => $montoTotal]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Valida y mueve un archivo de $_FILES a storage/pagos/ — devuelve el nombre guardado, o null si no es válido. */
    private function _guardarComprobantePago(array $archivo): ?string
    {
        $mime    = $archivo['type'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true) || $archivo['size'] > 8 * 1024 * 1024) return null;

        $dir = BASE_PATH . '/storage/pagos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        $name = 'pago_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        return move_uploaded_file($archivo['tmp_name'], $dir . $name) ? $name : null;
    }

    /**
     * AJAX: agrega uno o más comprobantes adicionales a un pago ya creado (ver
     * PagoProveedorAdjuntoModel) — a diferencia de guardar()/actualizar(), no toca
     * comprobante_path, solo suma a la lista.
     */
    public function subirComprobantesAdjuntos(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $pagoId = (int)($_POST['pago_id'] ?? 0);
        $pago   = $pagoId ? $this->model->findById($pagoId) : null;
        if (!$pago) $this->json(['ok' => false, 'error' => 'Pago no encontrado.'], 404);

        if (empty($_FILES['archivos']['name'][0])) {
            $this->json(['ok' => false, 'error' => 'Seleccioná al menos un archivo.'], 422);
        }

        $usuarioId = (int) Session::user()['id'];
        $subidos   = 0;
        foreach ($_FILES['archivos']['name'] as $i => $nombre) {
            if (($_FILES['archivos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $nombreGuardado = $this->_guardarComprobantePago([
                'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                'type'     => $_FILES['archivos']['type'][$i],
                'size'     => $_FILES['archivos']['size'][$i],
            ]);
            if (!$nombreGuardado) continue;
            $this->adjuntoModel->crear($pagoId, $nombreGuardado, $usuarioId);
            $subidos++;
        }

        if (!$subidos) {
            $this->json(['ok' => false, 'error' => 'No se pudo subir ningún archivo (verificá formato y tamaño, máx. 8 MB cada uno).'], 422);
        }

        $this->json([
            'ok'       => true,
            'mensaje'  => $subidos === 1 ? 'Comprobante agregado.' : "{$subidos} comprobantes agregados.",
            'adjuntos' => $this->adjuntoModel->listar($pagoId),
        ]);
    }

    /** AJAX: elimina un comprobante adicional de un pago (no el legado en comprobante_path). */
    public function eliminarComprobanteAdjunto(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $id      = (int)($_POST['id'] ?? 0);
        $adjunto = $id ? $this->adjuntoModel->findById($id) : null;
        if (!$adjunto) $this->json(['ok' => false, 'error' => 'No encontrado.'], 404);

        $path = BASE_PATH . '/storage/pagos/' . basename($adjunto['archivo']);
        if (file_exists($path)) @unlink($path);
        $this->adjuntoModel->eliminar($id);

        $this->json(['ok' => true, 'mensaje' => 'Comprobante eliminado.']);
    }

    /** GET: sirve un comprobante adicional con auth — mismo criterio que comprobante(). */
    public function verComprobanteAdjunto(): void
    {
        $this->requireCanView('pagos_proveedores');
        $id      = (int)($_GET['id'] ?? 0);
        $adjunto = $id ? $this->adjuntoModel->findById($id) : null;
        if (!$adjunto) { http_response_code(404); exit; }

        $path = BASE_PATH . '/storage/pagos/' . basename($adjunto['archivo']);
        if (!file_exists($path)) { http_response_code(404); exit; }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : 'image/jpeg';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="comprobante_pago_adjunto_' . $id . '.' . $ext . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function actualizar(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $id = (int) ($_POST['pago_id'] ?? 0);
        if (!$id) {
            $this->json(['ok' => false, 'error' => 'ID de pago requerido.']);
        }

        $montoMp    = $this->parseDecimal($_POST['monto_mercado_pago']  ?? '0');
        $montoEfec  = $this->parseDecimal($_POST['monto_efectivo']      ?? '0');
        $montoDebit = $this->parseDecimal($_POST['monto_debito']        ?? '0');
        $montoTrans = $this->parseDecimal($_POST['monto_transferencia'] ?? '0');
        $montoCheq  = $this->parseDecimal($_POST['monto_cheque']        ?? '0');
        $montoOtro  = $this->parseDecimal($_POST['monto_otro']          ?? '0');
        $montoTotal = round($montoMp + $montoEfec + $montoDebit + $montoTrans + $montoCheq + $montoOtro, 2);

        if ($montoTotal <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto total debe ser mayor a cero.']);
        }

        $origenPago  = in_array($_POST['origen_pago'] ?? '', ['empresa', 'socio'], true)
                        ? $_POST['origen_pago'] : 'empresa';
        $socioId     = $origenPago === 'socio' ? ((int) ($_POST['socio_id'] ?? 0) ?: null) : null;
        $nombreSocio = $socioId ? ($this->socioModel->findById($socioId)['nombre'] ?? null) : null;
        $facturaId   = (int) ($_POST['factura_id'] ?? 0) ?: null;
        $gastoId     = (int) ($_POST['gasto_id']   ?? 0) ?: null;

        try {
            $this->model->actualizarPago($id, [
                'fecha'               => $_POST['fecha'] ?? date('Y-m-d'),
                'concepto'            => trim($_POST['concepto'] ?? '') ?: null,
                'monto_mercado_pago'  => $montoMp,
                'monto_efectivo'      => $montoEfec,
                'monto_debito'        => $montoDebit,
                'monto_transferencia' => $montoTrans,
                'monto_cheque'        => $montoCheq,
                'monto_otro'          => $montoOtro,
                'monto_total'         => $montoTotal,
                'origen_pago'         => $origenPago,
                'nombre_socio'        => $nombreSocio,
                'socio_id'            => $socioId,
                'factura_id'          => $facturaId,
                'gasto_id'            => $gastoId,
            ]);
            $this->json(['ok' => true, 'mensaje' => 'Pago actualizado correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Vincula un pago ya registrado (sin imputar, o parcialmente imputado) a una factura o gasto pendiente */
    public function vincular(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $pagoId = (int) ($_POST['pago_id'] ?? 0);
        $tipo   = $_POST['tipo'] ?? '';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $monto  = $this->parseDecimal($_POST['monto'] ?? '0');

        if (!$pagoId || !$itemId || $monto <= 0 || !in_array($tipo, ['factura', 'gasto'], true)) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos para vincular el pago.']);
        }

        $pago = $this->model->findById($pagoId);
        if (!$pago || $pago['estado'] !== 'activo') {
            $this->json(['ok' => false, 'error' => 'El pago no existe o está anulado.']);
        }

        try {
            if ($tipo === 'factura') {
                $this->model->aplicarAFactura($pagoId, $itemId, $monto);
            } else {
                $this->model->aplicarAGasto($pagoId, $itemId, $monto);
            }
            $this->json(['ok' => true, 'mensaje' => 'Pago vinculado correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ver(): void
    {
        $this->requireCanView('pagos_proveedores');
        $id   = (int) ($_GET['id'] ?? 0);
        $pago = $this->model->findById($id);
        if (!$pago) {
            http_response_code(404);
            echo 'Pago no encontrado.';
            exit;
        }

        $facturas = $this->model->getFacturasDelPago($id);
        $gastos   = $this->model->getGastosDelPago($id);

        // Comprobantes pendientes del mismo proveedor, para poder vincular este
        // pago a uno de ellos si quedó sin imputar (o parcialmente imputado)
        $facturasPendientes = $gastosPendientes = [];
        if (!empty($pago['proveedor_id'])) {
            $facturasPendientes = $this->model->getFacturasPendientesByProveedor((int) $pago['proveedor_id']);
            $gastosPendientes   = $this->model->getGastosPendientesByProveedor((int) $pago['proveedor_id']);
        }

        $montoAplicado = array_sum(array_column($facturas, 'monto_aplicado')) + array_sum(array_column($gastos, 'monto_aplicado'));

        $this->render('pagos-proveedores.ver', [
            'pageTitle'          => 'Pago #' . $id,
            'breadcrumb'         => ['Pagos' => url('pagos-proveedores'), 'Detalle' => null],
            'pago'               => $pago,
            'facturas'           => $facturas,
            'gastos'             => $gastos,
            'facturasPendientes' => $facturasPendientes,
            'gastosPendientes'   => $gastosPendientes,
            'montoDisponible'    => round((float) $pago['monto_total'] - $montoAplicado, 2),
        ]);
    }

    public function anular(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->model->anular($id);
            $this->empleadoModel->revertirLiquidacion($id);
            $this->json(['ok' => true, 'mensaje' => 'Pago anulado y facturas revertidas a pendiente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** AJAX: facturas pendientes de un proveedor */
    public function facturasPendientes(): void
    {
        $this->requireCanView('pagos_proveedores');
        $proveedorId = (int) ($_GET['proveedor_id'] ?? 0);
        $this->json($proveedorId ? $this->model->getFacturasPendientesByProveedor($proveedorId) : []);
    }

    /** AJAX: gastos pendientes de un proveedor (alquiler, servicios, etc.) */
    public function gastosPendientes(): void
    {
        $this->requireCanView('pagos_proveedores');
        $proveedorId = (int) ($_GET['proveedor_id'] ?? 0);
        $this->json($proveedorId ? $this->model->getGastosPendientesByProveedor($proveedorId) : []);
    }

    /** AJAX: pagos de un socio puntual, para el modal de detalle de su tarjeta */
    public function pagosDeSocio(): void
    {
        $this->requireCanView('pagos_proveedores');
        $socioId = (int) ($_GET['socio_id'] ?? 0);
        if (!$socioId) {
            $this->json(['ok' => false, 'data' => []]);
        }

        $filtros = [
            'proveedor_id' => (int) ($_GET['proveedor_id'] ?? 0) ?: null,
            'tipo'         => $_GET['tipo']   ?? '',
            'desde'        => $_GET['desde']  ?? '',
            'hasta'        => $_GET['hasta']  ?? '',
            'estado'       => $_GET['estado'] ?? '',
        ];

        $this->json(['ok' => true, 'data' => $this->model->listarPorSocio($socioId, $filtros)]);
    }

    // ─── Reembolsos a socios (saldar la deuda que la empresa tiene con ellos) ─────

    /** AJAX: aportes con saldo pendiente de un socio puntual, para armar un reembolso nuevo */
    public function aportesPendientes(): void
    {
        $this->requireCanView('pagos_proveedores');
        $socioId = (int) ($_GET['socio_id'] ?? 0);
        $this->json($socioId ? $this->reembolsoModel->getAportesPendientesBySocio($socioId) : []);
    }

    public function reembolsoNuevo(): void
    {
        $this->requireCanEdit('pagos_proveedores');

        $socioPreselect     = (int) ($_GET['socio_id'] ?? 0) ?: null;
        $aportesPendientes  = $socioPreselect ? $this->reembolsoModel->getAportesPendientesBySocio($socioPreselect) : [];

        $this->render('pagos-proveedores.reembolso-form', [
            'pageTitle'          => 'Reembolsar a Socio',
            'breadcrumb'         => ['Pagos' => url('pagos-proveedores'), 'Reembolso a socio' => null],
            'socios'             => $this->socioModel->findAllActivos(),
            'aportesPendientes'  => $aportesPendientes,
            'socioPreselect'     => $socioPreselect,
        ]);
    }

    public function reembolsoGuardar(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $socioId = (int) ($_POST['socio_id'] ?? 0) ?: null;
        if (!$socioId) {
            $this->json(['ok' => false, 'error' => 'Seleccioná el socio a reembolsar.']);
        }

        $montoMp    = $this->parseDecimal($_POST['monto_mercado_pago']  ?? '0');
        $montoEfec  = $this->parseDecimal($_POST['monto_efectivo']      ?? '0');
        $montoDebit = $this->parseDecimal($_POST['monto_debito']        ?? '0');
        $montoTrans = $this->parseDecimal($_POST['monto_transferencia'] ?? '0');
        $montoCheq  = $this->parseDecimal($_POST['monto_cheque']        ?? '0');
        $montoOtro  = $this->parseDecimal($_POST['monto_otro']          ?? '0');
        $montoTotal = round($montoMp + $montoEfec + $montoDebit + $montoTrans + $montoCheq + $montoOtro, 2);

        if ($montoTotal <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto total debe ser mayor a cero.']);
        }

        $comprobantePath = null;
        if (!empty($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $comprobantePath = $this->_guardarComprobantePago($_FILES['comprobante']);
        }

        // Aportes a los que se aplica este reembolso
        $pagoIds     = $_POST['pago_ids']    ?? [];
        $pagoMontos  = $_POST['pago_montos'] ?? [];
        $aportesAplicados = [];
        foreach ($pagoIds as $k => $pid) {
            $pid   = (int) $pid;
            $monto = $this->parseDecimal($pagoMontos[$k] ?? '0');
            if ($pid > 0 && $monto > 0) {
                $aportesAplicados[] = ['pago_id' => $pid, 'monto_aplicado' => $monto];
            }
        }

        try {
            $id = $this->reembolsoModel->guardar([
                'socio_id'            => $socioId,
                'fecha'               => $_POST['fecha'] ?? date('Y-m-d'),
                'concepto'            => trim($_POST['concepto'] ?? '') ?: null,
                'monto_mercado_pago'  => $montoMp,
                'monto_efectivo'      => $montoEfec,
                'monto_debito'        => $montoDebit,
                'monto_transferencia' => $montoTrans,
                'monto_cheque'        => $montoCheq,
                'monto_otro'          => $montoOtro,
                'monto_total'         => $montoTotal,
                'comprobante_path'    => $comprobantePath,
                'notas'               => trim($_POST['notas'] ?? '') ?: null,
                'usuario_id'          => (int) Session::user()['id'],
            ], $aportesAplicados);

            AuditLog::log('reembolso_socio', 'pagos_proveedores', "Reembolso #{$id} a socio #{$socioId} por " . moneda($montoTotal));

            $this->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function reembolsoVer(): void
    {
        $this->requireCanView('pagos_proveedores');
        $id        = (int) ($_GET['id'] ?? 0);
        $reembolso = $id ? $this->reembolsoModel->findById($id) : null;
        if (!$reembolso) {
            http_response_code(404);
            echo 'Reembolso no encontrado.';
            exit;
        }

        $aportes       = $this->reembolsoModel->getAportesDelReembolso($id);
        $montoAplicado = array_sum(array_column($aportes, 'monto_aplicado'));

        // Aportes pendientes del MISMO socio, para poder vincular este reembolso a uno de
        // ellos si quedó sin imputar (o parcialmente imputado) — igual que pasa con un pago
        // a proveedor (ver ver()/vincular() más arriba). Por eso un reembolso puede quedar
        // "suelto" al cargarlo (por un importe X, sin elegir aporte todavía) y aplicarse
        // después, de a partes, contra uno o varios aportes puntuales.
        $aportesPendientes = $reembolso['estado'] === 'activo'
            ? $this->reembolsoModel->getAportesPendientesBySocio((int) $reembolso['socio_id'])
            : [];

        $this->render('pagos-proveedores.reembolso-ver', [
            'pageTitle'          => 'Reembolso #' . $id,
            'breadcrumb'         => ['Pagos' => url('pagos-proveedores'), 'Reembolso' => null],
            'reembolso'          => $reembolso,
            'aportes'            => $aportes,
            'aportesPendientes'  => $aportesPendientes,
            'montoDisponible'    => round((float) $reembolso['monto_total'] - $montoAplicado, 2),
        ]);
    }

    /** Vincula (o suma monto a) un reembolso ya registrado con un aporte pendiente de socio — pago parcial, por aporte puntual, o el resto de un importe suelto. */
    public function vincularAporte(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();

        $reembolsoId = (int) ($_POST['reembolso_id'] ?? 0);
        $pagoId      = (int) ($_POST['pago_id'] ?? 0);
        $monto       = $this->parseDecimal($_POST['monto'] ?? '0');

        if (!$reembolsoId || !$pagoId || $monto <= 0) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos para vincular el reembolso.']);
        }

        $reembolso = $this->reembolsoModel->findById($reembolsoId);
        if (!$reembolso || $reembolso['estado'] !== 'activo') {
            $this->json(['ok' => false, 'error' => 'El reembolso no existe o está anulado.']);
        }

        try {
            $this->reembolsoModel->aplicarAAporte($reembolsoId, $pagoId, $monto);
            $this->json(['ok' => true, 'mensaje' => 'Reembolso vinculado correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function reembolsoAnular(): void
    {
        $this->requireCanEdit('pagos_proveedores');
        Csrf::check();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->reembolsoModel->anular($id);
            AuditLog::log('anular_reembolso_socio', 'pagos_proveedores', "Reembolso #{$id} anulado");
            $this->json(['ok' => true, 'mensaje' => 'Reembolso anulado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Sirve el archivo de comprobante adjunto */
    public function comprobante(): void
    {
        $this->requireCanView('pagos_proveedores');
        $id   = (int) ($_GET['id'] ?? 0);
        $pago = $this->model->findById($id);
        if (!$pago || !$pago['comprobante_path']) {
            http_response_code(404);
            exit;
        }
        $path = BASE_PATH . '/storage/pagos/' . basename($pago['comprobante_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }
        $mime = str_ends_with($path, '.pdf') ? 'application/pdf' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Sirve el comprobante de un reembolso a socio — mismo criterio que comprobante(), pero apunta a reembolsos_socios. */
    public function reembolsoComprobante(): void
    {
        $this->requireCanView('pagos_proveedores');
        $id        = (int) ($_GET['id'] ?? 0);
        $reembolso = $id ? $this->reembolsoModel->findById($id) : null;
        if (!$reembolso || !$reembolso['comprobante_path']) {
            http_response_code(404);
            exit;
        }
        $path = BASE_PATH . '/storage/pagos/' . basename($reembolso['comprobante_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }
        $mime = str_ends_with($path, '.pdf') ? 'application/pdf' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function getProveedores(): array
    {
        return Database::getInstance()
            ->query("SELECT id, razon_social FROM proveedores WHERE eliminado = 0 ORDER BY razon_social")
            ->fetchAll();
    }

    private function parseDecimal(string $v): float
    {
        $v = trim($v);
        if ($v === '' || $v === '-') return 0.0;
        // Soporta "1.234,56" (AR) y "1234.56" (ISO)
        if (str_contains($v, ',') && str_contains($v, '.')) {
            return (float) str_replace(['.', ','], ['', '.'], $v);
        }
        return (float) str_replace(',', '.', $v);
    }
}
