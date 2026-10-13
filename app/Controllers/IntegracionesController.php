<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';

/**
 * Integraciones → MenuApp (ver docs/integracion-menuapp-checklist.md, tarea 0.4).
 * Solo admin: desde acá se generan las credenciales que le dan a otro sistema acceso de
 * lectura al catálogo y de escritura a las ventas, así que no alcanza con el permiso de
 * módulo — se exige el rol.
 */
class IntegracionesController extends Controller
{
    private IntegracionMenuAppModel $model;

    public function __construct()
    {
        $this->model = new IntegracionMenuAppModel();
    }

    public function menuapp(): void
    {
        $this->requireRole('admin');

        $config = $this->model->obtener();

        // El token recién generado se pasa por la sesión y se consume en el primer render:
        // es la única vez que existe en claro (después solo quedan los últimos 4), y así no
        // vuelve a aparecer si el admin recarga la página.
        $tokenNuevo = Session::get('menuapp_token_nuevo');
        Session::delete('menuapp_token_nuevo');

        // Lo mismo que devuelve GET /api/menuapp/v1/estado, para que el admin vea en
        // pantalla exactamente lo que MenuApp está viendo (tarea 6.6).
        $cajaModel = new CajaModel();
        $aperturaCaja = $cajaModel->aperturaDelTurnoAbiertoMasViejo();

        $this->render('integraciones.menuapp', [
            'pageTitle'  => 'Integraciones · MenuApp',
            'breadcrumb' => ['Administración' => null, 'Integraciones' => null, 'MenuApp' => null],
            'config'     => $config,
            'tokenNuevo' => $tokenNuevo,
            // instalacionId() lo genera al primer uso y después no cambia nunca, así que
            // entrar a esta pantalla es lo que lo crea en una instalación nueva.
            'instalacionId'  => $this->model->instalacionId(),
            'cuit'           => (defined('AFIP_CUIT') && AFIP_CUIT !== '') ? AFIP_CUIT : null,
            'cajaAbierta'    => $aperturaCaja !== null,
            'cajaDesde'      => $aperturaCaja,
            'turnosAbiertos' => $cajaModel->cantidadTurnosAbiertos(),
            'cobrosPendientesMenuApp' => (new VentaModel())->contarCobrosPendientesMenuApp(),
            'productos'  => Database::getInstance()->query(
                "SELECT id, nombre FROM productos WHERE eliminado = 0 AND activo = 1 ORDER BY nombre"
            )->fetchAll(\PDO::FETCH_ASSOC),
            'usuarioTecnico' => $config['usuario_id']
                ? Database::getInstance()->query(
                    'SELECT nombre, email FROM usuarios WHERE id = ' . (int)$config['usuario_id']
                  )->fetch(\PDO::FETCH_ASSOC) ?: null
                : null,
        ]);
    }

    /**
     * Genera (o regenera) el token Bearer. Se muestra UNA sola vez: lo que queda guardado
     * es solo su hash. Regenerar invalida el anterior al instante, así que MenuApp deja de
     * poder entrar hasta que le peguen el nuevo.
     */
    public function generarToken(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $tokenPrevio = $this->model->obtener()['token_hash'] ?? null;
        $token       = $this->model->generarToken();

        Session::set('menuapp_token_nuevo', $token);
        AuditLog::log(
            $tokenPrevio ? 'regenerar_token' : 'generar_token',
            'integraciones',
            'MenuApp: se ' . ($tokenPrevio ? 'regeneró' : 'generó') . ' el token de la API'
                . ($tokenPrevio ? ' (el anterior quedó invalidado)' : '')
        );

        $this->json(['ok' => true, 'mensaje' => 'Token generado. Copialo ahora: no se vuelve a mostrar.']);
    }

    /** URL y secreto del webhook, menú público y producto con el que se factura el envío. */
    public function guardar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $webhookUrl = trim((string)$this->input('webhook_url', ''));
        if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $this->json(['ok' => false, 'error' => 'La URL del webhook no es válida.'], 422);
        }

        $menuUrl = trim((string)$this->input('menu_url', ''));
        if ($menuUrl !== '' && !filter_var($menuUrl, FILTER_VALIDATE_URL)) {
            $this->json(['ok' => false, 'error' => 'La URL del menú no es válida.'], 422);
        }

        $data = [
            'webhook_url'       => $webhookUrl ?: null,
            'menu_url'          => $menuUrl ?: null,
            'producto_envio_id' => (int)$this->input('producto_envio_id', 0) ?: null,
        ];

        // El secreto solo se sobrescribe si vino cargado: el campo se muestra vacío para no
        // filtrarlo en el HTML, y dejarlo en blanco significa "no cambiar", no "borrar".
        $secreto = trim((string)$this->input('webhook_secret', ''));
        if ($secreto !== '') $data['webhook_secret'] = $secreto;

        $this->model->guardar($data);
        AuditLog::log('actualizar', 'integraciones', 'MenuApp: se actualizó la configuración de la integración'
            . ($secreto !== '' ? ' (incluido el secreto del webhook)' : ''));

        $this->json(['ok' => true, 'mensaje' => 'Configuración guardada.']);
    }

    /**
     * Activa o desactiva la integración. Apagada, la API devuelve 401 a todo y no se manda
     * ningún aviso de cambios. Al activarla se asegura el usuario técnico "MenuApp
     * (sistema)", que es el que va en ventas.usuario_id.
     */
    public function activar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $activa = (bool)(int)$this->input('activa', 0);
        $config = $this->model->obtener();

        if ($activa && empty($config['token_hash'])) {
            $this->json(['ok' => false, 'error' => 'Generá primero el token: sin token MenuApp no puede autenticarse.'], 422);
        }

        $usuarioId = $activa ? $this->model->asegurarUsuarioTecnico() : null;
        if ($activa && !$usuarioId) {
            $this->json(['ok' => false, 'error' => 'No se pudo crear el usuario técnico "MenuApp (sistema)".'], 500);
        }

        $this->model->guardar(['activa' => (int)$activa]);
        AuditLog::log($activa ? 'activar' : 'desactivar', 'integraciones',
            'MenuApp: integración ' . ($activa ? 'activada' : 'desactivada'));

        $this->json([
            'ok'       => true,
            'activa'   => $activa,
            'mensaje'  => $activa ? 'Integración activada.' : 'Integración desactivada: la API deja de responder y no se envían avisos.',
        ]);
    }

    /** Manda un webhook `evento: "ping"` firmado, para verificar la URL y el secreto. */
    public function probar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $r = MenuAppNotifier::ping();

        $this->json([
            'ok'      => $r['ok'],
            'http'    => $r['http'],
            'error'   => $r['error'],
            'mensaje' => $r['ok']
                ? 'MenuApp respondió correctamente (HTTP ' . $r['http'] . ').'
                : 'No se pudo avisar a MenuApp: ' . $r['error'],
        ], $r['ok'] ? 200 : 200); // 200 en los dos casos: el fallo es del webhook, no de esta request
    }
}
