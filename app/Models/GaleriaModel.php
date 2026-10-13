<?php
require_once BASE_PATH . '/app/Models/Model.php';

class GaleriaModel extends Model
{
    protected string $table = 'galeria_archivos';

    public function findAllActivos(?string $tipo = null): array
    {
        $where  = 'eliminado = 0';
        $params = [];
        if ($tipo) {
            $where .= ' AND tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        return $this->queryAll(
            "SELECT * FROM galeria_archivos WHERE {$where} ORDER BY created_at DESC",
            $params
        );
    }
}
