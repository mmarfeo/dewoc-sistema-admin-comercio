<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/app/Models/IntegracionMenuAppModel.php';
require_once BASE_PATH . '/core/Licencias.php';
require_once BASE_PATH . '/core/ServidorLicencias.php';

/**
 * Licencias cargadas en esta instalación (ver docs/dewoc-cobros-checklist.md, tareas 1A.4
 * y 1A.5). La verificación criptográfica está en core/Licencias.php; acá se guardan y se
 * elige la que manda.
 *
 * Nunca se confía en las columnas de la tabla para habilitar nada: vigente() vuelve a
 * verificar la firma del texto de cada fila.
 */
class LicenciaModel extends Model
{
    protected string $table = 'licencias';

    /**
     * Instalación a la que tienen que estar atadas las licencias (C19). Es el mismo UUID
     * fijo de la integración con MenuApp, que se genera una vez y no cambia nunca.
     */
    public function instalacionId(): string
    {
        return (new IntegracionMenuAppModel())->instalacionId();
    }

    /**
     * Verifica y guarda una licencia pegada por el admin.
     * Devuelve ['ok' => bool, 'mensaje' => texto para mostrar, 'datos' => datos de la licencia].
     */
    public function cargar(string $texto, string $modulo, ?int $usuarioId): array
    {
        $texto = preg_replace('/\s+/', '', $texto);
        $v = Licencias::verificar($texto, $this->instalacionId(), $modulo);
        if (!$v['ok']) return ['ok' => false, 'mensaje' => $v['mensaje']];

        $d = $v['datos'];
        if ($d['hasta'] < date('Y-m-d')) {
            return ['ok' => false, 'mensaje' => 'La licencia ya venció (el ' . fecha($d['hasta']) . ').'];
        }

        // INSERT IGNORE + UNIQUE(licencia_id): pegar dos veces la misma no la duplica.
        $insertadas = $this->query(
            "INSERT IGNORE INTO licencias
                (licencia_id, modulo, texto, dispositivos, desde, hasta, emitida_at, cargada_por)
             VALUES (:lid, :mod, :txt, :disp, :desde, :hasta, :emit, :usr)",
            [
                ':lid'   => (string)$d['licencia_id'],
                ':mod'   => $modulo,
                ':txt'   => $texto,
                ':disp'  => (int)$d['dispositivos'],
                ':desde' => $d['desde'],
                ':hasta' => $d['hasta'],
                // En la licencia viene en ISO 8601 con zona; se guarda en la del comercio.
                ':emit'  => date('Y-m-d H:i:s', strtotime((string)$d['emitida_at'])),
                ':usr'   => $usuarioId,
            ]
        )->rowCount();

        if ($insertadas === 0) {
            return ['ok' => true, 'mensaje' => 'Esa licencia ya estaba cargada.', 'datos' => $d];
        }

        $celulares = $d['dispositivos'] === 1 ? '1 celular' : $d['dispositivos'] . ' celulares';
        $mensaje   = "Licencia cargada: {$celulares} hasta el " . fecha($d['hasta']) . '.';
        if ($d['desde'] > date('Y-m-d')) {
            $mensaje .= ' Empieza a regir el ' . fecha($d['desde']) . '.';
        }
        return ['ok' => true, 'mensaje' => $mensaje, 'datos' => $d];
    }

    // ── Licencias automáticas (C22, etapa 1B) ────────────────────────────────

    /** Dirección del servidor de licencias. Se cambia con LICENCIAS_SERVIDOR_URL en el .env. */
    public static function servidorUrl(): string
    {
        return rtrim((string)(getenv('LICENCIAS_SERVIDOR_URL') ?: 'https://erpcomercio.dewoc.com/'), '/') . '/';
    }

    /**
     * Pide la licencia al servidor y la carga. Es lo que hace "Contratar" y la renovación
     * automática: el comercio no copia ni pega nada.
     * Devuelve ['ok' => bool, 'mensaje' => texto para mostrar, 'baja' => bool].
     */
    public function pedirAlServidor(string $modulo): array
    {
        $instalacion = $this->instalacionId();
        $comercio    = defined('NEGOCIO_NOMBRE') ? NEGOCIO_NOMBRE : 'Comercio';

        // La instalación que es el servidor se firma sola, sin llamarse por HTTP.
        if (ServidorLicencias::habilitado()) {
            require_once BASE_PATH . '/app/Models/LicenciaClienteModel.php';
            $r = (new LicenciaClienteModel())->atender($instalacion, $modulo, $comercio, BASE_URL);
            if (!$r['ok']) return ['ok' => false, 'mensaje' => $r['mensaje'] ?? 'No se pudo emitir la licencia.', 'baja' => ($r['codigo'] ?? '') === 'baja'];
            return $this->cargar($r['licencia'], $modulo, null) + ['baja' => false];
        }

        $cuerpo = json_encode(['instalacion_id' => $instalacion, 'modulo' => $modulo, 'comercio' => $comercio, 'url' => BASE_URL], JSON_UNESCAPED_UNICODE);
        $ch = curl_init(self::servidorUrl() . 'api/licencias/v1/solicitar');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8', 'Accept: application/json', 'User-Agent: Dewoc/Licencias'],
        ]);
        $respuesta = curl_exec($ch);
        $http      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errCurl   = curl_error($ch);
        curl_close($ch);
        logApp("Licencias: solicitud de {$modulo} al servidor → HTTP {$http}" . ($errCurl !== '' ? " ({$errCurl})" : ''));

        $datos = is_string($respuesta) ? json_decode($respuesta, true) : null;
        if ($http === 200 && is_array($datos) && !empty($datos['licencia'])) {
            return $this->cargar((string)$datos['licencia'], $modulo, null) + ['baja' => false];
        }
        if ($http === 403) {
            return ['ok' => false, 'mensaje' => (string)($datos['mensaje'] ?? 'Dewoc Cobros está dado de baja para este comercio.'), 'baja' => true];
        }
        return ['ok' => false, 'mensaje' => 'No se pudo conectar con el servidor de licencias. Probá de nuevo en un rato.', 'baja' => false];
    }

    /**
     * Si el módulo ya se contrató alguna vez y la licencia está por vencer (o venció), pide
     * la renovación. Lo llaman la pantalla de Integraciones y el cron diario. Nunca contrata
     * por primera vez: eso lo decide el admin con "Contratar".
     */
    public function renovarSiHaceFalta(string $modulo): ?array
    {
        $contratado = (bool)$this->queryScalar("SELECT COUNT(*) FROM licencias WHERE modulo = :m", [':m' => $modulo]);
        $estado     = Licencias::estado($this->vigente($modulo))['estado'];
        if (!self::necesitaRenovar($estado, $contratado)) return null;
        return $this->pedirAlServidor($modulo);
    }

    /** Se renueva desde el aviso de vencimiento (7 días antes) y también si ya venció. */
    public static function necesitaRenovar(string $estado, bool $contratado): bool
    {
        return $contratado && in_array($estado, ['por_vencer', 'gracia', 'vencida', 'sin_licencia'], true);
    }

    /** Todas las licencias cargadas de un módulo, la más nueva primero (para listarlas). */
    public function listar(string $modulo): array
    {
        return $this->queryAll(
            "SELECT id, licencia_id, dispositivos, desde, hasta, emitida_at, cargada_por, created_at
               FROM licencias WHERE modulo = :m ORDER BY emitida_at DESC, id DESC",
            [':m' => $modulo]
        );
    }

    /**
     * Datos de la licencia que manda hoy (C19: la válida emitida más recientemente entre
     * las que ya empezaron), o null si no hay ninguna. Verifica la firma de cada fila.
     */
    public function vigente(string $modulo, ?string $hoy = null): ?array
    {
        $filas = $this->queryAll("SELECT texto FROM licencias WHERE modulo = :m", [':m' => $modulo]);
        if (!$filas) return null;

        $instalacion = $this->instalacionId();
        $validas = [];
        foreach ($filas as $f) {
            $v = Licencias::verificar((string)$f['texto'], $instalacion, $modulo);
            if ($v['ok']) $validas[] = $v['datos'];
        }
        return Licencias::elegirVigente($validas, $hoy);
    }
}
