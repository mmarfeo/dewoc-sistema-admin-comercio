<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/IntegracionMenuAppModel.php';

/**
 * Integración con MenuApp (ver docs/integracion-menuapp-checklist.md). Mismo estilo que el
 * resto de la suite: análisis estático + reflexión, sin fixtures en la base — lo que se
 * verifica acá son las propiedades que no se pueden perder en un refactor (que la API no
 * use sesión, que el token nunca se guarde ni se loguee en claro, que el webhook no pueda
 * frenar un guardado, que las ventas de MenuApp nunca muevan la caja al aceptarse).
 */
class ApiMenuAppTest extends TestCase
{
    private string $api;
    /**
     * Igual que $api pero sin comentarios: las aserciones de "esto NO debe aparecer" tienen
     * que mirar el código real. Con el archivo crudo, un comentario que explica justamente
     * que la API no usa $_SESSION ni Csrf::check() hacía fallar el test que verifica eso.
     */
    private string $apiCodigo;
    private string $notifier;
    private string $modelo;
    private string $venta;
    private string $caja;

    public function __construct()
    {
        $this->caja     = file_get_contents(BASE_PATH . '/app/Controllers/CajaController.php');
        $this->api      = file_get_contents(BASE_PATH . '/app/Controllers/ApiMenuAppController.php');
        $this->apiCodigo = php_strip_whitespace(BASE_PATH . '/app/Controllers/ApiMenuAppController.php');
        $this->notifier = file_get_contents(BASE_PATH . '/core/MenuAppNotifier.php');
        $this->modelo   = file_get_contents(BASE_PATH . '/app/Models/IntegracionMenuAppModel.php');
        $this->venta    = file_get_contents(BASE_PATH . '/app/Models/VentaModel.php');
    }

    public function testArchivosExisten(): void
    {
        foreach ([
            '/app/Controllers/ApiMenuAppController.php',
            '/app/Controllers/IntegracionesController.php',
            '/app/Models/IntegracionMenuAppModel.php',
            '/app/Models/MenuAppCatalogoModel.php',
            '/core/MenuAppNotifier.php',
            '/core/PrecioHelper.php',
            '/database/menuapp_integracion_migration.sql',
            '/app/Views/integraciones/menuapp.php',
        ] as $f) {
            $this->assertFileExists(BASE_PATH . $f);
        }
    }

    // ── Autenticación ────────────────────────────────────────────────────────

    public function testTodasLasAccionesPublicasValidanElToken(): void
    {
        // El Router de esta app no tiene middleware: si una acción se olvida de llamar a
        // requireApiToken(), queda abierta a internet sin ninguna autenticación.
        foreach (['estado', 'catalogo', 'disponibilidad', 'ventas', 'cobrar', 'anular'] as $accion) {
            $pos = strpos($this->api, "public function {$accion}(");
            $this->assertTrue($pos !== false, "Debe existir la acción {$accion}()");
            if ($pos === false) continue;

            $cuerpo = substr($this->api, $pos, 600);
            $this->assertContains('requireApiToken', $cuerpo,
                "{$accion}() debe empezar validando el token con requireApiToken()");
        }
    }

    public function testLaApiNoUsaSesionNiCsrf(): void
    {
        $this->assertNotContains('Csrf::check', $this->apiCodigo,
            'La API se autentica por token, no por CSRF: un cliente externo no tiene sesión');
        $this->assertNotContains('$_SESSION', $this->apiCodigo,
            'La API no debe depender de la sesión de staff');
        $this->assertNotContains('requireAuth(', $this->apiCodigo,
            'La API no debe exigir sesión iniciada');
    }

    public function testElTokenSeGuardaHasheado(): void
    {
        $this->assertContains("hash('sha256', \$token)", $this->modelo,
            'En la base solo se guarda el hash del token, nunca el token en claro');
        $this->assertContains('hash_equals', $this->modelo,
            'La comparación del token debe ser en tiempo constante (hash_equals)');
    }

    public function testElTokenNoViajaAlLog(): void
    {
        // logLlamada() loguea método, ruta y resultado. Si alguna vez se le pasara el token
        // (ni completo ni recortado), quedaría en storage/logs/app.log en texto plano.
        $pos = strpos($this->api, 'private function logLlamada(');
        $this->assertTrue($pos !== false, 'Debe existir logLlamada()');
        if ($pos === false) return;

        $cuerpo = substr($this->api, $pos, 500);
        $this->assertNotContains('$token', $cuerpo, 'logLlamada() no debe recibir ni imprimir el token');
    }

    public function testTokenInvalidoRespondeConUn401(): void
    {
        $this->assertContains("'no_autorizado'], 401", $this->api,
            'Token inválido o integración apagada deben devolver 401');
    }

    public function testIntegracionInactivaEquivaleATokenInvalido(): void
    {
        $pos = strpos($this->modelo, 'public function validarToken(');
        $cuerpo = substr($this->modelo, $pos, 800);
        $this->assertContains("empty(\$config['activa'])", $cuerpo,
            'Con la integración desactivada, validarToken() debe fallar igual que con un token malo');
    }

    public function testHayLimiteDeRequestsPorMinuto(): void
    {
        $this->assertEquals(120, IntegracionMenuAppModel::RATE_LIMITE_POR_MINUTO,
            'El contrato fija 120 requests por minuto como mínimo');
        $this->assertContains('consumirRateLimit', $this->api,
            'requireApiToken() debe consumir el rate limit');
        $this->assertContains('429', $this->api, 'Pasado el límite se responde 429');
    }

    // ── Ventas (3.3) ─────────────────────────────────────────────────────────

    public function testVentasEsIdempotentePorReferencia(): void
    {
        $this->assertContains('buscarPorReferenciaExterna', $this->api,
            'POST /ventas debe buscar la referencia antes de registrar, para no duplicar');
        $this->assertContains('1062', $this->api,
            'Una carrera entre dos POST simultáneos se resuelve con el UNIQUE (error 1062)');
        $this->assertContains('referencia_externa', file_get_contents(BASE_PATH . '/database/menuapp_integracion_migration.sql'));
        $this->assertContains('uq_venta_referencia_externa', file_get_contents(BASE_PATH . '/database/menuapp_integracion_migration.sql'),
            'La migración debe crear el UNIQUE que garantiza la idempotencia');
    }

    public function testVentasNuncaConfirmaSinStock(): void
    {
        // Con confirmarSinStock = true, MenuApp podría aceptar un pedido que el comercio no
        // puede preparar y el stock quedaría en negativo sin que nadie lo decidiera.
        $this->assertContains('registrar($cabecera, $detalle, false)', $this->api,
            'La API debe llamar a registrar() con confirmarSinStock = false siempre');
        $this->assertContains("'sin_stock'", $this->api, 'Faltando materia prima se responde 409 sin_stock');
    }

    public function testVentasDeMenuAppNoMuevenLaCajaAlAceptarse(): void
    {
        // D6: lo cobrado online nunca entra a la caja física, y lo cobrado en mano entra
        // recién al entregar. registrar() solo crea el caja_movimientos si viene turno_id,
        // así que la cabecera tiene que mandarlo en null.
        $pos = strpos($this->api, '$cabecera = [');
        $this->assertTrue($pos !== false, 'Debe armarse la cabecera de la venta');
        if ($pos === false) return;

        $cuerpo = substr($this->api, $pos, 1600);
        $this->assertContains("'turno_id'         => null", $cuerpo,
            'La venta se registra sin turno: aceptar un pedido no mueve la caja');
        $this->assertContains("'origen'           => 'menuapp'", $cuerpo,
            'La venta debe quedar marcada con origen = menuapp');
    }

    public function testElTotalDeMenuAppSeControlaContraElDeDewoc(): void
    {
        $this->assertContains('total_no_coincide', $this->api,
            'Si el total de MenuApp no coincide con el de Dewoc se responde 422');
        $this->assertContains('> 0.01', $this->api,
            'La comparación de totales tiene una tolerancia de un centavo');
    }

    public function testPreciosDeMenuAppSeTomanComoFinalesConIva(): void
    {
        $this->assertContains("'precio_final_unitario' => \$precio", $this->api,
            'MenuApp manda el precio final con IVA: la venta se arma con precio_final_unitario');
        $this->assertContains('_recalcularIvaDetalle', $this->venta,
            'VentaModel debe recalcular neto e IVA en el servidor, no confiar en el llamador');
    }

    // ── Cobro y anulación (3.4 y 3.5) ────────────────────────────────────────

    public function testCobrarEsIdempotenteYRespetaLaFaltaDeTurno(): void
    {
        $pos = strpos($this->venta, 'public function cobrarEnCaja(');
        $this->assertTrue($pos !== false, 'Debe existir VentaModel::cobrarEnCaja()');
        if ($pos === false) return;

        $cuerpo = substr($this->venta, $pos, 2500);
        $this->assertContains('ya_cobrada', $cuerpo,
            'Llamar dos veces a cobrar no debe duplicar el ingreso de caja');
        $this->assertContains("'cobro_pendiente' => 1", $cuerpo,
            'Sin turno abierto, la venta queda marcada como cobro pendiente');
    }

    public function testCobrarRechazaMercadoPago(): void
    {
        $this->assertContains('metodo_no_cobrable', $this->api,
            'Lo cobrado online por Mercado Pago no entra a la caja física (D6)');
    }

    public function testAnularEsIdempotenteYRevierteLaCaja(): void
    {
        $this->assertContains('ya_anulada', $this->api, 'Anular dos veces no debe fallar ni duplicar nada');
        $this->assertContains('revertirCobroEnCaja', $this->api,
            'Si ya se había cobrado en caja, la anulación debe revertir ese ingreso');

        $pos = strpos($this->venta, 'public function revertirCobroEnCaja(');
        $cuerpo = substr($this->venta, $pos, 1200);
        $this->assertContains("tipo = 'egreso'", $cuerpo,
            'La reversión deja un egreso que compensa, no borra el movimiento original');
    }

    // ── Webhook (2.1) ────────────────────────────────────────────────────────

    public function testElWebhookNoPuedeFrenarNiRomperUnGuardado(): void
    {
        $this->assertContains('CURLOPT_TIMEOUT', $this->notifier, 'El aviso debe tener timeout');
        $this->assertContains('const TIMEOUT = 3', $this->notifier, 'El timeout del contrato es de 3 segundos');
        $this->assertContains('catch (\Throwable', $this->notifier,
            'El notificador no debe poder lanzar excepciones al llamador');
        $this->assertNotContains('throw ', php_strip_whitespace(BASE_PATH . '/core/MenuAppNotifier.php'),
            'El notificador nunca lanza: solo registra en el log');
    }

    public function testElWebhookVaFirmadoConHmac(): void
    {
        $this->assertContains("hash_hmac('sha256', \$cuerpo", $this->notifier,
            'La firma se calcula con HMAC-SHA256 sobre el cuerpo crudo');
        $this->assertContains('X-Signature', $this->notifier,
            'La firma viaja en el header X-Signature');
    }

    public function testElWebhookNoSeEnviaConLaIntegracionApagada(): void
    {
        $this->assertContains("empty(\$config['activa'])", $this->notifier,
            'Con la integración desactivada no se manda ningún aviso de catálogo');
    }

    public function testSeAvisaDesdeTodosLosPuntosQueCambianElCatalogo(): void
    {
        $puntos = [
            '/app/Controllers/ProductosController.php',
            '/app/Controllers/CategoriasController.php',
            '/app/Controllers/OpcionesGrupoController.php',
            '/app/Controllers/ChatController.php',
            '/app/Controllers/RecetasBorradorController.php',
        ];
        foreach ($puntos as $f) {
            $this->assertContains('MenuAppNotifier::catalogoCambio', file_get_contents(BASE_PATH . $f),
                basename($f) . ' debe avisar a MenuApp cuando cambia el catálogo');
        }
    }

    // ── Catálogo y disponibilidad (3.1 y 3.2) ────────────────────────────────

    public function testElPrecioDelCatalogoSaleDelHelperUnico(): void
    {
        $this->assertContains('PrecioHelper::precioFinalOnline', $this->api,
            'El precio del catálogo se calcula con PrecioHelper, nunca repitiendo la cuenta');
    }

    public function testLaDisponibilidadReusaLaLogicaDeLaVenta(): void
    {
        $this->assertContains('disponibilidadProducto', $this->api);
        $this->assertContains('disponibilidadOpcion', $this->api);

        // disponibilidadOpcion() tiene que usar el MISMO cálculo que registrar(), si no la
        // API puede decir "disponible" y después la venta fallar con 409.
        $pos = strpos($this->venta, 'public function disponibilidadOpcion(');
        $cuerpo = substr($this->venta, $pos, 900);
        $this->assertContains('_necesarioOpcion', $cuerpo);
        $this->assertContains('_faltanteOpcion', $cuerpo);
    }

    // -- Etapa 6: caja e identidad de la instalacion (D10 a D13) --------------

    public function testElIdDeInstalacionEsFijoYNoSeRegenera(): void
    {
        $pos = strpos($this->modelo, 'public function instalacionId(');
        $this->assertTrue($pos !== false, 'Debe existir IntegracionMenuAppModel::instalacionId()');
        if ($pos === false) return;

        $cuerpo = substr($this->modelo, $pos, 900);
        $this->assertContains('if ($actual) return', $cuerpo,
            'Si ya hay un UUID guardado se devuelve ese: no se genera uno nuevo');
        $this->assertContains('instalacion_id IS NULL', $cuerpo,
            'El UPDATE solo escribe si esta vacio, para que dos requests simultaneas no lo pisen');

        // Regenerar el token no puede tocar el UUID: si cambiara, MenuApp veria una
        // instalacion nueva y permitiria conectarla a un segundo comercio (D11).
        $posToken    = strpos($this->modelo, 'public function generarToken(');
        $cuerpoToken = substr($this->modelo, $posToken, 600);
        $this->assertNotContains('instalacion_id', $cuerpoToken,
            'generarToken() NO debe tocar instalacion_id');
    }

    public function testElUuidTieneFormatoV4(): void
    {
        $ref = new ReflectionMethod(IntegracionMenuAppModel::class, 'uuidV4');
        $ref->setAccessible(true);
        for ($i = 0; $i < 5; $i++) {
            $this->assertMatchesRegex(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $ref->invoke(null),
                'uuidV4() debe devolver un UUID version 4 valido'
            );
        }
    }

    public function testEstadoInformaIdentidadYCaja(): void
    {
        $pos    = strpos($this->api, 'public function estado(');
        $cuerpo = substr($this->api, $pos, 1600);
        foreach (['instalacionId', 'NEGOCIO_NOMBRE', 'AFIP_CUIT', 'turnos_abiertos', 'webhook_url', 'version'] as $clave) {
            $this->assertContains($clave, $cuerpo, "GET /estado debe informar {$clave}");
        }
        $this->assertContains('aperturaDelTurnoAbiertoMasViejo', $cuerpo,
            'caja.desde es la apertura del turno abierto mas viejo');
    }

    public function testLaCajaCerradaBloqueaVentasNuevasPeroNoLosReintentos(): void
    {
        $pos    = strpos($this->api, 'public function ventas(');
        $cuerpo = substr($this->api, $pos, 2200);

        $posIdempotencia = strpos($cuerpo, 'buscarPorReferenciaExterna');
        $posCajaCerrada  = strpos($cuerpo, 'caja_cerrada');
        $this->assertTrue($posIdempotencia !== false && $posCajaCerrada !== false,
            'ventas() debe chequear la referencia existente y la caja cerrada');
        if ($posIdempotencia === false || $posCajaCerrada === false) return;

        // El orden importa: la idempotencia va PRIMERO, para que un reintento de un pedido
        // aceptado con la caja abierta devuelva la venta aunque la caja ya este cerrada.
        $this->assertTrue($posIdempotencia < $posCajaCerrada,
            'El chequeo de referencia existente debe ir ANTES del de caja cerrada');

        $posItems = strpos($cuerpo, 'items no puede estar');
        $this->assertTrue($posItems !== false && $posCajaCerrada < $posItems,
            'La caja cerrada se controla antes de validar los items y el stock');
    }

    public function testLosAvisosDeCajaSoloSalenEnElCambioReal(): void
    {
        foreach (['cajaAbierta', 'cajaCerrada'] as $metodo) {
            $this->assertContains("function {$metodo}(", $this->notifier,
                "MenuAppNotifier debe tener {$metodo}()");
        }

        // Abrir el segundo turno, o cerrar uno de dos, no cambia nada para MenuApp: solo
        // mira si hay AL MENOS un turno abierto. Un aviso de mas le cerraria el menu.
        $this->assertContains('$eraLaPrimeraCaja = !$this->model->hayTurnoAbierto();', $this->caja,
            'El aviso de apertura se decide ANTES de abrir, mirando si ya habia turno');
        $this->assertContains('if ($eraLaPrimeraCaja) MenuAppNotifier::cajaAbierta();', $this->caja,
            'Solo se avisa al abrir el primer turno');
        $this->assertContains('if (!$this->model->hayTurnoAbierto()) MenuAppNotifier::cajaCerrada();', $this->caja,
            'Solo se avisa al cerrar el ultimo turno');
    }

    public function testElAvisoDeCajaVaDespuesDeAbrirYCerrar(): void
    {
        // Si el webhook fuera antes, un MenuApp caido podria frenar la caja del comercio.
        $posAbrir = strpos($this->caja, '$id = $this->model->abrir(');
        $posAviso = strpos($this->caja, 'MenuAppNotifier::cajaAbierta();');
        $this->assertTrue($posAbrir !== false && $posAviso !== false && $posAbrir < $posAviso,
            'cajaAbierta() se envia DESPUES de abrir el turno');

        $posCerrar = strpos($this->caja, '$resultado = $this->model->cerrar(');
        $posAviso2 = strpos($this->caja, 'MenuAppNotifier::cajaCerrada();');
        $this->assertTrue($posCerrar !== false && $posAviso2 !== false && $posCerrar < $posAviso2,
            'cajaCerrada() se envia DESPUES de cerrar el turno');
    }

    public function testSeAvisaAlCerrarConPedidosDeMenuAppSinCobrar(): void
    {
        $this->assertContains('contarCobrosPendientesMenuApp', $this->caja,
            'preverCierre() debe informar cuantos pedidos de MenuApp quedan sin cobrar');
        $this->assertContains("origen = 'menuapp'", $this->venta,
            'El conteo filtra por origen menuapp, no por cualquier cobro pendiente');

        $vista = file_get_contents(BASE_PATH . '/app/Views/caja/index.php');
        $this->assertContains('avisoMenuAppPendientes', $vista,
            'La vista debe mostrar el aviso antes de confirmar el cierre');
        $this->assertContains('como cobro pendiente para el', $vista,
            'El aviso explica que pasa si se cierra igual');
    }

    public function testLaPantallaOfreceMenuAppCuandoNoEstaActiva(): void
    {
        // Promocion cruzada (D13), pero solo mientras no este conectada: con la integracion
        // activa el aviso ya no aporta y ensucia una pantalla de configuracion.
        $vista = file_get_contents(BASE_PATH . '/app/Views/integraciones/menuapp.php');
        $this->assertContains('if (!$activa)', $vista,
            'La tarjeta de promocion se muestra solo con la integracion inactiva');
        $this->assertContains('https://menuapp.dewoc.com', $vista,
            'La tarjeta enlaza a MenuApp');
    }

    // -- Migracion ------------------------------------------------------------

    public function testLaMigracionEsIdempotente(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/menuapp_integracion_migration.sql');
        foreach (['precio_online', 'precios_incluyen_iva', 'origen', 'cobro_pendiente',
                  'integracion_menuapp', 'instalacion_id'] as $pieza) {
            $this->assertContains($pieza, $sql, "La migración debe crear {$pieza}");
        }
        $this->assertContains('IF NOT EXISTS', $sql,
            'La migración se tiene que poder volver a correr sin romper nada');
    }
}
