<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Configuración de la integración con MenuApp (ver docs/integracion-menuapp-checklist.md).
 * Fila única (id = 1), mismo patrón que configuracion_fiscal.
 *
 * Del token Bearer se guarda SOLO el hash: el valor en claro se muestra una única vez,
 * al generarlo en la pantalla Integraciones → MenuApp, y después ya no se puede recuperar
 * (hay que regenerarlo).
 */
class IntegracionMenuAppModel extends Model
{
    protected string $table = 'integracion_menuapp';

    /** Cuántas requests por minuto acepta la API antes de responder 429. */
    const RATE_LIMITE_POR_MINUTO = 120;

    public function obtener(): array
    {
        return $this->queryOne("SELECT * FROM integracion_menuapp WHERE id = 1") ?: [
            'id' => 1, 'instalacion_id' => null, 'activa' => 0, 'token_hash' => null,
            'token_ultimos4' => null, 'token_generado_at' => null, 'webhook_url' => null,
            'webhook_secret' => null, 'menu_url' => null, 'producto_envio_id' => null,
            'usuario_id' => null, 'ultimo_acceso_at' => null, 'ultimo_error' => null,
        ];
    }

    /**
     * Identificador único y fijo de ESTA instalación de Dewoc (decisión D11, tarea 6.1).
     * Se genera una sola vez, al primer uso, y NO se regenera nunca: ni al regenerar el
     * token, ni al desactivar y volver a activar la integración. MenuApp lo usa para que
     * una misma instalación no termine conectada a dos comercios distintos — si cambiara,
     * MenuApp la vería como una instalación nueva y permitiría justamente eso.
     */
    public function instalacionId(): string
    {
        $actual = $this->obtener()['instalacion_id'] ?? null;
        if ($actual) return (string)$actual;

        $uuid = self::uuidV4();
        // WHERE instalacion_id IS NULL: si dos requests simultáneas llegan acá a la vez,
        // la segunda no pisa el UUID que acaba de escribir la primera.
        $this->query(
            "UPDATE integracion_menuapp SET instalacion_id = :u WHERE id = 1 AND instalacion_id IS NULL",
            [':u' => $uuid]
        );
        return (string)($this->obtener()['instalacion_id'] ?? $uuid);
    }

    /** UUID v4 con bytes criptográficamente aleatorios. */
    private static function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); // versión 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Guarda cualquier subconjunto de columnas de la fila única. */
    public function guardar(array $data): void
    {
        if (!$data) return;

        $cols   = array_keys($data);
        $insCol = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
        $insVal = implode(', ', array_map(fn($c) => ":{$c}", $cols));

        // El UPDATE usa VALUES() en vez de repetir los placeholders: con la emulación de
        // prepared statements desactivada, PDO no permite reusar el mismo named placeholder
        // dos veces en la misma consulta.
        $sets = implode(', ', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $cols));

        $this->query(
            "INSERT INTO integracion_menuapp (`id`, {$insCol}) VALUES (1, {$insVal})
             ON DUPLICATE KEY UPDATE {$sets}",
            array_combine(array_map(fn($c) => ":{$c}", $cols), array_values($data))
        );
    }

    // ── Token ────────────────────────────────────────────────────────────────

    /**
     * Genera un token nuevo, guarda su hash y devuelve el valor EN CLARO — que es la única
     * vez que existe fuera del hash. Regenerar invalida el anterior de inmediato.
     */
    public function generarToken(): string
    {
        $token = 'dwc_' . randomToken(24);
        $this->guardar([
            'token_hash'        => hash('sha256', $token),
            'token_ultimos4'    => substr($token, -4),
            'token_generado_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /**
     * Valida un token Bearer contra el hash guardado. Devuelve la configuración si es
     * válido y la integración está activa; null en cualquier otro caso (sin distinguir
     * "token incorrecto" de "integración apagada": las dos responden 401).
     */
    public function validarToken(string $token): ?array
    {
        $config = $this->obtener();
        if (empty($config['activa']) || empty($config['token_hash'])) return null;
        if ($token === '') return null;

        // hash_equals: comparación en tiempo constante, para no filtrar el hash por timing.
        if (!hash_equals((string)$config['token_hash'], hash('sha256', $token))) return null;

        return $config;
    }

    public function registrarAcceso(): void
    {
        $this->query("UPDATE integracion_menuapp SET ultimo_acceso_at = NOW() WHERE id = 1");
    }

    public function registrarError(string $error): void
    {
        $this->query(
            "UPDATE integracion_menuapp SET ultimo_error = :e WHERE id = 1",
            [':e' => date('Y-m-d H:i:s') . ' — ' . mb_substr($error, 0, 2000)]
        );
    }

    public function limpiarError(): void
    {
        $this->query("UPDATE integracion_menuapp SET ultimo_error = NULL WHERE id = 1");
    }

    // ── Rate limit ───────────────────────────────────────────────────────────

    /**
     * Contador simple por ventana de un minuto (tarea 3: "120 requests por minuto por
     * token, como mínimo con un contador simple en la base de datos"). Devuelve false si
     * se pasó del límite. De paso borra las ventanas viejas, así la tabla no crece.
     */
    public function consumirRateLimit(): bool
    {
        $ventana = date('Y-m-d H:i');

        $this->query("DELETE FROM integracion_menuapp_rate WHERE ventana < :v", [':v' => $ventana]);
        $this->query(
            "INSERT INTO integracion_menuapp_rate (ventana, requests) VALUES (:v, 1)
             ON DUPLICATE KEY UPDATE requests = requests + 1",
            [':v' => $ventana]
        );

        $usadas = (int)$this->queryScalar(
            "SELECT requests FROM integracion_menuapp_rate WHERE ventana = :v",
            [':v' => $ventana]
        );
        return $usadas <= self::RATE_LIMITE_POR_MINUTO;
    }

    // ── Usuario técnico ──────────────────────────────────────────────────────

    /**
     * Usuario "MenuApp (sistema)" con el que se registran las ventas que llegan por la
     * API (ventas.usuario_id es obligatorio). Se crea al activar la integración y queda
     * sin posibilidad de iniciar sesión: activo = 0 y un password_hash que no corresponde
     * a ninguna contraseña (AuthController exige activo = 1, así que nunca entra).
     */
    public function asegurarUsuarioTecnico(): int
    {
        $config = $this->obtener();
        if (!empty($config['usuario_id'])) {
            $existe = $this->queryScalar(
                "SELECT id FROM usuarios WHERE id = :id AND eliminado = 0",
                [':id' => (int)$config['usuario_id']]
            );
            if ($existe) return (int)$existe;
        }

        $email = 'menuapp@sistema.local';
        $yaEsta = $this->queryScalar("SELECT id FROM usuarios WHERE email = :e", [':e' => $email]);
        if ($yaEsta) {
            $this->guardar(['usuario_id' => (int)$yaEsta]);
            return (int)$yaEsta;
        }

        $id = (int)$this->query(
            "INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, eliminado)
             VALUES ('MenuApp (sistema)', :e, :p, 2, 0, 0)",
            [':e' => $email, ':p' => password_hash(randomToken(32), PASSWORD_DEFAULT)]
        ) ? (int)Database::getInstance()->lastInsertId() : 0;

        if ($id) $this->guardar(['usuario_id' => $id]);
        return $id;
    }
}
