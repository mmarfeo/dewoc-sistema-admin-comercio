<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/GastoModel.php';
require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';

/**
 * "Separación de Ingresos": de lo vendido en un período, cuánto corresponde
 * separar para Recompra (MP), Impuestos, Servicios y Sueldos, y cuánto queda
 * Disponible (ganancia real). Los % se calculan sobre un período de referencia
 * (los últimos N meses completos) y se aplican a las ventas del período filtrado.
 */
class SeparacionIngresosModel extends Model
{
    /** Fragmento SQL reutilizado: costo de MP en vivo (receta + precio actual de insumo/MP/genérico) por producto */
    private function sqlCostoMpVivo(): string
    {
        return "LEFT JOIN (
                 SELECT pr.producto_id,
                        COALESCE(SUM(
                            rd.cantidad
                            * COALESCE(
                                mp.costo_unitario * (1 + mp.desvio_pct/100),
                                ins.costo_unitario, gen.costo, 0
                            )
                            * (1 + rd.merma_pct / 100)
                        ), 0) AS costo_mp
                 FROM producto_receta pr
                 LEFT JOIN receta_detalle rd ON rd.receta_id = pr.receta_id
                 LEFT JOIN materias_primas mp  ON mp.id  = rd.materia_prima_id AND rd.tipo_ingrediente = 'materia_prima'
                 LEFT JOIN insumos         ins ON ins.id = rd.insumo_id        AND rd.tipo_ingrediente = 'insumo'
                 LEFT JOIN (
                     SELECT ingrediente_generico_id,
                            CASE WHEN SUM(stock_actual) > 0.0001
                                 THEN SUM((costo_unitario * (1 + desvio_pct/100)) * stock_actual) / SUM(stock_actual)
                                 ELSE AVG(costo_unitario * (1 + desvio_pct/100))
                            END AS costo
                     FROM materias_primas
                     WHERE eliminado = 0 AND ingrediente_generico_id IS NOT NULL
                     GROUP BY ingrediente_generico_id
                 ) gen ON gen.ingrediente_generico_id = rd.ingrediente_generico_id
                 GROUP BY pr.producto_id
             ) rc ON rc.producto_id = p.id";
    }

    /** Costo unitario "vivo": receta (elaborado) o precio_costo actualizado por compra (reventa) */
    private function sqlCostoUnitarioVivo(): string
    {
        return "CASE WHEN p.tipo = 'elaborado' THEN COALESCE(rc.costo_mp, 0) ELSE COALESCE(p.precio_costo, 0) END";
    }

    /**
     * Calcula, sobre los $meses meses completos anteriores al mes de $hastaRef,
     * cuánto representó cada categoría (Recompra/Impuestos/Servicios/Sueldos)
     * sobre el total vendido, en $ y en %.
     */
    public function calcularReferencia(string $hastaRef, int $meses = 3): array
    {
        $primerDiaMesActual = new \DateTime(date('Y-m-01', strtotime($hastaRef)));
        $hastaRefVentana     = (clone $primerDiaMesActual)->modify('-1 day')->format('Y-m-d');
        $desdeRefVentana     = (clone $primerDiaMesActual)->modify("-{$meses} months")->format('Y-m-d');

        $totalVentas = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva), 0) FROM ventas
             WHERE eliminado = 0 AND estado = 'completada' AND DATE(created_at) BETWEEN :d AND :h",
            [':d' => $desdeRefVentana, ':h' => $hastaRefVentana]
        );

        // Las filas hijas de promo quedan afuera: el costo de los componentes ya viene
        // contado en el precio_costo de la promo — ver la nota canónica en
        // ReportesModel::margenVentasPorProducto().
        $costoUnitario = $this->sqlCostoUnitarioVivo();
        $totalRecompra = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(vd.cantidad * ({$costoUnitario})), 0)
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             {$this->sqlCostoMpVivo()}
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :d AND :h",
            [':d' => $desdeRefVentana, ':h' => $hastaRefVentana]
        );

        $gastoModel = new GastoModel();
        $pagoModel  = new PagoProveedorModel();

        $er           = $gastoModel->estadoResultados($desdeRefVentana, $hastaRefVentana);
        $porCategoria = $gastoModel->resumenPorCategoria($desdeRefVentana, $hastaRefVentana);
        $sueldosNomina = $pagoModel->totalSueldosEnPeriodo($desdeRefVentana, $hastaRefVentana);

        $servicios = 0.0; $sueldosCat = 0.0; $alquiler = 0.0;
        $desgloseServicios = []; $desgloseSueldos = []; $desgloseAlquiler = [];
        foreach ($porCategoria as $c) {
            if ($c['tipo'] !== 'costo_fijo') continue;
            $total = (float)$c['total'];
            if ($total <= 0.009) continue;
            if (stripos($c['categoria'], 'servicio') !== false) {
                $servicios += $total;
                $desgloseServicios[] = ['concepto' => $c['categoria'], 'monto' => $total];
            } elseif (stripos($c['categoria'], 'sueldo') !== false) {
                $sueldosCat += $total;
                $desgloseSueldos[] = ['concepto' => $c['categoria'], 'monto' => $total];
            } elseif (stripos($c['categoria'], 'alquiler') !== false) {
                $alquiler += $total;
                $desgloseAlquiler[] = ['concepto' => $c['categoria'], 'monto' => $total];
            }
        }
        if ($sueldosNomina > 0.009) {
            array_unshift($desgloseSueldos, ['concepto' => 'Empleados / Nómina', 'monto' => $sueldosNomina]);
        }

        $totalSueldos   = $sueldosNomina + $sueldosCat;
        $totalImpuestos = (float)$er['impuestos'] + (float)$er['comprasImp'];

        $desgloseImpuestos = [];
        if ((float)$er['impuestos'] > 0.009) {
            $desgloseImpuestos[] = ['concepto' => 'Gastos categoría Impuesto', 'monto' => (float)$er['impuestos']];
        }
        if ((float)$er['comprasImp'] > 0.009) {
            $desgloseImpuestos[] = ['concepto' => 'Compras con destino Impuesto', 'monto' => (float)$er['comprasImp']];
        }

        $desgloseRecompra = array_map(
            fn($p) => ['concepto' => $p['nombre'], 'monto' => (float)$p['costo_total']],
            $this->detalleRecompraPorProducto($desdeRefVentana, $hastaRefVentana)
        );

        $sinDatos = $totalVentas <= 0.009;
        $pct = null;
        if (!$sinDatos) {
            $pct = [
                'recompra'  => round($totalRecompra   / $totalVentas * 100, 2),
                'impuestos' => round($totalImpuestos   / $totalVentas * 100, 2),
                'servicios' => round($servicios        / $totalVentas * 100, 2),
                'sueldos'   => round($totalSueldos     / $totalVentas * 100, 2),
                'alquiler'  => round($alquiler         / $totalVentas * 100, 2),
            ];
            $pct['disponible'] = round(100 - $pct['recompra'] - $pct['impuestos'] - $pct['servicios'] - $pct['sueldos'] - $pct['alquiler'], 2);
        }

        return [
            'desde'             => $desdeRefVentana,
            'hasta'             => $hastaRefVentana,
            'meses'             => $meses,
            'sin_datos'         => $sinDatos,
            'total_ventas'      => $totalVentas,
            'total_recompra'    => $totalRecompra,
            'total_impuestos'   => $totalImpuestos,
            'total_servicios'   => $servicios,
            'total_sueldos'     => $totalSueldos,
            'total_alquiler'    => $alquiler,
            'porcentajes'       => $pct,
            'desglose_recompra' => $desgloseRecompra,
            'desglose_impuestos'=> $desgloseImpuestos,
            'desglose_servicios'=> $desgloseServicios,
            'desglose_sueldos'  => $desgloseSueldos,
            'desglose_alquiler' => $desgloseAlquiler,
        ];
    }

    /** Ventas del período agrupadas por día, semana o mes */
    public function ventasAgrupadas(string $desde, string $hasta, string $agrupacion): array
    {
        switch ($agrupacion) {
            case 'semana':
                $periodoKey = 'YEARWEEK(created_at, 1)';
                break;
            case 'mes':
                $periodoKey = "DATE_FORMAT(created_at, '%Y-%m')";
                break;
            default:
                $agrupacion = 'dia';
                $periodoKey = 'DATE(created_at)';
        }

        $filas = $this->queryAll(
            "SELECT {$periodoKey} AS periodo_key,
                    MIN(DATE(created_at)) AS periodo_desde,
                    MAX(DATE(created_at)) AS periodo_hasta,
                    COUNT(*) AS cant_ventas,
                    COALESCE(SUM(total_con_iva), 0) AS total
             FROM ventas
             WHERE eliminado = 0 AND estado = 'completada'
               AND DATE(created_at) BETWEEN :d AND :h
             GROUP BY periodo_key
             ORDER BY periodo_key",
            [':d' => $desde, ':h' => $hasta]
        );

        $mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        foreach ($filas as &$f) {
            if ($agrupacion === 'semana') {
                $f['label'] = fecha($f['periodo_desde']) . ' – ' . fecha($f['periodo_hasta']);
            } elseif ($agrupacion === 'mes') {
                [$anio, $mes] = explode('-', $f['periodo_key']);
                $f['label'] = $mesesEs[(int)$mes - 1] . ' ' . $anio;
            } else {
                $f['label'] = fecha($f['periodo_desde']);
            }
        }
        unset($f);

        return $filas;
    }

    /** Aplica los % de referencia a cada fila de ventas agrupadas (cuánto separar por período) */
    public function aplicarAPeriodo(array $filasVentas, ?array $porcentajes): array
    {
        $tieneDatos = $porcentajes !== null;
        $totales = ['ventas' => 0.0, 'recompra' => 0.0, 'impuestos' => 0.0, 'servicios' => 0.0, 'sueldos' => 0.0, 'alquiler' => 0.0, 'disponible' => 0.0];
        $filas = [];

        foreach ($filasVentas as $f) {
            $ventas = (float)$f['total'];
            $separar = [
                'recompra'  => $tieneDatos ? round($ventas * $porcentajes['recompra']  / 100, 2) : 0.0,
                'impuestos' => $tieneDatos ? round($ventas * $porcentajes['impuestos'] / 100, 2) : 0.0,
                'servicios' => $tieneDatos ? round($ventas * $porcentajes['servicios'] / 100, 2) : 0.0,
                'sueldos'   => $tieneDatos ? round($ventas * $porcentajes['sueldos']   / 100, 2) : 0.0,
                'alquiler'  => $tieneDatos ? round($ventas * $porcentajes['alquiler']  / 100, 2) : 0.0,
            ];
            $separar['disponible'] = round($ventas - $separar['recompra'] - $separar['impuestos'] - $separar['servicios'] - $separar['sueldos'] - $separar['alquiler'], 2);

            $filas[] = [
                'periodo_key' => $f['periodo_key'],
                'label'       => $f['label'],
                'cant_ventas' => (int)$f['cant_ventas'],
                'ventas'      => $ventas,
                'separar'     => $separar,
            ];

            $totales['ventas']     += $ventas;
            $totales['recompra']   += $separar['recompra'];
            $totales['impuestos']  += $separar['impuestos'];
            $totales['servicios']  += $separar['servicios'];
            $totales['sueldos']    += $separar['sueldos'];
            $totales['alquiler']   += $separar['alquiler'];
            $totales['disponible'] += $separar['disponible'];
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 2);
        }

        return ['filas' => $filas, 'totales' => $totales];
    }

    /**
     * Detalle de Recompra: costo de MP en vivo por producto vendido en el período filtrado.
     * Excluye las filas hijas de promo, que duplicarían el costo de los componentes — ver la
     * nota canónica en ReportesModel::margenVentasPorProducto().
     */
    public function detalleRecompraPorProducto(string $desde, string $hasta): array
    {
        $costoUnitario = $this->sqlCostoUnitarioVivo();

        return $this->queryAll(
            "SELECT p.id, p.nombre,
                    SUM(vd.cantidad) AS unidades_vendidas,
                    ({$costoUnitario}) AS costo_unitario,
                    SUM(vd.cantidad) * ({$costoUnitario}) AS costo_total
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             {$this->sqlCostoMpVivo()}
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :d AND :h
             GROUP BY p.id, p.nombre, p.tipo, rc.costo_mp, p.precio_costo
             HAVING costo_total > 0.009
             ORDER BY costo_total DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }
}
