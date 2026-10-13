<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/SolicitudModel.php';

/**
 * Plantillas de solicitudes de inventario recurrentes (ej. "todos los martes,
 * Helados"). Mismo patrón que RecurrenteModel (gastos fijos mensuales) pero con
 * periodicidad semanal por día en vez de mensual por día del mes: generación
 * perezosa desde Controller::requireAuth(), con reclamo atómico para evitar
 * duplicados si dos requests corren casi al mismo tiempo.
 */
class SolicitudRecurrenteModel extends Model
{
    protected string $table = 'solicitudes_recurrentes';

    public function findAllFiltrado(array $filtros = []): array
    {
        $where  = ['sr.eliminado = 0'];
        $params = [];

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $where[]           = 'sr.activo = :activo';
            $params[':activo'] = (int)$filtros['activo'];
        }

        return $this->queryAll(
            "SELECT sr.*, u.nombre AS asignado_a_nombre
             FROM solicitudes_recurrentes sr
             LEFT JOIN usuarios u ON u.id = sr.asignado_a
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sr.activo DESC, sr.dia_semana, sr.descripcion",
            $params
        );
    }

    public function findByIdActivo(int $id): array|false
    {
        return $this->queryOne(
            "SELECT * FROM solicitudes_recurrentes WHERE id = ? AND eliminado = 0", [$id]
        );
    }

    /** Criterios de una plantilla, con nombre resuelto (para mostrar y para copiar al generar) */
    public function getCriterios(int $plantillaId): array
    {
        $filas = $this->queryAll(
            "SELECT tipo_criterio, referencia_id FROM solicitudes_recurrentes_detalle WHERE solicitud_recurrente_id = ?",
            [$plantillaId]
        );
        // Misma resolución de nombres que usan las solicitudes de inventario puntuales.
        return (new SolicitudModel())->resolverNombresCriterios($filas);
    }

    /** Crea la plantilla y su detalle de criterios en la misma transacción */
    public function crear(array $data, array $criterios): int
    {
        $this->db->beginTransaction();
        try {
            $id = $this->insert($data);

            $stmt = $this->db->prepare(
                "INSERT INTO solicitudes_recurrentes_detalle (solicitud_recurrente_id, tipo_criterio, referencia_id)
                 VALUES (:pid, :tipo, :ref)"
            );
            foreach ($criterios as $c) {
                $stmt->execute([':pid' => $id, ':tipo' => $c['tipo_criterio'], ':ref' => $c['referencia_id']]);
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Actualiza la plantilla y reemplaza por completo su detalle de criterios */
    public function actualizar(int $id, array $data, array $criterios): void
    {
        $this->db->beginTransaction();
        try {
            $this->update($id, $data);
            $this->db->prepare("DELETE FROM solicitudes_recurrentes_detalle WHERE solicitud_recurrente_id = ?")->execute([$id]);

            $stmt = $this->db->prepare(
                "INSERT INTO solicitudes_recurrentes_detalle (solicitud_recurrente_id, tipo_criterio, referencia_id)
                 VALUES (:pid, :tipo, :ref)"
            );
            foreach ($criterios as $c) {
                $stmt->execute([':pid' => $id, ':tipo' => $c['tipo_criterio'], ':ref' => $c['referencia_id']]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function toggleActivo(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE solicitudes_recurrentes SET activo = 1 - activo WHERE id = ? AND eliminado = 0"
        )->execute([$id]);
    }

    public function eliminar(int $id): bool
    {
        return $this->softDelete($id);
    }

    /**
     * Genera las solicitudes de inventario cuya plantilla activa cae en el día de la
     * semana de hoy y todavía no se generó esta semana. Se llama de forma perezosa
     * desde Controller::requireAuth(), no depende de un cron del servidor. Cada
     * plantilla se "reclama" con un UPDATE atómico antes de generar (mismo mecanismo
     * que RecurrenteModel::generarPendientes()) para no duplicar la solicitud si dos
     * requests corren casi al mismo tiempo.
     */
    public function generarPendientes(int $usuarioSistemaId): int
    {
        $diaSemanaHoy    = (int)date('N'); // 1=lunes .. 7=domingo
        $lunesEstaSemana = date('Y-m-d', strtotime('-' . ($diaSemanaHoy - 1) . ' days'));

        $pendientes = $this->queryAll(
            "SELECT * FROM solicitudes_recurrentes
             WHERE activo = 1 AND eliminado = 0 AND dia_semana = :dia
               AND (ultima_fecha_generada IS NULL OR ultima_fecha_generada < :lunes)",
            [':dia' => $diaSemanaHoy, ':lunes' => $lunesEstaSemana]
        );

        $solicitudModel = new SolicitudModel();
        $generadas = 0;

        foreach ($pendientes as $r) {
            $claim = $this->db->prepare(
                "UPDATE solicitudes_recurrentes SET ultima_fecha_generada = :lunes
                 WHERE id = :id AND activo = 1 AND eliminado = 0
                   AND (ultima_fecha_generada IS NULL OR ultima_fecha_generada < :lunes2)"
            );
            $claim->execute([':lunes' => $lunesEstaSemana, ':id' => $r['id'], ':lunes2' => $lunesEstaSemana]);
            if ($claim->rowCount() === 0) continue; // otro request ya la reclamó/generó

            try {
                $criterios = $this->getCriterios((int)$r['id']);
                $solicitudModel->crear([
                    'tipo'          => 'inventario',
                    'item_id'       => null,
                    'item_nombre'   => $solicitudModel->etiquetaCriterios($criterios),
                    'descripcion'   => 'Generada automáticamente desde la plantilla recurrente "' . $r['descripcion'] . '".',
                    'prioridad'     => $r['prioridad'],
                    'estado'        => 'pendiente',
                    'usuario_id'    => $usuarioSistemaId,
                    'asignado_a'    => $r['asignado_a'],
                    'recurrente_id' => $r['id'],
                ], $criterios);

                $generadas++;
            } catch (\Throwable $e) {
                // Liberar el reclamo para reintentar en el próximo request en vez de
                // quedar marcada como "generada" sin haberse creado nada.
                $this->db->prepare(
                    "UPDATE solicitudes_recurrentes SET ultima_fecha_generada = ? WHERE id = ?"
                )->execute([$r['ultima_fecha_generada'], $r['id']]);
                logApp('Error generando solicitud recurrente #' . $r['id'] . ' (' . $r['descripcion'] . '): ' . $e->getMessage());
            }
        }

        return $generadas;
    }
}
