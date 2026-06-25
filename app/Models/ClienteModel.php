<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ClienteModel extends Model
{
    protected string $table = 'clientes';

    public function findAll(array $filtros = []): array
    {
        $where  = ['eliminado = 0'];
        $params = [];

        if (!empty($filtros['q'])) {
            $where[]      = '(nombre LIKE :q OR documento LIKE :q OR email LIKE :q OR telefono LIKE :q)';
            $params[':q'] = '%' . $filtros['q'] . '%';
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $where[]          = 'activo = :activo';
            $params[':activo'] = (int)$filtros['activo'];
        }

        $sql = 'SELECT * FROM clientes WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre';
        return $this->queryAll($sql, $params);
    }

    public function buscar(string $q): array
    {
        return $this->queryAll(
            'SELECT id, nombre, documento, tipo_doc, condicion_iva, telefono, email
             FROM clientes WHERE eliminado = 0 AND activo = 1
             AND (nombre LIKE :q OR documento LIKE :q)
             ORDER BY nombre LIMIT 15',
            [':q' => '%' . $q . '%']
        );
    }

    public function tieneVentas(int $id): bool
    {
        $count = (int)$this->queryScalar(
            'SELECT COUNT(*) FROM ventas WHERE cliente_id = ? AND eliminado = 0',
            [$id]
        );
        return $count > 0;
    }

    public function historialVentas(int $id): array
    {
        return $this->queryAll(
            'SELECT v.id, v.created_at, v.total_con_iva, v.metodo_pago, v.estado
             FROM ventas v WHERE v.cliente_id = ? AND v.eliminado = 0
             ORDER BY v.created_at DESC LIMIT 50',
            [$id]
        );
    }
}
