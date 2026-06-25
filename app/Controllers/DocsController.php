<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';

class DocsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('docs.index', [
            'pageTitle' => 'Documentación — ' . APP_NAME,
        ]);
    }

    public function descargar(): void
    {
        $this->requireAuth();
        $allowed = ['arquitectura.drawio', 'flujo_usuario.drawio'];
        $file    = basename($this->param('file') ?? '');

        if (!in_array($file, $allowed, true)) {
            http_response_code(404);
            exit;
        }

        $path = BASE_PATH . '/docs/' . $file;
        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
