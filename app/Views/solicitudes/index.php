<?php
$tipoLabels = [
    'producto'      => ['label' => 'Producto',       'badge' => 'bg-primary'],
    'materia_prima' => ['label' => 'Materia prima',   'badge' => 'bg-info'],
    'tarea'         => ['label' => 'Tarea',           'badge' => 'bg-secondary'],
    'inventario'    => ['label' => 'Inventario',      'badge' => 'bg-success'],
];
$prioridadLabels = [
    'baja'  => 'bg-secondary-soft text-secondary',
    'media' => 'bg-warning-soft text-warning',
    'alta'  => 'bg-danger-soft text-danger',
];
$estadoLabels = [
    'pendiente'  => ['label' => 'Pendiente',   'badge' => 'bg-warning-soft text-warning'],
    'en_proceso' => ['label' => 'En proceso',  'badge' => 'bg-info-soft text-info'],
    'resuelta'   => ['label' => 'Resuelta',    'badge' => 'bg-success-soft text-success'],
    'rechazada'  => ['label' => 'Rechazada',   'badge' => 'bg-danger-soft text-danger'],
];
// Mismo permiso que ya exige SolicitudesController::cambiarEstado() — antes acá se
// chequeaba el rol admin a secas, así que un encargado con "solicitudes: editar"
// habilitado no veía los botones aunque el backend sí le dejara usarlos.
$puedeResolverSolicitudes = canEdit('solicitudes');

$tareaEstadoLabels = [
    'pendiente'  => ['label' => 'Pendiente',   'badge' => 'bg-warning-soft text-warning'],
    'aceptada'   => ['label' => 'Aceptada',    'badge' => 'bg-info-soft text-info'],
    'rechazada'  => ['label' => 'Rechazada',   'badge' => 'bg-danger-soft text-danger'],
    'finalizada' => ['label' => 'Finalizada',  'badge' => 'bg-success-soft text-success'],
];
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0 fw-bold">Solicitudes</h1>
</div>

<!-- Pestañas: "Solicitudes" (pedir compra de algo, o una tarea — que ahora también se
     puede asignar desde acá mismo) e "Inventario" (pedir un recuento de stock, ver
     docs/solicitudes-inventario-checklist.md) — cada una es su propia tabla, solo
     comparten pantalla. El listado de tareas asignadas ya no tiene pestaña propia — se
     navega a él con el botón "Ver tareas asignadas" del header de Solicitudes, para no
     tener un título de sección aparte. -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $vista !== 'inventario' ? 'active' : '' ?>" href="<?= url('solicitudes') ?>">Solicitudes</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'inventario' ? 'active' : '' ?>" href="<?= url('solicitudes?vista=inventario') ?>">Inventario</a>
    </li>
</ul>

<!-- Comunes a las pestañas "Solicitudes" e "Inventario" -->
<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

function esc(v){const d=document.createElement('div');d.textContent=v||'';return d.innerHTML;}

async function cambiarEstadoSolicitud(id, estado){
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('id', id);
    fd.append('estado', estado);
    const r = await fetch(`${BASE_URL}/solicitudes/cambiar-estado`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo actualizar.');
}
</script>

<?php if ($vista === 'tareas'): ?>
<div class="row g-3">
    <!-- Listado (la creación se hace desde la pestaña Solicitudes, tipo "Tarea a realizar") -->
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-semibold mb-0">Tareas asignadas</h6>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="btn-group btn-group-sm flex-wrap">
                        <a href="<?= url('solicitudes?vista=tareas') ?>" class="btn btn-outline-secondary <?= $filtroEstado === '' ? 'active' : '' ?>">Todas</a>
                        <a href="<?= url('solicitudes?vista=tareas&estado=pendiente') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'pendiente' ? 'active' : '' ?>">Pendientes</a>
                        <a href="<?= url('solicitudes?vista=tareas&estado=aceptada') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'aceptada' ? 'active' : '' ?>">Aceptadas</a>
                        <a href="<?= url('solicitudes?vista=tareas&estado=rechazada') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'rechazada' ? 'active' : '' ?>">Rechazadas</a>
                        <a href="<?= url('solicitudes?vista=tareas&estado=finalizada') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'finalizada' ? 'active' : '' ?>">Finalizadas</a>
                    </div>
                    <a href="<?= url('solicitudes') ?>" class="btn btn-outline-primary btn-sm">← Volver a Solicitudes</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Título</th><th>Asignada a</th><th>Estado</th>
                                <th>Creada por</th><th>Fecha</th><th>Resolución</th>
                                <?php if (canEdit('tareas_asignar')): ?><th class="text-center" style="width:80px">Acciones</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tareas): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay tareas.</td></tr>
                        <?php endif; ?>
                        <?php
                        $destinoLabels = [
                            'usuario_especifico' => null, // se resuelve abajo con destino_nombre
                            'turno_abierto'       => 'Quien tenga caja abierta',
                            'administradores'     => 'Administradores',
                        ];
                        ?>
                        <?php foreach ($tareas as $t): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?= e($t['titulo']) ?></div>
                                    <?php if ($t['descripcion']): ?><div class="small text-muted"><?= e($t['descripcion']) ?></div><?php endif; ?>
                                </td>
                                <td class="small"><?= $t['modo_destino'] === 'usuario_especifico' ? e($t['destino_nombre'] ?? '—') : e($destinoLabels[$t['modo_destino']] ?? $t['modo_destino']) ?></td>
                                <td><span class="badge <?= $tareaEstadoLabels[$t['estado']]['badge'] ?>"><?= $tareaEstadoLabels[$t['estado']]['label'] ?></span></td>
                                <td class="small"><?= e($t['crea_nombre']) ?></td>
                                <td class="small text-muted"><?= fechaHora($t['created_at']) ?></td>
                                <td class="small text-muted">
                                    <?php if ($t['estado'] === 'rechazada' && $t['motivo_rechazo']): ?>
                                        "<?= e($t['motivo_rechazo']) ?>" — <?= e($t['responde_nombre'] ?? '') ?>
                                    <?php elseif ($t['estado'] === 'finalizada'): ?>
                                        <?= e($t['finaliza_nombre'] ?? '') ?>, <?= fechaHora($t['finalizado_at']) ?>
                                    <?php elseif ($t['estado'] === 'aceptada'): ?>
                                        Aceptada por <?= e($t['responde_nombre'] ?? '') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <?php if (canEdit('tareas_asignar')): ?>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 btn-editar-tarea" title="Editar"
                                        data-id="<?= $t['id'] ?>"
                                        data-titulo="<?= e($t['titulo']) ?>"
                                        data-descripcion="<?= e($t['descripcion'] ?? '') ?>"
                                        data-modo_destino="<?= e($t['modo_destino']) ?>"
                                        data-usuario_destino_id="<?= $t['usuario_destino_id'] ?? '' ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 btn-eliminar-tarea" title="Eliminar"
                                        data-id="<?= $t['id'] ?>" data-titulo="<?= e($t['titulo']) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (canEdit('tareas_asignar')): ?>
<!-- Modal editar tarea -->
<div class="modal fade" id="modalEditarTarea" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Editar tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTarea">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" id="editTareaId">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" id="editTareaTitulo" class="form-control form-control-sm" maxlength="150" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Detalle (opcional)</label>
                        <textarea name="descripcion" id="editTareaDescripcion" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Asignar a</label>
                        <select name="modo_destino" id="editTareaModoDestino" class="form-select form-select-sm" onchange="_actualizarUiDestinoEdit()">
                            <option value="usuario_especifico">Un usuario en particular</option>
                            <option value="turno_abierto">Quien tenga la caja abierta ese día</option>
                            <option value="administradores">Administradores</option>
                        </select>
                    </div>
                    <div class="mb-3" id="wrapEditTareaUsuario">
                        <label class="form-label small fw-semibold">Usuario</label>
                        <select name="usuario_destino_id" id="editTareaUsuarioDestinoId" class="form-select form-select-sm">
                            <option value="">— Elegir —</option>
                            <?php foreach ($usuariosActivos as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="editTareaError" class="alert alert-danger py-2 small d-none"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditTarea">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE_URL_TA   = '<?= BASE_URL ?>';
const CSRF_TOKEN_TA = '<?= Csrf::token() ?>';

function _showModalTA(id) {
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
function _hideModalTA(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    el.querySelector('[data-bs-dismiss="modal"]')?.click();
}

function _actualizarUiDestinoEdit() {
    const esEspecifico = document.getElementById('editTareaModoDestino').value === 'usuario_especifico';
    document.getElementById('wrapEditTareaUsuario').style.display = esEspecifico ? '' : 'none';
}

document.querySelectorAll('.btn-editar-tarea').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('editTareaId').value = this.dataset.id;
        document.getElementById('editTareaTitulo').value = this.dataset.titulo;
        document.getElementById('editTareaDescripcion').value = this.dataset.descripcion;
        document.getElementById('editTareaModoDestino').value = this.dataset.modo_destino;
        document.getElementById('editTareaUsuarioDestinoId').value = this.dataset.usuario_destino_id || '';
        document.getElementById('editTareaError').classList.add('d-none');
        _actualizarUiDestinoEdit();
        _showModalTA('modalEditarTarea');
    });
});

document.getElementById('formEditarTarea').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('editTareaError');
    errEl.classList.add('d-none');
    const btn = document.getElementById('btnGuardarEditTarea');
    btn.disabled = true;
    try {
        const fd = new FormData(this);
        const r = await fetch(`${BASE_URL_TA}/tareas/actualizar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});

document.querySelectorAll('.btn-eliminar-tarea').forEach(btn => {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const titulo = this.dataset.titulo;
        const ejecutar = async () => {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('_csrf_token', CSRF_TOKEN_TA);
            const r = await fetch(`${BASE_URL_TA}/tareas/eliminar`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 600); }
            else showToast(d.error || 'No se pudo eliminar.', 'danger');
        };
        const msg = `¿Eliminar la tarea "${titulo}"?`;
        if (typeof showConfirm === 'function') {
            showConfirm(msg, ejecutar, { label: 'Eliminar', sub: 'Esta acción no se puede deshacer.' });
        } else if (confirm(msg + ' Esta acción no se puede deshacer.')) {
            ejecutar();
        }
    });
});
</script>
<?php endif; ?>

<?php elseif ($vista === 'inventario'): ?>
<div class="row g-3">
    <!-- Nueva solicitud de inventario -->
    <div class="col-lg-4">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Nueva solicitud de inventario</h6>
                <p class="text-muted small mb-0 mt-1">Elegí qué contar: categorías completas y/o ítems puntuales.</p>
            </div>
            <div class="card-body">
                <form id="formSolicitudInventario">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="tipo" value="inventario">

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Agregar categoría</label>
                        <div class="input-group input-group-sm">
                            <select id="critCategoriaSelect" class="form-select form-select-sm">
                                <option value="">— Elegir categoría —</option>
                                <?php if ($categoriasProducto): ?>
                                <optgroup label="Productos">
                                    <?php foreach ($categoriasProducto as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-tipo="producto"><?= e($c['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if ($categoriasMateriaPrima): ?>
                                <optgroup label="Materias primas">
                                    <?php foreach ($categoriasMateriaPrima as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-tipo="materia_prima"><?= e($c['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if ($categoriasInsumo): ?>
                                <optgroup label="Insumos">
                                    <?php foreach ($categoriasInsumo as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-tipo="insumo"><?= e($c['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="btnAgregarCategoria">Agregar</button>
                        </div>
                        <div id="critCategoriaPanel" class="border rounded p-2 mt-1 d-none" style="max-height:220px;overflow-y:auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="critCategoriaTodaChk" checked>
                                <label class="form-check-label small fw-semibold" for="critCategoriaTodaChk">
                                    Toda la categoría <span id="critCategoriaTodaCount" class="text-muted fw-normal"></span>
                                </label>
                            </div>
                            <div id="critCategoriaItemsList" class="ms-3 mt-1"></div>
                        </div>
                    </div>
                    <?php if ($ingredientesGenericos): ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Agregar ingrediente genérico</label>
                        <div class="input-group input-group-sm">
                            <select id="critGenericoSelect" class="form-select form-select-sm">
                                <option value="">— Elegir —</option>
                                <?php foreach ($ingredientesGenericos as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= e($g['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="btnAgregarGenerico">Agregar</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2 position-relative">
                        <label class="form-label small fw-semibold">Agregar producto / materia prima / insumo puntual</label>
                        <input type="text" id="critItemBuscador" class="form-control form-control-sm"
                               placeholder="Buscar..." autocomplete="off">
                        <div id="critItemDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none;top:100%;left:0"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Se va a contar</label>
                        <div id="critChips" class="d-flex flex-wrap gap-1"><span class="text-muted small">Nada elegido todavía.</span></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Asignar a <span class="text-muted fw-normal">(opcional)</span></label>
                        <select name="asignado_a" class="form-select form-select-sm">
                            <option value="">— Cualquiera —</option>
                            <?php foreach ($usuariosActivos as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="criterios_json" id="solCriteriosJson" value="[]">

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Información adicional (opcional)</label>
                        <textarea name="descripcion" class="form-control form-control-sm" rows="2"
                                  placeholder="Urgencia, dónde buscar, aclaraciones..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Prioridad</label>
                        <select name="prioridad" class="form-select form-select-sm">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>

                    <div id="solInvError" class="alert alert-danger py-2 small d-none"></div>

                    <button type="submit" class="btn btn-primary btn-sm w-100" id="btnEnviarSolicitudInventario">Enviar solicitud</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="col-lg-8">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-semibold mb-0">Solicitudes de inventario</h6>
                <div class="btn-group btn-group-sm flex-wrap">
                    <a href="<?= url('solicitudes?vista=inventario') ?>" class="btn btn-outline-secondary <?= $filtroEstado === '' ? 'active' : '' ?>">Todas</a>
                    <a href="<?= url('solicitudes?vista=inventario&estado=pendiente') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'pendiente' ? 'active' : '' ?>">Pendientes</a>
                    <a href="<?= url('solicitudes?vista=inventario&estado=en_proceso') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'en_proceso' ? 'active' : '' ?>">En proceso</a>
                    <a href="<?= url('solicitudes?vista=inventario&estado=resuelta') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'resuelta' ? 'active' : '' ?>">Resueltas</a>
                    <a href="<?= url('solicitudes?vista=inventario&estado=rechazada') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'rechazada' ? 'active' : '' ?>">Rechazadas</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Detalle</th><th>Prioridad</th><th>Estado</th>
                                <th>Asignada a</th><th>Pedida por</th><th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$solicitudesInventario): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay solicitudes de inventario.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($solicitudesInventario as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?= e($s['item_nombre']) ?></div>
                                    <?php if ($s['descripcion']): ?><div class="small text-muted"><?= e($s['descripcion']) ?></div><?php endif; ?>
                                    <?php if ($s['estado'] === 'rechazada' && $s['motivo_rechazo']): ?>
                                    <div class="small text-danger">Rechazada: <?= e($s['motivo_rechazo']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['items_resueltos'])): ?>
                                    <details class="small mt-1">
                                        <summary class="text-primary" style="cursor:pointer">Ver ítems (<?= count($s['items_resueltos']) ?>)</summary>
                                        <ul class="mb-0 ps-3 text-muted">
                                            <?php foreach ($s['items_resueltos'] as $it): ?>
                                            <li><?= e($it['nombre']) ?> <span class="text-muted">(<?= e($it['tipo_label']) ?>)</span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $prioridadLabels[$s['prioridad']] ?>"><?= ucfirst($s['prioridad']) ?></span></td>
                                <td><span class="badge <?= $estadoLabels[$s['estado']]['badge'] ?>"><?= $estadoLabels[$s['estado']]['label'] ?></span></td>
                                <td class="small"><?= e($s['asignado_a_nombre'] ?? 'Cualquiera') ?></td>
                                <td class="small"><?= e($s['creador_nombre']) ?></td>
                                <td class="small text-muted"><?= fechaHora($s['created_at']) ?></td>
                                <td class="text-end">
                                    <?php if (!in_array($s['estado'], ['resuelta', 'rechazada'], true)): ?>
                                    <a href="<?= url('stock/inventario?solicitud_id=' . $s['id']) ?>" class="btn btn-sm btn-outline-primary d-block mb-1">Ir a contar</a>
                                    <?php endif; ?>
                                    <?php if ($puedeResolverSolicitudes && $s['estado'] !== 'resuelta'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($s['estado'] === 'pendiente'): ?>
                                        <button type="button" class="btn btn-outline-info" onclick="cambiarEstadoSolicitud(<?= $s['id'] ?>,'en_proceso')">En proceso</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-success" onclick="cambiarEstadoSolicitud(<?= $s['id'] ?>,'resuelta')">Resuelta</button>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeResolverSolicitudes): ?>
<!-- Solicitudes de inventario recurrentes (ej. "todos los martes, Helados") -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-semibold mb-0">Solicitudes de inventario recurrentes</h6>
                    <p class="text-muted small mb-0 mt-1">Se generan solas el día de la semana que elijas, sin tener que crearlas a mano cada vez.</p>
                </div>
                <button id="btnNuevaRecurrente" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRecurrente">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nueva recurrente
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Descripción</th><th>Día</th><th>Se va a contar</th>
                                <th>Asignada a</th><th>Prioridad</th><th>Último generado</th>
                                <th>Estado</th><th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recurrentes): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Todavía no cargaste ninguna plantilla recurrente.</td></tr>
                        <?php endif; ?>
                        <?php
                        $diasSemanaLabels = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
                        ?>
                        <?php foreach ($recurrentes as $r): ?>
                            <tr data-id="<?= $r['id'] ?>"
                                data-descripcion="<?= e($r['descripcion']) ?>"
                                data-dia_semana="<?= $r['dia_semana'] ?>"
                                data-prioridad="<?= e($r['prioridad']) ?>"
                                data-asignado_a="<?= $r['asignado_a'] ?? '' ?>"
                                data-criterios='<?= e(json_encode(array_map(fn($c) => ['tipo_criterio' => $c['tipo_criterio'], 'referencia_id' => (int)$c['referencia_id'], 'label' => $c['tipo_label'] . ': ' . $c['nombre']], $r['criterios']))) ?>'
                                class="<?= $r['activo'] ? '' : 'opacity-50' ?>">
                                <td class="fw-semibold small"><?= e($r['descripcion']) ?></td>
                                <td class="small"><?= $diasSemanaLabels[(int)$r['dia_semana']] ?? '—' ?></td>
                                <td class="small">
                                    <?php foreach ($r['criterios'] as $c): ?>
                                    <span class="badge bg-secondary-soft text-secondary me-1"><?= e($c['tipo_label']) ?>: <?= e($c['nombre']) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="small"><?= e($r['asignado_a_nombre'] ?? 'Cualquiera') ?></td>
                                <td><span class="badge <?= $prioridadLabels[$r['prioridad']] ?>"><?= ucfirst($r['prioridad']) ?></span></td>
                                <td class="small text-muted"><?= $r['ultima_fecha_generada'] ? fecha($r['ultima_fecha_generada']) : 'Nunca' ?></td>
                                <td>
                                    <span class="badge <?= $r['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                                        <?= $r['activo'] ? 'Activa' : 'Suspendida' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 btn-editar-recurrente" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning py-0 px-2 btn-toggle-recurrente" title="<?= $r['activo'] ? 'Suspender' : 'Reactivar' ?>">
                                        <?php if ($r['activo']): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                        <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                        <?php endif; ?>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-recurrente" title="Eliminar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal nuevo/editar recurrente -->
<div class="modal fade" id="modalRecurrente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formRecurrente">
                <input type="hidden" name="_csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" id="recId">
                <input type="hidden" name="criterios_json" id="recCriteriosJson" value="[]">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalRecurrenteTitulo">Nueva Solicitud Recurrente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Descripción *</label>
                            <input type="text" name="descripcion" id="recDescripcion" class="form-control" maxlength="255" required
                                   placeholder="Ej. Inventario semanal — Helados">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Día de la semana *</label>
                            <select name="dia_semana" id="recDiaSemana" class="form-select" required>
                                <option value="1">Lunes</option>
                                <option value="2">Martes</option>
                                <option value="3">Miércoles</option>
                                <option value="4">Jueves</option>
                                <option value="5">Viernes</option>
                                <option value="6">Sábado</option>
                                <option value="7">Domingo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Prioridad</label>
                            <select name="prioridad" id="recPrioridad" class="form-select">
                                <option value="baja">Baja</option>
                                <option value="media" selected>Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Asignar a <span class="text-muted fw-normal">(opcional)</span></label>
                            <select name="asignado_a" id="recAsignadoA" class="form-select">
                                <option value="">— Cualquiera —</option>
                                <?php foreach ($usuariosActivos as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small">Agregar categoría</label>
                            <div class="input-group input-group-sm">
                                <select id="recCritCategoriaSelect" class="form-select form-select-sm">
                                    <option value="">— Elegir categoría —</option>
                                    <?php if ($categoriasProducto): ?>
                                    <optgroup label="Productos">
                                        <?php foreach ($categoriasProducto as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-tipo="producto"><?= e($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                    <?php if ($categoriasMateriaPrima): ?>
                                    <optgroup label="Materias primas">
                                        <?php foreach ($categoriasMateriaPrima as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-tipo="materia_prima"><?= e($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                    <?php if ($categoriasInsumo): ?>
                                    <optgroup label="Insumos">
                                        <?php foreach ($categoriasInsumo as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-tipo="insumo"><?= e($c['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="recBtnAgregarCategoria">Agregar</button>
                            </div>
                            <div id="recCritCategoriaPanel" class="border rounded p-2 mt-1 d-none" style="max-height:220px;overflow-y:auto">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recCritCategoriaTodaChk" checked>
                                    <label class="form-check-label small fw-semibold" for="recCritCategoriaTodaChk">
                                        Toda la categoría <span id="recCritCategoriaTodaCount" class="text-muted fw-normal"></span>
                                    </label>
                                </div>
                                <div id="recCritCategoriaItemsList" class="ms-3 mt-1"></div>
                            </div>
                        </div>
                        <?php if ($ingredientesGenericos): ?>
                        <div class="col-12">
                            <label class="form-label small">Agregar ingrediente genérico</label>
                            <div class="input-group input-group-sm">
                                <select id="recCritGenericoSelect" class="form-select form-select-sm">
                                    <option value="">— Elegir —</option>
                                    <?php foreach ($ingredientesGenericos as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= e($g['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" id="recBtnAgregarGenerico">Agregar</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-12 position-relative">
                            <label class="form-label small">Agregar producto / materia prima / insumo puntual</label>
                            <input type="text" id="recCritItemBuscador" class="form-control form-control-sm" placeholder="Buscar..." autocomplete="off">
                            <div id="recCritItemDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none;top:100%;left:0"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Se va a contar</label>
                            <div id="recCritChips" class="d-flex flex-wrap gap-1"><span class="text-muted small">Nada elegido todavía.</span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarRecurrente">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── Criterios de inventario (categorías / ítems puntuales / ingredientes genéricos) ──
let solCriterios = [];

function renderCriterios(chipsElId, jsonElId, criterios){
    const wrap = document.getElementById(chipsElId);
    if(!criterios.length){
        wrap.innerHTML = '<span class="text-muted small">Nada elegido todavía.</span>';
    } else {
        wrap.innerHTML = criterios.map((c,i) =>
            `<span class="badge bg-primary-soft text-primary d-inline-flex align-items-center gap-1">${esc(c.label)}` +
            `<button type="button" class="btn-close" style="font-size:.55rem" data-idx="${i}" aria-label="Quitar"></button></span>`
        ).join('');
        wrap.querySelectorAll('.btn-close').forEach(b => b.addEventListener('click', function(){
            criterios.splice(parseInt(this.dataset.idx), 1);
            renderCriterios(chipsElId, jsonElId, criterios);
        }));
    }
    document.getElementById(jsonElId).value = JSON.stringify(
        criterios.map(c => ({ tipo_criterio: c.tipo_criterio, referencia_id: c.referencia_id }))
    );
}

// El mismo nombre de categoría puede existir para más de un tipo (ej. "Helados" como
// categoría de Producto Y de Materia prima) — sin aclarar el tipo, el chip queda ambiguo
// sobre a cuál de las dos se refiere. data-tipo viene de la opción elegida (ver <option>
// arriba, uno por cada tipo/optgroup).
function labelCategoria(sel) {
    const tipo = sel.options[sel.selectedIndex].dataset.tipo;
    return 'Categoría de ' + (TIPO_ITEM_LABELS[tipo] || tipo) + ': ' + sel.options[sel.selectedIndex].text;
}

/**
 * Al elegir una categoría, en vez de agregarla entera de una, se despliega un panel con
 * un checkbox "Toda la categoría" (tildado por defecto, igual que el comportamiento de
 * siempre) y la lista de ítems que tiene asignados esa categoría, TAMBIÉN tildados por
 * defecto — así se puede destildar puntualmente los 2-3 que no se quieren, sin tener que
 * volver a tildar uno por uno los que sí. "Toda la categoría" es solo un atajo de
 * "tildar/destildar todos" (y se auto-actualiza a tildado/destildado/indeterminado según
 * lo que haya marcado en la lista) — lo que realmente se agrega al guardar es SIEMPRE lo
 * que haya quedado tildado en ese momento (foto fija, no dinámico): si están todos
 * tildados se manda como un único criterio "categoría entera" (igual que siempre); si se
 * destildó alguno, se manda cada ítem tildado como criterio individual.
 */
function armarSelectorCategoria(selectId, panelId, todaChkId, todaCountId, itemsListId, btnId, criterios, chipsElId, jsonElId){
    const sel        = document.getElementById(selectId);
    const panel       = document.getElementById(panelId);
    const todaChk     = document.getElementById(todaChkId);
    const todaCount   = document.getElementById(todaCountId);
    const itemsList   = document.getElementById(itemsListId);
    let itemsCache    = [];

    function itemsChks() {
        return Array.from(itemsList.querySelectorAll('.crit-cat-item-chk'));
    }

    // Refleja en "Toda la categoría" el estado real de los ítems: tildado si están todos
    // tildados, destildado si no queda ninguno, o "indeterminado" (el tercer estado nativo
    // del checkbox, ni tildado ni vacío) si hay una mezcla — así no miente sobre qué se va
    // a mandar.
    function actualizarTodaChk() {
        const chks = itemsChks();
        const marcados = chks.filter(c => c.checked).length;
        todaChk.checked = chks.length > 0 && marcados === chks.length;
        todaChk.indeterminate = marcados > 0 && marcados < chks.length;
    }

    sel.addEventListener('change', async function(){
        const catId = this.value;
        if (!catId) { panel.classList.add('d-none'); itemsCache = []; return; }
        panel.classList.remove('d-none');
        todaChk.checked = true;
        todaChk.indeterminate = false;
        todaCount.textContent = '';
        itemsList.innerHTML = '<p class="text-muted small mb-0">Cargando…</p>';
        try {
            const r = await fetch(`${BASE_URL}/solicitudes/items-por-categoria?categoria_id=${catId}`);
            itemsCache = await r.json();
        } catch { itemsCache = []; }
        todaCount.textContent = `(${itemsCache.length} ítem${itemsCache.length === 1 ? '' : 's'})`;
        if (!itemsCache.length) {
            itemsList.innerHTML = '<p class="text-muted small mb-0">Esta categoría no tiene ítems cargados.</p>';
            return;
        }
        itemsList.innerHTML = itemsCache.map((it, i) => `
            <div class="form-check">
                <input class="form-check-input crit-cat-item-chk" type="checkbox" id="${itemsListId}_${i}" data-idx="${i}" checked>
                <label class="form-check-label small" for="${itemsListId}_${i}">${esc(it.nombre)}</label>
            </div>`).join('');
        itemsChks().forEach(chk => chk.addEventListener('change', actualizarTodaChk));
    });

    todaChk.addEventListener('change', function(){
        itemsChks().forEach(chk => { chk.checked = this.checked; });
    });

    document.getElementById(btnId).addEventListener('click', function(){
        if (!sel.value) return;
        const chks     = itemsChks();
        const elegidos = chks.filter(c => c.checked);
        if (!elegidos.length) return;

        if (elegidos.length === chks.length) {
            // Nada destildado: se manda la categoría entera, igual que siempre.
            criterios.push({ tipo_criterio: 'categoria', referencia_id: parseInt(sel.value), label: labelCategoria(sel) });
        } else {
            elegidos.forEach(chk => {
                const it = itemsCache[parseInt(chk.dataset.idx)];
                criterios.push({ tipo_criterio: it.tipo, referencia_id: it.id, label: (TIPO_ITEM_LABELS[it.tipo] || it.tipo) + ': ' + it.nombre });
            });
        }
        sel.value = '';
        panel.classList.add('d-none');
        renderCriterios(chipsElId, jsonElId, criterios);
    });
}

armarSelectorCategoria('critCategoriaSelect', 'critCategoriaPanel', 'critCategoriaTodaChk', 'critCategoriaTodaCount', 'critCategoriaItemsList', 'btnAgregarCategoria', solCriterios, 'critChips', 'solCriteriosJson');

const btnAgregarGenerico = document.getElementById('btnAgregarGenerico');
if (btnAgregarGenerico) btnAgregarGenerico.addEventListener('click', function(){
    const sel = document.getElementById('critGenericoSelect');
    if(!sel.value) return;
    solCriterios.push({ tipo_criterio: 'ingrediente_generico', referencia_id: parseInt(sel.value), label: 'Ingrediente genérico: ' + sel.options[sel.selectedIndex].text });
    sel.value = '';
    renderCriterios('critChips', 'solCriteriosJson', solCriterios);
});

const TIPO_ITEM_LABELS = { producto: 'Producto', materia_prima: 'Materia prima', insumo: 'Insumo' };

function armarBuscadorCriterios(inputId, dropdownId, criterios, chipsElId, jsonElId){
    let timeoutId;
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input) return;

    input.addEventListener('input', function(){
        clearTimeout(timeoutId);
        const q = this.value.trim();
        if(!q){ dropdown.style.display = 'none'; return; }
        timeoutId = setTimeout(async () => {
            const r = await fetch(`${BASE_URL}/solicitudes/buscar-items?q=${encodeURIComponent(q)}`);
            const data = await r.json();
            dropdown.innerHTML = '';
            if(!data.length){ dropdown.style.display = 'none'; return; }
            data.forEach(it => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action small py-2';
                btn.innerHTML = `<span class="fw-semibold">${esc(it.nombre)}</span> <span class="text-muted ms-2">${esc(TIPO_ITEM_LABELS[it.tipo] || it.tipo)}</span>`;
                btn.addEventListener('click', () => {
                    criterios.push({ tipo_criterio: it.tipo, referencia_id: it.id, label: (TIPO_ITEM_LABELS[it.tipo] || it.tipo) + ': ' + it.nombre });
                    input.value = '';
                    dropdown.style.display = 'none';
                    renderCriterios(chipsElId, jsonElId, criterios);
                });
                dropdown.appendChild(btn);
            });
            dropdown.style.display = 'block';
        }, 200);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest(`#${inputId}`) && !e.target.closest(`#${dropdownId}`)) dropdown.style.display = 'none';
    });
}
armarBuscadorCriterios('critItemBuscador', 'critItemDropdown', solCriterios, 'critChips', 'solCriteriosJson');

// ── Envío del formulario de inventario ───────────────────────────────────────
document.getElementById('formSolicitudInventario').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl = document.getElementById('solInvError');
    errEl.classList.add('d-none');
    if (!solCriterios.length) {
        errEl.textContent = 'Elegí al menos una categoría o ítem para el inventario.';
        errEl.classList.remove('d-none');
        return;
    }
    const btn = document.getElementById('btnEnviarSolicitudInventario');
    btn.disabled = true;
    try {
        const fd = new FormData(this);
        const r = await fetch(`${BASE_URL}/solicitudes/crear`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});

// ── Solicitudes de inventario recurrentes ────────────────────────────────────
const formRecurrente = document.getElementById('formRecurrente');
if (formRecurrente) {
    let recCriterios = [];
    armarBuscadorCriterios('recCritItemBuscador', 'recCritItemDropdown', recCriterios, 'recCritChips', 'recCriteriosJson');

    armarSelectorCategoria('recCritCategoriaSelect', 'recCritCategoriaPanel', 'recCritCategoriaTodaChk', 'recCritCategoriaTodaCount', 'recCritCategoriaItemsList', 'recBtnAgregarCategoria', recCriterios, 'recCritChips', 'recCriteriosJson');

    const recBtnAgregarGenerico = document.getElementById('recBtnAgregarGenerico');
    if (recBtnAgregarGenerico) recBtnAgregarGenerico.addEventListener('click', function(){
        const sel = document.getElementById('recCritGenericoSelect');
        if(!sel.value) return;
        recCriterios.push({ tipo_criterio: 'ingrediente_generico', referencia_id: parseInt(sel.value), label: 'Ingrediente genérico: ' + sel.options[sel.selectedIndex].text });
        sel.value = '';
        renderCriterios('recCritChips', 'recCriteriosJson', recCriterios);
    });

    document.getElementById('modalRecurrente').addEventListener('show.bs.modal', function (e) {
        if (e.relatedTarget?.id !== 'btnNuevaRecurrente') return; // editar: los valores ya se cargaron antes de abrir
        document.getElementById('modalRecurrenteTitulo').textContent = 'Nueva Solicitud Recurrente';
        formRecurrente.reset();
        document.getElementById('recId').value = '';
        recCriterios = [];
        renderCriterios('recCritChips', 'recCriteriosJson', recCriterios);
    });

    document.querySelectorAll('.btn-editar-recurrente').forEach(btn => {
        btn.addEventListener('click', function () {
            const tr = this.closest('tr');
            document.getElementById('modalRecurrenteTitulo').textContent = 'Editar Solicitud Recurrente';
            document.getElementById('recId').value          = tr.dataset.id;
            document.getElementById('recDescripcion').value = tr.dataset.descripcion;
            document.getElementById('recDiaSemana').value   = tr.dataset.dia_semana;
            document.getElementById('recPrioridad').value   = tr.dataset.prioridad;
            document.getElementById('recAsignadoA').value   = tr.dataset.asignado_a || '';
            recCriterios = JSON.parse(tr.dataset.criterios || '[]');
            renderCriterios('recCritChips', 'recCriteriosJson', recCriterios);
            if (typeof bootstrap !== 'undefined') new bootstrap.Modal(document.getElementById('modalRecurrente')).show();
        });
    });

    formRecurrente.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!recCriterios.length) {
            alert('Elegí al menos una categoría o ítem para el inventario.');
            return;
        }
        const btn = document.getElementById('btnGuardarRecurrente');
        btn.disabled = true;

        const data = new FormData(this);
        const id   = data.get('id');
        const url  = id
            ? `${BASE_URL}/solicitudes/recurrentes/actualizar`
            : `${BASE_URL}/solicitudes/recurrentes/crear`;

        try {
            const res  = await fetch(url, { method: 'POST', body: data });
            const json = await res.json();
            if (json.ok) {
                location.reload();
            } else {
                alert(json.error || 'Error al guardar.');
                btn.disabled = false;
            }
        } catch {
            alert('Error de conexión.');
            btn.disabled = false;
        }
    });

    document.querySelectorAll('.btn-toggle-recurrente').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.closest('tr').dataset.id;
            const fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN);
            fd.append('id', id);
            const res  = await fetch(`${BASE_URL}/solicitudes/recurrentes/toggle-activo`, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.ok) location.reload();
            else alert(json.error || 'No se pudo actualizar.');
        });
    });

    document.querySelectorAll('.btn-eliminar-recurrente').forEach(btn => {
        btn.addEventListener('click', function () {
            const fila = this.closest('tr');
            const ejecutar = async () => {
                const fd = new FormData();
                fd.append('_csrf_token', CSRF_TOKEN);
                fd.append('id', fila.dataset.id);
                const res  = await fetch(`${BASE_URL}/solicitudes/recurrentes/eliminar`, { method: 'POST', body: fd });
                const json = await res.json();
                if (json.ok) fila.remove();
                else alert(json.error || 'No se pudo eliminar.');
            };
            const msg = '¿Eliminar esta plantilla recurrente?';
            if (typeof showConfirm === 'function') {
                showConfirm(msg, ejecutar, { label: 'Eliminar', sub: 'Si solo querés pausarla, usá "Suspender".' });
            } else if (confirm(msg + ' (si solo querés pausarla, usá "Suspender")')) {
                ejecutar();
            }
        });
    });
}
</script>

<?php else: ?>
<div class="row g-3">
    <!-- Nueva solicitud -->
    <div class="col-lg-4">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Nueva solicitud</h6>
            </div>
            <div class="card-body">
                <form id="formSolicitud">
                    <?= Csrf::field() ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo" id="solTipo" class="form-select form-select-sm">
                            <option value="producto">Producto para comprar</option>
                            <option value="materia_prima">Materia prima para comprar</option>
                            <option value="tarea">Tarea a realizar</option>
                        </select>
                    </div>

                    <div class="mb-2 position-relative" id="solItemBuscadorWrap">
                        <label class="form-label small fw-semibold">Buscar en el catálogo</label>
                        <input type="hidden" name="item_id" id="solItemId" value="">
                        <input type="text" id="solItemBuscador" class="form-control form-control-sm"
                               placeholder="Buscar producto o materia prima..." autocomplete="off">
                        <div id="solItemDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none;top:100%;left:0"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold" id="solItemNombreLabel">Qué se necesita</label>
                        <input type="text" name="item_nombre" id="solItemNombre" class="form-control form-control-sm"
                               placeholder="Nombre del producto/materia prima (si no está en el catálogo) o título de la tarea">
                    </div>

                    <?php if (canEdit('tareas_asignar')): ?>
                    <!-- Solo para quien puede asignar tareas a otros — si no, "tarea" queda como
                         una nota puntual sin destinatario, igual que antes. -->
                    <div class="mb-2 d-none" id="wrapSolAsignarA">
                        <label class="form-label small fw-semibold">Asignar a</label>
                        <select id="solModoDestino" class="form-select form-select-sm" onchange="_actualizarUiDestinoSol()">
                            <option value="usuario_especifico">Un usuario en particular</option>
                            <option value="turno_abierto">Quien tenga la caja abierta ese día</option>
                            <option value="administradores">Administradores</option>
                        </select>
                    </div>
                    <div class="mb-2 d-none" id="wrapSolTareaDestinoUsuario">
                        <label class="form-label small fw-semibold">Usuario</label>
                        <select id="solUsuarioDestinoId" class="form-select form-select-sm">
                            <option value="">— Elegir —</option>
                            <?php foreach ($usuariosActivos as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="mb-2" id="wrapSolDescripcion">
                        <label class="form-label small fw-semibold">Información adicional (opcional)</label>
                        <textarea name="descripcion" id="solDescripcion" class="form-control form-control-sm" rows="3"
                                  placeholder="Cantidad, detalle, urgencia, dónde conseguirlo..."></textarea>
                    </div>

                    <div class="mb-3" id="wrapSolPrioridad">
                        <label class="form-label small fw-semibold">Prioridad</label>
                        <select name="prioridad" class="form-select form-select-sm">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>

                    <div id="solError" class="alert alert-danger py-2 small d-none"></div>

                    <button type="submit" class="btn btn-primary btn-sm w-100" id="btnEnviarSolicitud">Enviar solicitud</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="col-lg-8">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-semibold mb-0">Solicitudes</h6>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="btn-group btn-group-sm flex-wrap">
                        <a href="<?= url('solicitudes') ?>" class="btn btn-outline-secondary <?= $filtroEstado === '' ? 'active' : '' ?>">Todas</a>
                        <a href="<?= url('solicitudes?estado=pendiente') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'pendiente' ? 'active' : '' ?>">Pendientes</a>
                        <a href="<?= url('solicitudes?estado=en_proceso') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'en_proceso' ? 'active' : '' ?>">En proceso</a>
                        <a href="<?= url('solicitudes?estado=resuelta') ?>" class="btn btn-outline-secondary <?= $filtroEstado === 'resuelta' ? 'active' : '' ?>">Resueltas</a>
                    </div>
                    <a href="<?= url('solicitudes?vista=tareas') ?>" class="btn btn-outline-primary btn-sm">Ver tareas asignadas →</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th><th>Detalle</th><th>Prioridad</th><th>Estado</th>
                                <th>Asignada a</th><th>Pedido por</th><th>Fecha</th>
                                <?php if ($puedeResolverSolicitudes): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$solicitudes): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay solicitudes.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td><span class="badge <?= $tipoLabels[$s['tipo']]['badge'] ?>"><?= $tipoLabels[$s['tipo']]['label'] ?></span></td>
                                <td>
                                    <div class="fw-semibold small"><?= e($s['item_nombre']) ?></div>
                                    <?php if ($s['descripcion']): ?><div class="small text-muted"><?= e($s['descripcion']) ?></div><?php endif; ?>
                                </td>
                                <td><span class="badge <?= $prioridadLabels[$s['prioridad']] ?>"><?= ucfirst($s['prioridad']) ?></span></td>
                                <td><span class="badge <?= $estadoLabels[$s['estado']]['badge'] ?>"><?= $estadoLabels[$s['estado']]['label'] ?></span></td>
                                <td class="small"><?= $s['tipo'] === 'tarea' ? e($s['asignado_a_nombre'] ?? 'Cualquiera') : '—' ?></td>
                                <td class="small"><?= e($s['creador_nombre']) ?></td>
                                <td class="small text-muted"><?= fechaHora($s['created_at']) ?></td>
                                <?php if ($puedeResolverSolicitudes): ?>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-editar-solicitud" title="Editar"
                                            data-id="<?= $s['id'] ?>"
                                            data-tipo="<?= e($s['tipo']) ?>"
                                            data-item_nombre="<?= e($s['item_nombre']) ?>"
                                            data-descripcion="<?= e($s['descripcion'] ?? '') ?>"
                                            data-prioridad="<?= e($s['prioridad']) ?>"
                                            data-estado="<?= e($s['estado']) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <?php if ($s['estado'] !== 'resuelta'): ?>
                                        <?php if ($s['estado'] === 'pendiente'): ?>
                                        <button type="button" class="btn btn-outline-info" onclick="cambiarEstadoSolicitud(<?= $s['id'] ?>,'en_proceso')">En proceso</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-success" onclick="cambiarEstadoSolicitud(<?= $s['id'] ?>,'resuelta')">Resuelta</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeResolverSolicitudes): ?>
<!-- Modal editar solicitud -->
<div class="modal fade" id="modalEditarSolicitud" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Editar solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarSolicitud">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" id="editSolId">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo" id="editSolTipo" class="form-select form-select-sm">
                            <option value="producto">Producto para comprar</option>
                            <option value="materia_prima">Materia prima para comprar</option>
                            <option value="tarea">Tarea a realizar</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Detalle</label>
                        <input type="text" name="item_nombre" id="editSolItemNombre" class="form-control form-control-sm" required>
                    </div>
                    <?php if (canEdit('tareas_asignar')): ?>
                    <div class="mb-2 d-none" id="wrapEditSolConvertir">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="editSolConvertirCheck" onchange="_toggleConvertirEditSol()">
                            <label class="form-check-label small fw-semibold" for="editSolConvertirCheck">Asignar a alguien</label>
                        </div>
                        <div class="alert alert-secondary py-2 small mb-2">
                            Esto la convierte en una <strong>tarea asignada</strong> de verdad: aparece en
                            "Tareas asignadas", dispara el cartel en el POS, y se puede aceptar/rechazar/
                            finalizar. Deja de verse en este listado.
                        </div>
                        <div id="wrapEditSolModoDestino" class="d-none">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Asignar a</label>
                                <select id="editSolModoDestino" class="form-select form-select-sm" onchange="_actualizarUiDestinoEditSol()">
                                    <option value="usuario_especifico">Un usuario en particular</option>
                                    <option value="turno_abierto">Quien tenga la caja abierta ese día</option>
                                    <option value="administradores">Administradores</option>
                                </select>
                            </div>
                            <div class="mb-2" id="wrapEditSolUsuarioDestino">
                                <label class="form-label small fw-semibold">Usuario</label>
                                <select id="editSolUsuarioDestinoId" class="form-select form-select-sm">
                                    <option value="">— Elegir —</option>
                                    <?php foreach ($usuariosActivos as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Información adicional (opcional)</label>
                        <textarea name="descripcion" id="editSolDescripcion" class="form-control form-control-sm" rows="3"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Prioridad</label>
                            <select name="prioridad" id="editSolPrioridad" class="form-select form-select-sm">
                                <option value="baja">Baja</option>
                                <option value="media">Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Estado</label>
                            <select name="estado" id="editSolEstado" class="form-select form-select-sm">
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En proceso</option>
                                <option value="resuelta">Resuelta</option>
                            </select>
                        </div>
                    </div>
                    <div id="editSolError" class="alert alert-danger py-2 small d-none"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEditSolicitud">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function _showModalSol(id) {
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

const wrapEditSolConvertir = document.getElementById('wrapEditSolConvertir');

function _actualizarUiTipoEditSol() {
    const esTarea = document.getElementById('editSolTipo').value === 'tarea';
    if (wrapEditSolConvertir) wrapEditSolConvertir.classList.toggle('d-none', !esTarea);
}
document.getElementById('editSolTipo').addEventListener('change', _actualizarUiTipoEditSol);

function _toggleConvertirEditSol() {
    const marcado = document.getElementById('editSolConvertirCheck').checked;
    document.getElementById('wrapEditSolModoDestino').classList.toggle('d-none', !marcado);
    if (marcado) _actualizarUiDestinoEditSol();
}
function _actualizarUiDestinoEditSol() {
    const esEspecifico = document.getElementById('editSolModoDestino').value === 'usuario_especifico';
    document.getElementById('wrapEditSolUsuarioDestino').classList.toggle('d-none', !esEspecifico);
}

document.querySelectorAll('.btn-editar-solicitud').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('editSolId').value = this.dataset.id;
        document.getElementById('editSolTipo').value = this.dataset.tipo;
        document.getElementById('editSolItemNombre').value = this.dataset.item_nombre;
        document.getElementById('editSolDescripcion').value = this.dataset.descripcion;
        document.getElementById('editSolPrioridad').value = this.dataset.prioridad;
        document.getElementById('editSolEstado').value = this.dataset.estado;
        document.getElementById('editSolError').classList.add('d-none');
        // "Asignar a alguien" siempre arranca destildado — es una decisión nueva cada vez
        // que se abre el modal, la solicitud en sí no guarda ningún destino previo.
        const chk = document.getElementById('editSolConvertirCheck');
        if (chk) { chk.checked = false; _toggleConvertirEditSol(); }
        _actualizarUiTipoEditSol();
        _showModalSol('modalEditarSolicitud');
    });
});

document.getElementById('formEditarSolicitud').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('editSolError');
    errEl.classList.add('d-none');
    const btn = document.getElementById('btnGuardarEditSolicitud');
    btn.disabled = true;

    const convertirChk = document.getElementById('editSolConvertirCheck');
    const seConvierte = convertirChk && convertirChk.checked;

    try {
        let url, fd;
        if (seConvierte) {
            url = `${BASE_URL}/solicitudes/convertir-a-tarea`;
            fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN);
            fd.append('id', document.getElementById('editSolId').value);
            fd.append('item_nombre', document.getElementById('editSolItemNombre').value);
            fd.append('descripcion', document.getElementById('editSolDescripcion').value);
            fd.append('modo_destino', document.getElementById('editSolModoDestino').value);
            fd.append('usuario_destino_id', document.getElementById('editSolUsuarioDestinoId')?.value || '');
        } else {
            url = `${BASE_URL}/solicitudes/actualizar`;
            fd = new FormData(this);
        }
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});
</script>
<?php endif; ?>

<script>
// ── Selector tipo → mostrar/ocultar buscador de catálogo, y (si corresponde) el
// bloque "Asignar a" que reemplaza a la vieja pestaña "Tareas asignadas" ───────
const solTipo = document.getElementById('solTipo');
const solItemBuscadorWrap = document.getElementById('solItemBuscadorWrap');
const solItemNombreLabel  = document.getElementById('solItemNombreLabel');
const PUEDE_ASIGNAR_TAREAS = <?= canEdit('tareas_asignar') ? 'true' : 'false' ?>;
const wrapSolAsignarA = document.getElementById('wrapSolAsignarA');

function actualizarUiTipo(){
    const esTarea = solTipo.value === 'tarea';
    solItemBuscadorWrap.style.display = esTarea ? 'none' : '';
    solItemNombreLabel.textContent = esTarea ? 'Título de la tarea' : 'Qué se necesita';
    if(esTarea){
        document.getElementById('solItemId').value = '';
        document.getElementById('solItemBuscador').value = '';
    }
    if (wrapSolAsignarA) {
        wrapSolAsignarA.classList.toggle('d-none', !esTarea);
        if (esTarea) _actualizarUiDestinoSol(); else document.getElementById('wrapSolTareaDestinoUsuario').classList.add('d-none');
    }
}
solTipo.addEventListener('change', actualizarUiTipo);
actualizarUiTipo();

function _actualizarUiDestinoSol(){
    const wrapUsuario = document.getElementById('wrapSolTareaDestinoUsuario');
    if (!wrapUsuario) return;
    const esEspecifico = document.getElementById('solModoDestino').value === 'usuario_especifico';
    wrapUsuario.classList.toggle('d-none', !esEspecifico);
}
const _solModoDestinoSel = document.getElementById('solModoDestino');
if (_solModoDestinoSel) _solModoDestinoSel.addEventListener('change', _actualizarUiDestinoSol);

// ── Buscador de productos/materias primas ───────────────────────────────────
let solTimeout;
const solItemBuscador = document.getElementById('solItemBuscador');
const solItemDropdown = document.getElementById('solItemDropdown');
const solItemId        = document.getElementById('solItemId');
const solItemNombre     = document.getElementById('solItemNombre');

solItemBuscador.addEventListener('input', function(){
    clearTimeout(solTimeout);
    solItemId.value = '';
    const q = this.value.trim();
    if(!q){ solItemDropdown.style.display='none'; return; }
    solTimeout = setTimeout(() => buscarItemsSolicitud(q), 200);
});

async function buscarItemsSolicitud(q){
    const r = await fetch(`${BASE_URL}/solicitudes/buscar-items?q=${encodeURIComponent(q)}`);
    const data = await r.json();
    solItemDropdown.innerHTML = '';
    if(!data.length){ solItemDropdown.style.display='none'; return; }
    data.forEach(it=>{
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action small py-2';
        btn.innerHTML = `<span class="fw-semibold">${esc(it.nombre)}</span> <span class="text-muted ms-2">${esc(it.codigo||'')}</span>`;
        btn.addEventListener('click', () => {
            solItemId.value = it.id;
            solItemBuscador.value = it.nombre;
            solItemNombre.value = it.nombre;
            solItemDropdown.style.display = 'none';
        });
        solItemDropdown.appendChild(btn);
    });
    solItemDropdown.style.display = 'block';
}

document.addEventListener('click', e => {
    if (!e.target.closest('#solItemBuscador') && !e.target.closest('#solItemDropdown')) {
        solItemDropdown.style.display = 'none';
    }
});

// ── Envío del formulario ─────────────────────────────────────────────────────
// Si es tipo "tarea" y este usuario puede asignar tareas (bloque "Asignar a" visible),
// se crea como tarea asignada (/tareas/crear, mismo motor de siempre: aceptar/rechazar/
// finalizar desde el cartel del POS) en vez de como una solicitud puntual sin destinatario.
document.getElementById('formSolicitud').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl = document.getElementById('solError');
    errEl.classList.add('d-none');
    const btn = document.getElementById('btnEnviarSolicitud');
    btn.disabled = true;

    const esTareaAsignada = PUEDE_ASIGNAR_TAREAS && solTipo.value === 'tarea' && wrapSolAsignarA && !wrapSolAsignarA.classList.contains('d-none');

    try {
        let url, fd;
        if (esTareaAsignada) {
            url = `${BASE_URL}/tareas/crear`;
            fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN);
            fd.append('titulo', solItemNombre.value);
            fd.append('descripcion', document.getElementById('solDescripcion').value);
            fd.append('modo_destino', document.getElementById('solModoDestino').value);
            fd.append('usuario_destino_id', document.getElementById('solUsuarioDestinoId')?.value || '');
        } else {
            url = `${BASE_URL}/solicitudes/crear`;
            fd = new FormData(this);
        }
        const r = await fetch(url, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});
</script>
<?php endif; ?>

<style>
#solItemDropdown, #critItemDropdown, #recCritItemDropdown {
    background-color: var(--bs-dropdown-bg, #fff) !important;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .5rem;
    overflow-x: hidden;
}
#solItemDropdown .list-group-item, #critItemDropdown .list-group-item, #recCritItemDropdown .list-group-item {
    background-color: var(--bs-dropdown-bg, #fff) !important;
    color: var(--bs-dropdown-color, #212529) !important;
    border-color: var(--bs-border-color, #dee2e6) !important;
}
#solItemDropdown .list-group-item *, #critItemDropdown .list-group-item *, #recCritItemDropdown .list-group-item * {
    color: inherit !important;
}
#solItemDropdown .list-group-item .text-muted, #critItemDropdown .list-group-item .text-muted, #recCritItemDropdown .list-group-item .text-muted {
    opacity: .75;
}
#solItemDropdown .list-group-item:hover, #critItemDropdown .list-group-item:hover, #recCritItemDropdown .list-group-item:hover,
#solItemDropdown .list-group-item:focus, #critItemDropdown .list-group-item:focus, #recCritItemDropdown .list-group-item:focus {
    background-color: var(--bs-dropdown-link-hover-bg, #f0f0f0) !important;
}
</style>
