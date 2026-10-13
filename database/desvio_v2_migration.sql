-- ============================================================
-- Desvío v2: nuevo cálculo por conteo físico contra conteo físico anterior
-- ============================================================
-- Ver DesvioModel::registrar() y docs/desvio-mermas-checklist.md. Resumen:
--   * Cada conteo se compara contra el conteo físico ANTERIOR del mismo ítem
--     (stock anterior + compras − consumo registrado − stock contado), no contra el saldo
--     acumulado del sistema. El primer conteo de un ítem es solo punto de partida ("baseline").
--   * desvio_pct = pérdida ÷ consumo registrado del período, y se aplica al costo de la
--     materia prima en el momento del conteo (piso 0: un sobrante lo deja en 0).
--
-- Las filas que ya existen en desvio_historial se calcularon con otra lógica (dividían por
-- unidades de compra, o por el saldo total, y el primer conteo de cada ítem daba 90-100%),
-- así que quedan marcadas 'legacy' y NO participan del promedio nuevo.
--
-- Este script REEMPLAZA a desvio_pct_formula_fix.sql (que recalculaba esas filas con la
-- lógica vieja): no corras ese.
--
-- Ejecutar una sola vez desde phpMyAdmin/Hostinger, ANTES de subir los PHP nuevos.

-- 1. Columnas nuevas (las filas existentes quedan 'legacy' por el DEFAULT).
ALTER TABLE `desvio_historial`
  ADD COLUMN `estado`        VARCHAR(12)   NOT NULL DEFAULT 'legacy'
    COMMENT 'legacy | baseline | valido | sin_consumo' AFTER `desvio_monto`,
  ADD COLUMN `stock_inicial` DECIMAL(14,3) NULL
    COMMENT 'Stock contado en el conteo anterior (inicio del período)' AFTER `estado`,
  ADD COLUMN `ultimo_mov_id` INT UNSIGNED  NULL
    COMMENT 'Último stock_movimientos.id del ítem al momento del conteo — el período siguiente arranca después de este' AFTER `stock_inicial`;
-- Nota: si da error porque ya existen las columnas, ignorar este ALTER.

-- 2. Reset a 0 del desvío vigente de los ítems que tienen ciclos 'legacy': ese valor salió de la
--    calibración automática con la lógica vieja (999.99, 845, 760, -999.99, ...). Los ítems
--    sin historial conservan el desvío que se les haya cargado a mano.
UPDATE `materias_primas`
SET `desvio_pct` = 0
WHERE `id` IN (SELECT DISTINCT `item_id` FROM `desvio_historial` WHERE `item_tipo` = 'materia_prima');

UPDATE `productos`
SET `desvio_pct` = 0
WHERE `id` IN (SELECT DISTINCT `item_id` FROM `desvio_historial` WHERE `item_tipo` = 'producto');

-- Verificación (deben devolver 0 filas):
--   SELECT id, nombre, desvio_pct FROM materias_primas
--    WHERE desvio_pct <> 0 AND id IN (SELECT item_id FROM desvio_historial WHERE item_tipo = 'materia_prima');
--   SELECT id, nombre, desvio_pct FROM productos
--    WHERE desvio_pct <> 0 AND id IN (SELECT item_id FROM desvio_historial WHERE item_tipo = 'producto');
--
-- OJO: el UPDATE cambia el costo de las MP por SQL directo, sin pasar por la propagación a
-- recetas/productos. Las ventas NUEVAS ya usan el costo correcto al instante (se calcula en
-- vivo al vender); productos.precio_costo de elaborados/promos se refresca al guardar cada
-- receta o en la próxima compra de sus ingredientes.
