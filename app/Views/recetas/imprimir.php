<?php
$r = $receta;
// Mismo criterio de color que libro.php (ciclo de 8 tonos por id) para que una receta
// se vea igual si se imprime suelta o dentro del libro completo.
$paletaAcentos = ['#e64980', '#e8590c', '#d4a017', '#2f9e44', '#0ca678', '#1971c2', '#7048e8', '#a61e4d'];
$acento = $paletaAcentos[((int)$r['id']) % 8];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 13px; color: #212529; background: #f1f3f5; padding: 20px; }
.wrap { max-width: 800px; margin: 0 auto; }
h1 { font-size: 22px; margin-bottom: 2px; }
.sub { color: #666; font-size: 12px; margin-bottom: 14px; }
.row { display: flex; gap: 24px; margin-bottom: 16px; flex-wrap: wrap; }
.col { flex: 1; min-width: 120px; }
.label { color: #868e96; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.val { font-size: 14px; font-weight: 600; }
h2 {
    font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #495057;
    margin: 18px 0 8px; padding: 5px 0 5px 10px; border-left: 4px solid <?= $acento ?>;
    background: #f8f9fa; border-radius: 0 4px 4px 0;
}
table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
thead th { background: #f8f9fa; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; color: #868e96; border-bottom: 2px solid #dee2e6; }
tbody td { padding: 7px 8px; border-bottom: 1px solid #f1f3f5; vertical-align: top; }
.right { text-align: right; }
.prep { white-space: pre-wrap; line-height: 1.5; font-size: 13px; }
.actions { font-family: -apple-system, sans-serif; display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.btn { padding: 7px 14px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer; font-size: 12px; text-decoration: none; background: #fff; color: #212529; display: inline-flex; align-items: center; gap: 5px; }
.btn:hover { background: #f8f9fa; }
.btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.btn-primary:hover { background: #0b5ed7; }
.tarjeta {
    background: #fff; border: 1px solid #e3e6ea; border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 22px;
    border-top: 6px solid <?= $acento ?>;
}
@media print {
    body { background: #fff; padding: 0; }
    .actions { display: none; }
    .tarjeta { box-shadow: none; border-color: #ccc; }
    @page { size: A4; margin: 15mm; }
}
</style>
</head>
<body onload="window.print()">

<div class="wrap">

<div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <button class="btn" id="btnCompartir" onclick="compartirReceta()">🔗 Compartir</button>
    <a href="<?= url('recetas/editar?id=' . $r['id']) ?>" class="btn">← Volver</a>
</div>

<div class="tarjeta">
<?php require __DIR__ . '/_ficha.php'; ?>
</div>

<div style="color:#adb5bd; font-size:9px; margin-top:14px;">
    Generado por DEWOC — <?= date('d/m/Y H:i') ?>
</div>

</div>

<script>
async function compartirReceta() {
    const btn = document.getElementById('btnCompartir');
    const url = window.location.href;
    if (navigator.share) {
        try { await navigator.share({ title: document.title, url }); } catch { /* el usuario canceló el share sheet */ }
        return;
    }
    try {
        await navigator.clipboard.writeText(url);
        const textoOriginal = btn.textContent;
        btn.textContent = '✅ Enlace copiado';
        setTimeout(() => { btn.textContent = textoOriginal; }, 1800);
    } catch {
        prompt('Copiá el enlace:', url);
    }
}
</script>

</body>
</html>
