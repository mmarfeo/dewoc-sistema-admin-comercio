-- ============================================================
--  Agrega el modelo usado en cada llamada al log de Gemini, para
--  poder ver si el fallback (GEMINI_MODEL_FALLBACK) se está usando
--  cuando el modelo principal agota su cuota (RPM/RPD).
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `gemini_llamadas_log` ADD COLUMN `modelo` VARCHAR(50) NULL AFTER `iteracion`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
