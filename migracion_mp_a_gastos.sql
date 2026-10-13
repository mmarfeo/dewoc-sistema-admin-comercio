-- ============================================================
-- MIGRACIÓN: Materias Primas mal cargadas → Sueldos/Viáticos/Gastos
-- ⚠️ HACÉ UN BACKUP DE LA BASE ANTES DE CORRER ESTO (phpMyAdmin →
--    Exportar, o desde la app en /admin/backup).
-- Este script SÍ modifica datos. Revisado paso a paso contra el
-- diagnóstico de 88 filas + detalle de facturas #2, #190, #314.
-- ============================================================

START TRANSACTION;

-- ── PARTE 0: Corregir factura #314 (Supermercado) ──────────────────────
-- La línea "Aguinaldo de Aldana" (cant=2, $2520 c/u = $5040) en realidad
-- corresponde a "Fiambre Jamón cocido" (2700+2340=$5040). Se corrige solo
-- la referencia del ítem; cantidad/precio/total quedan igual, así que el
-- pago ya aplicado a esta factura sigue siendo válido.
INSERT INTO materias_primas (nombre, stock_minimo, stock_actual, costo_unitario, activo, eliminado)
SELECT 'Fiambre Jamón cocido', 0, 0, 2520, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM materias_primas WHERE nombre = 'Fiambre Jamón cocido');

UPDATE facturas_compra_detalle
SET materia_prima_id = (SELECT id FROM materias_primas WHERE nombre = 'Fiambre Jamón cocido' LIMIT 1)
WHERE id = 826;

-- ── PARTE 1: Categorías de Gastos nuevas (si no existen) ───────────────
INSERT INTO gasto_categorias (nombre, tipo, parent_id, color, orden, eliminado)
SELECT 'Honorarios Profesionales', 'costo_fijo', 1, '#0d6efd', 6, 0
WHERE NOT EXISTS (SELECT 1 FROM gasto_categorias WHERE nombre = 'Honorarios Profesionales');

INSERT INTO gasto_categorias (nombre, tipo, parent_id, color, orden, eliminado)
SELECT 'Impuestos Municipales y Tasas', 'impuesto', 3, '#dc3545', 5, 0
WHERE NOT EXISTS (SELECT 1 FROM gasto_categorias WHERE nombre = 'Impuestos Municipales y Tasas');

-- ── PARTE 2: Sueldos → pagos_proveedores (tipo='sueldo') ───────────────
-- Aldana (empleado_id=2)
INSERT INTO pagos_proveedores (empleado_id, tipo, fecha, concepto, monto_efectivo, monto_total, estado, origen_pago, usuario_id, notas)
SELECT 2, 'sueldo', fc.fecha, 'Aguinaldo', fcd.subtotal_con_iva, fcd.subtotal_con_iva, 'activo', 'empresa',
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Aguinaldo de Aldana' AND fc.id <> 314 AND fc.estado <> 'anulada';

INSERT INTO pagos_proveedores (empleado_id, tipo, fecha, concepto, monto_efectivo, monto_total, estado, origen_pago, usuario_id, notas)
SELECT 2, 'sueldo', fc.fecha, 'Sueldo', fcd.subtotal_con_iva, fcd.subtotal_con_iva, 'activo', 'empresa',
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Sueldo 2026 Aldana' AND fc.estado <> 'anulada';

INSERT INTO pagos_proveedores (empleado_id, tipo, fecha, concepto, monto_efectivo, monto_total, estado, origen_pago, usuario_id, notas)
SELECT 2, 'sueldo', fc.fecha, 'Horas extras', fcd.subtotal_con_iva, fcd.subtotal_con_iva, 'activo', 'empresa',
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Horas Extras Aldana' AND fc.estado <> 'anulada';

-- Lautaro (empleado_id=1)
INSERT INTO pagos_proveedores (empleado_id, tipo, fecha, concepto, monto_efectivo, monto_total, estado, origen_pago, usuario_id, notas)
SELECT 1, 'sueldo', fc.fecha, 'Aguinaldo', fcd.subtotal_con_iva, fcd.subtotal_con_iva, 'activo', 'empresa',
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Aguinaldo de Lautaro' AND fc.estado <> 'anulada';

INSERT INTO pagos_proveedores (empleado_id, tipo, fecha, concepto, monto_efectivo, monto_total, estado, origen_pago, usuario_id, notas)
SELECT 1, 'sueldo', fc.fecha, 'Sueldo', fcd.subtotal_con_iva, fcd.subtotal_con_iva, 'activo', 'empresa',
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Pago a Lautaro' AND fc.estado <> 'anulada';

-- ── PARTE 3: Viáticos → empleados_conceptos ─────────────────────────────
INSERT INTO empleados_conceptos (empleado_id, fecha, tipo, descripcion, monto, pagado, usuario_id)
SELECT 1, fc.fecha, 'viatico', 'Viático', fcd.subtotal_con_iva, 1,
       (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Viaticos Lautaro' AND fc.estado <> 'anulada';

-- ── PARTE 4: Gastos → tabla gastos ──────────────────────────────────────
-- Alquiler
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Alquiler' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Alquiler del local comercial', 'Alquiler de Generador') AND fc.estado <> 'anulada';

-- Comisiones
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Comisiones' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre = 'Comisión de transferencia con tarjeta de crédito' AND fc.estado <> 'anulada';

-- Compras Generales (gastos puntuales)
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Compras Generales' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Copia de llaves del negocio', 'Honorarios por servicios de mantenimiento') AND fc.estado <> 'anulada';

-- Impuestos Municipales y Tasas
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Impuestos Municipales y Tasas' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Cuota Fondo de Comercio', 'Habilitación municipal') AND fc.estado <> 'anulada';

-- Honorarios Profesionales
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Honorarios Profesionales' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Honorarios por servicios profesionales contables', 'Honorarios x Inscripcion Monotributo') AND fc.estado <> 'anulada';

-- Servicios
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Servicios (luz/gas/internet)' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Internet wifi', 'Plan inicial 20') AND fc.estado <> 'anulada';

-- Seguros
INSERT INTO gastos (fecha, descripcion, monto, categoria_id, proveedor_id, estado, usuario_id, notas)
SELECT fc.fecha, mp.nombre, fcd.subtotal_con_iva,
       (SELECT id FROM gasto_categorias WHERE nombre = 'Seguros' LIMIT 1),
       fc.proveedor_id, 'pagado', (SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1),
       CONCAT('Migrado desde factura de compra #', fc.id)
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE mp.nombre IN ('Seguro de empleado Lautaro y Aldana', 'Seguro Integral MAX de Comercio') AND fc.estado <> 'anulada';

-- ── PARTE 5a: Facturas "puras" (todo su contenido migrado) → anular ────
-- No borra nada, solo cambia estado para que dejen de contar como
-- "Compras" en el Resumen Económico (ya cuentan como Sueldo/Gasto ahora).
UPDATE facturas_compra fc
JOIN facturas_compra_detalle fcd ON fcd.factura_compra_id = fc.id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
SET fc.estado = 'anulada',
    fc.notas = CONCAT(COALESCE(fc.notas, ''), ' [Migrado a Sueldos/Gastos - ver notas del registro nuevo]')
WHERE mp.nombre IN (
    'Aguinaldo de Aldana', 'Aguinaldo de Lautaro', 'Alquiler del local comercial',
    'Copia de llaves del negocio', 'Cuota Fondo de Comercio', 'Habilitación municipal',
    'Honorarios por servicios de mantenimiento', 'Honorarios por servicios profesionales contables',
    'Honorarios x Inscripcion Monotributo', 'Horas Extras Aldana', 'Internet wifi',
    'Pago a Lautaro', 'Plan inicial 20', 'Seguro de empleado Lautaro y Aldana',
    'Seguro Integral MAX de Comercio', 'Sueldo 2026 Aldana', 'Viaticos Lautaro'
)
AND fc.id NOT IN (2, 190, 314)
AND fc.estado <> 'anulada';

-- ── PARTE 5b: Facturas mezcladas (#2 y #190) → quitar solo la línea falsa ─
-- Factura #2 (Nico Papelera): se resta "Comisión de transferencia" del total,
-- quedan intactos los 8 ítems reales de librería/heladería.
UPDATE facturas_compra fc
JOIN facturas_compra_detalle fcd ON fcd.factura_compra_id = fc.id
SET fc.subtotal      = fc.subtotal - fcd.subtotal,
    fc.total_iva     = fc.total_iva - (fcd.subtotal_con_iva - fcd.subtotal),
    fc.total_con_iva = fc.total_con_iva - fcd.subtotal_con_iva,
    fc.notas         = CONCAT(COALESCE(fc.notas, ''), ' [Línea "Comisión de transferencia" migrada a Gastos]')
WHERE fcd.id = 20;

DELETE FROM facturas_compra_detalle WHERE id = 20;

-- Factura #190 (Alquilo Todo): se resta "Alquiler de Generador" del total,
-- queda intacto "Cables - enchufes - cinta aisladora - nafta".
UPDATE facturas_compra fc
JOIN facturas_compra_detalle fcd ON fcd.factura_compra_id = fc.id
SET fc.subtotal      = fc.subtotal - fcd.subtotal,
    fc.total_iva     = fc.total_iva - (fcd.subtotal_con_iva - fcd.subtotal),
    fc.total_con_iva = fc.total_con_iva - fcd.subtotal_con_iva,
    fc.notas         = CONCAT(COALESCE(fc.notas, ''), ' [Línea "Alquiler de Generador" migrada a Gastos]')
WHERE fcd.id = 403;

DELETE FROM facturas_compra_detalle WHERE id = 403;

-- ── PARTE 6: Dar de baja los ítems falsos del catálogo (soft-delete) ───
UPDATE materias_primas
SET eliminado = 1, activo = 0
WHERE nombre IN (
    'Aguinaldo de Aldana', 'Aguinaldo de Lautaro', 'Alquiler de Generador', 'Alquiler del local comercial',
    'Comisión de transferencia con tarjeta de crédito', 'Copia de llaves del negocio', 'Cuota Fondo de Comercio',
    'Habilitación municipal', 'Honorarios por servicios de mantenimiento', 'Honorarios por servicios profesionales contables',
    'Honorarios x Inscripcion Monotributo', 'Horas Extras Aldana', 'Internet wifi', 'Pago a Lautaro', 'Plan inicial 20',
    'Seguro de empleado Lautaro y Aldana', 'Seguro Integral MAX de Comercio', 'Sueldo 2026 Aldana', 'Viaticos Lautaro'
);

-- ── PARTE 7: Verificación (revisá estos números antes del COMMIT) ──────
SELECT 'pagos_proveedores creados' AS chequeo, COUNT(*) AS cantidad FROM pagos_proveedores WHERE notas LIKE 'Migrado desde factura%'
UNION ALL
SELECT 'empleados_conceptos creados', COUNT(*) FROM empleados_conceptos WHERE descripcion = 'Viático'
UNION ALL
SELECT 'gastos creados', COUNT(*) FROM gastos WHERE notas LIKE 'Migrado desde factura%'
UNION ALL
SELECT 'facturas anuladas por migración', COUNT(*) FROM facturas_compra WHERE notas LIKE '%Migrado a Sueldos/Gastos%'
UNION ALL
SELECT 'materias primas dadas de baja', COUNT(*) FROM materias_primas WHERE eliminado = 1 AND nombre IN (
    'Aguinaldo de Aldana', 'Aguinaldo de Lautaro', 'Alquiler de Generador', 'Alquiler del local comercial',
    'Comisión de transferencia con tarjeta de crédito', 'Copia de llaves del negocio', 'Cuota Fondo de Comercio',
    'Habilitación municipal', 'Honorarios por servicios de mantenimiento', 'Honorarios por servicios profesionales contables',
    'Honorarios x Inscripcion Monotributo', 'Horas Extras Aldana', 'Internet wifi', 'Pago a Lautaro', 'Plan inicial 20',
    'Seguro de empleado Lautaro y Aldana', 'Seguro Integral MAX de Comercio', 'Sueldo 2026 Aldana', 'Viaticos Lautaro'
);

-- Si llegamos hasta acá sin errores, se confirma todo en un solo paso.
-- (No dejamos esto para un COMMIT manual aparte: en phpMyAdmin cada
-- ejecución puede usar una conexión distinta, y la transacción abierta
-- de la corrida anterior se perdería antes de poder confirmarla.)
COMMIT;
