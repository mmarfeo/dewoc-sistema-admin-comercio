-- Corrige 3 pagos que el Chat IA registró mal como "proveedor" antes de que existiera
-- la tool registrar_pago_sueldo (ver ChatController.php): quedaron con tipo='proveedor'
-- y vinculados a proveedores incorrectos (dos "fantasma" creados sin querer para
-- Lautaro/Aldana, y uno directamente mal atribuido a un proveedor real, Trapal S.R.L.).
--
-- Empleados: Lautaro = id 1, Aldana = id 2 (confirmado por consulta).
-- Único ítem realmente pendiente en el sistema: viático de Lautaro $30.000
-- (empleados_conceptos, fecha 2026-08-01) — el resto de los montos corresponde a
-- sueldo base/adelantos, que no tienen una fila "pendiente" a la cual atarse.
--
-- Ejecutar una sola vez.

-- 1) Recategorizar los 3 pagos como 'sueldo' del empleado correcto, sacando la
--    relación con proveedores (reales o fantasma).

-- Pago #104 — Lautaro — $450.000
UPDATE pagos_proveedores
SET tipo = 'sueldo', empleado_id = 1, proveedor_id = NULL
WHERE id = 104;

-- Pago #101 — Aldana — $90.000
UPDATE pagos_proveedores
SET tipo = 'sueldo', empleado_id = 2, proveedor_id = NULL
WHERE id = 101;

-- Pago #108 — Aldana — $90.000 (estaba mal atribuido a Trapal S.R.L., proveedor_id=1)
UPDATE pagos_proveedores
SET tipo = 'sueldo', empleado_id = 2, proveedor_id = NULL
WHERE id = 108;

-- 2) Marcar como liquidado el viático pendiente de Lautaro, contra el pago #104
--    (lo cubre de sobra: $450.000 vs $30.000). El WHERE es específico a propósito
--    (no solo el id) para no tocar nada si el dato no coincide exactamente.
UPDATE empleados_conceptos
SET pagado = 1, pago_id = 104
WHERE empleado_id = 1
  AND fecha = '2026-08-01'
  AND tipo = 'viatico'
  AND monto = 30000.00
  AND pagado = 0;

-- 3) Verificación
SELECT id, fecha, proveedor_id, empleado_id, tipo, concepto, monto_total
FROM pagos_proveedores
WHERE id IN (104, 101, 108);

SELECT id, empleado_id, fecha, tipo, descripcion, monto, pagado, pago_id
FROM empleados_conceptos
WHERE empleado_id = 1 AND fecha = '2026-08-01';
