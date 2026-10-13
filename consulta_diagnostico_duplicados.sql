SELECT
    g.recurrente_id,
    gr.descripcion AS plantilla,
    g.fecha,
    COUNT(*)              AS cantidad_filas,
    GROUP_CONCAT(g.id ORDER BY g.id)     AS ids_gasto,
    GROUP_CONCAT(g.estado ORDER BY g.id) AS estados,
    SUM(g.monto)           AS suma_actual_duplicada
FROM gastos g
JOIN gastos_recurrentes gr ON gr.id = g.recurrente_id
WHERE g.eliminado = 0
  AND g.recurrente_id IS NOT NULL
GROUP BY g.recurrente_id, g.fecha
HAVING COUNT(*) > 1
ORDER BY plantilla;
