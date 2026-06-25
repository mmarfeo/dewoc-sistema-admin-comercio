<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/core/AuditLog.php';

class AuditLogController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $desde  = $this->param('desde') ?: date('Y-m-01');
        $hasta  = $this->param('hasta') ?: date('Y-m-d');
        $modulo = $this->param('modulo') ?? '';
        $accion = $this->param('accion') ?? '';

        $where  = ["DATE(al.created_at) BETWEEN :desde AND :hasta"];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($modulo) {
            $where[]         = 'al.modulo = :mod';
            $params[':mod']  = $modulo;
        }
        if ($accion) {
            $where[]         = 'al.accion = :acc';
            $params[':acc']  = $accion;
        }

        $db     = Database::getInstance();
        $sql    = 'SELECT al.* FROM audit_log al WHERE ' . implode(' AND ', $where) . ' ORDER BY al.created_at DESC LIMIT 500';
        $logs   = $db->query($sql, $params)->fetchAll();

        $modulos = $db->query('SELECT DISTINCT modulo FROM audit_log ORDER BY modulo')->fetchAll(\PDO::FETCH_COLUMN);
        $acciones = $db->query('SELECT DISTINCT accion FROM audit_log ORDER BY accion')->fetchAll(\PDO::FETCH_COLUMN);

        $this->render('audit_log.index', [
            'pageTitle'  => 'Log de Auditoría',
            'breadcrumb' => ['Administración' => null, 'Auditoría' => null],
            'logs'       => $logs,
            'modulos'    => $modulos,
            'acciones'   => $acciones,
            'filtros'    => compact('desde', 'hasta', 'modulo', 'accion'),
        ]);
    }
}
