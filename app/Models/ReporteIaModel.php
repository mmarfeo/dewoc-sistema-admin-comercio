<?php

class ReporteIaModel extends Model
{
    protected string $table = 'reportes_ia';

    public function crear(
        ?int    $usuarioId,
        string  $titulo,
        ?string $descripcion,
        string  $tipo,
        ?string $sqlEjecutado,
        array   $parametros,
        array   $columnas,
        array   $resultado,
        string  $origen = 'chat'
    ): int {
        // $usuarioId nullable y $origen: para reportes que genera un script de cron solo,
        // sin que ningún usuario lo haya pedido por chat (ver docs/tareas-pendientes.md #3).
        return $this->insert([
            'usuario_id'    => $usuarioId,
            'origen'        => $origen,
            'titulo'        => $titulo,
            'descripcion'   => $descripcion,
            'tipo'          => $tipo,
            'sql_ejecutado' => $sqlEjecutado,
            'parametros'    => json_encode($parametros, JSON_UNESCAPED_UNICODE),
            'columnas'      => json_encode($columnas, JSON_UNESCAPED_UNICODE),
            'resultado'     => json_encode($resultado, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function listar(int $limite = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.titulo, r.descripcion, r.tipo, r.created_at, u.nombre AS usuario_nombre
             FROM reportes_ia r
             LEFT JOIN usuarios u ON u.id = r.usuario_id
             ORDER BY r.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Última foto guardada de un tipo puntual (ej. 'semanal', 'margen_producto',
     * 'compras_pagos_usuario' — los tipos que arman los cron de docs/tareas-pendientes.md
     * #3). La usa ResumenEjecutivoController para mostrar "lo último de cada reporte
     * automático" sin tener que ir reporte por reporte a /reportes-ia.
     */
    public function obtenerUltimoPorTipo(string $tipo): array|false
    {
        $fila = $this->queryOne(
            'SELECT r.*, u.nombre AS usuario_nombre
             FROM reportes_ia r
             LEFT JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.tipo = :tipo
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 1',
            [':tipo' => $tipo]
        );
        if (!$fila) return false;

        $fila['parametros'] = json_decode($fila['parametros'] ?? '', true) ?? [];
        $fila['columnas']   = json_decode($fila['columnas']   ?? '', true) ?? [];
        $fila['resultado']  = json_decode($fila['resultado']  ?? '', true) ?? [];

        return $fila;
    }

    public function obtener(int $id): array|false
    {
        $fila = $this->queryOne(
            'SELECT r.*, u.nombre AS usuario_nombre
             FROM reportes_ia r
             LEFT JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.id = :id',
            [':id' => $id]
        );
        if (!$fila) return false;

        $fila['parametros'] = json_decode($fila['parametros'] ?? '', true) ?? [];
        $fila['columnas']   = json_decode($fila['columnas']   ?? '', true) ?? [];
        $fila['resultado']  = json_decode($fila['resultado']  ?? '', true) ?? [];

        return $fila;
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM reportes_ia WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
