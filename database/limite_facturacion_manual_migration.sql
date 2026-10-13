-- Permite al admin sobreescribir a mano el límite de facturación usado para la
-- barra de Monotributo (además del acumulado móvil de 12 meses calculado solo).
-- Si queda NULL, se usa el límite anual teórico de la categoría tal cual.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `configuracion_fiscal`
  ADD COLUMN `limite_facturacion_manual` DECIMAL(14,2) NULL DEFAULT NULL AFTER `bloquear_facturacion_afip`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
