<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ReportesModel.php';
require_once BASE_PATH . '/app/Models/ProveedorModel.php';

class ReportesController extends Controller
{
    private ReportesModel $model;

    public function __construct()
    {
        $this->model = new ReportesModel();
    }

    public function ventas(): void
    {
        $this->requireAuth();

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'metodo_pago' => $this->param('metodo_pago') ?? '',
            'usuario_id'  => (int)($this->param('usuario_id') ?? 0),
        ];

        $ventas   = $this->model->ventasPorPeriodo($desde, $hasta, $filtros);
        $resumen  = $this->model->resumenVentas($desde, $hasta);
        $top      = $this->model->topProductos($desde, $hasta, 10);
        $porDia   = $this->model->ventasPorDia($desde, $hasta);

        $this->render('reportes.ventas', [
            'pageTitle'  => 'Reporte de Ventas',
            'breadcrumb' => ['Reportes' => null, 'Ventas' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'ventas'     => $ventas,
            'resumen'    => $resumen,
            'top'        => $top,
            'porDia'     => $porDia,
            'filtros'    => $filtros,
        ]);
    }

    public function compras(): void
    {
        $this->requireAuth();

        [$desde, $hasta] = $this->rango();

        $proveedores = (new ProveedorModel())->findAll(['activo' => 1]);

        $filtros = [
            'proveedor_id'     => (int)($this->param('proveedor_id') ?? 0),
            'tipo_comprobante' => $this->param('tipo_comprobante') ?? '',
        ];

        $compras      = $this->model->comprasPorPeriodo($desde, $hasta, $filtros);
        $resumen      = $this->model->resumenCompras($desde, $hasta);
        $porProveedor = $this->model->comprasPorProveedor($desde, $hasta);

        $this->render('reportes.compras', [
            'pageTitle'   => 'Reporte de Compras',
            'breadcrumb'  => ['Reportes' => null, 'Compras' => null],
            'desde'       => $desde,
            'hasta'       => $hasta,
            'compras'     => $compras,
            'resumen'     => $resumen,
            'porProveedor'=> $porProveedor,
            'proveedores' => $proveedores,
            'filtros'     => $filtros,
        ]);
    }

    public function stock(): void
    {
        $this->requireAuth();

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'tipo_item' => $this->param('tipo_item') ?? '',
            'origen'    => $this->param('origen') ?? '',
        ];

        $movimientos = $this->model->movimientosStock($desde, $hasta, $filtros);

        $this->render('reportes.stock', [
            'pageTitle'   => 'Reporte de Stock',
            'breadcrumb'  => ['Reportes' => null, 'Stock' => null],
            'desde'       => $desde,
            'hasta'       => $hasta,
            'movimientos' => $movimientos,
            'filtros'     => $filtros,
        ]);
    }

    public function caja(): void
    {
        $this->requireAuth();

        [$desde, $hasta] = $this->rango();

        $turnos  = $this->model->turnosPorPeriodo($desde, $hasta);
        $resumen = $this->model->resumenCaja($desde, $hasta);

        $this->render('reportes.caja', [
            'pageTitle'  => 'Reporte de Caja',
            'breadcrumb' => ['Reportes' => null, 'Caja' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'turnos'     => $turnos,
            'resumen'    => $resumen,
        ]);
    }

    public function produccion(): void
    {
        $this->requireAuth();

        [$desde, $hasta] = $this->rango();

        $filtros = [
            'receta_id' => (int)($this->param('receta_id') ?? 0),
        ];

        $ordenes   = $this->model->produccionPorPeriodo($desde, $hasta, $filtros);
        $resumen   = $this->model->resumenProduccion($desde, $hasta);
        $porReceta = $this->model->produccionPorReceta($desde, $hasta);
        $recetas   = $this->model->getRecetasActivas();

        $this->render('reportes.produccion', [
            'pageTitle'  => 'Reporte de Producción',
            'breadcrumb' => ['Reportes' => null, 'Producción' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'ordenes'    => $ordenes,
            'resumen'    => $resumen,
            'porReceta'  => $porReceta,
            'recetas'    => $recetas,
            'filtros'    => $filtros,
        ]);
    }

    private function rango(): array
    {
        $hoy   = date('Y-m-d');
        $desde = $this->param('desde') ?: date('Y-m-01');
        $hasta = $this->param('hasta') ?: $hoy;

        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

        return [$desde, $hasta];
    }
}
