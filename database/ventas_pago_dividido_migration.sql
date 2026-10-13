-- Pago dividido en el POS: permite cobrar una misma venta combinando varios medios de
-- pago (ej. parte efectivo, parte Mercado Pago, parte débito), mismo patrón ya usado en
-- pagos_proveedores (montos por medio + total). metodo_pago se mantiene para todo lo que
-- ya lo usa como "el medio de la venta" (ticket, listados, reportes simples) — cuando hay
-- más de un medio con monto > 0 queda en 'combinado'.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `ventas`
  ADD COLUMN `monto_efectivo`      DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `metodo_pago`,
  ADD COLUMN `monto_debito`        DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `monto_efectivo`,
  ADD COLUMN `monto_credito`       DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `monto_debito`,
  ADD COLUMN `monto_transferencia` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `monto_credito`,
  ADD COLUMN `monto_mercadopago`   DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `monto_transferencia`;
-- Nota: si da error porque ya existen las columnas, ignorar este ALTER.

ALTER TABLE `ventas`
  MODIFY COLUMN `metodo_pago` ENUM('efectivo','debito','credito','transferencia','mercadopago','combinado')
  NOT NULL DEFAULT 'efectivo';
