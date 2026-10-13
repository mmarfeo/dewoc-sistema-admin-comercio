<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/NotificacionModel.php';

/** Bandeja de notificaciones genérica del header (ver docs/tareas-pendientes.md #3). */
class NotificacionesController extends Controller
{
    private NotificacionModel $model;

    public function __construct()
    {
        $this->model = new NotificacionModel();
    }

    /** AJAX: para la campanita del header — contador de no leídas + las últimas, con polling. */
    public function listar(): void
    {
        $this->requireAuth();
        $usuario = Session::user();
        $this->json([
            'ok'         => true,
            'no_leidas'  => $this->model->contarNoLeidas((int)$usuario['id']),
            'recientes'  => $this->model->recientes((int)$usuario['id'], 10),
        ]);
    }

    public function marcarLeida(): void
    {
        $this->requireAuth();
        Csrf::check();
        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $usuario = Session::user();
        $this->model->marcarLeida($id, (int)$usuario['id']);
        $this->json(['ok' => true]);
    }
}
