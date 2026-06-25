<?php
require_once BASE_PATH . '/app/Models/Model.php';

class PermisosModel extends Model
{
    protected string $table = 'permisos';

    const MODULOS = [
        'ventas'          => 'Ventas / POS',
        'caja'            => 'Caja y Turnos',
        'compras'         => 'Facturas de Compra',
        'proveedores'     => 'Proveedores',
        'stock'           => 'Control de Stock',
        'recetas'         => 'Recetas y Producción',
        'productos'       => 'Productos',
        'materias_primas' => 'Materias Primas',
        'insumos'         => 'Insumos',
        'categorias'      => 'Categorías',
        'clientes'        => 'Clientes',
        'reportes'        => 'Reportes',
        'ecommerce'       => 'Ecommerce',
        'chat'            => 'Chat IA',
    ];

    /** Non-admin roles */
    public function getRoles(): array
    {
        return $this->queryAll(
            "SELECT id, nombre, descripcion FROM roles WHERE nombre != 'admin' ORDER BY id"
        );
    }

    /** Permissions indexed as [rol_id][modulo] = ['ver' => bool, 'editar' => bool] */
    public function getMatrix(): array
    {
        $matrix = [];
        foreach ($this->queryAll("SELECT rol_id, modulo, ver, editar FROM permisos") as $row) {
            $matrix[$row['rol_id']][$row['modulo']] = [
                'ver'    => (bool)$row['ver'],
                'editar' => (bool)$row['editar'],
            ];
        }
        return $matrix;
    }

    /**
     * Replace full permission matrix for all non-admin roles.
     * $data: [rol_id => [modulo => ['ver' => 0|1, 'editar' => 0|1]]]
     */
    public function saveAll(array $data): void
    {
        $conn = Database::getInstance()->getConnection();
        $conn->beginTransaction();
        try {
            $conn->exec(
                "DELETE p FROM permisos p
                 JOIN roles r ON r.id = p.rol_id
                 WHERE r.nombre != 'admin'"
            );

            $stmt = $conn->prepare(
                "INSERT INTO permisos (rol_id, modulo, ver, editar) VALUES (?, ?, ?, ?)"
            );
            foreach ($data as $rolId => $modulos) {
                foreach ($modulos as $modulo => $flags) {
                    if (!array_key_exists($modulo, self::MODULOS)) continue;
                    $stmt->execute([
                        (int)$rolId,
                        $modulo,
                        (int)!empty($flags['ver']),
                        (int)!empty($flags['editar']),
                    ]);
                }
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
