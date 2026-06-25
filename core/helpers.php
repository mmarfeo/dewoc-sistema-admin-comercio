<?php
/** Escapa para salida HTML */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    return ($permisos[$modulo][$accion] ?? false) === true;
}

function canView(string $modulo): bool   { return can($modulo, 'ver'); }
function canEdit(string $modulo): bool   { return can($modulo, 'editar'); }

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
