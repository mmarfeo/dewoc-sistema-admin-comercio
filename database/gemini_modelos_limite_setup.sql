-- ============================================================
--  Límites RPM/RPD conocidos de cada modelo de la cadena de Gemini
--  (GEMINI_MODEL + GEMINI_MODEL_FALLBACK), cargados a mano desde la
--  pantalla "Límite de frecuencia" de Google AI Studio — Google no
--  expone estos números por API. Se usan para elegir, antes de cada
--  mensaje del chat, qué modelo de la cadena tiene cupo disponible
--  en vez de esperar a que uno falle a mitad de una conversación.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `gemini_modelos_limite` (
  `modelo`     VARCHAR(50) NOT NULL,
  `rpm`        INT UNSIGNED NULL COMMENT 'Solicitudes por minuto permitidas. NULL = sin límite configurado (no se chequea).',
  `rpd`        INT UNSIGNED NULL COMMENT 'Solicitudes por día permitidas. NULL = sin límite configurado.',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`modelo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores conocidos al momento de esta migración (proyecto "CourtApp-Chat", nivel gratuito).
INSERT IGNORE INTO `gemini_modelos_limite` (`modelo`, `rpm`, `rpd`) VALUES
('gemini-2.5-flash',      5, 20),
('gemini-3.6-flash',      5, 20),
('gemini-3.5-flash-lite', 15, 500),
('gemini-3.1-flash-lite', 15, 500);
