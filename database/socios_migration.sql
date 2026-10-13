-- Módulo: Socios (ABM) + vínculo con pagos_proveedores.
-- Reemplaza de a poco el texto libre `nombre_socio` por una FK real, para poder
-- agrupar aportes de forma confiable en reportes (antes "Juan Pérez" y "juan perez"
-- contaban como socios distintos).
-- Ejecutar una sola vez desde phpMyAdmin.

-- COLLATE fijado a propósito (no alcanza con DEFAULT CHARSET=utf8mb4 solo): en
-- servidores MySQL/MariaDB nuevos el collation por defecto de utf8mb4 cambió a
-- utf8mb4_uca1400_ai_ci, distinto al utf8mb4_unicode_ci que usa el resto de esta
-- base — sin esto, comparar `socios.nombre` contra `pagos_proveedores.nombre_socio`
-- (como hace socios_migrar_datos.sql) tira "Illegal mix of collations".
CREATE TABLE IF NOT EXISTS `socios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(150) NOT NULL,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `eliminado`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Por si la tabla ya se había creado antes con el collation por defecto del
-- servidor (utf8mb4_uca1400_ai_ci u otro) — inofensivo si ya está bien.
ALTER TABLE `socios` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `pagos_proveedores`
  ADD COLUMN IF NOT EXISTS `socio_id` INT UNSIGNED NULL
    COMMENT 'FK a socios; reemplaza a nombre_socio (se mantiene por compatibilidad)'
    AFTER `nombre_socio`,
  ADD CONSTRAINT `fk_pp_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL;
