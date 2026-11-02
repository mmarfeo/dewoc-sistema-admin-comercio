<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CobroDispositivoModel.php';
require_once BASE_PATH . '/app/Models/CobroPaqueteModel.php';
require_once BASE_PATH . '/app/Models/CobroRecibidoModel.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';

/**
 * API que consume la app del celular Dewoc Cobros — ver docs/dewoc-cobros-checklist.md,
 * sección 3, y docs/dewoc-cobros-servidor-plan.md. Mismo estilo que ApiMenuAppController:
 *
 * - NO usa $_SESSION ni Csrf::check(): se autentica con `Authorization: Bearer <token del
 *   dispositivo>`, validado al principio de CADA acción con requireDispositivo(). El router
 *   no tiene middleware, así que la llamada es explícita.
 * - Cada acción revisa además la licencia del módulo (C16 a C20): GET /estado responde 200
 *   y la informa en `licencia.estado`; las que reciben datos responden 402.
 * - La app ignora los campos que no conoce: se pueden agregar campos a las respuestas.
 *
 * Códigos: 401 token inválido o revocado · 402 módulo pausado · 422 datos inválidos ·
 * 429 pasado el límite de requests.
 */
class ApiCobrosController extends Controller
{
    const MODULO = 'dewoc_cobros';

    private CobroDispositivoModel $dispositivos;
    private CobroPaqueteModel     $paquetes;

    /** Celular autenticado, disponible después de requireDispositivo(). */
    private array $dispositivo = [];

    public function __construct()
    {
        $this->dispositivos = new CobroDispositivoModel();
        $this->paquetes     = new CobroPaqueteModel();
    }

    // ── Autenticación y utilidades ───────────────────────────────────────────

    /**
     * Valida el token Bearer y el límite de requests, y registra el contacto del celular.
     * Corta con el JSON de error si algo no cierra. En el log queda la llamada, nunca el token.
     */
    private function requireDispositivo(): void
    {
        $disp = $this->dispositivos->porToken($this->bearerToken());
        if (!$disp) {
            $this->logLlamada('401 token inválido o revocado');
            $this->json(['error' => 'no_autorizado'], 401);
        }

        if (!$this->dispositivos->consumirRateLimit((int)$disp['id'])) {
            $this->logLlamada("429 límite de requests superado (dispositivo {$disp['id']})");
            $this->json(['error' => 'demasiadas_solicitudes', 'limite_por_minuto' => CobroDispositivoModel::RATE_LIMITE_POR_MINUTO], 429);
        }

        $this->dispositivo = $disp;
        $this->dispositivos->registrarContacto((int)$disp['id']);
        $this->logLlamada("OK (dispositivo {$disp['id']})");
    }

    /**
     * Estado de la licencia PARA ESTE CELULAR: el del módulo y, si el módulo funciona pero
     * el celular quedó fuera de los incluidos (C18), `limite_dispositivos`.
     */
    private function licencia(): array
    {
        $mod    = moduloEstado(self::MODULO);
        $estado = $mod['estado'];
        if (moduloHabilitado(self::MODULO)
            && !$this->dispositivos->dentroDelLimite((int)$this->dispositivo['id'], (int)$mod['dispositivos'])) {
            $estado = 'limite_dispositivos';
        }
        return ['estado' => $estado, 'hasta' => $mod['hasta'], 'dispositivos' => (int)$mod['dispositivos']];
    }

    /**
     * Token del header Authorization, con las mismas variantes que
     * ApiMenuAppController::bearerToken(): Apache/LiteSpeed no siempre lo pasan en
     * HTTP_AUTHORIZATION (ver la regla del .htaccess, que vale para cualquier ruta).
     */
    private function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) { $header = $valor; break; }
            }
        }

        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string)$header, $m)) return $m[1];
        return '';
    }

    /** Deja la llamada en storage/logs/app.log, SIN el token (ni un fragmento). */
    private function logLlamada(string $resultado): void
    {
        logApp(sprintf(
            'API Cobros %s %s → %s',
            $_SERVER['REQUEST_METHOD'] ?? '?',
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '?',
            $resultado
        ));
    }

    // ── 3.1 · GET /api/cobros/v1/estado ──────────────────────────────────────

    /**
     * Configuración y estado del celular. La app lo llama al abrirse, al vincularse (para
     * probar el token antes de guardarlo) y cuando cambia `paquetes_version`. Responde 200
     * aunque la licencia esté vencida: así la app sabe que está pausada y por qué.
     */
    public function estado(): void
    {
        $this->requireDispositivo();

        $config = $this->paquetes->config();
        $resp = [
            'comercio'            => NEGOCIO_NOMBRE,
            'dispositivo_activo'  => true,
            'licencia'            => $this->licencia(),
            'caja_abierta'        => (new CajaModel())->cantidadTurnosAbiertos() > 0,
            'paquetes_version'    => (int)$config['paquetes_version'],
            'paquetes_permitidos' => $this->paquetes->permitidos(),
        ];

        $app = $this->paquetes->versionApp();
        if ($app) $resp['app'] = $app;

        $this->json($resp);
    }

    // ── 3.2 · POST /api/cobros/v1/notificaciones ─────────────────────────────

    /**
     * Notificación de una app de pago. 201 nueva, 200 si ese uuid ya estaba (la app la da
     * por enviada). En los dos casos la respuesta trae lo que decidió el lector, que la app
     * muestra ("Cobro $ 10 · Daniela") y puede decir en voz alta.
     *
     * Tiene que responder rápido (< 3 s): el lector no hace llamadas externas y la
     * conciliación con las ventas es una consulta con índice.
     */
    public function notificaciones(): void
    {
        $this->requireDispositivo();
        $this->requireLicencia();

        $d = $this->cuerpoJson();

        $uuid = is_string($d['uuid'] ?? null) ? strtolower(trim($d['uuid'])) : '';
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
            $this->json(['error' => 'uuid_invalido'], 422);
        }
        $paquete = is_string($d['paquete'] ?? null) ? trim($d['paquete']) : '';
        if ($paquete === '' || strlen($paquete) > 150) $this->json(['error' => 'paquete_invalido'], 422);

        $ts = is_string($d['recibido_at'] ?? null) ? strtotime($d['recibido_at']) : false;
        if ($ts === false) $this->json(['error' => 'recibido_at_invalido'], 422);

        $textos = [];
        foreach (['titulo' => 500, 'texto' => 5000, 'texto_largo' => 5000] as $campo => $max) {
            $v = $d[$campo] ?? null;
            if ($v !== null && !is_string($v)) $this->json(['error' => "{$campo}_invalido"], 422);
            $textos[$campo] = ($v === null || trim($v) === '') ? null : mb_substr($v, 0, $max);
        }

        $recibidos = new CobroRecibidoModel();

        $existente = $recibidos->porUuid($uuid);
        if ($existente) $this->json($this->respuestaNotificacion($existente), 200);

        $leido = CobroRecibidoModel::leer($paquete, $this->paquetes->porPaquete($paquete),
            $textos['titulo'], $textos['texto'], $textos['texto_largo']);

        $fila = $textos + $leido + [
            'uuid'           => $uuid,
            'dispositivo_id' => (int)$this->dispositivo['id'],
            'paquete'        => $paquete,
            // Viene con la zona del celular (-03:00); se guarda en la del comercio.
            'recibido_at'    => date('Y-m-d H:i:s', $ts),
        ];

        $id = $recibidos->registrar($fila);
        if ($id === 0) {
            // Llegó la misma a la vez por otra request: ya está guardada.
            $this->json($this->respuestaNotificacion($recibidos->porUuid($uuid) ?? $fila), 200);
        }

        if ($leido['resultado'] === 'cobro') {
            // Nunca puede impedir que se registre la notificación.
            try {
                $recibidos->conciliarCobro($id);
            } catch (\Throwable $e) {
                logApp("Dewoc Cobros: no se pudo conciliar el cobro {$id} — " . $e->getMessage(), 'ERROR');
            }
        }

        $this->json($this->respuestaNotificacion($fila), 201);
    }

    private function respuestaNotificacion(array $f): array
    {
        $r = ['resultado' => $f['resultado']];
        if ($f['resultado'] === 'cobro') {
            $r['monto']  = $f['monto'] !== null ? round((float)$f['monto'], 2) : null;
            $r['nombre'] = $f['nombre'];
        }
        return $r;
    }

    // ── 3.3 · POST /api/cobros/v1/latido ─────────────────────────────────────

    /**
     * El celular sigue vivo (cada 5 minutos). Guarda el estado de los permisos para mostrarlo
     * en Integraciones y devuelve la versión de la lista de paquetes: si cambió, la app pide
     * GET /estado para traer la nueva.
     */
    public function latido(): void
    {
        $this->requireDispositivo();
        $this->requireLicencia();

        $d    = $this->cuerpoJson();
        $bool = fn($k) => array_key_exists($k, $d) && $d[$k] !== null ? (int)(bool)$d[$k] : null;

        $this->dispositivos->registrarLatido((int)$this->dispositivo['id'], [
            'version_app'            => is_scalar($d['version_app'] ?? null) ? mb_substr((string)$d['version_app'], 0, 20) : null,
            'permiso_notificaciones' => $bool('permiso_notificaciones'),
            'optimizacion_bateria'   => $bool('optimizacion_bateria'),
            'pendientes_en_cola'     => isset($d['pendientes_en_cola']) ? max(0, (int)$d['pendientes_en_cola']) : null,
        ]);

        $this->json(['paquetes_version' => (int)$this->paquetes->config()['paquetes_version']]);
    }

    // ── Licencia en las acciones que reciben datos ───────────────────────────

    /**
     * 402 si el módulo está pausado o el celular quedó fuera de la licencia (sección 3 del
     * checklist). Con un 402 la app no encola ni reintenta: muestra "pausado" y consulta
     * GET /estado cada 30 minutos.
     */
    private function requireLicencia(): void
    {
        $lic = $this->licencia();
        if ($lic['estado'] === 'limite_dispositivos') {
            $this->logLlamada('402 celular fuera del límite de la licencia');
            $this->json(['error' => 'limite_dispositivos'], 402);
        }
        if (!in_array($lic['estado'], Licencias::ESTADOS_HABILITADOS, true)) {
            $this->logLlamada("402 módulo no habilitado ({$lic['estado']})");
            $this->json(['error' => 'modulo_no_habilitado', 'estado' => $lic['estado']], 402);
        }
    }

    /** Cuerpo JSON de la request. Ilegible = 422 con un cuerpo corto (la app guarda 200 caracteres). */
    private function cuerpoJson(): array
    {
        // INVALID_UTF8_SUBSTITUTE: un carácter mal codificado en el texto no tira el cobro entero.
        $data = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_array($data)) $this->json(['error' => 'json_invalido'], 422);
        return $data;
    }
}
