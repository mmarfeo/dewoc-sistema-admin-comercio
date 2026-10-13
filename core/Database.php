<?php
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::conectar();
            return self::$instance;
        }

        // Requests largas (ej. Chat IA encadenando varios minutos de llamadas a Gemini para
        // analizar una factura) pueden volver con la conexión ya cerrada del lado de MySQL si
        // el wait_timeout del hosting es más corto que eso ("MySQL server has gone away").
        // Un SELECT 1 es prácticamente gratis en una conexión viva; si falla, reconectamos.
        try {
            self::$instance->query('SELECT 1');
        } catch (\Throwable) {
            self::$instance = self::conectar();
        }

        return self::$instance;
    }

    private static function conectar(): PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Refuerzo explícito: el charset del DSN no siempre es suficiente
            // en algunas versiones de MySQL/MariaDB en hosting compartido.
            // time_zone en offset fijo (no nombre de zona IANA): Argentina no tiene horario de
            // verano, y muchos hosts administrados no tienen cargadas las tablas de zonas
            // horarias de MySQL (mysql.time_zone_name), donde 'America/Argentina/Buenos_Aires'
            // fallaría en silencio. Sin esto, CURDATE()/NOW() de MySQL corrían con el default
            // del servidor (típicamente UTC) en vez de con la hora real del negocio — cualquier
            // consulta con CURDATE()/NOW() (ej. las que arma el Chat IA, o los widgets "hoy"/
            // "últimos 7 días" del dashboard) daba mal entre las 21:00 y las 00:00 hora
            // Argentina, la ventana en la que UTC ya cruzó a "mañana".
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'",
        ];
        try {
            return new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            logApp('Error de conexión BD: ' . $e->getMessage(), 'ERROR');
            if (PHP_SAPI === 'cli') {
                throw $e;
            }
            if (DEBUG) {
                die('<pre>Error de conexión a la base de datos: ' . e($e->getMessage()) . '</pre>');
            }
            die('Error interno del servidor. Contacte al administrador.');
        }
    }

    private function __construct() {}
    private function __clone() {}
}
