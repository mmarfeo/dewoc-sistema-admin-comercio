<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Historial de costo</h1>
        <p class="text-muted mb-0 small"><?= e($receta['nombre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('recetas/editar?id=' . $receta['id']) ?>" class="btn btn-outline-primary btn-sm">Editar receta</a>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<?php if (!$historial): ?>
<div class="alert alert-info">
    Todavía no hubo cambios de costo registrados para esta receta. Esto se llena automáticamente
    cuando se compra un ingrediente genérico que usa esta receta.
</div>
<?php else: ?>

<div class="card border-0">
    <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Costo actual: <?= moneda($historial[0]['costo_nuevo']) ?></span>
        <span class="badge bg-secondary"><?= count($historial) ?> cambios registrados</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th class="text-end">Costo anterior</th>
                        <th class="text-end">Costo nuevo</th>
                        <th class="text-end">Variación</th>
                        <th>Disparado por</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historial as $h): ?>
                    <?php
                    $anterior  = (float) $h['costo_anterior'];
                    $nuevo     = (float) $h['costo_nuevo'];
                    $variacion = $anterior > 0 ? (($nuevo - $anterior) / $anterior) * 100 : 0;
                    $subio     = $nuevo > $anterior;
                    ?>
                    <tr>
                        <td class="small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td class="text-end small text-muted"><?= moneda($anterior) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($nuevo) ?></td>
                        <td class="text-end">
                            <?php if (abs($variacion) < 0.01): ?>
                            <span class="text-muted small">—</span>
                            <?php else: ?>
                            <span class="badge <?= $subio ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?>">
                                <?= $subio ? '▲' : '▼' ?> <?= number_format(abs($variacion), 1) ?>%
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($h['nro_comprobante'] || $h['proveedor_nombre']): ?>
                                Compra <?= $h['nro_comprobante'] ? '#' . e($h['nro_comprobante']) : '' ?>
                                <?= $h['proveedor_nombre'] ? '— ' . e($h['proveedor_nombre']) : '' ?>
                            <?php else: ?>
                                <span class="text-muted"><?= e($h['motivo'] ?? '—') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
