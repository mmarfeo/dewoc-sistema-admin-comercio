<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/app/Models/WebauthnCredentialModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/vendor/autoload.php';

/**
 * Login sin contraseña vía WebAuthn (huella/Face ID/PIN del dispositivo, "passkeys").
 * La contraseña nunca se deshabilita: esto es un método alternativo de acceso, atado
 * 1 credencial -> 1 usuario, así que la trazabilidad en AuditLog no cambia.
 */
class WebauthnController extends Controller
{
    private UsuarioModel $usuarioModel;
    private WebauthnCredentialModel $credModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
        $this->credModel    = new WebauthnCredentialModel();
    }

    private function rpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return explode(':', $host)[0];
    }

    private function webAuthn(): \lbuchs\WebAuthn\WebAuthn
    {
        // $allowedFormats = null → acepta cualquier formato de atestación que use cada
        // dispositivo (packed, apple, android-safetynet, etc.) sin validar contra ninguna
        // root CA — no necesitamos atestación fuerte, solo que el próximo login use la
        // misma clave pública que se generó acá.
        return new \lbuchs\WebAuthn\WebAuthn(APP_NAME, $this->rpId());
    }

    // ─── Registro de un dispositivo (usuario ya logueado, opt-in desde su perfil) ──

    public function registroChallenge(): void
    {
        $this->requireAuth();
        $user = Session::user();

        $webAuthn = $this->webAuthn();
        // userId: bytes opacos únicos para el autenticador, sin PII (no email/nombre).
        // requireResidentKey=true: guarda una credencial descubrible/"passkey" en el
        // dispositivo — imprescindible para que loginChallenge() pueda ofrecer el login
        // "un toque" sin pedir email antes (allowCredentials vacío).
        $createArgs = $webAuthn->getCreateArgs((string)$user['id'], $user['email'], $user['nombre'], 60, true, true);

        $_SESSION['webauthn_challenge'] = $webAuthn->getChallenge()->getBinaryString();
        $this->json($createArgs);
    }

    public function registroVerificar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $user      = Session::user();
        $challenge = $_SESSION['webauthn_challenge'] ?? '';
        $body       = json_decode(file_get_contents('php://input'), false);
        $deviceName = trim((string)($body->deviceName ?? '')) ?: 'Dispositivo sin nombre';

        if (!$challenge || !$body || empty($body->clientDataJSON) || empty($body->attestationObject)) {
            $this->json(['ok' => false, 'error' => 'Solicitud de registro inválida o expirada.'], 400);
        }

        try {
            $webAuthn = $this->webAuthn();
            $data = $webAuthn->processCreate(
                base64_decode($body->clientDataJSON),
                base64_decode($body->attestationObject),
                $challenge,
                true,  // requireUserVerification: exige huella/Face ID/PIN, no solo "estar presente"
                true,  // requireUserPresent
                false  // failIfRootMismatch: no validamos cadena de certificados
            );

            $credentialIdB64 = base64_encode($data->credentialId);
            $this->credModel->crear((int)$user['id'], $credentialIdB64, $data->credentialPublicKey, $deviceName);

            unset($_SESSION['webauthn_challenge']);
            AuditLog::log('webauthn_registro', 'auth', "Dispositivo registrado: {$deviceName}");
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo registrar el dispositivo: ' . $e->getMessage()], 400);
        }
    }

    // ─── Login con el dispositivo ya registrado (sin sesión previa) ────────────────

    public function loginChallenge(): void
    {
        // Credencial descubrible (passkey): sin allowCredentials, el propio SO/navegador
        // busca en su almacenamiento cuál usar y ofrece directamente la huella/PIN, sin
        // pedir email antes — es el flujo "un toque" al estilo WhatsApp.
        $webAuthn = $this->webAuthn();
        $getArgs  = $webAuthn->getGetArgs([], 60, false, false, false, true, true, true);

        $_SESSION['webauthn_challenge'] = $webAuthn->getChallenge()->getBinaryString();
        $this->json($getArgs);
    }

    public function loginVerificar(): void
    {
        $challenge = $_SESSION['webauthn_challenge'] ?? '';
        $body      = json_decode(file_get_contents('php://input'), false);

        if (!$challenge || !$body || empty($body->id) || empty($body->clientDataJSON)) {
            $this->json(['ok' => false, 'error' => 'Solicitud de login inválida o expirada.'], 400);
        }

        $cred = $this->credModel->obtenerPorCredentialId($body->id);
        if (!$cred) {
            $this->json(['ok' => false, 'error' => 'Este dispositivo no está registrado.'], 401);
        }

        try {
            $webAuthn = $this->webAuthn();
            $webAuthn->processGet(
                base64_decode($body->clientDataJSON),
                base64_decode($body->authenticatorData),
                base64_decode($body->signature),
                $cred['public_key'],
                $challenge,
                (int)$cred['sign_count'],
                true, // requireUserVerification
                true  // requireUserPresent
            );
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'No se pudo verificar el dispositivo: ' . $e->getMessage()], 401);
        }

        unset($_SESSION['webauthn_challenge']);

        $usuario = $this->usuarioModel->findByIdConRol((int)$cred['usuario_id']);
        if (!$usuario || !$usuario['activo'] || $usuario['eliminado']) {
            $this->json(['ok' => false, 'error' => 'Cuenta inactiva. Contactá a un administrador.'], 403);
        }
        if ($this->usuarioModel->estaBloquado($usuario)) {
            $this->json(['ok' => false, 'error' => 'Cuenta bloqueada temporalmente. Probá con tu contraseña más tarde.'], 403);
        }

        $this->credModel->actualizarUso((int)$cred['id'], (int)$webAuthn->getSignatureCounter());

        // Mismo bloque de sesión que AuthController::login() — cualquier acción posterior
        // se audita igual, sin importar el método de autenticación usado.
        Session::regenerate();
        $permisos = $this->usuarioModel->getPermisosEfectivos((int)$usuario['id'], (int)$usuario['rol_id']);
        Session::set('auth_user', [
            'id'              => $usuario['id'],
            'nombre'          => $usuario['nombre'],
            'email'           => $usuario['email'],
            'rol'             => $usuario['rol'],
            'rol_id'          => $usuario['rol_id'],
            'foto'            => $usuario['foto'],
            'pantalla_inicio' => $usuario['pantalla_inicio'],
        ]);
        Session::set('permisos', $permisos);

        logApp("Login WebAuthn exitoso: {$usuario['email']} | rol: {$usuario['rol']}");
        AuditLog::log('login_webauthn', 'auth', "Dispositivo: " . ($cred['device_name'] ?: 'sin nombre'), (int)$usuario['id'], $usuario['nombre']);

        $this->json(['ok' => true, 'redirect' => url(homeUrl())]);
    }

    // ─── Gestión de dispositivos propios (perfil del usuario) ──────────────────────

    public function dispositivos(): void
    {
        $this->requireAuth();
        $usuarioId = (int)Session::user()['id'];
        $this->json(['ok' => true, 'dispositivos' => $this->credModel->listarPorUsuario($usuarioId)]);
    }

    public function eliminarDispositivo(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id        = (int)$this->input('id', 0);
        $usuarioId = (int)Session::user()['id'];
        $this->credModel->eliminar($id, $usuarioId);
        AuditLog::log('webauthn_eliminar_dispositivo', 'auth', "Dispositivo eliminado (id={$id})");
        $this->json(['ok' => true]);
    }
}
