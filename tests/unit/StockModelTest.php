<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/StockModel.php';

class StockModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/StockModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new StockModel();
        $this->assertTrue($model instanceof Model, 'StockModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new StockModel();
        $ref   = new ReflectionProperty(StockModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('stock_movimientos', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach ([
            'resumenStock', 'ajustar', 'reporteComparativo', 'stockTeoricoVsFisico',
            'historialMovimientos', 'historialItem', 'itemsBajoMinimo',
        ] as $met) {
            $this->assertTrue(method_exists(StockModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testAjustarUsaTransaccionYRegistraMovimiento(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/StockModel.php');
        $this->assertContains('beginTransaction', $content, 'ajustar() debe usar transacción');
        $this->assertContains('rollBack', $content, 'ajustar() debe hacer rollBack en caso de error');
        $this->assertContains('stock_antes', $content, 'ajustar() debe registrar el stock antes del ajuste (trazabilidad)');
        $this->assertContains('stock_despues', $content, 'ajustar() debe registrar el stock después del ajuste (trazabilidad)');
    }

    public function testAjustarSoportaLosTresTiposDeItem(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/StockModel.php');
        $this->assertContains("'materia_prima' => 'materias_primas'", $content);
        $this->assertContains("'insumo'        => 'insumos'", $content);
    }

    public function testItemsBajoMinimoComparaConStockMinimo(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/StockModel.php');
        $this->assertContains('stock_actual<=stock_minimo', $content, 'itemsBajoMinimo() debe comparar stock_actual contra stock_minimo');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/StockModel.php');
        $this->assertNotContains('$_GET', $content, 'StockModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'StockModel no debe usar $_POST directamente en queries');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/StockController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/StockController.php');
        // ajuste, guardarInventario, guardarApertura, importar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(4, $count,
            'StockController debe validar CSRF en ajuste/guardarInventario/guardarApertura/importar');
    }

    public function testAjusteValidaTipoDeItem(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/StockController.php');
        $this->assertContains("['producto','materia_prima','insumo']", $content,
            'ajuste() debe validar que tipo_item sea uno de los tipos soportados');
    }

    public function testAjusteRechazaCantidadCero(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/StockController.php');
        $this->assertContains('cantidad == 0', $content, 'ajuste() debe rechazar cantidad cero (no es un ajuste real)');
    }

    public function testInventarioRegistraDesvioSoloEnConteoFisico(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/StockController.php');
        // guardarInventario() llama procesarInventario(..., true) — apertura no debería registrar desvío
        $this->assertMatchesRegex(
            "/procesarInventario\\('Inventario físico', true\\)/",
            $content,
            'guardarInventario() debe registrar desvío (true), a diferencia de guardarApertura()'
        );
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/stock/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/stock/inventario.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/stock/apertura.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/stock/reporte.php');
    }

    public function testSchemaTieneTrazabilidadDeMovimientos(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('stock_movimientos', $schema, 'Schema debe tener la tabla stock_movimientos');
        $this->assertContains('stock_antes', $schema, 'stock_movimientos debe registrar stock_antes');
        $this->assertContains('stock_despues', $schema, 'stock_movimientos debe registrar stock_despues');
    }
}
