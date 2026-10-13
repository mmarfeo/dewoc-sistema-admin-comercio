-- ============================================================
-- Recetas anidadas: permite que un ingrediente de receta sea a
-- su vez otra receta (ej. "Vaso de cookies" usa la receta genérica
-- "Cookies" como uno de sus ingredientes), en vez de tener que
-- duplicar su fórmula. Al producir, se explota recursivamente
-- hasta los ingredientes base (igual que ya pasa con
-- ingrediente_generico) — no requiere que la sub-receta tenga
-- stock propio ya producido.
-- Ejecutar una sola vez sobre la base existente.
-- ============================================================

ALTER TABLE `receta_detalle`
  MODIFY COLUMN `tipo_ingrediente` ENUM('materia_prima','insumo','ingrediente_generico','receta') NOT NULL;

ALTER TABLE `receta_detalle`
  ADD COLUMN `receta_ingrediente_id` INT UNSIGNED NULL DEFAULT NULL AFTER `ingrediente_generico_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

ALTER TABLE `receta_detalle`
  ADD CONSTRAINT `fk_rd_subreceta` FOREIGN KEY (`receta_ingrediente_id`) REFERENCES `recetas` (`id`);
-- Nota: si da error porque ya existe la constraint, ignorar este ALTER.

ALTER TABLE `receta_detalle` ADD INDEX `idx_rd_subreceta` (`receta_ingrediente_id`);
-- Nota: si da error porque ya existe el índice, ignorar este ALTER.

-- No se puede impedir auto-referencia ni ciclos (A usa B, B usa A) a nivel de
-- FK/CHECK de MySQL — esa validación se hace en la aplicación (RecetaModel::_tieneCiclo).
