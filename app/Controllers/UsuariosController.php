<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/core/AuditLog.php';

class UsuariosController extends Controller
{
    private UsuarioModel $model;

    public function __construct()
    {
        $this->model = new UsuarioModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $this->render('usuarios.index', [
            'pageTitle'  => 'Usuarios',
            'breadcrumb' => ['Administración' => null, 'Usuarios' => null],
            'usuarios'   => $this->model->findAllConRol(),
            'roles'      => $this->model->getRoles(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $this->render('usuarios.form', [
            'pageTitle'  => 'Nuevo usuario',
            'breadcrumb' => ['Administración' => null, 'Usuarios' => 'usuarios', 'Nuevo' => null],
            'usuario'    => null,
            'roles'      => $this->model->getRoles(),
        ]);
    }

    public function crear(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        [$data, $errores] = $this->parsearForm(true);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        if ($this->model->findByEmail($data['email'])) {
            $this->json(['ok' => false, 'error' => 'El email ya está en uso.'], 422);
        }

        $id = $this->model->insert($data);
        AuditLog::log('crear', 'usuarios', "Usuario creado: {$data['email']} (rol_id={$data['rol_id']})");
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Usuario creado.']);
    }

    public function editar(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');

        $id      = (int)$this->param('id');
        $usuario = $this->model->findById($id);
        if (!$usuario) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('usuarios.form', [
            'pageTitle'  => 'Editar usuario',
            'breadcrumb' => ['Administración' => null, 'Usuarios' => 'usuarios', 'Editar' => null],
            'usuario'    => $usuario,
            'roles'      => $this->model->getRoles(),
        ]);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$data, $errores] = $this->parsearForm(false);
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        // Si no se envió contraseña, no la actualizar
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->model->update($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Usuario actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id      = (int)$this->input('id');
        $current = (int)Session::user()['id'];

        if ($id === $current) {
            $this->json(['ok' => false, 'error' => 'No puede eliminarse a sí mismo.'], 409);
        }

        $eliminado = $this->model->findById($id);
        $this->model->softDelete($id);
        AuditLog::log('eliminar', 'usuarios', "Usuario eliminado: " . ($eliminado['email'] ?? "id={$id}"));
        $this->json(['ok' => true, 'mensaje' => 'Usuario eliminado.']);
    }

    public function perfil(): void
    {
        $this->requireAuth();
        $usuario = $this->model->findByIdConRol((int)Session::user()['id']);

        $this->render('usuarios.perfil', [
            'pageTitle'  => 'Mi Perfil',
            'breadcrumb' => ['Perfil' => null],
            'usuario'    => $usuario,
        ]);
    }

    public function subirFoto(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int)Session::user()['id'];

        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 422);
        }

        $file = $_FILES['foto'];

        if ($file['size'] > MAX_FILE_SIZE) {
            $this->json(['ok' => false, 'error' => 'El archivo supera el tamaño máximo (5 MB).'], 422);
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_IMAGES, true)) {
            $this->json(['ok' => false, 'error' => 'Formato no permitido. Use JPG, PNG o WebP.'], 422);
        }

        $destDir = UPLOAD_PATH . '/usuarios';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if ($mimeType === 'image/png') {
            $src = imagecreatefrompng($file['tmp_name']);
        } elseif ($mimeType === 'image/webp') {
            $src = imagecreatefromwebp($file['tmp_name']);
        } else {
            $src = imagecreatefromjpeg($file['tmp_name']);
        }

        if (!$src) {
            $this->json(['ok' => false, 'error' => 'No se pudo procesar la imagen.'], 422);
        }

        [$srcW, $srcH] = getimagesize($file['tmp_name']);
        $size = min($srcW, $srcH);
        $x    = (int)(($srcW - $size) / 2);
        $y    = (int)(($srcH - $size) / 2);

        $out = imagecreatetruecolor(200, 200);
        imagecopyresampled($out, $src, 0, 0, $x, $y, 200, 200, $size, $size);
        imagedestroy($src);

        $destFile = $destDir . '/' . $id . '.jpg';
        imagejpeg($out, $destFile, 85);
        imagedestroy($out);

        $fotoRel = 'uploads/usuarios/' . $id . '.jpg';
        $this->model->update($id, ['foto' => $fotoRel]);

        $this->json([
            'ok'      => true,
            'url'     => BASE_URL . '/' . $fotoRel . '?v=' . time(),
            'mensaje' => 'Foto de perfil actualizada.',
        ]);
    }

    public function cambiarPassword(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuario   = Session::user();
        $id        = (int)$usuario['id'];
        $actual    = $this->input('password_actual', '');
        $nueva     = $this->input('password_nueva', '');
        $confirmar = $this->input('password_confirmar', '');

        $dbUser = $this->model->findById($id);
        if (!$dbUser || !password_verify($actual, $dbUser['password_hash'])) {
            $this->json(['ok' => false, 'error' => 'La contraseña actual es incorrecta.'], 422);
        }
        if (strlen($nueva) < 8) {
            $this->json(['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.'], 422);
        }
        if ($nueva !== $confirmar) {
            $this->json(['ok' => false, 'error' => 'Las contraseñas nuevas no coinciden.'], 422);
        }

        $this->model->update($id, [
            'password_hash' => password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        $this->json(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
    }

    private function parsearForm(bool $requierePassword): array
    {
        $errores = [];

        $nombre   = $this->input('nombre', '');
        $email    = filter_var($this->input('email', ''), FILTER_VALIDATE_EMAIL);
        $rolId    = (int)$this->input('rol_id', 0);
        $activo   = (int)$this->input('activo', 1);
        $password = $this->input('password', '');
        $confirm  = $this->input('password_confirm', '');

        if (!$nombre)  $errores[] = 'El nombre es obligatorio.';
        if (!$email)   $errores[] = 'El email no es válido.';
        if (!$rolId)   $errores[] = 'Seleccione un rol.';

        if ($requierePassword && !$password) {
            $errores[] = 'La contraseña es obligatoria.';
        }
        if ($password && strlen($password) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($password && $password !== $confirm) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        $data = [
            'nombre'    => $nombre,
            'email'     => $email ?: '',
            'rol_id'    => $rolId,
            'activo'    => $activo,
            'eliminado' => 0,
        ];

        if ($password) {
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return [$data, $errores];
    }
}
