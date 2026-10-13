<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/FotoMenuModel.php';
require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
require_once BASE_PATH . '/core/ImagenHelper.php';
require_once BASE_PATH . '/core/GeminiImagen.php';
require_once BASE_PATH . '/core/PrecioHelper.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';
require_once BASE_PATH . '/core/AuditLog.php';

/**
 * Fotos del menú online (ver docs/integracion-menuapp-checklist.md, etapa 8).
 *
 * Pensado para que un empleado, desde el celular, saque o elija hasta 3 fotos candidatas
 * por producto, las vea tal como van a quedar en el menú y, si tiene permiso, elija la
 * oficial. Sin tocar precios ni recetas: por eso tiene sus propios permisos y no depende
 * del de Productos, que además deja cambiar precios.
 *
 * Permisos, en dos niveles (tarea 8.8):
 *   fotos_menu           → ver, sacar y subir candidatas, mejorarlas con IA, descartarlas.
 *   fotos_menu_publicar  → además, elegir la oficial y volver a la anterior.
 *
 * MenuApp no necesita ningún cambio: al publicar una oficial se actualiza
 * productos.imagen y el aviso catalogo.cambio hace que MenuApp descargue la foto nueva.
 */
class FotosMenuController extends Controller
{
    /**
     * Opciones fijas de "Mejorar con IA" (tarea 8.6). A propósito NO hay texto libre: con
     * una instrucción abierta el modelo termina cambiando el producto (le agrega toppings,
     * le cambia la porción), y la foto deja de representar lo que el cliente va a recibir.
     */
    private const MEJORAS_IA = [
        'luz'      => ['label' => 'Mejorar luz y color',  'prompt' => 'Mejorá la iluminación y el balance de color de esta foto de producto. Hacela más nítida y apetitosa, con luz natural y parejas.'],
        'fondo'    => ['label' => 'Fondo limpio claro',   'prompt' => 'Reemplazá el fondo de esta foto por un fondo liso, claro y neutro, de estudio, sin objetos ni texto.'],
        'madera'   => ['label' => 'Fondo de madera',      'prompt' => 'Reemplazá el fondo de esta foto por una mesa de madera clara, prolija y sin objetos alrededor.'],
        'encuadre' => ['label' => 'Centrar y enderezar',  'prompt' => 'Enderezá y centrá el producto en el cuadro, corrigiendo la perspectiva y la inclinación, con un encuadre parejo.'],
    ];

    /**
     * Se agrega a TODAS las instrucciones. Es la única defensa real contra que la IA
     * "mejore" el producto inventándole cosas: la foto del menú es una promesa de lo que
     * el cliente va a recibir, no una ilustración.
     */
    private const GUARDA_IA = ' No agregues, quites ni cambies el producto: mantené su forma, tamaño, porción, toppings, colores y cantidad. Solo modificá luz, color, fondo o encuadre.';

    /** Tope diario de mejoras con IA por usuario si no hay nada configurado (tarea 8.9). */
    private const MEJORAS_IA_POR_DIA_DEFAULT = 20;

    private FotoMenuModel $model;

    public function __construct()
    {
        $this->model = new FotoMenuModel();
    }

    // ── 8.2 · Listado de productos del menú ─────────────────────────────────

    public function index(): void
    {
        $this->requireCanView('fotos_menu');

        $busqueda  = (string)$this->param('q', '');
        $categoria = (int)$this->param('categoria', 0);
        $revisar   = $this->param('revisar', '') === '1';

        $this->render('galeria.fotos-menu', [
            'pageTitle'   => 'Fotos del menú',
            'breadcrumb'  => ['Asistente IA' => null, 'Galería' => 'galeria', 'Fotos del menú' => null],
            'productos'   => $this->model->productosDelMenu($busqueda, $categoria, $revisar),
            'categorias'  => $this->model->categoriasDelMenu(),
            'busqueda'    => $busqueda,
            'categoriaId' => $categoria,
            'revisar'     => $revisar,
            'puedePublicar' => canEdit('fotos_menu_publicar'),
        ]);
    }

    // ── 8.3 · Vista previa del producto, igual que en el menú ───────────────

    public function producto(): void
    {
        $this->requireCanView('fotos_menu');

        $id       = (int)$this->param('id', 0);
        $producto = $id ? $this->model->producto($id) : null;
        if (!$producto) abort(404, 'Producto no encontrado.');

        $config = [];
        try {
            require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
            $config = (new IntegracionMenuAppModel())->obtener();
        } catch (\Throwable) { /* falta la migración de MenuApp — el enlace al menú no se muestra */ }

        $this->render('galeria.fotos-menu-producto', [
            'pageTitle'   => 'Fotos · ' . $producto['nombre'],
            'breadcrumb'  => ['Galería' => 'galeria', 'Fotos del menú' => 'galeria/fotos-menu', $producto['nombre'] => null],
            'producto'    => $producto,
            // El precio que se muestra en la vista previa es el MISMO que ve el cliente en
            // MenuApp: sale de PrecioHelper, no de precio_venta a secas (ver D9).
            'precioFinal' => PrecioHelper::precioFinalOnline($producto),
            'oficial'     => $this->model->oficial($id),
            'anterior'    => $this->model->anterior($id),
            'candidatas'  => $this->model->candidatas($id),
            'maxCandidatas' => FotoMenuModel::MAX_CANDIDATAS,
            'mejorasIa'   => array_map(fn($m) => $m['label'], self::MEJORAS_IA),
            'menuUrl'     => $config['menu_url'] ?? null,
            'puedePublicar' => canEdit('fotos_menu_publicar'),
            'mejorasRestantes' => $this->mejorasIaRestantes(),
        ]);
    }

    // ── 8.4 y 8.5 · Subir una candidata ─────────────────────────────────────

    /**
     * Guarda una foto como candidata. La foto ya viene achicada por el navegador (ver la
     * vista), pero acá se vuelve a normalizar: el achicado del cliente es para que la
     * subida no falle con mala señal, no una validación — se puede saltear.
     */
    public function subirCandidata(): void
    {
        $this->requireCanEdit('fotos_menu');
        Csrf::check();

        $productoId = (int)$this->input('producto_id', 0);
        $producto   = $productoId ? $this->model->producto($productoId) : null;
        if (!$producto) {
            $this->json(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
        }

        // El tope se controla en el servidor, no solo deshabilitando los botones (8.5).
        if (!$this->model->hayLugarParaCandidata($productoId)) {
            $this->json([
                'ok'    => false,
                'error' => 'Este producto ya tiene ' . FotoMenuModel::MAX_CANDIDATAS . ' candidatas. Descartá una para sumar otra.',
            ], 409);
        }

        if (empty($_FILES['archivo'])) {
            $this->json(['ok' => false, 'error' => 'No se recibió ninguna foto.'], 422);
        }

        // A diferencia de lo que hacía ProductosController, un rechazo se AVISA en vez de
        // guardar sin foto (ver el problema que resuelve la tarea 8.10).
        $motivo = ImagenHelper::motivoRechazo($_FILES['archivo']);
        if ($motivo !== null) {
            $this->json(['ok' => false, 'error' => $motivo], 422);
        }

        $archivo = $this->guardarEnGaleria($_FILES['archivo']['tmp_name'], $_FILES['archivo']['name'] ?? null);
        if (!$archivo) {
            $this->json(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor.'], 500);
        }

        $id = $this->model->registrarCandidata($archivo, $productoId, (int)Session::user()['id']);

        $this->json([
            'ok'         => true,
            'id'         => $id,
            'mensaje'    => 'Foto agregada como candidata.',
            'candidatas' => $this->model->contarCandidatas($productoId),
        ]);
    }

    // ── 8.6 · Mejorar una candidata con IA ──────────────────────────────────

    public function mejorar(): void
    {
        set_time_limit(120);
        $this->requireCanEdit('fotos_menu');
        Csrf::check();

        $productoId = (int)$this->input('producto_id', 0);
        $fotoId     = (int)$this->input('foto_id', 0);
        $opcion     = (string)$this->input('opcion', '');

        if (!isset(self::MEJORAS_IA[$opcion])) {
            $this->json(['ok' => false, 'error' => 'Opción de mejora no válida.'], 422);
        }
        if (GEMINI_MEDIA_API_KEY === '') {
            $this->json(['ok' => false, 'error' => 'Falta configurar GEMINI_MEDIA_API_KEY en el archivo .env.'], 422);
        }

        $foto = $fotoId && $productoId ? $this->model->fotoDeProducto($fotoId, $productoId) : null;
        if (!$foto) {
            $this->json(['ok' => false, 'error' => 'La foto no existe o no pertenece a este producto.'], 404);
        }

        // El resultado se suma como OTRA candidata, así que compite por el mismo tope.
        if (!$this->model->hayLugarParaCandidata($productoId)) {
            $this->json([
                'ok'    => false,
                'error' => 'Este producto ya tiene ' . FotoMenuModel::MAX_CANDIDATAS . ' candidatas. Descartá una para sumar otra.',
            ], 409);
        }

        // Tope diario de IA por usuario (8.9): la IA de imágenes se paga, y sin esto un
        // usuario puede gastar sin techo apretando un botón.
        if ($this->mejorasIaRestantes() <= 0) {
            $this->json([
                'ok'    => false,
                'error' => 'Llegaste al límite de ' . $this->mejorasIaPorDia() . ' mejoras con IA por día. Probá de nuevo mañana.',
            ], 429);
        }

        $path = BASE_PATH . '/storage/galeria/' . basename((string)$foto['nombre_archivo']);
        if (!is_file($path)) {
            $this->json(['ok' => false, 'error' => 'No se encontró el archivo original en el servidor.'], 404);
        }

        $instruccion = self::MEJORAS_IA[$opcion]['prompt'] . self::GUARDA_IA;
        $resultado   = GeminiImagen::editar((string)$foto['mime'], base64_encode(file_get_contents($path)), $instruccion);

        if ($resultado === null) {
            $this->json(['ok' => false, 'error' => 'No se pudo conectar con Gemini. Probá de nuevo en un momento.'], 502);
        }
        if (empty($resultado['imagen'])) {
            // El modelo contestó solo con texto (ej. rechazó el pedido): no se crea nada.
            $this->json([
                'ok'        => false,
                'error'     => $resultado['texto'] !== ''
                    ? 'La IA no generó una foto: ' . $resultado['texto']
                    : 'La IA no generó ninguna foto. Probá con otra opción.',
            ], 422);
        }

        $bytes  = base64_decode($resultado['imagen']);
        $nombre = 'menu_ia_' . $productoId . '_' . uniqid('', true) . '.' . GeminiImagen::extensionDe($resultado['mime']);
        $dir    = BASE_PATH . '/storage/galeria/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . $nombre, $bytes);

        $id = $this->model->registrarCandidata(
            [
                'nombre'          => $nombre,
                'nombre_original' => $foto['nombre_original'],
                'mime'            => $resultado['mime'] ?: 'image/png',
                'tamano'          => strlen($bytes),
            ],
            $productoId,
            (int)Session::user()['id'],
            true,
            $fotoId
        );

        $this->json([
            'ok'         => true,
            'id'         => $id,
            'mensaje'    => 'Mejora lista: quedó como otra candidata, la original sigue disponible.',
            'candidatas' => $this->model->contarCandidatas($productoId),
        ]);
    }

    // ── 8.7 · Elegir como oficial ───────────────────────────────────────────

    /**
     * Publica una candidata: la copia de storage/galeria/ (privado) a uploads/productos/
     * (público) ya enderezada y achicada, actualiza productos.imagen y le avisa a MenuApp.
     *
     * Exige fotos_menu_publicar, no fotos_menu: la idea de los dos permisos es que un
     * empleado proponga y alguien con criterio decida qué ve el cliente.
     */
    public function elegirOficial(): void
    {
        $this->requireCanEdit('fotos_menu_publicar');
        Csrf::check();

        $this->responder($this->publicar(
            (int)$this->input('producto_id', 0),
            (int)$this->input('foto_id', 0)
        ));
    }

    /**
     * Vuelve a publicar la foto que era oficial antes (tarea 8.7). Pasa por el mismo
     * publicar() que elegirOficial(), así la foto recuperada recibe el mismo enderezado y
     * achicado, y la oficial de ahora queda como `anterior`.
     */
    public function volverAnterior(): void
    {
        $this->requireCanEdit('fotos_menu_publicar');
        Csrf::check();

        $productoId = (int)$this->input('producto_id', 0);
        if (!$productoId || !$this->model->producto($productoId)) {
            $this->json(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
        }

        $anterior = $this->model->recuperarAnteriorComoCandidata($productoId);
        if (!$anterior) {
            $this->json(['ok' => false, 'error' => 'Este producto no tiene una foto anterior para recuperar.'], 409);
        }

        $this->responder($this->publicar($productoId, (int)$anterior['id'], true));
    }

    /**
     * Emite el resultado de publicar(). El código HTTP viaja en la clave `http` y se saca
     * antes de serializar, para que no aparezca en el cuerpo de la respuesta: sin esto, un
     * fallo de publicación devolvía 200 y el frontend lo trataba como éxito.
     */
    private function responder(array $resultado): never
    {
        $http = (int)($resultado['http'] ?? (empty($resultado['ok']) ? 422 : 200));
        unset($resultado['http']);
        $this->json($resultado, $http);
    }

    /**
     * Publica una foto como oficial: la copia de storage/galeria/ (privado) a
     * uploads/productos/ (público) ya enderezada y achicada, actualiza productos.imagen,
     * mueve los estados y le avisa a MenuApp.
     *
     * Devuelve el cuerpo de la respuesta JSON en vez de emitirlo, para que lo usen las dos
     * acciones sin que una tenga que llamar a la otra (ni retocar $_POST para lograrlo).
     *
     * @return array{ok: bool, error?: string, mensaje?: string, imagen?: string, http?: int}
     *         `http` es el código con el que responder; lo consume responder().
     */
    private function publicar(int $productoId, int $fotoId, bool $esRecuperacion = false): array
    {
        $producto = $productoId ? $this->model->producto($productoId) : null;
        $foto     = $producto && $fotoId ? $this->model->fotoDeProducto($fotoId, $productoId) : null;
        if (!$producto || !$foto) {
            return ['ok' => false, 'error' => 'La foto no existe o no pertenece a este producto.', 'http' => 404];
        }

        $origen = BASE_PATH . '/storage/galeria/' . basename((string)$foto['nombre_archivo']);
        if (!is_file($origen)) {
            return ['ok' => false, 'error' => 'No se encontró el archivo de la foto en el servidor.', 'http' => 404];
        }

        $dir = UPLOAD_PATH . '/productos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Siempre .jpg: ImagenHelper::normalizar() devuelve JPEG, así que la extensión
        // tiene que coincidir con el contenido real o el navegador se confunde.
        $nombrePublico = 'prod_' . $productoId . '_' . uniqid('', true) . '.jpg';
        if (!ImagenHelper::normalizar($origen, $dir . $nombrePublico)) {
            return ['ok' => false, 'error' => 'No se pudo preparar la foto para publicarla.', 'http' => 500];
        }

        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        (new ProductoModel())->update($productoId, ['imagen' => $nombrePublico]);

        $this->model->marcarOficial($fotoId, $productoId);

        // Recién ahora se avisa: si MenuApp está caído, la foto ya quedó publicada en Dewoc
        // y MenuApp la toma en su próxima consulta del catálogo.
        MenuAppNotifier::catalogoCambio('producto', $productoId);

        AuditLog::log($esRecuperacion ? 'restaurar_foto' : 'publicar_foto', 'fotos_menu',
            "Producto #{$productoId} ({$producto['nombre']}): se publicó la foto #{$fotoId}"
            . ((int)$foto['mejorada_ia'] === 1 ? ' (mejorada con IA)' : '')
            . ($esRecuperacion ? ' [volver a la foto anterior]' : ''));

        return [
            'ok'      => true,
            'mensaje' => $esRecuperacion
                ? 'Se volvió a la foto anterior. MenuApp la va a mostrar en unos segundos.'
                : 'Foto publicada. MenuApp la va a mostrar en unos segundos.',
            'imagen'  => $nombrePublico,
        ];
    }

    // ── 8.5 · Descartar una candidata ───────────────────────────────────────

    public function descartar(): void
    {
        $this->requireCanEdit('fotos_menu');
        Csrf::check();

        $productoId = (int)$this->input('producto_id', 0);
        $fotoId     = (int)$this->input('foto_id', 0);

        $foto = $productoId && $fotoId ? $this->model->fotoDeProducto($fotoId, $productoId) : null;
        if (!$foto) {
            $this->json(['ok' => false, 'error' => 'La foto no existe o no pertenece a este producto.'], 404);
        }
        if ($foto['estado_menu'] !== 'candidata') {
            $this->json(['ok' => false, 'error' => 'Solo se pueden descartar candidatas.'], 409);
        }

        $this->model->descartar($fotoId, $productoId);

        $this->json([
            'ok'         => true,
            'mensaje'    => 'Candidata descartada.',
            'candidatas' => $this->model->contarCandidatas($productoId),
        ]);
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /**
     * Normaliza la foto y la deja en storage/galeria/ (privada: se sirve con sesión por
     * galeria/ver). Solo la oficial llega a uploads/, que es público.
     */
    private function guardarEnGaleria(string $tmpPath, ?string $nombreOriginal): ?array
    {
        $dir = BASE_PATH . '/storage/galeria/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = 'menu_' . uniqid('', true) . '.jpg';
        if (!ImagenHelper::normalizar($tmpPath, $dir . $nombre)) return null;

        return [
            'nombre'          => $nombre,
            'nombre_original' => $nombreOriginal,
            'mime'            => 'image/jpeg',
            'tamano'          => (int)@filesize($dir . $nombre),
        ];
    }

    /** Tope diario configurable de mejoras con IA (gemini_config), con default propio. */
    private function mejorasIaPorDia(): int
    {
        try {
            $config = (new GeminiUsageModel())->getConfig();
            $valor  = (int)($config['fotos_menu_mejoras_dia'] ?? 0);
            return $valor > 0 ? $valor : self::MEJORAS_IA_POR_DIA_DEFAULT;
        } catch (\Throwable) {
            return self::MEJORAS_IA_POR_DIA_DEFAULT;
        }
    }

    private function mejorasIaRestantes(): int
    {
        $usuarioId = (int)(Session::user()['id'] ?? 0);
        if (!$usuarioId) return 0;
        return max(0, $this->mejorasIaPorDia() - $this->model->mejorasIaDeHoy($usuarioId));
    }
}
