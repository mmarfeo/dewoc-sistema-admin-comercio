# Socios y Pagos a Proveedores — Checklist de desarrollo

> Objetivo: poder ver en `/pagos-proveedores` cuánto pagó cada socio y cuánto pagó la
> empresa, con una tarjeta por cada uno, para poder controlar fácilmente que
> socio 1 + socio 2 + socio 3 + socio 4 + empresa = total pagado.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Contexto (verificado antes de armar esto)

- `pagos_proveedores` ya tenía `origen_pago` ENUM(`empresa`,`socio`) y `nombre_socio`
  (texto libre) — agregados en `comprobantes_compra_setup.sql`. "Empresa" es un
  valor de ese enum, no una entidad nueva: el sistema sigue siendo de un solo local.
- Ya existía `PagoProveedorModel::aportesSociosEnPeriodo()`, usado en el Resumen
  Económico, agrupando por `nombre_socio` de texto libre.
- **Encontrado durante el desarrollo**: `origen_pago`/`nombre_socio` no solo se
  cargan desde `pagos-proveedores/form.php` — también desde `compras/form.php`
  (registrar/editar un pago inline dentro de una factura de compra), que ya tenía
  un `<select>` de "socio" pero poblado con la tabla `usuarios` completa (todos los
  usuarios del sistema, no un listado de socios real). Se corrigió también ese
  formulario para usar la tabla `socios` nueva.

## Fase 1 — Modelo de datos ✅
- [x] Tabla `socios` (`id, nombre, activo, eliminado, created_at`)
- [x] `pagos_proveedores.socio_id` (FK nullable a `socios`, `ON DELETE SET NULL`) —
  convive con `nombre_socio` (que ahora funciona como snapshot legible, no como
  fuente de verdad)
- [x] `database/socios_migration.sql`

## Fase 2 — ABM de Socios ✅
- [x] `app/Models/SocioModel.php` (`findAll`, `findAllActivos`, `tienePagos`)
- [x] `app/Controllers/SociosController.php` + `app/Views/socios/index.php` (modal,
  mismo patrón que Categorías — sin importación, no hacía falta para 4 socios)
- [x] Ruta `/socios` + entrada en el sidebar (Maestros) + módulo `socios` agregado a
  `PermisosModel::MODULOS` (antes no existía ni siquiera para `pagos-proveedores`)
- [ ] Cargar los 4 socios reales desde la pantalla nueva (paso manual, no de código)

## Fase 3 — Formularios que registran pagos ✅
- [x] `pagos-proveedores/form.php`: `nombre_socio` (texto libre) → `<select name="socio_id">`
- [x] `compras/form.php`: los DOS selects de socio que ya existían (`pr_socio_select`
  para pago nuevo, `ep_socio_select` para editar un pago) pasan de listar
  `usuarios` a listar `socios`, por id en vez de por nombre
- [x] `PagosProveedoresController::guardar()`/`actualizar()`: reciben `socio_id`,
  resuelven el nombre actual del socio y lo guardan también en `nombre_socio`
  (snapshot) — `PagoProveedorModel` actualizado para persistir ambos campos
- [x] `FacturaCompraModel::getPagosDeFactura()`: agregado `pp.socio_id` al SELECT
  (lo necesita el JS de `compras/form.php` para preseleccionar el socio al editar)

## Fase 4 — Migrar datos existentes ⬜ (pendiente de correr, ya escrito)
- [x] `database/socios_migrar_datos.sql`: crea un socio por cada `nombre_socio`
  distinto que no matchee ya uno existente (case-insensitive) y vincula los pagos
  viejos por `socio_id`
- [ ] Correrlo y revisar la tabla `socios` resultante (fusionar/renombrar a mano si
  quedó algún nombre raro de datos viejos)

## Fase 5 — Backend: resumen para las tarjetas ✅
- [x] `PagoProveedorModel::resumenPorOrigen(array $filtros)`: usa el MISMO
  criterio de filtros que `listar()` (proveedor/tipo/desde/hasta/estado), así la
  suma de las tarjetas es, por construcción, igual al total general — no hace
  falta que el usuario sume nada a mano, es una comprobación automática
  - Sin filtro de estado explícito, solo cuenta pagos `activo` (un pago anulado no
    es plata que realmente salió) — a diferencia de `listar()`, que sin filtro
    muestra todo. Si el usuario filtra por estado explícitamente, se respeta tal cual.
  - Devuelve también `sin_asignar`: aportes de socio sin `socio_id` (pagos viejos
    todavía no migrados, o de un socio dado de baja) — para que la cuenta siga
    cerrando aunque falte la Fase 4

## Fase 6 — Vista: tarjetas en pagos-proveedores/index.php ✅
- [x] Una tarjeta por socio activo, una de "Empresa", una de "Sin asignar" (solo si
  hay algo ahí) y una de "Total pagado" con ✓/⚠ según si cuadra
- [x] Las tarjetas ya respetan los filtros de fecha/proveedor/tipo/estado que
  existían en la página (mismo formulario, no uno nuevo)

## Fase 7 — Verificación ✅
- [x] `php -l` de todos los archivos tocados/creados (todos OK)
- [x] Revisado que `ResumenEconomicoController`/`resumen-economico/index.php` (que
  ya consumía `aportesSociosEnPeriodo()`) siguen funcionando sin cambios — la forma
  de los datos que devuelve no cambió, solo mejoró el agrupamiento interno

---

## Fase 8 — Deuda de la empresa hacia los socios + Reembolsos ✅

> Hasta acá, `origen_pago='socio'` solo alimentaba las tarjetas de "cuánto puso cada
> uno" (Fase 5/6) — no generaba ninguna deuda real. Esta fase le da uso real a esa
> plata: pasa a ser una deuda pendiente de la empresa hacia ese socio, visible en
> "Saldos pendientes de pago" (`/pagos-proveedores`), que se salda registrando un
> reembolso — mismo patrón que pagar una factura de proveedor.

- [x] `database/reembolsos_socios_migration.sql`:
  - `pagos_proveedores.tipo` gana un 4to valor, `aporte` — para un aporte de capital
    "libre" de un socio, sin atarlo a pagar una factura/gasto/sueldo puntual (siempre
    `origen_pago='socio'`, forzado server-side)
  - `reembolsos_socios`: lo que la EMPRESA le devuelve a un socio (mismo desglose de
    medios de pago que `pagos_proveedores`)
  - `reembolsos_socios_aportes`: contra qué aporte(s) puntuales (filas de
    `pagos_proveedores`) se aplica cada reembolso — mismo patrón que
    `pagos_facturas`/`pagos_gastos`
- [x] `app/Models/ReembolsoSocioModel.php`: `guardar()`, `aplicarAAporte()`,
  `getAportesPendientesBySocio()`, `getDeudaPorSocio()`, `anular()`, etc. — espejo de
  `PagoProveedorModel`
- [x] `PagosProveedoresController`: soporte para `tipo=aporte` en `guardar()` +
  acciones nuevas (`reembolsoNuevo`/`reembolsoGuardar`/`reembolsoVer`/
  `reembolsoAnular`/`aportesPendientes`/`reembolsoComprobante`)
- [x] `pagos-proveedores/form.php`: 4ta opción "Aporte de socio" en Tipo de pago
- [x] `pagos-proveedores/reembolso-form.php` + `reembolso-ver.php` (nuevas)
- [x] `pagos-proveedores/index.php`: tabla "Deuda con socios" (mismo estilo que
  "Saldos pendientes de pago" de proveedores, pero separada — son deudas de
  naturaleza distinta) + botón "+ Reembolsar a Socio"
- [x] Histórico incluido a propósito: `getDeudaPorSocio()`/`getAportesPendientesBySocio()`
  no filtran por fecha, así que TODO pago ya cargado con `origen_pago='socio'` cuenta
  como deuda pendiente desde el momento en que se corre la migración — no hace falta
  ningún backfill aparte
- [x] `compras/form.php` no necesitó tocarse: su pago inline con socio ya pega contra
  `pagos-proveedores/guardar` (mismo endpoint), así que ya queda cubierto
- [x] Flexibilidad de reembolso confirmada — parcial (monto editable por aporte),
  por un aporte puntual (checkbox por fila), por un importe X suelto (sin marcar
  ningún aporte al cargarlo), o por el total (tildar todos, cada uno precarga su
  saldo completo): todo ya cubierto por la tabla de aportes de `reembolso-form.php`
- [x] `vincularAporte()` + `pagos-proveedores/vincular-aporte`: para el caso de un
  reembolso cargado "suelto" (importe X sin aplicar a nada) — permite volver
  después y aplicarlo, de a partes, contra uno o varios aportes puntuales, mismo
  patrón que `vincular()` ya tenía para pagos a facturas de proveedor. Modal
  "+ Vincular a aporte" en `reembolso-ver.php`, visible solo si el reembolso tiene
  saldo sin aplicar y el socio tiene aportes pendientes.
- [x] `php -l` de todos los archivos tocados/creados (todos OK)

## Pendiente (no es código, son pasos manuales)

1. Correr `database/socios_migration.sql` en phpMyAdmin.
2. Correr `database/socios_migrar_datos.sql` en phpMyAdmin.
3. Entrar a `/socios` y revisar que los socios migrados desde datos viejos tengan
   nombres razonables (fusionar duplicados si quedó alguno raro).
4. Si hacen falta socios nuevos que nunca se usaron en un pago viejo (ej. un socio
   que recién ahora arranca a aportar), cargarlos ahí directamente.
5. Correr `database/reembolsos_socios_migration.sql` en phpMyAdmin (Fase 8).

## Notas de diseño que quedaron implícitas

- **"Empresa" no es una entidad nueva**: sigue siendo simplemente el otro valor del
  enum `origen_pago` que ya existía.
- **Socio no depende de `usuarios`**: un socio puede no tener cuenta para loguearse.
  Si además opera la caja, tiene sus dos registros independientes.
- **`nombre_socio` no se eliminó**: queda como snapshot del nombre al momento del
  pago (útil si un socio se renombra después) y por si algún reporte viejo lo sigue
  leyendo directo. La fuente de verdad para agrupar es `socio_id`.
- **Se encontró y corrigió de paso** el mismo problema en `compras/form.php`, que
  ya tenía un selector de "socio" pero poblado incorrectamente desde la tabla
  `usuarios` — quedó unificado con el ABM nuevo.
