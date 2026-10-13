# POS — Cliente buscador + Cuenta Corriente — Checklist de desarrollo

> Objetivo: (1) confirmar/terminar de resolver el campo de cliente en el POS, y (2) sumar
> "Cuenta Corriente" como medio de pago, para ventas a un cliente habitual que puede
> pagarlas el mismo día, en la semana o en el mes — llevando un saldo pendiente por cliente,
> igual que ya existe para lo que se le debe a un proveedor.

Leyenda: ✅ Hecho · ⬜ Pendiente · 🔍 Necesita confirmación antes de programar

---

## Contexto (verificado en código antes de armar esto)

- **El campo "Cliente" del POS ya es un buscador con autocompletado, no un input de texto
  plano.** `app/Views/ventas/pos.php:144-153` tiene un input visible `clienteBuscador` que
  dispara `buscarCliente(q)` (debounce 250ms) contra `GET /clientes/buscar`
  (`ClientesController::buscar()` → `ClienteModel::buscar()`, que ya filtra por nombre o
  documento, `activo=1`, límite 15 resultados), muestra un panel `clienteDropdown` con los
  resultados, y al elegir uno carga el `id` en un input oculto `cliente_id` que es lo que
  viaja con la venta. **Ver "Punto 1" más abajo — hace falta confirmar con el usuario qué es
  lo que no le está funcionando**, porque tal como está el código, ya cumple lo pedido.
- **"Cuenta Corriente" para clientes no existe hoy en ningún lado** — ni en el `ENUM
  metodo_pago` de `ventas`, ni una columna `monto_cuenta_corriente`, ni una tabla de saldos.
  Lo que el usuario describe (ventas que se cobran total o parcialmente después, en el día/
  semana/mes) es exactamente lo que ya existe para **proveedores** con `facturas_compra.
  condicion_pago='cuenta_corriente'` + `pagos_proveedores`/`pagos_facturas` — ese mecanismo
  se usa acá como espejo de diseño, no hay que inventar el patrón de cero:
  - `facturas_compra.estado` (`pagada`/`pendiente`) se recalcula solo cuando la suma de
    `pagos_facturas.monto_aplicado` (de pagos `estado='activo'`) alcanza el total.
  - El saldo pendiente **no se guarda como columna**: siempre se calcula al vuelo —
    `total - SUM(monto_aplicado de pagos activos)` — ver `PagoProveedorModel::
    getFacturasPendientesByProveedor()` y `getCuentaCorriente()`.
  - Pantalla existente que mirar como referencia visual/de flujo: `/pagos-proveedores`
    (`PagosProveedoresController` + `app/Views/pagos-proveedores/*`).
- **Pago dividido ya existe en el POS** (`database/ventas_pago_dividido_migration.sql`):
  `ventas` tiene `monto_efectivo/monto_debito/monto_credito/monto_transferencia/
  monto_mercadopago` + `metodo_pago` (que pasa a `'combinado'` si hay más de un medio con
  monto > 0). "Cuenta Corriente" se suma como un 6º medio en ese mismo esquema.
- **Ojo con `VentaModel::registrar()` (líneas 458-491)**: por cada medio con monto > 0 se
  inserta un `caja_movimientos` tipo `'ingreso'` — una venta a cuenta corriente **no** tiene
  que generar ese movimiento (no entró plata a la caja). Además hay un fallback (líneas
  467-471) que, si la suma de los 5 medios conocidos da 0, carga el total completo bajo
  `metodo_pago` tal cual venga — si una venta es 100% cuenta corriente, ese fallback la
  metería igual como ingreso de caja si no se lo excluye explícitamente.

## Decisiones tomadas (respuestas del usuario a las preguntas abiertas)

| Pregunta | Decisión |
|---|---|
| Tope de cuenta corriente | **$100.000 por defecto**, con el administrador pudiendo editarlo **por cliente** (override). |
| Quién ve/registra cobros | **Abierto a cualquiera con acceso al POS** — no hace falta un módulo de permisos nuevo. |
| Texto especial en el ticket | **No** — el ticket no debe mostrar nada distinto cuando la venta queda a cuenta corriente. |
| Medios de cobro en `pagos_clientes` | Los ya propuestos alcanzan (efectivo/débito/transferencia/mercadopago) — **no hace falta** cheque/otro. |
| Punto 1 (campo de cliente) | El usuario ve el mensaje **"Sin conexión: no se puede buscar clientes ahora. La venta sigue como 'Consumidor Final'."** — ver diagnóstico abajo, se encontró la causa real revisando el código. |

---

## Punto 1 — Campo de cliente en el POS: 2 bugs encontrados (no es un pedido de UI nuevo)

Con la respuesta del usuario se pudo reproducir el código exacto — **no hace falta un
`<select>` nuevo, el panel `clienteDropdown` ya es esa lista**; el problema es que nunca
llega a mostrar resultados por dos bugs reales en `app/Views/ventas/pos.php`:

- **Bug A — desajuste de forma de la respuesta (rompe SIEMPRE, con o sin conexión)**:
  `ClientesController::buscar()` (`app/Controllers/ClientesController.php:97-104`) responde
  `{"ok": true, "items": [...]}`, pero `buscarCliente()` en `pos.php:1501-1527` usa la
  respuesta completa como si fuera el array directo (`data.forEach(...)`, `!data.length`).
  Un objeto plano no tiene `.forEach` ni `.length` útil — en el mejor caso (pedido exitoso)
  tira un `TypeError` silencioso (fuera del `try/catch`, que solo cubre el `fetch`+`.json()`)
  y el dropdown simplemente nunca se llena. Esto solo no explica el mensaje "Sin conexión"
  que vio el usuario, pero sí que la búsqueda "no ande" en general.
- **Bug B — el catch-all rotula cualquier falla como "Sin conexión" (esto sí explica el
  mensaje exacto)**: el `catch` de `buscarCliente()` (`pos.php:1507-1511`) no distingue "no
  hay internet" de cualquier otra falla del fetch. El sospechoso más probable: si la sesión
  expiró, `Controller::requireAuth()` hace `redirect('auth/login')` — el `fetch()` sigue esa
  redirección, recibe el HTML del login con `r.ok = true`, y `r.json()` explota al parsear
  HTML como JSON → cae en el `catch` → muestra "Sin conexión" aunque el dispositivo esté
  perfectamente online. Un POS que queda con la pestaña abierta muchas horas (uso típico de
  mostrador) es exactamente el escenario donde la sesión expira en medio del turno.

### Checklist
- [ ] Bug A: cambiar `buscarCliente()` para leer `data.items` (con fallback `|| []`) en vez
  de tratar `data` como el array.
- [ ] Bug B: distinguir en el `catch`/manejo de respuesta entre (a) error de red real
  (`TypeError: Failed to fetch` / `!navigator.onLine`) → mensaje actual de "Sin conexión" es
  correcto ahí; y (b) respuesta no-JSON u otro código (probable sesión expirada) → mensaje
  distinto y más útil (ej. "Tu sesión expiró — recargá la página para volver a entrar") en
  vez de "Sin conexión".
- [ ] Probar con una sesión vencida a propósito (borrar la cookie de sesión o esperar el
  timeout) que el mensaje nuevo sea el correcto y no el de "Sin conexión".
- [ ] Confirmar que, una vez arreglado el Bug A, la búsqueda ya funciona como pide el
  usuario (lista de coincidencias mientras tipea) — no se necesita ningún componente nuevo.

---

## Punto 2 — Cuenta Corriente como medio de pago

### Fase 1 — Modelo de datos ⬜
- [ ] `ALTER TABLE ventas`: agregar `monto_cuenta_corriente DECIMAL(14,2) NOT NULL DEFAULT 0`
  (mismo patrón que `ventas_pago_dividido_migration.sql`) y sumar `'cuenta_corriente'` al
  `ENUM metodo_pago` (que ya incluye `'combinado'`).
- [ ] Tablas nuevas, espejo exacto de `pagos_proveedores`/`pagos_facturas`
  (`database/pagos_setup.sql`) pero del lado de clientes (medios confirmados —
  efectivo/débito/transferencia/mercadopago, sin cheque/otro):
  - `pagos_clientes` (`id, cliente_id, fecha, concepto, monto_efectivo, monto_debito,
    monto_transferencia, monto_mercadopago, monto_total, comprobante_path, notas,
    estado ENUM('activo','anulado'), usuario_id, eliminado, created_at`).
  - `pagos_ventas` (`id, pago_id, venta_id, monto_aplicado`, `UNIQUE(pago_id, venta_id)`) —
    igual que `pagos_facturas`.
- [ ] `cliente_id` en `ventas` ya existe (nullable) — no requiere cambio, pero pasa a ser
  **obligatorio cuando `monto_cuenta_corriente > 0`** (regla de negocio, no de esquema).
- [ ] `ALTER TABLE clientes`: agregar `limite_cuenta_corriente DECIMAL(12,2) NULL` —
  `NULL` = usa el tope por defecto ($100.000, confirmado por el usuario); un valor cargado
  ahí lo pisa (override por cliente, editable por el admin). El default de $100.000 queda
  como constante en código (no hace falta pantalla de configuración global — no se pidió).

### Fase 2 — Backend: registrar la venta ⬜
- [ ] `VentasController::guardar()`: aceptar el 6º medio, validar que si
  `monto_cuenta_corriente > 0` entonces `cliente_id` no puede venir vacío (no se puede
  vender "a cuenta" a un cliente anónimo) — error claro si falta.
- [ ] Validar el **tope de cuenta corriente**: `saldo pendiente actual del cliente +
  monto_cuenta_corriente de esta venta` no puede superar `clientes.limite_cuenta_corriente`
  (o $100.000 si es `NULL`) — rechazar con un error claro indicando el saldo actual y el
  tope, no solo "no se puede".
- [ ] `VentaModel::registrar()`: persistir `monto_cuenta_corriente`; en el loop de
  `caja_movimientos` (líneas 458-491) **no incluir** `cuenta_corriente` en
  `$montosPorMedio`, y ajustar el fallback de suma-cero para que, si la venta es 100% a
  cuenta corriente, NO genere ningún movimiento de caja (hoy ese fallback asume que algo
  siempre entra a la caja).

### Fase 3 — Cobros de cuenta corriente ⬜
- [ ] `app/Models/PagoClienteModel.php` (espejo de `PagoProveedorModel`):
  `aplicarAVenta()`, `getVentasPendientesByCliente()`, `getCuentaCorriente()` — mismo
  cálculo de saldo (`total_con_iva - SUM(monto_aplicado de pagos activos)`), sin guardar un
  campo de saldo aparte.
- [ ] `app/Controllers/CobrosClientesController.php` + vistas (mismo patrón que
  `pagos-proveedores`: listado con saldo por cliente, formulario para registrar un cobro
  aplicándolo a una o varias ventas pendientes, detalle).
- [ ] Rutas nuevas (`/cobros-clientes*`) + entrada en el sidebar. **Sin módulo de permisos
  nuevo** (confirmado): gatear solo con `requireAuth()`/acceso al POS, igual que el resto
  del flujo de venta — no hace falta un `canView`/`canEdit` de un módulo dedicado.
- [ ] `app/Views/clientes/form.php` + `ClientesController::actualizar()`: agregar el campo
  **"Límite de cuenta corriente"** (nullable, placeholder "$100.000 por defecto") — este sí
  queda protegido por el permiso que ya existe para editar clientes (es el admin quien lo
  edita, según lo pedido), no por el permiso nuevo de Cobros (que no existe).

### Fase 4 — POS: UI del medio nuevo ⬜
- [ ] Tarjeta "Cuenta Corriente" junto a las 5 existentes en `pos.php` (mismo componente
  checkbox + monto que ya usan las otras).
- [ ] Si se carga un monto en Cuenta Corriente sin cliente seleccionado, bloquear "Cobrar" y
  avisar (mismo lugar donde hoy se valida que la suma de medios cierre con el total).
- [ ] Si el monto cargado + saldo ya pendiente del cliente supera su tope, avisar en el
  momento (antes de mandar al servidor) — la validación real igual queda del lado backend
  (Fase 2), esto es solo para no hacer esperar al cajero por un error evitable.
- [ ] El ticket/comprobante **no lleva ningún texto especial** (confirmado) — no tocar la
  plantilla de impresión para esto.

### Fase 5 — Reportes / visibilidad ⬜
- [ ] Agregar `monto_cuenta_corriente` a `ReportesModel::resumenVentas()` (hoy suma
  explícitamente los 5 medios existentes) para que el desglose por medio de pago siga
  siendo completo.
- [ ] Tarjeta o reporte de "Cuentas por cobrar" (saldo total pendiente, por cliente) —
  evaluar si va en Resumen Económico, en Reportes, o en la propia pantalla de Cobros.
- [ ] Nota para quien lo implemente: el margen/ganancia (`ReporteSemanalModel::margenBruto()`)
  ya suma `vd.subtotal` sin mirar método de pago — una venta a cuenta corriente **ya cuenta
  como vendida** ahí aunque todavía no se cobró en efectivo. Es el comportamiento correcto
  (devengado, no lo que entró a la caja), pero vale dejarlo documentado para que no se lea
  como inconsistencia entre "ganancia del mes" y "plata que efectivamente entró".

### Fase 6 — Verificación ⬜
- [ ] `php -l` de todo lo tocado/creado.
- [ ] Probar: venta 100% cuenta corriente (sin otro medio) → confirmar que NO se crea
  movimiento de caja. Venta combinada (ej. mitad efectivo, mitad cuenta corriente) →
  confirmar que solo el efectivo genera movimiento de caja. Intentar cargar cuenta
  corriente sin cliente → debe bloquear. Registrar un cobro parcial desde Cobros de
  Clientes → confirmar que el saldo pendiente de esa venta baja lo correcto.
- [ ] Probar el tope: cliente sin `limite_cuenta_corriente` propio → tope $100.000 por
  defecto; cliente con override cargado → se respeta ese valor; intentar una venta que
  haga superar el tope (sola o sumada al saldo ya pendiente) → debe bloquear con el mensaje
  claro, tanto en el aviso del POS como en la validación real del backend.

---

## Fuera de alcance (v1)

- Límite de crédito por cliente (tampoco existe hoy para proveedores — no hay precedente
  en el sistema que copiar).
- Intereses/recargos por mora en cuentas corrientes vencidas.
- Notificaciones automáticas (push/WhatsApp) avisando saldo pendiente a cobrar.

## Preguntas abiertas

Ninguna — todas resueltas (ver "Decisiones tomadas" al principio del documento).
