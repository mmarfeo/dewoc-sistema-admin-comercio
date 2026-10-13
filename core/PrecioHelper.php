<?php
/**
 * Único lugar del sistema donde se decide cómo se interpreta un precio de venta cargado
 * (ver docs/integracion-menuapp-checklist.md, decisión D9 y tarea 0.6). Nadie más debe
 * volver a hacer la cuenta del IVA por su lado: POS, Portal mayorista, Chat IA, reportes
 * y la API de MenuApp pasan todos por acá.
 *
 * Separa DOS conceptos que antes iban pegados a la misma constante:
 *
 * 1. Si el precio CARGADO ya trae IVA → configuracion_fiscal.precios_incluyen_iva (D9).
 *    Rige igual para productos.precio_venta y para productos.precio_online.
 * 2. Si el comprobante DISCRIMINA IVA → NEGOCIO_CONDICION_IVA (condición fiscal).
 *    Un Responsable Inscripto emite Factura A/B y desglosa neto + IVA; un monotributista
 *    emite Factura C y nunca discrimina, cobre lo que cobre.
 *
 * Son independientes: un Responsable Inscripto con "incluyen IVA" cobra el precio tal
 * cual y lo desglosa en el comprobante; un monotributista con "no incluyen" suma el IVA
 * al precio pero sigue sin discriminarlo.
 *
 * Con la opción en NULL (todavía no configurada) se reproduce EXACTAMENTE el
 * comportamiento anterior a esta funcionalidad, para no cambiarle los precios a nadie
 * sin que lo haya pedido:
 *   - Responsable Inscripto  → "no incluyen" (el POS sumaba el IVA al precio_venta);
 *   - cualquier otra condición → "incluyen"  (el precio_venta era el precio final).
 */
class PrecioHelper
{
    /** Cache por request de configuracion_fiscal.precios_incluyen_iva (null = sin leer todavía). */
    private static ?bool $cacheIncluyenIva = null;

    /**
     * ¿Los precios de venta cargados (precio_venta / precio_online) ya traen el IVA?
     * Lee configuracion_fiscal una sola vez por request.
     */
    public static function preciosIncluyenIva(): bool
    {
        if (self::$cacheIncluyenIva !== null) return self::$cacheIncluyenIva;

        $valor = null;
        try {
            // Consulta directa en vez de ConfiguracionFiscalModel: este helper lo usan
            // también los crons y la API, que no siempre tienen los modelos cargados.
            $valor = Database::getInstance()
                ->query("SELECT precios_incluyen_iva FROM configuracion_fiscal WHERE id = 1")
                ->fetchColumn();
            if ($valor === false) $valor = null;
        } catch (\Throwable $e) {
            // Falta la migración o la base no responde: se cae al default histórico, nunca
            // se rompe una venta por no poder leer una preferencia.
            logApp('PrecioHelper: no se pudo leer precios_incluyen_iva (' . $e->getMessage() . '). Se usa el default histórico.');
        }

        self::$cacheIncluyenIva = $valor === null
            ? !self::discriminaIva()           // default histórico (ver el bloque de arriba)
            : (bool)(int)$valor;

        return self::$cacheIncluyenIva;
    }

    /** Valor efectivo que la pantalla de configuración muestra preseleccionado, sin haberse guardado nunca. */
    public static function preciosIncluyenIvaPorDefecto(): bool
    {
        return !self::discriminaIva();
    }

    /** true si todavía nadie guardó la opción (la pantalla lo aclara al usuario). */
    public static function sinConfigurar(): bool
    {
        try {
            $valor = Database::getInstance()
                ->query("SELECT precios_incluyen_iva FROM configuracion_fiscal WHERE id = 1")
                ->fetchColumn();
            return $valor === null || $valor === false;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * ¿El comprobante discrimina IVA? Es la condición fiscal, NO la opción D9.
     * Responsable Inscripto → Factura A/B con IVA desglosado; el resto → Factura C.
     */
    public static function discriminaIva(): bool
    {
        return defined('NEGOCIO_CONDICION_IVA') && NEGOCIO_CONDICION_IVA === 'responsable_inscripto';
    }

    /**
     * Alícuota que realmente se guarda en ventas_detalle.iva_porcentaje: la del producto
     * si el comprobante discrimina IVA, 0 si no. Un monotributista guarda 0 aunque el
     * producto tenga 21% cargado — ese 21% solo sirve para calcular el precio final
     * cuando los precios "no incluyen IVA".
     */
    public static function ivaEfectivo(float $ivaProducto): float
    {
        return self::discriminaIva() ? $ivaProducto : 0.0;
    }

    /**
     * Precio FINAL al consumidor (lo que efectivamente paga) a partir del precio cargado.
     * Es el único número que MenuApp y el Portal necesitan mostrar.
     */
    public static function precioFinal(float $precioCargado, float $ivaProducto): float
    {
        if (self::preciosIncluyenIva()) return round($precioCargado, 2);
        return round($precioCargado * (1 + $ivaProducto / 100), 2);
    }

    /**
     * Precio cargado (el que va en el formulario del producto) a partir de un precio final
     * conocido — operación inversa de precioFinal(). La usa la API cuando MenuApp manda el
     * precio final y hay que compararlo contra lo que tiene cargado el producto.
     */
    public static function precioCargadoDesdeFinal(float $precioFinal, float $ivaProducto): float
    {
        if (self::preciosIncluyenIva()) return round($precioFinal, 2);
        return round($precioFinal / (1 + $ivaProducto / 100), 2);
    }

    /**
     * Separa un precio final en neto + IVA, con la alícuota EFECTIVA (ver ivaEfectivo()).
     * Devuelve ['neto' => float, 'iva' => float] y garantiza que neto + iva == $precioFinal
     * al centavo: el IVA se calcula por resta en vez de por multiplicación, así el total
     * guardado en `ventas` nunca difiere del que vio el cliente por un redondeo.
     *
     * $decimales: 2 para totales de venta, 4 para líneas de ventas_detalle (que la base
     * guarda con 4 decimales y después se suman).
     */
    public static function desglosar(float $precioFinal, float $ivaEfectivo, int $decimales = 2): array
    {
        $final = round($precioFinal, $decimales);
        if ($ivaEfectivo <= 0) {
            return ['neto' => $final, 'iva' => 0.0];
        }
        $neto = round($final / (1 + $ivaEfectivo / 100), $decimales);
        return ['neto' => $neto, 'iva' => round($final - $neto, $decimales)];
    }

    /**
     * Precio base del menú online de un producto: precio_online si tiene valor, si no
     * precio_venta. Se interpreta con el mismo criterio D9 que precio_venta.
     * $producto necesita las claves precio_online y precio_venta.
     */
    public static function precioBaseOnline(array $producto): float
    {
        $online = $producto['precio_online'] ?? null;
        return ($online !== null && $online !== '') ? (float)$online : (float)($producto['precio_venta'] ?? 0);
    }

    /** Precio final del menú online (lo que MenuApp muestra y cobra). */
    public static function precioFinalOnline(array $producto): float
    {
        return self::precioFinal(self::precioBaseOnline($producto), (float)($producto['iva'] ?? 0));
    }

    /** Invalida el cache — solo para tests, que cambian la configuración entre casos. */
    public static function limpiarCache(): void
    {
        self::$cacheIncluyenIva = null;
    }
}
