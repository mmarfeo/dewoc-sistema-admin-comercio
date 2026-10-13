<?php
$v          = $venta;
$esAnulada  = $v['estado'] === 'anulada';
$esAdmin    = (Session::user()['rol'] ?? '') === 'admin';
$sa         = $solicitudAnulacion ?? null;
$hayPendiente = $sa && $sa['estado'] === 'pendiente';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Venta #<?= $v['id'] ?></h1>
        <span class="badge <?= $esAnulada ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?> mt-1">
            <?= ucfirst($v['estado']) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('ventas/ticket?id='.$v['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">🖨 Ticket</a>
        <a href="<?= url('ventas/comanda?id='.$v['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">📋 Comanda</a>
        <?php
        // Las ventas que vienen de MenuApp no se editan desde Dewoc (tarea 4.5 de
        // docs/integracion-menuapp-checklist.md): el pedido lo maneja MenuApp, y cambiarle
        // el medio de pago acá dejaría los dos sistemas contando distinto.
        $esMenuApp = ($v['origen'] ?? 'pos') === 'menuapp';
        ?>
        <?php if (!$esAnulada && !$esMenuApp && canEdit('ventas') && in_array($v['metodo_pago'], VentaModel::MEDIOS_PAGO_SIMPLES, true)): ?>
        <button class="btn btn-outline-secondary btn-sm" onclick="abrirCambiarMedioPago(<?= $v['id'] ?>, '<?= e($v['metodo_pago']) ?>')">Cambiar medio de pago</button>
        <?php endif; ?>
        <?php if (!$esAnulada && !$hayPendiente): ?>
            <?php if ($esAdmin): ?>
            <button class="btn btn-outline-danger btn-sm" onclick="anular(<?= $v['id'] ?>, <?= $esMenuApp ? 'true' : 'false' ?>)">Anular</button>
            <?php else: ?>
            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalSolicitarAnulacion">Solicitar anulación</button>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= url('ventas') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<?php if ($hayPendiente): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div class="small">
        ⏳ <strong><?= e($sa['solicita_nombre']) ?></strong> pidió anular esta venta — motivo: <strong><?= e($sa['motivo']) ?></strong><?= $sa['motivo_detalle'] ? ': ' . e($sa['motivo_detalle']) : '' ?>
    </div>
    <?php if ($esAdmin): ?>
    <div class="d-flex gap-2 flex-shrink-0">
        <button class="btn btn-sm btn-success" onclick="resolverAnulacion(<?= $sa['id'] ?>,'aprobar')">✅ Aprobar</button>
        <button class="btn btn-sm btn-outline-danger" onclick="resolverAnulacion(<?= $sa['id'] ?>,'rechazar')">Rechazar</button>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($sa && $sa['estado'] === 'rechazada' && !$esAnulada): ?>
<div class="alert alert-secondary small mb-4">
    ❌ Tu solicitud de anulación fue rechazada<?= $sa['resuelve_nombre'] ? ' por ' . e($sa['resuelve_nombre']) : '' ?><?= $sa['motivo_rechazo'] ? ': "' . e($sa['motivo_rechazo']) . '"' : '.' ?>
</div>
<?php endif; ?>

<!-- Modal: cambiar medio de pago (corrige un error de tipeo del cajero sin anular+recargar) -->
<div class="modal fade" id="modalCambiarMedioPago" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Cambiar medio de pago</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Actualmente: <strong id="cmpMedioActual"></strong></div>
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

<!-- Modal: solicitar anulación (usuarios sin permiso para anular directo) -->
<div class="modal fade" id="modalSolicitarAnulacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Solicitar anulación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Motivo</label>
                    <select id="motivoSolicitud" class="form-select">
                        <option value="Cancelación de compra">Cancelación de compra</option>
                        <option value="Error de producto seleccionado">Error de producto seleccionado</option>
                        <option value="Error de monto o precio">Error de monto o precio</option>
                        <option value="Cobro duplicado">Cobro duplicado</option>
                        <option value="Cliente se arrepintió">Cliente se arrepintió</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="mb-0 d-none" id="wrapDetalleSolicitud">
                    <label class="form-label fw-semibold">Contanos qué pasó</label>
                    <textarea id="detalleSolicitud" class="form-control" rows="3" placeholder="Obligatorio"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnEnviarSolicitud" onclick="enviarSolicitudAnulacion(<?= $v['id'] ?>)">Enviar solicitud</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Datos</h6>
            <div class="small"><span class="text-muted">Fecha:</span> <strong><?= fechaHora($v['created_at']) ?></strong></div>
            <div class="small"><span class="text-muted">Vendedor:</span> <?= e($v['usuario_nombre'] ?? '—') ?></div>
            <div class="small"><span class="text-muted">Turno:</span> <?= $v['turno_id'] ? '#'.$v['turno_id'] : '—' ?></div>
            <div class="small"><span class="text-muted">Pago:</span> <?= e(ucfirst(str_replace('_',' ',$v['metodo_pago']))) ?></div>
            <?php if ($v['metodo_pago'] === 'combinado'):
                $labelsMedio = ['efectivo'=>'Efectivo','debito'=>'Débito','credito'=>'Crédito','transferencia'=>'Transferencia','mercadopago'=>'Mercado Pago'];
                $medios = array_filter($labelsMedio, fn($k) => (float)($v['monto_'.$k] ?? 0) > 0.009, ARRAY_FILTER_USE_KEY);
            ?>
            <div class="small text-muted ms-3" style="font-size:.78rem">
                <?= implode(' · ', array_map(fn($k, $lbl) => e($lbl) . ' ' . moneda((float)$v['monto_'.$k]), array_keys($medios), $medios)) ?>
            </div>
            <?php endif; ?>
            <?php if ($v['facturar_afip']): ?>
            <div class="small text-primary fw-semibold mt-1">Facturado por AFIP</div>
            <?php if (!empty($v['afip_cae'])): ?>
            <div class="mt-2 p-2 rounded" style="background:#f0f9ff; border:1px solid #bae6fd; font-size:11px; font-family:monospace">
                <div><strong>CAE:</strong> <?= e($v['afip_cae']) ?></div>
                <div><strong>Vto.:</strong> <?= $v['afip_cae_vto'] ? date('d/m/Y', strtotime($v['afip_cae_vto'])) : '—' ?></div>
                <div><strong>Cbte.:</strong>
                    <?= $v['afip_tipo_cbte'] ?>/<?= str_pad((string)($v['afip_pto_vta']??''), 5, '0', STR_PAD_LEFT) ?>-<?= str_pad((string)($v['afip_nro_cbte']??''), 8, '0', STR_PAD_LEFT) ?>
                </div>
                <?php if ($v['afip_resultado'] && $v['afip_resultado'] !== 'A'): ?>
                <div class="text-warning"><strong>Resultado:</strong> <?= e($v['afip_resultado']) ?></div>
                <?php endif; ?>
                <?php if ($v['afip_obs']): ?>
                <div class="text-muted"><strong>Obs.:</strong> <?= e($v['afip_obs']) ?></div>
                <?php endif; ?>
            </div>
            <?php elseif ($v['facturar_afip']): ?>
            <div class="small text-warning mt-1">Sin CAE (no procesado o error AFIP)</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($v['notas']): ?><div class="small text-muted mt-1"><?= e($v['notas']) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Cliente</h6>
            <p class="fw-bold mb-1"><?= e($v['cliente_nombre'] ?? 'Consumidor final') ?></p>
            <?php if ($v['cliente_doc']): ?><p class="small text-muted mb-0"><?= e($v['cliente_doc']) ?></p><?php endif; ?>
            <?php if ($v['cliente_civa']): ?><p class="small text-muted mb-0"><?= e(ucfirst(str_replace('_',' ',$v['cliente_civa']))) ?></p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-light h-100"><div class="card-body">
            <h6 class="fw-semibold text-muted small text-uppercase mb-3">Totales</h6>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Subtotal sin IVA</span><span class="fw-semibold"><?= moneda($v['subtotal']) ?></span></div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">IVA</span><span><?= moneda($v['total_iva']) ?></span></div>
            <?php if ($v['descuento_global'] > 0): ?>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Descuento <?= $v['descuento_global'] ?>%</span><span class="text-danger">—</span></div>
            <?php endif; ?>
            <hr class="my-2">
            <div class="d-flex justify-content-between"><span class="fw-bold">TOTAL</span><span class="fw-bold text-primary fs-5"><?= moneda($v['total_con_iva']) ?></span></div>
        </div></div>
    </div>
</div>

<div class="card border-0"><div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Detalle</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">P.unit.</th><th class="text-end">Desc%</th><th class="text-end">Subtotal</th><th class="text-end">Total c/IVA</th></tr>
                </thead>
                <tbody>
                    <?php
                    // Una promo se guarda como una fila "cabecera" (el producto promo, con el
                    // precio real) + una fila por cada producto que la compone, a $0 y con
                    // venta_detalle_padre_id apuntando a la cabecera (ver VentaModel::explotarPromos()).
                    // Sin este chequeo, esas filas de componente se ven como si fueran 2 ventas
                    // sueltas de $0 — mismo criterio (y misma fuente de datos) que ya usan
                    // ticket.php y comanda.php para no confundir. El stock/receta de cada
                    // componente SÍ se descuenta una vez por fila (comportamiento correcto,
                    // ver VentaModel::registrar()) — acá solo se ajusta cómo se muestra.
                    $esPadrePromo = [];
                    foreach ($v['detalle'] as $d) {
                        if (!empty($d['venta_detalle_padre_id'])) $esPadrePromo[$d['venta_detalle_padre_id']] = true;
                    }
                    ?>
                    <?php foreach ($v['detalle'] as $d): ?>
                    <?php
                    $esPromo      = isset($esPadrePromo[$d['id']]);
                    $esComponente = !empty($d['venta_detalle_padre_id']);
                    ?>
                    <tr<?= $esComponente ? ' class="text-muted"' : '' ?>>
                        <td class="fw-semibold">
                            <?= $esComponente ? '<span class="ms-3">↳ </span>' : '' ?><?= e($d['producto_nombre']) ?>
                            <span class="text-muted small"><?= e($d['producto_codigo'] ?? '') ?></span>
                            <?php if ($esComponente): ?><span class="badge bg-light text-secondary border small">incluido en la promo</span><?php endif; ?>
                            <?php if ($esPromo): ?><span class="badge bg-light text-primary border small">promo</span><?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format($d['cantidad'],3,',','.') ?> <span class="text-muted small"><?= e($d['unidad']??'') ?></span></td>
                        <td class="text-end"><?= moneda($d['precio_unitario']) ?></td>
                        <td class="text-end small text-muted"><?= $d['descuento_pct'] > 0 ? number_format($d['descuento_pct'],1,',','.').'%' : '—' ?></td>
                        <td class="text-end"><?= moneda($d['subtotal']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($d['subtotal_con_iva']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
function anular(id, esMenuApp){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/ventas/anular`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };

    // Anular acá repone el stock, pero NO cancela el pedido en MenuApp ni reembolsa al
    // cliente: eso tiene que salir de MenuApp (ver tarea 4.5 del checklist).
    const sub = esMenuApp
        ? 'Esta venta vino de MenuApp. Anulándola acá se repone el stock, pero el pedido sigue vivo en MenuApp y NO se reembolsa al cliente: cancelalo desde MenuApp.'
        : 'El stock de productos se recuperará.';

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
// data-bs-toggle (Bootstrap sí registra esa delegación de eventos al importarse) — mismo
// mecanismo que ya usa el botón de "Solicitar anulación" de esta misma página.
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

document.getElementById('motivoSolicitud')?.addEventListener('change', function(){
    document.getElementById('wrapDetalleSolicitud').classList.toggle('d-none', this.value !== 'Otro');
});

async function enviarSolicitudAnulacion(ventaId){
    const motivo  = document.getElementById('motivoSolicitud').value;
    const detalle = document.getElementById('detalleSolicitud').value.trim();
    if (motivo === 'Otro' && !detalle) { showToast('Contanos brevemente el motivo.', 'danger'); return; }

    const btn = document.getElementById('btnEnviarSolicitud');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('venta_id', ventaId);
    fd.append('motivo', motivo);
    fd.append('motivo_detalle', detalle);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/ventas/solicitar-anulacion`, {method:'POST', body: fd});
        const d = await r.json();
        if (d.ok) { showToast(d.mensaje); setTimeout(()=>location.reload(), 800); }
        else { showToast(d.error, 'danger'); btn.disabled = false; }
    } catch { showToast('Error de conexión.', 'danger'); btn.disabled = false; }
}

async function resolverAnulacion(solicitudId, accion){
    const msg = accion === 'aprobar'
        ? '¿Aprobar y anular esta venta?'
        : '¿Rechazar esta solicitud de anulación?';
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('solicitud_id', solicitudId);
        fd.append('accion', accion);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const r = await fetch(`${BASE_URL}/ventas/resolver-anulacion`, {method:'POST', body: fd});
            const d = await r.json();
            d.ok ? (showToast(d.mensaje), setTimeout(()=>location.reload(), 800)) : showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    };
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, {
            label: accion === 'aprobar' ? 'Aprobar y anular' : 'Rechazar',
            sub: accion === 'aprobar' ? 'El stock se recuperará.' : '',
        });
    } else {
        if (confirm(msg + (accion === 'aprobar' ? ' El stock se recuperará.' : ''))) ejecutar();
    }
}
</script>
