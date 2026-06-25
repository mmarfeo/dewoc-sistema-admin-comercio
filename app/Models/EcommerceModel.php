<?php
require_once BASE_PATH . '/app/Models/Model.php';

class EcommerceModel extends Model
{
    protected string $table = 'pedidos_ecommerce';

    // ── Catálogo público ─────────────────────────────────────────────────────

    public function getProductosPublicos(array $filtros = []): array
    {
        $where  = ["p.eliminado = 0 AND p.activo = 1 AND p.ecommerce = 1"];
        $params = [];

        if (!empty($filtros['categoria_id'])) {
            $where[]                  = 'p.categoria_id = :cat';
            $params[':cat']           = (int)$filtros['categoria_id'];
        }
        if (!empty($filtros['q'])) {
            $where[]              = '(p.nombre LIKE :q OR p.descripcion LIKE :q)';
            $params[':q']         = '%' . $filtros['q'] . '%';
        }

        return $this->queryAll(
            'SELECT p.id, p.nombre, p.descripcion, p.precio_venta, p.iva,
                    p.imagen, p.stock_actual, p.stock_minimo, p.unidad_medida_id,
                    c.nombre AS categoria
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.nombre',
            $params
        );
    }

    public function getProductoPublico(int $id): array|false
    {
        return $this->queryOne(
            'SELECT p.*, c.nombre AS categoria
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = ? AND p.eliminado = 0 AND p.activo = 1 AND p.ecommerce = 1',
            [$id]
        );
    }

    public function getCategoriasConProductos(): array
    {
        return $this->queryAll(
            "SELECT c.id, c.nombre, COUNT(p.id) AS total_productos
             FROM categorias c
             JOIN productos p ON p.categoria_id = c.id
             WHERE c.eliminado = 0 AND c.activo = 1
               AND p.eliminado = 0 AND p.activo = 1 AND p.ecommerce = 1
               AND (c.tipo = 'producto' OR c.tipo IS NULL)
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre"
        );
    }

    // ── Pedidos ───────────────────────────────────────────────────────────────

    public function crearPedido(array $cabecera, array $items): int
    {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO pedidos_ecommerce
                 (cliente_nombre, cliente_email, cliente_telefono, cliente_direccion,
                  subtotal, total_iva, total_con_iva, estado, notas, eliminado, created_at, updated_at)
                 VALUES (:cn, :ce, :ct, :cd, :sub, :tiva, :total, :est, :notas, 0, NOW(), NOW())'
            );
            $stmt->execute([
                ':cn'    => $cabecera['cliente_nombre'],
                ':ce'    => $cabecera['cliente_email'],
                ':ct'    => $cabecera['cliente_telefono'] ?? null,
                ':cd'    => $cabecera['cliente_direccion'] ?? null,
                ':sub'   => $cabecera['subtotal'],
                ':tiva'  => $cabecera['total_iva'],
                ':total' => $cabecera['total_con_iva'],
                ':est'   => 'pendiente',
                ':notas' => $cabecera['notas'] ?? null,
            ]);
            $pedidoId = (int)$db->lastInsertId();

            $stmtDet = $db->prepare(
                'INSERT INTO pedidos_ecommerce_detalle
                 (pedido_id, producto_id, cantidad, precio_unitario, iva_porcentaje, subtotal_con_iva)
                 VALUES (:pid, :prod, :cant, :prec, :iva, :sub)'
            );
            foreach ($items as $item) {
                $stmtDet->execute([
                    ':pid'  => $pedidoId,
                    ':prod' => $item['producto_id'],
                    ':cant' => $item['cantidad'],
                    ':prec' => $item['precio_unitario'],
                    ':iva'  => $item['iva_porcentaje'],
                    ':sub'  => $item['subtotal_con_iva'],
                ]);
            }

            $db->commit();
            return $pedidoId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizarPago(int $pedidoId, array $datos): void
    {
        $this->db->prepare(
            'UPDATE pedidos_ecommerce SET
               estado = :estado,
               mp_preference_id = :pref,
               mp_payment_id    = :pay,
               mp_status        = :mps,
               updated_at       = NOW()
             WHERE id = :id'
        )->execute([
            ':estado' => $datos['estado'],
            ':pref'   => $datos['preference_id'] ?? null,
            ':pay'    => $datos['payment_id'] ?? null,
            ':mps'    => $datos['mp_status'] ?? null,
            ':id'     => $pedidoId,
        ]);
    }

    public function findById(int $id): array|false
    {
        return $this->queryOne(
            'SELECT * FROM pedidos_ecommerce WHERE id = ? AND eliminado = 0',
            [$id]
        );
    }

    public function findByPreferenceId(string $preferenceId): array|false
    {
        return $this->queryOne(
            'SELECT * FROM pedidos_ecommerce WHERE mp_preference_id = ? AND eliminado = 0',
            [$preferenceId]
        );
    }

    public function registrarPagoMP(array $datos): void
    {
        $this->db->prepare(
            'INSERT INTO pagos_mercadopago
             (pedido_id, payment_id, status, status_detail, payer_email,
              amount, payment_type, created_at, updated_at)
             VALUES (:pid, :pay, :st, :sd, :email, :amt, :type, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = :st, status_detail = :sd, updated_at = NOW()'
        )->execute([
            ':pid'   => $datos['pedido_id'],
            ':pay'   => $datos['payment_id'],
            ':st'    => $datos['status'],
            ':sd'    => $datos['status_detail'] ?? '',
            ':email' => $datos['payer_email'] ?? null,
            ':amt'   => $datos['amount'],
            ':type'  => $datos['payment_type'] ?? null,
        ]);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function findAllAdmin(array $filtros = []): array
    {
        $where  = ['p.eliminado = 0'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[]           = 'p.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['desde'])) {
            $where[]           = 'DATE(p.created_at) >= :desde';
            $params[':desde']  = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]           = 'DATE(p.created_at) <= :hasta';
            $params[':hasta']  = $filtros['hasta'];
        }

        return $this->queryAll(
            'SELECT p.id, p.cliente_nombre, p.cliente_email, p.total_con_iva,
                    p.estado, p.mp_status, p.created_at
             FROM pedidos_ecommerce p
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.created_at DESC',
            $params
        );
    }

    public function findByIdConDetalle(int $id): array|false
    {
        $pedido = $this->queryOne(
            'SELECT * FROM pedidos_ecommerce WHERE id = ? AND eliminado = 0',
            [$id]
        );
        if (!$pedido) return false;

        $pedido['detalle'] = $this->queryAll(
            'SELECT d.*, pr.nombre AS producto_nombre, pr.imagen
             FROM pedidos_ecommerce_detalle d
             JOIN productos pr ON pr.id = d.producto_id
             WHERE d.pedido_id = ?',
            [$id]
        );
        return $pedido;
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $estadosValidos = ['pendiente', 'pagado', 'en_preparacion', 'enviado', 'entregado', 'cancelado'];
        if (!in_array($estado, $estadosValidos, true)) return;

        $this->db->prepare(
            'UPDATE pedidos_ecommerce SET estado = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$estado, $id]);
    }

    /**
     * Descuenta del stock de productos los ítems de un pedido.
     * Solo se llama cuando el pedido pasa a estado "pagado".
     * Si el stock_actual ya fue descontado (stock_descontado = 1), no repite.
     */
    public function descontarStock(int $pedidoId): void
    {
        $pedido = $this->queryOne(
            'SELECT id, stock_descontado FROM pedidos_ecommerce WHERE id = ?',
            [$pedidoId]
        );
        if (!$pedido || !empty($pedido['stock_descontado'])) {
            return; // ya descontado o no existe
        }

        $items = $this->queryAll(
            'SELECT producto_id, cantidad FROM pedidos_ecommerce_detalle WHERE pedido_id = ?',
            [$pedidoId]
        );

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->db->prepare(
                    'UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?'
                )->execute([$item['cantidad'], $item['producto_id'], $item['cantidad']]);

                $this->db->prepare(
                    "INSERT INTO stock_movimientos
                     (tipo_item, item_id, tipo_movimiento, cantidad, stock_antes, stock_despues,
                      origen, usuario_id, notas, created_at)
                     SELECT 'producto', id,  'salida', ?,
                            stock_actual + ?, stock_actual,
                            'venta', NULL, CONCAT('Pedido ecommerce #', ?), NOW()
                     FROM productos WHERE id = ?"
                )->execute([$item['cantidad'], $item['cantidad'], $pedidoId, $item['producto_id']]);
            }

            $this->db->prepare(
                'UPDATE pedidos_ecommerce SET stock_descontado = 1, updated_at = NOW() WHERE id = ?'
            )->execute([$pedidoId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

}
