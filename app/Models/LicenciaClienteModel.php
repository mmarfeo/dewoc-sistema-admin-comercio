<?php
require_once BASE_PATH . '/app/Models/Model.php';
require_once BASE_PATH . '/core/ServidorLicencias.php';

/**
 * Comercios que pidieron licencia a este servidor (ver core/ServidorLicencias.php). Solo se usa
 * en la instalación que hace de servidor de licencias.
 */
class LicenciaClienteModel extends Model
{
    protected string $table = 'licencias_clientes';

    /**
     * Atiende una solicitud: devuelve la licencia vigente, emite una nueva o la rechaza si el
     * comercio está dado de baja.
     *
     * @return array{ok: bool, licencia?: string, codigo?: string, mensaje?: string}
     */
    public function atender(string $instalacionId, string $modulo, string $comercio, ?string $url, ?string $hoy = null): array
    {
        $instalacionId = strtolower(trim($instalacionId));
        $comercio      = trim($comercio);
        $error = ServidorLicencias::validarSolicitud($instalacionId, $modulo, $comercio);
        if ($error !== null) return ['ok' => false, 'codigo' => $error, 'mensaje' => 'Solicitud inválida.'];

        $hoy ??= date('Y-m-d');
        $url = $url !== null && preg_match('#^https?://#i', $url) ? mb_substr($url, 0, 255) : null;

        // Alta o actualización del comercio (cambia de nombre o de dirección sin pedir nada).
        $this->query(
            "INSERT INTO licencias_clientes (instalacion_id, modulo, comercio, url, dispositivos, ultima_solicitud_at)
             VALUES (:i, :m, :c, :u, :d, NOW())
             ON DUPLICATE KEY UPDATE comercio = VALUES(comercio), url = COALESCE(VALUES(url), url), ultima_solicitud_at = NOW()",
            [':i' => $instalacionId, ':m' => $modulo, ':c' => $comercio, ':u' => $url, ':d' => ServidorLicencias::DISPOSITIVOS]
        );
        $cliente = $this->porInstalacion($instalacionId, $modulo);

        switch (ServidorLicencias::decidir($cliente, $hoy)) {
            case 'baja':
                return ['ok' => false, 'codigo' => 'baja',
                        'mensaje' => 'Dewoc Cobros está dado de baja para este comercio. Escribile al proveedor.'];
            case 'reutilizar':
                return ['ok' => true, 'licencia' => (string)$cliente['ultima_licencia']];
        }

        $datos    = ServidorLicencias::datosNuevaLicencia($instalacionId, $modulo, $comercio, (int)$cliente['dispositivos'], $hoy, ServidorLicencias::kid());
        $licencia = ServidorLicencias::firmar($datos);

        $this->query(
            "UPDATE licencias_clientes
                SET emisiones = emisiones + 1, ultima_licencia_id = :lid, ultima_licencia = :txt, ultima_hasta = :hasta
              WHERE id = :id",
            [':lid' => $datos['licencia_id'], ':txt' => $licencia, ':hasta' => $datos['hasta'], ':id' => (int)$cliente['id']]
        );
        logApp("Servidor de licencias: {$datos['licencia_id']} para {$comercio} ({$instalacionId}), {$datos['dispositivos']} celulares hasta {$datos['hasta']}");

        return ['ok' => true, 'licencia' => $licencia];
    }

    public function porInstalacion(string $instalacionId, string $modulo): ?array
    {
        return $this->queryOne(
            "SELECT * FROM licencias_clientes WHERE instalacion_id = :i AND modulo = :m",
            [':i' => strtolower($instalacionId), ':m' => $modulo]
        ) ?: null;
    }

    /** Para el panel del servidor: los que pidieron más recientemente primero. */
    public function listar(): array
    {
        return $this->queryAll(
            "SELECT id, instalacion_id, modulo, comercio, url, dispositivos, estado, emisiones,
                    ultima_licencia_id, ultima_hasta, ultima_solicitud_at, created_at
               FROM licencias_clientes ORDER BY ultima_solicitud_at DESC, id DESC"
        );
    }

    /**
     * Baja o reactivación. La baja no corta la licencia que el comercio ya tiene (funciona sin
     * conexión hasta que vence, C19): solo frena la próxima renovación.
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        if (!in_array($estado, ['activo', 'baja'], true)) return false;
        return $this->query("UPDATE licencias_clientes SET estado = :e WHERE id = :id", [':e' => $estado, ':id' => $id])->rowCount() > 0;
    }

    /**
     * Cambia los celulares de las próximas licencias y fuerza una emisión nueva en la próxima
     * solicitud, para que el comercio los tenga sin esperar a que venza la actual.
     */
    public function cambiarDispositivos(int $id, int $dispositivos): bool
    {
        if ($dispositivos < 1 || $dispositivos > 50) return false;
        return $this->query(
            "UPDATE licencias_clientes SET dispositivos = :d, ultima_licencia = NULL, ultima_hasta = NULL WHERE id = :id",
            [':d' => $dispositivos, ':id' => $id]
        )->rowCount() > 0;
    }
}
