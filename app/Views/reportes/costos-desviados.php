<?php $_exportQ = http_build_query(['tipo' => 'costos_desviados']); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Costos desviados</h1>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reportes/exportar?' . $_exportQ) ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-table-export me-1"></i> Exportar CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-printer me-1"></i> Imprimir
        </button>
    </div>
</div>

<div class="alert alert-warning d-print-none">
    <strong>¿Qué es esto?</strong> Materias primas e insumos cuyo <strong>costo actual</strong> (el que usan las
    recetas, el Costo Promedio Ponderado, expresado por unidad base: gramo, cc, unidad) está muy lejos del
    <strong>precio de su última compra real</strong> — el doble o más, o menos de la mitad. Ese precio de compra
    ya viene normalizado a la misma unidad base (si el ítem se compra en bolsa/balde/bidón, se divide por la
    cantidad que contiene, para no comparar "por gramo" contra "por bolsa entera"); pasá el mouse sobre el
    precio para ver el dato tal cual figura en la factura. No se marca cualquier diferencia porque los precios
    suben seguido por inflación; solo se listan los casos donde la distancia es grande, que casi siempre
    significan un costo inicial mal cargado (típicamente en una importación por planilla, o una compra vieja a
    $0) que las compras reales, al ser chicas en comparación, tardan mucho en diluir por sí solas.
</div>

<div class="d-flex align-items-center gap-2 mb-2 d-print-none">
    <button type="button" id="btnRecalcularSeleccionadas" class="btn btn-sm btn-warning" disabled>
        ↻ Recalcular seleccionadas (<span id="cantSeleccionadas">0</span>)
    </button>
    <button type="button" id="btnRecalcularTodas" class="btn btn-sm btn-outline-warning">
        ↻ Recalcular todas (<?= count($filas) ?>)
    </button>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblCostosDesviados" class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="d-print-none" style="width:34px"><input type="checkbox" id="chkTodas" class="form-check-input"></th>
                        <th>Tipo</th>
                        <th>Ítem</th>
                        <th class="text-end">Costo actual</th>
                        <th class="text-end">Precio última compra</th>
                        <th class="text-end">Desvío</th>
                        <th>Fecha última compra</th>
                        <th>Recetas afectadas</th>
                        <th class="text-center" style="width:70px">Revisar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <?php $subio = (float)$f['desviacion_pct'] > 0; ?>
                    <tr>
                        <td class="d-print-none">
                            <input type="checkbox" class="form-check-input chk-item" data-tipo="<?= e($f['tipo']) ?>" data-id="<?= (int)$f['item_id'] ?>">
                        </td>
                        <td class="small"><span class="badge bg-secondary-soft text-secondary"><?= str_replace('_', ' ', $f['tipo']) ?></span></td>
                        <td class="small fw-semibold"><?= e($f['nombre']) ?> <span class="text-muted"><?= e($f['unidad'] ?? '') ?></span></td>
                        <td class="text-end small fw-semibold"><?= moneda((float)$f['costo_actual']) ?></td>
                        <td class="text-end small">
                            <?= moneda((float)$f['precio_ultima_compra']) ?>
                            <?php if ((float)($f['cantidad_contenida'] ?? 0) > 1): ?>
                            <sup>
                                <span class="text-muted" title="Precio de la factura: <?= moneda((float)$f['precio_ultima_compra_bruto']) ?> cada <?= number_format((float)$f['cantidad_contenida'], 0, ',', '.') ?> <?= e($f['unidad'] ?? '') ?>">ⓘ</span>
                            </sup>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small fw-bold <?= $subio ? 'text-danger' : 'text-primary' ?>" data-order="<?= abs((float)$f['desviacion_pct']) ?>">
                            <?= $subio ? '▲' : '▼' ?> <?= number_format(abs((float)$f['desviacion_pct']), 0) ?>%
                        </td>
                        <td class="small">
                            <a href="<?= url('compras/editar?id=' . (int)$f['factura_id']) ?>" title="Abrir esa compra">
                                <?= fecha($f['fecha_ultima_compra']) ?>
                            </a>
                        </td>
                        <td class="small" data-order="<?= !empty($f['recetas_nombres']) ? count(explode(',', $f['recetas_nombres'])) : 0 ?>">
                            <?php if (!empty($f['recetas_nombres'])): ?>
                            <?php $listaRecetas = explode(', ', $f['recetas_nombres']); ?>
                            <span class="badge bg-warning-soft text-warning" style="cursor:help" title="<?= e($f['recetas_nombres']) ?>">
                                <?= count($listaRecetas) ?> receta<?= count($listaRecetas) === 1 ? '' : 's' ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted" title="Ninguna receta activa usa este ítem hoy — este desvío no está afectando ningún costo de venta todavía.">sin uso</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <?php if ($f['tipo'] === 'materia_prima'): ?>
                            <a href="<?= url('materias-primas/historial-precios?id=' . (int)$f['item_id']) ?>" class="btn btn-sm btn-outline-primary py-0" title="Ver historial de precios">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-warning py-0 btn-recalcular" data-tipo="<?= e($f['tipo']) ?>" data-id="<?= (int)$f['item_id'] ?>"
                                title="Recalcular el costo repasando todo el historial real de compras (mismo cálculo que usa el sistema al editar una factura)">↻</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

// Sin paginado: esta pantalla es para revisar y accionar TODO lo que aparece, no para
// hojear — con paginado, los checkboxes de las páginas no visibles se pierden porque
// DataTables saca esas filas del DOM (no las oculta nomás).
$(document).ready(() => $('#tblCostosDesviados').DataTable({
    order: [[5, 'desc']],
    paging: false,
    language: { emptyTable: 'No hay costos desviados. 🎉' },
}));

async function recalcularLote(items, btnDisparador) {
    if (!items.length) return;
    const botones = [btnDisparador, document.getElementById('btnRecalcularSeleccionadas'), document.getElementById('btnRecalcularTodas')].filter(Boolean);
    botones.forEach(b => b.disabled = true);
    try {
        const fd = new FormData();
        fd.append('items', JSON.stringify(items));
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/reportes/recalcular-costos`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            let msg = `${d.corregidos} ítem(s) recalculado(s).`;
            if (d.errores && d.errores.length) msg += ` ${d.errores.length} con error.`;
            showToast(msg, d.errores && d.errores.length ? 'warning' : 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(d.error || 'No se pudo recalcular.', 'danger');
            botones.forEach(b => b.disabled = false);
        }
    } catch {
        showToast('Error de conexión.', 'danger');
        botones.forEach(b => b.disabled = false);
    }
}

// ── Recalcular de a uno (botón por fila) ────────────────────────────────────────
document.querySelectorAll('.btn-recalcular').forEach(btn => {
    btn.addEventListener('click', () => recalcularLote([{ tipo: btn.dataset.tipo, id: parseInt(btn.dataset.id) }], btn));
});

// ── Selección con checkboxes ────────────────────────────────────────────────────
function itemsSeleccionados() {
    return Array.from(document.querySelectorAll('.chk-item:checked'))
        .map(chk => ({ tipo: chk.dataset.tipo, id: parseInt(chk.dataset.id) }));
}
function actualizarBotonSeleccionadas() {
    const n = itemsSeleccionados().length;
    document.getElementById('cantSeleccionadas').textContent = n;
    document.getElementById('btnRecalcularSeleccionadas').disabled = n === 0;
}
document.querySelectorAll('.chk-item').forEach(chk => chk.addEventListener('change', actualizarBotonSeleccionadas));
document.getElementById('chkTodas').addEventListener('change', function () {
    document.querySelectorAll('.chk-item').forEach(chk => chk.checked = this.checked);
    actualizarBotonSeleccionadas();
});

document.getElementById('btnRecalcularSeleccionadas').addEventListener('click', function () {
    recalcularLote(itemsSeleccionados(), this);
});

// ── Recalcular todas las que aparecen en el reporte, estén tildadas o no ────────
document.getElementById('btnRecalcularTodas').addEventListener('click', function () {
    const todas = Array.from(document.querySelectorAll('.chk-item'))
        .map(chk => ({ tipo: chk.dataset.tipo, id: parseInt(chk.dataset.id) }));
    if (!confirm(`¿Recalcular los ${todas.length} ítems de la lista?`)) return;
    recalcularLote(todas, this);
});
</script>
