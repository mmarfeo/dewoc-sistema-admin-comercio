-- Promos (combos de composición fija, ej. "1 Café + 2 Medialunas") — ver docs/promos-diseno.md.
-- El producto-promo en sí no controla stock propio: al venderse, cada componente se
-- descuenta con su propia regla de siempre (controla_stock directo o receta de hecho al
-- momento) — ver VentaModel::registrar().
-- Ejecutar una sola vez desde phpMyAdmin.

-- Catálogo: qué productos y cantidades componen cada promo.
CREATE TABLE IF NOT EXISTS `producto_promo_detalle` (
  `id`                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `producto_id`             INT UNSIGNED  NOT NULL COMMENT 'El producto-promo (ej. "Promo Café + 2 Medialunas")',
  `componente_producto_id`  INT UNSIGNED  NOT NULL COMMENT 'El producto real que contiene (ej. Café, Medialuna)',
  `cantidad`                DECIMAL(12,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promo_componente` (`producto_id`, `componente_producto_id`),
  KEY `idx_ppd_producto` (`producto_id`),
  CONSTRAINT `fk_ppd_producto`    FOREIGN KEY (`producto_id`)            REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppd_componente`  FOREIGN KEY (`componente_producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registro de venta: qué se entregó realmente dentro de cada promo vendida. Al vender una
-- promo se generan filas hijas (precio $0, no duplican ingreso) apuntando acá a la fila de
-- la promo — así los reportes de producto pueden sumar la cantidad real entregada, y
-- marcar que vino de una promo.
ALTER TABLE `ventas_detalle`
  ADD COLUMN `venta_detalle_padre_id` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Si esta línea es un componente entregado dentro de una promo, apunta a la fila de la promo (esta misma tabla)'
    AFTER `producto_id`;

ALTER TABLE `ventas_detalle`
  ADD CONSTRAINT `fk_vd_padre` FOREIGN KEY (`venta_detalle_padre_id`) REFERENCES `ventas_detalle` (`id`) ON DELETE CASCADE;

ALTER TABLE `ventas_detalle` ADD INDEX `idx_vd_padre` (`venta_detalle_padre_id`);
