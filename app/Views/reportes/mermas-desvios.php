<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Mermas y Desvíos</h1>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        🖨 Imprimir
    </button>
</div>

<div class="alert alert-info-soft d-print-none">
    <strong>Merma</strong> es pérdida de proceso, ya conocida y configurada en cada receta (evaporación, pelado).
    <strong>Desvío</strong> es pérdida NO explicada por ninguna venta (robo, rotura, consumo interno, regalos,
    o un olvido de contar). Se calcula en cada conteo físico de inventario comparándolo contra el conteo
    anterior: <em>lo que había + lo que se compró − lo que se vendió/consumió − lo que se contó ahora</em>,
    dividido por lo consumido en el período. El primer conteo de cada ítem es solo el punto de partida.
    La pérdida del último período se aplica al costo de la materia prima en el momento del conteo, y de ahí
    a todas las recetas que la usan (un sobrante deja el desvío en 0). Si un valor te parece raro, revisá
    que el conteo esté cargado en la misma unidad que muestra el sistema.
</div>

<?php if (!count($items)): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-5">
        Todavía no hay desvíos registrados. Se generan al hacer un conteo físico de
        inventario (<a href="<?= url('stock/inventario') ?>">Stock → Inventario físico</a>); desde el
        segundo conteo de cada ítem se puede comparar contra el anterior.
    </div>
</div>
<?php else: ?>
<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="tblMermasDesvios">
                <thead class="table-light">
                    <tr>
                        <th>Ítem</th>
                        <th>Tipo</th>
                        <th>Merma % (receta)</th>
                        <th>Desvío % vigente</th>
                        <th>Último ciclo</th>
                        <th>Ciclo anterior</th>
                        <th>Tendencia</th>
                        <th class="text-center d-print-none">Historial</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it):
                        $ultimo    = $it['ultimo_ciclo'];
                        $anterior  = $it['ciclo_anterior'];
                        $tendencia = null;
                        if ($ultimo && $anterior) {
                            $tendencia = (float)$ultimo['desvio_pct'] <=> (float)$anterior['desvio_pct'];
                        }
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($it['nombre']) ?></td>
                        <td class="small text-muted"><?= $it['tipo'] === 'producto' ? 'Producto (reventa)' : 'Materia prima' ?></td>
                        <td class="small"><?= $it['merma_pct'] !== null ? number_format((float)$it['merma_pct'], 2, ',', '.') . '%' : '—' ?></td>
                        <td class="fw-semibold text-danger"><?= number_format((float)$it['desvio_pct_actual'], 2, ',', '.') ?>%</td>
                        <td class="small">
                            <?php if ($ultimo): ?>
                                <?= number_format((float)$ultimo['desvio_cantidad'], 3, ',', '.') ?> u. —
                                <?= moneda($ultimo['desvio_monto']) ?>
                                <div class="text-muted" style="font-size:.75rem">
                                    <?= date('d/m/Y', strtotime($ultimo['fecha'])) ?>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($anterior): ?>
                                <?= number_format((float)$anterior['desvio_cantidad'], 3, ',', '.') ?> u. —
                                <?= moneda($anterior['desvio_monto']) ?>
                                <div class="text-muted" style="font-size:.75rem">
                                    <?= date('d/m/Y', strtotime($anterior['fecha'])) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Sin ciclo previo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tendencia === null): ?>
                                <span class="text-muted small">—</span>
                            <?php elseif ($tendencia < 0): ?>
                                <span class="badge bg-success-soft text-success">↓ Mejora</span>
                            <?php elseif ($tendencia > 0): ?>
                                <span class="badge bg-danger-soft text-danger">↑ Empeora</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-soft text-secondary">= Igual</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center d-print-none">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0"
                                onclick="verHistorialDesvio('<?= $it['tipo'] ?>', <?= (int)$it['id'] ?>, '<?= addslashes(e($it['nombre'])) ?>')">
                                Ver (<?= (int)$it['cant_ciclos'] ?>)
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal historial completo de un ítem -->
<div class="modal fade" id="modalHistorialDesvio" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="tituloHistorialDesvio">Historial de desvío</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Compras</th>
                                <th>Consumo</th>
                                <th>Esperado</th>
                                <th>Contado</th>
                                <th>Pérdida</th>
                                <th>Desvío %</th>
                                <th>Desvío $</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody id="filasHistorialDesvio"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

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

const ESTADOS_DESVIO = {
    baseline:    ['bg-info-soft text-info',           'Punto de partida', 'Primer conteo: no hay conteo anterior contra qué comparar.'],
    valido:      ['bg-success-soft text-success',     'Aplicado',         'Este ciclo definió el desvío vigente de la materia prima.'],
    sin_consumo: ['bg-secondary-soft text-secondary','Sin consumo',      'No hubo consumo registrado en el período: no se puede medir.'],
    legacy:      ['bg-secondary-soft text-secondary','Anterior',         'Calculado con la lógica vieja: no se usa.'],
};
function badgeEstadoDesvio(estado) {
    const e = ESTADOS_DESVIO[estado] || ESTADOS_DESVIO.legacy;
    return `<span class="badge ${e[0]}" title="${e[2]}">${e[1]}</span>`;
}
function formatMoneda(v) { return '$ ' + parseFloat(v || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

async function verHistorialDesvio(tipo, id, nombre) {
    document.getElementById('tituloHistorialDesvio').textContent = 'Historial de desvío — ' + nombre;
    const tbody = document.getElementById('filasHistorialDesvio');
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Cargando...</td></tr>';
    _showModal('modalHistorialDesvio');

    try {
        const r = await fetch(`${BASE_URL}/reportes/historial-desvio-item?tipo=${tipo}&id=${id}`);
        const d = await r.json();
        tbody.innerHTML = '';
        if (!d.ok || !d.data.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Sin ciclos registrados.</td></tr>';
            return;
        }
        d.data.forEach(c => {
            const fecha = new Date(c.fecha).toLocaleDateString('es-AR');
            tbody.innerHTML += `<tr>
                <td class="small">${fecha}</td>
                <td class="small">${badgeEstadoDesvio(c.estado)}</td>
                <td class="small">${parseFloat(c.cantidad_comprada).toLocaleString('es-AR')}</td>
                <td class="small">${parseFloat(c.cantidad_explicada_ventas).toLocaleString('es-AR')}</td>
                <td class="small">${parseFloat(c.stock_teorico).toLocaleString('es-AR')}</td>
                <td class="small">${parseFloat(c.stock_real_contado).toLocaleString('es-AR')}</td>
                <td class="small">${parseFloat(c.desvio_cantidad).toLocaleString('es-AR')}</td>
                <td class="small fw-semibold">${parseFloat(c.desvio_pct).toLocaleString('es-AR')}%</td>
                <td class="small">${formatMoneda(c.desvio_monto)}</td>
                <td class="small text-muted">${esc(c.usuario_nombre || '—')}</td>
            </tr>`;
        });
    } catch {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Error al cargar el historial.</td></tr>';
    }
}
</script>
