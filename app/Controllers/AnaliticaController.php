<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/AnaliticaUsoModel.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';

/**
 * Analítica de uso (ver docs/auditoria-analitica-uso.md) — de consulta ocasional, no
 * pensada para revisarse a diario. Admin-only, mismo criterio que /auditoria y /permisos:
 * no es una acción de negocio con permiso propio, es una herramienta de gestión del sistema.
 */
class AnaliticaController extends Controller
{
    private AnaliticaUsoModel $model;

    public function __construct()
    {
        $this->model = new AnaliticaUsoModel();
    }

    public function index(): void
    {
        $this->requireRole('admin');

        $desde     = $this->param('desde') ?: date('Y-m-d', strtotime('-30 days'));
        $hasta     = $this->param('hasta') ?: date('Y-m-d');
        $usuarioId = (int)$this->param('usuario_id', 0) ?: null;
        $rolId     = (int)$this->param('rol_id', 0) ?: null;

        $usuarioModel = new UsuarioModel();

        $this->render('analitica.index', [
            'pageTitle'       => 'Analítica de Uso',
            'breadcrumb'      => ['Administración' => null, 'Analítica de Uso' => null],
            'ranking'         => $this->model->rankingPorModulo($desde, $hasta, $usuarioId, $rolId),
            // Sin acotar por rango: para el aviso de "sin uso reciente" interesa el
            // último acceso histórico, no solo dentro del período que se esté mirando.
            'sinUsoReciente'  => $this->model->ultimoUsoPorModulo(),
            'filtros'         => compact('desde', 'hasta', 'usuarioId', 'rolId'),
            'usuariosActivos' => $usuarioModel->findActivos(),
            'roles'           => $usuarioModel->getRoles(),
        ]);
    }

    /** AJAX: desglose por usuario de un módulo puntual, para expandir una fila del ranking. */
    public function detalleModulo(): void
    {
        $this->requireRole('admin');

        $modulo = $this->param('modulo', '');
        $desde  = $this->param('desde') ?: date('Y-m-d', strtotime('-30 days'));
        $hasta  = $this->param('hasta') ?: date('Y-m-d');

        if (!$modulo) $this->json(['ok' => false, 'error' => 'Módulo inválido.'], 422);

        $this->json([
            'ok'      => true,
            'detalle' => $this->model->detallePorUsuario($modulo, $desde, $hasta),
        ]);
    }
}
