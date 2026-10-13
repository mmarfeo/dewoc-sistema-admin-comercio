<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';
require_once BASE_PATH . '/app/Models/TareaAsignadaModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';

class CajaController extends Controller
{
    private CajaModel $model;

    public function __construct()
    {
        $this->model = new CajaModel();
    }

    public function index(): void
    {
        $this->requireCanView('caja');
        $usuario     = Session::user();
        $cierraCiego = !canEdit('caja');

        $turnoActivo  = $this->model->getTurnoActivo((int)$usuario['id']);
        $turnoDetalle = $turnoActivo ? $this->model->getTurnoConMovimientos((int)$turnoActivo['id']) : null;

        if ($turnoDetalle && $cierraCiego) {
            // No debe llegar al HTML ningún dato que revele lo que el sistema espera encontrar en caja.
            unset($turnoDetalle['esperado'], $turnoDetalle['saldo_calculado']);
        }

        $this->render('caja.index', [
            'pageTitle'   => 'Caja',
            'breadcrumb'  => ['Caja' => null],
            'turnoActivo' => $turnoDetalle,
            'cierraCiego' => $cierraCiego,
            // Ventas cobradas en mano que todavía no entraron a ninguna caja — típicamente
            // pedidos de MenuApp entregados sin turno abierto (ver tarea 4.4 de
            // docs/integracion-menuapp-checklist.md).
            'cobrosPendientes' => (new VentaModel())->cobrosPendientes(),
        ]);
    }

    /**
     * Imputa a ESTE turno una venta que quedó con "cobro pendiente" (se entregó y se cobró
     * en mano cuando no había ningún turno abierto). Reusa VentaModel::cobrarEnCaja(), que
     * es idempotente: si alguien ya la imputó, no la duplica.
     */
    public function imputarCobroPendiente(): void
    {
        $this->requireCanEdit('caja');
        Csrf::check();

        $ventaId = (int)$this->input('venta_id', 0);
        if (!$ventaId) $this->json(['ok' => false, 'error' => 'ID de venta inválido.'], 422);

        $usuario = Session::user();
        if (!$this->model->getTurnoActivo((int)$usuario['id'])) {
            $this->json(['ok' => false, 'error' => 'Abrí un turno de caja antes de imputar un cobro pendiente.'], 409);
        }

        $r = (new VentaModel())->cobrarEnCaja($ventaId);
        if (empty($r['ok'])) {
            $this->json(['ok' => false, 'error' => 'La venta no existe o está anulada.'], 404);
        }
        if (!empty($r['ya_cobrada'])) {
            $this->json(['ok' => false, 'error' => 'Esa venta ya estaba imputada a una caja.'], 409);
        }

        AuditLog::log('imputar_cobro', 'caja',
            "Venta #{$ventaId}: cobro pendiente imputado al turno #{$r['turno_id']}");

        $this->json(['ok' => true, 'mensaje' => 'Cobro imputado a este turno.']);
    }

    public function abrir(): void
    {
        $this->requireCanView('caja');
        Csrf::check();

        $usuario = Session::user();
        $existe  = $this->model->getTurnoActivo((int)$usuario['id']);
        if ($existe) {
            $this->json(['ok' => false, 'error' => 'Ya tiene un turno abierto.'], 409);
        }

        $montoInicial = (float)$this->input('monto_inicial', 0);
        $obs          = $this->input('observaciones', '');

        // Se mira ANTES de abrir: si ya había un turno, para MenuApp no cambia nada (solo
        // le importa que haya al menos uno), así que no se manda un aviso de más.
        $eraLaPrimeraCaja = !$this->model->hayTurnoAbierto();

        $id = $this->model->abrir((int)$usuario['id'], $montoInicial, $obs);

        // MenuApp vuelve a aceptar pedidos (D10). Después de abrir, nunca antes: si el
        // webhook falla, el turno ya quedó abierto igual y MenuApp se entera por GET /estado.
        if ($eraLaPrimeraCaja) MenuAppNotifier::cajaAbierta();

        // Tareas en modo "turno_abierto" (ver docs/tareas-pendientes.md #2): recién ahora
        // se sabe quién va a estar en caja, así que el aviso se dispara acá y no al crear
        // la tarea.
        $this->notificarTareasTurnoAbierto((int)$usuario['id']);

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Turno de caja abierto.']);
    }

    /**
     * Calcula lo que daría el cierre con los montos declarados SIN cerrarlo todavía — para
     * mostrarle el resultado al usuario y que pueda cancelar (ir a revisar ventas) en vez de
     * quedar comprometido a un cierre con diferencia sin haberlo visto antes.
     */
    public function preverCierre(): void
    {
        $this->requireCanView('caja');
        Csrf::check();

        $turnoId           = (int)$this->input('turno_id');
        $efectivoDeclarado = (float)$this->input('efectivo_declarado', 0);
        $mpDeclarado       = (float)$this->input('mp_declarado', 0);

        $preview = $this->model->previsualizar($turnoId, $efectivoDeclarado, $mpDeclarado);
        if (!$preview) {
            $this->json(['ok' => false, 'error' => 'No se pudo calcular el cierre.'], 409);
        }

        $diagnostico = $preview['tiene_diferencia']
            ? $this->model->diagnosticar($turnoId, $preview['diferencia_efectivo'], $preview['diferencia_mp'])
            : [];

        $this->json([
            'ok'               => true,
            'tiene_diferencia' => $preview['tiene_diferencia'],
            'resultado'        => [
                'diferencia_efectivo' => $preview['diferencia_efectivo'],
                'diferencia_mp'       => $preview['diferencia_mp'],
                'ok_efectivo'         => abs($preview['diferencia_efectivo']) < 0.01,
                'ok_mp'               => abs($preview['diferencia_mp']) < 0.01,
            ],
            'diagnostico'      => $diagnostico,
            // Pedidos de MenuApp entregados y cobrados en mano que todavía no entraron a
            // ninguna caja (tarea 6.5): se avisa antes de confirmar, pero no bloquea el
            // cierre. Si se cierra igual, quedan para imputar en el próximo turno.
            'menuapp_pendientes' => (new VentaModel())->contarCobrosPendientesMenuApp(),
        ]);
    }

    public function cerrar(): void
    {
        $this->requireCanView('caja');
        Csrf::check();

        $turnoId           = (int)$this->input('turno_id');
        $efectivoDeclarado = (float)$this->input('efectivo_declarado', 0);
        $mpDeclarado       = (float)$this->input('mp_declarado', 0);
        $obs               = $this->input('observaciones', '');
        $cierreCiego       = !canEdit('caja');

        $resultado = $this->model->cerrar($turnoId, $efectivoDeclarado, $mpDeclarado, $obs, $cierreCiego);
        if (!$resultado) {
            $this->json(['ok' => false, 'error' => 'No se pudo cerrar el turno.'], 409);
        }

        // Se consulta DESPUÉS de cerrar: el aviso sale solo si este era el último turno
        // abierto. Con dos cajeros, que uno cierre no deja al comercio sin tomar pedidos.
        if (!$this->model->hayTurnoAbierto()) MenuAppNotifier::cajaCerrada();

        // Escalamiento de tareas sin resolver (ver docs/tareas-pendientes.md #2): si el
        // usuario que cierra el turno todavía tiene tareas pendientes/aceptadas sin
        // terminar, se avisa a quien las asignó. La tarea no se cierra ni se pierde, sigue
        // disponible para quien abra el próximo turno.
        $this->escalarTareasSinResolver((int)Session::user()['id']);

        $diagnostico = $resultado['tiene_diferencia']
            ? $this->model->diagnosticar($turnoId, $resultado['diferencia_efectivo'], $resultado['diferencia_mp'])
            : [];

        $this->json([
            'ok'          => true,
            'mensaje'     => 'Turno cerrado correctamente.',
            'resultado'   => [
                'diferencia_efectivo' => $resultado['diferencia_efectivo'],
                'diferencia_mp'       => $resultado['diferencia_mp'],
                'ok_efectivo'         => abs($resultado['diferencia_efectivo']) < 0.01,
                'ok_mp'               => abs($resultado['diferencia_mp']) < 0.01,
            ],
            'diagnostico' => $diagnostico,
            'ticket_url'  => url('caja/ticket?id=' . $turnoId),
        ]);
    }

    public function movimiento(): void
    {
        $this->requireCanView('caja');
        Csrf::check();

        $usuario  = Session::user();
        $turnoId  = (int)$this->input('turno_id');
        $tipo     = $this->input('tipo', 'egreso');
        $monto    = (float)$this->input('monto', 0);
        $desc     = $this->input('descripcion', '') ?: null;

        if (!in_array($tipo, ['ingreso','egreso'], true)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }
        if ($monto <= 0) {
            $this->json(['ok' => false, 'error' => 'El monto debe ser mayor que 0.'], 422);
        }

        $this->model->registrarMovimiento([
            'turno_id'    => $turnoId,
            'tipo'        => $tipo,
            'monto'       => $monto,
            'descripcion' => $desc,
            'usuario_id'  => (int)$usuario['id'],
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Movimiento registrado.']);
    }

    public function historial(): void
    {
        $this->requireCanView('caja');
        // Mismo permiso que gatea el listado de Ventas — sin él, cada usuario ve solo los
        // turnos que abrió él mismo, no los de otras cajas/cajeros.
        $usuarioFiltro = canView('ventas_ver_todas') ? null : (int) Session::user()['id'];
        $this->render('caja.historial', [
            'pageTitle'   => 'Historial de Turnos',
            'breadcrumb'  => ['Caja' => 'caja', 'Historial' => null],
            'turnos'      => $this->model->historial(30, $usuarioFiltro),
            'cierraCiego' => !canEdit('caja'),
        ]);
    }

    /** Ticket de cierre para imprimir (térmico). El propio cajero puede reimprimir el suyo. */
    public function ticket(): void
    {
        $this->requireCanView('caja');
        $turnoId = (int)$this->param('id');
        $turno   = $this->model->getTurnoConMovimientos($turnoId);
        if (!$turno || $turno['estado'] !== 'cerrado') {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }
        $this->view('caja.ticket_cierre', ['t' => $turno]);
    }

    /** Diferencias de caja acumuladas por usuario — reporte de auditoría, requiere permiso de edición. */
    public function diferencias(): void
    {
        $this->requireCanEdit('caja');
        $desde = (string)$this->param('desde', date('Y-m-01'));
        $hasta = (string)$this->param('hasta', date('Y-m-d'));

        $this->render('caja.diferencias', [
            'pageTitle'  => 'Diferencias de Caja por Usuario',
            'breadcrumb' => ['Caja' => 'caja', 'Diferencias' => null],
            'desde'      => $desde,
            'hasta'      => $hasta,
            'filas'      => $this->model->diferenciasPorUsuario($desde, $hasta),
        ]);
    }

    /** Push a quien acaba de abrir turno, por cada tarea en modo turno_abierto todavía sin resolver. */
    private function notificarTareasTurnoAbierto(int $usuarioId): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        $tareaModel = new TareaAsignadaModel();
        $pendientes = $tareaModel->listarPendientesTurnoAbierto();
        if (!$pendientes) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';
        $pushModel = new PushSubscriptionModel();
        $subs = $pushModel->findWhere(['usuario_id' => $usuarioId]);
        if (!$subs) return;

        $notifModel = new NotificacionModel();
        foreach ($pendientes as $t) {
            $notifTitulo = '📋 Tenés una tarea asignada';
            $notifCuerpo = "{$t['crea_nombre']} pidió: {$t['titulo']}";
            $notifUrl    = rtrim(BASE_URL, '/') . '/ventas/pos';
            $payload = json_encode([
                'title' => $notifTitulo,
                'body'  => $notifCuerpo,
                'url'   => $notifUrl,
                'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
            ]);
            $notifModel->crear($usuarioId, $notifTitulo, $notifCuerpo, $notifUrl, 'tarea_turno_abierto');
            foreach ($subs as $sub) {
                try {
                    $res = WebPush::enviar(
                        ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                        $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                    );
                    if (in_array($res['status'], [404, 410], true)) {
                        $pushModel->eliminarPorId((int)$sub['id']);
                    }
                } catch (\Throwable $e) {
                    logApp('Error enviando push de tarea (turno abierto) a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }

    /** Push al encargado que creó cada tarea sin resolver, cuando el destinatario cierra su turno sin haberla terminado. */
    private function escalarTareasSinResolver(int $usuarioId): void
    {
        $tareaModel = new TareaAsignadaModel();
        $sinResolver = $tareaModel->listarSinResolverDeUsuario($usuarioId);
        if (!$sinResolver) return;

        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';
        $pushModel  = new PushSubscriptionModel();
        $notifModel = new NotificacionModel();

        foreach ($sinResolver as $t) {
            $usuarioCreaId = (int)$t['usuario_crea_id'];
            $subs = $pushModel->findWhere(['usuario_id' => $usuarioCreaId]);
            if (!$subs) continue;

            $notifTitulo = '⏳ Tarea sin resolver';
            $notifCuerpo = "\"{$t['titulo']}\" sigue sin resolverse — el turno del destinatario se cerró.";
            $notifUrl    = rtrim(BASE_URL, '/') . '/solicitudes?vista=tareas';
            $payload = json_encode([
                'title' => $notifTitulo,
                'body'  => $notifCuerpo,
                'url'   => $notifUrl,
                'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
            ]);
            $notifModel->crear($usuarioCreaId, $notifTitulo, $notifCuerpo, $notifUrl, 'tarea_escalada');
            foreach ($subs as $sub) {
                try {
                    $res = WebPush::enviar(
                        ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                        $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                    );
                    if (in_array($res['status'], [404, 410], true)) {
                        $pushModel->eliminarPorId((int)$sub['id']);
                    }
                } catch (\Throwable $e) {
                    logApp('Error enviando push de escalamiento de tarea a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }
}
