-- ============================================================
--  Deuda de la empresa hacia los socios (ver docs de la conversación que armó
--  esto): todo pago con origen_pago='socio' en pagos_proveedores (factura/compra/
--  sueldo pagado con plata propia de un socio, o un aporte de capital "libre" —
--  tipo='aporte' nuevo, sin atarlo a pagar nada puntual) es plata que la empresa
--  le debe devolver a ese socio. Antes esto solo alimentaba las tarjetas de
--  resumen de "cuánto puso cada uno" (PagoProveedorModel::resumenPorOrigen());
--  ahora también genera una deuda real, visible en "Saldos pendientes de pago"
--  (/pagos-proveedores), que se salda registrando un reembolso — mismo patrón
--  que pagar una factura de proveedor (pagos_facturas/pagos_gastos).
--  Ejecutar una sola vez desde phpMyAdmin.
-- ============================================================

ALTER TABLE `pagos_proveedores`
  MODIFY COLUMN `tipo` ENUM('proveedor','sueldo','otro','aporte') NOT NULL DEFAULT 'proveedor';

CREATE TABLE IF NOT EXISTS `reembolsos_socios` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `socio_id`            INT UNSIGNED  NOT NULL,
  `fecha`               DATE          NOT NULL,
  `concepto`            VARCHAR(255)  DEFAULT NULL,
  `monto_mercado_pago`  DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_efectivo`      DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_debito`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_transferencia` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_cheque`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_otro`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_total`         DECIMAL(14,2) NOT NULL,
  `comprobante_path`    VARCHAR(255)  DEFAULT NULL,
  `notas`               TEXT          DEFAULT NULL,
  `estado`              ENUM('activo','anulado') NOT NULL DEFAULT 'activo',
  `usuario_id`          INT UNSIGNED  NOT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rs_socio` (`socio_id`),
  KEY `idx_rs_fecha` (`fecha`),
  CONSTRAINT `fk_rs_socio`   FOREIGN KEY (`socio_id`)   REFERENCES `socios`   (`id`),
  CONSTRAINT `fk_rs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reembolsos_socios_aportes` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `reembolso_id`   INT UNSIGNED  NOT NULL,
  -- INT sin UNSIGNED a propósito: pagos_proveedores.id es INT (no UNSIGNED) — MySQL
  -- exige que el tipo de la FK coincida exacto, con signo incluido, o tira error 150
  -- al crear la tabla (pasó justo acá: "Foreign key constraint is incorrectly formed").
  `pago_id`        INT           NOT NULL COMMENT 'FK a pagos_proveedores — el aporte del socio que se está saldando',
  `monto_aplicado` DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reembolso_pago` (`reembolso_id`, `pago_id`),
  KEY `idx_rsa_reembolso` (`reembolso_id`),
  KEY `idx_rsa_pago` (`pago_id`),
  CONSTRAINT `fk_rsa_reembolso` FOREIGN KEY (`reembolso_id`) REFERENCES `reembolsos_socios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsa_pago`      FOREIGN KEY (`pago_id`)      REFERENCES `pagos_proveedores`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
