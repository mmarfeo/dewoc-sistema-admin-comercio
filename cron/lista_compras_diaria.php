<?php
/**
 * Cron diario: calcula qué materias primas/insumos/productos están por agotarse (según el
 * consumo real de los últimos 30 días, no solo el mínimo estático) y, si hay candidatos
 * nuevos, los deja cargados en /lista-compras como 'sugerido' + avisa por push + bandeja +
 * Chat IA a quien tenga el permiso 'lista_compras' (mismo mecanismo de 3 canales que
 * core/NotificadorReportes.php, ver cron/reporte_margen_producto.php).
 *
 * A diferencia de un reporte_ia, acá el mensaje del Chat IA se guarda como una tarjeta de
 * confirmación en lote (mismo datos_json que arma ChatController::armarPayloadConfirmacionAccion,
 * accion="confirmar_lista_compras_sugeridos") — el usuario la confirma o cancela con los
 * mismos botones genéricos que cualquier otra acción del chat (ver ChatController::confirmar()/
 * cancelar()), y lo confirmado pasa de 'sugerido' a 'pendiente'. Si no hay nada nuevo para
 * sugerir ese día, no se manda ningún aviso (evita ruido).
 *
 * Programar en hPanel > Avanzado > Cron Jobs, una vez por día (ej. a la mañana temprano):
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/lista_compras_diaria.php
 *   - Por URL: https://tudominio.com/cron/lista_compras_diaria.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/ListaComprasModel.php';
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
    logApp('[cron lista_compras_diaria] ' . $msg);
}

$sugeridos = (new ListaComprasModel())->generarSugeridos();
if (!$sugeridos) {
    salida('Sin candidatos nuevos hoy — no se mandó aviso.');
    exit;
}

// $MAX_ITEMS_CARD: a diferencia de la lista de texto de antes, ahora cada ítem es una fila
// con checkbox (ver chat/index.php::construirTarjetaListaCompras) — un poco más angosto
// que el viejo $MAX_LISTADOS=10 para que la tarjeta no quede kilométrica; el resto sigue
// entrando igual a /lista-compras como 'sugerido', solo no aparece en ESTA tarjeta puntual.
$MAX_ITEMS_CARD = 15;
$itemsCard = array_map(static function (array $it): array {
    $detalle = $it['dias_stock_restante'] !== null
        ? 'quedan ~' . number_format((float) $it['dias_stock_restante'], 1, ',', '.') . ' días'
        : 'stock bajo el mínimo';
    // Decimales solo para MP/insumo (kg, litros, etc.) — un producto entero no se compra "2,50 unidades".
    $decimales  = $it['tipo_item'] === 'producto' ? 0 : 2;
    $stockTexto = number_format((float) $it['stock_actual'], $decimales, ',', '.') . ($it['unidad'] ? ' ' . $it['unidad'] : '');
    return ['id' => (int) $it['id'], 'nombre' => $it['nombre'], 'detalle' => $detalle, 'stock_texto' => $stockTexto];
}, array_slice($sugeridos, 0, $MAX_ITEMS_CARD));

$resto = count($sugeridos) - count($itemsCard);

// resumen_texto: sigue siendo texto plano (va también a push/bandeja, que no saben renderizar
// checkboxes) — la tarjeta interactiva del chat arma su propia lista a partir de payload.items,
// este texto es el respaldo/resumen.
$lineas = array_map(
    static fn(array $it) => '- ' . $it['nombre'] . ' (' . $it['detalle'] . ' · stock: ' . $it['stock_texto'] . ')',
    $itemsCard
);
if ($resto > 0) $lineas[] = '- …y ' . $resto . ' más (quedan cargados como sugeridos en /lista-compras).';

$titulo   = '🛒 Lista de compras: ' . count($sugeridos) . ' ítem' . (count($sugeridos) === 1 ? '' : 's') . ' para reponer';
$resumen  = "Detecté que " . (count($sugeridos) === 1 ? 'este ítem está' : 'estos ítems están') . " por agotarse:\n"
    . implode("\n", $lineas)
    . "\nElegí cuáles agregar a la lista de compras (todos vienen tildados de entrada):";

$payload = [
    'tipo'          => 'accion',
    'accion'        => 'confirmar_lista_compras_sugeridos',
    // 'args.ids' es lo que ejecutarHerramienta() usa al confirmar (ver
    // ChatController::confirmar(), que pisa esto con el subconjunto tildado si llega
    // 'resolucion' desde la tarjeta); 'items' es solo para que el chat dibuje los
    // checkboxes con nombre/detalle/stock — no lo usa el backend al ejecutar.
    'args'          => ['ids' => array_column($itemsCard, 'id')],
    'items'         => $itemsCard,
    'resumen_texto' => $resumen,
    'estado'        => 'pendiente',
];
$datosJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

$usuarioModel  = new UsuarioModel();
$destinatarios = $usuarioModel->findConPermiso('lista_compras', 'ver');
if (!$destinatarios) {
    salida(count($sugeridos) . ' candidatos generados, pero nadie tiene el permiso "lista_compras" para recibir el aviso.');
    exit;
}

$url = rtrim(BASE_URL, '/') . '/lista-compras';

// notificaciones.cuerpo es VARCHAR(500) — con muchos ítems el detalle completo (que sí va
// entero al mensaje del Chat IA, sin este límite) puede superarlo; se recorta acá nada más
// para la bandeja, dejando siempre el link a /lista-compras como referencia completa.
$resumenBandeja = mb_strlen($resumen) > 490 ? mb_substr($resumen, 0, 487) . '...' : $resumen;

$notifModel = new NotificacionModel();
$chatModel  = new ChatModel();
foreach ($destinatarios as $u) {
    $uid = (int) $u['id'];
    $notifModel->crear($uid, $titulo, $resumenBandeja, $url, 'lista_compras');
    $chatModel->guardarMensaje($uid, 'assistant', $titulo . "\n" . $resumen, null, $datosJson);
}

if (VAPID_PUBLIC_KEY && VAPID_PRIVATE_KEY) {
    $pushModel = new PushSubscriptionModel();
    $pushPayload = json_encode([
        'title' => $titulo,
        'body'  => $resumen,
        'url'   => $url,
        'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
    ]);

    foreach ($pushModel->findSuscripcionesConPermiso('lista_compras', 'ver') as $sub) {
        try {
            $res = WebPush::enviar(
                ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                $pushPayload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
            );
            if (in_array($res['status'], [404, 410], true)) {
                $pushModel->eliminarPorId((int) $sub['id']);
            }
        } catch (\Throwable $e) {
            logApp('Error enviando push de lista de compras a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
        }
    }
}

salida(count($sugeridos) . ' candidatos generados (ids: ' . implode(',', array_column($sugeridos, 'id')) . ') — avisado por permiso "lista_compras" a ' . count($destinatarios) . ' usuario(s).');
