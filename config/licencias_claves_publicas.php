<?php
/**
 * Claves públicas que verifican las licencias de los módulos que se cobran aparte (ver
 * docs/dewoc-cobros-checklist.md, tareas 1A.1 a 1A.3). Indexadas por `kid`: cada licencia
 * dice con qué clave se firmó.
 *
 * La clave PRIVADA que corresponde a cada una está solo en la PC del proveedor, fuera del
 * repositorio. Para cambiar de clave (por ejemplo, si se filtra la privada): se genera un
 * par nuevo, se agrega acá con otro `kid` y se BORRA la entrada vieja — las licencias
 * firmadas con la vieja dejan de valer y hay que volver a emitirlas.
 */
return [
    // Generada el 2026-11-02 (ECDSA P-256).
    'k1' => <<<PEM
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEmPgMS1DgD3BepPkMvGVkJxYBT5sE
OKES1Qi86Gn8znkug+nWw5G8nA7g1Da0GA6hbmJPnANnjk7fcuccKvXJIA==
-----END PUBLIC KEY-----
PEM,
];
