<?php
require_once BASE_PATH . '/app/Models/Model.php';

class CajaModel extends Model
{
    protected string $table      = 'turnos';
    protected string $primaryKey = 'id';

    public function getTurnoActivo(int $usuarioId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM turnos WHERE usuario_id=:uid AND estado='abierto' AND eliminado=0 ORDER BY id DESC LIMIT 1",
            [':uid' => $usuarioId]
        );
    }

    public function getUltimoNumero(): int
    {
        return (int)$this->queryScalar("SELECT COALESCE(MAX(numero),0) FROM turnos");
    }

    public function abrir(int $usuarioId, float $montoInicial, string $obs = ''): int
    {
        $numero = $this->getUltimoNumero() + 1;
        return $this->insert([
            'numero'       => $numero,
            'usuario_id'   => $usuarioId,
            'monto_inicial'=> $montoInicial,
            'estado'       => 'abierto',
            'observaciones'=> $obs ?: null,
            'eliminado'    => 0,
            'apertura_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function cerrar(int $turnoId, float $montoCierre, string $obs = ''): bool
    {
        $turno      = $this->findById($turnoId);
        if (!$turno || $turno['estado'] !== 'abierto') return false;

        $calculado  = $this->calcularMontoEfectivo($turnoId, (float)$turno['monto_inicial']);
        $diferencia = $montoCierre - $calculado;

        return $this->update($turnoId, [
            'estado'                 => 'cerrado',
            'monto_cierre_declarado' => $montoCierre,
            'monto_cierre_calculado' => $calculado,
            'diferencia'             => $diferencia,
            'observaciones'          => $obs ?: null,
            'cierre_at'              => date('Y-m-d H:i:s'),
        ]);
    }

    private function calcularMontoEfectivo(int $turnoId, float $montoInicial): float
    {
        $ingresos = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto),0) FROM caja_movimientos WHERE turno_id=:tid AND tipo='ingreso'",
            [':tid' => $turnoId]
        );
        $egresos = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto),0) FROM caja_movimientos WHERE turno_id=:tid AND tipo='egreso'",
            [':tid' => $turnoId]
        );
        return round($montoInicial + $ingresos - $egresos, 2);
    }

    public function registrarMovimiento(array $data): int
    {
        return $this->query(
            "INSERT INTO caja_movimientos (turno_id, tipo, monto, descripcion, venta_id, usuario_id, created_at, updated_at)
             VALUES (:tid, :tipo, :monto, :desc, :vid, :uid, NOW(), NOW())",
            [
                ':tid'  => $data['turno_id'],
                ':tipo' => $data['tipo'],
                ':monto'=> $data['monto'],
                ':desc' => $data['descripcion'] ?? null,
                ':vid'  => $data['venta_id'] ?? null,
                ':uid'  => $data['usuario_id'],
            ]
        );
    }

    public function getMovimientos(int $turnoId): array
    {
        return $this->queryAll(
            "SELECT cm.*, u.nombre AS usuario_nombre
             FROM caja_movimientos cm
             LEFT JOIN usuarios u ON u.id=cm.usuario_id
             WHERE cm.turno_id=:tid
             ORDER BY cm.created_at",
            [':tid' => $turnoId]
        );
    }

    public function getTurnoConMovimientos(int $turnoId): ?array
    {
        $turno = $this->queryOne(
            "SELECT t.*, u.nombre AS usuario_nombre
             FROM turnos t
             LEFT JOIN usuarios u ON u.id=t.usuario_id
             WHERE t.id=:id",
            [':id' => $turnoId]
        );
        if (!$turno) return null;

        $turno['movimientos'] = $this->getMovimientos($turnoId);

        $turno['total_ingresos'] = array_sum(
            array_column(array_filter($turno['movimientos'], fn($m) => $m['tipo'] === 'ingreso'), 'monto')
        );
        $turno['total_egresos'] = array_sum(
            array_column(array_filter($turno['movimientos'], fn($m) => $m['tipo'] === 'egreso'), 'monto')
        );
        $turno['saldo_calculado'] = (float)$turno['monto_inicial'] + $turno['total_ingresos'] - $turno['total_egresos'];

        return $turno;
    }

    public function historial(int $limite = 30): array
    {
        return $this->queryAll(
            "SELECT t.*, u.nombre AS usuario_nombre,
                    (SELECT COUNT(*) FROM caja_movimientos WHERE turno_id=t.id AND tipo='ingreso') AS cant_ingresos,
                    COALESCE(t.monto_cierre_calculado, 0) AS total_calculado
             FROM turnos t
             LEFT JOIN usuarios u ON u.id=t.usuario_id
             WHERE t.eliminado=0
             ORDER BY t.id DESC
             LIMIT :lim",
            [':lim' => $limite]
        );
    }
}
