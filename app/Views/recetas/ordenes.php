<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Órdenes de Producción</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPlanificar">
            + Planificar orden
        </button>
        <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver a Recetas</a>
    </div>
</div>

<!-- Modal Planificar Orden -->
<div class="modal fade" id="modalPlanificar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Planificar orden de producción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPlanificar">
                <?= Csrf::field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Receta</label>
                        <select name="receta_id" class="form-select" required>
                            <option value="">Seleccionar receta...</option>
                            <?php foreach ($recetas as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cantidad a producir</label>
                        <input type="number" name="cantidad" class="form-control" min="0.01" step="0.01" value="1" required>
                    </div>
                    <div id="planificarMsg" class="alert d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnConfPlanificar">Planificar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Receta</label>
                <select name="receta_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($recetas as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (int)$filtros['receta_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= e($r['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label fw-semibold">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['planificada','en_proceso','completada','anulada'] as $est): ?>
                    <option value="<?= $est ?>" <?= $filtros['estado'] === $est ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $est)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-1">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<?php
$totalOrdenes   = count($ordenes);
$totalProducido = array_sum(array_column($ordenes, 'cantidad_producida'));
?>
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h2 fw-bold text-primary mb-0"><?= $totalOrdenes ?></div>
                <div class="text-muted small">Órdenes ejecutadas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h2 fw-bold text-success mb-0"><?= number_format($totalProducido, 2, ',', '.') ?></div>
                <div class="text-muted small">Unidades producidas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 text-center">
            <div class="card-body py-3">
                <div class="h2 fw-bold text-info mb-0">
                    <?= $totalOrdenes > 0 ? number_format($totalProducido / $totalOrdenes, 2, ',', '.') : '0,00' ?>
                </div>
                <div class="text-muted small">Promedio por orden</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblOrdenes" class="table table-hover align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha y hora</th>
                    <th>Receta</th>
                    <th>Producto final</th>
                    <th class="text-end">Cant. producida</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th class="d-print-none"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ordenes as $o): ?>
            <?php
            $estado = $o['estado'] ?? 'completada';
            $color  = match($estado) {
                'completada' => 'success',
                'anulada'    => 'danger',
                'en_proceso' => 'warning',
                'planificada'=> 'info',
                default      => 'secondary',
            };
            ?>
            <tr id="orden-<?= $o['id'] ?>">
                <td>#<?= $o['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                <td class="fw-semibold"><?= e($o['receta_nombre']) ?></td>
                <td><?= $o['producto_nombre'] ? e($o['producto_nombre']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$o['cantidad_producida'], 2, ',', '.') ?></td>
                <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis estado-badge"><?= ucfirst(str_replace('_', ' ', e($estado))) ?></span></td>
                <td><?= e($o['usuario_nombre'] ?? '—') ?></td>
                <td class="d-print-none text-end" style="width:180px">
                    <?php if ($estado === 'planificada'): ?>
                    <button class="btn btn-xs btn-outline-warning me-1" onclick="cambiarEstado(<?= $o['id'] ?>,'en_proceso')">Iniciar</button>
                    <button class="btn btn-xs btn-outline-success me-1" onclick="cambiarEstado(<?= $o['id'] ?>,'completada')">Completar</button>
                    <button class="btn btn-xs btn-outline-danger"  onclick="cambiarEstado(<?= $o['id'] ?>,'anulada')">Anular</button>
                    <?php elseif ($estado === 'en_proceso'): ?>
                    <button class="btn btn-xs btn-outline-success me-1" onclick="cambiarEstado(<?= $o['id'] ?>,'completada')">Completar</button>
                    <button class="btn btn-xs btn-outline-danger"  onclick="cambiarEstado(<?= $o['id'] ?>,'anulada')">Anular</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblOrdenes', {
        language: DT_LANG_ES,
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        order: [[1, 'desc']],
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
        columnDefs: [{ orderable: false, targets: -1 }],
    });
});

// ── Planificar orden ─────────────────────────────────────────────────────────
document.getElementById('formPlanificar').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msgEl = document.getElementById('planificarMsg');
    const btn   = document.getElementById('btnConfPlanificar');
    msgEl.className = 'alert d-none small';
    btn.disabled = true;

    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/recetas/planificar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            msgEl.className = 'alert alert-success small';
            msgEl.textContent = d.mensaje;
            setTimeout(() => location.reload(), 1200);
        } else {
            msgEl.className = 'alert alert-danger small';
            msgEl.textContent = d.error;
        }
    } catch {
        msgEl.className = 'alert alert-danger small';
        msgEl.textContent = 'Error de conexión.';
    } finally {
        btn.disabled = false;
        msgEl.classList.remove('d-none');
    }
});

// ── Cambiar estado de orden ──────────────────────────────────────────────────
async function cambiarEstado(ordenId, nuevoEstado) {
    const etiquetas = { en_proceso: 'iniciar', completada: 'completar', anulada: 'anular' };
    if (!confirm(`¿${(etiquetas[nuevoEstado] || nuevoEstado)} esta orden?`)) return;

    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('orden_id', ordenId);
    fd.append('estado',   nuevoEstado);

    try {
        const r = await fetch(`${BASE_URL}/recetas/ordenes/estado`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            const msg = d.faltantes?.length
                ? 'Stock insuficiente:\n• ' + d.faltantes.join('\n• ')
                : (d.error || 'Error al cambiar estado.');
            alert(msg);
        }
    } catch {
        alert('Error de conexión.');
    }
}
</script>
