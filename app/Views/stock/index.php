<?php
$bajosCount = count($bajoMinimo);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Control de Stock</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('stock/inventario') ?>" class="btn btn-outline-primary btn-sm">📋 Inventario físico</a>
        <a href="<?= url('stock/reporte') ?>" class="btn btn-outline-secondary btn-sm">📊 Reporte comparativo</a>
    </div>
</div>

<?php if ($bajosCount): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <strong><?= $bajosCount ?> ítem<?= $bajosCount>1?'s':'' ?> con stock bajo el mínimo.</strong>
    <a href="#alertas" class="ms-auto small">Ver →</a>
</div>
<?php endif; ?>

<!-- Modal ajuste de stock -->
<div class="modal fade" id="modalAjuste" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Ajuste de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAjuste">
                <?= Csrf::field() ?>
                <input type="hidden" id="ajTipo" name="tipo_item">
                <input type="hidden" id="ajId"   name="item_id">
                <div class="modal-body">
                    <p id="ajNombre" class="fw-bold mb-3"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Stock actual</label>
                            <input type="number" id="ajStockActual" class="form-control" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ajuste (+/-)</label>
                            <input type="number" id="ajCantidad" name="cantidad" class="form-control" step="0.001" placeholder="Ej: -2 o +5">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Motivo</label>
                            <input type="text" name="motivo" class="form-control" maxlength="255" placeholder="Merma, rotura, diferencia de inventario...">
                        </div>
                    </div>
                    <div id="ajError" class="alert alert-danger mt-3 d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnAjustar">Aplicar ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblStock" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Tipo</th><th>Categoría</th><th>Código</th>
                        <th class="text-end">Stock actual</th>
                        <th class="text-end">Stock mín.</th>
                        <th class="text-end">Costo unit.</th>
                        <th>Unidad</th><th>Estado</th>
                        <th class="text-center" style="width:80px">Ajustar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <?php $bajo = $it['stock_actual'] <= $it['stock_minimo']; ?>
                    <tr class="<?= $bajo ? 'table-warning' : '' ?>">
                        <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                        <td><span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ',$it['tipo'])) ?></span></td>
                        <td class="small"><?= e($it['categoria'] ?? '—') ?></td>
                        <td class="small text-muted"><?= e($it['codigo'] ?? '—') ?></td>
                        <td class="text-end <?= $bajo ? 'text-danger fw-bold' : 'text-success fw-semibold' ?>">
                            <?= number_format($it['stock_actual'],3,',','.') ?>
                        </td>
                        <td class="text-end small text-muted"><?= number_format($it['stock_minimo'],3,',','.') ?></td>
                        <td class="text-end small"><?= moneda($it['costo']) ?></td>
                        <td class="small text-muted"><?= e($it['unidad'] ?? '') ?></td>
                        <td>
                            <?php if ($bajo): ?>
                            <span class="badge bg-danger-soft text-danger">Bajo mínimo</span>
                            <?php else: ?>
                            <span class="badge bg-success-soft text-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning py-0"
                                onclick="abrirAjuste('<?= e($it['tipo']) ?>',<?= $it['id'] ?>,'<?= e($it['nombre']) ?>',<?= $it['stock_actual'] ?>)"
                                title="Ajustar stock">±</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($bajosCount): ?>
<h5 class="fw-bold mt-4 mb-3" id="alertas">⚠ Ítems bajo el mínimo</h5>
<div class="row g-2">
    <?php foreach ($bajoMinimo as $b): ?>
    <div class="col-md-4">
        <div class="card border-danger border-opacity-25">
            <div class="card-body py-2">
                <div class="fw-semibold small"><?= e($b['nombre']) ?></div>
                <div class="d-flex justify-content-between small">
                    <span class="text-danger">Stock: <?= number_format($b['stock_actual'],3,',','.') ?></span>
                    <span class="text-muted">Mín: <?= number_format($b['stock_minimo'],3,',','.') ?></span>
                </div>
                <span class="badge bg-secondary-soft text-secondary" style="font-size:10px"><?= e(str_replace('_',' ',$b['tipo'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblStock').DataTable());

function abrirAjuste(tipo, id, nombre, stock){
    document.getElementById('ajTipo').value=tipo;
    document.getElementById('ajId').value=id;
    document.getElementById('ajNombre').textContent=nombre;
    document.getElementById('ajStockActual').value=stock;
    document.getElementById('ajCantidad').value='';
    document.getElementById('ajError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('modalAjuste')).show();
}

document.getElementById('formAjuste').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('ajError').classList.add('d-none');
    document.getElementById('btnAjustar').disabled=true;
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/stock/ajuste`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){
            bootstrap.Modal.getInstance(document.getElementById('modalAjuste'))?.hide();
            showToast(d.mensaje);
            setTimeout(()=>location.reload(),800);
        } else { const e2=document.getElementById('ajError'); e2.textContent=d.error; e2.classList.remove('d-none'); }
    }catch{ document.getElementById('ajError').textContent='Error de conexión.'; document.getElementById('ajError').classList.remove('d-none'); }
    finally{ document.getElementById('btnAjustar').disabled=false; }
});
</script>
