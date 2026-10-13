-- ============================================================
-- Desglose del costo real de MP por venta (ver ventas_costo_real_migration.sql):
-- costo_mp_real ya guarda el TOTAL de la línea; esta columna guarda cuánto de ese
-- total corresponde a líneas de ingrediente_generico (ej. el helado, cuyo costo
-- varía mucho según el sabor puntual elegido) — el resto (costo_mp_real menos
-- esta columna) es materia prima/insumo "directa" (envase, cucharita, etc.).
-- Sirve para poder mostrar en el Chat IA un desglose "MP directa" vs "Ingrediente
-- genérico" agrupado por producto, sin tener que recalcular con costos de hoy.
-- Ejecutar una sola vez sobre la base existente.
-- ============================================================

ALTER TABLE `ventas_detalle`
  ADD COLUMN `costo_ingrediente_generico` DECIMAL(14,4) NULL DEFAULT NULL
    COMMENT 'Porción de costo_mp_real que corresponde a líneas de ingrediente_generico; NULL en ventas anteriores a esta funcionalidad'
    AFTER `costo_mp_real`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
