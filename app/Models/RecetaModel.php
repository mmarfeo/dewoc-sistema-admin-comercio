<?php
require_once BASE_PATH . '/app/Models/Model.php';

class RecetaModel extends Model
{
    protected string $table      = 'recetas';
    protected string $primaryKey = 'id';

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            "SELECT r.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    (SELECT COUNT(*) FROM receta_detalle rd WHERE rd.receta_id=r.id) AS cant_ingredientes
             FROM recetas r
             LEFT JOIN productos p ON p.id = r.producto_id
             WHERE r.eliminado = 0
             ORDER BY r.nombre"
        );
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

        $receta['detalle'] = $this->queryAll(
            "SELECT rd.*,
                    COALESCE(mp.nombre, ins.nombre) AS ingrediente_nombre,
                    COALESCE(mp.codigo, ins.codigo) AS ingrediente_codigo,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura) AS unidad,
                    COALESCE(mp.stock_actual, ins.stock_actual) AS stock_actual,
                    COALESCE(mp.costo_unitario, ins.costo_unitario) AS costo_unitario
             FROM receta_detalle rd
             LEFT JOIN materias_primas mp   ON mp.id  = rd.materia_prima_id
             LEFT JOIN insumos         ins  ON ins.id = rd.insumo_id
             LEFT JOIN unidades_medida um_mp  ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins ON um_ins.id = ins.unidad_medida_id
             WHERE rd.receta_id = :id
             ORDER BY rd.id",
            [':id' => $id]
        );

        return $receta;
    }

    public function guardar(array $cabecera, array $detalle, ?int $usuarioId = null): int
    {
        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $id = $this->insert($cabecera);
            $this->insertarDetalle($id, $detalle, $conn);
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
        $this->guardarVersion($id, $cabecera, $detalle, $usuarioId);
        return $id;
    }

    public function actualizarConDetalle(int $id, array $cabecera, array $detalle, ?int $usuarioId = null): void
    {
        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $this->update($id, $cabecera);
            $conn->prepare("DELETE FROM receta_detalle WHERE receta_id = :id")->execute([':id' => $id]);
            $this->insertarDetalle($id, $detalle, $conn);
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
        $this->guardarVersion($id, $cabecera, $detalle, $usuarioId);
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

    private function insertarDetalle(int $recetaId, array $detalle, \PDO $conn): void
    {
        $stmt = $conn->prepare(
            "INSERT INTO receta_detalle
                (receta_id, tipo_ingrediente, materia_prima_id, insumo_id, cantidad, merma_pct)
             VALUES (:rid, :tipo, :mp, :ins, :cant, :merma)"
        );
        foreach ($detalle as $it) {
            $stmt->execute([
                ':rid'  => $recetaId,
                ':tipo' => $it['tipo_ingrediente'],
                ':mp'   => $it['materia_prima_id'] ?: null,
                ':ins'  => $it['insumo_id'] ?: null,
                ':cant' => (float)$it['cantidad'],
                ':merma'=> (float)($it['merma_pct'] ?? 0),
            ]);
        }
    }

    /** Registra una producción: descuenta ingredientes y suma stock al producto */
    public function producir(int $recetaId, float $cantProducciones, int $usuarioId): array
    {
        $receta = $this->findByIdConDetalle($recetaId);
        if (!$receta) throw new \RuntimeException('Receta no encontrada.');

        $db   = Database::getInstance();
        $conn = $db->getConnection();
        $conn->beginTransaction();
        try {
            $faltantes = [];

            foreach ($receta['detalle'] as $it) {
                $necesario = $it['cantidad'] * $cantProducciones * (1 + $it['merma_pct'] / 100);
                if ((float)$it['stock_actual'] < $necesario) {
                    $faltantes[] = $it['ingrediente_nombre'] . " (necesita {$necesario}, hay {$it['stock_actual']})";
                }
            }
            if ($faltantes) {
                $conn->rollBack();
                return ['ok' => false, 'faltantes' => $faltantes];
            }

            // Descontar ingredientes
            foreach ($receta['detalle'] as $it) {
                $necesario = $it['cantidad'] * $cantProducciones * (1 + $it['merma_pct'] / 100);
                if ($it['materia_prima_id']) {
                    $conn->prepare("UPDATE materias_primas SET stock_actual=stock_actual-:c WHERE id=:id")
                         ->execute([':c' => $necesario, ':id' => $it['materia_prima_id']]);
                } elseif ($it['insumo_id']) {
                    $conn->prepare("UPDATE insumos SET stock_actual=stock_actual-:c WHERE id=:id")
                         ->execute([':c' => $necesario, ':id' => $it['insumo_id']]);
                }
            }

            // Sumar stock al producto terminado
            $cantFinal = $cantProducciones * (float)($receta['rendimiento'] ?? 1);
            if ($receta['producto_id']) {
                $conn->prepare("UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id")
                     ->execute([':c' => $cantFinal, ':id' => $receta['producto_id']]);
            }

            // Registrar orden de producción
            $conn->prepare(
                "INSERT INTO ordenes_produccion
                    (receta_id, usuario_id, cantidad_producida, estado, created_at, updated_at)
                 VALUES (:rid, :uid, :cant, 'completada', NOW(), NOW())"
            )->execute([':rid' => $recetaId, ':uid' => $usuarioId, ':cant' => $cantFinal]);

            $conn->commit();
            return ['ok' => true, 'producido' => $cantFinal];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function planificarOrden(int $recetaId, float $cantidad, int $usuarioId): int
    {
        $conn = Database::getInstance()->getConnection();
        $conn->prepare(
            "INSERT INTO ordenes_produccion (receta_id, usuario_id, cantidad_producida, estado)
             VALUES (:rid, :uid, :cant, 'planificada')"
        )->execute([':rid' => $recetaId, ':uid' => $usuarioId, ':cant' => $cantidad]);
        return (int)$conn->lastInsertId();
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

            $db   = Database::getInstance();
            $conn = $db->getConnection();
            $conn->beginTransaction();
            try {
                $faltantes = [];
                foreach ($receta['detalle'] as $it) {
                    $necesario = $it['cantidad'] * $cantProd * (1 + $it['merma_pct'] / 100);
                    if ((float)$it['stock_actual'] < $necesario) {
                        $faltantes[] = $it['ingrediente_nombre'] . " (necesita {$necesario}, hay {$it['stock_actual']})";
                    }
                }
                if ($faltantes) {
                    $conn->rollBack();
                    return ['ok' => false, 'faltantes' => $faltantes];
                }

                foreach ($receta['detalle'] as $it) {
                    $necesario = $it['cantidad'] * $cantProd * (1 + $it['merma_pct'] / 100);
                    if ($it['materia_prima_id']) {
                        $conn->prepare("UPDATE materias_primas SET stock_actual=stock_actual-:c WHERE id=:id")
                             ->execute([':c' => $necesario, ':id' => $it['materia_prima_id']]);
                    } elseif ($it['insumo_id']) {
                        $conn->prepare("UPDATE insumos SET stock_actual=stock_actual-:c WHERE id=:id")
                             ->execute([':c' => $necesario, ':id' => $it['insumo_id']]);
                    }
                }

                if ($receta['producto_id']) {
                    $conn->prepare("UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id")
                         ->execute([':c' => $orden['cantidad_producida'], ':id' => $receta['producto_id']]);
                }

                $conn->prepare(
                    "UPDATE ordenes_produccion SET estado='completada', usuario_id=:uid, updated_at=NOW() WHERE id=:id"
                )->execute([':uid' => $usuarioId, ':id' => $ordenId]);

                $conn->commit();
                return ['ok' => true];
            } catch (\Throwable $e) {
                $conn->rollBack();
                throw $e;
            }
        }

        Database::getInstance()->getConnection()->prepare(
            "UPDATE ordenes_produccion SET estado=:est, updated_at=NOW() WHERE id=:id"
        )->execute([':est' => $nuevoEstado, ':id' => $ordenId]);

        return ['ok' => true];
    }

    public function calcularCosto(int $recetaId): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(
                rd.cantidad * COALESCE(mp.costo_unitario, ins.costo_unitario, 0)
                * (1 + rd.merma_pct/100)
             ),0)
             FROM receta_detalle rd
             LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id
             LEFT JOIN insumos ins        ON ins.id = rd.insumo_id
             WHERE rd.receta_id = :id",
            [':id' => $recetaId]
        );
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

    public function buscarIngredientes(string $q): array
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
        return array_merge($mp, $ins);
    }
}
