-- ============================================================
-- "DULCE DE LECHE" — VERIFICACIÓN DE VALORES HISTÓRICOS — SOLO LECTURA
-- ============================================================
-- Ninguna consulta de este archivo modifica datos. Busca, en productos,
-- materias primas e insumos, todo lo que tenga "dulce de leche" en el
-- nombre (LIKE, no distingue mayúsculas) y muestra:
--   1. Ficha actual (costo/precio, stock, desvío) de cada ítem.
--   2. Historial completo de compras de esos ítems (si son MP o insumo),
--      para detectar precios cargados mal (cero de más/de menos, precio
--      total en vez de unitario, etc.) igual que en diagnostico_costos_mp.sql.
--   3. Outliers dentro del propio historial de cada ítem (precio que se
--      aleja más de 3x del promedio del mismo ítem).
-- ============================================================


-- ── 1. Ficha actual de todo lo que contenga "dulce de leche" ──────
SELECT * FROM (
    SELECT 'producto' AS tipo, p.id, p.nombre, p.codigo,
           p.precio_costo AS costo_o_precio, NULL AS desvio_pct,
           p.stock_actual, p.activo, p.eliminado
    FROM productos p
    WHERE p.nombre LIKE '%dulce de leche%'

    UNION ALL

    SELECT 'materia_prima' AS tipo, mp.id, mp.nombre, mp.codigo,
           mp.costo_unitario AS costo_o_precio, mp.desvio_pct,
           mp.stock_actual, mp.activo, mp.eliminado
    FROM materias_primas mp
    WHERE mp.nombre LIKE '%dulce de leche%'

    UNION ALL

    SELECT 'insumo' AS tipo, i.id, i.nombre, i.codigo,
           i.costo_unitario AS costo_o_precio, NULL AS desvio_pct,
           i.stock_actual, i.activo, i.eliminado
    FROM insumos i
    WHERE i.nombre LIKE '%dulce de leche%'
) t
ORDER BY tipo, nombre;


-- ── 2. Historial de compras de esos ítems (materia_prima / insumo) ──
-- Cada fila es una línea de factura de compra real. Repasar precio_unitario
-- y cantidad_contenida columna por columna para encontrar el valor cargado mal.
SELECT
    fc.id AS factura_id, fc.fecha, fc.tipo_comprobante, fc.nro_comprobante,
    fc.estado, pr.razon_social AS proveedor,
    it.tipo_item, it.item_id, it.nombre AS item_nombre,
    fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario,
    ROUND(fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0), 4) AS precio_por_unidad_base,
    fcd.subtotal_con_iva
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN proveedores pr ON pr.id = fc.proveedor_id
JOIN (
    SELECT 'materia_prima' AS tipo_item, mp.id AS item_id, mp.nombre
    FROM materias_primas mp WHERE mp.nombre LIKE '%dulce de leche%'
    UNION ALL
    SELECT 'insumo' AS tipo_item, i.id AS item_id, i.nombre
    FROM insumos i WHERE i.nombre LIKE '%dulce de leche%'
) it ON it.tipo_item = fcd.tipo_item
    AND it.item_id = COALESCE(fcd.materia_prima_id, fcd.insumo_id)
WHERE fc.eliminado = 0
ORDER BY it.nombre, fc.fecha DESC;


-- ── 3. Outliers dentro del historial de cada ítem (>3x o <1/3 del promedio) ──
-- Mismo criterio que la consulta #3 de diagnostico_costos_mp.sql, pero
-- restringido a los ítems de "dulce de leche" y sumando insumos además de MP.
SELECT
    fc.id AS factura_id, fc.fecha, fc.nro_comprobante,
    it.tipo_item, it.item_id, it.nombre,
    fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario,
    ROUND(fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0), 4) AS precio_por_unidad_base,
    ROUND(prom.precio_prom_base, 4) AS precio_promedio_base_del_item,
    ROUND(
        (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) / NULLIF(prom.precio_prom_base,0)
    , 2) AS veces_vs_promedio
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN (
    SELECT 'materia_prima' AS tipo_item, mp.id AS item_id, mp.nombre
    FROM materias_primas mp WHERE mp.nombre LIKE '%dulce de leche%'
    UNION ALL
    SELECT 'insumo' AS tipo_item, i.id AS item_id, i.nombre
    FROM insumos i WHERE i.nombre LIKE '%dulce de leche%'
) it ON it.tipo_item = fcd.tipo_item
    AND it.item_id = COALESCE(fcd.materia_prima_id, fcd.insumo_id)
JOIN (
    SELECT fcd2.tipo_item, COALESCE(fcd2.materia_prima_id, fcd2.insumo_id) AS item_id,
           AVG(fcd2.precio_unitario / NULLIF(COALESCE(fcd2.cantidad_contenida,1),0)) AS precio_prom_base
    FROM facturas_compra_detalle fcd2
    JOIN facturas_compra fc2 ON fc2.id = fcd2.factura_compra_id
    WHERE fc2.eliminado = 0 AND fc2.estado <> 'anulada'
    GROUP BY fcd2.tipo_item, COALESCE(fcd2.materia_prima_id, fcd2.insumo_id)
) prom ON prom.tipo_item = it.tipo_item AND prom.item_id = it.item_id
WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
  AND (
        (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) > prom.precio_prom_base * 3
     OR (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) < prom.precio_prom_base / 3
  )
ORDER BY veces_vs_promedio DESC;
