-- Agrega índices FULLTEXT en las columnas nombre de ítems.
-- MySQL construye el índice desde los datos existentes; no se modifica ningún registro.
-- Ejecutar una sola vez. Si ya existen los índices, el IF NOT EXISTS los ignora.

ALTER TABLE `materias_primas` ADD FULLTEXT INDEX `ft_mp_nombre` (`nombre`);
ALTER TABLE `insumos`         ADD FULLTEXT INDEX `ft_ins_nombre` (`nombre`);
ALTER TABLE `productos`       ADD FULLTEXT INDEX `ft_prod_nombre` (`nombre`);
ALTER TABLE `proveedores`     ADD FULLTEXT INDEX `ft_prov_razon` (`razon_social`);
