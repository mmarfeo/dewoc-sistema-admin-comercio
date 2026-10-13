<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class ProveedoresController extends Controller
{
    private ProveedorModel $model;

    public function __construct()
    {
        $this->model = new ProveedorModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render('proveedores.index', [
            'pageTitle'   => 'Proveedores',
            'breadcrumb'  => ['Compras' => null, 'Proveedores' => null],
            'proveedores' => $this->model->findAllActivos(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->render('proveedores.form', [
            'pageTitle'  => 'Nuevo proveedor',
            'breadcrumb' => ['Compras' => null, 'Proveedores' => 'proveedores', 'Nuevo' => null],
            'proveedor'  => null,
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data    = $this->recogerForm();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $id = $this->model->insert($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Proveedor creado.']);
    }

    public function editar(): void
    {
        $this->requireAuth();
        $id        = (int)$this->param('id', 0);
        $proveedor = $this->model->findById($id);
        if (!$proveedor) abort(404);

        $this->render('proveedores.form', [
            'pageTitle'  => 'Editar proveedor',
            'breadcrumb' => ['Compras' => null, 'Proveedores' => 'proveedores', 'Editar' => null],
            'proveedor'  => $proveedor,
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data    = $this->recogerForm();
        $errores = $this->validar($data);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Proveedor actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if ($this->model->tieneCompras($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene facturas de compra asociadas.'], 409);
        }
        $this->model->softDelete($id);
        $this->json(['ok' => true, 'mensaje' => 'Proveedor eliminado.']);
    }

    public function toggleActivo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $proveedor = $this->model->findById($id);
        if (!$proveedor) $this->json(['ok' => false, 'error' => 'Proveedor no encontrado.'], 404);

        $nuevoEstado = $proveedor['activo'] ? 0 : 1;
        $this->model->update($id, ['activo' => $nuevoEstado]);
        $this->json([
            'ok'      => true,
            'activo'  => $nuevoEstado,
            'mensaje' => $nuevoEstado ? 'Proveedor activado.' : 'Proveedor desactivado.',
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

        $filas   = [];
        $resumen = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($rows as $i => $row) {
            $fila        = $i + 2;
            $razonSocial = ImportHelper::nullIfEmpty($row['razon_social'] ?? '');

            if (!$razonSocial) {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => '(sin razón social)', 'mensaje' => "Campo 'razon_social' obligatorio."];
                $resumen['error']++;
                continue;
            }

            $cuit = ImportHelper::nullIfEmpty($row['cuit'] ?? '');
            if ($cuit && !$this->model->validarCuit($cuit)) {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => $razonSocial, 'mensaje' => "CUIT '{$cuit}' inválido (debe tener 11 dígitos)."];
                $resumen['error']++;
                continue;
            }

            $email = ImportHelper::nullIfEmpty($row['email'] ?? '');
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $filas[] = ['fila' => $fila, 'estado' => 'error', 'titulo' => $razonSocial, 'mensaje' => "Email '{$email}' no tiene formato válido."];
                $resumen['error']++;
                continue;
            }

            $warns = [];
            if (!$cuit)  $warns[] = 'Sin CUIT (recomendado para facturar)';

            if ($warns) {
                $filas[] = ['fila' => $fila, 'estado' => 'warning', 'titulo' => $razonSocial, 'mensaje' => implode('; ', $warns)];
                $resumen['warning']++;
            } else {
                $filas[] = ['fila' => $fila, 'estado' => 'ok', 'titulo' => $razonSocial, 'mensaje' => null];
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

        $importados = 0;
        $errores    = [];

        $condicionesIva = ['responsable_inscripto', 'monotributista', 'exento', 'consumidor_final'];

        foreach ($rows as $i => $row) {
            $fila = $i + 2;
            $razonSocial = ImportHelper::nullIfEmpty($row['razon_social'] ?? '');
            if (!$razonSocial) { $errores[] = "Fila {$fila}: 'razon_social' es obligatorio."; continue; }

            $cuit = ImportHelper::nullIfEmpty($row['cuit'] ?? '');
            if ($cuit && !$this->model->validarCuit($cuit)) {
                $errores[] = "Fila {$fila}: CUIT '{$cuit}' inválido (debe tener 11 dígitos)."; continue;
            }
            if ($cuit) $cuit = $this->model->normalizarCuit($cuit);

            // Soporta tanto la columna "cbu/alias" (plantilla actual) como "cbu" (plantillas antiguas)
            $cbu = ImportHelper::nullIfEmpty($row['cbu/alias'] ?? $row['cbu'] ?? '');

            $condIva = strtolower(trim($row['condicion_iva'] ?? ''));
            if (!in_array($condIva, $condicionesIva, true)) { $condIva = 'responsable_inscripto'; }

            $estadoRaw = strtolower(trim($row['estado'] ?? 'activo'));
            $activo    = in_array($estadoRaw, ['1', 'activo', 'si', 'yes', 'true'], true) ? 1 : 0;

            try {
                $this->model->insert([
                    'razon_social'  => $razonSocial,
                    'cuit'          => $cuit,
                    'condicion_iva' => $condIva,
                    'email'         => filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                    'telefono'      => ImportHelper::nullIfEmpty($row['telefono'] ?? ''),
                    'direccion'     => ImportHelper::nullIfEmpty($row['direccion'] ?? ''),
                    'banco'         => ImportHelper::nullIfEmpty($row['banco'] ?? ''),
                    'cbu'           => $cbu,
                    'rubro'         => ImportHelper::nullIfEmpty($row['rubro'] ?? ''),
                    'activo'        => $activo,
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
        ImportHelper::sendCsvTemplate('plantilla_proveedores.csv',
            ['razon_social', 'cuit', 'condicion_iva', 'email', 'telefono', 'direccion', 'banco', 'cbu/alias', 'rubro', 'estado'],
            [
                ['Proveedor Ejemplo SA', '30-12345678-9', 'responsable_inscripto', 'ventas@proveedor.com', '011-4444-5555', 'Av. Corrientes 1234, CABA', 'Banco Nación', '0110999900001234567890', 'Alimentos', 'activo'],
                ['Distribuidora Norte SRL', '20-98765432-1', 'monotributista', '', '0341-555-1234', 'San Martín 456, Rosario', '', 'mi.proveedor.mp', 'Bebidas', 'activo'],
            ]
        );
    }

    // ── Identidades AJAX ──────────────────────────────────────────────────────────

    public function identidades(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        $this->json($this->model->getIdentidades($id));
    }

    public function agregarIdentidad(): void
    {
        $this->requireAuth();
        Csrf::check();
        $proveedorId = (int)$this->input('proveedor_id', 0);
        $tipo        = $this->input('tipo', 'otro');
        $valor       = trim($this->input('valor', ''));
        $descripcion = $this->input('descripcion', '') ?: null;

        if (!$proveedorId || $valor === '') {
            $this->json(['ok' => false, 'error' => 'Datos incompletos.'], 422);
        }

        $tipos = ['cbu', 'alias_mp', 'nombre', 'cuit', 'otro'];
        if (!in_array($tipo, $tipos, true)) $tipo = 'otro';

        $id = $this->model->agregarIdentidad($proveedorId, $tipo, $valor, $descripcion);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Identidad agregada.']);
    }

    public function eliminarIdentidad(): void
    {
        $this->requireAuth();
        Csrf::check();
        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $this->model->eliminarIdentidad($id);
        $this->json(['ok' => true, 'mensaje' => 'Identidad eliminada.']);
    }

    // ── Ítems aprendidos (proveedor_item_alias) AJAX ───────────────────────────────

    public function aliasItems(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id', 0);
        $this->json($this->model->getAliasItems($id));
    }

    public function eliminarAliasItem(): void
    {
        $this->requireAuth();
        Csrf::check();
        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $this->model->eliminarAliasItem($id);
        $this->json(['ok' => true, 'mensaje' => 'Relación eliminada.']);
    }

    public function reasignarAliasItem(): void
    {
        $this->requireAuth();
        Csrf::check();
        $id       = (int)$this->input('id', 0);
        $tipoItem = $this->input('tipo_item', '');
        $itemId   = (int)$this->input('item_id', 0);

        if (!$id || !$itemId || !in_array($tipoItem, ['materia_prima', 'insumo', 'producto'], true)) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos.'], 422);
        }

        $this->model->reasignarAliasItem($id, $tipoItem, $itemId);
        $this->json(['ok' => true, 'mensaje' => 'Reasignado.']);
    }

    private function recogerForm(): array
    {
        $cuit = $this->input('cuit', '') ?: null;
        if ($cuit) $cuit = $this->model->normalizarCuit($cuit);
        return [
            'razon_social'  => $this->input('razon_social', ''),
            'cuit'          => $cuit,
            'condicion_iva' => $this->input('condicion_iva', 'responsable_inscripto'),
            'direccion'     => $this->input('direccion', '') ?: null,
            'telefono'      => $this->input('telefono', '') ?: null,
            'email'         => filter_var($this->input('email', ''), FILTER_VALIDATE_EMAIL) ?: null,
            'banco'         => $this->input('banco', '') ?: null,
            'cbu'           => $this->input('cbu', '') ?: null,
            'rubro'         => $this->input('rubro', '') ?: null,
            'activo'        => (int)($this->input('activo', '1') === '1'),
            'eliminado'     => 0,
        ];
    }

    private function validar(array $data): array
    {
        $errores = [];
        if (empty($data['razon_social'])) $errores[] = 'La razón social es obligatoria.';
        if (!empty($data['cuit']) && !$this->model->validarCuit($data['cuit'])) {
            $errores[] = 'El CUIT no tiene formato válido (debe tener 11 dígitos, con o sin guiones).';
        }
        return $errores;
    }
}
