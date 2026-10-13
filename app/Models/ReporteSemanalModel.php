<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ReporteSemanalModel extends Model
{
    /** Arma el resumen semanal: compras (pagadas/pendientes), ventas por método de pago, pagos reales y margen bruto */
    public function generarResumen(string $desde, string $hasta): array
    {
        return [
            'desde'   => $desde,
            'hasta'   => $hasta,
            'compras' => $this->resumenCompras($desde, $hasta),
            'ventas'  => $this->resumenVentas($desde, $hasta),
            'pagos'   => $this->resumenPagos($desde, $hasta),
            'margen'  => $this->margenBruto($desde, $hasta),
        ];
    }

    /**
     * Plata que efectivamente salió de caja en el período (cualquier tipo: proveedor,
     * sueldo, otro) — distinto de "compras" arriba, que es lo facturado/adeudado, no lo
     * pagado. Ver PagoProveedorModel::totalPagadoEnPeriodo().
     */
    private function resumenPagos(string $desde, string $hasta): array
    {
        require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
        $r = (new PagoProveedorModel())->totalPagadoEnPeriodo($desde, $hasta);
        return ['total' => (float) $r['total'], 'cantidad' => (int) $r['cantidad']];
    }

    private function resumenCompras(string $desde, string $hasta): array
    {
        $filas = $this->queryAll(
            "SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(total_con_iva),0) AS total
             FROM facturas_compra
             WHERE eliminado = 0 AND estado != 'anulada' AND fecha BETWEEN :d AND :h
             GROUP BY estado",
            [':d' => $desde, ':h' => $hasta]
        );

        $pagadas    = ['cantidad' => 0, 'total' => 0.0];
        $pendientes = ['cantidad' => 0, 'total' => 0.0];
        foreach ($filas as $f) {
            $dato = ['cantidad' => (int)$f['cantidad'], 'total' => (float)$f['total']];
            if ($f['estado'] === 'pagada') {
                $pagadas = $dato;
            } else {
                $pendientes = $dato;
            }
        }

        return [
            'total'      => $pagadas['total'] + $pendientes['total'],
            'pagadas'    => $pagadas,
            'pendientes' => $pendientes,
        ];
    }

    private function resumenVentas(string $desde, string $hasta): array
    {
        // Se suma directo sobre las columnas monto_* (no se agrupa por metodo_pago) para
        // que una venta pagada con varios medios a la vez reparta su importe entre los
        // medios reales, en vez de caer entera en un solo bucket "combinado".
        $totales = $this->queryOne(
            "SELECT COUNT(*) AS cantidad,
                    COALESCE(SUM(total_con_iva),      0) AS total,
                    COALESCE(SUM(monto_efectivo),      0) AS efectivo,
                    COALESCE(SUM(monto_debito),        0) AS debito,
                    COALESCE(SUM(monto_credito),       0) AS credito,
                    COALESCE(SUM(monto_transferencia), 0) AS transferencia,
                    COALESCE(SUM(monto_mercadopago),   0) AS mercadopago
             FROM ventas
             WHERE eliminado = 0 AND estado = 'completada' AND DATE(created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        ) ?: [];

        $porMetodo = [];
        foreach (['efectivo', 'debito', 'credito', 'transferencia', 'mercadopago'] as $medio) {
            $total = (float)($totales[$medio] ?? 0);
            if ($total > 0.009) $porMetodo[] = ['metodo_pago' => $medio, 'total' => $total];
        }
        usort($porMetodo, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total'      => (float)($totales['total']    ?? 0),
            'cantidad'   => (int)($totales['cantidad']   ?? 0),
            'por_metodo' => $porMetodo,
        ];
    }

    /**
     * Margen bruto: ingresos por ventas (sin IVA) menos costo de mercadería vendida
     * (precio_costo de productos). Excluye las filas hijas de promo, que duplicarían el
     * costo de los componentes — ver la nota canónica en ReportesModel::margenVentasPorProducto().
     */
    private function margenBruto(string $desde, string $hasta): array
    {
        $fila = $this->queryOne(
            "SELECT
                COALESCE(SUM(vd.subtotal), 0) AS ingresos,
                COALESCE(SUM(vd.cantidad * p.precio_costo * (1 + p.desvio_pct/100)), 0) AS costo
             FROM ventas_detalle vd
             JOIN ventas v     ON v.id = vd.venta_id
             JOIN productos p  ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        ) ?: ['ingresos' => 0, 'costo' => 0];

        $ingresos = (float)$fila['ingresos'];
        $costo    = (float)$fila['costo'];
        $margen   = $ingresos - $costo;

        return [
            'ingresos'   => $ingresos,
            'costo'      => $costo,
            'margen'     => $margen,
            'margen_pct' => $ingresos > 0.009 ? round($margen / $ingresos * 100, 1) : 0.0,
        ];
    }
}
