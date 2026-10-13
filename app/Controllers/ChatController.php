<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ChatModel.php';
require_once BASE_PATH . '/app/Models/ChatAccesoModel.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/app/Models/SocioModel.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
require_once BASE_PATH . '/app/Models/AyudaModel.php';
require_once BASE_PATH . '/app/Models/ReportesModel.php';
require_once BASE_PATH . '/app/Models/ReporteSemanalModel.php';
require_once BASE_PATH . '/app/Models/StockModel.php';
require_once BASE_PATH . '/app/Models/ListaComprasModel.php';
require_once BASE_PATH . '/app/Models/ClienteModel.php';

class ChatController extends Controller
{
    private ChatModel $model;
    private AyudaModel $ayudaModel;

    public function __construct()
    {
        $this->model      = new ChatModel();
        $this->ayudaModel = new AyudaModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $usuarioId = (int)Session::user()['id'];
        $historial = $this->model->getHistorial($usuarioId);

        $archivoCompartido = $_SESSION['chat_compartido'] ?? null;
        unset($_SESSION['chat_compartido']);

        $this->render('chat.index', [
            'pageTitle'         => 'Asistente IA',
            'breadcrumb'        => ['Chat IA' => null],
            'historial'         => $historial,
            'apiActiva'         => GEMINI_API_KEY !== '',
            'archivoCompartido' => $archivoCompartido,
            'accesosDirectos'   => (new ChatAccesoModel())->findAsignadosUsuario($usuarioId),
            'unidadesMedida'    => Database::getInstance()->query('SELECT id, nombre, abreviatura FROM unidades_medida ORDER BY nombre')->fetchAll(\PDO::FETCH_ASSOC),
            'socios'            => (new SocioModel())->findAllActivos(),
            'genericos'         => (new IngredienteGenericoModel())->listar(),
        ]);
    }

    /**
     * Visor de solo lectura para el admin: ver qué escribió/hizo otro usuario en el Chat
     * IA (ej. una compra que un vendedor cargó y no se registró bien), sin tocar la
     * sesión de ese usuario ni permitir escribir/confirmar nada desde acá.
     */
    public function verUsuario(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $usuarioId = (int) $this->param('id');
        $usuario   = $usuarioId ? (new UsuarioModel())->findById($usuarioId) : false;
        if (!$usuario) { http_response_code(404); echo 'Usuario no encontrado.'; return; }

        $historial = $this->model->getHistorial($usuarioId, 100);

        $this->render('chat.index', [
            'pageTitle'         => 'Chat IA — ' . $usuario['nombre'] . ' (solo lectura)',
            'breadcrumb'        => ['Usuarios' => url('usuarios'), 'Chat IA de ' . $usuario['nombre'] => null],
            'historial'         => $historial,
            'apiActiva'         => GEMINI_API_KEY !== '',
            'archivoCompartido' => null,
            'accesosDirectos'   => [],
            'unidadesMedida'    => Database::getInstance()->query('SELECT id, nombre, abreviatura FROM unidades_medida ORDER BY nombre')->fetchAll(\PDO::FETCH_ASSOC),
            'socios'            => (new SocioModel())->findAllActivos(),
            'genericos'         => (new IngredienteGenericoModel())->listar(),
            'soloLectura'       => true,
            'usuarioObjetivo'   => $usuario,
        ]);
    }

    /** Sirve un adjunto de factura del chat: el dueño del mensaje, o cualquier admin. */
    public function verAdjunto(): void
    {
        $this->requireAuth();

        $mensajeId = (int) $this->param('id');
        $msg       = $mensajeId ? $this->model->getMensajePorId($mensajeId) : null;

        if (!$msg || !$msg['archivo_adjunto']) { http_response_code(404); exit; }

        $esDueno = (int)$msg['usuario_id'] === (int)Session::user()['id'];
        $esAdmin = (Session::user()['rol'] ?? '') === 'admin';
        if (!$esDueno && !$esAdmin) { http_response_code(403); exit; }

        $path = BASE_PATH . '/storage/chat_adjuntos/' . basename($msg['archivo_adjunto']);
        if (!is_file($path)) { http_response_code(404); exit; }

        header('Content-Type: ' . ($msg['archivo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($msg['archivo_adjunto']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * AJAX de solo lectura para la burbuja flotante (assets/js/chat-flotante.js, ver
     * docs/tareas-pendientes.md, sección Help Desk) — misma conversación que /chat, pero
     * aplanada a lo mínimo para pintar un panel chico. Enviar un mensaje nuevo sigue yendo
     * por POST chat/mensaje de siempre, esto solo alimenta el historial al abrir el panel.
     */
    public function historialReciente(): void
    {
        $this->requireAuth();
        $usuarioId = (int) Session::user()['id'];
        $historial = $this->model->getHistorial($usuarioId, 20);

        $mensajes = array_map(function (array $m): array {
            $tipo    = 'texto';
            $payload = null;

            if (!empty($m['datos_json'])) {
                $decoded = json_decode($m['datos_json'], true) ?: [];
                if (($decoded['tipo'] ?? '') === 'guia_ayuda') {
                    $tipo    = 'guia_ayuda';
                    $payload = $decoded;
                } else {
                    // Tarjeta de compra/acción (pendiente o ya resuelta): el flotante no la
                    // sabe pintar (selects editables, adjuntos) — se muestra como placeholder
                    // con link a /chat, ver chat-flotante.js.
                    $tipo = 'pesado';
                }
            }

            return [
                'id'         => (int) $m['id'],
                'rol'        => $m['rol'],
                'contenido'  => $m['contenido'],
                'tipo'       => $tipo,
                'payload'    => $payload,
                'created_at' => $m['created_at'],
            ];
        }, $historial);

        $this->json(['ok' => true, 'mensajes' => $mensajes]);
    }

    /**
     * Bypass de Gemini para accesos directos de solo lectura con parámetros fijos (ver
     * docs/chat-accesos-sin-llm-analisis.md) — corre una consulta PHP directa y arma la
     * respuesta con una plantilla de texto, sin ninguna llamada a la API. Los accesos sin
     * `accion_interna` (custom del admin, o los de acción como "Nuevo cliente") NO pegan
     * acá — el frontend los sigue mandando a mensaje() como siempre.
     */
    public function accionDirecta(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuarioId = (int) Session::user()['id'];
        $accesoId  = (int) $this->input('acceso_id', 0);

        $accesoModel = new ChatAccesoModel();
        $acceso      = $accesoModel->findById($accesoId);

        // Defensa en profundidad: aunque el chip no debería aparecer si no está asignado,
        // no confiamos en el id que mande el cliente sin volver a chequear del lado servidor.
        $asignado = $acceso && in_array($accesoId, $accesoModel->idsAsignadosUsuario($usuarioId), true);
        if (!$acceso || !$acceso['activo'] || empty($acceso['accion_interna']) || !$asignado) {
            $this->json(['ok' => false, 'error' => 'Acceso inválido.'], 404);
        }

        $this->model->guardarMensaje($usuarioId, 'user', $acceso['texto']);
        $texto = $this->ejecutarAccionDirecta($acceso['accion_interna']);
        $this->model->guardarMensaje($usuarioId, 'assistant', $texto);

        $this->json(['ok' => true, 'tipo' => 'texto', 'respuesta' => $texto]);
    }

    private function ejecutarAccionDirecta(string $clave): string
    {
        return match ($clave) {
            'ventas_hoy'        => $this->accionVentasDia(date('Y-m-d'), 'hoy'),
            'ventas_ayer'       => $this->accionVentasDia(date('Y-m-d', strtotime('-1 day')), 'ayer'),
            'ventas_semana'     => $this->accionVentasSemana(),
            'ganancia_mensual'  => $this->accionGananciaMensual(),
            'top_productos'     => $this->accionTopProductos(),
            'stock_bajo_minimo' => $this->accionStockBajoMinimo(),
            'compras_mes'       => $this->accionComprasMes(),
            'caja_hoy'          => $this->accionCajaHoy(),
            'clientes_nuevos'   => $this->accionClientesNuevos(),
            default             => "❌ No se pudo resolver la acción '{$clave}' (puede que el catálogo de accesos esté desactualizado — avisale al administrador).",
        };
    }

    /**
     * Ventas de un día puntual (hoy o ayer), separando productos vendidos sueltos de promos
     * vendidas — bypass de Gemini vía ReportesModel::ventasPorProductoDia(), que ya excluye
     * las filas de componente de promo (precio $0) para no inflar cantidades ni mezclar
     * ambos grupos (ver comentario de ese método para el caso concreto que motivó esto).
     */
    private function accionVentasDia(string $fecha, string $etiqueta): string
    {
        $r = (new ReportesModel())->ventasPorProductoDia($fecha);
        if ($r['cantidad_ventas'] === 0) {
            return "📊 Todavía no hay ventas registradas {$etiqueta} ({$this->fmtFecha($fecha)}).";
        }

        $fmt  = static fn(float $n): string => number_format($n, 0, ',', '.');
        $resp = "📊 Ventas de {$etiqueta} ({$this->fmtFecha($fecha)}):\n"
              . "- Total: \${$fmt($r['total_dia'])} ({$r['cantidad_ventas']} ventas)\n";
        foreach ($r['por_metodo'] as $m) {
            $resp .= "  - " . ucfirst($m['metodo_pago']) . ": \${$fmt($m['total'])}\n";
        }
        $resp .= "\n";

        if ($r['unitarios']) {
            $resp .= "🧾 Productos vendidos (unitario):\n";
            foreach ($r['unitarios'] as $p) {
                $cant = number_format((float) $p['cantidad'], 0, ',', '.');
                $resp .= "- {$p['nombre']} — {$cant} unid. (\${$fmt((float) $p['total'])})\n";
            }
            $resp .= "\n";
        }

        if ($r['promos']) {
            $resp .= "🎁 Promos vendidas:\n";
            foreach ($r['promos'] as $p) {
                $cant = number_format((float) $p['cantidad'], 0, ',', '.');
                $resp .= "- {$p['nombre']} — {$cant} unid. (\${$fmt((float) $p['total'])})\n";
            }
        }

        return trim($resp);
    }

    private function accionVentasSemana(): string
    {
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d');
        $v     = (new ReporteSemanalModel())->generarResumen($desde, $hasta)['ventas'];

        $fmt  = static fn(float $n): string => number_format($n, 0, ',', '.');
        $resp = "📊 Ventas esta semana ({$this->fmtFecha($desde)} al {$this->fmtFecha($hasta)}):\n"
              . "- Total: \${$fmt($v['total'])} ({$v['cantidad']} ventas)\n";
        foreach ($v['por_metodo'] as $m) {
            $resp .= "- " . ucfirst($m['metodo_pago']) . ": \${$fmt($m['total'])}\n";
        }
        return trim($resp);
    }

    private function accionGananciaMensual(): string
    {
        $desde = date('Y-m-01');
        $hasta = date('Y-m-d');
        $m     = (new ReporteSemanalModel())->generarResumen($desde, $hasta)['margen'];

        $fmt = static fn(float $n): string => number_format($n, 0, ',', '.');
        return "💰 Ganancia bruta de este mes ({$this->fmtFecha($desde)} al {$this->fmtFecha($hasta)}):\n"
             . "- Ingresos: \${$fmt($m['ingresos'])}\n"
             . "- Costo de mercadería vendida: \${$fmt($m['costo'])}\n"
             . "- Margen bruto: \${$fmt($m['margen'])} ({$m['margen_pct']}%)";
    }

    private function accionTopProductos(): string
    {
        $desde = date('Y-m-01');
        $hasta = date('Y-m-d');
        $top   = (new ReportesModel())->topProductos($desde, $hasta, 5);

        if (!$top) return "🏆 Todavía no hay ventas este mes para armar un top de productos.";

        $fmt  = static fn(float $n): string => number_format($n, 0, ',', '.');
        $resp = "🏆 Top 5 productos más vendidos este mes ({$this->fmtFecha($desde)} al {$this->fmtFecha($hasta)}):\n";
        foreach ($top as $i => $p) {
            $cant = number_format((float) $p['cantidad_vendida'], 0, ',', '.');
            $resp .= ($i + 1) . ". {$p['nombre']} — {$cant} unidades (\${$fmt((float) $p['total_vendido'])})\n";
        }
        return trim($resp);
    }

    private function accionStockBajoMinimo(): string
    {
        $items = (new StockModel())->itemsBajoMinimo();
        if (!$items) return "📦 Ningún ítem está por debajo de su stock mínimo ahora mismo. ✅";

        $resp   = "📦 Ítems bajo el stock mínimo (" . count($items) . "):\n";
        $limite = 20;
        foreach (array_slice($items, 0, $limite) as $it) {
            $resp .= "- {$it['nombre']} ({$it['tipo']}): {$it['stock_actual']} / mín. {$it['stock_minimo']}\n";
        }
        if (count($items) > $limite) $resp .= "...y " . (count($items) - $limite) . " más.\n";
        return trim($resp);
    }

    private function accionComprasMes(): string
    {
        $desde = date('Y-m-01');
        $hasta = date('Y-m-d');
        $c     = (new ReportesModel())->resumenCompras($desde, $hasta);

        $fmt = static fn(float $n): string => number_format((float) $n, 0, ',', '.');
        return "🛒 Compras a proveedores este mes ({$this->fmtFecha($desde)} al {$this->fmtFecha($hasta)}):\n"
             . "- Total: \${$fmt((float) $c['total_bruto'])} ({$c['total_facturas']} facturas)";
    }

    private function accionCajaHoy(): string
    {
        $hoy = date('Y-m-d');
        $c   = (new ReportesModel())->resumenCaja($hoy, $hoy);

        if ((int) $c['total_turnos'] === 0) return "🏧 Todavía no hay ningún turno de caja registrado hoy.";

        $fmt = static fn(float $n): string => number_format((float) $n, 0, ',', '.');
        return "🏧 Movimiento de caja hoy:\n"
             . "- Turnos: {$c['total_turnos']}\n"
             . "- Calculado por el sistema: \${$fmt((float) $c['total_calculado'])}\n"
             . "- Declarado por el cajero: \${$fmt((float) $c['total_declarado'])}\n"
             . "- Diferencia: \${$fmt((float) $c['diferencia_total'])}";
    }

    private function accionClientesNuevos(): string
    {
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d');
        $nuevos = (new ClienteModel())->nuevosPorPeriodo($desde, $hasta);

        if (!$nuevos) return "👥 No se registraron clientes nuevos esta semana.";

        $resp = "👥 Clientes nuevos esta semana ({$this->fmtFecha($desde)} al {$this->fmtFecha($hasta)}): " . count($nuevos) . "\n";
        foreach (array_slice($nuevos, 0, 5) as $c) {
            $resp .= "- {$c['nombre']}\n";
        }
        if (count($nuevos) > 5) $resp .= "...y " . (count($nuevos) - 5) . " más.\n";
        return trim($resp);
    }

    private function fmtFecha(string $ymd): string
    {
        return date('d/m', strtotime($ymd));
    }

    public function mensaje(): void
    {
        // Cada confirmación puede requerir 4-5 llamadas a Gemini encadenadas
        set_time_limit(300);

        $this->requireAuth();
        Csrf::check();

        $pregunta = trim($_POST['mensaje'] ?? '');

        // Imagen adjunta (factura)
        $imagenBase64 = null;
        $imagenMime   = null;
        $archivoGuardado = null;
        if (!empty($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $mime    = $_FILES['imagen']['type'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
            if (in_array($mime, $allowed, true) && $_FILES['imagen']['size'] <= 10 * 1024 * 1024) {
                $bytes        = file_get_contents($_FILES['imagen']['tmp_name']);
                $imagenBase64 = base64_encode($bytes);
                $imagenMime   = $mime;

                // Se guarda el archivo original en disco (antes solo se mandaba a Gemini y se
                // descartaba) para que quede auditable: propio historial, o el de otro usuario
                // visto por un admin vía ChatController::verUsuario().
                $ext = match ($mime) {
                    'image/jpeg'      => 'jpg',
                    'image/png'       => 'png',
                    'image/webp'      => 'webp',
                    'image/gif'       => 'gif',
                    'application/pdf' => 'pdf',
                    default           => 'bin',
                };
                $dirAdjuntos = BASE_PATH . '/storage/chat_adjuntos/';
                if (!is_dir($dirAdjuntos)) mkdir($dirAdjuntos, 0755, true);
                $nombreArchivo = 'chat_' . (int)Session::user()['id'] . '_' . uniqid('', true) . '.' . $ext;
                file_put_contents($dirAdjuntos . $nombreArchivo, $bytes);
                $archivoGuardado = $nombreArchivo;
            }
        }

        if ($pregunta === '' && $imagenBase64 === null) {
            $this->json(['ok' => false, 'error' => 'El mensaje no puede estar vacío.']);
        }
        if ($pregunta === '' && $imagenBase64 !== null) {
            $soporte = $imagenMime === 'application/pdf' ? 'PDF' : 'imagen';
            $pregunta = "Analizá este {$soporte}. Primero identificá qué tipo de comprobante es: una compra de "
                . "mercadería con stock (factura/ticket de un proveedor), un pago de servicio/alquiler/impuesto/"
                . "honorario, o un comprobante de pago de sueldo. Si no podés determinarlo con certeza, preguntame "
                . "antes de seguir. Una vez que sepas el tipo, extraé los datos y mostrame un resumen para confirmar "
                . "el registro.";
        }

        $usuarioId = (int) Session::user()['id'];
        $mensajeUsuarioId = $this->model->guardarMensaje($usuarioId, 'user', $pregunta, null, null, $archivoGuardado, $imagenMime);

        // Si el usuario sube una foto/PDF nueva mientras todavía tiene una tarjeta de compra
        // o acción sin confirmar ni cancelar, no la procesamos: dejar esa tarjeta vieja "abierta"
        // en el historial reciente que se le manda a Gemini puede confundirlo y terminar
        // mezclando proveedor/ítems de una compra con los de otra (bug reportado: subió un
        // ticket nuevo y la tarjeta que apareció tenía los datos de una compra previa sin resolver).
        if ($imagenBase64 !== null && $this->model->getUltimaTarjetaPendiente($usuarioId)) {
            $error = 'Todavía tenés una compra o acción pendiente de confirmar más arriba en el chat. '
                . 'Confirmala o cancelala antes de subir otro comprobante, así no se mezclan los datos.';
            $this->model->guardarMensaje($usuarioId, 'assistant', $error);
            $this->json(['ok' => false, 'error' => $error]);
        }

        if (GEMINI_API_KEY === '') {
            $resp = 'El Chat IA no está configurado. Completá GEMINI_API_KEY en el archivo .env.';
            $this->model->guardarMensaje($usuarioId, 'assistant', $resp);
            $this->json(['ok' => true, 'tipo' => 'texto', 'respuesta' => $resp]);
        }

        // Esquema de BD para contexto
        try {
            $esquema = $this->model->getEsquemaTablas();
        } catch (\Exception $e) {
            $esquema = [];
        }
        $esquemaTexto = '';
        foreach ($esquema as $tabla => $cols) {
            $esquemaTexto .= "- $tabla: " . implode(', ', $cols) . "\n";
        }

        // Recordatorio de productos de reventa incompletos (precio/categoría), una vez por
        // día — ver UsuarioModel::debeRecordarProductosIncompletos() y ProductoModel::findIncompletos().
        // Solo se marca "avisado hoy" cuando efectivamente hay algo para mostrar; si no hay
        // productos incompletos, el chequeo (barato) se repite en cada mensaje sin marcar nada.
        $productosIncompletos = [];
        $usuarioModel = new UsuarioModel();
        if ($usuarioModel->debeRecordarProductosIncompletos($usuarioId)) {
            require_once BASE_PATH . '/app/Models/ProductoModel.php';
            $productosIncompletos = (new ProductoModel())->findIncompletos();
            if ($productosIncompletos) {
                $usuarioModel->marcarRecordatorioProductosHoy($usuarioId);
            }
        }

        $systemPrompt = $this->buildSystemPrompt($esquemaTexto, $productosIncompletos);

        // Construir historial en formato Gemini (role: user/model, parts: [{text}])
        $historialReciente = $this->model->getHistorial($usuarioId, 20);
        $messages = [];
        foreach ($historialReciente as $m) {
            if ($m['rol'] === 'user' && trim($m['contenido']) === trim($pregunta)) continue;
            $messages[] = [
                'role'  => $m['rol'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['contenido']]],
            ];
        }
        // Mensaje actual: puede llevar imagen inline (Gemini multimodal)
        $userParts = [];
        if ($imagenBase64 !== null) {
            $userParts[] = ['inline_data' => ['mime_type' => $imagenMime, 'data' => $imagenBase64]];
        }
        $userParts[] = ['text' => $pregunta];
        $messages[] = ['role' => 'user', 'parts' => $userParts];

        // Loop de function calling (máx. 10 iteraciones)
        $tools           = $this->obtenerHerramientas();
        $sqlEjecutado    = null;
        $resultados      = null;
        $acciones        = [];
        $reporteGenerado = null;

        // El modelo se elige antes de la primera llamada, y se mantiene fijo durante todo
        // el loop de function-calling de este mensaje — nunca se cambia de modelo a mitad
        // de camino (ver el porqué en elegirModeloConCupo()).
        $modelosDisponibles = $this->obtenerCadenaModelos();
        $modeloElegido      = $this->elegirModeloConCupo($modelosDisponibles);

        for ($iter = 0; $iter < 10; $iter++) {
            $apiResp = $this->llamarGemini($systemPrompt, $messages, $tools, $mensajeUsuarioId, $iter + 1, $modeloElegido);

            // Si falla la PRIMERA llamada del mensaje (antes de que exista historial de
            // function-calling), todavía es seguro probar el resto de la cadena — a
            // diferencia de una falla a mitad de conversación, acá no hay nada que
            // "thought_signature" pueda romper. Esto rescata casos como un 503 puntual de
            // Google ("modelo con alta demanda") en el modelo elegido, sin que el usuario
            // tenga que reintentar a mano.
            if ($apiResp === null && $iter === 0) {
                foreach ($modelosDisponibles as $candidato) {
                    if ($candidato === $modeloElegido) continue;
                    $apiResp = $this->llamarGemini($systemPrompt, $messages, $tools, $mensajeUsuarioId, $iter + 1, $candidato);
                    if ($apiResp !== null) { $modeloElegido = $candidato; break; }
                }
            }

            if ($apiResp === null) {
                $error = $this->mensajeErrorGemini();
                $this->model->guardarMensaje($usuarioId, 'assistant', $error);
                $this->json(['ok' => false, 'error' => $error]);
            }

            // Parsear respuesta de Gemini
            $candidato    = $apiResp['candidates'][0] ?? [];
            $parts        = $candidato['content']['parts'] ?? [];
            $modelRole    = $candidato['content']['role']  ?? 'model';
            $finishReason = $candidato['finishReason'] ?? null;
            $textFinal    = '';
            $funcCalls    = [];

            foreach ($parts as $part) {
                if (isset($part['text']))         $textFinal .= $part['text'];
                if (isset($part['functionCall'])) $funcCalls[] = $part['functionCall'];
            }

            // Gemini terminó sin texto ni function call (ej. MAX_TOKENS por una compra con
            // muchos ítems/opciones, o bloqueo de SAFETY): sin esto, el chat mostraba una
            // burbuja vacía (solo el horario) sin explicar qué pasó.
            $respuestaVacia = empty($funcCalls) && trim($textFinal) === '';

            // A veces Gemini devuelve 200 OK con finishReason=STOP (o ni siquiera lo manda)
            // y el contenido igual viene vacío, sin ningún motivo — un comportamiento errático
            // ya visto en producción (ver /gemini-log), distinto de MAX_TOKENS/SAFETY de abajo.
            // Si es la primera llamada del mensaje, todavía es seguro probar el resto de la
            // cadena de modelos (mismo criterio que el rescate de $apiResp===null de arriba: no
            // hay thought_signature de por medio porque todavía no se ejecutó ninguna función).
            if ($respuestaVacia && (!$finishReason || $finishReason === 'STOP') && $iter === 0) {
                foreach ($modelosDisponibles as $candidatoRescate) {
                    if ($candidatoRescate === $modeloElegido) continue;
                    $reintento = $this->llamarGemini($systemPrompt, $messages, $tools, $mensajeUsuarioId, $iter + 1, $candidatoRescate);
                    if ($reintento === null) continue;

                    $candidato2    = $reintento['candidates'][0] ?? [];
                    $parts2        = $candidato2['content']['parts'] ?? [];
                    $textFinal2    = '';
                    $funcCalls2    = [];
                    foreach ($parts2 as $part2) {
                        if (isset($part2['text']))         $textFinal2 .= $part2['text'];
                        if (isset($part2['functionCall'])) $funcCalls2[] = $part2['functionCall'];
                    }
                    if (!empty($funcCalls2) || trim($textFinal2) !== '') {
                        $modeloElegido  = $candidatoRescate;
                        $parts          = $parts2;
                        $modelRole      = $candidato2['content']['role'] ?? 'model';
                        $finishReason   = $candidato2['finishReason'] ?? null;
                        $textFinal      = $textFinal2;
                        $funcCalls      = $funcCalls2;
                        $respuestaVacia = false;
                        break;
                    }
                }
            }

            if ($respuestaVacia && $finishReason && $finishReason !== 'STOP') {
                $error = match ($finishReason) {
                    'MAX_TOKENS' => 'La respuesta se cortó por ser demasiado larga (factura con muchos ítems/opciones ambiguas). Probá confirmando la compra en partes, o cargala manualmente desde el módulo Compras.',
                    'SAFETY', 'RECITATION' => 'Gemini bloqueó la respuesta por sus filtros de contenido. Reintentá, o cargá la compra manualmente desde el módulo Compras.',
                    default => "Gemini no pudo generar una respuesta ({$finishReason}). Reintentá.",
                };
                $this->model->guardarMensaje($usuarioId, 'assistant', $error);
                $this->json(['ok' => false, 'error' => $error]);
            }

            // Vacía sin ningún finishReason que lo explique (o el rescate de arriba tampoco
            // consiguió nada): antes esto caía al bloque de "respuesta final" de abajo y
            // devolvía ok:true con una burbuja en blanco, sin ninguna pista de qué pasó.
            if ($respuestaVacia) {
                $error = 'Gemini no devolvió ninguna respuesta esta vez (puede pasar de vez en cuando, sin un motivo claro). Volvé a intentar el mismo pedido.';
                $this->model->guardarMensaje($usuarioId, 'assistant', $error);
                $this->json(['ok' => false, 'error' => $error]);
            }

            // Sin function calls → respuesta final
            if (empty($funcCalls)) {
                if ($reporteGenerado !== null) {
                    $tipo = 'reporte';
                } elseif (!empty($acciones) || !is_null($resultados)) {
                    $tipo = !empty($acciones) ? 'accion' : 'sql';
                } else {
                    $tipo = 'texto';
                }
                $this->model->guardarMensaje($usuarioId, 'assistant', $textFinal, $sqlEjecutado);
                $this->json([
                    'ok'          => true,
                    'tipo'        => $tipo,
                    'respuesta'   => $textFinal,
                    'sql'         => $sqlEjecutado,
                    'resultados'  => $resultados,
                    'acciones'    => $acciones,
                    'reporte_id'  => $reporteGenerado['reporte_id'] ?? null,
                    'reporte_url' => $reporteGenerado['url']        ?? null,
                ]);
            }

            // Si el modelo pide mostrar una tarjeta de confirmación, cortamos acá: se guarda
            // como "pendiente" y se le devuelve al frontend para que renderice los botones.
            // La acción real (registrar_compra, marcar_feriado_trabajado, etc.) recién se
            // ejecuta cuando el usuario confirma desde la tarjeta — ver confirmar()/cancelar().
            // Tarjeta del help desk (mostrar_guia_ayuda): a diferencia de las de confirmación,
            // acá SOLO cortamos si el tema existe y el usuario tiene permiso sobre su módulo —
            // si no, la dejamos caer al bucle normal de abajo (ejecutarHerramienta), que le
            // devuelve un error a Gemini y este le explica en texto que no tiene acceso, en vez
            // de mostrarle un botón hacia una sección que no puede usar.
            foreach ($funcCalls as $call) {
                if (($call['name'] ?? '') !== 'mostrar_guia_ayuda') continue;

                $tema     = (string) ($call['args']['tema'] ?? '');
                $registro = $this->ayudaModel->findPorTema($tema) ?: null;
                if ($registro === null || !canView($registro['modulo'])) continue;

                $resumen = $textFinal !== '' ? $textFinal : $registro['titulo'];
                $payload = [
                    'tipo'          => 'guia_ayuda',
                    'tema'          => $tema,
                    'titulo'        => $registro['titulo'],
                    'resumen_texto' => $resumen,
                    'url'           => url($registro['url']),
                    'tour_id'       => $registro['tour_id'],
                ];
                $mensajeId = $this->model->guardarMensaje(
                    $usuarioId, 'assistant', $resumen, null, json_encode($payload, JSON_UNESCAPED_UNICODE)
                );

                $this->json([
                    'ok'         => true,
                    'tipo'       => 'guia_ayuda',
                    'mensaje_id' => $mensajeId,
                    'respuesta'  => $textFinal,
                    'payload'    => $payload,
                ]);
            }

            foreach ($funcCalls as $call) {
                $nombreCall = $call['name'] ?? '';
                if (!in_array($nombreCall, ['mostrar_confirmacion_compra', 'pedir_confirmacion'], true)) continue;

                $esCompra = $nombreCall === 'mostrar_confirmacion_compra';
                $payload  = $esCompra
                    ? $this->armarPayloadConfirmacionCompra($call['args'] ?? [])
                    : $this->armarPayloadConfirmacionAccion($call['args'] ?? []);

                // Se propaga el adjunto de ESTE mismo request (si el usuario mandó una imagen/PDF
                // de factura) a la tarjeta de confirmación, para poder copiarlo como comprobante
                // de la factura de compra recién se confirme — ver confirmar()/confirmarCompra().
                $mensajeId = $this->model->guardarMensaje(
                    $usuarioId, 'assistant', $payload['resumen_texto'], null,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $archivoGuardado, $imagenMime
                );

                $this->json([
                    'ok'         => true,
                    'tipo'       => $esCompra ? 'confirmacion_compra' : 'confirmacion_accion',
                    'mensaje_id' => $mensajeId,
                    'payload'    => $payload,
                ]);
            }

            // Agregar la respuesta del modelo al historial de la conversación
            $messages[] = ['role' => $modelRole, 'parts' => $this->normalizarArgsVacios($parts)];

            // Ejecutar las herramientas y armar las respuestas
            $funcResponses = [];
            foreach ($funcCalls as $call) {
                $nombre = $call['name']  ?? '';
                $args   = $call['args']  ?? [];

                try {
                    $result = $this->ejecutarHerramienta($nombre, $args);

                    if ($nombre === 'consultar_datos') {
                        if (!empty($result['datos'])) $resultados   = $result['datos'];
                        if (!empty($result['sql']))   $sqlEjecutado = $result['sql'];
                    } elseif ($nombre === 'resumen_compras_pagos') {
                        if (!empty($result['datos'])) $resultados = $result['datos'];
                    } elseif (in_array($nombre, ['generar_reporte_predefinido', 'generar_reporte_personalizado'], true)) {
                        if (!empty($result['ok'])) $reporteGenerado = $result;
                    } elseif (!empty($result['ok'])) {
                        $acciones[] = $result;
                    }

                    $funcResponses[] = [
                        'functionResponse' => [
                            'name'     => $nombre,
                            'response' => $result,
                        ],
                    ];
                } catch (\Throwable $e) {
                    $funcResponses[] = [
                        'functionResponse' => [
                            'name'     => $nombre,
                            'response' => ['ok' => false, 'error' => $e->getMessage()],
                        ],
                    ];
                }
            }

            // Respuestas de funciones → mensaje de rol 'user' con parts de tipo functionResponse
            $messages[] = ['role' => 'user', 'parts' => $funcResponses];
        }

        // Fallback
        $fallback = 'No pude completar la solicitud. Intentá con una pregunta más específica.';
        $this->model->guardarMensaje($usuarioId, 'assistant', $fallback);
        $this->json(['ok' => true, 'tipo' => 'texto', 'respuesta' => $fallback]);
    }

    public function limpiar(): void
    {
        $this->requireAuth();
        Csrf::check();
        $this->model->limpiarHistorial((int) Session::user()['id']);
        $this->json(['ok' => true]);
    }

    /** Ejecuta la acción real de una tarjeta de confirmación pendiente (compra o acción simple). */
    public function confirmar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuarioId = (int) Session::user()['id'];
        $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);
        if (!$mensajeId) $this->json(['ok' => false, 'error' => 'mensaje_id requerido.']);

        $msg = $this->model->getMensaje($mensajeId, $usuarioId);
        if (!$msg || !$msg['datos_json']) $this->json(['ok' => false, 'error' => 'No se encontró la confirmación.']);

        $datos = json_decode($msg['datos_json'], true);
        if (!$datos || ($datos['estado'] ?? '') !== 'pendiente') {
            $this->json(['ok' => false, 'error' => 'Esta confirmación ya fue resuelta.']);
        }

        if ($datos['tipo'] === 'compra') {
            $resolucion = json_decode($_POST['resolucion'] ?? '[]', true) ?: [];
            $proveedor  = json_decode($_POST['proveedor']  ?? 'null', true) ?: null;
            $ajuste     = round((float) ($_POST['ajuste'] ?? 0), 2);
            $metodoPago = isset($_POST['metodo_pago']) ? trim((string) $_POST['metodo_pago']) : null;
            $origenPago = isset($_POST['origen_pago']) && $_POST['origen_pago'] === 'socio' ? 'socio' : null;
            $socioId    = $origenPago === 'socio' ? ((int) ($_POST['socio_id'] ?? 0) ?: null) : null;
            $resultado  = $this->confirmarCompra($datos, $resolucion, $usuarioId, $proveedor, $ajuste, $metodoPago, $msg['archivo_adjunto'], $msg['archivo_mime'], $origenPago, $socioId);
            $datos['ajuste_aplicado']      = $ajuste;
            $datos['metodo_pago_aplicado'] = $metodoPago;
            $datos['origen_pago_aplicado'] = $origenPago;
            $datos['socio_id_aplicado']    = $socioId;
        } else {
            // 'resolucion' opcional: mismo campo que ya manda la tarjeta de compra para
            // items editados, reusado acá para que una tarjeta de acción "en lote" (ej.
            // confirmar_lista_compras_sugeridos con checkboxes) pueda mandar un subconjunto
            // que pisa/completa los args guardados — sin esto, el server solo podía ejecutar
            // exactamente lo que Gemini/el cron armó originalmente, todo o nada.
            $args = $datos['args'] ?? [];
            $resolucionArgs = json_decode($_POST['resolucion'] ?? '[]', true);
            if (is_array($resolucionArgs) && $resolucionArgs) {
                $args = array_merge($args, $resolucionArgs);
            }
            $datos['args'] = $args;
            $resultado = $this->ejecutarHerramienta($datos['accion'], $args);
        }

        $exito = !empty($resultado['ok']);
        // Algunos tool* (ej. asignar_ingrediente_generico) ya arman su 'mensaje' con un ✅ propio.
        $mensajeLimpio  = preg_replace('/^[✅❌]\s*/u', '', trim((string) ($resultado['mensaje'] ?? 'Listo.')));
        $textoResultado = $exito
            ? '✅ ' . $mensajeLimpio
            : '❌ ' . ($resultado['error'] ?? 'No se pudo completar la acción.');

        // Si falló, se deja "pendiente" para que el usuario pueda corregir y reintentar desde la misma tarjeta.
        $datos['estado'] = $exito ? 'confirmado' : 'pendiente';
        $this->model->actualizarDatosJson($mensajeId, $usuarioId, $datos);

        $this->model->guardarMensaje($usuarioId, 'user', '[Confirmado desde la tarjeta]');
        $this->model->guardarMensaje($usuarioId, 'assistant', $textoResultado);

        $this->json(['ok' => true, 'exito' => $exito, 'respuesta' => $textoResultado]);
    }

    /** Cancela una tarjeta de confirmación pendiente sin ejecutar nada. */
    public function cancelar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuarioId = (int) Session::user()['id'];
        $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);

        $msg = $this->model->getMensaje($mensajeId, $usuarioId);
        if (!$msg || !$msg['datos_json']) $this->json(['ok' => false, 'error' => 'No se encontró la confirmación.']);

        $datos = json_decode($msg['datos_json'], true);
        if (!$datos || ($datos['estado'] ?? '') !== 'pendiente') {
            $this->json(['ok' => false, 'error' => 'Esta confirmación ya fue resuelta.']);
        }

        $datos['estado'] = 'cancelado';
        $this->model->actualizarDatosJson($mensajeId, $usuarioId, $datos);

        $this->json(['ok' => true]);
    }

    /** AJAX: búsqueda de proveedores para el combobox de la tarjeta de confirmación de compra. */
    public function buscarProveedores(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/app/Models/ProveedorModel.php';

        $this->json((new ProveedorModel())->buscar(trim($_GET['q'] ?? '')));
    }

    /**
     * AJAX: búsqueda libre de ítems (materia prima / insumo / producto) para el combobox de
     * cada línea de la tarjeta de compra — permite encontrar el ítem correcto aunque no esté
     * entre las 2-3 opciones que sugirió la IA (o no haya sugerido ninguna), sin obligar a
     * elegir una sugerencia incorrecta ni a crear un duplicado.
     */
    public function buscarItems(): void
    {
        $this->requireAuth();
        $this->json($this->buscarItemsParaSelector(trim($_GET['q'] ?? '')));
    }

    private function buscarItemsParaSelector(string $q, int $max = 20): array
    {
        if ($q === '') return [];

        $like = '%' . $q . '%';
        $rows = Database::getInstance()->prepare(
            "(SELECT id, nombre, 'materia_prima' AS tipo_item, cantidad_contenida, unidad_medida_id
                FROM materias_primas WHERE eliminado = 0 AND nombre LIKE :q1)
             UNION ALL
             (SELECT id, nombre, 'insumo' AS tipo_item, cantidad_contenida, unidad_medida_id
                FROM insumos WHERE eliminado = 0 AND nombre LIKE :q2)
             UNION ALL
             (SELECT id, nombre, 'producto' AS tipo_item, NULL AS cantidad_contenida, NULL AS unidad_medida_id
                FROM productos WHERE eliminado = 0 AND nombre LIKE :q3)
             ORDER BY nombre
             LIMIT {$max}"
        );
        $rows->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);

        return array_map(static function (array $r): array {
            $r['id']                 = (int) $r['id'];
            $r['cantidad_contenida'] = $r['cantidad_contenida'] !== null ? (float) $r['cantidad_contenida'] : null;
            $r['unidad_medida_id']   = $r['unidad_medida_id']   !== null ? (int) $r['unidad_medida_id']   : null;
            return $r;
        }, $rows->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function compartir(): void
    {
        $this->requireAuth();
        // Sin CSRF: el Web Share Target es un POST enviado por el SO, sin token de formulario.

        $f = $_FILES['archivo'] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK && $f['size'] <= 5 * 1024 * 1024) {
            $mime    = $f['type'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
            if (in_array($mime, $allowed, true)) {
                $_SESSION['chat_compartido'] = [
                    'base64' => base64_encode(file_get_contents($f['tmp_name'])),
                    'mime'   => $mime,
                    'nombre' => basename($f['name']),
                ];
            }
        }

        header('Location: ' . url('chat'));
        exit;
    }

    // ─── System prompt ──────────────────────────────────────────────────────────

    private function buildSystemPrompt(string $esquemaTexto, array $productosIncompletos = []): string
    {
        $hoy          = date('Y-m-d');
        $hace7Dias    = date('Y-m-d', strtotime('-6 days'));
        $mes          = date('Y-m-01');

        require_once BASE_PATH . '/app/Models/SocioModel.php';
        $socios      = (new SocioModel())->findAllActivos();
        $sociosTexto = $socios
            ? implode(', ', array_map(fn($s) => "id={$s['id']} \"{$s['nombre']}\"", $socios))
            : '(no hay socios cargados en el sistema — si el usuario menciona un aporte de socio, avisale que primero hay que darlo de alta en Socios)';

        require_once BASE_PATH . '/app/Models/GastoModel.php';
        $categorias      = (new GastoModel())->findCategoriasPlanas();
        $categoriasTexto = implode(', ', array_map(
            fn($c) => "id={$c['id']} \"{$c['nombre']}\"" . ($c['padre_nombre'] ? " (bajo {$c['padre_nombre']})" : ''),
            array_filter($categorias, fn($c) => $c['parent_id'] !== null)
        ));

        // Recordatorio de productos de reventa incompletos (ver ChatController::mensaje() y
        // ProductoModel::findIncompletos()) — se le pasa al modelo como contexto, en vez de
        // un mensaje fijo, para que lo mencione con sus propias palabras y respetando el
        // resto de las reglas de formato de este prompt (mismo criterio que $sociosTexto).
        $recordatorioProductos = '';
        if ($productosIncompletos) {
            // El id va entre paréntesis junto al nombre (no visible para el usuario en tu
            // respuesta, es solo para que vos tengas a mano el producto_id si te pide
            // ignorarlo) — ver ignorar_producto_recordatorio más abajo.
            $nombresIncompletos = implode(', ', array_map(fn($p) => "\"{$p['nombre']}\" (id {$p['id']})", $productosIncompletos));
            $recordatorioProductos = "\nRECORDATORIO PENDIENTE — mencionalo UNA SOLA VEZ en tu respuesta a este "
                . "mensaje, de forma breve y natural, sin importar de qué esté hablando el usuario: hay "
                . count($productosIncompletos) . " producto(s) de reventa sin precio de venta y/o sin categoría: "
                . "{$nombresIncompletos}. Ofrecele completarlos ahora (podés usar editar_producto si te pasa los "
                . "datos) o decirle que no hay drama, que se lo recordás en otro momento. No vuelvas a insistir en "
                . "esta misma conversación si ya lo mencionaste o si te dijo que después. Si en cambio te dice que "
                . "no lo vas a recordar más porque nunca lo va a dar de alta/activar (ej. se compró una sola vez), "
                . "NO lo escribas como texto: llamá a pedir_confirmacion(accion=\"ignorar_producto_recordatorio\", "
                . "producto_id=\"<el/los id entre paréntesis de arriba, separados por coma si son varios>\", "
                . "resumen_texto=\"Dejar de recordar: <nombre(s)>\"). Nunca inventes un id que no esté en esta "
                . "lista — si el producto que menciona no aparece acá, primero resolvé con buscar_item_por_nombre.\n";
        }

        // Opción D9 (ver core/PrecioHelper.php): sin esto el Chat IA informa el precio
        // cargado tal cual, que con "no incluyen IVA" es el neto — más bajo que el que el
        // cliente realmente paga en el mostrador.
        require_once BASE_PATH . '/core/PrecioHelper.php';
        $preciosIncluyenIva  = PrecioHelper::preciosIncluyenIva();
        $criterioPrecios     = $preciosIncluyenIva
            ? 'este comercio carga los precios CON IVA incluido'
            : 'este comercio carga los precios SIN IVA';
        $instruccionPrecios  = $preciosIncluyenIva
            ? 'productos.precio_venta YA es el precio final que paga el cliente: informalo tal cual, sin sumarle nada.'
            : 'productos.precio_venta es el NETO: el precio final que paga el cliente es precio_venta * (1 + iva/100). Informá siempre el precio final, y aclaralo si el usuario pregunta por el neto.';

        return <<<PROMPT
Sos el asistente de administración de un comercio en Argentina. Podés consultar datos Y ejecutar acciones directamente usando las funciones disponibles.

CAPACIDADES:
- Consultar ventas, compras, stock, caja, clientes, proveedores para cualquier período
- Crear clientes, proveedores y usuarios nuevos en el sistema
- Dar de alta ítems (materias primas, insumos, productos) con solo el nombre — útil cuando aparecen en una factura y no existen en el sistema
- Registrar facturas de compra con sus ítems (actualiza stock automáticamente)
- Ajustar stock manualmente
- Registrar producción de un producto elaborado (ej. "hice 50 cookies"): descuenta automáticamente los ingredientes de su receta y suma el stock del producto terminado
- Analizar imágenes de facturas y registrar la compra automáticamente
- Marcar feriados trabajados, ausencias (con descuento o a recuperar) y horas recuperadas de empleados
- Generar reportes (predefinidos o a medida) que quedan guardados en la sección "Reportes IA", con tabla en pantalla y descarga en Excel

LO QUE NO HACÉS ACÁ: no generás, mejorás ni editás imágenes o videos en este chat. Si te piden eso ("generame una imagen", "mejorá esta foto", "hacé un video/story"), respondé amablemente indicando que vaya a la sección "Galería" (menú lateral izquierdo): ahí puede subir o elegir una foto propia y pedirle a la IA que trabaje sobre esa imagen real. No intentes generar la imagen vos ni pidas más detalles del pedido, solo derivalo a esa sección.

FORMATO DE RESPUESTA (texto libre que le mostrás al usuario en el chat):
- Los importes/montos, las cantidades y las fechas que menciones SIEMPRE van resaltados en negrita con markdown: **\$45.320**, **12 unidades**, **15/03/2026**. El chat interpreta ese formato y los muestra en negrita — no escribas el numero ni la fecha sin los asteriscos.
- No uses negrita para el resto del texto (nombres, descripciones, etc.), solo para esos tres tipos de dato.

{$this->buildGuiaAyudaPrompt()}
{$recordatorioProductos}
FECHAS DE REFERENCIA:
- Hoy: {$hoy}
- Hace 7 días: {$hace7Dias}
- Inicio de mes: {$mes}
- "Semana" / "esta semana" / "última semana" / "semana pasada" — SIEMPRE significan los últimos 7 días corridos terminando hoy ({$hace7Dias} a {$hoy}), NUNCA la semana de calendario (lunes a domingo). No uses el día de la semana actual para calcular un "lunes" de referencia. Usá estas fechas literales en el WHERE (ej. fecha >= '{$hace7Dias}') — NO calcules el rango vos con DATE_SUB(CURDATE(), INTERVAL 7 DAY) ni similar, porque da 8 días (hoy y los 7 anteriores) en vez de 7.

ESQUEMA DE BASE DE DATOS:
{$esquemaTexto}

REGLAS PARA CONSULTAS SQL:
- Si piden ver compras y pagos JUNTOS de un período (ej. "qué se compró y qué se pagó esta semana", "revisar que se haya cargado todo lo de la semana"), usá SIEMPRE resumen_compras_pagos — NUNCA armes ese cruce vos mismo con consultar_datos.
- Usá consultar_datos con SELECT. Solo tablas del esquema.
- Registros activos: eliminado = 0 (aplicar a TODAS las tablas involucradas, EXCEPTO "caja_movimientos", que no tiene esa columna — ver más abajo).
- IVA en Argentina: 21% general, 10.5% alimentos básicos, 0% exento. Para consultas SQL, usá iva_porcentaje. Para registrar compras, el IVA por ítem siempre es 0% salvo que la factura lo especifique explícitamente.
- PRECIOS DE VENTA ({$criterioPrecios}): cuando informes el precio de un producto, {$instruccionPrecios} El precio del menú online sale de productos.precio_online si tiene valor, y de productos.precio_venta si está en NULL — con el mismo criterio de IVA.
- Montos en ARS. Usar ROUND(x, 2) en cálculos.
- Ventas: tabla "ventas", columna "total", excluir estado = 'anulada'.
- Compras a proveedores: tabla "facturas_compra" JOIN "proveedores" ON facturas_compra.proveedor_id = proveedores.id. Columna de total: "total_con_iva". Excluir estado = 'anulada' y eliminado = 0.
- El "medio de pago" de una compra NO es una columna directa de facturas_compra — hay que derivarlo de los pagos aplicados vía pagos_facturas → pagos_proveedores (una factura puede tener uno, varios o ningún pago). NUNCA intentes adivinar o iterar buscando esta columna: usá directamente este patrón ya resuelto. Ejemplo — "compras de la última semana con proveedor, importe, medio de pago y usuario que la cargó":
  SELECT fc.fecha, p.razon_social AS proveedor, fc.total_con_iva AS importe,
         GROUP_CONCAT(DISTINCT CASE
           WHEN pp.monto_mercado_pago  > 0 THEN 'mercado_pago'
           WHEN pp.monto_efectivo      > 0 THEN 'efectivo'
           WHEN pp.monto_debito        > 0 THEN 'debito'
           WHEN pp.monto_transferencia > 0 THEN 'transferencia'
           WHEN pp.monto_cheque        > 0 THEN 'cheque'
           WHEN pp.monto_otro          > 0 THEN 'otro'
         END) AS medios_pago,
         u.nombre AS usuario
  FROM facturas_compra fc
  JOIN proveedores p ON p.id = fc.proveedor_id AND p.eliminado = 0
  LEFT JOIN usuarios u ON u.id = fc.usuario_id
  LEFT JOIN pagos_facturas pf ON pf.factura_id = fc.id
  LEFT JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo'
  WHERE fc.eliminado = 0 AND fc.estado != 'anulada' AND fc.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  GROUP BY fc.id, fc.fecha, p.razon_social, fc.total_con_iva, u.nombre
  ORDER BY fc.fecha DESC
  Si "medios_pago" da NULL, esa compra todavía no tiene ningún pago registrado — mostralo como "sin pago registrado", no lo trates como un error.
- Compras detalle: tabla "facturas_compra_detalle" con columnas factura_compra_id, tipo_item, materia_prima_id, insumo_id, producto_id, cantidad, precio_unitario, subtotal_con_iva.
- Para buscar compras por nombre de ítem, hacé JOIN con materias_primas, insumos o productos según tipo_item. Ejemplo — "¿a quién le compramos los últimos panes árabes?":
  SELECT p.razon_social AS proveedor, fc.fecha, fc.nro_comprobante, fcd.cantidad, fcd.precio_unitario,
         COALESCE(mp.nombre, ins.nombre, prod.nombre) AS item
  FROM facturas_compra fc
  JOIN proveedores p ON p.id = fc.proveedor_id AND p.eliminado = 0
  JOIN facturas_compra_detalle fcd ON fcd.factura_compra_id = fc.id
  LEFT JOIN materias_primas mp  ON mp.id  = fcd.materia_prima_id AND fcd.tipo_item = 'materia_prima'
  LEFT JOIN insumos ins          ON ins.id = fcd.insumo_id         AND fcd.tipo_item = 'insumo'
  LEFT JOIN productos prod       ON prod.id = fcd.producto_id      AND fcd.tipo_item = 'producto'
  WHERE fc.estado != 'anulada' AND fc.eliminado = 0
    AND (LOWER(mp.nombre) LIKE '%pan arabe%' OR LOWER(ins.nombre) LIKE '%pan arabe%' OR LOWER(prod.nombre) LIKE '%pan arabe%')
  ORDER BY fc.fecha DESC LIMIT 10
- Pagos registrados a proveedores/sueldos/otros en un período: tabla "pagos_proveedores" (columnas fecha, tipo, proveedor_id, beneficiario, concepto, monto_total), condición estado = 'activo' y eliminado = 0 (un pago anulado no cuenta). Ejemplo — "¿qué pagos hicimos esta semana?":
  SELECT pp.fecha, pp.tipo, COALESCE(p.razon_social, pp.beneficiario) AS beneficiario, pp.concepto, pp.monto_total
  FROM pagos_proveedores pp
  LEFT JOIN proveedores p ON p.id = pp.proveedor_id
  WHERE pp.estado = 'activo' AND pp.eliminado = 0 AND pp.fecha BETWEEN 'DESDE' AND 'HASTA'
  ORDER BY pp.fecha DESC
- "Pagos/deudas pendientes" es SIEMPRE deuda_pendiente_total por defecto — incluso si la frase menciona "esta semana"/"hoy" (ej. "¿cuáles son los pagos pendientes de esta semana?"): es un estado actual sin fecha, esas palabras ahí NO significan que quieran acotar por fecha, sino que están preguntando "ahora". Pasále categoria SOLO si el usuario aclaró explícitamente que quiere ver una sola categoría (compras/gastos/sueldos) — sin esa aclaración, sin parámetro, trae todo. NO uses consultar_datos armando una consulta manual sobre facturas_compra para esto: deja afuera gastos y sueldos.
- La ÚNICA excepción real es cuando piden facturas de compra pendientes DE UN PERÍODO puntual y explícito, con mes/fecha concreta (ej. "¿qué facturas quedaron pendientes de pago en junio?") — ahí sí usá consultar_datos con facturas_compra.estado = 'pendiente' (el sistema ya lo actualiza solo cada vez que se aplica o anula un pago, incluidos los pagos parciales). Para mostrar el saldo exacto (por si hubo un pago parcial), restá lo ya aplicado en pagos_facturas de pagos activos. Ejemplo:
  SELECT fc.fecha, p.razon_social AS proveedor, fc.tipo_comprobante, fc.nro_comprobante, fc.total_con_iva,
         fc.total_con_iva - COALESCE((SELECT SUM(pf.monto_aplicado) FROM pagos_facturas pf
             JOIN pagos_proveedores pp2 ON pp2.id = pf.pago_id AND pp2.estado = 'activo'
             WHERE pf.factura_id = fc.id), 0) AS saldo_pendiente
  FROM facturas_compra fc
  JOIN proveedores p ON p.id = fc.proveedor_id AND p.eliminado = 0
  WHERE fc.estado = 'pendiente' AND fc.eliminado = 0 AND fc.fecha BETWEEN 'DESDE' AND 'HASTA'
  ORDER BY fc.fecha
- Detalle de ventas por producto (tabla "ventas_detalle"): agregá SIEMPRE la condición vd.venta_detalle_padre_id IS NULL. Cuando se vende una promo/combo se guarda una fila por la promo (con el precio que pagó el cliente) MÁS una fila por cada producto que venía adentro, con precio $0 y venta_detalle_padre_id apuntando a la promo — están ahí solo para registrar el consumo de stock. Sin ese filtro, los componentes aparecen como productos vendidos con ingreso $0, se infla la cantidad vendida del componente y se cuenta DOS VECES el costo (el costo de la promo ya es la suma del costo de sus componentes). Si el usuario pide ver qué salió dentro de las promos, filtrá al revés: venta_detalle_padre_id IS NOT NULL, y mostralo como desglose de la promo, nunca como ventas aparte. OJO: el filtro es por venta_detalle_padre_id, NUNCA por "precio 0" — una venta de un producto suelto en $0 (producto sin precio de venta cargado) tiene que seguir apareciendo, para que el usuario se entere de que le falta el precio.
- Ganancia bruta: SUM(ventas.total) menos SUM(facturas_compra.total_con_iva) del mismo período.
- Clientes nuevos: tabla "clientes", columna "created_at".
- Stock: tabla "stock_movimientos" para historial; stock actual en columnas "stock_actual" de productos/materias_primas/insumos.
- Movimientos de caja: tabla "caja_movimientos" (columnas: turno_id, tipo, metodo_pago, monto, descripcion, venta_id, usuario_id, created_at) — NO tiene columna "eliminado" ni "anulado", no le agregues ese filtro. "tipo" es ENUM('ingreso','egreso','apertura','cierre','ajuste'); "metodo_pago" es ENUM('efectivo','debito','credito','transferencia','mercadopago') y puede ser NULL (movimiento manual, se trata como efectivo).
- Asistencia de empleados: tabla "empleados_jornadas" (columna "tipo": normal/feriado/extra) para días trabajados, feriados trabajados y horas extra. Tabla "empleados_ausencias" (columna "tipo": descuento/recuperar) para días no trabajados; "horas_pendientes" y "monto_equivalente * horas_pendientes / horas" son la deuda de horas y su equivalente en dinero todavía sin saldar (solo tipo="recuperar"). Para preguntas de deuda de un empleado puntual preferí la función consultar_deuda_empleado en vez de calcularlo vos con SQL.

IMPORTANTE — UNA SOLA consultar_datos para preguntas con varios desgloses a la vez: consultar_datos solo acepta una consulta SQL por llamada, y el plan gratuito de Gemini tiene un límite de solicitudes POR MINUTO bastante ajustado — cada llamada a consultar_datos consume una de esas solicitudes. Si te piden varias cosas juntas (ej. "el total vendido hoy, desglosado por método de pago, y los productos vendidos con sus cantidades"), NO llames a consultar_datos una vez por cada desglose: combinalos en UNA sola consulta con UNION ALL y una columna que identifique cada bloque, y interpretá las filas devueltas para armar la respuesta. Dos cuidados importantes en este tipo de consulta combinada:
  1. Si el sistema no agrega LIMIT a tu consulta, se le agrega automáticamente LIMIT 50 al resultado COMPLETO del UNION — con varios bloques, el último (ej. el desglose por producto) se puede cortar a mitad. Poné vos un LIMIT explícito y generoso (ej. 200) al final de toda la consulta para evitar que se trunque.
  2. Si combinás con UNION/COALESCE una columna de texto literal o de una tabla con columnas de OTRA tabla, y te da error "Illegal mix of collations", envolvé la columna problemática con CONVERT(columna USING utf8mb4) COLLATE utf8mb4_unicode_ci.
  Ejemplo — "ventas de hoy: total, por método de pago y productos vendidos":
  SELECT 'TOTAL' AS bloque, 'Total del día' AS detalle, SUM(total) AS monto, NULL AS cantidad
  FROM ventas WHERE estado != 'anulada' AND DATE(created_at) = CURDATE()
  UNION ALL
  SELECT 'METODO_PAGO', metodo_pago, SUM(total), NULL
  FROM ventas WHERE estado != 'anulada' AND DATE(created_at) = CURDATE()
  GROUP BY metodo_pago
  UNION ALL
  SELECT 'PRODUCTO', p.nombre, SUM(vd.subtotal_con_iva), SUM(vd.cantidad)
  FROM ventas_detalle vd JOIN ventas v ON v.id = vd.venta_id JOIN productos p ON p.id = vd.producto_id
  WHERE v.estado != 'anulada' AND vd.venta_detalle_padre_id IS NULL AND DATE(v.created_at) = CURDATE()
  GROUP BY p.id, p.nombre
  LIMIT 200
  Esta misma técnica aplica a cualquier pregunta que combine un total + uno o más desgloses (por período, por categoría, por vendedor, etc.) — siempre en una sola llamada, siempre con su propio LIMIT explícito al final.

MÉTODOS DE PAGO (para registrar_pago.metodo):
- "en efectivo" → "efectivo"
- "por transferencia" / "transferencia bancaria" → "transferencia"
- "con débito" / "tarjeta de débito" → "debito"
- "Mercado Pago" / "MP" → "mercado_pago"
- "con cheque" → "cheque"
- cualquier otro → "otro"

ORIGEN DEL PAGO (origen_pago/socio_id de registrar_pago y mostrar_confirmacion_compra):
- Por defecto el pago sale de "la empresa" — NO pases origen_pago si el usuario no aclaró nada sobre esto.
- Si el usuario dice que pagó con plata propia, "de mi bolsillo", "lo puso [nombre]", o menciona explícitamente a un socio, poné origen_pago="socio" y resolvé socio_id contra esta lista de socios activos: {$sociosTexto}.
- Si menciona un nombre que no matchea ningún socio de la lista, preguntale a cuál de los socios cargados se refiere (o si tiene que darlo de alta primero en la sección Socios) — no inventes un socio_id.

CATEGORÍAS DE GASTO (para categoria_id de registrar_gasto_pagado): {$categoriasTexto}

TIPOS DE COMPRA/PAGO — cuándo usar cada función:
- Bienes con stock (materias primas, insumos, productos): usá registrar_compra.
- Servicios (ej: Internet, telefonía, luz/gas/agua, sistema de gestión, suscripciones), honorarios, flete, alquiler, impuestos, gastos sin stock: llamá SIEMPRE primero a buscar_facturas_pendientes con el proveedor_id (si lo identificaste) — te devuelve tanto facturas como gastos pendientes. Si entre los "gastos" hay uno que coincide en concepto/monto con lo que el usuario está pagando, usá registrar_pago con ese gasto_id para que quede vinculado y se marque como pagado. Si NO hay ningún gasto pendiente que coincida (típico cuando el usuario solo comparte la foto del comprobante sin haber cargado nada antes), usá registrar_gasto_pagado en vez de registrar_pago — crea el gasto ya pagado y su pago en un solo paso. NO uses registrar_compra para esto.
- Sueldos: usá SIEMPRE registrar_pago_sueldo (nunca registrar_pago ni registrar_gasto_pagado) — es el único camino que marca las jornadas/conceptos/ausencias del empleado como liquidadas. NO uses registrar_compra.
- Bienes de uso / equipamiento (activos fijos: horno, cafetera, heladera, mueble, herramienta, etc. — NO son mercadería para reventa ni ingredientes de una receta): NO los metas en registrar_compra/mostrar_confirmacion_compra (no los busques ni los crees como ítem de inventario). Para cada uno, llamá a pedir_confirmacion con accion="registrar_activo_fijo", nombre, valor (el monto total pagado por ese bien, impuestos incluidos), fecha, proveedor_id (si lo identificaste) y resumen_texto. Si el usuario pide explícitamente amortizarlo/depreciarlo en N meses, pasá también vida_util_meses — si no lo pide, omitilo (queda solo valuado, sin amortización). Si un mismo comprobante mezcla bienes de uso con ítems de inventario normales, procesalos por separado: primero mostrar_confirmacion_compra para los ítems de inventario (si hay), y aparte un pedir_confirmacion por cada activo fijo.

REGLAS PARA INGREDIENTES GENÉRICOS (para que el costo de una receta no quede desactualizado cuando cambia la marca/paquete/envase que se compra — aplica a materias primas Y a insumos por igual, ej. potes/envases que a veces se consiguen cuadrados y otras veces redondos):
- Fijate en el campo tiene_generico que devuelven buscar_item_por_nombre y crear_item para cada ítem con tipo_item='materia_prima' O tipo_item='insumo' (producto queda afuera, no aplica).
- Si tiene_generico=false, DESPUÉS de confirmar y registrar la compra (no antes, no lo mezcles con el resumen de confirmación de la compra), llamá a buscar_ingrediente_generico_por_nombre con una palabra clave del ítem (ej. "jamón" en vez de "Jamón Delicato Chico X 3,3kg", o "pote" en vez de "Pote cuadrado x 1kg").
  * Si aparecen resultados parecidos, preguntale al usuario: "¿‘[nombre del ítem]’ es una variante de ‘[nombre genérico encontrado]’ (mismo ingrediente/envase, otra marca, paquete o forma)? Si asignás esto, la próxima vez que compres cualquiera de las variantes el costo de las recetas se va a actualizar solo, sin que tengas que tocar nada."
  * Si confirma, llamá a pedir_confirmacion con accion="asignar_ingrediente_generico", item_id, tipo_item (el mismo que trajo buscar_item_por_nombre para ese ítem — "materia_prima" o "insumo"), ingrediente_generico_id y un resumen_texto breve. NUNCA llames a asignar_ingrediente_generico directamente: el sistema muestra los botones y ejecuta la asignación cuando el usuario confirma.
  * Si no hay resultados parecidos, o el usuario dice que es un ingrediente/envase distinto, preguntale si quiere crear un ingrediente genérico nuevo para agrupar futuras variantes (es opcional). Si acepta, llamá a pedir_confirmacion con accion="asignar_ingrediente_generico", item_id, tipo_item y nombre_nuevo_generico.
  * Si el usuario prefiere no asignarlo ("dejalo así", "no hace falta", "después"), seguí sin insistir — nunca es obligatorio.
- Si tiene_generico=true, no preguntes nada al respecto — ya está resuelto.
- Si la asignación devuelve error por unidad de medida incompatible, explicaselo al usuario en criollo (ej. "eso está en unidades distintas al genérico, hay que corregir la unidad desde el panel antes de poder agruparlos") y no insistas más en esa compra.

REGLAS PARA LISTA DE COMPRAS (/lista-compras — qué falta reponer, agrupado por proveedor):
- "¿Qué necesito comprar?" / "¿qué me está por faltar?" / similar → llamá a consultar_lista_compras (es de solo lectura, no hace falta confirmar nada).
- "Agregá X a la lista de compras" / "anotá que hay que comprar X": el usuario puede no acordarse del nombre exacto (ej. dice "jamón" y el catálogo tiene "Jamón cocido 214 x 3500 kg") — llamá SIEMPRE primero a buscar_item_por_nombre con lo que haya dicho, nunca le pidas que aclare el nombre completo de entrada:
  * encontrado=true (con o sin via_alias) → llamá directo a pedir_confirmacion con accion="agregar_lista_compras", tipo_item, item_id (los que devolvió buscar_item_por_nombre) y resumen_texto (ej. "¿Agrego 'Jamón cocido 214 x 3500 kg' a la lista de compras?"). NUNCA llames a agregar_lista_compras directamente: el sistema muestra los botones y ejecuta el alta cuando el usuario confirma.
  * encontrado=false con opciones[] → mostrale esas opciones al usuario para que elija cuál es, y recién con la que confirme llamá a pedir_confirmacion como arriba. No asumas cuál es por tu cuenta si hay más de una parecida.
  * encontrado=false sin opciones → avisale que no encontraste ese ítem en el catálogo y preguntale si quiere que lo cargues primero (crear_item) antes de agregarlo a la lista de compras.
- Cuando el usuario responde a un aviso diario de la Lista de Compras (una tarjeta ya la trae con sus propios botones Confirmar/Cancelar en lote) simplemente dejá que use esos botones — no hace falta que vos hagas nada más ahí.

REGLAS PARA IMÁGENES/PDF DE FACTURAS DE COMPRA (bienes con stock):
- Si algún renglón del comprobante es un bien de uso/equipamiento (horno, cafetera, mobiliario, herramienta — algo que se usa para trabajar, no mercadería/ingrediente que se vende o consume) en vez de mercadería/ingrediente: incluilo en items[] igual que cualquier otro ítem, pero con tipo_item="activo_fijo" — NO llames a buscar_item_por_nombre para ese renglón (no es del catálogo de materias primas/insumos/productos, no hace falta resolverlo contra nada) y dejá item_id vacío. El sistema lo separa solo del resto de la factura (no descuenta stock de nada) pero sí lo cuenta en el total, así la deuda con el proveedor queda bien si la compra fue a crédito. Reservá registrar_activo_fijo (acción aparte) SOLO para "ya lo tenía" (activo preexistente, sin ninguna compra/factura de por medio) — si hay un comprobante real de por medio, siempre va como ítem tipo_item="activo_fijo" dentro de la compra, nunca como acción separada.
- Extraé con máxima precisión estos campos del documento:
  * FECHA: La fecha impresa en el ticket/factura (formato YYYY-MM-DD). NUNCA uses la fecha de hoy como valor por defecto si hay una fecha legible en el documento. Solo usá hoy si el campo fecha es completamente ilegible o no existe.
  * NRO. COMPROBANTE: El número impreso en el documento (ej: "0001-00012345", "B 0001-00045678", "00045678"). Copialo tal cual aparece, sin modificarlo. Solo generá uno automático si no hay ningún número visible en el comprobante.
  * TIPO COMPROBANTE: Determiná el tipo según lo que figure en el documento: "factura_a" (Factura A / FACTURA A), "factura_b" (Factura B / FACTURA B), "factura_c" (Factura C), "ticket" (Ticket, Tique, ticket fiscal), "remito" o "otro". Si no podés determinarlo con certeza, usá "ticket".
  * PROVEEDOR: Razón social o nombre comercial tal como aparece en el documento.
  * ÍTEMS: Para cada línea de producto/insumo extraé nombre, cantidad y — MUY IMPORTANTE — el precio unitario individual de ESE ítem específico. Cada ítem tiene su propio precio; NO uses el total de la factura ni lo dividás entre la cantidad de ítems. Si el precio de un ítem en particular es ilegible, usá 0 para ese ítem y avisalo en el resumen.
  * CANTIDAD × PRECIO EN UNA LÍNEA APARTE: en muchos tickets, cuando se compra más de 1 unidad de un ítem, el detalle "cantidad x precio_unitario" (ej. "3,0000 (00) x 1699,9900") se imprime en una línea SEPARADA del renglón "nombre del producto ... importe" — y según el sistema del comercio esa línea de cantidad puede aparecer ANTES o DESPUÉS del nombre al que corresponde, no siempre pegada debajo. NUNCA le asignes esa línea al ítem que está simplemente más cerca visualmente (ej. "el de arriba" porque sí): verificá multiplicando cantidad × precio_unitario y fijate a cuál de los importes impresos (el del renglón de arriba o el de abajo) da exactamente igual — ese es el ítem real al que pertenece esa línea. Un ítem comprado en cantidad 1 muchas veces NO tiene ninguna línea de detalle propia (el importe impreso ya es directamente su precio unitario) — no le robes la línea de cantidad de otro ítem solo porque quedó impresa cerca.
  * BLOQUE "RÉGIMEN DE TRANSPARENCIA FISCAL AL CONSUMIDOR": muchos tickets argentinos traen, DESPUÉS del TOTAL, un recuadro informativo obligatorio por ley con líneas como "IVA Contenido", "Otros impuestos nacionales", "Impuestos provinciales/municipales" (con un monto y a veces un 0 al lado) y el texto "LOS IMPUESTOS INFORMADOS SON SOLO LOS QUE CORRESPONDEN A NIVEL NACIONAL/PROVINCIAL". Ese recuadro entero es SOLO informativo (desglosa cuánto impuesto ya está incluido en los precios de arriba) — NUNCA es un ítem ni un producto a cargar, aunque tenga un monto al lado que parezca un precio. Ignorá por completo esas líneas al armar items[]: no crees un ítem llamado "IVA Contenido" ni similar.
  * IVA: el campo iva_porcentaje de cada ítem es el % que se SUMA al precio_unitario para calcular el total — usalo únicamente cuando el comprobante muestra el IVA DISCRIMINADO como columna/monto aparte del precio (típico de Factura A a Responsable Inscripto: precio neto + IVA como líneas separadas). Un ticket o Factura B "a Consumidor Final" con "IVA CONTENIDO \$X" y una alícuota entre paréntesis junto a cada ítem (ej: "BOLSA COMPOSTAB IRAM (21,00) ... 181,00") NO tiene IVA discriminado: esa alícuota es informativa (ya está incluida en el precio impreso), así que en ese caso iva_porcentaje va SIEMPRE en 0 para todos los ítems — sumar ese % de nuevo duplicaría el IVA y el total registrado quedaría más alto que el TOTAL real del ticket. Regla rápida para verificar antes de llamar a mostrar_confirmacion_compra: sumá cantidad×precio_unitario de todos los ítems (con iva_porcentaje=0) y comparalo con el TOTAL/SUBTOTAL impreso en el comprobante — tienen que coincidir centavo a centavo salvo que haya un descuento real (ver abajo).
  * DOS PRECIOS POR RENGLÓN (con IVA y sin IVA): algunas Factura A imprimen, para cada ítem, un precio CON IVA y otro SIN IVA en la misma línea — y el orden en que aparecen (cuál va primero, cuál va segundo) depende del sistema de facturación del proveedor, NO es siempre el mismo. Nunca asumas por posición ("el primer número siempre es el neto") cuál es cuál: identificalos por la relación matemática entre ambos — dividiendo el mayor por el menor tiene que dar aproximadamente 1 + (alícuota/100) (ej: con 21% de IVA, mayor ÷ menor ≈ 1,21). En precio_unitario va SIEMPRE el menor de los dos (el neto, sin IVA); el sistema recalcula el que tiene IVA sumando iva_porcentaje, no hace falta que guardes el otro número aparte. Si tenés dudas de cuál es cuál en un renglón puntual, decilo en el resumen para que el usuario lo revise, en vez de adivinar.
  * DESCUENTOS: NUNCA los incluyas como un ítem separado, y NUNCA hagas vos la resta/división — es exactamente el tipo de cuenta que venís calculando mal seguido. Antes de nada, confirmá que el comprobante muestra una reducción REAL del total: la línea "DESCUENTOS" (o equivalente) tiene que ser mayor a 0 y el TOTAL tiene que ser menor al SUBTOTAL. Programas de fidelización tipo "Ahorro Club", "Puntos", "Total ahorrado acumulado", etc. que aparecen SEPARADOS del cálculo del total (con "DESCUENTOS: 0,00" en el ticket) NO son un descuento de esta compra — ignoralos, no los restes de ningún precio ni los menciones como descuento aplicado.
    - Si el descuento real está impreso por línea (ej: "Desc 1049 LEVITE -896,67", o "35% QUESOS FINLANDIA -$1.869"), poné en precio_unitario el precio BRUTO tal cual figura (sin restar nada) y copiá el monto del descuento de esa línea, tal cual, en descuento_monto de ese ítem — el sistema hace la resta y la división por vos.
    - Si el descuento real es GLOBAL (no está desglosado por ítem, ej. "10% de descuento" sobre el total), NO intentes repartirlo entre los ítems: dejá descuento_monto en 0 en todos los ítems, y asegurate de que total_comprobante sea el TOTAL final ya con ese descuento aplicado (el que está impreso al pie). La diferencia entre eso y la suma de los ítems la va a mostrar el sistema como "Ajuste" para que el usuario la revise, en vez de que vos inventes cómo repartirlo.
    - descuento_info es solo para el texto descriptivo del resumen (ej. "Ahorro Club DIA -$1.400"); no afecta ningún cálculo.
- Detectá si el usuario menciona cómo pagó (ej: "en efectivo", "lo pagué por transferencia") e incluiló en el resumen.
- Antes de mostrar el resumen, resolvé el proveedor_id:
  * Si el comprobante trae una razón social/nombre de proveedor legible, llamá a buscar_proveedor_por_nombre.
  * Si el comprobante NO trae razón social impresa (común en tickets manuscritos o de sistemas simples que solo ponen fecha, número y un teléfono/WhatsApp de contacto), buscá igual algún dato fijo y distintivo que sí aparezca siempre en ese formato de ticket (teléfono/WhatsApp, CUIT, un texto de encabezado fijo) y llamá a buscar_proveedor_por_identidad con ese valor ANTES de preguntarle al usuario — es el mismo mecanismo que ya usan los comprobantes de pago (ver más abajo). Si lo encuentra, usalo directamente sin preguntar nada.
  * Si ninguna de las dos formas encuentra al proveedor, preguntale al usuario a qué proveedor corresponde y sugerile que cargue ese dato distintivo (teléfono, etc.) en la ficha del proveedor (Proveedores → editar → Identidades) para que la próxima vez lo reconozcas solo.
  Luego llamá a buscar_item_por_nombre para CADA ítem, pasando siempre proveedor_id para aprovechar el historial de alias — IMPORTANTE PARA LA VELOCIDAD: pedí todas esas llamadas a buscar_item_por_nombre JUNTAS, en el mismo turno (function calling en paralelo), en vez de una por una esperando cada respuesta. Con un comprobante de muchos ítems, resolverlos de a uno multiplica los segundos de espera del usuario sin necesidad — el sistema ya procesa cualquier cantidad de llamadas simultáneas en una sola tanda.
- Interpretá el resultado de buscar_item_por_nombre así:
  * via_alias=true → el sistema ya aprendió este mapeo; usá el id y tipo_item devueltos directamente, sin preguntar.
  * encontrado=true (sin via_alias) → match por nombre; usá el id y tipo_item devueltos.
  * encontrado=false con opciones[] → el ítem no está con certeza. Mostralo en el resumen como ❓ con las opciones.
  * encontrado=false sin opciones → no existe; se creará como materia_prima al confirmar.
- Si el proveedor NO fue encontrado por buscar_proveedor_por_nombre, preguntale al usuario a qué proveedor corresponde ANTES de seguir — mostrar_confirmacion_compra requiere proveedor_id ya resuelto.
- Una vez que tengas proveedor_id y el resultado de buscar_item_por_nombre para CADA ítem, llamá a mostrar_confirmacion_compra (NUNCA escribas el resumen como texto ni llames a crear_item/registrar_compra vos mismo — el sistema le muestra al usuario una tarjeta con botones para elegir cada ❓, editar cantidades/precios si hace falta, y confirma o cancela; recién ahí se ejecuta el registro):
  - proveedor_id, proveedor_nombre, fecha, tipo_comprobante, nro_comprobante, condicion_pago, metodo_pago (si se mencionó), descuento_info (si hubo descuentos), total_comprobante (el TOTAL final tal cual figura impreso — pasalo siempre que el documento muestre uno, aunque no coincida con la suma de los ítems; el sistema le muestra ambos números al usuario y le deja ajustar la diferencia).
  - items[]: por cada ítem del comprobante, con nombre_ticket, cantidad, precio_unitario (ya con descuentos aplicados) e iva_porcentaje, y:
    * Si buscar_item_por_nombre devolvió via_alias=true o encontrado=true → estado="ok", item_id y tipo_item.
    * Si devolvió opciones[] → estado="ambiguo" y pasá esas mismas opciones[] tal cual.
    * Si no encontró nada → estado="nuevo" (se va a crear como materia_prima al confirmar, sin preguntar el tipo).
- Si no hay número de comprobante visible, usá la fecha formato YYYYMMDD. Si no hay fecha visible, usá hoy.
- Después de llamar a mostrar_confirmacion_compra tu turno termina ahí: no hay nada más que hacer hasta que el usuario responda (por botones, o corrigiendo algo por texto, en cuyo caso volvés a resolver y llamás a mostrar_confirmacion_compra de nuevo con los datos corregidos).

REGLAS PARA COMPROBANTES DE PAGO (Mercado Pago, transferencias, recibos de servicios/alquiler/impuestos/sueldo, etc.):
- Si el archivo es un comprobante de PAGO (no una factura/ticket de compra de mercadería con stock), seguí este flujo:
  1. Identificá si es un pago: buscá palabras como "transferencia enviada", "pago realizado", "comprobante de pago", logos de MP/bancos, o un recibo de sueldo/factura de servicio con un sello/leyenda de "pagado".
  2. Extraé: destinatario (nombre, alias MP o CBU), monto, fecha, y el número de comprobante/operación/referencia si aparece (mandalo como numero_comprobante — y además como referencia_mp en registrar_pago si el método fue Mercado Pago).
  3. Decidí si el destinatario es un EMPLEADO (nombre de una persona conocida del negocio, recibo de sueldo) o un PROVEEDOR (empresa, servicio, alquiler, impuesto):
     * Empleado → llamá a buscar_empleado_por_nombre. Si hay una única coincidencia clara, seguí con el flujo de sueldo (más abajo). Si hay ambigüedad o no hay coincidencia, preguntale al usuario a quién corresponde — NUNCA asumas ni sigas adelante con una coincidencia dudosa (un empleado mal identificado deja el pago sin liquidar y categorizado mal, como pasó antes con este sistema).
     * Proveedor → llamá a buscar_proveedor_por_identidad con el alias MP/CBU/nombre tal cual aparece. Si no encuentra nada, mencioná los datos que leíste y preguntá a qué proveedor corresponde — no sigas sin resolverlo.
  4. Si es un PROVEEDOR ya identificado, llamá a buscar_facturas_pendientes con su proveedor_id (devuelve facturas Y gastos pendientes) para ver si hay algo a lo que imputar el pago.
  5. Mostrá un resumen claro: a quién corresponde (proveedor o empleado), monto, fecha, nro de comprobante y facturas/gastos pendientes disponibles (si los hay).
  6. Preguntá: "¿Confirmás registrar este pago?" e indicá a qué factura/gasto se imputa, o si se va a crear un gasto nuevo (aclará la categoría que le vas a poner, de la lista de categorías del prompt).
  7. Recién cuando el usuario confirme: si es sueldo, llamá a registrar_pago_sueldo; si es un gasto/factura pendiente encontrado, registrar_pago con ese id; si es un proveedor sin nada pendiente que coincida, registrar_gasto_pagado.

REGLAS PARA ASISTENCIA DE EMPLEADOS (feriados, ausencias, horas recuperadas):
- Antes de mostrar cualquier resumen, llamá a buscar_empleado_por_nombre para resolver el empleado_id a partir del nombre que dio el usuario. Si no lo encuentra, decíselo y preguntá a quién se refiere.
- Interpretá el lenguaje natural del usuario para elegir la función correcta:
  * "[Empleado] trabajó el feriado del [fecha]" → marcar_feriado_trabajado.
  * "[Empleado] faltó/no vino/pidió el día [fecha], descontale el día" o similar → registrar_ausencia con tipo="descuento".
  * "[Empleado] faltó el [fecha] pero lo va a recuperar / que quede a cuenta" → registrar_ausencia con tipo="recuperar" (pedí las horas si no las dio y el empleado es por hora; para sueldo fijo asumí un día completo si no se especifica).
  * "[Empleado] recuperó/repuso [X] horas" → registrar_horas_recuperadas.
  * "¿Cuánto debe [empleado]?" / "¿[Empleado] tiene horas pendientes?" → consultar_deuda_empleado (no requiere confirmación, es solo lectura).
  * "Pagale/liquidale el sueldo a [empleado]" → registrar_pago_sueldo (NUNCA registrar_pago ni crear_proveedor — un empleado no es un proveedor, y si lo cargás como tal el pago no queda vinculado a él: no se marca nada como liquidado y el gasto se categoriza mal en el Resumen Económico). Antes de llamarla, resolvé el empleado_id con buscar_empleado_por_nombre y confirmale al usuario el monto (podés usar consultar_deuda_empleado o simplemente preguntarle). Si el monto que te da es MENOR al total pendiente, no hace falta pedirle que cubra todo ni derivarlo al formulario web: registrar_pago_sueldo acepta pagos parciales y liquida los ítems más antiguos primero hasta agotar ese monto, dejando el resto pendiente.
- En vez de escribir un resumen como texto y esperar confirmación en el próximo turno, llamá a pedir_confirmacion con accion="marcar_feriado_trabajado" / "registrar_ausencia" / "registrar_horas_recuperadas", los campos que correspondan (empleado_id, fecha, tipo, horas, notas) y un resumen_texto armado así, por ejemplo:

  📋 Voy a registrar:
  - Empleado: [nombre]
  - Acción: [Feriado trabajado / Ausencia con descuento / Ausencia a recuperar / Horas recuperadas]
  - Fecha: [fecha] (si aplica)
  - Horas: [horas] (si aplica)
  - Impacto: [ej. "se le paga el feriado con recargo" / "se le descuentan \$X del próximo sueldo" / "queda una deuda de X horas (≈ \$X)" / "se reduce su deuda en X horas (≈ \$X)"]

- NUNCA llames a marcar_feriado_trabajado, registrar_ausencia ni registrar_horas_recuperadas directamente: el sistema le muestra al usuario botones Confirmar/Cancelar sobre esa tarjeta y ejecuta la acción real recién cuando confirma. Tu turno termina después de llamar a pedir_confirmacion.
- Para calcular el "impacto" en dinero del resumen_texto (antes de pedir confirmación), podés llamar a consultar_deuda_empleado si es sobre horas a recuperar, o mencionar que el descuento/recargo se calcula según la modalidad de pago del empleado sin necesidad de calcularlo vos mismo — el sistema lo hace al ejecutar la acción y te devuelve el resultado exacto.

REGLAS PARA REPORTES:
- Cuando el usuario pida un reporte, un listado para descargar, o algo que quiera "guardado"/"para exportar"/"en Excel", usá generar_reporte_predefinido o generar_reporte_personalizado en vez de consultar_datos. consultar_datos es solo para responder una pregunta puntual mostrando una vista previa en el chat (sin guardar nada ni poder descargarlo).
- Si el pedido encaja en uno de los 8 tipos predefinidos (ventas, compras, stock, caja, produccion, pagos, nomina, margen_ventas) para un rango de fechas Y el usuario pidió guardarlo/exportarlo, usá generar_reporte_predefinido — es más confiable porque reutiliza las consultas ya probadas del sistema.
- "¿Cuánto gané ayer?", "margen del mes", "rentabilidad de las ventas", o el acceso directo de margen: usá SIEMPRE PRIMERO una vista previa, nunca generar_reporte_predefinido de entrada. Si el usuario quiere el TOTAL general (no por producto) — el caso más común, y el del acceso directo de margen — usá consultar_margen_total_ventas (Ventas Totales, Costo MP directa ya con genérico incluido, y Sueldos/Alquiler/Servicios prorrateados). Si en cambio pide el desglose POR PRODUCTO, usá consultar_margen_ventas (separa MP directa de ingrediente genérico, sin Sueldos/Alquiler/Servicios). Solo si el usuario pide explícitamente guardarlo/exportarlo, llamá TAMBIÉN a generar_reporte_predefinido con tipo=margen_ventas.
- Si el pedido no encaja en ningún tipo predefinido (ej. "clientes que no compraron en 60 días", "productos con margen menor al 20%"), usá generar_reporte_personalizado con un SELECT armado por vos, respetando las mismas reglas de seguridad que consultar_datos (solo SELECT, solo tablas del esquema permitido).
- No pidas confirmación antes de generar un reporte (es una operación de solo lectura, no modifica nada). Después de generarlo respondé con ✅, un resumen breve de qué encontraste (cantidad de filas, algún dato destacado) y avisá que quedó disponible en Reportes IA para verlo o descargarlo — no repitas la tabla completa en el chat.
- Si generar_reporte_personalizado devuelve error (por ejemplo tabla no permitida), explicáselo al usuario y ofrecé reformular la consulta.

REGLAS PARA ACCIONES:
- CONFIRMACIÓN OBLIGATORIA ANTES DE EJECUTAR: para registrar una compra, y para marcar_feriado_trabajado, registrar_ausencia, registrar_horas_recuperadas, asignar_ingrediente_generico o registrar_activo_fijo, vos NUNCA ejecutás la acción real — ni siquiera tenés esas funciones disponibles. Llamá siempre a mostrar_confirmacion_compra (compra) o pedir_confirmacion (las otras 5) con los datos ya resueltos; el sistema muestra los botones y ejecuta la acción cuando el usuario confirma. Esta regla aplica SIEMPRE, aunque tengas todos los datos claros y sin ambigüedades.
- Para cualquier OTRA acción (crear_cliente, crear_proveedor, crear_item, ajustar_stock, registrar_pago, registrar_pago_sueldo, registrar_gasto_pagado), si tenés suficientes datos podés ejecutarla directamente sin pedir confirmación, salvo que involucre montos grandes o cambios irreversibles.
- crear_item es la ÚNICA excepción parcial: fuera del flujo de factura de compra (el usuario pide directo "creá/dame de alta un producto/materia prima/insumo llamado X"), llamala vos mismo sin pedir confirmación. Pero DENTRO del flujo de registrar una factura (ver REGLAS PARA IMÁGENES/PDF DE FACTURAS más abajo), NUNCA la llames vos — ahí el ítem se crea solo, automáticamente, al confirmar la compra.
- IDENTIFICACIÓN DE PROVEEDORES: Nunca asumas que un proveedor no existe. Siempre llamá primero a buscar_proveedor_por_nombre con el nombre extraído del comprobante. El sistema hace matching fuzzy y encuentra el proveedor aunque el nombre tenga variaciones de acentos, mayúsculas o pequeños errores tipográficos. Solo si buscar_proveedor_por_nombre confirma que no existe, preguntale al usuario.
- Después de cada acción exitosa respondé con ✅ y los detalles (nombre, ID, total, etc.).
- Si hay un error respondé con ❌ y explicá el problema y cómo resolverlo.

DUPLICADOS: registrar_pago, registrar_pago_sueldo y registrar_gasto_pagado pueden devolver posible_duplicado=true en vez de registrar algo. En ese caso:
- NO reintentes solo ni asumas que es un error — mostrale al usuario el mensaje tal cual viene (incluye el link a lo ya cargado y quién/cuándo lo hizo).
- Preguntale si de verdad quiere cargarlo igual (puede ser un pago legítimo distinto que coincide en monto/fecha por casualidad, o el mismo comprobante subido dos veces).
- Si confirma que sí, volvé a llamar EXACTAMENTE a la misma función con los mismos datos, agregando confirmado_duplicado=true — no le vuelvas a preguntar nada más.
- Si dice que no, cancelá sin registrar nada y avisale que no se cargó.

ESTILO:
- Español argentino, tono natural y directo.
- Montos como $1.234,56. Usá listas para múltiples ítems.
- Sé conciso pero completo.
PROMPT;
    }

    /**
     * Sección de "help desk" del prompt: solo incluye los temas de AyudaModel::TEMAS
     * cuyo módulo el usuario actual puede ver (canView()) — si pregunta por un tema
     * fuera de esta lista, el prompt le indica que avise que no tiene acceso.
     * El servidor vuelve a validar el permiso en mensaje()/ejecutarHerramienta() antes
     * de armar la tarjeta, así que esto no es la única barrera, es la primera.
     */
    private function buildGuiaAyudaPrompt(): string
    {
        $bloques = [];
        foreach ($this->ayudaModel->activos() as $clave => $tema) {
            if (!canView($tema['modulo'])) continue;
            $bloques[] = "· tema=\"{$clave}\" — {$tema['titulo']}:\n{$tema['manual']}";
        }

        if (!$bloques) {
            return "GUÍA DE USO DEL SISTEMA (help desk): este usuario no tiene acceso a ningún módulo con guía disponible. Si te pregunta \"cómo hago X\", avisale que no tiene permiso para esa sección y que consulte a un administrador.";
        }

        $manual = implode("\n\n", $bloques);

        return <<<GUIA
GUÍA DE USO DEL SISTEMA (help desk) — cuando te pregunten "cómo hago X" / "dónde está X" / "cómo se usa X" (no es una consulta de datos, es una pregunta de uso del sistema):
- Respondé en tus propias palabras, basándote en los pasos de abajo. No los copies literal, adaptalos al tono de la conversación.
- Al terminar tu respuesta, llamá SIEMPRE a la función mostrar_guia_ayuda con el "tema" exacto (la clave entre comillas) del tema que respondiste, para que el sistema le muestre un botón de acceso directo.
- Si la pregunta es sobre un tema que NO está en esta lista (el usuario no tiene permiso para ese módulo, o no existe un tema cargado todavía), NO llames a mostrar_guia_ayuda — avisale en texto que no tiene acceso a esa sección (si es por permisos) o que todavía no tenés una guía para eso (si el tema no existe), sin inventar pasos.

Temas disponibles para este usuario:
{$manual}
GUIA;
    }

    // ─── Definición de herramientas (formato Gemini functionDeclarations) ───────

    private function obtenerHerramientas(): array
    {
        return [[
            'functionDeclarations' => [
                [
                    'name'        => 'consultar_datos',
                    'description' => 'Ejecuta una consulta SELECT en la base de datos. Usala para responder preguntas sobre ventas, compras, stock, clientes, caja, ganancias, etc.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'sql'         => ['type' => 'STRING', 'description' => 'Consulta SELECT válida'],
                            'descripcion' => ['type' => 'STRING', 'description' => 'Descripción breve de qué consulta'],
                        ],
                        'required' => ['sql'],
                    ],
                ],
                [
                    'name'        => 'resumen_compras_pagos',
                    'description' => 'Devuelve en una sola consulta ya optimizada el listado combinado de compras (facturas_compra) y pagos (pagos_proveedores: a proveedores, sueldos y otros) de un período, ordenado por fecha, con una columna "tipo" que indica COMPRA o PAGO en cada fila. Usala SIEMPRE que pidan ver o controlar compras y pagos juntos de un período (ej. "qué se compró y qué se pagó esta semana", "revisar que se haya cargado todo") — NUNCA armes ese SQL vos mismo con consultar_datos, esta tool ya resuelve el cruce completo en un solo llamado.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'desde' => ['type' => 'STRING', 'description' => 'Fecha desde YYYY-MM-DD (opcional, default: hace 7 días)'],
                            'hasta' => ['type' => 'STRING', 'description' => 'Fecha hasta YYYY-MM-DD (opcional, default: hoy)'],
                        ],
                        'required' => [],
                    ],
                ],
                [
                    'name'        => 'consultar_margen_ventas',
                    'description' => 'Devuelve el margen de ventas de un período AGRUPADO POR PRODUCTO — el DETALLE producto por producto (a diferencia de consultar_margen_total_ventas, que da un único resumen general) — usa el costo REAL guardado al momento de cada venta (incluye ingrediente genérico como el helado, con el sabor puntual vendido, ya sumado dentro del costo — NO lo separes), no el costo de hoy aplicado para atrás. Es solo una VISTA PREVIA en el chat, no guarda nada (como consultar_datos). Usala SOLO cuando el usuario pida explícitamente el desglose "por producto"/"por item"/"detallado", NO para pedidos genéricos de margen (esos van a consultar_margen_total_ventas). Para el desglose por producto, mostrá cada uno como viñeta con: precio de venta total, Costo MP (un solo monto, "costo_total" del dato — NO lo dividas en MP directa/ingrediente genérico), y margen $ y %. Si un producto da costo $0 y no es lógico (ej. un producto elaborado), NO afirmes con seguridad la causa — el costo de cada venta es una FOTO calculada en el momento en que se vendió, así que un $0 puede deberse a que en ESE momento el producto tenía control de stock propio (controla_stock=1) o no tenía receta asignada, aunque eso ya se haya corregido después (una venta vieja nunca se recalcula con la configuración de hoy). Mencioná esa posibilidad junto con la de que directamente le falte configuración, y sugerí probar con una venta nueva o revisar la configuración actual del producto, en vez de afirmar categóricamente "no tiene receta cargada". Si el margen de un producto da negativo, avisá que el costo supera al precio de venta y sugerí revisar la receta o el precio. Si una fila viene con "vendido_sin_precio": true (se vendieron unidades pero el total vendido es $0), NO la reportes como "margen 0%" ni la escondas: avisale explícitamente que ese producto se vendió SIN precio de venta cargado (o se cargó la venta en $0), que por eso figura con ingreso $0, y ofrecele completarle el precio. Si una fila trae "componentes" (es una promo), ese array es el desglose de lo que se entregó adentro, con cantidad y costo por componente: mostralo solo si el usuario pide ver qué incluye la promo, SIEMPRE anidado bajo la promo y SIN margen por componente (un componente de promo no tiene precio propio, el ingreso lo cobra la promo entera) — y nunca lo listes como si fueran productos vendidos aparte. SOLO si el usuario pide explícitamente "guardarlo", "un reporte", "para descargar/exportar" o similar, generá ADEMÁS un reporte guardado con generar_reporte_predefinido (tipo=margen_ventas) — si no lo pide, no lo generes.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'desde' => ['type' => 'STRING', 'description' => 'Fecha desde YYYY-MM-DD (opcional, default: ayer)'],
                            'hasta' => ['type' => 'STRING', 'description' => 'Fecha hasta YYYY-MM-DD (opcional, default: ayer)'],
                        ],
                        'required' => [],
                    ],
                ],
                [
                    'name'        => 'consultar_margen_total_ventas',
                    'description' => 'Devuelve el RESUMEN/margen de ventas TOTAL (no por producto) de un período: Ventas Totales, Costo MP directa (incluye ingrediente genérico como el helado, SIN desglosar — usa el costo real guardado al momento de cada venta), y Sueldos/Alquiler/Servicios prorrateados como % de ventas sobre los últimos meses (mismo cálculo que la pestaña "Separación de Ingresos" de /reportes/ventas). Es solo una VISTA PREVIA en el chat, no guarda nada. Esta es la tool POR DEFECTO para cualquier pedido de margen/ganancia/rentabilidad de un período (ej. "¿cuánto gané ayer?", "margen del mes", "resumen de margen de ventas", "margen... resumidamente") — incluido el acceso directo "Resumen de margen de ventas". Usá la OTRA tool (consultar_margen_ventas) únicamente si el usuario pide explícitamente el desglose "por producto"/"detallado". La respuesta trae, por cada costo, su monto Y su "_pct" (el % que representa sobre las Ventas Totales de este período) — mostrá SIEMPRE ambos juntos, monto y %, en cada viñeta (ej. "Costo MP directa: $X (Y%)"). Formateá la respuesta con viñetas, EN ESTE ORDEN: Ventas Totales, Costo MP directa (con %), Costo de Sueldos % por ventas (con %), Costo de Alquiler % por ventas (con %), Costo de Servicios % por ventas (con %), Margen: $X (Y%). SOLO si el usuario pide explícitamente guardarlo/exportarlo, generá ADEMÁS un reporte con generar_reporte_predefinido (tipo=margen_ventas) — si no lo pide, no lo generes.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'desde' => ['type' => 'STRING', 'description' => 'Fecha desde YYYY-MM-DD (opcional, default: ayer)'],
                            'hasta' => ['type' => 'STRING', 'description' => 'Fecha hasta YYYY-MM-DD (opcional, default: ayer)'],
                        ],
                        'required' => [],
                    ],
                ],
                [
                    'name'        => 'consultar_lista_compras',
                    'description' => 'Devuelve lo que hay pendiente de comprar AHORA MISMO en la Lista de Compras (/lista-compras): tanto lo ya confirmado ("pendiente") como lo recién sugerido por el sistema y todavía sin confirmar ("sugerido"), agrupado por proveedor habitual, con los días de stock restante calculados según el consumo real. Usala para cualquier pregunta tipo "¿qué necesito comprar?", "¿qué me está por faltar?", "¿qué hay en la lista de compras?". Es una VISTA PREVIA, no modifica nada. Mostrá el resultado agrupado por proveedor, con cada ítem indicando cuántos días de stock quedan (o "stock bajo el mínimo" si no hay dato de días) y si es "sugerido" (todavía sin confirmar) o "pendiente" (ya confirmado, falta comprarlo). Si el usuario quiere agregar algo que no está en la lista, no lo hagas vos acá: usá buscar_item_por_nombre + pedir_confirmacion(accion="agregar_lista_compras").',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => new stdClass(),
                        'required'   => [],
                    ],
                ],
                [
                    'name'        => 'deuda_pendiente_total',
                    'description' => 'Devuelve TODO lo que está pendiente de pagar ahora mismo: facturas de compra sin saldar, gastos pendientes (alquiler, servicios, honorarios, impuestos — con o sin proveedor asociado) y sueldos de empleados sin liquidar, cada uno con su saldo real (ya descontados los pagos parciales). Es la tool POR DEFECTO para cualquier pregunta de "qué debemos" / "qué está pendiente de pago" / "pagos pendientes" — SIEMPRE que no aclaren explícitamente que quieren ver solo una categoría, llamala sin el parámetro categoria (trae las tres juntas). Si el usuario SÍ pide ver solo una parte (ej. "las compras pendientes", "solo sueldos", "los impuestos que debemos", "servicios pendientes"), pasá categoria con ese valor para filtrar. NO reemplaces esto por una consulta manual con consultar_datos: deja afuera gastos y/o sueldos. Es un estado ACTUAL (a hoy), no admite filtro de fecha — si piden específicamente facturas de compra pendientes DE UN PERÍODO puntual (ej. "de junio"), ahí sí usá consultar_datos con la tabla facturas_compra. Los resultados vienen ordenados por urgencia real: los gastos con fecha_vencimiento (único tipo que la tiene hoy) van primero, del más vencido/próximo a vencer al que falta más — si un ítem trae fecha_vencimiento, mencionala ("vence el DD/MM" o "venció hace N días"); las facturas de compra y sueldos no tienen ese dato, van ordenados por fecha de la operación.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'categoria' => [
                                'type'        => 'STRING',
                                'description' => 'Opcional — dejar sin pasar para traer TODO (compras + gastos + sueldos, el caso por defecto). Pasar "compras" para solo facturas de compra, "sueldos" para solo empleados, o "gastos" para solo gastos (alquiler/servicios/honorarios/impuestos) si el usuario pide específicamente una de esas categorías. Para pedir un tipo de gasto puntual (ej. "solo impuestos" o "solo servicios"), pasá categoria="gastos" y después filtrá/mencioná vos mismo, a partir de la columna "detalle" de cada fila, solo las que correspondan a esa categoría.',
                            ],
                        ],
                        'required'   => [],
                    ],
                ],
                [
                    'name'        => 'crear_cliente',
                    'description' => 'Crea un nuevo cliente en el sistema.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre'        => ['type' => 'STRING', 'description' => 'Nombre completo o razón social'],
                            'documento'     => ['type' => 'STRING', 'description' => 'Número de DNI o CUIT'],
                            'tipo_doc'      => ['type' => 'STRING', 'description' => 'Tipo de documento: dni o cuit'],
                            'condicion_iva' => ['type' => 'STRING', 'description' => 'consumidor_final, responsable_inscripto, monotributista o exento'],
                            'email'         => ['type' => 'STRING'],
                            'telefono'      => ['type' => 'STRING'],
                            'direccion'     => ['type' => 'STRING'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'crear_proveedor',
                    'description' => 'Crea un nuevo proveedor en el sistema.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'razon_social'  => ['type' => 'STRING'],
                            'cuit'          => ['type' => 'STRING', 'description' => 'Formato XX-XXXXXXXX-X'],
                            'condicion_iva' => ['type' => 'STRING', 'description' => 'responsable_inscripto, monotributista o exento'],
                            'email'         => ['type' => 'STRING'],
                            'telefono'      => ['type' => 'STRING'],
                            'direccion'     => ['type' => 'STRING'],
                            'banco'         => ['type' => 'STRING'],
                            'cbu'           => ['type' => 'STRING', 'description' => '22 dígitos numéricos'],
                        ],
                        'required' => ['razon_social'],
                    ],
                ],
                [
                    'name'        => 'crear_usuario',
                    'description' => 'Crea un nuevo usuario del sistema. Solo puede hacerlo un administrador.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre'   => ['type' => 'STRING'],
                            'email'    => ['type' => 'STRING'],
                            'password' => ['type' => 'STRING', 'description' => 'Contraseña, mínimo 8 caracteres'],
                            'rol'      => ['type' => 'STRING', 'description' => 'admin, cajero, deposito o vendedor'],
                        ],
                        'required' => ['nombre', 'email', 'password', 'rol'],
                    ],
                ],
                [
                    'name'        => 'mostrar_confirmacion_compra',
                    'description' => 'Muestra al usuario una tarjeta interactiva para confirmar el registro de una factura de compra, con selects para resolver los ítems ambiguos. Llamala en vez de escribir el resumen como texto y en vez de llamar a registrar_compra directamente: el sistema se encarga de mostrar los botones (Confirmar/Editar/Cancelar) y de ejecutar el registro cuando el usuario confirme. Requiere que el proveedor y cada ítem ya se hayan resuelto con buscar_proveedor_por_nombre / buscar_item_por_nombre.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'proveedor_id'     => ['type' => 'NUMBER', 'description' => 'ID del proveedor (de buscar_proveedor_por_nombre)'],
                            'proveedor_nombre' => ['type' => 'STRING', 'description' => 'Nombre o razón social del proveedor tal como figura en el sistema'],
                            'nro_comprobante'  => ['type' => 'STRING', 'description' => 'Número de factura. Ej: FA-0001-00000001'],
                            'fecha'            => ['type' => 'STRING', 'description' => 'Fecha YYYY-MM-DD. Por defecto hoy'],
                            'tipo_comprobante' => ['type' => 'STRING', 'description' => 'factura_a, factura_b, factura_c, remito u otro'],
                            'condicion_pago'   => ['type' => 'STRING', 'description' => 'contado, credito o cheque'],
                            'metodo_pago'      => ['type' => 'STRING', 'description' => 'Si el usuario mencionó cómo pagó: efectivo, transferencia, debito, mercado_pago, cheque u otro. Omitir si no lo mencionó.'],
                            'origen_pago'      => ['type' => 'STRING', 'description' => '"empresa" (default, omitir si no se aclara) o "socio" si el usuario dice que pagó esta compra con plata propia/de un socio. Requiere socio_id.'],
                            'socio_id'         => ['type' => 'NUMBER', 'description' => 'ID del socio (de la lista de socios del prompt) cuando origen_pago="socio".'],
                            'descuento_info'   => ['type' => 'STRING', 'description' => 'Descripción de descuentos ya absorbidos en los precios unitarios (ej: "Ahorro Club DIA -$1.400 distribuido proporcionalmente"). Omitir si no hubo descuentos.'],
                            'total_comprobante' => ['type' => 'NUMBER', 'description' => 'El TOTAL final impreso en el comprobante, tal cual figura (ej: el número junto a "TOTAL $"). Pasalo siempre que el documento muestre un total, aunque no coincida con la suma de los ítems — el sistema se lo muestra al usuario para que pueda ajustar la diferencia.'],
                            'items' => [
                                'type'  => 'ARRAY',
                                'items' => [
                                    'type'       => 'OBJECT',
                                    'properties' => [
                                        'nombre_ticket'   => ['type' => 'STRING', 'description' => 'Nombre del ítem tal como figura en la factura'],
                                        'estado'          => ['type' => 'STRING', 'description' => '"ok" si buscar_item_por_nombre lo encontró con certeza (via_alias o encontrado=true), "ambiguo" si devolvió opciones[], "nuevo" si no existe y se va a crear. Si tipo_item="activo_fijo" dejalo en "nuevo" (no aplica, no se resuelve contra ningún catálogo)'],
                                        'item_id'         => ['type' => 'NUMBER', 'description' => 'ID del ítem en el sistema. Requerido cuando estado="ok". No aplica (dejar vacío) si tipo_item="activo_fijo".'],
                                        'tipo_item'       => ['type' => 'STRING', 'description' => 'materia_prima, insumo, producto, o activo_fijo si es un bien de uso/equipamiento (no mercadería) — ver REGLAS PARA IMÁGENES/PDF DE FACTURAS. Requerido cuando estado="ok".'],
                                        'opciones' => [
                                            'type'  => 'ARRAY',
                                            'description' => 'Las opciones[] devueltas por buscar_item_por_nombre. Requerido cuando estado="ambiguo".',
                                            'items' => [
                                                'type'       => 'OBJECT',
                                                'properties' => [
                                                    'id'        => ['type' => 'NUMBER'],
                                                    'nombre'    => ['type' => 'STRING'],
                                                    'tipo_item' => ['type' => 'STRING'],
                                                ],
                                            ],
                                        ],
                                        'cantidad'        => ['type' => 'NUMBER'],
                                        'precio_unitario' => ['type' => 'NUMBER', 'description' => 'Precio unitario BRUTO tal cual figura impreso en el comprobante para este ítem (columna "Precio Unit." o similar), SIN restar ningún descuento — copialo literal, no hagas la resta vos.'],
                                        'descuento_monto' => ['type' => 'NUMBER', 'description' => 'Monto total de descuento para esta línea, tal cual figura impreso (ej: el número de la línea "Desc ... -$X" que corresponde a este ítem). Es el descuento de TODA la línea (ya multiplicado por la cantidad), no por unidad. Omitir o poner 0 si no hay descuento para este ítem. El sistema se encarga de restarlo y dividirlo — no calcules vos el precio ya descontado.'],
                                        'iva_porcentaje'  => ['type' => 'NUMBER', 'description' => 'Alícuota IVA: 0, 10.5 o 21. Por defecto 0'],
                                    ],
                                    'required' => ['nombre_ticket', 'estado', 'cantidad', 'precio_unitario'],
                                ],
                            ],
                        ],
                        'required' => ['proveedor_id', 'proveedor_nombre', 'items'],
                    ],
                ],
                [
                    'name'        => 'ajustar_stock',
                    'description' => 'Ajusta el stock de un ítem sumando/restando directamente, SIN tocar nada más. Cantidad positiva = ingreso, negativa = egreso o merma. Usala para correcciones de inventario, mermas o roturas. NO la uses cuando el usuario dice que produjo/elaboró/horneó un producto con receta (ej. "hice 50 cookies") — para eso existe registrar_produccion, que además descuenta los ingredientes correspondientes.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre_o_codigo' => ['type' => 'STRING', 'description' => 'Nombre o código del ítem'],
                            'tipo_item'       => ['type' => 'STRING', 'description' => 'producto, materia_prima o insumo (opcional)'],
                            'cantidad'        => ['type' => 'NUMBER', 'description' => 'Positivo para agregar, negativo para restar'],
                            'motivo'          => ['type' => 'STRING'],
                        ],
                        'required' => ['nombre_o_codigo', 'cantidad'],
                    ],
                ],
                [
                    'name'        => 'registrar_produccion',
                    'description' => 'Registra que se elaboró/produjo una cantidad de un producto con receta (ej: "hice 50 cookies", "produje 20 medialunas", "elaboré 3 tortas de chocolate"). Busca la receta activa asociada a ese producto, descuenta automáticamente sus ingredientes (materias primas/insumos) según la receta —contemplando mermas y sub-recetas— y suma esa cantidad al stock del producto terminado. Si no alcanza el stock de algún ingrediente, NO registra nada y devuelve el detalle de qué falta. La cantidad que pasás es en unidades del producto terminado (lo que se ve en su stock), no en "lotes"/"producciones" de la receta — la conversión la hace el sistema solo, usando el rendimiento configurado en la receta.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre_producto' => ['type' => 'STRING', 'description' => 'Nombre del producto elaborado que se produjo (ej: "Cookies", "Medialunas de manteca")'],
                            'cantidad'        => ['type' => 'NUMBER', 'description' => 'Cantidad de unidades del producto terminado que se produjeron'],
                        ],
                        'required' => ['nombre_producto', 'cantidad'],
                    ],
                ],
                [
                    'name'        => 'buscar_item_por_nombre',
                    'description' => 'Busca un ítem (materia prima, insumo o producto) por nombre. Primero revisa el historial de alias del proveedor; si no hay alias, hace matching fuzzy. Cuando no encuentra con certeza, devuelve opciones[] para que el usuario elija. Llamá ANTES de registrar_compra para cada ítem del comprobante. Pasá siempre proveedor_id para aprovechar el historial.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre'       => ['type' => 'STRING', 'description' => 'Nombre del ítem tal como aparece en la factura'],
                            'tipo_item'    => ['type' => 'STRING', 'description' => 'materia_prima, insumo o producto (opcional, para acotar la búsqueda)'],
                            'proveedor_id' => ['type' => 'NUMBER', 'description' => 'ID del proveedor (obtenido de buscar_proveedor_por_nombre). Requerido para usar el historial de alias.'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'buscar_proveedor_por_nombre',
                    'description' => 'Busca un proveedor por su nombre o razón social usando matching fuzzy. Llamá SIEMPRE a esta función antes de registrar_compra para obtener el nombre exacto del proveedor en el sistema, incluso si el nombre del comprobante tiene variaciones de acentos, mayúsculas o pequeños errores.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre' => ['type' => 'STRING', 'description' => 'Nombre o razón social del proveedor tal como aparece en la factura o remito'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'buscar_proveedor_por_identidad',
                    'description' => 'Busca un proveedor en el sistema usando un alias de Mercado Pago, CBU, nombre alternativo o CUIT tal como aparece en un comprobante de pago.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'valor' => ['type' => 'STRING', 'description' => 'Alias MP, CBU, nombre o CUIT extraído del comprobante'],
                        ],
                        'required' => ['valor'],
                    ],
                ],
                [
                    'name'        => 'buscar_facturas_pendientes',
                    'description' => 'Retorna las facturas de compra Y los gastos (alquiler, servicios, honorarios, etc.) pendientes de pago de un proveedor, con su saldo. Llamala SIEMPRE antes de registrar_pago cuando el pago sea para un proveedor conocido, para poder imputar el pago a la factura/gasto correcto en vez de dejarlo suelto.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'proveedor_id' => ['type' => 'NUMBER', 'description' => 'ID del proveedor'],
                        ],
                        'required' => ['proveedor_id'],
                    ],
                ],
                [
                    'name'        => 'crear_item',
                    'description' => 'Da de alta un nuevo ítem en el sistema (materia prima, insumo o producto). Se usa en dos casos: (1) el usuario pide DIRECTAMENTE crear/dar de alta un producto, materia prima o insumo (ej. "creá un producto Torta de Chocolate a $5000", "dame de alta un insumo Servilletas") — en ese caso llamala vos mismo, sin pedir confirmación; (2) un ítem aparece en una factura de compra y no existe en el sistema — en ESE caso NO la llames vos, se crea sola al confirmar la compra (ver mostrar_confirmacion_compra). Solo necesitás el nombre y el tipo; el resto es opcional.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre'             => ['type' => 'STRING', 'description' => 'Nombre del ítem'],
                            'tipo_item'          => ['type' => 'STRING', 'description' => 'Tipo: "materia_prima", "insumo" o "producto"'],
                            'precio_venta'       => ['type' => 'NUMBER', 'description' => 'Precio de venta. Solo aplica si tipo_item="producto" y el usuario lo mencionó. Si no se especifica, queda en 0 y se completa después desde el panel.'],
                            'cantidad_contenida' => ['type' => 'NUMBER', 'description' => 'Cantidad contenida por unidad/envase/bulto, expresada en unidad_medida (ej: 5000 si unidad_medida="Mililitro" y compraste un bidón de 5 litros; o 100 si compraste un bulto de 100 potes). Aplica a materias_primas e insumos. Omitir si no aplica (producto, o el ítem se compra y consume 1:1).'],
                            'unidad_medida'      => ['type' => 'STRING', 'description' => 'Unidad de medida base del ítem (nombre o abreviatura, ej: "kg", "Litro", "Unidad"). Es la unidad en la que se expresa cantidad_contenida y en la que las recetas van a consumir este ítem.'],
                            'categoria_nombre'   => ['type' => 'STRING', 'description' => 'Nombre de la categoría a la que pertenece (opcional). Se busca por nombre en el sistema.'],
                        ],
                        'required' => ['nombre', 'tipo_item'],
                    ],
                ],
                [
                    'name'        => 'crear_producto',
                    'description' => 'Da de alta un producto nuevo en el catálogo (alta completa, con más campos que crear_item: código, descripción, categoría, tipo elaborado/reventa, IVA, unidad de medida, stock mínimo, precio online, si se muestra en el menú online, activo). Requiere que el usuario tenga permiso de "editar" en el módulo Productos — si la función devuelve ok=false por falta de permiso, avisale al usuario que no tiene permiso para crear productos y no intentes otro camino para lograrlo (ni crear_item ni un INSERT vía consultar_datos). Para un alta rápida con solo nombre y tipo alcanza con crear_item.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre'           => ['type' => 'STRING', 'description' => 'Nombre del producto'],
                            'codigo'           => ['type' => 'STRING', 'description' => 'Código/SKU interno (opcional, debe ser único)'],
                            'descripcion'      => ['type' => 'STRING', 'description' => 'Descripción del producto (opcional)'],
                            'categoria_nombre' => ['type' => 'STRING', 'description' => 'Nombre de la categoría (opcional). Se busca por nombre en el sistema.'],
                            'tipo'             => ['type' => 'STRING', 'description' => '"elaborado" (se prepara con receta) o "reventa" (se compra y se revende tal cual). Por defecto "reventa".'],
                            'precio_venta'     => ['type' => 'NUMBER', 'description' => 'Precio de venta. Por defecto 0.'],
                            'iva'              => ['type' => 'NUMBER', 'description' => 'Alícuota de IVA: 0, 10.5 o 21. Por defecto 21.'],
                            'unidad_medida'    => ['type' => 'STRING', 'description' => 'Unidad de medida (nombre o abreviatura, ej: "kg", "Unidad"). Opcional.'],
                            'stock_minimo'     => ['type' => 'NUMBER', 'description' => 'Stock mínimo antes de avisar reposición. Por defecto 0.'],
                            'controla_stock'   => ['type' => 'BOOLEAN', 'description' => 'Si el sistema debe controlar el stock de este producto. Por defecto true.'],
                            'precio_online'    => ['type' => 'NUMBER', 'description' => 'Precio para el menú online (MenuApp), si es distinto al del local. Omitir para que el menú online use el mismo precio_venta.'],
                            'ecommerce'        => ['type' => 'BOOLEAN', 'description' => 'Si el producto se muestra en el menú online (MenuApp). Por defecto false.'],
                            'activo'           => ['type' => 'BOOLEAN', 'description' => 'Si el producto queda activo. Por defecto true.'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'editar_producto',
                    'description' => 'Modifica un producto ya existente en el catálogo. Requiere que el usuario tenga permiso de "editar" en el módulo Productos — si la función devuelve ok=false por falta de permiso, avisale al usuario que no tiene permiso para editar productos y no intentes otro camino para lograrlo. Pasá producto_id si ya lo conocés (ej. de buscar_item_por_nombre con tipo_item="producto"), o nombre_o_codigo para que la función lo busque. Solo se modifican los campos que se incluyan — el resto queda igual que estaba.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'producto_id'      => ['type' => 'NUMBER', 'description' => 'ID del producto a editar, si ya se conoce.'],
                            'nombre_o_codigo'  => ['type' => 'STRING', 'description' => 'Nombre o código del producto a buscar, si no se conoce el ID.'],
                            'nombre'           => ['type' => 'STRING', 'description' => 'Nuevo nombre (opcional).'],
                            'codigo'           => ['type' => 'STRING', 'description' => 'Nuevo código/SKU (opcional).'],
                            'descripcion'      => ['type' => 'STRING', 'description' => 'Nueva descripción (opcional).'],
                            'categoria_nombre' => ['type' => 'STRING', 'description' => 'Nueva categoría, por nombre (opcional).'],
                            'tipo'             => ['type' => 'STRING', 'description' => '"elaborado" o "reventa" (opcional).'],
                            'precio_venta'     => ['type' => 'NUMBER', 'description' => 'Nuevo precio de venta (opcional).'],
                            'iva'              => ['type' => 'NUMBER', 'description' => 'Nueva alícuota de IVA: 0, 10.5 o 21 (opcional).'],
                            'unidad_medida'    => ['type' => 'STRING', 'description' => 'Nueva unidad de medida, nombre o abreviatura (opcional).'],
                            'stock_minimo'     => ['type' => 'NUMBER', 'description' => 'Nuevo stock mínimo (opcional).'],
                            'controla_stock'   => ['type' => 'BOOLEAN', 'description' => 'Si el sistema debe controlar el stock de este producto (opcional).'],
                            'precio_online'    => ['type' => 'NUMBER', 'description' => 'Nuevo precio para el menú online (MenuApp). Mandá 0 para volver a usar el precio del local (opcional).'],
                            'ecommerce'        => ['type' => 'BOOLEAN', 'description' => 'Si el producto se muestra en el menú online (MenuApp) (opcional).'],
                            'activo'           => ['type' => 'BOOLEAN', 'description' => 'Si el producto queda activo o inactivo (opcional).'],
                        ],
                        'required' => [],
                    ],
                ],
                [
                    'name'        => 'buscar_empleado_por_nombre',
                    'description' => 'Busca un empleado por su nombre usando matching fuzzy. Llamá SIEMPRE antes de marcar_feriado_trabajado, registrar_ausencia, registrar_horas_recuperadas o consultar_deuda_empleado para obtener el empleado_id.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre' => ['type' => 'STRING', 'description' => 'Nombre del empleado tal como lo escribió el usuario'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'consultar_deuda_empleado',
                    'description' => 'Devuelve cuántas horas y cuánto dinero equivalente debe un empleado por ausencias tipo="recuperar" todavía no saldadas.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'empleado_id' => ['type' => 'NUMBER', 'description' => 'ID del empleado (obtenido de buscar_empleado_por_nombre)'],
                        ],
                        'required' => ['empleado_id'],
                    ],
                ],
                [
                    'name'        => 'registrar_pago',
                    'description' => 'Registra un pago a un proveedor. IMPORTANTE: para que la factura o el gasto queden marcados como pagados debés pasar factura_id/factura_ids (facturas de compra) o gasto_id/gasto_ids (alquiler, servicios, honorarios, etc. — obtenidos de buscar_facturas_pendientes). Sin esos campos el pago se registra pero el comprobante queda en estado pendiente.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'proveedor_id'   => ['type' => 'NUMBER',  'description' => 'ID del proveedor'],
                            'fecha'          => ['type' => 'STRING',  'description' => 'Fecha del pago YYYY-MM-DD'],
                            'monto_total'    => ['type' => 'NUMBER',  'description' => 'Monto total pagado'],
                            'metodo'         => ['type' => 'STRING',  'description' => 'mercado_pago, efectivo, debito, transferencia, cheque u otro'],
                            'referencia_mp'  => ['type' => 'STRING',  'description' => 'Número de operación MP o referencia bancaria (opcional)'],
                            'concepto'       => ['type' => 'STRING',  'description' => 'Descripción libre del pago (opcional)'],
                            'origen_pago'    => ['type' => 'STRING',  'description' => '"empresa" (default, omitir si no se aclara) o "socio" si el usuario dice que pagó con plata propia/de un socio/"de mi bolsillo". Requiere socio_id.'],
                            'socio_id'       => ['type' => 'NUMBER',  'description' => 'ID del socio (de la lista de socios del prompt) cuando origen_pago="socio".'],
                            'factura_id'     => ['type' => 'NUMBER',  'description' => 'ID de la factura a saldar (usar cuando el pago cubre exactamente UNA factura — es el id retornado por registrar_compra o buscar_facturas_pendientes)'],
                            'factura_ids'    => [
                                'type'  => 'ARRAY',
                                'items' => ['type' => 'NUMBER'],
                                'description' => 'IDs de varias facturas a imputar (usar cuando el pago se distribuye entre múltiples facturas)',
                            ],
                            'montos_aplicados' => [
                                'type'  => 'ARRAY',
                                'items' => ['type' => 'NUMBER'],
                                'description' => 'Montos a aplicar a cada factura de factura_ids (mismo orden)',
                            ],
                            'gasto_id'       => ['type' => 'NUMBER',  'description' => 'ID del gasto a saldar (alquiler, servicios, honorarios, etc.) — usar cuando el pago cubre exactamente UN gasto pendiente encontrado con buscar_facturas_pendientes'],
                            'gasto_ids'      => [
                                'type'  => 'ARRAY',
                                'items' => ['type' => 'NUMBER'],
                                'description' => 'IDs de varios gastos a imputar (usar cuando el pago se distribuye entre múltiples gastos)',
                            ],
                            'montos_aplicados_gastos' => [
                                'type'  => 'ARRAY',
                                'items' => ['type' => 'NUMBER'],
                                'description' => 'Montos a aplicar a cada gasto de gasto_ids (mismo orden)',
                            ],
                            'numero_comprobante' => ['type' => 'STRING', 'description' => 'Número de comprobante/operación impreso en el recibo, si aparece (opcional, refuerza la detección de duplicados)'],
                            'confirmado_duplicado' => ['type' => 'BOOLEAN', 'description' => 'Pasar true SOLO en el segundo intento, cuando el usuario ya confirmó que quiere cargarlo de todos modos después de un aviso de posible_duplicado'],
                        ],
                        'required' => ['proveedor_id', 'monto_total', 'metodo'],
                    ],
                ],
                [
                    'name'        => 'registrar_pago_sueldo',
                    'description' => 'Registra el pago del SUELDO de un empleado (no de un proveedor — para proveedores usá registrar_pago). Llamá primero a buscar_empleado_por_nombre para obtener el empleado_id, y a consultar_deuda_empleado o simplemente preguntale al usuario el monto si no lo sabés. Soporta pago parcial: si monto_total no cubre todo lo pendiente, el sistema liquida las jornadas/conceptos más antiguos primero hasta agotar ese monto y deja el resto pendiente para un próximo pago (nunca parte un ítem individual a la mitad) — no hace falta pedirle al usuario el monto exacto ni derivarlo al formulario web para esto.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'empleado_id' => ['type' => 'NUMBER', 'description' => 'ID del empleado (obtenido de buscar_empleado_por_nombre)'],
                            'monto_total' => ['type' => 'NUMBER', 'description' => 'Monto pagado. Si es menor al total pendiente de liquidar, se aplica como pago parcial (ítems más antiguos primero) — no es obligatorio que cubra todo.'],
                            'metodo'      => ['type' => 'STRING', 'description' => 'mercado_pago, efectivo, debito, transferencia, cheque u otro'],
                            'fecha'       => ['type' => 'STRING', 'description' => 'Fecha del pago YYYY-MM-DD (opcional, default hoy)'],
                            'concepto'    => ['type' => 'STRING', 'description' => 'Descripción libre del pago (opcional)'],
                            'origen_pago' => ['type' => 'STRING', 'description' => '"empresa" (default, omitir si no se aclara) o "socio" si el usuario dice que pagó con plata propia/de un socio. Requiere socio_id.'],
                            'socio_id'    => ['type' => 'NUMBER', 'description' => 'ID del socio (de la lista de socios del prompt) cuando origen_pago="socio".'],
                            'numero_comprobante' => ['type' => 'STRING', 'description' => 'Número de comprobante/operación impreso en el recibo, si aparece (opcional, refuerza la detección de duplicados)'],
                            'confirmado_duplicado' => ['type' => 'BOOLEAN', 'description' => 'Pasar true SOLO en el segundo intento, cuando el usuario ya confirmó que quiere cargarlo de todos modos después de un aviso de posible_duplicado'],
                        ],
                        'required' => ['empleado_id', 'monto_total', 'metodo'],
                    ],
                ],
                [
                    'name'        => 'registrar_gasto_pagado',
                    'description' => 'Crea un gasto YA PAGADO (servicio, alquiler, impuesto, honorario) junto con su pago, en un solo paso — usar SOLO cuando buscar_facturas_pendientes no encontró ningún gasto pendiente pre-cargado que coincida (si lo encontró, usar registrar_pago con gasto_id en vez de esta función, para no duplicar el gasto).',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'categoria_id'   => ['type' => 'NUMBER', 'description' => 'ID de la categoría del gasto, de la lista de categorías del prompt'],
                            'descripcion'    => ['type' => 'STRING', 'description' => 'Descripción breve del gasto (ej: "Alquiler agosto 2026", "Factura de luz EDN")'],
                            'monto'          => ['type' => 'NUMBER', 'description' => 'Monto total del gasto/pago'],
                            'fecha'          => ['type' => 'STRING', 'description' => 'Fecha del comprobante YYYY-MM-DD'],
                            'proveedor_id'   => ['type' => 'NUMBER', 'description' => 'ID del proveedor si se pudo identificar (opcional)'],
                            'numero_comprobante' => ['type' => 'STRING', 'description' => 'Número de comprobante/operación impreso en el recibo, si aparece (opcional, refuerza la detección de duplicados)'],
                            'metodo'         => ['type' => 'STRING', 'description' => 'mercado_pago, efectivo, debito, transferencia, cheque u otro'],
                            'concepto'       => ['type' => 'STRING', 'description' => 'Descripción libre del pago (opcional, si no se pasa se usa descripcion)'],
                            'origen_pago'    => ['type' => 'STRING', 'description' => '"empresa" (default, omitir si no se aclara) o "socio" si el usuario dice que pagó con plata propia/de un socio. Requiere socio_id.'],
                            'socio_id'       => ['type' => 'NUMBER', 'description' => 'ID del socio (de la lista de socios del prompt) cuando origen_pago="socio".'],
                            'confirmado_duplicado' => ['type' => 'BOOLEAN', 'description' => 'Pasar true SOLO en el segundo intento, cuando el usuario ya confirmó que quiere cargarlo de todos modos después de un aviso de posible_duplicado'],
                        ],
                        'required' => ['categoria_id', 'descripcion', 'monto', 'fecha', 'metodo'],
                    ],
                ],
                [
                    'name'        => 'buscar_ingrediente_generico_por_nombre',
                    'description' => 'Busca ingredientes genéricos existentes por nombre parecido (ej. "jamón" encuentra "Jamón cocido"). Usala cuando una materia prima de una compra no tiene todavía un ingrediente genérico asignado, para sugerirle uno al usuario en vez de preguntar en blanco.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'nombre' => ['type' => 'STRING', 'description' => 'Texto a buscar'],
                        ],
                        'required' => ['nombre'],
                    ],
                ],
                [
                    'name'        => 'pedir_confirmacion',
                    'description' => 'Muestra al usuario una tarjeta con botones Confirmar/Cancelar para una de estas 7 acciones: marcar_feriado_trabajado, registrar_ausencia, registrar_horas_recuperadas, asignar_ingrediente_generico, registrar_activo_fijo, agregar_lista_compras o ignorar_producto_recordatorio. Llamala en vez de escribir el resumen como texto y en vez de llamar a la acción directamente — el sistema se encarga de mostrar los botones y de ejecutarla cuando el usuario confirme. Pasá accion + resumen_texto (el mismo resumen que ya arma el prompt) + solo los campos de abajo que correspondan a esa acción.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'accion'       => ['type' => 'STRING', 'description' => 'Nombre exacto de la acción a confirmar: marcar_feriado_trabajado, registrar_ausencia, registrar_horas_recuperadas, asignar_ingrediente_generico, registrar_activo_fijo, agregar_lista_compras o ignorar_producto_recordatorio'],
                            'resumen_texto'=> ['type' => 'STRING', 'description' => 'Texto del resumen a mostrar en la tarjeta antes de los botones'],
                            'empleado_id'  => ['type' => 'NUMBER', 'description' => 'ID del empleado (para marcar_feriado_trabajado, registrar_ausencia o registrar_horas_recuperadas)'],
                            'fecha'        => ['type' => 'STRING', 'description' => 'Fecha YYYY-MM-DD (para marcar_feriado_trabajado, registrar_ausencia o registrar_activo_fijo)'],
                            'tipo'         => ['type' => 'STRING', 'description' => '"descuento" o "recuperar" (para registrar_ausencia)'],
                            'horas'        => ['type' => 'NUMBER', 'description' => 'Horas (para marcar_feriado_trabajado, registrar_ausencia o registrar_horas_recuperadas)'],
                            'notas'        => ['type' => 'STRING', 'description' => 'Notas opcionales'],
                            'materia_prima_id'        => ['type' => 'NUMBER', 'description' => 'ID del ítem, si tipo_item="materia_prima" (para asignar_ingrediente_generico) — alias viejo de item_id, mantenido por compatibilidad'],
                            'item_id'                 => ['type' => 'NUMBER', 'description' => 'ID de la materia prima o insumo a asignar (para asignar_ingrediente_generico) — usar este en vez de materia_prima_id'],
                            'tipo_item'               => ['type' => 'STRING', 'description' => '"materia_prima" o "insumo" — de cuál es item_id (para asignar_ingrediente_generico). Default "materia_prima" si se omite.'],
                            'ingrediente_generico_id' => ['type' => 'NUMBER', 'description' => 'ID de un genérico existente (para asignar_ingrediente_generico)'],
                            'nombre_nuevo_generico'   => ['type' => 'STRING', 'description' => 'Nombre para crear un genérico nuevo (para asignar_ingrediente_generico)'],
                            'nombre'          => ['type' => 'STRING', 'description' => 'Nombre del bien (para registrar_activo_fijo). Ej: "Horno pizzero", "Cafetera espresso"'],
                            'valor'           => ['type' => 'NUMBER', 'description' => 'Monto total pagado por el bien, impuestos incluidos (para registrar_activo_fijo)'],
                            'categoria'       => ['type' => 'STRING', 'description' => 'Agrupación libre opcional, ej: "Cocina", "Mobiliario" (para registrar_activo_fijo)'],
                            'vida_util_meses' => ['type' => 'NUMBER', 'description' => 'Opcional: si el usuario pide amortizarlo, meses de vida útil (para registrar_activo_fijo). Omitir si no lo pidió.'],
                            'proveedor_id'    => ['type' => 'NUMBER', 'description' => 'ID del proveedor si se identificó (para registrar_activo_fijo, obtenido de buscar_proveedor_por_nombre/identidad)'],
                            'tipo_item'       => ['type' => 'STRING', 'description' => '"materia_prima", "insumo" o "producto" (para agregar_lista_compras) — viene del tipo_item devuelto por buscar_item_por_nombre'],
                            'item_id'         => ['type' => 'NUMBER', 'description' => 'ID del ítem a agregar a la lista de compras (para agregar_lista_compras) — viene del id devuelto por buscar_item_por_nombre'],
                            'producto_id'     => ['type' => 'STRING', 'description' => 'Para ignorar_producto_recordatorio: el/los ID de producto a dejar de recordar, tal como vienen en el RECORDATORIO PENDIENTE del prompt (cada nombre trae su id entre paréntesis). Uno solo ("14") o varios separados por coma ("14,22,31") si el usuario pidió ignorar más de uno de una vez.'],
                        ],
                        'required' => ['accion', 'resumen_texto'],
                    ],
                ],
                [
                    'name'        => 'generar_reporte_predefinido',
                    'description' => 'Genera y GUARDA uno de los reportes predefinidos del sistema (ventas, compras, stock, caja, produccion, pagos, nomina o margen_ventas) para un período de fechas, y lo deja disponible en la sección "Reportes IA" del sistema (tabla en pantalla + descarga en Excel). Usala cuando el pedido del usuario encaje directamente en uno de estos 8 tipos. Para pedidos que no encajen en ninguno, usá generar_reporte_personalizado.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'tipo'   => ['type' => 'STRING', 'description' => 'Uno de: ventas, compras, stock, caja, produccion, pagos, nomina, margen_ventas. Usá margen_ventas para pedidos de margen/rentabilidad/ganancia real por venta (ej. "cuánto gané ayer", "margen del mes"), no el promedio de receta: cada línea trae el costo de MP real (con el sabor de helado puntual elegido) guardado al momento de esa venta.'],
                            'desde'  => ['type' => 'STRING', 'description' => 'Fecha desde YYYY-MM-DD'],
                            'hasta'  => ['type' => 'STRING', 'description' => 'Fecha hasta YYYY-MM-DD'],
                            'titulo' => ['type' => 'STRING', 'description' => 'Título breve para identificar el reporte en la galería, ej: "Ventas de junio 2026"'],
                        ],
                        'required' => ['tipo', 'desde', 'hasta', 'titulo'],
                    ],
                ],
                [
                    'name'        => 'generar_reporte_personalizado',
                    'description' => 'Genera y GUARDA un reporte "a medida" a partir de una consulta SELECT, para pedidos que no encajan en ningún reporte predefinido (generar_reporte_predefinido). Mismas reglas que consultar_datos: solo SELECT, solo tablas permitidas. A diferencia de consultar_datos (que solo muestra una vista previa efímera en el chat), esto queda guardado en la sección "Reportes IA" con tabla en pantalla y descarga en Excel.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'titulo'      => ['type' => 'STRING', 'description' => 'Título breve para identificar el reporte en la galería'],
                            'descripcion' => ['type' => 'STRING', 'description' => 'Descripción breve de qué muestra el reporte'],
                            'sql'         => ['type' => 'STRING', 'description' => 'Consulta SELECT válida'],
                        ],
                        'required' => ['titulo', 'sql'],
                    ],
                ],
                [
                    'name'        => 'mostrar_guia_ayuda',
                    'description' => 'Muestra al usuario una tarjeta con un botón de acceso directo (y, para algunos temas, un tour guiado que resalta los botones reales en pantalla) sobre CÓMO USAR una sección del sistema. Llamala SIEMPRE al final de responder una pregunta de "cómo hago X" que esté cubierta por la guía de uso del prompt — nunca para preguntas de datos.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'tema' => ['type' => 'STRING', 'description' => 'Clave exacta del tema (una de las listadas en la guía de uso del sistema del prompt), ej: pos_facturar, caja_abrir_turno'],
                        ],
                        'required' => ['tema'],
                    ],
                ],
            ],
        ]];
    }

    // ─── Tarjetas de confirmación (mostrar_confirmacion_compra / pedir_confirmacion) ────

    /** Arma el payload estructurado de la tarjeta de compra a partir de los args que pasó Gemini. */
    private function armarPayloadConfirmacionCompra(array $args): array
    {
        $fecha           = (string) ($args['fecha'] ?? date('Y-m-d'));
        $tipoComprobante = (string) ($args['tipo_comprobante'] ?? 'factura_b');
        $nroComprobante  = (string) ($args['nro_comprobante'] ?? '');
        $proveedorNombre = (string) ($args['proveedor_nombre'] ?? '');

        // El IVA discriminado (que se SUMA al precio) solo existe en Factura A. En tickets,
        // remitos y Factura B/C a Consumidor Final el precio ya incluye el IVA (si lo hay) —
        // Gemini viene confundiendo esto reiteradamente (ver conversación), así que la regla
        // se aplica acá en código en vez de confiar solo en el prompt.
        $permiteIvaDiscriminado = $tipoComprobante === 'factura_a';

        // Primera pasada: cantidad/precio neto (con descuento de línea ya aplicado) de cada
        // ítem, sin decidir todavía el IVA — hace falta la suma neta total para poder backear
        // una alícuota sugerida a partir del total del comprobante.
        $itemsBase        = [];
        $sumaSubtotalNeto = 0.0;
        foreach ($args['items'] ?? [] as $it) {
            $cant        = (float) ($it['cantidad']        ?? 0);
            $precioBruto = (float) ($it['precio_unitario'] ?? 0);

            // El descuento por línea viene como monto total impreso (no por unidad) — la resta
            // y la división se hacen acá en código, no confiamos en que Gemini haga la
            // aritmética (venía calculando mal el precio neto en varios ítems por comprobante).
            $descuentoMonto = (float) ($it['descuento_monto'] ?? 0);
            $precio = $cant > 0 ? round($precioBruto - ($descuentoMonto / $cant), 4) : $precioBruto;
            $sub    = round($cant * $precio, 2);

            $sumaSubtotalNeto += $sub;
            $itemsBase[] = ['it' => $it, 'cant' => $cant, 'precio' => $precio, 'sub' => $sub];
        }

        // La mayoría de las Factura A no imprimen una alícuota por línea (solo un renglón de
        // IVA global al pie), así que Gemini casi nunca puede leer un iva_porcentaje por ítem
        // con certeza y lo deja en 0. En vez de dejar eso pasar (el total calculado terminaba
        // más bajo que el total real y el costo que se guarda por ítem quedaba sin IVA), se
        // backea una alícuota única a partir de Total comprobante vs. la suma neta de ítems,
        // redondeada a la alícuota real más cercana. Es solo una sugerencia PRECARGADA en el
        // dropdown de cada ítem — el usuario la confirma o la corrige por línea antes de
        // registrar, y nunca pisa un iva_porcentaje que Gemini haya traído explícito (>0).
        $ivaSugerido = 21.0;
        if ($permiteIvaDiscriminado && $sumaSubtotalNeto > 0
            && isset($args['total_comprobante']) && $args['total_comprobante'] !== '') {
            $ratio            = (((float) $args['total_comprobante']) - $sumaSubtotalNeto) / $sumaSubtotalNeto * 100;
            $alicuotasValidas = [0.0, 10.5, 21.0, 27.0];
            $mejorDif         = null;
            foreach ($alicuotasValidas as $a) {
                $dif = abs($a - $ratio);
                if ($mejorDif === null || $dif < $mejorDif) { $mejorDif = $dif; $ivaSugerido = $a; }
            }
        }

        $items       = [];
        $totalConIva = 0.0;

        foreach ($itemsBase as $base) {
            $it     = $base['it'];
            $cant   = $base['cant'];
            $precio = $base['precio'];
            $sub    = $base['sub'];

            $ivaExplicito = $permiteIvaDiscriminado ? (float) ($it['iva_porcentaje'] ?? 0) : 0.0;
            $ivaPct       = $permiteIvaDiscriminado ? ($ivaExplicito ?: $ivaSugerido) : 0.0;

            $totalConIva += round($sub * (1 + $ivaPct / 100), 2);

            $itemId   = isset($it['item_id']) ? (int) $it['item_id'] : null;
            $tipoItem = $it['tipo_item'] ?? null;

            // Nombre real del ítem ya existente al que se vinculó ("ok"/"ambiguo" con opción
            // elegida) — antes la tarjeta solo mostraba un badge "✅ materia prima" sin decir A
            // CUÁL, así que un match automático equivocado (ej. "CHOCOLATE" del ticket vinculado
            // sin preguntar a "Helado en balde de Chocolate con Almendras") pasaba desapercibido.
            $nombreResuelto = ((string) ($it['estado'] ?? '')) === 'ok'
                ? $this->nombreDeItemExistente($tipoItem, $itemId)
                : null;

            // Contenido x unidad YA cargado del ítem (no lo manda Gemini — se busca acá en
            // código para no depender de que el modelo lo repita bien) — así la tarjeta lo
            // muestra pre-completado y editable en vez de pedirlo como si fuera un ítem nuevo.
            $contenidaInfo = $this->infoContenidaItem($tipoItem, $itemId);

            // Ídem para cada opción del selector cuando el estado es "ambiguo": al elegir una
            // opción existente, el frontend necesita saber su contenido x unidad para completar
            // el campo (si el usuario termina eligiendo "Crear nuevo" en cambio, queda vacío).
            $opciones = $it['opciones'] ?? [];
            foreach ($opciones as &$op) {
                $opInfo = $this->infoContenidaItem($op['tipo_item'] ?? null, isset($op['id']) ? (int) $op['id'] : null);
                $op['cantidad_contenida'] = $opInfo['cantidad_contenida'];
                $op['unidad_medida_id']   = $opInfo['unidad_medida_id'];
            }
            unset($op);

            $items[] = [
                'nombre_ticket'      => (string) ($it['nombre_ticket'] ?? ''),
                'estado'             => (string) ($it['estado'] ?? 'nuevo'),
                'item_id'            => $itemId,
                'tipo_item'          => $tipoItem,
                'nombre_resuelto'    => $nombreResuelto,
                'opciones'           => $opciones,
                'cantidad'           => $cant,
                'precio_unitario'    => $precio,
                'iva_porcentaje'     => $ivaPct,
                'subtotal'           => $sub,
                'cantidad_contenida' => $contenidaInfo['cantidad_contenida'],
                'unidad_medida_id'   => $contenidaInfo['unidad_medida_id'],
            ];
        }

        $resumen = "📋 Resumen de la compra a registrar:\n"
            . "- Proveedor: {$proveedorNombre}\n"
            . "- Fecha: {$fecha}\n"
            . "- Comprobante: {$tipoComprobante} {$nroComprobante}\n"
            . "- Ítems:\n";
        foreach ($items as $it) {
            $estadoTxt = $it['tipo_item'] === 'activo_fijo' ? '🏗️ activo fijo' : match ($it['estado']) {
                'ok'      => '✅ resuelto',
                'ambiguo' => '❓ a confirmar',
                default   => '➕ se creará nuevo',
            };
            $resumen .= "  · {$it['nombre_ticket']} ({$estadoTxt}) — {$it['cantidad']} x $"
                . number_format($it['precio_unitario'], 2, ',', '.') . "\n";
        }
        if (!empty($args['descuento_info'])) {
            $resumen .= "- Descuentos aplicados: {$args['descuento_info']}\n";
        }
        $resumen .= "- Total: $" . number_format($totalConIva, 2, ',', '.') . "\n";

        $origenPago = ($args['origen_pago'] ?? 'empresa') === 'socio' ? 'socio' : 'empresa';
        $socioId    = null;
        $nombreSocio = null;
        if ($origenPago === 'socio' && !empty($args['socio_id'])) {
            require_once BASE_PATH . '/app/Models/SocioModel.php';
            $socio = (new SocioModel())->findById((int) $args['socio_id']);
            if ($socio) { $socioId = (int) $socio['id']; $nombreSocio = $socio['nombre']; }
        }
        if ($origenPago === 'socio' && !$socioId) $origenPago = 'empresa';

        $resumen .= "- Pago: " . ($args['metodo_pago'] ?? 'sin confirmar')
            . ($origenPago === 'socio' ? " (aporte de {$nombreSocio})" : '');

        return [
            'tipo'             => 'compra',
            'proveedor_id'     => (int) ($args['proveedor_id'] ?? 0),
            'proveedor_nombre' => $proveedorNombre,
            'fecha'            => $fecha,
            'tipo_comprobante' => $tipoComprobante,
            'nro_comprobante'  => $nroComprobante,
            'condicion_pago'   => (string) ($args['condicion_pago'] ?? 'contado'),
            'metodo_pago'      => $args['metodo_pago']    ?? null,
            'origen_pago'      => $origenPago,
            'socio_id'         => $socioId,
            'nombre_socio'     => $nombreSocio,
            'descuento_info'   => $args['descuento_info'] ?? null,
            'items'            => $items,
            'total_estimado'   => round($totalConIva, 2),
            'total_comprobante' => isset($args['total_comprobante']) && $args['total_comprobante'] !== ''
                ? round((float) $args['total_comprobante'], 2)
                : null,
            'resumen_texto'    => $resumen,
            'estado'           => 'pendiente',
        ];
    }

    /** Arma el payload estructurado de una tarjeta de confirmación simple (sin ítems). */
    private function armarPayloadConfirmacionAccion(array $args): array
    {
        $campos     = ['empleado_id', 'fecha', 'tipo', 'horas', 'notas', 'materia_prima_id', 'ingrediente_generico_id', 'nombre_nuevo_generico', 'nombre', 'valor', 'categoria', 'vida_util_meses', 'proveedor_id', 'tipo_item', 'item_id', 'producto_id'];
        $accionArgs = [];
        foreach ($campos as $c) {
            if (isset($args[$c]) && $args[$c] !== '') $accionArgs[$c] = $args[$c];
        }

        return [
            'tipo'          => 'accion',
            'accion'        => (string) ($args['accion'] ?? ''),
            'args'          => $accionArgs,
            'resumen_texto' => trim((string) ($args['resumen_texto'] ?? '')) ?: 'Confirmá la acción.',
            'estado'        => 'pendiente',
        ];
    }

    /**
     * Ejecuta el registro real de una compra confirmada desde la tarjeta.
     * $resolucion viene indexada igual que $datos['items']: por cada ítem, opcionalmente
     * {item_id, tipo_item} (opción elegida) o {nuevo:true}, más cantidad/precio_unitario/
     * iva_porcentaje si el usuario los editó en la tarjeta.
     * $proveedorOverride (opcional): {id, nombre} si el usuario cambió el proveedor desde
     * el combobox de la tarjeta — {id: 0/null, nombre: '...'} para crear uno nuevo.
     * $ajuste (opcional): redondeo/descuento manual para que el total registrado coincida
     * con el total impreso en el comprobante, aunque este último tenga un error de suma.
     * $metodoPagoOverride (opcional): forma de pago elegida en la tarjeta — '' explícito
     * significa "sin especificar, queda pendiente"; null significa "no se tocó el select"
     * y se usa lo que ya venía en $datos.
     */
    private function confirmarCompra(
        array $datos,
        array $resolucion,
        int $usuarioId,
        ?array $proveedorOverride = null,
        float $ajuste = 0.0,
        ?string $metodoPagoOverride = null,
        ?string $archivoAdjunto = null,
        ?string $archivoMime = null,
        ?string $origenPagoOverride = null,
        ?int $socioIdOverride = null
    ): array {
        $proveedorId     = (int) ($datos['proveedor_id'] ?? 0);
        $proveedorNombre = (string) ($datos['proveedor_nombre'] ?? '');
        $metodoPago      = $metodoPagoOverride !== null ? $metodoPagoOverride : (string) ($datos['metodo_pago'] ?? '');
        $origenPago      = $origenPagoOverride !== null ? $origenPagoOverride : (string) ($datos['origen_pago'] ?? 'empresa');
        $socioId         = $origenPagoOverride !== null ? $socioIdOverride    : ($datos['socio_id'] ?? null);

        if ($proveedorOverride) {
            $idOverride     = (int) ($proveedorOverride['id'] ?? 0);
            $nombreOverride = trim((string) ($proveedorOverride['nombre'] ?? ''));

            if ($idOverride) {
                $proveedorId     = $idOverride;
                $proveedorNombre = $nombreOverride ?: $proveedorNombre;
            } elseif ($nombreOverride !== '') {
                $creado = $this->toolCrearProveedor(['razon_social' => $nombreOverride]);
                if (empty($creado['ok'])) {
                    return ['ok' => false, 'error' => "No se pudo crear el proveedor '{$nombreOverride}': " . ($creado['error'] ?? '')];
                }
                $proveedorId     = (int) $creado['id'];
                $proveedorNombre = $nombreOverride;
            }
        }

        // Mismo chequeo que ya usa el formulario manual de Compras (ComprasController::crear/editar):
        // proveedor + número de comprobante iguales no tienen ningún caso de uso legítimo, así que
        // acá se bloquea directo (a diferencia de pagos/gastos, donde el match es difuso y solo se
        // avisa) — con link a la factura ya cargada y quién la cargó.
        $nroComprobante = trim((string) ($datos['nro_comprobante'] ?? ''));
        if ($proveedorId && $nroComprobante !== '') {
            require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
            $dup = (new FacturaCompraModel())->buscarDuplicado($proveedorId, $nroComprobante);
            if ($dup) {
                $link = url('compras/ver?id=' . $dup['id']);
                return [
                    'ok'    => false,
                    'error' => "Ya existe una factura de este proveedor con el comprobante \"{$nroComprobante}\", "
                        . "cargada el {$dup['fecha']} por {$dup['usuario_nombre']}. Ver: {$link}",
                ];
            }
        }

        $itemsFinal = [];
        // Nombres de productos de reventa dados de alta en esta misma compra — este flujo
        // nunca pasa precio_venta ni categoria_nombre a toolCrearItem(), así que quedan
        // incompletos por definición (ver ProductoModel::findIncompletos()). Se usan para
        // avisarle al usuario en el mensaje final, en vez de dejarlo pasar en silencio.
        $productosNuevosIncompletos = [];

        // El frontend manda un $resolucion por cada ítem que el usuario dejó en la tarjeta
        // (no todos los originales — puede haber borrado alguno con la cruz roja "Quitar este
        // ítem"), etiquetado con su índice original en $datos['items'] (indice_original). Antes
        // se cruzaba por POSICIÓN de array ($resolucion[$i]), lo cual rompía todo: si se borraba
        // un ítem, el array de resolucion quedaba más corto y se corría, así que el ítem borrado
        // igual se cargaba (con datos de otro ítem pisados encima) en vez de saltearse.
        $resolucionPorIndice = [];
        $usaIndiceExplicito  = false;
        foreach ($resolucion as $r) {
            if (isset($r['indice_original'])) {
                $resolucionPorIndice[(int) $r['indice_original']] = $r;
                $usaIndiceExplicito = true;
            }
        }

        foreach ($datos['items'] as $i => $item) {
            if ($usaIndiceExplicito) {
                if (!isset($resolucionPorIndice[$i])) continue; // el usuario lo quitó en la tarjeta
                $res = $resolucionPorIndice[$i];
            } else {
                // Compatibilidad con tarjetas ya pendientes antes de este fix (sin indice_original).
                $res = $resolucion[$i] ?? [];
            }

            $cantidad = isset($res['cantidad'])       ? (float) $res['cantidad']       : (float) $item['cantidad'];
            $precio   = isset($res['precio_unitario']) ? (float) $res['precio_unitario'] : (float) $item['precio_unitario'];
            $ivaPct   = isset($res['iva_porcentaje'])  ? (float) $res['iva_porcentaje']  : (float) $item['iva_porcentaje'];

            $itemId   = null;
            $tipoItem = null;

            // La tarjeta ahora deja editar el ítem vinculado también cuando vino "ok" (match
            // automático) — antes esos ítems eran de solo lectura, así que un match automático
            // equivocado no se podía corregir acá. Por eso $res manda siempre que venga con
            // item_id, sin importar el estado original; solo se cae al item_id/tipo_item
            // originales de $item como compatibilidad con tarjetas pendientes de antes de este
            // cambio (que no mandan item_id en la resolución de los ítems "ok").
            if (!empty($res['nuevo'])) {
                // queda en null: se crea un ítem nuevo más abajo
            } elseif (isset($res['item_id']) && $res['item_id']) {
                $itemId   = (int) $res['item_id'];
                $tipoItem = $res['tipo_item'] ?? null;
            } elseif ($item['estado'] === 'ok') {
                $itemId   = $item['item_id'];
                $tipoItem = $item['tipo_item'];
            }

            if (!$itemId) {
                // El nombre a dar de alta puede ser distinto al que figura en el ticket (el
                // usuario lo edita en la tarjeta para que quede prolijo) — nombre_ticket se
                // preserva sin tocar para el alias (así el próximo ticket con el mismo texto
                // sigue resolviendo a este mismo ítem).
                $nombreCrear = trim((string) ($res['nombre_item'] ?? '')) ?: $item['nombre_ticket'];

                $creado = $this->toolCrearItem([
                    'nombre'             => $nombreCrear,
                    // Tipo elegido por el usuario en la tarjeta (select "Tipo" que aparece al
                    // dar de alta) — antes quedaba fijo en materia_prima sin que se pudiera elegir.
                    'tipo_item'          => $tipoItem ?: ($res['tipo_item'] ?? 'materia_prima'),
                    // Cantidad real por unidad comprada (ej: bidón de 5 litros) — separada de
                    // la cantidad comprada, para que las recetas calculen bien el costo por uso.
                    'cantidad_contenida' => (isset($res['cantidad_contenida']) && $res['cantidad_contenida'] !== '')
                        ? (float) $res['cantidad_contenida']
                        : null,
                    'unidad_medida_id'   => (isset($res['unidad_medida_id']) && $res['unidad_medida_id'] !== '')
                        ? (int) $res['unidad_medida_id']
                        : null,
                    // Ingrediente genérico elegido en la tarjeta (select "Ingrediente genérico",
                    // solo aplica si el tipo elegido es materia_prima).
                    'ingrediente_generico_id' => (isset($res['ingrediente_generico_id']) && $res['ingrediente_generico_id'] !== '')
                        ? (int) $res['ingrediente_generico_id']
                        : null,
                ]);
                if (empty($creado['ok'])) {
                    return ['ok' => false, 'error' => "No se pudo crear '{$nombreCrear}': " . ($creado['error'] ?? '')];
                }
                $itemId   = $creado['id'];
                $tipoItem = $creado['tipo_item'];

                if ($tipoItem === 'producto') {
                    $productosNuevosIncompletos[] = $creado['nombre'];
                }

                if (empty($creado['tiene_generico'])) {
                    $this->sugerirIngredienteGenerico($usuarioId, $item['nombre_ticket'], (int) $itemId);
                }
            } elseif (in_array($tipoItem, ['materia_prima', 'insumo'], true) && isset($res['cantidad_contenida']) && $res['cantidad_contenida'] !== '') {
                // Ítem ya existente: la tarjeta muestra el contenido x unidad históricamente
                // cargado (ej. "Salsa de Chocolate" → 500 gr, o "Pote 1/4" → 100 unid) para que
                // el usuario lo corrija ahí mismo si el envase de esta compra cambió, sin tener
                // que ir aparte a la ficha del ítem. Solo actualiza si vino un valor (evita pisar
                // con null si el campo quedó vacío en la tarjeta).
                $this->actualizarContenidaItem(
                    $tipoItem,
                    $itemId,
                    (float) $res['cantidad_contenida'],
                    isset($res['unidad_medida_id']) && $res['unidad_medida_id'] !== '' ? (int) $res['unidad_medida_id'] : null
                );
            }

            $itemsFinal[] = [
                'nombre'             => $item['nombre_ticket'],
                'tipo_item'          => $tipoItem,
                'item_id'            => $itemId,
                'cantidad'           => $cantidad,
                'precio_unitario'    => $precio,
                'iva_porcentaje'     => $ivaPct,
                // Solo se informa cuando el usuario la cargó al crear el ítem — queda en el
                // detalle de la factura para trazabilidad, aunque también vive en el ítem.
                'cantidad_contenida' => (isset($res['cantidad_contenida']) && $res['cantidad_contenida'] !== '')
                    ? (float) $res['cantidad_contenida']
                    : null,
            ];
        }

        $resultCompra = $this->toolRegistrarCompra([
            'proveedor_id'     => $proveedorId,
            'proveedor_nombre' => $proveedorNombre,
            'nro_comprobante'  => $datos['nro_comprobante'],
            'fecha'            => $datos['fecha'],
            'tipo_comprobante' => $datos['tipo_comprobante'],
            'condicion_pago'   => $datos['condicion_pago'],
            'items'            => $itemsFinal,
            'ajuste'           => $ajuste,
        ]);
        if (empty($resultCompra['ok'])) return $resultCompra;

        // Si el usuario adjuntó la foto/PDF de la factura al pedirle al chat que la registre,
        // se copia como comprobante de la factura de compra recién creada (mismo lugar/formato
        // que "Comprobante adjunto" en compras/ver.php: storage/compras/, servido por
        // ComprasController::verComprobante()).
        if ($archivoAdjunto) {
            $origen = BASE_PATH . '/storage/chat_adjuntos/' . basename($archivoAdjunto);
            if (is_file($origen)) {
                $ext = match ($archivoMime) {
                    'application/pdf' => 'pdf',
                    'image/png'       => 'png',
                    'image/webp'      => 'webp',
                    default           => 'jpg',
                };
                $dirCompras = BASE_PATH . '/storage/compras/';
                if (!is_dir($dirCompras)) mkdir($dirCompras, 0755, true);
                $nombreCompra = 'compra_' . $resultCompra['id'] . '_' . time() . '.' . $ext;
                if (copy($origen, $dirCompras . $nombreCompra)) {
                    require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
                    (new FacturaCompraModel())->update((int) $resultCompra['id'], ['comprobante_archivo' => $nombreCompra]);
                }
            }
        }

        $mensaje = "Compra registrada a {$resultCompra['proveedor']} — {$datos['tipo_comprobante']} {$datos['nro_comprobante']}. "
            . "Total: $" . number_format($resultCompra['total'], 2, ',', '.') . " ({$resultCompra['items']} ítems).";
        if ($ajuste != 0) {
            $mensaje .= ' Incluye ajuste de ' . ($ajuste >= 0 ? '+' : '-') . '$' . number_format(abs($ajuste), 2, ',', '.') . '.';
        }

        foreach ($resultCompra['advertencias'] ?? [] as $adv) {
            $mensaje .= "\n⚠️ " . $adv;
        }

        if ($productosNuevosIncompletos) {
            $listaProductos = implode(', ', array_map(fn($n) => "\"{$n}\"", $productosNuevosIncompletos));
            $mensaje .= "\n\n📋 Di de alta " . count($productosNuevosIncompletos)
                . " producto(s) nuevo(s) sin precio de venta ni categoría: {$listaProductos}. "
                . "¿Querés decirme ahora a cuánto los vendés (y en qué categoría van), o preferís completarlo después desde Productos?";
        }

        if ($metodoPago !== '') {
            $pago = $this->toolRegistrarPago([
                'proveedor_id' => $proveedorId,
                'monto_total'  => $resultCompra['total'],
                'metodo'       => $metodoPago,
                'factura_id'   => $resultCompra['id'],
                'fecha'        => $datos['fecha'],
                'origen_pago'  => $origenPago,
                'socio_id'     => $socioId,
            ]);
            $mensaje .= !empty($pago['ok'])
                ? ' Pago registrado' . ($origenPago === 'socio' ? ' (aporte de socio).' : '.')
                : ' (No se pudo registrar el pago: ' . ($pago['error'] ?? '') . ')';
        }

        $resultCompra['mensaje'] = $mensaje;
        return $resultCompra;
    }

    /** Si un ítem recién creado no tiene ingrediente genérico, sugiere uno parecido (o queda sin preguntar si no hay candidatos). */
    private function sugerirIngredienteGenerico(int $usuarioId, string $nombreItem, int $materiaPrimaId): void
    {
        $palabraClave = trim((string) (explode(' ', $nombreItem)[0] ?? ''));
        $busq = $this->toolBuscarIngredienteGenericoPorNombre(['nombre' => $palabraClave !== '' ? $palabraClave : $nombreItem]);
        if (empty($busq['ok']) || empty($busq['resultados'])) return;

        $nombresGenericos = implode('" / "', array_map(static fn($r) => $r['nombre'], array_slice($busq['resultados'], 0, 3)));
        $texto = "¿'{$nombreItem}' es una variante de \"{$nombresGenericos}\" (mismo ingrediente, otra marca o paquete)? "
            . "Si querés, decime y lo asigno para que la próxima vez que compres cualquiera de las dos variantes el costo "
            . "de las recetas se actualice solo. (ID interno: {$materiaPrimaId})";
        $this->model->guardarMensaje($usuarioId, 'assistant', $texto);
    }

    // ─── Ejecución de herramientas ──────────────────────────────────────────────

    private function ejecutarHerramienta(string $nombre, array $args): array
    {
        return match ($nombre) {
            'consultar_datos'               => $this->toolConsultarDatos($args),
            'resumen_compras_pagos'         => $this->toolResumenComprasPagos($args),
            'consultar_margen_ventas'       => $this->toolConsultarMargenVentas($args),
            'consultar_margen_total_ventas' => $this->toolConsultarMargenTotalVentas($args),
            'consultar_lista_compras'       => $this->toolConsultarListaCompras($args),
            'agregar_lista_compras'         => $this->toolAgregarListaCompras($args),
            'confirmar_lista_compras_sugeridos' => $this->toolConfirmarSugeridosListaCompras($args),
            'ignorar_producto_recordatorio' => $this->toolIgnorarProductoRecordatorio($args),
            'deuda_pendiente_total'         => $this->toolDeudaPendienteTotal($args),
            'crear_cliente'                 => $this->toolCrearCliente($args),
            'crear_proveedor'               => $this->toolCrearProveedor($args),
            'crear_usuario'                 => $this->toolCrearUsuario($args),
            'registrar_compra'              => $this->toolRegistrarCompra($args),
            'ajustar_stock'                 => $this->toolAjustarStock($args),
            'registrar_produccion'          => $this->toolRegistrarProduccion($args),
            'crear_item'                     => $this->toolCrearItem($args),
            'crear_producto'                 => $this->toolCrearProducto($args),
            'editar_producto'                => $this->toolEditarProducto($args),
            'buscar_item_por_nombre'         => $this->toolBuscarItemPorNombre($args),
            'buscar_proveedor_por_nombre'    => $this->toolBuscarProveedorPorNombre($args),
            'buscar_proveedor_por_identidad'=> $this->toolBuscarProveedorPorIdentidad($args),
            'buscar_facturas_pendientes'    => $this->toolBuscarFacturasPendientes($args),
            'registrar_pago'                => $this->toolRegistrarPago($args),
            'registrar_pago_sueldo'         => $this->toolRegistrarPagoSueldo($args),
            'registrar_gasto_pagado'        => $this->toolRegistrarGastoPagado($args),
            'registrar_activo_fijo'         => $this->toolRegistrarActivoFijo($args),
            'buscar_ingrediente_generico_por_nombre' => $this->toolBuscarIngredienteGenericoPorNombre($args),
            'asignar_ingrediente_generico'  => $this->toolAsignarIngredienteGenerico($args),
            'buscar_empleado_por_nombre'    => $this->toolBuscarEmpleadoPorNombre($args),
            'marcar_feriado_trabajado'      => $this->toolMarcarFeriadoTrabajado($args),
            'registrar_ausencia'            => $this->toolRegistrarAusencia($args),
            'registrar_horas_recuperadas'   => $this->toolRegistrarHorasRecuperadas($args),
            'consultar_deuda_empleado'      => $this->toolConsultarDeudaEmpleado($args),
            'generar_reporte_predefinido'   => $this->toolGenerarReportePredefinido($args),
            'generar_reporte_personalizado' => $this->toolGenerarReportePersonalizado($args),
            'mostrar_guia_ayuda'            => $this->toolMostrarGuiaAyuda($args),
            default                         => ['ok' => false, 'error' => "Función desconocida: $nombre"],
        };
    }

    /**
     * Solo se llega acá cuando mensaje() NO pudo cortar con la tarjeta de ayuda (tema
     * inexistente o sin permiso) — devuelve el motivo para que Gemini se lo explique al
     * usuario en texto, sin ofrecerle nunca un botón hacia algo que no puede usar.
     */
    private function toolMostrarGuiaAyuda(array $args): array
    {
        $tema     = (string) ($args['tema'] ?? '');
        $registro = $this->ayudaModel->findPorTema($tema) ?: null;

        if ($registro === null) {
            return ['ok' => false, 'error' => 'No existe todavía una guía cargada para ese tema.'];
        }
        return ['ok' => false, 'error' => 'El usuario no tiene permiso para ese módulo — avisale que no tiene acceso, sin explicarle los pasos ni ofrecerle un link.'];
    }

    /**
     * Vista previa (no guarda nada) del margen de ventas agrupado por producto, con
     * costo desglosado MP directa / ingrediente genérico — ver ReportesModel::
     * margenVentasPorProducto() y el comentario de la tool 'consultar_margen_ventas'.
     * Default desde/hasta = ayer, porque es el pedido más común ("¿cuánto gané ayer?").
     */
    private function toolConsultarMargenVentas(array $args): array
    {
        try {
            require_once BASE_PATH . '/app/Models/ReportesModel.php';
            $ayer  = date('Y-m-d', strtotime('yesterday'));
            $desde = (string)($args['desde'] ?? $ayer);
            $hasta = (string)($args['hasta'] ?? $ayer);

            $datos = (new ReportesModel())->margenVentasPorProducto($desde, $hasta);
            return ['ok' => true, 'desde' => $desde, 'hasta' => $hasta, 'datos' => $datos, 'filas' => count($datos)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Vista previa (no guarda nada) del margen de ventas TOTAL de un período — ver
     * ReportesModel::margenVentasTotal() y el comentario de la tool
     * 'consultar_margen_total_ventas'. Default desde/hasta = ayer.
     */
    private function toolConsultarMargenTotalVentas(array $args): array
    {
        try {
            require_once BASE_PATH . '/app/Models/ReportesModel.php';
            $ayer  = date('Y-m-d', strtotime('yesterday'));
            $desde = (string)($args['desde'] ?? $ayer);
            $hasta = (string)($args['hasta'] ?? $ayer);

            $datos = (new ReportesModel())->margenVentasTotal($desde, $hasta);
            return ['ok' => true, 'datos' => $datos];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Vista previa (no modifica nada) de lo pendiente en la Lista de Compras, agrupado por proveedor. Ver ListaComprasModel. */
    private function toolConsultarListaCompras(array $args): array
    {
        try {
            $grupos = (new ListaComprasModel())->agrupadoPorProveedor();
            $total  = array_sum(array_map('count', $grupos));
            return ['ok' => true, 'total' => $total, 'grupos' => $grupos];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolConsultarDatos(array $args): array
    {
        try {
            $sql   = $args['sql'] ?? '';
            $datos = $this->model->ejecutarConsulta($sql);
            return ['ok' => true, 'datos' => $datos, 'filas' => count($datos), 'sql' => $sql];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta fija (no armada por Gemini) que cruza compras (facturas_compra) y pagos
     * (pagos_proveedores) de un período en un solo listado cronológico — pensada para el
     * acceso directo "compras y pagos de la semana". Al ser una consulta ya escrita en PHP,
     * con solo la fecha como variable, evita el problema real que veníamos viendo en
     * /gemini-log: el modelo reintentando varias veces un SQL armado a mano para este mismo
     * cruce, agotando el cupo de llamadas por minuto sin necesidad.
     */
    private function toolResumenComprasPagos(array $args): array
    {
        try {
            $desde = (string)($args['desde'] ?? date('Y-m-d', strtotime('-7 days')));
            $hasta = (string)($args['hasta'] ?? date('Y-m-d'));

            // COLLATE explícito en cada columna de texto: usuarios/proveedores/empleados/
            // facturas_compra no fijan collation propio (quedan en el "de servidor", que en
            // Hostinger/MariaDB nuevos es utf8mb4_uca1400_ai_ci) mientras que pagos_proveedores
            // sí fija utf8mb4_unicode_ci — mezclarlos en un UNION sin igualar el collation tira
            // "Illegal mix of collations" (mismo problema ya documentado en
            // database/socios_migration.sql). database/collation_fix_chat_tools_migration.sql
            // lo corrige a nivel tabla; esto de acá es un blindaje extra por si esa migración
            // todavía no se corrió.
            $stmt = Database::getInstance()->prepare(
                "SELECT fc.fecha, 'COMPRA' AS tipo,
                        CONVERT(p.razon_social USING utf8mb4) COLLATE utf8mb4_unicode_ci AS entidad,
                        fc.total_con_iva AS monto,
                        CONVERT(fc.nro_comprobante USING utf8mb4) COLLATE utf8mb4_unicode_ci AS detalle,
                        CONVERT(u.nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci AS usuario
                 FROM facturas_compra fc
                 JOIN proveedores p ON p.id = fc.proveedor_id AND p.eliminado = 0
                 LEFT JOIN usuarios u ON u.id = fc.usuario_id
                 WHERE fc.eliminado = 0 AND fc.estado != 'anulada' AND fc.fecha BETWEEN :desde1 AND :hasta1

                 UNION ALL

                 SELECT pp.fecha, 'PAGO' AS tipo,
                        COALESCE(
                            CONVERT(p2.razon_social USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                            CONVERT(e.nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                            pp.beneficiario
                        ) AS entidad,
                        pp.monto_total AS monto,
                        pp.concepto AS detalle,
                        CONVERT(u2.nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci AS usuario
                 FROM pagos_proveedores pp
                 LEFT JOIN proveedores p2 ON p2.id = pp.proveedor_id
                 LEFT JOIN empleados e   ON e.id  = pp.empleado_id
                 LEFT JOIN usuarios u2   ON u2.id = pp.usuario_id
                 WHERE pp.estado = 'activo' AND pp.eliminado = 0 AND pp.fecha BETWEEN :desde2 AND :hasta2

                 ORDER BY fecha DESC"
            );
            $stmt->execute([
                ':desde1' => $desde, ':hasta1' => $hasta,
                ':desde2' => $desde, ':hasta2' => $hasta,
            ]);
            $datos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['ok' => true, 'desde' => $desde, 'hasta' => $hasta, 'filas' => count($datos), 'datos' => $datos];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta fija (no armada por Gemini) que junta TODO lo pendiente de pagar a hoy:
     * facturas de compra sin saldar, gastos pendientes (con o sin proveedor) y sueldos de
     * empleados sin liquidar — las tres fuentes de "plata que debemos" que existen en el
     * sistema. Antes el chat solo miraba facturas_compra (ejemplo de SQL del prompt),
     * dejando afuera gastos y sueldos — reportado por el usuario al ver que "pagos
     * pendientes" no incluía nada de eso. Es un estado actual, no admite rango de fechas
     * (mismo criterio que la tarjeta "Deuda total a hoy" de Resumen Económico).
     */
    private function toolDeudaPendienteTotal(array $args): array
    {
        try {
            // Vacío/omitido = todo (comportamiento por defecto). Si el usuario pidió una sola
            // categoría, se saltan las consultas de las otras dos — ni falta traerlas.
            $categoria = strtolower(trim((string) ($args['categoria'] ?? '')));
            if (!in_array($categoria, ['compras', 'gastos', 'sueldos'], true)) $categoria = '';

            $facturas = $gastos = $sueldos = [];

            // 1) Facturas de compra pendientes, con el saldo real ya descontados los pagos parciales.
            if ($categoria === '' || $categoria === 'compras') {
                $facturas = Database::getInstance()->query(
                    "SELECT fc.fecha, 'COMPRA' AS tipo,
                            CONVERT(p.razon_social USING utf8mb4) COLLATE utf8mb4_unicode_ci AS entidad,
                            fc.total_con_iva - COALESCE((SELECT SUM(pf.monto_aplicado) FROM pagos_facturas pf
                                JOIN pagos_proveedores pp ON pp.id = pf.pago_id AND pp.estado = 'activo'
                                WHERE pf.factura_id = fc.id), 0) AS saldo
                     FROM facturas_compra fc
                     JOIN proveedores p ON p.id = fc.proveedor_id AND p.eliminado = 0
                     WHERE fc.estado = 'pendiente' AND fc.eliminado = 0"
                )->fetchAll(\PDO::FETCH_ASSOC);
            }

            // 2) Gastos pendientes (alquiler, servicios, honorarios, impuestos), con o sin
            // proveedor asociado, con el saldo real ya descontados los pagos parciales. Se suma
            // el nombre de la categoría (gasto_categorias) para que, si preguntan por un tipo
            // puntual (ej. "solo impuestos" o "solo servicios"), el modelo pueda filtrar/
            // mencionar las filas correctas a partir de esa columna sin otra consulta.
            if ($categoria === '' || $categoria === 'gastos') {
                $gastos = Database::getInstance()->query(
                    "SELECT g.fecha, g.fecha_vencimiento, 'GASTO' AS tipo,
                            COALESCE(
                                CONVERT(p.razon_social USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                                CONVERT(g.descripcion USING utf8mb4) COLLATE utf8mb4_unicode_ci
                            ) AS entidad,
                            g.monto - COALESCE((SELECT SUM(pg.monto_aplicado) FROM pagos_gastos pg
                                JOIN pagos_proveedores pp2 ON pp2.id = pg.pago_id AND pp2.estado = 'activo'
                                WHERE pg.gasto_id = g.id), 0) AS saldo,
                            CONVERT(gc.nombre USING utf8mb4) COLLATE utf8mb4_unicode_ci AS categoria_gasto
                     FROM gastos g
                     JOIN gasto_categorias gc ON gc.id = g.categoria_id
                     LEFT JOIN proveedores p ON p.id = g.proveedor_id
                     WHERE g.estado = 'pendiente' AND g.eliminado = 0"
                )->fetchAll(\PDO::FETCH_ASSOC);
            }

            // 3) Sueldos pendientes de liquidar — reusa EmpleadoModel::getResumenLiquidacion()
            // (la misma lógica que ya usa registrar_pago_sueldo) en vez de reimplementar el
            // cálculo de nómina acá, que varía según modalidad_pago de cada empleado.
            if ($categoria === '' || $categoria === 'sueldos') {
                require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
                $empleadoModel = new EmpleadoModel();
                foreach ($empleadoModel->findAllActivos() as $emp) {
                    $resumen = $empleadoModel->getResumenLiquidacion((int) $emp['id']);
                    if (!empty($resumen['ok']) && (float) $resumen['total_sugerido'] > 0.009) {
                        $sueldos[] = [
                            'fecha'   => date('Y-m-d'),
                            'tipo'    => 'SUELDO',
                            'entidad' => $emp['nombre'],
                            'saldo'   => (float) $resumen['total_sugerido'],
                        ];
                    }
                }
            }

            $datos = array_values(array_filter(
                array_merge($facturas, $gastos, $sueldos),
                fn($r) => (float) $r['saldo'] > 0.009
            ));
            // Lo que tiene una fecha de vencimiento real (solo gastos la tienen hoy) va primero,
            // ordenado por urgencia (vencido/por vencer antes que lo que vence más adelante);
            // el resto (facturas de compra y sueldos, sin ese dato) queda ordenado por fecha
            // de la operación, más reciente primero, como ya se venía haciendo.
            usort($datos, function ($a, $b) {
                $vA = $a['fecha_vencimiento'] ?? null;
                $vB = $b['fecha_vencimiento'] ?? null;
                if ($vA && $vB) return strcmp($vA, $vB);
                if ($vA) return -1;
                if ($vB) return 1;
                return strcmp((string) $b['fecha'], (string) $a['fecha']);
            });

            return ['ok' => true, 'filas' => count($datos), 'datos' => $datos];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Genera y guarda uno de los 7 reportes predefinidos (mismas queries que ReportesController::exportarCsv) */
    private function toolGenerarReportePredefinido(array $args): array
    {
        require_once BASE_PATH . '/app/Models/ReportesModel.php';
        require_once BASE_PATH . '/app/Models/ReporteIaModel.php';

        $tipo   = strtolower(trim((string)($args['tipo'] ?? '')));
        $desde  = (string)($args['desde']  ?? '');
        $hasta  = (string)($args['hasta']  ?? '');
        $titulo = trim((string)($args['titulo'] ?? ''));

        $tiposValidos = ['ventas', 'compras', 'stock', 'caja', 'produccion', 'pagos', 'nomina', 'margen_ventas'];
        if (!in_array($tipo, $tiposValidos, true)) {
            return ['ok' => false, 'error' => 'Tipo de reporte inválido. Debe ser uno de: ' . implode(', ', $tiposValidos)];
        }
        if ($desde === '' || $hasta === '' || $titulo === '') {
            return ['ok' => false, 'error' => 'Faltan datos: tipo, desde, hasta y titulo son obligatorios.'];
        }

        $reportesModel = new ReportesModel();
        $filas         = [];
        $columnas      = [];

        try {
            switch ($tipo) {
                case 'ventas':
                    $columnas = ['N° Venta', 'Fecha', 'Cajero', 'Ítems', 'Total c/IVA', 'IVA', 'Descuento', 'Método de pago'];
                    foreach ($reportesModel->ventasPorPeriodo($desde, $hasta, ['metodo_pago' => '', 'usuario_id' => 0]) as $r) {
                        $filas[] = array_combine($columnas, [
                            $r['id'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['cajero'], $r['items'],
                            (float)$r['total_con_iva'], (float)$r['total_iva'], (float)$r['descuento_global'], $r['metodo_pago'],
                        ]);
                    }
                    break;

                case 'compras':
                    $columnas = ['N°', 'Fecha', 'Comprobante', 'Tipo', 'Proveedor', 'Cond. pago', 'Total c/IVA', 'IVA', 'Estado'];
                    foreach ($reportesModel->comprasPorPeriodo($desde, $hasta, ['proveedor_id' => 0, 'tipo_comprobante' => '']) as $r) {
                        $filas[] = array_combine($columnas, [
                            $r['id'], date('d/m/Y', strtotime($r['fecha'])), $r['nro_comprobante'], $r['tipo_comprobante'],
                            $r['proveedor'], $r['condicion_pago'], (float)$r['total_con_iva'], (float)$r['total_iva'], $r['estado'],
                        ]);
                    }
                    break;

                case 'stock':
                    $columnas = ['Fecha', 'Ítem', 'Tipo', 'Origen', 'Cantidad', 'Stock anterior', 'Stock nuevo', 'Motivo', 'Usuario'];
                    foreach ($reportesModel->movimientosStock($desde, $hasta, ['tipo_item' => '', 'origen' => '']) as $r) {
                        $filas[] = array_combine($columnas, [
                            date('d/m/Y H:i', strtotime($r['created_at'])), $r['item_nombre'], $r['tipo_item'], $r['origen'],
                            (float)$r['cantidad'], (float)$r['cantidad_anterior'], (float)$r['cantidad_nueva'], $r['motivo'], $r['usuario'],
                        ]);
                    }
                    break;

                case 'caja':
                    $columnas = ['N° Turno', 'Apertura', 'Cierre', 'Cajero', 'Monto inicial', 'Cierre declarado', 'Cierre calculado', 'Diferencia', 'Estado'];
                    foreach ($reportesModel->turnosPorPeriodo($desde, $hasta) as $r) {
                        $filas[] = array_combine($columnas, [
                            $r['numero'], date('d/m/Y H:i', strtotime($r['apertura_at'])),
                            $r['cierre_at'] ? date('d/m/Y H:i', strtotime($r['cierre_at'])) : '', $r['cajero'],
                            (float)$r['monto_inicial'], (float)$r['monto_cierre_declarado'], (float)$r['monto_cierre_calculado'],
                            (float)$r['diferencia'], $r['estado'],
                        ]);
                    }
                    break;

                case 'produccion':
                    $columnas = ['ID', 'Fecha', 'Receta', 'Producto', 'Cantidad producida', 'Usuario'];
                    foreach ($reportesModel->produccionPorPeriodo($desde, $hasta, ['receta_id' => 0]) as $r) {
                        $filas[] = array_combine($columnas, [
                            $r['id'], date('d/m/Y H:i', strtotime($r['created_at'])), $r['receta_nombre'],
                            $r['producto_nombre'], (float)$r['cantidad_producida'], $r['usuario_nombre'],
                        ]);
                    }
                    break;

                case 'pagos':
                    $columnas = ['Tipo', 'Fecha', 'Proveedor', 'Comprobante', 'Total', 'Estado', 'Saldo pendiente'];
                    foreach ($reportesModel->comprobantesPeriodo($desde, $hasta, ['proveedor_id' => 0, 'estado' => '']) as $r) {
                        $filas[] = array_combine($columnas, [
                            ucfirst($r['tipo']), date('d/m/Y', strtotime($r['fecha'])), $r['proveedor'], $r['descripcion'],
                            (float)$r['total'], ucfirst($r['estado_pago']), (float)$r['saldo_pendiente'],
                        ]);
                    }
                    break;

                case 'nomina':
                    require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
                    $empleadoModel = new EmpleadoModel();
                    $columnas = ['Empleado', 'Modalidad', 'Pagado en el período', 'Pendiente de liquidar'];
                    foreach ($empleadoModel->findAllActivos() as $emp) {
                        $filas[] = array_combine($columnas, [
                            $emp['nombre'],
                            $emp['modalidad_pago'],
                            $empleadoModel->totalPagadoEnPeriodo((int)$emp['id'], $desde, $hasta),
                            (float)($empleadoModel->getResumenLiquidacion((int)$emp['id'])['total_sugerido'] ?? 0),
                        ]);
                    }
                    break;

                case 'margen_ventas':
                    // Costo real por línea de venta (ver RecetaModel::calcularCostoReal()): usa
                    // el sabor/variante puntual elegido en cada venta, guardado al momento de
                    // venderse — no el promedio de receta ni el costo de hoy aplicado para atrás.
                    $columnas = ['Fecha', 'Venta', 'Producto', 'Sabor(es)', 'Cantidad', 'Precio unit.', 'Total vendido', 'Costo MP', '¿Estimado?', 'Margen', 'Margen %'];
                    foreach ($reportesModel->ventasConCostoReal($desde, $hasta) as $r) {
                        $filas[] = array_combine($columnas, [
                            date('d/m/Y H:i', strtotime($r['fecha'])), $r['venta_id'], $r['producto_nombre'],
                            $r['sabores_elegidos'] ?? '', (float)$r['cantidad'], (float)$r['precio_unitario'],
                            (float)$r['total_vendido'], (float)$r['costo_mp'], $r['costo_estimado'] ? 'Sí' : 'No',
                            (float)$r['margen'], $r['margen_pct'] . '%',
                        ]);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $usuarioId   = (int) Session::user()['id'];
        $descripcion = ucfirst($tipo) . " del $desde al $hasta";
        $id = (new ReporteIaModel())->crear(
            $usuarioId, $titulo, $descripcion, $tipo, null,
            ['desde' => $desde, 'hasta' => $hasta], $columnas, $filas
        );

        return ['ok' => true, 'reporte_id' => $id, 'titulo' => $titulo, 'url' => '/reportes-ia/ver?id=' . $id, 'filas' => count($filas)];
    }

    /** Genera y guarda un reporte "a medida" a partir de un SELECT (mismas reglas que consultar_datos, sin límite de 50 filas) */
    private function toolGenerarReportePersonalizado(array $args): array
    {
        require_once BASE_PATH . '/app/Models/ReporteIaModel.php';

        $titulo      = trim((string)($args['titulo']      ?? ''));
        $descripcion = trim((string)($args['descripcion'] ?? ''));
        $sql         = (string)($args['sql'] ?? '');

        if ($titulo === '' || $sql === '') {
            return ['ok' => false, 'error' => 'Faltan datos: titulo y sql son obligatorios.'];
        }

        try {
            $filas = $this->model->ejecutarConsulta($sql, 1000);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $columnas  = $filas ? array_keys($filas[0]) : [];
        $usuarioId = (int) Session::user()['id'];

        $id = (new ReporteIaModel())->crear(
            $usuarioId, $titulo, $descripcion ?: null, 'personalizado', $sql,
            [], $columnas, $filas
        );

        return ['ok' => true, 'reporte_id' => $id, 'titulo' => $titulo, 'url' => '/reportes-ia/ver?id=' . $id, 'filas' => count($filas)];
    }

    private function toolCrearCliente(array $args): array
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare(
                "INSERT INTO clientes (nombre, documento, tipo_doc, condicion_iva, email, telefono, direccion, activo, eliminado)
                 VALUES (:nombre, :documento, :tipo_doc, :condicion_iva, :email, :telefono, :direccion, 1, 0)"
            );
            $stmt->execute([
                'nombre'        => $args['nombre'],
                'documento'     => $args['documento']     ?? null,
                'tipo_doc'      => $args['tipo_doc']      ?? 'dni',
                'condicion_iva' => $args['condicion_iva'] ?? 'consumidor_final',
                'email'         => $args['email']         ?? null,
                'telefono'      => $args['telefono']      ?? null,
                'direccion'     => $args['direccion']     ?? null,
            ]);
            return ['ok' => true, 'id' => (int) $db->lastInsertId(), 'entidad' => 'cliente', 'nombre' => $args['nombre']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolCrearProveedor(array $args): array
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare(
                "INSERT INTO proveedores (razon_social, cuit, condicion_iva, email, telefono, direccion, banco, cbu, eliminado)
                 VALUES (:razon_social, :cuit, :condicion_iva, :email, :telefono, :direccion, :banco, :cbu, 0)"
            );
            $stmt->execute([
                'razon_social'  => $args['razon_social'],
                'cuit'          => $args['cuit']          ?? null,
                'condicion_iva' => $args['condicion_iva'] ?? 'responsable_inscripto',
                'email'         => $args['email']         ?? null,
                'telefono'      => $args['telefono']      ?? null,
                'direccion'     => $args['direccion']     ?? null,
                'banco'         => $args['banco']         ?? null,
                'cbu'           => $args['cbu']           ?? null,
            ]);
            return ['ok' => true, 'id' => (int) $db->lastInsertId(), 'entidad' => 'proveedor', 'nombre' => $args['razon_social']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolCrearUsuario(array $args): array
    {
        $this->requireRole('admin');
        $db = Database::getInstance();
        try {
            $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND eliminado = 0");
            $check->execute([$args['email'] ?? '']);
            if ($check->fetch()) {
                return ['ok' => false, 'error' => 'Ya existe un usuario con ese email.'];
            }
            $rolStmt = $db->prepare("SELECT id FROM roles WHERE nombre = ?");
            $rolStmt->execute([$args['rol'] ?? '']);
            $rol = $rolStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$rol) {
                return ['ok' => false, 'error' => "Rol '{$args['rol']}' no encontrado. Válidos: admin, cajero, deposito, vendedor."];
            }
            $hash = password_hash($args['password'] ?? '', PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare(
                "INSERT INTO usuarios (nombre, email, password, rol_id, activo, eliminado)
                 VALUES (:nombre, :email, :password, :rol_id, 1, 0)"
            );
            $stmt->execute([
                'nombre'   => $args['nombre'],
                'email'    => $args['email'],
                'password' => $hash,
                'rol_id'   => $rol['id'],
            ]);
            return ['ok' => true, 'id' => (int) $db->lastInsertId(), 'entidad' => 'usuario', 'nombre' => $args['nombre']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolRegistrarCompra(array $args): array
    {
        require_once BASE_PATH . '/app/Models/FacturaCompraModel.php';
        $db = Database::getInstance();
        try {
            $provNombre = trim($args['proveedor_nombre'] ?? '');
            $provIdExplicito = (int) ($args['proveedor_id'] ?? 0);

            if ($provIdExplicito) {
                // Proveedor ya resuelto (elegido por el usuario en la tarjeta de confirmación): sin fuzzy match.
                $stmt = $db->prepare("SELECT id, razon_social FROM proveedores WHERE id = ? AND eliminado = 0");
                $stmt->execute([$provIdExplicito]);
                $prov = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } else {
                $prov = $this->buscarProveedorFuzzy($provNombre);
            }
            if (!$prov) {
                return ['ok' => false, 'error' => "Proveedor '{$provNombre}' no encontrado en el sistema. Crealo primero desde la sección Proveedores."];
            }

            $detalleDB    = [];
            $subtotal     = 0.0;
            $totalIva     = 0.0;
            $aliasAGuardar = [];
            // Ítems con tipo_item="activo_fijo" (bien de uso/equipamiento, ej. "Cafetera
            // Faema", NO mercadería/ingrediente) — igual que el tilde "Es un activo fijo" del
            // formulario web: no van al detalle de la factura (no son inventario), pero SÍ
            // suman al total (para que la deuda con el proveedor quede bien si es a crédito).
            // Ver ActivoFijoModel::crearActivosSueltos(), compartido con ComprasController.
            $montoActivosFijos = 0.0;
            $activosFijos      = [];

            foreach ($args['items'] ?? [] as $item) {
                $tipoExplicito  = strtolower($item['tipo_item'] ?? '');
                $itemIdExplicito = (int)($item['item_id'] ?? 0) ?: null;

                if ($tipoExplicito === 'activo_fijo') {
                    $cant   = (float) ($item['cantidad']        ?? 1);
                    $precio = (float) ($item['precio_unitario'] ?? 0);
                    $ivaPct = (float) ($item['iva_porcentaje']  ?? 0);
                    if ($cant <= 0) $cant = 1;
                    $subIvaActivo = round($cant * $precio * (1 + $ivaPct / 100), 2);
                    $activosFijos[]     = ['nombre' => trim((string)($item['nombre'] ?? 'Activo fijo')), 'valor' => $subIvaActivo];
                    $montoActivosFijos += $subIvaActivo;
                    $subtotal += round($cant * $precio, 4);
                    $totalIva += $subIvaActivo - round($cant * $precio, 4);
                    continue;
                }

                if ($itemIdExplicito && $tipoExplicito) {
                    // El AI pasó el ID elegido por el usuario: lo usamos directamente. El nombre
                    // tiene que ser el REAL del ítem del sistema (no el texto del ticket, que es
                    // $item['nombre']) — si no, la comparación de más abajo para decidir si hace
                    // falta guardar un alias siempre daba "son iguales" (se comparaba contra sí
                    // mismo) y nunca se guardaba nada, aunque el nombre del ticket fuera
                    // completamente distinto al del ítem vinculado.
                    $itemFound = [
                        'id'     => $itemIdExplicito,
                        'tipo'   => $tipoExplicito,
                        'nombre' => $this->nombreDeItemExistente($tipoExplicito, $itemIdExplicito) ?? trim($item['nombre'] ?? ''),
                    ];
                } else {
                    $itemFound = $this->buscarItemFuzzy(trim($item['nombre'] ?? ''), $tipoExplicito, (int) $prov['id']);
                }

                if (!$itemFound) {
                    return [
                        'ok'            => false,
                        'item_faltante' => $item['nombre'],
                        'error'         => "Ítem '{$item['nombre']}' no encontrado. Usá crear_item para darlo de alta y luego reintentá registrar_compra.",
                    ];
                }

                $cant   = (float) ($item['cantidad']        ?? 0);
                $precio = (float) ($item['precio_unitario'] ?? 0);
                $ivaPct = (float) ($item['iva_porcentaje']  ?? 0);

                // Misma validación que el formulario web (ComprasController::guardar) — sin esto,
                // una cantidad mal leída por Gemini en una foto borrosa (0 o negativa) se guardaría
                // sin avisar y podría descontar stock por error en vez de sumarlo.
                if ($cant <= 0) {
                    return [
                        'ok'    => false,
                        'error' => "Cantidad inválida para '{$item['nombre']}': {$cant}. Debe ser mayor a 0.",
                    ];
                }

                $sub    = round($cant * $precio, 4);
                $subIva = round($sub * (1 + $ivaPct / 100), 4);
                $subtotal += $sub;
                $totalIva += $subIva - $sub;

                $detalleDB[] = [
                    'tipo_item'          => $itemFound['tipo'],
                    'materia_prima_id'   => $itemFound['tipo'] === 'materia_prima' ? $itemFound['id'] : null,
                    'insumo_id'          => $itemFound['tipo'] === 'insumo'        ? $itemFound['id'] : null,
                    'producto_id'        => $itemFound['tipo'] === 'producto'      ? $itemFound['id'] : null,
                    'cantidad'           => $cant,
                    'cantidad_contenida' => isset($item['cantidad_contenida']) ? (float) $item['cantidad_contenida'] : null,
                    'precio_unitario'    => $precio,
                    'subtotal'           => $sub,
                    'iva_porcentaje'     => $ivaPct,
                    'subtotal_con_iva'   => $subIva,
                ];

                // Guardar alias si el nombre del ticket difiere del nombre en sistema
                $nombreTicket = trim($item['nombre'] ?? '');
                if ($nombreTicket !== '' && $this->normalizarTexto($nombreTicket) !== $this->normalizarTexto($itemFound['nombre'])) {
                    $aliasAGuardar[] = ['nombre_ticket' => $nombreTicket, 'tipo' => $itemFound['tipo'], 'id' => $itemFound['id']];
                }
            }

            // Ajuste de redondeo/descuento (opcional): diferencia entre lo que suman los ítems
            // y el total real impreso en el comprobante (a veces el comprobante mismo redondea
            // mal). Va en su propia columna de la cabecera —no se mezcla con ningún ítem— para
            // que quede visible aparte en el detalle de la factura (compras/ver.php).
            $ajuste      = round((float) ($args['ajuste'] ?? 0), 2);
            $totalConIva = round($subtotal + $totalIva + $ajuste, 2);
            $fecha          = $args['fecha'] ?? date('Y-m-d');
            $nroComprobante = $args['nro_comprobante'] ?? str_replace('-', '', $fecha);
            $cabecera = [
                'proveedor_id'     => $prov['id'],
                'usuario_id'       => (int) Session::user()['id'],
                'fecha'            => $fecha,
                'tipo_comprobante' => $args['tipo_comprobante'] ?? 'factura_b',
                'nro_comprobante'  => $nroComprobante,
                'condicion_pago'   => $args['condicion_pago']   ?? 'contado',
                'subtotal'         => round($subtotal, 2),
                'total_iva'        => round($totalIva, 2),
                'total_con_iva'    => $totalConIva,
                'ajuste'           => $ajuste,
                'monto_activos_fijos' => round($montoActivosFijos, 2),
                'estado'           => 'pendiente',
                'notas'            => null,
                'eliminado'        => 0,
            ];

            $facturaModel = new FacturaCompraModel();

            // Aviso no bloqueante: si algún ítem trae un cantidad_contenida muy distinto al
            // que se usó históricamente para el mismo ítem, se registra igual pero se avisa
            // (ver FacturaCompraModel::advertenciasCantidadContenida) — mismo chequeo que ya
            // corre el formulario web de Compras.
            $advertencias = $facturaModel->advertenciasCantidadContenida($detalleDB);

            $id = $facturaModel->guardar($cabecera, $detalleDB);

            // Persistir aliases para compras futuras del mismo proveedor
            foreach ($aliasAGuardar as $a) {
                $this->guardarAlias((int)$prov['id'], $a['nombre_ticket'], $a['tipo'], $a['id']);
            }

            // Ítems activo_fijo (ver arriba): se crean DESPUÉS de guardar la factura, ya con
            // su id — mismo camino que usa el tilde "Es un activo fijo" del formulario web
            // (ActivoFijoModel::crearActivosSueltos()), así quedan con el link real a esta
            // factura y sin duplicarse en "Compras" del Resumen Económico.
            if ($activosFijos) {
                require_once BASE_PATH . '/app/Models/ActivoFijoModel.php';
                $advertencias = array_merge($advertencias, (new ActivoFijoModel())->crearActivosSueltos(
                    $activosFijos, (int)$prov['id'], $fecha, $id, $nroComprobante
                ));
            }

            return [
                'ok' => true, 'id' => $id, 'entidad' => 'compra', 'proveedor' => $prov['razon_social'],
                'total' => $totalConIva, 'items' => count($detalleDB),
                'activos_fijos' => count($activosFijos), 'advertencias' => $advertencias,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolCrearItem(array $args): array
    {
        $nombre   = trim($args['nombre']    ?? '');
        $tipo     = strtolower(trim($args['tipo_item'] ?? ''));
        $cantCont = isset($args['cantidad_contenida']) ? (float) $args['cantidad_contenida'] : null;
        $catNombre = trim($args['categoria_nombre'] ?? '');
        // Solo aplica a materia_prima — se ignora para insumo/producto aunque venga cargado.
        $genericoId = isset($args['ingrediente_generico_id']) && $args['ingrediente_generico_id'] !== ''
            ? (int) $args['ingrediente_generico_id']
            : null;

        // Unidad de medida: la tarjeta de confirmación manda el id directo (unidad_medida_id);
        // si Gemini la llama fuera de ese flujo puede mandar el nombre/abreviatura como texto.
        $unidadMedidaId = isset($args['unidad_medida_id']) ? (int) $args['unidad_medida_id'] : null;
        if (!$unidadMedidaId && !empty($args['unidad_medida'])) {
            $unidadTexto = trim((string) $args['unidad_medida']);
            $db0 = Database::getInstance();
            $u = $db0->prepare(
                "SELECT id FROM unidades_medida WHERE LOWER(nombre) = LOWER(?) OR LOWER(abreviatura) = LOWER(?) LIMIT 1"
            );
            $u->execute([$unidadTexto, $unidadTexto]);
            $filaUnidad = $u->fetch(\PDO::FETCH_ASSOC);
            $unidadMedidaId = $filaUnidad ? (int) $filaUnidad['id'] : null;
        }

        if ($nombre === '') {
            return ['ok' => false, 'error' => 'El nombre del ítem es requerido.'];
        }

        $tiposValidos = ['materia_prima', 'insumo', 'producto'];
        if (!in_array($tipo, $tiposValidos, true)) {
            return ['ok' => false, 'error' => "tipo_item inválido '$tipo'. Usá: materia_prima, insumo o producto."];
        }

        $db = Database::getInstance();

        // Verificar si ya existe con ese nombre (evitar duplicados)
        $tabla = match ($tipo) {
            'materia_prima' => 'materias_primas',
            'insumo'        => 'insumos',
            default         => 'productos',
        };
        $dup = $db->prepare("SELECT id, nombre FROM `{$tabla}` WHERE eliminado=0 AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
        $dup->execute([$nombre]);
        if ($existe = $dup->fetch(\PDO::FETCH_ASSOC)) {
            return array_merge([
                'ok'        => true,
                'creado'    => false,
                'id'        => (int) $existe['id'],
                'nombre'    => $existe['nombre'],
                'tipo_item' => $tipo,
                'mensaje'   => "El ítem '{$existe['nombre']}' ya existe con ID {$existe['id']} (no se creó de nuevo).",
            ], $this->infoGenericoDeItem($tipo, (int) $existe['id']));
        }

        // Buscar categoría por nombre si se proporcionó
        $categoriaId = null;
        if ($catNombre !== '') {
            $catStmt = $db->prepare("SELECT id FROM categorias WHERE eliminado=0 AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
            $catStmt->execute([$catNombre]);
            $cat = $catStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$cat) {
                // Búsqueda fuzzy de categoría
                $cats = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
                $mejorPct = 0.0; $mejorCat = null;
                foreach ($cats as $c) {
                    similar_text($this->normalizarTexto($catNombre), $this->normalizarTexto($c['nombre']), $pct);
                    if ($pct > $mejorPct) { $mejorPct = $pct; $mejorCat = $c; }
                }
                if ($mejorPct >= 60.0) $cat = $mejorCat;
            }
            if ($cat) $categoriaId = (int) $cat['id'];
        }

        try {
            if ($tipo === 'materia_prima') {
                $stmt = $db->prepare(
                    "INSERT INTO materias_primas (nombre, categoria_id, unidad_medida_id, cantidad_contenida, ingrediente_generico_id, stock_actual, stock_minimo, costo_unitario, activo, eliminado)
                     VALUES (:nombre, :categoria_id, :unidad_medida_id, :cantidad_contenida, :generico, 0, 0, 0, 1, 0)"
                );
                $stmt->execute([
                    'nombre'             => $nombre,
                    'categoria_id'       => $categoriaId,
                    'unidad_medida_id'   => $unidadMedidaId,
                    'cantidad_contenida' => $cantCont,
                    'generico'           => $genericoId,
                ]);
            } elseif ($tipo === 'insumo') {
                $stmt = $db->prepare(
                    "INSERT INTO insumos (nombre, categoria_id, unidad_medida_id, cantidad_contenida, stock_actual, stock_minimo, costo_unitario, activo, eliminado)
                     VALUES (:nombre, :categoria_id, :unidad_medida_id, :cantidad_contenida, 0, 0, 0, 1, 0)"
                );
                $stmt->execute([
                    'nombre'             => $nombre,
                    'categoria_id'       => $categoriaId,
                    'unidad_medida_id'   => $unidadMedidaId,
                    'cantidad_contenida' => $cantCont,
                ]);
            } else {
                $precioVenta = isset($args['precio_venta']) ? (float) $args['precio_venta'] : 0.0;
                $stmt = $db->prepare(
                    "INSERT INTO productos (nombre, categoria_id, unidad_medida_id, tipo, precio_venta, precio_costo, iva, stock_actual, stock_minimo, activo, eliminado)
                     VALUES (:nombre, :categoria_id, :unidad_medida_id, 'reventa', :precio_venta, 0, 21, 0, 0, 1, 0)"
                );
                $stmt->execute([
                    'nombre'           => $nombre,
                    'categoria_id'     => $categoriaId,
                    'unidad_medida_id' => $unidadMedidaId,
                    'precio_venta'     => $precioVenta,
                ]);
            }

            $id = (int) $db->lastInsertId();

            // Un producto nuevo nace con ecommerce = 0 (no se muestra en el menú online),
            // pero igual se avisa: MenuApp relee el catálogo y así no queda desfasado si el
            // usuario lo marca enseguida desde la ficha.
            if ($tipo === 'producto') {
                require_once BASE_PATH . '/core/MenuAppNotifier.php';
                MenuAppNotifier::catalogoCambio('producto', $id);
            }

            return [
                'ok'        => true,
                'creado'    => true,
                'id'        => $id,
                'nombre'    => $nombre,
                'tipo_item' => $tipo,
                'categoria_id' => $categoriaId,
                'tiene_generico' => $tipo === 'materia_prima' && $genericoId !== null,
                'mensaje'   => "✅ Ítem '{$nombre}' dado de alta como {$tipo} con ID {$id}. El usuario puede completar precio, categoría y cantidad contenida desde el panel.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Alta completa de un producto desde el Chat IA. A diferencia de crear_item (y del
     * resto de las tools de alta de este archivo, que no chequean módulo), acá SÍ se exige
     * el mismo permiso que la pantalla /productos (canEdit('productos'), ver core/helpers.php)
     * porque el usuario pidió explícitamente que crear/editar producto por chat respete los
     * permisos de la matriz de /permisos, no solo requireAuth().
     */
    private function toolCrearProducto(array $args): array
    {
        if (!canEdit('productos')) {
            return ['ok' => false, 'error' => 'No tenés permiso para crear productos. Pedile a un administrador que te habilite el permiso de edición en Productos (sección Permisos).'];
        }

        $nombre = trim((string) ($args['nombre'] ?? ''));
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'El nombre del producto es obligatorio.'];
        }

        $tipo = strtolower(trim((string) ($args['tipo'] ?? 'reventa')));
        if (!in_array($tipo, ['elaborado', 'reventa'], true)) {
            return ['ok' => false, 'error' => "Tipo de producto inválido '{$tipo}'. Usá: elaborado o reventa."];
        }

        $iva = isset($args['iva']) ? (float) $args['iva'] : 21.0;
        if (!in_array($iva, [0, 10.5, 21], true)) {
            return ['ok' => false, 'error' => "Alícuota de IVA inválida '{$iva}'. Usá: 0, 10.5 o 21."];
        }

        $precioVenta = (float) ($args['precio_venta'] ?? 0);
        if ($precioVenta < 0) {
            return ['ok' => false, 'error' => 'El precio de venta no puede ser negativo.'];
        }

        $db = Database::getInstance();

        $dup = $db->prepare("SELECT id FROM productos WHERE eliminado=0 AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
        $dup->execute([$nombre]);
        if ($existe = $dup->fetch(\PDO::FETCH_ASSOC)) {
            return ['ok' => false, 'error' => "Ya existe un producto llamado '{$nombre}' (ID {$existe['id']}). Usá editar_producto si querés modificarlo."];
        }

        $codigo = trim((string) ($args['codigo'] ?? ''));
        if ($codigo !== '') {
            $dupCod = $db->prepare("SELECT id FROM productos WHERE eliminado=0 AND codigo = ? LIMIT 1");
            $dupCod->execute([$codigo]);
            if ($dupCod->fetch()) {
                return ['ok' => false, 'error' => "Ya existe un producto con el código '{$codigo}'."];
            }
        }

        try {
            require_once BASE_PATH . '/app/Models/ProductoModel.php';

            $data = [
                'nombre'           => $nombre,
                'codigo'           => $codigo ?: null,
                'descripcion'      => trim((string) ($args['descripcion'] ?? '')) ?: null,
                'categoria_id'     => $this->resolverCategoriaId((string) ($args['categoria_nombre'] ?? '')),
                'tipo'             => $tipo,
                'precio_venta'     => $precioVenta,
                'precio_costo'     => 0,
                'iva'              => $iva,
                'unidad_medida_id' => $this->resolverUnidadMedidaId((string) ($args['unidad_medida'] ?? '')),
                'stock_minimo'     => (float) ($args['stock_minimo'] ?? 0),
                'stock_actual'     => 0,
                'controla_stock'   => isset($args['controla_stock']) ? (int) (bool) $args['controla_stock'] : 1,
                'desvio_pct'       => 0,
                'ecommerce'        => isset($args['ecommerce']) ? (int) (bool) $args['ecommerce'] : 0,
                'activo'           => isset($args['activo']) ? (int) (bool) $args['activo'] : 1,
                'eliminado'        => 0,
            ];

            // precio_online NULL (y no 0) cuando no se informó: NULL significa "el menú
            // online usa el precio del local" — ver PrecioHelper::precioBaseOnline().
            $precioOnline = isset($args['precio_online']) ? (float) $args['precio_online'] : null;
            if ($precioOnline !== null && $precioOnline < 0) {
                return ['ok' => false, 'error' => 'El precio online no puede ser negativo.'];
            }
            $data['precio_online'] = ($precioOnline !== null && $precioOnline > 0) ? $precioOnline : null;

            $id = (new ProductoModel())->insert($data);

            require_once BASE_PATH . '/core/MenuAppNotifier.php';
            MenuAppNotifier::catalogoCambio('producto', $id);

            return [
                'ok' => true, 'creado' => true, 'id' => $id, 'entidad' => 'producto', 'nombre' => $nombre,
                'tipo' => $tipo, 'precio_venta' => $precioVenta,
                'mensaje' => "✅ Producto '{$nombre}' creado con ID {$id}.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Edición de un producto existente desde el Chat IA. Mismo chequeo de permiso que
     * toolCrearProducto (canEdit('productos')). Solo modifica los campos presentes en
     * $args — el resto del registro queda igual.
     */
    private function toolEditarProducto(array $args): array
    {
        if (!canEdit('productos')) {
            return ['ok' => false, 'error' => 'No tenés permiso para editar productos. Pedile a un administrador que te habilite el permiso de edición en Productos (sección Permisos).'];
        }

        $db = Database::getInstance();

        $productoId = (int) ($args['producto_id'] ?? 0);
        $producto   = null;
        if ($productoId) {
            $stmt = $db->prepare("SELECT * FROM productos WHERE id = ? AND eliminado = 0");
            $stmt->execute([$productoId]);
            $producto = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } elseif (!empty($args['nombre_o_codigo'])) {
            $busqueda = trim((string) $args['nombre_o_codigo']);
            $stmt = $db->prepare(
                "SELECT * FROM productos WHERE eliminado = 0 AND (LOWER(nombre) LIKE ? OR LOWER(codigo) = LOWER(?)) LIMIT 1"
            );
            $stmt->execute(['%' . strtolower($busqueda) . '%', $busqueda]);
            $producto = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        if (!$producto) {
            return ['ok' => false, 'error' => 'Producto no encontrado. Pasá producto_id o nombre_o_codigo.'];
        }
        $productoId = (int) $producto['id'];

        $data = [];

        if (!empty($args['nombre'])) {
            $data['nombre'] = trim((string) $args['nombre']);
        }
        if (array_key_exists('codigo', $args)) {
            $data['codigo'] = trim((string) $args['codigo']) ?: null;
        }
        if (array_key_exists('descripcion', $args)) {
            $data['descripcion'] = trim((string) $args['descripcion']) ?: null;
        }
        if (!empty($args['categoria_nombre'])) {
            $data['categoria_id'] = $this->resolverCategoriaId((string) $args['categoria_nombre']);
        }
        if (!empty($args['tipo'])) {
            $tipo = strtolower(trim((string) $args['tipo']));
            if (!in_array($tipo, ['elaborado', 'reventa'], true)) {
                return ['ok' => false, 'error' => "Tipo de producto inválido '{$tipo}'. Usá: elaborado o reventa."];
            }
            $data['tipo'] = $tipo;
        }
        if (array_key_exists('precio_venta', $args)) {
            $precioVenta = (float) $args['precio_venta'];
            if ($precioVenta < 0) {
                return ['ok' => false, 'error' => 'El precio de venta no puede ser negativo.'];
            }
            $data['precio_venta'] = $precioVenta;
        }
        if (array_key_exists('iva', $args)) {
            $iva = (float) $args['iva'];
            if (!in_array($iva, [0, 10.5, 21], true)) {
                return ['ok' => false, 'error' => "Alícuota de IVA inválida '{$iva}'. Usá: 0, 10.5 o 21."];
            }
            $data['iva'] = $iva;
        }
        if (!empty($args['unidad_medida'])) {
            $data['unidad_medida_id'] = $this->resolverUnidadMedidaId((string) $args['unidad_medida']);
        }
        if (array_key_exists('stock_minimo', $args)) {
            $data['stock_minimo'] = (float) $args['stock_minimo'];
        }
        if (array_key_exists('controla_stock', $args)) {
            $data['controla_stock'] = (int) (bool) $args['controla_stock'];
        }
        if (array_key_exists('precio_online', $args)) {
            $precioOnline = (float) $args['precio_online'];
            if ($precioOnline < 0) {
                return ['ok' => false, 'error' => 'El precio online no puede ser negativo.'];
            }
            // 0 = volver a usar el precio del local (NULL en la base).
            $data['precio_online'] = $precioOnline > 0 ? $precioOnline : null;
        }
        if (array_key_exists('ecommerce', $args)) {
            $data['ecommerce'] = (int) (bool) $args['ecommerce'];
        }
        if (array_key_exists('activo', $args)) {
            $data['activo'] = (int) (bool) $args['activo'];
        }

        if (!$data) {
            return ['ok' => false, 'error' => 'No se indicó ningún campo para modificar.'];
        }

        try {
            require_once BASE_PATH . '/app/Models/ProductoModel.php';
            (new ProductoModel())->update($productoId, $data);

            require_once BASE_PATH . '/core/MenuAppNotifier.php';
            MenuAppNotifier::catalogoCambio('producto', $productoId);

            $nombreFinal = $data['nombre'] ?? $producto['nombre'];
            return [
                'ok' => true, 'id' => $productoId, 'entidad' => 'producto', 'nombre' => $nombreFinal,
                'campos_modificados' => array_keys($data),
                'mensaje' => "✅ Producto '{$nombreFinal}' (ID {$productoId}) actualizado.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Resuelve una categoría por nombre exacto o fuzzy (≥60%) — mismo criterio que crear_item. */
    private function resolverCategoriaId(string $catNombre): ?int
    {
        $catNombre = trim($catNombre);
        if ($catNombre === '') return null;

        $db = Database::getInstance();
        $catStmt = $db->prepare("SELECT id FROM categorias WHERE eliminado=0 AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
        $catStmt->execute([$catNombre]);
        $cat = $catStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$cat) {
            $cats = $db->query("SELECT id, nombre FROM categorias WHERE eliminado=0")->fetchAll(\PDO::FETCH_ASSOC);
            $mejorPct = 0.0; $mejorCat = null;
            foreach ($cats as $c) {
                similar_text($this->normalizarTexto($catNombre), $this->normalizarTexto($c['nombre']), $pct);
                if ($pct > $mejorPct) { $mejorPct = $pct; $mejorCat = $c; }
            }
            if ($mejorPct >= 60.0) $cat = $mejorCat;
        }
        return $cat ? (int) $cat['id'] : null;
    }

    /** Resuelve una unidad de medida por nombre o abreviatura exactos. */
    private function resolverUnidadMedidaId(string $texto): ?int
    {
        $texto = trim($texto);
        if ($texto === '') return null;

        $stmt = Database::getInstance()->prepare(
            "SELECT id FROM unidades_medida WHERE LOWER(nombre) = LOWER(?) OR LOWER(abreviatura) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$texto, $texto]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $fila ? (int) $fila['id'] : null;
    }

    /** Info de ingrediente genérico de un ítem (solo aplica a materia_prima) — para que el chat sepa si hace falta preguntar */
    private function infoGenericoDeItem(string $tipoItem, int $itemId): array
    {
        // Ingredientes genéricos agrupa materia_prima O insumo (ej. envases/potes — ver
        // IngredienteGenericoModel) — producto queda afuera a propósito, no tiene sentido
        // agrupar productos terminados bajo un genérico de costo.
        if (!in_array($tipoItem, ['materia_prima', 'insumo'], true)) return ['tiene_generico' => false];
        $tabla = $tipoItem === 'insumo' ? 'insumos' : 'materias_primas';

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT ig.id, ig.nombre, it.unidad_medida_id
             FROM {$tabla} it
             LEFT JOIN ingredientes_genericos ig ON ig.id = it.ingrediente_generico_id
             WHERE it.id = ?"
        );
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !$row['id']) {
            return ['tiene_generico' => false, 'unidad_medida_id' => $row ? ($row['unidad_medida_id'] !== null ? (int) $row['unidad_medida_id'] : null) : null];
        }

        return [
            'tiene_generico'              => true,
            'ingrediente_generico_id'     => (int) $row['id'],
            'ingrediente_generico_nombre' => $row['nombre'],
        ];
    }

    /**
     * Contenido x unidad comprada ya cargado para un ítem existente — materia_prima o
     * insumo (ambos tienen cantidad_contenida en su ficha; producto no). Se usa para
     * mostrarlo pre-completado y editable en la tarjeta de confirmación de compra
     * (ej. "Salsa de Chocolate" → 500 gr, o "Pote 1/4 IS 100/BULTO" → 100 unid), en vez
     * de mostrarlo vacío como si fuera un ítem nuevo.
     */
    private function infoContenidaItem(?string $tipoItem, ?int $itemId): array
    {
        $tablas = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos'];
        if (!$itemId || !$tipoItem || !isset($tablas[$tipoItem])) {
            return ['cantidad_contenida' => null, 'unidad_medida_id' => null];
        }

        $stmt = Database::getInstance()->prepare(
            "SELECT cantidad_contenida, unidad_medida_id FROM `{$tablas[$tipoItem]}` WHERE id = ?"
        );
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'cantidad_contenida' => ($row && $row['cantidad_contenida'] !== null) ? (float) $row['cantidad_contenida'] : null,
            'unidad_medida_id'   => ($row && $row['unidad_medida_id']   !== null) ? (int) $row['unidad_medida_id']   : null,
        ];
    }

    /**
     * Nombre real en el sistema de un ítem ya vinculado (materia_prima/insumo/producto) — para
     * que la tarjeta de confirmación de compra muestre A QUÉ se vinculó un match automático
     * ("ok"), en vez de un badge genérico que no dice cuál de todos los ítems similares es.
     */
    private function nombreDeItemExistente(?string $tipoItem, ?int $itemId): ?string
    {
        $tablas = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos', 'producto' => 'productos'];
        if (!$itemId || !$tipoItem || !isset($tablas[$tipoItem])) return null;

        $stmt = Database::getInstance()->prepare("SELECT nombre FROM `{$tablas[$tipoItem]}` WHERE id = ?");
        $stmt->execute([$itemId]);
        $nombre = $stmt->fetchColumn();
        return $nombre !== false ? (string) $nombre : null;
    }

    /** Actualiza el contenido x unidad de una materia prima o insumo ya existente, si el usuario lo corrigió en la tarjeta de compra. */
    private function actualizarContenidaItem(string $tipoItem, int $itemId, float $cantidadContenida, ?int $unidadMedidaId): void
    {
        $datos = ['cantidad_contenida' => $cantidadContenida];
        if ($unidadMedidaId !== null) $datos['unidad_medida_id'] = $unidadMedidaId;
        if ($tipoItem === 'insumo') {
            require_once BASE_PATH . '/app/Models/InsumoModel.php';
            (new InsumoModel())->update($itemId, $datos);
        } else {
            require_once BASE_PATH . '/app/Models/MateriaPrimaModel.php';
            (new MateriaPrimaModel())->update($itemId, $datos);
        }
    }

    private function toolBuscarIngredienteGenericoPorNombre(array $args): array
    {
        require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
        $nombre = trim($args['nombre'] ?? '');
        if ($nombre === '') return ['ok' => false, 'error' => 'nombre requerido.'];

        $resultados = (new IngredienteGenericoModel())->buscarPorNombre($nombre);
        return ['ok' => true, 'resultados' => $resultados, 'total' => count($resultados)];
    }

    /**
     * Asigna una materia prima O un insumo (ej. un envase/pote) a un ingrediente genérico —
     * ver IngredienteGenericoModel, que agrupa las dos tablas por igual. tipo_item default
     * "materia_prima" por compatibilidad con llamadas viejas del modelo de IA que todavía no
     * conocen "insumo" como opción acá.
     */
    private function toolAsignarIngredienteGenerico(array $args): array
    {
        require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';

        $tipo = strtolower(trim((string) ($args['tipo_item'] ?? 'materia_prima')));
        if (!in_array($tipo, ['materia_prima', 'insumo'], true)) $tipo = 'materia_prima';

        $itemId = (int) ($args['item_id'] ?? $args['materia_prima_id'] ?? 0);
        if (!$itemId) return ['ok' => false, 'error' => 'item_id requerido (o materia_prima_id, para compatibilidad).'];

        if ($tipo === 'insumo') {
            require_once BASE_PATH . '/app/Models/InsumoModel.php';
            $itemModel = new InsumoModel();
            $etiquetaTipo = 'insumo';
        } else {
            require_once BASE_PATH . '/app/Models/MateriaPrimaModel.php';
            $itemModel = new MateriaPrimaModel();
            $etiquetaTipo = 'materia prima';
        }

        $item = $itemModel->findById($itemId);
        if (!$item) return ['ok' => false, 'error' => ucfirst($etiquetaTipo) . ' no encontrada.'];
        if (empty($item['unidad_medida_id'])) {
            return ['ok' => false, 'error' => "Este {$etiquetaTipo} no tiene unidad de medida definida; no se puede asignar a un genérico hasta que se le cargue una unidad (kg, gr, etc.) desde el panel."];
        }

        $model = new IngredienteGenericoModel();

        $genericoId = (int) ($args['ingrediente_generico_id'] ?? 0);
        if (!$genericoId && !empty($args['nombre_nuevo_generico'])) {
            $genericoId = $model->crear([
                'nombre'           => trim($args['nombre_nuevo_generico']),
                'unidad_medida_id' => (int) $item['unidad_medida_id'],
                'categoria_id'     => $item['categoria_id'] ?: null,
            ]);
        }
        if (!$genericoId) return ['ok' => false, 'error' => 'Indicá ingrediente_generico_id (uno existente) o nombre_nuevo_generico (para crear uno).'];

        if (!$model->unidadCompatible($genericoId, (int) $item['unidad_medida_id'])) {
            return ['ok' => false, 'error' => "La unidad de medida de ese {$etiquetaTipo} no coincide con la del ingrediente genérico. Deben ser la misma para que los costos sean comparables."];
        }

        $model->asignarVariante($genericoId, $tipo, $itemId);

        return [
            'ok'                      => true,
            'tipo_item'               => $tipo,
            'item_id'                 => $itemId,
            'ingrediente_generico_id' => $genericoId,
            'mensaje'                 => "✅ '{$item['nombre']}' quedó asignado al ingrediente genérico. De ahora en más, las recetas que usen ese genérico van a reflejar el costo de esta compra.",
        ];
    }

    private function toolAjustarStock(array $args): array
    {
        require_once BASE_PATH . '/app/Models/StockModel.php';
        $db = Database::getInstance();
        try {
            $busqueda      = strtolower(trim($args['nombre_o_codigo'] ?? ''));
            $tipoExplicito = strtolower($args['tipo_item'] ?? '');
            $itemFound     = null;

            $tablas = ['materias_primas' => 'materia_prima', 'insumos' => 'insumo', 'productos' => 'producto'];
            if ($tipoExplicito && in_array($tipoExplicito, ['materia_prima', 'insumo', 'producto'], true)) {
                $tabla  = match ($tipoExplicito) { 'materia_prima' => 'materias_primas', 'insumo' => 'insumos', default => 'productos' };
                $tablas = [$tabla => $tipoExplicito];
            }
            foreach ($tablas as $tabla => $tipo) {
                $r = $db->prepare("SELECT id, nombre FROM `$tabla` WHERE eliminado=0 AND (LOWER(nombre) LIKE ? OR LOWER(codigo) = ?) LIMIT 1");
                $r->execute(['%' . $busqueda . '%', $busqueda]);
                $found = $r->fetch(\PDO::FETCH_ASSOC);
                if ($found) { $itemFound = ['id' => $found['id'], 'tipo' => $tipo, 'nombre' => $found['nombre']]; break; }
            }
            if (!$itemFound) {
                return ['ok' => false, 'error' => "Ítem '{$args['nombre_o_codigo']}' no encontrado."];
            }

            (new StockModel())->ajustar([
                'tipo_item'  => $itemFound['tipo'],
                'item_id'    => $itemFound['id'],
                'cantidad'   => (float) ($args['cantidad'] ?? 0),
                'motivo'     => $args['motivo'] ?? 'Ajuste vía Chat IA',
                'usuario_id' => (int) Session::user()['id'],
                'origen'     => 'chat_ia',
            ]);

            return ['ok' => true, 'entidad' => 'stock', 'item' => $itemFound['nombre'], 'cantidad' => $args['cantidad'], 'motivo' => $args['motivo'] ?? 'Ajuste vía Chat IA'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Registra la producción de un producto elaborado a partir de su receta: descuenta los
     * ingredientes (materias primas/insumos, contemplando merma y sub-recetas) y suma el
     * stock del producto terminado. Reutiliza RecetaModel::producir(), la misma lógica que
     * ya usa el formulario web (RecetasController::producir()) — incluida la verificación
     * de faltantes con rollback si no alcanza el stock de algún ingrediente.
     */
    private function toolRegistrarProduccion(array $args): array
    {
        $nombreProducto = trim((string) ($args['nombre_producto'] ?? ''));
        if ($nombreProducto === '') {
            return ['ok' => false, 'error' => 'Falta el nombre del producto elaborado.'];
        }

        $cantidadUnidades = (float) ($args['cantidad'] ?? 0);
        if ($cantidadUnidades <= 0) {
            return ['ok' => false, 'error' => 'La cantidad producida debe ser mayor a 0.'];
        }

        $receta = $this->buscarRecetaPorProducto($nombreProducto);
        if (!$receta) {
            return [
                'ok'    => false,
                'error' => "No encontré una receta activa para '{$nombreProducto}'. Verificá el nombre, o si el "
                    . "producto todavía no tiene una receta cargada, hay que crearla primero desde Recetas.",
            ];
        }

        // rendimiento = cuántas unidades del producto terminado da UNA "producción"/lote de
        // la receta — RecetaModel::producir() descuenta ingredientes por lote, no por unidad,
        // así que la cantidad pedida (en unidades) se convierte a lotes antes de llamarlo.
        $rendimiento      = (float) ($receta['rendimiento'] ?: 1);
        $cantProducciones = $cantidadUnidades / $rendimiento;

        try {
            require_once BASE_PATH . '/app/Models/RecetaModel.php';
            $result = (new RecetaModel())->producir((int) $receta['receta_id'], $cantProducciones, (int) Session::user()['id']);

            if (empty($result['ok'])) {
                $faltantes = implode('; ', $result['faltantes'] ?? []);
                return [
                    'ok'    => false,
                    'error' => "No hay stock suficiente de ingredientes para producir {$cantidadUnidades} de "
                        . "'{$receta['producto_nombre']}'. Falta: {$faltantes}.",
                ];
            }

            return [
                'ok'        => true,
                'entidad'   => 'produccion',
                'producto'  => $receta['producto_nombre'],
                'producido' => $result['producido'],
                'mensaje'   => "✅ Producción registrada: {$result['producido']} de '{$receta['producto_nombre']}'. "
                    . "Se descontaron los ingredientes de la receta y se sumó el stock del producto terminado.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Busca una receta activa por el nombre del producto que genera (exacto o fuzzy ≥60%) — mismo criterio que resolverCategoriaId. */
    private function buscarRecetaPorProducto(string $nombre): ?array
    {
        $nombre = trim($nombre);
        if ($nombre === '') return null;

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT r.id AS receta_id, r.rendimiento, r.producto_id, p.nombre AS producto_nombre
             FROM recetas r
             JOIN productos p ON p.id = r.producto_id
             WHERE r.eliminado = 0 AND r.activo = 1 AND p.eliminado = 0
               AND LOWER(TRIM(p.nombre)) = LOWER(TRIM(?))
             ORDER BY r.updated_at DESC LIMIT 1"
        );
        $stmt->execute([$nombre]);
        $receta = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if (!$receta) {
            $candidatos = $db->query(
                "SELECT r.id AS receta_id, r.rendimiento, r.producto_id, p.nombre AS producto_nombre
                 FROM recetas r
                 JOIN productos p ON p.id = r.producto_id
                 WHERE r.eliminado = 0 AND r.activo = 1 AND p.eliminado = 0"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $mejorPct = 0.0; $mejorReceta = null;
            foreach ($candidatos as $c) {
                similar_text($this->normalizarTexto($nombre), $this->normalizarTexto($c['producto_nombre']), $pct);
                if ($pct > $mejorPct) { $mejorPct = $pct; $mejorReceta = $c; }
            }
            if ($mejorPct >= 60.0) $receta = $mejorReceta;
        }

        return $receta;
    }

    /**
     * Ejecuta el alta real a la Lista de Compras — solo alcanzable vía pedir_confirmacion
     * (accion="agregar_lista_compras"), nunca declarada como tool directa: el ítem ya tiene
     * que venir resuelto (tipo_item + item_id) por buscar_item_por_nombre antes de llegar acá.
     */
    private function toolAgregarListaCompras(array $args): array
    {
        try {
            $tipoItem = (string) ($args['tipo_item'] ?? '');
            $itemId   = (int) ($args['item_id'] ?? 0);
            if (!in_array($tipoItem, ['materia_prima', 'insumo', 'producto'], true) || !$itemId) {
                return ['ok' => false, 'error' => 'Falta resolver el ítem (tipo_item/item_id) antes de agregarlo a la lista de compras.'];
            }

            $usuarioId = (int) Session::user()['id'];
            $res = (new ListaComprasModel())->agregarManual($tipoItem, $itemId, $usuarioId, $args['notas'] ?? null);

            return [
                'ok'      => true,
                'mensaje' => $res['ya_existia']
                    ? 'Ya estaba en la lista de compras, lo dejé confirmado.'
                    : 'Agregado a la lista de compras.',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Marca uno o más productos para que el recordatorio diario de "productos de reventa
     * incompletos" (ver buildSystemPrompt()/ProductoModel::findIncompletos()) deje de
     * mencionarlos — solo alcanzable vía pedir_confirmacion (accion=
     * "ignorar_producto_recordatorio"), nunca declarada como tool directa. `producto_id`
     * viene tal cual lo pasó Gemini: un id solo ("14") o varios separados por coma
     * ("14,22,31") si el usuario pidió ignorar más de uno de una vez.
     */
    private function toolIgnorarProductoRecordatorio(array $args): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string) ($args['producto_id'] ?? ''))));
        if (!$ids) {
            return ['ok' => false, 'error' => 'Falta indicar qué producto(s) dejar de recordar.'];
        }

        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        $actualizados = (new ProductoModel())->ignorarRecordatorio($ids);

        return [
            'ok'      => true,
            'mensaje' => $actualizados === 1
                ? 'Listo, no te voy a volver a recordar ese producto.'
                : "Listo, no te voy a volver a recordar {$actualizados} producto(s).",
        ];
    }

    /**
     * Confirma en lote los ítems 'sugerido' de un aviso diario de Lista de Compras —
     * único accion de pedir_confirmacion que NO llega desde una respuesta de Gemini: el
     * cron (ver cron/lista_compras_diaria.php) inserta directo el mensaje con este
     * datos_json, y el usuario lo confirma/cancela con los mismos botones genéricos que
     * cualquier otra tarjeta de accion (ver ChatController::confirmar()).
     */
    private function toolConfirmarSugeridosListaCompras(array $args): array
    {
        try {
            $ids = is_array($args['ids'] ?? null) ? $args['ids'] : [];
            if (!$ids) return ['ok' => false, 'error' => 'No se especificaron ítems para confirmar.'];

            $n = (new ListaComprasModel())->confirmarSugeridos($ids);
            return ['ok' => true, 'mensaje' => $n . ' ítem' . ($n === 1 ? '' : 's') . ' confirmado' . ($n === 1 ? '' : 's') . ' en la lista de compras.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toolBuscarItemPorNombre(array $args): array
    {
        $nombre      = trim($args['nombre'] ?? '');
        $tipo        = $args['tipo_item']    ?? '';
        $proveedorId = (int)($args['proveedor_id'] ?? 0) ?: null;

        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Nombre vacío.'];
        }

        // 1. Historial de alias del proveedor (aprendizaje previo)
        if ($proveedorId) {
            $alias = $this->buscarAlias($proveedorId, $nombre);
            if ($alias) {
                return array_merge([
                    'ok'        => true,
                    'encontrado'=> true,
                    'via_alias' => true,
                    'id'        => (int) $alias['item_id'],
                    'nombre'    => $alias['nombre_sistema'],
                    'tipo_item' => $alias['tipo_item'],
                    'mensaje'   => "Encontrado en historial del proveedor: '{$nombre}' → '{$alias['nombre_sistema']}'",
                ], $this->infoGenericoDeItem($alias['tipo_item'], (int) $alias['item_id']));
            }
        }

        // 2. Matching fuzzy de alta confianza (≥70%) — primero acotado a lo que este
        //    proveedor ya vendió antes (más preciso y más rápido), y si no aparece nada
        //    ahí, recién contra el catálogo completo.
        $item = $this->buscarItemFuzzy($nombre, $tipo, $proveedorId);
        if ($item) {
            return array_merge([
                'ok'        => true,
                'encontrado'=> true,
                'id'        => $item['id'],
                'nombre'    => $item['nombre'],
                'tipo_item' => $item['tipo'],
            ], $this->infoGenericoDeItem($item['tipo'], (int) $item['id']));
        }

        // 3. Candidatos de baja confianza para que el usuario elija
        $candidatos = $this->buscarItemCandidatos($nombre, $tipo, 3, $proveedorId);
        return [
            'ok'         => false,
            'encontrado' => false,
            'opciones'   => $candidatos,
            'mensaje'    => count($candidatos) > 0
                ? "'{$nombre}' no se encontró con certeza. Mostrá las " . count($candidatos) . " opciones similares al usuario para que elija."
                : "'{$nombre}' no existe en el sistema. Al confirmar, crealo como materia_prima con crear_item.",
        ];
    }

    private function buscarAlias(int $proveedorId, string $nombreTicket): ?array
    {
        try {
            $db   = Database::getInstance();
            $key  = mb_strtolower(trim($nombreTicket), 'UTF-8');
            $stmt = $db->prepare(
                "SELECT pa.tipo_item, pa.item_id,
                        COALESCE(mp.nombre, ins.nombre, prod.nombre) AS nombre_sistema
                 FROM proveedor_item_alias pa
                 LEFT JOIN materias_primas mp  ON mp.id  = pa.item_id AND pa.tipo_item = 'materia_prima'
                 LEFT JOIN insumos ins          ON ins.id = pa.item_id AND pa.tipo_item = 'insumo'
                 LEFT JOIN productos prod       ON prod.id = pa.item_id AND pa.tipo_item = 'producto'
                 WHERE pa.proveedor_id = ? AND pa.nombre_ticket = ?"
            );
            $stmt->execute([$proveedorId, $key]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function guardarAlias(int $proveedorId, string $nombreTicket, string $tipoItem, int $itemId): void
    {
        try {
            $key = mb_strtolower(trim($nombreTicket), 'UTF-8');
            Database::getInstance()->prepare(
                "INSERT INTO proveedor_item_alias (proveedor_id, nombre_ticket, tipo_item, item_id)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE tipo_item = VALUES(tipo_item), item_id = VALUES(item_id), updated_at = NOW()"
            )->execute([$proveedorId, $key, $tipoItem, $itemId]);
        } catch (\Throwable) {
            // No-critical: no interrumpir la compra si falla el alias
        }
    }

    /**
     * Tokeniza un término de búsqueda normalizado para FULLTEXT y word-scoring.
     * Separa tokens combinados (dígitos+letras): "370cc" → ["370", "cc"],
     * filtra tokens de menos de 2 chars.
     */
    private function tokenizarBusqueda(string $normalizada): array
    {
        // Separar dígito→letra y letra→dígito: "370cc" → "370 cc", "ml500" → "ml 500"
        $expandida = preg_replace('/(\d)([a-z])/u', '$1 $2', $normalizada);
        $expandida = preg_replace('/([a-z])(\d)/u', '$1 $2', $expandida);
        return array_values(array_filter(
            preg_split('/\W+/u', $expandida, -1, PREG_SPLIT_NO_EMPTY),
            static fn($w) => strlen($w) >= 2
        ));
    }

    /**
     * Busca en una tabla usando FULLTEXT BOOLEAN MODE con prefix wildcard por token.
     * Retorna hasta $limit filas ordenadas por relevancia.
     * Si el índice no existe (migración no ejecutada), retorna [] sin errores.
     */
    private function buscarPorFulltext(\PDO $db, string $tabla, array $tokens, int $limit = 25): array
    {
        $tokensSignificativos = array_filter($tokens, static fn($t) => strlen($t) >= 3);
        if (empty($tokensSignificativos)) return [];

        // Sin "+" → al menos uno debe coincidir; MySQL rankea según cuántos coinciden
        $query = implode(' ', array_map(static fn($t) => $t . '*', $tokensSignificativos));

        try {
            $stmt = $db->prepare(
                "SELECT id, nombre,
                        MATCH(nombre) AGAINST(? IN BOOLEAN MODE) AS ft_score
                 FROM `{$tabla}` WHERE eliminado = 0
                   AND MATCH(nombre) AGAINST(? IN BOOLEAN MODE) > 0
                 ORDER BY ft_score DESC
                 LIMIT {$limit}"
            );
            $stmt->execute([$query, $query]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return []; // índice no existe aún → el caller hace fallback a scan completo
        }
    }

    /**
     * Scoring bidireccional de palabras con substring matching.
     * Maneja casos como:
     *  "VASO MILANO 370CC" ↔ "Vaso Milano 370 cc (Bulto 800u)" — "370cc" ⊃ "370"
     *  "TAPA SORBETE"      ↔ "Tapa con sorbete Vaso Milano"    — todas las palabras del ticket están en la BD
     *  "AZUCAR COMUN TIPO A" ↔ "Azúcar"                        — todas las palabras de la BD están en el ticket
     */
    private function calcWordScore(array $palabrasBusqueda, array $palabrasCandidate): float
    {
        if (!$palabrasBusqueda || !$palabrasCandidate) return 0.0;

        // Dirección A: cuántas palabras de la BD aparecen en el ticket (exacto o substring)
        $dbEnTicket = 0;
        foreach ($palabrasCandidate as $dbWord) {
            foreach ($palabrasBusqueda as $tWord) {
                if ($tWord === $dbWord || str_contains($tWord, $dbWord) || str_contains($dbWord, $tWord)) {
                    $dbEnTicket++;
                    break;
                }
            }
        }

        // Dirección B: cuántas palabras del ticket aparecen en la BD (exacto o substring)
        $ticketEnDb = 0;
        foreach ($palabrasBusqueda as $tWord) {
            foreach ($palabrasCandidate as $dbWord) {
                if ($tWord === $dbWord || str_contains($tWord, $dbWord) || str_contains($dbWord, $tWord)) {
                    $ticketEnDb++;
                    break;
                }
            }
        }

        $ratioA = $dbEnTicket    / count($palabrasCandidate);
        $ratioB = $ticketEnDb    / count($palabrasBusqueda);
        return max($ratioA, $ratioB);
    }

    /**
     * Ítems que un proveedor ya vendió antes (según facturas de compra registradas), agrupados
     * por tabla — para acotar la búsqueda a lo conocido antes de ir al catálogo completo. En un
     * comercio chico con proveedores fijos, la gran mayoría de los ítems resuelven acá: más
     * preciso (menos ambigüedad entre proveedores distintos) y más rápido (menos filas a puntuar).
     */
    private function obtenerItemsHistoricosProveedor(int $proveedorId, string $tipoExplicito = ''): array
    {
        $tablas = ['materias_primas' => 'materia_prima', 'insumos' => 'insumo', 'productos' => 'producto'];
        if ($tipoExplicito) {
            $mapa = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos', 'producto' => 'productos'];
            if (isset($mapa[$tipoExplicito])) $tablas = [$mapa[$tipoExplicito] => $tipoExplicito];
        }

        $porTabla = [];
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT DISTINCT fcd.tipo_item,
                        COALESCE(fcd.materia_prima_id, fcd.insumo_id, fcd.producto_id) AS item_id
                 FROM facturas_compra_detalle fcd
                 JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
                 WHERE fc.proveedor_id = ? AND fc.eliminado = 0"
            );
            $stmt->execute([$proveedorId]);

            $ids = ['materia_prima' => [], 'insumo' => [], 'producto' => []];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                if (isset($ids[$r['tipo_item']])) $ids[$r['tipo_item']][] = (int) $r['item_id'];
            }

            foreach ($tablas as $tabla => $tipo) {
                if (empty($ids[$tipo])) continue;
                $in = implode(',', array_fill(0, count($ids[$tipo]), '?'));
                $rowsStmt = $db->prepare("SELECT id, nombre FROM `{$tabla}` WHERE eliminado = 0 AND id IN ($in)");
                $rowsStmt->execute($ids[$tipo]);
                $porTabla[$tabla] = ['tipo' => $tipo, 'rows' => $rowsStmt->fetchAll(\PDO::FETCH_ASSOC)];
            }
        } catch (\Throwable) {
            return []; // no crítico: sin pre-filtro, sigue directo al catálogo completo
        }

        return $porTabla;
    }

    /**
     * Puntúa un conjunto de filas {id, nombre} ya traídas (sin volver a golpear la base) contra
     * la búsqueda, con el mismo scoring bidireccional que usa el resto del matching de ítems.
     */
    private function puntuarCandidatos(string $busqueda, array $palabrasBusqueda, array $tablasConRows, float $umbralMinimo = 30.0): array
    {
        $candidatos = [];
        foreach ($tablasConRows as $info) {
            $tipo = $info['tipo'];
            foreach ($info['rows'] as $row) {
                $candidato = $this->normalizarTexto($row['nombre']);
                similar_text($busqueda, $candidato, $pct);

                $palabrasCandidate = array_values(array_filter(
                    preg_split('/\W+/u', $candidato, -1, PREG_SPLIT_NO_EMPTY),
                    static fn($w) => strlen($w) >= 3
                ));
                $wordScore = $this->calcWordScore($palabrasBusqueda, $palabrasCandidate);
                if ($wordScore >= 1.0)      $pct = max($pct, 85.0);
                elseif ($wordScore >= 0.7)  $pct = max($pct, 72.0);
                elseif ($wordScore >= 0.5)  $pct = max($pct, 65.0);
                elseif ($wordScore >= 0.3)  $pct = max($pct, 38.0);

                if ($pct >= $umbralMinimo) {
                    $candidatos[] = ['id' => (int) $row['id'], 'nombre' => $row['nombre'], 'tipo_item' => $tipo, 'score' => round($pct, 1)];
                }
            }
        }
        usort($candidatos, static fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidatos;
    }

    private function buscarItemCandidatos(string $nombre, string $tipoExplicito = '', int $max = 3, ?int $proveedorId = null): array
    {
        if (trim($nombre) === '') return [];

        $busqueda = $this->normalizarTexto($nombre);
        $tokens   = $this->tokenizarBusqueda($busqueda);
        $palabrasBusqueda = array_values(array_filter($tokens, static fn($w) => strlen($w) >= 3));

        // Primero acotado al historial del proveedor (si hay). Si ya alcanza para completar
        // $max, ni siquiera hace falta tocar el catálogo completo.
        $candidatos = [];
        if ($proveedorId) {
            $historicos = $this->obtenerItemsHistoricosProveedor($proveedorId, $tipoExplicito);
            if ($historicos) {
                $candidatos = $this->puntuarCandidatos($busqueda, $palabrasBusqueda, $historicos);
            }
        }
        if (count($candidatos) >= $max) {
            return array_slice($candidatos, 0, $max);
        }

        $db     = Database::getInstance();
        $tablas = ['materias_primas' => 'materia_prima', 'insumos' => 'insumo', 'productos' => 'producto'];
        if ($tipoExplicito) {
            $mapa = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos', 'producto' => 'productos'];
            if (isset($mapa[$tipoExplicito])) $tablas = [$mapa[$tipoExplicito] => $tipoExplicito];
        }

        $tablasConRows = [];
        foreach ($tablas as $tabla => $tipo) {
            // FULLTEXT como filtro primario; fallback a scan completo si el índice no existe
            $rows = $this->buscarPorFulltext($db, $tabla, $tokens);
            if (empty($rows)) {
                $rows = $db->query("SELECT id, nombre FROM `{$tabla}` WHERE eliminado = 0")->fetchAll(\PDO::FETCH_ASSOC);
            }
            $tablasConRows[] = ['tipo' => $tipo, 'rows' => $rows];
        }
        $delCatalogo = $this->puntuarCandidatos($busqueda, $palabrasBusqueda, $tablasConRows);

        // Mezclar sin duplicar (un candidato del historial ya evaluado no se repite)
        $yaIncluidos = array_map(static fn($c) => $c['tipo_item'] . ':' . $c['id'], $candidatos);
        foreach ($delCatalogo as $c) {
            $clave = $c['tipo_item'] . ':' . $c['id'];
            if (!in_array($clave, $yaIncluidos, true)) {
                $candidatos[]  = $c;
                $yaIncluidos[] = $clave;
            }
        }

        usort($candidatos, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($candidatos, 0, $max);
    }

    /**
     * Búsqueda fuzzy de ítems: primero acotada al historial del proveedor (si hay), y solo si
     * ahí no aparece nada suficientemente bueno, contra el catálogo completo con FULLTEXT como
     * filtro primario + ranking bidireccional (similar_text + calcWordScore). Umbral: 70%.
     */
    private function buscarItemFuzzy(string $nombre, string $tipoExplicito = '', ?int $proveedorId = null): ?array
    {
        if (trim($nombre) === '') return null;

        $busqueda = $this->normalizarTexto($nombre);
        $tokens   = $this->tokenizarBusqueda($busqueda);
        $palabrasBusqueda = array_values(array_filter($tokens, static fn($w) => strlen($w) >= 3));

        if ($proveedorId) {
            $historicos = $this->obtenerItemsHistoricosProveedor($proveedorId, $tipoExplicito);
            if ($historicos) {
                $mejores = $this->puntuarCandidatos($busqueda, $palabrasBusqueda, $historicos, 70.0);
                if ($mejores) {
                    $m = $mejores[0];
                    return ['id' => $m['id'], 'tipo' => $m['tipo_item'], 'nombre' => $m['nombre']];
                }
            }
        }

        $db     = Database::getInstance();
        $tablas = [
            'materias_primas' => 'materia_prima',
            'insumos'         => 'insumo',
            'productos'       => 'producto',
        ];
        if ($tipoExplicito) {
            $mapa = ['materia_prima' => 'materias_primas', 'insumo' => 'insumos', 'producto' => 'productos'];
            if (isset($mapa[$tipoExplicito])) {
                $tablas = [$mapa[$tipoExplicito] => $tipoExplicito];
            }
        }

        $mejorPct  = 0.0;
        $mejorItem = null;

        foreach ($tablas as $tabla => $tipo) {
            // FULLTEXT como filtro primario; fallback a scan completo si el índice no existe
            $rows = $this->buscarPorFulltext($db, $tabla, $tokens);
            if (empty($rows)) {
                $rows = $db->query("SELECT id, nombre FROM `{$tabla}` WHERE eliminado = 0")->fetchAll(\PDO::FETCH_ASSOC);
            }

            foreach ($rows as $row) {
                $candidato = $this->normalizarTexto($row['nombre']);
                similar_text($busqueda, $candidato, $pct);

                if ($pct < 70.0) {
                    $palabrasCandidate = array_values(array_filter(
                        preg_split('/\W+/u', $candidato, -1, PREG_SPLIT_NO_EMPTY),
                        static fn($w) => strlen($w) >= 3
                    ));
                    $wordScore = $this->calcWordScore($palabrasBusqueda, $palabrasCandidate);
                    if ($wordScore >= 1.0)      $pct = max($pct, 85.0);
                    elseif ($wordScore >= 0.7)  $pct = max($pct, 72.0);
                    elseif ($wordScore >= 0.5)  $pct = max($pct, 65.0);
                }

                if ($pct > $mejorPct) {
                    $mejorPct  = $pct;
                    $mejorItem = ['id' => $row['id'], 'tipo' => $tipo, 'nombre' => $row['nombre']];
                }
            }
        }

        return $mejorPct >= 70.0 ? $mejorItem : null;
    }

    private function toolBuscarProveedorPorNombre(array $args): array
    {
        $nombre = trim($args['nombre'] ?? '');
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Nombre vacío.'];
        }
        $prov = $this->buscarProveedorFuzzy($nombre);
        if (!$prov) {
            return [
                'ok'         => false,
                'encontrado' => false,
                'mensaje'    => "No se encontró ningún proveedor similar a '{$nombre}' en el sistema.",
            ];
        }
        return [
            'ok'           => true,
            'encontrado'   => true,
            'proveedor_id' => (int) $prov['id'],
            'razon_social' => $prov['razon_social'],
        ];
    }

    /**
     * Normaliza texto para comparación: minúsculas + sin acentos + sin puntuación extra.
     */
    private function normalizarTexto(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $desde = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û','ä','ë','ï','ö','ã','õ'];
        $hasta = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u','a','e','i','o','a','o'];
        return str_replace($desde, $hasta, $s);
    }

    /**
     * Busca el proveedor más parecido al nombre dado usando similar_text().
     * Umbral: 70% de similitud sobre texto normalizado (sin acentos, minúsculas).
     * Retorna el proveedor con mayor score si supera el umbral, o null.
     */
    private function buscarProveedorFuzzy(string $nombre): ?array
    {
        if (trim($nombre) === '') return null;

        $db        = Database::getInstance();
        $todos     = $db->query(
            "SELECT id, razon_social FROM proveedores WHERE eliminado = 0"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $busqueda         = $this->normalizarTexto($nombre);
        $palabrasBusqueda = array_values(array_filter(
            preg_split('/\W+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY),
            static fn($w) => strlen($w) >= 3
        ));
        $mejorPct   = 0.0;
        $mejorProv  = null;

        foreach ($todos as $p) {
            $candidato = $this->normalizarTexto($p['razon_social']);
            similar_text($busqueda, $candidato, $pct);

            // similar_text() por sí solo castiga cuando el usuario dice un nombre corto/
            // parcial (ej. "new premium") y el guardado es más largo (ej. "Supermercado
            // New Premium"): el % se calcula sobre la suma de ambas longitudes, así que
            // baja solo por la diferencia de tamaño aunque el match sea perfecto. Se
            // complementa con superposición de palabras completas (mismo criterio que ya
            // usa la búsqueda de ítems en buscarItemFuzzy/puntuarCandidatos).
            $palabrasCandidato = array_values(array_filter(
                preg_split('/\W+/u', $candidato, -1, PREG_SPLIT_NO_EMPTY),
                static fn($w) => strlen($w) >= 3
            ));
            $wordScore = $this->calcWordScore($palabrasBusqueda, $palabrasCandidato);
            if ($wordScore >= 1.0)      $pct = max($pct, 90.0);
            elseif ($wordScore >= 0.7)  $pct = max($pct, 75.0);
            elseif ($wordScore >= 0.5)  $pct = max($pct, 65.0);

            if ($pct > $mejorPct) {
                $mejorPct  = $pct;
                $mejorProv = $p;
            }
        }

        return $mejorPct >= 70.0 ? $mejorProv : null;
    }

    private function toolBuscarEmpleadoPorNombre(array $args): array
    {
        $nombre = trim($args['nombre'] ?? '');
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Nombre vacío.'];
        }
        $emp = $this->buscarEmpleadoFuzzy($nombre);
        if (!$emp) {
            return [
                'ok'         => false,
                'encontrado' => false,
                'mensaje'    => "No se encontró ningún empleado similar a '{$nombre}' en el sistema.",
            ];
        }
        return [
            'ok'             => true,
            'encontrado'     => true,
            'empleado_id'    => (int) $emp['id'],
            'nombre'         => $emp['nombre'],
            'modalidad_pago' => $emp['modalidad_pago'],
        ];
    }

    /** Busca el empleado más parecido al nombre dado (mismo criterio que buscarProveedorFuzzy, umbral más laxo por ser nombres de pila cortos) */
    private function buscarEmpleadoFuzzy(string $nombre): ?array
    {
        if (trim($nombre) === '') return null;

        $db    = Database::getInstance();
        $todos = $db->query(
            "SELECT id, nombre, modalidad_pago FROM empleados WHERE eliminado = 0"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $busqueda         = $this->normalizarTexto($nombre);
        $palabrasBusqueda = array_values(array_filter(
            preg_split('/\W+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY),
            static fn($w) => strlen($w) >= 3
        ));
        $mejorPct  = 0.0;
        $mejorEmp  = null;

        foreach ($todos as $e) {
            $candidato = $this->normalizarTexto($e['nombre']);
            similar_text($busqueda, $candidato, $pct);

            // Mismo problema y misma solución que en buscarProveedorFuzzy(): un nombre de
            // pila suelto (ej. "Juan") contra un nombre completo (ej. "Juan Pérez García")
            // puede dar un % diluido por la diferencia de longitud.
            $palabrasCandidato = array_values(array_filter(
                preg_split('/\W+/u', $candidato, -1, PREG_SPLIT_NO_EMPTY),
                static fn($w) => strlen($w) >= 3
            ));
            $wordScore = $this->calcWordScore($palabrasBusqueda, $palabrasCandidato);
            if ($wordScore >= 1.0)      $pct = max($pct, 90.0);
            elseif ($wordScore >= 0.7)  $pct = max($pct, 75.0);
            elseif ($wordScore >= 0.5)  $pct = max($pct, 65.0);

            if ($pct > $mejorPct) {
                $mejorPct = $pct;
                $mejorEmp = $e;
            }
        }

        return $mejorPct >= 60.0 ? $mejorEmp : null;
    }

    private function toolMarcarFeriadoTrabajado(array $args): array
    {
        require_once BASE_PATH . '/app/Models/EmpleadoModel.php';

        $empleadoId = (int) ($args['empleado_id'] ?? 0);
        $fecha      = $args['fecha'] ?? '';
        $horas      = (float) ($args['horas'] ?? 0);
        $notas      = $args['notas'] ?? null;

        if (!$empleadoId || !$fecha) {
            return ['ok' => false, 'error' => 'empleado_id y fecha son obligatorios.'];
        }

        $r = (new EmpleadoModel())->agregarJornada($empleadoId, $fecha, $horas, true, $notas, (int) Session::user()['id']);
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];

        return ['ok' => true, 'mensaje' => 'Feriado trabajado registrado.', 'id' => $r['id']];
    }

    private function toolRegistrarAusencia(array $args): array
    {
        require_once BASE_PATH . '/app/Models/EmpleadoModel.php';

        $empleadoId = (int) ($args['empleado_id'] ?? 0);
        $fecha      = $args['fecha'] ?? '';
        $tipo       = $args['tipo']  ?? '';
        $horas      = (float) ($args['horas'] ?? 0);
        $notas      = $args['notas'] ?? null;

        if (!$empleadoId || !$fecha || !in_array($tipo, ['descuento', 'recuperar'], true)) {
            return ['ok' => false, 'error' => 'empleado_id, fecha y tipo ("descuento" o "recuperar") son obligatorios.'];
        }

        $r = (new EmpleadoModel())->agregarAusencia($empleadoId, $fecha, $tipo, $horas, $notas, (int) Session::user()['id']);
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];

        return ['ok' => true, 'mensaje' => 'Ausencia registrada.', 'id' => $r['id']];
    }

    private function toolRegistrarHorasRecuperadas(array $args): array
    {
        require_once BASE_PATH . '/app/Models/EmpleadoModel.php';

        $empleadoId = (int) ($args['empleado_id'] ?? 0);
        $horas      = (float) ($args['horas'] ?? 0);
        $notas      = $args['notas'] ?? null;

        if (!$empleadoId || $horas <= 0) {
            return ['ok' => false, 'error' => 'empleado_id y horas son obligatorios.'];
        }

        $r = (new EmpleadoModel())->recuperarHoras($empleadoId, $horas, $notas, (int) Session::user()['id']);
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];

        return [
            'ok'              => true,
            'mensaje'         => 'Horas recuperadas registradas.',
            'horas_aplicadas' => $r['horas_aplicadas'],
            'monto_aplicado'  => $r['monto_aplicado'],
        ];
    }

    private function toolConsultarDeudaEmpleado(array $args): array
    {
        require_once BASE_PATH . '/app/Models/EmpleadoModel.php';

        $empleadoId = (int) ($args['empleado_id'] ?? 0);
        if (!$empleadoId) {
            return ['ok' => false, 'error' => 'empleado_id requerido.'];
        }

        $model = new EmpleadoModel();
        return [
            'ok'          => true,
            'deuda_horas' => $model->getDeudaHoras($empleadoId),
            'deuda_monto' => $model->getDeudaMonto($empleadoId),
        ];
    }

    private function toolBuscarProveedorPorIdentidad(array $args): array
    {
        require_once BASE_PATH . '/app/Models/ProveedorModel.php';
        $valor = trim($args['valor'] ?? '');
        if ($valor === '') {
            return ['ok' => false, 'error' => 'Valor de búsqueda vacío.'];
        }
        $resultado = (new ProveedorModel())->buscarPorIdentidad($valor);
        if (!$resultado) {
            return [
                'ok'          => false,
                'encontrado'  => false,
                'mensaje'     => "No se encontró ningún proveedor con la identidad '$valor'. Podés preguntar al usuario a qué proveedor corresponde.",
            ];
        }
        return [
            'ok'             => true,
            'encontrado'     => true,
            'proveedor_id'   => (int) $resultado['id'],
            'razon_social'   => $resultado['razon_social'],
            'tipo_identidad' => $resultado['tipo'],
            'valor_match'    => $resultado['identidad_valor'],
        ];
    }

    private function toolBuscarFacturasPendientes(array $args): array
    {
        require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
        $proveedorId = (int)($args['proveedor_id'] ?? 0);
        if (!$proveedorId) {
            return ['ok' => false, 'error' => 'proveedor_id requerido.'];
        }
        $model    = new PagoProveedorModel();
        $facturas = $model->getFacturasPendientesByProveedor($proveedorId);
        $gastos   = $model->getGastosPendientesByProveedor($proveedorId);
        return [
            'ok'       => true,
            'facturas' => $facturas,
            'gastos'   => $gastos,
            'total'    => count($facturas) + count($gastos),
        ];
    }

    private function toolRegistrarPago(array $args): array
    {
        require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
        try {
            $proveedorId = (int)($args['proveedor_id'] ?? 0);
            $montoTotal  = (float)($args['monto_total'] ?? 0);
            $metodo      = $args['metodo'] ?? 'mercado_pago';
            $fecha       = $args['fecha']  ?? date('Y-m-d');

            if (!$proveedorId || $montoTotal <= 0) {
                return ['ok' => false, 'error' => 'proveedor_id y monto_total son obligatorios.'];
            }

            $numeroComprobante = trim((string)($args['numero_comprobante'] ?? '')) ?: null;
            if (empty($args['confirmado_duplicado'])) {
                $dup = (new PagoProveedorModel())->buscarDuplicado($proveedorId, null, $montoTotal, $fecha, $numeroComprobante);
                if ($dup) {
                    return $this->respuestaPosibleDuplicado('pago', $dup);
                }
            }

            $metodosValidos = ['mercado_pago','efectivo','debito','transferencia','cheque','otro'];
            if (!in_array($metodo, $metodosValidos, true)) $metodo = 'otro';

            // Origen del pago: "empresa" (default) o "aporte de socio" — mismo campo que ya
            // usan los formularios manuales de Compras y Pagos a Proveedores.
            $origenPago = ($args['origen_pago'] ?? 'empresa') === 'socio' ? 'socio' : 'empresa';
            $socioId    = null;
            $nombreSocio = null;
            if ($origenPago === 'socio' && !empty($args['socio_id'])) {
                require_once BASE_PATH . '/app/Models/SocioModel.php';
                $socio = (new SocioModel())->findById((int) $args['socio_id']);
                if ($socio) {
                    $socioId     = (int) $socio['id'];
                    $nombreSocio = $socio['nombre'];
                }
            }
            if ($origenPago === 'socio' && !$socioId) $origenPago = 'empresa'; // socio_id inválido/no encontrado: no forzar un aporte sin socio real

            $data = [
                'proveedor_id'        => $proveedorId,
                'beneficiario'        => null,
                'tipo'                => 'proveedor',
                'fecha'               => $fecha,
                'concepto'            => $args['concepto'] ?? null,
                'monto_mercado_pago'  => $metodo === 'mercado_pago'  ? $montoTotal : 0,
                'monto_efectivo'      => $metodo === 'efectivo'       ? $montoTotal : 0,
                'monto_debito'        => $metodo === 'debito'         ? $montoTotal : 0,
                'monto_transferencia' => $metodo === 'transferencia'  ? $montoTotal : 0,
                'monto_cheque'        => $metodo === 'cheque'         ? $montoTotal : 0,
                'monto_otro'          => $metodo === 'otro'           ? $montoTotal : 0,
                'monto_total'         => $montoTotal,
                'referencia_mp'       => $args['referencia_mp']       ?? null,
                'numero_comprobante'  => $numeroComprobante,
                'comprobante_path'    => null,
                'origen_pago'         => $origenPago,
                'socio_id'            => $socioId,
                'nombre_socio'        => $nombreSocio,
                'notas'               => 'Registrado desde Chat IA',
                'usuario_id'          => (int) Session::user()['id'],
            ];

            // Soporte para factura_id singular (caso más común: pago cubre una sola factura)
            $aplicaciones = [];
            if (!empty($args['factura_id'])) {
                $aplicaciones[] = [
                    'factura_id'     => (int) $args['factura_id'],
                    'monto_aplicado' => $montoTotal,
                ];
            } else {
                $facturaIds      = $args['factura_ids']      ?? [];
                $montosAplicados = $args['montos_aplicados'] ?? [];
                foreach ($facturaIds as $i => $fid) {
                    $monto = (float)($montosAplicados[$i] ?? $montoTotal);
                    if ($fid && $monto > 0) {
                        $aplicaciones[] = ['factura_id' => (int)$fid, 'monto_aplicado' => $monto];
                    }
                }
            }

            // Soporte para gasto_id singular (alquiler, servicios, honorarios, etc.)
            $aplicacionesGastos = [];
            if (!empty($args['gasto_id'])) {
                $aplicacionesGastos[] = [
                    'gasto_id'       => (int) $args['gasto_id'],
                    'monto_aplicado' => $montoTotal,
                ];
            } else {
                $gastoIds        = $args['gasto_ids']                ?? [];
                $montosAplicados = $args['montos_aplicados_gastos']  ?? [];
                foreach ($gastoIds as $i => $gid) {
                    $monto = (float)($montosAplicados[$i] ?? $montoTotal);
                    if ($gid && $monto > 0) {
                        $aplicacionesGastos[] = ['gasto_id' => (int)$gid, 'monto_aplicado' => $monto];
                    }
                }
            }

            $pagoId = (new PagoProveedorModel())->guardar($data, $aplicaciones, $aplicacionesGastos);
            return [
                'ok'       => true,
                'id'       => $pagoId,
                'entidad'  => 'pago',
                'total'    => $montoTotal,
                'facturas' => count($aplicaciones),
                'gastos'   => count($aplicacionesGastos),
                'mensaje'  => "Pago #$pagoId registrado por $" . number_format($montoTotal, 2, ',', '.') . ".",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Registra el pago del sueldo de un EMPLEADO (tipo='sueldo' + empleado_id, no proveedor_id)
     * y marca como liquidadas sus jornadas/conceptos/ausencias pendientes — mismo camino que
     * sigue PagosProveedoresController::guardar() para el formulario web. Antes de que existiera
     * esta tool, el chat no tenía forma de pagar un sueldo y terminaba usando registrar_pago
     * (pensada solo para proveedores), lo que dejaba el pago sin vincular al empleado: no se
     * marcaba nada como liquidado y el gasto quedaba mal categorizado en el Resumen Económico.
     */
    private function toolRegistrarPagoSueldo(array $args): array
    {
        require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
        require_once BASE_PATH . '/app/Models/EmpleadoModel.php';
        try {
            $empleadoId = (int)($args['empleado_id'] ?? 0);
            $montoTotal = (float)($args['monto_total'] ?? 0);
            $metodo     = $args['metodo'] ?? 'efectivo';
            $fecha      = $args['fecha']  ?? date('Y-m-d');

            if (!$empleadoId || $montoTotal <= 0) {
                return ['ok' => false, 'error' => 'empleado_id y monto_total son obligatorios.'];
            }

            $numeroComprobante = trim((string)($args['numero_comprobante'] ?? '')) ?: null;
            if (empty($args['confirmado_duplicado'])) {
                $dup = (new PagoProveedorModel())->buscarDuplicado(null, $empleadoId, $montoTotal, $fecha, $numeroComprobante);
                if ($dup) {
                    return $this->respuestaPosibleDuplicado('pago', $dup);
                }
            }

            $empleadoModel = new EmpleadoModel();
            $empleado = $empleadoModel->findById($empleadoId);
            if (!$empleado) {
                return ['ok' => false, 'error' => "No se encontró el empleado #{$empleadoId}."];
            }

            $resumen = $empleadoModel->getResumenLiquidacion($empleadoId);
            if (empty($resumen['ok'])) {
                return ['ok' => false, 'error' => 'No se pudo calcular lo pendiente de liquidar de este empleado.'];
            }

            $totalSugerido  = (float)$resumen['total_sugerido'];
            $esPagoParcial  = $totalSugerido > 0.009 && $montoTotal + 1 < $totalSugerido;

            if ($esPagoParcial) {
                // Pago a cuenta: se liquidan las jornadas/conceptos pendientes MÁS ANTIGUOS
                // primero, hasta agotar el monto pagado — nunca se parte un ítem individual a
                // la mitad (si el próximo no entra completo, se corta ahí y queda pendiente
                // para un pago futuro). Las ausencias tipo "descuento" no son un ítem que se
                // "paga": son una deducción ya devengada, así que siempre se aplican completas
                // — por eso se suman de vuelta al presupuesto antes de repartir.
                $totalAusencias = array_sum(array_column($resumen['ausencias'] ?? [], 'monto_descuento'));
                $presupuesto    = $montoTotal + $totalAusencias;

                $baseMensual = (float)$resumen['base_mensual'];
                if ($baseMensual > 0) $presupuesto = max(0.0, $presupuesto - $baseMensual);

                $itemsFillable = array_merge(
                    array_map(fn($j) => ['tipo' => 'jornada', 'id' => (int)$j['id'], 'monto' => (float)$j['monto'], 'fecha' => $j['fecha']], $resumen['jornadas'] ?? []),
                    array_map(fn($c) => ['tipo' => 'concepto', 'id' => (int)$c['id'], 'monto' => (float)$c['monto'], 'fecha' => $c['fecha']], $resumen['conceptos'] ?? [])
                );
                usort($itemsFillable, fn($a, $b) => $a['fecha'] <=> $b['fecha']);

                $jornadaIds  = [];
                $conceptoIds = [];
                foreach ($itemsFillable as $it) {
                    if ($it['monto'] > $presupuesto + 0.01) break; // el siguiente (más viejo pendiente) no entra completo: se corta acá
                    $presupuesto -= $it['monto'];
                    if ($it['tipo'] === 'jornada') $jornadaIds[] = $it['id'];
                    else $conceptoIds[] = $it['id'];
                }
                $ausenciaIds = array_column($resumen['ausencias'] ?? [], 'id');
            } else {
                $jornadaIds  = array_column($resumen['jornadas']  ?? [], 'id');
                $conceptoIds = array_column($resumen['conceptos'] ?? [], 'id');
                $ausenciaIds = array_column($resumen['ausencias'] ?? [], 'id');
            }

            $metodosValidos = ['mercado_pago','efectivo','debito','transferencia','cheque','otro'];
            if (!in_array($metodo, $metodosValidos, true)) $metodo = 'otro';

            $origenPago  = ($args['origen_pago'] ?? 'empresa') === 'socio' ? 'socio' : 'empresa';
            $socioId     = null;
            $nombreSocio = null;
            if ($origenPago === 'socio' && !empty($args['socio_id'])) {
                require_once BASE_PATH . '/app/Models/SocioModel.php';
                $socio = (new SocioModel())->findById((int) $args['socio_id']);
                if ($socio) {
                    $socioId     = (int) $socio['id'];
                    $nombreSocio = $socio['nombre'];
                }
            }
            if ($origenPago === 'socio' && !$socioId) $origenPago = 'empresa';

            $data = [
                'proveedor_id'        => null,
                'empleado_id'         => $empleadoId,
                'beneficiario'        => null,
                'tipo'                => 'sueldo',
                'fecha'               => $fecha,
                'concepto'            => $args['concepto'] ?? ('Sueldo ' . $empleado['nombre']),
                'monto_mercado_pago'  => $metodo === 'mercado_pago'  ? $montoTotal : 0,
                'monto_efectivo'      => $metodo === 'efectivo'      ? $montoTotal : 0,
                'monto_debito'        => $metodo === 'debito'        ? $montoTotal : 0,
                'monto_transferencia' => $metodo === 'transferencia' ? $montoTotal : 0,
                'monto_cheque'        => $metodo === 'cheque'        ? $montoTotal : 0,
                'monto_otro'          => $metodo === 'otro'          ? $montoTotal : 0,
                'monto_total'         => $montoTotal,
                'referencia_mp'       => null,
                'numero_comprobante'  => $numeroComprobante,
                'comprobante_path'    => null,
                'origen_pago'         => $origenPago,
                'socio_id'            => $socioId,
                'nombre_socio'        => $nombreSocio,
                'notas'               => 'Registrado desde Chat IA',
                'usuario_id'          => (int) Session::user()['id'],
            ];

            $pagoId = (new PagoProveedorModel())->guardar($data, [], []);
            $empleadoModel->marcarLiquidado($pagoId, $jornadaIds, $conceptoIds, $ausenciaIds);

            if ($esPagoParcial) {
                $pendiente = round($totalSugerido - $montoTotal, 2);
                $mensaje = "Pago #$pagoId registrado — pago a cuenta del sueldo de {$empleado['nombre']} por $"
                    . number_format($montoTotal, 2, ',', '.') . " (se liquidaron los ítems pendientes más antiguos hasta agotar ese monto). "
                    . "Quedan $" . number_format($pendiente, 2, ',', '.') . " pendientes para un próximo pago.";
            } else {
                $mensaje = "Pago #$pagoId registrado — sueldo de {$empleado['nombre']} liquidado por $" . number_format($montoTotal, 2, ',', '.') . ".";
            }

            return [
                'ok'      => true,
                'id'      => $pagoId,
                'entidad' => 'pago_sueldo',
                'total'   => $montoTotal,
                'mensaje' => $mensaje,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Arma la respuesta que le llega a Gemini cuando un tool de registro encuentra un
     * posible duplicado (buscarDuplicado() de FacturaCompraModel/GastoModel/PagoProveedorModel):
     * no guarda nada, y le da al modelo el link + quién/cuándo lo cargó, para que se lo
     * muestre al usuario y le pregunte si quiere continuar de todos modos (ver sección
     * "DUPLICADOS" del system prompt).
     */
    private function respuestaPosibleDuplicado(string $entidad, array $dup): array
    {
        $link = match ($entidad) {
            'factura' => url('compras/ver?id=' . $dup['id']),
            'pago'    => url('pagos-proveedores/ver?id=' . $dup['id']),
            'gasto'   => url('gastos'),
            default   => url(''),
        };
        $monto  = isset($dup['monto_total']) ? $dup['monto_total'] : ($dup['monto'] ?? null);
        $detalle = $dup['concepto'] ?? $dup['descripcion'] ?? $dup['nro_comprobante'] ?? '';

        return [
            'ok'                => false,
            'posible_duplicado' => true,
            'fecha'             => $dup['fecha'],
            'monto'             => $monto !== null ? (float) $monto : null,
            'detalle'           => $detalle,
            'usuario'           => $dup['usuario_nombre'] ?? '(desconocido)',
            'link'              => $link,
            'mensaje'           => "Ya hay un registro parecido: {$detalle} por $" . number_format((float)$monto, 2, ',', '.')
                . " el {$dup['fecha']}, cargado por {$dup['usuario_nombre']}. Ver: {$link}",
        ];
    }

    /**
     * Crea un gasto ya PAGADO + su pago vinculado en un solo paso (servicio, alquiler,
     * impuesto, honorario) para cuando no había un gasto "pendiente" pre-cargado al que
     * enganchar el pago con registrar_pago+gasto_id — típicamente porque el usuario solo
     * compartió la foto del comprobante sin haber cargado nada antes en el módulo Gastos.
     */
    private function toolRegistrarGastoPagado(array $args): array
    {
        require_once BASE_PATH . '/app/Models/GastoModel.php';
        require_once BASE_PATH . '/app/Models/PagoProveedorModel.php';
        try {
            $categoriaId = (int)($args['categoria_id'] ?? 0);
            $descripcion = trim((string)($args['descripcion'] ?? ''));
            $monto       = (float)($args['monto'] ?? 0);
            $fecha       = $args['fecha'] ?? date('Y-m-d');
            $proveedorId = !empty($args['proveedor_id']) ? (int)$args['proveedor_id'] : null;
            $metodo      = $args['metodo'] ?? 'efectivo';

            if (!$categoriaId || $descripcion === '' || $monto <= 0) {
                return ['ok' => false, 'error' => 'categoria_id, descripcion y monto son obligatorios.'];
            }

            $numeroComprobante = trim((string)($args['numero_comprobante'] ?? '')) ?: null;
            $gastoModel = new GastoModel();
            if (empty($args['confirmado_duplicado'])) {
                $dup = $gastoModel->buscarDuplicado($proveedorId, $monto, $fecha, $numeroComprobante);
                if ($dup) {
                    return $this->respuestaPosibleDuplicado('gasto', $dup);
                }
            }

            $metodosValidos = ['mercado_pago','efectivo','debito','transferencia','cheque','otro'];
            if (!in_array($metodo, $metodosValidos, true)) $metodo = 'otro';

            $origenPago  = ($args['origen_pago'] ?? 'empresa') === 'socio' ? 'socio' : 'empresa';
            $socioId     = null;
            $nombreSocio = null;
            if ($origenPago === 'socio' && !empty($args['socio_id'])) {
                require_once BASE_PATH . '/app/Models/SocioModel.php';
                $socio = (new SocioModel())->findById((int) $args['socio_id']);
                if ($socio) {
                    $socioId     = (int) $socio['id'];
                    $nombreSocio = $socio['nombre'];
                }
            }
            if ($origenPago === 'socio' && !$socioId) $origenPago = 'empresa';

            $usuarioId = (int) Session::user()['id'];

            $gastoId = $gastoModel->crearPagado([
                'fecha'              => $fecha,
                'descripcion'        => $descripcion,
                'numero_comprobante' => $numeroComprobante,
                'monto'              => $monto,
                'categoria_id'       => $categoriaId,
                'proveedor_id'       => $proveedorId,
                'usuario_id'         => $usuarioId,
                'notas'              => 'Registrado desde Chat IA',
            ]);

            $data = [
                'proveedor_id'        => $proveedorId,
                'beneficiario'        => null,
                'tipo'                => 'otro',
                'fecha'               => $fecha,
                'concepto'            => $args['concepto'] ?? $descripcion,
                'monto_mercado_pago'  => $metodo === 'mercado_pago'  ? $monto : 0,
                'monto_efectivo'      => $metodo === 'efectivo'      ? $monto : 0,
                'monto_debito'        => $metodo === 'debito'        ? $monto : 0,
                'monto_transferencia' => $metodo === 'transferencia' ? $monto : 0,
                'monto_cheque'        => $metodo === 'cheque'        ? $monto : 0,
                'monto_otro'          => $metodo === 'otro'          ? $monto : 0,
                'monto_total'         => $monto,
                'referencia_mp'       => null,
                'numero_comprobante'  => $numeroComprobante,
                'comprobante_path'    => null,
                'origen_pago'         => $origenPago,
                'socio_id'            => $socioId,
                'nombre_socio'        => $nombreSocio,
                'notas'               => 'Registrado desde Chat IA',
                'usuario_id'          => $usuarioId,
            ];

            $pagoId = (new PagoProveedorModel())->guardar($data, [], [
                ['gasto_id' => $gastoId, 'monto_aplicado' => $monto],
            ]);

            return [
                'ok'       => true,
                'id'       => $pagoId,
                'gasto_id' => $gastoId,
                'entidad'  => 'gasto_pagado',
                'total'    => $monto,
                'mensaje'  => "Gasto '{$descripcion}' registrado y pagado (pago #$pagoId) por $" . number_format($monto, 2, ',', '.') . ".",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Da de alta un bien de uso (activo_fijo) desde el Chat IA (ver docs/activos-fijos-diseno.md).
     * Solo alcanzable vía pedir_confirmacion (accion="registrar_activo_fijo") — nunca declarado
     * como tool directo, para que siempre pase por la tarjeta de confirmación del usuario.
     */
    private function toolRegistrarActivoFijo(array $args): array
    {
        require_once BASE_PATH . '/app/Models/ActivoFijoModel.php';
        try {
            $nombre = trim((string) ($args['nombre'] ?? ''));
            $valor  = (float) ($args['valor'] ?? 0);
            if ($nombre === '' || $valor <= 0) {
                return ['ok' => false, 'error' => 'nombre y valor (mayor a 0) son obligatorios.'];
            }

            $vidaUtil = !empty($args['vida_util_meses']) ? (int) $args['vida_util_meses'] : null;
            if ($vidaUtil !== null && $vidaUtil <= 0) $vidaUtil = null;

            $id = (new ActivoFijoModel())->crear([
                'nombre'          => $nombre,
                'categoria'       => trim((string) ($args['categoria'] ?? '')) ?: null,
                'origen'          => 'compra_nueva',
                'fecha_alta'      => $args['fecha'] ?? date('Y-m-d'),
                'valor'           => $valor,
                'vida_util_meses' => $vidaUtil,
                'proveedor_id'    => !empty($args['proveedor_id']) ? (int) $args['proveedor_id'] : null,
                'estado'          => 'activo',
                'notas'           => trim((string) ($args['notas'] ?? '')) ?: 'Registrado desde Chat IA',
                'usuario_id'      => (int) Session::user()['id'],
                'eliminado'       => 0,
            ]);

            return [
                'ok'      => true,
                'id'      => $id,
                'mensaje' => "Activo fijo '{$nombre}' registrado por $" . number_format($valor, 2, ',', '.') . ".",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Gemini API ─────────────────────────────────────────────────────────────

    /** Detalle de la última falla de llamarGemini() (http + curl_error), para poder
     *  armar un mensaje de error preciso en mensaje() sin cambiar la firma de la función. */
    private ?array $ultimoErrorGemini = null;

    /** Mensaje de error preciso para el usuario según el código HTTP de la última falla
     *  de Gemini — antes siempre decía "verificá la API key" sin importar la causa real. */
    private function mensajeErrorGemini(): string
    {
        $http            = $this->ultimoErrorGemini['http']              ?? 0;
        $body            = $this->ultimoErrorGemini['body']              ?? '';
        $retryEnSegundos = $this->ultimoErrorGemini['retry_en_segundos'] ?? null;
        $iteracion       = $this->ultimoErrorGemini['iteracion']         ?? 1;

        // Un 400 por payload mal formado (ej. el args:[] de normalizarArgsVacios(), si se
        // coló alguno viejo, u otro detalle de formato del historial) no tiene NADA que ver
        // con la API key — mostrar el mismo mensaje de "avisá al administrador" ahí manda a
        // revisar credenciales que están bien. El código HTTP (400) es igual en ambos casos,
        // así que se distingue mirando el texto del error que manda Google.
        $esErrorDeFormato = $http === 400 && (
            stripos($body, 'Unknown name') !== false
            || stripos($body, 'INVALID_ARGUMENT') !== false
        );

        return match (true) {
            // A mitad de una tarea con varios pasos (ya se venían resolviendo funciones):
            // no tiene sentido mencionar "esperá X segundos" porque lo que se perdió es el
            // avance hecho hasta acá, no solo la próxima llamada — mejor pedir reintentar
            // el pedido completo, que va a elegir el modelo con más cupo desde el principio.
            $http === 429 && $iteracion > 1 => 'Se cortó a mitad de una tarea con varios pasos porque el modelo de IA se quedó sin cupo. Volvé a intentar el pedido — el sistema va a elegir automáticamente el modelo con más cupo disponible.',
            $http === 429 && $retryEnSegundos !== null => "El modelo de IA está sin cupo en este momento. Esperá unos {$retryEnSegundos} segundos y volvé a intentar — el sistema va a elegir el modelo con más cupo disponible.",
            $http === 429 => 'El asistente está recibiendo muchas consultas en este momento. Esperá unos segundos y volvé a intentar.',
            $esErrorDeFormato => 'Gemini rechazó el formato de un paso interno de esta conversación. Volvé a intentar el pedido — si vuelve a pasar seguido, avisá al administrador con la hora exacta para revisar el log.',
            in_array($http, [400, 401, 403], true) => 'Hubo un problema con la configuración de la IA (API key o permisos). Avisá al administrador.',
            default => 'No se pudo conectar con el servicio de IA (problema de red). Reintentá en un momento.',
        };
    }

    /** Extrae el "Please retry in 55.1s" del cuerpo del error 429 de Gemini, si viene. */
    private function parseRetrySegundos(string $body): ?int
    {
        if (preg_match('/retry in ([\d.]+)s/i', $body, $m)) {
            return (int) ceil((float) $m[1]);
        }
        return null;
    }

    /**
     * Resume qué se le mandó a Gemini en esta llamada (para el log de diagnóstico) sin
     * guardar el payload completo — mirando el último turno de $messages: si son
     * resultados de función, lista sus nombres; si es el turno inicial, un extracto del
     * texto del usuario.
     */
    private function resumirEnviado(array $messages): string
    {
        $ultimoParts = end($messages)['parts'] ?? [];
        $resumenes = [];
        foreach ($ultimoParts as $p) {
            if (!isset($p['functionResponse'])) continue;
            $nombre   = $p['functionResponse']['name']     ?? '?';
            $response = $p['functionResponse']['response'] ?? [];
            // Si la función devolvió un error (ej. consultar_datos con SQL inválido), mostrar
            // el mensaje real acá — es la pista más directa para diagnosticar por qué el
            // modelo termina reintentando la misma función varias veces seguidas.
            if (($response['ok'] ?? true) === false && !empty($response['error'])) {
                $resumenes[] = "{$nombre} ERROR: " . mb_substr((string) $response['error'], 0, 120);
            } else {
                $resumenes[] = $nombre;
            }
        }
        if ($resumenes) return mb_substr('Resultados de: ' . implode(' | ', $resumenes), 0, 300);

        foreach ($ultimoParts as $p) {
            if (isset($p['text']) && trim($p['text']) !== '') return mb_substr(trim($p['text']), 0, 200);
        }
        return '(imagen/adjunto sin texto)';
    }

    /** Resume qué contestó Gemini (texto final, o qué función pidió llamar, con el SQL si es consultar_datos) para el log de diagnóstico. */
    private function resumirRecibido(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $llamadas = [];
        foreach ($parts as $p) {
            if (!isset($p['functionCall'])) continue;
            $nombre = $p['functionCall']['name'] ?? '?';
            $sql    = $p['functionCall']['args']['sql'] ?? null;
            $llamadas[] = $sql ? "{$nombre}(" . mb_substr((string) $sql, 0, 180) . ')' : $nombre;
        }
        if ($llamadas) return mb_substr('Llama a: ' . implode(' | ', $llamadas), 0, 300);

        $texto = implode(' ', array_filter(array_map(fn($p) => $p['text'] ?? null, $parts)));
        return $texto !== '' ? mb_substr($texto, 0, 200) : '(sin texto ni function call)';
    }

    /**
     * Gemini manda `args: {}` en llamadas a funciones sin parámetros (ej.
     * resumen_compras_pagos) — json_decode(..., true) lo convierte en un array PHP vacío,
     * indistinguible de `args: []`. Si ese `parts` se reenvía tal cual en el historial de la
     * próxima llamada, json_encode() lo vuelve a serializar como `[]` en vez de `{}`, y Gemini
     * responde 400 "Unknown name 'args': Cannot bind query parameter...". Se normaliza a
     * stdClass ANTES de guardarlo en $messages (no toca $funcCalls, que ya se extrajo aparte
     * y sigue usándose como array asociativo para ejecutar la herramienta).
     */
    private function normalizarArgsVacios(array $parts): array
    {
        foreach ($parts as &$part) {
            if (isset($part['functionCall']['args']) && $part['functionCall']['args'] === []) {
                $part['functionCall']['args'] = new stdClass();
            }
        }
        unset($part);
        return $parts;
    }

    /** Cadena de modelos configurada: el principal (GEMINI_MODEL) + la lista de respaldo
     *  (GEMINI_MODEL_FALLBACK, separada por coma), sin duplicados. */
    private function obtenerCadenaModelos(): array
    {
        $fallbacks = array_filter(array_map('trim', explode(',', GEMINI_MODEL_FALLBACK)));
        return array_values(array_unique(array_filter(array_merge([GEMINI_MODEL], $fallbacks))));
    }

    /**
     * Elige con qué modelo arrancar un mensaje NUEVO, mirando cuánto cupo (RPM/RPD) le
     * queda a cada uno según lo que el propio sistema ya registró en gemini_llamadas_log
     * (GeminiUsageModel::listarLimitesModelos() + GeminiLlamadaLogModel::usoActualPorModelo()).
     * A propósito NO se usa para cambiar de modelo a mitad de una conversación ya empezada
     * (ver comentario en llamarGemini()) — los modelos Gemini 3.x exigen que el historial de
     * function-calling incluya una "thought_signature" que solo genera el mismo modelo que
     * hizo esas llamadas; pasarle ese historial a otro modelo devuelve 400 INVALID_ARGUMENT
     * (bug real observado: mensaje #669, "Compras semanal x usuario"). Por eso el modelo se
     * elige UNA sola vez por mensaje, acá, y se mantiene fijo durante todo el loop de
     * function-calling de ese mensaje.
     *
     * Si ningún modelo de la cadena tiene margen conocido, devuelve igual el primero
     * (best-effort: nuestro conteo es una estimación local, no la cuota real de Google).
     */
    private function elegirModeloConCupo(array $modelos): string
    {
        if (count($modelos) <= 1) return $modelos[0] ?? GEMINI_MODEL;

        try {
            require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
            require_once BASE_PATH . '/app/Models/GeminiLlamadaLogModel.php';
            $limites  = (new GeminiUsageModel())->listarLimitesModelos();
            $logModel = new GeminiLlamadaLogModel();

            foreach ($modelos as $modelo) {
                $limite = $limites[$modelo] ?? null;
                if (!$limite) return $modelo; // sin límite configurado: se intenta directo

                $uso    = $logModel->usoActualPorModelo($modelo);
                $rpmOk  = $limite['rpm'] === null || $uso['rpm_usado'] < $limite['rpm'];
                $rpdOk  = $limite['rpd'] === null || $uso['rpd_usado'] < $limite['rpd'];
                if ($rpmOk && $rpdOk) return $modelo;
            }
        } catch (\Throwable $e) { /* tabla de límites aún no existe — usar el principal */ }

        return $modelos[0];
    }

    /**
     * Wrapper de llamarGeminiConKey() — decide con qué API key(s) probar:
     *
     * - Modo normal (default): intenta primero con GEMINI_API_KEY (gratis) y, SOLO si esa
     *   llamada falló específicamente con 429 "cupo agotado" (nunca por otro tipo de error
     *   — network, 400, etc.), reintenta el MISMO modelo con GEMINI_API_KEY_PAGO si está
     *   configurada.
     * - Modo "solo pago" (switch en /gemini-uso, tabla gemini_config, clave
     *   'modo_solo_pago'): salta directo a GEMINI_API_KEY_PAGO, sin gastar ni un solo
     *   intento de la cuota gratis — pensado para prenderlo/apagarlo al toque sin tocar
     *   .env ni redesplegar. Si está prendido pero no hay key de pago cargada, se cae de
     *   nuevo a la gratis (mejor "sigue funcionando" que dejar el chat mudo).
     *
     * A propósito nunca cambia de MODELO acá (eso ya lo maneja mensaje() con la cadena de
     * respaldo) — solo alterna la key, que es segura de cambiar a mitad de conversación
     * (a diferencia del modelo, la key no participa del thought_signature).
     */
    private function llamarGemini(string $systemPrompt, array $messages, array $tools, int $mensajeChatId, int $iteracion, string $modelo): ?array
    {
        if ($this->geminiSoloPago() && GEMINI_API_KEY_PAGO !== '') {
            return $this->llamarGeminiConKey($systemPrompt, $messages, $tools, $mensajeChatId, $iteracion, $modelo, GEMINI_API_KEY_PAGO);
        }

        $resultado = $this->llamarGeminiConKey($systemPrompt, $messages, $tools, $mensajeChatId, $iteracion, $modelo, GEMINI_API_KEY);

        if ($resultado === null && GEMINI_API_KEY_PAGO !== '' && ($this->ultimoErrorGemini['http'] ?? null) === 429) {
            logApp("Gemini: cupo free agotado para modelo={$modelo}, reintentando con GEMINI_API_KEY_PAGO", 'WARN');
            $resultado = $this->llamarGeminiConKey($systemPrompt, $messages, $tools, $mensajeChatId, $iteracion, $modelo, GEMINI_API_KEY_PAGO);
        }

        return $resultado;
    }

    /** Lee el switch 'modo_solo_pago' de gemini_config (ver /gemini-uso) — false si la tabla todavía no existe. */
    private function geminiSoloPago(): bool
    {
        try {
            require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
            return (new GeminiUsageModel())->getConfig()['modo_solo_pago'] === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function llamarGeminiConKey(string $systemPrompt, array $messages, array $tools, int $mensajeChatId, int $iteracion, string $modelo, string $apiKey): ?array
    {
        $usuarioIdLog = 0;
        try { $usuarioIdLog = (int) Session::user()['id']; } catch (\Throwable $e) {}
        $resumenEnviado = $this->resumirEnviado($messages);

        $payload = json_encode([
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents'       => $messages,
            'tools'          => $tools,
            'generationConfig' => [
                'temperature'     => 0.2,
                // mostrar_confirmacion_compra puede llevar varios ítems con opciones[] cada
                // uno (id/nombre/tipo_item) — en JSON pesa bastante más que el resumen en
                // texto libre que reemplazó, por eso el límite es más alto que antes (2048).
                'maxOutputTokens' => 8192,
            ],
        ], JSON_UNESCAPED_UNICODE);

        require_once BASE_PATH . '/app/Models/GeminiLlamadaLogModel.php';
        $logModel = new GeminiLlamadaLogModel();

        // El modelo llega FIJO desde mensaje() (elegido una sola vez, antes de la
        // iteración 1, con elegirModeloConCupo()) — acá solo se reintenta EL MISMO
        // modelo, y solo si Google pidió esperar poco. Nunca se cambia de modelo a
        // mitad de una conversación: ver el porqué en el comentario de
        // elegirModeloConCupo() (error 400 "thought_signature" de los modelos Gemini 3.x).
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . $modelo . ':generateContent?key=' . $apiKey;

        for ($intento = 1; $intento <= 2; $intento++) {
            $inicioMs = microtime(true);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                // 30s, no 90: las respuestas normales tardan 1-20s (visto en /gemini-log).
                // Con el rescate automático a otro modelo en la iteración 1 (mensaje()), un
                // modelo colgado/lento con 90s de margen bloqueaba todo el rescate casi 2
                // minutos antes de probar el siguiente — mejor cortar antes y pasar al
                // siguiente modelo de la cadena.
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);
            $duracionMs = (int) round((microtime(true) - $inicioMs) * 1000);

            if ($response !== false && $httpCode === 200) {
                $this->ultimoErrorGemini = null;
                $data  = json_decode($response, true);
                $usage = $data['usageMetadata'] ?? [];

                try {
                    $logModel->registrar([
                        'mensaje_chat_id'  => $mensajeChatId,
                        'usuario_id'       => $usuarioIdLog,
                        'iteracion'        => $iteracion,
                        'modelo'           => $modelo,
                        'resumen_enviado'  => $resumenEnviado,
                        'resumen_recibido' => $data ? $this->resumirRecibido($data) : null,
                        'http_code'        => $httpCode,
                        'exito'            => true,
                        'tokens_prompt'    => (int) ($usage['promptTokenCount']     ?? 0),
                        'tokens_respuesta' => (int) ($usage['candidatesTokenCount'] ?? 0),
                        'duracion_ms'      => $duracionMs,
                    ]);
                } catch (\Throwable $e) { /* tabla aún no existe — ignorar */ }

                // Registrar uso de tokens para costeo (no crítico: no falla si la tabla no existe)
                if ($data) {
                    try {
                        require_once BASE_PATH . '/app/Models/GeminiUsageModel.php';
                        (new GeminiUsageModel())->log(
                            $modelo,
                            (int) ($usage['promptTokenCount']     ?? 0),
                            (int) ($usage['candidatesTokenCount'] ?? 0),
                            (int) ($usage['totalTokenCount']      ?? 0),
                            $usuarioIdLog ?: null,
                            'chat'
                        );
                    } catch (\Throwable $e) { /* tabla aún no existe — ignorar */ }
                }

                return $data;
            }

            // Sin esto no queda rastro en app.log de POR QUÉ falló (429 por cuota, timeout,
            // API key inválida, etc.) — el frontend solo mostraba "No se pudo conectar con
            // Gemini" sin ninguna pista para diagnosticar.
            logApp("Gemini generateContent (chat, modelo={$modelo}) falló (intento {$intento}) - HTTP {$httpCode} curl_error={$curlErr} body=" . substr((string) $response, 0, 1000), 'ERROR');

            $retrySegundos = $httpCode === 429 ? $this->parseRetrySegundos((string) $response) : null;
            $this->ultimoErrorGemini = ['http' => $httpCode, 'curl_error' => $curlErr, 'retry_en_segundos' => $retrySegundos, 'iteracion' => $iteracion, 'body' => substr((string) $response, 0, 500)];

            try {
                $logModel->registrar([
                    'mensaje_chat_id' => $mensajeChatId,
                    'usuario_id'      => $usuarioIdLog,
                    'iteracion'       => $iteracion,
                    'modelo'          => $modelo,
                    'resumen_enviado' => $resumenEnviado,
                    'http_code'       => $httpCode,
                    'exito'           => false,
                    'duracion_ms'     => $duracionMs,
                    'error_resumen'   => substr($curlErr ?: (string) $response, 0, 500),
                ]);
            } catch (\Throwable $e) { /* tabla aún no existe — ignorar */ }

            // Solo vale la pena reintentar si la espera que pide Google es corta (Google
            // manda ese número exacto en el cuerpo del error) — si pide 50-60s, cortar
            // directo es mejor experiencia que bloquear al usuario esperando eso.
            if ($httpCode === 429 && $intento === 1 && $retrySegundos !== null && $retrySegundos <= 10) {
                sleep((int) ceil($retrySegundos));
                continue;
            }
            return null;
        }

        return null;
    }
}
