<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ChatAccesoModel extends Model
{
    protected string $table = 'chat_accesos_directos';

    /** Catálogo completo (para la pantalla de administración) */
    public function listarCatalogo(): array
    {
        return $this->queryAll('SELECT * FROM chat_accesos_directos ORDER BY orden, label');
    }

    public function findById(int $id): array|false
    {
        return $this->queryOne('SELECT * FROM chat_accesos_directos WHERE id = ?', [$id]);
    }

    public function crear(array $data): int
    {
        return $this->insert($data);
    }

    public function actualizar(int $id, array $data): void
    {
        $this->update($id, $data);
    }

    public function eliminar(int $id): void
    {
        $this->query('DELETE FROM chat_accesos_directos WHERE id = ?', [$id]);
    }

    /** Accesos directos asignados a un usuario (solo los activos), para mostrar en el Chat */
    public function findAsignadosUsuario(int $usuarioId): array
    {
        return $this->queryAll(
            "SELECT a.id, a.label, a.texto, a.accion_interna
             FROM chat_accesos_usuario cau
             JOIN chat_accesos_directos a ON a.id = cau.acceso_id
             WHERE cau.usuario_id = :uid AND a.activo = 1
             ORDER BY a.orden, a.label",
            [':uid' => $usuarioId]
        );
    }

    /** IDs de accesos asignados a un usuario (incluyendo inactivos), para precargar el formulario de edición */
    public function idsAsignadosUsuario(int $usuarioId): array
    {
        $rows = $this->queryAll(
            'SELECT acceso_id FROM chat_accesos_usuario WHERE usuario_id = ?',
            [$usuarioId]
        );
        return array_map('intval', array_column($rows, 'acceso_id'));
    }

    /** Reemplaza la asignación de accesos directos de un usuario */
    public function guardarAsignacionUsuario(int $usuarioId, array $accesoIds): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM chat_accesos_usuario WHERE usuario_id = ?');
            $stmt->execute([$usuarioId]);

            if ($accesoIds) {
                $ins = $this->db->prepare(
                    'INSERT INTO chat_accesos_usuario (usuario_id, acceso_id) VALUES (?, ?)'
                );
                foreach (array_unique(array_map('intval', $accesoIds)) as $accesoId) {
                    $ins->execute([$usuarioId, $accesoId]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
