<?php
require_once BASE_PATH . '/app/Models/Model.php';

class VentaModel extends Model
{
    protected string $table      = 'ventas';
    protected string $primaryKey = 'id';

    /** Total facturado con CAE de AFIP en un período, para el indicador de límite de monotributo */
    public function facturadoAfipEnPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva), 0) FROM ventas
             WHERE estado = 'completada' AND eliminado = 0
               AND facturar_afip = 1 AND afip_cae IS NOT NULL
               AND DATE(created_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    /**
     * Facturado por AFIP en los últimos 12 meses móviles (desde hoy hacia atrás) — es la
     * regla real de Monotributo: la categoría se controla contra el acumulado móvil de
     * 12 meses frente al límite ANUAL completo, no contra un prorrateo mensual fijo. Así,
     * facturar menos un mes deja margen para facturar más otro sin salir de categoría.
     */
    public function facturadoAfipUltimos12Meses(): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva), 0) FROM ventas
             WHERE estado = 'completada' AND eliminado = 0
               AND facturar_afip = 1 AND afip_cae IS NOT NULL
               AND created_at >= (NOW() - INTERVAL 12 MONTH)"
        );
    }

    /**
     * Desglose mes a mes (más reciente primero) de lo facturado por AFIP, para que el
     * admin pueda tildar manualmente qué meses sumar y verificar el acumulado él mismo.
     */
    public function facturadoAfipPorMes(int $cantidadMeses = 24): array
    {
        $desde = date('Y-m-01', strtotime('-' . ($cantidadMeses - 1) . ' months'));
        $rows  = $this->queryAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total_con_iva), 0) AS total
             FROM ventas
             WHERE estado = 'completada' AND eliminado = 0
               AND facturar_afip = 1 AND afip_cae IS NOT NULL
               AND created_at >= :desde
             GROUP BY ym",
            [':desde' => $desde]
        );
        $porMes = array_column($rows, 'total', 'ym');

        $nombresMes = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

        $meses = [];
        for ($i = 0; $i < $cantidadMeses; $i++) {
            $ts = strtotime("-{$i} months", strtotime(date('Y-m-01')));
            $ym = date('Y-m', $ts);
            $meses[] = [
                'ym'    => $ym,
                'label' => $nombresMes[(int)date('n', $ts)] . ' ' . date('Y', $ts),
                'total' => (float)($porMes[$ym] ?? 0),
            ];
        }
        return $meses;
    }

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['v.eliminado = 0'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]  = 'DATE(v.created_at) >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'DATE(v.created_at) <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'v.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['turno_id'])) {
            $where[]  = 'v.turno_id = :turno_id';
            $params[':turno_id'] = (int)$filtros['turno_id'];
        }
        // Ver permiso 'ventas_ver_todas' — sin él, VentasController pasa esto para que cada
        // usuario vea solo lo que él mismo facturó, nunca lo de otro cajero.
        if (!empty($filtros['usuario_id'])) {
            $where[]  = 'v.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int)$filtros['usuario_id'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT v.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre,
                    t.numero AS turno_numero
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             LEFT JOIN turnos   t ON t.id = v.turno_id
             WHERE $cond
             ORDER BY v.id DESC",
            $params
        );
    }

    /** Comprobantes emitidos (o intentados) por AFIP, para la sección de control fiscal */
    public function findComprobantesAfip(array $filtros = []): array
    {
        $where  = ['v.eliminado = 0', 'v.facturar_afip = 1'];
        $params = [];

        if (!empty($filtros['desde'])) {
            $where[]  = 'DATE(v.created_at) >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'DATE(v.created_at) <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (($filtros['resultado'] ?? '') === 'aprobado') {
            $where[] = "v.afip_resultado = 'A'";
        } elseif (($filtros['resultado'] ?? '') === 'rechazado') {
            $where[] = "(v.afip_resultado IS NULL OR v.afip_resultado != 'A')";
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT v.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE $cond
             ORDER BY v.id DESC",
            $params
        );
    }

    /**
     * $usuarioIdFiltro (permiso 'ventas_ver_todas'): si viene, solo devuelve la venta cuando
     * es del propio usuario — así alguien sin ese permiso no puede esquivar el filtro del
     * listado escribiendo directo /ventas/ver?id=X de una venta ajena (misma respuesta que
     * "no existe", para no revelar si el id pertenece a otro usuario o no existe).
     */
    public function findByIdConDetalle(int $id, ?int $usuarioIdFiltro = null): ?array
    {
        $params = [':id' => $id];
        $condUsuario = '';
        if ($usuarioIdFiltro !== null) {
            $condUsuario = ' AND v.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioIdFiltro;
        }

        $venta = $this->queryOne(
            "SELECT v.*, u.nombre AS usuario_nombre,
                    c.nombre AS cliente_nombre, c.documento AS cliente_doc,
                    c.tipo_doc AS cliente_tipo_doc, c.condicion_iva AS cliente_civa,
                    c.direccion AS cliente_direccion,
                    c.telefono AS cliente_telefono, c.email AS cliente_email
             FROM ventas v
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE v.id = :id AND v.eliminado = 0{$condUsuario}",
            $params
        );
        if (!$venta) return null;

        $venta['detalle'] = $this->queryAll(
            "SELECT vd.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad
             FROM ventas_detalle vd
             JOIN productos p ON p.id = vd.producto_id
             WHERE vd.venta_id = :id
             ORDER BY vd.id",
            [':id' => $id]
        );

        // Opciones/adicionales elegidos por línea (ver docs/tareas-pendientes.md #1) —
        // se traen todas de una y se agrupan en PHP en vez de un JOIN por línea, para no
        // duplicar filas de ventas_detalle cuando una línea tiene varias opciones.
        if ($venta['detalle']) {
            $detalleIds = array_column($venta['detalle'], 'id');
            $in = implode(',', array_fill(0, count($detalleIds), '?'));
            $opciones = $this->queryAll(
                "SELECT vdo.venta_detalle_id, vdo.cantidad_elegida, o.id AS opcion_id, o.nombre,
                        o.materia_prima_id, o.insumo_id, o.producto_id, o.cantidad_consumida, o.grupo_id,
                        mp.ingrediente_generico_id
                 FROM ventas_detalle_opciones vdo
                 JOIN opciones o ON o.id = vdo.opcion_id
                 LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
                 WHERE vdo.venta_detalle_id IN ($in)
                 ORDER BY vdo.id",
                $detalleIds
            );
            $opcionesPorDetalle = [];
            foreach ($opciones as $o) {
                $opcionesPorDetalle[$o['venta_detalle_id']][] = $o;
            }
            foreach ($venta['detalle'] as &$d) {
                $d['opciones'] = $opcionesPorDetalle[$d['id']] ?? [];
            }
            unset($d);
        }

        return $venta;
    }

    /** Datos de una opción (nombre + a qué se descuenta stock), para registrar()/anular(). */
    private function _obtenerOpcion(int $opcionId): ?array
    {
        return $this->queryOne(
            "SELECT o.id, o.nombre, o.materia_prima_id, o.insumo_id, o.producto_id, o.cantidad_consumida, o.grupo_id,
                    mp.ingrediente_generico_id, p.controla_stock AS producto_controla_stock
             FROM opciones o
             LEFT JOIN materias_primas mp ON mp.id = o.materia_prima_id
             LEFT JOIN productos p        ON p.id  = o.producto_id
             WHERE o.id = :id",
            [':id' => $opcionId]
        ) ?: null;
    }

    /**
     * De las opciones elegidas en una línea del carrito (ya resueltas, con
     * materia_prima_id/ingrediente_generico_id), arma la lista de "sabores elegidos"
     * que RecetaModel usa para resolver las líneas de ingrediente_generico de la receta
     * contra el sabor real en vez de FIFO ciego — ver RecetaModel::descontarIngrediente().
     * Solo entran las opciones que apuntan a una materia prima que a su vez pertenece a
     * un ingrediente genérico (una opción "sin azúcar" puramente informativa, sin
     * materia_prima_id, queda afuera y no afecta nada).
     */
    private function _seleccionSabores(array $opcionesResueltas): array
    {
        $out = [];
        foreach ($opcionesResueltas as $o) {
            if (!empty($o['materia_prima_id']) && !empty($o['ingrediente_generico_id'])) {
                $out[] = [
                    'materia_prima_id'        => (int)$o['materia_prima_id'],
                    'ingrediente_generico_id' => (int)$o['ingrediente_generico_id'],
                    'peso'                    => (float)($o['cantidad_elegida'] ?? 1),
                ];
            }
        }
        return $out;
    }

    /** cantidad_a_repartir configurada para este producto+grupo (ver producto_grupos_opciones), o null si no aplica. */
    private function _cantidadARepartir(int $productoId, int $grupoId): ?float
    {
        if (!$grupoId) return null;
        $v = $this->queryScalar(
            'SELECT cantidad_a_repartir FROM producto_grupos_opciones WHERE producto_id = :pid AND grupo_id = :gid',
            [':pid' => $productoId, ':gid' => $grupoId]
        );
        return ($v !== null && (float)$v > 0) ? (float)$v : null;
    }

    /**
     * Cuánto se descuenta de stock por UNA opción elegida en una línea del carrito.
     * Dos modos, según si el grupo de esa opción tiene cantidad_a_repartir configurada
     * para ESTE producto (producto_grupos_opciones.cantidad_a_repartir):
     * - Configurada (ej. 1000 para un producto "1kg" de helado): esa cantidad se reparte
     *   en partes iguales entre TODAS las selecciones de la línea (3 sabores elegidos →
     *   1000/3 ≈ 333,33 cada uno), multiplicado por cuántas veces se compró el producto.
     * - Sin configurar (comportamiento de siempre): cada selección descuenta el fijo
     *   opciones.cantidad_consumida — para promos por unidad ("elegí 2, 1 unidad c/u").
     */
    private function _necesarioOpcion(int $productoId, array $opcion, float $cantElegida, float $cantProducto, float $totalSeleccionesLinea): float
    {
        $aRepartir = $this->_cantidadARepartir($productoId, (int)($opcion['grupo_id'] ?? 0));
        if ($aRepartir !== null && $totalSeleccionesLinea > 0) {
            return ($aRepartir * $cantProducto / $totalSeleccionesLinea) * $cantElegida;
        }
        return (float)$opcion['cantidad_consumida'] * $cantElegida * $cantProducto;
    }

    /** Suma de cantidad_elegida de todas las opciones de una línea — denominador del reparto. */
    private function _totalSeleccionesLinea(array $opciones): float
    {
        $total = 0.0;
        foreach ($opciones as $op) $total += (float)($op['cantidad_elegida'] ?? 1);
        return $total;
    }

    /** Deduce/repone stock de la materia prima/insumo/producto vinculado a una opción — mismo criterio que RecetaModel::descontarIngrediente(). */
    private function _ajustarStockOpcion(\PDO $conn, array $opcion, float $delta, int $ventaId): void
    {
        if (!empty($opcion['materia_prima_id'])) {
            $conn->prepare("UPDATE materias_primas SET stock_actual = stock_actual + :d WHERE id = :id")
                 ->execute([':d' => $delta, ':id' => $opcion['materia_prima_id']]);
            $this->_registrarMovimientoStock($conn, 'materia_prima', (int)$opcion['materia_prima_id'], $delta, 'venta', $ventaId, "Opción \"{$opcion['nombre']}\" — venta #{$ventaId}");
        } elseif (!empty($opcion['insumo_id'])) {
            $conn->prepare("UPDATE insumos SET stock_actual = stock_actual + :d WHERE id = :id")
                 ->execute([':d' => $delta, ':id' => $opcion['insumo_id']]);
            $this->_registrarMovimientoStock($conn, 'insumo', (int)$opcion['insumo_id'], $delta, 'venta', $ventaId, "Opción \"{$opcion['nombre']}\" — venta #{$ventaId}");
        } elseif (!empty($opcion['producto_id'])) {
            // Mismo criterio que cualquier producto de reventa: solo descuenta si "Controla
            // stock" está tildado (ver docs/stock-reventa-vs-produccion.md) — rowCount()>0
            // confirma que el WHERE controla_stock=1 sí afectó la fila, para no loggear un
            // movimiento de algo que en realidad no se tocó.
            $stmt = $conn->prepare("UPDATE productos SET stock_actual = stock_actual + :d WHERE id = :id AND controla_stock = 1");
            $stmt->execute([':d' => $delta, ':id' => $opcion['producto_id']]);
            if ($stmt->rowCount() > 0) {
                $this->_registrarMovimientoStock($conn, 'producto', (int)$opcion['producto_id'], $delta, 'venta', $ventaId, "Opción \"{$opcion['nombre']}\" — venta #{$ventaId}");
            }
        }
        // Opción sin materia_prima_id/insumo_id/producto_id: puramente informativa (ej.
        // "sin azúcar"), no hay stock que tocar.
    }

    /**
     * Inserta el movimiento en stock_movimientos DESPUÉS de que el UPDATE de stock_actual
     * ya corrió (lee el valor ya actualizado y reconstruye stock_antes restando/sumando
     * $delta) — mismo patrón que StockModel::ajustar(). Antes ningún camino de venta
     * (producto propio, opciones) quedaba loggeado acá, así que el reporte de Stock
     * teórico vs físico (StockModel::stockTeoricoVsFisico()) comparaba contra un
     * stock_movimientos que nunca veía las ventas — ver conversación de soporte.
     */
    private function _registrarMovimientoStock(\PDO $conn, string $tipoItem, int $itemId, float $delta, string $origen, int $origenId, string $motivo): void
    {
        $tabla        = $tipoItem === 'insumo' ? 'insumos' : ($tipoItem === 'materia_prima' ? 'materias_primas' : 'productos');
        $stockDespues = (float)$this->queryScalar("SELECT stock_actual FROM `{$tabla}` WHERE id = ?", [$itemId]);
        $stockAntes   = $stockDespues - $delta;
        $conn->prepare(
            "INSERT INTO stock_movimientos (tipo_item, item_id, tipo_mov, origen, origen_id, cantidad, stock_antes, stock_despues, motivo, created_at)
             VALUES (:ti, :iid, :tm, :o, :oid, :cant, :sa, :sd, :mot, NOW())"
        )->execute([
            ':ti'   => $tipoItem,
            ':iid'  => $itemId,
            ':tm'   => $delta < 0 ? 'salida' : 'entrada',
            ':o'    => $origen,
            ':oid'  => $origenId,
            ':cant' => abs($delta),
            ':sa'   => $stockAntes,
            ':sd'   => $stockDespues,
            ':mot'  => $motivo,
        ]);
    }

    /** Faltante de stock de una opción, en el mismo formato que ventas_sin_stock — null si alcanza. */
    private function _faltanteOpcion(array $opcion, float $necesario): ?array
    {
        if (!empty($opcion['materia_prima_id'])) {
            $disponible = (float)$this->queryScalar('SELECT stock_actual FROM materias_primas WHERE id = :id', [':id' => $opcion['materia_prima_id']]);
            if ($disponible >= $necesario) return null;
            return ['tipo_ingrediente' => 'materia_prima', 'materia_prima_id' => $opcion['materia_prima_id'], 'insumo_id' => null, 'producto_opcion_id' => null,
                    'nombre' => $opcion['nombre'], 'necesario' => $necesario, 'disponible' => $disponible];
        }
        if (!empty($opcion['insumo_id'])) {
            $disponible = (float)$this->queryScalar('SELECT stock_actual FROM insumos WHERE id = :id', [':id' => $opcion['insumo_id']]);
            if ($disponible >= $necesario) return null;
            return ['tipo_ingrediente' => 'insumo', 'materia_prima_id' => null, 'insumo_id' => $opcion['insumo_id'], 'producto_opcion_id' => null,
                    'nombre' => $opcion['nombre'], 'necesario' => $necesario, 'disponible' => $disponible];
        }
        // Solo chequea/avisa faltante si el producto vinculado controla stock — uno sin
        // "Controla stock" tildado (ej. un café que se hace al momento) nunca se queda "sin
        // stock" en este sentido, mismo criterio que la venta del producto en sí.
        if (!empty($opcion['producto_id']) && !empty($opcion['producto_controla_stock'])) {
            $disponible = (float)$this->queryScalar('SELECT stock_actual FROM productos WHERE id = :id', [':id' => $opcion['producto_id']]);
            if ($disponible >= $necesario) return null;
            return ['tipo_ingrediente' => 'producto', 'materia_prima_id' => null, 'insumo_id' => null, 'producto_opcion_id' => $opcion['producto_id'],
                    'nombre' => $opcion['nombre'], 'necesario' => $necesario, 'disponible' => $disponible];
        }
        return null;
    }

    /** Componentes (producto + cantidad) de una promo — ver producto_promo_detalle. Público: lo usa también VentasController::opcionesProducto() para armar el selector por unidad. */
    public function componentesPromo(int $productoId): array
    {
        return $this->queryAll(
            'SELECT ppd.componente_producto_id, ppd.cantidad, p.nombre, p.imagen
             FROM producto_promo_detalle ppd
             JOIN productos p ON p.id = ppd.componente_producto_id
             WHERE ppd.producto_id = :pid',
            [':pid' => $productoId]
        );
    }

    /**
     * Expande cada línea de promo del carrito (ver docs/promos-diseno.md) en su propia
     * línea + una línea por componente, marcada con '_componente_de_promo' — precio $0 (ya
     * se cobró en la línea de la promo, no hay que sumarlo de nuevo) para que registrar()
     * sepa que esa línea es un componente y le asigne venta_detalle_padre_id apuntando a
     * la fila de la promo. Los productos sin componentes en producto_promo_detalle pasan
     * sin cambios.
     *
     * $item['opciones_por_componente'][componente_producto_id]: si el cajero eligió sabor
     * por unidad para ese componente (ver VentasController::opcionesProducto(), que arma un
     * selector propio por cada unidad en vez de un pool combinado), viene un array con una
     * selección de opciones POR UNIDAD — acá se genera UNA línea por unidad, cada una con
     * cantidad=1 y su propia selección, para que la receta de esa unidad consuma exactamente
     * el sabor elegido para ELLA (no un promedio ni FIFO ciego entre las unidades del combo).
     * Sin esa selección por unidad (componente sin sabor para elegir, ej. "Café" dentro de
     * "Promo Café + 1/4"), se arma una sola línea con la cantidad total, como antes.
     */
    private function explotarPromos(array $detalle): array
    {
        $expandido = [];
        foreach ($detalle as $item) {
            $expandido[] = $item;

            $componentes = $this->componentesPromo((int)$item['producto_id']);
            foreach ($componentes as $c) {
                $componenteId    = (int)$c['componente_producto_id'];
                $seleccionPorUnidad = $item['opciones_por_componente'][$componenteId] ?? null;

                if (is_array($seleccionPorUnidad) && count($seleccionPorUnidad) > 0) {
                    foreach ($seleccionPorUnidad as $opcionesUnidad) {
                        $expandido[] = [
                            'producto_id'           => $componenteId,
                            'cantidad'              => 1.0,
                            'precio_unitario'       => 0,
                            'descuento_pct'         => 0,
                            'subtotal'              => 0,
                            'iva_porcentaje'        => 0,
                            'subtotal_con_iva'      => 0,
                            '_componente_de_promo'  => true,
                            'opciones'              => $opcionesUnidad,
                        ];
                    }
                    continue;
                }

                $expandido[] = [
                    'producto_id'           => $componenteId,
                    'cantidad'              => (float)$c['cantidad'] * (float)$item['cantidad'],
                    'precio_unitario'       => 0,
                    'descuento_pct'         => 0,
                    'subtotal'              => 0,
                    'iva_porcentaje'        => 0,
                    'subtotal_con_iva'      => 0,
                    '_componente_de_promo'  => true,
                ];
            }
        }
        return $expandido;
    }

    /**
     * Ficha de receta a consumir directo para un producto "no controla stock" (hecho
     * al momento) con receta de producción propia asignada — ver registrar()/anular().
     * Los productos con controla_stock=1 no entran acá: para esos, el consumo de
     * ingredientes ya pasó antes, en el momento de "Producción" por lotes.
     */
    private function _recetaDeProductoNoControlado(int $productoId): ?array
    {
        return $this->queryOne(
            "SELECT r.id AS receta_id
             FROM productos p
             JOIN recetas r ON r.producto_id = p.id AND r.eliminado = 0
             WHERE p.id = :pid AND p.controla_stock = 0
             LIMIT 1",
            [':pid' => $productoId]
        ) ?: null;
    }

    /**
     * Recalcula, línea por línea, neto / alícuota / total con IVA a partir del precio y de
     * la configuración de precios del comercio (ver core/PrecioHelper.php, decisión D9).
     * Reemplaza los campos `subtotal`, `iva_porcentaje` y `subtotal_con_iva` que venían del
     * llamador: ninguno de los tres se guarda tal como llegó.
     *
     * Dos formas de informar el precio de una línea, ambas válidas:
     * - `precio_unitario`: el precio CARGADO, con el criterio D9 vigente (lo que usan el POS
     *   y el Portal, donde el cajero/cliente ve el precio tal como está en el producto).
     * - `precio_final_unitario`: el precio FINAL con IVA, sin importar D9 (lo que usa la API
     *   de MenuApp, que siempre cobra el precio final al cliente).
     *
     * `precio_unitario` se guarda siempre NETO, para que se mantenga la invariante
     * subtotal = cantidad × precio_unitario × (1 − descuento) que ya asumen el comprobante
     * fiscal y los reportes.
     */
    private function _recalcularIvaDetalle(array $detalle): array
    {
        require_once BASE_PATH . '/core/PrecioHelper.php';

        $ivaPorProducto = [];
        foreach ($detalle as &$item) {
            // Componente de promo (ver explotarPromos()): la plata la cobra la línea de la
            // promo, el componente va a $0 solo para dejar registrado el consumo de stock.
            // Se deja tal cual, con alícuota 0, para que no aparezca en el comprobante como
            // una línea más de IVA al 21% por $0.
            if (!empty($item['_componente_de_promo'])) continue;

            $pid  = (int)$item['producto_id'];
            $cant = (float)$item['cantidad'];
            $desc = (float)($item['descuento_pct'] ?? 0);

            // La alícuota sale SIEMPRE de la base, nunca de lo que mandó el cliente.
            if (!array_key_exists($pid, $ivaPorProducto)) {
                $ivaPorProducto[$pid] = (float)($this->queryScalar('SELECT iva FROM productos WHERE id = ?', [$pid]) ?? 0);
            }
            $ivaProducto = $ivaPorProducto[$pid];

            $finalUnitario = array_key_exists('precio_final_unitario', $item)
                ? (float)$item['precio_final_unitario']
                : PrecioHelper::precioFinal((float)($item['precio_unitario'] ?? 0), $ivaProducto);

            // Solo se discrimina IVA si el comprobante lo discrimina (condición fiscal), que
            // es independiente de si el precio cargado ya lo traía (D9).
            $ivaEfectivo = PrecioHelper::ivaEfectivo($ivaProducto);

            $totalLinea = round($finalUnitario * $cant * (1 - $desc / 100), 4);
            $desglose   = PrecioHelper::desglosar($totalLinea, $ivaEfectivo, 4);
            $netoUnit   = PrecioHelper::desglosar($finalUnitario, $ivaEfectivo, 4)['neto'];

            $item['precio_unitario']  = $netoUnit;
            $item['iva_porcentaje']   = $ivaEfectivo;
            $item['subtotal']         = $desglose['neto'];
            $item['subtotal_con_iva'] = $totalLinea;
        }
        unset($item);

        return $detalle;
    }

    /**
     * Rearma subtotal / total_iva / total_con_iva de la cabecera sumando las líneas ya
     * normalizadas por _recalcularIvaDetalle(). El descuento global se aplica al neto y al
     * IVA con el mismo factor (y no desglosando el total descontado) porque una venta puede
     * mezclar alícuotas distintas: así cada parte conserva su proporción.
     */
    private function _recalcularTotalesCabecera(array $cabecera, array $detalle): array
    {
        $neto = 0.0;
        $iva  = 0.0;
        foreach ($detalle as $item) {
            $neto += (float)$item['subtotal'];
            $iva  += (float)$item['subtotal_con_iva'] - (float)$item['subtotal'];
        }

        $descGlobal = (float)($cabecera['descuento_global'] ?? 0);
        if ($descGlobal > 0 && $descGlobal <= 100) {
            $factor = 1 - $descGlobal / 100;
            $neto  *= $factor;
            $iva   *= $factor;
        }

        $cabecera['subtotal']      = round($neto, 2);
        $cabecera['total_iva']     = round($iva, 2);
        $cabecera['total_con_iva'] = round($neto + $iva, 2);

        return $cabecera;
    }

    /**
     * Registra la venta. Devuelve:
     * - ['ok'=>true,'id'=>venta_id] si salió bien.
     * - ['ok'=>false,'requiereConfirmacion'=>true,'faltantes'=>[...]] SIN registrar nada,
     *   si algún producto "hecho al momento" (controla_stock=0 con receta propia) no
     *   tiene suficiente materia prima/insumos y todavía no se confirmó vender igual
     *   ($confirmarSinStock=false) — el llamador debe avisarle al usuario y, si confirma,
     *   volver a llamar con $confirmarSinStock=true.
     * - Si ya viene confirmado, la venta se registra igual (permite quedar en negativo)
     *   y cada faltante queda anotado en ventas_sin_stock para revisar después.
     */
    public function registrar(array $cabecera, array $detalle, bool $confirmarSinStock = false): array
    {
        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $recetaModel = new RecetaModel();

        // Promos (ver docs/promos-diseno.md): "explota" cada línea de promo del carrito en
        // su propia línea + una línea por componente, ANTES de todo lo demás — así el
        // resto del método (chequeo de faltantes, descuento de stock, ventas_sin_stock)
        // las procesa exactamente igual que si fueran ítems del carrito normales, sin
        // ninguna lógica de stock nueva.
        $detalle = $this->explotarPromos($detalle);

        // IVA (ver docs/integracion-menuapp-checklist.md, D9 y tarea 0.6): el neto, el IVA
        // y el total se RECALCULAN acá con PrecioHelper, en vez de confiar en el subtotal /
        // subtotal_con_iva que armó el navegador o el llamador. Así el total guardado en
        // `ventas` no puede diferir del que vio el cliente, y los cuatro escenarios de D9
        // (precios con/sin IVA × Responsable Inscripto/monotributista) se resuelven en un
        // único lugar para el POS, el Portal mayorista y la API de MenuApp.
        $detalle  = $this->_recalcularIvaDetalle($detalle);
        $cabecera = $this->_recalcularTotalesCabecera($cabecera, $detalle);

        // Chequeo previo, antes de tocar la base: qué productos de esta venta se hacen
        // al momento (sin stock propio) y tienen receta asociada, y si alcanza la
        // materia prima/insumos para todos ellos.
        $recetaPorProducto = [];
        $faltantesDetallado = []; // pid => [faltantes estructurados]
        $seleccionSaboresPorItem = []; // itemIdx => sabores elegidos vía opciones, para RecetaModel
        $hayFaltantes = false;
        foreach ($detalle as $itemIdx => $item) {
            $pid = (int)$item['producto_id'];

            // Opciones/adicionales elegidas (ver docs/tareas-pendientes.md #1): mismo
            // chequeo de faltantes, pero por línea del carrito en vez de por producto,
            // porque dos líneas del mismo producto pueden traer opciones distintas.
            // De paso se arma $seleccionSaboresPorItem, que la receta del producto (si
            // tiene una línea de ingrediente_generico que coincida) usa para descontar
            // el sabor puntual elegido en vez de FIFO ciego — ver RecetaModel.
            $totalSelLinea = $this->_totalSeleccionesLinea($item['opciones'] ?? []);
            $opcionesResueltas = [];
            foreach ($item['opciones'] ?? [] as $op) {
                $opcion = $this->_obtenerOpcion((int)($op['opcion_id'] ?? 0));
                if (!$opcion) continue;
                $opcionesResueltas[] = $opcion + ['cantidad_elegida' => (float)($op['cantidad_elegida'] ?? 1)];
                $necesario = $this->_necesarioOpcion($pid, $opcion, (float)($op['cantidad_elegida'] ?? 1), (float)$item['cantidad'], $totalSelLinea);
                $f = $this->_faltanteOpcion($opcion, $necesario);
                if ($f) {
                    $faltantesDetallado["item{$itemIdx}_op{$opcion['id']}"] = [$f];
                    $hayFaltantes = true;
                }
            }
            $seleccionSaboresPorItem[$itemIdx] = $this->_seleccionSabores($opcionesResueltas);

            if (!array_key_exists($pid, $recetaPorProducto)) {
                $recetaPorProducto[$pid] = $this->_recetaDeProductoNoControlado($pid);
            }
            if ($recetaPorProducto[$pid]) {
                $faltantes = $recetaModel->verificarFaltantesDetalladoParaVenta((int)$recetaPorProducto[$pid]['receta_id'], (float)$item['cantidad'], $seleccionSaboresPorItem[$itemIdx]);
                if ($faltantes) {
                    $faltantesDetallado[$pid] = $faltantes;
                    $hayFaltantes = true;
                }
            }
        }
        if ($hayFaltantes && !$confirmarSinStock) {
            $planos = [];
            foreach ($faltantesDetallado as $lista) {
                foreach ($lista as $f) $planos[] = "{$f['nombre']} (necesita {$f['necesario']}, hay {$f['disponible']})";
            }
            return ['ok' => false, 'requiereConfirmacion' => true, 'faltantes' => array_values(array_unique($planos))];
        }

        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $ventaId = $this->insert($cabecera);

            $stmtDet = $conn->prepare(
                "INSERT INTO ventas_detalle
                    (venta_id, producto_id, cantidad, precio_unitario, descuento_pct, subtotal, iva_porcentaje, subtotal_con_iva, costo_mp_real, costo_ingrediente_generico, venta_detalle_padre_id)
                 VALUES (:vid, :pid, :cant, :precio, :desc, :sub, :iva, :sub_iva, :costo_real, :costo_generico, :padre)"
            );
            $stmtSinStock = $conn->prepare(
                "INSERT INTO ventas_sin_stock
                    (venta_id, producto_id, tipo_ingrediente, materia_prima_id, insumo_id, producto_opcion_id, ingrediente_generico_id, ingrediente_nombre, cantidad_necesaria, cantidad_disponible, usuario_id)
                 VALUES (:vid, :pid, :tipo, :mp, :ins, :prod_op, :gen, :nombre, :necesaria, :disponible, :uid)"
            );
            $stmtDetOpcion = $conn->prepare(
                "INSERT INTO ventas_detalle_opciones (venta_detalle_id, opcion_id, cantidad_elegida)
                 VALUES (:vdid, :oid, :cant)"
            );

            // Componente de promo (ver explotarPromos() arriba): apunta como padre a la
            // fila de la promo que lo contiene, que siempre es la última línea "normal"
            // insertada — nunca queda interleavado con otra promo antes de terminar la
            // suya, porque explotarPromos() agrupa cada promo con sus componentes juntos.
            $padreDetalleId = null;
            foreach ($detalle as $itemIdx => $item) {
                $esComponente = !empty($item['_componente_de_promo']);
                $pid = (int)$item['producto_id'];

                // Costo real de MP de esta línea (total, no unitario), al momento de la
                // venta — con receta propia usa el sabor puntual elegido (no el promedio
                // de calcularCosto()); de reventa, el precio_costo del producto en este
                // instante (sin ingrediente_generico). Se guarda como snapshot para que
                // el reporte/consulta de margen quede exacto e inmutable, sin importar
                // cómo cambien los costos después. costo_ingrediente_generico es la
                // porción de ese total que sale de líneas de ingrediente_generico (ej. el
                // helado) — para poder mostrar "MP directa" vs "ingrediente genérico"
                // por separado sin recalcular con costos de hoy.
                if ($recetaPorProducto[$pid]) {
                    $costoReal          = $recetaModel->calcularCostoReal((int)$recetaPorProducto[$pid]['receta_id'], (float)$item['cantidad'], $seleccionSaboresPorItem[$itemIdx] ?? []);
                    $costoMpReal        = $costoReal['total'];
                    $costoGenericoReal  = $costoReal['generico'];
                } else {
                    $costoMpReal        = (float)$item['cantidad'] * (float)($this->queryScalar('SELECT precio_costo FROM productos WHERE id = ?', [$pid]) ?? 0);
                    $costoGenericoReal  = 0.0;
                }

                $stmtDet->execute([
                    ':vid'            => $ventaId,
                    ':pid'            => $pid,
                    ':cant'           => (float)$item['cantidad'],
                    ':precio'         => (float)$item['precio_unitario'],
                    ':desc'           => (float)($item['descuento_pct'] ?? 0),
                    ':sub'            => (float)$item['subtotal'],
                    ':iva'            => (float)$item['iva_porcentaje'],
                    ':sub_iva'        => (float)$item['subtotal_con_iva'],
                    ':costo_real'     => $costoMpReal,
                    ':costo_generico' => $costoGenericoReal,
                    ':padre'          => $esComponente ? $padreDetalleId : null,
                ]);
                $ventaDetalleId = (int)$conn->lastInsertId();
                if (!$esComponente) $padreDetalleId = $ventaDetalleId;

                // Descontar stock (solo si el producto lo controla — ej. café no)
                $stmtStockProd = $conn->prepare(
                    "UPDATE productos SET stock_actual=stock_actual-:c WHERE id=:id AND controla_stock=1"
                );
                $stmtStockProd->execute([':c' => (float)$item['cantidad'], ':id' => (int)$item['producto_id']]);
                // rowCount()>0 confirma que controla_stock=1 (si no, el WHERE no afectó nada
                // y no hay que loggear un movimiento de algo que no se tocó).
                if ($stmtStockProd->rowCount() > 0) {
                    $this->_registrarMovimientoStock($conn, 'producto', $pid, -(float)$item['cantidad'], 'venta', $ventaId, "Venta #{$ventaId}");
                }

                // Producto hecho al momento con receta propia: consumir la materia
                // prima/insumos directo (ya se validó alcance más arriba; si faltaba y
                // se confirmó igual, esto puede dejar stock en negativo a propósito).
                if ($recetaPorProducto[$pid]) {
                    $recetaModel->consumirParaVenta((int)$recetaPorProducto[$pid]['receta_id'], (float)$item['cantidad'], $seleccionSaboresPorItem[$itemIdx] ?? [], $ventaId);

                    foreach ($faltantesDetallado[$pid] ?? [] as $f) {
                        $stmtSinStock->execute([
                            ':vid'        => $ventaId,
                            ':pid'        => $pid,
                            ':tipo'       => $f['tipo_ingrediente'],
                            ':mp'         => $f['materia_prima_id'],
                            ':ins'        => $f['insumo_id'],
                            ':prod_op'    => $f['producto_opcion_id'] ?? null,
                            ':gen'        => $f['ingrediente_generico_id'] ?? null,
                            ':nombre'     => $f['nombre'],
                            ':necesaria'  => $f['necesario'],
                            ':disponible' => $f['disponible'],
                            ':uid'        => $cabecera['usuario_id'] ?? null,
                        ]);
                    }
                }

                // Opciones/adicionales elegidas en esta línea (ver docs/tareas-pendientes.md
                // #1): se guarda la selección y se descuenta la materia prima/insumo
                // vinculado a cada una, además de la receta base del producto de arriba.
                // OJO: si el producto tiene una receta con una línea de ingrediente_generico
                // que coincide con el genérico de estas opciones (ver $seleccionSaboresPorItem
                // arriba y RecetaModel::descontarIngrediente()), ESE consumo ya salió de la
                // receta — para no descontar doble, ese caso deja cantidad_a_repartir sin
                // configurar en producto_grupos_opciones y cantidad_consumida en 0 en la
                // opción, así _necesarioOpcion() da 0 y _ajustarStockOpcion() no toca nada acá.
                $totalSelLinea = $this->_totalSeleccionesLinea($item['opciones'] ?? []);
                foreach ($item['opciones'] ?? [] as $op) {
                    $opcion = $this->_obtenerOpcion((int)($op['opcion_id'] ?? 0));
                    if (!$opcion) continue;
                    $cantElegida = (float)($op['cantidad_elegida'] ?? 1);

                    $stmtDetOpcion->execute([
                        ':vdid' => $ventaDetalleId,
                        ':oid'  => $opcion['id'],
                        ':cant' => $cantElegida,
                    ]);

                    $necesario = $this->_necesarioOpcion($pid, $opcion, $cantElegida, (float)$item['cantidad'], $totalSelLinea);
                    if ($necesario > 0) {
                        $this->_ajustarStockOpcion($conn, $opcion, -$necesario, $ventaId);
                    }

                    foreach ($faltantesDetallado["item{$itemIdx}_op{$opcion['id']}"] ?? [] as $f) {
                        $stmtSinStock->execute([
                            ':vid'        => $ventaId,
                            ':pid'        => $pid,
                            ':tipo'       => $f['tipo_ingrediente'],
                            ':mp'         => $f['materia_prima_id'],
                            ':ins'        => $f['insumo_id'],
                            ':prod_op'    => $f['producto_opcion_id'] ?? null,
                            ':gen'        => null,
                            ':nombre'     => $f['nombre'],
                            ':necesaria'  => $f['necesario'],
                            ':disponible' => $f['disponible'],
                            ':uid'        => $cabecera['usuario_id'] ?? null,
                        ]);
                    }
                }
            }

            // Registrar movimiento(s) en caja si hay turno abierto — una fila por cada
            // medio de pago con monto > 0, para que CajaModel::calcularEsperado() (que ya
            // agrupa caja_movimientos por metodo_pago) siga sumando bien una venta pagada
            // con varios medios a la vez, sin necesitar ningún cambio ahí.
            if (!empty($cabecera['turno_id'])) {
                $fecha = $cabecera['created_at'] ?? date('Y-m-d H:i:s');
                $montosPorMedio = [
                    'efectivo'      => (float)($cabecera['monto_efectivo']      ?? 0),
                    'debito'        => (float)($cabecera['monto_debito']        ?? 0),
                    'credito'       => (float)($cabecera['monto_credito']       ?? 0),
                    'transferencia' => (float)($cabecera['monto_transferencia'] ?? 0),
                    'mercadopago'   => (float)($cabecera['monto_mercadopago']   ?? 0),
                ];
                // Cuenta corriente no entra a este array a propósito: no es plata que haya
                // entrado a la caja. Si la venta es 100% cuenta corriente, ninguno de los 5
                // medios reales suma > 0 — el fallback de abajo NO tiene que asignarle el
                // total a un medio real (generaría un ingreso de caja falso), así que se
                // excluye explícitamente por metodo_pago.
                if (array_sum($montosPorMedio) <= 0 && ($cabecera['metodo_pago'] ?? '') !== 'cuenta_corriente') {
                    // Compatibilidad: cabecera armada sin desglose (no debería pasar desde
                    // VentasController, pero por las dudas no perder el movimiento de caja).
                    $montosPorMedio[$cabecera['metodo_pago'] ?? 'efectivo'] = (float)$cabecera['total_con_iva'];
                }

                $stmtCaja = $conn->prepare(
                    "INSERT INTO caja_movimientos
                        (turno_id, tipo, monto, descripcion, venta_id, usuario_id, metodo_pago, created_at, updated_at)
                     VALUES (:tid, 'ingreso', :monto, :desc, :vid, :uid, :metodo, :fecha1, :fecha2)"
                );
                foreach ($montosPorMedio as $medio => $monto) {
                    if ($monto <= 0) continue;
                    $stmtCaja->execute([
                        ':tid'   => $cabecera['turno_id'],
                        ':monto' => $monto,
                        ':desc'  => 'Venta #' . $ventaId,
                        ':vid'   => $ventaId,
                        ':uid'   => $cabecera['usuario_id'],
                        ':metodo'=> $medio,
                        ':fecha1'=> $fecha,
                        ':fecha2'=> $fecha,
                    ]);
                }
            }

            $conn->commit();
            return ['ok' => true, 'id' => $ventaId];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Guarda el resultado AFIP (CAE) en la venta */
    public function updateAfip(int $id, array $afip): void
    {
        $this->update($id, [
            'afip_tipo_cbte' => $afip['tipo_cbte'] ?? null,
            'afip_pto_vta'   => $afip['pto_vta']   ?? null,
            'afip_nro_cbte'  => $afip['nro_cbte']  ?? null,
            'afip_cae'       => $afip['cae']        ?: null,
            'afip_cae_vto'   => $afip['cae_vto']   ?? null,
            'afip_resultado' => $afip['resultado']  ?? null,
            'afip_obs'       => $afip['obs']        ?: null,
        ]);
    }

    public function anular(int $id): bool
    {
        $venta = $this->findByIdConDetalle($id);
        if (!$venta || $venta['estado'] === 'anulada') return false;

        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $recetaModel = new RecetaModel();

        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $this->update($id, ['estado' => 'anulada']);

            // Si esta venta venía de un pedido del Portal de Clientes Mayoristas (quedó
            // "convertido" al facturarse), reflejar la anulación ahí también — si no, el
            // cliente sigue viendo el pedido como "Convertido" para siempre en /portal/pedidos
            // aunque la venta ya no exista.
            if (!empty($venta['pedido_cliente_id'])) {
                require_once BASE_PATH . '/app/Models/PedidoClienteModel.php';
                (new PedidoClienteModel())->marcarAnulado((int)$venta['pedido_cliente_id']);
            }

            foreach ($venta['detalle'] as $it) {
                $pid = (int)$it['producto_id'];
                $stmtStockProd = $conn->prepare(
                    "UPDATE productos SET stock_actual=stock_actual+:c WHERE id=:id AND controla_stock=1"
                );
                $stmtStockProd->execute([':c' => (float)$it['cantidad'], ':id' => $pid]);
                if ($stmtStockProd->rowCount() > 0) {
                    $this->_registrarMovimientoStock($conn, 'producto', $pid, (float)$it['cantidad'], 'venta', $id, "Anulación de venta #{$id}");
                }

                // Reponer stock de las opciones/adicionales elegidas en esta línea (ver
                // docs/tareas-pendientes.md #1) — se descontaron en registrar(), acá se
                // devuelven al anular, mismo criterio que la receta base de abajo.
                $totalSelLinea = $this->_totalSeleccionesLinea($it['opciones'] ?? []);
                foreach ($it['opciones'] ?? [] as $op) {
                    $necesario = $this->_necesarioOpcion($pid, $op, (float)$op['cantidad_elegida'], (float)$it['cantidad'], $totalSelLinea);
                    if ($necesario > 0) {
                        $this->_ajustarStockOpcion($conn, $op, $necesario, $id);
                    }
                }

                // Si era un producto "hecho al momento" que había consumido su receta
                // directo al vender, devolver esa materia prima/insumos al anular — con
                // los mismos sabores elegidos en su momento (ya guardados en
                // ventas_detalle_opciones), para reponer exacto y no "adivinar" variante.
                $recetaInfo = $this->_recetaDeProductoNoControlado($pid);
                if ($recetaInfo) {
                    $seleccionSabores = $this->_seleccionSabores($it['opciones'] ?? []);
                    $recetaModel->reponerParaVentaAnulada((int)$recetaInfo['receta_id'], (float)$it['cantidad'], $seleccionSabores, $id);
                }
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** Medios de pago "simples" (un solo medio, monto completo) que soporta cambiarMedioPago() — a
     *  propósito no incluye 'combinado' (no hay forma no ambigua de reasignar varios montos a uno
     *  solo) ni 'cuenta_corriente' (no genera movimiento de caja, es una cuenta a cobrar aparte). */
    public const MEDIOS_PAGO_SIMPLES = ['efectivo', 'debito', 'credito', 'transferencia', 'mercadopago'];

    /**
     * Corrige el medio de pago de una venta ya cobrada (ej. el cajero facturó con Mercado
     * Pago por error y en realidad cobró en efectivo) sin pasar por anular+recargar. Mueve
     * el monto del medio viejo al nuevo en la propia venta, y actualiza el/los
     * caja_movimientos ya generados para que el cierre de caja (que agrupa por
     * metodo_pago, ver CajaModel::calcularEsperado()) quede coherente.
     */
    public function cambiarMedioPago(int $id, string $nuevoMedio): bool
    {
        if (!in_array($nuevoMedio, self::MEDIOS_PAGO_SIMPLES, true)) {
            throw new \InvalidArgumentException('Medio de pago inválido.');
        }

        $venta = $this->findById($id);
        if (!$venta || $venta['estado'] === 'anulada') return false;

        // Las ventas que llegaron por la API de MenuApp no se editan desde Dewoc (tarea 4.5
        // de docs/integracion-menuapp-checklist.md): el medio de pago lo definió el cliente
        // al pagar en MenuApp, y cambiarlo acá dejaría los dos sistemas contando distinto.
        // El bloqueo va en el modelo y no solo en la vista para que tampoco se pueda hacer
        // llamando al endpoint a mano.
        if (($venta['origen'] ?? 'pos') === 'menuapp') {
            throw new \InvalidArgumentException('Esta venta vino de MenuApp: el medio de pago se cambia desde MenuApp, no desde Dewoc.');
        }

        $medioActual = $venta['metodo_pago'];
        if (!in_array($medioActual, self::MEDIOS_PAGO_SIMPLES, true)) {
            throw new \InvalidArgumentException('Esta venta tiene un medio de pago combinado o es a cuenta corriente — no se puede cambiar desde acá.');
        }
        if ($medioActual === $nuevoMedio) {
            throw new \InvalidArgumentException('La venta ya está registrada con ese medio de pago.');
        }

        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            // Fallback a total_con_iva: ventas históricas importadas pueden no tener el
            // desglose monto_* cargado (columna en 0 aunque el medio sea real).
            $monto = (float)$venta['monto_' . $medioActual];
            if ($monto <= 0) $monto = (float)$venta['total_con_iva'];

            $this->update($id, [
                'metodo_pago'            => $nuevoMedio,
                'monto_' . $medioActual  => 0,
                'monto_' . $nuevoMedio   => $monto,
            ]);

            // Puede no existir fila (venta sin turno_id, ej. importada del ERP anterior) —
            // no es un error, simplemente no hay caja que corregir.
            $conn->prepare(
                "UPDATE caja_movimientos SET metodo_pago = :nuevo WHERE venta_id = :vid AND tipo = 'ingreso'"
            )->execute([':nuevo' => $nuevoMedio, ':vid' => $id]);

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // ── Integración con MenuApp (ver docs/integracion-menuapp-checklist.md) ─────

    /**
     * Venta ya registrada para una referencia externa (ej. el código del pedido de
     * MenuApp). Es lo que hace idempotente a POST /ventas: si el mismo pedido llega dos
     * veces, se devuelve la venta que ya existe en vez de duplicarla.
     */
    public function buscarPorReferenciaExterna(string $referencia): ?array
    {
        return $this->queryOne(
            "SELECT * FROM ventas WHERE referencia_externa = :ref AND eliminado = 0 LIMIT 1",
            [':ref' => $referencia]
        ) ?: null;
    }

    /**
     * ¿Se puede vender UNA unidad de este producto ahora mismo? Mismas tres reglas que ya
     * aplica registrar(), expuestas para /api/menuapp/v1/disponibilidad sin duplicar el
     * cálculo:
     * - controla_stock = 1       → alcanza con stock_actual >= 1;
     * - controla_stock = 0 + receta propia → que la receta no tenga faltantes para 1 unidad;
     * - controla_stock = 0 sin receta      → siempre disponible (se hace al momento).
     */
    public function disponibilidadProducto(int $productoId): array
    {
        $prod = $this->queryOne(
            "SELECT id, nombre, controla_stock, stock_actual FROM productos WHERE id = :id AND eliminado = 0",
            [':id' => $productoId]
        );
        if (!$prod) return ['disponible' => false, 'motivo' => 'Producto inexistente'];

        if ((int)$prod['controla_stock'] === 1) {
            return (float)$prod['stock_actual'] >= 1
                ? ['disponible' => true,  'motivo' => null]
                : ['disponible' => false, 'motivo' => 'Sin stock de ' . $prod['nombre']];
        }

        $receta = $this->_recetaDeProductoNoControlado($productoId);
        if (!$receta) return ['disponible' => true, 'motivo' => null];

        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        // La variante "detallado" en vez de verificarFaltantesParaVenta(): esa devuelve el
        // texto ya armado con las cantidades ("necesita 300, hay 120"), y el motivo que ve
        // MenuApp solo tiene que nombrar lo que falta, no publicar el stock del comercio.
        $faltantes = (new RecetaModel())->verificarFaltantesDetalladoParaVenta((int)$receta['receta_id'], 1);
        if (!$faltantes) return ['disponible' => true, 'motivo' => null];

        $nombres = array_slice(array_column($faltantes, 'nombre'), 0, 3);
        return ['disponible' => false, 'motivo' => 'Sin stock de ' . implode(', ', $nombres)];
    }

    /**
     * ¿Alcanza el stock para UNA selección de esta opción (gusto/adicional)? Reusa el mismo
     * _necesarioOpcion() + _faltanteOpcion() que usa registrar(), con cantidad de producto 1
     * y una sola selección en la línea, así el criterio es exactamente el de una venta real.
     *
     * $opcion tiene que venir con las columnas que devuelve MenuAppCatalogoModel::
     * opcionesPublicadas() (id, nombre, grupo_id, materia_prima_id, insumo_id, producto_id,
     * cantidad_consumida); $productoId es el producto del menú al que está asignada, porque
     * el reparto (producto_grupos_opciones.cantidad_a_repartir) se configura por producto.
     */
    public function disponibilidadOpcion(array $opcion, int $productoId): array
    {
        $necesario = $this->_necesarioOpcion($productoId, $opcion, 1.0, 1.0, 1.0);
        if ($necesario <= 0) return ['disponible' => true, 'motivo' => null];

        $faltante = $this->_faltanteOpcion($opcion, $necesario);
        if (!$faltante) return ['disponible' => true, 'motivo' => null];

        return ['disponible' => false, 'motivo' => 'Sin stock de ' . $faltante['nombre']];
    }

    /** Productos del menú a los que está asignado el grupo de una opción (para disponibilidadOpcion()). */
    public function productosDeGrupoOpcion(int $grupoId): array
    {
        return array_map('intval', array_column($this->queryAll(
            "SELECT pgo.producto_id
             FROM producto_grupos_opciones pgo
             JOIN productos p ON p.id = pgo.producto_id
             WHERE pgo.grupo_id = :gid AND p.ecommerce = 1 AND p.activo = 1 AND p.eliminado = 0",
            [':gid' => $grupoId]
        ), 'producto_id'));
    }

    /**
     * Imputa a la caja del turno abierto una venta cobrada en mano (efectivo o
     * transferencia) que se registró sin turno — el caso de MenuApp: la venta se crea al
     * ACEPTAR el pedido (D5) pero la plata entra recién al ENTREGARLO (D6).
     *
     * Idempotente: si ya hay un caja_movimientos para esta venta, no hace nada y devuelve
     * ['ya_cobrada' => true]. Si no hay ningún turno abierto, la venta queda con
     * cobro_pendiente = 1 para imputarla a mano al abrir el turno siguiente.
     *
     * @return array{ok: bool, ya_cobrada: bool, turno_id: ?int, cobro_pendiente: bool}
     */
    public function cobrarEnCaja(int $ventaId): array
    {
        $venta = $this->findById($ventaId);
        if (!$venta || $venta['estado'] === 'anulada') {
            return ['ok' => false, 'ya_cobrada' => false, 'turno_id' => null, 'cobro_pendiente' => false];
        }

        $yaHayMovimiento = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM caja_movimientos WHERE venta_id = :vid AND tipo = 'ingreso'",
            [':vid' => $ventaId]
        );
        if ($yaHayMovimiento > 0) {
            return ['ok' => true, 'ya_cobrada' => true, 'turno_id' => (int)$venta['turno_id'] ?: null, 'cobro_pendiente' => false];
        }

        $turnoId = $this->queryScalar(
            "SELECT id FROM turnos WHERE estado = 'abierto' AND eliminado = 0 ORDER BY id DESC LIMIT 1"
        );

        if (!$turnoId) {
            $this->update($ventaId, ['cobro_pendiente' => 1]);
            return ['ok' => true, 'ya_cobrada' => false, 'turno_id' => null, 'cobro_pendiente' => true];
        }

        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $conn->prepare(
                "INSERT INTO caja_movimientos
                    (turno_id, tipo, monto, descripcion, venta_id, usuario_id, metodo_pago, created_at, updated_at)
                 VALUES (:tid, 'ingreso', :monto, :desc, :vid, :uid, :metodo, NOW(), NOW())"
            )->execute([
                ':tid'    => (int)$turnoId,
                ':monto'  => (float)$venta['total_con_iva'],
                ':desc'   => 'Venta #' . $ventaId . ' (MenuApp, entregada)',
                ':vid'    => $ventaId,
                ':uid'    => (int)$venta['usuario_id'],
                ':metodo' => $venta['metodo_pago'],
            ]);

            // turno_id en la venta: así aparece en el detalle del turno y en el historial
            // de caja como cualquier otra venta cobrada en ese turno.
            $this->update($ventaId, ['turno_id' => (int)$turnoId, 'cobro_pendiente' => 0]);

            $conn->commit();
            return ['ok' => true, 'ya_cobrada' => false, 'turno_id' => (int)$turnoId, 'cobro_pendiente' => false];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Revierte el ingreso de caja de una venta que se anula después de haberse cobrado en
     * mano (ver 3.5 del checklist). No borra el movimiento original: deja un egreso que lo
     * compensa, para que el arqueo del turno siga siendo auditable y se vea qué pasó.
     * Idempotente: no agrega un segundo egreso si ya hay uno.
     */
    public function revertirCobroEnCaja(int $ventaId): bool
    {
        $ingreso = $this->queryOne(
            "SELECT * FROM caja_movimientos WHERE venta_id = :vid AND tipo = 'ingreso' ORDER BY id LIMIT 1",
            [':vid' => $ventaId]
        );
        if (!$ingreso) return false;

        $yaRevertido = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM caja_movimientos WHERE venta_id = :vid AND tipo = 'egreso'",
            [':vid' => $ventaId]
        );
        if ($yaRevertido > 0) return false;

        $this->query(
            "INSERT INTO caja_movimientos
                (turno_id, tipo, monto, descripcion, venta_id, usuario_id, metodo_pago, created_at, updated_at)
             VALUES (:tid, 'egreso', :monto, :desc, :vid, :uid, :metodo, NOW(), NOW())",
            [
                ':tid'    => $ingreso['turno_id'],
                ':monto'  => (float)$ingreso['monto'],
                ':desc'   => 'Anulación de la venta #' . $ventaId . ' (MenuApp)',
                ':vid'    => $ventaId,
                ':uid'    => $ingreso['usuario_id'],
                ':metodo' => $ingreso['metodo_pago'],
            ]
        );
        return true;
    }

    /**
     * Cuántos pedidos de MenuApp se entregaron y se cobraron en mano pero todavía no
     * entraron a ninguna caja. Se avisa antes de cerrar el turno (tarea 6.5): esa plata
     * está en el negocio y, si se cierra, queda para imputar en el turno siguiente.
     * Devuelve 0 si falta la migración, por el mismo motivo que cobrosPendientes().
     */
    public function contarCobrosPendientesMenuApp(): int
    {
        try {
            return (int)$this->queryScalar(
                "SELECT COUNT(*) FROM ventas
                 WHERE cobro_pendiente = 1 AND origen = 'menuapp'
                   AND eliminado = 0 AND estado = 'completada'"
            );
        } catch (\Throwable $e) {
            logApp('contarCobrosPendientesMenuApp(): ' . $e->getMessage() . ' — ¿falta correr database/menuapp_integracion_migration.sql?');
            return 0;
        }
    }

    /**
     * Ventas cobradas en mano todavía sin imputar a ninguna caja — se listan en /caja
     * (tarea 4.4). El try/catch cubre el caso de que todavía no se haya corrido
     * database/menuapp_integracion_migration.sql: Caja es una pantalla de uso diario y no
     * puede quedar caída por un panel informativo, así que sin las columnas nuevas
     * simplemente no se muestra nada. Mismo criterio que
     * Controller::generarGastosRecurrentesSiCorresponde().
     */
    public function cobrosPendientes(): array
    {
        try {
            return $this->queryAll(
                "SELECT v.id, v.total_con_iva, v.metodo_pago, v.origen, v.referencia_externa, v.created_at,
                        c.nombre AS cliente_nombre
                 FROM ventas v
                 LEFT JOIN clientes c ON c.id = v.cliente_id
                 WHERE v.cobro_pendiente = 1 AND v.eliminado = 0 AND v.estado = 'completada'
                 ORDER BY v.created_at"
            );
        } catch (\Throwable $e) {
            logApp('cobrosPendientes(): ' . $e->getMessage() . ' — ¿falta correr database/menuapp_integracion_migration.sql?');
            return [];
        }
    }

    public function buscarProductos(string $q): array
    {
        $like = "%$q%";
        return $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.precio_venta, p.iva AS iva_porcentaje,
                    p.stock_actual, p.stock_minimo, p.controla_stock,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad,
                    c.nombre AS categoria,
                    (EXISTS(SELECT 1 FROM producto_grupos_opciones pgo WHERE pgo.producto_id = p.id)
                        OR EXISTS(
                            SELECT 1 FROM producto_promo_detalle ppd
                            JOIN producto_grupos_opciones pgo2 ON pgo2.producto_id = ppd.componente_producto_id
                            WHERE ppd.producto_id = p.id
                        )) AS tiene_opciones
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1
               AND (p.nombre LIKE :q OR p.codigo LIKE :q2)
             ORDER BY p.nombre
             LIMIT 30",
            [':q' => $like, ':q2' => $like]
        );
    }

    /** Categorías de productos con al menos un producto activo, para el selector visual del POS */
    public function categoriasPos(): array
    {
        return $this->queryAll(
            "SELECT c.id, c.nombre, COUNT(p.id) AS total_productos
             FROM categorias c
             JOIN productos p ON p.categoria_id = c.id AND p.eliminado=0 AND p.activo=1
             WHERE c.eliminado=0 AND c.activo=1 AND c.tipo='producto'
             GROUP BY c.id, c.nombre
             HAVING total_productos > 0
             ORDER BY c.nombre"
        );
    }

    /** Productos activos de una categoría, para el selector visual del POS */
    public function productosPorCategoria(int $categoriaId): array
    {
        return $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.precio_venta, p.iva AS iva_porcentaje,
                    p.stock_actual, p.stock_minimo, p.controla_stock,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad,
                    c.nombre AS categoria,
                    (EXISTS(SELECT 1 FROM producto_grupos_opciones pgo WHERE pgo.producto_id = p.id)
                        OR EXISTS(
                            SELECT 1 FROM producto_promo_detalle ppd
                            JOIN producto_grupos_opciones pgo2 ON pgo2.producto_id = ppd.componente_producto_id
                            WHERE ppd.producto_id = p.id
                        )) AS tiene_opciones
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1 AND p.categoria_id = :cid
             ORDER BY p.nombre",
            [':cid' => $categoriaId]
        );
    }

    /**
     * Catálogo completo (todos los productos activos + categorías con productos),
     * para que el POS lo cachee en IndexedDB y pueda seguir vendiendo sin conexión.
     */
    public function catalogoOffline(): array
    {
        $productos = $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.precio_venta, p.iva AS iva_porcentaje,
                    p.stock_actual, p.stock_minimo, p.controla_stock, p.categoria_id,
                    (SELECT abreviatura FROM unidades_medida WHERE id=p.unidad_medida_id) AS unidad,
                    c.nombre AS categoria
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.eliminado=0 AND p.activo=1
             ORDER BY p.nombre"
        );

        return ['productos' => $productos, 'categorias' => $this->categoriasPos()];
    }

    public function getTurnoAbierto(int $usuarioId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM turnos WHERE usuario_id=:uid AND estado='abierto' AND eliminado=0 LIMIT 1",
            [':uid' => $usuarioId]
        ) ?: null;
    }

    /** Marcadores 'notas' de ventas ya importadas desde un ERP anterior (para evitar duplicados al reimportar) */
    public function getNotasImportadasExistentes(): array
    {
        $rows = $this->queryAll(
            "SELECT notas FROM ventas WHERE notas LIKE 'Importado ERP anterior%'"
        );
        return array_column($rows, 'notas');
    }

    /**
     * Registra una venta histórica importada desde un ERP anterior.
     * A diferencia de registrar(), NO descuenta stock ni genera movimiento de caja,
     * ya que se trata de un registro retroactivo con fines de historial/reportes.
     */
    public function registrarImportada(array $cabecera, array $detalle): int
    {
        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $ventaId = $this->insert($cabecera);

            $stmtDet = $conn->prepare(
                "INSERT INTO ventas_detalle
                    (venta_id, producto_id, cantidad, precio_unitario, descuento_pct, subtotal, iva_porcentaje, subtotal_con_iva)
                 VALUES (:vid, :pid, :cant, :precio, :desc, :sub, :iva, :sub_iva)"
            );

            foreach ($detalle as $item) {
                $stmtDet->execute([
                    ':vid'    => $ventaId,
                    ':pid'    => (int)$item['producto_id'],
                    ':cant'   => (float)$item['cantidad'],
                    ':precio' => (float)$item['precio_unitario'],
                    ':desc'   => (float)($item['descuento_pct'] ?? 0),
                    ':sub'    => (float)$item['subtotal'],
                    ':iva'    => (float)$item['iva_porcentaje'],
                    ':sub_iva'=> (float)$item['subtotal_con_iva'],
                ]);
            }

            $conn->commit();
            return $ventaId;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /** KPIs para el dashboard */
    public function totalesDelDia(): array
    {
        $hoy = date('Y-m-d');
        return [
            'total'    => (float)$this->queryScalar(
                "SELECT COALESCE(SUM(total_con_iva),0) FROM ventas WHERE eliminado=0 AND estado='completada' AND DATE(created_at)=:h",
                [':h' => $hoy]
            ),
            'cantidad' => (int)$this->queryScalar(
                "SELECT COUNT(*) FROM ventas WHERE eliminado=0 AND estado='completada' AND DATE(created_at)=:h",
                [':h' => $hoy]
            ),
        ];
    }
}
