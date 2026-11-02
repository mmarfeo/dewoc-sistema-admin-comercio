<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/CobroRecibidoModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

/**
 * Transferencias recibidas por Dewoc Cobros, del lado de las ventas (ver
 * docs/dewoc-cobros-checklist.md, etapa 3, y la revisión del 2026-11-02 (4)):
 *
 * - El aviso del POS cuando llega un cobro (nuevos()).
 * - El pop-up de cada hora en el POS con las transferencias del turno abierto que no se
 *   pudieron validar solas (pendientesTurno()), para que el cajero las asigne a la venta
 *   correcta cuando tenga tiempo.
 * - La pantalla Ventas → Transferencias recibidas (index()), para relacionar transferencias y
 *   ventas de cualquier día.
 *
 * Quién puede asignar: el cajero desde el POS, solo transferencias y ventas de SU turno
 * abierto; quien tiene permiso de editar ventas, cualquiera (y además puede corregir el
 * medio de pago de la venta, como en el listado de ventas).
 */
class CobrosRecibidosController extends Controller
{
    const MODULO = 'dewoc_cobros';

    private CobroRecibidoModel $model;

    public function __construct()
    {
        $this->model = new CobroRecibidoModel();
    }

    // ── POS ──────────────────────────────────────────────────────────────────

    /**
     * GET cobros-recibidos/nuevos?desde_id=N — los cobros que llegaron después del último
     * que vio este POS. Se llama cada 5 segundos, así que es una consulta con índice.
     */
    public function nuevos(): void
    {
        $this->requireCanView('ventas_pos');
        // Con sesiones en archivo, una consulta cada 5 s quedaría trabada detrás de cualquier
        // request lento del mismo usuario (por ejemplo AFIP), y al revés.
        session_write_close();
        $this->requireModuloHabilitado();

        $r = $this->model->nuevosParaPos(max(0, (int)$this->param('desde_id', 0)));
        $this->json([
            'ok'        => true,
            'ultimo_id' => $r['ultimo_id'],
            'cobros'    => array_map(fn($c) => [
                'id'     => (int)$c['id'],
                'monto'  => $c['monto'] !== null ? (float)$c['monto'] : null,
                'nombre' => $c['nombre'],
                'app'    => $c['app'],
                'hora'   => date('H:i', strtotime((string)$c['recibido_at'])),
            ], $r['cobros']),
        ]);
    }

    /**
     * GET cobros-recibidos/pendientes-turno — transferencias sin asignar recibidas desde que
     * abrió el turno del cajero, cada una con las ventas de ese turno a las que se puede
     * asignar. Sin turno abierto no hay nada (el del turno siguiente no ve las de antes).
     */
    public function pendientesTurno(): void
    {
        $this->requireCanView('ventas_pos');
        $usuarioId = (int)Session::user()['id'];
        session_write_close();
        $this->requireModuloHabilitado();

        $turno = (new VentaModel())->getTurnoAbierto($usuarioId);
        if (!$turno) $this->json(['ok' => true, 'turno_id' => null, 'cobros' => []]);

        $cobros = [];
        foreach ($this->model->pendientesDesde((string)$turno['apertura_at']) as $c) {
            $cobros[] = $this->cobroJson($c) + [
                'ventas' => array_map([$this, 'ventaJson'], $this->model->ventasCandidatas($c, (int)$turno['id'])),
            ];
        }

        $this->json([
            'ok'                  => true,
            'turno_id'            => (int)$turno['id'],
            'cobros'              => $cobros,
            'puede_cambiar_medio' => canEdit('ventas'),
        ]);
    }

    // ── Ventas → Transferencias recibidas ───────────────────────────────────

    public function index(): void
    {
        $this->requireCanView('ventas');
        if (moduloEstado(self::MODULO)['estado'] === 'sin_licencia') redirect('integraciones/dewoc-cobros');

        $filtros = [
            'desde'  => $this->param('desde') ?: date('Y-m-d'),
            'hasta'  => $this->param('hasta') ?: date('Y-m-d'),
            'estado' => (string)$this->param('estado', 'pendientes'),
        ];
        if (!in_array($filtros['estado'], ['', 'pendientes', 'asignadas', 'no_corresponde'], true)) $filtros['estado'] = 'pendientes';

        // Todas las del período (para los totales por estado) y, de esas, las del filtro.
        $todas = $this->model->listarTransferencias($filtros['desde'], $filtros['hasta']);
        foreach ($todas as &$t) {
            $t['estado'] = $t['ignorado_at'] ? 'no_corresponde' : ($t['venta_id'] ? 'asignadas' : 'pendientes');
        }
        unset($t);

        $this->render('ventas.transferencias', [
            'pageTitle'     => 'Transferencias recibidas',
            'breadcrumb'    => ['Ventas' => 'ventas', 'Transferencias recibidas' => null],
            'filtros'       => $filtros,
            'todas'         => $todas,
            'transferencias'=> $filtros['estado'] === '' ? $todas
                : array_values(array_filter($todas, fn($t) => $t['estado'] === $filtros['estado'])),
            'puedeEditar'   => canEdit('ventas'),
            'habilitado'    => moduloHabilitado(self::MODULO),
        ]);
    }

    /** GET cobros-recibidos/candidatas?cobro_id=N — ventas a las que se puede asignar (pantalla). */
    public function candidatas(): void
    {
        $this->requireCanEdit('ventas');
        $c = $this->model->porId((int)$this->param('cobro_id', 0));
        if (!$c || $c['resultado'] !== 'cobro') $this->json(['ok' => false, 'error' => 'Esa transferencia no existe.'], 404);

        $this->json([
            'ok'     => true,
            'cobro'  => $this->cobroJson($c),
            'ventas' => array_map([$this, 'ventaJson'], $this->model->ventasCandidatas($c)),
        ]);
    }

    // ── Acciones ─────────────────────────────────────────────────────────────

    /**
     * POST cobros-recibidos/asignar (cobro_id, venta_id[, cambiar_medio]) — asigna la
     * transferencia a la venta. Con cambiar_medio = transferencia | mercadopago y permiso de
     * editar ventas, corrige además el medio de pago de una venta registrada con otro medio
     * (ej. se cobró por transferencia y se cargó como efectivo).
     */
    public function asignar(): void
    {
        $this->requireAuth();
        Csrf::check();
        $this->requireModuloHabilitado();

        $cobroId = (int)$this->input('cobro_id', 0);
        $ventaId = (int)$this->input('venta_id', 0);
        $this->requirePuedeOperar($cobroId, $ventaId);

        $usuarioId = (int)Session::user()['id'];
        $error = $this->model->asignar($cobroId, $ventaId, $usuarioId);
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $mensaje = "Transferencia asignada a la venta #{$ventaId}.";
        $medio   = (string)$this->input('cambiar_medio', '');
        if (in_array($medio, ['transferencia', 'mercadopago'], true) && canEdit('ventas')) {
            $venta = (new VentaModel())->findById($ventaId);
            if ($venta && $venta['metodo_pago'] !== $medio && in_array($venta['metodo_pago'], VentaModel::MEDIOS_PAGO_SIMPLES, true)) {
                try {
                    if ((new VentaModel())->cambiarMedioPago($ventaId, $medio)) {
                        AuditLog::log('cambiar_medio_pago', 'ventas', "Venta #{$ventaId} — medio de pago cambiado a {$medio} al asignarle la transferencia #{$cobroId}");
                        $mensaje .= ' Medio de pago corregido a ' . ($medio === 'transferencia' ? 'Transferencia' : 'Mercado Pago') . '.';
                    }
                } catch (\Throwable $e) {
                    $mensaje .= ' No se pudo cambiar el medio de pago: ' . $e->getMessage();
                }
            }
        }

        AuditLog::log('asignar_transferencia', 'ventas', "Transferencia #{$cobroId} asignada a la venta #{$ventaId}");
        $this->json(['ok' => true, 'mensaje' => $mensaje]);
    }

    /** POST cobros-recibidos/no-corresponde (cobro_id, motivo) — deja de figurar como pendiente. */
    public function noCorresponde(): void
    {
        $this->requireAuth();
        Csrf::check();
        $this->requireModuloHabilitado();

        $cobroId = (int)$this->input('cobro_id', 0);
        $this->requirePuedeOperar($cobroId, null);

        $motivo = trim((string)$this->input('motivo', ''));
        if ($motivo === '') $this->json(['ok' => false, 'error' => 'Escribí el motivo (por ejemplo: seña, devolución, transferencia personal).'], 422);

        if (!$this->model->marcarNoCorresponde($cobroId, (int)Session::user()['id'], $motivo)) {
            $this->json(['ok' => false, 'error' => 'Esa transferencia ya no está pendiente. Actualizá la lista.'], 422);
        }
        AuditLog::log('transferencia_no_corresponde', 'ventas', "Transferencia #{$cobroId}: no corresponde a una venta ({$motivo})");
        $this->json(['ok' => true, 'mensaje' => 'Marcada como "No corresponde a una venta".']);
    }

    /** POST cobros-recibidos/desasignar (cobro_id) — vuelve a quedar pendiente. */
    public function desasignar(): void
    {
        $this->requireCanEdit('ventas');
        Csrf::check();

        $cobroId = (int)$this->input('cobro_id', 0);
        if (!$this->model->desasignar($cobroId)) $this->json(['ok' => false, 'error' => 'Esa transferencia no estaba asignada.'], 422);
        AuditLog::log('desasignar_transferencia', 'ventas', "Transferencia #{$cobroId}: se quitó la venta asignada");
        $this->json(['ok' => true, 'mensaje' => 'La transferencia volvió a quedar pendiente.']);
    }

    /** POST cobros-recibidos/quitar-no-corresponde (cobro_id) — vuelve a quedar pendiente. */
    public function quitarNoCorresponde(): void
    {
        $this->requireCanEdit('ventas');
        Csrf::check();

        $cobroId = (int)$this->input('cobro_id', 0);
        if (!$this->model->quitarNoCorresponde($cobroId)) $this->json(['ok' => false, 'error' => 'Esa transferencia no estaba marcada.'], 422);
        AuditLog::log('transferencia_no_corresponde', 'ventas', "Transferencia #{$cobroId}: se quitó la marca \"No corresponde\"");
        $this->json(['ok' => true, 'mensaje' => 'La transferencia volvió a quedar pendiente.']);
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    private function requireModuloHabilitado(): void
    {
        if (!moduloHabilitado(self::MODULO)) $this->json(['ok' => false, 'error' => 'modulo_no_habilitado'], 403);
    }

    /**
     * Con permiso de editar ventas, cualquier transferencia y venta. Si no, el cajero desde el
     * POS: solo transferencias recibidas desde que abrió su turno y ventas de ese turno.
     */
    private function requirePuedeOperar(int $cobroId, ?int $ventaId): void
    {
        if (canEdit('ventas')) return;
        if (!canView('ventas_pos')) $this->json(['ok' => false, 'error' => 'No tenés permiso para asignar transferencias.'], 403);

        $turno = (new VentaModel())->getTurnoAbierto((int)Session::user()['id']);
        $cobro = $this->model->porId($cobroId);
        $fuera = !$turno || !$cobro || (string)$cobro['recibido_at'] < (string)$turno['apertura_at'];
        if (!$fuera && $ventaId !== null) {
            $venta = (new VentaModel())->findById($ventaId);
            $fuera = !$venta || (int)$venta['turno_id'] !== (int)$turno['id'];
        }
        if ($fuera) {
            $this->json(['ok' => false, 'error' => 'Desde el POS solo se asignan transferencias y ventas de tu turno abierto.'], 403);
        }
    }

    private function cobroJson(array $c): array
    {
        return [
            'id'     => (int)$c['id'],
            'monto'  => $c['monto'] !== null ? (float)$c['monto'] : null,
            'nombre' => $c['nombre'],
            'app'    => $c['app'] ?? null,
            'hora'   => date('H:i', strtotime((string)$c['recibido_at'])),
            'fecha'  => date('d/m/Y', strtotime((string)$c['recibido_at'])),
        ];
    }

    private function ventaJson(array $v): array
    {
        return [
            'id'          => (int)$v['id'],
            'hora'        => date('H:i', strtotime((string)$v['created_at'])),
            'fecha'       => date('d/m/Y', strtotime((string)$v['created_at'])),
            'total'       => (float)$v['total_con_iva'],
            'electronico' => (float)$v['electronico'],
            'metodo_pago' => $v['metodo_pago'],
            'mesa'        => $v['mesa'],
            'vendedor'    => $v['usuario_nombre'],
        ];
    }
}
