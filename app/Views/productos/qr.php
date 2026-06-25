<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.1); padding: 2rem; max-width: 480px; width: 100%; }
        h1 { font-size: 1.1rem; font-weight: 700; margin-bottom: .25rem; }
        .sub { color: #6c757d; font-size: .85rem; margin-bottom: 1.5rem; }
        .tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
        .tab-btn { flex: 1; padding: .5rem; border: 1px solid #dee2e6; background: #f8f9fa; border-radius: 8px; cursor: pointer; font-size: .875rem; font-weight: 600; color: #495057; transition: all .15s; }
        .tab-btn.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
        .code-area { display: flex; flex-direction: column; align-items: center; gap: 1rem; min-height: 140px; }
        #qrCanvas, #barcodeCanvas { max-width: 100%; }
        .code-value { font-family: monospace; font-size: 1rem; letter-spacing: .1em; color: #212529; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn { padding: .5rem 1.25rem; border-radius: 8px; border: 1px solid transparent; cursor: pointer; font-size: .875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem; }
        .btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
        .btn-outline { background: #fff; color: #495057; border-color: #dee2e6; }
        .btn:hover { opacity: .88; }
        @media print {
            body { background: #fff; padding: 0; }
            .card { box-shadow: none; padding: 1rem; }
            .tabs, .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1><?= e($producto['nombre']) ?></h1>
    <div class="sub">
        <?php if ($producto['codigo']): ?>Código: <strong><?= e($producto['codigo']) ?></strong> &nbsp;·&nbsp; <?php endif; ?>
        Categoría: <?= e($producto['categoria_nombre'] ?? '—') ?>
    </div>

    <?php
        $codigoQr      = BASE_URL . '/?url=tienda/producto&id=' . $producto['id'];
        $codigoBarras  = $producto['codigo'] ?: (string)$producto['id'];
    ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="mostrar('qr', this)">Código QR</button>
        <button class="tab-btn" onclick="mostrar('barcode', this)">Código de barras</button>
    </div>

    <div class="code-area" id="areaQr">
        <canvas id="qrCanvas"></canvas>
        <div class="code-value small text-muted"><?= e($codigoQr) ?></div>
    </div>

    <div class="code-area" id="areaBarcode" style="display:none">
        <svg id="barcodeSvg"></svg>
        <div class="code-value"><?= e($codigoBarras) ?></div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" onclick="descargar()">⬇ Descargar</button>
        <button class="btn btn-outline" onclick="window.print()">🖨 Imprimir</button>
        <a class="btn btn-outline" href="javascript:history.back()">← Volver</a>
    </div>
</div>

<!-- QRCode.js -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
const QR_DATA      = <?= json_encode($codigoQr) ?>;
const BARCODE_DATA = <?= json_encode($codigoBarras) ?>;
const NOMBRE       = <?= json_encode($producto['nombre']) ?>;

let modoActual = 'qr';

// Generar QR en canvas
QRCode.toCanvas(document.getElementById('qrCanvas'), QR_DATA, { width: 220, margin: 2 }, function(err) {
    if (err) console.error(err);
});

// Generar código de barras en SVG
try {
    JsBarcode('#barcodeSvg', BARCODE_DATA, {
        format: 'CODE128',
        width: 2,
        height: 80,
        displayValue: true,
        fontOptions: 'bold',
        fontSize: 14,
    });
} catch(e) {
    document.getElementById('barcodeSvg').insertAdjacentHTML('afterend',
        '<p class="text-muted small">No se pudo generar el código de barras para este valor.</p>');
}

function mostrar(modo, btn) {
    modoActual = modo;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('areaQr').style.display      = modo === 'qr'      ? 'flex' : 'none';
    document.getElementById('areaBarcode').style.display = modo === 'barcode' ? 'flex' : 'none';
}

function descargar() {
    if (modoActual === 'qr') {
        const link = document.createElement('a');
        link.download = 'qr-' + NOMBRE.replace(/[^a-z0-9]/gi, '_') + '.png';
        link.href = document.getElementById('qrCanvas').toDataURL('image/png');
        link.click();
    } else {
        const svg  = document.getElementById('barcodeSvg');
        const data = new XMLSerializer().serializeToString(svg);
        const blob = new Blob([data], { type: 'image/svg+xml' });
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = 'barcode-' + NOMBRE.replace(/[^a-z0-9]/gi, '_') + '.svg';
        link.href = url;
        link.click();
        URL.revokeObjectURL(url);
    }
}
</script>
</body>
</html>
