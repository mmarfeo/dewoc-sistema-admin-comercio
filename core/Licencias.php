<?php
/**
 * Licencias firmadas de los módulos que se cobran aparte del plan de Dewoc (ver
 * docs/dewoc-cobros-checklist.md, decisiones C16 a C21 y etapa 1A). La primera es
 * Dewoc Cobros (`dewoc_cobros`), pero nada acá es exclusivo de ese módulo.
 *
 * Formato: DWC1.<base64url(json)>.<base64url(firma)>
 *  - El JSON lleva kid, licencia_id, instalacion_id, comercio, modulo, dispositivos, desde,
 *    hasta y emitida_at.
 *  - La firma es ECDSA P-256 con SHA-256 sobre los bytes del JSON TAL CUAL vienen (no sobre
 *    un JSON re-serializado: cambiaría el orden o los espacios y la firma no cerraría).
 *  - Se verifica con openssl_verify(), que está en cualquier hosting (sodium no está
 *    habilitado en el PHP local).
 *
 * Esta clase NO toca la base: verifica el texto y calcula el estado según la fecha. La
 * licencia la carga y la elige LicenciaModel; el estado lo expone moduloEstado().
 *
 * Una licencia funciona sin conexión con el proveedor y no se puede revocar antes de que
 * venza (C19): se emite por el período pagado y se renueva con una nueva.
 */
class Licencias
{
    const PREFIJO = 'DWC1';

    /** Días antes de `hasta` desde los que se avisa (`por_vencer`). */
    const DIAS_AVISO = 7;

    /** Días después de `hasta` en los que sigue funcionando, con aviso en rojo (`gracia`). */
    const DIAS_GRACIA = 7;

    const CAMPOS = [
        'kid', 'licencia_id', 'instalacion_id', 'comercio', 'modulo',
        'dispositivos', 'desde', 'hasta', 'emitida_at',
    ];

    /** Estados en los que el módulo funciona (moduloHabilitado()). */
    const ESTADOS_HABILITADOS = ['activa', 'por_vencer', 'gracia'];

    const MENSAJES = [
        'formato_invalido' => 'El texto no es una licencia de Dewoc. Copiala completa, desde "DWC1." hasta el final.',
        'kid_desconocido'  => 'La licencia está firmada con una clave que esta instalación no reconoce. Pedile una nueva al proveedor.',
        'firma_invalida'   => 'La firma de la licencia no es válida: el texto está incompleto o fue modificado.',
        'datos_invalidos'  => 'La licencia tiene datos incompletos o inválidos. Pedile una nueva al proveedor.',
        'otra_instalacion' => 'La licencia es de otra instalación de Dewoc. Revisá que el proveedor haya usado tu código de instalación.',
        'otro_modulo'      => 'La licencia es de otro módulo.',
    ];

    // ── Emitir (solo herramientas/emitir_licencia.php y los tests) ──────────

    /**
     * Firma los datos con la clave privada y devuelve la licencia lista para pegar.
     * $clavePrivada: PEM, o el resultado de openssl_pkey_get_private()/openssl_pkey_new().
     */
    public static function firmar(array $datos, mixed $clavePrivada): string
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudieron serializar los datos de la licencia.');
        }
        if (!openssl_sign($json, $firma, $clavePrivada, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la licencia: ' . (openssl_error_string() ?: 'clave privada inválida'));
        }
        return self::PREFIJO . '.' . self::b64urlEncode($json) . '.' . self::b64urlEncode($firma);
    }

    // ── Verificar ────────────────────────────────────────────────────────────

    /**
     * Verifica formato, firma, instalación y módulo. NO mira las fechas: una licencia
     * vencida sigue siendo auténtica (el estado lo calcula estado()).
     *
     * Devuelve ['ok' => true, 'datos' => [...]] o
     *         ['ok' => false, 'error' => <código>, 'mensaje' => <texto para el admin>].
     *
     * $claves: [kid => PEM]. Por defecto, config/licencias_claves_publicas.php.
     */
    public static function verificar(string $texto, string $instalacionId, string $modulo, ?array $claves = null): array
    {
        // Pegada desde WhatsApp o un correo puede venir partida en varias líneas.
        $texto  = preg_replace('/\s+/', '', $texto);
        $partes = explode('.', $texto);

        if (count($partes) !== 3 || $partes[0] !== self::PREFIJO) return self::error('formato_invalido');

        $json  = self::b64urlDecode($partes[1]);
        $firma = self::b64urlDecode($partes[2]);
        if ($json === null || $firma === null || $firma === '') return self::error('formato_invalido');

        $datos = json_decode($json, true);
        if (!is_array($datos)) return self::error('formato_invalido');

        $claves ??= self::clavesPublicas();
        $kid = $datos['kid'] ?? null;
        if (!is_string($kid) || !isset($claves[$kid])) return self::error('kid_desconocido');

        // openssl_verify: 1 = válida, 0 = inválida, -1/false = error (firma mal formada, etc.).
        if (openssl_verify($json, $firma, $claves[$kid], OPENSSL_ALGO_SHA256) !== 1) {
            while (openssl_error_string() !== false); // vaciar la cola de errores de OpenSSL
            return self::error('firma_invalida');
        }

        if (!self::datosValidos($datos)) return self::error('datos_invalidos');
        if (!hash_equals(strtolower($instalacionId), strtolower((string)$datos['instalacion_id']))) {
            return self::error('otra_instalacion');
        }
        if ($datos['modulo'] !== $modulo) return self::error('otro_modulo');

        $datos['dispositivos'] = (int)$datos['dispositivos'];
        return ['ok' => true, 'datos' => $datos];
    }

    /** Campos obligatorios presentes y con forma razonable (ya con la firma verificada). */
    private static function datosValidos(array $d): bool
    {
        foreach (self::CAMPOS as $campo) {
            if (!array_key_exists($campo, $d) || $d[$campo] === null || $d[$campo] === '') return false;
        }
        if (filter_var($d['dispositivos'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) return false;
        if (!self::esFecha((string)$d['desde']) || !self::esFecha((string)$d['hasta'])) return false;
        if ($d['desde'] > $d['hasta']) return false;
        return strtotime((string)$d['emitida_at']) !== false;
    }

    private static function esFecha(string $f): bool
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
        return $dt !== false && $dt->format('Y-m-d') === $f;
    }

    // ── Estado según la fecha ───────────────────────────────────────────────

    /**
     * Estado de una licencia ya verificada en la fecha $hoy (Y-m-d, por defecto hoy):
     *  - activa      → faltan más de DIAS_AVISO días para `hasta`
     *  - por_vencer  → faltan DIAS_AVISO días o menos (hasta el mismo `hasta`, inclusive)
     *  - gracia      → pasó `hasta`, hasta DIAS_GRACIA días después
     *  - vencida     → pasada la gracia
     *  - sin_licencia → $datos null
     *
     * dias_restantes: días hasta `hasta` (0 el último día, negativo después).
     */
    public static function estado(?array $datos, ?string $hoy = null): array
    {
        if ($datos === null) {
            return ['estado' => 'sin_licencia', 'hasta' => null, 'dispositivos' => 0, 'dias_restantes' => null, 'licencia_id' => null];
        }

        $dias = self::diasEntre($hoy ?? date('Y-m-d'), (string)$datos['hasta']);

        $estado = match (true) {
            $dias > self::DIAS_AVISO    => 'activa',
            $dias >= 0                  => 'por_vencer',
            $dias >= -self::DIAS_GRACIA => 'gracia',
            default                     => 'vencida',
        };

        return [
            'estado'         => $estado,
            'hasta'          => (string)$datos['hasta'],
            'dispositivos'   => (int)$datos['dispositivos'],
            'dias_restantes' => $dias,
            'licencia_id'    => (string)$datos['licencia_id'],
        ];
    }

    /**
     * De varias licencias ya verificadas, la que manda (C19): la emitida más recientemente
     * entre las que ya empezaron (`desde` <= hoy). Una renovación cargada antes de tiempo
     * no le gana a la vigente hasta que llega su `desde`. Devuelve null si no hay ninguna.
     */
    public static function elegirVigente(array $licencias, ?string $hoy = null): ?array
    {
        $hoy ??= date('Y-m-d');
        $vigente = null;
        foreach ($licencias as $d) {
            if ((string)$d['desde'] > $hoy) continue;
            if ($vigente === null || strtotime((string)$d['emitida_at']) > strtotime((string)$vigente['emitida_at'])) {
                $vigente = $d;
            }
        }
        return $vigente;
    }

    private static function diasEntre(string $desde, string $hasta): int
    {
        $a = new DateTimeImmutable($desde);
        $b = new DateTimeImmutable($hasta);
        return (int)$a->diff($b)->format('%r%a');
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    public static function clavesPublicas(): array
    {
        static $claves = null;
        return $claves ??= (require dirname(__DIR__) . '/config/licencias_claves_publicas.php');
    }

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** null si no es base64url válido. */
    public static function b64urlDecode(string $txt): ?string
    {
        if ($txt === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $txt)) return null;
        $bin = base64_decode(strtr($txt, '-_', '+/'), true);
        return $bin === false ? null : $bin;
    }

    private static function error(string $codigo): array
    {
        return ['ok' => false, 'error' => $codigo, 'mensaje' => self::MENSAJES[$codigo]];
    }
}
