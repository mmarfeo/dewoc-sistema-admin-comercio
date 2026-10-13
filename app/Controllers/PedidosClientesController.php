<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/PedidoClienteModel.php';
require_once BASE_PATH . '/app/Models/PedidoMensajeModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';

/**
 * Lado STAFF de los pedidos de clientes mayoristas — aceptar/rechazar/chatear desde el POS.
 * Ver docs/portal-clientes-mayoristas-checklist.md. Gatea con el permiso 'ventas_pos' que ya
 * existe (decisión confirmada: abierto a cualquiera con acceso al POS, sin módulo nuevo).
 * Mismo split admin/público que ya usa EcommerceController (staff) vs TiendaController
 * (público) — acá PedidosClientesController es el lado staff, PortalController el del cliente.
 */
class PedidosClientesController extends Controller
{
    private PedidoClienteModel $model;
    private PedidoMensajeModel $mensajeModel;
    private VentaModel         $ventaModel;

    public function __construct()
    {
        $this->model        = new PedidoClienteModel();
        $this->mensajeModel = new PedidoMensajeModel();
        $this->ventaModel   = new VentaModel();
    }

    /** Poll cada 30s desde ventas/pos — "al primero que lo atienda": no hay asignación por turno, es el más viejo sin resolver, global. */
    public function pendienteParaMi(): void
    {
        $this->requireCanView('ventas_pos');

        $pedido = $this->model->findPendienteMasViejo();
        if (!$pedido) $this->json(['ok' => true, 'pedido' => null]);

        $this->json(['ok' => true, 'pedido' => $this->model->findByIdConDetalle((int) $pedido['id'])]);
    }

    public function aceptar(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $id = (int) $this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $usuario = Session::user();
        $turno   = $this->ventaModel->getTurnoAbierto((int) $usuario['id']);
        $this->model->aceptar($id, (int) $usuario['id'], $turno ? (int) $turno['id'] : null);

        $this->json(['ok' => true, 'pedido' => $this->model->findByIdConDetalle($id)]);
    }

    /** Rechazar exige mandar un mensaje por el chat del pedido — es el único canal, no hay campo de motivo aparte (decisión confirmada). */
    public function rechazar(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $id      = (int) $this->input('id', 0);
        $mensaje = trim($this->input('mensaje', ''));
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        if ($mensaje === '') $this->json(['ok' => false, 'error' => 'Escribí un mensaje explicando el rechazo — se lo mandamos al cliente por el chat.'], 422);

        $usuario = Session::user();
        $turno   = $this->ventaModel->getTurnoAbierto((int) $usuario['id']);
        $this->model->rechazar($id, (int) $usuario['id'], $turno ? (int) $turno['id'] : null);
        $this->mensajeModel->enviar($id, 'staff', (int) $usuario['id'], $mensaje);

        $this->json(['ok' => true]);
    }

    /** Poll no bloqueante: hay un pedido ya aceptado con mensaje del cliente sin responder? */
    public function hayMensajesSinLeer(): void
    {
        $this->requireCanView('ventas_pos');
        $pedido = $this->model->findConMensajeSinLeer();
        $this->json(['ok' => true, 'pedido' => $pedido ?: null]);
    }

    public function chat(): void
    {
        $this->requireCanView('ventas_pos');
        $pedidoId = (int) $this->param('pedido_id', 0);
        $this->mensajeModel->marcarLeidos($pedidoId, 'staff');
        $this->json(['ok' => true, 'mensajes' => $this->mensajeModel->listarPorPedido($pedidoId)]);
    }

    public function chatEnviar(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $pedidoId = (int) $this->input('pedido_id', 0);
        $mensaje  = trim($this->input('mensaje', ''));
        if (!$pedidoId || $mensaje === '') $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        $usuario = Session::user();
        $this->mensajeModel->enviar($pedidoId, 'staff', (int) $usuario['id'], $mensaje);

        $pedido = $this->model->findByIdConDetalle($pedidoId);
        $this->notificarClienteRespuesta((int) $pedido['cliente_id'], $pedidoId);

        $this->json(['ok' => true]);
    }

    /** Push + queda para el modal "tenés una respuesta" del portal (decisión confirmada: push + modal, no solo uno de los dos). */
    private function notificarClienteRespuesta(int $clienteId, int $pedidoId): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $payload = json_encode([
            'title' => 'Tenés una respuesta sobre tu pedido #' . $pedidoId,
            'body'  => 'Entrá al portal para verla.',
            'url'   => rtrim(BASE_URL, '/') . '/portal/pedido/chat?id=' . $pedidoId,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        $pushModel = new PushSubscriptionModel();
        foreach ($pushModel->findSuscripcionesCliente($clienteId) as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) $pushModel->eliminarPorId((int) $sub['id']);
            } catch (\Throwable $e) {
                logApp('Error enviando push de chat de pedido #' . $pedidoId . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }
}
