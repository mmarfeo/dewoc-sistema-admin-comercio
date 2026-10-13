<?php
$f          = $factura;
$esAnulada  = $f['estado'] === 'anulada';
$volverUrl ??= url('compras');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Factura de Compra #<?= $f['id'] ?></h1>
        <span class="badge <?= $esAnulada ? 'bg-danger-soft text-danger' : ($f['estado']==='pagada' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning') ?> mt-1">
            <?= ucfirst($f['estado']) ?>
        </span>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$esAnulada): ?>
        <a href="<?= url('compras/editar?id='.$f['id']) . '&volver=' . urlencode($volverUrl) ?>" class="btn btn-outline-secondary btn-sm">Editar</a>
        <button class="btn btn-outline-danger btn-sm" onclick="anular(<?= $f['id'] ?>)">Anular factura</button>
        <?php endif; ?>
        <a href="<?= url('compras/imprimir?id=' . $f['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Imprimir / PDF</a>
        <a href="<?= e($volverUrl) ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <h6 class="fw-semibold text-muted small text-uppercase mb-3">Proveedor</h6>
                <p class="fw-bold fs-5 mb-1"><?= e($f['proveedor_nombre'] ?? '—') ?></p>
                <?php if ($f['cuit']): ?><p class="small text-muted mb-1">CUIT: <?= e($f['cuit']) ?></p><?php endif; ?>
                <?php if ($f['condicion_iva']): ?><p class="small text-muted mb-0">IVA: <?= e(ucfirst(str_replace('_',' ',$f['condicion_iva']))) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <h6 class="fw-semibold text-muted small text-uppercase mb-3">Datos del comprobante</h6>
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-muted">Tipo:</span> <span class="fw-semibold"><?= e(strtoupper(str_replace('_',' ',$f['tipo_comprobante']))) ?></span></div>
                    <div class="col-6"><span class="text-muted">Número:</span> <span class="fw-semibold"><?= e($f['nro_comprobante'] ?? '—') ?></span></div>
                    <div class="col-6"><span class="text-muted">Fecha:</span> <span class="fw-semibold"><?= fecha($f['fecha']) ?></span></div>
                    <div class="col-6"><span class="text-muted">Condición pago:</span> <span class="fw-semibold"><?= e(ucfirst(str_replace('_',' ',$f['condicion_pago']))) ?></span></div>
                    <?php if ($f['notas']): ?>
                    <div class="col-12"><span class="text-muted">Notas:</span> <?= e($f['notas']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detalle de ítems -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Ítems</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ítem</th><th>Tipo</th><th class="text-end">Cont. x unidad</th><th class="text-end">Cantidad</th>
                        <th class="text-end">P. unit.</th><th class="text-end">IVA %</th>
                        <th class="text-end">Subtotal</th><th class="text-end">Total c/IVA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($f['detalle'] as $d): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= e($d['item_nombre'] ?? '—') ?></span>
                            <?php if ($d['item_codigo']): ?><span class="text-muted small ms-1">(<?= e($d['item_codigo']) ?>)</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary-soft text-secondary small"><?= e(str_replace('_',' ', $d['tipo_item'])) ?></span>
                            <?php
                            $tgLabel = ['costo_variable' => 'Variable', 'costo_fijo' => 'Fijo', 'impuesto' => 'Impuesto', 'gasto' => 'Gasto'];
                            $tgColor = ['costo_variable' => 'warning',  'costo_fijo' => 'info',   'impuesto' => 'danger',  'gasto' => 'secondary'];
                            $tg = $d['item_tipo_gasto'] ?? 'costo_variable';
                            ?>
                            <span class="badge bg-<?= $tgColor[$tg] ?? 'secondary' ?>-soft text-<?= $tgColor[$tg] ?? 'secondary' ?> small" title="Clasificación en el Estado de Resultados">
                                <?= $tgLabel[$tg] ?? e($tg) ?>
                            </span>
                        </td>
                        <td class="text-end small text-muted">
                            <?php if (!empty($d['cantidad_contenida'])): ?>
                                <?= number_format($d['cantidad_contenida'], 3, ',', '.') ?> <?= e($d['unidad'] ?? '') ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php
                        // "Cantidad" es cuántos paquetes/envases se compraron cuando hay
                        // "Cont. x unidad" cargado (ej. 1 = un balde de 500gr) — mostrar ahí
                        // la unidad BASE del ítem (gr) confunde, porque cantidad no está en
                        // gramos, está en unidades de compra. Solo tiene sentido mostrar la
                        // unidad base en "Cantidad" cuando se compró suelto (sin envase).
                        $unidadCantidad = !empty($d['cantidad_contenida']) ? 'unid.' : ($d['unidad'] ?? '');
                        ?>
                        <td class="text-end"><?= number_format($d['cantidad'],3,',','.') ?> <span class="text-muted small"><?= e($unidadCantidad) ?></span></td>
                        <td class="text-end"><?= moneda($d['precio_unitario']) ?></td>
                        <td class="text-end small"><?= number_format($d['iva_porcentaje'],1,',','.')  ?>%</td>
                        <td class="text-end"><?= moneda($d['subtotal']) ?></td>
                        <td class="text-end fw-semibold"><?= moneda($d['subtotal_con_iva']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Totales -->
<div class="row justify-content-end">
    <div class="col-md-4">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Subtotal sin IVA</span>
                    <span class="fw-semibold"><?= moneda($f['subtotal']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">IVA</span>
                    <span class="fw-semibold"><?= moneda($f['total_iva']) ?></span>
                </div>
                <?php if (!empty($f['ajuste'])): ?>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Ajuste (redondeo/descuento)</span>
                    <span class="fw-semibold <?= $f['ajuste'] < 0 ? 'text-danger' : 'text-success' ?>"><?= moneda($f['ajuste']) ?></span>
                </div>
                <?php endif; ?>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">TOTAL</span>
                    <span class="fw-bold text-primary fs-5"><?= moneda($f['total_con_iva']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Comprobante adjunto ───────────────────────────────────────────────── -->
<div class="card border-0 mb-4" id="cardComprobante">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="fw-semibold mb-0">Comprobante adjunto</h6>
        <?php if (!$esAnulada): ?>
        <label class="btn btn-outline-primary btn-sm mb-0" style="cursor:pointer">
            <?= $f['comprobante_archivo'] ? 'Reemplazar' : '+ Adjuntar comprobante' ?>
            <input type="file" id="inputComprobante" class="d-none"
                   accept="image/jpeg,image/png,image/webp,application/pdf">
        </label>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($f['comprobante_archivo']): ?>
        <div id="comprobanteActual">
            <?php
            $ext = strtolower(pathinfo($f['comprobante_archivo'], PATHINFO_EXTENSION));
            $url = url('compras/comprobante?id='.$f['id']);
            ?>
            <?php if ($ext === 'pdf'): ?>
            <a href="<?= $url ?>" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="#dc3545" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span class="fw-semibold text-danger">Ver PDF adjunto</span>
            </a>
            <?php else: ?>
            <a href="<?= $url ?>" target="_blank">
                <img src="<?= $url ?>" alt="Comprobante" class="rounded border"
                     style="max-height:220px;max-width:100%;object-fit:contain">
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-0" id="sinComprobante">No hay comprobante adjunto aún.</p>
        <?php endif; ?>
        <div id="uploadProgress" class="d-none mt-2">
            <div class="progress" style="height:6px"><div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div></div>
            <p class="small text-muted mt-1 mb-0">Subiendo…</p>
        </div>
    </div>
</div>

<!-- ── Pagos aplicados ────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="fw-semibold mb-0">Pagos registrados</h6>
        <?php if (!$esAnulada): ?>
        <a href="<?= url('pagos-proveedores/nuevo?proveedor_id='.$f['proveedor_id']) ?>"
           class="btn btn-outline-success btn-sm">+ Registrar pago</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pagos)): ?>
        <p class="text-muted small p-3 mb-0">No se registraron pagos para esta factura.</p>
        <?php else: ?>
        <?php
        $pagosActivos = array_filter($pagos, fn($p) => ($p['estado'] ?? '') === 'activo');
        $totalPagado  = array_sum(array_column($pagosActivos, 'monto_aplicado'));
        $saldo       = (float)$f['total_con_iva'] - $totalPagado;
        $metodos = [
            'monto_efectivo'      => 'Efectivo',
            'monto_transferencia' => 'Transferencia',
            'monto_mercado_pago'  => 'Mercado Pago',
            'monto_debito'        => 'Débito',
            'monto_cheque'        => 'Cheque',
            'monto_otro'          => 'Otro',
        ];
        ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Medios de pago</th>
                        <th>Origen</th>
                        <th>Registrado por</th>
                        <th class="text-end">Monto aplicado</th>
                        <th class="text-center" style="width:70px">Comprobante</th>
                        <th class="text-center" style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $p):
                        $mediosTexto = [];
                        foreach ($metodos as $col => $lbl) {
                            $m = (float)($p[$col] ?? 0);
                            if ($m > 0) $mediosTexto[] = $lbl . ' ' . moneda($m);
                        }
                        $esAnuladoPago = ($p['estado'] ?? '') === 'anulado';
                    ?>
                    <tr class="<?= $esAnuladoPago ? 'text-muted text-decoration-line-through' : '' ?>">
                        <td class="small"><?= fecha($p['fecha']) ?></td>
                        <td class="small"><?= e(implode(' · ', $mediosTexto) ?: '—') ?></td>
                        <td class="small">
                            <?php if (($p['origen_pago'] ?? 'empresa') === 'socio'): ?>
                            <span class="badge bg-info-soft text-info">Socio: <?= e($p['nombre_socio'] ?? '') ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary-soft text-secondary">Empresa</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($p['usuario_nombre'] ?? '—') ?></td>
                        <td class="text-end fw-semibold"><?= moneda($p['monto_aplicado']) ?></td>
                        <td class="text-center">
                            <?php if ($p['comprobante_path']): ?>
                            <a href="<?= url('pagos-proveedores/comprobante?id='.$p['id']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Ver comprobante de pago">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= url('pagos-proveedores/ver?id='.$p['id']) ?>"
                               class="btn btn-sm btn-outline-primary py-0" title="Ver pago">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end small fw-semibold">Total pagado:</td>
                        <td class="text-end fw-bold text-success"><?= moneda($totalPagado) ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <?php if ($saldo > 0.01): ?>
                    <tr>
                        <td colspan="4" class="text-end small fw-semibold">Saldo pendiente:</td>
                        <td class="text-end fw-bold text-danger"><?= moneda($saldo) ?></td>
                        <td colspan="2"></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>', FACTURA_ID=<?= $f['id'] ?>;

function anular(id){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/compras/anular`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular esta factura?', ejecutar, { label: 'Anular', sub: 'El stock será revertido.' });
    } else {
        if (confirm('¿Anular esta factura? El stock será revertido.')) ejecutar();
    }
}

// ── Upload comprobante AJAX ────────────────────────────────────────────────────
const inputComp = document.getElementById('inputComprobante');
if (inputComp) {
    inputComp.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const prog = document.getElementById('uploadProgress');
        prog.classList.remove('d-none');

        const fd = new FormData();
        fd.append('id', FACTURA_ID);
        fd.append('_csrf_token', CSRF_TOKEN);
        fd.append('comprobante_archivo', file);

        try {
            const r = await fetch(`${BASE_URL}/compras/subir-comprobante`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                showToast('Comprobante adjuntado correctamente.');
                setTimeout(() => location.reload(), 700);
            } else {
                showToast(d.error || 'Error al subir el archivo.', 'danger');
            }
        } catch {
            showToast('Error de conexión.', 'danger');
        } finally {
            prog.classList.add('d-none');
            this.value = '';
        }
    });
}
</script>

<style>
@media print {
    .btn, .sidebar, .header-top, nav, .breadcrumb { display:none!important; }
    .main-content { margin:0!important; }
}
</style>
