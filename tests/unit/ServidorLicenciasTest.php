<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Licencias.php';
require_once __DIR__ . '/../../core/ServidorLicencias.php';
require_once __DIR__ . '/../../app/Models/LicenciaModel.php';

/**
 * Licencias automáticas (decisión C22, etapa 1B): qué decide el servidor ante cada solicitud,
 * qué emite y cuándo renueva cada Dewoc. No usa la base: firma con un par de claves generado
 * para el test (nunca el real).
 */
class ServidorLicenciasTest extends TestCase
{
    const INSTALACION = '3f2b8c1e-7d4a-4e6b-9c2d-1a2b3c4d5e6f';
    const HOY = '2026-11-02';

    private mixed $privada = null;
    private array $claves  = [];

    public function __construct()
    {
        $opts = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $k = @openssl_pkey_new($opts);
        if ($k === false) {
            foreach ([getenv('OPENSSL_CONF'), dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf'] as $cnf) {
                if ($cnf && is_file($cnf) && ($k = @openssl_pkey_new($opts + ['config' => $cnf]))) break;
            }
        }
        while (openssl_error_string() !== false);
        if ($k) {
            $this->privada = $k;
            $this->claves  = ['test' => openssl_pkey_get_details($k)['key']];
        }
    }

    private function cliente(array $cambios = []): array
    {
        return $cambios + ['estado' => 'activo', 'ultima_licencia' => 'DWC1.x.y', 'ultima_hasta' => '2026-11-30'];
    }

    // ── Qué decide el servidor ───────────────────────────────────────────────

    public function testUnComercioNuevoRecibeUnaLicenciaNueva(): void
    {
        $this->assertEquals('emitir', ServidorLicencias::decidir(null, self::HOY));
        $this->assertEquals('emitir', ServidorLicencias::decidir($this->cliente(['ultima_licencia' => null, 'ultima_hasta' => null]), self::HOY));
    }

    public function testSiLaUltimaTodaviaDuraSeDevuelveLaMisma(): void
    {
        // Vence en 28 días: no se emite una por cada vez que el Dewoc pregunta.
        $this->assertEquals('reutilizar', ServidorLicencias::decidir($this->cliente(), self::HOY));
    }

    public function testCercaDelVencimientoSeEmiteLaRenovacion(): void
    {
        // Desde 7 días antes (cuando el Dewoc la ve "por vencer") ya se emite la nueva.
        $this->assertEquals('emitir', ServidorLicencias::decidir($this->cliente(['ultima_hasta' => '2026-11-09']), self::HOY));
        $this->assertEquals('reutilizar', ServidorLicencias::decidir($this->cliente(['ultima_hasta' => '2026-11-10']), self::HOY));
        $this->assertEquals('emitir', ServidorLicencias::decidir($this->cliente(['ultima_hasta' => '2026-10-15']), self::HOY));
    }

    public function testDadoDeBajaNoSeLeEmiteNada(): void
    {
        $this->assertEquals('baja', ServidorLicencias::decidir($this->cliente(['estado' => 'baja']), self::HOY));
        $this->assertEquals('baja', ServidorLicencias::decidir($this->cliente(['estado' => 'baja', 'ultima_hasta' => '2026-10-01']), self::HOY));
    }

    public function testValidaLaSolicitud(): void
    {
        $this->assertNull(ServidorLicencias::validarSolicitud(self::INSTALACION, 'dewoc_cobros', 'Heladería Serendipia'));
        $this->assertEquals('instalacion_invalida', ServidorLicencias::validarSolicitud('no-es-un-uuid', 'dewoc_cobros', 'X'));
        $this->assertEquals('modulo_invalido', ServidorLicencias::validarSolicitud(self::INSTALACION, 'otro_modulo', 'X'));
        $this->assertEquals('comercio_invalido', ServidorLicencias::validarSolicitud(self::INSTALACION, 'dewoc_cobros', ''));
        $this->assertEquals('comercio_invalido', ServidorLicencias::validarSolicitud(self::INSTALACION, 'dewoc_cobros', str_repeat('a', 151)));
    }

    // ── Qué emite ────────────────────────────────────────────────────────────

    public function testLaLicenciaNuevaEsDe30DiasDesdeHoyYVerifica(): void
    {
        $d = ServidorLicencias::datosNuevaLicencia(strtoupper(self::INSTALACION), 'dewoc_cobros', 'Heladería X', 2, self::HOY, 'test');
        $this->assertEquals(self::HOY, $d['desde']);
        $this->assertEquals('2026-12-01', $d['hasta'], '30 días contando hoy');
        $this->assertEquals(self::INSTALACION, $d['instalacion_id'], 'el UUID se guarda en minúsculas');
        $this->assertEquals(2, $d['dispositivos']);
        $this->assertMatchesRegex('/^A20261102-[0-9A-F]{8}$/', $d['licencia_id']);

        if (!$this->privada) return;
        $v = Licencias::verificar(Licencias::firmar($d, $this->privada), self::INSTALACION, 'dewoc_cobros', $this->claves);
        $this->assertTrue($v['ok'], 'la licencia emitida tiene que verificar');
        $this->assertEquals('activa', Licencias::estado($v['datos'], self::HOY)['estado']);
    }

    public function testNuncaSeEmiteConCeroCelulares(): void
    {
        $d = ServidorLicencias::datosNuevaLicencia(self::INSTALACION, 'dewoc_cobros', 'X', 0, self::HOY, 'test');
        $this->assertEquals(1, $d['dispositivos']);
    }

    public function testSinConfiguracionNoEsServidor(): void
    {
        $antes = getenv('LICENCIAS_SERVIDOR');
        putenv('LICENCIAS_SERVIDOR=0');
        $this->assertFalse(ServidorLicencias::habilitado());
        putenv($antes === false ? 'LICENCIAS_SERVIDOR' : "LICENCIAS_SERVIDOR={$antes}");
    }

    // ── Cuándo renueva cada Dewoc ────────────────────────────────────────────

    public function testRenuevaSoloSiYaSeContratoYEstaPorVencer(): void
    {
        $this->assertFalse(LicenciaModel::necesitaRenovar('activa', true));
        $this->assertTrue(LicenciaModel::necesitaRenovar('por_vencer', true));
        $this->assertTrue(LicenciaModel::necesitaRenovar('gracia', true));
        $this->assertTrue(LicenciaModel::necesitaRenovar('vencida', true));
    }

    public function testNuncaContrataPorPrimeraVezSolo(): void
    {
        foreach (['sin_licencia', 'por_vencer', 'vencida'] as $estado) {
            $this->assertFalse(LicenciaModel::necesitaRenovar($estado, false), "{$estado} sin contratar");
        }
    }

    public function testLaClavePrivadaNoEstaEnElRepositorio(): void
    {
        $ruta = (string)getenv('LICENCIAS_CLAVE_PRIVADA');
        if ($ruta === '') return;
        $repo = strtolower(str_replace('\\', '/', BASE_PATH));
        $this->assertFalse(str_starts_with(strtolower(str_replace('\\', '/', $ruta)), $repo), 'LICENCIAS_CLAVE_PRIVADA tiene que estar fuera del proyecto');
    }
}
