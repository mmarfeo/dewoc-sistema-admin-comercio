# Chat IA con Gemini — arquitectura reutilizable

> Guía de referencia para portar la lógica del Chat IA de este proyecto (function-calling,
> lectura de comprobantes/PDF, fallback de modelos, diagnóstico) a un proyecto nuevo que
> necesite un chat IA para **generar reportes** y **extraer información de documentos PDF**.
> No es código para copiar y pegar tal cual — la mayoría de las reglas de negocio de acá
> (IVA argentino, categorías de gasto, nombres de tablas) son específicas de este comercio.
> Lo reutilizable es el **patrón**: cómo está armado cada mecanismo y por qué.

---

## 1. El loop de function-calling (el corazón del chat)

Archivo: `app/Controllers/ChatController.php`, método `mensaje()` (recibe el mensaje del
usuario, arma el prompt y la conversación, y corre el loop).

Idea central: Gemini no "hace" nada por sí solo — cada vez que necesita un dato o ejecutar
una acción, devuelve una `functionCall` (nombre de función + argumentos) en vez de texto.
El backend ejecuta esa función en PHP, le manda el resultado de vuelta como
`functionResponse`, y así sigue hasta que Gemini devuelve texto final (sin más function
calls). Piezas:

- **`obtenerHerramientas()`**: array con el "menú" de funciones disponibles, en el formato
  `functionDeclarations` que espera la API de Gemini (nombre, descripción, parámetros con
  su JSON Schema). Gemini elige cuál llamar según la descripción — por eso la descripción
  de cada función importa tanto como el código que la implementa.
- **`ejecutarHerramienta(string $nombre, array $args): array`**: un `match()` que mapea el
  nombre de función devuelto por Gemini a un método PHP `toolXxx()`. Cada `toolXxx()`
  devuelve siempre `['ok' => bool, ...]`, nunca lanza una excepción hacia afuera (se
  atrapan adentro y se devuelven como `ok:false, error:'...'`) — así el loop nunca se corta
  por un error de una sola herramienta.
- **El loop** (`for ($iter = 0; $iter < 10; $iter++)` en `mensaje()`): llama a Gemini, si
  la respuesta trae `functionCall`s las ejecuta todas (Gemini puede pedir varias en
  paralelo en el mismo turno — aprovechalo, es mucho más rápido que una por una), arma los
  `functionResponse` y vuelve a llamar a Gemini con eso agregado al historial de la
  conversación. Si no hay más function calls, el texto de esa respuesta es lo que se le
  muestra al usuario.
- **Historial acotado**: se manda solo los últimos N mensajes (`getHistorial($usuarioId,
  20)` acá), no toda la conversación — controla el tamaño del prompt y el costo.

### 1.1 Forma exacta del JSON que espera y devuelve Gemini (genérico, sin nada del negocio)

Esto es lo que hace falta para escribir el loop desde cero sin tener que ir a buscar la
documentación de Gemini por separado. Una **tool** (una entrada de `obtenerHerramientas()`)
es un objeto JSON Schema:

```php
[
    'name'        => 'buscar_cliente',                    // el nombre por el que Gemini la va a "llamar"
    'description' => 'Busca un cliente por nombre o documento. Usala antes de registrar una venta a nombre de alguien.',
    'parameters'  => [
        'type'       => 'OBJECT',
        'properties' => [
            'nombre' => ['type' => 'STRING', 'description' => 'Nombre o parte del nombre a buscar'],
        ],
        'required'   => ['nombre'],
    ],
]
```

Todas las tools van dentro de un único bloque `functionDeclarations` en el payload que se
manda a `POST /v1beta/models/{modelo}:generateContent`:

```json
{
  "system_instruction": { "parts": [{ "text": "..." }] },
  "contents": [ /* $messages, ver abajo */ ],
  "tools": [ { "functionDeclarations": [ /* la lista de tools */ ] } ]
}
```

El "historial" (`$messages`/`contents`) es un array de turnos, cada uno con `role` (`user` o
`model`) y `parts`. Cuando Gemini quiere ejecutar una función, su turno trae un
`functionCall` en vez de `text`:

```json
{ "role": "model", "parts": [ { "functionCall": { "name": "buscar_cliente", "args": { "nombre": "Pérez" } } } ] }
```

El backend ejecuta esa función y arma la respuesta como un **turno de rol `user`** (no
`function` — detalle fácil de pasar por alto si venís de otra API que sí usa un rol
separado para esto) con un `functionResponse`:

```json
{ "role": "user", "parts": [ { "functionResponse": { "name": "buscar_cliente", "response": { "ok": true, "clientes": [...] } } } ] }
```

Ambos turnos (el `model` con el `functionCall` y el `user` con el `functionResponse`) se
appendean a `$messages` en ese orden, y recién ahí se vuelve a llamar a Gemini con el
historial actualizado — así hasta que un turno del modelo venga sin ningún `functionCall`,
que es la señal de "esto ya es la respuesta final para el usuario".

---

## 2. El system prompt: cómo se arma y por qué importa tanto

Archivo: `ChatController::buildSystemPrompt()`.

- Se arma con un heredoc de PHP que **interpola datos dinámicos en tiempo real**: fecha de
  hoy, esquema de las tablas de la base (para que el modelo sepa qué columnas existen sin
  tener que preguntarlo con una tool aparte), y catálogos chicos y estables (lista de
  socios, categorías de gasto) directo como texto — en vez de obligar al modelo a llamar a
  una función para "buscar categoría" en cada mensaje. Menos tools intermedias = menos
  llamadas a la API = menos costo y menos chance de pegarle a un límite de cuota.
- **Las reglas más útiles del prompt no son genéricas, son reglas nacidas de un error real
  observado**, descriptas con el mismo nivel de detalle que el error. Ejemplo real de este
  proyecto: el modelo confundía repetidamente un recuadro informativo obligatorio de los
  tickets argentinos ("Régimen de Transparencia Fiscal al Consumidor") con un ítem a
  cargar. La regla que lo arregló no fue "no inventes ítems" (muy genérica, no funcionaba)
  sino describir textualmente CÓMO se ve ese recuadro, DÓNDE aparece (después del TOTAL) y
  la instrucción explícita de ignorarlo. Para el proyecto nuevo: cuando el modelo se
  equivoque en un caso puntual al extraer un PDF, la solución casi nunca es "una regla más
  genérica" — es documentar el patrón exacto que lo confunde.
- **Ejemplos de queries ya armadas a mano dentro del prompt**, no dejar que el modelo
  improvise SQL para preguntas de negocio recurrentes (ver sección "REGLAS PARA CONSULTAS
  SQL" del prompt actual). Reduce errores y, para preguntas que piden varios desgloses a la
  vez, hay una regla explícita de combinar todo en una sola consulta con `UNION ALL` en vez
  de llamar a la tool de consulta varias veces — ver más abajo, sección 6.

---

## 3. Tarjeta de confirmación — el patrón para "extraer de un documento y no confiar ciegamente"

Archivos: `ChatController::armarPayloadConfirmacionCompra()`, `confirmarCompra()`,
`confirmar()`/`cancelar()`, tabla `chat_mensajes` (columna `datos_json`), vista
`app/Views/chat/index.php`.

Principio central: **nunca ejecutar una acción irreversible (guardar algo en la base)
directo a partir de lo que el modelo extrajo de una imagen/PDF**. El flujo es:

1. Gemini extrae datos del documento y en vez de guardar, llama a una tool especial
   (`mostrar_confirmacion_compra` en este proyecto) con los datos estructurados.
2. El backend arma un `payload` (JSON) con esos datos, lo guarda en `datos_json` del
   mensaje con `estado: 'pendiente'`, y se lo devuelve al frontend — **el turno de Gemini
   termina ahí**, no se guarda nada todavía.
3. El frontend renderiza una tarjeta editable con esos datos (cada campo se puede corregir
   a mano antes de confirmar).
4. Recién cuando el usuario aprieta "Confirmar" se ejecuta la acción real
   (`confirmarCompra()`), usando los datos ya corregidos por el usuario, no los originales
   del modelo.

Para el proyecto nuevo (extracción de PDFs para reportes): el mismo patrón aplica 1:1 —
"extraje estos datos del PDF, ¿confirmás?" en vez de "ya generé el reporte con lo que
entendí". Especialmente importante si el PDF alimenta algo que después se usa para tomar
decisiones (facturación, cifras oficiales, etc.).

**Principio derivado, aprendido de un bug real de este proyecto**: cuando el modelo tiene
que resolver una entidad ambigua (¿este nombre en el PDF es el Proveedor X que ya existe, o
uno nuevo?), **nunca la cree ni la asigne en silencio si hay dudas** — que la tarjeta de
confirmación muestre el match tal como lo resolvió y deje corregirlo, o que el modelo
pregunte antes de seguir si la ambigüedad es alta. El bug real: el chat autocreaba
proveedores "fantasma" cuando no reconocía bien un nombre, mezclando pagos de sueldos con
pagos a proveedores reales — costó una limpieza de datos importante corregirlo después.

---

## 4. Lectura de imágenes/PDF: mecanismo técnico

Archivo: `ChatController::mensaje()`, líneas donde se procesa `$_FILES['imagen']`.

- El archivo se lee, se codifica en base64, y se manda a Gemini como parte del mensaje
  `user`: `['inline_data' => ['mime_type' => $mime, 'data' => $base64]]` junto al texto.
  Gemini 2.x+ acepta PDF directo en `inline_data` igual que una imagen (no hace falta
  convertir el PDF a imagen antes).
- **El archivo original se guarda en disco** (no solo se manda a Gemini y se descarta) —
  importante para poder auditar después qué documento generó qué extracción, y para poder
  copiarlo como comprobante adjunto de lo que se termine guardando.
- **Clasificación de tipo de documento antes de extraer**: si el usuario no aclaró qué es
  el archivo, el prompt por defecto le pide a Gemini que primero identifique de qué tipo de
  documento se trata (en este proyecto: factura de compra / comprobante de pago / recibo de
  sueldo) y que **pregunte si no está seguro**, en vez de asumir un tipo fijo. Para un
  proyecto de extracción de PDFs más general, este paso de clasificación previa es todavía
  más importante si van a convivir varios tipos de documento distintos.
- **Guardia contra mezclar documentos**: si el usuario sube un archivo nuevo mientras
  todavía tiene una tarjeta de confirmación sin resolver, el sistema rechaza procesar el
  nuevo archivo hasta que resuelva la anterior — evita que el modelo mezcle datos de dos
  documentos distintos en una sola tarjeta (bug real que se dio en este proyecto).

---

## 5. Reportes dinámicos: de una pregunta en el chat a un Excel descargable

Archivos: `ChatController::toolConsultarDatos()` / `toolGenerarReportePredefinido()` /
`toolGenerarReportePersonalizado()`, `app/Models/ChatModel::ejecutarConsulta()`,
`app/Models/ReporteIaModel.php`, `app/Models/ReportesModel.php`,
`app/Controllers/ReportesIaController.php`, `app/Views/reportes-ia/*.php`,
`config/config.php` (constante `MCP_TABLAS_OK`).

Esta es la sección más relevante si el proyecto nuevo necesita **la misma funcionalidad de
reportes dinámicos**: dejar que el usuario le pida un reporte al chat en lenguaje natural,
que el modelo arme la consulta, y que el resultado quede guardado y descargable — no solo
mostrado una vez en pantalla. Son tres piezas separadas que conviene copiar en este orden.

### 5.1 Tres tools, tres niveles de "qué tan definitivo es el resultado"

| Tool | Quién arma el SQL | ¿Se guarda? | Uso |
|---|---|---|---|
| `consultar_datos` | El modelo, libre | No — vista previa efímera en el chat | Responder una pregunta puntual |
| `generar_reporte_predefinido` | PHP (queries fijas, ya probadas) | Sí, en `reportes_ia` | El pedido encaja en uno de los tipos ya armados |
| `generar_reporte_personalizado` | El modelo, libre (mismas reglas de seguridad que `consultar_datos`) | Sí, en `reportes_ia` | Pedido que no encaja en ningún tipo predefinido |

El system prompt es el que le enseña al modelo a elegir entre las tres (texto real usado en
este proyecto, sección "REGLAS PARA REPORTES" de `buildSystemPrompt()`):

```
- Cuando el usuario pida un reporte, un listado para descargar, o algo que quiera
  "guardado"/"para exportar"/"en Excel", usá generar_reporte_predefinido o
  generar_reporte_personalizado en vez de consultar_datos. consultar_datos es solo para
  responder una pregunta puntual mostrando una vista previa en el chat (sin guardar nada
  ni poder descargarlo).
- Si el pedido encaja en uno de los N tipos predefinidos (acá: ventas, compras, stock,
  caja, produccion, pagos, nomina) para un rango de fechas, usá generar_reporte_predefinido
  — es más confiable porque reutiliza las consultas ya probadas del sistema.
- Si el pedido no encaja en ningún tipo predefinido (ej. "clientes que no compraron en 60
  días", "productos con margen menor al 20%"), usá generar_reporte_personalizado con un
  SELECT armado por vos, respetando las mismas reglas de seguridad que consultar_datos.
- No pidas confirmación antes de generar un reporte (es una operación de solo lectura, no
  modifica nada). Después de generarlo respondé con un resumen breve (cantidad de filas,
  algún dato destacado) y avisá que quedó disponible en la galería de reportes — no
  repitas la tabla completa en el chat.
```

La declaración de las tres tools (formato `functionDeclarations`, ver sección 1.1):

```php
[
    'name'        => 'consultar_datos',
    'description' => 'Ejecuta una consulta SELECT en la base de datos. Usala para responder preguntas sobre ventas, compras, stock, clientes, caja, ganancias, etc.',
    'parameters'  => ['type' => 'OBJECT', 'properties' => [
        'sql' => ['type' => 'STRING', 'description' => 'Consulta SELECT válida'],
    ], 'required' => ['sql']],
],
[
    'name'        => 'generar_reporte_predefinido',
    'description' => 'Genera y GUARDA uno de los reportes predefinidos del sistema para un período de fechas, y lo deja disponible en la galería de reportes (tabla en pantalla + descarga en Excel). Usala cuando el pedido encaje directamente en uno de estos tipos. Para pedidos que no encajen en ninguno, usá generar_reporte_personalizado.',
    'parameters'  => ['type' => 'OBJECT', 'properties' => [
        'tipo'   => ['type' => 'STRING', 'description' => 'Uno de: ventas, compras, stock, caja, produccion, pagos, nomina'],
        'desde'  => ['type' => 'STRING', 'description' => 'Fecha desde, formato YYYY-MM-DD'],
        'hasta'  => ['type' => 'STRING', 'description' => 'Fecha hasta, formato YYYY-MM-DD'],
        'titulo' => ['type' => 'STRING', 'description' => 'Título breve para identificar el reporte en la galería, ej: "Ventas de junio 2026"'],
    ], 'required' => ['tipo', 'desde', 'hasta', 'titulo']],
],
[
    'name'        => 'generar_reporte_personalizado',
    'description' => 'Genera y GUARDA un reporte "a medida" a partir de una consulta SELECT, para pedidos que no encajan en ningún reporte predefinido. Mismas reglas que consultar_datos: solo SELECT, solo tablas permitidas. A diferencia de consultar_datos (vista previa efímera), esto queda guardado en la galería de reportes con tabla en pantalla y descarga en Excel.',
    'parameters'  => ['type' => 'OBJECT', 'properties' => [
        'titulo'      => ['type' => 'STRING', 'description' => 'Título breve para identificar el reporte en la galería'],
        'descripcion' => ['type' => 'STRING', 'description' => 'Descripción breve de qué muestra el reporte'],
        'sql'         => ['type' => 'STRING', 'description' => 'Consulta SELECT válida'],
    ], 'required' => ['titulo', 'sql']],
],
```

### 5.2 La barrera de seguridad: dejarle a la IA armar SQL, pero con jaula

`consultar_datos` y `generar_reporte_personalizado` le dan a Gemini la libertad de escribir
un SELECT propio — eso es lo que los hace flexibles, y también lo que hay que blindar. Las
dos tools pasan por la misma validación, `ChatModel::ejecutarConsulta()`:

```php
public function ejecutarConsulta(string $sql, int $limite = 50): array
{
    $sqlTrim = trim($sql);

    if (!preg_match('/^SELECT\s/i', $sqlTrim)) {
        throw new \InvalidArgumentException('Solo se permiten consultas SELECT.');
    }

    $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER',
                  'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE', 'LOAD', 'INTO OUTFILE'];
    foreach ($forbidden as $kw) {
        // \b evita falsos positivos como "CREATE" dentro de la columna "created_at"
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sqlTrim)) {
            throw new \InvalidArgumentException("Operación no permitida en consulta SQL: $kw");
        }
    }

    // Verificar que solo accede a tablas del whitelist (busca nombres luego de FROM/JOIN)
    preg_match_all('/\b(?:FROM|JOIN)\s+`?(\w+)`?/i', $sqlTrim, $matchesTablas);
    $tablasUsadas = array_unique(array_map('strtolower', $matchesTablas[1] ?? []));
    $tablasPermitidas = array_map('strtolower', MCP_TABLAS_OK);
    foreach ($tablasUsadas as $tabla) {
        if (!in_array($tabla, $tablasPermitidas, true)) {
            throw new \InvalidArgumentException("Acceso no permitido a la tabla: $tabla");
        }
    }

    // Agregar LIMIT si no viene
    if (!preg_match('/LIMIT\s+\d+/i', $sqlTrim)) {
        $sqlTrim .= ' LIMIT ' . $limite;
    }

    $stmt = Database::getInstance()->query($sqlTrim);
    return $stmt->fetchAll();
}
```

Tres capas de defensa, cada una cerrando un hueco distinto de la anterior:

1. **Solo SELECT** (regex de arranque) — bloquea cualquier intento de escritura directo.
2. **Lista de palabras prohibidas con `\b`** — red de seguridad extra por si un SELECT trae
   una subconsulta rara o una función que dispare escritura (`INTO OUTFILE`, etc.). El `\b`
   importa: sin él, `CREATE` como keyword prohibida matchearía falsamente dentro de la
   columna `created_at`, tirando error en consultas legítimas.
3. **Whitelist de tablas** (`MCP_TABLAS_OK` en `config/config.php`) — aunque el SQL sea un
   SELECT válido, si toca una tabla fuera de esta lista (ej. `usuarios.password_hash`,
   tablas de configuración interna) se rechaza. Esta es la barrera que de verdad importa
   copiar tal cual a un proyecto nuevo: es una constante simple, un array de nombres de
   tabla en minúscula, mantenida a mano — cada vez que agregues una tabla nueva al sistema
   tenés que decidir explícitamente si el chat puede leerla o no.
4. (Extra, no seguridad sino control de costo) **LIMIT automático** si el modelo no puso
   uno — `consultar_datos` usa 50 (vista previa liviana), `generar_reporte_personalizado`
   usa 1000 (un reporte para exportar puede necesitar más filas).

**Para portar esto**: `MCP_TABLAS_OK` es la única pieza que hay que rearmar a mano por
proyecto (depende del esquema propio), el resto de `ejecutarConsulta()` es copiable tal
cual. Importante no aflojar ninguna de las 3 capas pensando que "la whitelist de tablas ya
alcanza" — cada una tapa un vector distinto (escritura directa, escritura vía función SQL,
lectura de tabla sensible).

### 5.3 `generar_reporte_predefinido`: reusar queries ya probadas en vez de reinventar

Para los tipos de reporte que se piden seguido, es más confiable tener la query ya escrita
en PHP (con sus joins, formateo de columnas, nombres de encabezado) que dejar que el modelo
la arme de cero cada vez. El patrón, con un solo caso de ejemplo (los demás son el mismo
patrón repetido por tipo de reporte):

```php
private function toolGenerarReportePredefinido(array $args): array
{
    $tipo   = strtolower(trim((string)($args['tipo'] ?? '')));
    $desde  = (string)($args['desde']  ?? '');
    $hasta  = (string)($args['hasta']  ?? '');
    $titulo = trim((string)($args['titulo'] ?? ''));

    $tiposValidos = ['ventas', 'compras', 'stock', /* ...el resto de tipos del proyecto... */];
    if (!in_array($tipo, $tiposValidos, true)) {
        return ['ok' => false, 'error' => 'Tipo de reporte inválido. Debe ser uno de: ' . implode(', ', $tiposValidos)];
    }

    $reportesModel = new ReportesModel();
    $filas = $columnas = [];

    switch ($tipo) {
        case 'ventas':
            $columnas = ['N° Venta', 'Fecha', 'Cajero', 'Ítems', 'Total c/IVA', 'Método de pago'];
            foreach ($reportesModel->ventasPorPeriodo($desde, $hasta, [...]) as $r) {
                // array_combine($columnas, [...]) — cada fila es un array asociativo
                // columna-nombre => valor, en el MISMO orden que $columnas, así la tabla
                // en pantalla y el Excel exportado quedan consistentes sin lógica aparte.
                $filas[] = array_combine($columnas, [$r['id'], /* ... */]);
            }
            break;
        // ...un case por tipo, cada uno reutilizando un método ya existente de ReportesModel...
    }

    $id = (new ReporteIaModel())->crear(
        (int) Session::user()['id'], $titulo, ucfirst($tipo) . " del $desde al $hasta",
        $tipo, null, ['desde' => $desde, 'hasta' => $hasta], $columnas, $filas
    );

    return ['ok' => true, 'reporte_id' => $id, 'titulo' => $titulo, 'url' => '/reportes-ia/ver?id=' . $id, 'filas' => count($filas)];
}
```

Punto clave para portar: **`ReportesModel` (las queries) ya existía antes del chat** — son
las mismas consultas que usa la exportación CSV manual de la pantalla de Reportes normal
(`ReportesController::exportarCsv()`). El chat no duplica esa lógica de negocio, solo la
envuelve para poder invocarla desde una tool y persistir el resultado. Si el proyecto nuevo
ya tiene pantallas de reportes tradicionales, la forma más rápida de darle esta
funcionalidad al chat es exactamente esta: reusar esas queries, no reescribirlas.

`generar_reporte_personalizado` es más simple porque no arma columnas a mano — usa
directamente las claves del primer resultado:

```php
private function toolGenerarReportePersonalizado(array $args): array
{
    $titulo = trim((string)($args['titulo'] ?? ''));
    $sql    = (string)($args['sql'] ?? '');
    if ($titulo === '' || $sql === '') {
        return ['ok' => false, 'error' => 'Faltan datos: titulo y sql son obligatorios.'];
    }

    $filas    = $this->model->ejecutarConsulta($sql, 1000); // misma validación de 5.2, catch afuera
    $columnas = $filas ? array_keys($filas[0]) : [];

    $id = (new ReporteIaModel())->crear(
        (int) Session::user()['id'], $titulo, trim((string)($args['descripcion'] ?? '')) ?: null,
        'personalizado', $sql, [], $columnas, $filas
    );

    return ['ok' => true, 'reporte_id' => $id, 'titulo' => $titulo, 'url' => '/reportes-ia/ver?id=' . $id, 'filas' => count($filas)];
}
```

### 5.4 El snapshot: por qué el reporte NO vuelve a consultar la base al abrirlo

`ReporteIaModel::crear()` guarda el `resultado` (las filas) como JSON **ya resuelto**, no
el SQL para volver a correrlo cada vez que alguien lo abre:

```php
public function crear(?int $usuarioId, string $titulo, ?string $descripcion, string $tipo,
    ?string $sqlEjecutado, array $parametros, array $columnas, array $resultado,
    string $origen = 'chat'): int
{
    return $this->insert([
        'usuario_id'    => $usuarioId,   // nullable: admite reportes generados por un cron sin usuario humano detrás
        'origen'        => $origen,      // 'chat' | 'cron'
        'titulo'        => $titulo,
        'descripcion'   => $descripcion,
        'tipo'          => $tipo,        // 'personalizado', o uno de los tipos predefinidos
        'sql_ejecutado' => $sqlEjecutado, // NULL para predefinidos (no hace falta guardarlo, la query vive en PHP)
        'parametros'    => json_encode($parametros, JSON_UNESCAPED_UNICODE),
        'columnas'      => json_encode($columnas, JSON_UNESCAPED_UNICODE),
        'resultado'     => json_encode($resultado, JSON_UNESCAPED_UNICODE), // snapshot, no un puntero a re-consultar
    ]);
}
```

Es una decisión de diseño deliberada, con dos razones:

1. **Auditoría/estabilidad**: un reporte de "ventas de junio" tiene que seguir mostrando
   exactamente lo que mostraba el día que se generó, aunque después se corrija una venta de
   junio con carga tardía o un ajuste contable. Si `ver()` volviera a ejecutar el SQL,
   el reporte "cambiaría solo" con el tiempo sin que nadie lo pidiera.
2. **Seguridad económica**: si `generar_reporte_personalizado` guardara solo el SQL y lo
   re-ejecutara en cada vista, cada apertura del reporte sería una consulta arbitraria más
   corriendo contra la base — el snapshot desacopla "cuánto cuesta generarlo" (una vez) de
   "cuántas veces se mira" (ilimitado, gratis, es solo leer una fila ya guardada).

Consecuencia directa en `ReportesIaController`: `ver()` y `exportarXlsx()` **nunca tocan la
tabla original**, solo leen `columnas`/`resultado` ya decodificados de `reportes_ia`. La
exportación arma el `.xlsx` a partir de ese mismo snapshot con PhpSpreadsheet
(`Spreadsheet` → una celda por columna en la fila 1, encabezados en negrita, autosize de
columnas, `header('Content-Disposition: attachment; ...')`) — no hay nada de lógica de
negocio en el exportador, solo iterar `columnas` × `resultado`.

### 5.5 De la function call a la tarjeta con botones en el chat

El resultado de la tool (`{ok, reporte_id, titulo, url, filas}`) primero vuelve a Gemini
como `functionResponse` (igual que cualquier otra tool, sección 1.1) para que el modelo
redacte el resumen en texto ("Generé el reporte de ventas de junio, 214 filas..."). Pero
además, `ChatController::mensaje()` **intercepta ese mismo resultado por fuera del loop** en
una variable `$reporteGenerado`, para poder decorar la respuesta final con datos que Gemini
no necesita ver ni podría inventar de forma confiable (el id y la url reales del reporte):

```php
} elseif (in_array($nombre, ['generar_reporte_predefinido', 'generar_reporte_personalizado'], true)) {
    if (!empty($result['ok'])) $reporteGenerado = $result;
}
// ...
// Cuando el loop termina (sin más functionCalls), $reporteGenerado (si se seteó) decide
// el "tipo" de la respuesta que se manda al frontend:
if ($reporteGenerado !== null) {
    $tipo = 'reporte';
} elseif (!empty($acciones) || !is_null($resultados)) {
    $tipo = !empty($acciones) ? 'accion' : 'sql';
} else {
    $tipo = 'texto';
}
$this->json([
    'ok' => true, 'tipo' => $tipo, 'respuesta' => $textFinal,
    'reporte_id'  => $reporteGenerado['reporte_id'] ?? null,
    'reporte_url' => $reporteGenerado['url']        ?? null,
    // ...
]);
```

El frontend (`app/Views/chat/index.php`) solo necesita un `if` sobre ese `tipo` para agregar
los botones a la burbuja de respuesta:

```js
if (tipo === 'reporte' && reporteUrl) {
    html += '<div class="d-flex gap-2 mt-2">'
        + '<a href="' + BASE_URL + reporteUrl + '" class="btn btn-sm btn-success">Ver reporte</a>'
        + '<a href="' + BASE_URL + reporteUrl.replace('/ver?', '/exportar?') + '" class="btn btn-sm btn-outline-success">Descargar Excel</a>'
        + '</div>';
}
```

Nótese que el link de descarga se arma con un `.replace()` sobre la misma URL en vez de
mandar dos URLs desde el backend — funciona porque las dos rutas comparten el mismo `id` y
solo cambia el verbo (`/ver` vs `/exportar`); si el proyecto nuevo arma rutas menos
predecibles, es más simple mandar `reporte_url_ver` y `reporte_url_excel` por separado que
replicar este atajo.

### 5.6 La galería (`/reportes-ia`): dónde vive todo lo ya generado, no solo lo de esta charla

`ReportesIaController` expone 4 acciones sobre lo mismo que arma `ReporteIaModel`:
`index()` (galería, lista todos los reportes de todos los usuarios — no solo los de esta
conversación), `ver()` (detalle: tabla HTML del snapshot), `exportarXlsx()` (descarga,
sección 5.4), `eliminar()` (borra — permitido al autor o a un admin, chequeado a mano
comparando `$reporte['usuario_id']` contra `Session::user()['id']`, ya que borrar un
reporte no pasa por `can()`/`canEdit()` sino por ser dueño del recurso). Esta pantalla es lo
que le da persistencia real al "generado por chat" — sin ella, el link que aparece en la
burbuja del chat sería la única forma de llegar al reporte, y se perdería en cuanto se
scrollee el historial de mensajes.

**Para portar la sección 5 completa a otro proyecto**, el orden de trabajo sugerido es:

1. Definí `MCP_TABLAS_OK` para tu esquema (5.2) y copiá `ejecutarConsulta()` tal cual.
2. Creá la tabla `reportes_ia` (Apéndice A.4) y `ReporteIaModel` (5.4) — esto ya te da
   `consultar_datos` + `generar_reporte_personalizado` funcionando de punta a punta.
3. Si tenés pantallas de reportes tradicionales previas, agregá `generar_reporte_predefinido`
   envolviendo esas queries existentes (5.3) — si no las tenés todavía, arrancá sin esta
   tool y agregala más adelante, `generar_reporte_personalizado` ya cubre el caso general.
4. Armá la pantalla `/reportes-ia` (galería + ver + exportar) reusando 5.6 — es la pieza que
   hace que "reporte generado por IA" sea un objeto persistente del sistema, no un mensaje
   de chat efímero.
5. Conectá el `tipo: 'reporte'` en la respuesta del chat (5.5) para que la burbuja muestre
   los botones — es la parte más chica pero la que hace que el usuario final no tenga que
   saber que existe la pantalla `/reportes-ia` para llegar a su reporte recién generado.

---

## 6. Resiliencia: cadena de fallback de modelos ante límites de cuota

Archivos: `config/config.php` (constantes `GEMINI_MODEL` / `GEMINI_MODEL_FALLBACK`),
`ChatController::mensaje()`, `elegirModeloConCupo()`, `llamarGemini()`,
`parseRetrySegundos()`, `mensajeErrorGemini()` (todos en `ChatController.php`).

Contexto del porqué: el plan gratuito de Gemini tiene **dos cuotas independientes por
modelo** — RPM (solicitudes por minuto) y RPD (solicitudes por día) — y son cuotas
**separadas por cada modelo**, no compartidas. Cuando un mensaje del chat encadena varias
llamadas (function-calling), se puede agotar el RPM/RPD de un modelo con poco uso real.

- `GEMINI_MODEL_FALLBACK` en `.env` es una **lista separada por coma**, en el orden en que
  se prueban: `gemini-3.6-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite`.
- Un ID de modelo mal escrito en la cadena **no rompe nada**: esa llamada devuelve error
  (ej. 400) y el sistema pasa al siguiente modelo de la lista igual que ante un 429 — queda
  visible en el log de diagnóstico (sección 7) para corregirlo.
- Importante: RPM se libera solo (ventana de ~1 minuto), pero **RPD se resetea recién al
  otro día** — si un modelo ya superó su cuota diaria, insistir con reintentos no sirve,
  hay que cambiar de modelo.

### 6.1 Por qué el cambio de modelo NO puede pasar en cualquier momento

Esta es la restricción que más moldeó el diseño, y viene de un bug real (mensaje #669,
"Compras semanal x usuario"): los modelos Gemini 3.x exigen que el historial de
function-calling que se les manda incluya una `thought_signature` — un campo opaco que
genera el propio modelo al devolver una function call, y que hay que reenviarle tal cual en
el turno siguiente. Esa firma está atada al modelo que la generó: si a mitad de una
conversación con varias function calls ya resueltas se le pasa ese historial a OTRO modelo,
Gemini responde `400 INVALID_ARGUMENT`.

Consecuencia directa en el diseño: **el modelo se elige una sola vez por mensaje, antes de
la primera llamada, y se mantiene fijo durante todo el loop de function-calling de ese
mensaje** — nunca se cambia de modelo a mitad de camino. El único momento en que está
permitido probar otro modelo de la cadena es la iteración 0 (la primerísima llamada, antes
de que exista ningún historial de function-calling que perder).

### 6.2 El try/catch en capas, tal cual está en el código

Son tres niveles distintos, cada uno resolviendo un problema distinto — vale la pena
copiar la estructura completa, no solo la idea:

**Nivel 1 — elegir el modelo de arranque, sin llamar a la API todavía**
(`elegirModeloConCupo()`, `ChatController.php:3882`). Antes de la primera llamada, mira
cuánto cupo le queda a cada modelo de la cadena según lo que el propio sistema ya registró
en `gemini_llamadas_log` (RPM/RPD cargados a mano por el admin en `/gemini-uso`, sección 7).
Es una estimación local, no la cuota real de Google — por eso todo el `try` está envuelto en
un solo `catch` genérico que, si la tabla de límites todavía no existe (recién instalado,
sin migrar), simplemente cae al modelo principal sin explotar:

```php
private function elegirModeloConCupo(array $modelos): string
{
    if (count($modelos) <= 1) return $modelos[0] ?? GEMINI_MODEL;
    try {
        $limites  = (new GeminiUsageModel())->listarLimitesModelos();
        $logModel = new GeminiLlamadaLogModel();
        foreach ($modelos as $modelo) {
            $limite = $limites[$modelo] ?? null;
            if (!$limite) return $modelo; // sin límite configurado: se intenta directo
            $uso   = $logModel->usoActualPorModelo($modelo);
            $rpmOk = $limite['rpm'] === null || $uso['rpm_usado'] < $limite['rpm'];
            $rpdOk = $limite['rpd'] === null || $uso['rpd_usado'] < $limite['rpd'];
            if ($rpmOk && $rpdOk) return $modelo;
        }
    } catch (\Throwable $e) { /* tabla de límites aún no existe — usar el principal */ }
    return $modelos[0];
}
```

**Nivel 2 — reintento corto DEL MISMO modelo** (`llamarGemini()`, línea 3906). Un `for`
de como máximo 2 intentos. No es un `try/catch` de excepciones PHP: la llamada HTTP se hace
con cURL directo (no una librería que lance excepciones), así que "atrapar el error" acá es
leer el código HTTP devuelto y decidir en base a eso — el patrón real es más "si no vino
200, mirá qué pasó y decidí" que un `catch`:

```php
for ($intento = 1; $intento <= 2; $intento++) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30, // no 90: ver por qué en el comentario del código real
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        return json_decode($response, true); // éxito: log de costeo+diagnóstico y listo
    }

    // Google manda el tiempo de espera REAL en el cuerpo del 429 ("Please retry in 5.2s")
    $retrySegundos = $httpCode === 429 ? $this->parseRetrySegundos((string) $response) : null;

    // Solo vale la pena esperar si Google pide POCO — si pide 50-60s (RPD agotado),
    // cortar directo y pasar al siguiente modelo es mejor que bloquear al usuario.
    if ($httpCode === 429 && $intento === 1 && $retrySegundos !== null && $retrySegundos <= 10) {
        sleep((int) ceil($retrySegundos));
        continue;
    }
    return null; // acá es donde el nivel 3 (afuera) sabe que hay que probar OTRO modelo
}
```

`parseRetrySegundos()` es una sola línea con una regex sobre el cuerpo crudo del error:

```php
private function parseRetrySegundos(string $body): ?int
{
    if (preg_match('/retry in ([\d.]+)s/i', $body, $m)) return (int) ceil((float) $m[1]);
    return null;
}
```

**Nivel 3 — probar el RESTO de la cadena, solo en la iteración 0** (`mensaje()`, alrededor
de la línea 414). Este es el nivel que de verdad "cambia de modelo". La condición
`$iter === 0` es la que hace cumplir la regla de la sección 6.1:

```php
$modelosDisponibles = $this->obtenerCadenaModelos();
$modeloElegido      = $this->elegirModeloConCupo($modelosDisponibles);

for ($iter = 0; $iter < 10; $iter++) {
    $apiResp = $this->llamarGemini($systemPrompt, $messages, $tools, $mensajeUsuarioId, $iter + 1, $modeloElegido);

    // Solo se prueba otro modelo si esto es la PRIMERA llamada del mensaje — a mitad de
    // conversación no es seguro (ver 6.1, thought_signature).
    if ($apiResp === null && $iter === 0) {
        foreach ($modelosDisponibles as $candidato) {
            if ($candidato === $modeloElegido) continue;
            $apiResp = $this->llamarGemini($systemPrompt, $messages, $tools, $mensajeUsuarioId, $iter + 1, $candidato);
            if ($apiResp !== null) { $modeloElegido = $candidato; break; } // desde acá, este modelo queda fijo
        }
    }

    if ($apiResp === null) {
        $error = $this->mensajeErrorGemini(); // mensaje distinto según si falló en iter 0 o a mitad de tarea
        $this->model->guardarMensaje($usuarioId, 'assistant', $error);
        $this->json(['ok' => false, 'error' => $error]);
    }
    // ... seguir procesando $apiResp normalmente
}
```

`mensajeErrorGemini()` usa la iteración en la que se cortó para diferenciar el mensaje: si
fue en la 1 (nunca se llegó a arrancar), invita a reintentar apuntando a que el sistema va a
elegir otro modelo solo; si fue más adelante (ya se venían resolviendo function calls),
avisa que se perdió el avance de esa tarea puntual, porque ahí no hay nada más que "esperar
unos segundos" pueda arreglar.

### 6.3 Cómo portarlo a otro sistema

1. Necesitás, como mínimo, la tabla de diagnóstico (`gemini_llamadas_log`, sección 7) desde
   el día uno — sin ella, `elegirModeloConCupo()` no tiene de dónde leer el uso actual y
   siempre cae al modelo principal (funciona, pero sin la parte "inteligente").
2. Copiá la regla de los 3 niveles tal cual: (1) elegir modelo de arranque mirando cupo
   local, (2) reintento corto del mismo modelo solo si Google pide esperar poco, (3) probar
   el resto de la cadena solo si **no hay function-calling history todavía**. Si tu chat NO
   usa function-calling (por ejemplo, es solo generación de texto/reportes sin tools), el
   nivel 3 podés dejarlo activo en CUALQUIER iteración — la restricción de
   `thought_signature` de la sección 6.1 solo aplica cuando hay tools de por medio.
3. Ajustá `parseRetrySegundos()` al formato real de error que devuelva el proveedor que
   uses — la regex de acá es específica del texto que manda la API de Gemini.

---

## 7. Diagnóstico: separar "costeo agregado" de "detalle por llamada"

Archivos: `app/Models/GeminiUsageModel.php` (+ tabla `gemini_llamadas`) y
`app/Models/GeminiLlamadaLogModel.php` (+ tabla `gemini_llamadas_log`), pantallas
`/gemini-uso` y `/gemini-log`.

Son dos necesidades distintas que conviene separar en dos tablas:

- **Costeo** (`gemini_llamadas`): una fila por llamada exitosa, con tokens usados — sirve
  para calcular gasto estimado por día/mes. Liviano, agregable.
- **Diagnóstico** (`gemini_llamadas_log`): una fila por **cada intento real** (éxito o
  error, incluidos los reintentos), con: a qué mensaje del usuario pertenece
  (`mensaje_chat_id`), en qué vuelta del loop de function-calling (`iteracion`), qué modelo
  respondió, un **resumen corto** (no el prompt completo) de qué se envió y qué contestó
  Gemini, código HTTP, duración, tokens.

**Por qué no guardar el payload completo en cada fila**: el system prompt + las
declaraciones de herramientas pesan decenas de KB y son casi idénticos en cada llamada de
una misma conversación — guardarlos completos en cada fila sería carísimo en espacio sin
aportar información nueva. Lo que sí varía y aporta señal es un resumen corto: para lo
enviado, "texto del usuario" o "resultados de la función X"; para lo recibido, "llama a la
función Y" o el texto final. Eso alcanza para ver el patrón (cuántas llamadas encadenó un
mensaje, en qué función se traba) sin guardar megabytes de datos redundantes.

Con esta tabla se pudo diagnosticar en este proyecto, con evidencia y no a ciegas, que el
problema real no era "el chat hace demasiadas llamadas" sino un límite de cuota diario ya
agotado del lado de Google — un dato que el log de texto plano (`app.log`) por sí solo no
dejaba ver con claridad.

### 7.1 `/gemini-log` — el detalle por llamada, para diagnosticar un caso puntual

Archivos: `app/Controllers/GeminiLogController.php`, `app/Views/gemini-log/index.php`.
Acceso restringido a `rol === 'admin'` (chequeo directo en el controller, no un permiso de
`PermisosModel`, porque es información sensible de configuración/costo).

Es una sola tabla, filtrable por fecha (`desde`/`hasta`, default hoy) y por éxito/error, con
una fila por cada intento real de `GeminiLlamadaLogModel::registrar()` — incluye los
reintentos, no solo el resultado final. Columnas (viendo el ejemplo real que se usó para
diagnosticar el bug de esta misma sección): **Fecha/Hora, Usuario, Mensaje** (el id del
`chat_mensajes` al que pertenece esa llamada, para agrupar todas las llamadas de una misma
pregunta del usuario), **Iter.** (número de vuelta del loop de function-calling),
**Modelo**, **Enviado** (el resumen corto — "Llama a: consultar_datos(...)" o el texto del
usuario), **Recibido / Error** (el resumen de la respuesta, o el motivo si fue error),
**HTTP**, **Duración**, **Tokens**.

Encima de la tabla hay una lista de **"top ofensores"**
(`GeminiLlamadaLogModel::topMensajesConMasLlamadas()`): qué mensajes del chat encadenaron
más llamadas a la API — la forma más rápida de encontrar un loop de function-calling que se
fue de largo (por ejemplo, un pedido ambiguo que Gemini no lograba resolver en pocas
vueltas) sin tener que leer fila por fila.

Si la tabla `gemini_llamadas_log` todavía no existe (recién clonado el proyecto, sin correr
la migración), la pantalla no rompe: muestra un aviso con el `SOURCE
database/gemini_llamadas_log_setup.sql;` para copiar y pegar en phpMyAdmin, y listo — mismo
patrón defensivo que los `try/catch` de la sección 6.2 (todo lo que depende de esta tabla
está pensado para degradar sin romper si todavía no se migró).

### 7.2 `/gemini-uso` — el consumo agregado, para decidir con plata en la mano

Archivos: `app/Controllers/GeminiUsageController.php`, `app/Views/gemini-usage/index.php`,
tabla `gemini_llamadas` (costeo) + `gemini_llamadas_log` (para la sección de modelos).
Mismo control de acceso: solo `rol === 'admin'`.

Tres bloques visuales, de arriba a abajo:

1. **Tres tarjetas** (`row g-3`, una por categoría: Chat IA texto / Galería imagen / Galería
   video — nunca mezcladas, porque tienen estructuras de costo distintas). Cada tarjeta
   muestra: llamadas de hoy vs. el límite gratuito diario configurable
   (`llamadas_gratuitas_dia`), con una barra de progreso que cambia de verde a amarillo (60%)
   a rojo (90%) según qué tan cerca está del límite, más un badge "En cuota gratis" / "Sin
   cuota" (para Chat) o "De pago" (para imagen/video, que no tienen tier gratis); debajo,
   llamadas del mes y tokens del mes (o segundos generados, para video); al pie, el costo
   estimado del mes en USD y su conversión a ARS con un tipo de cambio configurable a mano.
2. **Tabla de uso diario** (últimos 30 días), con un `btn-group` arriba para cambiar de
   categoría sin recargar la página completa (recarga por link, no JS — simple y suficiente
   acá). Columnas: fecha, llamadas, tokens entrada/salida (o segundos, en video), costo USD
   estimado del día, y — solo para la categoría chat — una columna "% cuota día" con la
   misma barra de progreso semáforo que las tarjetas.
3. **Tabla "Límites por modelo (RPM/RPD)"** — es la ventana directa a la cadena de fallback
   de la sección 6: una fila por modelo de `GEMINI_MODEL` + `GEMINI_MODEL_FALLBACK`, con
   badge "principal" en el primero y "respaldo" en el resto. Como **Google no expone estos
   límites por API**, son campos editables a mano (`RPM (límite)` / `RPD (límite)`, guardados
   por `guardarLimitesModelos()`) — se cargan una vez consultando la sección "Límite de
   frecuencia" de Google AI Studio para cada modelo. La columna "Uso actual" muestra
   `usoActualPorModelo()` (el mismo cálculo que usa `elegirModeloConCupo()` para decidir, acá
   solo para mostrarlo) con otra barra de progreso semáforo sobre el RPD. Dejar el límite
   vacío es válido y significa "sin límite conocido, intentar siempre" — coincide con el
   `if (!$limite) return $modelo;` de `elegirModeloConCupo()`.

Al pie, una tabla de **errores recientes** (Chat + Galería juntos) — fecha + detalle en
texto plano, para tener a mano los últimos motivos de falla sin ir a `/gemini-log`.

**Para portar esto a otro proyecto**: lo mínimo que hace falta reproducir es la tabla de
límites por modelo con su barra de uso — es lo que le da sentido visual a la cadena de
fallback (sin esto, `GEMINI_MODEL_FALLBACK` es una env var invisible que nadie sabe si está
funcionando). Las 3 tarjetas de costo son opcionales si el proyecto nuevo no necesita
control de gasto fino por categoría.

Las 3 tablas de este apartado (`gemini_llamadas`, `gemini_llamadas_log`,
`gemini_modelos_limite`) están con el `CREATE TABLE` completo en el **Apéndice A**, al final
de este documento — se pueden ejecutar tal cual en cualquier base MySQL/MariaDB nueva.

---

## 8. Duplicados — patrón aplicable a cualquier extracción de documentos

Archivos: `app/Models/FacturaCompraModel::buscarDuplicado()`,
`GastoModel::buscarDuplicado()`, `PagoProveedorModel::buscarDuplicado()`.

Al extraer datos de un documento para guardarlos, conviene chequear si ya existe algo
parecido antes de guardar, para evitar cargas duplicadas (ej. la misma factura subida dos
veces). Estrategia de dos niveles:

1. **Dato fuerte primero**: si el documento trae un número de comprobante/operación, buscar
   coincidencia exacta por ese campo — es la señal más confiable.
2. **Fallback difuso solo si hay ancla**: si no hay número, comparar por
   entidad+monto+fecha **exacta** (mismo día, sin ventana de varios días) — una ventana
   amplia genera falsos positivos con pagos recurrentes legítimos (mismo proveedor, mismo
   monto, mes distinto). Sin una entidad de referencia (proveedor/cliente identificado), no
   hay ancla suficiente para un match difuso seguro — mejor no buscar nada en ese caso que
   arriesgar un falso positivo.

Es una **advertencia con link a lo ya cargado y quién/cuándo lo hizo**, no un bloqueo
silencioso — salvo el caso de dato fuerte exacto (mismo proveedor + mismo número de
comprobante), que no tiene ningún caso de uso legítimo y ahí sí se bloquea directo.

---

## 9. Mapa de archivos de referencia

| Archivo | Qué contiene |
|---|---|
| `app/Controllers/ChatController.php` | Todo: loop de function-calling, system prompt, tools, lectura de imagen/PDF, tarjetas de confirmación, fallback de modelos |
| `app/Models/ChatModel.php` | Historial de mensajes, guardado de `chat_mensajes` |
| `app/Models/GeminiUsageModel.php` | Costeo agregado (tokens/día, para calcular gasto) |
| `app/Models/GeminiLlamadaLogModel.php` | Log detallado por llamada (diagnóstico) |
| `app/Models/ReporteIaModel.php` | Persistencia de reportes generados por el chat (sección 5) |
| `app/Models/ReportesModel.php` | Queries de los 7 reportes predefinidos, reusadas por `generar_reporte_predefinido` (sección 5.3) |
| `app/Controllers/ReportesIaController.php` | Pantalla `/reportes-ia` — galería, detalle, export a Excel, borrado (sección 5.6) |
| `app/Views/reportes-ia/*.php` | UI de la galería y el detalle de un reporte |
| `config/config.php` | Constantes `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_MODEL_FALLBACK`, `MCP_TABLAS_OK` (whitelist de tablas para SQL de la IA, sección 5.2) |
| `database/gemini_llamadas_log_setup.sql` | Esquema de la tabla de diagnóstico — SQL completo en el **Apéndice A** |
| `database/gemini_usage_setup.sql` | Esquema de la tabla de costeo (`gemini_llamadas`) — SQL completo en el **Apéndice A** |
| `database/gemini_modelos_limite_setup.sql` | Esquema de los límites RPM/RPD por modelo — SQL completo en el **Apéndice A** |
| `app/Controllers/GeminiLogController.php` | Pantalla `/gemini-log` — detalle por llamada (sección 7.1) |
| `app/Controllers/GeminiUsageController.php` | Pantalla `/gemini-uso` — consumo agregado + límites por modelo (sección 7.2) |
| `app/Views/gemini-log/index.php` | UI del log detallado |
| `app/Views/gemini-usage/index.php` | UI de tarjetas de costo + tabla diaria + tabla de límites por modelo |
| `app/Views/chat/index.php` | UI del chat: burbujas, tarjeta de confirmación, adjuntos |

---

## 10. Qué NO es directamente portable

Todo lo específico del dominio de este negocio es solo un **ejemplo del nivel de detalle**
que hace falta, no algo para copiar literal:

- Reglas de IVA argentino, tipos de comprobante AFIP, categorías de gasto — hay que
  rehacerlas con las reglas del dominio del proyecto nuevo.
- Nombres de tablas (`ventas`, `facturas_compra`, etc.) — el esquema va a ser distinto.
- Las herramientas concretas (`registrar_compra`, `registrar_pago`, etc.) — hay que
  diseñar las propias según qué acciones necesite el proyecto nuevo.

Lo que sí es portable prácticamente tal cual: la **estructura de la tabla
`gemini_llamadas_log`** (sección 7), el **mecanismo de cadena de fallback** de
`llamarGemini()` (sección 6), y el **patrón de tarjeta de confirmación** (sección 3) como
concepto de diseño.

---

## Apéndice A — Esquema SQL completo de las tablas de soporte

Documento pensado para compartirse solo (sin el resto del repo): las 4 tablas que sostienen
las secciones 5, 6 y 7 van completas acá abajo, tal cual están en
`database/gemini_llamadas_log_setup.sql`, `database/gemini_usage_setup.sql`,
`database/gemini_modelos_limite_setup.sql` y `database/reportes_ia_setup.sql` de este
proyecto — se pueden ejecutar en ese orden sobre una base MySQL/MariaDB nueva sin tener que
ir a buscar los archivos aparte.

### A.1 `gemini_llamadas_log` — diagnóstico por llamada (sección 7, 7.1)

```sql
CREATE TABLE IF NOT EXISTS `gemini_llamadas_log` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mensaje_chat_id`  INT UNSIGNED NULL COMMENT 'id del mensaje del usuario que disparó esta cadena de llamadas',
  `usuario_id`       INT UNSIGNED NULL,
  `iteracion`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'vuelta del loop de function-calling (1..10) dentro de ese mismo mensaje',
  `resumen_enviado`  VARCHAR(300) NULL COMMENT 'qué se le mandó a Gemini en esta llamada',
  `resumen_recibido` VARCHAR(300) NULL COMMENT 'qué contestó Gemini',
  `http_code`        SMALLINT NOT NULL,
  `exito`            TINYINT(1) NOT NULL,
  `tokens_prompt`    INT UNSIGNED NULL,
  `tokens_respuesta` INT UNSIGNED NULL,
  `duracion_ms`      INT UNSIGNED NULL,
  `error_resumen`    VARCHAR(500) NULL COMMENT 'body del error truncado, solo si exito=0',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gll_mensaje` (`mensaje_chat_id`),
  KEY `idx_gll_created` (`created_at`),
  KEY `idx_gll_exito`   (`exito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Si el proyecto nuevo no tiene una tabla `chat_mensajes` propia, `mensaje_chat_id` puede
quedar sin FK (así está acá a propósito, `NULL` sin `CONSTRAINT`) — sirve igual como
agrupador lógico para "todas las llamadas que disparó esta pregunta del usuario".

### A.2 `gemini_llamadas` + `gemini_config` — costeo agregado (sección 7, 7.2)

```sql
CREATE TABLE IF NOT EXISTS `gemini_llamadas` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `fecha`            DATE         NOT NULL,
  `modelo`           VARCHAR(100) NOT NULL DEFAULT '',
  `categoria`        ENUM('chat','imagen','video') NOT NULL DEFAULT 'chat' COMMENT 'Para separar consumo/costo de cada categoría de uso — ajustar el ENUM a las categorías reales del proyecto nuevo',
  `tokens_prompt`    INT          NOT NULL DEFAULT 0,
  `tokens_respuesta` INT          NOT NULL DEFAULT 0,
  `tokens_total`     INT          NOT NULL DEFAULT 0,
  `video_segundos`   INT UNSIGNED DEFAULT NULL COMMENT 'Solo para categorías facturadas por segundo en vez de por token (ej. generación de video)',
  `usuario_id`       INT          DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecha`       (`fecha`),
  KEY `idx_usuario_id`  (`usuario_id`),
  KEY `idx_categoria`   (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila única de configuración editable desde la propia pantalla /gemini-uso (guardarConfig()) —
-- evita hardcodear precios que Google cambia de tanto en tanto.
CREATE TABLE IF NOT EXISTS `gemini_config` (
  `clave` VARCHAR(50)  NOT NULL,
  `valor` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `gemini_config` (`clave`, `valor`) VALUES
  ('llamadas_gratuitas_dia',    '1500'),  -- ajustar al límite real del modelo principal elegido
  ('costo_input_por_millon',    '0.10'),  -- USD por millón de tokens de entrada
  ('costo_output_por_millon',   '0.40'),  -- USD por millón de tokens de salida
  ('costo_video_por_segundo',   '0.40'),
  ('tipo_cambio_usd_ars',       '1200');  -- reemplazar por la moneda local del proyecto nuevo, o borrar si no aplica
```

### A.3 `gemini_modelos_limite` — límites RPM/RPD por modelo (sección 6, 7.2)

```sql
CREATE TABLE IF NOT EXISTS `gemini_modelos_limite` (
  `modelo`     VARCHAR(50) NOT NULL,
  `rpm`        INT UNSIGNED NULL COMMENT 'Solicitudes por minuto permitidas. NULL = sin límite configurado (no se chequea).',
  `rpd`        INT UNSIGNED NULL COMMENT 'Solicitudes por día permitidas. NULL = sin límite configurado.',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`modelo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores de referencia del nivel gratuito de Gemini al momento de escribir esto — Google
-- los cambia sin aviso. Confirmar siempre contra "Límite de frecuencia" en Google AI Studio
-- antes de confiar en estos números para el proyecto nuevo.
INSERT IGNORE INTO `gemini_modelos_limite` (`modelo`, `rpm`, `rpd`) VALUES
('gemini-2.5-flash',      5, 20),
('gemini-3.6-flash',      5, 20),
('gemini-3.5-flash-lite', 15, 500),
('gemini-3.1-flash-lite', 15, 500);
```

### A.4 `reportes_ia` — reportes generados por el chat, guardados como snapshot (sección 5)

```sql
CREATE TABLE IF NOT EXISTS `reportes_ia` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NULL COMMENT 'NULL si lo generó un proceso automático sin usuario humano detrás',
  `origen`        ENUM('chat','cron') NOT NULL DEFAULT 'chat' COMMENT 'chat = lo pidió un usuario al Chat IA, cron = lo generó un script automático — quitar el ENUM o dejar solo "chat" si el proyecto nuevo no tiene generación programada',
  `titulo`        VARCHAR(150) NOT NULL,
  `descripcion`   VARCHAR(500) DEFAULT NULL,
  `tipo`          VARCHAR(30)  NOT NULL COMMENT 'personalizado, o uno de los tipos predefinidos propios del proyecto (ventas, compras, stock, etc.)',
  `sql_ejecutado` TEXT         DEFAULT NULL COMMENT 'Solo para tipo personalizado, la consulta SELECT que se ejecutó — NULL en predefinidos, la query vive en PHP',
  `parametros`    JSON         DEFAULT NULL COMMENT 'Filtros usados (desde, hasta, etc.), para mostrar contexto en la vista',
  `columnas`      JSON         NOT NULL COMMENT 'Nombres de columnas en orden, para que tabla en pantalla y export a Excel queden consistentes',
  `resultado`     LONGTEXT     NOT NULL COMMENT 'JSON del array de filas — SNAPSHOT al momento de generar el reporte, no se vuelve a consultar la base al verlo (ver sección 5.4 del porqué)',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ria_usuario` (`usuario_id`),
  KEY `idx_ria_created` (`created_at`),
  CONSTRAINT `fk_ria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Si el proyecto nuevo no necesita reportes generados por cron (solo los que pide el chat en
el momento), se puede simplificar: `usuario_id` vuelve `NOT NULL` y se quita la columna
`origen` entera — es la forma en que arrancó originalmente esta tabla en este proyecto,
antes de que se agregara generación automática por cron.

**Nota para el LLM que reciba este documento**: estos 4 `CREATE TABLE` son el único punto
donde este documento depende de MySQL/MariaDB específicamente — si el proyecto nuevo usa
otro motor de base de datos, el esquema (columnas, tipos, para qué sirve cada una) es igual
de válido, solo hay que traducir la sintaxis del `CREATE TABLE` al motor que corresponda.
