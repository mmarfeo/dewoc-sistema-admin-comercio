-- ============================================================
--  Opciones/adicionales configurables por producto (ej. "Gustos de
--  helado", "Torta de la promo"), ver docs/tareas-pendientes.md #1.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

-- Grupo reutilizable de opciones (ej. "Gustos de helado", con sus sabores).
CREATE TABLE IF NOT EXISTS `grupos_opciones` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(100)  NOT NULL,
  `tipo_seleccion`  ENUM('unica','multiple') NOT NULL DEFAULT 'unica',
  `permite_repetir` TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Selección múltiple: si se puede elegir la misma opción más de una vez (ej. 2x Dulce de leche)',
  `activo`          TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cada choice dentro de un grupo (ej. "Dulce de leche"). Vincular a materia_prima_id o
-- insumo_id es opcional a propósito: una opción puede ser puramente informativa (ej. "sin
-- azúcar") sin ningún consumo de stock asociado.
CREATE TABLE IF NOT EXISTS `opciones` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `grupo_id`            INT UNSIGNED  NOT NULL,
  `nombre`              VARCHAR(100)  NOT NULL,
  `materia_prima_id`    INT UNSIGNED  DEFAULT NULL,
  `insumo_id`           INT UNSIGNED  DEFAULT NULL,
  `cantidad_consumida`  DECIMAL(12,4) NOT NULL DEFAULT 0 COMMENT 'Cuánto se descuenta de materia_prima_id/insumo_id por cada vez que se elige',
  `orden`               INT UNSIGNED  NOT NULL DEFAULT 0,
  `activo`              TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_opcion_grupo` (`grupo_id`),
  CONSTRAINT `fk_opcion_grupo`         FOREIGN KEY (`grupo_id`)         REFERENCES `grupos_opciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_opcion_materia_prima` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_opcion_insumo`        FOREIGN KEY (`insumo_id`)        REFERENCES `insumos`         (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Qué producto usa qué grupo, y con qué límite de selecciones — el límite vive acá y no en
-- el grupo porque el mismo grupo se reutiliza entre productos con límites distintos (ej.
-- "Gustos de helado" permite elegir 3 en el 1kg pero solo 1 en el cucurucho).
CREATE TABLE IF NOT EXISTS `producto_grupos_opciones` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `producto_id`         INT UNSIGNED  NOT NULL,
  `grupo_id`            INT UNSIGNED  NOT NULL,
  `minimo_selecciones`  INT UNSIGNED  NOT NULL DEFAULT 1,
  `maximo_selecciones`  INT UNSIGNED  NOT NULL DEFAULT 1,
  `obligatorio`         TINYINT(1)    NOT NULL DEFAULT 1,
  `orden`               INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_producto_grupo` (`producto_id`, `grupo_id`),
  CONSTRAINT `fk_pgo_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pgo_grupo`    FOREIGN KEY (`grupo_id`)    REFERENCES `grupos_opciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Qué opciones se eligieron realmente en cada línea de venta (para comanda e historial).
CREATE TABLE IF NOT EXISTS `ventas_detalle_opciones` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `venta_detalle_id`   INT UNSIGNED  NOT NULL,
  `opcion_id`          INT UNSIGNED  NOT NULL,
  `cantidad_elegida`   INT UNSIGNED  NOT NULL DEFAULT 1 COMMENT 'Para permitir repetición, ej. 2x Dulce de leche',
  PRIMARY KEY (`id`),
  KEY `idx_vdo_detalle` (`venta_detalle_id`),
  CONSTRAINT `fk_vdo_detalle` FOREIGN KEY (`venta_detalle_id`) REFERENCES `ventas_detalle` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vdo_opcion`  FOREIGN KEY (`opcion_id`)        REFERENCES `opciones`        (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
