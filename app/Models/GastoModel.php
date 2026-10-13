<?php
require_once BASE_PATH . '/app/Models/Model.php';

class GastoModel extends Model
{
    protected string $table = 'gastos';

    public function findAllFiltrado(array $filtros = []): array
    {
        $where  = ['g.eliminado = 0'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]          = 'g.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]          = 'g.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]         = 'gc.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['categoria_id'])) {
            $where[]          = '(g.categoria_id = :cat OR gc.parent_id = :cat2)';
            $params[':cat']   = (int)$filtros['categoria_id'];
            $params[':cat2']  = (int)$filtros['categoria_id'];
        }
        if (!empty($filtros['proveedor_id'])) {
            $where[]              = 'g.proveedor_id = :proveedor_id';
            $params[':proveedor_id'] = (int)$filtros['proveedor_id'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT g.*, gc.nombre AS categoria_nombre, gc.tipo AS categoria_tipo,
                    gc.color, gcp.nombre AS padre_nombre, u.nombre AS usuario_nombre,
                    p.razon_social AS proveedor_nombre
             FROM gastos g
             JOIN gasto_categorias gc  ON gc.id  = g.categoria_id
             LEFT JOIN gasto_categorias gcp ON gcp.id = gc.parent_id
             JOIN usuarios u           ON u.id   = g.usuario_id
             LEFT JOIN proveedores p   ON p.id   = g.proveedor_id
             WHERE $cond
             ORDER BY g.fecha DESC, g.id DESC",
            $params
        );
    }

    /**
     * Gastos pendientes con vencimiento real (fecha_vencimiento) ya vencidos o por vencer
     * dentro de $diasUmbral días, con el saldo real ya descontados los pagos parciales
     * (misma subquery de pagos_gastos que usa ChatController::toolDeudaPendienteTotal).
     * Solo mira `gastos` — las facturas de compra a cuenta corriente no tienen fecha de
     * vencimiento propia en el sistema todavía. Ver cron/vencimientos_gastos_diario.php.
     */
    public function proximosAVencer(int $diasUmbral = 3): array
    {
        return $this->queryAll(
            "SELECT g.id, g.fecha, g.fecha_vencimiento, g.descripcion,
                    COALESCE(p.razon_social, gc.nombre) AS entidad,
                    g.monto - COALESCE((SELECT SUM(pg.monto_aplicado) FROM pagos_gastos pg
                        JOIN pagos_proveedores pp ON pp.id = pg.pago_id AND pp.estado = 'activo'
                        WHERE pg.gasto_id = g.id), 0) AS saldo,
                    DATEDIFF(g.fecha_vencimiento, CURDATE()) AS dias_para_vencer
             FROM gastos g
             JOIN gasto_categorias gc ON gc.id = g.categoria_id
             LEFT JOIN proveedores p  ON p.id  = g.proveedor_id
             WHERE g.estado = 'pendiente' AND g.eliminado = 0
               AND g.fecha_vencimiento IS NOT NULL
               AND g.fecha_vencimiento <= (CURDATE() + INTERVAL :dias DAY)
             HAVING saldo > 0.009
             ORDER BY g.fecha_vencimiento ASC",
            [':dias' => $diasUmbral]
        );
    }

    public function findByIdConProveedor(int $id): array|false
    {
        return $this->queryOne(
            "SELECT g.*, gc.nombre AS categoria_nombre, p.razon_social AS proveedor_nombre
             FROM gastos g
             JOIN gasto_categorias gc ON gc.id = g.categoria_id
             LEFT JOIN proveedores p  ON p.id  = g.proveedor_id
             WHERE g.id = ? AND g.eliminado = 0",
            [$id]
        );
    }

    /** Pagos aplicados a un gasto (historial de pagos parciales/totales) */
    public function getPagosDeGasto(int $gastoId): array
    {
        return $this->queryAll(
            "SELECT pg.monto_aplicado, pp.id AS pago_id, pp.fecha, pp.concepto, pp.monto_total, pp.estado
             FROM pagos_gastos pg
             JOIN pagos_proveedores pp ON pp.id = pg.pago_id
             WHERE pg.gasto_id = ?
             ORDER BY pp.fecha DESC",
            [$gastoId]
        );
    }

    /** Árbol de categorías (padres con hijos embebidos) */
    public function findCategorias(): array
    {
        $padres = $this->queryAll(
            "SELECT * FROM gasto_categorias WHERE parent_id IS NULL AND eliminado = 0 ORDER BY orden, nombre"
        );
        foreach ($padres as &$p) {
            $p['hijos'] = $this->queryAll(
                "SELECT * FROM gasto_categorias WHERE parent_id = :pid AND eliminado = 0 ORDER BY orden, nombre",
                [':pid' => $p['id']]
            );
        }
        return $padres;
    }

    /** Lista plana ordenada para selectores (padres primero, hijos indentados) */
    public function findCategoriasPlanas(): array
    {
        return $this->queryAll(
            "SELECT gc.id, gc.nombre, gc.tipo, gc.color, gc.parent_id,
                    gcp.nombre AS padre_nombre
             FROM gasto_categorias gc
             LEFT JOIN gasto_categorias gcp ON gcp.id = gc.parent_id
             WHERE gc.eliminado = 0
             ORDER BY COALESCE(gc.parent_id, gc.id), gc.orden, gc.nombre"
        );
    }

    /**
     * Estado de Resultados para un período.
     * Ventas − (Compras + Gastos Fijos + Gastos Variables + Impuestos)
     * Además de los totales, devuelve el desglose pagado/pendiente de cada
     * componente (facturas_compra.estado y gastos.estado) para poder mostrar
     * en el Resumen Económico qué parte de cada ítem ya se pagó y cuánto falta.
     */
    public function estadoResultados(string $desde, string $hasta): array
    {
        $ventas = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva), 0)
             FROM ventas
             WHERE estado = 'completada' AND eliminado = 0
               AND DATE(created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );

        // total_con_iva - monto_activos_fijos: los ítems cargados como Activo Fijo suman al
        // total real de la factura (para que la deuda con el proveedor quede bien si se compró
        // a crédito, ver ComprasController::guardar), pero no deben contarse acá como "Compras"
        // — ya se valúan aparte en Activos Fijos (y, si tienen vida útil, se amortizan como
        // costo fijo mensual, que sí entra en $gastosFijos más abajo).
        $compras = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva - monto_activos_fijos), 0)
             FROM facturas_compra
             WHERE estado != 'anulada' AND eliminado = 0
               AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );

        $comprasEstado = $this->queryOne(
            "SELECT
               COALESCE(SUM(CASE WHEN estado = 'pagada' THEN total_con_iva - monto_activos_fijos ELSE 0 END), 0) AS pagado
             FROM facturas_compra
             WHERE estado != 'anulada' AND eliminado = 0
               AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
        $comprasPagado    = (float)$comprasEstado['pagado'];
        $comprasPendiente = round($compras - $comprasPagado, 2);

        $porTipo = $this->queryAll(
            "SELECT gc.tipo,
                    COALESCE(SUM(g.monto), 0) AS total,
                    COALESCE(SUM(CASE WHEN g.estado = 'pagado' THEN g.monto ELSE 0 END), 0) AS pagado
             FROM gastos g
             JOIN gasto_categorias gc ON gc.id = g.categoria_id
             WHERE g.eliminado = 0 AND g.fecha BETWEEN :d AND :h
             GROUP BY gc.tipo",
            [':d' => $desde, ':h' => $hasta]
        );

        $tipoMap       = [];
        $tipoMapPagado = [];
        foreach ($porTipo as $row) {
            $tipoMap[$row['tipo']]       = (float)$row['total'];
            $tipoMapPagado[$row['tipo']] = (float)$row['pagado'];
        }

        $gastosFijos     = $tipoMap['costo_fijo']    ?? 0.0;
        $gastosVariables = $tipoMap['costo_variable'] ?? 0.0;
        $impuestos       = $tipoMap['impuesto']       ?? 0.0;

        $gastosFijosPagado     = $tipoMapPagado['costo_fijo']    ?? 0.0;
        $gastosVariablesPagado = $tipoMapPagado['costo_variable'] ?? 0.0;
        $impuestosPagado       = $tipoMapPagado['impuesto']       ?? 0.0;

        $gastosFijosPendiente     = round($gastosFijos - $gastosFijosPagado, 2);
        $gastosVariablesPendiente = round($gastosVariables - $gastosVariablesPagado, 2);
        $impuestosPendiente       = round($impuestos - $impuestosPagado, 2);

        // Desglose de compras por tipo_gasto de cada ítem del detalle (y su estado de pago)
        $comprasFijo = $comprasFijoPagado = 0.0;
        $comprasVar  = $comprasVarPagado  = 0.0;
        $comprasImp  = $comprasImpPagado  = 0.0;
        try {
            $desglose = $this->queryAll(
                "SELECT
                   CASE
                     WHEN fcd.tipo_item = 'materia_prima' THEN COALESCE(mp.tipo_gasto,  'costo_variable')
                     WHEN fcd.tipo_item = 'insumo'        THEN COALESCE(ins.tipo_gasto, 'costo_variable')
                     WHEN fcd.tipo_item = 'producto'      THEN COALESCE(p.tipo_gasto,   'costo_variable')
                     ELSE 'costo_variable'
                   END AS tipo_gasto,
                   fc.estado,
                   COALESCE(SUM(fcd.subtotal), 0) AS total
                 FROM facturas_compra_detalle fcd
                 JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
                   AND fc.estado != 'anulada' AND fc.eliminado = 0
                   AND fc.fecha BETWEEN :d AND :h
                 LEFT JOIN materias_primas mp ON fcd.tipo_item = 'materia_prima' AND fcd.materia_prima_id = mp.id
                 LEFT JOIN insumos ins        ON fcd.tipo_item = 'insumo'        AND fcd.insumo_id = ins.id
                 LEFT JOIN productos p        ON fcd.tipo_item = 'producto'      AND fcd.producto_id = p.id
                 GROUP BY 1, 2",
                [':d' => $desde, ':h' => $hasta]
            );
            foreach ($desglose as $row) {
                $monto    = (float)$row['total'];
                $esPagado = $row['estado'] === 'pagada';
                if ($row['tipo_gasto'] === 'costo_fijo') {
                    $comprasFijo += $monto;
                    if ($esPagado) $comprasFijoPagado += $monto;
                } elseif ($row['tipo_gasto'] === 'impuesto') {
                    $comprasImp += $monto;
                    if ($esPagado) $comprasImpPagado += $monto;
                } else {
                    $comprasVar += $monto;
                    if ($esPagado) $comprasVarPagado += $monto;
                }
            }
        } catch (\Throwable $e) {
            // tipo_gasto aún no existe (migración pendiente) — desglose vacío
        }
        $comprasFijoPendiente = round($comprasFijo - $comprasFijoPagado, 2);
        $comprasVarPendiente  = round($comprasVar  - $comprasVarPagado, 2);
        $comprasImpPendiente  = round($comprasImp  - $comprasImpPagado, 2);

        $totalEgresos = $compras + $gastosFijos + $gastosVariables + $impuestos;
        $resultado    = $ventas - $totalEgresos;

        return compact(
            'ventas', 'compras', 'comprasFijo', 'comprasVar', 'comprasImp',
            'gastosFijos', 'gastosVariables', 'impuestos', 'totalEgresos', 'resultado',
            'comprasPagado', 'comprasPendiente',
            'comprasFijoPagado', 'comprasFijoPendiente',
            'comprasVarPagado', 'comprasVarPendiente',
            'comprasImpPagado', 'comprasImpPendiente',
            'gastosFijosPagado', 'gastosFijosPendiente',
            'gastosVariablesPagado', 'gastosVariablesPendiente',
            'impuestosPagado', 'impuestosPendiente'
        );
    }

    /** Evolución mensual de ventas, compras y gastos para gráficos */
    public function resumenMensual(string $desde, string $hasta): array
    {
        $mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $ventasIdx = array_column($this->queryAll(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS mes, COALESCE(SUM(total_con_iva),0) AS total
             FROM ventas WHERE estado='completada' AND eliminado=0 AND DATE(created_at) BETWEEN :d AND :h
             GROUP BY mes",
            [':d' => $desde, ':h' => $hasta]
        ), 'total', 'mes');

        $comprasIdx = array_column($this->queryAll(
            "SELECT DATE_FORMAT(fecha,'%Y-%m') AS mes, COALESCE(SUM(total_con_iva - monto_activos_fijos),0) AS total
             FROM facturas_compra WHERE estado!='anulada' AND eliminado=0 AND fecha BETWEEN :d AND :h
             GROUP BY mes",
            [':d' => $desde, ':h' => $hasta]
        ), 'total', 'mes');

        $gastosIdx = array_column($this->queryAll(
            "SELECT DATE_FORMAT(fecha,'%Y-%m') AS mes, COALESCE(SUM(monto),0) AS total
             FROM gastos WHERE eliminado=0 AND fecha BETWEEN :d AND :h
             GROUP BY mes",
            [':d' => $desde, ':h' => $hasta]
        ), 'total', 'mes');

        $result = [];
        $cur    = new DateTime(date('Y-m-01', strtotime($desde)));
        $end    = new DateTime(date('Y-m-01', strtotime($hasta)));

        while ($cur <= $end) {
            $key      = $cur->format('Y-m');
            $mesIdx   = (int)$cur->format('m') - 1;
            $ventas   = (float)($ventasIdx[$key]  ?? 0);
            $egresos  = (float)($comprasIdx[$key] ?? 0) + (float)($gastosIdx[$key] ?? 0);
            $result[] = [
                'mes'      => $key,
                'label'    => $mesesEs[$mesIdx] . ' ' . $cur->format('Y'),
                'ventas'   => $ventas,
                'egresos'  => $egresos,
                'resultado'=> $ventas - $egresos,
            ];
            $cur->modify('+1 month');
        }

        return $result;
    }

    /** Gastos agrupados por categoría con totales, y su desglose pagado/pendiente */
    public function resumenPorCategoria(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT gc.id AS categoria_id, COALESCE(gcp.nombre, gc.nombre) AS grupo, gc.nombre AS categoria,
                    gc.tipo, gc.color, COALESCE(SUM(g.monto), 0) AS total,
                    COALESCE(SUM(CASE WHEN g.estado = 'pagado' THEN g.monto ELSE 0 END), 0) AS pagado,
                    COALESCE(SUM(CASE WHEN g.estado = 'pendiente' THEN g.monto ELSE 0 END), 0) AS pendiente
             FROM gastos g
             JOIN gasto_categorias gc  ON gc.id  = g.categoria_id
             LEFT JOIN gasto_categorias gcp ON gcp.id = gc.parent_id
             WHERE g.eliminado = 0 AND g.fecha BETWEEN :d AND :h
             GROUP BY g.categoria_id
             ORDER BY grupo, total DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    // ── CRUD gastos (sin updated_at en la tabla) ───────────────────────────────

    /**
     * Crea un gasto ya PAGADO en un solo paso (usado por el Chat IA cuando el usuario
     * comparte la foto de un comprobante de servicio/alquiler/impuesto/honorario que
     * no tenía un gasto "pendiente" pre-cargado en el sistema al que vincularse).
     */
    public function crearPagado(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO gastos
             (fecha, descripcion, numero_comprobante, monto, categoria_id, proveedor_id,
              comprobante_archivo, estado, usuario_id, notas)
             VALUES
             (:fecha, :descripcion, :numero_comprobante, :monto, :categoria_id, :proveedor_id,
              :comprobante_archivo, 'pagado', :usuario_id, :notas)"
        );
        $stmt->execute([
            ':fecha'               => $data['fecha'],
            ':descripcion'         => $data['descripcion'],
            ':numero_comprobante'  => $data['numero_comprobante']  ?? null,
            ':monto'               => $data['monto'],
            ':categoria_id'        => $data['categoria_id'],
            ':proveedor_id'        => $data['proveedor_id']        ?? null,
            ':comprobante_archivo' => $data['comprobante_archivo'] ?? null,
            ':usuario_id'          => $data['usuario_id'],
            ':notas'               => $data['notas']               ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca un gasto ya cargado que coincida con lo que se está por registrar, para
     * avisar de un posible duplicado antes de guardar (Chat IA). Prioridad: si hay
     * numero_comprobante, matchea por ese dato exacto (dato fuerte); si no, cae a
     * proveedor + monto exacto + misma fecha (sin ventana de días, para no marcar como
     * "duplicado" un alquiler o servicio recurrente legítimo del mes siguiente). Sin
     * proveedor_id no hay ancla suficiente para un match difuso seguro.
     */
    public function buscarDuplicado(?int $proveedorId, float $monto, string $fecha, ?string $numeroComprobante): array|false
    {
        if ($numeroComprobante) {
            $row = $this->queryOne(
                "SELECT g.id, g.fecha, g.descripcion, g.monto, u.nombre AS usuario_nombre
                 FROM gastos g
                 LEFT JOIN usuarios u ON u.id = g.usuario_id
                 WHERE g.numero_comprobante = :nro AND g.eliminado = 0
                   " . ($proveedorId ? 'AND g.proveedor_id = :pid' : '') . "
                 ORDER BY g.id DESC LIMIT 1",
                $proveedorId ? [':nro' => $numeroComprobante, ':pid' => $proveedorId] : [':nro' => $numeroComprobante]
            );
            if ($row) return $row;
        }

        if (!$proveedorId) return false;

        return $this->queryOne(
            "SELECT g.id, g.fecha, g.descripcion, g.monto, u.nombre AS usuario_nombre
             FROM gastos g
             LEFT JOIN usuarios u ON u.id = g.usuario_id
             WHERE g.proveedor_id = :pid AND g.monto = :monto AND g.fecha = :fecha AND g.eliminado = 0
             ORDER BY g.id DESC LIMIT 1",
            [':pid' => $proveedorId, ':monto' => $monto, ':fecha' => $fecha]
        );
    }

    public function updateGasto(int $id, array $data): bool
    {
        $sets   = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt   = $this->db->prepare("UPDATE gastos SET $sets WHERE id = :_id AND eliminado = 0");
        $params = $data;
        $params[':_id'] = $id;
        return $stmt->execute($params);
    }

    public function deleteGasto(int $id): bool
    {
        return $this->db->prepare("UPDATE gastos SET eliminado = 1 WHERE id = ?")->execute([$id]);
    }

    // ── CRUD gasto_categorias ─────────────────────────────────────────────────

    public function insertCategoria(array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $stmt   = $this->db->prepare("INSERT INTO gasto_categorias ($cols) VALUES ($places)");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function updateCategoria(int $id, array $data): bool
    {
        $sets   = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt   = $this->db->prepare("UPDATE gasto_categorias SET $sets WHERE id = :_id");
        $params = $data;
        $params[':_id'] = $id;
        return $stmt->execute($params);
    }

    public function deleteCategoria(int $id): bool
    {
        return $this->db->prepare("UPDATE gasto_categorias SET eliminado = 1 WHERE id = ?")->execute([$id]);
    }

    public function categoriaConHijos(int $id): bool
    {
        return (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM gasto_categorias WHERE parent_id = ? AND eliminado = 0",
            [$id]
        );
    }

    public function categoriaConGastos(int $id): bool
    {
        return (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM gastos g
             JOIN gasto_categorias gc ON gc.id = g.categoria_id
             WHERE (g.categoria_id = :a OR gc.parent_id = :b) AND g.eliminado = 0",
            [':a' => $id, ':b' => $id]
        );
    }
}
