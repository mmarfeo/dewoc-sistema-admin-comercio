# Promos (combos de 2+ productos) — documento para conversar

> Documento de diseño para discutir, no un plan cerrado. Sigue de
> `docs/stock-reventa-vs-produccion.md`, donde quedó claro que ni Recetas ni Opciones
> resuelven el caso de "vender juntos 2 productos que ya existen por separado, cada uno
> con su propia regla de stock".

## 1. Lo que ya tenés hoy, y por qué no alcanza

Hoy armás la promo dando de alta un producto nuevo (ej. "Promo Café + 2 Medialunas") bajo
una categoría "Promos", con su propio precio. Esa parte — el precio y que aparezca para
venderse en el POS — **ya funciona bien y no hace falta rediseñarla**. Lo que falta es la
mitad del problema: **nada conecta ese producto-promo con el stock real de café y
medialunas** — venderlo no descuenta ni el café ni las medialunas de ningún lado (o, si le
pusiste una receta a mano, la rompe, como vimos en el documento anterior).

## 2. La idea central: no inventar una forma nueva de descontar stock

Café y medialunas **ya tienen cada uno su forma correcta de descontar stock** (Casos 2 y 3
del documento anterior: café se resuelve por receta al momento de la venta, medialuna resta
directo su propio stock ya producido). Lo único que falta es una manera de decirle al
sistema: **"vender 1 Promo = vender 1 Café + 2 Medialunas, cada uno con su propia regla de
siempre"** — no hace falta que la promo tenga ninguna lógica de stock propia.

Esto se puede pensar como "explotar" la promo en sus líneas componentes en el momento de la
venta, igual que ya se hace con las recetas (`RecetaModel::descontarDetalle()` "explota"
sub-recetas recursivamente). Acá sería lo mismo, pero explotando hacia **productos**, no
hacia ingredientes — y una vez explotado, cada componente sigue exactamente el camino que
ya tiene hoy (`VentaModel::registrar()` ya sabe descontar cualquier producto según su
`controla_stock`).

## 3. Ya existe un patrón hermano en el sistema: Opciones

No es una idea nueva para este sistema — `grupos_opciones` / `opciones` /
`producto_grupos_opciones` (usado hoy para "gustos de helado", adicionales, etc.) es
exactamente esta misma idea: un producto que, al venderse, dispara el descuento de **otras**
cosas (ahí, materias primas/insumos vía `cantidad_consumida`). Una Promo sería la misma
idea un nivel más arriba: en vez de apuntar a materia prima/insumo, apuntaría a **productos
completos**, y en vez de una cantidad fija a descontar directo, dispara la regla de stock
que ya tiene cada producto.

## 4. Modelo de datos propuesto (borrador)

Una tabla nueva y chica:

```
producto_promo_detalle
  id
  producto_id            → el producto "Promo" (ej. "Promo Café + 2 Medialunas")
  componente_producto_id → el producto real que contiene (ej. Café, o Medialuna)
  cantidad                 → cuántas unidades de ese componente (ej. 1, 2)
```

El producto "Promo" en sí sigue siendo una fila común en `productos` — con su `nombre`,
`precio_venta` (el precio de la promo, no la suma de precios individuales), su categoría
"Promos", como ya lo hacés. La única diferencia: **no necesita `controla_stock=1` con stock
propio, ni una receta** — directamente no se le mira el stock propio para nada, porque al
venderse se resuelve 100% por sus componentes.

## 4bis. Actualizado con tus respuestas

Con composición fija confirmada, y con que los componentes SÍ tienen que poder rastrearse
en los reportes (tanto "va con una columna aparte en reportes de producto" como "aparece
desglosada en reportes de ventas"), hace falta un segundo elemento además de la tabla de
catálogo de la sección 4 — algo que registre, en cada venta puntual, qué se entregó dentro
de cada promo vendida:

```
ventas_detalle (tabla existente) — se le agrega UNA columna nueva:
  venta_detalle_padre_id  INT UNSIGNED NULL  → si esta línea es un componente que vino de
                                                una promo, apunta a la fila (de esta misma
                                                tabla) de la promo que lo contiene
```

Así, vender "1 Promo Café + 2 Medialunas" no genera una sola fila en `ventas_detalle` como
hoy — genera **tres**:

1. La promo en sí: producto = "Promo Café + 2 Medialunas", cantidad = 1, precio = el precio
   real de la promo (lo que efectivamente se cobró).
2. Café: cantidad = 1, precio = **$0** (ya se cobró en la fila 1, esta fila no suma
   ingresos de nuevo), `venta_detalle_padre_id` = la fila 1.
3. Medialuna: cantidad = 2, precio = **$0**, `venta_detalle_padre_id` = la fila 1.

Con esto, las dos cosas que pediste salen solas, sin duplicar plata ni inventar una segunda
fuente de verdad:

- **Reportes de producto** (ej. "cuánto vendimos de medialunas"): ya suman bien la
  cantidad, porque las filas 2 y 3 existen y tienen cantidad real — antes esas unidades
  quedaban invisibles. La columna "vino de una promo" es simplemente mostrar si
  `venta_detalle_padre_id` está lleno o vacío en esa fila.
- **Reportes de ventas generales**: la fila 1 (la promo) es la que aporta el ingreso real —
  ningún reporte de facturación se desbalancea. Si además el reporte quiere *desglosar* qué
  había adentro, alcanza con traer las filas 2 y 3 agrupadas bajo la fila 1 (mismo
  `venta_detalle_padre_id`).

Esta misma columna es también la que sirve para descontar el stock: al vender, las filas 2
y 3 pasan por el mismo camino que ya existe para cualquier producto normal (línea con
`controla_stock=1` resta directo, línea con `controla_stock=0` y receta propia consume sus
ingredientes) — no hay que escribir una lógica de stock nueva, solo generar esas filas
extra antes de que el descuento de stock haga lo que ya sabe hacer.

**Un detalle a decidir más adelante, no ahora**: si conviene que el ticket/comanda impresos
muestren esas filas 2 y 3 (ej. para que el barista vea "hacer 1 café" dentro de la promo) o
si se ocultan y solo se ven en los reportes — es una decisión de diseño de impresión, no de
esta base de datos, y se puede definir después sin afectar nada de lo de arriba.

## 5. Cómo se vendería (POS)

Buena noticia: **no hace falta ningún cambio de interfaz para encontrarla y venderla** — al
ser un producto común de la categoría "Promos", ya aparece en el buscador y en la grilla de
categorías del POS exactamente como hoy. El cambio es puramente interno: al confirmar la
venta, `VentaModel::registrar()` reconoce que ese producto tiene componentes, y en vez de
(o además de) tratarlo como una línea con su propio stock, genera el descuento de cada
componente con su regla ya existente. La comanda/ticket seguiría mostrando "1x Promo Café +
2 Medialunas" como una sola línea (no 3 líneas sueltas), para que no se vea rara en el
mostrador ni en la cocina.

## 6. Alcance confirmado

Composición fija, promos pre-armadas por vos — no hace falta la variante donde el cliente
elige entre alternativas. Esto simplifica bastante el trabajo: el modelo de la sección 4
(`producto_promo_detalle`, catálogo fijo) alcanza, sin necesidad de construir nada parecido
a Opciones para esto. Si en algún momento aparece un caso de "elegí vos qué va en el
combo", se aborda aparte como una extensión — no hace falta preverlo ahora en el diseño.

## 7. Reportes — resuelto (ver sección 4bis)

Confirmado: los componentes de una promo tienen que poder verse tanto en reportes de
producto (con una columna que marque que vinieron de una promo) como desglosados en
reportes de ventas generales. La columna `venta_detalle_padre_id` de la sección 4bis
resuelve ambos casos sin duplicar ingresos ni inventar una fuente de datos aparte.

## 8. Qué falta tocar (resumen para implementar)

Con el diseño ya cerrado, esto es lo que haría falta construir:

1. **`producto_promo_detalle`** (tabla nueva) — catálogo: qué productos y cantidades
   componen cada promo.
2. **`ventas_detalle.venta_detalle_padre_id`** (columna nueva, nullable) — registro de qué
   se entregó realmente en cada venta puntual de una promo.
3. **`VentaModel::registrar()`** — antes de recorrer el detalle de la venta, "explotar" toda
   línea cuyo producto tenga componentes en `producto_promo_detalle`, agregando las filas
   hijas con precio $0 y `venta_detalle_padre_id` apuntando a la fila de la promo. El resto
   del método (descuento de stock, chequeo de faltantes, `ventas_sin_stock`) sigue
   funcionando igual, porque ahora simplemente hay más filas para procesar con la misma
   lógica de siempre.
4. **Anulación** (`VentaModel::anular()`) — misma idea: al revertir, recorrer también las
   filas hijas y reponerles el stock con la regla de cada una (ya existe la reposición por
   producto, solo hay que asegurarse de que también reponga las filas con
   `venta_detalle_padre_id` seteado).
5. **Formulario de producto** (`/productos`) — cuando el producto pertenece a la categoría
   "Promos" (o se agrega un flag simple "Es promo"), mostrar un selector para armar la
   lista de componentes y cantidades (`producto_promo_detalle`), reusando el mismo patrón
   de buscador que ya existe en Recetas.
6. **Reportes puntuales** a actualizar para mostrar la columna "vino de una promo" o el
   desglose — se resuelven de a uno, no requieren tocar el modelo de datos de nuevo.

¿Empiezo por acá?

---

## 9. Corrección posterior — el costo SÍ se desbalanceaba (13/10/2026)

La sección 4bis dice que "ningún reporte de facturación se desbalancea" porque las filas
hijas van en $0. Eso es cierto **del lado del ingreso**, pero el diseño original no miró el
lado del **costo**, y ahí el esquema sí se duplicaba:

- Una promo no tiene receta propia, así que `VentaModel::registrar()` le calcula su
  `costo_mp_real` como `cantidad × productos.precio_costo`.
- Y `ProductoModel::sincronizarPrecioCostoPromo()` mantiene ese `precio_costo` igual a la
  **suma del costo de los componentes**.

O sea: el costo de los componentes ya está contado una vez en la fila de la promo. Todo
reporte que sumaba también las filas hijas lo contaba **dos veces**. El síntoma visible fue
una consulta de margen en el Chat IA donde cada componente aparecía como un producto más,
con "Venta total: $0" y margen negativo igual a su costo — y, menos visible, el costo de MP
del período inflado (en el día que se detectó: $17.283,28 informados contra $10.083,69
reales, $7.199,59 de más).

Arreglado agregando `AND vd.venta_detalle_padre_id IS NULL` a toda consulta de costo/margen
(ver la nota canónica en `ReportesModel::margenVentasPorProducto()`, que explica el porqué y
desde la que se referencian las demás): `margenVentasPorProducto()`, `margenVentasTotal()`,
`ventasConCostoReal()`, `margenPorProducto()`, `topProductos()`,
`ReporteSemanalModel::margenBruto()`, `SeparacionIngresosModel::calcularReferencia()` /
`detalleRecompraPorProducto()` y `ProyeccionModel::tendenciaMargenProducto()`. También se
agregó la regla al prompt del Chat IA, para que las consultas SQL ad-hoc que arma el modelo
no repitan el error.

Dos criterios que conviene no perder de vista al tocar esto de nuevo:

1. **El filtro es por `venta_detalle_padre_id`, nunca por "precio $0".** Una venta de un
   producto suelto cargada en $0 (producto al que le falta el precio de venta) tiene el padre
   en NULL y tiene que **seguir apareciendo**, justamente para que el usuario note que a ese
   producto le falta el precio. Por eso `margenVentasPorProducto()` devuelve además el flag
   `vendido_sin_precio` (se vendieron unidades, no entró plata) y el prompt del chat obliga a
   avisarlo en vez de reportar un "margen 0%" sin explicación.
2. **Las consultas de CANTIDAD para proyección sí cuentan las filas hijas, a propósito**
   (`ProyeccionModel::proyectarVentasProducto()` / `calcularPesoFeriado()`): ahí las unidades
   del componente son demanda real, y la fila de la promo no aporta consumo de materia prima
   propio porque no tiene receta. Mismo criterio en `StockModel::reporteComparativo()`, que
   compara unidades físicas vendidas contra consumo de MP.

El desglose "qué salió adentro de las promos" que pedía la sección 7 quedó resuelto en
`margenVentasPorProducto()`: cada fila de promo trae un array `componentes` con cantidad y
costo por componente, para mostrar anidado bajo la promo y **sin margen propio** (un
componente de promo no tiene precio propio: el ingreso lo cobró la promo entera).
