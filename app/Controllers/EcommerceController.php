<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/EcommerceModel.php';
require_once BASE_PATH . '/core/Mailer.php';

class EcommerceController extends Controller
{
    private EcommerceModel $model;

    public function __construct()
    {
        $this->model = new EcommerceModel();
    }

    public function pedidos(): void
    {
        $this->requireAuth();

        $filtros = [
            'estado' => $this->param('estado') ?? '',
            'desde'  => $this->param('desde') ?: date('Y-m-01'),
            'hasta'  => $this->param('hasta') ?: date('Y-m-d'),
        ];

        $this->render('ecommerce.pedidos', [
            'pageTitle'  => 'Pedidos Ecommerce',
            'breadcrumb' => ['Ecommerce' => null, 'Pedidos' => null],
            'pedidos'    => $this->model->findAllAdmin($filtros),
            'filtros'    => $filtros,
        ]);
    }

    public function verPedido(): void
    {
        $this->requireAuth();

        $id     = (int)$this->param('id');
        $pedido = $this->model->findByIdConDetalle($id);
        if (!$pedido) {
            $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]);
            return;
        }

        $this->render('ecommerce.ver_pedido', [
            'pageTitle'  => 'Pedido #' . $pedido['id'],
            'breadcrumb' => ['Ecommerce' => null, 'Pedidos' => 'ecommerce/pedidos', '#' . $pedido['id'] => null],
            'pedido'     => $pedido,
        ]);
    }

    public function cambiarEstado(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id     = (int)$this->input('id');
        $estado = $this->input('estado', '');

        if (!$id || !$estado) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        try {
            $pedido = $this->model->findById($id);
            $this->model->cambiarEstado($id, $estado);

            if ($estado === 'pagado') {
                $this->model->descontarStock($id);
                if ($pedido) {
                    Mailer::confirmacionPedido($pedido);
                }
            }

            $this->json(['ok' => true, 'mensaje' => 'Estado actualizado a "' . $estado . '".']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
