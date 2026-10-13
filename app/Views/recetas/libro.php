<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 13px; color: #212529; background: #f1f3f5; padding: 0 0 30px; }
.wrap { max-width: 900px; margin: 0 auto; padding: 0 20px; }
h1 { font-size: 22px; margin-bottom: 2px; }
.sub { color: #666; font-size: 12px; margin-bottom: 14px; }
.row { display: flex; gap: 24px; margin-bottom: 16px; flex-wrap: wrap; }
.col { flex: 1; min-width: 120px; }
.label { color: #868e96; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.val { font-size: 14px; font-weight: 600; }

/* Encabezados de sección (Ingredientes/Preparación/Notas) con acento de color en vez
   del gris plano de antes — ayuda a separar visualmente los bloques de información. */
h2 {
    font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #495057;
    margin: 18px 0 8px; padding: 5px 0 5px 10px; border-left: 4px solid #0d6efd;
    background: #eaf2ff; border-radius: 0 4px 4px 0;
}

table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
thead th { background: #f8f9fa; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; color: #868e96; border-bottom: 2px solid #dee2e6; }
tbody td { padding: 7px 8px; border-bottom: 1px solid #f1f3f5; vertical-align: top; }
.right { text-align: right; }
.prep { white-space: pre-wrap; line-height: 1.5; font-size: 13px; }

.actions { font-family: -apple-system, sans-serif; display: flex; gap: 8px; padding: 16px 20px 0; flex-wrap: wrap; }
.btn { padding: 7px 14px; border: 1px solid #dee2e6; border-radius: 8px; cursor: pointer; font-size: 12px; text-decoration: none; background: #fff; color: #212529; display: inline-flex; align-items: center; gap: 5px; }
.btn:hover { background: #f8f9fa; }
.btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.btn-primary:hover { background: #0b5ed7; }

/* ── Buscador: queda pegado arriba de todo al hacer scroll ──────────────────── */
.search-bar {
    position: sticky; top: 0; z-index: 50;
    background: #fff; border-bottom: 1px solid #dee2e6; box-shadow: 0 2px 8px rgba(0,0,0,.05);
    padding: 12px 20px; margin-top: 16px;
}
.search-bar input {
    width: 100%; max-width: 900px; margin: 0 auto; display: block;
    padding: 9px 14px; border: 1.5px solid #dee2e6; border-radius: 8px; font-size: 14px;
}
.search-bar input:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
.search-bar .contador { text-align: center; font-size: 11px; color: #868e96; margin-top: 6px; }

/* Portada + índice */
.portada { text-align: center; padding: 40px 0 30px; }
.portada h1 { font-size: 30px; margin-bottom: 8px; }
.tarjeta { background: #fff; border: 1px solid #e3e6ea; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 20px; margin-bottom: 20px; }
.indice ol { list-style: decimal; padding-left: 22px; }
.indice li { padding: 6px 0; border-bottom: 1px dashed #eee; }
.indice a { color: #0d6efd; text-decoration: none; font-weight: 600; }
.indice a:hover { text-decoration: underline; }
.indice .ver { color: #888; font-size: 11px; }

/* Cada receta es un recuadro propio con una franja de color arriba — el color cicla
   entre 8 tonos (mismo criterio que los cuadrados de producto del POS), asignado desde
   PHP con var(--acento) inline (nth-child no sirve acá: cada tarjeta es hija única de
   su .receta-item, así que siempre sería la posición 1). Solo ayuda a distinguir una
   receta de la siguiente al hojear, no tiene otro significado. */
.receta-item { page-break-before: always; }
.receta-tarjeta {
    background: #fff; border: 1px solid #e3e6ea; border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 22px; margin-bottom: 26px;
    border-top: 6px solid var(--acento, #0d6efd);
}

.receta-header-acciones { display: flex; gap: 10px; margin-top: 10px; }
.receta-header-acciones a { font-size: 11px; color: #495057; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.receta-header-acciones a:hover { color: #0d6efd; }

.volver { font-size: 11px; margin-top: 8px; }
.volver a { color: #888; text-decoration: none; }
.volver a:hover { text-decoration: underline; }

.sin-resultados { text-align: center; color: #868e96; padding: 40px 0; display: none; }

@media print {
    body { background: #fff; padding: 0; }
    .actions, .search-bar, .sin-resultados { display: none !important; }
    .receta-item { display: block !important; }
    .tarjeta, .receta-tarjeta { box-shadow: none; border-color: #ccc; }
    @page { size: A4; margin: 15mm; }
}
</style>
</head>
<body>

<div class="actions">
    <button class="btn btn-primary" onclick="window.print()">🖨 Imprimir libro completo / Guardar PDF</button>
    <button class="btn" id="btnCompartir" onclick="compartirLibro()">🔗 Compartir</button>
    <a href="<?= url('recetas') ?>" class="btn">← Volver a Recetas</a>
</div>

<div class="search-bar">
    <input type="text" id="buscadorLibro" placeholder="Buscar receta por nombre o producto..." oninput="filtrarLibro(this.value)" autocomplete="off">
    <div class="contador" id="contadorLibro"></div>
</div>

<div class="wrap">

<div class="portada">
    <h1><?= e(NEGOCIO_NOMBRE) ?></h1>
    <div class="sub" style="font-size:16px; margin-top:6px;">Libro de Recetas</div>
    <div class="sub"><?= count($recetas) ?> receta<?= count($recetas) === 1 ? '' : 's' ?> · Generado el <?= date('d/m/Y') ?></div>
</div>

<div class="tarjeta indice" id="indice">
    <h2 style="margin-top:0">Índice</h2>
    <ol>
        <?php foreach ($recetas as $r): ?>
        <li data-nombre-indice="<?= e(mb_strtolower($r['nombre'] . ' ' . ($r['producto_nombre'] ?? ''))) ?>">
            <a href="#receta-<?= (int)$r['id'] ?>"><?= e($r['nombre']) ?></a>
            <span class="ver">— v<?= (int)$r['version'] ?><?= $r['producto_nombre'] ? ' · ' . e($r['producto_nombre']) : '' ?></span>
        </li>
        <?php endforeach; ?>
        <?php if (!$recetas): ?>
        <li class="sub" style="list-style:none">No hay recetas cargadas todavía.</li>
        <?php endif; ?>
    </ol>
</div>

<?php
$paletaAcentos = ['#e64980', '#e8590c', '#d4a017', '#2f9e44', '#0ca678', '#1971c2', '#7048e8', '#a61e4d'];
foreach ($recetas as $i => $r): ?>
<div class="receta-item" id="receta-<?= (int)$r['id'] ?>" data-nombre="<?= e(mb_strtolower($r['nombre'] . ' ' . ($r['producto_nombre'] ?? ''))) ?>">
    <div class="receta-tarjeta" style="--acento: <?= $paletaAcentos[$i % 8] ?>">
        <?php $version = $r['version']; ?>
        <?php require __DIR__ . '/_ficha.php'; ?>
        <div class="receta-header-acciones">
            <a href="<?= url('recetas/imprimir?id=' . (int)$r['id']) ?>" target="_blank">🖨 Imprimir / compartir solo esta receta</a>
        </div>
        <div class="volver"><a href="#indice">↑ Volver al índice</a></div>
    </div>
</div>
<?php endforeach; ?>

<div class="sin-resultados" id="sinResultadosLibro">Ninguna receta coincide con la búsqueda.</div>

<div style="color:#adb5bd; font-size:9px; margin-top:10px; border-top:1px solid #e9ecef; padding-top:6px;">
    Generado por DEWOC — <?= date('d/m/Y H:i') ?>
</div>

</div>

<script>
function filtrarLibro(q) {
    const texto = (q || '').trim().toLowerCase();
    const items = document.querySelectorAll('.receta-item');
    const filasIndice = document.querySelectorAll('#indice li[data-nombre-indice]');
    let visibles = 0;

    items.forEach(el => {
        const matchea = !texto || el.dataset.nombre.includes(texto);
        el.style.display = matchea ? '' : 'none';
        if (matchea) visibles++;
    });
    filasIndice.forEach(li => {
        li.style.display = (!texto || li.dataset.nombreIndice.includes(texto)) ? '' : 'none';
    });

    document.getElementById('sinResultadosLibro').style.display = (texto && visibles === 0) ? 'block' : 'none';
    document.getElementById('contadorLibro').textContent = texto
        ? `${visibles} de <?= count($recetas) ?> recetas`
        : '';
}

async function compartirLibro() {
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
