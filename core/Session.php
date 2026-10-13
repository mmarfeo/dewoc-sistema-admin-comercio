<?php
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // El hosting suele tener session.gc_maxlifetime más corto que la cookie
            // (ej. 24 min por defecto), lo que borra la sesión en el servidor antes de
            // que expire la cookie del navegador y causa "CSRF inválido" en pestañas
            // abiertas por un rato. Lo alineamos con SESSION_LIFETIME acá.
            ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false, // true en producción con HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }

    public static function destroy(): void
    {
        session_unset();
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Devuelve el usuario autenticado de la sesión o null */
    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['auth_user']);
    }
}
