<?php
require_once BASE_PATH . '/app/Models/Model.php';

/** Chat por pedido entre cliente y staff — ver docs/portal-clientes-mayoristas-checklist.md. */
class PedidoMensajeModel extends Model
{
    protected string $table = 'pedidos_clientes_mensajes';

    public function enviar(int $pedidoId, string $autorTipo, int $autorId, string $mensaje): int
    {
        $this->db->prepare(
            'INSERT INTO pedidos_clientes_mensajes (pedido_id, autor_tipo, autor_id, mensaje) VALUES (?, ?, ?, ?)'
        )->execute([$pedidoId, $autorTipo, $autorId, $mensaje]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorPedido(int $pedidoId): array
    {
        return $this->queryAll(
            'SELECT * FROM pedidos_clientes_mensajes WHERE pedido_id = ? ORDER BY created_at ASC',
            [$pedidoId]
        );
    }

    /** Marca como leídos los mensajes del OTRO lado (leer los propios no tiene sentido). */
    public function marcarLeidos(int $pedidoId, string $autorTipoQueLee): void
    {
        $otro = $autorTipoQueLee === 'cliente' ? 'staff' : 'cliente';
        $this->db->prepare(
            "UPDATE pedidos_clientes_mensajes SET leido_at = NOW() WHERE pedido_id = ? AND autor_tipo = ? AND leido_at IS NULL"
        )->execute([$pedidoId, $otro]);
    }

    /**
     * IDs de pedidos de este cliente con mensajes del staff sin leer, más reciente primero —
     * el aviso del portal (modalAvisoChatPortal) necesita saber A CUÁL pedido llevar al
     * cliente, no solo si hay algo sin leer (antes mandaba siempre a /portal/pedidos, que no
     * marca nada como leído, así que el aviso volvía a aparecer en bucle).
     */
    public function pedidosNoLeidosParaCliente(int $clienteId): array
    {
        return array_column($this->queryAll(
            "SELECT DISTINCT p.id
             FROM pedidos_clientes_mensajes m
             JOIN pedidos_clientes p ON p.id = m.pedido_id
             WHERE p.cliente_id = ? AND m.autor_tipo = 'staff' AND m.leido_at IS NULL
             ORDER BY p.id DESC",
            [$clienteId]
        ), 'id');
    }

    /** Mensajes de cliente sin responder, en pedidos ya aceptados — aviso no bloqueante al staff (decisión confirmada). */
    public function hayNoLeidosParaStaff(): bool
    {
        return (bool) $this->queryScalar(
            "SELECT COUNT(*) FROM pedidos_clientes_mensajes m
             JOIN pedidos_clientes p ON p.id = m.pedido_id
             WHERE m.autor_tipo = 'cliente' AND m.leido_at IS NULL AND p.estado != 'pendiente'"
        );
    }
}
