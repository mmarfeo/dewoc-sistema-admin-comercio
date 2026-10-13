# Portal de Clientes Mayoristas — Checklist de diseño

> Objetivo: un cliente revendedor (ej. el vecino que revende helado) se loguea a una
> sección propia del sistema, ve **solo su catálogo asignado** (ej. Helado 1kg/½kg/¼kg con
> selector de gustos según stock real), arma un pedido, y ese pedido dispara un **modal
> bloqueante** en `ventas/pos` para quien tenga la caja abierta — que tiene que Aceptar,
> Rechazar o contestar por chat antes de poder seguir usando el POS. Aceptado, se convierte
> en una venta normal a cuenta corriente. Documento de diseño — nada de esto está
> implementado todavía.

Leyenda: ✅ Decidido · ⬜ A diseñar/implementar · 🔍 Abierto

---

## Decisiones ya tomadas (respuestas del usuario)

| Pregunta | Decisión |
|---|---|
| ¿Catálogo genérico "mayorista" o por cliente? | **Específico por cliente** — cada cliente tiene su propio catálogo asignado, pensado para poder sumar más revendedores después con catálogos distintos. |
| ¿El cajero puede ajustar cantidades/gustos al aceptar? | **No** — tiene que quedar exactamente lo que pidió el cliente. Se agrega una sección de **notas** libres para aclaraciones que no cambian el pedido en sí (ej. "sin crema", "envolver por separado"). |
| ¿Varias cajas abiertas — a cuál le llega el modal? | **Al primero que lo atienda** (mismo criterio que el modo `turno_abierto` de tareas asignadas en `docs/tareas-pendientes.md` #2). |
| ¿Alcanza con que el cliente vea "rechazado" en el portal? | **Sí, pero el rechazo tiene que mandarse como mensaje por el chat** — no hay un campo de motivo aparte: el chat es el único canal de comunicación del pedido. |
| ¿Cómo se entera el cliente de una respuesta en el chat? | **Push notification + modal visible en la pantalla principal del portal** al volver a entrar — dos avisos, no uno solo. |

## Por qué no reutilizar `pedidos_ecommerce`

Ya se descartó en la charla previa: ese flujo es anónimo, se cobra automático por Mercado
Pago, y el stock se descuenta recién cuando el pago se acredita. Acá el pedido necesita
**aceptación humana obligatoria** y se cobra a **cuenta corriente**, no por Mercado Pago —
son semánticas distintas. Se crea un concepto propio: `pedidos_clientes`.

## Cómo se resuelven las variantes (1kg/½kg/¼kg) y los gustos

**Sin mecanismo nuevo** — ya existe todo: cada variante (Helado 1kg, ½kg, ¼kg) es un
producto distinto, y las tres comparten el mismo grupo de opciones "Gustos de helado"
(reutilizable entre productos, con mínimo/máximo de selección propio por producto — 1kg
puede pedir 3 gustos, ¼kg solo 1 — tal como ya está diseñado en
`docs/tareas-pendientes.md` #1 y **ya implementado** en `GrupoOpcionModel`/
`ventas_detalle_opciones`). El stock por gusto que se ve en el portal es el mismo
`stock_actual` de la materia prima que ya descuenta el POS — no hay que duplicar nada.

---

## Modelo de datos (conceptual)

#### Acceso del cliente al portal
`clientes` no tiene login hoy. Necesita credenciales propias (separadas de `usuarios`,
que es el login de staff) — nueva tabla o columnas en `clientes`:

| Campo | Para qué |
|---|---|
| email / usuario | login del cliente |
| password_hash | igual que `usuarios.password_hash` — mismo mecanismo de hash |
| portal_activo | el admin habilita/deshabilita el acceso sin borrar al cliente |

Reutilizar el mismo flujo de "recuperar contraseña" que ya existe para `usuarios`
(`AuthController::recuperar/resetPassword`) en vez de inventar uno nuevo.

#### Catálogo por cliente
| Campo | Para qué |
|---|---|
| cliente_id | qué cliente |
| producto_id | qué producto le está habilitado |
| precio_especial | nullable — precio mayorista si difiere del `precio_venta` normal |
| activo | dar de baja un producto del catálogo de ese cliente sin perder historial |

#### Pedido de cliente
| Campo | Para qué |
|---|---|
| cliente_id | quién lo hizo |
| estado | `pendiente` \| `aceptado` \| `rechazado` \| `convertido` (ya se facturó como venta) |
| notas_cliente | aclaraciones libres del cliente, no estructuradas |
| turno_id / usuario_id | quién lo atendió, una vez resuelto |
| venta_id | nullable — a qué venta se convirtió al aceptar |
| created_at / resuelto_at | |

#### Detalle del pedido
Igual forma que `ventas_detalle` + `ventas_detalle_opciones` (producto, cantidad, y las
opciones/gustos elegidos) — se copia tal cual a la venta real cuando se acepta, sin
reinterpretar nada.

#### Chat del pedido
| Campo | Para qué |
|---|---|
| pedido_id | a qué pedido pertenece (el chat es por pedido, no un chat general del cliente) |
| autor_tipo | `cliente` \| `staff` |
| autor_id | cliente_id o usuario_id según corresponda |
| mensaje | texto |
| created_at / leido_at | para saber qué hay sin leer de cada lado |

---

## Ciclo de vida del pedido

```
Cliente arma el pedido en el portal → pendiente
        │
        ▼
Modal bloqueante en ventas/pos (al primero que tenga turno abierto)
        │
   ┌────┴─────┬──────────────┐
   ▼          ▼              ▼
Aceptar   Rechazar      Abrir chat (no resuelve el modal todavía,
   │      (obligatorio   permite preguntar antes de decidir)
   │       mandar msj.
   │       por el chat)
   ▼
Precarga el carrito del POS con exactamente los ítems/gustos pedidos
(líneas no editables — las notas del cliente se muestran al cajero como texto)
   │
   ▼
Cajero cobra normal (cliente + Cuenta Corriente ya preseleccionados) → venta creada
   │
   ▼
pedidos_clientes.estado = 'convertido', venta_id asignado
```

## El modal bloqueante en `ventas/pos`

- Mismo mecanismo de aviso que ya existe (WebPush, `PushSubscriptionModel` +
  `notificarAdminsAnulacion()` como referencia) para avisar a quien tenga turno abierto.
- A diferencia del cartel de "tareas asignadas" (que se puede posponer), este bloquea:
  modal con backdrop fijo, sin botón de cerrar, sin click-afuera — no se puede seguir
  usando el POS hasta Aceptar/Rechazar.
- "Al primero que lo atienda": si hay dos cajas abiertas y ambas reciben el push, el
  primero que resuelve el modal cierra el pedido para los dos (mismo patrón ya usado en
  el modo `turno_abierto` de tareas asignadas — el que actúa primero lo resuelve para todos).
- Abrir el chat **no** cierra el modal — el cajero puede preguntar algo antes de decidir
  Aceptar/Rechazar, el bloqueo se mantiene hasta que efectivamente elija una de las dos.

## El chat

- Scope por pedido, no un chat general del cliente — todo tiene contexto ("esto es sobre
  el pedido #123").
- Es el único canal de comunicación del pedido: un rechazo **requiere** escribir un
  mensaje (no hay un campo de motivo separado — ver "Decisiones ya tomadas").
- Notificación al cliente cuando el staff responde: **push** (extender el `WebPush` que ya
  existe — hoy solo suscribe a `usuarios`, hay que sumar suscripción de push para
  `clientes`) **+ modal visible** al volver a la pantalla principal del portal (mismo
  concepto que el modal del cajero, pero sin bloquear la navegación del cliente).

---

## Fuera de alcance (por ahora)

- Límite de crédito específico para pedidos del portal — ya existe el tope de cuenta
  corriente general por cliente (`limite_cuenta_corriente`), se reutiliza tal cual.
- Historial/analítica de pedidos por cliente dentro del portal (más allá de ver el estado
  del propio pedido) — se puede sumar después sin cambios de modelo.
- Que el cliente pueda cancelar un pedido ya aceptado desde el portal.

## Preguntas abiertas

Ninguna — todas resueltas (ver "Decisiones ya tomadas (ronda 2)").

## Decisiones ya tomadas (ronda 2)

| Pregunta | Decisión |
|---|---|
| Alta del acceso al portal | El admin lo carga a mano desde la ficha del cliente (mismo patrón que la creación de `usuarios`) — no hay invitación por email en esta versión. |
| Chat sin responder por parte del staff | También dispara aviso (no bloqueante, a diferencia del modal de pedido nuevo) — un cliente esperando respuesta no debería quedar sin que nadie se entere. |
| Precio del pedido | Se congela al momento en que el cliente lo hace (snapshot en `pedidos_clientes_detalle`, igual que `ventas_detalle.precio_unitario` ya es un snapshot y no algo que se recalcula después). |

**Alcance de implementación**: se desarrolla todo junto en un solo tramo (login, catálogo
por cliente, carrito/checkout, modal bloqueante, chat y push) — no se separa en etapas.
