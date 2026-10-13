<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Log detallado de cada llamada real a la API de Gemini (una fila por cada
 * curl_exec, incluidos los reintentos por 429) — complementa a
 * GeminiUsageModel (que es agregado, solo para costeo) sin reemplazarlo.
 * Pensado para diagnosticar por qué se agota el límite de solicitudes por
 * minuto del plan gratuito, no para guardar el contenido completo enviado
 * (eso pesaría demasiado: ver ChatController::buildSystemPrompt()).
 */
class GeminiLlamadaLogModel extends Model
{
    protected string $table = 'gemini_llamadas_log';

    public function registrar(array $data): void
    {
        $this->db->prepare(
            "INSERT INTO gemini_llamadas_log
             (mensaje_chat_id, usuario_id, iteracion, modelo, resumen_enviado, resumen_recibido,
              http_code, exito, tokens_prompt, tokens_respuesta, duracion_ms, error_resumen, created_at)
             VALUES
             (:mensaje_chat_id, :usuario_id, :iteracion, :modelo, :resumen_enviado, :resumen_recibido,
              :http_code, :exito, :tokens_prompt, :tokens_respuesta, :duracion_ms, :error_resumen, :created_at)"
        )->execute([
            ':mensaje_chat_id'  => $data['mensaje_chat_id']  ?: null,
            ':usuario_id'       => $data['usuario_id']       ?: null,
            ':iteracion'        => $data['iteracion']        ?? 1,
            ':modelo'           => $data['modelo']           ?? null,
            ':resumen_enviado'  => $data['resumen_enviado']  ?? null,
            ':resumen_recibido' => $data['resumen_recibido'] ?? null,
            ':http_code'        => $data['http_code']        ?? 0,
            ':exito'            => !empty($data['exito']) ? 1 : 0,
            ':tokens_prompt'    => $data['tokens_prompt']    ?? null,
            ':tokens_respuesta' => $data['tokens_respuesta'] ?? null,
            ':duracion_ms'      => $data['duracion_ms']      ?? null,
            ':error_resumen'    => $data['error_resumen']    ?? null,
            // Fijado desde PHP (huso horario America/Argentina/Buenos_Aires, ver TIMEZONE en
            // config.php) en vez de confiar en el DEFAULT CURRENT_TIMESTAMP de MySQL — el reloj
            // del servidor de MySQL no coincide con la hora de Argentina (mismo bug ya corregido
            // antes en ChatModel::guardarMensaje() para chat_mensajes.created_at).
            ':created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function listar(array $filtros = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]          = 'DATE(gll.created_at) >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]          = 'DATE(gll.created_at) <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (isset($filtros['exito']) && $filtros['exito'] !== '') {
            $where[]          = 'gll.exito = :exito';
            $params[':exito'] = (int) $filtros['exito'];
        }
        if (!empty($filtros['usuario_id'])) {
            $where[]              = 'gll.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filtros['usuario_id'];
        }

        return $this->queryAll(
            "SELECT gll.*, u.nombre AS usuario_nombre
             FROM gemini_llamadas_log gll
             LEFT JOIN usuarios u ON u.id = gll.usuario_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY gll.created_at DESC
             LIMIT 500",
            $params
        );
    }

    /**
     * Cuántas llamadas EXITOSAS hizo este modelo en el último minuto y en lo que va del
     * día — proxy local de RPM/RPD para elegir con qué modelo arrancar un mensaje nuevo
     * antes de que Google rechace la llamada. Una llamada rechazada (429) no cuenta:
     * Google no llegó a concederla, así que no consume cupo real.
     */
    public function usoActualPorModelo(string $modelo): array
    {
        // Límites calculados contra la hora de PHP (Argentina), no NOW()/CURDATE() de MySQL —
        // desde que created_at también se fija con date() (ver registrar()), mezclar acá el
        // reloj de MySQL volvería a desalinear la ventana de RPM/RPD contra lo guardado.
        $haceUnMinuto = date('Y-m-d H:i:s', time() - 60);
        $hoy          = date('Y-m-d');

        $rpm = (int) $this->queryScalar(
            "SELECT COUNT(*) FROM gemini_llamadas_log
             WHERE modelo = :modelo AND exito = 1 AND created_at >= :hace_un_minuto",
            [':modelo' => $modelo, ':hace_un_minuto' => $haceUnMinuto]
        );
        $rpd = (int) $this->queryScalar(
            "SELECT COUNT(*) FROM gemini_llamadas_log
             WHERE modelo = :modelo AND exito = 1 AND DATE(created_at) = :hoy",
            [':modelo' => $modelo, ':hoy' => $hoy]
        );
        return ['rpm_usado' => $rpm, 'rpd_usado' => $rpd];
    }

    /** Mensajes que más llamadas encadenaron — para detectar patrones ineficientes de un vistazo. */
    public function topMensajesConMasLlamadas(int $limite = 20): array
    {
        $limite = max(1, min(100, $limite));
        return $this->queryAll(
            "SELECT gll.mensaje_chat_id, COUNT(*) AS total_llamadas,
                    SUM(gll.exito = 0) AS total_errores,
                    MAX(gll.created_at) AS ultima,
                    cm.contenido AS mensaje_texto, u.nombre AS usuario_nombre
             FROM gemini_llamadas_log gll
             LEFT JOIN chat_mensajes cm ON cm.id = gll.mensaje_chat_id
             LEFT JOIN usuarios u ON u.id = gll.usuario_id
             WHERE gll.mensaje_chat_id IS NOT NULL
             GROUP BY gll.mensaje_chat_id, cm.contenido, u.nombre
             ORDER BY total_llamadas DESC, ultima DESC
             LIMIT {$limite}"
        );
    }
}
