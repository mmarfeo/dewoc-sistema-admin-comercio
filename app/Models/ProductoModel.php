<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ProductoModel extends Model
{
    protected string $table = 'productos';

    /** Busca un producto activo por nombre exacto (sin distinguir mayúsculas ni espacios) — usado por el import para actualizar en vez de duplicar. */
    public function findByNombre(string $nombre): array|false
    {
        return $this->queryOne(
            "SELECT * FROM productos WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
            [$nombre]
        );
    }

    /**
     * Productos de reventa activos sin precio de venta y/o sin categoría — candidatos a
     * recordatorio del Chat IA. Excluye los que el usuario ya marcó "no recordar más"
     * (ver ignorarRecordatorio()) — típicamente productos que se compraron una sola vez
     * y nunca se van a dar de alta para la venta.
     */
    public function findIncompletos(int $limite = 15): array
    {
        return $this->queryAll(
            "SELECT id, nombre FROM productos
             WHERE eliminado = 0 AND activo = 1 AND tipo = 'reventa' AND ignorar_recordatorio = 0
               AND (precio_venta = 0 OR categoria_id IS NULL)
             ORDER BY nombre
             LIMIT " . (int) $limite
        );
    }

    /**
     * Marca uno o más productos para que no vuelvan a aparecer en el recordatorio de
     * "productos de reventa incompletos" del Chat IA — usado cuando el usuario avisa que
     * ese producto (comprado una sola vez, por ejemplo) nunca se va a activar para la
     * venta. No cambia nada más del producto (sigue existiendo, sigue como esté su
     * `activo`/`precio_venta`), solo lo saca de findIncompletos().
     */
    public function ignorarRecordatorio(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->query("UPDATE productos SET ignorar_recordatorio = 1 WHERE id IN ($in)", $ids)->rowCount();
    }

    public function findAllConDetalle(): array
    {
        return $this->queryAll(
            'SELECT p.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad,
                    COALESCE(rc.recetas_count, 0) AS recetas_count,
                    COALESCE(rc.costo_mp, 0)      AS costo_mp,
                    COALESCE(pm.es_promo, 0)      AS es_promo
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
             LEFT JOIN (
                 SELECT producto_id, 1 AS es_promo
                 FROM producto_promo_detalle
                 GROUP BY producto_id
             ) pm ON pm.producto_id = p.id
             LEFT JOIN (
                 SELECT pr.producto_id,
                        COUNT(DISTINCT pr.receta_id) AS recetas_count,
                        COALESCE(SUM(
                            rd.cantidad
                            * COALESCE(
                                mp.costo_unitario * (1 + mp.desvio_pct/100),
                                ins.costo_unitario, gen.costo, 0
                            )
                            * (1 + rd.merma_pct / 100)
                        ), 0) AS costo_mp
                 FROM producto_receta pr
                 LEFT JOIN receta_detalle rd ON rd.receta_id = pr.receta_id
                 LEFT JOIN materias_primas mp  ON mp.id  = rd.materia_prima_id AND rd.tipo_ingrediente = \'materia_prima\'
                 LEFT JOIN insumos         ins ON ins.id = rd.insumo_id        AND rd.tipo_ingrediente = \'insumo\'
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
                 GROUP BY pr.producto_id
             ) rc ON rc.producto_id = p.id
             WHERE p.eliminado = 0
             ORDER BY p.nombre'
        );
    }

    /**
     * Recetas vinculadas a un producto (vía producto_receta) con su costo actual.
     * El costo se pide a RecetaModel::calcularCosto() en vez de agregarlo acá con SQL
     * propio, porque esa es la única implementación que sabe explotar sub-recetas
     * anidadas recursivamente — duplicar la cuenta acá quedaba en 0 para esos casos.
     */
    public function getRecetasConCosto(int $productoId): array
    {
        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $recetaModel = new RecetaModel();

        $recetas = $this->queryAll(
            'SELECT r.id, r.nombre, r.rendimiento, r.unidad_rendimiento
             FROM producto_receta pr
             JOIN recetas r ON r.id = pr.receta_id
             WHERE pr.producto_id = :pid AND r.eliminado = 0
             ORDER BY r.nombre',
            [':pid' => $productoId]
        );
        foreach ($recetas as &$r) {
            $r['costo'] = $recetaModel->calcularCosto((int)$r['id']);
        }
        unset($r);

        return $recetas;
    }

    /**
     * Si este producto tiene su costo sincronizado desde alguna receta — 1:1 como
     * "Producto final" (recetas.producto_id) o sumando varias vía "Recetas de costo"
     * (producto_receta). Para decidir si tiene sentido ofrecerle "Recalcular desde
     * compras" (que pisaría ese costo con el de facturas_compra_detalle, sección solo
     * pensada para reventa comprada ya terminada — ver FacturaCompraModel::recalcularCostoDesdeHistorial()).
     */
    public function tieneRecetaPropia(int $productoId): bool
    {
        $directa = $this->queryScalar('SELECT 1 FROM recetas WHERE producto_id = ? AND eliminado = 0 LIMIT 1', [$productoId]);
        if ($directa) return true;
        $pivote = $this->queryScalar('SELECT 1 FROM producto_receta WHERE producto_id = ? LIMIT 1', [$productoId]);
        return (bool) $pivote;
    }

    /** IDs de productos vinculados (vía producto_receta) a alguna de las recetas dadas */
    public function productosPorRecetas(array $recetaIds): array
    {
        if (!$recetaIds) return [];
        $in = implode(',', array_fill(0, count($recetaIds), '?'));
        return array_map('intval', array_column(
            $this->queryAll("SELECT DISTINCT producto_id FROM producto_receta WHERE receta_id IN ($in)", $recetaIds),
            'producto_id'
        ));
    }

    /**
     * Para cada producto vinculado (vía producto_receta) a alguna receta de $recetaIds,
     * recalcula precio_costo sumando el costo actual de TODAS sus recetas vinculadas
     * (no solo las que cambiaron — un producto puede combinar varias) y lo guarda.
     */
    public function sincronizarPrecioCostoPorPivote(array $recetaIds): void
    {
        foreach ($this->productosPorRecetas($recetaIds) as $productoId) {
            $costoTotal = 0.0;
            foreach ($this->getRecetasConCosto($productoId) as $r) {
                $rendimiento = max((float)$r['rendimiento'], 0.001);
                $costoTotal += (float)$r['costo'] / $rendimiento;
            }
            $this->query('UPDATE productos SET precio_costo = ? WHERE id = ?', [$costoTotal, $productoId]);
        }
    }

    /**
     * Vincula una receta como "receta de costo" del producto (tabla pivote
     * producto_receta). Devuelve un mensaje de error si esa receta ya es el
     * "Producto final" (recetas.producto_id) de ESTE mismo producto — ese vínculo 1:1
     * ya sincroniza precio_costo solo (ver RecetaModel::guardarVersion/actualizar), y
     * agregarlo también acá lo pisaría con el mismo cálculo hecho de nuevo por la
     * tabla pivote (RecetaModel::asignarProducto tiene el mismo chequeo, para cuando
     * se asigna desde el lado de la receta en vez del producto).
     */
    public function asignarReceta(int $productoId, int $recetaId): ?string
    {
        $productoFinalDe = $this->queryScalar('SELECT producto_id FROM recetas WHERE id = ?', [$recetaId]);
        if ($productoFinalDe !== null && (int)$productoFinalDe === $productoId) {
            return 'Esta receta ya es el "Producto final" de este producto — agregarla también acá sería redundante (el costo ya se sincroniza solo).';
        }

        $this->db->prepare(
            'INSERT IGNORE INTO producto_receta (producto_id, receta_id) VALUES (?, ?)'
        )->execute([$productoId, $recetaId]);
        return null;
    }

    public function quitarReceta(int $productoId, int $recetaId): void
    {
        $this->db->prepare(
            'DELETE FROM producto_receta WHERE producto_id = ? AND receta_id = ?'
        )->execute([$productoId, $recetaId]);
    }

    /**
     * Grupos de opciones/adicionales asignados a un producto (ver docs/tareas-pendientes.md #1), con
     * su límite. Suma también costo_min/costo_max/costo_promedio/total_opciones_costeadas: el costo
     * (cantidad_consumida × costo del insumo/mp/producto de cada opción) de las opciones del grupo,
     * para poder mostrar una estimación de cuánto agrega el grupo al costo de una promo — el costo
     * real depende de cuál elija el cliente en el momento de la venta, así que esto es un promedio,
     * no un valor exacto (igual que "Costo MP total" en Recetas ya es aproximado).
     */
    public function getGruposOpciones(int $productoId): array
    {
        return $this->queryAll(
            "SELECT g.id, g.nombre, g.tipo_seleccion, g.permite_repetir,
                    pgo.minimo_selecciones, pgo.maximo_selecciones, pgo.obligatorio, pgo.orden,
                    pgo.cantidad_a_repartir,
                    COALESCE(oc.costo_min, 0)      AS costo_min,
                    COALESCE(oc.costo_max, 0)      AS costo_max,
                    COALESCE(oc.costo_promedio, 0) AS costo_promedio,
                    COALESCE(oc.total_opciones, 0) AS total_opciones
             FROM producto_grupos_opciones pgo
             JOIN grupos_opciones g ON g.id = pgo.grupo_id
             LEFT JOIN (
                 SELECT o.grupo_id,
                        MIN(o.cantidad_consumida * COALESCE(mp.costo_unitario, ins.costo_unitario, p.precio_costo, 0)) AS costo_min,
                        MAX(o.cantidad_consumida * COALESCE(mp.costo_unitario, ins.costo_unitario, p.precio_costo, 0)) AS costo_max,
                        AVG(o.cantidad_consumida * COALESCE(mp.costo_unitario, ins.costo_unitario, p.precio_costo, 0)) AS costo_promedio,
                        COUNT(*) AS total_opciones
                 FROM opciones o
                 LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
                 LEFT JOIN insumos ins         ON ins.id = o.insumo_id
                 LEFT JOIN productos p         ON p.id = o.producto_id
                 WHERE o.activo = 1
                 GROUP BY o.grupo_id
             ) oc ON oc.grupo_id = g.id
             WHERE pgo.producto_id = :pid AND g.activo = 1
             ORDER BY pgo.orden, g.nombre",
            [':pid' => $productoId]
        );
    }

    /**
     * $cantidadARepartir: si se carga (ej. 1000 para un producto "1kg"), el stock de las
     * opciones elegidas de este grupo en esta línea se reparte en partes iguales entre
     * todas las selecciones en vez de usar opciones.cantidad_consumida fijo — ver
     * VentaModel::_necesarioOpcion(). NULL mantiene el comportamiento de siempre.
     */
    public function asignarGrupoOpciones(int $productoId, int $grupoId, int $minimo, int $maximo, bool $obligatorio, int $orden = 0, ?float $cantidadARepartir = null): void
    {
        $this->db->prepare(
            'INSERT INTO producto_grupos_opciones (producto_id, grupo_id, minimo_selecciones, maximo_selecciones, obligatorio, orden, cantidad_a_repartir)
             VALUES (:pid, :gid, :min, :max, :obl, :orden, :rep)
             ON DUPLICATE KEY UPDATE minimo_selecciones = :min2, maximo_selecciones = :max2, obligatorio = :obl2, orden = :orden2, cantidad_a_repartir = :rep2'
        )->execute([
            ':pid' => $productoId, ':gid' => $grupoId,
            ':min' => $minimo, ':max' => $maximo, ':obl' => (int)$obligatorio, ':orden' => $orden, ':rep' => $cantidadARepartir,
            ':min2' => $minimo, ':max2' => $maximo, ':obl2' => (int)$obligatorio, ':orden2' => $orden, ':rep2' => $cantidadARepartir,
        ]);
    }

    public function quitarGrupoOpciones(int $productoId, int $grupoId): void
    {
        $this->db->prepare(
            'DELETE FROM producto_grupos_opciones WHERE producto_id = ? AND grupo_id = ?'
        )->execute([$productoId, $grupoId]);
    }

    // ── Promos (ver docs/promos-diseno.md) ──────────────────────────────────────

    /** Componentes de una promo (qué productos y cuántos), con su nombre/unidad/costo para mostrar en el formulario. */
    public function getPromoComponentes(int $productoId): array
    {
        return $this->queryAll(
            'SELECT ppd.cantidad, p.id AS producto_id, p.nombre, p.controla_stock, p.precio_costo,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad
             FROM producto_promo_detalle ppd
             JOIN productos p ON p.id = ppd.componente_producto_id
             WHERE ppd.producto_id = :pid
             ORDER BY p.nombre',
            [':pid' => $productoId]
        );
    }

    /**
     * El costo de una promo NO se carga a mano ni necesita su propia receta: es la suma de
     * cantidad × precio_costo de cada componente (que a su vez ya tiene su costo resuelto por
     * su propia receta/compras). Se recalcula acá mismo cada vez que cambia la lista de
     * componentes (ver asignarPromoComponente()/quitarPromoComponente()) y también en cascada
     * cuando cambia el costo de alguno de sus componentes por una compra nueva (ver
     * sincronizarPromosPorComponente(), llamado desde RecetaModel::propagarCambioCosto()).
     */
    /**
     * Costo de la promo = componentes fijos (van todos) + un estimado de los grupos de
     * opciones asignados (elegí 1 de N — el cliente elige, así que se usa el costo PROMEDIO
     * de las opciones del grupo × la cantidad mínima a elegir, no un valor exacto: el costo
     * real de cada venta puntual varía según qué elija el cliente).
     */
    public function sincronizarPrecioCostoPromo(int $productoId): void
    {
        $costoFijos = (float)$this->queryScalar(
            'SELECT COALESCE(SUM(ppd.cantidad * p.precio_costo), 0)
             FROM producto_promo_detalle ppd
             JOIN productos p ON p.id = ppd.componente_producto_id
             WHERE ppd.producto_id = ?',
            [$productoId]
        );
        $costoOpciones = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(pgo.minimo_selecciones * oc.costo_promedio), 0)
             FROM producto_grupos_opciones pgo
             JOIN (
                 SELECT o.grupo_id,
                        AVG(o.cantidad_consumida * COALESCE(mp.costo_unitario, ins.costo_unitario, p.precio_costo, 0)) AS costo_promedio
                 FROM opciones o
                 LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
                 LEFT JOIN insumos ins         ON ins.id = o.insumo_id
                 LEFT JOIN productos p         ON p.id = o.producto_id
                 WHERE o.activo = 1
                 GROUP BY o.grupo_id
             ) oc ON oc.grupo_id = pgo.grupo_id
             WHERE pgo.producto_id = ?",
            [$productoId]
        );
        $this->query('UPDATE productos SET precio_costo = ? WHERE id = ?', [$costoFijos + $costoOpciones, $productoId]);
    }

    /** true si el producto ya tiene al menos un componente fijo de promo cargado (es_promo, ver productos/index.php). */
    public function tienePromoComponentes(int $productoId): bool
    {
        return (int)$this->queryScalar(
            'SELECT COUNT(*) FROM producto_promo_detalle WHERE producto_id = ?', [$productoId]
        ) > 0;
    }

    /** Para cada producto-componente cuyo precio_costo acaba de cambiar, resincroniza el costo de toda promo que lo incluya. */
    public function sincronizarPromosPorComponente(array $componenteIds): void
    {
        if (!$componenteIds) return;
        $in = implode(',', array_fill(0, count($componenteIds), '?'));
        $promoIds = array_map('intval', array_column(
            $this->queryAll("SELECT DISTINCT producto_id FROM producto_promo_detalle WHERE componente_producto_id IN ($in)", $componenteIds),
            'producto_id'
        ));
        foreach ($promoIds as $promoId) {
            $this->sincronizarPrecioCostoPromo($promoId);
        }
    }

    /** Agrega un componente a la promo, o actualiza su cantidad si ya estaba agregado. */
    public function asignarPromoComponente(int $productoId, int $componenteProductoId, float $cantidad): void
    {
        $this->db->prepare(
            'INSERT INTO producto_promo_detalle (producto_id, componente_producto_id, cantidad)
             VALUES (:pid, :cid, :cant)
             ON DUPLICATE KEY UPDATE cantidad = :cant2'
        )->execute([
            ':pid' => $productoId, ':cid' => $componenteProductoId,
            ':cant' => $cantidad, ':cant2' => $cantidad,
        ]);
        $this->sincronizarPrecioCostoPromo($productoId);
    }

    public function quitarPromoComponente(int $productoId, int $componenteProductoId): void
    {
        $this->db->prepare(
            'DELETE FROM producto_promo_detalle WHERE producto_id = ? AND componente_producto_id = ?'
        )->execute([$productoId, $componenteProductoId]);
        $this->sincronizarPrecioCostoPromo($productoId);
    }

    /**
     * Productos que se pueden agregar como componente de una promo: activos, distintos del
     * propio producto-promo, y que a su vez NO sean ellos mismos una promo (evita anidar
     * promos dentro de promos, que el descuento de stock no sabe explotar recursivamente).
     */
    public function buscarComponentesPromo(string $q, int $excluirProductoId): array
    {
        $like = "%$q%";
        return $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.controla_stock, p.precio_costo,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad
             FROM productos p
             WHERE p.eliminado = 0 AND p.activo = 1 AND p.id != :excl
               AND (p.nombre LIKE :q OR p.codigo LIKE :q2)
               AND NOT EXISTS (SELECT 1 FROM producto_promo_detalle x WHERE x.producto_id = p.id)
             ORDER BY p.nombre
             LIMIT 15",
            [':excl' => $excluirProductoId, ':q' => $like, ':q2' => $like]
        );
    }

    public function findByIdConDetalle(int $id): array|false
    {
        return $this->queryOne(
            'SELECT p.*, c.nombre AS categoria_nombre, u.abreviatura AS unidad
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
             WHERE p.id = ? AND p.eliminado = 0',
            [$id]
        );
    }

    public function tieneVentas(int $id): bool
    {
        return (bool)$this->queryScalar(
            'SELECT COUNT(*) FROM ventas_detalle WHERE producto_id = ?',
            [$id]
        );
    }

    public function actualizarStock(int $id, float $delta): void
    {
        $this->db->prepare(
            'UPDATE productos SET stock_actual = stock_actual + ?, updated_at = NOW() WHERE id = ?'
        )->execute([$delta, $id]);
    }
}
