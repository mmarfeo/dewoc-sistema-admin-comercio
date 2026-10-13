-- Permite marcar productos que no necesitan control de stock (ej. café, productos
-- hechos al momento): el POS los trata como siempre disponibles, no los descuenta
-- del inventario y no los marca como "agotado" ni "stock bajo".
-- Los registros existentes quedan en 1 (comportamiento actual, sin cambios).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `productos`
  ADD COLUMN `controla_stock`
    TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = siempre disponible, no descuenta stock ni se marca agotado/bajo stock'
    AFTER `stock_actual`;
