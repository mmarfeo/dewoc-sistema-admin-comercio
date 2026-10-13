<?php
require_once BASE_PATH . '/app/Models/Model.php';

class SocioModel extends Model
{
    protected string $table = 'socios';

    public function findAll(string $orderBy = ''): array
    {
        return $this->queryAll('SELECT * FROM socios WHERE eliminado = 0 ORDER BY ' . ($orderBy ?: 'nombre'));
    }

    public function findAllActivos(): array
    {
        return $this->queryAll('SELECT * FROM socios WHERE eliminado = 0 AND activo = 1 ORDER BY nombre');
    }

    public function tienePagos(int $id): bool
    {
        return (bool) $this->queryScalar(
            'SELECT COUNT(*) FROM pagos_proveedores WHERE socio_id = ? AND eliminado = 0',
            [$id]
        );
    }
}
