<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/CobrosLectores/LectorCobro.php';

/**
 * Notificaciones que reenvían los celulares de Dewoc Cobros y lo que decidió el lector de
 * cada una (ver docs/dewoc-cobros-checklist.md, tareas 1.1 a 1.3).
 *
 * También la VALIDACIÓN de ventas: un cobro recibido "valida" la venta que se registró con
 * Transferencia o Mercado Pago por el mismo monto, cerca en el tiempo. Sirve para el
 * listado de ventas (qué ventas por transferencia tienen un cobro real que las respalde) y
 * para la conciliación de caja. Un cobro NO es un movimiento de caja (C8): solo se asocia.
 *
 * Las fechas se escriben desde PHP y no con NOW(): el MySQL de Hostinger está en UTC.
 */
class CobroRecibidoModel extends Model
{
    protected string $table = 'cobros_recibidos';

    /** Paquetes de la propia app: la notificación de prueba (sección 3.2 del plan). */
    const PAQUETES_PRUEBA = ['com.dewoc.cobros', 'com.dewoc.cobros.prueba'];

    /** Cuánto antes o después de la venta puede haber llegado el cobro que la valida. */
    const VENTANA_CONCILIACION_MINUTOS = 30;

    /** El POS solo avisa los cobros de las últimas horas (un "volver a leer" viejo no avisa). */
    const HORAS_AVISO_POS = 12;

    public function porUuid(string $uuid): ?array
    {
        return $this->queryOne("SELECT * FROM cobros_recibidos WHERE uuid = :u", [':u' => $uuid]) ?: null;
    }

    /**
     * Pasa la notificación por el lector que corresponde según su paquete y devuelve
     * ['resultado', 'lector', 'monto', 'nombre']. $paquete: fila de cobros_paquetes o null.
     */
    public static function leer(string $paquete, ?array $filaPaquete, ?string $titulo, ?string $texto, ?string $textoLargo): array
    {
        if (in_array($paquete, self::PAQUETES_PRUEBA, true)) {
            return ['resultado' => 'prueba', 'lector' => null, 'monto' => null, 'nombre' => null];
        }

        // Un paquete que ya no está en la lista se guarda igual como sin_lector: pudo haber
        // llegado de la cola del celular, con la lista vieja (sección 3.2).
        $clave  = ($filaPaquete && (int)$filaPaquete['activo']) ? $filaPaquete['lector'] : null;
        $lector = LectorCobro::para($clave);
        if (!$lector) return ['resultado' => 'sin_lector', 'lector' => null, 'monto' => null, 'nombre' => null];

        return $lector->leer($titulo, $texto, $textoLargo) + ['lector' => $clave];
    }

    /**
     * Guarda una notificación ya leída. Devuelve el id nuevo, o 0 si ese uuid ya existía
     * (dos envíos simultáneos de la misma: el UNIQUE deja pasar uno solo).
     */
    public function registrar(array $n): int
    {
        $turno = $this->queryScalar(
            "SELECT id FROM turnos WHERE estado = 'abierto' AND eliminado = 0 ORDER BY apertura_at LIMIT 1"
        );
        $ahora = date('Y-m-d H:i:s');

        $filas = $this->query(
            "INSERT IGNORE INTO cobros_recibidos
                (uuid, dispositivo_id, paquete, titulo, texto, texto_largo, recibido_at,
                 resultado, lector, monto, nombre, leido_at, turno_id, created_at)
             VALUES (:uuid, :disp, :paq, :tit, :txt, :txl, :rec, :res, :lec, :monto, :nom, :leido, :turno, :creado)",
            [
                ':uuid'  => $n['uuid'],
                ':disp'  => $n['dispositivo_id'],
                ':paq'   => $n['paquete'],
                ':tit'   => $n['titulo'],
                ':txt'   => $n['texto'],
                ':txl'   => $n['texto_largo'],
                ':rec'   => $n['recibido_at'],
                ':res'   => $n['resultado'],
                ':lec'   => $n['lector'],
                ':monto' => $n['monto'],
                ':nom'   => $n['nombre'],
                ':leido' => $n['resultado'] === 'prueba' ? null : $ahora,
                ':turno' => $turno ?: null,
                ':creado'=> $ahora,
            ]
        )->rowCount();

        return $filas ? (int)$this->db->lastInsertId() : 0;
    }

    // ── POS ──────────────────────────────────────────────────────────────────

    /**
     * Cobros para el aviso del POS con id mayor a $desdeId. Con $desdeId = 0 (el POS se
     * acaba de abrir) solo los de los últimos 2 minutos, para no mostrar de golpe los de
     * todo el día. Devuelve ['cobros' => [...], 'ultimo_id' => int].
     */
    public function nuevosParaPos(int $desdeId): array
    {
        $ultimoId = (int)$this->queryScalar("SELECT COALESCE(MAX(id), 0) FROM cobros_recibidos");
        $desde    = $desdeId > 0
            ? date('Y-m-d H:i:s', strtotime('-' . self::HORAS_AVISO_POS . ' hours'))
            : date('Y-m-d H:i:s', strtotime('-2 minutes'));

        $cobros = $this->queryAll(
            "SELECT c.id, c.monto, c.nombre, c.recibido_at, COALESCE(p.nombre_app, c.paquete) AS app
               FROM cobros_recibidos c
               LEFT JOIN cobros_paquetes p ON p.paquete = c.paquete
              WHERE c.resultado = 'cobro' AND c.id > :id AND c.created_at >= :desde
              ORDER BY c.id
              LIMIT 10",
            [':id' => $desdeId, ':desde' => $desde]
        );
        return ['cobros' => $cobros, 'ultimo_id' => $ultimoId];
    }

    // ── Integraciones ────────────────────────────────────────────────────────

    /**
     * Últimas notificaciones, para depurar los lectores (1.6) y para la conciliación.
     * $resultado: '' = todas, 'sin_venta' = cobros que no validan ninguna venta, o un
     * resultado de la tabla.
     */
    public function ultimas(string $resultado = '', int $limite = 100): array
    {
        $where = '';
        $params = [];
        if ($resultado === 'sin_venta') {
            $where = "WHERE c.resultado = 'cobro' AND c.venta_id IS NULL AND c.ignorado_at IS NULL";
        } elseif ($resultado !== '') {
            $where  = 'WHERE c.resultado = :r';
            $params = [':r' => $resultado];
        }
        return $this->queryAll(
            "SELECT c.*, COALESCE(p.nombre_app, c.paquete) AS app, d.nombre AS dispositivo
               FROM cobros_recibidos c
               LEFT JOIN cobros_paquetes p ON p.paquete = c.paquete
               LEFT JOIN cobros_dispositivos d ON d.id = c.dispositivo_id
               {$where}
              ORDER BY c.id DESC LIMIT " . max(1, min(500, $limite)),
            $params
        );
    }

    /**
     * Vuelve a pasar por el lector las no_reconocido y sin_lector de los últimos $dias días
     * (C14), después de corregir un lector o de asignarle uno a un paquete. Devuelve cuántas
     * cambiaron de resultado. Las que pasan a cobro se intentan asociar a una venta.
     */
    public function volverALeer(int $dias = 7): int
    {
        $filas = $this->queryAll(
            "SELECT c.*, p.id AS paq_id, p.lector AS paq_lector, p.activo AS paq_activo
               FROM cobros_recibidos c
               LEFT JOIN cobros_paquetes p ON p.paquete = c.paquete
              WHERE c.resultado IN ('no_reconocido', 'sin_lector') AND c.created_at >= :d",
            [':d' => date('Y-m-d H:i:s', strtotime("-{$dias} days"))]
        );

        $cambiaron = 0;
        foreach ($filas as $f) {
            $paquete = $f['paq_id'] ? ['lector' => $f['paq_lector'], 'activo' => $f['paq_activo']] : null;
            $r = self::leer($f['paquete'], $paquete, $f['titulo'], $f['texto'], $f['texto_largo']);

            $this->query(
                "UPDATE cobros_recibidos SET resultado = :res, lector = :lec, monto = :m, nombre = :n, leido_at = :ahora
                  WHERE id = :id",
                [':res' => $r['resultado'], ':lec' => $r['lector'], ':m' => $r['monto'], ':n' => $r['nombre'],
                 ':ahora' => date('Y-m-d H:i:s'), ':id' => (int)$f['id']]
            );
            if ($r['resultado'] !== $f['resultado']) $cambiaron++;
            if ($r['resultado'] === 'cobro') $this->conciliarCobro((int)$f['id']);
        }
        return $cambiaron;
    }

    // ── Validación de ventas (conciliación) ─────────────────────────────────

    /**
     * Asocia un cobro recién llegado con la venta que valida: completada, sin otro cobro
     * asociado, cuyo monto en Transferencia + Mercado Pago es igual al del cobro, registrada
     * dentro de la ventana. Si hay varias, la más cercana en el tiempo. Devuelve el id de la
     * venta, o null.
     */
    public function conciliarCobro(int $cobroId): ?int
    {
        $c = $this->queryOne(
            "SELECT id, monto, recibido_at FROM cobros_recibidos
              WHERE id = :id AND resultado = 'cobro' AND venta_id IS NULL AND ignorado_at IS NULL AND monto IS NOT NULL",
            [':id' => $cobroId]
        );
        if (!$c) return null;

        [$desde, $hasta] = $this->ventana((string)$c['recibido_at']);
        $ventaId = $this->queryScalar(
            "SELECT v.id FROM ventas v
              WHERE v.eliminado = 0 AND v.estado = 'completada'
                AND ROUND(v.monto_transferencia + v.monto_mercadopago, 2) = :m
                AND v.created_at BETWEEN :desde AND :hasta
                AND NOT EXISTS (SELECT 1 FROM cobros_recibidos x WHERE x.venta_id = v.id)
              ORDER BY ABS(TIMESTAMPDIFF(SECOND, v.created_at, :t)), v.id
              LIMIT 1",
            [':m' => sprintf('%.2f', $c['monto']), ':desde' => $desde, ':hasta' => $hasta, ':t' => $c['recibido_at']]
        );
        return $ventaId && $this->vincular($cobroId, (int)$ventaId) ? (int)$ventaId : null;
    }

    /**
     * Recalcula la validación de una venta: se registró, se anuló o se le cambió el medio de
     * pago. Suelta el cobro que tuviera (que queda libre para validar otra venta) y busca de
     * nuevo. Devuelve el id del cobro asociado, o null.
     *
     * Una asignación hecha A MANO se respeta mientras la venta siga completada: la decidió una
     * persona que sabía algo que la regla exacta no (propina, pago una hora después…).
     */
    public function conciliarVenta(int $ventaId): ?int
    {
        $v = $this->queryOne(
            "SELECT id, created_at, ROUND(monto_transferencia + monto_mercadopago, 2) AS electronico
               FROM ventas WHERE id = :id AND eliminado = 0 AND estado = 'completada'",
            [':id' => $ventaId]
        );

        $ant = $this->queryOne("SELECT id, vinculado_por FROM cobros_recibidos WHERE venta_id = :v", [':v' => $ventaId]);
        if ($ant && $ant['vinculado_por'] !== null && $v) return (int)$ant['id'];

        $anterior = $ant ? (int)$ant['id'] : null;
        if ($anterior) $this->soltar($anterior);

        $cobroId = null;
        if ($v && (float)$v['electronico'] > 0) {
            [$desde, $hasta] = $this->ventana((string)$v['created_at']);
            $cobroId = $this->queryScalar(
                "SELECT id FROM cobros_recibidos
                  WHERE resultado = 'cobro' AND venta_id IS NULL AND ignorado_at IS NULL AND monto = :m
                    AND recibido_at BETWEEN :desde AND :hasta
                  ORDER BY ABS(TIMESTAMPDIFF(SECOND, recibido_at, :t)), id
                  LIMIT 1",
                [':m' => sprintf('%.2f', $v['electronico']), ':desde' => $desde, ':hasta' => $hasta, ':t' => $v['created_at']]
            ) ?: null;
            if ($cobroId && !$this->vincular((int)$cobroId, $ventaId)) $cobroId = null;
        }

        // El cobro que se soltó puede validar otra venta (ej. se anuló una venta duplicada).
        if ($anterior && $anterior !== (int)$cobroId) $this->conciliarCobro($anterior);

        return $cobroId ? (int)$cobroId : null;
    }

    /**
     * Cobros del período que no validan ninguna venta, para la conciliación de caja: plata
     * que entró por transferencia y no se registró como tal (o no se registró).
     * Devuelve ['cantidad' => int, 'total' => float, 'sin_monto' => int].
     */
    public function sinVenta(string $desde, string $hasta): array
    {
        $r = $this->queryOne(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total, SUM(monto IS NULL) AS sin_monto
               FROM cobros_recibidos
              WHERE resultado = 'cobro' AND venta_id IS NULL AND ignorado_at IS NULL
                AND DATE(recibido_at) BETWEEN :d AND :h",
            [':d' => $desde, ':h' => $hasta]
        ) ?: [];
        return ['cantidad' => (int)($r['cantidad'] ?? 0), 'total' => (float)($r['total'] ?? 0), 'sin_monto' => (int)($r['sin_monto'] ?? 0)];
    }

    // ── Asignación manual (pop-up del POS y Ventas → Transferencias recibidas) ──

    /**
     * Transferencias pendientes de asignar: cobros sin venta y sin marcar "No corresponde",
     * recibidos desde $desde (en el POS, la apertura del turno: al cerrar la caja, el cajero
     * del turno siguiente ya no las ve).
     */
    public function pendientesDesde(string $desde, int $limite = 50): array
    {
        return $this->queryAll(
            "SELECT c.id, c.monto, c.nombre, c.recibido_at, COALESCE(p.nombre_app, c.paquete) AS app
               FROM cobros_recibidos c
               LEFT JOIN cobros_paquetes p ON p.paquete = c.paquete
              WHERE c.resultado = 'cobro' AND c.venta_id IS NULL AND c.ignorado_at IS NULL
                AND c.recibido_at >= :desde
              ORDER BY c.recibido_at
              LIMIT " . max(1, min(200, $limite)),
            [':desde' => $desde]
        );
    }

    public function porId(int $id): ?array
    {
        return $this->queryOne("SELECT * FROM cobros_recibidos WHERE id = :id", [':id' => $id]) ?: null;
    }

    /**
     * Ventas a las que se puede asignar una transferencia: completadas y sin otra
     * transferencia asignada. Con $turnoId (pop-up del POS), solo las de ese turno; si no,
     * las de las 12 horas alrededor de la transferencia. Las más parecidas primero: por la
     * diferencia de monto y después por cercanía en el tiempo.
     */
    public function ventasCandidatas(array $cobro, ?int $turnoId = null, int $limite = 40): array
    {
        $params = [':m' => sprintf('%.2f', (float)$cobro['monto']), ':t' => $cobro['recibido_at']];
        if ($turnoId) {
            $cond = 'v.turno_id = :turno';
            $params[':turno'] = $turnoId;
        } else {
            $cond = 'v.created_at BETWEEN :desde AND :hasta';
            $ts = strtotime((string)$cobro['recibido_at']);
            $params[':desde'] = date('Y-m-d H:i:s', $ts - 12 * 3600);
            $params[':hasta'] = date('Y-m-d H:i:s', $ts + 12 * 3600);
        }

        return $this->queryAll(
            "SELECT v.id, v.created_at, v.total_con_iva, v.metodo_pago, v.mesa,
                    ROUND(v.monto_transferencia + v.monto_mercadopago, 2) AS electronico,
                    u.nombre AS usuario_nombre
               FROM ventas v
               LEFT JOIN usuarios u ON u.id = v.usuario_id
              WHERE v.eliminado = 0 AND v.estado = 'completada' AND {$cond}
                AND NOT EXISTS (SELECT 1 FROM cobros_recibidos x WHERE x.venta_id = v.id)
              ORDER BY ABS(v.total_con_iva - :m), ABS(TIMESTAMPDIFF(SECOND, v.created_at, :t)), v.id DESC
              LIMIT " . max(1, min(200, $limite)),
            $params
        );
    }

    /**
     * Asigna a mano una transferencia a una venta. Devuelve null si salió bien, o el motivo
     * por el que no se pudo (para mostrárselo a quien lo intentó).
     */
    public function asignar(int $cobroId, int $ventaId, int $usuarioId): ?string
    {
        $c = $this->porId($cobroId);
        if (!$c || $c['resultado'] !== 'cobro') return 'Esa transferencia no existe.';
        if ($c['venta_id'])                    return 'Esa transferencia ya está asignada a la venta #' . (int)$c['venta_id'] . '.';
        if ($c['ignorado_at'])                 return 'Esa transferencia está marcada como "No corresponde a una venta".';

        $v = $this->queryOne("SELECT id, estado, eliminado FROM ventas WHERE id = :id", [':id' => $ventaId]);
        if (!$v || (int)$v['eliminado'] || $v['estado'] !== 'completada') return 'La venta no existe o está anulada.';
        if ($this->queryScalar("SELECT id FROM cobros_recibidos WHERE venta_id = :v", [':v' => $ventaId])) {
            return 'Esa venta ya tiene otra transferencia asignada.';
        }

        return $this->vincular($cobroId, $ventaId, $usuarioId) ? null : 'Alguien la asignó recién. Actualizá la lista.';
    }

    /** Quita la asignación (manual o automática): la transferencia vuelve a quedar pendiente. */
    public function desasignar(int $cobroId): bool
    {
        return $this->soltar($cobroId);
    }

    /** "No corresponde a una venta" (una seña, una devolución, una transferencia personal…). */
    public function marcarNoCorresponde(int $cobroId, int $usuarioId, string $motivo): bool
    {
        return $this->query(
            "UPDATE cobros_recibidos SET ignorado_at = :ahora, ignorado_por = :u, ignorado_motivo = :mot
              WHERE id = :id AND resultado = 'cobro' AND venta_id IS NULL AND ignorado_at IS NULL",
            [':ahora' => date('Y-m-d H:i:s'), ':u' => $usuarioId, ':mot' => mb_substr($motivo, 0, 200), ':id' => $cobroId]
        )->rowCount() > 0;
    }

    /** Deshace el "No corresponde": la transferencia vuelve a quedar pendiente. */
    public function quitarNoCorresponde(int $cobroId): bool
    {
        return $this->query(
            "UPDATE cobros_recibidos SET ignorado_at = NULL, ignorado_por = NULL, ignorado_motivo = NULL
              WHERE id = :id AND ignorado_at IS NOT NULL",
            [':id' => $cobroId]
        )->rowCount() > 0;
    }

    /**
     * Ventas → Transferencias recibidas. $estado: pendientes | asignadas | no_corresponde | ''
     * (todas). Con la venta asignada y quién la asignó.
     */
    public function listarTransferencias(string $desde, string $hasta, string $estado = ''): array
    {
        $cond = match ($estado) {
            'pendientes'     => 'AND c.venta_id IS NULL AND c.ignorado_at IS NULL',
            'asignadas'      => 'AND c.venta_id IS NOT NULL',
            'no_corresponde' => 'AND c.ignorado_at IS NOT NULL',
            default          => '',
        };
        return $this->queryAll(
            "SELECT c.id, c.monto, c.nombre, c.recibido_at, c.venta_id, c.vinculado_por, c.vinculado_at,
                    c.ignorado_at, c.ignorado_motivo, c.titulo, c.texto, c.texto_largo,
                    COALESCE(p.nombre_app, c.paquete) AS app,
                    v.total_con_iva AS venta_total, v.metodo_pago AS venta_metodo, v.mesa AS venta_mesa,
                    v.created_at AS venta_created_at,
                    uv.nombre AS vinculado_por_nombre, ui.nombre AS ignorado_por_nombre
               FROM cobros_recibidos c
               LEFT JOIN cobros_paquetes p ON p.paquete = c.paquete
               LEFT JOIN ventas v   ON v.id = c.venta_id
               LEFT JOIN usuarios uv ON uv.id = c.vinculado_por
               LEFT JOIN usuarios ui ON ui.id = c.ignorado_por
              WHERE c.resultado = 'cobro' AND DATE(c.recibido_at) BETWEEN :d AND :h {$cond}
              ORDER BY c.recibido_at DESC",
            [':d' => $desde, ':h' => $hasta]
        );
    }

    /** Suelta la venta de un cobro, sea asignación manual o automática. */
    private function soltar(int $cobroId): bool
    {
        return $this->query(
            "UPDATE cobros_recibidos SET venta_id = NULL, vinculado_por = NULL, vinculado_at = NULL
              WHERE id = :id AND venta_id IS NOT NULL",
            [':id' => $cobroId]
        )->rowCount() > 0;
    }

    /** Desde/hasta de la ventana de conciliación alrededor de una fecha. */
    private function ventana(string $fecha): array
    {
        $ts = strtotime($fecha);
        $m  = self::VENTANA_CONCILIACION_MINUTOS * 60;
        return [date('Y-m-d H:i:s', $ts - $m), date('Y-m-d H:i:s', $ts + $m)];
    }

    /**
     * UNIQUE(venta_id): si otra request asoció esa venta a la vez, esta no la pisa.
     * $usuarioId null = asignación automática exacta.
     */
    private function vincular(int $cobroId, int $ventaId, ?int $usuarioId = null): bool
    {
        try {
            return $this->query(
                "UPDATE cobros_recibidos SET venta_id = :v, vinculado_por = :u, vinculado_at = :ahora
                  WHERE id = :c AND venta_id IS NULL AND ignorado_at IS NULL",
                [':v' => $ventaId, ':u' => $usuarioId, ':ahora' => date('Y-m-d H:i:s'), ':c' => $cobroId]
            )->rowCount() > 0;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') return false; // la venta ya tiene otro cobro
            throw $e;
        }
    }
}
