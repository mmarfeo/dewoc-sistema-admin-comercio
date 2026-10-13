<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ActivoFijoModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';
require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';

/** Activos fijos (ver docs/activos-fijos-diseno.md) — bienes de uso con valuación y amortización opcional. */
class ActivosFijosController extends Controller
{
    private ActivoFijoModel $model;

    public function __construct()
    {
        $this->model = new ActivoFijoModel();
    }

    public function index(): void
    {
        $this->requireCanView('activos_fijos');

        $estado  = $this->param('estado', '');
        $activos = $this->model->findAllConDetalle(['estado' => $estado]);

        $activosVigentes = array_filter($activos, fn($a) => $a['estado'] === 'activo');

        $this->render('activos.index', [
            'pageTitle'      => 'Activos Fijos',
            'breadcrumb'     => ['Activos Fijos' => null],
            'activos'        => $activos,
            'filtroEstado'   => $estado,
            'totalHistorico' => array_sum(array_column($activosVigentes, 'valor')),
            'totalActual'    => array_sum(array_column($activosVigentes, 'valor_actual')),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireCanEdit('activos_fijos');
        $this->render('activos.form', [
            'pageTitle'   => 'Nuevo activo fijo',
            'breadcrumb'  => ['Activos Fijos' => 'activos', 'Nuevo' => null],
            'activo'      => null,
            'proveedores' => (new ProveedorModel())->findAllActivos(),
        ]);
    }

    public function crear(): void
    {
        $this->requireCanEdit('activos_fijos');
        Csrf::check();

        // "Viene de una compra ya cargada" (ver buscarCompras()) — convierte esa línea en
        // vez de dar de alta un activo suelto: revierte su stock/costo y la saca de la
        // factura original (ActivoFijoModel::crearDesdeLineaCompra()), no pasa por
        // parsearForm()/crear() normal porque el origen siempre es "compra_nueva" con datos
        // que ya vinieron de esa línea, no de un formulario libre.
        $facturaCompraDetalleId = (int)$this->input('factura_compra_detalle_id', 0);
        if ($facturaCompraDetalleId) {
            $nombre = trim($this->input('nombre', ''));
            if (!$nombre) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

            $vidaUtilRaw = trim($this->input('vida_util_meses', ''));
            $vidaUtil    = $vidaUtilRaw !== '' ? (int)$vidaUtilRaw : null;
            if ($vidaUtil !== null && $vidaUtil <= 0) $vidaUtil = null;

            try {
                $id = $this->model->crearDesdeLineaCompra($facturaCompraDetalleId, [
                    'nombre'          => $nombre,
                    'categoria'       => trim($this->input('categoria', '')) ?: null,
                    'fecha_alta'      => $this->input('fecha_alta', '') ?: date('Y-m-d'),
                    'valor'           => (float)str_replace(',', '.', $this->input('valor', '0')),
                    'vida_util_meses' => $vidaUtil,
                    'notas'           => trim($this->input('notas', '')) ?: null,
                    'usuario_id'      => (int)Session::user()['id'],
                ]);
                $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Activo creado y línea convertida — se sacó del detalle de la factura original.']);
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            return;
        }

        [$data, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $id = $this->model->crear($data);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Activo registrado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Buscador de líneas de compra ya cargadas, para "viene de una compra ya cargada" en el
     *  formulario de alta (ver FacturaCompraModel::buscarLineasParaActivoFijo()). */
    public function buscarCompras(): void
    {
        $this->requireCanEdit('activos_fijos');
        $q = trim($this->param('q', ''));
        if (mb_strlen($q) < 2) { $this->json(['ok' => true, 'resultados' => []]); }
        $this->json(['ok' => true, 'resultados' => (new FacturaCompraModel())->buscarLineasParaActivoFijo($q)]);
    }

    public function editar(): void
    {
        $this->requireCanEdit('activos_fijos');
        $id     = (int)$this->param('id', 0);
        $activo = $this->model->findById($id);
        if (!$activo) abort(404);

        $this->render('activos.form', [
            'pageTitle'   => 'Editar activo fijo',
            'breadcrumb'  => ['Activos Fijos' => 'activos', 'Editar' => null],
            'activo'      => $activo,
            'proveedores' => (new ProveedorModel())->findAllActivos(),
        ]);
    }

    public function actualizar(): void
    {
        $this->requireCanEdit('activos_fijos');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $nombre = trim($this->input('nombre', ''));
        if (!$nombre) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        $estado = $this->input('estado', 'activo');
        if (!in_array($estado, ['activo', 'baja', 'vendido'], true)) $estado = 'activo';

        $this->model->actualizar($id, [
            'nombre'       => $nombre,
            'categoria'    => trim($this->input('categoria', '')) ?: null,
            'proveedor_id' => (int)$this->input('proveedor_id', 0) ?: null,
            'estado'       => $estado,
            'notas'        => trim($this->input('notas', '')) ?: null,
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Activo actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireCanEdit('activos_fijos');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $this->model->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Activo eliminado.']);
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre = trim($this->input('nombre', ''));
        if (!$nombre) $errores[] = 'El nombre es obligatorio.';

        $origen = $this->input('origen', 'compra_nueva');
        if (!in_array($origen, ['compra_nueva', 'preexistente'], true)) $origen = 'compra_nueva';

        $fechaAlta = $this->input('fecha_alta', '') ?: date('Y-m-d');

        $valor = (float)str_replace(',', '.', $this->input('valor', '0'));
        if ($valor <= 0) $errores[] = 'El valor debe ser mayor a cero.';

        $vidaUtilRaw = trim($this->input('vida_util_meses', ''));
        $vidaUtil    = $vidaUtilRaw !== '' ? (int)$vidaUtilRaw : null;
        if ($vidaUtil !== null && $vidaUtil <= 0) $vidaUtil = null;

        return [[
            'nombre'          => $nombre,
            'categoria'       => trim($this->input('categoria', '')) ?: null,
            'origen'          => $origen,
            'fecha_alta'      => $fechaAlta,
            'valor'           => $valor,
            'vida_util_meses' => $vidaUtil,
            'proveedor_id'    => (int)$this->input('proveedor_id', 0) ?: null,
            'estado'          => 'activo',
            'notas'           => trim($this->input('notas', '')) ?: null,
            'usuario_id'      => (int)Session::user()['id'],
            'eliminado'       => 0,
        ], $errores];
    }
}
