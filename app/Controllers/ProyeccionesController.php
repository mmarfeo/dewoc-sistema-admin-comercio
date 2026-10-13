<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ProyeccionModel.php';

class ProyeccionesController extends Controller
{
    private ProyeccionModel $model;

    public function __construct()
    {
        $this->model = new ProyeccionModel();
    }

    public function index(): void
    {
        $this->requireCanView('reportes_proyecciones');

        $config = $this->model->obtenerConfig();
        $ventas = $this->model->obtenerUltimasVentasPorProducto();

        // Total diario agregado (todos los productos juntos), para el gráfico.
        $totalPorFecha = [];
        foreach ($ventas as $v) {
            $totalPorFecha[$v['fecha']] = ($totalPorFecha[$v['fecha']] ?? 0) + (float)$v['cantidad'];
        }
        ksort($totalPorFecha);

        // Top 10 productos por cantidad total proyectada en el horizonte, para la tabla.
        $totalPorProducto = [];
        foreach ($ventas as $v) {
            $pid = (int)$v['producto_id'];
            if (!isset($totalPorProducto[$pid])) {
                $totalPorProducto[$pid] = ['producto_id' => $pid, 'producto_nombre' => $v['producto_nombre'], 'total' => 0.0];
            }
            $totalPorProducto[$pid]['total'] += (float)$v['cantidad'];
        }
        usort($totalPorProducto, fn($a, $b) => $b['total'] <=> $a['total']);
        $topProductos = array_slice($totalPorProducto, 0, 10);

        // Cruza cada producto del top contra su stock_actual EN VIVO (mismo criterio que
        // ProyeccionModel::obtenerUltimasComprasMp() para materia prima/insumos): stock real
        // al momento de mirar la pantalla, no el que había cuando corrió el cron. "Días hasta
        // agotarse" = stock actual / consumo diario proyectado (total del horizonte / horizonte
        // en días) — solo tiene sentido para productos que controlan stock (ej. no una torta
        // hecha al momento, que no lleva stock propio).
        if ($topProductos) {
            $horizonteDias = max(1, (int)$config['horizonte_dias']);
            $ids = array_column($topProductos, 'producto_id');
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::getInstance()->prepare(
                "SELECT id, stock_actual, controla_stock FROM productos WHERE id IN ({$in})"
            );
            $stmt->execute($ids);
            $stockPorProducto = [];
            foreach ($stmt->fetchAll() as $row) {
                $stockPorProducto[(int)$row['id']] = $row;
            }

            foreach ($topProductos as &$p) {
                $info = $stockPorProducto[$p['producto_id']] ?? null;
                $p['controla_stock'] = $info && (int)$info['controla_stock'] === 1;
                $p['stock_actual']   = $info ? (float)$info['stock_actual'] : null;

                $consumoDiario = $p['total'] / $horizonteDias;
                $p['dias_restante'] = ($p['controla_stock'] && $consumoDiario > 0.0001)
                    ? (int)floor($p['stock_actual'] / $consumoDiario)
                    : null;
            }
            unset($p);
        }

        $this->render('proyecciones.index', [
            'pageTitle'      => 'Proyecciones',
            'breadcrumb'     => ['Reportes' => null, 'Proyecciones' => null],
            'config'         => $config,
            'ultimaCorrida'  => $this->model->ultimaCorrida(),
            'totalPorFecha'  => $totalPorFecha,
            'topProductos'   => $topProductos,
            'comprasMp'      => $this->model->obtenerUltimasComprasMp(),
            'margenProducto' => $this->model->obtenerUltimoMargenPorProducto(),
        ]);
    }

    /** El admin ajusta el horizonte de proyección (días) — ver docs/prediccion-ventas-compras.md, "Decisiones ya tomadas". */
    public function guardarConfig(): void
    {
        $this->requireCanEdit('reportes_proyecciones');
        Csrf::check();

        $horizonteDias = (int)$this->input('horizonte_dias', 0);
        if ($horizonteDias < 7 || $horizonteDias > 60) {
            $this->json(['ok' => false, 'error' => 'El horizonte debe estar entre 7 y 60 días.'], 422);
        }

        $this->model->guardarHorizonte($horizonteDias, (int)(Session::user()['id'] ?? 0));
        $this->json(['ok' => true, 'mensaje' => 'Horizonte actualizado. Se aplica en la próxima corrida del cron.']);
    }
}
