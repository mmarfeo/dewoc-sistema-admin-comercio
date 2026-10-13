-- ============================================================
--  Módulo: Empleados / Nómina
--  Ejecutar una vez sobre la base de datos existente
-- ============================================================

-- 1. Ficha de empleado
CREATE TABLE IF NOT EXISTS `empleados` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(150)  NOT NULL,
  `documento`      VARCHAR(20)   DEFAULT NULL COMMENT 'DNI o CUIL',
  `puesto`         VARCHAR(100)  DEFAULT NULL,
  `fecha_ingreso`  DATE          DEFAULT NULL,
  `sueldo_mensual` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Sueldo de referencia, informativo',
  `telefono`       VARCHAR(30)   DEFAULT NULL,
  `email`          VARCHAR(150)  DEFAULT NULL,
  `notas`          TEXT          DEFAULT NULL,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  `eliminado`      TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Vincular pagos_proveedores a una ficha de empleado (reemplaza el texto libre
--    "beneficiario" para pagos de tipo 'sueldo'). El campo beneficiario se conserva
--    para pagos de tipo 'otro' y para el historial de pagos ya cargados.
ALTER TABLE `pagos_proveedores`
  ADD COLUMN `empleado_id` INT UNSIGNED NULL DEFAULT NULL AFTER `proveedor_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

ALTER TABLE `pagos_proveedores`
  ADD CONSTRAINT `fk_pago_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la constraint, ignorar este ALTER.

ALTER TABLE `pagos_proveedores` ADD INDEX `idx_pp_empleado` (`empleado_id`);
-- Nota: si da error porque ya existe el índice, ignorar este ALTER.
