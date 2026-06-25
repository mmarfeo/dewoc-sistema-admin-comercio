<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Clientes</h1>
    <a href="<?= url('clientes/nuevo') ?>" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i> Nuevo cliente
    </a>
</div>

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
        language: DT_LANG_ES,
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
        btn.addEventListener('click', async function () {
            const id     = this.dataset.id;
            const nombre = this.dataset.nombre;
            if (!confirm(`¿Eliminar el cliente "${nombre}"?`)) return;

            try {
                const fd = new FormData();
                fd.set('id', id);
                fd.set('_csrf', CSRF_TOKEN);
                const r = await fetch(`${BASE_URL}/clientes/eliminar`, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    showToast(d.mensaje);
                    this.closest('tr').remove();
                } else {
                    showToast(d.error, 'danger');
                }
            } catch { showToast('Error de conexión.', 'danger'); }
        });
    });
});
</script>
