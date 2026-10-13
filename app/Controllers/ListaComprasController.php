<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ListaComprasModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class ListaComprasController extends Controller
{
    private ListaComprasModel $model;

    public function __construct()
    {
        $this->model = new ListaComprasModel();
    }

    public function index(): void
    {
        $this->requireCanView('lista_compras');
        $grupos = $this->model->agrupadoPorProveedorYEstado();
        $this->render('lista-compras.index', [
            'pageTitle'   => 'Lista de Compras',
            'breadcrumb'  => ['Lista de Compras' => null],
            'sugeridos'   => $grupos['sugeridos'],
            'pendientes'  => $grupos['pendientes'],
            'produccion'  => $this->model->candidatosProduccion(),
            'proveedores' => (new ProveedorModel())->findAllActivos(),
        ]);
    }

    /**
     * Alta manual de un ítem directo a la lista, desde la pantalla (buscador + proveedor
     * opcional en /lista-compras, o el botón "Agregar a Lista de Compras" en Productos/
     * Stock/Inventario físico). `estado` deja elegir el destino: 'pendiente' (Orden de
     * Compra) o 'sugerido' (Recomendaciones) — default 'pendiente' si no viene.
     */
    public function agregarManual(): void
    {
        $this->requireCanEdit('lista_compras');
        Csrf::check();

        $tipoItem    = $this->input('tipo_item', '');
        $itemId      = (int) $this->input('item_id', 0);
        $proveedorId = (int) $this->input('proveedor_id', 0) ?: null;
        $notas       = trim($this->input('notas', '')) ?: null;
        $estado      = $this->input('estado', 'pendiente') === 'sugerido' ? 'sugerido' : 'pendiente';

        if (!in_array($tipoItem, ['materia_prima', 'insumo', 'producto'], true) || !$itemId) {
            $this->json(['ok' => false, 'error' => 'Elegí un ítem válido para agregar.'], 422);
        }

        $usuarioId = (int) Session::user()['id'];
        $resultado = $this->model->agregarManual($tipoItem, $itemId, $usuarioId, $notas, $proveedorId, $estado);

        $destino = $estado === 'sugerido' ? 'las Recomendaciones de compra' : 'la Orden de Compra';
        $this->json([
            'ok'      => true,
            'mensaje' => $resultado['ya_existia'] ? "Ese ítem ya estaba en {$destino}." : "Agregado a {$destino}.",
        ]);
    }

    public function confirmarSugerido(): void
    {
        $this->requireCanEdit('lista_compras');
        Csrf::check();
        $id = (int) $this->input('id', 0);
        $this->model->confirmarSugeridos([$id]);
        $this->json(['ok' => true]);
    }

    public function descartarSugerido(): void
    {
        $this->requireCanEdit('lista_compras');
        Csrf::check();
        $id = (int) $this->input('id', 0);
        $this->model->descartarSugeridos([$id]);
        $this->json(['ok' => true]);
    }

    public function marcarComprado(): void
    {
        $this->requireCanEdit('lista_compras');
        Csrf::check();
        $id = (int) $this->input('id', 0);
        $ok = $this->model->marcarComprado($id);
        $this->json(['ok' => $ok]);
    }

    public function quitar(): void
    {
        $this->requireCanEdit('lista_compras');
        Csrf::check();
        $id = (int) $this->input('id', 0);
        $ok = $this->model->quitar($id);
        $this->json(['ok' => $ok]);
    }
}
