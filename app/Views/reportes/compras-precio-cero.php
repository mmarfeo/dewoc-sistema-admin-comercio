<?php $_exportQ = http_build_query(['tipo' => 'compras_precio_cero']); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Compras a precio $0</h1>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes/exportar?' . $_exportQ) ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-table-export me-1"></i> Exportar CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<div class="alert alert-warning d-print-none">
    <strong>¿Qué es esto?</strong> Materias primas e insumos con al menos una compra registrada
    con precio unitario en <strong>$0</strong> — casi siempre un error de carga (se dejó el
    precio vacío en vez de completarlo). El sistema calcula el costo de cada ítem como un
    <strong>promedio ponderado por stock</strong> en cada compra, así que una sola línea a $0
    queda mezclada para siempre en ese promedio y puede inflar o desinflar el costo real que
    después usan las recetas. No se filtra por fecha: una compra vieja a $0 sigue afectando el
    costo actual.
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblComprasPrecioCero" class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Comprobante</th>
                        <th>Proveedor</th>
                        <th>Tipo</th>
                        <th>Ítem</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Costo actual</th>
                        <th class="text-center" style="width:70px">Revisar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <tr>
                        <td class="small"><?= fecha($f['fecha']) ?></td>
                        <td class="small">
                            <a href="<?= url('compras/editar?id=' . (int)$f['factura_id']) ?>" title="Abrir la factura para corregir el precio">
                                <?= e($f['nro_comprobante']) ?>
                            </a>
                        </td>
                        <td class="small"><?= e($f['proveedor'] ?? '—') ?></td>
                        <td class="small"><span class="badge bg-secondary-soft text-secondary"><?= str_replace('_', ' ', $f['tipo_item']) ?></span></td>
                        <td class="small fw-semibold"><?= e($f['item'] ?? '(ítem eliminado)') ?></td>
                        <td class="text-end small"><?= number_format((float)$f['cantidad'], 3, ',', '.') ?></td>
                        <td class="text-end small fw-semibold <?= (float)$f['costo_actual'] > 0 ? 'text-danger' : '' ?>"><?= moneda((float)$f['costo_actual']) ?></td>
                        <td class="text-center">
                            <?php if ($f['tipo_item'] === 'materia_prima' && $f['item_id']): ?>
                            <a href="<?= url('materias-primas/historial-precios?id=' . (int)$f['item_id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver historial de precios">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(() => $('#tblComprasPrecioCero').DataTable({ order: [[0, 'desc']], language: { emptyTable: 'No hay compras cargadas a precio $0. 🎉' } }));
</script>
