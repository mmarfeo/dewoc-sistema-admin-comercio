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
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <?php
        $exportParams = array_filter([
            'proveedor_id' => $filtros['proveedor_id'] ?: null,
            'desde'        => $filtros['desde']  ?: null,
            'hasta'        => $filtros['hasta']  ?: null,
            'estado'       => $filtros['estado'] ?: null,
        ]);
        $exportUrl = url('compras/exportar') . ($exportParams ? '?' . http_build_query($exportParams) : '');
        // URL de retorno (con los filtros activos) para los links de Ver/Editar de cada fila
        $volverUrl = url('compras') . ($exportParams ? '?' . http_build_query($exportParams) : '');
        ?>
        <a href="<?= $exportUrl ?>" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Exportar CSV
        </a>
        <a href="<?= url('compras/nueva') . '?volver=' . urlencode($volverUrl) ?>" class="btn btn-primary">+ Nueva factura</a>
    </div>
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
                        <th></th><!-- columna oculta: ítems -->
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
                            <a href="<?= url('compras/ver?id='.$f['id']) . '&volver=' . urlencode($volverUrl) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver detalle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php if ($f['estado'] !== 'anulada'): ?>
                            <a href="<?= url('compras/editar?id='.$f['id']) . '&volver=' . urlencode($volverUrl) ?>" class="btn btn-sm btn-outline-secondary py-0 ms-1" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="anular(<?= $f['id'] ?>)" title="Anular">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </button>
                            <?php endif; ?>
                        </td>
                        <!-- ítems ocultos para búsqueda -->
                        <td class="d-none"><?= e($f['items_nombres'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>{
    $('#tblCompras').DataTable({
        order: [[0, 'desc']],
        columnDefs: [{ targets: 9, visible: false, searchable: true }],
        language: { search: 'Buscar:', searchPlaceholder: 'Proveedor, ítem, nro. comprobante…' },
    });
});
function anular(id){
    const ejecutar = async () => {
        const fd=new FormData(); fd.append('id',id); fd.append('_csrf_token',CSRF_TOKEN);
        const r=await fetch(`${BASE_URL}/compras/anular`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):showToast(d.error,'danger');
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Anular esta factura?', ejecutar, { label: 'Anular', sub: 'Se revertirá el stock ingresado.' });
    } else {
        if (confirm('¿Anular esta factura? Se revertirá el stock ingresado.')) ejecutar();
    }
}
</script>
<?php
$importUrl        = url('compras/importar');
$importPreviewUrl = url('compras/previsualizar');
$importPlantilla  = url('compras/plantilla');
$importModulo     = 'Facturas de Compra';
$importColumnas   = [
    'proveedor_razon_social *', 'nro_comprobante *', 'fecha * (AAAA-MM-DD)',
    'tipo_comprobante (factura_a|factura_b|factura_c|ticket|remito|otro)',
    'condicion_pago (contado|cuenta_corriente|cheque|transferencia)', 'notas',
    'tipo_item * (materia_prima|insumo|producto)', 'item_nombre *',
    'cantidad *', 'cantidad_contenida', 'precio_unitario *',
    'descuento_pct (% dto, default 0)',
    'importe (subtotal neto — se calcula si se omite)',
    'iva_porcentaje (0|10.5|21)',
    'iva_importe (monto IVA — se calcula si se omite)',
    'monto_no_gravado (default 0)',
    'total (importe+iva+no_grav — se calcula si se omite)',
    'observaciones',
];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
