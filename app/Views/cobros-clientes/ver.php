<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Cobro #<?= $cobro['id'] ?></h1>
        <p class="text-muted small mb-0"><?= fecha($cobro['fecha']) ?> · <?= e($cliente['nombre'] ?? '—') ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($cobro['estado'] === 'activo'): ?>
        <button class="btn btn-sm btn-outline-danger" onclick="anular(<?= $cobro['id'] ?>)">Anular</button>
        <?php endif; ?>
        <a href="<?= url('cobros-clientes') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
    </div>
</div>

<div class="row g-4">

    <!-- ── Resumen del cobro ───────────────────────────────────────────── -->
    <div class="col-md-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Detalle del cobro</h6>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Cliente</dt>
                    <dd class="col-7 fw-semibold"><?= e($cliente['nombre'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Fecha</dt>
                    <dd class="col-7"><?= fecha($cobro['fecha']) ?></dd>
                    <?php if ($cobro['concepto']): ?>
                    <dt class="col-5 text-muted">Concepto</dt>
                    <dd class="col-7"><?= e($cobro['concepto']) ?></dd>
                    <?php endif; ?>
                    <?php if ($cobro['notas']): ?>
                    <dt class="col-5 text-muted">Notas</dt>
                    <dd class="col-7"><?= nl2br(e($cobro['notas'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted">Estado</dt>
                    <dd class="col-7">
                        <?php $cls = $cobro['estado']==='activo' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($cobro['estado']) ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Registrado por</dt>
                    <dd class="col-7"><?= e($cobro['usuario_nombre'] ?? '—') ?></dd>
                </dl>

                <hr class="my-3">
                <h6 class="fw-semibold mb-2 small">Métodos de cobro</h6>
                <?php
                $metodos = [
                    'efectivo'      => 'Efectivo',
                    'debito'        => 'Débito',
                    'transferencia' => 'Transferencia',
                    'mercadopago'   => 'Mercado Pago',
                ];
                $alguno = false;
                foreach ($metodos as $key => $label):
                    $monto = (float)($cobro["monto_{$key}"] ?? 0);
                    if ($monto <= 0) continue;
                    $alguno = true;
                ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted"><?= $label ?></span>
                    <span><?= moneda($monto) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$alguno): ?><p class="text-muted small">—</p><?php endif; ?>
                <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top">
                    <span>Total cobrado</span>
                    <span class="text-primary"><?= moneda($cobro['monto_total']) ?></span>
                </div>
                <?php if ($montoDisponible > 0.009): ?>
                <div class="d-flex justify-content-between small mt-1 text-muted">
                    <span>Sin aplicar a ninguna venta</span>
                    <span><?= moneda($montoDisponible) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Ventas aplicadas ──────────────────────────────────────────── -->
    <div class="col-md-7">
        <?php if (!empty($ventas)): ?>
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Ventas imputadas</h6>
                <table class="table table-sm small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Venta</th><th>Fecha</th>
                            <th class="text-end">Total venta</th>
                            <th class="text-end">Monto aplicado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ventas as $v): ?>
                    <tr>
                        <td><a href="<?= url('ventas/ver?id=' . $v['venta_id']) ?>">#<?= $v['venta_id'] ?></a></td>
                        <td><?= fecha($v['fecha']) ?></td>
                        <td class="text-end"><?= moneda($v['total_con_iva']) ?></td>
                        <td class="text-end fw-semibold text-success"><?= moneda($v['monto_aplicado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card border-0">
            <div class="card-body text-muted small">Este cobro todavía no se aplicó a ninguna venta.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
function anular(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/cobros-clientes/anular`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.href = `${BASE_URL}/cobros-clientes`, 800)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular este cobro?', ejecutar, { label: 'Anular', sub: 'Las ventas asociadas vuelven a quedar con saldo pendiente.' });
    } else {
        if (confirm('¿Anular este cobro? Las ventas asociadas vuelven a quedar con saldo pendiente.')) ejecutar();
    }
}
</script>
