<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 fw-bold">Activos Fijos</h1>
        <p class="text-muted small mb-0">Bienes de uso del negocio (equipamiento, mobiliario, etc.), con su valor y amortización, para saber cuánto está valuado.</p>
    </div>
    <a href="<?= url('activos/nuevo') ?>" class="btn btn-primary btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo activo
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Valor de compra total (activos vigentes)</p>
                <h3 class="fw-bold mb-0"><?= moneda($totalHistorico) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Valor actual estimado <span class="text-muted fw-normal">(costo − amortizado)</span></p>
                <h3 class="fw-bold mb-0"><?= moneda($totalActual) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3">
    <a href="<?= url('activos') ?>" class="btn btn-sm <?= $filtroEstado === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
    <a href="<?= url('activos?estado=activo') ?>" class="btn btn-sm <?= $filtroEstado === 'activo' ? 'btn-primary' : 'btn-outline-secondary' ?>">Activos</a>
    <a href="<?= url('activos?estado=baja') ?>" class="btn btn-sm <?= $filtroEstado === 'baja' ? 'btn-primary' : 'btn-outline-secondary' ?>">De baja</a>
    <a href="<?= url('activos?estado=vendido') ?>" class="btn btn-sm <?= $filtroEstado === 'vendido' ? 'btn-primary' : 'btn-outline-secondary' ?>">Vendidos</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Origen</th>
                        <th>Fecha de alta</th>
                        <th class="text-end">Valor de compra</th>
                        <th class="text-end">Valor actual estimado</th>
                        <th>Amortización</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activos)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Todavía no cargaste ningún activo fijo.</td></tr>
                    <?php else: ?>
                    <?php foreach ($activos as $a): ?>
                    <tr class="<?= $a['estado'] === 'activo' ? '' : 'opacity-50' ?>">
                        <td class="fw-semibold">
                            <?= e($a['nombre']) ?>
                            <?php if (!empty($a['factura_compra_id'])): ?>
                            <a href="<?= url('compras/ver?id=' . (int)$a['factura_compra_id']) ?>" class="d-block small text-muted" style="font-size:11px" title="Ver la factura de compra de la que vino este activo">Ver factura de origen →</a>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($a['categoria'] ?: '—') ?></td>
                        <td><span class="badge bg-secondary-soft text-secondary"><?= $a['origen'] === 'compra_nueva' ? 'Compra nueva' : 'Ya lo tenía' ?></span></td>
                        <td><?= fecha($a['fecha_alta']) ?></td>
                        <td class="text-end"><?= moneda((float)$a['valor']) ?></td>
                        <td class="text-end fw-bold"><?= moneda((float)$a['valor_actual']) ?></td>
                        <td class="small">
                            <?php if ($a['gasto_recurrente_id']): ?>
                                <?= moneda((float)$a['cuota_mensual']) ?>/mes
                                <?php if (!$a['amortizacion_activa']): ?><span class="text-muted">(completa)</span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Sin amortizar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $badgeEstado = ['activo' => 'bg-success-soft text-success', 'baja' => 'bg-secondary-soft text-secondary', 'vendido' => 'bg-info-soft text-info'][$a['estado']]; ?>
                            <?php $labelEstado = ['activo' => 'Activo', 'baja' => 'De baja', 'vendido' => 'Vendido'][$a['estado']]; ?>
                            <span class="badge <?= $badgeEstado ?>"><?= $labelEstado ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('activos/editar?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-activo" data-id="<?= $a['id'] ?>" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = '<?= Csrf::token() ?>';
    document.querySelectorAll('.btn-eliminar-activo').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const ejecutar = async () => {
                const fd = new FormData();
                fd.append('_csrf_token', csrf);
                fd.append('id', id);
                const res  = await fetch('<?= url('activos/eliminar') ?>', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.ok) {
                    location.reload();
                } else {
                    alert(json.error || 'No se pudo eliminar.');
                }
            };
            const msg = '¿Eliminar este activo?';
            if (typeof showConfirm === 'function') {
                showConfirm(msg, ejecutar, { label: 'Eliminar' });
            } else {
                if (confirm(msg)) ejecutar();
            }
        });
    });
})();
</script>
