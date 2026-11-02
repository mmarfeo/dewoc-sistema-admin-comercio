-- Dewoc Cobros: asignación manual de transferencias a ventas (ver
-- app/Models/CobroRecibidoModel.php y la pantalla Ventas → Transferencias recibidas).
-- Requiere database/dewoc_cobros_conciliacion_migration.sql. Idempotente. Ejecutar desde phpMyAdmin.
--
-- La validación automática sigue siendo exacta (mismo monto, ±30 minutos). Lo que no cierra
-- así lo asigna una persona: desde el pop-up del POS (solo las del turno abierto) o desde la
-- pantalla de Transferencias recibidas. Una transferencia que no es de una venta se marca
-- "No corresponde", con un motivo, para que deje de figurar como pendiente.

ALTER TABLE `cobros_recibidos`
  ADD COLUMN IF NOT EXISTS `vinculado_por` INT UNSIGNED NULL
    COMMENT 'usuarios.id de quien la asignó a mano. NULL con venta_id = asignación automática exacta'
    AFTER `venta_id`,
  ADD COLUMN IF NOT EXISTS `vinculado_at` DATETIME NULL AFTER `vinculado_por`,
  ADD COLUMN IF NOT EXISTS `ignorado_at` DATETIME NULL
    COMMENT 'Marcada "No corresponde a una venta": deja de figurar como pendiente'
    AFTER `vinculado_at`,
  ADD COLUMN IF NOT EXISTS `ignorado_por` INT UNSIGNED NULL AFTER `ignorado_at`,
  ADD COLUMN IF NOT EXISTS `ignorado_motivo` VARCHAR(200) NULL AFTER `ignorado_por`;
