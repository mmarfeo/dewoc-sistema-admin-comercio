-- Servidor de licencias automáticas (ver docs/dewoc-cobros-checklist.md, decisión C22 y
-- etapa 1B). Solo se usa en la instalación que hace de servidor (LICENCIAS_SERVIDOR=1 en el
-- .env, hoy erpcomercio.dewoc.com). En las demás la tabla queda vacía y no molesta.
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.
--
-- Una fila por instalación y módulo: quién pidió la licencia, la última que se le emitió y si
-- está dado de baja. La baja es lo único que frena la renovación automática.

CREATE TABLE IF NOT EXISTS `licencias_clientes` (
  `id`                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `instalacion_id`      CHAR(36)          NOT NULL COMMENT 'UUID de la instalación que pidió la licencia',
  `modulo`              VARCHAR(40)       NOT NULL COMMENT 'dewoc_cobros, …',
  `comercio`            VARCHAR(150)      NOT NULL,
  `url`                 VARCHAR(255)      DEFAULT NULL COMMENT 'Dirección de ese Dewoc, informada por él mismo',
  `dispositivos`        SMALLINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Celulares de las próximas licencias',
  `estado`              ENUM('activo','baja') NOT NULL DEFAULT 'activo' COMMENT 'baja = no se le emiten más',
  `emisiones`           INT UNSIGNED      NOT NULL DEFAULT 0,
  `ultima_licencia_id`  VARCHAR(40)       DEFAULT NULL,
  `ultima_licencia`     TEXT              DEFAULT NULL COMMENT 'Texto DWC1.… de la última emitida',
  `ultima_hasta`        DATE              DEFAULT NULL,
  `ultima_solicitud_at` DATETIME          DEFAULT NULL,
  `created_at`          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cliente_instalacion_modulo` (`instalacion_id`, `modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
