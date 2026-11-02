-- Licencias de los módulos que se cobran aparte del plan de Dewoc (ver
-- docs/dewoc-cobros-checklist.md, tarea 1A.4). La primera es Dewoc Cobros (`dewoc_cobros`).
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.
--
-- NO hay columna "activa" a propósito: el estado se calcula SIEMPRE verificando la firma
-- del texto (core/Licencias.php), así que editar esta tabla a mano no habilita nada. Las
-- demás columnas son una copia de lo que dice la licencia, para listarla sin verificar.

CREATE TABLE IF NOT EXISTS `licencias` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `licencia_id`  VARCHAR(40)   NOT NULL COMMENT 'Identificador que le asigna el proveedor al emitirla',
  `modulo`       VARCHAR(40)   NOT NULL COMMENT 'dewoc_cobros, …',
  `texto`        TEXT          NOT NULL COMMENT 'La licencia completa, tal como se pegó (DWC1.…)',
  `dispositivos` SMALLINT UNSIGNED NOT NULL,
  `desde`        DATE          NOT NULL,
  `hasta`        DATE          NOT NULL,
  `emitida_at`   DATETIME      NOT NULL,
  `cargada_por`  INT UNSIGNED  DEFAULT NULL COMMENT 'usuarios.id del admin que la pegó',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_licencia_id` (`licencia_id`),
  KEY `idx_licencia_modulo_emitida` (`modulo`, `emitida_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
