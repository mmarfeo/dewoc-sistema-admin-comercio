<?php
/**
 * Tests de protección CSRF
 */
class CsrfTest extends TestCase
{
    protected string $suite = 'Seguridad — CSRF';

    public function run(): void
    {
        $this->testCsrfClassExists();
        $this->testTokenUsesRandomBytes();
        $this->testVerifyUsesHashEquals();
        $this->testFieldOutputsHiddenInput();
        $this->testAllPostControllersCallCsrfCheck();
        $this->testTokenStoredinSession();
        $this->testCheckCallsVerify();
    }

    private function testCsrfClassExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/core/Csrf.php', 'core/Csrf.php existe');
    }

    private function testTokenUsesRandomBytes(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Csrf.php');
        $this->assertTrue(
            str_contains($content, 'random_bytes'),
            'Csrf::token() usa random_bytes() para generar el token'
        );
    }

    private function testVerifyUsesHashEquals(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Csrf.php');
        $this->assertTrue(
            str_contains($content, 'hash_equals'),
            'Csrf::verify() usa hash_equals() para comparación timing-safe'
        );
    }

    private function testFieldOutputsHiddenInput(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Csrf.php');
        $this->assertTrue(
            str_contains($content, 'type="hidden"') && str_contains($content, '_csrf_token'),
            'Csrf::field() genera input hidden con name="_csrf_token"'
        );
    }

    /** Todos los Controllers que manejan POST deben llamar Csrf::check() */
    private function testAllPostControllersCallCsrfCheck(): void
    {
        $controllers = glob(BASE_PATH . '/app/Controllers/*.php') ?: [];
        $missing     = [];

        foreach ($controllers as $file) {
            if (basename($file) === 'Controller.php') continue;
            $content = file_get_contents($file);
            // Si el controller tiene métodos que procesan POST (crear/actualizar/guardar/login/etc)
            $hasPostMethods = preg_match('/function\s+(crear|actualizar|guardar|login|anular|eliminar|abrir|cerrar)\s*\(/i', $content);
            if ($hasPostMethods && !str_contains($content, 'Csrf::check()')) {
                $missing[] = basename($file);
            }
        }

        $this->assertTrue(
            empty($missing),
            'Todos los controllers con acciones POST llaman Csrf::check()'
        );
        foreach ($missing as $m) {
            $this->assertTrue(false, "  CSRF faltante en: {$m}");
        }
    }

    private function testTokenStoredinSession(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Csrf.php');
        $this->assertTrue(
            str_contains($content, '$_SESSION'),
            'El token CSRF se almacena en $_SESSION'
        );
    }

    private function testCheckCallsVerify(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Csrf.php');
        $this->assertTrue(
            str_contains($content, 'self::verify()'),
            'Csrf::check() llama a self::verify() antes de continuar'
        );
    }
}
