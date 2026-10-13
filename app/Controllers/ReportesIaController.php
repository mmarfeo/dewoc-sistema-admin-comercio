<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/ReporteIaModel.php';

class ReportesIaController extends Controller
{
    private ReporteIaModel $model;

    public function __construct()
    {
        $this->model = new ReporteIaModel();
    }

    /** Galería de reportes generados por el Chat IA */
    public function index(): void
    {
        $this->requireCanView('reportes_ia');

        $this->render('reportes-ia.index', [
            'pageTitle'  => 'Reportes IA',
            'breadcrumb' => ['Reportes IA' => null],
            'reportes'   => $this->model->listar(),
        ]);
    }

    /** Detalle de un reporte generado: tabla con el snapshot guardado */
    public function ver(): void
    {
        $this->requireCanView('reportes_ia');

        $id      = (int)$this->param('id', 0);
        $reporte = $id ? $this->model->obtener($id) : false;

        if (!$reporte) {
            abort(404, 'Reporte no encontrado.');
        }

        $this->render('reportes-ia.ver', [
            'pageTitle'  => $reporte['titulo'],
            'breadcrumb' => ['Reportes IA' => 'reportes-ia', $reporte['titulo'] => null],
            'reporte'    => $reporte,
        ]);
    }

    /** Descarga el reporte como archivo .xlsx real, generado desde el snapshot guardado */
    public function exportarXlsx(): void
    {
        $this->requireCanView('reportes_ia');

        $id      = (int)$this->param('id', 0);
        $reporte = $id ? $this->model->obtener($id) : false;

        if (!$reporte) {
            abort(404, 'Reporte no encontrado.');
        }

        require_once BASE_PATH . '/vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $columnas = array_values($reporte['columnas']);
        $filas    = $reporte['resultado'];

        foreach ($columnas as $colIdx => $nombreCol) {
            $sheet->setCellValue([$colIdx + 1, 1], $nombreCol);
        }
        $ultimaColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columnas));
        $sheet->getStyle("A1:{$ultimaColumna}1")->getFont()->setBold(true);

        $filaIdx = 2;
        foreach ($filas as $registro) {
            foreach ($columnas as $colIdx => $nombreCol) {
                $sheet->setCellValue([$colIdx + 1, $filaIdx], $registro[$nombreCol] ?? '');
            }
            $filaIdx++;
        }

        foreach (range(1, count($columnas)) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }

        $nombreArchivo = 'reporte_' . preg_replace('/[^a-z0-9_-]+/i', '_', $reporte['titulo']) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** Elimina un reporte guardado (autor o admin) */
    public function eliminar(): void
    {
        $this->requireCanView('reportes_ia');
        Csrf::check();

        $id      = (int)$this->input('id', 0);
        $reporte = $id ? $this->model->obtener($id) : false;
        $user    = Session::user();

        if (!$reporte) {
            $this->json(['ok' => false, 'error' => 'Reporte no encontrado.'], 404);
        }
        if ($reporte['usuario_id'] != $user['id'] && $user['rol'] !== 'admin') {
            $this->json(['ok' => false, 'error' => 'No podés eliminar reportes de otros usuarios.'], 403);
        }

        $this->model->eliminar($id);
        $this->json(['ok' => true]);
    }
}
