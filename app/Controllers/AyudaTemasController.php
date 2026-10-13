<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/AyudaModel.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';

/**
 * ABM de los temas del "help desk" del Chat IA (ver docs/tareas-pendientes.md,
 * sección Help Desk) — contenido curado (manual paso a paso por tema) que Gemini
 * usa para responder "¿cómo hago X?" y con el que arma la tarjeta de ayuda.
 * Solo admin: es contenido que afecta lo que el asistente le explica a todos los
 * usuarios, igual criterio que Permisos o Usuarios.
 */
class AyudaTemasController extends Controller
{
    private AyudaModel $model;

    public function __construct()
    {
        $this->model = new AyudaModel();
    }

    public function index(): void
    {
        $this->requireRole('admin');
        $this->render('ayuda-temas.index', [
            'pageTitle'  => 'Guías del Help Desk',
            'breadcrumb' => ['Administración' => null, 'Guías del Help Desk' => null],
            'temas'      => $this->model->listar(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireRole('admin');
        $this->render('ayuda-temas.form', [
            'pageTitle'  => 'Nueva guía',
            'breadcrumb' => ['Administración' => null, 'Guías del Help Desk' => 'ayuda-temas', 'Nueva' => null],
            'tema'       => null,
            'modulos'    => PermisosModel::modulosOrdenados(),
            'tourIds'    => AyudaModel::TOUR_IDS,
        ]);
    }

    public function editar(): void
    {
        $this->requireRole('admin');
        $id   = (int)$this->param('id');
        $tema = $id ? $this->model->findById($id) : false;
        if (!$tema) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('ayuda-temas.form', [
            'pageTitle'  => 'Editar guía: ' . $tema['titulo'],
            'breadcrumb' => ['Administración' => null, 'Guías del Help Desk' => 'ayuda-temas', 'Editar' => null],
            'tema'       => $tema,
            'modulos'    => PermisosModel::modulosOrdenados(),
            'tourIds'    => AyudaModel::TOUR_IDS,
        ]);
    }

    public function guardar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        [$datos, $error] = $this->parsearForm();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        if ($this->model->findOneWhere(['tema' => $datos['tema']])) {
            $this->json(['ok' => false, 'error' => 'Ya existe una guía con esa clave "tema".'], 422);
        }

        $id = $this->model->insert($datos);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Guía creada.']);
    }

    public function actualizar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id || !$this->model->findById($id)) $this->json(['ok' => false, 'error' => 'Guía no encontrada.'], 422);

        [$datos, $error] = $this->parsearForm();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $existente = $this->model->findOneWhere(['tema' => $datos['tema']]);
        if ($existente && (int)$existente['id'] !== $id) {
            $this->json(['ok' => false, 'error' => 'Ya existe otra guía con esa clave "tema".'], 422);
        }

        $this->model->update($id, $datos);
        $this->json(['ok' => true, 'mensaje' => 'Guía actualizada.']);
    }

    public function desactivar(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        $this->model->desactivar((int)$this->input('id'));
        $this->json(['ok' => true, 'mensaje' => 'Guía desactivada — el Chat IA deja de ofrecerla.']);
    }

    public function activar(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        $this->model->activar((int)$this->input('id'));
        $this->json(['ok' => true, 'mensaje' => 'Guía reactivada.']);
    }

    /**
     * `modulo` y `tour_id` se validan contra listas cerradas (no son texto libre) porque
     * están atados a código: `modulo` tiene que existir en PermisosModel::MODULOS para que
     * canView() lo resuelva, y `tour_id` tiene que existir en AyudaModel::TOUR_IDS para que
     * el botón "Tour guiado" apunte a un tour real cargado en assets/js/tour.js.
     */
    private function parsearForm(): array
    {
        $tema    = strtolower(trim((string)$this->input('tema', '')));
        $modulo  = (string)$this->input('modulo', '');
        $titulo  = trim((string)$this->input('titulo', ''));
        $url     = ltrim(trim((string)$this->input('url', '')), '/');
        $tourId  = trim((string)$this->input('tour_id', ''));
        $manual  = trim((string)$this->input('manual', ''));
        $activo  = (int)$this->input('activo', 1);

        if (!preg_match('/^[a-z0-9_]+$/', $tema)) {
            return [[], 'La clave "tema" solo puede tener minúsculas, números y guión bajo (ej: pos_facturar).'];
        }
        if (!array_key_exists($modulo, PermisosModel::MODULOS)) {
            return [[], 'Elegí un módulo válido de la lista.'];
        }
        if ($titulo === '') return [[], 'El título es obligatorio.'];
        if ($url === '') return [[], 'La URL es obligatoria (ej: ventas/pos).'];
        if ($manual === '') return [[], 'El manual (pasos) es obligatorio.'];
        if ($tourId !== '' && !array_key_exists($tourId, AyudaModel::TOUR_IDS)) {
            return [[], 'Elegí un tour válido de la lista, o dejalo en "Sin tour".'];
        }

        return [[
            'tema'    => $tema,
            'modulo'  => $modulo,
            'titulo'  => $titulo,
            'url'     => $url,
            'tour_id' => $tourId !== '' ? $tourId : null,
            'manual'  => $manual,
            'activo'  => $activo ? 1 : 0,
        ], null];
    }
}
