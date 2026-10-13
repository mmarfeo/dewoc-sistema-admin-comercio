-- ============================================================
--  Aviso de reportes generados por cron, ver docs/tareas-pendientes.md #3.
--  Dos cambios:
--  1) `reportes_ia` pasa a admitir filas generadas por cron (sin usuario
--     autor humano) en vez de solo las pedidas por chat.
--  2) Bandeja de notificaciones genérica, nueva — le da uso real a la
--     campanita del header (hoy un cascarón fijo con "Sin notificaciones
--     pendientes"). No es específica de reportes: cualquier aviso futuro
--     in-app puede reutilizarla.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `reportes_ia`
  MODIFY COLUMN `usuario_id` INT UNSIGNED NULL,
  ADD COLUMN `origen` ENUM('chat','cron') NOT NULL DEFAULT 'chat'
    COMMENT 'chat = lo pidió un usuario al Chat IA, cron = lo generó un script automático'
    AFTER `usuario_id`;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED  NOT NULL,
  `titulo`     VARCHAR(150)  NOT NULL,
  `cuerpo`     VARCHAR(500)  DEFAULT NULL,
  `url`        VARCHAR(255)  DEFAULT NULL COMMENT 'Link de acción al tocar la notificación',
  `tipo`       VARCHAR(30)   NOT NULL DEFAULT 'general' COMMENT 'Ej. reporte_cron — para filtrar o darle un ícono distinto a futuro',
  `leido`      TINYINT(1)    NOT NULL DEFAULT 0,
  `leido_at`   DATETIME      DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_usuario_leido` (`usuario_id`, `leido`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
