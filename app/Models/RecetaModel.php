<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/ProductoModel.php';

class RecetaModel extends Model
{
    protected string $table      = 'recetas';
    protected string $primaryKey = 'id';

    /** Busca una receta activa por nombre exacto (sin distinguir mayúsculas ni espacios) — usada por el import para actualizar en vez de duplicar. */
    public function findByNombre(string $nombre): array|false
    {
        return $this->queryOne(
            "SELECT * FROM recetas WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
            [$nombre]
        );
    }

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            "SELECT r.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    (SELECT COUNT(*) FROM receta_detalle rd WHERE rd.receta_id=r.id) AS cant_ingredientes
             FROM recetas r
             LEFT JOIN productos p ON p.id = r.producto_id
             WHERE r.eliminado = 0 AND r.es_borrador = 0
             ORDER BY r.nombre"
        );
    }

    /**
     * Todas las recetas activas con su detalle de ingredientes (nombre/cantidad/unidad,
     * sin costo), para el Libro de Recetas. A propósito NO es un loop de
     * findByIdConDetalle() por receta: contra la base remota, cada llamada individual
     * tarda ~1s (varios JOIN), así que con 70+ recetas esa versión tardaba más de un
     * minuto y llegaba a cortarse por timeout. Acá son 2 queries en total, sin importar
     * cuántas recetas haya.
     */
    public function findAllConDetalleCompleto(): array
    {
        $recetas = $this->findAllConDetalle();

        $detalles = $this->queryAll(
            "SELECT rd.receta_id,
                    COALESCE(mp.nombre, ins.nombre, ig.nombre, rs.nombre, rd.ingrediente_libre_nombre) AS ingrediente_nombre,
                    rd.cantidad,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_ig.abreviatura, rs.unidad_rendimiento, rd.ingrediente_libre_unidad) AS unidad
             FROM receta_detalle rd
             LEFT JOIN materias_primas mp        ON mp.id  = rd.materia_prima_id
             LEFT JOIN insumos ins                ON ins.id = rd.insumo_id
             LEFT JOIN ingredientes_genericos ig  ON ig.id  = rd.ingrediente_generico_id
             LEFT JOIN recetas rs                 ON rs.id  = rd.receta_ingrediente_id
             LEFT JOIN unidades_medida um_mp      ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins     ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_ig      ON um_ig.id  = ig.unidad_medida_id
             ORDER BY rd.receta_id, rd.id"
        );

        $porReceta = [];
        foreach ($detalles as $d) {
            $porReceta[(int)$d['receta_id']][] = $d;
        }
        foreach ($recetas as &$r) {
            $r['detalle'] = $porReceta[(int)$r['id']] ?? [];
        }
        unset($r);

        return $recetas;
    }

    /** Una fila por cada ingrediente de cada receta (repite los datos de la receta en cada línea), para exportar el detalle completo */
    public function findParaExportar(): array
    {
        $rows = $this->queryAll(
            "SELECT
                r.id                                                             AS id_receta,
                r.nombre                                                         AS receta_nombre,
                p.nombre                                                         AS producto_final,
                r.rendimiento,
                r.unidad_rendimiento,
                (SELECT COUNT(*) FROM receta_detalle rd2 WHERE rd2.receta_id = r.id) AS cant_ingredientes,
                CASE WHEN r.activo THEN 'Activa' ELSE 'Inactiva' END              AS estado,
                rd.tipo_ingrediente,
                rd.receta_ingrediente_id,
                COALESCE(mp.nombre, ins.nombre, ig.nombre, rs.nombre)            AS ingrediente_nombre,
                rd.cantidad,
                COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_ig.abreviatura, rs.unidad_rendimiento) AS unidad,
                rd.merma_pct,
                COALESCE(
                    mp.costo_unitario * (1 + mp.desvio_pct/100),
                    ins.costo_unitario, gen.costo, 0
                )                                                                AS costo_unitario,
                mp.cantidad_contenida,
                rs.rendimiento                                                  AS sub_receta_rendimiento
             FROM recetas r
             LEFT JOIN productos p            ON p.id  = r.producto_id
             LEFT JOIN receta_detalle rd       ON rd.receta_id = r.id
             LEFT JOIN materias_primas mp      ON mp.id  = rd.materia_prima_id
             LEFT JOIN insumos ins             ON ins.id = rd.insumo_id
             LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
             LEFT JOIN recetas rs              ON rs.id  = rd.receta_ingrediente_id
             LEFT JOIN unidades_medida um_mp   ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins  ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_ig   ON um_ig.id  = ig.unidad_medida_id
             LEFT JOIN (
                 SELECT ingrediente_generico_id,
                        CASE WHEN SUM(stock_actual) > 0.0001
                             THEN SUM((costo_unitario * (1 + desvio_pct/100)) * stock_actual) / SUM(stock_actual)
                             ELSE AVG(costo_unitario * (1 + desvio_pct/100))
                        END AS costo
                 FROM materias_primas
                 WHERE eliminado = 0 AND ingrediente_generico_id IS NOT NULL
                 GROUP BY ingrediente_generico_id
             ) gen ON gen.ingrediente_generico_id = rd.ingrediente_generico_id
             WHERE r.eliminado = 0 AND r.es_borrador = 0
             ORDER BY r.nombre, rd.id ASC"
        );
        $this->enriquecerSubRecetas($rows);
        return $rows;
    }

    /**
     * El costo de un ingrediente que es a su vez otra receta no sale del SQL
     * (evita recursión en SQL): sus joins a mp/ins/gen ya dan NULL, así que
     * queda en 0 desde el COALESCE. Este paso completa ese costo_unitario
     * llamando a calcularCosto() sobre la sub-receta (recursivo, ya soporta
     * anidamiento a varios niveles).
     */
    private function enriquecerSubRecetas(array &$rows): void
    {
        foreach ($rows as &$row) {
            if (empty($row['receta_ingrediente_id'])) continue;
            $rendimiento = max((float)($row['sub_receta_rendimiento'] ?? 1), 0.001);
            $row['costo_unitario'] = $this->calcularCosto((int)$row['receta_ingrediente_id']) / $rendimiento;
        }
        unset($row);
    }

    public function findByIdConDetalle(int $id): ?array
    {
        $receta = $this->queryOne(
            "SELECT r.*, p.nombre AS producto_nombre
             FROM recetas r
             LEFT JOIN productos p ON p.id = r.producto_id
             WHERE r.id = :id AND r.eliminado = 0",
            [':id' => $id]
        );
        if (!$receta) return null;

        // Nota: acá el costo_unitario que se devuelve es la base SIN desvío (a
        // diferencia de otras consultas de este modelo, que sí lo incluyen ya
        // combinado). Este método alimenta el formulario de edición de recetas
        // (recetas/form.php), que necesita mostrar por separado "costo de MP" y
        // "costo de desvío" en vez de un solo número ya mezclado.
        $receta['detalle'] = $this->queryAll(
            "SELECT rd.*,
                    COALESCE(mp.nombre, ins.nombre, ig.nombre, rs.nombre, rd.ingrediente_libre_nombre) AS ingrediente_nombre,
                    COALESCE(mp.codigo, ins.codigo) AS ingrediente_codigo,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_ig.abreviatura, rs.unidad_rendimiento, rd.ingrediente_libre_unidad) AS unidad,
                    COALESCE(mp.stock_actual, ins.stock_actual, gen.stock_total) AS stock_actual,
                    COALESCE(
                        mp.costo_unitario,
                        ins.costo_unitario,
                        gen.costo_base,
                        rd.ingrediente_libre_costo_unitario
                    ) AS costo_unitario,
                    COALESCE(mp.desvio_pct, gen.desvio_pct, 0) AS desvio_pct,
                    rs.rendimiento AS sub_receta_rendimiento
             FROM receta_detalle rd
             LEFT JOIN materias_primas mp   ON mp.id  = rd.materia_prima_id
             LEFT JOIN insumos         ins  ON ins.id = rd.insumo_id
             LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
             LEFT JOIN recetas rs           ON rs.id  = rd.receta_ingrediente_id
             LEFT JOIN unidades_medida um_mp  ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_ig  ON um_ig.id  = ig.unidad_medida_id
             LEFT JOIN (
                 SELECT ingrediente_generico_id,
                        SUM(stock_actual) AS stock_total,
                        CASE WHEN SUM(stock_actual) > 0.0001
                             THEN SUM(costo_unitario * stock_actual) / SUM(stock_actual)
                             ELSE AVG(costo_unitario)
                        END AS costo_base,
                        CASE WHEN SUM(stock_actual) > 0.0001
                             THEN SUM(desvio_pct * stock_actual) / SUM(stock_actual)
                             ELSE AVG(desvio_pct)
                        END AS desvio_pct
                 FROM materias_primas
                 WHERE eliminado = 0 AND ingrediente_generico_id IS NOT NULL
                 GROUP BY ingrediente_generico_id
             ) gen ON gen.ingrediente_generico_id = rd.ingrediente_generico_id
             WHERE rd.receta_id = :id
             ORDER BY rd.id",
            [':id' => $id]
        );
        $this->enriquecerSubRecetas($receta['detalle']);

        return $receta;
    }

    public function guardar(array $cabecera, array $detalle, ?int $usuarioId = null): int
    {
        $this->db->beginTransaction();
        try {
            $id = $this->insert($cabecera);
            $this->insertarDetalle($id, $detalle, $this->db);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->guardarVersion($id, $cabecera, $detalle, $usuarioId);
        // Sincroniza el costo de cualquier producto que ya use esta receta (final o de
        // costo), sin esperar a la próxima compra — ver nota en actualizarConDetalle().
        // Un borrador del Simulador de Recetas nunca tiene producto vinculado todavía, así
        // que no vale la pena ensuciar recetas_historial_costo con esas idas y vueltas.
        if (empty($cabecera['es_borrador'])) {
            $this->propagarCambioCosto([$id], null, 'Recálculo por edición de receta');
        }
        return $id;
    }

    /**
     * Duplica una receta completa (todos sus ingredientes/cantidades) bajo un nombre
     * nuevo — para el caso de "esta variante es igual a la otra salvo un ingrediente"
     * (ej. "Latte saborizado frío" a partir de "Latte saborizado"): se entra a editar el
     * duplicado y se cambia/agrega solo lo que cambia, en vez de rearmar todo desde cero.
     * El `producto_id` NO se copia — el duplicado necesita su propio producto final (o
     * ninguno todavía), nunca el mismo que el original, porque si no las dos recetas
     * terminarían sumando stock al mismo producto al producir cada una por su lado.
     */
    public function duplicar(int $recetaId, string $nuevoNombre, ?int $usuarioId = null): int
    {
        $original = $this->findByIdConDetalle($recetaId);
        if (!$original) throw new \RuntimeException('Receta no encontrada.');

        $cabecera = [
            'nombre'             => $nuevoNombre,
            'producto_id'        => null,
            'rendimiento'        => $original['rendimiento'],
            'unidad_rendimiento' => $original['unidad_rendimiento'],
            'descripcion'        => $original['descripcion'],
            'instrucciones'      => $original['instrucciones'],
            'tiempo_preparacion' => $original['tiempo_preparacion'],
            'porciones'          => $original['porciones'],
            'notas'              => $original['notas'],
            'activo'             => 1,
            'eliminado'          => 0,
        ];

        return $this->guardar($cabecera, $original['detalle'], $usuarioId);
    }

    public function actualizarConDetalle(int $id, array $cabecera, array $detalle, ?int $usuarioId = null): void
    {
        foreach ($detalle as $it) {
            if (empty($it['receta_ingrediente_id'])) continue;
            $childId = (int)$it['receta_ingrediente_id'];
            if ($childId === $id || $this->_recetaContiene($childId, $id)) {
                $nombreChild = (string)($this->queryScalar('SELECT nombre FROM recetas WHERE id = :id', [':id' => $childId]) ?: $childId);
                throw new \RuntimeException("No se puede usar \"{$nombreChild}\" como ingrediente: crearía una referencia circular con esta receta.");
            }
        }

        $this->db->beginTransaction();
        try {
            $this->update($id, $cabecera);
            $this->db->prepare("DELETE FROM receta_detalle WHERE receta_id = :id")->execute([':id' => $id]);
            $this->insertarDetalle($id, $detalle, $this->db);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->guardarVersion($id, $cabecera, $detalle, $usuarioId);

        // Antes, productos.precio_costo solo se refrescaba con la próxima compra
        // (propagarCambioCosto(), disparado desde FacturaCompraModel::actualizarStock()) —
        // si mientras tanto se editaba la receta a mano (cambiar una cantidad, sacar/agregar
        // un ingrediente), el costo guardado del producto quedaba desactualizado hasta la
        // siguiente compra, sin avisar. propagarCambioCosto() ya sabe recalcular en cascada
        // (sub-recetas que la usan, producto final 1:1, productos vía pivote, y ahora también
        // promos que usen alguno de esos productos como componente) — se reutiliza tal cual,
        // solo que el motivo/factura quedan en null porque no vino de una compra.
        if (empty($cabecera['es_borrador'])) {
            $this->propagarCambioCosto([$id], null, 'Recálculo por edición de receta');
        }
    }

    /** Recorre transitivamente los ingredientes-receta de $recetaId para ver si en algún nivel usa $buscadoId. */
    private function _recetaContiene(int $recetaId, int $buscadoId, array $visitados = []): bool
    {
        if (in_array($recetaId, $visitados, true)) return false; // ya recorrido (ciclo preexistente, no lo agrava)
        $visitados[] = $recetaId;

        $hijos = array_column(
            $this->queryAll(
                "SELECT receta_ingrediente_id FROM receta_detalle WHERE receta_id = :id AND tipo_ingrediente = 'receta'",
                [':id' => $recetaId]
            ),
            'receta_ingrediente_id'
        );
        foreach ($hijos as $hijoId) {
            $hijoId = (int)$hijoId;
            if ($hijoId === $buscadoId) return true;
            if ($this->_recetaContiene($hijoId, $buscadoId, $visitados)) return true;
        }
        return false;
    }

    private function guardarVersion(int $recetaId, array $cabecera, array $detalle, ?int $usuarioId): void
    {
        $enriched = [];
        foreach ($detalle as $it) {
            $nombre = '';
            if (!empty($it['materia_prima_id'])) {
                $nombre = (string)($this->queryScalar(
                    'SELECT nombre FROM materias_primas WHERE id = :id',
                    [':id' => (int)$it['materia_prima_id']]
                ) ?: '');
            } elseif (!empty($it['insumo_id'])) {
                $nombre = (string)($this->queryScalar(
                    'SELECT nombre FROM insumos WHERE id = :id',
                    [':id' => (int)$it['insumo_id']]
                ) ?: '');
            } elseif (!empty($it['ingrediente_generico_id'])) {
                $nombre = (string)($this->queryScalar(
                    'SELECT nombre FROM ingredientes_genericos WHERE id = :id',
                    [':id' => (int)$it['ingrediente_generico_id']]
                ) ?: '');
            } elseif (!empty($it['receta_ingrediente_id'])) {
                $nombre = (string)($this->queryScalar(
                    'SELECT nombre FROM recetas WHERE id = :id',
                    [':id' => (int)$it['receta_ingrediente_id']]
                ) ?: '');
            } elseif (!empty($it['ingrediente_libre_nombre'])) {
                $nombre = (string)$it['ingrediente_libre_nombre'];
            }
            $enriched[] = [
                'tipo'      => $it['tipo_ingrediente'],
                'nombre'    => $nombre,
                'cantidad'  => (float)$it['cantidad'],
                'merma_pct' => (float)($it['merma_pct'] ?? 0),
            ];
        }

        $version = (int)($this->queryScalar(
            'SELECT COALESCE(MAX(version), 0) FROM recetas_versiones WHERE receta_id = :rid',
            [':rid' => $recetaId]
        )) + 1;

        $this->query(
            "INSERT INTO recetas_versiones (receta_id, version, nombre, rendimiento, unidad_rendimiento, detalle_json, usuario_id)
             VALUES (:rid, :ver, :nom, :rend, :unidad, :det, :uid)",
            [
                ':rid'   => $recetaId,
                ':ver'   => $version,
                ':nom'   => $cabecera['nombre'],
                ':rend'  => (float)$cabecera['rendimiento'],
                ':unidad'=> $cabecera['unidad_rendimiento'],
                ':det'   => json_encode($enriched, JSON_UNESCAPED_UNICODE),
                ':uid'   => $usuarioId,
            ]
        );
    }

    /** Historial de cambios de costo de la receta, disparados por compras de sus ingredientes genéricos */
    public function findHistorialCosto(int $recetaId): array
    {
        return $this->queryAll(
            "SELECT rhc.*, fc.nro_comprobante, p.razon_social AS proveedor_nombre
             FROM recetas_historial_costo rhc
             LEFT JOIN facturas_compra fc ON fc.id = rhc.factura_compra_id
             LEFT JOIN proveedores p      ON p.id  = fc.proveedor_id
             WHERE rhc.receta_id = ?
             ORDER BY rhc.created_at DESC, rhc.id DESC",
            [$recetaId]
        );
    }

    /** Número de la versión vigente (la más alta guardada) — 1 si todavía no tiene ninguna registrada. */
    public function versionActual(int $recetaId): int
    {
        return (int)($this->queryScalar(
            'SELECT COALESCE(MAX(version), 1) FROM recetas_versiones WHERE receta_id = :rid',
            [':rid' => $recetaId]
        ));
    }

    /** Número de versión vigente de TODAS las recetas de una — para listados (ej. Libro de Recetas) sin repetir la query por cada una. */
    public function versionesActuales(): array
    {
        $rows = $this->queryAll('SELECT receta_id, MAX(version) AS version FROM recetas_versiones GROUP BY receta_id');
        return array_map('intval', array_column($rows, 'version', 'receta_id'));
    }

    public function findVersiones(int $recetaId): array
    {
        return $this->queryAll(
            "SELECT rv.*, u.nombre AS usuario_nombre
             FROM recetas_versiones rv
             LEFT JOIN usuarios u ON u.id = rv.usuario_id
             WHERE rv.receta_id = :rid
             ORDER BY rv.version DESC",
            [':rid' => $recetaId]
        );
    }

    /**
     * El snapshot de una versión guardada (detalle_json) solo registra la
     * composición (ingrediente/cantidad/merma), no el id ni el costo — es un
     * historial de "qué llevaba la receta", no de "cuánto costaba". Para poder
     * mostrar un costo igual, se busca cada ingrediente por nombre contra el
     * catálogo actual y se costea a precios de HOY (no es un costo histórico
     * real; si cambió mucho el precio desde esa versión, el número lo refleja).
     */
    public function costearDetalleHistorico(array $detalle): array
    {
        require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
        $genericoModel = new IngredienteGenericoModel();

        $total = 0.0;
        foreach ($detalle as &$ing) {
            $tipo   = $ing['tipo']   ?? '';
            $nombre = $ing['nombre'] ?? '';
            $costoUnitario = 0.0;

            if ($tipo === 'materia_prima' && $nombre !== '') {
                $costoUnitario = (float) ($this->queryScalar(
                    "SELECT costo_unitario * (1 + desvio_pct/100)
                     FROM materias_primas WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
                    [$nombre]
                ) ?: 0);
            } elseif ($tipo === 'insumo' && $nombre !== '') {
                $costoUnitario = (float) ($this->queryScalar(
                    "SELECT costo_unitario FROM insumos WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
                    [$nombre]
                ) ?: 0);
            } elseif ($tipo === 'ingrediente_generico' && $nombre !== '') {
                $genId = $this->queryScalar(
                    "SELECT id FROM ingredientes_genericos WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
                    [$nombre]
                );
                if ($genId) $costoUnitario = $genericoModel->calcularCosto((int) $genId);
            } elseif ($tipo === 'receta' && $nombre !== '') {
                $sub = $this->queryOne(
                    "SELECT id, rendimiento FROM recetas WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
                    [$nombre]
                );
                if ($sub) $costoUnitario = $this->calcularCosto((int)$sub['id']) / max((float)$sub['rendimiento'], 0.001);
            }

            $costoLinea = $costoUnitario * (float) ($ing['cantidad'] ?? 0) * (1 + (float) ($ing['merma_pct'] ?? 0) / 100);
            $ing['costo_unitario'] = $costoUnitario;
            $ing['costo_total']    = $costoLinea;
            $total += $costoLinea;
        }
        unset($ing);

        return ['detalle' => $detalle, 'total' => $total];
    }

    private function insertarDetalle(int $recetaId, array $detalle, \PDO $conn): void
    {
        $stmt = $conn->prepare(
            "INSERT INTO receta_detalle
                (receta_id, tipo_ingrediente, materia_prima_id, insumo_id, ingrediente_generico_id, receta_ingrediente_id, cantidad, merma_pct, ingrediente_libre_nombre, ingrediente_libre_costo_unitario, ingrediente_libre_unidad)
             VALUES (:rid, :tipo, :mp, :ins, :gen, :rec, :cant, :merma, :libre_nombre, :libre_costo, :libre_unidad)"
        );
        foreach ($detalle as $it) {
            $stmt->execute([
                ':rid'  => $recetaId,
                ':tipo' => $it['tipo_ingrediente'],
                ':mp'   => $it['materia_prima_id'] ?: null,
                ':ins'  => $it['insumo_id'] ?: null,
                ':gen'  => !empty($it['ingrediente_generico_id']) ? $it['ingrediente_generico_id'] : null,
                ':rec'  => !empty($it['receta_ingrediente_id']) ? $it['receta_ingrediente_id'] : null,
                ':cant' => (float)$it['cantidad'],
                ':merma'=> (float)($it['merma_pct'] ?? 0),
                ':libre_nombre' => $it['ingrediente_libre_nombre'] ?? null,
                ':libre_costo'  => isset($it['ingrediente_libre_costo_unitario']) ? (float)$it['ingrediente_libre_costo_unitario'] : null,
                ':libre_unidad' => $it['ingrediente_libre_unidad'] ?? null,
            ]);
        }
    }

    /**
     * De $seleccionSabores (ver VentaModel::_seleccionSabores — sabores elegidos por el
     * cliente en ESTA venta puntual, vía el selector de opciones), devuelve solo las
     * entradas que pertenecen al ingrediente genérico $genericoId.
     */
    private function _seleccionParaGenerico(int $genericoId, array $seleccionSabores): array
    {
        return array_values(array_filter(
            $seleccionSabores,
            fn($s) => (int)($s['ingrediente_generico_id'] ?? 0) === $genericoId
        ));
    }

    /**
     * Inserta el movimiento en stock_movimientos DESPUÉS de que el UPDATE de stock_actual
     * ya corrió (lee el valor ya actualizado y reconstruye stock_antes restando/sumando
     * $delta) — mismo patrón que StockModel::ajustar(). Antes ningún consumo/reposición de
     * receta quedaba loggeado acá, así que el reporte de Stock teórico vs físico
     * (StockModel::stockTeoricoVsFisico()) no podía ver nada de esto — ver conversación
     * de soporte. $ventaId (hilado desde consumirParaVenta/reponerParaVentaAnulada, que a
     * su vez lo reciben de VentaModel::registrar()/anular()) permite vincular el consumo
     * de MP/insumo por receta a la venta puntual que lo causó — antes quedaba NULL a
     * propósito porque nada lo necesitaba; ahora lo usa StockModel::comprasYVentasStock()
     * para poder linkear "esta salida vino de la venta #X".
     */
    private function _registrarMovimientoStock(string $tipoItem, int $itemId, float $delta, string $motivo, ?int $ventaId = null): void
    {
        $tabla        = $tipoItem === 'insumo' ? 'insumos' : 'materias_primas';
        $stockDespues = (float)$this->queryScalar("SELECT stock_actual FROM `{$tabla}` WHERE id = ?", [$itemId]);
        $stockAntes   = $stockDespues - $delta;
        $this->db->prepare(
            "INSERT INTO stock_movimientos (tipo_item, item_id, tipo_mov, origen, origen_id, cantidad, stock_antes, stock_despues, motivo, created_at)
             VALUES (:ti, :iid, :tm, :o, :oid, :cant, :sa, :sd, :mot, NOW())"
        )->execute([
            ':ti'   => $tipoItem,
            ':iid'  => $itemId,
            ':tm'   => $delta < 0 ? 'salida' : 'entrada',
            ':o'    => $ventaId !== null ? 'venta' : 'produccion',
            ':oid'  => $ventaId,
            ':cant' => abs($delta),
            ':sa'   => $stockAntes,
            ':sd'   => $stockDespues,
            ':mot'  => $motivo,
        ]);
    }

    /**
     * Descuenta stock de un ítem de receta. Si el ítem es un ingrediente genérico y la
     * venta trae sabores elegidos por el cliente para ese mismo genérico (vía el
     * selector de opciones — ver VentaModel), reparte $necesario proporcionalmente
     * entre esos sabores puntuales en vez de adivinar. Si no hay selección para este
     * genérico (ej. una receta como "Milkshake" sin selector de sabor), sigue
     * consumiendo FIFO entre variantes con stock, como siempre.
     */
    private function descontarIngrediente(array $it, float $necesario, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        if (!empty($it['materia_prima_id'])) {
            $this->db->prepare("UPDATE materias_primas SET stock_actual=stock_actual-:c WHERE id=:id")
                     ->execute([':c' => $necesario, ':id' => $it['materia_prima_id']]);
            $this->_registrarMovimientoStock('materia_prima', (int)$it['materia_prima_id'], -$necesario, 'Consumo de receta', $ventaId);
            return;
        }
        if (!empty($it['insumo_id'])) {
            $this->db->prepare("UPDATE insumos SET stock_actual=stock_actual-:c WHERE id=:id")
                     ->execute([':c' => $necesario, ':id' => $it['insumo_id']]);
            $this->_registrarMovimientoStock('insumo', (int)$it['insumo_id'], -$necesario, 'Consumo de receta', $ventaId);
            return;
        }
        if (!empty($it['ingrediente_generico_id'])) {
            $elegidos = $this->_seleccionParaGenerico((int)$it['ingrediente_generico_id'], $seleccionSabores);
            $pesoTotal = array_sum(array_column($elegidos, 'peso'));
            if ($elegidos && $pesoTotal > 0) {
                $restante = $necesario;
                $ultimoIdx = count($elegidos) - 1;
                foreach ($elegidos as $i => $s) {
                    // el último se lleva el resto exacto, para no perder centésimas por redondeo
                    $porcion = ($i === $ultimoIdx) ? $restante : round($necesario * $s['peso'] / $pesoTotal, 4);
                    $this->db->prepare("UPDATE materias_primas SET stock_actual = stock_actual - ? WHERE id = ?")
                             ->execute([$porcion, $s['materia_prima_id']]);
                    $this->_registrarMovimientoStock('materia_prima', (int)$s['materia_prima_id'], -$porcion, 'Consumo de receta (sabor elegido)', $ventaId);
                    $restante -= $porcion;
                }
                return;
            }

            $variantes = $this->queryAll(
                "SELECT id, stock_actual FROM materias_primas
                 WHERE ingrediente_generico_id = ? AND eliminado = 0 AND stock_actual > 0
                 ORDER BY updated_at ASC",
                [$it['ingrediente_generico_id']]
            );
            $restante = $necesario;
            foreach ($variantes as $v) {
                if ($restante <= 0.0001) break;
                $tomar = min($restante, (float)$v['stock_actual']);
                $this->db->prepare("UPDATE materias_primas SET stock_actual = stock_actual - ? WHERE id = ?")
                         ->execute([$tomar, $v['id']]);
                $this->_registrarMovimientoStock('materia_prima', (int)$v['id'], -$tomar, 'Consumo de receta (FIFO variantes)', $ventaId);
                $restante -= $tomar;
            }
            // Si por redondeo quedó un resto ínfimo, se lo descuenta igual de la última variante
            // usada para no dejar el consumo incompleto (ya se validó que había stock suficiente).
            if ($restante > 0.0001 && $variantes) {
                $ultima = end($variantes);
                $this->db->prepare("UPDATE materias_primas SET stock_actual = stock_actual - ? WHERE id = ?")
                         ->execute([$restante, $ultima['id']]);
                $this->_registrarMovimientoStock('materia_prima', (int)$ultima['id'], -$restante, 'Consumo de receta (resto de redondeo)', $ventaId);
            }
        }
    }

    /**
     * Verifica stock suficiente para producir un detalle de receta. Si un ítem es a su
     * vez otra receta, no tiene stock propio — se "explota" recursivamente contra los
     * ingredientes base de esa sub-receta (escalados por cuántas "producciones" de ella
     * hacen falta), igual que ya se hace con ingrediente_generico vía descontarIngrediente.
     */
    private function verificarFaltantes(array $detalle, float $factor, array $seleccionSabores = []): array
    {
        return array_map(
            fn($f) => $f['nombre'] . " (necesita {$f['necesario']}, hay {$f['disponible']})",
            $this->verificarFaltantesDetallado($detalle, $factor, $seleccionSabores)
        );
    }

    /**
     * Igual que verificarFaltantes(), pero devuelve datos estructurados en vez de un
     * texto ya armado — para poder guardar el detalle (ej. en ventas_sin_stock) en vez
     * de tener que volver a parsear un string.
     *
     * Si el ítem es un ingrediente genérico y la venta trae sabores elegidos para ese
     * genérico, el chequeo se hace contra el stock de CADA sabor puntual elegido (no
     * contra el agregado de las 33 variantes) — si no, alcanzaba con que CUALQUIER
     * sabor tuviera stock de sobra para "tapar" que el elegido estuviera en cero.
     */
    private function verificarFaltantesDetallado(array $detalle, float $factor, array $seleccionSabores = []): array
    {
        $faltantes = [];
        foreach ($detalle as $it) {
            $necesario = (float)$it['cantidad'] * $factor * (1 + (float)$it['merma_pct'] / 100);
            if (!empty($it['receta_ingrediente_id'])) {
                $sub = $this->findByIdConDetalle((int)$it['receta_ingrediente_id']);
                if (!$sub) continue;
                $rendimiento = max((float)($sub['rendimiento'] ?? 1), 0.001);
                $faltantes = array_merge($faltantes, $this->verificarFaltantesDetallado($sub['detalle'], $necesario / $rendimiento, $seleccionSabores));
                continue;
            }

            $elegidos = !empty($it['ingrediente_generico_id'])
                ? $this->_seleccionParaGenerico((int)$it['ingrediente_generico_id'], $seleccionSabores)
                : [];
            $pesoTotal = array_sum(array_column($elegidos, 'peso'));
            if ($elegidos && $pesoTotal > 0) {
                foreach ($elegidos as $s) {
                    $porcion = $necesario * $s['peso'] / $pesoTotal;
                    $disponible = (float)$this->queryScalar(
                        'SELECT stock_actual FROM materias_primas WHERE id = ?',
                        [$s['materia_prima_id']]
                    );
                    if ($disponible < $porcion) {
                        $faltantes[] = [
                            'tipo_ingrediente'        => 'materia_prima',
                            'materia_prima_id'        => $s['materia_prima_id'],
                            'insumo_id'               => null,
                            'ingrediente_generico_id' => $it['ingrediente_generico_id'],
                            'nombre'                  => $it['ingrediente_nombre'],
                            'necesario'               => $porcion,
                            'disponible'              => $disponible,
                        ];
                    }
                }
                continue;
            }

            if ((float)$it['stock_actual'] < $necesario) {
                $faltantes[] = [
                    'tipo_ingrediente'        => $it['tipo_ingrediente'],
                    'materia_prima_id'        => $it['materia_prima_id'] ?? null,
                    'insumo_id'               => $it['insumo_id'] ?? null,
                    'ingrediente_generico_id' => $it['ingrediente_generico_id'] ?? null,
                    'nombre'                  => $it['ingrediente_nombre'],
                    'necesario'               => $necesario,
                    'disponible'              => (float)$it['stock_actual'],
                ];
            }
        }
        return $faltantes;
    }

    /** Descuenta stock de un detalle completo de receta, explotando recursivamente las sub-recetas. */
    private function descontarDetalle(array $detalle, float $factor, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        foreach ($detalle as $it) {
            $necesario = (float)$it['cantidad'] * $factor * (1 + (float)$it['merma_pct'] / 100);
            if (!empty($it['receta_ingrediente_id'])) {
                $sub = $this->findByIdConDetalle((int)$it['receta_ingrediente_id']);
                if (!$sub) continue;
                $rendimiento = max((float)($sub['rendimiento'] ?? 1), 0.001);
                $this->descontarDetalle($sub['detalle'], $necesario / $rendimiento, $seleccionSabores, $ventaId);
            } else {
                $this->descontarIngrediente($it, $necesario, $seleccionSabores, $ventaId);
            }
        }
    }

    /** Registra una producción: descuenta ingredientes y suma stock al producto */
    public function producir(int $recetaId, float $cantProducciones, int $usuarioId): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) throw new \RuntimeException('Receta no encontrada.');

        $this->db->beginTransaction();
        try {
            $faltantes = $this->verificarFaltantes($receta['detalle'], $cantProducciones);
            if ($faltantes) {
                $this->db->rollBack();
                return ['ok' => false, 'faltantes' => $faltantes];
            }

            $this->descontarDetalle($receta['detalle'], $cantProducciones);

            // Sumar stock al producto terminado
            $cantFinal = $cantProducciones * (float)($receta['rendimiento'] ?? 1);
            if ($receta['producto_id']) {
                $this->db->prepare("UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id")
                         ->execute([':c' => $cantFinal, ':id' => $receta['producto_id']]);
            }

            // Registrar orden de producción
            $this->db->prepare(
                "INSERT INTO ordenes_produccion
                    (receta_id, usuario_id, cantidad_producida, estado, created_at, updated_at)
                 VALUES (:rid, :uid, :cant, 'completada', NOW(), NOW())"
            )->execute([':rid' => $recetaId, ':uid' => $usuarioId, ':cant' => $cantFinal]);

            $this->db->commit();
            return ['ok' => true, 'producido' => $cantFinal];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Verifica alcance de stock para consumir directamente los ingredientes de una
     * receta al vender $cantidadVendida unidades del producto que produce, SIN pasar
     * por una Producción por lotes — usado por VentaModel::registrar() para productos
     * "no controla stock" (hechos al momento, ej. un café) que tienen una receta
     * asociada: la venta descuenta directo de la materia prima en vez de depender de
     * que alguien "produzca" antes. Reusa la misma lógica de verificarFaltantes() que
     * ya usa producir(), incluyendo sub-recetas e ingrediente_generico.
     */
    public function verificarFaltantesParaVenta(int $recetaId, float $cantidadVendida, array $seleccionSabores = []): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return [];
        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        return $this->verificarFaltantes($receta['detalle'], $cantidadVendida / $rendimiento, $seleccionSabores);
    }

    /**
     * Igual que verificarFaltantesParaVenta(), pero con el detalle estructurado (no texto)
     * — usado cuando el usuario decide vender igual sin stock, para guardar el registro
     * en ventas_sin_stock.
     *
     * $seleccionSabores: sabores puntuales elegidos por el cliente en esta venta (ver
     * VentaModel::_seleccionSabores) — cuando la receta usa un ingrediente genérico que
     * coincide, el chequeo de stock se hace contra esos sabores específicos en vez del
     * agregado de todas las variantes.
     */
    public function verificarFaltantesDetalladoParaVenta(int $recetaId, float $cantidadVendida, array $seleccionSabores = []): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return [];
        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        return $this->verificarFaltantesDetallado($receta['detalle'], $cantidadVendida / $rendimiento, $seleccionSabores);
    }

    /**
     * Descuenta los ingredientes de una receta por una venta directa (ver
     * verificarFaltantesParaVenta()). $seleccionSabores: ver
     * VentaModel::_seleccionSabores() — sabores puntuales elegidos por el cliente en
     * esta venta, para resolver las líneas de ingrediente_generico contra el sabor
     * real en vez de FIFO ciego.
     */
    public function consumirParaVenta(int $recetaId, float $cantidadVendida, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return;
        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        $this->descontarDetalle($receta['detalle'], $cantidadVendida / $rendimiento, $seleccionSabores, $ventaId);
    }

    /**
     * Repone los ingredientes de una receta al anular una venta que los había consumido
     * directo. $seleccionSabores debe ser la MISMA selección de sabores que se usó al
     * vender (VentaModel::anular() la reconstruye desde ventas_detalle_opciones), para
     * reponer exacto al sabor que se consumió en vez de adivinar.
     */
    public function reponerParaVentaAnulada(int $recetaId, float $cantidadVendida, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return;
        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        $this->reponerDetalle($receta['detalle'], $cantidadVendida / $rendimiento, $seleccionSabores, $ventaId);
    }

    /** Repone (suma) stock de un detalle completo de receta, explotando recursivamente las sub-recetas. Inverso de descontarDetalle(). */
    private function reponerDetalle(array $detalle, float $factor, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        foreach ($detalle as $it) {
            $cantidad = (float)$it['cantidad'] * $factor * (1 + (float)$it['merma_pct'] / 100);
            if (!empty($it['receta_ingrediente_id'])) {
                $sub = $this->findByIdConDetalle((int)$it['receta_ingrediente_id']);
                if (!$sub) continue;
                $rendimiento = max((float)($sub['rendimiento'] ?? 1), 0.001);
                $this->reponerDetalle($sub['detalle'], $cantidad / $rendimiento, $seleccionSabores, $ventaId);
            } else {
                $this->reponerIngrediente($it, $cantidad, $seleccionSabores, $ventaId);
            }
        }
    }

    /**
     * Repone stock de un ítem de receta. Para ingrediente_generico, si la venta que se
     * anula traía sabores elegidos para ese genérico, repone exacto a esos sabores
     * (proporcional, igual criterio que descontarIngrediente()). Si no hay selección
     * (ej. recetas sin selector de sabor, como "Milkshake"), no se puede saber con
     * certeza de qué variante puntual salió — se repone a la variante actualizada más
     * recientemente, que suele ser la que se sigue comprando/usando.
     */
    private function reponerIngrediente(array $it, float $cantidad, array $seleccionSabores = [], ?int $ventaId = null): void
    {
        if (!empty($it['materia_prima_id'])) {
            $this->db->prepare("UPDATE materias_primas SET stock_actual=stock_actual+:c WHERE id=:id")
                     ->execute([':c' => $cantidad, ':id' => $it['materia_prima_id']]);
            $this->_registrarMovimientoStock('materia_prima', (int)$it['materia_prima_id'], $cantidad, 'Reposición por anulación de venta', $ventaId);
            return;
        }
        if (!empty($it['insumo_id'])) {
            $this->db->prepare("UPDATE insumos SET stock_actual=stock_actual+:c WHERE id=:id")
                     ->execute([':c' => $cantidad, ':id' => $it['insumo_id']]);
            $this->_registrarMovimientoStock('insumo', (int)$it['insumo_id'], $cantidad, 'Reposición por anulación de venta', $ventaId);
            return;
        }
        if (!empty($it['ingrediente_generico_id'])) {
            $elegidos = $this->_seleccionParaGenerico((int)$it['ingrediente_generico_id'], $seleccionSabores);
            $pesoTotal = array_sum(array_column($elegidos, 'peso'));
            if ($elegidos && $pesoTotal > 0) {
                $restante = $cantidad;
                $ultimoIdx = count($elegidos) - 1;
                foreach ($elegidos as $i => $s) {
                    $porcion = ($i === $ultimoIdx) ? $restante : round($cantidad * $s['peso'] / $pesoTotal, 4);
                    $this->db->prepare("UPDATE materias_primas SET stock_actual = stock_actual + ? WHERE id = ?")
                             ->execute([$porcion, $s['materia_prima_id']]);
                    $this->_registrarMovimientoStock('materia_prima', (int)$s['materia_prima_id'], $porcion, 'Reposición por anulación de venta (sabor elegido)', $ventaId);
                    $restante -= $porcion;
                }
                return;
            }

            $variante = $this->queryOne(
                "SELECT id FROM materias_primas WHERE ingrediente_generico_id = ? AND eliminado = 0 ORDER BY updated_at DESC LIMIT 1",
                [$it['ingrediente_generico_id']]
            );
            if ($variante) {
                $this->db->prepare("UPDATE materias_primas SET stock_actual = stock_actual + ? WHERE id = ?")
                         ->execute([$cantidad, $variante['id']]);
                $this->_registrarMovimientoStock('materia_prima', (int)$variante['id'], $cantidad, 'Reposición por anulación de venta', $ventaId);
            }
        }
    }

    public function planificarOrden(int $recetaId, float $cantidad, int $usuarioId): int
    {
        $this->db->prepare(
            "INSERT INTO ordenes_produccion (receta_id, usuario_id, cantidad_producida, estado)
             VALUES (:rid, :uid, :cant, 'planificada')"
        )->execute([':rid' => $recetaId, ':uid' => $usuarioId, ':cant' => $cantidad]);
        return (int)$this->db->lastInsertId();
    }

    public function cambiarEstadoOrden(int $ordenId, string $nuevoEstado, int $usuarioId): array
    {
        $orden = $this->queryOne('SELECT * FROM ordenes_produccion WHERE id = ?', [$ordenId]);
        if (!$orden) return ['ok' => false, 'error' => 'Orden no encontrada.'];

        $validos = ['planificada', 'en_proceso', 'completada', 'anulada'];
        if (!in_array($nuevoEstado, $validos, true)) {
            return ['ok' => false, 'error' => 'Estado inválido.'];
        }

        if ($nuevoEstado === 'completada' && $orden['estado'] !== 'completada') {
            $receta = $this->findByIdConDetalle((int)$orden['receta_id']);
            if (!$receta) return ['ok' => false, 'error' => 'Receta no encontrada.'];

            $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
            $cantProd    = (float)$orden['cantidad_producida'] / $rendimiento;

            $this->db->beginTransaction();
            try {
                $faltantes = $this->verificarFaltantes($receta['detalle'], $cantProd);
                if ($faltantes) {
                    $this->db->rollBack();
                    return ['ok' => false, 'faltantes' => $faltantes];
                }

                $this->descontarDetalle($receta['detalle'], $cantProd);

                if ($receta['producto_id']) {
                    $this->db->prepare("UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id")
                             ->execute([':c' => $orden['cantidad_producida'], ':id' => $receta['producto_id']]);
                }

                $this->db->prepare(
                    "UPDATE ordenes_produccion SET estado='completada', usuario_id=:uid, updated_at=NOW() WHERE id=:id"
                )->execute([':uid' => $usuarioId, ':id' => $ordenId]);

                $this->db->commit();
                return ['ok' => true];
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        $this->db->prepare(
            "UPDATE ordenes_produccion SET estado=:est, updated_at=NOW() WHERE id=:id"
        )->execute([':est' => $nuevoEstado, ':id' => $ordenId]);

        return ['ok' => true];
    }

    public function calcularCosto(int $recetaId, array $visitados = []): float
    {
        $visitados[] = $recetaId;

        $costoBase = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(
                rd.cantidad * COALESCE(
                    mp.costo_unitario * (1 + mp.desvio_pct/100),
                    ins.costo_unitario, gen.costo, rd.ingrediente_libre_costo_unitario, 0
                )
                * (1 + rd.merma_pct/100)
             ),0)
             FROM receta_detalle rd
             LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id
             LEFT JOIN insumos ins        ON ins.id = rd.insumo_id
             LEFT JOIN (
                 SELECT ingrediente_generico_id,
                        CASE WHEN SUM(stock_actual) > 0.0001
                             THEN SUM((costo_unitario * (1 + desvio_pct/100)) * stock_actual) / SUM(stock_actual)
                             ELSE AVG(costo_unitario * (1 + desvio_pct/100))
                        END AS costo
                 FROM materias_primas
                 WHERE eliminado = 0 AND ingrediente_generico_id IS NOT NULL
                 GROUP BY ingrediente_generico_id
             ) gen ON gen.ingrediente_generico_id = rd.ingrediente_generico_id
             WHERE rd.receta_id = :id",
            [':id' => $recetaId]
        );

        // Costo de los ingredientes que son a su vez otra receta (no sale del SQL de
        // arriba: sus joins a mp/ins/gen dan NULL, así que ya suman 0 ahí). Se recorre
        // recursivamente, con guarda para no entrar en un ciclo A→B→A si alguno se
        // coló sin pasar por la validación de guardado.
        $subRecetas = $this->queryAll(
            "SELECT rd.cantidad, rd.merma_pct, rd.receta_ingrediente_id, r2.rendimiento
             FROM receta_detalle rd
             JOIN recetas r2 ON r2.id = rd.receta_ingrediente_id
             WHERE rd.receta_id = :id AND rd.tipo_ingrediente = 'receta'",
            [':id' => $recetaId]
        );
        $costoSub = 0.0;
        foreach ($subRecetas as $s) {
            $childId = (int)$s['receta_ingrediente_id'];
            if (in_array($childId, $visitados, true)) continue;
            $costoUnitario = $this->calcularCosto($childId, $visitados) / max((float)$s['rendimiento'], 0.001);
            $costoSub += (float)$s['cantidad'] * $costoUnitario * (1 + (float)$s['merma_pct'] / 100);
        }

        return $costoBase + $costoSub;
    }

    /**
     * Costo REAL de una venta puntual de $cantidadVendida unidades de la receta
     * $recetaId — a diferencia de calcularCosto() (que usa el promedio ponderado por
     * stock de TODAS las variantes de cada ingrediente genérico, ej. todos los sabores
     * de helado), acá se usa el costo del sabor/variante puntual que el cliente eligió
     * en esta venta (ver VentaModel::_seleccionSabores()) en las líneas de
     * ingrediente_generico. El resto de la receta (envase, insumos, etc.) cuesta igual
     * que en calcularCosto(). Pensado para guardarse como snapshot inmutable en
     * ventas_detalle.costo_mp_real/costo_ingrediente_generico al momento de la venta —
     * para costeo de menú/recetas en general sigue estando calcularCosto()/precio_costo.
     * Devuelve ['total' => costo completo, 'generico' => cuánto de ese total sale de
     * líneas de ingrediente_generico] — 'total' - 'generico' = materia prima/insumo directa.
     */
    public function calcularCostoReal(int $recetaId, float $cantidadVendida, array $seleccionSabores = []): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return ['total' => 0.0, 'generico' => 0.0];
        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        return $this->calcularCostoDetalle($receta['detalle'], $cantidadVendida / $rendimiento, $seleccionSabores);
    }

    /**
     * Costo real de un detalle de receta para $factor "producciones" — espejo de
     * descontarDetalle()/descontarIngrediente() (misma recursión de sub-recetas, mismo
     * reparto proporcional por $seleccionSabores en ingrediente_generico) pero acumulando
     * costo (total y la porción de ingrediente_generico) en vez de descontar stock.
     */
    private function calcularCostoDetalle(array $detalle, float $factor, array $seleccionSabores = []): array
    {
        $total = 0.0;
        $generico = 0.0;
        foreach ($detalle as $it) {
            $cantidad = (float)$it['cantidad'] * $factor * (1 + (float)$it['merma_pct'] / 100);

            if (!empty($it['receta_ingrediente_id'])) {
                $sub = $this->findByIdConDetalle((int)$it['receta_ingrediente_id']);
                if (!$sub) continue;
                $rendimientoSub = max((float)($sub['rendimiento'] ?? 1), 0.001);
                $r = $this->calcularCostoDetalle($sub['detalle'], $cantidad / $rendimientoSub, $seleccionSabores);
                $total += $r['total'];
                $generico += $r['generico'];
                continue;
            }

            if (!empty($it['ingrediente_generico_id'])) {
                $elegidos = $this->_seleccionParaGenerico((int)$it['ingrediente_generico_id'], $seleccionSabores);
                $pesoTotal = array_sum(array_column($elegidos, 'peso'));
                if ($elegidos && $pesoTotal > 0) {
                    $costoPonderado = 0.0;
                    foreach ($elegidos as $s) {
                        $costoVariante = (float)$this->queryScalar(
                            "SELECT costo_unitario * (1 + desvio_pct/100) FROM materias_primas WHERE id = ?",
                            [$s['materia_prima_id']]
                        );
                        $costoPonderado += $costoVariante * ($s['peso'] / $pesoTotal);
                    }
                    $total += $cantidad * $costoPonderado;
                    $generico += $cantidad * $costoPonderado;
                    continue;
                }
                // Sin selección puntual para este genérico (ej. receta sin selector de
                // sabor, como "Milkshake"): mismo promedio ponderado que calcularCosto().
                $costoLinea = $cantidad * (float)$it['costo_unitario'] * (1 + (float)$it['desvio_pct'] / 100);
                $total += $costoLinea;
                $generico += $costoLinea;
                continue;
            }

            // materia_prima / insumo directo: ya viene resuelto en $it desde findByIdConDetalle().
            $total += $cantidad * (float)$it['costo_unitario'] * (1 + (float)$it['desvio_pct'] / 100);
        }
        return ['total' => $total, 'generico' => $generico];
    }

    /**
     * Re-deriva costo_mp_real/costo_ingrediente_generico de ventas YA REGISTRADAS en un
     * rango de fechas, para cuando el costo se calculó mal en su momento (bug de fórmula
     * o de propagación, ej. el de cantidad_contenida en productos, o el de edición de
     * materia prima sin propagar) y quedó frío en la venta — ver reportes/margen-ventas.php,
     * que muestra ese costo "congelado" tal cual se guardó.
     *
     * OJO — esto corrige el ERROR DE CÁLCULO, no reconstruye una máquina del tiempo de
     * precios: usa el costo ACTUAL de cada materia prima (no se guarda un historial de
     * precio con granularidad diaria por ítem), aplicado sobre la receta y el sabor/
     * variante que REALMENTE se vendió en cada línea (reconstruido desde
     * ventas_detalle_opciones, igual que en el momento real de la venta). Cuanto más
     * vieja la venta, menos representa el costo actual el que tenía ese día — para
     * ventanas largas conviene revisar el resultado, no solo confiar a ciegas.
     *
     * Cubre TANTO productos con receta propia (recetas.producto_id — se recalcula con
     * calcularCostoReal(), sabor incluido) COMO de reventa simple sin receta (se recalcula
     * como cantidad × precio_costo ACTUAL del producto, igual que hace
     * VentaModel::registrar() en el momento real de la venta — ver el bug real que motivó
     * esto: "Macarons" tenía costo_mp_real congelado en el precio de la CAJA entera, por el
     * mismo bug de cantidad_contenida en compras de producto ya corregido en
     * FacturaCompraModel, pero el número viejo seguía guardado en cada venta pasada).
     */
    public function recalcularCostosVentasEnPeriodo(string $desde, string $hasta): array
    {
        $filas = $this->queryAll(
            "SELECT vd.id AS venta_detalle_id, vd.producto_id, vd.cantidad,
                    r.id AS receta_id, p.precio_costo AS producto_precio_costo
             FROM ventas_detalle vd
             JOIN ventas v      ON v.id = vd.venta_id
             JOIN productos p   ON p.id = vd.producto_id
             LEFT JOIN recetas r ON r.producto_id = vd.producto_id AND r.eliminado = 0
             WHERE v.eliminado = 0 AND v.estado = 'completada'
               AND DATE(v.created_at) BETWEEN :desde AND :hasta",
            [':desde' => $desde, ':hasta' => $hasta]
        );

        foreach ($filas as $f) {
            if ($f['receta_id']) {
                // ingrediente_generico_id vive en materias_primas, no en opciones — mismo
                // join que ya usa VentaModel::_obtenerOpcion() para resolverlo en el
                // momento real de la venta.
                $opciones = $this->queryAll(
                    "SELECT o.materia_prima_id, mp.ingrediente_generico_id, vdo.cantidad_elegida
                     FROM ventas_detalle_opciones vdo
                     JOIN opciones o ON o.id = vdo.opcion_id
                     LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
                     WHERE vdo.venta_detalle_id = ?",
                    [(int) $f['venta_detalle_id']]
                );
                $seleccionSabores = [];
                foreach ($opciones as $op) {
                    if (!empty($op['materia_prima_id']) && !empty($op['ingrediente_generico_id'])) {
                        $seleccionSabores[] = [
                            'materia_prima_id'        => (int) $op['materia_prima_id'],
                            'ingrediente_generico_id' => (int) $op['ingrediente_generico_id'],
                            'peso'                    => (float) $op['cantidad_elegida'],
                        ];
                    }
                }

                $costoReal = $this->calcularCostoReal((int) $f['receta_id'], (float) $f['cantidad'], $seleccionSabores);
                $costoTotal = $costoReal['total'];
                $costoGenerico = $costoReal['generico'];
            } else {
                // Reventa simple: mismo cálculo que el "else" de VentaModel::registrar().
                $costoTotal = (float) $f['cantidad'] * (float) $f['producto_precio_costo'];
                $costoGenerico = 0.0;
            }

            $this->query(
                "UPDATE ventas_detalle SET costo_mp_real = ?, costo_ingrediente_generico = ? WHERE id = ?",
                [$costoTotal, $costoGenerico, (int) $f['venta_detalle_id']]
            );
        }

        return ['filas_totales' => count($filas), 'actualizadas' => count($filas)];
    }

    /**
     * Desglose de cómo se llegó al costo_unitario actual de un ingrediente de
     * receta, para el botón "¿cómo se calculó?" de cada fila en el formulario.
     * - ingrediente_generico: el costo mostrado es un promedio ponderado por
     *   stock entre TODAS las materias primas mapeadas a ese genérico (ver
     *   misma lógica en findByIdConDetalle/calcularCosto) — acá se abre esa
     *   lista para que se vea qué materia prima está empujando el promedio.
     * - materia_prima / insumo: el costo es el Costo Promedio Ponderado que
     *   se va actualizando en cada compra (FacturaCompraModel::actualizarStock);
     *   acá se muestra el historial de compras que lo fue formando.
     */
    public function detalleCosto(string $tipo, int $id): array
    {
        if ($tipo === 'ingrediente_generico') {
            $generico = $this->queryOne('SELECT nombre FROM ingredientes_genericos WHERE id = :id', [':id' => $id]);

            $mps = $this->queryAll(
                "SELECT mp.id, mp.nombre, mp.costo_unitario, mp.desvio_pct, mp.stock_actual,
                        um.abreviatura AS unidad
                 FROM materias_primas mp
                 LEFT JOIN unidades_medida um ON um.id = mp.unidad_medida_id
                 WHERE mp.ingrediente_generico_id = :id AND mp.eliminado = 0
                 ORDER BY mp.stock_actual DESC",
                [':id' => $id]
            );

            $stockTotal = array_sum(array_map(fn($m) => (float)$m['stock_actual'], $mps));
            $ponderaPorStock = $stockTotal > 0.0001;
            $cantidad = count($mps);
            foreach ($mps as &$mp) {
                $mp['costo_con_desvio'] = (float)$mp['costo_unitario'] * (1 + (float)$mp['desvio_pct'] / 100);
                $mp['peso_pct'] = $ponderaPorStock
                    ? ((float)$mp['stock_actual'] / $stockTotal) * 100
                    : ($cantidad ? 100 / $cantidad : 0);
            }
            unset($mp);

            return [
                'tipo'              => 'ingrediente_generico',
                'nombre'            => $generico['nombre'] ?? '',
                'pondera_por_stock' => $ponderaPorStock,
                'materias_primas'   => $mps,
            ];
        }

        $tabla   = $tipo === 'insumo' ? 'insumos' : 'materias_primas';
        $columna = $tipo === 'insumo' ? 'insumo_id' : 'materia_prima_id';

        $item = $this->queryOne(
            "SELECT t.nombre, t.costo_unitario, t.stock_actual, um.abreviatura AS unidad
             FROM {$tabla} t LEFT JOIN unidades_medida um ON um.id = t.unidad_medida_id
             WHERE t.id = :id",
            [':id' => $id]
        );

        $compras = $this->queryAll(
            "SELECT fc.fecha, fc.nro_comprobante, p.razon_social AS proveedor,
                    fcd.cantidad, fcd.precio_unitario
             FROM facturas_compra_detalle fcd
             JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE fcd.tipo_item = :tipo AND fcd.{$columna} = :id
               AND fc.estado != 'anulada' AND fc.eliminado = 0
             ORDER BY fc.fecha DESC
             LIMIT 20",
            [':tipo' => $tipo, ':id' => $id]
        );

        return [
            'tipo'           => $tipo,
            'nombre'         => $item['nombre'] ?? '',
            'costo_unitario' => (float)($item['costo_unitario'] ?? 0),
            'stock_actual'   => (float)($item['stock_actual'] ?? 0),
            'unidad'         => $item['unidad'] ?? '',
            'compras'        => $compras,
        ];
    }

    /** IDs de recetas que usan un ingrediente genérico puntual — para recalcular tras una compra */
    public function findIdsPorGenerico(int $genericoId): array
    {
        return array_column(
            $this->queryAll("SELECT DISTINCT receta_id FROM receta_detalle WHERE ingrediente_generico_id = ?", [$genericoId]),
            'receta_id'
        );
    }

    /** IDs de recetas que usan una materia prima puntual DIRECTAMENTE (no vía ingrediente genérico) */
    public function findIdsPorMateriaPrima(int $materiaPrimaId): array
    {
        return array_column(
            $this->queryAll(
                "SELECT DISTINCT receta_id FROM receta_detalle WHERE materia_prima_id = ? AND tipo_ingrediente = 'materia_prima'",
                [$materiaPrimaId]
            ),
            'receta_id'
        );
    }

    /** IDs de recetas que usan un insumo puntual directamente */
    public function findIdsPorInsumo(int $insumoId): array
    {
        return array_column(
            $this->queryAll(
                "SELECT DISTINCT receta_id FROM receta_detalle WHERE insumo_id = ? AND tipo_ingrediente = 'insumo'",
                [$insumoId]
            ),
            'receta_id'
        );
    }

    /** IDs de recetas que usan $recetaId como sub-receta ingrediente — para propagar el recálculo hacia arriba */
    public function findIdsQueUsanReceta(int $recetaId): array
    {
        return array_column(
            $this->queryAll(
                "SELECT DISTINCT receta_id FROM receta_detalle WHERE receta_ingrediente_id = ? AND tipo_ingrediente = 'receta'",
                [$recetaId]
            ),
            'receta_id'
        );
    }

    /**
     * Se llama después de que una compra actualizó el costo de una materia prima o
     * insumo (directo, o de una variante de ingrediente genérico — da igual, ambos
     * casos pasan por FacturaCompraModel::actualizarStock()). Recalcula el costo de
     * $recetaIdsRaiz y, transitivamente, de cualquier receta que las use como
     * sub-receta ingrediente; graba el cambio en recetas_historial_costo; sincroniza
     * productos.precio_costo (tanto el "producto final" 1:1 como los vinculados por
     * la tabla pivote producto_receta); y avisa por push si el margen de algún
     * producto cayó más que el umbral configurado.
     */
    public function propagarCambioCosto(array $recetaIdsRaiz, ?int $facturaCompraId = null, string $motivo = 'Recálculo por compra'): array
    {
        if (!$recetaIdsRaiz) return ['recetas_afectadas' => 0];

        // BFS: sumar cualquier receta que use (directa o transitivamente) alguna de
        // las raíz como sub-receta, para que el recálculo también llegue a la receta
        // "padre" cuando lo que cambió fue el costo de una sub-receta anidada.
        $vistas     = [];
        $pendientes = array_map('intval', $recetaIdsRaiz);
        while ($pendientes) {
            $id = array_shift($pendientes);
            if (isset($vistas[$id])) continue;
            $vistas[$id] = true;
            foreach ($this->findIdsQueUsanReceta($id) as $padreId) {
                $padreId = (int)$padreId;
                if (!isset($vistas[$padreId])) $pendientes[] = $padreId;
            }
        }
        $recetaIds = array_keys($vistas);

        $preciosCostoAnteriores = []; // producto_id => precio_costo antes de sobreescribir, para el aviso de margen
        $deltasPorReceta        = [];

        foreach ($recetaIds as $recetaId) {
            $costoNuevo = $this->calcularCosto($recetaId);

            $ultimo = $this->queryOne(
                "SELECT costo_nuevo FROM recetas_historial_costo WHERE receta_id = ? ORDER BY id DESC LIMIT 1",
                [$recetaId]
            );
            // Sin historial previo no hay "antes" real que comparar — se toma el
            // valor actual como línea de base, sin disparar aviso todavía.
            $costoAnterior = $ultimo ? (float) $ultimo['costo_nuevo'] : $costoNuevo;

            $this->query(
                "INSERT INTO recetas_historial_costo (receta_id, costo_anterior, costo_nuevo, factura_compra_id, motivo)
                 VALUES (?, ?, ?, ?, ?)",
                [$recetaId, $costoAnterior, $costoNuevo, $facturaCompraId, $motivo]
            );

            $delta = $costoNuevo - $costoAnterior;
            if (abs($delta) > 0.005) {
                $deltasPorReceta[$recetaId] = $delta;
            }

            // Sincronizar productos.precio_costo del "producto final" 1:1 (recetas.producto_id)
            $receta = $this->queryOne("SELECT producto_id, rendimiento FROM recetas WHERE id = ?", [$recetaId]);
            if ($receta && $receta['producto_id']) {
                $productoId  = (int)$receta['producto_id'];
                $rendimiento = max((float)$receta['rendimiento'], 0.001);
                if (!array_key_exists($productoId, $preciosCostoAnteriores)) {
                    $preciosCostoAnteriores[$productoId] = (float)($this->queryScalar(
                        'SELECT precio_costo FROM productos WHERE id = ?', [$productoId]
                    ) ?: 0);
                }
                $this->query('UPDATE productos SET precio_costo = ? WHERE id = ?', [$costoNuevo / $rendimiento, $productoId]);
            }
        }

        // Productos vinculados vía la tabla pivote producto_receta (pueden sumar varias recetas)
        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        $productoModel = new ProductoModel();
        foreach ($productoModel->productosPorRecetas($recetaIds) as $productoId) {
            if (!array_key_exists($productoId, $preciosCostoAnteriores)) {
                $preciosCostoAnteriores[$productoId] = (float)($this->queryScalar(
                    'SELECT precio_costo FROM productos WHERE id = ?', [$productoId]
                ) ?: 0);
            }
        }
        $productoModel->sincronizarPrecioCostoPorPivote($recetaIds);

        // Cascada a promos: si alguno de estos productos es a su vez componente de una promo
        // (ver docs/promos-diseno.md), el costo de esa promo también quedó desactualizado.
        $productoModel->sincronizarPromosPorComponente(array_keys($preciosCostoAnteriores));

        if ($deltasPorReceta) {
            $this->avisarMargenAfectado($preciosCostoAnteriores);
        }

        return ['recetas_afectadas' => count($deltasPorReceta)];
    }

    /** Para cada producto cuyo precio_costo se acaba de sincronizar, avisa por push si el margen cayó más que el umbral configurado */
    private function avisarMargenAfectado(array $preciosCostoAnteriores): void
    {
        if (!$preciosCostoAnteriores) return;

        require_once BASE_PATH . '/app/Models/ConfiguracionFiscalModel.php';
        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';

        $config = (new ConfiguracionFiscalModel())->obtener();
        $umbral = isset($config['umbral_alerta_margen']) && $config['umbral_alerta_margen'] !== null
            ? (float) $config['umbral_alerta_margen']
            : 5.0;

        $pushModel     = new PushSubscriptionModel();
        $suscripciones = null; // se cargan solo si hace falta avisar de verdad, evita la consulta si no hay caídas

        foreach ($preciosCostoAnteriores as $productoId => $costoAnterior) {
            $producto = $this->queryOne('SELECT nombre, precio_venta, precio_costo FROM productos WHERE id = ? AND eliminado = 0', [$productoId]);
            if (!$producto || (float) $producto['precio_venta'] <= 0) continue;

            $precioVenta     = (float) $producto['precio_venta'];
            $costoNuevoTotal = (float) $producto['precio_costo'];

            $margenNuevo    = ($precioVenta - $costoNuevoTotal) / $precioVenta * 100;
            $margenAnterior = ($precioVenta - $costoAnterior)   / $precioVenta * 100;
            $caidaPuntos    = $margenAnterior - $margenNuevo;

            if ($caidaPuntos < $umbral) continue;

            if ($suscripciones === null) $suscripciones = $pushModel->findSuscripcionesAdmins();
            if (!$suscripciones || !VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) continue;

            require_once BASE_PATH . '/core/WebPush.php';
            $titulo = 'Cayó el margen de ' . $producto['nombre'];
            $cuerpo = sprintf('Margen: %.1f%% → %.1f%% por una compra reciente.', $margenAnterior, $margenNuevo);
            $url    = rtrim(BASE_URL, '/') . '/productos';
            $payload = json_encode([
                'title' => $titulo,
                'body'  => $cuerpo,
                'url'   => $url,
                'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
            ]);

            (new NotificacionModel())->crearParaSuscripciones($suscripciones, $titulo, $cuerpo, $url, 'margen_bajo');
            foreach ($suscripciones as $sub) {
                try {
                    $res = WebPush::enviar(
                        ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                        $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                    );
                    if (in_array($res['status'], [404, 410], true)) {
                        $pushModel->eliminarPorId((int) $sub['id']);
                    }
                } catch (\Throwable $e) {
                    logApp('Error enviando push de margen para producto #' . $productoId . ': ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }

    public function findAllOrdenes(array $filtros = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]         = 'op.created_at >= :desde';
            $params[':desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where[]         = 'op.created_at <= :hasta';
            $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['receta_id'])) {
            $where[]           = 'op.receta_id = :rid';
            $params[':rid']    = (int)$filtros['receta_id'];
        }
        if (!empty($filtros['estado'])) {
            $where[]           = 'op.estado = :est';
            $params[':est']    = $filtros['estado'];
        }

        return $this->queryAll(
            'SELECT op.*, r.nombre AS receta_nombre, p.nombre AS producto_nombre,
                    u.nombre AS usuario_nombre
             FROM ordenes_produccion op
             JOIN recetas r ON r.id = op.receta_id
             LEFT JOIN productos p ON p.id = r.producto_id
             LEFT JOIN usuarios u ON u.id = op.usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY op.created_at DESC',
            $params
        );
    }

    public function buscarIngredientes(string $q, ?int $excluirId = null, bool $incluirBorradores = false): array
    {
        $like = "%$q%";
        $mp   = $this->queryAll(
            "SELECT id, nombre, codigo, 'materia_prima' AS tipo, stock_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
             FROM materias_primas WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q OR codigo LIKE :q2)
             LIMIT 15",
            [':q' => $like, ':q2' => $like]
        );
        $ins  = $this->queryAll(
            "SELECT id, nombre, codigo, 'insumo' AS tipo, stock_actual,
                    (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
             FROM insumos WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q OR codigo LIKE :q2)
             LIMIT 15",
            [':q' => $like, ':q2' => $like]
        );
        $gen  = $this->queryAll(
            "SELECT ig.id, ig.nombre, NULL AS codigo, 'ingrediente_generico' AS tipo,
                    (SELECT SUM(stock_actual) FROM materias_primas WHERE ingrediente_generico_id = ig.id AND eliminado = 0) AS stock_actual,
                    um.abreviatura AS unidad
             FROM ingredientes_genericos ig
             LEFT JOIN unidades_medida um ON um.id = ig.unidad_medida_id
             WHERE ig.eliminado = 0 AND ig.nombre LIKE :q
             LIMIT 15",
            [':q' => $like]
        );
        $rec  = $this->queryAll(
            "SELECT r.id, r.nombre, NULL AS codigo, 'receta' AS tipo, NULL AS stock_actual,
                    r.unidad_rendimiento AS unidad
             FROM recetas r
             WHERE r.eliminado = 0 AND r.activo = 1 AND r.nombre LIKE :q"
                . ($incluirBorradores ? '' : ' AND r.es_borrador = 0')
                . ($excluirId ? ' AND r.id != :excluir' : '') . "
             LIMIT 15",
            $excluirId ? [':q' => $like, ':excluir' => $excluirId] : [':q' => $like]
        );
        return array_merge($gen, $rec, $mp, $ins);
    }

    // ── Simulador de Recetas (recetas borrador para costear productos nuevos) ───────

    public function findBorradores(): array
    {
        return $this->queryAll(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM receta_detalle rd WHERE rd.receta_id = r.id) AS cant_ingredientes,
                    (SELECT COUNT(*) FROM receta_detalle rd WHERE rd.receta_id = r.id AND rd.tipo_ingrediente = 'ingrediente_libre') AS cant_ingredientes_libres
             FROM recetas r
             WHERE r.eliminado = 0 AND r.es_borrador = 1
             ORDER BY r.nombre"
        );
    }

    /**
     * Convierte un borrador ya "limpio" (sin ingredientes inventados) en un producto real:
     * crea el producto y lo enlaza como "Producto final" de esta receta (recetas.producto_id),
     * y baja el flag es_borrador. Si todavía quedan ingredientes inventados, no crea nada y
     * devuelve la lista para que la pantalla le pida al usuario reemplazarlos primero.
     *
     * precio_costo se siembra acá mismo con calcularCosto()/rendimiento — el mecanismo que
     * normalmente mantiene sincronizado productos.precio_costo con su receta
     * (propagarCambioCosto()) solo se dispara cuando cambia el costo de un ingrediente por
     * una COMPRA nueva; como este producto recién se está creando, sin este seed quedaría en
     * precio_costo=0 hasta la primera compra que toque alguno de sus ingredientes.
     */
    public function promoverAProducto(int $recetaId, array $datosProducto): array
    {
        $pendientes = array_column(
            $this->queryAll(
                "SELECT ingrediente_libre_nombre FROM receta_detalle WHERE receta_id = :id AND tipo_ingrediente = 'ingrediente_libre'",
                [':id' => $recetaId]
            ),
            'ingrediente_libre_nombre'
        );
        if ($pendientes) {
            return ['ok' => false, 'pendientes' => $pendientes];
        }

        $rendimiento = max((float)($this->queryScalar('SELECT rendimiento FROM recetas WHERE id = ?', [$recetaId]) ?: 1), 0.001);
        $datosProducto['precio_costo'] = $this->calcularCosto($recetaId) / $rendimiento;

        $productoModel = new ProductoModel();
        $this->db->beginTransaction();
        try {
            $productoId = $productoModel->insert($datosProducto);
            $this->update($recetaId, ['producto_id' => $productoId, 'es_borrador' => 0]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['ok' => true, 'producto_id' => $productoId];
    }

    /**
     * "Explota" una receta a la cantidad acumulada de cada ingrediente que hace falta
     * para producir $cantidadProducto unidades del producto que la usa — mismo recorrido
     * recursivo que descontarDetalle()/verificarFaltantesDetallado() (incluye sub-recetas
     * anidadas), pero sin tocar stock ni compararlo: solo suma. Usado por Proyecciones
     * para estimar consumo futuro de materia prima/insumo sin que la venta haya ocurrido.
     * Los ingredientes de tipo ingrediente_generico quedan acumulados bajo su propia clave
     * ("ingrediente_generico:ID") — resolverlos a una materia prima puntual es
     * responsabilidad de quien llama (ver ProyeccionModel::proyectarConsumoMateriaPrima()).
     */
    public function explotarConsumo(int $recetaId, float $cantidadProducto): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) return [];

        $rendimiento = max((float)($receta['rendimiento'] ?? 1), 0.001);
        $acumulado   = [];
        $this->explotarDetalle($receta['detalle'], $cantidadProducto / $rendimiento, $acumulado);
        return $acumulado;
    }

    /** Recorrido recursivo usado por explotarConsumo() — acumula por referencia en $acumulado. */
    private function explotarDetalle(array $detalle, float $factor, array &$acumulado): void
    {
        foreach ($detalle as $it) {
            $necesario = (float)$it['cantidad'] * $factor * (1 + (float)$it['merma_pct'] / 100);

            if (!empty($it['receta_ingrediente_id'])) {
                $sub = $this->findByIdConDetalle((int)$it['receta_ingrediente_id']);
                if (!$sub) continue;
                $rendimiento = max((float)($sub['rendimiento'] ?? 1), 0.001);
                $this->explotarDetalle($sub['detalle'], $necesario / $rendimiento, $acumulado);
                continue;
            }

            $clave = match (true) {
                !empty($it['ingrediente_generico_id']) => 'ingrediente_generico:' . $it['ingrediente_generico_id'],
                !empty($it['materia_prima_id'])        => 'materia_prima:' . $it['materia_prima_id'],
                !empty($it['insumo_id'])                => 'insumo:' . $it['insumo_id'],
                default                                  => null,
            };
            if ($clave === null) continue;

            $acumulado[$clave] = ($acumulado[$clave] ?? 0) + $necesario;
        }
    }

    public function getProductosAsignados(int $recetaId): array
    {
        return $this->queryAll(
            'SELECT p.id, p.nombre, p.codigo, p.precio_venta
             FROM producto_receta pr
             JOIN productos p ON p.id = pr.producto_id
             WHERE pr.receta_id = :rid AND p.eliminado = 0
             ORDER BY p.nombre',
            [':rid' => $recetaId]
        );
    }

    /**
     * Vincula un producto a esta receta como "receta de costo" (tabla pivote
     * producto_receta). Mismo guardarraíl que ProductoModel::asignarReceta() — si
     * ESTA receta ya es el "Producto final" de ESE producto, el vínculo 1:1 ya
     * sincroniza precio_costo solo; agregarlo acá también lo pisaría con el mismo
     * cálculo repetido por la tabla pivote.
     */
    public function asignarProducto(int $recetaId, int $productoId): ?string
    {
        $productoFinalDe = $this->queryScalar('SELECT producto_id FROM recetas WHERE id = ?', [$recetaId]);
        if ($productoFinalDe !== null && (int)$productoFinalDe === $productoId) {
            return 'Este producto ya es el "Producto final" de esta receta — agregarlo también acá sería redundante (el costo ya se sincroniza solo).';
        }

        $this->db->prepare(
            'INSERT IGNORE INTO producto_receta (producto_id, receta_id) VALUES (:pid, :rid)'
        )->execute([':pid' => $productoId, ':rid' => $recetaId]);
        return null;
    }

    public function quitarProducto(int $recetaId, int $productoId): void
    {
        $this->db->prepare(
            'DELETE FROM producto_receta WHERE producto_id = :pid AND receta_id = :rid'
        )->execute([':pid' => $productoId, ':rid' => $recetaId]);
    }
}
