<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Proyecciones — Fase 1 (ver docs/prediccion-ventas-compras.md). Promedio móvil
 * ponderado por día de semana / día del mes / feriado para ventas, explosión de
 * receta para consumo de materia prima, y regresión lineal simple para tendencia
 * de margen. Sin ML, sin librerías externas — toda la aritmética es nativa en PHP.
 * Los resultados los persiste el cron (cron/proyecciones_generar.php); esta clase
 * no recalcula nada al leer, solo lee la última corrida guardada.
 */
class ProyeccionModel extends Model
{
    protected string $table = 'proyecciones';

    /** Semanas de historial usadas para el promedio móvil (8 semanas). */
    private const VENTANA_HISTORICA_DIAS = 56;

    /** Debajo de esta cantidad de feriados pasados con dato, no hay base confiable → peso 1.0 (sin ajuste). */
    private const MIN_MUESTRAS_FERIADO = 3;

    /** Clamp de los pesos combinados, para que un solo dato atípico no dispare la proyección. */
    private const PESO_MIN = 0.3;
    private const PESO_MAX = 3.0;

    /** Meses de historial usados para la tendencia de margen. */
    private const MESES_TENDENCIA_MARGEN = 6;

    /** Únicos tipos de feriado que importan para ponderar (ver docs/prediccion-ventas-compras.md) — "día no laborable" queda afuera a propósito. */
    private const TIPOS_FERIADO_RELEVANTES = ['inamovible', 'trasladable', 'turistico'];

    // ── Configuración ────────────────────────────────────────────────────────

    public function obtenerConfig(): array
    {
        return $this->queryOne("SELECT * FROM proyecciones_config WHERE id = 1")
            ?: ['id' => 1, 'horizonte_dias' => 14, 'usuario_id' => null];
    }

    public function guardarHorizonte(int $horizonteDias, int $usuarioId): void
    {
        $this->query(
            "INSERT INTO proyecciones_config (id, horizonte_dias, usuario_id) VALUES (1, :h, :u)
             ON DUPLICATE KEY UPDATE horizonte_dias = :h2, usuario_id = :u2",
            [':h' => $horizonteDias, ':u' => $usuarioId, ':h2' => $horizonteDias, ':u2' => $usuarioId]
        );
    }

    // ── (a) Ventas proyectadas por producto ─────────────────────────────────

    /**
     * Serie diaria [fecha => cantidad] para los próximos $horizonteDias días, a partir
     * de mañana. Promedio móvil de los últimos VENTANA_HISTORICA_DIAS días, ponderado
     * por día de la semana y por tercio del mes; en los días que caen feriado
     * (inamovible/trasladable/turístico), el peso de día-de-semana se REEMPLAZA por un
     * ratio propio calculado con el historial de ventas en feriados pasados de este
     * mismo producto (o 1.0 si no hay suficiente historial — ver MIN_MUESTRAS_FERIADO).
     */
    public function proyectarVentasProducto(int $productoId, int $horizonteDias, FeriadoModel $feriadoModel): array
    {
        $hoy   = new \DateTimeImmutable('today');
        $desde = $hoy->modify('-' . self::VENTANA_HISTORICA_DIAS . ' days');
        $hasta = $hoy->modify('-1 day');

        $historial = $this->queryAll(
            "SELECT DATE(v.created_at) AS fecha, SUM(vd.cantidad) AS cantidad
             FROM ventas_detalle vd
             JOIN ventas v ON v.id = vd.venta_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.producto_id = :pid
               AND DATE(v.created_at) BETWEEN :desde AND :hasta
             GROUP BY DATE(v.created_at)",
            [':pid' => $productoId, ':desde' => $desde->format('Y-m-d'), ':hasta' => $hasta->format('Y-m-d')]
        );
        $porFecha = [];
        foreach ($historial as $row) $porFecha[$row['fecha']] = (float)$row['cantidad'];

        $promedioDiarioBase = array_sum($porFecha) / self::VENTANA_HISTORICA_DIAS;

        $serieFutura = [];
        for ($i = 1; $i <= $horizonteDias; $i++) {
            $serieFutura[$hoy->modify("+{$i} days")->format('Y-m-d')] = 0.0;
        }
        // Sin ningún dato de venta en la ventana: no hay base para proyectar. Se devuelve
        // la serie en 0 en vez de romper el cron — el producto no aporta a "reponer pronto".
        if ($promedioDiarioBase <= 0.0) return $serieFutura;

        // Promedio por día de semana y por bucket de día del mes, recorriendo TODOS los
        // días de la ventana (no solo los que tuvieron venta), para que los días en 0
        // también empujen el promedio hacia abajo cuando corresponde.
        $sumaPorDow = $cantPorDow = array_fill(1, 7, 0.0);
        $sumaPorBucket = $cantPorBucket = array_fill(0, 3, 0.0);
        $periodo = new \DatePeriod($desde, new \DateInterval('P1D'), $hasta->modify('+1 day'));
        foreach ($periodo as $fechaObj) {
            $cant   = $porFecha[$fechaObj->format('Y-m-d')] ?? 0.0;
            $dow    = (int)$fechaObj->format('N');
            $bucket = self::bucketDelMes((int)$fechaObj->format('j'));
            $sumaPorDow[$dow]       += $cant; $cantPorDow[$dow]++;
            $sumaPorBucket[$bucket] += $cant; $cantPorBucket[$bucket]++;
        }

        $pesoDow = [];
        for ($d = 1; $d <= 7; $d++) {
            $promDow    = $cantPorDow[$d] > 0 ? $sumaPorDow[$d] / $cantPorDow[$d] : $promedioDiarioBase;
            $pesoDow[$d] = $promDow / $promedioDiarioBase;
        }
        $pesoBucket = [];
        for ($b = 0; $b < 3; $b++) {
            $promBucket   = $cantPorBucket[$b] > 0 ? $sumaPorBucket[$b] / $cantPorBucket[$b] : $promedioDiarioBase;
            $pesoBucket[$b] = $promBucket / $promedioDiarioBase;
        }

        $pesoFeriado = $this->calcularPesoFeriado($productoId, $promedioDiarioBase, $feriadoModel, $hoy);

        $feriadosFuturos = array_flip($feriadoModel->findFechasPorTipos(
            self::TIPOS_FERIADO_RELEVANTES,
            $hoy->modify('+1 day')->format('Y-m-d'),
            $hoy->modify("+{$horizonteDias} days")->format('Y-m-d')
        ));

        foreach ($serieFutura as $fecha => &$valor) {
            $fechaObj  = new \DateTimeImmutable($fecha);
            $bucket    = self::bucketDelMes((int)$fechaObj->format('j'));
            $esFeriado = isset($feriadosFuturos[$fecha]);
            $pesoBase  = $esFeriado ? $pesoFeriado : $pesoDow[(int)$fechaObj->format('N')];
            $peso      = max(self::PESO_MIN, min(self::PESO_MAX, $pesoBase * $pesoBucket[$bucket]));
            $valor     = round($promedioDiarioBase * $peso, 4);
        }
        unset($valor);

        return $serieFutura;
    }

    /** Ratio ventas-en-feriado-pasado vs. promedio diario, para este producto. Fallback 1.0 sin muestras suficientes. */
    private function calcularPesoFeriado(int $productoId, float $promedioDiarioBase, FeriadoModel $feriadoModel, \DateTimeImmutable $hoy): float
    {
        $feriadosPasados = $feriadoModel->findFechasPorTipos(self::TIPOS_FERIADO_RELEVANTES, null, $hoy->format('Y-m-d'));
        if (count($feriadosPasados) < self::MIN_MUESTRAS_FERIADO) return 1.0;

        $in    = implode(',', array_fill(0, count($feriadosPasados), '?'));
        $filas = $this->queryAll(
            "SELECT DATE(v.created_at) AS fecha, SUM(vd.cantidad) AS cantidad
             FROM ventas_detalle vd
             JOIN ventas v ON v.id = vd.venta_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.producto_id = ?
               AND DATE(v.created_at) IN ($in)
             GROUP BY DATE(v.created_at)",
            array_merge([$productoId], $feriadosPasados)
        );
        $porFecha = [];
        foreach ($filas as $f) $porFecha[$f['fecha']] = (float)$f['cantidad'];

        $suma = 0.0;
        foreach ($feriadosPasados as $f) $suma += $porFecha[$f] ?? 0.0;

        $promedioFeriado = $suma / count($feriadosPasados);
        return $promedioDiarioBase > 0.0 ? $promedioFeriado / $promedioDiarioBase : 1.0;
    }

    /** Bucket de día del mes en tercios: días 1-10→0, 11-20→1, 21-fin→2. */
    private static function bucketDelMes(int $dia): int
    {
        return min(2, intdiv($dia - 1, 10));
    }

    // ── (b) Consumo/compra proyectada de materia prima ──────────────────────

    /**
     * $totalesPorProducto: [producto_id => cantidad total proyectada en el horizonte].
     * Explota la receta de cada producto (vía RecetaModel::explotarConsumo) y acumula
     * por ingrediente. Los ingredientes de tipo ingrediente_generico se resuelven acá a
     * la variante de materia prima con más stock actual — misma simplificación que ya
     * usa RecetaModel::reponerIngrediente() para el caso inverso, porque no hay forma de
     * saber de qué variante puntual "hubiera salido" un consumo que todavía no pasó.
     */
    public function proyectarConsumoMateriaPrima(array $totalesPorProducto, RecetaModel $recetaModel): array
    {
        $necesidades = [];
        foreach ($totalesPorProducto as $productoId => $cantidadTotal) {
            if ($cantidadTotal <= 0.0) continue;
            foreach ($this->recetasDeProducto((int)$productoId) as $recetaId) {
                foreach ($recetaModel->explotarConsumo((int)$recetaId, $cantidadTotal) as $clave => $cantidad) {
                    $necesidades[$clave] = ($necesidades[$clave] ?? 0) + $cantidad;
                }
            }
        }

        foreach (array_keys($necesidades) as $clave) {
            if (!str_starts_with($clave, 'ingrediente_generico:')) continue;
            $cantidad   = $necesidades[$clave];
            $genericoId = (int)substr($clave, strlen('ingrediente_generico:'));
            unset($necesidades[$clave]);

            $variante = $this->queryOne(
                "SELECT id FROM materias_primas WHERE ingrediente_generico_id = ? AND eliminado = 0 ORDER BY stock_actual DESC LIMIT 1",
                [$genericoId]
            );
            if (!$variante) continue; // genérico sin ninguna variante cargada — no hay dónde acumularlo

            $claveMp = 'materia_prima:' . $variante['id'];
            $necesidades[$claveMp] = ($necesidades[$claveMp] ?? 0) + $cantidad;
        }

        $filas = [];
        foreach ($necesidades as $clave => $cantidad) {
            [$tipo, $id] = explode(':', $clave, 2);
            $tabla = $tipo === 'insumo' ? 'insumos' : 'materias_primas';
            $item  = $this->queryOne("SELECT nombre, stock_actual FROM {$tabla} WHERE id = ?", [(int)$id]);
            if (!$item) continue;

            $filas[] = [
                'entidad_tipo'        => $tipo,
                'entidad_id'          => (int)$id,
                'nombre'              => $item['nombre'],
                'necesario_horizonte' => round($cantidad, 4),
                'stock_actual'        => (float)$item['stock_actual'],
            ];
        }
        return $filas;
    }

    private function recetasDeProducto(int $productoId): array
    {
        return array_column(
            $this->queryAll('SELECT receta_id FROM producto_receta WHERE producto_id = ?', [$productoId]),
            'receta_id'
        );
    }

    // ── (c) Tendencia de margen por producto ─────────────────────────────────

    /**
     * OJO — aproximación, no margen histórico real: $costoMpActual es el costo de receta
     * de HOY, aplicado retroactivamente a las cantidades vendidas en meses pasados.
     * ventas_detalle no guarda el costo al momento de cada venta (se revisó: no existe esa
     * columna), así que no hay forma de reconstruir el margen "tal cual era" en ese momento.
     * El precio de venta sí es el real de cada mes. Este disclaimer también se muestra en
     * app/Views/proyecciones/index.php.
     * Excluye las filas hijas de promo: con precio $0, hundirían el precio promedio
     * (total / cantidad) de todo componente que se venda dentro de una promo — ver la
     * nota canónica en ReportesModel::margenVentasPorProducto(). Las dos consultas de
     * CANTIDAD de más arriba (proyectarVentasProducto() / calcularPesoFeriado()) sí las
     * cuentan a propósito: ahí son demanda real de unidades del componente, y la fila de
     * la promo no aporta consumo de materia prima propio (no tiene receta).
     */
    public function tendenciaMargenProducto(int $productoId, float $costoMpActual, int $meses = self::MESES_TENDENCIA_MARGEN): array
    {
        $meses = (int)$meses;
        $filas = $this->queryAll(
            "SELECT DATE_FORMAT(v.created_at, '%Y-%m') AS ym,
                    SUM(vd.cantidad) AS cantidad, SUM(vd.subtotal_con_iva) AS total
             FROM ventas_detalle vd
             JOIN ventas v ON v.id = vd.venta_id
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND vd.producto_id = :pid
               AND vd.venta_detalle_padre_id IS NULL
               AND v.created_at >= DATE_SUB(CURDATE(), INTERVAL {$meses} MONTH)
             GROUP BY ym
             ORDER BY ym",
            [':pid' => $productoId]
        );

        $puntos       = [];
        $margenActual = null;
        $i = 0;
        foreach ($filas as $f) {
            $cantidad = (float)$f['cantidad'];
            if ($cantidad <= 0.0) continue;
            $precioProm = (float)$f['total'] / $cantidad;
            if ($precioProm <= 0.0) continue;

            $margenActual = ($precioProm - $costoMpActual) / $precioProm * 100;
            $puntos[]     = [$i, $margenActual];
            $i++;
        }

        if (count($puntos) < 2) {
            return ['margen_actual' => $margenActual, 'pendiente' => 0.0, 'proyeccion' => $margenActual];
        }

        $reg = $this->regresionLineal($puntos);
        return [
            'margen_actual' => $margenActual,
            'pendiente'     => $reg['pendiente'],
            'proyeccion'    => $reg['intercepto'] + $reg['pendiente'] * count($puntos),
        ];
    }

    /** Mínimos cuadrados clásico sobre puntos [x, y]. Sin librería — aritmética simple. */
    private function regresionLineal(array $puntos): array
    {
        $n = count($puntos);
        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($puntos as [$x, $y]) {
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $den        = $n * $sumX2 - $sumX ** 2;
        $pendiente  = $den != 0.0 ? ($n * $sumXY - $sumX * $sumY) / $den : 0.0;
        $intercepto = ($sumY - $pendiente * $sumX) / $n;
        return ['pendiente' => $pendiente, 'intercepto' => $intercepto];
    }

    // ── Persistencia y lectura de la última corrida ──────────────────────────

    public function guardarProyeccion(
        string $tipo,
        string $entidadTipo,
        int $entidadId,
        string $fechaDesde,
        string $fechaHasta,
        float $valor,
        string $metodo
    ): int {
        $this->db->prepare(
            "INSERT INTO proyecciones (tipo, entidad_tipo, entidad_id, fecha_desde, fecha_hasta, valor_proyectado, metodo)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$tipo, $entidadTipo, $entidadId, $fechaDesde, $fechaHasta, $valor, $metodo]);
        return (int)$this->db->lastInsertId();
    }

    public function ultimaCorrida(): ?string
    {
        $fecha = $this->queryScalar("SELECT MAX(DATE(created_at)) FROM proyecciones");
        return $fecha ?: null;
    }

    public function obtenerUltimasVentasPorProducto(): array
    {
        return $this->queryAll(
            "SELECT pr.entidad_id AS producto_id, p.nombre AS producto_nombre,
                    pr.fecha_desde AS fecha, pr.valor_proyectado AS cantidad
             FROM proyecciones pr
             JOIN productos p ON p.id = pr.entidad_id
             WHERE pr.tipo = 'venta' AND pr.entidad_tipo = 'producto'
               AND DATE(pr.created_at) = (SELECT MAX(DATE(created_at)) FROM proyecciones)
             ORDER BY pr.fecha_desde, p.nombre"
        );
    }

    /**
     * Cruza el consumo proyectado (congelado al momento de la corrida) contra stock_actual
     * EN VIVO — comparar contra stock real solo tiene sentido al momento de mirarlo, aunque
     * la ponderación de la proyección en sí haya quedado fija (ver docs/prediccion-ventas-compras.md).
     */
    public function obtenerUltimasComprasMp(): array
    {
        $filas = $this->queryAll(
            "SELECT pr.entidad_tipo, pr.entidad_id, pr.valor_proyectado, pr.fecha_desde, pr.fecha_hasta
             FROM proyecciones pr
             WHERE pr.tipo = 'compra_mp'
               AND DATE(pr.created_at) = (SELECT MAX(DATE(created_at)) FROM proyecciones)"
        );

        $resultado = [];
        foreach ($filas as $f) {
            $tabla = $f['entidad_tipo'] === 'insumo' ? 'insumos' : 'materias_primas';
            $item  = $this->queryOne("SELECT nombre, stock_actual FROM {$tabla} WHERE id = ?", [$f['entidad_id']]);
            if (!$item) continue;

            $necesario     = (float)$f['valor_proyectado'];
            $stockActual   = (float)$item['stock_actual'];
            $dias          = max(1, (new \DateTime($f['fecha_desde']))->diff(new \DateTime($f['fecha_hasta']))->days + 1);
            $consumoDiario = $necesario / $dias;

            $resultado[] = [
                'entidad_tipo'        => $f['entidad_tipo'],
                'entidad_id'          => (int)$f['entidad_id'],
                'nombre'              => $item['nombre'],
                'necesario_horizonte' => $necesario,
                'stock_actual'        => $stockActual,
                'reponer'             => $stockActual < $necesario,
                'dias_hasta_quiebre'  => $consumoDiario > 0.0001 ? (int)floor($stockActual / $consumoDiario) : null,
            ];
        }

        usort($resultado, function ($a, $b) {
            if ($a['reponer'] !== $b['reponer']) return $b['reponer'] <=> $a['reponer'];
            return ($a['dias_hasta_quiebre'] ?? PHP_INT_MAX) <=> ($b['dias_hasta_quiebre'] ?? PHP_INT_MAX);
        });

        return $resultado;
    }

    public function obtenerUltimoMargenPorProducto(): array
    {
        return $this->queryAll(
            "SELECT pr.entidad_id AS producto_id, p.nombre AS producto_nombre,
                    pr.valor_proyectado AS margen_proyectado_pct
             FROM proyecciones pr
             JOIN productos p ON p.id = pr.entidad_id
             WHERE pr.tipo = 'margen' AND pr.entidad_tipo = 'producto'
               AND DATE(pr.created_at) = (SELECT MAX(DATE(created_at)) FROM proyecciones)
             ORDER BY p.nombre"
        );
    }
}
