-- ============================================================
--  Permite que una "opción" (grupos_opciones/opciones — ej. "Gustos de helado", "Torta
--  de la promo") descuente stock de un PRODUCTO del catálogo, además de materia prima o
--  insumo como ya se podía. Caso real: una promo donde cada opción es en sí un producto
--  vendible por separado (ej. elegir entre "Cookies", "Torta de chocolate", "Café" o
--  "Helado"), no una materia prima suelta.
--  Ejecutar una sola vez desde phpMyAdmin.
-- ============================================================

ALTER TABLE `opciones`
  ADD COLUMN `producto_id` INT UNSIGNED DEFAULT NULL AFTER `insumo_id`,
  ADD CONSTRAINT `fk_opcion_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;

-- ventas_sin_stock ya rastreaba faltantes de materia_prima/insumo/ingrediente_generico al
-- confirmar una venta sin stock suficiente ("¿vender igual?") — se agrega 'producto' al
-- enum + una columna propia `producto_opcion_id` (distinta de la columna `producto_id` que
-- YA EXISTE en esta tabla, que identifica el producto VENDIDO, no el producto vinculado a
-- la opción elegida dentro de esa venta).
ALTER TABLE `ventas_sin_stock`
  MODIFY COLUMN `tipo_ingrediente` ENUM('materia_prima','insumo','ingrediente_generico','producto') NOT NULL,
  ADD COLUMN `producto_opcion_id` INT UNSIGNED DEFAULT NULL AFTER `ingrediente_generico_id`,
  ADD CONSTRAINT `fk_vss_producto_opcion` FOREIGN KEY (`producto_opcion_id`) REFERENCES `productos` (`id`);
