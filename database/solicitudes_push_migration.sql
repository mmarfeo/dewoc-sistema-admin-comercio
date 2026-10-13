-- Solicitudes de compra/tareas + suscripciones Web Push para notificar a los administradores.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `solicitudes` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo`         ENUM('producto','materia_prima','tarea') NOT NULL,
  `item_id`      INT UNSIGNED  DEFAULT NULL COMMENT 'FK a productos.id o materias_primas.id según tipo (NULL si es tarea o no existe en el catálogo)',
  `item_nombre`  VARCHAR(150)  NOT NULL COMMENT 'Nombre del producto/materia prima elegido o escrito libremente, o título de la tarea',
  `descripcion`  TEXT          DEFAULT NULL COMMENT 'Información adicional / libre',
  `prioridad`    ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado`       ENUM('pendiente','en_proceso','resuelta') NOT NULL DEFAULT 'pendiente',
  `usuario_id`   INT UNSIGNED  NOT NULL COMMENT 'Quién creó la solicitud',
  `resuelto_por` INT UNSIGNED  DEFAULT NULL,
  `resuelto_at`  DATETIME      DEFAULT NULL,
  `eliminado`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sol_estado` (`estado`),
  KEY `idx_sol_usuario` (`usuario_id`),
  CONSTRAINT `fk_sol_usuario`      FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_sol_resuelto_por` FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `endpoint`   VARCHAR(500) NOT NULL,
  `p256dh`     VARCHAR(255) NOT NULL,
  `auth`       VARCHAR(255) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (`endpoint`(191)),
  KEY `idx_push_usuario` (`usuario_id`),
  CONSTRAINT `fk_push_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
