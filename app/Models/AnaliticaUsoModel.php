<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Frecuencia de uso por pantalla, agregada por día (ver docs/auditoria-analitica-uso.md).
 * Se alimenta desde Controller::render() — un evento por cada vista completa renderizada,
 * no por acciones AJAX puntuales. Pensada para responder "¿qué se usa y qué no?", no como
 * log de seguridad (eso lo sigue cubriendo audit_log / AuditLog).
 */
class AnaliticaUsoModel extends Model
{
    protected string $table = 'analitica_uso_diario';

    /** Suma 1 al contador del día para (usuario, módulo, acción) — crea la fila si no existía. */
    public function registrar(int $usuarioId, string $modulo, string $accion): void
    {
        $this->query(
            "INSERT INTO analitica_uso_diario (fecha, usuario_id, modulo, accion, cantidad)
             VALUES (:fecha, :uid, :mod, :acc, 1)
             ON DUPLICATE KEY UPDATE cantidad = cantidad + 1",
            [
                // Fecha calculada en PHP, no con CURDATE() de MySQL — mismo criterio que el
                // resto del sistema, para no depender del reloj/timezone del servidor de BD.
                ':fecha' => date('Y-m-d'),
                ':uid'   => $usuarioId,
                ':mod'   => $modulo,
                ':acc'   => $accion,
            ]
        );
    }

    /**
     * Ranking de accesos por módulo en un rango de fechas, opcionalmente acotado a un
     * usuario o a un rol puntual. Incluye el último uso dentro del rango y cuántos
     * usuarios distintos lo usaron, para distinguir "lo usa todo el mundo poco" de
     * "lo usa una sola persona mucho".
     */
    public function rankingPorModulo(string $desde, string $hasta, ?int $usuarioId = null, ?int $rolId = null): array
    {
        $where  = ['a.fecha BETWEEN :desde AND :hasta'];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($usuarioId) {
            $where[]         = 'a.usuario_id = :uid';
            $params[':uid']  = $usuarioId;
        }
        if ($rolId) {
            $where[]         = 'u.rol_id = :rol';
            $params[':rol']  = $rolId;
        }

        return $this->queryAll(
            "SELECT a.modulo,
                    SUM(a.cantidad)              AS accesos,
                    COUNT(DISTINCT a.usuario_id)  AS usuarios_distintos,
                    MAX(a.fecha)                  AS ultimo_uso
             FROM analitica_uso_diario a
             JOIN usuarios u ON u.id = a.usuario_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY a.modulo
             ORDER BY accesos DESC",
            $params
        );
    }

    /** Desglose por usuario de un módulo puntual en un rango — para expandir una fila del ranking. */
    public function detallePorUsuario(string $modulo, string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT u.id AS usuario_id, u.nombre, r.nombre AS rol,
                    SUM(a.cantidad) AS accesos, MAX(a.fecha) AS ultimo_uso
             FROM analitica_uso_diario a
             JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN roles r ON r.id = u.rol_id
             WHERE a.modulo = :mod AND a.fecha BETWEEN :desde AND :hasta
             GROUP BY u.id, u.nombre, r.nombre
             ORDER BY accesos DESC",
            [':mod' => $modulo, ':desde' => $desde, ':hasta' => $hasta]
        );
    }

    /**
     * Último uso histórico de cada módulo (sin acotar por rango) — para detectar
     * candidatos a dar de baja independientemente del período que se esté mirando en
     * el ranking principal. Ordenado por más viejo primero.
     */
    public function ultimoUsoPorModulo(): array
    {
        return $this->queryAll(
            "SELECT modulo, MAX(fecha) AS ultimo_uso, SUM(cantidad) AS accesos_totales
             FROM analitica_uso_diario
             GROUP BY modulo
             ORDER BY ultimo_uso ASC"
        );
    }

    /** Retención: borra el detalle diario más viejo que $fecha (ver sección 8 del doc — 6 meses). */
    public function purgarAnteriorA(string $fecha): void
    {
        $this->query('DELETE FROM analitica_uso_diario WHERE fecha < :fecha', [':fecha' => $fecha]);
    }
}
