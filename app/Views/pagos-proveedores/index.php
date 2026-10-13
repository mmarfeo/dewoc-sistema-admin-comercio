<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Pagos a Proveedores</h1>
        <p class="text-muted small mb-0">Cuenta corriente y registro de pagos</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('pagos-proveedores/reembolso-nuevo') ?>" class="btn btn-outline-primary">+ Reembolsar a Socio</a>
        <a href="<?= url('pagos-proveedores/nuevo') ?>" class="btn btn-primary">+ Registrar Pago</a>
    </div>
</div>

<!-- ── Cuánto pagó cada socio / la empresa ──────────────────────────────── -->
<?php
$sumaTarjetas = $resumen['empresa'] + $resumen['sin_asignar'] + array_sum(array_column($resumen['socios'], 'total'));
$cuadra       = abs($sumaTarjetas - $resumen['total_general']) < 0.01;
?>
<div class="row g-3 mb-4">
    <?php foreach ($resumen['socios'] as $s): ?>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 h-100 card-socio" role="button"
             onclick="verPagosDeSocio(<?= (int) $s['socio_id'] ?>, '<?= addslashes(e($s['socio'])) ?>')">
            <div class="card-body py-3">
                <div class="small text-muted text-truncate" title="<?= e($s['socio']) ?>"><?= e($s['socio']) ?></div>
                <div class="fw-bold fs-5"><?= moneda($s['total']) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($resumen['sin_asignar'] > 0.009): ?>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 h-100 bg-warning-soft">
            <div class="card-body py-3">
                <div class="small text-warning">Socio sin asignar</div>
                <div class="fw-bold fs-5 text-warning"><?= moneda($resumen['sin_asignar']) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 h-100 bg-info-soft">
            <div class="card-body py-3">
                <div class="small text-info">Empresa</div>
                <div class="fw-bold fs-5 text-info"><?= moneda($resumen['empresa']) ?></div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 h-100 <?= $cuadra ? 'bg-success-soft' : 'bg-danger-soft' ?>">
            <div class="card-body py-3">
                <div class="small <?= $cuadra ? 'text-success' : 'text-danger' ?>">
                    Total pagado <?= $cuadra ? '✓' : '⚠' ?>
                </div>
                <div class="fw-bold fs-5 <?= $cuadra ? 'text-success' : 'text-danger' ?>"><?= moneda($resumen['total_general']) ?></div>
            </div>
        </div>
    </div>
</div>
<?php if (empty($resumen['socios'])): ?>
<p class="small text-muted mb-4">
    Todavía no cargaste socios — <a href="<?= url('socios') ?>">creá los socios</a> para ver el desglose por persona acá.
</p>
<?php endif; ?>

<?php if (!empty($cuentas)): ?>
<!-- ── Cuenta Corriente ───────────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-white pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-semibold mb-0 text-danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Saldos pendientes de pago
        </h6>
        <div class="text-end">
            <div class="small text-muted mb-0">Deuda total</div>
            <div class="fw-bold fs-5 text-danger"><?= moneda($deudaTotal) ?></div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Proveedor</th><th>CUIT</th>
                        <th class="text-end">Ítems <small class="text-muted fw-normal">(facturas + gastos)</small></th>
                        <th class="text-end">Deuda total</th>
                        <th class="text-end">Pagado parcial</th>
                        <th class="text-end text-danger fw-semibold">Saldo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cuentas as $c): ?>
                <tr>
                    <td class="fw-semibold"><?= e($c['razon_social']) ?></td>
                    <td class="small text-muted"><?= e($c['cuit'] ?? '—') ?></td>
                    <td class="text-end small"><?= $c['facturas_pendientes'] ?></td>
                    <td class="text-end small"><?= moneda($c['total_deuda']) ?></td>
                    <td class="text-end small text-muted"><?= moneda($c['pagado_parcial']) ?></td>
                    <td class="text-end fw-bold text-danger"><?= moneda($c['saldo']) ?></td>
                    <td class="text-end">
                        <a href="<?= url('pagos-proveedores/cuenta-corriente?proveedor_id=' . $c['proveedor_id']) ?>"
                           class="btn btn-sm btn-outline-secondary py-0" title="Ver cuenta corriente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </a>
                        <a href="<?= url('pagos-proveedores/nuevo?proveedor_id=' . $c['proveedor_id']) ?>"
                           class="btn btn-sm btn-primary py-0 ms-1">Pagar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-semibold">Deuda total</td>
                        <td class="text-end fw-bold text-danger"><?= moneda($deudaTotal) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($deudaSocios)): ?>
<!-- ── Deuda con socios (aportes/facturas/compras pagadas con plata propia, sin reembolsar) ── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-white pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-semibold mb-0 text-warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Deuda con socios
        </h6>
        <div class="text-end">
            <div class="small text-muted mb-0">Total a reembolsar</div>
            <div class="fw-bold fs-5 text-warning"><?= moneda($deudaSociosTotal) ?></div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Socio</th>
                        <th class="text-end text-warning fw-semibold">Saldo a devolver</th>
                        <th class="text-end">Sin aplicar a factura
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="ms-1" style="cursor:help;vertical-align:-2px" title="Plata ya reembolsada al socio que todavía no quedó vinculada a ninguna factura/aporte puntual suyo. Hacé click para aplicarla."><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($deudaSocios as $d):
                    $sinAplicar = $sinAplicarPorSocio[$d['socio_id']] ?? null;
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($d['socio']) ?></td>
                    <td class="text-end fw-bold text-warning"><?= moneda($d['saldo']) ?></td>
                    <td class="text-end">
                        <?php if ($sinAplicar): ?>
                        <a href="<?= url('pagos-proveedores/reembolso-ver?id=' . $sinAplicar['reembolso_id']) ?>"
                           class="fw-semibold text-danger" title="Aplicar este saldo a una factura pendiente">
                            <?= moneda($sinAplicar['total']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= url('pagos-proveedores/cuenta-corriente-socio?socio_id=' . $d['socio_id']) ?>"
                           class="btn btn-sm btn-outline-secondary py-0 me-1" title="Ver cuenta corriente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </a>
                        <a href="<?= url('pagos-proveedores/reembolso-nuevo?socio_id=' . $d['socio_id']) ?>"
                           class="btn btn-sm btn-outline-primary py-0">Reembolsar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td class="text-end fw-semibold">Total a reembolsar</td>
                        <td class="text-end fw-bold text-warning"><?= moneda($deudaSociosTotal) ?></td>
                        <td class="text-end fw-semibold"><?= moneda(array_sum(array_column($sinAplicarPorSocio, 'total'))) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filtros ───────────────────────────────────────────────────────── -->
<form method="GET" action="<?= url('pagos-proveedores') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Proveedor / Beneficiario</label>
                <select name="proveedor_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($filtros['proveedor_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['razon_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <option value="proveedor"  <?= $filtros['tipo']==='proveedor' ?'selected':'' ?>>Proveedor</option>
                    <option value="sueldo"     <?= $filtros['tipo']==='sueldo'    ?'selected':'' ?>>Sueldo</option>
                    <option value="otro"       <?= $filtros['tipo']==='otro'      ?'selected':'' ?>>Otro</option>
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
                    <option value="activo"  <?= $filtros['estado']==='activo'  ?'selected':'' ?>>Activo</option>
                    <option value="anulado" <?= $filtros['estado']==='anulado' ?'selected':'' ?>>Anulado</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('pagos-proveedores') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<!-- ── Lista de pagos ─────────────────────────────────────────────────── -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblPagos" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Fecha</th><th>Tipo</th><th>Beneficiario</th>
                        <th>Concepto</th><th class="text-end">MP</th><th class="text-end">Efectivo</th>
                        <th class="text-end">Otros</th><th class="text-end fw-semibold">Total</th>
                        <th>Estado</th><th class="text-center" style="width:80px">Acc.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pagos as $p): ?>
                <tr class="<?= $p['estado']==='anulado' ? 'text-muted' : '' ?>">
                    <td class="small"><?= $p['id'] ?></td>
                    <td class="small"><?= fecha($p['fecha']) ?></td>
                    <td class="small">
                        <?php $tipos=['proveedor'=>'Proveedor','sueldo'=>'Sueldo','otro'=>'Otro']; ?>
                        <span class="badge bg-secondary-soft text-secondary"><?= $tipos[$p['tipo']] ?? $p['tipo'] ?></span>
                    </td>
                    <td class="fw-semibold"><?= e($p['nombre_beneficiario'] ?? '—') ?></td>
                    <td class="small text-muted"><?= e($p['concepto'] ?? '') ?></td>
                    <td class="text-end small"><?= $p['monto_mercado_pago'] > 0 ? moneda($p['monto_mercado_pago']) : '—' ?></td>
                    <td class="text-end small"><?= $p['monto_efectivo'] > 0 ? moneda($p['monto_efectivo']) : '—' ?></td>
                    <td class="text-end small text-muted">
                        <?php $otros = $p['monto_debito']+$p['monto_transferencia']+$p['monto_cheque']+$p['monto_otro']; ?>
                        <?= $otros > 0 ? moneda($otros) : '—' ?>
                    </td>
                    <td class="text-end fw-bold"><?= moneda($p['monto_total']) ?></td>
                    <td>
                        <?php $cls = $p['estado']==='anulado' ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success'; ?>
                        <span class="badge <?= $cls ?>"><?= ucfirst($p['estado']) ?></span>
                    </td>
                    <td class="text-center">
                        <a href="<?= url('pagos-proveedores/ver?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <?php if ($p['estado'] === 'activo'): ?>
                        <button class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="anular(<?= $p['id'] ?>)" title="Anular">
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

<!-- ── Modal detalle de pagos de un socio ───────────────────────────────── -->
<div class="modal fade" id="modalPagosSocio" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="tituloPagosSocio">Pagos de —</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th><th>Tipo</th><th>Beneficiario</th>
                                <th>Concepto</th><th class="text-end">Monto</th>
                                <th>Estado</th><th>Factura</th><th>Reembolsado</th>
                            </tr>
                        </thead>
                        <tbody id="filasPagosSocio"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
.card-socio { cursor: pointer; transition: transform .1s ease, box-shadow .15s ease; }
.card-socio:hover { transform: translateY(-2px); box-shadow: 0 .25rem .75rem rgba(0,0,0,.12); }
</style>

<script>
const BASE_URL = '<?= BASE_URL ?>', CSRF_TOKEN = '<?= Csrf::token() ?>';
$(document).ready(() => { $('#tblPagos').DataTable({ order: [[0, 'desc']] }); });

function anular(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/pagos-proveedores/anular`, { method: 'POST', body: fd });
        const d = await r.json();
        d.ok ? (showToast(d.mensaje), setTimeout(() => location.reload(), 800)) : showToast(d.error, 'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular este pago?', ejecutar, { label: 'Anular', sub: 'Las facturas asociadas volverán a estado Pendiente.' });
    } else {
        if (confirm('¿Anular este pago? Las facturas asociadas volverán a estado Pendiente.')) ejecutar();
    }
}

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle.
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { new bootstrap.Modal(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}

function esc(v) { const d = document.createElement('div'); d.textContent = v || ''; return d.innerHTML; }

// Mismos filtros que ya están aplicados en la página (fecha/proveedor/tipo/estado),
// para que el detalle del modal sea consistente con el total de la tarjeta.
const FILTROS_ACTUALES = new URLSearchParams(window.location.search);

async function verPagosDeSocio(socioId, nombreSocio) {
    document.getElementById('tituloPagosSocio').textContent = 'Pagos de ' + nombreSocio;
    const tbody = document.getElementById('filasPagosSocio');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Cargando...</td></tr>';
    _showModal('modalPagosSocio');

    const qs = new URLSearchParams(FILTROS_ACTUALES);
    qs.set('socio_id', socioId);

    try {
        const r = await fetch(`${BASE_URL}/pagos-proveedores/pagos-de-socio?${qs.toString()}`);
        const d = await r.json();
        tbody.innerHTML = '';
        if (!d.ok || !d.data.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>';
            return;
        }
        const tipos = { proveedor: 'Proveedor', sueldo: 'Sueldo', otro: 'Otro' };
        const infoIcon = (titulo) => `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="ms-1" style="cursor:help;vertical-align:-2px" title="${esc(titulo)}"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
        d.data.forEach(p => {
            const fecha       = new Date(p.fecha).toLocaleDateString('es-AR');
            const reembolsado = parseFloat(p.reembolsado || 0);
            const montoTotal  = parseFloat(p.monto_total || 0);
            const totalmenteReembolsado = reembolsado >= montoTotal - 0.01;
            const parcialReembolsado    = reembolsado > 0.009 && !totalmenteReembolsado;

            const cls = p.estado === 'anulado'
                ? 'text-muted'
                : (totalmenteReembolsado ? 'text-muted text-decoration-line-through' : '');
            const badge  = p.estado === 'anulado'
                ? '<span class="badge bg-danger-soft text-danger">Anulado</span>'
                : '<span class="badge bg-success-soft text-success">Activo</span>';
            const facturasHtml = (p.facturas && p.facturas.length)
                ? p.facturas.map(f => `<a href="${BASE_URL}/compras/ver?id=${f.factura_id}" class="d-block small">${esc(f.nro_comprobante || ('Factura #' + f.factura_id))}</a>`).join('')
                : `<a href="${BASE_URL}/pagos-proveedores/ver?id=${p.id}" class="small text-muted">Ver pago</a>`;

            let reembolsoHtml;
            if (totalmenteReembolsado) {
                reembolsoHtml = `<span class="badge bg-success-soft text-success">Reembolsada</span>${infoIcon('Esta factura ya fue pagada por la empresa a este socio.')}`;
            } else if (parcialReembolsado) {
                reembolsoHtml = `<span class="badge bg-warning-soft text-warning">Parcial: ${formatMoneda(reembolsado)}</span>${infoIcon('La empresa ya le devolvió una parte de esto a este socio; el resto sigue pendiente.')}`;
            } else {
                reembolsoHtml = '<span class="badge bg-secondary-soft text-secondary">Pendiente</span>';
            }

            tbody.innerHTML += `<tr class="${cls}">
                <td class="small">${fecha}</td>
                <td class="small">${tipos[p.tipo] || p.tipo}</td>
                <td class="small fw-semibold">${esc(p.nombre_beneficiario || '—')}</td>
                <td class="small text-muted">${esc(p.concepto || '')}</td>
                <td class="text-end small fw-bold">${formatMoneda(p.monto_total)}</td>
                <td>${badge}</td>
                <td>${facturasHtml}</td>
                <td class="small">${reembolsoHtml}</td>
            </tr>`;
        });
    } catch {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Error al cargar los pagos.</td></tr>';
    }
}
</script>
