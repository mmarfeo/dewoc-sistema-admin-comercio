<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Bandeja de notificaciones genérica (ver docs/tareas-pendientes.md #3) — le da uso real a
 * la campanita del header. No es específica de reportes: cualquier aviso in-app futuro
 * puede reutilizar esta misma tabla, solo cambia el campo `tipo`.
 */
class NotificacionModel extends Model
{
    protected string $table = 'notificaciones';

    public function crear(int $usuarioId, string $titulo, ?string $cuerpo, ?string $url, string $tipo = 'general'): int
    {
        return $this->insert([
            'usuario_id' => $usuarioId,
            'titulo'     => $titulo,
            'cuerpo'     => $cuerpo,
            'url'        => $url,
            'tipo'       => $tipo,
        ]);
    }

    /**
     * Crea una fila en la bandeja para cada usuario_id ÚNICO de una lista de suscripciones
     * push (una misma persona puede tener varias, un celular y una notebook). Pensado para
     * llamarse justo al lado de cada WebPush::enviar() disparado por push_subscriptions, así
     * la campanita del header termina mostrando exactamente los mismos avisos que ya llegan
     * por push — antes de esto, cada función de aviso mandaba el push pero nunca guardaba
     * nada acá, así que la campanita solo mostraba los reportes generados por cron.
     */
    public function crearParaSuscripciones(array $suscripciones, string $titulo, ?string $cuerpo, ?string $url, string $tipo = 'general'): void
    {
        $yaCreados = [];
        foreach ($suscripciones as $sub) {
            $uid = (int)($sub['usuario_id'] ?? 0);
            if (!$uid || isset($yaCreados[$uid])) continue;
            $yaCreados[$uid] = true;
            $this->crear($uid, $titulo, $cuerpo, $url, $tipo);
        }
    }

    /** Para el dropdown de la campanita: las más recientes, leídas o no. */
    public function recientes(int $usuarioId, int $limite = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notificaciones WHERE usuario_id = :uid ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        return (int)$this->queryScalar(
            'SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :uid AND leido = 0',
            [':uid' => $usuarioId]
        );
    }

    /** Se marca de a una (no hay "marcar todo como leído"), ver docs/tareas-pendientes.md #3. */
    public function marcarLeida(int $id, int $usuarioId): void
    {
        $this->db->prepare(
            'UPDATE notificaciones SET leido = 1, leido_at = NOW() WHERE id = :id AND usuario_id = :uid'
        )->execute([':id' => $id, ':uid' => $usuarioId]);
    }
}
