<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ProductoModel extends Model
{
    protected string $table = 'productos';

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            'SELECT p.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
             WHERE p.eliminado = 0
             ORDER BY p.nombre'
        );
    }

    public function findByIdConDetalle(int $id): array|false
    {
        return $this->queryOne(
            'SELECT p.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
             WHERE p.id = ? AND p.eliminado = 0',
            [$id]
        );
    }

    public function tieneVentas(int $id): bool
    {
        return (bool)$this->queryScalar(
            'SELECT COUNT(*) FROM ventas_detalle WHERE producto_id = ?',
            [$id]
        );
    }

    public function actualizarStock(int $id, float $delta): void
    {
        $this->db->prepare(
            'UPDATE productos SET stock_actual = stock_actual + ?, updated_at = NOW() WHERE id = ?'
        )->execute([$delta, $id]);
    }
}
