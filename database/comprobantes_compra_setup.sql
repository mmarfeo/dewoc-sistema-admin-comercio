-- ============================================================
--  Módulo: Comprobantes adjuntos en facturas de compra
--          y origen del pago en pagos a proveedores
--  Ejecutar una vez sobre la base de datos existente
-- ============================================================

-- ─── Comprobante adjunto en factura de compra ────────────────────────────────
ALTER TABLE `facturas_compra`
  ADD COLUMN IF NOT EXISTS `comprobante_archivo` VARCHAR(500) DEFAULT NULL
    COMMENT 'Nombre de archivo en storage/compras/' AFTER `notas`;

-- ─── Origen del pago (empresa vs. aporte de socio) ───────────────────────────
ALTER TABLE `pagos_proveedores`
  ADD COLUMN IF NOT EXISTS `origen_pago`  ENUM('empresa','socio') NOT NULL DEFAULT 'empresa'
    COMMENT 'Si el dinero proviene de la empresa o de un aporte de socio' AFTER `notas`,
  ADD COLUMN IF NOT EXISTS `nombre_socio` VARCHAR(255) DEFAULT NULL
    COMMENT 'Nombre del socio cuando origen_pago = socio' AFTER `origen_pago`;
