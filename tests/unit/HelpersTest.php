<?php
/**
 * Tests unitarios de helpers.php
 */
class HelpersTest extends TestCase
{
    protected string $suite = 'Unit — Helpers';

    public function run(): void
    {
        require_once BASE_PATH . '/core/helpers.php';

        $this->testEscapeBasic();
        $this->testEscapeScript();
        $this->testEscapeQuotes();
        $this->testEscapeNull();
        $this->testSanitizeTrims();
        $this->testSanitizeStripsHtml();
        $this->testMonedaFormat();
        $this->testFechaFormat();
        $this->testFechaHoraFormat();
        $this->testUrlBuildsCorrectly();
        $this->testAssetBuildsCorrectly();
    }

    private function testEscapeBasic(): void
    {
        $this->assertEquals('&lt;script&gt;', e('<script>'), 'e() convierte < > a entidades HTML');
    }

    private function testEscapeScript(): void
    {
        $result = e('<script>alert("xss")</script>');
        $this->assertFalse(str_contains($result, '<script>'), 'e() elimina tags script del output');
    }

    private function testEscapeQuotes(): void
    {
        $this->assertEquals('&quot;hola&quot;', e('"hola"'), 'e() escapa comillas dobles');
        $this->assertEquals('&#039;hola&#039;', e("'hola'"), 'e() escapa comillas simples');
    }

    private function testEscapeNull(): void
    {
        $this->assertEquals('', e(''), 'e() con string vacío devuelve string vacío');
        $this->assertEquals('0', e(0), 'e() con 0 devuelve "0"');
    }

    private function testSanitizeTrims(): void
    {
        $this->assertEquals('hola', sanitize('  hola  '), 'sanitize() hace trim del espacio');
    }

    private function testSanitizeStripsHtml(): void
    {
        $this->assertEquals('hola', sanitize('<b>hola</b>'), 'sanitize() elimina tags HTML');
        $this->assertEquals('', sanitize('<script>alert(1)</script>'), 'sanitize() elimina script tags');
    }

    private function testMonedaFormat(): void
    {
        $result = moneda(1234.5);
        $this->assertTrue(str_contains($result, '$'), 'moneda() incluye el símbolo $');
        $this->assertTrue(str_contains($result, '1.234'), 'moneda() formatea miles con punto');
        $this->assertTrue(str_contains($result, ',50'), 'moneda() incluye decimales con coma');
    }

    private function testFechaFormat(): void
    {
        $result = fecha('2025-12-31');
        $this->assertEquals('31/12/2025', $result, 'fecha() convierte Y-m-d a d/m/Y');
    }

    private function testFechaHoraFormat(): void
    {
        $result = fechaHora('2025-12-31 14:30:00');
        $this->assertEquals('31/12/2025 14:30', $result, 'fechaHora() convierte a d/m/Y H:i');
    }

    private function testUrlBuildsCorrectly(): void
    {
        $result = url('dashboard');
        $this->assertTrue(str_contains($result, 'dashboard'), 'url() incluye el path dado');
        $this->assertTrue(str_contains($result, 'http'), 'url() incluye el protocolo');
    }

    private function testAssetBuildsCorrectly(): void
    {
        $result = asset('css/app.css');
        $this->assertTrue(str_contains($result, 'assets'), 'asset() incluye /assets/ en la URL');
        $this->assertTrue(str_contains($result, 'css/app.css'), 'asset() incluye el path dado');
    }
}
