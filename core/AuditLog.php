<?php

class AuditLog
{
    public static function log(
        string $accion,
        string $modulo,
        string $descripcion,
        ?int   $usuarioId   = null,
        ?string $usuarioNombre = null
    ): void {
        try {
            if (!$usuarioId && class_exists('Session') && Session::user()) {
                $u             = Session::user();
                $usuarioId     = (int)$u['id'];
                $usuarioNombre = $u['nombre'] ?? null;
            }

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? null;

            Database::getInstance()->prepare(
                'INSERT INTO audit_log (usuario_id, usuario_nombre, accion, modulo, descripcion, ip)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$usuarioId, $usuarioNombre, $accion, $modulo, $descripcion, $ip]);
        } catch (\Throwable) {
            // No crashear la app por un error de auditoría
        }
    }
}
