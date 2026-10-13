<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Desvío = pérdida NO explicada por ventas ni por proceso (robo, rotura, consumo
 * interno, regalos, o un olvido de contar) — distinto de la merma de receta
 * (receta_detalle.merma_pct), que es pérdida de proceso conocida y ya se calcula aparte.
 *
 * El punto de verdad es el conteo físico de inventario (StockController::guardarInventario()),
 * no cualquier ajuste manual suelto. Cada conteo se compara contra el conteo físico ANTERIOR
 * del mismo ítem, con los movimientos de stock del período (stock_movimientos, ya en unidad
 * base):
 *
 *   esperado = stock contado la vez anterior + compras − consumo por ventas/producción
 *   pérdida  = esperado − stock contado ahora        (positivo = faltante, negativo = sobrante)
 *   desvío % = pérdida ÷ consumo registrado del período × 100
 *
 * Los ajustes manuales entre conteos NO se restan como consumo: pueden ser una rotura real o
 * un olvido de contar, así que quedan dentro de la pérdida. El primer conteo de un ítem es solo
 * el punto de partida (no hay contra qué comparar).
 * La pérdida del ÚLTIMO período se aplica al costo de la materia prima en el momento del conteo
 * (y de ahí a todas las recetas que la usan), sin filtros: el desvío vigente es ese % —con piso 0,
 * un sobrante lo deja en 0— y se pisa con el del conteo siguiente. Un ciclo sin consumo
 * registrado no se puede medir y deja el desvío como estaba. Ver docs/desvio-mermas-checklist.md.
 */
class DesvioModel extends Model
{
    protected string $table      = 'desvio_historial';
    protected string $primaryKey = 'id';

    /** Tope de desvio_historial.desvio_pct (DECIMAL(6,2)) */
    private const TOPE_HISTORIAL_PCT    = 9999.99;
    /** Tope de materias_primas.desvio_pct / productos.desvio_pct (DECIMAL(5,2)) */
    private const TOPE_VIGENTE_PCT      = 999.99;

    /**
     * Registra el ciclo de desvío de un ítem al hacer un conteo físico de inventario.
     * Se llama ANTES de StockModel::ajustar() con ese conteo: los movimientos que se miran son
     * los anteriores al ajuste. Siempre inserta una fila (baseline, sin consumo o válido) para
     * que el próximo conteo tenga contra qué compararse.
     * Solo mide materias primas y productos de REVENTA: la producción de un elaborado suma su
     * stock sin dejar movimiento, así que compararlo daría siempre un falso sobrante.
     */
    public function registrar(string $itemTipo, int $itemId, float $stockFisico, ?int $usuarioId): void
    {
        if (!in_array($itemTipo, ['producto', 'materia_prima'], true)) return;
        if ($itemTipo === 'producto' && !$this->esReventa($itemId)) return;

        $ultimoMovId   = $this->ultimoMovimientoId($itemTipo, $itemId);
        $costoUnitario = $this->costoUnitarioActual($itemTipo, $itemId);
        $anterior      = $this->cicloAnterior($itemTipo, $itemId);

        $fila = [
            'item_tipo'                 => $itemTipo,
            'item_id'                   => $itemId,
            'fecha'                     => date('Y-m-d'),
            'periodo_desde'             => date('Y-m-d H:i:s'),
            'cantidad_comprada'         => 0,
            'cantidad_explicada_ventas' => 0,
            'stock_teorico'             => $stockFisico,
            'stock_real_contado'        => $stockFisico,
            'desvio_cantidad'           => 0,
            'desvio_pct'                => 0,
            'costo_unitario_momento'    => $costoUnitario,
            'desvio_monto'              => 0,
            'estado'                    => 'baseline',
            'stock_inicial'             => null,
            'ultimo_mov_id'             => $ultimoMovId,
            'usuario_id'                => $usuarioId,
        ];

        if ($anterior) {
            $mov          = $this->movimientosDelPeriodo($itemTipo, $itemId, (int)($anterior['ultimo_mov_id'] ?? 0));
            $stockInicial = (float)$anterior['stock_real_contado'];
            $esperado     = $stockInicial + $mov['compras'] - $mov['consumo'];
            $perdida      = round($esperado - $stockFisico, 3);

            $pct    = 0.0;
            $estado = 'sin_consumo';
            if ($mov['consumo'] > 0.0001) {
                $pct    = $perdida / $mov['consumo'] * 100;
                $estado = 'valido';
            }

            $fila['periodo_desde']             = (string)$anterior['created_at'];
            $fila['cantidad_comprada']         = $mov['compras'];
            $fila['cantidad_explicada_ventas'] = $mov['consumo'];
            $fila['stock_teorico']             = round($esperado, 3);
            $fila['desvio_cantidad']           = $perdida;
            $fila['desvio_pct']                = max(-self::TOPE_HISTORIAL_PCT, min(self::TOPE_HISTORIAL_PCT, round($pct, 2)));
            $fila['desvio_monto']              = round($perdida * $costoUnitario, 2);
            $fila['estado']                    = $estado;
            $fila['stock_inicial']             = $stockInicial;
        }

        $this->insert($fila);

        if ($fila['estado'] === 'valido') {
            $this->aplicarDesvio($itemTipo, $itemId, $perdida, $mov['consumo']);
        }
    }

    private function esReventa(int $productoId): bool
    {
        return (string)$this->queryScalar("SELECT tipo FROM productos WHERE id = :id", [':id' => $productoId]) === 'reventa';
    }

    /** Último ciclo con la lógica actual (los 'legacy' usaron otra fórmula y no sirven de punto de partida) */
    private function cicloAnterior(string $itemTipo, int $itemId): array|false
    {
        return $this->queryOne(
            "SELECT * FROM desvio_historial
             WHERE item_tipo = :t AND item_id = :id AND estado <> 'legacy'
             ORDER BY id DESC LIMIT 1",
            [':t' => $itemTipo, ':id' => $itemId]
        );
    }

    /** Último movimiento de stock del ítem: el período siguiente arranca después de este */
    private function ultimoMovimientoId(string $itemTipo, int $itemId): int
    {
        return (int)$this->queryScalar(
            "SELECT COALESCE(MAX(id), 0) FROM stock_movimientos WHERE tipo_item = :t AND item_id = :id",
            [':t' => $itemTipo, ':id' => $itemId]
        );
    }

    /**
     * Compras y consumo del ítem desde el conteo anterior, en unidad base (los movimientos ya
     * vienen convertidos por cantidad_contenida). Solo se cuentan los orígenes que representan
     * entradas/consumo REAL: 'compra' (con su reversión por edición/anulación de factura como
     * salida), y 'venta'/'produccion' (con la anulación de venta como entrada). Los ajustes
     * ('ajuste_manual', 'chat_ia', 'inventario', 'apertura') NO se restan: son parte del desvío.
     * @return array{compras: float, consumo: float}
     */
    private function movimientosDelPeriodo(string $itemTipo, int $itemId, int $desdeMovId): array
    {
        $fila = $this->queryOne(
            "SELECT
                COALESCE(SUM(CASE WHEN origen = 'compra' THEN
                    CASE WHEN tipo_mov = 'entrada' THEN cantidad WHEN tipo_mov = 'salida' THEN -cantidad ELSE 0 END
                ELSE 0 END), 0) AS compras,
                COALESCE(SUM(CASE WHEN origen IN ('venta', 'produccion') THEN
                    CASE WHEN tipo_mov = 'salida' THEN cantidad WHEN tipo_mov = 'entrada' THEN -cantidad ELSE 0 END
                ELSE 0 END), 0) AS consumo
             FROM stock_movimientos
             WHERE tipo_item = :t AND item_id = :id AND id > :desde",
            [':t' => $itemTipo, ':id' => $itemId, ':desde' => $desdeMovId]
        );
        return [
            'compras' => (float)($fila['compras'] ?? 0),
            'consumo' => (float)($fila['consumo'] ?? 0),
        ];
    }

    /** Costo unitario base (sin desvío aplicado, para no calcular en círculo) */
    private function costoUnitarioActual(string $itemTipo, int $itemId): float
    {
        if ($itemTipo === 'producto') {
            return (float)$this->queryScalar("SELECT precio_costo FROM productos WHERE id=:id", [':id' => $itemId]);
        }
        return (float)$this->queryScalar(
            "SELECT costo_unitario FROM materias_primas WHERE id=:id",
            [':id' => $itemId]
        );
    }

    /**
     * Aplica al costo la pérdida del último período: desvio_pct = pérdida ÷ consumo del período
     * (cada unidad consumida por las recetas carga su parte de lo que faltó, así el sobrecosto
     * total iguala el valor de lo perdido). Piso 0: un desvío es una pérdida y no puede abaratar
     * el costo, así que un sobrante lo deja en 0. Tope 999,99 = máximo de la columna
     * (DECIMAL(5,2)). Para materias primas, si el valor cambió se propaga a las
     * recetas/productos que las usan (mismo criterio que MateriasPrimasController::actualizar()):
     * sin eso productos.precio_costo quedaba congelado en el desvío viejo.
     */
    private function aplicarDesvio(string $itemTipo, int $itemId, float $perdida, float $consumo): void
    {
        if ($consumo <= 0.0001) return;

        $desvioPct = max(0.0, min(self::TOPE_VIGENTE_PCT, round($perdida / $consumo * 100, 2)));
        $tabla     = $itemTipo === 'materia_prima' ? 'materias_primas' : 'productos';

        $actual = (float)$this->queryScalar("SELECT desvio_pct FROM `$tabla` WHERE id = :id", [':id' => $itemId]);
        if (abs($actual - $desvioPct) < 0.005) return;

        Database::getInstance()
            ->prepare("UPDATE `$tabla` SET desvio_pct=:d WHERE id=:id")
            ->execute([':d' => $desvioPct, ':id' => $itemId]);

        if ($itemTipo === 'materia_prima') {
            $this->propagarCosto($itemId);
        }
    }

    /**
     * Refresca el costo de las recetas/productos que usan la materia prima. Si falla no debe
     * tirar abajo el conteo de inventario (el desvío ya quedó guardado): se loguea y sigue.
     */
    private function propagarCosto(int $materiaPrimaId): void
    {
        try {
            require_once BASE_PATH . '/app/Models/RecetaModel.php';
            $recetaModel = new RecetaModel();
            $recetaIds   = $recetaModel->findIdsPorMateriaPrima($materiaPrimaId);
            $genericoId  = (int)$this->queryScalar(
                "SELECT ingrediente_generico_id FROM materias_primas WHERE id = :id",
                [':id' => $materiaPrimaId]
            );
            if ($genericoId) {
                $recetaIds = array_merge($recetaIds, $recetaModel->findIdsPorGenerico($genericoId));
            }
            if ($recetaIds) {
                $recetaModel->propagarCambioCosto(array_unique($recetaIds), null, 'Recálculo por desvío de inventario');
            }
        } catch (\Throwable $e) {
            logApp('No se pudo propagar el desvío de la materia prima #' . $materiaPrimaId . ': ' . $e->getMessage(), 'ERROR');
        }
    }

    /** Histórico de ciclos de desvío de un ítem, más reciente primero */
    public function historialItem(string $itemTipo, int $itemId): array
    {
        return $this->queryAll(
            "SELECT dh.*, u.nombre AS usuario_nombre
             FROM desvio_historial dh
             LEFT JOIN usuarios u ON u.id = dh.usuario_id
             WHERE dh.item_tipo=:t AND dh.item_id=:id
             ORDER BY dh.created_at DESC, dh.id DESC",
            [':t' => $itemTipo, ':id' => $itemId]
        );
    }

    /**
     * Reporte de mermas y desvíos: una fila por ítem con desvío vigente o con algún ciclo de
     * la lógica actual (un ítem con desvío 0 pero conteos baseline/sin consumo también se lista,
     * para poder revisar qué pasó), con el último ciclo (cantidad/$) y la tendencia contra el
     * ciclo anterior. Los ciclos 'legacy' no cuentan para el último/anterior.
     */
    public function reporte(): array
    {
        $productos = $this->queryAll(
            "SELECT 'producto' AS tipo, p.id, p.nombre, p.codigo, p.desvio_pct AS desvio_pct_actual,
                    NULL AS merma_pct
             FROM productos p
             WHERE p.eliminado=0 AND p.activo=1
               AND (p.desvio_pct > 0 OR (p.tipo = 'reventa' AND EXISTS (
                        SELECT 1 FROM desvio_historial dh
                        WHERE dh.item_tipo='producto' AND dh.item_id=p.id AND dh.estado <> 'legacy')))
             ORDER BY p.nombre"
        );
        $materias = $this->queryAll(
            "SELECT 'materia_prima' AS tipo, mp.id, mp.nombre, mp.codigo, mp.desvio_pct AS desvio_pct_actual,
                    (SELECT AVG(rd.merma_pct) FROM receta_detalle rd WHERE rd.materia_prima_id = mp.id) AS merma_pct
             FROM materias_primas mp
             WHERE mp.eliminado=0 AND mp.activo=1
               AND (mp.desvio_pct > 0 OR EXISTS (
                        SELECT 1 FROM desvio_historial dh
                        WHERE dh.item_tipo='materia_prima' AND dh.item_id=mp.id AND dh.estado <> 'legacy'))
             ORDER BY mp.nombre"
        );
        $items = array_merge($productos, $materias);

        foreach ($items as &$item) {
            $ciclos = array_values(array_filter(
                $this->historialItem($item['tipo'], (int)$item['id']),
                fn($c) => ($c['estado'] ?? 'legacy') !== 'legacy'
            ));
            $item['ultimo_ciclo']    = $ciclos[0] ?? null;
            $item['ciclo_anterior']  = $ciclos[1] ?? null;
            $item['cant_ciclos']     = count($ciclos);
        }

        return $items;
    }
}
