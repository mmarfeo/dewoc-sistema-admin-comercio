<?php
class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . self::token() . '">';
    }

    public static function verify(): bool
    {
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return hash_equals(self::token(), $token);
    }

    /**
     * Corta la ejecución si el token no es válido.
     * La inmensa mayoría de los formularios de esta app se envían por fetch()/AJAX y
     * esperan JSON de vuelta ($json = true, default): responder texto plano ahí rompía
     * el `await r.json()` del frontend con "Unexpected token" y el usuario no veía
     * ningún aviso. Los pocos formularios tradicionales (login, recuperar contraseña,
     * descarga de backup) pasan $json = false para volver a mostrar la página con el error.
     */
    public static function check(bool $json = true): void
    {
        if (!self::verify()) {
            http_response_code(403);
            if ($json) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Tu sesión expiró. Recargá la página e intentá de nuevo.',
                ]);
            } else {
                echo 'CSRF token inválido. Recargá la página e intentá de nuevo.';
            }
            exit;
        }
    }
}
