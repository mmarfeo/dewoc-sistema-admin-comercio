<?php
require_once __DIR__ . '/../../tests/TestCase.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/helpers.php';

/**
 * Integridad de las tablas que usan DataTables.
 *
 * Existe por un bug real y repetido: varias vistas dibujaban una fila
 * `<tr><td colspan="N">Sin datos</td></tr>` cuando no había registros. DataTables cuenta
 * las celdas del tbody para validar la tabla y NO interpreta colspan, así que veía 1
 * columna contra las N del thead y abortaba con
 *
 *   DataTables warning: table id=... - Incorrect column count
 *
 * El efecto no es solo el cartel: la tabla queda sin inicializar, y la pantalla pierde el
 * ordenamiento, el buscador y la paginación. Y solo se nota con la tabla vacía, que es
 * justo el caso que nadie prueba.
 *
 * El cartel de "sin datos" lo tiene que poner DataTables con language.emptyTable.
 */
class DataTablesTest extends TestCase
{
    /** Tablas inicializadas con DataTable(), por vista: [archivo => [id, ...]] */
    private function tablasConDataTable(): array
    {
        $out = [];
        foreach ($this->vistas() as $archivo) {
            $src = file_get_contents($archivo);
            if (!preg_match_all('/[\'"]#(\w+)[\'"]\s*\)\s*\.DataTable/', $src, $m)) continue;
            $out[$archivo] = array_unique($m[1]);
        }
        return $out;
    }

    private function vistas(): array
    {
        return array_merge(
            glob(BASE_PATH . '/app/Views/*.php') ?: [],
            glob(BASE_PATH . '/app/Views/*/*.php') ?: [],
            glob(BASE_PATH . '/app/Views/*/*/*.php') ?: []
        );
    }

    /** Recorta el HTML de la tabla con ese id (hasta su </table>). */
    private function bloqueTabla(string $src, string $id): ?string
    {
        $i = strpos($src, 'id="' . $id . '"');
        if ($i === false) return null;
        $fin = strpos($src, '</table>', $i);
        return $fin === false ? null : substr($src, $i, $fin - $i);
    }

    public function testNingunaTablaDeDataTablesTieneUnaFilaConColspan(): void
    {
        $culpables = [];

        foreach ($this->tablasConDataTable() as $archivo => $ids) {
            $src = file_get_contents($archivo);
            foreach ($ids as $id) {
                $tabla = $this->bloqueTabla($src, $id);
                if ($tabla === null) continue;

                // Una tabla de una sola columna con colspan no rompe nada; el problema es
                // cuando el thead declara más de una.
                $columnas = preg_match_all('/<th\b/', $tabla);
                if ($columnas <= 1) continue;

                if (preg_match('/<td\s+colspan=/i', $tabla)) {
                    $culpables[] = basename($archivo) . ' #' . $id . " ({$columnas} columnas)";
                }
            }
        }

        $this->assertTrue(
            empty($culpables),
            'Ninguna tabla de DataTables debe tener una fila con colspan en su tbody'
                . ($culpables ? ' — rompen: ' . implode(', ', $culpables) : '')
        );
    }

    /**
     * Una tabla de DataTables sin language.emptyTable muestra el texto en inglés por
     * defecto ("No data available in table"). No es un error, pero queda fuera de lugar en
     * una app en español, y es justamente el cartel que reemplaza a la fila con colspan.
     */
    public function testLasTablasQueSeVacianTienenElCartelEnEspanol(): void
    {
        // Solo se exige en las vistas donde se quitó la fila con colspan: son las que se
        // sabe que pueden quedar vacías. Pedirlo en todas sería ruido.
        $vistas = [
            '/app/Views/caja/diferencias.php'        => 'tblDiferencias',
            '/app/Views/chat/accesos.php'            => 'tblAccesos',
            '/app/Views/recetas-borrador/index.php'  => 'tblBorradores',
            '/app/Views/ventas/comprobantes-afip.php'=> 'tblComprobantes',
            '/app/Views/gemini-log/index.php'        => 'tblLlamadas',
        ];

        foreach ($vistas as $rel => $id) {
            $src = file_get_contents(BASE_PATH . $rel);
            $this->assertContains('emptyTable', $src,
                basename($rel) . " (#{$id}) debe definir language.emptyTable");
        }
    }

    public function testLasVistasCorregidasNoVolvieronAPonerLaFilaVacia(): void
    {
        // Chequeo directo sobre los cinco casos que se arreglaron, con el id de la tabla,
        // para que el mensaje de error sea inequívoco si alguien la reintroduce.
        $casos = [
            '/app/Views/caja/diferencias.php'         => 'tblDiferencias',
            '/app/Views/chat/accesos.php'             => 'tblAccesos',
            '/app/Views/recetas-borrador/index.php'   => 'tblBorradores',
            '/app/Views/ventas/comprobantes-afip.php' => 'tblComprobantes',
            '/app/Views/gemini-log/index.php'         => 'tblLlamadas',
        ];

        foreach ($casos as $rel => $id) {
            $tabla = $this->bloqueTabla(file_get_contents(BASE_PATH . $rel), $id);
            $this->assertTrue($tabla !== null, basename($rel) . ": debe existir la tabla #{$id}");
            if ($tabla === null) continue;

            $this->assertFalse(
                (bool)preg_match('/<td\s+colspan=/i', $tabla),
                basename($rel) . " (#{$id}): sin fila con colspan en el tbody"
            );
        }
    }

    /**
     * Si una tabla ordena por una columna fija (order: [[N, ...]]), ese índice tiene que
     * existir en el thead. Un índice de más también deja la tabla sin inicializar.
     */
    public function testElOrdenInicialApuntaAUnaColumnaQueExiste(): void
    {
        $culpables = [];

        foreach ($this->tablasConDataTable() as $archivo => $ids) {
            $src = file_get_contents($archivo);
            foreach ($ids as $id) {
                $tabla = $this->bloqueTabla($src, $id);
                if ($tabla === null) continue;
                $columnas = preg_match_all('/<th\b/', $tabla);
                if ($columnas < 1) continue;

                // Busca el init de ESTA tabla y le saca los índices de order
                $posInit = strpos($src, "#{$id}'");
                if ($posInit === false) $posInit = strpos($src, "#{$id}\"");
                if ($posInit === false) continue;

                $init = substr($src, $posInit, 400);
                if (!preg_match_all('/order\s*:\s*\[\s*\[\s*(\d+)/', $init, $m)) continue;

                foreach ($m[1] as $indice) {
                    if ((int)$indice >= $columnas) {
                        $culpables[] = basename($archivo) . " #{$id}: order apunta a la columna "
                            . $indice . ' y solo hay ' . $columnas;
                    }
                }
            }
        }

        $this->assertTrue(empty($culpables),
            'El orden inicial de cada tabla debe apuntar a una columna existente'
                . ($culpables ? ' — ' . implode(' · ', $culpables) : ''));
    }
}
