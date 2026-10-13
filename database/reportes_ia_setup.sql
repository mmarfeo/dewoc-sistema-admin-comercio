-- Reportes generados por el Chat IA (predefinidos parametrizados o consultas ad-hoc),
-- guardados como snapshot (no se re-ejecutan al verlos) y descargables en Excel.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `reportes_ia` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `titulo`        VARCHAR(150) NOT NULL,
  `descripcion`   VARCHAR(500) DEFAULT NULL,
  `tipo`          VARCHAR(30)  NOT NULL COMMENT 'personalizado, o uno de los tipos predefinidos (ventas, compras, stock, caja, produccion, pagos, nomina)',
  `sql_ejecutado` TEXT         DEFAULT NULL COMMENT 'Solo para tipo personalizado, la consulta SELECT que se ejecutó',
  `parametros`    JSON         DEFAULT NULL COMMENT 'Filtros usados (desde, hasta, etc.), para mostrar contexto en la vista',
  `columnas`      JSON         NOT NULL COMMENT 'Nombres de columnas en orden, para tabla y export consistentes',
  `resultado`     LONGTEXT     NOT NULL COMMENT 'JSON del array de filas, snapshot al momento de generar el reporte',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ria_usuario` (`usuario_id`),
  KEY `idx_ria_created` (`created_at`),
  CONSTRAINT `fk_ria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
