<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Comprobantes adicionales de una factura de compra (ej. una factura de varias hojas) —
 * facturas_compra.comprobante_archivo (single, legado) sigue siendo el primero de la lista
 * en la UI; esta tabla guarda los siguientes. Ver docs/adjuntos-multiples-compras.
 */
class FacturaCompraAdjuntoModel extends Model
{
    protected string $table = 'facturas_compra_adjuntos';

    public function listar(int $facturaId): array
    {
        return $this->queryAll(
            'SELECT * FROM facturas_compra_adjuntos WHERE factura_compra_id = ? ORDER BY created_at ASC',
            [$facturaId]
        );
    }

    public function crear(int $facturaId, string $archivo, int $usuarioId): int
    {
        return $this->insert([
            'factura_compra_id' => $facturaId,
            'archivo'           => $archivo,
            'usuario_id'        => $usuarioId,
        ]);
    }

    public function eliminar(int $id): void
    {
        $this->db->prepare('DELETE FROM facturas_compra_adjuntos WHERE id = ?')->execute([$id]);
    }
}
