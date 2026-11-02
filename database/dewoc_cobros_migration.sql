-- Dewoc Cobros: la app del celular que reenvía las notificaciones de Mercado Pago, billeteras
-- y bancos (ver docs/dewoc-cobros-checklist.md, tarea 1.1, y dewoc-cobros-servidor-plan.md).
-- Requiere database/licencias_migration.sql (el módulo se habilita con una licencia).
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.

-- ── Celulares vinculados ─────────────────────────────────────────────────────
-- Del token se guarda SOLO el hash; el valor en claro se muestra una única vez, en el QR
-- de Integraciones → Dewoc Cobros. Revocar = activo 0 (la fila queda para el historial).
CREATE TABLE IF NOT EXISTS `cobros_dispositivos` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`                 VARCHAR(80)   NOT NULL,
  `token_hash`             CHAR(64)      NOT NULL COMMENT 'SHA-256 del token Bearer. Nunca el token en claro',
  `token_ultimos4`         VARCHAR(8)    NOT NULL,
  `activo`                 TINYINT(1)    NOT NULL DEFAULT 1,
  `version_app`            VARCHAR(20)   DEFAULT NULL,
  `permiso_notificaciones` TINYINT(1)    DEFAULT NULL,
  `optimizacion_bateria`   TINYINT(1)    DEFAULT NULL COMMENT '1 = le FALTA la exclusión: Android puede cerrar la app',
  `pendientes_en_cola`     INT UNSIGNED  DEFAULT NULL,
  `ultimo_contacto_at`     DATETIME      DEFAULT NULL COMMENT 'NULL = todavía no se conectó (QR generado sin escanear)',
  `revocado_at`            DATETIME      DEFAULT NULL,
  `creado_por`             INT UNSIGNED  DEFAULT NULL,
  `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobros_disp_token` (`token_hash`),
  KEY `idx_cobros_disp_activo` (`activo`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Paquetes permitidos (C3) ─────────────────────────────────────────────────
-- El celular solo reenvía las notificaciones de estos paquetes. `lector` es la clave del
-- lector en PHP (app/Models/CobrosLectores/); NULL = sin lector todavía.
CREATE TABLE IF NOT EXISTS `cobros_paquetes` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `paquete`    VARCHAR(150)  NOT NULL COMMENT 'packageName de Android, ej. com.mercadopago.wallet',
  `nombre_app` VARCHAR(80)   NOT NULL,
  `lector`     VARCHAR(40)   DEFAULT NULL,
  `activo`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobros_paquete` (`paquete`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `cobros_paquetes` (`paquete`, `nombre_app`, `lector`, `activo`)
VALUES ('com.mercadopago.wallet', 'Mercado Pago', 'mercadopago', 1);

-- ── Configuración (una fila, id = 1) ─────────────────────────────────────────
-- paquetes_version sube cada vez que cambia cobros_paquetes: el celular la compara en el
-- latido y, si cambió, vuelve a pedir la lista. app_* = última versión publicada del APK
-- (sección 3.4 del plan), se carga a mano al publicar cada release.
CREATE TABLE IF NOT EXISTS `cobros_config` (
  `id`                 TINYINT UNSIGNED NOT NULL,
  `paquetes_version`   INT UNSIGNED  NOT NULL DEFAULT 1,
  `app_version_codigo` INT UNSIGNED  DEFAULT NULL COMMENT 'versionCode del APK',
  `app_version`        VARCHAR(20)   DEFAULT NULL COMMENT 'versionName, ej. 1.0.1',
  `app_url`            VARCHAR(255)  DEFAULT NULL COMMENT 'https:// del APK (release de GitHub)',
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `cobros_config` (`id`, `paquetes_version`) VALUES (1, 1);

-- ── Notificaciones recibidas ─────────────────────────────────────────────────
-- El texto original queda guardado para poder volver a leerlo si se corrige un lector (C14).
CREATE TABLE IF NOT EXISTS `cobros_recibidos` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36)      NOT NULL COMMENT 'UUID v5 que arma el celular con los datos de la notificación (C10)',
  `dispositivo_id` INT UNSIGNED  DEFAULT NULL,
  `paquete`        VARCHAR(150)  NOT NULL,
  `titulo`         VARCHAR(500)  DEFAULT NULL,
  `texto`          TEXT          DEFAULT NULL,
  `texto_largo`    TEXT          DEFAULT NULL,
  `recibido_at`    DATETIME      NOT NULL COMMENT 'postTime de la notificación, en la zona del comercio',
  `resultado`      ENUM('cobro','descartado','no_reconocido','sin_lector','prueba') NOT NULL,
  `lector`         VARCHAR(40)   DEFAULT NULL,
  `monto`          DECIMAL(12,2) DEFAULT NULL,
  `nombre`         VARCHAR(120)  DEFAULT NULL,
  `leido_at`       DATETIME      DEFAULT NULL COMMENT 'Última vez que pasó por un lector',
  `turno_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'Turno abierto más viejo al llegar (referencia)',
  `venta_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'Etapa 7: venta en la que se usó',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobros_recibidos_uuid` (`uuid`),
  KEY `idx_cobros_recibidos_resultado` (`resultado`, `id`),
  KEY `idx_cobros_recibidos_created` (`created_at`),
  KEY `idx_cobros_recibidos_recibido` (`recibido_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Avisos cerrados en el POS, por usuario (C7) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `cobros_recibidos_cierres` (
  `cobro_id`   INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `cerrado_at` DATETIME     NOT NULL,
  PRIMARY KEY (`cobro_id`, `usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Códigos de vinculación de un solo uso (tarea 4.5) ───────────────────────
CREATE TABLE IF NOT EXISTS `cobros_codigos_vinculacion` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo_hash` CHAR(64)     NOT NULL,
  `vence_at`    DATETIME     NOT NULL,
  `usado_at`    DATETIME     DEFAULT NULL,
  `creado_por`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobros_codigo` (`codigo_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Límite de requests por dispositivo (tarea 1.5: 60 por minuto) ───────────
-- Contador por ventana de un minuto. Cada chequeo borra las ventanas viejas.
CREATE TABLE IF NOT EXISTS `cobros_rate` (
  `dispositivo_id` INT UNSIGNED NOT NULL,
  `ventana`        CHAR(16)     NOT NULL COMMENT 'YYYY-mm-dd HH:ii truncado al minuto',
  `requests`       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`dispositivo_id`, `ventana`),
  KEY `idx_cobros_rate_ventana` (`ventana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
