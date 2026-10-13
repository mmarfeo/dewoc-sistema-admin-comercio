<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ReportesModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionFiscalModel.php';
require_once BASE_PATH . '/core/PrecioHelper.php';
require_once BASE_PATH . '/app/Models/ReporteSemanalModel.php';
require_once BASE_PATH . '/app/Models/DesvioModel.php';
require_once BASE_PATH . '/app/Models/SeparacionIngresosModel.php';

class ReportesController extends Controller
{
    private ReportesModel           $model;
    private ConfiguracionFiscalModel $fiscalModel;
    private DesvioModel             $desvioModel;

    public function __construct()
    {
        $this->model       = new ReportesModel();
        $this->fiscalModel = new ConfiguracionFiscalModel();
        $this->desvioModel = new DesvioModel();
    }

    public function ventas(): void
    {
        $this->requireCanView('reportes_ventas');

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'metodo_pago' => $this->param('metodo_pago') ?? '',
            'usuario_id'  => (int)($this->param('usuario_id') ?? 0),
        ];

        $ventas   = $this->model->ventasPorPeriodo($desde, $hasta, $filtros);
        $resumen  = $this->model->resumenVentas($desde, $hasta);
        $top      = $this->model->topProductos($desde, $hasta, 10);
        $porDia   = $this->model->ventasPorDia($desde, $hasta);

        $agrupacion = $this->param('agrupacion', 'dia');
        if (!in_array($agrupacion, ['dia', 'semana', 'mes'], true)) $agrupacion = 'dia';
        $mesesReferencia = max(1, min(12, (int)$this->param('meses_referencia', 3)));

        $separacionModel = new SeparacionIngresosModel();
        $referencia      = $separacionModel->calcularReferencia($hasta, $mesesReferencia);
        $ventasAgrupadas = $separacionModel->ventasAgrupadas($desde, $hasta, $agrupacion);
        $separacion      = $separacionModel->aplicarAPeriodo($ventasAgrupadas, $referencia['porcentajes']);
        $detalleRecompra = $separacionModel->detalleRecompraPorProducto($desde, $hasta);

        $this->render('reportes.ventas', [
            'pageTitle'          => 'Reporte de Ventas',
            'breadcrumb'         => ['Reportes' => null, 'Ventas' => null],
            'desde'              => $desde,
            'hasta'              => $hasta,
            'ventas'             => $ventas,
            'resumen'            => $resumen,
            'top'                => $top,
            'porDia'             => $porDia,
            'filtros'            => $filtros,
            'configFiscal'       => $this->fiscalModel->obtener(),
            'categoriasMono'     => ConfiguracionFiscalModel::categorias(),
            // Opción D9 (tarea 0.6): el valor efectivo y si todavía está sin guardar, para
            // mostrarla preseleccionada con el comportamiento histórico hasta que el
            // usuario la confirme.
            'preciosIncluyenIva'     => PrecioHelper::preciosIncluyenIva(),
            'preciosIvaSinConfigurar'=> PrecioHelper::sinConfigurar(),
            'discriminaIva'          => PrecioHelper::discriminaIva(),
            // El tope de Monotributo se controla contra lo facturado CON CAE de AFIP, no
            // contra el total de ventas (una venta sin "Facturar AFIP" tildado no cuenta).
            'facturado12Meses'   => (new VentaModel())->facturadoAfipUltimos12Meses(),
            'agrupacion'         => $agrupacion,
            'mesesReferencia'    => $mesesReferencia,
            'referencia'         => $referencia,
            'separacion'         => $separacion,
            'detalleRecompra'    => $detalleRecompra,
        ]);
    }

    /** Guarda la categoría de monotributo elegida (para el indicador de límite de facturación) */
    public function guardarCategoriaMonotributo(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $categoria = strtoupper(trim($this->input('categoria', '')));
        if ($categoria !== '' && !array_key_exists($categoria, ConfiguracionFiscalModel::categorias())) {
            $this->json(['ok' => false, 'error' => 'Categoría inválida.'], 422);
        }

        $this->fiscalModel->guardarCategoria($categoria ?: null);
        $this->json(['ok' => true]);
    }

    /**
     * Opción D9: si los precios de venta cargados ya traen IVA o si se les suma al vender
     * (ver core/PrecioHelper.php y docs/integracion-menuapp-checklist.md, tarea 0.6).
     *
     * Es lo más sensible de esta pantalla: cambia el precio que efectivamente paga el
     * cliente, tanto en el POS como en el menú online, así que queda registrado en el
     * AuditLog con el valor anterior y el nuevo.
     */
    public function guardarPreciosIncluyenIva(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $raw = (string)$this->input('incluyen', '');
        if (!in_array($raw, ['0', '1'], true)) {
            $this->json(['ok' => false, 'error' => 'Valor inválido: usá 1 (incluyen IVA) o 0 (no incluyen).'], 422);
        }
        $incluyen = $raw === '1';

        require_once BASE_PATH . '/core/PrecioHelper.php';
        require_once BASE_PATH . '/core/AuditLog.php';

        $antes = PrecioHelper::sinConfigurar()
            ? 'sin configurar (' . (PrecioHelper::preciosIncluyenIvaPorDefecto() ? 'incluyen' : 'no incluyen') . ' por defecto)'
            : (PrecioHelper::preciosIncluyenIva() ? 'incluyen IVA' : 'no incluyen IVA');

        $this->fiscalModel->guardarPreciosIncluyenIva($incluyen);
        PrecioHelper::limpiarCache();

        AuditLog::log('actualizar', 'reportes',
            'Configuración fiscal: los precios de venta cargados pasan de "' . $antes . '" a "'
            . ($incluyen ? 'incluyen IVA' : 'no incluyen IVA (se suma al vender)') . '"');

        $this->json([
            'ok'      => true,
            'mensaje' => 'Guardado. Cambia el precio que pagan tus clientes en el local y en el menú online: revisá tus precios.',
        ]);
    }

    /** El admin bloquea/desbloquea globalmente "Facturar por AFIP" en el POS (ej. cerca del límite de su categoría) */
    public function guardarBloqueoFacturacionAfip(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $this->fiscalModel->guardarBloqueoFacturacionAfip((bool)(int)$this->input('bloqueado', 0));
        $this->json(['ok' => true]);
    }

    /** El admin puede sobreescribir a mano el límite de facturación (referencia: el teórico de la categoría) */
    public function guardarLimiteFacturacionManual(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $raw = trim($this->input('limite', ''));
        if ($raw === '') {
            $this->fiscalModel->guardarLimiteManual(null);
            $this->json(['ok' => true]);
        }

        $valor = (float)str_replace(',', '.', $raw);
        if ($valor <= 0) {
            $this->json(['ok' => false, 'error' => 'El límite debe ser mayor a cero.'], 422);
        }

        $this->fiscalModel->guardarLimiteManual($valor);
        $this->json(['ok' => true]);
    }

    /** El admin guarda el saldo a prorratear día a día en el POS, junto con el período (desde/hasta) en que se reparte */
    public function guardarProrateoDiario(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $saldoRaw = trim($this->input('saldo', ''));
        $saldo    = $saldoRaw === '' ? null : (float)str_replace(',', '.', $saldoRaw);

        $desde = trim($this->input('desde', '')) ?: null;
        $hasta = trim($this->input('hasta', '')) ?: null;

        if (($desde !== null) !== ($hasta !== null)) {
            $this->json(['ok' => false, 'error' => 'Completá las dos fechas (inicio y vencimiento), o dejalas vacías las dos.'], 422);
        }
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $this->json(['ok' => false, 'error' => 'La fecha de inicio debe ser anterior (o igual) al vencimiento.'], 422);
        }

        $this->fiscalModel->guardarProrateoDiario($saldo, $desde, $hasta);
        $this->json(['ok' => true]);
    }

    /** Reporte semanal: compras (pagadas/pendientes), ventas por método de pago y margen bruto */
    public function semanal(): void
    {
        $this->requireCanView('reportes_semanal');

        $desde = $this->param('desde', '');
        $hasta = $this->param('hasta', '');
        if (!$desde || !$hasta) {
            // Semana anterior completa (lunes a domingo) por defecto, sin importar qué día es hoy
            $desde = (new \DateTime('monday last week'))->format('Y-m-d');
            $hasta = (new \DateTime('sunday last week'))->format('Y-m-d');
        }

        $this->render('reportes.semanal', [
            'pageTitle'  => 'Reporte Semanal',
            'breadcrumb' => ['Reportes' => null, 'Semanal' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'resumen'    => (new ReporteSemanalModel())->generarResumen($desde, $hasta),
        ]);
    }

    public function compras(): void
    {
        $this->requireCanView('reportes_compras');

        [$desde, $hasta] = $this->rango();

        $proveedores = (new ProveedorModel())->findAllActivos();

        $filtros = [
            'proveedor_id'     => (int)($this->param('proveedor_id') ?? 0),
            'tipo_comprobante' => $this->param('tipo_comprobante') ?? '',
        ];

        $compras      = $this->model->comprasPorPeriodo($desde, $hasta, $filtros);
        $resumen      = $this->model->resumenCompras($desde, $hasta);
        $porProveedor = $this->model->comprasPorProveedor($desde, $hasta);

        $this->render('reportes.compras', [
            'pageTitle'   => 'Reporte de Compras',
            'breadcrumb'  => ['Reportes' => null, 'Compras' => null],
            'desde'       => $desde,
            'hasta'       => $hasta,
            'compras'     => $compras,
            'resumen'     => $resumen,
            'porProveedor'=> $porProveedor,
            'proveedores' => $proveedores,
            'filtros'     => $filtros,
        ]);
    }

    /** Materias primas/insumos con compras cargadas a precio $0 — ver ReportesModel::comprasPrecioCero() */
    public function comprasPrecioCero(): void
    {
        $this->requireCanView('reportes_compras_precio_cero');
        $this->render('reportes.compras-precio-cero', [
            'pageTitle'  => 'Compras a precio $0',
            'breadcrumb' => ['Reportes' => null, 'Compras a precio $0' => null],
            'filas'      => $this->model->comprasPrecioCero(),
        ]);
    }

    /** Costo actual vs. precio de la última compra real, para detectar ítems con el CPP desviado — ver ReportesModel::costosDesviados() */
    public function costosDesviados(): void
    {
        $this->requireCanView('reportes_costos_desviados');
        $this->render('reportes.costos-desviados', [
            'pageTitle'  => 'Costos desviados',
            'breadcrumb' => ['Reportes' => null, 'Costos desviados' => null],
            'filas'      => $this->model->costosDesviados(),
        ]);
    }

    /**
     * Recalcula costo_unitario de una o varias materias primas/insumos, repasando todo
     * su historial real de compras (mismo cálculo que FacturaCompraModel usa al editar
     * una factura) — para corregir de una los ítems que "Costos desviados" ya detectó,
     * en vez de tener que ir uno por uno a Materias Primas/Insumos.
     * $items (JSON): [{tipo:'materia_prima'|'insumo', id:N}, ...]
     */
    public function recalcularCostos(): void
    {
        $this->requireCanView('reportes_costos_desviados');
        Csrf::check();

        $items = json_decode($this->input('items', '[]'), true);
        if (!is_array($items) || !$items) {
            $this->json(['ok' => false, 'error' => 'No se seleccionó ningún ítem.'], 422);
        }

        require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
        require_once BASE_PATH . '/core/AuditLog.php';
        $facturaModel = new FacturaCompraModel();

        $corregidos = 0;
        $errores    = [];
        foreach ($items as $it) {
            $tipo = $it['tipo'] ?? '';
            $id   = (int)($it['id'] ?? 0);
            if (!in_array($tipo, ['materia_prima', 'insumo'], true) || !$id) {
                $errores[] = 'Ítem inválido.';
                continue;
            }
            try {
                $facturaModel->recalcularCostoDesdeHistorial($tipo, $id);
                $corregidos++;
            } catch (\Throwable $e) {
                $errores[] = "id={$id}: " . $e->getMessage();
            }
        }

        AuditLog::log('recalcular', 'reportes', "Recálculo masivo desde Costos desviados: {$corregidos} corregido(s)" . ($errores ? (', ' . count($errores) . ' error(es)') : ''));

        $this->json(['ok' => true, 'corregidos' => $corregidos, 'errores' => $errores]);
    }

    /** Ventas de productos hechos al momento confirmadas sin stock suficiente — ver ReportesModel::ventasSinStock() */
    public function ventasSinStock(): void
    {
        $this->requireCanView('reportes_ventas_sin_stock');
        [$desde, $hasta] = $this->rango();
        $this->render('reportes.ventas-sin-stock', [
            'pageTitle'  => 'Ventas sin stock',
            'breadcrumb' => ['Reportes' => null, 'Ventas sin stock' => null],
            'filas'      => $this->model->ventasSinStock($desde, $hasta),
            'desde'      => $desde,
            'hasta'      => $hasta,
        ]);
    }

    /**
     * Detalle de ventas con costo real de MP (ver ReportesModel::ventasConCostoReal()):
     * a diferencia del cron semanal de margen por producto (que usa el costo ACTUAL
     * aplicado retroactivamente a toda la semana), acá cada línea muestra el costo
     * guardado al momento de esa venta puntual, con el sabor/variante realmente elegido
     * cuando aplica. rango() ya default-ea a "1° del mes hasta hoy" (mes a la fecha); el
     * botón "Ayer" de la vista solo pisa desde/hasta por querystring.
     */
    public function margenVentas(): void
    {
        $this->requireCanView('reportes_margen_producto');

        [$desde, $hasta] = $this->rango();
        $filas = $this->model->ventasConCostoReal($desde, $hasta);

        $totalVendido = array_sum(array_column($filas, 'total_vendido'));
        $totalCosto   = array_sum(array_column($filas, 'costo_mp'));
        $totalMargen  = $totalVendido - $totalCosto;

        $this->render('reportes.margen-ventas', [
            'pageTitle'      => 'Margen de ventas (costo real)',
            'breadcrumb'     => ['Reportes' => null, 'Margen de ventas' => null],
            'filas'          => $filas,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'totalVendido'   => $totalVendido,
            'totalCosto'     => $totalCosto,
            'totalMargen'    => $totalMargen,
            'totalMargenPct' => $totalVendido > 0.009 ? round($totalMargen / $totalVendido * 100, 1) : 0.0,
        ]);
    }

    /**
     * Recalcula costo_mp_real/costo_ingrediente_generico de las ventas de un rango de
     * fechas (ver RecetaModel::recalcularCostosVentasEnPeriodo() para el detalle de qué
     * hace y su limitación: usa el costo ACTUAL de cada ingrediente, no reconstruye el
     * precio exacto de ese día). Solo admin — reescribe un costo que el resto del sistema
     * trata como "congelado al momento de la venta".
     */
    public function recalcularCostosHistoricos(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $desde = (string) $this->input('desde', '');
        $hasta = (string) $this->input('hasta', '');
        if (!$desde || !$hasta) {
            $this->json(['ok' => false, 'error' => 'Indicá desde y hasta.'], 422);
        }

        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $resultado = (new RecetaModel())->recalcularCostosVentasEnPeriodo($desde, $hasta);

        require_once BASE_PATH . '/core/AuditLog.php';
        AuditLog::log('recalcular_costos_historicos', 'ventas_detalle', sprintf(
            "Recalculados %d de %d ítems de venta entre %s y %s",
            $resultado['actualizadas'], $resultado['filas_totales'], $desde, $hasta
        ));

        $this->json([
            'ok'      => true,
            'mensaje' => $resultado['actualizadas'] > 0
                ? "Se recalcularon {$resultado['actualizadas']} ítem(s) de venta con receta propia en ese período."
                : 'No había ítems de venta con receta propia en ese período (los de reventa simple no usan este costo).',
        ] + $resultado);
    }

    public function stock(): void
    {
        $this->requireCanView('reportes_stock');

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'tipo_item' => $this->param('tipo_item') ?? '',
            'origen'    => $this->param('origen') ?? '',
        ];

        $movimientos = $this->model->movimientosStock($desde, $hasta, $filtros);

        $this->render('reportes.stock', [
            'pageTitle'   => 'Reporte de Stock',
            'breadcrumb'  => ['Reportes' => null, 'Stock' => null],
            'desde'       => $desde,
            'hasta'       => $hasta,
            'movimientos' => $movimientos,
            'filtros'     => $filtros,
        ]);
    }

    /** Reporte de mermas (proceso, ya existente en recetas) y desvíos (pérdida no explicada) */
    public function mermasDesvios(): void
    {
        $this->requireCanView('reportes_mermas_desvios');

        $this->render('reportes.mermas-desvios', [
            'pageTitle'  => 'Mermas y Desvíos',
            'breadcrumb' => ['Reportes' => null, 'Mermas y Desvíos' => null],
            'items'      => $this->desvioModel->reporte(),
        ]);
    }

    /** AJAX: historial completo de ciclos de desvío de un ítem, para el modal de detalle */
    public function historialDesvioItem(): void
    {
        $this->requireCanView('reportes_mermas_desvios');
        $tipo = $this->param('tipo', '');
        $id   = (int)$this->param('id', 0);
        if (!$id || !in_array($tipo, ['producto', 'materia_prima'], true)) {
            $this->json(['ok' => false, 'data' => []]);
        }
        $this->json(['ok' => true, 'data' => $this->desvioModel->historialItem($tipo, $id)]);
    }

    public function caja(): void
    {
        $this->requireCanView('reportes_caja');

        [$desde, $hasta] = $this->rango();

        $turnos  = $this->model->turnosPorPeriodo($desde, $hasta);
        $resumen = $this->model->resumenCaja($desde, $hasta);

        $this->render('reportes.caja', [
            'pageTitle'  => 'Reporte de Caja',
            'breadcrumb' => ['Reportes' => null, 'Caja' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'turnos'     => $turnos,
            'resumen'    => $resumen,
        ]);
    }

    public function produccion(): void
    {
        $this->requireCanView('reportes_produccion');

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'receta_id' => (int)($this->param('receta_id') ?? 0),
        ];

        $ordenes   = $this->model->produccionPorPeriodo($desde, $hasta, $filtros);
        $resumen   = $this->model->resumenProduccion($desde, $hasta);
        $porReceta = $this->model->produccionPorReceta($desde, $hasta);
        $recetas   = $this->model->getRecetasActivas();

        $this->render('reportes.produccion', [
            'pageTitle'  => 'Reporte de Producción',
            'breadcrumb' => ['Reportes' => null, 'Producción' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'ordenes'    => $ordenes,
            'resumen'    => $resumen,
            'porReceta'  => $porReceta,
            'recetas'    => $recetas,
            'filtros'    => $filtros,
        ]);
    }

    public function nomina(): void
    {
        $this->requireCanView('reportes_nomina');

        [$desde, $hasta] = $this->rango();

        $empleadoModel = new EmpleadoModel();
        $soloActivos   = $this->param('activos', '1') !== '0';
        $empleados     = $soloActivos ? $empleadoModel->findAllActivos() : $empleadoModel->findAll();

        $modalidadLabels = ['mensual' => 'Mensual', 'quincenal' => 'Quincenal', 'diario' => 'Por día', 'por_hora' => 'Por hora'];

        $filas          = [];
        $totalPagado     = 0.0;
        $totalPendiente  = 0.0;

        foreach ($empleados as $emp) {
            $pagado   = $empleadoModel->totalPagadoEnPeriodo((int)$emp['id'], $desde, $hasta);
            $resumen  = $empleadoModel->getResumenLiquidacion((int)$emp['id']);
            $pendiente = (float)($resumen['total_sugerido'] ?? 0);

            $filas[] = [
                'id'             => $emp['id'],
                'nombre'         => $emp['nombre'],
                'modalidad'      => $modalidadLabels[$emp['modalidad_pago']] ?? $emp['modalidad_pago'],
                'activo'         => $emp['activo'],
                'pagado_periodo' => $pagado,
                'pendiente'      => $pendiente,
            ];
            $totalPagado    += $pagado;
            $totalPendiente += $pendiente;
        }

        $this->render('reportes.nomina', [
            'pageTitle'      => 'Reporte de Nómina',
            'breadcrumb'     => ['Reportes' => null, 'Nómina' => null],
            'desde'          => $desde,
            'hasta'          => $hasta,
            'filas'          => $filas,
            'totalPagado'    => $totalPagado,
            'totalPendiente' => $totalPendiente,
            'soloActivos'    => $soloActivos,
        ]);
    }

    /** Reporte consolidado de pagos: comprobantes (facturas de compra + gastos) pagos/pendientes de un período */
    public function pagos(): void
    {
        $this->requireCanView('reportes_pagos');

        [$desde, $hasta] = $this->rango();

        $proveedores = (new ProveedorModel())->findAllActivos();

        $filtros = [
            'proveedor_id' => (int)($this->param('proveedor_id') ?? 0),
            'estado'       => $this->param('estado') ?? '',
        ];

        $comprobantes = $this->model->comprobantesPeriodo($desde, $hasta, $filtros);
        $resumen      = $this->model->resumenPagosPeriodo($desde, $hasta);
        $pagos        = $this->model->pagosRegistradosPeriodo($desde, $hasta, ['proveedor_id' => $filtros['proveedor_id']]);

        $this->render('reportes.pagos', [
            'pageTitle'    => 'Reporte de Pagos',
            'breadcrumb'   => ['Reportes' => null, 'Pagos' => null],
            'desde'        => $desde,
            'hasta'        => $hasta,
            'comprobantes' => $comprobantes,
            'resumen'      => $resumen,
            'pagos'        => $pagos,
            'proveedores'  => $proveedores,
            'filtros'      => $filtros,
        ]);
    }

    /** Mapea el "tipo" de export al módulo de permiso del reporte al que pertenece */
    private const TIPO_A_MODULO = [
        'ventas'               => 'reportes_ventas',
        'compras'              => 'reportes_compras',
        'stock'                => 'reportes_stock',
        'compras_precio_cero'  => 'reportes_compras_precio_cero',
        'costos_desviados'     => 'reportes_costos_desviados',
        'caja'                 => 'reportes_caja',
        'produccion'           => 'reportes_produccion',
        'pagos'                => 'reportes_pagos',
        'nomina'               => 'reportes_nomina',
    ];

    public function exportarCsv(): void
    {
        $this->requireAuth();

        $tipo = $this->param('tipo', '');
        $this->requireCanView(self::TIPO_A_MODULO[$tipo] ?? 'reportes');

        [$desde, $hasta] = $this->rango();

        switch ($tipo) {
            case 'ventas':
                $filtros   = ['metodo_pago' => $this->param('metodo_pago') ?? '', 'usuario_id' => (int)($this->param('usuario_id') ?? 0)];
                $rows      = $this->model->ventasPorPeriodo($desde, $hasta, $filtros);
                $cabeceras = ['N° Venta', 'Fecha', 'Cajero', 'Ítems', 'Total c/IVA', 'IVA', 'Descuento', 'Método de pago'];
                $mapFn     = fn($r) => [$r['id'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['cajero'], $r['items'], number_format((float)$r['total_con_iva'], 2, '.', ''), number_format((float)$r['total_iva'], 2, '.', ''), number_format((float)$r['descuento_global'], 2, '.', ''), $r['metodo_pago']];
                break;

            case 'compras':
                $filtros   = ['proveedor_id' => (int)($this->param('proveedor_id') ?? 0), 'tipo_comprobante' => $this->param('tipo_comprobante') ?? ''];
                $rows      = $this->model->comprasPorPeriodo($desde, $hasta, $filtros);
                $cabeceras = ['N°', 'Fecha', 'Comprobante', 'Tipo', 'Proveedor', 'Cond. pago', 'Total c/IVA', 'IVA', 'Estado'];
                $mapFn     = fn($r) => [$r['id'], date('d/m/Y', strtotime($r['fecha'])), $r['nro_comprobante'], $r['tipo_comprobante'], $r['proveedor'], $r['condicion_pago'], number_format((float)$r['total_con_iva'], 2, '.', ''), number_format((float)$r['total_iva'], 2, '.', ''), $r['estado']];
                break;

            case 'stock':
                $filtros   = ['tipo_item' => $this->param('tipo_item') ?? '', 'origen' => $this->param('origen') ?? ''];
                $rows      = $this->model->movimientosStock($desde, $hasta, $filtros);
                $cabeceras = ['Fecha', 'Ítem', 'Tipo', 'Origen', 'Cantidad', 'Stock anterior', 'Stock nuevo', 'Motivo', 'Usuario'];
                $mapFn     = fn($r) => [date('d/m/Y H:i', strtotime($r['created_at'])), $r['item_nombre'], $r['tipo_item'], $r['origen'], $r['cantidad'], $r['cantidad_anterior'], $r['cantidad_nueva'], $r['motivo'], $r['usuario']];
                break;

            case 'compras_precio_cero':
                $rows      = $this->model->comprasPrecioCero();
                $cabeceras = ['Fecha', 'Comprobante', 'Proveedor', 'Tipo', 'Ítem', 'Cantidad', 'Costo actual'];
                $mapFn     = fn($r) => [date('d/m/Y', strtotime($r['fecha'])), $r['nro_comprobante'], $r['proveedor'], $r['tipo_item'], $r['item'], number_format((float)$r['cantidad'], 3, '.', ''), number_format((float)$r['costo_actual'], 4, '.', '')];
                break;

            case 'ventas_sin_stock':
                $rows      = $this->model->ventasSinStock($desde, $hasta);
                $cabeceras = ['Fecha', 'Venta #', 'Producto', 'Tipo ingrediente', 'Ingrediente', 'Cant. necesaria', 'Cant. disponible', 'Usuario'];
                $mapFn     = fn($r) => [date('d/m/Y H:i', strtotime($r['created_at'])), $r['venta_id'], $r['producto_nombre'], $r['tipo_ingrediente'], $r['ingrediente_nombre'], number_format((float)$r['cantidad_necesaria'], 3, '.', ''), number_format((float)$r['cantidad_disponible'], 3, '.', ''), $r['usuario'] ?? '—'];
                break;

            case 'costos_desviados':
                $rows      = $this->model->costosDesviados();
                $cabeceras = ['Tipo', 'Ítem', 'Costo actual (x unidad base)', 'Precio última compra (x unidad base)', 'Precio compra bruto (x unidad de compra)', 'Cant. contenida', 'Desvío %', 'Fecha última compra', 'Comprobante'];
                $mapFn     = fn($r) => [$r['tipo'], $r['nombre'], number_format((float)$r['costo_actual'], 4, '.', ''), number_format((float)$r['precio_ultima_compra'], 4, '.', ''), number_format((float)$r['precio_ultima_compra_bruto'], 4, '.', ''), $r['cantidad_contenida'] ? number_format((float)$r['cantidad_contenida'], 3, '.', '') : '1', number_format((float)$r['desviacion_pct'], 1, '.', ''), $r['fecha_ultima_compra'] ? date('d/m/Y', strtotime($r['fecha_ultima_compra'])) : '', $r['nro_comprobante']];
                break;

            case 'caja':
                $rows      = $this->model->turnosPorPeriodo($desde, $hasta);
                $cabeceras = ['N° Turno', 'Apertura', 'Cierre', 'Cajero', 'Monto inicial', 'Cierre declarado', 'Cierre calculado', 'Diferencia', 'Estado'];
                $mapFn     = fn($r) => [$r['numero'], date('d/m/Y H:i', strtotime($r['apertura_at'])), $r['cierre_at'] ? date('d/m/Y H:i', strtotime($r['cierre_at'])) : '', $r['cajero'], number_format((float)$r['monto_inicial'], 2, '.', ''), number_format((float)$r['monto_cierre_declarado'], 2, '.', ''), number_format((float)$r['monto_cierre_calculado'], 2, '.', ''), number_format((float)$r['diferencia'], 2, '.', ''), $r['estado']];
                break;

            case 'produccion':
                $filtros   = ['receta_id' => (int)($this->param('receta_id') ?? 0)];
                $rows      = $this->model->produccionPorPeriodo($desde, $hasta, $filtros);
                $cabeceras = ['ID', 'Fecha', 'Receta', 'Producto', 'Cantidad producida', 'Usuario'];
                $mapFn     = fn($r) => [$r['id'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['receta_nombre'], $r['producto_nombre'], number_format((float)$r['cantidad_producida'], 3, '.', ''), $r['usuario_nombre']];
                break;

            case 'pagos':
                $filtros   = ['proveedor_id' => (int)($this->param('proveedor_id') ?? 0), 'estado' => $this->param('estado') ?? ''];
                $rows      = $this->model->comprobantesPeriodo($desde, $hasta, $filtros);
                $cabeceras = ['Tipo', 'Fecha', 'Proveedor', 'Comprobante', 'Total', 'Estado', 'Saldo pendiente'];
                $mapFn     = fn($r) => [ucfirst($r['tipo']), date('d/m/Y', strtotime($r['fecha'])), $r['proveedor'], $r['descripcion'], number_format((float)$r['total'], 2, '.', ''), ucfirst($r['estado_pago']), number_format((float)$r['saldo_pendiente'], 2, '.', '')];
                break;

            case 'nomina':
                require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
                $empleadoModel = new EmpleadoModel();
                $rows = [];
                foreach ($empleadoModel->findAllActivos() as $emp) {
                    $rows[] = [
                        'nombre'         => $emp['nombre'],
                        'modalidad'      => $emp['modalidad_pago'],
                        'pagado_periodo' => $empleadoModel->totalPagadoEnPeriodo((int)$emp['id'], $desde, $hasta),
                        'pendiente'      => (float)($empleadoModel->getResumenLiquidacion((int)$emp['id'])['total_sugerido'] ?? 0),
                    ];
                }
                $cabeceras = ['Empleado', 'Modalidad', 'Pagado en el período', 'Pendiente de liquidar'];
                $mapFn     = fn($r) => [$r['nombre'], $r['modalidad'], number_format($r['pagado_periodo'], 2, '.', ''), number_format($r['pendiente'], 2, '.', '')];
                break;

            default:
                http_response_code(400);
                echo 'Tipo de reporte inválido.';
                exit;
        }

        $filename = 'reporte_' . $tipo . '_' . $desde . '_' . $hasta . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM para compatibilidad con Excel
        fputcsv($out, $cabeceras, ';');
        foreach ($rows as $row) {
            fputcsv($out, $mapFn($row), ';');
        }
        fclose($out);
        exit;
    }

    private function rango(): array
    {
        $hoy   = date('Y-m-d');
        $desde = $this->param('desde') ?: date('Y-m-01');
        $hasta = $this->param('hasta') ?: $hoy;

        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

        return [$desde, $hasta];
    }
}
