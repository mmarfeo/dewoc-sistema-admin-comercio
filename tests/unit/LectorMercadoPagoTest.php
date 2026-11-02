<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../app/Models/CobrosLectores/LectorMercadoPago.php';

/**
 * Lector de Mercado Pago de Dewoc Cobros (ver docs/dewoc-cobros-checklist.md, sección 5).
 * Un caso por formato. PRIMERA VERSIÓN: cuando lleguen las capturas reales del modo captura,
 * agregar acá cada formato real (con los nombres cambiados: el archivo exportado tiene
 * nombres de clientes y no se commitea).
 */
class LectorMercadoPagoTest extends TestCase
{
    private LectorMercadoPago $lector;

    public function __construct()
    {
        $this->lector = new LectorMercadoPago();
    }

    private function caso(string $titulo, string $texto, string $resultado, ?float $monto = null, ?string $nombre = null): void
    {
        $r = $this->lector->leer($titulo, $texto, null);
        $this->assertEquals($resultado, $r['resultado'], "\"{$texto}\" → {$resultado}");
        if ($resultado === 'cobro') {
            $this->assertEquals($monto, $r['monto'], "\"{$texto}\" → monto " . var_export($monto, true));
            $this->assertEquals($nombre, $r['nombre'], "\"{$texto}\" → nombre " . var_export($nombre, true));
        }
    }

    public function testMontosArgentinos(): void
    {
        foreach ([
            '$ 12.500,50' => 12500.50, '$ 500' => 500.0, '$ 1.000.000,00' => 1000000.0,
            '$10' => 10.0, '$ 35,2' => 35.20, 'te envió $ 1.500 hoy' => 1500.0,
        ] as $txt => $esperado) {
            $this->assertEquals($esperado, LectorCobro::montoArgentino($txt), "{$txt} → {$esperado}");
        }
        $this->assertNull(LectorCobro::montoArgentino('Te envió dinero'), 'Sin monto → null');
    }

    public function testCobros(): void
    {
        // Primera muestra real (2026-11-02), con el formato del ejemplo del plan.
        $this->caso('Recibiste una transferencia', 'Daniela te envió $ 10', 'cobro', 10.0, 'Daniela');
        $this->caso('Recibiste $ 10', 'Recibiste $ 10 de Daniela', 'cobro', 10.0, 'Daniela');
        $this->caso('¡Recibiste dinero!', 'Juan Pérez te transfirió $ 12.500,50', 'cobro', 12500.50, 'Juan Pérez');
        $this->caso('Recibiste una transferencia', 'María de los Ángeles Gómez te envió $ 1.000.000,00', 'cobro', 1000000.0, 'María de los Ángeles Gómez');
        $this->caso('Recibiste un pago', 'Te pagaron $ 2.500 con QR', 'cobro', 2500.0, null);
    }

    public function testFormatoRealDeTransferenciaEntreCuentasDeMercadoPago(): void
    {
        // Así llega en Android (2026-11-02): el monto en el título y el nombre en el texto, que
        // empieza con "De" en mayúscula y termina con otro "de" ("de Mercado Pago").
        $this->caso('Recibiste $ 1', 'De Nombre Segundo Apellido desde su cuenta de Mercado Pago.', 'cobro', 1.0, 'Nombre Segundo Apellido');
        $this->caso('Recibiste $ 10', 'De María de los Ángeles Gómez desde su cuenta de Mercado Pago.', 'cobro', 10.0, 'María de los Ángeles Gómez');
        $this->caso('Recibiste $ 12.500,50', 'De Juan Pérez desde su cuenta de Mercado Pago.', 'cobro', 12500.50, 'Juan Pérez');
        $this->caso('Tenés una factura que vence el 4/nov', 'Ya podés agendar o hacer tu pago de $ 100.690,49 a Edesur.', 'descartado');
    }

    public function testCobroConMontoOcultoSeAvisaIgual(): void
    {
        $this->caso('Recibiste una transferencia', 'Daniela te envió dinero', 'cobro', null, 'Daniela');
    }

    public function testLoQueNoEsUnCobroSeDescarta(): void
    {
        $this->caso('Pagaste $ 3.000', 'Pagaste $ 3.000 en Kiosco Pepe', 'descartado');
        $this->caso('Transferiste $ 5.000', 'Transferiste $ 5.000 a Juan Pérez', 'descartado');
        $this->caso('¡Aprovechá!', 'Hasta 20% off en tus compras', 'descartado');
        $this->caso('Tus rendimientos', 'Tu dinero rindió $ 35,20', 'descartado');
        $this->caso('¡Recibiste un cashback!', 'Recibiste $ 500 de cashback', 'descartado');
    }

    public function testLoQueNoConoceQuedaNoReconocido(): void
    {
        $this->caso('Mercado Pago', 'Tenés una actividad nueva', 'no_reconocido');
    }

    public function testElLectorSeEligePorClave(): void
    {
        $this->assertTrue(LectorCobro::para('mercadopago') instanceof LectorMercadoPago, "'mercadopago' → LectorMercadoPago");
        $this->assertNull(LectorCobro::para('no_existe'), 'Clave desconocida → sin lector');
        $this->assertNull(LectorCobro::para(null), 'Paquete sin lector → sin lector');
    }
}
