<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GaleriaModel.php';

class GaleriaController extends Controller
{
    private GaleriaModel $model;

    public function __construct()
    {
        $this->model = new GaleriaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $tipo = $this->param('tipo', '');
        $this->render('galeria.index', [
            'pageTitle'  => 'Galería',
            'breadcrumb' => ['Asistente IA' => null, 'Galería' => null],
            'archivos'   => $this->model->findAllActivos($tipo ?: null),
            'tipoFiltro' => $tipo,
        ]);
    }

    public function subir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuarioId = (int)(Session::user()['id'] ?? 0);
        $archivo   = $this->_subirArchivoGaleria($usuarioId);

        if (!$archivo) {
            $this->json(['ok' => false, 'error' => 'Archivo no válido (imagen JPG/PNG/WEBP máx. 5 MB, o video MP4/MOV máx. 50 MB).'], 422);
        }

        $id = $this->model->insert([
            'usuario_id'      => $usuarioId,
            'tipo'            => $archivo['tipo'],
            'origen'          => 'subido',
            'nombre_archivo'  => $archivo['nombre'],
            'nombre_original' => $archivo['nombre_original'],
            'mime'            => $archivo['mime'],
            'tamano_bytes'    => $archivo['tamano'],
            'estado'          => 'listo',
        ]);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Archivo subido correctamente.']);
    }

    /** GET: sirve el archivo con auth (imagen o video) */
    public function ver(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $archivo = $id ? $this->model->findById($id) : null;

        if (!$archivo || (int)$archivo['eliminado'] === 1 || $archivo['nombre_archivo'] === '') {
            http_response_code(404); exit;
        }

        $path = BASE_PATH . '/storage/galeria/' . basename($archivo['nombre_archivo']);
        if (!is_file($path)) { http_response_code(404); exit; }

        header('Content-Type: ' . $archivo['mime']);
        header('Content-Disposition: inline; filename="' . basename($archivo['nombre_archivo']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        }

        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Archivo eliminado.']);
    }

    /**
     * Mini-chat de la Galería: toma una foto ya subida + una instrucción libre
     * ("mejorá esta foto", "hacela más luminosa para una story", etc.) y le pide
     * a Gemini que genere una imagen nueva a partir de esa foto real. El
     * resultado se guarda como un archivo más de la galería (origen=generado_ia).
     * Separado a propósito del Chat IA general: ese asistente no genera ni edita
     * imágenes/video, y le indica al usuario que use esta sección para eso.
     */
    public function generar(): void
    {
        set_time_limit(120);
        $this->requireAuth();
        Csrf::check();

        $archivoId   = (int)$this->input('archivo_id', 0);
        $instruccion = trim($this->input('instruccion', ''));
        $proveedor   = $this->input('proveedor', 'gemini') === 'nvidia' ? 'nvidia' : 'gemini';

        if (!$archivoId || $instruccion === '') {
            $this->json(['ok' => false, 'error' => 'Elegí una foto y escribí qué querés que la IA le haga.'], 422);
        }

        $origenArchivo = $this->model->findById($archivoId);
        if (!$origenArchivo || (int)$origenArchivo['eliminado'] === 1 || $origenArchivo['tipo'] !== 'imagen') {
            $this->json(['ok' => false, 'error' => 'La foto seleccionada no existe o no es una imagen.'], 422);
        }

        if ($proveedor === 'gemini' && GEMINI_MEDIA_API_KEY === '') {
            $this->json(['ok' => false, 'error' => 'Falta configurar GEMINI_MEDIA_API_KEY en el archivo .env.'], 422);
        }
        if ($proveedor === 'nvidia' && NVIDIA_API_KEY === '') {
            $this->json(['ok' => false, 'error' => 'Falta configurar NVIDIA_API_KEY en el archivo .env.'], 422);
        }

        $path = BASE_PATH . '/storage/galeria/' . basename($origenArchivo['nombre_archivo']);
        if (!file_exists($path)) {
            $this->json(['ok' => false, 'error' => 'No se encontró el archivo original en el servidor.'], 404);
        }

        $base64  = base64_encode(file_get_contents($path));
        $mime    = $origenArchivo['mime'];
        $imgMime = null;
        $imgData = null;
        $texto   = '';

        if ($proveedor === 'gemini') {
            $resp = $this->_llamarGeminiImagen($mime, $base64, $instruccion);
            if ($resp === null) {
                $this->json(['ok' => false, 'error' => 'No se pudo conectar con Gemini. Probá de nuevo en un momento.'], 502);
            }
            $parts = $resp['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['text'])) $texto .= $part['text'];
                $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
                if ($inline && empty($imgData)) {
                    $imgMime = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png';
                    $imgData = $inline['data']     ?? null;
                }
            }
        } else {
            $resultado = $this->_llamarNvidiaImagen($mime, $base64, $instruccion);
            if ($resultado === null) {
                $this->json(['ok' => false, 'error' => 'No se pudo conectar con NVIDIA. Probá de nuevo en un momento, o probá con el proveedor Google.'], 502);
            }
            $imgMime = $resultado['mime'];
            $imgData = $resultado['base64'];
        }

        if (!$imgData) {
            // El modelo respondió solo con texto (ej. rechazó el pedido) — no hay imagen para guardar.
            $this->json(['ok' => true, 'tipo' => 'texto', 'respuesta' => $texto ?: 'No se generó ninguna imagen. Probá con otra instrucción.']);
        }

        $ext = match ($imgMime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };

        $dir = BASE_PATH . '/storage/galeria/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $usuarioId = (int)(Session::user()['id'] ?? 0);
        $nombre    = 'gal_ia_' . $usuarioId . '_' . uniqid('', true) . '.' . $ext;
        $bytes     = base64_decode($imgData);
        file_put_contents($dir . $nombre, $bytes);

        $id = $this->model->insert([
            'usuario_id'      => $usuarioId,
            'tipo'            => 'imagen',
            'origen'          => 'generado_ia',
            'proveedor_ia'    => $proveedor,
            'nombre_archivo'  => $nombre,
            'nombre_original' => $origenArchivo['nombre_original'],
            'mime'            => $imgMime,
            'tamano_bytes'    => strlen($bytes),
            'estado'          => 'listo',
        ]);

        $this->json([
            'ok'        => true,
            'tipo'      => 'imagen',
            'id'        => $id,
            'respuesta' => $texto,
        ]);
    }

    /**
     * Inicia la generación de un video (Veo) a partir de una foto real + instrucción.
     * Es asíncrona: acá solo se dispara el job (predictLongRunning) y se guarda un
     * registro en estado='generando'; el resultado se resuelve en estado() por polling
     * desde el frontend, ya que este proyecto no tiene cola de trabajos ni workers.
     */
    public function generarVideo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $archivoId   = (int)$this->input('archivo_id', 0);
        $instruccion = trim($this->input('instruccion', ''));
        $proveedor   = $this->input('proveedor', 'gemini') === 'nvidia' ? 'nvidia' : 'gemini';

        if (!$archivoId || $instruccion === '') {
            $this->json(['ok' => false, 'error' => 'Elegí una foto y escribí qué querés que la IA haga con el video.'], 422);
        }

        $origenArchivo = $this->model->findById($archivoId);
        if (!$origenArchivo || (int)$origenArchivo['eliminado'] === 1 || $origenArchivo['tipo'] !== 'imagen') {
            $this->json(['ok' => false, 'error' => 'La foto seleccionada no existe o no es una imagen.'], 422);
        }

        if ($proveedor === 'gemini' && GEMINI_MEDIA_API_KEY === '') {
            $this->json(['ok' => false, 'error' => 'Falta configurar GEMINI_MEDIA_API_KEY en el archivo .env.'], 422);
        }
        if ($proveedor === 'nvidia' && NVIDIA_API_KEY === '') {
            $this->json(['ok' => false, 'error' => 'Falta configurar NVIDIA_API_KEY en el archivo .env.'], 422);
        }

        $path = BASE_PATH . '/storage/galeria/' . basename($origenArchivo['nombre_archivo']);
        if (!file_exists($path)) {
            $this->json(['ok' => false, 'error' => 'No se encontró el archivo original en el servidor.'], 404);
        }

        $base64      = base64_encode(file_get_contents($path));
        $mime        = $origenArchivo['mime'];
        $operationId = $proveedor === 'gemini'
            ? $this->_iniciarGeminiVideo($mime, $base64, $instruccion)
            : $this->_iniciarNvidiaVideo($mime, $base64, $instruccion);

        if (!$operationId) {
            $this->json(['ok' => false, 'error' => 'No se pudo iniciar la generación del video. Probá de nuevo en un momento, o probá con el otro proveedor.'], 502);
        }

        $usuarioId = (int)(Session::user()['id'] ?? 0);
        $id = $this->model->insert([
            'usuario_id'       => $usuarioId,
            'tipo'             => 'video',
            'origen'           => 'generado_ia',
            'proveedor_ia'     => $proveedor,
            'nombre_archivo'   => '',
            'nombre_original'  => $origenArchivo['nombre_original'],
            'mime'             => 'video/mp4',
            'tamano_bytes'     => 0,
            'estado'           => 'generando',
            'proveedor_job_id' => $operationId,
        ]);

        $this->json(['ok' => true, 'id' => $id, 'estado' => 'generando']);
    }

    /** GET: consulta el estado de un archivo (usado para el polling de video) */
    public function estado(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $archivo = $id ? $this->model->findById($id) : null;

        if (!$archivo || (int)$archivo['eliminado'] === 1) {
            $this->json(['ok' => false, 'error' => 'No encontrado.'], 404);
        }

        if ($archivo['estado'] !== 'generando' || !$archivo['proveedor_job_id']) {
            $this->json(['ok' => true, 'estado' => $archivo['estado'], 'error' => $archivo['error_mensaje']]);
        }

        $resultado = $archivo['proveedor_ia'] === 'nvidia'
            ? $this->_consultarOperacionNvidia($archivo['proveedor_job_id'])
            : $this->_consultarOperacionVideo($archivo['proveedor_job_id']);

        if (!$resultado['done']) {
            $this->json(['ok' => true, 'estado' => 'generando']);
        }

        if ($resultado['error']) {
            $this->model->update($id, ['estado' => 'error', 'error_mensaje' => substr($resultado['error'], 0, 255)]);
            $this->json(['ok' => true, 'estado' => 'error', 'error' => $resultado['error']]);
        }

        if (!$resultado['videoBytes']) {
            $this->model->update($id, ['estado' => 'error', 'error_mensaje' => 'Respuesta de Gemini sin video reconocible (ver logs).']);
            $this->json(['ok' => true, 'estado' => 'error', 'error' => 'La IA no devolvió ningún video reconocible.']);
        }

        $dir = BASE_PATH . '/storage/galeria/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $usuarioId = (int)$archivo['usuario_id'];
        $nombre    = 'gal_ia_' . $usuarioId . '_' . uniqid('', true) . '.mp4';
        file_put_contents($dir . $nombre, $resultado['videoBytes']);

        $this->model->update($id, [
            'nombre_archivo' => $nombre,
            'mime'           => 'video/mp4',
            'tamano_bytes'   => strlen($resultado['videoBytes']),
            'estado'         => 'listo',
        ]);

        if ($archivo['proveedor_ia'] === 'gemini') {
            $this->_registrarUsoVideo($usuarioId);
        }

        $this->json(['ok' => true, 'estado' => 'listo', 'id' => $id]);
    }

    /**
     * Registra el consumo de un video de Veo para la pantalla "Uso Gemini". A
     * diferencia del chat/imagen, Veo no reporta tokens — se factura por segundo
     * de video generado. Como no pedimos una duración explícita en el request
     * (num_output_frames), se asume la duración por defecto de Veo 3.1 (8s) como
     * estimación; ajustable en el código si Google cambia el default.
     */
    private function _registrarUsoVideo(int $usuarioId): void
    {
        try {
            require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
            (new GeminiUsageModel())->log(
                GEMINI_VIDEO_MODEL,
                0,
                0,
                0,
                $usuarioId,
                'video',
                8
            );
        } catch (\Throwable $e) { /* tabla aún no existe — ignorar */ }
    }

    /** Dispara el job de Veo (predictLongRunning) y devuelve el nombre de la operación */
    private function _iniciarGeminiVideo(string $mime, string $base64, string $instruccion): ?string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . GEMINI_VIDEO_MODEL . ':predictLongRunning?key=' . GEMINI_MEDIA_API_KEY;

        $payload = json_encode([
            'instances' => [[
                'prompt' => $instruccion,
                'image'  => ['bytesBase64Encoded' => $base64, 'mimeType' => $mime],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            logApp('Veo predictLongRunning falló (HTTP ' . $httpCode . '): ' . substr((string)$response, 0, 500), 'ERROR');
            return null;
        }

        $data = json_decode($response, true);
        return $data['name'] ?? null;
    }

    /**
     * Consulta el estado de una operación de Veo (operations.get). El formato exacto
     * de la respuesta puede variar entre versiones de la API — se registra la
     * respuesta cruda en el log cuando no se reconoce el video, para poder ajustar
     * el parseo sin tener que adivinar a ciegas.
     */
    private function _consultarOperacionVideo(string $operationName): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/' . $operationName . '?key=' . GEMINI_MEDIA_API_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return ['done' => false, 'error' => null, 'videoBytes' => null];
        }

        $data = json_decode($response, true) ?? [];
        $done = (bool)($data['done'] ?? false);

        if (!$done) {
            return ['done' => false, 'error' => null, 'videoBytes' => null];
        }

        if (!empty($data['error']['message'])) {
            return ['done' => true, 'error' => $data['error']['message'], 'videoBytes' => null];
        }

        // Rutas posibles según variantes documentadas de la respuesta de Veo.
        $muestras = $data['response']['generateVideoResponse']['generatedSamples']
            ?? $data['response']['generatedSamples']
            ?? [];

        $video = $muestras[0]['video'] ?? null;

        if (isset($video['bytesBase64Encoded'])) {
            return ['done' => true, 'error' => null, 'videoBytes' => base64_decode($video['bytesBase64Encoded'])];
        }

        if (!empty($video['uri'])) {
            $videoBytes = $this->_descargarArchivoGemini($video['uri']);
            if ($videoBytes !== null) {
                return ['done' => true, 'error' => null, 'videoBytes' => $videoBytes];
            }
        }

        logApp('Veo operations.get: no se reconoció el video en la respuesta: ' . substr($response, 0, 1000), 'ERROR');
        return ['done' => true, 'error' => null, 'videoBytes' => null];
    }

    /** Descarga un archivo devuelto por Gemini (uri firmada) agregando la API key */
    private function _descargarArchivoGemini(string $uri): ?string
    {
        $url = $uri . (str_contains($uri, '?') ? '&' : '?') . 'key=' . GEMINI_MEDIA_API_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ]);
        $bytes    = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($bytes === false || $httpCode !== 200) {
            logApp('Descarga de archivo Gemini falló - HTTP ' . $httpCode . ' curl_error=' . $curlErr . ' uri=' . $uri . ' body=' . substr((string)$bytes, 0, 500), 'ERROR');
            return null;
        }

        return $bytes;
    }

    /**
     * Llama a un modelo de imagen de NVIDIA NIM (ej. Qwen-Image) con una foto real +
     * instrucción. El endpoint/formato exacto es el patrón más común de NIM para
     * modelos "genai" comunitarios (POST + Bearer token + JSON, respuesta con imagen
     * en base64) — NO se verificó contra una cuenta real todavía. Si NVIDIA_IMAGE_MODEL
     * usa un contrato distinto, esto va a fallar y quedará el detalle en el log para
     * ajustar el parseo con el error real en vez de adivinar de nuevo a ciegas.
     */
    private function _llamarNvidiaImagen(string $mime, string $base64, string $instruccion): ?array
    {
        $url = 'https://ai.api.nvidia.com/v1/genai/' . NVIDIA_IMAGE_MODEL;

        $payload = json_encode([
            'prompt' => $instruccion,
            'image'  => $base64,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . NVIDIA_API_KEY,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            logApp('NVIDIA NIM genai (imagen) falló - HTTP ' . $httpCode . ' curl_error=' . $curlErr . ' body=' . substr((string)$response, 0, 1000), 'ERROR');
            return null;
        }

        $data = json_decode($response, true) ?? [];

        // Rutas posibles según el estilo de API que use el modelo (OpenAI-images,
        // Stability-style artifacts, o un campo "image" plano).
        $b64 = $data['image']
            ?? $data['data'][0]['b64_json']
            ?? $data['artifacts'][0]['base64']
            ?? null;

        if (!$b64) {
            logApp('NVIDIA NIM genai (imagen): no se reconoció la imagen en la respuesta: ' . substr($response, 0, 1000), 'ERROR');
            return null;
        }

        return ['base64' => $b64, 'mime' => 'image/png'];
    }

    /**
     * Inicia un job de video en NVIDIA NIM (ej. Wan 2.1). Sigue el patrón asíncrono
     * de NVIDIA Cloud Functions (NVCF): un POST que devuelve 202 + un id de request
     * para pollear después. Igual que con la imagen, el contrato exacto depende del
     * modelo y no está verificado contra una cuenta real — ver logs si falla.
     */
    private function _iniciarNvidiaVideo(string $mime, string $base64, string $instruccion): ?string
    {
        $url = 'https://ai.api.nvidia.com/v1/genai/' . NVIDIA_VIDEO_MODEL;

        $payload = json_encode([
            'prompt' => $instruccion,
            'image'  => $base64,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . NVIDIA_API_KEY,
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || !in_array($httpCode, [200, 202], true)) {
            logApp('NVIDIA NIM genai (video) falló - HTTP ' . $httpCode . ' body=' . substr((string)$raw, 0, 1000), 'ERROR');
            return null;
        }

        $headers = substr($raw, 0, $headerSize);
        $body    = substr($raw, $headerSize);

        if (preg_match('/nvcf-reqid:\s*(\S+)/i', $headers, $m)) {
            return trim($m[1]);
        }

        $data = json_decode($body, true) ?? [];
        $reqId = $data['request_id'] ?? $data['id'] ?? null;

        if (!$reqId) {
            logApp('NVIDIA NIM genai (video): no se encontró request id en la respuesta: ' . substr($body, 0, 1000), 'ERROR');
        }

        return $reqId;
    }

    /** Consulta el estado de un job de video en NVIDIA (NVCF pexec/status) */
    private function _consultarOperacionNvidia(string $requestId): array
    {
        $url = 'https://api.nvcf.nvidia.com/v2/nvcf/pexec/status/' . $requestId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . NVIDIA_API_KEY],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 202) {
            return ['done' => false, 'error' => null, 'videoBytes' => null];
        }

        if ($response === false || $httpCode !== 200) {
            logApp('NVIDIA NVCF status falló - HTTP ' . $httpCode . ' body=' . substr((string)$response, 0, 1000), 'ERROR');
            return ['done' => false, 'error' => null, 'videoBytes' => null];
        }

        $data = json_decode($response, true) ?? [];

        $b64 = $data['video']
            ?? $data['artifacts'][0]['base64']
            ?? $data['data'][0]['b64_json']
            ?? null;

        if ($b64) {
            return ['done' => true, 'error' => null, 'videoBytes' => base64_decode($b64)];
        }

        $uri = $data['video_url'] ?? $data['url'] ?? null;
        if ($uri) {
            $ch = curl_init($uri);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . NVIDIA_API_KEY],
            ]);
            $bytes = curl_exec($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($bytes !== false && $code === 200) {
                return ['done' => true, 'error' => null, 'videoBytes' => $bytes];
            }
        }

        logApp('NVIDIA NVCF status: no se reconoció el video en la respuesta: ' . substr($response, 0, 1000), 'ERROR');
        return ['done' => true, 'error' => null, 'videoBytes' => null];
    }

    /** Llama a Gemini (modelo con salida de imagen) con una foto real + instrucción */
    private function _llamarGeminiImagen(string $mime, string $base64, string $instruccion): ?array
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
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
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
            logApp('Gemini generateContent (imagen) falló - HTTP ' . $httpCode . ' curl_error=' . $curlErr . ' body=' . substr((string)$response, 0, 1000), 'ERROR');
            return null;
        }

        $data = json_decode($response, true);

        if ($data) {
            $usage = $data['usageMetadata'] ?? [];
            try {
                require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
                $uid = null;
                try { $uid = (int) Session::user()['id']; } catch (\Throwable $e) {}
                (new GeminiUsageModel())->log(
                    GEMINI_IMAGE_MODEL,
                    (int) ($usage['promptTokenCount']     ?? 0),
                    (int) ($usage['candidatesTokenCount'] ?? 0),
                    (int) ($usage['totalTokenCount']      ?? 0),
                    $uid,
                    'imagen'
                );
            } catch (\Throwable $e) { /* tabla aún no existe — ignorar */ }
        }

        return $data;
    }

    /** Mueve el archivo subido en $_FILES['archivo'] a storage/galeria/ */
    private function _subirArchivoGaleria(int $usuarioId): ?array
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['archivo'];
        $mime = mime_content_type($file['tmp_name']);

        if (in_array($mime, ALLOWED_IMAGES, true) && $file['size'] <= MAX_FILE_SIZE) {
            $tipo = 'imagen';
        } elseif (in_array($mime, ALLOWED_VIDEOS, true) && $file['size'] <= MAX_VIDEO_SIZE) {
            $tipo = 'video';
        } else {
            return null;
        }

        $dir = BASE_PATH . '/storage/galeria/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext = match ($mime) {
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            'video/mp4'        => 'mp4',
            'video/quicktime'  => 'mov',
            default            => 'jpg',
        };

        $nombre = 'gal_' . $usuarioId . '_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
            return null;
        }

        return [
            'nombre'          => $nombre,
            'nombre_original' => $file['name'],
            'mime'            => $mime,
            'tamano'          => $file['size'],
            'tipo'            => $tipo,
        ];
    }
}
