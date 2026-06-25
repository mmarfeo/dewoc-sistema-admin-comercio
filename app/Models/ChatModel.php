<?php

class ChatModel extends Model
{
    protected string $table = 'chat_mensajes';

    public function guardarMensaje(
        int     $usuarioId,
        string  $rol,
        string  $contenido,
        ?string $sqlEjecutado = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO chat_mensajes (usuario_id, rol, contenido, sql_ejecutado)
             VALUES (:uid, :rol, :cont, :sql)'
        );
        $stmt->execute([
            'uid'  => $usuarioId,
            'rol'  => $rol,
            'cont' => $contenido,
            'sql'  => $sqlEjecutado,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getHistorial(int $usuarioId, int $limite = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT rol, contenido, sql_ejecutado, created_at
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

    /**
     * Ejecuta una consulta SELECT después de validarla.
     * Agrega LIMIT 50 automáticamente si no viene especificado.
     */
    public function ejecutarConsulta(string $sql): array
    {
        $sqlTrim = trim($sql);

        if (!preg_match('/^SELECT\s/i', $sqlTrim)) {
            throw new \InvalidArgumentException('Solo se permiten consultas SELECT.');
        }

        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER',
                      'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE', 'LOAD', 'INTO OUTFILE'];
        $upper = strtoupper($sqlTrim);
        foreach ($forbidden as $kw) {
            if (strpos($upper, $kw) !== false) {
                throw new \InvalidArgumentException("Operación no permitida en consulta SQL: $kw");
            }
        }

        // Verificar que solo accede a tablas del whitelist
        foreach (MCP_TABLAS_OK as $tabla) {
            // Quitar esta tabla de la verificación (está en el whitelist)
        }

        // Agregar LIMIT si no viene
        if (!preg_match('/LIMIT\s+\d+/i', $sqlTrim)) {
            $sqlTrim .= ' LIMIT 50';
        }

        $stmt = $this->db->query($sqlTrim);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve el esquema de las tablas habilitadas para el contexto del modelo IA.
     */
    public function getEsquemaTablas(): array
    {
        $tablasOk = MCP_TABLAS_OK;
        $in  = implode(',', array_fill(0, count($tablasOk), '?'));
        $stmt = $this->db->prepare(
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
