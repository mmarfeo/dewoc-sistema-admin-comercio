# Ingredientes Genéricos — Checklist de desarrollo

> Objetivo: recetas atadas a un "ingrediente genérico" (ej. "Jamón cocido") en vez de a una
> materia prima puntual, para que cambiar de marca/proveedor no rompa costos ni recetas.
> Regla de unidad: el genérico define la unidad de medida base (ej. gr); toda MP-variante
> que cuelgue de él debe cargar su `cantidad_contenida` en esa misma unidad, para que el
> costo por unidad base sea siempre comparable entre variantes.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Fase 0 — Migraciones SQL ✅
- [x] `ingredientes_genericos` (id, nombre, unidad_medida_id, categoria_id, eliminado, created_at)
- [x] `materias_primas.ingrediente_generico_id` (nullable, FK)
- [x] `receta_detalle.ingrediente_generico_id` (nullable, FK) + ampliar ENUM `tipo_ingrediente` a `('materia_prima','insumo','ingrediente_generico')`
- [x] `recetas_historial_costo` (id, receta_id, costo_anterior, costo_nuevo, factura_compra_id, motivo, created_at)
- [x] `configuracion_fiscal.umbral_alerta_margen` (reutiliza la tabla de config de una sola fila que ya existe; default NULL → 5% en código)

## Fase 1 — Modelo `IngredienteGenericoModel` ✅
- [x] CRUD básico (crear/listar/editar/eliminar)
- [x] `calcularCosto($id)`: promedio ponderado por `stock_actual` entre todas las MP-variante activas
- [x] `getVariantes($id)`
- [x] Validación: al asignar una MP a un genérico, su `unidad_medida_id` debe coincidir con la del genérico (si no, error claro)

## Fase 2 — Materias Primas: asignar variante a un genérico ✅
- [x] `MateriasPrimasController`: acción para asignar/reasignar `ingrediente_generico_id`
- [x] Vista de alta/edición de MP: selector opcional "Ingrediente genérico" + validación de unidad de medida (server y cliente)
- [x] Pantalla CRUD de genéricos (`app/Views/ingredientes-genericos/index.php`)

## Fase 3 — Recetas apuntando a un genérico ✅
- [x] `RecetaModel::calcularCosto()` y `findByIdConDetalle()`: usar costo del genérico cuando `ingrediente_generico_id` esté seteado
- [x] `ProductoModel` (costo por receta y por producto): mismo tratamiento, para que el margen no quede desactualizado
- [x] Form de receta: permitir elegir "Ingrediente genérico" como tipo de ingrediente (búsqueda ya lo prioriza)
- [x] `producir()` / `cambiarEstadoOrden()`: si el ingrediente es un genérico, descontar stock FIFO entre sus variantes (la comprada primero, primero se consume) hasta cubrir la cantidad necesaria

## Fase 4 — Propagación de costos y avisos de margen ✅
- [x] Método único de propagación (hook en `FacturaCompraModel::actualizarStock()`, cubre compra por chat y por formulario web sin duplicar lógica): recalcula costo del genérico → recetas afectadas → si cambió, graba en `recetas_historial_costo` → productos afectados → si el margen cae más que el umbral configurado, push a admins (reusa `WebPush`/`PushSubscriptionModel` ya existente)
- [x] Vista de historial de costos de una receta (`recetas/historial-costo`)
- [x] Campo de configuración editable (admin) para el umbral de aviso de margen, default 5%

## Fase 5 — Chat IA: asignar genérico al cargar una compra ✅
- [x] Tool `buscar_ingrediente_generico_por_nombre` (fuzzy match)
- [x] Tool `asignar_ingrediente_generico` (asigna existente o crea uno nuevo, validando unidad)
- [x] Prompt: si la MP de la compra no tiene genérico asignado, sugerir uno existente o preguntar si es nuevo, antes de confirmar el registro

## Fase 6 — Verificación ✅
- [x] `php -l` de todos los archivos tocados/creados (todos OK)
- [x] Chequeo de sintaxis JS de las vistas con JS inline modificadas (todos OK)
- [x] Guía de prueba manual paso a paso (ver abajo / mensaje final)
- [x] Lista final de archivos a subir por FTP (ver mensaje final)

---

## Notas de diseño que quedaron implícitas en el código

- **FIFO aproximado, no es un sistema de lotes real**: como `materias_primas` no trackea
  compras individuales (solo stock agregado + costo promedio ponderado), el consumo FIFO al
  producir usa `updated_at` como proxy de "cuál se compró antes". Si un admin edita el nombre
  o la categoría de una MP-variante sin que medie una compra, eso también actualiza
  `updated_at` y puede alterar el orden de consumo. Aceptado como limitación conocida — un
  sistema de lotes real sería un desarrollo bastante más grande.
- **Primera compra de un genérico nuevo**: la primera vez que se calcula el costo de una
  receta que usa un genérico recién creado, no hay "costo anterior" real con qué comparar —
  se guarda como línea de base (sin disparar aviso de margen) y a partir de la segunda compra
  ya compara contra algo real.
