-- ============================================================
--  Módulo: Nómina de Empleados (modalidades de pago, jornadas,
--  feriados con recargo, viáticos/premios/bonos)
--  Ejecutar una vez sobre la base de datos existente
--  (requiere haber corrido antes database/empleados_setup.sql)
-- ============================================================

-- 1. Modalidad de pago y tarifas de referencia en la ficha de empleado
ALTER TABLE `empleados`
  ADD COLUMN `modalidad_pago` ENUM('mensual','quincenal','diario','por_hora') NOT NULL DEFAULT 'mensual' AFTER `puesto`,
  ADD COLUMN `valor_hora` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Usado si modalidad_pago = por_hora' AFTER `sueldo_mensual`,
  ADD COLUMN `valor_dia`  DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Usado si modalidad_pago = diario' AFTER `valor_hora`,
  ADD COLUMN `markup_feriado_pct` DECIMAL(5,2) NOT NULL DEFAULT 100 COMMENT '% de recargo por feriado trabajado (100 = se paga doble)' AFTER `valor_dia`;
-- Nota: si da error porque ya existen las columnas, ignorar este ALTER.

-- 2. Jornadas trabajadas (planilla de asistencia día por día)
--    - diario / por_hora: un registro por cada día efectivamente trabajado.
--    - mensual / quincenal: solo se cargan los feriados trabajados (el resto
--      del sueldo ya está cubierto por el monto fijo mensual).
CREATE TABLE IF NOT EXISTS `empleados_jornadas` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id` INT UNSIGNED  NOT NULL,
  `fecha`       DATE          NOT NULL,
  `horas`       DECIMAL(5,2)  NOT NULL DEFAULT 0 COMMENT 'Horas trabajadas ese día (solo modalidad por_hora)',
  `es_feriado`  TINYINT(1)    NOT NULL DEFAULT 0,
  `monto`       DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Monto devengado, calculado y congelado al cargar la jornada',
  `notas`       VARCHAR(255)  DEFAULT NULL,
  `pagado`      TINYINT(1)    NOT NULL DEFAULT 0,
  `pago_id`     INT           DEFAULT NULL COMMENT 'pagos_proveedores.id que liquidó esta jornada',
  `usuario_id`  INT UNSIGNED  DEFAULT NULL,
  `eliminado`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ej_empleado_fecha` (`empleado_id`, `fecha`),
  KEY `idx_ej_empleado` (`empleado_id`),
  KEY `idx_ej_fecha`    (`fecha`),
  KEY `idx_ej_pago`     (`pago_id`),
  CONSTRAINT `fk_ej_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ej_pago`     FOREIGN KEY (`pago_id`)     REFERENCES `pagos_proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Viáticos / premios / bonos — quedan pendientes hasta incluirse en el
--    próximo pago de sueldo del empleado.
CREATE TABLE IF NOT EXISTS `empleados_conceptos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id` INT UNSIGNED  NOT NULL,
  `fecha`       DATE          NOT NULL,
  `tipo`        ENUM('viatico','premio','bono','otro') NOT NULL DEFAULT 'otro',
  `descripcion` VARCHAR(255)  DEFAULT NULL,
  `monto`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `pagado`      TINYINT(1)    NOT NULL DEFAULT 0,
  `pago_id`     INT           DEFAULT NULL,
  `usuario_id`  INT UNSIGNED  DEFAULT NULL,
  `eliminado`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ec_empleado` (`empleado_id`),
  KEY `idx_ec_pago`     (`pago_id`),
  CONSTRAINT `fk_ec_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ec_pago`     FOREIGN KEY (`pago_id`)     REFERENCES `pagos_proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
