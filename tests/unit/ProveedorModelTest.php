<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/ProveedorModel.php';

class ProveedorModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/ProveedorModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new ProveedorModel();
        $this->assertTrue($model instanceof Model, 'ProveedorModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new ProveedorModel();
        $ref   = new ReflectionProperty(ProveedorModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('proveedores', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach ([
            'findAllActivos', 'buscar', 'tieneCompras', 'validarCuit', 'normalizarCuit',
            'getIdentidades', 'agregarIdentidad', 'eliminarIdentidad', 'buscarPorIdentidad',
        ] as $met) {
            $this->assertTrue(method_exists(ProveedorModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testValidarCuitExigeOnceDigitos(): void
    {
        $model = new ProveedorModel();
        $this->assertTrue($model->validarCuit('20-12345678-9'), 'CUIT con guiones y 11 dígitos debe ser válido');
        $this->assertTrue($model->validarCuit('20123456789'), 'CUIT sin guiones y 11 dígitos debe ser válido');
        $this->assertFalse($model->validarCuit('12345'), 'CUIT con menos de 11 dígitos debe ser inválido');
        $this->assertFalse($model->validarCuit(''), 'CUIT vacío debe ser inválido');
    }

    public function testNormalizarCuitFormatea(): void
    {
        $model = new ProveedorModel();
        $this->assertEquals('20-12345678-9', $model->normalizarCuit('20123456789'),
            'normalizarCuit() debe formatear XX-XXXXXXXX-X');
    }

    public function testBuscarUsaLikeConParametros(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ProveedorModel.php');
        $this->assertContains('LIKE :q1', $content, 'buscar() debe usar LIKE con parámetro nombrado (evita inyección)');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ProveedorModel.php');
        $this->assertNotContains('$_GET', $content, 'ProveedorModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'ProveedorModel no debe usar $_POST directamente en queries');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/ProveedoresController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProveedoresController.php');
        // crear, actualizar, eliminar, toggleActivo, previsualizar, importar, agregarIdentidad, eliminarIdentidad
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(8, $count,
            'ProveedoresController debe validar CSRF en todas sus acciones de escritura');
    }

    public function testEliminarVerificaComprasAsociadas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProveedoresController.php');
        $this->assertContains('tieneCompras', $content, 'eliminar() debe verificar si el proveedor tiene facturas de compra asociadas');
    }

    public function testValidarRechazaCuitInvalido(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProveedoresController.php');
        $this->assertContains('validarCuit', $content, 'validar() debe usar validarCuit() del modelo antes de guardar');
    }

    public function testImportarNormalizaCuitYValidaEmail(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ProveedoresController.php');
        $this->assertContains('FILTER_VALIDATE_EMAIL', $content, 'La importación debe validar el formato de email');
        $this->assertContains('normalizarCuit', $content, 'La importación debe normalizar el CUIT');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/proveedores/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/proveedores/form.php');
    }

    public function testFormUsaCsrfField(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/proveedores/form.php');
        $this->assertContains('Csrf::field()', $content, 'Formulario de proveedores debe incluir el campo CSRF');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/proveedores/index.php');
        $this->assertContains('e(', $index, 'Vista index de proveedores debe usar helper e() para escape XSS');
    }
}
