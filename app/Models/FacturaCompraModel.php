<?php
require_once BASE_PATH . '/app/Models/Model.php';

class FacturaCompraModel extends Model
{
    protected string $table      = 'facturas_compra';
    protected string $primaryKey = 'id';

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['fc.eliminado = 0'];
        $params = [];

        if (!empty($filtros['proveedor_id'])) {
            $where[]  = 'fc.proveedor_id = :proveedor_id';
            $params[':proveedor_id'] = (int)$filtros['proveedor_id'];
        }
        if (!empty($filtros['desde'])) {
            $where[]  = 'fc.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'fc.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'fc.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT fc.*, p.razon_social AS proveedor_nombre,
                    u.nombre AS usuario_nombre,
                    (SELECT GROUP_CONCAT(DISTINCT COALESCE(mp.nombre, ins.nombre, prod.nombre)
                                        ORDER BY COALESCE(mp.nombre, ins.nombre, prod.nombre)
                                        SEPARATOR ', ')
                     FROM facturas_compra_detalle fcd
                     LEFT JOIN materias_primas mp   ON mp.id  = fcd.materia_prima_id
                     LEFT JOIN insumos         ins  ON ins.id = fcd.insumo_id
                     LEFT JOIN productos       prod ON prod.id= fcd.producto_id
                     WHERE fcd.factura_compra_id = fc.id
                    ) AS items_nombres
             FROM facturas_compra fc
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             LEFT JOIN usuarios    u ON u.id = fc.usuario_id
             WHERE $cond
             ORDER BY fc.fecha DESC, fc.id DESC",
            $params
        );
    }

    /**
     * Devuelve una fila por línea de detalle con todos los datos de cabecera incluidos.
     * Usado para exportar a CSV respetando los mismos filtros que la vista de listado.
     */
    public function findParaExportar(array $filtros = []): array
    {
        $where  = ['fc.eliminado = 0'];
        $params = [];

        if (!empty($filtros['proveedor_id'])) {
            $where[]  = 'fc.proveedor_id = :proveedor_id';
            $params[':proveedor_id'] = (int)$filtros['proveedor_id'];
        }
        if (!empty($filtros['desde'])) {
            $where[]  = 'fc.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]  = 'fc.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'fc.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        $cond = implode(' AND ', $where);

        return $this->queryAll(
            "SELECT
                fc.id                                                           AS id_factura,
                fc.fecha,
                p.razon_social                                                  AS proveedor,
                p.cuit                                                          AS proveedor_cuit,
                fc.nro_comprobante,
                fc.tipo_comprobante,
                fc.condicion_pago,
                fc.estado,
                fc.notas,
                fcd.tipo_item,
                COALESCE(mp.codigo,  ins.codigo,  prod.codigo)                 AS item_codigo,
                COALESCE(mp.nombre,  ins.nombre,  prod.nombre)                 AS item_nombre,
                COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_p.abreviatura) AS unidad,
                fcd.cantidad,
                fcd.cantidad_contenida,
                fcd.precio_unitario,
                fcd.descuento_pct,
                fcd.importe_dto,
                fcd.subtotal                                                    AS subtotal_linea,
                fcd.iva_porcentaje,
                fcd.subtotal_con_iva                                            AS total_linea,
                fcd.monto_no_gravado                                            AS no_gravado_linea,
                fcd.observaciones                                               AS obs_item,
                fc.subtotal                                                     AS subtotal_factura,
                fc.total_iva                                                    AS iva_factura,
                fc.monto_no_gravado                                             AS no_gravado_factura,
                fc.total_con_iva                                                AS total_factura
             FROM facturas_compra fc
             LEFT JOIN proveedores p           ON p.id    = fc.proveedor_id
             LEFT JOIN facturas_compra_detalle fcd ON fcd.factura_compra_id = fc.id
             LEFT JOIN materias_primas mp       ON mp.id   = fcd.materia_prima_id
             LEFT JOIN insumos         ins      ON ins.id  = fcd.insumo_id
             LEFT JOIN productos       prod     ON prod.id = fcd.producto_id
             LEFT JOIN unidades_medida um_mp    ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins   ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_p     ON um_p.id   = prod.unidad_medida_id
             WHERE $cond
             ORDER BY fc.fecha DESC, fc.id DESC, fcd.id ASC",
            $params
        );
    }

    public function findByIdConDetalle(int $id): ?array
    {
        $cabecera = $this->queryOne(
            "SELECT fc.*, p.razon_social AS proveedor_nombre,
                    p.cuit, p.condicion_iva, p.direccion AS proveedor_direccion
             FROM facturas_compra fc
             LEFT JOIN proveedores p ON p.id = fc.proveedor_id
             WHERE fc.id = :id AND fc.eliminado = 0",
            [':id' => $id]
        );
        if (!$cabecera) return null;

        $detalle = $this->queryAll(
            "SELECT fcd.*,
                    COALESCE(mp.nombre, ins.nombre, prod.nombre) AS item_nombre,
                    COALESCE(mp.codigo, ins.codigo, prod.codigo) AS item_codigo,
                    COALESCE(mp.tipo_gasto, ins.tipo_gasto, prod.tipo_gasto) AS item_tipo_gasto,
                    COALESCE(um_mp.abreviatura, um_ins.abreviatura, um_p.abreviatura) AS unidad
             FROM facturas_compra_detalle fcd
             LEFT JOIN materias_primas mp   ON mp.id  = fcd.materia_prima_id
             LEFT JOIN insumos         ins  ON ins.id = fcd.insumo_id
             LEFT JOIN productos       prod ON prod.id= fcd.producto_id
             LEFT JOIN unidades_medida um_mp  ON um_mp.id  = mp.unidad_medida_id
             LEFT JOIN unidades_medida um_ins ON um_ins.id = ins.unidad_medida_id
             LEFT JOIN unidades_medida um_p   ON um_p.id  = prod.unidad_medida_id
             WHERE fcd.factura_compra_id = :id
             ORDER BY fcd.id",
            [':id' => $id]
        );

        $cabecera['detalle'] = $detalle;
        return $cabecera;
    }

    public function getPagosDeFactura(int $facturaId): array
    {
        return $this->queryAll(
            "SELECT pp.id, pp.fecha, pp.concepto,
                    pp.monto_efectivo, pp.monto_transferencia, pp.monto_mercado_pago,
                    pp.monto_debito, pp.monto_cheque, pp.monto_otro, pp.monto_total,
                    pp.referencia_mp, pp.comprobante_path,
                    pp.origen_pago, pp.nombre_socio, pp.socio_id,
                    pp.estado, pp.usuario_id,
                    u.nombre AS usuario_nombre,
                    pf.monto_aplicado
             FROM pagos_facturas pf
             JOIN pagos_proveedores pp ON pp.id = pf.pago_id
             LEFT JOIN usuarios u ON u.id = pp.usuario_id
             WHERE pf.factura_id = :id AND pp.eliminado = 0
             ORDER BY pp.fecha DESC, pp.id DESC",
            [':id' => $facturaId]
        );
    }

    /**
     * Compara, para cada línea de materia prima O insumo, el cantidad_contenida que se
     * está por guardar contra el que se usó con más frecuencia en compras anteriores del
     * MISMO ítem, y arma avisos NO bloqueantes (la factura se guarda igual) cuando difiere
     * mucho. Ataca en el momento de carga el caso real que rompió el Costo Promedio
     * Ponderado en producción: la misma materia prima cargada unas veces como "1
     * unidad = X gramos" y otras como "X unidades sueltas", sin que nada avisara antes
     * de guardar (ver docs/diagnostico-costos-mp.md §6) — el mismo patrón de error de
     * carga se puede dar con insumos comprados por bulto/caja, ahora que sí aplican la
     * conversión (ver FacturaCompraModel::actualizarStock()).
     * $facturaIdExcluir: al editar una factura, no comparar contra el historial de la
     * propia factura que se está corrigiendo.
     */
    public function advertenciasCantidadContenida(array $detalle, ?int $facturaIdExcluir = null): array
    {
        $avisos = [];
        foreach ($detalle as $item) {
            $mpId  = (int)($item['materia_prima_id'] ?? 0);
            $insId = (int)($item['insumo_id'] ?? 0);
            if (!$mpId && !$insId) continue;

            $tabla = $mpId ? 'materias_primas' : 'insumos';
            $col   = $mpId ? 'materia_prima_id' : 'insumo_id';
            $id    = $mpId ?: $insId;

            $ccNueva = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;

            $params = [':id' => $id];
            $condExcluir = '';
            if ($facturaIdExcluir) {
                $condExcluir = ' AND fc.id <> :fid';
                $params[':fid'] = $facturaIdExcluir;
            }

            $habitual = $this->queryOne(
                "SELECT COALESCE(fcd.cantidad_contenida,1) AS cc, COUNT(*) AS n
                 FROM facturas_compra_detalle fcd
                 JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
                 WHERE fcd.{$col} = :id AND fc.eliminado = 0 AND fc.estado <> 'anulada'{$condExcluir}
                 GROUP BY COALESCE(fcd.cantidad_contenida,1)
                 ORDER BY n DESC, cc DESC
                 LIMIT 1",
                $params
            );
            if (!$habitual) continue; // sin historial previo: nada con qué comparar

            $ccHabitual = (float)$habitual['cc'];
            if ($ccHabitual <= 0) continue;

            $ratio = $ccNueva / $ccHabitual;
            if ($ratio > 3.0 || $ratio < (1 / 3)) {
                $nombre = (string)$this->queryScalar("SELECT nombre FROM `{$tabla}` WHERE id = ?", [$id]);
                $avisos[] = sprintf(
                    '"%s": esta línea usa Cant. Cont. = %s, pero en compras anteriores de este ítem lo más usado fue %s. Revisá si corresponde — un valor distinto puede descuadrar el costo promedio.',
                    $nombre,
                    $this->_fmtCant($ccNueva),
                    $this->_fmtCant($ccHabitual)
                );
            }
        }
        return $avisos;
    }

    /** Formatea un número para mostrar en un aviso, sin ceros ni coma decimal de más (ej. 250.000 → "250"). */
    private function _fmtCant(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    public function guardar(array $cabecera, array $detalle): int
    {
        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            // Insertar cabecera
            $facturaId = $this->insert($cabecera);

            // Insertar detalle y actualizar stock con costo promedio ponderado
            $stmtDet = $conn->prepare(
                "INSERT INTO facturas_compra_detalle
                    (factura_compra_id, tipo_item, materia_prima_id, insumo_id, producto_id,
                     cantidad, cantidad_contenida, precio_unitario, descuento_pct, importe_dto,
                     subtotal, iva_porcentaje, subtotal_con_iva, monto_no_gravado, observaciones)
                 VALUES (:fid, :tipo, :mp, :ins, :prod, :cant, :cc, :precio, :dto_pct, :imp_dto,
                         :sub, :iva, :sub_iva, :no_grav, :obs)"
            );

            foreach ($detalle as $item) {
                $stmtDet->execute([
                    ':fid'     => $facturaId,
                    ':tipo'    => $item['tipo_item'],
                    ':mp'      => $item['materia_prima_id'] ?: null,
                    ':ins'     => $item['insumo_id'] ?: null,
                    ':prod'    => $item['producto_id'] ?: null,
                    ':cant'    => $item['cantidad'],
                    ':cc'      => $item['cantidad_contenida'] ?? null,
                    ':precio'  => $item['precio_unitario'],
                    ':dto_pct' => $item['descuento_pct'] ?? 0,
                    ':imp_dto' => $item['importe_dto'] ?? 0,
                    ':sub'     => $item['subtotal'],
                    ':iva'     => $item['iva_porcentaje'],
                    ':sub_iva' => $item['subtotal_con_iva'],
                    ':no_grav' => $item['monto_no_gravado'] ?? 0,
                    ':obs'     => ($item['observaciones'] ?? null) ?: null,
                ]);

                $this->actualizarStock($item, true, $facturaId);
                $this->_guardarAliasSiCorresponde((int)($cabecera['proveedor_id'] ?? 0), $item);
                $this->_marcarCompradoEnListaDeCompras($item);
            }

            $conn->commit();
            $this->avisarCompraRegistrada($cabecera, $facturaId);
            return $facturaId;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Si la línea trae el texto que el usuario tipeó para buscarla ($item['descripcion_buscada'],
     * ver compras/form.php) y ese texto no es simplemente el nombre del ítem elegido, lo
     * memoriza como alias de este proveedor — para que la próxima factura del mismo
     * proveedor con la misma descripción sugiera este ítem sola (ver
     * ProveedorModel::guardarAlias() / FacturaCompraModel::buscarItems()). No crítico:
     * si falla, no debe interrumpir el guardado de la factura.
     */
    private function _guardarAliasSiCorresponde(int $proveedorId, array $item): void
    {
        if (!$proveedorId) return;
        $texto = trim((string)($item['descripcion_buscada'] ?? ''));
        if ($texto === '') return;

        $tipo   = $item['tipo_item'] ?? '';
        $itemId = (int)($item['materia_prima_id'] ?? $item['insumo_id'] ?? $item['producto_id'] ?? 0);
        if (!in_array($tipo, ['materia_prima', 'insumo', 'producto'], true) || !$itemId) return;

        try {
            require_once BASE_PATH . '/app/Models/ProveedorModel.php';
            (new ProveedorModel())->guardarAlias($proveedorId, $texto, $tipo, $itemId);
        } catch (\Throwable $e) {
            logApp('Error guardando alias de proveedor: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Si el ítem de esta línea está en la Lista de Compras (pendiente o todavía sugerido
     * sin confirmar), lo cierra solo como comprado — así la lista se mantiene al día sin
     * que nadie tenga que ir a tildarlo a mano después de cargar la compra real. Ver
     * ListaComprasModel::marcarCompradoPorItem(). Nunca debe hacer fallar el registro de
     * la compra en sí si algo sale mal acá.
     */
    private function _marcarCompradoEnListaDeCompras(array $item): void
    {
        try {
            $tipo   = $item['tipo_item'] ?? '';
            $itemId = (int)($item['materia_prima_id'] ?? $item['insumo_id'] ?? $item['producto_id'] ?? 0);
            if (!in_array($tipo, ['materia_prima', 'insumo', 'producto'], true) || !$itemId) return;

            require_once BASE_PATH . '/app/Models/ListaComprasModel.php';
            (new ListaComprasModel())->marcarCompradoPorItem($tipo, $itemId);
        } catch (\Throwable $e) {
            logApp('Error marcando lista de compras como comprada: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Avisa por push a los admins (menos a quien la acaba de cargar) que se
     * registró una compra — para que un equipo de varias personas se entere sin
     * tener que estar mirando la sección de Compras. Reusa la misma infraestructura
     * de push que ya existe para el aviso de caída de margen (RecetaModel).
     * Un error acá nunca debe hacer fallar el registro de la compra en sí.
     */
    private function avisarCompraRegistrada(array $cabecera, int $facturaId): void
    {
        try {
            require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
            require_once BASE_PATH . '/app/Models/NotificacionModel.php';

            $pushModel     = new PushSubscriptionModel();
            $suscripciones = $pushModel->findSuscripcionesAdmins();
            if (!$suscripciones || !VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

            $usuarioId = (int)($cabecera['usuario_id'] ?? 0);
            $usuario   = $this->queryOne('SELECT nombre FROM usuarios WHERE id = ?', [$usuarioId]);
            $proveedor = $this->queryOne('SELECT razon_social FROM proveedores WHERE id = ?', [(int)($cabecera['proveedor_id'] ?? 0)]);
            $total     = number_format((float)($cabecera['total_con_iva'] ?? 0), 2, ',', '.');

            $titulo = 'Nueva compra registrada';
            $cuerpo = sprintf(
                '%s cargó una compra a %s por $%s.',
                $usuario['nombre'] ?? 'Un usuario',
                $proveedor['razon_social'] ?? 'un proveedor',
                $total
            );
            $url = rtrim(BASE_URL, '/') . '/compras/ver?id=' . $facturaId;
            $payload = json_encode([
                'title' => $titulo,
                'body'  => $cuerpo,
                'url'   => $url,
                'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
            ]);

            // Mismo filtro que el push de abajo: no avisarle a quien cargó la compra.
            $destinatarios = array_filter($suscripciones, fn($s) => (int)$s['usuario_id'] !== $usuarioId);
            (new NotificacionModel())->crearParaSuscripciones($destinatarios, $titulo, $cuerpo, $url, 'compra_registrada');

            require_once BASE_PATH . '/core/WebPush.php';
            foreach ($suscripciones as $sub) {
                if ((int)$sub['usuario_id'] === $usuarioId) continue; // no avisarle a quien la cargó

                try {
                    $res = WebPush::enviar(
                        ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                        $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                    );
                    if (in_array($res['status'], [404, 410], true)) {
                        $pushModel->eliminarPorId((int)$sub['id']);
                    }
                } catch (\Throwable $e) {
                    logApp('Error enviando push de compra registrada #' . $facturaId . ': ' . $e->getMessage(), 'ERROR');
                }
            }
        } catch (\Throwable $e) {
            logApp('Error armando aviso de compra registrada #' . $facturaId . ': ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Inserta el movimiento en stock_movimientos DESPUÉS de que el UPDATE de stock_actual
     * ya corrió (lee el valor ya actualizado y reconstruye stock_antes restando/sumando
     * $delta) — mismo patrón que StockModel::ajustar(). Antes ninguna compra (ni su
     * reversión al editar/anular una factura) quedaba loggeada acá, así que el reporte de
     * Stock teórico vs físico (StockModel::stockTeoricoVsFisico()) no podía ver las
     * "Entradas" reales — ver conversación de soporte.
     */
    private function _registrarMovimientoStock(string $tipoItem, int $itemId, float $delta, ?int $facturaCompraId, string $motivo): void
    {
        $tabla        = $tipoItem === 'insumo' ? 'insumos' : ($tipoItem === 'materia_prima' ? 'materias_primas' : 'productos');
        $stockDespues = (float)$this->queryScalar("SELECT stock_actual FROM `{$tabla}` WHERE id = ?", [$itemId]);
        $stockAntes   = $stockDespues - $delta;
        $this->query(
            "INSERT INTO stock_movimientos (tipo_item, item_id, tipo_mov, origen, origen_id, cantidad, stock_antes, stock_despues, motivo, created_at)
             VALUES (:ti, :iid, :tm, 'compra', :oid, :cant, :sa, :sd, :mot, NOW())",
            [
                ':ti'   => $tipoItem,
                ':iid'  => $itemId,
                ':tm'   => $delta < 0 ? 'salida' : 'entrada',
                ':oid'  => $facturaCompraId,
                ':cant' => abs($delta),
                ':sa'   => $stockAntes,
                ':sd'   => $stockDespues,
                ':mot'  => $motivo,
            ]
        );
    }

    /**
     * Actualiza stock del ítem. Con $calcularCosto=true aplica Costo Promedio
     * Ponderado (CPP) cuando la cantidad es positiva (entrada de mercadería).
     * Las anulaciones (cantidad negativa) nunca recalculan el costo.
     * $facturaCompraId es solo para trazabilidad del historial de costos de recetas.
     */
    private function actualizarStock(array $item, bool $calcularCosto = false, ?int $facturaCompraId = null): void
    {
        $cant   = (float)$item['cantidad'];
        $precio = (float)($item['precio_unitario'] ?? 0);

        if ($item['materia_prima_id']) {
            // cantidad_contenida convierte "unidades compradas" a la unidad base de stock/receta
            // (ej: compraste 1 bidón que contiene 5000ml → al stock suman 5000, no 1). Default 1
            // para ítems que se compran y se consumen en la misma unidad — no cambia nada de lo
            // que ya funcionaba antes de este campo existir. El costo_unitario queda expresado
            // directo en la unidad base (ya no hace falta dividir por cantidad_contenida al leerlo
            // para costear una receta — ver migración database/materia_prima_stock_base_migration.sql).
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;

            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE materias_primas
                     SET costo_unitario = (stock_actual * costo_unitario + :cant * :precio) / NULLIF(stock_actual + :cantbase, 0),
                         stock_actual   = stock_actual + :cantbase2
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cantbase' => $cantBase, ':cantbase2' => $cantBase,
                     ':id'   => (int)$item['materia_prima_id']]
                );
                $this->_registrarMovimientoStock('materia_prima', (int)$item['materia_prima_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId}");

                // La cantidad_contenida de la ficha se actualiza igual que el costo unitario:
                // con cada compra que trae un valor explícito para esta línea, para que la
                // ficha siempre refleje el envase de la compra más reciente (no un valor fijo
                // cargado una vez y nunca más corregido). Si la compra no informó nada acá
                // (quedó vacío), no se toca — no hay que "resetear" a 1 por una línea incompleta.
                if (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '') {
                    $this->query(
                        "UPDATE materias_primas SET cantidad_contenida = :cc WHERE id = :id",
                        [':cc' => $contenido, ':id' => (int)$item['materia_prima_id']]
                    );
                }

                // Propagar el cambio de costo a las recetas afectadas (uso directo de
                // esta MP, y si además es variante de un ingrediente genérico, también
                // a las que usan el genérico) y avisar si el margen de algún producto
                // cae más que el umbral configurado. Cubre tanto la compra por chat
                // como por el formulario web, porque ambas pasan por acá.
                require_once BASE_PATH . '/app/Models/RecetaModel.php';
                $recetaModel = new RecetaModel();
                $recetaIds   = $recetaModel->findIdsPorMateriaPrima((int)$item['materia_prima_id']);

                $genericoId = $this->queryScalar(
                    "SELECT ingrediente_generico_id FROM materias_primas WHERE id = ?",
                    [(int)$item['materia_prima_id']]
                );
                if ($genericoId) {
                    $recetaIds = array_merge($recetaIds, $recetaModel->findIdsPorGenerico((int)$genericoId));
                }
                if ($recetaIds) {
                    $recetaModel->propagarCambioCosto(array_unique($recetaIds), $facturaCompraId, 'Recálculo por compra');
                }
            } else {
                // Reversión (anular/editar factura, $cant llega negativo): usa el mismo
                // cantidad_contenida que se guardó en esa línea al momento de la compra
                // original, para deshacer exactamente lo que se sumó entonces.
                $this->query(
                    "UPDATE materias_primas SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cantBase, ':id' => (int)$item['materia_prima_id']]
                );
                $this->_registrarMovimientoStock('materia_prima', (int)$item['materia_prima_id'], $cantBase, $facturaCompraId, 'Reversión de compra (edición/anulación de factura)');
            }
        } elseif ($item['insumo_id']) {
            // cantidad_contenida: mismo rol que en materia prima (ver arriba) — bug real
            // encontrado en producción (ver conversación de soporte): esta rama nunca
            // aplicaba cantidad_contenida, así que un insumo comprado "por bulto/caja"
            // (ej. "3 bultos de 100 potes") quedaba con costo_unitario = precio del bulto
            // entero y stock_actual sumando solo 3 en vez de 300.
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;

            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE insumos
                     SET costo_unitario = (stock_actual * costo_unitario + :cant * :precio) / NULLIF(stock_actual + :cantbase, 0),
                         stock_actual   = stock_actual + :cantbase2
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cantbase' => $cantBase, ':cantbase2' => $cantBase,
                     ':id'   => (int)$item['insumo_id']]
                );
                $this->_registrarMovimientoStock('insumo', (int)$item['insumo_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId}");

                // La cantidad_contenida de la ficha se actualiza igual que en materia prima:
                // con cada compra que trae un valor explícito para esta línea, para que la
                // ficha siempre refleje el envase de la compra más reciente.
                if (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '') {
                    $this->query(
                        "UPDATE insumos SET cantidad_contenida = :cc WHERE id = :id",
                        [':cc' => $contenido, ':id' => (int)$item['insumo_id']]
                    );
                }

                // Igual que con materias primas: propagar el recálculo a las recetas
                // que usan este insumo directamente.
                require_once BASE_PATH . '/app/Models/RecetaModel.php';
                $recetaModel = new RecetaModel();
                $recetaIds   = $recetaModel->findIdsPorInsumo((int)$item['insumo_id']);
                if ($recetaIds) {
                    $recetaModel->propagarCambioCosto($recetaIds, $facturaCompraId, 'Recálculo por compra');
                }
            } else {
                // Reversión (anular/editar factura, $cant llega negativo): usa el mismo
                // cantidad_contenida que se guardó en esa línea al momento de la compra
                // original, para deshacer exactamente lo que se sumó entonces.
                $this->query(
                    "UPDATE insumos SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cantBase, ':id' => (int)$item['insumo_id']]
                );
                $this->_registrarMovimientoStock('insumo', (int)$item['insumo_id'], $cantBase, $facturaCompraId, 'Reversión de compra (edición/anulación de factura)');
            }
        } elseif ($item['producto_id']) {
            // Mismo rol que en materia prima (ver arriba): "compré 1 caja que contiene 4
            // unidades" no debe sumar 1 al stock ni tomar el precio de la caja entera como
            // costo de 1 unidad — bug real encontrado (ver conversación de soporte): esta
            // rama nunca aplicaba cantidad_contenida, a diferencia de la de materia prima,
            // así que un producto de reventa comprado "por caja" quedaba con precio_costo
            // = precio de la caja completa y stock_actual sumando solo 1 por caja.
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;

            if ($calcularCosto && $cant > 0) {
                $this->query(
                    "UPDATE productos
                     SET precio_costo = (stock_actual * precio_costo + :cant * :precio) / NULLIF(stock_actual + :cantbase, 0),
                         stock_actual = stock_actual + :cantbase2
                     WHERE id = :id",
                    [':cant' => $cant, ':precio' => $precio, ':cantbase' => $cantBase, ':cantbase2' => $cantBase,
                     ':id'   => (int)$item['producto_id']]
                );
                $this->_registrarMovimientoStock('producto', (int)$item['producto_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId}");
            } else {
                // Reversión (anular/editar factura, $cant llega negativo): usa el mismo
                // cantidad_contenida que se guardó en esa línea al momento de la compra
                // original, para deshacer exactamente lo que se sumó entonces.
                $this->query(
                    "UPDATE productos SET stock_actual = stock_actual + :c WHERE id = :id",
                    [':c' => $cantBase, ':id' => (int)$item['producto_id']]
                );
                $this->_registrarMovimientoStock('producto', (int)$item['producto_id'], $cantBase, $facturaCompraId, 'Reversión de compra (edición/anulación de factura)');
            }
        }
    }

    /** Registra en $afectados qué ítem (tipo + id) tocó esta línea, para recalcular su costo una sola vez. */
    private function _marcarAfectado(array &$afectados, array $item): void
    {
        if (!empty($item['materia_prima_id']))    $afectados['materia_prima:' . (int)$item['materia_prima_id']] = true;
        elseif (!empty($item['insumo_id']))       $afectados['insumo:' . (int)$item['insumo_id']]               = true;
        elseif (!empty($item['producto_id']))     $afectados['producto:' . (int)$item['producto_id']]           = true;
    }

    /**
     * Usado al reaplicar la nueva versión de una línea editada: solo suma stock y
     * sincroniza cantidad_contenida en la ficha (igual que ya hace una compra nueva) —
     * el costo NO se toca acá, se recalcula una sola vez por ítem en
     * recalcularCostoDesdeHistorial() después de reinsertar todas las líneas.
     */
    private function _sumarStockYFicha(array $item, ?int $facturaCompraId = null): void
    {
        $cant = (float)$item['cantidad'];
        if ($cant <= 0) return;

        if ($item['materia_prima_id']) {
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;
            $this->query(
                "UPDATE materias_primas SET stock_actual = stock_actual + :c WHERE id = :id",
                [':c' => $cantBase, ':id' => (int)$item['materia_prima_id']]
            );
            $this->_registrarMovimientoStock('materia_prima', (int)$item['materia_prima_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId} (edición)");
            if (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '') {
                $this->query(
                    "UPDATE materias_primas SET cantidad_contenida = :cc WHERE id = :id",
                    [':cc' => $contenido, ':id' => (int)$item['materia_prima_id']]
                );
            }
        } elseif ($item['insumo_id']) {
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;
            $this->query(
                "UPDATE insumos SET stock_actual = stock_actual + :c WHERE id = :id",
                [':c' => $cantBase, ':id' => (int)$item['insumo_id']]
            );
            $this->_registrarMovimientoStock('insumo', (int)$item['insumo_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId} (edición)");
            if (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '') {
                $this->query(
                    "UPDATE insumos SET cantidad_contenida = :cc WHERE id = :id",
                    [':cc' => $contenido, ':id' => (int)$item['insumo_id']]
                );
            }
        } elseif ($item['producto_id']) {
            $contenido = (float)($item['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;
            $this->query(
                "UPDATE productos SET stock_actual = stock_actual + :c WHERE id = :id",
                [':c' => $cantBase, ':id' => (int)$item['producto_id']]
            );
            $this->_registrarMovimientoStock('producto', (int)$item['producto_id'], $cantBase, $facturaCompraId, "Compra #{$facturaCompraId} (edición)");
        }
    }

    /**
     * Recalcula costo_unitario/precio_costo de un ítem repasando TODO su historial real
     * de compras (no anuladas, de facturas no eliminadas) en orden cronológico, aplicando
     * la misma fórmula de Costo Promedio Ponderado que ya usa cada compra nueva — en vez
     * de "revertir y reaplicar" incrementalmente, que dejaba el costo viejo mezclado con
     * el nuevo (ver actualizarFactura()). Se llama solo al editar una factura o anularla,
     * nunca en cada venta ni en cada compra nueva — el historial de compras de UN ítem
     * puntual son a lo sumo unas pocas decenas de filas, así que repasarlo entero acá es
     * rápido y no afecta la performance del resto del sistema.
     */
    public function recalcularCostoDesdeHistorial(string $tipo, int $itemId, ?int $facturaCompraId = null): void
    {
        $tabla      = match ($tipo) { 'insumo' => 'insumos', 'producto' => 'productos', default => 'materias_primas' };
        $col        = match ($tipo) { 'insumo' => 'insumo_id', 'producto' => 'producto_id', default => 'materia_prima_id' };
        $campoCosto = $tipo === 'producto' ? 'precio_costo' : 'costo_unitario';

        $compras = $this->queryAll(
            "SELECT fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario
             FROM facturas_compra_detalle fcd
             JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
             WHERE fcd.tipo_item = :tipo AND fcd.{$col} = :id
               AND fc.estado != 'anulada' AND fc.eliminado = 0
             ORDER BY fc.fecha ASC, fcd.id ASC",
            [':tipo' => $tipo, ':id' => $itemId]
        );

        $costo = 0.0;
        $stock = 0.0;
        foreach ($compras as $c) {
            $cant = (float)$c['cantidad'];
            if ($cant <= 0) continue;
            // cantidad_contenida aplica a los 3 tipos por igual (materia prima, insumo y
            // producto de reventa comprados por bulto/caja/paquete) — bug real corregido acá
            // dos veces: primero para producto (ver comentario histórico más abajo en el
            // código), y ahora también para insumo, que hasta acá forzaba 1.0 siempre, así
            // que "recalcular desde compras" en un insumo comprado por bulto devolvía el
            // mismo costo inflado (precio del bulto entero) que el bug original.
            $contenido = (float)($c['cantidad_contenida'] ?? 0) ?: 1.0;
            $cantBase  = $cant * $contenido;
            $costo = ($stock * $costo + $cant * (float)$c['precio_unitario']) / max($stock + $cantBase, 0.0001);
            $stock += $cantBase;
        }

        $this->query("UPDATE {$tabla} SET {$campoCosto} = :c WHERE id = :id", [':c' => $costo, ':id' => $itemId]);

        if ($tipo === 'producto') return; // sin propagación a recetas para productos

        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $recetaModel = new RecetaModel();
        $recetaIds   = $tipo === 'insumo'
            ? $recetaModel->findIdsPorInsumo($itemId)
            : $recetaModel->findIdsPorMateriaPrima($itemId);

        if ($tipo === 'materia_prima') {
            $genericoId = $this->queryScalar('SELECT ingrediente_generico_id FROM materias_primas WHERE id = ?', [$itemId]);
            if ($genericoId) {
                $recetaIds = array_merge($recetaIds, $recetaModel->findIdsPorGenerico((int)$genericoId));
            }
        }
        if ($recetaIds) {
            $recetaModel->propagarCambioCosto(array_unique($recetaIds), $facturaCompraId, 'Recálculo por edición de factura');
        }
    }

    /**
     * Edita una factura existente. El costo (CPP) de cada ítem afectado NO se recalcula
     * incrementalmente acá — se recalcula aparte, una sola vez por ítem, repasando su
     * historial real de compras completo (recalcularCostoDesdeHistorial()). Antes se
     * revertía solo el stock de las líneas viejas (nunca el costo) y se reaplicaba el
     * costo nuevo mezclándolo incrementalmente sobre esa base sin limpiar — cada edición
     * de una factura vieja sumaba el aporte de esa misma compra otra vez, inflando (o a
     * veces hasta volviendo negativo) el costo con cada guardado repetido.
     */
    public function actualizarFactura(int $id, array $cabecera, array $detalle): void
    {
        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $afectados = [];

            // Revertir stock de los ítems anteriores (el costo se recalcula aparte, abajo)
            $stmt = $conn->prepare("SELECT * FROM facturas_compra_detalle WHERE factura_compra_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $item) {
                $item['cantidad'] = -1 * (float)$item['cantidad'];
                $this->actualizarStock($item, false, $id);
                $this->_marcarAfectado($afectados, $item);
            }

            // Eliminar detalle anterior
            $conn->prepare("DELETE FROM facturas_compra_detalle WHERE factura_compra_id = ?")->execute([$id]);

            // Actualizar cabecera
            $this->update($id, $cabecera);

            // Insertar nuevo detalle y sumar stock (el costo se recalcula aparte, abajo)
            $stmtDet = $conn->prepare(
                "INSERT INTO facturas_compra_detalle
                    (factura_compra_id, tipo_item, materia_prima_id, insumo_id, producto_id,
                     cantidad, cantidad_contenida, precio_unitario, descuento_pct, importe_dto,
                     subtotal, iva_porcentaje, subtotal_con_iva, monto_no_gravado, observaciones)
                 VALUES (:fid, :tipo, :mp, :ins, :prod, :cant, :cc, :precio, :dto_pct, :imp_dto,
                         :sub, :iva, :sub_iva, :no_grav, :obs)"
            );
            foreach ($detalle as $item) {
                $stmtDet->execute([
                    ':fid'     => $id,
                    ':tipo'    => $item['tipo_item'],
                    ':mp'      => $item['materia_prima_id'] ?: null,
                    ':ins'     => $item['insumo_id'] ?: null,
                    ':prod'    => $item['producto_id'] ?: null,
                    ':cant'    => $item['cantidad'],
                    ':cc'      => $item['cantidad_contenida'] ?? null,
                    ':precio'  => $item['precio_unitario'],
                    ':dto_pct' => $item['descuento_pct'] ?? 0,
                    ':imp_dto' => $item['importe_dto'] ?? 0,
                    ':sub'     => $item['subtotal'],
                    ':iva'     => $item['iva_porcentaje'],
                    ':sub_iva' => $item['subtotal_con_iva'],
                    ':no_grav' => $item['monto_no_gravado'] ?? 0,
                    ':obs'     => ($item['observaciones'] ?? null) ?: null,
                ]);
                $this->_sumarStockYFicha($item, $id);
                $this->_marcarAfectado($afectados, $item);
                $this->_guardarAliasSiCorresponde((int)($cabecera['proveedor_id'] ?? 0), $item);
                $this->_marcarCompradoEnListaDeCompras($item);
            }

            // Un solo recálculo de costo por ítem afectado (aunque haya aparecido en varias
            // líneas viejas/nuevas), repasando su historial real de compras ya actualizado.
            foreach ($afectados as $key => $_) {
                [$tipo, $itemId] = explode(':', $key, 2);
                $this->recalcularCostoDesdeHistorial($tipo, (int)$itemId, $id);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $usuarioId): bool
    {
        $factura = $this->findByIdConDetalle($id);
        if (!$factura || $factura['estado'] === 'anulada') return false;

        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $this->update($id, ['estado' => 'anulada']);

            // Revertir stock, y recalcular el costo de cada ítem afectado repasando su
            // historial real de compras (que ya no va a incluir esta, al quedar 'anulada')
            // — así el costo tampoco se queda con el aporte de una compra que se está
            // anulando.
            $afectados = [];
            foreach ($factura['detalle'] as $item) {
                $item['cantidad'] = -1 * (float)$item['cantidad'];
                $this->actualizarStock($item, false, $id);
                $this->_marcarAfectado($afectados, $item);
            }
            foreach ($afectados as $key => $_) {
                [$tipo, $itemId] = explode(':', $key, 2);
                $this->recalcularCostoDesdeHistorial($tipo, (int)$itemId, $id);
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * $proveedorId (opcional): si se pasa, y este proveedor ya tiene un alias exacto
     * guardado para $q (ver ProveedorModel::buscarAliasExacto — aprendido de una
     * compra anterior a este mismo proveedor con este mismo texto), ese ítem va
     * primero en el resultado, marcado con via_alias=true — para no tener que
     * volver a buscarlo/crearlo cada vez que llega una factura con la misma
     * descripción de este proveedor (ver docs — buscador de Compras).
     */
    public function buscarItems(string $q, string $tipo = '', int $proveedorId = 0): array
    {
        $alias = [];
        if ($proveedorId) {
            require_once BASE_PATH . '/app/Models/ProveedorModel.php';
            $hit = (new ProveedorModel())->buscarAliasExacto($proveedorId, $q);
            if ($hit && (!$tipo || $tipo === $hit['tipo_item'])) {
                $item = $this->_itemPorTipoId($hit['tipo_item'], (int)$hit['item_id']);
                if ($item) {
                    $item['via_alias'] = true;
                    $alias[] = $item;
                }
            }
        }

        $q      = "%$q%";
        $params = [':q1' => $q, ':q2' => $q];
        $rows   = [];

        if (!$tipo || $tipo === 'materia_prima') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'materia_prima' AS tipo, stock_actual, costo_unitario,
                        cantidad_contenida,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM materias_primas WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", $params
            ));
        }
        if (!$tipo || $tipo === 'insumo') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'insumo' AS tipo, stock_actual, costo_unitario,
                        cantidad_contenida,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM insumos WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", $params
            ));
        }
        if (!$tipo || $tipo === 'producto') {
            $rows = array_merge($rows, $this->queryAll(
                "SELECT id, nombre, codigo, 'producto' AS tipo, stock_actual, precio_costo AS costo_unitario,
                        NULL AS cantidad_contenida,
                        (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
                 FROM productos WHERE eliminado=0 AND activo=1 AND (nombre LIKE :q1 OR codigo LIKE :q2)
                 LIMIT 20", [':q1' => $q, ':q2' => $q]
            ));
        }

        if ($alias) {
            $aliasKey = $alias[0]['tipo'] . ':' . $alias[0]['id'];
            $rows = array_values(array_filter($rows, fn($r) => $r['tipo'] . ':' . $r['id'] !== $aliasKey));
        }
        return array_merge($alias, $rows);
    }

    /**
     * Busca líneas YA CARGADAS de compras (facturas_compra_detalle), no el catálogo — para
     * "vincular un producto de una compra ya realizada" desde Activos Fijos
     * (ver ActivoFijoModel::crearDesdeLineaCompra()). A diferencia de buscarItems() (que
     * busca en materias_primas/insumos/productos), acá se busca por el NOMBRE DEL ÍTEM que
     * esa línea compró, trayendo también de qué factura/fecha/proveedor salió — el admin
     * necesita ver eso para elegir la línea correcta si el mismo ítem se compró varias veces.
     */
    public function buscarLineasParaActivoFijo(string $q): array
    {
        $like = "%$q%";
        return $this->queryAll(
            "SELECT d.id AS detalle_id, d.tipo_item, d.cantidad, d.subtotal_con_iva,
                    f.id AS factura_compra_id, f.fecha, f.nro_comprobante, f.proveedor_id,
                    p.razon_social AS proveedor_nombre,
                    COALESCE(mp.nombre, ins.nombre, prod.nombre) AS nombre,
                    COALESCE(mp.id, ins.id, prod.id) AS item_id
             FROM facturas_compra_detalle d
             JOIN facturas_compra f       ON f.id = d.factura_compra_id AND f.eliminado = 0
             LEFT JOIN proveedores p      ON p.id = f.proveedor_id
             LEFT JOIN materias_primas mp ON mp.id = d.materia_prima_id AND d.tipo_item = 'materia_prima'
             LEFT JOIN insumos ins        ON ins.id = d.insumo_id        AND d.tipo_item = 'insumo'
             LEFT JOIN productos prod     ON prod.id = d.producto_id     AND d.tipo_item = 'producto'
             WHERE (mp.nombre LIKE :q1 OR ins.nombre LIKE :q2 OR prod.nombre LIKE :q3)
             ORDER BY f.fecha DESC
             LIMIT 20",
            [':q1' => $like, ':q2' => $like, ':q3' => $like]
        );
    }

    /** Ficha de un ítem por tipo+id, mismas columnas que buscarItems() — usado para resolver un hit de alias. */
    private function _itemPorTipoId(string $tipo, int $id): ?array
    {
        $tabla = match ($tipo) {
            'materia_prima' => 'materias_primas',
            'insumo'        => 'insumos',
            'producto'      => 'productos',
            default         => null,
        };
        if (!$tabla || !$id) return null;

        $costoCol = $tipo === 'producto' ? 'precio_costo' : 'costo_unitario';
        $ccCol    = $tipo !== 'producto' ? 'cantidad_contenida' : 'NULL';
        return $this->queryOne(
            "SELECT id, nombre, codigo, '{$tipo}' AS tipo, stock_actual, {$costoCol} AS costo_unitario,
                    {$ccCol} AS cantidad_contenida,
                    (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad
             FROM `{$tabla}` WHERE id = ? AND eliminado = 0",
            [$id]
        ) ?: null;
    }

    /**
     * Alta rápida de un ítem (materia prima, insumo o producto) desde el buscador de la
     * línea de una compra — para no tener que salir a Materias Primas/Insumos/Productos
     * a crearlo y volver. Deja el resto de los campos (costo, stock mínimo, etc.) en su
     * valor por defecto, editables después desde la ficha del ítem si hace falta.
     */
    public function crearItemRapido(string $tipo, string $nombre, ?int $categoriaId, ?int $unidadMedidaId, ?int $ingredienteGenericoId = null): array
    {
        $tiposValidos = ['materia_prima', 'insumo', 'producto'];
        if (!in_array($tipo, $tiposValidos, true)) {
            return ['ok' => false, 'error' => 'Tipo de ítem inválido.'];
        }
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'El nombre es obligatorio.'];
        }

        $tabla = match ($tipo) {
            'materia_prima' => 'materias_primas',
            'insumo'        => 'insumos',
            default         => 'productos',
        };

        $existente = $this->queryScalar(
            "SELECT id FROM `{$tabla}` WHERE eliminado = 0 AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
            [$nombre]
        );
        if ($existente) {
            return ['ok' => false, 'error' => "Ya existe un ítem con ese nombre (ID {$existente})."];
        }

        if ($tipo === 'materia_prima') {
            $this->query(
                "INSERT INTO materias_primas (nombre, categoria_id, unidad_medida_id, ingrediente_generico_id, stock_actual, stock_minimo, costo_unitario, activo, eliminado)
                 VALUES (:nombre, :cat, :unidad, :generico, 0, 0, 0, 1, 0)",
                [':nombre' => $nombre, ':cat' => $categoriaId, ':unidad' => $unidadMedidaId, ':generico' => $ingredienteGenericoId]
            );
        } elseif ($tipo === 'insumo') {
            $this->query(
                "INSERT INTO insumos (nombre, categoria_id, unidad_medida_id, stock_actual, stock_minimo, costo_unitario, activo, eliminado)
                 VALUES (:nombre, :cat, :unidad, 0, 0, 0, 1, 0)",
                [':nombre' => $nombre, ':cat' => $categoriaId, ':unidad' => $unidadMedidaId]
            );
        } else {
            $this->query(
                "INSERT INTO productos (nombre, categoria_id, unidad_medida_id, tipo, precio_venta, precio_costo, iva, stock_actual, stock_minimo, activo, eliminado)
                 VALUES (:nombre, :cat, :unidad, 'reventa', 0, 0, 21, 0, 0, 1, 0)",
                [':nombre' => $nombre, ':cat' => $categoriaId, ':unidad' => $unidadMedidaId]
            );
        }

        return ['ok' => true, 'id' => (int)$this->db->lastInsertId(), 'nombre' => $nombre, 'tipo' => $tipo];
    }

    /**
     * Devuelve los datos de la última compra de un ítem.
     * Busca primero al proveedor indicado; si no hay historial, busca a cualquier proveedor.
     */
    public function ultimaCompra(string $tipo, int $itemId, int $proveedorId): ?array
    {
        $col = match($tipo) {
            'materia_prima' => 'materia_prima_id',
            'insumo'        => 'insumo_id',
            default         => 'producto_id',
        };

        $select = "SELECT fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario,
                          fcd.descuento_pct, fcd.iva_porcentaje, fcd.monto_no_gravado,
                          fcd.observaciones, fc.fecha, fc.proveedor_id
                   FROM facturas_compra_detalle fcd
                   JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
                   WHERE fc.eliminado = 0 AND fc.estado != 'anulada'
                     AND fcd.{$col} = :item_id";

        // 1.º Mismo proveedor
        $row = $this->queryOne(
            $select . " AND fc.proveedor_id = :prov ORDER BY fc.fecha DESC, fc.id DESC LIMIT 1",
            [':item_id' => $itemId, ':prov' => $proveedorId]
        );

        // 2.º Fallback: cualquier proveedor
        return $row ?? $this->queryOne(
            $select . " ORDER BY fc.fecha DESC, fc.id DESC LIMIT 1",
            [':item_id' => $itemId]
        );
    }

    public function existeDuplicado(int $proveedorId, string $nroComprobante, ?int $excluirId = null): bool
    {
        if (!$nroComprobante) return false;

        $sql    = 'SELECT COUNT(*) FROM facturas_compra WHERE proveedor_id = :pid AND nro_comprobante = :nro AND eliminado = 0';
        $params = [':pid' => $proveedorId, ':nro' => $nroComprobante];

        if ($excluirId) {
            $sql         .= ' AND id != :eid';
            $params[':eid'] = $excluirId;
        }

        return (int)$this->queryScalar($sql, $params) > 0;
    }

    /** Como existeDuplicado() pero devuelve los datos de la factura existente (para armar un link + quién/cuándo la cargó), en vez de solo un bool. Usado por el Chat IA. */
    public function buscarDuplicado(int $proveedorId, string $nroComprobante): array|false
    {
        if (!$nroComprobante) return false;

        return $this->queryOne(
            "SELECT fc.id, fc.fecha, fc.nro_comprobante, u.nombre AS usuario_nombre
             FROM facturas_compra fc
             LEFT JOIN usuarios u ON u.id = fc.usuario_id
             WHERE fc.proveedor_id = :pid AND fc.nro_comprobante = :nro AND fc.eliminado = 0
             ORDER BY fc.id DESC LIMIT 1",
            [':pid' => $proveedorId, ':nro' => $nroComprobante]
        );
    }

    public function totalPorPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(total_con_iva),0) FROM facturas_compra
             WHERE eliminado=0 AND estado='pagada' AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
    }
}
