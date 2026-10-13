# Diagnóstico de costos de Materias Primas y Recetas

> Objetivo: cuando un costo de MP o de receta "no tiene sentido", este
> documento explica cómo lo calcula el sistema hoy y da un checklist +
> un script SQL (`database/diagnostico_costos_mp.sql`, **solo lectura**)
> para encontrar si la causa es una factura mal cargada, una cantidad de
> receta mal cargada, una unidad de medida cruzada, u otra cosa.

---

## 1. Cómo se calcula el costo hoy

### 1.1 Materia prima — Costo Promedio Ponderado (CPP)

`materias_primas.costo_unitario` está expresado en la **unidad base** del
ítem (la de `unidad_medida_id`, ej. gramo/ml/unidad — no la unidad en la
que se compra). Cada compra con cantidad positiva lo recalcula así
([`FacturaCompraModel::actualizarStock()`](../app/Models/FacturaCompraModel.php)):

```
costo_unitario_nuevo = (stock_actual × costo_unitario_actual + cantidad_comprada × precio_unitario)
                        ÷ (stock_actual + cantidad_comprada × cantidad_contenida)

stock_actual_nuevo = stock_actual + cantidad_comprada × cantidad_contenida
```

- **`cantidad_comprada`** y **`precio_unitario`** están en la unidad en la
  que se compró (ej. "3 bidones" a "$4000 el bidón").
- **`cantidad_contenida`** convierte esa unidad de compra a la unidad base
  de stock/receta (ej. 1 bidón = 5000 ml → `cantidad_contenida = 5000`).
  Si la línea de factura no la informa, se asume `1` (se compra y se
  consume en la misma unidad).

Este cálculo asume que **`precio_unitario` es el precio por unidad de
COMPRA** (el bidón entero), no por unidad base — es la fuente de error
más común (ver §2.1).

Al **editar o anular** una factura, el sistema **no** revierte/reaplica
de forma incremental: recalcula el costo repasando **todo el historial
real** de compras no anuladas de ese ítem, en orden cronológico
(`recalcularCostoDesdeHistorial()`). Esto significa que **cualquier
factura vieja con un dato mal cargado sigue pesando en el costo actual**
mientras siga activa (no anulada, no eliminada) — no hace falta tocarla
para que siga rompiendo el cálculo.

### 1.2 Desvío (`desvio_pct`) y merma (`merma_pct`) — no son lo mismo

- **`desvio_pct`** (en `materias_primas`/`productos`): pérdida **no
  explicada** (robo, rotura, consumo interno, olvido de contar) — se calibra en
  el ciclo de conteo físico comparando cada conteo contra el anterior (ver
  `docs/desvio-mermas-checklist.md`, Fase 9) o se carga a mano. Nunca es
  negativo. Se aplica como `costo_unitario × (1 + desvio_pct/100)`.
- **`merma_pct`** (en `receta_detalle`, por ingrediente): pérdida de
  **proceso**, conocida al diseñar la receta (pelado, evaporación). Se
  aplica como `costo_unitario × cantidad × (1 + merma_pct/100)`.

Ambos suman al costo final pero vienen de fuentes y significados
distintos — un valor absurdo en cualquiera de los dos infla el costo sin
que la factura tenga nada de malo.

### 1.3 Ingredientes genéricos

Cuando una receta usa un **ingrediente genérico** (ej. "Jamón cocido") en
vez de una materia prima puntual, su costo es el **promedio ponderado por
stock** de `costo_unitario × (1 + desvio_pct/100)` entre todas las
materias-primas "variante" de ese genérico (`materias_primas.ingrediente_generico_id`).
El sistema asume que todas las variantes comparten la misma unidad base
que el genérico — si una variante quedó cargada en otra unidad, el
promedio mezcla unidades sin conversión.

### 1.4 Sub-recetas (recetas que usan otra receta como ingrediente)

El costo se calcula recursivamente: `calcularCosto(sub-receta) / rendimiento`.
Un `rendimiento` mal cargado en la sub-receta (ej. `1` en vez de `100`
porciones) multiplica el costo que hereda cualquier receta que la use.

### 1.5 Propagación

Cada compra que cambia el costo de una MP/insumo dispara, en cadena:
recalcular el costo de toda receta que lo use directamente → recalcular
recetas que usen su ingrediente genérico → sincronizar `productos.precio_costo`
del producto 1:1 y de los vinculados por `producto_receta` → registrar el
salto en `recetas_historial_costo` → avisar si el margen cayó mucho. Un
dato mal cargado en **una sola compra** se propaga automáticamente a
todos los productos que dependen de ese ingrediente.

---

## 2. Causas más probables, de más a menos frecuente

### 2.1 `cantidad_contenida` mal cargada o inconsistente entre compras
El bug más común. Si una compra de un envase grande (bidón, caja x12,
bolsa de 25kg) **no** carga `cantidad_contenida`, el sistema suma al
stock solo "1 unidad" y el costo unitario termina siendo el precio del
envase entero *por unidad base* — carísimo. Al revés (cargarla en un
ítem que se compra y consume en la misma unidad) diluye el costo. Peor
aún si el mismo ítem tiene compras con y sin este campo cargado: el CPP
mezcla ambas escalas. → **Sección 1 y 2** del script SQL.

### 2.2 `precio_unitario` cargado como total de la línea (o en la unidad equivocada)
Si se tipeó el importe total de la línea en el campo "precio unitario",
o el precio por unidad base cuando correspondía por unidad de compra (o
viceversa), el CPP queda multiplicado/dividido por la cantidad. →
**Sección 3**.

### 2.3 Migración de unidad base corrida a destiempo o dos veces
`database/materia_prima_stock_base_migration.sql` convierte
`stock_actual`/`costo_unitario` de unidad de compra a unidad base **una
sola vez**; correrla de nuevo (o correrla antes de desplegar el código
que ya asume unidad base) vuelve a multiplicar/dividir esos valores. Si
el negocio tiene MPs con costos que son sistemáticamente un múltiplo
"redondo" (×1000, ÷5000, etc.) de lo esperado, sospechar esto primero. →
**Sección 1** (compara lo guardado contra lo que da recalcular desde cero
el historial de compras).

### 2.4 Facturas duplicadas o mal anuladas
Una factura cargada dos veces, o una que debería estar anulada pero
sigue activa, sigue pesando en el CPP cada vez que se recalcula. →
**Sección 4**.

### 2.5 Unidad de medida cruzada entre la ficha de la MP y la receta
Si la ficha dice "gramo" pero alguien pensó en kilos al cargar una
compra o una receta, el costo por gramo queda ×1000 o ÷1000 de lo real.
→ **Secciones 8 y 10**.

### 2.6 `receta_detalle.cantidad` en la unidad equivocada
Mismo problema que 2.5 pero del lado del consumo: la MP puede tener el
costo perfecto y aun así la receta cargar "cantidad" en una escala
distinta a la de la ficha. → **Sección 10**.

### 2.7 `merma_pct` o `desvio_pct` con valores absurdos
Cargados como enteros grandes (ej. 500 en vez de 5) o negativos. →
**Secciones 6 y 7**.

### 2.8 Ingrediente genérico con variantes en distinta unidad
→ **Sección 8**.

### 2.9 Sub-receta con rendimiento mal cargado
→ **Sección 9**.

---

## 3. Cómo usar el script de diagnóstico

Archivo: [`database/diagnostico_costos_mp.sql`](../database/diagnostico_costos_mp.sql)
— son 12 `SELECT` independientes y **de solo lectura** (no modifican
nada), pensados para correr contra la base real. Se pueden correr todos
juntos o de a uno.

Orden sugerido para investigar:
1. Corré la **sección 1** primero — te dice qué materias primas tienen el
   `costo_unitario` guardado desincronizado de lo que da su historial real
   de compras. Es el síntoma más directo de "el costo no tiene sentido".
2. Para cada MP que aparezca ahí, corré las **secciones 2 y 3** filtrando
   por ese `materia_prima_id` (agregá `AND mp.id = <id>` a la subconsulta)
   para ver si la causa es `cantidad_contenida` inconsistente o un
   `precio_unitario` outlier en alguna factura puntual.
3. Corré la **sección 11** para ver qué productos tienen el síntoma final
   (margen negativo o absurdo) y de ahí navegar a su receta.
4. Corré la **sección 12** para ver si hay un salto de costo que coincida
   en fecha con alguna factura sospechosa de los pasos anteriores.
5. Las secciones 5 a 10 son chequeos de "salud general" de los datos, no
   dependen de un síntoma puntual — sirven para una auditoría completa.

Para validar el costo **de una receta puntual** tal como lo ve la
aplicación (incluyendo sub-recetas y genéricos, que el SQL no reproduce
recursivamente), usá lo que ya existe en la app en vez de reimplementarlo
a mano:
- `recetas/form.php` al editar una receta muestra el desglose
  costo de MP / desvío / merma por ingrediente.
- `RecetaModel::desglosarCosto()` es la fuente de ese desglose si hace
  falta consultarlo por código.
- "Exportar" en el listado de Recetas (`RecetaModel::findParaExportar()`)
  da una fila por ingrediente de cada receta con su costo ya calculado,
  útil para revisar todo el catálogo de una.

---

## 4. Una vez identificada la causa

- **Dato mal cargado en una factura ya vieja** (cantidad_contenida,
  precio_unitario, cantidad): corregir esa línea desde **Editar factura**
  (no un `UPDATE` manual a `materias_primas`) — al guardar, el sistema
  dispara `recalcularCostoDesdeHistorial()` y recalcula el CPP correcto
  desde cero, además de propagar el cambio a recetas y productos.
- **Factura duplicada**: anular la copia de más desde la app (no
  eliminar por SQL) para que el historial y la propagación queden
  consistentes.
- **`cantidad_contenida` de la ficha desactualizada**: se corrige
  automáticamente con la próxima compra que informe el valor correcto en
  la línea; no hace falta tocarla a mano salvo urgencia.
- **`receta_detalle.cantidad`, `merma_pct`, `desvio_pct`, `rendimiento`
  de una sub-receta**: se editan desde el formulario de la receta/MP
  correspondiente — cualquiera de estos dispara el recálculo y la
  propagación al guardar.
- Si hace falta forzar un recálculo de costo de una MP sin registrar una
  compra nueva (ej. después de corregir varias facturas viejas a mano por
  algún motivo excepcional), replicar la lógica de
  `recalcularCostoDesdeHistorial()` es más seguro que un `UPDATE` directo,
  porque además dispara la propagación a recetas y productos.

---

## 5. Resultados del diagnóstico — 2026-08-11

> Corrida real de `database/diagnostico_costos_mp.sql` contra la base de
> Hostinger. No es una sola causa: son **3 causas raíz independientes que
> se retroalimentan entre sí**, más un hallazgo aparte de catálogo (no es
> un bug de cálculo). Detalle completo de la evidencia en el hilo de
> conversación; acá queda el resumen accionable.

### 5.1 `desvio_pct` saturado en el límite del campo (la más grave)

48 materias primas quedaron en exactamente `999.99` (39 ítems) o
`-999.99` (5 ítems) — el máximo/mínimo que permite `DECIMAL(5,2)`. Que
caigan justo en el límite del campo (y no en un valor cualquiera) indica
que el cálculo automático de desvío dio algo mucho más extremo todavía y
quedó recortado ahí. **25 de los 48 son la familia "Helado en balde de
\_\_\_ X 6400 kg"** — se recalibran en el ciclo de conteo físico
(`DesvioModel::registrar()`), que resuelve el "consumo teórico" vía
`producto_receta` tomando la receta de menor id como "principal"
(limitación ya documentada en `docs/desvio-mermas-checklist.md`); si esa
resolución agarra la receta equivocada para un producto vendido por
porciones de un balde, o si el conteo físico de ese lote se cargó con
las unidades cruzadas, el desvío se dispara para todo el lote a la vez.

Efecto: `costo_unitario × (1 + 999.99/100)` ≈ **×11 el costo real** en
toda receta que use esas MPs (helados, milkshakes, tortas, promos).

**Causa raíz confirmada en el código** (corrida la consulta de
`desvio_historial` de arriba): las 25 filas del ciclo del 2026-08-03
tienen `cantidad_explicada_ventas = 0.000` y un `desvio_pct` guardado
de exactamente `9999.99` — el máximo que admite la columna
(`DECIMAL(6,2)`, ver `database/desvio_pct_migration.sql:32`). No es
coincidencia que las 25 caigan en el mismo valor exacto: es la prueba
de que el cálculo real dio muchísimo más y se truncó ahí.

El bug está en `DesvioModel::registrar()` ([app/Models/DesvioModel.php:40](../app/Models/DesvioModel.php#L40)):
```php
$desvioCantidad = round($stockTeorico - $stockFisico, 3);   // unidad BASE (ej. gramos)
$desvioPct      = round($desvioCantidad / $cantidadComprada * 100, 2);
```
`$cantidadComprada` es `SUM(fcd.cantidad)` ([línea 76-88](../app/Models/DesvioModel.php#L76-L88)) — la cantidad de
**unidades de COMPRA** (ej. "19 baldes"), sin multiplicar por
`cantidad_contenida`. El numerador está en gramos, el denominador en
"baldes comprados": son unidades distintas. Con la fila real de
"Helado en balde de Crema del Cielo X 6400 kg" (`desvio_cantidad =
972.450` gramos, `cantidad_comprada = 19` baldes):
`972450 / 19 × 100 ≈ 5.118.157%` — un número sin sentido, que se trunca
a `9999.99` al guardarlo en `desvio_historial`, y **se trunca una
segunda vez** a `999.99` cuando `recalibrarDesvioPct()` ([línea 144](../app/Models/DesvioModel.php#L144))
promedia esos valores ya truncados y los escribe en
`materias_primas.desvio_pct` (`DECIMAL(5,2)`, tope `999.99`). Doble
saturación en cascada — el `999.99%` que terminó en la ficha de estas
25 MPs no es un desvío real medido, es un artefacto del bug.

**Corrección de la causa raíz — ya aplicada** en
[app/Models/DesvioModel.php:40](../app/Models/DesvioModel.php#L40):
`$desvioPct` ahora divide `$desvioCantidad` por `$stockTeorico` (misma
unidad base) en vez de por `$cantidadComprada` (unidades de compra).

**Sobre `cantidad_explicada_ventas = 0` — hipótesis inicial corregida**:
al revisar `StockController::procesarInventario()` y la vista
`stock/inventario.php`, el `stock_teorico` que se compara contra el
conteo físico **no** se calcula a partir de `cantidadExplicadaVentas()`
— es directamente `stock_actual` en el momento de abrir la pantalla de
conteo (`app/Views/stock/inventario.php:27`, `data-teorico="<?=
$it['stock_actual'] ?>"`). Es decir: `cantidad_explicada_ventas` es una
columna puramente informativa/de auditoría en `desvio_historial`, no
alimenta `desvio_cantidad` ni `desvio_pct` — mi hipótesis original de
que explicaba el `stock_teorico` inflado de estas 25 MPs era incorrecta.

Dicho eso, **sí encontré un bug real** en `cantidadExplicadaVentas()`
([línea 96](../app/Models/DesvioModel.php#L96)): solo sumaba consumo vía
`receta_detalle`, pero estos ítems de helado se venden con sabor
elegido en el momento a través del sistema de "opciones"
(`grupos_opciones`/`opciones`/`ventas_detalle_opciones`, ver
`database/opciones_productos_migration.sql`) — un camino de consumo
totalmente aparte, que `VentaModel::registrar()` sí descuenta de
`stock_actual` en tiempo real pero que `cantidadExplicadaVentas()`
ignoraba por completo. Por eso daba 0 en las 25 filas aunque sí se
vendió helado — la columna de auditoría mentía, aunque no rompía el
cálculo del desvío. **Ya corregido** ([línea 101](../app/Models/DesvioModel.php#L101)):
ahora suma también `ventas_detalle_opciones → opciones.cantidad_consumida`.

**Pregunta que queda abierta**: por qué `stock_actual` de estas 25 MPs
llegó a ser tan grande (cientos de miles de gramos) antes de que el
conteo del 2026-08-03 lo corrigiera — el patrón (`cantidad_comprada` en
baldes chico, `stock_teorico` enorme) es compatible con el mismo tipo
de problema de `cantidad_contenida` de §5.2, aplicado a las compras de
estos 25 ítems. Sección 4 de la solución, más abajo, da la consulta
para confirmarlo.

**Corrección — resumen**:
1. ✅ Fórmula de `desvio_pct` arreglada en `DesvioModel::registrar()`.
2. ✅ `cantidadExplicadaVentas()` arreglada para sumar también consumo
   vía "opciones" — mejora la auditoría, no cambiaba el desvío en sí.
3. ⬜ Recomputar con la fórmula nueva lo que ya está guardado: script
   [`database/desvio_pct_formula_fix.sql`](../database/desvio_pct_formula_fix.sql)
   (tiene un preview de solo lectura arriba, y las `UPDATE` abajo — no
   aplicado todavía, pendiente de que el usuario lo corra).
4. ⬜ Confirmar con esta consulta si `cantidad_contenida` de las
   facturas de estos 25 ítems tiene el mismo patrón inconsistente de
   §5.2 (probable causa de fondo del `stock_actual` inflado):
   ```sql
   SELECT mp.id, mp.nombre, fc.id AS factura_id, fc.fecha,
          fcd.cantidad, fcd.cantidad_contenida, fcd.precio_unitario
   FROM facturas_compra_detalle fcd
   JOIN facturas_compra fc ON fc.id = fcd.factura_compra_id
   JOIN materias_primas mp ON mp.id = fcd.materia_prima_id
   WHERE fcd.materia_prima_id IN (147,146,145,144,142,141,140,139,148,149,
                                   150,137,136,119,120,123,125,127,135,134,
                                   133,132,131,130,128)
     AND fc.eliminado = 0 AND fc.estado <> 'anulada'
   ORDER BY mp.nombre, fc.fecha;
   ```

### 5.2 `cantidad_contenida` cargada de forma inconsistente (la más frecuente)

37 materias primas tienen facturas donde a veces se completa
`cantidad_contenida` y a veces se deja vacía (→ se asume 1) **para el
mismo ítem**. Ejemplos extremos: *Fiambre Jamón cocido* usó 750, 1, 250
y 100 en distintas compras; *Tapa con sorbete Vaso Milano* usó 2, 4, 25,
20, 30 y 1. La consulta de precios outlier confirma el efecto: las
facturas #52 (Fiambre Jamón cocido, Queso dambo feteado) y #276 (Tapa
con sorbete) son justamente las que salen 4x-8x el precio promedio del
ítem — porque esas líneas puntuales dejaron `cantidad_contenida` vacía
cuando para ese ítem normalmente sí se carga.

Corrección: desde **Editar factura**, completar `cantidad_contenida` en
esas líneas puntuales (no a mano en la tabla) para que el sistema
recalcule el CPP correcto desde cero y lo propague.

> Ver §6 más abajo — esta es la causa que motivó una revisión del
> criterio de carga de estos dos campos, porque el diagnóstico mostró
> que la ambigüedad es real y no solo un descuido puntual.

### 5.3 Stock negativo (rompe la fórmula del CPP)

5 ítems con stock negativo, y coinciden con los mayores outliers de
costo: `CAFÉ BLEND ESTILO ITALIANO` y `CAFÉ SANTOS FINO` (stock -241
ambos, costo guardado 63x el que da el historial de compras),
`Levite de Pomelo x 500` (stock -4910, 63x), `Pote Térmico de Helado sin
tapa 370cc` (stock -103.350, costo_unitario terminó **negativo**).
Con stock negativo, `(stock×costo + cant×precio)/(stock+cantBase)`
divide por un número chico o negativo y el resultado se dispara o se
vuelve negativo — visible en estado puro en la factura 191 (3 ediciones
en 2 minutos), que dejó "1 kg" de helado en **-$11.973.890** de costo.

Corrección: encontrar por qué el stock de estos 5 ítems quedó negativo
(¿venta/consumo sin descuento correcto, ajuste manual, edición de
factura que revirtió de más?) y corregirlo con un ajuste de stock real
antes de que se vuelva a comprar ese ítem.

### 5.4 Hallazgo aparte — no es un bug de cálculo: falta costo en la mayoría del catálogo

141 productos con margen "absurdo", pero 137 tienen `precio_costo =
0.00` directamente — no tienen receta vinculada o nunca se sincronizó
el costo. Solo 4 productos tienen un costo real superior al precio de
venta (Macarons -811%, Tostado jamón y queso -427%, PALETAS -51,6%,
Promo 2 Milkshake Caliente -34,4%) y esos sí son consecuencia directa de
§5.1-5.3. Los otros 137 son un problema de catálogo aparte — auditar
cuáles deberían tener receta y no la tienen.

### 5.5 Descartado como causa
Facturas duplicadas, merma fuera de rango, genéricos con variantes en
otra unidad y sub-recetas con rendimiento inválido: todas dieron 0
filas. La consulta de cantidad de receta outlier dio solo 3 casos con
muestra demasiado chica para confiar — no prioritaria.

### 5.6 Plan de acción, en orden
1. Correr la consulta de `desvio_historial` de §5.1 para entender el
   origen del desvío saturado en los ítems de helado.
2. Corregir el stock negativo de los 5 ítems de §5.3.
3. Editar las facturas de §5.2 para completar `cantidad_contenida`
   donde falta (empezar por la factura 52 y la 276).
4. Con 1-3 corregidos, re-correr la sección 1 del script original — la
   lista de 62 debería reducirse sola (cada corrección dispara
   recálculo y propagación).
5. Recién ahí auditar los 137 productos sin receta vinculada de §5.4.

---

## 6. Ambigüedad de `cantidad_contenida` vs. `cantidad` al cargar una factura

> Origen: al revisar §5.2, surgió la duda de cómo cargar dos compras del
> mismo ítem con presentaciones distintas (ej. "1 caja de 12 alfajores a
> $12.000" vs. "3 alfajores sueltos a $1.000 c/u"). Este punto documenta
> el criterio correcto hoy y por qué el campo se presta a error.

### 6.1 Cómo funciona hoy (y por qué el ejemplo del alfajor SÍ da bien)

La tabla de ítems de una factura tiene, en este orden, "Cant. Cont."
(`cantidad_contenida`) → "Cant." (`cantidad`) → "P. Unit. ($)". La
relación es: **`cantidad` es cuántas unidades de COMPRA llevaste,
`cantidad_contenida` es cuántas unidades BASE trae cada una de esas
unidades de compra, y `precio_unitario` es el precio por unidad de
COMPRA** (no por unidad base).

- Caja de 12 alfajores a $12.000 la caja: `cantidad = 1` (una caja),
  `cantidad_contenida = 12` (contiene 12), `precio_unitario = 12000`
  (precio de la caja entera). Subtotal = 1 × 12000 = $12.000 ✔.
- 3 alfajores sueltos a $1.000 c/u: `cantidad = 3` (la unidad de compra
  ES el alfajor), `cantidad_contenida = 1` o vacío (no hay envase que
  convertir), `precio_unitario = 1000` (precio por alfajor).
  Subtotal = 3 × 1000 = $3.000 ✔.

Con esas dos líneas cargadas así, el CPP da exactamente lo esperado:
después de la primera, stock=12 a $1.000/alfajor; después de la
segunda, stock=15 y costo = (12×1000 + 3×1000)/(12+3×1) = **$1.000**
— consistente, porque ambas compras salieron al mismo precio real por
alfajor.

### 6.2 Dónde está el problema real (y por qué §5.2 encontró lo que encontró)

El campo no está roto — la ambigüedad es de **criterio humano** al
completarlo, y el sistema no avisa cuando alguien lo carga distinto a
como veía cargando ese mismo ítem antes. Con productos por peso (el
caso real de "Fiambre Jamón cocido" en §5.2) hay dos convenciones
igual de válidas para la MISMA compra real, pero que dan números de
`precio_unitario` totalmente distintos:

- **Convención A** ("compré 1 porción que pesa X"): `cantidad = 1`,
  `cantidad_contenida = 250` (gramos que trae esa porción),
  `precio_unitario = 1824` (precio de la porción entera).
- **Convención B** ("compré X gramos sueltos"): `cantidad = 500`,
  `cantidad_contenida = 1` (o vacío), `precio_unitario` tendría que ser
  el precio **por gramo** (≈3,65), no el total de la compra.

La factura #52 de §5.2 cargó `cantidad = 500` (como si fuera
Convención B) pero dejó `precio_unitario = 1824` (el total de la
compra, propio de la Convención A) — mezcló las dos convenciones en la
misma línea. El sistema no tiene forma de detectar esto porque, tomado
aislado, es una línea perfectamente válida: "compré 500 unidades a
$1.824 cada una" es una operación coherente en sí misma, solo que no es
lo que pasó en la realidad.

### 6.3 Sobre la fórmula propuesta

La propuesta ("cantidad comprada × cantidad contenida, dividido por la
cantidad comprada") es, simplificada, `(cantidad × cantidad_contenida)
/ cantidad = cantidad_contenida` — matemáticamente no cambia nada
respecto de usar `cantidad_contenida` directo, así que no resolvería el
problema por sí sola.

### 6.4 Qué sí ayudaría (para decidir, no implementado todavía)

1. **Texto de ayuda en el campo** ("Cant. Cont." hoy no tiene ningún
   tooltip ni ejemplo, ver `app/Views/compras/form.php:147`) explicando
   la convención con un ejemplo del ítem que se está cargando.
2. **Aviso (no bloqueo) si `cantidad_contenida` difiere mucho de lo
   usado antes para ese mismo ítem** — es exactamente lo que hubiera
   frenado la factura #52 y la #276 antes de guardarlas, con los datos
   que ya existen (comparar contra el historial de
   `facturas_compra_detalle` de ese `materia_prima_id`).
3. **Preview en vivo** del costo por unidad base que va a quedar antes
   de guardar la línea (ya existe un patrón similar en otras partes del
   sistema para preview de margen) — deja ver de un vistazo si el
   número "no tiene sentido" antes de guardar, en vez de después.

La opción 2 es la que más directamente ataca la causa encontrada en
§5.2, porque los datos históricos para compararla ya están en la base.
