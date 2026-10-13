-- ============================================================
--  Limpieza de gastos duplicados (cargados a mano + generados
--  automáticamente por Gastos Recurrentes para el mismo mes).
--
--  Da de baja (soft-delete, eliminado=1) las 6 filas AUTO-GENERADAS
--  que duplican un gasto ya cargado a mano o ya pagado:
--    97  Alquiler Comercial          (duplica manual #88, pendiente)
--    101 Honorarios Profesionales     (duplica manual #16, ya pagado)
--    98  Impuestos Municipales y Tasas (duplica manual #95, pendiente)
--    104 Seguro (uno de los dos)      (duplica manual #62, ya pagado)
--    105 Seguro (el otro)             (duplica manual #77, ya pagado)
--    99  Sistema Nucleto It           (duplica manual #89, pendiente)
--
--  No toca "Teléfono" (#102) ni "Internet Starlink" (#103) — son
--  gastos reales distintos, no duplicados.
--  No toca los duplicados de Seguros de agosto-diciembre (ambos
--  manuales) — no están relacionados con este bug.
-- ============================================================

START TRANSACTION;

-- Verificación: ninguna de estas 6 debería tener pagos aplicados
-- (todas figuraban en estado "pendiente" en el diagnóstico).
SELECT g.id, g.descripcion, g.estado,
       (SELECT COUNT(*) FROM pagos_gastos pg WHERE pg.gasto_id = g.id) AS pagos_aplicados
FROM gastos g
WHERE g.id IN (97, 101, 98, 104, 105, 99);

-- Si alguna fila de arriba tiene pagos_aplicados > 0, PARAR ACÁ y avisar
-- (no correr el UPDATE de abajo para esa fila).

UPDATE gastos
SET eliminado = 1
WHERE id IN (97, 101, 98, 104, 105, 99)
  AND id NOT IN (SELECT DISTINCT gasto_id FROM pagos_gastos);

SELECT 'gastos dados de baja' AS chequeo, ROW_COUNT() AS cantidad;

COMMIT;
