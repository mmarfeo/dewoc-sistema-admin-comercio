<?php
require_once BASE_PATH . '/app/Models/Model.php';

class FeriadoModel extends Model
{
    protected string $table = 'feriados';

    public function findPorAnio(int $anio): array
    {
        return $this->queryAll(
            "SELECT * FROM feriados WHERE YEAR(fecha) = :anio ORDER BY fecha",
            [':anio' => $anio]
        );
    }

    /** Mapa fecha (Y-m-d) => ['id'=>, 'nombre'=>] para lookup rápido */
    public function mapaPorAnio(int $anio): array
    {
        $mapa = [];
        foreach ($this->findPorAnio($anio) as $f) {
            $mapa[$f['fecha']] = ['id' => $f['id'], 'nombre' => $f['nombre']];
        }
        return $mapa;
    }

    /** Todas las fechas registradas como feriado (para validar jornadas de cualquier año) */
    public function findAllDates(): array
    {
        return array_column($this->queryAll("SELECT fecha FROM feriados"), 'fecha');
    }

    public function esFeriado(string $fecha): bool
    {
        return (bool)$this->queryScalar("SELECT COUNT(*) FROM feriados WHERE fecha = ?", [$fecha]);
    }

    public function agregar(string $fecha, ?string $nombre): int
    {
        $stmt = $this->db->prepare("INSERT INTO feriados (fecha, nombre) VALUES (?, ?)");
        $stmt->execute([$fecha, $nombre ?: null]);
        return (int)$this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        return (bool)$this->db->prepare("DELETE FROM feriados WHERE id = ?")->execute([$id]);
    }

    /**
     * Fechas feriado filtradas por tipo (inamovible/trasladable/turistico — no incluye
     * los cargados a mano sin clasificar, que tienen tipo NULL). Usado por Proyecciones
     * tanto para saber qué días del horizonte futuro son feriado, como para juntar los
     * feriados pasados con los que calcular el ratio histórico de venta en feriado.
     */
    public function findFechasPorTipos(array $tipos, ?string $desde = null, ?string $hasta = null): array
    {
        if (!$tipos) return [];

        $in     = implode(',', array_fill(0, count($tipos), '?'));
        $params = $tipos;
        $sql    = "SELECT fecha FROM feriados WHERE tipo IN ($in)";

        if ($desde !== null) { $sql .= " AND fecha >= ?"; $params[] = $desde; }
        if ($hasta !== null) { $sql .= " AND fecha <= ?"; $params[] = $hasta; }

        return array_column($this->queryAll($sql . " ORDER BY fecha", $params), 'fecha');
    }

    /**
     * Carga desde la importación automática (cron/feriados_importar.php) — a diferencia
     * de agregar(), respeta silenciosamente `uq_feriado_fecha` en vez de fallar, para no
     * pisar un feriado ya cargado a mano (por ejemplo, para Sueldos). Devuelve si insertó
     * una fila nueva o si esa fecha ya existía.
     */
    public function agregarDesdeImportacion(string $fecha, ?string $nombre, string $tipo): bool
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO feriados (fecha, nombre, tipo) VALUES (?, ?, ?)");
        $stmt->execute([$fecha, $nombre ?: null, $tipo]);
        return $stmt->rowCount() > 0;
    }
}
