<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Grupos de opciones/adicionales reutilizables entre productos (ej. "Gustos de helado",
 * "Torta de la promo") — ver docs/tareas-pendientes.md #1. Cada grupo tiene una lista de
 * opciones (tabla `opciones`); el límite de cuántas se pueden elegir vive en la tabla
 * puente `producto_grupos_opciones` (ProductoModel), no acá, porque el mismo grupo se
 * reutiliza entre productos con límites distintos.
 */
class GrupoOpcionModel extends Model
{
    protected string $table = 'grupos_opciones';

    public function listar(): array
    {
        return $this->queryAll(
            "SELECT g.*,
                    (SELECT COUNT(*) FROM opciones o WHERE o.grupo_id = g.id AND o.activo = 1) AS total_opciones,
                    (SELECT COUNT(*) FROM producto_grupos_opciones pgo WHERE pgo.grupo_id = g.id) AS total_productos
             FROM grupos_opciones g
             ORDER BY g.nombre"
        );
    }

    public function findByIdConOpciones(int $id): ?array
    {
        $grupo = $this->queryOne("SELECT * FROM grupos_opciones WHERE id = :id", [':id' => $id]);
        if (!$grupo) return null;

        $grupo['opciones'] = $this->queryAll(
            "SELECT o.*, mp.nombre AS materia_prima_nombre, ins.nombre AS insumo_nombre, p.nombre AS producto_nombre,
                    mp.stock_actual AS mp_stock_actual, mp.stock_minimo AS mp_stock_minimo,
                    ins.stock_actual AS ins_stock_actual, ins.stock_minimo AS ins_stock_minimo,
                    p.stock_actual  AS prod_stock_actual, p.stock_minimo  AS prod_stock_minimo,
                    p.controla_stock AS prod_controla_stock,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_p.abreviatura) AS unidad_abrev
             FROM opciones o
             LEFT JOIN materias_primas mp     ON mp.id  = o.materia_prima_id
             LEFT JOIN insumos ins            ON ins.id = o.insumo_id
             LEFT JOIN productos p            ON p.id   = o.producto_id
             LEFT JOIN unidades_medida um_mp  ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_p   ON um_p.id   = p.unidad_medida_id
             WHERE o.grupo_id = :id
             ORDER BY o.orden, o.id",
            [':id' => $id]
        );
        return $grupo;
    }

    /**
     * Crea el grupo y sus opciones en una sola transacción.
     * $opciones: [['nombre'=>, 'materia_prima_id'=>?, 'insumo_id'=>?, 'cantidad_consumida'=>, 'orden'=>], ...]
     */
    public function guardar(array $cabecera, array $opciones): int
    {
        $this->db->beginTransaction();
        try {
            $id = $this->insert($cabecera);
            $this->insertarOpciones($id, $opciones);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizarConOpciones(int $id, array $cabecera, array $opciones): void
    {
        $this->db->beginTransaction();
        try {
            $this->update($id, $cabecera);
            $this->db->prepare("DELETE FROM opciones WHERE grupo_id = :id")->execute([':id' => $id]);
            $this->insertarOpciones($id, $opciones);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function insertarOpciones(int $grupoId, array $opciones): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO opciones (grupo_id, nombre, materia_prima_id, insumo_id, producto_id, cantidad_consumida, orden, activo)
             VALUES (:grupo_id, :nombre, :mp, :ins, :prod, :cant, :orden, 1)"
        );
        foreach ($opciones as $i => $op) {
            $nombre = trim((string)($op['nombre'] ?? ''));
            if ($nombre === '') continue;
            $stmt->execute([
                ':grupo_id' => $grupoId,
                ':nombre'   => $nombre,
                ':mp'       => !empty($op['materia_prima_id']) ? (int)$op['materia_prima_id'] : null,
                ':ins'      => !empty($op['insumo_id']) ? (int)$op['insumo_id'] : null,
                ':prod'     => !empty($op['producto_id']) ? (int)$op['producto_id'] : null,
                ':cant'     => (float)($op['cantidad_consumida'] ?? 0),
                ':orden'    => (int)($op['orden'] ?? $i),
            ]);
        }
    }

    /** No se borra en duro: tiene ventas históricas que la referencian (ventas_detalle_opciones). */
    public function desactivar(int $id): void
    {
        $this->update($id, ['activo' => 0]);
    }

    public function activar(int $id): void
    {
        $this->update($id, ['activo' => 1]);
    }

    /** Grupos activos, para el selector de "asignar a producto". */
    public function listarActivos(): array
    {
        return $this->queryAll("SELECT id, nombre, tipo_seleccion FROM grupos_opciones WHERE activo = 1 ORDER BY nombre");
    }
}
