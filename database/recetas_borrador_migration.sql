-- Simulador de Recetas: recetas hipotéticas para costear posibles productos nuevos,
-- mezclando ingredientes reales del sistema con ingredientes "inventados" (nombre +
-- costo estimado tipeado a mano, sin FK a ninguna tabla real). Ejecutar una vez.

ALTER TABLE `recetas`
  ADD COLUMN IF NOT EXISTS `es_borrador` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Receta hipotética del Simulador — no es una receta de producción real' AFTER `activo`,
  ADD COLUMN IF NOT EXISTS `precio_venta_estimado` DECIMAL(12,2) DEFAULT NULL AFTER `es_borrador`;

ALTER TABLE `receta_detalle`
  MODIFY COLUMN `tipo_ingrediente` ENUM('materia_prima','insumo','ingrediente_generico','receta','ingrediente_libre') NOT NULL,
  ADD COLUMN IF NOT EXISTS `ingrediente_libre_nombre` VARCHAR(120) DEFAULT NULL AFTER `receta_ingrediente_id`,
  ADD COLUMN IF NOT EXISTS `ingrediente_libre_costo_unitario` DECIMAL(12,2) DEFAULT NULL AFTER `ingrediente_libre_nombre`,
  ADD COLUMN IF NOT EXISTS `ingrediente_libre_unidad` VARCHAR(20) DEFAULT NULL AFTER `ingrediente_libre_costo_unitario`;
