-- ============================================================
--  Log detallado de cada llamada real a la API de Gemini (chat),
--  para diagnosticar por qué se agota el límite de 20 solicitudes
--  por minuto del plan gratuito. Complementa a `gemini_llamadas`
--  (que es agregada, solo para costeo) sin reemplazarla.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `gemini_llamadas_log` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mensaje_chat_id`  INT UNSIGNED NULL COMMENT 'chat_mensajes.id del mensaje del usuario que disparó esta cadena de llamadas',
  `usuario_id`       INT UNSIGNED NULL,
  `iteracion`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'vuelta del loop de function-calling (1..10) dentro de ese mismo mensaje',
  `resumen_enviado`  VARCHAR(300) NULL COMMENT 'qué se le mandó a Gemini en esta llamada',
  `resumen_recibido` VARCHAR(300) NULL COMMENT 'qué contestó Gemini',
  `http_code`        SMALLINT NOT NULL,
  `exito`            TINYINT(1) NOT NULL,
  `tokens_prompt`    INT UNSIGNED NULL,
  `tokens_respuesta` INT UNSIGNED NULL,
  `duracion_ms`      INT UNSIGNED NULL,
  `error_resumen`    VARCHAR(500) NULL COMMENT 'body del error truncado, solo si exito=0',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gll_mensaje` (`mensaje_chat_id`),
  KEY `idx_gll_created` (`created_at`),
  KEY `idx_gll_exito`   (`exito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
