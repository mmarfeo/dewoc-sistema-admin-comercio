-- Vincula activos_fijos con la factura de compra de la que vino (ver plan de la conversación
-- que armó esto: integrar Activos Fijos con Compras, formulario + Chat IA). Antes solo quedaba
-- una nota de texto ("Cargado desde factura de compra #X"); ahora es un link real.
--
-- El FK apunta a `facturas_compra` (la CABECERA), no a `facturas_compra_detalle` (la línea
-- puntual) — a propósito: cuando se "convierte" una línea ya cargada en activo fijo
-- (ActivoFijoModel::crearDesdeLineaCompra()), esa línea se BORRA de facturas_compra_detalle
-- como parte de la conversión, así que un FK a la línea quedaría roto de entrada. La factura
-- en cambio nunca se borra físicamente (soft delete de siempre en este proyecto), y alcanza
-- igual para el pedido real: poder ver desde el activo fijo de qué compra vino.
--
-- Ejecutar una sola vez desde phpMyAdmin. Seguro re-ejecutar: chequea contra
-- INFORMATION_SCHEMA antes de alterar (mismo patrón que otras migraciones de este proyecto).

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activos_fijos' AND COLUMN_NAME = 'factura_compra_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `activos_fijos`
     ADD COLUMN `factura_compra_id` INT UNSIGNED DEFAULT NULL AFTER `proveedor_id`
       COMMENT "Factura de compra de origen, si se generó vinculando/convirtiendo una línea de compra existente o nueva",
     ADD CONSTRAINT `fk_activo_factura` FOREIGN KEY (`factura_compra_id`)
       REFERENCES `facturas_compra` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
