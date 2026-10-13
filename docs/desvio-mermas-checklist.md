# Desvío y Merma — Checklist de desarrollo

> Objetivo: que el costo de cada producto/materia prima sea fiel a la realidad,
> separando dos conceptos distintos que hoy se mezclarían si no se los modela bien:
>
> - **Merma** (ya existía, `receta_detalle.merma_pct`): pérdida de PROCESO, conocida y
>   esperada al preparar una receta (evaporación, pelado). Sin cambios.
> - **Desvío** (nuevo): pérdida NO explicada por ninguna venta ni proceso — robo,
>   rotura, consumo interno no registrado, regalos. Aplica a CUALQUIER ítem con stock
>   propio (`productos` de reventa Y `materias_primas`), tenga o no receta.
>
> Ejemplo guía: café (con receta) 10gr + 2gr de merma = 12gr → $12 de costo, y el café
> en grano (MP) puede además tener su propio desvío, que se suma antes. Gaseosa
> (reventa, sin receta): $1 de costo + $0,20 de desvío = $1,20 — nunca tiene merma.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Fase 1 — Modelo de datos ✅
- [x] `productos.desvio_pct` y `materias_primas.desvio_pct` (`DECIMAL(5,2)`, default 0)
- [x] Tabla `desvio_historial` (polimórfica por `item_tipo`), con cantidad comprada,
  vendido explicado, teórico, contado, desvío en cantidad/%/$, usuario y fecha
- [x] `database/desvio_pct_migration.sql` + `database/schema.sql` actualizado
- [x] De paso se encontró que `inventarios`/`inventarios_detalle` (schema.sql) es una
  tabla que ya existía pero **no la usa ningún código** — se dejó intacta a propósito,
  no se tocó ni se reusó, para no arriesgar un refactor no pedido

## Fase 2 — Cálculo al hacer un conteo físico ✅
- [x] `app/Models/DesvioModel.php` (nuevo): `registrar()` calcula el ciclo completo
- [x] El punto de verdad es específicamente `StockController::guardarInventario()` —
  **no** `ajuste()` ni `importar()` (esos no representan necesariamente un recuento
  físico total confiable) ni `guardarApertura()` (es carga inicial de sistema nuevo,
  sin historial de compras digital contra qué comparar)
- [x] Consumo teórico de MP resuelto retroactivo vía `ventas_detalle → producto_receta
  (MIN(receta_id) como "principal") → receta_detalle`, sin tocar el flujo de venta del POS
- [x] `desvio_pct` del ítem se recalibra como promedio ponderado por lo comprado en
  cada ciclo (mismo criterio que el costo promedio ponderado ya usado en el sistema)

## Fase 3 — Costo: desvío integrado ✅
- [x] `RecetaModel`, `ProductoModel`, `StockModel`, `IngredienteGenericoModel`: las 20
  ocurrencias del cálculo normalizado por `cantidad_contenida` ahora también
  multiplican por `(1 + desvio_pct/100)` de la materia prima
- [x] `ReporteSemanalModel::margenBruto()`: el costo de mercadería vendida de
  productos de reventa ahora incluye `p.desvio_pct`

## Fase 4 — Formulario de Productos ✅
- [x] Campo `desvio_pct` en `productos/form.php` (junto a Stock mínimo), editable a
  mano y con preview en vivo del costo real ajustado
- [x] `ProductosController::recogerFormulario()` lo persiste

## Fase 5 — Modal de Materias Primas ✅
- [x] Campo `desvio_pct` en el modal `#modalItem` de `materias-primas/index.php`
- [x] El hint existente "costo real por unidad" ahora incluye el desvío
- [x] `MateriasPrimasController::recogerForm()` lo persiste

## Fase 6 — Historial de desvíos por ítem ✅
- [x] `DesvioModel::historialItem()` — todos los ciclos de un ítem, más reciente primero
- [x] Se resolvió como modal de detalle dentro del propio reporte de mermas y
  desvíos (Fase 7), en vez de una pantalla separada — evita duplicar una vista solo
  para esto, ya que el reporte es justamente donde tiene sentido verlo

## Fase 7 — Reporte de mermas y desvíos ✅
- [x] `app/Views/reportes/mermas-desvios.php` (nueva) — una fila por ítem con desvío
  registrado: merma % (si es ingrediente de receta), desvío % vigente, último ciclo y
  ciclo anterior (cantidad y $), flecha de tendencia (mejora/empeora/igual)
- [x] Modal "Ver historial completo" por ítem (AJAX a `reportes/historial-desvio-item`)
- [x] `ReportesController::mermasDesvios()` + `historialDesvioItem()` + rutas
- [x] Entrada en el sidebar, sección Reportes → "Mermas y desvíos"

## Fase 9 — Rediseño del cálculo (v2) ✅
> Reemplaza lo descrito en Fase 2 (cálculo contra el saldo del sistema, promedio ponderado por
> compras, consumo retroactivo por receta). Motivo: el % salía de errores de saldo, no de
> pérdidas reales, y dejaba costos negativos o ×11 (ver `docs/diagnostico-costos-mp.md` §5.1).
- [x] Cada conteo se compara contra el **conteo físico anterior** del mismo ítem, con los
  movimientos de `stock_movimientos` del período (ya en unidad base):
  `esperado = contado anterior + compras − consumo (ventas/producción)`,
  `pérdida = esperado − contado`, `desvío % = pérdida ÷ consumo × 100`
- [x] El **primer conteo** de un ítem es solo punto de partida (`estado='baseline'`)
- [x] Los **ajustes manuales entre conteos no se restan como consumo**: pueden ser rotura real
  o un olvido de contar, así que quedan dentro de la pérdida. Los ajustes de conteo se
  guardan con `origen='inventario'` (apertura: `'apertura'`)
- [x] Sin consumo registrado en el período → `estado='sin_consumo'`: no se puede medir y el
  desvío queda como estaba
- [x] La pérdida del **último período se aplica al costo en el momento** del conteo, sin filtros
  ni umbrales (decisión del usuario): desvío = pérdida ÷ consumo, piso 0 (un sobrante lo deja
  en 0), tope 999,99 (máximo de la columna); se pisa con el del conteo siguiente y, si cambia,
  se propaga a todas las recetas/productos que usan esa materia prima. Un conteo en otra
  unidad se traduce directo en costo hasta el próximo conteo
- [x] Solo materias primas y productos de **reventa** (la producción de elaborados suma su stock
  sin dejar movimiento). Siempre se registra el ciclo, aunque lo contado coincida con el sistema
- [x] `database/desvio_v2_migration.sql`: columnas `estado`/`stock_inicial`/`ultimo_mov_id`; las
  filas anteriores quedan `legacy` (no cuentan) y el desvío de los ítems con historial se resetea a 0
- Limitaciones conocidas: una venta entre el momento de contar y el de guardar aparece como
  pérdida; un pedido ecommerce sin stock deja una "salida fantasma"

## Fase 8 — Pruebas ⬜
- [ ] Registrar una compra de una MP con receta, vender menos de lo teórico, hacer un
  conteo en Stock → Inventario físico, y verificar que aparece un ciclo en el reporte
- [ ] Ídem con un producto de reventa sin receta — verificar que ahí nunca aparece
  ninguna "merma %", solo desvío
- [ ] Verificar que el costo de la receta/producto sube después del conteo (Fase 3)
- [ ] Verificar `php -l` de todos los archivos tocados (ya corrido y en verde)

> Falta correr por quien pruebe manualmente con datos reales — no se puede simular un
> ciclo completo de compra→venta→conteo desde acá sin una base de datos real.

---

## Notas de diseño que quedaron implícitas

- **Por qué no se fuerza receta para todo**: se evaluó y se descartó (charlado antes de
  empezar) — una receta implica transformación, una reventa es pass-through. Se optó
  por `desvio_pct` directo en `productos`/`materias_primas` en su lugar.
- **Merma y desvío nunca se mezclan en un solo número**: el desvío incluye TODA
  pérdida no explicada (robo + regalos + rotura, da igual la causa para el costo),
  mientras que la merma sigue siendo la pérdida de proceso ya conocida al diseñar la
  receta. Se calculan y muestran por separado, aunque ambas terminen sumando al costo.
- ~~**No hace falta que las ventas descuenten materia prima en tiempo real**~~ — **OBSOLETO
  (v2, Fase 9)**: hoy `VentaModel`/`RecetaModel` sí descuentan y dejan un movimiento en
  `stock_movimientos` al vender, y el desvío se calcula a partir de esos movimientos.
- ~~**Un producto con más de una receta asignada**: se usa la de menor `receta_id`~~ —
  **OBSOLETO (v2)**: ya no se deduce el consumo por receta; se suman los movimientos reales.
- **`inventarios`/`inventarios_detalle` no se tocó**: es una tabla del schema que nunca
  llegó a usarse en el código (verificado con grep antes de empezar). Se dejó como
  estaba a propósito para no mezclar un refactor no pedido con esta funcionalidad.
