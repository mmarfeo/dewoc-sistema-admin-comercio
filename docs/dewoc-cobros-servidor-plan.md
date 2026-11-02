# Dewoc Cobros: lo que la app necesita de Dewoc

> Plan del **lado servidor**, ordenado según lo que necesita la app del celular para funcionar de punta a
> punta. Redactado el 2026‑11‑02.
>
> - **El detalle de cada tarea está en [`dewoc-cobros-checklist.md`](dewoc-cobros-checklist.md)**, y ese
>   checklist manda si hay diferencias. Este documento no repite tablas ni pantallas: dice **en qué orden**
>   hacerlas, **cuándo está lista cada fase** (probándola con la app) y **qué se agregó al contrato** a
>   partir de la app ya programada.
> - App: `C:\Users\Usuario\Documents\Proyectos\16-DewocCobros` · repo `mmarfeo/DewocCobros` · plan en
>   `docs/PLAN.md`. Cualquier cambio en el contrato de la API se hace en los tres documentos.

---

## 1. Dónde está la app

La app ya consume todo el contrato de la sección 3 del checklist y **está esperando al servidor**:

| Qué hace la app | Endpoint que usa | Estado en la app |
|---|---|---|
| Vincula escaneando el QR (o pegando el token) y prueba la conexión antes de guardar | `GET /estado` | Listo |
| Muestra comercio, estado de la licencia, apps permitidas; filtra por esa lista | `GET /estado` | Listo |
| Manda cada notificación de una app de pago apenas llega, con `uuid` v5 | `POST /notificaciones` | Listo |
| Cola local: si falla, reintenta con espera creciente cuando hay red | `POST /notificaciones` | Listo |
| Muestra lo que respondió Dewoc ("Cobro $ 10 · Daniela") y dice el cobro en voz alta (opcional) | respuesta de `POST /notificaciones` | Listo |
| Latido cada 5 minutos; si cambia `paquetes_version` vuelve a pedir la lista | `POST /latido` | Listo |
| `401` → vuelve a vincular · `402` → pausa y consulta `GET /estado` cada 30 min | todos | Listo |
| Vincula con código de un solo uso | `POST /vincular` | Listo |
| Avisa que hay una versión nueva del APK | `GET /estado` (campo nuevo `app`, 3.4) | Listo |

Hoy se prueba con un **modo demo** (solo en el APK de prueba) que imita las respuestas. Con el servidor
listo, la misma app se vincula de verdad.

---

## 2. Fases, en orden

Cada fase termina con una prueba hecha **con la app en el celular**. Entre paréntesis, las tareas del
checklist.

### Fase A · Licencia del módulo (1A.1 a 1A.6, 1A.10)

Hace falta primero porque **cada endpoint de la API revisa la licencia** (C16 a C20).

- **Lista cuando:** en el Dewoc local, con una licencia de prueba emitida con el script,
  `moduloEstado('dewoc_cobros')` devuelve `activa`, y los tests de licencia de la sección 5 del checklist
  pasan.
- La pantalla completa de Integraciones (1A.7 a 1A.9) puede esperar: alcanza con poder cargar la licencia.

### Fase B · Vincular y `GET /estado` (1.1, 1.2, 1.4 parcial, 1.6 parcial)

- Migración completa (1.1) y modelos (1.2).
- `ApiCobrosController` con autenticación por token y **solo `GET /estado`** (1.4), enrutado como
  `api/menuapp/v1/` en `index.php` (`str_starts_with`, sin `Session` ni `Csrf`). Confirmar que el
  `Authorization` llega también por `/api/cobros/v1/` (regla del `.htaccess`).
- En Integraciones → Dewoc Cobros, solo el botón **"Generar token del dispositivo"** con el QR (1.6).
- Mercado Pago cargado en `cobros_paquetes`.
- **Lista cuando:** la app escanea el QR, muestra **"Activo"** con el nombre del comercio y la lista
  "Mercado Pago". Con un token inventado, la app dice "Dewoc no reconoce este código".

### Fase C · Recibir notificaciones y lector de Mercado Pago (1.3, 1.4)

- `POST /notificaciones` con la respuesta `resultado` / `monto` / `nombre`, `201` nueva y `200` repetida.
- Lector de Mercado Pago armado con las **capturas reales** (sección 5), con un test por formato.
- Notificación de prueba (3.2).
- **Lista cuando:**
  - una transferencia real a Mercado Pago aparece en la app como **"Cobro $ 10 · Daniela"** en menos de
    unos segundos, y queda en `cobros_recibidos`;
  - el botón **"Enviar notificación de prueba"** de la app muestra "Recibida por Dewoc";
  - la misma notificación mandada dos veces deja un solo registro.

### Fase D · Latido, límite de requests y pantalla de dispositivos (1.4, 1.5, 1.6)

- `POST /latido` que actualiza el dispositivo; límite de 60 requests por minuto; registro de llamadas
  **sin el token**.
- Lista de dispositivos en Integraciones, con último contacto, versión, permisos y batería, y
  **Revocar**. ABM de paquetes permitidos que incrementa `paquetes_version`.
- **Lista cuando:**
  - a los 5 minutos de abrir la app, Integraciones muestra el celular con su último contacto, versión y
    permisos;
  - agregar Ualá a la lista hace que la app la muestre en "Apps de pago que se reenvían" en el latido
    siguiente, sin tocar el celular;
  - **Revocar** el dispositivo hace que la app vuelva sola a la pantalla de vincular.

### Fase E · Pausa por licencia (1.5)

- `402` en `POST /notificaciones` y `POST /latido` según la tabla de la sección 3 del checklist.
- **Lista cuando:** con una licencia vencida (pasada la gracia), la app muestra "Dewoc Cobros está pausado";
  al cargar una licencia nueva, a los 30 minutos como mucho vuelve a "Activo" **sin volver a vincular**.
  Con una licencia de 1 celular y 2 vinculados, el más nuevo muestra "Este celular supera los incluidos en
  la licencia".

### Fase F · Aviso en el POS (etapas 3 y 4 del checklist)

No cambia nada en la app. Se puede hacer apenas termina la fase C.

### Fase G · Vincular con código y versión de la app (4.5 y 3.4 de este documento)

- `POST /vincular` con el QR de código (3.3).
- Publicar la versión de la app en `GET /estado` (3.4).
- **Lista cuando:** la app se vincula escaneando el QR con código; un código usado o vencido da "El código
  no existe, ya se usó o venció". Al publicar un `version_codigo` mayor que el instalado, la app muestra
  "Hay una versión nueva" con el botón de descarga.

---

## 3. Contrato: lo que se agrega o se aclara

Todo esto ya está programado así en la app. Va también a la sección 3 del checklist y a la sección 4 de
`PLAN.md`.

### 3.1 `optimizacion_bateria` en el latido

`true` quiere decir que **le falta** el permiso: Android todavía puede frenar la app para ahorrar batería.
En Integraciones conviene mostrarlo como problema ("La batería puede cerrar la app").

### 3.2 Notificación de prueba

El botón "Enviar notificación de prueba" de la app manda un `POST /notificaciones` normal con:

```json
{ "uuid": "…", "paquete": "com.dewoc.cobros", "titulo": "Prueba de Dewoc Cobros",
  "texto": "Notificación de prueba enviada desde el celular", "texto_largo": null,
  "recibido_at": "2026-11-02T10:55:12-03:00" }
```

- `paquete` es el de la propia app: `com.dewoc.cobros`, o `com.dewoc.cobros.prueba` en el APK de prueba.
  **No está en la lista de paquetes permitidos y se acepta igual.**
- No pasa por ningún lector. Se responde `{ "resultado": "prueba" }` (`201`), no genera aviso en el POS y
  en Integraciones se ve como "Prueba recibida a las 10:55".
- Se guarda en `cobros_recibidos` con `resultado = 'prueba'` (hay que agregar ese valor) y se borra con los
  `descartado`, a los 7 días.

### 3.3 Vincular con código de un solo uso

El QR de código lleva la URL base, igual que el de token, pero con `codigo` en lugar de `token`:

```json
{ "v": 1, "base": "https://erpcomercio.dewoc.com/api/cobros/v1/", "codigo": "K7P2-9XQM" }
```

- La app canjea el código con `POST /vincular` (sección 3.4 del checklist) y después prueba el token con
  `GET /estado` antes de guardarlo.
- Si el código se **escribe a mano** (formato `XXXX-XXXX`), la app usa la URL base por defecto,
  `https://erpcomercio.dewoc.com/api/cobros/v1/`. Para otro dominio hay que escanear el QR.
- `nombre_dispositivo` es el nombre del celular en Android (por ejemplo "Galaxy A06").
- `401` si el código no existe, ya se usó o venció. Cualquier otro error, la app lo muestra como "no se
  pudo conectar".

### 3.4 Versión publicada de la app (nuevo, en `GET /estado`)

Campo opcional `app` en la respuesta de `GET /estado`:

```json
"app": { "version_codigo": 4, "version": "1.0.1", "url": "https://erpcomercio.dewoc.com/descargas/dewoc-cobros-1.0.1.apk" }
```

- `version_codigo` es el `versionCode` del APK (entero que sube en cada versión). Si es mayor que el
  instalado, la app muestra "Hay una versión nueva (1.0.1)" con un botón que abre `url` en el navegador.
- `url` tiene que ser `https://`; si no, la app no muestra el botón.
- Se guarda en `cobros_config` (`app_version_codigo`, `app_version`, `app_url`) y se carga a mano al
  publicar cada release de GitHub. Es el mismo link de descarga de Integraciones (1.6).
- Si no se manda el campo, la app no avisa nada.

### 3.5 Detalles que la app ya da por hechos

- **`recibido_at`** viene con la zona del celular (`-03:00`). Guardarlo convertido a la del comercio.
- `titulo`, `texto` y `texto_largo` pueden venir `null`.
- La app **ignora los campos que no conoce**: se pueden agregar campos a las respuestas sin romperla.
- `licencia.estado` desconocido: la app no muestra aviso y sigue enviando. Para pausarla, `402`.
- El `uuid` es un UUID v5 con un namespace fijo de la app. **Dewoc solo lo compara** (`UNIQUE`): no hace
  falta validarlo ni recalcularlo.
- **Responder rápido** (menos de 3 s): la app espera hasta 15 s y, si no llega respuesta, la deja en la
  cola. Los lectores no deben hacer llamadas externas.
- `422`: la app guarda los primeros 200 caracteres del cuerpo como motivo y **no** reintenta. Usar un
  cuerpo corto, por ejemplo `{"error": "uuid_invalido"}`.
- `429`: la app reintenta con su propia espera creciente; un `Retry-After` no hace daño pero no se usa.
- El token puede tener cualquier carácter salvo espacios. Se recomienda el prefijo `dc_`.

---

## 4. Cómo probar contra el Dewoc local

La app de entrega **solo acepta `https://`**. El **APK de prueba** ("Dewoc Cobros (prueba)") acepta
también `http://`, para poder apuntarla al XAMPP de la PC:

1. Celular y PC en la **misma red wifi**. Averiguar la IP de la PC (`ipconfig`, por ejemplo
   `192.168.0.10`).
2. Abrir el puerto 80 de Apache en el firewall de Windows para la red privada.
3. Generar el QR con la base de la PC. En local, `BASE_URL` es `http://localhost/…`, que el celular no
   puede abrir: el QR tiene que llevar la IP, por ejemplo
   `{"v":1,"base":"http://192.168.0.10/01-Dewoc-Sist-Admin-Comercio/api/cobros/v1/","token":"dc_…"}`.
   Conviene que la pantalla tome la base de una constante que en local se pueda cambiar.
4. Escanear con el APK de prueba.

Para probar sin el celular:

```bash
BASE=http://localhost/01-Dewoc-Sist-Admin-Comercio/api/cobros/v1
TOKEN=dc_...

curl -s -H "Authorization: Bearer $TOKEN" $BASE/estado

curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"uuid":"0b6f3c1e-1111-5222-8333-444455556666","paquete":"com.mercadopago.wallet","titulo":"Recibiste una transferencia","texto":"Daniela te envió $ 10","texto_largo":null,"recibido_at":"2026-11-02T11:30:00-03:00"}' \
  $BASE/notificaciones

curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"version_app":"0.4.0","permiso_notificaciones":true,"optimizacion_bateria":false,"pendientes_en_cola":0,"paquetes_version":1}' \
  $BASE/latido
```

---

## 5. Datos para el lector de Mercado Pago

- La **app de captura** (`v0.1.0-captura`, la que está instalada en el celular) guarda todo lo que llega
  de Mercado Pago en un archivo JSONL. Con **"Exportar"** se manda por correo o WhatsApp.
- Cada línea trae los campos de Android tal cual: `packageName`, `key`, `postTime`, `android.title`,
  `android.text`, `android.bigText`, `android.subText`, `android.textLines`, `category` y
  `actualizacion`.
- Al exportar, anotar qué fue cada cobro (por ejemplo "10:47, transferencia de Daniela, $ 10").

**Primeros formatos reales de Mercado Pago** (2026‑11‑02, celular Galaxy A06 con Android 16; los nombres
están cambiados):

| Título | Texto (igual en `texto_largo`) | Resultado esperado |
|---|---|---|
| `Recibiste $ 10` | `De Nombre Apellido desde su cuenta de Mercado Pago.` | `cobro`, monto `10.00`, nombre `Nombre Apellido` |
| `Tenés una factura que vence el 4/nov` | `Ya podés agendar o hacer tu pago de $ 100.690,49 a Edesur.` | `descartado` (tiene un monto, pero no es un cobro) |
| `¡Llegó el Cyber Monday! 🛍️` | `Hasta 50% OFF y 24 cuotas sin interés en tus tiendas favoritas 🤩` | `descartado` |

- En el cobro, **el monto está en el título y el nombre en el texto**. El lector tiene que mirar los dos.
- El de la factura muestra que **no alcanza con encontrar un `$`**: el lector tiene que reconocer la frase
  del cobro ("Recibiste …").
- El nombre viene completo (nombres y apellido) y con tildes: guardarlo tal cual, en UTF‑8.
- El archivo tiene nombres de clientes: **no se commitea**. Los tests del lector usan los textos con los
  nombres cambiados.

---

## 6. Resumen por tarea del checklist

| Fase | Tareas del checklist | Bloquea en la app |
|---|---|---|
| A | 1A.1 a 1A.6, 1A.10 | Todo (la API revisa la licencia) |
| B | 1.1, 1.2, 1.4 (`/estado`), 1.6 (token y QR) | Vincular |
| C | 1.3, 1.4 (`/notificaciones`), 3.2 de este documento | Cobros en la app y voz |
| D | 1.4 (`/latido`), 1.5, 1.6 (dispositivos, paquetes) | Salud, lista de paquetes, revocar |
| E | 1.5 (`402`) | Modo pausado |
| F | Etapas 3 y 4 | Nada (es el POS) |
| G | 4.5, 3.3 y 3.4 de este documento | Vincular con código, aviso de versión nueva |
