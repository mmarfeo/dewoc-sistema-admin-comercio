<?php
require_once __DIR__ . '/../TestCase.php';

class DashboardControllerTest extends TestCase
{
    public function testControllerExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Controllers/DashboardController.php'),
            'DashboardController.php debe existir'
        );
    }

    public function testControllerRequiereCanView(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/DashboardController.php');
        $this->assertContains("requireCanView('dashboard')", $content,
            'index() debe requerir permiso de vista sobre el módulo dashboard');
    }

    public function testFiltraEstadoCompletadaEnKpisDeVentas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/DashboardController.php');
        $this->assertContains("estado = \"completada\"", $content,
            'Los KPIs de ventas del día deben excluir ventas anuladas');
    }

    public function testFiltraStockBajoPorEliminadoYActivo(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/DashboardController.php');
        $this->assertContains('eliminado = 0', $content, 'El listado de stock bajo debe excluir ítems eliminados');
    }

    public function testViewExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Views/dashboard/index.php'),
            'Vista dashboard/index.php debe existir'
        );
    }

    public function testViewUsaEscapeHTML(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Views/dashboard/index.php');
        $this->assertContains('e(', $contenido, 'Vista de dashboard debe usar helper e() para escape XSS');
    }

    /**
     * HALLAZGO REAL: la consulta de "Top 5 productos del mes" filtra
     * v.estado = "activa", pero la columna ventas.estado es
     * ENUM('completada','anulada') (ver schema.sql) — 'activa' NUNCA es un
     * valor posible. En el resto del sistema (VentaModel::registrar(),
     * StockModel, ReportesModel) el estado de una venta válida siempre se
     * compara contra 'completada'. Como consecuencia, el bloque "Top 5
     * productos" del dashboard nunca encuentra filas y queda siempre vacío
     * en producción. No corregido acá: excede el alcance de tests/unit.
     */
    public function testTopProductosUsaEstadoDeVentaValido(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/DashboardController.php');
        $schema  = file_get_contents(BASE_PATH . '/database/schema.sql');

        $usaEstadoActiva = str_contains($content, 'v.estado = "activa"');

        $tablaVentas = substr($schema, (int)strpos($schema, 'CREATE TABLE IF NOT EXISTS `ventas`'));
        $tablaVentas = substr($tablaVentas, 0, (int)strpos($tablaVentas, ') ENGINE'));
        $estadoActivaEsValido = (bool)preg_match("/`estado`\\s+ENUM\\([^)]*'activa'/", $tablaVentas);

        $this->assertFalse(
            $usaEstadoActiva && !$estadoActivaEsValido,
            'BUG REAL: el Top 5 productos del dashboard filtra v.estado = "activa", valor que no existe '
            . "en ventas.estado ENUM('completada','anulada'). Esta sección del dashboard siempre "
            . 'devuelve vacío. No corregido acá: excede el alcance de tests/unit.'
        );
    }
}
