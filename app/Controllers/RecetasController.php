<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/RecetaModel.php';
require_once BASE_PATH . '/app/Models/ProductoModel.php';

class RecetasController extends Controller
{
    private RecetaModel  $model;
    private ProductoModel $prodModel;

    public function __construct()
    {
        $this->model     = new RecetaModel();
        $this->prodModel = new ProductoModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $recetas = $this->model->findAllConDetalle();
        // Costo total de la receta (todo el lote/rendimiento) — para poder detectar a
        // simple vista, ordenando la columna, recetas con costos que quedaron incoherentes
        // (ej. un ingrediente mal cargado disparó el costo, o quedó en 0 por error).
        foreach ($recetas as &$r) {
            $r['costo_total'] = $this->model->calcularCosto((int) $r['id']);
        }
        unset($r);

        $this->render('recetas.index', [
            'pageTitle'  => 'Recetas de Producción',
            'breadcrumb' => ['Producción' => null, 'Recetas' => null],
            'recetas'    => $recetas,
        ]);
    }

    public function nueva(): void
    {
        $this->requireAuth();
        $productosActivos = Database::getInstance()->query(
            'SELECT id, nombre FROM productos WHERE eliminado=0 AND activo=1 ORDER BY nombre'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $this->render('recetas.form', [
            'pageTitle'          => 'Nueva receta',
            'breadcrumb'         => ['Producción' => null, 'Recetas' => 'recetas', 'Nueva' => null],
            'receta'             => null,
            'productos'          => $this->prodModel->findAllConDetalle(),
            'productosActivos'   => $productosActivos,
            'productosAsignados' => [],
        ]);
    }

    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        [$cabecera, $detalle, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $id = $this->model->guardar($cabecera, $detalle, (int)Session::user()['id']);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Receta creada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function duplicar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id     = (int)$this->input('id', 0);
        $nombre = trim($this->input('nombre', ''));
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        if (!$nombre) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        try {
            $nuevoId = $this->model->duplicar($id, $nombre, (int)Session::user()['id']);
            $this->json(['ok' => true, 'id' => $nuevoId, 'mensaje' => 'Receta duplicada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $productosActivos = Database::getInstance()->query(
            'SELECT id, nombre FROM productos WHERE eliminado=0 AND activo=1 ORDER BY nombre'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('recetas.form', [
            'pageTitle'          => 'Editar receta: ' . $receta['nombre'],
            'breadcrumb'         => ['Producción' => null, 'Recetas' => 'recetas', 'Editar' => null],
            'receta'             => $receta,
            'productos'          => $this->prodModel->findAllConDetalle(),
            'productosActivos'   => $productosActivos,
            'productosAsignados' => $this->model->getProductosAsignados($id),
        ]);
    }

    /**
     * Vista standalone para imprimir / exportar a PDF — pensada para el cocinero: solo
     * ingredientes, cantidades y preparación. Reusa findByIdConDetalle() (misma fuente que
     * el form de edición) pero la vista imprimir.php deliberadamente NO muestra ningún
     * campo de costo/precio/desvío, aunque el método los traiga.
     */
    public function imprimir(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) { http_response_code(404); echo '404 Not Found'; return; }

        $pageTitle = 'Receta: ' . $receta['nombre'];
        $version   = $this->model->versionActual($id);
        require BASE_PATH . '/app/Views/recetas/imprimir.php';
        exit;
    }

    /**
     * Libro de recetas: todas las fichas técnicas (misma vista que imprimir(), una por
     * receta — reusa _ficha.php) juntas en un solo documento navegable/imprimible, por
     * fuera del listado editable de /recetas. Siempre muestra el estado VIVO de cada
     * receta (misma fuente que editar()/imprimir()), así que "última versión" queda
     * garantizado sin tener que tocar nada cuando se edita una receta — no lee snapshots
     * viejos de recetas_versiones, solo el número de versión para mostrarlo en el título.
     */
    public function libro(): void
    {
        $this->requireAuth();

        $recetas   = $this->model->findAllConDetalleCompleto();
        $versiones = $this->model->versionesActuales();
        foreach ($recetas as &$r) {
            $r['version'] = $versiones[(int)$r['id']] ?? 1;
        }
        unset($r);
        usort($recetas, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

        $pageTitle = 'Libro de Recetas';
        require BASE_PATH . '/app/Views/recetas/libro.php';
        exit;
    }

    public function asignarProducto(): void
    {
        $this->requireAuth();
        Csrf::check();
        $recetaId   = (int)$this->input('receta_id');
        $productoId = (int)$this->input('producto_id');
        if (!$recetaId || !$productoId) $this->json(['ok' => false, 'error' => 'IDs inválidos.'], 422);
        $error = $this->model->asignarProducto($recetaId, $productoId);
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);
        $this->json(['ok' => true, 'productos' => $this->model->getProductosAsignados($recetaId)]);
    }

    public function quitarProducto(): void
    {
        $this->requireAuth();
        Csrf::check();
        $recetaId   = (int)$this->input('receta_id');
        $productoId = (int)$this->input('producto_id');
        if (!$recetaId || !$productoId) $this->json(['ok' => false, 'error' => 'IDs inválidos.'], 422);
        $this->model->quitarProducto($recetaId, $productoId);
        $this->json(['ok' => true, 'productos' => $this->model->getProductosAsignados($recetaId)]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$cabecera, $detalle, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $this->model->actualizarConDetalle($id, $cabecera, $detalle, (int)Session::user()['id']);
            $this->json(['ok' => true, 'mensaje' => 'Receta actualizada.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Receta eliminada.']);
    }

    /** Vista para ejecutar producción */
    public function produccion(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $costo = $this->model->calcularCosto($id);

        $this->render('recetas.produccion', [
            'pageTitle'  => 'Producir: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Producir' => null],
            'receta'     => $receta,
            'costo'      => $costo,
        ]);
    }

    /** POST: ejecutar producción */
    public function producir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id   = (int)$this->input('receta_id');
        $cant = (float)$this->input('cantidad', 1);
        if (!$id || $cant <= 0) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $result = $this->model->producir($id, $cant, Session::user()['id']);
            if (!$result['ok']) {
                $this->json([
                    'ok'       => false,
                    'error'    => 'Stock insuficiente.',
                    'faltantes'=> $result['faltantes'],
                ], 409);
            }
            $this->json([
                'ok'       => true,
                'mensaje'  => "Producción registrada: {$result['producido']} unidades.",
                'producido'=> $result['producido'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Listado de órdenes de producción */
    public function ordenes(): void
    {
        $this->requireAuth();

        $filtros = [
            'desde'     => $this->param('desde') ?: date('Y-m-01'),
            'hasta'     => $this->param('hasta') ?: date('Y-m-d'),
            'receta_id' => (int)$this->param('receta_id') ?: null,
            'estado'    => $this->param('estado') ?: '',
        ];

        $this->render('recetas.ordenes', [
            'pageTitle'  => 'Órdenes de Producción',
            'breadcrumb' => ['Producción' => null, 'Órdenes' => null],
            'ordenes'    => $this->model->findAllOrdenes($filtros),
            'recetas'    => $this->model->findAllConDetalle(),
            'filtros'    => $filtros,
        ]);
    }

    /** POST: planificar orden sin descontar stock */
    public function planificar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id   = (int)$this->input('receta_id');
        $cant = (float)$this->input('cantidad', 1);
        if (!$id || $cant <= 0) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $ordenId = $this->model->planificarOrden($id, $cant, (int)Session::user()['id']);
            $this->json(['ok' => true, 'id' => $ordenId, 'mensaje' => 'Orden planificada correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST: cambiar estado de una orden de producción */
    public function cambiarEstadoOrden(): void
    {
        $this->requireAuth();
        Csrf::check();

        $ordenId = (int)$this->input('orden_id');
        $estado  = $this->input('estado', '');
        if (!$ordenId || !$estado) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        try {
            $result = $this->model->cambiarEstadoOrden($ordenId, $estado, (int)Session::user()['id']);
            if (!$result['ok']) {
                $this->json([
                    'ok'        => false,
                    'error'     => $result['error'] ?? 'Error al cambiar estado.',
                    'faltantes' => $result['faltantes'] ?? [],
                ], 409);
            }
            $this->json(['ok' => true, 'mensaje' => 'Estado actualizado a "' . $estado . '".']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Historial de versiones de una receta */
    public function versiones(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        // El snapshot de cada versión guarda la composición, no el costo — se
        // costea acá con los precios actuales de cada ingrediente (ver docblock
        // de costearDetalleHistorico).
        $versiones = $this->model->findVersiones($id);
        foreach ($versiones as &$v) {
            $costeo           = $this->model->costearDetalleHistorico(json_decode($v['detalle_json'], true) ?: []);
            $v['detalle']     = $costeo['detalle'];
            $v['costo_total'] = $costeo['total'];
        }
        unset($v);

        $this->render('recetas.versiones', [
            'pageTitle'  => 'Historial de versiones: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Historial' => null],
            'receta'     => $receta,
            'versiones'  => $versiones,
        ]);
    }

    /** Detalle completo de todas las recetas (una fila por ingrediente) en CSV, para revisar/editar en Excel */
    public function exportar(): void
    {
        $this->requireAuth();

        $rows = $this->model->findParaExportar();

        $filename = 'recetas_detalle_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Formatea un número para Excel con configuración regional argentina:
        // enteros sin decimales, decimales con coma (no punto, que Excel AR
        // confunde con separador de miles).
        $n = static function (mixed $v): string {
            if ($v === null || $v === '') return '';
            $f = (float) $v;
            if ($f == floor($f)) return (string) (int) $f;
            return rtrim(rtrim(str_replace('.', ',', sprintf('%.4f', $f)), '0'), ',');
        };

        $tipoLabels = ['materia_prima' => 'Materia Prima', 'insumo' => 'Insumo', 'ingrediente_generico' => 'Ingrediente Genérico'];

        // findParaExportar() ya devuelve costo_unitario normalizado a la unidad base
        // (divide por cantidad_contenida cuando corresponde), así que acá solo se
        // multiplica por la cantidad que usa la receta.
        // Costo total de la receta = suma de (costo unitario × cantidad × (1 + merma%)) de cada línea
        $costoTotalPorReceta = [];
        foreach ($rows as $r) {
            $costoLinea = (float) $r['costo_unitario'] * (float) $r['cantidad'] * (1 + (float) $r['merma_pct'] / 100);
            $costoTotalPorReceta[$r['id_receta']] = ($costoTotalPorReceta[$r['id_receta']] ?? 0) + $costoLinea;
        }

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM para que Excel abra correctamente en UTF-8

        fputcsv($out, [
            'Receta', 'Producto Final', 'Rendimiento', 'Unidad Rendimiento',
            'Cant. Ingredientes', 'Estado', 'Costo Total Receta',
            'Tipo Ingrediente', 'Ingrediente', 'Cantidad', 'Unidad', 'Cantidad Contenida', 'Merma %',
            'Costo Unitario', 'Costo Total Ingrediente',
        ], ';');

        $recetaActual = null;
        foreach ($rows as $r) {
            if ($r['id_receta'] !== $recetaActual) {
                $recetaActual = $r['id_receta'];
                // Fila resumen al inicio de cada receta, con el costo total ya calculado
                fputcsv($out, [
                    $r['receta_nombre'],
                    $r['producto_final'] ?: '—',
                    $n($r['rendimiento']),
                    $r['unidad_rendimiento'],
                    $r['cant_ingredientes'],
                    $r['estado'],
                    $n($costoTotalPorReceta[$r['id_receta']] ?? 0),
                    '', '', '', '', '', '', '', '',
                ], ';');
            }

            $costoUnitario = (float) $r['costo_unitario'];
            $costoLinea    = $costoUnitario * (float) $r['cantidad'] * (1 + (float) $r['merma_pct'] / 100);

            fputcsv($out, [
                $r['receta_nombre'],
                '', '', '', '', '', '',
                $tipoLabels[$r['tipo_ingrediente']] ?? $r['tipo_ingrediente'],
                $r['ingrediente_nombre'] ?: '—',
                $n($r['cantidad']),
                $r['unidad'],
                $r['tipo_ingrediente'] === 'materia_prima' ? $n($r['cantidad_contenida']) : '',
                $n($r['merma_pct']),
                $n($costoUnitario),
                $n($costoLinea),
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function historialCosto(): void
    {
        $this->requireAuth();
        $id     = (int)$this->param('id');
        $receta = $this->model->findByIdConDetalle($id);
        if (!$receta) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('recetas.historial_costo', [
            'pageTitle'  => 'Historial de costo: ' . $receta['nombre'],
            'breadcrumb' => ['Producción' => null, 'Recetas' => 'recetas', 'Historial de costo' => null],
            'receta'     => $receta,
            'historial'  => $this->model->findHistorialCosto($id),
        ]);
    }

    /** AJAX: desglose de cómo se calculó el costo actual de un ingrediente (botón "¿cómo se calculó?" en el form) */
    public function detalleCosto(): void
    {
        $this->requireAuth();
        $tipo = $this->param('tipo', '');
        $id   = (int)$this->param('id');
        if (!in_array($tipo, ['materia_prima', 'insumo', 'ingrediente_generico'], true) || !$id) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }
        $this->json(['ok' => true] + $this->model->detalleCosto($tipo, $id));
    }

    /** AJAX: buscar ingredientes */
    public function buscarIngredientes(): void
    {
        $this->requireAuth();
        $q         = $this->param('q', '');
        $excluirId = (int)$this->param('receta_id', 0) ?: null;
        if (strlen($q) < 2) { $this->json([]); }
        $this->json($this->model->buscarIngredientes($q, $excluirId));
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

        $db      = Database::getInstance();
        $insRows = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM insumos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $mpRows  = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM materias_primas WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $recRows = $db->query("SELECT id, nombre, unidad_rendimiento AS unidad FROM recetas WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $genRows = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM ingredientes_genericos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $insIdx  = $this->_buildNameIdx($insRows);
        $mpIdx   = $this->_buildNameIdx($mpRows);
        $recIdx  = $this->_buildNameIdx($recRows);
        $genIdx  = $this->_buildNameIdx($genRows);
        $insUnidad = array_column($insRows, 'unidad', 'id');
        $mpUnidad  = array_column($mpRows, 'unidad', 'id');
        $recUnidad = array_column($recRows, 'unidad', 'id');
        $genUnidad = array_column($genRows, 'unidad', 'id');
        $prodIdx = $this->_buildNameIdx($db->query("SELECT id, nombre FROM productos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC));

        // Agrupar por nombre_receta
        $grupos = [];
        foreach ($rows as $i => $row) {
            $key = trim($row['nombre_receta'] ?? '');
            if ($key === '') continue;
            $grupos[$key][] = ['fila' => $i + 2, 'row' => $row];
        }

        $usuarioId    = (int)Session::user()['id'];
        $creados      = 0;
        $actualizados = 0;
        $errores      = [];
        $advertencias = [];

        foreach ($grupos as $nombreReceta => $filas) {
            $firstRow = $filas[0]['row'];

            $prodNombre = strtolower(trim($firstRow['producto_resultante'] ?? ''));
            $prodId     = $prodNombre !== '' ? ($prodIdx[$prodNombre] ?? null) : null;
            $tiempoPre  = ($v = trim($firstRow['tiempo_preparacion'] ?? '')) !== '' ? (int)$v : null;
            $porciones  = ($v = trim($firstRow['porciones'] ?? '')) !== '' ? (int)$v : null;
            $activoRaw  = strtolower(trim($firstRow['activo'] ?? '1'));
            $activo     = in_array($activoRaw, ['0', 'false', 'inactivo', 'desactivado', 'no'], true) ? 0 : 1;

            $detalle  = [];
            $hayError = false;

            foreach ($filas as $entry) {
                $fila      = $entry['fila'];
                $row       = $entry['row'];
                $ingNombre = trim($row['insumo_nombre'] ?? '');
                if ($ingNombre === '') continue;

                $cantidad = ImportHelper::toFloat($row['cantidad'] ?? '0');
                if ($cantidad <= 0) {
                    $errores[] = "Fila {$fila}: cantidad inválida para '{$ingNombre}'.";
                    $hayError  = true;
                    continue;
                }

                $unidadCelda = trim($row['unidad_medida'] ?? '');
                $ingKey      = strtolower($ingNombre);
                if (isset($insIdx[$ingKey])) {
                    $detalle[] = ['tipo_ingrediente' => 'insumo',        'materia_prima_id' => null,            'insumo_id' => $insIdx[$ingKey], 'ingrediente_generico_id' => null, 'receta_ingrediente_id' => null, 'cantidad' => $cantidad, 'merma_pct' => ImportHelper::toFloat($row['merma_pct'] ?? '0')];
                    $this->_avisarUnidad($advertencias, $fila, $ingNombre, $unidadCelda, $insUnidad[$insIdx[$ingKey]] ?? null);
                } elseif (isset($mpIdx[$ingKey])) {
                    $detalle[] = ['tipo_ingrediente' => 'materia_prima', 'materia_prima_id' => $mpIdx[$ingKey], 'insumo_id' => null,             'ingrediente_generico_id' => null, 'receta_ingrediente_id' => null, 'cantidad' => $cantidad, 'merma_pct' => ImportHelper::toFloat($row['merma_pct'] ?? '0')];
                    $this->_avisarUnidad($advertencias, $fila, $ingNombre, $unidadCelda, $mpUnidad[$mpIdx[$ingKey]] ?? null);
                } elseif (isset($recIdx[$ingKey])) {
                    if ($ingKey === strtolower(trim((string)$nombreReceta))) {
                        $errores[] = "Fila {$fila}: la receta '{$nombreReceta}' no puede usarse a sí misma como ingrediente.";
                        $hayError  = true;
                        continue;
                    }
                    $detalle[] = ['tipo_ingrediente' => 'receta', 'materia_prima_id' => null, 'insumo_id' => null, 'ingrediente_generico_id' => null, 'receta_ingrediente_id' => $recIdx[$ingKey], 'cantidad' => $cantidad, 'merma_pct' => ImportHelper::toFloat($row['merma_pct'] ?? '0')];
                    $this->_avisarUnidad($advertencias, $fila, $ingNombre, $unidadCelda, $recUnidad[$recIdx[$ingKey]] ?? null);
                } elseif (isset($genIdx[$ingKey])) {
                    $detalle[] = ['tipo_ingrediente' => 'ingrediente_generico', 'materia_prima_id' => null, 'insumo_id' => null, 'ingrediente_generico_id' => $genIdx[$ingKey], 'receta_ingrediente_id' => null, 'cantidad' => $cantidad, 'merma_pct' => ImportHelper::toFloat($row['merma_pct'] ?? '0')];
                    $this->_avisarUnidad($advertencias, $fila, $ingNombre, $unidadCelda, $genUnidad[$genIdx[$ingKey]] ?? null);
                } else {
                    $errores[] = "Fila {$fila}: ingrediente '{$ingNombre}' no encontrado (no existe como insumo, materia prima, receta ni ingrediente genérico).";
                    $hayError  = true;
                }
            }

            if ($hayError || empty($detalle)) {
                if (empty($detalle) && !$hayError) {
                    $errores[] = "Receta '{$nombreReceta}': sin ingredientes válidos.";
                }
                continue;
            }

            // Si ya existe una receta con este nombre, se actualiza (cabecera + se
            // reemplaza todo su detalle) en vez de crear una copia duplicada — así
            // reimportar la misma planilla para corregir algo no va acumulando
            // recetas repetidas con el mismo nombre.
            $existente = $this->model->findByNombre($nombreReceta);

            $cabecera = [
                'nombre'             => $nombreReceta,
                'descripcion'        => ImportHelper::nullIfEmpty($firstRow['descripcion'] ?? ''),
                'instrucciones'      => ImportHelper::nullIfEmpty($firstRow['instrucciones'] ?? ''),
                'tiempo_preparacion' => $tiempoPre,
                'porciones'          => $porciones,
                'producto_id'        => $prodId,
                'rendimiento'        => $existente ? (float)$existente['rendimiento']        : 1,
                'unidad_rendimiento' => $existente ? $existente['unidad_rendimiento']         : 'unidades',
                'activo'             => $activo,
                'eliminado'          => 0,
            ];

            try {
                if ($existente) {
                    $this->model->actualizarConDetalle((int)$existente['id'], $cabecera, $detalle, $usuarioId);
                    $actualizados++;
                } else {
                    $this->model->guardar($cabecera, $detalle, $usuarioId);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $errores[] = "Receta '{$nombreReceta}': " . $e->getMessage();
            }
        }

        $this->json([
            'ok'           => true,
            'importados'   => $creados + $actualizados,
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'total'        => count($grupos),
            'errores'      => $errores,
            'advertencias' => $advertencias,
        ]);
    }

    /** Normaliza un texto libre de unidad a un código corto comparable contra unidades_medida.abreviatura. */
    private function _normalizarUnidad(string $v): string
    {
        $v   = strtolower(trim($v));
        $map = [
            'gr' => 'g', 'grs' => 'g', 'gramo' => 'g', 'gramos' => 'g',
            'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
            'cc' => 'ml', 'mililitro' => 'ml', 'mililitros' => 'ml',
            'lt' => 'l', 'lts' => 'l', 'litro' => 'l', 'litros' => 'l',
            'unidad' => 'u', 'unidades' => 'u', 'un' => 'u',
            'docena' => 'doc', 'docenas' => 'doc',
            'paquete' => 'paq', 'paquetes' => 'paq',
            'cajas' => 'caja',
            'metro' => 'm', 'metros' => 'm',
            'centimetro' => 'cm', 'centimetros' => 'cm', 'centímetro' => 'cm', 'centímetros' => 'cm',
        ];
        return $map[$v] ?? $v;
    }

    /** Si la unidad tipeada en la planilla no coincide con la del ingrediente encontrado, agrega un aviso (no bloquea la fila). */
    private function _avisarUnidad(array &$advertencias, int $fila, string $ingNombre, string $unidadCelda, ?string $unidadReal): void
    {
        if ($unidadCelda === '' || $unidadReal === null || trim($unidadReal) === '') return;
        if ($this->_normalizarUnidad($unidadCelda) !== $this->_normalizarUnidad($unidadReal)) {
            $advertencias[] = "Fila {$fila}: la unidad '{$unidadCelda}' no coincide con la unidad de '{$ingNombre}' ({$unidadReal}).";
        }
    }

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

        $db      = Database::getInstance();
        $insRows = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM insumos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $mpRows  = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM materias_primas WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $recRows = $db->query("SELECT id, nombre, unidad_rendimiento AS unidad FROM recetas WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $genRows = $db->query("SELECT id, nombre, (SELECT abreviatura FROM unidades_medida WHERE id=unidad_medida_id) AS unidad FROM ingredientes_genericos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $insIdx  = $this->_buildNameIdx($insRows);
        $mpIdx   = $this->_buildNameIdx($mpRows);
        $recIdx  = $this->_buildNameIdx($recRows);
        $genIdx  = $this->_buildNameIdx($genRows);
        $insUnidad = array_column($insRows, 'unidad', 'id');
        $mpUnidad  = array_column($mpRows, 'unidad', 'id');
        $recUnidad = array_column($recRows, 'unidad', 'id');
        $genUnidad = array_column($genRows, 'unidad', 'id');
        $prodIdx = $this->_buildNameIdx($db->query("SELECT id, nombre FROM productos WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC));

        $grupos = [];
        foreach ($rows as $i => $row) {
            $key = trim($row['nombre_receta'] ?? '');
            $grupos[$key][] = ['fila' => $i + 2, 'row' => $row];
        }

        $filas   = [];
        $resumen = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($grupos as $nombreReceta => $entradas) {
            $primeraFila = $entradas[0]['fila'];

            if ($nombreReceta === '') {
                $filas[] = ['fila' => $primeraFila, 'estado' => 'error', 'titulo' => '(sin nombre)', 'mensaje' => 'nombre_receta es obligatorio.'];
                $resumen['error']++;
                continue;
            }

            $firstRow    = $entradas[0]['row'];
            $mensajes    = [];
            $estadoGrupo = 'ok';

            if ($this->model->findByNombre($nombreReceta)) {
                $mensajes[] = 'Ya existe: se actualizará (reemplaza sus ingredientes actuales).';
            }

            $prodNombre = strtolower(trim($firstRow['producto_resultante'] ?? ''));
            if ($prodNombre !== '' && !isset($prodIdx[$prodNombre])) {
                $mensajes[]  = "Producto '{$firstRow['producto_resultante']}' no encontrado.";
                $estadoGrupo = 'warning';
            }

            $cantIngValidos = 0;
            $ingErrMensajes = [];
            $ingAvisos      = [];
            foreach ($entradas as $entry) {
                $ingNombre = trim($entry['row']['insumo_nombre'] ?? '');
                if ($ingNombre === '') continue;
                $cantidad    = ImportHelper::toFloat($entry['row']['cantidad'] ?? '0');
                $unidadCelda = trim($entry['row']['unidad_medida'] ?? '');
                $ingKey      = strtolower($ingNombre);

                if ($ingKey === strtolower(trim((string)$nombreReceta)) && isset($recIdx[$ingKey])) {
                    $ingErrMensajes["si_misma:{$ingKey}"] = "'{$ingNombre}' no puede usarse a sí misma como ingrediente";
                    $estadoGrupo      = 'error';
                } elseif (!isset($insIdx[$ingKey]) && !isset($mpIdx[$ingKey]) && !isset($recIdx[$ingKey]) && !isset($genIdx[$ingKey])) {
                    $ingErrMensajes["no_encontrado:{$ingKey}"] = "'{$ingNombre}' no encontrado (no existe como insumo, materia prima, receta ni ingrediente genérico)";
                    $estadoGrupo      = 'error';
                } elseif ($cantidad <= 0) {
                    $ingErrMensajes["cant_invalida:{$ingKey}"] = "'{$ingNombre}' cantidad inválida";
                    if ($estadoGrupo !== 'error') $estadoGrupo = 'warning';
                } else {
                    $cantIngValidos++;
                    $unidadReal = $insUnidad[$insIdx[$ingKey] ?? null] ?? $mpUnidad[$mpIdx[$ingKey] ?? null] ?? $recUnidad[$recIdx[$ingKey] ?? null] ?? $genUnidad[$genIdx[$ingKey] ?? null] ?? null;
                    if ($unidadCelda !== '' && $unidadReal !== null && trim($unidadReal) !== ''
                        && $this->_normalizarUnidad($unidadCelda) !== $this->_normalizarUnidad($unidadReal)) {
                        $ingAvisos["unidad:{$ingKey}"] = "'{$ingNombre}' unidad '{$unidadCelda}' no coincide con '{$unidadReal}'";
                        if ($estadoGrupo !== 'error') $estadoGrupo = 'warning';
                    }
                }
            }

            if (!empty($ingErrMensajes)) {
                $mensajes[] = 'Ingredientes: ' . implode(', ', $ingErrMensajes) . '.';
            }
            if (!empty($ingAvisos)) {
                $mensajes[] = 'Avisos: ' . implode(', ', $ingAvisos) . '.';
            }
            if ($cantIngValidos === 0 && $estadoGrupo !== 'error') {
                $mensajes[]  = 'Sin ingredientes válidos.';
                $estadoGrupo = 'error';
            }
            if ($estadoGrupo === 'ok') {
                $mensajes[] = "{$cantIngValidos} ingrediente(s) válido(s).";
            }

            $filas[]              = ['fila' => $primeraFila, 'estado' => $estadoGrupo, 'titulo' => $nombreReceta, 'mensaje' => implode(' ', $mensajes)];
            $resumen[$estadoGrupo]++;
        }

        $this->json(['ok' => true, 'filas' => $filas, 'resumen' => $resumen, 'total' => count($filas)]);
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_recetas.csv',
            ['nombre_receta', 'descripcion', 'instrucciones', 'producto_resultante', 'tiempo_preparacion', 'porciones', 'activo', 'insumo_nombre', 'cantidad', 'merma_pct', 'unidad_medida'],
            [
                ['Cookies genéricas', 'Base reutilizable en varias recetas', 'Mezclar y hornear 12 min a 180°C', '', '20', '', '1', 'Harina 000', '500', '2', 'gr'],
                ['Cookies genéricas', '', '', '', '', '', '', 'Manteca', '250', '0', 'gr'],
                ['Vaso de mini cookies', 'Vaso decorativo con mini cookies', 'Armar el vaso con los ingredientes', '', '10', '1', '1', 'Vasito de plástico', '1', '0', 'u'],
                ['Vaso de mini cookies', '', '', '', '', '', '', 'Cookies genéricas', '80', '0', 'gr'],
                ['Vaso de mini cookies', '', '', '', '', '', '', 'Jugo de naranja', '200', '0', 'cc'],
                ['Torta de chocolate',   'Torta húmeda de chocolate', 'Batir, hornear 180°C 35 min', 'Torta chocolate 1kg', '60', '8', '1', 'Harina 000', '500', '2', 'gr'],
                ['Torta de chocolate',   '', '', '', '', '', '', 'Azúcar blanca', '300', '0', 'gr'],
                ['Torta de chocolate',   '', '', '', '', '', '', 'Cacao amargo',  '100', '5', 'gr'],
                ['Torta de chocolate',   '', '', '', '', '', '', 'Leche', '250', '0', 'ml'],
            ]
        );
    }

    private function _buildNameIdx(array $rows): array
    {
        $idx = [];
        foreach ($rows as $r) {
            $idx[strtolower($r['nombre'])] = $r['id'];
        }
        return $idx;
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre     = $this->input('nombre', '');
        $prodId     = (int)$this->input('producto_id') ?: null;
        $rend       = (float)$this->input('rendimiento', 1);
        $unidad     = $this->input('unidad_rendimiento', 'unidades');
        $activo     = (int)$this->input('activo', 1);
        $notas      = $this->input('notas', '') ?: null;
        $descripcion    = $this->input('descripcion', '') ?: null;
        $instrucciones  = $this->input('instrucciones', '') ?: null;
        $tiempoPre      = ($v = (int)$this->input('tiempo_preparacion', 0)) > 0 ? $v : null;
        $porciones      = ($v = (int)$this->input('porciones', 0)) > 0 ? $v : null;

        if (!$nombre) $errores[] = 'El nombre de la receta es obligatorio.';
        if ($rend <= 0) $errores[] = 'El rendimiento debe ser mayor que 0.';

        $itemsJson = $this->input('items_json', '[]');
        $items     = json_decode($itemsJson, true);
        if (!is_array($items) || !count($items)) {
            $errores[] = 'Agregue al menos un ingrediente.';
        }

        $cabecera = [
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'instrucciones'      => $instrucciones,
            'tiempo_preparacion' => $tiempoPre,
            'porciones'          => $porciones,
            'producto_id'        => $prodId,
            'rendimiento'        => $rend,
            'unidad_rendimiento' => $unidad,
            'activo'             => $activo,
            'notas'              => $notas,
            'eliminado'          => 0,
        ];

        $detalle = [];
        foreach ($items ?? [] as $it) {
            $tipo = $it['tipo_ingrediente'] ?? '';
            $cant = (float)($it['cantidad'] ?? 0);
            if ($cant <= 0) { $errores[] = 'Cantidad inválida en ingrediente.'; continue; }
            $detalle[] = [
                'tipo_ingrediente'        => $tipo,
                'materia_prima_id'        => $tipo === 'materia_prima'        ? (int)$it['item_id'] : null,
                'insumo_id'               => $tipo === 'insumo'               ? (int)$it['item_id'] : null,
                'ingrediente_generico_id' => $tipo === 'ingrediente_generico' ? (int)$it['item_id'] : null,
                'receta_ingrediente_id'   => $tipo === 'receta'               ? (int)$it['item_id'] : null,
                'cantidad'         => $cant,
                'merma_pct'        => (float)($it['merma_pct'] ?? 0),
            ];
        }

        return [$cabecera, $detalle, $errores];
    }
}
