<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionSeguridadModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

class PermisosController extends Controller
{
    private PermisosModel $model;

    public function __construct()
    {
        $this->model = new PermisosModel();
    }

    public function index(): void
    {
        $this->requireRole('admin');
        $this->render('permisos.index', [
            'pageTitle'          => 'Permisos por módulo',
            'breadcrumb'         => ['Administración' => null, 'Permisos por módulo' => null],
            'roles'              => $this->model->getRoles(),
            'matrix'             => $this->model->getMatrix(),
            'modulos'            => PermisosModel::modulosOrdenados(),
            'webauthnHabilitado' => (bool)(new ConfiguracionSeguridadModel())->obtener()['webauthn_habilitado'],
        ]);
    }

    /** Toggle global: habilita/deshabilita el login con huella/PIN (WebAuthn) para todo el sistema */
    public function guardarConfigSeguridad(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $habilitado = !empty($_POST['webauthn_habilitado']);
        (new ConfiguracionSeguridadModel())->guardarWebauthnHabilitado($habilitado);
        AuditLog::log('config_seguridad_update', 'permisos', 'Login WebAuthn ' . ($habilitado ? 'habilitado' : 'deshabilitado'));
        $this->json(['ok' => true, 'mensaje' => 'Configuración guardada correctamente.']);
    }

    public function guardar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $raw   = $_POST['p'] ?? [];
        $roles = $this->model->getRoles();

        // Build full matrix: missing checkboxes default to 0
        $sanitized = [];
        foreach ($roles as $rol) {
            $rid = (int)$rol['id'];
            $sanitized[$rid] = [];
            foreach (array_keys(PermisosModel::MODULOS) as $mod) {
                $sanitized[$rid][$mod] = [
                    'ver'    => isset($raw[$rid][$mod]['ver'])    ? 1 : 0,
                    'editar' => isset($raw[$rid][$mod]['editar']) ? 1 : 0,
                ];
            }
        }

        try {
            $this->model->saveAll($sanitized);
            AuditLog::log('permisos_update', 'permisos', 'Matriz de permisos actualizada');
            $this->json(['ok' => true, 'mensaje' => 'Permisos guardados correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
