-- Verifica si el aviso "Datos truncados para la columna costo_reconstruido" del script
-- anterior (materia_prima_costo_unitario_precision_fix.sql) implicó pérdida real de
-- precisión, o si fue solo el aviso normal de MySQL al redondear a los 4 decimales finales
-- de costo_unitario (que es intencional).
--
-- Recalcula el costo desde el historial de compras con 8 decimales explícitos (CAST) y lo
-- compara contra lo que quedó guardado. Si esta consulta NO devuelve ninguna fila, todo está
-- bien y el aviso anterior era inofensivo. Si devuelve filas, esas materias primas quedaron
-- con un costo_unitario incorrecto y hay que re-aplicar el UPDATE con el CAST explícito.

SELECT
    mp.id,
    mp.nombre,
    mp.costo_unitario                    AS guardado,
    ROUND(hist.costo_reconstruido, 4)    AS deberia_ser,
    hist.costo_reconstruido              AS calculo_con_8_decimales
FROM materias_primas mp
JOIN (
    SELECT
        fcd.materia_prima_id,
        CAST(
            SUM(fcd.cantidad * fcd.precio_unitario)
              / NULLIF(SUM(fcd.cantidad * COALESCE(fcd.cantidad_contenida, 1)), 0)
        AS DECIMAL(20,8)) AS costo_reconstruido
    FROM facturas_compra_detalle fcd
    JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
    WHERE fc.eliminado = 0
      AND fc.estado <> 'anulada'
      AND fcd.materia_prima_id IS NOT NULL
    GROUP BY fcd.materia_prima_id
) hist ON hist.materia_prima_id = mp.id
WHERE mp.cantidad_contenida IS NOT NULL
  AND mp.cantidad_contenida > 0
  AND mp.cantidad_contenida <> 1
  AND ABS(mp.costo_unitario - ROUND(hist.costo_reconstruido, 4)) > 0.00005;
