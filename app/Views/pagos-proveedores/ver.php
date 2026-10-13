<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Pago #<?= $pago['id'] ?></h1>
        <p class="text-muted small mb-0"><?= fecha($pago['fecha']) ?> · <?= ucfirst($pago['tipo']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($pago['estado'] === 'activo' && $montoDisponible > 0.009 && ($facturasPendientes || $gastosPendientes)): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVincular">+ Vincular a comprobante</button>
        <?php endif; ?>
        <?php if ($pago['estado'] === 'activo'): ?>
        <button class="btn btn-sm btn-outline-danger" onclick="anular(<?= $pago['id'] ?>)">Anular</button>
        <?php endif; ?>
        <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
    </div>
</div>

<div class="row g-4">

    <!-- ── Resumen del pago ───────────────────────────────────────────── -->
    <div class="col-md-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Detalle del pago</h6>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Beneficiario</dt>
                    <dd class="col-7 fw-semibold"><?= e($pago['nombre_beneficiario'] ?? '—') ?></dd>
                    <?php if ($pago['cuit']): ?>
                    <dt class="col-5 text-muted">CUIT</dt>
                    <dd class="col-7"><?= e($pago['cuit']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted">Tipo</dt>
                    <dd class="col-7"><?= ucfirst($pago['tipo']) ?></dd>
                    <dt class="col-5 text-muted">Fecha</dt>
                    <dd class="col-7"><?= fecha($pago['fecha']) ?></dd>
                    <?php if ($pago['concepto']): ?>
                    <dt class="col-5 text-muted">Concepto</dt>
                    <dd class="col-7"><?= e($pago['concepto']) ?></dd>
                    <?php endif; ?>
                    <?php if ($pago['referencia_mp']): ?>
                    <dt class="col-5 text-muted">Op. MP</dt>
                    <dd class="col-7"><code><?= e($pago['referencia_mp']) ?></code></dd>
                    <?php endif; ?>
                    <?php if ($pago['notas']): ?>
                    <dt class="col-5 text-muted">Notas</dt>
                    <dd class="col-7"><?= nl2br(e($pago['notas'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted">Estado</dt>
                    <dd class="col-7">
                        <?php $cls = $pago['estado']==='activo' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($pago['estado']) ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Registrado por</dt>
                    <dd class="col-7"><?= e($pago['usuario_nombre'] ?? '—') ?></dd>
                </dl>

                <!-- Métodos -->
                <hr class="my-3">
                <h6 class="fw-semibold mb-2 small">Métodos de pago</h6>
                <?php
                $metodos = [
                    'mercado_pago'  => 'Mercado Pago',
                    'efectivo'      => 'Efectivo',
                    'debito'        => 'Débito',
                    'transferencia' => 'Transferencia',
                    'cheque'        => 'Cheque',
                    'otro'          => 'Otro',
                ];
                $alguno = false;
                foreach ($metodos as $key => $label):
                    $monto = (float)($pago["monto_{$key}"] ?? 0);
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
                    <span>Total pagado</span>
                    <span class="text-primary"><?= moneda($pago['monto_total']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Facturas aplicadas + comprobante ──────────────────────────── -->
    <div class="col-md-7">

        <?php if (!empty($facturas)): ?>
        <div class="card border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Facturas imputadas</h6>
                <table class="table table-sm small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Comprobante</th><th>Fecha</th>
                            <th class="text-end">Total factura</th>
                            <th class="text-end">Monto aplicado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($facturas as $f): ?>
                    <tr>
                        <td>
                            <a href="<?= url('compras/ver?id=' . $f['factura_id']) ?>">
                                <?= e(strtoupper(str_replace('_',' ',$f['tipo_comprobante']))) ?> <?= e($f['nro_comprobante'] ?? '') ?>
                            </a>
                        </td>
                        <td><?= fecha($f['fecha']) ?></td>
                        <td class="text-end"><?= moneda($f['total_con_iva']) ?></td>
                        <td class="text-end fw-semibold text-success"><?= moneda($f['monto_aplicado']) ?></td>
                        <td>
                            <?php $cls = match($f['estado']) {
                                'pagada'   => 'bg-success-soft text-success',
                                'pendiente'=> 'bg-warning-soft text-warning',
                                default    => 'bg-secondary-soft text-secondary'
                            }; ?>
                            <span class="badge <?= $cls ?>"><?= ucfirst($f['estado']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($gastos)): ?>
        <div class="card border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Gastos imputados</h6>
                <table class="table table-sm small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Descripción</th><th>Fecha</th>
                            <th class="text-end">Total gasto</th>
                            <th class="text-end">Monto aplicado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gastos as $g): ?>
                    <tr>
                        <td><a href="<?= url('gastos') ?>"><?= e($g['descripcion']) ?></a></td>
                        <td><?= fecha($g['fecha']) ?></td>
                        <td class="text-end"><?= moneda($g['monto']) ?></td>
                        <td class="text-end fw-semibold text-success"><?= moneda($g['monto_aplicado']) ?></td>
                        <td>
                            <?php $cls = $g['estado'] === 'pagado' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'; ?>
                            <span class="badge <?= $cls ?>"><?= $g['estado'] === 'pagado' ? 'Pagado' : 'Pendiente' ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pago['comprobante_path']): ?>
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Comprobante adjunto</h6>
                <?php $ext = pathinfo($pago['comprobante_path'], PATHINFO_EXTENSION); ?>
                <?php if ($ext === 'pdf'): ?>
                <a href="<?= url('pagos-proveedores/comprobante?id=' . $pago['id']) ?>"
                   target="_blank" class="btn btn-sm btn-outline-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Ver PDF del comprobante
                </a>
                <?php else: ?>
                <a href="<?= url('pagos-proveedores/comprobante?id=' . $pago['id']) ?>" target="_blank">
                    <img src="<?= url('pagos-proveedores/comprobante?id=' . $pago['id']) ?>"
                         alt="Comprobante" class="img-fluid rounded border" style="max-height:320px">
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal: vincular este pago a una factura o gasto pendiente del mismo proveedor -->
<?php if ($montoDisponible > 0.009 && ($facturasPendientes || $gastosPendientes)): ?>
<div class="modal fade" id="modalVincular" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Vincular pago a un comprobante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Monto de este pago sin imputar todavía: <strong><?= moneda($montoDisponible) ?></strong>.
                    Elegí a qué factura o gasto pendiente corresponde.
                </p>
                <div class="d-flex flex-column gap-2" id="listaVincular">
                    <?php foreach ($facturasPendientes as $f): ?>
                    <label class="border rounded p-2 d-flex justify-content-between align-items-center small" style="cursor:pointer">
                        <span>
                            <input type="radio" name="vincularItem" class="form-check-input me-2"
                                   data-tipo="factura" data-id="<?= $f['id'] ?>" data-saldo="<?= $f['saldo'] ?>"
                                   onchange="actualizarMontoVincular()">
                            <?= e(strtoupper(str_replace('_',' ',$f['tipo_comprobante']))) ?> <?= e($f['nro_comprobante'] ?? '') ?>
                            <span class="text-muted"> · <?= fecha($f['fecha']) ?></span>
                        </span>
                        <span class="fw-semibold text-warning">Saldo <?= moneda($f['saldo']) ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php foreach ($gastosPendientes as $g): ?>
                    <label class="border rounded p-2 d-flex justify-content-between align-items-center small" style="cursor:pointer">
                        <span>
                            <input type="radio" name="vincularItem" class="form-check-input me-2"
                                   data-tipo="gasto" data-id="<?= $g['id'] ?>" data-saldo="<?= $g['saldo'] ?>"
                                   onchange="actualizarMontoVincular()">
                            <?= e($g['descripcion']) ?>
                            <span class="text-muted"> · <?= fecha($g['fecha']) ?></span>
                        </span>
                        <span class="fw-semibold text-warning">Saldo <?= moneda($g['saldo']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <label class="form-label small fw-semibold mb-1">Monto a imputar</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="vincularMonto" value="<?= $montoDisponible ?>">
                </div>
                <div id="vincularError" class="text-danger small mt-2 d-none"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarVincular">Vincular</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
const MONTO_DISPONIBLE_PAGO = <?= (float) $montoDisponible ?>;

// Precarga "Monto a imputar" con el menor entre el saldo del comprobante elegido y lo
// disponible del pago — antes quedaba fijo en el disponible total, lo que permitía
// aplicar de más contra un comprobante más chico y perder la diferencia sin poder
// asignarla a otro (ver validación agregada en PagoProveedorModel::aplicarAFactura/Gasto).
function actualizarMontoVincular() {
    const sel = document.querySelector('input[name="vincularItem"]:checked');
    if (!sel) return;
    const saldo = parseFloat(sel.dataset.saldo || '0');
    document.getElementById('vincularMonto').value = Math.min(saldo, MONTO_DISPONIBLE_PAGO).toFixed(2);
}

function anular(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/pagos-proveedores/anular`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular este pago?', ejecutar, { label: 'Anular', sub: 'Las facturas asociadas volverán a estado Pendiente.' });
    } else {
        if (confirm('¿Anular este pago? Las facturas asociadas volverán a estado Pendiente.')) ejecutar();
    }
}

document.getElementById('btnConfirmarVincular')?.addEventListener('click', async () => {
    const seleccionado = document.querySelector('input[name="vincularItem"]:checked');
    const errorEl = document.getElementById('vincularError');
    errorEl.classList.add('d-none');

    if (!seleccionado) {
        errorEl.textContent = 'Elegí una factura o gasto de la lista.';
        errorEl.classList.remove('d-none');
        return;
    }
    const monto = parseFloat(document.getElementById('vincularMonto').value || '0');
    if (!(monto > 0)) {
        errorEl.textContent = 'Ingresá un monto mayor a cero.';
        errorEl.classList.remove('d-none');
        return;
    }

    const fd = new FormData();
    fd.append('pago_id', <?= (int) $pago['id'] ?>);
    fd.append('tipo', seleccionado.dataset.tipo);
    fd.append('item_id', seleccionado.dataset.id);
    fd.append('monto', monto);
    fd.append('_csrf_token', CSRF_TOKEN);

    const r = await fetch(`${BASE_URL}/pagos-proveedores/vincular`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        showToast(d.mensaje);
        setTimeout(() => location.reload(), 800);
    } else {
        errorEl.textContent = d.error || 'No se pudo vincular el pago.';
        errorEl.classList.remove('d-none');
    }
});
</script>
