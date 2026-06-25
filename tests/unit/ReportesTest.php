<?php
require_once __DIR__ . '/../TestCase.php';

class ReportesTest extends TestCase
{
    public function testModeloExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Models/ReportesModel.php'),
            'ReportesModel.php debe existir'
        );
    }

    public function testControllerExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Controllers/ReportesController.php'),
            'ReportesController.php debe existir'
        );
    }

    public function testMetodosModeloExisten(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ReportesModel.php');
        foreach ([
            'ventasPorPeriodo', 'resumenVentas', 'topProductos', 'ventasPorDia',
            'comprasPorPeriodo', 'resumenCompras', 'comprasPorProveedor',
            'movimientosStock',
            'turnosPorPeriodo', 'resumenCaja',
        ] as $met) {
            $this->assertContains("function $met", $contenido, "Debe existir el mÃ©todo $met");
        }
    }

    public function testControllerTieneMetodos(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Controllers/ReportesController.php');
        foreach (['ventas', 'compras', 'stock', 'caja'] as $met) {
            $this->assertContains("function $met", $contenido, "Controller debe tener mÃ©todo $met");
        }
    }

    public function testVistasExisten(): void
    {
        foreach (['ventas', 'compras', 'stock', 'caja'] as $v) {
            $this->assertTrue(
                file_exists(BASE_PATH . "/app/Views/reportes/$v.php"),
                "Vista reportes/$v.php debe existir"
            );
        }
    }

    public function testVistasUsanDataTable(): void
    {
        foreach (['ventas', 'compras', 'stock', 'caja'] as $v) {
            $contenido = file_get_contents(BASE_PATH . "/app/Views/reportes/$v.php");
            $this->assertContains('DataTable', $contenido, "Vista $v debe usar DataTable");
        }
    }

    public function testVistasUsanEscape(): void
    {
        foreach (['ventas', 'compras', 'stock', 'caja'] as $v) {
            $contenido = file_get_contents(BASE_PATH . "/app/Views/reportes/$v.php");
            $this->assertContains('e(', $contenido, "Vista $v debe usar e() para escape XSS");
        }
    }

    public function testVistasUsanFiltroFecha(): void
    {
        foreach (['ventas', 'compras', 'stock', 'caja'] as $v) {
            $contenido = file_get_contents(BASE_PATH . "/app/Views/reportes/$v.php");
            $this->assertContains('name="desde"', $contenido, "Vista $v debe tener filtro de fecha desde");
            $this->assertContains('name="hasta"', $contenido, "Vista $v debe tener filtro de fecha hasta");
        }
    }

    public function testModeloUsaPreparedStatements(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ReportesModel.php');
        $this->assertNotContains('$_GET', $contenido, 'No debe usar $_GET en queries');
        $this->assertNotContains('$_POST', $contenido, 'No debe usar $_POST en queries');
        $this->assertContains('queryAll', $contenido, 'Debe usar mÃ©todos PDO del modelo base');
    }

    public function testControllerUsaRango(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Controllers/ReportesController.php');
        $this->assertContains('rango()', $contenido, 'Controller debe usar mÃ©todo rango() para normalizar fechas');
    }

    public function testVentaChartJS(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Views/reportes/ventas.php');
        $this->assertContains('Chart', $contenido, 'Vista de ventas debe incluir grÃ¡fico Chart.js');
    }
}
