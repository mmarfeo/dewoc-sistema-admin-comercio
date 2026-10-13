-- Extiende "Ingredientes genéricos" para que también agrupe INSUMOS, no solo materias
-- primas (ver ingredientes_genericos_migration.sql, que agregó materias_primas.ingrediente_
-- generico_id). Caso real: un pote de helado cuadrado vs uno redondo — según lo que se
-- consiga en cada compra, distinta marca/forma de envase, mismo concepto para la receta.
--
-- Un ingrediente genérico agrupa variantes de UN SOLO tipo por vez (nunca mezcla materias
-- primas con insumos bajo el mismo genérico — no tendría sentido comparar el costo de una
-- materia prima con el de un envase) — por eso alcanza con agregar la misma columna
-- ingrediente_generico_id a `insumos`, igual que ya tiene `materias_primas`, sin tocar la
-- tabla `ingredientes_genericos` en sí. IngredienteGenericoModel ahora resuelve contra la
-- tabla que corresponda según en cuál de las dos esté cargado el id.
--
-- Ejecutar una sola vez desde phpMyAdmin. Seguro re-ejecutar: chequea contra
-- INFORMATION_SCHEMA antes de alterar.

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'insumos' AND COLUMN_NAME = 'ingrediente_generico_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `insumos`
     ADD COLUMN `ingrediente_generico_id` INT UNSIGNED NULL DEFAULT NULL AFTER `costo_unitario`,
     ADD CONSTRAINT `fk_insumo_ingrediente_generico` FOREIGN KEY (`ingrediente_generico_id`)
       REFERENCES `ingredientes_genericos` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
