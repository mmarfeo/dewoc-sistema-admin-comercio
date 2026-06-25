<?php
/**
 * Tests de prevención de SQL Injection
 * Verifica que todo acceso a BD use PDO prepared statements
 */
class SqlInjectionTest extends TestCase
{
    protected string $suite = 'Seguridad — SQL Injection';

    private string $appPath;

    public function __construct()
    {
        $this->appPath = BASE_PATH . '/app';
    }

    public function run(): void
    {
        $this->testNoConcatenationInQueries();
        $this->testNoRawQueryWithUserInput();
        $this->testModelUsesParameterBinding();
        $this->testTableNameFromClassPropertyOnly();
        $this->testUpdateMethodParamBinding();
        $this->testInsertMethodParamBinding();
        $this->testSanitizeHelperExists();
        $this->testSanitizeStripsTags();
        $this->testNoDirectSprintfInSql();
    }

    /** No debe haber concatenación directa de variables en strings SQL */
    private function testNoConcatenationInQueries(): void
    {
        $files   = $this->phpFiles($this->appPath . '/Models');
        $pattern = '/(?:SELECT|INSERT|UPDATE|DELETE)[^;]*\.\s*\$(?!this->table|this->primaryKey)/i';
        $found   = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match($pattern, $content, $m)) {
                $found[] = basename($file) . ': ' . trim($m[0]);
            }
        }

        $this->assertTrue(
            empty($found),
            'Los Modelos no concatenan variables de usuario en SQL'
        );
        if (!empty($found)) {
            foreach ($found as $f) {
                $this->assertTrue(false, "  → Posible inyección: {$f}");
            }
        }
    }

    /** Busca sprintf/vsprintf mezclado con SQL directo */
    private function testNoDirectSprintfInSql(): void
    {
        $files = $this->phpFiles($this->appPath . '/Models');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $hasSprintf = preg_match('/sprintf\s*\(["\'].*(?:SELECT|INSERT|UPDATE|DELETE)/i', $content);
            $this->assertFalse(
                (bool)$hasSprintf,
                'Modelo ' . basename($file) . ' no usa sprintf() para construir SQL'
            );
        }
    }

    /** Todos los modelos deben usar ->prepare() o ->query() en el singleton */
    private function testModelUsesParameterBinding(): void
    {
        $modelFiles = $this->phpFiles($this->appPath . '/Models');
        foreach ($modelFiles as $file) {
            if (basename($file) === 'Model.php') continue;
            $content = file_get_contents($file);
            $hasQuery = str_contains($content, '$this->query(')
                     || str_contains($content, '$this->queryAll(')
                     || str_contains($content, '$this->queryOne(')
                     || str_contains($content, '$this->db->prepare(');
            if (str_contains($content, 'SELECT') || str_contains($content, 'UPDATE') || str_contains($content, 'INSERT')) {
                $this->assertTrue($hasQuery, basename($file) . ' usa métodos de binding de parámetros');
            }
        }
    }

    /** $this->table debe venir de la clase, no de input externo */
    private function testTableNameFromClassPropertyOnly(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/Model.php');
        $usesTable = str_contains($content, '{$this->table}');
        $tableFromInput = preg_match('/\$this->table\s*=\s*\$_(POST|GET|REQUEST)/i', $content);
        $this->assertTrue($usesTable && !$tableFromInput, 'Model base: $this->table viene de la clase, no de input externo');
    }

    /** Verificar que el método update usa named parameters con prefijo seguro */
    private function testUpdateMethodParamBinding(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/Model.php');
        $hasBinding    = str_contains($content, ':_pk_id');
        $noBugConcat   = !str_contains($content, '$data[\':__id\']');
        $this->assertTrue($hasBinding, 'Model::update() usa named parameter :_pk_id para el WHERE');
        $this->assertTrue($noBugConcat, 'Model::update() no tiene el bug de concatenación de clave duplicada');
    }

    /** Verificar que insert usa named placeholders */
    private function testInsertMethodParamBinding(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/Model.php');
        $this->assertTrue(
            str_contains($content, 'array_map(fn($k) => ":{$k}"'),
            'Model::insert() usa named placeholders generados dinámicamente'
        );
    }

    /** El helper sanitize() debe existir */
    private function testSanitizeHelperExists(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/helpers.php');
        $this->assertTrue(str_contains($content, 'function sanitize'), 'helpers.php define función sanitize()');
    }

    /** sanitize() debe usar strip_tags y trim */
    private function testSanitizeStripsTags(): void
    {
        $content = file_get_contents(BASE_PATH . '/core/helpers.php');
        $this->assertTrue(
            str_contains($content, 'strip_tags') && str_contains($content, 'trim'),
            'sanitize() usa strip_tags() + trim() para limpiar input'
        );
    }

    /** No debe haber input directo en parámetros sin sanitizar */
    private function testNoRawQueryWithUserInput(): void
    {
        $files = $this->phpFiles($this->appPath . '/Controllers');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Buscar patrones como "WHERE x = " . $_POST  o  . $_GET
            $hasDirect = preg_match('/(?:WHERE|AND|OR)\s+\w+\s*=\s*["\'][^"\']*["\']\s*\.\s*\$_(?:POST|GET|REQUEST)/i', $content);
            $this->assertFalse(
                (bool)$hasDirect,
                basename($file) . ': no concatena $_POST/$_GET directamente en SQL'
            );
        }
    }

    private function phpFiles(string $dir): array
    {
        if (!is_dir($dir)) return [];
        return glob($dir . '/*.php') ?: [];
    }
}
