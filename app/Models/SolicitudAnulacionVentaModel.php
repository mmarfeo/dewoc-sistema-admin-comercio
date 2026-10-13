<?php
require_once BASE_PATH . '/app/Models/Model.php';

class SolicitudAnulacionVentaModel extends Model
{
    protected string $table = 'solicitudes_anulacion_venta';

    public function crear(int $ventaId, int $usuarioSolicitaId, string $motivo, ?string $motivoDetalle): int
    {
        return $this->insert([
            'venta_id'            => $ventaId,
            'usuario_solicita_id' => $usuarioSolicitaId,
            'motivo'              => $motivo,
            'motivo_detalle'      => $motivoDetalle ?: null,
            'estado'              => 'pendiente',
        ]);
    }

    /** La solicitud pendiente de una venta puntual, si hay una (no debería haber más de una a la vez). */
    public function findPendientePorVenta(int $ventaId): ?array
    {
        return $this->queryOne(
            "SELECT sav.*, u.nombre AS solicita_nombre
             FROM solicitudes_anulacion_venta sav
             JOIN usuarios u ON u.id = sav.usuario_solicita_id
             WHERE sav.venta_id = :vid AND sav.estado = 'pendiente'
             ORDER BY sav.id DESC LIMIT 1",
            [':vid' => $ventaId]
        ) ?: null;
    }

    /** La última solicitud de una venta (de cualquier estado) — para mostrarle al vendedor si se la rechazaron. */
    public function findUltimaPorVenta(int $ventaId): ?array
    {
        return $this->queryOne(
            "SELECT sav.*, u.nombre AS solicita_nombre, r.nombre AS resuelve_nombre
             FROM solicitudes_anulacion_venta sav
             JOIN usuarios u ON u.id = sav.usuario_solicita_id
             LEFT JOIN usuarios r ON r.id = sav.usuario_resuelve_id
             WHERE sav.venta_id = :vid
             ORDER BY sav.id DESC LIMIT 1",
            [':vid' => $ventaId]
        ) ?: null;
    }

    /**
     * $usuarioId: si se pasa, acota a las solicitudes que ESE usuario pidió (sin compartir
     * con nadie más — no hay "asignación" acá, es pedido→admin resuelve). null = ver todas
     * (uso reservado a quien tiene permiso de Editar sobre el módulo).
     */
    public function findAllConDetalle(string $estado = '', ?int $usuarioId = null): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($estado !== '') {
            $where[]           = 'sav.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($usuarioId !== null) {
            $where[]              = 'sav.usuario_solicita_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        return $this->queryAll(
            "SELECT sav.*, u.nombre AS solicita_nombre, r.nombre AS resuelve_nombre,
                    v.total_con_iva, v.estado AS venta_estado
             FROM solicitudes_anulacion_venta sav
             JOIN usuarios u        ON u.id = sav.usuario_solicita_id
             LEFT JOIN usuarios r   ON r.id = sav.usuario_resuelve_id
             JOIN ventas v          ON v.id = sav.venta_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(sav.estado,'pendiente','aprobada','rechazada'), sav.created_at DESC",
            $params
        );
    }

    public function resolver(int $id, string $estado, int $usuarioResuelveId, ?string $motivoRechazo = null): void
    {
        $this->update($id, [
            'estado'              => $estado,
            'usuario_resuelve_id' => $usuarioResuelveId,
            'resuelto_at'         => date('Y-m-d H:i:s'),
            'motivo_rechazo'      => $motivoRechazo ?: null,
        ]);
    }
}
