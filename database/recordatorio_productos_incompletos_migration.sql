-- Recordatorio del Chat IA para productos de reventa incompletos (sin precio de venta
-- y/o sin categoría) — típicamente creados al vuelo al cargar una factura de compra con
-- un ítem que todavía no existía en el sistema. Guarda la última fecha en que el chat
-- ya le avisó al usuario, para no insistir más de una vez por día.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `usuarios`
  ADD COLUMN `recordatorio_productos_fecha` DATE NULL DEFAULT NULL AFTER `ultimo_login`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
-- NULL = todavía no se le avisó (o fue antes de hoy) → el chat puede volver a mencionarlo.
