<?php
/**
 * Ventas → Transferencias recibidas (Dewoc Cobros). Las transferencias que llegaron al celular
 * del negocio y a qué venta corresponde cada una:
 *  - Validada: se asoció sola (mismo monto, ±30 minutos de la venta).
 *  - Asignada: la asignó una persona (desde acá o desde el pop-up del POS).
 *  - Pendiente: todavía no se sabe de qué venta es.
 *  - No corresponde: no es de una venta (seña, devolución…), con el motivo.
 *
 * Para la conciliación de caja: lo pendiente es plata que entró y no se sabe por qué.
 */
$medios = ['efectivo' => 'Efectivo', 'debito' => 'Débito', 'credito' => 'Crédito', 'transferencia' => 'Transferencia',
           'mercadopago' => 'Mercado Pago', 'cuenta_corriente' => 'Cuenta corriente', 'combinado' => 'Combinado'];
$resumen = [];
foreach (['pendientes', 'asignadas', 'no_corresponde'] as $e) {
    $filas = array_filter($todas, fn($t) => $t['estado'] === $e);
    $resumen[$e] = ['cantidad' => count($filas), 'total' => array_sum(array_map(fn($t) => (float)$t['monto'], $filas))];
}
$puedeOperar = $puedeEditar && $habilitado;
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Transferencias recibidas</h1>
        <p class="text-muted small mb-0">
            Lo que llegó al celular del negocio por Dewoc Cobros y a qué venta corresponde. Las que tienen el mismo
            monto que una venta se validan solas; el resto se asigna acá o desde el POS.
        </p>
    </div>
    <a href="<?= url('ventas?dewoc_cobros=sin_validar&desde=' . urlencode($filtros['desde']) . '&hasta=' . urlencode($filtros['hasta'])) ?>"
       class="btn btn-outline-primary btn-sm">Ventas sin validar</a>
</div>

<form method="GET" action="<?= url('cobros-recibidos') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <?php foreach (['pendientes' => 'Pendientes de asignar', 'asignadas' => 'Asignadas a una venta',
                                    'no_corresponde' => 'No corresponden a una venta', '' => 'Todas'] as $v => $txt): ?>
                    <option value="<?= $v ?>" <?= $filtros['estado'] === $v ? 'selected' : '' ?>><?= e($txt) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button></div>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([
        'pendientes'     => ['Pendientes de asignar', 'text-warning'],
        'asignadas'      => ['Asignadas a una venta', 'text-success'],
        'no_corresponde' => ['No corresponden a una venta', 'text-secondary'],
    ] as $e => [$titulo, $cls]): ?>
    <div class="col-md-4">
        <a href="<?= url('cobros-recibidos?estado=' . $e . '&desde=' . urlencode($filtros['desde']) . '&hasta=' . urlencode($filtros['hasta'])) ?>"
           class="card border-0 text-decoration-none h-100 <?= $filtros['estado'] === $e ? 'shadow-sm' : '' ?>">
            <div class="card-body py-2">
                <div class="small text-muted"><?= e($titulo) ?></div>
                <div class="fs-5 fw-bold <?= $cls ?>"><?= $resumen[$e]['cantidad'] ?> · <?= e(moneda($resumen[$e]['total'])) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$habilitado): ?>
<div class="alert alert-secondary small">La licencia de Dewoc Cobros no está vigente: lo recibido se puede ver, pero no asignar.</div>
<?php elseif (!$puedeEditar): ?>
<div class="alert alert-secondary small">Para asignar transferencias desde acá hace falta permiso para editar ventas. Desde el POS, cada cajero asigna las de su turno.</div>
<?php endif; ?>

<div class="card border-0">
    <div class="card-body p-0">
        <?php if (!$transferencias): ?>
        <p class="text-muted small p-4 mb-0 text-center">No hay transferencias <?= $filtros['estado'] === 'pendientes' ? 'pendientes ' : '' ?>en el período.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle small mb-0">
                <thead class="table-light">
                    <tr><th>Recibida</th><th class="text-end">Monto</th><th>De</th><th>Estado</th><th>Venta / motivo</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($transferencias as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fechaHora($t['recibido_at'])) ?></td>
                        <td class="text-end fw-semibold text-nowrap"><?= $t['monto'] !== null ? e(moneda((float)$t['monto'])) : '<span class="text-muted">oculto</span>' ?></td>
                        <td>
                            <?= e($t['nombre'] ?? '—') ?>
                            <span class="d-block text-muted" style="font-size:.72rem" title="<?= e(trim(($t['titulo'] ?? '') . ' — ' . ($t['texto_largo'] ?: ($t['texto'] ?? '')))) ?>"><?= e($t['app']) ?></span>
                        </td>
                        <td>
                            <?php if ($t['estado'] === 'pendientes'): ?>
                            <span class="badge bg-warning-soft text-warning">Pendiente</span>
                            <?php elseif ($t['estado'] === 'no_corresponde'): ?>
                            <span class="badge bg-secondary-soft text-secondary">No corresponde</span>
                            <?php elseif ($t['vinculado_por'] === null): ?>
                            <span class="badge bg-success-soft text-success" title="Mismo monto que la venta y dentro de los 30 minutos">✓ Validada sola</span>
                            <?php else: ?>
                            <span class="badge bg-success-soft text-success">✓ Asignada</span>
                            <span class="d-block text-muted" style="font-size:.72rem">por <?= e($t['vinculado_por_nombre'] ?? '?') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['venta_id']): ?>
                            <a href="<?= url('ventas/ver?id=' . (int)$t['venta_id']) ?>">Venta #<?= (int)$t['venta_id'] ?></a>
                            · <?= e(moneda((float)$t['venta_total'])) ?> · <?= e($medios[$t['venta_metodo']] ?? $t['venta_metodo']) ?>
                            <?= $t['venta_mesa'] ? '· ' . e($t['venta_mesa']) : '' ?>
                            <span class="d-block text-muted" style="font-size:.72rem"><?= e(fechaHora($t['venta_created_at'])) ?></span>
                            <?php
                            $dif = $t['monto'] !== null ? round((float)$t['monto'] - (float)$t['venta_total'], 2) : 0;
                            if (abs($dif) >= 0.01): ?>
                            <span class="d-block text-warning" style="font-size:.72rem">
                                <?= $dif > 0 ? 'La transferencia es ' . e(moneda($dif)) . ' mayor (¿propina?)' : 'La transferencia es ' . e(moneda(-$dif)) . ' menor' ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!in_array($t['venta_metodo'], ['transferencia', 'mercadopago', 'combinado'], true)): ?>
                            <span class="d-block text-danger" style="font-size:.72rem">La venta está registrada con <?= e($medios[$t['venta_metodo']] ?? $t['venta_metodo']) ?></span>
                            <?php endif; ?>
                            <?php elseif ($t['estado'] === 'no_corresponde'): ?>
                            <?= e($t['ignorado_motivo']) ?>
                            <span class="d-block text-muted" style="font-size:.72rem">por <?= e($t['ignorado_por_nombre'] ?? '?') ?> el <?= e(fechaHora($t['ignorado_at'])) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($puedeOperar && $t['estado'] === 'pendientes'): ?>
                            <button type="button" class="btn btn-primary btn-sm py-0" onclick="abrirAsignar(<?= (int)$t['id'] ?>)">Asignar</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="noCorresponde(<?= (int)$t['id'] ?>)">No corresponde</button>
                            <?php elseif ($puedeEditar && $t['estado'] === 'asignadas'): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0" onclick="accion('desasignar', <?= (int)$t['id'] ?>, '¿Quitar la venta asignada?', 'La transferencia vuelve a quedar pendiente.', 'Quitar')">Quitar</button>
                            <?php elseif ($puedeEditar && $t['estado'] === 'no_corresponde'): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0" onclick="accion('quitar-no-corresponde', <?= (int)$t['id'] ?>, '¿Volver a dejarla pendiente?', 'Deja de estar marcada como “No corresponde”.', 'Deshacer')">Deshacer</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Asignar una transferencia a una venta: las ventas de las 12 horas alrededor, las más parecidas primero -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title fw-semibold mb-0">Asignar transferencia</h6>
                    <div class="small text-muted" id="asgCobro"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle small mb-0">
                        <thead class="table-light"><tr><th></th><th>Venta</th><th>Fecha</th><th class="text-end">Total</th><th>Medio</th><th>Mesa</th><th>Vendedor</th></tr></thead>
                        <tbody id="asgVentas"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <select id="asgMedio" class="form-select form-select-sm w-auto" title="Corrige el medio de pago de la venta">
                    <option value="">Medio de pago de la venta: no cambiar</option>
                    <option value="transferencia">Cambiarlo a Transferencia</option>
                    <option value="mercadopago">Cambiarlo a Mercado Pago</option>
                </select>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="confirmarAsignar()">Asignar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
const MEDIOS = <?= json_encode($medios, JSON_UNESCAPED_UNICODE) ?>;
let asgCobroId = null;

function escH(v) { const d = document.createElement('div'); d.textContent = v ?? ''; return d.innerHTML; }

/** Dashly no expone window.bootstrap (ver ventas/index.php): botón oculto con data-bs-toggle. */
function mostrarModal(id) {
    const el = document.getElementById(id);
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

async function postear(ruta, datos) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(datos)) fd.append(k, v);
    fd.append('_csrf_token', CSRF_TOKEN);
    const r = await fetch(`${BASE_URL}/cobros-recibidos/${ruta}`, { method: 'POST', body: fd });
    return r.json();
}

function resultado(d) {
    if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 700); }
    else showToast(d.error || 'No se pudo guardar.', 'danger');
}

async function abrirAsignar(cobroId) {
    asgCobroId = cobroId;
    const r = await fetch(`${BASE_URL}/cobros-recibidos/candidatas?cobro_id=${cobroId}`, { headers: { Accept: 'application/json' } });
    const d = await r.json();
    if (!d.ok) { showToast(d.error || 'No se pudo cargar.', 'danger'); return; }

    const c = d.cobro;
    document.getElementById('asgCobro').textContent =
        `${c.monto !== null ? formatMoneda(c.monto) : 'Monto oculto'} de ${c.nombre || '—'} · ${c.fecha} ${c.hora}`;
    document.getElementById('asgMedio').value = '';
    document.getElementById('asgVentas').innerHTML = d.ventas.length
        ? d.ventas.map((v, i) => `<tr onclick="this.querySelector('input').checked = true" style="cursor:pointer">
              <td><input type="radio" name="asgVenta" value="${v.id}" ${i === 0 ? 'checked' : ''}></td>
              <td>#${v.id}</td><td class="text-nowrap">${escH(v.fecha)} ${escH(v.hora)}</td>
              <td class="text-end fw-semibold ${c.monto !== null && Math.abs(v.total - c.monto) < 0.01 ? 'text-success' : ''}">${escH(formatMoneda(v.total))}</td>
              <td>${escH(MEDIOS[v.metodo_pago] || v.metodo_pago)}</td><td>${escH(v.mesa || '')}</td><td>${escH(v.vendedor || '')}</td>
          </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">No hay ventas sin transferencia en las 12 horas alrededor.</td></tr>';
    mostrarModal('modalAsignar');
}

async function confirmarAsignar() {
    const sel = document.querySelector('input[name="asgVenta"]:checked');
    if (!sel) { showToast('Elegí la venta.', 'danger'); return; }
    resultado(await postear('asignar', { cobro_id: asgCobroId, venta_id: sel.value, cambiar_medio: document.getElementById('asgMedio').value }));
}

function noCorresponde(cobroId) {
    showPrompt('¿Por qué no corresponde a una venta?', async (motivo) => {
        resultado(await postear('no-corresponde', { cobro_id: cobroId, motivo }));
    }, { sub: 'Deja de figurar como pendiente. Se puede deshacer.', placeholder: 'Seña, devolución, transferencia personal…', label: 'Marcar' });
}

function accion(ruta, cobroId, pregunta, sub, label) {
    showConfirm(pregunta, async () => {
        resultado(await postear(ruta, { cobro_id: cobroId }));
    }, { sub, label, type: ruta === 'desasignar' ? 'danger' : 'primary' });
}
</script>
