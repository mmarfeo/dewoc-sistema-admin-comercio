<?php $t = $turnoActivo; ?>
<?php if (!$t): ?>
<!-- Sin turno: abrir -->
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold text-center mb-4">Abrir turno de caja</h4>
                <form id="formAbrir">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Monto inicial ($)</label>
                        <input type="number" name="monto_inicial" class="form-control form-control-lg" min="0" step="0.01" value="0">
                        <div class="form-text">Efectivo disponible al inicio del turno.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="abrirError" class="alert alert-danger d-none small mb-3"></div>
                    <button type="submit" class="btn btn-success btn-lg w-100" id="btnAbrir">Abrir turno</button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= url('caja/historial') ?>" class="small text-muted">Ver historial de turnos →</a>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
document.getElementById('formAbrir').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('btnAbrir').disabled=true;
    document.getElementById('abrirError').classList.add('d-none');
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/abrir`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):(document.getElementById('abrirError').textContent=d.error,document.getElementById('abrirError').classList.remove('d-none'));
    }catch{document.getElementById('abrirError').textContent='Error de conexión.';document.getElementById('abrirError').classList.remove('d-none');}
    finally{document.getElementById('btnAbrir').disabled=false;}
});
</script>

<?php else: ?>
<!-- Con turno abierto -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Caja — Turno #<?= $t['numero'] ?></h1>
        <span class="badge bg-success">Abierto desde <?= fechaHora($t['apertura_at']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCerrar">Cerrar turno</button>
        <a href="<?= url('caja/historial') ?>" class="btn btn-outline-secondary btn-sm">Historial</a>
    </div>
</div>

<!-- KPIs del turno -->
<div class="row g-3 mb-4">
    <?php
    $saldo = $t['saldo_calculado'];
    $kpis = [
        ['label'=>'Monto inicial', 'valor'=> moneda($t['monto_inicial']), 'icon'=>'💵', 'cls'=>'bg-secondary-soft text-secondary'],
        ['label'=>'Total ingresos', 'valor'=> moneda($t['total_ingresos']), 'icon'=>'↑', 'cls'=>'bg-success-soft text-success'],
        ['label'=>'Total egresos',  'valor'=> moneda($t['total_egresos']),  'icon'=>'↓', 'cls'=>'bg-danger-soft text-danger'],
        ['label'=>'Saldo calculado','valor'=> moneda($saldo),               'icon'=>'💰','cls'=>'bg-primary-soft text-primary'],
    ];
    ?>
    <?php foreach ($kpis as $k): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1"><?= $k['label'] ?></p>
                        <h4 class="fw-bold mb-0"><?= $k['valor'] ?></h4>
                    </div>
                    <span class="badge <?= $k['cls'] ?> p-2 fs-5"><?= $k['icon'] ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Registrar movimiento -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Registrar movimiento manual</h6></div>
    <div class="card-body">
        <form id="formMov" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Monto ($)</label>
                <input type="number" name="monto" class="form-control form-control-sm" min="0.01" step="0.01">
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Descripción</label>
                <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Pago proveedor, fondo chico, etc.">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Registrar</button>
            </div>
            <div id="movError" class="col-12 d-none"><div class="alert alert-danger py-1 small mb-0"></div></div>
        </form>
    </div>
</div>

<!-- Movimientos del turno -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Movimientos del turno</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMovimientos" class="table table-hover mb-0 table-sm">
                <thead class="table-light">
                    <tr><th>Hora</th><th>Tipo</th><th>Descripción</th><th class="text-end">Monto</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($t['movimientos'] as $m): ?>
                    <tr>
                        <td class="small text-muted"><?= date('H:i', strtotime($m['created_at'])) ?></td>
                        <td><span class="badge <?= $m['tipo']==='ingreso'?'bg-success-soft text-success':'bg-danger-soft text-danger' ?>"><?= ucfirst($m['tipo']) ?></span></td>
                        <td class="small"><?= e($m['descripcion'] ?? '—') ?></td>
                        <td class="text-end fw-semibold <?= $m['tipo']==='ingreso'?'text-success':'text-danger' ?>"><?= ($m['tipo']==='ingreso'?'+':'-') . moneda($m['monto']) ?></td>
                        <td class="small text-muted"><?= e($m['usuario_nombre'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$t['movimientos']): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3 small">Sin movimientos registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal cierre de turno -->
<div class="modal fade" id="modalCerrar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Cerrar turno #<?= $t['numero'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCerrar">
                <?= Csrf::field() ?>
                <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
                <div class="modal-body">
                    <div class="alert alert-info small">
                        Saldo calculado por sistema: <strong><?= moneda($saldo) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Efectivo declarado ($)</label>
                        <input type="number" name="monto_cierre" class="form-control" min="0" step="0.01" value="<?= $saldo ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="cerrarError" class="alert alert-danger mt-3 d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnCerrar">Cerrar turno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblMovimientos').DataTable({order:[[0,'asc']],pageLength:25}));

document.getElementById('formMov').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/movimiento`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),600)):(showToast(d.error,'danger'));
    }catch{showToast('Error de conexión.','danger');}
});

document.getElementById('formCerrar').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('btnCerrar').disabled=true;
    document.getElementById('cerrarError').classList.add('d-none');
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/cerrar`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):(document.getElementById('cerrarError').textContent=d.error,document.getElementById('cerrarError').classList.remove('d-none'));
    }catch{document.getElementById('cerrarError').textContent='Error de conexión.';document.getElementById('cerrarError').classList.remove('d-none');}
    finally{document.getElementById('btnCerrar').disabled=false;}
});
</script>
<?php endif; ?>
