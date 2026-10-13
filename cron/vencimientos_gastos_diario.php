<?php
/**
 * Cron diario: recordatorio de gastos/servicios pendientes de pago cuya fecha de
 * vencimiento (gastos.fecha_vencimiento) ya pasó o está por llegar. A diferencia de
 * lista_compras_diaria.php, este NO deja de avisar una vez mostrado — se repite todos
 * los días mientras el gasto siga 'pendiente' (es justamente el "recordatorio continuo"
 * pedido), así que no inserta nada en una tabla propia ni pide confirmación: es un aviso
 * puramente informativo, la acción real (pagarlo) se hace desde /gastos o el chat
 * (registrar_pago/registrar_gasto_pagado), no desde este mensaje.
 *
 * Mismo mecanismo de 3 canales que los demás crons (push + bandeja + Chat IA), ver
 * core/NotificadorReportes.php / cron/lista_compras_diaria.php. Apunta al permiso
 * 'gastos' (el mismo que gatea la pantalla /gastos).
 *
 * Programar en hPanel > Avanzado > Cron Jobs, una vez por día (mismo horario que el
 * resto de los avisos diarios, ej. a la mañana temprano):
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/vencimientos_gastos_diario.php
 *   - Por URL: https://tudominio.com/cron/vencimientos_gastos_diario.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/GastoModel.php';
require_once __DIR__ . '/../app/Models/UsuarioModel.php';
require_once __DIR__ . '/../app/Models/NotificacionModel.php';
require_once __DIR__ . '/../app/Models/ChatModel.php';
require_once __DIR__ . '/../app/Models/PushSubscriptionModel.php';
require_once __DIR__ . '/../core/WebPush.php';

// ── Seguridad: solo CLI, o HTTP con el token correcto ───────────────────────
$esCli = PHP_SAPI === 'cli';
if (!$esCli) {
    $token = $_GET['token'] ?? '';
    if (!CRON_SECRET || !hash_equals(CRON_SECRET, (string)$token)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function salida(string $msg): void
{
    echo $msg . "\n";
    logApp('[cron vencimientos_gastos_diario] ' . $msg);
}

const DIAS_UMBRAL = 3;

$items = (new GastoModel())->proximosAVencer(DIAS_UMBRAL);
if (!$items) {
    salida('Sin vencimientos próximos — no se mandó aviso.');
    exit;
}

$vencidos  = array_values(array_filter($items, static fn($it) => (int) $it['dias_para_vencer'] < 0));
$porVencer = array_values(array_filter($items, static fn($it) => (int) $it['dias_para_vencer'] >= 0));

$fmtMonto = static fn(float $n): string => '$' . number_format($n, 0, ',', '.');
$fmtItem  = static function (array $it) use ($fmtMonto): string {
    $dias = (int) $it['dias_para_vencer'];
    if ($dias < 0) {
        $detalle = 'venció hace ' . abs($dias) . ' día' . (abs($dias) === 1 ? '' : 's');
    } elseif ($dias === 0) {
        $detalle = 'vence hoy';
    } else {
        $detalle = 'vence en ' . $dias . ' día' . ($dias === 1 ? '' : 's') . ' (' . date('d/m', strtotime($it['fecha_vencimiento'])) . ')';
    }
    return '- ' . $it['entidad'] . ' — ' . $it['descripcion'] . ': ' . $fmtMonto((float) $it['saldo']) . ' — ' . $detalle;
};

$bloques = [];
if ($vencidos)  $bloques[] = "🔴 Vencidos:\n" . implode("\n", array_map($fmtItem, $vencidos));
if ($porVencer) $bloques[] = "🟡 Vencen pronto:\n" . implode("\n", array_map($fmtItem, $porVencer));

$totalSaldo = array_sum(array_column($items, 'saldo'));
$url        = rtrim(BASE_URL, '/') . '/gastos';

$titulo  = '💳 ' . count($items) . ' pago' . (count($items) === 1 ? '' : 's') . ' pendiente' . (count($items) === 1 ? '' : 's') . ' — ' . $fmtMonto($totalSaldo);
$resumen = implode("\n\n", $bloques) . "\n\nVer y pagar: " . $url;

$destinatarios = (new UsuarioModel())->findConPermiso('gastos', 'ver');
if (!$destinatarios) {
    salida(count($items) . ' vencimientos detectados, pero nadie tiene el permiso "gastos" para recibir el aviso.');
    exit;
}

// notificaciones.cuerpo es VARCHAR(500) — el mensaje del Chat IA (TEXT) no tiene ese límite.
$resumenBandeja = mb_strlen($resumen) > 490 ? mb_substr($resumen, 0, 487) . '...' : $resumen;

$notifModel = new NotificacionModel();
$chatModel  = new ChatModel();
foreach ($destinatarios as $u) {
    $uid = (int) $u['id'];
    $notifModel->crear($uid, $titulo, $resumenBandeja, '/gastos', 'vencimientos');
    // Mensaje plano (sin datos_json): es informativo, no una acción a confirmar — pagar se
    // hace desde /gastos o pidiéndoselo al chat, no clickeando un botón de esta tarjeta.
    $chatModel->guardarMensaje($uid, 'assistant', $titulo . "\n\n" . $resumen);
}

if (VAPID_PUBLIC_KEY && VAPID_PRIVATE_KEY) {
    $pushModel   = new PushSubscriptionModel();
    $pushPayload = json_encode([
        'title' => $titulo,
        'body'  => $resumenBandeja,
        'url'   => $url,
        'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
    ]);

    foreach ($pushModel->findSuscripcionesConPermiso('gastos', 'ver') as $sub) {
        try {
            $res = WebPush::enviar(
                ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                $pushPayload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
            );
            if (in_array($res['status'], [404, 410], true)) {
                $pushModel->eliminarPorId((int) $sub['id']);
            }
        } catch (\Throwable $e) {
            logApp('Error enviando push de vencimientos a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
        }
    }
}

salida(count($items) . ' vencimientos (' . count($vencidos) . ' vencidos, ' . count($porVencer) . ' por vencer), total ' . $fmtMonto($totalSaldo) . ' — avisado por permiso "gastos" a ' . count($destinatarios) . ' usuario(s).');
