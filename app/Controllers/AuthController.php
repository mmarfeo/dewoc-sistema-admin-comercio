<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionSeguridadModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/core/RememberMe.php';
require_once BASE_PATH . '/core/Mailer.php';

class AuthController extends Controller
{
    private UsuarioModel $model;

    public function __construct()
    {
        $this->model = new UsuarioModel();
    }

    public function loginForm(): void
    {
        if (Session::isLoggedIn()) {
            redirect(homeUrl());
        }
        // Auto-login si existe cookie "Recordarme" válida
        if (RememberMe::check($this->model)) {
            redirect(homeUrl());
        }
        $this->view('auth.login', [
            'pageTitle'          => 'Iniciar sesión',
            'error'              => Session::flash('login_error'),
            'webauthnHabilitado' => (bool)(new ConfiguracionSeguridadModel())->obtener()['webauthn_habilitado'],
        ]);
    }

    public function login(): void
    {
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX

        $email    = $this->input('email', '');
        $password = $_POST['password'] ?? '';

        $resultado = $this->validarCredenciales($email, $password);
        if (!$resultado['ok']) {
            $tipo = str_contains($resultado['error'], 'bloqueada') ? 'warning' : 'danger';
            Session::flash('login_error', ['tipo' => $tipo, 'texto' => $resultado['error']]);
            redirect('auth/login');
        }

        $usuario = $resultado['usuario'];
        $this->iniciarSesion($usuario, 'login');

        // Cookie "Recordarme" (30 días)
        if (!empty($_POST['recordarme'])) {
            RememberMe::set($this->model, (int)$usuario['id']);
        }

        redirect(homeUrl());
    }

    /**
     * Reconexión sin perder lo que el usuario estaba haciendo: cuando la sesión expira a
     * mitad de una acción (ej. una venta en el POS), Controller::requireAuth() responde
     * con {error:'sesion_expirada'} en vez de redirigir — el wrapper de fetch() en
     * assets/js/app.js detecta eso y muestra un modal con usuario/contraseña en vez de
     * mandar al login. Al confirmar acá, se restablece la sesión en el mismo request AJAX,
     * sin recargar la página, así el estado que tenía el usuario en el navegador (carrito,
     * formulario a medio llenar) no se pierde. Reusa la misma validación que login(), pero
     * responde JSON en vez de redirigir/flashear.
     */
    public function reconectar(): void
    {
        Csrf::check(); // JSON — el frontend usa el csrf_token fresco que ya le mandó el 401 de requireAuth()

        $email    = $this->input('email', '');
        $password = $_POST['password'] ?? '';

        $resultado = $this->validarCredenciales($email, $password);
        if (!$resultado['ok']) {
            $this->json(['ok' => false, 'error' => $resultado['error']], 401);
        }

        $this->iniciarSesion($resultado['usuario'], 'reconexion');
        $this->json(['ok' => true, 'mensaje' => 'Sesión renovada.', 'csrf_token' => Csrf::token()]);
    }

    /** Valida usuario/contraseña sin tocar sesión ni responder nada — compartido por login() y reconectar(). */
    private function validarCredenciales(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return ['ok' => false, 'error' => 'Completá todos los campos.'];
        }

        $usuario = $this->model->findByEmail($email);

        if (!$usuario || !$usuario['activo']) {
            return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
        }

        if ($this->model->estaBloquado($usuario)) {
            return ['ok' => false, 'error' => 'Cuenta bloqueada temporalmente. Intentá en ' . LOGIN_LOCKOUT_MINUTES . ' minutos.'];
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            $this->model->registrarIntentoFallido($usuario['id']);
            logApp("Login fallido para {$email}", 'WARN');
            AuditLog::log('login_fail', 'auth', "Email: {$email}", (int)$usuario['id'], $usuario['nombre']);
            return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
        }

        return ['ok' => true, 'usuario' => $usuario];
    }

    /** Arma la sesión autenticada (login nuevo o reconexión) — extraído para no duplicarlo. */
    private function iniciarSesion(array $usuario, string $accion): void
    {
        $this->model->resetIntentos($usuario['id']);
        Session::regenerate();

        $permisos = $this->model->getPermisosEfectivos((int)$usuario['id'], (int)$usuario['rol_id']);

        Session::set('auth_user', [
            'id'              => $usuario['id'],
            'nombre'          => $usuario['nombre'],
            'email'           => $usuario['email'],
            'rol'             => $usuario['rol'],
            'rol_id'          => $usuario['rol_id'],
            'foto'            => $usuario['foto'],
            'pantalla_inicio' => $usuario['pantalla_inicio'],
            've_tareas_generales' => (bool)($usuario['ve_tareas_generales'] ?? false),
        ]);
        Session::set('permisos', $permisos);

        logApp("Login exitoso: {$usuario['email']} | rol: {$usuario['rol']}" . ($accion === 'reconexion' ? ' (reconexión)' : ''));
        AuditLog::log($accion, 'auth', "Rol: {$usuario['rol']}", (int)$usuario['id'], $usuario['nombre']);
    }

    public function logout(): void
    {
        $user  = Session::user();
        $email = $user['email'] ?? 'desconocido';
        AuditLog::log('logout', 'auth', "Email: {$email}", (int)($user['id'] ?? 0), $user['nombre'] ?? null);
        logApp("Logout: {$email}");

        // Borrar cookie y token "Recordarme"
        RememberMe::clear($this->model);

        Session::destroy();
        redirect('auth/login');
    }

    /** Formulario "Olvidé mi contraseña" */
    public function recuperarForm(): void
    {
        if (Session::isLoggedIn()) redirect(homeUrl());
        $this->view('auth.recuperar', [
            'pageTitle' => 'Recuperar contraseña',
            'msg'       => Session::flash('recuperar_msg'),
        ]);
    }

    /** POST: envía el email de restablecimiento */
    public function recuperar(): void
    {
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX

        $email   = trim($this->input('email', ''));
        $usuario = $email ? $this->model->findByEmail($email) : false;

        // Siempre mostrar el mismo mensaje para no revelar si el email existe
        Session::flash('recuperar_msg', [
            'tipo'  => 'success',
            'texto' => 'Si el email está registrado, recibirás el enlace en unos minutos.',
        ]);

        if ($usuario && $usuario['activo']) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $this->model->setResetToken((int)$usuario['id'], $tokenHash);

            $resetUrl = BASE_URL . '/auth/reset-password?token=' . urlencode($token);
            Mailer::resetPassword($usuario['email'], $usuario['nombre'], $resetUrl);
            AuditLog::log('reset_request', 'auth', "Solicitud de reset para: {$email}", (int)$usuario['id'], $usuario['nombre']);
        }

        redirect('auth/recuperar');
    }

    /** Formulario de nueva contraseña (llega desde el link del email) */
    public function resetPasswordForm(): void
    {
        if (Session::isLoggedIn()) redirect(homeUrl());

        $token     = $this->param('token', '');
        $tokenHash = $token ? hash('sha256', $token) : '';
        $usuario   = $tokenHash ? $this->model->findByResetToken($tokenHash) : false;

        $this->view('auth.reset_password', [
            'pageTitle' => 'Nueva contraseña',
            'token'     => $token,
            'valido'    => (bool)$usuario,
            'error'     => Session::flash('reset_error'),
        ]);
    }

    /** POST: guarda la nueva contraseña */
    public function resetPassword(): void
    {
        Csrf::check(false); // form tradicional (redirect + flash), no AJAX

        $token     = $this->input('token', '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['password_confirm'] ?? '';
        $tokenHash = $token ? hash('sha256', $token) : '';
        $usuario   = $tokenHash ? $this->model->findByResetToken($tokenHash) : false;

        if (!$usuario) {
            Session::flash('reset_error', 'El enlace expiró o es inválido. Solicitá uno nuevo.');
            redirect('auth/reset-password?token=' . urlencode($token));
        }

        if (strlen($password) < 8) {
            Session::flash('reset_error', 'La contraseña debe tener al menos 8 caracteres.');
            redirect('auth/reset-password?token=' . urlencode($token));
        }

        if ($password !== $confirm) {
            Session::flash('reset_error', 'Las contraseñas no coinciden.');
            redirect('auth/reset-password?token=' . urlencode($token));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->model->updatePassword((int)$usuario['id'], $hash);
        AuditLog::log('reset_password', 'auth', 'Contraseña restablecida', (int)$usuario['id'], $usuario['nombre']);

        Session::flash('login_error', ['tipo' => 'success', 'texto' => 'Contraseña actualizada. Podés iniciar sesión.']);
        redirect('auth/login');
    }
}
