<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ConfiguracionFiscalModel extends Model
{
    protected string $table = 'configuracion_fiscal';

    public function obtener(): array
    {
        return $this->queryOne("SELECT * FROM configuracion_fiscal WHERE id = 1") ?: [
            'id' => 1, 'categoria_monotributo' => null, 'umbral_alerta_margen' => null,
            'bloquear_facturacion_afip' => 0, 'limite_facturacion_manual' => null,
            'saldo_prorateo_diario' => null, 'prorateo_desde' => null, 'prorateo_hasta' => null,
            'precios_incluyen_iva' => null,
        ];
    }

    /**
     * "Los precios de venta cargados incluyen IVA" — decisión D9, ver
     * docs/integracion-menuapp-checklist.md y core/PrecioHelper.php.
     * NULL = sin configurar: PrecioHelper cae al comportamiento histórico (Responsable
     * Inscripto = no incluyen; cualquier otra condición = incluyen).
     *
     * OJO: cambia el precio que efectivamente paga el cliente, en el local y en el menú
     * online. Quien la toque tiene que revisar sus precios después.
     */
    public function guardarPreciosIncluyenIva(?bool $incluyen): void
    {
        $valor = $incluyen === null ? null : (int)$incluyen;
        $this->query(
            "INSERT INTO configuracion_fiscal (id, precios_incluyen_iva) VALUES (1, :p)
             ON DUPLICATE KEY UPDATE precios_incluyen_iva = VALUES(precios_incluyen_iva)",
            [':p' => $valor]
        );
    }

    public function guardarCategoria(?string $categoria): void
    {
        $this->query(
            "INSERT INTO configuracion_fiscal (id, categoria_monotributo) VALUES (1, :cat)
             ON DUPLICATE KEY UPDATE categoria_monotributo = :cat2",
            [':cat' => $categoria, ':cat2' => $categoria]
        );
    }

    /** El admin puede bloquear globalmente "Facturar por AFIP" en el POS (ej. cerca del límite de su categoría) */
    public function guardarBloqueoFacturacionAfip(bool $bloqueado): void
    {
        $valor = (int) $bloqueado;
        $this->query(
            "INSERT INTO configuracion_fiscal (id, bloquear_facturacion_afip) VALUES (1, :b)
             ON DUPLICATE KEY UPDATE bloquear_facturacion_afip = :b2",
            [':b' => $valor, ':b2' => $valor]
        );
    }

    /** Override manual del límite de facturación (Monotributo). NULL = usar el límite anual teórico de la categoría tal cual. */
    public function guardarLimiteManual(?float $limite): void
    {
        $this->query(
            "INSERT INTO configuracion_fiscal (id, limite_facturacion_manual) VALUES (1, :l)
             ON DUPLICATE KEY UPDATE limite_facturacion_manual = :l2",
            [':l' => $limite, ':l2' => $limite]
        );
    }

    /**
     * Saldo a repartir día a día entre prorateo_desde y prorateo_hasta (POS): lo no
     * facturado un día se acumula para los siguientes. NULL en cualquiera de los 3
     * valores desactiva el prorrateo diario (el POS cae al indicador de 12 meses).
     */
    public function guardarProrateoDiario(?float $saldo, ?string $desde, ?string $hasta): void
    {
        $this->query(
            "INSERT INTO configuracion_fiscal (id, saldo_prorateo_diario, prorateo_desde, prorateo_hasta)
             VALUES (1, :s, :d, :h)
             ON DUPLICATE KEY UPDATE saldo_prorateo_diario = :s2, prorateo_desde = :d2, prorateo_hasta = :h2",
            [':s' => $saldo, ':d' => $desde, ':h' => $hasta, ':s2' => $saldo, ':d2' => $desde, ':h2' => $hasta]
        );
    }

    /** Puntos % de caída de margen que disparan el aviso push al comprar un ingrediente genérico. NULL = usar 5% por defecto. */
    public function guardarUmbralMargen(?float $umbral): void
    {
        $this->query(
            "INSERT INTO configuracion_fiscal (id, umbral_alerta_margen) VALUES (1, :u)
             ON DUPLICATE KEY UPDATE umbral_alerta_margen = :u2",
            [':u' => $umbral, ':u2' => $umbral]
        );
    }

    /** Límites vigentes por categoría (letra => monto anual) */
    public static function categorias(): array
    {
        return require BASE_PATH . '/config/monotributo_categorias.php';
    }
}
