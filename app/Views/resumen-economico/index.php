<?php
$mesesEs = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

if ($modo === 'mes') {
    $labelsM = array_map(fn($m) => $mesesEs[$m - 1], $meses);
    $labelPeriodo = (count($labelsM) === 1)
        ? $labelsM[0] . ' ' . $anio
        : implode(', ', $labelsM) . ' ' . $anio;
} elseif ($modo === 'anio') {
    $labelPeriodo = 'Año ' . $anio;
} else {
    $labelPeriodo = fecha($desde) . ' — ' . fecha($hasta);
}

$positivo = $resultadoNeto >= 0;
$margen   = $totalIngresos > 0 ? round($resultadoNeto / $totalIngresos * 100, 1) : 0;

/**
 * Renderiza recursivamente una fila del árbol de Ingresos/Egresos.
 * $nodo = ['id'=>, 'label'=>, 'monto'=>, 'color'=>?, 'hijos'=>?, 'pagado'=>?, 'pendiente'=>?, 'pendienteLiquidar'=>?]
 * Si el nodo trae 'pagado'/'pendiente' (egresos con estado de pago rastreado: compras y gastos),
 * se muestra debajo del monto cuánto ya se pagó y cuánto falta — solo cuando hay algo pendiente,
 * para no ensuciar la vista cuando todo un ítem ya está saldado.
 */
function _erArbol(array $nodo, int $nivel = 0, string $parentId = ''): void
{
    $id         = $nodo['id'];
    $hijos      = $nodo['hijos'] ?? [];
    $tieneHijos = !empty($hijos);
    $color      = $nodo['color'] ?? '';
    $esRaiz     = $nivel === 0;
    $indent     = $nivel * 0.9;

    $tienePagoTrack = array_key_exists('pendiente', $nodo);
    $pendiente      = (float)($nodo['pendiente'] ?? 0);
    $pagado         = (float)($nodo['pagado'] ?? 0);
    $pendienteLiq   = $nodo['pendienteLiquidar'] ?? null;
    ?>
    <tr data-node-id="<?= e($id) ?>" data-parent-id="<?= e($parentId) ?>" <?= $parentId ? 'style="display:none"' : '' ?>>
        <td class="border-0 <?= $esRaiz ? 'ps-0' : '' ?>" style="padding-left:<?= $indent ?>rem">
            <?php if ($tieneHijos): ?>
            <span class="d-inline-flex align-items-center gap-1" style="cursor:pointer" onclick="erToggle('<?= e($id) ?>')">
                <svg id="chev-<?= e($id) ?>" xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" style="transition:transform .15s;flex-shrink:0">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
                <span class="<?= $esRaiz ? 'fw-bold' : ($nivel === 1 ? 'fw-semibold' : 'text-muted') ?>" style="<?= $esRaiz ? 'font-size:1rem' : 'font-size:.85rem' ?>"><?= e($nodo['label']) ?></span>
            </span>
            <?php else: ?>
            <span class="<?= $esRaiz ? 'fw-bold' : ($nivel === 1 ? 'fw-semibold' : 'text-muted') ?>" style="padding-left:14px;<?= $esRaiz ? 'font-size:1rem' : 'font-size:.85rem' ?>"><?= e($nodo['label']) ?></span>
            <?php endif; ?>
        </td>
        <td class="text-end border-0 <?= $esRaiz ? 'fw-bold' : ($nivel === 1 ? 'fw-semibold' : 'text-muted') ?> <?= $color ? "text-$color" : '' ?>" style="<?= $esRaiz ? 'font-size:1rem' : 'font-size:.85rem' ?>">
            <?= moneda($nodo['monto']) ?>
            <?php if ($tienePagoTrack && $pendiente > 0.009): ?>
            <div class="text-muted fw-normal" style="font-size:.68rem;line-height:1.3">
                Pagado <?= moneda($pagado) ?> · <span class="text-warning fw-semibold">Falta <?= moneda($pendiente) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($pendienteLiq !== null && (float)$pendienteLiq > 0.009): ?>
            <div class="text-danger fw-normal" style="font-size:.68rem;line-height:1.3">
                + <?= moneda((float)$pendienteLiq) ?> sin liquidar a hoy (no incluido en el período)
            </div>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    foreach ($hijos as $hijo) {
        $hijo['color'] = $hijo['color'] ?? $color;
        _erArbol($hijo, $nivel + 1, $id);
    }
}

// Composición de egresos: valores ya desglosados por el controller (Servicios aparte de Costos Fijos)
$totalEgr = ($egresoFijo + $egresoVar + $egresoServicios + $egresoImp) ?: 1;

$pctFijos      = min(100, round($egresoFijo      / $totalEgr * 100, 1));
$pctVar        = min(100, round($egresoVar       / $totalEgr * 100, 1));
$pctServicios  = min(100, round($egresoServicios / $totalEgr * 100, 1));
$pctImp        = min(100, round($egresoImp       / $totalEgr * 100, 1));

$anioActual = date('Y');
$mesActual  = (int)date('m');
?>

<!-- ── Título ──────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Resumen Económico</h1>
        <small class="text-muted"><?= e($labelPeriodo) ?></small>
    </div>
    <a href="<?= url('gastos') ?>" class="btn btn-outline-secondary btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Registrar gasto
    </a>
</div>

<!-- ── Filtros ──────────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-body py-3">

        <div class="btn-group btn-group-sm mb-3" role="group">
            <button type="button" class="btn <?= $modo === 'mes'     ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchModo('mes')">Por mes</button>
            <button type="button" class="btn <?= $modo === 'anio'    ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchModo('anio')">Por año</button>
            <button type="button" class="btn <?= $modo === 'periodo' ? 'btn-primary' : 'btn-outline-secondary' ?>" onclick="switchModo('periodo')">Período libre</button>
        </div>

        <!-- Filtro: Mes -->
        <form id="formMes" method="get" action="<?= url('resumen-economico') ?>"
              class="row g-2 align-items-end <?= $modo !== 'mes' ? 'd-none' : '' ?>">
            <input type="hidden" name="modo" value="mes">
            <div class="col-auto">
                <label class="form-label small mb-1 d-block">Meses</label>
                <?php
                    $mesesSel   = $meses ?? [$mesActual];
                    $labSel     = array_map(fn($m) => mb_substr($mesesEs[$m - 1], 0, 3), $mesesSel);
                    $btnLabel   = count($labSel) === 1
                        ? $labSel[0]
                        : (count($labSel) . ' meses');
                ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                            id="dropdownMeses" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside" aria-expanded="false"
                            style="min-width:130px;text-align:left">
                        <span id="dropMesesLabel"><?= e($btnLabel) ?></span>
                    </button>
                    <ul class="dropdown-menu p-2" aria-labelledby="dropdownMeses" style="min-width:160px">
                        <?php foreach ($mesesEs as $i => $mn):
                            $num     = $i + 1;
                            $checked = in_array($num, $mesesSel, true);
                        ?>
                        <li>
                            <label class="dropdown-item d-flex align-items-center gap-2 py-1 px-2"
                                   style="cursor:pointer;font-size:.875rem">
                                <input type="checkbox" class="form-check-input mes-check m-0"
                                       name="mes[]" value="<?= $num ?>"
                                       <?= $checked ? 'checked' : '' ?>
                                       onchange="actualizarLabelMeses()">
                                <?= e($mn) ?>
                            </label>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li class="px-2 d-flex gap-1">
                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary"
                                    onclick="toggleTodosMeses(true)">Todos</button>
                            <span class="text-muted">·</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary"
                                    onclick="toggleTodosMeses(false)">Ninguno</button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Año</label>
                <select name="anio" class="form-select form-select-sm" style="width:100px">
                    <?php for ($y = $anioActual; $y >= $anioActual - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($anio ?? $anioActual) == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">Ver</button>
            </div>
        </form>

        <!-- Filtro: Año -->
        <form id="formAnio" method="get" action="<?= url('resumen-economico') ?>"
              class="row g-2 align-items-end <?= $modo !== 'anio' ? 'd-none' : '' ?>">
            <input type="hidden" name="modo" value="anio">
            <div class="col-auto">
                <label class="form-label small mb-1">Año</label>
                <select name="anio" class="form-select form-select-sm" style="width:100px">
                    <?php for ($y = $anioActual; $y >= $anioActual - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($anio ?? $anioActual) == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">Ver</button>
            </div>
        </form>

        <!-- Filtro: Período libre -->
        <form id="formPeriodo" method="get" action="<?= url('resumen-economico') ?>"
              class="row g-2 align-items-end <?= $modo !== 'periodo' ? 'd-none' : '' ?>">
            <input type="hidden" name="modo" value="periodo">
            <div class="col-auto">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary btn-sm">Ver</button>
            </div>
        </form>

    </div>
</div>

<!-- ── Deuda total a hoy (proveedores + sueldos), independiente del período filtrado ── -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h6 class="fw-semibold mb-1">Deuda total a hoy</h6>
                <div class="fs-4 fw-bold text-danger"><?= moneda($deudaTotalHoy) ?></div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#deudaTotalDesglose"
                    aria-expanded="false" aria-controls="deudaTotalDesglose">
                Ver composición
            </button>
        </div>
        <div class="collapse mt-3" id="deudaTotalDesglose">
            <table class="table table-sm mb-0 small">
                <tbody>
                    <tr>
                        <td>
                            Proveedores
                            <div class="text-muted" style="font-size:.72rem">Facturas y gastos pendientes de pago</div>
                        </td>
                        <td class="text-end align-middle">
                            <span class="fw-semibold"><?= moneda($deudaProveedores) ?></span>
                            <a href="<?= url('pagos-proveedores') ?>" class="ms-2 small">Ver detalle</a>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Sueldos
                            <div class="text-muted" style="font-size:.72rem">Pendiente de liquidar a los empleados</div>
                        </td>
                        <td class="text-end align-middle">
                            <span class="fw-semibold"><?= moneda($pendienteLiquidar) ?></span>
                            <a href="<?= url('reportes/nomina') ?>" class="ms-2 small">Ver detalle</a>
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold pt-2">Total</td>
                        <td class="text-end fw-bold text-danger pt-2"><?= moneda($deudaTotalHoy) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Estado de Resultados + Composición ──────────────────── -->
<div class="row g-3 mb-4">

    <!-- Tabla desglose -->
    <div class="col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent py-3">
                <h6 class="mb-0 fw-semibold">Estado de Resultados — <?= e($labelPeriodo) ?></h6>
            </div>
            <div class="card-body pt-0">
                <table class="table table-sm mb-0 small">
                    <tbody>
                        <?php _erArbol($arbolIngresos); ?>
                        <?php _erArbol($arbolEgresos); ?>
                        <tr class="border-top">
                            <td class="fw-bold pt-2 ps-0 <?= $positivo ? 'text-success' : 'text-danger' ?>">
                                Resultado Neto
                            </td>
                            <td class="text-end fw-bold pt-2 <?= $positivo ? 'text-success' : 'text-danger' ?>">
                                <?= ($positivo ? '+' : '') . moneda($resultadoNeto) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-0 pt-1 text-muted" style="font-size:.75rem">Margen neto (sobre ingresos operativos)</td>
                            <td class="text-end pt-1 fw-semibold <?= $positivo ? 'text-success' : 'text-danger' ?>" style="font-size:.75rem">
                                <?= ($positivo ? '+' : '') . $margen ?>%
                            </td>
                        </tr>

                        <!-- ── Aportes de socios: aparte, no cuenta como ingreso operativo ── -->
                        <?php if ($totalAportes > 0.009 || !$positivo): ?>
                        <?php
                        $hijosAportes = array_values(array_filter(array_map(
                            fn($a) => (float)$a['total'] > 0.009 ? ['id' => 'aporte_' . md5($a['socio']), 'label' => 'Aporte de ' . $a['socio'], 'monto' => (float)$a['total']] : null,
                            $aportesSocios
                        )));
                        _erArbol(['id' => 'aportes_socios', 'label' => 'Aportes de Socios (financiamiento, no ingreso operativo)', 'monto' => $totalAportes, 'color' => 'primary', 'hijos' => $hijosAportes]);
                        ?>
                        <?php if (!$positivo): ?>
                        <tr>
                            <td colspan="2" class="border-0 pt-1" style="font-size:.78rem">
                                <?php if ($totalAportes <= 0.009): ?>
                                <span class="text-danger">El período tuvo un déficit de <?= moneda($deficit) ?> sin aportes de socios registrados.</span>
                                <?php elseif ($coberturaPct >= 100): ?>
                                <span class="text-success">Los aportes de socios (<?= moneda($totalAportes) ?>) cubren la totalidad del déficit del período (<?= moneda($deficit) ?>).</span>
                                <?php else: ?>
                                <span class="text-warning">Los aportes de socios cubren el <?= $coberturaPct ?>% del déficit del período (<?= moneda($totalAportes) ?> de <?= moneda($deficit) ?>).</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Composición de egresos -->
    <div class="col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent py-3">
                <h6 class="mb-0 fw-semibold">Composición de egresos</h6>
            </div>
            <div class="card-body pt-2">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem">
                        <span class="text-muted">Costos Fijos</span>
                        <span class="fw-semibold"><?= $pctFijos ?>% <span class="text-muted fw-normal">(<?= moneda($egresoFijo) ?>)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-primary" style="width:<?= $pctFijos ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem">
                        <span class="text-muted">Costos Variables</span>
                        <span class="fw-semibold"><?= $pctVar ?>% <span class="text-muted fw-normal">(<?= moneda($egresoVar) ?>)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-success" style="width:<?= $pctVar ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem">
                        <span class="text-muted">Servicios</span>
                        <span class="fw-semibold"><?= $pctServicios ?>% <span class="text-muted fw-normal">(<?= moneda($egresoServicios) ?>)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-info" style="width:<?= $pctServicios ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem">
                        <span class="text-muted">Impuestos</span>
                        <span class="fw-semibold"><?= $pctImp ?>% <span class="text-muted fw-normal">(<?= moneda($egresoImp) ?>)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-danger" style="width:<?= $pctImp ?>%"></div>
                    </div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Total egresos</span>
                    <span class="fw-bold text-danger"><?= moneda($egresoFijo + $egresoVar + $egresoServicios + $egresoImp) ?></span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Límite de facturación — Monotributo ─────────────────── -->
<?php
$esAdminRE       = (Session::user()['rol'] ?? '') === 'admin';
$catMonoActual   = $configFiscal['categoria_monotributo'] ?? null;
// Sin cap: si ya superó el 100% necesitamos saberlo para el mensaje y el color, no solo
// para el ancho de la barra (antes se mostraba "100.0%" aunque en realidad fuera 137%).
$pctMonoUsado    = $limiteMonoEfectivo ? ($facturadoAfip12Meses / $limiteMonoEfectivo) * 100 : null;
$pctMonoBarra    = $pctMonoUsado !== null ? min(100, $pctMonoUsado) : null;
$colorMonoBarra  = 'bg-success';
if ($pctMonoUsado !== null) {
    if ($pctMonoUsado >= 95) $colorMonoBarra = 'bg-danger';
    elseif ($pctMonoUsado >= 80) $colorMonoBarra = 'bg-warning';
}
?>
<div class="card border-0 mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h6 class="fw-semibold mb-0">Límite de facturación — Monotributo (últimos 12 meses)</h6>
            <?php if ($esAdminRE): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0" for="selCategoriaMonoRE">Mi categoría:</label>
                <select id="selCategoriaMonoRE" class="form-select form-select-sm" style="width:auto">
                    <option value="">Sin definir</option>
                    <?php foreach (array_keys($categoriasMono) as $cat): ?>
                    <option value="<?= $cat ?>" <?= $catMonoActual === $cat ? 'selected' : '' ?>>Categoría <?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($limiteMonoEfectivo): ?>
        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.8rem">
            <span class="text-muted">Facturado por AFIP (móvil, últimos 12 meses)</span>
            <span class="fw-semibold d-flex align-items-center gap-1">
                <?= moneda($facturadoAfip12Meses) ?> / <span id="limiteMonoTexto"><?= moneda($limiteMonoEfectivo) ?></span>
                <span class="text-muted fw-normal">(<span id="pctMonoTexto"><?= number_format($pctMonoUsado, 1) ?></span>%)</span>
                <?php if ($esAdminRE): ?>
                <button type="button" id="btnEditarLimiteMono" class="btn btn-link btn-sm p-0 ms-1" title="Editar límite">✏️</button>
                <?php endif; ?>
            </span>
        </div>
        <div class="progress" style="height:8px">
            <div class="progress-bar <?= $colorMonoBarra ?>" id="barraMono" style="width:<?= $pctMonoBarra ?>%"></div>
        </div>
        <div class="text-muted mt-1" style="font-size:.7rem">
            Límite anual teórico de la categoría <?= e($catMonoActual) ?>: <?= moneda($limiteMonoAnual) ?>.
            <?php if ($limiteMonoManual !== null): ?>Estás usando un límite editado a mano (ver arriba).<?php endif; ?>
        </div>
        <?php if ($pctMonoUsado >= 80): ?>
        <div class="small mt-2 <?= $pctMonoUsado >= 95 ? 'text-danger' : 'text-warning' ?> fw-semibold">
            <?php if ($pctMonoUsado >= 100): ?>
            🔴 Superaste el límite de tu categoría por <?= moneda($facturadoAfip12Meses - $limiteMonoEfectivo) ?>. Consultá con tu contador sobre recategorizarte.
            <?php elseif ($pctMonoUsado >= 95): ?>
            ⚠️ Estás muy cerca del límite de tu categoría.
            <?php else: ?>
            ⚠️ Te estás acercando al límite de tu categoría.
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($esAdminRE): ?>
        <div id="wrapEditarLimiteMono" class="d-none mt-2 d-flex align-items-center gap-2">
            <input type="number" id="inputLimiteMono" class="form-control form-control-sm" style="max-width:200px" min="0.01" step="0.01"
                   value="<?= $limiteMonoManual !== null ? e($limiteMonoManual) : '' ?>" placeholder="<?= e($limiteMonoAnual) ?>">
            <button type="button" id="btnGuardarLimiteMono" class="btn btn-sm btn-primary">Guardar</button>
            <?php if ($limiteMonoManual !== null): ?>
            <button type="button" id="btnQuitarLimiteMono" class="btn btn-sm btn-outline-secondary">Usar el teórico</button>
            <?php endif; ?>
            <span class="small text-muted">Referencia (teórico): <?= moneda($limiteMonoAnual) ?></span>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="small text-muted">
            Elegí tu categoría de monotributo para ver cuánto facturaste por AFIP contra el límite anual.
            <?php if ($facturadoAfip12Meses > 0.009): ?>Llevás facturado <?= moneda($facturadoAfip12Meses) ?> por AFIP en los últimos 12 meses.<?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($esAdminRE): ?>
        <hr class="my-3">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="chkBloqueoAfip" <?= !empty($configFiscal['bloquear_facturacion_afip']) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-semibold" for="chkBloqueoAfip">
                Bloquear "Facturar por AFIP" en el Punto de Venta
            </label>
        </div>
        <div class="small text-muted mt-1" style="font-size:.72rem">
            Con esto activado, ningún cajero va a poder tildar "Facturar por AFIP" al cobrar — útil para no pasarte del límite por error mientras estás cerca.
        </div>

        <hr class="my-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#colapsoMesesMono">
            Verificar facturación por mes
        </button>
        <div class="collapse mt-2" id="colapsoMesesMono">
            <p class="small text-muted mb-2">Tildá los meses que quieras sumar para verificar el acumulado vos mismo.</p>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">Facturado en los meses tildados:</span>
                <span class="d-flex align-items-center gap-1">
                    <span class="fw-bold text-primary" id="sumaMesesMono">$ 0,00</span>
                    <input type="number" id="inputSumaMesesMono" class="form-control form-control-sm d-none" style="max-width:150px" min="0" step="0.01">
                    <button type="button" id="btnEditarSumaMeses" class="btn btn-link btn-sm p-0 ms-1" title="Editar (ej. facturación del sistema anterior que no figura acá)">✏️</button>
                </span>
            </div>
            <div class="small text-muted mb-1 d-none" id="avisoSumaManual" style="font-size:.7rem">
                Valor editado a mano. Si tildás o destildás un mes, se recalcula solo de nuevo.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small text-muted">Teórico de la categoría para esos <span id="cantMesesMono">0</span> mes(es):</span>
                <span class="small fw-semibold" id="teoricoMesesMono">$ 0,00</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted">Diferencia disponible (teórico − facturado):</span>
                <span class="small fw-bold" id="diferenciaMesesMono">$ 0,00</span>
            </div>
            <div class="row g-1 mb-2" style="max-height:260px;overflow-y:auto">
                <?php foreach ($facturadoAfipPorMes as $i => $m): ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input chk-mes-mono" type="checkbox" data-monto="<?= $m['total'] ?>"
                               id="chkMes<?= $m['ym'] ?>" <?= $i < 12 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="chkMes<?= $m['ym'] ?>">
                            <?= e($m['label']) ?> — <span class="text-muted"><?= moneda($m['total']) ?></span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="btnAgregarDiferenciaLimite" class="btn btn-sm btn-outline-primary">
                Agregar diferencia al límite de facturación
            </button>

            <hr class="my-3">
            <p class="small fw-semibold mb-1">Prorrateo diario (Punto de Venta)</p>
            <p class="small text-muted mb-2">
                Reparte la diferencia de arriba entre los días del período elegido — lo no facturado
                un día se acumula para los siguientes. El POS muestra "Podés facturar hoy: $X" en vez
                del acumulado de 12 meses mientras este período esté vigente.
            </p>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-auto">
                    <label class="form-label small mb-1" for="inputProrateoDesde">Desde</label>
                    <input type="date" id="inputProrateoDesde" class="form-control form-control-sm"
                           value="<?= e($configFiscal['prorateo_desde'] ?? '') ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1" for="inputProrateoHasta">Hasta (vencimiento)</label>
                    <input type="date" id="inputProrateoHasta" class="form-control form-control-sm"
                           value="<?= e($configFiscal['prorateo_hasta'] ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button type="button" id="btnGuardarProrateo" class="btn btn-sm btn-primary">
                        Usar esta diferencia como saldo a prorratear
                    </button>
                </div>
                <?php if ($configFiscal['saldo_prorateo_diario'] !== null): ?>
                <div class="col-auto">
                    <button type="button" id="btnQuitarProrateo" class="btn btn-sm btn-outline-secondary">Desactivar</button>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($configFiscal['saldo_prorateo_diario'] !== null && $configFiscal['prorateo_desde'] && $configFiscal['prorateo_hasta']): ?>
            <div class="small text-muted">
                Prorrateo diario activo: <?= moneda($configFiscal['saldo_prorateo_diario']) ?> entre
                <?= fecha($configFiscal['prorateo_desde']) ?> y <?= fecha($configFiscal['prorateo_hasta']) ?>.
                <?php if ($disponibleHoyProrateo !== null): ?>
                Podés facturar hoy: <strong><?= moneda($disponibleHoyProrateo) ?></strong>
                (quedan <?= (int)$diasRestantesProrateo ?> día<?= $diasRestantesProrateo == 1 ? '' : 's' ?> hasta el vencimiento) — mismo valor que ve el cajero en el POS.
                <?php else: ?>
                Este período ya venció o todavía no empezó.
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selCategoriaMonoRE')?.addEventListener('change', async function(){
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('categoria', this.value);
    const r = await fetch('<?= url('reportes/guardar-categoria-monotributo') ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo guardar.');
});

document.getElementById('chkBloqueoAfip')?.addEventListener('change', async function(){
    const chk = this;
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('bloqueado', chk.checked ? '1' : '0');
    try {
        const r = await fetch('<?= url('reportes/guardar-bloqueo-facturacion-afip') ?>', { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.ok) { chk.checked = !chk.checked; alert(d.error || 'No se pudo guardar.'); }
    } catch {
        chk.checked = !chk.checked;
        alert('Error de conexión.');
    }
});

// ── Editar a mano el límite de facturación (referencia: el teórico de la categoría) ──
document.getElementById('btnEditarLimiteMono')?.addEventListener('click', () => {
    document.getElementById('wrapEditarLimiteMono')?.classList.toggle('d-none');
});

async function _guardarLimiteMono(valor) {
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('limite', valor);
    try {
        const r = await fetch('<?= url('reportes/guardar-limite-facturacion-manual') ?>', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) location.reload();
        else alert(d.error || 'No se pudo guardar.');
    } catch {
        alert('Error de conexión.');
    }
}
document.getElementById('btnGuardarLimiteMono')?.addEventListener('click', () => {
    const v = document.getElementById('inputLimiteMono').value.trim();
    if (!v || parseFloat(v) <= 0) { alert('Ingresá un límite mayor a cero.'); return; }
    _guardarLimiteMono(v);
});
document.getElementById('btnQuitarLimiteMono')?.addEventListener('click', () => _guardarLimiteMono(''));

// ── Verificar facturación por mes: suma en vivo de los meses tildados,
// contra el teórico prorrateado de la categoría para esa misma cantidad de
// meses — la diferencia es lo que quedó "sin facturar" en esos meses y se
// puede sumar al límite editado a mano (ver botón más abajo).
const LIMITE_MONO_ANUAL = <?= $limiteMonoAnual ? (float)$limiteMonoAnual : 0 ?>;
let _diferenciaMesesMono  = 0;
let _facturadoMesesManual = null; // null = calculado solo de los meses tildados; número = editado a mano

function _fmtMoneda(n) {
    return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function _recalcularSumaMesesMono() {
    const tildados = Array.from(document.querySelectorAll('.chk-mes-mono')).filter(c => c.checked);
    const cantMeses = tildados.length;

    const facturado = _facturadoMesesManual !== null
        ? _facturadoMesesManual
        : tildados.reduce((acc, c) => acc + parseFloat(c.dataset.monto || '0'), 0);

    const teorico = LIMITE_MONO_ANUAL ? (LIMITE_MONO_ANUAL / 12) * cantMeses : 0;
    _diferenciaMesesMono = teorico - facturado;

    const elSuma = document.getElementById('sumaMesesMono');
    if (elSuma) elSuma.textContent = _fmtMoneda(facturado);

    const elCant = document.getElementById('cantMesesMono');
    if (elCant) elCant.textContent = cantMeses;

    const elTeorico = document.getElementById('teoricoMesesMono');
    if (elTeorico) elTeorico.textContent = _fmtMoneda(teorico);

    const elDif = document.getElementById('diferenciaMesesMono');
    if (elDif) {
        elDif.textContent = _fmtMoneda(_diferenciaMesesMono);
        elDif.className = 'small fw-bold ' + (_diferenciaMesesMono >= 0 ? 'text-success' : 'text-danger');
    }

    document.getElementById('avisoSumaManual')?.classList.toggle('d-none', _facturadoMesesManual === null);
}
document.querySelectorAll('.chk-mes-mono').forEach(c => c.addEventListener('change', () => {
    _facturadoMesesManual = null; // cambiar la selección de meses vuelve a calcular solo
    _recalcularSumaMesesMono();
}));
_recalcularSumaMesesMono();

// ── Editar a mano "Facturado en los meses tildados" (ej. facturación del
// sistema anterior, que no está cargada acá) ─────────────────────────────
const _spanSumaMeses  = document.getElementById('sumaMesesMono');
const _inputSumaMeses = document.getElementById('inputSumaMesesMono');

document.getElementById('btnEditarSumaMeses')?.addEventListener('click', () => {
    const actual = _facturadoMesesManual !== null
        ? _facturadoMesesManual
        : Array.from(document.querySelectorAll('.chk-mes-mono')).filter(c => c.checked)
              .reduce((acc, c) => acc + parseFloat(c.dataset.monto || '0'), 0);
    _inputSumaMeses.value = actual.toFixed(2);
    _spanSumaMeses.classList.add('d-none');
    _inputSumaMeses.classList.remove('d-none');
    _inputSumaMeses.focus();
    _inputSumaMeses.select();
});

function _confirmarSumaManual() {
    if (_inputSumaMeses.classList.contains('d-none')) return;
    const v = parseFloat(_inputSumaMeses.value);
    if (!isNaN(v) && v >= 0) {
        _facturadoMesesManual = v;
        _recalcularSumaMesesMono();
    }
    _inputSumaMeses.classList.add('d-none');
    _spanSumaMeses.classList.remove('d-none');
}
_inputSumaMeses?.addEventListener('blur', _confirmarSumaManual);
_inputSumaMeses?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); _confirmarSumaManual(); }
});

// ── Agregar la diferencia calculada al límite editado a mano ────────────────
document.getElementById('btnAgregarDiferenciaLimite')?.addEventListener('click', () => {
    const input = document.getElementById('inputLimiteMono');
    if (!input) return;
    const base = parseFloat(input.value) || LIMITE_MONO_ANUAL || 0;
    const nuevo = base + _diferenciaMesesMono;
    input.value = nuevo.toFixed(2);
    document.getElementById('wrapEditarLimiteMono')?.classList.remove('d-none');
    input.focus();
});

// ── Prorrateo diario (POS): guarda la diferencia calculada + el período ─────
async function _guardarProrateoDiario(saldo, desde, hasta) {
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('saldo', saldo);
    fd.append('desde', desde);
    fd.append('hasta', hasta);
    try {
        const r = await fetch('<?= url('reportes/guardar-prorateo-diario') ?>', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) location.reload();
        else alert(d.error || 'No se pudo guardar.');
    } catch {
        alert('Error de conexión.');
    }
}
document.getElementById('btnGuardarProrateo')?.addEventListener('click', () => {
    const desde = document.getElementById('inputProrateoDesde').value;
    const hasta = document.getElementById('inputProrateoHasta').value;
    if (!desde || !hasta) { alert('Completá las dos fechas (inicio y vencimiento).'); return; }
    if (desde > hasta) { alert('La fecha de inicio debe ser anterior (o igual) al vencimiento.'); return; }
    _guardarProrateoDiario(_diferenciaMesesMono.toFixed(2), desde, hasta);
});
document.getElementById('btnQuitarProrateo')?.addEventListener('click', () => _guardarProrateoDiario('', '', ''));
</script>

<!-- ── Otros Ingresos (registro manual) ────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent py-3"><h6 class="mb-0 fw-semibold">Otros ingresos — <?= e($labelPeriodo) ?></h6></div>
    <div class="card-body">
        <form id="formOtroIngreso" class="row g-2 align-items-end mb-3">
            <?= Csrf::field() ?>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Fecha</label>
                <input type="date" name="fecha" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Descripción</label>
                <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Ej: Venta de equipo usado" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Monto ($)</label>
                <input type="number" name="monto" class="form-control form-control-sm" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Notas</label>
                <input type="text" name="notas" class="form-control form-control-sm" placeholder="Opcional">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">+ Agregar</button>
            </div>
            <div id="otroIngresoError" class="col-12 d-none"><div class="alert alert-danger py-1 small mb-0"></div></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Descripción</th><th class="text-end">Monto</th><th>Registrado por</th><th style="width:36px"></th></tr>
                </thead>
                <tbody>
                    <?php if (!$otrosIngresos): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3 small">Sin otros ingresos registrados en este período</td></tr>
                    <?php endif; ?>
                    <?php foreach ($otrosIngresos as $o): ?>
                    <tr>
                        <td class="small"><?= fecha($o['fecha']) ?></td>
                        <td class="small"><?= e($o['descripcion']) ?></td>
                        <td class="text-end fw-semibold text-success"><?= moneda($o['monto']) ?></td>
                        <td class="small text-muted"><?= e($o['usuario_nombre'] ?? '—') ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarOtroIngreso(<?= $o['id'] ?>)" title="Eliminar">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const mesesNombres = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

function actualizarLabelMeses() {
    const checks  = document.querySelectorAll('.mes-check:checked');
    const label   = document.getElementById('dropMesesLabel');
    if (!label) return;
    const nums    = Array.from(checks).map(c => parseInt(c.value));
    nums.sort((a, b) => a - b);
    if (nums.length === 0)        label.textContent = 'Seleccioná';
    else if (nums.length === 1)   label.textContent = mesesNombres[nums[0] - 1];
    else                          label.textContent = nums.length + ' meses';
}

function toggleTodosMeses(marcar) {
    document.querySelectorAll('.mes-check').forEach(c => c.checked = marcar);
    actualizarLabelMeses();
}

function switchModo(modo) {
    document.getElementById('formMes').classList.add('d-none');
    document.getElementById('formAnio').classList.add('d-none');
    document.getElementById('formPeriodo').classList.add('d-none');
    document.getElementById('form' + modo.charAt(0).toUpperCase() + modo.slice(1)).classList.remove('d-none');
}

// ── Árbol Ingresos/Egresos: expandir/colapsar (soporta cualquier profundidad) ──
function erToggle(id) {
    const hijos = document.querySelectorAll(`tr[data-parent-id="${id}"]`);
    if (!hijos.length) return;
    const expandir = hijos[0].style.display === 'none';
    hijos.forEach(tr => tr.style.display = expandir ? '' : 'none');
    const chevron = document.getElementById('chev-' + id);
    if (chevron) chevron.style.transform = expandir ? 'rotate(90deg)' : '';
    if (!expandir) hijos.forEach(tr => erColapsarDescendientes(tr.dataset.nodeId));
}

function erColapsarDescendientes(id) {
    document.querySelectorAll(`tr[data-parent-id="${id}"]`).forEach(tr => {
        tr.style.display = 'none';
        erColapsarDescendientes(tr.dataset.nodeId);
    });
    const chevron = document.getElementById('chev-' + id);
    if (chevron) chevron.style.transform = '';
}

// ── Otros ingresos ──────────────────────────────────────────────────────────
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

document.getElementById('formOtroIngreso').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('otroIngresoError');
    errEl.classList.add('d-none');
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/resumen-economico/ingresos/agregar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else { errEl.querySelector('.alert').textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.querySelector('.alert').textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});

function eliminarOtroIngreso(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/resumen-economico/ingresos/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 600)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar este ingreso?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar este ingreso?')) ejecutar();
    }
}
</script>
