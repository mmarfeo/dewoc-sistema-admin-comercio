# Propuesta: Dewoc como SaaS multiempresa "chat-first"

> Documento de análisis y propuesta. No incluye código — es la base de discusión y diseño para un producto SaaS multiempresa nuevo donde el chat con IA sea la interfaz principal.

**Estado: decisiones tomadas por Marcos el 2026-08-26.** La sección 8 registra qué quedó resuelto y qué sigue pendiente de investigar.

## 0. Resumen ejecutivo

Dewoc (el sistema actual) es un sistema de gestión completo, maduro y single-tenant: se instala una vez por cliente, con su propia base de datos, su propia configuración fiscal y sus propias credenciales de Mercado Pago. Tiene ~41 controllers, ~48 models y un chat con IA (Gemini) que ya puede *ejecutar acciones reales* sobre el sistema (crear clientes, registrar compras, ajustar stock, registrar pagos, etc.), no solo responder preguntas.

La propuesta central de este documento es doble:

1. **Construir un SaaS multiempresa nuevo**: un solo despliegue sirviendo a muchos comercios, cada uno aislado de los demás, con alta de cuenta self-service y multiusuario por empresa desde el primer día.
2. **Poner el chat al frente para todo lo administrativo, y mantener una pantalla de venta rápida dedicada para el mostrador**: la gestión del negocio (altas, ajustes, configuración, consultas) se resuelve hablando; la venta física en el punto de venta mantiene una pantalla de click + impresión de ticket, porque ahí la velocidad importa más que la conversación. Toda acción del sistema, incluida la venta, tiene que poder ejecutarse también desde el chat, según los permisos del usuario logueado.

**Decisión de alcance — proyecto nuevo, no una migración.** Este SaaS se construye como un desarrollo nuevo, en un proyecto y repositorio separados. El Dewoc actual sigue funcionando como está y se usa como referencia de diseño — en particular, el motor de function-calling del chat (patrón de herramientas + tarjeta de confirmación) que ya resuelve bien el problema de ejecutar acciones desde una conversación — pero no se modifica el código existente como parte de este trabajo.

Esto no es un ajuste incremental: es un producto nuevo, porque hoy **no existe ningún concepto de "empresa" en el modelo de datos ni en la interfaz** del sistema actual. El documento describe el estado del sistema actual como punto de partida, la visión del producto nuevo, qué alcance funcional tiene sentido, las decisiones de arquitectura ya tomadas y las que faltan, y un camino de desarrollo por fases.

---

## 1. Diagnóstico del proyecto actual (Dewoc)

### 1.1 Lo que ya existe y es una base de referencia sólida

- **Cobertura funcional amplia y ya usada en producción**: ventas/POS, caja y turnos, stock, clientes (con cuenta corriente), compras y facturas de compra, productos, recetas de producción, insumos, materias primas, proveedores (con pagos y cta. cte.), reportes tradicionales y por IA, permisos por rol, auditoría, backups, ecommerce/tienda pública, y módulos adicionales (activos fijos, empleados/nómina, socios, notificaciones push, proyecciones, portal de clientes mayoristas, WebAuthn).
- **PWA madura**: manifest con `share_target` (se puede compartir una foto de factura directo a la app), service worker en su versión 12, push notifications — no es un prototipo, es una capa bien mantenida.
- **Integraciones fiscales y de cobro reales**: AFIP (WSAA/WSFEv1, CAE, QR fiscal, Factura A/B/C automática) y Mercado Pago (Checkout Pro y Payment Brick, con webhook de confirmación).
- **El chat ya no es un chatbot de preguntas y respuestas**: mediante function-calling nativo (hoy sobre Gemini), tiene ~28 herramientas que ejecutan acciones reales — crear cliente, crear proveedor, registrar compra a partir de una foto de factura, ajustar stock, registrar pagos, registrar gastos, etc. — con un patrón de "tarjeta de confirmación" para acciones irreversibles (el modelo propone la acción, el usuario confirma antes de que se ejecute). **Este es el activo de referencia más valioso para el producto nuevo**: el motor conversacional-transaccional ya está probado, no hay que inventarlo de cero — hay que rediseñarlo multiempresa.

### 1.2 La brecha crítica: todo asume una sola empresa

Revisando el esquema de base de datos completo (~45 tablas) y la configuración: **ninguna tabla tiene una columna de empresa/tenant**, y todos los datos de negocio (nombre y dirección del comercio, condición ante IVA, CUIT y certificados AFIP, punto de venta, access token de Mercado Pago, si la tienda online está activa) son **constantes globales de la instalación**, leídas de un único archivo de configuración por servidor. El modelo de despliegue actual es literalmente "un cliente = una instalación completa = una base de datos propia".

El producto nuevo diseña el modelo de datos multiempresa desde cero (ver sección 5), tomando como referencia el esquema actual pero sin arrastrar esa limitación.

### 1.3 Una inconsistencia del proyecto actual, a no repetir en el nuevo

La documentación de Dewoc describe la IA como "Anthropic Claude API (Chat IA + MCP MySQL)". En el código real, el motor es **Google Gemini** con function-calling propio, y "MCP" es solo el nombre interno de una whitelist de tablas legibles — no el protocolo Model Context Protocol real. Para el producto nuevo la recomendación es simple: comunicar desde el día 1 lo que realmente se usa (ver la respuesta a la pregunta sobre MCP en la sección 6), sin prometer integraciones que no existen.

---

## 2. La tesis: chat-first para la gestión, click rápido para vender

### 2.1 El principio

El dueño de un almacén, una rotisería o un local de ropa no quiere aprender un ERP para las tareas de gestión: dar de alta un proveedor, ajustar un stock, registrar un gasto, mirar cómo viene el mes. Para eso, hablarle a un asistente es más simple que navegar menús y formularios.

**Regla de diseño rectora: si una acción de gestión se puede resolver por chat, no necesita su propio botón, formulario ni entrada de menú permanente.**

**Regla complementaria — el chat no es un canal de segunda:** toda acción que exista en el sistema tiene que poder ejecutarse también desde el chat, sujeta a los permisos del usuario que inició sesión. El chat no es una versión reducida de la aplicación; es una forma alternativa de accionar las mismas capacidades que expone la interfaz tradicional.

### 2.2 La excepción deliberada: vender rápido en el mostrador

Vender en un negocio físico es distinto a administrarlo. Cuando hay un cliente parado frente al mostrador, describir la venta en palabras es más lento que tocar una categoría, tocar un producto e imprimir el ticket. Por eso el punto de venta mantiene una **pantalla dedicada de venta rápida** (categorías + productos + cobro + impresión de ticket, tal como funciona hoy en ventas/POS), como camino principal para vender — no como una vista que hay que pedirle al chat.

Lo mismo aplica a reportes que se consultan o descargan seguido (por ejemplo, exportar un Excel): quedan accesibles de forma directa, no exclusivamente detrás de un pedido conversacional.

El chat-first es entonces el modo por defecto para **gestionar** el negocio; el POS de click es el modo por defecto para **vender**; y ambos caminos conviven — cualquiera de las dos acciones (vender incluido) también se puede resolver por chat cuando conviene (por ejemplo, cargar una venta retroactiva, o registrar algo mientras no se está frente al mostrador).

### 2.3 Qué rol le queda al resto de la interfaz tradicional

Además del POS de venta y los reportes/exportaciones de uso frecuente, hay otras pantallas que se mantienen, pero invocadas desde el chat en vez de estar fijas en un menú permanente:

- **Vistas de solo lectura puntuales** (ej.: "mandame el gráfico de gastos del mes") — se abren *a partir de* un pedido en el chat.
- **Confirmaciones de acciones irreversibles**: el patrón de "tarjeta de confirmación" que ya existe en Dewoc (el modelo interpreta, el usuario aprueba) es el patrón correcto y se generaliza a toda acción de escritura, no solo a compras.
- **Configuración poco frecuente**: alta de la empresa, datos fiscales, medios de pago — un asistente de configuración inicial ("wizard"), no algo que se visita todos los días.

### 2.4 Qué deja de ser el camino principal

Los menús de navegación módulo por módulo y los formularios de alta/edición para tareas de **gestión** (dar de alta un cliente o proveedor, registrar un gasto, ajustar stock, registrar un pago, configurar algo poco frecuente) dejan de ser lo primero que ve un usuario. No se trata de borrar esas pantallas (siguen sirviendo de respaldo, y toda acción tiene que seguir siendo posible desde ahí también), sino de dejar de ofrecerlas como el camino principal — con la excepción explícita de vender (2.2) y de los reportes/exportaciones de uso frecuente.

---

## 3. Qué es "lo básico" y qué se recorta

Un error común al empaquetar un sistema hecho a medida como SaaS es ofrecer todos los módulos a todos los clientes desde el primer día, lo cual abruma exactamente al tipo de usuario que este producto quiere atraer (comercios chicos, sin personal administrativo dedicado). La propuesta es un **núcleo universal** más **módulos activables por rubro y por plan**, todo dentro del mismo código base — nunca ramas de código distintas por cliente.

### 3.1 Núcleo universal (visible para cualquier negocio, desde el día 1)

- Ventas y cobros, con pantalla de venta rápida (categorías, productos, cobro e impresión de ticket)
- Caja (apertura, cierre, movimientos)
- Stock básico (qué tengo, cuánto tengo)
- Clientes básicos
- Gastos y compras simples
- Reportes esenciales: qué vendí, cuánto tengo, cuánto gasté — con exportación a Excel disponible de forma directa
- Usuarios y permisos, **multiusuario por empresa desde el día 1** (no arranca limitado a un solo usuario)

### 3.2 Módulos que se recortan del primer plano

Todo lo demás —activos fijos, nómina de empleados, socios, proyecciones económicas, analítica de uso interno, portal de clientes mayoristas, recetas de producción, ingredientes genéricos, galería con IA, WebAuthn, ecommerce/tienda propia, facturación AFIP— pasa a ser:

- **invisible por defecto**, y el propio chat lo activa cuando detecta que hace falta ("veo que estás anotando ingredientes de una receta, ¿querés que active el módulo de producción?"), o
- **un add-on o un plan superior**, reservado para el segmento de negocio que realmente lo necesita (un almacén de barrio no necesita recetas de producción; una fábrica de alimentos sí).

La verticalización se resuelve con **configuración por empresa** (qué módulos/herramientas están activas), no con versiones de código distintas por tipo de negocio.

---

## 4. Modelo de negocio: SaaS multiempresa

### 4.1 Planes (hipótesis inicial, a validar con clientes reales)

| Plan | Incluye |
|---|---|
| **Chat** (entrada) | Núcleo universal (sección 3.1), incluye multiusuario, sin facturación AFIP |
| **Negocio** | + Facturación AFIP |
| **Producción / Mayorista** | + Recetas, insumos y materias primas, + proveedores y compras, + portal de clientes mayoristas |
| **Ecommerce** | + Tienda pública + Mercado Pago |

Multiusuario, caja/turnos y reportes esenciales ya están en el núcleo universal (sección 3.1) y no distinguen planes. Esta segmentación es un punto de partida para conversar, no un cierre. Lo importante conceptualmente es que el "gating" entre planes es una lista de módulos/herramientas activables por empresa, no código distinto por cliente.

### 4.2 Onboarding

En un SaaS, el alta de un cliente nuevo tiene que ser: alta de cuenta → un asistente corto de 2-3 preguntas (rubro del negocio, si factura o no) → primer mensaje al chat. Cero fricción técnica para el dueño del negocio; nada de instaladores ni archivos de configuración.

La moneda por defecto es **pesos argentinos**, y no se pregunta en el alta — se asume directamente. Queda como un dato editable a futuro por si en algún momento se necesita soporte multi-moneda, pero no es parte del flujo de onboarding inicial.

### 4.3 Dos facturaciones distintas, que no hay que confundir

- La facturación de **Dewoc a sus clientes** (el cobro de la suscripción SaaS).
- La facturación **AFIP de cada negocio a sus propios clientes** (lo que el sistema resuelve para el usuario final).

Son dos circuitos de cobro completamente separados, con sus propios medios de pago y su propia lógica, y conviene mantenerlos conceptualmente distintos desde el diseño para no mezclar credenciales, reportes ni responsabilidades fiscales.

---

## 5. Arquitectura del producto nuevo (decisiones, sin código)

### 5.1 Aislamiento entre empresas — decisión tomada: por fila

Se evaluaron dos caminos:

- **Aislamiento por fila**: una sola base de datos compartida, y cada tabla identifica a qué empresa pertenece cada registro. Escala mejor operativamente (un solo despliegue, backups centralizados, actualizaciones simultáneas para todos), pero exige disciplina estricta: ninguna consulta puede ejecutarse sin ese filtro.
- **Aislamiento por base de datos dedicada por cliente**: más simple de razonar en seguridad (es físicamente imposible que una empresa lea datos de otra), pero no escala bien a cientos o miles de comercios chicos: cada base nueva es un costo de infraestructura y de mantenimiento.

**Decisión tomada: se desarrolla con aislamiento por fila** (`empresa_id` en cada tabla, una sola base compartida). Queda abierto, como optimización futura y no como bloqueante, ofrecer una base dedicada solo para cuentas grandes o con requisitos de cumplimiento específicos si en algún momento hace falta.

### 5.2 Qué hay que resolver en cada capa (a alto nivel)

- **Datos**: el esquema nuevo identifica la empresa de cada registro desde el diseño inicial, con los índices adecuados para que las consultas sean rápidas.
- **El chat**: cada herramienta de function-calling, y cualquier whitelist de tablas legibles, quedan atadas obligatoriamente a la empresa de la conversación en curso — nunca depender de que el modelo de lenguaje "recuerde" o "respete" ese límite por sí solo. El scoping por empresa se resuelve en la capa de datos, antes de que la consulta llegue al modelo.
- **Permisos**: el default sin configuración explícita tiene que ser **restringido**, no abierto — al revés del comportamiento actual de Dewoc (razonable ahí porque es una instalación privada de un solo cliente, pero peligroso en un SaaS multiempresa).
- **Configuración de negocio**: datos fiscales, credenciales de AFIP, token de Mercado Pago y datos de tienda son datos propios de cada empresa desde el diseño, gestionados de forma que las credenciales de un negocio nunca sean accesibles, ni siquiera indirectamente, desde el contexto de otro.
- **El motor de IA**: un único servicio (Gemini) atendiendo a todas las empresas, con cada conversación atada a una empresa desde el primer punto de entrada (la sesión del usuario), de forma inviolable.

---

## 6. Chat, Gemini y la pregunta sobre MCP

Con Gemini confirmado como motor de IA, queda la pregunta de si conviene construir un servidor MCP (Model Context Protocol) real.

**Respuesta: no es necesario, al menos no en esta etapa.** El protocolo MCP resuelve un problema que este producto no tiene todavía: dejar que clientes de IA externos (Claude Desktop, otros agentes de terceros) se conecten a los datos y acciones de un sistema de forma estandarizada. Acá el chat es una función propia de la aplicación, con su propia interfaz, hablando con un solo modelo (Gemini) mediante function-calling nativo — el mismo patrón que ya funciona en Dewoc hoy. Construir un servidor MCP encima de eso agregaría una capa de protocolo (transporte, sesiones, formato estándar) que no le suma nada al usuario final y sí suma superficie de mantenimiento.

Vale la pena reconsiderarlo más adelante solo si aparece una razón concreta, por ejemplo: ofrecer que un cliente conecte su propio agente de IA (Claude Desktop u otro) a los datos de su negocio, como diferencial de un plan superior. Para el arranque, la recomendación es seguir con function-calling nativo de Gemini, sin protocolo MCP real — y evitar cualquier mensaje de marketing que sugiera lo contrario (ver 1.3).

### 6.1 Preparado para MCP desde el día 1 (sin construirlo todavía)

No construir el servidor MCP ahora no significa ignorar la posibilidad. El sistema se diseña desde el inicio para que agregar una integración MCP real, el día que haya una razón de negocio para hacerlo, sea sumar un adaptador — no reescribir el motor del chat. Concretamente:

- **El catálogo de herramientas se define en un formato propio, independiente de Gemini**: nombre, descripción, esquema de datos de entrada y handler. La integración específica con Gemini (cómo se le presentan esas herramientas y cómo se interpretan sus respuestas) vive en una capa fina y separada, no mezclada con la lógica de negocio de cada herramienta. Ese catálogo es, en la práctica, el mismo inventario que un servidor MCP expondría el día de mañana como su lista de *tools*.
- **El scoping por empresa se aplica en la capa de datos/handlers, no en el prompt ni en el glue de Gemini.** Así, cualquier transporte nuevo que se agregue en el futuro (un servidor MCP, otro proveedor de modelo) hereda automáticamente el mismo límite de seguridad, sin tener que reimplementarlo.
- **El patrón de confirmación para acciones irreversibles se modela como un mecanismo genérico** ("acción pendiente de aprobación"), desacoplado del formato de respuesta específico de Gemini — MCP tiene un concepto equivalente de confirmación humana en el medio, así que este patrón es directamente reutilizable si se agrega un cliente MCP más adelante.
- **El catálogo de herramientas ya está pensado como algo versionado por empresa y por plan** (sección 3.2): esa misma estructura es la que definiría, por empresa, qué expondría un futuro servidor MCP — no es trabajo perdido si MCP nunca se adopta, y no es trabajo extra si se adopta después.

En resumen: la arquitectura queda MCP-ready por diseño (separación clara entre catálogo de herramientas, motor de scoping y adaptador de proveedor de IA), aunque el protocolo en sí no se implemente en esta etapa.

---

## 7. Camino de desarrollo propuesto (por fases)

1. **Fase 0 — Fundacional**: diseñar el modelo de datos multiempresa desde cero (aislamiento por fila, ya decidido), definir cómo se gestionan de forma segura las credenciales AFIP/Mercado Pago por empresa, y validar el núcleo universal hablando con algunos comercios reales antes de fijarlo.
2. **Fase 1 — Multiempresa en los datos**: construir el esquema con `empresa_id` desde el diseño inicial, modelar la configuración de negocio como datos por empresa (no constantes globales), definir el default restringido de permisos, y el modelo de multiusuario por empresa.
3. **Fase 2 — Interfaz chat-first + POS**: el chat como puerta de entrada para gestión, con la pantalla de venta rápida (POS) como camino dedicado para vender; toda acción disponible en ambos caminos, según permisos.
4. **Fase 3 — Alta autoservicio y cobro**: alta de empresa sin intervención manual, planes, cobro recurrente de la suscripción (podría apoyarse en el mismo Mercado Pago, con suscripciones).
5. **Fase 4 — Verticalización**: catálogo de módulos activables por rubro y por plan, para diferenciar "comercio simple" de "productor/mayorista" o "ecommerce" sin bifurcar el código base.

---

## 8. Decisiones — estado actual

| Pregunta | Estado |
|---|---|
| ¿Aislamiento por fila o por base de datos por cliente? | **Resuelto** — por fila (sección 5.1). |
| ¿Se mantiene Gemini o se evalúa Claude/MCP real? | **Resuelto** — Gemini, sin servidor MCP real por ahora (sección 6). |
| ¿Qué tan "solo chat" desde el día 1? | **Resuelto** — conviven ambos caminos: chat-first para gestión, POS de click para vender; toda acción del sistema tiene que estar disponible también por chat, según los permisos del usuario logueado (secciones 2.1–2.2). |
| ¿Multiusuario por empresa desde el día 1, o se suma después? | **Resuelto** — multiusuario por empresa desde el día 1 (sección 3.1). |
| ¿Proyecto nuevo o migración del actual? | **Resuelto** — proyecto nuevo, separado del Dewoc actual (sección 0). |
| Cumplimiento normativo (datos fiscales/AFIP de terceros en infraestructura compartida) | **Pendiente** — Marcos todavía tiene que investigar esto con un contador o abogado antes de escalar a muchos clientes; no hay respuesta todavía. |
