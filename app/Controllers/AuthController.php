<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
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
            redirect('dashboard');
        }
        // Auto-login si existe cookie "Recordarme" válida
        if (RememberMe::check($this->model)) {
            redirect('dashboard');
        }
        $this->view('auth.login', [
            'pageTitle' => 'Iniciar sesión',
            'error'     => Session::flash('login_error'),
        ]);
    }

    public function login(): void
    {
        Csrf::check();

        $email    = $this->input('email', '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            Session::flash('login_error', ['tipo' => 'danger', 'texto' => 'Completá todos los campos.']);
            redirect('auth/login');
        }

        $usuario = $this->model->findByEmail($email);

        if (!$usuario || !$usuario['activo']) {
            Session::flash('login_error', ['tipo' => 'danger', 'texto' => 'Credenciales incorrectas.']);
            redirect('auth/login');
        }

        if ($this->model->estaBloquado($usuario)) {
            Session::flash('login_error', ['tipo' => 'warning', 'texto' => 'Cuenta bloqueada temporalmente. Intentá en ' . LOGIN_LOCKOUT_MINUTES . ' minutos.']);
            redirect('auth/login');
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            $this->model->registrarIntentoFallido($usuario['id']);
            logApp("Login fallido para {$email}", 'WARN');
            AuditLog::log('login_fail', 'auth', "Email: {$email}", (int)$usuario['id'], $usuario['nombre']);
            Session::flash('login_error', ['tipo' => 'danger', 'texto' => 'Credenciales incorrectas.']);
            redirect('auth/login');
        }

        // Login exitoso
        $this->model->resetIntentos($usuario['id']);
        Session::regenerate();

        $permisos = $this->model->getPermisos($usuario['rol_id']);

        Session::set('auth_user', [
            'id'     => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email'  => $usuario['email'],
            'rol'    => $usuario['rol'],
            'rol_id' => $usuario['rol_id'],
            'foto'   => $usuario['foto'],
        ]);
        Session::set('permisos', $permisos);

        // Cookie "Recordarme" (30 días)
        if (!empty($_POST['recordarme'])) {
            RememberMe::set($this->model, (int)$usuario['id']);
        }

        logApp("Login exitoso: {$email} | rol: {$usuario['rol']}");
        AuditLog::log('login', 'auth', "Rol: {$usuario['rol']}", (int)$usuario['id'], $usuario['nombre']);
        redirect('dashboard');
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
        if (Session::isLoggedIn()) redirect('dashboard');
        $this->view('auth.recuperar', [
            'pageTitle' => 'Recuperar contraseña',
            'msg'       => Session::flash('recuperar_msg'),
        ]);
    }

    /** POST: envía el email de restablecimiento */
    public function recuperar(): void
    {
        Csrf::check();

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
        if (Session::isLoggedIn()) redirect('dashboard');

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
        Csrf::check();

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
