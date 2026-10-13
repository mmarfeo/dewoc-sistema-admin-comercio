<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Ventas</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('ventas/comprobantes-afip') ?>" class="btn btn-outline-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Comprobantes AFIP
        </a>
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar histórico
        </button>
        <a href="<?= url('ventas/pos') ?>" class="btn btn-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Ir al POS
        </a>
    </div>
</div>

<?php if (!$puedeVerHistorial): ?>
<div class="alert alert-secondary small border-0 mb-3">
    🔒 Este listado solo muestra las ventas de hoy.
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="<?= url('ventas') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <?php if ($puedeVerHistorial): ?>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>"></div>
            <?php endif; ?>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="completada" <?= $filtros['estado']==='completada'?'selected':'' ?>>Completada</option>
                    <option value="anulada"    <?= $filtros['estado']==='anulada'   ?'selected':'' ?>>Anulada</option>
                </select></div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <?php if ($puedeVerHistorial): ?>
                <a href="<?= url('ventas') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
                <?php endif; ?>
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
                        <th>Pago</th><th>Origen</th><th>AFIP</th><th>Estado</th>
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
                        <td class="small">
                            <?php
                            // Las ventas de MenuApp no tienen turno hasta que se entregan (D6),
                            // así que sin esta etiqueta aparecen como "sin turno" sin explicación.
                            $origen = $v['origen'] ?? 'pos';
                            if ($origen === 'menuapp'): ?>
                            <span class="badge bg-info-soft text-info">MenuApp</span>
                            <?php if (!empty($v['cobro_pendiente'])): ?>
                            <span class="badge bg-warning-soft text-warning" title="Cobrada en mano, todavía sin imputar a una caja">Cobro pendiente</span>
                            <?php endif; ?>
                            <?php elseif ($origen === 'portal'): ?>
                            <span class="badge bg-secondary-soft text-secondary">Portal</span>
                            <?php else: ?>
                            <span class="text-muted">POS</span>
                            <?php endif; ?>
                        </td>
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
                            <?php if ($v['estado'] !== 'anulada' && canEdit('ventas') && ($v['origen'] ?? 'pos') !== 'menuapp' && in_array($v['metodo_pago'], VentaModel::MEDIOS_PAGO_SIMPLES, true)): ?>
                            <button class="btn btn-sm btn-outline-secondary py-0 ms-1"
                                    onclick="abrirCambiarMedioPago(<?= $v['id'] ?>, '<?= e($v['metodo_pago']) ?>')" title="Cambiar medio de pago">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                            </button>
                            <?php endif; ?>
                            <?php if ($v['estado'] !== 'anulada'): ?>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                    onclick="anular(<?= $v['id'] ?>, <?= ($v['origen'] ?? 'pos') === 'menuapp' ? 'true' : 'false' ?>)" title="Anular">
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

<!-- Cambiar medio de pago: corrige un error de tipeo del cajero (ej. cobró efectivo pero
     facturó Mercado Pago) sin pasar por anular + recargar toda la venta. -->
<div class="modal fade" id="modalCambiarMedioPago" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Cambiar medio de pago</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Venta #<span id="cmpVentaId"></span> — actualmente: <strong id="cmpMedioActual"></strong></div>
                <label class="form-label small fw-semibold">Nuevo medio de pago</label>
                <select id="cmpNuevoMedio" class="form-select form-select-sm"></select>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="confirmarCambioMedioPago()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblVentas').DataTable({order:[[0,'desc']]}));
function anular(id, esMenuApp){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/ventas/anular`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };

    // Tarea 4.5 de docs/integracion-menuapp-checklist.md: anular acá repone el stock, pero
    // NO cancela el pedido en MenuApp ni dispara el reembolso de Mercado Pago. Para que el
    // cliente recupere la plata, la cancelación tiene que salir de MenuApp — que después
    // llama a POST /api/menuapp/v1/ventas/{referencia}/anular y deja todo consistente.
    const sub = esMenuApp
        ? 'Esta venta vino de MenuApp. Anulándola acá se repone el stock, pero el pedido sigue vivo en MenuApp y NO se reembolsa al cliente: cancelalo desde MenuApp.'
        : 'El stock de los productos se recuperará.';

    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular esta venta?', ejecutar, { label: 'Anular', sub });
    } else {
        if (confirm('¿Anular esta venta? ' + sub)) ejecutar();
    }
}

// ── Cambiar medio de pago ────────────────────────────────────────────────────
// Dashly bundlea Bootstrap sin exponer window.bootstrap (mismo criterio documentado en
// ventas/pos.php y productos/form.php) — usando "new bootstrap.Modal(...)" directo, como
// estaba antes, el botón no hacía NADA (el "?." tras el ternario se comía el error en
// silencio): no era un tema de permisos, pasaba para cualquier usuario, siempre. _showModal/
// _hideModal abren/cierran igual aunque bootstrap no esté expuesto, vía un botón oculto con
// data-bs-toggle (Bootstrap sí registra esa delegación de eventos al importarse).
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getOrCreateInstance(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    let btn = el.querySelector('[data-bs-dismiss="modal"]');
    if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-dismiss', 'modal');
        el.appendChild(btn);
    }
    btn.click();
}

const ETIQUETAS_MEDIO_PAGO = {
    efectivo: 'Efectivo', debito: 'Débito', credito: 'Crédito',
    transferencia: 'Transferencia', mercadopago: 'Mercado Pago',
};
let cmpVentaIdActual = null;

function abrirCambiarMedioPago(ventaId, medioActual){
    cmpVentaIdActual = ventaId;
    document.getElementById('cmpVentaId').textContent = ventaId;
    document.getElementById('cmpMedioActual').textContent = ETIQUETAS_MEDIO_PAGO[medioActual] || medioActual;

    const select = document.getElementById('cmpNuevoMedio');
    select.innerHTML = Object.entries(ETIQUETAS_MEDIO_PAGO)
        .filter(([valor]) => valor !== medioActual)
        .map(([valor, etiqueta]) => `<option value="${valor}">${etiqueta}</option>`)
        .join('');

    _showModal('modalCambiarMedioPago');
}

async function confirmarCambioMedioPago(){
    const nuevo = document.getElementById('cmpNuevoMedio').value;
    const fd = new FormData();
    fd.append('id', cmpVentaIdActual);
    fd.append('metodo_pago', nuevo);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/ventas/cambiar-medio-pago`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        _hideModal('modalCambiarMedioPago');
        showToast(d.mensaje);
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(d.error, 'danger');
    }
}
</script>
<?php
$importUrl        = url('ventas/importar');
$importPreviewUrl = url('ventas/previsualizar');
$importPlantilla  = url('ventas/plantilla');
$importModulo     = 'Ventas (histórico ERP anterior)';
$importColumnas   = ['Fecha *', 'Numero *', 'Producto *', 'Cantidad *', 'Total *', 'Dia', 'Mes', 'Hora', 'Codigo', 'Rubro', 'SubRubro', 'Area', 'Sector'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
