<?php

class ChatModel extends Model
{
    protected string $table = 'chat_mensajes';

    public function guardarMensaje(
        int     $usuarioId,
        string  $rol,
        string  $contenido,
        ?string $sqlEjecutado   = null,
        ?string $datosJson      = null,
        ?string $archivoAdjunto = null,
        ?string $archivoMime    = null
    ): int {
        // Una sola conexión para el INSERT y el lastInsertId (si se pidieran por separado y
        // Database::getInstance() reconectara sola en el medio, lastInsertId() preguntaría a
        // una conexión que nunca insertó nada y devolvería 0).
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO chat_mensajes (usuario_id, rol, contenido, sql_ejecutado, datos_json, archivo_adjunto, archivo_mime, created_at)
             VALUES (:uid, :rol, :cont, :sql, :datos, :archivo, :mime, :created)'
        );
        $stmt->execute([
            'uid'     => $usuarioId,
            'rol'     => $rol,
            'cont'    => $contenido,
            'sql'     => $sqlEjecutado,
            'datos'   => $datosJson,
            'archivo' => $archivoAdjunto,
            'mime'    => $archivoMime,
            // Se fija explícito en vez de confiar en el DEFAULT CURRENT_TIMESTAMP de MySQL:
            // el servidor de base de datos puede estar en otra zona horaria (ej. UTC) mientras
            // que PHP ya está configurado en TIMEZONE (config.php) — mismo criterio que ya usa
            // VentasController::registrar() para las ventas.
            'created' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public function getHistorial(int $usuarioId, int $limite = 30): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, rol, contenido, sql_ejecutado, datos_json, archivo_adjunto, archivo_mime, created_at
             FROM chat_mensajes
             WHERE usuario_id = :uid
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite,    PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll());
    }

    /** Trae un mensaje puntual del usuario (para resolver una tarjeta de confirmación pendiente). */
    public function getMensaje(int $mensajeId, int $usuarioId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, rol, contenido, datos_json, archivo_adjunto, archivo_mime
             FROM chat_mensajes
             WHERE id = :id AND usuario_id = :uid
             LIMIT 1'
        );
        $stmt->execute(['id' => $mensajeId, 'uid' => $usuarioId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Trae un mensaje por id sin filtrar por dueño (para servir un adjunto: el
     * control de "puede verlo" —dueño o admin— lo hace el controller).
     */
    public function getMensajePorId(int $mensajeId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, usuario_id, archivo_adjunto, archivo_mime
             FROM chat_mensajes
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $mensajeId]);
        return $stmt->fetch() ?: null;
    }

    /** Ventana de "todavía cuenta como pendiente" para getUltimaTarjetaPendiente() — ver ahí el porqué. */
    private const HORAS_TARJETA_PENDIENTE_VIGENTE = 3;

    /**
     * Última tarjeta de confirmación (compra o acción) del usuario que sigue "pendiente" —
     * o null si no hay ninguna sin resolver. actualizarDatosJson() pisa el mismo registro al
     * confirmar/cancelar, así que basta con mirar el mensaje con datos_json más reciente.
     *
     * Solo cuenta si es de las últimas HORAS_TARJETA_PENDIENTE_VIGENTE horas — sin esto, una
     * tarjeta que el usuario simplemente nunca resolvió (ej. el aviso diario de Lista de
     * Compras del cron, o cualquier otra acción que dejó "para después" y se olvidó) bloqueaba
     * TODA subida de comprobantes nuevos para siempre, sin ninguna forma obvia de destrabarlo
     * salvo scrollear todo el historial a mano hasta encontrarla (bug reportado: "error
     * continuo" en el chat). Pasada la ventana, se ignora acá — su propia tarjeta en el
     * historial sigue funcionando igual si el usuario la encuentra y la confirma/cancela.
     */
    public function getUltimaTarjetaPendiente(int $usuarioId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, datos_json
             FROM chat_mensajes
             WHERE usuario_id = :uid AND datos_json IS NOT NULL
               AND created_at >= (NOW() - INTERVAL :horas HOUR)
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['uid' => $usuarioId, 'horas' => self::HORAS_TARJETA_PENDIENTE_VIGENTE]);
        $fila = $stmt->fetch();
        if (!$fila) return null;

        $datos = json_decode($fila['datos_json'], true);
        if (!$datos || ($datos['estado'] ?? '') !== 'pendiente') return null;

        return ['id' => (int) $fila['id'], 'datos' => $datos];
    }

    /** Actualiza el payload de una tarjeta de confirmación (ej. para marcarla confirmada/cancelada). */
    public function actualizarDatosJson(int $mensajeId, int $usuarioId, array $datos): void
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE chat_mensajes SET datos_json = :datos WHERE id = :id AND usuario_id = :uid'
        );
        $stmt->execute([
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'id'    => $mensajeId,
            'uid'   => $usuarioId,
        ]);
    }

    public function limpiarHistorial(int $usuarioId): void
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM chat_mensajes WHERE usuario_id = :uid');
        $stmt->execute(['uid' => $usuarioId]);
    }

    /**
     * Ejecuta una consulta SELECT después de validarla.
     * Agrega LIMIT $limite automáticamente si no viene especificado.
     */
    public function ejecutarConsulta(string $sql, int $limite = 50): array
    {
        $sqlTrim = trim($sql);

        if (!preg_match('/^SELECT\s/i', $sqlTrim)) {
            throw new \InvalidArgumentException('Solo se permiten consultas SELECT.');
        }

        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER',
                      'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE', 'LOAD', 'INTO OUTFILE'];
        foreach ($forbidden as $kw) {
            // \b evita falsos positivos como "CREATE" dentro de la columna "created_at"
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sqlTrim)) {
                throw new \InvalidArgumentException("Operación no permitida en consulta SQL: $kw");
            }
        }

        // Verificar que solo accede a tablas del whitelist (busca nombres luego de FROM/JOIN)
        preg_match_all('/\b(?:FROM|JOIN)\s+`?(\w+)`?/i', $sqlTrim, $matchesTablas);
        $tablasUsadas = array_unique(array_map('strtolower', $matchesTablas[1] ?? []));
        $tablasPermitidas = array_map('strtolower', MCP_TABLAS_OK);
        foreach ($tablasUsadas as $tabla) {
            if (!in_array($tabla, $tablasPermitidas, true)) {
                throw new \InvalidArgumentException("Acceso no permitido a la tabla: $tabla");
            }
        }

        // Agregar LIMIT si no viene
        if (!preg_match('/LIMIT\s+\d+/i', $sqlTrim)) {
            $sqlTrim .= ' LIMIT ' . $limite;
        }

        $stmt = Database::getInstance()->query($sqlTrim);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve el esquema de las tablas habilitadas para el contexto del modelo IA.
     */
    public function getEsquemaTablas(): array
    {
        $tablasOk = MCP_TABLAS_OK;
        $in  = implode(',', array_fill(0, count($tablasOk), '?'));
        $stmt = Database::getInstance()->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($in)
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );
        $stmt->execute($tablasOk);
        $rows = $stmt->fetchAll();

        $esquema = [];
        foreach ($rows as $r) {
            $esquema[$r['TABLE_NAME']][] = $r['COLUMN_NAME'] . ' (' . $r['DATA_TYPE'] . ')';
        }
        return $esquema;
    }
}
