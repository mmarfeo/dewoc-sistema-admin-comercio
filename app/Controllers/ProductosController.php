<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ProductoModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/GrupoOpcionModel.php';
require_once BASE_PATH . '/core/PrecioHelper.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';

class ProductosController extends Controller
{
    private ProductoModel  $model;
    private CategoriaModel $catModel;

    public function __construct()
    {
        $this->model    = new ProductoModel();
        $this->catModel = new CategoriaModel();
    }

    private function unidades(): array
    {
        return Database::getInstance()->query('SELECT * FROM unidades_medida ORDER BY nombre')->fetchAll();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('productos.index', [
            'pageTitle'      => 'Productos',
            'breadcrumb'     => ['Maestros' => null, 'Productos' => null],
            'productos'      => $this->model->findAllConDetalle(),
            'categorias'     => $this->catModel->findAllConPadre('producto'),
            'unidades'       => $this->unidades(),
            'recetasActivas' => Database::getInstance()->query(
                'SELECT id, nombre FROM recetas WHERE eliminado=0 AND activo=1 ORDER BY nombre'
            )->fetchAll(\PDO::FETCH_ASSOC),
            // Sin este permiso, el usuario no ve el costo de MP/receta de cada producto
            // (info sensible: revela márgenes) — mismo criterio que stock_ver_teorico.
            'verCostos'      => canView('productos_ver_costos'),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('productos.form', [
            'pageTitle'        => 'Nuevo producto',
            'breadcrumb'       => ['Maestros' => null, 'Productos' => 'productos', 'Nuevo' => null],
            'producto'         => null,
            'categorias'       => $this->catModel->findAllConPadre('producto'),
            'unidades'         => $this->unidades(),
            'recetasActivas'   => Database::getInstance()->query(
                'SELECT id, nombre FROM recetas WHERE eliminado=0 AND activo=1 ORDER BY nombre'
            )->fetchAll(\PDO::FETCH_ASSOC),
            'recetasAsignadas'       => [],
            'gruposOpcionesActivos'  => (new GrupoOpcionModel())->listarActivos(),
            'gruposOpcionesAsignados'=> [],
            'promoComponentes'       => [],
            // Opción D9: define si el precio cargado ya trae IVA, y con eso la ayuda del
            // campo y el "El cliente va a ver: $ X" del precio online.
            'preciosIncluyenIva'     => PrecioHelper::preciosIncluyenIva(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerFormulario();
        $errores = $this->validar($data);
        if ($errores) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }

        $imagen = $this->subirImagen();
        if ($imagen !== null) $data['imagen'] = $imagen;

        $id = $this->model->insert($data);
        MenuAppNotifier::catalogoCambio('producto', $id);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Producto creado correctamente.']);
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id', 0);
        $producto = $this->model->findByIdConDetalle($id);
        if (!$producto) abort(404);

        $recetasAsignadas = $this->model->getRecetasConCosto($id);
        $recetasActivas   = Database::getInstance()->query(
            'SELECT id, nombre FROM recetas WHERE eliminado=0 AND activo=1 ORDER BY nombre'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('productos.form', [
            'pageTitle'        => 'Editar producto',
            'breadcrumb'       => ['Maestros' => null, 'Productos' => 'productos', 'Editar' => null],
            'producto'         => $producto,
            'categorias'       => $this->catModel->findAllConPadre('producto'),
            'unidades'         => $this->unidades(),
            'recetasAsignadas'        => $recetasAsignadas,
            'recetasActivas'          => $recetasActivas,
            'gruposOpcionesActivos'   => (new GrupoOpcionModel())->listarActivos(),
            'gruposOpcionesAsignados' => $this->model->getGruposOpciones($id),
            'promoComponentes'        => $this->model->getPromoComponentes($id),
            'tieneRecetaPropia'       => $this->model->tieneRecetaPropia($id),
            'preciosIncluyenIva'      => PrecioHelper::preciosIncluyenIva(),
        ]);
    }

    /**
     * Recalcula precio_costo repasando TODO el historial real de compras del producto
     * (mismo Costo Promedio Ponderado que ya usa el sistema al editar/anular una factura —
     * ver FacturaCompraModel::recalcularCostoDesdeHistorial()). Botón de autoservicio, ej.
     * cuando el precio_costo quedó desincronizado por una factura mal cargada (equivalente
     * de "Recalcular desde compras" que ya existe en /materias-primas). Solo tiene sentido
     * para productos de reventa comprados directo (facturas_compra_detalle.tipo_item=
     * 'producto') — si el producto tiene una receta propia, su costo se sincroniza solo
     * desde ahí y este botón no debería usarse (la vista ya lo oculta en ese caso).
     */
    public function recalcularCosto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $producto = $this->model->findById($id);
        if (!$producto) $this->json(['ok' => false, 'error' => 'No encontrado.'], 404);

        require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
        $costoAnterior = (float)$producto['precio_costo'];
        (new FacturaCompraModel())->recalcularCostoDesdeHistorial('producto', $id);

        $actualizado = $this->model->findById($id);
        require_once BASE_PATH . '/core/AuditLog.php';
        AuditLog::log('recalcular', 'productos', sprintf(
            "Costo recalculado desde historial de compras: %s (id=%d, %s -> %s)",
            $producto['nombre'], $id, $costoAnterior, $actualizado['precio_costo']
        ));
        $this->json(['ok' => true, 'precio_costo' => $actualizado['precio_costo'], 'mensaje' => 'Costo recalculado desde el historial de compras.']);
    }

    public function getRecetas(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $recetas = $this->model->getRecetasConCosto($id);
        $total   = array_sum(array_column($recetas, 'costo'));
        $this->json(['ok' => true, 'recetas' => $recetas, 'costo_total' => $total]);
    }

    public function asignarReceta(): void
    {
        $this->requireAuth();
        Csrf::check();
        $productoId = (int)$this->input('producto_id', 0);
        $recetaId   = (int)$this->input('receta_id', 0);
        if (!$productoId || !$recetaId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        $error = $this->model->asignarReceta($productoId, $recetaId);
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);
        $recetas = $this->model->getRecetasConCosto($productoId);
        $this->json(['ok' => true, 'recetas' => $recetas, 'costo_total' => array_sum(array_column($recetas, 'costo'))]);
    }

    public function quitarReceta(): void
    {
        $this->requireAuth();
        Csrf::check();
        $productoId = (int)$this->input('producto_id', 0);
        $recetaId   = (int)$this->input('receta_id', 0);
        if (!$productoId || !$recetaId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        $this->model->quitarReceta($productoId, $recetaId);
        $recetas = $this->model->getRecetasConCosto($productoId);
        $this->json(['ok' => true, 'recetas' => $recetas, 'costo_total' => array_sum(array_column($recetas, 'costo'))]);
    }

    public function getOpcionesGrupos(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $this->json(['ok' => true, 'grupos' => $this->model->getGruposOpciones($id)]);
    }

    public function asignarOpcionesGrupo(): void
    {
        $this->requireAuth();
        Csrf::check();
        $productoId = (int)$this->input('producto_id', 0);
        $grupoId    = (int)$this->input('grupo_id', 0);
        $minimo     = max(0, (int)$this->input('minimo_selecciones', 1));
        $maximo     = max($minimo, (int)$this->input('maximo_selecciones', 1));
        $obligatorio = (bool)(int)$this->input('obligatorio', 1);
        $cantARepartirRaw = trim((string)$this->input('cantidad_a_repartir', ''));
        $cantARepartir    = $cantARepartirRaw !== '' ? max(0.0, (float)$cantARepartirRaw) : null;
        if (!$productoId || !$grupoId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        $this->model->asignarGrupoOpciones($productoId, $grupoId, $minimo, $maximo, $obligatorio, 0, $cantARepartir);
        // Si el producto ya es una promo (tiene componentes fijos), el grupo recién
        // asignado también aporta a su costo — resincronizamos. Si NO es promo (ej. un
        // helado con "Gustos" como único adicional), no tocamos precio_costo: ese campo
        // lo maneja receta/reventa, no esto.
        if ($this->model->tienePromoComponentes($productoId)) {
            $this->model->sincronizarPrecioCostoPromo($productoId);
        }
        // Los grupos viajan dentro de cada producto en GET /catalogo, así que asignar o
        // quitar uno cambia el menú online igual que editar el producto.
        MenuAppNotifier::catalogoCambio('producto', $productoId);
        $this->json(['ok' => true, 'grupos' => $this->model->getGruposOpciones($productoId)]);
    }

    public function quitarOpcionesGrupo(): void
    {
        $this->requireAuth();
        Csrf::check();
        $productoId = (int)$this->input('producto_id', 0);
        $grupoId    = (int)$this->input('grupo_id', 0);
        if (!$productoId || !$grupoId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        $this->model->quitarGrupoOpciones($productoId, $grupoId);
        if ($this->model->tienePromoComponentes($productoId)) {
            $this->model->sincronizarPrecioCostoPromo($productoId);
        }
        MenuAppNotifier::catalogoCambio('producto', $productoId);
        $this->json(['ok' => true, 'grupos' => $this->model->getGruposOpciones($productoId)]);
    }

    // ── Promos (ver docs/promos-diseno.md) ──────────────────────────────────────

    public function getPromoComponentes(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $componentes = $this->model->getPromoComponentes($id);
        $this->json(['ok' => true, 'componentes' => $componentes, 'costo_total' => $this->_costoTotalPromo($componentes)]);
    }

    /** AJAX: búsqueda de productos para agregar como componente de una promo */
    public function buscarComponentesPromo(): void
    {
        $this->requireAuth();
        $q = $this->param('q', '');
        $excluir = (int)$this->param('excluir', 0);
        if (strlen($q) < 1) $this->json([]);
        $this->json($this->model->buscarComponentesPromo($q, $excluir));
    }

    public function asignarPromoComponente(): void
    {
        $this->requireAuth();
        Csrf::check();

        $productoId   = (int)$this->input('producto_id', 0);
        $componenteId = (int)$this->input('componente_producto_id', 0);
        $cantidad     = (float)str_replace(',', '.', $this->input('cantidad', '1'));

        if (!$productoId || !$componenteId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        if ($componenteId === $productoId) $this->json(['ok' => false, 'error' => 'Un producto no puede ser componente de sí mismo.'], 422);
        if ($cantidad <= 0) $this->json(['ok' => false, 'error' => 'La cantidad debe ser mayor a cero.'], 422);

        $this->model->asignarPromoComponente($productoId, $componenteId, $cantidad);
        // Una promo no controla stock propio — se resuelve por sus componentes al vender
        // (ver VentaModel::registrar()) — así que apenas tiene el primer componente, se le
        // apaga el control de stock propio para no dejar un número sin sentido.
        $this->model->update($productoId, ['controla_stock' => 0]);

        $componentes = $this->model->getPromoComponentes($productoId);
        $this->json(['ok' => true, 'componentes' => $componentes, 'costo_total' => $this->_costoTotalPromo($componentes)]);
    }

    public function quitarPromoComponente(): void
    {
        $this->requireAuth();
        Csrf::check();

        $productoId   = (int)$this->input('producto_id', 0);
        $componenteId = (int)$this->input('componente_producto_id', 0);
        if (!$productoId || !$componenteId) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        $this->model->quitarPromoComponente($productoId, $componenteId);
        $componentes = $this->model->getPromoComponentes($productoId);
        $this->json(['ok' => true, 'componentes' => $componentes, 'costo_total' => $this->_costoTotalPromo($componentes)]);
    }

    /** Suma cantidad × precio_costo de cada componente — mismo número que ProductoModel::sincronizarPrecioCostoPromo() ya dejó persistido, solo para no pedirlo aparte. */
    private function _costoTotalPromo(array $componentes): float
    {
        return array_sum(array_map(fn($c) => (float)$c['cantidad'] * (float)$c['precio_costo'], $componentes));
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id   = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data   = $this->recogerFormulario();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $imagen = $this->subirImagen();
        if ($imagen !== null) $data['imagen'] = $imagen;

        $this->model->update($id, $data);
        // Después de guardar, nunca antes: si MenuApp está caído, el producto ya quedó
        // guardado igual y el aviso solo se registra en el log (ver MenuAppNotifier).
        MenuAppNotifier::catalogoCambio('producto', $id);
        $this->json(['ok' => true, 'mensaje' => 'Producto actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneVentas($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene ventas asociadas.'], 409);
        }

        $this->model->softDelete($id);
        MenuAppNotifier::catalogoCambio('producto', $id);
        $this->json(['ok' => true, 'mensaje' => 'Producto eliminado.']);
    }

    public function qr(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id', 0);
        $producto = $id ? $this->model->findByIdConDetalle($id) : null;
        if (!$producto) abort(404);

        // Vista standalone (sin layout)
        $pageTitle = 'QR / Código de barras: ' . $producto['nombre'];
        require BASE_PATH . '/app/Views/productos/qr.php';
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

        [$catIdx, $umedIdx] = $this->_buildLookups();

        $creados      = 0;
        $actualizados = 0;
        $errores      = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = trim($row['nombre'] ?? '');
            if ($nombre === '') {
                $errores[] = "Fila {$fila}: nombre es obligatorio.";
                continue;
            }

            try {
                $existente = $this->model->findByNombre($nombre);

                if ($existente) {
                    // Ya existe un producto con ese nombre: actualiza solo las columnas que
                    // vinieron con datos en la fila (una celda vacía significa "no tocar", no
                    // "borrar el valor actual") en vez de duplicar. stock_actual nunca se toca
                    // acá — es un valor vivo que se mueve con ventas/compras/producción, no
                    // algo que una reimportación deba resetear.
                    $data = $this->_rowToDataParaActualizar($row, $catIdx, $umedIdx);
                    if ($data) $this->model->update((int) $existente['id'], $data);
                    $actualizados++;
                } else {
                    $this->model->insert($this->_rowToData($row, $catIdx, $umedIdx));
                    $creados++;
                }
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila} ({$nombre}): " . $e->getMessage();
            }
        }

        // Un solo aviso para toda la importación (no uno por fila): el aviso no lleva los
        // datos, MenuApp vuelve a pedir el catálogo completo, así que con uno alcanza.
        if ($creados + $actualizados > 0) MenuAppNotifier::catalogoCambio('producto', 0);

        $this->json([
            'ok'           => true,
            'importados'   => $creados + $actualizados,
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'total'        => count($rows),
            'errores'      => $errores,
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

        [$catIdx, $umedIdx] = $this->_buildLookups();

        $filas   = [];
        $resumen = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($rows as $i => $row) {
            $fila     = $i + 2;
            $nombre   = trim($row['nombre'] ?? '');
            $mensajes = [];
            $estado   = 'ok';

            if ($nombre === '') {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => '(sin nombre)', 'mensaje' => 'nombre es obligatorio.'];
                $resumen['error']++;
                continue;
            }

            if ($this->model->findByNombre($nombre)) {
                $mensajes[] = 'Ya existe: se actualizará (no se duplica).';
            }

            $catNombre  = strtolower(trim($row['categoria'] ?? ''));
            if ($catNombre !== '' && !isset($catIdx[$catNombre])) {
                $mensajes[] = "Categoría '{$row['categoria']}' no encontrada (se importará sin categoría).";
                $estado     = 'warning';
            }

            $umedNombre = strtolower(trim($row['unidad_medida'] ?? ''));
            if ($umedNombre !== '' && !isset($umedIdx[$umedNombre])) {
                $mensajes[] = "Unidad '{$row['unidad_medida']}' no encontrada (se importará sin unidad).";
                $estado     = 'warning';
            }

            $tipo = strtolower(trim($row['tipo'] ?? 'reventa'));
            if (!in_array($tipo, ['elaborado', 'reventa'], true)) {
                $mensajes[] = "Tipo '{$tipo}' inválido, se usará 'reventa'.";
                if ($estado !== 'error') $estado = 'warning';
            }

            $iva = (float)str_replace(',', '.', $row['iva'] ?? '21');
            if (!in_array($iva, [0, 10.5, 21], true)) {
                $mensajes[] = "IVA '{$row['iva']}' inválido, se usará 21%.";
                if ($estado !== 'error') $estado = 'warning';
            }

            $filas[]          = ['fila' => $fila, 'estado' => $estado, 'titulo' => $nombre, 'mensaje' => implode(' ', $mensajes)];
            $resumen[$estado]++;
        }

        $this->json(['ok' => true, 'filas' => $filas, 'resumen' => $resumen, 'total' => count($filas)]);
    }

    /** Exporta el listado completo de productos (con código) a CSV */
    public function exportar(): void
    {
        $this->requireAuth();

        $rows = $this->model->findAllConDetalle();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="productos_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Formatea un número para Excel con configuración regional argentina
        // (coma decimal, sin punto de miles) para que no se lea mal al abrir el CSV.
        $n = static function (mixed $v): string {
            if ($v === null || $v === '') return '';
            $f = (float)$v;
            if ($f == floor($f)) return (string)(int)$f;
            return rtrim(rtrim(str_replace('.', ',', sprintf('%.4f', $f)), '0'), ',');
        };

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM para que Excel abra correctamente en UTF-8

        fputcsv($out, [
            'ID', 'Código', 'Nombre', 'Descripción', 'Categoría', 'Tipo',
            'Precio Venta', 'Precio Online', 'IVA', 'Unidad', 'Stock Mínimo', 'Stock Actual',
            'Menú online', 'Activo',
        ], ';');

        foreach ($rows as $p) {
            fputcsv($out, [
                $p['id'],
                $p['codigo'],
                $p['nombre'],
                $p['descripcion'],
                $p['categoria_nombre'],
                $p['tipo'],
                $n($p['precio_venta']),
                $n($p['precio_online'] ?? null),
                $n($p['iva']),
                $p['unidad'],
                $n($p['stock_minimo']),
                $n($p['stock_actual']),
                $p['ecommerce'] ? 'Si' : 'No',
                $p['activo'] ? 'Si' : 'No',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_productos.csv',
            ['nombre', 'codigo', 'descripcion', 'categoria', 'tipo', 'precio_venta', 'precio_online', 'iva', 'unidad_medida', 'stock_minimo', 'stock_actual', 'menu_online', 'activo'],
            [
                ['Torta de chocolate', 'TORTA-001', 'Torta húmeda de chocolate 1kg', 'Tortas y pasteles', 'elaborado', '2500', '2800', '21', 'Unidad', '2', '10', '1', '1'],
                ['Mini cookies x50', 'COOK-050',  'Bolsa de 50 mini cookies',        'Cookies',           'elaborado', '800',  '',     '21', 'Bolsa', '5', '20', '1', '1'],
                ['Papel madera x100','PAPEL-100', 'Papel kraft para packaging',       'Insumos',           'reventa',   '350',  '',     '0',  'Resma', '1', '5',  '0', '1'],
            ]
        );
    }

    private function _buildLookups(): array
    {
        $db = Database::getInstance();

        $cats = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
        $catIdx = [];
        foreach ($cats as $c) { $catIdx[strtolower($c['nombre'])] = $c['id']; }

        $umeds = $db->query("SELECT id, nombre, abreviatura FROM unidades_medida")->fetchAll(\PDO::FETCH_ASSOC);
        $umedIdx = [];
        foreach ($umeds as $u) {
            $umedIdx[strtolower($u['nombre'])]       = $u['id'];
            if ($u['abreviatura']) $umedIdx[strtolower($u['abreviatura'])] = $u['id'];
        }

        return [$catIdx, $umedIdx];
    }

    private function _rowToData(array $row, array $catIdx, array $umedIdx): array
    {
        $catNombre  = strtolower(trim($row['categoria'] ?? ''));
        $umedNombre = strtolower(trim($row['unidad_medida'] ?? ''));

        $tipo = strtolower(trim($row['tipo'] ?? 'reventa'));
        if (!in_array($tipo, ['elaborado', 'reventa'], true)) $tipo = 'reventa';

        $iva = (float)str_replace(',', '.', $row['iva'] ?? '21');
        if (!in_array($iva, [0, 10.5, 21], true)) $iva = 21.0;

        $activoRaw = strtolower(trim($row['activo'] ?? '1'));
        $activo    = in_array($activoRaw, ['0', 'false', 'no', 'inactivo'], true) ? 0 : 1;

        $ecommerce = self::_menuOnlineDeFila($row) ?? 0;

        $precioOnlineRaw = trim((string)($row['precio_online'] ?? ''));

        return [
            'nombre'          => trim($row['nombre'] ?? ''),
            'codigo'          => ImportHelper::nullIfEmpty($row['codigo'] ?? ''),
            'descripcion'     => ImportHelper::nullIfEmpty($row['descripcion'] ?? ''),
            'categoria_id'    => $catNombre !== '' ? ($catIdx[$catNombre] ?? null) : null,
            'tipo'            => $tipo,
            'precio_venta'    => (float)str_replace(',', '.', $row['precio_venta'] ?? '0'),
            'precio_online'   => $precioOnlineRaw === '' ? null : (float)str_replace(',', '.', $precioOnlineRaw),
            'iva'             => $iva,
            'unidad_medida_id'=> $umedNombre !== '' ? ($umedIdx[$umedNombre] ?? null) : null,
            'stock_minimo'    => (float)str_replace(',', '.', $row['stock_minimo'] ?? '0'),
            'stock_actual'    => (float)str_replace(',', '.', $row['stock_actual'] ?? '0'),
            'ecommerce'       => $ecommerce,
            'activo'          => $activo,
            'eliminado'       => 0,
        ];
    }

    /**
     * Mismos datos que _rowToData(), pero solo incluye una columna si la celda vino con
     * contenido — para actualizar un producto existente sin pisarle campos con los
     * valores por defecto de una fila que los dejó en blanco a propósito.
     */
    private function _rowToDataParaActualizar(array $row, array $catIdx, array $umedIdx): array
    {
        $data = [];

        if (trim($row['codigo'] ?? '') !== '')      $data['codigo']      = trim($row['codigo']);
        if (trim($row['descripcion'] ?? '') !== '') $data['descripcion'] = trim($row['descripcion']);

        $catNombre = strtolower(trim($row['categoria'] ?? ''));
        if ($catNombre !== '' && isset($catIdx[$catNombre])) $data['categoria_id'] = $catIdx[$catNombre];

        $tipoRaw = strtolower(trim($row['tipo'] ?? ''));
        if (in_array($tipoRaw, ['elaborado', 'reventa'], true)) $data['tipo'] = $tipoRaw;

        if (trim($row['precio_venta'] ?? '') !== '') $data['precio_venta'] = (float)str_replace(',', '.', $row['precio_venta']);

        // "0" o un guión vacían el precio online (vuelve a usar el del local); una celda en
        // blanco significa "no tocar", como el resto de las columnas de este método.
        $precioOnlineRaw = trim((string)($row['precio_online'] ?? ''));
        if ($precioOnlineRaw !== '') {
            $data['precio_online'] = in_array($precioOnlineRaw, ['0', '-'], true)
                ? null
                : (float)str_replace(',', '.', $precioOnlineRaw);
        }

        if (trim($row['iva'] ?? '') !== '') {
            $iva = (float)str_replace(',', '.', $row['iva']);
            if (in_array($iva, [0, 10.5, 21], true)) $data['iva'] = $iva;
        }

        $umedNombre = strtolower(trim($row['unidad_medida'] ?? ''));
        if ($umedNombre !== '' && isset($umedIdx[$umedNombre])) $data['unidad_medida_id'] = $umedIdx[$umedNombre];

        if (trim($row['stock_minimo'] ?? '') !== '') $data['stock_minimo'] = (float)str_replace(',', '.', $row['stock_minimo']);

        $menuOnline = self::_menuOnlineDeFila($row);
        if ($menuOnline !== null) $data['ecommerce'] = $menuOnline;

        if (trim($row['activo'] ?? '') !== '') {
            $activoRaw = strtolower(trim($row['activo']));
            $data['activo'] = in_array($activoRaw, ['0', 'false', 'no', 'inactivo'], true) ? 0 : 1;
        }

        return $data;
    }

    /**
     * Columna "mostrar en el menú online" de una fila importada. Se llama `menu_online` en
     * la plantilla nueva (ver decisión D2 de docs/integracion-menuapp-checklist.md), pero
     * se sigue aceptando el nombre viejo `ecommerce` para no invalidar los archivos que el
     * comercio ya tenía armados. Devuelve null si la fila no trae ninguna de las dos
     * columnas con contenido, que significa "no tocar".
     */
    private static function _menuOnlineDeFila(array $row): ?int
    {
        foreach (['menu_online', 'ecommerce'] as $col) {
            $raw = trim((string)($row[$col] ?? ''));
            if ($raw === '') continue;
            return in_array(strtolower($raw), ['1', 'true', 'si', 'sí', 'yes'], true) ? 1 : 0;
        }
        return null;
    }

    private function recogerFormulario(): array
    {
        $tiposGasto = ['costo_variable', 'costo_fijo', 'impuesto', 'gasto'];
        $tipoGasto  = $this->input('tipo_gasto', 'costo_variable');
        return [
            'nombre'          => $this->input('nombre', ''),
            'codigo'          => $this->input('codigo', '') ?: null,
            'descripcion'     => $this->input('descripcion', '') ?: null,
            'categoria_id'    => (int)($this->input('categoria_id', 0)) ?: null,
            'tipo_gasto'      => in_array($tipoGasto, $tiposGasto) ? $tipoGasto : 'costo_variable',
            'tipo'            => $this->input('tipo', 'reventa'),
            'precio_venta'    => (float)$this->input('precio_venta', 0),
            // NULL (no 0) cuando viene vacío: NULL significa "usar el precio del local" en
            // el menú online, y un 0 sería un producto gratis — ver PrecioHelper::precioBaseOnline().
            'precio_online'   => $this->input('precio_online', '') === '' ? null : (float)$this->input('precio_online', 0),
            'iva'             => (float)$this->input('iva', 21),
            'unidad_medida_id'=> (int)($this->input('unidad_medida_id', 0)) ?: null,
            'stock_minimo'    => (float)$this->input('stock_minimo', 0),
            'controla_stock'  => (int)$this->input('controla_stock', 1),
            'desvio_pct'      => max(0.0, (float)$this->input('desvio_pct', 0)), // pérdida: nunca puede abaratar el costo
            'ecommerce'       => (int)$this->input('ecommerce', 0),
            'activo'          => (int)$this->input('activo', 1),
            'eliminado'       => 0,
        ];
    }

    private function validar(array $data): array
    {
        $errores = [];
        if (empty($data['nombre'])) $errores[] = 'El nombre es obligatorio.';
        if ($data['precio_venta'] < 0) $errores[] = 'El precio de venta no puede ser negativo.';
        if ($data['precio_online'] !== null && $data['precio_online'] < 0) $errores[] = 'El precio online no puede ser negativo.';
        if (!in_array($data['tipo'], ['elaborado', 'reventa'])) $errores[] = 'Tipo de producto inválido.';
        if (!in_array($data['iva'], [0, 10.5, 21])) $errores[] = 'Alícuota de IVA inválida.';
        return $errores;
    }

    private function subirImagen(): ?string
    {
        if (empty($_FILES['imagen']['tmp_name'])) return null;

        $file = $_FILES['imagen'];
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > MAX_FILE_SIZE) return null;
        if (!in_array($file['type'], ALLOWED_IMAGES)) return null;

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nombre   = uniqid('prod_', true) . '.' . strtolower($ext);
        $destino  = UPLOAD_PATH . '/productos/' . $nombre;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return $nombre;
        }
        return null;
    }
}
