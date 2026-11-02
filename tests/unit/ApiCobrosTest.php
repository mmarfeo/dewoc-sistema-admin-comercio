<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

/**
 * Dewoc Cobros, lado servidor (ver docs/dewoc-cobros-checklist.md y
 * dewoc-cobros-servidor-plan.md). Mismo estilo que ApiMenuAppTest: análisis estático, sin
 * fixtures en la base. Verifica lo que no se puede perder en un refactor: que la API no use
 * sesión, que cada acción valide el token, que el token nunca se guarde ni se loguee en
 * claro y que la licencia se revise.
 */
class ApiCobrosTest extends TestCase
{
    private string $api;
    /** Sin comentarios: las aserciones de "esto NO debe aparecer" miran el código real. */
    private string $apiCodigo;
    private string $modeloDisp;
    private string $pantalla;
    private string $vista;
    private string $migracion;
    private string $index;

    public function __construct()
    {
        $this->api        = file_get_contents(BASE_PATH . '/app/Controllers/ApiCobrosController.php');
        $this->apiCodigo  = php_strip_whitespace(BASE_PATH . '/app/Controllers/ApiCobrosController.php');
        $this->modeloDisp = file_get_contents(BASE_PATH . '/app/Models/CobroDispositivoModel.php');
        $this->pantalla   = file_get_contents(BASE_PATH . '/app/Controllers/IntegracionDewocCobrosController.php');
        $this->vista      = file_get_contents(BASE_PATH . '/app/Views/integraciones/dewoc-cobros.php');
        $this->migracion  = file_get_contents(BASE_PATH . '/database/dewoc_cobros_migration.sql');
        $this->index      = file_get_contents(BASE_PATH . '/index.php');
    }

    public function testArchivosExisten(): void
    {
        foreach ([
            '/app/Controllers/ApiCobrosController.php',
            '/app/Controllers/IntegracionDewocCobrosController.php',
            '/app/Models/CobroDispositivoModel.php',
            '/app/Models/CobroPaqueteModel.php',
            '/app/Views/integraciones/dewoc-cobros.php',
            '/database/dewoc_cobros_migration.sql',
        ] as $f) {
            $this->assertFileExists(BASE_PATH . $f);
        }
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function testTodasLasAccionesPublicasValidanElToken(): void
    {
        preg_match_all('/public function (\w+)\(/', $this->api, $m);
        $acciones = array_diff($m[1], ['__construct']);
        $this->assertNotEmpty($acciones, 'La API tiene acciones públicas');

        foreach ($acciones as $accion) {
            $cuerpo = substr($this->api, strpos($this->api, "public function {$accion}("), 400);
            $this->assertContains('requireDispositivo', $cuerpo,
                "{$accion}() debe empezar validando el token con requireDispositivo()");
        }
    }

    public function testLaApiNoUsaSesionNiCsrf(): void
    {
        $this->assertNotContains('Csrf::check', $this->apiCodigo, 'La API se autentica por token, no por CSRF');
        $this->assertNotContains('$_SESSION', $this->apiCodigo, 'La API no depende de la sesión de staff');
        $this->assertNotContains('Session::', $this->apiCodigo, 'La API no depende de la sesión de staff');
    }

    public function testElLogNoIncluyeElToken(): void
    {
        $pos = strpos($this->api, 'private function logLlamada(');
        $cuerpo = substr($this->api, $pos, 500);
        $this->assertNotContains('bearerToken', $cuerpo, 'logLlamada() no registra el token');
        $this->assertNotContains('HTTP_AUTHORIZATION', $cuerpo, 'logLlamada() no registra el header Authorization');
    }

    public function testEstadoInformaLaLicenciaDelCelular(): void
    {
        $this->assertContains("moduloEstado(self::MODULO)", $this->api, 'La API revisa la licencia del módulo');
        $this->assertContains("'limite_dispositivos'", $this->api, 'Un celular fuera del límite recibe limite_dispositivos (C18)');
        foreach (['comercio', 'dispositivo_activo', 'licencia', 'caja_abierta', 'paquetes_version', 'paquetes_permitidos'] as $campo) {
            $this->assertContains("'{$campo}'", $this->api, "GET /estado devuelve {$campo} (sección 3.1)");
        }
    }

    public function testRutasDeLaApi(): void
    {
        $this->assertContains("str_starts_with(trim(\$url, '/'), 'api/cobros/v1/')", $this->index,
            'index.php resuelve api/cobros/v1/ antes del dispatch');
        $this->assertContains("'/api/cobros/v1/'", $this->index,
            'Los errores de la API de Cobros se responden en JSON también en los GET');
        $this->assertContains("'integraciones/dewoc-cobros'", $this->index, 'Ruta de la pantalla de Integraciones');
    }

    // ── Token ────────────────────────────────────────────────────────────────

    public function testDelTokenSoloSeGuardaElHash(): void
    {
        $this->assertContains("hash('sha256', \$token)", $this->modeloDisp, 'El token se guarda como SHA-256');
        $this->assertContains("'dc_' . randomToken(", $this->modeloDisp, 'Los tokens llevan el prefijo dc_');
        $this->assertNotContains('`token` ', $this->migracion, 'No hay columna con el token en claro');
        $this->assertContains('UNIQUE KEY `uq_cobros_disp_token`', $this->migracion, 'token_hash es UNIQUE');
    }

    public function testElTokenNoSeImprimeEnElHtmlNiQuedaEnLaSesion(): void
    {
        $this->assertNotContains('Session::set', $this->pantalla, 'El token nuevo no se guarda en la sesión');
        $this->assertNotContains('$token', $this->vista, 'La vista no recibe el token: llega solo por AJAX al modal del QR');
    }

    public function testVincularRespetaLaLicencia(): void
    {
        $pos = strpos($this->pantalla, 'public function generarToken(');
        $cuerpo = substr($this->pantalla, $pos, 900);
        $this->assertContains('moduloHabilitado(', $cuerpo, 'No se puede vincular sin licencia vigente');
        $this->assertContains('cantidadActivos()', $cuerpo, 'No se puede vincular con el límite de celulares completo (C18)');
    }

    public function testLaPantallaEsSoloParaAdmin(): void
    {
        preg_match_all('/public function (\w+)\(/', $this->pantalla, $m);
        foreach (array_diff($m[1], ['__construct', 'apiBase']) as $accion) {
            $cuerpo = substr($this->pantalla, strpos($this->pantalla, "public function {$accion}("), 300);
            $this->assertContains("requireRole('admin')", $cuerpo, "{$accion}() exige rol admin");
        }
    }

    public function testLasAccionesQueRecibenDatosRevisanLaLicencia(): void
    {
        foreach (['notificaciones', 'latido'] as $accion) {
            $cuerpo = substr($this->api, strpos($this->api, "public function {$accion}("), 300);
            $this->assertContains('requireLicencia()', $cuerpo, "{$accion}() responde 402 con el módulo pausado");
        }
        $this->assertContains("'modulo_no_habilitado'", $this->api, '402 modulo_no_habilitado (sección 3)');
        foreach (["'notificaciones'", "'latido'"] as $ruta) {
            $this->assertContains("\$ruta === {$ruta}", $this->index, "index.php enruta POST {$ruta}");
        }
    }

    public function testLaConciliacionNoPuedeFrenarNiLaNotificacionNiLaVenta(): void
    {
        $pos = strpos($this->api, '$recibidos->conciliarCobro($id)');
        $this->assertTrue($pos !== false && str_contains(substr($this->api, $pos - 120, 120), 'try'),
            'POST /notificaciones concilia dentro de un try');

        $ventas = file_get_contents(BASE_PATH . '/app/Controllers/VentasController.php');
        $metodo = substr($ventas, strpos($ventas, 'private function conciliarDewocCobros('), 500);
        $this->assertContains('catch (\Throwable', $metodo, 'VentasController concilia dentro de un try/catch');
        $this->assertEquals(4, substr_count($ventas, '$this->conciliarDewocCobros('),
            'Se concilia al registrar, anular, aprobar una anulación y cambiar el medio de pago');
    }

    // ── POS ──────────────────────────────────────────────────────────────────

    public function testElAvisoDelPosNoInterrumpe(): void
    {
        $parcial = file_get_contents(BASE_PATH . '/app/Views/ventas/_cobros_recibidos.php');
        // El primer <script> es el del aviso de cobro; el segundo, el pop-up de cada hora (ese
        // sí es un modal, a propósito).
        preg_match('#<script>(.*?)</script>#s', $parcial, $m);
        $codigo  = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $m[1] ?? '');
        $this->assertContains('cobros-recibidos/nuevos', $codigo, 'El primer script es el del aviso');
        $this->assertNotContains('.focus(', $codigo, 'El aviso no toma el foco (C5)');
        $this->assertNotContains('autofocus', $codigo, 'El aviso no toma el foco (C5)');
        $this->assertNotContains('modal', strtolower($codigo), 'El aviso no es un modal (C5)');
        $this->assertContains('tabindex="-1"', $parcial, 'La ✕ no entra en el orden del Tab');
        $this->assertContains('pointer-events: none', $parcial, 'La capa deja pasar los clics al POS');
        $this->assertContains('esc(', $parcial, 'El nombre se escapa');
        $this->assertContains('enCurso', $parcial, 'Nunca dos consultas a la vez');

        $pos = file_get_contents(BASE_PATH . '/app/Views/ventas/pos.php');
        $this->assertMatchesRegex("#if \(moduloHabilitado\('dewoc_cobros'\)\) \{\s*require BASE_PATH \. '/app/Views/ventas/_cobros_recibidos.php';#",
            $pos, 'Sin licencia el POS no incluye el aviso (3.0)');

        $ctrl = file_get_contents(BASE_PATH . '/app/Controllers/CobrosRecibidosController.php');
        $this->assertContains("requireCanView('ventas_pos')", $ctrl, 'El aviso es para quien tiene ventas_pos (C12)');
        $this->assertContains('session_write_close()', $ctrl, 'La consulta seguida no traba la sesión');
    }

    public function testElPopUpDeTransferenciasPendientes(): void
    {
        $parcial = file_get_contents(BASE_PATH . '/app/Views/ventas/_cobros_recibidos.php');
        $this->assertContains('const CADA_MS    = 60 * 60 * 1000;', $parcial, 'El pop-up se abre solo cada una hora');
        $this->assertContains("typeof carrito !== 'undefined' && carrito.length", $parcial,
            'Con una venta en curso no se abre (tomaría el foco del lector de códigos)');
        $this->assertContains("'dewocCobros.pendientes.' + TURNO_ID", $parcial, 'La última vez que se mostró se guarda por turno');

        $ctrl = file_get_contents(BASE_PATH . '/app/Controllers/CobrosRecibidosController.php');
        $pend = substr($ctrl, strpos($ctrl, 'public function pendientesTurno('), 900);
        $this->assertContains('getTurnoAbierto(', $pend, 'Solo las del turno abierto del cajero');
        $this->assertContains("pendientesDesde((string)\$turno['apertura_at'])", $pend,
            'Desde la apertura del turno: el cajero del turno siguiente ya no las ve');

        foreach (['asignar', 'noCorresponde'] as $accion) {
            $cuerpo = substr($ctrl, strpos($ctrl, "public function {$accion}("), 500);
            $this->assertContains('Csrf::check()', $cuerpo, "{$accion}() valida CSRF");
            $this->assertContains('requirePuedeOperar(', $cuerpo, "{$accion}() limita al cajero a su turno");
        }
        foreach (['desasignar', 'quitarNoCorresponde', 'candidatas'] as $accion) {
            $cuerpo = substr($ctrl, strpos($ctrl, "public function {$accion}("), 200);
            $this->assertContains("requireCanEdit('ventas')", $cuerpo, "{$accion}() exige permiso para editar ventas");
        }
        $asignar = substr($ctrl, strpos($ctrl, 'public function asignar('), 1500);
        $this->assertContains("canEdit('ventas')", $asignar, 'Cambiar el medio de pago al asignar exige permiso para editar ventas');
    }

    public function testLaValidacionAutomaticaRespetaLoAsignadoAMano(): void
    {
        $modelo = file_get_contents(BASE_PATH . '/app/Models/CobroRecibidoModel.php');
        $metodo = substr($modelo, strpos($modelo, 'public function conciliarVenta('), 900);
        $this->assertContains("\$ant['vinculado_por'] !== null && \$v", $metodo,
            'conciliarVenta() no pisa una asignación manual mientras la venta siga completada');
        $this->assertContains('ignorado_at IS NULL', substr($modelo, strpos($modelo, 'public function conciliarCobro('), 600),
            'Una transferencia marcada "No corresponde" no se valida sola');
    }

    // ── Migración ───────────────────────────────────────────────────────────

    public function testUnCobroValidaUnaSolaVenta(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/dewoc_cobros_conciliacion_migration.sql');
        $this->assertContains('UNIQUE KEY IF NOT EXISTS `uq_cobros_recibidos_venta` (`venta_id`)', $sql,
            'venta_id es UNIQUE: un cobro valida una venta y una venta tiene un cobro');
    }

    public function testMigracion(): void
    {
        $this->assertContains('UNIQUE KEY `uq_cobros_recibidos_uuid` (`uuid`)', $this->migracion,
            'El uuid es UNIQUE: la misma notificación dos veces deja un solo registro');
        $this->assertContains("'prueba'", $this->migracion, 'resultado acepta prueba (sección 3.2 del plan)');
        $this->assertContains("'com.mercadopago.wallet', 'Mercado Pago'", $this->migracion, 'Mercado Pago cargado de entrada');
        $this->assertNotContains('NOW()', php_strip_whitespace(BASE_PATH . '/app/Models/CobroDispositivoModel.php'),
            'Las fechas se escriben desde PHP: el MySQL de Hostinger está en UTC');
    }
}
