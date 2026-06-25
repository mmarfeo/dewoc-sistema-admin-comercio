<?php
require_once BASE_PATH . '/app/Models/Model.php';

class FacturaCompraModel extends Model
{
    protected string $table      = 'facturas_compra';
    protected string $primaryKey = 'id';

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['fc.eliminado = 0'];
        $params = [];

        if (!empty($filtros['proveedor_id'])) {
            $where[]  = 'fc.proveedor_id = :proveedor_id';
            $params[':proveedor_id'] = (int)$filtros['proveedor_id'];
        }
        if (!empty($filtros['desde'])) {
            $where[]  = 'fc.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'fc.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'fc.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT fc.*, p.razon_social AS proveedor_nombre,
                    u.nombre AS usuario_nombre
             FROM facturas_compra fc
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             LEFT JOIN usuarios    u ON u.id = fc.usuario_id
             WHERE $cond
             ORDER BY fc.fecha DESC, fc.id DESC",
            $params
        );
    }

    public function findByIdConDetalle(int $id): ?array
    {
        $cabecera = $this->queryOne(
            "SELECT fc.*, p.razon_social AS proveedor_nombre,
                    p.cuit, p.condicion_iva, p.direccion AS proveedor_direccion
             FROM facturas_compra fc
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE fc.id = :id AND fc.eliminado = 0",
            [':id' => $id]
        );
        if (!$cabecera) return null;

        $detalle = $this->queryAll(
            "SELECT fcd.*,
                    COALESCE(mp.nombre, ins.nombre, prod.nombre) AS item_nombre,
                    COALESCE(mp.codigo, ins.codigo, prod.codigo) AS item_codigo,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_p.abreviatura) AS unidad
             FROM facturas_compra_detalle fcd
             LEFT JOIN materias_primas mp   ON mp.id  = fcd.materia_prima_id
             LEFT JOIN insumos         ins  ON ins.id = fcd.insumo_id
             LEFT JOIN productos       prod ON prod.id= fcd.producto_id
             LEFT JOIN unidades_medida um_mp  ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_p   ON um_p.id  = prod.unidad_medida_id
             WHERE fcd.factura_compra_id = :id
             ORDER BY fcd.id",
            [':id' => $id]
        );

        $cabecera['detalle'] = $detalle;
        return $cabecera;
    }

    public function guardar(array $cabecera, array $detalle): int
    {
        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            // Insertar cabecera
            $facturaId = $this->insert($cabecera);

            // Insertar detalle y actualizar stock con costo promedio ponderado
            $stmtDet = $conn->prepare(
                "INSERT INTO facturas_compra_detalle
                    (factura_compra_id, tipo_item, materia_prima_id, insumo_id, producto_id,
                     cantidad, precio_unitario, subtotal, iva_porcentaje, subtotal_con_iva)
                 VALUES (:fid, :tipo, :mp, :ins, :prod, :cant, :precio, :sub, :iva, :sub_iva)"
            );

            foreach ($detalle as $item) {
                $stmtDet->execute([
                    ':fid'    => $facturaId,
                    ':tipo'   => $item['tipo_item'],
                    ':mp'     => $item['materia_prima_id'] ?: null,
                    ':ins'    => $item['insumo_id'] ?: null,
                    ':prod'   => $item['producto_id'] ?: null,
                    ':cant'   => $item['cantidad'],
                    ':precio' => $item['precio_unitario'],
                    ':sub'    => $item['subtotal'],
                    ':iva'    => $item['iva_porcentaje'],
                    ':sub_iva'=> $item['subtotal_con_iva'],
                ]);

                $this->actualizarStock($item, true);
            }

            $conn->commit();
            return $facturaId;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza stock del ítem. Con $calcularCosto=true aplica Costo Promedio
     * Ponderado (CPP) cuando la cantidad es positiva (entrada de mercadería).
     * Las anulaciones (cantidad negativa) nunca recalculan el costo.
     */
    private function actualizarStock(array $item, bool $calcularCosto = false): void
    {
        $cant   = (float)$item['cantidad'];
        $precio = (float)($item['precio_unitario'] ?? 0);

        if ($item['materia_prima_id']) {
            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE materias_primas
                     SET costo_unitario = (stock_actual * costo_unitario + :cant * :precio) / NULLIF(stock_actual + :cant2, 0),
                         stock_actual   = stock_actual + :cant3
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cant2' => $cant, ':cant3' => $cant,
                     ':id'   => (int)$item['materia_prima_id']]
                );
            } else {
                $this->query(
                    "UPDATE materias_primas SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cant, ':id' => (int)$item['materia_prima_id']]
                );
            }
        } elseif ($item['insumo_id']) {
            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE insumos
                     SET costo_unitario = (stock_actual * costo_unitario + :cant * :precio) / NULLIF(stock_actual + :cant2, 0),
                         stock_actual   = stock_actual + :cant3
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cant2' => $cant, ':cant3' => $cant,
                     ':id'   => (int)$item['insumo_id']]
                );
            } else {
                $this->query(
                    "UPDATE insumos SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cant, ':id' => (int)$item['insumo_id']]
                );
            }
        } elseif ($item['producto_id']) {
            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE productos
                     SET precio_costo = (stock_actual * precio_costo + :cant * :precio) / NULLIF(stock_actual + :cant2, 0),
                         stock_actual = stock_actual + :cant3
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cant2' => $cant, ':cant3' => $cant,
                     ':id'   => (int)$item['producto_id']]
                );
            } else {
                $this->query(
                    "UPDATE productos SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cant, ':id' => (int)$item['producto_id']]
                );
            }
        }
    }

    public function anular(int $id, int $usuarioId): bool
    {
        $factura = $this->findByIdConDetalle($id);
        if (!$factura || $factura['estado'] === 'anulada') return false;

        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $this->update($id, ['estado' => 'anulada']);

            // Revertir stock
            foreach ($factura['detalle'] as $item) {
                $item['cantidad'] = -1 * (float)$item['cantidad'];
                $this->actualizarStock($item);
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function buscarItems(string $q, string $tipo = ''): array
    {
        $q      = "%$q%";
        $params = [':q1' => $q, ':q2' => $q];
        $rows   = [];

        if (!$tipo || $tipo === 'materia_prima') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'materia_prima' AS tipo, stock_actual, costo_unitario,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM materias_primas WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", $params
            ));
        }
        if (!$tipo || $tipo === 'insumo') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'insumo' AS tipo, stock_actual, costo_unitario,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM insumos WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", $params
            ));
        }
        if (!$tipo || $tipo === 'producto') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'producto' AS tipo, stock_actual, precio_costo AS costo_unitario,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM productos WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", [':q1' => $q, ':q2' => $q]
            ));
        }
        return $rows;
    }

    public function existeDuplicado(int $proveedorId, string $nroComprobante, ?int $excluirId = null): bool
    {
        if (!$nroComprobante) return false;

        $sql    = 'SELECT COUNT(*) FROM facturas_compra WHERE proveedor_id = :pid AND nro_comprobante = :nro AND eliminado = 0';
        $params = [':pid' => $proveedorId, ':nro' => $nroComprobante];

        if ($excluirId) {
            $sql         .= ' AND id != :eid';
            $params[':eid'] = $excluirId;
        }

        return (int)$this->queryScalar($sql, $params) > 0;
    }

    public function totalPorPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva),0) FROM facturas_compra
             WHERE eliminado=0 AND estado='pagada' AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
    }
}
