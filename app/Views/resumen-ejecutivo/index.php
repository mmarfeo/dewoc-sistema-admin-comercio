<?php
/**
 * Resumen Ejecutivo — ver ResumenEjecutivoController. Cada sección se pinta solo si el
 * controlador la incluyó (es decir, si el usuario tiene permiso de ver ese módulo
 * puntual); si no queda ninguna, se muestra el estado vacío del final.
 */
$hayAlgo = ($semanal !== false) || ($margenProducto !== false) || ($comprasPagosUsuario !== false)
    || ($cuentasCorrientes !== false)
    || $listaCompras !== null || $vencimientos !== null || $proyeccionAReponer !== null;
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Resumen Ejecutivo</h1>
        <p class="text-muted small mb-0">
            Lo último de cada reporte automático, en un solo lugar. No genera avisos nuevos —
            los push/notificaciones/Chat IA de siempre siguen llegando igual; esto es para
            revisar todo de un vistazo cuando vos quieras, sin tener que juntar cada aviso suelto.
        </p>
    </div>
</div>

<?php if (!$hayAlgo): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-5">
        <div class="mb-2" style="font-size:2rem">📋</div>
        Todavía no hay ningún reporte automático generado, o tu usuario no tiene permiso de ver ninguno de ellos.
    </div>
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Resumen semanal ──────────────────────────────────────────────── -->
    <?php if ($semanal !== false): ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">📊 Resumen semanal</h6>
                <?php if ($semanal): ?>
                <span class="text-muted small"><?= e($semanal['descripcion']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body pt-2">
                <?php if (!$semanal): ?>
                <p class="text-muted small mb-0">Todavía no se generó ninguno — se arma solo los lunes (<code>cron/reporte_semanal.php</code>).</p>
                <?php else: ?>
                <div class="row row-cols-3 g-2 mb-2">
                    <?php foreach ($semanal['resultado'] as $fila): ?>
                    <div class="col">
                        <div class="text-muted" style="font-size:.72rem"><?= e($fila['Métrica']) ?></div>
                        <div class="fw-semibold"><?= e($fila['Valor']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= url('reportes-ia/ver?id=' . (int) $semanal['id']) ?>" class="small">Ver detalle completo →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Margen por producto ──────────────────────────────────────────── -->
    <?php if ($margenProducto !== false): ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">💹 Margen por producto</h6>
                <?php if ($margenProducto): ?>
                <span class="text-muted small"><?= e($margenProducto['descripcion']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body pt-2">
                <?php if (!$margenProducto): ?>
                <p class="text-muted small mb-0">Todavía no se generó ninguno — se arma solo los lunes (<code>cron/reporte_margen_producto.php</code>).</p>
                <?php else: ?>
                <table class="table table-sm mb-2">
                    <tbody>
                        <?php foreach (array_slice($margenProducto['resultado'], 0, 3) as $fila): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($fila['Producto']) ?></td>
                            <td class="text-end">$<?= e($fila['Ganancia']) ?></td>
                            <td class="text-end text-muted small" style="width:70px"><?= e($fila['Margen %']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="<?= url('reportes-ia/ver?id=' . (int) $margenProducto['id']) ?>" class="small">Ver los <?= count($margenProducto['resultado']) ?> productos →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Auditoría: compras y pagos por usuario ───────────────────────── -->
    <?php if ($comprasPagosUsuario !== false): ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0">🧾 Compras y pagos por usuario</h6>
            </div>
            <div class="card-body pt-2">
                <?php if (!$comprasPagosUsuario): ?>
                <p class="text-muted small mb-0">Todavía no se generó ninguno — se arma solo cada ~4 días (<code>cron/reporte_compras_pagos_usuario.php</code>).</p>
                <?php else: ?>
                <p class="text-muted small mb-1"><?= e($comprasPagosUsuario['descripcion']) ?></p>
                <h4 class="fw-bold mb-1"><?= moneda($comprasPagosTotal) ?></h4>
                <p class="text-muted small mb-2"><?= count($comprasPagosUsuario['resultado']) ?> registro(s) — ver quién cargó qué.</p>
                <a href="<?= url('reportes-ia/ver?id=' . (int) $comprasPagosUsuario['id']) ?>" class="small">Ver detalle completo →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Cuentas corrientes (proveedores/clientes/socios) ─────────────── -->
    <?php if ($cuentasCorrientes !== false):
        $ccSaldoTotal = 0.0;
        if ($cuentasCorrientes) {
            foreach ($cuentasCorrientes['resultado'] as $fila) {
                if ($fila['Métrica'] === 'Saldo total pendiente') {
                    $ccSaldoTotal = (float) str_replace(['$', '.'], '', $fila['Valor']);
                }
            }
        }
        $ccHaySaldo = $ccSaldoTotal > 0.009;
    ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">💰 Cuentas corrientes</h6>
                <?php if ($cuentasCorrientes): ?>
                <span class="small <?= $ccHaySaldo ? 'text-danger fw-semibold' : 'text-success' ?>">
                    <?= $ccHaySaldo ? 'Con saldos pendientes' : 'Todo al día' ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body pt-2">
                <?php if (!$cuentasCorrientes): ?>
                <p class="text-muted small mb-0">Todavía no se generó ninguno — se arma solo los lunes (<code>cron/cuentas_corrientes_semanal.php</code>).</p>
                <?php else: ?>
                <table class="table table-sm mb-2">
                    <tbody>
                        <?php foreach ($cuentasCorrientes['resultado'] as $fila): ?>
                        <tr>
                            <td class="small"><?= e($fila['Métrica']) ?></td>
                            <td class="text-end fw-semibold small"><?= e($fila['Valor']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="<?= url('reportes-ia/ver?id=' . (int) $cuentasCorrientes['id']) ?>" class="small">Ver detalle completo →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Lista de compras (en vivo) ────────────────────────────────────── -->
    <?php if ($listaCompras !== null): ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">🛒 Lista de compras</h6>
                <span class="text-muted small"><?= count($listaCompras) ?> ítem(s)</span>
            </div>
            <div class="card-body pt-2">
                <?php if (!$listaCompras): ?>
                <p class="text-muted small mb-0">Nada pendiente de comprar por ahora.</p>
                <?php else: ?>
                <table class="table table-sm mb-2">
                    <tbody>
                        <?php foreach (array_slice($listaCompras, 0, 5) as $it): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                            <td class="text-muted small">
                                <?= $it['dias_stock_restante'] !== null ? 'quedan ~' . number_format((float) $it['dias_stock_restante'], 1, ',', '.') . ' días' : 'bajo el mínimo' ?>
                            </td>
                            <td class="text-end" style="width:100px">
                                <span class="badge <?= $it['estado'] === 'sugerido' ? 'bg-info-soft text-info' : 'bg-warning-soft text-warning' ?> small">
                                    <?= $it['estado'] === 'sugerido' ? 'Sugerido' : 'Pendiente' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($listaCompras) > 5): ?>
                <p class="text-muted small mb-2">…y <?= count($listaCompras) - 5 ?> más.</p>
                <?php endif; ?>
                <a href="<?= url('lista-compras') ?>" class="small">Ver lista completa →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Vencimientos de gastos (en vivo) ─────────────────────────────── -->
    <?php if ($vencimientos !== null):
        $totalSaldo = array_sum(array_column($vencimientos, 'saldo'));
    ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">💳 Vencimientos de gastos</h6>
                <?php if ($vencimientos): ?>
                <span class="text-muted small"><?= moneda($totalSaldo) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body pt-2">
                <?php if (!$vencimientos): ?>
                <p class="text-muted small mb-0">Nada vencido ni por vencer en los próximos 7 días.</p>
                <?php else: ?>
                <table class="table table-sm mb-2">
                    <tbody>
                        <?php foreach (array_slice($vencimientos, 0, 5) as $it):
                            $dias = (int) $it['dias_para_vencer'];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($it['entidad']) ?></div>
                                <div class="text-muted small"><?= e($it['descripcion']) ?></div>
                            </td>
                            <td class="text-end"><?= moneda((float) $it['saldo']) ?></td>
                            <td class="text-end small" style="width:130px">
                                <span class="<?= $dias < 0 ? 'text-danger' : ($dias <= 1 ? 'text-warning' : 'text-muted') ?>">
                                    <?= $dias < 0 ? 'venció hace ' . abs($dias) . 'd' : ($dias === 0 ? 'vence hoy' : 'vence en ' . $dias . 'd') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($vencimientos) > 5): ?>
                <p class="text-muted small mb-2">…y <?= count($vencimientos) - 5 ?> más.</p>
                <?php endif; ?>
                <a href="<?= url('gastos') ?>" class="small">Ver y pagar →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Proyecciones (en vivo) ────────────────────────────────────────── -->
    <?php if ($proyeccionAReponer !== null): ?>
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-0 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">🔮 Proyecciones</h6>
                <?php if ($proyeccionFecha): ?>
                <span class="text-muted small">Última corrida: <?= fecha($proyeccionFecha) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body pt-2">
                <?php if (!$proyeccionFecha): ?>
                <p class="text-muted small mb-0">Todavía no corrió — se genera solo todos los días (<code>cron/proyecciones_generar.php</code>).</p>
                <?php elseif (!$proyeccionAReponer): ?>
                <p class="text-muted small mb-0">Según la proyección, nada necesita reponerse dentro del horizonte configurado.</p>
                <?php else: ?>
                <p class="text-muted small mb-2"><?= count($proyeccionAReponer) ?> ítem(s) de materia prima/insumo por debajo de lo que hace falta para el horizonte proyectado:</p>
                <table class="table table-sm mb-2">
                    <tbody>
                        <?php foreach (array_slice($proyeccionAReponer, 0, 5) as $it): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                            <td class="text-end text-muted small">
                                <?= $it['dias_hasta_quiebre'] !== null ? '~' . $it['dias_hasta_quiebre'] . 'd de stock' : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($proyeccionAReponer) > 5): ?>
                <p class="text-muted small mb-2">…y <?= count($proyeccionAReponer) - 5 ?> más.</p>
                <?php endif; ?>
                <a href="<?= url('proyecciones') ?>" class="small">Ver proyecciones →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
