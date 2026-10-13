<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Simulador de Recetas</h1>
        <p class="text-muted small mb-0">Armá recetas hipotéticas para costear posibles productos nuevos, antes de darlos de alta de verdad.</p>
    </div>
    <a href="<?= url('recetas-borrador/nuevo') ?>" class="btn btn-primary">+ Nuevo borrador</a>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblBorradores" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Rendimiento</th>
                        <th class="text-center">Ingredientes</th>
                        <th class="text-center">Inventados</th>
                        <th class="text-end">Costo total</th>
                        <th class="text-end">Precio estimado</th>
                        <th class="text-end">Margen</th>
                        <th class="text-center" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php /*
                      Sin filas NO se dibuja una fila con colspan: DataTables cuenta las
                      celdas del tbody para validar la tabla y un colspan le da 1 columna
                      contra las 8 del thead, lo que dispara "Incorrect column count" y
                      deja la tabla sin inicializar (se pierden el orden y el buscador). El
                      cartel de tabla vacía lo pone DataTables con language.emptyTable.
                    */ ?>
                    <?php foreach ($borradores as $b): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($b['nombre']) ?></td>
                        <td class="small"><?= number_format($b['rendimiento'],3,',','.') ?> <?= e($b['unidad_rendimiento'] ?? '') ?></td>
                        <td class="text-center"><span class="badge bg-secondary-soft text-secondary"><?= $b['cant_ingredientes'] ?></span></td>
                        <td class="text-center">
                            <?php if ($b['cant_ingredientes_libres'] > 0): ?>
                            <span class="badge bg-warning-soft text-warning" title="Ingredientes inventados sin reemplazar por uno real"><?= $b['cant_ingredientes_libres'] ?></span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold" data-order="<?= $b['costo_total'] ?>"><?= moneda($b['costo_total']) ?></td>
                        <td class="text-end small"><?= $b['precio_venta_estimado'] !== null ? moneda($b['precio_venta_estimado']) : '—' ?></td>
                        <td class="text-end">
                            <?php if ($b['margen_pct'] === null): ?>
                            <span class="text-muted small">—</span>
                            <?php else: ?>
                            <span class="badge <?= $b['margen_pct'] >= 0 ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>"><?= number_format($b['margen_pct'],1,',','.') ?>%</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('recetas-borrador/editar?id='.$b['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="eliminarBorrador(<?= $b['id'] ?>, '<?= e($b['nombre']) ?>')" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblBorradores').DataTable({
    order: [],
    language: { emptyTable: 'Todavía no creaste ningún borrador.' }
}));

function eliminarBorrador(id, nombre) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/recetas-borrador/eliminar`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
    };
    const msg = `¿Eliminar el borrador "${nombre}"?`;
    if (typeof showConfirm === 'function') { showConfirm(msg, ejecutar, { label: 'Eliminar' }); }
    else { if (confirm(msg)) ejecutar(); }
}
</script>
