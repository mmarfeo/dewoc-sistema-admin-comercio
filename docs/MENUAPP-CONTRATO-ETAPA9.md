# Contrato Dewoc ↔ MenuApp — etapa 9, lo que quedó implementado

> **Para quien está desarrollando el lado de MenuApp.** Esto NO reemplaza a
> `docs/integracion-menuapp-checklist.md`, que es el plan y la fuente de verdad: acá está
> lo que efectivamente quedó codeado en Dewoc, con los nombres, los formatos y los códigos
> exactos, para poder probar la conexión sin leer el código PHP.
>
> Base de Dewoc en producción: `https://erpcomercio.dewoc.com`
>
> **Nada de esto está probado de punta a punta todavía:** se escribió contra el checklist.
> Si algo no coincide con lo que espera MenuApp, lo que manda es el checklist — avisá y lo
> corrijo de este lado.

---

## 1. La firma: empezá por acá

Es lo que más barato es equivocar y más caro es diagnosticar, porque un desajuste se ve
como "firma inválida" sin decir dónde está el problema.

### Vector de prueba reproducible

```
secreto    = "secreto-compartido"
timestamp  = "1760000000"
cuerpo     = {"usuario":{"id":7,"nombre":"Juan"}}

a firmar   = 1760000000.{"usuario":{"id":7,"nombre":"Juan"}}
             ^^^^^^^^^^^ timestamp, luego un punto, luego el cuerpo CRUDO

X-Signature = d3383d3b56d0ed4330f7cc10802b50e7d009ca36a0e8a2cf40aa37921a01098e
```

En PHP: `hash_hmac('sha256', $timestamp . '.' . $raw, $secreto)` — hexadecimal, 64
caracteres, minúsculas.

Si con esos tres valores tu validador saca otra cosa, el problema está en uno de los dos
lados y no hay que adivinar cuál. Del lado de Dewoc esto está fijado en
`tests/unit/MenuAppPedidosTest.php::testElVectorDeFirmaEsEstable()`.

**Dos cosas que importan:**

- El cuerpo se firma **crudo**, byte por byte, y se envía ese mismo string. Si lo
  re-serializás antes de validar, cualquier diferencia de orden de claves o de escape de
  acentos rompe la firma. Dewoc usa `json_encode(..., JSON_UNESCAPED_UNICODE)`.
- En un `GET` el cuerpo es **string vacío**, así que se firma `"<timestamp>."` (el punto
  queda igual).

### Headers que manda Dewoc en cada llamada

| Header | Contenido |
|---|---|
| `X-Dewoc-Instalacion` | el UUID fijo de la instalación (`integracion_menuapp.instalacion_id`) |
| `X-Timestamp` | unix en segundos |
| `X-Signature` | el HMAC de arriba |
| `Content-Type` | `application/json; charset=utf-8` — **solo** cuando hay cuerpo |
| `User-Agent` | `Dewoc/MenuAppClient` |

El secreto es el **mismo** que ya comparten para los avisos
(`integracion_menuapp.webhook_secret`): no hay nada nuevo que configurar.

El `instalacion_id` es un UUID v4 que se genera una sola vez y **no cambia nunca**, ni al
regenerar el token. Lo podés ver en Dewoc en `GET /api/menuapp/v1/estado` o en la pantalla
Integraciones → MenuApp.

---

## 2. Lo que Dewoc EXPONE (MenuApp → Dewoc)

Auth: `Authorization: Bearer <token>`, el token que se genera en Integraciones → MenuApp.
Límite: 120 requests por minuto. Todo responde JSON en UTF-8.

### Nuevos en la etapa 9

#### `POST /api/menuapp/v1/pedidos`

Pedido nuevo para atender en el POS. **No registra ninguna venta ni toca el stock**: es
solo la alerta. La venta se registra al aceptar, y la registrás vos llamando a
`POST /ventas`.

Cuerpo: el de la sección 3.7 del checklist, tal cual.

| Respuesta | Cuándo |
|---|---|
| `201 {pedido_id, referencia}` | se creó |
| `200 {pedido_id, referencia}` | ya existía (reintento) — **no duplica la alerta ni reabre una ya atendida** |
| `422 {error: "datos_invalidos", detalle}` | falta `referencia` (máx. 40) o `items` está vacío |
| `401 {error: "no_autorizado"}` | token inválido o integración en pausa |

Obligatorios: `referencia`, `items`. El resto se guarda tal como llegue y se muestra en el
POS, así que podés agregar campos sin que haya que migrar nada de este lado.

#### `POST /api/menuapp/v1/pedidos/{referencia}/novedades`

| `tipo` | Campos que lee Dewoc | Qué hace |
|---|---|---|
| `estado` | `estado` (obligatorio), `estado_texto` | actualiza el pedido. **Si `estado` ≠ `esperando_aprobacion`, saca la alerta** |
| `mensaje` | `conversacion` (`cliente`\|`repartidor`), `de`, `texto` | suma 1 al contador de no leídos + push. **El texto NO se guarda** |
| `repartidor` | `repartidor` (nombre, o vacío para quitarlo) | habilita la conversación con el repartidor |

| Respuesta | Cuándo |
|---|---|
| `200 {ok: true, referencia, tipo}` | aplicada |
| `404 {error: "pedido_inexistente"}` | la referencia no está en Dewoc — ignoralo |
| `422 {error: "datos_invalidos", detalle}` | `tipo` desconocido, falta `estado`, o `conversacion` inválida |

**Importante sobre el estado:** `requiere_accion` se deriva del estado, no se manda aparte.
Alcanza con avisar el cambio para que la alerta del POS se vaya — incluido el caso de que el
pedido se acepte **desde MenuApp** y no desde el POS.

### Los que ya existían (etapas 0 a 6)

```
GET  /api/menuapp/v1/estado
GET  /api/menuapp/v1/catalogo[?desde=ISO8601]
GET  /api/menuapp/v1/disponibilidad
POST /api/menuapp/v1/ventas
POST /api/menuapp/v1/ventas/{referencia}/cobrar
POST /api/menuapp/v1/ventas/{referencia}/anular
```

Recordá de `POST /ventas`: es idempotente por `referencia`, se chequea **primero** la
idempotencia y **después** la caja (un reintento de algo aceptado con la caja abierta
devuelve la venta aunque la caja ya esté cerrada), y responde `409 caja_cerrada` o
`409 sin_stock` sin registrar nada.

---

## 3. Lo que Dewoc CONSUME (Dewoc → MenuApp)

Lo implementa `core/MenuAppClient.php`. Base configurable en Integraciones → MenuApp;
por defecto `https://menuapp.dewoc.com/api/dewoc/v1/`. Timeout de 10 segundos.

Todas las acciones mandan `usuario: {id, nombre}` — el cajero. No necesita existir en
MenuApp; se espera que lo muestres como "Caja · Juan".

| Llamada | Cuerpo que manda Dewoc |
|---|---|
| `GET pedidos/{ref}` | — |
| `POST pedidos/{ref}/aceptar` | `{usuario}` |
| `POST pedidos/{ref}/rechazar` | `{motivo, usuario}` |
| `POST pedidos/{ref}/proponer` | `{items, motivo, usuario}` |
| `GET pedidos/{ref}/mensajes?conversacion=…&despues=N` | — |
| `POST pedidos/{ref}/mensajes` | `{conversacion, texto, usuario}` — el texto ya viene cortado a 500 |
| `GET respuestas-rapidas` | — |

`items` de **proponer**, con IDs de Dewoc:

```json
[{ "producto_id": 345, "cantidad": 1,
   "opciones": [{ "opcion_id": 71, "cantidad": 1 }],
   "notas": null }]
```

Dewoc **no valida** los mínimos y máximos de cada grupo de gustos: eso lo validás vos, que
sos el dueño de esas reglas. Duplicar la validación acá daría dos verdades que se
desincronizan. Mandá `422` con un `mensaje` entendible y el POS lo muestra tal cual.

### Qué espera Dewoc de tus respuestas

- **Éxito (2xx):** si devolvés `estado` y `estado_texto`, Dewoc los usa para actualizar su
  copia; si no, cae a un valor por defecto. En `proponer`, si devolvés `total` se le muestra
  al cajero. En `mensajes`, acepta `{mensajes: [...]}` o directamente el array; cada mensaje
  se muestra con `de`, `texto` y `enviado_en`.
- **Error:** Dewoc lee `error` (o `codigo`) para el identificador y `mensaje` (o `detalle`)
  para el texto. **Si mandás `mensaje`, es el que ve el cajero** — preferilo a dejar que
  Dewoc arme uno genérico.
- `sin_stock` y `caja_cerrada` se tratan especial: el pedido **sigue esperando acción** y la
  alerta vuelve, para que el comercio lo edite o lo rechace.
- `GET respuestas-rapidas` espera `{cliente: [...], repartidor: [...]}`. Si falla, el POS
  deja escribir a mano: no es bloqueante.

---

## 4. Cómo probar la conexión

Sugerencia de orden, de lo más simple a lo más acoplado:

1. **La firma**, con el vector de arriba, en los dos lados. Sin esto nada más funciona.
2. **`GET /api/menuapp/v1/estado`** desde MenuApp: valida el token, te da el
   `instalacion_id` y te dice si la caja está abierta.
3. **`POST /api/menuapp/v1/pedidos`** con el ejemplo de 3.7. Mandalo **dos veces**: la
   segunda tiene que dar `200` y en el POS tiene que haber **una sola** alerta.
4. **`POST .../novedades`** con `tipo: "estado"` y un estado cualquiera distinto de
   `esperando_aprobacion`: la alerta tiene que desaparecer del POS.
5. **Aceptar desde el POS** → tiene que llegarte el `POST pedidos/{ref}/aceptar` firmado.
   Devolvé `409 {"error":"sin_stock","mensaje":"..."}` para comprobar que el POS muestra tu
   mensaje y el pedido sigue esperando.
6. **Las conversaciones y las respuestas rápidas.**

**Antes de empezar, de este lado hace falta:**

- correr `database/menuapp_pedidos_migration.sql` (y las dos migraciones anteriores de
  MenuApp y de fotos, si todavía no se corrieron);
- tener la integración **activa** con el token generado y el secreto cargado;
- tener una caja abierta, porque `POST /ventas` la exige (D10).

---

## 5. Lo que queda del lado de Dewoc, por si hace falta tocarlo

| Archivo | Qué es |
|---|---|
| `core/MenuAppClient.php` | el cliente firmado (3.9 y 3.10) |
| `app/Controllers/ApiMenuAppController.php` | todo lo que Dewoc expone, incluido `pedidos()` y `novedades()` |
| `app/Controllers/MenuAppPedidosController.php` | el lado POS: aceptar, rechazar, proponer, chat |
| `app/Models/MenuAppPedidoModel.php` | la copia local de los pedidos |
| `app/Views/ventas/_menuapp_pedidos.php` | la UI del POS |
| `database/menuapp_pedidos_migration.sql` | las dos tablas + `api_base_url` |
| `tests/unit/MenuAppPedidosTest.php` | 88 aserciones, incluido el vector de firma |
