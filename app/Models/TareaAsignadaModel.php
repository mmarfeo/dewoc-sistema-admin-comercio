<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Tareas asignadas a trabajadores (ver docs/tareas-pendientes.md #2). Módulo aparte de
 * `solicitudes` (esa tabla está pensada para pedir la compra de un producto, no tiene
 * estado "rechazada" ni aplazamiento ni el concepto de "usuario específico vs. turno
 * abierto").
 */
class TareaAsignadaModel extends Model
{
    protected string $table = 'tareas_asignadas';

    private const SELECT_BASE = "
        SELECT t.*,
               uc.nombre AS crea_nombre,
               ud.nombre AS destino_nombre,
               ur.nombre AS responde_nombre,
               uf.nombre AS finaliza_nombre
        FROM tareas_asignadas t
        JOIN usuarios uc ON uc.id = t.usuario_crea_id
        LEFT JOIN usuarios ud ON ud.id = t.usuario_destino_id
        LEFT JOIN usuarios ur ON ur.id = t.usuario_responde_id
        LEFT JOIN usuarios uf ON uf.id = t.usuario_finaliza_id
    ";

    public function crear(int $usuarioCreaId, string $titulo, ?string $descripcion, string $modoDestino, ?int $usuarioDestinoId): int
    {
        return $this->insert([
            'usuario_crea_id'    => $usuarioCreaId,
            'titulo'             => $titulo,
            'descripcion'        => $descripcion,
            'modo_destino'       => $modoDestino,
            'usuario_destino_id' => $modoDestino === 'usuario_especifico' ? $usuarioDestinoId : null,
            'estado'             => 'pendiente',
        ]);
    }

    public function find(int $id): ?array
    {
        return $this->queryOne(self::SELECT_BASE . " WHERE t.id = :id", [':id' => $id]) ?: null;
    }

    /**
     * Corrige una tarea ya creada (título, detalle, a quién va dirigida) — para el caso de
     * "me olvidé de asignarla a alguien" o "faltó algo en el detalle", sin tener que borrar
     * y volver a crearla (lo que perdería el historial de quién la creó y cuándo).
     */
    public function actualizar(int $id, string $titulo, ?string $descripcion, string $modoDestino, ?int $usuarioDestinoId): void
    {
        $this->update($id, [
            'titulo'             => $titulo,
            'descripcion'        => $descripcion,
            'modo_destino'       => $modoDestino,
            'usuario_destino_id' => $modoDestino === 'usuario_especifico' ? $usuarioDestinoId : null,
        ]);
    }

    /** Sin baja lógica: esta tabla no tiene columna `eliminado` (a diferencia de la mayoría del sistema). */
    public function eliminar(int $id): void
    {
        $this->db->prepare('DELETE FROM tareas_asignadas WHERE id = ?')->execute([$id]);
    }

    /**
     * La tarea que le corresponde ver a este usuario en el cartel del POS ahora mismo:
     * pendiente/aceptada, sin aplazamiento vigente, y dirigida a él puntualmente, en modo
     * turno_abierto (si $tieneTurnoAbierto), o a "Administradores" (si $puedeVerGenerales,
     * ver puedeVerTareasGenerales() — no todo admin califica). Se muestra una sola a la
     * vez — la más vieja primero — para no saturar el cartel si hubiera varias.
     */
    public function pendienteParaUsuario(int $usuarioId, bool $tieneTurnoAbierto, bool $puedeVerGenerales = false): ?array
    {
        $condiciones = ["(t.modo_destino = 'usuario_especifico' AND t.usuario_destino_id = :uid)"];
        if ($tieneTurnoAbierto) $condiciones[] = "(t.modo_destino = 'turno_abierto')";
        if ($puedeVerGenerales) $condiciones[] = "(t.modo_destino = 'administradores')";
        $condDestino = implode(' OR ', $condiciones);

        return $this->queryOne(
            self::SELECT_BASE . "
             WHERE t.estado IN ('pendiente','aceptada')
               AND (t.aplazado_hasta IS NULL OR t.aplazado_hasta <= NOW())
               AND ({$condDestino})
             ORDER BY t.created_at ASC
             LIMIT 1",
            [':uid' => $usuarioId]
        ) ?: null;
    }

    /**
     * Confirma que $usuarioId puede accionar esta tarea: destinatario puntual, cualquiera
     * si es turno_abierto, o cualquiera con $puedeVerGenerales si es "administradores".
     */
    public function puedeAccionar(array $tarea, int $usuarioId, bool $tieneTurnoAbierto, bool $puedeVerGenerales = false): bool
    {
        if ($tarea['modo_destino'] === 'usuario_especifico') {
            return (int)$tarea['usuario_destino_id'] === $usuarioId;
        }
        if ($tarea['modo_destino'] === 'administradores') {
            return $puedeVerGenerales;
        }
        return $tieneTurnoAbierto;
    }

    public function aceptar(int $id, int $usuarioId): void
    {
        $this->update($id, [
            'estado'              => 'aceptada',
            'usuario_responde_id' => $usuarioId,
            'respondido_at'       => date('Y-m-d H:i:s'),
            'aplazado_hasta'      => null,
        ]);
    }

    public function rechazar(int $id, int $usuarioId, string $motivo): void
    {
        $this->update($id, [
            'estado'              => 'rechazada',
            'usuario_responde_id' => $usuarioId,
            'respondido_at'       => date('Y-m-d H:i:s'),
            'motivo_rechazo'      => $motivo,
            'aplazado_hasta'      => null,
        ]);
    }

    /** Disponible desde pendiente o aceptada — no cambia el estado de base, solo oculta el cartel hasta esa hora. */
    public function aplazar(int $id, int $minutos): void
    {
        $this->update($id, [
            'aplazado_hasta' => date('Y-m-d H:i:s', strtotime("+{$minutos} minutes")),
        ]);
    }

    public function finalizar(int $id, int $usuarioId): void
    {
        $this->update($id, [
            'estado'              => 'finalizada',
            'usuario_finaliza_id' => $usuarioId,
            'finalizado_at'       => date('Y-m-d H:i:s'),
            'aplazado_hasta'      => null,
        ]);
    }

    /**
     * Para el listado de tareas asignadas (dentro de Solicitudes). Si se pasa $usuarioId,
     * se limita a las tareas que le podrían tocar a ESE usuario (dirigidas a él puntualmente,
     * en modo turno_abierto, o a "administradores" si $puedeVerGenerales) — quien no tiene
     * permiso para asignar tareas a otros tampoco debería ver la lista completa de lo que
     * se le asignó a todo el mundo.
     */
    public function listar(string $estado = '', ?int $usuarioId = null, bool $puedeVerGenerales = false): array
    {
        $where = [];
        $params = [];
        if ($estado !== '') {
            $where[] = 't.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($usuarioId !== null) {
            $condDestino = ["t.usuario_destino_id = :uid", "t.modo_destino = 'turno_abierto'"];
            if ($puedeVerGenerales) $condDestino[] = "t.modo_destino = 'administradores'";
            $where[] = '(' . implode(' OR ', $condDestino) . ')';
            $params[':uid'] = $usuarioId;
        }
        $cond = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return $this->queryAll(self::SELECT_BASE . " {$cond} ORDER BY t.created_at DESC", $params);
    }

    /**
     * Tareas sin resolver (pendiente/aceptada) relacionadas con este usuario al cerrar su
     * turno — asignadas puntualmente a él, o en modo turno_abierto (ya que él tenía el
     * turno abierto y pudo haberlas visto) — para el escalamiento al encargado.
     */
    public function listarSinResolverDeUsuario(int $usuarioId): array
    {
        return $this->queryAll(
            self::SELECT_BASE . "
             WHERE t.estado IN ('pendiente','aceptada')
               AND (
                    (t.modo_destino = 'usuario_especifico' AND t.usuario_destino_id = :uid)
                 OR (t.modo_destino = 'turno_abierto')
               )
             ORDER BY t.created_at ASC",
            [':uid' => $usuarioId]
        );
    }

    /** Tareas en modo turno_abierto todavía sin resolver, para avisarle a quien recién abre un turno. */
    public function listarPendientesTurnoAbierto(): array
    {
        return $this->queryAll(
            self::SELECT_BASE . "
             WHERE t.estado IN ('pendiente','aceptada')
               AND t.modo_destino = 'turno_abierto'
               AND (t.aplazado_hasta IS NULL OR t.aplazado_hasta <= NOW())
             ORDER BY t.created_at ASC"
        );
    }
}
