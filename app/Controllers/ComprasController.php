<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/app/Models/SocioModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
require_once BASE_PATH . '/app/Models/ActivoFijoModel.php';
require_once BASE_PATH . '/app/Models/FacturaCompraAdjuntoModel.php';

class ComprasController extends Controller
{
    private FacturaCompraModel $model;
    private ProveedorModel     $provModel;
    private SocioModel         $socioModel;

    public function __construct()
    {
        $this->model      = new FacturaCompraModel();
        $this->provModel  = new ProveedorModel();
        $this->socioModel = new SocioModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $filtros = [
            'proveedor_id' => (int)$this->param('proveedor_id'),
            'desde'        => $this->param('desde') ?: '',
            'hasta'        => $this->param('hasta') ?: '',
            'estado'       => $this->param('estado'),
        ];

        $this->render('compras.index', [
            'pageTitle'   => 'Facturas de Compra',
            'breadcrumb'  => ['Compras' => null, 'Facturas' => null],
            'facturas'    => $this->model->findAllConDetalle($filtros),
            'proveedores' => $this->provModel->findAllActivos(),
            'filtros'     => $filtros,
        ]);
    }

    public function nueva(): void
    {
        $this->requireAuth();

        $this->render('compras.form', [
            'pageTitle'   => 'Nueva factura de compra',
            'breadcrumb'  => ['Compras' => null, 'Facturas' => 'compras', 'Nueva' => null],
            'proveedores' => $this->provModel->findAllActivos(),
            'factura'     => null,
            'socios'      => $this->socioModel->findAllActivos(),
            'volverUrl'   => $this->_volverUrl(),
        ] + $this->_datosCrearItem());
    }

    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $errores = [];

        // Validar cabecera
        $proveedorId  = (int)$this->input('proveedor_id');
        $fecha        = $this->input('fecha') ?: date('Y-m-d');
        $tipoComprobante = $this->input('tipo_comprobante', 'factura_b');
        $nroComprobante  = $this->input('nro_comprobante', '');
        $condPago     = $this->input('condicion_pago', 'contado');
        $notas        = $this->input('notas', '');

        if (!$proveedorId) $errores[] = 'Seleccione un proveedor.';

        // Validar duplicado (mismo proveedor + número de comprobante)
        if ($proveedorId && $nroComprobante && $this->model->existeDuplicado($proveedorId, $nroComprobante)) {
            $errores[] = "Ya existe una factura del mismo proveedor con el número de comprobante \"{$nroComprobante}\".";
        }

        // Leer items del detalle enviados como JSON
        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) === 0) {
            $errores[] = 'Agregue al menos un ítem a la factura.';
        }

        if ($errores) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }

        // Calcular totales
        $subtotal    = 0.0;
        $totalIva    = 0.0;
        $totalNoGrav = 0.0;
        $detalleDB   = [];
        $activosFijos = [];
        $montoActivosFijos = 0.0;

        foreach ($items as $item) {
            $cant     = (float)($item['cantidad'] ?? 0);
            $precio   = (float)($item['precio_unitario'] ?? 0);
            $dtoPct   = (float)($item['descuento_pct'] ?? 0);
            $ivaPct   = (float)($item['iva_porcentaje'] ?? 21);
            $noGrav   = (float)($item['monto_no_gravado'] ?? 0);

            if ($cant <= 0) { $errores[] = 'Cantidad inválida.'; continue; }

            $bruto   = $cant * $precio;
            $impDto  = round($bruto * $dtoPct / 100, 4);
            $neto    = round($bruto - $impDto, 4);
            $ivaImp  = round($neto * $ivaPct / 100, 4);
            $subIva  = round($neto + $ivaImp + $noGrav, 4);

            // Ítem marcado "Es un activo fijo": no entra al detalle de la factura (no es
            // inventario) pero SÍ suma al total de la factura — así la deuda con el proveedor
            // (Pagos a Proveedores/Cuenta Corriente) queda bien si se compró a crédito. Para que
            // no se cuente dos veces como "Compras" en el Resumen Económico, su monto se guarda
            // aparte en monto_activos_fijos y GastoModel::estadoResultados() lo resta.
            if (!empty($item['es_activo_fijo'])) {
                $nombreActivo = trim((string)($item['nombre_activo'] ?? ''));
                if ($nombreActivo === '') { $errores[] = 'Falta el nombre del activo fijo.'; continue; }
                $activosFijos[]     = ['nombre' => $nombreActivo, 'valor' => round($subIva, 2)];
                $montoActivosFijos += $subIva;
                $subtotal    += $neto;
                $totalIva    += $ivaImp;
                $totalNoGrav += $noGrav;
                continue;
            }

            $tipo     = $item['tipo_item'] ?? '';
            $cantCont = (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '')
                          ? (float)$item['cantidad_contenida'] : null;

            $subtotal    += $neto;
            $totalIva    += $ivaImp;
            $totalNoGrav += $noGrav;

            $detalleDB[] = [
                'tipo_item'         => $tipo,
                'materia_prima_id'  => $tipo === 'materia_prima' ? (int)$item['item_id'] : null,
                'insumo_id'         => $tipo === 'insumo'        ? (int)$item['item_id'] : null,
                'producto_id'       => $tipo === 'producto'      ? (int)$item['item_id'] : null,
                'cantidad'          => $cant,
                'cantidad_contenida'=> $cantCont,
                'precio_unitario'   => $precio,
                'descuento_pct'     => $dtoPct,
                'importe_dto'       => $impDto,
                'subtotal'          => $neto,
                'iva_porcentaje'    => $ivaPct,
                'subtotal_con_iva'  => $subIva,
                'monto_no_gravado'  => $noGrav,
                'observaciones'     => $item['observaciones'] ?? null,
                'descripcion_buscada' => trim((string)($item['descripcion_buscada'] ?? '')),
            ];
        }

        if ($errores) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }

        $totalConIva = round($subtotal + $totalIva + $totalNoGrav, 2);

        $cabecera = [
            'proveedor_id'    => $proveedorId,
            'usuario_id'      => Session::user()['id'],
            'fecha'           => $fecha,
            'tipo_comprobante'=> $tipoComprobante,
            'nro_comprobante' => $nroComprobante ?: null,
            'condicion_pago'  => $condPago,
            'subtotal'        => round($subtotal, 2),
            'monto_no_gravado'=> round($totalNoGrav, 2),
            'total_iva'       => round($totalIva, 2),
            'total_con_iva'   => $totalConIva,
            'monto_activos_fijos' => round($montoActivosFijos, 2),
            'estado'          => 'pagada',
            'notas'           => $notas ?: null,
            'eliminado'       => 0,
        ];

        // Aviso no bloqueante: si algún ítem trae un cantidad_contenida muy distinto al
        // que se usó históricamente para el mismo ítem, se guarda igual pero se avisa
        // (ver FacturaCompraModel::advertenciasCantidadContenida).
        $advertencias = $this->model->advertenciasCantidadContenida($detalleDB);

        try {
            $id      = $this->model->guardar($cabecera, $detalleDB);
            $archivo = $this->_subirArchivoCompra($id);
            if ($archivo) $this->model->update($id, ['comprobante_archivo' => $archivo]);
            $advertencias = array_merge($advertencias, (new ActivoFijoModel())->crearActivosSueltos($activosFijos, $proveedorId, $fecha, $id, $nroComprobante));
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Factura guardada correctamente.', 'advertencias' => $advertencias]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $factura = $this->model->findByIdConDetalle($id);
        if (!$factura || $factura['estado'] === 'anulada') abort(404);

        $pagos = $this->model->getPagosDeFactura($id);
        require_once BASE_PATH . '/app/Models/PagoProveedorAdjuntoModel.php';
        $pagoAdjuntoModel = new PagoProveedorAdjuntoModel();
        foreach ($pagos as &$pg) {
            $pg['adjuntos'] = $pagoAdjuntoModel->listar((int)$pg['id']);
        }
        unset($pg);

        $this->render('compras.form', [
            'pageTitle'   => 'Editar factura #' . $id,
            'breadcrumb'  => ['Compras' => null, 'Facturas' => 'compras', 'Editar' => null],
            'proveedores' => $this->provModel->findAllActivos(),
            'factura'     => $factura,
            'pagos'       => $pagos,
            'adjuntosFactura' => (new FacturaCompraAdjuntoModel())->listar($id),
            'socios'      => $this->socioModel->findAllActivos(),
            'volverUrl'   => $this->_volverUrl(),
        ] + $this->_datosCrearItem());
    }

    /** Categorías (de los 3 tipos) y unidades de medida para el modal de alta rápida de ítem. */
    private function _datosCrearItem(): array
    {
        $catModel = new CategoriaModel();
        return [
            'categoriasCrearItem' => array_merge(
                $catModel->findAllConPadre('materia_prima'),
                $catModel->findAllConPadre('insumo'),
                $catModel->findAllConPadre('producto')
            ),
            'unidadesCrearItem' => Database::getInstance()->query('SELECT id, nombre, abreviatura FROM unidades_medida ORDER BY nombre')->fetchAll(\PDO::FETCH_ASSOC),
            'genericosCrearItem' => (new IngredienteGenericoModel())->listar(),
        ];
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $errores         = [];
        $proveedorId     = (int)$this->input('proveedor_id');
        $fecha           = $this->input('fecha') ?: date('Y-m-d');
        $tipoComprobante = $this->input('tipo_comprobante', 'factura_b');
        $nroComprobante  = $this->input('nro_comprobante', '');
        $condPago        = $this->input('condicion_pago', 'contado');
        $estado          = $this->input('estado', 'pendiente');
        $notas           = $this->input('notas', '');

        if (!$proveedorId) $errores[] = 'Seleccione un proveedor.';

        if ($proveedorId && $nroComprobante && $this->model->existeDuplicado($proveedorId, $nroComprobante, $id)) {
            $errores[] = "Ya existe otra factura del mismo proveedor con el número \"{$nroComprobante}\".";
        }

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) === 0) {
            $errores[] = 'Agregue al menos un ítem a la factura.';
        }

        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $subtotal    = 0.0;
        $totalIva    = 0.0;
        $totalNoGrav = 0.0;
        $detalleDB   = [];
        $activosFijos = [];
        $montoActivosFijos = 0.0;

        foreach ($items as $item) {
            $cant     = (float)($item['cantidad'] ?? 0);
            $precio   = (float)($item['precio_unitario'] ?? 0);
            $dtoPct   = (float)($item['descuento_pct'] ?? 0);
            $ivaPct   = (float)($item['iva_porcentaje'] ?? 21);
            $noGrav   = (float)($item['monto_no_gravado'] ?? 0);

            if ($cant <= 0) { $errores[] = 'Cantidad inválida.'; continue; }

            $bruto  = $cant * $precio;
            $impDto = round($bruto * $dtoPct / 100, 4);
            $neto   = round($bruto - $impDto, 4);
            $ivaImp = round($neto * $ivaPct / 100, 4);
            $subIva = round($neto + $ivaImp + $noGrav, 4);

            // Ver mismo comentario en guardar(): un ítem marcado como activo fijo no entra al
            // detalle de la factura, pero sí suma al total (queda aparte en monto_activos_fijos).
            if (!empty($item['es_activo_fijo'])) {
                $nombreActivo = trim((string)($item['nombre_activo'] ?? ''));
                if ($nombreActivo === '') { $errores[] = 'Falta el nombre del activo fijo.'; continue; }
                $activosFijos[]     = ['nombre' => $nombreActivo, 'valor' => round($subIva, 2)];
                $montoActivosFijos += $subIva;
                $subtotal    += $neto;
                $totalIva    += $ivaImp;
                $totalNoGrav += $noGrav;
                continue;
            }

            $tipo     = $item['tipo_item'] ?? '';
            $cantCont = (isset($item['cantidad_contenida']) && $item['cantidad_contenida'] !== null && $item['cantidad_contenida'] !== '')
                          ? (float)$item['cantidad_contenida'] : null;

            $subtotal    += $neto;
            $totalIva    += $ivaImp;
            $totalNoGrav += $noGrav;

            $detalleDB[] = [
                'tipo_item'          => $tipo,
                'materia_prima_id'   => $tipo === 'materia_prima' ? (int)$item['item_id'] : null,
                'insumo_id'          => $tipo === 'insumo'        ? (int)$item['item_id'] : null,
                'producto_id'        => $tipo === 'producto'      ? (int)$item['item_id'] : null,
                'cantidad'           => $cant,
                'cantidad_contenida' => $cantCont,
                'precio_unitario'    => $precio,
                'descuento_pct'      => $dtoPct,
                'importe_dto'        => $impDto,
                'subtotal'           => $neto,
                'iva_porcentaje'     => $ivaPct,
                'subtotal_con_iva'   => $subIva,
                'monto_no_gravado'   => $noGrav,
                'observaciones'      => $item['observaciones'] ?? null,
                'descripcion_buscada' => trim((string)($item['descripcion_buscada'] ?? '')),
            ];
        }

        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $estadosValidos = ['pendiente', 'pagada'];
        if (!in_array($estado, $estadosValidos, true)) $estado = 'pendiente';

        $cabecera = [
            'proveedor_id'     => $proveedorId,
            'fecha'            => $fecha,
            'tipo_comprobante' => $tipoComprobante,
            'nro_comprobante'  => $nroComprobante ?: null,
            'condicion_pago'   => $condPago,
            'subtotal'         => round($subtotal, 2),
            'monto_no_gravado' => round($totalNoGrav, 2),
            'total_iva'        => round($totalIva, 2),
            'total_con_iva'    => round($subtotal + $totalIva + $totalNoGrav, 2),
            'monto_activos_fijos' => round($montoActivosFijos, 2),
            'estado'           => $estado,
            'notas'            => $notas ?: null,
        ];

        $advertencias = $this->model->advertenciasCantidadContenida($detalleDB, $id);

        try {
            $this->model->actualizarFactura($id, $cabecera, $detalleDB);
            $archivo = $this->_subirArchivoCompra($id);
            if ($archivo) {
                $factura = $this->model->findByIdConDetalle($id);
                if ($factura['comprobante_archivo']) {
                    $old = BASE_PATH . '/storage/compras/' . basename($factura['comprobante_archivo']);
                    if (file_exists($old)) @unlink($old);
                }
                $this->model->update($id, ['comprobante_archivo' => $archivo]);
            }
            $advertencias = array_merge($advertencias, (new ActivoFijoModel())->crearActivosSueltos($activosFijos, $proveedorId, $fecha, $id, $nroComprobante));
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Factura actualizada correctamente.', 'advertencias' => $advertencias]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function ver(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id');
        $factura = $this->model->findByIdConDetalle($id);
        if (!$factura) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('compras.ver', [
            'pageTitle'  => 'Factura #' . $id,
            'breadcrumb' => ['Compras' => null, 'Facturas' => 'compras', 'Ver' => null],
            'factura'    => $factura,
            'pagos'      => $this->model->getPagosDeFactura($id),
            'volverUrl'  => $this->_volverUrl(),
        ]);
    }

    public function anular(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        try {
            $ok = $this->model->anular($id, Session::user()['id']);
            $ok
                ? $this->json(['ok' => true, 'mensaje' => 'Factura anulada.'])
                : $this->json(['ok' => false, 'error' => 'No se pudo anular (ya anulada o no existe).'], 409);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Vista standalone para imprimir / exportar PDF */
    public function imprimir(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $factura = $this->model->findByIdConDetalle($id);
        if (!$factura) { http_response_code(404); echo '404 Not Found'; return; }

        $pageTitle = 'Factura #' . $id;
        require BASE_PATH . '/app/Views/compras/imprimir.php';
        exit;
    }

    public function importar(): void
    {
        $this->requireAuth();
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 422);
        }
        try {
            $rows = ImportHelper::parseUploadedFile($_FILES['archivo']);
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if (empty($rows)) {
            $this->json(['ok' => false, 'error' => 'El archivo está vacío o sin datos.'], 422);
        }

        $db = Database::getInstance();

        // Cache proveedores por razon_social
        $provRows = $db->query("SELECT id, razon_social FROM proveedores WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $provIdx  = [];
        foreach ($provRows as $p) { $provIdx[self::_norm($p['razon_social'])] = (int)$p['id']; }

        // Cache ítems
        $buildIdx = function(string $tabla, string $tipo) use ($db): array {
            $rs = $db->query("SELECT id, nombre, codigo FROM `{$tabla}` WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
            $idx = [];
            foreach ($rs as $r) {
                $idx[self::_norm($r['nombre'])] = ['id' => $r['id'], 'tipo' => $tipo];
                if ($r['codigo']) { $idx[self::_norm($r['codigo'])] = ['id' => $r['id'], 'tipo' => $tipo]; }
            }
            return $idx;
        };
        $itemIdx = array_merge(
            $buildIdx('materias_primas', 'materia_prima'),
            $buildIdx('insumos',         'insumo'),
            $buildIdx('productos',       'producto')
        );

        // Agrupar por proveedor + nro_comprobante (clave compuesta: distintos proveedores
        // pueden tener el mismo número de comprobante)
        $grupos = [];
        foreach ($rows as $i => $row) {
            $nro  = trim($row['nro_comprobante'] ?? '');
            $prov = trim($row['proveedor_razon_social'] ?? '');
            if (!$nro) continue;
            $grupos[$prov . '|||' . $nro][] = ['fila' => $i + 2, 'row' => $row];
        }

        $usuarioId         = (int)Session::user()['id'];
        $importados        = 0;
        $errores           = [];
        $todasAdvertencias = [];

        foreach ($grupos as $_ => $filas) {
            $firstRow       = $filas[0]['row'];
            $nroComprobante = trim($firstRow['nro_comprobante'] ?? '');
            $provNombre  = self::_norm($firstRow['proveedor_razon_social'] ?? '');
            $proveedorId = $provIdx[$provNombre] ?? null;
            if (!$proveedorId) { $errores[] = "Factura '{$nroComprobante}': proveedor '" . trim($firstRow['proveedor_razon_social'] ?? '') . "' no encontrado."; continue; }

            if ($this->model->existeDuplicado($proveedorId, $nroComprobante)) {
                $errores[] = "Factura '{$nroComprobante}': ya existe para ese proveedor (duplicado)."; continue;
            }

            $fecha           = ImportHelper::normalizeDate(trim($firstRow['fecha'] ?? date('Y-m-d')));
            $tipoComprobante = trim($firstRow['tipo_comprobante'] ?? 'factura_b');
            $condPago        = trim($firstRow['condicion_pago'] ?? 'contado');
            $notas           = ImportHelper::nullIfEmpty(trim($firstRow['notas'] ?? ''));

            $subtotal     = 0.0;
            $totalIva     = 0.0;
            $totalNoGrav  = 0.0;
            $detalleDB    = [];
            $hayError     = false;
            $advertencias = [];

            foreach ($filas as $entry) {
                $fila       = $entry['fila'];
                $row        = $entry['row'];
                $tipoItem   = self::_normTipo($row['tipo_item'] ?? '');
                $itemNombre = self::_norm($row['item_nombre'] ?? '');
                $cantidad   = ImportHelper::toFloat($row['cantidad'] ?? '0');
                $precio     = ImportHelper::toFloat($row['precio_unitario'] ?? '0');
                $ivaPct     = ImportHelper::toFloat($row['iva_porcentaje'] ?? '21');
                $obsItem    = ImportHelper::nullIfEmpty(trim($row['observaciones'] ?? ''));
                $ccRaw      = trim($row['cantidad_contenida'] ?? '');
                $cantCont   = $ccRaw !== '' ? ImportHelper::toFloat($ccRaw) : null;

                // Leer campos financieros detectando si fueron provistos explícitamente
                $dtoRaw     = trim($row['descuento_pct']    ?? '');
                $noGravRaw  = trim($row['monto_no_gravado'] ?? '');
                $importeRaw = trim($row['importe']          ?? '');
                $ivaImpRaw  = trim($row['iva_importe']      ?? '');
                $totalRaw   = trim($row['total']            ?? '');

                $dtoPct = $dtoRaw    !== '' ? ImportHelper::toFloat($dtoRaw)    : 0.0;
                $noGrav = $noGravRaw !== '' ? ImportHelper::toFloat($noGravRaw) : 0.0;

                if (!$itemNombre) { $errores[] = "Fila {$fila}: item_nombre obligatorio."; $hayError = true; continue; }
                if ($cantidad <= 0) { $errores[] = "Fila {$fila}: cantidad debe ser > 0."; $hayError = true; continue; }
                if ($precio < 0)   { $errores[] = "Fila {$fila}: precio_unitario inválido."; $hayError = true; continue; }

                $item = self::_findItem($db, $itemIdx, $tipoItem, $itemNombre);
                if (!$item) {
                    $errores[] = "Fila {$fila}: ítem '" . trim($row['item_nombre'] ?? $itemNombre) . "' no encontrado.";
                    $hayError  = true; continue;
                }

                // Detectar campos financieros no provistos → avisar pero continuar
                $faltanCalc = [];
                if ($dtoRaw     === '') $faltanCalc[] = 'descuento_pct';
                if ($importeRaw === '') $faltanCalc[] = 'importe';
                if ($ivaImpRaw  === '') $faltanCalc[] = 'iva_importe';
                if ($noGravRaw  === '') $faltanCalc[] = 'monto_no_gravado';
                if ($totalRaw   === '') $faltanCalc[] = 'total';
                if (!empty($faltanCalc)) {
                    $advertencias[] = "Fila {$fila}: calculados → " . implode(', ', $faltanCalc) . ".";
                }

                // Usar valores provistos; calcular los que faltan
                $bruto = $cantidad * $precio;
                if ($importeRaw !== '') {
                    // Importe explícito: confiamos en él como neto.
                    // Si descuento_pct no fue provisto tampoco, dto = 0
                    // (no inferir descuento de bruto − neto porque precio×cantidad
                    // puede no coincidir con el importe por distintos motivos).
                    $neto   = ImportHelper::toFloat($importeRaw);
                    $impDto = round($bruto * $dtoPct / 100, 4);
                } else {
                    // Importe no provisto: calcular desde bruto y dto
                    $impDto = round($bruto * $dtoPct / 100, 4);
                    $neto   = round($bruto - $impDto, 4);
                    // Recalcular dto efectivo para guardar el valor real almacenado
                    if ($bruto > 0) {
                        $impDto = round($bruto - $neto, 4);
                        $dtoPct = round($impDto / $bruto * 100, 4);
                    }
                }
                $ivaImp = $ivaImpRaw !== '' ? ImportHelper::toFloat($ivaImpRaw) : round($neto * $ivaPct / 100, 4);
                $subIva = $totalRaw  !== '' ? ImportHelper::toFloat($totalRaw)  : round($neto + $ivaImp + $noGrav, 4);

                $subtotal    += $neto;
                $totalIva    += $ivaImp;
                $totalNoGrav += $noGrav;

                $detalleDB[] = [
                    'tipo_item'          => $item['tipo'],
                    'materia_prima_id'   => $item['tipo'] === 'materia_prima' ? $item['id'] : null,
                    'insumo_id'          => $item['tipo'] === 'insumo'        ? $item['id'] : null,
                    'producto_id'        => $item['tipo'] === 'producto'      ? $item['id'] : null,
                    'cantidad'           => $cantidad,
                    'cantidad_contenida' => $cantCont,
                    'precio_unitario'    => $precio,
                    'descuento_pct'      => $dtoPct,
                    'importe_dto'        => $impDto,
                    'subtotal'           => $neto,
                    'iva_porcentaje'     => $ivaPct,
                    'subtotal_con_iva'   => $subIva,
                    'monto_no_gravado'   => $noGrav,
                    'observaciones'      => $obsItem,
                ];
            }

            if ($hayError || empty($detalleDB)) continue;

            $cabecera = [
                'proveedor_id'    => $proveedorId,
                'usuario_id'      => $usuarioId,
                'fecha'           => $fecha,
                'tipo_comprobante'=> $tipoComprobante,
                'nro_comprobante' => $nroComprobante,
                'condicion_pago'  => $condPago,
                'subtotal'        => round($subtotal, 2),
                'monto_no_gravado'=> round($totalNoGrav, 2),
                'total_iva'       => round($totalIva, 2),
                'total_con_iva'   => round($subtotal + $totalIva + $totalNoGrav, 2),
                'estado'          => 'pagada',
                'notas'           => $notas,
                'eliminado'       => 0,
            ];

            try {
                $this->model->guardar($cabecera, $detalleDB);
                $importados++;
                foreach ($advertencias as $adv) {
                    $todasAdvertencias[] = "[{$nroComprobante}] {$adv}";
                }
            } catch (\Throwable $e) {
                $errores[] = "Factura '{$nroComprobante}': " . $e->getMessage();
            }
        }

        $this->json(['ok' => true, 'importados' => $importados, 'total' => count($grupos), 'errores' => $errores, 'advertencias' => $todasAdvertencias]);
    }

    /** AJAX: previsualiza el archivo sin guardar — devuelve {ok, filas, resumen} */
    public function previsualizar(): void
    {
        $this->requireAuth();
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 422);
        }
        try {
            $rows = ImportHelper::parseUploadedFile($_FILES['archivo']);
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if (empty($rows)) {
            $this->json(['ok' => false, 'error' => 'El archivo está vacío o sin datos.'], 422);
        }

        $db = Database::getInstance();

        // Cache proveedores (key = nombre normalizado)
        $provRows = $db->query("SELECT id, razon_social FROM proveedores WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $provIdx  = [];
        foreach ($provRows as $p) {
            $provIdx[self::_norm($p['razon_social'])] = (int)$p['id'];
        }

        // Cache ítems de todos los tipos
        $buildIdx = function(string $tabla, string $tipo) use ($db): array {
            $rs  = $db->query("SELECT id, nombre, codigo FROM `{$tabla}` WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
            $idx = [];
            foreach ($rs as $r) {
                $idx[self::_norm($r['nombre'])] = ['id' => $r['id'], 'tipo' => $tipo];
                if ($r['codigo']) $idx[self::_norm($r['codigo'])] = ['id' => $r['id'], 'tipo' => $tipo];
            }
            return $idx;
        };
        $itemIdx = array_merge(
            $buildIdx('materias_primas', 'materia_prima'),
            $buildIdx('insumos',         'insumo'),
            $buildIdx('productos',       'producto')
        );

        // Agrupar por proveedor + nro_comprobante (clave compuesta)
        $grupos = [];
        foreach ($rows as $i => $row) {
            $nro  = trim($row['nro_comprobante'] ?? '');
            $prov = trim($row['proveedor_razon_social'] ?? '');
            if ($nro === '') continue;
            $grupos[$prov . '|||' . $nro][] = ['fila' => $i + 2, 'row' => $row];
        }

        $filas   = [];
        $resumen = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($grupos as $_ => $grupoFilas) {
            $firstRow       = $grupoFilas[0]['row'];
            $firstFila      = $grupoFilas[0]['fila'];
            $nroComprobante = trim($firstRow['nro_comprobante'] ?? '');
            $provNombre = self::_norm($firstRow['proveedor_razon_social'] ?? '');
            $nItems     = count($grupoFilas);
            $tituloBase = "#{$nroComprobante} — " . trim($firstRow['proveedor_razon_social'] ?? '?')
                        . " ({$nItems} ítem" . ($nItems > 1 ? 's' : '') . ") · " . ($firstRow['fecha'] ?? '');

            // 1) Proveedor
            if (!isset($provIdx[$provNombre])) {
                $filas[] = ['fila' => $firstFila, 'titulo' => $tituloBase, 'estado' => 'error',
                    'mensaje' => "Proveedor '" . trim($firstRow['proveedor_razon_social'] ?? '') . "' no encontrado. Crealo primero en Proveedores."];
                $resumen['error']++;
                continue;
            }
            $proveedorId = $provIdx[$provNombre];

            // 2) Duplicado
            if ($this->model->existeDuplicado($proveedorId, $nroComprobante)) {
                $filas[] = ['fila' => $firstFila, 'titulo' => $tituloBase, 'estado' => 'warning',
                    'mensaje' => "Ya existe en el sistema (duplicado — será omitida)."];
                $resumen['warning']++;
                continue;
            }

            // 3) Validar ítems y campos financieros
            $erroresFact    = [];
            $itemsValidos   = 0;
            $faltanCalcFact = [];

            foreach ($grupoFilas as $entry) {
                $fila       = $entry['fila'];
                $row        = $entry['row'];
                $tipoItem   = self::_normTipo($row['tipo_item'] ?? '');
                $itemNombre = self::_norm($row['item_nombre'] ?? '');
                $cantidad   = ImportHelper::toFloat($row['cantidad'] ?? '0');

                if ($itemNombre === '') { $erroresFact[] = "Fila {$fila}: item_nombre vacío."; continue; }
                if ($cantidad <= 0)    { $erroresFact[] = "Fila {$fila}: cantidad inválida.";  continue; }

                $item = self::_findItem($db, $itemIdx, $tipoItem, $itemNombre);

                if (!$item) {
                    $nombreOriginal = trim($row['item_nombre'] ?? $itemNombre);
                    $erroresFact[]  = "Fila {$fila}: ítem '{$nombreOriginal}' no encontrado.";
                } else {
                    $itemsValidos++;
                }

                // Detectar campos financieros no provistos (serán calculados al importar)
                foreach (['descuento_pct', 'importe', 'iva_importe', 'monto_no_gravado', 'total'] as $fc) {
                    if (trim($row[$fc] ?? '') === '') {
                        $faltanCalcFact[] = $fc;
                    }
                }
            }
            $faltanCalcFact = array_unique($faltanCalcFact);

            if (!empty($erroresFact)) {
                $estado  = $itemsValidos === 0 ? 'error' : 'warning';
                $mensaje = implode(' | ', array_slice($erroresFact, 0, 5));
                if (count($erroresFact) > 5) $mensaje .= ' … y ' . (count($erroresFact) - 5) . ' más.';
                $filas[] = ['fila' => $firstFila, 'titulo' => $tituloBase, 'estado' => $estado, 'mensaje' => $mensaje];
                $resumen[$estado]++;
            } elseif (!empty($faltanCalcFact)) {
                $filas[] = ['fila' => $firstFila, 'titulo' => $tituloBase, 'estado' => 'warning',
                    'mensaje' => "{$itemsValidos} ítem(s) listos · calculados: " . implode(', ', $faltanCalcFact) . "."];
                $resumen['warning']++;
            } else {
                $filas[] = ['fila' => $firstFila, 'titulo' => $tituloBase, 'estado' => 'ok',
                    'mensaje' => "{$itemsValidos} ítem(s) listos."];
                $resumen['ok']++;
            }
        }

        $this->json(['ok' => true, 'filas' => $filas, 'resumen' => $resumen]);
    }

    /**
     * URL de retorno hacia el listado, preservando los filtros activos.
     * Viene del parámetro 'volver' (armado en compras/index.php con la query
     * string vigente); se valida que apunte a este mismo sitio para evitar
     * open-redirect, si no cae al listado sin filtros.
     */
    private function _volverUrl(): string
    {
        $v = $this->param('volver', '');
        if ($v && str_starts_with($v, BASE_URL)) {
            return $v;
        }
        return url('compras');
    }

    /** Normaliza un string para comparación: minúsculas + encoding + espacios internos */
    private static function _norm(string $s): string
    {
        $s = trim($s);
        if (mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) !== 'UTF-8') {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        } elseif (preg_match('/\xC3[\x80-\xBF]/', $s)) {
            $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        $s = mb_strtolower($s, 'UTF-8');
        return preg_replace('/\s+/', ' ', $s); // colapsar espacios internos dobles
    }

    /**
     * Normaliza el valor de tipo_item del CSV al formato interno.
     * Acepta: 'materia_prima', 'Materia Prima', 'materia prima', 'mp', 'insumo', 'ins', 'producto', 'prod'…
     */
    private static function _normTipo(string $s): string
    {
        $s = strtolower(trim(preg_replace('/\s+/', '_', $s)));
        return match(true) {
            in_array($s, ['materia_prima', 'materia-prima', 'mp', 'materiaprima'], true) => 'materia_prima',
            in_array($s, ['insumo', 'ins', 'insumos'],                              true) => 'insumo',
            in_array($s, ['producto', 'prod', 'productos'],                          true) => 'producto',
            default => $s,
        };
    }

    /**
     * Busca un ítem con cascada de fallbacks:
     *  1. Tabla específica por tipo (si tipo válido)
     *  2. Índice completo en memoria (todos los tipos)
     *  3. LIKE parcial en todas las tablas (último recurso)
     */
    private static function _findItem(\PDO $db, array $itemIdx, string $tipoItem, string $itemNombre): ?array
    {
        $tablas = [
            'materia_prima' => 'materias_primas',
            'insumo'        => 'insumos',
            'producto'      => 'productos',
        ];

        // 1. Búsqueda exacta en la tabla del tipo indicado
        if ($tipoItem && isset($tablas[$tipoItem])) {
            $st = $db->prepare(
                "SELECT id FROM `{$tablas[$tipoItem]}` WHERE eliminado=0 AND (LOWER(nombre)=? OR LOWER(codigo)=?)"
            );
            $st->execute([$itemNombre, $itemNombre]);
            if ($r = $st->fetch(\PDO::FETCH_ASSOC)) return ['id' => (int)$r['id'], 'tipo' => $tipoItem];
        }

        // 2. Índice en memoria (todos los tipos), por si el tipo estaba mal o vacío
        if (isset($itemIdx[$itemNombre])) return $itemIdx[$itemNombre];

        // 3. LIKE en todas las tablas (tolerante a pequeñas diferencias)
        $like = '%' . $itemNombre . '%';
        foreach ($tablas as $tipo => $tabla) {
            $st = $db->prepare("SELECT id FROM `{$tabla}` WHERE eliminado=0 AND LOWER(nombre) LIKE ?");
            $st->execute([$like]);
            if ($r = $st->fetch(\PDO::FETCH_ASSOC)) return ['id' => (int)$r['id'], 'tipo' => $tipo];
        }

        return null;
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';

        // Columnas: campos de cabecera (se repiten por cada ítem del comprobante)
        //           + campos de detalle del ítem
        // Campos con * son obligatorios. Los demás se pueden dejar vacíos.
        $columnas = [
            'proveedor_razon_social',  // * Razón social exacta como figura en Proveedores
            'nro_comprobante',         // * Ej: FA-0001-00000123  (agrupa las filas del mismo comprobante)
            'fecha',                   // * AAAA-MM-DD
            'tipo_comprobante',        // factura_a | factura_b | factura_c | ticket | remito | otro
            'condicion_pago',          // contado | cuenta_corriente | cheque | transferencia  (default: contado)
            'notas',                   // Observaciones generales del comprobante (opcional)
            'tipo_item',               // * materia_prima | insumo | producto
            'item_nombre',             // * Nombre exacto del ítem como aparece en el sistema
            'cantidad',                // * Cantidad comprada
            'cantidad_contenida',      // Unidades por envase/bulto (mat. prima, insumo o producto; opcional)
            'precio_unitario',         // * Precio unitario SIN IVA
            'descuento_pct',           // % descuento 0-100 · si se omite se asume 0
            'importe',                 // Subtotal neto (cant × precio − dto) · si se omite se calcula
            'iva_porcentaje',          // 0 | 10.5 | 21  (default 21) · se usa para almacenar el %
            'iva_importe',             // Monto de IVA en pesos · si se omite se calcula
            'monto_no_gravado',        // Monto no gravado del ítem · si se omite se asume 0
            'total',                   // Total de la línea (importe + iva_importe + no_gravado) · si se omite se calcula
            'observaciones',           // Observaciones del ítem (opcional)
        ];

        $ejemplos = [
            // Factura A — 3 ítems con valores financieros completos
            //  col: prov               nro_comp               fecha        tipo       cond_pago           notas   tipo_item        item_nombre           cant  cc   precio  dto    importe   iva%   iva_imp      no_grav  total        obs
            ['Molinos Río de la Plata', 'FA-0001-00000456', '2026-02-03', 'factura_a', 'cuenta_corriente', '',     'materia_prima', 'Harina 000',          '100', '25', '2850',  '5',   '270750',   '10.5', '28428.75',  '0',  '299178.75', ''],
            ['Molinos Río de la Plata', 'FA-0001-00000456', '2026-02-03', 'factura_a', 'cuenta_corriente', '',     'materia_prima', 'Azúcar blanca',        '50',  '',  '3200',  '0',   '160000',   '10.5', '16800',     '0',  '176800',    ''],
            ['Molinos Río de la Plata', 'FA-0001-00000456', '2026-02-03', 'factura_a', 'cuenta_corriente', '',     'insumo',        'Papel film 30cm',      '10',  '',   '980',  '0',    '9800',    '21',    '2058',     '0',   '11858',    ''],

            // Factura B — sin importe/iva_importe/total (se calcularán automáticamente → aviso)
            ['Distribuidora Omega SRL', 'FB-0002-00001234', '2026-02-10', 'factura_b', 'contado', 'Pago en efectivo', 'insumo', 'Bolsas kraft x50',  '5',  '',  '1450', '0',  '',  '21', '',  '0',  '', ''],
            ['Distribuidora Omega SRL', 'FB-0002-00001234', '2026-02-10', 'factura_b', 'contado', '',                 'insumo', 'Cajas cartón x25', '20',  '',   '890', '0',  '',  '21', '',  '0',  '', ''],

            // Factura C — con descuento y valores provistos
            ['Importadora Sur SA',      'FC-0003-00000789', '2026-03-05', 'factura_c', 'transferencia', '', 'producto', 'Aceite de oliva x500ml', '48', '', '4200', '10', '181440', '21', '38102.40', '0', '219542.40', 'Lote 2026-A'],
        ];

        ImportHelper::sendCsvTemplate('plantilla_facturas_compra.csv', $columnas, $ejemplos);
    }

    public function exportar(): void
    {
        $this->requireAuth();

        $filtros = [
            'proveedor_id' => (int)$this->param('proveedor_id'),
            'desde'        => $this->param('desde') ?: '',
            'hasta'        => $this->param('hasta') ?: '',
            'estado'       => $this->param('estado') ?: '',
        ];

        $rows = $this->model->findParaExportar($filtros);

        // Nombre de archivo descriptivo según filtros activos
        $partes = ['facturas_compra'];
        if ($filtros['desde'] || $filtros['hasta']) {
            $partes[] = ($filtros['desde'] ?: 'inicio') . '_' . ($filtros['hasta'] ?: 'hoy');
        }
        $partes[] = date('Ymd_His');
        $filename = implode('_', $partes) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Formatea un número para Excel con configuración regional argentina:
        // - enteros sin punto decimal ("6400", no "6400.0000")
        // - decimales con coma ("6400,50") para que Excel AR no confunda el punto
        //   con separador de miles y produzca lecturas como 6.400.000
        $n = static function(mixed $v): string {
            if ($v === null || $v === '') return '';
            $f = (float)$v;
            if ($f == floor($f)) return (string)(int)$f;
            return rtrim(rtrim(str_replace('.', ',', sprintf('%.4f', $f)), '0'), ',');
        };

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM para que Excel abra correctamente en UTF-8

        fputcsv($out, [
            'ID Factura', 'Fecha', 'Proveedor', 'CUIT',
            'Nro. Comprobante', 'Tipo Comprobante', 'Condición de Pago', 'Estado', 'Notas',
            'Tipo Ítem', 'Código Ítem', 'Nombre Ítem', 'Unidad',
            'Cantidad', 'Cant. Contenida', 'Precio Unitario',
            '% Descuento', 'Importe Descuento',
            'Subtotal Neto Línea', '% IVA', 'Total Línea (c/IVA)', 'No Gravado Línea',
            'Observaciones Ítem',
            'Subtotal Factura', 'IVA Factura', 'No Gravado Factura', 'Total Factura',
        ], ';');

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id_factura'],
                $r['fecha'],
                $r['proveedor'],
                $r['proveedor_cuit'],
                $r['nro_comprobante'],
                $r['tipo_comprobante'],
                $r['condicion_pago'],
                $r['estado'],
                $r['notas'],
                $r['tipo_item'],
                $r['item_codigo'],
                $r['item_nombre'],
                $r['unidad'],
                $n($r['cantidad']),
                $n($r['cantidad_contenida']),
                $n($r['precio_unitario']),
                $n($r['descuento_pct']),
                $n($r['importe_dto']),
                $n($r['subtotal_linea']),
                $n($r['iva_porcentaje']),
                $n($r['total_linea']),
                $n($r['no_gravado_linea']),
                $r['obs_item'],
                $n($r['subtotal_factura']),
                $n($r['iva_factura']),
                $n($r['no_gravado_factura']),
                $n($r['total_factura']),
            ], ';');
        }

        fclose($out);
        exit;
    }

    /** AJAX: búsqueda de items (materias primas / insumos / productos) */
    public function buscarItems(): void
    {
        $this->requireAuth();
        $q           = $this->param('q', '');
        $tipo        = $this->param('tipo', '');
        $proveedorId = (int)$this->param('proveedor_id', 0);
        if (strlen($q) < 2) { $this->json([]); }
        $this->json($this->model->buscarItems($q, $tipo, $proveedorId));
    }

    /** AJAX: alta rápida de un ítem desde el buscador de una línea de compra */
    public function crearItem(): void
    {
        $this->requireAuth();
        Csrf::check();

        $tipo        = $this->input('tipo', '');
        $nombre      = $this->input('nombre', '');
        $categoriaId = (int)$this->input('categoria_id', 0) ?: null;
        $unidadId    = (int)$this->input('unidad_medida_id', 0) ?: null;
        $genericoId  = (int)$this->input('ingrediente_generico_id', 0) ?: null;

        $resultado = $this->model->crearItemRapido($tipo, $nombre, $categoriaId, $unidadId, $genericoId);
        if (!$resultado['ok']) $this->json($resultado, 422);
        $this->json($resultado);
    }

    /** AJAX: última compra de un ítem (para auto-rellenar la fila) */
    public function ultimaCompra(): void
    {
        $this->requireAuth();
        $tipo        = $this->param('tipo', '');
        $itemId      = (int)$this->param('item_id', 0);
        $proveedorId = (int)$this->param('proveedor_id', 0);

        if (!$tipo || !$itemId) {
            $this->json(['ok' => true, 'data' => null]);
            return;
        }

        $data = $this->model->ultimaCompra($tipo, $itemId, $proveedorId);
        $this->json(['ok' => true, 'data' => $data]);
    }

    /** AJAX POST: subir/reemplazar comprobante de una factura existente */
    public function subirComprobante(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id      = (int)$this->input('id');
        $factura = $id ? $this->model->findByIdConDetalle($id) : null;
        if (!$factura) $this->json(['ok' => false, 'error' => 'Factura no encontrada.'], 404);

        $archivo = $this->_subirArchivoCompra($id);
        if (!$archivo) {
            $this->json(['ok' => false, 'error' => 'Archivo no válido o no recibido (PDF/imagen, máx 10 MB).'], 422);
        }

        if ($factura['comprobante_archivo']) {
            $old = BASE_PATH . '/storage/compras/' . basename($factura['comprobante_archivo']);
            if (file_exists($old)) @unlink($old);
        }

        $this->model->update($id, ['comprobante_archivo' => $archivo]);
        $this->json(['ok' => true, 'archivo' => $archivo]);
    }

    /** GET: sirve el archivo de comprobante con auth */
    public function verComprobante(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id');
        $factura = $id ? $this->model->findByIdConDetalle($id) : null;

        if (!$factura || !$factura['comprobante_archivo']) {
            http_response_code(404); exit;
        }

        $path = BASE_PATH . '/storage/compras/' . basename($factura['comprobante_archivo']);
        if (!file_exists($path)) { http_response_code(404); exit; }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="comprobante_compra_' . $id . '.' . $ext . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * AJAX: agrega uno o más comprobantes adicionales a una factura ya creada (ej. una
     * factura de varias hojas) — a diferencia de subirComprobante(), no reemplaza nada, se
     * suman a la lista (ver FacturaCompraAdjuntoModel).
     */
    public function subirComprobantesAdjuntos(): void
    {
        $this->requireAuth();
        Csrf::check();

        $facturaId = (int)$this->input('factura_id', 0);
        if (!$facturaId || !$this->model->findById($facturaId)) {
            $this->json(['ok' => false, 'error' => 'Factura no encontrada.'], 404);
        }

        $archivos = $_FILES['archivos'] ?? null;
        if (!$archivos || empty($archivos['name'][0])) {
            $this->json(['ok' => false, 'error' => 'Seleccioná al menos un archivo.'], 422);
        }

        $adjuntoModel = new FacturaCompraAdjuntoModel();
        $usuarioId    = (int) Session::user()['id'];
        $subidos      = 0;

        foreach ($archivos['name'] as $i => $nombre) {
            if (($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

            $tmp  = $archivos['tmp_name'][$i];
            $mime = mime_content_type($tmp);
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!in_array($mime, $allowed, true) || $archivos['size'][$i] > 10 * 1024 * 1024) continue;

            $ext = match ($mime) {
                'application/pdf' => 'pdf',
                'image/png'       => 'png',
                'image/webp'      => 'webp',
                default           => 'jpg',
            };
            $dir = BASE_PATH . '/storage/compras/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nombreArchivo = 'compra_' . $facturaId . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($tmp, $dir . $nombreArchivo)) {
                $adjuntoModel->crear($facturaId, $nombreArchivo, $usuarioId);
                $subidos++;
            }
        }

        if (!$subidos) {
            $this->json(['ok' => false, 'error' => 'No se pudo subir ningún archivo (verificá formato y tamaño, máx. 10 MB cada uno).'], 422);
        }

        $this->json([
            'ok'       => true,
            'mensaje'  => $subidos === 1 ? 'Comprobante agregado.' : "{$subidos} comprobantes agregados.",
            'adjuntos' => $adjuntoModel->listar($facturaId),
        ]);
    }

    /** AJAX: elimina un comprobante adicional (no el legado en facturas_compra.comprobante_archivo — para ese, subir otro lo reemplaza). */
    public function eliminarComprobanteAdjunto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        $adjuntoModel = new FacturaCompraAdjuntoModel();
        $adjunto = $id ? $adjuntoModel->findById($id) : null;
        if (!$adjunto) $this->json(['ok' => false, 'error' => 'No encontrado.'], 404);

        $path = BASE_PATH . '/storage/compras/' . basename($adjunto['archivo']);
        if (file_exists($path)) @unlink($path);
        $adjuntoModel->eliminar($id);

        $this->json(['ok' => true, 'mensaje' => 'Comprobante eliminado.']);
    }

    /** GET: sirve un comprobante adicional con auth — mismo criterio que verComprobante(). */
    public function verComprobanteAdjunto(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        $adjuntoModel = new FacturaCompraAdjuntoModel();
        $adjunto = $id ? $adjuntoModel->findById($id) : null;
        if (!$adjunto) { http_response_code(404); exit; }

        $path = BASE_PATH . '/storage/compras/' . basename($adjunto['archivo']);
        if (!file_exists($path)) { http_response_code(404); exit; }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="comprobante_compra_adjunto_' . $id . '.' . $ext . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Mueve el archivo subido en $_FILES['comprobante_archivo'] a storage/compras/ */
    private function _subirArchivoCompra(int $facturaId): ?string
    {
        if (empty($_FILES['comprobante_archivo']) || $_FILES['comprobante_archivo']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file    = $_FILES['comprobante_archivo'];
        $mime    = mime_content_type($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (!in_array($mime, $allowed, true) || $file['size'] > 10 * 1024 * 1024) {
            return null;
        }

        $dir = BASE_PATH . '/storage/compras/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = match($mime) {
            'application/pdf' => 'pdf',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            default           => 'jpg',
        };

        $name = 'compra_' . $facturaId . '_' . time() . '.' . $ext;

        return move_uploaded_file($file['tmp_name'], $dir . $name) ? $name : null;
    }

    // La creación de activos fijos "sueltos" desde una compra (tilde "Es un activo fijo" por
    // ítem, ver guardar()/actualizar() arriba) vive ahora en ActivoFijoModel::crearActivosSueltos()
    // — la comparte con ChatController::toolRegistrarCompra(), que también puede marcar un
    // ítem como activo fijo desde el Chat IA. Antes cada uno tenía su propia copia de esto.
}
