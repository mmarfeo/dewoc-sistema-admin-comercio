-- ============================================================
--  Módulo: Identidades de Proveedores para detección por IA
--  Ejecutar una vez sobre la base de datos existente
-- ============================================================

CREATE TABLE IF NOT EXISTS `proveedor_identidades` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `proveedor_id` INT          NOT NULL,
  `tipo`         ENUM('cbu','alias_mp','nombre','cuit','otro') NOT NULL DEFAULT 'otro',
  `valor`        VARCHAR(255) NOT NULL,
  `descripcion`  VARCHAR(255) DEFAULT NULL COMMENT 'Etiqueta libre: Cuenta MP personal, CBU empresa, etc.',
  `eliminado`    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_proveedor_id` (`proveedor_id`),
  KEY `idx_valor`        (`valor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
