<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/RecetaModel.php';

class RecetaModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/RecetaModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new RecetaModel();
        $this->assertTrue($model instanceof Model, 'RecetaModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new RecetaModel();
        $ref   = new ReflectionProperty(RecetaModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('recetas', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach ([
            'findAllConDetalle', 'findByIdConDetalle', 'guardar', 'actualizarConDetalle',
            'producir', 'planificarOrden', 'cambiarEstadoOrden', 'calcularCosto',
            'findVersiones', 'findHistorialCosto',
        ] as $met) {
            $this->assertTrue(method_exists(RecetaModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testGuardarUsaTransaccionYGeneraVersion(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/RecetaModel.php');
        $this->assertContains('beginTransaction', $content, 'guardar() debe usar transacción');
        $this->assertContains('guardarVersion', $content, 'guardar() debe versionar la receta (historial de cambios)');
    }

    public function testProducirValidaStockAntesDeDescontar(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/RecetaModel.php');
        $this->assertContains('faltantes', $content, 'producir() debe validar faltantes de stock antes de ejecutar');
        $this->assertContains('rollBack', $content, 'producir() debe hacer rollBack si algo falla');
    }

    public function testDescontarIngredienteConsumeFifoParaGenericos(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/RecetaModel.php');
        $this->assertContains('descontarIngrediente', $content);
        $this->assertContains('ORDER BY updated_at ASC', $content,
            'El consumo de ingredientes genéricos debe ser FIFO (la variante comprada primero se usa primero)');
    }

    public function testCalcularCostoConsideraMermaYDesvio(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/RecetaModel.php');
        $this->assertContains('merma_pct', $content, 'calcularCosto() debe considerar el % de merma de cada ingrediente');
        $this->assertContains('desvio_pct', $content, 'calcularCosto() debe considerar el % de desvío de costo de la materia prima');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/RecetaModel.php');
        $this->assertNotContains('$_GET', $content, 'RecetaModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'RecetaModel no debe usar $_POST directamente en queries');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/RecetasController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/RecetasController.php');
        // guardar, asignarProducto, quitarProducto, actualizar, eliminar, producir,
        // planificar, cambiarEstadoOrden, importar, previsualizar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(9, $count,
            'RecetasController debe validar CSRF en todas sus acciones de escritura');
    }

    public function testProducirValidaCantidadPositiva(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/RecetasController.php');
        $this->assertContains('$cant <= 0', $content, 'producir() debe rechazar cantidades de producción <= 0');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/recetas/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/recetas/form.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/recetas/produccion.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/recetas/ordenes.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/recetas/versiones.php');
    }

    public function testFormUsaCsrfField(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/recetas/form.php');
        $this->assertContains('Csrf::field()', $content, 'Formulario de recetas debe incluir el campo CSRF');
    }

    public function testSchemaRecetaDetalleTieneColumnasEsperadas(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('tipo_ingrediente', $schema, 'receta_detalle debe tener columna tipo_ingrediente');
        $this->assertContains('materia_prima_id', $schema, 'receta_detalle debe tener columna materia_prima_id');
        $this->assertContains('insumo_id', $schema, 'receta_detalle debe tener columna insumo_id');
        $this->assertContains('merma_pct', $schema, 'receta_detalle debe tener columna merma_pct');
    }

    /**
     * HALLAZGO REAL (gap de instalación, no de este Model puntual): la columna
     * `ingrediente_generico_id` (usada intensivamente por RecetaModel,
     * ProductoModel y StockModel) NO está en schema.sql — la agrega
     * database/ingredientes_genericos_migration.sql. El README (paso 3) e
     * install.php solo aplican schema.sql + seed.sql; ninguno de los dos
     * ejecuta las ~16 migraciones sueltas en database/*_migration.sql o
     * *_setup.sql. Una instalación nueva siguiendo esas instrucciones al pie
     * de la letra quedaría con la base incompleta y estas consultas fallarían
     * con "columna desconocida". Este test deja constancia del gap; no lo
     * corrige (excede el alcance de tests/unit y de esta tarea).
     */
    public function testIngredienteGenericoIdRequiereMigracionSeparada(): void
    {
        $schema    = file_get_contents(BASE_PATH . '/database/schema.sql');
        $migracion = file_get_contents(BASE_PATH . '/database/ingredientes_genericos_migration.sql');

        $this->assertFalse(
            str_contains($schema, 'ingrediente_generico_id'),
            'Confirmado: ingrediente_generico_id NO está en schema.sql base'
        );
        $this->assertContains(
            'ingrediente_generico_id', $migracion,
            'database/ingredientes_genericos_migration.sql debe agregar la columna ingrediente_generico_id'
        );
    }
}
