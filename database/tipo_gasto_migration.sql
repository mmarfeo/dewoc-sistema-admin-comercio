-- Agrega tipo_gasto a materias_primas, insumos y productos.
-- Los registros existentes quedan con el valor por defecto 'costo_variable'.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `materias_primas`
  ADD COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados'
    AFTER `categoria_id`;

ALTER TABLE `insumos`
  ADD COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados'
    AFTER `categoria_id`;

ALTER TABLE `productos`
  ADD COLUMN `tipo_gasto`
    ENUM('costo_variable','costo_fijo','impuesto')
    NOT NULL DEFAULT 'costo_variable'
    COMMENT 'Clasificación para el Estado de Resultados'
    AFTER `categoria_id`;
