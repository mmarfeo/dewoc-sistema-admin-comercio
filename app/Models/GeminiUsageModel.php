<?php
require_once BASE_PATH . '/app/Models/Model.php';

class GeminiUsageModel extends Model
{
    protected string $table = 'gemini_llamadas';

    const CATEGORIAS = ['chat', 'imagen', 'video'];

    public function log(
        string $modelo,
        int $tokensPrompt,
        int $tokensRespuesta,
        int $tokensTotal,
        ?int $usuarioId,
        string $categoria = 'chat',
        ?int $videoSegundos = null
    ): void {
        if (!in_array($categoria, self::CATEGORIAS, true)) $categoria = 'chat';

        // fecha fijada desde PHP (Argentina), no CURDATE() de MySQL — mismo desfase de huso
        // horario que gemini_llamadas_log: el reloj del servidor de MySQL no coincide con la
        // hora local, así que llamadas cerca de medianoche podían quedar contadas en el día
        // equivocado en las estadísticas de /gemini-uso.
        $this->db->prepare(
            "INSERT INTO gemini_llamadas (fecha, modelo, categoria, tokens_prompt, tokens_respuesta, tokens_total, video_segundos, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([date('Y-m-d'), $modelo, $categoria, $tokensPrompt, $tokensRespuesta, $tokensTotal, $videoSegundos, $usuarioId]);
    }

    public function getStatsThisMonth(string $categoria = 'chat'): array
    {
        $mes  = date('Y-m');
        $stmt = $this->db->prepare(
            "SELECT
               COUNT(*)                 AS total_llamadas,
               COALESCE(SUM(tokens_prompt),    0) AS total_tokens_prompt,
               COALESCE(SUM(tokens_respuesta), 0) AS total_tokens_respuesta,
               COALESCE(SUM(tokens_total),     0) AS total_tokens_total,
               COALESCE(SUM(video_segundos),   0) AS total_video_segundos,
               COUNT(DISTINCT fecha)    AS dias_activos
             FROM gemini_llamadas
             WHERE DATE_FORMAT(fecha, '%Y-%m') = ? AND categoria = ?"
        );
        $stmt->execute([$mes, $categoria]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getCallsToday(string $categoria = 'chat'): int
    {
        return (int) $this->queryScalar(
            "SELECT COUNT(*) FROM gemini_llamadas WHERE fecha = CURDATE() AND categoria = ?",
            [$categoria]
        );
    }

    public function getStatsPerDay(int $days = 30, string $categoria = 'chat'): array
    {
        $stmt = $this->db->prepare(
            "SELECT
               fecha,
               COUNT(*)                 AS llamadas,
               COALESCE(SUM(tokens_total),     0) AS tokens_total,
               COALESCE(SUM(tokens_prompt),    0) AS tokens_prompt,
               COALESCE(SUM(tokens_respuesta), 0) AS tokens_respuesta,
               COALESCE(SUM(video_segundos),   0) AS video_segundos
             FROM gemini_llamadas
             WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND categoria = ?
             GROUP BY fecha
             ORDER BY fecha DESC"
        );
        $stmt->execute([$days, $categoria]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getConfig(): array
    {
        try {
            $rows = $this->db->query("SELECT clave, valor FROM gemini_config")->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            $rows = [];
        }
        return array_merge([
            'llamadas_gratuitas_dia'   => '1500',
            'costo_input_por_millon'   => '0.10',
            'costo_output_por_millon'  => '0.40',
            'costo_video_por_segundo'  => '0.40',
            'tipo_cambio_usd_ars'      => '1200',
            // '1' = el Chat IA salta directo a GEMINI_API_KEY_PAGO, sin intentar la gratis
            // primero — ver ChatController::llamarGemini(). Switch pensado para prenderlo/
            // apagarlo al toque desde /gemini-uso sin tocar .env ni redesplegar.
            'modo_solo_pago'           => '0',
        ], $rows ?: []);
    }

    public function setConfig(string $clave, string $valor): void
    {
        $this->db->prepare(
            "INSERT INTO gemini_config (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        )->execute([$clave, $valor]);
    }

    /**
     * Límites RPM/RPD cargados a mano por modelo (Google no los expone por API — ver
     * database/gemini_modelos_limite_setup.sql), indexados por nombre de modelo. Usado por
     * ChatController::elegirModeloConCupo() para arrancar cada mensaje con un modelo que
     * todavía tenga cupo, en vez de esperar a que Google lo rechace.
     */
    public function listarLimitesModelos(): array
    {
        try {
            $rows = $this->db->query("SELECT * FROM gemini_modelos_limite")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
        $porModelo = [];
        foreach ($rows as $r) {
            $porModelo[$r['modelo']] = [
                'rpm' => $r['rpm'] !== null ? (int) $r['rpm'] : null,
                'rpd' => $r['rpd'] !== null ? (int) $r['rpd'] : null,
            ];
        }
        return $porModelo;
    }

    public function guardarLimiteModelo(string $modelo, ?int $rpm, ?int $rpd): void
    {
        $this->db->prepare(
            "INSERT INTO gemini_modelos_limite (modelo, rpm, rpd) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rpm = VALUES(rpm), rpd = VALUES(rpd)"
        )->execute([$modelo, $rpm, $rpd]);
    }

    /**
     * Últimas fallas de Gemini/Veo/NVIDIA logueadas por ChatController y
     * GaleriaController (ver core/helpers.php::logApp()), para poder
     * diagnosticarlas desde la pantalla de administración sin tener que
     * entrar por FTP a leer storage/logs/app.log.
     */
    public function getErroresRecientes(int $limite = 20): array
    {
        $path = BASE_PATH . '/storage/logs/app.log';
        if (!is_file($path)) return [];

        // Se lee solo la cola del archivo (no todo) para no cargar en memoria un log que
        // con el tiempo puede crecer bastante.
        $tamano  = filesize($path);
        $handle  = fopen($path, 'r');
        if (!$handle) return [];
        $leerDesde = max(0, $tamano - 300_000);
        fseek($handle, $leerDesde);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        $lineas    = explode("\n", trim((string) $contenido));
        $errores   = [];
        foreach ($lineas as $linea) {
            if (!str_contains($linea, '[ERROR]')) continue;
            if (!str_contains($linea, 'Gemini') && !str_contains($linea, 'Veo') && !str_contains($linea, 'NVIDIA')) continue;
            if (!preg_match('/^\[(.+?)\] \[(\w+)\] (.+)$/', $linea, $m)) continue;
            $errores[] = ['fecha' => $m[1], 'nivel' => $m[2], 'mensaje' => $m[3]];
        }

        return array_slice(array_reverse($errores), 0, $limite);
    }
}
