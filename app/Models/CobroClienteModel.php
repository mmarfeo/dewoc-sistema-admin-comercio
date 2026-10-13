<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Cobros de cuenta corriente a clientes — espejo simplificado de PagoProveedorModel (sin
 * tipo/socio/empleado/gastos: solo cliente + ventas). A diferencia de facturas_compra
 * (que "flipea" estado pendiente→pagada), `ventas` no gana un estado nuevo — el saldo de
 * cada venta se calcula siempre al vuelo (monto_cuenta_corriente menos lo aplicado en
 * cobros_ventas de cobros activos), ver docs/pos-cliente-cuenta-corriente-checklist.md.
 */
class CobroClienteModel extends Model
{
    protected string $table = 'cobros_clientes';

    /** Tope de cuenta corriente cuando el cliente no tiene un límite propio cargado. */
    const TOPE_DEFAULT = 100000.0;

    public function guardar(array $data, array $ventasAplicadas = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cobros_clientes
             (cliente_id, fecha, concepto, monto_efectivo, monto_debito,
              monto_transferencia, monto_mercadopago, monto_total,
              comprobante_path, notas, usuario_id)
             VALUES
             (:cliente_id, :fecha, :concepto, :monto_efectivo, :monto_debito,
              :monto_transferencia, :monto_mercadopago, :monto_total,
              :comprobante_path, :notas, :usuario_id)"
        );
        $stmt->execute([
            ':cliente_id'          => $data['cliente_id'],
            ':fecha'               => $data['fecha']               ?? date('Y-m-d'),
            ':concepto'            => $data['concepto']            ?? null,
            ':monto_efectivo'      => $data['monto_efectivo']      ?? 0,
            ':monto_debito'        => $data['monto_debito']        ?? 0,
            ':monto_transferencia' => $data['monto_transferencia'] ?? 0,
            ':monto_mercadopago'   => $data['monto_mercadopago']   ?? 0,
            ':monto_total'         => $data['monto_total']         ?? 0,
            ':comprobante_path'    => $data['comprobante_path']    ?? null,
            ':notas'               => $data['notas']               ?? null,
            ':usuario_id'          => $data['usuario_id'],
        ]);
        $cobroId = (int) $this->db->lastInsertId();

        foreach ($ventasAplicadas as $item) {
            $this->aplicarAVenta($cobroId, (int) $item['venta_id'], (float) $item['monto_aplicado']);
        }

        return $cobroId;
    }

    /** Aplica (parcial o total) un cobro a una venta — sin "flip" de estado, el saldo siempre se calcula al vuelo. */
    public function aplicarAVenta(int $cobroId, int $ventaId, float $monto): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cobros_ventas (cobro_id, venta_id, monto_aplicado)
             VALUES (:cobro_id, :venta_id, :monto)
             ON DUPLICATE KEY UPDATE monto_aplicado = monto_aplicado + VALUES(monto_aplicado)"
        );
        $stmt->execute([':cobro_id' => $cobroId, ':venta_id' => $ventaId, ':monto' => $monto]);
    }

    /** Ventas con saldo de cuenta corriente pendiente de un cliente puntual, para el form de cobro. */
    public function getVentasPendientesByCliente(int $clienteId): array
    {
        return $this->queryAll(
            "SELECT v.id, v.created_at AS fecha, v.total_con_iva, v.monto_cuenta_corriente,
                    COALESCE((
                        SELECT SUM(cv.monto_aplicado) FROM cobros_ventas cv
                        JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                        WHERE cv.venta_id = v.id
                    ), 0) AS pagado,
                    (v.monto_cuenta_corriente - COALESCE((
                        SELECT SUM(cv.monto_aplicado) FROM cobros_ventas cv
                        JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                        WHERE cv.venta_id = v.id
                    ), 0)) AS saldo
             FROM ventas v
             WHERE v.cliente_id = :cid AND v.monto_cuenta_corriente > 0
               AND v.eliminado = 0 AND v.estado = 'completada'
             HAVING saldo > 0.009
             ORDER BY v.created_at ASC",
            [':cid' => $clienteId]
        );
    }

    /** Saldo total pendiente de cuenta corriente de un cliente — usado para validar el tope y para mostrarlo en el POS. */
    public function getSaldoPendienteCliente(int $clienteId): float
    {
        return (float) ($this->queryScalar(
            "SELECT COALESCE(SUM(
                v.monto_cuenta_corriente - COALESCE((
                    SELECT SUM(cv.monto_aplicado) FROM cobros_ventas cv
                    JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                    WHERE cv.venta_id = v.id
                ), 0)
             ), 0)
             FROM ventas v
             WHERE v.cliente_id = :cid AND v.monto_cuenta_corriente > 0
               AND v.eliminado = 0 AND v.estado = 'completada'",
            [':cid' => $clienteId]
        ) ?: 0);
    }

    /** Resumen de saldo por cliente, para la tarjeta de "Cuenta Corriente" del listado. */
    public function getCuentaCorriente(): array
    {
        $rows = $this->queryAll(
            "SELECT v.cliente_id,
                    COUNT(*) AS ventas_pendientes,
                    SUM(v.monto_cuenta_corriente) AS total_deuda,
                    SUM(COALESCE((
                        SELECT SUM(cv.monto_aplicado) FROM cobros_ventas cv
                        JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                        WHERE cv.venta_id = v.id
                    ), 0)) AS pagado_parcial
             FROM ventas v
             WHERE v.monto_cuenta_corriente > 0 AND v.eliminado = 0 AND v.estado = 'completada'
             GROUP BY v.cliente_id"
        );

        $resultado = [];
        foreach ($rows as $r) {
            $saldo = (float) $r['total_deuda'] - (float) $r['pagado_parcial'];
            if ($saldo <= 0.009) continue;

            $cliente = $this->queryOne('SELECT nombre FROM clientes WHERE id = ?', [$r['cliente_id']]);
            $resultado[] = [
                'cliente_id'        => (int) $r['cliente_id'],
                'nombre'            => $cliente['nombre'] ?? '(cliente eliminado)',
                'ventas_pendientes' => (int) $r['ventas_pendientes'],
                'total_deuda'       => (float) $r['total_deuda'],
                'pagado_parcial'    => (float) $r['pagado_parcial'],
                'saldo'             => $saldo,
            ];
        }
        usort($resultado, fn($a, $b) => $b['saldo'] <=> $a['saldo']);
        return $resultado;
    }

    public function listar(array $filtros = []): array
    {
        $where  = ['cc.eliminado = 0'];
        $params = [];

        if (!empty($filtros['cliente_id'])) {
            $where[]              = 'cc.cliente_id = :cid';
            $params[':cid']       = (int) $filtros['cliente_id'];
        }
        if (!empty($filtros['desde'])) {
            $where[]               = 'cc.fecha >= :desde';
            $params[':desde']      = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]               = 'cc.fecha <= :hasta';
            $params[':hasta']      = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]                = 'cc.estado = :estado';
            $params[':estado']      = $filtros['estado'];
        }

        return $this->queryAll(
            "SELECT cc.*, cl.nombre AS cliente_nombre, u.nombre AS usuario_nombre
             FROM cobros_clientes cc
             JOIN clientes cl ON cl.id = cc.cliente_id
             LEFT JOIN usuarios u ON u.id = cc.usuario_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY cc.fecha DESC, cc.id DESC",
            $params
        );
    }

    /** Ventas a las que se aplicó un cobro puntual, para la pantalla de detalle. */
    public function getVentasAplicadas(int $cobroId): array
    {
        return $this->queryAll(
            "SELECT cv.venta_id, cv.monto_aplicado, v.created_at AS fecha, v.total_con_iva
             FROM cobros_ventas cv
             JOIN ventas v ON v.id = cv.venta_id
             WHERE cv.cobro_id = ?
             ORDER BY v.created_at ASC",
            [$cobroId]
        );
    }

    /** Anula un cobro — no hace falta revertir nada más: los cálculos de saldo ya filtran estado='activo'. */
    public function anular(int $id): void
    {
        $this->db->prepare("UPDATE cobros_clientes SET estado = 'anulado' WHERE id = ?")->execute([$id]);
    }

    /**
     * Extracto cronológico con saldo corrido de UN cliente — mismo patrón que
     * PagoProveedorModel::getExtracto() (Debe/Haber/Saldo): Debe = ventas con saldo de
     * cuenta corriente, Haber = cobros ya aplicados a esas ventas. $incluirHistorico trae
     * también las ventas ya cobradas del todo (y sus cobros), no solo lo pendiente.
     *
     * COLLATE explícito por las dudas: mismo desajuste de collation visto entre
     * facturas_compra/gastos y pagos_proveedores (ver PagoProveedorModel::getExtracto()) —
     * ventas/cobros_clientes podrían tener el mismo tipo de mezcla.
     */
    public function getExtracto(int $clienteId, bool $incluirHistorico = false): array
    {
        $condDebe  = $incluirHistorico ? '' : 'HAVING saldo > 0.009';
        $condHaber = $incluirHistorico ? '' : "AND v.monto_cuenta_corriente - COALESCE((
                          SELECT SUM(cv2.monto_aplicado)
                          FROM cobros_ventas cv2
                          JOIN cobros_clientes cc2 ON cc2.id = cv2.cobro_id AND cc2.estado = 'activo'
                          WHERE cv2.venta_id = v.id
                      ), 0) > 0.009";

        $movimientos = $this->queryAll(
            "SELECT fecha, 'venta' AS tipo, id, referencia, debe, haber FROM (
                SELECT v.created_at AS fecha, v.id,
                       CONVERT(CONCAT('Venta #', v.id) USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       v.monto_cuenta_corriente AS debe, 0 AS haber,
                       v.monto_cuenta_corriente - COALESCE((
                           SELECT SUM(cv.monto_aplicado)
                           FROM cobros_ventas cv
                           JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                           WHERE cv.venta_id = v.id
                       ), 0) AS saldo
                FROM ventas v
                WHERE v.cliente_id = :cid1 AND v.monto_cuenta_corriente > 0 AND v.eliminado = 0 AND v.estado = 'completada'
                {$condDebe}
             ) a

             UNION ALL

             SELECT fecha, 'cobro' AS tipo, id, referencia, debe, haber FROM (
                SELECT cc.fecha, cc.id,
                       CONVERT(CONCAT('Cobro aplicado a venta #', v.id) USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       0 AS debe, cv.monto_aplicado AS haber
                FROM cobros_ventas cv
                JOIN cobros_clientes cc ON cc.id = cv.cobro_id AND cc.estado = 'activo'
                JOIN ventas          v  ON v.id = cv.venta_id
                WHERE v.cliente_id = :cid2 AND v.monto_cuenta_corriente > 0 AND v.eliminado = 0 AND v.estado = 'completada'
                  {$condHaber}
             ) b

             ORDER BY fecha ASC, id ASC",
            [':cid1' => $clienteId, ':cid2' => $clienteId]
        );

        $saldo      = 0.0;
        $totalDebe  = 0.0;
        $totalHaber = 0.0;
        foreach ($movimientos as &$m) {
            $m['debe']  = (float) $m['debe'];
            $m['haber'] = (float) $m['haber'];
            $totalDebe  += $m['debe'];
            $totalHaber += $m['haber'];
            $saldo      += $m['debe'] - $m['haber'];
            $m['saldo']  = $saldo;
        }
        unset($m);

        return [
            'movimientos' => $movimientos,
            'totalDebe'   => $totalDebe,
            'totalHaber'  => $totalHaber,
            'saldo'       => $saldo,
        ];
    }
}
