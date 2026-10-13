<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Lista de compras: mismo espíritu que StockModel::itemsBajoMinimo() (que solo mira
 * stock_actual <= stock_minimo, un umbral fijo) pero agrega una capa de previsibilidad
 * real: cuántos días de stock quedan al ritmo de consumo real (stock_movimientos,
 * salidas de los últimos 30 días), agrupado por el proveedor habitual de cada ítem.
 *
 * Flujo:
 *  1) generarSugeridos() (cron diario) inserta candidatos como estado='sugerido' y los
 *     devuelve para armar el aviso por Chat IA (tarjeta de confirmación en lote).
 *  2) El usuario confirma/descarta ese lote (confirmarSugeridos/descartarSugeridos) o
 *     agrega algo suelto a mano en cualquier momento (agregarManual, típicamente desde
 *     el Chat IA vía buscar_item_por_nombre + pedir_confirmacion).
 *  3) Lo confirmado queda 'pendiente' en la pantalla /lista-compras hasta que se compra
 *     (marcarComprado) o se descarta (quitar).
 */
class ListaComprasModel extends Model
{
    protected string $table = 'lista_compras';

    /** Umbral: por debajo de estos días de stock restante (al ritmo de consumo real), se sugiere reponer. */
    private const DIAS_UMBRAL = 7;
    /** Ventana de historial usada para calcular la velocidad de consumo diaria. */
    private const VENTANA_DIAS = 30;

    private const TABLAS = [
        'materia_prima' => 'materias_primas',
        'insumo'        => 'insumos',
        'producto'      => 'productos',
    ];

    /**
     * Candidatos a reponer: cruza stock_actual con la velocidad de consumo real
     * (salidas de stock_movimientos en los últimos 30 días) para estimar días de stock
     * restante. Si un ítem no tiene consumo reciente registrado (sin historial, o de
     * rotación muy baja) cae al criterio estático stock_actual<=stock_minimo, mismo que
     * usa StockModel::itemsBajoMinimo(), para no perder cobertura sobre ítems nuevos.
     * No incluye ítems que ya tengan una fila activa (sugerido/pendiente) en la lista.
     */
    public function candidatos(): array
    {
        $activos = $this->itemsConFilaActiva();
        $candidatos = [];

        foreach (self::TABLAS as $tipo => $tabla) {
            // Un producto 'elaborado' no se compra, se PRODUCE — queda afuera de acá a
            // propósito y tiene su propia lista (ver candidatosProduccion()), para no
            // mezclar "hay que pedirle al proveedor" con "hay que hacer un lote".
            $filtroStock = $tipo === 'producto' ? "AND controla_stock = 1 AND tipo = 'reventa'" : '';
            $items = $this->queryAll(
                "SELECT id, nombre, codigo, stock_actual, stock_minimo,
                        (SELECT abreviatura FROM unidades_medida WHERE id = `{$tabla}`.unidad_medida_id) AS unidad" .
                ($tipo === 'materia_prima' ? ', proveedor_id' : '') .
                " FROM `{$tabla}` WHERE eliminado = 0 AND activo = 1 {$filtroStock}"
            );
            if (!$items) continue;

            $consumo = $this->consumoPorItem($tipo);

            foreach ($items as $it) {
                $id = (int) $it['id'];
                if (isset($activos[$tipo][$id])) continue;

                $totalSalida     = $consumo[$id] ?? 0.0;
                $velocidadDiaria = $totalSalida / self::VENTANA_DIAS;
                $stockActual     = (float) $it['stock_actual'];
                $stockMinimo     = (float) $it['stock_minimo'];

                $diasRestantes = $velocidadDiaria > 0 ? round($stockActual / $velocidadDiaria, 1) : null;

                $esCandidato = $diasRestantes !== null
                    ? $diasRestantes <= self::DIAS_UMBRAL
                    : ($stockMinimo > 0 && $stockActual <= $stockMinimo);

                if (!$esCandidato) continue;

                $candidatos[] = [
                    'tipo_item'           => $tipo,
                    'item_id'             => $id,
                    'nombre'              => $it['nombre'],
                    'codigo'              => $it['codigo'],
                    'stock_actual'        => $stockActual,
                    'stock_minimo'        => $stockMinimo,
                    'unidad'              => $it['unidad'],
                    'dias_stock_restante' => $diasRestantes,
                    // materia_prima usaba SOLO su campo propio (materias_primas.proveedor_id)
                    // y, si nunca se lo cargaron a mano, quedaba sin proveedor para siempre —
                    // aunque tuviera compras de sobra para inferirlo (bug real: MPs recurrentes
                    // como "medialunas congeladas XL" quedaban en "Sin proveedor habitual").
                    // Ahora, igual que insumo/producto, cae a proveedorUltimaCompra() si el
                    // campo propio está vacío.
                    'proveedor_id'        => ($tipo === 'materia_prima' && ($it['proveedor_id'] ?? null) !== null)
                        ? (int) $it['proveedor_id']
                        : $this->proveedorUltimaCompra($tipo, $id),
                ];
            }
        }

        // Sin datos de velocidad (más urgente = ya bajo el mínimo) primero.
        usort($candidatos, static fn($a, $b) => ($a['dias_stock_restante'] ?? -1) <=> ($b['dias_stock_restante'] ?? -1));

        return $candidatos;
    }

    /**
     * Productos ELABORADOS con receta activa y stock por debajo del umbral — candidatos a
     * PRODUCIR, no a comprar (mismo cálculo de "días de stock restante" que candidatos(),
     * separado porque un producto elaborado no tiene proveedor: se repone produciendo un
     * lote — ver RecetaModel::producir()/recetas/ordenes — no cargando una factura de
     * compra). Solo candidatos: `controla_stock=1` (si no controla stock, se arma al
     * momento de la venta — RecetaModel::consumirParaVenta() — y no tiene sentido
     * "recomendar producir" con anticipación) y con al menos una receta activa (no
     * borrador) que lo produzca. A diferencia de candidatos(), esto NO se persiste en
     * `lista_compras` (no hay "confirmar/descartar": es una recomendación en vivo, cada
     * vez que se abre la pantalla) ni tiene proveedor.
     */
    public function candidatosProduccion(): array
    {
        $consumo = $this->consumoPorItem('producto');

        $items = $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.stock_actual, p.stock_minimo,
                    (SELECT abreviatura FROM unidades_medida WHERE id = p.unidad_medida_id) AS unidad,
                    (SELECT r.id FROM recetas r
                     WHERE r.producto_id = p.id AND r.activo = 1 AND r.eliminado = 0 AND r.es_borrador = 0
                     ORDER BY r.id LIMIT 1) AS receta_id
             FROM productos p
             WHERE p.eliminado = 0 AND p.activo = 1 AND p.tipo = 'elaborado' AND p.controla_stock = 1
               AND EXISTS (
                   SELECT 1 FROM recetas r
                   WHERE r.producto_id = p.id AND r.activo = 1 AND r.eliminado = 0 AND r.es_borrador = 0
               )"
        );

        $candidatos = [];
        foreach ($items as $it) {
            $id = (int) $it['id'];

            $totalSalida     = $consumo[$id] ?? 0.0;
            $velocidadDiaria = $totalSalida / self::VENTANA_DIAS;
            $stockActual     = (float) $it['stock_actual'];
            $stockMinimo     = (float) $it['stock_minimo'];

            $diasRestantes = $velocidadDiaria > 0 ? round($stockActual / $velocidadDiaria, 1) : null;

            $esCandidato = $diasRestantes !== null
                ? $diasRestantes <= self::DIAS_UMBRAL
                : ($stockMinimo > 0 && $stockActual <= $stockMinimo);

            if (!$esCandidato) continue;

            $candidatos[] = [
                'producto_id'         => $id,
                'receta_id'           => (int) $it['receta_id'],
                'nombre'              => $it['nombre'],
                'stock_actual'        => $stockActual,
                'stock_minimo'        => $stockMinimo,
                'unidad'              => $it['unidad'],
                'dias_stock_restante' => $diasRestantes,
            ];
        }

        usort($candidatos, static fn($a, $b) => ($a['dias_stock_restante'] ?? -1) <=> ($b['dias_stock_restante'] ?? -1));

        return $candidatos;
    }

    /** [tipo][item_id] => true para todo lo que ya está sugerido o pendiente (evita re-sugerir/duplicar). */
    private function itemsConFilaActiva(): array
    {
        $rows = $this->queryAll("SELECT tipo_item, item_id FROM lista_compras WHERE estado IN ('sugerido','pendiente')");
        $out = [];
        foreach ($rows as $r) $out[$r['tipo_item']][(int) $r['item_id']] = true;
        return $out;
    }

    /** [item_id] => total salido en la ventana, para un tipo de ítem, en una sola query agrupada. */
    private function consumoPorItem(string $tipo): array
    {
        $rows = $this->queryAll(
            "SELECT item_id, SUM(cantidad) AS total
             FROM stock_movimientos
             WHERE tipo_item = :tipo AND tipo_mov = 'salida'
               AND created_at >= (NOW() - INTERVAL :dias DAY)
             GROUP BY item_id",
            [':tipo' => $tipo, ':dias' => self::VENTANA_DIAS]
        );
        $out = [];
        foreach ($rows as $r) $out[(int) $r['item_id']] = (float) $r['total'];
        return $out;
    }

    /**
     * Proveedor de la última factura de compra que incluyó este ítem — fallback para
     * cualquier tipo (materia_prima primero intenta su propio campo `proveedor_id` en
     * candidatos(), y solo cae acá si ese campo está vacío).
     */
    private function proveedorUltimaCompra(string $tipo, int $itemId): ?int
    {
        $col = match ($tipo) {
            'materia_prima' => 'materia_prima_id',
            'insumo'        => 'insumo_id',
            default         => 'producto_id',
        };
        $row = $this->queryOne(
            "SELECT fc.proveedor_id
             FROM facturas_compra_detalle fcd
             JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id AND fc.eliminado = 0
             WHERE fcd.tipo_item = :tipo AND fcd.{$col} = :id
             ORDER BY fc.fecha DESC, fc.id DESC
             LIMIT 1",
            [':tipo' => $tipo, ':id' => $itemId]
        );
        return $row ? (int) $row['proveedor_id'] : null;
    }

    /** Corre candidatos() y los inserta como 'sugerido'. Devuelve las filas insertadas (con su id nuevo) para armar el aviso. */
    public function generarSugeridos(): array
    {
        $insertados = [];
        foreach ($this->candidatos() as $c) {
            $id = $this->insert([
                'tipo_item'           => $c['tipo_item'],
                'item_id'             => $c['item_id'],
                'estado'              => 'sugerido',
                'origen'              => 'automatico',
                'dias_stock_restante' => $c['dias_stock_restante'],
                'proveedor_id'        => $c['proveedor_id'],
            ]);
            $c['id'] = $id;
            $insertados[] = $c;
        }
        return $insertados;
    }

    /**
     * Agrega (o reactiva) un ítem directo a la lista, ya confirmado. Usado por el alta
     * manual (chat o pantalla — Órdenes de Compra en /lista-compras, y desde Productos/
     * Stock/Inventario físico). `$proveedorId` permite forzar el proveedor a mano (ej.
     * cuando el ítem no tiene proveedor habitual o se lo quiere comprar puntualmente a
     * otro) — si se omite, se resuelve automático (proveedor habitual de la MP, o el de
     * la última compra para insumo/producto). `$estado` deja elegir si entra directo como
     * 'pendiente' (Orden de Compra — se va a comprar sí o sí) o como 'sugerido'
     * (Recomendaciones — a confirmar después); si ya existe una fila activa y se pide
     * 'pendiente', la promueve (mismo criterio de siempre); si se pide 'sugerido' y ya
     * existe en cualquiera de los dos estados, se deja tal cual (nunca se "degrada" un
     * pendiente ya confirmado de vuelta a sugerido).
     */
    public function agregarManual(string $tipoItem, int $itemId, ?int $usuarioId, ?string $notas = null, ?int $proveedorId = null, string $estado = 'pendiente'): array
    {
        $existente = $this->queryOne(
            "SELECT id, estado FROM lista_compras WHERE tipo_item = ? AND item_id = ? AND estado IN ('sugerido','pendiente')",
            [$tipoItem, $itemId]
        );
        if ($existente) {
            if ($estado === 'pendiente' && $existente['estado'] === 'sugerido') {
                $this->update((int) $existente['id'], ['estado' => 'pendiente']);
            }
            return ['id' => (int) $existente['id'], 'ya_existia' => true];
        }

        if ($proveedorId === null) {
            $proveedorId = $tipoItem === 'materia_prima'
                ? $this->queryScalar('SELECT proveedor_id FROM materias_primas WHERE id = ?', [$itemId])
                : $this->proveedorUltimaCompra($tipoItem, $itemId);
        }

        $id = $this->insert([
            'tipo_item'    => $tipoItem,
            'item_id'      => $itemId,
            'estado'       => $estado === 'sugerido' ? 'sugerido' : 'pendiente',
            'origen'       => 'manual',
            'proveedor_id' => $proveedorId !== null ? (int) $proveedorId : null,
            'creado_por'   => $usuarioId,
            'notas'        => $notas,
        ]);
        return ['id' => $id, 'ya_existia' => false];
    }

    /** Pasa de 'sugerido' a 'pendiente' (el lote que el usuario confirmó desde la tarjeta del aviso diario). */
    public function confirmarSugeridos(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query("UPDATE lista_compras SET estado = 'pendiente' WHERE id IN ($in) AND estado = 'sugerido'", $ids);
        return $stmt->rowCount();
    }

    /** Descarta un lote de sugeridos (no interesa reponerlos ahora) — no se van a volver a sugerir solos hasta que se resuelva/borre esta fila. */
    public function descartarSugeridos(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->query("UPDATE lista_compras SET estado = 'descartado', resuelto_at = NOW() WHERE id IN ($in) AND estado = 'sugerido'", $ids);
        return $stmt->rowCount();
    }

    public function marcarComprado(int $id): bool
    {
        return $this->query("UPDATE lista_compras SET estado = 'comprado', resuelto_at = NOW() WHERE id = ? AND estado = 'pendiente'", [$id])->rowCount() > 0;
    }

    /**
     * Igual que marcarComprado() pero por ítem en vez de por fila — la llama
     * FacturaCompraModel cada vez que se registra/edita una compra real (UI, chat o
     * importación), para que si ese ítem estaba en la lista (pendiente o todavía sugerido
     * sin confirmar) se cierre solo, sin que haya que ir a tildarlo a mano. Ver
     * FacturaCompraModel::_marcarCompradoEnListaDeCompras().
     */
    public function marcarCompradoPorItem(string $tipoItem, int $itemId): int
    {
        return $this->query(
            "UPDATE lista_compras SET estado = 'comprado', resuelto_at = NOW()
             WHERE tipo_item = ? AND item_id = ? AND estado IN ('pendiente', 'sugerido')",
            [$tipoItem, $itemId]
        )->rowCount();
    }

    public function quitar(int $id): bool
    {
        return $this->query("UPDATE lista_compras SET estado = 'descartado', resuelto_at = NOW() WHERE id = ? AND estado IN ('pendiente','sugerido')", [$id])->rowCount() > 0;
    }

    /** Pendientes + sugeridos, con nombre/proveedor resueltos, para la pantalla y para el resumen del chat. */
    public function listarActivos(): array
    {
        $rows = $this->queryAll(
            "SELECT lc.*, pr.razon_social AS proveedor_nombre
             FROM lista_compras lc
             LEFT JOIN proveedores pr ON pr.id = lc.proveedor_id
             WHERE lc.estado IN ('sugerido','pendiente')
             ORDER BY (lc.dias_stock_restante IS NULL), lc.dias_stock_restante ASC, lc.created_at ASC"
        );

        // Nombre/stock actual "en vivo" (no la foto de cuando se sugirió) — una query por tabla, no por fila.
        $porTabla = [];
        foreach ($rows as $r) $porTabla[$r['tipo_item']][] = (int) $r['item_id'];

        $nombres = [];
        foreach ($porTabla as $tipo => $ids) {
            $tabla = self::TABLAS[$tipo];
            $ids   = array_unique($ids);
            $in    = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->queryAll("SELECT id, nombre, codigo, stock_actual FROM `{$tabla}` WHERE id IN ($in)", $ids) as $it) {
                $nombres[$tipo][(int) $it['id']] = $it;
            }
        }

        foreach ($rows as &$r) {
            $info = $nombres[$r['tipo_item']][(int) $r['item_id']] ?? null;
            $r['nombre']       = $info['nombre']       ?? '(eliminado)';
            $r['codigo']       = $info['codigo']       ?? null;
            $r['stock_actual'] = $info['stock_actual'] ?? null;
        }
        unset($r);

        return $rows;
    }

    /** Agrupado por proveedor (null = "Sin proveedor habitual") para la pantalla y el resumen de texto. */
    public function agrupadoPorProveedor(): array
    {
        $grupos = [];
        foreach ($this->listarActivos() as $r) {
            $clave = $r['proveedor_nombre'] ?? 'Sin proveedor habitual';
            $grupos[$clave][] = $r;
        }
        return $grupos;
    }

    /**
     * Igual que agrupadoPorProveedor() pero separando 'sugerido' de 'pendiente' en dos
     * bloques — la pantalla /lista-compras los muestra en dos secciones bien distintas:
     * "Recomendaciones de compra" (sugeridos, todavía sin confirmar) y "Órdenes de
     * Compra" (pendientes, lo que realmente se va a comprar). agrupadoPorProveedor()
     * queda intacto porque ChatController lo usa tal cual para el resumen del chat.
     */
    public function agrupadoPorProveedorYEstado(): array
    {
        $sugeridos  = [];
        $pendientes = [];
        foreach ($this->listarActivos() as $r) {
            $clave = $r['proveedor_nombre'] ?? 'Sin proveedor habitual';
            if ($r['estado'] === 'sugerido') {
                $sugeridos[$clave][] = $r;
            } else {
                $pendientes[$clave][] = $r;
            }
        }
        return ['sugeridos' => $sugeridos, 'pendientes' => $pendientes];
    }
}
