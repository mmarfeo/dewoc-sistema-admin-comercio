<?php
require_once __DIR__ . '/LectorCobro.php';

/**
 * Notificaciones de Mercado Pago (com.mercadopago.wallet).
 *
 * PRIMERA VERSIÓN: armada con los formatos conocidos de MP Argentina y la primera muestra
 * real ($ 10 de Daniela, 2026-11-02). Hay que ajustarla con las capturas del modo captura
 * (sección 5 del plan). Por eso es conservadora: lo que no entiende queda `no_reconocido`
 * (aparece en Integraciones para corregir el lector y volver a leerlo), nunca un cobro
 * inventado.
 *
 * Orden de las reglas:
 *  1. Plata que SALE (pagaste, transferiste, enviaste…) → descartado. Va primero porque
 *     "Transferiste $ 3.000 a Juan" también trae monto y nombre.
 *  2. Promociones, rendimientos, cashback, préstamos → descartado. Antes que la regla 3,
 *     porque "Recibiste un cashback de $ 500" no es un cobro de un cliente.
 *  3. Plata que ENTRA (recibiste, te envió, te transfirió, te pagaron…) → cobro.
 *  4. Todo lo demás → no_reconocido.
 */
class LectorMercadoPago extends LectorCobro
{
    const SALE = '/\b(pagaste|transferiste|enviaste|compraste|retiraste|cargaste|invertiste|
                  extrajiste|tu\s+pago|debitamos|se\s+debit[óo]|d[eé]bito\s+autom[aá]tico|vence|venci[óo]|
                  rechaz\w*|no\s+pudimos)\b/iux';

    const ENTRA = '/\b(recibiste|te\s+(envi[óo]|transfiri[óo]|pag[óo]|mand[óo]|depositaron|pagaron|transfirieron|enviaron)|
                   cobraste|ingres[óo]|ingresaron|lleg[óo]\s+(una\s+)?(transferencia|plata|dinero))\b/iux';

    const PROMO = '/(aprovech|descuento|\d+\s?%|cuotas|promo|beneficio|cashback|rendimiento|rindi|ganaste|
                   pr[eé]stamo|cr[eé]dito\s+disponible|invert[ií]|sorteo|cup[oó]n)/iux';

    public function leer(?string $titulo, ?string $texto, ?string $textoLargo): array
    {
        // El texto largo, si viene, suele ser el mismo texto completo (sin cortar con "…").
        $cuerpo = trim((string)($textoLargo ?: $texto));
        $todo   = trim(($titulo ?? '') . "\n" . $cuerpo);
        if ($todo === '') return self::resultado('no_reconocido');

        if (preg_match(self::SALE, $todo) || preg_match(self::PROMO, $todo)) return self::resultado('descartado');

        $monto = self::montoArgentino($cuerpo) ?? self::montoArgentino((string)$titulo);

        if (preg_match(self::ENTRA, $todo)) {
            return self::resultado('cobro', $monto, self::nombre($cuerpo) ?? self::nombre((string)$titulo));
        }

        return self::resultado('no_reconocido');
    }

    /**
     * Nombre de quien pagó, en los formatos:
     *   "Daniela Pérez te envió $ 10"     "Daniela te transfirió $ 1.500"
     *   "Recibiste $ 10 de Daniela Pérez"  "Recibiste una transferencia de Daniela Pérez"
     *   "De Daniela Pérez desde su cuenta de Mercado Pago."  (formato real en Android, 2026-11-02)
     */
    private static function nombre(string $t): ?string
    {
        $palabra = "[\\p{Lu}][\\p{L}'´.-]*";
        if (preg_match("/({$palabra}(?:\\s+(?:de\\s+|del\\s+)?[\\p{L}'´.-]+){0,5})\\s+te\\s+(?:envi|transfiri|pag|mand)[óo]/u", $t, $m)) {
            return $m[1];
        }
        // "De" también con mayúscula: el texto real empieza así, y si no se toma el primero, el
        // "de" de "desde su cuenta de Mercado Pago" deja "Mercado Pago" como nombre.
        if (preg_match("/\\b[Dd]e\\s+({$palabra}(?:\\s+[\\p{L}'´.-]+){0,5}?)\\s*(?:$|[.!,\\n]|\\s+(?:con|por|a\\s+tu|en\\s+tu|desde)\\b)/u", $t, $m)) {
            return $m[1];
        }
        return null;
    }
}
