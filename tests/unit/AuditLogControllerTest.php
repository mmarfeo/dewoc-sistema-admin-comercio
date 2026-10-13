<?php
require_once __DIR__ . '/../TestCase.php';

class AuditLogControllerTest extends TestCase
{
    public function testControllerExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Controllers/AuditLogController.php'),
            'AuditLogController.php debe existir'
        );
    }

    public function testControllerRequiereRolAdmin(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuditLogController.php');
        $this->assertContains("requireRole('admin')", $content,
            'El log de auditoría solo debe ser visible para administradores');
    }

    public function testFiltrosUsanParametrosNombrados(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuditLogController.php');
        $this->assertContains(':desde', $content, 'El filtro de fecha desde debe usar parámetro nombrado');
        $this->assertContains(':hasta', $content, 'El filtro de fecha hasta debe usar parámetro nombrado');
        $this->assertContains(':mod', $content, 'El filtro de módulo debe usar parámetro nombrado');
        $this->assertContains(':acc', $content, 'El filtro de acción debe usar parámetro nombrado');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuditLogController.php');
        $this->assertNotContains('$_GET', $content, 'No debe usar $_GET directamente en la query de auditoría');
        $this->assertNotContains('$_POST', $content, 'No debe usar $_POST directamente en la query de auditoría');
        // Interpolación directa de las variables de filtro dentro del SQL (en vez de parámetros nombrados)
        $this->assertFalse(
            (bool)preg_match('/[\'"]\s*\.\s*\$(modulo|accion)\s*\.\s*[\'"]/', $content),
            'El módulo/acción filtrados no deben concatenarse directo en el SQL'
        );
    }

    public function testLimitaResultadosA500(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/AuditLogController.php');
        $this->assertContains('LIMIT 500', $content, 'El listado de auditoría debe limitar la cantidad de filas devueltas');
    }

    public function testViewExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Views/audit_log/index.php'),
            'Vista audit_log/index.php debe existir'
        );
    }

    public function testViewUsaEscapeHTML(): void
    {
        $contenido = file_get_contents(BASE_PATH . '/app/Views/audit_log/index.php');
        $this->assertContains('e(', $contenido, 'Vista de auditoría debe usar helper e() para escape XSS');
    }

    // ── AuditLog (core) ──────────────────────────────────────────────────────

    public function testAuditLogHelperExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/core/AuditLog.php'),
            'core/AuditLog.php debe existir (usado por Ventas/Usuarios/etc. para dejar rastro de acciones sensibles)'
        );
    }

    public function testAuditLogTieneMetodoLog(): void
    {
        require_once BASE_PATH . '/core/AuditLog.php';
        $this->assertTrue(method_exists('AuditLog', 'log'), 'AuditLog::log() debe existir');
    }

    public function testSchemaTieneTablaAuditLog(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $this->assertContains('audit_log', $schema, 'Schema debe tener la tabla audit_log');
    }
}
