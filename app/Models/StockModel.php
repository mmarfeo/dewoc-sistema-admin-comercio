<?php
require_once BASE_PATH . '/app/Models/Model.php';

class StockModel extends Model
{
    protected string $table      = 'stock_movimientos';
    protected string $primaryKey = 'id';

    /** Resumen de stock actual para todos los items */
    public function resumenStock(): array
    {
        $productos = $this->queryAll(
            "SELECT 'producto' AS tipo, p.id, p.nombre, p.codigo,
                    p.stock_actual, p.stock_minimo,
                    COALESCE((
                        SELECT SUM(rd.cantidad * COALESCE(
                            mp.costo_unitario * (1 + mp.desvio_pct/100),
                            ins.costo_unitario, gen.costo, 0
                        ) * (1 + rd.merma_pct / 100))
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
                        WHERE pr.producto_id = p.id
                    ), 0) AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad,
                    COALESCE(
                        (SELECT MAX(sm.created_at) FROM stock_movimientos sm WHERE sm.tipo_item='producto' AND sm.item_id=p.id),
                        p.updated_at
                    ) AS ultima_actualizacion
             FROM productos p
             LEFT JOIN categorias c ON c.id=p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1 AND p.controla_stock=1
             ORDER BY p.nombre"
        );
        $materias = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, mp.id, mp.nombre, mp.codigo,
                    mp.stock_actual, mp.stock_minimo, mp.costo_unitario AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=mp.unidad_medida_id) AS unidad,
                    COALESCE(
                        (SELECT MAX(sm.created_at) FROM stock_movimientos sm WHERE sm.tipo_item='materia_prima' AND sm.item_id=mp.id),
                        mp.updated_at
                    ) AS ultima_actualizacion
             FROM materias_primas mp
             LEFT JOIN categorias c ON c.id=mp.categoria_id
             WHERE mp.eliminado=0 AND mp.activo=1 AND mp.controla_stock=1
             ORDER BY mp.nombre"
        );
        $insumos = $this->queryAll(
            "SELECT 'insumo' AS tipo, ins.id, ins.nombre, ins.codigo,
                    ins.stock_actual, ins.stock_minimo, ins.costo_unitario AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=ins.unidad_medida_id) AS unidad,
                    COALESCE(
                        (SELECT MAX(sm.created_at) FROM stock_movimientos sm WHERE sm.tipo_item='insumo' AND sm.item_id=ins.id),
                        ins.updated_at
                    ) AS ultima_actualizacion
             FROM insumos ins
             LEFT JOIN categorias c ON c.id=ins.categoria_id
             WHERE ins.eliminado=0 AND ins.activo=1 AND ins.controla_stock=1
             ORDER BY ins.nombre"
        );
        return array_merge($productos, $materias, $insumos);
    }

    /**
     * Ajuste de stock. $data['origen'] distingue de dónde vino (default 'ajuste_manual' —
     * el modal de Stock/Productos; el Chat IA pasa 'chat_ia' — ver
     * ChatController::toolAjustarStock()), para poder responder después "¿cuándo fue el
     * último ajuste hecho desde el Chat IA?" con una consulta sobre stock_movimientos.
     */
    public function ajustar(array $data): void
    {
        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $tabla   = match($data['tipo_item']) {
                'materia_prima' => 'materias_primas',
                'insumo'        => 'insumos',
                default         => 'productos',
            };

            $stockAntes = (float)$this->queryScalar(
                "SELECT stock_actual FROM `$tabla` WHERE id=:id",
                [':id' => $data['item_id']]
            );

            $tipoMov  = $data['cantidad'] >= 0 ? 'ajuste_pos' : 'ajuste_neg';
            $stockDespues = $stockAntes + (float)$data['cantidad'];

            $conn->prepare(
                "UPDATE `$tabla` SET stock_actual=:s WHERE id=:id"
            )->execute([':s' => $stockDespues, ':id' => $data['item_id']]);

            $this->insert([
                'tipo_item'     => $data['tipo_item'],
                'item_id'       => $data['item_id'],
                'tipo_mov'      => $tipoMov,
                'origen'        => $data['origen'] ?? 'ajuste_manual',
                'cantidad'      => $data['cantidad'],
                'stock_antes'   => $stockAntes,
                'stock_despues' => $stockDespues,
                'motivo'        => $data['motivo'] ?? null,
                'usuario_id'    => $data['usuario_id'],
            ]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Reporte comparativo: ventas vs consumo de MP/insumos vs stock del período */
    public function reporteComparativo(string $desde, string $hasta): array
    {
        $ventas = $this->queryAll(
            "SELECT p.id AS producto_id, p.nombre AS producto, p.codigo,
                    SUM(vd.cantidad) AS cant_vendida,
                    SUM(vd.subtotal_con_iva) AS total_vendido,
                    p.stock_actual
             FROM ventas_detalle vd
             JOIN ventas v ON v.id=vd.venta_id
             JOIN productos p ON p.id=vd.producto_id
             WHERE v.eliminado=0 AND v.estado='completada'
               AND DATE(v.created_at) BETWEEN :d AND :h
             GROUP BY p.id, p.nombre, p.codigo, p.stock_actual
             ORDER BY cant_vendida DESC",
            [':d' => $desde, ':h' => $hasta]
        );

        $consumoMP = $this->queryAll(
            "SELECT mp.nombre AS ingrediente, 'materia_prima' AS tipo,
                    SUM(rd.cantidad * op.cantidad_producida) AS cant_consumida,
                    mp.stock_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id=mp.unidad_medida_id) AS unidad
             FROM ordenes_produccion op
             JOIN receta_detalle rd ON rd.receta_id=op.receta_id AND rd.tipo_ingrediente='materia_prima'
             JOIN materias_primas mp ON mp.id=rd.materia_prima_id
             WHERE DATE(op.created_at) BETWEEN :d AND :h AND op.estado='completada'
             GROUP BY mp.id, mp.nombre, mp.stock_actual",
            [':d' => $desde, ':h' => $hasta]
        );

        $consumoIns = $this->queryAll(
            "SELECT ins.nombre AS ingrediente, 'insumo' AS tipo,
                    SUM(rd.cantidad * op.cantidad_producida) AS cant_consumida,
                    ins.stock_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id=ins.unidad_medida_id) AS unidad
             FROM ordenes_produccion op
             JOIN receta_detalle rd ON rd.receta_id=op.receta_id AND rd.tipo_ingrediente='insumo'
             JOIN insumos ins ON ins.id=rd.insumo_id
             WHERE DATE(op.created_at) BETWEEN :d AND :h AND op.estado='completada'
             GROUP BY ins.id, ins.nombre, ins.stock_actual",
            [':d' => $desde, ':h' => $hasta]
        );

        return [
            'ventas'     => $ventas,
            'consumo_mp' => $consumoMP,
            'consumo_ins'=> $consumoIns,
        ];
    }

    /**
     * Stock teórico (calculado por movimientos del período) vs físico actual.
     * stock_inicio = stock_antes del primer movimiento en el período (o stock_actual si no hubo).
     * diferencia   = stock_fisico_actual − stock_teorico_fin
     */
    public function stockTeoricoVsFisico(string $desde, string $hasta): array
    {
        $tipos = [
            ['tipo' => 'producto',      'tabla' => 'productos',       'costoCol' => 'precio_costo'],
            ['tipo' => 'materia_prima', 'tabla' => 'materias_primas', 'costoCol' => 'costo_unitario'],
            ['tipo' => 'insumo',        'tabla' => 'insumos',         'costoCol' => 'costo_unitario'],
        ];

        $result = [];

        foreach ($tipos as $t) {
            $tipo     = $t['tipo'];
            $tabla    = $t['tabla'];
            $costoCol = $t['costoCol'];

            $items = $this->queryAll(
                "SELECT t.id, t.nombre, t.codigo, t.stock_actual, t.{$costoCol} AS costo,
                        (SELECT abreviatura FROM unidades_medida WHERE id=t.unidad_medida_id) AS unidad
                 FROM `{$tabla}` t
                 WHERE t.eliminado=0 AND t.activo=1
                 ORDER BY t.nombre"
            );
            if (!$items) continue;

            // Entradas y salidas netas por ítem en el período. OJO: `cantidad` NO es
            // confiable como signo — compra/consumo de receta/venta siempre guardan
            // ABS($delta) (positiva) y la dirección va aparte en tipo_mov; solo los
            // ajustes manuales (StockModel::ajustar()) guardan el signo real. Por eso acá
            // se clasifica por tipo_mov, no por si cantidad es positiva o negativa — antes
            // esto hacía que TODO el consumo de receta (tipo_mov='salida', cantidad
            // positiva) se contara como entrada, y "salidas" diera siempre 0.
            $movRows = $this->queryAll(
                "SELECT item_id,
                        SUM(CASE WHEN tipo_mov IN ('entrada','ajuste_pos','apertura') THEN ABS(cantidad) ELSE 0 END) AS entradas,
                        SUM(CASE WHEN tipo_mov IN ('salida','ajuste_neg') THEN ABS(cantidad) ELSE 0 END) AS salidas
                 FROM stock_movimientos
                 WHERE tipo_item = :tipo
                   AND DATE(created_at) BETWEEN :desde AND :hasta
                 GROUP BY item_id",
                [':tipo' => $tipo, ':desde' => $desde, ':hasta' => $hasta]
            );
            $movsIdx = array_column($movRows, null, 'item_id');

            // stock_antes del primer movimiento de cada ítem en el período
            $primerIdx     = [];
            $itemsConMovs  = array_keys($movsIdx);
            if ($itemsConMovs) {
                $in       = implode(',', array_map('intval', $itemsConMovs));
                $firstRows = $this->queryAll(
                    "SELECT sm.item_id, sm.stock_antes
                     FROM stock_movimientos sm
                     WHERE sm.tipo_item = :tipo
                       AND sm.id IN (
                         SELECT MIN(sm2.id) FROM stock_movimientos sm2
                         WHERE sm2.tipo_item = :tipo2
                           AND sm2.item_id IN ({$in})
                           AND DATE(sm2.created_at) BETWEEN :desde AND :hasta
                         GROUP BY sm2.item_id
                       )",
                    [':tipo' => $tipo, ':tipo2' => $tipo, ':desde' => $desde, ':hasta' => $hasta]
                );
                $primerIdx = array_column($firstRows, 'stock_antes', 'item_id');
            }

            foreach ($items as $item) {
                $id           = $item['id'];
                $mov          = $movsIdx[$id] ?? null;
                $entradas     = $mov ? (float)$mov['entradas'] : 0.0;
                $salidas      = $mov ? (float)$mov['salidas']  : 0.0;
                $stockInicio  = isset($primerIdx[$id]) ? (float)$primerIdx[$id] : (float)$item['stock_actual'];
                $stockTeorico = $stockInicio + $entradas - $salidas;
                $diferencia   = (float)$item['stock_actual'] - $stockTeorico;
                $costo        = (float)($item['costo'] ?? 0);

                $result[] = [
                    'tipo_item'           => $tipo,
                    'id'                  => $id,
                    'nombre'              => $item['nombre'],
                    'codigo'              => $item['codigo'] ?? '',
                    'unidad'              => $item['unidad'] ?? '',
                    'stock_fisico_actual' => (float)$item['stock_actual'],
                    'stock_inicio'        => $stockInicio,
                    'entradas'            => $entradas,
                    'salidas'             => $salidas,
                    'stock_teorico_fin'   => $stockTeorico,
                    'diferencia'          => $diferencia,
                    // Valor físico teórico que debería haber en stock, y cuánto vale en $ la
                    // diferencia contra lo contado — para ver de un vistazo si el desvío es
                    // significativo en plata, no solo en cantidad.
                    'costo'               => $costo,
                    'valor_teorico'       => round($stockTeorico * $costo, 2),
                    'valor_diferencia'    => round($diferencia * $costo, 2),
                ];
            }
        }

        return $result;
    }

    /**
     * Entradas/salidas/consumo neto de un período, agrupado por cada ítem cuyo nombre
     * matchea $q (ej. "Helado en balde" agrupa todos los sabores) — para responder
     * "cuánto se consumió de esta familia de ítems en tal período" sin tener que sumar
     * ítem por ítem a mano. Usa la misma clasificación por tipo_mov que
     * stockTeoricoVsFisico() (no el signo de `cantidad`, que no es confiable).
     * $tipoItem: 'producto'|'materia_prima'|'insumo'|'' (vacío = las tres).
     */
    public function consumoPorBusqueda(string $q, string $tipoItem, string $desde, string $hasta): array
    {
        $tipos = [
            ['tipo' => 'producto',      'tabla' => 'productos',       'fkCol' => 'producto_id'],
            ['tipo' => 'materia_prima', 'tabla' => 'materias_primas', 'fkCol' => 'materia_prima_id'],
            ['tipo' => 'insumo',        'tabla' => 'insumos',         'fkCol' => 'insumo_id'],
        ];
        if ($tipoItem !== '') {
            $tipos = array_values(array_filter($tipos, fn($t) => $t['tipo'] === $tipoItem));
        }

        $result = [];
        foreach ($tipos as $t) {
            $tipo  = $t['tipo'];
            $tabla = $t['tabla'];
            $fkCol = $t['fkCol'];

            $items = $this->queryAll(
                "SELECT i.id, i.nombre, i.codigo, i.stock_actual,
                        c.nombre AS categoria,
                        (SELECT abreviatura FROM unidades_medida WHERE id = i.unidad_medida_id) AS unidad,
                        COALESCE((
                            SELECT SUM(CASE WHEN sm.tipo_mov IN ('entrada','ajuste_pos','apertura') THEN ABS(sm.cantidad) ELSE 0 END)
                            FROM stock_movimientos sm
                            WHERE sm.tipo_item = :tipo AND sm.item_id = i.id
                              AND DATE(sm.created_at) BETWEEN :desde AND :hasta
                        ), 0) AS entradas,
                        COALESCE((
                            SELECT SUM(CASE WHEN sm.tipo_mov IN ('salida','ajuste_neg') THEN ABS(sm.cantidad) ELSE 0 END)
                            FROM stock_movimientos sm
                            WHERE sm.tipo_item = :tipo2 AND sm.item_id = i.id
                              AND DATE(sm.created_at) BETWEEN :desde2 AND :hasta2
                        ), 0) AS salidas,
                        (
                            SELECT MAX(fc.fecha)
                            FROM facturas_compra_detalle fcd
                            JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
                            WHERE fcd.tipo_item = :tipo3 AND fcd.`{$fkCol}` = i.id
                              AND fc.estado != 'anulada' AND fc.eliminado = 0
                        ) AS ultima_compra
                 FROM `{$tabla}` i
                 LEFT JOIN categorias c ON c.id = i.categoria_id
                 WHERE i.eliminado = 0 AND i.nombre LIKE :q
                 ORDER BY i.nombre",
                [
                    ':tipo' => $tipo, ':desde' => $desde, ':hasta' => $hasta,
                    ':tipo2' => $tipo, ':desde2' => $desde, ':hasta2' => $hasta,
                    ':tipo3' => $tipo,
                    ':q' => '%' . $q . '%',
                ]
            );

            foreach ($items as $item) {
                $entradas = (float)$item['entradas'];
                $salidas  = (float)$item['salidas'];
                // Filas sin ningún movimiento en el período no aportan nada a la pregunta
                // "cuánto se consumió" — se dejan afuera para no ensuciar el listado con
                // sabores/variantes que ni se compraron ni se vendieron esos días.
                if ($entradas <= 0.0001 && $salidas <= 0.0001) continue;

                $result[] = [
                    'tipo_item'  => $tipo,
                    'id'         => (int)$item['id'],
                    'nombre'     => $item['nombre'],
                    'codigo'     => $item['codigo'] ?? '',
                    'categoria'  => $item['categoria'] ?? '—',
                    'unidad'     => $item['unidad'] ?? '',
                    'stock_actual' => (float)$item['stock_actual'],
                    'entradas'   => $entradas,
                    'salidas'    => $salidas,
                    // Entradas - Salidas: positivo = entró más de lo que se consumió (stock
                    // creciendo neto en el período); negativo = se consumió más de lo que entró.
                    'consumo_neto' => $entradas - $salidas,
                    'ultima_compra' => $item['ultima_compra'] ?? null,
                ];
            }
        }

        usort($result, fn($a, $b) => $b['salidas'] <=> $a['salidas']);
        return $result;
    }

    /**
     * Detalle transaccional (no agregado) de qué compra puntual aumentó el stock y qué
     * venta puntual lo bajó, para los ítems que matchean $q en el período — complementa
     * consumoPorBusqueda() (que da el total por ítem) mostrando el movimiento uno por uno,
     * con link al comprobante/venta real.
     * Las ventas solo traen link cuando stock_movimientos.origen = 'venta' — el consumo de
     * receta (RecetaModel::consumirParaVenta()) recién empezó a guardar ese vínculo; el
     * consumo de ventas ANTERIORES a este cambio quedó tageado 'produccion' (igual que una
     * orden de producción manual real) y no se puede distinguir de forma confiable, así
     * que no aparece acá — es historia vieja, no un error de esta consulta.
     */
    public function movimientosDetalle(string $q, string $tipoItem, string $desde, string $hasta): array
    {
        $tipos = [
            ['tipo' => 'producto',      'tabla' => 'productos'],
            ['tipo' => 'materia_prima', 'tabla' => 'materias_primas'],
            ['tipo' => 'insumo',        'tabla' => 'insumos'],
        ];
        if ($tipoItem !== '') {
            $tipos = array_values(array_filter($tipos, fn($t) => $t['tipo'] === $tipoItem));
        }

        $compras = [];
        $ventas  = [];
        foreach ($tipos as $t) {
            $tipo  = $t['tipo'];
            $tabla = $t['tabla'];

            $items = $this->queryAll(
                "SELECT id, nombre FROM `{$tabla}` WHERE eliminado = 0 AND nombre LIKE ?",
                ['%' . $q . '%']
            );
            if (!$items) continue;
            $ids     = array_map('intval', array_column($items, 'id'));
            $nombres = array_column($items, 'nombre', 'id');
            $in      = implode(',', $ids);

            $comprasRows = $this->queryAll(
                "SELECT sm.item_id, sm.cantidad, sm.created_at,
                        fc.id AS factura_id, fc.tipo_comprobante, fc.nro_comprobante,
                        pr.razon_social AS proveedor
                 FROM stock_movimientos sm
                 LEFT JOIN facturas_compra fc ON fc.id = sm.origen_id
                 LEFT JOIN proveedores pr     ON pr.id = fc.proveedor_id
                 WHERE sm.tipo_item = :tipo AND sm.item_id IN ({$in})
                   AND sm.origen = 'compra' AND sm.tipo_mov = 'entrada'
                   AND DATE(sm.created_at) BETWEEN :desde AND :hasta
                 ORDER BY sm.created_at DESC",
                [':tipo' => $tipo, ':desde' => $desde, ':hasta' => $hasta]
            );
            foreach ($comprasRows as $r) {
                $compras[] = [
                    'tipo_item'        => $tipo,
                    'nombre'           => $nombres[$r['item_id']] ?? '',
                    'cantidad'         => (float)$r['cantidad'],
                    'fecha'            => $r['created_at'],
                    'factura_id'       => $r['factura_id'] !== null ? (int)$r['factura_id'] : null,
                    'comprobante'      => $r['tipo_comprobante'] && $r['nro_comprobante']
                        ? strtoupper($r['tipo_comprobante']) . ' ' . $r['nro_comprobante'] : null,
                    'proveedor'        => $r['proveedor'],
                ];
            }

            $ventasRows = $this->queryAll(
                "SELECT sm.item_id, sm.cantidad, sm.created_at, sm.motivo,
                        v.id AS venta_id, v.total_con_iva
                 FROM stock_movimientos sm
                 LEFT JOIN ventas v ON v.id = sm.origen_id AND sm.origen = 'venta'
                 WHERE sm.tipo_item = :tipo AND sm.item_id IN ({$in})
                   AND sm.tipo_mov = 'salida' AND sm.origen = 'venta'
                   AND DATE(sm.created_at) BETWEEN :desde AND :hasta
                 ORDER BY sm.created_at DESC",
                [':tipo' => $tipo, ':desde' => $desde, ':hasta' => $hasta]
            );
            foreach ($ventasRows as $r) {
                $ventas[] = [
                    'tipo_item' => $tipo,
                    'nombre'    => $nombres[$r['item_id']] ?? '',
                    'cantidad'  => (float)$r['cantidad'],
                    'fecha'     => $r['created_at'],
                    'venta_id'  => $r['venta_id'] !== null ? (int)$r['venta_id'] : null,
                    'motivo'    => $r['motivo'],
                ];
            }
        }

        usort($compras, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        usort($ventas, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return ['compras' => $compras, 'ventas' => $ventas];
    }

    public function historialMovimientos(int $itemId, string $tipoItem, int $limite = 50): array
    {
        return $this->queryAll(
            "SELECT sm.*, u.nombre AS usuario_nombre
             FROM stock_movimientos sm
             LEFT JOIN usuarios u ON u.id=sm.usuario_id
             WHERE sm.item_id=:id AND sm.tipo_item=:tipo
             ORDER BY sm.created_at DESC
             LIMIT :lim",
            [':id' => $itemId, ':tipo' => $tipoItem, ':lim' => $limite]
        );
    }

    /** Historial de stock_despues por fecha para gráfico Chart.js */
    public function historialItem(string $tipo, int $id, string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT DATE(created_at) AS fecha,
                    stock_despues,
                    cantidad,
                    COALESCE(motivo, tipo_mov) AS motivo
             FROM stock_movimientos
             WHERE tipo_item = :tipo AND item_id = :id
               AND DATE(created_at) BETWEEN :desde AND :hasta
             ORDER BY created_at ASC",
            [':tipo' => $tipo, ':id' => $id, ':desde' => $desde, ':hasta' => $hasta]
        );
    }

    /** Items con stock por debajo del mínimo */
    public function itemsBajoMinimo(): array
    {
        $prod = $this->queryAll(
            "SELECT 'producto' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM productos WHERE eliminado=0 AND activo=1 AND controla_stock=1 AND stock_actual<=stock_minimo"
        );
        $mp   = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM materias_primas WHERE eliminado=0 AND activo=1 AND controla_stock=1 AND stock_actual<=stock_minimo"
        );
        $ins  = $this->queryAll(
            "SELECT 'insumo' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM insumos WHERE eliminado=0 AND activo=1 AND controla_stock=1 AND stock_actual<=stock_minimo"
        );
        return array_merge($prod, $mp, $ins);
    }
}
