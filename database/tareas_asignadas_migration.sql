-- ============================================================
--  Tareas asignadas a trabajadores (ej. "Controlar la heladera del
--  depósito"), ver docs/tareas-pendientes.md #2. No reutiliza la
--  tabla `solicitudes` existente (pensada para otra cosa: pedir la
--  compra de un producto), es un módulo aparte.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `tareas_asignadas` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_crea_id`     INT UNSIGNED  NOT NULL COMMENT 'Quién asignó la tarea (el encargado)',
  `titulo`              VARCHAR(150)  NOT NULL,
  `descripcion`         TEXT          DEFAULT NULL,
  `modo_destino`        ENUM('usuario_especifico','turno_abierto') NOT NULL,
  `usuario_destino_id`  INT UNSIGNED  DEFAULT NULL COMMENT 'Solo tiene valor si modo_destino = usuario_especifico',
  `estado`              ENUM('pendiente','aceptada','rechazada','finalizada') NOT NULL DEFAULT 'pendiente',
  `usuario_responde_id` INT UNSIGNED  DEFAULT NULL COMMENT 'Quién aceptó o rechazó',
  `respondido_at`       DATETIME      DEFAULT NULL,
  `motivo_rechazo`      TEXT          DEFAULT NULL,
  `aplazado_hasta`      DATETIME      DEFAULT NULL COMMENT 'Mientras esté en el futuro, el cartel no se muestra aunque el estado siga pendiente/aceptada. No es un estado propio: se puede aplazar desde pendiente o desde aceptada, y hay que poder volver al que corresponda',
  `usuario_finaliza_id` INT UNSIGNED  DEFAULT NULL COMMENT 'Quién la marcó como terminada',
  `finalizado_at`       DATETIME      DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tarea_estado`  (`estado`),
  KEY `idx_tarea_destino` (`usuario_destino_id`),
  CONSTRAINT `fk_tarea_crea`     FOREIGN KEY (`usuario_crea_id`)     REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tarea_destino`  FOREIGN KEY (`usuario_destino_id`)  REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tarea_responde` FOREIGN KEY (`usuario_responde_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tarea_finaliza` FOREIGN KEY (`usuario_finaliza_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
