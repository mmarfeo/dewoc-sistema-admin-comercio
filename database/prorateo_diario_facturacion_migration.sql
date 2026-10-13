-- Prorrateo diario del límite de facturación para el POS: reparte un saldo entre los
-- días de un período (desde/hasta), acumulando lo no facturado de un día para los
-- siguientes. El saldo sale de "Verificar facturación por mes" en Resumen Económico
-- (teórico de los meses tildados menos lo ya facturado, editado a mano si hace falta).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `configuracion_fiscal`
  ADD COLUMN `saldo_prorateo_diario` DECIMAL(14,2) NULL DEFAULT NULL,
  ADD COLUMN `prorateo_desde` DATE NULL DEFAULT NULL,
  ADD COLUMN `prorateo_hasta` DATE NULL DEFAULT NULL;
-- Nota: si da error porque ya existen las columnas, ignorar este ALTER.
