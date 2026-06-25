<?php
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
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
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
