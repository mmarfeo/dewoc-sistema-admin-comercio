<?php
require_once BASE_PATH . '/app/Models/Model.php';

class CategoriaModel extends Model
{
    protected string $table = 'categorias';

    public function findAllConPadre(?string $tipo = null): array
    {
        $where  = 'c.eliminado = 0';
        $params = [];
        if ($tipo) {
            $where   .= ' AND c.tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        return $this->queryAll(
            "SELECT c.*, p.nombre AS padre_nombre
             FROM categorias c
             LEFT JOIN categorias p ON c.padre_id = p.id
             WHERE {$where}
             ORDER BY c.tipo, p.nombre, c.nombre",
            $params
        );
    }

    public function findPadres(?string $tipo = null): array
    {
        $where  = 'eliminado = 0 AND padre_id IS NULL';
        $params = [];
        if ($tipo) {
            $where   .= ' AND tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        return $this->queryAll("SELECT * FROM categorias WHERE {$where} ORDER BY nombre", $params);
    }

    public function tieneHijos(int $id): bool
    {
        return (bool)$this->queryScalar('SELECT COUNT(*) FROM categorias WHERE padre_id = ? AND eliminado = 0', [$id]);
    }

    public function tieneProductos(int $id): bool
    {
        $enProductos = (int)$this->queryScalar('SELECT COUNT(*) FROM productos WHERE categoria_id = ? AND eliminado = 0', [$id]);
        $enMPs       = (int)$this->queryScalar('SELECT COUNT(*) FROM materias_primas WHERE categoria_id = ? AND eliminado = 0', [$id]);
        $enInsumos   = (int)$this->queryScalar('SELECT COUNT(*) FROM insumos WHERE categoria_id = ? AND eliminado = 0', [$id]);
        return ($enProductos + $enMPs + $enInsumos) > 0;
    }
}
