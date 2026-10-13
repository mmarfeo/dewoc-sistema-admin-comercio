<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireCanView('dashboard');

        $db = Database::getInstance();

        // KPIs del día
        $hoy = date('Y-m-d');

        $ventasHoy = $db->prepare(
            'SELECT COUNT(*) AS cantidad, COALESCE(SUM(total_con_iva),0) AS total
             FROM ventas WHERE DATE(created_at) = ? AND estado = "completada"'
        );
        $ventasHoy->execute([$hoy]);
        $kpiVentas = $ventasHoy->fetch();

        $ticketPromedio = $kpiVentas['cantidad'] > 0
            ? $kpiVentas['total'] / $kpiVentas['cantidad']
            : 0;

        // Stock bajo
        $stockBajo = $db->query(
            'SELECT "producto" AS tipo, nombre, stock_actual, stock_minimo FROM productos WHERE stock_actual <= stock_minimo AND eliminado = 0 AND activo = 1 AND controla_stock = 1
             UNION ALL
             SELECT "materia_prima", nombre, stock_actual, stock_minimo FROM materias_primas WHERE stock_actual <= stock_minimo AND eliminado = 0
             UNION ALL
             SELECT "insumo", nombre, stock_actual, stock_minimo FROM insumos WHERE stock_actual <= stock_minimo AND eliminado = 0
             LIMIT 10'
        )->fetchAll();

        // Últimas 8 ventas
        $ultimasVentas = $db->query(
            'SELECT v.id, v.total_con_iva AS total, v.metodo_pago, v.afip_resultado AS estado_afip, u.nombre AS cajero, v.created_at
             FROM ventas v JOIN usuarios u ON v.usuario_id = u.id
             WHERE v.estado = "completada"
             ORDER BY v.created_at DESC LIMIT 8'
        )->fetchAll();

        // Ventas últimos 7 días (para gráfico)
        $stmt = $db->prepare(
            'SELECT DATE(created_at) AS dia, COALESCE(SUM(total_con_iva),0) AS total
             FROM ventas
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND estado = "completada"
             GROUP BY DATE(created_at)
             ORDER BY dia ASC'
        );
        $stmt->execute();
        $ventasSemana = $stmt->fetchAll();

        // Top 5 productos del mes
        $topProductos = $db->prepare(
            'SELECT p.nombre, SUM(vd.cantidad) AS cant_total, SUM(vd.subtotal) AS monto_total
             FROM ventas_detalle vd
             JOIN productos p ON vd.producto_id = p.id
             JOIN ventas v ON vd.venta_id = v.id
             WHERE MONTH(v.created_at) = MONTH(NOW()) AND YEAR(v.created_at) = YEAR(NOW()) AND v.estado = "activa"
             GROUP BY vd.producto_id
             ORDER BY cant_total DESC LIMIT 5'
        );
        $topProductos->execute();
        $top5 = $topProductos->fetchAll();

        // Turno activo
        $turnoActivo = $db->query(
            'SELECT t.*, u.nombre AS cajero FROM turnos t JOIN usuarios u ON t.usuario_id = u.id WHERE t.estado = "abierto" LIMIT 1'
        )->fetch();

        $this->render('dashboard.index', [
            'pageTitle'    => 'Dashboard',
            'breadcrumb'   => [],
            'kpiVentas'    => $kpiVentas,
            'ticketProm'   => $ticketPromedio,
            'stockBajo'    => $stockBajo,
            'ultimasVentas'=> $ultimasVentas,
            'ventasSemana' => $ventasSemana,
            'top5'         => $top5,
            'turnoActivo'  => $turnoActivo,
        ]);
    }
}
