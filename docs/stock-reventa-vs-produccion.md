# Cómo maneja el stock cada tipo de producto (reventa / hecho al momento / producido por lotes)

> Documento explicativo, no un plan de cambios — describe el comportamiento **ya existente**
> del sistema (`VentaModel.php`, `RecetaModel.php`, `FacturaCompraModel.php`), para saber
> cómo configurar cada tipo de producto según cómo se maneja en el negocio.

## Los 3 patrones que ya soporta el sistema

| | Reventa simple (gaseosa) | Hecho al momento (tostado) | Producido por lotes (medialunas) |
|---|---|---|---|
| `productos.controla_stock` | **1** (tildado) | **0** (destildado) | **1** (tildado) |
| ¿Necesita Receta? | No | Sí (con "Producto final" = ese producto) | Sí (con "Producto final" = ese producto) |
| ¿Cuándo se arma/prepara? | Nunca — se compra ya terminado | En el momento de cada venta | Antes, en un lote (`/recetas` → Producción) |
| ¿Qué descuenta CADA venta? | El stock propio del producto | Los ingredientes de la receta, uno por uno | El stock propio del producto (ya se armó antes) |

La diferencia clave entre las tres es **cuándo pasa la transformación de materia prima a
producto terminado**: nunca (reventa), en cada venta (hecho al momento), o antes en un lote
(producción). El resto del comportamiento se deduce de esa decisión.

---

## Caso 1 — Reventa simple: gaseosa (no hace falta receta)

Para un producto que se compra ya terminado y se revende tal cual, **no hace falta crear
ninguna receta**. Es el caso más simple del sistema:

1. En la ficha del producto (`/productos`), dejar tildado **"Controla stock"** (es el valor
   por defecto, no hay que tocar nada ahí).
2. Al registrar la compra a proveedor, esa línea va con **tipo = "producto"**, apuntando a
   la gaseosa. Eso es lo que suma stock — `facturas_compra_detalle` tiene una columna
   `producto_id` justamente para este caso, distinta de `materia_prima_id`/`insumo_id`.
3. Cada venta descuenta directo `productos.stock_actual` de la gaseosa
   (`UPDATE productos SET stock_actual = stock_actual - cantidad ... WHERE controla_stock=1`).
   No hay ningún ingrediente de por medio, no hay nada más que configurar.
4. Si se anula la venta, se repone ese mismo stock — también directo, sin pasar por nada más.

**Resumen**: reventa = producto con `controla_stock=1` + comprado directamente como
"producto" en las facturas de compra. Cero recetas.

---

## Caso 2 — Hecho al momento: tostado de jamón y queso

Acá sí hace falta una **Receta**, pero el mecanismo es distinto al de un lote de producción:
la transformación (armar el tostado) pasa **en el instante de la venta**, no antes.

### Configuración necesaria

1. Materias primas ya cargadas: Jamón, Queso, Pan (cada una con su `stock_actual` en la
   unidad que corresponda — gr, fetas, unidades, lo que uses).
2. Producto "Tostado de jamón y queso": **"Controla stock" DESTILDADO** (`controla_stock = 0`).
3. Una Receta (`/recetas`) con:
   - **Producto final** = "Tostado de jamón y queso" (este vínculo es el que hace que todo
     funcione — sin este campo cargado, el sistema no sabe qué receta corresponde a ese
     producto y la venta no va a descontar ningún ingrediente).
   - Ingredientes: Jamón (ej. 2 fetas), Queso (ej. 2 fetas), Pan (ej. 1 unidad).
   - Rendimiento = 1 (cada "ejecución" de la receta arma 1 tostado — a diferencia de un
     lote de producción, acá no tiene sentido un rendimiento mayor a 1).

### Qué pasa y cuándo — línea de tiempo completa

| Momento | Stock del **tostado** (`productos.stock_actual`) | Stock de **jamón/queso/pan** (`materias_primas.stock_actual`) |
|---|---|---|
| Se compra jamón/queso/pan al proveedor | Sin cambios | **Sube** (compra normal, como cualquier materia prima) |
| Se vende un tostado | **No se toca** — la condición `controla_stock=1` de la resta de stock del producto lo excluye a propósito | **Baja** en el momento — el sistema busca la receta ligada a este producto y descuenta jamón/queso/pan según las cantidades de la receta |
| No alcanza el jamón/queso/pan | — | El sistema avisa "falta stock" y pide confirmar antes de vender igual (queda anotado para revisar después, y puede dejar el stock en negativo si se confirma) |
| Se anula esa venta | Sin cambios (nunca se había tocado) | **Sube de nuevo** — se repone lo que esa venta había consumido |

**La conclusión importante**: el `stock_actual` propio del producto "Tostado de jamón y
queso" **nunca se usa ni se actualiza** en este flujo — queda como un número sin sentido
real (por eso mismo `/stock/inventario` ya lo excluye automáticamente al tener
`controla_stock=0`, como vimos en la conversación anterior). Lo único que importa, lo único
que sube y baja de verdad, es el stock de jamón, queso y pan.

### Nota — el otro camino posible (no es el que corresponde acá, pero para contraste)

Si en algún momento quisieran preparar tostados **por adelantado** en un lote y guardarlos
(ej. pre-armados para calentar rápido), ahí sí usarían el patrón de "Producción por lotes"
(igual que las medialunas) — ver Caso 3 más abajo.

---

## Caso 3 — Producción por lotes: cookies (se hornean una vez por semana)

Acá la transformación (mezclar, hornear) pasa **una sola vez, adelantada**, para toda la
semana de golpe — no en cada venta como el tostado, ni nunca como la gaseosa. Es la misma
lógica que ya usa el sistema para las medialunas.

### Configuración necesaria

1. Materias primas: harina, manteca, azúcar, chips de chocolate, huevos, etc. — cada una
   con su `stock_actual` normal.
2. Producto "Cookies": **"Controla stock" TILDADO** (`controla_stock = 1`) — a diferencia
   del tostado. Acá sí importa el número de stock del producto, porque es lo que se va
   descontando venta a venta durante toda la semana.
3. Una Receta con:
   - **Producto final** = "Cookies".
   - Ingredientes: harina, manteca, azúcar, chips, huevos, con las cantidades que lleva
     **una tanda completa** (toda la mezcla que hacen de una vez).
   - **Rendimiento** = cuántas cookies salen de esa tanda (ej. si una tanda da 200
     cookies, Rendimiento = 200, Unidad = "unidades"). Este campo es la clave de todo el
     cálculo: es lo que traduce "1 tanda" en "200 cookies de stock".

### Qué pasa y cuándo

| Momento | Stock de **harina/manteca/azúcar/etc.** | Stock de **Cookies** (`productos.stock_actual`) |
|---|---|---|
| Se compra harina/manteca/etc. al proveedor | **Sube** (compra normal) | Sin cambios |
| **Día de producción** (1 vez por semana): en `/recetas` → esa receta → Producción, cargan cuántas tandas hacen (ej. 1, o 2 si hacen el doble esa semana) | **Baja de una sola vez**, toda la mezcla que consume esa cantidad de tandas — si no alcanza algún ingrediente, el sistema avisa qué falta antes de confirmar la producción | **Sube de una sola vez** — tandas × Rendimiento (ej. 1 tanda × 200 = +200 cookies) |
| Cada venta de una cookie, el resto de la semana | Sin cambios — ya no se vuelve a tocar la harina por cada cookie vendida | **Baja de a 1** (o la cantidad vendida), directo, sin pasar por la receta de nuevo |
| Se anula una venta de cookies | Sin cambios | **Sube** lo que se había descontado de esa venta |
| Se venden todas antes de la próxima producción | Sin cambios | Puede llegar a 0 y seguir vendiéndose en negativo — a diferencia del tostado, acá el sistema **no pide confirmación** al quedarse sin stock (esa validación es específica del caso "hecho al momento"). Por eso conviene mirar el stock de Cookies contra el `stock_minimo` configurado en su ficha, para saber si conviene adelantar la próxima tanda. |

**La diferencia clave con el tostado**: en el tostado, la receta se ejecuta en cada venta
(una acción del cajero dispara el descuento de ingredientes). En las cookies, la receta se
ejecuta una sola vez por semana, en una acción separada y deliberada ("Producción"), y las
ventas del resto de la semana ni se enteran de que existe una receta — solo restan del
stock de Cookies como si fuera cualquier producto de reventa simple (Caso 1), porque en ese
momento, para el sistema, ya son cookies terminadas y contadas.

### Resumen de todo el ciclo semanal

1. Durante la semana: se compra harina/manteca/etc. normalmente (sube su stock).
2. Un día fijo: se ejecuta la Producción de la receta de Cookies por la cantidad de
   tandas que corresponda → baja la harina/manteca/etc. de golpe, sube el stock de
   Cookies de golpe. Queda un registro en el historial de producción (fecha, cantidad).
3. Resto de la semana: cada cookie vendida resta 1 del stock de Cookies. Nadie vuelve a
   tocar harina/manteca hasta la próxima Producción.
4. Cuando el stock de Cookies se acerca al mínimo (o se termina), es la señal de que
   toca la próxima tanda — no es automático, alguien tiene que ir y producirla.
