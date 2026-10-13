-- Registra cada vez que se vendió un producto "hecho al momento" (controla_stock=0
-- con receta asociada) sin tener suficiente materia prima/insumos — la venta se deja
-- pasar igual (el usuario lo confirma en el POS), pero queda anotado acá para
-- revisar después en el reporte "Ventas sin stock".
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `ventas_sin_stock` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `venta_id`            INT UNSIGNED NOT NULL,
  `producto_id`         INT UNSIGNED NOT NULL,
  `tipo_ingrediente`     ENUM('materia_prima','insumo','ingrediente_generico') NOT NULL,
  `materia_prima_id`    INT UNSIGNED DEFAULT NULL,
  `insumo_id`           INT UNSIGNED DEFAULT NULL,
  `ingrediente_generico_id` INT UNSIGNED DEFAULT NULL,
  `ingrediente_nombre`  VARCHAR(150) NOT NULL,
  `cantidad_necesaria`  DECIMAL(14,4) NOT NULL,
  `cantidad_disponible` DECIMAL(14,4) NOT NULL,
  `usuario_id`          INT UNSIGNED DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vss_venta` (`venta_id`),
  KEY `idx_vss_producto` (`producto_id`),
  KEY `idx_vss_created` (`created_at`),
  CONSTRAINT `fk_vss_venta`    FOREIGN KEY (`venta_id`)    REFERENCES `ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vss_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_vss_usuario`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
