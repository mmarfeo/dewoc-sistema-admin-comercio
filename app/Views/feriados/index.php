<?php
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

/** Arma las semanas (lunes a domingo) de un mes: cada celda es el día (1..N) o null */
function _feriadosGrillaMes(int $anio, int $mes): array
{
    $primerDia       = new DateTime(sprintf('%04d-%02d-01', $anio, $mes));
    $totalDias       = (int)$primerDia->format('t');
    $diaSemanaInicio = (int)$primerDia->format('N'); // 1=lunes … 7=domingo

    $celdas = array_fill(0, $diaSemanaInicio - 1, null);
    for ($d = 1; $d <= $totalDias; $d++) $celdas[] = $d;
    while (count($celdas) % 7 !== 0) $celdas[] = null;

    return array_chunk($celdas, 7);
}
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Feriados <?= $anio ?></h1>
        <p class="text-muted small mb-0">Hacé clic en un día para marcarlo/desmarcarlo como feriado. Se usa para calcular el recargo de las jornadas de empleados.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= url('feriados?anio=' . ($anio - 1)) ?>" class="btn btn-outline-secondary btn-sm">← <?= $anio - 1 ?></a>
        <a href="<?= url('feriados?anio=' . date('Y')) ?>" class="btn btn-outline-secondary btn-sm">Hoy</a>
        <a href="<?= url('feriados?anio=' . ($anio + 1)) ?>" class="btn btn-outline-secondary btn-sm"><?= $anio + 1 ?> →</a>
    </div>
</div>

<div class="row g-3" id="gridMeses">
    <?php foreach ($meses as $numMes => $nombreMes): ?>
    <div class="col-md-6 col-lg-4 col-xl-3">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent py-2"><h6 class="fw-semibold mb-0 small"><?= $nombreMes ?></h6></div>
            <div class="card-body pt-0">
                <table class="table table-sm table-borderless mb-0 text-center" style="font-size:.78rem">
                    <thead>
                        <tr class="text-muted">
                            <th class="fw-normal">L</th><th class="fw-normal">M</th><th class="fw-normal">M</th>
                            <th class="fw-normal">J</th><th class="fw-normal">V</th><th class="fw-normal">S</th><th class="fw-normal">D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (_feriadosGrillaMes($anio, $numMes) as $semana): ?>
                        <tr>
                            <?php foreach ($semana as $dia): ?>
                                <?php if ($dia === null): ?>
                                <td></td>
                                <?php else:
                                    $fechaIso = sprintf('%04d-%02d-%02d', $anio, $numMes, $dia);
                                    $esFer    = isset($feriados[$fechaIso]);
                                    $ferId    = $esFer ? $feriados[$fechaIso]['id'] : '';
                                    $ferNom   = $esFer ? ($feriados[$fechaIso]['nombre'] ?? '') : '';
                                ?>
                                <td>
                                    <span class="dia-cell <?= $esFer ? 'dia-feriado' : '' ?>"
                                          data-fecha="<?= $fechaIso ?>"
                                          data-id="<?= e($ferId) ?>"
                                          data-nombre="<?= e($ferNom) ?>"
                                          title="<?= $esFer ? e($ferNom ?: 'Feriado') : '' ?>"
                                          onclick="toggleFeriado(this)">
                                        <?= $dia ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
    .dia-cell {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 50%; cursor: pointer;
    }
    .dia-cell:hover { background: rgba(13,110,253,.12); }
    .dia-feriado { background: #dc3545; color: #fff; font-weight: 600; }
    .dia-feriado:hover { background: #bb2d3b; }
</style>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

async function toggleFeriado(el) {
    const fecha    = el.dataset.fecha;
    const esFeriado = el.classList.contains('dia-feriado');

    if (esFeriado) {
        const msg = `¿Quitar el feriado del ${fecha}${el.dataset.nombre ? ' ("' + el.dataset.nombre + '")' : ''}?`;
        const ejecutar = async () => {
            const fd = new FormData();
            fd.append('id', el.dataset.id);
            fd.append('_csrf_token', CSRF_TOKEN);
            const r = await fetch(`${BASE_URL}/feriados/eliminar`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                el.classList.remove('dia-feriado');
                el.title = ''; el.dataset.id = ''; el.dataset.nombre = '';
                showToast(d.mensaje);
            } else {
                showToast(d.error, 'danger');
            }
        };
        if (typeof showConfirm === 'function') {
            showConfirm(msg, ejecutar, { label: 'Quitar' });
        } else {
            if (confirm(msg)) ejecutar();
        }
    } else {
        const nombre = prompt(`Nombre del feriado (${fecha}) — opcional:`, '');
        if (nombre === null) return; // cancelado
        const fd = new FormData();
        fd.append('fecha', fecha);
        fd.append('nombre', nombre);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/feriados/crear`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            el.classList.add('dia-feriado');
            el.dataset.id = d.id; el.dataset.nombre = nombre;
            el.title = nombre || 'Feriado';
            showToast(d.mensaje);
        } else {
            showToast(d.error, 'danger');
        }
    }
}
</script>
