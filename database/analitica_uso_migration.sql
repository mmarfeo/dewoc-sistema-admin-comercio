-- Analítica de uso (ver docs/auditoria-analitica-uso.md) — frecuencia de acceso a cada
-- pantalla, agregada por día, para detectar qué módulos se usan poco/nada y ayudar a
-- decidir qué dar de baja. Es un dato de producto, no de seguridad (eso ya lo cubre
-- `audit_log` / `/auditoria`) — por eso va en tabla y pantalla aparte.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `analitica_uso_diario` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha`      DATE         NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `modulo`     VARCHAR(50)  NOT NULL COMMENT 'Primer segmento del nombre de vista, ej: "stock.inventario" -> "stock"',
  `accion`     VARCHAR(50)  NOT NULL COMMENT 'Resto del nombre de vista, ej: "stock.inventario" -> "inventario"',
  `cantidad`   INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Veces que se renderizó esa vista ese día para ese usuario',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uso_dia` (`fecha`, `usuario_id`, `modulo`, `accion`),
  KEY `idx_uso_fecha`  (`fecha`),
  KEY `idx_uso_modulo` (`modulo`),
  CONSTRAINT `fk_uso_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
