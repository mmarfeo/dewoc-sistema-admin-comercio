<?php
$f    = $factura;
$esAnulada = $f['estado'] === 'anulada';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Factura de Compra #<?= $f['id'] ?></h1>
        <span class="badge <?= $esAnulada ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?> mt-1">
            <?= ucfirst($f['estado']) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$esAnulada): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="anular(<?= $f['id'] ?>)">Anular factura</button>
        <?php endif; ?>
        <a href="<?= url('compras/imprimir?id=' . $f['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">🖨 Imprimir / PDF</a>
        <a href="<?= url('compras') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <h6 class="fw-semibold text-muted small text-uppercase mb-3">Proveedor</h6>
                <p class="fw-bold fs-5 mb-1"><?= e($f['proveedor_nombre'] ?? '—') ?></p>
                <?php if ($f['cuit']): ?><p class="small text-muted mb-1">CUIT: <?= e($f['cuit']) ?></p><?php endif; ?>
                <?php if ($f['condicion_iva']): ?><p class="small text-muted mb-0">IVA: <?= e(ucfirst(str_replace('_',' ',$f['condicion_iva']))) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <h6 class="fw-semibold text-muted small text-uppercase mb-3">Datos del comprobante</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Tipo:</span> <span class="fw-semibold"><?= e(strtoupper(str_replace('_',' ',$f['tipo_comprobante']))) ?></span></div>
                    <div class="col-6"><span class="text-muted">Número:</span> <span class="fw-semibold"><?= e($f['nro_comprobante'] ?? '—') ?></span></div>
                    <div class="col-6"><span class="text-muted">Fecha:</span> <span class="fw-semibold"><?= fecha($f['fecha']) ?></span></div>
                    <div class="col-6"><span class="text-muted">Condición pago:</span> <span class="fw-semibold"><?= e(ucfirst(str_replace('_',' ',$f['condicion_pago']))) ?></span></div>
                    <?php if ($f['notas']): ?>
                    <div class="col-12"><span class="text-muted">Notas:</span> <?= e($f['notas']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detalle de ítems -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Ítems</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ítem</th><th>Tipo</th><th class="text-end">Cantidad</th>
                        <th class="text-end">P. unit.</th><th class="text-end">IVA %</th>
                        <th class="text-end">Subtotal</th><th class="text-end">Total c/IVA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($f['detalle'] as $d): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= e($d['item_nombre'] ?? '—') ?></span>
                            <?php if ($d['item_codigo']): ?><span class="text-muted small ms-1">(<?= e($d['item_codigo']) ?>)</span><?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ', $d['tipo_item'])) ?></span></td>
                        <td class="text-end"><?= number_format($d['cantidad'],3,',','.') ?> <span class="text-muted small"><?= e($d['unidad'] ?? '') ?></span></td>
                        <td class="text-end"><?= moneda($d['precio_unitario']) ?></td>
                        <td class="text-end small"><?= number_format($d['iva_porcentaje'],1,',','.')  ?>%</td>
                        <td class="text-end"><?= moneda($d['subtotal']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($d['subtotal_con_iva']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Totales -->
<div class="row justify-content-end">
    <div class="col-md-4">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Subtotal sin IVA</span>
                    <span class="fw-semibold"><?= moneda($f['subtotal']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">IVA</span>
                    <span class="fw-semibold"><?= moneda($f['total_iva']) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">TOTAL</span>
                    <span class="fw-bold text-primary fs-5"><?= moneda($f['total_con_iva']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
async function anular(id){
    if(!confirm('¿Anular esta factura? El stock será revertido.'))return;
    const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
    const r=await fetch(`${BASE_URL}/compras/anular`,{method:'POST',body:fd});
    const d=await r.json();
    d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
}
</script>

<style>
@media print {
    .btn, .sidebar, .header-top, nav, .breadcrumb { display:none!important; }
    .main-content { margin:0!important; }
}
</style>
