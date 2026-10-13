<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/PlantillaPermisosModel.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

/**
 * Plantillas de permisos ("Permission Sets" estilo Salesforce) — ver
 * database/plantillas_permisos_migration.sql y PlantillaPermisosModel para la jerarquía
 * completa (plantilla puntual de usuario > plantilla default del rol > matriz vieja por
 * rol_id > excepciones sueltas de permisos_usuario encima de todo).
 */
class PlantillaPermisosController extends Controller
{
    private PlantillaPermisosModel $model;

    public function __construct()
    {
        $this->model = new PlantillaPermisosModel();
    }

    public function index(): void
    {
        $this->requireRole('admin');
        $this->render('permisos.plantillas.index', [
            'pageTitle'  => 'Plantillas de permisos',
            'breadcrumb' => ['Administración' => null, 'Permisos por módulo' => 'permisos', 'Plantillas' => null],
            'plantillas' => $this->model->listar(),
            'roles'      => (new UsuarioModel())->getRoles(),
        ]);
    }

    public function crear(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $nombre = trim((string)$this->input('nombre', ''));
        if ($nombre === '') $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        $id = $this->model->crear($nombre, (string)$this->input('descripcion', ''));
        AuditLog::log('crear', 'plantillas_permisos', "Plantilla creada: {$nombre}");
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Plantilla creada. Ahora configurá su matriz de permisos.']);
    }

    public function editar(): void
    {
        $this->requireRole('admin');

        $id        = (int)$this->param('id', 0);
        $plantilla = $id ? $this->model->findByIdConMatriz($id) : null;
        if (!$plantilla) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('permisos.plantillas.editar', [
            'pageTitle'  => 'Editar plantilla: ' . $plantilla['nombre'],
            'breadcrumb' => ['Administración' => null, 'Permisos por módulo' => 'permisos', 'Plantillas' => 'permisos/plantillas', $plantilla['nombre'] => null],
            'plantilla'  => $plantilla,
            'modulos'    => PermisosModel::modulosOrdenados(),
        ]);
    }

    public function actualizarCabecera(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id     = (int)$this->input('id', 0);
        $nombre = trim((string)$this->input('nombre', ''));
        if (!$id || $nombre === '') $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        $this->model->actualizarCabecera($id, $nombre, (string)$this->input('descripcion', ''));
        AuditLog::log('editar', 'plantillas_permisos', "Cabecera actualizada: {$nombre}");
        $this->json(['ok' => true, 'mensaje' => 'Guardado.']);
    }

    public function guardarMatriz(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'Plantilla inválida.'], 422);

        $raw    = $_POST['p'] ?? [];
        $matriz = [];
        foreach (array_keys(PermisosModel::MODULOS) as $mod) {
            $matriz[$mod] = [
                'ver'    => isset($raw[$mod]['ver'])    ? 1 : 0,
                'editar' => isset($raw[$mod]['editar']) ? 1 : 0,
            ];
        }

        try {
            $this->model->guardarMatriz($id, $matriz);
            AuditLog::log('permisos_update', 'plantillas_permisos', "Matriz actualizada (plantilla id={$id})");
            $this->json(['ok' => true, 'mensaje' => 'Permisos guardados correctamente.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function desactivar(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'Plantilla inválida.'], 422);

        if ($this->model->estaEnUso($id)) {
            $this->json(['ok' => false, 'error' => 'Esta plantilla está asignada a un rol o a un usuario — reasignalos antes de desactivarla.'], 422);
        }
        $this->model->desactivar($id);
        AuditLog::log('editar', 'plantillas_permisos', "Plantilla desactivada (id={$id})");
        $this->json(['ok' => true, 'mensaje' => 'Plantilla desactivada.']);
    }

    public function activar(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'Plantilla inválida.'], 422);

        $this->model->activar($id);
        AuditLog::log('editar', 'plantillas_permisos', "Plantilla reactivada (id={$id})");
        $this->json(['ok' => true, 'mensaje' => 'Plantilla reactivada.']);
    }

    /** Asigna (o quita, con plantilla_id vacío) la plantilla POR DEFECTO de un Rol — la
     *  heredan automáticamente todos sus usuarios que no tengan una plantilla puntual propia. */
    public function asignarDefaultRol(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $rolId = (int)$this->input('rol_id', 0);
        if (!$rolId) $this->json(['ok' => false, 'error' => 'Rol inválido.'], 422);

        $plantillaIdRaw = trim((string)$this->input('plantilla_id', ''));
        $plantillaId    = $plantillaIdRaw !== '' ? (int)$plantillaIdRaw : null;

        $db = Database::getInstance();
        $db->prepare('UPDATE roles SET plantilla_permisos_id_default = ? WHERE id = ?')
            ->execute([$plantillaId, $rolId]);

        AuditLog::log('editar', 'roles', "Plantilla por defecto del rol id={$rolId}: " . ($plantillaId ? "id={$plantillaId}" : 'quitada'));
        $this->json(['ok' => true, 'mensaje' => 'Guardado.']);
    }
}
