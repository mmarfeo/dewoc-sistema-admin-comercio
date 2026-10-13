<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/CajaModel.php';

/**
 * Cajas abiertas en el Historial de Turnos: el filtro por estado y el panel con la hora de
 * apertura y los montos de cada caja abierta.
 *
 * Suite aparte de CajaModelTest a propósito: esa instancia CajaModel, lo que abre una
 * conexión a la base, y donde la base no está disponible la suite entera se omite. Todo lo
 * de acá es análisis estático y reflexión, así que corre siempre — y lo que verifica es
 * justamente lo que no se puede perder en un refactor: que el cierre ciego siga ocultando
 * los montos.
 */
class CajaAbiertasTest extends TestCase
{
    // ── Cajas abiertas en el historial ──────────────────────────────────────

    public function testHistorialAceptaFiltroPorEstado(): void
    {
        $ref = new ReflectionMethod(CajaModel::class, 'historial');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertTrue(in_array('estado', $params, true),
            'historial() debe aceptar un filtro por estado (abierto/cerrado)');
    }

    public function testHistorialSoloAceptaEstadosValidos(): void
    {
        $src = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $pos = strpos($src, 'public function historial(');
        $cuerpo = substr($src, $pos, 1400);
        $this->assertContains("in_array(\$estado, ['abierto', 'cerrado'], true)", $cuerpo,
            'Cualquier otro valor de estado se ignora, no se interpola en el SQL');
    }

    public function testLosTurnosAbiertosSalenPrimero(): void
    {
        $src = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $pos = strpos($src, 'public function historial(');
        $cuerpo = substr($src, $pos, 1600);
        $this->assertContains("ORDER BY (t.estado = 'abierto') DESC", $cuerpo,
            'Un turno abierto es lo unico accionable: va arriba aunque su numero sea menor');
    }

    public function testExisteElDetalleDeTurnosAbiertosConMontos(): void
    {
        $this->assertTrue(method_exists(CajaModel::class, 'turnosAbiertosConMontos'),
            'Debe existir turnosAbiertosConMontos()');

        $src = file_get_contents(BASE_PATH . '/app/Models/CajaModel.php');
        $pos = strpos($src, 'public function turnosAbiertosConMontos(');
        $cuerpo = substr($src, $pos, 2200);

        $this->assertContains("estado = 'abierto'", $cuerpo, 'Solo trae los turnos abiertos');
        $this->assertContains('calcularEsperado', $cuerpo,
            'Reusa calcularEsperado() en vez de duplicar el criterio de que cuenta como esperado');
        $this->assertContains('efectivo_esperado', $cuerpo,
            'Separa el efectivo fisico, que es lo que deberia estar en el cajon');
        $this->assertContains('electronico', $cuerpo,
            'Lo cobrado por medios electronicos no esta en la caja fisica: va aparte');
    }

    public function testElCierreCiegoNoVeLosMontosDeLasCajasAbiertas(): void
    {
        // El cierre ciego existe para que el cajero declare lo que conto SIN saber antes
        // cuanto tendria que haber. Si el panel de cajas abiertas le mostrara el efectivo
        // esperado, bastaba con leerlo de ahi y declarar ese numero.
        $vista = file_get_contents(BASE_PATH . '/app/Views/caja/historial.php');

        $pos = strpos($vista, 'Cajas abiertas ahora');
        $this->assertTrue($pos !== false, 'La vista debe tener el panel de cajas abiertas');
        if ($pos === false) return;

        $panel = substr($vista, $pos, 3200);
        $this->assertContains('if (!$cierraCiego)', $panel,
            'Las columnas de montos del panel van detras de !$cierraCiego');

        // La hora de apertura y el monto inicial si se muestran siempre: no revelan el
        // esperado y son justamente para lo que sirve el panel.
        $this->assertContains('apertura_at', $panel);
        $this->assertContains('monto_inicial', $panel);
    }

    public function testElHistorialNoReordenaYPierdeLosAbiertosArriba(): void
    {
        $vista = file_get_contents(BASE_PATH . '/app/Views/caja/historial.php');
        $this->assertContains('order: []', $vista,
            'DataTables tiene que respetar el orden del servidor, que pone los abiertos primero');
    }
}
