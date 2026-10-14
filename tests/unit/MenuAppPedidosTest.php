<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/MenuAppPedidoModel.php';
require_once __DIR__ . '/../../core/MenuAppClient.php';

/**
 * Atender los pedidos de MenuApp desde el POS (etapa 9, decisiones D14 a D17).
 *
 * Lo más importante que se verifica acá es la **firma de 3.9**, porque es el contrato con
 * el otro repositorio: si el orden de concatenación o el separador cambian, TODAS las
 * llamadas fallan del otro lado y el error se ve como "firma inválida", que no dice dónde
 * está el problema. El vector de prueba de abajo es fijo y reproducible, así que MenuApp
 * puede usar el mismo para comprobar su validador.
 */
class MenuAppPedidosTest extends TestCase
{
    private string $cliente;
    private string $modelo;
    private string $controlador;
    private string $api;
    private string $vista;

    public function __construct()
    {
        $this->cliente     = file_get_contents(BASE_PATH . '/core/MenuAppClient.php');
        $this->modelo      = file_get_contents(BASE_PATH . '/app/Models/MenuAppPedidoModel.php');
        $this->controlador = file_get_contents(BASE_PATH . '/app/Controllers/MenuAppPedidosController.php');
        $this->api         = file_get_contents(BASE_PATH . '/app/Controllers/ApiMenuAppController.php');
        $this->vista       = file_get_contents(BASE_PATH . '/app/Views/ventas/_menuapp_pedidos.php');
    }

    /**
     * Cuerpo de una función JS declarada al nivel superior del archivo, hasta su "}" de
     * cierre en la columna 0. Con una ventana fija de N caracteres se colaba la función
     * siguiente y el test medía otra cosa.
     */
    private function cuerpoDeFuncionJs(string $src, string $nombre): ?string
    {
        $inicio = strpos($src, "function {$nombre}(");
        if ($inicio === false) return null;

        $fin = strpos($src, "
}", $inicio);
        return $fin === false ? substr($src, $inicio) : substr($src, $inicio, $fin - $inicio);
    }

    public function testArchivosExisten(): void
    {
        foreach ([
            '/core/MenuAppClient.php',
            '/app/Models/MenuAppPedidoModel.php',
            '/app/Controllers/MenuAppPedidosController.php',
            '/app/Views/ventas/_menuapp_pedidos.php',
            '/database/menuapp_pedidos_migration.sql',
        ] as $f) {
            $this->assertFileExists(BASE_PATH . $f);
        }
    }

    // ── 3.9 · La firma, que es el contrato con MenuApp ──────────────────────

    /**
     * Vector fijo: con este secreto, este timestamp y este cuerpo, la firma tiene que ser
     * exactamente esta. Si MenuApp calcula otra cosa con los mismos datos, el problema está
     * en uno de los dos validadores y no hay que adivinar cuál.
     */
    public function testElVectorDeFirmaEsEstable(): void
    {
        $secreto   = 'secreto-compartido';
        $timestamp = '1760000000';
        $cuerpo    = '{"usuario":{"id":7,"nombre":"Juan"}}';

        $this->assertEquals(
            'd3383d3b56d0ed4330f7cc10802b50e7d009ca36a0e8a2cf40aa37921a01098e',
            hash_hmac('sha256', $timestamp . '.' . $cuerpo, $secreto),
            'La firma de 3.9 es HMAC-SHA256 de timestamp + "." + cuerpo crudo, en hexadecimal'
        );
    }

    public function testElClienteFirmaConElFormatoDelContrato(): void
    {
        $this->assertContains("hash_hmac('sha256', \$timestamp . '.' . \$raw", $this->cliente,
            'Se firma timestamp + punto + cuerpo crudo, en ese orden');

        foreach (['X-Dewoc-Instalacion', 'X-Timestamp', 'X-Signature'] as $header) {
            $this->assertContains($header, $this->cliente, "Falta el header {$header} de 3.9");
        }
    }

    public function testSeFirmaYSeEnviaElMISMOCuerpoCrudo(): void
    {
        // Si se volviera a serializar después de firmar, cualquier diferencia de orden de
        // claves o de escape de acentos rompería la firma del otro lado.
        $pos    = strpos($this->cliente, 'private static function pedir(');
        $cuerpo = substr($this->cliente, $pos, 2600);

        $this->assertContains('$raw', $cuerpo);
        $this->assertContains('CURLOPT_POSTFIELDS, $raw', $cuerpo,
            'El cuerpo que se envía tiene que ser la misma variable que se firmó');
    }

    public function testElSecretoEsElMismoQueElDeLosAvisos(): void
    {
        $this->assertContains("config['webhook_secret']", $this->cliente,
            'El contrato dice que la firma usa el mismo secreto que ya comparten para los avisos');
    }

    public function testElClienteNoLogueaLaFirmaNiElSecreto(): void
    {
        $pos    = strpos($this->cliente, 'logApp(');
        $linea  = substr($this->cliente, $pos, 200);
        $this->assertNotContains('$firma', $linea,
            'La firma no va al log: junto con el cuerpo permitiría atacar el secreto');
        $this->assertNotContains('webhook_secret', $linea);
    }

    public function testElTimeoutEsMasLargoQueElDelNotificador(): void
    {
        // El notificador avisa y se olvida (3 s); acá hay un cajero esperando la respuesta
        // para saber si el pedido quedó aceptado.
        $this->assertEquals(10, MenuAppClient::TIMEOUT, 'El contrato fija 10 segundos');
    }

    public function testCadaAccionMandaElUsuarioDeLaCaja(): void
    {
        foreach (['aceptar', 'rechazar', 'proponer', 'enviarMensaje'] as $metodo) {
            $pos    = strpos($this->cliente, "function {$metodo}(");
            $cuerpo = substr($this->cliente, $pos, 700);
            $this->assertContains('usuarioActual()', $cuerpo,
                "{$metodo}() debe mandar el usuario de la caja (3.9)");
        }
    }

    // ── 9.2 · Lo que Dewoc recibe ───────────────────────────────────────────

    public function testRecibirUnPedidoNoRegistraNingunaVenta(): void
    {
        // La venta la registra MenuApp al aceptar, con POST /ventas. Si este endpoint
        // tocara el stock, un pedido que después se rechaza lo dejaría descontado.
        $pos    = strpos($this->api, 'public function pedidos(');
        $cuerpo = substr($this->api, $pos, 2000);

        $this->assertNotContains('registrar(', $cuerpo,
            'POST /pedidos no registra la venta: solo crea la alerta');
        $this->assertNotContains('VentaModel', $cuerpo);
    }

    public function testRecibirUnPedidoEsIdempotente(): void
    {
        $pos    = strpos($this->api, 'public function pedidos(');
        $cuerpo = substr($this->api, $pos, 2000);
        $this->assertContains('guardarDesdeMenuApp', $cuerpo);

        $sql = file_get_contents(BASE_PATH . '/database/menuapp_pedidos_migration.sql');
        $this->assertContains('uq_map_referencia', $sql,
            'El UNIQUE por referencia es lo que impide duplicar la alerta');
    }

    public function testUnReintentoNoReabreLaAlertaDeUnPedidoYaAtendido(): void
    {
        $pos    = strpos($this->modelo, 'public function guardarDesdeMenuApp(');
        $cuerpo = substr($this->modelo, $pos, 1800);
        $this->assertContains("unset(\$campos['estado']", $cuerpo,
            'Si el pedido ya existía, el reintento no vuelve a ponerlo en "requiere acción"');
    }

    public function testUnaNovedadDeUnPedidoDesconocidoDevuelve404(): void
    {
        $pos    = strpos($this->api, 'public function novedades(');
        $cuerpo = substr($this->api, $pos, 1200);
        $this->assertContains('pedido_inexistente', $cuerpo);
        $this->assertContains('404', $cuerpo,
            'El contrato dice 404 para que MenuApp lo ignore');
    }

    public function testLosTresTiposDeNovedadEstanCubiertos(): void
    {
        $pos    = strpos($this->api, 'public function novedades(');
        $cuerpo = substr($this->api, $pos, 3000);
        foreach (['estado', 'mensaje', 'repartidor'] as $tipo) {
            $this->assertContains("case '{$tipo}':", $cuerpo, "Falta el tipo de novedad '{$tipo}'");
        }
    }

    public function testElTextoDeLosMensajesNoSeGuardaEnDewoc(): void
    {
        // Las conversaciones viven en MenuApp (D17). Guardarlas acá sería tener dos copias
        // de lo mismo desincronizándose; Dewoc solo lleva el CONTADOR de no leídos.
        $pos    = strpos($this->api, "case 'mensaje':");
        $cuerpo = substr($this->api, $pos, 900);
        $this->assertContains('sumarMensajeSinLeer', $cuerpo);

        $sql = file_get_contents(BASE_PATH . '/database/menuapp_pedidos_migration.sql');
        $this->assertContains('cantidad', $sql);
        $this->assertNotContains('`texto`', $sql,
            'La tabla de no leídos no guarda el texto de los mensajes');
    }

    public function testElPushNoPuedeRomperLaRecepcionDelPedido(): void
    {
        $pos    = strpos($this->api, 'private function avisarPorPush(');
        $cuerpo = substr($this->api, $pos, 1500);
        $this->assertContains('catch (\Throwable', $cuerpo,
            'Si el push falla, el pedido ya quedó guardado y el POS lo ve en su sondeo');
    }

    // ── 9.4 a 9.7 · El POS ──────────────────────────────────────────────────

    public function testTodasLasAccionesDelPosPidenPermisoDePos(): void
    {
        foreach (['pendienteParaMi', 'hayMensajesSinLeer', 'lista', 'aceptar', 'rechazar',
                  'proponer', 'mensajes', 'enviarMensaje', 'respuestasRapidas',
                  'catalogoParaPropuesta'] as $accion) {
            $pos = strpos($this->controlador, "public function {$accion}(");
            $this->assertTrue($pos !== false, "Debe existir {$accion}()");
            if ($pos === false) continue;

            $cuerpo = substr($this->controlador, $pos, 300);
            $this->assertContains("requireCanView('ventas_pos')", $cuerpo,
                "{$accion}() debe exigir el permiso ventas_pos (D16)");
        }
    }

    public function testLasAccionesQueEscribenValidanCsrf(): void
    {
        foreach (['aceptar', 'rechazar', 'proponer', 'enviarMensaje'] as $accion) {
            $pos    = strpos($this->controlador, "public function {$accion}(");
            $cuerpo = substr($this->controlador, $pos, 400);
            $this->assertContains('Csrf::check()', $cuerpo,
                "{$accion}() se llama desde el POS con sesión: necesita CSRF");
        }
    }

    public function testDewocNoDecideSoloLePideAMenuApp(): void
    {
        // D16: aceptar, rechazar y proponer son de MenuApp, que cobra, reintegra, avisa al
        // cliente y registra la venta. Si Dewoc lo resolviera localmente, los dos sistemas
        // quedarían contando distinto.
        foreach (['aceptar' => 'MenuAppClient::aceptar',
                  'rechazar' => 'MenuAppClient::rechazar',
                  'proponer' => 'MenuAppClient::proponer'] as $accion => $llamada) {
            $pos    = strpos($this->controlador, "public function {$accion}(");
            $cuerpo = substr($this->controlador, $pos, 2600);
            $this->assertContains($llamada, $cuerpo, "{$accion}() debe delegar en MenuApp");
        }
    }

    public function testSiMenuAppRechazaElPedidoSigueEsperando(): void
    {
        // Con sin_stock o caja_cerrada el pedido NO se marca como resuelto: la alerta tiene
        // que volver para que el comercio lo edite o lo rechace.
        $pos    = strpos($this->controlador, 'public function aceptar(');
        $cuerpo = substr($this->controlador, $pos, 1200);

        $posFallo   = strpos($cuerpo, "if (!\$r['ok'])");
        $posReflejo = strpos($cuerpo, 'reflejarRespuesta');
        $this->assertTrue($posFallo !== false && $posReflejo !== false && $posFallo < $posReflejo,
            'El estado local se actualiza solo si MenuApp aceptó');
    }

    public function testLosNoLeidosSeBajanDespuesDeLeerDeVerdad(): void
    {
        $pos    = strpos($this->controlador, 'public function mensajes(');
        $cuerpo = substr($this->controlador, $pos, 1400);

        $posLlamada = strpos($cuerpo, 'MenuAppClient::mensajes');
        $posMarcar  = strpos($cuerpo, 'marcarLeidos');
        $this->assertTrue($posLlamada !== false && $posMarcar !== false && $posLlamada < $posMarcar,
            'Si MenuApp no responde, el badge tiene que seguir ahí');
    }

    public function testLaAlertaSeVaSolaAlVencer(): void
    {
        // Red de seguridad de 3.7: si la novedad de vencimiento no llega, la alerta no puede
        // quedar trabada para siempre.
        $pos    = strpos($this->modelo, 'public function pendienteMasViejo(');
        $cuerpo = substr($this->modelo, $pos, 800);
        $this->assertContains('vence_en IS NULL OR vence_en > NOW()', $cuerpo,
            'El pendiente deja de aparecer pasado vence_en');

        $this->assertContains('mapRenderCuentaRegresiva', $this->vista,
            'El POS muestra la cuenta regresiva y cierra el modal al llegar a cero');
    }

    public function testLaAlertaSeVaCuandoElEstadoYaNoRequiereAccion(): void
    {
        $pos    = strpos($this->modelo, 'public function actualizarEstado(');
        $cuerpo = substr($this->modelo, $pos, 900);
        $this->assertContains('ESTADOS_REQUIEREN_ACCION', $cuerpo,
            'requiere_accion se deriva del estado, así aceptar desde MenuApp también saca la alerta');
    }

    public function testLaPropuestaUsaElCatalogoDelMenuYNoValidaLosLimitesLocalmente(): void
    {
        $pos    = strpos($this->controlador, 'public function catalogoParaPropuesta(');
        $cuerpo = substr($this->controlador, $pos, 1400);
        $this->assertContains('MenuAppCatalogoModel', $cuerpo,
            'Solo se ofrecen productos del menú online');
        $this->assertContains('PrecioHelper::precioFinalOnline', $cuerpo,
            'El precio que se muestra es el que ve el cliente (D9)');

        // Los mínimos y máximos de cada grupo los valida MenuApp: duplicar esa regla acá
        // sería tener dos verdades que se desincronizan.
        $posProponer = strpos($this->controlador, 'public function proponer(');
        $cuerpoProp  = substr($this->controlador, $posProponer, 2600);
        $this->assertContains('422', $cuerpoProp,
            'Si MenuApp rechaza la propuesta por los límites de gustos, se muestra su motivo');
    }

    public function testLaRespuestaRapidaQuedaEnElCuadroParaEditarla(): void
    {
        $cuerpo = $this->cuerpoDeFuncionJs($this->vista, 'mapUsarRespuesta');
        $this->assertTrue($cuerpo !== null, 'Debe existir mapUsarRespuesta()');
        if ($cuerpo === null) return;

        $this->assertContains("getElementById('mapChatTexto').value", $cuerpo,
            'Elegir una respuesta rápida la pone en el cuadro, no la envía (9.7)');
        $this->assertNotContains('mapEnviarMensaje', $cuerpo,
            'No debe enviarse sola: el contrato pide poder retocarla');
    }

    public function testLasDosConversacionesEstanSeparadas(): void
    {
        $this->assertContains("mapCambiarConversacion('cliente')", $this->vista);
        $this->assertContains("mapCambiarConversacion('repartidor')", $this->vista);

        // El servidor también valida: no alcanza con las pestañas de la pantalla.
        $pos    = strpos($this->controlador, 'private function conversacionValida(');
        $cuerpo = substr($this->controlador, $pos, 500);
        $this->assertContains("['cliente', 'repartidor']", $cuerpo,
            'Cualquier otro valor de conversación se rechaza en el servidor');
    }

    public function testElSondeoSeCortaSiLaEtapaNoEstaDesplegada(): void
    {
        // Mismo criterio que los pedidos mayoristas: si la ruta da 404 porque el servidor
        // todavía no tiene esta etapa, se corta el intervalo en vez de insistir cada 30 s.
        $this->assertContains('clearInterval(_mapIntervalPedido)', $this->vista);
        $this->assertContains('clearInterval(_mapIntervalChat)', $this->vista);
        $this->assertContains('30000', $this->vista, 'El sondeo es cada 30 segundos, como los mayoristas');
    }

    // ── Migración ───────────────────────────────────────────────────────────

    public function testLaMigracionEsIdempotenteYCreaLoQueHaceFalta(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/menuapp_pedidos_migration.sql');
        foreach (['menuapp_pedidos', 'menuapp_mensajes_no_leidos', 'referencia', 'datos',
                  'requiere_accion', 'repartidor', 'vence_en', 'atendido_por_usuario_id',
                  'api_base_url'] as $pieza) {
            $this->assertContains($pieza, $sql, "La migración debe crear {$pieza}");
        }
        $this->assertContains('IF NOT EXISTS', $sql, 'Se puede volver a correr sin romper nada');
    }
}
