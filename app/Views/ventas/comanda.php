<?php $v = $venta; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comanda #<?= $v['id'] ?></title>
<style>
/* overflow-wrap/word-break acá: sin esto, un texto largo sin espacios (una nota del
   pedido, una url) no corta dentro del ancho fijo del ticket — sigue de largo más allá
   del borde y la impresora térmica lo corta sin más, en vez de bajar de línea. */
* { margin:0; padding:0; box-sizing:border-box; overflow-wrap:break-word; word-break:break-word; }
/* TICKET_ANCHO_MM/TICKET_ZOOM_PCT: configurables en .env, sin tocar esta vista
   (ver config/config.php) — cambiar acá si se cambia de impresora térmica. */
body { font-family:'Courier New', monospace; font-size:15px; font-weight:bold; color:#000; width:<?= TICKET_ANCHO_MM ?>mm; margin:0 auto; padding:4px; zoom: <?= TICKET_ZOOM_PCT ?>%; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.center { text-align:center; }
.right  { text-align:right; }
.bold   { font-weight:bold; }
.line   { border-top:1px dashed #000; margin:5px 0; }
.double { border-top:3px solid #000; margin:5px 0; }
table   { width:100%; border-collapse:collapse; }
td      { padding:2px 0; vertical-align:top; }
.total-row td { font-size:15px; font-weight:bold; }
.item-cant { font-size:22px; font-weight:bold; min-width:28px; display:inline-block; }
@media print {
    @page { size:<?= TICKET_ANCHO_MM ?>mm auto; margin:0; }
    body  { width:<?= TICKET_ANCHO_MM ?>mm; }
    /* Fuerza negro puro — la comanda tiene grises (#666) para el código de
       producto, pero en térmica queremos máximo contraste, no jerarquía. */
    * { color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<!-- Si se abre embebido en el iframe del POS, quien dispara print() es el padre
     (pos.php); si se abre sola, se imprime sola — evita el diálogo de
     impresión duplicado cuando está dentro del iframe. -->
<body onload="if (window === window.top) window.print()">

<div class="center bold" style="font-size:16px">★ COMANDA ★</div>
<div class="double"></div>

<div><strong>Mesa/Orden:</strong> Venta #<?= $v['id'] ?></div>
<div><strong>Hora:</strong> <?= date('H:i', strtotime($v['created_at'])) ?></div>
<div><strong>Vendedor:</strong> <?= e($v['usuario_nombre'] ?? '—') ?></div>

<div class="double"></div>

<?php
// Una promo se registra como una fila "cabecera" (el producto promo en sí) + una fila
// por cada producto que la compone (venta_detalle_padre_id apunta a la cabecera — ver
// VentaModel::explotarPromos()). Acá se arma $esPadrePromo para detectar la cabecera y
// no mostrarle el "1×" grande de cantidad: ese 1 es la cantidad de PROMOS vendidas, no
// de productos, y puesto al lado del nombre ("1 × Promo 2 x Wafflez...") se leía como si
// fuera un producto más del pedido — confundía al que arma el pedido en cocina/mostrador.
$esPadrePromo = [];
foreach ($v['detalle'] as $d) {
    if (!empty($d['venta_detalle_padre_id'])) $esPadrePromo[$d['venta_detalle_padre_id']] = true;
}
?>
<?php foreach ($v['detalle'] as $idx => $d): ?>
<?php
$esPromo      = isset($esPadrePromo[$d['id']]);
$esComponente = !empty($d['venta_detalle_padre_id']);
// Si el nombre del producto promo no arranca ya con la palabra "Promo" (depende de cómo
// lo haya cargado cada comercio), se le suma una etiqueta chica para que no haya dudas
// de que es un combo y no un producto normal.
$necesitaEtiquetaPromo = $esPromo && stripos($d['producto_nombre'], 'promo') !== 0;
?>
<?php if ($esPromo && $idx > 0): ?>
<div class="line"></div>
<?php endif; ?>
<table style="margin-bottom:6px">
    <tr>
        <td style="width:30px; vertical-align:middle;">
            <?php if (!($esPromo && (float)$d['cantidad'] == 1)): ?>
            <span class="item-cant"><?= number_format($d['cantidad'],0,',','.') ?>×</span>
            <?php endif; ?>
        </td>
        <td style="<?= $esComponente ? 'padding-left:14px' : '' ?>">
            <div class="bold" style="font-size:15px">
                <?php if ($necesitaEtiquetaPromo): ?><span style="font-size:10px; border:1px solid #000; padding:0 3px; margin-right:3px; white-space:nowrap;">PROMO</span><?php endif; ?>
                <?= $esComponente ? '↳ ' : '' ?><?= e($d['producto_nombre']) ?>
            </div>
            <?php if ($d['producto_codigo']): ?><div style="font-size:11px"><?= e($d['producto_codigo']) ?></div><?php endif; ?>
            <?php foreach (($d['opciones'] ?? []) as $op): ?>
            <div style="font-size:13px; padding-left:10px">
                – <?= (int)$op['cantidad_elegida'] > 1 ? (int)$op['cantidad_elegida'] . 'x ' : '' ?><?= e($op['nombre']) ?>
            </div>
            <?php endforeach; ?>
        </td>
    </tr>
</table>
<?php endforeach; ?>

<div class="double"></div>
<?php if ($v['notas']): ?>
<div class="bold">NOTA:</div>
<!-- width:92% (no 100%): deja un margen a propósito para que, con el zoom de impresión
     aplicado (TICKET_ZOOM_PCT), el texto siga cortando dentro del área imprimible del
     rollo térmico en vez de perder los últimos caracteres de cada línea contra el borde. -->
<div style="font-size:13px; width:92%; white-space:pre-wrap"><?= e($v['notas']) ?></div>
<div class="line"></div>
<?php endif; ?>

<div class="center" style="font-size:10px; margin-top:6px">
    Impreso: <?= date('d/m/Y H:i:s') ?>
</div>

<!-- ══════ Corte de papel — segunda parte: comprobante fiscal para el cliente ══════ -->
<div class="center" style="font-size:9px; margin-top:40px">✂ - - - - - - - - - - - - - - - - - - - - - - - - ✂</div>
<div style="margin-top:40px; padding-top:10px; border-top:3px dashed #000;"></div>

<?php require __DIR__ . '/_comprobante_fiscal.php'; ?>

</body>
</html>
