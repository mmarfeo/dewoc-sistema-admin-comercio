<?php
/**
 * Lectores de notificaciones de Dewoc Cobros (ver docs/dewoc-cobros-checklist.md, C14 y
 * tarea 1.3). Cada app de pago escribe sus notificaciones distinto: el lector de cada una
 * decide si es un cobro y saca el monto y el nombre de quien pagó.
 *
 * Viven en Dewoc y no en el celular: si una app cambia su formato, el arreglo es un deploy
 * (no un APK nuevo) y las notificaciones que llegaron mientras tanto se pueden volver a leer,
 * porque el texto original queda guardado.
 *
 * No hacen llamadas externas: la app espera la respuesta (sección 3.5 del plan).
 */
abstract class LectorCobro
{
    /** Lectores disponibles: clave (la de cobros_paquetes.lector) → clase. */
    const LECTORES = [
        'mercadopago' => 'LectorMercadoPago',
    ];

    /**
     * Devuelve ['resultado' => cobro|descartado|no_reconocido, 'monto' => ?float,
     *           'nombre' => ?string]. Un cobro con el monto oculto viene con monto null.
     */
    abstract public function leer(?string $titulo, ?string $texto, ?string $textoLargo): array;

    /** El lector de esa clave, o null si no existe (la notificación queda sin_lector). */
    public static function para(?string $clave): ?self
    {
        $clase = self::LECTORES[$clave ?? ''] ?? null;
        if (!$clase) return null;
        require_once __DIR__ . "/{$clase}.php";
        return new $clase();
    }

    /**
     * Monto argentino a número: "$ 12.500,50" → 12500.50, "$500" → 500.0,
     * "$ 1.000.000,00" → 1000000.0. El punto separa miles y la coma, decimales.
     * null si el texto no trae un monto.
     */
    public static function montoArgentino(string $texto): ?float
    {
        if (!preg_match('/\$\s*(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?(?!\d)/u', $texto, $m)) return null;
        $entero = str_replace('.', '', $m[1]);
        $dec    = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
        return round((float)"{$entero}.{$dec}", 2);
    }

    protected static function resultado(string $resultado, ?float $monto = null, ?string $nombre = null): array
    {
        if ($nombre !== null) {
            $nombre = trim(preg_replace('/\s+/u', ' ', $nombre), " \t.,;:!¡-");
            $nombre = $nombre === '' ? null : mb_substr($nombre, 0, 120);
        }
        return ['resultado' => $resultado, 'monto' => $monto, 'nombre' => $nombre];
    }
}
