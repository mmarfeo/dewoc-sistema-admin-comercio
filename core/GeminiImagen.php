<?php
/**
 * Edición de una imagen real con Gemini: se le manda la foto + una instrucción y devuelve
 * una imagen nueva. Extraído de GaleriaController::_llamarGeminiImagen() para que lo
 * puedan usar también las Fotos del menú (ver docs/integracion-menuapp-checklist.md, etapa
 * 8, tarea 8.6) sin duplicar la llamada ni el registro de uso.
 *
 * El uso queda registrado en gemini_llamadas con categoría 'imagen', así el gasto se sigue
 * viendo en /gemini-uso como hasta ahora.
 */
class GeminiImagen
{
    /**
     * Pide a Gemini la imagen editada.
     *
     * @return array{imagen: ?string, mime: ?string, texto: string}|null
     *         null si no se pudo conectar. Si `imagen` viene null pero hay `texto`, el
     *         modelo respondió solo con palabras (típicamente, rechazó el pedido).
     */
    public static function editar(string $mime, string $base64, string $instruccion): ?array
    {
        $respuesta = self::llamar($mime, $base64, $instruccion);
        if ($respuesta === null) return null;

        $texto   = '';
        $imgMime = null;
        $imgData = null;

        foreach ($respuesta['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) $texto .= $part['text'];

            // La API alterna entre camelCase y snake_case según la versión; se aceptan las dos.
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if ($inline && empty($imgData)) {
                $imgMime = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png';
                $imgData = $inline['data']     ?? null;
            }
        }

        return ['imagen' => $imgData, 'mime' => $imgMime, 'texto' => $texto];
    }

    /** Extensión de archivo que corresponde a un mime devuelto por el modelo. */
    public static function extensionDe(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };
    }

    /** Respuesta cruda de generateContent, o null si falló la conexión. */
    private static function llamar(string $mime, string $base64, string $instruccion): ?array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . GEMINI_IMAGE_MODEL . ':generateContent?key=' . GEMINI_MEDIA_API_KEY;

        $payload = json_encode([
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                    ['text' => $instruccion],
                ],
            ]],
            'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            logApp('Gemini generateContent (imagen) falló - HTTP ' . $httpCode
                . ' curl_error=' . $curlErr . ' body=' . substr((string)$response, 0, 1000), 'ERROR');
            return null;
        }

        $data = json_decode($response, true);
        if ($data) self::registrarUso($data['usageMetadata'] ?? []);

        return $data ?: null;
    }

    /** Deja el consumo en gemini_llamadas. Nunca puede hacer fallar la edición. */
    private static function registrarUso(array $usage): void
    {
        try {
            require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
            $uid = null;
            try { $uid = (int)(Session::user()['id'] ?? 0) ?: null; } catch (\Throwable) {}

            (new GeminiUsageModel())->log(
                GEMINI_IMAGE_MODEL,
                (int)($usage['promptTokenCount']     ?? 0),
                (int)($usage['candidatesTokenCount'] ?? 0),
                (int)($usage['totalTokenCount']      ?? 0),
                $uid,
                'imagen'
            );
        } catch (\Throwable) { /* la tabla puede no existir todavía — ignorar */ }
    }
}
