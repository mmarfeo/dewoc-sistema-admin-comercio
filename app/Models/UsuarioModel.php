<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';

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

    /** Usuarios activos, para selectores simples (ej. "asignar tarea a" en Tareas asignadas). */
    public function findActivos(): array
    {
        return $this->queryAll(
            'SELECT id, nombre FROM usuarios WHERE eliminado = 0 AND activo = 1 ORDER BY nombre'
        );
    }

    /** Admins activos con el flag ve_tareas_generales tildado — a quiénes avisar/mostrar una tarea asignada a "Administradores". */
    public function findConVeTareasGenerales(): array
    {
        return $this->queryAll(
            "SELECT u.id, u.nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.eliminado = 0 AND u.activo = 1 AND r.nombre = 'admin' AND u.ve_tareas_generales = 1
             ORDER BY u.nombre"
        );
    }

    /**
     * Usuarios activos que efectivamente pueden $accion un $modulo — admins siempre,
     * más cualquier otro rol con ese permiso habilitado (por rol o por excepción
     * personal). Misma lógica que PushSubscriptionModel::findSuscripcionesConPermiso(),
     * pero devolviendo usuarios en vez de suscripciones push (ver docs/tareas-pendientes.md
     * #3 — hace falta la lista de gente, no solo de quién tiene push activado).
     */
    public function findConPermiso(string $modulo, string $accion = 'ver'): array
    {
        $candidatos = $this->queryAll(
            "SELECT u.id, u.nombre, u.rol_id, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.activo = 1 AND u.eliminado = 0"
        );

        $resultado = [];
        foreach ($candidatos as $u) {
            if ($u['rol_nombre'] === 'admin') {
                $resultado[] = $u;
                continue;
            }
            $permisos = $this->getPermisosEfectivos((int)$u['id'], (int)$u['rol_id']);
            if (!empty($permisos[$modulo][$accion])) $resultado[] = $u;
        }
        return $resultado;
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

    /**
     * Si el Chat IA todavía no le avisó HOY al usuario sobre productos de reventa
     * incompletos (ver ProductoModel::findIncompletos()) — true también la primera vez
     * (columna en NULL). Se consulta la BD en frío, no Session::user(), porque la sesión
     * puede venir de un login de días atrás y no reflejar el último aviso.
     */
    public function debeRecordarProductosIncompletos(int $id): bool
    {
        $fecha = $this->queryScalar('SELECT recordatorio_productos_fecha FROM usuarios WHERE id = ?', [$id]);
        return $fecha !== date('Y-m-d');
    }

    /** Marca que el Chat IA ya le avisó hoy al usuario sobre productos incompletos — no vuelve a insistir hasta mañana. */
    public function marcarRecordatorioProductosHoy(int $id): void
    {
        $this->db->prepare('UPDATE usuarios SET recordatorio_productos_fecha = CURDATE() WHERE id = ?')->execute([$id]);
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

    /**
     * Excepciones de permisos propias de un usuario (sobre las de su plantilla/rol) — las
     * ya vencidas (vence_en) se ignoran acá directo, así ningún llamador tiene que acordarse
     * de filtrarlas: en el momento en que se lee, siempre da el estado real. La fila vencida
     * no se borra sola (queda para que el admin la vea/limpie desde la UI, no se pierde el
     * historial de qué se le dio a quién). Esta función corre en cada login — el try/catch
     * es el mismo criterio de tolerancia a deploy-no-atómico que getPermisosEfectivos(): si
     * `vence_en` todavía no existe (migración no corrida), cae a la consulta vieja sin filtro.
     */
    public function getPermisosUsuario(int $usuarioId): array
    {
        try {
            $rows = $this->queryAll(
                'SELECT modulo, ver, editar FROM permisos_usuario
                 WHERE usuario_id = ? AND (vence_en IS NULL OR vence_en > NOW())',
                [$usuarioId]
            );
        } catch (\Throwable $e) {
            $rows = $this->queryAll('SELECT modulo, ver, editar FROM permisos_usuario WHERE usuario_id = ?', [$usuarioId]);
        }
        $permisos = [];
        foreach ($rows as $row) {
            $permisos[$row['modulo']] = ['ver' => (bool)$row['ver'], 'editar' => (bool)$row['editar']];
        }
        return $permisos;
    }

    /** TODAS las excepciones del usuario, vencidas o no (para listarlas en usuarios/form.php,
     *  con su vence_en crudo para mostrar el badge) — a diferencia de getPermisosUsuario(),
     *  que es la que se usa para RESOLVER permisos reales y por eso sí filtra las vencidas. */
    public function getPermisosUsuarioTodas(int $usuarioId): array
    {
        return $this->queryAll(
            'SELECT modulo, ver, editar, vence_en FROM permisos_usuario WHERE usuario_id = ? ORDER BY modulo',
            [$usuarioId]
        );
    }

    public function tienePermisosPersonalizados(int $usuarioId): bool
    {
        return (bool)$this->queryScalar(
            'SELECT COUNT(*) FROM permisos_usuario WHERE usuario_id = ?',
            [$usuarioId]
        );
    }

    /**
     * Agrega o actualiza UNA excepción puntual (upsert, no toca las demás) — a diferencia de
     * la versión vieja "todo o nada", esto deja agregar/editar un módulo sin tener que
     * reenviar los ~35 de la matriz completa. $vence: fecha 'Y-m-d H:i:s' o null (permanente).
     */
    public function guardarPermisoUsuario(int $usuarioId, string $modulo, bool $ver, bool $editar, ?string $vence): void
    {
        if (!array_key_exists($modulo, PermisosModel::MODULOS)) return;
        $this->db->prepare(
            'INSERT INTO permisos_usuario (usuario_id, modulo, ver, editar, vence_en)
             VALUES (:uid, :mod, :ver, :edit, :vence)
             ON DUPLICATE KEY UPDATE ver = :ver2, editar = :edit2, vence_en = :vence2'
        )->execute([
            ':uid' => $usuarioId, ':mod' => $modulo,
            ':ver' => (int)$ver, ':edit' => (int)$editar, ':vence' => $vence,
            ':ver2' => (int)$ver, ':edit2' => (int)$editar, ':vence2' => $vence,
        ]);
    }

    /** Saca una excepción puntual — el módulo vuelve a heredar de la plantilla/rol. */
    public function quitarPermisoUsuario(int $usuarioId, string $modulo): void
    {
        $this->db->prepare('DELETE FROM permisos_usuario WHERE usuario_id = ? AND modulo = ?')
            ->execute([$usuarioId, $modulo]);
    }

    /** Asigna (o quita, con null) la plantilla puntual de UN usuario — manda por sobre la
     *  plantilla por defecto de su rol. Ver RolModel/roles.plantilla_permisos_id_default. */
    public function asignarPlantilla(int $usuarioId, ?int $plantillaId): void
    {
        $this->update($usuarioId, ['plantilla_permisos_id' => $plantillaId]);
    }

    /**
     * Permisos efectivos de un usuario, resolviendo la jerarquía de 3 niveles (ver
     * database/plantillas_permisos_migration.sql):
     *   1. Plantilla puntual del usuario (usuarios.plantilla_permisos_id), si tiene.
     *   2. Si no, la plantilla por defecto de su Rol (roles.plantilla_permisos_id_default).
     *   3. Si el rol tampoco tiene plantilla default (no debería pasar después de correr la
     *      migración), la matriz vieja por rol_id (tabla `permisos`) como red de seguridad.
     * Sobre el resultado de eso se superponen las excepciones propias del usuario
     * (getPermisosUsuario(), ya sin las vencidas). Si ni plantilla ni rol tienen NADA
     * configurado todavía, la base es "acceso total" (mismo criterio de retrocompatibilidad
     * que ya usaba can()), para que agregar una excepción puntual no termine bloqueando el
     * resto de los módulos por accidente.
     *
     * IMPORTANTE — tolerante a que la migración de plantillas_permisos_migration.sql todavía
     * no se haya corrido en el servidor: esta función corre en CADA login (ver
     * AuthController::iniciarSesion(), RememberMe::check()), así que si el código se despliega
     * antes que el SQL (orden de deploy no atómico en este proyecto — ya pasó otras veces),
     * un error acá tumbaría el login de todo el mundo hasta correr la migración. El try/catch
     * hace que, mientras las columnas nuevas no existan, se comporte EXACTAMENTE como antes
     * (matriz vieja por rol_id) — mismo criterio de "nunca debe poder tirar abajo nada" que ya
     * usa Controller::registrarUsoAnalitica().
     */
    public function getPermisosEfectivos(int $usuarioId, int $rolId): array
    {
        $plantillaId = null;
        try {
            $plantillaId = $this->queryScalar('SELECT plantilla_permisos_id FROM usuarios WHERE id = ?', [$usuarioId]);
            if (!$plantillaId) {
                $plantillaId = $this->queryScalar('SELECT plantilla_permisos_id_default FROM roles WHERE id = ?', [$rolId]);
            }
        } catch (\Throwable $e) {
            $plantillaId = null; // migración todavía no corrida — cae al comportamiento viejo
        }

        if ($plantillaId) {
            require_once BASE_PATH . '/app/Models/PlantillaPermisosModel.php';
            $permisosRol = (new PlantillaPermisosModel())->getMatrix((int)$plantillaId);
        } else {
            $permisosRol = $this->getPermisos($rolId);
        }

        if (empty($permisosRol)) {
            $permisosRol = array_fill_keys(
                array_keys(PermisosModel::MODULOS),
                ['ver' => true, 'editar' => true]
            );
        }

        $permisosUsuario = $this->getPermisosUsuario($usuarioId);
        return array_merge($permisosRol, $permisosUsuario);
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
