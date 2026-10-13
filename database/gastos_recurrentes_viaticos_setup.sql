-- ============================================================
--  Costos Fijos Recurrentes: viáticos / premios / bonos por empleado
--
--  Extiende gastos_recurrentes (tipo='viatico') para poder generar
--  automáticamente cada mes un viático/premio/bono fijo asignado a
--  un empleado específico, usando el mismo mecanismo de "Gastos
--  Recurrentes" ya existente (alquiler, sueldos, etc.). Se guarda
--  en empleados_conceptos, igual que si se cargara a mano desde la
--  ficha del empleado.
--
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `gastos_recurrentes`
  MODIFY COLUMN `tipo` ENUM('gasto','sueldo','viatico') NOT NULL DEFAULT 'gasto';

ALTER TABLE `gastos_recurrentes`
  ADD COLUMN IF NOT EXISTS `concepto_tipo` ENUM('viatico','premio','bono','otro') NULL DEFAULT 'viatico'
    COMMENT 'Solo tipo=viatico: subtipo que se registra en empleados_conceptos' AFTER `empleado_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

ALTER TABLE `empleados_conceptos`
  ADD COLUMN IF NOT EXISTS `recurrente_id` INT UNSIGNED NULL DEFAULT NULL AFTER `usuario_id`;
ALTER TABLE `empleados_conceptos`
  ADD CONSTRAINT `fk_ec_recurrente` FOREIGN KEY (`recurrente_id`) REFERENCES `gastos_recurrentes` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la columna/constraint, ignorar estos ALTER.
