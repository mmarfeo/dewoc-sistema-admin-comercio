<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ClienteModel.php';
require_once BASE_PATH . '/app/Models/ClienteCatalogoModel.php';
require_once BASE_PATH . '/app/Models/PedidoClienteModel.php';
require_once BASE_PATH . '/app/Models/PortalConfigModel.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/core/Mailer.php';

/**
 * Portal de Clientes Mayoristas — ver docs/portal-clientes-mayoristas-checklist.md. Sesión
 * de cliente completamente aparte de la de staff (`auth_cliente`, ver
 * Controller::requireAuthCliente()). `AuditLog::log()` no tiene FK en `usuario_id`, así que
 * se reusa tal cual pasándole el `cliente_id` — se distingue por `modulo='portal'`.
 */
class PortalController extends Controller
{
    private ClienteModel $model;

    public function __construct()
    {
        $this->model = new ClienteModel();
    }

    public function loginForm(): void
    {
        if (Session::has('auth_cliente')) {
            redirect('portal');
        }
        $this->view('portal.login', [
            'pageTitle' => 'Portal de Clientes',
            'error'     => Session::flash('portal_login_error'),
        ]);
    }

    public function login(): void
    {
        Csrf::check(false);

        $email    = trim($this->input('email', ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            Session::flash('portal_login_error', 'Completá todos los campos.');
            redirect('portal/login');
        }

        $cliente = $this->model->login($email, $password);
        if (!$cliente) {
            logApp("Login de portal fallido para {$email}", 'WARN');
            Session::flash('portal_login_error', 'Credenciales incorrectas.');
            redirect('portal/login');
        }

        Session::regenerate();
        Session::set('auth_cliente', [
            'id'     => (int) $cliente['id'],
            'nombre' => $cliente['nombre'],
            'email'  => $cliente['email'],
        ]);

        logApp("Login de portal exitoso: {$cliente['email']}");
        AuditLog::log('login', 'portal', "Cliente: {$cliente['nombre']}", (int) $cliente['id'], $cliente['nombre']);

        redirect('portal');
    }

    public function logout(): void
    {
        $cliente = $this->clienteActual();
        if ($cliente) {
            AuditLog::log('logout', 'portal', "Cliente: {$cliente['nombre']}", (int) $cliente['id'], $cliente['nombre']);
        }
        Session::delete('auth_cliente');
        redirect('portal/login');
    }

    // ── Cambio / recuperación de contraseña ──────────────────────────────────

    /** "Olvidé mi contraseña" — formulario para pedir el email (cliente NO logueado). */
    public function recuperarForm(): void
    {
        if (Session::has('auth_cliente')) redirect('portal');
        $this->view('portal.recuperar', [
            'pageTitle' => 'Recuperar contraseña',
            'msg'       => Session::flash('portal_recuperar_msg'),
        ]);
    }

    /** POST: envía el email de restablecimiento — mismo esquema que AuthController::recuperar(). */
    public function recuperar(): void
    {
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX

        $email   = trim($this->input('email', ''));
        $cliente = $email ? $this->model->findByEmailPortal($email) : false;

        // Siempre mostrar el mismo mensaje para no revelar si el email existe
        Session::flash('portal_recuperar_msg', [
            'tipo'  => 'success',
            'texto' => 'Si el email está registrado, recibirás el enlace en unos minutos.',
        ]);

        if ($cliente) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $this->model->setResetToken((int) $cliente['id'], $tokenHash);

            $resetUrl = BASE_URL . '/portal/reset-password?token=' . urlencode($token);
            Mailer::resetPassword($cliente['email'], $cliente['nombre'], $resetUrl);
            AuditLog::log('reset_request', 'portal', "Solicitud de reset para: {$email}", (int) $cliente['id'], $cliente['nombre']);
        }

        redirect('portal/recuperar');
    }

    /** Formulario de nueva contraseña (llega desde el link del email). */
    public function resetPasswordForm(): void
    {
        if (Session::has('auth_cliente')) redirect('portal');

        $token     = $this->param('token', '');
        $tokenHash = $token ? hash('sha256', $token) : '';
        $cliente   = $tokenHash ? $this->model->findByResetToken($tokenHash) : false;

        $this->view('portal.reset-password', [
            'pageTitle' => 'Nueva contraseña',
            'token'     => $token,
            'valido'    => (bool) $cliente,
            'error'     => Session::flash('portal_reset_error'),
        ]);
    }

    /** POST: guarda la nueva contraseña desde el link del email. */
    public function resetPassword(): void
    {
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX

        $token     = $this->input('token', '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['password_confirm'] ?? '';
        $tokenHash = $token ? hash('sha256', $token) : '';
        $cliente   = $tokenHash ? $this->model->findByResetToken($tokenHash) : false;

        if (!$cliente) {
            Session::flash('portal_reset_error', 'El enlace expiró o es inválido. Solicitá uno nuevo.');
            redirect('portal/reset-password?token=' . urlencode($token));
        }

        if (strlen($password) < 8) {
            Session::flash('portal_reset_error', 'La contraseña debe tener al menos 8 caracteres.');
            redirect('portal/reset-password?token=' . urlencode($token));
        }

        if ($password !== $confirm) {
            Session::flash('portal_reset_error', 'Las contraseñas no coinciden.');
            redirect('portal/reset-password?token=' . urlencode($token));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->model->updatePassword((int) $cliente['id'], $hash);
        AuditLog::log('reset_password', 'portal', 'Contraseña restablecida', (int) $cliente['id'], $cliente['nombre']);

        Session::flash('portal_login_error', 'Contraseña actualizada. Ya podés iniciar sesión.');
        redirect('portal/login');
    }

    /** "Cambiar contraseña" — cliente ya logueado, desde el sidebar del portal. */
    public function cambiarPasswordForm(): void
    {
        $this->requireAuthCliente();
        $this->render('portal.cambiar-password', [
            'pageTitle'     => 'Cambiar contraseña',
            'clienteActual' => $this->clienteActual(),
            'msg'           => Session::flash('portal_cambiar_msg'),
        ], 'layouts.portal');
    }

    /** POST: valida la contraseña actual y guarda la nueva. */
    public function cambiarPassword(): void
    {
        $this->requireAuthCliente();
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX
        $cliente = $this->clienteActual();

        $actual  = $_POST['password_actual'] ?? '';
        $nueva   = $_POST['password_nueva'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (strlen($nueva) < 8) {
            Session::flash('portal_cambiar_msg', ['tipo' => 'danger', 'texto' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
            redirect('portal/cambiar-password');
        }

        if ($nueva !== $confirm) {
            Session::flash('portal_cambiar_msg', ['tipo' => 'danger', 'texto' => 'Las contraseñas no coinciden.']);
            redirect('portal/cambiar-password');
        }

        if (!$this->model->cambiarPassword((int) $cliente['id'], $actual, $nueva)) {
            Session::flash('portal_cambiar_msg', ['tipo' => 'danger', 'texto' => 'La contraseña actual es incorrecta.']);
            redirect('portal/cambiar-password');
        }

        AuditLog::log('cambio_password', 'portal', 'Contraseña cambiada desde el portal', (int) $cliente['id'], $cliente['nombre']);
        Session::flash('portal_cambiar_msg', ['tipo' => 'success', 'texto' => 'Contraseña actualizada correctamente.']);
        redirect('portal/cambiar-password');
    }

    // ── Catálogo + carrito ───────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuthCliente();
        $cliente = $this->clienteActual();

        $this->render('portal.catalogo', [
            'pageTitle'     => 'Catálogo',
            'clienteActual' => $cliente,
            'catalogo'      => (new ClienteCatalogoModel())->catalogoDeCliente($cliente['id']),
            'criterioStock' => (new PortalConfigModel())->getConfig()['criterio_stock'],
            'negocioAbierto'=> (new CajaModel())->hayTurnoAbierto(),
        ], 'layouts.portal');
    }

    /**
     * "Mi cuenta corriente" — mismos datos que ve el staff en Cobros de Clientes
     * (CobrosClientesController), acá filtrados al propio cliente logueado, para que las
     * cuentas queden claras para los dos lados. Ver PermisosModel::MODULOS['cobros_clientes']
     * — el permiso de staff es independiente de esto, no hay ningún cruce entre los dos.
     */
    public function cuentaCorriente(): void
    {
        $this->requireAuthCliente();
        $cliente = $this->clienteActual();

        require_once BASE_PATH . '/app/Models/CobroClienteModel.php';
        $model = new CobroClienteModel();

        $ventasPendientes = $model->getVentasPendientesByCliente((int) $cliente['id']);

        $this->render('portal.cuenta-corriente', [
            'pageTitle'        => 'Mi cuenta corriente',
            'clienteActual'    => $cliente,
            // Compras/Pagado se calculan sobre las MISMAS ventas que se listan abajo (con
            // saldo pendiente) — decisión: mostrarle al cliente solo Compras/Pagado/Saldo,
            // sin límite ni disponible (eso es información interna del negocio, no del
            // cliente). Límite/disponible quedan solo en Cobros de Clientes (staff).
            'totalCompras'     => array_sum(array_column($ventasPendientes, 'monto_cuenta_corriente')),
            'totalPagado'      => array_sum(array_column($ventasPendientes, 'pagado')),
            'saldoPendiente'   => $model->getSaldoPendienteCliente((int) $cliente['id']),
            'ventasPendientes' => $ventasPendientes,
            'cobros'           => $model->listar(['cliente_id' => (int) $cliente['id']]),
        ], 'layouts.portal');
    }

    /** AJAX: grupos/opciones (gustos) de un producto — mismo endpoint que ya usa el POS, gateado por sesión de cliente. */
    public function opcionesProducto(): void
    {
        $this->requireAuthCliente();
        $productoId = (int) $this->param('producto_id', 0);
        if (!$productoId) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        require_once BASE_PATH . '/app/Models/GrupoOpcionModel.php';
        $prodModel  = new ProductoModel();
        $grupoModel = new GrupoOpcionModel();

        // Criterio de stock configurable por el admin (ver PortalConfigModel, editable desde
        // Clientes) — 'stock_minimo' (default) corta apenas entra en zona de reposición;
        // 'stock_cero' recién corta sin stock físico, igual que el cajero en el POS.
        $criterioStock = (new PortalConfigModel())->getConfig()['criterio_stock'];

        $grupos = $prodModel->getGruposOpciones($productoId);
        foreach ($grupos as &$g) {
            $conOpciones   = $grupoModel->findByIdConOpciones((int) $g['id']);
            // sin_stock: "mostrar pero deshabilitar" (decisión confirmada) — a diferencia del
            // POS, acá no hay cajero que pueda confirmar "vender igual". Una opción sin
            // materia_prima_id ni insumo_id (puramente informativa, ej. "sin azúcar") nunca
            // se deshabilita.
            $g['opciones'] = array_map(function ($o) use ($criterioStock) {
                $sinStock = false;
                if (!empty($o['materia_prima_id'])) {
                    $sinStock = $criterioStock === 'stock_cero'
                        ? (float) $o['mp_stock_actual'] <= 0
                        : (float) $o['mp_stock_actual'] <= (float) $o['mp_stock_minimo'];
                } elseif (!empty($o['insumo_id'])) {
                    $sinStock = $criterioStock === 'stock_cero'
                        ? (float) $o['ins_stock_actual'] <= 0
                        : (float) $o['ins_stock_actual'] <= (float) $o['ins_stock_minimo'];
                }
                return ['id' => (int) $o['id'], 'nombre' => $o['nombre'], 'sin_stock' => $sinStock];
            }, $conOpciones['opciones'] ?? []);
        }
        unset($g);

        $this->json(['ok' => true, 'grupos' => $grupos]);
    }

    /**
     * Crea el pedido — queda `pendiente` hasta que un cajero lo acepte o rechace desde el
     * POS. El precio de cada línea se resuelve acá, contra el catálogo de ESTE cliente (no
     * se confía en ningún precio que venga del navegador) — es el snapshot que decide el
     * checklist ("se congela al momento del pedido").
     */
    public function crearPedido(): void
    {
        $this->requireAuthCliente();
        Csrf::check();
        $cliente = $this->clienteActual();

        // Sin caja abierta no hay quien reciba el pedido — se bloquea acá además de en el
        // botón del cliente (que se deshabilita en pantalla), para no depender solo del JS.
        if (!(new CajaModel())->hayTurnoAbierto()) {
            $this->json(['ok' => false, 'error' => 'El negocio está cerrado en este momento — no se pueden enviar pedidos hasta que reabra.'], 422);
        }

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        $notas     = trim($this->input('notas', '')) ?: null;

        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'El pedido no puede estar vacío.'], 422);
        }

        $catalogoModel = new ClienteCatalogoModel();
        $catalogo      = $catalogoModel->catalogoDeCliente((int) $cliente['id']);
        $catalogoPorId = [];
        foreach ($catalogo as $c) $catalogoPorId[(int) $c['id']] = $c;

        $itemsValidados = [];
        foreach ($items as $it) {
            $productoId = (int) ($it['producto_id'] ?? 0);
            $cantidad   = (float) ($it['cantidad'] ?? 0);
            if (!$productoId || $cantidad <= 0 || !isset($catalogoPorId[$productoId])) continue;

            $prod = $catalogoPorId[$productoId];
            $opciones = [];
            foreach (($it['opciones'] ?? []) as $op) {
                $opcionId = (int) ($op['opcion_id'] ?? 0);
                if (!$opcionId) continue;
                $opciones[] = ['opcion_id' => $opcionId, 'cantidad_elegida' => max(1, (int) ($op['cantidad_elegida'] ?? 1))];
            }

            $itemsValidados[] = [
                'producto_id'     => $productoId,
                'nombre'          => $prod['nombre'],
                'cantidad'        => $cantidad,
                'precio_unitario' => (float) $prod['precio_venta'],
                'iva_porcentaje'  => (float) $prod['iva_porcentaje'],
                'opciones'        => $opciones,
                'controla_stock'  => (bool) $prod['controla_stock'],
                'stock_actual'    => (float) $prod['stock_actual'],
                'stock_minimo'    => (float) $prod['stock_minimo'],
            ];
        }

        $errorStock = $this->validarStockPedido($itemsValidados);
        if ($errorStock) {
            $this->json(['ok' => false, 'error' => $errorStock], 422);
        }

        if (!count($itemsValidados)) {
            $this->json(['ok' => false, 'error' => 'Ninguno de los productos del pedido está disponible en tu catálogo.'], 422);
        }

        $pedidoModel = new PedidoClienteModel();
        $pedidoId    = $pedidoModel->crear((int) $cliente['id'], $notas, $itemsValidados);

        $this->notificarStaffPedidoNuevo($pedidoId, $cliente['nombre']);

        $this->json(['ok' => true, 'id' => $pedidoId, 'mensaje' => 'Pedido enviado — te vamos a avisar cuando lo confirmen.']);
    }

    /**
     * Revalidación de stock server-side (defense in depth — el catálogo del navegador puede
     * quedar desactualizado entre que el cliente abrió la página y confirmó el pedido, mismo
     * criterio que VentasController::guardar() revalida el tope diario de AFIP antes de
     * facturar). A diferencia del POS, acá no hay cajero para un "¿vender igual?": si falta
     * stock se rechaza directo. Devuelve el mensaje de error, o null si todo está disponible.
     */
    private function validarStockPedido(array $itemsValidados): ?string
    {
        $db = Database::getInstance();

        foreach ($itemsValidados as $it) {
            if ($it['controla_stock'] && $it['stock_actual'] <= $it['stock_minimo']) {
                return "\"{$it['nombre']}\" ya no tiene stock suficiente — actualizá la página.";
            }

            foreach ($it['opciones'] as $op) {
                $opcion = $db->prepare(
                    'SELECT o.nombre, o.materia_prima_id, o.insumo_id, o.cantidad_consumida,
                            mp.stock_actual AS mp_stock, ins.stock_actual AS ins_stock
                     FROM opciones o
                     LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
                     LEFT JOIN insumos ins        ON ins.id = o.insumo_id
                     WHERE o.id = ?'
                );
                $opcion->execute([$op['opcion_id']]);
                $fila = $opcion->fetch();
                if (!$fila) continue;

                $necesario = (float) $fila['cantidad_consumida'] * $op['cantidad_elegida'] * $it['cantidad'];
                if ($necesario <= 0) continue;

                $disponible = $fila['materia_prima_id'] ? (float) $fila['mp_stock'] : ($fila['insumo_id'] ? (float) $fila['ins_stock'] : null);
                if ($disponible !== null && $disponible < $necesario) {
                    return "\"{$fila['nombre']}\" ya no tiene stock suficiente — actualizá la página.";
                }
            }
        }

        return null;
    }

    /**
     * Avisa por push a TODAS las cajas con turno abierto ahora mismo — "al primero que lo
     * atienda" (decisión confirmada), no hay asignación puntual. Mismo patrón que
     * VentasController::notificarAdminsAnulacion().
     */
    private function notificarStaffPedidoNuevo(int $pedidoId, string $clienteNombre): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $titulo = '🛎️ Nuevo pedido de ' . $clienteNombre;
        $cuerpo = 'Tiene un pedido esperando aceptación en el POS.';
        $url    = rtrim(BASE_URL, '/') . '/ventas/pos';
        $payload = json_encode([
            'title' => $titulo,
            'body'  => $cuerpo,
            'url'   => $url,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        $pushModel = new PushSubscriptionModel();
        $suscripciones = $pushModel->findSuscripcionesTurnosAbiertos();
        (new NotificacionModel())->crearParaSuscripciones($suscripciones, $titulo, $cuerpo, $url, 'pedido_portal');
        foreach ($suscripciones as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) $pushModel->eliminarPorId((int) $sub['id']);
            } catch (\Throwable $e) {
                logApp('Error enviando push de pedido #' . $pedidoId . ' a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    public function pedidos(): void
    {
        $this->requireAuthCliente();
        $cliente = $this->clienteActual();

        $this->render('portal.pedidos', [
            'pageTitle'     => 'Mis pedidos',
            'clienteActual' => $cliente,
            'pedidos'       => (new PedidoClienteModel())->listarPorCliente((int) $cliente['id']),
        ], 'layouts.portal');
    }

    // ── Chat del pedido ──────────────────────────────────────────────────────

    public function chat(): void
    {
        $this->requireAuthCliente();
        $cliente  = $this->clienteActual();
        $pedidoId = (int) $this->param('id', 0);

        $pedido = (new PedidoClienteModel())->findByIdConDetalle($pedidoId);
        if (!$pedido || (int) $pedido['cliente_id'] !== (int) $cliente['id']) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        require_once BASE_PATH . '/app/Models/PedidoMensajeModel.php';
        $mensajeModel = new PedidoMensajeModel();
        $mensajeModel->marcarLeidos($pedidoId, 'cliente');

        $this->render('portal.chat', [
            'pageTitle'     => 'Pedido #' . $pedidoId,
            'clienteActual' => $cliente,
            'pedido'        => $pedido,
            'mensajes'      => $mensajeModel->listarPorPedido($pedidoId),
        ], 'layouts.portal');
    }

    public function chatEnviar(): void
    {
        $this->requireAuthCliente();
        Csrf::check();
        $cliente  = $this->clienteActual();
        $pedidoId = (int) $this->input('pedido_id', 0);
        $mensaje  = trim($this->input('mensaje', ''));
        if (!$pedidoId || $mensaje === '') $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        $pedido = (new PedidoClienteModel())->findByIdConDetalle($pedidoId);
        if (!$pedido || (int) $pedido['cliente_id'] !== (int) $cliente['id']) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado.'], 404);
        }

        require_once BASE_PATH . '/app/Models/PedidoMensajeModel.php';
        (new PedidoMensajeModel())->enviar($pedidoId, 'cliente', (int) $cliente['id'], $mensaje);
        $this->json(['ok' => true]);
    }

    /** Poll no bloqueante para el modal "tenés una respuesta" en la pantalla principal (decisión confirmada: push + modal, no solo uno). */
    public function estadoPoll(): void
    {
        $this->requireAuthCliente();
        $cliente = $this->clienteActual();
        require_once BASE_PATH . '/app/Models/PedidoMensajeModel.php';
        $pedidosNoLeidos = (new PedidoMensajeModel())->pedidosNoLeidosParaCliente((int) $cliente['id']);
        // pedidoId: a cuál pedido llevar al cliente desde el aviso (ver layouts/portal.php,
        // modalAvisoChatPortal) — antes el botón del aviso mandaba siempre al listado, que no
        // marca nada como leído, así que el aviso reaparecía en bucle sin que hubiera forma
        // de sacárselo de encima.
        $this->json(['ok' => true, 'hayNoLeidos' => !empty($pedidosNoLeidos), 'pedidoId' => $pedidosNoLeidos[0] ?? null]);
    }

    // ── Push notifications ───────────────────────────────────────────────────

    public function vapidPublicKey(): void
    {
        $this->requireAuthCliente();
        $this->json(['publicKey' => VAPID_PUBLIC_KEY]);
    }

    public function suscribir(): void
    {
        $this->requireAuthCliente();
        Csrf::check();
        $cliente  = $this->clienteActual();
        $endpoint = $this->input('endpoint', '');
        $p256dh   = $this->input('p256dh', '');
        $auth     = $this->input('auth', '');
        if (!$endpoint || !$p256dh || !$auth) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        (new PushSubscriptionModel())->guardarCliente((int) $cliente['id'], $endpoint, $p256dh, $auth, $_SERVER['HTTP_USER_AGENT'] ?? null);
        $this->json(['ok' => true]);
    }

    public function desuscribir(): void
    {
        $this->requireAuthCliente();
        Csrf::check();
        $endpoint = $this->input('endpoint', '');
        if ($endpoint) {
            require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
            (new PushSubscriptionModel())->eliminarPorEndpoint($endpoint);
        }
        $this->json(['ok' => true]);
    }
}
