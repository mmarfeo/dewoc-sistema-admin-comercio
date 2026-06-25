<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Historial de versiones</h1>
        <p class="text-muted mb-0 small"><?= e($receta['nombre']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('recetas/editar?id=' . $receta['id']) ?>" class="btn btn-outline-primary btn-sm">Editar receta</a>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<?php if (!$versiones): ?>
<div class="alert alert-info">Esta receta todavía no tiene versiones guardadas. Se registrará una versión automáticamente la próxima vez que se guarde o edite.</div>
<?php else: ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-3">
        <span class="badge bg-secondary"><?= count($versiones) ?> versiones</span>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="accordionVersiones">
        <?php foreach ($versiones as $i => $v): ?>
            <?php
                $detalle = json_decode($v['detalle_json'], true) ?: [];
                $esActual = ($i === 0);
            ?>
            <div class="accordion-item border-0 border-bottom">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $esActual ? '' : 'collapsed' ?> fw-semibold"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#ver<?= $v['id'] ?>">
                        <span class="me-3">
                            <span class="badge <?= $esActual ? 'bg-success' : 'bg-secondary' ?> me-2">v<?= $v['version'] ?></span>
                            <?= e($v['nombre']) ?>
                        </span>
                        <small class="text-muted fw-normal ms-auto me-3">
                            <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
                            <?php if ($v['usuario_nombre']): ?>
                            · <?= e($v['usuario_nombre']) ?>
                            <?php endif; ?>
                        </small>
                        <?php if ($esActual): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle me-2">Actual</span>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="ver<?= $v['id'] ?>" class="accordion-collapse collapse <?= $esActual ? 'show' : '' ?>"
                     data-bs-parent="#accordionVersiones">
                    <div class="accordion-body">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <div class="small text-muted">Rendimiento</div>
                                <div class="fw-semibold"><?= number_format($v['rendimiento'], 3) ?> <?= e($v['unidad_rendimiento']) ?></div>
                            </div>
                            <div class="col-sm-4">
                                <div class="small text-muted">Ingredientes</div>
                                <div class="fw-semibold"><?= count($detalle) ?></div>
                            </div>
                        </div>

                        <?php if ($detalle): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ingrediente</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Merma %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($detalle as $ing): ?>
                                    <tr>
                                        <td><?= e($ing['nombre'] ?: '—') ?></td>
                                        <td>
                                            <span class="badge <?= $ing['tipo'] === 'materia_prima' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' ?> border <?= $ing['tipo'] === 'materia_prima' ? 'border-primary-subtle' : 'border-info-subtle' ?>">
                                                <?= $ing['tipo'] === 'materia_prima' ? 'M. Prima' : 'Insumo' ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= number_format($ing['cantidad'], 3) ?></td>
                                        <td class="text-end"><?= $ing['merma_pct'] > 0 ? number_format($ing['merma_pct'], 1) . '%' : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small mb-0">Sin detalle de ingredientes registrado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
