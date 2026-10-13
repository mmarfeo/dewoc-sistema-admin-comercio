-- Agrega la columna donde se guarda el payload estructurado de las tarjetas
-- de confirmación del Chat IA (compra con ítems, o acción simple), junto con
-- su estado (pendiente/confirmado/cancelado), para poder resolverlas con
-- botones en vez de texto libre y re-renderizarlas igual tras un reload.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `chat_mensajes` ADD COLUMN `datos_json` TEXT NULL AFTER `sql_ejecutado`;
