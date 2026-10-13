<?php
/**
 * Ficha técnica de UNA receta (nombre, rendimiento, ingredientes, preparación),
 * pensada para el cocinero — deliberadamente NO muestra ningún campo de costo/precio/
 * desvío. Reutilizada por imprimir.php (una receta, standalone) y libro.php (todas
 * las recetas, una detrás de otra). Espera en scope: $r (receta con 'detalle', de
 * RecetaModel::findByIdConDetalle()) y $version (int, de versionActual()/versionesActuales()).
 */
$logoRel = null;
foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
    if (is_file(BASE_PATH . '/assets/images/logo-empresa.' . $ext)) { $logoRel = 'images/logo-empresa.' . $ext; break; }
}
?>
<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:6px;">
    <div>
        <h1><?= e($r['nombre']) ?></h1>
        <?php if ($r['producto_nombre']): ?><div class="sub">Producto: <?= e($r['producto_nombre']) ?></div><?php endif; ?>
    </div>
    <?php if ($logoRel): ?>
    <img src="<?= asset($logoRel) ?>" alt="Logo" style="max-height:60px; max-width:180px; object-fit:contain;">
    <?php endif; ?>
</div>

<div class="sub" style="margin-bottom:14px;">
    Versión <?= (int)$version ?> · Creada el <?= fecha($r['created_at']) ?>
</div>

<div class="row">
    <div class="col">
        <div class="label">Rendimiento</div>
        <div class="val"><?= cantidad((float)$r['rendimiento']) ?> <?= e($r['unidad_rendimiento'] ?? '') ?></div>
    </div>
    <?php if ($r['porciones']): ?>
    <div class="col">
        <div class="label">Porciones</div>
        <div class="val"><?= (int)$r['porciones'] ?></div>
    </div>
    <?php endif; ?>
    <?php if ($r['tiempo_preparacion']): ?>
    <div class="col">
        <div class="label">Tiempo de preparación</div>
        <div class="val"><?= (int)$r['tiempo_preparacion'] ?> min</div>
    </div>
    <?php endif; ?>
</div>

<?php if ($r['descripcion']): ?>
<div style="font-size:13px; color:#333; margin-bottom:6px;"><?= nl2br(e($r['descripcion'])) ?></div>
<?php endif; ?>

<h2>Ingredientes</h2>
<table>
    <thead>
        <tr>
            <th>Ingrediente</th>
            <th class="right">Cantidad</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($r['detalle'] as $d): ?>
        <tr>
            <td><?= e($d['ingrediente_nombre'] ?? '—') ?></td>
            <td class="right"><?= cantidad((float)$d['cantidad']) ?> <?= e($d['unidad'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($r['instrucciones']): ?>
<h2>Preparación</h2>
<div class="prep"><?= nl2br(e($r['instrucciones'])) ?></div>
<?php endif; ?>

<?php if ($r['notas']): ?>
<h2>Notas</h2>
<div class="prep"><?= nl2br(e($r['notas'])) ?></div>
<?php endif; ?>
