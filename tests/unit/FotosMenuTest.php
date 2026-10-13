<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/ImagenHelper.php';
require_once __DIR__ . '/../../app/Models/Model.php';
require_once __DIR__ . '/../../app/Models/FotoMenuModel.php';

/**
 * Fotos del menú online (ver docs/integracion-menuapp-checklist.md, etapa 8). Mismo estilo
 * que el resto de la suite: análisis estático + reflexión, sin fixtures en la base, salvo
 * ImagenHelper, que sí se ejecuta de verdad con imágenes generadas al vuelo.
 *
 * Lo que se verifica son las propiedades que no se pueden perder en un refactor: que el
 * tope de 3 candidatas se controle en el servidor, que la IA no pueda cambiar el producto,
 * que publicar exija el permiso más alto, y que una foto grande o rotada no termine mal en
 * el menú del cliente.
 */
class FotosMenuTest extends TestCase
{
    private string $controlador;
    private string $modelo;
    private string $productos;
    private string $galeria;
    private string $vistaPreview;

    public function __construct()
    {
        $this->controlador  = file_get_contents(BASE_PATH . '/app/Controllers/FotosMenuController.php');
        $this->modelo       = file_get_contents(BASE_PATH . '/app/Models/FotoMenuModel.php');
        $this->productos    = file_get_contents(BASE_PATH . '/app/Controllers/ProductosController.php');
        $this->galeria      = file_get_contents(BASE_PATH . '/app/Controllers/GaleriaController.php');
        $this->vistaPreview = file_get_contents(BASE_PATH . '/app/Views/galeria/fotos-menu-producto.php');
    }

    public function testArchivosExisten(): void
    {
        foreach ([
            '/app/Controllers/FotosMenuController.php',
            '/app/Models/FotoMenuModel.php',
            '/app/Views/galeria/fotos-menu.php',
            '/app/Views/galeria/fotos-menu-producto.php',
            '/core/ImagenHelper.php',
            '/core/GeminiImagen.php',
            '/cron/fotos_menu_limpiar.php',
            '/database/fotos_menu_migration.sql',
        ] as $f) {
            $this->assertFileExists(BASE_PATH . $f);
        }
    }

    // ── 8.1 · Migración ─────────────────────────────────────────────────────

    public function testLaMigracionCreaLoQueHaceFalta(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/fotos_menu_migration.sql');
        foreach (['producto_id', 'estado_menu', 'mejorada_ia', 'origen_id',
                  'candidata', 'oficial', 'anterior', 'descartada'] as $pieza) {
            $this->assertContains($pieza, $sql, "La migración debe crear/definir {$pieza}");
        }
        $this->assertContains('IF NOT EXISTS', $sql, 'Las columnas se agregan de forma idempotente');
        $this->assertContains('ON DELETE SET NULL', $sql,
            'Borrar un producto no debe borrar sus fotos, solo desvincularlas');
    }

    // ── 8.5 · Tope de 3 candidatas, controlado en el servidor ───────────────

    public function testElTopeDeCandidatasEsTres(): void
    {
        $this->assertEquals(3, FotoMenuModel::MAX_CANDIDATAS, 'El contrato fija 3 candidatas por producto');
    }

    public function testElTopeSeControlaEnElServidorEnLosDosCaminos(): void
    {
        // Deshabilitar los botones no alcanza: con solo eso, bastaba con llamar al endpoint
        // a mano para subir sin límite o gastar IA sin techo.
        foreach (['subirCandidata', 'mejorar'] as $accion) {
            $pos = strpos($this->controlador, "public function {$accion}(");
            $this->assertTrue($pos !== false, "Debe existir {$accion}()");
            if ($pos === false) continue;

            $cuerpo = substr($this->controlador, $pos, 2600);
            $this->assertContains('hayLugarParaCandidata', $cuerpo,
                "{$accion}() debe chequear el tope en el servidor");
        }
    }

    public function testLaOficialNoCuentaParaElTope(): void
    {
        $pos    = strpos($this->modelo, 'public function contarCandidatas(');
        $cuerpo = substr($this->modelo, $pos, 400);
        $this->assertContains("estado_menu = 'candidata'", $cuerpo,
            'El tope cuenta solo candidatas: la oficial y la anterior no ocupan lugar');
    }

    public function testDescartarNoBorraElArchivo(): void
    {
        $pos    = strpos($this->modelo, 'public function descartar(');
        $cuerpo = substr($this->modelo, $pos, 500);
        $this->assertContains("estado_menu = 'descartada'", $cuerpo,
            'Descartar cambia el estado, no borra el archivo');
        $this->assertNotContains('unlink', $cuerpo,
            'El borrado físico lo hace el cron a los 30 días, no el descarte');

        $this->assertEquals(30, FotoMenuModel::DIAS_RETENCION_DESCARTADAS,
            'El contrato fija 30 días de retención');
        $this->assertContains('unlink', file_get_contents(BASE_PATH . '/cron/fotos_menu_limpiar.php'),
            'El cron sí borra del disco');
    }

    // ── 8.6 · La IA no puede cambiar el producto ────────────────────────────

    public function testLaIaUsaOpcionesFijasYNoTextoLibre(): void
    {
        // Con instrucción libre el modelo termina agregando toppings o cambiando la porción,
        // y la foto deja de representar lo que el cliente va a recibir.
        $this->assertContains('MEJORAS_IA', $this->controlador, 'Las opciones de mejora son una lista fija');

        $pos    = strpos($this->controlador, 'public function mejorar(');
        $cuerpo = substr($this->controlador, $pos, 1200);
        $this->assertContains('isset(self::MEJORAS_IA[$opcion])', $cuerpo,
            'Solo se aceptan las opciones de la lista; cualquier otra es 422');
        $this->assertNotContains("input('instruccion'", $cuerpo,
            'mejorar() no debe aceptar una instrucción libre del usuario');
    }

    public function testTodaInstruccionDeIaLlevaLaGuardaDelProducto(): void
    {
        $this->assertContains('GUARDA_IA', $this->controlador);
        $this->assertContains('No agregues, quites ni cambies el producto', $this->controlador,
            'La guarda tiene que estar textual, es lo único que evita que la IA invente');

        $pos    = strpos($this->controlador, '$instruccion = self::MEJORAS_IA');
        $linea  = substr($this->controlador, $pos, 140);
        $this->assertContains('self::GUARDA_IA', $linea,
            'La guarda se concatena SIEMPRE a la instrucción, no es opcional');
    }

    public function testLaMejoraEsOtraCandidataYNoPisaLaOriginal(): void
    {
        $pos    = strpos($this->controlador, 'public function mejorar(');
        $cuerpo = substr($this->controlador, $pos, 3200);
        $this->assertContains('registrarCandidata', $cuerpo,
            'El resultado de la IA entra como otra candidata, para poder comparar');
        $this->assertContains('$fotoId', $cuerpo, 'Se guarda de qué foto salió (origen_id)');
    }

    public function testSiLaIaNoDevuelveImagenNoSeCreaNada(): void
    {
        $pos    = strpos($this->controlador, 'public function mejorar(');
        $cuerpo = substr($this->controlador, $pos, 2800);
        $this->assertContains("empty(\$resultado['imagen'])", $cuerpo,
            'Si el modelo responde solo con texto, se muestra el mensaje y no se guarda nada');
    }

    public function testHayTopeDiarioDeIaPorUsuario(): void
    {
        $this->assertContains('mejorasIaRestantes', $this->controlador);
        $this->assertContains('mejorasIaDeHoy', $this->modelo);

        $pos    = strpos($this->controlador, 'public function mejorar(');
        $cuerpo = substr($this->controlador, $pos, 2000);
        $this->assertContains('429', $cuerpo, 'Pasado el tope diario se responde 429');
    }

    // ── 8.7 y 8.8 · Permisos y publicación ─────────────────────────────────

    public function testPublicarExigeElPermisoMasAlto(): void
    {
        // La idea de los dos permisos es que un empleado proponga y alguien con criterio
        // decida qué ve el cliente. Si publicar pidiera 'fotos_menu', no servirían de nada.
        foreach (['elegirOficial', 'volverAnterior'] as $accion) {
            $pos    = strpos($this->controlador, "public function {$accion}(");
            $cuerpo = substr($this->controlador, $pos, 300);
            $this->assertContains("requireCanEdit('fotos_menu_publicar')", $cuerpo,
                "{$accion}() debe exigir fotos_menu_publicar");
        }

        foreach (['subirCandidata', 'mejorar', 'descartar'] as $accion) {
            $pos    = strpos($this->controlador, "public function {$accion}(");
            $cuerpo = substr($this->controlador, $pos, 300);
            $this->assertContains("requireCanEdit('fotos_menu')", $cuerpo,
                "{$accion}() alcanza con fotos_menu: es lo que hace un empleado");
        }
    }

    public function testLosPermisosEstanRegistrados(): void
    {
        $permisos = file_get_contents(BASE_PATH . '/app/Models/PermisosModel.php');
        foreach (['fotos_menu', 'fotos_menu_publicar'] as $p) {
            $this->assertContains("'{$p}'", $permisos, "El módulo {$p} debe estar en PermisosModel");
        }
    }

    public function testLaGaleriaYLaIaYaNoAlcanzanConTenerSesion(): void
    {
        // Antes la Galería solo pedía requireAuth(): cualquier usuario logueado podía gastar
        // IA de imagen y de video, que se paga por llamada (tarea 8.8).
        foreach (['generar', 'generarVideo', 'subir', 'eliminar'] as $accion) {
            $pos    = strpos($this->galeria, "public function {$accion}(");
            $this->assertTrue($pos !== false, "Debe existir GaleriaController::{$accion}()");
            if ($pos === false) continue;

            $cuerpo = substr($this->galeria, $pos, 400);
            $this->assertContains("requireCanEdit('galeria')", $cuerpo,
                "GaleriaController::{$accion}() debe exigir permiso de edición, no solo sesión");
        }
    }

    public function testPublicarAvisaAMenuAppYQuedaEnAuditoria(): void
    {
        $pos    = strpos($this->controlador, 'private function publicar(');
        $cuerpo = substr($this->controlador, $pos, 3000);

        $this->assertContains('MenuAppNotifier::catalogoCambio', $cuerpo,
            'Publicar una foto tiene que avisarle a MenuApp para que la descargue');
        $this->assertContains('AuditLog::log', $cuerpo,
            'Queda registrado quién publicó la foto');
        $this->assertContains('mejorada_ia', $cuerpo,
            'La auditoría anota si la foto venía mejorada con IA');

        // El aviso va DESPUÉS de guardar: si MenuApp está caído, la foto ya quedó publicada.
        $posUpdate = strpos($cuerpo, "update(\$productoId, ['imagen'");
        $posAviso  = strpos($cuerpo, 'MenuAppNotifier::catalogoCambio');
        $this->assertTrue($posUpdate !== false && $posUpdate < $posAviso,
            'Primero se publica la foto, después se avisa');
    }

    public function testPublicarNormalizaLaFotoAntesDeExponerla(): void
    {
        $pos    = strpos($this->controlador, 'private function publicar(');
        $cuerpo = substr($this->controlador, $pos, 2000);
        $this->assertContains('ImagenHelper::normalizar', $cuerpo,
            'La foto que ve el cliente pasa por el enderezado y el achicado');
        $this->assertContains(".jpg'", $cuerpo,
            'La extensión publicada tiene que coincidir con el JPEG que devuelve el helper');
    }

    public function testVolverALaAnteriorReusaElMismoCamino(): void
    {
        $pos    = strpos($this->controlador, 'public function volverAnterior(');
        $cuerpo = substr($this->controlador, $pos, 900);
        $this->assertContains('publicar(', $cuerpo,
            'Recuperar la anterior pasa por el mismo publicar(), con el mismo tratamiento');
        $this->assertNotContains('$_POST[', $cuerpo,
            'No debe reusar la otra acción retocando $_POST');
    }

    // ── 8.3 · La vista previa imita al menú ────────────────────────────────

    public function testLaVistaPreviaUsaLosDosRecortesDeMenuApp(): void
    {
        $this->assertContains('aspect-ratio: 4 / 3', $this->vistaPreview,
            'La tarjeta del listado va en 4:3');
        $this->assertContains('height: 240px', $this->vistaPreview,
            'El detalle va con un alto máximo de 240 px');
        $this->assertContains('object-fit: cover', $this->vistaPreview,
            'Los dos recortes usan object-fit: cover, igual que MenuApp');
        $this->assertContains('grayscale(1)', $this->vistaPreview,
            'Un producto agotado se ve en gris, como en MenuApp');
    }

    public function testLaVistaPreviaMuestraElPrecioQueVeElCliente(): void
    {
        $this->assertContains('PrecioHelper::precioFinalOnline', $this->controlador,
            'El precio de la vista previa sale del mismo helper que usa el menú (D9)');
    }

    public function testLaCamaraSeAbreDirectoYLaFotoSeAchicaAntesDeSubir(): void
    {
        $this->assertContains('capture="environment"', $this->vistaPreview,
            '"Sacar foto" abre directo la cámara trasera del celular');
        $this->assertContains('achicarImagen', $this->vistaPreview,
            'La foto se achica en el navegador antes de subir');
        $this->assertContains('canvas', $this->vistaPreview);
        $this->assertContains('LADO_MAXIMO = 1600', $this->vistaPreview,
            'El achicado del navegador usa el mismo lado máximo que el servidor');
        $this->assertContains('previoTarjeta', $this->vistaPreview,
            'Antes de subir se ve en los dos recortes, para encuadrar bien');
        $this->assertContains('previoDetalle', $this->vistaPreview);
    }

    // ── 8.10 · La ficha del producto avisa el motivo del rechazo ────────────

    public function testLaFichaDelProductoAvisaPorQueRechazoLaFoto(): void
    {
        // Antes subirImagen() devolvía null en los tres casos de rechazo y el producto se
        // guardaba SIN foto sin decir nada. Con fotos de celular de 3 a 12 MB, ese era el
        // caso normal, no el raro.
        $pos    = strpos($this->productos, 'private function subirImagen(');
        $this->assertTrue($pos !== false, 'Debe existir subirImagen()');
        if ($pos === false) return;

        $cuerpo = substr($this->productos, $pos, 2200);
        $this->assertContains('ImagenHelper::motivoRechazo', $cuerpo,
            'El rechazo tiene que traer un motivo para mostrárselo al usuario');
        $this->assertContains('ImagenHelper::normalizar', $cuerpo,
            'La foto de la ficha recibe el mismo achicado y enderezado que la del menú');
        $this->assertContains('$errorImagen', $this->productos,
            'crear() y actualizar() deben cortar con el motivo del rechazo');
    }

    // ── ImagenHelper: esto sí se ejecuta de verdad ──────────────────────────

    public function testAchicaUnaFotoGrandeYNoAgrandaUnaChica(): void
    {
        if (!extension_loaded('gd')) {
            $this->assertTrue(true, 'Sin GD en este entorno: prueba omitida');
            return;
        }

        $grande = $this->crearJpg(3000, 2000);
        $salida = tempnam(sys_get_temp_dir(), 'fm_') . '.jpg';
        $this->assertTrue(ImagenHelper::normalizar($grande, $salida), 'normalizar() debe funcionar');

        [$w, $h] = getimagesize($salida);
        $this->assertEquals(1600, $w, 'El lado mayor queda en 1600 px');
        $this->assertEquals(1067, $h, 'Se mantiene la proporción');

        $chica  = $this->crearJpg(400, 300);
        $salida2 = tempnam(sys_get_temp_dir(), 'fm_') . '.jpg';
        ImagenHelper::normalizar($chica, $salida2);
        [$w2, $h2] = getimagesize($salida2);
        $this->assertEquals(400, $w2, 'Una foto chica no se agranda');
        $this->assertEquals(300, $h2);

        foreach ([$grande, $salida, $chica, $salida2] as $f) @unlink($f);
    }

    public function testElAchicadoBajaElPesoPorDebajoDelLimite(): void
    {
        if (!extension_loaded('gd')) {
            $this->assertTrue(true, 'Sin GD en este entorno: prueba omitida');
            return;
        }

        // El caso real: una foto de celular que supera MAX_FILE_SIZE tiene que entrar
        // después de normalizar.
        $grande = $this->crearJpg(4000, 3000, true);
        $salida = tempnam(sys_get_temp_dir(), 'fm_') . '.jpg';
        ImagenHelper::normalizar($grande, $salida);

        $this->assertTrue(filesize($salida) < MAX_FILE_SIZE,
            'Después de achicar, la foto entra en el límite de subida');

        @unlink($grande); @unlink($salida);
    }

    public function testMotivoRechazoExplicaCadaCaso(): void
    {
        $grande = ['error' => UPLOAD_ERR_OK, 'size' => MAX_FILE_SIZE + 1, 'tmp_name' => '', 'type' => 'image/jpeg'];
        $motivo = ImagenHelper::motivoRechazo($grande);
        $this->assertTrue($motivo !== null && str_contains($motivo, 'MB'),
            'El motivo por peso tiene que decir cuánto pesa y cuál es el máximo');

        $sinArchivo = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'type' => ''];
        $this->assertTrue(ImagenHelper::motivoRechazo($sinArchivo) !== null,
            'Sin archivo también devuelve un motivo');

        if (extension_loaded('gd')) {
            $ok = $this->crearJpg(800, 600);
            $valido = ['error' => UPLOAD_ERR_OK, 'size' => filesize($ok), 'tmp_name' => $ok, 'type' => 'image/jpeg'];
            $this->assertEquals(null, ImagenHelper::motivoRechazo($valido),
                'Una foto válida no devuelve ningún motivo');
            @unlink($ok);
        }
    }

    /** JPEG de prueba del tamaño pedido. Con $ruido, con contenido difícil de comprimir. */
    private function crearJpg(int $w, int $h, bool $ruido = false): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 180, 120, 60));

        if ($ruido) {
            // Un relleno plano comprime a unos pocos KB y no serviría para probar el peso.
            for ($i = 0; $i < 4000; $i++) {
                imagefilledellipse(
                    $img, random_int(0, $w), random_int(0, $h), random_int(5, 60), random_int(5, 60),
                    imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255))
                );
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'fm_src_') . '.jpg';
        imagejpeg($img, $ruta, 95);
        imagedestroy($img);
        return $ruta;
    }
}
