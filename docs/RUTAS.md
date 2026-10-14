# Documentación de Rutas — Dewoc Admin Comercio

> Todas las rutas se definen en `index.php` y son resueltas por `core/Router.php`.  
> Las rutas protegidas requieren sesión activa (`requireAuth()`).  
> Las rutas marcadas **[admin]** requieren además `requireRole('admin')`.

---

## Auth

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `auth/login` | AuthController#loginForm | Formulario de login |
| POST | `auth/login` | AuthController#login | Procesar login (+ cookie "Recordarme") |
| GET | `auth/logout` | AuthController#logout | Cerrar sesión (borra cookie "Recordarme") |
| GET | `auth/recuperar` | AuthController#recuperarForm | Formulario "Olvidé mi contraseña" |
| POST | `auth/recuperar` | AuthController#recuperar | Enviar email de restablecimiento |
| GET | `auth/reset-password?token=X` | AuthController#resetPasswordForm | Formulario nueva contraseña |
| POST | `auth/reset-password` | AuthController#resetPassword | Guardar nueva contraseña |

---

## Dashboard

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `` / `dashboard` | DashboardController#index | Panel principal con KPIs |

---

## Usuarios [admin]

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `usuarios` | UsuariosController#index | Listado de usuarios |
| GET | `usuarios/nuevo` | UsuariosController#nuevo | Formulario nuevo usuario |
| POST | `usuarios/crear` | UsuariosController#crear | Crear usuario |
| GET | `usuarios/editar?id=X` | UsuariosController#editar | Formulario editar usuario |
| POST | `usuarios/actualizar` | UsuariosController#actualizar | Actualizar usuario |
| POST | `usuarios/eliminar` | UsuariosController#eliminar | Baja lógica de usuario |
| GET | `usuarios/perfil` | UsuariosController#perfil | Mi perfil |
| POST | `usuarios/cambiar-password` | UsuariosController#cambiarPassword | Cambiar contraseña (AJAX) |
| POST | `usuarios/subir-foto` | UsuariosController#subirFoto | Subir foto de perfil (AJAX) |

---

## Categorías [admin]

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `categorias` | CategoriasController#index | Listado de categorías |
| POST | `categorias/crear` | CategoriasController#crear | Crear categoría (AJAX) |
| POST | `categorias/actualizar` | CategoriasController#actualizar | Actualizar categoría (AJAX) |
| POST | `categorias/eliminar` | CategoriasController#eliminar | Eliminar categoría (AJAX) |

---

## Productos

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `productos` | ProductosController#index | Listado de productos |
| GET | `productos/nuevo` | ProductosController#nuevo | Formulario nuevo producto |
| POST | `productos/crear` | ProductosController#crear | Crear producto |
| GET | `productos/editar?id=X` | ProductosController#editar | Formulario editar producto |
| POST | `productos/actualizar` | ProductosController#actualizar | Actualizar producto |
| POST | `productos/eliminar` | ProductosController#eliminar | Baja lógica de producto |
| GET  | `productos/qr?id=X` | ProductosController#qr | Vista standalone QR + código de barras |

---

## Materias Primas

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `materias-primas` | MateriasPrimasController#index | Listado con ABM |
| GET | `materias-primas/nuevo` | MateriasPrimasController#nuevo | (misma vista que index) |
| POST | `materias-primas/crear` | MateriasPrimasController#crear | Crear MP (AJAX) |
| GET | `materias-primas/editar` | MateriasPrimasController#editar | (misma vista que index) |
| POST | `materias-primas/actualizar` | MateriasPrimasController#actualizar | Actualizar MP (AJAX) |
| POST | `materias-primas/eliminar` | MateriasPrimasController#eliminar | Eliminar MP (AJAX) |
| GET | `materias-primas/historial-precios?id=X` | MateriasPrimasController#historialPrecios | Historial de precios de compra |

---

## Insumos

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `insumos` | InsumosController#index | Listado con ABM |
| POST | `insumos/crear` | InsumosController#crear | Crear insumo (AJAX) |
| POST | `insumos/actualizar` | InsumosController#actualizar | Actualizar insumo (AJAX) |
| POST | `insumos/eliminar` | InsumosController#eliminar | Eliminar insumo (AJAX) |

---

## Clientes

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `clientes` | ClientesController#index | Listado de clientes |
| GET | `clientes/nuevo` | ClientesController#nuevo | Formulario nuevo cliente |
| POST | `clientes/crear` | ClientesController#crear | Crear cliente |
| GET | `clientes/editar?id=X` | ClientesController#editar | Formulario editar cliente |
| POST | `clientes/actualizar` | ClientesController#actualizar | Actualizar cliente |
| POST | `clientes/eliminar` | ClientesController#eliminar | Baja lógica de cliente |
| GET | `clientes/buscar?q=X` | ClientesController#buscar | Búsqueda AJAX (JSON) |

---

## Proveedores

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `proveedores` | ProveedoresController#index | Listado de proveedores |
| GET | `proveedores/nuevo` | ProveedoresController#nuevo | Formulario nuevo proveedor |
| POST | `proveedores/crear` | ProveedoresController#crear | Crear proveedor |
| GET | `proveedores/editar?id=X` | ProveedoresController#editar | Formulario editar proveedor |
| POST | `proveedores/actualizar` | ProveedoresController#actualizar | Actualizar proveedor |
| POST | `proveedores/eliminar` | ProveedoresController#eliminar | Baja lógica de proveedor |

---

## Facturas de Compra

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `compras` | ComprasController#index | Listado de facturas de compra |
| GET | `compras/nueva` | ComprasController#nueva | Formulario nueva factura |
| POST | `compras/guardar` | ComprasController#guardar | Guardar factura |
| GET | `compras/ver?id=X` | ComprasController#ver | Vista de detalle |
| POST | `compras/anular` | ComprasController#anular | Anular factura (AJAX) |
| GET | `compras/buscar-items?q=X&tipo=Y` | ComprasController#buscarItems | Búsqueda de ítems (AJAX, JSON) |

---

## Recetas de Producción

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `recetas` | RecetasController#index | Listado de recetas |
| GET | `recetas/nueva` | RecetasController#nueva | Formulario nueva receta |
| POST | `recetas/guardar` | RecetasController#guardar | Guardar receta |
| GET | `recetas/editar?id=X` | RecetasController#editar | Formulario editar receta |
| POST | `recetas/actualizar` | RecetasController#actualizar | Actualizar receta |
| POST | `recetas/eliminar` | RecetasController#eliminar | Baja lógica de receta |
| GET | `recetas/produccion?id=X` | RecetasController#produccion | Vista "Producir" (validación stock) |
| POST | `recetas/producir` | RecetasController#producir | Ejecutar producción inmediata |
| POST | `recetas/planificar` | RecetasController#planificar | Crear orden planificada (sin stock) |
| GET | `recetas/ordenes` | RecetasController#ordenes | Listado de órdenes de producción |
| POST | `recetas/ordenes/estado` | RecetasController#cambiarEstadoOrden | Cambiar estado de una orden (AJAX) |
| GET | `recetas/buscar-ingredientes?q=X` | RecetasController#buscarIngredientes | Búsqueda de ingredientes (AJAX, JSON) |
| GET | `recetas/versiones?id=X` | RecetasController#versiones | Historial de versiones de una receta |

---

## Ventas y POS

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `ventas` | VentasController#index | Listado de ventas |
| GET | `ventas/pos` | VentasController#pos | Punto de Venta (POS fullscreen) |
| POST | `ventas/guardar` | VentasController#guardar | Registrar venta |
| GET | `ventas/ver?id=X` | VentasController#ver | Vista de detalle |
| POST | `ventas/anular` | VentasController#anular | Anular venta (AJAX) |
| GET | `ventas/ticket?id=X` | VentasController#ticket | Ticket 80mm (impresión) |
| GET | `ventas/comanda?id=X` | VentasController#comanda | Comanda cocina (impresión) |
| GET | `ventas/buscar-productos?q=X` | VentasController#buscarProductos | Búsqueda de productos en POS (AJAX) |

---

## Stock

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `stock` | StockController#index | Resumen de stock actual |
| POST | `stock/ajuste` | StockController#ajuste | Ajuste manual de stock (AJAX) |
| GET | `stock/reporte` | StockController#reporte | Reporte comparativo por período |
| GET | `stock/inventario` | StockController#inventario | Formulario inventario físico |
| POST | `stock/inventario/guardar` | StockController#guardarInventario | Confirmar inventario físico |

---

## Caja y Turnos

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `caja` | CajaController#index | Estado del turno actual |
| POST | `caja/abrir` | CajaController#abrir | Abrir turno |
| POST | `caja/imputar-cobro-pendiente` | CajaController#imputarCobroPendiente | Imputa al turno una venta cobrada en mano sin turno abierto (MenuApp) |
| POST | `caja/cerrar` | CajaController#cerrar | Cerrar turno |
| POST | `caja/movimiento` | CajaController#movimiento | Registrar ingreso/egreso manual |
| GET | `caja/historial` | CajaController#historial | Historial de turnos |

---

## Reportes

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `reportes/ventas` | ReportesController#ventas | Reporte de ventas por período |
| GET | `reportes/compras` | ReportesController#compras | Reporte de compras por período |
| GET | `reportes/stock` | ReportesController#stock | Reporte de movimientos de stock |
| GET | `reportes/caja` | ReportesController#caja | Reporte de caja por turnos |
| GET | `reportes/produccion` | ReportesController#produccion | Reporte de órdenes de producción |

---

## Ecommerce — Admin

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `ecommerce/pedidos` | EcommerceController#pedidos | Listado de pedidos online |
| GET | `ecommerce/pedido?id=X` | EcommerceController#verPedido | Vista de detalle de pedido |
| POST | `ecommerce/estado` | EcommerceController#cambiarEstado | Cambiar estado de pedido (AJAX) |

---

## Ecommerce — Tienda Pública *(sin autenticación)*

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `tienda` | TiendaController#index | Catálogo público |
| GET | `tienda/categoria?id=X` | TiendaController#categoria | Productos por categoría |
| GET | `tienda/producto?id=X` | TiendaController#producto | Detalle de producto |
| GET | `tienda/carrito` | TiendaController#carrito | Vista del carrito |
| POST | `tienda/checkout` | TiendaController#checkout | Procesar checkout (AJAX, JSON) |
| GET | `tienda/pago/pendiente` | TiendaController#pagoPendiente | Retorno MP — pago pendiente |
| GET | `tienda/pago/exito` | TiendaController#pagoExito | Retorno MP — pago exitoso |
| GET | `tienda/pago/fallo` | TiendaController#pagoFallo | Retorno MP — pago fallido |
| POST | `tienda/webhook/mp` | TiendaController#webhookMercadoPago | Webhook IPN Mercado Pago |

---

## Chat IA

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `chat` | ChatController#index | Interfaz de Chat IA |
| POST | `chat/mensaje` | ChatController#mensaje | Enviar mensaje (AJAX, JSON) |

---

## Pedidos de MenuApp en el POS

Permiso `ventas_pos`. Dewoc **no decide**: aceptar, rechazar, proponer y escribir se le piden
a MenuApp con `core/MenuAppClient.php` (firmado, ver 3.9). Etapa 9 del checklist.

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `menuapp-pedidos/pendiente-para-mi` | MenuAppPedidosController#pendienteParaMi | Sondeo de 30 s: pedido más viejo esperando acción |
| GET | `menuapp-pedidos/hay-mensajes-sin-leer` | MenuAppPedidosController#hayMensajesSinLeer | Aviso no bloqueante de mensajes nuevos |
| GET | `menuapp-pedidos/lista` | MenuAppPedidosController#lista | Esperando acción / en curso / terminados hoy |
| GET | `menuapp-pedidos/mensajes` | MenuAppPedidosController#mensajes | Lee una conversación de MenuApp y pone los no leídos en cero |
| GET | `menuapp-pedidos/catalogo` | MenuAppPedidosController#catalogoParaPropuesta | Productos del menú con sus grupos de gustos, para armar una propuesta |
| GET | `menuapp-pedidos/respuestas-rapidas` | MenuAppPedidosController#respuestasRapidas | La lista que el comercio edita en MenuApp |
| POST | `menuapp-pedidos/aceptar` | MenuAppPedidosController#aceptar | Le pide a MenuApp que acepte (puede dar `sin_stock` o `caja_cerrada`) |
| POST | `menuapp-pedidos/rechazar` | MenuAppPedidosController#rechazar | Rechaza con motivo; MenuApp reembolsa si estaba pagado |
| POST | `menuapp-pedidos/proponer` | MenuAppPedidosController#proponer | Propone otro pedido al cliente, con IDs de Dewoc |
| POST | `menuapp-pedidos/mensaje` | MenuAppPedidosController#enviarMensaje | Escribe en la conversación del cliente o del repartidor |

---

## Fotos del menú

Permiso `fotos_menu` para proponer candidatas; `fotos_menu_publicar` para elegir la oficial
que ve el cliente. Ver `docs/integracion-menuapp-checklist.md`, etapa 8.

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `galeria/fotos-menu` | FotosMenuController#index | Productos del menú, los sin foto primero |
| GET | `galeria/fotos-menu/producto?id=X` | FotosMenuController#producto | Vista previa con los dos recortes de MenuApp |
| POST | `galeria/fotos-menu/subir` | FotosMenuController#subirCandidata | Sube una candidata (máx. 3 por producto) |
| POST | `galeria/fotos-menu/mejorar` | FotosMenuController#mejorar | Mejora una candidata con IA (opciones fijas) |
| POST | `galeria/fotos-menu/oficial` | FotosMenuController#elegirOficial | Publica la foto en el menú **[fotos_menu_publicar]** |
| POST | `galeria/fotos-menu/descartar` | FotosMenuController#descartar | Descarta una candidata |
| POST | `galeria/fotos-menu/volver-anterior` | FotosMenuController#volverAnterior | Restaura la foto oficial previa **[fotos_menu_publicar]** |

---

## Integraciones [admin]

Ver `docs/integracion-menuapp-checklist.md`.

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `integraciones` / `integraciones/menuapp` | IntegracionesController#menuapp | Pantalla de la integración con MenuApp |
| POST | `integraciones/generar-token` | IntegracionesController#generarToken | Genera/regenera el token de la API (se muestra una sola vez) |
| POST | `integraciones/guardar` | IntegracionesController#guardar | URL y secreto del webhook, producto de envío, menú público |
| POST | `integraciones/activar` | IntegracionesController#activar | Activa o desactiva la integración |
| POST | `integraciones/probar` | IntegracionesController#probar | Envía un webhook `ping` firmado |

---

## API de MenuApp (sin sesión — token Bearer)

No pasan por `core/Router.php`: se resuelven en un bloque propio de `index.php`, antes del
dispatch (el Router no sabe extraer un parámetro del medio de la URL). **No usan sesión ni
CSRF**: cada acción valida `Authorization: Bearer <token>` con `requireApiToken()`.

Respuestas JSON UTF-8. Códigos: `401` token inválido o integración apagada · `404`
inexistente · `409` falta stock · `422` datos inválidos · `429` más de 120 req/min.

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `api/menuapp/v1/estado` | ApiMenuAppController#estado | Identidad de la instalación (UUID fijo, comercio, CUIT) y si la caja está abierta |
| GET | `api/menuapp/v1/catalogo[?desde=ISO8601]` | ApiMenuAppController#catalogo | Catálogo del menú online (con `?desde`, solo los cambios; los despublicados vienen con `visible: false`) |
| GET | `api/menuapp/v1/disponibilidad` | ApiMenuAppController#disponibilidad | Qué productos y gustos se pueden vender ahora |
| POST | `api/menuapp/v1/pedidos` | ApiMenuAppController#pedidos | Pedido nuevo para atender en el POS (idempotente; NO registra la venta) |
| POST | `api/menuapp/v1/pedidos/{referencia}/novedades` | ApiMenuAppController#novedades | Cambio de estado, mensaje nuevo o repartidor asignado |
| POST | `api/menuapp/v1/ventas` | ApiMenuAppController#ventas | Registra un pedido aceptado como venta (idempotente por `referencia`; no mueve la caja; `409 caja_cerrada` si no hay turno abierto) |
| POST | `api/menuapp/v1/ventas/{referencia}/cobrar` | ApiMenuAppController#cobrar | Pedido entregado y cobrado en mano: entra a la caja del turno abierto |
| POST | `api/menuapp/v1/ventas/{referencia}/anular` | ApiMenuAppController#anular | Cancela un pedido ya aceptado: repone la MP y revierte el cobro |

---

## Administración [admin]

| Método | Ruta | Controlador#Método | Descripción |
|--------|------|-------------------|-------------|
| GET | `audit-log` | AuditLogController#index | Log de auditoría de acciones críticas |
| GET  | `admin/backup` | BackupController#index | Panel de backup (solo admin) |
| POST | `admin/backup/download` | BackupController#download | Descargar dump SQL (solo admin) |

---

*Generado: 2026-06-25 · Total: ~78 endpoints*
