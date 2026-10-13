<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GastoModel.php';
require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
require_once BASE_PATH . '/app/Models/IngresoModel.php';
require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionFiscalModel.php';

class ResumenEconomicoController extends Controller
{
    public function index(): void
    {
        $this->requireCanView('resumen_economico');

        $modo = $this->input('modo', 'mes');

        if ($modo === 'mes') {
            $anio   = (int)$this->input('anio', date('Y'));
            // mes puede ser un array (mes[]) o un valor simple
            $mesRaw = isset($_GET['mes']) ? (array)$_GET['mes'] : [(int)date('m')];
            $meses  = array_values(array_unique(array_filter(
                array_map('intval', $mesRaw),
                fn($m) => $m >= 1 && $m <= 12
            )));
            if (empty($meses)) $meses = [(int)date('m')];
            sort($meses);
            $desde = sprintf('%04d-%02d-01', $anio, min($meses));
            $hasta = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, max($meses))));
        } elseif ($modo === 'anio') {
            $anio  = (int)$this->input('anio', date('Y'));
            $meses = null;
            $desde = "{$anio}-01-01";
            $hasta = "{$anio}-12-31";
        } else {
            $modo  = 'periodo';
            $desde = $this->input('desde', date('Y-m-01'));
            $hasta = $this->input('hasta', date('Y-m-d'));
            $anio  = null;
            $meses = null;
        }

        // Cantidad de meses cubiertos por el período filtrado, para prorratear el límite anual de monotributo
        if ($modo === 'mes') {
            $mesesEnPeriodo = count($meses);
        } elseif ($modo === 'anio') {
            $mesesEnPeriodo = 12;
        } else {
            $dDesde = new \DateTime($desde);
            $dHasta = new \DateTime($hasta);
            $mesesEnPeriodo = max(1, ($dHasta->format('Y') - $dDesde->format('Y')) * 12 + ($dHasta->format('n') - $dDesde->format('n')) + 1);
        }

        $gastoModel = new GastoModel();
        $pagoModel  = new PagoProveedorModel();
        $ingModel   = new IngresoModel();
        $ventaModel = new VentaModel();
        $fiscalModel = new ConfiguracionFiscalModel();

        $facturadoAfip = $ventaModel->facturadoAfipEnPeriodo($desde, $hasta);
        $configFiscal  = $fiscalModel->obtener();
        $categoriasMono = ConfiguracionFiscalModel::categorias();
        $catMono        = $configFiscal['categoria_monotributo'] ?? null;
        $limiteMonoAnual = $catMono ? ($categoriasMono[$catMono] ?? null) : null;

        // El límite de Monotributo se controla contra el ACUMULADO MÓVIL de los últimos 12
        // meses frente al límite anual completo (no un prorrateo mensual fijo) — es la regla
        // real de AFIP: facturar menos un mes deja margen para facturar más otro. Por eso
        // esta parte de la tarjeta es independiente del filtro de período de la página.
        $limiteMonoManual   = $configFiscal['limite_facturacion_manual'] ?? null;
        $limiteMonoEfectivo = $limiteMonoManual !== null ? (float)$limiteMonoManual : $limiteMonoAnual;
        $facturadoAfip12Meses = $ventaModel->facturadoAfipUltimos12Meses();
        $facturadoAfipPorMes  = $ventaModel->facturadoAfipPorMes(24);

        // Mismo cálculo que VentasController::pos() (disponible HOY = saldo a prorratear
        // menos lo ya facturado en el período, dividido los días que faltan para el
        // vencimiento) — se muestra acá para que el admin vea el mismo número que el cajero.
        $disponibleHoyProrateo = null;
        $diasRestantesProrateo = null;
        $saldoProratear = $configFiscal['saldo_prorateo_diario'] ?? null;
        $prorateoDesde  = $configFiscal['prorateo_desde'] ?? null;
        $prorateoHasta  = $configFiscal['prorateo_hasta'] ?? null;
        $hoyProrateo    = date('Y-m-d');
        if ($saldoProratear !== null && $prorateoDesde && $prorateoHasta && $hoyProrateo >= $prorateoDesde && $hoyProrateo <= $prorateoHasta) {
            $diasRestantesProrateo = (strtotime($prorateoHasta) - strtotime($hoyProrateo)) / 86400 + 1;
            if ($diasRestantesProrateo > 0) {
                $facturadoAcumProrateo = $ventaModel->facturadoAfipEnPeriodo($prorateoDesde, $hoyProrateo);
                $disponibleHoyProrateo = ((float)$saldoProratear - $facturadoAcumProrateo) / $diasRestantesProrateo;
            }
        }

        $er           = $gastoModel->estadoResultados($desde, $hasta);
        $mensual      = $gastoModel->resumenMensual($desde, $hasta);
        $porCategoria = $gastoModel->resumenPorCategoria($desde, $hasta);

        $aportesSocios      = $pagoModel->aportesSociosEnPeriodo($desde, $hasta);
        $totalAportes       = array_sum(array_column($aportesSocios, 'total'));
        $otrosIngresos      = $ingModel->findEnPeriodo($desde, $hasta);
        $totalOtrosIngresos = array_sum(array_column($otrosIngresos, 'monto'));
        $totalSueldos       = $pagoModel->totalSueldosEnPeriodo($desde, $hasta);
        $pendienteLiquidar  = (new EmpleadoModel())->getTotalPendienteLiquidarTodos();
        $sueldosPorEmpleado = $pagoModel->sueldosPorEmpleadoEnPeriodo($desde, $hasta);

        // Detalle de gastos individuales por categoría de costo fijo (Honorarios, Seguros,
        // etc.) — para el desplegable "cómo está compuesto" de cada fila del árbol de Egresos.
        $gastosPorCategoria = [];
        foreach ($porCategoria as $c) {
            if ($c['tipo'] !== 'costo_fijo' || (float)$c['total'] <= 0.009 || empty($c['categoria_id'])) continue;
            $gastosPorCategoria[(int)$c['categoria_id']] = $gastoModel->findAllFiltrado([
                'desde'        => $desde,
                'hasta'        => $hasta,
                'categoria_id' => (int)$c['categoria_id'],
            ]);
        }

        // Deuda total "a hoy" — independiente del filtro de período de la página (mismo
        // criterio que el cálculo de Monotributo más abajo): cuánto se le debe HOY a
        // proveedores (facturas + gastos pendientes, misma cuenta corriente de
        // /pagos-proveedores) más lo que falta liquidar de sueldos.
        $deudaProveedores = array_sum(array_column($pagoModel->getCuentaCorriente(), 'saldo'));
        $deudaTotalHoy    = $deudaProveedores + $pendienteLiquidar;

        // Los aportes de socios NO se cuentan como ingreso operativo: son financiamiento,
        // no ganancia. Se muestran aparte, debajo del Resultado Neto.
        // Los sueldos (Empleados/Nómina) sí son un costo real y se suman a Egresos,
        // aunque no pasen por facturas_compra ni por la tabla gastos.
        $totalIngresos = (float)$er['ventas'] + $totalOtrosIngresos;
        $totalEgresos  = (float)$er['totalEgresos'] + $totalSueldos;
        $resultadoNeto = $totalIngresos - $totalEgresos;

        $deficit       = $resultadoNeto < 0 ? abs($resultadoNeto) : 0.0;
        $coberturaPct  = $deficit > 0.009 ? round($totalAportes / $deficit * 100, 1) : null;

        $arbolIngresos    = $this->_arbolIngresos($er, $otrosIngresos, $totalOtrosIngresos, $totalIngresos, $facturadoAfip);
        $egresosCalculado = $this->_arbolEgresos($er, $porCategoria, $totalEgresos, $totalSueldos, $pendienteLiquidar, $sueldosPorEmpleado, $gastosPorCategoria);

        $this->render('resumen-economico.index', [
            'pageTitle'       => 'Resumen Económico',
            'breadcrumb'      => ['Finanzas' => null, 'Resumen Económico' => null],
            'er'              => $er,
            'mensual'         => $mensual,
            'porCategoria'    => $porCategoria,
            'modo'            => $modo,
            'desde'           => $desde,
            'hasta'           => $hasta,
            'anio'            => $anio,
            'meses'           => $meses,
            'arbolIngresos'   => $arbolIngresos,
            'arbolEgresos'    => $egresosCalculado['arbol'],
            'egresoVar'       => $egresosCalculado['egresoVar'],
            'egresoFijo'      => $egresosCalculado['egresoFijo'],
            'egresoServicios' => $egresosCalculado['egresoServicios'],
            'egresoImp'       => $egresosCalculado['egresoImp'],
            'totalIngresos'   => $totalIngresos,
            'totalEgresos'    => $totalEgresos,
            'resultadoNeto'   => $resultadoNeto,
            'otrosIngresos'   => $otrosIngresos,
            'aportesSocios'   => $aportesSocios,
            'totalAportes'    => $totalAportes,
            'deficit'         => $deficit,
            'coberturaPct'    => $coberturaPct,
            'pendienteLiquidar' => $pendienteLiquidar,
            'deudaProveedores'  => $deudaProveedores,
            'deudaTotalHoy'     => $deudaTotalHoy,
            'facturadoAfip'      => $facturadoAfip,
            'mesesEnPeriodo'     => $mesesEnPeriodo,
            'categoriasMono'     => $categoriasMono,
            'configFiscal'       => $configFiscal,
            'limiteMonoAnual'      => $limiteMonoAnual,
            'limiteMonoManual'     => $limiteMonoManual,
            'limiteMonoEfectivo'   => $limiteMonoEfectivo,
            'facturadoAfip12Meses' => $facturadoAfip12Meses,
            'facturadoAfipPorMes'  => $facturadoAfipPorMes,
            'disponibleHoyProrateo' => $disponibleHoyProrateo,
            'diasRestantesProrateo' => $diasRestantesProrateo,
        ]);
    }

    /** AJAX/POST: registrar un "otro ingreso" */
    public function agregarIngreso(): void
    {
        $this->requireCanEdit('resumen_economico');
        Csrf::check();

        $fecha       = $this->input('fecha', '');
        $descripcion = trim($this->input('descripcion', ''));
        $monto       = (float)$this->input('monto', 0);
        $notas       = $this->input('notas', '') ?: null;

        if (!$fecha || !$descripcion || $monto <= 0) {
            $this->json(['ok' => false, 'error' => 'Completá fecha, descripción y un monto mayor a cero.'], 422);
        }

        $id = (new IngresoModel())->agregar($fecha, $descripcion, $monto, $notas, (int)Session::user()['id']);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Ingreso registrado.']);
    }

    public function eliminarIngreso(): void
    {
        $this->requireCanEdit('resumen_economico');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        (new IngresoModel())->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Ingreso eliminado.']);
    }

    /** Arma el árbol de Ingresos operativos: Ventas / Otros ingresos (los aportes de socios van aparte, no son ingreso operativo) */
    private function _arbolIngresos(array $er, array $otrosIngresos, float $totalOtros, float $totalIngresos, float $facturadoAfip = 0.0): array
    {
        $hijosOtros = array_map(
            fn($o) => ['id' => 'otro_' . $o['id'], 'label' => $o['descripcion'] . ' (' . fecha($o['fecha']) . ')', 'monto' => (float)$o['monto']],
            $otrosIngresos
        );

        $hijosVentas = [];
        if ($facturadoAfip > 0.009) {
            $hijosVentas[] = ['id' => 'ing_ventas_afip', 'label' => 'Facturado por AFIP', 'monto' => $facturadoAfip];
        }

        return [
            'id' => 'ingresos', 'label' => 'Ingresos', 'monto' => $totalIngresos, 'color' => 'success',
            'hijos' => [
                ['id' => 'ing_ventas', 'label' => 'Ingresos por Ventas', 'monto' => (float)$er['ventas'], 'hijos' => $hijosVentas],
                ['id' => 'ing_otros', 'label' => 'Otros Ingresos', 'monto' => $totalOtros, 'hijos' => $hijosOtros],
            ],
        ];
    }

    /**
     * Arma el árbol de Egresos:
     *  - Costos Variables → Compras / Gastos
     *  - Costos Fijos → Sueldos (Empleados/Nómina + tabla gastos combinados) / Alquiler / otras categorías costo_fijo / Compras
     *    (Servicios se separa aparte, no cuenta como parte de Costos Fijos)
     *  - Servicios → fila propia dentro de Egresos
     *  - Impuestos → total acumulado, sin desglose
     *
     * Cada nodo lleva además 'pagado' y 'pendiente' (basado en el estado de facturas_compra
     * y gastos) para poder ver, de cada ítem, cuánto ya se pagó y cuánto falta. Los sueldos de
     * Empleados/Nómina son pagos ya ejecutados (no tienen "pendiente" dentro del período); la
     * plata sin liquidar todavía se informa aparte en $pendienteLiquidar (no está acotada al
     * período filtrado, es "cuánto se les debe a los empleados hoy").
     *
     * $sueldosPorEmpleado (de PagoProveedorModel::sueldosPorEmpleadoEnPeriodo) y
     * $gastosPorCategoria (categoria_id => filas de GastoModel::findAllFiltrado) arman los
     * 'hijos' de "Sueldos" y de cada categoría de costo fijo/servicio, para que se puedan
     * desplegar y ver de qué está compuesto cada total.
     */
    private function _arbolEgresos(
        array $er,
        array $porCategoria,
        float $totalEgresos,
        float $totalSueldosNomina = 0.0,
        float $pendienteLiquidar = 0.0,
        array $sueldosPorEmpleado = [],
        array $gastosPorCategoria = []
    ): array {
        $categoriasFijo = array_filter($porCategoria, fn($c) => $c['tipo'] === 'costo_fijo');

        $hijosGastoDeCategoria = function (array $c) use ($gastosPorCategoria): array {
            $filas = $gastosPorCategoria[(int)($c['categoria_id'] ?? 0)] ?? [];
            return array_map(fn($g) => [
                'id'        => 'gasto_' . $g['id'],
                'label'     => $g['descripcion'] . ($g['proveedor_nombre'] ? ' — ' . $g['proveedor_nombre'] : '') . ' (' . fecha($g['fecha']) . ')',
                'monto'     => (float)$g['monto'],
                'pagado'    => $g['estado'] === 'pagado'    ? (float)$g['monto'] : 0.0,
                'pendiente' => $g['estado'] === 'pendiente' ? (float)$g['monto'] : 0.0,
            ], $filas);
        };

        $servicios = $serviciosPagado = 0.0;
        $hijosServicios = [];
        $sueldos       = $totalSueldosNomina;
        $sueldosPagado = $totalSueldosNomina; // pagos de nómina ya registrados = siempre pagados
        $hijosFijos = [];
        foreach ($categoriasFijo as $c) {
            $total = (float)$c['total'];
            if ($total <= 0.009) continue;
            $pagado    = (float)($c['pagado']    ?? 0);
            $pendiente = (float)($c['pendiente'] ?? 0);
            if (stripos($c['categoria'], 'servicio') !== false) {
                $servicios       += $total;
                $serviciosPagado += $pagado;
                $hijosServicios   = array_merge($hijosServicios, $hijosGastoDeCategoria($c));
            } elseif (stripos($c['categoria'], 'sueldo') !== false) {
                $sueldos       += $total;
                $sueldosPagado += $pagado;
            } else {
                $hijosFijos[] = [
                    'id' => 'cat_fijo_' . md5($c['categoria']), 'label' => $c['categoria'], 'monto' => $total,
                    'pagado' => $pagado, 'pendiente' => $pendiente,
                    'hijos' => $hijosGastoDeCategoria($c),
                ];
            }
        }
        $serviciosPendiente = round($servicios - $serviciosPagado, 2);
        $sueldosPendiente   = round($sueldos - $sueldosPagado, 2);

        if ($sueldos > 0.009) {
            $hijosSueldos = array_map(fn($s) => [
                'id'    => 'sueldo_emp_' . $s['empleado_id'],
                'label' => $s['nombre'],
                'monto' => (float)$s['total'],
            ], $sueldosPorEmpleado);
            array_unshift($hijosFijos, [
                'id' => 'eg_fijo_sueldos', 'label' => 'Sueldos', 'monto' => $sueldos,
                'pagado' => round($sueldosPagado, 2), 'pendiente' => $sueldosPendiente,
                'pendienteLiquidar' => $pendienteLiquidar,
                'hijos' => $hijosSueldos,
            ]);
        }
        if ((float)$er['comprasFijo'] > 0.009) {
            $hijosFijos[] = [
                'id' => 'eg_fijo_compras', 'label' => 'Compras', 'monto' => (float)$er['comprasFijo'],
                'pagado' => (float)$er['comprasFijoPagado'], 'pendiente' => (float)$er['comprasFijoPendiente'],
            ];
        }

        $egresoVar          = (float)$er['comprasVar']  + (float)$er['gastosVariables'];
        $egresoVarPagado    = (float)$er['comprasVarPagado'] + (float)$er['gastosVariablesPagado'];
        $egresoVarPendiente = round($egresoVar - $egresoVarPagado, 2);

        $egresoFijo          = (float)$er['comprasFijo'] + (float)$er['gastosFijos'] - $servicios + $totalSueldosNomina;
        $egresoFijoPagado    = array_sum(array_column($hijosFijos, 'pagado'));
        $egresoFijoPendiente = round($egresoFijo - $egresoFijoPagado, 2);

        $egresoImp          = (float)$er['comprasImp'] + (float)$er['impuestos'];
        $egresoImpPagado    = (float)$er['comprasImpPagado'] + (float)$er['impuestosPagado'];
        $egresoImpPendiente = round($egresoImp - $egresoImpPagado, 2);

        $totalPagado    = $egresoVarPagado + $egresoFijoPagado + $serviciosPagado + $egresoImpPagado;
        $totalPendiente = round($totalEgresos - $totalPagado, 2);

        $arbol = [
            'id' => 'egresos', 'label' => 'Egresos', 'monto' => $totalEgresos, 'color' => 'danger',
            'pagado' => round($totalPagado, 2), 'pendiente' => $totalPendiente,
            'hijos' => [
                [
                    'id' => 'eg_var', 'label' => 'Costos Variables', 'monto' => $egresoVar,
                    'pagado' => round($egresoVarPagado, 2), 'pendiente' => $egresoVarPendiente,
                    'hijos' => [
                        ['id' => 'eg_var_compras', 'label' => 'Compras', 'monto' => (float)$er['comprasVar'],
                         'pagado' => (float)$er['comprasVarPagado'], 'pendiente' => (float)$er['comprasVarPendiente']],
                        ['id' => 'eg_var_gastos',  'label' => 'Gastos',  'monto' => (float)$er['gastosVariables'],
                         'pagado' => (float)$er['gastosVariablesPagado'], 'pendiente' => (float)$er['gastosVariablesPendiente']],
                    ],
                ],
                [
                    'id' => 'eg_fijo', 'label' => 'Costos Fijos', 'monto' => $egresoFijo,
                    'pagado' => round($egresoFijoPagado, 2), 'pendiente' => $egresoFijoPendiente,
                    'hijos' => $hijosFijos,
                ],
                ['id' => 'eg_servicios', 'label' => 'Servicios', 'monto' => $servicios,
                 'pagado' => round($serviciosPagado, 2), 'pendiente' => $serviciosPendiente,
                 'hijos' => $hijosServicios],
                ['id' => 'eg_imp', 'label' => 'Impuestos', 'monto' => $egresoImp,
                 'pagado' => round($egresoImpPagado, 2), 'pendiente' => $egresoImpPendiente],
            ],
        ];

        return [
            'arbol'           => $arbol,
            'egresoVar'       => $egresoVar,
            'egresoFijo'      => $egresoFijo,
            'egresoServicios' => $servicios,
            'egresoImp'       => $egresoImp,
        ];
    }
}
