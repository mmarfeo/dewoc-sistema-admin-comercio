-- ============================================================
-- OBSOLETO — REEMPLAZADO POR database/desvio_v2_migration.sql. NO CORRER ESTE SCRIPT.
-- El desvío ahora se calcula contra el conteo físico anterior (no contra el saldo del sistema)
-- y las filas viejas quedan marcadas 'legacy' en vez de recalcularse con la lógica anterior.
-- Se conserva solo como registro histórico del diagnóstico.
-- ============================================================
-- FIX: desvio_pct calculado con unidades mezcladas (ver docs/diagnostico-costos-mp.md §5.1)
-- ============================================================
-- DesvioModel::registrar() calculaba desvio_pct dividiendo desvio_cantidad (en la
-- unidad BASE del ítem, ej. gramos) por cantidad_comprada (cantidad de unidades de
-- COMPRA, ej. "19 baldes", sin convertir por cantidad_contenida) — daba porcentajes
-- de millones que se truncaban al guardarse (desvio_historial.desvio_pct es
-- DECIMAL(6,2), tope 9999.99) y se truncaban una SEGUNDA vez al recalibrar
-- materias_primas.desvio_pct / productos.desvio_pct (DECIMAL(5,2), tope 999.99).
-- Ya corregido en el código (DesvioModel.php): ahora divide por stock_teorico.
--
-- Este script recalcula con la fórmula correcta lo que YA está guardado (los datos
-- crudos de cada ciclo — compras, ventas, conteo — están bien; solo el % calculado
-- estaba mal) y vuelve a calibrar el desvio_pct vigente de cada ítem, con el mismo
-- criterio que DesvioModel::recalibrarDesvioPct() (promedio ponderado por
-- cantidad_comprada de cada ciclo).
--
-- Es idempotente: correrlo de nuevo no cambia el resultado. Ejecutar la sección 1
-- (PREVIEW) primero para revisar el antes/después — no modifica nada. Recién después
-- correr la sección 2 (APLICAR).


-- ══════════════════ 1. PREVIEW — SOLO LECTURA, no modifica nada ══════════════════

-- 1a. Por ciclo: cómo cambia desvio_pct de cada fila de desvio_historial.
SELECT
    dh.id, dh.item_tipo, dh.item_id,
    COALESCE(mp.nombre, p.nombre)                                    AS nombre,
    dh.stock_teorico, dh.stock_real_contado, dh.desvio_cantidad,
    dh.desvio_pct                                                    AS desvio_pct_guardado,
    ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2) AS desvio_pct_corregido
FROM desvio_historial dh
LEFT JOIN materias_primas mp ON mp.id = dh.item_id AND dh.item_tipo = 'materia_prima'
LEFT JOIN productos       p  ON p.id  = dh.item_id AND dh.item_tipo = 'producto'
WHERE dh.stock_teorico > 0
ORDER BY ABS(dh.desvio_pct - ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2)) DESC;

-- 1b. Por ítem: cómo cambia el desvio_pct vigente (el que se usa para costear recetas).
SELECT 'materia_prima' AS tipo, mp.id, mp.nombre,
       mp.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM materias_primas mp
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'materia_prima' AND stock_teorico > 0
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = mp.id
WHERE mp.eliminado = 0
UNION ALL
SELECT 'producto' AS tipo, p.id, p.nombre,
       p.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM productos p
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'producto' AND stock_teorico > 0
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = p.id
WHERE p.eliminado = 0
ORDER BY ABS(desvio_pct_actual - desvio_pct_nuevo) DESC;


**********************************************************

La respuesta de la consulta 1 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/desvio_historial/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=desvio_historial

   Mostrando filas 0 - 24 (total de 51, La consulta tardó 0.0006 segundos.)



   Mostrando filas 0 - 24 (total de 51, La consulta tardó 0.0014 segundos.)


-- 1a. Por ciclo: cómo cambia desvio_pct de cada fila de desvio_historial.
SELECT
    dh.id, dh.item_tipo, dh.item_id,
    COALESCE(mp.nombre, p.nombre)                                    AS nombre,
    dh.stock_teorico, dh.stock_real_contado, dh.desvio_cantidad,
    dh.desvio_pct                                                    AS desvio_pct_guardado,
    ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2) AS desvio_pct_corregido
FROM desvio_historial dh
LEFT JOIN materias_primas mp ON mp.id = dh.item_id AND dh.item_tipo = 'materia_prima'
LEFT JOIN productos       p  ON p.id  = dh.item_id AND dh.item_tipo = 'producto'
WHERE dh.stock_teorico > 0
ORDER BY ABS(dh.desvio_pct - ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2)) DESC;


-- 1b. Por ítem: cómo cambia el desvio_pct vigente (el que se usa para costear recetas).
SELECT 'materia_prima' AS tipo, mp.id, mp.nombre,
       mp.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM materias_primas mp
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'materia_prima' AND stock_teorico > 0
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = mp.id
WHERE mp.eliminado = 0
UNION ALL
SELECT 'producto' AS tipo, p.id, p.nombre,
       p.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM productos p
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item[...]

id	item_tipo	item_id	nombre	stock_teorico	stock_real_contado	desvio_cantidad	desvio_pct_guardado	desvio_pct_corregido	tipo	id	nombre	desvio_pct_actual	desvio_pct_nuevo	
46	materia_prima	353	PUNTA DE AGUA X KG QUESO TYBO	3.010	1966.000	-1962.990	-9999.99	-65215.61	
23	materia_prima	119	Helado en balde de Banana Split X 6400 kg	57600.000	5102.000	52498.000	9999.99	91.14	
16	materia_prima	147	Helado en balde de Mousse de Maracuyá X 6400 kg	57600.000	4982.000	52618.000	9999.99	91.35	
28	materia_prima	149	Helado en balde de Quinotos al Whisky X 6400 kg	51200.000	3770.000	47430.000	9999.99	92.64	
9	materia_prima	130	Helado en balde de Crema Americana X 6400 kg	108800.000	6288.000	102512.000	9999.99	94.22	
5	materia_prima	127	Helado en balde de Chocolate X 6400 kg	53872.000	2672.000	51200.000	9999.99	95.04	
17	materia_prima	141	Helado en balde de Granizado X 6400 kg	153600.000	6390.000	147210.000	9999.99	95.84	
24	materia_prima	133	Helado en balde de Crema Rusa X 6400 kg	51200.000	2022.000	49178.000	9999.99	96.05	
13	materia_prima	150	Helado en balde de Super Dulce de Leche X 6400 kg	166400.000	6020.000	160380.000	9999.99	96.38	
25	materia_prima	128	Helado en balde de Chocotorta X 6400 kg	160000.000	5148.000	154852.000	9999.99	96.78	
10	materia_prima	144	Helado en balde de Marroc X 6400 kg	160000.000	4948.000	155052.000	9999.99	96.91	
14	materia_prima	146	Helado en balde de Menta Granizada X 6400 kg	160000.000	4664.000	155336.000	9999.99	97.09	
19	materia_prima	134	Helado en balde de Crema Tramontana X 6400 kg	211200.000	5664.000	205536.000	9999.99	97.32	
6	materia_prima	142	Helado en balde de Kinder X 6400 kg	211200.000	4980.000	206220.000	9999.99	97.64	
26	materia_prima	136	Helado en balde de Dulce de Leche Granizado X 6400...	275200.000	4732.000	270468.000	9999.99	98.28	
22	materia_prima	137	Helado en balde de Dulce de Leche X 6400 kg	307200.000	4170.000	303030.000	9999.99	98.64	
11	materia_prima	139	Helado en balde de Frutilla a la Crema X 6400 kg	467200.000	4272.000	462928.000	9999.99	99.09	
41	materia_prima	82	Jugo Naranja Citric 3L	129000.000	1000.000	128000.000	9999.99	99.22	
37	materia_prima	303	Alfajor fuaa Blanco	1200.000	5.000	1195.000	9999.99	99.58	
38	materia_prima	302	Alfajor fuaa Negro	1200.000	3.000	1197.000	9999.99	99.75	
47	materia_prima	99	Esencia de vainilla x Litro	2006000.000	1000.000	2005000.000	9999.99	99.95	
29	materia_prima	131	Helado en balde de Crema del Cielo X 6400 kg	972800.000	350.000	972450.000	9999.99	99.96	
52	materia_prima	112	Galletitas Oreo x 1	6018.000	2.000	6016.000	9999.99	99.97	
50	materia_prima	255	Salsa de Dulce de Leche para helados	8000.000	2.000	7998.000	9999.99	99.98	
51	materia_prima	256	Salsa de Frutilla para helados	16500.000	2.000	16498.000	9999.99	99.99	
materia_prima	353	PUNTA DE AGUA X KG QUESO TYBO	-999.99	-65215.61	
producto	108	Promo 2 Milkshake	-999.99	-2900.00	
materia_prima	119	Helado en balde de Banana Split X 6400 kg	999.99	91.14	
materia_prima	147	Helado en balde de Mousse de Maracuyá X 6400 kg	999.99	91.35	
materia_prima	149	Helado en balde de Quinotos al Whisky X 6400 kg	999.99	92.64	
materia_prima	130	Helado en balde de Crema Americana X 6400 kg	999.99	94.22	
producto	104	Alfajor fuaa de chocolate Blanco	999.99	95.00	
materia_prima	127	Helado en balde de Chocolate X 6400 kg	999.99	95.04	
materia_prima	141	Helado en balde de Granizado X 6400 kg	999.99	95.84	
materia_prima	133	Helado en balde de Crema Rusa X 6400 kg	999.99	96.05	
materia_prima	150	Helado en balde de Super Dulce de Leche X 6400 kg	999.99	96.38	
materia_prima	128	Helado en balde de Chocotorta X 6400 kg	999.99	96.78	
materia_prima	144	Helado en balde de Marroc X 6400 kg	999.99	96.91	
materia_prima	146	Helado en balde de Menta Granizada X 6400 kg	999.99	97.09	
materia_prima	134	Helado en balde de Crema Tramontana X 6400 kg	999.99	97.32	
materia_prima	142	Helado en balde de Kinder X 6400 kg	999.99	97.64	
materia_prima	136	Helado en balde de Dulce de Leche Granizado X 6400...	999.99	98.28	
materia_prima	137	Helado en balde de Dulce de Leche X 6400 kg	999.99	98.64	
materia_prima	159	Huevos Blanco x 6 unidades	999.99	99.04	
materia_prima	139	Helado en balde de Frutilla a la Crema X 6400 kg	999.99	99.09	
materia_prima	82	Jugo Naranja Citric 3L	999.99	99.22	
materia_prima	303	Alfajor fuaa Blanco	999.99	99.58	
materia_prima	302	Alfajor fuaa Negro	999.99	99.75	
materia_prima	99	Esencia de vainilla x Litro	999.99	99.95	
materia_prima	131	Helado en balde de Crema del Cielo X 6400 kg	999.99	99.96	





127.0.0.1:3306/u374453216_erp_comercio/desvio_historial/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=desvio_historial

   Mostrando filas 0 - 24 (total de 51, La consulta tardó 0.0006 segundos.)



   Mostrando filas 0 - 24 (total de 51, La consulta tardó 0.0014 segundos.)


-- 1a. Por ciclo: cómo cambia desvio_pct de cada fila de desvio_historial.
SELECT
    dh.id, dh.item_tipo, dh.item_id,
    COALESCE(mp.nombre, p.nombre)                                    AS nombre,
    dh.stock_teorico, dh.stock_real_contado, dh.desvio_cantidad,
    dh.desvio_pct                                                    AS desvio_pct_guardado,
    ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2) AS desvio_pct_corregido
FROM desvio_historial dh
LEFT JOIN materias_primas mp ON mp.id = dh.item_id AND dh.item_tipo = 'materia_prima'
LEFT JOIN productos       p  ON p.id  = dh.item_id AND dh.item_tipo = 'producto'
WHERE dh.stock_teorico > 0
ORDER BY ABS(dh.desvio_pct - ROUND(dh.desvio_cantidad / NULLIF(dh.stock_teorico, 0) * 100, 2)) DESC;


-- 1b. Por ítem: cómo cambia el desvio_pct vigente (el que se usa para costear recetas).
SELECT 'materia_prima' AS tipo, mp.id, mp.nombre,
       mp.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM materias_primas mp
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'materia_prima' AND stock_teorico > 0
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = mp.id
WHERE mp.eliminado = 0
UNION ALL
SELECT 'producto' AS tipo, p.id, p.nombre,
       p.desvio_pct AS desvio_pct_actual, x.desvio_pct_nuevo
FROM productos p
JOIN (
    SELECT item_id,
           ROUND(SUM(ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2) * cantidad_comprada)
                 / SUM(cantidad_comprada), 2) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item[...]

id	item_tipo	item_id	nombre	stock_teorico	stock_real_contado	desvio_cantidad	desvio_pct_guardado	desvio_pct_corregido	tipo	id	nombre	desvio_pct_actual	desvio_pct_nuevo	
46	materia_prima	353	PUNTA DE AGUA X KG QUESO TYBO	3.010	1966.000	-1962.990	-9999.99	-65215.61	
23	materia_prima	119	Helado en balde de Banana Split X 6400 kg	57600.000	5102.000	52498.000	9999.99	91.14	
16	materia_prima	147	Helado en balde de Mousse de Maracuyá X 6400 kg	57600.000	4982.000	52618.000	9999.99	91.35	
28	materia_prima	149	Helado en balde de Quinotos al Whisky X 6400 kg	51200.000	3770.000	47430.000	9999.99	92.64	
9	materia_prima	130	Helado en balde de Crema Americana X 6400 kg	108800.000	6288.000	102512.000	9999.99	94.22	
5	materia_prima	127	Helado en balde de Chocolate X 6400 kg	53872.000	2672.000	51200.000	9999.99	95.04	
17	materia_prima	141	Helado en balde de Granizado X 6400 kg	153600.000	6390.000	147210.000	9999.99	95.84	
24	materia_prima	133	Helado en balde de Crema Rusa X 6400 kg	51200.000	2022.000	49178.000	9999.99	96.05	
13	materia_prima	150	Helado en balde de Super Dulce de Leche X 6400 kg	166400.000	6020.000	160380.000	9999.99	96.38	
25	materia_prima	128	Helado en balde de Chocotorta X 6400 kg	160000.000	5148.000	154852.000	9999.99	96.78	
10	materia_prima	144	Helado en balde de Marroc X 6400 kg	160000.000	4948.000	155052.000	9999.99	96.91	
14	materia_prima	146	Helado en balde de Menta Granizada X 6400 kg	160000.000	4664.000	155336.000	9999.99	97.09	
19	materia_prima	134	Helado en balde de Crema Tramontana X 6400 kg	211200.000	5664.000	205536.000	9999.99	97.32	
6	materia_prima	142	Helado en balde de Kinder X 6400 kg	211200.000	4980.000	206220.000	9999.99	97.64	
26	materia_prima	136	Helado en balde de Dulce de Leche Granizado X 6400...	275200.000	4732.000	270468.000	9999.99	98.28	
22	materia_prima	137	Helado en balde de Dulce de Leche X 6400 kg	307200.000	4170.000	303030.000	9999.99	98.64	
11	materia_prima	139	Helado en balde de Frutilla a la Crema X 6400 kg	467200.000	4272.000	462928.000	9999.99	99.09	
41	materia_prima	82	Jugo Naranja Citric 3L	129000.000	1000.000	128000.000	9999.99	99.22	
37	materia_prima	303	Alfajor fuaa Blanco	1200.000	5.000	1195.000	9999.99	99.58	
38	materia_prima	302	Alfajor fuaa Negro	1200.000	3.000	1197.000	9999.99	99.75	
47	materia_prima	99	Esencia de vainilla x Litro	2006000.000	1000.000	2005000.000	9999.99	99.95	
29	materia_prima	131	Helado en balde de Crema del Cielo X 6400 kg	972800.000	350.000	972450.000	9999.99	99.96	
52	materia_prima	112	Galletitas Oreo x 1	6018.000	2.000	6016.000	9999.99	99.97	
50	materia_prima	255	Salsa de Dulce de Leche para helados	8000.000	2.000	7998.000	9999.99	99.98	
51	materia_prima	256	Salsa de Frutilla para helados	16500.000	2.000	16498.000	9999.99	99.99	
materia_prima	353	PUNTA DE AGUA X KG QUESO TYBO	-999.99	-65215.61	
producto	108	Promo 2 Milkshake	-999.99	-2900.00	
materia_prima	119	Helado en balde de Banana Split X 6400 kg	999.99	91.14	
materia_prima	147	Helado en balde de Mousse de Maracuyá X 6400 kg	999.99	91.35	
materia_prima	149	Helado en balde de Quinotos al Whisky X 6400 kg	999.99	92.64	
materia_prima	130	Helado en balde de Crema Americana X 6400 kg	999.99	94.22	
producto	104	Alfajor fuaa de chocolate Blanco	999.99	95.00	
materia_prima	127	Helado en balde de Chocolate X 6400 kg	999.99	95.04	
materia_prima	141	Helado en balde de Granizado X 6400 kg	999.99	95.84	
materia_prima	133	Helado en balde de Crema Rusa X 6400 kg	999.99	96.05	
materia_prima	150	Helado en balde de Super Dulce de Leche X 6400 kg	999.99	96.38	
materia_prima	128	Helado en balde de Chocotorta X 6400 kg	999.99	96.78	
materia_prima	144	Helado en balde de Marroc X 6400 kg	999.99	96.91	
materia_prima	146	Helado en balde de Menta Granizada X 6400 kg	999.99	97.09	
materia_prima	134	Helado en balde de Crema Tramontana X 6400 kg	999.99	97.32	
materia_prima	142	Helado en balde de Kinder X 6400 kg	999.99	97.64	
materia_prima	136	Helado en balde de Dulce de Leche Granizado X 6400...	999.99	98.28	
materia_prima	137	Helado en balde de Dulce de Leche X 6400 kg	999.99	98.64	
materia_prima	159	Huevos Blanco x 6 unidades	999.99	99.04	
materia_prima	139	Helado en balde de Frutilla a la Crema X 6400 kg	999.99	99.09	
materia_prima	82	Jugo Naranja Citric 3L	999.99	99.22	
materia_prima	303	Alfajor fuaa Blanco	999.99	99.58	
materia_prima	302	Alfajor fuaa Negro	999.99	99.75	
materia_prima	99	Esencia de vainilla x Litro	999.99	99.95	
materia_prima	131	Helado en balde de Crema del Cielo X 6400 kg	999.99	99.96	



**********************************************************


-- ══════════════════ 2. APLICAR — modifica datos, correr después de revisar arriba ══════════════════

-- 2a. Recalcula desvio_pct de cada ciclo ya guardado con la fórmula corregida.
--     Los ciclos con stock_teorico = 0 quedan sin tocar (división indefinida — ya
--     eran un caso degenerado con la fórmula vieja también). El resultado se acota a
--     ±9999.99 (máximo de desvio_historial.desvio_pct, DECIMAL(6,2)): un caso como
--     "PUNTA DE AGUA" daría -65215.61 y el UPDATE fallaría por fuera de rango.
UPDATE desvio_historial
SET desvio_pct = GREATEST(-9999.99, LEAST(9999.99, ROUND(desvio_cantidad / NULLIF(stock_teorico, 0) * 100, 2)))
WHERE stock_teorico > 0;

-- 2b. Recalibra el desvio_pct vigente de cada materia prima afectada.
--     GREATEST(desvio_pct, 0): un ciclo negativo es un SOBRANTE, no una pérdida — no puede
--     abaratar el costo (mismo criterio que DesvioModel::recalibrarDesvioPct()). Tope 999.99
--     (máximo de la columna, DECIMAL(5,2)).
UPDATE materias_primas mp
JOIN (
    SELECT item_id,
           LEAST(999.99, ROUND(SUM(GREATEST(desvio_pct, 0) * cantidad_comprada) / SUM(cantidad_comprada), 2)) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'materia_prima'
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = mp.id
SET mp.desvio_pct = x.desvio_pct_nuevo;

-- 2c. Ídem para productos de reventa con desvío.
UPDATE productos p
JOIN (
    SELECT item_id,
           LEAST(999.99, ROUND(SUM(GREATEST(desvio_pct, 0) * cantidad_comprada) / SUM(cantidad_comprada), 2)) AS desvio_pct_nuevo
    FROM desvio_historial
    WHERE item_tipo = 'producto'
    GROUP BY item_id
    HAVING SUM(cantidad_comprada) > 0
) x ON x.item_id = p.id
SET p.desvio_pct = x.desvio_pct_nuevo;

-- Después de correr esto, el costo de las recetas que usan estas materias primas
-- (y precio_costo de los productos afectados) queda desactualizado hasta la próxima
-- compra de cada una (es lo que dispara la propagación — ver
-- RecetaModel::propagarCambioCosto()). Si hace falta verlo reflejado ya, hay que
-- recalcular manualmente o esperar a la próxima compra de cada ítem.
