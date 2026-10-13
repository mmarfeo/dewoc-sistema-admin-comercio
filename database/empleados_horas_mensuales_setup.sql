-- ============================================================
--  Empleados por hora: horas mensuales estimadas
--
--  Permite cargar cuántas horas va a trabajar al mes un empleado
--  con modalidad "por_hora" para calcular el sueldo mensual fijo
--  (horas_mensuales × valor_hora), igual que un empleado mensual.
--  Si horas_mensuales = 0 (valor por defecto), el empleado sigue
--  funcionando como hasta ahora: se liquida día por día según las
--  jornadas cargadas.
--
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `empleados`
  ADD COLUMN IF NOT EXISTS `horas_mensuales` DECIMAL(8,2) NOT NULL DEFAULT 0
    COMMENT 'Horas estimadas por mes (solo modalidad_pago = por_hora); si es > 0, se paga como sueldo fijo mensual' AFTER `valor_hora`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
