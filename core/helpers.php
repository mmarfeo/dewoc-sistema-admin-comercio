<?php
/** Escapa para salida HTML */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escapa un texto para HTML y convierte las URLs http(s):// en links clickeables
 * (target=_blank), y **negrita** de markdown en <strong> — el prompt le pide a Gemini
 * que resalte así montos, cantidades y fechas. Usar en vez de e() para texto de fuente
 * confiable (ej. mensajes del Chat IA) que puede incluir un link armado por el propio
 * sistema. Equivalente en PHP de escHtmlLinkificado() (chat/index.php) — mismo orden de
 * pasos, para que un mensaje se vea igual recién llegado (JS) que tras recargar (PHP,
 * al pintar el historial guardado).
 */
function eLinkificado(mixed $value): string
{
    $escapado   = e($value);
    $conNegrita = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escapado);
    return preg_replace(
        '/(https?:\/\/[^\s<]+)/',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        $conNegrita
    );
}

/** Redirigir */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

/** URL completa */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL de asset */
function asset(string $path): string
{
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Token que autoriza ver el ticket de una venta sin sesión iniciada (para el link
 * que se comparte por WhatsApp/email — el cliente no está logueado en el sistema).
 * No es información sensible en sí (no discrimina más que el id ya visible en la URL),
 * solo evita que cualquiera enumere ventas ajenas probando ids consecutivos.
 *
 * Sin TICKET_SHARE_SECRET configurado devuelve ''. hash_hmac() con clave vacía produce un
 * token perfectamente válido pero que cualquiera puede recalcular, así que daría acceso a
 * todas las ventas enumerando ids — exactamente lo que este token existe para impedir.
 *
 * OJO: devolver '' no alcanza por sí solo. hash_equals('', '') es true, así que un
 * llamador que compare contra un ?token= ausente dejaría pasar a cualquiera. Quien valide
 * tiene que exigir además que el token recibido NO esté vacío — ver
 * VentasController::ticket(). Ver config/config.php.
 */
function ticketToken(int $ventaId): string
{
    if (TICKET_SHARE_SECRET === '') return '';
    return substr(hash_hmac('sha256', (string)$ventaId, TICKET_SHARE_SECRET), 0, 16);
}

/** Sanitizar string */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

/** Formatear moneda argentina */
function moneda(float $valor): string
{
    return '$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formatea una cantidad (stock, ingredientes de receta, etc.) recortando los ceros de
 * más — a diferencia de number_format(), que siempre fuerza la cantidad de decimales
 * pedida aunque el valor sea entero. "120,000 cc" se lee ambiguo (parece "120 mil" a
 * quien no está mirando la coma con atención); esto lo deja en "120 cc". Si el valor
 * tiene decimales reales, se conservan (ej. 45,5 sigue mostrando ",5").
 */
function cantidad(float $valor, int $decimales = 3): string
{
    $formateado = number_format($valor, $decimales, ',', '.');
    if ($decimales > 0 && str_contains($formateado, ',')) {
        $formateado = rtrim(rtrim($formateado, '0'), ',');
    }
    return $formateado;
}

/** Formatear fecha a d/m/Y */
function fecha(string $dateStr): string
{
    return date('d/m/Y', strtotime($dateStr));
}

/** Formatear fecha y hora */
function fechaHora(string $dateStr): string
{
    return date('d/m/Y H:i', strtotime($dateStr));
}

/** Generar token aleatorio */
function randomToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/** Verificar si el usuario tiene permiso sobre un módulo */
function can(string $modulo, string $accion = 'ver'): bool
{
    $user = Session::user();
    if (!$user) return false;
    if ($user['rol'] === 'admin') return true;
    $permisos = $_SESSION['permisos'] ?? [];
    // No configurado → acceso total (retrocompatibilidad)
    if (empty($permisos)) return true;
    if (isset($permisos[$modulo][$accion])) {
        return $permisos[$modulo][$accion] === true;
    }
    // Los sub-módulos de reportes (reportes_ventas, reportes_caja, etc.) son más
    // finos que el histórico módulo "reportes" — si un rol/usuario nunca los
    // configuró puntualmente, heredan el valor de "reportes" en vez de caer en el
    // default de acceso total, para no reabrirle reportes a quien ya los tenía
    // bloqueados en bloque antes de que existiera esta granularidad.
    if ($modulo !== 'reportes' && str_starts_with($modulo, 'reportes_') && $modulo !== 'reportes_ia'
        && isset($permisos['reportes'][$accion])) {
        return $permisos['reportes'][$accion] === true;
    }
    return false;
}

function canView(string $modulo): bool   { return can($modulo, 'ver'); }
function canEdit(string $modulo): bool   { return can($modulo, 'editar'); }

/**
 * Puede ver/accionar tareas asignadas a "Administradores" — a propósito NO usa can(),
 * que le da acceso total a CUALQUIER admin sin mirar nada más: acá el objetivo es
 * justamente que dos usuarios con rol admin puedan tener una respuesta distinta (el
 * flag es explícito por usuario, default apagado incluso para admins existentes).
 * Los roles no-admin nunca califican, tengan o no el flag tildado.
 */
function puedeVerTareasGenerales(): bool
{
    $user = Session::user();
    return (bool)($user && $user['rol'] === 'admin' && !empty($user['ve_tareas_generales']));
}

/** Pantalla de inicio configurada para el usuario logueado (fallback: Chat IA). */
function homeUrl(): string
{
    require_once BASE_PATH . '/app/Models/PermisosModel.php';
    $modulo = Session::user()['pantalla_inicio'] ?? '';
    if ($modulo === '' || !array_key_exists($modulo, PermisosModel::MODULOS) || !canView($modulo)) {
        $modulo = 'chat';
    }
    return PermisosModel::MODULOS_URL[$modulo] ?? $modulo;
}

/** Abortar con código HTTP */
function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    echo $message;
    exit;
}

/** Logger simple */
function logApp(string $message, string $level = 'INFO'): void
{
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
    file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
}
