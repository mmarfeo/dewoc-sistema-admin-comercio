-- ============================================================
-- DIAGNÓSTICO DE COSTOS DE MATERIAS PRIMAS Y RECETAS — SOLO LECTURA
-- ============================================================
-- Ninguna consulta de este archivo modifica datos. Se corre entera o
-- sección por sección contra la base real (Hostinger) para encontrar
-- POR QUÉ un costo de materia prima o receta "no tiene sentido":
-- factura mal cargada, cantidad_contenida inconsistente, unidad de
-- receta equivocada, merma/desvío con valores absurdos, etc.
--
-- Ver docs/diagnostico-costos-mp.md para la explicación de cada
-- síntoma, cómo interpretar cada consulta y cómo corregir cada causa.
-- ============================================================


-- ── 1. Deriva del Costo Promedio Ponderado (CPP) ──────────────────
-- Recalcula desde cero el costo_unitario de cada materia prima repasando
-- TODO su historial real de compras (igual fórmula que usa el sistema en
-- FacturaCompraModel::recalcularCostoDesdeHistorial) y lo compara contra
-- lo que hoy está guardado en la ficha. Si aparecen filas acá, el costo
-- guardado quedó "desincronizado" de las facturas — típicamente por una
-- migración corrida a destiempo, una edición manual directa a la tabla,
-- o un bug ya corregido en el pasado que dejó el valor viejo pisado.
SELECT
    mp.id,
    mp.nombre,
    mp.costo_unitario                              AS costo_guardado,
    ROUND(hist.costo_reconstruido, 4)               AS costo_segun_historial,
    ROUND(mp.costo_unitario - hist.costo_reconstruido, 4) AS diferencia
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
    WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
      AND fcd.materia_prima_id IS NOT NULL
    GROUP BY fcd.materia_prima_id
) hist ON hist.materia_prima_id = mp.id
WHERE mp.eliminado = 0
  AND ABS(mp.costo_unitario - hist.costo_reconstruido) > 0.01
ORDER BY ABS(mp.costo_unitario - hist.costo_reconstruido) DESC;


******************************************************************************

--La respuesta de la consulta 1 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/materias_primas/		https://auth-db693.hstgr.io/index.php?route=/database/sql&db=u374453216_erp_comercio&lang=es

   Mostrando filas 0 - 61 (total de 62, La consulta tardó 0.0046 segundos.)


SELECT
    mp.id,
    mp.nombre,
    mp.costo_unitario                              AS costo_guardado,
    ROUND(hist.costo_reconstruido, 4)               AS costo_segun_historial,
    ROUND(mp.costo_unitario - hist.costo_reconstruido, 4) AS diferencia
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
    WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
      AND fcd.materia_prima_id IS NOT NULL
    GROUP BY fcd.materia_prima_id
) hist ON hist.materia_prima_id = mp.id
WHERE mp.eliminado = 0
  AND ABS(mp.costo_unitario - hist.costo_reconstruido) > 0.01
ORDER BY ABS(mp.costo_unitario - hist.costo_reconstruido) DESC;


id	nombre	costo_guardado	costo_segun_historial	diferencia	
40	CAFÉ BLEND ESTILO ITALIANO X1/4KG DP	754858.6721	11956.8800	742901.7921	
43	CAFÉ SANTOS FINO X1/4KG DP	686471.8481	10873.6400	675598.2081	
179	Levite de Pomelo x 500	79667.3052	1264.5400	78402.7652	
41	CAFÉ COLOMBIA GUANES GENUINO X1/4KG	83.4915	20223.2100	-20139.7185	
104	Farmacia - Medicamentos	17000.0000	17.0000	16983.0000	
223	Placa Pan Focaccia de Romero	16599.7953	708.3333	15891.4620	
235	Pure de papas	-13650.0000	12.0000	-13662.0000	
215	Pan Arabe	9813.6600	1009.7401	8803.9199	
281	tomate cherry	0.0468	5590.0000	-5589.9532	
84	Cuchara helado autom 80 grs asii304	41418.0000	38943.2200	2474.7800	
53	cebolla morada	0.0092	2290.0000	-2289.9908	
222	Placa Pan Focaccia de Cherrys	23.6156	1700.0000	-1676.3844	
252	Salero	1511.4000	4.2957	1507.1043	
322	CLORO X 5 L ENVASADO	4900.0000	3450.0000	1450.0000	
83	Cuchara de Helado Acero 80gr	38943.2200	40180.6100	-1237.3900	
56	Chocolate alpino barra x 100 gr	1202.4048	15.0000	1187.4048	
96	Elegante Papel Higienico x 4	1500.0000	375.0000	1125.0000	
312	ALMACEN	2557.0400	3366.6667	-809.6267	
236	pvc film 38x300 100mt	3322.5000	4129.5000	-807.0000	
285	Triple Reforzado	2250.0000	3000.0000	-750.0000	
58	Churros rellenos de dulce de leche	0.0000	583.3300	-583.3300	
63	Colorante rosa	1300.0000	1680.0000	-380.0000	
183	Mangas N°  4	476.2090	252.7525	223.4565	
102	Etiquetas blancas	200.0000	25.0000	175.0000	
226	Pote Helado 80cc plastico por 100 unid	174.3941	2.4788	171.9153	
290	Vaso Milano 370 cc (Bulto 800u)	1.0026	129.3157	-128.3131	
243	Redondeo	109.8003	5.2533	104.5470	
44	Caja 16 Macarons Surtidos	1154.5833	1210.0000	-55.4167	
274	Tapa con sorbete Vaso Milano	0.3172	43.2149	-42.8977	
218	Papel parafinado blanco x 100 unid	72.4065	33.0310	39.3755	
315	CIF DETERGENTE LIMON	3460.9300	3499.9900	-39.0600	
238	Queso dambo feteado	275.1928	304.2657	-29.0729	
196	Mini Oreo x 50 gr	24.2462	41.3333	-17.0871	
32	Bolsa descartble dia	222.1905	208.5714	13.6191	
229	Pote Térmico de Helado 1/4	102.3316	114.4934	-12.1618	
167	Keuken x 500gr Azucar impalbable	16.5064	10.6714	5.8350	
31	Bolsa de consorcio 90*120cm bulto de 20 unid	155.3624	150.4549	4.9075	
86	Cuchara Sundae para torta x50 unidades	20.5878	16.1855	4.4023	
4	AGITADOR MADERA X1000UN	0.0397	4.0100	-3.9703	
27	Bolsa Camiseta 40x50 Blanca	80.7050	84.0263	-3.3213	
180	Limón en fruta x kg	0.0152	3.2900	-3.2748	
311	MAKE FIBRA ABRASIVA	1196.8800	1200.0000	-3.1200	
113	Galletitas Oreo x 3	10.7687	8.6723	2.0964	
231	Pote Térmico de Helado sin tapa 370cc	-0.0261	1.0712	-1.0973	
228	Pote Térmico de Helado 1/2	110.5265	109.8217	0.7048	
28	Bolsa camiseta verde coto	84.0200	83.3333	0.6867	
313	MAKE ESPONJA ACERO INOXID	600.5500	600.0000	0.5500	
230	Pote Térmico de Helado 1kg	263.0293	262.5054	0.5239	
112	Galletitas Oreo x 1	14.1233	13.7524	0.3709	
137	Helado en balde de Dulce de Leche X 6400 kg	5.2225	4.9460	0.2765	
147	Helado en balde de Mousse de Maracuyá X 6400 kg	6.4804	6.6306	-0.1502	
119	Helado en balde de Banana Split X 6400 kg	5.6311	5.7616	-0.1305	
57	Galletitas Chocolinas x250g	9.6062	9.4989	0.1073	
298	Yerba Playadito x 500gr	6.6649	6.7575	-0.0926	
237	Queso Crema La Serenisima	15.2384	15.1545	0.0839	
128	Helado en balde de Chocotorta X 6400 kg	6.4530	6.5341	-0.0811	
130	Helado en balde de Crema Americana X 6400 kg	4.8295	4.9088	-0.0793	
142	Helado en balde de Kinder X 6400 kg	6.4492	6.5148	-0.0656	
131	Helado en balde de Crema del Cielo X 6400 kg	5.4546	5.3900	0.0646	
143	Helado en balde de Limón Natural al agua X 6400 kg	4.0410	4.0917	-0.0507	
95	Dulce de leche Vacalin x 1 kg	7.3859	7.3650	0.0209	
42	Café Oyambre Santos Fino x kg	18.0083	17.9906	0.0177	



*********************************************************************************


-- ── 2. cantidad_contenida inconsistente entre compras del mismo ítem ──
-- Si a veces se carga la línea de factura con cantidad_contenida (ej.
-- "1 bidón = 5000") y otras veces se deja vacía/1 para el MISMO ítem, el
-- CPP mezcla peras con manzanas: una compra suma stock en unidad base y
-- otra en unidad de envase. Esta consulta lista ítems cuyas compras NO
-- usan siempre el mismo cantidad_contenida (tratando NULL como 1).
SELECT
    mp.id, mp.nombre,
    COUNT(*)                                          AS compras,
    COUNT(DISTINCT COALESCE(fcd.cantidad_contenida,1)) AS valores_cc_distintos,
    GROUP_CONCAT(DISTINCT COALESCE(fcd.cantidad_contenida,1) ORDER BY fc.fecha) AS valores_cc,
    MIN(fc.fecha) AS primera_compra,
    MAX(fc.fecha) AS ultima_compra
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
GROUP BY mp.id, mp.nombre
HAVING COUNT(DISTINCT COALESCE(fcd.cantidad_contenida,1)) > 1
ORDER BY compras DESC;


***************************************************************************

--La respuesta de la consulta 2 es la siguiente:


127.0.0.1:3306/u374453216_erp_comercio/mp/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=materias_primas

   Mostrando filas 0 - 36 (total de 37, La consulta tardó 0.0053 segundos.)


SELECT
    mp.id, mp.nombre,
    COUNT(*)                                          AS compras,
    COUNT(DISTINCT COALESCE(fcd.cantidad_contenida,1)) AS valores_cc_distintos,
    GROUP_CONCAT(DISTINCT COALESCE(fcd.cantidad_contenida,1) ORDER BY fc.fecha) AS valores_cc,
    MIN(fc.fecha) AS primera_compra,
    MAX(fc.fecha) AS ultima_compra
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
GROUP BY mp.id, mp.nombre
HAVING COUNT(DISTINCT COALESCE(fcd.cantidad_contenida,1)) > 1
ORDER BY compras DESC;


id	nombre	compras   	valores_cc_distintos	valores_cc	primera_compra	ultima_compra	
198	Monotributo Impuesto	12	2	1000.000,1.000	2026-02-19	2026-12-01	
274	Tapa con sorbete Vaso Milano	11	6	2.000,4.000,25.000,20.000,30.000,1.000	2026-02-03	2026-03-16	
17	Azucar Ledesma	10	2	1000.000,1.000	2026-03-16	2026-07-20	
79	CREMA LEDEVIT X LITRO	10	2	1000.000,1.000	2026-02-06	2026-05-28	
57	Galletitas Chocolinas x250g	9	2	1.000,250.000	2026-02-03	2026-08-09	
107	Fiambre Jamón cocido	9	4	750.000,1.000,250.000,100.000	2026-02-11	2026-07-07	
290	Vaso Milano 370 cc (Bulto 800u)	9	6	2.000,3.000,25.000,20.000,30.000,1.000	2026-02-03	2026-07-16	
229	Pote Térmico de Helado 1/4	8	3	20.000,25.000,100.000	2026-02-05	2026-07-16	
183	Mangas N°  4	7	3	10.000,1.000,50.000	2026-02-06	2026-07-16	
112	Galletitas Oreo x 1	6	2	108.000,118.000	2026-03-01	2026-07-31	
238	Queso dambo feteado	6	4	100.000,250.000,750.000,1.000	2026-02-11	2026-05-14	
82	Jugo Naranja Citric 3L	5	2	3000.000,1.000	2026-02-21	2026-07-25	
86	Cuchara Sundae para torta x50 unidades	5	2	50.000,100.000	2026-02-03	2026-07-16	
150	Helado en balde de Super Dulce de Leche X 6400 kg	5	2	6400.000,1.000	2026-02-05	2026-07-30	
240	Queso finlandia  La Serenisima x 290 gr	5	2	290.000,1.000	2026-04-30	2026-07-20	
275	Tapa de Vaso 8 oz  cafe polipapel	5	3	25.000,50.000,1.000	2026-05-12	2026-07-03	
8	Agua Mineral sin gas Cimes x 600 cc	4	2	12.000,1.000	2026-02-03	2026-07-25	
9	Agua Mineral sin gas Villa del sur x 600 cc	4	3	12.000,10.000,1.000	2026-02-03	2026-07-25	
27	Bolsa Camiseta 40x50 Blanca	4	2	1.000,50.000	2026-02-13	2026-05-02	
228	Pote Térmico de Helado 1/2	4	2	20.000,100.000	2026-02-03	2026-03-13	
231	Pote Térmico de Helado sin tapa 370cc	4	4	4.000,2.000,1.000,1000.000	2026-02-06	2026-05-29	
298	Yerba Playadito x 500gr	4	2	500.000,1.000	2026-03-20	2026-07-03	
90	Desodorante para piso	3	2	1.000,1000.000	2026-02-12	2026-06-26	
94	Dulce de Leche Repostero DIA	3	2	405.000,1.000	2026-02-05	2026-07-20	
116	Guantes Nylon  x paquete	3	2	2.000,1.000	2026-02-06	2026-05-22	
187	Maple de huevo x 30	3	2	1.000,30.000	2026-03-20	2026-07-25	
291	Vaso Mini Toledo 230 cc	3	3	25.000,30.000,20.000	2026-02-14	2026-03-16	
325	FIAMBRERIA QUESO PUNTA AG	3	3	100.000,3290.000,2005.000	2026-07-07	2026-07-11	
103	Extracto de vainilla	2	2	500.000,100.000	2026-03-06	2026-03-08	
173	Leche entera fresca casanto 3%	2	2	1000.000,1.000	2026-02-03	2026-06-15	
177	Levite de Manzana x 500	2	2	12.000,1.000	2026-02-03	2026-07-25	
219	Paquete de mini rocklets	2	2	50.000,1.000	2026-03-08	2026-07-14	
230	Pote Térmico de Helado 1kg	2	2	4.000,100.000	2026-02-06	2026-03-13	
252	Salero	2	2	1.000,1000.000	2026-03-20	2026-07-31	
287	Vaso de café poipapel 8 oz marron	2	2	50.000,25.000	2026-05-12	2026-05-22	
293	Vaso polipale 12 oz marron	2	2	50.000,1.000	2026-05-12	2026-07-03	
317	MELGAR AZUCAR COMUN	2	2	1.000,1000.000	2026-06-05	2026-07-27	





***************************************************************************

-- ── 3. precio_unitario con outliers dentro del mismo ítem ─────────
-- Compara cada línea de compra contra el promedio de precio_unitario
-- (ya normalizado a unidad base, dividiendo por cantidad_contenida) del
-- mismo ítem, y marca las que se alejan más de 3x para arriba o para
-- abajo. Síntoma típico de: precio cargado como total de la línea en vez
-- de precio unitario, un cero de más/de menos, o precio en la unidad de
-- compra cuando correspondía en la unidad base (o viceversa).
SELECT
    fc.id AS factura_id, fc.fecha, fc.nro_comprobante,
    mp.id AS materia_prima_id, mp.nombre,
    fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario,
    ROUND(fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0), 4) AS precio_por_unidad_base,
    ROUND(prom.precio_prom_base, 4) AS precio_promedio_base_del_item,
    ROUND(
        (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) / NULLIF(prom.precio_prom_base,0)
    , 2) AS veces_vs_promedio
FROM facturas_compra_detalle fcd
JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
JOIN (
    SELECT fcd2.materia_prima_id,
           AVG(fcd2.precio_unitario / NULLIF(COALESCE(fcd2.cantidad_contenida,1),0)) AS precio_prom_base
    FROM facturas_compra_detalle fcd2
    JOIN facturas_compra fc2 ON fc2.id = fcd2.factura_compra_id
    WHERE fc2.eliminado = 0 AND fc2.estado <> 'anulada' AND fcd2.materia_prima_id IS NOT NULL
    GROUP BY fcd2.materia_prima_id
) prom ON prom.materia_prima_id = mp.id
WHERE fc.eliminado = 0 AND fc.estado <> 'anulada'
  AND (
        (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) > prom.precio_prom_base * 3
     OR (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) < prom.precio_prom_base / 3
  )
ORDER BY veces_vs_promedio DESC;


***************************************************************************

--La respuesta de la consulta 3 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/fc/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=mp

   Mostrando filas 0 - 117 (total de 118, La consulta tardó 0.0066 segundos.)


-- ── 3. precio_unitario con outliers dentro del mismo ítem ─────────
-- Compara cada línea de compra contra el promedio de precio_unitario
-- (ya normalizado a unidad base, dividiendo por cantidad_contenida) del
-- mismo ítem, y marca las que se alejan más de 3x para arriba o para
-- abajo. Síntoma típico de: precio cargado como total de la línea en vez
-- de precio unitario, un cero de más/de menos, o precio en la unidad de
-- compra cuando correspondía en la unidad base (o viceversa).
SELECT
    fc.id AS factura_id, fc.fecha, fc.nro_comprobante,
    mp.id AS materia_prima_id, mp.nombre,
    fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario,
    ROUND(fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0), 4) AS precio_por_unidad_base,
    ROUND(prom.precio_prom_base, 4) AS precio_promedio_base_del_item,
    ROUND(
        (fcd.precio_unitario / NULLIF(COALESCE(fcd.cantidad_contenida,1),0)) / NULLIF(prom.precio_prom_base,0)
    , 2) AS veces_vs_prom[...]

factura_id	fecha	nro_comprobante	materia_prima_id	nombre	cantidad	cantidad_contenida	precio_unitario	precio_por_unidad_base	precio_promedio_base_del_item	veces_vs_promedio   	
292	2026-05-28	FA-0001-00000001	79	CREMA LEDEVIT X LITRO	1.000	NULL	10000.0000	10000.0000	1008.9100	9.91	
295	2026-06-05	14429-00149114	57	Galletitas Chocolinas x250g	2.000	NULL	2420.0000	2420.0000	276.1282	8.76	
52	2026-02-19	19922926	107	Fiambre Jamón cocido	500.000	1.000	1824.0000	1824.0000	224.1751	8.14	
297	2026-07-14	20260714	183	Mangas N°  4	1.000	NULL	3500.0000	3500.0000	633.9086	5.52	
52	2026-02-19	19922926	238	Queso dambo feteado	500.000	1.000	1216.0000	1216.0000	222.9444	5.45	
276	2026-03-13	7-79340	274	Tapa con sorbete Vaso Milano	200.000	1.000	93.3800	93.3800	21.9940	4.25	
313	2026-07-03	00003-00008829	17	Azucar Ledesma	1.000	NULL	2500.0000	2500.0000	599.9128	4.17	
317	2026-07-11	00003-00009044	150	Helado en balde de Super Dulce de Leche X 6400 kg	1.000	NULL	100.0000	100.0000	24.5421	4.07	
323	2026-07-16	0007-00082652	290	Vaso Milano 370 cc (Bulto 800u)	100.000	1.000	324.5800	324.5800	81.5699	3.98	
313	2026-07-03	00003-00008829	298	Yerba Playadito x 500gr	2.000	NULL	1700.0000	1700.0000	428.3749	3.97	
26	2026-02-05	5022026	229	Pote Térmico de Helado 1/4	1.000	20.000	10820.0000	541.0000	137.4480	3.94	
270	2026-05-25	12-22872	243	Redondeo	1.000	1.000	20.0000	20.0000	5.2533	3.81	
313	2026-07-03	00003-00008829	17	Azucar Ledesma	1.000	NULL	2200.0000	2200.0000	599.9128	3.67	
71	2026-02-05	B-07826-423885	32	Bolsa descartble dia	1.000	1.000	750.0000	750.0000	208.5714	3.60	
276	2026-03-13	7-79340	228	Pote Térmico de Helado 1/2	1.000	100.000	26770.2300	267.7023	76.1103	3.52	
304	2026-06-15	07827-00603227	240	Queso finlandia  La Serenisima x 290 gr	1.000	NULL	5340.0000	5340.0000	1716.8490	3.11	
341	2026-07-31		167	Keuken x 500gr Azucar impalbable	1.000	500.000	1700.0000	3.4000	10.6714	0.32	
211	2026-03-31	31032026	196	Mini Oreo x 50 gr	6.000	50.000	1100.0000	22.0000	80.6667	0.27	
185	2026-03-16	16032026	274	Tapa con sorbete Vaso Milano	1.000	20.000	114.7000	5.7350	21.9940	0.26	
341	2026-07-31		359	granas	1.000	100.000	700.0000	7.0000	27.5000	0.25	
172	2026-03-08	8032026	196	Mini Oreo x 50 gr	2.000	50.000	1000.0000	20.0000	80.6667	0.25	
338	2026-07-30		150	Helado en balde de Super Dulce de Leche X 6400 kg	1.000	6400.000	37948.0000	5.9294	24.5421	0.24	
173	2026-03-09	5-19989	110	Fotocopias Menu Nuevo	1.000	1.000	4550.0000	4550.0000	19075.0000	0.24	
188	2026-03-16	2-167370	32	Bolsa descartble dia	1.000	1.000	50.0000	50.0000	208.5714	0.24	
82	2026-03-02	2032026	274	Tapa con sorbete Vaso Milano	1.000	20.000	100.7500	5.0375	21.9940	0.23	
9	2026-02-05	A-4-30253	150	Helado en balde de Super Dulce de Leche X 6400 kg	1.000	6400.000	35800.0000	5.5938	24.5421	0.23	
10	2026-02-12	A-4-30347	150	Helado en balde de Super Dulce de Leche X 6400 kg	1.000	6400.000	35800.0000	5.5938	24.5421	0.23	
191	2026-03-18	4-30748	150	Helado en balde de Super Dulce de Leche X 6400 kg	1.000	6400.000	35800.0000	5.5938	24.5421	0.23	
34	2026-02-11	11022026	238	Queso dambo feteado	1.000	100.000	4368.0000	43.6800	222.9444	0.20	
53	2026-02-20	20022026	228	Pote Térmico de Helado 1/2	2.000	20.000	275.5000	13.7750	76.1103	0.18	
55	2026-02-21	21022026	274	Tapa con sorbete Vaso Milano	2.000	25.000	100.7500	4.0300	21.9940	0.18	
61	2026-02-23	23022026	274	Tapa con sorbete Vaso Milano	2.000	25.000	100.7500	4.0300	21.9940	0.18	
2	2026-02-03	3022026	228	Pote Térmico de Helado 1/2	1.000	20.000	266.0000	13.3000	76.1103	0.17	
84	2026-03-04	4032026	274	Tapa con sorbete Vaso Milano	1.000	30.000	106.9500	3.5650	21.9940	0.16	
4	2026-02-14	14022026	274	Tapa con sorbete Vaso Milano	1.000	30.000	100.7500	3.3583	21.9940	0.15	
83	2026-03-02	20320266	274	Tapa con sorbete Vaso Milano	1.000	30.000	100.7500	3.3583	21.9940	0.15	
255	2026-05-14	14052026	107	Fiambre Jamón cocido	1.000	250.000	8000.0000	32.0000	224.1751	0.14	
255	2026-05-14	14052026	107	Fiambre Jamón cocido	1.000	250.000	8000.0000	32.0000	224.1751	0.14	
2	2026-02-03	3022026	228	Pote Térmico de Helado 1/2	5.000	20.000	193.2800	9.6640	76.1103	0.13	
82	2026-03-02	2032026	290	Vaso Milano 370 cc (Bulto 800u)	1.000	20.000	210.8000	10.5400	81.5699	0.13	
238	2026-05-01	1892018	107	Fiambre Jamón cocido	1.000	250.000	7440.0000	29.7600	224.1751	0.13	
45	2026-02-13	13022026	107	Fiambre Jamón cocido	1.000	250.000	6250.0000	25.0000	224.1751	0.11	
255	2026-05-14	14052026	238	Queso dambo feteado	1.000	250.000	6000.0000	24.0000	222.9444	0.11	
255	2026-05-14	14052026	238	Queso dambo feteado	1.000	250.000	6000.0000	24.0000	222.9444	0.11	
314	2026-07-07	00003-00008942	107	Fiambre Jamón cocido	2.000	100.000	2520.0000	25.2000	224.1751	0.11	
55	2026-02-21	21022026	290	Vaso Milano 370 cc (Bulto 800u)	2.000	25.000	210.8000	8.4320	81.5699	0.10	
61	2026-02-23	23022026	290	Vaso Milano 370 cc (Bulto 800u)	2.000	25.000	210.8000	8.4320	81.5699	0.10	
248	2026-05-06	6052026	107	Fiambre Jamón cocido	3.000	250.000	4900.0000	19.6000	224.1751	0.09	
47	2026-02-14	14022026	107	Fiambre Jamón cocido	1.000	750.000	15960.0000	21.2800	224.1751	0.09	
83	2026-03-02	20320266	290	Vaso Milano 370 cc (Bulto 800u)	1.000	30.000	210.8000	7.0267	81.5699	0.09	
84	2026-03-04	4032026	290	Vaso Milano 370 cc (Bulto 800u)	1.000	30.000	221.6500	7.3883	81.5699	0.09	
82	2026-03-02	2032026	229	Pote Térmico de Helado 1/4	2.000	20.000	218.9500	10.9475	137.4480	0.08	
83	2026-03-02	20320266	229	Pote Térmico de Helado 1/4	3.000	20.000	218.9500	10.9475	137.4480	0.08	
45	2026-02-13	13022026	238	Queso dambo feteado	1.000	250.000	3950.0000	15.8000	222.9444	0.07	
3	2026-02-13	13022026	229	Pote Térmico de Helado 1/4	5.000	20.000	193.2800	9.6640	137.4480	0.07	
47	2026-02-14	14022026	238	Queso dambo feteado	1.000	750.000	10640.0000	14.1867	222.9444	0.06	
172	2026-03-08	8032026	219	Paquete de mini rocklets	1.000	50.000	5800.0000	116.0000	2058.0000	0.06	
61	2026-02-23	23022026	229	Pote Térmico de Helado 1/4	2.000	25.000	161.2000	6.4480	137.4480	0.05	
175	2026-03-10	10032026	183	Mangas N°  4	1.000	10.000	280.0000	28.0000	633.9086	0.04	
269	2026-05-23	23052026	187	Maple de huevo x 30	1.000	30.000	3500.0000	116.6667	2872.2222	0.04	
54	2026-02-21	21022026	183	Mangas N°  4	1.000	10.000	250.0000	25.0000	633.9086	0.04	
315	2026-07-09	00004-00000405	57	Galletitas Chocolinas x250g	1.000	250.000	2500.0000	10.0000	276.1282	0.04	
34	2026-02-11	11022026	107	Fiambre Jamón cocido	1.000	750.000	6552.0000	8.7360	224.1751	0.04	
193	2026-03-20	20032026	183	Mangas N°  4	1.000	10.000	280.0000	28.0000	633.9086	0.04	
1	2026-02-03	B-23-89122	57	Galletitas Chocolinas x250g	2.000	250.000	1986.7300	7.9469	276.1282	0.03	
287	2026-06-05	14429-149114	57	Galletitas Chocolinas x250g	2.000	250.000	1815.0000	7.2600	276.1282	0.03	
197	2026-03-20	23-22144998	57	Galletitas Chocolinas x250g	4.000	250.000	1986.7200	7.9469	276.1282	0.03	
294	2026-06-05	00003-00008102	312	ALMACEN	1.000	NULL	100.0000	100.0000	3366.6667	0.03	
172	2026-03-08	8032026	57	Galletitas Chocolinas x250g	1.000	250.000	2000.0000	8.0000	276.1282	0.03	
80	2026-03-01	1280966	57	Galletitas Chocolinas x250g	1.000	250.000	2000.0000	8.0000	276.1282	0.03	
348	2026-08-09	0003-00009753	57	Galletitas Chocolinas x250g	1.000	250.000	1999.9900	8.0000	276.1282	0.03	
260	2026-05-22	2120659	57	Galletitas Chocolinas x250g	1.000	250.000	2000.0000	8.0000	276.1282	0.03	
2	2026-02-03	3022026	86	Cuchara Sundae para torta x50 unidades	1.000	50.000	15.3500	0.3070	14.5977	0.02	
3	2026-02-13	13022026	67	Cono Dulce Cucurucho 120u	1.000	120.000	159.1400	1.3262	85.8023	0.02	
36	2026-02-11	11022026	79	CREMA LEDEVIT X LITRO	2.000	1000.000	9800.0000	9.8000	1008.9100	0.01	
197	2026-03-20	23-22144998	298	Yerba Playadito x 500gr	1.000	500.000	2174.8700	4.3497	428.3749	0.01	
73	2026-02-19	19022026	94	Dulce de Leche Repostero DIA	1.000	405.000	5350.0000	13.2099	2150.0823	0.01	
211	2026-03-31	31032026	79	CREMA LEDEVIT X LITRO	1.000	1000.000	9800.0000	9.8000	1008.9100	0.01	
49	2026-02-18	18022026	79	CREMA LEDEVIT X LITRO	2.000	1000.000	9800.0000	9.8000	1008.9100	0.01	
220	2026-04-05	5042026	298	Yerba Playadito x 500gr	1.000	500.000	2174.8700	4.3497	428.3749	0.01	
228	2026-04-13	1342026	79	CREMA LEDEVIT X LITRO	1.000	1000.000	9900.0000	9.9000	1008.9100	0.01	
259	2026-05-20	2098745	240	Queso finlandia  La Serenisima x 290 gr	1.000	290.000	4800.0000	16.5517	1716.8490	0.01	
229	2026-04-17	4-283	298	Yerba Playadito x 500gr	1.000	500.000	2400.0000	4.8000	428.3749	0.01	
187	2026-03-16	16032026	79	CREMA LEDEVIT X LITRO	1.000	1000.000	10000.0000	10.0000	1008.9100	0.01	
274	2026-05-15	15052026	79	CREMA LEDEVIT X LITRO	1.000	1000.000	10000.0000	10.0000	1008.9100	0.01	
33	2026-02-06	6022026	79	CREMA LEDEVIT X LITRO	2.000	1000.000	10000.0000	10.0000	1008.9100	0.01	
276	2026-03-13	7-79340	27	Bolsa Camiseta 40x50 Blanca	2.000	50.000	880.0300	17.6006	1728.0627	0.01	
86	2026-03-04	4032026	79	CREMA LEDEVIT X LITRO	2.000	1000.000	9800.0000	9.8000	1008.9100	0.01	
193	2026-03-20	20032026	79	CREMA LEDEVIT X LITRO	1.000	1000.000	10000.0000	10.0000	1008.9100	0.01	
240	2026-04-30	232447	240	Queso finlandia  La Serenisima x 290 gr	1.000	290.000	5341.0000	18.4172	1716.8490	0.01	
321	2026-07-20	0003-00610147	240	Queso finlandia  La Serenisima x 290 gr	2.000	290.000	2690.0000	9.2759	1716.8490	0.01	
246	2026-05-04	1931464	17	Azucar Ledesma	1.000	1000.000	1499.9900	1.5000	599.9128	0.00	
277	2026-05-29	7-81383	231	Pote Térmico de Helado sin tapa 370cc	50.000	1000.000	209.0900	0.2091	83.2673	0.00	
120	2026-02-19	CF-14-2026-02	198	Monotributo Impuesto	1.000	1000.000	93889.6000	93.8896	62105.4299	0.00	
246	2026-05-04	1931464	243	Redondeo	1.000	1.000	0.0100	0.0100	5.2533	0.00	
37	2026-02-12	501-6050	42	Café Oyambre Santos Fino x kg	2.000	1000.000	35.2300	0.0352	17.9906	0.00	
61	2026-02-23	23022026	262	Servilleta 18x18 Beige Ecologica	1.000	1000.000	5.0000	0.0050	3.5686	0.00	
121	2026-03-01	CF-14-2026-03	198	Monotributo Impuesto	1.000	1000.000	93889.6000	93.8896	62105.4299	0.00	
247	2026-05-05	14445-126947	82	Jugo Naranja Citric 3L	1.000	3000.000	15250.0000	5.0833	5418.3848	0.00	
287	2026-06-05	14429-149114	82	Jugo Naranja Citric 3L	1.000	3000.000	11925.0000	3.9750	5418.3848	0.00	
71	2026-02-05	B-07826-423885	94	Dulce de Leche Repostero DIA	4.000	405.000	2850.0000	7.0370	2150.0823	0.00	
122	2026-04-01	CF-14-2026-04	198	Monotributo Impuesto	1.000	1000.000	93889.6000	93.8896	62105.4299	0.00	
197	2026-03-20	23-22144998	17	Azucar Ledesma	2.000	1000.000	1119.0000	1.1190	599.9128	0.00	
123	2026-05-01	CF-14-2026-05	198	Monotributo Impuesto	1.000	1000.000	93889.6000	93.8896	62105.4299	0.00	
342	2026-07-31	0003-00009532	252	Salero	1.000	1000.000	2600.0000	2.6000	851.3000	0.00	
343	2026-07-27	0023-000178259	317	MELGAR AZUCAR COMUN	3.000	1000.000	1091.4926	1.0915	700.5407	0.00	
220	2026-04-05	5042026	17	Azucar Ledesma	1.000	1000.000	1119.0000	1.1190	599.9128	0.00	
3	2026-02-13	13022026	262	Servilleta 18x18 Beige Ecologica	1.000	1000.000	4.3100	0.0043	3.5686	0.00	
229	2026-04-17	4-283	243	Redondeo	1.000	1.000	0.0200	0.0200	5.2533	0.00	
233	2026-04-23	1800707	17	Azucar Ledesma	1.000	1000.000	1499.9900	1.5000	599.9128	0.00	
236	2026-04-24	1815317	243	Redondeo	1.000	1.000	0.0200	0.0200	5.2533	0.00	
18	2026-02-03	2189-5744839	173	Leche entera fresca casanto 3%	1.000	1000.000	1450.0000	1.4500	863.2250	0.00	
188	2026-03-16	2-167370	17	Azucar Ledesma	1.000	1000.000	1300.0000	1.3000	599.9128	0.00	
239	2026-04-30	1883982	17	Azucar Ledesma	1.000	1000.000	1499.9900	1.5000	599.9128	0.00	
58	2026-02-21	6-561564	82	Jugo Naranja Citric 3L	3.000	3000.000	8596.5100	2.8655	5418.3848	0.00	
239	2026-04-30	1883982	243	Redondeo	1.000	1.000	0.0200	0.0200	5.2533	0.00	
321	2026-07-20	0003-00610147	17	Azucar Ledesma	2.000	1000.000	1090.0000	1.0900	599.9128	0.00	
89	2026-03-03	3032026	90	Desodorante para piso	1.000	1000.000	3000.0000	3.0000	2001.0000	0.00	


***************************************************************************



***************************************************************************

-- ── 4. Posibles facturas duplicadas ───────────────────────────────
-- Mismo proveedor + mismo número de comprobante cargados más de una vez
-- (sin contar anuladas). Cada duplicado infla el CPP de todos sus ítems
-- porque el sistema recorre TODO el historial activo al recalcular.
SELECT proveedor_id, nro_comprobante, COUNT(*) AS veces_cargada,
       GROUP_CONCAT(id ORDER BY id) AS facturas_ids,
       GROUP_CONCAT(fecha ORDER BY id) AS fechas
FROM facturas_compra
WHERE eliminado = 0 AND estado <> 'anulada'
  AND nro_comprobante IS NOT NULL AND nro_comprobante <> ''
GROUP BY proveedor_id, nro_comprobante
HAVING COUNT(*) > 1;


***************************************************************************

--La respuesta de la consulta 4 es la siguiente:

 MySQL ha devuelto un conjunto de valores vacío (es decir: cero columnas). (La consulta tardó 0.0009 segundos.)
-- ── 4. Posibles facturas duplicadas ─────────────────────────────── -- Mismo proveedor + mismo número de comprobante cargados más de una vez -- (sin contar anuladas). Cada duplicado infla el CPP de todos sus ítems -- porque el sistema recorre TODO el historial activo al recalcular. SELECT proveedor_id, nro_comprobante, COUNT(*) AS veces_cargada, GROUP_CONCAT(id ORDER BY id) AS facturas_ids, GROUP_CONCAT(fecha ORDER BY id) AS fechas FROM facturas_compra WHERE eliminado = 0 AND estado <> 'anulada' AND nro_comprobante IS NOT NULL AND nro_comprobante <> '' GROUP BY proveedor_id, nro_comprobante HAVING COUNT(*) > 1;
[ Editar en línea ] [ Editar ] [ Crear código PHP ]
proveedor_id	nro_comprobante	veces_cargada	facturas_ids	fechas	



***************************************************************************

-- ── 5. Materias primas con stock negativo ─────────────────────────
-- Nunca debería pasar si las compras/ventas/ajustes están bien
-- aplicados. Stock negativo suele venir de una reversión de factura
-- (anulación/edición) que restó de más, o de consumo de receta sin
-- stock disponible. No corrompe el costo_unitario directamente, pero es
-- señal de que ese ítem tiene movimientos inconsistentes para revisar.
SELECT id, nombre, stock_actual, costo_unitario
FROM materias_primas
WHERE eliminado = 0 AND stock_actual < 0
ORDER BY stock_actual ASC;


***************************************************************************

--La respuesta de la consulta 5 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/materias_primas/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=facturas_compra

   Mostrando filas 0 -  4 (total de 5, La consulta tardó 0.0006 segundos.) [stock_actual: -103350.000... - -241.000...]


-- ── 5. Materias primas con stock negativo ─────────────────────────
-- Nunca debería pasar si las compras/ventas/ajustes están bien
-- aplicados. Stock negativo suele venir de una reversión de factura
-- (anulación/edición) que restó de más, o de consumo de receta sin
-- stock disponible. No corrompe el costo_unitario directamente, pero es
-- señal de que ese ítem tiene movimientos inconsistentes para revisar.
SELECT id, nombre, stock_actual, costo_unitario
FROM materias_primas
WHERE eliminado = 0 AND stock_actual < 0
ORDER BY stock_actual ASC;


id	nombre	stock_actual   	costo_unitario	
231	Pote Térmico de Helado sin tapa 370cc	-103350.000	-0.0261	
178	Levite de Pera x 500	-11784.000	1183.7271	
179	Levite de Pomelo x 500	-4910.000	79667.3052	
40	CAFÉ BLEND ESTILO ITALIANO X1/4KG DP	-241.000	754858.6721	
43	CAFÉ SANTOS FINO X1/4KG DP	-241.000	686471.8481	


***************************************************************************

-- ── 6. desvio_pct fuera de rango razonable ────────────────────────
-- Ajustá el umbral (50%) según el tipo de negocio. Un desvío muy alto
-- infla el costo de TODAS las recetas que usan ese ingrediente.
SELECT id, nombre, desvio_pct, costo_unitario,
       ROUND(costo_unitario * (1 + desvio_pct/100), 4) AS costo_con_desvio
FROM materias_primas
WHERE eliminado = 0 AND (desvio_pct > 50 OR desvio_pct < 0)
ORDER BY desvio_pct DESC;

***************************************************************************


--La respuesta de la consulta 6 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/materias_primas/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=materias_primas

   Mostrando filas 0 - 47 (total de 48, La consulta tardó 0.0009 segundos.) [desvio_pct: 999.99... - -999.99...]


-- ── 6. desvio_pct fuera de rango razonable ────────────────────────
-- Ajustá el umbral (50%) según el tipo de negocio. Un desvío muy alto
-- infla el costo de TODAS las recetas que usan ese ingrediente.
SELECT id, nombre, desvio_pct, costo_unitario,
       ROUND(costo_unitario * (1 + desvio_pct/100), 4) AS costo_con_desvio
FROM materias_primas
WHERE eliminado = 0 AND (desvio_pct > 50 OR desvio_pct < 0)
ORDER BY desvio_pct DESC;


id	nombre	desvio_pct   	costo_unitario	costo_con_desvio	
303	Alfajor fuaa Blanco	999.99	3000.0000	32999.7000	
147	Helado en balde de Mousse de Maracuyá X 6400 kg	999.99	6.4804	71.2838	
146	Helado en balde de Menta Granizada X 6400 kg	999.99	5.6777	62.4541	
145	Helado en balde de Mascarpone con Frutos Rojos X 6...	999.99	6.4375	70.8119	
144	Helado en balde de Marroc X 6400 kg	999.99	6.5341	71.8744	
142	Helado en balde de Kinder X 6400 kg	999.99	6.4492	70.9406	
141	Helado en balde de Granizado X 6400 kg	999.99	5.5938	61.5312	
140	Helado en balde de Frutilla al Agua X 6400 kg	999.99	4.0313	44.3439	
139	Helado en balde de Frutilla a la Crema X 6400 kg	999.99	4.9237	54.1602	
148	Helado en balde de Pistacho X 6400 kg	999.99	6.4375	70.8119	
149	Helado en balde de Quinotos al Whisky X 6400 kg	999.99	4.8125	52.9370	
150	Helado en balde de Super Dulce de Leche X 6400 kg	999.99	5.6813	62.4937	
302	Alfajor fuaa Negro	999.99	3000.0000	32999.7000	
256	Salsa de Frutilla para helados	999.99	8.1529	89.6811	
255	Salsa de Dulce de Leche para helados	999.99	7.7630	85.3922	
254	Salsa de Chocolate para helados	999.99	8.1835	90.0177	
253	Salsa de Caramelo para helados	999.99	8.4284	92.7116	
237	Queso Crema La Serenisima	999.99	15.2384	167.6209	
185	Manteca La Paulina x 500gr	999.99	16.0944	177.0368	
159	Huevos Blanco x 6 unidades	999.99	148.6108	1634.7039	
137	Helado en balde de Dulce de Leche X 6400 kg	999.99	5.2225	57.4470	
136	Helado en balde de Dulce de Leche Granizado X 6400...	999.99	5.6161	61.7765	
82	Jugo Naranja Citric 3L	999.99	6.0804	66.8838	
95	Dulce de leche Vacalin x 1 kg	999.99	7.3859	81.2442	
99	Esencia de vainilla x Litro	999.99	0.0040	0.0440	
79	CREMA LEDEVIT X LITRO	999.99	10.6530	117.1819	
112	Galletitas Oreo x 1	999.99	14.1233	155.3549	
119	Helado en balde de Banana Split X 6400 kg	999.99	5.6311	61.9415	
120	Helado en balde de Cardbury X 6400 kg	999.99	6.7684	74.4517	
123	Helado en balde de Chocolate con Almendras X 6400 ...	999.99	6.5561	72.1164	
125	Helado en balde de Chocolate Rocher X 6400 kg	999.99	7.7894	85.6826	
127	Helado en balde de Chocolate X 6400 kg	999.99	6.1369	67.5053	
135	Helado en balde de Crema Vainilla X 6400 kg	999.99	4.9739	54.7124	
134	Helado en balde de Crema Tramontana X 6400 kg	999.99	5.7280	63.0074	
133	Helado en balde de Crema Rusa X 6400 kg	999.99	7.7894	85.6826	
132	Helado en balde de Crema Flan X 6400 kg	999.99	5.5938	61.5312	
131	Helado en balde de Crema del Cielo X 6400 kg	999.99	5.4546	60.0001	
130	Helado en balde de Crema Americana X 6400 kg	999.99	4.8295	53.1240	
128	Helado en balde de Chocotorta X 6400 kg	999.99	6.4530	70.9824	
81	Croissant	790.63	1437.5000	12802.8063	
209	Paleta Corazón Frutilla al Agua	790.00	2400.0000	21360.0000	
248	Roll de Canela congelado	768.75	1325.0000	11510.9375	
215	Pan Arabe	727.27	9813.6600	81185.4651	
9	Agua Mineral sin gas Villa del sur x 600 cc	-999.99	786.8849	-7081.8854	
213	Palito Bombon	-999.99	762.5000	-6862.4238	
177	Levite de Manzana x 500	-999.99	1322.9672	-11906.5725	
8	Agua Mineral sin gas Cimes x 600 cc	-999.99	321.7947	-2896.1201	
353	PUNTA DE AGUA X KG QUESO TYBO	-999.99	10764.0027	-96874.9479	


***************************************************************************


-- ── 7. merma_pct fuera de rango razonable en recetas ──────────────
-- Una merma cargada como "50" en vez de "5", o negativa, dispara (o
-- reduce) el costo de la línea sin que sea un error de compra.
SELECT rd.id AS receta_detalle_id, r.id AS receta_id, r.nombre AS receta,
       COALESCE(mp.nombre, ins.nombre, ig.nombre) AS ingrediente,
       rd.cantidad, rd.merma_pct
FROM receta_detalle rd
JOIN recetas r ON r.id = rd.receta_id
LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id
LEFT JOIN insumos ins ON ins.id = rd.insumo_id
LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
WHERE r.eliminado = 0 AND (rd.merma_pct > 100 OR rd.merma_pct < 0)
ORDER BY rd.merma_pct DESC;


***************************************************************************


--La respuesta de la consulta 7 es la siguiente:

 MySQL ha devuelto un conjunto de valores vacío (es decir: cero columnas). (La consulta tardó 0.0011 segundos.)
-- ── 7. merma_pct fuera de rango razonable en recetas ────────────── -- Una merma cargada como "50" en vez de "5", o negativa, dispara (o -- reduce) el costo de la línea sin que sea un error de compra. SELECT rd.id AS receta_detalle_id, r.id AS receta_id, r.nombre AS receta, COALESCE(mp.nombre, ins.nombre, ig.nombre) AS ingrediente, rd.cantidad, rd.merma_pct FROM receta_detalle rd JOIN recetas r ON r.id = rd.receta_id LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id LEFT JOIN insumos ins ON ins.id = rd.insumo_id LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id WHERE r.eliminado = 0 AND (rd.merma_pct > 100 OR rd.merma_pct < 0) ORDER BY rd.merma_pct DESC;
[ Editar en línea ] [ Editar ] [ Crear código PHP ]
receta_detalle_id	receta_id	receta	ingrediente	cantidad	merma_pct	
Operaciones sobre los resultados de la consulta

***************************************************************************


-- ── 8. Ingredientes genéricos con variantes en distinta unidad ────
-- El promedio ponderado de un ingrediente genérico asume que todas sus
-- materias primas "variante" comparten la unidad base declarada en
-- ingredientes_genericos.unidad_medida_id. Si alguna variante quedó
-- cargada en otra unidad, el costo promedio del genérico mezcla
-- unidades distintas sin conversión (ej. gramos con kilos).
SELECT ig.id AS generico_id, ig.nombre AS generico,
       um_ig.abreviatura AS unidad_generico,
       mp.id AS materia_prima_id, mp.nombre AS variante,
       um_mp.abreviatura AS unidad_variante
FROM ingredientes_genericos ig
JOIN materias_primas mp ON mp.ingrediente_generico_id = ig.id AND mp.eliminado = 0
LEFT JOIN unidades_medida um_ig ON um_ig.id = ig.unidad_medida_id
LEFT JOIN unidades_medida um_mp ON um_mp.id = mp.unidad_medida_id
WHERE ig.eliminado = 0
  AND (mp.unidad_medida_id IS NULL OR mp.unidad_medida_id <> ig.unidad_medida_id);


***************************************************************************

--La respuesta de la consulta 8 es la siguiente:

 MySQL ha devuelto un conjunto de valores vacío (es decir: cero columnas). (La consulta tardó 0.0006 segundos.)
-- ── 8. Ingredientes genéricos con variantes en distinta unidad ──── -- El promedio ponderado de un ingrediente genérico asume que todas sus -- materias primas "variante" comparten la unidad base declarada en -- ingredientes_genericos.unidad_medida_id. Si alguna variante quedó -- cargada en otra unidad, el costo promedio del genérico mezcla -- unidades distintas sin conversión (ej. gramos con kilos). SELECT ig.id AS generico_id, ig.nombre AS generico, um_ig.abreviatura AS unidad_generico, mp.id AS materia_prima_id, mp.nombre AS variante, um_mp.abreviatura AS unidad_variante FROM ingredientes_genericos ig JOIN materias_primas mp ON mp.ingrediente_generico_id = ig.id AND mp.eliminado = 0 LEFT JOIN unidades_medida um_ig ON um_ig.id = ig.unidad_medida_id LEFT JOIN unidades_medida um_mp ON um_mp.id = mp.unidad_medida_id WHERE ig.eliminado = 0 AND (mp.unidad_medida_id IS NULL OR mp.unidad_medida_id <> ig.unidad_medida_id);
[ Editar en línea ] [ Editar ] [ Crear código PHP ]
generico_id	generico	unidad_generico	materia_prima_id	variante	unidad_variante	
Operaciones sobre los resultados de la consulta

***************************************************************************

-- ── 9. Sub-recetas con rendimiento inválido ───────────────────────
-- El costo heredado de una sub-receta se divide por su rendimiento; un
-- rendimiento en 0, NULL o claramente mal cargado (ej. "1" para una
-- receta que en realidad rinde 100 porciones) multiplica el costo que
-- hereda cualquier receta que la use como ingrediente.
SELECT id, nombre, rendimiento, unidad_rendimiento
FROM recetas
WHERE eliminado = 0 AND (rendimiento IS NULL OR rendimiento <= 0);

***************************************************************************

--La respuesta de la consulta 9 es la siguiente:

 MySQL ha devuelto un conjunto de valores vacío (es decir: cero columnas). (La consulta tardó 0.0008 segundos.)
-- ── 9. Sub-recetas con rendimiento inválido ─────────────────────── -- El costo heredado de una sub-receta se divide por su rendimiento; un -- rendimiento en 0, NULL o claramente mal cargado (ej. "1" para una -- receta que en realidad rinde 100 porciones) multiplica el costo que -- hereda cualquier receta que la use como ingrediente. SELECT id, nombre, rendimiento, unidad_rendimiento FROM recetas WHERE eliminado = 0 AND (rendimiento IS NULL OR rendimiento <= 0);
[ Editar en línea ] [ Editar ] [ Crear código PHP ]
id	nombre	rendimiento	unidad_rendimiento	
Operaciones sobre los resultados de la consulta

***************************************************************************

-- ── 10. Cantidad de receta con outlier respecto al mismo ingrediente ──
-- Para cada ingrediente, compara la "cantidad" que cada receta declara
-- usar contra la mediana de cantidad usada por las demás recetas que
-- usan ese mismo ingrediente. Un factor >20x o <0.05x suele ser un
-- desfasaje de unidad (ej. la ficha del ingrediente está en gramos y la
-- receta cargó pensando en kilos, o al revés).
SELECT
    COALESCE(mp.nombre, ins.nombre, ig.nombre) AS ingrediente,
    r.id AS receta_id, r.nombre AS receta, rd.cantidad,
    stats.mediana_aprox,
    ROUND(rd.cantidad / NULLIF(stats.mediana_aprox,0), 2) AS veces_vs_mediana
FROM receta_detalle rd
JOIN recetas r ON r.id = rd.receta_id
LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id
LEFT JOIN insumos ins ON ins.id = rd.insumo_id
LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
JOIN (
    SELECT
        COALESCE(materia_prima_id, insumo_id, ingrediente_generico_id) AS ing_id,
        tipo_ingrediente,
        AVG(cantidad) AS mediana_aprox,   -- aproximación simple; con pocas recetas por ingrediente alcanza
        COUNT(*) AS usos
    FROM receta_detalle rd2
    JOIN recetas r2 ON r2.id = rd2.receta_id AND r2.eliminado = 0
    GROUP BY COALESCE(materia_prima_id, insumo_id, ingrediente_generico_id), tipo_ingrediente
) stats ON stats.ing_id = COALESCE(rd.materia_prima_id, rd.insumo_id, rd.ingrediente_generico_id)
       AND stats.tipo_ingrediente = rd.tipo_ingrediente
WHERE r.eliminado = 0
  AND stats.usos >= 3   -- solo tiene sentido comparar si el ingrediente se usa en varias recetas
  AND (rd.cantidad / NULLIF(stats.mediana_aprox,0) > 20 OR rd.cantidad / NULLIF(stats.mediana_aprox,0) < 0.05)
ORDER BY veces_vs_mediana DESC;



***************************************************************************

--La respuesta de la consulta 10 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/recetas/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=recetas

   Mostrando filas 0 -  4 (total de 5, La consulta tardó 0.0027 segundos.)


-- ── 10. Cantidad de receta con outlier respecto al mismo ingrediente ──
-- Para cada ingrediente, compara la "cantidad" que cada receta declara
-- usar contra la mediana de cantidad usada por las demás recetas que
-- usan ese mismo ingrediente. Un factor >20x o <0.05x suele ser un
-- desfasaje de unidad (ej. la ficha del ingrediente está en gramos y la
-- receta cargó pensando en kilos, o al revés).
SELECT
    COALESCE(mp.nombre, ins.nombre, ig.nombre) AS ingrediente,
    r.id AS receta_id, r.nombre AS receta, rd.cantidad,
    stats.mediana_aprox,
    ROUND(rd.cantidad / NULLIF(stats.mediana_aprox,0), 2) AS veces_vs_mediana
FROM receta_detalle rd
JOIN recetas r ON r.id = rd.receta_id
LEFT JOIN materias_primas mp ON mp.id = rd.materia_prima_id
LEFT JOIN insumos ins ON ins.id = rd.insumo_id
LEFT JOIN ingredientes_genericos ig ON ig.id = rd.ingrediente_generico_id
JOIN (
    SELECT
        COALESCE(materia_prima_id, insumo_id, ingrediente_generico_id) AS ing_id,
     [...]

ingrediente	receta_id	receta	cantidad	mediana_aprox	veces_vs_mediana   	
Dulce de Leche	11	Cookie Artesanal Propia Chocolina	6.0000	132.66666667	0.05	
Hielo Rolito x 15kg	17	Receta de hielo comprado a proveedor	1.0000	150.25000000	0.01	
Helado en balde x 6400 gr	32	Receta para el promedio de precio de Helados	0.6670	254.54840000	0.00	
Helado en balde x 6400 gr	32	Receta para el promedio de precio de Helados	0.2000	254.54840000	0.00	
Helado en balde x 6400 gr	32	Receta para el promedio de precio de Helados	0.1000	254.54840000	0.00	


***************************************************************************


-- ── 11. Productos con margen negativo o absurdo ───────────────────
-- Síntoma final visible: el precio_costo (ya sincronizado desde la
-- receta en cada compra) superó al precio_venta, o el margen es
-- irrealmente alto (>95%, señal de que el costo quedó casi en cero).
SELECT id, nombre, precio_venta, precio_costo,
       ROUND((precio_venta - precio_costo) / NULLIF(precio_venta,0) * 100, 1) AS margen_pct
FROM productos
WHERE eliminado = 0 AND precio_venta > 0
  AND (
        precio_costo > precio_venta
     OR (precio_venta - precio_costo) / precio_venta * 100 > 95
  )
ORDER BY margen_pct ASC;


***************************************************************************

--La respuesta de la consulta 11 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/productos/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=recetas

   Mostrando filas 0 - 140 (total de 141, La consulta tardó 0.0011 segundos.)


-- ── 11. Productos con margen negativo o absurdo ───────────────────
-- Síntoma final visible: el precio_costo (ya sincronizado desde la
-- receta en cada compra) superó al precio_venta, o el margen es
-- irrealmente alto (>95%, señal de que el costo quedó casi en cero).
SELECT id, nombre, precio_venta, precio_costo,
       ROUND((precio_venta - precio_costo) / NULLIF(precio_venta,0) * 100, 1) AS margen_pct
FROM productos
WHERE eliminado = 0 AND precio_venta > 0
  AND (
        precio_costo > precio_venta
     OR (precio_venta - precio_costo) / precio_venta * 100 > 95
  )
ORDER BY margen_pct ASC;


id	nombre	precio_venta	precio_costo	margen_pct   	
91	Macarons	2000.00	18220.00	-811.0	
51	Tostado jamon y queso	7000.00	36902.28	-427.2	
35	PALETAS	6000.00	9096.65	-51.6	
399	Promo 2 Milkshake Caliente	8500.00	11423.36	-34.4	
95	Promo Cafe + 1/4	9000.00	0.00	100.0	
96	Rol de Canela	3000.00	0.00	100.0	
94	Promo 2  x Wafflez pezco helado	11000.00	0.00	100.0	
97	SALSA	200.00	0.00	100.0	
98	Submarino	4000.00	0.00	100.0	
99	TORTA HELADA  FRUTILLA (Pedidos ya)	22000.00	0.00	100.0	
100	Torta helada chocolate	18000.00	0.00	100.0	
101	Torta Lemon Pie Individual	6500.00	0.00	100.0	
102	Torta Mousse Chocolate Individual	6500.00	0.00	100.0	
103	Tortas Individuales	6500.00	0.00	100.0	
106	Agua Saborizada de Pomelo	1800.00	0.00	100.0	
107	Cafe + Alfajor Artesanal	7500.00	0.00	100.0	
111	Lagrima doble	4500.00	0.00	100.0	
112	CORTADO doble	4500.00	0.00	100.0	
93	Palito Bombon (Crema Americana bañado en Choco)	1500.00	0.00	100.0	
92	Milkshake caliente + Churro	4500.00	0.00	100.0	
74	Cookie Red Velvet Artesanal	4800.00	0.00	100.0	
75	Cookie Oreo Artesanal	4800.00	0.00	100.0	
77	Cookie Chocotorta Artesanal	4800.00	0.00	100.0	
79	1 kg Helado + 1/4 PEDIDO YA	28000.00	0.00	100.0	
80	alfajor - brownie - tortita	3500.00	0.00	100.0	
81	Alfajor fuaa de chocolate Negro	4800.00	0.00	100.0	
82	Brownie con helado y salsa	6500.00	0.00	100.0	
83	Brownie con salsa	5500.00	0.00	100.0	
84	PROMO 11: Café + cookie	6500.00	0.00	100.0	
85	Cafe + Medialuna	4500.00	0.00	100.0	
86	Churro	1200.00	0.00	100.0	
87	Cookies Pedidos Ya	5600.00	0.00	100.0	
88	Cookies Rellenas	4800.00	0.00	100.0	
89	FL0RES G	7500.00	0.00	100.0	
90	Focaccia de Romero	3500.00	0.00	100.0	
55	Cafe con leche	4500.00	0.00	100.0	
114	CUCURUCHO SOLO	300.00	0.00	100.0	
126	ALFAJOR HELADO	2800.00	0.00	100.0	
195	PINK LATTE	5500.00	0.00	100.0	
196	Vaso Mini Cookies	5500.00	0.00	100.0	
197	medialuna con dulce de leche	3000.00	0.00	100.0	
198	VASO OSO	14500.00	0.00	100.0	
200	CAJA MATERA	10500.00	0.00	100.0	
201	Prod Prueba	1.00	0.00	100.0	
264	Cookie de Limón y Chocolate Artesanal	4800.00	0.00	100.0	
334	Prod Prueba	1.00	0.00	100.0	
398	CAPUCCINO	4800.00	0.00	100.0	
400	Coca cola	2200.00	0.00	100.0	
413	Levite de Manzana	1800.00	0.00	100.0	
414	Torta Red Velvet Individual	7500.00	0.00	100.0	
415	Torta Carrot cake Individual	7500.00	0.00	100.0	
416	PROMO 15: Café + Torta Individual	10000.00	0.00	100.0	
417	Carrot cake + Café	10000.00	0.00	100.0	
193	cafe doble oferta	4500.00	0.00	100.0	
190	COOKIE NUTELLA	4800.00	0.00	100.0	
187	PROMO 1 VECINOS CAFE + 3 MEDIALUNA	4000.00	0.00	100.0	
127	CONOS X4	1200.00	0.00	100.0	
128	PINKY DRINK	5000.00	0.00	100.0	
129	pico loco	5000.00	0.00	100.0	
130	DAIKIRI	5000.00	0.00	100.0	
132	AGUA CON GAS	1500.00	0.00	100.0	
136	TINTO DE VERANO	5000.00	0.00	100.0	
140	FLORES	3000.00	0.00	100.0	
146	ADICIONAL CREMA	500.00	0.00	100.0	
151	VASO SERENDIPIA	5500.00	0.00	100.0	
158	ADICIONAL 1 BOCHA HELADO	1700.00	0.00	100.0	
166	MATE + 6 MEDIALUNA	8500.00	0.00	100.0	
168	FLORES M	4500.00	0.00	100.0	
172	gancia frozen	5000.00	0.00	100.0	
177	PROMO 2 CAFE + COOKIE	6500.00	0.00	100.0	
185	WAFFLE + HELADO	6500.00	0.00	100.0	
418	Red velvetcake + Café	10000.00	0.00	100.0	
2	1/2kg	9500.00	0.00	100.0	
20	CUCURUCHO DOBLE	4500.00	0.00	100.0	
21	Cucurucho simple	2800.00	0.00	100.0	
22	Focaccia de Tomates Cherrys	3500.00	0.00	100.0	
23	Frappe	6000.00	0.00	100.0	
24	hielo	500.00	0.00	100.0	
25	ICED COFFE	5800.00	0.00	100.0	
26	iced latte dalgona	7500.00	0.00	100.0	
27	Jugo de naranja	3500.00	0.00	100.0	
28	LATTE SABORIZADO	5500.00	0.00	100.0	
29	Medialuna de manteca	6000.00	0.00	100.0	
30	Milkshake	6000.00	0.00	100.0	
31	MILKSHAKE MICKEY	7500.00	0.00	100.0	
32	MILKSHAKE UNICORNIO	7500.00	0.00	100.0	
33	MOCCA	4800.00	0.00	100.0	
34	Torta Oreo en vaso	4500.00	0.00	100.0	
19	Croissant	3000.00	0.00	100.0	
18	COOKIES	3200.00	0.00	100.0	
3	1/4kg	5500.00	0.00	100.0	
4	2 ICED COFFE	7500.00	0.00	100.0	
5	AGUA	1500.00	0.00	100.0	
6	agua para tu mate	1500.00	0.00	100.0	
7	Agua saborizada de Pera	1800.00	0.00	100.0	
8	BATIDO FROZEN	5000.00	0.00	100.0	
9	Budin de Banana con nueces	3500.00	0.00	100.0	
10	Budin de Frutos Rojos	3500.00	0.00	100.0	
11	Budin de Limón y Semillas de Amapola	3500.00	0.00	100.0	
12	CAFÉ DOBLE	4800.00	0.00	100.0	
13	CAFE GRANDE  para llevar	4500.00	0.00	100.0	
14	CAFE JARRITO	3500.00	0.00	100.0	
15	Cappuccino	4000.00	0.00	100.0	
16	CHOCOLATADA	3000.00	0.00	100.0	
17	Torta Chocotorta en vaso	4500.00	0.00	100.0	
1	1 kg	16500.00	0.00	100.0	
36	Palito frutilla	3400.00	0.00	100.0	
37	Pan de Chocolate Aguila	4800.00	0.00	100.0	
58	Cafe para llevar	3500.00	0.00	100.0	
59	Chipa XL	3200.00	0.00	100.0	
60	Croissant J y Q	5000.00	0.00	100.0	
61	Focaccia con Aceitunas	3500.00	0.00	100.0	
62	ice latte saborizado	6000.00	0.00	100.0	
63	Latte	5200.00	0.00	100.0	
64	Medialuna XL	2200.00	0.00	100.0	
65	Medialuna XL J y Q	4500.00	0.00	100.0	
66	promo millkshake	10000.00	0.00	100.0	
67	Scon de Caprese	3800.00	0.00	100.0	
68	Torta helada frutilla	18000.00	0.00	100.0	
69	tostado focaccia J y Q	5500.00	0.00	100.0	
70	waffle pez	6000.00	0.00	100.0	
71	Medialuna J y Q	3500.00	0.00	100.0	
72	Brownie	4000.00	0.00	100.0	
57	Cafe con leche con focaccia	7000.00	0.00	100.0	
56	Cafe con leche con 2 medialunas de manteca	6800.00	0.00	100.0	
38	PROMO 1: Café + 2  medialunas	6500.00	0.00	100.0	
39	PROMO 2: Café + 2 medialunas JYQ	8500.00	0.00	100.0	
40	PROMO 3: Café + tostado	8000.00	0.00	100.0	
41	PROMO 4: Jugo de naranja+ tostado	9000.00	0.00	100.0	
43	PROMO 7: 2 Batidos	8400.00	0.00	100.0	
44	PROMO FIN DE MILKSHAKE 7500	7500.00	0.00	100.0	
45	PROMO FIN SEMANA 2 1/4 HELADO 6800	6800.00	0.00	100.0	
46	Promo 2 x 1/4	9800.00	0.00	100.0	
47	Prueba	7500.00	0.00	100.0	
48	TÉ	3500.00	0.00	100.0	
49	Torta de Frutos Rojos	4500.00	0.00	100.0	
50	Torta de Pistacho	4500.00	0.00	100.0	
52	VASITO	3000.00	0.00	100.0	
53	2x1 waffle pez	6000.00	0.00	100.0	
54	Affogato	6800.00	0.00	100.0	
73	Mini Torta de Mousse de chocolate	4500.00	0.00	100.0	


***************************************************************************

-- ── 12. Historial de saltos bruscos de costo por receta ───────────
-- recetas_historial_costo registra cada recálculo disparado por una
-- compra. Un salto grande de un día para otro (para arriba o para
-- abajo) suele coincidir en fecha con la factura que introdujo el dato
-- mal cargado — cruzá el factura_compra_id contra la sección 1 y 3.
SELECT rhc.id, r.nombre AS receta, rhc.costo_anterior, rhc.costo_nuevo,
       ROUND((rhc.costo_nuevo - rhc.costo_anterior) / NULLIF(rhc.costo_anterior,0) * 100, 1) AS variacion_pct,
       rhc.factura_compra_id, rhc.motivo, rhc.created_at
FROM recetas_historial_costo rhc
JOIN recetas r ON r.id = rhc.receta_id
WHERE rhc.costo_anterior > 0
  AND ABS((rhc.costo_nuevo - rhc.costo_anterior) / rhc.costo_anterior) > 0.5  -- +/-50%
ORDER BY rhc.created_at DESC;


***************************************************************************

--La respuesta de la consulta 12 es la siguiente:

127.0.0.1:3306/u374453216_erp_comercio/productos/		https://auth-db693.hstgr.io/index.php?route=/table/sql&db=u374453216_erp_comercio&table=productos

   Mostrando filas 0 - 96 (total de 97, La consulta tardó 0.0030 segundos.) [created_at: 2026-08-09 23:20:28... - 2026-07-31 01:26:12...]


-- ── 12. Historial de saltos bruscos de costo por receta ───────────
-- recetas_historial_costo registra cada recálculo disparado por una
-- compra. Un salto grande de un día para otro (para arriba o para
-- abajo) suele coincidir en fecha con la factura que introdujo el dato
-- mal cargado — cruzá el factura_compra_id contra la sección 1 y 3.
SELECT rhc.id, r.nombre AS receta, rhc.costo_anterior, rhc.costo_nuevo,
       ROUND((rhc.costo_nuevo - rhc.costo_anterior) / NULLIF(rhc.costo_anterior,0) * 100, 1) AS variacion_pct,
       rhc.factura_compra_id, rhc.motivo, rhc.created_at
FROM recetas_historial_costo rhc
JOIN recetas r ON r.id = rhc.receta_id
WHERE rhc.costo_anterior > 0
  AND ABS((rhc.costo_nuevo - rhc.costo_anterior) / rhc.costo_anterior) > 0.5  -- +/-50%
ORDER BY rhc.created_at DESC;


id	receta	costo_anterior	costo_nuevo	variacion_pct	factura_compra_id	motivo	created_at   	
1669	Cookie Artesanal Propia Oreo	2467.5233	12246.4708	396.3	348	Recálculo por compra	2026-08-09 23:20:28	
1671	Cookie Artesanal Propia Chocolina	2169.8418	11967.7789	451.6	348	Recálculo por compra	2026-08-09 23:20:28	
1427	Chocolate caliente con helado y crema	1494.8913	6340.8476	324.2	345	Recálculo por compra	2026-08-06 13:23:33	
1428	Chocolate Caliente con Helado	1059.0409	4071.5041	284.5	345	Recálculo por compra	2026-08-06 13:23:33	
1429	Receta para el promedio de precio de Helados	11.9905	60.5406	404.9	345	Recálculo por compra	2026-08-06 13:23:33	
1430	Waffle de pez Taiyaki con helado	2453.7756	11491.0147	368.3	345	Recálculo por compra	2026-08-06 13:23:33	
1431	Pescado taiyaki	2282.1710	11319.4101	396.0	345	Recálculo por compra	2026-08-06 13:23:33	
1432	Milkshake	2589.5314	12445.1124	380.6	345	Recálculo por compra	2026-08-06 13:23:33	
1433	VASITO	1417.6044	6940.3617	389.6	345	Recálculo por compra	2026-08-06 13:23:33	
1434	1/4kg	3382.2864	16425.0125	385.6	345	Recálculo por compra	2026-08-06 13:23:33	
1435	1/2kg	7110.3862	35215.1776	395.3	345	Recálculo por compra	2026-08-06 13:23:33	
1436	1 kg	13462.7306	66670.9641	395.2	345	Recálculo por compra	2026-08-06 13:23:33	
1437	CONO DOBLE	2367.9733	11405.2124	381.6	345	Recálculo por compra	2026-08-06 13:23:33	
1438	Cucurucho simple	2367.9733	11405.2124	381.6	345	Recálculo por compra	2026-08-06 13:23:33	
1439	PROMO HELADO Dos 1/4kg	7303.8519	35899.6484	391.5	345	Recálculo por compra	2026-08-06 13:23:33	
1440	PROMO 7: 2 Batidos	5179.0628	24890.2247	380.6	345	Recálculo por compra	2026-08-06 13:23:33	
1441	PROMO FIN DE MILKSHAKE 7500	5179.0628	24890.2247	380.6	345	Recálculo por compra	2026-08-06 13:23:33	
1442	PROMO FIN SEMANA 2 1/4 HELADO 6800	7282.4403	35878.2368	392.7	345	Recálculo por compra	2026-08-06 13:23:33	
1443	MILKSHAKE UNICORNIO	2674.4181	12529.9991	368.5	345	Recálculo por compra	2026-08-06 13:23:33	
1444	Affogato Especial (Café con helado) Vainilla, Amer...	1226.0742	5242.6250	327.6	345	Recálculo por compra	2026-08-06 13:23:33	
1421	Agua Saborizada de Pera	76547.4234	1183.7271	-98.5	1	Recálculo por edición de factura	2026-08-04 04:12:36	
1422	Agua	16459.8213	786.8849	-95.2	1	Recálculo por edición de factura	2026-08-04 04:12:36	
1377	Masa Base de Cookie	16270.6041	6478.2216	-60.2	329	Recálculo por edición de factura	2026-08-04 03:59:12	
1379	Cookie Artesanal Propia Chocolina	4694.5274	2246.4318	-52.1	329	Recálculo por edición de factura	2026-08-04 03:59:12	
1380	Cookie Artesanal Propia con Chips de Chocolate	4324.0510	1875.9554	-56.6	329	Recálculo por edición de factura	2026-08-04 03:59:12	
1381	Vaso de mini cookies	16271.9239	6479.5414	-60.2	329	Recálculo por edición de factura	2026-08-04 03:59:12	
1321	Chocolate caliente con helado y crema	20524.2867	1542.3646	-92.5	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1322	Receta de torta oreo en vaso	44103.1552	10605.6456	-76.0	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1323	Milkshake	30573.9363	2659.3450	-91.3	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1324	PROMO 7: 2 Batidos	61147.8725	5318.6900	-91.3	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1325	PROMO FIN DE MILKSHAKE 7500	61147.8725	5318.6900	-91.3	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1326	MILKSHAKE UNICORNIO	36255.7040	2758.1944	-92.4	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1327	Cappuccino	14005.8757	606.8719	-95.7	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1328	Latte (Avellana, Caramelo, Pistacho)	17554.0367	805.2819	-95.4	315	Recálculo por edición de factura	2026-08-04 03:49:41	
1310	Affogato Especial (Café con helado) Vainilla, Amer...	3008.0742	1226.0742	-59.2	314	Recálculo por edición de factura	2026-08-04 03:39:18	
987	Chocolate caliente con helado y crema	37716.7845	-1006963.1146	-2769.8	191	Recálculo por compra	2026-08-04 01:00:40	
988	Chocolate Caliente con Helado	12520.8971	-683943.9765	-5562.4	191	Recálculo por compra	2026-08-04 01:00:40	
989	Receta para el promedio de precio de Helados	196.7143	-11027.7908	-5706.0	191	Recálculo por compra	2026-08-04 01:00:40	
990	Waffle de pez Taiyaki con helado	36838.7713	-2052521.0270	-5671.6	191	Recálculo por compra	2026-08-04 01:00:40	
991	Pescado taiyaki	36667.1667	-2052692.6316	-5698.2	191	Recálculo por compra	2026-08-04 01:00:40	
992	Milkshake	64958.9320	-2024400.8663	-3216.4	191	Recálculo por compra	2026-08-04 01:00:40	
993	VASITO	22430.6574	-1254400.3304	-5692.3	191	Recálculo por compra	2026-08-04 01:00:40	
994	1/4kg	51139.2248	-2850749.3838	-5674.5	191	Recálculo por compra	2026-08-04 01:00:40	
995	1/2kg	112175.6508	-6271979.2882	-5691.2	191	Recálculo por compra	2026-08-04 01:00:40	
996	1 kg	214041.8721	-11973890.2841	-5694.2	191	Recálculo por compra	2026-08-04 01:00:40	
997	CONO DOBLE	36752.9690	-2052606.8293	-5684.9	191	Recálculo por compra	2026-08-04 01:00:40	
998	Cucurucho simple	36752.9690	-2052606.8293	-5684.9	191	Recálculo por compra	2026-08-04 01:00:40	
999	PROMO HELADO Dos 1/4kg	112369.1165	-6271785.8225	-5681.4	191	Recálculo por compra	2026-08-04 01:00:40	
1000	PROMO 7: 2 Batidos	129917.8639	-4048801.7325	-3216.4	191	Recálculo por compra	2026-08-04 01:00:40	
1001	PROMO FIN DE MILKSHAKE 7500	129917.8639	-4048801.7325	-3216.4	191	Recálculo por compra	2026-08-04 01:00:40	
1002	PROMO FIN SEMANA 2 1/4 HELADO 6800	112347.7049	-6271807.2341	-5682.5	191	Recálculo por compra	2026-08-04 01:00:40	
1003	MILKSHAKE UNICORNIO	70640.6997	-2018719.0986	-2957.7	191	Recálculo por compra	2026-08-04 01:00:40	
1004	Affogato Especial (Café con helado) Vainilla, Amer...	18290.2946	-910314.0602	-5077.0	191	Recálculo por compra	2026-08-04 01:00:40	
825	Chocolate caliente con helado y crema	20200.4481	39167.1471	93.9	191	Recálculo por compra	2026-08-04 01:00:00	
826	Chocolate Caliente con Helado	843.1449	13487.8216	1499.7	191	Recálculo por compra	2026-08-04 01:00:00	
827	Receta para el promedio de precio de Helados	8.5110	212.2977	2394.4	191	Recálculo por compra	2026-08-04 01:00:00	
828	Waffle de pez Taiyaki con helado	1806.0985	39739.4964	2100.3	191	Recálculo por compra	2026-08-04 01:00:00	
829	Pescado taiyaki	1634.4939	39567.8918	2320.8	191	Recálculo por compra	2026-08-04 01:00:00	
830	Milkshake	29926.2592	67859.6571	126.8	191	Recálculo por compra	2026-08-04 01:00:00	
831	VASITO	1021.8018	24203.3227	2268.7	191	Recálculo por compra	2026-08-04 01:00:00	
832	1/4kg	2482.7349	55168.0097	2122.1	191	Recálculo por compra	2026-08-04 01:00:00	
833	1/2kg	5131.3730	121038.9776	2258.8	191	Recálculo por compra	2026-08-04 01:00:00	
834	1 kg	9684.6145	230962.7686	2284.8	191	Recálculo por compra	2026-08-04 01:00:00	
835	CONO DOBLE	1720.2962	39653.6941	2205.1	191	Recálculo por compra	2026-08-04 01:00:00	
836	Cucurucho simple	1720.2962	39653.6941	2205.1	191	Recálculo por compra	2026-08-04 01:00:00	
837	PROMO HELADO Dos 1/4kg	5324.8387	121232.4433	2176.7	191	Recálculo por compra	2026-08-04 01:00:00	
838	PROMO 7: 2 Batidos	59852.5184	135719.3141	126.8	191	Recálculo por compra	2026-08-04 01:00:00	
839	PROMO FIN DE MILKSHAKE 7500	59852.5184	135719.3141	126.8	191	Recálculo por compra	2026-08-04 01:00:00	
840	PROMO FIN SEMANA 2 1/4 HELADO 6800	5303.4271	121211.0317	2185.5	191	Recálculo por compra	2026-08-04 01:00:00	
841	MILKSHAKE UNICORNIO	35608.0269	73541.4248	106.5	191	Recálculo por compra	2026-08-04 01:00:00	
842	Affogato Especial (Café con helado) Vainilla, Amer...	2720.2178	19579.5057	619.8	191	Recálculo por compra	2026-08-04 01:00:00	
665	Receta para el promedio de precio de Helados	11.9895	3.6289	-69.7	191	Recálculo por compra	2026-08-04 00:58:48	
666	Waffle de pez Taiyaki con helado	2453.5977	897.3166	-63.4	191	Recálculo por compra	2026-08-04 00:58:48	
667	Pescado taiyaki	2281.9931	725.7120	-68.2	191	Recálculo por compra	2026-08-04 00:58:48	
669	VASITO	1417.4957	466.4351	-67.1	191	Recálculo por compra	2026-08-04 00:58:48	
670	1/4kg	3382.0393	1220.5378	-63.9	191	Recálculo por compra	2026-08-04 00:58:48	
671	1/2kg	7109.8426	2354.5394	-66.9	191	Recálculo por compra	2026-08-04 00:58:48	
672	1 kg	13461.6928	4383.3867	-67.4	191	Recálculo por compra	2026-08-04 00:58:48	
673	CONO DOBLE	2367.7954	811.5143	-65.7	191	Recálculo por compra	2026-08-04 00:58:48	
674	Cucurucho simple	2367.7954	811.5143	-65.7	191	Recálculo por compra	2026-08-04 00:58:48	
675	PROMO HELADO Dos 1/4kg	7303.3083	2548.0051	-65.1	191	Recálculo por compra	2026-08-04 00:58:48	
678	PROMO FIN SEMANA 2 1/4 HELADO 6800	7281.8967	2526.5935	-65.3	191	Recálculo por compra	2026-08-04 00:58:48	
425	Croissant de manteca relleno de Jamón y Queso	22417.9125	45148.5250	101.4	197	Recálculo por compra	2026-08-03 23:33:41	
359	Receta de Focaccia de Tomates Cherrys comprado a p...	11.8881	23.6156	98.6	63	Recálculo por compra	2026-08-03 17:02:33	
322	Masa Base de Cookie	444619541.7625	17348.8491	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
323	Cookie Artesanal Propia Oreo	111175265.3157	5261.5500	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
324	Cookie Artesanal Propia Chocolina	111155022.9359	5016.2686	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
325	Cookie Artesanal Propia con Chips de Chocolate	111155060.1046	4593.6123	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
326	Vaso de mini cookies	444619824.6973	17349.4772	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
328	Receta de torta oreo en vaso	163837.8646	44121.1212	-73.1	343	Recálculo por compra	2026-08-02 04:01:35	
333	Cappuccino	238583900.5165	14005.4509	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
334	Latte (Avellana, Caramelo, Pistacho)	119317681.4244	17553.8243	-100.0	343	Recálculo por compra	2026-08-02 04:01:35	
34	Chocolate caliente con helado y crema	4077.5437	20097.9882	392.9	337	Recálculo por compra	2026-07-31 01:26:12	
39	Milkshake	6348.1734	29621.7042	366.6	337	Recálculo por compra	2026-07-31 01:26:12	
47	PROMO 7: 2 Batidos	12696.3467	59243.4084	366.6	337	Recálculo por compra	2026-07-31 01:26:12	
48	PROMO FIN DE MILKSHAKE 7500	12696.3467	59243.4084	366.6	337	Recálculo por compra	2026-07-31 01:26:12	
50	MILKSHAKE UNICORNIO	7342.0655	35358.4404	381.6	337	Recálculo por compra	2026-07-31 01:26:12	


***************************************************************************