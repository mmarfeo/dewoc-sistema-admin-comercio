<?php $t = $turnoActivo; ?>

<?php if (!empty($cobrosPendientes)): ?>
<!--
  Cobros pendientes (ver tarea 4.4 de docs/integracion-menuapp-checklist.md): ventas que se
  entregaron y se cobraron en mano cuando no había ningún turno abierto. La plata está en el
  negocio pero todavía no entró a ninguna caja, así que se imputan a mano desde acá.
-->
<div class="alert alert-warning">
    <div class="fw-semibold mb-2">
        Cobros pendientes de imputar (<?= count($cobrosPendientes) ?>)
    </div>
    <p class="small mb-2">
        Se cobraron en mano sin un turno de caja abierto.
        <?= $t ? 'Imputalos a este turno para que el arqueo cierre.' : 'Abrí un turno para poder imputarlos.' ?>
    </p>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr class="small text-muted">
                    <th>Venta</th><th>Fecha</th><th>Origen</th><th class="text-end">Monto</th><th>Medio</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cobrosPendientes as $cp): ?>
                <tr class="small">
                    <td>
                        <a href="<?= url('ventas/ver?id=' . (int)$cp['id']) ?>">#<?= (int)$cp['id'] ?></a>
                        <?php if (!empty($cp['referencia_externa'])): ?>
                        <span class="text-muted">· <?= e($cp['referencia_externa']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= fechaHora($cp['created_at']) ?></td>
                    <td><?= $cp['origen'] === 'menuapp' ? '<span class="badge bg-info-soft text-info">MenuApp</span>' : e(ucfirst((string)$cp['origen'])) ?></td>
                    <td class="text-end fw-semibold"><?= moneda((float)$cp['total_con_iva']) ?></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', (string)$cp['metodo_pago']))) ?></td>
                    <td class="text-end">
                        <?php if ($t && canEdit('caja')): ?>
                        <button class="btn btn-sm btn-outline-primary py-0" onclick="imputarCobroPendiente(<?= (int)$cp['id'] ?>)">
                            Imputar a este turno
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function imputarCobroPendiente(ventaId) {
    if (!confirm('¿Imputar el cobro de la venta #' + ventaId + ' a este turno de caja?')) return;
    const fd = new FormData();
    fd.append('_csrf_token', '<?= Csrf::token() ?>');
    fd.append('venta_id', ventaId);
    const r = await fetch('<?= url('caja/imputar-cobro-pendiente') ?>', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo imputar el cobro.');
}
</script>
<?php endif; ?>
<?php if (!$t): ?>
<!-- Sin turno: abrir -->
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold text-center mb-4">Abrir turno de caja</h4>
                <form id="formAbrir">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Monto inicial ($)</label>
                        <input type="number" name="monto_inicial" class="form-control form-control-lg" min="0" step="0.01" value="0">
                        <div class="form-text">Efectivo disponible al inicio del turno.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="abrirError" class="alert alert-danger d-none small mb-3"></div>
                    <button type="submit" class="btn btn-success btn-lg w-100" id="btnAbrir">Abrir turno</button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= url('caja/historial') ?>" class="small text-muted">Ver historial de turnos →</a>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
document.getElementById('formAbrir').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('btnAbrir').disabled=true;
    document.getElementById('abrirError').classList.add('d-none');
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/abrir`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),800)):(document.getElementById('abrirError').textContent=d.error,document.getElementById('abrirError').classList.remove('d-none'));
    }catch{document.getElementById('abrirError').textContent='Error de conexión.';document.getElementById('abrirError').classList.remove('d-none');}
    finally{document.getElementById('btnAbrir').disabled=false;}
});
</script>

<?php else: ?>
<!-- Con turno abierto -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Caja — Turno #<?= $t['numero'] ?></h1>
        <span class="badge bg-success">Abierto desde <?= fechaHora($t['apertura_at']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCerrar">Cerrar turno</button>
        <a href="<?= url('caja/historial') ?>" class="btn btn-outline-secondary btn-sm">Historial</a>
    </div>
</div>

<!-- KPIs del turno -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['label'=>'Monto inicial', 'valor'=> moneda($t['monto_inicial']), 'icon'=>'💵', 'cls'=>'bg-secondary-soft text-secondary'],
        ['label'=>'Total ingresos', 'valor'=> moneda($t['total_ingresos']), 'icon'=>'↑', 'cls'=>'bg-success-soft text-success'],
        ['label'=>'Total egresos',  'valor'=> moneda($t['total_egresos']),  'icon'=>'↓', 'cls'=>'bg-danger-soft text-danger'],
    ];
    if (!$cierraCiego) {
        $kpis[] = ['label'=>'Efectivo esperado', 'valor'=> moneda($t['saldo_calculado']), 'icon'=>'💰', 'cls'=>'bg-primary-soft text-primary'];
    } else {
        $kpis[] = ['label'=>'Efectivo esperado', 'valor'=> '🔒 se revela al cerrar', 'icon'=>'💰', 'cls'=>'bg-primary-soft text-primary'];
    }
    ?>
    <?php foreach ($kpis as $k): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1"><?= $k['label'] ?></p>
                        <h4 class="fw-bold mb-0"><?= $k['valor'] ?></h4>
                    </div>
                    <span class="badge <?= $k['cls'] ?> p-2 fs-5"><?= $k['icon'] ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Registrar movimiento -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Registrar movimiento manual</h6></div>
    <div class="card-body">
        <form id="formMov" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Monto ($)</label>
                <input type="number" name="monto" class="form-control form-control-sm" min="0.01" step="0.01">
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Descripción</label>
                <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Pago proveedor, fondo chico, etc.">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Registrar</button>
            </div>
            <div id="movError" class="col-12 d-none"><div class="alert alert-danger py-1 small mb-0"></div></div>
        </form>
    </div>
</div>

<!-- Movimientos del turno -->
<div class="card border-0">
    <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Movimientos del turno</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblMovimientos" class="table table-hover mb-0 table-sm">
                <thead class="table-light">
                    <tr><th>Hora</th><th>Tipo</th><th>Descripción</th><th class="text-end">Monto</th><th>Usuario</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($t['movimientos'] as $m): $esAnulada = $m['venta_estado'] === 'anulada'; ?>
                    <tr class="<?= $esAnulada ? 'opacity-50' : '' ?>">
                        <td class="small text-muted"><?= date('H:i', strtotime($m['created_at'])) ?></td>
                        <td><span class="badge <?= $m['tipo']==='ingreso'?'bg-success-soft text-success':'bg-danger-soft text-danger' ?>"><?= ucfirst($m['tipo']) ?></span></td>
                        <td class="small">
                            <?= e($m['descripcion'] ?? '—') ?>
                            <?php if ($esAnulada): ?>
                            <span class="badge bg-secondary-soft text-secondary ms-1" title="Venta anulada — no cuenta en el efectivo esperado">Venta anulada</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold <?= $esAnulada ? 'text-muted text-decoration-line-through' : ($m['tipo']==='ingreso'?'text-success':'text-danger') ?>"><?= ($m['tipo']==='ingreso'?'+':'-') . moneda($m['monto']) ?></td>
                        <td class="small text-muted"><?= e($m['usuario_nombre'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal cierre de turno -->
<div class="modal fade" id="modalCerrar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Cerrar turno #<?= $t['numero'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCerrar">
                <?= Csrf::field() ?>
                <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
                <div class="modal-body">
                    <?php if (!$cierraCiego): ?>
                    <div class="alert alert-info small">
                        Efectivo esperado por sistema: <strong><?= moneda($t['saldo_calculado']) ?></strong> ·
                        Mercado Pago esperado: <strong><?= moneda($t['esperado']['mercadopago']) ?></strong>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning small">
                        🔒 Cierre a ciegas: contá el efectivo físico de la caja y verificá el total acreditado en Mercado Pago antes de declarar. El sistema te va a decir si hay diferencia recién después de confirmar.
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Efectivo declarado ($)</label>
                        <input type="number" name="efectivo_declarado" class="form-control" min="0" step="0.01"
                               value="<?= !$cierraCiego ? $t['saldo_calculado'] : '' ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mercado Pago declarado ($)</label>
                        <input type="number" name="mp_declarado" class="form-control" min="0" step="0.01"
                               value="<?= !$cierraCiego ? $t['esperado']['mercadopago'] : '' ?>" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="cerrarError" class="alert alert-danger mt-3 d-none small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnCerrar">Cerrar turno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal resultado del cierre (también se usa para la revisión previa, antes de comprometer el cierre) -->
<div class="modal fade" id="modalResultado" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="resultadoTitulo">Resultado del cierre</h5>
            </div>
            <div class="modal-body" id="resultadoBody"></div>
            <!-- Hay diferencia y todavía NO se cerró: el turno sigue abierto, se puede cancelar sin costo -->
            <div class="modal-footer border-0" id="resultadoFooterPreview">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancelar y revisar ventas</button>
                <button type="button" class="btn btn-outline-danger" id="btnConfirmarDeTodosModos">Cerrar de todas formas</button>
            </div>
            <!-- El turno ya quedó cerrado -->
            <div class="modal-footer border-0 d-none" id="resultadoFooterFinal">
                <a href="#" id="btnTicket" class="btn btn-outline-primary">🖨 Imprimir ticket</a>
                <button type="button" class="btn btn-primary" id="btnResultadoOk" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
$(document).ready(()=>$('#tblMovimientos').DataTable({order:[[0,'asc']],pageLength:25,language:{emptyTable:'Sin movimientos registrados'}}));

// Imprime el ticket en un iframe oculto en vez de abrir una pestaña nueva — caja/ticket_cierre.php
// ya dispara window.print() solo en su propio onload, así que alcanza con cargarlo ahí adentro.
function imprimirTicket(url) {
    if (!url) return;
    document.getElementById('_ticketPrintFrame')?.remove();
    const iframe = document.createElement('iframe');
    iframe.id = '_ticketPrintFrame';
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    iframe.src = url;
    document.body.appendChild(iframe);
    setTimeout(() => iframe.remove(), 60000);
}
document.getElementById('btnTicket').addEventListener('click', function (e) {
    e.preventDefault();
    imprimirTicket(this.dataset.url);
});

document.getElementById('formMov').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/movimiento`,{method:'POST',body:fd});
        const d=await r.json();
        d.ok?(showToast(d.mensaje),setTimeout(()=>location.reload(),600)):(showToast(d.error,'danger'));
    }catch{showToast('Error de conexión.','danger');}
});

function fmtMoneda(v){ return '$ ' + Number(v).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
const confianzaBadge = {alta:'bg-danger', media:'bg-warning text-dark', baja:'bg-secondary'};

function renderResultado(resultado, diagnostico){
    const filas = [
        {medio:'Efectivo', ok:resultado.ok_efectivo, dif:resultado.diferencia_efectivo},
        {medio:'Mercado Pago', ok:resultado.ok_mp, dif:resultado.diferencia_mp},
    ];
    let html = '<div class="mb-3">';
    filas.forEach(f=>{
        if (f.ok) {
            html += `<div class="alert alert-success py-2 small mb-2">✅ ${f.medio}: sin diferencia.</div>`;
        } else {
            const tipo = f.dif < 0 ? 'Faltante' : 'Sobrante';
            html += `<div class="alert alert-danger py-2 small mb-2">⚠️ ${f.medio}: ${tipo} de ${fmtMoneda(Math.abs(f.dif))}.</div>`;
        }
    });
    html += '</div>';

    if (diagnostico && diagnostico.length){
        html += '<h6 class="fw-semibold small">Posibles causas detectadas</h6><ul class="list-unstyled small mb-0">';
        diagnostico.forEach(h=>{
            const badge = confianzaBadge[h.confianza] || 'bg-secondary';
            html += `<li class="mb-2 pb-2 border-bottom"><span class="badge ${badge} me-1">${h.confianza}</span> ${h.mensaje}`;
            if (h.ventas_relacionadas && h.ventas_relacionadas.length) {
                html += '<div class="mt-2 d-flex flex-wrap gap-1">';
                h.ventas_relacionadas.forEach(v => {
                    const monto = v.total ? ' — ' + fmtMoneda(v.total) : '';
                    html += `<a href="${BASE_URL}/ventas/ver?id=${v.id}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2">Ver venta #${v.id}${monto}</a>`;
                });
                html += '</div>';
            }
            html += '</li>';
        });
        html += '</ul>';
    } else if (!resultado.ok_efectivo || !resultado.ok_mp) {
        html += '<div class="small text-muted">No se detectó una causa automática. Revisar conteo manual, vueltos mal dados o billetes falsos/rotos.</div>';
    }
    return html;
}

// Antes de cerrar de verdad, se previsualiza (sin escribir nada) para poder mostrar la
// diferencia y dejar cancelar — el turno recién se cierra si el usuario confirma.
document.getElementById('formCerrar').addEventListener('submit', async function(e){
    e.preventDefault();
    document.getElementById('btnCerrar').disabled=true;
    document.getElementById('cerrarError').classList.add('d-none');
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/caja/prever-cierre`,{method:'POST',body:fd});
        const d=await r.json();
        if (!d.ok) {
            document.getElementById('cerrarError').textContent=d.error;
            document.getElementById('cerrarError').classList.remove('d-none');
            document.getElementById('btnCerrar').disabled=false;
            return;
        }
        const pendientes = d.menuapp_pendientes || 0;

        if (!d.tiene_diferencia && !pendientes) {
            // Sin diferencia ni pedidos de MenuApp sin cobrar: cerrar directo, sin fricción
            // extra (mismo comportamiento de siempre).
            await confirmarCierre(fd);
            return;
        }
        // Hay algo para avisar: el turno sigue ABIERTO acá — el usuario puede cancelar e ir
        // a revisar, o cerrar igual. Ninguno de los dos avisos bloquea el cierre.
        $('#modalCerrar').modal('hide');
        document.getElementById('resultadoTitulo').textContent = d.tiene_diferencia
            ? '⚠️ Hay una diferencia — revisar antes de cerrar'
            : '⚠️ Revisar antes de cerrar';
        document.getElementById('resultadoBody').innerHTML =
            avisoMenuAppPendientes(pendientes) +
            (d.tiene_diferencia ? renderResultado(d.resultado, d.diagnostico) : '');
        document.getElementById('resultadoFooterPreview').classList.remove('d-none');
        document.getElementById('resultadoFooterFinal').classList.add('d-none');
        document.getElementById('btnConfirmarDeTodosModos').onclick = () => confirmarCierre(fd);
        document.getElementById('btnCerrar').disabled=false;
        $('#modalResultado').modal('show');
    }catch{
        document.getElementById('cerrarError').textContent='Error de conexión.';
        document.getElementById('cerrarError').classList.remove('d-none');
        document.getElementById('btnCerrar').disabled=false;
    }
});

// Aviso de pedidos de MenuApp entregados y cobrados en mano que todavía no se imputaron a
// la caja (tarea 6.5 de docs/integracion-menuapp-checklist.md). No impide cerrar: si el
// turno se cierra, quedan listados en Caja para imputarlos al abrir el siguiente.
function avisoMenuAppPendientes(n){
    if (!n) return '';
    const plural = n === 1 ? 'pedido' : 'pedidos';
    return `<div class="alert alert-warning small">
        Tenés <strong>${n}</strong> ${plural} de MenuApp sin cobrar.
        Si cerrás, ${n === 1 ? 'queda' : 'quedan'} como cobro pendiente para el próximo turno.
    </div>`;
}

async function confirmarCierre(fd){
    document.getElementById('btnConfirmarDeTodosModos').disabled = true;
    try{
        const r=await fetch(`${BASE_URL}/caja/cerrar`,{method:'POST',body:fd});
        const d=await r.json();
        if (d.ok) {
            $('#modalCerrar').modal('hide');
            document.getElementById('resultadoTitulo').textContent = 'Resultado del cierre';
            document.getElementById('resultadoBody').innerHTML = renderResultado(d.resultado, d.diagnostico);
            document.getElementById('resultadoFooterPreview').classList.add('d-none');
            document.getElementById('resultadoFooterFinal').classList.remove('d-none');
            document.getElementById('btnTicket').dataset.url = d.ticket_url;
            document.getElementById('btnResultadoOk').onclick = () => location.reload();
            $('#modalResultado').modal('show');
        } else {
            $('#modalResultado').modal('hide');
            document.getElementById('cerrarError').textContent=d.error;
            document.getElementById('cerrarError').classList.remove('d-none');
            $('#modalCerrar').modal('show');
        }
    }catch{
        showToast('Error de conexión al cerrar el turno.', 'danger');
    }finally{
        document.getElementById('btnConfirmarDeTodosModos').disabled = false;
        document.getElementById('btnCerrar').disabled=false;
    }
}
</script>
<?php endif; ?>
