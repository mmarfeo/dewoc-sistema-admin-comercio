<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/SolicitudModel.php';
require_once BASE_PATH . '/app/Models/SolicitudRecurrenteModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
require_once BASE_PATH . '/app/Models/NotificacionModel.php';
require_once BASE_PATH . '/core/WebPush.php';
require_once BASE_PATH . '/core/AuditLog.php';

class SolicitudesController extends Controller
{
    private SolicitudModel         $model;
    private PushSubscriptionModel  $pushModel;

    public function __construct()
    {
        $this->model     = new SolicitudModel();
        $this->pushModel = new PushSubscriptionModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        // "Tareas asignadas" (ver docs/tareas-pendientes.md #2) e "Inventario" viven como
        // pestañas más acá en vez de módulos separados — cada una es su propia tabla
        // (tareas_asignadas / solicitudes tipo=inventario), solo comparten pantalla.
        $vista = $this->param('vista', 'solicitudes');
        if (!in_array($vista, ['solicitudes', 'inventario', 'tareas'], true)) $vista = 'solicitudes';
        $estado = $this->param('estado', '');

        require_once BASE_PATH . '/app/Models/TareaAsignadaModel.php';
        require_once BASE_PATH . '/app/Models/UsuarioModel.php';
        $tareaModel = new TareaAsignadaModel();
        $usuario    = Session::user();

        // Quien puede resolver solicitudes / asignar tareas ve el listado completo (lo
        // necesita para gestionarlo); el resto solo ve lo suyo — lo que pidió, o lo que
        // le asignaron a él puntualmente / a "quien tenga la caja abierta" — no lo de
        // todos los demás usuarios.
        $veTodasSolicitudes = canEdit('solicitudes');
        $veTodasTareas      = canEdit('tareas_asignar');

        $solicitudes = [];
        if ($vista === 'solicitudes') {
            // Esta pestaña es solo pedidos de compra/tareas puntuales — el inventario
            // tiene su propia pestaña y su propio listado más abajo.
            $solicitudes = array_values(array_filter(
                $this->model->findAllConDetalle([
                    'estado'     => $estado,
                    'usuario_id' => $veTodasSolicitudes ? null : (int)$usuario['id'],
                ]),
                fn($s) => $s['tipo'] !== 'inventario'
            ));
        }

        $solicitudesInventario = [];
        $recurrentes = [];
        $categoriasProducto = $categoriasMateriaPrima = $categoriasInsumo = $ingredientesGenericos = [];
        if ($vista === 'inventario') {
            $solicitudesInventario = $this->model->findAllConDetalle([
                'estado'     => $estado,
                'tipo'       => 'inventario',
                'usuario_id' => $veTodasSolicitudes ? null : (int)$usuario['id'],
            ]);
            foreach ($solicitudesInventario as &$s) {
                $s['criterios'] = $this->model->getCriterios((int)$s['id']);
                // Para que quien la creó pueda ver qué productos/materias primas/insumos
                // quedaron incluidos en concreto, no solo la categoría/criterio elegido.
                $s['items_resueltos'] = $this->model->resolverNombresItemsParaInventario((int)$s['id']);
            }
            unset($s);

            $recurrenteModel = new SolicitudRecurrenteModel();
            $recurrentes = $veTodasSolicitudes ? $recurrenteModel->findAllFiltrado() : [];
            foreach ($recurrentes as &$r) {
                $r['criterios'] = $recurrenteModel->getCriterios((int)$r['id']);
            }
            unset($r);

            // Solo categorías/productos activos: no tiene sentido pedirle a alguien que
            // cuente algo que ya está dado de baja.
            $catModel = new CategoriaModel();
            $categoriasProducto     = $catModel->findAllConPadre('producto', true);
            $categoriasMateriaPrima = $catModel->findAllConPadre('materia_prima', true);
            $categoriasInsumo       = $catModel->findAllConPadre('insumo', true);
            $ingredientesGenericos  = (new IngredienteGenericoModel())->listar();
        }

        $this->render('solicitudes.index', [
            'pageTitle'   => 'Solicitudes',
            'breadcrumb'  => ['Solicitudes' => null],
            'vista'       => $vista,
            'filtroEstado'=> $estado,
            'solicitudes' => $solicitudes,
            'solicitudesInventario' => $solicitudesInventario,
            'recurrentes' => $recurrentes,
            'tareas'          => $vista === 'tareas'
                ? $tareaModel->listar($estado, $veTodasTareas ? null : (int)$usuario['id'], puedeVerTareasGenerales())
                : [],
            'usuariosActivos' => (new UsuarioModel())->findActivos(),
            // Catálogo para armar los pickers de criterios (categorías/ítems) del
            // formulario de inventario, sin una llamada AJAX extra en la carga inicial.
            'categoriasProducto'      => $categoriasProducto,
            'categoriasMateriaPrima'  => $categoriasMateriaPrima,
            'categoriasInsumo'        => $categoriasInsumo,
            'ingredientesGenericos'   => $ingredientesGenericos,
        ]);
    }

    /** AJAX: solicitud de inventario pendiente que le corresponde ver al usuario logueado — la consulta el cartel del POS, con polling (ver docs/tareas-pendientes.md #2, mismo patrón que tareas/pendiente-para-mi). */
    public function pendienteParaMi(): void
    {
        $this->requireAuth();
        $usuario   = Session::user();
        $solicitud = $this->model->pendienteParaUsuario((int)$usuario['id']);
        $this->json(['ok' => true, 'solicitud' => $solicitud ?: null]);
    }

    /**
     * Aceptar/rechazar/aplazar/finalizar una solicitud de inventario desde el cartel de
     * ventas/pos — mismo flujo que TareasController con tareas_asignadas (ver
     * docs/tareas-pendientes.md #2), aplicado acá a solicitudes tipo=inventario.
     */
    public function aceptarInventario(): void
    {
        [$s, $usuario] = $this->obtenerSolicitudInventarioAccionable();
        $this->model->aceptar((int)$s['id'], (int)$usuario['id']);
        AuditLog::log('aceptar_solicitud_inventario', 'solicitudes', "Solicitud #{$s['id']} aceptada: {$s['item_nombre']}");
        $this->json(['ok' => true, 'mensaje' => 'Solicitud aceptada.']);
    }

    public function rechazarInventario(): void
    {
        $motivo = trim($this->input('motivo', ''));
        if (!$motivo) $this->json(['ok' => false, 'error' => 'El motivo es obligatorio.'], 422);

        [$s, $usuario] = $this->obtenerSolicitudInventarioAccionable();
        $this->model->rechazar((int)$s['id'], (int)$usuario['id'], $motivo);
        AuditLog::log('rechazar_solicitud_inventario', 'solicitudes', "Solicitud #{$s['id']} rechazada: {$motivo}");
        $this->json(['ok' => true, 'mensaje' => 'Solicitud rechazada.']);
    }

    public function aplazarInventario(): void
    {
        $minutos = (int)$this->input('minutos', 0);
        if (!in_array($minutos, [15, 30, 60], true)) {
            $this->json(['ok' => false, 'error' => 'Tiempo de aplazamiento inválido.'], 422);
        }

        [$s] = $this->obtenerSolicitudInventarioAccionable();
        $this->model->aplazar((int)$s['id'], $minutos);
        $this->json(['ok' => true, 'mensaje' => "Recordatorio pospuesto {$minutos} minutos."]);
    }

    public function finalizarInventario(): void
    {
        [$s, $usuario] = $this->obtenerSolicitudInventarioAccionable();
        if ($s['estado'] !== 'en_proceso') {
            $this->json(['ok' => false, 'error' => 'La solicitud todavía no fue aceptada.'], 422);
        }
        $this->model->cambiarEstado((int)$s['id'], 'resuelta', (int)$usuario['id']);
        AuditLog::log('finalizar_solicitud_inventario', 'solicitudes', "Solicitud #{$s['id']} finalizada: {$s['item_nombre']}");
        $this->json(['ok' => true, 'mensaje' => 'Inventario finalizado.']);
    }

    /** Lee id del POST, valida que sea una solicitud de inventario accionable y que el usuario logueado pueda accionarla. Corta con json de error si no. */
    private function obtenerSolicitudInventarioAccionable(): array
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)$this->input('id', 0);
        $s  = $id ? $this->model->findById($id) : null;
        if (!$s || $s['eliminado'] || $s['tipo'] !== 'inventario') {
            $this->json(['ok' => false, 'error' => 'Solicitud no encontrada.'], 404);
        }
        if (!in_array($s['estado'], ['pendiente', 'en_proceso'], true)) {
            $this->json(['ok' => false, 'error' => 'Esta solicitud ya fue resuelta.'], 409);
        }

        $usuario = Session::user();
        if ($s['asignado_a'] !== null && (int)$s['asignado_a'] !== (int)$usuario['id']) {
            $this->json(['ok' => false, 'error' => 'No podés accionar esta solicitud.'], 403);
        }

        return [$s, $usuario];
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $tipo      = $this->input('tipo', '');
        $prioridad = $this->input('prioridad', 'media');
        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) $prioridad = 'media';

        if (!in_array($tipo, ['producto', 'materia_prima', 'tarea', 'inventario'], true)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }

        $usuario     = Session::user();
        $descripcion = trim($this->input('descripcion', ''));

        if ($tipo === 'inventario') {
            $criterios = $this->_criteriosDesdeInput();
            if (!$criterios) {
                $this->json(['ok' => false, 'error' => 'Elegí al menos una categoría o ítem para el inventario.'], 422);
            }
            $criteriosConNombre = $this->model->resolverNombresCriterios($criterios);
            $itemNombre          = $this->model->etiquetaCriterios($criteriosConNombre);

            $id = $this->model->crear([
                'tipo'        => 'inventario',
                'item_id'     => null,
                'item_nombre' => $itemNombre,
                'descripcion' => $descripcion ?: null,
                'prioridad'   => $prioridad,
                'estado'      => 'pendiente',
                'usuario_id'  => $usuario['id'],
                'asignado_a'  => (int)$this->input('asignado_a', 0) ?: null,
            ], $criterios);

            $this->notificarAdmins('inventario', $itemNombre, $prioridad, $usuario['nombre'] ?? 'Un usuario');
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Solicitud de inventario enviada.']);
        }

        $itemId     = (int)$this->input('item_id', 0) ?: null;
        $itemNombre = trim($this->input('item_nombre', ''));
        if ($itemNombre === '') {
            $this->json(['ok' => false, 'error' => 'Indicá qué se necesita.'], 422);
        }

        $id = $this->model->crear([
            'tipo'        => $tipo,
            'item_id'     => $itemId,
            'item_nombre' => $itemNombre,
            'descripcion' => $descripcion ?: null,
            'prioridad'   => $prioridad,
            'estado'      => 'pendiente',
            'usuario_id'  => $usuario['id'],
        ]);

        $this->notificarAdmins($tipo, $itemNombre, $prioridad, $usuario['nombre'] ?? 'Un usuario');

        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Solicitud enviada.']);
    }

    /** Parsea y valida `criterios_json` ([{tipo_criterio, referencia_id}, ...]) del formulario de inventario */
    private function _criteriosDesdeInput(): array
    {
        $raw = json_decode($this->input('criterios_json', '[]'), true);
        if (!is_array($raw)) return [];

        $tiposValidos = ['categoria', 'producto', 'materia_prima', 'insumo', 'ingrediente_generico'];
        $out = [];
        foreach ($raw as $c) {
            $tipo = $c['tipo_criterio'] ?? '';
            $ref  = (int)($c['referencia_id'] ?? 0);
            if (!in_array($tipo, $tiposValidos, true) || $ref <= 0) continue;
            $out[] = ['tipo_criterio' => $tipo, 'referencia_id' => $ref];
        }
        return $out;
    }

    /**
     * Corrige una solicitud ya creada (tipo/detalle/descripción/prioridad/estado/a quién
     * se le asigna si es tarea) — por si faltó algo al cargarla, o se tocó "En proceso"/
     * "Resuelta" por error y hay que volver atrás. Mismo permiso que cambiarEstado(). Solo
     * para producto/materia_prima/tarea: las de tipo=inventario tienen su propio detalle
     * de criterios y no pasan por esta pantalla (ya vienen excluidas del listado que
     * alimenta este botón).
     */
    public function actualizar(): void
    {
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $tipo = $this->input('tipo', '');
        if (!in_array($tipo, ['producto', 'materia_prima', 'tarea'], true)) {
            $this->json(['ok' => false, 'error' => 'Tipo inválido.'], 422);
        }

        $itemNombre = trim($this->input('item_nombre', ''));
        if ($itemNombre === '') $this->json(['ok' => false, 'error' => 'Indicá qué se necesita.'], 422);

        $prioridad = $this->input('prioridad', 'media');
        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) $prioridad = 'media';

        $estado = $this->input('estado', 'pendiente');
        if (!in_array($estado, ['pendiente', 'en_proceso', 'resuelta'], true)) $estado = 'pendiente';

        $data = [
            'tipo'        => $tipo,
            'item_nombre' => $itemNombre,
            'descripcion' => trim($this->input('descripcion', '')) ?: null,
            'prioridad'   => $prioridad,
            'estado'      => $estado,
        ];

        // Si se vuelve para atrás desde "resuelta" (ej. se tocó por error), no debe quedar
        // un "resuelto por/cuándo" de una resolución que ya no es real.
        if ($estado === 'resuelta') {
            $data['resuelto_por'] = (int)Session::user()['id'];
            $data['resuelto_at']  = date('Y-m-d H:i:s');
        } else {
            $data['resuelto_por'] = null;
            $data['resuelto_at']  = null;
        }

        $this->model->update($id, $data);

        $this->json(['ok' => true, 'mensaje' => 'Solicitud actualizada.']);
    }

    /**
     * Convierte una solicitud tipo=tarea en una tarea asignada de verdad — con seguimiento
     * de aceptar/rechazar/finalizar y cartel en ventas/pos, no solo un dato informativo.
     * Antes, editar una solicitud y ponerle "Asignar a" solo guardaba un dato suelto (la
     * columna `asignado_a`) sin generar nada en `tareas_asignadas`, así que no aparecía ni
     * en "Tareas asignadas" ni en el cartel del POS — confuso, porque se veía igual que
     * asignar de verdad. Esto reemplaza a eso: la solicitud original se da de baja (no
     * queda duplicada en el listado) y en su lugar se crea la tarea asignada real.
     */
    public function convertirATarea(): void
    {
        $this->requireCanEdit('tareas_asignar');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        $solicitud = $id ? $this->model->findById($id) : false;
        if (!$solicitud || $solicitud['eliminado'] || $solicitud['tipo'] !== 'tarea') {
            $this->json(['ok' => false, 'error' => 'Solicitud no encontrada.'], 404);
        }

        $modoDestino = $this->input('modo_destino', '');
        $destinoId   = (int)$this->input('usuario_destino_id', 0) ?: null;
        if (!in_array($modoDestino, ['usuario_especifico', 'turno_abierto', 'administradores'], true)) {
            $this->json(['ok' => false, 'error' => 'Modo de destino inválido.'], 422);
        }
        if ($modoDestino === 'usuario_especifico' && !$destinoId) {
            $this->json(['ok' => false, 'error' => 'Elegí a quién asignarle la tarea.'], 422);
        }

        $itemNombre  = trim($this->input('item_nombre', '')) ?: $solicitud['item_nombre'];
        $descripcion = trim($this->input('descripcion', '')) ?: ($solicitud['descripcion'] ?: null);

        require_once BASE_PATH . '/app/Models/TareaAsignadaModel.php';
        $tareaModel = new TareaAsignadaModel();
        $usuario    = Session::user();

        $tareaId = $tareaModel->crear((int)$usuario['id'], $itemNombre, $descripcion, $modoDestino, $destinoId);
        $this->model->softDelete($id);

        AuditLog::log('convertir_tarea', 'tareas', "Solicitud #{$id} convertida en tarea asignada #{$tareaId}: {$itemNombre}");

        $this->json(['ok' => true, 'id' => $tareaId, 'mensaje' => 'Convertida en tarea asignada.']);
    }

    public function cambiarEstado(): void
    {
        // Antes exigía el rol admin a secas, ignorando el permiso personalizado "solicitudes"
        // que se le puede dar a otros roles (ej. un encargado) desde su ficha de usuario —
        // ese toggle no tenía ningún efecto acá. Ahora respeta ese permiso, igual que el resto
        // de las acciones de "editar" del sistema (mismo criterio que VentasController con
        // ventas_anulaciones).
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $id     = (int)$this->input('id', 0);
        $estado = $this->input('estado', '');

        if (!$id || !in_array($estado, ['pendiente', 'en_proceso', 'resuelta'], true)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $usuario = Session::user();
        $this->model->cambiarEstado($id, $estado, (int)$usuario['id']);

        $this->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
    }

    /** AJAX: búsqueda combinada de productos + materias primas para el selector */
    public function buscarItems(): void
    {
        $this->requireAuth();
        $q = $this->param('q', '');
        if (strlen($q) < 1) { $this->json([]); }
        $this->json($this->model->buscarItems($q));
    }

    /** Ítems puntuales de una categoría, para tildarlos uno por uno en vez de pedir la categoría entera. */
    public function itemsPorCategoria(): void
    {
        $this->requireAuth();
        $categoriaId = (int)$this->param('categoria_id', 0);
        if (!$categoriaId) $this->json([]);
        $this->json($this->model->itemsPorCategoria($categoriaId));
    }

    // ── Web Push ──────────────────────────────────────────────────────────────

    public function vapidPublicKey(): void
    {
        $this->requireAuth();
        $this->json(['publicKey' => VAPID_PUBLIC_KEY]);
    }

    public function suscribir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $endpoint = $this->input('endpoint', '');
        $p256dh   = $this->input('p256dh', '');
        $auth     = $this->input('auth', '');

        if (!$endpoint || !$p256dh || !$auth) {
            $this->json(['ok' => false, 'error' => 'Suscripción inválida.'], 422);
        }

        $usuario = Session::user();
        $this->pushModel->guardar(
            (int)$usuario['id'],
            $endpoint,
            $p256dh,
            $auth,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        );

        $this->json(['ok' => true]);
    }

    public function desuscribir(): void
    {
        $this->requireAuth();
        Csrf::check();

        $endpoint = $this->input('endpoint', '');
        if ($endpoint) {
            $this->pushModel->eliminarPorEndpoint($endpoint);
        }

        $this->json(['ok' => true]);
    }

    /**
     * Push a quien puede resolver una solicitud: admins siempre, más cualquier otro rol con
     * el permiso "solicitudes: editar" habilitado desde su ficha de usuario (ej. un encargado)
     * — antes se limitaba solo a admins, sin mirar ese permiso, así que dárselo a alguien no
     * tenía ningún efecto real (ni lo notificaba, ni lo dejaba resolver — ver cambiarEstado()).
     * Las suscripciones vencidas/inválidas se eliminan.
     */
    private function notificarAdmins(string $tipo, string $itemNombre, string $prioridad, string $creadorNombre): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        $etiquetasTipo = [
            'producto'      => 'Comprar producto',
            'materia_prima' => 'Comprar materia prima',
            'tarea'         => 'Tarea pendiente',
            'inventario'    => 'Recuento de inventario',
        ];

        $titulo = $etiquetasTipo[$tipo] . ($prioridad === 'alta' ? ' (URGENTE)' : '');
        $cuerpo = $itemNombre . ' — pedido por ' . $creadorNombre;
        $url    = rtrim(BASE_URL, '/') . '/solicitudes';
        $payload = json_encode([
            'title' => $titulo,
            'body'  => $cuerpo,
            'url'   => $url,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        $suscripciones = $this->pushModel->findSuscripcionesConPermiso('solicitudes', 'editar');
        (new NotificacionModel())->crearParaSuscripciones($suscripciones, $titulo, $cuerpo, $url, 'solicitud');
        foreach ($suscripciones as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload,
                    VAPID_PUBLIC_KEY,
                    VAPID_PRIVATE_KEY,
                    VAPID_SUBJECT
                );
                // 404/410 = el navegador invalidó la suscripción (desinstaló la app, revocó el permiso, etc.)
                if (in_array($res['status'], [404, 410], true)) {
                    $this->pushModel->eliminarPorId((int)$sub['id']);
                }
            } catch (\Throwable $e) {
                logApp('Error enviando push a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    // ── Plantillas recurrentes (solicitudes de inventario semanales) ────────────

    public function crearRecurrente(): void
    {
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $data = $this->_recogerFormRecurrente();
        if ($data === null) {
            $this->json(['ok' => false, 'error' => 'Completá los datos requeridos.'], 422);
        }
        $criterios = $this->_criteriosDesdeInput();
        if (!$criterios) {
            $this->json(['ok' => false, 'error' => 'Elegí al menos una categoría o ítem para el inventario.'], 422);
        }

        $data['usuario_id'] = Session::user()['id'];
        $data['eliminado']  = 0;

        $id = (new SolicitudRecurrenteModel())->crear($data, $criterios);
        $this->_generarSolicitudesRecurrentesAhoraSiCorresponde();
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Plantilla recurrente creada.']);
    }

    public function actualizarRecurrente(): void
    {
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->_recogerFormRecurrente();
        if ($data === null) {
            $this->json(['ok' => false, 'error' => 'Completá los datos requeridos.'], 422);
        }
        $criterios = $this->_criteriosDesdeInput();
        if (!$criterios) {
            $this->json(['ok' => false, 'error' => 'Elegí al menos una categoría o ítem para el inventario.'], 422);
        }

        (new SolicitudRecurrenteModel())->actualizar($id, $data, $criterios);
        $this->_generarSolicitudesRecurrentesAhoraSiCorresponde();
        $this->json(['ok' => true, 'mensaje' => 'Plantilla recurrente actualizada.']);
    }

    public function toggleActivoRecurrente(): void
    {
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        (new SolicitudRecurrenteModel())->toggleActivo($id);
        $this->_generarSolicitudesRecurrentesAhoraSiCorresponde();
        $this->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
    }

    public function eliminarRecurrente(): void
    {
        $this->requireCanEdit('solicitudes');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        (new SolicitudRecurrenteModel())->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Plantilla recurrente eliminada.']);
    }

    /** Valida y arma el array de datos de una plantilla recurrente. Devuelve null si falta algo obligatorio. */
    private function _recogerFormRecurrente(): ?array
    {
        $descripcion = trim($this->input('descripcion', ''));
        $diaSemana   = (int)$this->input('dia_semana', 0);
        $prioridad   = $this->input('prioridad', 'media');

        if ($descripcion === '') return null;
        if ($diaSemana < 1 || $diaSemana > 7) return null;
        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) $prioridad = 'media';

        return [
            'descripcion' => $descripcion,
            'dia_semana'  => $diaSemana,
            'prioridad'   => $prioridad,
            'asignado_a'  => (int)$this->input('asignado_a', 0) ?: null,
        ];
    }

    /**
     * Crear/editar/reactivar una plantilla es una acción deliberada del usuario:
     * generamos al toque en vez de esperar el chequeo perezoso de una vez por día
     * (mismo criterio que GastosRecurrentesController::_generarAhoraSiCorresponde()).
     */
    private function _generarSolicitudesRecurrentesAhoraSiCorresponde(): void
    {
        try {
            (new SolicitudRecurrenteModel())->generarPendientes((int)(Session::user()['id'] ?? 0));
        } catch (\Throwable $e) {
            logApp('Error generando solicitudes recurrentes (al guardar plantilla): ' . $e->getMessage());
        }
    }
}
