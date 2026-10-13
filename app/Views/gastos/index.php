<?php
$tipoLabels = [
    'costo_fijo'     => ['label' => 'Costo Fijo',     'badge' => 'bg-primary'],
    'costo_variable' => ['label' => 'Costo Variable', 'badge' => 'bg-success'],
    'impuesto'       => ['label' => 'Impuesto',       'badge' => 'bg-danger'],
];
?>
<style>
.gasto-card-link { transition: box-shadow .15s, transform .15s; cursor: pointer; }
.gasto-card-link:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.1); transform: translateY(-1px); }
</style>

<!-- Título -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Gastos</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('gastos/categorias') ?>" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            Categorías
        </a>
        <button id="btnNuevoGasto" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGasto">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo gasto
        </button>
    </div>
</div>

<!-- Resumen del período -->
<div class="row g-3 mb-4">
    <?php
    $qPeriodo = ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta']];
    $cards = [
        ['label' => 'Ingresos',        'valor' => $resumen['ventas'],          'color' => 'success', 'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        // "Compras" sale de facturas_compra, no de gastos — no hay nada que filtrar acá
        // abajo, así que lleva al listado de Compras en vez de aplicar un filtro de tipo.
        ['label' => 'Compras',         'valor' => $resumen['compras'],         'color' => 'warning', 'icon' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
            'href' => url('compras?' . http_build_query($qPeriodo))],
        ['label' => 'Gastos Fijos',    'valor' => $resumen['gastosFijos'],     'color' => 'primary', 'icon' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'href' => url('gastos?' . http_build_query($qPeriodo + ['tipo' => 'costo_fijo'])), 'tipo' => 'costo_fijo'],
        ['label' => 'Gastos Variables','valor' => $resumen['gastosVariables'], 'color' => 'info',    'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'href' => url('gastos?' . http_build_query($qPeriodo + ['tipo' => 'costo_variable'])), 'tipo' => 'costo_variable'],
        ['label' => 'Impuestos',       'valor' => $resumen['impuestos'],       'color' => 'danger',  'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
            'href' => url('gastos?' . http_build_query($qPeriodo + ['tipo' => 'impuesto'])), 'tipo' => 'impuesto'],
    ];
    ?>
    <?php foreach ($cards as $c): ?>
    <?php $activa = isset($c['tipo']) && $filtros['tipo'] === $c['tipo']; ?>
    <div class="col-6 col-lg">
        <?= isset($c['href']) ? '<a href="' . e($c['href']) . '" class="text-decoration-none">' : '' ?>
        <div class="card border-0 h-100<?= $activa ? ' border-' . $c['color'] . ' border-2' : '' ?><?= isset($c['href']) ? ' gasto-card-link' : '' ?>">
            <div class="card-body py-3">
                <p class="small text-muted mb-1"><?= $c['label'] ?><?= $activa ? ' ✓' : '' ?></p>
                <h5 class="fw-bold mb-0 text-<?= $c['color'] ?>"><?= moneda($c['valor']) ?></h5>
            </div>
        </div>
        <?= isset($c['href']) ? '</a>' : '' ?>
    </div>
    <?php endforeach; ?>

    <!-- Resultado -->
    <?php $ok = $resumen['resultado'] >= 0; ?>
    <div class="col-12 col-lg-2">
        <div class="card border-0 h-100 <?= $ok ? 'bg-success-soft' : 'bg-danger-soft' ?>">
            <div class="card-body py-3">
                <p class="small fw-semibold mb-1 <?= $ok ? 'text-success' : 'text-danger' ?>">Resultado</p>
                <h5 class="fw-bold mb-0 <?= $ok ? 'text-success' : 'text-danger' ?>"><?= moneda($resumen['resultado']) ?></h5>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 mb-3">
    <div class="card-body py-3">
        <form method="get" action="<?= url('gastos') ?>" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="costo_fijo"     <?= $filtros['tipo'] === 'costo_fijo'     ? 'selected' : '' ?>>Costos Fijos</option>
                    <option value="costo_variable" <?= $filtros['tipo'] === 'costo_variable' ? 'selected' : '' ?>>Costos Variables</option>
                    <option value="impuesto"       <?= $filtros['tipo'] === 'impuesto'       ? 'selected' : '' ?>>Impuestos</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Categoría</label>
                <select name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($catPlanas as $c): ?>
                    <?php $esHijo = !is_null($c['parent_id']); ?>
                    <option value="<?= $c['id'] ?>"
                        <?= (int)$filtros['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>
                        <?= !$esHijo ? 'disabled style="font-weight:600;color:#444"' : '' ?>>
                        <?= $esHijo ? '  ↳ ' . e($c['nombre']) : e($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Proveedor</label>
                <select name="proveedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int)$filtros['proveedor_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">Filtrar</button>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?= url('gastos') ?>" class="btn btn-outline-secondary btn-sm w-100">Limpiar filtros</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de gastos -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="tablaGastos">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Tipo</th>
                        <th>Proveedor</th>
                        <th class="text-end">Monto</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Usuario</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gastos)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Sin gastos en el período seleccionado.</td></tr>
                    <?php else: ?>
                    <?php foreach ($gastos as $g): ?>
                    <?php $ti = $tipoLabels[$g['categoria_tipo']] ?? ['label' => $g['categoria_tipo'], 'badge' => 'bg-secondary']; ?>
                    <?php $gVencido = $g['estado'] === 'pendiente' && !empty($g['fecha_vencimiento']) && $g['fecha_vencimiento'] < date('Y-m-d'); ?>
                    <tr data-id="<?= $g['id'] ?>"
                        data-fecha="<?= e($g['fecha']) ?>"
                        data-fecha_vencimiento="<?= e($g['fecha_vencimiento'] ?? '') ?>"
                        data-descripcion="<?= e($g['descripcion']) ?>"
                        data-monto="<?= $g['monto'] ?>"
                        data-categoria_id="<?= $g['categoria_id'] ?>"
                        data-proveedor_id="<?= $g['proveedor_id'] ?? '' ?>"
                        data-notas="<?= e($g['notas'] ?? '') ?>"
                        class="<?= $gVencido ? 'table-danger' : '' ?>">
                        <td><?= fecha($g['fecha']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($g['descripcion']) ?></div>
                            <?php if ($g['notas']): ?>
                            <div class="text-muted" style="font-size:.75rem"><?= e($g['notas']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?= e($g['color']) ?>;display:inline-block"></span>
                                <?php if ($g['padre_nombre']): ?>
                                <span class="text-muted"><?= e($g['padre_nombre']) ?> /</span>
                                <?php endif; ?>
                                <?= e($g['categoria_nombre']) ?>
                            </div>
                        </td>
                        <td><span class="badge <?= $ti['badge'] ?>"><?= $ti['label'] ?></span></td>
                        <td class="small"><?= e($g['proveedor_nombre'] ?? '—') ?></td>
                        <td class="text-end fw-bold"><?= moneda((float)$g['monto']) ?></td>
                        <td class="small <?= $gVencido ? 'text-danger fw-semibold' : '' ?>">
                            <?= $g['fecha_vencimiento'] ? fecha($g['fecha_vencimiento']) : '—' ?>
                            <?= $gVencido ? ' ⚠' : '' ?>
                        </td>
                        <td>
                            <span class="badge <?= $g['estado'] === 'pagado' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' ?>">
                                <?= $g['estado'] === 'pagado' ? 'Pagado' : 'Pendiente' ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= e($g['usuario_nombre']) ?></td>
                        <td class="text-center">
                            <?php if ($g['comprobante_archivo']): ?>
                            <a href="<?= url('gastos/comprobante?id=' . $g['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Ver comprobante">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($g['estado'] === 'pendiente' && $g['proveedor_id']): ?>
                            <a href="<?= url('pagos-proveedores/nuevo?proveedor_id=' . $g['proveedor_id']) ?>" class="btn btn-sm btn-outline-success py-0 px-2" title="Registrar pago">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            </a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 btn-editar-gasto" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-gasto" title="Eliminar">
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

<!-- Modal nuevo/editar gasto -->
<div class="modal fade" id="modalGasto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formGasto" enctype="multipart/form-data">
                <input type="hidden" name="_csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" id="gastoId">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalGastoTitulo">Nuevo Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Fecha *</label>
                            <input type="date" name="fecha" id="gastoFecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Vencimiento <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="date" name="fecha_vencimiento" id="gastoFechaVencimiento" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Monto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" id="gastoMonto" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Descripción *</label>
                            <input type="text" name="descripcion" id="gastoDescripcion" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Categoría *</label>
                            <select name="categoria_id" id="gastoCategoriaId" class="form-select" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($catPlanas as $c): ?>
                                <?php $esHijo = !is_null($c['parent_id']); ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= !$esHijo ? 'disabled style="font-weight:600;color:#444"' : 'style="padding-left:1.5rem"' ?>>
                                    <?= $esHijo ? '  ↳ ' . e($c['nombre']) : e($c['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">
                                Proveedor <span class="text-muted fw-normal">(opcional — si lo asignás, el gasto queda "pendiente" hasta que lo pagues desde Pagos a Proveedores)</span>
                            </label>
                            <select name="proveedor_id" id="gastoProveedorId" class="form-select">
                                <option value="">— Sin proveedor (se considera ya pagado) —</option>
                                <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notas</label>
                            <textarea name="notas" id="gastoNotas" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Comprobante <span class="text-muted fw-normal">(PDF/imagen, opcional)</span></label>
                            <input type="file" name="comprobante_archivo" id="gastoComprobante" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                            <div id="gastoComprobanteActual" class="form-text d-none"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarGasto">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf = '<?= Csrf::token() ?>';

    // Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
    // abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
    // (Bootstrap sí registra esa delegación de eventos al importarse).
    function _showModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(el).show();
        } else {
            let btn = document.getElementById('_trigger_' + id);
            if (!btn) {
                btn = document.createElement('button');
                btn.id = '_trigger_' + id;
                btn.type = 'button';
                btn.hidden = true;
                btn.setAttribute('data-bs-toggle', 'modal');
                btn.setAttribute('data-bs-target', '#' + id);
                document.body.appendChild(btn);
            }
            btn.click();
        }
    }

    // Abrir modal nuevo (resetea solo cuando lo dispara el botón "Nuevo gasto")
    document.getElementById('modalGasto').addEventListener('show.bs.modal', function (e) {
        if (e.relatedTarget?.id !== 'btnNuevoGasto') return; // editar: los valores ya se cargaron antes de abrir
        document.getElementById('modalGastoTitulo').textContent = 'Nuevo Gasto';
        document.getElementById('formGasto').reset();
        document.getElementById('gastoId').value = '';
        document.getElementById('gastoFecha').value = '<?= date('Y-m-d') ?>';
        document.getElementById('gastoFechaVencimiento').value = '';
    });

    // Editar
    document.querySelectorAll('.btn-editar-gasto').forEach(btn => {
        btn.addEventListener('click', function () {
            const tr = this.closest('tr');
            document.getElementById('modalGastoTitulo').textContent  = 'Editar Gasto';
            document.getElementById('gastoId').value                 = tr.dataset.id;
            document.getElementById('gastoFecha').value              = tr.dataset.fecha;
            document.getElementById('gastoFechaVencimiento').value   = tr.dataset.fecha_vencimiento || '';
            document.getElementById('gastoDescripcion').value        = tr.dataset.descripcion;
            document.getElementById('gastoMonto').value              = tr.dataset.monto;
            document.getElementById('gastoCategoriaId').value        = tr.dataset.categoria_id;
            document.getElementById('gastoProveedorId').value        = tr.dataset.proveedor_id || '';
            document.getElementById('gastoNotas').value              = tr.dataset.notas;
            _showModal('modalGasto');
        });
    });

    // Submit
    document.getElementById('formGasto').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarGasto');
        btn.disabled = true;

        const data = new FormData(this);
        const id   = data.get('id');
        const url  = id
            ? '<?= url('gastos/actualizar') ?>'
            : '<?= url('gastos/crear') ?>';

        try {
            const res = await fetch(url, { method: 'POST', body: data });
            const json = await res.json();
            if (json.ok) {
                location.reload();
            } else {
                alert(json.error || 'Error al guardar.');
                btn.disabled = false;
            }
        } catch {
            alert('Error de conexión.');
            btn.disabled = false;
        }
    });

    // Eliminar
    document.querySelectorAll('.btn-eliminar-gasto').forEach(btn => {
        btn.addEventListener('click', function () {
            const fila = this.closest('tr');
            const ejecutar = async () => {
                const fd = new FormData();
                fd.append('_csrf_token', csrf);
                fd.append('id', fila.dataset.id);
                const res  = await fetch('<?= url('gastos/eliminar') ?>', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.ok) {
                    fila.remove();
                } else {
                    alert(json.error || 'No se pudo eliminar.');
                }
            };
            if (typeof showConfirm === 'function') {
                showConfirm('¿Eliminar este gasto?', ejecutar, { label: 'Eliminar' });
            } else {
                if (confirm('¿Eliminar este gasto?')) ejecutar();
            }
        });
    });
})();
</script>
