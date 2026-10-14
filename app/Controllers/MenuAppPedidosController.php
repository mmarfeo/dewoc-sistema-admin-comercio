<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/MenuAppPedidoModel.php';
require_once BASE_PATH . '/app/Models/MenuAppCatalogoModel.php';
require_once BASE_PATH . '/core/MenuAppClient.php';
require_once BASE_PATH . '/core/PrecioHelper.php';
require_once BASE_PATH . '/core/AuditLog.php';

/**
 * Lado POS de los pedidos de MenuApp (ver docs/integracion-menuapp-checklist.md, etapa 9,
 * decisiones D14 a D17).
 *
 * Espejo de PedidosClientesController, que hace lo mismo con los pedidos del Portal
 * mayorista: sondeo cada 30 segundos, "al primero que lo atienda" (sin asignación por
 * usuario) y permiso `ventas_pos`, que es el que ya tiene cualquiera que trabaje en la caja.
 * Quien atiende NO necesita un usuario de MenuApp (D16).
 *
 * Diferencia clave con los mayoristas: **Dewoc no decide**. Aceptar, rechazar, proponer y
 * escribir se le piden a MenuApp (core/MenuAppClient.php), que es el dueño del pedido y el
 * que cobra, reintegra, avisa al cliente y registra la venta con POST /ventas. Acá solo se
 * refleja el resultado en la copia local.
 */
class MenuAppPedidosController extends Controller
{
    private MenuAppPedidoModel $model;

    public function __construct()
    {
        $this->model = new MenuAppPedidoModel();
    }

    // ── 9.4 · Sondeo del POS ────────────────────────────────────────────────

    /**
     * Pedido más viejo que espera acción, para la alerta del POS. Mismo contrato que
     * pedidos-clientes/pendiente-para-mi, así el JS del POS los trata igual.
     */
    public function pendienteParaMi(): void
    {
        $this->requireCanView('ventas_pos');
        $this->json(['ok' => true, 'pedido' => $this->model->pendienteMasViejo()]);
    }

    /** Aviso no bloqueante: hay un mensaje sin leer en alguna conversación. */
    public function hayMensajesSinLeer(): void
    {
        $this->requireCanView('ventas_pos');
        $this->json(['ok' => true, 'pedido' => $this->model->conMensajesSinLeer()]);
    }

    // ── 9.6 · Lista de pedidos ──────────────────────────────────────────────

    public function lista(): void
    {
        $this->requireCanView('ventas_pos');
        $this->json(['ok' => true, 'grupos' => $this->model->listaParaPos()]);
    }

    // ── 9.4 · Aceptar y rechazar ────────────────────────────────────────────

    /**
     * Le pide a MenuApp que acepte el pedido. MenuApp cobra (o deja el link), avisa al
     * cliente y registra la venta en Dewoc, así que puede responder que falta materia prima
     * o que la caja está cerrada: en esos casos el pedido sigue esperando y se le muestra el
     * motivo al cajero.
     */
    public function aceptar(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $referencia = $this->referenciaValida();
        $r = MenuAppClient::aceptar($referencia);

        if (!$r['ok']) {
            // No se toca el estado local: el pedido sigue requiriendo acción y la alerta
            // vuelve en el próximo sondeo, que es lo que se quiere si falló por stock.
            $this->json(['ok' => false, 'error' => $r['error'], 'codigo' => $r['codigo']], 409);
        }

        $this->reflejarRespuesta($referencia, $r['datos'], 'aceptado');
        AuditLog::log('aceptar_pedido', 'menuapp', "Pedido {$referencia} aceptado desde el POS");

        $this->json(['ok' => true, 'mensaje' => 'Pedido aceptado.', 'estado' => $r['datos']['estado'] ?? 'aceptado']);
    }

    public function rechazar(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $referencia = $this->referenciaValida();
        $motivo     = trim((string)$this->input('motivo', ''));
        if ($motivo === '') {
            $this->json(['ok' => false, 'error' => 'El motivo es obligatorio: el cliente lo va a leer.'], 422);
        }

        $r = MenuAppClient::rechazar($referencia, $motivo);
        if (!$r['ok']) {
            $this->json(['ok' => false, 'error' => $r['error'], 'codigo' => $r['codigo']], 409);
        }

        $this->reflejarRespuesta($referencia, $r['datos'], 'rechazado');
        AuditLog::log('rechazar_pedido', 'menuapp', "Pedido {$referencia} rechazado desde el POS. Motivo: {$motivo}");

        $this->json(['ok' => true, 'mensaje' => 'Pedido rechazado. MenuApp le avisa al cliente.']);
    }

    // ── 9.5 · Proponer algo diferente ───────────────────────────────────────

    /**
     * Catálogo para armar la propuesta: solo los productos del menú online y sus grupos de
     * gustos, con los mínimos y máximos de cada uno. Es el mismo catálogo que ve MenuApp, así
     * que lo que se arme acá no puede quedar fuera del menú.
     */
    public function catalogoParaPropuesta(): void
    {
        $this->requireCanView('ventas_pos');

        $catalogo = new MenuAppCatalogoModel();
        $filas    = $catalogo->productos();
        $grupos   = $catalogo->gruposPorProducto(array_column($filas, 'id'));

        $productos = array_map(fn(array $p): array => [
            'id'     => (int)$p['id'],
            'nombre' => $p['nombre'],
            // El precio que ve el cliente, con el mismo criterio de IVA que el menú (D9).
            // El total real lo recalcula MenuApp al proponer; esto es para orientar al cajero.
            'precio' => round(PrecioHelper::precioFinalOnline($p), 2),
            'grupos' => $grupos[(int)$p['id']] ?? [],
        ], $filas);

        $this->json(['ok' => true, 'productos' => $productos]);
    }

    /**
     * Manda la propuesta a MenuApp con IDs de Dewoc. MenuApp valida el menú y los mínimos y
     * máximos de gustos (422 si algo no cierra) y devuelve el total nuevo; el cliente la
     * acepta o la rechaza desde su seguimiento.
     */
    public function proponer(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $referencia = $this->referenciaValida();
        $motivo     = trim((string)$this->input('motivo', ''));
        if ($motivo === '') {
            $this->json(['ok' => false, 'error' => 'Escribí el motivo: es lo que va a leer el cliente.'], 422);
        }

        $itemsRaw = json_decode((string)($_POST['items'] ?? ''), true);
        if (!is_array($itemsRaw) || !count($itemsRaw)) {
            $this->json(['ok' => false, 'error' => 'La propuesta no puede quedar sin productos.'], 422);
        }

        // Se sanean los IDs y las cantidades, pero NO se valida el menú acá: eso lo hace
        // MenuApp, que es el dueño de qué se puede pedir y de los límites de cada grupo.
        // Duplicar esa validación sería tener dos reglas que se pueden desincronizar.
        $items = [];
        foreach ($itemsRaw as $it) {
            $productoId = (int)($it['producto_id'] ?? 0);
            $cantidad   = (float)($it['cantidad'] ?? 0);
            if ($productoId <= 0 || $cantidad <= 0) continue;

            $opciones = [];
            foreach (($it['opciones'] ?? []) as $op) {
                $opcionId = (int)($op['opcion_id'] ?? 0);
                if ($opcionId > 0) {
                    $opciones[] = ['opcion_id' => $opcionId, 'cantidad' => max(1, (int)($op['cantidad'] ?? 1))];
                }
            }

            $items[] = [
                'producto_id' => $productoId,
                'cantidad'    => $cantidad,
                'opciones'    => $opciones,
                'notas'       => trim((string)($it['notas'] ?? '')) ?: null,
            ];
        }

        if (!count($items)) {
            $this->json(['ok' => false, 'error' => 'Ningún producto de la propuesta es válido.'], 422);
        }

        $r = MenuAppClient::proponer($referencia, $items, $motivo);
        if (!$r['ok']) {
            $this->json(['ok' => false, 'error' => $r['error'], 'codigo' => $r['codigo']], $r['http'] === 422 ? 422 : 409);
        }

        $this->reflejarRespuesta($referencia, $r['datos'], 'propuesta_enviada');
        AuditLog::log('proponer_pedido', 'menuapp',
            "Pedido {$referencia}: propuesta enviada al cliente desde el POS. Motivo: {$motivo}");

        $this->json([
            'ok'         => true,
            'mensaje'    => 'Propuesta enviada. El cliente la ve en su seguimiento.',
            'total_nuevo'=> $r['datos']['total'] ?? null,
        ]);
    }

    // ── 9.7 · Las dos conversaciones ────────────────────────────────────────

    /** Lee una conversación de MenuApp y pone en cero el contador de no leídos. */
    public function mensajes(): void
    {
        $this->requireCanView('ventas_pos');

        $referencia   = $this->referenciaValida('param');
        $conversacion = $this->conversacionValida((string)$this->param('conversacion', 'cliente'));

        $r = MenuAppClient::mensajes($referencia, $conversacion, (int)$this->param('despues', 0));
        if (!$r['ok']) {
            $this->json(['ok' => false, 'error' => $r['error']], 502);
        }

        // Recién después de leerlos de verdad se bajan los no leídos: si MenuApp no
        // respondió, el badge tiene que seguir ahí.
        $this->model->marcarLeidos($referencia, $conversacion);

        $this->json([
            'ok'        => true,
            'mensajes'  => $r['datos']['mensajes'] ?? $r['datos'] ?? [],
        ]);
    }

    public function enviarMensaje(): void
    {
        $this->requireCanView('ventas_pos');
        Csrf::check();

        $referencia   = $this->referenciaValida();
        $conversacion = $this->conversacionValida((string)$this->input('conversacion', 'cliente'));
        $texto        = trim((string)$this->input('texto', ''));

        if ($texto === '') {
            $this->json(['ok' => false, 'error' => 'El mensaje está vacío.'], 422);
        }

        $r = MenuAppClient::enviarMensaje($referencia, $conversacion, $texto);
        if (!$r['ok']) {
            // 409 típico: se quiso escribir al repartidor y todavía no hay uno asignado.
            $this->json(['ok' => false, 'error' => $r['error'], 'codigo' => $r['codigo']], $r['http'] ?: 502);
        }

        $this->json(['ok' => true, 'mensaje' => 'Mensaje enviado.']);
    }

    /**
     * Respuestas rápidas que el comercio edita en MenuApp. Se piden en vivo a propósito: si
     * se cachearan acá, editar la lista en MenuApp no se vería en el POS hasta vaciar el
     * cache, que es justo lo que el contrato pide evitar.
     */
    public function respuestasRapidas(): void
    {
        $this->requireCanView('ventas_pos');

        $r = MenuAppClient::respuestasRapidas();
        if (!$r['ok']) {
            // No es un error que deba frenar el chat: sin la lista se escribe a mano.
            $this->json(['ok' => false, 'error' => $r['error'], 'cliente' => [], 'repartidor' => []], 200);
        }

        $this->json([
            'ok'         => true,
            'cliente'    => $r['datos']['cliente']    ?? [],
            'repartidor' => $r['datos']['repartidor'] ?? [],
        ]);
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /** Referencia del pedido, que además tiene que existir en la copia local. */
    private function referenciaValida(string $origen = 'input'): string
    {
        $referencia = trim((string)($origen === 'param'
            ? $this->param('referencia', '')
            : $this->input('referencia', '')));

        if ($referencia === '') {
            $this->json(['ok' => false, 'error' => 'Falta la referencia del pedido.'], 422);
        }
        if (!$this->model->findByReferencia($referencia)) {
            $this->json(['ok' => false, 'error' => 'Este pedido no está en Dewoc.'], 404);
        }
        return $referencia;
    }

    private function conversacionValida(string $conversacion): string
    {
        if (!in_array($conversacion, ['cliente', 'repartidor'], true)) {
            $this->json(['ok' => false, 'error' => 'Conversación inválida.'], 422);
        }
        return $conversacion;
    }

    /**
     * Refleja en la copia local el estado que devolvió MenuApp. Se usa el que manda MenuApp
     * y no el que supone Dewoc, porque MenuApp es el dueño del pedido; $porDefecto es solo
     * el respaldo para cuando la respuesta no trae estado.
     */
    private function reflejarRespuesta(string $referencia, array $datos, string $porDefecto): void
    {
        $this->model->actualizarEstado(
            $referencia,
            (string)($datos['estado'] ?? $porDefecto),
            (string)($datos['estado_texto'] ?? '') ?: null
        );
        $this->model->marcarAtendidoPor($referencia, (int)(Session::user()['id'] ?? 0));
    }
}
