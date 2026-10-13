<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/GrupoOpcionModel.php';
require_once BASE_PATH . '/core/MenuAppNotifier.php';

/**
 * Admin de "Grupos de opciones/adicionales" configurables por producto
 * (ver docs/tareas-pendientes.md #1) — ej. "Gustos de helado", "Torta de la promo".
 */
class OpcionesGrupoController extends Controller
{
    private GrupoOpcionModel $model;

    public function __construct()
    {
        $this->model = new GrupoOpcionModel();
    }

    public function index(): void
    {
        $this->requireCanView('opciones_productos');
        $this->render('opciones-grupos.index', [
            'pageTitle'  => 'Opciones/Adicionales de Productos',
            'breadcrumb' => ['Maestros' => null, 'Opciones/Adicionales' => null],
            'grupos'     => $this->model->listar(),
        ]);
    }

    public function nuevo(): void
    {
        $this->requireCanEdit('opciones_productos');
        $this->render('opciones-grupos.form', [
            'pageTitle'  => 'Nuevo grupo de opciones',
            'breadcrumb' => ['Maestros' => null, 'Opciones/Adicionales' => 'opciones-productos', 'Nuevo' => null],
            'grupo'      => null,
            'ingredientesPool' => $this->ingredientesPool(),
        ]);
    }

    public function editar(): void
    {
        $this->requireCanEdit('opciones_productos');
        $id    = (int)$this->param('id');
        $grupo = $this->model->findByIdConOpciones($id);
        if (!$grupo) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        $this->render('opciones-grupos.form', [
            'pageTitle'  => 'Editar grupo: ' . $grupo['nombre'],
            'breadcrumb' => ['Maestros' => null, 'Opciones/Adicionales' => 'opciones-productos', 'Editar' => null],
            'grupo'      => $grupo,
            'ingredientesPool' => $this->ingredientesPool(),
        ]);
    }

    public function guardar(): void
    {
        $this->requireCanEdit('opciones_productos');
        Csrf::check();

        [$cabecera, $opciones, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $id = $this->model->guardar($cabecera, $opciones);
            MenuAppNotifier::catalogoCambio('grupo_opciones', $id);
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Grupo de opciones creado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function actualizar(): void
    {
        $this->requireCanEdit('opciones_productos');
        Csrf::check();

        $id = (int)$this->input('id');
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        [$cabecera, $opciones, $errores] = $this->parsearForm();
        if ($errores) $this->json(['ok' => false, 'error' => implode(' ', $errores)], 422);

        try {
            $this->model->actualizarConOpciones($id, $cabecera, $opciones);
            // Un cambio acá puede agregar, renombrar o desactivar gustos de varios productos
            // a la vez: el aviso no lleva los datos, MenuApp relee el catálogo completo.
            MenuAppNotifier::catalogoCambio('grupo_opciones', $id);
            $this->json(['ok' => true, 'mensaje' => 'Grupo de opciones actualizado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Baja lógica — no se borra en duro porque puede haber ventas históricas con esta opción elegida. */
    public function eliminar(): void
    {
        $this->requireCanEdit('opciones_productos');
        Csrf::check();
        $id = (int)$this->input('id');
        $this->model->desactivar($id);
        MenuAppNotifier::catalogoCambio('grupo_opciones', $id);
        $this->json(['ok' => true, 'mensaje' => 'Grupo desactivado.']);
    }

    public function activar(): void
    {
        $this->requireCanEdit('opciones_productos');
        Csrf::check();
        $id = (int)$this->input('id');
        $this->model->activar($id);
        MenuAppNotifier::catalogoCambio('grupo_opciones', $id);
        $this->json(['ok' => true, 'mensaje' => 'Grupo reactivado.']);
    }

    /** Materias primas + insumos + productos activos, para el selector "a qué se descuenta stock" de cada opción. */
    private function ingredientesPool(): array
    {
        $db = Database::getInstance();
        $mp = $db->query(
            "SELECT id, nombre FROM materias_primas WHERE eliminado=0 AND activo=1 ORDER BY nombre"
        )->fetchAll();
        $ins = $db->query(
            "SELECT id, nombre FROM insumos WHERE eliminado=0 AND activo=1 ORDER BY nombre"
        )->fetchAll();
        // Productos: para promos donde cada opción es en sí un producto vendible por
        // separado (ej. "Cookies", "Torta de chocolate", "Café", "Helado" como sabores/
        // variantes elegibles), no solo materia prima suelta.
        $prod = $db->query(
            "SELECT id, nombre FROM productos WHERE eliminado=0 AND activo=1 ORDER BY nombre"
        )->fetchAll();
        return ['materias_primas' => $mp, 'insumos' => $ins, 'productos' => $prod];
    }

    private function parsearForm(): array
    {
        $errores = [];

        $nombre         = trim($this->input('nombre', ''));
        $tipoSeleccion  = $this->input('tipo_seleccion', 'unica');
        $permiteRepetir = (int)$this->input('permite_repetir', 0);
        $activo         = (int)$this->input('activo', 1);

        if (!$nombre) $errores[] = 'El nombre del grupo es obligatorio.';
        if (!in_array($tipoSeleccion, ['unica', 'multiple'], true)) $tipoSeleccion = 'unica';
        if ($tipoSeleccion === 'unica') $permiteRepetir = 0; // no aplica a selección única

        $opcionesJson = $this->input('opciones_json', '[]');
        $items        = json_decode($opcionesJson, true);
        if (!is_array($items) || !count($items)) {
            $errores[] = 'Agregue al menos una opción.';
        }

        $cabecera = [
            'nombre'          => $nombre,
            'tipo_seleccion'  => $tipoSeleccion,
            'permite_repetir' => $permiteRepetir,
            'activo'          => $activo,
        ];

        $opciones = [];
        foreach ($items ?? [] as $i => $it) {
            $nombreOp = trim((string)($it['nombre'] ?? ''));
            if ($nombreOp === '') { $errores[] = 'Falta el nombre de una opción.'; continue; }
            $opciones[] = [
                'nombre'             => $nombreOp,
                'materia_prima_id'   => !empty($it['materia_prima_id']) ? (int)$it['materia_prima_id'] : null,
                'insumo_id'          => !empty($it['insumo_id']) ? (int)$it['insumo_id'] : null,
                'producto_id'        => !empty($it['producto_id']) ? (int)$it['producto_id'] : null,
                'cantidad_consumida' => (float)($it['cantidad_consumida'] ?? 0),
                'orden'              => $i,
            ];
        }

        return [$cabecera, $opciones, $errores];
    }
}
