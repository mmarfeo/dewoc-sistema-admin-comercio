<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/UsuarioModel.php';
require_once BASE_PATH . '/app/Models/PermisosModel.php';
require_once BASE_PATH . '/app/Models/PlantillaPermisosModel.php';
require_once BASE_PATH . '/app/Models/ChatAccesoModel.php';
require_once BASE_PATH . '/app/Models/WebauthnCredentialModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionSeguridadModel.php';
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
            'modulos'    => PermisosModel::modulosOrdenados(),
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

        $chatAccesoModel = new ChatAccesoModel();
        // El catálogo se administra con su propio orden en Chat IA → Accesos directos
        // (para configurar la secuencia de botones que ve el usuario en el chat), pero
        // acá, para elegir a mano qué le asignás, se lista alfabéticamente.
        $catalogoAccesosChat = $chatAccesoModel->listarCatalogo();
        usort($catalogoAccesosChat, fn($a, $b) => strcasecmp($a['label'], $b['label']));

        $this->render('usuarios.form', [
            'pageTitle'    => 'Editar usuario',
            'breadcrumb'   => ['Administración' => null, 'Usuarios' => 'usuarios', 'Editar' => null],
            'usuario'      => $usuario,
            'roles'        => $this->model->getRoles(),
            'modulos'      => PermisosModel::modulosOrdenados(),
            // Plantillas activas (para el select "Plantilla de permisos" del usuario y, cruzado
            // con 'roles' arriba, para mostrar de qué plantilla hereda por defecto su rol — ver
            // usuarios/form.php). El desglose de excepciones (ver/quitar una por una, con su
            // vence_en) reemplaza al viejo switch "todo o nada".
            'plantillas'          => (new PlantillaPermisosModel())->listarActivas(),
            'permisosEfectivos'   => $this->model->getPermisosEfectivos($id, (int)$usuario['rol_id']),
            'excepcionesPermisos' => $this->model->getPermisosUsuarioTodas($id),
            'catalogoAccesosChat' => $catalogoAccesosChat,
            'accesosChatAsignados' => $chatAccesoModel->idsAsignadosUsuario($id),
        ]);
    }

    /** Asigna (o quita, con plantilla_id vacío) la plantilla de permisos puntual de un usuario
     *  — manda por sobre la plantilla por defecto de su rol. Ver UsuarioModel::asignarPlantilla(). */
    public function asignarPlantilla(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('usuario_id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'Usuario inválido.'], 422);

        $usuario = $this->model->findById($id);
        if (!$usuario) $this->json(['ok' => false, 'error' => 'Usuario no encontrado.'], 404);

        $plantillaIdRaw = trim((string)$this->input('plantilla_id', ''));
        $plantillaId    = $plantillaIdRaw !== '' ? (int)$plantillaIdRaw : null;

        $this->model->asignarPlantilla($id, $plantillaId);
        AuditLog::log('editar', 'usuarios', "Plantilla de permisos " . ($plantillaId ? "asignada (id={$plantillaId})" : 'quitada (vuelve a heredar del rol)') . " para: {$usuario['email']}");
        $this->json(['ok' => true, 'mensaje' => $plantillaId ? 'Plantilla asignada.' : 'El usuario vuelve a heredar la plantilla de su rol.']);
    }

    /** Guarda qué accesos directos del Chat IA ve este usuario puntual */
    public function guardarAccesosChat(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('usuario_id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'Usuario inválido.'], 422);

        $usuario = $this->model->findById($id);
        if (!$usuario) $this->json(['ok' => false, 'error' => 'Usuario no encontrado.'], 404);

        $accesoIds = array_map('intval', (array)($_POST['accesos'] ?? []));
        (new ChatAccesoModel())->guardarAsignacionUsuario($id, $accesoIds);

        AuditLog::log('editar', 'usuarios', "Accesos directos del chat actualizados para: {$usuario['email']}");
        $this->json(['ok' => true, 'mensaje' => 'Accesos directos actualizados.']);
    }

    /**
     * Agrega o actualiza UNA excepción de permiso puntual de un usuario (sobre lo que le dé
     * su plantilla/rol) — reemplaza al viejo switch "todo o nada" que reescribía las ~35
     * excepciones juntas. $vence_en opcional ('Y-m-d\TH:i' del <input type="datetime-local">)
     * para el caso "temporal": pasado ese momento, deja de aplicar sola (ver
     * UsuarioModel::getPermisosUsuario()), sin que nadie tenga que venir a sacarla.
     */
    public function guardarExcepcionPermiso(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id     = (int)$this->input('usuario_id', 0);
        $modulo = (string)$this->input('modulo', '');
        if (!$id || !array_key_exists($modulo, PermisosModel::MODULOS)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $usuario = $this->model->findById($id);
        if (!$usuario) $this->json(['ok' => false, 'error' => 'Usuario no encontrado.'], 404);

        $ver    = (bool)$this->input('ver', 0);
        $editar = (bool)$this->input('editar', 0);
        $venceRaw = trim((string)$this->input('vence_en', ''));
        $vence  = $venceRaw !== '' ? str_replace('T', ' ', $venceRaw) . ':00' : null;

        $this->model->guardarPermisoUsuario($id, $modulo, $ver, $editar, $vence);
        AuditLog::log('editar', 'usuarios', sprintf(
            'Excepción de permiso "%s" (ver=%s, editar=%s%s) para: %s',
            $modulo, $ver ? '1' : '0', $editar ? '1' : '0',
            $vence ? ", vence {$vence}" : '', $usuario['email']
        ));
        $this->json([
            'ok' => true,
            'mensaje' => 'Excepción guardada.',
            'excepciones' => $this->model->getPermisosUsuarioTodas($id),
        ]);
    }

    /** Saca una excepción puntual — el módulo vuelve a heredar de la plantilla/rol del usuario. */
    public function quitarExcepcionPermiso(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();

        $id     = (int)$this->input('usuario_id', 0);
        $modulo = (string)$this->input('modulo', '');
        if (!$id || !$modulo) $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);

        $usuario = $this->model->findById($id);
        if (!$usuario) $this->json(['ok' => false, 'error' => 'Usuario no encontrado.'], 404);

        $this->model->quitarPermisoUsuario($id, $modulo);
        AuditLog::log('editar', 'usuarios', "Excepción de permiso \"{$modulo}\" quitada para: {$usuario['email']}");
        $this->json([
            'ok' => true,
            'mensaje' => 'Excepción quitada — vuelve a heredar de la plantilla/rol.',
            'excepciones' => $this->model->getPermisosUsuarioTodas($id),
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
        $usuarioId = (int)Session::user()['id'];
        $usuario   = $this->model->findByIdConRol($usuarioId);

        $this->render('usuarios.perfil', [
            'pageTitle'          => 'Mi Perfil',
            'breadcrumb'         => ['Perfil' => null],
            'usuario'            => $usuario,
            'webauthnHabilitado' => (bool)(new ConfiguracionSeguridadModel())->obtener()['webauthn_habilitado'],
            'dispositivos'       => (new WebauthnCredentialModel())->listarPorUsuario($usuarioId),
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

    public function importar(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'No se recibió ningún archivo.'], 422);
        }
        try {
            $rows = ImportHelper::parseUploadedFile($_FILES['archivo']);
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if (empty($rows)) {
            $this->json(['ok' => false, 'error' => 'El archivo está vacío o sin datos.'], 422);
        }

        // Cachear roles
        $roles    = $this->model->getRoles();
        $rolIdx   = array_column($roles, 'id', 'nombre');

        $importados = 0;
        $errores    = [];

        foreach ($rows as $i => $row) {
            $fila   = $i + 2;
            $nombre = ImportHelper::nullIfEmpty($row['nombre'] ?? '');
            $email  = filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $pass   = trim($row['password'] ?? '');
            $rolNom = trim($row['rol'] ?? '');

            if (!$nombre) { $errores[] = "Fila {$fila}: 'nombre' es obligatorio."; continue; }
            if (!$email)  { $errores[] = "Fila {$fila}: 'email' inválido o vacío."; continue; }
            if (!$pass || strlen($pass) < 8) { $errores[] = "Fila {$fila}: 'password' debe tener al menos 8 caracteres."; continue; }

            $rolId = $rolIdx[$rolNom] ?? null;
            if (!$rolId) { $errores[] = "Fila {$fila}: Rol '{$rolNom}' no existe. Use: admin, cajero, deposito, vendedor."; continue; }

            if ($this->model->findByEmail($email)) {
                $errores[] = "Fila {$fila}: El email '{$email}' ya está en uso."; continue;
            }

            try {
                $id = $this->model->insert([
                    'nombre'        => $nombre,
                    'email'         => $email,
                    'rol_id'        => $rolId,
                    'password_hash' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]),
                    'activo'        => 1,
                    'eliminado'     => 0,
                ]);
                AuditLog::log('importar', 'usuarios', "Usuario importado: {$email} (id={$id})");
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: " . $e->getMessage();
            }
        }

        $this->json(['ok' => true, 'importados' => $importados, 'total' => count($rows), 'errores' => $errores]);
    }

    public function plantilla(): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        require_once BASE_PATH . '/core/ImportHelper.php';
        ImportHelper::sendCsvTemplate('plantilla_usuarios.csv',
            ['nombre', 'email', 'password', 'rol'],
            [
                ['María García', 'maria@comercio.com', 'Clave1234!', 'vendedor'],
                ['Carlos López', 'carlos@comercio.com', 'Clave5678!', 'cajero'],
                ['Ana Martínez', 'ana@comercio.com', 'Clave9999!', 'deposito'],
            ]
        );
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

        $pantallaInicio = trim($this->input('pantalla_inicio', ''));
        if (!array_key_exists($pantallaInicio, PermisosModel::MODULOS)) {
            $pantallaInicio = null;
        }

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
            'nombre'          => $nombre,
            'email'           => $email ?: '',
            'rol_id'          => $rolId,
            'activo'          => $activo,
            'eliminado'       => 0,
            'pantalla_inicio' => $pantallaInicio,
            // Independiente del rol a propósito — ver core/helpers.php::puedeVerTareasGenerales()
            // (no todo admin tiene por qué ver las tareas asignadas a "Administradores").
            've_tareas_generales' => (int)$this->input('ve_tareas_generales', 0),
        ];

        if ($password) {
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return [$data, $errores];
    }
}
