<?php
require_once BASE_PATH . '/app/Models/Model.php';

class SolicitudModel extends Model
{
    protected string $table = 'solicitudes';

    /** Tablas concretas de stock por tipo de criterio/ítem (todas menos 'categoria' e 'ingrediente_generico') */
    private const TABLA_POR_TIPO = [
        'producto'      => 'productos',
        'materia_prima' => 'materias_primas',
        'insumo'        => 'insumos',
    ];

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['s.eliminado = 0'];
        $params = [];

        if (!empty($filtros['id'])) {
            $where[]        = 's.id = :id';
            $params[':id']  = $filtros['id'];
        }
        if (!empty($filtros['estado'])) {
            $where[]           = 's.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]         = 's.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['usuario_id'])) {
            // No solo "lo que YO pedí" (para cualquier tipo, no solo inventario): también
            // cuenta lo que me asignaron puntualmente, o lo que quedó "sin asignar" (abierto
            // a quien esté de turno) — en ese último caso, solo si YO tenía mi caja abierta
            // en el momento en que se creó la solicitud, no cualquiera con permiso de ver.
            // Sin esto, un empleado sin "solicitudes: editar" vería en su lista pedidos de
            // turnos en los que ni siquiera estaba trabajando.
            $where[] = "(s.usuario_id = :usuario_id
                         OR s.asignado_a = :usuario_id2
                         OR (s.asignado_a IS NULL AND EXISTS (
                                SELECT 1 FROM turnos t
                                WHERE t.eliminado = 0 AND t.usuario_id = :usuario_id3
                                  AND t.apertura_at <= s.created_at
                                  AND (t.cierre_at IS NULL OR t.cierre_at >= s.created_at)
                            )))";
            $params[':usuario_id']  = $filtros['usuario_id'];
            $params[':usuario_id2'] = $filtros['usuario_id'];
            $params[':usuario_id3'] = $filtros['usuario_id'];
        }

        $sql = "SELECT s.*, u.nombre AS creador_nombre, r.nombre AS resuelto_por_nombre,
                       a.nombre AS asignado_a_nombre
                FROM solicitudes s
                JOIN usuarios u ON u.id = s.usuario_id
                LEFT JOIN usuarios r ON r.id = s.resuelto_por
                LEFT JOIN usuarios a ON a.id = s.asignado_a
                WHERE " . implode(' AND ', $where) . "
                ORDER BY FIELD(s.estado,'pendiente','en_proceso','resuelta'),
                         FIELD(s.prioridad,'alta','media','baja'),
                         s.created_at DESC";

        return $this->queryAll($sql, $params);
    }

    /**
     * Próxima solicitud de inventario pendiente para el cartel de ventas/pos (ver
     * SolicitudesController::pendienteParaMi) — asignada puntualmente al usuario logueado,
     * o abierta a cualquiera (asignado_a NULL). A diferencia de findAllConDetalle(), NO
     * incluye lo que el usuario haya creado él mismo para otra persona (eso no es algo que
     * TENGA que ir a contar).
     */
    public function pendienteParaUsuario(int $usuarioId): array|false
    {
        return $this->queryOne(
            "SELECT s.*, u.nombre AS creador_nombre
             FROM solicitudes s
             JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.eliminado = 0 AND s.tipo = 'inventario'
               AND s.estado IN ('pendiente', 'en_proceso')
               AND (s.aplazado_hasta IS NULL OR s.aplazado_hasta <= NOW())
               AND (s.asignado_a = :uid OR s.asignado_a IS NULL)
             ORDER BY FIELD(s.prioridad,'alta','media','baja'), s.created_at ASC
             LIMIT 1",
            [':uid' => $usuarioId]
        );
    }

    /** Búsqueda combinada de productos + materias primas + insumos para el selector de la solicitud */
    public function buscarItems(string $q): array
    {
        $like = "%$q%";
        $productos = $this->queryAll(
            "SELECT id, nombre, codigo, 'producto' AS tipo
             FROM productos
             WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q OR codigo LIKE :q2)
             ORDER BY nombre LIMIT 15",
            [':q' => $like, ':q2' => $like]
        );
        $materias = $this->queryAll(
            "SELECT id, nombre, codigo, 'materia_prima' AS tipo
             FROM materias_primas
             WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q OR codigo LIKE :q2)
             ORDER BY nombre LIMIT 15",
            [':q' => $like, ':q2' => $like]
        );
        $insumos = $this->queryAll(
            "SELECT id, nombre, codigo, 'insumo' AS tipo
             FROM insumos
             WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q OR codigo LIKE :q2)
             ORDER BY nombre LIMIT 15",
            [':q' => $like, ':q2' => $like]
        );

        return array_merge($productos, $materias, $insumos);
    }

    /**
     * Ítems (producto/materia prima/insumo, según el tipo de la categoría) que tiene
     * asignados una categoría puntual — para el selector de "Agregar categoría" del
     * formulario de inventario: al elegir una categoría, se puede ver y tildar ítems
     * concretos en vez de mandar la categoría entera (ej. solo "Leche entera" dentro de
     * "Lácteos"). Mismo mapeo tipo→tabla y mismo filtro que resolverItemsParaInventario(),
     * más activo=1 (acá es para elegir qué pedir, no para expandir una solicitud ya hecha).
     */
    public function itemsPorCategoria(int $categoriaId): array
    {
        $tipoCategoria = (string)$this->queryScalar('SELECT tipo FROM categorias WHERE id = ?', [$categoriaId]);
        $tabla = self::TABLA_POR_TIPO[$tipoCategoria] ?? null;
        if (!$tabla) return [];

        $items = $this->queryAll(
            "SELECT id, nombre FROM `$tabla` WHERE categoria_id = ? AND eliminado = 0 AND activo = 1 ORDER BY nombre",
            [$categoriaId]
        );
        foreach ($items as &$it) $it['tipo'] = $tipoCategoria;
        unset($it);

        return $items;
    }

    public function cambiarEstado(int $id, string $estado, ?int $resueltoPor): void
    {
        $data = ['estado' => $estado];
        if ($estado === 'resuelta') {
            $data['resuelto_por'] = $resueltoPor;
            $data['resuelto_at']  = date('Y-m-d H:i:s');
        }
        $this->update($id, $data);
    }

    /** Aceptar una solicitud de inventario (pendiente → en_proceso) — mismo flujo que TareaAsignadaModel::aceptar(). */
    public function aceptar(int $id, int $usuarioId): void
    {
        $this->update($id, [
            'estado'         => 'en_proceso',
            'aceptado_por'   => $usuarioId,
            'aceptado_at'    => date('Y-m-d H:i:s'),
            'aplazado_hasta' => null,
        ]);
    }

    /** Rechazar una solicitud de inventario, con motivo obligatorio. */
    public function rechazar(int $id, int $usuarioId, string $motivo): void
    {
        $this->update($id, [
            'estado'         => 'rechazada',
            'resuelto_por'   => $usuarioId,
            'resuelto_at'    => date('Y-m-d H:i:s'),
            'motivo_rechazo' => $motivo,
            'aplazado_hasta' => null,
        ]);
    }

    /** Disponible desde pendiente o en_proceso — no cambia el estado, solo oculta el cartel hasta esa hora. */
    public function aplazar(int $id, int $minutos): void
    {
        $this->update($id, [
            'aplazado_hasta' => date('Y-m-d H:i:s', strtotime("+{$minutos} minutes")),
        ]);
    }

    /**
     * Crea una solicitud y, si trae criterios (tipo=inventario), su detalle en la
     * misma transacción. $criterios: array de ['tipo_criterio' => ..., 'referencia_id' => ...].
     */
    public function crear(array $data, array $criterios = []): int
    {
        $this->db->beginTransaction();
        try {
            $id = $this->insert($data);

            if ($criterios) {
                $stmt = $this->db->prepare(
                    "INSERT INTO solicitudes_inventario_detalle (solicitud_id, tipo_criterio, referencia_id)
                     VALUES (:sid, :tipo, :ref)"
                );
                foreach ($criterios as $c) {
                    $stmt->execute([
                        ':sid'  => $id,
                        ':tipo' => $c['tipo_criterio'],
                        ':ref'  => $c['referencia_id'],
                    ]);
                }
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Criterios de una solicitud de inventario ya creada, con el nombre resuelto para mostrar */
    public function getCriterios(int $solicitudId): array
    {
        $filas = $this->queryAll(
            "SELECT tipo_criterio, referencia_id FROM solicitudes_inventario_detalle WHERE solicitud_id = ?",
            [$solicitudId]
        );
        return $this->resolverNombresCriterios($filas);
    }

    /**
     * Resuelve el nombre de cada criterio {tipo_criterio, referencia_id} — se usa tanto
     * para mostrar criterios ya guardados (getCriterios()) como para armar el
     * item_nombre de una solicitud ANTES de insertarla (SolicitudesController::crear()).
     */
    public function resolverNombresCriterios(array $criteriosCrudos): array
    {
        $labelsTipo = [
            'categoria'             => 'Categoría',
            'producto'              => 'Producto',
            'materia_prima'         => 'Materia prima',
            'insumo'                => 'Insumo',
            'ingrediente_generico'  => 'Ingrediente genérico',
        ];
        // El mismo nombre de categoría puede existir para más de un tipo (ej. "Helados"
        // como categoría de Producto Y de Materia prima) — sin esto el label quedaba
        // "Categoría: Helados" sin decir a cuál de las dos se refiere.
        $labelsTipoCategoria = ['producto' => 'Producto', 'materia_prima' => 'Materia prima', 'insumo' => 'Insumo'];

        $filas = $criteriosCrudos;
        foreach ($filas as &$f) {
            $tabla = match ($f['tipo_criterio']) {
                'categoria'            => 'categorias',
                'ingrediente_generico' => 'ingredientes_genericos',
                default                => self::TABLA_POR_TIPO[$f['tipo_criterio']] ?? null,
            };
            $f['nombre'] = $tabla
                ? (string)$this->queryScalar("SELECT nombre FROM `$tabla` WHERE id = ?", [$f['referencia_id']])
                : '';
            $f['tipo_label'] = $labelsTipo[$f['tipo_criterio']] ?? $f['tipo_criterio'];

            if ($f['tipo_criterio'] === 'categoria') {
                $tipoCategoria = (string)$this->queryScalar(
                    "SELECT tipo FROM categorias WHERE id = ?", [$f['referencia_id']]
                );
                $f['tipo_label'] = 'Categoría de ' . ($labelsTipoCategoria[$tipoCategoria] ?? $tipoCategoria);
            }
        }
        unset($f);

        return $filas;
    }

    /**
     * Arma una etiqueta corta para item_nombre a partir de los criterios elegidos,
     * ej. "Categoría: Helados, Insumo: Servilletas" — así el listado de solicitudes
     * no necesita lógica especial para mostrar el tipo=inventario.
     */
    public function etiquetaCriterios(array $criteriosConNombre): string
    {
        $partes = array_map(fn($c) => $c['tipo_label'] . ': ' . $c['nombre'], $criteriosConNombre);
        return $partes ? implode(', ', $partes) : 'Inventario';
    }

    /**
     * Expande los criterios de una solicitud de inventario a pares concretos de
     * ítems de stock (tipo_item/item_id) — 'categoria' trae todos los ítems del tipo
     * que indique `categorias.tipo` con ese categoria_id; 'ingrediente_generico' trae
     * todas las materias_primas con ese ingrediente_generico_id; el resto es directo.
     * Devuelve un set (sin duplicados) de claves "tipo_item:item_id".
     */
    public function resolverItemsParaInventario(int $solicitudId): array
    {
        $criterios = $this->queryAll(
            "SELECT tipo_criterio, referencia_id FROM solicitudes_inventario_detalle WHERE solicitud_id = ?",
            [$solicitudId]
        );

        $set = [];
        foreach ($criterios as $c) {
            if ($c['tipo_criterio'] === 'categoria') {
                $tipoCategoria = (string)$this->queryScalar(
                    "SELECT tipo FROM categorias WHERE id = ?", [$c['referencia_id']]
                );
                $tabla = self::TABLA_POR_TIPO[$tipoCategoria] ?? null;
                if (!$tabla) continue;
                $ids = $this->queryAll(
                    "SELECT id FROM `$tabla` WHERE categoria_id = ? AND eliminado = 0", [$c['referencia_id']]
                );
                foreach ($ids as $row) $set["{$tipoCategoria}:{$row['id']}"] = true;
            } elseif ($c['tipo_criterio'] === 'ingrediente_generico') {
                $ids = $this->queryAll(
                    "SELECT id FROM materias_primas WHERE ingrediente_generico_id = ? AND eliminado = 0",
                    [$c['referencia_id']]
                );
                foreach ($ids as $row) $set["materia_prima:{$row['id']}"] = true;
            } else {
                $set["{$c['tipo_criterio']}:{$c['referencia_id']}"] = true;
            }
        }

        return array_keys($set);
    }

    /**
     * Igual que resolverItemsParaInventario() pero con el nombre de cada ítem resuelto —
     * para que quien creó la solicitud pueda ver EXACTAMENTE qué productos/materias
     * primas/insumos quedaron incluidos (no solo la categoría/criterio elegido), sin tener
     * que entrar a la pantalla de conteo. Agrupa los ids por tabla y hace un solo `IN (...)`
     * por tipo — resolver categorías grandes (ej. "Helados" con 30 productos) ítem por ítem
     * significaba 30 viajes de ida y vuelta a la base remota por cada solicitud, suficiente
     * para tirar un 503 por timeout con varias solicitudes en la lista.
     */
    public function resolverNombresItemsParaInventario(int $solicitudId): array
    {
        $claves = $this->resolverItemsParaInventario($solicitudId);
        $labelsTipo = ['producto' => 'Producto', 'materia_prima' => 'Materia prima', 'insumo' => 'Insumo'];

        $idsPorTipo = [];
        foreach ($claves as $clave) {
            [$tipo, $id] = explode(':', $clave, 2);
            if (!isset(self::TABLA_POR_TIPO[$tipo])) continue;
            $idsPorTipo[$tipo][] = (int)$id;
        }

        $items = [];
        foreach ($idsPorTipo as $tipo => $ids) {
            $tabla        = self::TABLA_POR_TIPO[$tipo];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $filas        = $this->queryAll("SELECT id, nombre FROM `$tabla` WHERE id IN ($placeholders)", $ids);
            foreach ($filas as $f) {
                $items[] = ['tipo' => $tipo, 'tipo_label' => $labelsTipo[$tipo], 'id' => (int)$f['id'], 'nombre' => $f['nombre']];
            }
        }

        usort($items, fn($a, $b) => $a['nombre'] <=> $b['nombre']);
        return $items;
    }
}
