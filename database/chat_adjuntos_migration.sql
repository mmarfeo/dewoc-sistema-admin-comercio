-- Guarda en disco la imagen/PDF de factura que el usuario adjunta al Chat IA
-- (hasta ahora se mandaba una sola vez a Gemini y se descartaba, sin quedar
-- registro de qué documento subió cada usuario). Estas dos columnas guardan
-- el nombre de archivo dentro de storage/chat_adjuntos/ y su mime original,
-- para poder mostrarlo después en el historial (propio o, si es admin,
-- el de otro usuario).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `chat_mensajes`
  ADD COLUMN `archivo_adjunto` VARCHAR(255) NULL AFTER `datos_json`,
  ADD COLUMN `archivo_mime`    VARCHAR(100) NULL AFTER `archivo_adjunto`;
