-- ============================================================
-- Costo real de MP por venta: hoy productos.precio_costo (y el
-- costo de receta que lo alimenta) es un promedio ponderado por
-- stock entre TODAS las variantes de un ingrediente genérico
-- (ej. todos los sabores de helado) — sirve para fijar precio de
-- venta, pero no refleja lo que realmente costó una venta puntual
-- si el cliente eligió un sabor caro o uno barato. Esta columna
-- guarda, al momento de la venta, el costo real usando el/los
-- sabor(es) puntuales elegidos (ver RecetaModel::calcularCostoReal()),
-- para poder armar reportes de margen históricamente exactos sin
-- que el costo de hoy distorsione ventas pasadas.
-- Ejecutar una sola vez sobre la base existente.
-- ============================================================

ALTER TABLE `ventas_detalle`
  ADD COLUMN `costo_mp_real` DECIMAL(14,4) NULL DEFAULT NULL
    COMMENT 'Costo real de MP de esta línea (total, no unitario) al momento de la venta; NULL en ventas anteriores a esta funcionalidad'
    AFTER `subtotal_con_iva`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
