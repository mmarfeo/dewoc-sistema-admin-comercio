<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';

/**
 * Plantillas de permisos reutilizables ("Permission Sets" estilo Salesforce) — ver
 * docs de la conversación que armó esto y database/plantillas_permisos_migration.sql.
 * Desacopla el conjunto de permisos del Rol: una plantilla se puede asignar como
 * default de un Rol (RolModel/UsuarioModel la resuelven ahí) y/o puntualmente a un
 * usuario individual (usuarios.plantilla_permisos_id, manda por sobre la del rol).
 * Comparte la lista de módulos con el sistema viejo por rol — ver PermisosModel::MODULOS.
 */
class PlantillaPermisosModel extends Model
{
    protected string $table = 'plantillas_permisos';

    public function listar(): array
    {
        return $this->queryAll(
            "SELECT pl.*,
                    (SELECT COUNT(*) FROM roles r WHERE r.plantilla_permisos_id_default = pl.id) AS roles_default,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.plantilla_permisos_id = pl.id AND u.eliminado = 0) AS usuarios_asignados
             FROM plantillas_permisos pl
             ORDER BY pl.nombre"
        );
    }

    /** Para selects (asignar a un rol o a un usuario) — solo las activas. */
    public function listarActivas(): array
    {
        return $this->queryAll("SELECT id, nombre FROM plantillas_permisos WHERE activo = 1 ORDER BY nombre");
    }

    public function findByIdConMatriz(int $id): ?array
    {
        $plantilla = $this->queryOne("SELECT * FROM plantillas_permisos WHERE id = :id", [':id' => $id]);
        if (!$plantilla) return null;
        $plantilla['matriz'] = $this->getMatrix($id);
        return $plantilla;
    }

    /** Matriz [modulo => ['ver'=>bool,'editar'=>bool]] de UNA plantilla puntual — usada por
     *  UsuarioModel::getPermisosEfectivos() para resolver los permisos de sesión. */
    public function getMatrix(int $plantillaId): array
    {
        $matrix = [];
        foreach ($this->queryAll(
            "SELECT modulo, ver, editar FROM plantilla_permisos_detalle WHERE plantilla_id = ?",
            [$plantillaId]
        ) as $row) {
            $matrix[$row['modulo']] = ['ver' => (bool)$row['ver'], 'editar' => (bool)$row['editar']];
        }
        return $matrix;
    }

    public function crear(string $nombre, ?string $descripcion): int
    {
        return $this->insert(['nombre' => trim($nombre), 'descripcion' => $descripcion ?: null]);
    }

    public function actualizarCabecera(int $id, string $nombre, ?string $descripcion): void
    {
        $this->update($id, ['nombre' => trim($nombre), 'descripcion' => $descripcion ?: null]);
    }

    /** Reemplaza toda la matriz de la plantilla — mismo patrón que PermisosModel::saveAll(),
     *  pero para una sola plantilla en vez de todos los roles a la vez. */
    public function guardarMatriz(int $plantillaId, array $matriz): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM plantilla_permisos_detalle WHERE plantilla_id = ?')->execute([$plantillaId]);
            $stmt = $this->db->prepare(
                'INSERT INTO plantilla_permisos_detalle (plantilla_id, modulo, ver, editar) VALUES (?, ?, ?, ?)'
            );
            foreach ($matriz as $modulo => $flags) {
                if (!array_key_exists($modulo, PermisosModel::MODULOS)) continue;
                $stmt->execute([$plantillaId, $modulo, (int)!empty($flags['ver']), (int)!empty($flags['editar'])]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function desactivar(int $id): void { $this->update($id, ['activo' => 0]); }
    public function activar(int $id): void    { $this->update($id, ['activo' => 1]); }

    /** true si algún rol o usuario todavía depende de esta plantilla — para avisar antes de desactivar. */
    public function estaEnUso(int $id): bool
    {
        return (bool)$this->queryScalar(
            "SELECT (SELECT COUNT(*) FROM roles WHERE plantilla_permisos_id_default = ?)
                   + (SELECT COUNT(*) FROM usuarios WHERE plantilla_permisos_id = ? AND eliminado = 0)",
            [$id, $id]
        );
    }
}
