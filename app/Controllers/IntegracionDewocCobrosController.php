<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/LicenciaModel.php';
require_once BASE_PATH . '/app/Models/CobroDispositivoModel.php';
require_once BASE_PATH . '/app/Models/CobroRecibidoModel.php';
require_once BASE_PATH . '/app/Models/LicenciaClienteModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/core/ServidorLicencias.php';

/**
 * Integraciones → Dewoc Cobros (ver docs/dewoc-cobros-checklist.md, sección 1 "Recorrido
 * del usuario", tareas 1A.7, 1A.8 y 1.6). Una sola pantalla que muestra el paso en el que
 * está el comercio: contratar, cargar la licencia, vincular celulares y uso diario.
 *
 * Solo admin, igual que MenuApp: desde acá se generan los tokens que dejan a un celular
 * mandarle datos a Dewoc.
 */
class IntegracionDewocCobrosController extends Controller
{
    const MODULO = 'dewoc_cobros';

    /** Sin latido hace más de esto, el celular se muestra "sin conexión" (C11). */
    const MINUTOS_SIN_CONTACTO = 15;

    private LicenciaModel         $licencias;
    private CobroDispositivoModel $dispositivos;

    public function __construct()
    {
        $this->licencias    = new LicenciaModel();
        $this->dispositivos = new CobroDispositivoModel();
    }

    /**
     * URL base de la API que va en el QR. En local se puede cambiar con COBROS_API_BASE en
     * el .env, porque el celular no puede abrir "localhost": hay que poner la IP de la PC
     * (ver la sección 4 de docs/dewoc-cobros-servidor-plan.md).
     */
    public static function apiBase(): string
    {
        $base = getenv('COBROS_API_BASE') ?: rtrim(BASE_URL, '/') . '/api/cobros/v1/';
        return rtrim($base, '/') . '/';
    }

    public function index(): void
    {
        $this->requireRole('admin');

        // Renovación automática (C22): si la licencia está por vencer, se pide la nueva antes de
        // mostrar la pantalla. El cron diario hace lo mismo aunque nadie entre acá.
        $avisoRenovacion = null;
        try {
            $r = $this->licencias->renovarSiHaceFalta(self::MODULO);
            if ($r !== null && !$r['ok']) $avisoRenovacion = $r['mensaje'];
        } catch (\Throwable $e) {
            logApp('Dewoc Cobros: no se pudo renovar la licencia — ' . $e->getMessage(), 'ERROR');
        }

        $estado = moduloEstado(self::MODULO);

        // Con la migración sin correr, la pantalla igual se abre y lo dice, en vez de dar
        // un error 500.
        $resultado = (string)$this->param('resultado', '');
        if (!in_array($resultado, ['', 'cobro', 'sin_venta', 'descartado', 'no_reconocido', 'sin_lector', 'prueba'], true)) {
            $resultado = '';
        }

        $faltaMigracion = false;
        $licencias = $dispositivos = $notificaciones = $clientes = [];
        try {
            $licencias      = $this->licencias->listar(self::MODULO);
            $dispositivos   = $this->dispositivos->listar();
            $notificaciones = (new CobroRecibidoModel())->ultimas($resultado);
            if (ServidorLicencias::habilitado()) $clientes = (new LicenciaClienteModel())->listar();
        } catch (\PDOException $e) {
            $faltaMigracion = true;
            logApp('Integraciones → Dewoc Cobros: falta correr una migración — ' . $e->getMessage(), 'ERROR');
        }

        // Estado de cada celular para la lista. Los activos vienen primero y en orden de
        // vinculación, así que los primeros N son los que entran en la licencia (C18).
        $limite   = (int)$estado['dispositivos'];
        $posicion = 0;
        $limiteContacto = date('Y-m-d H:i:s', strtotime('-' . self::MINUTOS_SIN_CONTACTO . ' minutes'));
        foreach ($dispositivos as &$d) {
            if (!(int)$d['activo'])                  $d['estado'] = 'revocado';
            elseif ($posicion++ >= $limite)          $d['estado'] = 'pausado_limite';
            elseif ($d['ultimo_contacto_at'] === null) $d['estado'] = 'esperando';
            elseif ($d['ultimo_contacto_at'] < $limiteContacto) $d['estado'] = 'sin_contacto';
            else                                     $d['estado'] = 'activo';
        }
        unset($d);
        $usados = count(array_filter($dispositivos, fn($d) => (int)$d['activo'] === 1));

        $this->render('integraciones.dewoc-cobros', [
            'pageTitle'      => 'Integraciones · Dewoc Cobros',
            'breadcrumb'     => ['Administración' => null, 'Integraciones' => null, 'Dewoc Cobros' => null],
            'estado'         => $estado,
            'habilitado'     => moduloHabilitado(self::MODULO),
            'licencias'      => $licencias,
            'dispositivos'   => $dispositivos,
            'usados'         => $usados,
            'notificaciones' => $notificaciones,
            'filtroResultado'=> $resultado,
            'faltaMigracion' => $faltaMigracion,
            'instalacionId'  => $this->licencias->instalacionId(),
            'contactoWhatsapp' => getenv('DEWOC_COBROS_CONTACTO_WHATSAPP') ?: '',
            'contactoEmail'    => getenv('DEWOC_COBROS_CONTACTO_EMAIL') ?: '',
            'avisoRenovacion'  => $avisoRenovacion,
            // Panel del servidor de licencias: solo en la instalación que las emite.
            'esServidorLicencias' => ServidorLicencias::habilitado(),
            'clientes'         => $clientes,
        ]);
    }

    /**
     * "Contratar": pide la licencia al servidor y la carga, sin copiar ni pegar nada (C22). El
     * comercio queda habilitado al instante por un mes y después se renueva solo.
     */
    public function contratar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $r = $this->licencias->pedirAlServidor(self::MODULO);
        if (!$r['ok']) $this->json(['ok' => false, 'error' => $r['mensaje']], 422);

        AuditLog::log('contratar', 'integraciones', 'Dewoc Cobros: licencia automática ' . ($r['datos']['licencia_id'] ?? ''));
        $this->json(['ok' => true, 'mensaje' => 'Dewoc Cobros habilitado. ' . $r['mensaje']]);
    }

    /** Panel del servidor de licencias: dar de baja o reactivar a un comercio. */
    public function clienteEstado(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        if (!ServidorLicencias::habilitado()) $this->json(['ok' => false, 'error' => 'Esta instalación no emite licencias.'], 404);

        $id     = (int)$this->input('id', 0);
        $estado = (string)$this->input('estado', '');
        if (!(new LicenciaClienteModel())->cambiarEstado($id, $estado)) {
            $this->json(['ok' => false, 'error' => 'No se pudo cambiar el estado.'], 422);
        }
        AuditLog::log('licencia_cliente', 'integraciones', "Servidor de licencias: cliente {$id} → {$estado}");
        $this->json(['ok' => true, 'mensaje' => $estado === 'baja'
            ? 'Dado de baja: no se le renueva más. La licencia que ya tiene funciona hasta que vence.'
            : 'Reactivado: la próxima vez que lo pida se le emite la licencia.']);
    }

    /** Panel del servidor de licencias: celulares de las próximas licencias de un comercio. */
    public function clienteDispositivos(): void
    {
        $this->requireRole('admin');
        Csrf::check();
        if (!ServidorLicencias::habilitado()) $this->json(['ok' => false, 'error' => 'Esta instalación no emite licencias.'], 404);

        $id   = (int)$this->input('id', 0);
        $cant = (int)$this->input('dispositivos', 0);
        if (!(new LicenciaClienteModel())->cambiarDispositivos($id, $cant)) {
            $this->json(['ok' => false, 'error' => 'La cantidad tiene que estar entre 1 y 50.'], 422);
        }
        AuditLog::log('licencia_cliente', 'integraciones', "Servidor de licencias: cliente {$id} → {$cant} celulares");
        $this->json(['ok' => true, 'mensaje' => "Listo: {$cant} celulares. El comercio los recibe la próxima vez que abra Integraciones o al día siguiente."]);
    }

    /** Verifica y guarda la licencia que pegó el admin (1A.8). */
    public function cargarLicencia(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        // Sin sanitize(): el texto se verifica por firma, y strip_tags no aporta nada acá.
        $texto = trim((string)($_POST['licencia'] ?? ''));
        if ($texto === '') {
            $this->json(['ok' => false, 'error' => 'Pegá la licencia que te pasó el proveedor.'], 422);
        }

        $r = $this->licencias->cargar($texto, self::MODULO, (int)(Session::user()['id'] ?? 0) ?: null);
        if (!$r['ok']) $this->json(['ok' => false, 'error' => $r['mensaje']], 422);

        AuditLog::log('cargar_licencia', 'integraciones',
            "Dewoc Cobros: licencia {$r['datos']['licencia_id']} ({$r['datos']['dispositivos']} celulares, "
            . "{$r['datos']['desde']} a {$r['datos']['hasta']})");

        $this->json(['ok' => true, 'mensaje' => $r['mensaje']]);
    }

    /**
     * Da de alta un celular y devuelve su token EN CLARO junto con el contenido del QR. Es la
     * única vez que el token existe fuera del hash: no se guarda en la sesión ni en ningún
     * lado, así que si se cierra el QR sin escanearlo hay que revocar ese celular y generar
     * otro.
     */
    public function generarToken(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        if (!moduloHabilitado(self::MODULO)) {
            $this->json(['ok' => false, 'error' => 'Dewoc Cobros no está habilitado: cargá una licencia vigente.'], 422);
        }
        $incluidos = (int)moduloEstado(self::MODULO)['dispositivos'];
        if ($this->dispositivos->cantidadActivos() >= $incluidos) {
            $this->json(['ok' => false, 'error' => "Tu licencia incluye {$incluidos} " . ($incluidos === 1 ? 'celular' : 'celulares')
                . '. Revocá uno o ampliá la licencia.'], 422);
        }

        $nombre = trim((string)$this->input('nombre', ''));
        if ($nombre === '') $nombre = 'Celular ' . ($this->dispositivos->cantidadActivos() + 1);

        $nuevo = $this->dispositivos->crear($nombre, (int)(Session::user()['id'] ?? 0) ?: null);
        AuditLog::log('generar_token', 'integraciones', "Dewoc Cobros: se vinculó el celular \"{$nombre}\" (id {$nuevo['id']})");

        $this->json([
            'ok'             => true,
            'dispositivo_id' => $nuevo['id'],
            'token'          => $nuevo['token'],
            // Contenido del QR que escanea la app (tarea 1.6).
            'qr'             => json_encode(['v' => 1, 'base' => self::apiBase(), 'token' => $nuevo['token']], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Para el QR abierto: la pantalla consulta cada pocos segundos si el celular ya se
     * conectó, y lo muestra como "Activo" sin recargar (1.6).
     */
    public function dispositivo(): void
    {
        $this->requireRole('admin');
        session_write_close(); // consulta seguida: que no trabe otras requests de la sesión

        $d = $this->dispositivos->findById((int)$this->param('id', 0));
        if (!$d) $this->json(['ok' => false, 'error' => 'No existe ese celular.'], 404);

        $this->json([
            'ok'        => true,
            'conectado' => $d['ultimo_contacto_at'] !== null,
            'activo'    => (int)$d['activo'] === 1,
        ]);
    }

    /** Revoca un celular: la app vuelve sola a la pantalla de vincular en su próxima llamada. */
    public function revocar(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id', 0);
        if (!$this->dispositivos->revocar($id)) {
            $this->json(['ok' => false, 'error' => 'Ese celular ya estaba revocado o no existe.'], 422);
        }
        AuditLog::log('revocar', 'integraciones', "Dewoc Cobros: se revocó el celular id {$id}");
        $this->json(['ok' => true, 'mensaje' => 'Celular revocado.']);
    }

    /**
     * Vuelve a pasar por el lector las no_reconocido y sin_lector de los últimos 7 días
     * (C14), después de corregir un lector. Con la licencia vencida no se puede (1.6).
     */
    public function volverALeer(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        if (!moduloHabilitado(self::MODULO)) {
            $this->json(['ok' => false, 'error' => 'Dewoc Cobros no está habilitado.'], 422);
        }
        $cambiaron = (new CobroRecibidoModel())->volverALeer(7);
        $this->json(['ok' => true, 'mensaje' => $cambiaron === 0
            ? 'Ninguna notificación cambió: el lector todavía no las reconoce.'
            : "Se volvieron a leer: {$cambiaron} cambiaron de resultado."]);
    }
}
