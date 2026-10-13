<?php
require_once BASE_PATH . '/app/Models/Model.php';

class PushSubscriptionModel extends Model
{
    protected string $table = 'push_subscriptions';

    /** Alta o actualización (mismo endpoint = mismo dispositivo/navegador) */
    public function guardar(int $usuarioId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        $existente = $this->queryOne(
            "SELECT id FROM push_subscriptions WHERE endpoint = :endpoint",
            [':endpoint' => $endpoint]
        );

        if ($existente) {
            $this->update((int)$existente['id'], [
                'usuario_id' => $usuarioId,
                'p256dh'     => $p256dh,
                'auth'       => $auth,
                'user_agent' => $userAgent,
            ]);
            return;
        }

        $this->insert([
            'usuario_id' => $usuarioId,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'user_agent' => $userAgent,
        ]);
    }

    public function eliminarPorEndpoint(string $endpoint): void
    {
        $this->query("DELETE FROM push_subscriptions WHERE endpoint = :endpoint", [':endpoint' => $endpoint]);
    }

    /**
     * Alta/actualización de suscripción de un CLIENTE del Portal de Clientes Mayoristas
     * (ver docs/portal-clientes-mayoristas-checklist.md) — misma tabla que usa el staff,
     * distinguida por `cliente_id` en vez de `usuario_id` (uno de los dos siempre NULL).
     */
    public function guardarCliente(int $clienteId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        $existente = $this->queryOne("SELECT id FROM push_subscriptions WHERE endpoint = :endpoint", [':endpoint' => $endpoint]);
        if ($existente) {
            $this->update((int) $existente['id'], ['cliente_id' => $clienteId, 'usuario_id' => null, 'p256dh' => $p256dh, 'auth' => $auth, 'user_agent' => $userAgent]);
            return;
        }
        $this->insert(['cliente_id' => $clienteId, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth, 'user_agent' => $userAgent]);
    }

    /** Suscripciones de un cliente puntual del portal, para avisarle (ej. respuesta en el chat de un pedido). */
    public function findSuscripcionesCliente(int $clienteId): array
    {
        return $this->queryAll(
            "SELECT * FROM push_subscriptions WHERE cliente_id = :cid",
            [':cid' => $clienteId]
        );
    }

    /**
     * Suscripciones de STAFF que tiene un turno de caja abierto ahora mismo — usado para
     * avisar de un pedido nuevo del portal: "al primero que lo atienda" (decisión
     * confirmada) son todos los que estén con caja abierta en este momento, no uno puntual.
     */
    public function findSuscripcionesTurnosAbiertos(): array
    {
        return $this->queryAll(
            "SELECT DISTINCT ps.*
             FROM push_subscriptions ps
             JOIN turnos t ON t.usuario_id = ps.usuario_id AND t.estado = 'abierto'
             WHERE ps.usuario_id IS NOT NULL"
        );
    }

    public function eliminarPorId(int $id): void
    {
        $this->query("DELETE FROM push_subscriptions WHERE id = :id", [':id' => $id]);
    }

    /** Suscripciones de todos los administradores activos, para notificarles */
    public function findSuscripcionesAdmins(): array
    {
        return $this->queryAll(
            "SELECT ps.*
             FROM push_subscriptions ps
             JOIN usuarios u ON u.id = ps.usuario_id
             JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre = 'admin' AND u.activo = 1 AND u.eliminado = 0"
        );
    }

    /**
     * Suscripciones de usuarios activos que pueden ver/editar un módulo: admins siempre
     * (mismo criterio que can()/canView() en core/helpers.php), más cualquier otro rol al
     * que se le haya habilitado ese módulo puntual desde "Permisos personalizados" en su
     * ficha de usuario — a diferencia de findSuscripcionesAdmins(), no se limita al rol
     * admin, así un encargado con el módulo habilitado también recibe la notificación.
     */
    public function findSuscripcionesConPermiso(string $modulo, string $accion = 'ver'): array
    {
        require_once BASE_PATH . '/app/Models/UsuarioModel.php';
        $usuarioModel = new UsuarioModel();

        $candidatos = $this->queryAll(
            "SELECT ps.*, u.rol_id, r.nombre AS rol_nombre
             FROM push_subscriptions ps
             JOIN usuarios u ON u.id = ps.usuario_id
             JOIN roles r ON r.id = u.rol_id
             WHERE u.activo = 1 AND u.eliminado = 0"
        );

        $resultado = [];
        foreach ($candidatos as $sub) {
            if ($sub['rol_nombre'] === 'admin') {
                $resultado[] = $sub;
                continue;
            }
            $permisos = $usuarioModel->getPermisosEfectivos((int) $sub['usuario_id'], (int) $sub['rol_id']);
            if (!empty($permisos[$modulo][$accion])) $resultado[] = $sub;
        }
        return $resultado;
    }
}
