-- Agrega el ajuste de redondeo/descuento (para que el total registrado coincida
-- con el total impreso en el comprobante, incluso cuando el comprobante mismo
-- tiene un error de suma) como columna propia de la cabecera, visible en el
-- detalle de la factura (compras/ver.php).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `facturas_compra`
  ADD COLUMN `ajuste` DECIMAL(14,2) NOT NULL DEFAULT 0
    COMMENT 'Redondeo/descuento manual para que el total coincida con el comprobante'
    AFTER `total_con_iva`;
