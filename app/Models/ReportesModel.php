<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ReportesModel extends Model
{
    protected string $table = 'ventas';

    // ── Ventas ────────────────────────────────────────────────────────────────

    /**
     * Ventas de productos "hechos al momento" que se confirmaron vender igual sin
     * tener suficiente materia prima/insumos (ver VentaModel::registrar()) — para
     * revisar qué faltó y cuándo, y decidir si conviene ajustar stock o comprar más.
     */
    public function ventasSinStock(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT vss.id, vss.created_at, vss.tipo_ingrediente, vss.ingrediente_nombre,
                    vss.cantidad_necesaria, vss.cantidad_disponible,
                    v.id AS venta_id, p.nombre AS producto_nombre,
                    u.nombre AS usuario
             FROM ventas_sin_stock vss
             JOIN ventas v     ON v.id = vss.venta_id
             JOIN productos p  ON p.id = vss.producto_id
             LEFT JOIN usuarios u ON u.id = vss.usuario_id
             WHERE DATE(vss.created_at) BETWEEN :desde AND :hasta
             ORDER BY vss.created_at DESC",
            [':desde' => $desde, ':hasta' => $hasta]
        );
    }

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
                COALESCE(SUM(monto_efectivo),      0) AS efectivo,
                COALESCE(SUM(monto_debito),        0) AS debito,
                COALESCE(SUM(monto_credito),       0) AS credito,
                COALESCE(SUM(monto_transferencia), 0) AS transferencia,
                COALESCE(SUM(monto_mercadopago),   0) AS mercadopago,
                COALESCE(SUM(monto_cuenta_corriente), 0) AS cuenta_corriente
             FROM ventas
             WHERE eliminado = 0 AND estado = 'completada'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        ) ?: [];
    }

    /**
     * Ventas de un día puntual, separadas en productos vendidos sueltos ("unitarios") vs
     * promos vendidas. Excluye las filas de componente de promo (venta_detalle_padre_id NOT
     * NULL, precio $0, ya cobradas dentro del precio de la promo) — sin este filtro, "1/4 de
     * helado" vendido suelto + una "Promo Helado 1/4 x2" se mezclarían en una sola fila con
     * cantidad inflada (3 unidades) y quedaría ambiguo qué parte de esas 3 se cobró aparte
     * y qué parte ya venía incluida en el precio de la promo. Ver VentaModel::explotarPromos().
     */
    public function ventasPorProductoDia(string $fecha): array
    {
        $filas = $this->queryAll(
            "SELECT p.nombre,
                    (promo.producto_id IS NOT NULL) AS es_promo,
                    SUM(vd.cantidad) AS cantidad,
                    SUM(vd.subtotal_con_iva) AS total
             FROM ventas_detalle vd
             JOIN ventas v ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             LEFT JOIN (SELECT DISTINCT producto_id FROM producto_promo_detalle) promo ON promo.producto_id = p.id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) = ?
             GROUP BY p.id, p.nombre, es_promo
             ORDER BY es_promo, total DESC",
            [$fecha]
        );

        $unitarios = [];
        $promos    = [];
        foreach ($filas as $f) {
            if ($f['es_promo']) {
                $promos[] = $f;
            } else {
                $unitarios[] = $f;
            }
        }

        // Se suma directo sobre las columnas monto_* (no se agrupa por metodo_pago) para que
        // una venta pagada con varios medios a la vez reparta su importe entre los medios
        // reales, en vez de caer entera en un solo bucket "combinado" — mismo criterio que
        // ReporteSemanalModel::resumenVentas().
        $resumen = $this->queryOne(
            "SELECT COUNT(*) AS cantidad_ventas,
                    COALESCE(SUM(total_con_iva),      0) AS total,
                    COALESCE(SUM(monto_efectivo),      0) AS efectivo,
                    COALESCE(SUM(monto_debito),        0) AS debito,
                    COALESCE(SUM(monto_credito),       0) AS credito,
                    COALESCE(SUM(monto_transferencia), 0) AS transferencia,
                    COALESCE(SUM(monto_mercadopago),   0) AS mercadopago
             FROM ventas WHERE eliminado = 0 AND estado = 'completada' AND DATE(created_at) = ?",
            [$fecha]
        ) ?: ['cantidad_ventas' => 0, 'total' => 0];

        $porMetodo = [];
        foreach (['efectivo', 'debito', 'credito', 'transferencia', 'mercadopago'] as $medio) {
            $total = (float) ($resumen[$medio] ?? 0);
            if ($total > 0.009) $porMetodo[] = ['metodo_pago' => $medio, 'total' => $total];
        }
        usort($porMetodo, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'unitarios'       => $unitarios,
            'promos'          => $promos,
            'total_dia'       => (float) $resumen['total'],
            'cantidad_ventas' => (int) $resumen['cantidad_ventas'],
            'por_metodo'      => $porMetodo,
        ];
    }

    /** Top de productos por facturación. Excluye las filas hijas de promo (inflarían la cantidad vendida del componente y sumarían ingreso $0) — ver la nota canónica en margenVentasPorProducto(). */
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
               AND vd.venta_detalle_padre_id IS NULL
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

    /**
     * Materias primas e insumos con compras registradas a precio unitario $0 — casi
     * siempre un error de carga (el precio se dejó vacío/cero en vez de completarlo), que
     * contamina para siempre el Costo Promedio Ponderado de ese ítem (ver
     * FacturaCompraModel::actualizarStock()). No se filtra por período: una compra vieja
     * a $0 sigue afectando el costo actual, así que interesa verlas todas.
     */
    public function comprasPrecioCero(): array
    {
        return $this->queryAll(
            "SELECT fc.id AS factura_id, fc.fecha, fc.nro_comprobante,
                    p.razon_social AS proveedor,
                    fcd.tipo_item,
                    COALESCE(mp.id, ins.id)     AS item_id,
                    COALESCE(mp.nombre, ins.nombre) AS item,
                    fcd.cantidad,
                    COALESCE(mp.costo_unitario, ins.costo_unitario) AS costo_actual
             FROM facturas_compra_detalle fcd
             JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             LEFT JOIN proveedores p   ON p.id = fc.proveedor_id
             LEFT JOIN materias_primas mp ON mp.id  = fcd.materia_prima_id AND fcd.tipo_item = 'materia_prima'
             LEFT JOIN insumos ins        ON ins.id = fcd.insumo_id        AND fcd.tipo_item = 'insumo'
             WHERE fcd.tipo_item IN ('materia_prima','insumo')
               AND fcd.precio_unitario = 0
               AND fc.eliminado = 0 AND fc.estado != 'anulada'
             ORDER BY fc.fecha DESC"
        );
    }

    /**
     * Materias primas/insumos cuyo costo_unitario actual (el Costo Promedio Ponderado
     * usado por las recetas) se desvía mucho del precio de su última compra real —
     * el doble o más, o menos de la mitad. No alcanza con cualquier diferencia porque
     * acá los precios suben seguido por inflación; el umbral 2x/0.5x es a propósito
     * conservador para no ensuciarse con eso y marcar solo casos realmente raros.
     * Encontrado el caso real que motivó este reporte: una importación masiva de
     * materias primas (2026-07-07) cargó costos iniciales absurdos para algunos ítems
     * (ver "Pan Arabe": ~$11.200 inicial vs ~$800-1.500 de compras reales), y como esas
     * compras reales son chicas comparadas con el stock que trajo la importación, el
     * promedio ponderado tarda muchísimo en diluirse solo.
     *
     * costo_unitario está expresado en la UNIDAD BASE del ítem (por gramo/cc/unidad —
     * ver FacturaCompraModel::actualizarStock()), pero facturas_compra_detalle.precio_unitario
     * es el precio "por unidad de COMPRA" tal cual se tipeó en la factura (por bolsa,
     * balde, bidón, etc. — antes de aplicar cantidad_contenida). Comparar esos dos
     * números directo, sin normalizar, compara peras con manzanas para cualquier ítem
     * comprado en paquete y consumido por gramo — por eso acá se divide el precio de
     * compra por su cantidad_contenida antes de calcular el desvío.
     */
    public function costosDesviados(): array
    {
        $rows = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, mp.id AS item_id, mp.nombre, mp.costo_unitario AS costo_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id = mp.unidad_medida_id) AS unidad,
                    fcd.precio_unitario AS precio_compra, fcd.cantidad_contenida AS cantidad_contenida,
                    fc.fecha AS fecha_ultima_compra, fc.nro_comprobante, fc.id AS factura_id,
                    (SELECT GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', ')
                        FROM receta_detalle rd JOIN recetas r ON r.id = rd.receta_id AND r.eliminado = 0
                        WHERE rd.materia_prima_id = mp.id
                           OR (mp.ingrediente_generico_id IS NOT NULL AND rd.ingrediente_generico_id = mp.ingrediente_generico_id)
                    ) AS recetas_nombres
             FROM materias_primas mp
             LEFT JOIN facturas_compra_detalle fcd ON fcd.id = (
                 SELECT fcd2.id FROM facturas_compra_detalle fcd2
                 JOIN facturas_compra fc2 ON fc2.id = fcd2.factura_compra_id
                 WHERE fcd2.tipo_item = 'materia_prima' AND fcd2.materia_prima_id = mp.id
                   AND fc2.estado != 'anulada' AND fc2.eliminado = 0
                 ORDER BY fc2.fecha DESC, fcd2.id DESC LIMIT 1
             )
             LEFT JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             WHERE mp.eliminado = 0

             UNION ALL

             SELECT 'insumo' AS tipo, ins.id AS item_id, ins.nombre, ins.costo_unitario AS costo_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id = ins.unidad_medida_id) AS unidad,
                    fcd.precio_unitario AS precio_compra, NULL AS cantidad_contenida,
                    fc.fecha AS fecha_ultima_compra, fc.nro_comprobante, fc.id AS factura_id,
                    (SELECT GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ', ')
                        FROM receta_detalle rd JOIN recetas r ON r.id = rd.receta_id AND r.eliminado = 0
                        WHERE rd.insumo_id = ins.id
                    ) AS recetas_nombres
             FROM insumos ins
             LEFT JOIN facturas_compra_detalle fcd ON fcd.id = (
                 SELECT fcd2.id FROM facturas_compra_detalle fcd2
                 JOIN facturas_compra fc2 ON fc2.id = fcd2.factura_compra_id
                 WHERE fcd2.tipo_item = 'insumo' AND fcd2.insumo_id = ins.id
                   AND fc2.estado != 'anulada' AND fc2.eliminado = 0
                 ORDER BY fc2.fecha DESC, fcd2.id DESC LIMIT 1
             )
             LEFT JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             WHERE ins.eliminado = 0",
            []
        );

        $resultado = [];
        foreach ($rows as $r) {
            if ($r['precio_compra'] === null || (float)$r['precio_compra'] <= 0) continue;
            $costoActual  = (float)$r['costo_actual'];
            $contenida    = (float)($r['cantidad_contenida'] ?? 0) ?: 1.0;
            $precioPorBase = (float)$r['precio_compra'] / $contenida;
            if ($costoActual < $precioPorBase * 2 && $costoActual > $precioPorBase * 0.5) continue;

            $r['precio_ultima_compra']      = $precioPorBase;
            $r['precio_ultima_compra_bruto'] = (float)$r['precio_compra'];
            $r['desviacion_pct']            = (($costoActual - $precioPorBase) / $precioPorBase) * 100;
            $resultado[] = $r;
        }

        usort($resultado, fn($a, $b) => abs($b['desviacion_pct']) <=> abs($a['desviacion_pct']));
        return $resultado;
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

    /**
     * Compras y pagos fila a fila, con quién los cargó — para el cron de control de
     * "compras y pagos por usuario" (ver docs y cron/reporte_compras_pagos_usuario.php).
     * Compras sin usuario_id (facturas viejas cargadas antes de que la columna existiera)
     * quedan como "Sin usuario", no se descartan.
     */
    public function comprasYPagosPorUsuario(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT * FROM (
                SELECT COALESCE(u.nombre, 'Sin usuario') AS usuario, fc.fecha, 'Compra' AS tipo,
                       CONCAT(COALESCE(p.razon_social, '—'), ' — ', UPPER(REPLACE(fc.tipo_comprobante,'_',' ')),
                              ' ', COALESCE(fc.nro_comprobante, '')) AS detalle,
                       fc.total_con_iva AS monto,
                       REPLACE(fc.condicion_pago, '_', ' ') AS forma_pago
                FROM facturas_compra fc
                LEFT JOIN usuarios u    ON u.id = fc.usuario_id
                LEFT JOIN proveedores p ON p.id = fc.proveedor_id
                WHERE fc.eliminado = 0 AND fc.estado != 'anulada'
                  AND fc.fecha BETWEEN :desde1 AND :hasta1

                UNION ALL

                SELECT u.nombre AS usuario, pp.fecha, 'Pago' AS tipo,
                       CONCAT(COALESCE(p.razon_social, pp.beneficiario, '—'),
                              CASE WHEN pp.concepto IS NOT NULL AND pp.concepto != '' THEN CONCAT(' — ', pp.concepto) ELSE '' END) AS detalle,
                       pp.monto_total AS monto,
                       TRIM(BOTH ', ' FROM CONCAT_WS(', ',
                           IF(pp.monto_efectivo      > 0, 'Efectivo',      NULL),
                           IF(pp.monto_debito        > 0, 'Débito',        NULL),
                           IF(pp.monto_transferencia > 0, 'Transferencia', NULL),
                           IF(pp.monto_cheque        > 0, 'Cheque',        NULL),
                           IF(pp.monto_mercado_pago  > 0, 'Mercado Pago',  NULL),
                           IF(pp.monto_otro          > 0, 'Otro',          NULL)
                       )) AS forma_pago
                FROM pagos_proveedores pp
                JOIN usuarios u          ON u.id = pp.usuario_id
                LEFT JOIN proveedores p  ON p.id = pp.proveedor_id
                WHERE pp.eliminado = 0 AND pp.estado = 'activo'
                  AND pp.fecha BETWEEN :desde2 AND :hasta2
             ) t
             ORDER BY usuario, fecha",
            [':desde1' => $desde, ':hasta1' => $hasta, ':desde2' => $desde, ':hasta2' => $hasta]
        );
    }

    /**
     * Cantidad vendida, costo y ganancia por producto en un período — para el cron semanal
     * de margen por producto. OJO: el costo usado es `precio_costo` de HOY (con su desvío),
     * aplicado retroactivamente a toda la cantidad vendida en el período — igual que ya se
     * documentó en Proyecciones/CobroClienteModel, `ventas_detalle` no guarda el costo al
     * momento de cada venta, así que esto es una aproximación, no un margen histórico real.
     * Excluye las filas hijas de promo, que duplicarían el costo — ver la nota
     * canónica en margenVentasPorProducto().
     */
    public function margenPorProducto(string $desde, string $hasta): array
    {
        $rows = $this->queryAll(
            "SELECT p.id, p.nombre,
                    SUM(vd.cantidad) AS cantidad_vendida,
                    SUM(vd.subtotal) AS total_vendido,
                    COALESCE(p.precio_costo, 0) * (1 + COALESCE(p.desvio_pct, 0) / 100) AS costo_unitario
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN ? AND ?
             GROUP BY p.id, p.nombre, p.precio_costo, p.desvio_pct",
            [$desde, $hasta]
        );

        $resultado = [];
        foreach ($rows as $r) {
            $cantidad    = (float) $r['cantidad_vendida'];
            $totalVend   = (float) $r['total_vendido'];
            $costoUnit   = (float) $r['costo_unitario'];
            $costoTotal  = $cantidad * $costoUnit;
            $ganancia    = $totalVend - $costoTotal;

            $resultado[] = [
                'producto_id'       => (int) $r['id'],
                'nombre'            => $r['nombre'],
                'cantidad_vendida'  => $cantidad,
                'total_vendido'     => $totalVend,
                'costo_total'       => $costoTotal,
                'ganancia'          => $ganancia,
                'margen_pct'        => $totalVend > 0.009 ? round($ganancia / $totalVend * 100, 1) : 0.0,
            ];
        }
        usort($resultado, fn($a, $b) => $b['ganancia'] <=> $a['ganancia']);
        return $resultado;
    }

    /**
     * Detalle de ventas con costo real de MP y margen, línea por línea — a diferencia de
     * margenPorProducto() (que agrupa por producto y usa el costo ACTUAL sin importar el
     * sabor vendido ni cuándo se vendió), acá se usa vd.costo_mp_real: el costo calculado
     * con el sabor puntual elegido y guardado al momento de esa venta (ver
     * VentaModel::registrar() / RecetaModel::calcularCostoReal()), así que queda fijo en
     * el tiempo sin importar cómo cambien los costos después.
     * Ventas de antes de esta funcionalidad no tienen costo_mp_real (columna NULL): para
     * esas se estima con el precio_costo ACTUAL del producto, marcadas 'costo_estimado'
     * para no confundirlas con un costo real verificado.
     * Excluye las filas hijas de promo, que duplicarían el costo — ver la nota
     * canónica en margenVentasPorProducto().
     */
    public function ventasConCostoReal(string $desde, string $hasta): array
    {
        $rows = $this->queryAll(
            "SELECT vd.id, v.id AS venta_id, v.created_at,
                    p.nombre AS producto_nombre, p.precio_costo AS producto_costo_actual,
                    vd.cantidad, vd.precio_unitario, vd.subtotal, vd.costo_mp_real,
                    (SELECT GROUP_CONCAT(DISTINCT o.nombre SEPARATOR ', ')
                        FROM ventas_detalle_opciones vdo
                        JOIN opciones o ON o.id = vdo.opcion_id
                        WHERE vdo.venta_detalle_id = vd.id
                    ) AS sabores_elegidos
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :desde AND :hasta
             ORDER BY v.created_at DESC, vd.id",
            [':desde' => $desde, ':hasta' => $hasta]
        );

        $out = [];
        foreach ($rows as $r) {
            $cantidad   = (float)$r['cantidad'];
            $totalVend  = (float)$r['subtotal'];
            $esEstimado = $r['costo_mp_real'] === null;
            $costoMp    = $esEstimado ? $cantidad * (float)$r['producto_costo_actual'] : (float)$r['costo_mp_real'];
            $margen     = $totalVend - $costoMp;

            $out[] = [
                'venta_detalle_id' => (int)$r['id'],
                'venta_id'         => (int)$r['venta_id'],
                'fecha'            => $r['created_at'],
                'producto_nombre'  => $r['producto_nombre'],
                'sabores_elegidos' => $r['sabores_elegidos'],
                'cantidad'         => $cantidad,
                'precio_unitario'  => (float)$r['precio_unitario'],
                'total_vendido'    => $totalVend,
                'costo_mp'         => $costoMp,
                'costo_estimado'   => $esEstimado,
                'margen'           => $margen,
                'margen_pct'       => $totalVend > 0.009 ? round($margen / $totalVend * 100, 1) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Margen de ventas agrupado por producto, con el costo desglosado en "MP directa"
     * vs "ingrediente genérico" (ver ventas_detalle.costo_ingrediente_generico /
     * RecetaModel::calcularCostoReal()) — pensado para una respuesta conversacional
     * rápida del Chat IA (ver ChatController::toolConsultarMargenVentas()), a
     * diferencia de ventasConCostoReal() que da el detalle línea por línea para el
     * reporte guardado. Igual que ese método, usa el costo guardado al momento de cada
     * venta; si falta (ventas anteriores a esta funcionalidad) cae al precio_costo
     * actual como estimación completa (sin desglose, todo a "directa").
     */
    /**
     * POR QUÉ TODAS LAS CONSULTAS DE COSTO/MARGEN EXCLUYEN LAS FILAS HIJAS DE PROMO
     * (`vd.venta_detalle_padre_id IS NOT NULL`) — explicación canónica, referenciada
     * desde el resto de los métodos de este archivo y de ReporteSemanalModel /
     * SeparacionIngresosModel / ProyeccionModel:
     *
     * Al vender una promo, VentaModel::explotarPromos() genera una fila por componente
     * con precio $0 (el ingreso lo cobra la fila de la promo) y cada una guarda su
     * propio `costo_mp_real`. Pero el costo de la promo NO es cero: una promo no tiene
     * receta propia, así que su `costo_mp_real` sale de `productos.precio_costo`, que
     * ProductoModel::sincronizarPrecioCostoPromo() mantiene igual a la SUMA del costo de
     * sus componentes. O sea que el costo de los componentes ya está contado una vez en
     * la fila de la promo: sumar también las filas hijas lo cuenta DOS VECES e infla el
     * costo del período, además de mostrar cada componente como un producto con venta $0
     * y margen negativo (ver docs/promos-diseno.md, sección 4bis).
     *
     * OJO — el filtro es por `venta_detalle_padre_id`, NUNCA por "precio $0": una venta
     * de un producto suelto cargada en $0 (producto al que le falta el precio de venta)
     * tiene `venta_detalle_padre_id` NULL y TIENE que seguir apareciendo, justamente para
     * que el usuario se dé cuenta de que ese producto está sin precio. Ver el flag
     * `vendido_sin_precio` de margenVentasPorProducto().
     */
    public function margenVentasPorProducto(string $desde, string $hasta): array
    {
        $rows = $this->queryAll(
            "SELECT p.id, p.nombre,
                    SUM(vd.cantidad) AS cantidad_vendida,
                    SUM(vd.subtotal) AS total_vendido,
                    SUM(COALESCE(vd.costo_mp_real, vd.cantidad * p.precio_costo)) AS costo_total,
                    SUM(COALESCE(vd.costo_ingrediente_generico, 0)) AS costo_generico,
                    SUM(vd.costo_mp_real IS NULL) AS lineas_estimadas
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :desde AND :hasta
             GROUP BY p.id, p.nombre",
            [':desde' => $desde, ':hasta' => $hasta]
        );

        // Qué salió entregado DENTRO de las promos del período, agrupado por promo y por
        // producto componente (las mismas filas hijas que la consulta de arriba excluye).
        // Va como desglose informativo bajo la fila de su promo: cantidad y costo sí, margen
        // NO — un componente de promo no tiene precio propio, así que su margen no existe
        // como concepto (el ingreso lo cobró la promo entera).
        $componentesPorPromo = [];
        $filasComp = $this->queryAll(
            "SELECT padre.producto_id AS promo_id, p.nombre,
                    SUM(vd.cantidad) AS cantidad,
                    SUM(COALESCE(vd.costo_mp_real, vd.cantidad * p.precio_costo)) AS costo_total
             FROM ventas_detalle vd
             JOIN ventas_detalle padre ON padre.id = vd.venta_detalle_padre_id
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND DATE(v.created_at) BETWEEN :desde AND :hasta
             GROUP BY padre.producto_id, p.id, p.nombre
             ORDER BY p.nombre",
            [':desde' => $desde, ':hasta' => $hasta]
        );
        foreach ($filasComp as $c) {
            $componentesPorPromo[(int)$c['promo_id']][] = [
                'producto_nombre' => $c['nombre'],
                'cantidad'        => (float)$c['cantidad'],
                'costo_total'     => (float)$c['costo_total'],
            ];
        }

        $out = [];
        foreach ($rows as $r) {
            $totalVend  = (float)$r['total_vendido'];
            $costoTotal = (float)$r['costo_total'];
            $costoGen   = (float)$r['costo_generico'];
            $margen     = $totalVend - $costoTotal;
            $cantidad   = (float)$r['cantidad_vendida'];

            // Nombre(s) del/los ingrediente(s) genérico(s) de la receta de este producto
            // (ej. "Helado"), solo si de verdad hubo costo de genérico en el período —
            // para no consultar recetas de productos de reventa sin sentido.
            $nombreGenerico = null;
            if ($costoGen > 0.009) {
                $nombres = $this->queryAll(
                    "SELECT DISTINCT ig.nombre
                     FROM recetas r
                     JOIN receta_detalle rd      ON rd.receta_id = r.id
                     JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
                     WHERE r.producto_id = :pid AND r.eliminado = 0",
                    [':pid' => (int)$r['id']]
                );
                $nombreGenerico = $nombres ? implode(' + ', array_column($nombres, 'nombre')) : null;
            }

            $out[] = [
                'producto_id'        => (int)$r['id'],
                'producto_nombre'    => $r['nombre'],
                'cantidad_vendida'   => $cantidad,
                'total_vendido'      => $totalVend,
                'costo_mp_directa'   => $costoTotal - $costoGen,
                'costo_generico'     => $costoGen,
                'generico_nombre'    => $nombreGenerico,
                'costo_total'        => $costoTotal,
                'margen'             => $margen,
                'margen_pct'         => $totalVend > 0.009 ? round($margen / $totalVend * 100, 2) : 0.0,
                'tiene_estimados'    => (int)$r['lineas_estimadas'] > 0,
                // Se vendieron unidades pero no entró plata: no es un componente de promo
                // (esos ya quedaron afuera), así que es un producto al que le falta el
                // precio de venta, o una venta cargada a mano en $0. Se marca para que el
                // chat lo avise en vez de reportarlo como "margen 0%" sin más.
                'vendido_sin_precio' => $cantidad > 0.0 && $totalVend <= 0.009,
                // Desglose informativo de qué se entregó adentro, solo para promos.
                'componentes'        => $componentesPorPromo[(int)$r['id']] ?? null,
            ];
        }
        usort($out, fn($a, $b) => $b['margen'] <=> $a['margen']);
        return $out;
    }

    /**
     * Margen de ventas TOTAL de un período (no por producto) — a diferencia de
     * margenVentasPorProducto(), acá "Costo MP directa" es UN solo número que ya
     * incluye ingrediente genérico (sin desglosar), y se le restan además Sueldos,
     * Alquiler y Servicios prorrateados como % de ventas sobre un período de
     * referencia — mismo mecanismo que la pestaña "Separación de Ingresos" de
     * /reportes/ventas (ver SeparacionIngresosModel::calcularReferencia()), para que
     * el margen del chat coincida con esa misma lógica ya usada en el sistema.
     * Igual que margenVentasPorProducto(), excluye las filas hijas de promo para no
     * contar dos veces el costo de sus componentes — ver la nota canónica de ese método.
     */
    public function margenVentasTotal(string $desde, string $hasta, int $mesesReferencia = 3): array
    {
        require_once BASE_PATH . '/app/Models/SeparacionIngresosModel.php';

        $totalVentas = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva), 0) FROM ventas
             WHERE eliminado = 0 AND estado = 'completada' AND DATE(created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );

        $costoMp = $this->queryOne(
            "SELECT COALESCE(SUM(COALESCE(vd.costo_mp_real, vd.cantidad * p.precio_costo)), 0) AS costo,
                    SUM(vd.costo_mp_real IS NULL) AS lineas_estimadas
             FROM ventas_detalle vd
             JOIN ventas v    ON v.id = vd.venta_id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.venta_detalle_padre_id IS NULL
               AND DATE(v.created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        ) ?: ['costo' => 0, 'lineas_estimadas' => 0];
        $costoMpTotal   = (float)$costoMp['costo'];
        $tieneEstimados = (int)$costoMp['lineas_estimadas'] > 0;

        $referencia = (new SeparacionIngresosModel())->calcularReferencia($hasta, $mesesReferencia);
        $pct = $referencia['porcentajes'] ?? null;

        $costoSueldos   = $pct ? round($totalVentas * $pct['sueldos']   / 100, 2) : 0.0;
        $costoAlquiler  = $pct ? round($totalVentas * $pct['alquiler']  / 100, 2) : 0.0;
        $costoServicios = $pct ? round($totalVentas * $pct['servicios'] / 100, 2) : 0.0;

        $margen = $totalVentas - $costoMpTotal - $costoSueldos - $costoAlquiler - $costoServicios;

        // % que representa cada costo sobre las ventas de ESTE período. Para Sueldos/
        // Alquiler/Servicios coincide con el % de referencia (por construcción: el monto
        // sale de aplicar ese % a total_ventas) — para MP directa hay que calcularlo,
        // porque ese monto es el costo real de lo vendido, no un prorrateo.
        $pctSobreVentas = fn(float $monto) => $totalVentas > 0.009 ? round($monto / $totalVentas * 100, 2) : 0.0;

        return [
            'desde'                 => $desde,
            'hasta'                 => $hasta,
            'total_ventas'          => $totalVentas,
            'costo_mp_directa'      => $costoMpTotal,
            'costo_mp_directa_pct'  => $pctSobreVentas($costoMpTotal),
            'costo_sueldos'         => $costoSueldos,
            'costo_sueldos_pct'     => $pctSobreVentas($costoSueldos),
            'costo_alquiler'        => $costoAlquiler,
            'costo_alquiler_pct'    => $pctSobreVentas($costoAlquiler),
            'costo_servicios'       => $costoServicios,
            'costo_servicios_pct'   => $pctSobreVentas($costoServicios),
            'margen'                => $margen,
            'margen_pct'            => $pctSobreVentas($margen),
            'tiene_estimados'       => $tieneEstimados,
            'referencia_sin_datos'  => $referencia['sin_datos'],
            'referencia_meses'      => $mesesReferencia,
        ];
    }

    // ── Pagos ─────────────────────────────────────────────────────────────────

    /**
     * Comprobantes (facturas de compra + gastos) de un período, con su estado
     * de pago consolidado ('pagado'/'pendiente') y el saldo aún adeudado en
     * caso de pago parcial. Ambos circuitos comparten pagos_proveedores como
     * registro de pago (vía pagos_facturas / pagos_gastos).
     */
    public function comprobantesPeriodo(string $desde, string $hasta, array $filtros = []): array
    {
        $params = [
            ':desde1' => $desde, ':hasta1' => $hasta,
            ':desde2' => $desde, ':hasta2' => $hasta,
        ];

        $condProv1 = $condProv2 = '';
        if (!empty($filtros['proveedor_id'])) {
            $condProv1         = ' AND fc.proveedor_id = :prov1';
            $condProv2         = ' AND g.proveedor_id = :prov2';
            $params[':prov1']  = (int)$filtros['proveedor_id'];
            $params[':prov2']  = (int)$filtros['proveedor_id'];
        }

        $sql = "SELECT * FROM (
            SELECT fc.id, 'factura' AS tipo, fc.fecha, fc.proveedor_id,
                   p.razon_social AS proveedor,
                   CONCAT(UPPER(REPLACE(fc.tipo_comprobante,'_',' ')), ' ', COALESCE(fc.nro_comprobante,'')) AS descripcion,
                   fc.total_con_iva AS total,
                   CASE WHEN fc.estado = 'pagada' THEN 'pagado' ELSE 'pendiente' END AS estado_pago,
                   GREATEST(fc.total_con_iva - COALESCE((
                       SELECT SUM(pf.monto_aplicado) FROM pagos_facturas pf
                       JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo'
                       WHERE pf.factura_id = fc.id
                   ), 0), 0) AS saldo_pendiente
            FROM facturas_compra fc
            LEFT JOIN proveedores p ON p.id = fc.proveedor_id
            WHERE fc.eliminado = 0 AND fc.estado != 'anulada' AND fc.fecha BETWEEN :desde1 AND :hasta1{$condProv1}

            UNION ALL

            SELECT g.id, 'gasto' AS tipo, g.fecha, g.proveedor_id,
                   COALESCE(p2.razon_social, gc.nombre) AS proveedor,
                   g.descripcion,
                   g.monto AS total,
                   CASE WHEN g.estado = 'pagado' THEN 'pagado' ELSE 'pendiente' END AS estado_pago,
                   GREATEST(g.monto - COALESCE((
                       SELECT SUM(pg.monto_aplicado) FROM pagos_gastos pg
                       JOIN pagos_proveedores pp2 ON pp2.id = pg.pago_id AND pp2.estado = 'activo'
                       WHERE pg.gasto_id = g.id
                   ), 0), 0) AS saldo_pendiente
            FROM gastos g
            LEFT JOIN proveedores p2 ON p2.id = g.proveedor_id
            JOIN gasto_categorias gc ON gc.id = g.categoria_id
            WHERE g.eliminado = 0 AND g.fecha BETWEEN :desde2 AND :hasta2{$condProv2}
        ) t";

        if (!empty($filtros['estado'])) {
            $sql              .= ' WHERE t.estado_pago = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY t.fecha DESC, t.id DESC';

        return $this->queryAll($sql, $params);
    }

    /** Totales consolidados (facturas + gastos) para las tarjetas de resumen del reporte de pagos */
    public function resumenPagosPeriodo(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT
                COUNT(*) AS total_comprobantes,
                COALESCE(SUM(total), 0) AS total_general,
                COALESCE(SUM(CASE WHEN estado_pago = 'pagado' THEN total ELSE 0 END), 0) AS total_pagado,
                COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN saldo_pendiente ELSE 0 END), 0) AS total_pendiente,
                COALESCE(SUM(CASE WHEN estado_pago = 'pendiente' THEN 1 ELSE 0 END), 0) AS cant_pendientes
             FROM (
                SELECT fc.total_con_iva AS total,
                       CASE WHEN fc.estado = 'pagada' THEN 'pagado' ELSE 'pendiente' END AS estado_pago,
                       GREATEST(fc.total_con_iva - COALESCE((
                           SELECT SUM(pf.monto_aplicado) FROM pagos_facturas pf
                           JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo'
                           WHERE pf.factura_id = fc.id
                       ), 0), 0) AS saldo_pendiente
                FROM facturas_compra fc
                WHERE fc.eliminado = 0 AND fc.estado != 'anulada' AND fc.fecha BETWEEN :d1 AND :h1

                UNION ALL

                SELECT g.monto AS total,
                       CASE WHEN g.estado = 'pagado' THEN 'pagado' ELSE 'pendiente' END AS estado_pago,
                       GREATEST(g.monto - COALESCE((
                           SELECT SUM(pg.monto_aplicado) FROM pagos_gastos pg
                           JOIN pagos_proveedores pp2 ON pp2.id = pg.pago_id AND pp2.estado = 'activo'
                           WHERE pg.gasto_id = g.id
                       ), 0), 0) AS saldo_pendiente
                FROM gastos g
                WHERE g.eliminado = 0 AND g.fecha BETWEEN :d2 AND :h2
             ) t",
            [':d1' => $desde, ':h1' => $hasta, ':d2' => $desde, ':h2' => $hasta]
        ) ?: [];
    }

    /** Pagos (movimientos de dinero) efectivamente registrados en el período */
    public function pagosRegistradosPeriodo(string $desde, string $hasta, array $filtros = []): array
    {
        $where  = ["pp.estado = 'activo'", 'pp.eliminado = 0', 'pp.fecha BETWEEN :desde AND :hasta'];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if (!empty($filtros['proveedor_id'])) {
            $where[]         = 'pp.proveedor_id = :prov';
            $params[':prov'] = (int)$filtros['proveedor_id'];
        }

        return $this->queryAll(
            'SELECT pp.id, pp.fecha, pp.tipo, COALESCE(p.razon_social, pp.beneficiario) AS beneficiario,
                    pp.concepto, pp.monto_total
             FROM pagos_proveedores pp
             LEFT JOIN proveedores p ON p.id = pp.proveedor_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY pp.fecha DESC, pp.id DESC',
            $params
        );
    }
}
