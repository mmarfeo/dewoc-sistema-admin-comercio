-- ============================================================
--  Asistencia de empleados: calendario, horas extra y ausencias
--
--  - `empleados_jornadas` suma una columna `tipo` para distinguir
--    día normal trabajado / feriado trabajado / horas extra.
--  - `empleados_ausencias` registra los días que un empleado NO
--    trabajó, con dos modos:
--      · descuento: se resta dinero del próximo pago (día/hora
--        equivalente según su modalidad).
--      · recuperar: no se descuenta nada ahora, queda una deuda
--        de horas que se va saldando cuando el empleado las
--        recupera en otro momento.
--
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `empleados_jornadas`
  ADD COLUMN IF NOT EXISTS `tipo` ENUM('normal','feriado','extra') NOT NULL DEFAULT 'normal' AFTER `es_feriado`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

UPDATE `empleados_jornadas` SET `tipo` = 'feriado' WHERE `es_feriado` = 1 AND `tipo` = 'normal';

CREATE TABLE IF NOT EXISTS `empleados_ausencias` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id`        INT UNSIGNED  NOT NULL,
  `fecha`              DATE          NOT NULL,
  `tipo`               ENUM('descuento','recuperar') NOT NULL DEFAULT 'descuento',
  `horas`              DECIMAL(5,2)  NOT NULL DEFAULT 0 COMMENT 'Horas de la ausencia (informativo / base para el descuento en por_hora)',
  `monto_descuento`    DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Solo tipo=descuento: monto restado del próximo pago',
  `horas_pendientes`   DECIMAL(5,2)  NOT NULL DEFAULT 0 COMMENT 'Solo tipo=recuperar: horas que el empleado todavía nos debe',
  `monto_equivalente`  DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Solo tipo=recuperar: valor $ equivalente a las horas originales (congelado al crear), para mostrar la deuda también en dinero',
  `notas`              VARCHAR(255)  DEFAULT NULL,
  `pagado`            TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Solo tipo=descuento: si ya se aplicó a un pago',
  `pago_id`           INT           DEFAULT NULL,
  `usuario_id`        INT UNSIGNED  DEFAULT NULL,
  `eliminado`         TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ea_empleado_fecha` (`empleado_id`, `fecha`),
  KEY `idx_ea_empleado` (`empleado_id`),
  KEY `idx_ea_pago`     (`pago_id`),
  CONSTRAINT `fk_ea_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_pago`     FOREIGN KEY (`pago_id`)     REFERENCES `pagos_proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Por si esta tabla ya existía de una corrida anterior de este mismo script
-- (antes de agregar el equivalente en dinero de la deuda de horas):
ALTER TABLE `empleados_ausencias`
  ADD COLUMN IF NOT EXISTS `monto_equivalente` DECIMAL(12,2) NOT NULL DEFAULT 0
    COMMENT 'Solo tipo=recuperar: valor $ equivalente a las horas originales (congelado al crear)' AFTER `horas_pendientes`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
