<?php $v = $venta; $esAnulada = $v['estado'] === 'anulada'; ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Venta #<?= $v['id'] ?></h1>
        <span class="badge <?= $esAnulada ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?> mt-1">
            <?= ucfirst($v['estado']) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('ventas/ticket?id='.$v['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">🖨 Ticket</a>
        <a href="<?= url('ventas/comanda?id='.$v['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">📋 Comanda</a>
        <?php if (!$esAnulada): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="anular(<?= $v['id'] ?>)">Anular</button>
        <?php endif; ?>
        <a href="<?= url('ventas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Datos</h6>
            <div class="small"><span class="text-muted">Fecha:</span> <strong><?= fechaHora($v['created_at']) ?></strong></div>
            <div class="small"><span class="text-muted">Vendedor:</span> <?= e($v['usuario_nombre'] ?? '—') ?></div>
            <div class="small"><span class="text-muted">Turno:</span> <?= $v['turno_id'] ? '#'.$v['turno_id'] : '—' ?></div>
            <div class="small"><span class="text-muted">Pago:</span> <?= e(ucfirst(str_replace('_',' ',$v['metodo_pago']))) ?></div>
            <?php if ($v['facturar_afip']): ?>
            <div class="small text-primary fw-semibold mt-1">Facturado por AFIP</div>
            <?php if (!empty($v['afip_cae'])): ?>
            <div class="mt-2 p-2 rounded" style="background:#f0f9ff; border:1px solid #bae6fd; font-size:11px; font-family:monospace">
                <div><strong>CAE:</strong> <?= e($v['afip_cae']) ?></div>
                <div><strong>Vto.:</strong> <?= $v['afip_cae_vto'] ? date('d/m/Y', strtotime($v['afip_cae_vto'])) : '—' ?></div>
                <div><strong>Cbte.:</strong>
                    <?= $v['afip_tipo_cbte'] ?>/<?= str_pad((string)($v['afip_pto_vta']??''), 5, '0', STR_PAD_LEFT) ?>-<?= str_pad((string)($v['afip_nro_cbte']??''), 8, '0', STR_PAD_LEFT) ?>
                </div>
                <?php if ($v['afip_resultado'] && $v['afip_resultado'] !== 'A'): ?>
                <div class="text-warning"><strong>Resultado:</strong> <?= e($v['afip_resultado']) ?></div>
                <?php endif; ?>
                <?php if ($v['afip_obs']): ?>
                <div class="text-muted"><strong>Obs.:</strong> <?= e($v['afip_obs']) ?></div>
                <?php endif; ?>
            </div>
            <?php elseif ($v['facturar_afip']): ?>
            <div class="small text-warning mt-1">Sin CAE (no procesado o error AFIP)</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($v['notas']): ?><div class="small text-muted mt-1"><?= e($v['notas']) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Cliente</h6>
            <p class="fw-bold mb-1"><?= e($v['cliente_nombre'] ?? 'Consumidor final') ?></p>
            <?php if ($v['cliente_doc']): ?><p class="small text-muted mb-0"><?= e($v['cliente_doc']) ?></p><?php endif; ?>
            <?php if ($v['cliente_civa']): ?><p class="small text-muted mb-0"><?= e(ucfirst(str_replace('_',' ',$v['cliente_civa']))) ?></p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-light h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Totales</h6>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Subtotal sin IVA</span><span class="fw-semibold"><?= moneda($v['subtotal']) ?></span></div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">IVA</span><span><?= moneda($v['total_iva']) ?></span></div>
            <?php if ($v['descuento_global'] > 0): ?>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Descuento <?= $v['descuento_global'] ?>%</span><span class="text-danger">—</span></div>
            <?php endif; ?>
            <hr class="my-2">
            <div class="d-flex justify-content-between"><span class="fw-bold">TOTAL</span><span class="fw-bold text-primary fs-5"><?= moneda($v['total_con_iva']) ?></span></div>
        </div></div>
    </div>
</div>

<div class="card border-0"><div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Detalle</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">P.unit.</th><th class="text-end">Desc%</th><th class="text-end">Subtotal</th><th class="text-end">Total c/IVA</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($v['detalle'] as $d): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($d['producto_nombre']) ?> <span class="text-muted small"><?= e($d['producto_codigo'] ?? '') ?></span></td>
                        <td class="text-end"><?= number_format($d['cantidad'],3,',','.') ?> <span class="text-muted small"><?= e($d['unidad']??'') ?></span></td>
                        <td class="text-end"><?= moneda($d['precio_unitario']) ?></td>
                        <td class="text-end small text-muted"><?= $d['descuento_pct'] > 0 ? number_format($d['descuento_pct'],1,',','.').'%' : '—' ?></td>
                        <td class="text-end"><?= moneda($d['subtotal']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($d['subtotal_con_iva']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
async function anular(id){
    if(!confirm('¿Anular esta venta? El stock de productos se recuperará.'))return;
    const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
    const r=await fetch(`${BASE_URL}/ventas/anular`,{method:'POST',body:fd});
    const d=await r.json();
    d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
}
</script>
