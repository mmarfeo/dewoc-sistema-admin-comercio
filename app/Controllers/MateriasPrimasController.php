<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/MateriaPrimaModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

class MateriasPrimasController extends Controller
{
    private MateriaPrimaModel $model;

    public function __construct()
    {
        $this->model = new MateriaPrimaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $catModel  = new CategoriaModel();
        $provModel = new ProveedorModel();
        $this->render('materias-primas.index', [
            'pageTitle'      => 'Materias Primas',
            'breadcrumb'     => ['Maestros' => null, 'Materias Primas' => null],
            'items'          => $this->model->findAllConDetalle(),
            'categorias'     => $catModel->findAllConPadre('materia_prima'),
            'proveedores'    => $provModel->findAllActivos(),
            'unidades'       => Database::getInstance()->query('SELECT * FROM unidades_medida ORDER BY nombre')->fetchAll(),
            'genericos'      => (new IngredienteGenericoModel())->listar(),
        ]);
    }

    public function nuevo(): void { $this->index(); }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        if ($error = $this->validarGenerico($data)) $this->json(['ok' => false, 'error' => $error], 422);

        $id = $this->model->insert($data);
        AuditLog::log('crear', 'materias_primas', "Materia prima creada: {$data['nombre']} (id={$id}, costo_unitario={$data['costo_unitario']})");
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Materia prima creada.']);
    }

    public function editar(): void { $this->index(); }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        if ($error = $this->validarGenerico($data)) $this->json(['ok' => false, 'error' => $error], 422);

        $anterior = $this->model->findById($id);
        $this->model->update($id, $data);
        AuditLog::log('editar', 'materias_primas', sprintf(
            "Materia prima actualizada: %s (id=%d, costo_unitario: %s -> %s)",
            $data['nombre'], $id, $anterior['costo_unitario'] ?? '?', $data['costo_unitario']
        ));

        // Bug real corregido (ver conversación de soporte): esta acción nunca propagaba el
        // cambio a las recetas/productos que usan esta materia prima — solo lo hacía una
        // compra nueva (FacturaCompraModel::actualizarStock()). Si acá se editaba a mano el
        // costo_unitario o el desvio_pct (ej. corrigiendo un dato mal cargado, sin pasar por
        // una factura), productos.precio_costo quedaba congelado en el valor viejo para
        // siempre — mostrando un costo/desvío inflado que ya no reflejaba la realidad.
        require_once BASE_PATH . '/app/Models/RecetaModel.php';
        $recetaModel = new RecetaModel();
        $recetaIds   = $recetaModel->findIdsPorMateriaPrima($id);
        if (!empty($data['ingrediente_generico_id'])) {
            $recetaIds = array_merge($recetaIds, $recetaModel->findIdsPorGenerico((int) $data['ingrediente_generico_id']));
        }
        if ($recetaIds) {
            $recetaModel->propagarCambioCosto(array_unique($recetaIds), null, 'Recálculo por edición de materia prima');
        }

        $this->json(['ok' => true, 'mensaje' => 'Materia prima actualizada.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneUsoEnRecetas($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: está en uso en recetas.'], 409);
        }

        $mp = $this->model->findById($id);
        $this->model->softDelete($id);
        AuditLog::log('eliminar', 'materias_primas', "Materia prima eliminada: " . ($mp['nombre'] ?? "id={$id}"));
        $this->json(['ok' => true, 'mensaje' => 'Materia prima eliminada.']);
    }

    /**
     * Recalcula costo_unitario repasando TODO el historial real de compras del ítem
     * (mismo Costo Promedio Ponderado que ya usa el sistema al editar/anular una
     * factura — ver FacturaCompraModel::recalcularCostoDesdeHistorial()), y propaga
     * el cambio a las recetas/ingredientes genéricos que lo usan. Botón de
     * autoservicio para cuando el costo guardado quedó desincronizado del real (ej.
     * una importación con el precio del paquete completo en la columna equivocada).
     */
    public function recalcularCosto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $mp = $this->model->findById($id);
        if (!$mp) $this->json(['ok' => false, 'error' => 'No encontrada.'], 404);

        require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
        $costoAnterior = (float)$mp['costo_unitario'];
        (new FacturaCompraModel())->recalcularCostoDesdeHistorial('materia_prima', $id);

        $actualizado = $this->model->findById($id);
        AuditLog::log('recalcular', 'materias_primas', sprintf(
            "Costo recalculado desde historial de compras: %s (id=%d, %s -> %s)",
            $mp['nombre'], $id, $costoAnterior, $actualizado['costo_unitario']
        ));
        $this->json(['ok' => true, 'costo_unitario' => $actualizado['costo_unitario'], 'mensaje' => 'Costo recalculado desde el historial de compras.']);
    }

    public function historialPrecios(): void
    {
        $this->requireAuth();

        $id = (int)$this->param('id');
        if (!$id) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $mp = $this->model->findById($id);
        if (!$mp) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $historial = $this->model->historialPrecios($id);

        $this->render('materias-primas.historial_precios', [
            'pageTitle'  => 'Historial de Precios — ' . $mp['nombre'],
            'breadcrumb' => ['Materias Primas' => 'materias-primas', $mp['nombre'] => null, 'Historial precios' => null],
            'mp'         => $mp,
            'historial'  => $historial,
        ]);
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

        $db          = Database::getInstance();
        $unidades    = $db->query("SELECT id, abreviatura FROM unidades_medida")->fetchAll(\PDO::FETCH_ASSOC);
        $categorias  = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0 AND tipo='materia_prima'")->fetchAll(\PDO::FETCH_ASSOC);
        $proveedores = $db->query("SELECT id, razon_social FROM proveedores WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $unidadIdx    = array_column($unidades,    'id', 'abreviatura');
        $categoriaIdx = array_column($categorias,  'id', 'nombre');
        $proveedorIdx = array_column($proveedores, 'id', 'razon_social');

        $filas   = [];
        $resumen = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');

            if (!$nombre) {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => "(fila {$fila})", 'mensaje' => "Campo 'nombre' es obligatorio."];
                $resumen['error']++;
                continue;
            }

            $unidadAbrev = trim($row['unidad'] ?? '');
            if ($unidadAbrev !== '' && !isset($unidadIdx[$unidadAbrev])) {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => $nombre, 'mensaje' => "Unidad '{$unidadAbrev}' no existe. Abreviaturas disponibles: " . implode(', ', array_keys($unidadIdx)) . '.'];
                $resumen['error']++;
                continue;
            }

            $warns = [];

            if ($unidadAbrev === '') {
                $warns[] = 'Sin unidad de medida';
            }

            $catNombre  = trim($row['categoria'] ?? '');
            if ($catNombre !== '' && !isset($categoriaIdx[$catNombre])) {
                $warns[] = "Categoría '{$catNombre}' no encontrada — se importará sin categoría";
            }

            $provNombre = trim($row['proveedor'] ?? '');
            if ($provNombre !== '' && !isset($proveedorIdx[$provNombre])) {
                $warns[] = "Proveedor '{$provNombre}' no encontrado — se importará sin proveedor";
            }

            $stockActual = ImportHelper::toFloat($row['stock_actual'] ?? '0');
            if ($stockActual < 0) {
                $warns[] = 'stock_actual negativo — se usará 0';
            }

            $yaExiste = (bool) $this->model->findByNombre($nombre);
            $accion   = $yaExiste ? 'Ya existe — se actualizará' : 'Se creará como nueva';

            if ($warns) {
                $filas[] = ['fila' => $fila, 'estado' => 'warning', 'titulo' => $nombre, 'mensaje' => $accion . '; ' . implode('; ', $warns)];
                $resumen['warning']++;
            } else {
                $filas[] = ['fila' => $fila, 'estado' => 'ok', 'titulo' => $nombre, 'mensaje' => $accion];
                $resumen['ok']++;
            }
        }

        $this->json(['ok' => true, 'filas' => $filas, 'resumen' => $resumen, 'total' => count($rows)]);
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
        $unidades    = $db->query("SELECT id, abreviatura FROM unidades_medida")->fetchAll(\PDO::FETCH_ASSOC);
        $categorias  = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0 AND tipo='materia_prima'")->fetchAll(\PDO::FETCH_ASSOC);
        $proveedores = $db->query("SELECT id, razon_social FROM proveedores WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $unidadIdx    = array_column($unidades,   'id', 'abreviatura');
        $categoriaIdx = array_column($categorias, 'id', 'nombre');
        $proveedorIdx = array_column($proveedores,'id', 'razon_social');

        $creados      = 0;
        $actualizados = 0;
        $errores      = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');
            if (!$nombre) { $errores[] = "Fila {$fila}: 'nombre' es obligatorio."; continue; }

            $unidadAbrev = trim($row['unidad'] ?? '');
            $unidadId    = $unidadAbrev !== '' ? ($unidadIdx[$unidadAbrev] ?? null) : null;
            if ($unidadAbrev !== '' && $unidadId === null) {
                $errores[] = "Fila {$fila}: Unidad '{$unidadAbrev}' no encontrada."; continue;
            }
            $catNombre   = trim($row['categoria'] ?? '');
            $categoriaId = $catNombre !== '' ? ($categoriaIdx[$catNombre] ?? null) : null;
            $provNombre  = trim($row['proveedor'] ?? '');
            $proveedorId = $provNombre !== '' ? ($proveedorIdx[$provNombre] ?? null) : null;

            $cantContenida = ImportHelper::toFloat($row['cantidad_contenida'] ?? '0');
            $estadoStr     = strtolower(trim($row['estado'] ?? ''));
            $activo        = ($estadoStr === 'desactivado' || $estadoStr === 'inactivo' || $estadoStr === '0') ? 0 : 1;

            try {
                $existente = $this->model->findByNombre($nombre);

                if ($existente) {
                    // Ya existe una MP con ese nombre: actualiza solo las columnas que
                    // vinieron con datos en la fila (una celda vacía significa "no
                    // tocar", no "borrar el valor actual"). El stock_actual nunca se
                    // toca en una actualización: es un valor vivo que se mueve con
                    // compras/producción, no algo que una reimportación deba resetear.
                    $data = [];
                    if (trim($row['codigo'] ?? '') !== '')          $data['codigo']             = trim($row['codigo']);
                    if ($categoriaId !== null)                       $data['categoria_id']       = $categoriaId;
                    if ($unidadId !== null)                          $data['unidad_medida_id']   = $unidadId;
                    if ($cantContenida > 0)                          $data['cantidad_contenida'] = $cantContenida;
                    if (trim($row['stock_minimo'] ?? '') !== '')    $data['stock_minimo']       = ImportHelper::toFloat($row['stock_minimo']);
                    if (trim($row['costo_unitario'] ?? '') !== '') {
                        // Mismo criterio que el modal (recogerForm()): la columna trae el precio
                        // del ENVASE completo (ej. "$40.604 el balde de 6.400gr"), no por unidad
                        // base — se divide por "Cantidad contenida" antes de guardar. Si la fila
                        // no trae cantidad_contenida, se usa la que ya tenía guardada la MP.
                        $ccParaCosto = $cantContenida > 0 ? $cantContenida : (float)($existente['cantidad_contenida'] ?? 0);
                        $costoEnvase = ImportHelper::toFloat($row['costo_unitario']);
                        $data['costo_unitario'] = $ccParaCosto > 0 ? $costoEnvase / $ccParaCosto : $costoEnvase;
                    }
                    if ($proveedorId !== null)                       $data['proveedor_id']       = $proveedorId;
                    if (trim($row['estado'] ?? '') !== '')          $data['activo']             = $activo;

                    if ($data) {
                        $this->model->update((int) $existente['id'], $data);
                    }
                    $actualizados++;
                } else {
                    // Mismo criterio que el modal: "costo_unitario" en la planilla es el precio
                    // del envase completo, se divide por cantidad_contenida antes de guardar.
                    $costoEnvaseNuevo = ImportHelper::toFloat($row['costo_unitario'] ?? '0');
                    $costoUnitarioNuevo = $cantContenida > 0 ? $costoEnvaseNuevo / $cantContenida : $costoEnvaseNuevo;
                    $this->model->insert([
                        'nombre'             => $nombre,
                        'codigo'             => ImportHelper::nullIfEmpty($row['codigo'] ?? ''),
                        'categoria_id'       => $categoriaId,
                        'unidad_medida_id'   => $unidadId,
                        'cantidad_contenida' => $cantContenida > 0 ? $cantContenida : null,
                        'stock_actual'       => ImportHelper::toFloat($row['stock_actual'] ?? '0'),
                        'stock_minimo'       => ImportHelper::toFloat($row['stock_minimo'] ?? '0'),
                        'costo_unitario'     => $costoUnitarioNuevo,
                        'proveedor_id'       => $proveedorId,
                        'activo'             => $activo,
                        'eliminado'          => 0,
                    ]);
                    $creados++;
                }
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
            }
        }

        AuditLog::log('importar', 'materias_primas', "Importación: {$creados} creadas, {$actualizados} actualizadas, " . count($errores) . " errores (de " . count($rows) . " filas)");

        $this->json([
            'ok'           => true,
            'importados'   => $creados + $actualizados,
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'total'        => count($rows),
            'errores'      => $errores,
        ]);
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_materias_primas.csv',
            ['nombre', 'codigo', 'unidad', 'cantidad_contenida', 'categoria', 'proveedor', 'stock_actual', 'stock_minimo', 'costo_unitario', 'estado'],
            [
                ['Harina 000',    'HAR-000', 'kg', '50', 'Harinas',  'Molinos SA', '50', '10', '1500', 'Activo'],
                ['Azúcar blanca', 'AZU-001', 'kg', '25', 'Azúcares', '',           '30',  '5',  '800', 'Activo'],
            ]
        );
    }

    public function toggleActivo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $mp = $this->model->findById($id);
        if (!$mp) $this->json(['ok' => false, 'error' => 'Materia prima no encontrada.'], 404);

        $nuevo = $mp['activo'] ? 0 : 1;
        $this->model->update($id, ['activo' => $nuevo]);
        $this->json([
            'ok'     => true,
            'activo' => $nuevo,
            'mensaje' => $nuevo ? 'Materia prima activada.' : 'Materia prima desactivada.',
        ]);
    }

    private function recogerForm(): array
    {
        $cantContenida = (float)$this->input('cantidad_contenida', 0);
        $tiposGasto = ['costo_variable', 'costo_fijo', 'impuesto', 'gasto'];
        $tipoGasto  = $this->input('tipo_gasto', 'costo_variable');
        // El campo "Costo unitario ($)" del formulario se carga como precio del ENVASE
        // completo (ej. "$1688 la botella de 1L"), no por unidad base — el hint de abajo
        // ("= $X por cc") ya lo mostraba así, pero antes esa conversión nunca se aplicaba
        // al guardar y quedaba el precio del envase crudo en costo_unitario, que el resto
        // del sistema (recetas, compras) sí consume como precio por unidad base. Acá se
        // divide para que lo guardado quede consistente con eso.
        $costoIngresado = (float)$this->input('costo_unitario', 0);
        $costoUnitario  = $cantContenida > 0 ? $costoIngresado / $cantContenida : $costoIngresado;
        return [
            'nombre'             => $this->input('nombre', ''),
            'codigo'             => $this->input('codigo', '') ?: null,
            'categoria_id'       => (int)($this->input('categoria_id', 0)) ?: null,
            'tipo_gasto'         => in_array($tipoGasto, $tiposGasto) ? $tipoGasto : 'costo_variable',
            'unidad_medida_id'   => (int)($this->input('unidad_medida_id', 0)) ?: null,
            'cantidad_contenida' => $cantContenida > 0 ? $cantContenida : null,
            'stock_minimo'       => (float)$this->input('stock_minimo', 0),
            'costo_unitario'     => $costoUnitario,
            'desvio_pct'         => max(0.0, (float)$this->input('desvio_pct', 0)), // pérdida: nunca puede abaratar el costo
            'proveedor_id'       => (int)($this->input('proveedor_id', 0)) ?: null,
            'ingrediente_generico_id' => (int)($this->input('ingrediente_generico_id', 0)) ?: null,
            'activo'             => (int)$this->input('activo', 1),
            'controla_stock'     => (int)$this->input('controla_stock', 1),
            'eliminado'          => 0,
        ];
    }

    /** Si se asigna un genérico, su unidad de medida base debe coincidir con la de la MP */
    private function validarGenerico(array $data): ?string
    {
        if (empty($data['ingrediente_generico_id'])) return null;
        if (empty($data['unidad_medida_id'])) return 'Elegí una unidad de medida para poder asignar un ingrediente genérico.';

        $generico = new IngredienteGenericoModel();
        if (!$generico->unidadCompatible((int)$data['ingrediente_generico_id'], (int)$data['unidad_medida_id'])) {
            return 'La unidad de medida de esta materia prima no coincide con la del ingrediente genérico elegido. Deben ser la misma para que los costos sean comparables.';
        }
        return null;
    }
}
