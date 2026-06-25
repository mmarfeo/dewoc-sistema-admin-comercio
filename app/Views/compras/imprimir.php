<?php $f = $factura; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #111; padding: 20px; }
h1 { font-size: 20px; margin-bottom: 2px; }
.sub { color: #555; font-size: 11px; margin-bottom: 14px; }
.row { display: flex; gap: 24px; margin-bottom: 14px; }
.col { flex: 1; }
.label { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.val { font-size: 12px; font-weight: 600; }
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
thead th { background: #f0f0f0; padding: 5px 6px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #ccc; }
tbody td { padding: 5px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
.right { text-align: right; }
.totales { width: 200px; margin-left: auto; }
.totales td { padding: 3px 6px; }
.totales .total td { font-size: 14px; font-weight: bold; border-top: 2px solid #111; padding-top: 5px; }
.badge { display: inline-block; background: #eee; border-radius: 3px; padding: 1px 5px; font-size: 10px; }
.badge.anulada { background: #fee2e2; color: #b91c1c; }
.actions { font-family: -apple-system, sans-serif; display: flex; gap: 8px; margin-bottom: 16px; }
.btn { padding: 6px 14px; border: 1px solid #dee2e6; border-radius: 6px; cursor: pointer; font-size: 12px; text-decoration: none; background: #f8f9fa; color: #212529; }
.btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
@media print {
    .actions { display: none; }
    @page { size: A4; margin: 15mm; }
}
</style>
</head>
<body onload="window.print()">

<div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <a href="<?= url('compras/ver?id=' . $f['id']) ?>" class="btn">← Volver</a>
</div>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
    <div>
        <h1>DEWOC — Factura de Compra</h1>
        <div class="sub">Sistema de Administración de Comercios</div>
    </div>
    <div style="text-align:right">
        <div style="font-size:22px; font-weight:bold">#<?= $f['id'] ?></div>
        <div class="badge <?= $f['estado'] === 'anulada' ? 'anulada' : '' ?>"><?= strtoupper($f['estado']) ?></div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="label">Proveedor</div>
        <div class="val"><?= e($f['proveedor_nombre'] ?? '—') ?></div>
        <?php if ($f['cuit']): ?><div style="font-size:10px; color:#555">CUIT: <?= e($f['cuit']) ?></div><?php endif; ?>
        <?php if ($f['condicion_iva']): ?><div style="font-size:10px; color:#555">IVA: <?= e(ucfirst(str_replace('_',' ',$f['condicion_iva']))) ?></div><?php endif; ?>
        <?php if ($f['proveedor_direccion']): ?><div style="font-size:10px; color:#555"><?= e($f['proveedor_direccion']) ?></div><?php endif; ?>
    </div>
    <div class="col">
        <div class="label">Comprobante</div>
        <div class="val"><?= strtoupper(str_replace('_',' ',$f['tipo_comprobante'])) ?> <?= e($f['nro_comprobante'] ?? '') ?></div>
        <div style="font-size:10px; color:#555">Fecha: <?= fecha($f['fecha']) ?></div>
        <div style="font-size:10px; color:#555">Condición de pago: <?= e(ucfirst(str_replace('_',' ',$f['condicion_pago']))) ?></div>
    </div>
    <?php if ($f['notas']): ?>
    <div class="col">
        <div class="label">Notas</div>
        <div style="font-size:10px"><?= nl2br(e($f['notas'])) ?></div>
    </div>
    <?php endif; ?>
</div>

<table>
    <thead>
        <tr>
            <th>Ítem</th>
            <th>Tipo</th>
            <th class="right">Cantidad</th>
            <th class="right">P. Unit.</th>
            <th class="right">IVA %</th>
            <th class="right">Subtotal</th>
            <th class="right">Total c/IVA</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($f['detalle'] as $d): ?>
        <tr>
            <td>
                <strong><?= e($d['item_nombre'] ?? '—') ?></strong>
                <?php if ($d['item_codigo']): ?><br><span style="color:#666;font-size:10px"><?= e($d['item_codigo']) ?></span><?php endif; ?>
            </td>
            <td><?= e(str_replace('_',' ',$d['tipo_item'])) ?></td>
            <td class="right"><?= number_format($d['cantidad'],3,',','.') ?> <?= e($d['unidad'] ?? '') ?></td>
            <td class="right">$<?= number_format($d['precio_unitario'],2,',','.') ?></td>
            <td class="right"><?= number_format($d['iva_porcentaje'],1,',','.') ?>%</td>
            <td class="right">$<?= number_format($d['subtotal'],2,',','.') ?></td>
            <td class="right"><strong>$<?= number_format($d['subtotal_con_iva'],2,',','.') ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table class="totales">
    <tr><td style="color:#555">Subtotal sin IVA</td><td class="right">$<?= number_format($f['subtotal'],2,',','.') ?></td></tr>
    <tr><td style="color:#555">IVA</td><td class="right">$<?= number_format($f['total_iva'],2,',','.') ?></td></tr>
    <tr class="total"><td>TOTAL</td><td class="right">$<?= number_format($f['total_con_iva'],2,',','.') ?></td></tr>
</table>

<div style="color:#888; font-size:9px; margin-top:20px; border-top:1px solid #eee; padding-top:6px;">
    Generado por DEWOC — <?= date('d/m/Y H:i') ?>
</div>

</body>
</html>
