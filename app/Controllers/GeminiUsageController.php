<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';

class GeminiUsageController extends Controller
{
    private GeminiUsageModel $model;

    public function __construct()
    {
        $this->model = new GeminiUsageModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['rol'] !== 'admin') abort(403);

        $tablaExiste = $this->tablaExiste();

        $config      = $this->model->getConfig();
        $categoria   = $this->param('categoria', 'chat');
        if (!in_array($categoria, GeminiUsageModel::CATEGORIAS, true)) $categoria = 'chat';

        $costoInputMillon   = (float) $config['costo_input_por_millon'];
        $costoOutputMillon  = (float) $config['costo_output_por_millon'];
        $costoVideoSegundo  = (float) $config['costo_video_por_segundo'];
        $tipoCambio         = (float) $config['tipo_cambio_usd_ars'];
        $limiteGratDia      = (int)   $config['llamadas_gratuitas_dia'];

        // Resumen de cada categoría (llamadas hoy/mes + costo estimado del mes),
        // para las 3 tarjetas separadas — así el gasto de imagen/video nunca se
        // mezcla con el conteo/cuota gratuita del chat de texto.
        $resumen = [];
        foreach (GeminiUsageModel::CATEGORIAS as $cat) {
            $statsMes = $tablaExiste ? $this->model->getStatsThisMonth($cat) : [];
            $tokensIn  = (int) ($statsMes['total_tokens_prompt']    ?? 0);
            $tokensOut = (int) ($statsMes['total_tokens_respuesta'] ?? 0);
            $segundos  = (int) ($statsMes['total_video_segundos']  ?? 0);

            $costoUsd = $cat === 'video'
                ? $segundos * $costoVideoSegundo
                : (($tokensIn / 1_000_000) * $costoInputMillon) + (($tokensOut / 1_000_000) * $costoOutputMillon);

            $resumen[$cat] = [
                'llamadas_hoy' => $tablaExiste ? $this->model->getCallsToday($cat) : 0,
                'llamadas_mes' => (int) ($statsMes['total_llamadas'] ?? 0),
                'tokens_mes'   => (int) ($statsMes['total_tokens_total'] ?? 0),
                'segundos_mes' => $segundos,
                'costo_usd'    => round($costoUsd, 4),
                'costo_ars'    => round($costoUsd * $tipoCambio, 2),
            ];
        }

        $statsXDia = $tablaExiste ? $this->model->getStatsPerDay(30, $categoria) : [];

        // Cadena de modelos de texto configurada (GEMINI_MODEL + GEMINI_MODEL_FALLBACK) con
        // su límite RPM/RPD cargado a mano (si existe) y el uso actual — mismo cálculo que
        // ChatController::elegirModeloConCupo(), acá solo para mostrarlo, no para decidir nada.
        $fallbacksTexto = array_filter(array_map('trim', explode(',', GEMINI_MODEL_FALLBACK)));
        $modelosTexto   = array_values(array_unique(array_filter(array_merge([GEMINI_MODEL], $fallbacksTexto))));
        $limitesModelos = $this->model->listarLimitesModelos();
        $modelosConUso  = [];
        require_once BASE_PATH . '/app/Models/GeminiLlamadaLogModel.php';
        $logModel = new GeminiLlamadaLogModel();
        foreach ($modelosTexto as $m) {
            try {
                $uso = $logModel->usoActualPorModelo($m);
            } catch (\Throwable $e) {
                $uso = ['rpm_usado' => 0, 'rpd_usado' => 0]; // gemini_llamadas_log aún no existe
            }
            $modelosConUso[] = [
                'modelo' => $m,
                'rpm'    => $limitesModelos[$m]['rpm'] ?? null,
                'rpd'    => $limitesModelos[$m]['rpd'] ?? null,
                'uso'    => $uso,
            ];
        }

        $this->render('gemini-usage.index', [
            'pageTitle'         => 'Uso de Gemini API',
            'breadcrumb'        => ['Asistente IA' => 'chat', 'Uso Gemini' => null],
            'tablaExiste'       => $tablaExiste,
            'config'            => $config,
            'resumen'           => $resumen,
            'categoriaFiltro'   => $categoria,
            'statsXDia'         => $statsXDia,
            'limiteGratDia'     => $limiteGratDia,
            'erroresRecientes'  => $this->model->getErroresRecientes(),
            'modelosConUso'     => $modelosConUso,
        ]);
    }

    public function guardarConfig(): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['rol'] !== 'admin') $this->json(['ok' => false, 'error' => 'Sin permisos.'], 403);
        Csrf::check();

        $campos = ['llamadas_gratuitas_dia', 'costo_input_por_millon', 'costo_output_por_millon', 'costo_video_por_segundo', 'tipo_cambio_usd_ars'];
        foreach ($campos as $campo) {
            $valor = trim($this->input($campo, ''));
            if ($valor !== '' && is_numeric($valor)) {
                $this->model->setConfig($campo, $valor);
            }
        }

        // Checkbox aparte del loop de arriba: a diferencia de los campos numéricos, un
        // checkbox destildado no manda NADA en el POST (no un '' vacío) — si se lo tratara
        // igual que los de arriba, destildarlo nunca se guardaría.
        $this->model->setConfig('modo_solo_pago', $this->input('modo_solo_pago') ? '1' : '0');

        $this->json(['ok' => true, 'mensaje' => 'Configuración guardada.']);
    }

    /** Guarda los límites RPM/RPD cargados a mano por modelo (ver database/gemini_modelos_limite_setup.sql). */
    public function guardarLimitesModelos(): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['rol'] !== 'admin') $this->json(['ok' => false, 'error' => 'Sin permisos.'], 403);
        Csrf::check();

        $modelos = (array) ($_POST['modelo'] ?? []);
        $rpms    = (array) ($_POST['rpm']    ?? []);
        $rpds    = (array) ($_POST['rpd']    ?? []);

        foreach ($modelos as $i => $modelo) {
            $modelo = trim((string) $modelo);
            if ($modelo === '') continue;
            $rpm = isset($rpms[$i]) && $rpms[$i] !== '' ? (int) $rpms[$i] : null;
            $rpd = isset($rpds[$i]) && $rpds[$i] !== '' ? (int) $rpds[$i] : null;
            $this->model->guardarLimiteModelo($modelo, $rpm, $rpd);
        }

        $this->json(['ok' => true, 'mensaje' => 'Límites guardados.']);
    }

    private function tablaExiste(): bool
    {
        try {
            Database::getInstance()->query("SELECT 1 FROM gemini_llamadas LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
