<!-- Título -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Costos Fijos Recurrentes</h1>
        <p class="text-muted small mb-0">Alquiler, seguros, servicios, honorarios, sueldos fijos y viáticos/premios/bonos por empleado que se generan solos cada mes.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('gastos') ?>" class="btn btn-outline-secondary btn-sm">Ver Gastos</a>
        <button id="btnNuevoRecurrente" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRecurrente">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo recurrente
        </button>
    </div>
</div>

<div class="alert alert-info-soft border-0 small mb-4">
    Cada plantilla de <strong>Gasto</strong> o <strong>Viático/Premio/Bono</strong> genera solo su registro del mes en
    cuanto alguien entra al sistema a partir del día configurado (no hace falta cargarlo a mano). El monto queda
    editable en el registro generado por si hubo un aumento de precio o de consumo. Para pausar uno sin perder el
    historial (servicio dado de baja, cambio de proveedor, etc.) usá "Suspender" — no lo borres.
    <br>El <strong>sueldo fijo</strong> mensual/quincenal de un empleado ya se calcula solo cada mes (no hace falta
    una plantilla acá) y se confirma como pagado desde Empleados → Liquidar sueldo, para no marcarlo como pagado sin
    que se le haya entregado realmente al empleado.
</div>

<?php
    $mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    $vencMesAnt = $vencMes === 1 ? 12 : $vencMes - 1; $vencAnioAnt = $vencMes === 1 ? $vencAnio - 1 : $vencAnio;
    $vencMesSig = $vencMes === 12 ? 1 : $vencMes + 1; $vencAnioSig = $vencMes === 12 ? $vencAnio + 1 : $vencAnio;
    $hoyIso = date('Y-m-d');
    $totalVencimientos = array_sum(array_column($vencimientos, 'monto'));
?>
<!-- Vencimientos del mes -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="fw-semibold mb-0">Vencimientos de <?= $mesesEs[$vencMes] ?> <?= $vencAnio ?></h6>
            <p class="text-muted small mb-0 mt-1">Todo lo que hay que pagar este mes y cuándo vence, se haya generado o no todavía. Total: <strong><?= moneda($totalVencimientos) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('gastos-recurrentes?anio=' . $vencAnioAnt . '&mes=' . $vencMesAnt) ?>" class="btn btn-outline-secondary btn-sm">←</a>
            <a href="<?= url('gastos-recurrentes') ?>" class="btn btn-outline-secondary btn-sm">Este mes</a>
            <a href="<?= url('gastos-recurrentes?anio=' . $vencAnioSig . '&mes=' . $vencMesSig) ?>" class="btn btn-outline-secondary btn-sm">→</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Vence</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Categoría / Empleado</th>
                        <th class="text-end">Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vencimientos)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No hay costos fijos recurrentes activos.</td></tr>
                    <?php else: ?>
                    <?php foreach ($vencimientos as $v): ?>
                    <?php $vencido = $v['fecha_vencimiento'] < $hoyIso && !$v['ya_generado']; ?>
                    <tr class="<?= $vencido ? 'table-danger' : '' ?>">
                        <td class="fw-semibold"><?= fecha($v['fecha_vencimiento']) ?></td>
                        <td><?= e($v['descripcion']) ?></td>
                        <td>
                            <?php $badgeTipoV = ['sueldo' => 'bg-info-soft text-info', 'viatico' => 'bg-warning-soft text-warning'][$v['tipo']] ?? 'bg-primary-soft text-primary'; ?>
                            <?php $labelTipoV = ['sueldo' => 'Sueldo', 'viatico' => 'Viático/Premio/Bono'][$v['tipo']] ?? 'Gasto'; ?>
                            <span class="badge <?= $badgeTipoV ?>"><?= $labelTipoV ?></span>
                        </td>
                        <td class="small"><?= e($v['tipo'] === 'gasto' ? ($v['categoria_nombre'] ?? '—') : ($v['empleado_nombre'] ?? '—')) ?></td>
                        <td class="text-end fw-bold"><?= moneda((float)$v['monto']) ?></td>
                        <td>
                            <?php if ($v['ya_generado']): ?>
                            <span class="badge bg-success-soft text-success">Generado</span>
                            <?php elseif ($vencido): ?>
                            <span class="badge bg-danger-soft text-danger">Vencido, sin generar</span>
                            <?php else: ?>
                            <span class="badge bg-secondary-soft text-secondary">Pendiente de generar</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Categoría / Empleado</th>
                        <th>Proveedor</th>
                        <th class="text-end">Monto</th>
                        <th class="text-center">Se genera</th>
                        <th class="text-center">Vence</th>
                        <th>Último generado</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recurrentes)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Todavía no cargaste ningún costo fijo recurrente.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recurrentes as $r): ?>
                    <tr data-id="<?= $r['id'] ?>"
                        data-tipo="<?= e($r['tipo']) ?>"
                        data-descripcion="<?= e($r['descripcion']) ?>"
                        data-monto="<?= $r['monto'] ?>"
                        data-dia_generacion="<?= $r['dia_generacion'] ?>"
                        data-dia_vencimiento="<?= $r['dia_vencimiento'] ?? '' ?>"
                        data-categoria_id="<?= $r['categoria_id'] ?? '' ?>"
                        data-proveedor_id="<?= $r['proveedor_id'] ?? '' ?>"
                        data-empleado_id="<?= $r['empleado_id'] ?? '' ?>"
                        data-concepto_tipo="<?= e($r['concepto_tipo'] ?? '') ?>"
                        data-notas="<?= e($r['notas'] ?? '') ?>"
                        class="<?= $r['activo'] ? '' : 'opacity-50' ?>">
                        <td>
                            <?php $badgeTipo = ['sueldo' => 'bg-info-soft text-info', 'viatico' => 'bg-warning-soft text-warning'][$r['tipo']] ?? 'bg-primary-soft text-primary'; ?>
                            <?php $labelTipo = ['sueldo' => 'Sueldo', 'viatico' => 'Viático/Premio/Bono'][$r['tipo']] ?? 'Gasto'; ?>
                            <span class="badge <?= $badgeTipo ?>"><?= $labelTipo ?></span>
                        </td>
                        <td class="fw-semibold"><?= e($r['descripcion']) ?></td>
                        <td class="small"><?= e($r['tipo'] === 'gasto' ? ($r['categoria_nombre'] ?? '—') : ($r['empleado_nombre'] ?? '—')) ?></td>
                        <td class="small"><?= e($r['proveedor_nombre'] ?? '—') ?></td>
                        <td class="text-end fw-bold"><?= moneda((float)$r['monto']) ?></td>
                        <td class="text-center">Día <?= (int)$r['dia_generacion'] ?></td>
                        <td class="text-center">Día <?= (int)($r['dia_vencimiento'] ?: $r['dia_generacion']) ?></td>
                        <td class="text-muted"><?= e($r['ultimo_periodo_generado'] ?? 'Nunca') ?></td>
                        <td>
                            <span class="badge <?= $r['activo'] ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>">
                                <?= $r['activo'] ? 'Activo' : 'Suspendido' ?>
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal nuevo/editar -->
<div class="modal fade" id="modalRecurrente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formRecurrente">
                <input type="hidden" name="_csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" id="recId">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="modalRecurrenteTitulo">Nuevo Costo Fijo Recurrente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Tipo *</label>
                            <select name="tipo" id="recTipo" class="form-select" required>
                                <option value="gasto">Gasto (alquiler, seguro, servicio, honorarios, etc.)</option>
                                <option value="sueldo">Sueldo fijo mensual/quincenal (heredado — ya no genera el pago solo, se liquida desde Empleados)</option>
                                <option value="viatico">Viático / Premio / Bono fijo de un empleado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Descripción *</label>
                            <input type="text" name="descripcion" id="recDescripcion" class="form-control" maxlength="255" required>
                        </div>

                        <div id="recCamposGasto" class="col-12 row g-3">
                            <div class="col-12">
                                <label class="form-label small">Categoría *</label>
                                <select name="categoria_id" id="recCategoriaId" class="form-select">
                                    <option value="">— Seleccionar —</option>
                                    <?php foreach ($catPlanas as $c): ?>
                                    <?php $esHijo = !is_null($c['parent_id']); ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= !$esHijo ? 'disabled style="font-weight:600;color:#444"' : 'style="padding-left:1.5rem"' ?>>
                                        <?= $esHijo ? '  ↳ ' . e($c['nombre']) : e($c['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">
                                    Proveedor <span class="text-muted fw-normal">(opcional — si lo asignás, cada gasto generado queda "pendiente" hasta pagarlo)</span>
                                </label>
                                <select name="proveedor_id" id="recProveedorId" class="form-select">
                                    <option value="">— Sin proveedor (se considera ya pagado) —</option>
                                    <?php foreach ($proveedores as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['razon_social']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="recCamposSueldo" class="col-12 d-none">
                            <label class="form-label small">Empleado *</label>
                            <select name="empleado_id" id="recEmpleadoId" class="form-select">
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($empleados as $emp): ?>
                                <?php
                                    $sugerido = $emp['modalidad_pago'] === 'por_hora'
                                        ? (float)$emp['horas_mensuales'] * (float)$emp['valor_hora']
                                        : (float)$emp['sueldo_mensual'] * ($emp['modalidad_pago'] === 'quincenal' ? 0.5 : 1);
                                    $etiquetaModalidad = $emp['modalidad_pago'] === 'por_hora'
                                        ? $emp['horas_mensuales'] . ' hs/mes × ' . moneda((float)$emp['valor_hora'])
                                        : e($emp['modalidad_pago']);
                                ?>
                                <option value="<?= $emp['id'] ?>" data-sugerido="<?= $sugerido ?>">
                                    <?= e($emp['nombre']) ?> (<?= $etiquetaModalidad ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Empleados mensuales/quincenales, y por hora que tengan cargadas sus horas mensuales estimadas en su ficha. Las horas extra, feriados trabajados y viáticos se siguen liquidando a mano como hasta ahora.</div>
                        </div>

                        <div id="recCamposViatico" class="col-12 row g-3 d-none">
                            <div class="col-12">
                                <label class="form-label small">Empleado *</label>
                                <select name="empleado_id_viatico" id="recEmpleadoViaticoId" class="form-select">
                                    <option value="">— Seleccionar —</option>
                                    <?php foreach ($empleadosViatico as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= e($emp['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Cualquier empleado, sin importar su modalidad de pago.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Concepto</label>
                                <select name="concepto_tipo" id="recConceptoTipo" class="form-select">
                                    <?php foreach ($conceptosTipos as $ct): ?>
                                    <option value="<?= e($ct['codigo']) ?>"><?= e($ct['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-6">
                            <label class="form-label small">Monto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" id="recMonto" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Día que se genera *</label>
                            <input type="number" name="dia_generacion" id="recDia" class="form-control" min="1" max="28" value="1" required>
                            <div class="form-text">Entre 1 y 28, para que funcione en cualquier mes.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Día que vence <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="number" name="dia_vencimiento" id="recDiaVencimiento" class="form-control" min="1" max="31" placeholder="Igual al de generación">
                            <div class="form-text">Si el pago vence otro día distinto al que se genera (ej: se genera el 1, vence el 10).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notas</label>
                            <textarea name="notas" id="recNotas" class="form-control" rows="2" maxlength="255"></textarea>
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

<script>
(function () {
    const csrf = '<?= Csrf::token() ?>';
    const tipoSel       = document.getElementById('recTipo');
    const camposGasto   = document.getElementById('recCamposGasto');
    const camposSueldo  = document.getElementById('recCamposSueldo');
    const camposViatico = document.getElementById('recCamposViatico');

    function actualizarCampos() {
        const esGasto   = tipoSel.value === 'gasto';
        const esSueldo  = tipoSel.value === 'sueldo';
        const esViatico = tipoSel.value === 'viatico';
        camposGasto.classList.toggle('d-none', !esGasto);
        camposSueldo.classList.toggle('d-none', !esSueldo);
        camposViatico.classList.toggle('d-none', !esViatico);
        document.getElementById('recCategoriaId').required       = esGasto;
        document.getElementById('recEmpleadoId').required         = esSueldo;
        document.getElementById('recEmpleadoViaticoId').required  = esViatico;
    }
    tipoSel.addEventListener('change', actualizarCampos);

    // Al elegir un empleado, sugerir el monto (editable) según su sueldo/horas mensuales
    document.getElementById('recEmpleadoId').addEventListener('change', function () {
        const montoEl = document.getElementById('recMonto');
        const sugerido = this.selectedOptions[0]?.dataset.sugerido;
        if (sugerido && !montoEl.value) montoEl.value = parseFloat(sugerido).toFixed(2);
    });

    // Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
    // abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
    // (Bootstrap sí registra esa delegación de eventos al importarse).
    function _showModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(el).show();
        } else {
            let btn = document.getElementById('_trigger_' + id);
            if (!btn) {
                btn = document.createElement('button');
                btn.id = '_trigger_' + id;
                btn.type = 'button';
                btn.hidden = true;
                btn.setAttribute('data-bs-toggle', 'modal');
                btn.setAttribute('data-bs-target', '#' + id);
                document.body.appendChild(btn);
            }
            btn.click();
        }
    }

    // Opción "sueldo" heredada: ya no auto-genera nada (ver RecurrenteModel::generarPendientes),
    // el sueldo fijo se liquida desde Empleados. Se deshabilita para plantillas nuevas — solo
    // queda seleccionable al editar una plantilla vieja que ya la tenía, para no romper su edición.
    const opcionSueldo = tipoSel.querySelector('option[value="sueldo"]');

    document.getElementById('modalRecurrente').addEventListener('show.bs.modal', function (e) {
        if (e.relatedTarget?.id !== 'btnNuevoRecurrente') return; // editar: los valores ya se cargaron antes de abrir
        document.getElementById('modalRecurrenteTitulo').textContent = 'Nuevo Costo Fijo Recurrente';
        document.getElementById('formRecurrente').reset();
        document.getElementById('recId').value = '';
        opcionSueldo.disabled = true;
        tipoSel.value = 'gasto';
        actualizarCampos();
    });

    document.querySelectorAll('.btn-editar-recurrente').forEach(btn => {
        btn.addEventListener('click', function () {
            const tr = this.closest('tr');
            document.getElementById('modalRecurrenteTitulo').textContent = 'Editar Costo Fijo Recurrente';
            opcionSueldo.disabled = tr.dataset.tipo !== 'sueldo';
            document.getElementById('recId').value          = tr.dataset.id;
            tipoSel.value                                     = tr.dataset.tipo;
            document.getElementById('recDescripcion').value = tr.dataset.descripcion;
            document.getElementById('recMonto').value        = tr.dataset.monto;
            document.getElementById('recDia').value          = tr.dataset.dia_generacion;
            document.getElementById('recDiaVencimiento').value = tr.dataset.dia_vencimiento || '';
            document.getElementById('recCategoriaId').value  = tr.dataset.categoria_id || '';
            document.getElementById('recProveedorId').value  = tr.dataset.proveedor_id || '';
            document.getElementById('recEmpleadoId').value   = tr.dataset.tipo === 'sueldo' ? (tr.dataset.empleado_id || '') : '';
            document.getElementById('recEmpleadoViaticoId').value = tr.dataset.tipo === 'viatico' ? (tr.dataset.empleado_id || '') : '';
            document.getElementById('recConceptoTipo').value = tr.dataset.concepto_tipo || 'viatico';
            document.getElementById('recNotas').value         = tr.dataset.notas;
            actualizarCampos();
            _showModal('modalRecurrente');
        });
    });

    document.getElementById('formRecurrente').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnGuardarRecurrente');
        btn.disabled = true;

        const data = new FormData(this);
        const id   = data.get('id');
        const url  = id
            ? '<?= url('gastos-recurrentes/actualizar') ?>'
            : '<?= url('gastos-recurrentes/crear') ?>';

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
            fd.append('_csrf_token', csrf);
            fd.append('id', id);
            const res  = await fetch('<?= url('gastos-recurrentes/toggle-activo') ?>', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.ok) {
                location.reload();
            } else {
                alert(json.error || 'No se pudo actualizar.');
            }
        });
    });

    document.querySelectorAll('.btn-eliminar-recurrente').forEach(btn => {
        btn.addEventListener('click', function () {
            const fila = this.closest('tr');
            const ejecutar = async () => {
                const fd = new FormData();
                fd.append('_csrf_token', csrf);
                fd.append('id', fila.dataset.id);
                const res  = await fetch('<?= url('gastos-recurrentes/eliminar') ?>', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.ok) {
                    fila.remove();
                } else {
                    alert(json.error || 'No se pudo eliminar.');
                }
            };
            const msg = '¿Eliminar este costo fijo recurrente?';
            if (typeof showConfirm === 'function') {
                showConfirm(msg, ejecutar, { label: 'Eliminar', sub: 'Si solo querés pausarlo, usá "Suspender".' });
            } else {
                if (confirm(msg + ' (si solo querés pausarlo, usá "Suspender")')) ejecutar();
            }
        });
    });
})();
</script>
