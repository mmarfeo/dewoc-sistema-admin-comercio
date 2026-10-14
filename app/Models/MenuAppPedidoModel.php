<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Copia local de los pedidos de MenuApp que se atienden desde el POS (ver
 * docs/integracion-menuapp-checklist.md, etapa 9).
 *
 * Los pedidos y las conversaciones VIVEN EN MENUAPP. Esto guarda una copia para poder
 * mostrar la alerta y la lista sin depender de que MenuApp responda en ese instante, y se
 * mantiene al día con las novedades de 3.8. Nada de lo que hay acá decide: cuando el cajero
 * acepta, rechaza, propone o escribe, se le pide a MenuApp (ver core/MenuAppClient.php).
 */
class MenuAppPedidoModel extends Model
{
    protected string $table = 'menuapp_pedidos';

    /** Estados en los que el POS todavía tiene algo que hacer con el pedido. */
    const ESTADOS_REQUIEREN_ACCION = ['esperando_aprobacion'];

    /**
     * Estados en los que el pedido ya está resuelto y no se muestra más en "en curso".
     * Lo manda MenuApp; la lista está acá solo para decidir dónde se muestra cada uno.
     */
    const ESTADOS_TERMINADOS = ['entregado', 'rechazado', 'vencido', 'cancelado'];

    // ── 3.7 · Alta y actualización desde MenuApp ────────────────────────────

    /**
     * Guarda (o actualiza) un pedido que llegó por POST /pedidos. Idempotente por
     * referencia: el mismo pedido puede llegar dos veces si MenuApp reintenta, y no tiene
     * que duplicar la alerta.
     *
     * @return array{id: int, creado: bool}
     */
    public function guardarDesdeMenuApp(string $referencia, array $datos): array
    {
        $existente = $this->findByReferencia($referencia);

        $campos = [
            'codigo'          => isset($datos['codigo']) ? (string)$datos['codigo'] : null,
            'datos'           => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'estado'          => 'esperando_aprobacion',
            'estado_texto'    => 'Esperando aprobación',
            'requiere_accion' => 1,
            'vence_en'        => !empty($datos['vence_en']) ? date('Y-m-d H:i:s', strtotime((string)$datos['vence_en'])) : null,
        ];

        if ($existente) {
            // Reintento: se refresca el contenido pero NO se reabre la alerta si alguien ya
            // lo atendió. Si no, aceptar un pedido y que MenuApp reintentara el aviso lo
            // habría puesto a pedir acción de nuevo.
            unset($campos['estado'], $campos['estado_texto'], $campos['requiere_accion']);
            $this->update((int)$existente['id'], $campos);
            return ['id' => (int)$existente['id'], 'creado' => false];
        }

        $campos['referencia'] = $referencia;
        return ['id' => $this->insert($campos), 'creado' => true];
    }

    // ── 3.8 · Novedades ────────────────────────────────────────────────────

    /**
     * Aplica un cambio de estado. `requiere_accion` se deriva del estado: cualquier estado
     * que no sea "esperando aprobación" significa que el POS ya no tiene que decidir nada,
     * así que la alerta se va sola — incluido el caso de que el pedido se haya aceptado
     * desde MenuApp y no desde acá.
     */
    public function actualizarEstado(string $referencia, string $estado, ?string $estadoTexto = null): bool
    {
        $pedido = $this->findByReferencia($referencia);
        if (!$pedido) return false;

        $this->update((int)$pedido['id'], [
            'estado'          => $estado,
            'estado_texto'    => $estadoTexto,
            'requiere_accion' => in_array($estado, self::ESTADOS_REQUIEREN_ACCION, true) ? 1 : 0,
        ]);
        return true;
    }

    /** Asigna o cambia el repartidor, lo que habilita su conversación en el POS. */
    public function actualizarRepartidor(string $referencia, ?string $repartidor): bool
    {
        $pedido = $this->findByReferencia($referencia);
        if (!$pedido) return false;

        $this->update((int)$pedido['id'], ['repartidor' => $repartidor ?: null]);
        return true;
    }

    /** Suma uno al contador de no leídos de esa conversación. */
    public function sumarMensajeSinLeer(string $referencia, string $conversacion): void
    {
        $this->query(
            "INSERT INTO menuapp_mensajes_no_leidos (referencia, conversacion, cantidad, ultimo_en)
             VALUES (:ref, :conv, 1, NOW())
             ON DUPLICATE KEY UPDATE cantidad = cantidad + 1, ultimo_en = NOW()",
            [':ref' => $referencia, ':conv' => $conversacion]
        );
    }

    /** El cajero abrió la conversación: se pone el contador en cero. */
    public function marcarLeidos(string $referencia, string $conversacion): void
    {
        $this->query(
            "UPDATE menuapp_mensajes_no_leidos SET cantidad = 0
             WHERE referencia = :ref AND conversacion = :conv",
            [':ref' => $referencia, ':conv' => $conversacion]
        );
    }

    // ── Consultas para el POS ──────────────────────────────────────────────

    public function findByReferencia(string $referencia): ?array
    {
        return $this->queryOne(
            "SELECT * FROM menuapp_pedidos WHERE referencia = :ref",
            [':ref' => $referencia]
        ) ?: null;
    }

    /**
     * El pedido más viejo que espera acción y todavía no venció — es lo que dispara la
     * alerta del POS. Mismo criterio que los mayoristas: "al primero que lo atienda", sin
     * asignación por usuario.
     *
     * El filtro por vence_en es la red de seguridad de 3.7: si la novedad de vencimiento no
     * llega, la alerta igual deja de aparecer en vez de quedar trabada para siempre.
     */
    public function pendienteMasViejo(): ?array
    {
        $fila = $this->queryOne(
            "SELECT * FROM menuapp_pedidos
             WHERE requiere_accion = 1
               AND (vence_en IS NULL OR vence_en > NOW())
             ORDER BY created_at
             LIMIT 1"
        );
        return $fila ? $this->hidratar($fila) : null;
    }

    /** Pedidos agrupados como los muestra la lista del POS (tarea 9.6). */
    public function listaParaPos(): array
    {
        $terminados = "'" . implode("','", self::ESTADOS_TERMINADOS) . "'";

        $filas = $this->queryAll(
            "SELECT p.*,
                    COALESCE(nc.cantidad, 0) AS no_leidos_cliente,
                    COALESCE(nr.cantidad, 0) AS no_leidos_repartidor,
                    u.nombre AS atendido_por
             FROM menuapp_pedidos p
             LEFT JOIN menuapp_mensajes_no_leidos nc
                    ON nc.referencia = p.referencia AND nc.conversacion = 'cliente'
             LEFT JOIN menuapp_mensajes_no_leidos nr
                    ON nr.referencia = p.referencia AND nr.conversacion = 'repartidor'
             LEFT JOIN usuarios u ON u.id = p.atendido_por_usuario_id
             WHERE p.requiere_accion = 1
                OR p.estado NOT IN ({$terminados})
                OR DATE(p.created_at) = CURDATE()
             ORDER BY p.requiere_accion DESC, p.created_at DESC"
        );

        $grupos = ['esperando' => [], 'en_curso' => [], 'terminados' => []];
        foreach ($filas as $f) {
            $p = $this->hidratar($f);

            if ((int)$f['requiere_accion'] === 1 && (empty($f['vence_en']) || strtotime($f['vence_en']) > time())) {
                $grupos['esperando'][] = $p;
            } elseif (!in_array($f['estado'], self::ESTADOS_TERMINADOS, true)) {
                $grupos['en_curso'][] = $p;
            } else {
                $grupos['terminados'][] = $p;
            }
        }
        return $grupos;
    }

    /** Pedido con mensajes sin leer más reciente — alimenta el aviso no bloqueante del POS. */
    public function conMensajesSinLeer(): ?array
    {
        $fila = $this->queryOne(
            "SELECT p.*, n.conversacion, n.cantidad
             FROM menuapp_mensajes_no_leidos n
             JOIN menuapp_pedidos p ON p.referencia = n.referencia
             WHERE n.cantidad > 0
             ORDER BY n.ultimo_en DESC
             LIMIT 1"
        );
        if (!$fila) return null;

        $p = $this->hidratar($fila);
        $p['conversacion'] = $fila['conversacion'];
        $p['cantidad']     = (int)$fila['cantidad'];
        return $p;
    }

    public function marcarAtendidoPor(string $referencia, int $usuarioId): void
    {
        $this->query(
            "UPDATE menuapp_pedidos SET atendido_por_usuario_id = :uid WHERE referencia = :ref",
            [':uid' => $usuarioId, ':ref' => $referencia]
        );
    }

    /**
     * Deja los datos listos para el front: el JSON de MenuApp desarmado en `datos` y los
     * contadores como enteros. La vista no tiene que saber que `datos` viene serializado.
     */
    private function hidratar(array $fila): array
    {
        $fila['datos'] = $fila['datos'] ? (json_decode((string)$fila['datos'], true) ?: []) : [];

        $fila['id']                   = (int)$fila['id'];
        $fila['requiere_accion']      = (int)$fila['requiere_accion'] === 1;
        $fila['no_leidos_cliente']    = (int)($fila['no_leidos_cliente'] ?? 0);
        $fila['no_leidos_repartidor'] = (int)($fila['no_leidos_repartidor'] ?? 0);

        // Segundos que faltan para que venza, que es lo que el POS muestra en la cuenta
        // regresiva. Se calcula con la hora de PHP (Argentina) y no con la de MySQL, que
        // puede estar en otro huso — mismo criterio que el resto del sistema.
        $fila['segundos_para_vencer'] = !empty($fila['vence_en'])
            ? max(0, strtotime((string)$fila['vence_en']) - time())
            : null;

        return $fila;
    }
}
