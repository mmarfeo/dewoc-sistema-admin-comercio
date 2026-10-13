-- Normaliza stock_actual y costo_unitario de materias primas a la unidad BASE
-- (la misma que ya declara unidad_medida_id, ej. gramo/mililitro), en vez de la
-- unidad de compra (ej. "1 bidón").
--
-- Hasta ahora: stock_actual/costo_unitario estaban en unidad de compra, y recién
-- al costear una receta se dividía costo_unitario / cantidad_contenida para pasar
-- a la unidad base. A partir de este cambio, FacturaCompraModel::actualizarStock()
-- ya suma "cantidad × cantidad_contenida" directo al stock en cada compra nueva —
-- así que los ítems que YA tenían cantidad_contenida cargado (≠ 1) hay que
-- convertirlos una sola vez para que el stock/costo existente quede coherente con
-- las compras nuevas.
--
-- cantidad_contenida del ítem NO se toca: sigue funcionando igual que antes como
-- valor sugerido para precargar la próxima compra de este ítem en el chat/formulario.
--
-- IMPORTANTE: ejecutar una sola vez. Si se corre dos veces, vuelve a multiplicar/
-- dividir el stock y el costo, dejándolos mal. Correr recién después de haber
-- desplegado el código actualizado (FacturaCompraModel.php, RecetaModel.php,
-- DesvioModel.php, IngredienteGenericoModel.php, SeparacionIngresosModel.php,
-- ProductoModel.php, StockModel.php).

UPDATE materias_primas
SET stock_actual   = stock_actual * cantidad_contenida,
    costo_unitario  = costo_unitario / cantidad_contenida
WHERE cantidad_contenida IS NOT NULL
  AND cantidad_contenida > 0
  AND cantidad_contenida <> 1;
