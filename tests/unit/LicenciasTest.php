<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Licencias.php';

/**
 * Licencias de los módulos que se cobran aparte (ver docs/dewoc-cobros-checklist.md,
 * sección 5). No usa la base: firma con un par de claves generado para el test (nunca el
 * real) y verifica core/Licencias.php directamente.
 */
class LicenciasTest extends TestCase
{
    const INSTALACION = '3f2b8c1e-7d4a-4e6b-9c2d-1a2b3c4d5e6f';

    private mixed  $privada = null;
    private array  $claves  = [];

    public function __construct()
    {
        $this->privada = self::nuevaClave();
        if ($this->privada) {
            $this->claves = ['test' => openssl_pkey_get_details($this->privada)['key']];
        }
    }

    /**
     * Par ECDSA P-256 para el test. En el PHP de XAMPP para Windows openssl_pkey_new() falla
     * si no encuentra openssl.cnf, así que se prueba también con el que trae PHP.
     */
    private static function nuevaClave(): mixed
    {
        $opts = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $k = @openssl_pkey_new($opts);
        if ($k === false) {
            foreach ([getenv('OPENSSL_CONF'), dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf'] as $cnf) {
                if ($cnf && is_file($cnf) && ($k = @openssl_pkey_new($opts + ['config' => $cnf]))) break;
            }
        }
        while (openssl_error_string() !== false);
        return $k ?: null;
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'kid'            => 'test',
            'licencia_id'    => 'L20261102-TEST0001',
            'instalacion_id' => self::INSTALACION,
            'comercio'       => 'Heladería Serendipia',
            'modulo'         => 'dewoc_cobros',
            'dispositivos'   => 2,
            'desde'          => '2026-11-01',
            'hasta'          => '2026-12-31',
            'emitida_at'     => '2026-11-02T10:00:00-03:00',
        ];
    }

    private function verificar(string $licencia, string $modulo = 'dewoc_cobros', string $instalacion = self::INSTALACION): array
    {
        return Licencias::verificar($licencia, $instalacion, $modulo, $this->claves);
    }

    public function testSePuedeGenerarUnaClaveDePrueba(): void
    {
        $this->assertNotNull($this->privada, 'openssl_pkey_new() debe poder generar un par P-256 para los tests');
    }

    // ── Firma ────────────────────────────────────────────────────────────────

    public function testUnaLicenciaBienFirmadaSeAcepta(): void
    {
        if (!$this->privada) return;
        $lic = Licencias::firmar($this->datos(), $this->privada);

        $this->assertTrue(str_starts_with($lic, 'DWC1.'), 'El formato empieza con DWC1.');
        $v = $this->verificar($lic);
        $this->assertTrue($v['ok'], 'Una licencia bien firmada se acepta');
        $this->assertEquals(2, $v['datos']['dispositivos'] ?? null, 'Trae los celulares incluidos');
    }

    public function testPartidaEnVariasLineasSeAceptaIgual(): void
    {
        if (!$this->privada) return;
        $lic = Licencias::firmar($this->datos(), $this->privada);
        $this->assertTrue($this->verificar(chunk_split($lic, 40, "\r\n") . '  ')['ok'],
            'Pegada desde WhatsApp, con saltos de línea, se acepta');
    }

    public function testUnCaracterCambiadoSeRechaza(): void
    {
        if (!$this->privada) return;
        $lic = Licencias::firmar($this->datos(), $this->privada);
        [$pre, $json, $firma] = explode('.', $lic);

        // Mismo JSON con 1 celular en vez de 2: base64 válido, firma que ya no cierra.
        $jsonTrucho = Licencias::b64urlEncode(str_replace('"dispositivos":2', '"dispositivos":9', Licencias::b64urlDecode($json)));
        $this->assertFalse($this->verificar("{$pre}.{$jsonTrucho}.{$firma}")['ok'],
            'Con los datos cambiados (9 celulares) se rechaza');

        $otro = fn(string $s, int $i) => substr_replace($s, $s[$i] === 'A' ? 'B' : 'A', $i, 1);
        $this->assertFalse($this->verificar("{$pre}." . $otro($json, 10) . ".{$firma}")['ok'],
            'Con un carácter cambiado en el JSON se rechaza');
        $this->assertFalse($this->verificar("{$pre}.{$json}." . $otro($firma, 20))['ok'],
            'Con un carácter cambiado en la firma se rechaza');
    }

    public function testTextosQueNoSonLicenciaSeRechazan(): void
    {
        foreach (['', 'hola', 'DWC1.abc', 'DWC2.e30.e30', 'DWC1.!!!.e30'] as $txt) {
            $v = Licencias::verificar($txt, self::INSTALACION, 'dewoc_cobros', $this->claves);
            $this->assertEquals('formato_invalido', $v['error'] ?? null, "'{$txt}' es formato_invalido");
        }
    }

    public function testOtraInstalacionOtroModuloYKidDesconocido(): void
    {
        if (!$this->privada) return;
        $lic = Licencias::firmar($this->datos(), $this->privada);

        $this->assertEquals('otra_instalacion', $this->verificar($lic, 'dewoc_cobros', '00000000-0000-4000-8000-000000000000')['error'] ?? null,
            'La licencia de otra instalación se rechaza');
        $this->assertEquals('otro_modulo', $this->verificar($lic, 'otro_modulo')['error'] ?? null,
            'La licencia de otro módulo se rechaza');

        $sinKid = Licencias::firmar($this->datos(['kid' => 'k99']), $this->privada);
        $this->assertEquals('kid_desconocido', $this->verificar($sinKid)['error'] ?? null,
            'La firmada con un kid desconocido se rechaza');
    }

    public function testDatosInvalidosAunqueEsteFirmada(): void
    {
        if (!$this->privada) return;
        foreach ([
            'sin celulares'        => ['dispositivos' => 0],
            'fecha imposible'      => ['hasta' => '2026-02-30'],
            'desde después de hasta' => ['desde' => '2027-01-01'],
        ] as $caso => $cambio) {
            $lic = Licencias::firmar($this->datos($cambio), $this->privada);
            $this->assertEquals('datos_invalidos', $this->verificar($lic)['error'] ?? null, "Se rechaza: {$caso}");
        }
    }

    public function testLaClavePublicaRealEsUnaClaveValida(): void
    {
        $claves = Licencias::clavesPublicas();
        $this->assertNotEmpty($claves, 'config/licencias_claves_publicas.php debe tener al menos una clave');
        foreach ($claves as $kid => $pem) {
            $this->assertTrue(openssl_pkey_get_public($pem) !== false, "La clave pública '{$kid}' se puede leer");
        }
        while (openssl_error_string() !== false);
    }

    // ── Estados según la fecha ───────────────────────────────────────────────

    public function testEstadosSegunLaFecha(): void
    {
        $d = $this->datos(['hasta' => '2026-12-31']);
        foreach ([
            '2026-12-23' => 'activa',      // faltan 8 días
            '2026-12-24' => 'por_vencer',  // faltan 7
            '2026-12-31' => 'por_vencer',  // último día
            '2027-01-01' => 'gracia',      // 1 día después
            '2027-01-07' => 'gracia',      // 7 días después
            '2027-01-08' => 'vencida',     // pasada la gracia
        ] as $hoy => $esperado) {
            $this->assertEquals($esperado, Licencias::estado($d, $hoy)['estado'], "{$hoy} → {$esperado}");
        }
        $this->assertEquals(-1, Licencias::estado($d, '2027-01-01')['dias_restantes'], 'dias_restantes es negativo después de hasta');
        $this->assertEquals('sin_licencia', Licencias::estado(null)['estado'], 'Sin licencia → sin_licencia');
    }

    public function testSoloVencidaYSinLicenciaDeshabilitan(): void
    {
        $this->assertEquals(['activa', 'por_vencer', 'gracia'], Licencias::ESTADOS_HABILITADOS,
            'moduloHabilitado() es falso solo en vencida y sin_licencia');
    }

    public function testMandaLaEmitidaMasRecientemente(): void
    {
        $vieja = $this->datos(['licencia_id' => 'A', 'dispositivos' => 3, 'emitida_at' => '2026-10-01T10:00:00-03:00']);
        $nueva = $this->datos(['licencia_id' => 'B', 'dispositivos' => 1, 'emitida_at' => '2026-11-01T10:00:00-03:00']);
        $this->assertEquals('B', Licencias::elegirVigente([$vieja, $nueva], '2026-11-02')['licencia_id'] ?? null,
            'Manda la emitida más recientemente, aunque traiga menos celulares');

        $futura = $this->datos(['licencia_id' => 'C', 'desde' => '2026-12-01', 'emitida_at' => '2026-11-02T09:00:00-03:00']);
        $this->assertEquals('B', Licencias::elegirVigente([$vieja, $nueva, $futura], '2026-11-02')['licencia_id'] ?? null,
            'Una renovación que todavía no empezó no le gana a la vigente');
        $this->assertEquals('C', Licencias::elegirVigente([$vieja, $nueva, $futura], '2026-12-01')['licencia_id'] ?? null,
            'Cuando llega su desde, manda la renovación');
        $this->assertNull(Licencias::elegirVigente([], '2026-11-02'), 'Sin licencias → null');
    }

    // ── Lo que no se puede perder en un refactor ────────────────────────────

    public function testNingunArchivoDelRepositorioTieneUnaClavePrivada(): void
    {
        // Un bloque PEM real: la marca seguida del cuerpo en base64. Solo la marca no alcanza,
        // porque la nombran la documentación y core/WebPush.php (que arma el PEM en memoria).
        $pem = '/-----BEGIN (EC |ENCRYPTED )?PRIVATE KEY-----\s*[A-Za-z0-9+\/=]{40,}/';

        exec('git -C ' . escapeshellarg(BASE_PATH) . ' ls-files --cached --others --exclude-standard', $archivos, $rc);
        $this->assertEquals(0, $rc, 'git ls-files debe poder listar el repositorio');

        $encontrados = [];
        foreach ($archivos as $rel) {
            if (preg_match('/\.(jpe?g|png|gif|webp|pdf|xlsx?|zip|mp4|ico|woff2?|ttf)$/i', $rel)) continue;
            $ruta = BASE_PATH . '/' . $rel;
            if (!is_file($ruta) || filesize($ruta) > 5_000_000) continue;
            if (preg_match($pem, file_get_contents($ruta))) $encontrados[] = $rel;
        }
        $this->assertEmpty($encontrados, 'Ningún archivo del repositorio contiene una clave privada');
    }

    public function testLaTablaNoTieneColumnaActiva(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/licencias_migration.sql');
        $this->assertNotContains('`activa`', $sql, 'El estado sale siempre de la firma: editar la base no habilita nada');
        $this->assertContains('UNIQUE KEY `uq_licencia_id`', $sql, 'licencia_id es UNIQUE');
    }

    public function testElScriptDeEmisionSoloCorrePorConsola(): void
    {
        $script = file_get_contents(BASE_PATH . '/herramientas/emitir_licencia.php');
        $this->assertContains("PHP_SAPI !== 'cli'", $script, 'emitir_licencia.php se niega si no es CLI');
        $this->assertMatchesRegex('#RewriteRule \^\([^)]*herramientas[^)]*\)/ - \[F,L\]#',
            file_get_contents(BASE_PATH . '/.htaccess'), 'herramientas/ está bloqueada en el .htaccess');
    }
}
