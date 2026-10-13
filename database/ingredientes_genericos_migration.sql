-- ============================================================
-- Ingredientes Genéricos: desacopla "qué ingrediente usa la
-- receta" de "qué materia prima puntual se compró", para que
-- cambiar de marca/proveedor no rompa costos ni recetas.
-- Ejecutar una sola vez sobre la base existente.
-- ============================================================

-- 1. Catálogo de ingredientes genéricos (ej. "Jamón cocido")
CREATE TABLE IF NOT EXISTS `ingredientes_genericos` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`           VARCHAR(150)  NOT NULL,
  `unidad_medida_id` TINYINT UNSIGNED NOT NULL COMMENT 'Unidad base (ej. gr) — toda MP-variante debe compartirla',
  `categoria_id`     INT UNSIGNED  DEFAULT NULL,
  `eliminado`        TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ig_unidad`    FOREIGN KEY (`unidad_medida_id`) REFERENCES `unidades_medida` (`id`),
  CONSTRAINT `fk_ig_categoria` FOREIGN KEY (`categoria_id`)     REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Materias primas: a qué genérico pertenece esta variante (marca/paquete puntual)
ALTER TABLE `materias_primas`
  ADD COLUMN `ingrediente_generico_id` INT UNSIGNED NULL DEFAULT NULL AFTER `categoria_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

ALTER TABLE `materias_primas`
  ADD CONSTRAINT `fk_mp_generico` FOREIGN KEY (`ingrediente_generico_id`) REFERENCES `ingredientes_genericos` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la constraint, ignorar este ALTER.

ALTER TABLE `materias_primas` ADD INDEX `idx_mp_generico` (`ingrediente_generico_id`);
-- Nota: si da error porque ya existe el índice, ignorar este ALTER.

-- 3. Receta detalle: permitir que un ingrediente de receta apunte al genérico en vez
--    de a una materia prima puntual. Se amplía el ENUM para poder distinguir el caso.
ALTER TABLE `receta_detalle`
  MODIFY COLUMN `tipo_ingrediente` ENUM('materia_prima','insumo','ingrediente_generico') NOT NULL;

ALTER TABLE `receta_detalle`
  ADD COLUMN `ingrediente_generico_id` INT UNSIGNED NULL DEFAULT NULL AFTER `insumo_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

ALTER TABLE `receta_detalle`
  ADD CONSTRAINT `fk_rd_generico` FOREIGN KEY (`ingrediente_generico_id`) REFERENCES `ingredientes_genericos` (`id`);
-- Nota: si da error porque ya existe la constraint, ignorar este ALTER.

-- 4. Historial de cambios de costo de receta (auditoría — qué compra disparó qué cambio)
CREATE TABLE IF NOT EXISTS `recetas_historial_costo` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `receta_id`         INT UNSIGNED  NOT NULL,
  `costo_anterior`    DECIMAL(14,4) NOT NULL,
  `costo_nuevo`       DECIMAL(14,4) NOT NULL,
  `factura_compra_id` INT UNSIGNED  DEFAULT NULL,
  `motivo`            VARCHAR(255)  DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rhc_receta` (`receta_id`),
  CONSTRAINT `fk_rhc_receta`  FOREIGN KEY (`receta_id`)         REFERENCES `recetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rhc_factura` FOREIGN KEY (`factura_compra_id`) REFERENCES `facturas_compra` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Umbral de aviso de margen (% de caída que dispara la notificación push).
--    Reutiliza la tabla de configuración de una sola fila que ya existe.
ALTER TABLE `configuracion_fiscal`
  ADD COLUMN `umbral_alerta_margen` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Puntos % de caída de margen que disparan aviso; NULL = usar 5% por defecto';
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
