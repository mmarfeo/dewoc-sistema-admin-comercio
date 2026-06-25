<?php
/**
 * Tests unitarios de Session.php
 */
class SessionTest extends TestCase
{
    protected string $suite = 'Unit — Session';

    public function run(): void
    {
        require_once BASE_PATH . '/core/Session.php';

        $this->testSessionClassExists();
        $this->testHasFlashMethod();
        $this->testHasDestroyMethod();
        $this->testHasRegenerateMethod();
        $this->testHasUserMethod();
        $this->testHasIsLoggedInMethod();
        $this->testSameSiteCookieConfig();
        $this->testSessionLifetimeFromConfig();
        $this->testFlashDeletesAfterRead();
    }

    private function testSessionClassExists(): void
    {
        $this->assertTrue(class_exists('Session'), 'Clase Session existe y se cargó');
    }

    private function testHasFlashMethod(): void
    {
        $this->assertTrue(method_exists('Session', 'flash'), 'Session::flash() existe');
    }

    private function testHasDestroyMethod(): void
    {
        $this->assertTrue(method_exists('Session', 'destroy'), 'Session::destroy() existe');
    }

    private function testHasRegenerateMethod(): void
    {
        $this->assertTrue(method_exists('Session', 'regenerate'), 'Session::regenerate() existe');
    }

    private function testHasUserMethod(): void
    {
        $this->assertTrue(method_exists('Session', 'user'), 'Session::user() existe');
    }

    private function testHasIsLoggedInMethod(): void
    {
        $this->assertTrue(method_exists('Session', 'isLoggedIn'), 'Session::isLoggedIn() existe');
    }

    private function testSameSiteCookieConfig(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Session.php');
        $this->assertTrue(
            str_contains($content, "'samesite' => 'Lax'"),
            "Cookie de sesión usa SameSite=Lax (protección CSRF básica)"
        );
    }

    private function testSessionLifetimeFromConfig(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Session.php');
        $this->assertTrue(
            str_contains($content, 'SESSION_LIFETIME'),
            'Session::start() usa la constante SESSION_LIFETIME de config'
        );
    }

    private function testFlashDeletesAfterRead(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Session.php');
        // Verificar que la implementación de flash() borra la clave después de leerla
        $this->assertTrue(
            str_contains($content, "unset(\$_SESSION['_flash']"),
            'Session::flash() borra el mensaje de sesión después de leerlo (mensajes one-time)'
        );
    }
}
