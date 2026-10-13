<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/RecurrenteModel.php';

/**
 * Activos fijos (ver docs/activos-fijos-diseno.md) — bienes de uso (horno, cafetera,
 * mobiliario), con su costo histórico y, opcionalmente, una amortización mensual
 * automática vía gastos_recurrentes (categoría "Amortizaciones"). El valor guardado
 * (`valor`) nunca cambia; el "valor actual" se calcula al vuelo restando lo ya
 * amortizado, no se persiste — así nunca queda desactualizado.
 */
class ActivoFijoModel extends Model
{
    protected string $table = 'activos_fijos';

    public function findAllConDetalle(array $filtros = []): array
    {
        $where  = ['a.eliminado = 0'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[]           = 'a.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        $rows = $this->queryAll(
            "SELECT a.*, p.razon_social AS proveedor_nombre, u.nombre AS usuario_nombre,
                    gr.monto AS cuota_mensual, gr.activo AS amortizacion_activa
             FROM activos_fijos a
             LEFT JOIN proveedores p       ON p.id  = a.proveedor_id
             LEFT JOIN usuarios u          ON u.id  = a.usuario_id
             LEFT JOIN gastos_recurrentes gr ON gr.id = a.gasto_recurrente_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.fecha_alta DESC",
            $params
        );

        foreach ($rows as &$r) {
            $r['monto_amortizado'] = $r['gasto_recurrente_id']
                ? (float)$this->queryScalar('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE recurrente_id = ?', [$r['gasto_recurrente_id']])
                : 0.0;
            $r['valor_actual'] = max(0.0, (float)$r['valor'] - $r['monto_amortizado']);
        }
        unset($r);

        return $rows;
    }

    /**
     * Crea el activo y, si trae vida útil, la plantilla de amortización mensual
     * (valor ÷ vida_util_meses) categorizada en "Amortizaciones", con tope de
     * repeticiones = vida_util_meses (se apaga sola, ver RecurrenteModel::generarPendientes()).
     */
    public function crear(array $data): int
    {
        $vidaUtil = !empty($data['vida_util_meses']) ? (int)$data['vida_util_meses'] : null;
        $valor    = (float)($data['valor'] ?? 0);

        $this->db->beginTransaction();
        try {
            $id = $this->insert($data);

            if ($vidaUtil && $vidaUtil > 0 && $valor > 0) {
                $recurrenteId = $this->crearAmortizacion(
                    $data['nombre'], $valor, $vidaUtil, $data['fecha_alta'], (int)$data['usuario_id']
                );
                $this->db->prepare('UPDATE activos_fijos SET gasto_recurrente_id = ? WHERE id = ?')
                    ->execute([$recurrenteId, $id]);
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function crearAmortizacion(string $nombre, float $valor, int $vidaUtilMeses, string $fechaAlta, int $usuarioId): int
    {
        $categoriaId = (int)$this->queryScalar(
            "SELECT id FROM gasto_categorias WHERE nombre = 'Amortizaciones' AND eliminado = 0 LIMIT 1"
        );
        if (!$categoriaId) {
            throw new \RuntimeException('No se encontró la categoría "Amortizaciones" en Gastos — no se puede armar la amortización.');
        }

        $recurrenteModel = new RecurrenteModel();
        return $recurrenteModel->crear([
            'tipo'                 => 'gasto',
            'descripcion'          => "Amortización: {$nombre}",
            'categoria_id'         => $categoriaId,
            'monto'                => round($valor / $vidaUtilMeses, 2),
            'dia_generacion'       => min(28, max(1, (int)date('j', strtotime($fechaAlta)))),
            'repeticiones_maximas' => $vidaUtilMeses,
            'activo'               => 1,
            'usuario_id'           => $usuarioId,
        ]);
    }

    /**
     * Solo los campos editables después de creado (nombre, categoría libre, proveedor,
     * estado, notas) — valor/vida útil/origen/fecha quedan fijos una vez armada la
     * amortización, para no tener que reconciliar cuotas ya generadas. Si hace falta
     * corregir esos, se elimina y se vuelve a cargar.
     */
    public function actualizar(int $id, array $data): void
    {
        $activo = $this->findById($id);
        if (!$activo) return;

        $this->update($id, $data);

        // Dado de baja/vendido: no tiene sentido seguir amortizando algo que ya no se usa
        // — se apaga la plantilla (no se borra, queda el historial de lo ya generado).
        if (isset($data['estado']) && $data['estado'] !== 'activo' && $activo['gasto_recurrente_id']) {
            $this->db->prepare('UPDATE gastos_recurrentes SET activo = 0 WHERE id = ?')->execute([$activo['gasto_recurrente_id']]);
        }
    }

    public function eliminar(int $id): void
    {
        $activo = $this->findById($id);
        if ($activo && $activo['gasto_recurrente_id']) {
            $this->db->prepare('UPDATE gastos_recurrentes SET activo = 0 WHERE id = ?')->execute([$activo['gasto_recurrente_id']]);
        }
        $this->softDelete($id);
    }

    /**
     * Crea activos fijos "sueltos" (texto libre — nombre + valor, sin ítem de catálogo
     * vinculado: una cafetera nueva no es una materia prima) que se separaron de una compra
     * al momento de cargarla — ver ComprasController::guardar()/actualizar() (tilde "Es un
     * activo fijo" por ítem) y ChatController::toolRegistrarCompra() (tipo_item=activo_fijo
     * dentro de items[]), los DOS únicos llamadores — comparten esta única implementación en
     * vez de cada uno tener la suya, para no repetir la lógica ni arriesgarse a que diverjan.
     * $facturaCompraId ya identifica la factura (cabecera) de la que salió cada uno — se
     * guarda como link real en vez de la nota de texto que se usaba antes.
     * Un error acá no debe tirar abajo el guardado de la factura (ya se guardó bien): se
     * devuelve como advertencia para que el usuario lo cargue a mano desde Activos Fijos.
     */
    public function crearActivosSueltos(array $activosFijos, ?int $proveedorId, string $fecha, ?int $facturaCompraId, ?string $nroComprobante = null): array
    {
        if (!$activosFijos) return [];

        $advertencias = [];
        foreach ($activosFijos as $a) {
            try {
                $this->crear([
                    'nombre'            => $a['nombre'],
                    'categoria'         => null,
                    'origen'            => 'compra_nueva',
                    'fecha_alta'        => $fecha,
                    'valor'             => $a['valor'],
                    'vida_util_meses'   => null,
                    'proveedor_id'      => $proveedorId ?: null,
                    'factura_compra_id' => $facturaCompraId,
                    'estado'            => 'activo',
                    'notas'             => 'Cargado desde factura de compra' . ($nroComprobante ? " #{$nroComprobante}" : '') . '.',
                    'usuario_id'        => Session::user()['id'],
                    'eliminado'         => 0,
                ]);
            } catch (\Throwable $e) {
                $advertencias[] = "No se pudo cargar el activo fijo \"{$a['nombre']}\": {$e->getMessage()}. Cargalo manualmente desde Activos Fijos.";
            }
        }
        return $advertencias;
    }

    /**
     * Convierte una línea YA CARGADA de una factura de compra (materia_prima/insumo/producto
     * con stock/costo real) en un activo fijo — el pedido de "vincular un producto de una
     * compra ya realizada" desde /activos/nuevo. A diferencia de crearActivosSueltos() (que
     * nunca tocó ningún ítem de catálogo), acá SÍ hay stock que revertir: se reusa
     * FacturaCompraModel::actualizarFactura() pasándole el detalle actual de la factura SIN
     * esta línea — ese método ya revierte el stock de TODAS las líneas viejas y reaplica
     * únicamente las que quedan, así que la línea excluida termina con su stock/costo
     * correctamente revertido y las demás no sufren ningún cambio neto (se revierten y se
     * reaplican igual). El id de la LÍNEA no se guarda (se borra en el mismo paso) — se
     * guarda el id de la FACTURA (cabecera), que nunca se borra físicamente.
     */
    public function crearDesdeLineaCompra(int $facturaCompraDetalleId, array $datosActivo): int
    {
        require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
        $facturaModel = new FacturaCompraModel();

        $linea = $this->queryOne('SELECT * FROM facturas_compra_detalle WHERE id = ?', [$facturaCompraDetalleId]);
        if (!$linea) throw new \RuntimeException('La línea de compra elegida ya no existe.');

        $facturaCompraId = (int)$linea['factura_compra_id'];
        $factura = $facturaModel->findById($facturaCompraId);
        if (!$factura) throw new \RuntimeException('No se encontró la factura de esa línea.');

        $detalleActual = $this->queryAll('SELECT * FROM facturas_compra_detalle WHERE factura_compra_id = ?', [$facturaCompraId]);
        $detalleSinEsaLinea = array_values(array_filter($detalleActual, fn($d) => (int)$d['id'] !== $facturaCompraDetalleId));

        // OJO: sin transacción propia acá a propósito — tanto actualizarFactura() como
        // crear() (llamados abajo) ya abren y cierran SU PROPIA transacción por separado, y
        // PDO no soporta transacciones anidadas de verdad en este proyecto (sin savepoints)
        // — envolver esto en una tercera transacción tira "ya hay una transacción activa" en
        // cuanto entra a la primera de las dos. Si algo falla DESPUÉS de que
        // actualizarFactura() ya confirmó (revirtió el stock y sacó la línea), pero antes de
        // crear el activo, queda un estado intermedio real: la línea ya no está en la
        // factura pero el activo fijo todavía no existe. Se avisa explícito en el mensaje de
        // error para que quede claro qué revisar a mano — mismo criterio tolerante que ya usa
        // crearActivosSueltos() para sus propios fallos parciales.
        try {
            // actualizarFactura() reescribe el detalle completo de la factura (revierte todo
            // el stock viejo y reaplica solo lo que le llega) — pasándole todo MENOS esta
            // línea, queda excluida y su stock correctamente revertido.
            $facturaModel->actualizarFactura($facturaCompraId, [
                'proveedor_id'     => $factura['proveedor_id'],
                'fecha'            => $factura['fecha'],
                'tipo_comprobante' => $factura['tipo_comprobante'],
                'nro_comprobante'  => $factura['nro_comprobante'],
                'condicion_pago'   => $factura['condicion_pago'],
                'subtotal'         => (float)$factura['subtotal'] - (float)$linea['subtotal'],
                'total_iva'        => (float)$factura['total_iva'] - ((float)$linea['subtotal_con_iva'] - (float)$linea['subtotal'] - (float)($linea['monto_no_gravado'] ?? 0)),
                'total_con_iva'    => (float)$factura['total_con_iva'], // no cambia: el importe se corre a monto_activos_fijos, sigue contando en el total que se le debe al proveedor
                'monto_activos_fijos' => (float)($factura['monto_activos_fijos'] ?? 0) + (float)$linea['subtotal_con_iva'],
                'estado'           => $factura['estado'],
                'notas'            => $factura['notas'],
                'eliminado'        => 0,
            ], $detalleSinEsaLinea);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo actualizar la factura de origen: ' . $e->getMessage());
        }

        try {
            return $this->crear($datosActivo + [
                'origen'            => 'compra_nueva',
                'factura_compra_id' => $facturaCompraId,
                'proveedor_id'      => $datosActivo['proveedor_id'] ?? $factura['proveedor_id'],
                'estado'            => 'activo',
                'notas'             => $datosActivo['notas'] ?? ('Convertido desde la factura de compra' . ($factura['nro_comprobante'] ? " #{$factura['nro_comprobante']}" : '') . '.'),
                'eliminado'         => 0,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'La línea ya se sacó de la factura #' . $facturaCompraId . ' (stock revertido), pero no se pudo crear el activo fijo: '
                . $e->getMessage() . '. Cargalo a mano desde Activos Fijos con origen "preexistente".'
            );
        }
    }
}
