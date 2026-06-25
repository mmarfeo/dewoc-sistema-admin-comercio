<?php
require_once BASE_PATH . '/app/Models/Model.php';

class UsuarioModel extends Model
{
    protected string $table = 'usuarios';

    public function findByEmail(string $email): array|false
    {
        return $this->queryOne(
            'SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.email = ? AND u.eliminado = 0',
            [$email]
        );
    }

    public function findByIdConRol(int $id): array|false
    {
        return $this->queryOne(
            'SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?',
            [$id]
        );
    }

    public function findAllConRol(): array
    {
        return $this->queryAll(
            'SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.eliminado = 0 ORDER BY u.nombre'
        );
    }

    public function registrarIntentoFallido(int $id): void
    {
        $this->db->prepare(
            'UPDATE usuarios SET intentos_login = intentos_login + 1 WHERE id = ?'
        )->execute([$id]);

        $intentos = (int)$this->queryScalar('SELECT intentos_login FROM usuarios WHERE id = ?', [$id]);

        if ($intentos >= LOGIN_MAX_ATTEMPTS) {
            $hasta = date('Y-m-d H:i:s', strtotime('+' . LOGIN_LOCKOUT_MINUTES . ' minutes'));
            $this->db->prepare(
                'UPDATE usuarios SET bloqueado_hasta = ? WHERE id = ?'
            )->execute([$hasta, $id]);
        }
    }

    public function resetIntentos(int $id): void
    {
        $this->db->prepare(
            'UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?'
        )->execute([$id]);
    }

    public function estaBloquado(array $usuario): bool
    {
        if (empty($usuario['bloqueado_hasta'])) return false;
        return strtotime($usuario['bloqueado_hasta']) > time();
    }

    public function getPermisos(int $rolId): array
    {
        $rows = $this->queryAll(
            'SELECT modulo, ver, editar FROM permisos WHERE rol_id = ?',
            [$rolId]
        );
        $permisos = [];
        foreach ($rows as $row) {
            $permisos[$row['modulo']] = ['ver' => (bool)$row['ver'], 'editar' => (bool)$row['editar']];
        }
        return $permisos;
    }

    public function getRoles(): array
    {
        return $this->queryAll('SELECT * FROM roles ORDER BY nombre');
    }

    /** Guarda el hash del token de restablecimiento (válido 1 hora). */
    public function setResetToken(int $id, string $tokenHash): void
    {
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->db->prepare(
            'UPDATE usuarios SET reset_token = ?, reset_expires = ? WHERE id = ?'
        )->execute([$tokenHash, $expira, $id]);
    }

    /** Busca un usuario activo por el hash del reset token, verificando expiración. */
    public function findByResetToken(string $tokenHash): array|false
    {
        return $this->queryOne(
            'SELECT * FROM usuarios WHERE reset_token = ? AND reset_expires > NOW() AND eliminado = 0 AND activo = 1',
            [$tokenHash]
        );
    }

    /** Actualiza la contraseña y borra el reset token. */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare(
            'UPDATE usuarios SET password_hash = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?'
        )->execute([$passwordHash, $id]);
    }

    /** Guarda el hash SHA-256 del token de "Recordarme" y su fecha de expiración. */
    public function setRememberToken(int $id, string $tokenHash, int $dias = 30): void
    {
        $expira = date('Y-m-d H:i:s', strtotime("+{$dias} days"));
        $this->db->prepare(
            'UPDATE usuarios SET remember_token = ?, remember_expires = ? WHERE id = ?'
        )->execute([$tokenHash, $expira, $id]);
    }

    /** Busca un usuario activo por id, verifica el token y la expiración. */
    public function findByRememberToken(int $userId, string $tokenHash): array|false
    {
        return $this->queryOne(
            'SELECT u.*, r.nombre AS rol
             FROM usuarios u
             JOIN roles r ON u.rol_id = r.id
             WHERE u.id = :id
               AND u.eliminado = 0
               AND u.activo = 1
               AND u.remember_token = :tok
               AND u.remember_expires > NOW()',
            [':id' => $userId, ':tok' => $tokenHash]
        );
    }

    /** Borra el token "Recordarme" del usuario. */
    public function clearRememberToken(int $id): void
    {
        $this->db->prepare(
            'UPDATE usuarios SET remember_token = NULL, remember_expires = NULL WHERE id = ?'
        )->execute([$id]);
    }
}
