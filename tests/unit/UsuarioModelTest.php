<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/PermisosModel.php';
require_once __DIR__ . '/../../app/Models/UsuarioModel.php';

/**
 * Cobertura de autenticación, roles y permisos — crítico de seguridad.
 * Análisis estático/reflexión, sin fixtures reales en la base.
 */
class UsuarioModelTest extends TestCase
{
    public function testModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/UsuarioModel.php');
    }

    public function testModelExtendsModel(): void
    {
        $model = new UsuarioModel();
        $this->assertTrue($model instanceof Model, 'UsuarioModel debe extender Model');
    }

    public function testTableName(): void
    {
        $model = new UsuarioModel();
        $ref   = new ReflectionProperty(UsuarioModel::class, 'table');
        $ref->setAccessible(true);
        $this->assertEquals('usuarios', $ref->getValue($model));
    }

    public function testMetodosClaveExisten(): void
    {
        foreach ([
            'findByEmail', 'findByIdConRol', 'findAllConRol',
            'registrarIntentoFallido', 'resetIntentos', 'estaBloquado',
            'getPermisos', 'getPermisosUsuario', 'getPermisosEfectivos',
            'setResetToken', 'findByResetToken', 'updatePassword',
            'setRememberToken', 'findByRememberToken', 'clearRememberToken',
        ] as $met) {
            $this->assertTrue(method_exists(UsuarioModel::class, $met), "Debe existir el método $met()");
        }
    }

    public function testLoginUsaContadorDeIntentosYBloqueoTemporal(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('LOGIN_MAX_ATTEMPTS', $content, 'Debe existir un límite de intentos fallidos configurable');
        $this->assertContains('LOGIN_LOCKOUT_MINUTES', $content, 'Debe existir un tiempo de bloqueo configurable');
        $this->assertContains('bloqueado_hasta', $content, 'Debe persistir el bloqueo temporal en la base');
    }

    public function testResetTokenExpira(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('reset_expires', $content, 'El token de restablecimiento debe tener expiración');
        $this->assertContains("'+1 hour'", $content, 'El token de restablecimiento debe expirar en 1 hora');
    }

    public function testFindByResetTokenValidaExpiracionYEstado(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('reset_expires > NOW()', $content, 'findByResetToken debe validar que no haya expirado');
        $this->assertContains('eliminado = 0', $content, 'findByResetToken debe excluir usuarios eliminados');
    }

    public function testRememberTokenUsaHashYExpiracion(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('remember_expires', $content, 'El token "Recordarme" debe tener expiración');
        // El nombre del parámetro es tokenHash: el hash se calcula antes de llegar al modelo (en RememberMe),
        // el modelo no debe manipular la contraseña ni el token en texto plano.
        $this->assertContains('tokenHash', $content, 'Los tokens deben manejarse como hash, no en texto plano');
    }

    public function testPermisosEfectivosCombinaRolYExcepciones(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('getPermisosEfectivos', $content);
        $this->assertContains('array_merge($permisosRol, $permisosUsuario)', $content,
            'Los permisos efectivos deben superponer las excepciones del usuario sobre las del rol');
    }

    public function testGuardarPermisosUsuarioUsaTransaccion(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('beginTransaction', $content, 'guardarPermisosUsuario() debe usar transacción');
        $this->assertContains('rollBack', $content, 'guardarPermisosUsuario() debe hacer rollBack en caso de error');
    }

    public function testGuardarPermisosUsuarioValidaModuloContraWhitelist(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertContains('array_key_exists($modulo, PermisosModel::MODULOS)', $content,
            'No debe guardar permisos de módulos que no estén en la whitelist de PermisosModel::MODULOS');
    }

    public function testNoSqlConcatenationInsegura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/UsuarioModel.php');
        $this->assertNotContains('$_GET', $content, 'UsuarioModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'UsuarioModel no debe usar $_POST directamente en queries');
    }

    // ── PermisosModel ────────────────────────────────────────────────────────

    public function testPermisosModelFileExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Models/PermisosModel.php');
    }

    public function testPermisosModelExtendsModel(): void
    {
        $model = new PermisosModel();
        $this->assertTrue($model instanceof Model, 'PermisosModel debe extender Model');
    }

    public function testPermisosModulosIncluyeModulosCriticos(): void
    {
        foreach (['caja', 'ventas', 'compras', 'productos', 'reportes'] as $mod) {
            $this->assertTrue(array_key_exists($mod, PermisosModel::MODULOS), "El módulo '$mod' debe estar en PermisosModel::MODULOS");
        }
    }

    public function testSaveAllUsaTransaccionYExcluyeAdmin(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/PermisosModel.php');
        $this->assertContains('beginTransaction', $content, 'saveAll() debe usar transacción');
        $this->assertContains("nombre != 'admin'", $content, 'El rol admin no debe tener permisos personalizables (acceso total fijo)');
    }

    // ── Controller ───────────────────────────────────────────────────────────

    public function testControllerExists(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/UsuariosController.php');
    }

    public function testControllerRequiereRolAdminEnAccionesSensibles(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        // index, nuevo, crear, editar, guardarAccesosChat, guardarPermisosUsuario, actualizar, eliminar, importar, plantilla
        $count = substr_count($content, "requireRole('admin')");
        $this->assertGreaterThanOrEqual(9, $count,
            'La gestión de usuarios (crear/editar/eliminar/permisos) debe requerir rol admin');
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(8, $count,
            'UsuariosController debe validar CSRF en todas sus acciones de escritura (POST)');
    }

    public function testNoPuedeEliminarseASiMismo(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $this->assertContains('No puede eliminarse a sí mismo', $content,
            'Un usuario no debe poder eliminar su propia cuenta');
    }

    public function testCambiarPasswordValidaPasswordActual(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $this->assertContains('password_verify', $content, 'cambiarPassword() debe verificar la contraseña actual antes de cambiarla');
        $this->assertContains('strlen($nueva) < 8', $content, 'La nueva contraseña debe tener un largo mínimo');
    }

    public function testPasswordsSeHasheanConBcrypt(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $this->assertContains('PASSWORD_BCRYPT', $content, 'Las contraseñas deben hashearse con bcrypt, nunca en texto plano');
        $this->assertNotContains("'password_hash' => \$password", $content,
            'Nunca debe guardarse la contraseña sin hashear');
    }

    public function testValidaEmailUnicoAlCrear(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $this->assertContains('findByEmail', $content, 'crear() debe validar que el email no esté ya en uso');
    }

    public function testAccionesSensiblesRegistranAuditLog(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/UsuariosController.php');
        $this->assertContains('AuditLog::log', $content, 'Crear/editar/eliminar usuarios debe quedar en el log de auditoría');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Views/usuarios/index.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/usuarios/form.php');
        $this->assertFileExists(BASE_PATH . '/app/Views/usuarios/perfil.php');
    }

    public function testFormUsaCsrfField(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Views/usuarios/form.php');
        $this->assertContains('Csrf::field()', $content, 'Formulario de usuarios debe incluir el campo CSRF');
    }

    public function testViewsUsanEscapeHTML(): void
    {
        $index = file_get_contents(BASE_PATH . '/app/Views/usuarios/index.php');
        $this->assertContains('e(', $index, 'Vista index de usuarios debe usar helper e() para escape XSS');
    }
}
