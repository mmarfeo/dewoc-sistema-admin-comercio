-- ============================================================
-- Módulo de Gastos y Estado de Resultados
-- Ejecutar en phpMyAdmin o cliente MySQL
-- ============================================================

-- 1. Tabla de categorías de gastos (jerarquía 2 niveles)
CREATE TABLE IF NOT EXISTS `gasto_categorias` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(120) NOT NULL,
  `tipo`      ENUM('costo_fijo','costo_variable','impuesto') NOT NULL,
  `parent_id` INT(11)      DEFAULT NULL,
  `color`     VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
  `orden`     TINYINT(3)   NOT NULL DEFAULT 0,
  `eliminado` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_gcat_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `gasto_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Categorías padre (IDs fijos para referenciar subcategorías)
INSERT IGNORE INTO `gasto_categorias` (`id`,`nombre`,`tipo`,`parent_id`,`color`,`orden`) VALUES
(1, 'Costos Fijos',     'costo_fijo',     NULL, '#0d6efd', 1),
(2, 'Costos Variables', 'costo_variable', NULL, '#198754', 2),
(3, 'Impuestos',        'impuesto',       NULL, '#dc3545', 3);

-- 3. Subcategorías de Costos Fijos (IDs fijos para evitar duplicados al re-ejecutar)
INSERT IGNORE INTO `gasto_categorias` (`id`,`nombre`,`tipo`,`parent_id`,`color`,`orden`) VALUES
(4,  'Sueldos y Jornales',          'costo_fijo', 1, '#0d6efd', 1),
(5,  'Alquiler',                    'costo_fijo', 1, '#0d6efd', 2),
(6,  'Servicios (luz/gas/internet)','costo_fijo', 1, '#0d6efd', 3),
(7,  'Seguros',                     'costo_fijo', 1, '#0d6efd', 4),
(8,  'Amortizaciones',              'costo_fijo', 1, '#0d6efd', 5);

-- 4. Subcategorías de Costos Variables
INSERT IGNORE INTO `gasto_categorias` (`id`,`nombre`,`tipo`,`parent_id`,`color`,`orden`) VALUES
(9,  'Materias Primas',       'costo_variable', 2, '#198754', 1),
(10, 'Insumos y Materiales',  'costo_variable', 2, '#198754', 2),
(11, 'Comisiones',            'costo_variable', 2, '#198754', 3),
(12, 'Fletes y Envíos',       'costo_variable', 2, '#198754', 4),
(13, 'Compras Generales',     'costo_variable', 2, '#198754', 5);

-- 5. Subcategorías de Impuestos
INSERT IGNORE INTO `gasto_categorias` (`id`,`nombre`,`tipo`,`parent_id`,`color`,`orden`) VALUES
(14, 'IVA',             'impuesto', 3, '#dc3545', 1),
(15, 'Ingresos Brutos', 'impuesto', 3, '#dc3545', 2),
(16, 'Monotributo',     'impuesto', 3, '#dc3545', 3),
(17, 'Ganancias',       'impuesto', 3, '#dc3545', 4);

-- 6. Tabla de gastos (sueldos, alquiler, servicios, etc.)
CREATE TABLE IF NOT EXISTS `gastos` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fecha`        DATE          NOT NULL,
  `descripcion`  VARCHAR(255)  NOT NULL,
  `monto`        DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `categoria_id` INT(11)       NOT NULL,
  `usuario_id`   INT UNSIGNED  NOT NULL,
  `notas`        TEXT          DEFAULT NULL,
  `eliminado`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gastos_fecha` (`fecha`),
  CONSTRAINT `fk_gasto_cat`  FOREIGN KEY (`categoria_id`) REFERENCES `gasto_categorias` (`id`),
  CONSTRAINT `fk_gasto_user` FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Columna de categoría en facturas_compra (para vincular compras a categorías de costo)
ALTER TABLE `facturas_compra` ADD COLUMN `categoria_gasto_id` INT(11) NULL DEFAULT NULL;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

-- 8. Proveedores: agregar rubro y ampliar cbu para soportar alias
ALTER TABLE `proveedores` MODIFY COLUMN `cbu` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `proveedores` ADD COLUMN `rubro` VARCHAR(100) DEFAULT NULL AFTER `banco`;
-- Nota: si da error porque ya existen las columnas, ignorar estos ALTER.

-- 9. Materias primas: agregar cantidad_contenida
ALTER TABLE `materias_primas` ADD COLUMN `cantidad_contenida` DECIMAL(12,3) DEFAULT NULL AFTER `unidad_medida_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

-- 10. Recetas: agregar descripcion, instrucciones, tiempo_preparacion, porciones
ALTER TABLE `recetas`
  ADD COLUMN IF NOT EXISTS `descripcion`        TEXT             DEFAULT NULL AFTER `unidad_rendimiento`,
  ADD COLUMN IF NOT EXISTS `instrucciones`      TEXT             DEFAULT NULL AFTER `descripcion`,
  ADD COLUMN IF NOT EXISTS `tiempo_preparacion` SMALLINT UNSIGNED DEFAULT NULL AFTER `instrucciones`,
  ADD COLUMN IF NOT EXISTS `porciones`          SMALLINT UNSIGNED DEFAULT NULL AFTER `tiempo_preparacion`;
-- Nota: si da error porque ya existen las columnas, ignorar estos ALTER.

-- 11. Tabla pivot muchos-a-muchos entre productos y recetas (para costo de materia prima)
CREATE TABLE IF NOT EXISTS `producto_receta` (
  `producto_id` INT UNSIGNED NOT NULL,
  `receta_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`producto_id`, `receta_id`),
  CONSTRAINT `fk_pr_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_receta`   FOREIGN KEY (`receta_id`)   REFERENCES `recetas`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
