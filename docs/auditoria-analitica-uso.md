# Auditoría / analítica de uso — documento para conversar

> Esto es un documento de discusión, no un plan cerrado. El objetivo es alinear qué
> problema se quiere resolver y qué tan lejos vale la pena llegar, antes de diseñar nada
> en concreto. Ninguna sección de acá implica que ya se vaya a implementar así.

## 1. La pregunta original

> "¿Es posible crear una sección de auditoría/análisis de datos para dar seguimiento a
> las acciones que utilizan los usuarios, o cuánto tiempo le lleva a un usuario realizar
> acciones, y así poder acumular datos para mejorar la experiencia y eliminar
> funcionalidades que ya no se usan?"

Separo esto en tres preguntas de producto distintas, porque cada una tiene una respuesta
técnica muy distinta y no hace falta responder las tres a la vez:

1. **¿Qué funcionalidades se usan y cuáles no?** → frecuencia de uso por módulo/acción.
   Es la más barata de responder y la que más directamente sirve para "qué doy de baja".
2. **¿Quién usa qué, y con qué frecuencia?** → mismo dato de (1) pero cruzado por usuario/rol,
   útil para detectar features que solo usa una persona, o pantallas que un rol entero
   nunca abre (puede ser señal de que le falta un permiso, no de que sobra la feature).
3. **¿Cuánto tarda un usuario en completar una acción?** → esto es "analítica de UX", mucho
   más caro de instrumentar bien y con un techo de utilidad más bajo si no se define de
   antemano qué flujos puntuales importa medir (ver sección 4).

## 2. Qué ya existe hoy (no arrancamos de cero)

Ya hay una tabla `audit_log` + clase `core/AuditLog.php` + pantalla `/auditoria`
(admin-only, `AuditLogController.php`), pensada como **log de seguridad/trazabilidad**,
no como analítica de producto:

- Columnas: `usuario_id`, `usuario_nombre`, `accion`, `modulo`, `descripción`, `ip`,
  `created_at`. Sin duración, sin distinguir sesión/dispositivo.
- Cobertura actual: **solo 7 archivos** llaman a `AuditLog::log()`, y son casi todos
  eventos sensibles — login/logout, intentos fallidos, permisos, anulaciones de venta,
  tareas asignadas, descarga de backups, WebAuthn. **No registra vistas de pantalla ni
  el uso normal del día a día** (abrir el listado de productos, entrar a un reporte,
  hacer una venta común, etc.) — así que hoy `audit_log` no puede responder "¿qué
  módulos no usa nadie?", porque la mayoría de los módulos nunca escriben ahí.
- La pantalla `/auditoria` ya tiene filtro por fecha/módulo/acción y tabla — la base de
  UI para una futura pantalla de analítica podría heredar ese mismo esqueleto.

**Conclusión de esta sección**: no hace falta "crear una sección de auditoría" desde
cero — ya existe una, pero mide otra cosa (seguridad, no uso). La pregunta real es si
conviene ampliarla, o separar esto en un sistema nuevo y más liviano.

## 3. Opción A — Frecuencia de uso por módulo (la más barata, la más directa)

**Qué mide**: cuántas veces se accedió a cada pantalla/acción, por período, por usuario.
Alcanza para responder la pregunta de "¿qué se usa y qué no?" sin necesidad de nada de
duración.

**Cómo se podría instrumentar** (dos variantes, no excluyentes):

- **Server-side, en `Controller::render()`**: como todas las vistas ya pasan por un
  único punto (`Controller.php`), se podría loguear un evento "vista X, módulo Y" cada
  vez que se renderiza una pantalla completa — un solo lugar para tocar, cubre el 100%
  de las pantallas automáticamente (a diferencia de `AuditLog::log()`, que hay que
  llamar a mano en cada controlador). No captura acciones AJAX puntuales (ej. un ajuste
  de stock vía fetch) a menos que también se enganche en el método `json()`/`input()`
  común.
- **Ampliar `AuditLog::log()`** a más acciones (ej. cada `index()` de cada controlador) —
  más manual, más control fino sobre qué se considera "una acción" digna de registrar,
  pero requiere tocar decenas de archivos y mantenerlo a mano cada vez que se agregue
  una pantalla nueva (se olvida fácil, como ya se ve con la cobertura actual).

**Volumen de datos**: esto sí puede crecer rápido — cada carga de página de cada
usuario, multiplicado por meses. Para que sea sostenible, casi seguro conviene guardar
esto **agregado por día** (`fecha, usuario_id, modulo, accion, cantidad`) en vez de una
fila por evento — se pierde el detalle de "a qué hora exacta", pero para responder
"¿qué no se usa hace 6 meses?" alcanza de sobra y la tabla no crece sin límite.

## 4. Opción B — Tiempo que tarda un usuario en completar una acción

Esto es sensiblemente más caro y más ambiguo que la opción A, porque primero hay que
definir **qué significa "una acción" y dónde empieza/termina el cronómetro**. Algunos
ejemplos concretos de lo ambiguo que es esto en la práctica:

- ¿"Registrar una venta" se mide desde que se abre `/ventas/pos` hasta que se confirma
  el cobro? ¿Incluye el tiempo que el cajero pasó atendiendo al cliente en persona
  (que no es un problema de UX del sistema) o solo los clicks dentro de la pantalla?
- ¿"Cargar una factura de compra" se mide desde que se abre el formulario, o desde que
  se sube la foto al Chat IA (que es un camino totalmente distinto al formulario manual)?
- Si el usuario abre la pantalla, la deja abierta 20 minutos mientras atiende otra cosa,
  y después completa la acción — ¿esos 20 minutos "cuentan" como parte del tiempo de la
  tarea? (Casi seguro que no deberían, pero hay que filtrarlo explícitamente.)

**Por qué no conviene instrumentar esto de forma genérica**: a diferencia de la
frecuencia de uso (que se puede medir igual en todos lados con un solo hook central),
la duración "bien medida" necesita marcar a mano un punto de inicio y un punto de fin
**por cada flujo que importe medir** — no es algo que se pueda activar globalmente sin
producir números que después no signifiquen nada útil.

**Alternativa más realista**: en vez de "duración de una acción" en abstracto, elegir
2-3 flujos puntuales que de verdad interesa optimizar (ej. "tiempo desde que se abre el
POS hasta que se cobra una venta", "tiempo desde que se sube una foto al Chat IA hasta
que se confirma la compra") e instrumentar esos puntualmente, con un inicio y un fin
bien definidos. Esto da datos accionables; una medición genérica de "tiempo en
pantalla" para todo el sistema, no.

## 5. Qué NO hace falta para responder "¿qué se puede dar de baja?"

Vale la pena decirlo explícito, porque a veces "quiero analítica" termina siendo más
proyecto del que hace falta: para la pregunta concreta de **detectar funcionalidades sin
uso**, la Opción A (frecuencia por módulo, agregada por día) alcanza sola. No hace falta
duración, no hace falta trackear cada click, no hace falta un sistema tipo Google
Analytics interno. Con 2-3 meses de datos de "cuántas veces se entró a cada pantalla"
ya se puede armar una lista razonable de candidatos a dar de baja (ej. "reportes/X: 0
accesos en 90 días, exceptuando el admin").

## 6. Propuesta de fases (para discutir, no decidido)

1. **Fase 1 — Frecuencia de uso agregada** (Opción A, server-side centralizado en
   `Controller::render()` o similar). Tabla nueva y chica, agregada por día. Sin tocar
   flujos individuales. Esto ya responde la pregunta principal del pedido original
   ("qué se usa y qué no").
2. **Fase 2 — Pantalla de reporte** sobre esos datos: ranking de módulos por uso,
   filtro por rango de fechas, quizás un flag visual "sin uso en los últimos N días".
   Podría vivir en `/auditoria` como una pestaña nueva, o en una sección aparte
   (`/analitica`) para no mezclar "seguridad" con "producto" en la misma pantalla.
3. **Fase 3 (opcional, solo si después de la Fase 1-2 sigue interesando)** — duración de
   flujos puntuales, elegidos a mano, no genérico.

## 7. Preguntas abiertas para definir antes de diseñar algo puntual

- ¿El objetivo principal es "dar de baja funcionalidades" (Fase 1 alcanza) o también
  "mejorar la experiencia de uso" en flujos específicos (ahí sí entraría la Fase 3, y
  convendría nombrar qué flujos)?

  - Las 2 cosas, para la mejora de experiencia de usuario me basaria en pocos flujos, 1 ventas/pos , 2 carga de ft de compras tanto por chat ia como en la seccion de compras de facturas, 3 carga de inventario. 

- ¿Cada cuánto se necesitaría mirar este reporte — es algo que se consulta una vez cada
  tanto (alcanza con una pantalla simple) o se quiere un hábito de revisión periódica
  (ahí valdría la pena un resumen automático, ej. mensual por mail/chat)?
  - Se mirará una vez cada tanto,

- ¿Interesa cruzar esto por rol/usuario (Opción A, punto 2 de la sección 1), o alcanza
  con el total por módulo sin importar quién lo usó?
  - Si es importante el rol/usuario

- Retención: ¿cuánto tiempo tiene sentido guardar este dato agregado? (a diferencia de
  `audit_log`, que es trazabilidad y capaz interesa guardarlo más tiempo, esto es
  analítica de producto — probablemente no hace falta guardarlo "para siempre").
  - 6 meses

## 8. Diseño concreto según lo respondido

Con las respuestas de la sección 7, esto es lo que se armaría — todavía para confirmar
antes de tocar código, en particular los puntos de inicio/fin de cada flujo de la Fase 3
(son la parte ambigua que señalaba la sección 4, y ahora hay que fijarla).

**Fase 1 — Frecuencia de uso agregada**, con desglose por usuario (ya que "sí es
importante el rol/usuario"): tabla agregada por día con `fecha, usuario_id, modulo,
accion, cantidad`. El rol no se guarda en la tabla (cambia con el tiempo) — se resuelve
al momento de mirar el reporte, cruzando `usuario_id` contra `usuarios.rol_id` actual.

**Fase 2 — Pantalla de reporte**, de consulta ocasional (no hace falta resumen
automático ni envío periódico): ranking de módulos por accesos, filtrable por rango de
fechas y por usuario/rol, con flag de "sin uso en los últimos N días". La pondría en una
sección aparte (`/analitica`, o similar) en vez de una pestaña de `/auditoria`, ya que
esa pantalla es de seguridad y esta es de producto — mezclar los dos temas en una sola
grilla de filtros los hace más confusos a ambos.

**Fase 3 — Duración de los 3 flujos elegidos**. Punto de inicio/fin propuesto para cada
uno (a confirmar, es la parte que hay que fijar bien para que el dato sirva):

1. **Ventas / POS**: inicio = se agrega el primer ítem al carrito de una venta nueva
   (no desde que se abre la pantalla — el POS puede quedar abierto horas sin que eso
   sea "tiempo de la tarea"); fin = venta confirmada/cobrada con éxito.
2. **Carga de factura de compra — dos caminos, medidos por separado para poder
   comparar uno contra el otro**:
   - Por Chat IA: inicio = se envía la foto/PDF del comprobante; fin = se confirma la
     tarjeta de compra (factura guardada).
   - Por el formulario manual de Compras: inicio = se abre "Nueva factura"; fin = se
     guarda el formulario con éxito.
3. **Inventario físico**: inicio = se abre `/stock/inventario`; fin = se guarda el
   conteo con éxito (`/stock/inventario/guardar` responde `ok`).

**Retención**: 6 meses. A definir un detalle chico: ¿se purga el detalle diario
completo a los 6 meses (se pierde la posibilidad de re-armar el reporte de un período
viejo), o se mantiene un total agregado sin desglose diario más allá de eso? Por
default asumiría "se purga completo" salvo que se indique lo contrario, ya que el caso
de uso es siempre "los últimos N meses", no histórico permanente.

## 9. Próximo paso

Con esto ya definido, el siguiente paso natural sería armar un plan de implementación
concreto (tablas, dónde engancha cada hook, diseño de la pantalla de reporte) y
ejecutarlo por fases. Decime si querés que arranque por ahí — probablemente convenga
usar el modo plan para la Fase 1+2 (es la que más archivos toca) y dejar la Fase 3 para
después de tenerla andando en producción, ya que sus 3 flujos son independientes entre
sí y no hace falta definirlos todos de una.