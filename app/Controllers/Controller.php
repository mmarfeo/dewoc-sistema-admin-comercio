<?php
abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            abort(500, "Vista no encontrada: {$view}");
        }

        require $viewPath;
    }

    /** Renderiza una vista dentro del layout principal */
    protected function render(string $view, array $data = [], string $layout = 'layouts.main'): void
    {
        $data['view']    = $view;
        $data['content'] = $this->capture($view, $data);
        $this->view($layout, $data);
    }

    /** Captura el output de una vista como string */
    private function capture(string $view, array $data): string
    {
        extract($data);
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
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
                redirect('auth/login');
            }
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
