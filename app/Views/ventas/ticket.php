<?php $v = $venta; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket #<?= $v['id'] ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Courier New', monospace; font-size: 11px; width: 78mm; margin: 0 auto; padding: 4px; }
.center { text-align:center; }
.right  { text-align:right; }
.bold   { font-weight:bold; }
.line   { border-top: 1px dashed #000; margin: 4px 0; }
.double { border-top: 2px solid #000; margin: 4px 0; }
table   { width:100%; border-collapse:collapse; }
td, th  { padding: 1px 2px; vertical-align:top; }
.total-row td { font-size:13px; font-weight:bold; }
.share-bar { font-family: -apple-system, sans-serif; display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
.share-btn { flex:1; min-width:90px; padding:6px 8px; border:1px solid #dee2e6; border-radius:6px; text-decoration:none; font-size:11px; text-align:center; cursor:pointer; background:#f8f9fa; color:#212529; }
.share-btn.wa { background:#25d366; color:#fff; border-color:#25d366; }
.share-btn.ml { background:#0d6efd; color:#fff; border-color:#0d6efd; }
@media print {
    @page { size: 80mm auto; margin: 0; }
    body  { width: 80mm; }
    .share-bar { display: none; }
}
</style>
</head>
<body onload="window.print()">

<div class="center bold" style="font-size:14px">DEWOC</div>
<div class="center">Sistema de administración</div>
<div class="line"></div>

<div class="center">
    <?php if ($v['facturar_afip']): ?>
    <span class="bold">FACTURA ELECTRÓNICA</span><br>
    <?php else: ?>
    <span class="bold">TICKET</span><br>
    <?php endif; ?>
    Venta #<?= $v['id'] ?>
</div>
<div class="line"></div>

<div>Fecha: <?= fechaHora($v['created_at']) ?></div>
<div>Vendedor: <?= e($v['usuario_nombre'] ?? '—') ?></div>
<?php if (!empty($v['mesa'])): ?>
<div class="bold">Mesa/Dest.: <?= e($v['mesa']) ?></div>
<?php endif; ?>
<?php if ($v['cliente_nombre']): ?>
<div>Cliente: <?= e($v['cliente_nombre']) ?></div>
<?php endif; ?>
<div class="line"></div>

<table>
    <tr>
        <th class="left">Ítem</th>
        <th class="right" style="width:30px">Ct.</th>
        <th class="right" style="width:52px">P.unit.</th>
        <th class="right" style="width:52px">Total</th>
    </tr>
    <tr><td colspan="4"><div class="line" style="margin:2px 0"></div></td></tr>
    <?php foreach ($v['detalle'] as $d): ?>
    <tr>
        <td><?= e(mb_strimwidth($d['producto_nombre'],0,20,'…')) ?></td>
        <td class="right"><?= number_format($d['cantidad'],2,',','.') ?></td>
        <td class="right">$<?= number_format($d['precio_unitario'],2,',','.') ?></td>
        <td class="right">$<?= number_format($d['subtotal_con_iva'],2,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="double"></div>

<table>
    <tr><td>Subtotal s/IVA</td><td class="right"><?= moneda($v['subtotal']) ?></td></tr>
    <tr><td>IVA</td><td class="right"><?= moneda($v['total_iva']) ?></td></tr>
    <?php if ($v['descuento_global'] > 0): ?>
    <tr><td>Descuento <?= $v['descuento_global'] ?>%</td><td class="right">-</td></tr>
    <?php endif; ?>
    <tr class="total-row">
        <td>TOTAL</td>
        <td class="right"><?= moneda($v['total_con_iva']) ?></td>
    </tr>
    <tr><td>Pago:</td><td class="right"><?= e(ucfirst(str_replace('_',' ',$v['metodo_pago']))) ?></td></tr>
</table>

<div class="line"></div>
<div class="center">¡Gracias por su compra!</div>
<div class="center" style="font-size:9px; margin-top:4px">www.dewoc.com.ar</div>

<?php if (!empty($v['afip_cae'])): ?>
<div class="line"></div>
<div style="font-size:9px">
    CAE: <?= e($v['afip_cae']) ?><br>
    Venc.: <?= $v['afip_cae_vto'] ? date('d/m/Y', strtotime($v['afip_cae_vto'])) : '' ?><br>
    Cbte: <?= $v['afip_tipo_cbte'] ?>/<?= str_pad((string)($v['afip_pto_vta']??''), 5, '0', STR_PAD_LEFT) ?>-<?= str_pad((string)($v['afip_nro_cbte']??''), 8, '0', STR_PAD_LEFT) ?>
</div>
<?php endif; ?>

<?php if ($v['notas']): ?>
<div class="line"></div>
<div><?= e($v['notas']) ?></div>
<?php endif; ?>

<?php
// Construir texto para compartir
$items_txt = '';
foreach ($v['detalle'] as $d) {
    $items_txt .= '• ' . mb_strimwidth($d['producto_nombre'], 0, 25, '…')
        . ' x' . number_format($d['cantidad'], 0, ',', '.')
        . ' — $' . number_format($d['subtotal_con_iva'], 2, ',', '.') . "\n";
}
$waText = "🛒 *DEWOC — Ticket #" . $v['id'] . "*\n"
    . "📅 " . fechaHora($v['created_at']) . "\n\n"
    . $items_txt . "\n"
    . "*TOTAL: $" . number_format($v['total_con_iva'], 2, ',', '.') . "*\n\n"
    . "¡Gracias por su compra!";

$waPhone = preg_replace('/\D/', '', $v['cliente_telefono'] ?? '');
// Agregar código de país Argentina si parece local (10 dígitos sin +54)
if ($waPhone && strlen($waPhone) === 10) {
    $waPhone = '54' . ltrim($waPhone, '0');
}
$waUrl = 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($waText);

$emailSubject = 'Ticket #' . $v['id'] . ' — DEWOC';
$emailBody    = str_replace(['*', '•'], ['', '-'], $waText);
$mailtoUrl    = 'mailto:' . rawurlencode($v['cliente_email'] ?? '') . '?subject=' . rawurlencode($emailSubject) . '&body=' . rawurlencode($emailBody);
?>

<div class="share-bar">
    <a href="<?= $waUrl ?>" target="_blank" class="share-btn wa">📲 WhatsApp</a>
    <a href="<?= $mailtoUrl ?>" class="share-btn ml">✉ Email</a>
    <a href="javascript:window.print()" class="share-btn">🖨 Imprimir</a>
</div>

</body>
</html>
