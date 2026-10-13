<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/CajaModel.php';

/**
 * Cobertura del módulo de Caja (cierre de turno) — recién modificado para
 * declarar efectivo y Mercado Pago por separado. Sigue la misma convención
 * de análisis estático/reflexión que FacturaCompraModelTest/ClienteModelTest:
 * no inserta datos reales, solo verifica estructura, permisos y schema.
 */
class CajaModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/CajaModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new CajaModel();
        $this->assertTrue($model instanceof Model, 'CajaModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new CajaModel();
        $ref   = new ReflectionProperty(CajaModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('turnos', $ref->getValue($model));
    }

    public function testCalcularEsperadoEsPrivadoYExiste(): void
    {
        $this->assertTrue(
            method_exists(CajaModel::class, 'calcularEsperado'),
            'Debe existir el método calcularEsperado()'
        );
        $ref = new ReflectionMethod(CajaModel::class, 'calcularEsperado');
        $this->assertTrue($ref->isPrivate(), 'calcularEsperado() debe ser privado (no expone el esperado directamente)');
    }

    public function testCerrarMethodExists(): void
    {
        $this->assertTrue(method_exists(CajaModel::class, 'cerrar'), 'Debe tener método cerrar()');
    }

    public function testDiferenciasPorUsuarioMethodExists(): void
    {
        $this->assertTrue(
            method_exists(CajaModel::class, 'diferenciasPorUsuario'),
            'Debe tener método diferenciasPorUsuario()'
        );
    }

    public function testDiagnosticarMethodExists(): void
    {
        $this->assertTrue(method_exists(CajaModel::class, 'diagnosticar'), 'Debe tener método diagnosticar()');
    }

    public function testAbrirMethodExists(): void
    {
        $this->assertTrue(method_exists(CajaModel::class, 'abrir'), 'Debe tener método abrir()');
    }

    public function testRegistrarMovimientoMethodExists(): void
    {
        $this->assertTrue(method_exists(CajaModel::class, 'registrarMovimiento'), 'Debe tener método registrarMovimiento()');
    }

    public function testCerrarCalculaDiferenciaPorMedioDePago(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $this->assertContains('diferencia_efectivo', $content, 'cerrar() debe calcular diferencia_efectivo');
        $this->assertContains('diferencia_mp', $content, 'cerrar() debe calcular diferencia_mp');
        $this->assertContains('cierre_ciego', $content, 'cerrar() debe registrar si el cierre fue ciego');
    }

    public function testCalcularEsperadoAgrupaPorMetodoPago(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $this->assertContains('metodo_pago', $content, 'calcularEsperado() debe agrupar movimientos por metodo_pago');
        $this->assertContains('mercadopago', $content, 'calcularEsperado() debe distinguir el medio mercadopago');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $this->assertNotContains('$_GET', $content, 'CajaModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'CajaModel no debe usar $_POST directamente en queries');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/CajaController.php');
    }

    public function testControllerRequiereCanViewEnTodasLasAcciones(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/CajaController.php');
        // index, abrir, cerrar, movimiento, historial, ticket => 6 llamadas
        $count = substr_count($content, "requireCanView('caja')");
        $this->assertGreaterThanOrEqual(6, $count,
            'CajaController debe llamar requireCanView(\'caja\') en index/abrir/cerrar/movimiento/historial/ticket');
    }

    public function testDiferenciasRequiereCanEdit(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/CajaController.php');
        // Verificar que el método diferencias() usa requireCanEdit (no solo requireCanView)
        $pos = strpos($content, 'function diferencias');
        $this->assertTrue($pos !== false, 'Debe existir el método diferencias()');
        $fragmento = substr($content, $pos, 300);
        $this->assertContains("requireCanEdit('caja')", $fragmento,
            'diferencias() (reporte de auditoría) debe requerir permiso de edición, no solo de vista');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/CajaController.php');
        // abrir, cerrar, movimiento => 3 POSTs con Csrf::check()
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(3, $count,
            'CajaController debe validar CSRF en abrir/cerrar/movimiento');
    }

    public function testCierreCiegoOcultaEsperadoEnIndex(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/CajaController.php');
        $this->assertContains('cierraCiego', $content,
            'index() debe ocultar el monto esperado cuando el usuario no tiene permiso de edición');
        $this->assertContains("unset(\$turnoDetalle['esperado']", $content,
            'Si el cierre es ciego, no debe llegar al HTML el monto esperado calculado');
    }

    // ── Vistas ───────────────────────────────────────────────────────────────

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/caja/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/caja/historial.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/caja/diferencias.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/caja/ticket_cierre.php');
    }

    // ── Schema ───────────────────────────────────────────────────────────────

    public function testSchemaTieneColumnasDeCierrePorMedioDePago(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('metodo_pago', $schema, 'Schema debe tener columna metodo_pago');
        $this->assertContains('diferencia_efectivo', $schema, 'Schema debe tener columna diferencia_efectivo');
        $this->assertContains('diferencia_mp', $schema, 'Schema debe tener columna diferencia_mp');
        $this->assertContains('cierre_ciego', $schema, 'Schema debe tener columna cierre_ciego');
    }

    // ── VentaModel::registrar propaga metodo_pago a caja_movimientos ─────────

    public function testVentaModelPropagaMetodoPagoACajaMovimientos(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains('metodo_pago', $content,
            'VentaModel::registrar debe propagar metodo_pago al INSERT de caja_movimientos');
        // El INSERT de caja_movimientos debe incluir la columna metodo_pago en su lista de columnas
        $this->assertMatchesRegex(
            '/INSERT INTO\s+caja_movimientos[\s\S]{0,200}metodo_pago/i',
            $content,
            'El INSERT de caja_movimientos debe incluir la columna metodo_pago'
        );
    }
}
