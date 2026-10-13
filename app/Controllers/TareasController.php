<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/TareaAsignadaModel.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

/**
 * Tareas asignadas a trabajadores (ver docs/tareas-pendientes.md #2) — el encargado le
 * manda una tarea puntual a un usuario específico o a "quien tenga la caja abierta ese
 * día", y el destinatario la acepta/rechaza/aplaza/finaliza desde el cartel de ventas/pos.
 */
class TareasController extends Controller
{
    private TareaAsignadaModel $model;
    private CajaModel $cajaModel;

    public function __construct()
    {
        $this->model     = new TareaAsignadaModel();
        $this->cajaModel = new CajaModel();
    }

    /** AJAX: la tarea que le corresponde ver al usuario logueado ahora mismo (o null) — la consulta el cartel del POS, con polling. */
    public function pendienteParaMi(): void
    {
        $this->requireAuth();
        $usuario = Session::user();
        $tieneTurno = (bool)$this->cajaModel->getTurnoActivo((int)$usuario['id']);
        $tarea = $this->model->pendienteParaUsuario((int)$usuario['id'], $tieneTurno, puedeVerTareasGenerales());
        $this->json(['ok' => true, 'tarea' => $tarea]);
    }

    public function crear(): void
    {
        $this->requireCanEdit('tareas_asignar');
        Csrf::check();

        $titulo      = trim($this->input('titulo', ''));
        $descripcion = trim($this->input('descripcion', '')) ?: null;
        $modoDestino = $this->input('modo_destino', '');
        $destinoId   = (int)$this->input('usuario_destino_id', 0) ?: null;

        if (!$titulo) $this->json(['ok' => false, 'error' => 'El título es obligatorio.'], 422);
        if (!in_array($modoDestino, ['usuario_especifico', 'turno_abierto', 'administradores'], true)) {
            $this->json(['ok' => false, 'error' => 'Modo de destino inválido.'], 422);
        }
        if ($modoDestino === 'usuario_especifico' && !$destinoId) {
            $this->json(['ok' => false, 'error' => 'Elegí a quién asignarle la tarea.'], 422);
        }

        $usuario = Session::user();
        $id = $this->model->crear((int)$usuario['id'], $titulo, $descripcion, $modoDestino, $destinoId);

        AuditLog::log('crear_tarea', 'tareas', "Tarea #{$id}: {$titulo}");

        // En modo usuario_específico el destinatario ya se conoce, así que se avisa ya
        // mismo. En modo turno_abierto todavía no se sabe quién va a estar en caja, el
        // aviso se dispara recién cuando alguien abre turno (ver CajaController::abrir()).
        // En modo administradores también se sabe de entrada quién califica (los usuarios
        // con ve_tareas_generales=1), así que se avisa ya mismo, igual que usuario_específico.
        if ($modoDestino === 'usuario_especifico') {
            $this->notificarNuevaTarea($destinoId, $titulo, $usuario['nombre'] ?? '');
        } elseif ($modoDestino === 'administradores') {
            require_once BASE_PATH . '/app/Models/UsuarioModel.php';
            foreach ((new UsuarioModel())->findConVeTareasGenerales() as $admin) {
                $this->notificarNuevaTarea((int)$admin['id'], $titulo, $usuario['nombre'] ?? '');
            }
        }

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Tarea asignada.']);
    }

    /** Corrige una tarea ya creada — mismo permiso que crear(): quien puede asignar, puede corregir lo que asignó. */
    public function actualizar(): void
    {
        $this->requireCanEdit('tareas_asignar');
        Csrf::check();

        $id          = (int)$this->input('id', 0);
        $titulo      = trim($this->input('titulo', ''));
        $descripcion = trim($this->input('descripcion', '')) ?: null;
        $modoDestino = $this->input('modo_destino', '');
        $destinoId   = (int)$this->input('usuario_destino_id', 0) ?: null;

        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        if (!$this->model->find($id)) $this->json(['ok' => false, 'error' => 'Tarea no encontrada.'], 404);
        if (!$titulo) $this->json(['ok' => false, 'error' => 'El título es obligatorio.'], 422);
        if (!in_array($modoDestino, ['usuario_especifico', 'turno_abierto', 'administradores'], true)) {
            $this->json(['ok' => false, 'error' => 'Modo de destino inválido.'], 422);
        }
        if ($modoDestino === 'usuario_especifico' && !$destinoId) {
            $this->json(['ok' => false, 'error' => 'Elegí a quién asignarle la tarea.'], 422);
        }

        $this->model->actualizar($id, $titulo, $descripcion, $modoDestino, $destinoId);
        AuditLog::log('actualizar_tarea', 'tareas', "Tarea #{$id}: {$titulo}");

        $this->json(['ok' => true, 'mensaje' => 'Tarea actualizada.']);
    }

    /** Mismo permiso que crear()/actualizar(). Sin baja lógica: se borra de verdad (la tabla no tiene columna `eliminado`). */
    public function eliminar(): void
    {
        $this->requireCanEdit('tareas_asignar');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        $tarea = $this->model->find($id);
        if (!$tarea) $this->json(['ok' => false, 'error' => 'Tarea no encontrada.'], 404);

        $this->model->eliminar($id);
        AuditLog::log('eliminar_tarea', 'tareas', "Tarea #{$id}: {$tarea['titulo']}");

        $this->json(['ok' => true, 'mensaje' => 'Tarea eliminada.']);
    }

    public function aceptar(): void
    {
        [$tarea, $usuario] = $this->obtenerTareaAccionable();
        $this->model->aceptar((int)$tarea['id'], (int)$usuario['id']);
        AuditLog::log('aceptar_tarea', 'tareas', "Tarea #{$tarea['id']} aceptada.");
        $this->json(['ok' => true, 'mensaje' => 'Tarea aceptada.']);
    }

    public function rechazar(): void
    {
        $motivo = trim($this->input('motivo', ''));
        if (!$motivo) $this->json(['ok' => false, 'error' => 'El motivo es obligatorio.'], 422);

        [$tarea, $usuario] = $this->obtenerTareaAccionable();
        $this->model->rechazar((int)$tarea['id'], (int)$usuario['id'], $motivo);
        AuditLog::log('rechazar_tarea', 'tareas', "Tarea #{$tarea['id']} rechazada: {$motivo}");
        $this->json(['ok' => true, 'mensaje' => 'Tarea rechazada.']);
    }

    public function aplazar(): void
    {
        $minutos = (int)$this->input('minutos', 0);
        if (!in_array($minutos, [15, 30, 60], true)) {
            $this->json(['ok' => false, 'error' => 'Tiempo de aplazamiento inválido.'], 422);
        }

        [$tarea] = $this->obtenerTareaAccionable();
        $this->model->aplazar((int)$tarea['id'], $minutos);
        $this->json(['ok' => true, 'mensaje' => "Recordatorio pospuesto {$minutos} minutos."]);
    }

    public function finalizar(): void
    {
        [$tarea, $usuario] = $this->obtenerTareaAccionable();
        if ($tarea['estado'] !== 'aceptada') {
            $this->json(['ok' => false, 'error' => 'La tarea todavía no fue aceptada.'], 422);
        }
        $this->model->finalizar((int)$tarea['id'], (int)$usuario['id']);
        AuditLog::log('finalizar_tarea', 'tareas', "Tarea #{$tarea['id']} finalizada.");
        $this->json(['ok' => true, 'mensaje' => 'Tarea finalizada.']);
    }

    /** Lee tarea_id del POST, valida que exista y que el usuario logueado pueda accionarla. Corta con json de error si no. */
    private function obtenerTareaAccionable(): array
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('tarea_id', 0);
        $tarea = $id ? $this->model->find($id) : null;
        if (!$tarea) $this->json(['ok' => false, 'error' => 'Tarea no encontrada.'], 404);
        if (!in_array($tarea['estado'], ['pendiente', 'aceptada'], true)) {
            $this->json(['ok' => false, 'error' => 'Esta tarea ya fue resuelta.'], 409);
        }

        $usuario = Session::user();
        $tieneTurno = (bool)$this->cajaModel->getTurnoActivo((int)$usuario['id']);
        if (!$this->model->puedeAccionar($tarea, (int)$usuario['id'], $tieneTurno, puedeVerTareasGenerales())) {
            $this->json(['ok' => false, 'error' => 'No podés accionar esta tarea.'], 403);
        }

        return [$tarea, $usuario];
    }

    /** Push a un usuario puntual avisándole que le asignaron una tarea (modo usuario_específico, al crearla). */
    private function notificarNuevaTarea(int $usuarioDestinoId, string $titulo, string $creadorNombre): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $notifTitulo = '📋 Nueva tarea asignada';
        $notifCuerpo = "{$creadorNombre} te asignó: {$titulo}";
        $notifUrl    = rtrim(BASE_URL, '/') . '/ventas/pos';
        $payload = json_encode([
            'title' => $notifTitulo,
            'body'  => $notifCuerpo,
            'url'   => $notifUrl,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        (new NotificacionModel())->crear($usuarioDestinoId, $notifTitulo, $notifCuerpo, $notifUrl, 'tarea_asignada');

        $pushModel = new PushSubscriptionModel();
        foreach ($pushModel->findWhere(['usuario_id' => $usuarioDestinoId]) as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) {
                    $pushModel->eliminarPorId((int)$sub['id']);
                }
            } catch (\Throwable $e) {
                logApp('Error enviando push de tarea a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }
}
