<?php
require_once BASE_PATH . '/app/Models/Model.php';

class IngresoModel extends Model
{
    protected string $table = 'ingresos_otros';

    public function findEnPeriodo(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT io.*, u.nombre AS usuario_nombre
             FROM ingresos_otros io
             LEFT JOIN usuarios u ON u.id = io.usuario_id
             WHERE io.eliminado = 0 AND io.fecha BETWEEN :d AND :h
             ORDER BY io.fecha DESC, io.id DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    public function totalEnPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto), 0) FROM ingresos_otros WHERE eliminado = 0 AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    public function agregar(string $fecha, string $descripcion, float $monto, ?string $notas, int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ingresos_otros (fecha, descripcion, monto, notas, usuario_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$fecha, $descripcion, $monto, $notas ?: null, $usuarioId]);
        return (int)$this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        return (bool)$this->db->prepare("UPDATE ingresos_otros SET eliminado = 1 WHERE id = ?")->execute([$id]);
    }
}
