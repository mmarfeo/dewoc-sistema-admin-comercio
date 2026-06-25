<?php $v = $venta; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comanda #<?= $v['id'] ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Courier New', monospace; font-size:13px; width:78mm; margin:0 auto; padding:4px; }
.center { text-align:center; }
.bold   { font-weight:bold; }
.line   { border-top:1px dashed #000; margin:5px 0; }
.double { border-top:3px solid #000; margin:5px 0; }
table   { width:100%; border-collapse:collapse; }
td      { padding:2px 0; vertical-align:top; }
.item-cant { font-size:18px; font-weight:bold; min-width:28px; display:inline-block; }
@media print {
    @page { size:80mm auto; margin:0; }
    body  { width:80mm; }
}
</style>
</head>
<body onload="window.print()">

<div class="center bold" style="font-size:16px">★ COMANDA ★</div>
<div class="double"></div>

<div><strong>Mesa/Orden:</strong> Venta #<?= $v['id'] ?></div>
<div><strong>Hora:</strong> <?= date('H:i', strtotime($v['created_at'])) ?></div>
<div><strong>Vendedor:</strong> <?= e($v['usuario_nombre'] ?? '—') ?></div>

<div class="double"></div>

<?php foreach ($v['detalle'] as $d): ?>
<table style="margin-bottom:6px">
    <tr>
        <td style="width:30px; vertical-align:middle;">
            <span class="item-cant"><?= number_format($d['cantidad'],0,',','.') ?>×</span>
        </td>
        <td>
            <div class="bold" style="font-size:14px"><?= e($d['producto_nombre']) ?></div>
            <?php if ($d['producto_codigo']): ?><div style="font-size:10px; color:#666"><?= e($d['producto_codigo']) ?></div><?php endif; ?>
        </td>
    </tr>
</table>
<?php endforeach; ?>

<div class="double"></div>
<?php if ($v['notas']): ?>
<div class="bold">NOTA:</div>
<div style="font-size:13px"><?= e($v['notas']) ?></div>
<div class="line"></div>
<?php endif; ?>

<div class="center" style="font-size:10px; margin-top:6px">
    Impreso: <?= date('d/m/Y H:i:s') ?>
</div>

</body>
</html>
