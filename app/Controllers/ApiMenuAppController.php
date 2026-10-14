<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
require_once BASE_PATH . '/app/Models/MenuAppCatalogoModel.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/app/Models/CajaModel.php';
require_once BASE_PATH . '/app/Models/MenuAppPedidoModel.php';
require_once BASE_PATH . '/core/PrecioHelper.php';

/**
 * API que consume MenuApp (menuapp.dewoc.com) — ver docs/integracion-menuapp-checklist.md,
 * sección 3. Dewoc es la fuente de verdad del catálogo, del stock y de las ventas; MenuApp
 * es la vidriera y la toma de pedidos.
 *
 * Diferencias a propósito con el resto de los controladores:
 * - NO usa $_SESSION ni Csrf::check(): se autentica con `Authorization: Bearer <token>`,
 *   validado al principio de CADA acción con requireApiToken(). El router de esta app no
 *   tiene middleware, así que la llamada es explícita y no se puede olvidar sin que la
 *   acción quede sin ninguna autenticación.
 * - Responde siempre JSON en UTF-8, con montos a 2 decimales y fechas ISO 8601 con la zona
 *   del comercio.
 *
 * Códigos: 401 token inválido o integración apagada · 404 inexistente · 409 falta stock ·
 * 422 datos inválidos · 429 pasado el límite de requests.
 */
class ApiMenuAppController extends Controller
{
    /** Versión del contrato de la API que habla esta instalación (la informa GET /estado). */
    const VERSION = '1.1.0';

    private IntegracionMenuAppModel $integracion;
    private MenuAppCatalogoModel    $catalogo;
    private VentaModel              $ventaModel;
    private CajaModel               $cajaModel;

    /** Config de la integración ya validada, disponible después de requireApiToken(). */
    private array $config = [];

    public function __construct()
    {
        $this->integracion = new IntegracionMenuAppModel();
        $this->catalogo    = new MenuAppCatalogoModel();
        $this->ventaModel  = new VentaModel();
        $this->cajaModel   = new CajaModel();
    }

    // ── Autenticación y utilidades ───────────────────────────────────────────

    /**
     * Valida el token Bearer, el estado de la integración y el límite de requests.
     * Corta la ejecución con el JSON de error correspondiente si algo no cierra.
     * De paso deja registrado el acceso (ultimo_acceso_at) y la llamada en el log — nunca
     * el token, ni completo ni parcial.
     */
    private function requireApiToken(): void
    {
        $token  = $this->bearerToken();
        $config = $this->integracion->validarToken($token);

        if (!$config) {
            $this->logLlamada('401 token inválido o integración inactiva');
            $this->json(['error' => 'no_autorizado'], 401);
        }

        if (!$this->integracion->consumirRateLimit()) {
            $this->logLlamada('429 límite de requests superado');
            $this->json(['error' => 'demasiadas_solicitudes', 'limite_por_minuto' => IntegracionMenuAppModel::RATE_LIMITE_POR_MINUTO], 429);
        }

        $this->config = $config;
        $this->integracion->registrarAcceso();
        $this->logLlamada('OK');
    }

    /**
     * Token del header Authorization. Apache/LiteSpeed no siempre se lo pasan a PHP en
     * $_SERVER['HTTP_AUTHORIZATION'] (depende del SAPI y de la configuración), así que se
     * intentan también las dos variantes alternativas habituales y, por último,
     * apache_request_headers(). Ver la regla RewriteRule del .htaccess (tarea 1.3).
     */
    private function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) { $header = $valor; break; }
            }
        }

        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string)$header, $m)) return $m[1];
        return '';
    }

    /** Deja la llamada en storage/logs/app.log, SIN el token (ni un fragmento). */
    private function logLlamada(string $resultado): void
    {
        logApp(sprintf(
            'API MenuApp %s %s → %s',
            $_SERVER['REQUEST_METHOD'] ?? '?',
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '?',
            $resultado
        ));
    }

    /** Cuerpo JSON de la request. Un cuerpo ilegible es 422, no un 500. */
    private function cuerpoJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->json(['error' => 'json_invalido', 'detalle' => 'El cuerpo de la request no es un JSON válido.'], 422);
        }
        return $data;
    }

    /** Fecha en ISO 8601 con la zona del comercio (ej. 2026-10-12T18:30:00-03:00). */
    private function iso(?string $fecha = null): string
    {
        $ts = $fecha ? strtotime($fecha) : time();
        return date('c', $ts ?: time());
    }

    /** Monto con 2 decimales como número, no como string. */
    private function monto(float $v): float
    {
        return round($v, 2);
    }

    // ── 3.0 · GET /api/menuapp/v1/estado ─────────────────────────────────────

    /**
     * Identidad de la instalación y estado de la caja (etapa 6, decisiones D10 y D11).
     * MenuApp lo llama al conectar (para validar con quién se conectó), cada minuto (para
     * abrir o cerrar el menú) y al reactivar la integración.
     *
     * Es la única acción que, además de los avisos del webhook, le dice a MenuApp si el
     * comercio está tomando pedidos: si Dewoc no responde, MenuApp usa el último estado
     * conocido por 10 minutos y después cierra el menú por las suyas.
     */
    public function estado(): void
    {
        $this->requireApiToken();

        $turnoMasViejo = $this->cajaModel->aperturaDelTurnoAbiertoMasViejo();

        $this->json([
            'instalacion' => [
                // Fijo de por vida: no cambia al regenerar el token ni al reactivar.
                'id'       => $this->integracion->instalacionId(),
                'comercio' => NEGOCIO_NOMBRE,
                // El CUIT sale de la configuración de AFIP, que es donde ya estaba. Sin
                // facturación electrónica configurada, la constante viene vacía → null.
                'cuit'     => (defined('AFIP_CUIT') && AFIP_CUIT !== '') ? AFIP_CUIT : null,
            ],
            'caja' => [
                'abierta'         => $turnoMasViejo !== null,
                'desde'           => $turnoMasViejo !== null ? $this->iso($turnoMasViejo) : null,
                'turnos_abiertos' => $this->cajaModel->cantidadTurnosAbiertos(),
            ],
            'menuapp' => [
                // Para que MenuApp detecte que esta instalación quedó apuntando al webhook
                // de OTRO comercio y rechace la conexión (D11).
                'webhook_url' => $this->config['webhook_url'] ?? null,
            ],
            'version'     => self::VERSION,
            'generado_en' => $this->iso(),
        ]);
    }

    // ── 3.1 · GET /api/menuapp/v1/catalogo ───────────────────────────────────

    /**
     * Catálogo completo del menú online. Con ?desde=<ISO8601> devuelve solo lo que cambió
     * desde entonces, incluidos los productos que SE DEJARON de publicar: esos vienen con
     * `visible: false` para que MenuApp los oculte sin borrarlos de su base.
     */
    public function catalogo(): void
    {
        $this->requireApiToken();

        $desde = $this->param('desde');
        if ($desde !== null && $desde !== '') {
            $ts = strtotime($desde);
            if ($ts === false) {
                $this->json(['error' => 'parametro_invalido', 'detalle' => 'desde debe ser una fecha ISO 8601.'], 422);
            }
            $desde = date('Y-m-d H:i:s', $ts);
        } else {
            $desde = null;
        }

        $filas = $this->catalogo->productos($desde);

        $gruposPorProducto = $this->catalogo->gruposPorProducto(array_column($filas, 'id'));
        $categoriaIds      = [];
        $productos         = [];

        foreach ($filas as $p) {
            $publicado = (int)$p['publicado'] === 1;

            // Sin ?desde solo vienen los publicados; con ?desde pueden venir los
            // desmarcados/desactivados/eliminados, que son justamente los que hay que
            // ocultar del lado de MenuApp.
            if ($publicado) $categoriaIds[] = (int)$p['categoria_id'];

            $actualizado = $this->iso($p['updated_at']);

            $productos[] = [
                'id'              => (int)$p['id'],
                'codigo'          => $p['codigo'],
                'nombre'          => $p['nombre'],
                'descripcion'     => $p['descripcion'],
                'categoria_id'    => $p['categoria_id'] !== null ? (int)$p['categoria_id'] : null,
                // Precio FINAL al consumidor, con IVA, calculado SIEMPRE con PrecioHelper
                // (base = precio_online si tiene valor, si no precio_venta; y se le suma el
                // IVA solo si la opción D9 dice que los precios cargados no lo traen).
                'precio'          => $this->monto(PrecioHelper::precioFinalOnline($p)),
                'imagen_url'      => $p['imagen'] ? rtrim(BASE_URL, '/') . '/uploads/productos/' . $p['imagen'] : null,
                'imagen_version'  => $this->catalogo->versionImagen($p['imagen'], $actualizado),
                'visible'         => $publicado,
                'actualizado_en'  => $actualizado,
                'grupos'          => array_map(
                    fn(array $g) => [
                        'id'              => $g['id'],
                        'nombre'          => $g['nombre'],
                        'minimo'          => $g['minimo'],
                        'maximo'          => $g['maximo'],
                        'obligatorio'     => $g['obligatorio'],
                        'permite_repetir' => $g['permite_repetir'],
                        'orden'           => $g['orden'],
                        'opciones'        => $g['opciones'],
                    ],
                    $gruposPorProducto[(int)$p['id']] ?? []
                ),
            ];
        }

        $categorias = array_map(fn(array $c) => [
            'id'           => (int)$c['id'],
            'nombre'       => $c['nombre'],
            'padre_id'     => $c['padre_id'] !== null ? (int)$c['padre_id'] : null,
            'padre_nombre' => $c['padre_nombre'],
            'orden'        => 0,
        ], $this->catalogo->categoriasDe($categoriaIds));

        $this->json([
            'generado_en' => $this->iso(),
            'comercio'    => ['nombre' => NEGOCIO_NOMBRE, 'moneda' => 'ARS'],
            'categorias'  => $categorias,
            'productos'   => $productos,
        ]);
    }

    // ── 3.2 · GET /api/menuapp/v1/disponibilidad ─────────────────────────────

    /**
     * Qué se puede vender ahora mismo. Reutiliza los métodos de VentaModel y RecetaModel
     * (no recalcula nada por su cuenta), así la respuesta siempre coincide con lo que haría
     * una venta real; de todos modos Dewoc vuelve a validar al registrar el pedido (3.3).
     */
    public function disponibilidad(): void
    {
        $this->requireApiToken();

        $productos = [];
        foreach ($this->catalogo->productos() as $p) {
            $estado = $this->ventaModel->disponibilidadProducto((int)$p['id']);
            $productos[] = [
                'id'         => (int)$p['id'],
                'disponible' => $estado['disponible'],
            ] + ($estado['motivo'] ? ['motivo' => $estado['motivo']] : []);
        }

        // Una opción puede estar asignada a varios productos con reparto distinto (ej.
        // "Gustos" reparte 1000gr en el kilo y 250gr en el cuarto): se la considera
        // disponible si alcanza para AL MENOS uno de ellos, y MenuApp vuelve a validar
        // contra Dewoc al aceptar el pedido.
        $opciones = [];
        foreach ($this->catalogo->opcionesPublicadas() as $o) {
            $productosDelGrupo = $this->ventaModel->productosDeGrupoOpcion((int)$o['grupo_id']);
            $disponible = false;
            $motivo     = 'Sin stock de ' . $o['nombre'];

            foreach ($productosDelGrupo as $pid) {
                $estado = $this->ventaModel->disponibilidadOpcion($o, $pid);
                if ($estado['disponible']) { $disponible = true; break; }
                $motivo = $estado['motivo'] ?? $motivo;
            }

            $opciones[] = ['id' => (int)$o['id'], 'disponible' => $disponible]
                + ($disponible ? [] : ['motivo' => $motivo]);
        }

        $this->json([
            'generado_en' => $this->iso(),
            'productos'   => $productos,
            'opciones'    => $opciones,
        ]);
    }

    // ── 3.3 · POST /api/menuapp/v1/ventas ────────────────────────────────────

    /**
     * Registra en Dewoc un pedido que el comercio ACEPTÓ en MenuApp (D5): descuenta la
     * materia prima y cuenta en los reportes de ventas, pero NO mueve la caja (D6) — eso
     * pasa recién al entregarlo, en cobrar().
     *
     * Idempotente por `referencia`: si el mismo pedido llega dos veces, se devuelve la
     * venta ya creada con HTTP 200 en vez de duplicarla.
     */
    public function ventas(): void
    {
        $this->requireApiToken();

        $body       = $this->cuerpoJson();
        $referencia = trim((string)($body['referencia'] ?? ''));

        if ($referencia === '' || mb_strlen($referencia) > 40) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'referencia es obligatoria (hasta 40 caracteres).'], 422);
        }

        // Idempotencia, antes de tocar cualquier stock Y antes de mirar la caja: un reintento
        // de un pedido que se aceptó con la caja abierta tiene que devolver la misma venta
        // aunque la caja ya se haya cerrado (ver la excepción de 3.3).
        $yaExiste = $this->ventaModel->buscarPorReferenciaExterna($referencia);
        if ($yaExiste) {
            $this->json(['venta_id' => (int)$yaExiste['id'], 'referencia' => $referencia], 200);
        }

        // Caja cerrada (D10, tarea 6.4): el comercio no está tomando pedidos. Se controla
        // ANTES de validar stock, para que MenuApp cierre el menú al instante en vez de
        // mostrarle al cliente un error de faltantes que no es el problema real.
        if (!$this->cajaModel->hayTurnoAbierto()) {
            $this->json(['error' => 'caja_cerrada'], 409);
        }

        $canal = (string)($body['canal'] ?? 'delivery');
        if (!in_array($canal, ['delivery', 'retiro', 'mesa'], true)) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'canal debe ser delivery, retiro o mesa.'], 422);
        }

        $metodoPago = (string)($body['metodo_pago'] ?? '');
        if (!in_array($metodoPago, ['mercadopago', 'efectivo', 'transferencia'], true)) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'metodo_pago debe ser mercadopago, efectivo o transferencia.'], 422);
        }

        $items = $body['items'] ?? null;
        if (!is_array($items) || !count($items)) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'items no puede estar vacío.'], 422);
        }

        // ── Detalle ──────────────────────────────────────────────────────────
        // precio_final_unitario (no precio_unitario): MenuApp manda siempre el precio FINAL
        // con IVA, sin importar la opción D9. VentaModel::registrar() se encarga de separar
        // neto e IVA con PrecioHelper, igual que para una venta del POS.
        $detalle   = [];
        $totalItems = 0.0;
        foreach ($items as $it) {
            $productoId = (int)($it['producto_id'] ?? 0);
            $cantidad   = (float)($it['cantidad'] ?? 0);
            $precio     = (float)($it['precio_unitario'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                $this->json(['error' => 'datos_invalidos', 'detalle' => 'Cada item necesita producto_id y cantidad > 0.'], 422);
            }
            if ($precio < 0) {
                $this->json(['error' => 'datos_invalidos', 'detalle' => 'precio_unitario no puede ser negativo.'], 422);
            }

            $producto = $this->catalogo->productoParaVenta($productoId);
            if (!$producto) {
                $this->json(['error' => 'producto_inexistente', 'producto_id' => $productoId], 404);
            }

            $opciones = [];
            foreach (($it['opciones'] ?? []) as $op) {
                $opcionId = (int)($op['opcion_id'] ?? 0);
                if (!$opcionId) continue;
                $opciones[] = [
                    'opcion_id'        => $opcionId,
                    'cantidad_elegida' => max(1, (int)($op['cantidad_elegida'] ?? 1)),
                ];
            }

            $detalle[] = [
                'producto_id'           => $productoId,
                'cantidad'              => $cantidad,
                'precio_final_unitario' => $precio,
                'descuento_pct'         => 0,
                'opciones'              => $opciones,
            ];
            $totalItems += $precio * $cantidad;
        }

        // ── Envío (tarea 4.1) ────────────────────────────────────────────────
        // Va como una línea más, con el producto "Envío a domicilio" elegido en la pantalla
        // Integraciones → MenuApp (controla_stock = 0 y sin receta), para que el envío
        // aparezca en los reportes en vez de desaparecer dentro del total.
        $costoEnvio = max(0.0, (float)($body['costo_envio'] ?? 0));
        if ($costoEnvio > 0) {
            $productoEnvioId = (int)($this->config['producto_envio_id'] ?? 0);
            if (!$productoEnvioId || !$this->catalogo->productoParaVenta($productoEnvioId)) {
                $this->json([
                    'error'   => 'envio_sin_producto',
                    'detalle' => 'El pedido tiene costo de envío pero no hay un producto "Envío a domicilio" configurado en Dewoc (Integraciones → MenuApp).',
                ], 422);
            }
            $detalle[] = [
                'producto_id'           => $productoEnvioId,
                'cantidad'              => 1.0,
                'precio_final_unitario' => $costoEnvio,
                'descuento_pct'         => 0,
                'opciones'              => [],
            ];
            $totalItems += $costoEnvio;
        }

        // ── Descuento y control del total ────────────────────────────────────
        // El descuento llega como MONTO y `ventas` lo guarda como PORCENTAJE
        // (descuento_global), así que se convierte. Se hace sobre el total de las líneas
        // para que el total que queda en Dewoc cierre exacto con el de MenuApp.
        $descuento = max(0.0, (float)($body['descuento'] ?? 0));
        if ($descuento > $totalItems + 0.01) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'El descuento no puede superar el total de los items.'], 422);
        }
        $descuentoPct = $totalItems > 0 ? round($descuento / $totalItems * 100, 2) : 0.0;

        $totalCalculado = round($totalItems - $descuento, 2);
        $totalMenuApp   = round((float)($body['total'] ?? 0), 2);
        if (abs($totalCalculado - $totalMenuApp) > 0.01) {
            $this->json([
                'error'   => 'total_no_coincide',
                'detalle' => 'El total de MenuApp no coincide con el calculado por Dewoc.',
                'total_menuapp' => $totalMenuApp,
                'total_dewoc'   => $totalCalculado,
            ], 422);
        }

        // ── Cabecera ─────────────────────────────────────────────────────────
        $usuarioId = $this->integracion->asegurarUsuarioTecnico();
        if (!$usuarioId) {
            $this->json(['error' => 'sin_usuario_tecnico', 'detalle' => 'No se pudo resolver el usuario técnico de MenuApp.'], 500);
        }

        $cliente = is_array($body['cliente'] ?? null) ? $body['cliente'] : [];
        $notas   = $this->armarNotas($canal, $cliente, (string)($body['notas'] ?? ''), $referencia, (bool)($body['cobrado_online'] ?? false));

        // Efectivo y transferencia entran a la caja recién al entregar (D6): la venta se
        // crea con cobro_pendiente = 1 y sin turno_id, y cobrar() la imputa después. Lo
        // cobrado por Mercado Pago nunca entra a la caja física.
        $cobradoEnMano = in_array($metodoPago, ['efectivo', 'transferencia'], true);

        $cabecera = [
            'usuario_id'       => $usuarioId,
            'turno_id'         => null,   // a propósito: aceptar el pedido no mueve la caja
            'cliente_id'       => null,
            'subtotal'         => 0,      // los tres totales los recalcula registrar()
            'total_iva'        => 0,
            'total_con_iva'    => 0,
            'descuento_global' => $descuentoPct,
            'metodo_pago'      => $metodoPago,
            'monto_efectivo'      => 0,
            'monto_debito'        => 0,
            'monto_credito'       => 0,
            'monto_transferencia' => 0,
            'monto_mercadopago'   => 0,
            'monto_cuenta_corriente' => 0,
            'facturar_afip'    => 0,      // por ahora no se factura lo de MenuApp
            'estado'           => 'completada',
            'mesa'             => $canal === 'mesa' ? 'Mesa (MenuApp)' : null,
            'notas'            => $notas,
            'origen'           => 'menuapp',
            'referencia_externa' => $referencia,
            'cobro_pendiente'  => $cobradoEnMano ? 1 : 0,
            'eliminado'        => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        // El monto del medio se deja cargado igual que en el POS, para que los reportes por
        // medio de pago sigan sumando; lo que NO se crea es el caja_movimientos.
        $cabecera['monto_' . $metodoPago] = $totalCalculado;

        try {
            // confirmarSinStock: false SIEMPRE (nunca true). Así MenuApp no puede aceptar un
            // pedido que el comercio no puede preparar: recibe 409 con los faltantes y el
            // comercio lo edita o lo rechaza.
            $resultado = $this->ventaModel->registrar($cabecera, $detalle, false);
        } catch (\PDOException $e) {
            // Carrera entre dos POST con la misma referencia: el UNIQUE de
            // referencia_externa es la última línea de defensa de la idempotencia.
            if (($e->errorInfo[1] ?? 0) === 1062) {
                $existente = $this->ventaModel->buscarPorReferenciaExterna($referencia);
                if ($existente) {
                    $this->json(['venta_id' => (int)$existente['id'], 'referencia' => $referencia], 200);
                }
            }
            throw $e;
        }

        if (empty($resultado['ok'])) {
            $this->json([
                'error'     => 'sin_stock',
                'faltantes' => $this->faltantesParaMenuApp($resultado['faltantes'] ?? []),
            ], 409);
        }

        $ventaId = (int)$resultado['id'];
        logApp("API MenuApp: venta #{$ventaId} registrada para la referencia {$referencia} (total {$totalCalculado}, {$metodoPago}).");

        $this->json(['venta_id' => $ventaId, 'referencia' => $referencia], 201);
    }

    /**
     * Datos del pedido que no tienen columna propia en `ventas` y el comercio necesita ver
     * en el detalle de la venta y en la comanda.
     */
    private function armarNotas(string $canal, array $cliente, string $notas, string $referencia, bool $cobradoOnline): string
    {
        $lineas = ['MenuApp ' . $referencia . ' — ' . ucfirst($canal)];

        $nombre = trim((string)($cliente['nombre'] ?? ''));
        $tel    = trim((string)($cliente['telefono'] ?? ''));
        $email  = trim((string)($cliente['email'] ?? ''));
        $datos  = array_filter([$nombre, $tel, $email]);
        if ($datos) $lineas[] = 'Cliente: ' . implode(' · ', $datos);

        $lineas[] = $cobradoOnline ? 'Cobrado online (no entra a la caja física)' : 'A cobrar en la entrega';

        if (trim($notas) !== '') $lineas[] = 'Nota: ' . sanitize($notas);

        return implode("\n", $lineas);
    }

    /**
     * Pasa los faltantes al formato de 3.3. registrar() los devuelve como texto ya armado
     * ("Dulce de leche (necesita 300, hay 120)"): se recorta la parte de cantidades, que no
     * tiene por qué viajar al menú público.
     */
    private function faltantesParaMenuApp(array $faltantes): array
    {
        return array_values(array_map(static function (string $f): array {
            $nombre = trim(preg_replace('/\s*\(necesita .*$/', '', $f) ?? $f);
            return ['tipo' => 'ingrediente', 'nombre' => $nombre !== '' ? $nombre : $f];
        }, $faltantes));
    }


    // ── 3.7 · POST /api/menuapp/v1/pedidos ───────────────────────────────────

    /**
     * Pedido nuevo para atender desde el POS (etapa 9, decisión D14). MenuApp lo llama
     * cuando el pedido queda esperando aprobación — con pago previo, recién cuando el pago
     * se acreditó.
     *
     * NO registra ninguna venta ni toca el stock: es solo la alerta. La venta se registra
     * al aceptar, y la registra MenuApp llamando a POST /ventas (3.3). Por eso acá no hay
     * nada de PrecioHelper ni de VentaModel.
     *
     * Idempotente por referencia: un reintento de MenuApp responde 200 y no duplica la
     * alerta ni reabre una que alguien ya atendió.
     */
    public function pedidos(): void
    {
        $this->requireApiToken();

        $body       = $this->cuerpoJson();
        $referencia = trim((string)($body['referencia'] ?? ''));

        if ($referencia === '' || mb_strlen($referencia) > 40) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'referencia es obligatoria (hasta 40 caracteres).'], 422);
        }
        if (empty($body['items']) || !is_array($body['items'])) {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'items no puede estar vacío.'], 422);
        }

        $r = $this->pedidoModel()->guardarDesdeMenuApp($referencia, $body);

        // Push a quienes tienen la caja abierta. Como el menú solo recibe pedidos con la
        // caja abierta (D10), siempre hay alguien ahí para recibirlo.
        if ($r['creado']) {
            $this->avisarPorPush(
                'Pedido nuevo de MenuApp',
                trim(($body['canal'] ?? 'Pedido') . ' · ' . ($body['cliente']['nombre'] ?? 'Cliente')
                    . ' · ' . moneda((float)($body['total'] ?? 0))),
                'ventas/pos'
            );
        }

        logApp("API MenuApp: pedido {$referencia} " . ($r['creado'] ? 'recibido' : 'reenviado (ya existía)') . '.');

        $this->json(['pedido_id' => $r['id'], 'referencia' => $referencia], $r['creado'] ? 201 : 200);
    }

    // ── 3.8 · POST /api/menuapp/v1/pedidos/{referencia}/novedades ────────────

    /**
     * Algo cambió en el pedido y el POS tiene que reflejarlo. Idempotente; si la referencia
     * no existe responde 404 y MenuApp lo ignora (puede pasar si el pedido se creó antes de
     * que el comercio tuviera Dewoc conectado).
     */
    public function novedades(string $referencia = ''): void
    {
        $this->requireApiToken();

        $referencia = trim(urldecode($referencia));
        if ($referencia === '') {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'Falta la referencia en la URL.'], 422);
        }

        $modelo = $this->pedidoModel();
        if (!$modelo->findByReferencia($referencia)) {
            $this->json(['error' => 'pedido_inexistente', 'referencia' => $referencia], 404);
        }

        $body = $this->cuerpoJson();
        $tipo = (string)($body['tipo'] ?? '');

        switch ($tipo) {
            case 'estado':
                $estado = trim((string)($body['estado'] ?? ''));
                if ($estado === '') {
                    $this->json(['error' => 'datos_invalidos', 'detalle' => 'Falta estado.'], 422);
                }
                $modelo->actualizarEstado($referencia, $estado, (string)($body['estado_texto'] ?? '') ?: null);
                break;

            case 'mensaje':
                $conversacion = (string)($body['conversacion'] ?? '');
                if (!in_array($conversacion, ['cliente', 'repartidor'], true)) {
                    $this->json(['error' => 'datos_invalidos', 'detalle' => 'conversacion debe ser cliente o repartidor.'], 422);
                }
                // El texto del mensaje NO se guarda: la conversación vive en MenuApp y se lee
                // de ahí (3.10). Acá solo se cuenta, para el badge del POS.
                $modelo->sumarMensajeSinLeer($referencia, $conversacion);
                $this->avisarPorPush(
                    'Mensaje nuevo de un pedido',
                    trim(((string)($body['de'] ?? 'Alguien')) . ': ' . mb_substr((string)($body['texto'] ?? ''), 0, 80)),
                    'ventas/pos'
                );
                break;

            case 'repartidor':
                $modelo->actualizarRepartidor($referencia, (string)($body['repartidor'] ?? '') ?: null);
                break;

            default:
                $this->json(['error' => 'datos_invalidos', 'detalle' => 'tipo debe ser estado, mensaje o repartidor.'], 422);
        }

        logApp("API MenuApp: novedad '{$tipo}' del pedido {$referencia}.");
        $this->json(['ok' => true, 'referencia' => $referencia, 'tipo' => $tipo], 200);
    }

    private function pedidoModel(): MenuAppPedidoModel
    {
        return new MenuAppPedidoModel();
    }

    /**
     * Web Push a los usuarios con la caja abierta (tarea 9.8). Nunca puede hacer fallar la
     * recepción del pedido: si el push falla, el pedido ya quedó guardado y el POS lo ve en
     * su sondeo de 30 segundos igual.
     */
    private function avisarPorPush(string $titulo, string $cuerpo, string $ruta): void
    {
        try {
            if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

            require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
            require_once BASE_PATH . '/core/WebPush.php';

            $payload = json_encode([
                'title' => $titulo,
                'body'  => $cuerpo,
                'url'   => rtrim(BASE_URL, '/') . '/' . ltrim($ruta, '/'),
                'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
            ], JSON_UNESCAPED_UNICODE);

            $pushModel = new PushSubscriptionModel();
            foreach ($pushModel->findSuscripcionesTurnosAbiertos() as $sub) {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) $pushModel->eliminarPorId((int)$sub['id']);
            }
        } catch (\Throwable $e) {
            logApp('Push de pedido de MenuApp: ' . $e->getMessage(), 'WARN');
        }
    }

    // ── 3.4 · POST /api/menuapp/v1/ventas/{referencia}/cobrar ────────────────

    /**
     * El pedido se entregó y se cobró en mano: ahora sí entra a la caja del turno abierto
     * (D6). Solo para efectivo y transferencia — lo cobrado por Mercado Pago ya se cobró
     * online y nunca pasa por la caja física.
     *
     * Si no hay ningún turno abierto, la venta queda marcada "cobro pendiente" y aparece en
     * /caja para imputarla a mano al abrir el turno siguiente. Idempotente.
     */
    public function cobrar(string $referencia = ''): void
    {
        $this->requireApiToken();

        $venta = $this->ventaPorReferencia($referencia);

        if ($venta['metodo_pago'] === 'mercadopago') {
            $this->json([
                'error'   => 'metodo_no_cobrable',
                'detalle' => 'La venta se cobró online por Mercado Pago: no entra a la caja física.',
            ], 422);
        }
        if ($venta['estado'] === 'anulada') {
            $this->json(['error' => 'venta_anulada', 'venta_id' => (int)$venta['id']], 409);
        }

        $r = $this->ventaModel->cobrarEnCaja((int)$venta['id']);

        $this->json([
            'venta_id'        => (int)$venta['id'],
            'referencia'      => $venta['referencia_externa'],
            'ya_cobrada'      => $r['ya_cobrada'],
            'turno_id'        => $r['turno_id'],
            'cobro_pendiente' => $r['cobro_pendiente'],
        ], 200);
    }

    // ── 3.5 · POST /api/menuapp/v1/ventas/{referencia}/anular ────────────────

    /**
     * El pedido se canceló después de aceptado: se anula la venta (lo que repone la materia
     * prima) y, si ya se había cobrado en caja, se revierte ese ingreso. Idempotente.
     * El reembolso de Mercado Pago lo hace MenuApp, no Dewoc.
     */
    public function anular(string $referencia = ''): void
    {
        $this->requireApiToken();

        $venta   = $this->ventaPorReferencia($referencia);
        $ventaId = (int)$venta['id'];

        if ($venta['estado'] === 'anulada') {
            $this->json(['venta_id' => $ventaId, 'referencia' => $venta['referencia_externa'], 'ya_anulada' => true], 200);
        }

        $this->ventaModel->anular($ventaId);
        $cajaRevertida = $this->ventaModel->revertirCobroEnCaja($ventaId);
        // Si quedó marcada como cobro pendiente y nunca se imputó, ya no hay nada que cobrar.
        $this->ventaModel->update($ventaId, ['cobro_pendiente' => 0]);

        logApp("API MenuApp: venta #{$ventaId} anulada (referencia {$venta['referencia_externa']})"
            . ($cajaRevertida ? ', ingreso de caja revertido.' : '.'));

        $this->json([
            'venta_id'       => $ventaId,
            'referencia'     => $venta['referencia_externa'],
            'ya_anulada'     => false,
            'caja_revertida' => $cajaRevertida,
        ], 200);
    }

    /** Busca la venta por referencia externa o corta con 404. */
    private function ventaPorReferencia(string $referencia): array
    {
        $referencia = trim(urldecode($referencia));
        if ($referencia === '') {
            $this->json(['error' => 'datos_invalidos', 'detalle' => 'Falta la referencia en la URL.'], 422);
        }

        $venta = $this->ventaModel->buscarPorReferenciaExterna($referencia);
        if (!$venta) {
            $this->json(['error' => 'venta_inexistente', 'referencia' => $referencia], 404);
        }
        return $venta;
    }
}
