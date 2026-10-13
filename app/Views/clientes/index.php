<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Clientes</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Importar Excel
        </button>
        <a href="<?= url('clientes/nuevo') ?>" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i> Nuevo cliente</a>
    </div>
</div>

<?php if (!empty($esAdmin)): ?>
<div class="card border-0 mb-3">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Disponibilidad de productos y gustos en el Portal de Clientes Mayoristas</h6>
    </div>
    <div class="card-body pt-0">
        <p class="text-muted small mb-3">
            Este criterio decide cuándo un producto (o un gusto/opción dentro de él, ej. sabores de helado)
            aparece deshabilitado como "Sin stock" en <code>/portal</code>. A diferencia del cajero en el
            Punto de Venta, el cliente no tiene forma de pedirlo igual sin stock — por eso conviene elegir
            con cuidado.
        </p>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="p-3 rounded-3 border d-block" style="cursor:pointer">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="criterioStockPortal" id="critStockMinimo" value="stock_minimo"
                               <?= ($portalConfig['criterio_stock'] ?? 'stock_minimo') === 'stock_minimo' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="critStockMinimo">Stock mínimo (recomendado)</label>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        Se deshabilita apenas la materia prima/insumo entra en zona de reposición
                        (stock actual ≤ stock mínimo), aunque todavía tenga algo físico. Más conservador:
                        nunca deja que un pedido del portal dependa de algo que ya hay que reponer.
                    </p>
                </label>
            </div>
            <div class="col-md-6">
                <label class="p-3 rounded-3 border d-block" style="cursor:pointer">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="criterioStockPortal" id="critStockCero" value="stock_cero"
                               <?= ($portalConfig['criterio_stock'] ?? 'stock_minimo') === 'stock_cero' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="critStockCero">Solo cuando esté en cero</label>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        Se deshabilita recién con stock actual ≤ 0 — mismo criterio que usa el cajero en
                        el Punto de Venta. Deja pedir por más tiempo, a costa de acercarse más seguido a
                        quedarse sin materia prima.
                    </p>
                </label>
            </div>
        </div>
        <div id="portalConfigMsg" class="small mt-2"></div>
    </div>
</div>
<script>
document.querySelectorAll('input[name="criterioStockPortal"]').forEach(r => {
    r.addEventListener('change', async function () {
        const msg = document.getElementById('portalConfigMsg');
        msg.textContent = 'Guardando...';
        msg.className = 'small mt-2 text-muted';
        const fd = new FormData();
        fd.append('_csrf_token', '<?= Csrf::token() ?>');
        fd.append('criterio_stock', this.value);
        try {
            const r = await fetch('<?= url('clientes/guardar-config-portal') ?>', { method: 'POST', body: fd });
            const d = await r.json();
            msg.textContent = d.ok ? 'Guardado — ya aplica para nuevas visitas al portal.' : (d.error || 'No se pudo guardar.');
            msg.className = 'small mt-2 ' + (d.ok ? 'text-success' : 'text-danger');
        } catch {
            msg.textContent = 'Error de conexión.';
            msg.className = 'small mt-2 text-danger';
        }
    });
});
</script>
<?php endif; ?>

<div class="card border-0">
    <div class="card-body p-0">
        <table id="tblClientes" class="table table-hover align-middle mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Condición IVA</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td class="fw-semibold"><?= e($c['nombre']) ?></td>
                <td>
                    <?php if ($c['documento']): ?>
                    <small class="badge bg-secondary-subtle text-secondary-emphasis"><?= strtoupper(e($c['tipo_doc'])) ?></small>
                    <?= e($c['documento']) ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= e(str_replace('_', ' ', ucfirst($c['condicion_iva']))) ?></td>
                <td><?= $c['telefono'] ? e($c['telefono']) : '—' ?></td>
                <td><?= $c['email'] ? e($c['email']) : '—' ?></td>
                <td>
                    <?php if ($c['activo']): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                    <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="<?= url('clientes/vista-portal?id=' . $c['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="Ver qué ve este cliente en su portal">
                        <i class="ti ti-eye"></i>
                    </a>
                    <a href="<?= url('clientes/editar?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar">
                        <i class="ti ti-pencil"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger btn-eliminar"
                            data-id="<?= $c['id'] ?>"
                            data-nombre="<?= e($c['nombre']) ?>"
                            title="Eliminar">
                        <i class="ti ti-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';

document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#tblClientes', {
        stateSave: true,
        scrollX: true,
        fixedHeader: true,
        layout: {
            topStart: { buttons: ['colvis', 'excel', 'pdf'] },
            topEnd:   'search',
        },
        columnDefs: [{ orderable: false, targets: -1 }],
    });

    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function () {
            const id     = this.dataset.id;
            const nombre = this.dataset.nombre;
            const fila   = this.closest('tr');

            const ejecutar = async () => {
                try {
                    const fd = new FormData();
                    fd.set('id', id);
                    fd.set('_csrf', CSRF_TOKEN);
                    const r = await fetch(`${BASE_URL}/clientes/eliminar`, { method: 'POST', body: fd });
                    const d = await r.json();
                    if (d.ok) {
                        showToast(d.mensaje);
                        fila.remove();
                    } else {
                        showToast(d.error, 'danger');
                    }
                } catch { showToast('Error de conexión.', 'danger'); }
            };
            const msg = `¿Eliminar el cliente "${nombre}"?`;
            if (typeof showConfirm === 'function') {
                showConfirm(msg, ejecutar, { label: 'Eliminar' });
            } else {
                if (confirm(msg)) ejecutar();
            }
        });
    });
});
</script>
<?php
$importUrl       = url('clientes/importar');
$importPlantilla = url('clientes/plantilla');
$importModulo    = 'Clientes';
$importColumnas  = ['nombre *', 'documento', 'tipo_doc (dni/cuit)', 'condicion_iva', 'email', 'telefono', 'direccion'];
require BASE_PATH . '/app/Views/partials/modal_importar.php';
?>
