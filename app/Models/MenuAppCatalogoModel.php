<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Consultas del catálogo y la disponibilidad que consume MenuApp (ver
 * docs/integracion-menuapp-checklist.md, secciones 3.1 y 3.2).
 *
 * "Está en el menú online" = productos.ecommerce = 1 (decisión D2: se reutiliza la columna
 * que antes significaba "publicar en la tienda de Dewoc"; la columna NO se renombra, solo
 * cambia lo que dice la interfaz).
 *
 * Nada de lógica de precios acá: el precio final lo arma PrecioHelper a partir de
 * precio_online/precio_venta + iva, en un solo lugar (ver la vista del controlador).
 */
class MenuAppCatalogoModel extends Model
{
    protected string $table = 'productos';

    /** WHERE compartido: lo que se publica en el menú online. */
    private const COND_PUBLICADO = "p.ecommerce = 1 AND p.activo = 1 AND p.eliminado = 0";

    /**
     * Productos del menú online. Con $desde devuelve ADEMÁS los que cambiaron desde esa
     * fecha aunque ya no estén publicados (desmarcados, desactivados o eliminados), para
     * que MenuApp pueda ocultarlos — esos vienen con `publicado = 0` y el controlador los
     * emite con `visible: false`.
     */
    public function productos(?string $desde = null): array
    {
        $params = [];
        if ($desde === null) {
            $cond = self::COND_PUBLICADO;
        } else {
            // "Cambió desde": o sigue publicado y se tocó después de $desde, o ya no está
            // publicado pero se tocó después de $desde (justamente el caso a ocultar).
            $cond = "p.updated_at >= :desde";
            $params[':desde'] = $desde;
        }

        return $this->queryAll(
            "SELECT p.id, p.codigo, p.nombre, p.descripcion, p.categoria_id,
                    p.precio_venta, p.precio_online, p.iva, p.imagen,
                    p.controla_stock, p.stock_actual, p.tipo,
                    p.ecommerce, p.activo, p.eliminado, p.updated_at,
                    (" . self::COND_PUBLICADO . ") AS publicado
             FROM productos p
             WHERE {$cond}
             ORDER BY p.nombre",
            $params
        );
    }

    /**
     * Categorías de los productos recibidos, con el nombre de su categoría padre.
     * MenuApp tiene un solo nivel, así que el padre va solo como etiqueta informativa.
     */
    public function categoriasDe(array $categoriaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $categoriaIds))));
        if (!$ids) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->queryAll(
            "SELECT c.id, c.nombre, c.padre_id, pc.nombre AS padre_nombre
             FROM categorias c
             LEFT JOIN categorias pc ON pc.id = c.padre_id
             WHERE c.id IN ({$in}) AND c.eliminado = 0
             ORDER BY c.nombre",
            $ids
        );
    }

    /**
     * Grupos de opciones de cada producto, con sus opciones ACTIVAS. Devuelve
     * [producto_id => [grupo, ...]]. Los límites (mínimo/máximo/obligatorio) salen de
     * producto_grupos_opciones, no del grupo: el mismo grupo se reutiliza entre productos
     * con límites distintos (ej. "Gustos" permite 4 en el 1kg y 1 en el cucurucho).
     */
    public function gruposPorProducto(array $productoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productoIds))));
        if (!$ids) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->queryAll(
            "SELECT pgo.producto_id, pgo.grupo_id, pgo.minimo_selecciones, pgo.maximo_selecciones,
                    pgo.obligatorio, pgo.orden,
                    g.nombre AS grupo_nombre, g.tipo_seleccion, g.permite_repetir
             FROM producto_grupos_opciones pgo
             JOIN grupos_opciones g ON g.id = pgo.grupo_id
             WHERE pgo.producto_id IN ({$in}) AND g.activo = 1
             ORDER BY pgo.producto_id, pgo.orden, g.nombre",
            $ids
        );
        if (!$filas) return [];

        $opcionesPorGrupo = $this->opcionesPorGrupo(array_column($filas, 'grupo_id'));

        $out = [];
        foreach ($filas as $f) {
            $out[(int)$f['producto_id']][] = [
                'id'              => (int)$f['grupo_id'],
                'nombre'          => $f['grupo_nombre'],
                'minimo'          => (int)$f['minimo_selecciones'],
                'maximo'          => (int)$f['maximo_selecciones'],
                'obligatorio'     => (bool)$f['obligatorio'],
                'tipo_seleccion'  => $f['tipo_seleccion'],
                'permite_repetir' => (bool)$f['permite_repetir'],
                'orden'           => (int)$f['orden'],
                'opciones'        => $opcionesPorGrupo[(int)$f['grupo_id']] ?? [],
            ];
        }
        return $out;
    }

    /** Opciones activas agrupadas por grupo_id. */
    private function opcionesPorGrupo(array $grupoIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $grupoIds))));
        if (!$ids) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $filas = $this->queryAll(
            "SELECT o.id, o.grupo_id, o.nombre, o.orden, o.activo
             FROM opciones o
             WHERE o.grupo_id IN ({$in}) AND o.activo = 1
             ORDER BY o.grupo_id, o.orden, o.nombre",
            $ids
        );

        $out = [];
        foreach ($filas as $f) {
            $out[(int)$f['grupo_id']][] = [
                'id'     => (int)$f['id'],
                'nombre' => $f['nombre'],
                'orden'  => (int)$f['orden'],
                'activa' => true,
            ];
        }
        return $out;
    }

    /**
     * Fecha del archivo de imagen, para `imagen_version`: MenuApp solo vuelve a descargar
     * la imagen si ese valor cambió. Se usa el filemtime del archivo y no el updated_at
     * del producto, que se mueve con cualquier edición de precio o nombre.
     */
    public function versionImagen(?string $imagen, string $fallback): string
    {
        if (!$imagen) return $fallback;
        $ruta = UPLOAD_PATH . '/productos/' . $imagen;
        $mtime = @filemtime($ruta);
        return $mtime ? date('c', $mtime) : $fallback;
    }

    /**
     * Todas las opciones activas de los grupos usados por productos publicados, con su
     * vínculo de stock — insumo de /disponibilidad.
     */
    public function opcionesPublicadas(): array
    {
        return $this->queryAll(
            "SELECT DISTINCT o.id, o.nombre, o.grupo_id, o.materia_prima_id, o.insumo_id,
                    o.producto_id, o.cantidad_consumida,
                    mp.ingrediente_generico_id, pr.controla_stock AS producto_controla_stock
             FROM opciones o
             JOIN producto_grupos_opciones pgo ON pgo.grupo_id = o.grupo_id
             JOIN productos p                  ON p.id = pgo.producto_id
             LEFT JOIN materias_primas mp      ON mp.id = o.materia_prima_id
             LEFT JOIN productos pr            ON pr.id = o.producto_id
             WHERE o.activo = 1 AND " . self::COND_PUBLICADO . "
             ORDER BY o.grupo_id, o.orden, o.nombre"
        );
    }

    /** Receta propia de un producto "no controla stock", o null — espejo de VentaModel. */
    public function recetaDeProductoNoControlado(int $productoId): ?int
    {
        $id = $this->queryScalar(
            "SELECT r.id FROM productos p
             JOIN recetas r ON r.producto_id = p.id AND r.eliminado = 0
             WHERE p.id = :pid AND p.controla_stock = 0 LIMIT 1",
            [':pid' => $productoId]
        );
        return $id ? (int)$id : null;
    }

    /** Producto por id (para POST /ventas), con lo que hace falta para armar la línea. */
    public function productoParaVenta(int $id): ?array
    {
        return $this->queryOne(
            "SELECT id, nombre, precio_venta, precio_online, iva, controla_stock, ecommerce, activo
             FROM productos WHERE id = :id AND eliminado = 0",
            [':id' => $id]
        ) ?: null;
    }
}
