<?php
/**
 * Envío de notificaciones Web Push (RFC 8291 — aes128gcm, RFC 8292 — VAPID)
 * implementado con la extensión openssl de PHP, sin dependencias externas
 * (el proyecto no usa Composer).
 */
class WebPush
{
    /** Genera un par de claves VAPID en base64url crudo (P-256) */
    public static function generarClavesVapid(): array
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$key) {
            throw new \RuntimeException('No se pudo generar el par de claves VAPID: ' . openssl_error_string());
        }
        $details = openssl_pkey_get_details($key);
        $pub  = "\x04" . self::pad32($details['ec']['x']) . self::pad32($details['ec']['y']);
        $priv = self::pad32($details['ec']['d']);

        return [
            'publicKey'  => self::b64u($pub),
            'privateKey' => self::b64u($priv),
        ];
    }

    /**
     * Envía una notificación push a una suscripción del navegador.
     * @param array $subscription ['endpoint'=>string,'p256dh'=>string,'auth'=>string] (p256dh/auth en base64url, tal como los entrega la Push API)
     * @return array ['ok'=>bool,'status'=>int,'body'=>?string,'error'=>?string]
     */
    public static function enviar(
        array $subscription,
        string $payload,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
        int $ttl = 2419200
    ): array {
        $uaPublic   = self::b64ud($subscription['p256dh']);
        $authSecret = self::b64ud($subscription['auth']);

        [$body, $contentEncoding] = self::cifrarPayload($payload, $uaPublic, $authSecret);

        $endpoint = $subscription['endpoint'];
        $aud      = self::origen($endpoint);
        $jwt      = self::firmarJwtVapid($aud, $vapidSubject, $vapidPrivateKey);

        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidPublicKey,
            'Content-Encoding: ' . $contentEncoding,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($body),
            'TTL: ' . $ttl,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $respBody = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch) ?: null;
        curl_close($ch);

        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => $respBody === false ? null : $respBody,
            'error'  => $error,
        ];
    }

    // ── Cifrado aes128gcm (RFC 8291) ────────────────────────────────────────
    private static function cifrarPayload(string $payload, string $uaPublic, string $authSecret): array
    {
        $eph        = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ephDetails = openssl_pkey_get_details($eph);
        $asPublic   = "\x04" . self::pad32($ephDetails['ec']['x']) . self::pad32($ephDetails['ec']['y']);
        $ephPriv    = self::cargarClavePrivadaEc(self::pad32($ephDetails['ec']['d']));

        $uaPublicKey = self::cargarClavePublicaEc($uaPublic);

        $sharedSecret = openssl_pkey_derive($uaPublicKey, $ephPriv, 32);
        if ($sharedSecret === false) {
            throw new \RuntimeException('ECDH falló al cifrar el push: ' . openssl_error_string());
        }

        $salt = random_bytes(16);

        $context = "WebPush: info\x00" . $uaPublic . $asPublic;
        $ikm     = hash_hkdf('sha256', $sharedSecret, 32, $context, $authSecret);

        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // 0x02 = delimitador de "último registro" (RFC 8188); el mensaje entra en un único registro.
        $plaintext = $payload . "\x02";
        $tag       = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Cifrado AES-GCM falló: ' . openssl_error_string());
        }

        $recordSize = pack('N', 4096);
        $keyIdLen   = chr(strlen($asPublic));
        $header     = $salt . $recordSize . $keyIdLen . $asPublic;

        return [$header . $ciphertext . $tag, 'aes128gcm'];
    }

    // ── VAPID JWT (RFC 8292, ES256) ──────────────────────────────────────────
    private static function firmarJwtVapid(string $aud, string $subject, string $vapidPrivateKey): string
    {
        $header = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64u(json_encode([
            'aud' => $aud,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ]));
        $signingInput = $header . '.' . $claims;

        $privKey = self::cargarClavePrivadaEc(self::b64ud($vapidPrivateKey));

        $derSig = '';
        if (!openssl_sign($signingInput, $derSig, $privKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Firma VAPID falló: ' . openssl_error_string());
        }

        return $signingInput . '.' . self::b64u(self::derAsRaw($derSig));
    }

    // ── Helpers EC / ASN.1 ────────────────────────────────────────────────────

    /** Carga un escalar privado EC P-256 crudo (32 bytes) como recurso OpenSSL (openssl deriva el punto público solo) */
    private static function cargarClavePrivadaEc(string $d): \OpenSSLAsymmetricKey
    {
        $oidP256 = hex2bin('06082a8648ce3d030107');
        $params  = hex2bin('a00a') . $oidP256;
        $privOct = hex2bin('0420') . $d;
        $version = hex2bin('020101');
        $body    = $version . $privOct . $params;
        $seq     = hex2bin('30') . chr(strlen($body)) . $body;
        $pem     = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($seq), 64) . "-----END EC PRIVATE KEY-----\n";

        $k = openssl_pkey_get_private($pem);
        if (!$k) {
            throw new \RuntimeException('No se pudo cargar la clave privada EC: ' . openssl_error_string());
        }
        return $k;
    }

    /** Carga un punto público EC P-256 sin comprimir (65 bytes: 0x04||X||Y) como recurso OpenSSL */
    private static function cargarClavePublicaEc(string $point65): \OpenSSLAsymmetricKey
    {
        $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d0301070342');
        $der    = $prefix . "\x00" . $point65;
        $pem    = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END PUBLIC KEY-----\n";

        $k = openssl_pkey_get_public($pem);
        if (!$k) {
            throw new \RuntimeException('No se pudo cargar la clave pública EC: ' . openssl_error_string());
        }
        return $k;
    }

    /** DER ASN.1 SEQUENCE{INTEGER r, INTEGER s} (firma ECDSA) → r(32)||s(32) para JWT ES256 */
    private static function derAsRaw(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('Firma DER inválida (SEQUENCE)');
        }
        $len = ord($der[$offset++]);
        if ($len & 0x80) {
            $offset += $len & 0x7f; // longitud larga (no esperada para una firma ES256, 70-72 bytes)
        }
        $readInt = static function () use ($der, &$offset): string {
            if (ord($der[$offset++]) !== 0x02) {
                throw new \RuntimeException('Firma DER inválida (INTEGER)');
            }
            $l = ord($der[$offset++]);
            $bytes = substr($der, $offset, $l);
            $offset += $l;
            return str_pad(ltrim($bytes, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };
        return $readInt() . $readInt();
    }

    private static function pad32(string $b): string
    {
        return str_pad($b, 32, "\x00", STR_PAD_LEFT);
    }

    private static function b64u(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64ud(string $d): string
    {
        return base64_decode(strtr($d, '-_', '+/'));
    }

    private static function origen(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    }
}
