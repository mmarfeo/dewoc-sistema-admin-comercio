<?php
class Router
{
    private array $routes = [];

    public function get(string $path, string $controller, string $method): void
    {
        $this->routes['GET'][$path] = [$controller, $method];
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->routes['POST'][$path] = [$controller, $method];
    }

    public function dispatch(string $url, string $httpMethod): void
    {
        $url        = trim($url, '/');
        $httpMethod = strtoupper($httpMethod);
        $segments   = explode('/', $url);

        // Intentar ruta exacta primero
        if (isset($this->routes[$httpMethod][$url])) {
            [$ctrl, $method] = $this->routes[$httpMethod][$url];
            $this->call($ctrl, $method, []);
            return;
        }

        // Resolución convencional: /controller/method/param1/param2
        $controllerName = !empty($segments[0]) ? ucfirst(strtolower($segments[0])) . 'Controller' : 'ChatController';
        $methodName     = $segments[1] ?? 'index';
        $params         = array_slice($segments, 2);

        $this->call($controllerName, $methodName, $params);
    }

    private function call(string $controllerName, string $methodName, array $params): void
    {
        $file = BASE_PATH . '/app/Controllers/' . $controllerName . '.php';

        if (!file_exists($file)) {
            $this->notFound();
            return;
        }

        require_once $file;

        if (!class_exists($controllerName)) {
            $this->notFound();
            return;
        }

        $ctrl = new $controllerName();

        if (!method_exists($ctrl, $methodName)) {
            $this->notFound();
            return;
        }

        call_user_func_array([$ctrl, $methodName], $params);
    }

    private function notFound(): void
    {
        http_response_code(404);
        require BASE_PATH . '/app/Views/errors/404.php';
    }
}
