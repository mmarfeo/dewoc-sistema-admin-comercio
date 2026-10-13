-- ============================================================
--  Módulo: Feriados
--  Calendario de feriados usado para calcular automáticamente
--  el recargo de las jornadas trabajadas por empleados.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `feriados` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha`      DATE         NOT NULL,
  `nombre`     VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_feriado_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
