SELECT
    gc.nombre AS categoria,
    DATE_FORMAT(g.fecha, '%Y-%m') AS mes,
    COUNT(*)                                  AS cantidad_filas,
    GROUP_CONCAT(g.id ORDER BY g.id)                                  AS ids_gasto,
    GROUP_CONCAT(g.descripcion ORDER BY g.id SEPARATOR ' | ')         AS descripciones,
    GROUP_CONCAT(g.fecha ORDER BY g.id)                               AS fechas,
    GROUP_CONCAT(COALESCE(g.recurrente_id,'manual') ORDER BY g.id)    AS origen,
    GROUP_CONCAT(g.estado ORDER BY g.id)                              AS estados,
    SUM(g.monto)                              AS suma_total
FROM gastos g
JOIN gasto_categorias gc ON gc.id = g.categoria_id
WHERE g.eliminado = 0
  AND g.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
GROUP BY g.categoria_id, DATE_FORMAT(g.fecha, '%Y-%m')
HAVING COUNT(*) > 1
ORDER BY categoria;
