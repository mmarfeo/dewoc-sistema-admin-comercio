<?php
require_once BASE_PATH . '/app/Models/Model.php';

class EmpleadoModel extends Model
{
    protected string $table = 'empleados';

    public function findAllActivos(): array
    {
        // OJO: antes esta query era idéntica a findAll() (copiada sin el filtro) — un
        // empleado desactivado (activo=0, ej. ya no trabaja más) seguía apareciendo en
        // los desplegables de "Nuevo pago"/plantillas recurrentes y en el total agregado
        // de sueldos pendientes de liquidar (getTotalPendienteLiquidarTodos), inflándolo
        // con deuda de gente que ya no está.
        return $this->queryAll(
            'SELECT * FROM empleados WHERE eliminado = 0 AND activo = 1 ORDER BY nombre'
        );
    }

    public function findAll(string $orderBy = ''): array
    {
        return $this->queryAll(
            'SELECT * FROM empleados WHERE eliminado = 0 ORDER BY nombre'
        );
    }

    public function tienePagos(int $id): bool
    {
        return (bool)$this->queryScalar(
            'SELECT COUNT(*) FROM pagos_proveedores WHERE empleado_id = ?',
            [$id]
        );
    }

    /** Historial de pagos (sueldos) registrados a este empleado */
    public function getPagos(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT pp.*, u.nombre AS usuario_nombre
             FROM pagos_proveedores pp
             LEFT JOIN usuarios u ON u.id = pp.usuario_id
             WHERE pp.empleado_id = :id AND pp.eliminado = 0
             ORDER BY pp.fecha DESC, pp.id DESC",
            [':id' => $empleadoId]
        );
    }

    public function totalPagadoEnAnio(int $empleadoId, int $anio): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM pagos_proveedores
             WHERE empleado_id = :id AND estado = 'activo' AND eliminado = 0 AND YEAR(fecha) = :anio",
            [':id' => $empleadoId, ':anio' => $anio]
        );
    }

    public function totalPagadoEnPeriodo(int $empleadoId, string $desde, string $hasta): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto_total), 0)
             FROM pagos_proveedores
             WHERE empleado_id = :id AND estado = 'activo' AND eliminado = 0 AND fecha BETWEEN :desde AND :hasta",
            [':id' => $empleadoId, ':desde' => $desde, ':hasta' => $hasta]
        );
    }

    // ── Jornadas (planilla de asistencia) ───────────────────────────────────

    /**
     * Un empleado tiene sueldo fijo mensual cuando su modalidad es mensual/quincenal,
     * o cuando es por_hora pero cargó una cantidad de horas mensuales estimadas
     * (en ese caso se paga horas_mensuales × valor_hora, igual que un mensual).
     */
    private function esSueldoFijo(array $empleado): bool
    {
        return in_array($empleado['modalidad_pago'], ['mensual', 'quincenal'], true)
            || ($empleado['modalidad_pago'] === 'por_hora' && (float)($empleado['horas_mensuales'] ?? 0) > 0);
    }

    /** Base mensual de un sueldo fijo (ver esSueldoFijo). Quincenal se resuelve aparte donde se usa. */
    private function baseSueldoFijo(array $empleado): float
    {
        return $empleado['modalidad_pago'] === 'por_hora'
            ? (float)$empleado['horas_mensuales'] * (float)$empleado['valor_hora']
            : (float)$empleado['sueldo_mensual'];
    }

    /**
     * Calcula el monto devengado de una jornada según la modalidad de pago del empleado.
     *  - por_hora (sin horas mensuales cargadas): horas × valor_hora (× recargo si es feriado)
     *  - diario: valor_dia (× recargo si es feriado)
     *  - sueldo fijo (mensual/quincenal, o por_hora con horas mensuales cargadas): el sueldo
     *    fijo ya cubre los días normales — solo se devenga algo si es_feriado (día adicional
     *    a base/30, con recargo)
     */
    private function calcularMontoJornada(array $empleado, float $horas, bool $esFeriado): float
    {
        $markup = 1 + ((float)$empleado['markup_feriado_pct'] / 100);

        if ($this->esSueldoFijo($empleado)) {
            return $esFeriado ? round(($this->baseSueldoFijo($empleado) / 30) * $markup, 2) : 0.0;
        }

        return match ($empleado['modalidad_pago']) {
            'por_hora' => round($horas * (float)$empleado['valor_hora'] * ($esFeriado ? $markup : 1), 2),
            'diario'   => round((float)$empleado['valor_dia'] * ($esFeriado ? $markup : 1), 2),
            default => 0.0,
        };
    }

    /** Ya hay un registro (jornada o ausencia) para ese empleado en esa fecha */
    private function existeRegistroEnFecha(int $empleadoId, string $fecha): bool
    {
        $enJornada = (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM empleados_jornadas WHERE empleado_id = ? AND fecha = ? AND eliminado = 0",
            [$empleadoId, $fecha]
        );
        $enAusencia = (bool)$this->queryScalar(
            "SELECT COUNT(*) FROM empleados_ausencias WHERE empleado_id = ? AND fecha = ? AND eliminado = 0",
            [$empleadoId, $fecha]
        );
        return $enJornada || $enAusencia;
    }

    /**
     * Registra una jornada trabajada. Para sueldo fijo (mensual/quincenal, o por_hora con
     * horas mensuales cargadas) solo tiene sentido registrar feriados trabajados.
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function agregarJornada(int $empleadoId, string $fecha, float $horas, bool $esFeriado, ?string $notas, int $usuarioId): array
    {
        $empleado = $this->findById($empleadoId);
        if (!$empleado) return ['ok' => false, 'error' => 'Empleado no encontrado.'];

        if ($this->esSueldoFijo($empleado) && !$esFeriado) {
            return ['ok' => false, 'error' => 'Para sueldo fijo solo se registran los feriados trabajados (el resto del sueldo ya es fijo).'];
        }
        if ($empleado['modalidad_pago'] === 'por_hora' && !$this->esSueldoFijo($empleado) && $horas <= 0) {
            return ['ok' => false, 'error' => 'Ingresá la cantidad de horas trabajadas.'];
        }
        if ($this->existeRegistroEnFecha($empleadoId, $fecha)) {
            return ['ok' => false, 'error' => 'Ya hay un registro (jornada o ausencia) para ese empleado en esa fecha.'];
        }

        $monto = $this->calcularMontoJornada($empleado, $horas, $esFeriado);

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO empleados_jornadas (empleado_id, fecha, horas, es_feriado, tipo, monto, notas, usuario_id)
                 VALUES (:eid, :fecha, :horas, :feriado, :tipo, :monto, :notas, :uid)"
            );
            $stmt->execute([
                ':eid'     => $empleadoId,
                ':fecha'   => $fecha,
                ':horas'   => $horas,
                ':feriado' => $esFeriado ? 1 : 0,
                ':tipo'    => $esFeriado ? 'feriado' : 'normal',
                ':monto'   => $monto,
                ':notas'   => $notas ?: null,
                ':uid'     => $usuarioId,
            ]);
            return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'uq_ej_empleado_fecha')
                ? 'Ya existe una jornada cargada para ese empleado en esa fecha.'
                : $e->getMessage();
            return ['ok' => false, 'error' => $msg];
        }
    }

    /**
     * Registra horas extra (por encima del sueldo/jornada normal) para cualquier
     * modalidad de pago. Se paga siempre a valor_hora, sin recargo de feriado.
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function agregarHoraExtra(int $empleadoId, string $fecha, float $horas, ?string $notas, int $usuarioId): array
    {
        $empleado = $this->findById($empleadoId);
        if (!$empleado) return ['ok' => false, 'error' => 'Empleado no encontrado.'];
        if ($horas <= 0) return ['ok' => false, 'error' => 'Ingresá la cantidad de horas extra.'];
        if ((float)$empleado['valor_hora'] <= 0) {
            return ['ok' => false, 'error' => 'Este empleado no tiene un valor hora configurado — cargalo en su ficha para poder liquidar horas extra.'];
        }
        if ($this->existeRegistroEnFecha($empleadoId, $fecha)) {
            return ['ok' => false, 'error' => 'Ya hay un registro (jornada o ausencia) para ese empleado en esa fecha.'];
        }

        $monto = round($horas * (float)$empleado['valor_hora'], 2);

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO empleados_jornadas (empleado_id, fecha, horas, es_feriado, tipo, monto, notas, usuario_id)
                 VALUES (:eid, :fecha, :horas, 0, 'extra', :monto, :notas, :uid)"
            );
            $stmt->execute([
                ':eid' => $empleadoId, ':fecha' => $fecha, ':horas' => $horas,
                ':monto' => $monto, ':notas' => $notas ?: null, ':uid' => $usuarioId,
            ]);
            return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'uq_ej_empleado_fecha')
                ? 'Ya existe una jornada cargada para ese empleado en esa fecha.'
                : $e->getMessage();
            return ['ok' => false, 'error' => $msg];
        }
    }

    public function eliminarJornada(int $id): bool
    {
        return (bool)$this->db->prepare(
            "DELETE FROM empleados_jornadas WHERE id = ? AND pagado = 0"
        )->execute([$id]);
    }

    public function getJornadas(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_jornadas WHERE empleado_id = :id AND eliminado = 0 ORDER BY fecha DESC, id DESC",
            [':id' => $empleadoId]
        );
    }

    public function getJornadasPendientes(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_jornadas WHERE empleado_id = :id AND eliminado = 0 AND pagado = 0 ORDER BY fecha",
            [':id' => $empleadoId]
        );
    }

    // ── Conceptos (viáticos / premios / bonos) ──────────────────────────────

    public function agregarConcepto(int $empleadoId, string $fecha, string $tipo, ?string $descripcion, float $monto, int $usuarioId): int
    {
        $tiposValidos = array_column($this->getConceptosTipos(), 'codigo');
        if (!in_array($tipo, $tiposValidos, true)) $tipo = 'otro';

        $stmt = $this->db->prepare(
            "INSERT INTO empleados_conceptos (empleado_id, fecha, tipo, descripcion, monto, usuario_id)
             VALUES (:eid, :fecha, :tipo, :desc, :monto, :uid)"
        );
        $stmt->execute([
            ':eid'   => $empleadoId,
            ':fecha' => $fecha,
            ':tipo'  => $tipo,
            ':desc'  => $descripcion ?: null,
            ':monto' => $monto,
            ':uid'   => $usuarioId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function eliminarConcepto(int $id): bool
    {
        return (bool)$this->db->prepare(
            "DELETE FROM empleados_conceptos WHERE id = ? AND pagado = 0"
        )->execute([$id]);
    }

    public function getConceptos(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_conceptos WHERE empleado_id = :id AND eliminado = 0 ORDER BY fecha DESC, id DESC",
            [':id' => $empleadoId]
        );
    }

    public function getConceptosPendientes(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_conceptos WHERE empleado_id = :id AND eliminado = 0 AND pagado = 0 ORDER BY fecha",
            [':id' => $empleadoId]
        );
    }

    // ── Catálogo de tipos de concepto (viático/premio/bono/aguinaldo/…) ─────

    public function getConceptosTipos(): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_conceptos_tipos WHERE eliminado = 0 ORDER BY orden, nombre"
        );
    }

    /** Convierte "Aguinaldo" en "aguinaldo", deduplicando con sufijo numérico si ya existe */
    private function slugConceptoTipo(string $nombre): string
    {
        $base = strtolower(trim($nombre));
        $base = strtr($base, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = trim($base, '_');
        if ($base === '') $base = 'tipo';
        $base = substr($base, 0, 25);

        $codigo = $base;
        $i = 2;
        while ($this->queryScalar(
            "SELECT COUNT(*) FROM empleados_conceptos_tipos WHERE codigo = :c",
            [':c' => $codigo]
        ) > 0) {
            $codigo = $base . '_' . $i;
            $i++;
        }
        return $codigo;
    }

    public function crearConceptoTipo(string $nombre, string $color): int
    {
        $codigo = $this->slugConceptoTipo($nombre);
        $orden = (int)$this->queryScalar("SELECT COALESCE(MAX(orden), 0) + 1 FROM empleados_conceptos_tipos");

        $stmt = $this->db->prepare(
            "INSERT INTO empleados_conceptos_tipos (codigo, nombre, color, orden)
             VALUES (:codigo, :nombre, :color, :orden)"
        );
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => trim($nombre),
            ':color'  => $color,
            ':orden'  => $orden,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizarConceptoTipo(int $id, string $nombre, string $color): bool
    {
        return (bool)$this->db->prepare(
            "UPDATE empleados_conceptos_tipos SET nombre = :nombre, color = :color WHERE id = :id"
        )->execute([':nombre' => trim($nombre), ':color' => $color, ':id' => $id]);
    }

    public function conceptoTipoEnUso(string $codigo): bool
    {
        $enConceptos = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM empleados_conceptos WHERE tipo = :c AND eliminado = 0",
            [':c' => $codigo]
        );
        if ($enConceptos > 0) return true;

        $enRecurrentes = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM gastos_recurrentes WHERE concepto_tipo = :c AND eliminado = 0",
            [':c' => $codigo]
        );
        return $enRecurrentes > 0;
    }

    /** @return true si se eliminó, false si estaba en uso y no se pudo eliminar */
    public function eliminarConceptoTipo(int $id): bool
    {
        $tipo = $this->queryOne("SELECT codigo FROM empleados_conceptos_tipos WHERE id = ?", [$id]);
        if (!$tipo) return false;
        if ($this->conceptoTipoEnUso($tipo['codigo'])) return false;

        $this->db->prepare("UPDATE empleados_conceptos_tipos SET eliminado = 1 WHERE id = ?")->execute([$id]);
        return true;
    }

    // ── Ausencias (descuento de dinero o deuda de horas a recuperar) ────────

    /** Monto a descontar por un día de ausencia, según la modalidad de pago del empleado */
    private function calcularDescuentoAusencia(array $empleado, float $horas): float
    {
        if ($this->esSueldoFijo($empleado)) {
            return round($this->baseSueldoFijo($empleado) / 30, 2);
        }
        return match ($empleado['modalidad_pago']) {
            'diario'   => round((float)$empleado['valor_dia'], 2),
            'por_hora' => round($horas * (float)$empleado['valor_hora'], 2),
            default    => 0.0,
        };
    }

    /**
     * Registra una ausencia. tipo='descuento' resta plata del próximo pago;
     * tipo='recuperar' no descuenta nada, solo deja una deuda de horas pendiente.
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function agregarAusencia(int $empleadoId, string $fecha, string $tipo, float $horas, ?string $notas, int $usuarioId): array
    {
        $empleado = $this->findById($empleadoId);
        if (!$empleado) return ['ok' => false, 'error' => 'Empleado no encontrado.'];
        if (!in_array($tipo, ['descuento', 'recuperar'], true)) {
            return ['ok' => false, 'error' => 'Tipo de ausencia inválido.'];
        }
        if ($tipo === 'recuperar' && $horas <= 0) {
            return ['ok' => false, 'error' => 'Ingresá la cantidad de horas de la ausencia a recuperar.'];
        }
        if ($this->existeRegistroEnFecha($empleadoId, $fecha)) {
            return ['ok' => false, 'error' => 'Ya hay un registro (jornada o ausencia) para ese empleado en esa fecha.'];
        }

        // Para tipo=recuperar el monto no se descuenta ahora, pero se congela como
        // referencia (valor $ equivalente a las horas debidas) para mostrar la deuda
        // también en dinero, y para saber cuánto "vale" cada hora que se recupere.
        $montoDescuento    = $tipo === 'descuento' ? $this->calcularDescuentoAusencia($empleado, $horas) : 0.0;
        $montoEquivalente  = $tipo === 'recuperar' ? $this->calcularDescuentoAusencia($empleado, $horas) : 0.0;
        $horasPendientes   = $tipo === 'recuperar' ? $horas : 0.0;

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO empleados_ausencias (empleado_id, fecha, tipo, horas, monto_descuento, horas_pendientes, monto_equivalente, notas, usuario_id)
                 VALUES (:eid, :fecha, :tipo, :horas, :monto, :hp, :meq, :notas, :uid)"
            );
            $stmt->execute([
                ':eid' => $empleadoId, ':fecha' => $fecha, ':tipo' => $tipo, ':horas' => $horas,
                ':monto' => $montoDescuento, ':hp' => $horasPendientes, ':meq' => $montoEquivalente,
                ':notas' => $notas ?: null, ':uid' => $usuarioId,
            ]);
            return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'uq_ea_empleado_fecha')
                ? 'Ya existe un registro para ese empleado en esa fecha.'
                : $e->getMessage();
            return ['ok' => false, 'error' => $msg];
        }
    }

    public function eliminarAusencia(int $id): bool
    {
        return (bool)$this->db->prepare(
            "DELETE FROM empleados_ausencias WHERE id = ? AND pagado = 0"
        )->execute([$id]);
    }

    public function getAusencias(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_ausencias WHERE empleado_id = :id AND eliminado = 0 ORDER BY fecha DESC, id DESC",
            [':id' => $empleadoId]
        );
    }

    public function getAusenciasPendientes(int $empleadoId): array
    {
        return $this->queryAll(
            "SELECT * FROM empleados_ausencias WHERE empleado_id = :id AND eliminado = 0 AND tipo = 'descuento' AND pagado = 0 ORDER BY fecha",
            [':id' => $empleadoId]
        );
    }

    /** Total de horas que el empleado todavía nos debe (ausencias tipo=recuperar no saldadas) */
    public function getDeudaHoras(int $empleadoId): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(horas_pendientes), 0) FROM empleados_ausencias
             WHERE empleado_id = ? AND eliminado = 0 AND tipo = 'recuperar' AND horas_pendientes > 0",
            [$empleadoId]
        );
    }

    /** Equivalente en dinero de la deuda de horas pendiente (proporcional a lo que ya se recuperó de cada ausencia) */
    public function getDeudaMonto(int $empleadoId): float
    {
        return (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto_equivalente * horas_pendientes / horas), 0) FROM empleados_ausencias
             WHERE empleado_id = ? AND eliminado = 0 AND tipo = 'recuperar' AND horas_pendientes > 0 AND horas > 0",
            [$empleadoId]
        );
    }

    /**
     * Marca horas como recuperadas de la ausencia más antigua con deuda pendiente primero (FIFO).
     * Reduce tanto la deuda de horas como su equivalente en dinero (proporcional).
     * @return array{ok:bool, error?:string, horas_aplicadas?:float, monto_aplicado?:float}
     */
    public function recuperarHoras(int $empleadoId, float $horas, ?string $notas, int $usuarioId): array
    {
        if ($horas <= 0) return ['ok' => false, 'error' => 'Ingresá la cantidad de horas recuperadas.'];

        $pendientes = $this->queryAll(
            "SELECT id, horas, horas_pendientes, monto_equivalente FROM empleados_ausencias
             WHERE empleado_id = ? AND eliminado = 0 AND tipo = 'recuperar' AND horas_pendientes > 0
             ORDER BY fecha",
            [$empleadoId]
        );

        $restante      = $horas;
        $montoAplicado = 0.0;
        foreach ($pendientes as $a) {
            if ($restante <= 0) break;
            $aplicar        = min($restante, (float)$a['horas_pendientes']);
            $nuevoPendiente = round((float)$a['horas_pendientes'] - $aplicar, 2);
            $tasaHora       = (float)$a['horas'] > 0 ? (float)$a['monto_equivalente'] / (float)$a['horas'] : 0.0;
            $montoAplicado += round($aplicar * $tasaHora, 2);
            $notaExtra      = $notas ? (' | Recuperadas: ' . $aplicar . 'hs — ' . $notas) : (' | Recuperadas: ' . $aplicar . 'hs');
            $this->db->prepare(
                "UPDATE empleados_ausencias SET horas_pendientes = ?, notas = CONCAT(COALESCE(notas,''), ?) WHERE id = ?"
            )->execute([$nuevoPendiente, $notaExtra, $a['id']]);
            $restante -= $aplicar;
        }

        if ($restante > 0.009) {
            return ['ok' => false, 'error' => 'El empleado solo debía ' . round($horas - $restante, 2) . ' horas; se aplicó ese total.'];
        }

        return ['ok' => true, 'horas_aplicadas' => $horas, 'monto_aplicado' => round($montoAplicado, 2)];
    }

    /** Estado del mes (jornadas + ausencias) por fecha, para el calendario de la ficha */
    public function getCalendarioMes(int $empleadoId, int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $mapa = [];
        foreach ($this->queryAll(
            "SELECT id, fecha, tipo, horas, monto, pagado FROM empleados_jornadas
             WHERE empleado_id = ? AND eliminado = 0 AND fecha BETWEEN ? AND ?",
            [$empleadoId, $desde, $hasta]
        ) as $j) {
            $mapa[$j['fecha']] = ['origen' => 'jornada', 'tipo' => $j['tipo'], 'id' => $j['id'], 'horas' => $j['horas'], 'monto' => $j['monto'], 'pagado' => $j['pagado']];
        }
        foreach ($this->queryAll(
            "SELECT id, fecha, tipo, horas, monto_descuento, horas_pendientes, monto_equivalente, pagado FROM empleados_ausencias
             WHERE empleado_id = ? AND eliminado = 0 AND fecha BETWEEN ? AND ?",
            [$empleadoId, $desde, $hasta]
        ) as $a) {
            $montoPendiente = ($a['tipo'] === 'recuperar' && (float)$a['horas'] > 0)
                ? round((float)$a['monto_equivalente'] * (float)$a['horas_pendientes'] / (float)$a['horas'], 2)
                : null;
            $mapa[$a['fecha']] = [
                'origen' => 'ausencia',
                'tipo'   => $a['tipo'] === 'descuento' ? 'ausencia_descuento' : 'ausencia_recuperar',
                'id'     => $a['id'], 'horas' => $a['horas'],
                'monto'  => $a['tipo'] === 'descuento' ? $a['monto_descuento'] : $montoPendiente,
                'horas_pendientes' => $a['horas_pendientes'],
                'pagado' => $a['pagado'],
            ];
        }
        return $mapa;
    }

    // ── Liquidación de sueldo ────────────────────────────────────────────────

    /**
     * Resumen de lo pendiente de liquidar para un empleado: base fija (si aplica),
     * jornadas pendientes (feriados/días/horas) y conceptos pendientes (viáticos/premios/bonos).
     */
    public function getResumenLiquidacion(int $empleadoId): array
    {
        $empleado = $this->findById($empleadoId);
        if (!$empleado) return ['ok' => false];

        $baseMensual = 0.0;
        if ($this->esSueldoFijo($empleado)) {
            $baseMensual = $this->baseSueldoFijo($empleado) * ($empleado['modalidad_pago'] === 'quincenal' ? 0.5 : 1);
        }

        // El sueldo base fijo no tiene una fila propia como jornadas/conceptos (no hay un
        // "id de sueldo base" para marcar pagado=1), así que hay que inferir cuánto de la
        // base ya se cubrió este mes a partir de los pagos tipo=sueldo ya registrados.
        // Por cada pago del mes, la "porción de base" es lo que sobra de monto_total una
        // vez descontadas las jornadas/conceptos vinculados a ESE pago (ya están marcados
        // pagado=1 y excluidos de $totalJornadas/$totalConceptos más abajo — si no se
        // restaran acá, se contarían dos veces) y sumadas las ausencias vinculadas.
        // GREATEST(0, ...) evita que un pago "solo de jornadas/conceptos" (porción de base
        // negativa) reste cuenta de la base cubierta por otro pago del mismo mes.
        //
        // Antes esto solo restaba pagos con la columna recurrente_id cargada — una marca de
        // un generador automático que ya no se usa (ver RecurrenteModel::generarPendientes).
        // Como los pagos de sueldo cargados a mano desde "Nuevo pago" nunca tienen esa marca,
        // el sueldo base nunca se descontaba y quedaba "pendiente" para siempre aunque ya se
        // hubiera pagado — eso es lo que se veía como un egreso teórico de Sueldos inflado en
        // el Resumen Económico, sin relación con los pagos reales.
        if ($baseMensual > 0) {
            $yaGenerado = (float)$this->queryScalar(
                "SELECT COALESCE(SUM(
                    GREATEST(0, pp.monto_total
                        - COALESCE((SELECT SUM(ej.monto) FROM empleados_jornadas ej WHERE ej.pago_id = pp.id), 0)
                        - COALESCE((SELECT SUM(ec.monto) FROM empleados_conceptos ec WHERE ec.pago_id = pp.id), 0)
                        + COALESCE((SELECT SUM(ea.monto_descuento) FROM empleados_ausencias ea WHERE ea.pago_id = pp.id), 0)
                    )
                 ), 0)
                 FROM pagos_proveedores pp
                 WHERE pp.empleado_id = :id AND pp.tipo = 'sueldo' AND pp.estado = 'activo' AND pp.eliminado = 0
                   AND DATE_FORMAT(pp.fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')",
                [':id' => $empleadoId]
            );
            $baseMensual = max(0.0, round($baseMensual - $yaGenerado, 2));
        }

        $jornadas  = $this->getJornadasPendientes($empleadoId);
        $conceptos = $this->getConceptosPendientes($empleadoId);
        $ausencias = $this->getAusenciasPendientes($empleadoId);

        $totalJornadas  = array_sum(array_column($jornadas, 'monto'));
        $totalConceptos = array_sum(array_column($conceptos, 'monto'));
        $totalAusencias = array_sum(array_column($ausencias, 'monto_descuento'));

        return [
            'ok'              => true,
            'modalidad_pago'  => $empleado['modalidad_pago'],
            'base_mensual'    => round($baseMensual, 2),
            'jornadas'        => $jornadas,
            'conceptos'       => $conceptos,
            'ausencias'       => $ausencias,
            'deuda_horas'     => $this->getDeudaHoras($empleadoId),
            'deuda_monto'     => $this->getDeudaMonto($empleadoId),
            'total_sugerido'  => round($baseMensual + $totalJornadas + $totalConceptos - $totalAusencias, 2),
        ];
    }

    /**
     * Total pendiente de liquidar a día de hoy, sumado entre todos los empleados activos.
     * No está acotado a un período (a diferencia del resto del Resumen Económico): es
     * "cuánto les debo a los empleados ahora mismo" independientemente del mes filtrado.
     */
    public function getTotalPendienteLiquidarTodos(): float
    {
        $total = 0.0;
        foreach ($this->findAllActivos() as $emp) {
            $resumen = $this->getResumenLiquidacion((int)$emp['id']);
            if (!empty($resumen['ok'])) {
                $total += max(0.0, (float)$resumen['total_sugerido']);
            }
        }
        return round($total, 2);
    }

    /** Marca como pagadas (liquidadas) las jornadas, conceptos y ausencias incluidos en un pago */
    public function marcarLiquidado(int $pagoId, array $jornadaIds, array $conceptoIds, array $ausenciaIds = []): void
    {
        $jornadaIds  = array_filter(array_map('intval', $jornadaIds));
        $conceptoIds = array_filter(array_map('intval', $conceptoIds));
        $ausenciaIds = array_filter(array_map('intval', $ausenciaIds));

        if ($jornadaIds) {
            $in = implode(',', array_fill(0, count($jornadaIds), '?'));
            $this->db->prepare(
                "UPDATE empleados_jornadas SET pagado = 1, pago_id = ? WHERE id IN ($in) AND pagado = 0"
            )->execute([$pagoId, ...$jornadaIds]);
        }
        if ($conceptoIds) {
            $in = implode(',', array_fill(0, count($conceptoIds), '?'));
            $this->db->prepare(
                "UPDATE empleados_conceptos SET pagado = 1, pago_id = ? WHERE id IN ($in) AND pagado = 0"
            )->execute([$pagoId, ...$conceptoIds]);
        }
        if ($ausenciaIds) {
            $in = implode(',', array_fill(0, count($ausenciaIds), '?'));
            $this->db->prepare(
                "UPDATE empleados_ausencias SET pagado = 1, pago_id = ? WHERE id IN ($in) AND pagado = 0"
            )->execute([$pagoId, ...$ausenciaIds]);
        }
    }

    /** Revierte a "pendiente" las jornadas/conceptos/ausencias que habían sido liquidados por un pago anulado */
    public function revertirLiquidacion(int $pagoId): void
    {
        $this->db->prepare("UPDATE empleados_jornadas SET pagado = 0, pago_id = NULL WHERE pago_id = ?")->execute([$pagoId]);
        $this->db->prepare("UPDATE empleados_conceptos SET pagado = 0, pago_id = NULL WHERE pago_id = ?")->execute([$pagoId]);
        $this->db->prepare("UPDATE empleados_ausencias SET pagado = 0, pago_id = NULL WHERE pago_id = ?")->execute([$pagoId]);
    }
}
