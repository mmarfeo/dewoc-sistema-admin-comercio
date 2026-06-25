<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Ventas</h1>
    <a href="<?= url('ventas/pos') ?>" class="btn btn-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Ir al POS
    </a>
</div>

<!-- Filtros -->
<form method="GET" action="<?= url('ventas') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="completada" <?= $filtros['estado']==='completada'?'selected':'' ?>>Completada</option>
                    <option value="anulada"    <?= $filtros['estado']==='anulada'   ?'selected':'' ?>>Anulada</option>
                </select></div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('ventas') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<!-- KPI rápido del período filtrado -->
<?php
$totalPeriodo = array_sum(array_column(
    array_filter($ventas, fn($v) => $v['estado'] === 'completada'),
    'total_con_iva'
));
?>
<div class="alert alert-primary py-2 small mb-3">
    Total del período (completadas): <strong><?= moneda($totalPeriodo) ?></strong>
    — <?= count(array_filter($ventas, fn($v) => $v['estado'] === 'completada')) ?> ventas
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblVentas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Fecha/Hora</th><th>Vendedor</th><th>Cliente</th>
                        <th>Turno</th><th class="text-end">Total</th>
                        <th>Pago</th><th>AFIP</th><th>Estado</th>
                        <th class="text-center" style="width:90px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                    <tr class="<?= $v['estado']==='anulada' ? 'text-muted' : '' ?>">
                        <td class="small"><?= $v['id'] ?></td>
                        <td class="small"><?= fechaHora($v['created_at']) ?></td>
                        <td class="small"><?= e($v['usuario_nombre'] ?? '—') ?></td>
                        <td class="small"><?= e($v['cliente_nombre'] ?? 'Consumidor final') ?></td>
                        <td class="small"><?= $v['turno_numero'] ? '#'.$v['turno_numero'] : '—' ?></td>
                        <td class="text-end fw-semibold"><?= moneda($v['total_con_iva']) ?></td>
                        <td class="small"><?= e(ucfirst(str_replace('_',' ',$v['metodo_pago']))) ?></td>
                        <td class="text-center">
                            <?php if ($v['facturar_afip']): ?>
                            <span class="badge bg-primary-soft text-primary small">AFIP</span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $v['estado']==='completada' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>"><?= ucfirst($v['estado']) ?></span></td>
                        <td class="text-center">
                            <a href="<?= url('ventas/ver?id='.$v['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php if ($v['estado'] !== 'anulada'): ?>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="anular(<?= $v['id'] ?>)" title="Anular">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </button>
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
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblVentas').DataTable({order:[[0,'desc']]}));
async function anular(id){
    if(!confirm('¿Anular esta venta? El stock de los productos se recuperará.'))return;
    const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
    const r=await fetch(`${BASE_URL}/ventas/anular`,{method:'POST',body:fd});
    const d=await r.json();
    d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
}
</script>
