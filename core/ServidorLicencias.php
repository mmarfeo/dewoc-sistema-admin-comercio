<?php
require_once __DIR__ . '/Licencias.php';

/**
 * Servidor de licencias automáticas (ver docs/dewoc-cobros-checklist.md, decisión C22 y etapa
 * 1B). Firma licencias para cualquier instalación de Dewoc que las pida, sin aprobación manual:
 * al tocar "Contratar" el comercio queda habilitado al instante por un mes, y cada Dewoc la
 * renueva solo antes de que venza. Lo único que lo frena es dar de baja al comercio.
 *
 * Solo funciona en la instalación que tiene LICENCIAS_SERVIDOR=1 y la clave privada
 * (LICENCIAS_CLAVE_PRIVADA, una ruta FUERA de public_html). Es una decisión de producto mínimo:
 * con la clave en el hosting, quien acceda al servidor puede emitir licencias. Si Dewoc Cobros
 * escala, la firma vuelve a una PC del proveedor o a un servidor aparte.
 *
 * Esta clase no toca la base: decide y firma. Los datos de cada comercio los guarda
 * LicenciaClienteModel.
 */
class ServidorLicencias
{
    /** Módulos que se pueden pedir. */
    const MODULOS = ['dewoc_cobros'];

    /** Celulares por defecto de cada licencia nueva (se cambia por comercio en el panel). */
    const DISPOSITIVOS = 2;

    /** Duración de cada licencia. */
    const DIAS_LICENCIA = 30;

    public static function habilitado(): bool
    {
        return getenv('LICENCIAS_SERVIDOR') === '1' && (string)getenv('LICENCIAS_CLAVE_PRIVADA') !== '';
    }

    /**
     * Qué hacer con una solicitud:
     *  - 'baja'     → el comercio está dado de baja: no se emite nada.
     *  - 'reutilizar' → la última licencia emitida todavía tiene más de DIAS_AVISO días: se
     *                 devuelve esa, así un Dewoc que pregunta seguido no genera una por día.
     *  - 'emitir'   → licencia nueva desde hoy por DIAS_LICENCIA días.
     */
    public static function decidir(?array $cliente, string $hoy): string
    {
        if ($cliente !== null && ($cliente['estado'] ?? 'activo') === 'baja') return 'baja';
        if ($cliente !== null && !empty($cliente['ultima_licencia']) && !empty($cliente['ultima_hasta'])) {
            $limite = (new DateTimeImmutable($hoy))->modify('+' . Licencias::DIAS_AVISO . ' days')->format('Y-m-d');
            if ((string)$cliente['ultima_hasta'] > $limite) return 'reutilizar';
        }
        return 'emitir';
    }

    /** Datos de una licencia nueva, listos para firmar. */
    public static function datosNuevaLicencia(string $instalacionId, string $modulo, string $comercio, int $dispositivos, string $hoy, string $kid): array
    {
        $hasta = (new DateTimeImmutable($hoy))->modify('+' . (self::DIAS_LICENCIA - 1) . ' days')->format('Y-m-d');
        return [
            'kid'            => $kid,
            'licencia_id'    => 'A' . str_replace('-', '', $hoy) . '-' . strtoupper(bin2hex(random_bytes(4))),
            'instalacion_id' => strtolower($instalacionId),
            'comercio'       => $comercio,
            'modulo'         => $modulo,
            'dispositivos'   => max(1, $dispositivos),
            'desde'          => $hoy,
            'hasta'          => $hasta,
            'emitida_at'     => date('c'),
        ];
    }

    /** Valida lo que manda el Dewoc que pide. Devuelve el mensaje de error o null. */
    public static function validarSolicitud(string $instalacionId, string $modulo, string $comercio): ?string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $instalacionId)) {
            return 'instalacion_invalida';
        }
        if (!in_array($modulo, self::MODULOS, true)) return 'modulo_invalido';
        if ($comercio === '' || mb_strlen($comercio) > 150) return 'comercio_invalido';
        return null;
    }

    /** Firma con la clave del .env y comprueba que verifique con la clave pública del código. */
    public static function firmar(array $datos): string
    {
        $ruta  = (string)getenv('LICENCIAS_CLAVE_PRIVADA');
        $pem   = is_file($ruta) ? file_get_contents($ruta) : false;
        $clave = $pem !== false ? openssl_pkey_get_private($pem) : false;
        if ($clave === false) {
            throw new RuntimeException('No se pudo leer la clave privada de LICENCIAS_CLAVE_PRIVADA.');
        }
        $licencia = Licencias::firmar($datos, $clave);
        $v = Licencias::verificar($licencia, $datos['instalacion_id'], $datos['modulo']);
        if (!$v['ok']) {
            throw new RuntimeException("La licencia firmada no verifica con la clave pública ({$v['error']}): revisá LICENCIAS_KID.");
        }
        return $licencia;
    }

    public static function kid(): string
    {
        return (string)(getenv('LICENCIAS_KID') ?: 'k1');
    }
}
