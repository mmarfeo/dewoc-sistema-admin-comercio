<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Pedidos del Portal de Clientes Mayoristas — ver docs/portal-clientes-mayoristas-checklist.md.
 * Ciclo de vida: pendiente → aceptado/rechazado → (aceptado) convertido, una vez que el
 * cajero lo cobra como venta real. "Al primero que lo atienda" (decisión confirmada): no
 * hay asignación por turno al crear el pedido, cualquier caja con turno abierto lo ve y el
 * primero que actúa lo resuelve para todos.
 */
class PedidoClienteModel extends Model
{
    protected string $table = 'pedidos_clientes';

    /**
     * $items: [{producto_id, cantidad, precio_unitario, iva_porcentaje, opciones:[{opcion_id,cantidad_elegida}]}]
     * precio_unitario ya viene resuelto (precio_especial del cliente si tenía, si no precio_venta normal) —
     * se congela acá, no se recalcula después (decisión confirmada).
     */
    public function crear(int $clienteId, ?string $notas, array $items): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO pedidos_clientes (cliente_id, notas_cliente) VALUES (?, ?)')
                ->execute([$clienteId, $notas]);
            $pedidoId = (int) $this->db->lastInsertId();

            $stmtDet = $this->db->prepare(
                'INSERT INTO pedidos_clientes_detalle (pedido_id, producto_id, cantidad, precio_unitario, iva_porcentaje)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmtOp = $this->db->prepare(
                'INSERT INTO pedidos_clientes_detalle_opciones (pedido_cliente_detalle_id, opcion_id, cantidad_elegida)
                 VALUES (?, ?, ?)'
            );
            foreach ($items as $it) {
                $stmtDet->execute([
                    $pedidoId, (int) $it['producto_id'], (float) $it['cantidad'],
                    (float) $it['precio_unitario'], (float) ($it['iva_porcentaje'] ?? 21),
                ]);
                $detalleId = (int) $this->db->lastInsertId();
                foreach ($it['opciones'] ?? [] as $op) {
                    $stmtOp->execute([$detalleId, (int) $op['opcion_id'], max(1, (int) ($op['cantidad_elegida'] ?? 1))]);
                }
            }
            $this->db->commit();
            return $pedidoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** El pedido pendiente más viejo sin resolver — "al primero que lo atienda" es global, no por usuario/turno. */
    public function findPendienteMasViejo(): array|false
    {
        return $this->queryOne(
            "SELECT pc.*, c.nombre AS cliente_nombre
             FROM pedidos_clientes pc
             JOIN clientes c ON c.id = pc.cliente_id
             WHERE pc.estado = 'pendiente'
             ORDER BY pc.created_at ASC
             LIMIT 1"
        );
    }

    /** Pedido con su detalle completo (productos + opciones elegidas), para el modal del POS y el chat. */
    public function findByIdConDetalle(int $id): array|false
    {
        $pedido = $this->queryOne(
            "SELECT pc.*, c.nombre AS cliente_nombre
             FROM pedidos_clientes pc JOIN clientes c ON c.id = pc.cliente_id
             WHERE pc.id = ?",
            [$id]
        );
        if (!$pedido) return false;

        $detalle = $this->queryAll(
            "SELECT pcd.id, pcd.producto_id, pcd.cantidad, pcd.precio_unitario, pcd.iva_porcentaje,
                    p.nombre AS producto_nombre, p.controla_stock, p.stock_actual
             FROM pedidos_clientes_detalle pcd
             JOIN productos p ON p.id = pcd.producto_id
             WHERE pcd.pedido_id = ?
             ORDER BY pcd.id",
            [$id]
        );
        foreach ($detalle as &$linea) {
            $linea['opciones'] = $this->queryAll(
                "SELECT pcdo.opcion_id, pcdo.cantidad_elegida, o.nombre
                 FROM pedidos_clientes_detalle_opciones pcdo
                 JOIN opciones o ON o.id = pcdo.opcion_id
                 WHERE pcdo.pedido_cliente_detalle_id = ?",
                [$linea['id']]
            );
        }
        unset($linea);
        $pedido['detalle'] = $detalle;
        return $pedido;
    }

    public function aceptar(int $id, int $usuarioId, ?int $turnoId): void
    {
        $this->db->prepare(
            "UPDATE pedidos_clientes SET estado = 'aceptado', usuario_id = ?, turno_id = ?, resuelto_at = NOW() WHERE id = ? AND estado = 'pendiente'"
        )->execute([$usuarioId, $turnoId, $id]);
    }

    public function rechazar(int $id, int $usuarioId, ?int $turnoId): void
    {
        $this->db->prepare(
            "UPDATE pedidos_clientes SET estado = 'rechazado', usuario_id = ?, turno_id = ?, resuelto_at = NOW() WHERE id = ? AND estado = 'pendiente'"
        )->execute([$usuarioId, $turnoId, $id]);
    }

    public function marcarConvertido(int $id, int $ventaId): void
    {
        $this->db->prepare("UPDATE pedidos_clientes SET estado = 'convertido', venta_id = ? WHERE id = ?")
            ->execute([$ventaId, $id]);
    }

    /** La venta en la que se había convertido este pedido se anuló en el POS — ver VentaModel::anular(). */
    public function marcarAnulado(int $id): void
    {
        $this->db->prepare("UPDATE pedidos_clientes SET estado = 'anulado' WHERE id = ?")->execute([$id]);
    }

    /**
     * Pedido ya aceptado con un mensaje del cliente sin responder — aviso no bloqueante al
     * staff (decisión confirmada: "también dispara aviso" cuando el cliente escribe y nadie
     * contestó, a diferencia del modal bloqueante que es solo para el pedido nuevo).
     */
    public function findConMensajeSinLeer(): array|false
    {
        return $this->queryOne(
            "SELECT pc.id, c.nombre AS cliente_nombre
             FROM pedidos_clientes pc
             JOIN clientes c ON c.id = pc.cliente_id
             WHERE pc.estado != 'pendiente' AND EXISTS (
                 SELECT 1 FROM pedidos_clientes_mensajes m
                 WHERE m.pedido_id = pc.id AND m.autor_tipo = 'cliente' AND m.leido_at IS NULL
             )
             ORDER BY pc.resuelto_at ASC
             LIMIT 1"
        );
    }

    /** "Mis pedidos" del cliente en el portal. */
    public function listarPorCliente(int $clienteId): array
    {
        return $this->queryAll(
            "SELECT pc.id, pc.estado, pc.notas_cliente, pc.created_at, pc.resuelto_at, pc.venta_id,
                    EXISTS(
                        SELECT 1 FROM pedidos_clientes_mensajes m
                        WHERE m.pedido_id = pc.id AND m.autor_tipo = 'staff' AND m.leido_at IS NULL
                    ) AS tiene_no_leido
             FROM pedidos_clientes pc WHERE pc.cliente_id = ? ORDER BY pc.created_at DESC",
            [$clienteId]
        );
    }
}
