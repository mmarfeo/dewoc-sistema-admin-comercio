<?php
$_exportQ = http_build_query(array_filter([
    'tipo'        => 'ventas',
    'desde'       => $desde,
    'hasta'       => $hasta,
    'metodo_pago' => $filtros['metodo_pago'] ?: null,
    'usuario_id'  => $filtros['usuario_id']  ?: null,
], fn($v) => $v !== null && $v !== ''));
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Reporte de Ventas</h1>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes/exportar?' . $_exportQ) ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-table-export me-1"></i> Exportar CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
    </div>
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

<!-- Límite de Monotributo -->
<?php
$catActual = $configFiscal['categoria_monotributo'] ?? null;
$limiteActual = $catActual ? ($categoriasMono[$catActual] ?? null) : null;
// Sin cap: si ya superó el 100% necesitamos saberlo para el mensaje y el color, no solo
// para el ancho de la barra (antes se mostraba "100.0%" aunque en realidad fuera 137%).
$pctUsado = $limiteActual ? ($facturado12Meses / $limiteActual) * 100 : null;
$pctBarra = $pctUsado !== null ? min(100, $pctUsado) : null;
$colorBarra = 'bg-success';
if ($pctUsado !== null) {
    if ($pctUsado >= 95) $colorBarra = 'bg-danger';
    elseif ($pctUsado >= 80) $colorBarra = 'bg-warning';
}
?>
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h6 class="fw-semibold mb-0">Límite de facturación — Monotributo</h6>
            <?php if ((Session::user()['rol'] ?? '') === 'admin'): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0" for="selCategoriaMono">Mi categoría:</label>
                <select id="selCategoriaMono" class="form-select form-select-sm" style="width:auto">
                    <option value="">Sin definir</option>
                    <?php foreach (array_keys($categoriasMono) as $cat): ?>
                    <option value="<?= $cat ?>" <?= $catActual === $cat ? 'selected' : '' ?>>Categoría <?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($limiteActual): ?>
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Facturado por AFIP (últimos 12 meses)</span>
            <span class="fw-semibold"><?= moneda($facturado12Meses) ?> / <?= moneda($limiteActual) ?> (<?= number_format($pctUsado, 1) ?>%)</span>
        </div>
        <div class="progress" style="height:10px">
            <div class="progress-bar <?= $colorBarra ?>" style="width:<?= $pctBarra ?>%"></div>
        </div>
        <?php if ($pctUsado >= 80): ?>
        <div class="small mt-2 <?= $pctUsado >= 95 ? 'text-danger' : 'text-warning' ?> fw-semibold">
            <?php if ($pctUsado >= 100): ?>
            🔴 Superaste el límite anual de tu categoría por <?= moneda($facturado12Meses - $limiteActual) ?>. Consultá con tu contador sobre recategorizarte.
            <?php elseif ($pctUsado >= 95): ?>
            ⚠️ Estás muy cerca del límite anual de tu categoría.
            <?php else: ?>
            ⚠️ Te estás acercando al límite anual de tu categoría.
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="small text-muted">Elegí tu categoría de monotributo para ver cuánto llevás facturado contra tu límite anual.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Los precios cargados incluyen IVA (decisión D9 — ver core/PrecioHelper.php) -->
<?php if ((Session::user()['rol'] ?? '') === 'admin'): ?>
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <h6 class="fw-semibold mb-1">¿Los precios de venta cargados incluyen IVA?</h6>
        <p class="small text-muted mb-3">
            Define cómo se interpreta el precio de cada producto (<code>precio_venta</code> y
            <code>precio_online</code>). Es independiente de tu condición fiscal, que define si el
            comprobante <strong>discrimina</strong> el IVA
            (<?= $discriminaIva ? 'hoy: Responsable Inscripto, se discrimina' : 'hoy: no se discrimina' ?>).
        </p>

        <?php if ($preciosIvaSinConfigurar): ?>
        <div class="alert alert-info py-2 small">
            Todavía no está configurado: por ahora se usa el comportamiento histórico
            (<strong><?= $preciosIncluyenIva ? 'incluyen IVA' : 'no incluyen IVA' ?></strong>),
            que es el que ya venía aplicando el POS. Queda igual hasta que guardes una opción acá.
        </div>
        <?php endif; ?>

        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="preciosIva" id="radioIvaIncluido" value="1"
                   <?= $preciosIncluyenIva ? 'checked' : '' ?>>
            <label class="form-check-label" for="radioIvaIncluido">
                Los precios de venta cargados <strong>incluyen IVA</strong>
                <span class="d-block text-muted small">El precio que cargás es el precio final que paga el cliente.</span>
            </label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="preciosIva" id="radioIvaSumado" value="0"
                   <?= $preciosIncluyenIva ? '' : 'checked' ?>>
            <label class="form-check-label" for="radioIvaSumado">
                <strong>No incluyen IVA</strong>: se suma al vender
                <span class="d-block text-muted small">El precio que cargás es el neto; el IVA del producto se le agrega.</span>
            </label>
        </div>

        <button type="button" class="btn btn-primary btn-sm" id="btnGuardarPreciosIva">Guardar</button>
        <div class="small text-warning fw-semibold mt-2">
            ⚠️ Cambia el precio que pagan tus clientes en el local y en el menú online. Revisá tus precios.
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('btnGuardarPreciosIva')?.addEventListener('click', async function(){
    const elegido = document.querySelector('input[name="preciosIva"]:checked')?.value;
    if (elegido === undefined) return;
    if (!confirm('Esto cambia el precio que pagan tus clientes en el local y en el menú online. ¿Guardar y revisar tus precios después?')) return;

    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('incluyen', elegido);
    const r = await fetch('<?= url('reportes/guardar-precios-incluyen-iva') ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) { alert(d.mensaje); location.reload(); }
    else alert(d.error || 'No se pudo guardar.');
});

document.getElementById('selCategoriaMono')?.addEventListener('change', async function(){
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('categoria', this.value);
    const r = await fetch('<?= url('reportes/guardar-categoria-monotributo') ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo guardar.');
});
</script>

<!-- Pestañas -->
<ul class="nav nav-tabs mb-4 d-print-none" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetalle" type="button" role="tab">Detalle</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSeparacion" type="button" role="tab">Separación de Ingresos</button>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tabDetalle" role="tabpanel">

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
                <div class="rounded-3 p-3 bg-<?= $k['color'] ?>-soft text-<?= $k['color'] ?>">
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
                    'Efectivo'         => $resumen['efectivo']      ?? 0,
                    'Débito'           => $resumen['debito']        ?? 0,
                    'Crédito'          => $resumen['credito']       ?? 0,
                    'Transferencia'    => $resumen['transferencia'] ?? 0,
                    'Mercado Pago'     => $resumen['mercadopago']  ?? 0,
                    'Cuenta Corriente' => $resumen['cuenta_corriente'] ?? 0,
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

</div><!-- /tabDetalle -->

<div class="tab-pane fade" id="tabSeparacion" role="tabpanel">
<?php include __DIR__ . '/ventas-separacion.php'; ?>
</div><!-- /tabSeparacion -->

</div><!-- /tab-content -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblVentas', {
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
