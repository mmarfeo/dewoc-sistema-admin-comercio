<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/EcommerceModel.php';
require_once BASE_PATH . '/core/Mailer.php';

class TiendaController extends Controller
{
    private const LAYOUT = 'layouts.tienda';
    private EcommerceModel $model;

    public function __construct()
    {
        $this->model = new EcommerceModel();
    }

    public function index(): void
    {
        $filtros = [
            'q'            => trim($this->param('q') ?? ''),
            'categoria_id' => (int)($this->param('categoria_id') ?? 0),
        ];

        $this->render('tienda.index', [
            'pageTitle'  => TIENDA_NOMBRE,
            'productos'  => $this->model->getProductosPublicos($filtros),
            'categorias' => $this->model->getCategoriasConProductos(),
            'filtros'    => $filtros,
        ], self::LAYOUT);
    }

    public function categoria(): void
    {
        $catId   = (int)$this->param('id');
        $filtros = ['categoria_id' => $catId];

        $this->render('tienda.index', [
            'pageTitle'  => TIENDA_NOMBRE,
            'productos'  => $this->model->getProductosPublicos($filtros),
            'categorias' => $this->model->getCategoriasConProductos(),
            'filtros'    => $filtros,
        ], self::LAYOUT);
    }

    public function producto(): void
    {
        $id       = (int)$this->param('id');
        $producto = $this->model->getProductoPublico($id);

        if (!$producto) {
            $this->render('tienda.404', ['pageTitle' => 'Producto no encontrado'], self::LAYOUT);
            return;
        }

        $this->render('tienda.producto', [
            'pageTitle' => e($producto['nombre']) . ' — ' . TIENDA_NOMBRE,
            'producto'  => $producto,
        ], self::LAYOUT);
    }

    public function carrito(): void
    {
        $this->render('tienda.carrito', [
            'pageTitle' => 'Carrito — ' . TIENDA_NOMBRE,
        ], self::LAYOUT);
    }

    public function checkout(): void
    {
        Csrf::check();

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);

        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
        }

        $nombre    = trim($this->input('nombre', ''));
        $emailRaw  = trim($this->input('email', ''));
        $email     = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        $telefono  = trim($this->input('telefono', ''));
        $direccion = trim($this->input('direccion', ''));

        if (!$nombre || !$email) {
            $this->json(['ok' => false, 'error' => 'Nombre y email son obligatorios.'], 422);
        }

        // Calcular totales
        $subtotal = 0.0;
        $totalIva = 0.0;
        $detalle  = [];

        foreach ($items as $it) {
            $prodId = (int)($it['producto_id'] ?? 0);
            $cant   = (float)($it['cantidad'] ?? 0);
            $precio = (float)($it['precio_unitario'] ?? 0);
            $iva    = (float)($it['iva_porcentaje'] ?? 21);

            if (!$prodId || $cant <= 0) continue;

            $sub    = round($cant * $precio, 4);
            $subIva = round($sub * (1 + $iva / 100), 4);

            $subtotal += $sub;
            $totalIva += $subIva - $sub;

            $detalle[] = [
                'producto_id'      => $prodId,
                'cantidad'         => $cant,
                'precio_unitario'  => $precio,
                'iva_porcentaje'   => $iva,
                'subtotal_con_iva' => $subIva,
            ];
        }

        if (!$detalle) {
            $this->json(['ok' => false, 'error' => 'Items inválidos.'], 422);
        }

        $cabecera = [
            'cliente_nombre'    => $nombre,
            'cliente_email'     => $email,
            'cliente_telefono'  => $telefono ?: null,
            'cliente_direccion' => $direccion ?: null,
            'subtotal'          => round($subtotal, 2),
            'total_iva'         => round($totalIva, 2),
            'total_con_iva'     => round($subtotal + $totalIva, 2),
            'notas'             => trim($this->input('notas', '')) ?: null,
        ];

        try {
            $pedidoId = $this->model->crearPedido($cabecera, $detalle);

            $prefUrl = null;
            if (MP_ACCESS_TOKEN) {
                $prefUrl = $this->crearPreferenciaMP($pedidoId, $cabecera, $detalle);
            }

            $this->json([
                'ok'        => true,
                'pedido_id' => $pedidoId,
                'mp_url'    => $prefUrl,
                'mensaje'   => 'Pedido creado correctamente.',
            ]);
        } catch (\Throwable $e) {
            logApp('Error al crear pedido: ' . $e->getMessage(), 'ERROR');
            $this->json(['ok' => false, 'error' => 'Error al procesar el pedido.'], 500);
        }
    }

    public function pagoBrick(): void
    {
        $pedidoId = (int)($this->param('id') ?? 0);
        $pedido   = $this->model->findById($pedidoId);

        if (!$pedido) {
            header('Location: ' . url('tienda'));
            exit;
        }

        if ($pedido['estado'] === 'pagado') {
            header('Location: ' . url('tienda/pago/exito'));
            exit;
        }

        $mpProUrl = null;
        if (!empty($pedido['mp_preference_id']) && MP_ACCESS_TOKEN) {
            $sandbox  = str_starts_with(MP_ACCESS_TOKEN, 'TEST-');
            $mpProUrl = ($sandbox
                ? 'https://sandbox.mercadopago.com.ar'
                : 'https://www.mercadopago.com.ar')
                . '/checkout/v1/redirect?pref_id=' . $pedido['mp_preference_id'];
        }

        $this->render('tienda.pago_brick', [
            'pageTitle'   => 'Pagar pedido #' . $pedidoId . ' — ' . TIENDA_NOMBRE,
            'pedido'      => $pedido,
            'mpPublicKey' => MP_PUBLIC_KEY,
            'mpProUrl'    => $mpProUrl,
        ], self::LAYOUT);
    }

    public function procesarPagoBrick(): void
    {
        Csrf::check();

        $pedidoId = (int)$this->input('pedido_id', 0);
        $token    = trim($this->input('token', ''));
        $pmId     = trim($this->input('payment_method_id', ''));
        $issuerId = trim($this->input('issuer_id', ''));
        $cuotas   = max(1, (int)$this->input('installments', 1));
        $email    = trim($this->input('payer_email', ''));

        if (!$pedidoId || !$token || !$pmId) {
            $this->json(['ok' => false, 'error' => 'Datos de pago incompletos.'], 422);
            return;
        }

        $pedido = $this->model->findById($pedidoId);
        if (!$pedido) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado.'], 404);
            return;
        }

        if ($pedido['estado'] === 'pagado') {
            $this->json(['ok' => true, 'redirect' => url('tienda/pago/exito')]);
            return;
        }

        if (!MP_ACCESS_TOKEN) {
            $this->json(['ok' => false, 'error' => 'Mercado Pago no configurado.'], 503);
            return;
        }

        $body = [
            'transaction_amount' => (float)$pedido['total_con_iva'],
            'token'              => $token,
            'description'        => 'Pedido #' . $pedidoId . ' — ' . TIENDA_NOMBRE,
            'installments'       => $cuotas,
            'payment_method_id'  => $pmId,
            'payer'              => ['email' => $email ?: $pedido['cliente_email']],
            'external_reference' => 'pedido-' . $pedidoId,
            'notification_url'   => url('tienda/webhook/mp'),
        ];
        if ($issuerId) {
            $body['issuer_id'] = (int)$issuerId;
        }

        $ch = curl_init('https://api.mercadopago.com/v1/payments');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                'X-Idempotency-Key: brick-pedido-' . $pedidoId . '-' . time(),
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT    => 15,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$resp) {
            logApp('MP Brick API error: ' . $err, 'ERROR');
            $this->json(['ok' => false, 'error' => 'Error al conectar con Mercado Pago.'], 502);
            return;
        }

        $result    = json_decode($resp, true);
        $status    = $result['status'] ?? '';
        $detail    = $result['status_detail'] ?? '';
        $paymentId = (string)($result['id'] ?? '');

        $nuevoEstado = match($status) {
            'approved'              => 'pagado',
            'rejected', 'cancelled' => 'cancelado',
            default                 => 'pendiente',
        };

        $this->model->actualizarPago($pedidoId, [
            'estado'        => $nuevoEstado,
            'preference_id' => $pedido['mp_preference_id'] ?? null,
            'payment_id'    => $paymentId,
            'mp_status'     => $status,
        ]);

        if ($paymentId) {
            $this->model->registrarPagoMP([
                'pedido_id'     => $pedidoId,
                'payment_id'    => $paymentId,
                'status'        => $status,
                'status_detail' => $detail,
                'payer_email'   => $email ?: $pedido['cliente_email'],
                'amount'        => $pedido['total_con_iva'],
                'payment_type'  => $result['payment_type_id'] ?? null,
            ]);
        }

        if ($status === 'approved') {
            $this->model->descontarStock($pedidoId);
            Mailer::confirmacionPedido($pedido);
            $this->json(['ok' => true, 'redirect' => url('tienda/pago/exito') . '?payment_id=' . $paymentId]);
            return;
        }

        if (in_array($status, ['in_process', 'pending', 'authorized'])) {
            $this->json(['ok' => true, 'redirect' => url('tienda/pago/pendiente')]);
            return;
        }

        $this->json(['ok' => false, 'error' => $this->mpStatusMsg($detail)]);
    }

    public function pagoPendiente(): void
    {
        $this->render('tienda.pago_pendiente', [
            'pageTitle' => 'Pago pendiente — ' . TIENDA_NOMBRE,
        ], self::LAYOUT);
    }

    public function pagoExito(): void
    {
        $paymentId = $this->param('payment_id') ?? '';
        $prefId    = $this->param('preference_id') ?? '';
        $status    = $this->param('status') ?? '';

        if ($prefId) {
            $pedido = $this->model->findByPreferenceId($prefId);
            if ($pedido && $status === 'approved') {
                $this->model->actualizarPago($pedido['id'], [
                    'estado'        => 'pagado',
                    'preference_id' => $prefId,
                    'payment_id'    => $paymentId,
                    'mp_status'     => $status,
                ]);
            }
        }

        $this->render('tienda.pago_exito', [
            'pageTitle'  => '¡Pago confirmado! — ' . TIENDA_NOMBRE,
            'payment_id' => $paymentId,
        ], self::LAYOUT);
    }

    public function pagoFallo(): void
    {
        $this->render('tienda.pago_fallo', [
            'pageTitle' => 'Pago rechazado — ' . TIENDA_NOMBRE,
        ], self::LAYOUT);
    }

    public function webhookMercadoPago(): void
    {
        $payload = file_get_contents('php://input');
        $data    = json_decode($payload, true);

        if (empty($data['type']) || $data['type'] !== 'payment') {
            http_response_code(200);
            exit;
        }

        $paymentId = (string)($data['data']['id'] ?? '');
        if (!$paymentId) {
            http_response_code(200);
            exit;
        }

        $mpData = $this->consultarPagoMP($paymentId);
        if (!$mpData) {
            http_response_code(200);
            exit;
        }

        $prefId = $mpData['preference_id'] ?? '';
        $pedido = $prefId ? $this->model->findByPreferenceId($prefId) : null;

        if ($pedido) {
            $nuevoEstado = $mpData['status'] === 'approved' ? 'pagado' : $pedido['estado'];
            $this->model->actualizarPago($pedido['id'], [
                'estado'        => $nuevoEstado,
                'preference_id' => $prefId,
                'payment_id'    => $paymentId,
                'mp_status'     => $mpData['status'],
            ]);

            $this->model->registrarPagoMP([
                'pedido_id'     => $pedido['id'],
                'payment_id'    => $paymentId,
                'status'        => $mpData['status'],
                'status_detail' => $mpData['status_detail'] ?? '',
                'payer_email'   => $mpData['payer']['email'] ?? null,
                'amount'        => $mpData['transaction_amount'] ?? 0,
                'payment_type'  => $mpData['payment_type_id'] ?? null,
            ]);

            if ($mpData['status'] === 'approved') {
                $this->model->descontarStock($pedido['id']);
                Mailer::confirmacionPedido($pedido);
            }
        }

        logApp("MP Webhook payment_id=$paymentId status=" . ($mpData['status'] ?? ''));
        http_response_code(200);
        exit;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function crearPreferenciaMP(int $pedidoId, array $cabecera, array $detalle): ?string
    {
        $mpItems = [];
        foreach ($detalle as $it) {
            $mpItems[] = [
                'title'       => 'Producto #' . $it['producto_id'],
                'quantity'    => (int)$it['cantidad'],
                'unit_price'  => (float)round($it['subtotal_con_iva'] / max(1, $it['cantidad']), 2),
                'currency_id' => 'ARS',
            ];
        }

        $body = [
            'items'             => $mpItems,
            'payer'             => ['email' => $cabecera['cliente_email']],
            'external_reference'=> 'pedido-' . $pedidoId,
            'back_urls'         => [
                'success' => url('tienda/pago/exito'),
                'failure' => url('tienda/pago/fallo'),
                'pending' => url('tienda/pago/pendiente'),
            ],
            'auto_return'       => 'approved',
            'notification_url'  => url('tienda/webhook/mp'),
        ];

        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT    => 10,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$resp) {
            logApp('MP API error: ' . $err, 'ERROR');
            return null;
        }

        $result = json_decode($resp, true);
        $prefId = $result['id'] ?? null;
        $initPt = $result['init_point'] ?? null;

        if ($prefId) {
            $this->model->actualizarPago($pedidoId, [
                'estado'        => 'pendiente',
                'preference_id' => $prefId,
                'payment_id'    => null,
                'mp_status'     => null,
            ]);
        }

        return $initPt;
    }

    private function mpStatusMsg(string $detail): string
    {
        return match($detail) {
            'cc_rejected_insufficient_amount'      => 'Fondos insuficientes. Intentá con otra tarjeta.',
            'cc_rejected_bad_filled_card_number'   => 'Número de tarjeta incorrecto.',
            'cc_rejected_bad_filled_date'          => 'Fecha de vencimiento incorrecta.',
            'cc_rejected_bad_filled_security_code' => 'Código de seguridad incorrecto.',
            'cc_rejected_card_disabled'            => 'Tarjeta inhabilitada. Contactá a tu banco.',
            'cc_rejected_call_for_authorize'       => 'El banco requiere autorización. Contactá a tu banco.',
            'cc_rejected_duplicated_payment'       => 'Este pago ya fue procesado.',
            'cc_rejected_high_risk'                => 'Pago rechazado por seguridad. Intentá con otra tarjeta.',
            default                                => 'Pago no aprobado. Revisá los datos e intentá nuevamente.',
        };
    }

    private function consultarPagoMP(string $paymentId): ?array
    {
        if (!MP_ACCESS_TOKEN) return null;

        $ch = curl_init('https://api.mercadopago.com/v1/payments/' . urlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp ? json_decode($resp, true) : null;
    }
}
