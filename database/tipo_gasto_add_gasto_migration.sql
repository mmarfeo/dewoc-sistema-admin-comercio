-- Agrega 'gasto' como 4ta clasificación de tipo_gasto en materias_primas, insumos y productos.
-- Uso: ítems que no son mercadería/inventario real (ej. un seguro o un servicio cargado
-- como "ítem" de una factura de compra para poder usar el circuito de comprobante/pago),
-- distinto de costo_variable / costo_fijo / impuesto.
-- Ejecutar una sola vez desde phpMyAdmin. Los registros existentes no se modifican.

ALTER TABLE `materias_primas`
  MODIFY COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto','gasto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados';

ALTER TABLE `insumos`
  MODIFY COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto','gasto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados';

ALTER TABLE `productos`
  MODIFY COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto','gasto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados';
