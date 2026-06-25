<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ProveedorModel extends Model
{
    protected string $table = 'proveedores';

    public function findAllActivos(): array
    {
        return $this->queryAll(
            'SELECT * FROM proveedores WHERE eliminado = 0 ORDER BY razon_social'
        );
    }

    public function tieneCompras(int $id): bool
    {
        return (bool)$this->queryScalar(
            'SELECT COUNT(*) FROM facturas_compra WHERE proveedor_id = ?',
            [$id]
        );
    }

    public function validarCuit(string $cuit): bool
    {
        // CUIT argentino: XX-XXXXXXXX-X
        return (bool)preg_match('/^\d{2}-\d{8}-\d{1}$/', $cuit);
    }
}
