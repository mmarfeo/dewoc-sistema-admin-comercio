<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Ingrediente genérico (ej. "Jamón cocido", "Pote cuadrado x 1kg"): agrupa varias variantes
 * "del mismo ingrediente real" (distintas marcas/paquetes) bajo un mismo costo y unidad base,
 * para que las recetas no queden atadas a una marca puntual que puede dejar de comprarse.
 *
 * Las variantes pueden ser materias primas (mp) O insumos (ej. envases/packaging) — nunca
 * mezcladas bajo el mismo genérico (comparar el costo de un ingrediente con el de un envase
 * no tiene sentido), pero el genérico en sí no "sabe" de antemano cuál de los dos es: se
 * infiere de en qué tabla estén cargadas sus variantes. Todos los métodos reciben/devuelven
 * un $tipo ('materia_prima'|'insumo') para saber contra cuál tabla trabajar — mismo patrón
 * que ya usa el resto de la app para polimorfismo de ítem (opciones, receta_detalle, etc.).
 */
class IngredienteGenericoModel extends Model
{
    protected string $table = 'ingredientes_genericos';

    private const TABLAS = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos'];

    /** Nombre de tabla real para un $tipo, o null si no es uno de los dos válidos. */
    private function tabla(string $tipo): ?string
    {
        return self::TABLAS[$tipo] ?? null;
    }

    public function listar(): array
    {
        return $this->queryAll(
            "SELECT ig.*, um.abreviatura AS unidad, c.nombre AS categoria_nombre,
                    (
                        (SELECT COUNT(*) FROM materias_primas mp WHERE mp.ingrediente_generico_id = ig.id AND mp.eliminado = 0)
                        + (SELECT COUNT(*) FROM insumos ins WHERE ins.ingrediente_generico_id = ig.id AND ins.eliminado = 0)
                    ) AS variantes_count
             FROM ingredientes_genericos ig
             LEFT JOIN unidades_medida um ON um.id = ig.unidad_medida_id
             LEFT JOIN categorias c       ON c.id  = ig.categoria_id
             WHERE ig.eliminado = 0
             ORDER BY ig.nombre"
        );
    }

    public function crear(array $data): int
    {
        return $this->insert([
            'nombre'           => $data['nombre'],
            'unidad_medida_id' => (int) $data['unidad_medida_id'],
            'categoria_id'     => $data['categoria_id'] ?: null,
        ]);
    }

    /** No usa Model::update() porque esta tabla no tiene columna updated_at */
    public function actualizar(int $id, array $data): void
    {
        $this->query(
            "UPDATE ingredientes_genericos SET nombre = :nombre, categoria_id = :categoria_id WHERE id = :id",
            [':nombre' => $data['nombre'], ':categoria_id' => $data['categoria_id'] ?: null, ':id' => $id]
        );
    }

    public function eliminar(int $id): void
    {
        $this->query("UPDATE ingredientes_genericos SET eliminado = 1 WHERE id = ?", [$id]);
    }

    public function tieneVariantesAsignadas(int $id): bool
    {
        return (bool) $this->queryScalar(
            "SELECT
                (SELECT COUNT(*) FROM materias_primas WHERE ingrediente_generico_id = ? AND eliminado = 0)
                + (SELECT COUNT(*) FROM insumos        WHERE ingrediente_generico_id = ? AND eliminado = 0)",
            [$id, $id]
        );
    }

    /** Variantes (materias primas E insumos) que hoy cuelgan de este genérico, cada una con su 'tipo'. */
    public function getVariantes(int $id): array
    {
        return $this->queryAll(
            "SELECT mp.id, mp.nombre, mp.stock_actual, mp.costo_unitario, um.abreviatura AS unidad, 'materia_prima' AS tipo
             FROM materias_primas mp
             LEFT JOIN unidades_medida um ON um.id = mp.unidad_medida_id
             WHERE mp.ingrediente_generico_id = ? AND mp.eliminado = 0
             UNION ALL
             SELECT ins.id, ins.nombre, ins.stock_actual, ins.costo_unitario, um2.abreviatura AS unidad, 'insumo' AS tipo
             FROM insumos ins
             LEFT JOIN unidades_medida um2 ON um2.id = ins.unidad_medida_id
             WHERE ins.ingrediente_generico_id = ? AND ins.eliminado = 0
             ORDER BY nombre",
            [$id, $id]
        );
    }

    /**
     * Candidatas para agregar a este genérico: misma unidad de medida base (la que ya exige
     * unidadCompatible()), activas, que coincidan con la búsqueda, sin genérico asignado
     * todavía — de AMBAS tablas (materia prima o insumo), cada una marcada con su 'tipo' para
     * que asignarVariante() sepa contra cuál trabajar. Reasignar una variante de un genérico a
     * otro es una decisión aparte, se hace desde su propia ficha, no se mezcla acá.
     */
    public function buscarCandidatasParaAsignar(int $genericoId, string $q): array
    {
        $unidadGenerico = $this->queryScalar('SELECT unidad_medida_id FROM ingredientes_genericos WHERE id = ?', [$genericoId]);
        if (!$unidadGenerico) return [];

        $like = '%' . $q . '%';
        return $this->queryAll(
            "SELECT mp.id, mp.nombre, mp.stock_actual, mp.costo_unitario, um.abreviatura AS unidad, 'materia_prima' AS tipo
             FROM materias_primas mp
             LEFT JOIN unidades_medida um ON um.id = mp.unidad_medida_id
             WHERE mp.eliminado = 0 AND mp.activo = 1 AND mp.unidad_medida_id = ?
               AND mp.ingrediente_generico_id IS NULL AND mp.nombre LIKE ?
             UNION ALL
             SELECT ins.id, ins.nombre, ins.stock_actual, ins.costo_unitario, um2.abreviatura AS unidad, 'insumo' AS tipo
             FROM insumos ins
             LEFT JOIN unidades_medida um2 ON um2.id = ins.unidad_medida_id
             WHERE ins.eliminado = 0 AND ins.activo = 1 AND ins.unidad_medida_id = ?
               AND ins.ingrediente_generico_id IS NULL AND ins.nombre LIKE ?
             ORDER BY nombre
             LIMIT 20",
            [$unidadGenerico, $like, $unidadGenerico, $like]
        );
    }

    /** Asigna una materia prima o insumo existente a este genérico — pisa lo que tuviera antes en ingrediente_generico_id. */
    public function asignarVariante(int $genericoId, string $tipo, int $itemId): void
    {
        $tabla = $this->tabla($tipo);
        if (!$tabla) throw new \InvalidArgumentException('Tipo inválido: debe ser materia_prima o insumo.');
        $this->query(
            "UPDATE {$tabla} SET ingrediente_generico_id = ? WHERE id = ? AND eliminado = 0",
            [$genericoId, $itemId]
        );
    }

    /** Desvincula una materia prima o insumo de su genérico actual — queda como variante suelta, no se elimina. */
    public function quitarVariante(string $tipo, int $itemId): void
    {
        $tabla = $this->tabla($tipo);
        if (!$tabla) throw new \InvalidArgumentException('Tipo inválido: debe ser materia_prima o insumo.');
        $this->query("UPDATE {$tabla} SET ingrediente_generico_id = NULL WHERE id = ?", [$itemId]);
    }

    /**
     * Costo por unidad base del genérico: promedio ponderado por stock entre todas sus
     * variantes activas (materias primas E insumos juntas — son mutuamente excluyentes en la
     * práctica, un genérico nunca tiene de los dos tipos a la vez). Si ninguna tiene stock,
     * promedia los costos sin ponderar (para no devolver 0 apenas se compra la primera vez).
     * Los insumos no tienen columna desvio_pct (a diferencia de materias primas) — se
     * consideran sin desvío (factor 1) para ese término.
     */
    public function calcularCosto(int $id): float
    {
        // costo_unitario de una MP/insumo ya está expresado por unidad base (gramo/ml/unidad
        // — FacturaCompraModel::actualizarStock() lo normaliza en cada compra usando
        // cantidad_contenida), así que las distintas variantes de un mismo genérico ya son
        // directamente comparables aunque vengan en paquetes de tamaños distintos.
        $row = $this->queryOne(
            "SELECT
                COALESCE(SUM(costo_normalizado * stock_actual), 0) AS costo_ponderado,
                COALESCE(SUM(stock_actual), 0)                     AS stock_total,
                COALESCE(AVG(costo_normalizado), 0)                AS costo_promedio_simple,
                COUNT(*)                                           AS cant_variantes
             FROM (
                SELECT costo_unitario * (1 + desvio_pct/100) AS costo_normalizado, stock_actual
                FROM materias_primas WHERE ingrediente_generico_id = ? AND eliminado = 0
                UNION ALL
                SELECT costo_unitario AS costo_normalizado, stock_actual
                FROM insumos WHERE ingrediente_generico_id = ? AND eliminado = 0
             ) variantes",
            [$id, $id]
        );
        if (!$row || (int) $row['cant_variantes'] === 0) return 0.0;

        $stockTotal = (float) $row['stock_total'];
        if ($stockTotal > 0.0001) {
            return round((float) $row['costo_ponderado'] / $stockTotal, 4);
        }
        return round((float) $row['costo_promedio_simple'], 4);
    }

    /** Valida que una MP/insumo pueda asignarse a este genérico: misma unidad de medida base */
    public function unidadCompatible(int $genericoId, int $unidadMedidaId): bool
    {
        $unidadGenerico = $this->queryScalar(
            "SELECT unidad_medida_id FROM ingredientes_genericos WHERE id = ?",
            [$genericoId]
        );
        return $unidadGenerico !== false && (int) $unidadGenerico === $unidadMedidaId;
    }

    /** Búsqueda difusa por nombre — usada por Materias Primas/Insumos y por el Chat IA */
    public function buscarPorNombre(string $q): array
    {
        return $this->queryAll(
            "SELECT ig.*, um.abreviatura AS unidad
             FROM ingredientes_genericos ig
             LEFT JOIN unidades_medida um ON um.id = ig.unidad_medida_id
             WHERE ig.eliminado = 0 AND ig.nombre LIKE ?
             ORDER BY ig.nombre
             LIMIT 10",
            ['%' . $q . '%']
        );
    }

}
