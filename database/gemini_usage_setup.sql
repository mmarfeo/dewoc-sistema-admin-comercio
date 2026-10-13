-- ── Gemini API Usage Tracking ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `gemini_llamadas` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `fecha`            DATE         NOT NULL,
  `modelo`           VARCHAR(100) NOT NULL DEFAULT '',
  `categoria`        ENUM('chat','imagen','video') NOT NULL DEFAULT 'chat' COMMENT 'Para separar consumo/costo del Chat IA (texto) del de la Galería (imagen/video)',
  `tokens_prompt`    INT          NOT NULL DEFAULT 0,
  `tokens_respuesta` INT          NOT NULL DEFAULT 0,
  `tokens_total`     INT          NOT NULL DEFAULT 0,
  `video_segundos`   INT UNSIGNED DEFAULT NULL COMMENT 'Solo categoria=video: Veo no reporta tokens, se factura por segundo generado',
  `usuario_id`       INT          DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecha`       (`fecha`),
  KEY `idx_usuario_id`  (`usuario_id`),
  KEY `idx_categoria`   (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migración para instalaciones donde la tabla ya existía sin estas columnas.
ALTER TABLE `gemini_llamadas`
  ADD COLUMN IF NOT EXISTS `categoria`      ENUM('chat','imagen','video') NOT NULL DEFAULT 'chat' AFTER `modelo`,
  ADD COLUMN IF NOT EXISTS `video_segundos` INT UNSIGNED DEFAULT NULL AFTER `tokens_total`,
  ADD INDEX IF NOT EXISTS `idx_categoria` (`categoria`);

CREATE TABLE IF NOT EXISTS `gemini_config` (
  `clave` VARCHAR(50)  NOT NULL,
  `valor` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `gemini_config` (`clave`, `valor`) VALUES
  ('llamadas_gratuitas_dia',    '1500'),
  ('costo_input_por_millon',    '0.10'),
  ('costo_output_por_millon',   '0.40'),
  ('costo_video_por_segundo',   '0.40'),
  ('tipo_cambio_usd_ars',       '1200');
