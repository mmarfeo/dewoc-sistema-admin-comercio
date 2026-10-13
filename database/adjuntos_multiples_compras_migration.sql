-- Comprobantes múltiples: una factura de compra puede tener varias hojas/páginas, y un pago
-- puede tener más de un comprobante. facturas_compra.comprobante_archivo y
-- pagos_proveedores.comprobante_path (single-file, ya existentes) quedan como el "primero"
-- de la lista por compatibilidad — los adicionales van a estas tablas nuevas.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `facturas_compra_adjuntos` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `factura_compra_id` INT UNSIGNED NOT NULL,
  `archivo`           VARCHAR(255) NOT NULL,
  `usuario_id`        INT UNSIGNED NOT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fca_factura` (`factura_compra_id`),
  CONSTRAINT `fk_fca_factura` FOREIGN KEY (`factura_compra_id`) REFERENCES `facturas_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fca_usuario`  FOREIGN KEY (`usuario_id`)        REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pagos_proveedores_adjuntos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pago_id`    INT          NOT NULL COMMENT 'Sin UNSIGNED a propósito: pagos_proveedores.id es INT con signo',
  `archivo`    VARCHAR(255) NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ppa_pago` (`pago_id`),
  CONSTRAINT `fk_ppa_pago`    FOREIGN KEY (`pago_id`)    REFERENCES `pagos_proveedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
