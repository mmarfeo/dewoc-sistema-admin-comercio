<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/VentaModel.php';

/**
 * Cobertura del módulo de Ventas / POS — dinero real, IVA, AFIP.
 * Mismo estilo de FacturaCompraModelTest: análisis estático/reflexión, sin
 * fixtures reales en la base.
 */
class VentaModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/VentaModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new VentaModel();
        $this->assertTrue($model instanceof Model, 'VentaModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new VentaModel();
        $ref   = new ReflectionProperty(VentaModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('ventas', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach (['registrar', 'anular', 'findAllConDetalle', 'findByIdConDetalle', 'updateAfip', 'totalesDelDia'] as $met) {
            $this->assertTrue(method_exists(VentaModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testRegistrarUsaTransaccion(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains('beginTransaction', $content, 'registrar() debe usar transacciones DB');
        $this->assertContains('rollBack', $content, 'registrar() debe hacer rollBack en caso de error');
        $this->assertContains('commit', $content, 'registrar() debe confirmar la transacción con commit');
    }

    public function testRegistrarDescuentaStockSoloSiControlaStock(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains('controla_stock=1', $content,
            'registrar() no debe descontar stock de productos que no lo controlan (ej. café)');
        $this->assertContains('stock_actual=stock_actual-:c', $content,
            'registrar() debe descontar stock al vender');
    }

    public function testAnularRevierteStock(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains('stock_actual=stock_actual+:c', $content,
            'anular() debe devolver el stock descontado');
        $this->assertContains("estado' => 'anulada'", $content,
            'anular() debe marcar la venta como anulada');
    }

    public function testRegistrarPropagaMetodoPagoACajaMovimientos(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        // Debe existir un INSERT a caja_movimientos que incluya la columna metodo_pago
        $this->assertMatchesRegex(
            '/INSERT INTO\s+caja_movimientos[\s\S]{0,200}metodo_pago/i',
            $content,
            'registrar() debe propagar metodo_pago al INSERT de caja_movimientos'
        );
        $this->assertContains("cabecera['metodo_pago'] ?? 'efectivo'", $content,
            'Si no se especifica método de pago, debe usar efectivo por defecto');
    }

    public function testRegistrarSoloGeneraMovimientoDeCajaSiHayTurno(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains("!empty(\$cabecera['turno_id'])", $content,
            'Solo debe registrar movimiento de caja si la venta tiene turno abierto asociado');
    }

    public function testIvaSoloSeDiscriminaParaResponsableInscripto(): void
    {
        // La lógica de IVA condicional vive en el Controller (guardar()), no en el Model.
        $content = file_get_contents(BASE_PATH . '/app/Controllers/VentasController.php');
        $this->assertContains('responsable_inscripto', $content,
            'El IVA solo debe discriminarse si el negocio es Responsable Inscripto');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertNotContains('$_GET', $content, 'VentaModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'VentaModel no debe usar $_POST directamente en queries');
    }

    public function testBuscarProductosUsaLikeConParametros(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
        $this->assertContains('LIKE :q', $content, 'buscarProductos() debe usar LIKE con parámetro nombrado');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/VentasController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/VentasController.php');
        // guardar, anular, previsualizar, importar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(4, $count,
            'VentasController debe validar CSRF en guardar/anular/previsualizar/importar');
    }

    public function testAnularRegistraAuditLog(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/VentasController.php');
        $this->assertContains('AuditLog::log', $content,
            'anular() debe dejar registro en el log de auditoría (acción irreversible sobre dinero)');
    }

    public function testTicketPublicoValidaTokenSeguro(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/VentasController.php');
        $this->assertContains('hash_equals', $content,
            'El ticket público (sin login) debe validar el token con hash_equals (evita timing attack)');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/ventas/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/ventas/pos.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/ventas/ver.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/ventas/ticket.php');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $ver = file_get_contents(BASE_PATH . '/app/Views/ventas/ver.php');
        $this->assertContains('e(', $ver, 'Vista ver.php debe usar helper e() para escape XSS');
    }

    public function testSchemaTieneColumnasEsperadas(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('total_con_iva', $schema, 'Schema de ventas debe tener total_con_iva');
        $this->assertContains('metodo_pago', $schema, 'Schema de ventas debe tener metodo_pago');
        $this->assertContains('facturar_afip', $schema, 'Schema de ventas debe tener facturar_afip');
        $this->assertContains('afip_cae', $schema, 'Schema de ventas debe tener afip_cae');
    }
}
