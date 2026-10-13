<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/ReporteIaModel.php';
require_once BASE_PATH . '/app/Models/ListaComprasModel.php';
require_once BASE_PATH . '/app/Models/GastoModel.php';
require_once BASE_PATH . '/app/Models/ProyeccionModel.php';

/**
 * Resumen Ejecutivo: una sola pantalla con "lo último" de los reportes que hoy se
 * disparan por separado (cron/reporte_semanal.php, reporte_margen_producto.php,
 * reporte_compras_pagos_usuario.php — cada uno avisa por push + bandeja + Chat IA de
 * forma independiente, ver core/NotificadorReportes.php) más el estado EN VIVO de lista
 * de compras, vencimientos de gastos y proyecciones. Pensada para que un admin pueda
 * revisar todo de un vistazo sin depender de haber visto cada aviso suelto.
 *
 * A propósito NO genera ni dispara ningún aviso nuevo: solo lee lo que los cron ya
 * dejaron guardado (reportes_ia) o el estado actual de sus tablas — los cron y sus
 * notificaciones existentes siguen funcionando exactamente igual que antes.
 *
 * Cada sección se muestra solo si el usuario tiene permiso de VER el módulo puntual
 * de esa sección (reportes_semanal, gastos, etc.) — requireCanView('resumen_ejecutivo')
 * solo gatea la pantalla en sí, no el contenido de cada tarjeta.
 */
class ResumenEjecutivoController extends Controller
{
    /** Ventana de vencimientos de gastos más amplia que la del cron diario (docs: 3 días)
     *  porque acá el usuario puede entrar cualquier día, no solo el día exacto del aviso. */
    private const DIAS_VENCIMIENTOS = 7;

    public function index(): void
    {
        $this->requireCanView('resumen_ejecutivo');

        $reporteModel = new ReporteIaModel();

        $data = [
            'pageTitle'  => 'Resumen Ejecutivo',
            'breadcrumb' => ['Resumen Ejecutivo' => null],

            'semanal'             => canView('reportes_semanal')
                ? $reporteModel->obtenerUltimoPorTipo('semanal') : false,
            'margenProducto'      => canView('reportes_margen_producto')
                ? $reporteModel->obtenerUltimoPorTipo('margen_producto') : false,
            'comprasPagosUsuario' => canView('reportes_compras_pagos_usuario')
                ? $reporteModel->obtenerUltimoPorTipo('compras_pagos_usuario') : false,
            'comprasPagosTotal'   => 0.0,
            'cuentasCorrientes'   => canView('reportes_cuentas_corrientes')
                ? $reporteModel->obtenerUltimoPorTipo('cuentas_corrientes') : false,

            'listaCompras'        => null,
            'vencimientos'        => null,
            'proyeccionFecha'     => null,
            'proyeccionAReponer'  => null,
        ];

        // Monto ya viene formateado como texto ("12.345", sin decimales — ver
        // cron/reporte_compras_pagos_usuario.php) porque reportes_ia guarda la foto lista
        // para pintar/exportar tal cual; para el total del resumen hay que des-formatearlo.
        if ($data['comprasPagosUsuario']) {
            foreach ($data['comprasPagosUsuario']['resultado'] as $fila) {
                $data['comprasPagosTotal'] += (float) str_replace('.', '', $fila['Monto'] ?? '0');
            }
        }

        if (canView('lista_compras')) {
            $data['listaCompras'] = (new ListaComprasModel())->listarActivos();
        }

        if (canView('gastos')) {
            $data['vencimientos'] = (new GastoModel())->proximosAVencer(self::DIAS_VENCIMIENTOS);
        }

        if (canView('reportes_proyecciones')) {
            $proyeccionModel = new ProyeccionModel();
            $data['proyeccionFecha']    = $proyeccionModel->ultimaCorrida();
            $data['proyeccionAReponer'] = array_values(array_filter(
                $proyeccionModel->obtenerUltimasComprasMp(),
                static fn(array $it): bool => $it['reponer']
            ));
        }

        $this->render('resumen-ejecutivo.index', $data);
    }
}
