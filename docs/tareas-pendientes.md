# Tareas pendientes — documentos de diseño

Documentos de diseño para funcionalidades todavía no implementadas. Sirven de base para planificar el desarrollo en próximas sesiones — nada de lo que describe este archivo existe en el código todavía.

1. [POS — Opciones/adicionales configurables por producto](#1-pos--opcionesadicionales-configurables-por-producto)
2. [Sistema de tareas asignadas a trabajadores](#2-sistema-de-tareas-asignadas-a-trabajadores)
3. [Aviso de reportes generados por cron](#3-aviso-de-reportes-generados-por-cron)

---

## 1. POS — Opciones/adicionales configurables por producto

### Problema

Hoy un producto en `ventas/pos` es una unidad atómica: se clickea el tile y se agrega tal cual al carrito. No hay forma de que, al vender "Helado 1kg", el cajero especifique qué gustos lleva, ni de que una promo "Café + Torta" deje elegir qué torta. Esa información termina resuelta verbalmente entre el mostrador y la cocina, y no queda registrada en ningún lado (ni en el carrito, ni en la comanda impresa, ni en la venta guardada).

### Concepto

Un producto puede tener asociados uno o más **grupos de opciones** (ej. "Gustos de helado", "Torta de la promo"). Si el producto tocado tiene algún grupo configurado, en vez de sumarse directo al carrito se abre un modal donde el cajero elige — recién ahí se agrega la línea, ya con la selección adjunta. Esa selección viaja con la línea del carrito, se guarda con la venta, y se imprime como sub-detalle en la comanda.

Dos ejemplos guía (los que dio el usuario, se usan como casos de referencia en todo el documento):

- **Helado 1kg** → grupo "Gustos de helado", selección múltiple, hasta 3 opciones, permite repetir (ej. 2x Dulce de leche + 1x Vainilla).
- **Promo Café + Torta** → grupo "Torta de la promo", selección única, 1 opción obligatoria (Red Velvet / Carrot Cake / Lemon Pie / etc.).

### Decisiones ya tomadas

| Pregunta | Decisión |
|---|---|
| ¿Los grupos son reutilizables entre productos o exclusivos de uno? | **Reutilizables.** "Gustos de helado" se define una vez y se linkea a 1kg, 1/2kg, cucurucho, etc. |
| ¿Las opciones elegidas afectan el precio de la venta? | **No, por ahora.** Elegir un gusto o una torta no cambia el total — el precio ya lo define el producto. Queda para una fase futura si hace falta (ej. "agregar granizado +$500"). |
| ¿Elegir una opción descuenta stock de un insumo/materia prima puntual? | **Sí, desde el arranque.** Elegir "Red Velvet" tiene que descontar stock de esa torta específica, no de un genérico "torta". |

### Modelo de datos (conceptual)

No es código todavía, son las entidades que va a hacer falta crear — sirven para dimensionar el trabajo, no para copiar y pegar.

#### Grupo de opciones
La lista reutilizable de opciones (ej. "Gustos de helado", con sus 12 sabores).

| Campo | Para qué |
|---|---|
| nombre | "Gustos de helado", "Torta de la promo" |
| tipo_seleccion | única (radio) o múltiple (checkboxes) |
| permite_repetir | si en selección múltiple se puede elegir la misma opción más de una vez (2x Dulce de leche) |
| activo | para dar de baja un grupo sin borrar el historial de ventas que ya lo usaron |

#### Opción
Cada choice dentro de un grupo (ej. "Dulce de leche" dentro de "Gustos de helado").

| Campo | Para qué |
|---|---|
| grupo_id | a qué grupo pertenece |
| nombre | "Dulce de leche", "Red Velvet" |
| materia_prima_id / insumo_id (uno de los dos, nullable) | de qué se descuenta stock al elegir esta opción. Nullable a propósito: algunas opciones pueden ser puramente informativas (ej. "sin azúcar") sin ningún consumo asociado |
| cantidad_consumida | cuánto se descuenta de esa materia prima/insumo por cada vez que se elige (ej. 0.1kg por cucharada de gusto, 1 unidad por porción de torta) |
| orden | orden de aparición en el modal |
| activo | dar de baja sin romper ventas históricas |

#### Producto ↔ Grupo de opciones (tabla puente)
Acá vive el **límite de selecciones**, no en el grupo — porque el mismo grupo se comparte entre productos con límites distintos.

| Campo | Para qué |
|---|---|
| producto_id | qué producto |
| grupo_id | qué grupo de opciones usa |
| minimo_selecciones / maximo_selecciones | ej. 1kg → min 3, max 3. 1/2kg → min 2, max 2. Cucurucho → min 1, max 1 |
| obligatorio | si no se puede cobrar el producto sin completar la selección |
| orden | si un producto tiene más de un grupo (poco común, pero posible), en qué orden se muestran los modales/secciones |

#### Venta detalle ↔ Opciones elegidas
Lo que realmente se vendió, para historial, comanda y reportes.

| Campo | Para qué |
|---|---|
| venta_detalle_id | a qué línea de la venta pertenece |
| opcion_id | qué opción se eligió |
| cantidad_elegida | cuántas veces (para el caso de repetición: 2x Dulce de leche = cantidad_elegida 2) |

### Impacto en stock (el punto más delicado)

Esto es lo que más se acopla al sistema de Recetas/Stock que ya existe, así que merece su propia sección.

**Regla propuesta**: al registrar la venta, además de descontar la receta base del producto (como ya pasa hoy), se descuenta *adicionalmente* `cantidad_consumida × cantidad_elegida × cantidad_vendida` de cada opción elegida.

**Condición para que esto no duplique descuentos**: la receta base del producto **no debe incluir** el ingrediente que va a quedar cubierto por la opción. Es decir, al configurar "Helado 1kg", su receta base lleva el envase, la cuchara, etc., pero **no** un gusto fijo — el gusto se resuelve 100% a través de la opción elegida en el momento de la venta. Mismo criterio para la promo: la receta base del combo lleva el café, pero no la torta — la torta la aporta la opción.

Esto es una responsabilidad de **configuración**, no algo que el sistema pueda validar solo (no hay forma automática de saber si un admin dejó sin querer el ingrediente duplicado en la receta base). Va a hacer falta dejarlo bien documentado en la pantalla de configuración para que no se preste a confusión.

**Con qué se puede vincular una opción**: `materia_prima_id` o `insumo_id`. Para el caso de la torta, lo más prolijo es que "Red Velvet" no sea un texto suelto sino que apunte a una materia prima/insumo que ya exista en el catálogo (si hoy no existe una entrada para cada sabor de torta, hay que crearlas ahí primero) — así se reutiliza el mismo ledger de stock que ya tiene todo el sistema, en vez de inventar un mecanismo de descuento paralelo.

**Sin stock de una opción puntual**: debería integrarse con el flujo que ya existe de "confirmar venta sin stock" (el mismo que se armó para productos agotados en general) en vez de bloquear directo — a definir en la implementación.

### Flujo de cajero (POS)

1. Click en un producto con grupo(s) de opciones configurado(s) → se abre un modal en vez de sumar directo al carrito.
2. El modal muestra el/los grupo(s) según su tipo:
   - Selección única → radios.
   - Selección múltiple → checkboxes o contador (para permitir repetición, tipo "+/-" por opción hasta llegar al máximo).
3. El botón de confirmar queda deshabilitado hasta cumplir el mínimo de cada grupo obligatorio (mismo patrón de validación que ya se usa en otros modales del sistema).
4. Al confirmar, se agrega la línea al carrito con las opciones adjuntas.
5. La fila del carrito muestra el producto + un resumen de la selección debajo (ej. "1kg Helado — 2x Dulce de leche, 1x Vainilla").
6. Tiene que poder reabrirse (mismo botón "Editar" que ya existe para cantidad/precio/descuento) para cambiar la selección sin tener que sacar el ítem y volver a agregarlo.

### Comanda

Cada línea con opciones se imprime con sub-líneas indentadas debajo del producto, para que la cocina prepare exactamente lo que se pidió — este es el requisito que disparó todo el documento, así que es el criterio de éxito principal: *si la comanda no deja 100% claro qué preparar sin tener que preguntar, la funcionalidad no cumplió su objetivo*.

### Administración

Hace falta una pantalla nueva para gestionar grupos y opciones (probablemente colgando de Productos, o como sección propia dentro de Maestros), más la asignación de qué producto usa qué grupo con qué límites. Esto implica un nuevo módulo de permisos (mismo patrón que ya usa `PermisosModel::MODULOS` para el resto del sistema).

### Fuera de alcance (por ahora)

- **Precio adicional por opción** — quedó descartado para esta primera versión por decisión explícita; el diseño de datos ya lo contempla como campo opcional a futuro (`precio_adicional` en Opción) para no tener que rehacer el modelo si se pide más adelante.
- **Reportes específicos de opciones más vendidas** (ej. "¿qué gusto de helado se vende más?") — no se pidió, pero al quedar todo registrado en `ventas_detalle_opciones` queda disponible para un reporte futuro sin cambios de modelo.

### Preguntas abiertas

1. ¿Qué pasa si falta stock de una opción puntual al momento de vender — se integra con el flujo de "confirmar sin stock" existente, o se bloquea distinto?
2. ¿Un producto puede tener más de un grupo de opciones a la vez (ej. una promo con "elegí la torta" + "elegí la bebida")? El modelo ya lo permite (la tabla puente es N:M), pero hay que confirmar si el modal necesita mostrar varios grupos en un solo paso o en pasos separados.
3. ¿Las opciones y grupos se pueden dar de baja (desactivar) sin romper el historial de ventas que ya los usó? El campo `activo` en el diseño apunta a esto, pero hay que definir el comportamiento exacto (¿se sigue mostrando en ventas viejas pero no aparece más como elegible?).
4. ¿Quién configura esto — solo admin, o también encargado (según el patrón de permisos por módulo que ya usa el resto del sistema)?

---

## 2. Sistema de tareas asignadas a trabajadores

### Problema

Hoy no hay forma de que un encargado le mande una tarea puntual a un trabajador durante el día (ej. "reponer servilletas", "llamar al proveedor X") y quede registrado si la aceptó, la rechazó, la pospuso o la terminó — ni a qué hora ni quién la resolvió. El módulo `solicitudes` que ya existe está pensado para otra cosa (pedir la compra de un producto) y no le entra este flujo: no tiene estado "rechazada", no tiene aplazamiento, y no distingue "avisale a Juan puntualmente" de "avisale a quien tenga la caja abierta hoy".

### Concepto

Un usuario con permiso para asignar tareas crea una **solicitud de tarea**, dirigida a un usuario específico o a "quien tenga el turno de caja abierto ese día". El destinatario la ve como un cartel llamativo arriba de `ventas/pos` (más una notificación push, para el caso de que no esté mirando esa pantalla en ese momento) y tiene que resolverla: **aceptar**, **rechazar** (con motivo obligatorio) o **aplazar** por un tiempo dentro del mismo día. Si la acepta, más tarde la marca como **finalizada** — y ahí se graba qué usuario la finalizó y a qué hora, sin importar si la tarea se había asignado a esa persona puntual o "a quien estuviera en caja".

Ejemplo guía: el encargado le manda "Controlar la heladera del depósito" al trabajador del día, sin saber todavía quién va a abrir la caja esa mañana.

### Decisiones ya tomadas

| Pregunta | Decisión |
|---|---|
| ¿A quién se puede asignar una tarea? | **Las dos formas.** A un usuario específico, o a "quien tenga la caja abierta ese día" (turno activo). |
| ¿Se registra quién la finalizó? | **Sí, siempre**, sea cual sea el modo de asignación — es el dato que más le importa al que la creó. |
| ¿Hace falta push notification, además del cartel en pantalla? | **Sí**, reutilizando la misma infraestructura de WebPush que ya se usa para el aviso de anulación de ventas. |
| Modo `turno_abierto` con varias cajas abiertas a la vez | La ven **todos** los que tienen turno activo en ese momento; en modo `usuario_especifico` la ve únicamente esa persona. |
| Momento del push en modo `turno_abierto` | Se dispara **cuando se abre el turno** (`CajaController::abrir()`), no al crear la tarea — recién ahí se sabe a quién avisar. |
| ¿Se puede aplazar una tarea ya aceptada? | **Sí.** "La acepto, pero la hago en un rato" es un caso válido — aplazar está disponible tanto desde `pendiente` como desde `aceptada`. |
| Opciones de aplazamiento | Tiempos fijos: **15 / 30 / 60 minutos.** No hay un máximo configurable por quien asigna. |
| Tarea sin resolver al cerrar el turno | **Las dos cosas a la vez**: escala al encargado (se le notifica que quedó sin resolver) y la tarea sigue abierta para quien abra turno al día siguiente — no se auto-cierra ni se pierde. |

### Relación con el módulo "Solicitudes" existente

Se recomienda **no** reutilizar la tabla `solicitudes` actual y construir una nueva, dedicada — mismo criterio que ya se aplicó dos veces en este proyecto (el chat de compras y la anulación de ventas construyeron sus propias tablas en vez de forzar un esquema que no encajaba). Acá pasa lo mismo: `solicitudes` no tiene estado "rechazada", no tiene aplazamiento, no tiene el concepto de "usuario específico vs. turno abierto", y no registra quién finalizó. Forzarlo generaría más columnas nullable-solo-para-este-caso que complejidad evitada.

### Modelo de datos (conceptual)

Todo en una sola tabla — a diferencia del caso de anulación de ventas (que necesitaba varias solicitudes históricas por venta), acá cada tarea se crea una vez y recorre su ciclo de vida una sola vez. Si el encargado quiere mandar otra tarea, es una fila nueva.

| Campo | Para qué |
|---|---|
| usuario_crea_id | quién asignó la tarea (el encargado) |
| titulo | texto corto (ej. "Reponer servilletas en mesa 3") |
| descripcion | detalle opcional, más largo |
| modo_destino | `usuario_especifico` \| `turno_abierto` |
| usuario_destino_id | nullable — solo tiene valor si modo_destino es `usuario_especifico` |
| estado | `pendiente` \| `aceptada` \| `rechazada` \| `finalizada` — **sin** "aplazada": aplazar es un atributo aparte (siguiente fila), no un estado propio, porque se puede aplazar tanto desde `pendiente` como desde `aceptada` y hay que poder volver al que corresponda |
| usuario_responde_id / respondido_at | quién y cuándo aceptó o rechazó |
| motivo_rechazo | obligatorio si estado = `rechazada` |
| aplazado_hasta | datetime, nullable. Mientras esté en el futuro, el cartel no se muestra aunque el estado siga siendo `pendiente` o `aceptada`. Se pisa cada vez que se vuelve a aplazar (no se guarda historial de cada aplazamiento, ver "Fuera de alcance") |
| usuario_finaliza_id / finalizado_at | quién y cuándo la marcó como terminada — el dato explícitamente pedido |
| creado_at | |

### Ciclo de vida

```
pendiente ──aceptar────────────► aceptada ──finalizar──► finalizada
    │                                │
    └──rechazar (+motivo)──► rechazada  [fin]

Aplazar (15/30/60 min) es ortogonal a lo anterior: se puede pedir desde "pendiente"
o desde "aceptada", pone aplazado_hasta en el futuro (el cartel se oculta) y el
estado de base (pendiente o aceptada) no cambia — al cumplirse el horario, el
cartel simplemente vuelve a aparecer con el estado que ya tenía.
```

### Flujo en ventas/pos (el cartel)

1. Al entrar a `ventas/pos` (y cada tanto mientras la pantalla sigue abierta, vía polling — el POS suele quedar abierto todo el turno), se consulta si el usuario logueado tiene una tarea sin `aplazado_hasta` vigente en alguno de estos casos: `pendiente`/`aceptada` dirigida a él puntualmente, o `pendiente`/`aceptada` en modo `turno_abierto` mientras él tiene un turno de caja activo (en ese modo la ven **todos** los que tengan turno abierto en simultáneo — el primero que actúa la resuelve para todos).
2. Si hay una en estado `pendiente`, aparece un cartel destacado (más prominente que el aviso actual de "No hay turno de caja abierto", porque este sí requiere una acción) con: título, quién la asignó, y tres botones — **Aceptar** / **Rechazar** / **Aplazar**.
3. **Rechazar** abre un campo de texto obligatorio antes de poder confirmar.
4. **Aplazar** ofrece tres opciones fijas — 15 / 30 / 60 minutos — y el cartel desaparece hasta que se cumpla ese tiempo (disponible tanto en `pendiente` como en `aceptada`, ver "Ciclo de vida").
5. Una vez **aceptada**, el cartel pasa a un estado "en curso" con dos botones — **Finalizar** y **Aplazar** — al presionar Finalizar se guarda usuario + hora y el cartel desaparece para siempre.

### Push notification

Mismo patrón que `notificarAdminsAnulacion()` / el aviso del 90% de facturación AFIP: WebPush con `PushSubscriptionModel`. En modo `usuario_especifico` se dispara al crear la tarea, directo a las suscripciones de ese usuario. En modo `turno_abierto` se dispara recién **cuando se abre un turno** (`CajaController::abrir()`), a quien lo acaba de abrir — al momento de crear la tarea todavía no se sabe quién va a estar en caja, así que no hay a quién avisarle antes de eso.

### Escalamiento al cierre de turno

Al cerrar un turno (`CajaController::cerrar()`), se revisan las tareas sin resolver relacionadas con ese usuario (asignadas puntualmente a él, o en modo `turno_abierto` mientras tenía el turno abierto). Si queda alguna en `pendiente` o `aceptada` sin finalizar:

- se notifica (push) a `usuario_crea_id` (el encargado que la asignó) que quedó sin resolver al cierre del turno,
- y la tarea **no se cierra ni se pierde**: sigue disponible tal cual para quien abra el próximo turno (mismo día más tarde, u otro día).

### Administración / permisos

Nuevo módulo de permisos (ej. `tareas_asignar`) que gatea quién puede **crear** tareas — no hace falta permiso especial para simplemente ver/responder las tareas que a uno le asignan, eso es inherente a ser el destinatario. Mismo patrón ya usado en `PermisosModel::MODULOS` + permisos por rol/usuario.

### Fuera de alcance (por ahora)

- **Historial de cada aplazamiento** — solo se guarda el aplazamiento vigente (`aplazado_hasta` se pisa), no un registro de "se aplazó 3 veces con estos motivos". Se puede sumar después si hace falta auditar eso.
- **Tareas recurrentes/programadas** (ej. "todos los días a las 9am") — lo pedido es una tarea puntual creada a mano, no un scheduler.
- **Asignar una misma tarea a varios usuarios o roles a la vez** — el modelo es 1 tarea → 1 destino (persona o turno abierto).

### Preguntas abiertas

Ninguna — todas resueltas (ver tabla de "Decisiones ya tomadas" y las secciones de arriba, incluida la de escalamiento).

Sobre el escalamiento: además del push, las tareas sin resolver van a quedar visibles en un listado dentro de la sección **Solicitudes** que ya existe en el sidebar — como una pestaña/vista más ahí, no mezclada en la tabla `solicitudes` (que sigue siendo un módulo aparte, ver "Relación con el módulo Solicitudes existente"). Es una integración a nivel de menú/pantalla, no de esquema de datos.

---

## 3. Aviso de reportes generados por cron

### Problema

Hoy hay reportes que se generan automáticamente (por cron) y nadie se entera cuando están listos, salvo que le pregunten al sistema o se acuerden de ir a buscarlos.

### Estado actual (investigado antes de diseñar esto)

Existen **dos mecanismos separados**, cada uno resolviendo la mitad del problema:

- **`cron/reporte_semanal.php`** — el único script que hay en `cron/` hoy, corre los lunes. **Ya manda push notification** con el link al reporte, pero:
  - el destinatario está **hardcodeado a admins** (`PushSubscriptionModel::findSuscripcionesAdmins()`), no a quien tenga el permiso `reportes_semanal` (que ya existe como módulo, pero no se usa acá),
  - y el reporte en sí **no se guarda en ningún lado** — se recalcula en vivo cada vez que se abre el link. Si después se anula una venta vieja de esa semana, el número cambia al volver a mirarlo — no es una foto fija de lo que pasó.
- **"Reportes IA"** (los que arma el Chat IA cuando se le pide un reporte) — es al revés: **sí quedan guardados** (tabla `reportes_ia`, con pantalla propia para verlos después en `/reportes-ia`), pero **no avisan a nadie** cuando se crean.

Este documento apunta puntualmente a los reportes por cron (lo que se pidió), pero el diseño de abajo reutiliza y corrige ambos mecanismos en vez de armar un tercero desde cero.

### Decisiones ya tomadas

| Pregunta | Decisión |
|---|---|
| ¿El reporte se guarda como foto fija o se sigue recalculando en vivo? | **Foto fija.** Se persiste el resultado al momento de generarse — el link del aviso lleva a esa foto guardada, no a un cálculo en vivo que puede correrse con cambios posteriores. |
| ¿El aviso es solo push? | **No.** Son **tres canales a la vez**: push notification, una bandeja de notificaciones visible dentro del sistema (la campanita del header, que hoy es un cascarón vacío con "Sin notificaciones pendientes" fijo), y un mensaje con el link insertado en el Chat IA de cada destinatario. |
| ¿La bandeja se marca como leída sola al abrir el dropdown, o aviso por aviso? | **Aviso por aviso.** Cada notificación se marca individualmente, no hay un "marcar todo como leído" automático al abrir la campanita. |
| ¿El mensaje en el Chat IA va mezclado con la conversación normal o aparte? | **Mezclado**, pero con una etiqueta clara tipo "Aviso del sistema" para que se distinga aunque esté entre las preguntas del día a día. |
| ¿Cada tipo de reporte cron futuro necesita su propio permiso? | **No necesariamente** — se puede compartir un mismo permiso entre varios tipos de reporte. |

### Modelo de datos (conceptual)

#### Snapshot del reporte
En vez de crear una tabla nueva paralela, tiene sentido **reutilizar `reportes_ia`** — a diferencia de los otros dos temas de este documento (donde se decidió explícitamente NO reutilizar `solicitudes` porque el esquema no encajaba), acá sí encaja: `reportes_ia` ya tiene título, descripción, resultado en JSON, columnas, parámetros y fecha — exactamente la forma de "un reporte generado y guardado", sea que lo haya pedido un usuario por chat o lo haya generado el cron solo. La diferencia real es quién/qué lo generó, y que en este caso hace falta avisar. Ajuste necesario a la tabla existente:

| Campo | Ajuste necesario |
|---|---|
| usuario_id | hoy asume que un usuario lo pidió por chat — para un reporte de cron no hay autor humano, así que tiene que poder quedar `NULL` (o un valor especial tipo "sistema") |
| origen (campo nuevo) | `chat` \| `cron` — para poder distinguir el origen sin inventar una tabla aparte |

#### Bandeja de notificaciones (nueva — esto es lo que le da contenido real a la campanita del header)
Genérica a propósito, para no atarla solo a reportes — el mismo mecanismo sirve a futuro para cualquier otro aviso in-app.

| Campo | Para qué |
|---|---|
| usuario_id | a quién |
| titulo / cuerpo | texto del aviso |
| url | link de acción (al reporte guardado, en este caso) |
| tipo | ej. `reporte_cron` — para poder filtrar o darle un ícono distinto a futuro |
| leido / leido_at | si ya se vio |
| creado_at | |

### Los tres canales, disparados juntos

Cuando el cron genera y guarda el reporte, para cada usuario con el permiso correspondiente (`reportes_semanal` en este caso, vía el mismo `findSuscripcionesConPermiso()`/equivalente por usuario que ya existe):

1. Se inserta una fila en la bandeja de notificaciones de ese usuario.
2. Se inserta un mensaje en su historial de Chat IA (`ChatModel::guardarMensaje($usuarioId, 'assistant', ...)`) con un resumen corto + el link.
3. Si tiene alguna suscripción push activa, se le manda la notificación — mismo patrón ya usado (`WebPush::enviar()`, limpieza de suscripciones muertas en 404/410).

**Detalle de implementación a anotar**: `findSuscripcionesConPermiso()` hoy devuelve *suscripciones push*, no *usuarios* — para la bandeja y el mensaje de chat hace falta la lista de usuarios con el permiso tengan o no push activado, así que probablemente haga falta un método nuevo (ej. `UsuarioModel::findConPermiso($modulo, $accion)`) en vez de derivarlo de las suscripciones.

### Flujo para el usuario

- **Header**: la campanita pasa a tener contador real de no leídos, y el dropdown lista los avisos con su link — deja de ser el cascarón fijo que es hoy.
- **Chat IA**: la próxima vez que el usuario entra a `/chat`, encuentra un mensaje del asistente con el resumen y el link al reporte, mezclado en la conversación normal pero encabezado con algo tipo "📋 Aviso del sistema" para que no se confunda con una respuesta a algo que haya preguntado.
- **Push**: igual que hoy, pero dirigido por permiso real en vez de hardcodeado a admin.
- Los tres links apuntan al reporte **ya guardado** (la pantalla de detalle de `reportes-ia` sirve tal cual, reutilizando el id del snapshot).

### Pensado para reutilizarse a futuro

Aunque hoy solo existe el cron semanal, el diseño no queda atado a él: el snapshot ya es genérico (`reportes_ia` + campo `origen`/`tipo`), y el envío por los tres canales conviene armarlo como una función reutilizable (ej. `notificarReporteGenerado($reporteId, $modulo)`) invocable desde cualquier script de cron que se agregue después, sin duplicar la lógica de armar el payload y recorrer destinatarios.

### Administración / permisos

No hace falta un permiso nuevo — se reutiliza el módulo de permiso que ya existe por tipo de reporte (`reportes_semanal`). Quien puede **ver** ese reporte es quien lo recibe por los tres canales.

### Fuera de alcance (por ahora)

- Gestión fina de la bandeja (archivar, borrar notificaciones viejas) — queda para cuando se implemente, no es parte de este alcance inicial.
- Notificaciones de otros orígenes además de reportes por cron — aunque la bandeja queda diseñada para poder reutilizarse ahí después.
- Reintentos automáticos si falla el envío de push — se mantiene el mismo comportamiento que ya existe (se descarta la suscripción muerta, no se reintenta).

### Preguntas abiertas

Ninguna — todas resueltas (ver tabla de "Decisiones ya tomadas").