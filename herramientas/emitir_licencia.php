<?php
/**
 * Emite una licencia firmada de un módulo de Dewoc (ver docs/dewoc-cobros-checklist.md,
 * tareas 1A.10 y C22). La corre el PROVEEDOR en su PC, nunca el servidor de un comercio.
 *
 *   php herramientas/emitir_licencia.php \
 *       --clave="C:/Users/Usuario/Documents/Proyectos/01-Dewoc/claves/licencias_privada.pem" \
 *       --instalacion=<código de instalación que muestra Integraciones → Dewoc Cobros> \
 *       --comercio="Heladería Serendipia" --dispositivos=2 \
 *       --desde=2026-11-01 --hasta=2026-11-30
 *
 * Opcionales: --modulo=dewoc_cobros (por defecto) · --kid=k1 (por defecto) ·
 *             --registro=<archivo CSV> (por defecto, licencias_emitidas.csv junto a la clave).
 *
 * Imprime la licencia lista para pegar y agrega una línea al registro de licencias emitidas
 * (a quién, cuántos celulares y qué período), que vive FUERA del repositorio, junto a la
 * clave privada.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de comandos.');
}

require_once dirname(__DIR__) . '/core/Licencias.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$opt = getopt('', ['clave:', 'instalacion:', 'comercio:', 'dispositivos:', 'desde:', 'hasta:', 'modulo::', 'kid::', 'registro::']);

$faltan = array_diff(['clave', 'instalacion', 'comercio', 'dispositivos', 'desde', 'hasta'], array_keys($opt));
if ($faltan) {
    fwrite(STDERR, 'Faltan: --' . implode(', --', $faltan) . "\nVer el encabezado de este archivo para el uso.\n");
    exit(1);
}

$modulo = $opt['modulo'] ?? 'dewoc_cobros';
$kid    = $opt['kid'] ?? 'k1';

function fallar(string $msg): never
{
    fwrite(STDERR, "Error: {$msg}\n");
    exit(1);
}

// La clave tiene que estar fuera del repositorio (1A.1): si alguien la copió adentro, que
// se entere antes de commitearla.
$rutaClave = realpath($opt['clave']) ?: fallar("no existe la clave privada {$opt['clave']}");
if (str_starts_with(strtolower(str_replace('\\', '/', $rutaClave)), strtolower(str_replace('\\', '/', dirname(__DIR__))))) {
    fallar('la clave privada está dentro del repositorio. Movela fuera (ver tarea 1A.1).');
}
$clave = openssl_pkey_get_private(file_get_contents($rutaClave)) ?: fallar('no se pudo leer la clave privada.');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $opt['instalacion'])) {
    fallar('--instalacion tiene que ser el UUID que muestra Integraciones (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).');
}
$dispositivos = filter_var($opt['dispositivos'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
if ($dispositivos === false) fallar('--dispositivos tiene que ser un entero entre 1 y 50.');
foreach (['desde', 'hasta'] as $c) {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $opt[$c]);
    if (!$dt || $dt->format('Y-m-d') !== $opt[$c]) fallar("--{$c} tiene que ser una fecha AAAA-MM-DD.");
}
if ($opt['desde'] > $opt['hasta']) fallar('--desde es posterior a --hasta.');

$datos = [
    'kid'            => $kid,
    'licencia_id'    => 'L' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))),
    'instalacion_id' => strtolower($opt['instalacion']),
    'comercio'       => $opt['comercio'],
    'modulo'         => $modulo,
    'dispositivos'   => $dispositivos,
    'desde'          => $opt['desde'],
    'hasta'          => $opt['hasta'],
    'emitida_at'     => date('c'),
];

$licencia = Licencias::firmar($datos, $clave);

// Comprobación: la licencia recién firmada tiene que verificar con la clave pública que
// está en el código para ese kid. Si no, la clave privada no es la que corresponde.
$v = Licencias::verificar($licencia, $datos['instalacion_id'], $modulo);
if (!$v['ok']) {
    fallar("la licencia no verifica con config/licencias_claves_publicas.php ({$v['error']}). ¿La clave privada corresponde al kid '{$kid}'?");
}

$registro = $opt['registro'] ?? dirname($rutaClave) . '/licencias_emitidas.csv';
$nuevo    = !file_exists($registro);
$fh       = fopen($registro, 'a') ?: fallar("no se pudo escribir el registro {$registro}");
if ($nuevo) fputcsv($fh, ['emitida_at', 'licencia_id', 'comercio', 'instalacion_id', 'modulo', 'dispositivos', 'desde', 'hasta']);
fputcsv($fh, [$datos['emitida_at'], $datos['licencia_id'], $datos['comercio'], $datos['instalacion_id'], $modulo, $dispositivos, $datos['desde'], $datos['hasta']]);
fclose($fh);

echo "Licencia {$datos['licencia_id']} · {$datos['comercio']} · {$modulo} · {$dispositivos} celular(es) · {$datos['desde']} a {$datos['hasta']}\n";
echo "Registrada en {$registro}\n\n";
echo $licencia . "\n";
