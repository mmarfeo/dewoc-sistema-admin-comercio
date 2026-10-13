<?php
/**
 * Aviso por los tres canales cuando se genera un reporte (ver docs/tareas-pendientes.md
 * #3): push, bandeja de notificaciones in-app y un mensaje en el Chat IA de cada
 * destinatario. Pensado para reutilizarse desde cualquier script de cron futuro, no solo
 * el semanal — por eso recibe el módulo de permiso como parámetro en vez de tenerlo
 * hardcodeado, y no depende de Session (puede llamarse desde CLI).
 */
class NotificadorReportes
{
    /**
     * @param int    $reporteId Fila ya insertada en reportes_ia — arma el link a partir del id.
     * @param string $modulo    Módulo de permiso que decide los destinatarios (ej. 'reportes_semanal').
     */
    public static function notificarReporteGenerado(int $reporteId, string $modulo, string $titulo, string $resumen): void
    {
        // ChatModel no se require a sí mismo Model.php (asume que ya está cargado) — se
        // pide acá explícito para que este helper también funcione si algún día se llama
        // desde un contexto que todavía no lo cargó.
        require_once BASE_PATH . '/app/Models/Model.php';
        require_once BASE_PATH . '/app/Models/UsuarioModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/app/Models/ChatModel.php';
        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $url = rtrim(BASE_URL, '/') . '/reportes-ia/ver?id=' . $reporteId;

        $usuarioModel = new UsuarioModel();
        $destinatarios = $usuarioModel->findConPermiso($modulo, 'ver');
        if (!$destinatarios) return;

        // 1) Bandeja de notificaciones + 2) mensaje en el Chat IA — uno por destinatario.
        // El link NO va pegado como texto: se guarda en datos_json (tipo='reporte_cron')
        // para que la vista lo dibuje como botón "Ver reporte", mismo patrón que ya usa
        // el chat para los reportes generados en vivo (ver chat/index.php, tipo==='reporte').
        // Así el título+resumen quedan legibles solos, sin que el usuario tenga que entrar
        // al reporte si solo quiere el dato general.
        $reporteUrlRelativa = '/reportes-ia/ver?id=' . $reporteId;
        $datosJson = json_encode(['tipo' => 'reporte_cron', 'reporte_url' => $reporteUrlRelativa]);

        $notifModel = new NotificacionModel();
        $chatModel  = new ChatModel();
        foreach ($destinatarios as $u) {
            $uid = (int)$u['id'];
            $notifModel->crear($uid, $titulo, $resumen, $url, 'reporte_cron');
            $chatModel->guardarMensaje($uid, 'assistant', "📋 Aviso del sistema\n{$titulo}\n{$resumen}", null, $datosJson);
        }

        // 3) Push — reutiliza findSuscripcionesConPermiso() en vez de derivarlo de
        // $destinatarios: ya filtra exactamente el mismo permiso y devuelve directo las
        // suscripciones con endpoint/p256dh/auth, sin tener que volver a cruzarlas acá.
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        $pushModel = new PushSubscriptionModel();
        $payload = json_encode([
            'title' => $titulo,
            'body'  => $resumen,
            'url'   => $url,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        foreach ($pushModel->findSuscripcionesConPermiso($modulo, 'ver') as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) {
                    $pushModel->eliminarPorId((int)$sub['id']);
                }
            } catch (\Throwable $e) {
                logApp('Error enviando push de reporte generado a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }
}
