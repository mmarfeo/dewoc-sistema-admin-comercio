-- Configuración fiscal del comercio (categoría de monotributo para trackear el límite de facturación).
-- Fila única (id=1). Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `configuracion_fiscal` (
  `id`                    TINYINT UNSIGNED NOT NULL,
  `categoria_monotributo` CHAR(1) DEFAULT NULL COMMENT 'A-K, NULL si no es monotributista',
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `configuracion_fiscal` (`id`, `categoria_monotributo`) VALUES (1, NULL);
