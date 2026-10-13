# ¿Los accesos directos del Chat IA deberían saltear a Gemini?

Documento de análisis sobre si conviene que los "accesos directos" (los chips debajo del
chat, ej. "📊 Ventas esta semana") se resuelvan con una consulta SQL directa en vez de pasar
por Gemini, dejando el LLM reservado para preguntas libres.

**Estado: implementado** para los 7 accesos de lectura de fábrica — ver
`ChatController::accionDirecta()`, campo `accion_interna` en `chat_accesos_directos`
(`database/chat_accesos_accion_interna_migration.sql`) y `enviarAccionDirecta()` en
`app/Views/chat/index.php`. La sección "Cómo se vería" queda como referencia de diseño; el
código real puede tener pequeños ajustes respecto a lo descripto acá.

## Cómo funciona hoy (verificado en código)

Los accesos directos (`chat_accesos_directos`, admin en `/chat/accesos`) son botones que
el admin arma con dos campos de texto libre: un `label` (lo que se ve en el botón) y un
`texto` (el prompt que se manda). Al hacer click, el JS de `chat/index.php` llama
literalmente a la misma función `enviar()` que se usa al tipear a mano:

```js
btn.addEventListener('click', () => enviar(sustituirFechasAcceso(btn.dataset.texto || btn.textContent)));
```

Es decir: **hoy el 100% de los accesos directos pasan por el loop completo de
function-calling de Gemini** — mismo costo, misma latencia, mismo riesgo de falla que
tipear la pregunta a mano. El catálogo por defecto tiene 10 accesos:

| Tipo | Accesos |
|---|---|
| Consulta de solo lectura, parámetros fijos | Ventas esta semana, Ganancia bruta mensual, Top 5 productos, Stock bajo mínimo, Compras del mes, Movimiento de caja hoy, Clientes nuevos |
| Acción/conversación abierta | Nuevo cliente, Nuevo proveedor, Registrar compra |

Esta distinción (7 lecturas fijas vs. 3 acciones abiertas) es el dato central de todo este
análisis — ver más abajo.

## ¿Tiene sentido? Sí, pero no como "todo o nada"

La frontera correcta no es "accesos directos vs. chat libre" en general — es **"consulta de
solo lectura con parámetros ya fijos" vs. "todo lo demás"**. Los 7 accesos de lectura son
candidatos genuinos a bypass; los 3 de acción y cualquier pregunta tipeada libremente no.

### A favor del bypass (para las 7 consultas de lectura)

- **Cuota compartida protegida**: Gemini free tier tiene cuota RPM/RPD **por modelo**, y esa
  cuota la comparten los accesos directos con TODO el uso real del chat (leer fotos de
  facturas, preguntas libres, reportes personalizados) — ver
  `docs/chat-ia-arquitectura-reutilizable.md`, sección 6. Cada click en "Stock bajo mínimo"
  gasta cuota que podría hacer falta para algo que sí necesita IA de verdad.
- **Costo real, aunque parezca chico por llamada**: a los precios cargados en
  `GeminiUsageModel::getConfig()` (\$0.10/millón tokens input, \$0.40/millón output), el
  costo por click individual es bajo, pero el system prompt + las ~20 declaraciones de
  herramientas pesan "decenas de KB" y se mandan enteras en cada llamada del loop (según la
  arquitectura documentada) — multiplicado por 7 chips × cada usuario que los clickea
  seguido, no es gratis. Una consulta SQL directa cuesta $0.
- **Latencia**: los logs que trajo el usuario muestran ~2.6 segundos por llamada a Gemini,
  y el loop de function-calling puede encadenar 2-3 vueltas para una sola pregunta. Un
  `SELECT` directo tarda milisegundos. Para "¿cuánto vendimos esta semana?" —una pregunta
  100% determinística— esa espera es latencia de red a Google sin agregar ningún valor.
- **Elimina una categoría entera de fallo**: el bug de "(sin texto ni function call)" que
  se vio en los logs (Gemini corta con `finishReason=STOP` sin texto ni llamada a función,
  ver `ChatController.php:294-327`) es estructuralmente imposible en un camino que no pasa
  por Gemini. Para preguntas donde la respuesta correcta ya es 100% conocida de antemano,
  no tiene sentido seguir expuestos a esa clase de falla.
- **La base ya existe, no hay que inventar SQL nuevo**: `ReportesModel` ya tiene queries
  probadas y en producción para prácticamente todo el catálogo — `resumenVentas()` /
  `ventasPorDia()` (ventas semana), `topProductos()` (top 5), `resumenCompras()` (compras
  del mes), `resumenCaja()` (caja hoy) — el trabajo es enrutar, no programar de cero.

### En contra / riesgos a resolver

- **No aplica parejo a los 10**: "Creá un cliente nuevo" o "Registrá una factura de compra"
  no tienen datos fijos — dependen de una conversación de varios turnos (nombre, CUIT,
  ítems de la factura...). Esos 3 accesos de acción **tienen** que seguir yendo por el LLM
  (o, alternativa más simple todavía, apuntar directo a la pantalla del formulario
  tradicional — `/clientes?nuevo`, `/proveedores?nuevo` — sin pasar por el chat en
  absoluto). No hay forma de "SQL-ificar" una conversación abierta.
- **El catálogo es dinámico y hoy no requiere código**: cualquier admin arma un acceso
  nuevo desde `/chat/accesos` con dos campos de texto libre, sin tocar una línea de código.
  Si el bypass se basa en reconocer el `texto` del acceso y mapearlo a una función PHP fija,
  un acceso custom que el admin invente ("¿cuánto le debemos a tal proveedor?") no tendría
  mapeo y quedaría roto — a menos que el bypass sea **opt-in por acceso** (ver
  implementación), no automático por texto.
- **Se pierde "gratis" el formateo de la respuesta**: parte de lo que hoy aporta Gemini
  incluso en una consulta trivial es armar la respuesta en lenguaje natural con formato
  (bullets, resumen, emojis). Un bypass a SQL puro necesita una plantilla de texto armada a
  mano en PHP por cada tipo de consulta — trabajo real, aunque chico y de una sola vez.
- **Mantenimiento duplicado**: si se bypassea, "ventas de esta semana" queda resuelto por
  dos caminos (el directo, y el que sigue existiendo para cuando alguien lo escribe distinto
  en el chat libre) — hay que mantenerlos consistentes (mismo criterio `eliminado=0`, mismo
  huso horario, etc.) o van a divergir con el tiempo si se toca la lógica de negocio en uno
  y no en el otro. Mitigación: que el bypass llame a los mismos métodos de `ReportesModel`
  que ya usa el chat/los reportes, no una query nueva paralela.

## Es viable — cómo se vería (propuesta, no implementada)

1. Agregar un campo `accion_interna` (nullable) a `chat_accesos_directos` — valores fijos
   tipo `ventas_semana`, `stock_bajo_minimo`, etc. Los accesos de fábrica lo traen cargado;
   un acceso custom que arme un admin lo deja en `NULL`.
2. El JS del chip, si el acceso tiene `accion_interna`, pega a un endpoint nuevo (ej.
   `chat/accion-directa`) en vez de `chat/mensaje` — ese endpoint corre el método PHP
   correspondiente (reutilizando `ReportesModel`, no una query nueva) y arma la respuesta
   con una plantilla simple. **Cero llamadas a Gemini.**
3. Si el acceso NO tiene `accion_interna` (custom del admin, o los 3 de acción), sigue el
   camino actual sin ningún cambio — se degrada a exactamente el comportamiento de hoy.
4. La respuesta directa igual se guarda en `chat_mensajes` (para que quede en el historial
   de la conversación como cualquier otra), solo que sin pasar por `llamarGemini()`.

## Fuera de alcance (esto NO se está proponiendo)

- Tocar el chat libre: preguntas tipeadas a mano por el usuario siguen 100% por Gemini —
  ahí es exactamente donde el LLM aporta valor (interpretar lenguaje natural ambiguo).
- Los tools de creación/acción (`crear_cliente`, `registrar_compra`, etc.) no cambian.
- Adivinar con reglas/regex si un mensaje tipeado a mano "se parece" a un acceso directo
  para bypassearlo también — la variación posible del lenguaje natural es demasiado amplia
  y el riesgo de falsos positivos/negativos no vale la pena frente a la simplicidad de "el
  bypass es solo para el click exacto del botón".

## Veredicto

Sí tiene sentido y es viable, acotado a los accesos de solo lectura con parámetros fijos
(7 de los 10 de fábrica). No es una migración de "todo el chat deja de usar IA" — es sacarle
del camino a Gemini exactamente el subconjunto de preguntas donde hoy no está aportando
nada (la respuesta ya es 100% determinística antes de preguntarle), liberando cuota,
latencia y superficie de falla para donde el LLM sí es indispensable: leer un comprobante,
resolver una pregunta ambigua, o sostener una conversación de varios turnos para dar de
alta algo.
