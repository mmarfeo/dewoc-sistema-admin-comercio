-- ============================================================
--  Módulo: Otros Ingresos
--  Ingresos manuales que no son ventas ni aportes de socios
--  (ej. venta de un activo, intereses, etc.) — usados en el
--  desglose de "Ingresos" del Resumen Económico.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `ingresos_otros` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `fecha`       DATE          NOT NULL,
  `descripcion` VARCHAR(255)  NOT NULL,
  `monto`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `notas`       TEXT          DEFAULT NULL,
  `usuario_id`  INT UNSIGNED  NOT NULL,
  `eliminado`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_io_fecha` (`fecha`),
  CONSTRAINT `fk_io_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
