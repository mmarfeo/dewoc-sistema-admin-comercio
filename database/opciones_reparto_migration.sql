-- Reparto proporcional de stock entre opciones elegidas (ej. "1kg" de helado con 3
-- sabores elegidos: en vez de descontar una cantidad fija por sabor, se reparten los
-- 1000gr del producto en partes iguales entre los 3 sabores elegidos — 333,33gr c/u).
-- Convive con el comportamiento existente (opciones.cantidad_consumida fijo por
-- selección, para promos por unidad como "elegí 2 de esta lista, 1 unidad cada una")
-- sin romperlo: si cantidad_a_repartir queda NULL para un producto+grupo, se sigue
-- usando el criterio fijo de siempre — ver VentaModel::_necesarioOpcion().
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `producto_grupos_opciones`
  ADD COLUMN IF NOT EXISTS `cantidad_a_repartir` DECIMAL(12,3) NULL DEFAULT NULL
    COMMENT 'Cantidad total (en la unidad base del producto, ej. gramos) a repartir en partes iguales entre las opciones elegidas en la línea. NULL = usar opciones.cantidad_consumida fijo por selección (comportamiento de siempre, para promos por unidad).'
    AFTER `maximo_selecciones`;
