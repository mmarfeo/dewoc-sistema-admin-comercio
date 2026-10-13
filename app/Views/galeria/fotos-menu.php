<?php
/**
 * Fotos del menú — listado de productos (ver docs/integracion-menuapp-checklist.md,
 * tarea 8.2). Pensado primero para el celular: filas grandes, tocables con el pulgar, y
 * los productos sin foto arriba, porque es la lista de lo que falta hacer.
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1 fw-bold">Fotos del menú</h1>
        <p class="text-muted small mb-0">
            Sacá o elegí las fotos que ven tus clientes en el menú online. Tocá un producto para ver cómo le queda.
        </p>
    </div>
    <a href="<?= url('galeria') ?>" class="btn btn-outline-secondary btn-sm">← Galería</a>
</div>

<!-- Filtros -->
<form method="get" action="<?= url('galeria/fotos-menu') ?>" class="card border-0 mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="q" class="form-control" placeholder="Nombre o código"
                       value="<?= e($busqueda) ?>">
            </div>
            <div class="col-8 col-md-4">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoriaId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </div>

        <?php if ($puedePublicar): ?>
        <!-- Filtro para el encargado: lo que alguien propuso y falta decidir (tarea 8.8). -->
        <div class="form-check mt-3 mb-0">
            <input class="form-check-input" type="checkbox" name="revisar" value="1" id="chkRevisar"
                   onchange="this.form.submit()" <?= $revisar ? 'checked' : '' ?>>
            <label class="form-check-label small" for="chkRevisar">
                Solo los que tienen candidatas para revisar
            </label>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($productos)): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-5">
        <?php if ($busqueda !== '' || $categoriaId > 0 || $revisar): ?>
        No hay productos que coincidan con el filtro.
        <?php else: ?>
        Todavía no hay productos marcados para el menú online.
        Marcalos con "Mostrar en el menú online" en la ficha de cada producto.
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="list-group shadow-sm">
    <?php foreach ($productos as $p):
        $tieneFoto  = !empty($p['imagen']);
        $candidatas = (int)$p['candidatas'];
    ?>
    <a href="<?= url('galeria/fotos-menu/producto?id=' . (int)$p['id']) ?>"
       class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">

        <!-- Miniatura en 4:3, el mismo recorte que usa la tarjeta del menú -->
        <div class="flex-shrink-0 bg-light rounded overflow-hidden d-flex align-items-center justify-content-center"
             style="width:80px;height:60px">
            <?php if ($tieneFoto): ?>
            <img src="<?= url('uploads/productos/' . rawurlencode($p['imagen'])) ?>"
                 style="width:100%;height:100%;object-fit:cover" alt="<?= e($p['nombre']) ?>">
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="1.5" class="text-secondary">
                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
            </svg>
            <?php endif; ?>
        </div>

        <div class="flex-grow-1 min-width-0">
            <div class="fw-semibold text-truncate"><?= e($p['nombre']) ?></div>
            <div class="small text-muted text-truncate">
                <?= e($p['categoria_nombre'] ?? 'Sin categoría') ?>
                <?php if (!empty($p['codigo'])): ?> · <?= e($p['codigo']) ?><?php endif; ?>
            </div>
            <div class="small mt-1">
                <?php if (!$tieneFoto): ?>
                <span class="badge bg-danger-soft text-danger">Sin foto</span>
                <?php else: ?>
                <span class="badge bg-success-soft text-success">Oficial</span>
                <?php endif; ?>

                <?php if ($candidatas > 0): ?>
                <span class="badge bg-warning-soft text-warning">
                    <?= (int)$candidatas ?> candidata<?= $candidatas === 1 ? '' : 's' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" class="text-secondary flex-shrink-0">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
