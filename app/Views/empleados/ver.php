<?php $emp = $empleado; ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><?= e($emp['nombre']) ?></h1>
        <span class="badge <?= $emp['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?> mt-1">
            <?= $emp['activo'] ? 'Activo' : 'Inactivo' ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('pagos-proveedores/nuevo?empleado_id=' . $emp['id']) ?>" class="btn btn-success btn-sm">+ Registrar pago</a>
        <a href="<?= url('empleados/editar?id=' . $emp['id']) ?>" class="btn btn-outline-secondary btn-sm">Editar</a>
        <a href="<?= url('empleados') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<?php
$modalidadLabels = ['mensual' => 'Mensual', 'quincenal' => 'Quincenal', 'diario' => 'Por día', 'por_hora' => 'Por hora'];
$porHoraFijo      = $emp['modalidad_pago'] === 'por_hora' && (float)$emp['horas_mensuales'] > 0;
// "Por tiempo" = se carga jornada por jornada (diario, o por_hora sin horas mensuales fijas cargadas).
// por_hora con horas mensuales cargadas pasa a comportarse como un sueldo fijo (solo feriados).
$esPorTiempo      = $emp['modalidad_pago'] === 'diario' || ($emp['modalidad_pago'] === 'por_hora' && !$porHoraFijo);

$mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

/** Arma las semanas (lunes a domingo) de un mes: cada celda es el día (1..N) o null */
function _calGrillaMes(int $anio, int $mes): array
{
    $primerDia       = new DateTime(sprintf('%04d-%02d-01', $anio, $mes));
    $totalDias       = (int)$primerDia->format('t');
    $diaSemanaInicio = (int)$primerDia->format('N');

    $celdas = array_fill(0, $diaSemanaInicio - 1, null);
    for ($d = 1; $d <= $totalDias; $d++) $celdas[] = $d;
    while (count($celdas) % 7 !== 0) $celdas[] = null;

    return array_chunk($celdas, 7);
}

function _calMesUrl(int $empId, int $anio, int $mes): string
{
    return url('empleados/ver?id=' . $empId . '&anio=' . $anio . '&mes=' . $mes);
}
?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <h6 class="fw-semibold text-muted small text-uppercase mb-3">Datos del empleado</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Documento:</span> <span class="fw-semibold"><?= e($emp['documento'] ?? '—') ?></span></div>
                    <div class="col-6"><span class="text-muted">Puesto:</span> <span class="fw-semibold"><?= e($emp['puesto'] ?? '—') ?></span></div>
                    <div class="col-6"><span class="text-muted">Ingreso:</span> <span class="fw-semibold"><?= $emp['fecha_ingreso'] ? fecha($emp['fecha_ingreso']) : '—' ?></span></div>
                    <div class="col-6"><span class="text-muted">Modalidad:</span> <span class="badge bg-primary-soft text-primary"><?= $modalidadLabels[$emp['modalidad_pago']] ?? $emp['modalidad_pago'] ?></span></div>
                    <?php if (in_array($emp['modalidad_pago'], ['mensual','quincenal'], true)): ?>
                    <div class="col-6"><span class="text-muted">Sueldo mensual:</span> <span class="fw-semibold"><?= $emp['sueldo_mensual'] > 0 ? moneda($emp['sueldo_mensual']) : '—' ?></span></div>
                    <?php elseif ($emp['modalidad_pago'] === 'diario'): ?>
                    <div class="col-6"><span class="text-muted">Valor día:</span> <span class="fw-semibold"><?= moneda($emp['valor_dia']) ?></span></div>
                    <?php else: ?>
                    <div class="col-6"><span class="text-muted">Valor hora:</span> <span class="fw-semibold"><?= moneda($emp['valor_hora']) ?></span></div>
                    <?php if ($porHoraFijo): ?>
                    <div class="col-6"><span class="text-muted">Horas/mes:</span> <span class="fw-semibold"><?= number_format($emp['horas_mensuales'],2,',','.') ?></span></div>
                    <div class="col-12"><span class="text-muted">Valor mensual estimado:</span> <span class="fw-semibold text-primary"><?= moneda($emp['horas_mensuales'] * $emp['valor_hora']) ?></span></div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div class="col-6"><span class="text-muted">Recargo feriado:</span> <span class="fw-semibold"><?= number_format($emp['markup_feriado_pct'],0,',','.') ?>%</span></div>
                    <div class="col-6"><span class="text-muted">Teléfono:</span> <span class="fw-semibold"><?= e($emp['telefono'] ?? '—') ?></span></div>
                    <div class="col-6"><span class="text-muted">Email:</span> <span class="fw-semibold"><?= e($emp['email'] ?? '—') ?></span></div>
                    <?php if ($emp['notas']): ?>
                    <div class="col-12 mt-1"><span class="text-muted">Notas:</span> <?= nl2br(e($emp['notas'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                <p class="text-muted small mb-1">Total pagado en <?= date('Y') ?></p>
                <h5 class="fw-bold text-primary mb-0"><?= moneda($totalAnio) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                <p class="text-muted small mb-1">Pendiente de liquidar</p>
                <h5 class="fw-bold <?= $resumen['total_sugerido'] > 0 ? 'text-warning' : ($resumen['total_sugerido'] < 0 ? 'text-danger' : 'text-muted') ?> mb-0"><?= moneda($resumen['total_sugerido']) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                <p class="text-muted small mb-1">Debe horas (a recuperar)</p>
                <h5 class="fw-bold <?= $deudaHoras > 0 ? 'text-danger' : 'text-muted' ?> mb-0"><?= number_format($deudaHoras, 2, ',', '.') ?> hs</h5>
                <?php if ($deudaHoras > 0): ?>
                <p class="text-muted small mb-0">≈ <?= moneda($deudaMonto) ?></p>
                <?php endif; ?>
                <?php if ($deudaHoras > 0): ?>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="registrarHorasRecuperadas()">Registrar recuperación</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Calendario de asistencia ─────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="fw-semibold mb-0">Calendario de asistencia — <?= $mesesEs[$calMes] ?> <?= $calAnio ?></h6>
            <p class="text-muted small mb-0 mt-1">Hacé clic en un día para registrar feriado trabajado, horas extra o ausencia.</p>
        </div>
        <div class="d-flex gap-2">
            <?php
                $mesAnt = $calMes === 1 ? 12 : $calMes - 1; $anioAnt = $calMes === 1 ? $calAnio - 1 : $calAnio;
                $mesSig = $calMes === 12 ? 1 : $calMes + 1; $anioSig = $calMes === 12 ? $calAnio + 1 : $calAnio;
            ?>
            <a href="<?= _calMesUrl($emp['id'], $anioAnt, $mesAnt) ?>" class="btn btn-outline-secondary btn-sm">←</a>
            <a href="<?= url('empleados/ver?id=' . $emp['id']) ?>" class="btn btn-outline-secondary btn-sm">Hoy</a>
            <a href="<?= _calMesUrl($emp['id'], $anioSig, $mesSig) ?>" class="btn btn-outline-secondary btn-sm">→</a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-sm table-borderless mb-2 text-center" style="max-width:420px;margin:0 auto">
            <thead>
                <tr class="text-muted">
                    <th class="fw-normal">L</th><th class="fw-normal">M</th><th class="fw-normal">M</th>
                    <th class="fw-normal">J</th><th class="fw-normal">V</th><th class="fw-normal">S</th><th class="fw-normal">D</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (_calGrillaMes($calAnio, $calMes) as $semana): ?>
                <tr>
                    <?php foreach ($semana as $dia): ?>
                        <?php if ($dia === null): ?>
                        <td></td>
                        <?php else:
                            $fechaIso = sprintf('%04d-%02d-%02d', $calAnio, $calMes, $dia);
                            $reg      = $calendario[$fechaIso] ?? null;
                            $esFerGlobal = in_array($fechaIso, $feriadosFecha, true);
                            $clase = 'dia-cell';
                            if ($reg) $clase .= ' dia-' . $reg['tipo'];
                            elseif ($esFerGlobal) $clase .= ' dia-feriado-global';
                        ?>
                        <td>
                            <span class="<?= $clase ?>"
                                  data-fecha="<?= $fechaIso ?>"
                                  data-reg="<?= $reg ? e(json_encode($reg)) : '' ?>"
                                  title="<?= $reg ? e($reg['tipo']) : ($esFerGlobal ? 'Feriado (no trabajado)' : '') ?>"
                                  onclick="clickDia(this)">
                                <?= $dia ?>
                            </span>
                        </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="d-flex flex-wrap justify-content-center gap-3 small text-muted mt-2">
            <span><span class="leyenda-dot dia-normal"></span> Trabajado</span>
            <span><span class="leyenda-dot dia-feriado"></span> Feriado trabajado</span>
            <span><span class="leyenda-dot dia-extra"></span> Horas extra</span>
            <span><span class="leyenda-dot dia-ausencia_descuento"></span> Ausencia (descuento)</span>
            <span><span class="leyenda-dot dia-ausencia_recuperar"></span> Ausencia (a recuperar)</span>
            <span><span class="leyenda-dot dia-feriado-global"></span> Feriado (no trabajado)</span>
        </div>
    </div>
</div>

<!-- ── Jornadas / feriados trabajados ─────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0"><?= $esPorTiempo ? 'Jornadas trabajadas' : 'Feriados trabajados' ?></h6>
        <?php if (!$esPorTiempo): ?>
        <p class="text-muted small mb-0 mt-1">El sueldo fijo mensual ya cubre los días normales — acá solo se cargan los feriados trabajados, que se pagan como día adicional con recargo.</p>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form id="formJornada" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="empleado_id" value="<?= $emp['id'] ?>">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Fecha</label>
                <input type="date" name="fecha" id="jFecha" class="form-control form-control-sm" required>
            </div>
            <?php if ($emp['modalidad_pago'] === 'por_hora' && $esPorTiempo): ?>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Horas trabajadas</label>
                <input type="number" name="horas" class="form-control form-control-sm" min="0.5" step="0.5" required>
            </div>
            <?php endif; ?>
            <?php if ($esPorTiempo): ?>
            <div class="col-md-auto">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="jFeriado" name="es_feriado" value="1">
                    <label class="form-check-label small" for="jFeriado">Es feriado</label>
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="es_feriado" value="1">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Notas</label>
                <input type="text" name="notas" class="form-control form-control-sm" placeholder="Opcional">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">+ Agregar</button>
            </div>
            <div class="col-12">
                <span id="jFechaAviso" class="small text-warning d-none">⚠ Esta fecha no figura en el <a href="<?= url('feriados') ?>" target="_blank">calendario de feriados</a>.</span>
            </div>
            <div id="jornadaError" class="col-12 d-none"><div class="alert alert-danger py-1 small mb-0"></div></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th><?php if ($emp['modalidad_pago']==='por_hora' && $esPorTiempo): ?><th class="text-end">Horas</th><?php endif; ?>
                        <th>Feriado</th><th class="text-end">Monto</th><th>Notas</th><th>Estado</th><th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$jornadas): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3 small">Sin jornadas registradas</td></tr>
                    <?php endif; ?>
                    <?php foreach ($jornadas as $j): ?>
                    <tr class="<?= $j['pagado'] ? 'text-muted' : '' ?>">
                        <td class="small"><?= fecha($j['fecha']) ?></td>
                        <?php if ($emp['modalidad_pago']==='por_hora' && $esPorTiempo): ?><td class="text-end small"><?= number_format($j['horas'],2,',','.') ?></td><?php endif; ?>
                        <td><?= $j['es_feriado'] ? '<span class="badge bg-warning-soft text-warning">Feriado</span>' : '—' ?></td>
                        <td class="text-end fw-semibold"><?= moneda($j['monto']) ?></td>
                        <td class="small text-muted"><?= e($j['notas'] ?? '—') ?></td>
                        <td><span class="badge <?= $j['pagado'] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' ?> small"><?= $j['pagado'] ? 'Liquidado' : 'Pendiente' ?></span></td>
                        <td class="text-center">
                            <?php if (!$j['pagado']): ?>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarJornada(<?= $j['id'] ?>)" title="Eliminar">✕</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Ausencias ─────────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Ausencias</h6>
        <p class="text-muted small mb-0 mt-1">Se cargan desde el calendario de arriba, haciendo clic en el día que faltó.</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th class="text-end">Horas</th><th class="text-end">Monto / Pendiente</th><th>Notas</th><th>Estado</th><th style="width:36px"></th></tr>
                </thead>
                <tbody>
                    <?php if (!$ausencias): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3 small">Sin ausencias registradas</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ausencias as $a): ?>
                    <tr class="<?= $a['pagado'] ? 'text-muted' : '' ?>">
                        <td class="small"><?= fecha($a['fecha']) ?></td>
                        <td>
                            <?php if ($a['tipo'] === 'descuento'): ?>
                            <span class="badge bg-danger-soft text-danger">Descuento</span>
                            <?php else: ?>
                            <span class="badge bg-warning-soft text-warning">A recuperar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small"><?= number_format($a['horas'],2,',','.') ?></td>
                        <td class="text-end fw-semibold">
                            <?php if ($a['tipo'] === 'descuento'): ?>
                                -<?= moneda($a['monto_descuento']) ?>
                            <?php else: ?>
                                <?= number_format($a['horas_pendientes'],2,',','.') ?> hs pend.
                                <?php if ($a['horas_pendientes'] > 0 && $a['horas'] > 0): ?>
                                <div class="text-muted" style="font-size:.72rem">≈ <?= moneda($a['monto_equivalente'] * $a['horas_pendientes'] / $a['horas']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($a['notas'] ?? '—') ?></td>
                        <td>
                            <?php if ($a['tipo'] === 'descuento'): ?>
                            <span class="badge <?= $a['pagado'] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' ?> small"><?= $a['pagado'] ? 'Liquidado' : 'Pendiente' ?></span>
                            <?php else: ?>
                            <span class="badge <?= $a['horas_pendientes'] > 0 ? 'bg-warning-soft text-warning' : 'bg-success-soft text-success' ?> small"><?= $a['horas_pendientes'] > 0 ? 'Debe' : 'Saldada' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$a['pagado']): ?>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarDia(<?= $a['id'] ?>, 'ausencia')" title="Eliminar">✕</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Viáticos / premios / bonos ──────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="fw-semibold mb-0">Viáticos / Premios / Bonos</h6>
        <a href="<?= url('empleados/conceptos-tipos') ?>" class="small text-muted text-decoration-none" title="Administrar tipos de concepto">Administrar tipos</a>
    </div>
    <div class="card-body">
        <form id="formConcepto" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="empleado_id" value="<?= $emp['id'] ?>">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Fecha</label>
                <input type="date" name="fecha" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <?php foreach ($conceptosTipos as $ct): ?>
                    <option value="<?= e($ct['codigo']) ?>"><?= e($ct['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Descripción</label>
                <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Opcional">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Monto ($)</label>
                <input type="number" name="monto" class="form-control form-control-sm" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">+ Agregar</button>
            </div>
            <div id="conceptoError" class="col-12 d-none"><div class="alert alert-danger py-1 small mb-0"></div></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th class="text-end">Monto</th><th>Estado</th><th style="width:36px"></th></tr>
                </thead>
                <tbody>
                    <?php if (!$conceptos): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3 small">Sin conceptos registrados</td></tr>
                    <?php endif; ?>
                    <?php $tipoLabels = array_column($conceptosTipos, 'nombre', 'codigo'); ?>
                    <?php foreach ($conceptos as $c): ?>
                    <tr class="<?= $c['pagado'] ? 'text-muted' : '' ?>">
                        <td class="small"><?= fecha($c['fecha']) ?></td>
                        <td><span class="badge bg-info-soft text-info small"><?= $tipoLabels[$c['tipo']] ?? $c['tipo'] ?></span></td>
                        <td class="small"><?= e($c['descripcion'] ?? '—') ?></td>
                        <td class="text-end fw-semibold"><?= moneda($c['monto']) ?></td>
                        <td><span class="badge <?= $c['pagado'] ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' ?> small"><?= $c['pagado'] ? 'Liquidado' : 'Pendiente' ?></span></td>
                        <td class="text-center">
                            <?php if (!$c['pagado']): ?>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarConcepto(<?= $c['id'] ?>)" title="Eliminar">✕</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Historial de pagos</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblPagosEmpleado" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th><th>Concepto</th>
                        <th class="text-end">Total</th><th>Estado</th>
                        <th class="text-center" style="width:60px">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $p): ?>
                    <tr class="<?= $p['estado']==='anulado' ? 'text-muted' : '' ?>">
                        <td class="small"><?= fecha($p['fecha']) ?></td>
                        <td class="small"><?= e($p['concepto'] ?? '—') ?></td>
                        <td class="text-end fw-bold"><?= moneda($p['monto_total']) ?></td>
                        <td>
                            <?php $cls = $p['estado']==='anulado' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'; ?>
                            <span class="badge <?= $cls ?>"><?= ucfirst($p['estado']) ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('pagos-proveedores/ver?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal: registrar / ver día del calendario ────────────────────────── -->
<div class="modal fade" id="modalDia" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h6 class="modal-title" id="modalDiaTitulo">Registrar día</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="diaDetalle" class="d-none small"></div>
                <form id="formDia">
                    <input type="hidden" name="empleado_id" value="<?= $emp['id'] ?>">
                    <input type="hidden" name="fecha" id="diaFecha">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Fecha</label>
                        <input type="text" id="diaFechaLabel" class="form-control form-control-sm" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Tipo de registro</label>
                        <select name="tipo_registro" id="diaTipo" class="form-select form-select-sm">
                            <?php if ($esPorTiempo): ?><option value="normal">Día trabajado (horas normales)</option><?php endif; ?>
                            <option value="feriado">Feriado trabajado (se paga con recargo)</option>
                            <option value="extra">Horas extra</option>
                            <option value="ausencia_descuento">Ausencia — descontar del sueldo</option>
                            <option value="ausencia_recuperar">Ausencia — a recuperar (deuda de horas)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Horas <span class="text-muted fw-normal">(si corresponde)</span></label>
                        <input type="number" name="horas" id="diaHoras" class="form-control form-control-sm" min="0.5" step="0.5">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Notas</label>
                        <input type="text" name="notas" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div id="diaError" class="alert alert-danger py-1 small d-none mb-0"></div>
                </form>
            </div>
            <div class="modal-footer border-0" id="diaFooterNuevo">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnGuardarDia">Guardar</button>
            </div>
            <div class="modal-footer border-0 d-none" id="diaFooterExistente">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnEliminarDiaModal">Eliminar registro</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: registrar horas recuperadas ───────────────────────────────── -->
<div class="modal fade" id="modalRecuperar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h6 class="modal-title">Registrar horas recuperadas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Debe <strong><?= number_format($deudaHoras,2,',','.') ?> hs</strong> (≈ <?= moneda($deudaMonto) ?>). Se descuentan primero de la ausencia más antigua.</p>
                <form id="formRecuperar">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Horas recuperadas</label>
                        <input type="number" name="horas" id="recHoras" class="form-control form-control-sm" min="0.5" step="0.5" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Notas</label>
                        <input type="text" name="notas" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div id="recuperarError" class="alert alert-danger py-1 small d-none mb-0"></div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnGuardarRecuperar">Guardar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .dia-cell {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 50%; cursor: pointer; margin: 0 auto;
    }
    .dia-cell:hover { background: rgba(13,110,253,.12); }
    .dia-normal              { background: #198754; color: #fff; font-weight: 600; }
    .dia-feriado             { background: #0d6efd; color: #fff; font-weight: 600; }
    .dia-extra               { background: #6f42c1; color: #fff; font-weight: 600; }
    .dia-ausencia_descuento  { background: #dc3545; color: #fff; font-weight: 600; }
    .dia-ausencia_recuperar  { background: #fd7e14; color: #fff; font-weight: 600; }
    .dia-feriado-global      { border: 2px solid #dc3545; }
    .leyenda-dot {
        display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle;
    }
</style>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const FERIADOS   = new Set(<?= json_encode($feriadosFecha) ?>);

function formatMoney(v) {
    return '$ ' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2 });
}

// ── Autocompletar / avisar según el calendario de feriados ────────────────
document.getElementById('jFecha')?.addEventListener('change', function () {
    const esFeriado = FERIADOS.has(this.value);
    const chk   = document.getElementById('jFeriado');
    const aviso = document.getElementById('jFechaAviso');
    if (chk) chk.checked = esFeriado;
    aviso.classList.toggle('d-none', esFeriado || !this.value);
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined') {
        new DataTable('#tblPagosEmpleado', { order: [[0, 'desc']], language: { emptyTable: 'Sin pagos registrados para este empleado' } });
    }
});

// ── Jornadas ──────────────────────────────────────────────────────────────
document.getElementById('formJornada').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('jornadaError');
    errEl.classList.add('d-none');
    const fd = new FormData(this);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/empleados/jornadas/agregar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else { errEl.querySelector('.alert').textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.querySelector('.alert').textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});

function eliminarJornada(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/empleados/jornadas/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 600)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar esta jornada?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar esta jornada?')) ejecutar();
    }
}

// ── Conceptos (viáticos/premios/bonos) ─────────────────────────────────────
document.getElementById('formConcepto').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('conceptoError');
    errEl.classList.add('d-none');
    const fd = new FormData(this);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/empleados/conceptos/agregar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else { errEl.querySelector('.alert').textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.querySelector('.alert').textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});

function eliminarConcepto(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/empleados/conceptos/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 600)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar este concepto?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar este concepto?')) ejecutar();
    }
}

// ── Calendario de asistencia ────────────────────────────────────────────────

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
// (Bootstrap sí registra esa delegación de eventos al importarse).
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    let btn = el.querySelector('[data-bs-dismiss="modal"]');
    if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-dismiss', 'modal');
        el.appendChild(btn);
    }
    btn.click();
}

const TIPO_DIA_LABELS = {
    normal: 'Día trabajado', feriado: 'Feriado trabajado', extra: 'Horas extra',
    ausencia_descuento: 'Ausencia (descuento)', ausencia_recuperar: 'Ausencia (a recuperar)',
};

function clickDia(el) {
    const fecha = el.dataset.fecha;
    const reg   = el.dataset.reg ? JSON.parse(el.dataset.reg) : null;

    document.getElementById('diaFecha').value = fecha;
    document.getElementById('diaFechaLabel').value = fecha;
    document.getElementById('diaError').classList.add('d-none');

    const detalle = document.getElementById('diaDetalle');

    if (reg) {
        document.getElementById('modalDiaTitulo').textContent = 'Detalle del día';
        document.getElementById('formDia').classList.add('d-none');
        detalle.classList.remove('d-none');
        let extra = '';
        const labelMonto = reg.tipo === 'ausencia_recuperar' ? 'Equivalente en $' : 'Monto';
        if (reg.monto !== null && reg.monto !== undefined) extra = `<div>${labelMonto}: <strong>${formatMoney(reg.monto)}</strong></div>`;
        if (reg.horas_pendientes !== undefined && reg.horas_pendientes !== null) extra += `<div>Horas pendientes: <strong>${reg.horas_pendientes}</strong></div>`;
        detalle.innerHTML = `
            <div class="mb-2">
                <div>Fecha: <strong>${fecha}</strong></div>
                <div>Tipo: <strong>${TIPO_DIA_LABELS[reg.tipo] || reg.tipo}</strong></div>
                <div>Horas: <strong>${reg.horas}</strong></div>
                ${extra}
                <div>Estado: <strong>${reg.pagado == 1 ? 'Liquidado' : 'Pendiente'}</strong></div>
            </div>`;
        document.getElementById('diaFooterNuevo').classList.add('d-none');
        document.getElementById('diaFooterExistente').classList.remove('d-none');
        document.getElementById('btnEliminarDiaModal').disabled = reg.pagado == 1;
        document.getElementById('btnEliminarDiaModal').onclick = () => eliminarDia(reg.id, reg.origen);
    } else {
        document.getElementById('modalDiaTitulo').textContent = 'Registrar día — ' + fecha;
        document.getElementById('formDia').classList.remove('d-none');
        detalle.classList.add('d-none');
        document.getElementById('formDia').reset();
        document.getElementById('diaFecha').value = fecha;
        document.getElementById('diaFechaLabel').value = fecha;
        document.getElementById('diaTipo').value = FERIADOS.has(fecha) ? 'feriado' : (document.getElementById('diaTipo').querySelector('option[value="normal"]') ? 'normal' : 'feriado');
        document.getElementById('diaFooterNuevo').classList.remove('d-none');
        document.getElementById('diaFooterExistente').classList.add('d-none');
    }

    _showModal('modalDia');
}

document.getElementById('btnGuardarDia').addEventListener('click', async function () {
    const errEl = document.getElementById('diaError');
    errEl.classList.add('d-none');
    const fd = new FormData(document.getElementById('formDia'));
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/empleados/dia/guardar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});

function eliminarDia(id, origen, fromModal) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('origen', origen);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/empleados/dia/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar este registro?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar este registro?')) ejecutar();
    }
}

function registrarHorasRecuperadas() {
    document.getElementById('formRecuperar').reset();
    document.getElementById('recuperarError').classList.add('d-none');
    _showModal('modalRecuperar');
}

document.getElementById('btnGuardarRecuperar').addEventListener('click', async function () {
    const errEl = document.getElementById('recuperarError');
    errEl.classList.add('d-none');
    const fd = new FormData(document.getElementById('formRecuperar'));
    fd.append('empleado_id', '<?= $emp['id'] ?>');
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/empleados/horas/recuperar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
        else { errEl.textContent = d.error; errEl.classList.remove('d-none'); }
    } catch { errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none'); }
});
</script>
