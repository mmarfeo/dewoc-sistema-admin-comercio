<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cierre de caja N° <?= $t['numero'] ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Courier New', monospace; font-size: 12px; width: <?= TICKET_ANCHO_MM ?>mm; margin: 0 auto; padding: 4px; zoom: <?= TICKET_ZOOM_PCT ?>%; }
.center { text-align:center; }
.right  { text-align:right; }
.bold   { font-weight:bold; }
.line   { border-top: 1px dashed #000; margin: 4px 0; }
.double { border-top: 2px solid #000; margin: 4px 0; }
table   { width:100%; border-collapse:collapse; }
td, th  { padding: 1px 2px; vertical-align:top; }
.row    { display:flex; justify-content:space-between; gap:8px; }
.print-bar { font-family: -apple-system, sans-serif; margin-top:12px; }
.print-btn { display:block; width:100%; padding:8px; border:1px solid #dee2e6; border-radius:6px; text-align:center; cursor:pointer; background:#f8f9fa; color:#212529; text-decoration:none; }
@media print {
    @page { size: <?= TICKET_ANCHO_MM ?>mm auto; margin: 0; }
    body  { width: <?= TICKET_ANCHO_MM ?>mm; }
    .print-bar { display: none; }
}
</style>
</head>
<body onload="window.print()">

<div class="center bold" style="font-size:14px"><?= e(NEGOCIO_NOMBRE) ?></div>
<div class="center bold">CIERRE DE CAJA N° <?= $t['numero'] ?></div>
<div class="line"></div>

<div class="bold">APERTURA</div>
<div>Cajero: <?= e($t['usuario_nombre'] ?? '—') ?></div>
<div>Fecha: <?= fechaHora($t['apertura_at']) ?></div>
<div>Efectivo inicial: <?= moneda($t['monto_inicial']) ?></div>
<div class="line"></div>

<div class="bold">CIERRE</div>
<div>Fecha: <?= $t['cierre_at'] ? fechaHora($t['cierre_at']) : '—' ?></div>
<div class="row"><span>Efectivo declarado:</span><span><?= moneda((float)$t['monto_cierre_declarado_efectivo']) ?></span></div>
<div class="row"><span>Mercado Pago declarado:</span><span><?= moneda((float)$t['monto_cierre_declarado_mp']) ?></span></div>
<div class="row bold"><span>Diferencia efectivo:</span><span><?= ((float)$t['diferencia_efectivo'] >= 0 ? '+' : '') . moneda((float)$t['diferencia_efectivo']) ?></span></div>
<div class="row bold"><span>Diferencia Mercado Pago:</span><span><?= ((float)$t['diferencia_mp'] >= 0 ? '+' : '') . moneda((float)$t['diferencia_mp']) ?></span></div>
<div>Importe para la próxima apertura: <?= moneda((float)$t['monto_cierre_declarado_efectivo']) ?></div>
<div class="line"></div>

<div class="bold">RESUMEN DEL DIA</div>
<div class="row"><span>Ventas:</span><span><?= $t['resumen_ventas']['completadas']['cantidad'] ?> — <?= moneda($t['resumen_ventas']['completadas']['total']) ?></span></div>
<div class="row"><span>Anulaciones:</span><span><?= $t['resumen_ventas']['anuladas']['cantidad'] ?> — <?= moneda($t['resumen_ventas']['anuladas']['total']) ?></span></div>
<div class="line"></div>

<div class="bold">EGRESOS MANUALES</div>
<?php $egresos = array_filter($t['movimientos'], fn($m) => $m['tipo'] === 'egreso'); ?>
<?php if (!$egresos): ?>
<div>Sin egresos manuales.</div>
<?php else: ?>
<?php foreach ($egresos as $m): ?>
<div class="row"><span><?= moneda($m['monto']) ?></span><span class="right"><?= e($m['descripcion'] ?: 'Sin descripción') ?></span></div>
<?php endforeach; ?>
<div class="row bold"><span>TOTAL EGRESOS:</span><span><?= moneda($t['total_egresos']) ?></span></div>
<?php endif; ?>
<div class="line"></div>

<div class="bold">FORMAS DE PAGO</div>
<div class="row"><span>Efectivo:</span><span><?= moneda($t['esperado']['efectivo']) ?></span></div>
<div class="row"><span>Débito:</span><span><?= moneda($t['esperado']['debito']) ?></span></div>
<div class="row"><span>Crédito:</span><span><?= moneda($t['esperado']['credito']) ?></span></div>
<div class="row"><span>Transferencia:</span><span><?= moneda($t['esperado']['transferencia']) ?></span></div>
<div class="row"><span>Mercado Pago:</span><span><?= moneda($t['esperado']['mercadopago']) ?></span></div>

<?php if (!empty($t['observaciones'])): ?>
<div class="line"></div>
<div class="bold">Observaciones:</div>
<div><?= e($t['observaciones']) ?></div>
<?php endif; ?>

<div class="double"></div>
<div class="center" style="font-size:10px">Cierre <?= $t['cierre_ciego'] ? 'a ciegas' : 'con visibilidad completa' ?></div>

<div class="print-bar">
    <a href="javascript:window.print()" class="print-btn">🖨 Imprimir</a>
</div>

</body>
</html>
