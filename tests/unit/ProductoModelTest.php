<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/ProductoModel.php';

class ProductoModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/ProductoModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new ProductoModel();
        $this->assertTrue($model instanceof Model, 'ProductoModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new ProductoModel();
        $ref   = new ReflectionProperty(ProductoModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('productos', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach ([
            'findAllConDetalle', 'findByIdConDetalle', 'getRecetasConCosto',
            'asignarReceta', 'quitarReceta', 'tieneVentas', 'actualizarStock',
        ] as $met) {
            $this->assertTrue(method_exists(ProductoModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testFindAllConDetalleFiltraEliminados(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ProductoModel.php');
        $this->assertContains('p.eliminado = 0', $content, 'findAllConDetalle() debe filtrar productos eliminados');
    }

    public function testTieneVentasVerificaDetalleDeVentas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ProductoModel.php');
        $this->assertContains('ventas_detalle', $content, 'tieneVentas() debe consultar ventas_detalle');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ProductoModel.php');
        $this->assertNotContains('$_GET', $content, 'ProductoModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'ProductoModel no debe usar $_POST directamente en queries');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/ProductosController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        // crear, asignarReceta, quitarReceta, actualizar, eliminar, importar, previsualizar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(7, $count,
            'ProductosController debe validar CSRF en todas sus acciones de escritura');
    }

    public function testEliminarVerificaVentasAsociadas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->assertContains('tieneVentas', $content, 'eliminar() debe verificar si el producto tiene ventas asociadas antes de borrarlo');
    }

    public function testEliminarUsaSoftDelete(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->assertContains('softDelete', $content, 'eliminar() debe hacer baja lógica, no DELETE físico');
    }

    public function testValidarRechazaPrecioNegativo(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->assertContains("precio_venta'] < 0", $content, 'validar() debe rechazar precio de venta negativo');
    }

    public function testValidarRestringeIvaAAlicuotasValidas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->assertContains('[0, 10.5, 21]', $content, 'La alícuota de IVA debe restringirse a 0/10.5/21%');
    }

    public function testSubirImagenValidaTipoYTamano(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->assertContains('ALLOWED_IMAGES', $content, 'La subida de imagen debe validar el tipo MIME permitido');
        $this->assertContains('MAX_FILE_SIZE', $content, 'La subida de imagen debe validar el tamaño máximo');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/productos/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/productos/form.php');
    }

    public function testFormUsaCsrfField(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/productos/form.php');
        $this->assertContains('Csrf::field()', $content, 'Formulario de productos debe incluir el campo CSRF');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/productos/index.php');
        $this->assertContains('e(', $index, 'Vista index de productos debe usar helper e() para escape XSS');
    }
}
