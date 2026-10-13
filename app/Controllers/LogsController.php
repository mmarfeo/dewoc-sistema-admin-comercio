<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';

class LogsController extends Controller
{
    private const MAX_LINEAS = 300;

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $archivo = $this->param('archivo', 'app') === 'php' ? 'php' : 'app';

        $rutaApp = BASE_PATH . '/storage/logs/app.log';
        $rutaPhp = BASE_PATH . '/storage/logs/php_errors.log';

        $lineas = $this->_leerUltimasLineas($archivo === 'php' ? $rutaPhp : $rutaApp, self::MAX_LINEAS);

        $this->render('logs.index', [
            'pageTitle'   => 'Logs del Sistema',
            'breadcrumb'  => ['Administración' => null, 'Logs' => null],
            'archivo'     => $archivo,
            'lineas'      => array_reverse($lineas), // más reciente primero
            'existeApp'   => file_exists($rutaApp),
            'existePhp'   => file_exists($rutaPhp),
        ]);
    }

    /** AJAX: refresca el contenido sin recargar la página */
    public function contenido(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $archivo = $this->param('archivo', 'app') === 'php' ? 'php' : 'app';
        $ruta    = BASE_PATH . '/storage/logs/' . ($archivo === 'php' ? 'php_errors.log' : 'app.log');

        $lineas = array_reverse($this->_leerUltimasLineas($ruta, self::MAX_LINEAS));
        $this->json(['ok' => true, 'lineas' => $lineas]);
    }

    public function descargar(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $archivo = $this->param('archivo', 'app') === 'php' ? 'php_errors.log' : 'app.log';
        $ruta    = BASE_PATH . '/storage/logs/' . $archivo;

        if (!file_exists($ruta)) { http_response_code(404); echo 'No encontrado.'; exit; }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /** Lee las últimas $max líneas de un archivo sin cargarlo entero en memoria si es grande */
    private function _leerUltimasLineas(string $ruta, int $max): array
    {
        if (!file_exists($ruta)) return [];

        $tamanioMax = 2 * 1024 * 1024; // 2 MB de cola como máximo
        $size = filesize($ruta);
        $fh   = fopen($ruta, 'r');
        if (!$fh) return [];

        if ($size > $tamanioMax) {
            fseek($fh, -$tamanioMax, SEEK_END);
            fgets($fh); // descartar línea parcial inicial
        }

        $lineas = [];
        while (($linea = fgets($fh)) !== false) {
            $linea = rtrim($linea, "\r\n");
            if ($linea === '') continue;
            $lineas[] = $linea;
            if (count($lineas) > $max) array_shift($lineas);
        }
        fclose($fh);

        return $lineas;
    }
}
