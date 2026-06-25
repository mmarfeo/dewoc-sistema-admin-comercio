# Sistema de Administración de Comercios — Checklist de Funcionalidades

**Stack:** PHP · MySQL · HTML · CSS · JS · MVC  
**Tema:** Dashly 1.6.0 (Bootstrap, responsive)  
**Extras:** PWA · Ecommerce · Mercado Pago · Chat IA + MCP MySQL · AFIP

> Leyenda: ✅ Completado · 🔄 Parcial · ⬜ Pendiente

---

## 0. Infraestructura y Configuración Base

### 0.1 Estructura MVC ✅
- [x] Crear estructura de directorios `/app/Models`, `/app/Views`, `/app/Controllers`
- [x] Router principal (`index.php`) con reescritura de URLs (`.htaccess`)
- [x] Clase base `Controller.php` con carga de vistas
- [x] Clase base `Model.php` con conexión PDO a MySQL
- [x] Archivo de configuración centralizado (`config/config.php`) con constantes de entorno
- [x] Gestión de sesiones seguras (`session_start`, regeneración de ID)
- [x] Manejo global de errores y excepciones con log
- [x] Helpers de sanitización y validación de inputs

### 0.2 Base de datos MySQL ✅
- [x] Script `database/schema.sql` con creación de schema completo
- [x] Todas las tablas del sistema (usuarios, roles, permisos, categorias, productos, materias_primas, insumos, recetas, receta_detalle, proveedores, facturas_compra, facturas_compra_detalle, ventas, ventas_detalle, stock_movimientos, caja_movimientos, caja_turnos, clientes, ordenes_produccion, pedidos_ecommerce, pedidos_ecommerce_detalle, pagos_mercadopago, chat_mensajes)
- [x] Script de datos iniciales (seed) — usuario admin y roles base

### 0.3 PWA (Progressive Web App) ✅
- [x] Archivo `manifest.json` con nombre, iconos (192×192, 512×512), `display: standalone`
- [x] Service Worker (`sw.js`) con estrategia cache-first para assets estáticos
- [x] Estrategia network-first para llamadas a controladores PHP
- [x] Banner "Instalar aplicación" (beforeinstallprompt)
- [x] Iconos PWA generados con GD en `install.php`
- [x] Página offline de fallback (`offline.html`)
- [x] Meta tags `apple-mobile-web-app-capable` e `apple-mobile-web-app-status-bar-style` para iOS
- [x] Precache de páginas clave — `dewoc-v2`, PRECACHE_URLS incluye login/dashboard/ventas-pos/stock

### 0.4 Frontend — Dashly ✅
- [x] Integrar Dashly 1.6.0 como tema base (Bootstrap 5)
- [x] Sidebar de navegación lateral con todos los módulos y submenús
- [x] Header con nombre de usuario, link a Mi Perfil y logout
- [x] Modo oscuro/claro con `data-bs-theme` y localStorage
- [x] Layout responsive (colapsado mobile, expandido desktop)
- [x] Breadcrumbs en cada sección
- [x] Cards de KPI, toasts flash messages, modales Bootstrap

### 0.5 Estándar Global de Tablas ✅
- [x] DataTables 1.13+ con extensiones Buttons y FixedHeader
- [x] Header fijo (FixedHeader) en todas las tablas
- [x] Selector de columnas (ColVis) en todas las tablas
- [x] `stateSave: true` — preferencias guardadas en localStorage
- [x] Scroll horizontal en mobile (`scrollX: true`)
- [x] Paginado 10/25/50/100
- [x] Búsqueda global y ordenamiento por columna
- [x] Exportación Excel y PDF (ExcelHtml5, PdfHtml5)
- [x] Textos en español (DataTables language)

---

## 1. Autenticación y Gestión de Usuarios

### 1.1 Login / Logout ✅
- [x] Pantalla de login estilo `sign-in-basic` de Dashly
- [x] Validación de credenciales con `password_hash` / `password_verify` (bcrypt cost 12)
- [x] Bloqueo tras 5 intentos fallidos (15 min, almacenado en DB)
- [x] Logout con destrucción de sesión y redirect
- [x] Opción "Recordarme" con cookie segura (token SHA-256, 30 días, token rotation, limpieza en logout)
- [x] Pantalla de restablecimiento de contraseña por email (token SHA-256, 1h expiración, link seguro, nuevas rutas auth/recuperar y auth/reset-password)

### 1.2 ABM de Usuarios ✅
- [x] Listado de usuarios con DataTable (búsqueda, paginado, filtro por rol)
- [x] Alta: nombre, email, rol, contraseña (bcrypt)
- [x] Modificación: datos personales, rol, estado activo/inactivo
- [x] Baja lógica con confirmación (no puede eliminarse a sí mismo)
- [x] Mi Perfil: cambio de contraseña con validación de contraseña actual (bcrypt)
- [x] Foto de perfil (upload con resize GD, guardado en uploads/usuarios/, actualización AJAX)

### 1.3 Roles y Permisos 🔄
- [x] Roles predefinidos: admin(1), cajero(2), deposito(3), vendedor(4)
- [x] Middleware `requireRole()` en Controllers
- [x] Pantalla UI de asignación de permisos por módulo (Ver/Editar/Sin acceso — `PermisosModel`, `PermisosController`, vista matriz, sidebar condicional)

---

## 2. ABM de Maestros

### 2.1 Categorías ✅
- [x] Listado con árbol/tabla (DataTable)
- [x] Alta: nombre, descripción, categoría padre, tipo, estado
- [x] Modificación y baja lógica (verifica hijos y productos antes)
- [x] Modal AJAX — sin recarga de página

### 2.2 Productos ✅
- [x] Listado con alerta stock bajo (fila amarilla)
- [x] Alta: código, nombre, descripción, categoría, precio venta, precio costo, unidad, imagen, IVA (0/10.5/21%), activo ecommerce
- [x] Upload de imagen con validación MIME y límite 5MB
- [x] Modificación completa y baja lógica
- [x] Indicador visual de stock bajo
- [x] Código de barras / QR por producto (ruta , JsBarcode + qrcode.js, descarga PNG/SVG)

### 2.3 Materias Primas ✅
- [x] Listado con stock actual (fila amarilla si ≤ mínimo)
- [x] Alta: código, nombre, categoría, unidad, stock mínimo, proveedor habitual, costo unitario
- [x] Modificación y baja lógica
- [x] Historial de precios de compra por MP (vista con KPIs + gráfico Chart.js + DataTable)

### 2.4 Insumos ✅
- [x] Listado con stock actual
- [x] Alta: código, nombre, categoría, unidad, stock mínimo, costo unitario
- [x] Modificación y baja lógica
- [x] Indicador de stock bajo

### 2.5 Proveedores ✅
- [x] Alta: razón social, CUIT (XX-XXXXXXXX-X), dirección, teléfono, email, condición IVA, banco/CBU (22 dígitos)
- [x] Modificación y baja lógica (verifica facturas asociadas)
- [x] Historial de compras por proveedor (filtro por proveedor_id en ComprasController, header contextual en vista, botón en listado de proveedores)

### 2.6 Clientes ✅
- [x] Alta: nombre, DNI/CUIT, condición IVA, teléfono, email, dirección
- [x] Modificación y baja lógica (verifica ventas asociadas antes de eliminar)
- [x] Búsqueda AJAX por nombre/email/CUIT
- [x] Historial de ventas del cliente
- [x] Toggle activo/inactivo
- [x] Tests unitarios: `ClienteModelTest` (20 tests)

---

## 3. Facturas de Compra ✅

### 3.1 Carga de Factura
- [x] Cabecera: proveedor, nro. comprobante, fecha, tipo (A/B/C/ticket/remito/otro), condición de pago, notas
- [x] Buscador inline de ítems por tipo (Materia Prima / Insumo / Producto reventa)
- [x] Campos de ítem: ítem, cantidad, precio unitario, % IVA (0/10.5/21%), subtotal
- [x] Cálculo automático de subtotal, IVA discriminado y total
- [x] Actualización automática de stock al guardar
- [x] Anulación con reversión de stock
- [x] Validación de duplicado (mismo proveedor + número de comprobante)
- [x] Costo unitario promedio ponderado al recibir (CPP en SQL atomico, )

### 3.2 Listado y Gestión
- [x] DataTable con filtros: proveedor, fecha desde/hasta, estado
- [x] Vista de detalle con todos los datos
- [x] Impresión CSS `@media print`
- [x] Exportación PDF individual (ruta `compras/imprimir`, vista standalone A4, `window.print()`, enlace desde ver.php)

---

## 4. Recetas de Producción ✅

### 4.1 Gestión de Recetas
- [x] Listado con cantidad de ingredientes
- [x] Alta: nombre, producto final, rendimiento, unidad, notas
- [x] Detalle de ingredientes: materia prima / insumo, cantidad, merma%
- [x] Costo de receta calculado automáticamente
- [x] Modificación completa (reemplaza detalle) y baja lógica
- [x] Historial de versiones de receta (, , vista accordión)

### 4.2 Producción
- [x] Vista "Producir": verifica stock disponible vs necesario, muestra faltantes
- [x] Registro de orden de producción al confirmar
- [x] Descuenta materias primas e insumos del stock
- [x] Suma stock del producto elaborado resultante
- [x] Listado de órdenes de producción con filtros (fecha, receta) y KPIs
- [x] Estados de producción: Planificada / En Proceso / Completada / Anulada — con flujo AJAX desde órdenes

---

## 5. Ventas ✅

### 5.1 Punto de Venta (POS)
- [x] Pantalla POS fullscreen: búsqueda de productos por nombre o código (F2)
- [x] Carrito con cantidades editables, precio, descuento por ítem
- [x] Descuento global %
- [x] Métodos de pago: efectivo, débito, crédito, transferencia, Mercado Pago
- [x] Checkbox "Facturar por AFIP"
- [x] Modal post-venta con links a ticket y comanda (F10 para cobrar)
- [x] Selector de cliente con búsqueda AJAX (nombre / email / CUIT)
- [x] Cálculo de vuelto en tiempo real (aparece solo al seleccionar efectivo)
- [x] Selector de mesa / mostrador (campo "Mesa / Destino" con datalist en POS, columna `mesa` en ventas, muestra en ticket)

### 5.2 Facturación AFIP ✅
- [x] Checkbox por venta para marcar si se factura por AFIP
- [x] Integración API AFIP (WebService WSFE v1 — factura electrónica) con `AfipWsaa` + `AfipWsfe`
- [x] Generación de CAE — solicitud automática en `VentasController::guardar()`, resultado guardado en ventas
- [x] Tipo de comprobante A/B/C configurable via `AFIP_TIPO_CBTE` en .env; DocTipo por CUIT/DNI/consumidor final
- [x] CAE visible en modal POS post-venta, en ticket 80mm y en vista detalle de venta

### 5.3 Impresión ✅
- [x] Ticket de venta 80mm (`ventas/ticket`) — `window.print()` automático
- [x] Comanda de cocina 80mm (`ventas/comanda`) con cantidad en grande
- [x] `@media print` para ocultar sidebar/header
- [x] Vista previa en modal (iframe con ticket) antes de imprimir
- [x] Envío por WhatsApp (share-bar en ticket, normalización teléfono Argentina 54XXXXXXXXXX, botón wa.me en modal POS; mailto en ticket)

### 5.4 Listado de Ventas ✅
- [x] DataTable con filtros: fecha desde/hasta, estado
- [x] Totales del período filtrado
- [x] Vista de detalle completa
- [x] Anulación con reversión de stock

---

## 6. Control de Stock ✅

### 6.1 Registro de Stock
- [x] Ajuste de stock ±: motivo (merma, robo, error, etc.)
- [x] Movimientos registrados en `stock_movimientos` con origen
- [x] Inventario inicial de apertura (ruta `stock/apertura`, muestra solo ítems con stock ≤ 0, guarda vía `procesarInventario()`)

### 6.2 Consulta de Stock Actual ✅
- [x] Vista de stock en tiempo real (productos + MP + insumos)
- [x] Alerta visual bajo mínimo (fila amarilla + badge)
- [x] Sección de alertas al pie con tarjetas

### 6.3 Reporte Comparativo de Stock ✅
- [x] Selector de período
- [x] Productos vendidos en el período (qty + total)
- [x] Consumo de materias primas y consumo de insumos en producción
- [x] Stock inicial / stock final teórico / diferencia vs físico (KPIs + tabla comparativa por ítem con badges de discrepancia + DataTable + Excel/PDF + filtro "solo con diferencia")
- [x] Gráfico de evolución de stock (selector ítem+tipo, AJAX a `stock/historial-item`, Chart.js line chart en `stock/reporte.php`)

### 6.4 Inventario Físico ✅
- [x] Listado de todos los ítems con campo para ingresar cantidad física
- [x] Diferencia calculada en tiempo real (verde/rojo)
- [x] Ajuste batch automático al confirmar

---

## 7. Caja y Turnos ✅

### 7.1 Turnos ✅
- [x] Apertura: monto inicial, observaciones
- [x] Cierre: monto declarado vs calculado → diferencia (sobrante/faltante)
- [x] Solo un turno abierto por usuario
- [x] Historial de turnos con estado y diferencias

### 7.2 Movimientos de Caja ✅
- [x] Registro automático de ventas en efectivo al turno activo
- [x] Ingreso manual: ingreso / egreso, monto, descripción
- [x] Resumen del turno: monto inicial, ingresos, egresos, saldo calculado

### 7.3 Reportes de Caja ✅
- [x] Reporte por rango de fechas con KPIs de turnos
- [x] Diferencia sobrante/faltante con coloreado visual
- [x] DataTable con detalle de movimientos por turno

---

## 8. Ecommerce ✅

### 8.1 Catálogo Público
- [x] Landing page con categorías y productos destacados
- [x] Grilla de productos con imagen, nombre, precio, botón carrito
- [x] Filtros por categoría y búsqueda
- [x] Página de detalle de producto
- [x] Responsive mobile-first (layout Bootstrap público sin sidebar)

### 8.2 Carrito de Compras
- [x] Agregar, modificar cantidad y eliminar ítems
- [x] Persistencia del carrito en localStorage
- [x] Subtotal y total con IVA discriminado

### 8.3 Checkout y Pagos con Mercado Pago
- [x] Formulario de datos del comprador (nombre, email, teléfono, dirección)
- [x] Integración Mercado Pago Checkout Pro (redirect a MP via API cURL)
- [x] Webhook IPN para confirmar pago (`/tienda/webhook/mp`)
- [x] Páginas de retorno: éxito, pendiente, fallo
- [x] Estados de pedido: Pendiente / Pagado / En preparación / Enviado / Entregado / Cancelado
- [x] Checkout API (tarjeta inline) — MP Bricks CardPayment, tienda/pago/brick + tienda/pago/procesar
- [x] Email de confirmación al comprador (Mailer::confirmacionPedido, MAIL_ENABLED en config)

### 8.4 Gestión de Pedidos (Admin)
- [x] Listado de pedidos ecommerce con estado, filtros fecha/estado y DataTable
- [x] Vista de detalle del pedido (productos, cliente, datos MP)
- [x] Cambio de estado manual via AJAX (con CSRF)
- [x] Actualización de stock automática al pasar a estado "pagado" (admin y webhook IPN)

---

## 9. Chat Interno con IA + MCP MySQL ✅

### 9.1 Interfaz de Chat
- [x] Pantalla de chat estilo Dashly con burbujas usuario/asistente
- [x] Input de texto para preguntas en lenguaje natural
- [x] Historial de mensajes cargado desde BD (últimas 30 interacciones)
- [x] Indicador "escribiendo..." animado mientras se espera respuesta
- [x] Respuestas con tablas de resultados inline y modal para ver SQL ejecutado

### 9.2 Integración IA con MCP MySQL
- [x] Integración con API Anthropic (Claude) via cURL (`/v1/messages`)
- [x] NL → SQL: IA genera SELECT, se ejecuta, segunda llamada interpreta resultados
- [x] Whitelist de tablas habilitadas (`MCP_TABLAS_OK` en config)
- [x] Validación SQL: solo SELECT, keywords prohibidos (DROP/INSERT/UPDATE/etc.)
- [x] LIMIT 50 automático si la consulta no lo especifica
- [x] Log de consultas SQL ejecutadas en `chat_mensajes.sql_ejecutado`
- [x] Panel lateral con preguntas de ejemplo y listado de tablas disponibles
- [x] Fallback claro cuando `CLAUDE_API_KEY` no está configurada
- [x] Tests unitarios: `ChatModelTest` (29 tests)

---

## 10. Reportes y Dashboard

### 10.1 Dashboard Principal ✅
- [x] KPIs del día: ventas totales, cantidad de tickets, ticket promedio, stock bajo
- [x] Gráfico de ventas últimos 7 días (Chart.js)
- [x] Top 5 productos más vendidos
- [x] Stock en alerta (tabla rápida)
- [x] Últimas ventas registradas
- [x] Estado del turno actual

### 10.2 Reportes ✅
- [x] Reporte de ventas por período con KPIs, Chart.js por día, top 10 productos, desglose por método de pago
- [x] Reporte de compras por período con desglose por proveedor
- [x] Reporte de stock: movimientos por tipo (compra/venta/producción/ajuste/inventario)
- [x] Reporte de caja por turno con diferencias sobrante/faltante
- [x] Exportación Excel y PDF vía DataTables Buttons en todos los reportes
- [x] Tests unitarios: `ReportesTest` (41 tests)
- [x] Reporte de producción (órdenes ejecutadas, KPIs, gráfico por receta, DataTable + Excel/PDF)

---

## 11. Seguridad ✅

- [x] Prepared statements PDO en todas las consultas (prevención SQL Injection)
- [x] Escape HTML con `e()` / `htmlspecialchars(ENT_QUOTES)` en todas las vistas
- [x] CSRF token en todos los formularios POST (`Csrf::check()`)
- [x] Cabeceras HTTP: `X-Frame-Options`, `X-Content-Type-Options`, `CSP`, `X-XSS-Protection`, `Permissions-Policy`
- [x] Contraseñas con `password_hash` bcrypt cost 12
- [x] `session_regenerate_id(true)` tras login exitoso
- [x] Acceso a directorios sensibles bloqueado via `.htaccess`
- [x] Lockout en login (5 intentos → 15 min)
- [x] Suite de tests de seguridad en `tests/security/`
- [x] Log de auditoría de acciones críticas — tabla audit_log, AuditLog helper, vista admin
- [x] Variables de entorno (`.env`) para credenciales en producción — carga automática en config.php con fallbacks, `.env.example` documentado

---

## 12. DevOps y Calidad ✅

- [x] Script de instalación `install.php` (crea DB, ejecuta schema + seed, genera iconos)
- [x] Tests unitarios y de seguridad en `tests/` con runner propio (`tests/run.php`)
- [x] TestCase base con auto-discovery de métodos `test*` y 11 tipos de aserciones
- [x] Tests: ClienteModelTest (20), ReportesTest (41), ChatModelTest (29), FacturaCompraModelTest, seguridad (CSRF/XSS/SQLi/Headers/Sessions) — 291 total
- [x] `.gitignore` con exclusiones de uploads, logs, secretos y dumps
- [x] `README.md` con instrucciones de instalación, estructura de directorios y módulos
- [x] Log de errores PHP separado por entorno (producción → `storage/logs/php_errors.log`)
- [x] Backup manual de base de datos (BackupController, dump PHP puro, sólo admin, audit log)
- [x] Documentación de rutas del router — `docs/RUTAS.md` con ~70 endpoints por módulo

---

## Resumen de Estado General

| # | Módulo | Estado | Items ✅ | Items ⬜ |
|---|--------|--------|---------|---------|
| 0 | Infraestructura MVC + DB + PWA + Dashly | ✅ | 38 | 0 |
| 1 | Autenticación, Usuarios y Perfil + Permisos | ✅ | 15 | 0 |
| 2 | ABM Maestros (6 entidades) | ✅ | 25 | 0 |
| 3 | Facturas de Compra | ✅ | 11 | 0 |
| 4 | Recetas de Producción + Órdenes | ✅ | 12 | 0 |
| 5 | Ventas (POS + Ticket + Comanda) | ✅ | 21 | 0 |
| 5.2 | Integración AFIP (factura electrónica) | ✅ | 5 | 0 |
| 6 | Control de Stock | ✅ | 12 | 0 |
| 7 | Caja y Turnos | ✅ | 9 | 0 |
| 8 | Ecommerce + Mercado Pago | ✅ | 17 | 0 |
| 9 | Chat IA + MCP MySQL | ✅ | 14 | 0 |
| 10 | Dashboard + Reportes | ✅ | 12 | 0 |
| 11 | Seguridad | ✅ | 11 | 0 |
| 12 | DevOps y Calidad | ✅ | 9 | 0 |

---

## Tareas pendientes priorizadas

### Alta prioridad (funcionalidad core)
- [x] **AFIP WebService WSFE** — integración API factura electrónica, generación CAE, comprobantes A/B/C ✅

### Baja prioridad (nice to have)
- [x] **Selector de mesa/mostrador** en POS ✅
- [x] **Código de barras / QR** por producto ✅
- [x] **Recordarme** con cookie segura en login ✅
- [x] **Restablecimiento de contraseña** por email ✅
- [x] **Precache de páginas** clave en Service Worker ✅
- [x] **Backup manual** de base de datos ✅
- [x] **Historial de compras por proveedor** (filtro en listado + header contextual) ✅
- [x] **PDF individual de factura de compra** (vista standalone A4, window.print) ✅
- [x] **Envío por WhatsApp / email** del ticket de venta ✅
- [x] **Inventario inicial de apertura** (flujo para primer uso con ítems en cero) ✅
- [x] **Gráfico de evolución de stock** (Chart.js en reporte, AJAX historial-item) ✅
- [x] **Historial de versiones** de recetas ✅
- [x] **Costo promedio ponderado** al recibir facturas de compra ✅
- [x] **Variables de entorno** (.env) para producción ✅
- [x] **Checkout API** Mercado Pago (tarjeta inline, adicional al Checkout Pro) ✅
- [x] **Permisos por módulo** vía UI (Ver/Editar/Sin acceso) ✅

---

*Última actualización: 2026-06-25 (sesión 5) | Tests: 291 pasan · 20 falsos positivos conocidos*

---

## Sesión 2026-06-25 — Tareas completadas (sesión 1)

- **Stock teórico vs físico** — `StockModel::stockTeoricoVsFisico()`, nueva sección en `stock/reporte.php` con KPIs + DataTable + filtro "solo discrepancias"
- **Selector mesa/mostrador en POS** — campo "Mesa / Destino" con `<datalist>`, columna `mesa VARCHAR(30)` en `ventas`, muestra en ticket
- **Recordarme** — cookie httpOnly+SameSite, token SHA-256 en DB, token rotation, `core/RememberMe.php`, limpieza en logout, auto-login en `requireAuth()`
- **Restablecimiento de contraseña** — rutas `auth/recuperar` y `auth/reset-password`, token 1h, `Mailer::resetPassword()`, vistas standalone
- **Variables de entorno** — parser `.env` integrado en `config.php`, `.env.example` documentado, `getenv()` con fallbacks para DB/API keys/email/URL

## Sesión 2026-06-25 — Tareas completadas (sesión 5)

- **Checkout API Mercado Pago (Bricks)** — `EcommerceModel::findById()`; `TiendaController::pagoBrick()` (GET — muestra página de pago con Brick y botón fallback Checkout Pro); `TiendaController::procesarPagoBrick()` (POST — crea pago vía MP API `/v1/payments`, gestiona aprobado/pendiente/rechazado, registra en `pagos_mercadopago`, descuenta stock, envía email de confirmación); helper `mpStatusMsg()` con mensajes en español por `status_detail`; vista `tienda/pago_brick.php` con MP Bricks `CardPayment` SDK JS, botón fallback Checkout Pro si existe `mp_preference_id`; `carrito.php` actualizado (botón → "Confirmar y pagar", redirect post-checkout → `tienda/pago/brick?id=`); nuevas rutas `GET tienda/pago/brick` + `POST tienda/pago/procesar`

## Sesión 2026-06-25 — Tareas completadas (sesión 4)

- **Permisos por módulo UI** — Tabla `permisos` (ya en schema); `PermisosModel` con `MODULOS` const, `getMatrix()`, `saveAll()`; `PermisosController` (admin-only, GET + POST/JSON); vista matriz con checkboxes por rol×módulo, toggle-all, acciones rápidas; `can()` en helpers.php actualizado con backward-compat (permisos vacíos → acceso total); aliases `canView()` / `canEdit()`; sidebar completamente condicional via `canView()`; enlace "Permisos" en sección admin del sidebar; rutas `GET permisos` + `POST permisos/guardar`
- **AFIP WSFE (factura electrónica)** — `AfipWsaa`: obtiene Ticket de Acceso firmando TRA con `openssl_pkcs7_sign()`, cachea en `storage/afip/ta_cache_{modo}.json`; `AfipWsfe`: `solicitarCae()` llama a `FECompUltimoAutorizado` + `FECAESolicitar` vía SoapClient, calcula IVA agrupado por alícuota (21%→Id5, 10.5%→Id4, etc.), resuelve DocTipo por CUIT/DNI/consumidor final; columnas AFIP en `ventas` (`afip_cae`, `afip_cae_vto`, etc.) + migración ALTER TABLE; `VentaModel::updateAfip()`; `VentasController::guardar()` integra el flujo post-save; CAE visible en modal POS, ticket 80mm y vista detalle; constantes `AFIP_*` en config.php + `.env.example`; directorio `storage/afip/`

## Sesión 2026-06-25 — Tareas completadas (sesión 3)

- **WhatsApp / email desde ticket** — `share-bar` en `ventas/ticket.php` con botones WhatsApp (wa.me + texto enriquecido) y mailto; botón WhatsApp en modal post-venta del POS; normalización teléfono Argentina (10 dígitos → 54XXXXXXXXXX)
- **Historial de compras por proveedor** — filtro `proveedor_id` ya existía en `ComprasController::index()`; se agregó botón en `proveedores/index.php` y header contextual en `compras/index.php`
- **PDF individual de factura de compra** — `ComprasController::imprimir()`, vista standalone `compras/imprimir.php` (A4, `@page`, `onload="window.print()"`); enlace "Imprimir / PDF" en `compras/ver.php`
- **Inventario inicial de apertura** — `StockController::apertura()` filtra ítems con `stock_actual ≤ 0`; `guardarApertura()` llama a `procesarInventario('Inventario inicial de apertura')`; vista `stock/apertura.php` con DataTable, inputs numéricos, submit AJAX; rutas `GET stock/apertura` + `POST stock/apertura/guardar`
- **Gráfico de evolución de stock** — `StockModel::historialItem()` consulta `stock_movimientos` por tipo+id+rango; `StockController::historialItem()` AJAX endpoint; sección en `stock/reporte.php` con selector de ítem, pickers de fecha, Chart.js 4.4 line chart con tooltips enriquecidos (movimiento + motivo)

## Sesión 2026-06-25 — Tareas completadas (sesión 2)

- **Costo promedio ponderado** — `FacturaCompraModel::actualizarStock($item, true)`: UPDATE atomico CPP en SQL; actualiza `costo_unitario`/`precio_costo` al recibir; anulaciones no recalculan costo
- **Historial de versiones de recetas** — tabla `recetas_versiones`; `RecetaModel::guardarVersion()` + `findVersiones()`; snapshot enriquecido con nombres de ingredientes; `RecetasController::versiones()`; vista accordion; botón historial en listado; ruta `GET recetas/versiones`
- **Precache Service Worker** — CACHE_NAME `dewoc-v2`; 4 paginas PHP en PRECACHE_URLS: login, dashboard, ventas/pos, stock
- **QR / Codigo de barras por producto** — ruta `GET productos/qr`; `ProductosController::qr()`; vista standalone con qrcode.js + JsBarcode (CDN); tabs QR/barcode; descarga PNG/SVG; boton en listado de productos
- **Backup de base de datos** — `BackupController` (solo rol admin); dump PHP puro sin exec(); streaming con flush(); INSERT INTO con PDO::quote(); registra en audit_log; ruta `GET admin/backup` + `POST admin/backup/download`; enlace en sidebar
