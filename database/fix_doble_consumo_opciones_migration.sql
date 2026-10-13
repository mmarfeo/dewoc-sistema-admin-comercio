-- ============================================================
-- Corrige doble descuento de stock de helado en ventas directas (no promo):
-- estos productos tienen a la vez (a) una receta propia con una línea de
-- ingrediente_generico "Helado en balde x 6400 gr" (respeta el sabor elegido,
-- vía RecetaModel::descontarIngrediente()) y (b) su propia fila en
-- producto_grupos_opciones para el grupo "Helados" con cantidad_a_repartir
-- configurado — lo que dispara ADEMÁS un descuento directo por VentaModel::
-- _ajustarStockOpcion() del mismo sabor. El propio código ya está pensado para
-- que solo UNO de los dos dispare (ver comentario en VentaModel::registrar(),
-- sección "Opciones/adicionales elegidas"): dejando cantidad_a_repartir en NULL
-- acá, _necesarioOpcion() cae a cantidad_consumida (ya en 0 para estas
-- opciones), así que deja de descontar por ese lado y el único descuento real
-- queda del lado de la receta.
-- Ejecutar una sola vez sobre la base existente.
-- ============================================================

UPDATE producto_grupos_opciones pgo
JOIN grupos_opciones g ON g.id = pgo.grupo_id AND g.nombre = 'Helados'
SET pgo.cantidad_a_repartir = NULL
WHERE pgo.producto_id IN (2, 3, 20, 30, 52, 70); -- 1/2kg, 1/4kg, Cucurucho Doble, Milkshake, VASITO, waffle pez
