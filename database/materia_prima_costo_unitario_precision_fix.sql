-- Corrige un problema real detectado al correr materia_prima_stock_base_migration.sql:
-- `materias_primas.costo_unitario` era DECIMAL(12,2) (2 decimales) — suficiente para el
-- modelo viejo (costo por bidón/paquete, en pesos enteros), pero INSUFICIENTE para el
-- modelo nuevo (costo por gramo/ml, a veces fracciones de centavo). MySQL avisó
-- "Datos truncados para la columna costo_unitario" en 220 filas: esos valores quedaron
-- redondeados/con precisión perdida (ej: $30 ÷ 5000ml = $0.006 se guardó como $0.01).
--
-- Este script:
--   1. Ensancha la columna a DECIMAL(14,4) (mismo criterio que ya usa precio_unitario en
--      facturas_compra_detalle y costo_unitario_momento en desvio_historial).
--   2. Reconstruye costo_unitario para las materias primas afectadas (cantidad_contenida
--      != 1) a partir del HISTORIAL de compras (facturas_compra_detalle), no del valor ya
--      truncado — es la única fuente de verdad disponible sin backup previo. Se calcula
--      como el promedio ponderado de TODAS las compras no anuladas:
--        SUM(cantidad × precio_unitario) / SUM(cantidad × cantidad_contenida)
--      No es matemáticamente idéntico al Costo Promedio Ponderado "verdadero" (que también
--      se ve afectado por las ventas/consumos entre compra y compra), pero es una
--      reconstrucción razonable y con precisión completa, mucho mejor que arrastrar el
--      valor ya redondeado.
--
-- IMPORTANTE:
--   - Ejecutar UNA sola vez, después de materia_prima_stock_base_migration.sql (que ya
--     corriste). No vuelve a tocar stock_actual (ese no se truncó).
--   - Al final corre un SELECT de verificación: lista las materias primas que quedaron
--     afectadas por la migración pero NO tienen historial de compras para reconstruir su
--     costo (ítems dados de alta con stock inicial manual, sin factura de compra propia) —
--     esas hay que revisarlas/corregirlas a mano, este script no las toca.

ALTER TABLE materias_primas
  MODIFY COLUMN costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0;

UPDATE materias_primas mp
JOIN (
    SELECT
        fcd.materia_prima_id,
        SUM(fcd.cantidad * fcd.precio_unitario)
          / NULLIF(SUM(fcd.cantidad * COALESCE(fcd.cantidad_contenida, 1)), 0) AS costo_reconstruido
    FROM facturas_compra_detalle fcd
    JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
    WHERE fc.eliminado = 0
      AND fc.estado <> 'anulada'
      AND fcd.materia_prima_id IS NOT NULL
    GROUP BY fcd.materia_prima_id
) hist ON hist.materia_prima_id = mp.id
SET mp.costo_unitario = hist.costo_reconstruido
WHERE mp.cantidad_contenida IS NOT NULL
  AND mp.cantidad_contenida > 0
  AND mp.cantidad_contenida <> 1;

-- Verificación: materias primas afectadas por la migración que NO se pudieron reconstruir
-- (sin historial de compras) — revisar costo_unitario de estas a mano.
SELECT id, nombre, stock_actual, costo_unitario, cantidad_contenida
FROM materias_primas mp
WHERE mp.cantidad_contenida IS NOT NULL
  AND mp.cantidad_contenida > 0
  AND mp.cantidad_contenida <> 1
  AND NOT EXISTS (
      SELECT 1
      FROM facturas_compra_detalle fcd
      JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
      WHERE fcd.materia_prima_id = mp.id
        AND fc.eliminado = 0
        AND fc.estado <> 'anulada'
  );
