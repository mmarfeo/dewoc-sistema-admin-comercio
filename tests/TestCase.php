<?php
/**
 * TestCase base — runner liviano sin dependencias externas.
 * Ejecutar desde CLI:  php tests/run.php
 * O desde browser:     http://localhost/01-Dewoc-Sist-Admin-Comercio/tests/run.php
 */
abstract class TestCase
{
    protected int    $passed  = 0;
    protected int    $failed  = 0;
    protected array  $results = [];
    protected string $suite = '';

    public function run(): void
    {
        if ($this->suite === '') {
            $this->suite = get_class($this);
        }
        $methods = array_filter(
            get_class_methods($this),
            fn($m) => str_starts_with($m, 'test') && $m !== 'testCase'
        );
        sort($methods);
        foreach ($methods as $method) {
            $this->$method();
        }
    }

    // ── Assertions ────────────────────────────────────────────

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->record($condition, $message);
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->record(!$condition, $message);
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->record($expected === $actual, $message, "Esperado: " . var_export($expected, true) . " | Obtenido: " . var_export($actual, true));
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->record(str_contains($haystack, $needle), $message ?: "'$needle' debe estar en el string", "'{$needle}' no encontrado");
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->record(!str_contains($haystack, $needle), $message ?: "'$needle' no debe estar en el string", "'{$needle}' fue encontrado (no deberia estar)");
    }

    protected function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void
    {
        $this->record((bool)preg_match($pattern, $subject), $message);
    }

    protected function assertFileExists(string $path, string $message = ''): void
    {
        $this->record(file_exists($path), $message ?: "Archivo debe existir: " . basename($path), "No encontrado: {$path}");
    }

    protected function assertFileNotContains(string $needle, string $filePath, string $message = ''): void
    {
        $content = file_exists($filePath) ? file_get_contents($filePath) : '';
        $this->record(!str_contains($content, $needle), $message ?: "'$needle' no debe estar en " . basename($filePath), "'{$needle}' encontrado en {$filePath}");
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->record($value !== null, $message ?: 'El valor no debe ser null', 'El valor es null');
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->record($value === null, $message ?: 'El valor debe ser null', 'El valor no es null: ' . var_export($value, true));
    }

    protected function assertNotEmpty(mixed $value, string $message = ''): void
    {
        $this->record(!empty($value), $message ?: 'El valor no debe ser vacío', 'El valor está vacío');
    }

    protected function assertEmpty(mixed $value, string $message = ''): void
    {
        $this->record(empty($value), $message ?: 'El valor debe ser vacío', 'El valor no está vacío: ' . var_export($value, true));
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $actual = count($array);
        $this->record($expected === $actual, $message ?: "Se esperaban {$expected} elementos", "Se obtuvieron {$actual} elementos");
    }

    protected function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->record($actual > $expected, $message ?: "{$actual} debe ser mayor que {$expected}", "{$actual} no es mayor que {$expected}");
    }

    protected function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->record($actual >= $expected, $message ?: "{$actual} debe ser >= {$expected}", "{$actual} no es >= {$expected}");
    }

    // ── Internos ──────────────────────────────────────────────

    private function record(bool $pass, string $message, string $detail = ''): void
    {
        if ($pass) {
            $this->passed++;
            $this->results[] = ['pass', $message, ''];
        } else {
            $this->failed++;
            $this->results[] = ['fail', $message, $detail];
        }
    }

    public function getResults(): array
    {
        return [
            'suite'   => $this->suite,
            'passed'  => $this->passed,
            'failed'  => $this->failed,
            'results' => $this->results,
        ];
    }
}
