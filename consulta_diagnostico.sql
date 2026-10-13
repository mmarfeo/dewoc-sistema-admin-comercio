SELECT
    fc.id                AS factura_id,
    fc.fecha,
    p.razon_social        AS proveedor,
    fc.nro_comprobante,
    fc.estado             AS estado_factura,
    fc.total_con_iva      AS total_factura,
    mp.nombre             AS item_nombre,
    fcd.cantidad,
    fcd.precio_unitario,
    fcd.subtotal_con_iva  AS total_de_esta_linea,
    (SELECT COUNT(*) FROM facturas_compra_detalle WHERE factura_compra_id = fc.id)
                          AS total_items_en_factura,
    (SELECT COALESCE(SUM(pf.monto_aplicado), 0)
     FROM pagos_facturas pf
     JOIN pagos_proveedores pp ON pp.id = pf.pago_id
     WHERE pf.factura_id = fc.id AND pp.estado = 'activo')
                          AS monto_pagado_en_factura
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc  ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp  ON mp.id = fcd.materia_prima_id
LEFT JOIN proveedores p  ON p.id = fc.proveedor_id
WHERE mp.nombre IN (
    'Aguinaldo de Aldana',
    'Aguinaldo de Lautaro',
    'Alquiler de Generador',
    'Alquiler del local comercial',
    'Comisión de transferencia con tarjeta de crédito',
    'Copia de llaves del negocio',
    'Cuota Fondo de Comercio',
    'Habilitación municipal',
    'Honorarios por servicios de mantenimiento',
    'Honorarios por servicios profesionales contables',
    'Honorarios x Inscripcion Monotributo',
    'Horas Extras Aldana',
    'Internet wifi',
    'Incripcion al monotributo',
    'Pago a Lautaro',
    'Plan inicial 20',
    'Seguro de empleado Lautaro y Aldana',
    'Seguro Integral MAX de Comercio',
    'Sueldo 2026 Aldana',
    'Sueldo Aldana',
    'Viaticos Lautaro'
)
ORDER BY mp.nombre, fc.fecha;
