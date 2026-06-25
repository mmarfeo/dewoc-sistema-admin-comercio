<?php

class ChatController extends Controller
{
    private ChatModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ChatModel();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $historial = $this->model->getHistorial((int) $_SESSION['usuario_id']);
        $this->render('chat/index', [
            'title'     => 'Chat IA — MCP MySQL',
            'historial' => $historial,
            'apiActiva' => CLAUDE_API_KEY !== '',
        ]);
    }

    public function mensaje(): void
    {
        Auth::requireLogin();
        Csrf::check();

        $pregunta = trim($_POST['mensaje'] ?? '');
        if ($pregunta === '') {
            $this->json(['ok' => false, 'error' => 'El mensaje no puede estar vacío.']);
        }

        $usuarioId = (int) $_SESSION['usuario_id'];

        // Guardar mensaje del usuario
        $this->model->guardarMensaje($usuarioId, 'user', $pregunta);

        if (CLAUDE_API_KEY === '') {
            $respuesta = 'El módulo Chat IA no está configurado. Por favor completá CLAUDE_API_KEY en config/config.php.';
            $this->model->guardarMensaje($usuarioId, 'assistant', $respuesta);
            $this->json(['ok' => true, 'respuesta' => $respuesta, 'tipo' => 'texto']);
        }

        // Obtener esquema de tablas para el contexto
        try {
            $esquema = $this->model->getEsquemaTablas();
        } catch (\Exception $e) {
            $esquema = [];
        }

        $esquemaTexto = '';
        foreach ($esquema as $tabla => $cols) {
            $esquemaTexto .= "- $tabla: " . implode(', ', $cols) . "\n";
        }

        $systemPrompt = <<<PROMPT
Eres un asistente de análisis de datos para un sistema de administración de comercio en Argentina.
Tienes acceso de solo lectura a una base de datos MySQL con las siguientes tablas:

{$esquemaTexto}
REGLAS:
- Solo puedes generar consultas SELECT. Nunca INSERT, UPDATE, DELETE, DROP ni DDL.
- Las filas activas tienen eliminado = 0 (aplica a la mayoría de tablas excepto roles, caja_turnos).
- Los montos están en pesos argentinos (ARS). Usar número_format con 2 decimales.
- El IVA puede ser 10.5%, 21% o 0% (exento).
- Fechas en formato DATETIME (YYYY-MM-DD HH:MM:SS).
- Si precisas datos, responde ÚNICAMENTE con este JSON (sin texto extra):
{"tipo":"sql","sql":"SELECT ...","explicacion":"Descripción breve de la consulta"}
- Si puedes responder sin datos de la BD, responde ÚNICAMENTE con:
{"tipo":"texto","respuesta":"Tu respuesta aquí"}
- SIEMPRE responde con JSON válido. Sin texto fuera del JSON.
PROMPT;

        // Historial reciente para contexto (últimas 10 interacciones)
        $historialReciente = $this->model->getHistorial($usuarioId, 10);
        $messages = [];
        foreach ($historialReciente as $m) {
            if ($m['contenido'] === $pregunta) {
                continue; // El mensaje actual lo agregramos al final
            }
            $messages[] = ['role' => $m['rol'], 'content' => $m['contenido']];
        }
        $messages[] = ['role' => 'user', 'content' => $pregunta];

        // Primera llamada a Claude
        $respuestaIA = $this->llamarClaude($systemPrompt, $messages);
        if ($respuestaIA === null) {
            $error = 'No se pudo conectar con el servicio de IA. Verificá la API key.';
            $this->model->guardarMensaje($usuarioId, 'assistant', $error);
            $this->json(['ok' => false, 'error' => $error]);
        }

        $parsed = $this->parsearRespuestaIA($respuestaIA);

        // Si el modelo quiere ejecutar SQL
        if (($parsed['tipo'] ?? '') === 'sql' && !empty($parsed['sql'])) {
            $sql         = $parsed['sql'];
            $explicacion = $parsed['explicacion'] ?? '';

            try {
                $resultados = $this->model->ejecutarConsulta($sql);

                // Segunda llamada: interpretar resultados
                $cantFilas     = count($resultados);
                $muestraJSON   = json_encode(array_slice($resultados, 0, 10), JSON_UNESCAPED_UNICODE);
                $mensajeResult = "Ejecuté la consulta SQL y obtuve {$cantFilas} fila(s). " .
                                 "Muestra de hasta 10 resultados:\n{$muestraJSON}\n\n" .
                                 "Por favor interpretá los resultados y dá una respuesta clara y concisa al usuario. " .
                                 "Responde ÚNICAMENTE con: {\"tipo\":\"texto\",\"respuesta\":\"...\"}";

                $messages[] = ['role' => 'assistant', 'content' => $respuestaIA];
                $messages[] = ['role' => 'user',      'content' => $mensajeResult];

                $respuestaFinal = $this->llamarClaude($systemPrompt, $messages);
                if ($respuestaFinal === null) {
                    $respuestaFinal = '{"tipo":"texto","respuesta":"Obtuve los datos pero no pude interpretarlos."}';
                }
                $parsedFinal = $this->parsearRespuestaIA($respuestaFinal);
                $textoFinal  = $parsedFinal['respuesta'] ?? $respuestaFinal;

                $this->model->guardarMensaje($usuarioId, 'assistant', $textoFinal, $sql);
                $this->json([
                    'ok'          => true,
                    'tipo'        => 'sql',
                    'respuesta'   => $textoFinal,
                    'sql'         => $sql,
                    'explicacion' => $explicacion,
                    'resultados'  => $resultados,
                    'filas'       => $cantFilas,
                ]);
            } catch (\InvalidArgumentException $e) {
                $error = 'SQL no permitido: ' . $e->getMessage();
                $this->model->guardarMensaje($usuarioId, 'assistant', $error);
                $this->json(['ok' => false, 'error' => $error]);
            } catch (\PDOException $e) {
                $error = 'Error al ejecutar la consulta. Verificá la sintaxis.';
                $this->model->guardarMensaje($usuarioId, 'assistant', $error, $sql);
                $this->json(['ok' => false, 'error' => $error, 'sql' => $sql]);
            }
        }

        // Respuesta de texto directa
        $texto = $parsed['respuesta'] ?? $respuestaIA;
        $this->model->guardarMensaje($usuarioId, 'assistant', $texto);
        $this->json(['ok' => true, 'tipo' => 'texto', 'respuesta' => $texto]);
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function llamarClaude(string $system, array $messages): ?string
    {
        $payload = json_encode([
            'model'      => CLAUDE_MODEL,
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $messages,
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '        . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }

    private function parsearRespuestaIA(string $texto): array
    {
        // Intentar extraer JSON limpio (puede venir con backticks de markdown)
        $texto = preg_replace('/^```json\s*/m', '', $texto);
        $texto = preg_replace('/^```\s*/m', '',   $texto);
        $texto = trim($texto);

        $parsed = json_decode($texto, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        // Si Claude no devolvió JSON válido, buscar JSON embebido
        if (preg_match('/\{[\s\S]+\}/U', $texto, $m)) {
            $parsed = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        }

        return ['tipo' => 'texto', 'respuesta' => $texto];
    }
}
