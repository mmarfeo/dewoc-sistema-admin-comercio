-- Pone en 0 el desvio_pct NEGATIVO de materias primas y productos.
--
-- Un desvío es una PÉRDIDA (robo, rotura, consumo interno) y solo puede sumar al costo
-- (`costo × (1 + desvio_pct/100)`). Un valor negativo viene de un SOBRANTE en el conteo
-- (se contó más de lo que el sistema esperaba), no de una pérdida — y con -999,99 el
-- multiplicador daba -8,9999: costo negativo en recetas, márgenes y reportes (ej. los
-- 6 sabores de "Helado en balde", cuyo costo daba negativo y hundía "Cucurucho Doble").
-- Ya corregido en el código (DesvioModel::recalibrarDesvioPct() y la carga manual en
-- MateriasPrimasController/ProductosController); esto limpia lo que ya quedó guardado.
-- No toca desvio_historial (el sobrante queda registrado como dato histórico).
-- Ejecutar una sola vez desde phpMyAdmin/Hostinger.

UPDATE materias_primas SET desvio_pct = 0 WHERE desvio_pct < 0;
UPDATE productos       SET desvio_pct = 0 WHERE desvio_pct < 0;

-- Verificación (debe devolver 0 filas):
--   SELECT id, nombre, desvio_pct FROM materias_primas WHERE desvio_pct < 0
--   UNION ALL
--   SELECT id, nombre, desvio_pct FROM productos WHERE desvio_pct < 0;
--
-- OJO: este UPDATE cambia el costo de las MP por SQL directo, sin pasar por la propagación
-- a recetas/productos (RecetaModel::propagarCambioCosto()). Las ventas NUEVAS ya usan el
-- costo correcto al instante (se calcula en vivo al vender), pero productos.precio_costo
-- de los elaborados/promos se actualiza recién al guardar cada receta o en la próxima
-- compra de sus ingredientes.
