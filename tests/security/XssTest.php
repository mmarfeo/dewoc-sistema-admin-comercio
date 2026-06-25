<?php
/**
 * Tests de prevención de XSS (Cross-Site Scripting)
 * Verifica que toda salida de variables se escape con e() o htmlspecialchars()
 */
class XssTest extends TestCase
{
    protected string $suite = 'Seguridad — XSS';
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = BASE_PATH . '/app/Views';
    }

    public function run(): void
    {
        $this->testEscapeHelperExists();
        $this->testEscapeHelperUsesHtmlspecialchars();
        $this->testNoRawEchoOfVariables();
        $this->testNoRawPrintOfVariables();
        $this->testInputValuesAreEscaped();
        $this->testHtmlspecialcharsFlags();
        $this->testNoInnerHtmlWithUserData();
    }

    /** e() debe existir en helpers */
    private function testEscapeHelperExists(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/helpers.php');
        $this->assertTrue(str_contains($content, 'function e('), 'helpers.php define función e() para escape HTML');
    }

    /** e() debe usar htmlspecialchars con ENT_QUOTES */
    private function testEscapeHelperUsesHtmlspecialchars(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/helpers.php');
        $this->assertTrue(
            str_contains($content, 'htmlspecialchars') && str_contains($content, 'ENT_QUOTES'),
            'e() usa htmlspecialchars() con ENT_QUOTES | ENT_SUBSTITUTE'
        );
    }

    /** Buscar <?= $var ?> sin e() en vistas (potencial XSS) */
    private function testNoRawEchoOfVariables(): void
    {
        $viewFiles  = $this->getAllViewFiles();
        $violations = [];

        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            $lines   = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                /* Detecta <?= $var end-tag o <?= $arr['key'] end-tag sin e() ni moneda() */
                if (preg_match('/\<\?=\s*\$(?!_SESSION|_SERVER|_COOKIE)(\w+)(?:\[[\'"]\w+[\'"]\])?\s*\?>/', $line, $m)) {
                    /* Excluir variables de sistema o escapadas en contexto */
                    $var = $m[0];
                    if (!str_contains($var, 'e(') && !str_contains($line, 'moneda(') && !str_contains($line, 'fecha(') && !str_contains($line, 'number_format(')) {
                        $violations[] = basename($file) . ':' . ($lineNum + 1) . ' → ' . trim($line);
                    }
                }
            }
        }

        $this->assertTrue(
            empty($violations),
            'Vistas no tienen <?= $var ?> sin escapar (' . count($violations) . ' detectados)'
        );
        // Reportar los primeros 5
        foreach (array_slice($violations, 0, 5) as $v) {
            $this->assertTrue(false, "  XSS potencial: {$v}");
        }
    }

    /** Ninguna vista debe usar print $var sin e() */
    private function testNoRawPrintOfVariables(): void
    {
        $viewFiles = $this->getAllViewFiles();
        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            $hasRaw  = preg_match('/\bprint\s+\$(?!_)/i', $content);
            $this->assertFalse((bool)$hasRaw, basename($file) . ' no usa print $var sin escapar');
        }
    }

    /** Los value= de inputs deben usar e() */
    private function testInputValuesAreEscaped(): void
    {
        $viewFiles = $this->getAllViewFiles();
        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            /* value="<?= $var end-tag" sin e() */
            if (preg_match('/value=["\']<\?=\s*\$\w+\s*\?>["\']/', $content, $m)) {
                $this->assertFalse(true, basename($file) . ' tiene value sin e(): ' . $m[0]);
            } else {
                $this->assertTrue(true, basename($file) . ': atributos value usan e() correctamente');
            }
        }
    }

    /** htmlspecialchars debe tener el charset correcto */
    private function testHtmlspecialcharsFlags(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/helpers.php');
        $this->assertTrue(
            str_contains($content, "'UTF-8'"),
            'htmlspecialchars usa charset UTF-8'
        );
    }

    /** No debe haber innerHTML en JS con datos de PHP sin escapar */
    private function testNoInnerHtmlWithUserData(): void
    {
        $viewFiles = $this->getAllViewFiles();
        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            /* innerHTML = "<?= $var end-tag" (sin json_encode) */
            if (preg_match('/innerHTML\s*=\s*["\']<\?=\s*\$(?!\w+json|\w+Json)/i', $content, $m)) {
                $this->assertFalse(true, basename($file) . ' asigna innerHTML con PHP sin json_encode');
            }
        }
        $this->assertTrue(true, 'Vistas no asignan innerHTML con variables PHP crudas');
    }

    private function getAllViewFiles(): array
    {
        $files = [];
        $iter  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->viewsPath));
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
