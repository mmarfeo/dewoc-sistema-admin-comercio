<?php $r = $receta; ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Producir: <?= e($r['nombre']) ?></h1>
        <p class="text-muted small mb-0">Rendimiento: <?= number_format($r['rendimiento'],3,',','.') ?> <?= e($r['unidad_rendimiento'] ?? '') ?> por producción</p>
    </div>
    <a href="<?= url('recetas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 mb-3">
            <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Ejecutar producción</h6></div>
            <div class="card-body">
                <?php if ($r['producto_nombre']): ?>
                <p class="small text-muted mb-3">Producto final: <strong><?= e($r['producto_nombre']) ?></strong></p>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cantidad de producciones</label>
                    <input type="number" id="cantProd" class="form-control" min="0.1" step="0.1" value="1">
                    <div class="form-text">Ej: 1 = producir <?= number_format($r['rendimiento'],3,',','.') ?> <?= e($r['unidad_rendimiento'] ?? '') ?></div>
                </div>
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Costo estimado</span>
                            <span class="fw-bold" id="costoEstimado"><?= moneda($costo) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Producción total</span>
                            <span class="fw-bold text-primary" id="prodTotal"><?= number_format($r['rendimiento'],3,',','.') ?> <?= e($r['unidad_rendimiento'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-success w-100" id="btnProducir">
                    ▶ Producir ahora
                </button>
                <div id="errProduccion" class="alert alert-danger mt-3 d-none small"></div>
                <div id="okProduccion" class="alert alert-success mt-3 d-none small"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Ingredientes necesarios</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tblIng">
                        <thead class="table-light">
                            <tr>
                                <th>Ingrediente</th><th>Tipo</th>
                                <th class="text-end">Cant./prod.</th>
                                <th class="text-end">Merma</th>
                                <th class="text-end" id="colNecesario">Necesario (×1)</th>
                                <th class="text-end">Stock actual</th>
                                <th class="text-center">Disponible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($r['detalle'] as $it): ?>
                            <?php
                            $necesario = $it['cantidad'] * 1 * (1 + $it['merma_pct'] / 100);
                            $ok        = (float)$it['stock_actual'] >= $necesario;
                            ?>
                            <tr data-cant="<?= $it['cantidad'] ?>" data-merma="<?= $it['merma_pct'] ?>"
                                data-stock="<?= $it['stock_actual'] ?>">
                                <td class="fw-semibold small"><?= e($it['ingrediente_nombre']) ?></td>
                                <td><span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ',$it['tipo_ingrediente'] ?? ($it['materia_prima_id'] ? 'materia prima' : 'insumo'))) ?></span></td>
                                <td class="text-end small"><?= number_format($it['cantidad'],3,',','.') ?> <span class="text-muted"><?= e($it['unidad'] ?? '') ?></span></td>
                                <td class="text-end small text-muted"><?= number_format($it['merma_pct'],1,',','.') ?>%</td>
                                <td class="text-end fw-semibold necesario-col"><?= number_format($necesario,3,',','.') ?></td>
                                <td class="text-end small"><?= number_format($it['stock_actual'],3,',','.') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $ok ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?> disp-badge">
                                        <?= $ok ? 'OK' : 'Falta' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($r['notas']): ?>
        <div class="alert alert-info mt-3 small"><?= e($r['notas']) ?></div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const RECETA_ID  = <?= $r['id'] ?>;
const RENDIMIENTO = <?= (float)$r['rendimiento'] ?>;
const COSTO_BASE  = <?= (float)$costo ?>;
const UNIDAD      = '<?= e($r['unidad_rendimiento'] ?? 'unidades') ?>';

document.getElementById('cantProd').addEventListener('input', recalcular);

function recalcular(){
    const cant=parseFloat(document.getElementById('cantProd').value)||1;
    document.getElementById('costoEstimado').textContent=formatMoneda(COSTO_BASE*cant);
    document.getElementById('prodTotal').textContent=formatNum(RENDIMIENTO*cant)+' '+UNIDAD;
    document.getElementById('colNecesario').textContent=`Necesario (×${formatNum(cant)})`;

    document.querySelectorAll('#tblIng tbody tr').forEach(tr=>{
        const c=parseFloat(tr.dataset.cant)||0;
        const m=parseFloat(tr.dataset.merma)||0;
        const s=parseFloat(tr.dataset.stock)||0;
        const nec=c*cant*(1+m/100);
        const ok=s>=nec;
        tr.querySelector('.necesario-col').textContent=formatNum(nec,3);
        const badge=tr.querySelector('.disp-badge');
        badge.textContent=ok?'OK':'Falta';
        badge.className='badge '+(ok?'bg-success-soft text-success':'bg-danger-soft text-danger')+' disp-badge';
    });
}

function formatNum(n,dec=3){return n.toFixed(dec).replace('.',',');}

document.getElementById('btnProducir').addEventListener('click', async function(){
    const cant=parseFloat(document.getElementById('cantProd').value)||0;
    if(cant<=0){alert('Ingrese una cantidad válida.');return;}
    this.disabled=true;
    document.getElementById('errProduccion').classList.add('d-none');
    document.getElementById('okProduccion').classList.add('d-none');
    const fd=new FormData();
    fd.append('receta_id',RECETA_ID); fd.append('cantidad',cant); fd.append('_csrf_token',CSRF_TOKEN);
    try{
        const r=await fetch(`${BASE_URL}/recetas/producir`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){
            const ok=document.getElementById('okProduccion');
            ok.textContent=d.mensaje; ok.classList.remove('d-none');
            showToast(d.mensaje);
            setTimeout(()=>location.reload(),1500);
        } else {
            const err=document.getElementById('errProduccion');
            let msg=d.error;
            if(d.faltantes?.length) msg+='\n• '+d.faltantes.join('\n• ');
            err.textContent=msg; err.classList.remove('d-none');
        }
    }catch{document.getElementById('errProduccion').textContent='Error de conexión.';document.getElementById('errProduccion').classList.remove('d-none');}
    finally{this.disabled=false;}
});
</script>
