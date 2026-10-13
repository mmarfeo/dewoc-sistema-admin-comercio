<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';

class BackupController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');

        $this->render('backup.index', [
            'pageTitle'  => 'Backup de Base de Datos',
            'breadcrumb' => ['Administración' => null, 'Backup BD' => null],
        ]);
    }

    public function download(): void
    {
        $this->requireRole('admin');
        Csrf::check(false); // descarga de archivo directa, no AJAX

        while (ob_get_level()) ob_end_clean();

        $filename = 'dewoc_backup_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');

        $this->streamDump(Database::getInstance());
        exit;
    }

    private function streamDump(\PDO $conn): void
    {
        $this->line("-- ═══════════════════════════════════════════════════════");
        $this->line("-- Dewoc Admin Comercio · Backup SQL");
        $this->line("-- Generado : " . date('Y-m-d H:i:s'));
        $this->line("-- Base     : " . DB_NAME);
        $this->line("-- Usuario  : " . (Session::user()['nombre'] ?? 'sistema'));
        $this->line("-- ═══════════════════════════════════════════════════════\n");
        $this->line("SET NAMES utf8mb4;");
        $this->line("SET FOREIGN_KEY_CHECKS = 0;\n");

        $tables = $conn->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->line("-- ──────────────────────────────────────────────────");
            $this->line("-- Tabla: `{$table}`");
            $this->line("-- ──────────────────────────────────────────────────");

            $create = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
            $this->line("DROP TABLE IF EXISTS `{$table}`;");
            $this->line($create[1] . ";\n");

            $rows = $conn->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                foreach ($rows as $row) {
                    $values = array_map(static function ($v) use ($conn): string {
                        return $v === null ? 'NULL' : $conn->quote((string)$v);
                    }, $row);
                    $this->line("INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $values) . ");");
                }
                $this->line('');
            }
        }

        $this->line("SET FOREIGN_KEY_CHECKS = 1;");

        AuditLog::log('backup_download', 'sistema', 'Backup SQL descargado');
    }

    private function line(string $text): void
    {
        echo $text . "\n";
        flush();
    }
}
