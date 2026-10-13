<?php

class PagoProveedorModel extends Model
{
    protected string $table = 'pagos_proveedores';

    public function guardar(array $data, array $facturasAplicadas = [], array $gastosAplicados = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO pagos_proveedores
             (proveedor_id, empleado_id, beneficiario, tipo, fecha, concepto,
              monto_mercado_pago, monto_efectivo, monto_debito,
              monto_transferencia, monto_cheque, monto_otro, monto_total,
              referencia_mp, numero_comprobante, comprobante_path, notas,
              origen_pago, nombre_socio, socio_id, usuario_id)
             VALUES
             (:proveedor_id, :empleado_id, :beneficiario, :tipo, :fecha, :concepto,
              :monto_mercado_pago, :monto_efectivo, :monto_debito,
              :monto_transferencia, :monto_cheque, :monto_otro, :monto_total,
              :referencia_mp, :numero_comprobante, :comprobante_path, :notas,
              :origen_pago, :nombre_socio, :socio_id, :usuario_id)"
        );
        $stmt->execute([
            ':proveedor_id'        => $data['proveedor_id']        ?? null,
            ':empleado_id'         => $data['empleado_id']         ?? null,
            ':beneficiario'        => $data['beneficiario']        ?? null,
            ':tipo'                => $data['tipo']                ?? 'proveedor',
            ':fecha'               => $data['fecha']               ?? date('Y-m-d'),
            ':concepto'            => $data['concepto']            ?? null,
            ':monto_mercado_pago'  => $data['monto_mercado_pago']  ?? 0,
            ':monto_efectivo'      => $data['monto_efectivo']      ?? 0,
            ':monto_debito'        => $data['monto_debito']        ?? 0,
            ':monto_transferencia' => $data['monto_transferencia'] ?? 0,
            ':monto_cheque'        => $data['monto_cheque']        ?? 0,
            ':monto_otro'          => $data['monto_otro']          ?? 0,
            ':monto_total'         => $data['monto_total']         ?? 0,
            ':referencia_mp'       => $data['referencia_mp']       ?? null,
            ':numero_comprobante'  => $data['numero_comprobante']  ?? null,
            ':comprobante_path'    => $data['comprobante_path']    ?? null,
            ':notas'               => $data['notas']               ?? null,
            ':origen_pago'         => $data['origen_pago']         ?? 'empresa',
            ':nombre_socio'        => $data['nombre_socio']        ?? null,
            ':socio_id'            => $data['socio_id']            ?? null,
            ':usuario_id'          => $data['usuario_id'],
        ]);
        $pagoId = (int) $this->db->lastInsertId();

        foreach ($facturasAplicadas as $item) {
            $this->aplicarAFactura($pagoId, (int) $item['factura_id'], (float) $item['monto_aplicado']);
        }
        foreach ($gastosAplicados as $item) {
            $this->aplicarAGasto($pagoId, (int) $item['gasto_id'], (float) $item['monto_aplicado']);
        }

        return $pagoId;
    }

    public function actualizarPago(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE pagos_proveedores SET
                fecha               = :fecha,
                concepto            = :concepto,
                monto_mercado_pago  = :monto_mercado_pago,
                monto_efectivo      = :monto_efectivo,
                monto_debito        = :monto_debito,
                monto_transferencia = :monto_transferencia,
                monto_cheque        = :monto_cheque,
                monto_otro          = :monto_otro,
                monto_total         = :monto_total,
                origen_pago         = :origen_pago,
                nombre_socio        = :nombre_socio,
                socio_id            = :socio_id
             WHERE id = :id AND eliminado = 0 AND estado != 'anulado'"
        );
        $stmt->execute([
            ':fecha'               => $data['fecha']               ?? date('Y-m-d'),
            ':concepto'            => $data['concepto']            ?? null,
            ':monto_mercado_pago'  => $data['monto_mercado_pago']  ?? 0,
            ':monto_efectivo'      => $data['monto_efectivo']      ?? 0,
            ':monto_debito'        => $data['monto_debito']        ?? 0,
            ':monto_transferencia' => $data['monto_transferencia'] ?? 0,
            ':monto_cheque'        => $data['monto_cheque']        ?? 0,
            ':monto_otro'          => $data['monto_otro']          ?? 0,
            ':monto_total'         => $data['monto_total']         ?? 0,
            ':origen_pago'         => $data['origen_pago']         ?? 'empresa',
            ':nombre_socio'        => $data['nombre_socio']        ?? null,
            ':socio_id'            => $data['socio_id']            ?? null,
            ':id'                  => $id,
        ]);

        // Actualizar monto_aplicado en la factura vinculada y re-evaluar estado
        if (!empty($data['factura_id'])) {
            $facturaId    = (int) $data['factura_id'];
            $montoAplicado = (float) $data['monto_total'];

            $this->db->prepare(
                "UPDATE pagos_facturas SET monto_aplicado = ?
                 WHERE pago_id = ? AND factura_id = ?"
            )->execute([$montoAplicado, $id, $facturaId]);

            // Recalcular estado de la factura (pagada ↔ pendiente según pagos activos)
            $this->db->prepare(
                "UPDATE facturas_compra
                 SET estado = CASE
                     WHEN total_con_iva <= (
                         SELECT COALESCE(SUM(pf.monto_aplicado), 0)
                         FROM pagos_facturas pf
                         JOIN pagos_proveedores pp ON pp.id = pf.pago_id
                         WHERE pf.factura_id = ? AND pp.estado = 'activo'
                     ) THEN 'pagada'
                     ELSE 'pendiente'
                 END
                 WHERE id = ? AND estado != 'anulada'"
            )->execute([$facturaId, $facturaId]);
        }

        // Actualizar monto_aplicado en el gasto vinculado y re-evaluar estado
        if (!empty($data['gasto_id'])) {
            $gastoId       = (int) $data['gasto_id'];
            $montoAplicado = (float) $data['monto_total'];

            $this->db->prepare(
                "UPDATE pagos_gastos SET monto_aplicado = ?
                 WHERE pago_id = ? AND gasto_id = ?"
            )->execute([$montoAplicado, $id, $gastoId]);

            $this->db->prepare(
                "UPDATE gastos
                 SET estado = CASE
                     WHEN monto <= (
                         SELECT COALESCE(SUM(pg.monto_aplicado), 0)
                         FROM pagos_gastos pg
                         JOIN pagos_proveedores pp ON pp.id = pg.pago_id
                         WHERE pg.gasto_id = ? AND pp.estado = 'activo'
                     ) THEN 'pagado'
                     ELSE 'pendiente'
                 END
                 WHERE id = ?"
            )->execute([$gastoId, $gastoId]);
        }
    }

    /**
     * Valida, antes de aplicar un pago a una factura o gasto, que el monto no supere ni el
     * disponible del pago (monto_total - ya aplicado a otros comprobantes) ni el saldo
     * pendiente del comprobante elegido — mismo chequeo que ReembolsoSocioModel::aplicarAAporte(),
     * necesario para no "tragarse" de más un pago contra un comprobante más chico y dejar
     * la diferencia sin poder aplicarla a otro.
     */
    private function _validarAplicacion(int $pagoId, float $monto, string $tablaAplic, string $colItem, int $itemId, string $tablaItem, string $colTotalItem): void
    {
        $pago = $this->queryOne(
            "SELECT monto_total,
                    COALESCE((SELECT SUM(monto_aplicado) FROM pagos_facturas WHERE pago_id = ?), 0)
                  + COALESCE((SELECT SUM(monto_aplicado) FROM pagos_gastos   WHERE pago_id = ?), 0) AS ya_aplicado
             FROM pagos_proveedores WHERE id = ?",
            [$pagoId, $pagoId, $pagoId]
        );
        if (!$pago) throw new \RuntimeException('El pago no existe.');
        $disponiblePago = round((float)$pago['monto_total'] - (float)$pago['ya_aplicado'], 2);

        $item = $this->queryOne(
            "SELECT {$colTotalItem} AS total, COALESCE((
                 SELECT SUM(ap.monto_aplicado)
                 FROM {$tablaAplic} ap
                 JOIN pagos_proveedores pp ON pp.id = ap.pago_id
                 WHERE ap.{$colItem} = ? AND pp.estado = 'activo'
             ), 0) AS ya_pagado
             FROM {$tablaItem} WHERE id = ?",
            [$itemId, $itemId]
        );
        if (!$item) throw new \RuntimeException('El comprobante no existe.');
        $saldoItem = round((float)$item['total'] - (float)$item['ya_pagado'], 2);

        $monto = round($monto, 2);
        if ($monto - $disponiblePago > 0.01) {
            throw new \RuntimeException("El monto supera lo disponible del pago (" . number_format($disponiblePago, 2, ',', '.') . ").");
        }
        if ($monto - $saldoItem > 0.01) {
            throw new \RuntimeException("El monto supera el saldo pendiente de ese comprobante (" . number_format($saldoItem, 2, ',', '.') . ").");
        }
    }

    /** Vincula (o suma monto a un vínculo ya existente) un pago con una factura, y la marca pagada si queda cubierta. */
    public function aplicarAFactura(int $pagoId, int $facturaId, float $monto): void
    {
        $this->_validarAplicacion($pagoId, $monto, 'pagos_facturas', 'factura_id', $facturaId, 'facturas_compra', 'total_con_iva');

        $this->db->prepare(
            "INSERT INTO pagos_facturas (pago_id, factura_id, monto_aplicado)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_aplicado = monto_aplicado + VALUES(monto_aplicado)"
        )->execute([$pagoId, $facturaId, $monto]);

        // Auto-pagar la factura si ya está cubierta
        $this->db->prepare(
            "UPDATE facturas_compra SET estado = 'pagada'
             WHERE id = ?
               AND total_con_iva <= (
                   SELECT COALESCE(SUM(pf.monto_aplicado), 0)
                   FROM pagos_facturas pf
                   JOIN pagos_proveedores pp ON pp.id = pf.pago_id
                   WHERE pf.factura_id = ? AND pp.estado = 'activo'
               )
               AND estado = 'pendiente'"
        )->execute([$facturaId, $facturaId]);
    }

    /** Vincula (o suma monto a un vínculo ya existente) un pago con un gasto, y lo marca pagado si queda cubierto. */
    public function aplicarAGasto(int $pagoId, int $gastoId, float $monto): void
    {
        $this->_validarAplicacion($pagoId, $monto, 'pagos_gastos', 'gasto_id', $gastoId, 'gastos', 'monto');

        $this->db->prepare(
            "INSERT INTO pagos_gastos (pago_id, gasto_id, monto_aplicado)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE monto_aplicado = monto_aplicado + VALUES(monto_aplicado)"
        )->execute([$pagoId, $gastoId, $monto]);

        // Auto-pagar el gasto si ya está cubierto
        $this->db->prepare(
            "UPDATE gastos SET estado = 'pagado'
             WHERE id = ?
               AND monto <= (
                   SELECT COALESCE(SUM(pg.monto_aplicado), 0)
                   FROM pagos_gastos pg
                   JOIN pagos_proveedores pp ON pp.id = pg.pago_id
                   WHERE pg.gasto_id = ? AND pp.estado = 'activo'
               )
               AND estado = 'pendiente'"
        )->execute([$gastoId, $gastoId]);
    }

    public function getGastosDelPago(int $pagoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pg.monto_aplicado,
                    g.id AS gasto_id, g.descripcion, g.fecha, g.monto, g.estado
             FROM pagos_gastos pg
             JOIN gastos g ON g.id = pg.gasto_id
             WHERE pg.pago_id = ?"
        );
        $stmt->execute([$pagoId]);
        return $stmt->fetchAll();
    }

    public function getGastosPendientesByProveedor(int $proveedorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT g.id, g.fecha, g.descripcion, g.monto,
                    COALESCE((
                        SELECT SUM(pg.monto_aplicado)
                        FROM pagos_gastos pg
                        JOIN pagos_proveedores pp ON pp.id = pg.pago_id
                        WHERE pg.gasto_id = g.id AND pp.estado = 'activo'
                    ), 0) AS pagado,
                    g.monto - COALESCE((
                        SELECT SUM(pg.monto_aplicado)
                        FROM pagos_gastos pg
                        JOIN pagos_proveedores pp ON pp.id = pg.pago_id
                        WHERE pg.gasto_id = g.id AND pp.estado = 'activo'
                    ), 0) AS saldo
             FROM gastos g
             WHERE g.proveedor_id = ? AND g.estado = 'pendiente' AND g.eliminado = 0
             ORDER BY g.fecha ASC"
        );
        $stmt->execute([$proveedorId]);
        return $stmt->fetchAll();
    }

    public function listar(array $filtros = []): array
    {
        $where  = ['pp.eliminado = 0'];
        $params = [];

        if (!empty($filtros['proveedor_id'])) {
            $where[]               = 'pp.proveedor_id = :proveedor_id';
            $params['proveedor_id'] = $filtros['proveedor_id'];
        }
        if (!empty($filtros['empleado_id'])) {
            $where[]               = 'pp.empleado_id = :empleado_id';
            $params['empleado_id'] = $filtros['empleado_id'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]       = 'pp.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['desde'])) {
            $where[]         = 'pp.fecha >= :desde';
            $params['desde']  = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]         = 'pp.fecha <= :hasta';
            $params['hasta']  = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]          = 'pp.estado = :estado';
            $params['estado']  = $filtros['estado'];
        }

        $sql = "SELECT pp.*,
                       COALESCE(e.nombre, p.razon_social, pp.beneficiario) AS nombre_beneficiario,
                       u.nombre AS usuario_nombre
                FROM pagos_proveedores pp
                LEFT JOIN proveedores p ON p.id = pp.proveedor_id
                LEFT JOIN empleados   e ON e.id = pp.empleado_id
                LEFT JOIN usuarios    u ON u.id = pp.usuario_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY pp.fecha DESC, pp.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Total pagado por cada socio activo + por la empresa, respetando los MISMOS
     * filtros que ya usa listar() — así la suma de las tarjetas es, por
     * construcción, igual al total de la tabla de pagos que se ve debajo (misma
     * fuente, mismos filtros). Pagos anulados no cuentan salvo que el filtro de
     * estado los pida explícitamente.
     */
    public function resumenPorOrigen(array $filtros = []): array
    {
        $where  = ['pp.eliminado = 0'];
        $params = [];

        if (!empty($filtros['proveedor_id'])) {
            $where[]               = 'pp.proveedor_id = :proveedor_id';
            $params['proveedor_id'] = $filtros['proveedor_id'];
        }
        if (!empty($filtros['empleado_id'])) {
            $where[]               = 'pp.empleado_id = :empleado_id';
            $params['empleado_id'] = $filtros['empleado_id'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]       = 'pp.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['desde'])) {
            $where[]         = 'pp.fecha >= :desde';
            $params['desde']  = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]         = 'pp.fecha <= :hasta';
            $params['hasta']  = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]          = 'pp.estado = :estado';
            $params['estado']  = $filtros['estado'];
        } else {
            // Sin filtro de estado explícito: por defecto solo activos, para que el
            // total de las tarjetas coincida con "lo realmente pagado" y no arrastre
            // pagos anulados (a diferencia de listar(), que sin filtro muestra todo).
            $where[] = "pp.estado = 'activo'";
        }

        $whereSql = implode(' AND ', $where);

        $porSocio = $this->queryAll(
            "SELECT s.id AS socio_id, s.nombre AS socio, COALESCE(SUM(pp.monto_total), 0) AS total
             FROM socios s
             LEFT JOIN pagos_proveedores pp ON pp.socio_id = s.id AND pp.origen_pago = 'socio' AND {$whereSql}
             WHERE s.eliminado = 0 AND s.activo = 1
             GROUP BY s.id, s.nombre
             ORDER BY s.nombre",
            $params
        );

        $totalEmpresa = (float) $this->queryScalar(
            "SELECT COALESCE(SUM(pp.monto_total), 0) FROM pagos_proveedores pp WHERE pp.origen_pago = 'empresa' AND {$whereSql}",
            $params
        );

        $totalGeneral = (float) $this->queryScalar(
            "SELECT COALESCE(SUM(pp.monto_total), 0) FROM pagos_proveedores pp WHERE {$whereSql}",
            $params
        );

        // Aportes de socios sin socio_id asignado todavía (pagos viejos con solo
        // nombre_socio libre, o de un socio dado de baja) — para que la suma total
        // de tarjetas + empresa siga cuadrando con el total general.
        $totalSocios     = array_sum(array_column($porSocio, 'total'));
        $sinAsignar      = (float) $this->queryScalar(
            "SELECT COALESCE(SUM(pp.monto_total), 0)
             FROM pagos_proveedores pp
             WHERE pp.origen_pago = 'socio' AND pp.socio_id IS NULL AND {$whereSql}",
            $params
        );

        return [
            'socios'        => $porSocio,
            'sin_asignar'   => $sinAsignar,
            'empresa'       => $totalEmpresa,
            'total_general' => $totalGeneral,
        ];
    }

    /**
     * Detalle de pagos de un socio puntual (para el modal que se abre al hacer
     * clic en su tarjeta), respetando los mismos filtros vigentes en la página.
     * Cada pago trae las facturas de compra a las que se aplicó (puede ser más
     * de una, o ninguna si el pago quedó sin imputar o es de tipo sueldo/otro).
     */
    public function listarPorSocio(int $socioId, array $filtros = []): array
    {
        $where  = ['pp.eliminado = 0', 'pp.socio_id = :socio_id'];
        $params = ['socio_id' => $socioId];

        if (!empty($filtros['proveedor_id'])) {
            $where[]               = 'pp.proveedor_id = :proveedor_id';
            $params['proveedor_id'] = $filtros['proveedor_id'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]       = 'pp.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['desde'])) {
            $where[]        = 'pp.fecha >= :desde';
            $params['desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[]        = 'pp.fecha <= :hasta';
            $params['hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['estado'])) {
            $where[]         = 'pp.estado = :estado';
            $params['estado'] = $filtros['estado'];
        } else {
            $where[] = "pp.estado = 'activo'";
        }

        $pagos = $this->queryAll(
            "SELECT pp.id, pp.fecha, pp.concepto, pp.tipo, pp.monto_total, pp.estado,
                    COALESCE(e.nombre, p.razon_social, pp.beneficiario) AS nombre_beneficiario,
                    COALESCE((
                        SELECT SUM(rsa.monto_aplicado)
                        FROM reembolsos_socios_aportes rsa
                        JOIN reembolsos_socios rs ON rs.id = rsa.reembolso_id
                        WHERE rsa.pago_id = pp.id AND rs.estado = 'activo'
                    ), 0) AS reembolsado
             FROM pagos_proveedores pp
             LEFT JOIN proveedores p ON p.id = pp.proveedor_id
             LEFT JOIN empleados   e ON e.id = pp.empleado_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pp.fecha DESC, pp.id DESC",
            $params
        );
        if (!$pagos) return [];

        $pagoIds = array_column($pagos, 'id');
        $in      = implode(',', array_map('intval', $pagoIds));
        $facturasRows = $this->queryAll(
            "SELECT pf.pago_id, fc.id AS factura_id, fc.nro_comprobante, fc.tipo_comprobante
             FROM pagos_facturas pf
             JOIN facturas_compra fc ON fc.id = pf.factura_id
             WHERE pf.pago_id IN ({$in})"
        );
        $facturasPorPago = [];
        foreach ($facturasRows as $f) {
            $facturasPorPago[$f['pago_id']][] = $f;
        }

        foreach ($pagos as &$pago) {
            $pago['facturas'] = $facturasPorPago[$pago['id']] ?? [];
        }
        unset($pago);

        return $pagos;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT pp.*,
                    COALESCE(e.nombre, p.razon_social, pp.beneficiario) AS nombre_beneficiario,
                    p.cuit,
                    u.nombre AS usuario_nombre
             FROM pagos_proveedores pp
             LEFT JOIN proveedores p ON p.id = pp.proveedor_id
             LEFT JOIN empleados   e ON e.id = pp.empleado_id
             LEFT JOIN usuarios    u ON u.id = pp.usuario_id
             WHERE pp.id = ? AND pp.eliminado = 0"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getFacturasDelPago(int $pagoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pf.monto_aplicado,
                    fc.id AS factura_id, fc.nro_comprobante, fc.tipo_comprobante,
                    fc.fecha, fc.total_con_iva, fc.estado
             FROM pagos_facturas pf
             JOIN facturas_compra fc ON fc.id = pf.factura_id
             WHERE pf.pago_id = ?"
        );
        $stmt->execute([$pagoId]);
        return $stmt->fetchAll();
    }

    public function anular(int $id): void
    {
        // Revertir estado de facturas y gastos afectados
        $stmt = $this->db->prepare("SELECT factura_id FROM pagos_facturas WHERE pago_id = ?");
        $stmt->execute([$id]);
        $facturas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $stmt = $this->db->prepare("SELECT gasto_id FROM pagos_gastos WHERE pago_id = ?");
        $stmt->execute([$id]);
        $gastos = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->db->prepare("UPDATE pagos_proveedores SET estado = 'anulado' WHERE id = ?")
                 ->execute([$id]);

        foreach ($facturas as $facturaId) {
            $this->db->prepare(
                "UPDATE facturas_compra SET estado = 'pendiente'
                 WHERE id = ? AND estado = 'pagada'"
            )->execute([$facturaId]);
        }

        foreach ($gastos as $gastoId) {
            $this->db->prepare(
                "UPDATE gastos SET estado = 'pendiente'
                 WHERE id = ? AND estado = 'pagado'"
            )->execute([$gastoId]);
        }
    }

    /**
     * Saldo pendiente por proveedor: combina facturas de compra pendientes y
     * gastos pendientes (alquiler, servicios, etc. con proveedor asignado).
     */
    public function getCuentaCorriente(): array
    {
        return $this->db->query(
            "SELECT p.id AS proveedor_id, p.razon_social, p.cuit,
                    COUNT(*)                         AS facturas_pendientes,
                    COALESCE(SUM(items.total), 0)    AS total_deuda,
                    COALESCE(SUM(items.pagado), 0)   AS pagado_parcial,
                    COALESCE(SUM(items.total - items.pagado), 0) AS saldo
             FROM (
                 SELECT fc.proveedor_id, fc.total_con_iva AS total,
                        (SELECT COALESCE(SUM(pf.monto_aplicado),0)
                         FROM pagos_facturas pf
                         JOIN pagos_proveedores pp ON pp.id = pf.pago_id
                         WHERE pf.factura_id = fc.id AND pp.estado = 'activo') AS pagado
                 FROM facturas_compra fc
                 WHERE fc.estado = 'pendiente' AND fc.eliminado = 0

                 UNION ALL

                 SELECT g.proveedor_id, g.monto AS total,
                        (SELECT COALESCE(SUM(pg.monto_aplicado),0)
                         FROM pagos_gastos pg
                         JOIN pagos_proveedores pp ON pp.id = pg.pago_id
                         WHERE pg.gasto_id = g.id AND pp.estado = 'activo') AS pagado
                 FROM gastos g
                 WHERE g.estado = 'pendiente' AND g.eliminado = 0 AND g.proveedor_id IS NOT NULL
             ) items
             JOIN proveedores p ON p.id = items.proveedor_id AND p.eliminado = 0
             GROUP BY p.id, p.razon_social, p.cuit
             HAVING saldo > 0.009
             ORDER BY saldo DESC"
        )->fetchAll();
    }

    /**
     * Auditoría de todo el sistema (todos los proveedores): facturas/gastos marcados como
     * ya resueltos (`estado='pagada'`/`'pagado'`) cuya suma real de pagos aplicados
     * (`pagos_facturas`/`pagos_gastos`, solo pagos activos) NO cubre el total — es decir,
     * casos donde el sistema dice "esto ya se pagó" pero no hay un pago real registrado por
     * ese monto detrás. Existen porque ComprasController::guardar()/importar() marcan TODA
     * factura nueva como 'pagada' sin importar cómo se pagó en la realidad (ver comentario
     * de getExtracto() más abajo) — un gasto puede tener el mismo problema si se lo marcó
     * 'pagado' a mano sin cargar el pago. Por diseño, ningún otro lugar del sistema (cuenta
     * corriente "solo pendiente", getCuentaCorriente(), el listado de saldos) puede ver
     * esta plata: todos confían en el campo `estado`, que acá está mintiendo. Esta consulta
     * es la única forma de encontrarla, recorriendo TODOS los proveedores de una — antes
     * había que ir probando "Histórico completo" proveedor por proveedor.
     */
    public function auditoriaPagadosSinCubrir(): array
    {
        return $this->queryAll(
            "SELECT * FROM (
                SELECT p.id AS proveedor_id, p.razon_social AS proveedor, 'factura' AS tipo,
                       fc.id, fc.fecha,
                       CONVERT(fc.nro_comprobante USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       fc.total_con_iva AS total,
                       COALESCE((
                           SELECT SUM(pf.monto_aplicado)
                           FROM pagos_facturas pf
                           JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo'
                           WHERE pf.factura_id = fc.id
                       ), 0) AS pagado
                FROM facturas_compra fc
                JOIN proveedores p ON p.id = fc.proveedor_id
                WHERE fc.estado = 'pagada' AND fc.eliminado = 0

                UNION ALL

                SELECT p.id, p.razon_social, 'gasto',
                       g.id, g.fecha,
                       CONVERT(g.descripcion USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       g.monto AS total,
                       COALESCE((
                           SELECT SUM(pg.monto_aplicado)
                           FROM pagos_gastos pg
                           JOIN pagos_proveedores pp ON pp.id = pg.pago_id AND pp.estado = 'activo'
                           WHERE pg.gasto_id = g.id
                       ), 0) AS pagado
                FROM gastos g
                JOIN proveedores p ON p.id = g.proveedor_id
                WHERE g.estado = 'pagado' AND g.eliminado = 0
             ) x
             WHERE (total - pagado) > 0.009
             ORDER BY proveedor ASC, fecha ASC"
        );
    }

    /**
     * Total pagado en un período — CUALQUIER tipo (proveedor/sueldo/otro), plata que
     * efectivamente salió de caja. A diferencia de "compras" (facturas_compra, lo
     * facturado/adeudado), esto es el cash real — usado por el resumen semanal cron.
     */
    public function totalPagadoEnPeriodo(string $desde, string $hasta): array
    {
        return $this->queryOne(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto_total), 0) AS total
             FROM pagos_proveedores
             WHERE eliminado = 0 AND estado = 'activo'
               AND fecha BETWEEN :desde AND :hasta",
            [':desde' => $desde, ':hasta' => $hasta]
        ) ?: ['cantidad' => 0, 'total' => 0.0];
    }

    /** Total pagado en concepto de sueldos (Empleados/Nómina) en un período, para el Resumen Económico */
    public function totalSueldosEnPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM pagos_proveedores
             WHERE tipo = 'sueldo' AND estado = 'activo' AND eliminado = 0
               AND fecha BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    /** Sueldos pagados en el período, agrupados por empleado — para el desglegable de "Sueldos" en el Resumen Económico */
    public function sueldosPorEmpleadoEnPeriodo(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT e.id AS empleado_id, e.nombre, COALESCE(SUM(pp.monto_total), 0) AS total
             FROM pagos_proveedores pp
             JOIN empleados e ON e.id = pp.empleado_id
             WHERE pp.tipo = 'sueldo' AND pp.estado = 'activo' AND pp.eliminado = 0
               AND pp.fecha BETWEEN :d AND :h
             GROUP BY e.id, e.nombre
             HAVING total > 0.009
             ORDER BY total DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    /** Aportes de socios (pagos financiados con fondos propios de un socio) agrupados por socio, en un período */
    public function aportesSociosEnPeriodo(string $desde, string $hasta): array
    {
        return $this->queryAll(
            "SELECT COALESCE(s.nombre, NULLIF(TRIM(pp.nombre_socio), ''), 'Sin especificar') AS socio,
                    SUM(pp.monto_total) AS total
             FROM pagos_proveedores pp
             LEFT JOIN socios s ON s.id = pp.socio_id
             WHERE pp.origen_pago = 'socio' AND pp.estado = 'activo' AND pp.eliminado = 0
               AND pp.fecha BETWEEN :d AND :h
             GROUP BY socio
             ORDER BY total DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    /**
     * Busca un pago ya cargado que coincida con el que se está por registrar, para
     * avisar de un posible duplicado antes de guardar (Chat IA). Misma lógica de
     * prioridad que GastoModel::buscarDuplicado(): numero_comprobante exacto primero
     * (dato fuerte), y si no hay, proveedor/empleado + monto exacto + misma fecha (sin
     * ventana de días, para no marcar como "duplicado" un sueldo o pago recurrente
     * legítimo del mes siguiente). Sin proveedor_id ni empleado_id no hay ancla
     * suficiente para un match difuso seguro.
     */
    public function buscarDuplicado(?int $proveedorId, ?int $empleadoId, float $monto, string $fecha, ?string $numeroComprobante): array|false
    {
        if ($numeroComprobante) {
            $row = $this->queryOne(
                "SELECT pp.id, pp.fecha, pp.concepto, pp.monto_total, u.nombre AS usuario_nombre
                 FROM pagos_proveedores pp
                 LEFT JOIN usuarios u ON u.id = pp.usuario_id
                 WHERE pp.numero_comprobante = :nro AND pp.estado = 'activo' AND pp.eliminado = 0
                 ORDER BY pp.id DESC LIMIT 1",
                [':nro' => $numeroComprobante]
            );
            if ($row) return $row;
        }

        if (!$proveedorId && !$empleadoId) return false;

        $where  = ["pp.estado = 'activo'", 'pp.eliminado = 0', 'pp.monto_total = :monto', 'pp.fecha = :fecha'];
        $params = [':monto' => $monto, ':fecha' => $fecha];
        if ($proveedorId) {
            $where[]         = 'pp.proveedor_id = :pid';
            $params[':pid']  = $proveedorId;
        } else {
            $where[]         = 'pp.empleado_id = :eid';
            $params[':eid']  = $empleadoId;
        }

        return $this->queryOne(
            "SELECT pp.id, pp.fecha, pp.concepto, pp.monto_total, u.nombre AS usuario_nombre
             FROM pagos_proveedores pp
             LEFT JOIN usuarios u ON u.id = pp.usuario_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pp.id DESC LIMIT 1",
            $params
        );
    }

    public function getFacturasPendientesByProveedor(int $proveedorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT fc.id, fc.fecha, fc.nro_comprobante, fc.tipo_comprobante, fc.total_con_iva,
                    COALESCE((
                        SELECT SUM(pf.monto_aplicado)
                        FROM pagos_facturas pf
                        JOIN pagos_proveedores pp ON pp.id = pf.pago_id
                        WHERE pf.factura_id = fc.id AND pp.estado = 'activo'
                    ), 0) AS pagado,
                    fc.total_con_iva - COALESCE((
                        SELECT SUM(pf.monto_aplicado)
                        FROM pagos_facturas pf
                        JOIN pagos_proveedores pp ON pp.id = pf.pago_id
                        WHERE pf.factura_id = fc.id AND pp.estado = 'activo'
                    ), 0) AS saldo
             FROM facturas_compra fc
             WHERE fc.proveedor_id = ? AND fc.estado = 'pendiente' AND fc.eliminado = 0
             ORDER BY fc.fecha ASC"
        );
        $stmt->execute([$proveedorId]);
        return $stmt->fetchAll();
    }

    /**
     * Extracto de cuenta corriente de UN proveedor puntual: sus facturas y gastos
     * PENDIENTES (Debe) más las aplicaciones de pago hechas puntualmente contra cada uno
     * de ellos (Haber, vía pagos_facturas/pagos_gastos), ordenados por fecha, con el
     * saldo corrido calculado en PHP fila a fila (mismo criterio que el resto del
     * modelo: todo cálculo de saldo se hace al vuelo, nunca se persiste una columna de
     * saldo).
     *
     * A propósito NO se arma como "todo el historial completo" (todas las facturas
     * alguna vez cargadas, estén pagadas o no, más todos los pagos del proveedor): se
     * comprobó contra datos reales que facturas_compra.estado='pagada' no siempre tiene
     * un pago real cargado en pagos_proveedores detrás — ComprasController::guardar()/
     * importar() graban TODA factura nueva como 'pagada' sin importar condicion_pago
     * (se paga en el momento, fuera del módulo de Pagos), y es el Chat IA
     * (ChatController, registro de compra por foto) el que las crea 'pendiente' para
     * que luego se salden acá. Mezclar "todas las facturas" como Debe con "todos los
     * pagos" como Haber inflaba el Debe con facturas que nunca iban a tener un pago
     * asociado, dando un saldo corrido que NO coincidía con getCuentaCorriente(). Al
     * restringir Debe a solo lo pendiente y Haber a los pagos efectivamente aplicados a
     * eso pendiente (misma fuente que getFacturasPendientesByProveedor/
     * getGastosPendientesByProveedor), el saldo final de este extracto coincide by
     * construction con el que ya muestra la tabla "Saldos pendientes de pago".
     */
    public function getExtracto(int $proveedorId, bool $incluirHistorico = false): array
    {
        // Histórico: en vez de solo lo que sigue "pendiente", trae también facturas/gastos
        // ya pagados (y los pagos que los cubrieron) — se excluyen igual las facturas
        // anuladas porque nunca representaron una deuda real. Sin histórico, el saldo del
        // extracto coincide con "Saldos pendientes de pago"; con histórico, el saldo final
        // es el mismo (los ítems saldados se cancelan Debe/Haber), pero se ve el camino completo.
        $condFactura = $incluirHistorico ? "fc.estado != 'anulada'" : "fc.estado = 'pendiente'";
        $condGasto   = $incluirHistorico ? "1=1" : "g.estado = 'pendiente'";
        // COLLATE explícito en referencia: facturas_compra/gastos usan
        // utf8mb4_uca1400_ai_ci y pagos_proveedores usa utf8mb4_unicode_ci (mismo tipo de
        // desajuste que soluciona socios_migration.sql para otra tabla) — sin normalizar,
        // el UNION ALL tira "Illegal mix of collations".
        // origen/medio_pago: NULL para Debe (facturas/gastos, no aplica "cómo se pagó" a
        // una deuda todavía no saldada) y calculado para Haber (pagos) — origen distingue
        // "Empresa" de "Socio: <nombre>" (pp.origen_pago/nombre_socio, ver
        // PagosProveedoresController::guardar()), medio_pago concatena los medios con
        // monto > 0 de ESE pago (puede ser más de uno si fue un pago combinado).
        $medioPagoSql = "NULLIF(CONCAT_WS(' + ',
                CASE WHEN pp.monto_efectivo > 0 THEN 'Efectivo' END,
                CASE WHEN pp.monto_debito > 0 THEN 'Débito' END,
                CASE WHEN pp.monto_transferencia > 0 THEN 'Transferencia' END,
                CASE WHEN pp.monto_mercado_pago > 0 THEN 'Mercado Pago' END,
                CASE WHEN pp.monto_cheque > 0 THEN 'Cheque' END,
                CASE WHEN pp.monto_otro > 0 THEN 'Otro' END
            ), '')";
        $origenSql = "CASE WHEN pp.origen_pago = 'socio'
                THEN CONCAT('Socio: ', COALESCE(NULLIF(pp.nombre_socio, ''), '(sin nombre)'))
                ELSE 'Empresa'
            END";

        $movimientos = $this->queryAll(
            "SELECT fecha, 'factura' AS tipo, id, referencia, debe, haber, origen, medio_pago,
                    comprobante_tipo, comprobante_id, comprobante_ref FROM (
                SELECT fc.fecha, fc.id,
                       CONVERT(fc.nro_comprobante USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       fc.total_con_iva AS debe, 0 AS haber,
                       NULL AS origen, NULL AS medio_pago,
                       NULL AS comprobante_tipo, NULL AS comprobante_id, NULL AS comprobante_ref
                FROM facturas_compra fc
                WHERE fc.proveedor_id = :pid1 AND {$condFactura} AND fc.eliminado = 0
             ) a

             UNION ALL

             SELECT fecha, 'gasto' AS tipo, id, referencia, debe, haber, origen, medio_pago,
                    comprobante_tipo, comprobante_id, comprobante_ref FROM (
                SELECT g.fecha, g.id,
                       CONVERT(g.descripcion USING utf8mb4) COLLATE utf8mb4_general_ci AS referencia,
                       g.monto AS debe, 0 AS haber,
                       NULL AS origen, NULL AS medio_pago,
                       NULL AS comprobante_tipo, NULL AS comprobante_id, NULL AS comprobante_ref
                FROM gastos g
                WHERE g.proveedor_id = :pid2 AND {$condGasto} AND g.eliminado = 0
             ) b

             UNION ALL

             SELECT fecha, 'pago' AS tipo, id, referencia, debe, haber, origen, medio_pago,
                    comprobante_tipo, comprobante_id, comprobante_ref FROM (
                SELECT pp.fecha, pp.id,
                       CONCAT('Pago aplicado a factura ',
                              CONVERT(COALESCE(NULLIF(fc.nro_comprobante, ''), CONCAT('#', fc.id)) USING utf8mb4) COLLATE utf8mb4_general_ci
                       ) AS referencia,
                       0 AS debe, pf.monto_aplicado AS haber,
                       {$origenSql} AS origen, {$medioPagoSql} AS medio_pago,
                       'factura' AS comprobante_tipo, fc.id AS comprobante_id,
                       CONVERT(COALESCE(NULLIF(fc.nro_comprobante, ''), CONCAT('#', fc.id)) USING utf8mb4) COLLATE utf8mb4_general_ci AS comprobante_ref
                FROM pagos_facturas pf
                JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo' AND pp.eliminado = 0
                JOIN facturas_compra   fc ON fc.id = pf.factura_id
                WHERE fc.proveedor_id = :pid3 AND {$condFactura} AND fc.eliminado = 0
             ) c

             UNION ALL

             SELECT fecha, 'pago' AS tipo, id, referencia, debe, haber, origen, medio_pago,
                    comprobante_tipo, comprobante_id, comprobante_ref FROM (
                SELECT pp.fecha, pp.id,
                       CONCAT('Pago aplicado a gasto: ', CONVERT(g.descripcion USING utf8mb4) COLLATE utf8mb4_general_ci) AS referencia,
                       0 AS debe, pg.monto_aplicado AS haber,
                       {$origenSql} AS origen, {$medioPagoSql} AS medio_pago,
                       'gasto' AS comprobante_tipo, g.id AS comprobante_id,
                       CONVERT(g.descripcion USING utf8mb4) COLLATE utf8mb4_general_ci AS comprobante_ref
                FROM pagos_gastos pg
                JOIN pagos_proveedores pp ON pp.id = pg.pago_id AND pp.estado = 'activo' AND pp.eliminado = 0
                JOIN gastos            g  ON g.id = pg.gasto_id
                WHERE g.proveedor_id = :pid4 AND {$condGasto} AND g.eliminado = 0
             ) d

             ORDER BY fecha ASC, (tipo = 'pago') ASC, id ASC",
            [':pid1' => $proveedorId, ':pid2' => $proveedorId, ':pid3' => $proveedorId, ':pid4' => $proveedorId]
        );

        // Grupo de "color" para pintar Debe/Haber emparejados: cada factura/gasto (sea Debe
        // en sí mismo, o el comprobante al que apunta un Haber) es un grupo — para el Debe,
        // el grupo es el propio tipo/id de la fila; para el Haber, es el comprobante_tipo/
        // comprobante_id al que se aplicó el pago. Se arma acá (no en la vista) porque acá
        // es donde se sabe si una fila es 'factura'/'gasto' (grupo = ella misma) o 'pago'
        // (grupo = su comprobante_tipo/comprobante_id).
        foreach ($movimientos as &$m) {
            $m['grupo_color'] = $m['tipo'] === 'pago'
                ? ($m['comprobante_tipo'] ? "{$m['comprobante_tipo']}:{$m['comprobante_id']}" : null)
                : "{$m['tipo']}:{$m['id']}";
        }
        unset($m);

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
