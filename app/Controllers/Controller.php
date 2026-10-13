<?php
abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            abort(500, "Vista no encontrada: {$view}");
        }

        extract($data);
        require $viewPath;
    }

    /** Renderiza una vista dentro del layout principal */
    protected function render(string $view, array $data = [], string $layout = 'layouts.main'): void
    {
        $this->registrarUsoAnalitica($view);
        $data['view']    = $view;
        $data['content'] = $this->capture($view, $data);
        $this->view($layout, $data);
    }

    /**
     * Analítica de uso (ver docs/auditoria-analitica-uso.md): un evento por cada pantalla
     * completa que se renderiza, para saber qué se usa y qué no. Deliberadamente solo acá
     * (no en json()) — cubre navegación normal, no cada acción AJAX puntual. Nunca debe
     * poder tirar abajo una vista por un error de este conteo, por eso el try/catch amplio,
     * mismo criterio que AuditLog::log().
     */
    private function registrarUsoAnalitica(string $view): void
    {
        $usuario = Session::user();
        if (!$usuario) return;

        try {
            [$modulo, $accion] = array_pad(explode('.', $view, 2), 2, 'index');
            require_once BASE_PATH . '/app/Models/AnaliticaUsoModel.php';
            (new AnaliticaUsoModel())->registrar((int)$usuario['id'], $modulo, $accion);
        } catch (\Throwable $e) {
            logApp('Error registrando analítica de uso: ' . $e->getMessage());
        }
    }

    /** Captura el output de una vista como string */
    private function capture(string $view, array $data): string
    {
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        extract($data);
        ob_start();
        require $viewPath;
        return ob_get_clean();
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            require_once BASE_PATH . '/core/RememberMe.php';
            require_once BASE_PATH . '/app/Models/UsuarioModel.php';
            if (!RememberMe::check(new UsuarioModel())) {
                // Un fetch()/AJAX (la inmensa mayoría de las acciones de esta app, ej. guardar
                // una venta del POS) no puede seguir un redirect a la pantalla de login: el
                // llamador espera JSON y termina viendo "Error de conexión" sin explicación,
                // perdiendo lo que estaba haciendo. Acá se responde un JSON puntual que el
                // wrapper de fetch() en assets/js/app.js reconoce para mostrar un modal de
                // reconexión sin recargar la página (ver AuthController::reconectar()). La
                // navegación normal (GET de una página completa) sigue redirigiendo como antes.
                $esAjax = $this->isPost() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
                if ($esAjax) {
                    require_once BASE_PATH . '/core/Csrf.php';
                    $this->json(['ok' => false, 'error' => 'sesion_expirada', 'csrf_token' => Csrf::token()], 401);
                }
                redirect('auth/login');
            }
        }
        $this->generarGastosRecurrentesSiCorresponde();
        $this->generarSolicitudesRecurrentesSiCorresponde();
        $this->purgarAnaliticaUsoSiCorresponde();
    }

    /**
     * Dispara la generación de costos fijos recurrentes (alquiler, sueldos, etc.)
     * de forma perezosa: no depende de un cron del servidor, corre sola en el
     * primer request autenticado de cada día de sesión (throttleada acá para no
     * pegarle a la base en cada request).
     */
    private function generarGastosRecurrentesSiCorresponde(): void
    {
        $hoy = date('Y-m-d');
        if (Session::get('recurrentes_check_fecha') === $hoy) return;

        try {
            require_once BASE_PATH . '/app/Models/RecurrenteModel.php';
            $usuarioId = Session::user()['id'] ?? 0;
            (new RecurrenteModel())->generarPendientes((int)$usuarioId);
            // Solo marcamos el día como "ya chequeado" si no hubo error — si falla
            // (ej. falta correr una migración), reintenta en el próximo request en
            // vez de quedar bloqueado hasta mañana sin que nadie se entere.
            Session::set('recurrentes_check_fecha', $hoy);
        } catch (\Throwable $e) {
            logApp('Error generando gastos recurrentes: ' . $e->getMessage());
        }
    }

    /**
     * Dispara la generación de solicitudes de inventario recurrentes (ej. "todos los
     * martes, Helados") de forma perezosa, mismo mecanismo y mismo throttle por
     * sesión/día que generarGastosRecurrentesSiCorresponde() (ver ese método).
     */
    private function generarSolicitudesRecurrentesSiCorresponde(): void
    {
        $hoy = date('Y-m-d');
        if (Session::get('solicitudes_recurrentes_check_fecha') === $hoy) return;

        try {
            require_once BASE_PATH . '/app/Models/SolicitudRecurrenteModel.php';
            $usuarioId = Session::user()['id'] ?? 0;
            (new SolicitudRecurrenteModel())->generarPendientes((int)$usuarioId);
            Session::set('solicitudes_recurrentes_check_fecha', $hoy);
        } catch (\Throwable $e) {
            logApp('Error generando solicitudes recurrentes: ' . $e->getMessage());
        }
    }

    /**
     * Retención de analítica de uso: 6 meses de detalle diario (ver docs/auditoria-analitica-uso.md
     * sección 8) — después de eso no aporta y solo hace crecer la tabla sin límite. Mismo
     * patrón perezoso que generarGastosRecurrentesSiCorresponde(): sin cron real, corre
     * como mucho una vez por día de sesión.
     */
    private function purgarAnaliticaUsoSiCorresponde(): void
    {
        $hoy = date('Y-m-d');
        if (Session::get('analitica_purge_check_fecha') === $hoy) return;

        try {
            require_once BASE_PATH . '/app/Models/AnaliticaUsoModel.php';
            $limite = date('Y-m-d', strtotime('-6 months'));
            (new AnaliticaUsoModel())->purgarAnteriorA($limite);
            Session::set('analitica_purge_check_fecha', $hoy);
        } catch (\Throwable $e) {
            logApp('Error purgando analítica de uso: ' . $e->getMessage());
        }
    }

    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        $user = Session::user();
        if (!in_array($user['rol'], $roles)) {
            abort(403, 'Sin permisos para acceder a este recurso.');
        }
    }

    /**
     * Exige sesión de CLIENTE activa (portal de clientes mayoristas, ver
     * docs/portal-clientes-mayoristas-checklist.md) — sesión completamente aparte de la de
     * staff (`auth_user`): usa su propia key `auth_cliente`, así un cliente logueado nunca
     * colisiona con ni puede confundirse con una sesión de `usuarios`. No hay remember-me
     * ni bloqueo por intentos fallidos en esta primera versión.
     */
    protected function requireAuthCliente(): void
    {
        if (!Session::has('auth_cliente')) {
            $esAjax = $this->isPost() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'sesion_expirada'], 401);
            }
            redirect('portal/login');
        }
    }

    /** Sesión de cliente actual del portal, o null si no hay ninguna logueada. */
    protected function clienteActual(): ?array
    {
        return Session::get('auth_cliente');
    }

    /** Exige sesión activa y permiso de "ver" sobre un módulo (rol + excepciones del usuario) */
    protected function requireCanView(string $modulo): void
    {
        $this->requireAuth();
        if (!canView($modulo)) {
            abort(403, 'No tenés permiso para acceder a esta sección.');
        }
    }

    /** Exige sesión activa y permiso de "editar" sobre un módulo (rol + excepciones del usuario) */
    protected function requireCanEdit(string $modulo): void
    {
        $this->requireAuth();
        if (!canEdit($modulo)) {
            abort(403, 'No tenés permiso para acceder a esta sección.');
        }
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
    }

    protected function param(string $key, mixed $default = null): mixed
    {
        return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
    }
}
