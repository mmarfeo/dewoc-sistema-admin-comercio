<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Margen de ventas (costo real)</h1>
    <div class="d-flex gap-2 d-print-none">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<div class="alert alert-warning d-print-none">
    <strong>¿Qué es esto?</strong> Margen real por línea de venta: a diferencia del costo de receta (un
    promedio entre todas las variantes de un ingrediente, ej. todos los sabores de helado), acá se usa el
    costo <strong>guardado al momento de cada venta</strong>, con el sabor/variante puntual que se eligió —
    así el margen no cambia según cómo suban los costos después. Las filas marcadas <span class="badge bg-secondary-soft text-secondary">estimado</span>
    son de antes de esta funcionalidad: no tienen costo guardado, así que se les aplica el costo actual del
    producto como aproximación.
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
            <div class="col-sm-6 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= url('reportes/margen-ventas?desde=' . date('Y-m-d', strtotime('yesterday')) . '&hasta=' . date('Y-m-d', strtotime('yesterday'))) ?>">Ayer</a>
                <a class="btn btn-outline-secondary" href="<?= url('reportes/margen-ventas?desde=' . date('Y-m-01') . '&hasta=' . date('Y-m-d')) ?>">Mes hasta hoy</a>
            </div>
        </form>
    </div>
</div>

<?php if ((Session::user()['rol'] ?? '') === 'admin'): ?>
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <h6 class="fw-semibold mb-1">Recalcular costos guardados de un período</h6>
        <p class="small text-muted mb-3">
            Para las filas <strong>sin</strong> el badge "estimado" (tienen un costo guardado al
            momento de la venta): si ese costo se calculó mal por un bug ya corregido, esto lo
            vuelve a calcular con la fórmula actual, sobre el mismo sabor/variante que se vendió
            en cada línea. Usa el costo <strong>actual</strong> de cada ingrediente — no reconstruye
            el precio exacto de ese día (el sistema no guarda esa granularidad), así que cuanto más
            vieja la venta, menos representativo. Las filas "estimado" no hace falta tocarlas: ya
            se recalculan solas cada vez que abrís este reporte.
        </p>
        <form id="formRecalcularHistorico" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" id="recalcDesde" class="form-control form-control-sm" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" id="recalcHasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-outline-warning btn-sm" id="btnRecalcularHistorico">↻ Recalcular costos guardados</button>
            </div>
        </form>
        <div id="recalcularHistoricoResultado" class="small mt-2"></div>
    </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-primary mb-0"><?= moneda($totalVendido) ?></div>
                <div class="text-muted small">Total vendido</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-danger mb-0"><?= moneda($totalCosto) ?></div>
                <div class="text-muted small">Costo MP</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h4 fw-bold text-success mb-0"><?= moneda($totalMargen) ?> <span class="fs-6">(<?= $totalMargenPct ?>%)</span></div>
                <div class="text-muted small">Margen</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMargenVentas" class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Venta</th>
                        <th>Producto</th>
                        <th>Sabor(es)</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Precio unit.</th>
                        <th class="text-end">Total vendido</th>
                        <th class="text-end">Costo MP</th>
                        <th class="text-end">Margen</th>
                        <th class="text-end">Margen %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <tr>
                        <td class="small"><?= fechaHora($f['fecha']) ?></td>
                        <td class="small">
                            <a href="<?= url('ventas/ver?id=' . (int)$f['venta_id']) ?>" title="Ver la venta">
                                #<?= (int)$f['venta_id'] ?>
                            </a>
                        </td>
                        <td class="small fw-semibold"><?= e($f['producto_nombre']) ?></td>
                        <td class="small text-muted"><?= e($f['sabores_elegidos'] ?? '') ?: '—' ?></td>
                        <td class="text-end small"><?= number_format((float)$f['cantidad'], 3, ',', '.') ?></td>
                        <td class="text-end small"><?= moneda((float)$f['precio_unitario']) ?></td>
                        <td class="text-end small fw-semibold"><?= moneda((float)$f['total_vendido']) ?></td>
                        <td class="text-end small">
                            <?= moneda((float)$f['costo_mp']) ?>
                            <?php if ($f['costo_estimado']): ?>
                            <sup><span class="badge bg-secondary-soft text-secondary" title="Sin costo guardado al momento de la venta: se usó el costo actual del producto como aproximación.">estimado</span></sup>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small fw-bold <?= $f['margen'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= moneda((float)$f['margen']) ?></td>
                        <td class="text-end small" data-order="<?= (float)$f['margen_pct'] ?>"><?= number_format((float)$f['margen_pct'], 1) ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(() => $('#tblMargenVentas').DataTable({ order: [[0, 'desc']], language: { emptyTable: 'No hay ventas en este período.' } }));

document.getElementById('formRecalcularHistorico')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const desde = document.getElementById('recalcDesde').value;
    const hasta = document.getElementById('recalcHasta').value;
    const resEl = document.getElementById('recalcularHistoricoResultado');
    if (!desde || !hasta) { resEl.innerHTML = '<span class="text-danger">Indicá desde y hasta.</span>'; return; }

    const ejecutar = async () => {
        const btn = document.getElementById('btnRecalcularHistorico');
        btn.disabled = true;
        resEl.innerHTML = '<span class="text-muted">Recalculando…</span>';
        const fd = new FormData();
        fd.append('desde', desde);
        fd.append('hasta', hasta);
        fd.append('_csrf_token', window.APP_CSRF);
        try {
            const r = await fetch(`${window.APP_BASE}/reportes/recalcular-costos-historicos`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                resEl.innerHTML = `<span class="text-success">${d.mensaje}</span>`;
                if (d.actualizadas > 0) setTimeout(() => location.reload(), 1200);
            } else {
                resEl.innerHTML = `<span class="text-danger">${d.error}</span>`;
            }
        } catch {
            resEl.innerHTML = '<span class="text-danger">Error de conexión.</span>';
        } finally {
            btn.disabled = false;
        }
    };

    const msg = `¿Recalcular los costos guardados entre ${desde} y ${hasta}?`;
    const sub = 'Usa el costo actual de cada ingrediente sobre el sabor/variante realmente vendido — no reconstruye el precio exacto de esa fecha.';
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, { label: 'Recalcular', sub });
    } else if (confirm(msg + '\n' + sub)) {
        ejecutar();
    }
});
</script>
