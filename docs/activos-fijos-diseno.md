# Activos fijos: registro + amortización — documento para conversar

> Documento de diseño para discutir, no un plan cerrado. Queda explícitamente afuera la
> parte de "cuándo se recupera la ganancia invertida" (no se puede automatizar de forma
> confiable — no hay en el sistema ningún dato que conecte "compré una cafetera" con
> "aumentaron las ventas de café").

## 1. Qué ya existe (contexto)

Investigué el módulo de Gastos y Resumen Económico antes de proponer nada:

- Hoy **cualquier compra se descuenta 100% en el mes en que se carga** del Estado de
  Resultados (`GastoModel::estadoResultados()`). No hay depreciación ni capitalización de
  ningún tipo.
- Ya existe, sin usar, una categoría de gasto llamada **"Amortizaciones"** (id=8,
  `gastos_setup.sql`) — un nombre cargado a propósito en su momento, pero sin ningún
  cálculo detrás. La vamos a activar de verdad con esto.
- El sistema ya tiene un patrón resuelto para "generar un gasto solo, mes a mes, sin
  intervención manual": `gastos_recurrentes` (usado hoy para alquiler, sueldos fijos,
  etc.). Vamos a reusar exactamente ese mecanismo para la amortización, en vez de inventar
  uno nuevo.

## 2. Modelo de datos propuesto

Una tabla nueva, `activos_fijos`:

```
activos_fijos
  id
  nombre                  → "Horno industrial", "Cafetera Faema", "Mobiliario salón"
  categoria_id             → reusa gasto_categorias (agregarle un nuevo tipo 'activo_fijo'
                              al ENUM, o una categoría "Bienes de uso" bajo ese tipo)
  origen                  ENUM('compra_nueva', 'preexistente')
  fecha_alta               → fecha de compra real (origen=compra_nueva) o fecha en que se
                              da de alta en el sistema (origen=preexistente, puede no
                              coincidir con cuándo se compró de verdad)
  valor                    → ver sección 4, es el punto más importante a definir
  vida_util_meses          → nullable: si no se carga, el activo no amortiza (queda
                              valuado pero no genera gasto mensual)
  vida_util_restante_meses → solo para preexistentes: cuántos meses de vida útil quedan
                              (puede ser 0 = ya totalmente amortizado, no genera gasto)
  gasto_recurrente_id       → FK a gastos_recurrentes, si se activó la amortización
  proveedor_id              → opcional, reusa proveedores existente
  estado                   ENUM('activo', 'baja', 'vendido')
  notas
  usuario_id, created_at, updated_at, eliminado
```

## 3. Dos formas de alta (como pediste)

**Compra nueva**: se carga con fecha de compra real, valor = costo de compra, vida útil en
meses. Si se le pone vida útil, el sistema arma automáticamente una `gastos_recurrentes`
por `valor ÷ vida_util_meses` cada mes, categorizada en "Amortizaciones", durante esa
cantidad de meses — así el costo se reparte en el Estado de Resultados en vez de golpear
todo de una vez el mes de la compra.

**Ya lo tenía** (activo preexistente, nunca antes registrado en el sistema): se carga con
la fecha en que se da de alta (o una fecha de compra aproximada si la sabés), el valor que
le asignes vos (ver sección 4), y opcionalmente cuánta vida útil le queda. Si le ponés vida
útil restante > 0, arranca a amortizar desde ahora hacia adelante (no se puede reconstruir
retroactivamente lo que no se cargó en su momento). Si no le ponés vida útil (o la dejás en
0), queda solo como un registro de valuación, sin generar ningún gasto — que es
probablemente el caso más común para "ya lo tenía".

## 4. El punto a definir: qué significa "valor"

Acá hay una decisión real que cambia el número final del total, y quiero que la
resolvamos antes de construir nada:

- **Costo histórico** (lo que costó, sin descontar nada): simple, siempre disponible, pero
  con el tiempo sobreestima cuánto vale realmente el negocio en activos (un horno
  comprado hace 5 años no vale hoy lo mismo que el día que se compró).
- **Valor contable neto** (costo histórico menos lo ya amortizado hasta la fecha): más
  preciso con el paso del tiempo, pero depende de que la amortización esté bien cargada
  (vida útil real) para no dar un número artificial.

Mi recomendación: guardar el `valor` como **costo histórico** (el dato fijo, no cambia
nunca) y que la pantalla de listado calcule al vuelo el **valor neto actual** = valor −
(cuotas de amortización ya generadas hasta hoy, si tiene `gasto_recurrente_id`) — mostrando
las dos columnas ("Valor de compra" y "Valor actual estimado") en vez de una sola, así no
se pierde ningún dato y cada quien mira el número que le sirve. El total del negocio en
activos lo armaría con la columna de valor actual (más realista), con la opción de ver
también el total histórico al lado.

## 5. Pantalla nueva

Sidebar: nueva sección **"Activos"** (ícono tipo caja/edificio), fuera de "Stock" y
"Productos" — es un concepto distinto (bienes de uso, no mercadería para vender). Listado
con: nombre, categoría, origen (compra nueva/preexistente), fecha de alta, valor de compra,
valor actual estimado, vida útil restante, estado. Filtro por estado/categoría. Un total al
pie (o en un KPI arriba) con el valor actual estimado sumado de todos los activos "activo"
(no de baja/vendidos).

Formulario de alta: un toggle "¿Es una compra nueva o ya lo tenías?" que muestra/oculta los
campos correspondientes (fecha de compra real vs. fecha de alta + vida útil restante),
mismo patrón de UI condicional que ya usamos en Promos y en la sección de tareas.

## 6. Qué NO incluye esto (a propósito)

- Nada de "cuándo se recupera la inversión" — ya lo descartamos.
- No vincula automáticamente con una factura de compra existente — es un registro
  paralelo. Si el activo ya se cargó como una factura de compra normal (ej. "gasto" en una
  compra a proveedor), habría una duplicación de costo en Resultados el mes de la compra
  además de la amortización mensual nueva — es una decisión operativa (cargar la compra del
  activo DIRECTAMENTE acá, no como una factura de compra aparte) más que algo que el
  sistema pueda prevenir solo.

## 7. Próximo paso

Si estás de acuerdo con la sección 4 (costo histórico + valor actual calculado, mostrando
las dos columnas), armo el plan de implementación concreto: migración, modelo, controlador,
vista, y el enganche con `gastos_recurrentes` para la amortización.
