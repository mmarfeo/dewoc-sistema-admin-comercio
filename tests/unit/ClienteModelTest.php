<?php
require_once __DIR__ . '/../TestCase.php';

class ClienteModelTest extends TestCase
{
    public function testArchivoExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Models/ClienteModel.php'),
            'ClienteModel.php debe existir'
        );
    }

    public function testExtiendeModel(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        $this->assertContains('extends Model', $contenido, 'ClienteModel debe extender Model');
    }

    public function testTablaCorrecta(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        $this->assertContains("'clientes'", $contenido, 'La tabla debe ser clientes');
    }

    public function testMetodosExisten(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        foreach (['findAll', 'buscar', 'tieneVentas', 'historialVentas'] as $met) {
            $this->assertContains("function $met", $contenido, "Debe existir el mÃ©todo $met");
        }
    }

    public function testSinConcatenacionSQL(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        $this->assertNotContains('$_GET', $contenido, 'No debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $contenido, 'No debe usar $_POST directamente en queries');
    }

    public function testBuscarUsaLike(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        $this->assertContains('LIKE', $contenido, 'buscar() debe usar LIKE para bÃºsqueda parcial');
        $this->assertContains(':q', $contenido, 'La bÃºsqueda debe usar parÃ¡metro nombrado :q');
    }

    public function testSoftDeleteEnFindAll(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Models/ClienteModel.php');
        $this->assertContains('eliminado = 0', $contenido, 'findAll() debe filtrar eliminados');
    }

    public function testControllerExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Controllers/ClientesController.php'),
            'ClientesController.php debe existir'
        );
    }

    public function testControllerUsaCSRF(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Controllers/ClientesController.php');
        $this->assertContains('Csrf::check()', $contenido, 'Controller debe verificar CSRF en POSTs');
    }

    public function testVistaIndexExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Views/clientes/index.php'),
            'Vista clientes/index.php debe existir'
        );
    }

    public function testVistaFormExiste(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Views/clientes/form.php'),
            'Vista clientes/form.php debe existir'
        );
    }

    public function testVistaUsaEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/clientes/index.php');
        $form  = file_get_contents(BASE_PATH . '/app/Views/clientes/form.php');
        $this->assertContains('e(', $index, 'Vista index debe usar helper e() para escape XSS');
        $this->assertContains('e(', $form,  'Vista form debe usar helper e() para escape XSS');
    }

    public function testFormUsaCsrfField(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Views/clientes/form.php');
        $this->assertContains('Csrf::field()', $contenido, 'Formulario debe incluir campo CSRF');
    }

    public function testControllerVerificaVentasAntesDeEliminar(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Controllers/ClientesController.php');
        $this->assertContains('tieneVentas', $contenido, 'Eliminar debe verificar si hay ventas asociadas');
    }
}
