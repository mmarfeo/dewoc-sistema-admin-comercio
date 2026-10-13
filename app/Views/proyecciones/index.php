<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Proyecciones</h1>
</div>

<!-- Estado de la corrida + horizonte configurable -->
<div class="card border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="small text-muted">Última corrida del cron</div>
            <div class="fw-semibold">
                <?= $ultimaCorrida ? date('d/m/Y', strtotime($ultimaCorrida)) : 'Todavía no se generó ninguna proyección' ?>
            </div>
        </div>
        <?php if (canEdit('reportes_proyecciones')): ?>
        <form id="formHorizonte" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label fw-semibold small mb-1">Horizonte (días)</label>
                <input type="number" name="horizonte_dias" id="horizonteDias" class="form-control form-control-sm"
                       min="7" max="60" value="<?= (int)$config['horizonte_dias'] ?>" style="width:100px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
        </form>
        <?php else: ?>
        <div>
            <div class="small text-muted">Horizonte vigente</div>
            <div class="fw-semibold"><?= (int)$config['horizonte_dias'] ?> días</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="ti ti-info-circle mt-1"></i>
    <div class="small">
        Todo lo que ves acá es una <strong>estimación estadística</strong> (promedio móvil ponderado
        por día de semana, día del mes y feriados), no un cálculo exacto — sirve para anticipar, no
        para reemplazar el criterio del negocio. La <strong>tendencia de margen</strong> además usa el
        costo de receta de <strong>hoy</strong> aplicado a las ventas de meses pasados (no hay costo
        guardado al momento de cada venta), así que es una aproximación, no el margen histórico real.
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gráfico de ventas proyectadas -->
    <div class="col-lg-8">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Ventas proyectadas por día (todos los productos)</div>
            <div class="card-body"><canvas id="chartProyeccion" height="100"></canvas></div>
        </div>
    </div>
    <!-- Top productos proyectados -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Top productos proyectados</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    <?php foreach ($topProductos as $p): ?>
                    <tr>
                        <td>
                            <div><?= e($p['producto_nombre']) ?></div>
                            <div class="text-muted small">
                                <?php if (!$p['controla_stock']): ?>
                                    No controla stock
                                <?php else: ?>
                                    Stock: <?= number_format((float)$p['stock_actual'], 2, ',', '.') ?>
                                    <?= $p['dias_restante'] !== null ? ' — ~' . $p['dias_restante'] . ' día' . ($p['dias_restante'] === 1 ? '' : 's') : '' ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end fw-semibold"><?= number_format((float)$p['total'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$topProductos): ?>
                    <tr><td class="text-muted">Sin datos todavía.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Materias primas / insumos a reponer -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Materias primas / insumos a reponer pronto</div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ítem</th>
                    <th>Tipo</th>
                    <th class="text-end">Necesario en el horizonte</th>
                    <th class="text-end">Stock actual</th>
                    <th class="text-end">Días hasta quiebre</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($comprasMp as $c): ?>
            <tr>
                <td><?= e($c['nombre']) ?></td>
                <td><?= $c['entidad_tipo'] === 'insumo' ? 'Insumo' : 'Materia prima' ?></td>
                <td class="text-end"><?= number_format((float)$c['necesario_horizonte'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float)$c['stock_actual'], 2, ',', '.') ?></td>
                <td class="text-end"><?= $c['dias_hasta_quiebre'] !== null ? (int)$c['dias_hasta_quiebre'] : '—' ?></td>
                <td>
                    <?php if ($c['reponer']): ?>
                    <span class="badge bg-danger">Reponer</span>
                    <?php else: ?>
                    <span class="badge bg-success">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$comprasMp): ?>
            <tr><td colspan="6" class="text-muted">Sin datos todavía — corré el cron de Proyecciones.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tendencia de margen por producto -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold">Margen proyectado por producto (próximo período)</div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Producto</th><th class="text-end">Margen proyectado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($margenProducto as $m): ?>
            <tr>
                <td><?= e($m['producto_nombre']) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$m['margen_proyectado_pct'], 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$margenProducto): ?>
            <tr><td colspan="2" class="text-muted">Sin datos todavía.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = <?= json_encode(array_keys($totalPorFecha)) ?>;
    const totals  = <?= json_encode(array_values(array_map('floatval', $totalPorFecha))) ?>;

    if (labels.length) {
        new Chart(document.getElementById('chartProyeccion'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Cantidad proyectada',
                    data: totals,
                    borderColor: '#0d6efd',
                    backgroundColor: '#0d6efd22',
                    fill: true,
                    tension: 0.25,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
            }
        });
    }

    const formHorizonte = document.getElementById('formHorizonte');
    if (formHorizonte) {
        formHorizonte.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('horizonte_dias', document.getElementById('horizonteDias').value);
            fd.append('_csrf_token', '<?= Csrf::token() ?>');
            const r = await fetch('<?= url('proyecciones/guardar-config') ?>', { method: 'POST', body: fd });
            const d = await r.json();
            showToast(d.mensaje || d.error, d.ok ? 'success' : 'danger');
        });
    }
});
</script>
