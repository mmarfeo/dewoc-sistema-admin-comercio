<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Reembolso #<?= $reembolso['id'] ?></h1>
        <p class="text-muted small mb-0"><?= fecha($reembolso['fecha']) ?> · Socio: <?= e($reembolso['socio_nombre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($reembolso['estado'] === 'activo' && $montoDisponible > 0.009 && $aportesPendientes): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalVincularAporte">+ Vincular a aporte</button>
        <?php endif; ?>
        <?php if ($reembolso['estado'] === 'activo'): ?>
        <button class="btn btn-sm btn-outline-danger" onclick="anular(<?= $reembolso['id'] ?>)">Anular</button>
        <?php endif; ?>
        <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary">← Volver</a>
    </div>
</div>

<?php if ($montoDisponible > 0.009 && $reembolso['estado'] === 'activo'): ?>
<div class="alert alert-warning-soft text-warning small mb-4">
    Este reembolso tiene <strong><?= moneda($montoDisponible) ?></strong> sin aplicar contra ningún aporte todavía
    <?php if ($aportesPendientes): ?>— podés vincularlo a uno o varios de los aportes pendientes de <?= e($reembolso['socio_nombre']) ?> con el botón de arriba.<?php else: ?>(<?= e($reembolso['socio_nombre']) ?> no tiene otros aportes pendientes por ahora).<?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Resumen del reembolso ─────────────────────────────────────── -->
    <div class="col-md-5">
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Detalle del reembolso</h6>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Socio</dt>
                    <dd class="col-7 fw-semibold"><?= e($reembolso['socio_nombre']) ?></dd>
                    <dt class="col-5 text-muted">Fecha</dt>
                    <dd class="col-7"><?= fecha($reembolso['fecha']) ?></dd>
                    <?php if ($reembolso['concepto']): ?>
                    <dt class="col-5 text-muted">Concepto</dt>
                    <dd class="col-7"><?= e($reembolso['concepto']) ?></dd>
                    <?php endif; ?>
                    <?php if ($reembolso['notas']): ?>
                    <dt class="col-5 text-muted">Notas</dt>
                    <dd class="col-7"><?= nl2br(e($reembolso['notas'])) ?></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted">Estado</dt>
                    <dd class="col-7">
                        <?php $cls = $reembolso['estado']==='activo' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($reembolso['estado']) ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Registrado por</dt>
                    <dd class="col-7"><?= e($reembolso['usuario_nombre'] ?? '—') ?></dd>
                </dl>

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
                    $monto = (float)($reembolso["monto_{$key}"] ?? 0);
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
                    <span>Total reembolsado</span>
                    <span class="text-primary"><?= moneda($reembolso['monto_total']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Aportes cubiertos + comprobante ───────────────────────────── -->
    <div class="col-md-7">

        <?php if (!empty($aportes)): ?>
        <div class="card border-0 mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Aportes cubiertos</h6>
                <table class="table table-sm small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th><th>Concepto / Referencia</th><th>Fecha</th>
                            <th class="text-end">Total aporte</th>
                            <th class="text-end">Monto aplicado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $tipos = ['proveedor'=>'Proveedor','sueldo'=>'Sueldo','otro'=>'Otro','aporte'=>'Aporte']; ?>
                    <?php foreach ($aportes as $a): ?>
                    <tr>
                        <td><span class="badge bg-secondary-soft text-secondary"><?= $tipos[$a['tipo']] ?? $a['tipo'] ?></span></td>
                        <td>
                            <a href="<?= url('pagos-proveedores/ver?id=' . $a['pago_id']) ?>">
                                <?= e($a['concepto'] ?: ($a['nombre_referencia'] ?: ('Pago #' . $a['pago_id']))) ?>
                            </a>
                        </td>
                        <td><?= fecha($a['fecha']) ?></td>
                        <td class="text-end"><?= moneda($a['monto_total']) ?></td>
                        <td class="text-end fw-semibold text-success"><?= moneda($a['monto_aplicado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card border-0 mb-3">
            <div class="card-body">
                <p class="text-muted small mb-0">Este reembolso no quedó aplicado contra ningún aporte puntual — se registró solo como un movimiento general hacia el socio.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($reembolso['comprobante_path']): ?>
        <div class="card border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Comprobante adjunto</h6>
                <?php $ext = pathinfo($reembolso['comprobante_path'], PATHINFO_EXTENSION); ?>
                <?php if ($ext === 'pdf'): ?>
                <a href="<?= url('pagos-proveedores/reembolso-comprobante?id=' . $reembolso['id']) ?>"
                   target="_blank" class="btn btn-sm btn-outline-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Ver PDF del comprobante
                </a>
                <?php else: ?>
                <a href="<?= url('pagos-proveedores/reembolso-comprobante?id=' . $reembolso['id']) ?>" target="_blank">
                    <img src="<?= url('pagos-proveedores/reembolso-comprobante?id=' . $reembolso['id']) ?>"
                         alt="Comprobante" class="img-fluid rounded border" style="max-height:320px">
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal: vincular este reembolso a un aporte pendiente del mismo socio -->
<?php if ($montoDisponible > 0.009 && $aportesPendientes): ?>
<div class="modal fade" id="modalVincularAporte" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Vincular reembolso a un aporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Monto de este reembolso sin aplicar todavía: <strong><?= moneda($montoDisponible) ?></strong>.
                    Elegí a qué aporte de <?= e($reembolso['socio_nombre']) ?> corresponde.
                </p>
                <div class="d-flex flex-column gap-2" id="listaVincular">
                    <?php $tiposV = ['proveedor'=>'Proveedor','sueldo'=>'Sueldo','otro'=>'Otro','aporte'=>'Aporte']; ?>
                    <?php foreach ($aportesPendientes as $a): ?>
                    <label class="border rounded p-2 d-flex justify-content-between align-items-center small" style="cursor:pointer">
                        <span>
                            <input type="radio" name="vincularAporte" class="form-check-input me-2" data-id="<?= $a['id'] ?>"
                                   data-saldo="<?= $a['saldo'] ?>" onchange="actualizarMontoVincular()">
                            <span class="badge bg-secondary-soft text-secondary me-1"><?= $tiposV[$a['tipo']] ?? $a['tipo'] ?></span>
                            <?= e($a['concepto'] ?: ($a['nombre_referencia'] ?: ('Pago #' . $a['id']))) ?>
                            <span class="text-muted"> · <?= fecha($a['fecha']) ?></span>
                        </span>
                        <span class="fw-semibold text-warning">Saldo <?= moneda($a['saldo']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <label class="form-label small fw-semibold mb-1">Monto a aplicar</label>
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
const MONTO_DISPONIBLE_REEMBOLSO = <?= (float) $montoDisponible ?>;

// Precarga "Monto a aplicar" con el menor entre el saldo del aporte elegido y lo
// disponible del reembolso — antes quedaba fijo en el disponible total, lo que permitía
// aplicar de más contra un aporte más chico y perder la diferencia sin poder asignarla
// a otro (ver validación agregada en ReembolsoSocioModel::aplicarAAporte).
function actualizarMontoVincular() {
    const sel = document.querySelector('input[name="vincularAporte"]:checked');
    if (!sel) return;
    const saldo = parseFloat(sel.dataset.saldo || '0');
    document.getElementById('vincularMonto').value = Math.min(saldo, MONTO_DISPONIBLE_REEMBOLSO).toFixed(2);
}

function anular(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/pagos-proveedores/reembolso-anular`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular este reembolso?', ejecutar, { label: 'Anular', sub: 'Los aportes que cubría vuelven a quedar pendientes de reembolso.' });
    } else {
        if (confirm('¿Anular este reembolso? Los aportes que cubría vuelven a quedar pendientes.')) ejecutar();
    }
}

document.getElementById('btnConfirmarVincular')?.addEventListener('click', async () => {
    const seleccionado = document.querySelector('input[name="vincularAporte"]:checked');
    const errorEl = document.getElementById('vincularError');
    errorEl.classList.add('d-none');

    if (!seleccionado) {
        errorEl.textContent = 'Elegí un aporte de la lista.';
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
    fd.append('reembolso_id', <?= (int) $reembolso['id'] ?>);
    fd.append('pago_id', seleccionado.dataset.id);
    fd.append('monto', monto);
    fd.append('_csrf_token', CSRF_TOKEN);

    const r = await fetch(`${BASE_URL}/pagos-proveedores/vincular-aporte`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        showToast(d.mensaje);
        setTimeout(() => location.reload(), 800);
    } else {
        errorEl.textContent = d.error || 'No se pudo vincular el reembolso.';
        errorEl.classList.remove('d-none');
    }
});
</script>
