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
define('DB_NAME',    getenv('DB_NAME')    ?: 'dewoc_comercio');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ─── Aplicación ─────────────────────────────────────────────────────────────
define('APP_NAME',    'Dewoc — Admin Comercio');
define('APP_VERSION', '1.0.0');
define('APP_LOCALE',  'es_AR');
define('TIMEZONE',    'America/Argentina/Buenos_Aires');

// ─── Seguridad ───────────────────────────────────────────────────────────────
define('SESSION_NAME',     'dewoc_sess');
define('SESSION_LIFETIME', 7200);       // 2 horas
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ─── Tienda / Ecommerce ──────────────────────────────────────────────────────
define('TIENDA_NOMBRE',   'Mi Tienda Online');
define('TIENDA_ACTIVA',   true);
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: '');
define('MP_PUBLIC_KEY',   getenv('MP_PUBLIC_KEY')   ?: '');

// ─── Chat IA + MCP MySQL ─────────────────────────────────────────────────────
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL',   'claude-haiku-4-5-20251001');
define('MCP_TABLAS_OK', [
    'productos', 'categorias', 'ventas', 'ventas_detalle',
    'clientes', 'proveedores', 'compras', 'compras_detalle',
    'stock_movimientos', 'pedidos_ecommerce', 'pedidos_ecommerce_detalle',
    'caja_turnos', 'caja_movimientos', 'usuarios',
]);

// ─── AFIP ────────────────────────────────────────────────────────────────────
define('AFIP_MODO',      getenv('AFIP_MODO')      ?: 'homo');   // 'homo' | 'prod'
define('AFIP_CUIT',      getenv('AFIP_CUIT')      ?: '');       // CUIT emisor (sin guiones)
define('AFIP_CERT',      getenv('AFIP_CERT')      ?: BASE_PATH . '/storage/afip/cert.pem');
define('AFIP_KEY',       getenv('AFIP_KEY')        ?: BASE_PATH . '/storage/afip/key.pem');
define('AFIP_KEY_PASS',  getenv('AFIP_KEY_PASS')  ?: '');       // contraseña clave privada
define('AFIP_PTO_VENTA', (int)(getenv('AFIP_PTO_VENTA') ?: 1));
define('AFIP_TIPO_CBTE', (int)(getenv('AFIP_TIPO_CBTE') ?: 6)); // 1=A, 6=B, 11=C

// ─── Email ───────────────────────────────────────────────────────────────────
define('MAIL_ENABLED',   filter_var(getenv('MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'noreply@dewoc.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// ─── Uploads ─────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',    BASE_PATH . '/uploads');
define('MAX_FILE_SIZE',  5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGES', ['image/jpeg', 'image/png', 'image/webp']);

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
