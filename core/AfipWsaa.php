<?php
/**
 * AfipWsaa — Web Service de Autenticación y Autorización (AFIP)
 * Obtiene y cachea el Ticket de Acceso (TA) para los WS de AFIP.
 * Requiere: AFIP_CERT, AFIP_KEY, AFIP_KEY_PASS, AFIP_MODO en config.php.
 */
class AfipWsaa
{
    private const WSDL_HOMO = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL';
    private const WSDL_PROD = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL';

    private static string $cacheFile = '';

    /** Devuelve ['token' => ..., 'sign' => ...] — refresca si el TA está por vencer */
    public static function getTicket(): array
    {
        $cached = self::loadCache();
        if ($cached && strtotime($cached['expires_at']) > time() + 600) {
            return ['token' => $cached['token'], 'sign' => $cached['sign']];
        }
        return self::requestNewTicket();
    }

    private static function requestNewTicket(): array
    {
        $tra = self::buildTRA();
        $cms = self::signTRA($tra);
        $ta  = self::loginCms($cms);

        $xml   = simplexml_load_string($ta);
        $token = (string)$xml->credentials->token;
        $sign  = (string)$xml->credentials->sign;
        $exp   = (string)$xml->header->expirationTime;

        self::saveCache(['token' => $token, 'sign' => $sign, 'expires_at' => $exp]);
        return ['token' => $token, 'sign' => $sign];
    }

    private static function buildTRA(): string
    {
        $tz  = new DateTimeZone('America/Argentina/Buenos_Aires');
        $now = new DateTime('now', $tz);
        $exp = (clone $now)->modify('+12 hours');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0">'
            . '<header>'
            . '<uniqueId>' . time() . '</uniqueId>'
            . '<generationTime>' . $now->format('Y-m-d\TH:i:sP') . '</generationTime>'
            . '<expirationTime>' . $exp->format('Y-m-d\TH:i:sP') . '</expirationTime>'
            . '</header>'
            . '<service>wsfe</service>'
            . '</loginTicketRequest>';
    }

    private static function signTRA(string $tra): string
    {
        $certPath = AFIP_CERT;
        $keyPath  = AFIP_KEY;

        if (!is_file($certPath) || !is_file($keyPath)) {
            throw new \RuntimeException(
                'Certificado AFIP no encontrado. Configure AFIP_CERT y AFIP_KEY en .env. '
                . "Cert: $certPath"
            );
        }

        $traFile = tempnam(sys_get_temp_dir(), 'afip_tra_');
        $cmsFile = tempnam(sys_get_temp_dir(), 'afip_cms_');

        try {
            file_put_contents($traFile, $tra);

            $cert = file_get_contents($certPath);
            $pass = AFIP_KEY_PASS;
            $key  = AFIP_KEY_PASS
                ? [file_get_contents($keyPath), $pass]
                : file_get_contents($keyPath);

            $ok = openssl_pkcs7_sign(
                $traFile, $cmsFile,
                $cert,
                $key,
                [],
                PKCS7_NOOLDMIMETYPE  // attached signature — no PKCS7_DETACHED
            );

            if (!$ok) {
                $err = openssl_error_string();
                throw new \RuntimeException("Error firmando TRA AFIP: $err");
            }

            // MIME output: headers + blank line + base64 CMS
            $mime = file_get_contents($cmsFile);
            $parts = explode("\n\n", $mime, 2);
            $b64   = trim($parts[1] ?? $mime);
            return str_replace(["\r\n", "\n", "\r"], '', $b64);
        } finally {
            @unlink($traFile);
            @unlink($cmsFile);
        }
    }

    private static function loginCms(string $cms): string
    {
        $wsdl = AFIP_MODO === 'prod' ? self::WSDL_PROD : self::WSDL_HOMO;

        $client = new \SoapClient($wsdl, [
            'trace'          => false,
            'exceptions'     => true,
            'stream_context' => stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]),
        ]);

        $result = $client->loginCms(['in0' => $cms]);
        return $result->loginCmsReturn;
    }

    // ── Cache helpers ──────────────────────────────────────────────────────────

    private static function getCacheFile(): string
    {
        if (!self::$cacheFile) {
            self::$cacheFile = BASE_PATH . '/storage/afip/ta_cache_' . AFIP_MODO . '.json';
        }
        return self::$cacheFile;
    }

    private static function loadCache(): ?array
    {
        $f = self::getCacheFile();
        if (!is_file($f)) return null;
        $data = json_decode(file_get_contents($f), true);
        return is_array($data) ? $data : null;
    }

    private static function saveCache(array $data): void
    {
        $dir = dirname(self::getCacheFile());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::getCacheFile(), json_encode($data), LOCK_EX);
    }
}
