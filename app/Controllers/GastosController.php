<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GastoModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class GastosController extends Controller
{
    private GastoModel $model;

    public function __construct()
    {
        $this->model = new GastoModel();
    }

    // ── Listado principal ─────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();

        $desde  = $this->param('desde') ?: date('Y-m-01');
        $hasta  = $this->param('hasta') ?: date('Y-m-d');
        $tipo   = $this->param('tipo', '');
        $catId  = (int)$this->param('categoria_id', 0);
        $provId = (int)$this->param('proveedor_id', 0);

        $filtros = ['desde' => $desde, 'hasta' => $hasta];
        if ($tipo)   $filtros['tipo']         = $tipo;
        if ($catId)  $filtros['categoria_id'] = $catId;
        if ($provId) $filtros['proveedor_id'] = $provId;

        $this->render('gastos.index', [
            'pageTitle'   => 'Gastos',
            'breadcrumb'  => ['Finanzas' => null, 'Gastos' => null],
            'gastos'      => $this->model->findAllFiltrado($filtros),
            'catPlanas'   => $this->model->findCategoriasPlanas(),
            'resumen'     => $this->model->estadoResultados($desde, $hasta),
            'proveedores' => (new ProveedorModel())->findAllActivos(),
            'filtros'     => ['desde' => $desde, 'hasta' => $hasta, 'tipo' => $tipo, 'categoria_id' => $catId, 'proveedor_id' => $provId],
        ]);
    }

    // ── CRUD gastos ───────────────────────────────────────────────────────────

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $fecha            = $this->input('fecha', date('Y-m-d'));
        $fechaVencimiento = $this->input('fecha_vencimiento', '') ?: null;
        $descripcion      = trim($this->input('descripcion', ''));
        $monto            = (float)str_replace(',', '.', $this->input('monto', '0'));
        $categoria_id     = (int)$this->input('categoria_id', 0);
        $proveedorId      = (int)$this->input('proveedor_id', 0) ?: null;
        $notas            = trim($this->input('notas', ''));

        if (empty($descripcion) || $monto <= 0 || !$categoria_id) {
            $this->json(['ok' => false, 'error' => 'Completá descripción, monto y categoría.'], 422);
        }

        $id = $this->model->insert([
            'fecha'               => $fecha,
            'fecha_vencimiento'   => $fechaVencimiento,
            'descripcion'         => $descripcion,
            'monto'               => $monto,
            'categoria_id'        => $categoria_id,
            'proveedor_id'        => $proveedorId,
            'usuario_id'          => Session::user()['id'],
            'notas'               => $notas ?: null,
            'comprobante_archivo' => $this->_subirComprobante(),
            // Con proveedor asignado, el gasto queda pendiente de pago (se liquida desde
            // Pagos a Proveedores); sin proveedor, se considera ya saldado.
            'estado'              => $proveedorId ? 'pendiente' : 'pagado',
            'eliminado'           => 0,
        ]);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Gasto registrado correctamente.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id               = (int)$this->input('id', 0);
        $fecha            = $this->input('fecha', date('Y-m-d'));
        $fechaVencimiento = $this->input('fecha_vencimiento', '') ?: null;
        $descripcion      = trim($this->input('descripcion', ''));
        $monto            = (float)str_replace(',', '.', $this->input('monto', '0'));
        $categoria_id     = (int)$this->input('categoria_id', 0);
        $proveedorId      = (int)$this->input('proveedor_id', 0) ?: null;
        $notas            = trim($this->input('notas', ''));

        if (!$id || empty($descripcion) || $monto <= 0 || !$categoria_id) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $data = [
            'fecha'             => $fecha,
            'fecha_vencimiento' => $fechaVencimiento,
            'descripcion'       => $descripcion,
            'monto'             => $monto,
            'categoria_id'      => $categoria_id,
            'proveedor_id'      => $proveedorId,
            'notas'             => $notas ?: null,
        ];

        $comprobante = $this->_subirComprobante();
        if ($comprobante) {
            $actual = $this->model->findByIdConProveedor($id);
            if ($actual && $actual['comprobante_archivo']) {
                $old = BASE_PATH . '/storage/gastos/' . basename($actual['comprobante_archivo']);
                if (file_exists($old)) @unlink($old);
            }
            $data['comprobante_archivo'] = $comprobante;
        }

        $this->model->updateGasto($id, $data);

        $this->json(['ok' => true, 'mensaje' => 'Gasto actualizado correctamente.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->deleteGasto($id);
        $this->json(['ok' => true, 'mensaje' => 'Gasto eliminado.']);
    }

    /** GET: sirve el comprobante adjunto de un gasto (requiere sesión) */
    public function verComprobante(): void
    {
        $this->requireAuth();
        $id    = (int)$this->param('id', 0);
        $gasto = $id ? $this->model->findByIdConProveedor($id) : null;

        if (!$gasto || !$gasto['comprobante_archivo']) { http_response_code(404); exit; }

        $path = BASE_PATH . '/storage/gastos/' . basename($gasto['comprobante_archivo']);
        if (!file_exists($path)) { http_response_code(404); exit; }

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="comprobante_gasto_' . $id . '.' . $ext . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Mueve el archivo subido en $_FILES['comprobante_archivo'] a storage/gastos/ */
    private function _subirComprobante(): ?string
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

        $dir = BASE_PATH . '/storage/gastos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            default           => 'jpg',
        };

        $name = 'gasto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        return move_uploaded_file($file['tmp_name'], $dir . $name) ? $name : null;
    }

    // ── ABM categorías ────────────────────────────────────────────────────────

    public function categorias(): void
    {
        $this->requireAuth();

        $this->render('gastos.categorias', [
            'pageTitle'  => 'Categorías de Gastos',
            'breadcrumb' => ['Finanzas' => null, 'Gastos' => url('gastos'), 'Categorías' => null],
            'categorias' => $this->model->findCategorias(),
        ]);
    }

    public function crearCategoria(): void
    {
        $this->requireAuth();
        Csrf::check();

        $nombre    = trim($this->input('nombre', ''));
        $tipo      = $this->input('tipo', 'costo_fijo');
        $parent_id = (int)($this->input('parent_id', 0) ?: 0) ?: null;
        $color     = $this->input('color', '#6c757d');

        if (empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        }

        $tiposValidos = ['costo_fijo', 'costo_variable', 'impuesto'];
        if (!in_array($tipo, $tiposValidos, true)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }

        $id = $this->model->insertCategoria([
            'nombre'    => $nombre,
            'tipo'      => $tipo,
            'parent_id' => $parent_id,
            'color'     => $color,
            'orden'     => 0,
            'eliminado' => 0,
        ]);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Categoría creada.']);
    }

    public function actualizarCategoria(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id        = (int)$this->input('id', 0);
        $nombre    = trim($this->input('nombre', ''));
        $tipo      = $this->input('tipo', 'costo_fijo');
        $parent_id = (int)($this->input('parent_id', 0) ?: 0) ?: null;
        $color     = $this->input('color', '#6c757d');

        if (!$id || empty($nombre)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        if ($parent_id === $id) {
            $this->json(['ok' => false, 'error' => 'Una categoría no puede ser su propio padre.'], 422);
        }

        $this->model->updateCategoria($id, [
            'nombre'    => $nombre,
            'tipo'      => $tipo,
            'parent_id' => $parent_id,
            'color'     => $color,
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Categoría actualizada.']);
    }

    public function eliminarCategoria(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->categoriaConHijos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene subcategorías asociadas.'], 409);
        }

        if ($this->model->categoriaConGastos($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene gastos registrados.'], 409);
        }

        $this->model->deleteCategoria($id);
        $this->json(['ok' => true, 'mensaje' => 'Categoría eliminada.']);
    }
}
