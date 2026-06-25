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

    /** Lanza excepción si el token no es válido */
    public static function check(): void
    {
        if (!self::verify()) {
            http_response_code(403);
            die('CSRF token inválido.');
        }
    }
}
