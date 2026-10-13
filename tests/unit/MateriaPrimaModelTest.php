<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/MateriaPrimaModel.php';

class MateriaPrimaModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/MateriaPrimaModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new MateriaPrimaModel();
        $this->assertTrue($model instanceof Model, 'MateriaPrimaModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new MateriaPrimaModel();
        $ref   = new ReflectionProperty(MateriaPrimaModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('materias_primas', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach (['findAllConDetalle', 'findByNombre', 'actualizarStock', 'tieneUsoEnRecetas', 'historialPrecios'] as $met) {
            $this->assertTrue(method_exists(MateriaPrimaModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/MateriaPrimaModel.php');
        $this->assertNotContains('$_GET', $content, 'MateriaPrimaModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'MateriaPrimaModel no debe usar $_POST directamente en queries');
    }

    /**
     * HALLAZGO REAL (bug de la aplicación, no del test): tieneUsoEnRecetas()
     * consulta `receta_detalle WHERE tipo_item = ? AND item_id = ?`, pero la
     * tabla receta_detalle (ver database/schema.sql) NO tiene columnas
     * `tipo_item` ni `item_id` — tiene `tipo_ingrediente`, `materia_prima_id`
     * e `insumo_id`. Esas columnas (`tipo_item`/`item_id`) sí existen en
     * `stock_movimientos`, pero no en `receta_detalle`; no hay ninguna
     * migración que las agregue ahí tampoco. En consecuencia,
     * MateriasPrimasController::eliminar() —que llama a este método antes de
     * borrar— dispara un PDOException ("columna desconocida") en producción
     * en vez de bloquear correctamente el borrado de una materia prima usada
     * en recetas. Este test documenta el bug dejándolo fallar
     * intencionalmente (no se "arregla" bajando el estándar del assert).
     */
    public function testTieneUsoEnRecetasUsaColumnasQueExistenEnLaTabla(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/MateriaPrimaModel.php');
        $schema  = file_get_contents(BASE_PATH . '/database/schema.sql');

        // La consulta real de tieneUsoEnRecetas() usa tipo_item/item_id:
        $usaColumnasInexistentes = (bool)preg_match(
            "/tieneUsoEnRecetas[\\s\\S]{0,200}tipo_item\\s*=\\s*'materia_prima'[\\s\\S]{0,50}item_id/",
            $content
        );

        // receta_detalle (schema base) no declara esas columnas — tiene tipo_ingrediente/materia_prima_id/insumo_id.
        $tablaRecetaDetalle = substr($schema, (int)strpos($schema, 'CREATE TABLE IF NOT EXISTS `receta_detalle`'));
        $tablaRecetaDetalle = substr($tablaRecetaDetalle, 0, (int)strpos($tablaRecetaDetalle, ') ENGINE'));
        $tablaTieneColumnas = str_contains($tablaRecetaDetalle, '`tipo_item`') || str_contains($tablaRecetaDetalle, '`item_id`');

        $this->assertFalse(
            $usaColumnasInexistentes && !$tablaTieneColumnas,
            'BUG REAL: MateriaPrimaModel::tieneUsoEnRecetas() consulta receta_detalle.tipo_item/item_id, '
            . 'columnas que NO existen en esa tabla (existen tipo_ingrediente/materia_prima_id/insumo_id en su lugar). '
            . 'Esto hace que MateriasPrimasController::eliminar() lance un PDOException en vez de bloquear '
            . 'correctamente el borrado de una materia prima usada en recetas. Mismo problema en '
            . 'InsumoModel::tieneUsoEnRecetas(). No corregido acá: excede el alcance de tests/unit — '
            . 'este test se deja fallando intencionalmente hasta que se corrija en el código de la app.'
        );
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/MateriasPrimasController.php');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/MateriasPrimasController.php');
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(6, $count,
            'MateriasPrimasController debe validar CSRF en sus acciones de escritura');
    }

    public function testEliminarVerificaUsoEnRecetas(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/MateriasPrimasController.php');
        $this->assertContains('tieneUsoEnRecetas', $content,
            'eliminar() debe verificar uso en recetas antes de borrar (aunque la query subyacente tenga el bug arriba documentado)');
    }

    public function testValidarGenericoRequiereUnidadCompatible(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/MateriasPrimasController.php');
        $this->assertContains('unidadCompatible', $content,
            'Al asignar un ingrediente genérico, debe validarse que la unidad de medida sea compatible');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/materias-primas/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/materias-primas/historial_precios.php');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/materias-primas/index.php');
        $this->assertContains('e(', $index, 'Vista index de materias primas debe usar helper e() para escape XSS');
    }
}
