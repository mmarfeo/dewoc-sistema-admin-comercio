<?php
/**
 * Cliente de la API de MenuApp (ver docs/integracion-menuapp-checklist.md, secciones 3.9 y
 * 3.10, tarea 9.3).
 *
 * Acá se invierte la dirección del resto del contrato: en las etapas 0 a 8 MenuApp consume
 * la API de Dewoc; en la etapa 9 **Dewoc llama a MenuApp** para aceptar, rechazar, proponer
 * cambios y escribir en las conversaciones del pedido. Dewoc no decide por su cuenta: le
 * pide a MenuApp, que cobra o reintegra, avisa al cliente y registra la venta.
 *
 * Firma de cada request (3.9), que es lo que MenuApp valida:
 *   X-Dewoc-Instalacion: <instalacion_id>   — el UUID fijo de esta instalación
 *   X-Timestamp:         <unix>             — rechazado si difiere más de 5 minutos
 *   X-Signature:         hex(HMAC-SHA256(timestamp + "." + cuerpo crudo, webhook_secret))
 *
 * El secreto es el MISMO que ya comparten para los avisos (integracion_menuapp.webhook_secret),
 * así que no hay nada nuevo que configurar: alcanza con tener la integración activa.
 *
 * A diferencia de MenuAppNotifier (que avisa y se olvida, y nunca puede frenar una operación
 * de Dewoc), acá el resultado SÍ importa: el cajero está esperando la respuesta para saber si
 * el pedido quedó aceptado. Por eso el timeout es más largo (10 s) y los errores vuelven con
 * un mensaje en castellano listo para mostrar.
 */
class MenuAppClient
{
    /** Segundos máximos de espera. Más largo que el notificador: hay alguien esperando. */
    const TIMEOUT = 10;

    /** Base por defecto si no se configuró otra en Integraciones. */
    const BASE_URL_DEFAULT = 'https://menuapp.dewoc.com/api/dewoc/v1/';

    // ── 3.10 · Endpoints ────────────────────────────────────────────────────

    /** Estado actual del pedido en MenuApp: ítems, total, si se puede aceptar o proponer. */
    public static function verPedido(string $referencia): array
    {
        return self::pedir('GET', 'pedidos/' . rawurlencode($referencia));
    }

    /**
     * Acepta el pedido. MenuApp cobra (o deja el link de pago), avisa al cliente y registra
     * la venta en Dewoc con POST /ventas, así que puede responder 409 con `sin_stock` o
     * `caja_cerrada`: son los mismos motivos por los que Dewoc rechazaría la venta.
     */
    public static function aceptar(string $referencia): array
    {
        return self::pedir('POST', 'pedidos/' . rawurlencode($referencia) . '/aceptar', [
            'usuario' => self::usuarioActual(),
        ]);
    }

    /** Rechaza el pedido. MenuApp reembolsa si estaba pagado. */
    public static function rechazar(string $referencia, string $motivo): array
    {
        return self::pedir('POST', 'pedidos/' . rawurlencode($referencia) . '/rechazar', [
            'motivo'  => $motivo,
            'usuario' => self::usuarioActual(),
        ]);
    }

    /**
     * Propone un pedido distinto al cliente (ej. cambiar un gusto que se acabó). Los IDs son
     * de DEWOC. MenuApp valida que todo esté en el menú y que se cumplan los mínimos y
     * máximos de cada grupo de gustos, y devuelve el total nuevo.
     *
     * @param array $items [{producto_id, cantidad, opciones:[{opcion_id, cantidad}], notas}]
     */
    public static function proponer(string $referencia, array $items, string $motivo): array
    {
        return self::pedir('POST', 'pedidos/' . rawurlencode($referencia) . '/proponer', [
            'items'   => $items,
            'motivo'  => $motivo,
            'usuario' => self::usuarioActual(),
        ]);
    }

    /** Lee una conversación. $despues permite traer solo lo nuevo. */
    public static function mensajes(string $referencia, string $conversacion, int $despues = 0): array
    {
        $qs = '?conversacion=' . urlencode($conversacion) . ($despues > 0 ? '&despues=' . $despues : '');
        return self::pedir('GET', 'pedidos/' . rawurlencode($referencia) . '/mensajes' . $qs);
    }

    /** Escribe en una conversación. MenuApp lo muestra como "Caja · {nombre}". */
    public static function enviarMensaje(string $referencia, string $conversacion, string $texto): array
    {
        return self::pedir('POST', 'pedidos/' . rawurlencode($referencia) . '/mensajes', [
            'conversacion' => $conversacion,
            'texto'        => mb_substr($texto, 0, 500), // el contrato corta en 500
            'usuario'      => self::usuarioActual(),
        ]);
    }

    /** Respuestas rápidas que el comercio edita en MenuApp: {cliente: [...], repartidor: [...]}. */
    public static function respuestasRapidas(): array
    {
        return self::pedir('GET', 'respuestas-rapidas');
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /**
     * Usuario de la caja que ejecuta la acción (3.9). MenuApp lo muestra y lo guarda en el
     * historial del pedido; no necesita existir del otro lado.
     */
    private static function usuarioActual(): array
    {
        $u = Session::user() ?: [];
        return [
            'id'     => (int)($u['id'] ?? 0),
            'nombre' => (string)($u['nombre'] ?? 'Caja'),
        ];
    }

    private static function config(): array
    {
        require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
        return (new IntegracionMenuAppModel())->obtener();
    }

    private static function baseUrl(array $config): string
    {
        $base = trim((string)($config['api_base_url'] ?? ''));
        return rtrim($base !== '' ? $base : self::BASE_URL_DEFAULT, '/') . '/';
    }

    /**
     * Hace la llamada firmada y normaliza la respuesta.
     *
     * @return array{ok: bool, http: int, datos: array, error: ?string, codigo: ?string}
     *   `error` ya viene en castellano, listo para mostrarle al cajero.
     *   `codigo` es el identificador que manda MenuApp (ej. sin_stock, caja_cerrada), para
     *   poder reaccionar distinto según el caso sin parsear el texto.
     */
    private static function pedir(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        $fallo = fn(string $msg, string $codigo = 'error', int $http = 0): array =>
            ['ok' => false, 'http' => $http, 'datos' => [], 'error' => $msg, 'codigo' => $codigo];

        try {
            $config = self::config();

            if (empty($config['activa'])) {
                return $fallo('La integración con MenuApp está desactivada.', 'inactiva');
            }
            if (empty($config['webhook_secret'])) {
                return $fallo('Falta el secreto de MenuApp: cargalo en Integraciones → MenuApp.', 'sin_secreto');
            }
            if (empty($config['instalacion_id'])) {
                return $fallo('Esta instalación todavía no tiene identificador. Entrá a Integraciones → MenuApp.', 'sin_instalacion');
            }

            // Cuerpo CRUDO: se firma y se envía exactamente el mismo string. Si se volviera a
            // serializar después de firmar, cualquier diferencia de orden o de escape rompería
            // la firma del otro lado.
            $raw       = $cuerpo === null ? '' : json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
            $timestamp = (string)time();
            $firma     = hash_hmac('sha256', $timestamp . '.' . $raw, (string)$config['webhook_secret']);

            $ch = curl_init(self::baseUrl($config) . $ruta);
            $headers = [
                'Accept: application/json',
                'X-Dewoc-Instalacion: ' . $config['instalacion_id'],
                'X-Timestamp: ' . $timestamp,
                'X-Signature: ' . $firma,
                'User-Agent: Dewoc/MenuAppClient',
            ];
            if ($cuerpo !== null) $headers[] = 'Content-Type: application/json; charset=utf-8';

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $metodo,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);

            $respuesta = curl_exec($ch);
            $http      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errCurl   = curl_error($ch);
            curl_close($ch);

            // El token no se loguea nunca; la firma tampoco, porque con el cuerpo permitiría
            // deducir el secreto por fuerza bruta.
            logApp("MenuAppClient {$metodo} {$ruta} → HTTP {$http}" . ($errCurl !== '' ? " ({$errCurl})" : ''));

            if ($respuesta === false || $errCurl !== '') {
                return $fallo('MenuApp no responde, probá de nuevo en un momento.', 'sin_conexion', $http);
            }

            $datos = json_decode((string)$respuesta, true);
            if (!is_array($datos)) $datos = [];

            if ($http >= 200 && $http < 300) {
                return ['ok' => true, 'http' => $http, 'datos' => $datos, 'error' => null, 'codigo' => null];
            }

            // MenuApp manda su propio motivo: se prefiere ese antes que un texto genérico,
            // porque es el que el comercio entiende ("Nos quedamos sin Frutilla").
            $codigo  = (string)($datos['error'] ?? $datos['codigo'] ?? 'error');
            $mensaje = (string)($datos['mensaje'] ?? $datos['detalle'] ?? '');

            if ($mensaje === '') {
                $mensaje = match (true) {
                    $codigo === 'sin_stock'    => 'No hay materia prima suficiente para preparar este pedido.',
                    $codigo === 'caja_cerrada' => 'La caja está cerrada: abrí un turno antes de aceptar.',
                    $http === 404              => 'MenuApp no encuentra este pedido.',
                    $http === 409              => 'El pedido ya no está esperando aprobación: alguien lo atendió o se venció.',
                    $http === 422              => 'MenuApp rechazó los datos del pedido.',
                    $http === 401 || $http === 403 => 'MenuApp rechazó la firma de Dewoc. Revisá el secreto en Integraciones → MenuApp.',
                    default                    => "MenuApp respondió un error (HTTP {$http}).",
                };
            }

            return ['ok' => false, 'http' => $http, 'datos' => $datos, 'error' => $mensaje, 'codigo' => $codigo];
        } catch (\Throwable $e) {
            logApp('MenuAppClient: ' . $e->getMessage(), 'ERROR');
            return $fallo('No se pudo contactar a MenuApp: ' . $e->getMessage());
        }
    }
}
