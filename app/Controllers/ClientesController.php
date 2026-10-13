<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ClienteModel.php';
require_once BASE_PATH . '/app/Models/PortalConfigModel.php';
require_once BASE_PATH . '/app/Models/ClienteCatalogoModel.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';

class ClientesController extends Controller
{
    private ClienteModel $model;
    private PortalConfigModel $portalConfigModel;

    public function __construct()
    {
        $this->model             = new ClienteModel();
        $this->portalConfigModel = new PortalConfigModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('clientes.index', [
            'pageTitle'    => 'Clientes',
            'breadcrumb'   => ['Maestros' => null, 'Clientes' => null],
            'clientes'     => $this->model->listar(),
            'esAdmin'      => (Session::user()['rol'] ?? '') === 'admin',
            'portalConfig' => $this->portalConfigModel->getConfig(),
        ]);
    }

    /**
     * Vista previa (solo lectura, para staff) de lo que ESTE cliente ve ahora mismo en
     * /portal — mismo catálogo (con su precio especial ya resuelto) y mismo criterio de
     * disponibilidad/stock que /portal, para poder auditar que no vea nada erróneo sin
     * tener que loguearse como el cliente (no existe esa opción — el portal usa una sesión
     * `auth_cliente` completamente aparte de la de staff).
     */
    public function vistaPortal(): void
    {
        $this->requireAuth();

        $id      = (int)$this->param('id', 0);
        $cliente = $id ? $this->model->findById($id) : null;
        if (!$cliente) abort(404);

        $this->render('clientes.vista-portal', [
            'pageTitle'     => 'Vista previa del portal — ' . $cliente['nombre'],
            'breadcrumb'    => ['Maestros' => null, 'Clientes' => 'clientes', 'Vista previa del portal' => null],
            'cliente'       => $cliente,
            'catalogo'      => (new ClienteCatalogoModel())->catalogoDeCliente($id),
            'criterioStock' => $this->portalConfigModel->getConfig()['criterio_stock'],
            'negocioAbierto'=> (new CajaModel())->hayTurnoAbierto(),
        ]);
    }

    /**
     * AJAX de la vista previa: mismos grupos/opciones (gustos) y mismo cálculo de sin_stock
     * que PortalController::opcionesProducto() — duplicado acá (no depende de ningún dato
     * del cliente, solo del producto) porque ese endpoint exige sesión `auth_cliente`, que
     * staff no tiene.
     */
    public function opcionesProductoPreview(): void
    {
        $this->requireAuth();
        $productoId = (int) $this->param('producto_id', 0);
        if (!$productoId) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        require_once BASE_PATH . '/app/Models/GrupoOpcionModel.php';
        $prodModel  = new ProductoModel();
        $grupoModel = new GrupoOpcionModel();

        $criterioStock = $this->portalConfigModel->getConfig()['criterio_stock'];

        $grupos = $prodModel->getGruposOpciones($productoId);
        foreach ($grupos as &$g) {
            $conOpciones   = $grupoModel->findByIdConOpciones((int) $g['id']);
            $g['opciones'] = array_map(function ($o) use ($criterioStock) {
                $sinStock = false;
                if (!empty($o['materia_prima_id'])) {
                    $sinStock = $criterioStock === 'stock_cero'
                        ? (float) $o['mp_stock_actual'] <= 0
                        : (float) $o['mp_stock_actual'] <= (float) $o['mp_stock_minimo'];
                } elseif (!empty($o['insumo_id'])) {
                    $sinStock = $criterioStock === 'stock_cero'
                        ? (float) $o['ins_stock_actual'] <= 0
                        : (float) $o['ins_stock_actual'] <= (float) $o['ins_stock_minimo'];
                }
                return ['id' => (int) $o['id'], 'nombre' => $o['nombre'], 'sin_stock' => $sinStock];
            }, $conOpciones['opciones'] ?? []);
        }
        unset($g);

        $this->json(['ok' => true, 'grupos' => $grupos]);
    }

    /** Admin-only: criterio de stock para disponibilidad de productos/gustos en el Portal de Clientes Mayoristas (ver PortalConfigModel). */
    public function guardarConfigPortal(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $criterio = $this->input('criterio_stock', '');
        if (!in_array($criterio, ['stock_minimo', 'stock_cero'], true)) {
            $this->json(['ok' => false, 'error' => 'Criterio inválido.'], 422);
        }

        $this->portalConfigModel->setConfig('criterio_stock', $criterio);
        $this->json(['ok' => true, 'mensaje' => 'Configuración del portal actualizada.']);
    }

    public function nuevo(): void
    {
        $this->requireAuth();

        $this->render('clientes.form', [
            'pageTitle'  => 'Nuevo cliente',
            'breadcrumb' => ['Maestros' => null, 'Clientes' => 'clientes', 'Nuevo' => null],
            'cliente'    => null,
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        [$data, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Cliente creado correctamente.']);
    }

    public function editar(): void
    {
        $this->requireAuth();

        $id      = (int)$this->param('id');
        $cliente = $this->model->findById($id);
        if (!$cliente || $cliente['eliminado']) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('clientes.form', [
            'pageTitle'  => 'Editar cliente',
            'breadcrumb' => ['Maestros' => null, 'Clientes' => 'clientes', 'Editar' => null],
            'cliente'    => $cliente,
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$data, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $this->model->update($id, $data);

        // Acceso al portal de clientes mayoristas (ver docs/portal-clientes-mayoristas-checklist.md)
        // — el admin lo activa/edita desde acá, aparte de los datos generales del cliente.
        $portalActivo = (int)$this->input('portal_activo', 0);
        if ($portalActivo) {
            if (empty($data['email'])) {
                $this->json(['ok' => false, 'error' => 'Para activar el acceso al portal, el cliente necesita un email cargado.'], 422);
            }
            $passwordPlano = trim($this->input('portal_password', ''));
        } else {
            $passwordPlano = null;
        }
        $this->model->guardarAccesoPortal($id, (bool)$portalActivo, $passwordPlano ?: null);

        $this->json(['ok' => true, 'mensaje' => 'Cliente actualizado correctamente.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneVentas($id)) {
            $this->json(['ok' => false, 'error' => 'El cliente tiene ventas registradas y no puede eliminarse.'], 409);
        }

        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Cliente eliminado.']);
    }

    public function buscar(): void
    {
        $this->requireAuth();

        $q       = trim($this->param('q') ?? '');
        $results = $q ? $this->model->buscar($q) : [];
        $this->json(['ok' => true, 'items' => $results]);
    }

    /** Saldo pendiente de cuenta corriente + tope del cliente — usado por el POS al elegir un cliente. */
    public function saldoCuentaCorriente(): void
    {
        $this->requireAuth();

        $id      = (int) ($this->param('id') ?? 0);
        $cliente = $id ? $this->model->findById($id) : null;
        if (!$cliente) {
            $this->json(['ok' => false, 'error' => 'Cliente no encontrado.'], 404);
        }

        require_once BASE_PATH . '/app/Models/CobroClienteModel.php';
        $saldo  = (new CobroClienteModel())->getSaldoPendienteCliente($id);
        $limite = $cliente['limite_cuenta_corriente'] ?? null;
        $tope   = $limite !== null ? (float) $limite : CobroClienteModel::TOPE_DEFAULT;

        $this->json(['ok' => true, 'saldo' => $saldo, 'tope' => $tope]);
    }

    // ── Catálogo del Portal de Clientes Mayoristas ──────────────────────────
    // Ver docs/portal-clientes-mayoristas-checklist.md — qué productos ve este cliente y a qué precio.

    public function catalogo(): void
    {
        $this->requireAuth();
        $clienteId = (int) ($this->param('cliente_id') ?? 0);
        require_once BASE_PATH . '/app/Models/ClienteCatalogoModel.php';
        $this->json(['ok' => true, 'items' => $clienteId ? (new ClienteCatalogoModel())->listarParaAdmin($clienteId) : []]);
    }

    public function catalogoAsignar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $clienteId  = (int) $this->input('cliente_id', 0);
        $productoId = (int) $this->input('producto_id', 0);
        $precioRaw  = trim($this->input('precio_especial', ''));
        if (!$clienteId || !$productoId) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        require_once BASE_PATH . '/app/Models/ClienteCatalogoModel.php';
        (new ClienteCatalogoModel())->asignar($clienteId, $productoId, $precioRaw !== '' ? (float) $precioRaw : null);
        $this->json(['ok' => true, 'mensaje' => 'Producto agregado al catálogo del cliente.']);
    }

    public function catalogoQuitar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $clienteId  = (int) $this->input('cliente_id', 0);
        $productoId = (int) $this->input('producto_id', 0);
        if (!$clienteId || !$productoId) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        require_once BASE_PATH . '/app/Models/ClienteCatalogoModel.php';
        (new ClienteCatalogoModel())->quitar($clienteId, $productoId);
        $this->json(['ok' => true, 'mensaje' => 'Producto quitado del catálogo del cliente.']);
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

        $condicionesIva = ['responsable_inscripto', 'monotributista', 'exento', 'consumidor_final'];
        $tiposDoc       = ['dni', 'cuit'];
        $importados     = 0;
        $errores        = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');
            if (!$nombre) { $errores[] = "Fila {$fila}: 'nombre' es obligatorio."; continue; }

            $email   = filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $condIva = strtolower(trim($row['condicion_iva'] ?? 'consumidor_final'));
            if (!in_array($condIva, $condicionesIva, true)) { $condIva = 'consumidor_final'; }
            $tipoDoc = strtolower(trim($row['tipo_doc'] ?? 'dni'));
            if (!in_array($tipoDoc, $tiposDoc, true)) { $tipoDoc = 'dni'; }

            try {
                $this->model->insert([
                    'nombre'        => $nombre,
                    'documento'     => ImportHelper::nullIfEmpty($row['documento'] ?? ''),
                    'tipo_doc'      => $tipoDoc,
                    'condicion_iva' => $condIva,
                    'email'         => $email ?: null,
                    'telefono'      => ImportHelper::nullIfEmpty($row['telefono'] ?? ''),
                    'direccion'     => ImportHelper::nullIfEmpty($row['direccion'] ?? ''),
                    'activo'        => 1,
                    'eliminado'     => 0,
                ]);
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
            }
        }

        $this->json(['ok' => true, 'importados' => $importados, 'total' => count($rows), 'errores' => $errores]);
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_clientes.csv',
            ['nombre', 'documento', 'tipo_doc', 'condicion_iva', 'email', 'telefono', 'direccion'],
            [
                ['Juan Pérez', '30123456', 'dni', 'consumidor_final', 'juan@email.com', '011-5555-1234', 'Av. Rivadavia 1000, CABA'],
                ['Empresa XYZ SA', '30-12345678-9', 'cuit', 'responsable_inscripto', 'admin@xyz.com', '', ''],
            ]
        );
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre      = trim($this->input('nombre', ''));
        $documento   = trim($this->input('documento', ''));
        $tipoDoc     = $this->input('tipo_doc', 'dni');
        $condIva     = $this->input('condicion_iva', 'consumidor_final');
        $telefono    = trim($this->input('telefono', ''));
        $email       = trim($this->input('email', ''));
        $direccion   = trim($this->input('direccion', ''));
        $activo      = (int)$this->input('activo', 1);
        $limiteCta   = trim($this->input('limite_cuenta_corriente', ''));

        if (!$nombre) $errores[] = 'El nombre es obligatorio.';
        if (strlen($nombre) > 150) $errores[] = 'El nombre no puede superar 150 caracteres.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';

        $condicionesValidas = ['responsable_inscripto', 'monotributista', 'exento', 'consumidor_final'];
        if (!in_array($condIva, $condicionesValidas, true)) $condIva = 'consumidor_final';

        $tiposDoc = ['dni', 'cuit'];
        if (!in_array($tipoDoc, $tiposDoc, true)) $tipoDoc = 'dni';

        $data = [
            'nombre'       => $nombre,
            'documento'    => $documento ?: null,
            'tipo_doc'     => $tipoDoc,
            'condicion_iva'=> $condIva,
            'telefono'     => $telefono ?: null,
            'email'        => $email ?: null,
            'direccion'    => $direccion ?: null,
            'activo'       => $activo,
            'eliminado'    => 0,
            'limite_cuenta_corriente' => $limiteCta !== '' ? (float)$limiteCta : null,
        ];

        return [$data, $errores];
    }
}
