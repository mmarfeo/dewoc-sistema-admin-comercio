<?php $v = $venta; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket #<?= $v['id'] ?></title>
<style>
/* overflow-wrap/word-break acá: sin esto, un texto largo sin espacios (una nota del
   pedido, una url) no corta dentro del ancho fijo del ticket — sigue de largo más allá
   del borde y la impresora térmica lo corta sin más, en vez de bajar de línea. */
* { margin:0; padding:0; box-sizing:border-box; overflow-wrap:break-word; word-break:break-word; }
/* TICKET_ANCHO_MM/TICKET_ZOOM_PCT: configurables en .env, sin tocar esta vista
   (ver config/config.php) — cambiar acá si se cambia de impresora térmica. */
body { font-family: 'Courier New', monospace; font-size: 13px; font-weight: bold; color: #000; width: <?= TICKET_ANCHO_MM ?>mm; margin: 0 auto; padding: 4px; zoom: <?= TICKET_ZOOM_PCT ?>%; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.center { text-align:center; }
.right  { text-align:right; }
.bold   { font-weight:bold; }
.line   { border-top: 1px dashed #000; margin: 4px 0; }
.double { border-top: 2px solid #000; margin: 4px 0; }
table   { width:100%; border-collapse:collapse; }
td, th  { padding: 1px 2px; vertical-align:top; }
.total-row td { font-size:15px; font-weight:bold; }
.share-bar { font-family: -apple-system, sans-serif; display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; zoom: 1; }
.share-btn { flex:1; min-width:90px; padding:6px 8px; border:1px solid #dee2e6; border-radius:6px; text-decoration:none; font-size:11px; text-align:center; cursor:pointer; background:#f8f9fa; color:#212529; font-weight:normal; }
.share-btn.wa { background:#25d366; color:#fff; border-color:#25d366; }
.share-btn.ml { background:#0d6efd; color:#fff; border-color:#0d6efd; }
@media print {
    @page { size: <?= TICKET_ANCHO_MM ?>mm auto; margin: 0; }
    body  { width: <?= TICKET_ANCHO_MM ?>mm; }
    /* Fuerza negro puro en todo el ticket al imprimir — algunas líneas usan grises
       (#444/#666) para jerarquía visual en pantalla, pero en térmica salen
       demasiado claras/ilegibles. En impresión no queremos jerarquía, queremos
       máximo contraste. */
    * { color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .share-bar { display: none; }
}
</style>
</head>
<!-- Si se abre embebido en el iframe del POS, quien dispara print() es el padre
     (pos.php ya controla el foco/timing); si se abre solo (WhatsApp, reimpresión
     desde historial), se imprime solo — evita disparar el diálogo de impresión
     dos veces cuando está dentro del iframe. -->
<body onload="if (window === window.top) window.print()">

<!-- ══════ Parte 1: Comanda para mostrador/cocina (sin precios) ══════ -->
<div class="center bold" style="font-size:14px">COMANDA MOSTRADOR</div>
<div>N° PEDIDO: <?= $v['id'] ?></div>
<div>FECHA: <?= date('d-m-Y H:i', strtotime($v['created_at'])) ?></div>
<?php if (!empty($v['mesa'])): ?>
<div class="bold">Mesa/Dest.: <?= e($v['mesa']) ?></div>
<?php endif; ?>
<div class="line"></div>
<div class="bold">CANT. DESCRIPCION</div>
<div class="line"></div>

<?php
// Ver comanda.php: una promo trae una fila cabecera + una fila por producto que la
// compone (venta_detalle_padre_id). Mismo criterio acá — no repetir "1  Promo..." como
// si fuera un producto más, y marcar sus componentes con "–" para que se lea como parte
// del combo de arriba, no como líneas de pedido sueltas.
$esPadrePromo = [];
foreach ($v['detalle'] as $d) {
    if (!empty($d['venta_detalle_padre_id'])) $esPadrePromo[$d['venta_detalle_padre_id']] = true;
}
?>
<?php foreach ($v['detalle'] as $idx => $d): ?>
<?php
$esPromo       = isset($esPadrePromo[$d['id']]);
$esComponente  = !empty($d['venta_detalle_padre_id']);
$cant          = ($esPromo && (float)$d['cantidad'] == 1) ? '' : number_format($d['cantidad'], 0, ',', '.');
// Mismo criterio que comanda.php: si el nombre no arranca ya con "Promo", se le agrega
// la etiqueta para que no se confunda con un producto normal.
$etiquetaPromo = ($esPromo && stripos($d['producto_nombre'], 'promo') !== 0) ? '[PROMO] ' : '';
?>
<?php if ($esPromo && $idx > 0): ?>
<div class="line"></div>
<?php endif; ?>
<div><?= $cant ?>  <?= $esComponente ? '– ' : '' ?><?= $etiquetaPromo ?><?= e($d['producto_nombre']) ?></div>
<?php endforeach; ?>

<div class="line"></div>
<?php if ($v['notas']): ?>
<div class="bold">NOTA:</div>
<!-- width:92% (no 100%): deja un margen a propósito para que, con el zoom de impresión
     aplicado (TICKET_ZOOM_PCT), el texto siga cortando dentro del área imprimible del
     rollo térmico en vez de perder los últimos caracteres de cada línea contra el borde. -->
<div style="width:92%; white-space:pre-wrap"><?= e($v['notas']) ?></div>
<div class="line"></div>
<?php endif; ?>

<!-- ══════ Corte de papel — parte 2: comprobante fiscal ══════ -->
<div class="center" style="font-size:9px; margin-top:30px">✂ - - - - - - - - - - - - - - - - - - - - - - - - ✂</div>
<div style="margin-top:20px; padding-top:10px; border-top:3px dashed #000;"></div>

<?php require __DIR__ . '/_comprobante_fiscal.php'; ?>

<?php
// Construir texto para compartir — se omiten los componentes de una promo (precio $0,
// ya cobrado en la línea de la promo): listarlos aparte se veía como productos gratis.
$items_txt = '';
foreach ($v['detalle'] as $d) {
    if (!empty($d['venta_detalle_padre_id'])) continue;
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
