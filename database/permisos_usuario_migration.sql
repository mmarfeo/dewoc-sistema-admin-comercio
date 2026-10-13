-- Permisos personalizados por usuario individual (excepciones sobre los permisos de su rol).
-- Si un usuario no tiene filas acá, hereda los permisos de su rol tal cual (comportamiento actual).
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `permisos_usuario` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `modulo`     VARCHAR(30)  NOT NULL,
  `ver`        TINYINT(1)   NOT NULL DEFAULT 0,
  `editar`     TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pu_usuario_modulo` (`usuario_id`, `modulo`),
  CONSTRAINT `fk_pu_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
