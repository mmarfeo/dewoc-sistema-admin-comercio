<?php $_exportQ = http_build_query(['tipo' => 'ventas_sin_stock', 'desde' => $desde, 'hasta' => $hasta]); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Ventas sin stock</h1>
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
    <strong>¿Qué es esto?</strong> Productos "hechos al momento" (sin control de stock propio, con receta
    asignada — ej. un café) que se vendieron <strong>a pesar de no haber suficiente materia prima o insumos</strong>,
    porque en el momento se confirmó "vender igual". Revisá esta lista para decidir si conviene reponer
    stock, corregir un conteo, o si fue un caso puntual sin importancia.
</div>

<!-- Filtros -->
<div class="card border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblVentasSinStock" class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Venta</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Ingrediente faltante</th>
                        <th class="text-end">Necesitaba</th>
                        <th class="text-end">Había</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <tr>
                        <td class="small"><?= fechaHora($f['created_at']) ?></td>
                        <td class="small">
                            <a href="<?= url('ventas/ver?id=' . (int)$f['venta_id']) ?>" title="Ver la venta">
                                #<?= (int)$f['venta_id'] ?>
                            </a>
                        </td>
                        <td class="small fw-semibold"><?= e($f['producto_nombre']) ?></td>
                        <td class="small"><span class="badge bg-secondary-soft text-secondary"><?= str_replace('_', ' ', $f['tipo_ingrediente']) ?></span></td>
                        <td class="small"><?= e($f['ingrediente_nombre']) ?></td>
                        <td class="text-end small text-danger fw-semibold"><?= number_format((float)$f['cantidad_necesaria'], 3, ',', '.') ?></td>
                        <td class="text-end small"><?= number_format((float)$f['cantidad_disponible'], 3, ',', '.') ?></td>
                        <td class="small"><?= e($f['usuario'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(() => $('#tblVentasSinStock').DataTable({ order: [[0, 'desc']], language: { emptyTable: 'No hay ventas sin stock en este período. 🎉' } }));
</script>
