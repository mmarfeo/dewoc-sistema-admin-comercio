<!-- ── Título ────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Dashboard</h1>
    <small class="text-muted"><?= date('l d \d\e F \d\e Y') ?></small>
</div>

<!-- ── KPIs del día ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-uppercase text-body-secondary fw-semibold small mb-1">
                        <span class="legend-circle-sm bg-success me-1"></span>Ventas hoy
                    </p>
                    <h2 class="mb-0 fw-bold"><?= moneda($kpiVentas['total']) ?></h2>
                    <small class="text-muted"><?= $kpiVentas['cantidad'] ?> ticket(s)</small>
                </div>
                <span class="text-success fs-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-uppercase text-body-secondary fw-semibold small mb-1">
                        <span class="legend-circle-sm bg-primary me-1"></span>Ticket promedio
                    </p>
                    <h2 class="mb-0 fw-bold"><?= moneda($ticketProm) ?></h2>
                    <small class="text-muted">Promedio del día</small>
                </div>
                <span class="text-primary fs-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-uppercase text-body-secondary fw-semibold small mb-1">
                        <span class="legend-circle-sm bg-danger me-1"></span>Stock bajo
                    </p>
                    <h2 class="mb-0 fw-bold"><?= count($stockBajo) ?></h2>
                    <small class="text-muted">Ítems bajo mínimo</small>
                </div>
                <span class="text-danger fs-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-uppercase text-body-secondary fw-semibold small mb-1">
                        <span class="legend-circle-sm <?= $turnoActivo ? 'bg-success' : 'bg-secondary' ?> me-1"></span>Caja
                    </p>
                    <h2 class="mb-0 fw-bold"><?= $turnoActivo ? 'Abierta' : 'Cerrada' ?></h2>
                    <small class="text-muted"><?= $turnoActivo ? e($turnoActivo['cajero']) : 'Sin turno activo' ?></small>
                </div>
                <span class="<?= $turnoActivo ? 'text-success' : 'text-secondary' ?> fs-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ── Gráfico de ventas + Top productos ─────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Gráfico últimos 7 días -->
    <div class="col-lg-8">
        <div class="card border-0 h-100">
            <div class="card-header border-0 d-flex justify-content-between align-items-center bg-transparent">
                <h6 class="mb-0 fw-semibold">Ventas — últimos 7 días</h6>
            </div>
            <div class="card-body">
                <canvas id="chartVentas" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Top 5 productos -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="mb-0 fw-semibold">Top 5 productos del mes</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top5)): ?>
                    <p class="text-muted text-center py-4 small">Sin ventas este mes</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top5 as $i => $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center border-0 py-2 px-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary rounded-pill"><?= $i + 1 ?></span>
                                <span class="small fw-semibold"><?= e($p['nombre']) ?></span>
                            </div>
                            <div class="text-end">
                                <div class="small fw-bold"><?= moneda($p['monto_total']) ?></div>
                                <div class="text-muted" style="font-size:.7rem"><?= number_format($p['cant_total'], 0, ',', '.') ?> u.</div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Stock bajo + Últimas ventas ───────────────────────────── -->
<div class="row g-3">

    <!-- Stock bajo -->
    <?php if (!empty($stockBajo)): ?>
    <div class="col-lg-4">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold text-danger">⚠ Stock bajo</h6>
                <a href="<?= url('stock') ?>" class="small">Ver todo</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($stockBajo as $item): ?>
                    <li class="list-group-item border-0 py-2 px-3 d-flex justify-content-between">
                        <div>
                            <div class="small fw-semibold"><?= e($item['nombre']) ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?= ucfirst(str_replace('_',' ',$item['tipo'])) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger-soft text-danger small"><?= number_format($item['stock_actual'], 2) ?></span>
                            <div class="text-muted" style="font-size:.65rem">mín: <?= number_format($item['stock_minimo'], 2) ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimas ventas -->
    <div class="col-lg-<?= !empty($stockBajo) ? '8' : '12' ?>">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold">Últimas ventas</h6>
                <a href="<?= url('ventas') ?>" class="small">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Total</th>
                                <th>Método pago</th>
                                <th>AFIP</th>
                                <th>Cajero</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimasVentas)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin ventas hoy</td></tr>
                            <?php else: ?>
                            <?php foreach ($ultimasVentas as $v): ?>
                            <tr>
                                <td><a href="<?= url('ventas/ver?id=' . $v['id']) ?>">#<?= $v['id'] ?></a></td>
                                <td class="fw-semibold"><?= moneda($v['total']) ?></td>
                                <td><?= e(ucfirst(str_replace('_', ' ', $v['metodo_pago']))) ?></td>
                                <td>
                                    <?php if ($v['estado_afip'] === 'facturada'): ?>
                                        <span class="badge bg-success-soft text-success">AFIP</span>
                                    <?php elseif ($v['estado_afip'] === 'ticket_interno'): ?>
                                        <span class="badge bg-secondary-soft text-secondary">Ticket</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-soft text-warning">Sin factura</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($v['cajero']) ?></td>
                                <td class="text-muted"><?= date('H:i', strtotime($v['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart.js ──────────────────────────────────────────────── -->
<?php
$dias   = array_map(fn($r) => date('d/m', strtotime($r['dia'])), $ventasSemana);
$totals = array_map(fn($r) => round($r['total'], 2), $ventasSemana);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartVentas'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dias) ?>,
        datasets: [{
            label: 'Ventas ($)',
            data:  <?= json_encode($totals) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '$ ' + v.toLocaleString('es-AR') } }
        }
    }
});
</script>
