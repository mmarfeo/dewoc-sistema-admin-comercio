# Dewoc — Sistema de Administración de Comercios
## Documento de contexto para integración con otro proyecto

> Objetivo de este documento: que otra IA (u otro dev) entienda **qué contiene** este
> proyecto y **cómo está construido**, para poder integrarlo o reutilizar partes en otro
> sistema. Generado por inspección del código a fecha 2026‑09‑08.

---

## 1. Qué es

ERP / sistema de gestión web para comercios pequeños y medianos de Argentina (el caso
real es una heladería/almacén repostero, "Serendipia"). Cubre el ciclo completo:
compras → stock → producción (recetas) → venta (POS) → caja → cobros/pagos →
reportes → contabilidad básica → ecommerce → nómina de empleados. Incluye un **Chat IA**
que traduce preguntas en lenguaje natural a SQL y a acciones sobre el sistema.

- **Idioma del código y la UI:** español (nombres de clases, métodos, columnas, rutas).
- **Zona horaria:** `America/Argentina/Buenos_Aires` (sin DST). Se fuerza en PHP y en la
  conexión MySQL (`time_zone = '-03:00'`) porque hosting compartido no suele tener
  cargadas las tablas de zonas de MySQL.
- **Un solo comercio por instalación** (single‑tenant). Existe una propuesta de
  multiempresa en `docs/propuesta-saas-multiempresa-chat-first.md`, no implementada.
- **Versión:** `APP_VERSION = 1.0.0`. Un único commit inicial en git (`455ee7c`).

---

## 2. Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Lenguaje | **PHP 8.0+** (usa `declare(strict_types=1)`, tipos de retorno `never`, enums de unión) |
| Base de datos | **MySQL 8.0 / MariaDB** vía **PDO** con prepared statements, `ATTR_EMULATE_PREPARES=false` |
| Arquitectura | **MVC propio, sin framework**. Front controller + router de arreglo. |
| Dependencias Composer | `phpoffice/phpspreadsheet ^2.1` (export Excel), `lbuchs/webauthn ^2.2` (login biométrico). **Nada más.** |
| Frontend | Tema **Dashly** (Bootstrap 5) — `assets/css/theme.bundle.css`. jQuery 3.7, DataTables 1.13, Tabler Icons, Chart.js. Todo por CDN salvo el bundle del tema. |
| JS propio | `assets/js/app.js` (wrapper de `fetch`, manejo de sesión expirada, helpers de UI). Sin build step, sin bundler, sin npm. |
| PWA | `manifest.json` + `sw.js` (service worker, cache‑first para assets / network‑first para páginas) + `offline.html`. |
| IA | **Google Gemini** (API de AI Studio, no Vertex). Modelos de texto, imagen (`gemini-2.5-flash-image`) y video (Veo). Fallback opcional a **NVIDIA NIM**. Quedan constantes `CLAUDE_*` como alias legado. |
| Pagos | **Mercado Pago** — Checkout Bricks + webhook IPN. |
| Facturación electrónica | **AFIP/ARCA** WSFE (WSAA + WSFEv1) con implementación propia en `core/AfipWsaa.php` y `core/AfipWsfe.php` (firma de TRA con OpenSSL, sin SDK). |
| Notificaciones | **Web Push** (VAPID) implementado a mano en `core/WebPush.php`. |
| Email | `core/Mailer.php` (wrapper de `mail()` / SMTP simple), desactivable por `MAIL_ENABLED`. |

---

## 3. Arquitectura y flujo de una request

### 3.1 Bootstrap (`index.php`)
1. Fuerza `Content-Type: text/html; charset=UTF-8`.
2. `require` manual de: `config/config.php`, `core/helpers.php`, `core/Database.php`,
   `core/Session.php`, `core/Csrf.php`, `core/Router.php`, `app/Models/Model.php`,
   `app/Controllers/Controller.php`. **No hay autoloader** (ni Composer autoload para el
   código propio; cada clase se `require_once`‑ea donde se usa).
3. `Session::start()`.
4. Registra un `set_exception_handler` global: loguea a `storage/logs/app.log` y responde
   **JSON** `{ok:false,error:...}` si la request es POST o `Accept: application/json`
   (casi todo el front es AJAX), o HTML si es navegación normal.
5. Define **todas las rutas** con `$router->get(...)` / `$router->post(...)`.
6. `$router->dispatch($_GET['url'], $_SERVER['REQUEST_METHOD'])`.

### 3.2 Router (`core/Router.php`)
- Diccionario `['GET'|'POST'][path] = [ControllerClass, method]`.
- Primero intenta **match exacto** de la ruta registrada.
- Si no hay match, cae a resolución convencional `/{controller}/{metodo}/{param1}/{param2}`
  → `UcfirstController::metodo($param1, $param2)`. Ruta vacía → `ChatController::index`.
- Instancia el controlador, `call_user_func_array`. 404 → `app/Views/errors/404.php`.
- **No hay middleware.** La auth/permyisos se pide dentro de cada acción del controlador.

### 3.3 Reescritura de URL (`.htaccess`, Apache + mod_rewrite)
- Bloquea acceso directo a `config/ core/ app/Models app/Controllers storage/ database/ vendor/` y a los archivos dot (`.env`, `.git`).
- Deja pasar `assets/`, `uploads/`, `sw.js`, `manifest.json`.
- Todo lo demás → `index.php?url=$1`.
- Cabeceras de seguridad: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy` y un **CSP** con allowlist para jQuery/DataTables/CDNJS/jsDelivr/
  Google Fonts/Mercado Pago SDK.

### 3.4 Controlador base (`app/Controllers/Controller.php`)
Métodos protegidos que usan todos los controladores:

| Método | Qué hace |
|--------|----------|
| `view($v, $data)` | Renderiza `app/Views/{v con . → /}.php` con `extract($data)`. |
| `render($v, $data, $layout='layouts.main')` | Captura la vista con `ob_*` y la mete en el layout. Además dispara **analítica de uso** (1 evento por pantalla). |
| `json($data, $code=200)` | Responde JSON y `exit`. |
| `requireAuth()` | Exige sesión staff (`$_SESSION['auth_user']`). Si no hay, intenta `RememberMe::check()`. En AJAX responde `401 {error:'sesion_expirada', csrf_token}` (el front muestra modal de reconexión sin recargar); en navegación redirige a `auth/login`. **Efecto lateral:** dispara generación perezosa de gastos recurrentes, solicitudes recurrentes y purga de analítica (1 vez por día de sesión). |
| `requireRole(...$roles)` | `requireAuth` + comprueba `$user['rol']`. |
| `requireCanView($modulo)` / `requireCanEdit($modulo)` | `requireAuth` + `canView/canEdit()` (permisos por módulo). |
| `requireAuthCliente()` | Sesión **separada** para el portal de clientes mayoristas (`$_SESSION['auth_cliente']`). |
| `input($k)` / `param($k)` | Lee `$_POST` / `$_GET` pasando por `sanitize()` (`trim(strip_tags())`). |
| `isPost()` | — |

### 3.5 Modelo base (`app/Models/Model.php`)
- `$db` es `Database::getInstance()` (PDO singleton).
- CRUD genérico: `findAll, findById, findWhere, findOneWhere, insert, update, softDelete
  (eliminado=1), count`.
- Helpers: `query, queryAll, queryOne, queryScalar` (todos con params ligados).
- `insert`/`update` construyen SQL con placeholders con nombre a partir de las claves del
  array — **las claves nunca deben venir de input del usuario** (vienen fijas del
  controlador). Los valores siempre van ligados.
- Cada modelo concreto declara `protected string $table` y agrega sus queries propias.

### 3.6 `core/Database.php`
- Singleton PDO. En cada `getInstance()` posterior al primero hace `SELECT 1`; si falla
  ("MySQL server has gone away" tras una request larga de IA) **reconecta solo**.
- `MYSQL_ATTR_INIT_COMMAND`: `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone='-03:00'`.

### 3.7 Vistas
- PHP plano con `<?= e($x) ?>` para escapar (helper `e()` = `htmlspecialchars` con
  `ENT_QUOTES|ENT_SUBSTITUTE`).
- Layout principal `app/Views/layouts/main.php` incluye `layouts/sidebar.php` y
  `layouts/header.php`. Tema oscuro/claro por `localStorage` (`dewoc-theme`).
- Nombre de vista con punto: `render('ventas.pos')` → `app/Views/ventas/pos.php`.

---

## 4. Estructura de directorios

```
/
├── index.php                 # front controller + TODAS las rutas (~600 líneas)
├── config/
│   ├── config.php            # constantes (lee .env manualmente, formato KEY=VALUE)
│   └── monotributo_categorias.php
├── core/                     # infraestructura (NO son modelos de dominio)
│   ├── Router.php  Session.php  Csrf.php  Database.php  helpers.php
│   ├── RememberMe.php         # cookie "recordarme" con token en BD
│   ├── AuditLog.php           # log de auditoría (quién hizo qué)
│   ├── ImportHelper.php       # parser de CSV/XLSX para importaciones masivas
│   ├── Mailer.php  WebPush.php  NotificadorReportes.php
│   └── AfipWsaa.php  AfipWsfe.php   # cliente SOAP AFIP propio
├── app/
│   ├── Controllers/          # 50 controladores
│   ├── Models/               # 55 modelos (+ Model.php base)
│   └── Views/                # 1 carpeta por módulo + layouts/ partials/ errors/
├── assets/                   # css/ js/ images/ fonts/ favicon/  (tema Dashly)
├── database/
│   ├── schema.sql            # DDL base (~40 tablas)
│   ├── seed.sql              # datos iniciales (roles, admin, unidades)
│   └── *_migration.sql       # ~90 migraciones incrementales (ver §7)
├── cron/                     # scripts PHP para tareas programadas (hPanel/CLI)
├── docs/                     # documentos de diseño y checklists por feature
├── tests/                    # suite propia sin PHPUnit (run.php)
├── storage/logs/             # app.log, php_errors.log (fuera de git)
├── storage/afip/             # cert.pem, key.pem (fuera de git)
├── uploads/                  # archivos subidos (fuera de git)
├── install.php               # instalador web (crea tablas, admin)
├── reset_pass.php            # utilidad suelta para resetear password
├── sw.js  sw-register.js  manifest.json  offline.html   # PWA
└── vendor/                   # solo phpspreadsheet + webauthn
```

---

## 5. Convenciones importantes (lo que hay que respetar al tocar el código)

1. **Casi todo es AJAX y espera JSON.** Los controladores responden `$this->json([...])`.
   Solo login, recuperar contraseña y descarga de backup son formularios "tradicionales".
2. **CSRF en todo POST.** `Csrf::check()` (JSON por defecto) o `Csrf::check(false)` (HTML).
   El token va en `<input name="_csrf_token">` o en header `X-CSRF-Token`. `Csrf::field()`
   emite el input. El front lo re‑lee del `401` de sesión expirada.
3. **Soft delete siempre.** Columna `eliminado TINYINT(1)`. Nunca `DELETE` físico de datos
   de dominio. Las queries filtran `WHERE eliminado = 0`.
4. **Timestamps explícitos desde PHP** (`date('Y-m-d H:i:s')`) en inserts sensibles a la
   hora, en vez de confiar en `DEFAULT CURRENT_TIMESTAMP` (el server MySQL puede estar en
   UTC).
5. **Permisos por módulo**, no por ruta. El controlador llama `requireCanView('stock')`.
   La lista canónica de módulos está en `PermisosModel::MODULOS` (~60 entradas, incluye
   sub‑módulos finos tipo `stock_ver_costos`, `reportes_ventas`).
6. **`can()` con default abierto:** si un usuario/rol no tiene permisos configurados,
   `can()` devuelve `true` (retrocompatibilidad). `rol === 'admin'` siempre `true`.
7. **Migraciones son archivos `.sql` sueltos**, idempotentes (usan `IF NOT EXISTS` y
   chequeos contra `INFORMATION_SCHEMA` para `ADD COLUMN`). Se corren a mano por phpMyAdmin.
   **No hay runner de migraciones ni tabla de versión de schema.**
8. **Sin autoloader:** al agregar una clase hay que `require_once` explícito.
9. **Logs:** `logApp($msg, $level)` → `storage/logs/app.log`.

---

## 6. Autenticación y autorización

### 6.1 Usuarios staff
- Tabla `usuarios` (FK `rol_id` → `roles`). Sesión en `$_SESSION['auth_user']` (array con
  `id, nombre, rol, pantalla_inicio, ve_tareas_generales, ...`).
- Login: `AuthController::login`. **bcrypt cost 12**. Bloqueo tras **5 intentos** fallidos
  durante **15 min** (`LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`).
- **Remember‑me:** `core/RememberMe.php`, token hasheado en BD, cookie separada.
- **WebAuthn / passkeys:** login biométrico (huella / Face ID / PIN del dispositivo) con
  `lbuchs/webauthn`. Rutas `webauthn/*`, tabla `webauthn_credentials`.
- Recuperación de contraseña por email con token (`auth/recuperar`, `auth/reset-password`).
- Credencial por defecto del seed: `admin@dewoc.com` / `admin1234`.

### 6.2 Roles y permisos
- Roles base: `admin`, `cajero`, `deposito`/`depósito`, `vendedor`, `encargado`.
- **Matriz clásica:** tabla `permisos (rol_id, modulo, ver, editar)`.
- **Plantillas de permisos** ("Permission Sets", estilo Salesforce) — migración
  `database/plantillas_permisos_migration.sql`. Jerarquía de resolución en
  `UsuarioModel::getPermisosEfectivos()`:
  1. Plantilla puntual del usuario (`usuarios.plantilla_permisos_id`)
  2. Plantilla por defecto de su rol (`roles.plantilla_permisos_id_default`)
  3. Matriz vieja por `rol_id` (tabla `permisos`) — red de seguridad
  4. `+` excepciones sueltas en `permisos_usuario` (otorgan/quitan, con `vence_en` opcional)
     por encima de todo.
- Tablas: `plantillas_permisos`, `plantilla_permisos_detalle`, `permisos_usuario`.
- Los permisos efectivos se cargan a `$_SESSION['permisos']` al loguear; `can($modulo,$accion)`
  en `core/helpers.php` los evalúa.
- `homeUrl()` resuelve la "pantalla de inicio" configurable por usuario.

### 6.3 Portal de clientes mayoristas (auth aparte)
- Sesión `$_SESSION['auth_cliente']`, totalmente separada de la de staff.
- `requireAuthCliente()`. Rutas `portal/*`. Los clientes hacen pedidos, ven su cuenta
  corriente y chatean con el staff sobre cada pedido.
- Doc: `docs/portal-clientes-mayoristas-checklist.md`.

---

## 7. Base de datos

- **`database/schema.sql`** — DDL base, ~40 tablas núcleo: `roles, usuarios, permisos,
  categorias, unidades_medida, proveedores, materias_primas, insumos, productos, recetas,
  receta_detalle, producto_receta, recetas_versiones, ordenes_produccion, facturas_compra,
  facturas_compra_detalle, clientes, turnos, ventas, ventas_detalle, stock_movimientos,
  inventarios(+_detalle), desvio_historial, caja_movimientos, pedidos_ecommerce(+_detalle),
  pagos_mercadopago, chat_mensajes, audit_log, galeria_archivos, empleados_conceptos_tipos, ...`
- **`database/seed.sql`** — roles, usuario admin, unidades de medida base.
- **~90 archivos `*_migration.sql` / `*_setup.sql`** en `database/` — cada feature nueva
  agregó el suyo. Idempotentes. Hay que aplicarlos en orden cronológico (por fecha de
  archivo) sobre una BD que ya tiene `schema.sql`. Ejemplos de lo que agregan:
  empleados/nómina, gastos y gastos recurrentes, activos fijos, socios, pagos a
  proveedores, cuenta corriente de clientes (POS), portal mayorista, promos, opciones/
  adicionales de productos, ingredientes genéricos, recetas borrador, solicitudes de
  inventario, tareas asignadas, proyecciones, analítica de uso, ayuda/help‑desk,
  reportes IA, Gemini usage log, WebAuthn, desvío de costos/mermas, AFIP, Web Push.
- **`MCP_TABLAS_OK`** (en `config.php`) — allowlist de tablas que el Chat IA puede
  consultar por SQL generado.

> No hay ORM ni definición de schema en código. La verdad del schema está en los `.sql`.
> Para una integración limpia conviene **volcar el `CREATE TABLE` real de una BD ya
> migrada** en vez de reconstruir desde los 90 archivos.

---

## 8. Módulos funcionales (mapa completo)

Cada módulo = 1 controlador + 1..N modelos + carpeta de vistas. Rutas exactas en `index.php`.

| Área | Módulos / controladores |
|------|------------------------|
| **Acceso** | Auth, Webauthn, Portal (clientes), Usuarios, Socios, Permisos, PlantillaPermisos |
| **Maestros** | Categorias, Productos, MateriasPrimas, Insumos, IngredientesGenericos, OpcionesGrupo (adicionales), Proveedores, Clientes |
| **Compras / Pagos** | Compras (facturas de compra c/ IVA discriminado, anulación revierte stock, adjuntos múltiples), PagosProveedores (cta. cte., reembolsos a socios), Gastos, GastosRecurrentes, ActivosFijos, ResumenEconomico |
| **Producción** | Recetas (ingredientes, merma %, costo estimado, versionado, órdenes de producción, planificación), RecetasBorrador (simulador de costeo) |
| **Venta** | Ventas / POS (fullscreen, ticket 80mm, comanda de cocina, pago dividido, offline con catálogo cacheado, solicitudes de anulación), CobrosClientes (cta. cte. del POS), Tienda (catálogo público sin auth), Ecommerce (admin de pedidos), PedidosClientes (staff acepta/rechaza/chatea pedidos del portal) |
| **Stock** | Stock (ajustes, inventario físico, apertura, historial por ítem, reporte por período) |
| **Caja** | Caja (apertura/cierre por turno, movimientos, arqueo, diferencias, historial, ticket de cierre) |
| **Empleados** | Empleados (legajos, jornadas/asistencia, conceptos de liquidación, nómina, recuperación de horas), Feriados |
| **Reportes** | Reportes (ventas, compras, stock, caja, producción, nómina, pagos, margen, mermas/desvíos, compras a $0, costos desviados, ventas sin stock, semanal) con Chart.js + export CSV/Excel/PDF. ReportesIa (reportes generados por el Chat IA). Proyecciones (predicción de ventas/compras, generadas por cron). Analitica (uso interno del sistema). |
| **IA** | Chat (NL→SQL + acciones), ChatAccesos (permisos de acciones del chat), Galeria (generación de imágenes/videos con Gemini/NVIDIA), GeminiUsage + GeminiLog (control de cuotas y trazas), AyudaTemas (base de conocimiento del help‑desk del chat) |
| **Operación** | Solicitudes (compras/tareas + recurrentes + Web Push), Tareas (asignadas a trabajadores), Notificaciones (bandeja del header) |
| **Admin** | Backup (dump de BD descargable), Logs, Docs (documentación embebida), AuditLog |

---

## 9. Integraciones externas y dónde viven

| Integración | Archivos clave | Config (.env) |
|-------------|----------------|---------------|
| **Gemini (Chat IA)** | `app/Controllers/ChatController.php`, `app/Models/ChatModel.php`, `app/Models/ChatAccesoModel.php`, `GeminiUsageModel`, `GeminiLlamadaLogModel` | `GEMINI_API_KEY`, `GEMINI_MODEL` (default `gemini-2.0-flash-lite`), `GEMINI_MODEL_FALLBACK` (cadena de respaldo por cuota RPM/RPD) |
| **Gemini imagen/video (Galería)** | `GaleriaController` (`generar`, `generarVideo`, `estado` — video es async: `predictLongRunning` + `operations.get`) | `GEMINI_IMAGE_MODEL`, `GEMINI_VIDEO_MODEL`, `GEMINI_MEDIA_API_KEY` (key con billing, separada) |
| **NVIDIA NIM (alt. gratis Galería)** | mismo `GaleriaController` | `NVIDIA_API_KEY`, `NVIDIA_IMAGE_MODEL`, `NVIDIA_VIDEO_MODEL` |
| **Mercado Pago** | `TiendaController` (`checkout`, `pagoBrick`, `procesarPagoBrick`, `webhookMercadoPago`), tabla `pagos_mercadopago` | `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY` |
| **AFIP / ARCA (factura electrónica)** | `core/AfipWsaa.php` (login WSAA, firma TRA con OpenSSL CMS), `core/AfipWsfe.php` (WSFEv1: `FECAESolicitar`, `tipoComprobante()` A/B/C según condición IVA emisor+receptor), `VentasController` (`comprobantesAfip`, `verificarAfip`, `historicoAfip`) | `AFIP_MODO` (`homo`/`prod`), `AFIP_CUIT`, `AFIP_CERT`, `AFIP_KEY`, `AFIP_KEY_PASS`, `AFIP_PTO_VENTA` + certificados en `storage/afip/` |
| **Web Push (VAPID)** | `core/WebPush.php` (JWT ES256 + AES‑GCM a mano), `PushSubscriptionModel`, rutas `*/vapid-public-key`, `*/suscribir` | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` |
| **Email** | `core/Mailer.php`, `AuthController` (recuperación), `core/NotificadorReportes.php` | `MAIL_ENABLED`, `MAIL_FROM`, `MAIL_FROM_NAME` |
| **Excel** | `phpoffice/phpspreadsheet` — exports de reportes, listados, reportes IA; imports vía `core/ImportHelper.php` (CSV/XLSX) |
| **Cron por URL** | scripts en `cron/`, autorizados con `?secret=CRON_SECRET` | `CRON_SECRET` |
| **Ticket compartible** | `helper ticketToken()` — HMAC‑SHA256 del id de venta, permite ver el ticket sin login | `TICKET_SHARE_SECRET` |

### 9.1 Chat IA — cómo funciona (resumido)
- Flujo: mensaje del usuario → Gemini con prompt de sistema que describe el schema
  permitido (`MCP_TABLAS_OK`) → Gemini devuelve **SQL** (solo SELECT, o una "acción
  interna" pre‑aprobada) → se ejecuta → resultado se re‑pasa a Gemini para redacción
  natural → se guarda en `chat_mensajes` (con `sql_ejecutado`, `datos_json`).
- **Acciones que mutan** (cargar una compra, ajustar stock, registrar un pago…) pasan por
  una **tarjeta de confirmación** (`chat/confirmar`, `chat/cancelar`) antes de ejecutarse.
- **ChatAccesos:** por usuario/rol se define qué acciones internas puede pedir el chat.
- Soporta **adjuntos** (subir foto de una factura para que la IA la cargue).
- Fallback de modelos por límite de cuota; todo el consumo se registra en
  `gemini_llamadas_log` y se visualiza en `/gemini-log` y `/gemini-uso`.
- Diseño detallado y reutilizable: **`docs/chat-ia-arquitectura-reutilizable.md`** (56 KB,
  es la mejor referencia si querés portar el Chat IA a otro proyecto).

---

## 10. PWA / offline

- `manifest.json` + `sw.js` (registrado por `sw-register.js`). Estrategia: cache‑first para
  `assets/`, network‑first con fallback a `offline.html` para navegación.
- **POS offline** (`docs/pos-offline-checklist.md`): `VentasController::catalogoOffline`
  entrega un JSON de productos/precios que el POS cachea en el cliente para poder vender
  sin conexión y sincronizar después.
- Iconos en `assets/images/pwa-icon-*.png`.

---

## 11. Tareas programadas

- **Cron real** (`cron/`, se disparan por hPanel/CLI o por URL con `CRON_SECRET`):
  `reporte_semanal.php`, `reporte_compras_pagos_usuario.php`, `reporte_margen_producto.php`,
  `proyecciones_generar.php`, `feriados_importar.php`.
- **Cron "perezoso"** (sin cron del server): en el primer request autenticado de cada día
  de sesión, `Controller::requireAuth()` dispara:
  - `RecurrenteModel::generarPendientes()` — gastos fijos del mes (alquiler, sueldos…).
  - `SolicitudRecurrenteModel::generarPendientes()` — solicitudes de inventario semanales.
  - `AnaliticaUsoModel::purgarAnteriorA()` — retención de 6 meses.

---

## 12. Seguridad implementada

- PDO prepared statements en todas las queries; sin SQL concatenado con input.
- CSRF token en todos los POST (`core/Csrf.php`).
- XSS: `e()` / `htmlspecialchars(ENT_QUOTES)` en las vistas.
- bcrypt cost 12; bloqueo de login por intentos.
- Cabeceras HTTP de seguridad + CSP en `.htaccess`.
- Soft deletes (sin borrado físico).
- `.htaccess` bloquea acceso web a `config/`, `core/`, modelos, controladores, `.env`, etc.
- `AuditLog` (`core/AuditLog.php`, tabla `audit_log`) registra acciones sensibles.
- Chat IA: solo SELECT sobre tablas allowlist; mutaciones con confirmación explícita.
- Tests de seguridad propios (ver §13).

---

## 13. Tests (`tests/`)

- Suite **propia, sin PHPUnit ni dependencias**. Runner: `php tests/run.php`.
- `tests/TestCase.php` — clase base con asserts.
- `tests/security/` — `CsrfTest`, `XssTest`, `SqlInjectionTest`, `HttpSecurityTest`.
- `tests/unit/` — tests de modelos y algunos controladores (`VentaModelTest`,
  `RecetaModelTest`, `StockModelTest`, `ChatModelTest`, `HelpersTest`, `SessionTest`, …).
- `tests/integration/` existe pero está vacío.

---

## 14. Configuración (`config/config.php` + `.env`)

- `config.php` **lee `.env` a mano** al inicio (formato `KEY=VALUE`, ignora `#`, quita
  comillas envolventes). No usa `vlucas/phpdotenv`.
- Toda la config son `define()` de constantes globales. Con `getenv() ?: 'default'`.
- `ENV` (`development`/`production`) → `DEBUG`. En `development` muestra errores y traces;
  en `production` los loguea a `storage/logs/php_errors.log`.
- Plantilla completa de variables: **`.env.example`** (6.8 KB, está commiteado).
- Constantes de negocio en config: nombre/dirección del comercio, ancho de impresora
  térmica (`TICKET_ANCHO_MM`), condición IVA del emisor (`NEGOCIO_CONDICION_IVA` → deriva
  tipo de comprobante AFIP y si se discrimina IVA), datos fiscales del encabezado.

---

## 15. Notas para integrarlo en otro proyecto

### Qué es fácilmente reutilizable (poco acoplado)
- **`core/`** casi entero: `Router`, `Session`, `Csrf`, `Database`, `helpers.php`,
  `RememberMe`, `ImportHelper`, `Mailer`, `WebPush`, `AfipWsaa`/`AfipWsfe`. Dependen solo
  de las constantes de `config.php` y de PDO.
- **Patrón Model base** (`app/Models/Model.php`) — CRUD genérico portable a cualquier
  proyecto PDO.
- **Chat IA** — pensado explícitamente para portarse; leer `docs/chat-ia-arquitectura-reutilizable.md`.
- **Cliente AFIP propio** (`core/AfipWsaa.php` + `core/AfipWsfe.php`) — sin SDK, sirve para
  cualquier app PHP que necesite factura electrónica argentina.
- **WebPush**, **WebAuthn wiring**, export/import Excel.

### Qué está acoplado a este proyecto
- **No hay autoloader** → al mover clases hay que arrastrar los `require_once`.
- Constantes **globales** de `config.php` en vez de un objeto de configuración inyectado.
- **Rutas centralizadas en `index.php`** (no hay descubrimiento automático).
- Permisos referencian la lista fija `PermisosModel::MODULOS`.
- Vistas atadas al **tema Dashly** (Bootstrap 5) y a jQuery/DataTables por CDN.
- Single‑tenant: no hay columna `empresa_id` en ningún lado.
- Idioma español "hardcodeado" en toda la UI y en nombres de columnas.
- Schema real = suma de `schema.sql` + ~90 migraciones; para integrar, **dumpear el
  `CREATE TABLE` de una BD ya migrada**.

### Puntos de acople recomendados
1. **Base de datos:** compartir la instancia PDO / mismas credenciales, o replicar las
   tablas que necesite el otro sistema (`ventas`, `productos`, `stock_movimientos`,
   `clientes`, … están en `MCP_TABLAS_OK` como referencia de las "públicas").
2. **Auth:** si el otro proyecto ya tiene login, mapear su usuario a `$_SESSION['auth_user']`
   con las claves `id, nombre, rol, permisos` para reutilizar `requireAuth/can()`.
3. **API:** hoy no hay API REST formal; los endpoints AJAX devuelven JSON
   (`{ok:bool, ...}`) y exigen sesión + CSRF. Para consumo máquina‑a‑máquina habría que
   agregar auth por token.
4. **Chat IA como servicio:** el `ChatController` podría exponerse como endpoint y
   reusarse desde el otro sistema pasando el schema y la allowlist de tablas de ese otro
   sistema.

### Deuda técnica / cosas a saber
- Un solo commit en git; muchos archivos sueltos en la raíz (CSVs, capturas, `.xlsx`,
  scripts de diagnóstico `.sql`) que son **basura de trabajo**, no parte del sistema.
- `config.php` tiene **secretos por defecto hardcodeados** (VAPID, `CRON_SECRET`,
  `TICKET_SHARE_SECRET`) — rotarlos en cualquier despliegue nuevo.
- `install.php` y `reset_pass.php` en la raíz — borrar en producción.
- Constantes `CLAUDE_API_KEY` / `CLAUDE_MODEL` son **legado**; la IA hoy es Gemini.
- El README de la raíz está algo desactualizado (menciona "Anthropic Claude API" y no
  cubre empleados, AFIP, portal mayorista, socios, activos fijos, etc.).

---

## 16. Referencias internas útiles

| Documento | Contenido |
|-----------|-----------|
| `docs/chat-ia-arquitectura-reutilizable.md` | Arquitectura del Chat IA pensada para reutilizar (la más completa). |
| `docs/propuesta-saas-multiempresa-chat-first.md` | Plan (no implementado) para convertirlo en SaaS multiempresa. |
| `docs/RUTAS.md` | Listado de rutas. |
| `docs/CHECKLIST.md` | Estado histórico de funcionalidades. |
| `docs/arquitectura.drawio`, `docs/flujo_usuario.drawio` | Diagramas. |
| `docs/*-checklist.md` / `*-diseno.md` | Un documento de diseño por feature grande (promos, activos fijos, portal mayorista, cuenta corriente POS, solicitudes de inventario, predicción de ventas, desvío de mermas, etc.). |
| `.env.example` | Todas las variables de entorno con explicación. |
| `database/schema.sql` + `database/*_migration.sql` | Schema. |
