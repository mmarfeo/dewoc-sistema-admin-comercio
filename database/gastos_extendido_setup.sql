-- ============================================================
--  Ampliación del módulo Gastos: proveedor, comprobante adjunto
--  y estado pendiente/pagado — reutiliza el circuito de pagos
--  ya existente en "Pagos a Proveedores" (misma lógica que
--  facturas_compra + pagos_facturas), sin tocar stock.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `gastos`
  ADD COLUMN `proveedor_id`         INT UNSIGNED NULL DEFAULT NULL AFTER `categoria_id`,
  ADD COLUMN `comprobante_archivo`  VARCHAR(255) NULL DEFAULT NULL AFTER `notas`,
  ADD COLUMN `estado`               ENUM('pendiente','pagado') NOT NULL DEFAULT 'pagado' AFTER `comprobante_archivo`;
-- Nota: si da error porque ya existen las columnas, ignorar este ALTER.

ALTER TABLE `gastos`
  ADD CONSTRAINT `fk_gasto_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la constraint, ignorar este ALTER.

ALTER TABLE `gastos` ADD INDEX `idx_gasto_proveedor` (`proveedor_id`);
ALTER TABLE `gastos` ADD INDEX `idx_gasto_estado`    (`estado`);
-- Nota: si da error porque ya existen los índices, ignorar estos ALTER.

-- Aplicación de pagos a gastos (parcial o total) — espejo de `pagos_facturas`
CREATE TABLE IF NOT EXISTS `pagos_gastos` (
  `id`             INT           NOT NULL AUTO_INCREMENT,
  `pago_id`        INT           NOT NULL,
  `gasto_id`       INT UNSIGNED  NOT NULL,
  `monto_aplicado` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pago_gasto` (`pago_id`, `gasto_id`),
  KEY `idx_pg_gasto` (`gasto_id`),
  KEY `idx_pg_pago`  (`pago_id`),
  CONSTRAINT `fk_pg_pago`  FOREIGN KEY (`pago_id`)  REFERENCES `pagos_proveedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pg_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
