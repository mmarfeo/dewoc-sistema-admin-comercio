<?php
$hoy = date('Y-m-d');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Analítica de Uso</h1>
        <p class="text-muted mb-0 small">
            Frecuencia de acceso a cada pantalla, agregada por día — de consulta ocasional, para
            detectar qué módulos se usan poco/nada. No es un log de seguridad (eso es
            <a href="<?= url('audit-log') ?>">Log de Auditoría</a>). Se conserva el detalle de los
            últimos 6 meses.
        </p>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= url('analitica') ?>" class="card border-0 mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Usuario</label>
                <select name="usuario_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($usuariosActivos as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filtros['usuarioId'] == $u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Rol</label>
                <select name="rol_id" class="form-select form-select-sm">
                    <option value="">— Todos —</option>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $filtros['rolId'] == $r['id'] ? 'selected' : '' ?>><?= e(ucfirst($r['nombre'])) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="<?= url('analitica') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3">
    <!-- Ranking del período filtrado -->
    <div class="col-lg-7">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Accesos por módulo — <?= fecha($filtros['desde']) ?> a <?= fecha($filtros['hasta']) ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Módulo</th>
                                <th class="text-end">Accesos</th>
                                <th class="text-end">Usuarios distintos</th>
                                <th>Último uso</th>
                                <th class="text-center" style="width:70px">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$ranking): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin accesos registrados en este período/filtro.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($ranking as $r): ?>
                            <tr>
                                <td class="fw-semibold small"><?= e($r['modulo']) ?></td>
                                <td class="text-end"><?= (int)$r['accesos'] ?></td>
                                <td class="text-end small text-muted"><?= (int)$r['usuarios_distintos'] ?></td>
                                <td class="small text-muted"><?= fecha($r['ultimo_uso']) ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0"
                                        onclick="verDetalle('<?= e($r['modulo']) ?>')" title="Ver por usuario">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
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

    <!-- Módulos sin uso reciente (histórico completo, sin acotar por el filtro de arriba) -->
    <div class="col-lg-5">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Último uso por módulo (histórico)</h6>
                <p class="text-muted small mb-0">Candidatos a revisar: los de más arriba son los que hace más tiempo que nadie usa.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Módulo</th><th>Último uso</th><th class="text-end">Accesos totales</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$sinUsoReciente): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Todavía no hay datos acumulados.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($sinUsoReciente as $m):
                                $diasSinUso = (int)floor((strtotime($hoy) - strtotime($m['ultimo_uso'])) / 86400);
                                $viejo = $diasSinUso > 30;
                            ?>
                            <tr class="<?= $viejo ? 'table-warning' : '' ?>">
                                <td class="fw-semibold small"><?= e($m['modulo']) ?></td>
                                <td class="small">
                                    <?= fecha($m['ultimo_uso']) ?>
                                    <?php if ($viejo): ?>
                                    <span class="badge bg-warning-soft text-warning ms-1"><?= $diasSinUso ?> días</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small text-muted"><?= (int)$m['accesos_totales'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal detalle por usuario -->
<div class="modal fade" id="modalDetalleModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Detalle por usuario — <span id="detalleModuloNombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Usuario</th><th>Rol</th><th class="text-end">Accesos</th><th>Último uso</th></tr>
                        </thead>
                        <tbody id="detalleModuloBody">
                            <tr><td colspan="4" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const FILTRO_DESDE = '<?= e($filtros['desde']) ?>';
const FILTRO_HASTA = '<?= e($filtros['hasta']) ?>';

function _showModal(id) {
    const el = document.getElementById(id);
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

async function verDetalle(modulo) {
    document.getElementById('detalleModuloNombre').textContent = modulo;
    const body = document.getElementById('detalleModuloBody');
    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Cargando…</td></tr>';
    _showModal('modalDetalleModulo');

    try {
        const url = `${BASE_URL}/analitica/detalle-modulo?modulo=${encodeURIComponent(modulo)}&desde=${FILTRO_DESDE}&hasta=${FILTRO_HASTA}`;
        const r = await fetch(url);
        const d = await r.json();
        if (!d.ok || !d.detalle.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Sin datos.</td></tr>';
            return;
        }
        body.innerHTML = d.detalle.map(u => `
            <tr>
                <td class="small fw-semibold">${escHtmlAnalitica(u.nombre)}</td>
                <td class="small text-muted">${escHtmlAnalitica(u.rol || '—')}</td>
                <td class="text-end small">${u.accesos}</td>
                <td class="small text-muted">${escHtmlAnalitica(u.ultimo_uso)}</td>
            </tr>
        `).join('');
    } catch {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error de conexión.</td></tr>';
    }
}

function escHtmlAnalitica(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
