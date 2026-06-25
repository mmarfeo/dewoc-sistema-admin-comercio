<?php
/**
 * Tests de seguridad HTTP: cabeceras, sesiones, acceso a directorios
 */
class HttpSecurityTest extends TestCase
{
    protected string $suite = 'Seguridad — HTTP Headers y Sesiones';

    public function run(): void
    {
        $this->testHtaccessExists();
        $this->testHtaccessBlocksSensitiveDirs();
        $this->testHtaccessHasXFrameOptions();
        $this->testHtaccessHasXContentType();
        $this->testHtaccessHasCSP();
        $this->testHtaccessHasXXssProtection();
        $this->testHtaccessHasReferrerPolicy();
        $this->testSessionUsesHttpOnly();
        $this->testSessionUsesSecureName();
        $this->testSessionRegeneratesAfterLogin();
        $this->testPasswordUsesBcrypt();
        $this->testNoHardcodedCredentials();
        $this->testSensitiveFilesNotInWebRoot();
        $this->testLogsNotAccessible();
    }

    private function testHtaccessExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/.htaccess', '.htaccess existe en la raíz del proyecto');
    }

    private function testHtaccessBlocksSensitiveDirs(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'config') && str_contains($content, '[F,L]'),
            '.htaccess bloquea acceso directo a /config y otros dirs sensibles'
        );
    }

    private function testHtaccessHasXFrameOptions(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'X-Frame-Options'),
            '.htaccess incluye cabecera X-Frame-Options (anti-clickjacking)'
        );
    }

    private function testHtaccessHasXContentType(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'X-Content-Type-Options'),
            '.htaccess incluye X-Content-Type-Options: nosniff'
        );
    }

    private function testHtaccessHasCSP(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'Content-Security-Policy'),
            '.htaccess incluye cabecera Content-Security-Policy'
        );
    }

    private function testHtaccessHasXXssProtection(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'X-XSS-Protection'),
            '.htaccess incluye X-XSS-Protection'
        );
    }

    private function testHtaccessHasReferrerPolicy(): void
    {
        $content = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($content, 'Referrer-Policy'),
            '.htaccess incluye Referrer-Policy'
        );
    }

    private function testSessionUsesHttpOnly(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Session.php');
        $this->assertTrue(
            str_contains($content, "'httponly' => true"),
            'Session configura cookie con httponly=true (protección XSS)'
        );
    }

    private function testSessionUsesSecureName(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/Session.php');
        $this->assertTrue(
            str_contains($content, 'session_name(SESSION_NAME)'),
            'Session usa nombre personalizado (no el default "PHPSESSID")'
        );
    }

    private function testSessionRegeneratesAfterLogin(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuthController.php');
        $this->assertTrue(
            str_contains($content, 'Session::regenerate()'),
            'AuthController regenera el ID de sesión tras login exitoso (previene session fixation)'
        );
    }

    private function testPasswordUsesBcrypt(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuthController.php');
        $this->assertTrue(
            str_contains($content, 'password_verify'),
            'AuthController usa password_verify() para verificar contraseñas'
        );

        // Verificar que seed.sql usa hash real, no MD5/SHA1
        $seed = file_get_contents(BASE_PATH . '/database/seed.sql');
        $this->assertFalse(
            (bool)preg_match('/MD5\(|SHA1\(|SHA\(/i', $seed),
            'seed.sql no usa MD5/SHA1 para contraseñas (usa bcrypt)'
        );
        $this->assertTrue(
            str_contains($seed, '$2y$'),
            'seed.sql contiene hash bcrypt ($2y$) para la contraseña del admin'
        );
    }

    private function testNoHardcodedCredentials(): void
    {
        // Verificar que config.php no tenga credenciales hardcodeadas de producción
        $config = file_get_contents(BASE_PATH . '/config/config.php');
        $this->assertFalse(
            (bool)preg_match("/DB_PASS.*'[a-zA-Z0-9!@#\$]{8,}'/", $config),
            'config.php no tiene contraseña de BD hardcodeada larga (sólo vacía para dev)'
        );
    }

    private function testSensitiveFilesNotInWebRoot(): void
    {
        // .env no debe estar en webroot accesible
        $envExists = file_exists(BASE_PATH . '/.env');
        if ($envExists) {
            // Si existe, verificar que .htaccess lo bloquea
            $htaccess = file_get_contents(BASE_PATH . '/.htaccess');
            $this->assertTrue(
                str_contains($htaccess, '.env') || str_contains($htaccess, 'config'),
                'Si existe .env, .htaccess lo bloquea'
            );
        } else {
            $this->assertTrue(true, 'No existe .env expuesto en webroot');
        }
    }

    private function testLogsNotAccessible(): void
    {
        // /storage/logs no debe ser accesible directamente
        $htaccess = file_get_contents(BASE_PATH . '/.htaccess');
        $this->assertTrue(
            str_contains($htaccess, 'storage') || !is_dir(BASE_PATH . '/storage/logs'),
            'El directorio /storage/logs está bloqueado o no existe en webroot'
        );
        // También chequear que el log no esté en /assets o similar
        $this->assertFalse(
            file_exists(BASE_PATH . '/assets/logs'),
            'Los logs no están en /assets (accesible públicamente)'
        );
    }
}
