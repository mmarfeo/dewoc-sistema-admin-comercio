<?php

class ImportHelper
{
    private const MAX_SIZE = 4 * 1024 * 1024; // 4 MB

    /**
     * Parsea el archivo subido (CSV o XLSX) y devuelve un array de filas
     * asociativas con los nombres de columna de la primera fila como claves.
     */
    public static function parseUploadedFile(array $file): array
    {
        if ($file['size'] > self::MAX_SIZE) {
            throw new \InvalidArgumentException('El archivo supera el tamaño máximo de 4 MB.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        return match($ext) {
            'csv'  => self::parseCsv($file['tmp_name']),
            'xlsx' => self::parseXlsx($file['tmp_name']),
            default => throw new \InvalidArgumentException('Formato no soportado. Use .csv o .xlsx.'),
        };
    }

    // ─── CSV ─────────────────────────────────────────────────────────────────

    private static function parseCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el archivo CSV.');
        }

        // Quitar BOM UTF-8 si existe
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Excel guarda CSV en Windows-1252 por defecto.
        // Si el contenido no es UTF-8 válido, convertirlo.
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // Escribir en memoria para poder usar fgetcsv
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        // Detectar delimitador con la primera línea
        $firstLine = fgets($stream);
        rewind($stream);
        $delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $rows    = [];
        $headers = null;

        while (($cols = fgetcsv($stream, 0, $delim)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $cols);
                continue;
            }
            if (count(array_filter($cols, fn($v) => trim((string)$v) !== '')) === 0) {
                continue; // fila vacía
            }
            $padded = array_pad($cols, count($headers), '');
            $rows[] = array_combine($headers, array_map('trim', $padded));
        }

        fclose($stream);
        return $rows;
    }

    // ─── XLSX ─────────────────────────────────────────────────────────────────

    private static function parseXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP ZipArchive no está disponible en el servidor. Use formato CSV.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo XLSX. Verifique que no esté dañado.');
        }

        // Shared strings (textos en celdas tipo "s")
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    // Celdas con <t> directo o con runs <r><t>
                    $text = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                    } else {
                        $text = (string)$si->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // Índices de estilo que representan formatos de fecha
        $dateStyleIndices = self::detectDateStyleIndices($zip);

        // Hoja 1
        $wsXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($wsXml === false) {
            throw new \RuntimeException('No se encontró la hoja de cálculo en el archivo XLSX.');
        }

        $ws = simplexml_load_string($wsXml);
        if (!$ws || !isset($ws->sheetData->row)) {
            return [];
        }

        $rawRows = [];

        foreach ($ws->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $ref    = (string)$cell['r'];
                $colStr = preg_replace('/[0-9]/', '', $ref);
                $colIdx = self::colToIndex($colStr);

                $type  = (string)($cell['t'] ?? '');
                $style = isset($cell['s']) ? (int)$cell['s'] : -1;
                $val   = isset($cell->v) ? (string)$cell->v : '';

                if ($type === 's') {
                    $val = $sharedStrings[(int)$val] ?? '';
                } elseif ($type === 'b') {
                    $val = $val === '1' ? 'true' : 'false';
                } elseif ($type === '' && $val !== '' && $style >= 0 && isset($dateStyleIndices[$style])) {
                    // Celda numérica con formato de fecha: convertir serial Excel → YYYY-MM-DD
                    // Serial 0 significa fecha vacía en Excel → tratar como vacío
                    $serial = (float)$val;
                    $val = $serial > 0 ? self::excelSerialToDate($serial) : '';
                }

                while (count($rowData) < $colIdx) {
                    $rowData[] = '';
                }
                $rowData[$colIdx] = trim($val);
            }
            $rawRows[] = $rowData;
        }

        if (count($rawRows) < 1) {
            return [];
        }

        $headers = array_map('trim', array_shift($rawRows));
        $result  = [];

        foreach ($rawRows as $row) {
            $padded = array_pad($row, count($headers), '');
            if (count(array_filter($padded, fn($v) => $v !== '')) === 0) {
                continue;
            }
            $result[] = array_combine($headers, $padded);
        }

        return $result;
    }

    /**
     * Lee xl/styles.xml y devuelve un mapa [styleIndex => true] para los estilos
     * cuyo numFmtId corresponde a un formato de fecha (integrado o personalizado).
     */
    private static function detectDateStyleIndices(\ZipArchive $zip): array
    {
        $stylesXml = $zip->getFromName('xl/styles.xml');
        if ($stylesXml === false) return [];

        $styles = simplexml_load_string($stylesXml);
        if (!$styles) return [];

        // numFmtIds integrados de Excel que representan fechas/horas
        $dateNumFmtIds = array_flip([14,15,16,17,22,27,28,29,30,31,32,33,34,35,36,45,46,47,50,51,52,53,54,55,56,57,58]);

        // Formatos personalizados: detectar por caracteres y/m/d en el código
        if (isset($styles->numFmts->numFmt)) {
            foreach ($styles->numFmts->numFmt as $fmt) {
                $id   = (int)$fmt['numFmtId'];
                $code = strtolower(str_replace('"', '', (string)$fmt['formatCode']));
                if (preg_match('/[ymd]/', $code)) {
                    $dateNumFmtIds[$id] = true;
                }
            }
        }

        $result = [];
        if (isset($styles->cellXfs->xf)) {
            $idx = 0;
            foreach ($styles->cellXfs->xf as $xf) {
                if (isset($dateNumFmtIds[(int)$xf['numFmtId']])) {
                    $result[$idx] = true;
                }
                $idx++;
            }
        }

        return $result;
    }

    /**
     * Convierte un número serial de fecha de Excel al formato YYYY-MM-DD.
     * El offset 25569 ya incluye la corrección por el día bisiesto ficticio
     * de 1900 que Excel añade, por lo que no se requiere ajuste adicional.
     */
    private static function excelSerialToDate(float $serial): string
    {
        $ts = (int)(($serial - 25569) * 86400);
        return gmdate('Y-m-d', $ts);
    }

    private static function colToIndex(string $col): int
    {
        $col   = strtoupper($col);
        $index = 0;
        $len   = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + ord($col[$i]) - ord('A') + 1;
        }
        return $index - 1; // 0-based
    }

    // ─── Descarga de plantilla CSV ────────────────────────────────────────────

    /**
     * Envía al navegador un archivo CSV de ejemplo listo para llenar.
     * Los headers HTTP se envían aquí; no debe haber salida previa.
     *
     * @param string   $filename  Nombre del archivo a descargar
     * @param string[] $headers   Nombres de las columnas
     * @param array[]  $examples  Filas de ejemplo (cada una es un array indexado)
     */
    public static function sendCsvTemplate(string $filename, array $headers, array $examples = []): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM para que Excel abra correctamente en UTF-8
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($examples as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    // ─── Utilidades para los controllers ─────────────────────────────────────

    /**
     * Normaliza un string de fecha al formato YYYY-MM-DD que espera MySQL.
     * Soporta: YYYY-MM-DD (pasa tal cual), dd/mm/YYYY y dd-mm-YYYY.
     */
    public static function normalizeDate(string $v): string
    {
        $v = trim($v);
        // dd/mm/YYYY  o  d/m/YYYY
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        // dd-mm-YYYY  o  d-m-YYYY  (evitar colisión con YYYY-MM-DD que ya tiene 4 dígitos al inicio)
        if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        return $v; // YYYY-MM-DD u otro — se pasa tal cual
    }

    /**
     * Convierte un string numérico a float detectando el separador de decimales.
     *
     *  Casos soportados:
     *   "6400"        → 6400      (sin separadores)
     *   "6400.5"      → 6400.5    (punto decimal — formato ISO / XLSX numérico)
     *   "6400,5"      → 6400.5    (coma decimal — formato AR sin miles)
     *   "6.400"       → 6400      (punto de miles AR: exactamente 3 dígitos después del punto)
     *   "6.400,50"    → 6400.5    (AR completo: ambos separadores, coma al final = decimal)
     *   "1,234.56"    → 1234.56   (US: ambos, punto al final = decimal)
     *   "1.234.567"   → 1234567   (miles AR con más de un grupo)
     */
    public static function toFloat(string $v): float
    {
        $v = trim($v);
        if ($v === '') return 0.0;

        $hasDot   = str_contains($v, '.');
        $hasComma = str_contains($v, ',');

        // Ambos separadores: el que aparece ÚLTIMO es el decimal
        if ($hasDot && $hasComma) {
            if (strrpos($v, ',') > strrpos($v, '.')) {
                // "1.234,56" → decimal = coma (formato AR/EU)
                return (float)str_replace(['.', ','], ['', '.'], $v);
            }
            // "1,234.56" → decimal = punto (formato US)
            return (float)str_replace(',', '', $v);
        }

        // Solo coma → separador decimal argentino: "6400,50" → 6400.50
        if ($hasComma) {
            return (float)str_replace(',', '.', $v);
        }

        // Solo punto → ¿miles o decimal?
        if ($hasDot) {
            // Patrón de miles AR: 1-3 dígitos, luego uno o más grupos de ".ddd"
            // Ej: "6.400" o "1.234.567" → separador de miles
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
                return (float)str_replace('.', '', $v);
            }
            // Cualquier otro caso: "6400.5", "6400.000", "10.5" → decimal
            return (float)$v;
        }

        // Sin separadores: "6400"
        return (float)$v;
    }

    /** Devuelve null si el string está vacío, de lo contrario lo retorna. */
    public static function nullIfEmpty(string $v): ?string
    {
        return trim($v) !== '' ? trim($v) : null;
    }
}
