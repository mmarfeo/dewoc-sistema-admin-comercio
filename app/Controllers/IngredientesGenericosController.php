<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/IngredienteGenericoModel.php';
require_once BASE_PATH . '/app/Models/CategoriaModel.php';
require_once BASE_PATH . '/app/Models/ConfiguracionFiscalModel.php';

class IngredientesGenericosController extends Controller
{
    private IngredienteGenericoModel $model;

    public function __construct()
    {
        $this->model = new IngredienteGenericoModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('ingredientes-genericos.index', [
            'pageTitle'    => 'Ingredientes Genéricos',
            'breadcrumb'   => ['Maestros' => null, 'Ingredientes Genéricos' => null],
            'items'        => $this->model->listar(),
            'unidades'     => Database::getInstance()->query('SELECT * FROM unidades_medida ORDER BY nombre')->fetchAll(),
            'categorias'   => (new CategoriaModel())->findAllConPadre('materia_prima'),
            'configFiscal' => (new ConfiguracionFiscalModel())->obtener(),
        ]);
    }

    /** Umbral de puntos % de caída de margen que dispara el aviso push (solo admin) */
    public function guardarUmbralMargen(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $umbral = $this->input('umbral_alerta_margen', '');
        $valor  = $umbral === '' ? null : max(0.0, (float) str_replace(',', '.', $umbral));

        (new ConfiguracionFiscalModel())->guardarUmbralMargen($valor);
        $this->json(['ok' => true, 'mensaje' => 'Umbral guardado.']);
    }

    public function crear(): void
    {
        $this->requireAuth();
        Csrf::check();

        $data = $this->recogerForm();
        if (empty($data['nombre']))           $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        if (empty($data['unidad_medida_id'])) $this->json(['ok' => false, 'error' => 'La unidad de medida es obligatoria.'], 422);

        $id = $this->model->crear($data);
        $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Ingrediente genérico creado.']);
    }

    public function actualizar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int) $this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        $data = $this->recogerForm();
        if (empty($data['nombre'])) $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);

        // La unidad de medida es inmutable una vez creado el genérico (IngredienteGenericoModel::actualizar
        // solo toca nombre/categoria_id) — así nunca se rompe la comparabilidad de costos entre variantes.
        $this->model->actualizar($id, $data);
        $this->json(['ok' => true, 'mensaje' => 'Ingrediente genérico actualizado.']);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $id = (int) $this->input('id', 0);
        if (!$id) $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);

        if ($this->model->tieneVariantesAsignadas($id)) {
            $this->json(['ok' => false, 'error' => 'No se puede eliminar: tiene materias primas o insumos asignados.'], 409);
        }

        $this->model->eliminar($id);
        $this->json(['ok' => true, 'mensaje' => 'Ingrediente genérico eliminado.']);
    }

    /** AJAX: búsqueda difusa por nombre (usada también por el Chat IA) */
    public function buscar(): void
    {
        $this->requireAuth();
        $q = trim($this->param('q', ''));
        $this->json(['ok' => true, 'resultados' => $q !== '' ? $this->model->buscarPorNombre($q) : []]);
    }

    // ─── Variantes (materias primas) asignadas a un genérico puntual ────────────────

    /** AJAX: materias primas asignadas a un genérico, para el modal de detalle */
    public function variantes(): void
    {
        $this->requireAuth();
        $id = (int) $this->param('id', 0);
        if (!$id) $this->json(['ok' => false, 'variantes' => []]);
        $this->json(['ok' => true, 'variantes' => $this->model->getVariantes($id)]);
    }

    /** AJAX: materias primas candidatas para agregar (misma unidad, sin genérico asignado) */
    public function buscarVariantes(): void
    {
        $this->requireAuth();
        $genericoId = (int) $this->param('generico_id', 0);
        $q          = trim($this->param('q', ''));
        if (!$genericoId) $this->json(['ok' => false, 'resultados' => []]);
        $this->json(['ok' => true, 'resultados' => $this->model->buscarCandidatasParaAsignar($genericoId, $q)]);
    }

    /** Asigna una materia prima O insumo existente a un genérico (mismo chequeo de unidad que el resto del sistema) */
    public function asignarVariante(): void
    {
        $this->requireAuth();
        Csrf::check();

        $genericoId = (int) $this->input('generico_id', 0);
        $tipo       = (string) $this->input('tipo', 'materia_prima');
        $itemId     = (int) $this->input('item_id', 0) ?: (int) $this->input('materia_prima_id', 0); // materia_prima_id: compat con llamadas viejas
        if (!$genericoId || !$itemId || !in_array($tipo, ['materia_prima', 'insumo'], true)) {
            $this->json(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }

        $tabla = $tipo === 'insumo' ? 'insumos' : 'materias_primas';
        $stmtUnidad = Database::getInstance()->prepare("SELECT unidad_medida_id FROM {$tabla} WHERE id = ?");
        $stmtUnidad->execute([$itemId]);
        $unidad = (int) $stmtUnidad->fetchColumn();
        if (!$this->model->unidadCompatible($genericoId, $unidad)) {
            $this->json(['ok' => false, 'error' => 'La unidad de medida de ese ítem no coincide con la del genérico.'], 422);
        }

        $this->model->asignarVariante($genericoId, $tipo, $itemId);
        $etiqueta = $tipo === 'insumo' ? 'Insumo asignado.' : 'Materia prima asignada.';
        $this->json(['ok' => true, 'variantes' => $this->model->getVariantes($genericoId), 'mensaje' => $etiqueta]);
    }

    /** Desvincula una materia prima o insumo de su genérico — no lo elimina, queda como variante suelta */
    public function quitarVariante(): void
    {
        $this->requireAuth();
        Csrf::check();

        $genericoId = (int) $this->input('generico_id', 0);
        $tipo       = (string) $this->input('tipo', 'materia_prima');
        $itemId     = (int) $this->input('item_id', 0) ?: (int) $this->input('materia_prima_id', 0);
        if (!$itemId || !in_array($tipo, ['materia_prima', 'insumo'], true)) {
            $this->json(['ok' => false, 'error' => 'ID inválido.'], 422);
        }

        $this->model->quitarVariante($tipo, $itemId);
        $etiqueta = $tipo === 'insumo' ? 'Insumo desvinculado.' : 'Materia prima desvinculada.';
        $this->json(['ok' => true, 'variantes' => $this->model->getVariantes($genericoId), 'mensaje' => $etiqueta]);
    }

    private function recogerForm(): array
    {
        return [
            'nombre'           => trim($this->input('nombre', '')),
            'unidad_medida_id' => (int) ($this->input('unidad_medida_id', 0)) ?: null,
            'categoria_id'     => (int) ($this->input('categoria_id', 0)) ?: null,
        ];
    }
}
