<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ReportesModel extends Model
{
    protected string $table = 'ventas';

    // ── Ventas ────────────────────────────────────────────────────────────────

    public function ventasPorPeriodo(string $desde, string $hasta, array $filtros = []): array
    {
        $where  = ["v.eliminado = 0 AND v.estado = 'completada'", "DATE(v.created_at) BETWEEN :desde AND :hasta"];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if (!empty($filtros['metodo_pago'])) {
            $where[]               = 'v.metodo_pago = :mp';
            $params[':mp']         = $filtros['metodo_pago'];
        }
        if (!empty($filtros['usuario_id'])) {
            $where[]               = 'v.usuario_id = :uid';
            $params[':uid']        = (int)$filtros['usuario_id'];
        }

        return $this->queryAll(
            'SELECT v.id, v.created_at, u.nombre AS cajero,
                    v.total_con_iva, v.metodo_pago, v.descuento_global,
                    v.total_iva,
                    (SELECT COUNT(*) FROM ventas_detalle vd WHERE vd.venta_id = v.id) AS items
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY v.created_at DESC',
            $params
        );
    }

    public function resumenVentas(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT
                COUNT(*) AS total_tickets,
                COALESCE(SUM(total_con_iva), 0) AS total_bruto,
                COALESCE(SUM(total_iva), 0) AS total_iva,
                COALESCE(AVG(total_con_iva), 0) AS ticket_promedio,
                COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo'       THEN total_con_iva ELSE 0 END), 0) AS efectivo,
                COALESCE(SUM(CASE WHEN metodo_pago = 'debito'         THEN total_con_iva ELSE 0 END), 0) AS debito,
                COALESCE(SUM(CASE WHEN metodo_pago = 'credito'        THEN total_con_iva ELSE 0 END), 0) AS credito,
                COALESCE(SUM(CASE WHEN metodo_pago = 'transferencia'  THEN total_con_iva ELSE 0 END), 0) AS transferencia,
                COALESCE(SUM(CASE WHEN metodo_pago = 'mercadopago'    THEN total_con_iva ELSE 0 END), 0) AS mercadopago
             FROM ventas
             WHERE eliminado = 0 AND estado = 'completada'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        ) ?: [];
    }

    public function topProductos(string $desde, string $hasta, int $limit = 10): array
    {
        return $this->queryAll(
            'SELECT p.nombre, p.codigo,
                    SUM(vd.cantidad) AS cantidad_vendida,
                    SUM(vd.subtotal_con_iva) AS total_vendido
             FROM ventas_detalle vd
             JOIN ventas v ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = \'completada\'
               AND DATE(v.created_at) BETWEEN ? AND ?
             GROUP BY p.id, p.nombre, p.codigo
             ORDER BY total_vendido DESC
             LIMIT ' . (int)$limit,
            [$desde, $hasta]
        );
    }

    public function ventasPorDia(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT DATE(created_at) AS fecha,
                    COUNT(*) AS tickets,
                    SUM(total_con_iva) AS total
             FROM ventas
             WHERE eliminado = 0 AND estado = 'completada'
               AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY fecha",
            [$desde, $hasta]
        );
    }

    // ── Compras ───────────────────────────────────────────────────────────────

    public function comprasPorPeriodo(string $desde, string $hasta, array $filtros = []): array
    {
        $where  = ["fc.eliminado = 0 AND fc.estado != 'anulada'", 'fc.fecha BETWEEN :desde AND :hasta'];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if (!empty($filtros['proveedor_id'])) {
            $where[]                  = 'fc.proveedor_id = :prov';
            $params[':prov']          = (int)$filtros['proveedor_id'];
        }
        if (!empty($filtros['tipo_comprobante'])) {
            $where[]                  = 'fc.tipo_comprobante = :tipo';
            $params[':tipo']          = $filtros['tipo_comprobante'];
        }

        return $this->queryAll(
            'SELECT fc.id, fc.fecha, fc.nro_comprobante, fc.tipo_comprobante,
                    fc.condicion_pago, fc.total_con_iva, fc.total_iva, fc.estado,
                    p.razon_social AS proveedor
             FROM facturas_compra fc
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY fc.fecha DESC',
            $params
        );
    }

    public function resumenCompras(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT
                COUNT(*) AS total_facturas,
                COALESCE(SUM(total_con_iva), 0) AS total_bruto,
                COALESCE(SUM(total_iva), 0) AS total_iva,
                COALESCE(AVG(total_con_iva), 0) AS promedio,
                COALESCE(SUM(CASE WHEN condicion_pago = 'contado'          THEN total_con_iva ELSE 0 END), 0) AS contado,
                COALESCE(SUM(CASE WHEN condicion_pago = 'cuenta_corriente' THEN total_con_iva ELSE 0 END), 0) AS cuenta_corriente
             FROM facturas_compra
             WHERE eliminado = 0 AND estado != 'anulada'
               AND fecha BETWEEN ? AND ?",
            [$desde, $hasta]
        ) ?: [];
    }

    public function comprasPorProveedor(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT p.razon_social AS proveedor,
                    COUNT(*) AS facturas,
                    SUM(fc.total_con_iva) AS total
             FROM facturas_compra fc
             JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE fc.eliminado = 0 AND fc.estado != 'anulada'
               AND fc.fecha BETWEEN ? AND ?
             GROUP BY p.id, p.razon_social
             ORDER BY total DESC",
            [$desde, $hasta]
        );
    }

    // ── Stock ─────────────────────────────────────────────────────────────────

    public function movimientosStock(string $desde, string $hasta, array $filtros = []): array
    {
        $where  = ["DATE(sm.created_at) BETWEEN :desde AND :hasta"];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if (!empty($filtros['tipo_item'])) {
            $where[]           = 'sm.tipo_item = :tipo';
            $params[':tipo']   = $filtros['tipo_item'];
        }
        if (!empty($filtros['origen'])) {
            $where[]           = 'sm.origen = :origen';
            $params[':origen'] = $filtros['origen'];
        }

        return $this->queryAll(
            'SELECT sm.created_at, sm.tipo_item, sm.origen,
                    sm.cantidad, sm.stock_antes AS cantidad_anterior, sm.stock_despues AS cantidad_nueva,
                    sm.motivo, u.nombre AS usuario,
                    CASE sm.tipo_item
                        WHEN \'producto\'      THEN p.nombre
                        WHEN \'materia_prima\' THEN mp.nombre
                        WHEN \'insumo\'        THEN ins.nombre
                    END AS item_nombre
             FROM stock_movimientos sm
             LEFT JOIN usuarios u  ON u.id   = sm.usuario_id
             LEFT JOIN productos p ON p.id   = sm.item_id AND sm.tipo_item = \'producto\'
             LEFT JOIN materias_primas mp ON mp.id = sm.item_id AND sm.tipo_item = \'materia_prima\'
             LEFT JOIN insumos ins ON ins.id = sm.item_id AND sm.tipo_item = \'insumo\'
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY sm.created_at DESC
             LIMIT 500',
            $params
        );
    }

    // ── Caja ─────────────────────────────────────────────────────────────────

    public function turnosPorPeriodo(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT t.numero, t.apertura_at, t.cierre_at,
                    t.monto_inicial, t.monto_cierre_declarado, t.monto_cierre_calculado,
                    t.diferencia, t.estado, u.nombre AS cajero,
                    COALESCE(SUM(CASE WHEN cm.tipo IN ('ingreso','apertura') THEN cm.monto ELSE 0 END), 0) AS total_ingresos,
                    COALESCE(SUM(CASE WHEN cm.tipo IN ('egreso') THEN cm.monto ELSE 0 END), 0) AS total_egresos
             FROM turnos t
             LEFT JOIN usuarios u ON u.id = t.usuario_id
             LEFT JOIN caja_movimientos cm ON cm.turno_id = t.id
             WHERE t.eliminado = 0
               AND DATE(t.apertura_at) BETWEEN ? AND ?
             GROUP BY t.id
             ORDER BY t.apertura_at DESC",
            [$desde, $hasta]
        );
    }

    public function resumenCaja(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT
                COUNT(*) AS total_turnos,
                COALESCE(SUM(monto_cierre_calculado), 0) AS total_calculado,
                COALESCE(SUM(monto_cierre_declarado), 0) AS total_declarado,
                COALESCE(SUM(diferencia), 0) AS diferencia_total
             FROM turnos
             WHERE eliminado = 0 AND estado = 'cerrado'
               AND DATE(apertura_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        ) ?: [];
    }

    // ── Producción ────────────────────────────────────────────────────────────

    public function produccionPorPeriodo(string $desde, string $hasta, array $filtros = []): array
    {
        $where  = ["op.estado = 'completada'", "DATE(op.created_at) BETWEEN :desde AND :hasta"];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if (!empty($filtros['receta_id'])) {
            $where[]        = 'op.receta_id = :rid';
            $params[':rid'] = (int)$filtros['receta_id'];
        }

        return $this->queryAll(
            'SELECT op.id, op.created_at, op.cantidad_producida, op.estado,
                    r.nombre AS receta_nombre,
                    p.nombre AS producto_nombre,
                    u.nombre AS usuario_nombre
             FROM ordenes_produccion op
             JOIN recetas r ON r.id = op.receta_id
             LEFT JOIN productos p ON p.id = r.producto_id
             LEFT JOIN usuarios u ON u.id = op.usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY op.created_at DESC',
            $params
        );
    }

    public function resumenProduccion(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT
                COUNT(*) AS total_ordenes,
                COUNT(DISTINCT receta_id) AS recetas_distintas,
                COALESCE(SUM(cantidad_producida), 0) AS total_unidades,
                COALESCE(AVG(cantidad_producida), 0) AS promedio_unidades
             FROM ordenes_produccion
             WHERE estado = 'completada'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        ) ?: [];
    }

    public function produccionPorReceta(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT r.nombre AS receta,
                    COUNT(op.id) AS ordenes,
                    SUM(op.cantidad_producida) AS total_producido
             FROM ordenes_produccion op
             JOIN recetas r ON r.id = op.receta_id
             WHERE op.estado = 'completada'
               AND DATE(op.created_at) BETWEEN ? AND ?
             GROUP BY op.receta_id, r.nombre
             ORDER BY total_producido DESC
             LIMIT 10",
            [$desde, $hasta]
        );
    }

    public function getRecetasActivas(): array
    {
        return $this->queryAll(
            'SELECT id, nombre FROM recetas WHERE eliminado = 0 ORDER BY nombre',
            []
        );
    }
}
