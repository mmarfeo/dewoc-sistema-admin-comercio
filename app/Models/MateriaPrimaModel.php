<?php
require_once BASE_PATH . '/app/Models/Model.php';

class MateriaPrimaModel extends Model
{
    protected string $table = 'materias_primas';

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            'SELECT mp.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad, p.razon_social AS proveedor_nombre,
                    ig.nombre AS generico_nombre
             FROM materias_primas mp
             LEFT JOIN categorias c              ON mp.categoria_id      = c.id
             LEFT JOIN unidades_medida u         ON mp.unidad_medida_id  = u.id
             LEFT JOIN proveedores p             ON mp.proveedor_id      = p.id
             LEFT JOIN ingredientes_genericos ig ON mp.ingrediente_generico_id = ig.id
             WHERE mp.eliminado = 0
             ORDER BY mp.nombre'
        );
    }

    /** Busca una MP activa por nombre (sin distinguir mayúsculas ni espacios) — para detectar duplicados al importar */
    public function findByNombre(string $nombre): array|false
    {
        return $this->queryOne(
            "SELECT * FROM materias_primas WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
            [$nombre]
        );
    }

    public function actualizarStock(int $id, float $delta): void
    {
        $this->db->prepare(
            'UPDATE materias_primas SET stock_actual = stock_actual + ?, updated_at = NOW() WHERE id = ?'
        )->execute([$delta, $id]);
    }

    public function tieneUsoEnRecetas(int $id): bool
    {
        return (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM receta_detalle WHERE tipo_ingrediente = 'materia_prima' AND materia_prima_id = ?",
            [$id]
        );
    }

    public function historialPrecios(int $id): array
    {
        return $this->queryAll(
            "SELECT fc.id AS factura_id, fc.fecha, fc.tipo_comprobante, fc.nro_comprobante,
                    p.razon_social AS proveedor,
                    fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario, fcd.iva_porcentaje, fcd.subtotal_con_iva
             FROM facturas_compra_detalle fcd
             JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE fcd.tipo_item = 'materia_prima'
               AND fcd.materia_prima_id = :id
               AND fc.estado != 'anulada'
               AND fc.eliminado = 0
             ORDER BY fc.fecha DESC
             LIMIT 200",
            [':id' => $id]
        );
    }
}
