-- ============================================================
--  Costos Fijos Recurrentes: día de vencimiento (cuándo se paga)
--  separado del día de generación (cuándo se crea el registro).
--
--  Ej: el servicio de Internet se genera el día 1 (para que ya
--  aparezca en el resumen del mes) pero vence recién el día 10.
--  Esto permite, desde el día 1, ver todo lo que hay que pagar
--  ese mes y cuándo vence cada cosa (aunque todavía no se haya
--  generado el registro real).
--
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `gastos_recurrentes`
  ADD COLUMN IF NOT EXISTS `dia_vencimiento` TINYINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Día del mes (1-31) en que vence el pago. Si es NULL, se toma igual a dia_generacion' AFTER `dia_generacion`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.

-- Fecha de vencimiento congelada en cada gasto (generado automáticamente o cargado
-- a mano) para poder marcarlo como vencido y filtrar/ordenar por vencimiento.
ALTER TABLE `gastos`
  ADD COLUMN IF NOT EXISTS `fecha_vencimiento` DATE NULL DEFAULT NULL AFTER `fecha`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
