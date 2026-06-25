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
                    p.stock_actual, p.stock_minimo, p.costo_unitario AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad
             FROM productos p
             LEFT JOIN categorias c ON c.id=p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1
             ORDER BY p.nombre"
        );
        $materias = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, mp.id, mp.nombre, mp.codigo,
                    mp.stock_actual, mp.stock_minimo, mp.costo_unitario AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=mp.unidad_medida_id) AS unidad
             FROM materias_primas mp
             LEFT JOIN categorias c ON c.id=mp.categoria_id
             WHERE mp.eliminado=0 AND mp.activo=1
             ORDER BY mp.nombre"
        );
        $insumos = $this->queryAll(
            "SELECT 'insumo' AS tipo, ins.id, ins.nombre, ins.codigo,
                    ins.stock_actual, ins.stock_minimo, ins.costo_unitario AS costo,
                    c.nombre AS categoria,
                    (SELECT abreviatura FROM unidades_medida WHERE id=ins.unidad_medida_id) AS unidad
             FROM insumos ins
             LEFT JOIN categorias c ON c.id=ins.categoria_id
             WHERE ins.eliminado=0 AND ins.activo=1
             ORDER BY ins.nombre"
        );
        return array_merge($productos, $materias, $insumos);
    }

    /** Ajuste manual de stock */
    public function ajustar(array $data): void
    {
        $db   = Database::getInstance();
        $conn = $db->getConnection();
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
                'origen'        => 'ajuste_manual',
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
            "SELECT p.nombre AS producto, p.codigo,
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
            ['tipo' => 'producto',      'tabla' => 'productos'],
            ['tipo' => 'materia_prima', 'tabla' => 'materias_primas'],
            ['tipo' => 'insumo',        'tabla' => 'insumos'],
        ];

        $result = [];

        foreach ($tipos as $t) {
            $tipo  = $t['tipo'];
            $tabla = $t['tabla'];

            $items = $this->queryAll(
                "SELECT t.id, t.nombre, t.codigo, t.stock_actual,
                        (SELECT abreviatura FROM unidades_medida WHERE id=t.unidad_medida_id) AS unidad
                 FROM `{$tabla}` t
                 WHERE t.eliminado=0 AND t.activo=1
                 ORDER BY t.nombre"
            );
            if (!$items) continue;

            // Entradas y salidas netas por ítem en el período
            $movRows = $this->queryAll(
                "SELECT item_id,
                        SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) AS entradas,
                        ABS(SUM(CASE WHEN cantidad < 0 THEN cantidad ELSE 0 END)) AS salidas
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
                ];
            }
        }

        return $result;
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
            "SELECT 'producto' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM productos WHERE eliminado=0 AND activo=1 AND stock_actual<=stock_minimo"
        );
        $mp   = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM materias_primas WHERE eliminado=0 AND activo=1 AND stock_actual<=stock_minimo"
        );
        $ins  = $this->queryAll(
            "SELECT 'insumo' AS tipo, nombre, codigo, stock_actual, stock_minimo FROM insumos WHERE eliminado=0 AND activo=1 AND stock_actual<=stock_minimo"
        );
        return array_merge($prod, $mp, $ins);
    }
}
