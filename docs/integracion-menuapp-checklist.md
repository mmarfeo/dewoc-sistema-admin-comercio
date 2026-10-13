# Integración con MenuApp — checklist de tareas en Dewoc

> Documento de trabajo para implementar en **este** proyecto (Dewoc, `erpcomercio.dewoc.com`)
> lo que necesita MenuApp (`menuapp.dewoc.com`, Laravel, otro repositorio) para usar a Dewoc
> como fuente del catálogo, del stock y de las ventas. Redactado el 2026‑10‑12 a partir de
> la revisión del código de ambos proyectos. Leer junto con `docs/OVERVIEW-PARA-INTEGRACION.md`.
>
> Primer comercio: **Heladería Serendipia**.
>
> **Actualización 2026‑10‑12:** si los precios cargados incluyen o no IVA pasa a ser una
> **opción configurable en Dewoc** (D9, tarea 0.6). Ya no se deduce de la condición fiscal.
>
> **Actualización 2026‑10‑13:** el menú abre y cierra con la **caja de Dewoc** (D10 a D13). Se suman
> `GET /estado`, los avisos `caja.abierta`/`caja.cerrada` y la **etapa 6**. El licenciamiento queda como
> **etapa 7**, para antes de vender Dewoc a terceros. MenuApp ya implementó su parte.

---

## 1. Objetivo y decisiones ya tomadas

MenuApp es el menú online, la toma de pedidos (delivery, retiro y mesa) y el cobro online.
Dewoc sigue siendo el sistema de gestión. Se conectan así:

```
DEWOC (maestro)                              MENUAPP (vidriera + pedidos)
 productos, precio online, imágenes,  ──▶     menú público (campos de solo lectura)
 categorías, grupos de opciones
 disponibilidad calculada con MP      ──▶     agotados automáticos
 ventas, stock de MP, caja, reportes  ◀──     pedido ACEPTADO = venta en Dewoc
```

| # | Decisión |
|---|----------|
| D1 | **Dewoc es la única fuente de verdad del catálogo.** Nombre, descripción, precio, imagen, categoría y gustos se editan solo en Dewoc. MenuApp los muestra bloqueados. |
| D2 | La marca `productos.ecommerce` pasa a significar **"Mostrar en el menú online (MenuApp)"**. Se reutiliza la columna: los productos que hoy están en la tienda quedan marcados. |
| D3 | Nuevo **precio online** opcional por producto. Si está vacío, se usa el precio de venta del local. |
| D4 | **No** se agrega precio extra por opción o gusto, por ahora. |
| D5 | La venta se registra en Dewoc, y se descuenta la MP, **cuando el comercio ACEPTA el pedido** en MenuApp. |
| D6 | Caja: lo **cobrado online por Mercado Pago** suma a ventas pero **no a la caja física** del turno. **Efectivo y transferencia** entran a la caja **recién cuando se entrega** el pedido. |
| D7 | **Se elimina la tienda online de Dewoc** (`/tienda` y `/ecommerce`). MenuApp la reemplaza. El **Portal de clientes mayoristas NO se toca**, porque es otro circuito y no depende de la tienda. |
| D8 | Dewoc también se vende a otros comercios. La conexión es **por API con token por instalación**: nada de leer la base de datos de Dewoc desde MenuApp, ni al revés. |
| D10 | **El menú online recibe pedidos solo con una caja abierta en Dewoc** (al menos un turno abierto, `CajaModel::hayTurnoAbierto()`). Con la caja cerrada, el menú se ve pero un aviso impide pedir. Si Dewoc no responde, MenuApp usa el último estado conocido durante 10 minutos y después cierra. |
| D11 | **Una instalación de Dewoc se conecta a un solo comercio de MenuApp.** Al conectar, MenuApp comprueba la identidad de la instalación con `GET /estado`. |
| D12 | **La contratación de Dewoc la controla MenuApp:** un plan que incluya Dewoc, la prueba gratis, o la habilitación manual del super administrador. Si el comercio deja de tenerlo, la integración queda en pausa y el comercio maneja el menú desde MenuApp. Al reactivarla, Dewoc vuelve a mandar sobre el catálogo. |
| D13 | **Promoción cruzada:** MenuApp ofrece Dewoc a quien no lo tiene, y Dewoc ofrece MenuApp en su pantalla de Integraciones. |
| D9 | **Si los precios incluyen IVA es una opción de configuración de Dewoc**, por comercio: "Los precios de venta cargados **incluyen IVA**" o "**no incluyen IVA** (se suma al vender)". Rige para `precio_venta` y para `precio_online` por igual, y la respetan **todos** los módulos (POS, Portal mayorista, Chat IA, reportes y MenuApp). Es independiente de la condición fiscal: la condición define si el comprobante **discrimina** IVA, y esta opción define si el precio cargado **ya lo trae**. |

---

## 2. Datos de Dewoc relevantes (para no re‑investigar)

- **`productos`:**
  - Columnas: `id`, `codigo` (único y opcional), `nombre`, `descripcion`, `categoria_id`, `tipo` (elaborado/reventa), `precio_venta`, `iva`, `stock_actual`, `controla_stock`, `imagen`, `ecommerce`, `activo`, `eliminado`, `updated_at`.
  - Hay que usar **`id`** como identificador externo, no `codigo`, que puede estar vacío o cambiar.
- **IVA, comportamiento actual que cambia con D9:**
  - Hoy no hay una opción. El POS deduce todo de la constante `NEGOCIO_CONDICION_IVA` (`.env`, leída en `VentasController.php:146`).
  - Si es `responsable_inscripto`, trata `precio_venta` como **neto** y **suma** el IVA (`ventas/pos.php`, `actualizarTotales()`). Si no, lo trata como precio final.
  - El IVA de cada línea se calcula **en el navegador** (`subtotal_con_iva`) y `VentaModel::registrar()` lo guarda tal cual. Por eso hay que cambiar el front **y** el servidor.
  - Otros lugares que usan `iva_porcentaje` o la condición fiscal: `VentasController`, `PortalController`, `PedidoClienteModel`, `ClienteCatalogoModel`, `ChatController`, `app/Views/chat/index.php`, `cron/reporte_margen_producto.php` y los comprobantes AFIP. Antes de cambiar, buscar con `grep -rn "iva_porcentaje\|ES_RESPONSABLE_INSCRIPTO\|esResponsableInscripto\|NEGOCIO_CONDICION_IVA"`.
  - La configuración fiscal vive en la tabla `configuracion_fiscal` (una fila, `id = 1`) y en `ConfiguracionFiscalModel`.
  - **MenuApp siempre necesita el precio final** que paga el cliente.
- **`categorias`:** tiene jerarquía (`padre_id`) y `tipo` (producto/materia_prima/insumo). MenuApp tiene un solo nivel.
- **Opciones:**
  - `grupos_opciones` es **compartido** entre productos.
  - `producto_grupos_opciones` define, por producto, `minimo_selecciones`, `maximo_selecciones`, `obligatorio` y `orden`.
  - `opciones` liga cada gusto a `materia_prima_id`/`insumo_id` con `cantidad_consumida`.
  - `grupos_opciones` tiene `tipo_seleccion` y `permite_repetir`.
- **Venta:** se registra con `VentaModel::registrar($cabecera, $detalle, $confirmarSinStock)`.
  - Si falta MP, devuelve `requiereConfirmacion` + `faltantes` **sin registrar nada**.
  - Las opciones van por línea en `$item['opciones'] = [{opcion_id, cantidad_elegida}]`.
  - `ventas.usuario_id` es obligatorio. Solo se mueve la caja si viene `turno_id`.
- **Anulación:** `VentaModel::anular($id)` repone stock y MP.
- **Imágenes:** se guardan en `uploads/` (ver `ProductosController::subirImagen()`). El `.htaccess` deja `uploads/` público.
- **Tienda a eliminar:**
  - Controladores y modelo: `TiendaController`, `EcommerceController`, `EcommerceModel`.
  - Vistas: `app/Views/layouts/tienda.php` y las de tienda/ecommerce.
  - Rutas en `index.php` (bloque "Ecommerce", incluido `tienda/webhook/mp`).
  - Tablas `pedidos_ecommerce` y `pedidos_ecommerce_detalle`.
  - El ítem del menú lateral (`sidebar.php`) y el módulo `ecommerce` en `PermisosModel`.

---

## 3. Contrato de la API (lo que MenuApp va a consumir)

Reglas generales:
- **URL base:** `https://erpcomercio.dewoc.com/api/menuapp/v1/...`
- **Autenticación:** `Authorization: Bearer <token>`.
  - El token se genera en la pantalla de la tarea 0.4.
  - En la base se guarda **solo su hash** (por ejemplo, `password_hash` o SHA‑256), nunca el token en claro.
- **Sin sesión ni CSRF:** estas rutas no usan `$_SESSION` ni `Csrf::check()`. Validan el token al inicio de cada acción, con un helper tipo `requireApiToken()`.
  - El router no tiene middleware, así que hay que llamarlo a mano en cada acción.
- **Respuestas:**
  - Siempre JSON en UTF‑8, con montos como número con 2 decimales y fechas en ISO 8601 con zona `-03:00`.
  - Códigos de error: `401` si el token es inválido, `404` si no existe, `409` si falta stock (con detalle) y `422` si los datos son inválidos.
- **Límite:** 120 requests por minuto por token, como mínimo con un contador simple en la base de datos.
- **Registro:** cada llamada queda en `storage/logs/app.log` con `logApp()` y nivel `info`, **sin** el token.

### 3.0 `GET /estado` — identidad de la instalación y caja (etapa 6)

MenuApp lo llama al conectar (para validar), cada minuto (para abrir o cerrar el menú) y al reactivar.

```json
{
  "instalacion": { "id": "6f1c9a52-…", "comercio": "Heladería Serendipia", "cuit": "20-12345678-9" },
  "caja": { "abierta": true, "desde": "2026-10-13T09:02:00-03:00", "turnos_abiertos": 1 },
  "menuapp": { "webhook_url": "https://menuapp.dewoc.com/webhooks/dewoc/serendipia" },
  "version": "1.1.0",
  "generado_en": "2026-10-13T12:00:00-03:00"
}
```

- **`instalacion.id`:** un identificador **único y fijo** de esta instalación, un UUID que se genera una sola vez (tarea 6.1). Nunca cambia, aunque cambie el token. MenuApp lo usa para que una instalación no se conecte a dos comercios.
- **`instalacion.comercio` y `instalacion.cuit`:** salen de la configuración del negocio (`NEGOCIO_NOMBRE` y el CUIT). MenuApp se los muestra al comercio para confirmar con quién se conectó.
- **`caja.abierta`:** `true` si hay al menos un turno abierto (`CajaModel::hayTurnoAbierto()`). `desde` es la apertura del turno abierto más viejo.
- **`menuapp.webhook_url`:** la URL de avisos cargada en la pantalla de Integraciones, o `null`. Si MenuApp ve la URL de **otro** comercio, rechaza la conexión.

### 3.1 `GET /catalogo` — catálogo completo

Devuelve todo lo que tiene `ecommerce = 1`, `activo = 1` y `eliminado = 0`, más las categorías y grupos que usan esos productos.
Con `?desde=2026-10-12T10:00:00-03:00` devuelve **también** los productos que cambiaron o se desactivaron desde esa fecha, para que MenuApp pueda ocultarlos.

```json
{
  "generado_en": "2026-10-12T18:30:00-03:00",
  "comercio": { "nombre": "Heladería Serendipia", "moneda": "ARS" },
  "categorias": [
    { "id": 12, "nombre": "Helados", "padre_id": 3, "padre_nombre": "Elaborados", "orden": 1 }
  ],
  "productos": [
    {
      "id": 345,
      "codigo": "HEL-1KG",
      "nombre": "Helado 1 kg",
      "descripcion": "Hasta 4 gustos",
      "categoria_id": 12,
      "precio": 18500.00,
      "imagen_url": "https://erpcomercio.dewoc.com/uploads/productos/abc.jpg",
      "imagen_version": "2026-10-10T11:02:00-03:00",
      "visible": true,
      "actualizado_en": "2026-10-10T11:02:00-03:00",
      "grupos": [
        {
          "id": 7,
          "nombre": "Gustos",
          "minimo": 1,
          "maximo": 4,
          "obligatorio": true,
          "permite_repetir": false,
          "orden": 1,
          "opciones": [
            { "id": 71, "nombre": "Dulce de leche", "orden": 1, "activa": true }
          ]
        }
      ]
    }
  ]
}
```

- **`precio`:** es el **precio final al consumidor**, con IVA:
  - base = `precio_online` si tiene valor; si no, `precio_venta`;
  - si la opción D9 dice "**incluyen IVA**", `precio` = base;
  - si dice "**no incluyen IVA**", `precio` = base × (1 + `iva`/100), redondeado a 2 decimales;
  - calcularlo **siempre** con el helper de la tarea 0.6, nunca repitiendo la cuenta.
- **`visible: false`:** solo aparece con `?desde`. Significa que el producto se desmarcó, se desactivó o se eliminó, y MenuApp lo oculta sin borrarlo.
- **`imagen_version`:** cambia solo cuando cambia la imagen. Se puede usar el `updated_at` del producto o el `filemtime` del archivo. MenuApp vuelve a descargar la imagen solo si cambió.
- **Gustos de un grupo:** van solo las opciones activas. MenuApp copia el grupo dentro de cada producto. Dewoc no tiene que cambiar su modelo compartido.

### 3.2 `GET /disponibilidad` — qué se puede vender ahora

```json
{
  "generado_en": "2026-10-12T18:30:00-03:00",
  "productos": [ { "id": 345, "disponible": true } ],
  "opciones":  [ { "id": 71, "disponible": false, "motivo": "Sin stock de Dulce de leche" } ]
}
```

Reglas, reutilizando la lógica que ya existe:
- **Producto con `controla_stock = 1`:** disponible si `stock_actual >= 1`.
- **Producto con `controla_stock = 0` y receta propia:** disponible si `RecetaModel::verificarFaltantesParaVenta(receta, 1)` no devuelve faltantes.
- **Producto con `controla_stock = 0` sin receta:** siempre disponible.
- **Opción ligada a MP o insumo:** disponible si el stock de esa MP o insumo alcanza para **una** selección. Usar el mismo cálculo que `VentaModel::_necesarioOpcion()` para un producto de cantidad 1.

### 3.3 `POST /ventas` — registrar un pedido aceptado

MenuApp lo llama cuando el comercio acepta el pedido. **Tiene que ser idempotente:** si llega dos veces la misma `referencia`, devuelve la venta ya creada y no duplica nada.

```json
{
  "referencia": "MA-12-A7K2",
  "canal": "delivery",
  "mesa": null,
  "metodo_pago": "mercadopago",
  "cobrado_online": true,
  "cliente": { "nombre": "Juan Pérez", "telefono": "1155556666", "email": "juan@mail.com" },
  "items": [
    {
      "producto_id": 345,
      "cantidad": 1,
      "precio_unitario": 18500.00,
      "opciones": [ { "opcion_id": 71, "cantidad_elegida": 1 } ]
    }
  ],
  "costo_envio": 1500.00,
  "descuento": 1000.00,
  "total": 19000.00,
  "notas": "Timbre 2B"
}
```

- **`referencia`:** tiene la forma `MA-{id del comercio en MenuApp}-{código del pedido}` y nunca supera los 40 caracteres.
- **`canal`:** puede ser `delivery`, `retiro` o `mesa`. Si es `mesa`, el campo `mesa` trae el nombre de la mesa para `ventas.mesa`. En delivery, `notas` incluye la dirección de entrega.
- **`metodo_pago`:** `mercadopago`, `efectivo` o `transferencia`. Coincide con el enum de `ventas.metodo_pago`.
- **`precio_unitario`:** MenuApp siempre lo manda **final, con IVA**, sin importar la opción D9. Dewoc separa neto e IVA con el helper de la tarea 0.6 (`neto = final / (1 + iva/100)`) y lo guarda con el mismo criterio que una venta del POS.
- **`total`:** el total registrado en Dewoc **tiene que coincidir** con el de MenuApp, con una tolerancia de ±0,01. Si no coincide, responder `422`.

Respuestas:
- **`201` o `200`:** `{ "venta_id": 9876, "referencia": "MA-12-A7K2" }`.
- **`409`:** falta MP. Ejemplo: `{ "error": "sin_stock", "faltantes": [ { "tipo": "opcion", "id": 71, "nombre": "Dulce de leche" } ] }`.
  - Se llama a `registrar(..., confirmarSinStock: false)`, **nunca** con `true`. Así MenuApp no acepta un pedido que no se puede preparar, y el comercio lo edita o lo rechaza.
- **`409` con la caja cerrada (etapa 6):** `{ "error": "caja_cerrada" }`, sin registrar nada. Se controla **antes** de validar stock.
  - **Excepción:** si la `referencia` ya existe, se devuelve la venta existente aunque la caja esté cerrada. Es un reintento de algo que se aceptó con la caja abierta.
  - MenuApp no acepta el pedido y cierra el menú al instante.

### 3.4 `POST /ventas/{referencia}/cobrar` — pedido entregado y cobrado en mano

Solo para `efectivo` y `transferencia`. Registra el ingreso en la caja del turno abierto en ese momento, como un `caja_movimientos` de tipo `ingreso` con `venta_id` y su `metodo_pago`.
- **Si no hay turno abierto:** la venta queda marcada **"cobro pendiente"** y aparece en Caja para imputarla a mano al abrir el turno siguiente.
- **Idempotente:** si se llama dos veces, el segundo llamado no hace nada.

### 3.5 `POST /ventas/{referencia}/anular` — pedido cancelado después de aceptado

Llama a `VentaModel::anular()`, que repone la MP. Si ya estaba cobrada en caja, revierte también ese ingreso. Es idempotente. El reembolso de Mercado Pago lo hace MenuApp.

### 3.6 Aviso de cambios de Dewoc a MenuApp (webhook)

Cuando cambia algo del catálogo, Dewoc avisa y **MenuApp vuelve a pedir `/catalogo?desde=...`**. El aviso no lleva los datos: así es simple y no importa si llega dos veces.

- **Método y URL:** `POST <webhook_url>`. La URL se configura en la pantalla 0.4, con la forma `https://menuapp.dewoc.com/webhooks/dewoc/{slug-del-comercio}`.
- **Cuerpo:** `{"evento":"catalogo.cambio","entidad":"producto","id":345,"ocurrido_en":"..."}`. `entidad` puede ser `producto`, `categoria` o `grupo_opciones`.
- **Firma:** header `X-Signature: hex(HMAC-SHA256(cuerpo crudo, webhook_secret))`. Es el mismo esquema que MenuApp ya usa en su webhook de stock.
- **No debe frenar a Dewoc:** timeout de 3 segundos y, si falla, solo se registra en el log. MenuApp además consulta cada 5 minutos, así que un aviso perdido se recupera solo.
- **Caja (etapa 6):** al abrir un turno, si es el primero abierto, enviar `{"evento":"caja.abierta"}`. Al cerrar el **último** turno abierto, enviar `{"evento":"caja.cerrada"}`. Con varios turnos, el menú sigue abierto mientras quede alguno abierto. MenuApp además consulta `/estado` cada minuto.
- **Disponibilidad:** **no** hace falta avisar cada movimiento de stock. MenuApp consulta `/disponibilidad` periódicamente y Dewoc vuelve a validar al aceptar (3.3).

---

## 4. Tareas

> **Estado al 2026‑10‑13:** etapas 0 a 4 y **6** implementadas en el código. Queda
> **pendiente de ejecución** la migración `database/menuapp_integracion_migration.sql`
> sobre la base, y las tareas que son trabajo de datos o de otro sistema: **1.4** (revisar
> los productos de Serendipia), la sección 5 (pruebas end‑to‑end, que necesitan la base
> migrada), la **etapa 5** (eliminar la tienda online), que el propio plan deja para el
> final, y la **etapa 7** (licenciamiento), que el plan marca como futura.

### Etapa 0 · Preparar Dewoc

- [x] **0.1 Migración `database/menuapp_integracion_migration.sql`.** Tiene que ser idempotente, como las demás:
  - `productos.precio_online DECIMAL(12,2) NULL` (NULL = usar el del local). Se carga con el **mismo criterio de IVA** que `precio_venta` (D9).
  - `configuracion_fiscal.precios_incluyen_iva TINYINT(1) NULL`. NULL = "todavía no configurado": se mantiene el comportamiento actual (ver tarea 0.6).
  - `ventas.origen VARCHAR(20) NOT NULL DEFAULT 'pos'` (`pos`, `portal`, `menuapp`).
  - `ventas.referencia_externa VARCHAR(40) NULL` + `UNIQUE` (garantiza la idempotencia).
  - `ventas.cobro_pendiente TINYINT(1) NOT NULL DEFAULT 0`.
  - Tabla `integracion_menuapp`: `id`, `activa`, `token_hash`, `token_ultimos4`, `webhook_url`, `webhook_secret`, `usuario_id` (el usuario técnico), `ultimo_acceso_at`, `ultimo_error`, `created_at`, `updated_at`.
- [x] **0.2 Renombrar en la interfaz "Ecommerce" a "Mostrar en el menú online (MenuApp)":**
  - en `app/Views/productos/form.php` (checkbox `chkEcommerce`);
  - en el listado y en la importación masiva (`productos/index.php`, columna `ecommerce (1/0)` → `menu_online (1/0)`, aceptando también el nombre viejo);
  - en las descripciones de las herramientas del **Chat IA** (`ChatController.php`, cerca de las líneas 1469 y 1493).
  - La columna de la base **no se renombra**.
- [x] **0.3 Campo "Precio online"** en la ficha del producto, debajo del precio de venta.
  - Ayuda dinámica según la opción D9: "Precio del menú online, **con IVA incluido**" o "**sin IVA**: se suma al vender". Vacío = el mismo del local.
  - Debajo, mostrar en vivo **"El cliente va a ver: $ X"**, con el precio final calculado.
  - Guardarlo en `ProductosController` (alta y edición).
  - Agregarlo a la importación masiva y a las herramientas del Chat IA que crean o editan productos.
- [x] **0.4 Pantalla "Integraciones → MenuApp"**, solo para admin:
  - **Generar o regenerar token:** se muestra **una sola vez**, después solo los últimos 4 caracteres.
  - **Campos:** URL del webhook y secreto. El secreto lo genera MenuApp y se pega acá.
  - **Botón "Probar conexión":** envía un webhook `evento: "ping"`.
  - **Estado:** último acceso de MenuApp y último error.
  - **Desactivar la integración:** apaga la API y los avisos.
  - **Usuario técnico:** crear al activar un usuario **"MenuApp (sistema)"**, sin posibilidad de iniciar sesión, para usarlo en `ventas.usuario_id`.
- [x] **0.5 Permiso nuevo** `integraciones` en `PermisosModel::MODULOS`.
- [x] **0.6 Opción "Los precios incluyen IVA" (D9).** Es la tarea más delicada de la etapa 0, porque cambia cómo cobra el POS:
  - **Configuración:**
    - En la pantalla de configuración fiscal, dos opciones: "Los precios de venta cargados **incluyen IVA**" y "**No incluyen IVA**: se suma al vender".
    - Guardarlo en `configuracion_fiscal.precios_incluyen_iva` con un método nuevo de `ConfiguracionFiscalModel`.
    - Mostrar un aviso al cambiarla: "Cambia el precio que pagan tus clientes en el local y en el menú online. Revisá tus precios".
    - Registrar el cambio en `AuditLog`.
  - **Valor por defecto, para no cambiar nada sin querer:**
    - Mientras esté en NULL, usar el comportamiento de hoy: Responsable Inscripto = "no incluyen"; cualquier otra condición = "incluyen".
    - La pantalla lo muestra preseleccionado así, hasta que el usuario lo guarde.
  - **Helper único:** por ejemplo, `core/PrecioHelper.php`. Todos los módulos lo usan y nadie vuelve a hacer la cuenta por su lado:
    - `preciosIncluyenIva(): bool`;
    - `precioFinal($precio, $iva)`, que devuelve el precio con IVA;
    - `desglosar($precioFinal, $iva)`, que devuelve neto e IVA.
  - **Separar dos conceptos que hoy van juntos:**
    - si el precio **trae** IVA es la opción D9;
    - si el comprobante **discrimina** IVA sigue siendo la condición fiscal (`NEGOCIO_CONDICION_IVA`).
    - Un Responsable Inscripto con "incluyen IVA" cobra el precio tal cual y lo desglosa en neto + IVA en el comprobante. Un monotributista no discrimina IVA en ningún caso.
  - **Adaptar todos los lugares que calculan IVA** (la lista está en la sección 2):
    - POS en el front: `actualizarTotales()` y el armado de cada línea;
    - el servidor de la venta: que `VentaModel::registrar()` **recalcule** neto e IVA con el helper en lugar de confiar en lo que manda el navegador;
    - el Portal mayorista;
    - el Chat IA (herramientas que venden o informan precios);
    - el ticket y el comprobante AFIP;
    - el reporte de margen, que debe comparar costo contra precio **neto**.
  - **Pruebas obligatorias:** para "incluye" y "no incluye", combinadas con Responsable Inscripto y monotributista (4 casos), comprobar:
    - que el total del POS coincide con el total guardado en `ventas`;
    - que el ticket coincide con ese total;
    - que el comprobante AFIP coincide con ese total.

### Etapa 1 · API de catálogo (importación inicial)

- [x] **1.1 Controlador `ApiMenuAppController`:**
  - Con `requireApiToken()` (compara el hash, revisa `activa` y actualiza `ultimo_acceso_at`).
  - Las rutas van en `index.php`, **antes** del dispatch y separadas del resto con un comentario.
  - Deben responder JSON incluso ante una excepción (ya lo hace el handler global para `Accept: application/json`).
- [x] **1.2 `GET /api/menuapp/v1/catalogo`** según 3.1. El precio final sale de `PrecioHelper::precioFinal()` (tarea 0.6), que respeta la opción D9.
- [x] **1.3 Verificar en el `.htaccess`:**
  - que `/api/...` llegue a `index.php`;
  - que el header `Authorization` llegue a PHP. En Apache o LiteSpeed puede perderse; si pasa, agregar `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`.
- [ ] **1.4 Revisar los productos de Serendipia** que van al menú:
  - que tengan la marca, imagen, categoría y gustos bien cargados;
  - que los productos internos, los insumos y lo que no se vende online **no** tengan la marca.

### Etapa 2 · Avisos de cambios

- [x] **2.1 Helper `core/MenuAppNotifier.php`:** firma con HMAC y hace un `POST` con cURL y timeout de 3 segundos. Si la integración está inactiva, no hace nada. No lanza excepciones: solo registra en el log.
- [x] **2.2 Llamarlo después de guardar** en todos los puntos que cambian el catálogo:
  - `ProductosController`: alta, edición, eliminar, activar/desactivar, imagen e importación masiva (en este caso, **un solo** aviso al final).
  - `CategoriasController`: alta, edición y eliminación.
  - `OpcionesGrupoController`: grupos, opciones y asignación a productos.
  - `ChatController`: las herramientas que crean o editan productos.
  - `RecetasBorradorController`: si crea productos.

### Etapa 3 · Disponibilidad

- [x] **3.1 `GET /api/menuapp/v1/disponibilidad`** según 3.2, reutilizando `RecetaModel` y la lógica de opciones de `VentaModel`. Sin duplicar el cálculo: si hace falta, extraer un método público compartido.

### Etapa 4 · Ventas desde MenuApp

- [x] **4.1 `POST /ventas`** según 3.3:
  - Arma `$cabecera` y `$detalle` en el formato de `VentaModel::registrar()`.
  - **Cabecera:**
    - `usuario_id` = usuario técnico, `origen` = `menuapp`, `referencia_externa` = referencia;
    - **sin `turno_id`**, para que no mueva la caja al aceptar (D6);
    - `cobro_pendiente = 1` si el pago es efectivo o transferencia.
  - **Envío:** usar un producto **"Envío a domicilio"** con `controla_stock = 0` y sin receta, que se elige en la pantalla 0.4. Así queda en los reportes.
  - **Descuento:** convertir el monto a `descuento_global` %, o agregar una columna de monto, de forma que el total cierre exacto con el de MenuApp.
  - **Facturación:** `facturar_afip = 0` por ahora.
- [x] **4.2 `POST /ventas/{referencia}/cobrar`** según 3.4.
- [x] **4.3 `POST /ventas/{referencia}/anular`** según 3.5.
- [x] **4.4 Reportes y caja:**
  - Las ventas con `origen = 'menuapp'` cuentan en los reportes de ventas aunque no tengan `turno_id`. **Hay que verificar** que los reportes no filtren por turno.
  - En el listado de ventas, mostrar una etiqueta "MenuApp".
  - En Caja, mostrar las ventas con `cobro_pendiente = 1`.
- [x] **4.5 Ventas que se ven en el POS:** las de MenuApp aparecen en el historial, pero **no** se pueden editar desde el POS. Anularlas desde Dewoc debe avisar que la anulación correcta se hace desde MenuApp, para que también se reembolse.

### Etapa 5 · Eliminar la tienda online

> Hacerla **al final**, cuando MenuApp ya esté vendiendo para Serendipia. Así el comercio nunca queda sin tienda online.

- [ ] **5.1 Antes de borrar:**
  - confirmar que no queden pedidos en `pedidos_ecommerce` sin resolver;
  - exportar ese historial a Excel y guardarlo;
  - en Mercado Pago, quitar la URL de notificaciones que apunta a `tienda/webhook/mp`.
- [ ] **5.2 Quitar:**
  - las rutas `tienda/*` y `ecommerce/*` de `index.php`;
  - `TiendaController`, `EcommerceController` y `EcommerceModel`;
  - `app/Views/layouts/tienda.php` y las vistas de tienda y ecommerce;
  - el ítem del menú lateral;
  - el módulo `ecommerce` de `PermisosModel` (y su ruta de inicio);
  - las referencias en `app/Views/docs/index.php`.
- [ ] **5.3 Tablas `pedidos_ecommerce*`:** **no** borrarlas por ahora; dejarlas solo para consulta. Marcarlas como obsoletas en `schema.sql` con un comentario.
- [ ] **5.4 `MP_ACCESS_TOKEN` y `MP_PUBLIC_KEY`:** si ningún otro módulo los usa, eliminarlos de la configuración. Hoy solo los usa `TiendaController`. Los cobros online quedan en MenuApp, con las credenciales de cada comercio.
- [ ] **5.5 Redirección:** `/tienda` → 301 al menú del comercio en MenuApp (configurable en la pantalla 0.4).

---

### Etapa 6 · Caja e identidad de la instalación (D10 a D13)

- [x] **6.1 Identidad de la instalación:**
  - Generar **una sola vez** un UUID y guardarlo en una fila de configuración, por ejemplo una columna `instalacion_id` en la tabla `integracion_menuapp` (migración idempotente).
  - Si no existe, generarlo al primer uso, y **no regenerarlo nunca**, ni al regenerar el token.
  - Agregar el CUIT del negocio a la respuesta, desde la constante o configuración donde ya esté (si no existe, dejar `null`).
- [x] **6.2 `GET /api/menuapp/v1/estado`** según 3.0, con `requireApiToken()`. Agregar la ruta al bloque de `index.php` que despacha la API.
- [x] **6.3 Avisos de caja** en `core/MenuAppNotifier.php`: `cajaAbierta()` y `cajaCerrada()`, con la misma firma y el mismo timeout que `catalogoCambio()`.
  - Llamarlos desde `CajaController`/`CajaModel` **después** de abrir o cerrar el turno.
  - `caja.cerrada` solo si ya no queda ningún turno abierto (`! hayTurnoAbierto()`).
  - `caja.abierta` solo si es el primer turno abierto.
- [x] **6.4 Bloquear ventas con la caja cerrada:** en `ApiMenuAppController::ventas()`, si la referencia **no** existe todavía y `! hayTurnoAbierto()`, responder `409 {"error":"caja_cerrada"}` (ver 3.3).
- [x] **6.5 Aviso al cerrar la caja con pedidos de MenuApp en curso:** si hay ventas de origen `menuapp` con `cobro_pendiente = 1`, mostrar antes de confirmar el cierre: "Tenés N pedidos de MenuApp sin cobrar. Si cerrás, quedan como cobro pendiente para el próximo turno". Se puede cerrar igual.
- [x] **6.6 Pantalla Integraciones → MenuApp:**
  - mostrar el identificador de la instalación y el estado de la caja tal como lo ve MenuApp;
  - **promoción (D13):** si la integración no está activa, mostrar una tarjeta "Vendé online con MenuApp", con sus beneficios (menú con fotos, pedidos por delivery, retiro y mesa, cobro con Mercado Pago) y un enlace a `https://menuapp.dewoc.com`.

### Etapa 7 · Licenciamiento (futuro: antes de vender Dewoc a un cliente externo)

> No hace falta para Serendipia. Si Dewoc pasa a ser multiempresa
> (`docs/propuesta-saas-multiempresa-chat-first.md`), esta etapa se reemplaza por la suscripción de
> cada empresa dentro del sistema.

- [ ] **7.1 La licencia la controla MenuApp,** que ya tiene planes, cobro con Mercado Pago y suspensión por falta de pago:
  - una vez por día, Dewoc consulta a MenuApp por su `instalacion_id` si la licencia está activa (endpoint a definir en MenuApp);
  - un cliente que solo use Dewoc igual tiene su cuenta en MenuApp para el cobro.
- [ ] **7.2 Si la licencia vence:**
  - primero, unos días de aviso dentro de Dewoc;
  - después, **solo lectura**: no deja cargar ventas nuevas ni compras;
  - **nunca** bloquea el acceso a los datos, las facturas emitidas ni los reportes, ni su exportación.
- [ ] **7.3 Sin conexión con MenuApp:** Dewoc sigue funcionando normal varios días (por ejemplo, 7). Un corte de internet no puede frenar la caja de un comercio.

---

## 5. Pruebas (en `tests/run.php` o a mano en local)

> Ya hay cobertura automática en la suite (`php tests/run.php`), que no necesita base de
> datos y por eso corre siempre:
> - `tests/unit/PrecioHelperTest.php` — los 4 casos de D9, que `neto + iva` dé exactamente
>   el total cobrado, el default histórico y el precio del menú online (39 aserciones).
> - `tests/unit/ApiMenuAppTest.php` — que ninguna acción de la API quede sin validar el
>   token, que el token nunca se guarde ni se loguee en claro, la idempotencia por
>   referencia, que la venta se registre sin `turno_id`, que nunca se llame a
>   `registrar()` con `confirmarSinStock = true`, y que el webhook no pueda frenar ni
>   romper un guardado (73 aserciones).
>
> Lo de abajo sigue pendiente: son las pruebas de punta a punta, que necesitan la base ya
> migrada y un comercio con datos reales.

- [ ] **Token:** sin token o con uno incorrecto, `401`; con la integración desactivada, `401`.
- [ ] **Opción de IVA (D9):** los 4 casos de la tarea 0.6 (incluye/no incluye × Responsable Inscripto/monotributista). Con la opción en NULL, el POS cobra exactamente lo mismo que antes del cambio.
- [ ] **Catálogo:**
  - el precio con y sin `precio_online`;
  - el precio con la opción en "incluyen IVA" y en "no incluyen IVA": en los dos casos `precio` es el final, y coincide con lo que cobra el POS por el mismo producto;
  - un producto desmarcado aparece con `visible: false` en `?desde`.
- [ ] **Disponibilidad:**
  - se descuenta la MP de un gusto hasta 0 y la opción pasa a `disponible: false`;
  - se carga una compra y vuelve a estar disponible.
- [ ] **`POST /ventas`:**
  - con MP suficiente, crea la venta, descuenta la MP y **no** mueve la caja;
  - repetir la misma referencia devuelve la misma venta sin duplicar;
  - sin MP responde `409` y no registra nada;
  - el total distinto responde `422`;
  - con un comercio Responsable Inscripto, el neto y el IVA guardados suman el total que mandó MenuApp, con cualquiera de las dos opciones D9.
- [ ] **Cobro:**
  - `cobrar` con turno abierto registra el ingreso en ese turno;
  - sin turno, la venta queda con cobro pendiente;
  - llamarlo dos veces no duplica.
- [ ] **Anulación:** repone la MP y es idempotente.
- [ ] **Webhook:** se envía con una firma válida, y si MenuApp está caído no frena el guardado del producto.
- [ ] **Caja (etapa 6):**
  - `/estado` devuelve siempre el mismo `instalacion.id`, también después de regenerar el token;
  - abrir el primer turno envía `caja.abierta`, y cerrar el último envía `caja.cerrada`; con dos turnos, cerrar uno no envía nada;
  - con la caja cerrada, `POST /ventas` responde `409 caja_cerrada` y no registra nada; un reintento de una referencia ya registrada devuelve la venta.

---

## 6. Lo que hace MenuApp (otro repositorio, para no duplicarlo acá)

> **Estado: implementado en MenuApp el 2026‑10‑12** y probado contra un Dewoc simulado con los
> ejemplos de la sección 3. Detalle en `docs/integracion-dewoc.md` del repo de MenuApp.
> Cualquier cambio en el contrato de la sección 3 hay que hacerlo en los dos proyectos.

- **Nuevo conector "Dewoc"** en `StockManager::DRIVERS`, con URL, token y secreto del webhook.
- **Importación inicial** y vinculación por ID de Dewoc en categorías, productos, grupos y opciones. Los campos que vienen de Dewoc quedan bloqueados en su panel.
- **Recepción del webhook** `POST /webhooks/dewoc/{slug}` y consulta cada 5 minutos de `/catalogo?desde` y `/disponibilidad`.
- **Llamadas de pedidos:**
  - `POST /ventas` al aceptar; si recibe `409`, muestra los faltantes y no acepta;
  - `cobrar` al entregar los pedidos en efectivo o transferencia;
  - `anular` al cancelar uno ya aceptado.
- **Descarga y optimización de imágenes** cuando cambia `imagen_version`.

Orden recomendado entre los dos proyectos:
1. Dewoc, etapa 0 y etapa 1.
2. MenuApp: conector e importación.
3. Dewoc, etapa 2; luego MenuApp, avisos.
4. Dewoc, etapas 3 y 4; luego MenuApp, disponibilidad y ventas.
5. Probar con Serendipia en paralelo.
6. Dewoc, etapa 5.
