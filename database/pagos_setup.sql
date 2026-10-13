-- ============================================================
--  Módulo: Pagos a Proveedores / Sueldos
--  Ejecutar una vez sobre la base de datos existente
-- ============================================================

-- ─── Pagos ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pagos_proveedores` (
  `id`                  INT           NOT NULL AUTO_INCREMENT,
  `proveedor_id`        INT           DEFAULT NULL COMMENT 'NULL para sueldos u otros',
  `beneficiario`        VARCHAR(255)  DEFAULT NULL COMMENT 'Nombre libre (empleado, etc.)',
  `tipo`                ENUM('proveedor','sueldo','otro') NOT NULL DEFAULT 'proveedor',
  `fecha`               DATE          NOT NULL,
  `concepto`            VARCHAR(500)  DEFAULT NULL,
  `monto_mercado_pago`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_efectivo`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_debito`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_transferencia` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_cheque`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_otro`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_total`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `referencia_mp`       VARCHAR(255)  DEFAULT NULL COMMENT 'Nro de operación Mercado Pago',
  `comprobante_path`    VARCHAR(500)  DEFAULT NULL COMMENT 'Ruta al archivo adjunto',
  `notas`               TEXT          DEFAULT NULL,
  `estado`              ENUM('activo','anulado') NOT NULL DEFAULT 'activo',
  `usuario_id`          INT           NOT NULL,
  `eliminado`           TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proveedor` (`proveedor_id`),
  KEY `idx_fecha`     (`fecha`),
  KEY `idx_tipo`      (`tipo`),
  KEY `idx_estado`    (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Aplicación de pagos a facturas (parcial o total) ─────────────────────────
CREATE TABLE IF NOT EXISTS `pagos_facturas` (
  `id`             INT           NOT NULL AUTO_INCREMENT,
  `pago_id`        INT           NOT NULL,
  `factura_id`     INT           NOT NULL,
  `monto_aplicado` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pago_factura` (`pago_id`, `factura_id`),
  KEY `idx_factura` (`factura_id`),
  KEY `idx_pago`    (`pago_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
