<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Cobros de Clientes</h1>
        <p class="text-muted small mb-0">Cuenta corriente y registro de cobros</p>
    </div>
    <a href="<?= url('cobros-clientes/nuevo') ?>" class="btn btn-primary">+ Registrar Cobro</a>
</div>

<?php if (!empty($cuentas)): ?>
<!-- ── Cuenta Corriente ───────────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-white pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-semibold mb-0 text-danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Saldos pendientes de cobro
        </h6>
        <div class="text-end">
            <div class="small text-muted mb-0">Deuda total</div>
            <div class="fw-bold fs-5 text-danger"><?= moneda($deudaTotal) ?></div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th class="text-end">Ventas pendientes</th>
                        <th class="text-end">Deuda total</th>
                        <th class="text-end">Cobrado parcial</th>
                        <th class="text-end text-danger fw-semibold">Saldo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cuentas as $c): ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['nombre']) ?></td>
                    <td class="text-end small"><?= $c['ventas_pendientes'] ?></td>
                    <td class="text-end small"><?= moneda($c['total_deuda']) ?></td>
                    <td class="text-end small text-muted"><?= moneda($c['pagado_parcial']) ?></td>
                    <td class="text-end fw-bold text-danger"><?= moneda($c['saldo']) ?></td>
                    <td class="text-end">
                        <a href="<?= url('cobros-clientes/cuenta-corriente?cliente_id=' . $c['cliente_id']) ?>"
                           class="btn btn-sm btn-outline-secondary py-0 me-1" title="Ver cuenta corriente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </a>
                        <a href="<?= url('cobros-clientes/nuevo?cliente_id=' . $c['cliente_id']) ?>"
                           class="btn btn-sm btn-primary py-0">Cobrar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end fw-semibold">Deuda total</td>
                        <td class="text-end fw-bold text-danger"><?= moneda($deudaTotal) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filtros ───────────────────────────────────────────────────────── -->
<form method="GET" action="<?= url('cobros-clientes') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Cliente</label>
                <select name="cliente_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= ($filtros['cliente_id'] == $cl['id']) ? 'selected' : '' ?>><?= e($cl['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="activo"  <?= $filtros['estado']==='activo'  ?'selected':'' ?>>Activo</option>
                    <option value="anulado" <?= $filtros['estado']==='anulado' ?'selected':'' ?>>Anulado</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('cobros-clientes') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<!-- ── Lista de cobros ─────────────────────────────────────────────────── -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblCobros" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Fecha</th><th>Cliente</th><th>Concepto</th>
                        <th class="text-end">Efectivo</th><th class="text-end">Otros</th>
                        <th class="text-end fw-semibold">Total</th>
                        <th>Estado</th><th class="text-center" style="width:80px">Acc.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cobros as $c): ?>
                <tr class="<?= $c['estado']==='anulado' ? 'text-muted' : '' ?>">
                    <td class="small"><?= $c['id'] ?></td>
                    <td class="small"><?= fecha($c['fecha']) ?></td>
                    <td class="fw-semibold"><?= e($c['cliente_nombre']) ?></td>
                    <td class="small text-muted"><?= e($c['concepto'] ?? '') ?></td>
                    <td class="text-end small"><?= $c['monto_efectivo'] > 0 ? moneda($c['monto_efectivo']) : '—' ?></td>
                    <td class="text-end small text-muted">
                        <?php $otros = $c['monto_debito']+$c['monto_transferencia']+$c['monto_mercadopago']; ?>
                        <?= $otros > 0 ? moneda($otros) : '—' ?>
                    </td>
                    <td class="text-end fw-bold"><?= moneda($c['monto_total']) ?></td>
                    <td>
                        <?php $cls = $c['estado']==='anulado' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($c['estado']) ?></span>
                    </td>
                    <td class="text-center">
                        <a href="<?= url('cobros-clientes/ver?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <?php if ($c['estado'] === 'activo'): ?>
                        <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="anular(<?= $c['id'] ?>)" title="Anular">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
$(document).ready(() => { $('#tblCobros').DataTable({ order: [[0, 'desc']] }); });

function anular(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/cobros-clientes/anular`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular este cobro?', ejecutar, { label: 'Anular', sub: 'Las ventas asociadas vuelven a quedar con saldo pendiente.' });
    } else {
        if (confirm('¿Anular este cobro? Las ventas asociadas vuelven a quedar con saldo pendiente.')) ejecutar();
    }
}
</script>
