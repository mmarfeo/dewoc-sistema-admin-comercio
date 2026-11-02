# Dewoc Cobros — checklist de la app del celular y del aviso en el POS

> Documento de trabajo. Redactado el 2026‑11‑01 a partir de la revisión del código de Dewoc
> (`ventas/pos.php`, `ApiMenuAppController`, `IntegracionMenuAppModel`, permisos).
> **Revisado el 2026‑11‑01:** los lectores pasan al servidor (C14), la lista de paquetes
> permitidos se administra desde Dewoc (C3), el `uuid` sale de los datos de la notificación (C10)
> y las etapas se ordenan para tener primero una versión mínima que funcione de punta a punta.
> **Revisado el 2026‑11‑01 (2):** Dewoc Cobros es un **módulo que se cobra aparte** del plan de Dewoc,
> **por celular vinculado**, habilitado con una **licencia firmada** (C16 a C21 y etapa 1A).
> **Revisado el 2026‑11‑02:** la contratación es **manual** por ahora (C22): el comercio pide Dewoc
> Cobros desde Integraciones, el proveedor cobra por fuera y le pasa la licencia. El cobro automático
> desde Dewoc queda para la etapa 7.
> **Revisado el 2026‑11‑02 (2):** la app ya está programada contra el contrato de la sección 3. Se agregan
> la notificación de prueba, el QR con código, la versión publicada de la app y el significado de
> `optimizacion_bateria`. El orden de trabajo del servidor, visto desde la app, está en
> [`dewoc-cobros-servidor-plan.md`](dewoc-cobros-servidor-plan.md).
> **Revisado el 2026‑11‑02 (3), a pedido del comercio:**
> - **El aviso del POS se va solo** a los 12 segundos (cambia C6 y C7). También se cierra con la ✕, y con
>   el mouse encima espera. Ya no hace falta guardar quién lo cerró, así que `cobros_recibidos_cierres` y
>   `POST cobros-recibidos/cerrar` (3.1) quedan sin usar por ahora.
> - **Validación de ventas, adelantada de las etapas 4.4 y 7:** cada cobro se asocia automáticamente
>   (`cobros_recibidos.venta_id`) con la venta completada cuyo monto en Transferencia + Mercado Pago es
>   igual, registrada hasta 30 minutos antes o después (la más cercana si hay varias). Se recalcula al
>   registrar, anular o cambiar el medio de pago de una venta. El listado de ventas tiene la columna
>   **"Dewoc Cobros"** (Validada / Sin validar), el filtro y un resumen para la conciliación de caja:
>   ventas por transferencia sin validar y cobros recibidos sin venta.
> **Revisado el 2026‑11‑02 (4), a pedido del comercio:** se mantiene **1 transferencia ↔ 1 venta** (no
> se reparte un cobro entre varias ventas ni se registran propinas). La validación automática sigue
> siendo exacta; lo que no cierra (pagó una hora después, dejó propina) lo asigna una persona:
> - **Pop-up en el POS cada una hora** con las transferencias sin asignar **del turno abierto** (desde su
>   apertura). No se abre con una venta en curso. Al cerrar el turno, el cajero del siguiente ya no las
>   ve. El cajero asigna solo transferencias y ventas de su turno; cambiar el medio de pago de la venta
>   al asignar exige permiso para editar ventas.
> - **Ventas → Transferencias recibidas:** todas, por período y estado (pendientes, asignadas, no
>   corresponden), para asignar, quitar o marcar **"No corresponde a una venta"** con un motivo (seña,
>   devolución…), que la saca de pendientes.
> - Una asignación hecha a mano no la pisa la validación automática; si la venta se anula, la
>   transferencia vuelve a quedar pendiente.
>
> Son **dos productos**: **Dewoc** (el sistema web) y **Dewoc Cobros** (la app del celular). La
> "licencia" no es otra app: es el comprobante interno que guarda Dewoc cuando el comercio pagó Dewoc
> Cobros ("2 celulares hasta el 31/12"), y es lo que habilita a la app.
>
> **Qué es:** una app Android chica, **Dewoc Cobros**, que se instala en el celular del negocio
> (el que recibe las transferencias). Cuando llega una notificación de Mercado Pago, de otra
> billetera o de un banco, la app la reenvía a Dewoc **en el momento**, con el paquete de la app
> que la publicó. Dewoc la lee, saca el **monto** y el **nombre de quien pagó** y, si es un cobro,
> muestra un **aviso en el POS**, que se cierra con una cruz y no interrumpe la venta en curso.
>
> Primer comercio: **Heladería Serendipia**.

---

## 1. Objetivo y decisiones

El problema: el cliente dice "ya te transferí" y el cajero tiene que buscar el celular, desbloquearlo
y abrir la app del banco mientras la fila espera. Con Dewoc Cobros, el cobro aparece solo en la
pantalla del POS a los pocos segundos de llegar.

```
CELULAR DEL NEGOCIO                 DEWOC (erpcomercio.dewoc.com)                POS (cada caja abierta)
 notificación de MP / banco          POST /api/cobros/v1/notificaciones           consulta cada 5 s
   └─ ¿paquete permitido?  ───────▶   guarda en cobros_recibidos (sin duplicados)  ◀── aviso flotante
      sí → reenvía título y texto     lector del paquete → cobro / descartado /       💰 $12.500 · Juan Pérez ✕
      no → la descarta ahí mismo                           no reconocido
          ▲
          └──── lista de paquetes permitidos (GET /estado, respuesta de /latido)
```

### Dónde vive cada parte

| Parte | Dónde | Por qué |
|---|---|---|
| Servidor: tablas, API `/api/cobros/v1/`, lectores, pantalla de Integraciones, aviso del POS | **Dentro de Dewoc**, como integración, igual que MenuApp | Necesita usuarios, permisos, turnos de caja y el POS. Separarlo duplicaría login, base y deploy. |
| App Android | **Proyecto y repositorio aparte**, fuera de `htdocs` (`C:\Users\Usuario\Documents\Proyectos\16-DewocCobros`), en GitHub: [`mmarfeo/DewocCobros`](https://github.com/mmarfeo/DewocCobros) | Otro lenguaje (Kotlin), otro IDE y otro build. Lo que está en `htdocs` lo sirve el Apache de XAMPP. |
| Contratar y habilitar Dewoc Cobros (pantalla "Contratar", carga y verificación de la licencia, script para emitirla) | **Dentro de Dewoc** (etapa 1A) | El comercio lo pide y lo habilita desde su propio Dewoc. |
| Clave privada que firma las licencias | Solo en la PC del proveedor, **fuera del repositorio** | Quien la tenga puede habilitar Dewoc Cobros sin pagar. |

### Recorrido del usuario: Sidebar → Integraciones → Dewoc Cobros

Todo se hace desde **una sola pantalla** de Dewoc, `integraciones/dewoc-cobros`, que se abre desde el
sidebar, en el grupo **Integraciones**, debajo de "MenuApp" y "Disponibilidad en el menú". La ven los
usuarios con rol admin, igual que el resto del grupo. La pantalla muestra el paso en el que está el
comercio:

```
Sidebar ▸ Integraciones ▸ 💰 Dewoc Cobros
 │
 ├─ 1. CONTRATAR            (sin licencia)
 │     qué hace · cómo contactar al proveedor · código de instalación [copiar]
 │
 ├─ 2. CARGAR LICENCIA      (el proveedor cobró y le pasó la licencia)
 │     [pegar licencia] → "Dewoc Cobros habilitado: 2 celulares hasta el 31/12"
 │
 ├─ 3. HABILITAR EL CELULAR (con licencia)
 │     descargar el APK · instalar "Dewoc Cobros" · [Vincular celular] → QR
 │     la app escanea el QR → el celular aparece en la lista como "Activo"
 │
 └─ 4. USO DIARIO
       celulares (1 de 2) · últimos cobros · paquetes permitidos · renovar licencia
```

- El paso 1 **siempre** se ve mientras no haya licencia, y el ítem del sidebar lleva la etiqueta
  "Contratar".
- Pasos 3 y 4: se puede vincular otro celular mientras quede lugar en la licencia (C18).
- Con la licencia **vencida**, la pantalla vuelve a mostrar arriba "Renovar" (paso 2). La lista de
  cobros sigue visible.

### Decisiones

| # | Decisión |
|---|----------|
| C1 | **La app solo lee y reenvía.** No tiene acceso a la cuenta bancaria ni a Mercado Pago: lee la notificación que ya llega al celular (`NotificationListenerService` de Android). Por eso funciona con cualquier banco o billetera que avise los cobros. |
| C2 | **Solo Android.** iOS no deja que una app lea las notificaciones de otras. El negocio necesita un Android, que puede ser un celular viejo dedicado a esto. |
| C3 | **Lista cerrada de paquetes permitidos, administrada desde Dewoc.** La app solo reenvía notificaciones de los paquetes de la lista (`com.mercadopago.wallet`, Ualá, Cuenta DNI, Naranja X, Brubank, bancos). Las demás (WhatsApp, mensajes, etc.) **se descartan en el celular y nunca salen de él**. La lista se edita en Integraciones → Dewoc Cobros y el celular la recibe en `GET /estado` y en la respuesta de `/latido`: agregar un banco no requiere reinstalar el APK. El celular guarda la última lista recibida, para seguir filtrando sin conexión. |
| C4 | **Datos que se mandan a Dewoc:** `uuid`, paquete (el que informa Android en `StatusBarNotification.packageName`, que otra app no puede falsificar), título, texto, texto largo (`bigText`, si lo hay) y fecha y hora de la notificación. **El monto y el nombre no los saca el celular: los saca Dewoc** (C14). Son notificaciones de apps de pago, así que no agregan información sensible nueva. |
| C5 | **El aviso del POS no interrumpe.** No es un modal, no toma el foco del teclado ni del lector de códigos, no tapa el carrito ni el botón Cobrar, y no frena la venta. Se cierra con una ✕. |
| C6 | **El aviso queda a la vista hasta que alguien lo cierra**, para que no se pierda un pago por estar atendiendo. Después de 2 minutos se achica a una línea. Lo cerrado se puede volver a ver en "💰 Cobros recibidos". |
| C7 | **Cerrar el aviso vale solo para quien lo cierra.** Si hay varias cajas abiertas, cada cajero cierra el suyo. Queda guardado en el servidor, así que no reaparece al recargar la página. |
| C8 | **Un cobro recibido NO es un movimiento de caja.** Es solo un aviso. La plata entra a la caja como siempre, cuando se registra la venta con el medio de pago "Transferencia" o "Mercado Pago". Así no se cuenta dos veces. Asociar el aviso a una venta queda para el futuro (etapa 7); el **cruce** en el cierre de caja se adelanta a la etapa 4. |
| C9 | **Vinculación.** Primera versión (un solo comercio, un celular): en Integraciones → Dewoc Cobros se genera el token del dispositivo, se muestra **una sola vez** como QR y como texto, y la app lo escanea. Después (etapa 4): código de un solo uso que vence a los 10 minutos y se canjea por el token. En los dos casos se guarda **solo el hash**, igual que en `integracion_menuapp`, y cada dispositivo se puede revocar. **No se puede vincular más celulares que los que tiene la licencia** (C18). |
| C10 | **Si no hay conexión, la app no pierde notificaciones.** Las guarda en una cola local y las reenvía cuando vuelve internet. El `uuid` **se calcula con los datos de la notificación** (UUID v5 de paquete + clave de la notificación + `postTime`), no al azar: si Android vuelve a publicar la misma notificación, o la app se reinstala, sale el mismo `uuid` y Dewoc la descarta por repetida. |
| C11 | **Salud del celular.** La app manda un "latido" cada 5 minutos desde el servicio en primer plano (no con WorkManager, cuyo período mínimo es de 15 minutos). Si hay una caja abierta y Dewoc no recibe latidos hace más de 15 minutos, el POS muestra un aviso discreto: "El celular de cobros no está conectado". Así nadie confía en un aviso que no va a llegar. |
| C12 | **Quién ve los avisos:** cualquier usuario con permiso `ventas_pos` que tenga el POS abierto, el mismo criterio que los pedidos de MenuApp (D16 de `integracion-menuapp-checklist.md`). |
| C13 | **Distribución inicial por APK**, instalado a mano en el celular de Serendipia. Google Play queda para cuando se venda a muchos comercios (etapa 7), porque pide justificar el acceso a notificaciones. |
| C14 | **Los lectores viven en Dewoc, no en el celular.** El celular filtra por paquete (C3) y reenvía el texto. Dewoc elige el lector según el paquete y decide si es un cobro. Si una app cambia su formato, el arreglo es un deploy de Dewoc (no un APK nuevo) y las notificaciones que llegaron mientras tanto **se vuelven a leer** con el lector corregido, porque el texto original quedó guardado. |
| C15 | **Cuánto se guarda.** Las notificaciones `descartado` (promociones, pagos hechos por el negocio) se borran a los 7 días. El `texto` de los cobros se borra a los 90 días; el monto, el nombre y la fecha quedan. Lo hace un cron, como los demás de `cron/`. Son datos personales de clientes (Ley 25.326) y además lo va a pedir Google Play. |
| C16 | **Dewoc Cobros es un módulo que se cobra aparte** del plan de Dewoc. Sin licencia, el código está instalado pero **apagado**: la API responde que el módulo no está contratado, el POS no consulta ni muestra nada y en Integraciones → Dewoc Cobros solo se ve qué hace el módulo y el campo para cargar la licencia. |
| C17 | **Se habilita con una licencia firmada**, que emite el proveedor. El admin del comercio la recibe del proveedor y la pega en Integraciones → Dewoc Cobros, pero **no la puede fabricar**: Dewoc la verifica con una clave pública que está en el código, y la clave privada que firma está solo en la PC del proveedor, fuera del repositorio. Una vez cargada, **funciona sin conexión** con el proveedor hasta su vencimiento. |
| C18 | **Se cobra por celular vinculado.** La licencia dice cuántos celulares incluye (`dispositivos`). Con ese número de celulares activos, el botón de vincular queda deshabilitado ("Tu licencia incluye 2 celulares"). Si una licencia nueva trae menos celulares que los activos, siguen funcionando los **más viejos** y el resto queda "pausado por límite" hasta que se revoque alguno o se amplíe la licencia. |
| C19 | **La licencia está atada a la instalación** (`instalacion_id`, el UUID fijo que ya usa MenuApp) y al módulo: una licencia de otro comercio o de otro módulo se rechaza. Tiene un período (`desde`, `hasta`). Como funciona sin conexión, **no se puede revocar antes de que venza**: por eso se emite **por el período pagado** (por ejemplo, un mes) y se renueva con una licencia nueva cada vez que el comercio paga. Si hay varias válidas, manda la **emitida más recientemente**. |
| C20 | **Vencimiento: aviso y pausa.** Desde 7 días antes de `hasta`, aviso al admin en Integraciones y en el inicio. Después de `hasta`, **7 días de gracia**: sigue funcionando, con el aviso en rojo. Pasada la gracia, **pausa**: la API deja de aceptar notificaciones y latidos, y el POS deja de consultar y de mostrar avisos (tampoco el de salud). Lo ya recibido **sigue visible y exportable**, nunca se borra por la pausa. Al cargar la licencia nueva vuelve a funcionar **sin volver a vincular** los celulares. |
| C21 | **La capa de licencias es genérica**, no exclusiva de Dewoc Cobros: tabla `licencias` con columna `modulo` y la función `moduloEstado('dewoc_cobros')`. La van a usar los próximos módulos que se cobren aparte. |
| C22 | **Contratación automática** (cambió el 2026‑11‑02; antes era manual). Al tocar **"Contratar"** en Integraciones → Dewoc Cobros, ese Dewoc le pide la licencia al **servidor de licencias** (`POST /api/licencias/v1/solicitar`, en `erpcomercio.dewoc.com`) y queda habilitado al instante: **2 celulares por 30 días**. Cada Dewoc la **renueva solo** desde 7 días antes de que venza (al abrir Integraciones y con el cron `cron/licencias_renovar.php`). Lo único que frena la renovación es **dar de baja** al comercio en el panel del servidor; ahí también se cambia la cantidad de celulares. Nadie copia ni pega códigos. **Decisión de producto mínimo:** la clave privada vive en el hosting del servidor (`LICENCIAS_CLAVE_PRIVADA`, fuera de `public_html`) y cualquier Dewoc puede pedir una prueba; si el módulo escala, la firma vuelve a una PC del proveedor o a un servidor aparte. La carga manual de una licencia sigue disponible como alternativa. |

---

## 2. Cómo funciona (para no re‑investigar)

### En el celular

- **`NotificationListenerService`:** servicio de Android que recibe todas las notificaciones que se
  publican en el celular. El usuario lo habilita a mano en *Ajustes → Acceso a notificaciones*.
  Sin ese permiso la app no hace nada. Cada notificación trae el paquete de la app que la publicó,
  el título (`android.title`), el texto (`android.text`), a veces un texto largo (`android.bigText`)
  y la hora (`postTime`).
- **Lo único que decide el celular** es si el paquete está en la lista permitida (C3). No interpreta
  el texto.
- **Envío:** apenas llega la notificación se manda **directo** desde el servicio (OkHttp). Solo si ese
  envío falla queda en la cola de Room y la reenvía WorkManager con reintentos. Pasar todo por
  WorkManager agrega demora, porque Android lo posterga cuando el celular está en reposo (Doze).

### En Dewoc

- **Lectores por paquete:** cada paquete de la lista tiene asignado un lector (`mercadopago`, `uala`,
  …), que recibe título y texto y devuelve una de estas respuestas:

  | Resultado | Ejemplo | Qué hace Dewoc |
  |---|---|---|
  | `cobro` | "Juan Pérez te envió $ 12.500,50" | Guarda monto y nombre → aviso en el POS |
  | `descartado` | "¡Aprovechá 20% off…!", "Pagaste $ 3.000 en Kiosco" | Guarda, sin aviso; se borra a los 7 días |
  | `no_reconocido` | Un formato que el lector no conoce | Guarda, sin aviso; aparece en Integraciones para ajustar el lector |
  | `sin_lector` | Paquete agregado a la lista sin lector todavía | Igual que `no_reconocido` |

  **Los formatos reales salen del modo captura de la etapa 0, no se inventan.** Cada app y cada
  versión los escribe distinto.
- **Volver a leer:** después de corregir un lector, un botón en Integraciones pasa de nuevo por el
  lector las notificaciones `no_reconocido` y `sin_lector` de los últimos días. Las que pasan a
  `cobro` no generan aviso en el POS si tienen más de 12 horas (solo aparecen en la lista).
- **Montos argentinos:** `$ 12.500,50` → `12500.50`. El punto separa miles y la coma, decimales.

### Riesgos conocidos

- Algunas apps permiten **ocultar el monto** en las notificaciones o en la pantalla bloqueada. Hay que
  desactivarlo en el celular del negocio. Un cobro sin monto se muestra igual, con "monto oculto".
- Los fabricantes (Xiaomi, Samsung, Motorola) **cierran apps en segundo plano** para ahorrar batería.
  Hay que pedir la exclusión de la optimización de batería, llamar a `requestRebind()` en
  `onListenerDisconnected()` y usar un servicio en primer plano con una notificación fija
  ("Dewoc Cobros activo"). En Android 14 o superior ese servicio tiene que declarar su
  `foregroundServiceType`.
- Android puede **volver a publicar** la misma notificación (al actualizarla o al reiniciar). Por eso
  el `uuid` sale de los datos de la notificación (C10) y Dewoc lo tiene como `UNIQUE`.
- Si la app de MP o del banco **cambia el texto**, el lector deja de reconocerlo: aparecen
  `no_reconocido` en Integraciones, se corrige el lector en Dewoc y se vuelven a leer (C14).

---

## 3. Contrato de la API (lo que consume la app)

Base: `https://erpcomercio.dewoc.com/api/cobros/v1/`. JSON UTF‑8 y fechas ISO 8601 con la zona del
comercio. Se autentica con `Authorization: Bearer <token del dispositivo>`, salvo `/vincular`. Mismo
estilo que `ApiMenuAppController`: autenticación explícita al principio de cada acción y límite de
requests por minuto.

**Licencia (C16 a C20).** Después de validar el token, cada acción revisa `moduloEstado('dewoc_cobros')`
y el límite de celulares:

| Situación | `GET /estado` | `POST /notificaciones` y `POST /latido` |
|---|---|---|
| Licencia activa, por vencer o en gracia | `200` | normal |
| Sin licencia, vencida (pasada la gracia) o firma inválida | `200` con `licencia.estado` | `402 {"error": "modulo_no_habilitado", "estado": "vencida"}` |
| Celular fuera del límite de la licencia | `200` con `licencia.estado = "limite_dispositivos"` | `402 {"error": "limite_dispositivos"}` |

Con un `402` la app **no** encola ni reintenta: descarta la notificación, muestra "Dewoc Cobros está
pausado" y vuelve a consultar `GET /estado` cada 30 minutos. Cuando la licencia vuelve a estar activa,
sigue sola, sin volver a vincular.

### 3.1 `GET /estado` — configuración y estado del dispositivo

```json
// 200
{
  "comercio": "Heladería Serendipia",
  "dispositivo_activo": true,
  "licencia": { "estado": "activa", "hasta": "2026-12-31", "dispositivos": 2 },  // activa | por_vencer | gracia | vencida | sin_licencia | limite_dispositivos
  "caja_abierta": true,
  "paquetes_version": 4,
  "paquetes_permitidos": [
    { "paquete": "com.mercadopago.wallet", "nombre": "Mercado Pago" },
    { "paquete": "ar.com.uala",            "nombre": "Ualá" }
  ]
}
```
Campo opcional `app` con la última versión publicada del APK:
`"app": { "version_codigo": 4, "version": "1.0.1", "url": "https://…/dewoc-cobros-1.0.1.apk" }`. Si
`version_codigo` es mayor que el instalado, la app ofrece descargarla (`url` tiene que ser `https://`).
Se guarda en `cobros_config` y se actualiza al publicar cada versión.

La app lo llama al abrirse, al vincularse y cuando cambia `paquetes_version` (ver 3.3). Guarda la
lista localmente. Si el dispositivo fue revocado responde 401 y la app vuelve a la pantalla de
vinculación.

### 3.2 `POST /notificaciones` — notificación de una app de pago

```json
{
  "uuid": "0b6f3c1e-…",                    // UUID v5 de paquete + clave + postTime (C10)
  "paquete": "com.mercadopago.wallet",
  "titulo": "Recibiste una transferencia",
  "texto": "Juan Pérez te envió $ 12.500,50",
  "texto_largo": null,                      // android.bigText, si lo hay
  "recibido_at": "2026-11-01T15:42:10-03:00"  // postTime de la notificación
}
```
- `201` nueva, `200` ya existía (mismo `uuid`: no se duplica, la app la da por enviada). En los dos
  casos la respuesta trae lo que decidió el lector, para mostrarlo en la pantalla de la app:
  `{ "resultado": "cobro", "monto": 12500.50, "nombre": "Juan Pérez" }`.
- `422` datos inválidos. La app **no** lo reintenta y lo guarda en su registro local.
- Un paquete que ya no está en la lista se guarda igual como `sin_lector` (pudo haber llegado de la
  cola, con la lista vieja).
- **Notificación de prueba:** el botón de la app manda `paquete` = el de la propia app
  (`com.dewoc.cobros`, o `com.dewoc.cobros.prueba` en el APK de prueba). Se acepta aunque no esté en la
  lista, no pasa por lectores ni avisa en el POS, se responde `{ "resultado": "prueba" }` y se guarda con
  `resultado = 'prueba'` (se borra a los 7 días, con los `descartado`).

### 3.3 `POST /latido` — el celular sigue vivo

```json
// Request
{ "version_app": "1.0.0", "permiso_notificaciones": true, "optimizacion_bateria": false,
  "pendientes_en_cola": 0, "paquetes_version": 4 }
// 200
{ "paquetes_version": 4 }
```
`optimizacion_bateria: true` quiere decir que a la app **le falta** el permiso (Android puede frenarla
para ahorrar batería). Actualiza `ultimo_contacto_at` y el estado del dispositivo. Si la `paquetes_version` de la respuesta
es distinta de la que mandó la app, la app llama a `GET /estado` para traer la lista nueva.

### 3.4 `POST /vincular` — canjear el código de un solo uso (etapa 4)

```json
// Request
{ "codigo": "K7P2-9XQM", "nombre_dispositivo": "Moto G caja", "version_app": "1.0.0" }
// 200
{ "token": "dc_…(se muestra una sola vez)", "dispositivo_id": 3, "comercio": "Heladería Serendipia" }
```
401 si el código no existe, ya se usó o venció. El QR de código lleva la URL base y `codigo` en lugar de
`token`: `{"v": 1, "base": "https://erpcomercio.dewoc.com/api/cobros/v1/", "codigo": "K7P2-9XQM"}`. Si el
código se escribe a mano, la app usa la URL base por defecto.

---

## 4. Tareas

### Etapa 0 · Preparar y comprobar con datos reales

- [ ] 0.1 **Modo captura:** una primera versión de la app que solo guarde en un archivo, durante una
      semana, todo lo que llega de los paquetes de pago (paquete, título, texto, `bigText`, `postTime`,
      clave de la notificación y si fue una actualización). Exportarlo y pasarlo a Dewoc como base de los
      tests de los lectores. Reemplaza las capturas de pantalla, que no muestran el paquete ni cómo se
      reparte el texto.
- [ ] 0.2 Durante esa semana, hacer cobros reales de cada tipo (transferencia a alias o CVU, pago con QR,
      link de pago) desde cada app que use el negocio, y anotar cuáles fueron, para saber qué notificación
      es cuál. Que queden también ejemplos de lo que **no** es un cobro.
- [ ] 0.3 Revisar en esas apps que las notificaciones **muestren el monto** (riesgos de la sección 2).
- [ ] 0.4 **Probar la API de Mercado Pago:** comprobar si los webhooks o la búsqueda de pagos de la
      cuenta incluyen las **transferencias recibidas** al CVU o alias, con la demora. Si las incluyen,
      Mercado Pago puede funcionar sin celular y la app queda para las demás billeteras y bancos.
      Decidir antes de la etapa 1.
- [ ] 0.5 Elegir el celular del negocio (Android 8 o superior) y confirmar que queda prendido y con
      datos o wifi durante el turno.
- [ ] 0.6 Repositorio de la app: **https://github.com/mmarfeo/DewocCobros** (Kotlin, Android Studio).
      Se desarrolla en `C:\Users\Usuario\Documents\Proyectos\16-DewocCobros`, fuera de `htdocs`. Desde
      ahí sale también el "modo captura" de la tarea 0.1. El plan de la app está en `docs/PLAN.md` de esa
      carpeta; este checklist sigue siendo el plan completo y manda si hay diferencias.
  - Mantenerlo **privado**: el código es lo que se vende aparte (C16).
  - `.gitignore` de Android desde el primer commit: `local.properties`, `build/`, `.gradle/`, `*.jks`,
    `*.keystore` y `keystore.properties`. **La clave de firma del APK y las contraseñas nunca se suben**
    (tarea 2.8). Tampoco los archivos exportados del modo captura, que tienen nombres de clientes.
  - Mismo esquema de ramas que Dewoc: `main` para lo entregado y una rama de trabajo.

### Etapa 1A · Licencia del módulo: verificación en cada Dewoc (genérica, C16 a C21)

- [x] 1A.1 **Par de claves ECDSA P‑256**, generado una sola vez con la línea de comandos de `openssl`.
  - La **pública** va al código (1A.3).
  - La **privada** queda solo en la PC del proveedor, **fuera del repositorio y de `htdocs`** (por
    ejemplo `C:\Users\Usuario\Documents\Proyectos\01-Dewoc\claves\licencias_privada.pem`), con una
    copia de respaldo cifrada en otro lugar. Nunca se sube al servidor de un comercio. Si se pierde no se pueden emitir más licencias; si se filtra, cualquiera
    puede emitirlas y hay que cambiar de clave (1A.3).
- [x] 1A.2 **Formato:** `DWC1.<base64url(json)>.<base64url(firma)>`. El JSON lleva `kid` (qué clave la
      firmó), `licencia_id`, `instalacion_id`, `comercio`, `modulo` (`dewoc_cobros`), `dispositivos`,
      `desde`, `hasta` y `emitida_at`. La firma es ECDSA P‑256 con SHA‑256 sobre el JSON tal cual viene.
      Se verifica con `openssl_verify()`: está disponible en cualquier hosting, mientras que `sodium` no
      está habilitado en el PHP local (8.0.25).
- [x] 1A.3 `config/licencias_claves_publicas.php`: claves públicas por `kid`. Para cambiar de clave
      (por ejemplo si se filtra la privada) se agrega una nueva y se deja de aceptar la vieja.
- [x] 1A.4 Migración `database/licencias_migration.sql` (idempotente): `licencias` con `id`,
      `licencia_id` (**UNIQUE**), `modulo`, `texto` (la licencia completa, tal como se pegó),
      `dispositivos`, `desde`, `hasta`, `emitida_at`, `cargada_por`, `created_at`. Índice por
      `(modulo, emitida_at)`. **No hay columna "activa":** el estado se calcula siempre verificando la
      firma, así que editar la base a mano no habilita nada.
- [x] 1A.5 `core/Licencias.php` (verificar el formato y la firma, sin base de datos) y
      `app/Models/LicenciaModel.php` (cargar y elegir la vigente: la válida emitida más recientemente).
      La instalación sale de `IntegracionMenuAppModel::instalacionId()`, que ya genera ese UUID fijo y
      no lo cambia nunca.
- [x] 1A.6 Helpers en `core/helpers.php`: `moduloEstado(string $modulo): array` (`estado`, `hasta`,
      `dispositivos`, `dias_restantes`), con los estados `activa`, `por_vencer` (7 días antes),
      `gracia` (7 días después), `vencida` y `sin_licencia`, y `moduloHabilitado(string $modulo): bool`
      (verdadero en `activa`, `por_vencer` y `gracia`). El resultado se guarda durante la request, para
      no verificar la firma varias veces.
- [x] 1A.7 Entrada **"💰 Dewoc Cobros"** en el sidebar, en el grupo Integraciones de
      `app/Views/layouts/sidebar.php`, debajo de "Disponibilidad en el menú", con el mismo criterio de
      visibilidad (`canView('integraciones')` y rol admin). Rutas `integraciones/dewoc-cobros/*` en
      `index.php`, en un controlador propio, `IntegracionDewocCobrosController`, para no seguir sumando
      al `IntegracionesController` de MenuApp. Vista `app/Views/integraciones/dewoc-cobros.php`.
- [x] 1A.8 Pantalla **Integraciones → Dewoc Cobros**, pasos 1 y 2 del recorrido (sección 1):
  - Sin licencia: qué hace el módulo y **"Contratar"**, que muestra cómo contactar al proveedor
    (WhatsApp y correo) junto con el **código de instalación** y un botón para copiarlo, porque es lo
    que el proveedor necesita para emitir la licencia (C22). Abajo, el campo **"Cargar licencia"**.
  - Con licencia: estado, vigencia ("hasta el 31/12/2026"), celulares incluidos y usados ("1 de 2").
    Aviso amarillo en `por_vencer` y rojo en `gracia` y `vencida`. Campo para cargar la licencia nueva.
  - Al cargar: se rechaza con un mensaje claro si la firma no es válida, si es de otra instalación o de
    otro módulo, o si ya venció.
  - El ítem del sidebar lleva la etiqueta "Contratar" cuando no hay licencia.
- [ ] 1A.9 Aviso de vencimiento en el inicio (dashboard) para el admin, en `por_vencer`, `gracia` y
      `vencida`.
- [x] 1A.10 **Script para emitir licencias** (C22): `herramientas/emitir_licencia.php`, en el repositorio,
      que **solo corre por línea de comandos** (se niega si no es `php_sapi_name() === 'cli'`; además
      `herramientas/` se bloquea en el `.htaccess`, como `config/` y `core/`).
  - Recibe la ruta de la clave privada (que **no** está en el repositorio, 1A.1), el código de
    instalación, el comercio, la cantidad de celulares, `desde` y `hasta`.
  - Imprime la licencia lista para pegar y agrega una línea al registro de licencias emitidas, que
    también está fuera del repositorio, junto a la clave: a quién, cuántos celulares y qué período, para
    saber qué se vendió y cuándo hay que renovar.
- [ ] 1A.11 Emitir la licencia de **Serendipia** con ese script y cargarla en su Dewoc, como prueba
      completa del circuito.

### Etapa 1B · Licencias automáticas (C22)

- [x] 1B.1 Migración `database/licencias_servidor_migration.sql`: `licencias_clientes` (una fila por
      instalación y módulo, con la última licencia emitida, celulares y estado `activo`/`baja`).
- [x] 1B.2 `core/ServidorLicencias.php` (decide y firma) y `app/Models/LicenciaClienteModel.php`.
      Solo con `LICENCIAS_SERVIDOR=1` y `LICENCIAS_CLAVE_PRIVADA` en el `.env`.
- [x] 1B.3 `POST /api/licencias/v1/solicitar` (`ApiLicenciasController`): devuelve la licencia vigente
      si todavía le quedan más de 7 días, emite una nueva si no, y responde `403` si está de baja.
- [x] 1B.4 Cliente en `LicenciaModel`: `pedirAlServidor()` ("Contratar") y `renovarSiHaceFalta()`
      (Integraciones y `cron/licencias_renovar.php`, una vez por día). Nunca contrata por primera vez solo.
- [x] 1B.5 Pantalla: botón "Contratar", la carga manual queda como alternativa y, en el servidor, el
      panel de comercios (baja, reactivar, celulares).
- [x] 1B.6 Tests: `tests/unit/ServidorLicenciasTest.php`.
- [ ] 1B.7 En producción: subir la clave privada fuera de `public_html`, configurar el `.env`, correr la
      migración y programar el cron.

### Etapa 1 · Dewoc: núcleo (tablas, API, lector de Mercado Pago)

- [x] 1.1 Migración `database/dewoc_cobros_migration.sql` (idempotente, como las demás):
  - `cobros_dispositivos`: `id`, `nombre`, `token_hash` (SHA‑256), `token_ultimos4`, `activo`,
    `version_app`, `permiso_notificaciones`, `optimizacion_bateria`, `pendientes_en_cola`,
    `ultimo_contacto_at`, `creado_por`, `created_at`, `updated_at`.
  - `cobros_paquetes`: `id`, `paquete` (**UNIQUE**), `nombre_app`, `lector` (clave del lector en PHP,
    NULL = sin lector), `activo`, `created_at`, `updated_at`. Con Mercado Pago cargado de entrada.
  - `cobros_config`: `paquetes_version` (se incrementa cada vez que cambia `cobros_paquetes`) y la
    última versión publicada de la app (`app_version_codigo`, `app_version`, `app_url`).
  - `cobros_recibidos`: `id`, `uuid` (**UNIQUE**), `dispositivo_id`, `paquete`, `titulo`, `texto`,
    `texto_largo`, `recibido_at`, `resultado` (`cobro`, `descartado`, `no_reconocido`, `sin_lector`,
    `prueba`),
    `lector`, `monto` DECIMAL(12,2) NULL, `nombre` VARCHAR(120) NULL, `leido_at` (última vez que pasó
    por un lector), `turno_id` NULL (el turno abierto más viejo al llegar, como referencia),
    `venta_id` NULL (etapa 7), `created_at`. Índices por `(resultado, id)`, `created_at` y `recibido_at`.
  - `cobros_recibidos_cierres`: `cobro_id` + `usuario_id` (PK compuesta), `cerrado_at` (C7).
- [x] 1.2 `app/Models/CobroRecibidoModel.php`, `app/Models/CobroDispositivoModel.php` y
      `app/Models/CobroPaqueteModel.php`.
- [x] 1.3 **Lectores** en `app/Models/CobrosLectores/` (el proyecto no tiene carpeta de servicios):
      una interfaz común `leer(titulo, texto, textoLargo): resultado` y una clase por app. Empezar con
      **Mercado Pago**, armado con los datos del modo captura, con un test por cada formato real,
      incluidos los que se deben descartar. Función compartida para los montos argentinos.
- [x] 1.4 `app/Controllers/ApiCobrosController.php` con `GET /estado`, `POST /notificaciones` y
      `POST /latido`, enrutado en `index.php` igual que `api/menuapp/v1/` (bloque con `str_starts_with`,
      JSON de error, sin `Session` ni `Csrf`). El `.htaccess` ya reinyecta el header `Authorization`
      (regla de MenuApp): confirmar que también llega en `/api/cobros/v1/`.
- [x] 1.5 Límite de requests por dispositivo (60/min alcanza de sobra) y registro de cada llamada,
      **sin el token**. Revisión de la licencia y del límite de celulares en cada acción, con los `402`
      de la sección 3.
- [ ] 1.6 Pantalla **Integraciones → Dewoc Cobros** (la misma de 1A.8, en
      `IntegracionDewocCobrosController`), pasos 3 y 4 del recorrido (sección 1), versión mínima:
  - **Paso 3, habilitar el celular:** instrucciones cortas: 1) descargar e instalar el APK, 2) abrir
    "Dewoc Cobros" y dar los permisos, 3) tocar "Vincular celular" acá y escanear el QR con la app.
    Cuando el celular se conecta por primera vez, la pantalla lo muestra como "Activo" sin recargar
    (consulta cada pocos segundos mientras el QR está a la vista).
  - Todo lo de abajo solo aparece con la licencia habilitada. En `vencida`, la lista de notificaciones
    queda visible y exportable, pero no se puede vincular ni volver a leer.
  - "Generar token del dispositivo": lo muestra **una sola vez** como QR (con la URL base) y como texto.
    Contenido del QR: `{"v": 1, "base": "https://erpcomercio.dewoc.com/api/cobros/v1/", "token": "dc_…"}`.
    Deshabilitado si ya están vinculados todos los celulares de la licencia (C18).
  - Lista de dispositivos con estado (incluido "pausado por límite"), último contacto, versión,
    permiso de notificaciones y batería. Botón **Revocar**.
  - **Paquetes permitidos:** lista con nombre, paquete, lector asignado y activo. Agregar, editar,
    activar y desactivar. Cada cambio incrementa `paquetes_version`.
  - Últimas notificaciones recibidas con su resultado y su texto original, filtrables por resultado,
    para depurar los lectores. Botón **"Volver a leer"** para las `no_reconocido` y `sin_lector`.
  - Link para descargar el APK de la última versión publicada (tarea 2.8).

### Etapa 2 · App Android: versión mínima

- [ ] 2.1 Proyecto Kotlin, `minSdk 26` (Android 8). Pantallas mínimas:
  1. **Vincular:** escanear el QR de Dewoc (CameraX + ML Kit) o pegar el token.
  2. **Permisos:** guía paso a paso para habilitar el acceso a notificaciones y excluir la app de la
     optimización de batería, con el estado de cada permiso en verde o rojo.
  3. **Inicio:** comercio vinculado, "Activo / Sin conexión", últimas notificaciones enviadas con el
     resultado que devolvió Dewoc, cantidad en cola y botón "Enviar notificación de prueba".
- [ ] 2.2 `CobrosListenerService : NotificationListenerService`: filtra por la lista de paquetes
      guardada (C3), arma el `uuid` (C10) y manda la notificación **directo** (sección 2). Si falla, a la
      cola. `requestRebind()` en `onListenerDisconnected()`.
- [ ] 2.3 Cola local con Room: `uuid`, datos de la notificación y estado (pendiente, enviada,
      rechazada). Los reintentos los hace WorkManager, con espera creciente, apenas hay conexión (C10).
- [ ] 2.4 Lista de paquetes: se trae con `GET /estado` y se actualiza cuando cambia `paquetes_version`
      en la respuesta del latido. Se guarda localmente.
- [ ] 2.5 Token guardado en DataStore, cifrado con una clave del Android Keystore
      (`EncryptedSharedPreferences` está deprecado). Solo HTTPS.
- [ ] 2.6 Servicio en primer plano con una notificación fija "Dewoc Cobros activo"
      (`foregroundServiceType` declarado), y arranque automático al encender el celular
      (`BOOT_COMPLETED`).
- [ ] 2.7 Latido cada 5 minutos **desde el servicio en primer plano** (no WorkManager, C11), con el
      estado de los permisos.
- [ ] 2.8 Firmar y generar el APK de entrega. Guardar la clave de firma **fuera** del repositorio
      `DewocCobros`, con copia de respaldo: sin ella no se pueden publicar actualizaciones que se
      instalen encima de la versión anterior. El APK se publica como *release* de GitHub y en el link
      de descarga de Integraciones (1.6), nunca como archivo commiteado.

### Etapa 3 · Dewoc: aviso básico en el POS y primera prueba

- [x] 3.0 **Todo lo de esta etapa depende de `moduloHabilitado('dewoc_cobros')`:** sin licencia, el POS no
      incluye el parcial (no hay consulta ni botón) y los endpoints responden `403`.
- [ ] 3.1 Endpoints con sesión (usuario con `ventas_pos`):
  - `GET  cobros-recibidos/nuevos?desde_id=N` devuelve los `cobro` de las **últimas 12 horas** que el
    usuario no cerró. Consulta liviana con índice, porque se llama seguido. Llamar a
    `session_write_close()` apenas se valida el permiso: con sesiones en archivo, una consulta cada 5 s
    quedaría trabada detrás de cualquier request lento del mismo usuario (por ejemplo AFIP), y al revés.
  - `POST cobros-recibidos/cerrar` (`cobro_id`, CSRF) registra el cierre del usuario.
- [x] 3.2 Consulta en un parcial `app/Views/ventas/_cobros_recibidos.php`, incluido al final de
      `ventas/pos.php` como `_menuapp_pedidos.php`:
  - Cada **5 segundos** mientras la pestaña está visible y cada 30 cuando no lo está
    (`document.visibilityState`). Errores en silencio, como las otras consultas del POS: si falla, el POS
    sigue igual.
  - **Nunca** una consulta mientras otra está en curso (bandera), para no apilar requests si la red anda lenta.
- [x] 3.3 **Tarjeta básica** (C5 y C7): `position: fixed` en la **esquina inferior izquierda**, lejos del
      carrito y del botón Cobrar, con `z-index` por debajo de los modales de Bootstrap. Monto en
      `formatMoneda()`, nombre escapado con `esc()`, app de origen y hora. Se cierra con ✕.
      **No toma el foco:** sin `focus()`, sin `autofocus`, la ✕ con `tabindex="-1"` y sin atajos de
      teclado. Solo las tarjetas reciben clics (`pointer-events`): el resto deja pasar los clics al POS.
- [ ] 3.4 **Primera prueba en Serendipia** (antes de seguir puliendo):
  - Transferencias de prueba de montos chicos. Medir cuánto tarda desde la notificación hasta el aviso
    en el POS (objetivo: menos de 10 s).
  - Con el POS **en plena venta** (carrito cargado, buscando productos, con el lector de códigos):
    confirmar que el aviso no roba el foco, no tapa nada y que la ✕ funciona. Confirmar el lugar de la
    tarjeta con la pantalla real, también en tablet.

### Etapa 4 · Completar el aviso, el control y la vinculación

- [ ] 4.1 **Aviso completo** (C6):
  - Entra con una animación corta y sale con otra al tocar la ✕. **A los 2 minutos se achica** a una
    línea (`💰 $ 12.500,50 · Juan Pérez ✕`), que se vuelve a agrandar al pasar el mouse o tocarla. Hora
    relativa que se actualiza sola y color del medio de pago (los `--medio-*` del POS).
    ```
    ┌──────────────────────────────────┐
    │ 💰 $ 12.500,50                 ✕ │
    │ Juan Pérez                        │
    │ Mercado Pago · hace 20 s          │
    └──────────────────────────────────┘
    ```
  - **Máximo 3 tarjetas visibles.** Si hay más, un chip "+2 cobros más" que abre el panel. Con 2 o más
    tarjetas aparece "Cerrar todos" (`POST cobros-recibidos/cerrar-todos`).
  - Sonido corto opcional y **voz opcional** ("Cobro recibido: doce mil quinientos pesos de Juan Pérez",
    `speechSynthesis`), con un botón 🔔 del POS. La preferencia queda en `localStorage` para ese equipo
    (con try/catch). Si al abrir el POS ya hay cobros sin cerrar, se muestran **sin sonido**.
- [ ] 4.2 Botón **"💰 Cobros recibidos"** junto a "🛎 Pedidos de MenuApp", con un contador de los del
      turno. Abre un panel lateral (`offcanvas`, no modal) con la lista del día, cerrados incluidos
      (`GET cobros-recibidos/lista?fecha=`): hora, monto, nombre y app.
- [ ] 4.3 **Aviso de salud** (C11): si hay una caja abierta, un dispositivo activo y ningún latido hace más
      de 15 minutos, una línea discreta en el POS dice "📵 El celular de cobros no está conectado desde
      las 15:20". No se cierra con ✕: desaparece sola cuando vuelve el latido. Viaja en la respuesta de
      `cobros-recibidos/nuevos` como `salud`.
- [ ] 4.4 **Cruce en el cierre de caja:** reporte del turno *cobros avisados ↔ ventas registradas con
      "Transferencia" o "Mercado Pago"*, por monto y hora. Muestra las ventas por transferencia sin un
      cobro que las respalde (por ejemplo, cobradas en efectivo y registradas como transferencia) y los
      cobros sin venta. Sin el botón "Usar en esta venta" (eso queda para la etapa 7).
- [ ] 4.5 **Vinculación con código de un solo uso** (C9): `cobros_codigos_vinculacion` (`codigo_hash`,
      `vence_at`, `usado_at`, `creado_por`), `POST /vincular` (3.4), QR que vence a los 10 minutos, varios
      celulares y botón **Renombrar** en Integraciones.
- [ ] 4.6 Cron de limpieza (C15): borra los `descartado` de más de 7 días y el `texto` de los cobros de
      más de 90.
- [ ] 4.7 Opcional: juntar en un solo `pos/novedades` las consultas que el POS ya hace cada 30 s (tarea
      pendiente, solicitud de inventario, pedido de MenuApp, mensajes sin leer) con la de los cobros,
      para no sumar requests en el hosting compartido.

### Etapa 5 · Más orígenes y voz en el celular

- [ ] 5.1 Lectores de las otras apps que use el comercio (Ualá, Cuenta DNI, Naranja X, Brubank, bancos),
      cada uno con sus datos del modo captura y sus tests. Se agregan en Dewoc y se dan de alta en la
      lista de paquetes: **sin APK nuevo**.
- [ ] 5.2 Voz opcional **en el celular** (TextToSpeech de Android), para negocios sin el POS a la vista.
      Usa el `resultado`, `monto` y `nombre` que devuelve `POST /notificaciones`, así que solo habla con
      conexión. Se configura en la app y viene apagada por defecto, porque el aviso principal es el del POS.
- [ ] 5.3 Revisar en la app, cada vez que se abre, si Dewoc publicó una versión nueva del APK.

### Etapa 6 · Prueba completa en Serendipia

- [ ] 6.1 Dos cajas abiertas: cada una cierra su aviso por separado (C7).
- [ ] 6.2 Sin internet en el celular durante 10 minutos: al volver, llegan todas las notificaciones, sin
      duplicados.
- [ ] 6.3 Celular apagado o app cerrada: aparece el aviso de salud a los 15 minutos (C11).
- [ ] 6.4 Agregar un paquete nuevo en Integraciones y confirmar que el celular lo empieza a reenviar sin
      reinstalar nada (C3).
- [ ] 6.5 Licencia: emitir una que venza mañana y comprobar el aviso, la gracia y la pausa (la app muestra
      "pausado", el POS deja de avisar, el historial sigue visible). Cargar una nueva y confirmar que
      vuelve a funcionar sin volver a vincular. Emitir una con 1 celular teniendo 2 vinculados: el más
      nuevo queda "pausado por límite".
- [ ] 6.6 Una semana de uso real, revisando en Integraciones → Dewoc Cobros los `no_reconocido` y
      `sin_lector`, y el reporte de cruce de cada cierre.

### Etapa 7 · Futuro (sin programar)

- **Usar el cobro en la venta:** un botón "Usar en esta venta" en la tarjeta, que carga el monto en el
  medio de pago que corresponda y guarda `cobros_recibidos.venta_id`. Así ese cobro no se vuelve a
  ofrecer y el cruce de la etapa 4.4 pasa a ser exacto.
- **MenuApp:** confirmar solo los pedidos con pago por transferencia cuando llega un cobro con el mismo
  monto, siempre con la confirmación de una persona si hay más de un pedido posible.
- **Google Play** cuando Dewoc Cobros se venda a muchos comercios.
- **Cobro automático:** que el comercio pague Dewoc Cobros desde su propio Dewoc con una suscripción
  mensual de Mercado Pago a la cuenta del proveedor, y que la licencia de cada mes le llegue sola. Por
  ahora la contratación es manual (C22).
- **Multiempresa:** si Dewoc pasa a ser multiempresa (`propuesta-saas-multiempresa-chat-first.md`), la
  licencia se ata a la empresa en lugar de a la instalación.

---

## 5. Pruebas automáticas (en `tests/run.php`)

Licencia (con un par de claves de prueba generado para los tests, nunca el real):

- Una licencia bien firmada se acepta. Con un solo carácter cambiado en el JSON o en la firma, se rechaza.
- Se rechaza la de otra `instalacion_id`, la de otro módulo y la firmada con un `kid` desconocido.
- Estados según la fecha: `por_vencer` 7 días antes de `hasta`, `gracia` hasta 7 días después, y
  `vencida` pasado ese plazo. `moduloHabilitado()` es falso solo en `vencida` y `sin_licencia`.
- Con dos licencias válidas, manda la emitida más recientemente (aunque traiga menos celulares).
- Límite de celulares: con 2 activos y una licencia de 1, el más viejo funciona y el más nuevo recibe
  `402 limite_dispositivos`. No se puede generar un token nuevo con el límite completo.
- Con la licencia vencida, `POST /notificaciones` responde `402` y `GET /estado` responde `200` con
  `licencia.estado = "vencida"`.
- Que ningún archivo del repositorio contenga una clave privada (`BEGIN EC PRIVATE KEY`,
  `BEGIN PRIVATE KEY`).

Dewoc Cobros:

- Lector de Mercado Pago: un caso por cada formato real del modo captura, con su resultado esperado
  (`cobro` con monto y nombre, `descartado` o `no_reconocido`). Montos argentinos (`$ 12.500,50`,
  `$ 500`, `$ 1.000.000,00`).
- `POST /notificaciones` con el mismo `uuid` dos veces genera un solo registro (responde `201` y después
  `200`).
- Un paquete sin lector o fuera de la lista se guarda como `sin_lector` y no aparece en `nuevos`.
- "Volver a leer" convierte un `no_reconocido` en `cobro` cuando el lector ya lo entiende.
- Cambiar la lista de paquetes incrementa `paquetes_version`, y `GET /estado` devuelve la lista nueva.
- Token revocado o inexistente responde 401.
- `cobros-recibidos/nuevos` no devuelve los que el usuario cerró, pero sí a otro usuario. Los
  `descartado` y `no_reconocido` no aparecen.
- Salud: dispositivo activo, caja abierta y último latido hace 16 minutos dan `salud.desconectado=true`.
  Sin caja abierta, no hay aviso.
