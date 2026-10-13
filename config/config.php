<?php
// ── Carga .env si existe (formato KEY=VALUE, ignora comentarios #) ─────────
define('BASE_PATH', dirname(__DIR__));

(static function () {
    $envFile = BASE_PATH . '/.env';
    if (!is_file($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Quitar comillas envolventes (simples o dobles)
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
})();

// ─── Entorno ────────────────────────────────────────────────────────────────
define('ENV',   getenv('APP_ENV') ?: 'development');  // 'development' | 'production'
define('DEBUG', ENV === 'development');

// ─── Rutas ──────────────────────────────────────────────────────────────────
define('BASE_URL',   getenv('APP_URL') ?: 'http://localhost/01-Dewoc-Sist-Admin-Comercio');
define('ASSETS_URL', BASE_URL . '/assets');

// ─── Base de datos ───────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'u374453216_erp_comercio');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ─── Aplicación ─────────────────────────────────────────────────────────────
define('APP_NAME',    'Dewoc — Admin Comercio');
define('APP_VERSION', '1.0.0');
define('APP_LOCALE',  'es_AR');
define('TIMEZONE',    'America/Argentina/Buenos_Aires');

// ─── Datos del comercio (encabezado de tickets y comandas) ──────────────────
define('NEGOCIO_NOMBRE',     getenv('NEGOCIO_NOMBRE')     ?: 'Serendipia');
define('NEGOCIO_DIRECCION',  getenv('NEGOCIO_DIRECCION')  ?: 'Lapida 1026, Ezeiza, Bs. As.');

// Impresora térmica del ticket/comanda: ancho real del rollo de papel (50, 58, 80,
// 100mm...) y un zoom general para agrandar todo el texto si no se lee bien impreso.
// Cambiar acá si se cambia de impresora, sin tocar las vistas.
define('TICKET_ANCHO_MM', (int)(getenv('TICKET_ANCHO_MM') ?: 80));
define('TICKET_ZOOM_PCT', (int)(getenv('TICKET_ZOOM_PCT') ?: 130));

// ─── Datos fiscales del comercio (encabezado del comprobante ARCA/AFIP) ─────
define('NEGOCIO_IIBB',                  getenv('NEGOCIO_IIBB')                  ?: '');            // Ingresos Brutos (vacío = no se imprime la línea)
define('NEGOCIO_INICIO_ACTIVIDADES',    getenv('NEGOCIO_INICIO_ACTIVIDADES')    ?: '');             // formato dd/mm/YYYY

// Condición del NEGOCIO (emisor) frente al IVA. De acá se derivan automáticamente:
// - si se discrimina IVA en el POS y en la venta (solo responsable_inscripto),
// - qué tipo de comprobante se solicita a AFIP (Factura A/B/C) según también la
//   condición IVA del cliente receptor (ver AfipWsfe::tipoComprobante()).
// Valores válidos: monotributista | responsable_inscripto | exento
define('NEGOCIO_CONDICION_IVA', getenv('NEGOCIO_CONDICION_IVA') ?: 'monotributista');

$_NEGOCIO_CONDICION_IVA_LABELS = [
    'monotributista'        => 'Monotributista',
    'responsable_inscripto' => 'IVA Responsable Inscripto',
    'exento'                 => 'IVA Exento',
];
// Texto libre que se imprime en el encabezado del ticket; por defecto se arma
// a partir de NEGOCIO_CONDICION_IVA, pero puede sobreescribirse en .env.
define('NEGOCIO_CONDICION_FISCAL', getenv('NEGOCIO_CONDICION_FISCAL')
    ?: ($_NEGOCIO_CONDICION_IVA_LABELS[NEGOCIO_CONDICION_IVA] ?? 'Monotributista'));

define('NEGOCIO_ORIENTACION_CONSUMIDOR', getenv('NEGOCIO_ORIENTACION_CONSUMIDOR') ?: 'Orientación al Consumidor Provincia de Buenos Aires 0800-222-9042');

// ─── Seguridad ───────────────────────────────────────────────────────────────
define('SESSION_NAME',     'dewoc_sess');
define('SESSION_LIFETIME', 36000);      // 10 horas — cubre un turno completo sin cortar una venta a mitad
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ─── Tienda / Ecommerce ──────────────────────────────────────────────────────
define('TIENDA_NOMBRE',   'Mi Tienda Online');
define('TIENDA_ACTIVA',   true);
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: '');
define('MP_PUBLIC_KEY',   getenv('MP_PUBLIC_KEY')   ?: '');

// ─── Chat IA (Gemini) ────────────────────────────────────────────────────────
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'gemini-2.0-flash-lite');
// Cadena de modelos de respaldo, separados por coma, en el orden en que se prueban
// si GEMINI_MODEL (o el respaldo anterior) agota su límite de solicitudes — RPM
// (por minuto) o RPD (por día), son cuotas independientes por modelo del lado de
// Google. Dejar vacío desactiva el respaldo (se comporta como antes, un solo
// modelo). Usar el ID exacto de cada modelo tal como figura en la sección "Límite
// de frecuencia" de Google AI Studio — un ID incorrecto no rompe nada, ese paso
// de la cadena simplemente falla y sigue al siguiente (se ve en /gemini-log).
define('GEMINI_MODEL_FALLBACK', getenv('GEMINI_MODEL_FALLBACK') ?: '');

// API key de una cuenta CON facturación habilitada (tokens de pago), usada como último
// recurso solo cuando GEMINI_API_KEY (gratis) devuelve específicamente 429 "cupo agotado"
// en TODOS los modelos de la cadena de arriba — nunca para otro tipo de error (network,
// 400, etc.). Dejar vacío desactiva este respaldo (se comporta como antes: si se agota el
// free, corta con el mensaje de error de siempre). Ver ChatController::llamarGemini().
define('GEMINI_API_KEY_PAGO', getenv('GEMINI_API_KEY_PAGO') ?: '');

// Modelo con salida de imagen (usado solo por la Galería para generar/mejorar
// fotos), separado de GEMINI_MODEL porque el modelo de texto del chat general
// no genera imágenes. Ajustable por .env si Google cambia el nombre del modelo.
define('GEMINI_IMAGE_MODEL', getenv('GEMINI_IMAGE_MODEL') ?: 'gemini-2.5-flash-image');
// Modelo de video (Veo, expuesto vía la misma API de Gemini/AI Studio, no Vertex AI).
// La generación es asíncrona: se inicia con predictLongRunning y se consulta con
// operations.get hasta que termine (ver GaleriaController::generarVideo()/estado()).
define('GEMINI_VIDEO_MODEL', getenv('GEMINI_VIDEO_MODEL') ?: 'veo-3.1-fast-generate-preview');
// API key separada de GEMINI_API_KEY a propósito: el chat de texto usa el tier
// gratuito y con eso alcanza, mientras que imagen/video necesitan un proyecto
// con facturación habilitada — se usa esta key aparte para que el consumo
// pago de la Galería no se mezcle con el uso gratuito del chat general.
define('GEMINI_MEDIA_API_KEY', getenv('GEMINI_MEDIA_API_KEY') ?: '');

// ─── Galería · proveedor alternativo gratis (NVIDIA NIM, build.nvidia.com) ──
// Modelos open source (Qwen-Image, Wan 2.1) alojados por NVIDIA como alternativa
// gratuita/más barata a Gemini para generar imagen y video en la Galería. El
// slug exacto del modelo hay que confirmarlo en la pestaña "Deploy"/API de cada
// modelo en build.nvidia.com — estos son los valores por defecto, ajustables
// por .env sin tocar código si NVIDIA cambia el path.
define('NVIDIA_API_KEY',     getenv('NVIDIA_API_KEY')     ?: '');
define('NVIDIA_IMAGE_MODEL', getenv('NVIDIA_IMAGE_MODEL') ?: 'qwen/qwen-image');
define('NVIDIA_VIDEO_MODEL', getenv('NVIDIA_VIDEO_MODEL') ?: 'wan/wan2.1-i2v-14b');

// Mantener alias por compatibilidad con código legado
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL',   'claude-haiku-4-5-20251001');
define('MCP_TABLAS_OK', [
    'productos', 'categorias', 'ventas', 'ventas_detalle',
    'clientes', 'proveedores',
    'facturas_compra', 'facturas_compra_detalle',
    'materias_primas', 'insumos',
    'pagos_proveedores', 'pagos_facturas',
    'stock_movimientos', 'pedidos_ecommerce', 'pedidos_ecommerce_detalle',
    'turnos', 'caja_movimientos', 'usuarios',
    'empleados', 'empleados_jornadas', 'empleados_ausencias', 'empleados_conceptos', 'feriados',
    'recetas', 'receta_detalle', 'ingredientes_genericos', 'unidades_medida',
]);

// ─── Secretos ───────────────────────────────────────────────────────────────
// Los tres valores de abajo son SECRETOS y por eso no tienen valor por defecto: salen
// únicamente del .env, que no se versiona. Antes estaban hardcodeados como fallback, lo
// que los habría publicado en el repositorio (que es público) y los dejaría en el
// historial de git para siempre. Mismo criterio que GEMINI_API_KEY y MP_ACCESS_TOKEN.
//
// Si alguno queda vacío, la funcionalidad que depende de él se apaga sola — nunca se cae
// a un valor conocido, que es justamente lo que lo haría adivinable:
//   VAPID_PRIVATE_KEY vacío  → no se envían notificaciones push.
//   CRON_SECRET vacío        → los scripts de /cron/ rechazan las llamadas por HTTP
//                              (403); por CLI siguen funcionando igual.
//   TICKET_SHARE_SECRET vacío → los links de ticket compartidos no validan.
//
// Para generar valores nuevos: las VAPID con WebPush::generarClavesVapid(), y las otras
// dos con `php -r "echo bin2hex(random_bytes(32));"`.

// Web Push (notificaciones a administradores). La pública se expone al frontend a
// propósito —es parte del protocolo—; la privada nunca.
define('VAPID_PUBLIC_KEY',  getenv('VAPID_PUBLIC_KEY')  ?: '');
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');
define('VAPID_SUBJECT',     getenv('VAPID_SUBJECT')     ?: 'mailto:contacto@dewoc.com');

// Autoriza los scripts de /cron/ cuando el hosting solo permite dispararlos por URL
// (en vez de ejecutar el PHP directo por CLI).
define('CRON_SECRET', getenv('CRON_SECRET') ?: '');

// Firma los links de ticket que se comparten por WhatsApp/email (fuera de sesión,
// el cliente no está logueado): sin esto, ventas/ticket exige login y el cliente
// se encontraría con la pantalla de acceso en vez del comprobante. Ver helper
// ticketToken() en core/helpers.php.
define('TICKET_SHARE_SECRET', getenv('TICKET_SHARE_SECRET') ?: '');

// ─── AFIP ────────────────────────────────────────────────────────────────────
define('AFIP_MODO',      getenv('AFIP_MODO')      ?: 'homo');   // 'homo' | 'prod'
define('AFIP_CUIT',      getenv('AFIP_CUIT')      ?: '');       // CUIT emisor (sin guiones)
define('AFIP_CERT',      getenv('AFIP_CERT')      ?: BASE_PATH . '/storage/afip/cert.pem');
define('AFIP_KEY',       getenv('AFIP_KEY')        ?: BASE_PATH . '/storage/afip/key.pem');
define('AFIP_KEY_PASS',  getenv('AFIP_KEY_PASS')  ?: '');       // contraseña clave privada
define('AFIP_PTO_VENTA', (int)(getenv('AFIP_PTO_VENTA') ?: 1));
// El tipo de comprobante (A/B/C) ya no es fijo: se calcula por venta según
// NEGOCIO_CONDICION_IVA + la condición IVA del cliente (AfipWsfe::tipoComprobante()).

// ─── Email ───────────────────────────────────────────────────────────────────
define('MAIL_ENABLED',   filter_var(getenv('MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'noreply@dewoc.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// ─── Uploads ─────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',    BASE_PATH . '/uploads');
define('MAX_FILE_SIZE',  5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGES', ['image/jpeg', 'image/png', 'image/webp']);

// ─── Galería (fotos y videos propios del negocio) ───────────────────────────
define('MAX_VIDEO_SIZE',  50 * 1024 * 1024); // 50 MB
define('ALLOWED_VIDEOS',  ['video/mp4', 'video/quicktime']);

// ─── Encoding ────────────────────────────────────────────────────────────────
mb_internal_encoding('UTF-8');
mb_language('Neutral');

// ─── Zona horaria ────────────────────────────────────────────────────────────
date_default_timezone_set(TIMEZONE);

// ─── Errores ─────────────────────────────────────────────────────────────────
if (DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/storage/logs/php_errors.log');
}
