<?php
require_once BASE_PATH . '/app/Models/Model.php';

class InsumoModel extends Model
{
    protected string $table = 'insumos';

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            'SELECT i.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad
             FROM insumos i
             LEFT JOIN categorias c      ON i.categoria_id     = c.id
             LEFT JOIN unidades_medida u ON i.unidad_medida_id = u.id
             WHERE i.eliminado = 0
             ORDER BY i.nombre'
        );
    }

    public function actualizarStock(int $id, float $delta): void
    {
        $this->db->prepare(
            'UPDATE insumos SET stock_actual = stock_actual + ?, updated_at = NOW() WHERE id = ?'
        )->execute([$delta, $id]);
    }

    public function tieneUsoEnRecetas(int $id): bool
    {
        return (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM receta_detalle WHERE tipo_item = 'insumo' AND item_id = ?",
            [$id]
        );
    }
}
