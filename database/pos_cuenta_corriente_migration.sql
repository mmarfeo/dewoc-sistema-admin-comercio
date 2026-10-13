-- Cuenta Corriente como medio de pago del POS (ver docs/pos-cliente-cuenta-corriente-checklist.md).
-- Espejo simplificado de pagos_proveedores/pagos_facturas, del lado de clientes: cobros_clientes/
-- cobros_ventas. A diferencia de facturas_compra, `ventas` NO gana un estado nuevo — el saldo de
-- cuenta corriente de una venta siempre se calcula al vuelo (monto_cuenta_corriente menos lo
-- aplicado en cobros_ventas de cobros activos), nunca se persiste "pagada" (ver CobroClienteModel).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `monto_cuenta_corriente` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `monto_mercadopago`;

ALTER TABLE `ventas`
  MODIFY COLUMN `metodo_pago` ENUM('efectivo','debito','credito','transferencia','mercadopago','combinado','cuenta_corriente')
  NOT NULL DEFAULT 'efectivo';

ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `limite_cuenta_corriente` DECIMAL(12,2) NULL
    COMMENT 'NULL = usa el tope por defecto ($100.000, ver CobroClienteModel::TOPE_DEFAULT)'
    AFTER `condicion_iva`;

CREATE TABLE IF NOT EXISTS `cobros_clientes` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cliente_id`          INT UNSIGNED  NOT NULL,
  `fecha`               DATE          NOT NULL,
  `concepto`            VARCHAR(500)  DEFAULT NULL,
  `monto_efectivo`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_debito`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_transferencia` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_mercadopago`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monto_total`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `comprobante_path`    VARCHAR(500)  DEFAULT NULL,
  `notas`               TEXT          DEFAULT NULL,
  `estado`              ENUM('activo','anulado') NOT NULL DEFAULT 'activo',
  `usuario_id`          INT UNSIGNED  NOT NULL,
  `eliminado`           TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cc_cliente` (`cliente_id`),
  KEY `idx_cc_fecha`   (`fecha`),
  KEY `idx_cc_estado`  (`estado`),
  CONSTRAINT `fk_cc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cobros_ventas` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cobro_id`       INT UNSIGNED  NOT NULL,
  `venta_id`       INT UNSIGNED  NOT NULL,
  `monto_aplicado` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cobro_venta` (`cobro_id`, `venta_id`),
  KEY `idx_cv_venta` (`venta_id`),
  KEY `idx_cv_cobro` (`cobro_id`),
  CONSTRAINT `fk_cv_cobro` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cv_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
