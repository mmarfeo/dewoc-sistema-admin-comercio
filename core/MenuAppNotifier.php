<?php
/**
 * Aviso a MenuApp de que cambió algo del catálogo (ver docs/integracion-menuapp-checklist.md,
 * sección 3.6 y tarea 2.1). El aviso NO lleva los datos: MenuApp vuelve a pedir
 * /catalogo?desde=... cuando lo recibe. Así da igual si llega dos veces o si se pierde
 * (MenuApp además consulta cada 5 minutos por su cuenta).
 *
 * Regla de oro: esto NUNCA debe frenar ni hacer fallar una operación de Dewoc. Si la
 * integración está apagada, no hace nada; si MenuApp está caído, el timeout corta a los
 * 3 segundos y el error queda en el log y en integracion_menuapp.ultimo_error. No lanza
 * excepciones bajo ninguna circunstancia.
 */
class MenuAppNotifier
{
    /** Segundos máximos que se permite tardar el aviso, para no colgar el guardado. */
    const TIMEOUT = 3;

    /**
     * Avisa un cambio de catálogo.
     *
     * @param string $entidad 'producto' | 'categoria' | 'grupo_opciones'
     * @param int    $id      ID de la entidad que cambió (0 si es un cambio masivo)
     */
    public static function catalogoCambio(string $entidad, int $id = 0): void
    {
        self::enviar([
            'evento'      => 'catalogo.cambio',
            'entidad'     => $entidad,
            'id'          => $id,
            'ocurrido_en' => date('c'),
        ]);
    }

    /**
     * La caja del comercio se abrió: MenuApp vuelve a aceptar pedidos (decisión D10).
     * Se llama SOLO cuando se abre el PRIMER turno — con un turno ya abierto, abrir otro
     * no cambia nada para MenuApp, que solo mira si hay al menos uno.
     */
    public static function cajaAbierta(): void
    {
        self::enviar([
            'evento'      => 'caja.abierta',
            'ocurrido_en' => date('c'),
        ]);
    }

    /**
     * Se cerró el ÚLTIMO turno abierto: MenuApp cierra el menú y deja de tomar pedidos.
     * Con dos turnos abiertos, cerrar uno no manda nada — la caja sigue abierta.
     *
     * Igual que los avisos de catálogo, esto no puede frenar el cierre de caja: si MenuApp
     * no responde, el turno se cierra lo mismo y MenuApp se entera por su chequeo
     * periódico de GET /estado (y, mientras tanto, cierra solo a los 10 minutos).
     */
    public static function cajaCerrada(): void
    {
        self::enviar([
            'evento'      => 'caja.cerrada',
            'ocurrido_en' => date('c'),
        ]);
    }

    /**
     * Aviso de prueba del botón "Probar conexión" de la pantalla Integraciones → MenuApp.
     * A diferencia de catalogoCambio(), este SÍ devuelve el resultado: es lo único que el
     * admin tiene para saber si la URL y el secreto están bien.
     *
     * @return array{ok: bool, http: int, error: ?string}
     */
    public static function ping(): array
    {
        return self::enviar([
            'evento'      => 'ping',
            'ocurrido_en' => date('c'),
        ], true);
    }

    /**
     * @param bool $devolverResultado true para el ping (que necesita el detalle del fallo);
     *                                false para los avisos normales, que solo loguean.
     * @return array{ok: bool, http: int, error: ?string}
     */
    private static function enviar(array $payload, bool $devolverResultado = false): array
    {
        $fallo = fn(string $msg, int $http = 0): array => ['ok' => false, 'http' => $http, 'error' => $msg];

        try {
            require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
            $model  = new IntegracionMenuAppModel();
            $config = $model->obtener();

            // El ping se permite con la integración apagada (sirve para probar la URL antes
            // de activarla); los avisos de catálogo no, para no pegarle a MenuApp cuando el
            // comercio todavía no empezó a usarlo.
            if (!$devolverResultado && empty($config['activa'])) {
                return $fallo('integración inactiva');
            }
            if (empty($config['webhook_url'])) {
                return $fallo('falta la URL del webhook');
            }
            if (empty($config['webhook_secret'])) {
                return $fallo('falta el secreto del webhook');
            }

            $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
            // La firma se calcula sobre el cuerpo CRUDO, byte por byte, igual que la valida
            // MenuApp — por eso se firma $cuerpo y después se manda ese mismo string, sin
            // volver a serializar.
            $firma  = hash_hmac('sha256', $cuerpo, (string)$config['webhook_secret']);

            $ch = curl_init((string)$config['webhook_url']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $cuerpo,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'X-Signature: ' . $firma,
                    'User-Agent: Dewoc/MenuAppNotifier',
                ],
            ]);
            $respuesta = curl_exec($ch);
            $http      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errCurl   = curl_error($ch);
            curl_close($ch);

            if ($respuesta === false || $errCurl !== '') {
                $msg = 'webhook MenuApp: ' . ($errCurl ?: 'sin respuesta');
                logApp($msg, 'WARN');
                $model->registrarError($msg);
                return $fallo($msg, $http);
            }
            if ($http < 200 || $http >= 300) {
                $msg = "webhook MenuApp respondió HTTP {$http}";
                logApp($msg, 'WARN');
                $model->registrarError($msg);
                return $fallo($msg, $http);
            }

            $model->limpiarError();
            logApp('Aviso a MenuApp OK: ' . ($payload['evento'] ?? '?') . ' ' . ($payload['entidad'] ?? '') . ' ' . ($payload['id'] ?? ''));
            return ['ok' => true, 'http' => $http, 'error' => null];
        } catch (\Throwable $e) {
            // Deliberadamente amplio: ni una excepción de red, ni una tabla faltante, ni un
            // error de configuración puede tirar abajo el guardado de un producto.
            logApp('MenuAppNotifier: ' . $e->getMessage(), 'WARN');
            return $fallo($e->getMessage());
        }
    }
}
