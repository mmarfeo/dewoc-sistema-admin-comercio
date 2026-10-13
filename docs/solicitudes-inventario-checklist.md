# Solicitudes de Inventario (puntuales y recurrentes) — Checklist de desarrollo

> Objetivo: que desde `/solicitudes` el administrador pueda pedir un "Inventario"
> eligiendo categorías y/o productos y/o materias primas y/o insumos y/o ingredientes
> genéricos puntuales, para que al abrir `/stock/inventario` desde esa solicitud el
> usuario asignado solo vea esos ítems (no la lista completa del comercio). Además,
> poder armar plantillas recurrentes semanales (ej. "todos los martes, Helados";
> "miércoles, Panificados"; "jueves, Papelería") que generen esa solicitud solas.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Contexto (ya existente, verificado antes de armar esto)

- `/solicitudes` (`app/Controllers/SolicitudesController.php`) hoy solo maneja 3 tipos:
  `producto`/`materia_prima` (comprar algo puntual) y `tarea` (genérica) — tabla
  `solicitudes` (`tipo`, `item_id`, `item_nombre`, `descripcion`, `prioridad`, `estado`,
  `usuario_id`, `resuelto_por`, `resuelto_at`).
- `/stock/inventario` (`StockController::inventario()`) siempre muestra **todos** los
  ítems con stock vía `StockModel::resumenStock()` (`app/Models/StockModel.php:10-64`),
  sin ningún filtro por categoría ni por ítem — es la lista completa o nada.
- Las tablas `inventarios`/`inventarios_detalle` de `schema.sql` existen pero **no las
  usa ningún código PHP** — el flujo real de conteo persiste directo en
  `stock_movimientos` vía `StockController::procesarInventario()`
  (líneas 157-210, recalcula el teórico server-side, nunca confía en el cliente). Este
  plan no revive esas tablas (fuera de alcance); cerramos el círculo marcando la
  solicitud como `resuelta` al guardar el conteo.
- El proyecto ya resuelve un problema estructuralmente igual — "plantilla recurrente
  que genera un registro real cuando llega su día, sin cron de servidor" — para costos
  fijos mensuales: `app/Models/RecurrenteModel.php` (generación perezosa con reclamo
  atómico anti-duplicados) + `app/Controllers/GastosRecurrentesController.php`,
  disparado desde `Controller::requireAuth()` (líneas 60-76). Este plan adapta el mismo
  patrón a periodicidad **semanal por día** en vez de mensual por día del mes.
- Piezas de catálogo reutilizables, ya existentes: `CategoriaModel::findAllConPadre(?tipo)`,
  `IngredienteGenericoModel::listar()`, `UsuarioModel::findActivos()` (ya se carga y pasa
  a la vista en `SolicitudesController::index()` línea 53).
- Patrón de UI "asignar a usuario puntual o dejar abierto" ya existe para Tareas
  (`app/Views/solicitudes/index.php` líneas 66-81, selector `modo_destino`) — se reusa
  el mismo *look & feel* pero con un solo campo nullable (`asignado_a`) en vez de un enum.

## Fase 1 — Base de datos ✅

- [x] `database/solicitudes_inventario_migration.sql` (nuevo) — **falta ejecutarla en la
  base real**, ver nota al final.
  - `ALTER TABLE solicitudes MODIFY tipo ENUM('producto','materia_prima','tarea','inventario') ...`
  - `ALTER TABLE solicitudes ADD COLUMN asignado_a INT UNSIGNED NULL` (FK `usuarios`,
    `ON DELETE SET NULL`) — `NULL` = abierto a cualquiera.
  - `ALTER TABLE solicitudes ADD COLUMN recurrente_id INT UNSIGNED NULL` (FK a la tabla
    nueva de plantillas, `ON DELETE SET NULL`) — mismo patrón que `gastos.recurrente_id`.
  - Tabla `solicitudes_inventario_detalle`: `id, solicitud_id (FK CASCADE),
    tipo_criterio ENUM('categoria','producto','materia_prima','insumo','ingrediente_generico'),
    referencia_id` — guarda la selección *sin resolver* (si después se agrega un
    producto a la categoría "Helados", queda incluido solo).
  - Tabla `solicitudes_recurrentes`: `id, descripcion, dia_semana TINYINT
    (1=lunes..7=domingo, ISO-8601/date('N')), prioridad, asignado_a NULL, activo,
    ultima_fecha_generada DATE NULL, usuario_id, eliminado, timestamps`.
  - Tabla `solicitudes_recurrentes_detalle`: mismas columnas que
    `solicitudes_inventario_detalle` pero apuntando a `solicitud_recurrente_id`.

## Fase 2 — `SolicitudModel.php` (ampliar) ✅

- [x] `crear(array $data, array $criterios)`: transacción — inserta la cabecera y, si
  trae criterios, las filas en `solicitudes_inventario_detalle`.
- [x] `getCriterios(int $solicitudId): array` (y `resolverNombresCriterios()`, la
  resolución de nombres factoreada para poder usarse también ANTES de insertar, al
  armar el `item_nombre` automático).
- [x] `resolverItemsParaInventario(int $solicitudId): array` — expande criterios a
  pares concretos `tipo_item:item_id`.
- [x] `findAllConDetalle()`: filtro `usuario_id` ampliado para incluir inventarios
  asignados a mí o abiertos, sin tocar el comportamiento de producto/materia_prima/tarea.
  De paso se agregó un filtro `id` (reusado por `StockController::inventario()` para
  traer la solicitud con el nombre de quien la pidió).

## Fase 3 — `SolicitudRecurrenteModel.php` (nuevo) ✅

- [x] `findAllFiltrado()`, `crear()`, `actualizar()`, `toggleActivo()`, `eliminar()`.
- [x] `generarPendientes(int $usuarioSistemaId): int` — misma lógica de reclamo atómico
  que `RecurrenteModel`, adaptada a `dia_semana`/lunes de la semana en vez de mes.

## Fase 4 — Gancho de generación perezosa ✅

- [x] `app/Controllers/Controller.php::generarSolicitudesRecurrentesSiCorresponde()`,
  llamado desde `requireAuth()` junto a `generarGastosRecurrentesSiCorresponde()`.

## Fase 5 — `SolicitudesController.php` ✅

- [x] `crear()` ampliado para `tipo === 'inventario'` (`criterios_json`, `asignado_a`).
- [x] Acciones de plantillas: `crearRecurrente()`, `actualizarRecurrente()`,
  `toggleActivoRecurrente()`, `eliminarRecurrente()`.
- [x] `index()`: pasa `recurrentes` + catálogo (categorías x3 tipos, ingredientes
  genéricos) embebido directo — **desvío del plan original**: se descartó el endpoint
  AJAX `catalogoCriterios()` por quedar sin uso (el catálogo ya viaja en el render
  inicial); si en el futuro se necesita recargar sin refrescar la página entera, se
  puede agregar ahí.
- [x] `buscarItems()` ampliado para incluir insumos (antes solo producto/materia_prima).

## Fase 6 — `StockController.php` (filtro en `/stock/inventario`) ✅

- [x] `inventario()` acepta `?solicitud_id=` y filtra `resumenStock()` en PHP.
- [x] `procesarInventario()` acepta `solicitud_id` y cierra la solicitud (`resuelta`)
  al guardar exitosamente.

## Fase 7 — Vistas ✅

- [x] `app/Views/solicitudes/index.php`: opción `inventario` en el tipo, picker de
  criterios (categoría / ingrediente genérico / producto·MP·insumo puntual vía el
  buscador existente ampliado), "Asignar a", botón "Ir a contar" en la tabla, y sección
  completa de "Solicitudes de inventario recurrentes" (tabla + modal, calcada de
  `gastos-recurrentes/index.php`) reusando el mismo picker de criterios vía la función
  JS compartida `armarBuscadorCriterios()`/`renderCriterios()`.
- [x] `app/Views/stock/inventario.php`: banner con descripción/criterios/quién pidió +
  link "Ver todos los ítems (sin filtro)" + `<input type="hidden" name="solicitud_id">`.

## Fase 8 — Rutas (`index.php`) ✅

- [x] `solicitudes/recurrentes/crear|actualizar|toggle-activo|eliminar` (POST).
- [x] `stock/inventario` sin cambios de ruta (ya soportaba query params).

## Fase 9 — Pruebas ⬜

- [x] `php -l` de todos los archivos tocados/creados — sin errores.
- [ ] **Pendiente correr la migración** `database/solicitudes_inventario_migration.sql`
  contra la base real — no se pudo ejecutar desde este entorno (el hosting rechaza la
  conexión desde esta IP, mismo límite que ya se documentó en la sesión de Separación
  de Ingresos). El resto de las pruebas de abajo requieren la migración ya corrida.
- [ ] Crear una solicitud de inventario puntual (categoría + un insumo suelto);
  confirmar que aparece en el listado con el resumen correcto de criterios.
- [ ] Abrir `stock/inventario?solicitud_id=<id>` y confirmar que la tabla muestra
  *solo* esos ítems (comparar contra `/stock/inventario` sin filtro, que debe seguir
  mostrando todo).
- [ ] Guardar el conteo desde esa pantalla filtrada y confirmar que la solicitud pasa
  sola a `resuelta`, y que los movimientos de stock/desvío se generaron igual que en
  el flujo sin filtrar.
- [ ] Crear una plantilla recurrente con `dia_semana` = hoy, confirmar que al recargar
  cualquier pantalla (dispara `requireAuth()`) se genera la solicitud sola una única
  vez (recargar de nuevo y confirmar que NO se duplica).
- [ ] Probar con un usuario sin `canEdit('solicitudes')`: que solo vea sus propias
  solicitudes de compra/tarea, más las de inventario asignadas a él o abiertas — no
  las de inventario asignadas a otro usuario puntual.

---

## Fase 10 — Ajustes posteriores (pedidos por el usuario tras la v1) ✅

- [x] **Inventario como pestaña propia**: en vez de vivir como un `tipo` más dentro del
  formulario de "Solicitudes", ahora es una tercera pestaña (`solicitudes?vista=inventario`)
  con su propio panel "Nueva solicitud de inventario", su propio listado (filtrado a
  `tipo='inventario'`), y la sección de "Solicitudes recurrentes" movida ahí adentro.
  La pestaña "Solicitudes" ya no muestra ni permite crear el tipo `inventario` — su
  listado ahora excluye ese tipo explícitamente (`SolicitudesController::index()`).
- [x] **Solo categorías/productos activos**: `CategoriaModel::findAllConPadre()` ganó un
  parámetro opcional `$soloActivas` (default `false`, así no cambia el comportamiento en
  las pantallas de administración de Productos/MP/Insumos/Compras que ya lo usaban);
  `SolicitudesController::index()` lo pasa en `true` para los 3 pickers de categorías.
  El buscador de ítems puntuales (`SolicitudModel::buscarItems()`) ya filtraba
  `activo=1` desde la Fase 2, sin cambios ahí. `ingredientes_genericos` no tiene columna
  `activo` en el schema, así que no aplica.

## Notas de diseño que quedaron implícitas

- **No se revive `inventarios`/`inventarios_detalle`**: esas tablas están huérfanas en
  el código actual (nadie las usa); traerlas de vuelta sería un cambio de arquitectura
  más grande que no hace falta para lo pedido — alcanza con marcar la solicitud
  `resuelta` al guardar el conteo en `stock_movimientos`, que es el mecanismo real hoy.
- **Criterio "categoría" no es recursivo**: `categorias` tiene jerarquía (`padre_id`),
  pero elegir una categoría en la solicitud solo trae los ítems con ese `categoria_id`
  exacto, no los de sus subcategorías — coherente con que el resto del código
  (`resumenStock()`, etc.) tampoco resuelve la jerarquía en ningún lado.
- **`asignado_a` nullable en vez de `modo_destino` enum**: Tareas usa un enum
  (`usuario_especifico`/`turno_abierto`) porque además guarda quién respondió/aceptó;
  acá alcanza con un solo campo nullable (`NULL` = abierto) porque no se necesita ese
  flujo de aceptar/rechazar, solo contar y guardar.
- **Sin permiso nuevo**: todo queda bajo el permiso `solicitudes` ya existente
  (`PermisosModel::MODULOS['solicitudes']`) — ver una solicitud de inventario asignada
  a uno (o abierta) solo requiere sesión iniciada, igual que ya pasa con Tareas.
