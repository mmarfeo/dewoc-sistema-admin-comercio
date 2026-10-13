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

    /**
     * Búsqueda para el combobox de proveedor (chat IA, etc.): por razón social o CUIT.
     * Con $q vacío devuelve los primeros $limite (para poder "navegar" la lista completa
     * al abrir el combo sin haber escrito nada, como un <select> normal).
     */
    public function buscar(string $q, int $limite = 20): array
    {
        $q = trim($q);

        if ($q === '') {
            return $this->queryAll(
                'SELECT id, razon_social, cuit FROM proveedores
                 WHERE eliminado = 0 ORDER BY razon_social LIMIT ' . $limite
            );
        }

        return $this->queryAll(
            'SELECT id, razon_social, cuit
             FROM proveedores
             WHERE eliminado = 0 AND (razon_social LIKE :q1 OR cuit LIKE :q2)
             ORDER BY razon_social
             LIMIT ' . $limite,
            [':q1' => '%' . $q . '%', ':q2' => '%' . $q . '%']
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
        // Acepta XX-XXXXXXXX-X o XXXXXXXXXXX (11 dígitos, con o sin guiones)
        return strlen(preg_replace('/[^0-9]/', '', $cuit)) === 11;
    }

    public function normalizarCuit(string $cuit): string
    {
        $d = preg_replace('/[^0-9]/', '', $cuit);
        return strlen($d) === 11
            ? substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10, 1)
            : $cuit;
    }

    // ── Identidades (alias, CBU, nombres alternativos) ──────────────────────────

    public function getIdentidades(int $proveedorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM proveedor_identidades WHERE proveedor_id = ? AND eliminado = 0 ORDER BY id"
        );
        $stmt->execute([$proveedorId]);
        return $stmt->fetchAll();
    }

    public function agregarIdentidad(int $proveedorId, string $tipo, string $valor, ?string $descripcion): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO proveedor_identidades (proveedor_id, tipo, valor, descripcion)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$proveedorId, $tipo, $valor, $descripcion]);
        return (int) $this->db->lastInsertId();
    }

    public function eliminarIdentidad(int $id): void
    {
        $this->db->prepare("UPDATE proveedor_identidades SET eliminado = 1 WHERE id = ?")
                 ->execute([$id]);
    }

    /**
     * Busca un proveedor por valor de identidad (alias MP, CBU, nombre alternativo, CUIT).
     * Primero intenta match exacto, luego parcial.
     */
    public function buscarPorIdentidad(string $valor): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.razon_social, pi.tipo, pi.valor AS identidad_valor, pi.descripcion
             FROM proveedor_identidades pi
             JOIN proveedores p ON p.id = pi.proveedor_id
             WHERE pi.eliminado = 0 AND p.eliminado = 0
               AND (LOWER(pi.valor) = LOWER(?) OR LOWER(pi.valor) LIKE LOWER(?))
             ORDER BY CASE WHEN LOWER(pi.valor) = LOWER(?) THEN 0 ELSE 1 END
             LIMIT 1"
        );
        $stmt->execute([$valor, '%' . $valor . '%', $valor]);
        return $stmt->fetch();
    }

    /**
     * Ítems que el Chat IA aprendió a asociar a este proveedor (nombre_ticket → item del
     * sistema), para poder revisarlos/corregirlos desde la ficha del proveedor — ver
     * ChatController::buscarAlias()/guardarAlias().
     */
    public function getAliasItems(int $proveedorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pa.id, pa.nombre_ticket, pa.tipo_item, pa.item_id,
                    COALESCE(mp.nombre, ins.nombre, prod.nombre) AS nombre_sistema,
                    pa.updated_at
             FROM proveedor_item_alias pa
             LEFT JOIN materias_primas mp  ON mp.id  = pa.item_id AND pa.tipo_item = 'materia_prima'
             LEFT JOIN insumos ins         ON ins.id = pa.item_id AND pa.tipo_item = 'insumo'
             LEFT JOIN productos prod      ON prod.id = pa.item_id AND pa.tipo_item = 'producto'
             WHERE pa.proveedor_id = ?
             ORDER BY pa.updated_at DESC"
        );
        $stmt->execute([$proveedorId]);
        return $stmt->fetchAll();
    }

    public function eliminarAliasItem(int $id): void
    {
        $this->db->prepare("DELETE FROM proveedor_item_alias WHERE id = ?")->execute([$id]);
    }

    /**
     * Busca un alias exacto (proveedor + texto tal como se tipeó) — usado por el
     * buscador de ítems de Compras (carga manual) para sugerir primero lo que ya se
     * usó antes con este proveedor. Mismo criterio que ChatController::buscarAlias(),
     * pero público/reusable para no depender del chat.
     */
    public function buscarAliasExacto(int $proveedorId, string $nombreTicket): ?array
    {
        $key = mb_strtolower(trim($nombreTicket), 'UTF-8');
        if ($key === '') return null;

        $stmt = $this->db->prepare(
            "SELECT pa.tipo_item, pa.item_id
             FROM proveedor_item_alias pa
             WHERE pa.proveedor_id = ? AND pa.nombre_ticket = ?"
        );
        $stmt->execute([$proveedorId, $key]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Memoriza (o actualiza) a qué ítem del sistema corresponde un texto de proveedor
     * puntual, para que la próxima vez se sugiera solo — usado tanto por el Chat IA
     * como por el buscador de ítems de Compras al guardar una factura cargada a mano.
     */
    public function guardarAlias(int $proveedorId, string $nombreTicket, string $tipoItem, int $itemId): void
    {
        $key = mb_strtolower(trim($nombreTicket), 'UTF-8');
        if ($key === '' || !$itemId) return;

        $this->db->prepare(
            "INSERT INTO proveedor_item_alias (proveedor_id, nombre_ticket, tipo_item, item_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE tipo_item = VALUES(tipo_item), item_id = VALUES(item_id), updated_at = NOW()"
        )->execute([$proveedorId, $key, $tipoItem, $itemId]);
    }

    public function reasignarAliasItem(int $id, string $tipoItem, int $itemId): void
    {
        $this->db->prepare(
            "UPDATE proveedor_item_alias SET tipo_item = ?, item_id = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$tipoItem, $itemId, $id]);
    }
}
