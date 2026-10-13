<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Deuda de la empresa hacia los socios y su saldado (ver database/reembolsos_socios_migration.sql
 * para el porqué). Mismo patrón que PagoProveedorModel + pagos_facturas/pagos_gastos: un
 * "aporte" es una fila de pagos_proveedores con origen_pago='socio' (factura/compra/sueldo
 * pagado con plata propia, o tipo='aporte' libre); un "reembolso" es lo que la EMPRESA le
 * devuelve al socio, aplicado contra uno o más aportes puntuales.
 */
class ReembolsoSocioModel extends Model
{
    protected string $table = 'reembolsos_socios';

    public function guardar(array $data, array $aportesAplicados = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO reembolsos_socios
             (socio_id, fecha, concepto, monto_mercado_pago, monto_efectivo, monto_debito,
              monto_transferencia, monto_cheque, monto_otro, monto_total, comprobante_path,
              notas, usuario_id)
             VALUES
             (:socio_id, :fecha, :concepto, :monto_mercado_pago, :monto_efectivo, :monto_debito,
              :monto_transferencia, :monto_cheque, :monto_otro, :monto_total, :comprobante_path,
              :notas, :usuario_id)"
        );
        $stmt->execute([
            ':socio_id'            => $data['socio_id'],
            ':fecha'               => $data['fecha']               ?? date('Y-m-d'),
            ':concepto'            => $data['concepto']            ?? null,
            ':monto_mercado_pago'  => $data['monto_mercado_pago']  ?? 0,
            ':monto_efectivo'      => $data['monto_efectivo']      ?? 0,
            ':monto_debito'        => $data['monto_debito']        ?? 0,
            ':monto_transferencia' => $data['monto_transferencia'] ?? 0,
            ':monto_cheque'        => $data['monto_cheque']        ?? 0,
            ':monto_otro'          => $data['monto_otro']          ?? 0,
            ':monto_total'         => $data['monto_total']         ?? 0,
            ':comprobante_path'    => $data['comprobante_path']    ?? null,
            ':notas'               => $data['notas']               ?? null,
            ':usuario_id'          => $data['usuario_id'],
        ]);
        $reembolsoId = (int) $this->db->lastInsertId();

        foreach ($aportesAplicados as $item) {
            $this->aplicarAAporte($reembolsoId, (int) $item['pago_id'], (float) $item['monto_aplicado']);
        }

        return $reembolsoId;
    }

    /**
     * Vincula (o suma monto a un vínculo ya existente) un reembolso con un aporte puntual
     * (fila de pagos_proveedores). Valida que el monto no supere ni el disponible del
     * reembolso ni el saldo pendiente del aporte — sin este chequeo es fácil "tragarse"
     * de más un reembolso contra un aporte más chico y dejar la diferencia sin poder
     * aplicarla a ningún otro (bug real detectado: reembolso de $400.000 aplicado entero
     * contra un aporte de $334.200,50). También rechaza vincular contra un aporte que ya
     * está anulado — de lo contrario queda un vínculo "huérfano" que infla el Haber del
     * extracto sin un Debe real que lo respalde (mismo bug real: un aporte se corrigió/
     * anuló después de vincularlo, y el reembolso se quedó apuntando a la versión vieja).
     */
    public function aplicarAAporte(int $reembolsoId, int $pagoId, float $monto): void
    {
        $reembolso = $this->queryOne(
            "SELECT monto_total, COALESCE((
                 SELECT SUM(monto_aplicado) FROM reembolsos_socios_aportes WHERE reembolso_id = ?
             ), 0) AS ya_aplicado
             FROM reembolsos_socios WHERE id = ?",
            [$reembolsoId, $reembolsoId]
        );
        if (!$reembolso) throw new \RuntimeException('El reembolso no existe.');
        $disponibleReembolso = round((float)$reembolso['monto_total'] - (float)$reembolso['ya_aplicado'], 2);

        $aporte = $this->queryOne(
            "SELECT pp.monto_total, pp.estado, COALESCE((
                 SELECT SUM(rsa.monto_aplicado)
                 FROM reembolsos_socios_aportes rsa
                 JOIN reembolsos_socios rs ON rs.id = rsa.reembolso_id
                 WHERE rsa.pago_id = pp.id AND rs.estado = 'activo'
             ), 0) AS ya_reembolsado
             FROM pagos_proveedores pp WHERE pp.id = ?",
            [$pagoId]
        );
        if (!$aporte) throw new \RuntimeException('El aporte no existe.');
        if ($aporte['estado'] !== 'activo') {
            throw new \RuntimeException('Ese aporte está anulado, no se puede vincular un reembolso a él.');
        }
        $saldoAporte = round((float)$aporte['monto_total'] - (float)$aporte['ya_reembolsado'], 2);

        $monto = round($monto, 2);
        if ($monto - $disponibleReembolso > 0.01) {
            throw new \RuntimeException("El monto supera lo disponible del reembolso (" . number_format($disponibleReembolso, 2, ',', '.') . ").");
        }
        if ($monto - $saldoAporte > 0.01) {
            throw new \RuntimeException("El monto supera el saldo pendiente de ese aporte (" . number_format($saldoAporte, 2, ',', '.') . ").");
        }

        $this->db->prepare(
            "INSERT INTO reembolsos_socios_aportes (reembolso_id, pago_id, monto_aplicado)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_aplicado = monto_aplicado + VALUES(monto_aplicado)"
        )->execute([$reembolsoId, $pagoId, $monto]);
    }

    /**
     * Aportes (pagos_proveedores con origen_pago='socio') de un socio puntual que todavía
     * tienen saldo sin reembolsar — para la tabla de selección al armar un reembolso nuevo,
     * mismo rol que PagoProveedorModel::getFacturasPendientesByProveedor().
     */
    public function getAportesPendientesBySocio(int $socioId): array
    {
        return $this->queryAll(
            "SELECT pp.id, pp.fecha, pp.concepto, pp.tipo, pp.monto_total,
                    COALESCE(e.nombre, p.razon_social, pp.beneficiario) AS nombre_referencia,
                    COALESCE((
                        SELECT SUM(rsa.monto_aplicado)
                        FROM reembolsos_socios_aportes rsa
                        JOIN reembolsos_socios rs ON rs.id = rsa.reembolso_id
                        WHERE rsa.pago_id = pp.id AND rs.estado = 'activo'
                    ), 0) AS reembolsado,
                    pp.monto_total - COALESCE((
                        SELECT SUM(rsa.monto_aplicado)
                        FROM reembolsos_socios_aportes rsa
                        JOIN reembolsos_socios rs ON rs.id = rsa.reembolso_id
                        WHERE rsa.pago_id = pp.id AND rs.estado = 'activo'
                    ), 0) AS saldo
             FROM pagos_proveedores pp
             LEFT JOIN proveedores p ON p.id = pp.proveedor_id
             LEFT JOIN empleados   e ON e.id = pp.empleado_id
             WHERE pp.socio_id = ? AND pp.origen_pago = 'socio' AND pp.estado = 'activo' AND pp.eliminado = 0
             HAVING saldo > 0.009
             ORDER BY pp.fecha ASC",
            [$socioId]
        );
    }

    /**
     * Deuda pendiente de la empresa hacia cada socio activo (total aportado − total
     * reembolsado) — para la tabla nueva en pagos-proveedores/index.php. Solo devuelve
     * socios con saldo > 0 (mismo criterio que getCuentaCorriente() de proveedores).
     */
    public function getDeudaPorSocio(): array
    {
        return $this->queryAll(
            "SELECT s.id AS socio_id, s.nombre AS socio,
                    COALESCE(aportes.total, 0) AS total_aportado,
                    COALESCE(reembolsos.total, 0) AS total_reembolsado,
                    COALESCE(aportes.total, 0) - COALESCE(reembolsos.total, 0) AS saldo
             FROM socios s
             LEFT JOIN (
                 SELECT socio_id, SUM(monto_total) AS total
                 FROM pagos_proveedores
                 WHERE origen_pago = 'socio' AND estado = 'activo' AND eliminado = 0
                 GROUP BY socio_id
             ) aportes ON aportes.socio_id = s.id
             LEFT JOIN (
                 SELECT socio_id, SUM(monto_total) AS total
                 FROM reembolsos_socios
                 WHERE estado = 'activo'
                 GROUP BY socio_id
             ) reembolsos ON reembolsos.socio_id = s.id
             WHERE s.eliminado = 0 AND s.activo = 1
             HAVING saldo > 0.009
             ORDER BY saldo DESC"
        );
    }

    /**
     * Por cada socio con reembolsos activos que quedaron con plata "suelta" (sin vincular
     * a ningún aporte puntual, ver aplicarAAporte()), devuelve el total sin aplicar y el
     * id del reembolso más antiguo con ese remanente — para poder redirigir directo a
     * "vincular a aporte" desde la tabla de deuda con socios en pagos-proveedores/index.php.
     */
    public function getSinAplicarPorSocio(): array
    {
        $reembolsos = $this->queryAll(
            "SELECT rs.id, rs.socio_id, rs.fecha,
                    rs.monto_total - COALESCE(ap.aplicado, 0) AS disponible
             FROM reembolsos_socios rs
             LEFT JOIN (
                 SELECT reembolso_id, SUM(monto_aplicado) AS aplicado
                 FROM reembolsos_socios_aportes
                 GROUP BY reembolso_id
             ) ap ON ap.reembolso_id = rs.id
             WHERE rs.estado = 'activo'
             HAVING disponible > 0.009
             ORDER BY rs.socio_id, rs.fecha ASC, rs.id ASC"
        );

        $porSocio = [];
        foreach ($reembolsos as $r) {
            $sid = (int) $r['socio_id'];
            if (!isset($porSocio[$sid])) {
                $porSocio[$sid] = ['total' => 0.0, 'reembolso_id' => (int) $r['id']];
            }
            $porSocio[$sid]['total'] += (float) $r['disponible'];
        }
        return $porSocio;
    }

    /**
     * Extracto cronológico con saldo corrido de UN socio — mismo patrón que
     * PagoProveedorModel::getExtracto() para proveedores (Debe/Haber/Saldo), pero acá
     * Debe = aportes suyos todavía sin reembolsar del todo, Haber = reembolsos ya
     * aplicados a esos aportes. Igual que con facturas/gastos, un aporte que ya quedó
     * 100% reembolsado desaparece del extracto (tanto su fila de Debe como los Haber
     * que lo cubrieron) — el extracto muestra lo que sigue abierto, no todo el histórico.
     *
     * COLLATE explícito en referencia: mismo desajuste que PagoProveedorModel::getExtracto()
     * (proveedores/pagos_proveedores en utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci según la
     * tabla) — sin normalizar, el UNION ALL tira "Illegal mix of collations".
     */
    public function getExtracto(int $socioId, bool $incluirHistorico = false): array
    {
        // Histórico: sin el filtro "todavía con saldo" tanto del lado del aporte (Debe)
        // como del reembolso que lo cubrió (Haber) — igual que PagoProveedorModel::getExtracto(),
        // el saldo final no cambia (lo saldado se cancela Debe/Haber) pero se ve el camino completo.
        $condDebe  = $incluirHistorico ? '' : 'HAVING saldo > 0.009';
        $condHaber = $incluirHistorico ? '' : "AND pp.monto_total - COALESCE((
                          SELECT SUM(rsa2.monto_aplicado)
                          FROM reembolsos_socios_aportes rsa2
                          JOIN reembolsos_socios rs2 ON rs2.id = rsa2.reembolso_id
                          WHERE rs2.estado = 'activo' AND rsa2.pago_id = pp.id
                      ), 0) > 0.009";

        $movimientos = $this->queryAll(
            "SELECT fecha, 'aporte' AS tipo, id, referencia, debe, haber FROM (
                SELECT pp.fecha, pp.id,
                       CONVERT(COALESCE(NULLIF(pp.concepto, ''), p.razon_social, e.nombre, pp.beneficiario, CONCAT('Aporte #', pp.id)) USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       pp.monto_total AS debe, 0 AS haber,
                       pp.monto_total - COALESCE((
                           SELECT SUM(rsa.monto_aplicado)
                           FROM reembolsos_socios_aportes rsa
                           JOIN reembolsos_socios rs ON rs.id = rsa.reembolso_id
                           WHERE rsa.pago_id = pp.id AND rs.estado = 'activo'
                       ), 0) AS saldo
                FROM pagos_proveedores pp
                LEFT JOIN proveedores p ON p.id = pp.proveedor_id
                LEFT JOIN empleados   e ON e.id = pp.empleado_id
                WHERE pp.socio_id = :sid1 AND pp.origen_pago = 'socio' AND pp.estado = 'activo' AND pp.eliminado = 0
                {$condDebe}
             ) a

             UNION ALL

             SELECT fecha, 'reembolso' AS tipo, id, referencia, debe, haber FROM (
                SELECT rs.fecha, rs.id,
                       CONVERT(CONCAT('Reembolso aplicado a: ', COALESCE(NULLIF(pp.concepto, ''), p.razon_social, e.nombre, pp.beneficiario, CONCAT('aporte #', pp.id))) USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       0 AS debe, rsa.monto_aplicado AS haber
                FROM reembolsos_socios_aportes rsa
                JOIN reembolsos_socios   rs ON rs.id = rsa.reembolso_id AND rs.estado = 'activo'
                JOIN pagos_proveedores   pp ON pp.id = rsa.pago_id AND pp.estado = 'activo'
                LEFT JOIN proveedores p ON p.id = pp.proveedor_id
                LEFT JOIN empleados   e ON e.id = pp.empleado_id
                WHERE pp.socio_id = :sid2
                  {$condHaber}
             ) b

             ORDER BY fecha ASC, id ASC",
            [':sid1' => $socioId, ':sid2' => $socioId]
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

    public function findById(int $id): array|false
    {
        return $this->queryOne(
            "SELECT rs.*, s.nombre AS socio_nombre, u.nombre AS usuario_nombre
             FROM reembolsos_socios rs
             JOIN socios s ON s.id = rs.socio_id
             LEFT JOIN usuarios u ON u.id = rs.usuario_id
             WHERE rs.id = ?",
            [$id]
        );
    }

    /** Aportes puntuales a los que se aplicó un reembolso — para la pantalla de detalle. */
    public function getAportesDelReembolso(int $reembolsoId): array
    {
        return $this->queryAll(
            "SELECT rsa.monto_aplicado, pp.id AS pago_id, pp.concepto, pp.fecha, pp.monto_total, pp.tipo,
                    COALESCE(e.nombre, p.razon_social, pp.beneficiario) AS nombre_referencia
             FROM reembolsos_socios_aportes rsa
             JOIN pagos_proveedores pp ON pp.id = rsa.pago_id
             LEFT JOIN proveedores p ON p.id = pp.proveedor_id
             LEFT JOIN empleados   e ON e.id = pp.empleado_id
             WHERE rsa.reembolso_id = ?",
            [$reembolsoId]
        );
    }

    /** Historial de reembolsos de un socio puntual — para el modal de detalle de su tarjeta. */
    public function listarPorSocio(int $socioId): array
    {
        return $this->queryAll(
            "SELECT rs.*, u.nombre AS usuario_nombre
             FROM reembolsos_socios rs
             LEFT JOIN usuarios u ON u.id = rs.usuario_id
             WHERE rs.socio_id = ?
             ORDER BY rs.fecha DESC, rs.id DESC",
            [$socioId]
        );
    }

    /** Anula un reembolso — los aportes que cubría vuelven a tener saldo pendiente (ver
     *  getAportesPendientesBySocio(), que ya filtra por reembolsos.estado='activo'). */
    public function anular(int $id): void
    {
        $this->db->prepare("UPDATE reembolsos_socios SET estado = 'anulado' WHERE id = ?")->execute([$id]);
    }
}
