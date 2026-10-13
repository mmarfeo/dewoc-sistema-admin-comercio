<?php
/**
 * Comprobante fiscal estilo ARCA/AFIP (una factura autorizada con CAE), para
 * incluir dentro de ticket.php / comanda.php. Espera $v (venta con detalle,
 * de VentaModel::findByIdConDetalle()) en scope.
 *
 * Si la venta no tiene CAE (no se facturó por AFIP), se imprime un
 * comprobante simple no válido como factura, sin el bloque ARCA/QR.
 */
$letras     = [1 => 'A', 6 => 'B', 11 => 'C'];
$letra      = $letras[(int) ($v['afip_tipo_cbte'] ?? 0)] ?? '?';
$civaLabels = [
    'responsable_inscripto' => 'IVA Responsable Inscripto',
    'monotributista'        => 'Monotributista',
    'exento'                => 'IVA Exento',
    'consumidor_final'      => 'Consumidor final',
];
$docLabels  = ['dni' => 'DNI', 'cuit' => 'CUIT'];

$tieneCliente = !empty($v['cliente_doc']);
$civaReceptor = $tieneCliente ? ($civaLabels[$v['cliente_civa'] ?? ''] ?? 'Consumidor final') : 'Consumidor final';
$docLabel     = $tieneCliente ? ($docLabels[$v['cliente_tipo_doc'] ?? ''] ?? 'DNI') : 'DNI';
$docNro       = $tieneCliente ? $v['cliente_doc'] : '99999999';
$nombreRecep  = $tieneCliente ? ($v['cliente_nombre'] ?: 'Consumidor Final') : 'Consumidor Final';
$domicilioRec = $tieneCliente ? ($v['cliente_direccion'] ?: 'Sin especificar') : 'Sin especificar';

$metodoLabels = [
    'efectivo' => 'Efectivo', 'debito' => 'Tarjeta de Débito', 'credito' => 'Tarjeta de Crédito',
    'transferencia' => 'Transferencia', 'mercadopago' => 'Mercado Pago',
];

// Desglose informativo Subtotal/Descuento/Total (el total legal ya quedó
// autorizado por AFIP en total_con_iva; esto es solo para mostrar en el ticket).
$subtotalBruto  = array_sum(array_column($v['detalle'], 'subtotal_con_iva'));
$descuentoMonto = round($subtotalBruto * ((float) $v['descuento_global'] / 100), 2);
$subtotalConDto = round($subtotalBruto - $descuentoMonto, 2);

$esFiscal = !empty($v['facturar_afip']) && !empty($v['afip_cae']);
?>
<div class="center bold" style="font-size:17px; margin-top:6px"><?= $esFiscal ? 'ORIGINAL' : 'NO VÁLIDO COMO FACTURA' ?></div>
<div class="center bold" style="font-size:14px; margin-top:4px"><?= e(NEGOCIO_NOMBRE) ?></div>
<div class="center" style="font-size:11px">CUIT: <?= e(AFIP_CUIT ? substr(AFIP_CUIT, 0, 2) . '-' . substr(AFIP_CUIT, 2, 8) . '-' . substr(AFIP_CUIT, 10, 1) : '—') ?></div>
<?php if (NEGOCIO_IIBB): ?>
<div class="center" style="font-size:11px">IIBB: <?= e(NEGOCIO_IIBB) ?></div>
<?php endif; ?>
<div class="center" style="font-size:11px"><?= e(NEGOCIO_CONDICION_FISCAL) ?></div>
<div class="center" style="font-size:11px"><?= e(NEGOCIO_DIRECCION) ?></div>
<?php if (NEGOCIO_INICIO_ACTIVIDADES): ?>
<div class="center" style="font-size:11px">Inicio de act.: <?= e(NEGOCIO_INICIO_ACTIVIDADES) ?></div>
<?php endif; ?>
<div class="line"></div>

<?php if ($esFiscal): ?>
<div class="center bold" style="font-size:13px">FACTURA '<?= $letra ?>' (Cod. <?= (int) $v['afip_tipo_cbte'] ?>)</div>
<div class="center bold" style="font-size:12px">N° <?= str_pad((string) $v['afip_pto_vta'], 4, '0', STR_PAD_LEFT) ?>-<?= str_pad((string) $v['afip_nro_cbte'], 8, '0', STR_PAD_LEFT) ?></div>
<div class="center" style="font-size:11px">Fecha: <?= date('d-m-Y H:i:s', strtotime($v['created_at'])) ?></div>
<div class="line"></div>

<div style="font-size:11px"><?= e($civaReceptor) ?></div>
<div style="font-size:11px"><?= e($docLabel) ?>: <?= e($docNro) ?></div>
<div style="font-size:11px"><?= e($nombreRecep) ?></div>
<div style="font-size:11px"><?= e($domicilioRec) ?></div>
<?php if ($v['metodo_pago'] === 'combinado'): ?>
<?php foreach ($metodoLabels as $medio => $label):
    $montoMedio = (float) ($v['monto_' . $medio] ?? 0);
    if ($montoMedio <= 0.009) continue;
?>
<div style="font-size:11px"><?= e($label) ?>: $<?= number_format($montoMedio, 2, ',', '.') ?></div>
<?php endforeach; ?>
<?php else: ?>
<div style="font-size:11px"><?= e($metodoLabels[$v['metodo_pago']] ?? ucfirst($v['metodo_pago'])) ?></div>
<?php endif; ?>
<div class="line"></div>

<?php foreach ($v['detalle'] as $d): ?>
<div style="font-size:11px"><?= number_format($d['cantidad'], 2, ',', '.') ?> u. x $<?= number_format($d['precio_unitario'], 2, ',', '.') ?></div>
<table style="font-size:12px; margin-bottom:3px">
    <tr>
        <td><?= e($d['producto_nombre']) ?></td>
        <td class="right" style="width:60px">$<?= number_format($d['subtotal_con_iva'], 2, ',', '.') ?></td>
    </tr>
</table>
<?php endforeach; ?>

<div class="line"></div>
<table style="font-size:12px">
    <tr><td>Subtotal:</td><td class="right">$<?= number_format($subtotalBruto, 2, ',', '.') ?></td></tr>
    <tr><td>Descuento:</td><td class="right">$<?= number_format($descuentoMonto, 2, ',', '.') ?></td></tr>
    <tr><td>Subtotal con Dto.:</td><td class="right">$<?= number_format($subtotalConDto, 2, ',', '.') ?></td></tr>
    <tr><td>Otros Tributos:</td><td class="right">$0,00</td></tr>
    <tr class="total-row"><td>Total:</td><td class="right">$<?= number_format($v['total_con_iva'], 2, ',', '.') ?></td></tr>
</table>
<div class="line"></div>

<div class="center bold" style="font-size:19px; margin-top:4px">ARCA</div>
<div class="center" style="font-size:9px">AGENCIA DE RECAUDACIÓN Y CONTROL ADUANERO</div>
<div class="center" style="font-size:9px; margin-top:2px">Esta Agencia no se responsabiliza por los datos ingresados en el detalle de la operación</div>

<div style="font-size:11px; margin-top:8px">CAE N°: <?= e($v['afip_cae']) ?></div>
<div style="font-size:11px">Vto. de CAE: <?= $v['afip_cae_vto'] ? date('d-m-Y', strtotime($v['afip_cae_vto'])) : '—' ?></div>

<div class="center" style="font-size:10px; margin-top:6px"><?= e(NEGOCIO_ORIENTACION_CONSUMIDOR) ?></div>

<?php $qrDataUri = AfipWsfe::qrImageDataUri($v); ?>
<?php if ($qrDataUri): ?>
<div class="center" style="margin-top:8px">
    <img src="<?= e($qrDataUri) ?>" width="160" height="160" alt="QR AFIP">
</div>
<?php endif; ?>

<div class="center" style="font-size:9px; margin-top:6px">Comprobante autorizado por el sistema Módulo 1</div>

<?php else: ?>
<div class="center" style="font-size:12px; margin-top:6px">Comprobante interno — sin validez fiscal.</div>
<?php endif; ?>
