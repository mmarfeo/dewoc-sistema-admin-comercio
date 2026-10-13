<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Comprobantes adicionales de un pago (a proveedor, sueldo u otro) — pagos_proveedores.
 * comprobante_path (single, legado) sigue siendo el primero de la lista en la UI; esta tabla
 * guarda los siguientes. Genérica (no específica de Compras): un pago puede corresponder a
 * una factura de compra, un gasto, un sueldo, etc.
 */
class PagoProveedorAdjuntoModel extends Model
{
    protected string $table = 'pagos_proveedores_adjuntos';

    public function listar(int $pagoId): array
    {
        return $this->queryAll(
            'SELECT * FROM pagos_proveedores_adjuntos WHERE pago_id = ? ORDER BY created_at ASC',
            [$pagoId]
        );
    }

    public function crear(int $pagoId, string $archivo, int $usuarioId): int
    {
        return $this->insert([
            'pago_id'    => $pagoId,
            'archivo'    => $archivo,
            'usuario_id' => $usuarioId,
        ]);
    }

    public function eliminar(int $id): void
    {
        $this->db->prepare('DELETE FROM pagos_proveedores_adjuntos WHERE id = ?')->execute([$id]);
    }
}
