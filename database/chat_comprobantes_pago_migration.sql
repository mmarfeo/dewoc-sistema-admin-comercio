-- ============================================================
--  Chat IA: registrar pagos de servicios/alquiler/impuestos/sueldo
--  desde un comprobante (foto/PDF), con aviso de duplicados.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

-- Número de comprobante/operación genérico (no específico de Mercado Pago como
-- referencia_mp), usado para reforzar la detección de duplicados cuando el OCR
-- lo puede leer del comprobante.
ALTER TABLE `gastos` ADD COLUMN `numero_comprobante` VARCHAR(100) NULL DEFAULT NULL AFTER `descripcion`;
ALTER TABLE `gastos` ADD INDEX `idx_gastos_numero_comprobante` (`numero_comprobante`);
-- Nota: si da error porque ya existen la columna/el índice, ignorar estos ALTER.

ALTER TABLE `pagos_proveedores` ADD COLUMN `numero_comprobante` VARCHAR(100) NULL DEFAULT NULL AFTER `referencia_mp`;
ALTER TABLE `pagos_proveedores` ADD INDEX `idx_pagos_numero_comprobante` (`numero_comprobante`);
-- Nota: si da error porque ya existen la columna/el índice, ignorar estos ALTER.

-- Categorías de gasto que faltaban para clasificar pagos registrados desde el Chat IA.
-- Inserción por nombre (no por id fijo) para no chocar con categorías que ya se hayan
-- agregado a mano desde Gastos → Categorías en cualquier momento desde la instalación.
INSERT INTO `gasto_categorias` (`nombre`, `tipo`, `parent_id`, `color`, `orden`)
SELECT 'Honorarios', 'costo_fijo', 1, '#0d6efd', 6
WHERE NOT EXISTS (SELECT 1 FROM `gasto_categorias` WHERE `nombre` = 'Honorarios' AND `parent_id` = 1);

INSERT INTO `gasto_categorias` (`nombre`, `tipo`, `parent_id`, `color`, `orden`)
SELECT 'Otros gastos', 'costo_fijo', 1, '#6c757d', 7
WHERE NOT EXISTS (SELECT 1 FROM `gasto_categorias` WHERE `nombre` = 'Otros gastos' AND `parent_id` = 1);

INSERT INTO `gasto_categorias` (`nombre`, `tipo`, `parent_id`, `color`, `orden`)
SELECT 'Otros impuestos', 'impuesto', 3, '#dc3545', 5
WHERE NOT EXISTS (SELECT 1 FROM `gasto_categorias` WHERE `nombre` = 'Otros impuestos' AND `parent_id` = 3);
