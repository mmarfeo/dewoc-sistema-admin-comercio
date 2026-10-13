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
        $ptoVta    = AFIP_PTO_VENTA;

        // Datos del receptor
        [$docTipo, $docNro] = self::resolverDoc($venta);

        // Tipo de comprobante (A/B/C) según la condición IVA del negocio (emisor)
        // y del cliente (receptor). Una Factura A exige que el receptor tenga CUIT
        // válido (DocTipo 80); si no lo tiene, se emite B en su lugar.
        $tipoCbte = self::tipoComprobante($venta['cliente_civa'] ?? 'consumidor_final');
        if ($tipoCbte === 1 && $docTipo !== 80) {
            $tipoCbte = 6;
        }

        // Último número autorizado → próximo
        $lastNro = self::ultimoAutorizado($ptoVta, $tipoCbte, $client, $auth);
        $nextNro = $lastNro + 1;

        $impTotal = round((float)$venta['total_con_iva'], 2);

        $detalleReq = [
            'Concepto'   => 1,           // 1=Productos, 2=Servicios, 3=Mixto
            'DocTipo'    => $docTipo,
            'DocNro'     => $docNro,
            'CbteDesde'  => $nextNro,
            'CbteHasta'  => $nextNro,
            'CbteFch'    => date('Ymd'),
            'ImpTotal'   => $impTotal,
            'ImpTotConc' => 0,
            'ImpOpEx'    => 0,
            'ImpTrib'    => 0,
            'MonId'      => 'PES',
            'MonCotiz'   => 1,
        ];

        if ($tipoCbte === 11) {
            // Factura C (monotributo): no se discrimina IVA — todo el total es "neto"
            // y el objeto Iva no debe informarse (si no, AFIP rechaza con 10047/10048/10071).
            $detalleReq['ImpNeto'] = $impTotal;
            $detalleReq['ImpIVA']  = 0;
        } else {
            // IVA por alícuota y neto base imponible
            [$impNeto, $alicIva] = self::calcularIva($detalle, (float)($venta['descuento_global'] ?? 0));
            $detalleReq['ImpNeto'] = $impNeto;
            $detalleReq['ImpIVA']  = round((float)$venta['total_iva'], 2);
            if ($alicIva) {
                $detalleReq['Iva'] = ['AlicIva' => $alicIva];
            }
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

    /** Último número de comprobante autorizado para un Punto de Venta + Tipo de comprobante */
    public static function ultimoAutorizado(int $ptoVta, int $tipoCbte, ?\SoapClient $client = null, ?array $auth = null): int
    {
        if (!$client || !$auth) {
            require_once BASE_PATH . '/core/AfipWsaa.php';
            $ta     = AfipWsaa::getTicket();
            $auth   = ['Token' => $ta['token'], 'Sign' => $ta['sign'], 'Cuit' => AFIP_CUIT];
            $client = self::getClient();
        }

        $res = $client->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => $ptoVta, 'CbteTipo' => $tipoCbte]);
        return (int)$res->FECompUltimoAutorizadoResult->CbteNro;
    }

    /**
     * Consulta un comprobante ya emitido directamente contra el sistema núcleo de AFIP (WSFE),
     * para confirmar que realmente existe ahí — no depende de reportes/consultas secundarias
     * de ARCA (que suelen tardar en sincronizar los comprobantes emitidos por webservice).
     */
    public static function consultarComprobante(int $ptoVta, int $tipoCbte, int $nroCbte, ?\SoapClient $client = null, ?array $auth = null): array
    {
        if (!AFIP_CUIT) {
            throw new \RuntimeException('AFIP_CUIT no configurado en .env');
        }

        if (!$client || !$auth) {
            require_once BASE_PATH . '/core/AfipWsaa.php';
            $ta     = AfipWsaa::getTicket();
            $auth   = ['Token' => $ta['token'], 'Sign' => $ta['sign'], 'Cuit' => AFIP_CUIT];
            $client = self::getClient();
        }

        $response = $client->FECompConsultar([
            'Auth' => $auth,
            'FeCompConsReq' => ['CbteTipo' => $tipoCbte, 'CbteNro' => $nroCbte, 'PtoVta' => $ptoVta],
        ]);

        $res = $response->FECompConsultarResult;
        $err = self::extractMessages($res->Errors->Err ?? null);

        if (!empty($err)) {
            return ['ok' => false, 'encontrado' => false, 'error' => $err];
        }

        $c = $res->ResultGet;
        return [
            'ok'         => true,
            'encontrado' => true,
            'cae'        => (string)($c->CodAutorizacion ?? ''),
            'cae_vto'    => isset($c->FchVto) ? date('Y-m-d', strtotime((string)$c->FchVto)) : null,
            'resultado'  => (string)($c->Resultado ?? ''),
            'imp_total'  => (float)($c->ImpTotal ?? 0),
            'fecha_cbte' => isset($c->CbteFch) ? date('Y-m-d', strtotime((string)$c->CbteFch)) : null,
        ];
    }

    /**
     * URL del QR obligatorio en comprobantes electrónicos (RG 4892/2020).
     * Formato oficial: https://www.afip.gob.ar/fe/qr/?p=<JSON codificado en base64>
     */
    public static function qrUrl(array $venta): string
    {
        [$docTipo, $docNro] = self::resolverDoc($venta);

        $datos = [
            'ver'        => 1,
            'fecha'      => date('Y-m-d', strtotime($venta['created_at'] ?? 'now')),
            'cuit'       => (int) AFIP_CUIT,
            'ptoVta'     => (int) $venta['afip_pto_vta'],
            'tipoCmp'    => (int) $venta['afip_tipo_cbte'],
            'nroCmp'     => (int) $venta['afip_nro_cbte'],
            'importe'    => round((float) $venta['total_con_iva'], 2),
            'moneda'     => 'PES',
            'ctz'        => 1,
            'tipoDocRec' => $docTipo,
            'nroDocRec'  => (int) $docNro,
            'tipoCodAut' => 'E',
            'codAut'     => (int) $venta['afip_cae'],
        ];

        return 'https://www.afip.gob.ar/fe/qr/?p=' . base64_encode(json_encode($datos));
    }

    /**
     * PNG del QR de ARCA/AFIP generado del lado del servidor, devuelto como data URI
     * listo para un <img src="...">. Se resuelve así (en vez de dibujarlo en el
     * navegador con una librería de un CDN externo) porque el script del CDN puede
     * ser bloqueado por el Content-Security-Policy o por el bloqueo de rastreo de
     * terceros de navegadores como Edge/Safari — eso dejaba el ticket sin QR de forma
     * intermitente. Al generarse en el servidor, el QR ya viaja embebido en el HTML.
     * Se cachea en disco por CAE (inmutable una vez emitido el comprobante) para no
     * llamar a la API externa en cada reimpresión.
     */
    public static function qrImageDataUri(array $venta): ?string
    {
        $cae = preg_replace('/\D/', '', $venta['afip_cae'] ?? '');
        if (!$cae) return null;

        $file = BASE_PATH . '/storage/afip/qr_cache/' . $cae . '.png';

        if (is_file($file)) {
            $png = file_get_contents($file);
        } else {
            $api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data='
                . urlencode(self::qrUrl($venta));

            $ch = curl_init($api);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $png    = curl_exec($ch);
            $httpOk = $png !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
            curl_close($ch);

            if (!$httpOk) return null;

            $dir = dirname($file);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($file, $png, LOCK_EX);
        }

        return 'data:image/png;base64,' . base64_encode($png);
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

    /**
     * Determina el tipo de comprobante AFIP (1=A, 6=B, 11=C) según la condición
     * IVA del negocio (emisor, NEGOCIO_CONDICION_IVA) y del cliente (receptor):
     * - Negocio Monotributista o Exento → siempre Factura C, sin importar el cliente.
     * - Negocio Responsable Inscripto → Factura A si el cliente también es
     *   Responsable Inscripto, Factura B para el resto (consumidor final,
     *   monotributista, exento).
     */
    public static function tipoComprobante(string $clienteCondicionIva): int
    {
        if (NEGOCIO_CONDICION_IVA !== 'responsable_inscripto') {
            return 11;
        }
        return $clienteCondicionIva === 'responsable_inscripto' ? 1 : 6;
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
