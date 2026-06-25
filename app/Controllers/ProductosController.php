<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ProductoModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';

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
            'pageTitle'  => 'Productos',
            'breadcrumb' => ['Maestros' => null, 'Productos' => null],
            'productos'  => $this->model->findAllConDetalle(),
            'categorias' => $this->catModel->findAllConPadre('producto'),
            'unidades'   => $this->unidades(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('productos.form', [
            'pageTitle'  => 'Nuevo producto',
            'breadcrumb' => ['Maestros' => null, 'Productos' => 'productos', 'Nuevo' => null],
            'producto'   => null,
            'categorias' => $this->catModel->findAllConPadre('producto'),
            'unidades'   => $this->unidades(),
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
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Producto creado correctamente.']);
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id      = (int)$this->param('id', 0);
        $producto = $this->model->findByIdConDetalle($id);
        if (!$producto) abort(404);

        $this->render('productos.form', [
            'pageTitle'  => 'Editar producto',
            'breadcrumb' => ['Maestros' => null, 'Productos' => 'productos', 'Editar' => null],
            'producto'   => $producto,
            'categorias' => $this->catModel->findAllConPadre('producto'),
            'unidades'   => $this->unidades(),
        ]);
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

    private function recogerFormulario(): array
    {
        return [
            'nombre'          => $this->input('nombre', ''),
            'codigo'          => $this->input('codigo', '') ?: null,
            'descripcion'     => $this->input('descripcion', '') ?: null,
            'categoria_id'    => (int)($this->input('categoria_id', 0)) ?: null,
            'tipo'            => $this->input('tipo', 'reventa'),
            'precio_venta'    => (float)$this->input('precio_venta', 0),
            'precio_costo'    => (float)$this->input('precio_costo', 0),
            'iva'             => (float)$this->input('iva', 21),
            'unidad_medida_id'=> (int)($this->input('unidad_medida_id', 0)) ?: null,
            'stock_minimo'    => (float)$this->input('stock_minimo', 0),
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
