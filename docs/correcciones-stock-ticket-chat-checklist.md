# Correcciones: Inventario, Comanda, Impresión directa, Chat IA, Sesión — Checklist

> Cinco tareas correctivas pedidas por el usuario, sin relación entre sí — agrupadas en un
> solo checklist para ir tildando. Cada sección tiene el archivo exacto a tocar y el criterio
> de qué significa "terminado".

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## 1. Formulario de inventario: 3 campos en vez de 2 ⬜

**Dónde**: `app/Views/stock/inventario.php` (tabla del formulario), `app/Controllers/StockController.php::inventario()/procesarInventario()`, `app/Models/StockModel.php`.

**Estado actual**: la tabla ya tiene 2 columnas — "Stock sistema" (`data-teorico`, de solo lectura,
`= stock_actual` en ese momento) y "Stock físico" (input editable, lo que carga el usuario). Falta
diferenciar "el último conteo físico que se hizo" del "stock teórico actual".

**A hacer**:
- [ ] Agregar columna **"Último stock informado"** (solo lectura) — el valor del ÚLTIMO conteo físico
  real hecho antes de este, no el stock actual. Fuente de datos: `stock_movimientos` filtrando
  `origen = 'inventario'` para ese `tipo_item`+`item_id`, tomando el `stock_despues` de la fila más
  reciente (`ORDER BY created_at DESC LIMIT 1`). Si no hay ninguna fila (nunca se contó este ítem),
  mostrar "—" o el stock de apertura.
- [ ] Renombrar la columna actual **"Stock sistema"** a **"Stock teórico"** (solo lectura) — ya es
  exactamente lo que pide el usuario ("va restando según las ventas"), porque `stock_actual` ya se
  descuenta en tiempo real con cada venta/compra. No hace falta calcular nada nuevo acá, solo el label.
- [ ] Renombrar el input **"Stock físico"** a **"Stock Nuevo"** — mismo campo, mismo comportamiento
  (el que el usuario carga contando físicamente), solo cambia el texto de la columna.
- [ ] Confirmar que `procesarInventario()` sigue recibiendo `stock_teorico`/`stock_fisico` con los
  nombres de campo que ya espera (`items_json` en el POST) — el rename es solo visual/label, no debería
  requerir tocar el JSON que se manda al backend.

**Verificación**: entrar a `/stock/inventario`, confirmar que aparecen las 3 columnas en ese orden
(Último informado → Stock teórico → Stock Nuevo), que las dos primeras son de solo lectura, y que
guardar un conteo sigue funcionando igual que antes.

---

## 2. Comanda: el texto de comentarios se corta ⬜

**Dónde**: `app/Views/ventas/comanda.php:64-68` (bloque "NOTA").

**Estado actual**:
```php
<?php if ($v['notas']): ?>
<div class="bold">NOTA:</div>
<div style="font-size:13px"><?= e($v['notas']) ?></div>
<div class="line"></div>
<?php endif; ?>
```
Dos problemas probables, a confirmar imprimiendo una comanda con un comentario largo real:
- No usa `nl2br()` — si el comentario tiene saltos de línea (Enter en el textarea del POS), se
  pierden y todo queda pegado en una sola línea.
- No tiene `word-break`/`overflow-wrap` — si el comentario incluye una palabra larga sin espacios, en
  el ancho fijo del ticket térmico (`TICKET_ANCHO_MM`) puede desbordar en vez de partirse, y quedar
  cortada al imprimir.

**A hacer**:
- [ ] Cambiar `<?= e($v['notas']) ?>` por `<?= nl2br(e($v['notas'])) ?>` para respetar los saltos de
  línea que haya escrito el usuario.
- [ ] Agregar al `style` del div: `word-break:break-word; overflow-wrap:break-word;` para que una
  palabra/línea larga se parta en vez de desbordar el ancho del ticket.
- [ ] Revisar si `app/Views/ventas/ticket.php` (el comprobante para el cliente, no la comanda de
  cocina) tiene el mismo patrón de notas sin estos cuidados, y aplicar el mismo fix ahí si corresponde.

**Verificación**: cargar una venta con un comentario largo (con salto de línea y con una palabra sin
espacios de más de ~20 caracteres), imprimir la comanda, confirmar que se ve completo y bien partido.

---

## 3. Imprimir directo (comanda/venta) sin ventana de previsualización ⬜

**Esto no es un cambio de código de la aplicación** — es una limitación de seguridad de los
navegadores: `window.print()` (usado en `app/Views/ventas/pos.php:1359,1416` y en el
`onload="window.print()"` de `comanda.php`/`ticket.php`) SIEMPRE abre el diálogo nativo de impresión
del navegador; no existe una API web estándar para saltear ese diálogo desde JavaScript puro — es
intencional, para que un sitio no pueda mandar a imprimir sin que el usuario lo vea.

**Cómo lograrlo igual** (config del navegador/SO en la PC de venta, no de la app):

- [ ] **Opción recomendada — Chrome/Edge en modo kiosco de impresión**: crear un acceso directo que
  abra el navegador con el flag `--kiosk-printing`, apuntando directo a la URL del POS. Ejemplo en
  Windows (propiedades del acceso directo → Destino):
  ```
  "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing https://tu-dominio/ventas/pos
  ```
  Con ese flag, `window.print()` imprime directo en la impresora predeterminada de Windows, sin ningún
  diálogo — es la forma estándar en que casi todos los POS basados en navegador resuelven esto.
- [ ] Alternativa más permanente (sin depender de un acceso directo puntual): activar la política de
  Chrome `KioskPrintingEnabled` a nivel de Windows (Registro o Políticas de grupo), para que el
  comportamiento quede fijo sin importar cómo se abra el navegador.
- [ ] Configurar en Windows cuál es la **impresora predeterminada** (la térmica de comandas/tickets) —
  con kiosk-printing, siempre imprime ahí sin preguntar.
- [ ] Probar: con el flag activo, hacer una venta de prueba y confirmar que la comanda/ticket sale
  directo, sin ningún diálogo ni click extra.
- [ ] Si más adelante hace falta más control (corte automático de papel, apertura de cajón de dinero
  por comando ESC/POS), la alternativa es un puente de impresión local (ej. QZ Tray) — bastante más
  setup, no hace falta a menos que el kiosk-printing no alcance.

---

## 4. Chat IA: poder editar el número de comprobante ante un aviso de duplicado ⬜

**Dónde**: `app/Views/chat/index.php` (tarjeta de confirmación de compra, header ~línea 729-732),
`app/Controllers/ChatController.php::confirmar()` y `confirmarCompra()` (línea ~1171).

**Estado actual**: el número de comprobante se muestra como texto plano en el header de la tarjeta
(`'Comprobante: ' + tipo + ' ' + nro_comprobante`), no como campo editable. Si `confirmarCompra()`
detecta que ya existe una factura con ese mismo proveedor+número (`FacturaCompraModel::buscarDuplicado()`,
agregado hace poco), bloquea el guardado — pero como el número no se puede corregir desde la tarjeta,
el usuario queda trabado sin poder registrar una segunda compra real al mismo proveedor el mismo día
cuando el proveedor no emite comprobante numerado.

**A hacer**:
- [ ] Convertir el `nro_comprobante` del header de la tarjeta (JS, `chat/index.php`) en un `<input>`
  editable, igual que ya son editables otros campos de la tarjeta (proveedor, método de pago, cantidad/
  precio de cada ítem).
- [ ] Mandar el valor editado en el POST de `confirmar()` (nuevo campo, ej. `nro_comprobante`).
- [ ] En `ChatController::confirmar()`, leer ese campo y pasarlo a `confirmarCompra()` como un nuevo
  parámetro de override (mismo patrón que ya usa `$metodoPagoOverride`), para que el chequeo de
  duplicado y el guardado usen el número corregido, no el original que vino de la imagen/PDF.
- [ ] No hace falta agregar un flag de "confirmar igual" — el mecanismo actual ya deja la tarjeta en
  estado `pendiente` si el guardado falla por duplicado, así que el usuario corrige el número ahí mismo
  y vuelve a apretar "Confirmar" sobre la misma tarjeta.

**Verificación**: provocar el aviso de duplicado (misma factura dos veces), confirmar que aparece el
error con el link a la factura existente, editar el número de comprobante en la tarjeta, confirmar de
nuevo y verificar que esta vez se guarda como una factura distinta.

---

## 5. Evitar cortes de conexión durante una venta (sesión expirada) ✅

Se implementaron las dos opciones juntas, como se pidió: la sesión ahora dura más, Y si igual llega a
cortarse (por cualquier motivo, no solo por duración), aparece un modal de reconexión en vez de perder
lo que se estaba haciendo.

### Opción A — extender la duración de la sesión ✅
- [x] `config/config.php:81`: `SESSION_LIFETIME` de `7200` (2h) a `36000` (10h).
- [x] `core/Session.php` no necesitó cambios — ya usaba la constante tanto para la cookie como para
  `session.gc_maxlifetime`.
- [ ] Pendiente de verificar en producción: que Hostinger no tenga un límite de `session.gc_maxlifetime`
  más corto a nivel de servidor que igual corte la sesión antes de las 10 horas.

### Opción B — modal de reconexión con un solo formulario ✅
- [x] `app/Controllers/Controller.php::requireAuth()`: si la request es AJAX/POST (o pide
  `Accept: application/json`) y no hay sesión ni "Recordarme" válido, responde
  `{ok:false, error:'sesion_expirada', csrf_token:...}` con HTTP 401 en vez de `redirect()`. La
  navegación normal (GET de página completa) sigue redirigiendo igual que antes.
- [x] `app/Controllers/AuthController.php`: refactorizado — `validarCredenciales()` e `iniciarSesion()`
  extraídos como privados, reusados tanto por `login()` (form tradicional) como por el nuevo
  `reconectar()` (JSON, ruta `POST auth/reconectar` en `index.php`).
- [x] `app/Views/layouts/main.php`: modal `#modalReconexion` (usuario precargado con
  `window.APP_USER_EMAIL`, contraseña, un solo botón) — sin botón cancelar ni cierre por click
  afuera/Escape a propósito, porque lo que corresponde acá es reconectar, no perder el estado.
- [x] `assets/js/app.js`: `window.fetch` envuelto una sola vez para TODA la app — detecta el 401 +
  `sesion_expirada` y muestra el modal, sin tener que tocar cada `fetch()` de cada vista. Al reconectar
  con éxito, actualiza `window.APP_CSRF` y el token de los formularios ya visibles en la página, y le
  avisa al usuario con un toast que reintente la acción (no se reintenta sola, para no arriesgar un
  duplicado si la acción original era, por ejemplo, guardar una venta).

**Limitación conocida**: la detección de AJAX en `requireAuth()` cubre pedidos `POST` (la gran mayoría
de las acciones de la app, incluida toda la lógica de ventas/POS) y cualquier `fetch()` que pida
explícitamente `Accept: application/json`. Un `fetch()` por `GET` que NO declare ese header (poco común
en esta app, pero existe en algunas vistas) todavía puede caer en el `redirect()` viejo. Si aparece un
caso así, se agrega puntualmente.

**Verificación**: dejar una venta a medio cargar en el POS, bajar `SESSION_LIFETIME` a un valor chico de
prueba (ej. 10 segundos) o borrar la sesión del lado del servidor, esperar a que expire, intentar
guardar la venta y confirmar que aparece el modal, que reconectar funciona con un solo click, y que
después de reconectar la venta se puede guardar sin recargar la página ni perder el carrito.
