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
        ) ?: null;
    }

    /** Hay al menos un turno de caja abierto ahora mismo (de cualquier usuario) — usado por el Portal de Clientes Mayoristas para avisar si el negocio está cerrado. */
    public function hayTurnoAbierto(): bool
    {
        return (bool)$this->queryScalar("SELECT COUNT(*) FROM turnos WHERE estado='abierto' AND eliminado=0");
    }

    /**
     * Apertura del turno abierto MÁS VIEJO, o null si no hay ninguno — es el `caja.desde`
     * que informa GET /api/menuapp/v1/estado (ver docs/integracion-menuapp-checklist.md,
     * sección 3.0). Se toma el más viejo y no el último porque lo que le interesa a MenuApp
     * es desde cuándo el comercio está tomando pedidos sin interrupción.
     */
    public function aperturaDelTurnoAbiertoMasViejo(): ?string
    {
        $v = $this->queryScalar(
            "SELECT apertura_at FROM turnos WHERE estado='abierto' AND eliminado=0 ORDER BY apertura_at LIMIT 1"
        );
        return $v !== false && $v !== null ? (string)$v : null;
    }

    /** Cuántos turnos hay abiertos ahora mismo (varios cajeros pueden tener el suyo a la vez). */
    public function cantidadTurnosAbiertos(): int
    {
        return (int)$this->queryScalar("SELECT COUNT(*) FROM turnos WHERE estado='abierto' AND eliminado=0");
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

    /**
     * Calcula lo que daría un cierre con estos montos declarados, SIN escribir nada en la
     * base — para poder mostrarle el resultado al usuario y dejarlo cancelar antes de
     * comprometer el cierre de verdad. cerrar() reusa este mismo cálculo.
     */
    public function previsualizar(int $turnoId, float $efectivoDeclarado, float $mpDeclarado): ?array
    {
        $turno = $this->findById($turnoId);
        if (!$turno || $turno['estado'] !== 'abierto') return null;

        $esperado           = $this->calcularEsperado($turnoId, (float)$turno['monto_inicial']);
        $diferenciaEfectivo = round($efectivoDeclarado - $esperado['efectivo'], 2);
        $diferenciaMp       = round($mpDeclarado - $esperado['mercadopago'], 2);

        return [
            'esperado'            => $esperado,
            'diferencia_efectivo' => $diferenciaEfectivo,
            'diferencia_mp'       => $diferenciaMp,
            'tiene_diferencia'    => abs($diferenciaEfectivo) >= 0.01 || abs($diferenciaMp) >= 0.01,
        ];
    }

    /**
     * Cierre con desglose por medio de pago: efectivo y Mercado Pago se declaran
     * y validan por separado (los otros medios -débito/crédito/transferencia- no
     * se cuentan físicamente, solo se informan). $cierreCiego indica si quien
     * cierra no tenía permiso 'editar' en caja (no vio el esperado antes de declarar).
     */
    public function cerrar(int $turnoId, float $efectivoDeclarado, float $mpDeclarado, string $obs, bool $cierreCiego): ?array
    {
        $preview = $this->previsualizar($turnoId, $efectivoDeclarado, $mpDeclarado);
        if (!$preview) return null;

        $esperado           = $preview['esperado'];
        $diferenciaEfectivo = $preview['diferencia_efectivo'];
        $diferenciaMp       = $preview['diferencia_mp'];

        $this->update($turnoId, [
            'estado'                          => 'cerrado',
            'monto_cierre_declarado'          => round($efectivoDeclarado + $mpDeclarado, 2),
            'monto_cierre_calculado'          => round($esperado['efectivo'] + $esperado['mercadopago'], 2),
            'diferencia'                      => round($diferenciaEfectivo + $diferenciaMp, 2),
            'monto_cierre_declarado_efectivo' => $efectivoDeclarado,
            'monto_cierre_declarado_mp'       => $mpDeclarado,
            'monto_cierre_calculado_efectivo' => $esperado['efectivo'],
            'monto_cierre_calculado_mp'       => $esperado['mercadopago'],
            'diferencia_efectivo'             => $diferenciaEfectivo,
            'diferencia_mp'                   => $diferenciaMp,
            'cierre_ciego'                    => $cierreCiego ? 1 : 0,
            'observaciones'                   => $obs ?: null,
            'cierre_at'                       => date('Y-m-d H:i:s'),
        ]);

        return [
            'diferencia_efectivo' => $diferenciaEfectivo,
            'diferencia_mp'       => $diferenciaMp,
            'tiene_diferencia'    => $preview['tiene_diferencia'],
        ];
    }

    /** Monto esperado en caja por medio de pago, a partir de los movimientos del turno. */
    private function calcularEsperado(int $turnoId, float $montoInicial): array
    {
        // Si el ingreso viene de una venta que después se anuló, no cuenta como esperado — la
        // plata nunca quedó realmente en caja (o si quedó, el cajero tiene que devolverla).
        // Antes esto no se filtraba acá: anular una venta mal cargada y recargarla bien no
        // arreglaba el descuadre, lo empeoraba (quedaba contando las dos).
        $filas = $this->queryAll(
            "SELECT COALESCE(cm.metodo_pago,'efectivo') AS medio, SUM(cm.monto) AS total
             FROM caja_movimientos cm
             LEFT JOIN ventas v ON v.id = cm.venta_id
             WHERE cm.turno_id=:tid AND cm.tipo='ingreso'
               AND (v.id IS NULL OR v.estado <> 'anulada')
             GROUP BY COALESCE(cm.metodo_pago,'efectivo')",
            [':tid' => $turnoId]
        );
        $porMedio = array_column($filas, 'total', 'medio');

        // Los movimientos manuales (ingreso/egreso sin venta) son siempre efectivo físico.
        $egresos = (float)$this->queryScalar(
            "SELECT COALESCE(SUM(monto),0) FROM caja_movimientos WHERE turno_id=:tid AND tipo='egreso'",
            [':tid' => $turnoId]
        );

        return [
            'efectivo'      => round($montoInicial + (float)($porMedio['efectivo'] ?? 0) - $egresos, 2),
            'mercadopago'   => round((float)($porMedio['mercadopago'] ?? 0), 2),
            'debito'        => round((float)($porMedio['debito'] ?? 0), 2),
            'credito'       => round((float)($porMedio['credito'] ?? 0), 2),
            'transferencia' => round((float)($porMedio['transferencia'] ?? 0), 2),
            'total_egresos' => round($egresos, 2),
        ];
    }

    public function registrarMovimiento(array $data): void
    {
        $this->query(
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
            "SELECT cm.*, u.nombre AS usuario_nombre, v.estado AS venta_estado
             FROM caja_movimientos cm
             LEFT JOIN usuarios u ON u.id=cm.usuario_id
             LEFT JOIN ventas   v ON v.id=cm.venta_id
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

        // Mismo criterio que calcularEsperado(): un ingreso de una venta anulada no cuenta
        // como plata que realmente entró (o si entró, hay que devolverla) — antes este total
        // sí la contaba, así que "Total ingresos" quedaba más alto que "Efectivo esperado"
        // apenas se anulaba alguna venta del turno, aunque las dos cuentas fueran correctas
        // por separado.
        $turno['total_ingresos'] = array_sum(array_column(array_filter(
            $turno['movimientos'], fn($m) => $m['tipo'] === 'ingreso' && $m['venta_estado'] !== 'anulada'
        ), 'monto'));
        $turno['total_egresos'] = array_sum(
            array_column(array_filter($turno['movimientos'], fn($m) => $m['tipo'] === 'egreso'), 'monto')
        );

        $turno['esperado']        = $this->calcularEsperado($turnoId, (float)$turno['monto_inicial']);
        $turno['saldo_calculado'] = $turno['esperado']['efectivo'];
        $turno['resumen_ventas']  = $this->resumenVentas($turnoId);

        return $turno;
    }

    /** Cantidad y monto de ventas del turno, completadas vs. anuladas (para el ticket de cierre). */
    private function resumenVentas(int $turnoId): array
    {
        $filas = $this->queryAll(
            "SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(total_con_iva),0) AS total
             FROM ventas WHERE turno_id=:tid GROUP BY estado",
            [':tid' => $turnoId]
        );
        $porEstado = array_column($filas, null, 'estado');

        return [
            'completadas' => [
                'cantidad' => (int)($porEstado['completada']['cantidad'] ?? 0),
                'total'    => (float)($porEstado['completada']['total'] ?? 0),
            ],
            'anuladas' => [
                'cantidad' => (int)($porEstado['anulada']['cantidad'] ?? 0),
                'total'    => (float)($porEstado['anulada']['total'] ?? 0),
            ],
        ];
    }

    /**
     * $usuarioIdFiltro (permiso 'ventas_ver_todas', mismo que gatea el listado de Ventas):
     * si viene, solo trae los turnos abiertos por ese usuario — para que un cajero sin ese
     * permiso no vea las cajas/turnos de otros.
     */
    public function historial(int $limite = 30, ?int $usuarioIdFiltro = null): array
    {
        $params = [':lim' => $limite];
        $condUsuario = '';
        if ($usuarioIdFiltro !== null) {
            $condUsuario = ' AND t.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioIdFiltro;
        }

        return $this->queryAll(
            "SELECT t.*, u.nombre AS usuario_nombre,
                    (SELECT COUNT(*) FROM caja_movimientos WHERE turno_id=t.id AND tipo='ingreso') AS cant_ingresos,
                    COALESCE(t.monto_cierre_calculado, 0) AS total_calculado
             FROM turnos t
             LEFT JOIN usuarios u ON u.id=t.usuario_id
             WHERE t.eliminado=0{$condUsuario}
             ORDER BY t.id DESC
             LIMIT :lim",
            $params
        );
    }

    /**
     * Diferencia de caja acumulada por usuario (para detectar quién concentra más
     * faltantes/sobrantes en el tiempo). Se suma en valor absoluto para que un
     * faltante y un sobrante no se cancelen entre sí y escondan al usuario más
     * problemático.
     */
    public function diferenciasPorUsuario(?string $desde = null, ?string $hasta = null): array
    {
        $where  = ["t.estado='cerrado'", "t.eliminado=0"];
        $params = [];
        if ($desde) { $where[] = 'DATE(t.cierre_at) >= :desde'; $params[':desde'] = $desde; }
        if ($hasta) { $where[] = 'DATE(t.cierre_at) <= :hasta'; $params[':hasta'] = $hasta; }

        return $this->queryAll(
            "SELECT u.id AS usuario_id, u.nombre AS usuario_nombre,
                    COUNT(*) AS total_cierres,
                    SUM(CASE WHEN ABS(t.diferencia_efectivo)>=0.01 OR ABS(t.diferencia_mp)>=0.01 THEN 1 ELSE 0 END) AS cierres_con_diferencia,
                    COALESCE(SUM(t.diferencia_efectivo),0) AS diferencia_efectivo_total,
                    COALESCE(SUM(t.diferencia_mp),0)       AS diferencia_mp_total,
                    COALESCE(SUM(ABS(t.diferencia_efectivo)+ABS(t.diferencia_mp)),0) AS impacto_absoluto_total,
                    SUM(t.cierre_ciego) AS cierres_ciegos
             FROM turnos t
             JOIN usuarios u ON u.id = t.usuario_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.id, u.nombre
             ORDER BY impacto_absoluto_total DESC",
            $params
        );
    }

    /**
     * Posibles causas de la diferencia de un turno ya cerrado, a partir de datos
     * ya existentes (ventas anuladas, precios de lista, movimientos manuales).
     * Devuelve una lista de hallazgos ordenada por confianza (alta > media > baja).
     */
    public function diagnosticar(int $turnoId, ?float $diferenciaEfectivo = null, ?float $diferenciaMp = null): array
    {
        $turno = $this->findById($turnoId);
        if (!$turno) return [];

        // Antes de comprometer el cierre (previsualización) las diferencias todavía no están
        // guardadas en la fila del turno — se pasan por parámetro. Una vez cerrado, se puede
        // llamar sin ellas y se leen del turno ya guardado.
        $diferenciaEfectivo ??= (float) $turno['diferencia_efectivo'];
        $diferenciaMp       ??= (float) $turno['diferencia_mp'];
        if (abs($diferenciaEfectivo) < 0.01 && abs($diferenciaMp) < 0.01) return [];

        $hallazgos = [];

        // H1: ventas anuladas cuyo ingreso de caja nunca se reversó.
        $anuladas = $this->queryAll(
            "SELECT v.id, v.total_con_iva, v.metodo_pago, v.created_at
             FROM ventas v
             JOIN caja_movimientos cm ON cm.venta_id = v.id AND cm.tipo='ingreso'
             WHERE v.turno_id=:tid AND v.estado='anulada'",
            [':tid' => $turnoId]
        );
        foreach (['efectivo' => $diferenciaEfectivo, 'mercadopago' => $diferenciaMp] as $medio => $dif) {
            $delMedio = array_values(array_filter($anuladas, fn($v) => $v['metodo_pago'] === $medio));
            $totalAnuladasMedio = array_sum(array_column($delMedio, 'total_con_iva'));
            if ($totalAnuladasMedio > 0 && $dif < -0.01) {
                $hallazgos[] = [
                    'tipo'              => 'venta_anulada_no_reversada',
                    'medio'             => $medio,
                    'confianza'         => abs(abs($dif) - $totalAnuladasMedio) < 1 ? 'alta' : 'media',
                    'mensaje'           => 'Hay ventas anuladas en este turno por ' . moneda($totalAnuladasMedio)
                                          . " en $medio cuyo movimiento de caja no se revirtió automáticamente. "
                                          . 'Esto infla el monto esperado y puede explicar el faltante.',
                    'monto_relacionado' => $totalAnuladasMedio,
                    'ventas_relacionadas' => array_map(fn($v) => [
                        'id'    => (int) $v['id'],
                        'total' => (float) $v['total_con_iva'],
                        'fecha' => $v['created_at'],
                    ], $delMedio),
                ];
            }
        }

        // H2: ventas cargadas a un precio distinto del precio de lista vigente del producto.
        $precios = $this->queryAll(
            "SELECT v.id AS venta_id, v.metodo_pago, v.created_at,
                    (p.precio_venta - vd.precio_unitario) * vd.cantidad AS delta
             FROM ventas v
             JOIN ventas_detalle vd ON vd.venta_id = v.id
             JOIN productos p ON p.id = vd.producto_id
             WHERE v.turno_id=:tid AND v.estado='completada'
               AND ABS(vd.precio_unitario - p.precio_venta) > 0.01",
            [':tid' => $turnoId]
        );
        if ($precios) {
            $preciosEfectivo = array_values(array_filter($precios, fn($p) => $p['metodo_pago'] === 'efectivo'));
            $deltaEfectivo   = array_sum(array_column($preciosEfectivo, 'delta'));
            if (abs($deltaEfectivo) > 0.01) {
                // Puede haber más de una línea por venta — no repetir la misma venta en la lista.
                $ventasUnicas = [];
                foreach ($preciosEfectivo as $p) {
                    $ventasUnicas[$p['venta_id']] ??= ['id' => (int) $p['venta_id'], 'fecha' => $p['created_at']];
                }
                $hallazgos[] = [
                    'tipo'              => 'precio_desactualizado',
                    'medio'             => 'efectivo',
                    'confianza'         => abs(abs($diferenciaEfectivo) - abs($deltaEfectivo)) < 1 ? 'alta' : 'baja',
                    'mensaje'           => count($precios) . ' línea(s) de venta se cargaron con un precio distinto al vigente del producto ('
                                          . moneda(abs($deltaEfectivo)) . ' de diferencia potencial). Revisar si se cobró el precio correcto.',
                    'monto_relacionado' => abs($deltaEfectivo),
                    'ventas_relacionadas' => array_values($ventasUnicas),
                ];
            }
        }

        // H3: movimiento manual (no ligado a venta) cuyo monto coincide o está cerca (±5%) de la diferencia.
        if (abs($diferenciaEfectivo) >= 0.01) {
            $candidatos = $this->queryAll(
                "SELECT id, tipo, monto, descripcion, created_at
                 FROM caja_movimientos
                 WHERE turno_id=:tid AND venta_id IS NULL AND tipo IN ('ingreso','egreso')
                   AND ABS(monto - :abs_dif) <= GREATEST(:abs_dif2 * 0.05, 1)",
                [':tid' => $turnoId, ':abs_dif' => abs($diferenciaEfectivo), ':abs_dif2' => abs($diferenciaEfectivo)]
            );
            foreach ($candidatos as $c) {
                $hallazgos[] = [
                    'tipo'              => 'movimiento_manual_coincidente',
                    'medio'             => 'efectivo',
                    'confianza'         => abs((float)$c['monto'] - abs($diferenciaEfectivo)) < 0.01 ? 'alta' : 'media',
                    'mensaje'           => ucfirst($c['tipo']) . ' manual de ' . moneda($c['monto'])
                                          . ' ("' . ($c['descripcion'] ?: 'sin descripción') . '") registrado a las '
                                          . date('H:i', strtotime($c['created_at']))
                                          . ' coincide con el monto de la diferencia. Verificar si realmente entró/salió ese efectivo del cajón.',
                    'monto_relacionado' => (float)$c['monto'],
                ];
            }
        }

        // H4: el monto inicial de este turno no coincide con lo declarado al cerrar el turno anterior.
        $turnoAnterior = $this->queryOne(
            "SELECT id, numero, monto_cierre_declarado_efectivo
             FROM turnos
             WHERE estado='cerrado' AND eliminado=0 AND cierre_at <= :apertura AND id <> :tid
             ORDER BY cierre_at DESC LIMIT 1",
            [':apertura' => $turno['apertura_at'], ':tid' => $turnoId]
        );
        if ($turnoAnterior && $turnoAnterior['monto_cierre_declarado_efectivo'] !== null) {
            $delta = round((float)$turno['monto_inicial'] - (float)$turnoAnterior['monto_cierre_declarado_efectivo'], 2);
            if (abs($delta) > 0.01) {
                $hallazgos[] = [
                    'tipo'              => 'apertura_no_coincide_cierre_anterior',
                    'medio'             => 'efectivo',
                    'confianza'         => abs(abs($diferenciaEfectivo) - abs($delta)) < 1 ? 'alta' : 'media',
                    'mensaje'           => 'El monto inicial de este turno (' . moneda($turno['monto_inicial'])
                                          . ') no coincide con el efectivo declarado al cerrar el turno #' . $turnoAnterior['numero']
                                          . ' (' . moneda($turnoAnterior['monto_cierre_declarado_efectivo']) . '). '
                                          . 'La diferencia pudo arrastrarse desde la apertura.',
                    'monto_relacionado' => abs($delta),
                ];
            }
        }

        // H5: egresos manuales sin descripción (informativa, no explica el monto, solo alerta de control interno).
        $sinDescripcion = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM caja_movimientos WHERE turno_id=:tid AND tipo='egreso' AND (descripcion IS NULL OR descripcion='')",
            [':tid' => $turnoId]
        );
        if ($sinDescripcion > 0) {
            $hallazgos[] = [
                'tipo'              => 'egresos_sin_documentar',
                'medio'             => 'efectivo',
                'confianza'         => 'baja',
                'mensaje'           => "$sinDescripcion egreso(s) manual(es) de este turno no tienen descripción cargada. Revisarlos para descartar errores de carga.",
                'monto_relacionado' => null,
            ];
        }

        $orden = ['alta' => 0, 'media' => 1, 'baja' => 2];
        usort($hallazgos, fn($a, $b) => $orden[$a['confianza']] <=> $orden[$b['confianza']]);

        return $hallazgos;
    }
}
