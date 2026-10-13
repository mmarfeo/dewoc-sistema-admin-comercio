<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/InsumoModel.php';

class InsumoModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/InsumoModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new InsumoModel();
        $this->assertTrue($model instanceof Model, 'InsumoModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new InsumoModel();
        $ref   = new ReflectionProperty(InsumoModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('insumos', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach (['findAllConDetalle', 'actualizarStock', 'tieneUsoEnRecetas'] as $met) {
            $this->assertTrue(method_exists(InsumoModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/InsumoModel.php');
        $this->assertNotContains('$_GET', $content, 'InsumoModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'InsumoModel no debe usar $_POST directamente en queries');
    }

    /**
     * HALLAZGO REAL (mismo bug que MateriaPrimaModelTest::testTieneUsoEnRecetasUsaColumnasQueExistenEnLaTabla):
     * InsumoModel::tieneUsoEnRecetas() consulta `receta_detalle WHERE tipo_item = 'insumo' AND item_id = ?`,
     * pero receta_detalle no tiene columnas tipo_item/item_id (tiene tipo_ingrediente/insumo_id).
     * InsumosController::eliminar() llama a este método antes de dar de baja un insumo, así que
     * el intento de eliminar un insumo usado en recetas rompe con PDOException en vez de bloquearse
     * con el mensaje "está en uso en recetas". Se deja fallando intencionalmente como documentación.
     */
    public function testTieneUsoEnRecetasUsaColumnasQueExistenEnLaTabla(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/InsumoModel.php');
        $schema  = file_get_contents(BASE_PATH . '/database/schema.sql');

        $usaColumnasInexistentes = (bool)preg_match(
            "/tieneUsoEnRecetas[\\s\\S]{0,200}tipo_item\\s*=\\s*'insumo'[\\s\\S]{0,50}item_id/",
            $content
        );

        $tablaRecetaDetalle = substr($schema, (int)strpos($schema, 'CREATE TABLE IF NOT EXISTS `receta_detalle`'));
        $tablaRecetaDetalle = substr($tablaRecetaDetalle, 0, (int)strpos($tablaRecetaDetalle, ') ENGINE'));
        $tablaTieneColumnas = str_contains($tablaRecetaDetalle, '`tipo_item`') || str_contains($tablaRecetaDetalle, '`item_id`');

        $this->assertFalse(
            $usaColumnasInexistentes && !$tablaTieneColumnas,
            'BUG REAL: InsumoModel::tieneUsoEnRecetas() consulta receta_detalle.tipo_item/item_id, '
            . 'columnas que NO existen en esa tabla. InsumosController::eliminar() dispara un PDOException '
            . 'en vez de bloquear correctamente el borrado de un insumo en uso. No corregido acá: '
            . 'excede el alcance de tests/unit.'
        );
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/InsumosController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/InsumosController.php');
        // crear, actualizar, eliminar, importar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(4, $count,
            'InsumosController debe validar CSRF en sus acciones de escritura');
    }

    public function testEliminarVerificaUsoEnRecetas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/InsumosController.php');
        $this->assertContains('tieneUsoEnRecetas', $content,
            'eliminar() debe verificar uso en recetas antes de borrar (aunque la query subyacente tenga el bug arriba documentado)');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/insumos/index.php');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/insumos/index.php');
        $this->assertContains('e(', $index, 'Vista index de insumos debe usar helper e() para escape XSS');
    }
}
