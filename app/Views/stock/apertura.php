<div class="d-flex align-items-center justify-content-between mb-2">
    <div>
        <h1 class="h3 mb-1 fw-bold">Inventario inicial de apertura</h1>
        <p class="text-muted small mb-0">
            Ingrese el stock inicial de cada ítem. Solo se muestran los ítems con stock en cero (<?= count($items) ?> de <?= $total ?>).
            Los ajustes se registran con origen <strong>"Inventario inicial de apertura"</strong>.
        </p>
    </div>
    <a href="<?= url('stock') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<?php if (!$items): ?>
<div class="alert alert-success mt-3">
    Todos los ítems ya tienen stock registrado. Si necesitás ajustar stock, usá la sección de
    <a href="<?= url('stock/inventario') ?>">Inventario físico</a> o los ajustes manuales.
</div>
<?php else: ?>

<div class="alert alert-info d-flex gap-2 align-items-start mt-2 mb-4">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div class="small">Dejá en blanco los ítems que todavía no tienen stock. Solo se guardarán los valores mayores a cero que ingresés.</div>
</div>

<form id="formApertura" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="items_json" id="itemsJson" value="[]">

    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tblApertura" class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th><th>Tipo</th><th>Unidad</th><th>Categoría</th>
                            <th class="text-end" style="width:150px">Stock inicial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                        <tr data-idx="<?= $idx ?>" data-tipo="<?= e($it['tipo']) ?>" data-id="<?= $it['id'] ?>">
                            <td class="fw-semibold small"><?= e($it['nombre']) ?></td>
                            <td><span class="badge bg-secondary-soft text-secondary" style="font-size:10px"><?= e(str_replace('_',' ',$it['tipo'])) ?></span></td>
                            <td class="small text-muted"><?= e($it['unidad'] ?? '') ?></td>
                            <td class="small text-muted"><?= e($it['categoria'] ?? '') ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm apertura-input"
                                    min="0" step="0.001" placeholder="0,000" data-idx="<?= $idx ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="apError" class="alert alert-danger mt-3 d-none"></div>
    <div id="apResumen" class="text-muted small mt-2 d-none">
        <span id="apCount">0</span> ítem(s) con stock ingresado
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardarAp">Guardar stock inicial</button>
        <a href="<?= url('stock') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
let apertura = {};

$(document).ready(()=>$('#tblApertura').DataTable({pageLength:50,searching:true,language:{url:'//cdn.datatables.net/plug-ins/1.13.6/i18n/es-AR.json'}}));

document.querySelectorAll('.apertura-input').forEach(inp => {
    inp.addEventListener('input', function() {
        const tr  = this.closest('tr');
        const idx = tr.dataset.idx;
        const val = parseFloat(this.value);
        if (!isNaN(val) && val > 0) {
            apertura[idx] = {
                tipo_item:   tr.dataset.tipo,
                item_id:     parseInt(tr.dataset.id),
                stock_teorico: 0,
                stock_fisico:  val,
            };
        } else {
            delete apertura[idx];
        }
        sincronizar();
    });
});

function sincronizar() {
    const items = Object.values(apertura);
    document.getElementById('itemsJson').value = JSON.stringify(items);
    const res = document.getElementById('apResumen');
    document.getElementById('apCount').textContent = items.length;
    res.classList.toggle('d-none', items.length === 0);
}

document.getElementById('formApertura').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('apError');
    errEl.classList.add('d-none');
    const items = Object.values(apertura);
    if (!items.length) {
        errEl.textContent = 'Ingresá al menos un stock inicial.';
        errEl.classList.remove('d-none');
        return;
    }
    document.getElementById('btnGuardarAp').disabled = true;
    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/stock/apertura/guardar`, {method:'POST', body:fd});
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            setTimeout(() => { window.location.href = `${BASE_URL}/stock`; }, 1000);
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        document.getElementById('btnGuardarAp').disabled = false;
    }
});
</script>
<?php endif; ?>
