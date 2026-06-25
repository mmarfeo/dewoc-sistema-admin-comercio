<?php
require_once BASE_PATH . '/app/Models/Model.php';

class VentaModel extends Model
{
    protected string $table      = 'ventas';
    protected string $primaryKey = 'id';

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['v.eliminado = 0'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]  = 'DATE(v.created_at) >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'DATE(v.created_at) <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'v.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['turno_id'])) {
            $where[]  = 'v.turno_id = :turno_id';
            $params[':turno_id'] = (int)$filtros['turno_id'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT v.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre,
                    t.numero AS turno_numero
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             LEFT JOIN turnos   t ON t.id = v.turno_id
             WHERE $cond
             ORDER BY v.id DESC",
            $params
        );
    }

    public function findByIdConDetalle(int $id): ?array
    {
        $venta = $this->queryOne(
            "SELECT v.*, u.nombre AS usuario_nombre,
                    c.nombre AS cliente_nombre, c.documento AS cliente_doc,
                    c.condicion_iva AS cliente_civa,
                    c.telefono AS cliente_telefono, c.email AS cliente_email
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE v.id = :id AND v.eliminado = 0",
            [':id' => $id]
        );
        if (!$venta) return null;

        $venta['detalle'] = $this->queryAll(
            "SELECT vd.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad
             FROM ventas_detalle vd
             JOIN productos p ON p.id = vd.producto_id
             WHERE vd.venta_id = :id
             ORDER BY vd.id",
            [':id' => $id]
        );

        return $venta;
    }

    public function registrar(array $cabecera, array $detalle): int
    {
        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $ventaId = $this->insert($cabecera);

            $stmtDet = $conn->prepare(
                "INSERT INTO ventas_detalle
                    (venta_id, producto_id, cantidad, precio_unitario, descuento_pct, subtotal, iva_porcentaje, subtotal_con_iva)
                 VALUES (:vid, :pid, :cant, :precio, :desc, :sub, :iva, :sub_iva)"
            );

            foreach ($detalle as $item) {
                $stmtDet->execute([
                    ':vid'    => $ventaId,
                    ':pid'    => (int)$item['producto_id'],
                    ':cant'   => (float)$item['cantidad'],
                    ':precio' => (float)$item['precio_unitario'],
                    ':desc'   => (float)($item['descuento_pct'] ?? 0),
                    ':sub'    => (float)$item['subtotal'],
                    ':iva'    => (float)$item['iva_porcentaje'],
                    ':sub_iva'=> (float)$item['subtotal_con_iva'],
                ]);

                // Descontar stock
                $conn->prepare(
                    "UPDATE productos SET stock_actual=stock_actual-:c WHERE id=:id"
                )->execute([':c' => (float)$item['cantidad'], ':id' => (int)$item['producto_id']]);
            }

            // Registrar movimiento en caja si hay turno abierto
            if (!empty($cabecera['turno_id'])) {
                $conn->prepare(
                    "INSERT INTO caja_movimientos
                        (turno_id, tipo, monto, descripcion, venta_id, usuario_id, created_at, updated_at)
                     VALUES (:tid, 'ingreso', :monto, :desc, :vid, :uid, NOW(), NOW())"
                )->execute([
                    ':tid'  => $cabecera['turno_id'],
                    ':monto'=> $cabecera['total_con_iva'],
                    ':desc' => 'Venta #' . $ventaId,
                    ':vid'  => $ventaId,
                    ':uid'  => $cabecera['usuario_id'],
                ]);
            }

            $conn->commit();
            return $ventaId;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Guarda el resultado AFIP (CAE) en la venta */
    public function updateAfip(int $id, array $afip): void
    {
        $this->update($id, [
            'afip_tipo_cbte' => $afip['tipo_cbte'] ?? null,
            'afip_pto_vta'   => $afip['pto_vta']   ?? null,
            'afip_nro_cbte'  => $afip['nro_cbte']  ?? null,
            'afip_cae'       => $afip['cae']        ?: null,
            'afip_cae_vto'   => $afip['cae_vto']   ?? null,
            'afip_resultado' => $afip['resultado']  ?? null,
            'afip_obs'       => $afip['obs']        ?: null,
        ]);
    }

    public function anular(int $id): bool
    {
        $venta = $this->findByIdConDetalle($id);
        if (!$venta || $venta['estado'] === 'anulada') return false;

        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $this->update($id, ['estado' => 'anulada']);

            foreach ($venta['detalle'] as $it) {
                $conn->prepare(
                    "UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id"
                )->execute([':c' => (float)$it['cantidad'], ':id' => (int)$it['producto_id']]);
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function buscarProductos(string $q): array
    {
        $like = "%$q%";
        return $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.precio_venta, p.iva_porcentaje,
                    p.stock_actual, p.stock_minimo,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad,
                    c.nombre AS categoria
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1
               AND (p.nombre LIKE :q OR p.codigo LIKE :q2)
             ORDER BY p.nombre
             LIMIT 30",
            [':q' => $like, ':q2' => $like]
        );
    }

    public function getTurnoAbierto(int $usuarioId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM turnos WHERE usuario_id=:uid AND estado='abierto' AND eliminado=0 LIMIT 1",
            [':uid' => $usuarioId]
        );
    }

    /** KPIs para el dashboard */
    public function totalesDelDia(): array
    {
        $hoy = date('Y-m-d');
        return [
            'total'    => (float)$this->queryScalar(
                "SELECT COALESCE(SUM(total_con_iva),0) FROM ventas WHERE eliminado=0 AND estado='completada' AND DATE(created_at)=:h",
                [':h' => $hoy]
            ),
            'cantidad' => (int)$this->queryScalar(
                "SELECT COUNT(*) FROM ventas WHERE eliminado=0 AND estado='completada' AND DATE(created_at)=:h",
                [':h' => $hoy]
            ),
        ];
    }
}
