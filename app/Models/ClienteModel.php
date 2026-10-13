<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ClienteModel extends Model
{
    protected string $table = 'clientes';

    public function listar(array $filtros = []): array
    {
        $where  = ['eliminado = 0'];
        $params = [];

        if (!empty($filtros['q'])) {
            // Un placeholder por aparición — :q repetido rompe con prepares reales (no
            // emulados) con "SQLSTATE[HY093]: Invalid parameter number".
            $where[] = '(nombre LIKE :q1 OR documento LIKE :q2 OR email LIKE :q3 OR telefono LIKE :q4)';
            $like    = '%' . $filtros['q'] . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $where[]          = 'activo = :activo';
            $params[':activo'] = (int)$filtros['activo'];
        }

        $sql = 'SELECT * FROM clientes WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre';
        return $this->queryAll($sql, $params);
    }

    public function buscar(string $q): array
    {
        // :q no se puede reusar dos veces en el mismo prepare — con prepares reales (no
        // emulados, como está este hosting) MySQL rechaza el parámetro repetido con
        // "SQLSTATE[HY093]: Invalid parameter number". Un placeholder por aparición.
        $like = '%' . $q . '%';
        return $this->queryAll(
            'SELECT id, nombre, documento, tipo_doc, condicion_iva, telefono, email
             FROM clientes WHERE eliminado = 0 AND activo = 1
             AND (nombre LIKE :q1 OR documento LIKE :q2)
             ORDER BY nombre LIMIT 15',
            [':q1' => $like, ':q2' => $like]
        );
    }

    public function tieneVentas(int $id): bool
    {
        $count = (int)$this->queryScalar(
            'SELECT COUNT(*) FROM ventas WHERE cliente_id = ? AND eliminado = 0',
            [$id]
        );
        return $count > 0;
    }

    public function historialVentas(int $id): array
    {
        return $this->queryAll(
            'SELECT v.id, v.created_at, v.total_con_iva, v.metodo_pago, v.estado
             FROM ventas v WHERE v.cliente_id = ? AND v.eliminado = 0
             ORDER BY v.created_at DESC LIMIT 50',
            [$id]
        );
    }

    /** Clientes dados de alta en un período — usado por el acceso directo "Clientes nuevos" del Chat IA */
    public function nuevosPorPeriodo(string $desde, string $hasta): array
    {
        return $this->queryAll(
            'SELECT id, nombre, created_at
             FROM clientes
             WHERE eliminado = 0 AND DATE(created_at) BETWEEN :desde AND :hasta
             ORDER BY created_at DESC',
            [':desde' => $desde, ':hasta' => $hasta]
        );
    }

    // ── Portal de Clientes Mayoristas ────────────────────────────────────────
    // Ver docs/portal-clientes-mayoristas-checklist.md — login separado del de `usuarios`.

    /** Valida credenciales de login del portal. Requiere portal_activo=1 (lo habilita el admin desde la ficha). */
    public function login(string $email, string $password): array|false
    {
        $cliente = $this->queryOne(
            'SELECT * FROM clientes WHERE email = ? AND eliminado = 0 AND activo = 1 AND portal_activo = 1 LIMIT 1',
            [$email]
        );
        if (!$cliente || !$cliente['password_hash'] || !password_verify($password, $cliente['password_hash'])) {
            return false;
        }
        return $cliente;
    }

    /** El admin habilita/deshabilita el acceso al portal y opcionalmente fija una contraseña nueva (mismo hash que usuarios: bcrypt cost 12). */
    public function guardarAccesoPortal(int $id, bool $activo, ?string $passwordPlano): void
    {
        if ($passwordPlano !== null && $passwordPlano !== '') {
            $this->db->prepare('UPDATE clientes SET portal_activo = ?, password_hash = ? WHERE id = ?')
                ->execute([(int)$activo, password_hash($passwordPlano, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        } else {
            $this->db->prepare('UPDATE clientes SET portal_activo = ? WHERE id = ?')
                ->execute([(int)$activo, $id]);
        }
    }

    // ── Cambio / recuperación de contraseña del portal ──────────────────────
    // Mismo esquema de reset-token que UsuarioModel (ver AuthController::recuperar()).

    /** Busca un cliente con portal habilitado por email — usado por "olvidé mi contraseña". */
    public function findByEmailPortal(string $email): array|false
    {
        return $this->queryOne(
            'SELECT * FROM clientes WHERE email = ? AND eliminado = 0 AND activo = 1 AND portal_activo = 1 LIMIT 1',
            [$email]
        );
    }

    /** Guarda el hash del token de restablecimiento (válido 1 hora). */
    public function setResetToken(int $id, string $tokenHash): void
    {
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->db->prepare(
            'UPDATE clientes SET reset_token = ?, reset_expires = ? WHERE id = ?'
        )->execute([$tokenHash, $expira, $id]);
    }

    /** Busca un cliente con portal habilitado por el hash del reset token, verificando expiración. */
    public function findByResetToken(string $tokenHash): array|false
    {
        return $this->queryOne(
            'SELECT * FROM clientes WHERE reset_token = ? AND reset_expires > NOW() AND eliminado = 0 AND activo = 1 AND portal_activo = 1',
            [$tokenHash]
        );
    }

    /** Actualiza la contraseña y borra el reset token. */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare(
            'UPDATE clientes SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?'
        )->execute([$passwordHash, $id]);
    }

    /** Cambio de contraseña desde el portal estando logueado: valida la actual antes de pisarla. */
    public function cambiarPassword(int $id, string $actual, string $nueva): bool
    {
        $cliente = $this->queryOne('SELECT password_hash FROM clientes WHERE id = ?', [$id]);
        if (!$cliente || !$cliente['password_hash'] || !password_verify($actual, $cliente['password_hash'])) {
            return false;
        }
        $this->updatePassword($id, password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]));
        return true;
    }
}
