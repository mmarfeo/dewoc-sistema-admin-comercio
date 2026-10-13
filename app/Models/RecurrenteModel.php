<?php
require_once BASE_PATH . '/app/Models/Model.php';

class RecurrenteModel extends Model
{
    protected string $table = 'gastos_recurrentes';

    public function findAllFiltrado(array $filtros = []): array
    {
        $where  = ['gr.eliminado = 0'];
        $params = [];

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $where[]            = 'gr.activo = :activo';
            $params[':activo']  = (int)$filtros['activo'];
        }

        $cond = implode(' AND ', $where);
        return $this->queryAll(
            "SELECT gr.*, gc.nombre AS categoria_nombre, e.nombre AS empleado_nombre,
                    p.razon_social AS proveedor_nombre
             FROM gastos_recurrentes gr
             LEFT JOIN gasto_categorias gc ON gc.id = gr.categoria_id
             LEFT JOIN empleados e        ON e.id  = gr.empleado_id
             LEFT JOIN proveedores p      ON p.id  = gr.proveedor_id
             WHERE $cond
             ORDER BY gr.activo DESC, gr.tipo, gr.descripcion",
            $params
        );
    }

    public function findByIdActivo(int $id): array|false
    {
        return $this->queryOne(
            "SELECT * FROM gastos_recurrentes WHERE id = ? AND eliminado = 0",
            [$id]
        );
    }

    public function crear(array $data): int
    {
        return $this->insert($data);
    }

    public function actualizar(int $id, array $data): bool
    {
        $sets   = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt   = $this->db->prepare("UPDATE gastos_recurrentes SET $sets WHERE id = :_id AND eliminado = 0");
        $params = $data;
        $params[':_id'] = $id;
        return $stmt->execute($params);
    }

    public function toggleActivo(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE gastos_recurrentes SET activo = 1 - activo WHERE id = ? AND eliminado = 0"
        )->execute([$id]);
    }

    public function eliminar(int $id): bool
    {
        return $this->softDelete($id);
    }

    /** Arma una fecha YYYY-MM-DD para el período dado, ajustando el día si el mes es más corto (ej. día 31 en febrero) */
    private function _fechaDelPeriodo(string $periodoAAAAMM, int $dia): string
    {
        [$anio, $mes] = array_map('intval', explode('-', $periodoAAAAMM));
        $ultimoDiaMes = (int)date('t', mktime(0, 0, 0, $mes, 1, $anio));
        return sprintf('%s-%02d', $periodoAAAAMM, min(max($dia, 1), $ultimoDiaMes));
    }

    /**
     * Vencimientos de las plantillas activas para un mes puntual, se hayan generado
     * o no todavía (para poder ver desde el día 1 todo lo que hay que pagar ese mes
     * y cuándo vence, sin esperar a que se autogenere el registro real).
     *
     * tipo='sueldo' queda afuera (ver generarPendientes): como ya no se auto-genera,
     * "ya_generado" quedaría siempre en false y el vencimiento se mostraría "vencido"
     * para siempre aunque el sueldo se haya liquidado — ese estado real ya se ve
     * correctamente en Empleados (Liquidar sueldo), no hace falta duplicarlo acá.
     */
    public function getVencimientosMes(int $anio, int $mes): array
    {
        $periodo = sprintf('%04d-%02d', $anio, $mes);

        $plantillas = $this->queryAll(
            "SELECT gr.*, gc.nombre AS categoria_nombre, e.nombre AS empleado_nombre,
                    p.razon_social AS proveedor_nombre
             FROM gastos_recurrentes gr
             LEFT JOIN gasto_categorias gc ON gc.id = gr.categoria_id
             LEFT JOIN empleados e        ON e.id  = gr.empleado_id
             LEFT JOIN proveedores p      ON p.id  = gr.proveedor_id
             WHERE gr.activo = 1 AND gr.eliminado = 0 AND gr.tipo != 'sueldo'"
        );

        $resultado = [];
        foreach ($plantillas as $r) {
            $diaVenc = (int)($r['dia_vencimiento'] ?: $r['dia_generacion']);
            $r['fecha_vencimiento'] = $this->_fechaDelPeriodo($periodo, $diaVenc);
            $r['ya_generado']       = $r['ultimo_periodo_generado'] === $periodo;
            $resultado[] = $r;
        }

        usort($resultado, fn($a, $b) => $a['fecha_vencimiento'] <=> $b['fecha_vencimiento']);
        return $resultado;
    }

    /**
     * Genera los gastos/viáticos cuyo día de generación ya llegó y que todavía
     * no fueron generados en el período (mes) actual. Se llama de forma
     * perezosa desde Controller::requireAuth() (y también al guardar una
     * plantilla), no depende de un cron del servidor. El monto y la fecha
     * (día configurado del mes en curso) quedan congelados en el registro
     * generado; el usuario puede editarlos después si hubo un aumento de
     * precio o de consumo.
     *
     * tipo='sueldo' queda deliberadamente afuera: pagos_proveedores es un
     * registro de plata que YA salió de caja (ver PagoProveedorModel::
     * totalPagadoEnPeriodo), y generarlo acá auto-marcaba el sueldo fijo como
     * pagado en efectivo sin que nadie confirmara que realmente se le pagó al
     * empleado — el Chat IA y los reportes terminaban mostrando como "pago
     * realizado" un sueldo que en realidad todavía no se le entregó. El sueldo
     * fijo mensual/quincenal ya se calcula solo cada mes desde el propio
     * empleado (EmpleadoModel::getResumenLiquidacion, independiente de
     * cualquier plantilla acá) y requiere confirmarlo explícitamente vía
     * Liquidar sueldo (Empleados o registrar_pago_sueldo del Chat IA) — igual
     * que ya funciona para jornadas, ausencias y conceptos.
     *
     * Cada ítem se "reclama" con un UPDATE atómico antes de generarlo: si dos
     * requests corren casi al mismo tiempo (ej. el usuario guarda varias
     * plantillas seguidas y cada guardado dispara una corrida), el segundo ve
     * 0 filas afectadas y lo saltea, evitando generar el mismo gasto dos
     * veces. Si la generación de un ítem falla, se libera el reclamo (vuelve
     * a quedar pendiente) y se loguea, sin frenar a los demás ítems del lote.
     */
    public function generarPendientes(int $usuarioSistemaId): int
    {
        $periodoActual = date('Y-m');
        $diaHoy        = (int)date('j');

        $pendientes = $this->queryAll(
            "SELECT * FROM gastos_recurrentes
             WHERE activo = 1 AND eliminado = 0 AND tipo != 'sueldo'
               AND (ultimo_periodo_generado IS NULL OR ultimo_periodo_generado < :periodo)
               AND dia_generacion <= :dia",
            [':periodo' => $periodoActual, ':dia' => $diaHoy]
        );

        $generados = 0;
        foreach ($pendientes as $r) {
            $claim = $this->db->prepare(
                "UPDATE gastos_recurrentes SET ultimo_periodo_generado = :periodo
                 WHERE id = :id AND activo = 1 AND eliminado = 0
                   AND (ultimo_periodo_generado IS NULL OR ultimo_periodo_generado < :periodo2)"
            );
            $claim->execute([':periodo' => $periodoActual, ':id' => $r['id'], ':periodo2' => $periodoActual]);
            if ($claim->rowCount() === 0) continue; // otro request ya lo reclamó/generó

            $fecha     = $this->_fechaDelPeriodo($periodoActual, (int)$r['dia_generacion']);
            $vencDia   = (int)($r['dia_vencimiento'] ?: $r['dia_generacion']);
            $fechaVenc = $this->_fechaDelPeriodo($periodoActual, $vencDia);
            $usuarioId = (int)$r['usuario_id'] ?: $usuarioSistemaId;

            try {
            if ($r['tipo'] === 'viatico' && $r['empleado_id']) {
                $tipoValido = (int)$this->queryScalar(
                    "SELECT COUNT(*) FROM empleados_conceptos_tipos WHERE codigo = :c AND eliminado = 0",
                    [':c' => $r['concepto_tipo']]
                ) > 0;
                $conceptoTipo = $tipoValido ? $r['concepto_tipo'] : 'viatico';
                $this->db->prepare(
                    "INSERT INTO empleados_conceptos (empleado_id, fecha, tipo, descripcion, monto, usuario_id, recurrente_id)
                     VALUES (:eid, :fecha, :tipo, :desc, :monto, :uid, :rid)"
                )->execute([
                    ':eid'  => $r['empleado_id'],
                    ':fecha'=> $fecha,
                    ':tipo' => $conceptoTipo,
                    ':desc' => $r['descripcion'],
                    ':monto'=> $r['monto'],
                    ':uid'  => $usuarioId,
                    ':rid'  => $r['id'],
                ]);
            } elseif ($r['tipo'] === 'gasto' && $r['categoria_id']) {
                $this->db->prepare(
                    "INSERT INTO gastos
                     (fecha, fecha_vencimiento, descripcion, monto, categoria_id, proveedor_id,
                      estado, usuario_id, notas, recurrente_id)
                     VALUES (:fecha, :fvenc, :desc, :monto, :cat, :prov, :estado, :uid, :notas, :rid)"
                )->execute([
                    ':fecha'  => $fecha,
                    ':fvenc'  => $fechaVenc,
                    ':desc'   => $r['descripcion'],
                    ':monto'  => $r['monto'],
                    ':cat'    => $r['categoria_id'],
                    ':prov'   => $r['proveedor_id'] ?: null,
                    ':estado' => $r['proveedor_id'] ? 'pendiente' : 'pagado',
                    ':uid'    => $usuarioId,
                    ':notas'  => 'Generado automáticamente (costo fijo recurrente)',
                    ':rid'    => $r['id'],
                ]);

                // Tope de repeticiones (ej. amortización de un activo fijo a N meses, ver
                // ActivoFijoModel): se cuenta contra los gastos reales ya generados con este
                // recurrente_id, no un contador aparte, para no desincronizarse si alguno se
                // edita/borra a mano después.
                if ($r['repeticiones_maximas'] !== null) {
                    $generados = (int)$this->queryScalar(
                        'SELECT COUNT(*) FROM gastos WHERE recurrente_id = ?', [$r['id']]
                    );
                    if ($generados >= (int)$r['repeticiones_maximas']) {
                        $this->db->prepare('UPDATE gastos_recurrentes SET activo = 0 WHERE id = ?')->execute([$r['id']]);
                    }
                }
            } else {
                throw new \RuntimeException("Plantilla #{$r['id']} ({$r['descripcion']}) tipo '{$r['tipo']}' sin empleado_id/categoria_id asignado.");
            }

            $generados++;
            } catch (\Throwable $e) {
                // Liberar el reclamo para que se reintente en el próximo request en vez de
                // quedar marcado como "generado" sin haberse creado nada.
                $this->db->prepare(
                    "UPDATE gastos_recurrentes SET ultimo_periodo_generado = ? WHERE id = ?"
                )->execute([$r['ultimo_periodo_generado'], $r['id']]);
                logApp("Error generando recurrente #{$r['id']} ({$r['descripcion']}): " . $e->getMessage());
            }
        }

        return $generados;
    }
}
