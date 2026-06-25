<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/FacturaCompraModel.php';

class FacturaCompraModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/FacturaCompraModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new FacturaCompraModel();
        $this->assertTrue($model instanceof Model, 'FacturaCompraModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new FacturaCompraModel();
        $ref   = new ReflectionProperty(FacturaCompraModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('facturas_compra', $ref->getValue($model));
    }

    public function testGuardarMethodExists(): void
    {
        $this->assertTrue(method_exists(FacturaCompraModel::class, 'guardar'),
            'Debe tener método guardar()');
    }

    public function testAnularMethodExists(): void
    {
        $this->assertTrue(method_exists(FacturaCompraModel::class, 'anular'),
            'Debe tener método anular()');
    }

    public function testBuscarItemsMethodExists(): void
    {
        $this->assertTrue(method_exists(FacturaCompraModel::class, 'buscarItems'),
            'Debe tener método buscarItems()');
    }

    public function testFindAllConDetalleMethodExists(): void
    {
        $this->assertTrue(method_exists(FacturaCompraModel::class, 'findAllConDetalle'),
            'Debe tener método findAllConDetalle()');
    }

    public function testFindByIdConDetalleMethodExists(): void
    {
        $this->assertTrue(method_exists(FacturaCompraModel::class, 'findByIdConDetalle'),
            'Debe tener método findByIdConDetalle()');
    }

    public function testGuardarUsesTransaction(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/FacturaCompraModel.php');
        $this->assertContains('beginTransaction', $content,
            'guardar() debe usar transacciones DB');
        $this->assertContains('rollBack', $content,
            'guardar() debe hacer rollBack en caso de error');
        $this->assertContains('commit', $content,
            'guardar() debe confirmar la transacción con commit');
    }

    public function testStockUpdateOnGuardar(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/FacturaCompraModel.php');
        $this->assertContains('stock_actual + :c', $content,
            'Debe sumar stock al guardar factura');
    }

    public function testStockRevertedOnAnular(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/FacturaCompraModel.php');
        $this->assertContains('-1 * (float)', $content,
            'Al anular debe revertir el stock (multiplicar por -1)');
    }

    public function testNoSqlConcatenationInBuscarItems(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/FacturaCompraModel.php');
        // No debe tener concatenación directa de $q sin prepared statements
        $this->assertFalse(
            (bool)preg_match('/LIKE\s+["\'][^"\']*\'\s*\.\s*\$/', $content),
            'buscarItems no debe concatenar variables en SQL'
        );
    }

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/ComprasController.php');
    }

    public function testControllerHasCsrfOnPost(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ComprasController.php');
        // guardar, anular deben llamar Csrf::check()
        $checkCount = substr_count($content, 'Csrf::check()');
        $this->assertTrue($checkCount >= 2,
            'ComprasController debe tener al menos 2 llamadas a Csrf::check() (guardar + anular)');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/compras/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/compras/form.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/compras/ver.php');
    }

    public function testFormViewHasCsrfField(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/compras/form.php');
        $this->assertContains('Csrf::field()', $content,
            'El formulario de compras debe incluir el campo CSRF');
    }

    public function testItemsJsonSentAsHiddenInput(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/compras/form.php');
        $this->assertContains('items_json', $content,
            'El formulario debe enviar los ítems como JSON');
    }

    public function testSchemaHasCorrectColumns(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('tipo_comprobante', $schema, 'Schema debe tener tipo_comprobante');
        $this->assertContains('nro_comprobante', $schema, 'Schema debe tener nro_comprobante');
        $this->assertContains('total_con_iva', $schema, 'Schema debe tener total_con_iva');
        $this->assertContains('factura_compra_id', $schema, 'Detalle debe tener factura_compra_id');
        $this->assertContains('materia_prima_id', $schema, 'Detalle debe tener materia_prima_id');
        $this->assertContains('insumo_id', $schema, 'Detalle debe tener insumo_id');
        $this->assertContains('iva_porcentaje', $schema, 'Detalle debe tener iva_porcentaje');
    }
}
