<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Celulares vinculados a Dewoc Cobros (ver docs/dewoc-cobros-checklist.md, C9 y C18).
 *
 * Del token Bearer se guarda SOLO el hash: el valor en claro se muestra una única vez, en
 * el QR de Integraciones → Dewoc Cobros. Revocar no borra la fila (queda el historial).
 *
 * Las fechas se escriben desde PHP y no con NOW(): el MySQL de Hostinger está en UTC y la
 * app en la hora del comercio, y mezclarlas corre las horas de último contacto.
 */
class CobroDispositivoModel extends Model
{
    protected string $table = 'cobros_dispositivos';

    /** Requests por minuto que acepta la API de cada celular antes de responder 429. */
    const RATE_LIMITE_POR_MINUTO = 60;

    /**
     * Da de alta un celular y devuelve ['id' => …, 'token' => …] con el token EN CLARO,
     * que es la única vez que existe fuera del hash.
     */
    public function crear(string $nombre, ?int $usuarioId): array
    {
        $token = 'dc_' . randomToken(24);
        $this->query(
            "INSERT INTO cobros_dispositivos (nombre, token_hash, token_ultimos4, activo, creado_por, created_at)
             VALUES (:n, :h, :u4, 1, :usr, :ahora)",
            [
                ':n'     => mb_substr($nombre, 0, 80),
                ':h'     => hash('sha256', $token),
                ':u4'    => substr($token, -4),
                ':usr'   => $usuarioId,
                ':ahora' => date('Y-m-d H:i:s'),
            ]
        );
        return ['id' => (int)$this->db->lastInsertId(), 'token' => $token];
    }

    /**
     * Celular activo dueño del token, o null (token inexistente o revocado: los dos son 401).
     * Se busca por el hash: el token tiene 192 bits al azar, así que su SHA-256 no se puede
     * adivinar ni sacar por timing.
     */
    public function porToken(string $token): ?array
    {
        if ($token === '') return null;
        return $this->queryOne(
            "SELECT * FROM cobros_dispositivos WHERE token_hash = :h AND activo = 1",
            [':h' => hash('sha256', $token)]
        ) ?: null;
    }

    public function registrarContacto(int $id): void
    {
        $this->query(
            "UPDATE cobros_dispositivos SET ultimo_contacto_at = :ahora WHERE id = :id",
            [':ahora' => date('Y-m-d H:i:s'), ':id' => $id]
        );
    }

    /** Estado que informa el celular en cada latido (sección 3.3). Un null no pisa el valor anterior. */
    public function registrarLatido(int $id, array $estado): void
    {
        $this->query(
            "UPDATE cobros_dispositivos
                SET version_app            = COALESCE(:ver, version_app),
                    permiso_notificaciones = COALESCE(:perm, permiso_notificaciones),
                    optimizacion_bateria   = COALESCE(:bat, optimizacion_bateria),
                    pendientes_en_cola     = COALESCE(:cola, pendientes_en_cola),
                    ultimo_contacto_at     = :ahora
              WHERE id = :id",
            [
                ':ver'   => $estado['version_app'],
                ':perm'  => $estado['permiso_notificaciones'],
                ':bat'   => $estado['optimizacion_bateria'],
                ':cola'  => $estado['pendientes_en_cola'],
                ':ahora' => date('Y-m-d H:i:s'),
                ':id'    => $id,
            ]
        );
    }

    /** Todos los celulares, los activos primero y en orden de vinculación. */
    public function listar(): array
    {
        return $this->queryAll(
            "SELECT id, nombre, token_ultimos4, activo, version_app, permiso_notificaciones,
                    optimizacion_bateria, pendientes_en_cola, ultimo_contacto_at, revocado_at, created_at
               FROM cobros_dispositivos ORDER BY activo DESC, id"
        );
    }

    public function cantidadActivos(): int
    {
        return (int)$this->queryScalar("SELECT COUNT(*) FROM cobros_dispositivos WHERE activo = 1");
    }

    /** Revoca un celular: su token deja de valer al instante. Devuelve false si ya no estaba activo. */
    public function revocar(int $id): bool
    {
        return $this->query(
            "UPDATE cobros_dispositivos SET activo = 0, revocado_at = :ahora WHERE id = :id AND activo = 1",
            [':ahora' => date('Y-m-d H:i:s'), ':id' => $id]
        )->rowCount() > 0;
    }

    /**
     * Si el celular entra en la licencia (C18): con N celulares incluidos funcionan los N
     * activos MÁS VIEJOS; el resto queda "pausado por límite" hasta que se revoque alguno o
     * se amplíe la licencia.
     */
    public function dentroDelLimite(int $id, int $limite): bool
    {
        if ($limite <= 0) return false;
        $anteriores = (int)$this->queryScalar(
            "SELECT COUNT(*) FROM cobros_dispositivos WHERE activo = 1 AND id < :id",
            [':id' => $id]
        );
        return $anteriores < $limite;
    }

    /**
     * Contador por dispositivo y por minuto. Devuelve false si se pasó del límite. De paso
     * borra las ventanas viejas, así la tabla no crece.
     */
    public function consumirRateLimit(int $id): bool
    {
        $ventana = date('Y-m-d H:i');

        $this->query("DELETE FROM cobros_rate WHERE ventana < :v", [':v' => $ventana]);
        $this->query(
            "INSERT INTO cobros_rate (dispositivo_id, ventana, requests) VALUES (:id, :v, 1)
             ON DUPLICATE KEY UPDATE requests = requests + 1",
            [':id' => $id, ':v' => $ventana]
        );

        $usadas = (int)$this->queryScalar(
            "SELECT requests FROM cobros_rate WHERE dispositivo_id = :id AND ventana = :v",
            [':id' => $id, ':v' => $ventana]
        );
        return $usadas <= self::RATE_LIMITE_POR_MINUTO;
    }
}
