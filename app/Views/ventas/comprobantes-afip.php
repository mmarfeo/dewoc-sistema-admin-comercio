<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Comprobantes AFIP</h1>
    <a href="<?= url('ventas') ?>" class="btn btn-outline-secondary btn-sm">← Volver a Ventas</a>
</div>

<!-- Filtros -->
<form method="GET" action="<?= url('ventas/comprobantes-afip') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Resultado</label>
                <select name="resultado" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="aprobado"  <?= $filtros['resultado']==='aprobado' ?'selected':'' ?>>Aprobado (con CAE)</option>
                    <option value="rechazado" <?= $filtros['resultado']==='rechazado'?'selected':'' ?>>Rechazado / sin CAE</option>
                </select></div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('ventas/comprobantes-afip') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<?php
$totalAprobados  = count(array_filter($comprobantes, fn($c) => $c['afip_resultado'] === 'A'));
$totalRechazados = count($comprobantes) - $totalAprobados;
?>
<div class="alert alert-primary py-2 small mb-3">
    <?= count($comprobantes) ?> comprobantes en el período — <strong class="text-success"><?= $totalAprobados ?> aprobados</strong>
    <?php if ($totalRechazados > 0): ?> · <strong class="text-danger"><?= $totalRechazados ?> sin CAE</strong><?php endif; ?>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblComprobantes" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Venta</th><th>Fecha</th><th>Cliente</th>
                        <th>Comprobante</th><th>CAE</th><th>Vto. CAE</th>
                        <th class="text-end">Total</th><th>Resultado</th>
                        <th class="text-center" style="width:130px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comprobantes as $c): ?>
                    <?php $aprobado = $c['afip_resultado'] === 'A'; ?>
                    <tr>
                        <td class="small">#<?= $c['id'] ?></td>
                        <td class="small"><?= fechaHora($c['created_at']) ?></td>
                        <td class="small"><?= e($c['cliente_nombre'] ?? 'Consumidor final') ?></td>
                        <td class="small">
                            <?php if ($c['afip_nro_cbte']): ?>
                                <?= (int)$c['afip_tipo_cbte'] ?>/<?= str_pad((string)$c['afip_pto_vta'], 5, '0', STR_PAD_LEFT) ?>-<?= str_pad((string)$c['afip_nro_cbte'], 8, '0', STR_PAD_LEFT) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= $c['afip_cae'] ? e($c['afip_cae']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="small"><?= $c['afip_cae_vto'] ? fecha($c['afip_cae_vto']) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end fw-semibold"><?= moneda($c['total_con_iva']) ?></td>
                        <td>
                            <?php if ($aprobado): ?>
                            <span class="badge bg-success-soft text-success small">Aprobado</span>
                            <?php else: ?>
                            <span class="badge bg-danger-soft text-danger small" title="<?= e($c['afip_obs'] ?? '') ?>">Sin CAE</span>
                            <?php if ($c['afip_obs']): ?><div class="text-danger" style="font-size:.7rem;max-width:220px"><?= e(mb_strimwidth($c['afip_obs'], 0, 80, '…')) ?></div><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('ventas/ticket?id='.$c['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0" title="Ver ticket">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                            <?php if ($c['afip_nro_cbte']): ?>
                            <button type="button" class="btn btn-sm btn-outline-info py-0" title="Verificar en AFIP (tiempo real)" onclick="verificarAfip(<?= $c['id'] ?>, this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$comprobantes): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay comprobantes AFIP en el período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Histórico de otros puntos de venta (consulta en vivo a AFIP) ── -->
<div class="card border-0 mt-4">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-1">Comprobantes de otros puntos de venta</h6>
        <p class="small text-muted mb-0">
            Dewoc solo emite por el Punto de Venta <?= (int)AFIP_PTO_VENTA ?>. Si facturaste con otro sistema
            por otro punto de venta (por ejemplo el ERP anterior), esos comprobantes nunca pasaron por esta
            base de datos — pero podés traerlos en vivo directamente desde AFIP acá abajo.
            AFIP no permite listar "todos" de una vez, así que se consulta hacia atrás desde el último autorizado.
        </p>
    </div>
    <div class="card-body">
        <form id="formHistoricoAfip" class="row g-2 align-items-end mb-3">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Punto de Venta</label>
                <input type="number" id="histPtoVta" class="form-control form-control-sm" min="1" placeholder="Ej: 1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Tipo de comprobante</label>
                <select id="histTipoCbte" class="form-select form-select-sm">
                    <option value="11" selected>Factura C (monotributo)</option>
                    <option value="6">Factura B</option>
                    <option value="1">Factura A</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Cantidad a traer</label>
                <input type="number" id="histCantidad" class="form-control form-control-sm" min="1" max="50" value="20">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary" id="btnHistoricoAfip">Consultar en AFIP</button>
            </div>
        </form>
        <div id="histoAviso" class="alert alert-info small d-none"></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="tblHistoricoAfip">
                <thead class="table-light">
                    <tr>
                        <th>Comprobante</th><th>Fecha</th><th>CAE</th>
                        <th>Vto. CAE</th><th class="text-end">Importe</th><th>Resultado</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(()=>$('#tblComprobantes').DataTable({order:[[0,'desc']], paging:true}));

async function verificarAfip(id, btn) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '…';
    try {
        const r = await fetch(`${window.APP_BASE}/ventas/verificar-afip?id=${id}`);
        const d = await r.json();
        if (!d.ok) {
            alert('No se pudo verificar contra AFIP:\n' + (d.error || 'Error desconocido'));
        } else if (!d.encontrado) {
            alert('AFIP respondió que este comprobante NO existe en su sistema.');
        } else {
            alert(
                '✓ Comprobante confirmado en AFIP (en tiempo real):\n\n' +
                'Resultado: ' + d.resultado + '\n' +
                'CAE: ' + d.cae + '\n' +
                'Vencimiento CAE: ' + (d.cae_vto || '—') + '\n' +
                'Fecha del comprobante: ' + (d.fecha_cbte || '—') + '\n' +
                'Importe total: $' + Number(d.imp_total).toLocaleString('es-AR', {minimumFractionDigits: 2})
            );
        }
    } catch (e) {
        alert('Error de conexión al verificar: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ── Histórico de otros puntos de venta ──────────────────────────────────────
document.getElementById('formHistoricoAfip').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const ptoVta   = document.getElementById('histPtoVta').value;
    const tipoCbte = document.getElementById('histTipoCbte').value;
    const cantidad = document.getElementById('histCantidad').value;
    const btn      = document.getElementById('btnHistoricoAfip');
    const aviso    = document.getElementById('histoAviso');
    const tbody    = document.querySelector('#tblHistoricoAfip tbody');

    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = 'Consultando…';
    aviso.classList.add('d-none');
    tbody.innerHTML = '';

    try {
        const r = await fetch(`${window.APP_BASE}/ventas/historico-afip?pto_vta=${ptoVta}&tipo_cbte=${tipoCbte}&cantidad=${cantidad}`);
        const d = await r.json();
        if (!d.ok) {
            aviso.textContent = d.error || 'No se pudo consultar AFIP.';
            aviso.className = 'alert alert-danger small';
            return;
        }
        if (!d.comprobantes.length) {
            aviso.textContent = 'No se encontraron comprobantes para ese punto de venta/tipo.';
            aviso.className = 'alert alert-info small';
            return;
        }
        d.comprobantes.forEach(c => {
            const tr = document.createElement('tr');
            const pto = String(c.pto_vta).padStart(5,'0');
            const nro = String(c.nro_cbte).padStart(8,'0');
            tr.innerHTML = `
                <td class="small">${c.tipo_cbte}/${pto}-${nro}</td>
                <td class="small">${c.fecha_cbte || '—'}</td>
                <td class="small">${c.cae || '—'}</td>
                <td class="small">${c.cae_vto || '—'}</td>
                <td class="text-end fw-semibold">$${Number(c.imp_total||0).toLocaleString('es-AR',{minimumFractionDigits:2})}</td>
                <td>${c.resultado === 'A' ? '<span class="badge bg-success-soft text-success small">Aprobado</span>' : '<span class="badge bg-danger-soft text-danger small">'+(c.resultado||'—')+'</span>'}</td>`;
            tbody.appendChild(tr);
        });
    } catch (e) {
        aviso.textContent = 'Error de conexión: ' + e.message;
        aviso.className = 'alert alert-danger small';
    } finally {
        btn.disabled = false;
        btn.textContent = original;
    }
});
</script>
