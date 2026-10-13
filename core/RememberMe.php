<?php
/**
 * Cookie "Recordarme" — token seguro de 64 hex chars, hash SHA-256 en DB.
 * Cookie: {userId}:{token}, httpOnly, SameSite=Lax, 30 días.
 * Aplica token rotation en cada auto-login.
 */
class RememberMe
{
    const COOKIE_NAME = 'remember_me';
    const DAYS        = 30;

    /**
     * Verifica la cookie y restaura la sesión si el token es válido.
     * Returns true si se autenció al usuario por cookie.
     */
    public static function check(UsuarioModel $model): bool
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!$cookie) return false;

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2) return false;

        [$userId, $token] = $parts;
        if (!ctype_digit($userId) || strlen($token) !== 64) return false;

        $tokenHash = hash('sha256', $token);
        $usuario   = $model->findByRememberToken((int)$userId, $tokenHash);

        if (!$usuario) {
            self::clearCookie();
            return false;
        }

        // Token rotation — nuevo token en cada uso
        $newToken     = bin2hex(random_bytes(32));
        $newTokenHash = hash('sha256', $newToken);
        $model->setRememberToken((int)$usuario['id'], $newTokenHash, self::DAYS);
        self::setCookie((int)$usuario['id'], $newToken);

        // Restaurar sesión
        Session::regenerate();
        Session::set('auth_user', [
            'id'              => $usuario['id'],
            'nombre'          => $usuario['nombre'],
            'email'           => $usuario['email'],
            'rol'             => $usuario['rol'],
            'rol_id'          => $usuario['rol_id'],
            'foto'            => $usuario['foto'],
            'pantalla_inicio' => $usuario['pantalla_inicio'],
        ]);
        Session::set('permisos', $model->getPermisosEfectivos((int)$usuario['id'], (int)$usuario['rol_id']));

        return true;
    }

    /** Genera token, lo almacena en DB y setea la cookie. */
    public static function set(UsuarioModel $model, int $userId): void
    {
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $model->setRememberToken($userId, $tokenHash, self::DAYS);
        self::setCookie($userId, $token);
    }

    /** Borra el token del usuario en DB y elimina la cookie. */
    public static function clear(UsuarioModel $model): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie) {
            [$uid] = explode(':', $cookie, 2) + [''];
            if (ctype_digit($uid)) {
                $model->clearRememberToken((int)$uid);
            }
        }
        self::clearCookie();
    }

    private static function setCookie(int $userId, string $token): void
    {
        setcookie(
            self::COOKIE_NAME,
            $userId . ':' . $token,
            ['expires' => time() + 86400 * self::DAYS, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']
        );
    }

    private static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}
