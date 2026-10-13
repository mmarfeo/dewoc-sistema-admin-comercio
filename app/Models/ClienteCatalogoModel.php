<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Catálogo de productos habilitados por cliente (Portal de Clientes Mayoristas — ver
 * docs/portal-clientes-mayoristas-checklist.md). Un producto solo aparece en el portal de
 * un cliente si tiene una fila acá — no hay catálogo "genérico mayorista", es 100%
 * específico por cliente (decisión confirmada).
 */
class ClienteCatalogoModel extends Model
{
    protected string $table = 'clientes_catalogo';

    /**
     * Catálogo tal como lo ve el cliente en el portal — mismo shape de producto que ya usa
     * el POS (id, nombre, precio_venta, iva_porcentaje, stock_actual, controla_stock,
     * tiene_opciones) para poder reusar el mismo selector de opciones/gustos del lado cliente.
     */
    public function catalogoDeCliente(int $clienteId): array
    {
        $filas = $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo,
                    COALESCE(cc.precio_especial, p.precio_venta) AS precio_venta,
                    p.iva AS iva_porcentaje,
                    p.stock_actual, p.stock_minimo, p.controla_stock,
                    p.categoria_id, cat.nombre AS categoria_nombre,
                    (SELECT abreviatura FROM unidades_medida WHERE id = p.unidad_medida_id) AS unidad,
                    EXISTS(SELECT 1 FROM producto_grupos_opciones pgo WHERE pgo.producto_id = p.id) AS tiene_opciones
             FROM clientes_catalogo cc
             JOIN productos p ON p.id = cc.producto_id
             LEFT JOIN categorias cat ON cat.id = p.categoria_id
             WHERE cc.cliente_id = ? AND cc.activo = 1 AND p.eliminado = 0 AND p.activo = 1
             ORDER BY p.nombre",
            [$clienteId]
        );

        // `precio_final` es lo que el cliente realmente va a pagar: con la opción D9 en "no
        // incluyen IVA", `precio_venta` es el neto y el portal mostraba un precio más bajo
        // que el que terminaba facturando el POS. El portal muestra `precio_final`, pero
        // sigue mandando el pedido sin precio: el precio lo resuelve el servidor contra
        // este mismo catálogo (ver PortalController::enviarPedido()).
        require_once BASE_PATH . '/core/PrecioHelper.php';
        foreach ($filas as &$f) {
            $f['precio_final'] = PrecioHelper::precioFinal((float)$f['precio_venta'], (float)$f['iva_porcentaje']);
        }
        unset($f);

        return $filas;
    }

    /** Catálogo completo (incluye inactivos) para la pantalla de administración en la ficha del cliente. */
    public function listarParaAdmin(int $clienteId): array
    {
        return $this->queryAll(
            "SELECT cc.id, cc.producto_id, cc.precio_especial, cc.activo,
                    p.nombre, p.codigo, p.precio_venta
             FROM clientes_catalogo cc
             JOIN productos p ON p.id = cc.producto_id
             WHERE cc.cliente_id = ?
             ORDER BY p.nombre",
            [$clienteId]
        );
    }

    public function asignar(int $clienteId, int $productoId, ?float $precioEspecial): void
    {
        $this->db->prepare(
            'INSERT INTO clientes_catalogo (cliente_id, producto_id, precio_especial, activo)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE precio_especial = VALUES(precio_especial), activo = 1'
        )->execute([$clienteId, $productoId, $precioEspecial]);
    }

    public function quitar(int $clienteId, int $productoId): void
    {
        $this->db->prepare('DELETE FROM clientes_catalogo WHERE cliente_id = ? AND producto_id = ?')
            ->execute([$clienteId, $productoId]);
    }
}
