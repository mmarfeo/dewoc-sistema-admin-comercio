# POS Offline — Checklist de desarrollo

> Objetivo: que el POS (`ventas/pos.php`) siga permitiendo **vender** aunque se corte
> internet en el local. Fuera de alcance a propósito: facturación AFIP offline (requiere
> conexión sí o sí para pedir el CAE) y sincronización automática — la sincronización de
> ventas pendientes es una acción manual que decide el usuario, nunca algo que corra solo.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Fase 1 — Estado de conexión y UX ✅
- [x] Detectar online/offline (`navigator.onLine` + eventos `online`/`offline`)
- [x] Indicador visible en el POS ("🟢 Conectado" / "🔴 Sin conexión")
- [x] Deshabilitar el switch "Facturar por AFIP" cuando está offline (se desmarca solo)

## Fase 2 — Catálogo local (IndexedDB) ✅
- [x] Base IndexedDB (`dewoc_pos_offline`: productos, categorías, ventas_pendientes) — `assets/js/pos-offline.js`
- [x] Endpoint nuevo `GET /ventas/catalogo-offline` (`VentaModel::catalogoOffline()` + `VentasController::catalogoOffline()`) que trae todos los productos activos + categorías
- [x] Al abrir el POS con conexión, `cargarCategoriasPos()` refresca el catálogo local en segundo plano
- [x] `cargarCategoriasPos()`, `seleccionarCategoria()`, `buscarProductos()` intentan el servidor primero; si falla (o el Service Worker devuelve el 503 sintético), leen de IndexedDB

## Fase 3 — Carrito ✅
- [x] Armar carrito, descuentos y totales ya eran 100% cliente — sin cambios
- [x] `buscarCliente()` ahora tolera el fallo de red: muestra un aviso y la venta sigue como "Consumidor Final" en vez de romper con una excepción sin manejar

## Fase 4 — Guardar venta offline ✅
- [x] Si el POST a `/ventas/guardar` no llega a completarse (fetch rechazado por falta de conexión — no por un error de validación del servidor, eso se sigue mostrando como antes), se guarda la venta en IndexedDB (`PosOffline.guardarVentaPendiente()`) con un `local_id` propio
- [x] Descuento de stock local optimista (`PosOffline.descontarStockLocal()`) por cada ítem vendido offline
- [x] `facturar_afip` se fuerza a `0` al sincronizar una venta offline (`PosOffline.sincronizarTodo()`)
- [x] Ticket offline: se arma 100% en el navegador (`construirHtmlTicketOffline()`, sin pegarle al servidor) — "COMPROBANTE INTERNO — Sin validez fiscal — pendiente de sincronizar", sin CAE ni QR

## Fase 5 — Sincronización manual ✅
- [x] Badge con el contador de pendientes junto al botón "🕓 Pendientes" en el POS
- [x] Modal `modalVentasPendientes` con el listado (fecha, cantidad de ítems, total)
- [x] Botón "🔄 Sincronizar ahora" (`sincronizarPendientes()`) — nunca se dispara solo
- [x] Errores por venta individual se muestran en su fila sin perder el resto de la cola; al primer corte de red durante el envío, corta el lote y deja el resto para el próximo intento

## Fase 6 — Backend ✅
- [x] `/ventas/guardar` no necesitó cambios: ya aceptaba `items_json` + campos sueltos por `FormData`, que es exactamente lo que reconstruye `PosOffline.sincronizarTodo()`
- [ ] Condiciones de carrera de stock al sincronizar varias ventas offline juntas: **no se blindó** — el servidor descuenta stock en el orden en que llegan los `POST`, igual que cualquier venta normal; si dos cajas offline vendieron de más del mismo producto, la segunda en sincronizar puede dejar stock negativo (aceptado como limitación conocida, ver abajo)

## Fase 7 — Service Worker ✅
- [x] `sw.js` (`dewoc-v11`) ahora cachea el shell de `ventas/pos` en cada visita online exitosa, y lo sirve si falla el fetch estando offline (antes mostraba la página en blanco "Sin conexión")
- [x] `assets/js/pos-offline.js` agregado a `PRECACHE_URLS`

## Fase 8 — Pruebas ⬜
- [ ] Vender con la red desconectada (probar con las DevTools → Network → Offline, y también apagando el wifi de verdad)
- [ ] Cerrar y reabrir el navegador con ventas pendientes (debe persistir — es IndexedDB, no memoria)
- [ ] Reconectar y sincronizar manualmente
- [ ] Simular stock insuficiente al sincronizar (vender el mismo producto offline en dos pestañas/dispositivos y sincronizar ambas)
- [ ] Recargar `/ventas/pos` estando offline (debe abrir el shell cacheado, no "Sin conexión")

> Pendiente de correr por quien pruebe manualmente en el navegador — no se puede simular
> una desconexión de red real desde acá.

---

## Notas de diseño que quedaron implícitas

- **Sin AFIP offline**: el CAE depende de un webservice externo de AFIP: no hay forma de
  obtenerlo sin conexión. Toda venta guardada offline se sincroniza siempre con
  `facturar_afip=0`; si se necesita el comprobante fiscal, hay que facturarla aparte una
  vez sincronizada (fuera de alcance de este checklist).
- **Sin reintentos automáticos**: la sincronización es 100% manual — el usuario abre
  "🕓 Pendientes" y aprieta "Sincronizar ahora" cuando quiere. Nada corre en segundo plano.
- **Alcance acotado al POS**: el resto del sistema (reportes, compras, stock, etc.) sigue
  requiriendo conexión — no es parte de este trabajo.
- **`turno_id` se resuelve al sincronizar, no al momento de la venta offline**: como
  `VentasController::guardar()` ya consultaba el turno abierto en el momento del `POST`
  (no algo que viaje desde el cliente), una venta offline queda asociada al turno que esté
  abierto cuando se sincroniza, no al que estaba abierto cuando ocurrió la venta real. Si el
  corte de conexión cruza un cambio de turno, el movimiento de caja puede quedar en el turno
  "equivocado" — limitación conocida, no resuelta.
- **Sesión/CSRF**: la sincronización reutiliza el token CSRF de la página cargada en ese
  momento (dura toda la sesión, `SESSION_LIFETIME` = 2hs). Si el corte de conexión duró más
  que eso, sincronizar puede fallar con "tu sesión expiró" — se soluciona recargando la
  página (las ventas pendientes no se pierden, siguen en IndexedDB) y reintentando.
- **Sin caché de clientes**: buscar clientes por nombre/CUIT sigue requiriendo conexión; si
  falla, la venta sigue igual pero como "Consumidor Final" (no se cachea todo el padrón de
  clientes en IndexedDB, se consideró innecesario para el caso de uso).
