SELECT
    fcd.id AS detalle_id,
    fcd.factura_compra_id,
    fcd.tipo_item,
    COALESCE(mp.nombre, ins.nombre, prod.nombre) AS item_nombre,
    fcd.cantidad,
    fcd.precio_unitario,
    fcd.subtotal_con_iva
FROM facturas_compra_detalle fcd
LEFT JOIN materias_primas mp ON fcd.tipo_item = 'materia_prima' AND fcd.materia_prima_id = mp.id
LEFT JOIN insumos ins        ON fcd.tipo_item = 'insumo'        AND fcd.insumo_id = ins.id
LEFT JOIN productos prod     ON fcd.tipo_item = 'producto'      AND fcd.producto_id = prod.id
WHERE fcd.factura_compra_id IN (190, 2, 314)
ORDER BY fcd.factura_compra_id, fcd.id;
