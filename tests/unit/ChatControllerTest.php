<?php
require_once __DIR__ . '/../TestCase.php';

/**
 * Complementa ChatModelTest.php (que ya cubre la validación de SQL del chat IA).
 * Este archivo cubre el Controller: CSRF, y que las acciones destructivas/
 * irreversibles del asistente (compras, ajustes de asistencia, asignar
 * ingrediente genérico) requieran SIEMPRE una tarjeta de confirmación explícita
 * del usuario antes de ejecutarse — no deberían dispararse directo a partir de
 * lo que decida el modelo de IA sin que un humano confirme.
 */
class ChatControllerTest extends TestCase
{
    public function testControllerExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Controllers/ChatController.php'),
            'ChatController.php debe existir'
        );
    }

    public function testControllerTieneCsrfEnAccionesDeEscritura(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        // mensaje, limpiar, confirmar, cancelar
        $count = substr_count($content, 'Csrf::check()');
        $this->assertGreaterThanOrEqual(4, $count,
            'ChatController debe validar CSRF en mensaje/limpiar/confirmar/cancelar');
    }

    public function testCompartirDocumentaPorQueNoUsaCsrf(): void
    {
        // compartir() es el endpoint del Web Share Target del sistema operativo (no un
        // <form> propio de la app), por eso no puede llevar token CSRF — debe estar
        // explícitamente documentado en el código, no ser un olvido.
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        $pos = strpos($content, 'function compartir');
        $this->assertTrue($pos !== false, 'Debe existir el método compartir()');
        $fragmento = substr($content, $pos, 300);
        $this->assertContains('Sin CSRF', $fragmento,
            'La ausencia de Csrf::check() en compartir() debe estar documentada explícitamente en un comentario');
    }

    public function testAccionesDestructivasRequierenConfirmacionExplicita(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        // El modelo de IA nunca debe poder registrar una compra o modificar asistencia de
        // empleados directamente: siempre debe pasar por pedir_confirmacion / mostrar_confirmacion_compra
        // y recién ejecutarse en confirmar() cuando el usuario aprieta el botón.
        $this->assertContains('mostrar_confirmacion_compra', $content,
            'Debe existir la herramienta mostrar_confirmacion_compra para compras (no se ejecutan directo)');
        $this->assertContains('pedir_confirmacion', $content,
            'Debe existir la herramienta pedir_confirmacion para acciones sensibles (feriados, ausencias, etc.)');
        $this->assertMatchesRegex(
            "/'estado'\\s*=>\\s*'pendiente'/",
            $content,
            'Una acción propuesta por la IA debe quedar en estado "pendiente" hasta que el usuario la confirme'
        );
    }

    public function testConfirmarValidaQueLaConfirmacionEstePendiente(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        $pos = strpos($content, 'function confirmar');
        $this->assertTrue($pos !== false, 'Debe existir el método confirmar()');
        $fragmento = substr($content, $pos, 1200);
        $this->assertContains("'pendiente'", $fragmento,
            'confirmar() debe verificar que la tarjeta siga en estado pendiente antes de ejecutar (evita doble ejecución)');
    }

    public function testMensajeRequiereAuth(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        $count = substr_count($content, 'requireAuth()');
        $this->assertGreaterThanOrEqual(5, $count,
            'Todas las acciones del chat (index/mensaje/limpiar/confirmar/cancelar/etc.) deben requerir sesión activa');
    }

    public function testValidaTipoYTamanoDeArchivoAdjunto(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Controllers/ChatController.php');
        $this->assertContains('image/jpeg', $content, 'La imagen adjunta debe validar tipos MIME permitidos');
        $this->assertContains('UPLOAD_ERR_OK', $content, 'Debe verificar que la subida no tenga errores');
    }

    public function testViewExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Views/chat/index.php'),
            'Vista chat/index.php debe existir'
        );
    }

    public function testModelExists(): void
    {
        $this->assertTrue(
            file_exists(BASE_PATH . '/app/Models/ChatModel.php'),
            'ChatModel.php debe existir'
        );
    }

    public function testGuardarMensajeUsaParametrosNombrados(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ChatModel.php');
        $this->assertContains(':uid', $content, 'guardarMensaje() debe usar parámetros nombrados (evita inyección)');
        $this->assertNotContains('$_GET', $content, 'ChatModel no debe usar $_GET directamente en queries');
        $this->assertNotContains('$_POST', $content, 'ChatModel no debe usar $_POST directamente en queries');
    }

    public function testLimpiarHistorialAlcanzaSoloAlUsuarioActual(): void
    {
        $content = file_get_contents(BASE_PATH . '/app/Models/ChatModel.php');
        $this->assertMatchesRegex(
            '/DELETE FROM chat_mensajes WHERE usuario_id\s*=\s*:uid/',
            $content,
            'limpiarHistorial() debe borrar solo los mensajes del usuario dueño de la sesión, nunca de otros'
        );
    }
}
