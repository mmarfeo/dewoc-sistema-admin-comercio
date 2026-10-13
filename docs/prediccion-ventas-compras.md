# Proyecciones — anticipar ventas, compras y márgenes

Documento de diseño para una funcionalidad todavía no implementada. Sirve de base para decidir el enfoque (¿hace falta Machine Learning?) y planificar el desarrollo — nada de lo que describe este archivo existe en el código todavía.

## Problema

Hoy `Reportes` (`ReportesModel`) muestra **lo que ya pasó**: ventas por período, compras por proveedor, margen implícito en `ProductoModel::findAllConDetalle()` (`precio_venta` vs `costo_mp`). No hay nada que mire ese historial y proyecte **lo que probablemente va a pasar** — cuánto se va a vender la semana que viene, cuándo conviene reponer una materia prima antes de quedarse sin stock, o si el margen de una categoría viene cayendo mes a mes. Esa lectura hoy la hace el usuario a ojo, mirando los reportes existentes.

## La pregunta central: ¿hace falta Machine Learning?

**No para una primera versión.** Con el volumen y la forma de los datos de un comercio individual (una serie temporal por producto/categoría, sin miles de variables externas), un promedio móvil ponderado + ajuste por estacionalidad (día de la semana, día del mes) resuelve la gran mayoría del valor, calculado directo en PHP/SQL sobre las tablas que ya existen (`ventas`, `ventas_detalle`, `facturas_compra_detalle`) — sin librerías nuevas, sin infraestructura nueva, sin nada que haya que reentrenar.

ML "de verdad" (regresión con múltiples variables, modelos de series temporales tipo ARIMA/Prophet, redes neuronales) se justifica recién cuando se cumplen **las tres** condiciones a la vez:

1. Hay años de histórico estable (no unos pocos meses).
2. Los métodos simples fallan de forma sistemática — hay patrones no lineales que un promedio ponderado no puede capturar (ej. combinaciones de estacionalidad + feriados + promociones simultáneas).
3. Alguien va a mantener ese pipeline en el tiempo (reentrenar, versionar el modelo, monitorear que no se degrade).

Para este proyecto, hoy no se cumple ninguna de las tres. Meter Python + ML acá antes de agotar lo simple es costo de infraestructura sin el volumen de datos que lo justifique.

**Sobre el Chat IA / Gemini que ya existe**: es tentador pensar "ya tengo un LLM conectado, que proyecte él". No conviene usarlo para **calcular** la proyección — los LLM no son confiables haciendo aritmética/estadística exacta sobre series numéricas largas, y acá no hay forma de auditar cómo llegó a un número. Sí es la herramienta correcta para lo que ya hace en `Reportes IA`: **narrar y explicar** un resultado ya calculado por código determinístico (ej. "¿por qué el margen de Panadería bajó en julio?", usando como insumo los números que salieron del cálculo estadístico real).

## Decisiones ya tomadas

| Pregunta | Decisión |
|---|---|
| ¿Qué horizonte de proyección? | **2 semanas por defecto**, para todo el negocio en un primer momento. Tiene que ser una **variable configurable**, no un valor fijo en código — el administrador puede necesitar cambiarlo si el negocio cambia de ritmo. |
| ¿Proyección global o por producto? | **Por producto.** Es lo que hace falta para poder decidir stock y compras a partir de la proyección (no alcanza con un total agregado del negocio). |
| ¿Quién puede ver esta sección? | Se gatea con el módulo de permisos existente (`PermisosModel::MODULOS`) — el administrador decide qué roles/usuarios tienen acceso, mismo patrón que el resto del sistema. |
| ¿Recalculo por cron o bajo demanda? | **Cron.** Se genera como foto periódica, no se recalcula en vivo cada vez que se abre la pantalla (mismo criterio ya usado para el reporte semanal, ver `docs/tareas-pendientes.md` sección 3). |
| ¿Qué variables de estacionalidad entran en la Fase 1? | **Día de la semana, día del mes y feriados.** Quedan dentro del alcance porque son deterministas y baratas de calcular — no requieren ML. **Clima queda afuera** (ver "Fuera de alcance"): depende de una fuente externa y su correlación con ventas no es tan directa. |
| ¿Qué tipos de feriado importan? | Solo **inamovibles**, **trasladables** y **turísticos** — no se contempla la categoría "día no laborable" que también lista argentina.gob.ar, porque no fue pedida. |

## Enfoque propuesto — 3 fases

### Fase 1 — Estadística simple en PHP/SQL (sin dependencias nuevas)

Nueva sección "Proyecciones", reutilizando datos que `ReportesModel` ya expone (`ventasPorDia()`, `comprasPorPeriodo()`, `topProductos()`) más los costos que ya calcula `ProductoModel::findAllConDetalle()`.

| Proyección | De dónde sale | Método |
|---|---|---|
| Ventas próximas 2 semanas, por producto | `ventasPorDia()` filtrado/agrupado por producto | Promedio móvil (ej. últimas 4-8 semanas) ponderado por **día de la semana** y por **día del mes** — un local vende distinto un lunes que un sábado, y distinto a fin/inicio de mes (cobro de sueldo) que a mitad — ambos factores se calculan con el propio histórico, no se hardcodean |
| Ajuste por feriado | Tabla `feriados` (ver "Feriados — reutilización de la tabla existente" abajo) | Si un día del horizonte proyectado cae en un feriado (inamovible, trasladable o turístico), se reemplaza el peso de "día de la semana" por un factor propio calculado con el historial de ventas de feriados pasados — un feriado no se comporta como un día de semana cualquiera |
| Consumo/compra proyectada de materia prima | Ventas proyectadas por producto × consumo por receta (`RecetaModel`) − stock actual (`StockModel`) | Si el consumo proyectado agota el stock antes de que termine el horizonte, se marca como "hay que reponer" |
| Margen por producto en el tiempo | `precio_venta` vs `costo_mp` ya calculado, agrupado por período | Tendencia simple (regresión lineal por mínimos cuadrados) para ver si sube o baja, no solo el valor puntual |

Toda la matemática (promedio móvil, ponderación por día de semana/mes/feriado, regresión lineal simple) es aritmética que se resuelve nativo en PHP — no requiere librería estadística ni servicio externo. El horizonte (14 días por defecto) es un parámetro, no una constante — hoy el proyecto no tiene una tabla de configuración general reutilizable, así que probablemente haga falta una fila/tabla chica dedicada (ver "Modelo de datos") en vez de forzarlo en otro esquema existente.

Generación: **vía cron**, corriendo con la frecuencia que tenga sentido para el horizonte elegido (ej. diario), guardando la foto — no se recalcula al abrir la pantalla.

#### Feriados — reutilización de la tabla existente

El sistema **ya tiene** un módulo de feriados (`FeriadoModel`, `FeriadosController`, tabla `feriados` — `database/feriados_setup.sql`), usado hoy para calcular el recargo de las jornadas trabajadas por empleados (Sueldos). Proyecciones **reutiliza esa misma tabla** en vez de crear una nueva — mismo criterio que ya se aplicó con `reportes_ia` en `docs/tareas-pendientes.md`: cuando el esquema ya encaja, no se duplica.

Dos cosas hay que ajustar en esa tabla compartida:

- **Falta un campo `tipo`.** Hoy `feriados` solo tiene `fecha` y `nombre` — no distingue inamovible/trasladable/turístico. Hace falta un `ALTER TABLE` que agregue `tipo` (nullable, para no romper los feriados ya cargados a mano para Sueldos). Como es una tabla compartida entre dos módulos, conviene confirmar con quien mantiene Sueldos si ese campo también le sirve a él (ej. tratar distinto un feriado turístico a la hora del recargo) antes de fijar los valores posibles — para Proyecciones, los que importan son únicamente `inamovible`, `trasladable` y `turistico`.
- **Carga automática.** Se revisó `argentina.gob.ar/feriados`: es una página informativa, **no expone ninguna API pública** para consultarla de forma programática. Sí existen mirrors comunitarios de los mismos datos oficiales con campo `tipo` incluido (ej. ArgentinaDatos, `api.argentinadatos.com/v1/feriados/{año}`) — no son un servicio operado por el gobierno, así que hay que tratarlos como una fuente de conveniencia, no autoritativa. Propuesta: un cron anual (ej. en diciembre, para el año siguiente) que importa desde esa fuente, filtra solo los tres tipos que importan acá, e inserta en `feriados` respetando el `UNIQUE KEY` sobre `fecha` que ya existe (así no duplica lo cargado a mano). La carga manual que ya ofrece `FeriadosController::crear()`/`eliminar()` queda como fallback para corregir o completar si la fuente externa falla, llega tarde, o un feriado se decreta fuera de calendario (algo que pasa seguido en Argentina).

### Fase 2 — Retroalimentación (el "que aprenda" sin ser ML)

Guardar cada proyección hecha junto con el valor real una vez que ese período pasa, para:
- medir el error (proyectado vs real) y mostrarlo, para que el usuario sepa cuánto confiar en el número, y
- recalcular periódicamente los factores (ej. el peso de "sábado" en la ponderación) contra datos reales en vez de dejarlos fijos.

Esto es lo que hace que el sistema "se retroalimente": ajusta sus propios parámetros con lo que efectivamente pasó, sin ser una red neuronal ni requerir reentrenamiento con librerías de ML.

### Fase 3 — ML real (opcional, solo si Fase 1-2 se queda corta)

Si con el tiempo se ve que la estacionalidad simple no alcanza (ej. hay variables externas fuertes: clima, campañas de marketing, apertura de un competidor), ahí sí evaluar:

- **Microservicio Python aparte** (pandas + statsmodels/Prophet o scikit-learn), que el backend PHP consulta por HTTP, alimentado con un export periódico del histórico. Es infraestructura real nueva — deploy, mantenimiento, versionado del modelo — y hay que tenerlo claro antes de sumarla, no es "una librería que se instala y ya".
- PHP no tiene un ecosistema de ML maduro (existe Rubix ML, pero es chico y con soporte limitado) — si se llega a este punto, el microservicio en Python es más realista que forzarlo en PHP.

No hay apuro en decidir esto ahora: el diseño de datos de la Fase 1 (ver abajo) no se pisa con una Fase 3 futura, porque las proyecciones quedan guardadas con un campo `metodo` desde el arranque.

## Modelo de datos (conceptual)

#### Ajuste a `feriados` (tabla existente)
No es una tabla nueva — es un `ALTER TABLE` sobre la que ya usa Sueldos/Empleados.

| Campo | Para qué |
|---|---|
| tipo (nuevo, nullable) | `inamovible` \| `trasladable` \| `turistico` — nullable para no romper los feriados ya cargados sin este dato |

#### Configuración de proyecciones
Una sola fila (o una por tipo, si con el tiempo hace falta distinto horizonte para ventas vs. compras) con los parámetros que el administrador puede ajustar. No existe hoy una tabla de configuración general en el sistema para colgar esto, así que le corresponde una tabla propia y chica en vez de forzar otro esquema.

| Campo | Para qué |
|---|---|
| horizonte_dias | 14 por defecto — cuántos días adelante proyecta el cron |
| updated_at / usuario_id | quién lo cambió por última vez, por auditoría |

#### Proyección
Una predicción guardada para un período futuro, generada por cron (no se recalcula en vivo cada vez que se abre la pantalla).

| Campo | Para qué |
|---|---|
| tipo | `venta` \| `compra_mp` \| `margen` |
| entidad_tipo / entidad_id | a qué producto/materia prima aplica — siempre por producto, según lo decidido, no un total agregado del negocio |
| fecha_desde / fecha_hasta | período que cubre la proyección (según `horizonte_dias` vigente al momento de generarla) |
| valor_proyectado | el número calculado |
| metodo | `promedio_movil_ponderado` \| `regresion_lineal` \| (a futuro) `ml_externo` — para poder comparar métodos entre sí con el tiempo |
| created_at | |

#### Proyección ↔ Resultado real
El feedback loop de la Fase 2.

| Campo | Para qué |
|---|---|
| proyeccion_id | a qué proyección corresponde |
| valor_real | lo que efectivamente pasó, una vez cerrado el período |
| error_pct | `(valor_real - valor_proyectado) / valor_proyectado` — para mostrar qué tan confiable viene siendo el método |
| calculado_at | |

## Fuera de alcance (por ahora)

- Variables externas de fuente no confiable u operativamente costosas (clima, publicidad, competencia) — la Fase 1 no las contempla; si se necesitan, entran como factor manual antes de justificar un modelo de ML.
- Feriados de tipo "día no laborable" (la cuarta categoría que también lista `argentina.gob.ar/feriados`) — a diferencia de inamovibles/trasladables/turísticos, no fue pedida y queda fuera del filtro de importación.
- Proyección por combinaciones (ej. "producto X en sucursal Y en día lluvioso") — de mínima, se arranca a nivel producto/categoría y período, no cruces multivariable.
- Alertas automáticas push/email por proyección de quiebre de stock — el diseño de datos ya lo deja disponible (se puede consultar "esta materia prima se agota en proyección antes de la próxima compra habitual"), pero el disparo de aviso es una integración aparte, mismo patrón que otros avisos del sistema (ver `docs/tareas-pendientes.md`, sección 3).

## Preguntas abiertas

Ninguna — todas resueltas (ver "Decisiones ya tomadas").