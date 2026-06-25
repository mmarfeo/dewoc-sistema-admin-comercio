<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Facturas de Compra</h1>
        <?php if ($filtros['proveedor_id']): ?>
            <?php $prov = array_filter($proveedores, fn($p) => $p['id'] == $filtros['proveedor_id']); ?>
            <?php $prov = reset($prov); ?>
            <?php if ($prov): ?>
            <p class="text-muted mb-0 small">
                Historial de compras a <strong><?= e($prov['razon_social']) ?></strong>
                &nbsp;<a href="<?= url('compras') ?>" class="small">× limpiar filtro</a>
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <a href="<?= url('compras/nueva') ?>" class="btn btn-primary">+ Nueva factura</a>
</div>

<!-- Filtros -->
<form method="GET" action="<?= url('compras') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Proveedor</label>
                <select name="proveedor_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($filtros['proveedor_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['razon_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="pagada"   <?= $filtros['estado']==='pagada'   ? 'selected':'' ?>>Pagada</option>
                    <option value="pendiente"<?= $filtros['estado']==='pendiente'? 'selected':'' ?>>Pendiente</option>
                    <option value="anulada"  <?= $filtros['estado']==='anulada'  ? 'selected':'' ?>>Anulada</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('compras') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblCompras" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Fecha</th><th>Proveedor</th><th>Comprobante</th>
                        <th>Subtotal</th><th>IVA</th><th>Total</th><th>Estado</th>
                        <th class="text-center" style="width:100px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas as $f): ?>
                    <tr class="<?= $f['estado']==='anulada' ? 'text-muted' : '' ?>">
                        <td class="small"><?= $f['id'] ?></td>
                        <td class="small"><?= fecha($f['fecha']) ?></td>
                        <td class="fw-semibold"><?= e($f['proveedor_nombre'] ?? '—') ?></td>
                        <td class="small"><?= e(strtoupper(str_replace('_',' ',$f['tipo_comprobante']))) ?> <?= e($f['nro_comprobante'] ?? '') ?></td>
                        <td class="text-end small"><?= moneda($f['subtotal']) ?></td>
                        <td class="text-end small text-muted"><?= moneda($f['total_iva']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($f['total_con_iva']) ?></td>
                        <td>
                            <?php $cls = match($f['estado']){
                                'pagada'   => 'bg-success-soft text-success',
                                'pendiente'=> 'bg-warning-soft text-warning',
                                'anulada'  => 'bg-danger-soft text-danger',
                                default    => 'bg-secondary-soft text-secondary'
                            }; ?>
                            <span class="badge <?= $cls ?>"><?= ucfirst($f['estado']) ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('compras/ver?id='.$f['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver detalle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php if ($f['estado'] !== 'anulada'): ?>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="anular(<?= $f['id'] ?>)" title="Anular">
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
$(document).ready(()=>{ $('#tblCompras').DataTable({order:[[0,'desc']]}); });
async function anular(id){
    if(!confirm('¿Anular esta factura? Se revertirá el stock ingresado.'))return;
    const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
    const r=await fetch(`${BASE_URL}/compras/anular`,{method:'POST',body:fd});
    const d=await r.json();
    d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
}
</script>
