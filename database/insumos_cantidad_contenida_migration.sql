-- Agrega a `insumos` la misma columna `cantidad_contenida` que ya tiene `materias_primas`
-- (unidades reales por bulto/caja/paquete comprado, ej. "100" para un bulto de 100 potes).
--
-- Motivo: el formulario/Chat IA de compras siempre dejó cargar "Cantidad contenida" en
-- cualquier línea, sin importar el tipo de ítem — pero `insumos` no tenía dónde guardar
-- ese valor en su ficha, y el código que actualiza costo/stock al procesar una compra
-- (FacturaCompraModel::actualizarStock()) solo aplicaba la conversión para materias primas
-- y productos, nunca para insumos. Resultado real encontrado en producción: un insumo
-- comprado "3 bultos de 100 potes a $23.640,53 el bulto" terminó con stock_actual=3 y
-- costo_unitario=$23.640,53 (el precio del bulto entero tomado como precio de 1 pote), en
-- vez de stock_actual=300 y costo_unitario≈$236,41. Ver conversación de soporte.
--
-- IMPORTANTE: ejecutar una sola vez.

ALTER TABLE `insumos`
  ADD COLUMN `cantidad_contenida` DECIMAL(12,3) NULL DEFAULT NULL
    COMMENT 'Unidades reales por bulto/envase comprado (ej. 100 si se compra por bulto de 100 unidades) — igual que materias_primas.cantidad_contenida. NULL/1 = se compra y consume 1:1.'
    AFTER `unidad_medida_id`;
