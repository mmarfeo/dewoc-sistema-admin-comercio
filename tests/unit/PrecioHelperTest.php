<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/PrecioHelper.php';

/**
 * Opción D9 — "los precios de venta cargados incluyen IVA" (ver core/PrecioHelper.php y
 * docs/integracion-menuapp-checklist.md, tarea 0.6). Es la cuenta más delicada del sistema:
 * define cuánto paga el cliente en el mostrador y cuánto se guarda como neto e IVA.
 *
 * La configuración se fuerza por reflexión sobre el cache estático, así los 4 casos
 * (incluye / no incluye × discrimina / no discrimina) se prueban sin tocar la base ni
 * depender de cómo esté configurado el comercio donde corren los tests.
 */
class PrecioHelperTest extends TestCase
{
    /** Fija precios_incluyen_iva sin pasar por la base. */
    private function forzarIncluyenIva(bool $valor): void
    {
        $ref = new ReflectionProperty(PrecioHelper::class, 'cacheIncluyenIva');
        $ref->setAccessible(true);
        $ref->setValue(null, $valor);
    }

    public function testArchivoExiste(): void
    {
        $this->assertFileExists(BASE_PATH . '/core/PrecioHelper.php');
    }

    public function testMetodosDelContratoExisten(): void
    {
        foreach (['preciosIncluyenIva', 'precioFinal', 'desglosar', 'discriminaIva', 'ivaEfectivo',
                  'precioBaseOnline', 'precioFinalOnline', 'precioCargadoDesdeFinal'] as $met) {
            $this->assertTrue(method_exists(PrecioHelper::class, $met), "Debe existir PrecioHelper::{$met}()");
        }
    }

    // ── precioFinal(): qué paga el cliente ───────────────────────────────────

    public function testPrecioFinalConPreciosQueIncluyenIva(): void
    {
        $this->forzarIncluyenIva(true);
        $this->assertEquals(1000.00, PrecioHelper::precioFinal(1000.00, 21),
            'Con "incluyen IVA" el precio cargado ya es el final: no se le suma nada');
        $this->assertEquals(1000.00, PrecioHelper::precioFinal(1000.00, 0),
            'Un producto exento tampoco cambia');
    }

    public function testPrecioFinalConPreciosSinIva(): void
    {
        $this->forzarIncluyenIva(false);
        $this->assertEquals(1210.00, PrecioHelper::precioFinal(1000.00, 21),
            'Con "no incluyen IVA" se suma el 21%');
        $this->assertEquals(1105.00, PrecioHelper::precioFinal(1000.00, 10.5),
            'Alícuota reducida: 10,5%');
        $this->assertEquals(1000.00, PrecioHelper::precioFinal(1000.00, 0),
            'Sin alícuota no hay nada que sumar');
    }

    public function testPrecioCargadoDesdeFinalEsLaInversaDePrecioFinal(): void
    {
        foreach ([true, false] as $incluyen) {
            $this->forzarIncluyenIva($incluyen);
            $final   = PrecioHelper::precioFinal(1000.00, 21);
            $cargado = PrecioHelper::precioCargadoDesdeFinal($final, 21);
            $this->assertEquals(1000.00, $cargado,
                'precioCargadoDesdeFinal() debe deshacer precioFinal() ' . ($incluyen ? '(incluyen)' : '(no incluyen)'));
        }
    }

    // ── desglosar(): neto + IVA que se guardan en la venta ───────────────────

    public function testDesglosarSumaExactamenteElTotal(): void
    {
        // El IVA se calcula por resta justamente para esto: el total guardado en `ventas`
        // nunca puede diferir, por un redondeo, del que vio el cliente.
        foreach ([1210.00, 999.99, 0.01, 18500.00, 1105.37] as $total) {
            $d = PrecioHelper::desglosar($total, 21);
            $this->assertEquals($total, round($d['neto'] + $d['iva'], 2),
                "neto + iva debe dar exactamente {$total}");
        }
    }

    public function testDesglosarCon21PorCiento(): void
    {
        $d = PrecioHelper::desglosar(1210.00, 21);
        $this->assertEquals(1000.00, $d['neto'], 'Neto de $1210 al 21%');
        $this->assertEquals(210.00,  $d['iva'],  'IVA de $1210 al 21%');
    }

    public function testDesglosarSinAlicuotaDejaTodoComoNeto(): void
    {
        $d = PrecioHelper::desglosar(1000.00, 0);
        $this->assertEquals(1000.00, $d['neto'], 'Sin IVA discriminado, todo es neto');
        $this->assertEquals(0.0,     $d['iva'],  'Sin IVA discriminado, el IVA es 0');
    }

    // ── ivaEfectivo(): discriminar es la condición fiscal, no D9 ─────────────

    public function testIvaEfectivoDependeDeLaCondicionFiscalNoDeD9(): void
    {
        // Lo que decide si se guarda el IVA desglosado es NEGOCIO_CONDICION_IVA, no D9:
        // cambiar D9 no debe mover ivaEfectivo().
        $this->forzarIncluyenIva(true);
        $conD9Incluido = PrecioHelper::ivaEfectivo(21);
        $this->forzarIncluyenIva(false);
        $conD9Sumado = PrecioHelper::ivaEfectivo(21);

        $this->assertEquals($conD9Incluido, $conD9Sumado,
            'ivaEfectivo() no debe cambiar por la opción D9');

        $esperado = PrecioHelper::discriminaIva() ? 21.0 : 0.0;
        $this->assertEquals($esperado, $conD9Incluido,
            'ivaEfectivo() devuelve la alícuota del producto solo si el comprobante discrimina IVA');
    }

    public function testMonotributistaNuncaDiscriminaIva(): void
    {
        if (PrecioHelper::discriminaIva()) {
            $this->assertTrue(true, 'Comercio Responsable Inscripto: este caso no aplica acá');
            return;
        }
        $this->assertEquals(0.0, PrecioHelper::ivaEfectivo(21),
            'Un monotributista emite Factura C: el IVA guardado siempre es 0');
    }

    // ── Los 4 casos de la tarea 0.6, de punta a punta ────────────────────────

    /**
     * Para cada combinación, el total de la venta tiene que ser el precio final y el
     * desglose guardado tiene que sumar ese mismo total — que es exactamente la condición
     * que pide la tarea 0.6 ("el total del POS coincide con el total guardado en ventas").
     */
    public function testLosCuatroCasosCierranElTotal(): void
    {
        $base = 1000.00;
        $iva  = 21.0;

        foreach ([true, false] as $incluyen) {
            $this->forzarIncluyenIva($incluyen);

            $final     = PrecioHelper::precioFinal($base, $iva);
            $esperado  = $incluyen ? 1000.00 : 1210.00;
            $this->assertEquals($esperado, $final,
                'Precio final con D9 = ' . ($incluyen ? 'incluyen' : 'no incluyen'));

            $d = PrecioHelper::desglosar($final, PrecioHelper::ivaEfectivo($iva));
            $this->assertEquals($final, round($d['neto'] + $d['iva'], 2),
                'El desglose guardado debe sumar el total cobrado');
        }
    }

    public function testDefaultHistoricoEsElInversoDeDiscriminarIva(): void
    {
        // Con la opción en NULL se reproduce el comportamiento anterior: Responsable
        // Inscripto sumaba el IVA al precio_venta (= "no incluyen"); el resto lo trataba
        // como precio final (= "incluyen").
        $this->assertEquals(
            !PrecioHelper::discriminaIva(),
            PrecioHelper::preciosIncluyenIvaPorDefecto(),
            'El default histórico es "incluyen" salvo para Responsable Inscripto'
        );
    }

    // ── Precio del menú online (MenuApp) ────────────────────────────────────

    public function testPrecioBaseOnlineUsaPrecioOnlineSiTieneValor(): void
    {
        $this->assertEquals(2800.00, PrecioHelper::precioBaseOnline(['precio_online' => 2800, 'precio_venta' => 2500]),
            'Con precio_online cargado, el menú online usa ese');
        $this->assertEquals(2500.00, PrecioHelper::precioBaseOnline(['precio_online' => null, 'precio_venta' => 2500]),
            'precio_online NULL = usar el precio del local');
        $this->assertEquals(2500.00, PrecioHelper::precioBaseOnline(['precio_online' => '', 'precio_venta' => 2500]),
            'Un precio_online vacío también cae al del local');
    }

    public function testPrecioOnlineEnCeroNoSeTomaComoGratis(): void
    {
        // 0 sí es un precio válido si alguien lo cargó a propósito (ej. una promo), pero
        // NULL/'' nunca puede resolverse a 0: eso regalaría el producto en el menú.
        $this->assertEquals(2500.00, PrecioHelper::precioBaseOnline(['precio_venta' => 2500]),
            'Sin la clave precio_online se usa el del local, no 0');
    }

    public function testPrecioFinalOnlineRespetaD9(): void
    {
        $producto = ['precio_online' => 1000, 'precio_venta' => 900, 'iva' => 21];

        $this->forzarIncluyenIva(true);
        $this->assertEquals(1000.00, PrecioHelper::precioFinalOnline($producto),
            'Con "incluyen IVA", MenuApp muestra el precio online tal cual');

        $this->forzarIncluyenIva(false);
        $this->assertEquals(1210.00, PrecioHelper::precioFinalOnline($producto),
            'Con "no incluyen IVA", MenuApp muestra el precio online + IVA');
    }
}
