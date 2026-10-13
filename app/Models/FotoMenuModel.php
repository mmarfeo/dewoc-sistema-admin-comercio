<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Fotos del menú online (ver docs/integracion-menuapp-checklist.md, etapa 8). Vive sobre
 * la misma tabla `galeria_archivos` que la Galería: una foto del menú es una foto de la
 * Galería con `producto_id` y `estado_menu` cargados.
 *
 * El circuito es: un empleado sube hasta 3 **candidatas** por producto (sacadas con la
 * cámara o mejoradas con IA), y alguien con permiso de publicar elige la **oficial**, que
 * es la única que se copia a uploads/productos/ y llega al cliente. La oficial anterior
 * queda como **anterior** para poder volver atrás, y lo que no sirve queda **descartada**
 * (no se borra del disco en el momento: lo limpia un cron a los 30 días).
 */
class FotoMenuModel extends Model
{
    protected string $table = 'galeria_archivos';

    /** Tope de candidatas por producto. La oficial NO cuenta (ver tarea 8.5). */
    const MAX_CANDIDATAS = 3;

    /** Días que sobrevive una descartada antes de que el cron pueda limpiarla. */
    const DIAS_RETENCION_DESCARTADAS = 30;

    // ── Listado de productos del menú (tarea 8.2) ───────────────────────────

    /**
     * Productos marcados para el menú online con el conteo de sus fotos. Ordenados
     * poniendo PRIMERO los que no tienen foto oficial: es la lista de lo que falta hacer,
     * no un catálogo más.
     *
     * @param string $busqueda  Nombre o código (opcional)
     * @param int    $categoria ID de categoría, 0 = todas
     * @param bool   $soloConCandidatas Filtro "Con candidatas para revisar" del encargado
     */
    public function productosDelMenu(string $busqueda = '', int $categoria = 0, bool $soloConCandidatas = false): array
    {
        $where  = ["p.ecommerce = 1", "p.activo = 1", "p.eliminado = 0"];
        $params = [];

        if (trim($busqueda) !== '') {
            $where[] = "(p.nombre LIKE :q OR p.codigo LIKE :q2)";
            $params[':q']  = '%' . trim($busqueda) . '%';
            $params[':q2'] = '%' . trim($busqueda) . '%';
        }
        if ($categoria > 0) {
            $where[] = "p.categoria_id = :cat";
            $params[':cat'] = $categoria;
        }

        $having = $soloConCandidatas ? 'HAVING candidatas > 0' : '';
        $cond   = implode(' AND ', $where);

        return $this->queryAll(
            "SELECT p.id, p.nombre, p.codigo, p.imagen, p.categoria_id,
                    c.nombre AS categoria_nombre,
                    SUM(CASE WHEN g.estado_menu = 'candidata' THEN 1 ELSE 0 END) AS candidatas,
                    SUM(CASE WHEN g.estado_menu = 'oficial'   THEN 1 ELSE 0 END) AS oficiales,
                    SUM(CASE WHEN g.estado_menu = 'anterior'  THEN 1 ELSE 0 END) AS anteriores
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN galeria_archivos g
                    ON g.producto_id = p.id AND g.eliminado = 0 AND g.tipo = 'imagen'
             WHERE {$cond}
             GROUP BY p.id, p.nombre, p.codigo, p.imagen, p.categoria_id, c.nombre
             {$having}
             ORDER BY (p.imagen IS NULL OR p.imagen = '') DESC, candidatas DESC, p.nombre",
            $params
        );
    }

    /** Categorías de producto con al menos un producto en el menú, para el filtro. */
    public function categoriasDelMenu(): array
    {
        return $this->queryAll(
            "SELECT DISTINCT c.id, c.nombre
             FROM categorias c
             JOIN productos p ON p.categoria_id = c.id
             WHERE p.ecommerce = 1 AND p.activo = 1 AND p.eliminado = 0 AND c.eliminado = 0
             ORDER BY c.nombre"
        );
    }

    // ── Fotos de un producto (tarea 8.3) ────────────────────────────────────

    /** Producto con lo que hace falta para dibujar la vista previa del menú. */
    public function producto(int $productoId): ?array
    {
        return $this->queryOne(
            "SELECT p.id, p.nombre, p.descripcion, p.codigo, p.imagen,
                    p.precio_venta, p.precio_online, p.iva,
                    p.controla_stock, p.stock_actual, p.ecommerce, p.activo,
                    c.nombre AS categoria_nombre
             FROM productos p
             LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = :id AND p.eliminado = 0",
            [':id' => $productoId]
        ) ?: null;
    }

    /** Fotos de un producto en un estado dado, más nuevas primero. */
    public function fotosPorEstado(int $productoId, string $estado): array
    {
        return $this->queryAll(
            "SELECT * FROM galeria_archivos
             WHERE producto_id = :pid AND estado_menu = :e AND eliminado = 0 AND tipo = 'imagen'
             ORDER BY created_at DESC",
            [':pid' => $productoId, ':e' => $estado]
        );
    }

    public function candidatas(int $productoId): array
    {
        return $this->fotosPorEstado($productoId, 'candidata');
    }

    public function oficial(int $productoId): ?array
    {
        return $this->fotosPorEstado($productoId, 'oficial')[0] ?? null;
    }

    public function anterior(int $productoId): ?array
    {
        return $this->fotosPorEstado($productoId, 'anterior')[0] ?? null;
    }

    // ── Tope de candidatas (tarea 8.5) ──────────────────────────────────────

    public function contarCandidatas(int $productoId): int
    {
        return (int)$this->queryScalar(
            "SELECT COUNT(*) FROM galeria_archivos
             WHERE producto_id = :pid AND estado_menu = 'candidata' AND eliminado = 0",
            [':pid' => $productoId]
        );
    }

    /**
     * ¿Hay lugar para otra candidata? Se consulta SIEMPRE en el servidor antes de subir o
     * de mejorar con IA, no solo para deshabilitar los botones: si no, bastaba con llamar
     * al endpoint a mano para gastar IA sin límite.
     */
    public function hayLugarParaCandidata(int $productoId): bool
    {
        return $this->contarCandidatas($productoId) < self::MAX_CANDIDATAS;
    }

    // ── Alta de candidatas (tareas 8.4 y 8.6) ───────────────────────────────

    /**
     * Registra una foto ya guardada en storage/galeria/ como candidata de un producto.
     *
     * @param int|null $origenId Foto de la que salió, si es una mejora con IA
     */
    public function registrarCandidata(array $archivo, int $productoId, int $usuarioId, bool $mejoradaIa = false, ?int $origenId = null): int
    {
        return $this->insert([
            'usuario_id'      => $usuarioId,
            'producto_id'     => $productoId,
            'estado_menu'     => 'candidata',
            'mejorada_ia'     => $mejoradaIa ? 1 : 0,
            'origen_id'       => $origenId,
            'tipo'            => 'imagen',
            'origen'          => $mejoradaIa ? 'generado_ia' : 'subido',
            'proveedor_ia'    => $mejoradaIa ? 'gemini' : null,
            'nombre_archivo'  => $archivo['nombre'],
            'nombre_original' => $archivo['nombre_original'] ?? null,
            'mime'            => $archivo['mime'],
            'tamano_bytes'    => $archivo['tamano'],
            'estado'          => 'listo',
        ]);
    }

    /** Foto de la galería que pertenece a este producto, o null. */
    public function fotoDeProducto(int $fotoId, int $productoId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM galeria_archivos
             WHERE id = :id AND producto_id = :pid AND eliminado = 0 AND tipo = 'imagen'",
            [':id' => $fotoId, ':pid' => $productoId]
        ) ?: null;
    }

    // ── Publicar / descartar / volver atrás (tareas 8.5 y 8.7) ──────────────

    /**
     * Pasa una candidata a oficial, en una transacción:
     * la oficial que había queda como `anterior` (es lo que hace posible "Volver a la foto
     * anterior"), la que ya era `anterior` pasa a `descartada` para no acumular historial
     * infinito, y recién entonces la candidata queda `oficial`.
     *
     * Solo cambia los estados: copiar el archivo a uploads/ y actualizar productos.imagen
     * lo hace el controlador, que es el que sabe normalizar la imagen y avisarle a MenuApp.
     */
    public function marcarOficial(int $fotoId, int $productoId): void
    {
        $conn = Database::getInstance();
        $conn->beginTransaction();
        try {
            $conn->prepare(
                "UPDATE galeria_archivos SET estado_menu = 'descartada'
                 WHERE producto_id = :pid AND estado_menu = 'anterior' AND eliminado = 0"
            )->execute([':pid' => $productoId]);

            $conn->prepare(
                "UPDATE galeria_archivos SET estado_menu = 'anterior'
                 WHERE producto_id = :pid AND estado_menu = 'oficial' AND eliminado = 0"
            )->execute([':pid' => $productoId]);

            $conn->prepare(
                "UPDATE galeria_archivos SET estado_menu = 'oficial'
                 WHERE id = :id AND producto_id = :pid"
            )->execute([':id' => $fotoId, ':pid' => $productoId]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Descarta una candidata. No borra el archivo del disco: queda en estado `descartada`
     * para que el cron de limpieza lo saque a los 30 días. Así un descarte por error se
     * puede recuperar a mano desde la base.
     */
    public function descartar(int $fotoId, int $productoId): void
    {
        $this->query(
            "UPDATE galeria_archivos SET estado_menu = 'descartada'
             WHERE id = :id AND producto_id = :pid AND estado_menu = 'candidata'",
            [':id' => $fotoId, ':pid' => $productoId]
        );
    }

    /** Vuelve la foto `anterior` a `candidata`, para poder volver a publicarla. */
    public function recuperarAnteriorComoCandidata(int $productoId): ?array
    {
        $anterior = $this->anterior($productoId);
        if (!$anterior) return null;

        $this->query(
            "UPDATE galeria_archivos SET estado_menu = 'candidata' WHERE id = :id",
            [':id' => (int)$anterior['id']]
        );
        return $anterior;
    }

    // ── Límite diario de IA (tarea 8.9) ─────────────────────────────────────

    /** Mejoras con IA que este usuario hizo hoy, para el tope diario. */
    public function mejorasIaDeHoy(int $usuarioId): int
    {
        return (int)$this->queryScalar(
            "SELECT COUNT(*) FROM galeria_archivos
             WHERE usuario_id = :uid AND mejorada_ia = 1 AND DATE(created_at) = :hoy",
            [':uid' => $usuarioId, ':hoy' => date('Y-m-d')]
        );
    }

    // ── Limpieza (tarea 8.5) ────────────────────────────────────────────────

    /** Descartadas con más de DIAS_RETENCION_DESCARTADAS días, para que el cron las borre. */
    public function descartadasParaLimpiar(): array
    {
        // La cantidad de días va interpolada y no como placeholder: MySQL no acepta un
        // parámetro bindeado dentro de INTERVAL. Es una constante entera de la clase, así
        // que no hay nada que un usuario pueda inyectar acá.
        $dias = (int)self::DIAS_RETENCION_DESCARTADAS;
        return $this->queryAll(
            "SELECT id, nombre_archivo FROM galeria_archivos
             WHERE estado_menu = 'descartada' AND eliminado = 0
               AND created_at < DATE_SUB(NOW(), INTERVAL {$dias} DAY)"
        );
    }
}
