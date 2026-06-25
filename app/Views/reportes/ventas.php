<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Ventas</h1>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        <i class="ti ti-printer me-1"></i> Imprimir
    </button>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Método de pago</label>
                <select name="metodo_pago" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['efectivo','debito','credito','transferencia','mercadopago'] as $mp): ?>
                    <option value="<?= $mp ?>" <?= $filtros['metodo_pago'] === $mp ? 'selected' : '' ?>><?= ucfirst($mp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label' => 'Tickets',        'valor' => number_format((int)($resumen['total_tickets'] ?? 0)),   'icon' => 'ti-receipt', 'color' => 'primary'],
        ['label' => 'Total vendido',  'valor' => '$' . number_format((float)($resumen['total_bruto'] ?? 0), 2, ',', '.'), 'icon' => 'ti-currency-dollar', 'color' => 'success'],
        ['label' => 'IVA total',      'valor' => '$' . number_format((float)($resumen['total_iva'] ?? 0), 2, ',', '.'),   'icon' => 'ti-percentage', 'color' => 'warning'],
        ['label' => 'Ticket promedio','valor' => '$' . number_format((float)($resumen['ticket_promedio'] ?? 0), 2, ',', '.'), 'icon' => 'ti-chart-bar', 'color' => 'info'],
    ];
    foreach ($kpis as $k):
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-<?= $k['color'] ?>-subtle text-<?= $k['color'] ?>">
                    <i class="ti <?= $k['icon'] ?> fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5"><?= $k['valor'] ?></div>
                    <div class="text-muted small"><?= $k['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Gráfico por día -->
    <div class="col-lg-8">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Ventas por día</div>
            <div class="card-body"><canvas id="chartDias" height="100"></canvas></div>
        </div>
    </div>
    <!-- Por método de pago -->
    <div class="col-lg-4">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Por método de pago</div>
            <div class="card-body">
                <?php
                $metodos = [
                    'Efectivo'      => $resumen['efectivo']      ?? 0,
                    'Débito'        => $resumen['debito']        ?? 0,
                    'Crédito'       => $resumen['credito']       ?? 0,
                    'Transferencia' => $resumen['transferencia'] ?? 0,
                    'Mercado Pago'  => $resumen['mercadopago']  ?? 0,
                ];
                $totalBruto = max(1, (float)($resumen['total_bruto'] ?? 1));
                foreach ($metodos as $nombre => $monto):
                    if ($monto <= 0) continue;
                    $pct = round($monto / $totalBruto * 100, 1);
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $nombre ?></span>
                        <span class="fw-semibold">$<?= number_format($monto, 2, ',', '.') ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top productos -->
<?php if ($top): ?>
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent fw-semibold">Top 10 productos más vendidos</div>
    <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($top as $i => $p): ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><?= e($p['nombre']) ?></td>
                <td class="text-end"><?= number_format((float)$p['cantidad_vendida'], 2, ',', '.') ?></td>
                <td class="text-end fw-semibold">$<?= number_format((float)$p['total_vendido'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Detalle -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Detalle de ventas</span>
        <span class="badge bg-secondary"><?= count($ventas) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <table id="tblVentas" class="table table-hover table-sm align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha/Hora</th>
                    <th>Cajero</th>
                    <th>Método de pago</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th class="text-end d-print-none">Ver</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ventas as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
                <td><?= e($v['cajero'] ?? '—') ?></td>
                <td><?= ucfirst(e($v['metodo_pago'])) ?></td>
                <td class="text-end">$<?= number_format((float)$v['total_iva'], 2, ',', '.') ?></td>
                <td class="text-end fw-semibold">$<?= number_format((float)$v['total_con_iva'], 2, ',', '.') ?></td>
                <td class="text-end d-print-none">
                    <a href="<?= url('ventas/ver?id=' . $v['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="ti ti-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblVentas', {
        language: DT_LANG_ES,
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        order: [[1, 'desc']],
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
    });

    // Gráfico de ventas por día
    const labels = <?= json_encode(array_column($porDia, 'fecha')) ?>;
    const totals  = <?= json_encode(array_map(fn($r) => (float)$r['total'], $porDia)) ?>;

    if (labels.length) {
        new Chart(document.getElementById('chartDias'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'Ventas $', data: totals, backgroundColor: '#0d6efd88', borderColor: '#0d6efd', borderWidth: 1 }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { ticks: { callback: v => '$' + v.toLocaleString('es-AR') } } },
            }
        });
    }
});
</script>
