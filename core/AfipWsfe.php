<?php
/**
 * AfipWsfe — Web Service de Facturación Electrónica v1 (AFIP)
 * Solicita el CAE para una venta marcada como "Facturar por AFIP".
 * Requiere AfipWsaa, SoapClient y las constantes AFIP_* en config.php.
 */
class AfipWsfe
{
    private const WSDL_HOMO = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    private const WSDL_PROD = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';

    /**
     * Solicita el CAE para una venta.
     *
     * @param array $venta   Datos de la venta (total_con_iva, subtotal, total_iva, etc.)
     * @param array $detalle Ítems de la venta (para calcular alícuotas IVA)
     * @return array [ok, resultado, cae, cae_vto, nro_cbte, tipo_cbte, pto_vta, obs]
     */
    public static function solicitarCae(array $venta, array $detalle): array
    {
        require_once BASE_PATH . '/core/AfipWsaa.php';

        if (!AFIP_CUIT) {
            throw new \RuntimeException('AFIP_CUIT no configurado en .env');
        }

        $ta   = AfipWsaa::getTicket();
        $auth = ['Token' => $ta['token'], 'Sign' => $ta['sign'], 'Cuit' => AFIP_CUIT];

        $client    = self::getClient();
        $tipoCbte  = AFIP_TIPO_CBTE;
        $ptoVta    = AFIP_PTO_VENTA;

        // Último número autorizado → próximo
        $lastRes = $client->FECompUltimoAutorizado([
            'Auth' => $auth, 'PtoVta' => $ptoVta, 'CbteTipo' => $tipoCbte,
        ]);
        $lastNro = (int)$lastRes->FECompUltimoAutorizadoResult->CbteNro;
        $nextNro = $lastNro + 1;

        // IVA por alícuota y neto base imponible
        [$impNeto, $alicIva] = self::calcularIva($detalle, (float)($venta['descuento_global'] ?? 0));

        // Datos del receptor
        [$docTipo, $docNro] = self::resolverDoc($venta);

        $detalleReq = [
            'Concepto'   => 1,           // 1=Productos, 2=Servicios, 3=Mixto
            'DocTipo'    => $docTipo,
            'DocNro'     => $docNro,
            'CbteDesde'  => $nextNro,
            'CbteHasta'  => $nextNro,
            'CbteFch'    => date('Ymd'),
            'ImpTotal'   => round((float)$venta['total_con_iva'], 2),
            'ImpTotConc' => 0,
            'ImpNeto'    => $impNeto,
            'ImpOpEx'    => 0,
            'ImpIVA'     => round((float)$venta['total_iva'], 2),
            'ImpTrib'    => 0,
            'MonId'      => 'PES',
            'MonCotiz'   => 1,
        ];
        if ($alicIva) {
            $detalleReq['Iva'] = ['AlicIva' => $alicIva];
        }

        $response = $client->FECAESolicitar([
            'Auth' => $auth,
            'FeCAEReq' => [
                'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $ptoVta, 'CbteTipo' => $tipoCbte],
                'FeDetReq' => ['FECAEDetRequest' => $detalleReq],
            ],
        ]);

        $res = $response->FECAESolicitarResult;
        $det = $res->FeDetResp->FECAEDetResponse;

        $resultado = (string)$det->Resultado;
        $cae       = ($resultado === 'A') ? (string)$det->CAE : '';
        $caeVto    = ($resultado === 'A')
            ? date('Y-m-d', strtotime((string)$det->CAEFchVto))
            : null;

        $obs = self::extractMessages($det->Observaciones->Obs ?? null);
        $err = self::extractMessages($res->Errors->Err ?? null);

        return [
            'ok'        => $resultado === 'A',
            'resultado' => $resultado,
            'cae'       => $cae,
            'cae_vto'   => $caeVto,
            'nro_cbte'  => $nextNro,
            'tipo_cbte' => $tipoCbte,
            'pto_vta'   => $ptoVta,
            'obs'       => $obs ?: $err,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private static function getClient(): \SoapClient
    {
        $wsdl = AFIP_MODO === 'prod' ? self::WSDL_PROD : self::WSDL_HOMO;
        return new \SoapClient($wsdl, [
            'trace'          => false,
            'exceptions'     => true,
            'stream_context' => stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]),
        ]);
    }

    /**
     * Agrupa ítems por alícuota IVA y calcula base imponible + importe IVA.
     * Retorna [impNeto, alicIva array].
     */
    private static function calcularIva(array $detalle, float $descGlobal): array
    {
        $factor = $descGlobal > 0 ? (1 - $descGlobal / 100) : 1.0;
        $groups = [];

        foreach ($detalle as $d) {
            $ivaPct = (float)($d['iva_porcentaje'] ?? 21);
            $neto   = round((float)$d['subtotal'] * $factor, 4);

            // AFIP: Id 5=21%, Id 4=10.5%, Id 3=0%, Id 9=2.5%, Id 8=5%
            $id = match(true) {
                $ivaPct >= 20.5 => 5,
                $ivaPct >= 10.0 => 4,
                $ivaPct >= 4.5  => 9,
                $ivaPct >= 2.0  => 8,
                default         => 3,
            };

            if (!isset($groups[$id])) {
                $groups[$id] = ['Id' => $id, 'BaseImp' => 0.0, 'Importe' => 0.0];
            }
            $groups[$id]['BaseImp'] += $neto;
            $groups[$id]['Importe'] += round($neto * $ivaPct / 100, 4);
        }

        $impNeto = 0.0;
        $alicIva = [];
        foreach ($groups as $g) {
            $alicIva[] = [
                'Id'      => $g['Id'],
                'BaseImp' => round($g['BaseImp'], 2),
                'Importe' => round($g['Importe'], 2),
            ];
            $impNeto += $g['BaseImp'];
        }

        return [round($impNeto, 2), $alicIva];
    }

    /** Determina DocTipo/DocNro del receptor a partir de los datos de la venta */
    private static function resolverDoc(array $venta): array
    {
        $doc = preg_replace('/\D/', '', $venta['cliente_doc'] ?? '');
        if ($doc && strlen($doc) === 11) return [80, $doc]; // CUIT
        if ($doc && strlen($doc) >= 7)   return [96, $doc]; // DNI
        return [99, 0]; // Consumidor final
    }

    /** Convierte Observaciones/Errores SOAP a string */
    private static function extractMessages(mixed $items): string
    {
        if (!$items) return '';
        $items = is_array($items) ? $items : [$items];
        return implode(' | ', array_map(
            fn($o) => (is_object($o) ? $o->Code . ': ' . $o->Msg : (string)$o),
            $items
        ));
    }
}
