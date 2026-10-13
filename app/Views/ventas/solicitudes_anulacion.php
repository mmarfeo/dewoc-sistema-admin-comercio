<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0 fw-bold">Solicitudes de anulación</h1>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?= url('ventas/solicitudes-anulacion') ?>" class="btn btn-sm <?= $filtroEstado === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Todas</a>
    <a href="<?= url('ventas/solicitudes-anulacion?estado=pendiente') ?>" class="btn btn-sm <?= $filtroEstado === 'pendiente' ? 'btn-primary' : 'btn-outline-secondary' ?>">Pendientes</a>
    <a href="<?= url('ventas/solicitudes-anulacion?estado=aprobada') ?>" class="btn btn-sm <?= $filtroEstado === 'aprobada' ? 'btn-primary' : 'btn-outline-secondary' ?>">Aprobadas</a>
    <a href="<?= url('ventas/solicitudes-anulacion?estado=rechazada') ?>" class="btn btn-sm <?= $filtroEstado === 'rechazada' ? 'btn-primary' : 'btn-outline-secondary' ?>">Rechazadas</a>
</div>

<?php if (!$solicitudes): ?>
<div class="text-muted small">No hay solicitudes<?= $filtroEstado ? ' ' . e($filtroEstado) . 's' : '' ?>.</div>
<?php endif; ?>

<div class="d-flex flex-column gap-2">
    <?php foreach ($solicitudes as $s): ?>
    <?php
        $badge = [
            'pendiente' => 'bg-warning text-dark',
            'aprobada'  => 'bg-success',
            'rechazada' => 'bg-secondary',
        ][$s['estado']] ?? 'bg-secondary';
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                <div>
                    <a href="<?= url('ventas/ver?id='.$s['venta_id']) ?>" class="fw-bold text-decoration-none">Venta #<?= $s['venta_id'] ?></a>
                    <span class="badge <?= $badge ?> ms-2"><?= ucfirst($s['estado']) ?></span>
                </div>
                <span class="text-muted small"><?= fechaHora($s['created_at']) ?></span>
            </div>
            <div class="small mb-1"><span class="text-muted">Solicitado por:</span> <?= e($s['solicita_nombre']) ?></div>
            <div class="small mb-1"><span class="text-muted">Motivo:</span> <strong><?= e($s['motivo']) ?></strong><?= $s['motivo_detalle'] ? ': ' . e($s['motivo_detalle']) : '' ?></div>
            <div class="small mb-2"><span class="text-muted">Total de la venta:</span> <?= moneda($s['total_con_iva']) ?></div>

            <?php if ($s['estado'] === 'pendiente' && canEdit('ventas_anulaciones')): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success flex-fill" onclick="resolverAnulacion(<?= $s['id'] ?>,'aprobar')">✅ Aprobar</button>
                <button class="btn btn-sm btn-outline-danger flex-fill" onclick="resolverAnulacion(<?= $s['id'] ?>,'rechazar')">Rechazar</button>
            </div>
            <?php elseif ($s['estado'] === 'pendiente'): ?>
            <div class="small text-muted">Pendiente de revisión — no tenés permiso para aprobar/rechazar.</div>
            <?php elseif ($s['estado'] === 'rechazada' && $s['motivo_rechazo']): ?>
            <div class="small text-muted">Motivo del rechazo: "<?= e($s['motivo_rechazo']) ?>"</div>
            <?php elseif ($s['resuelve_nombre']): ?>
            <div class="small text-muted">Resuelto por <?= e($s['resuelve_nombre']) ?><?= $s['resuelto_at'] ? ' — ' . fechaHora($s['resuelto_at']) : '' ?>.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
const BASE_URL='<?= BASE_URL ?>', CSRF_TOKEN='<?= Csrf::token() ?>';
function resolverAnulacion(solicitudId, accion){
    const msg = accion === 'aprobar'
        ? '¿Aprobar y anular esta venta?'
        : '¿Rechazar esta solicitud de anulación?';
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('solicitud_id', solicitudId);
        fd.append('accion', accion);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const r = await fetch(`${BASE_URL}/ventas/resolver-anulacion`, {method:'POST', body: fd});
            const d = await r.json();
            d.ok ? (showToast(d.mensaje), setTimeout(()=>location.reload(), 800)) : showToast(d.error, 'danger');
        } catch { showToast('Error de conexión.', 'danger'); }
    };
    if (typeof showConfirm === 'function') {
        showConfirm(msg, ejecutar, {
            label: accion === 'aprobar' ? 'Aprobar y anular' : 'Rechazar',
            sub: accion === 'aprobar' ? 'El stock se recuperará.' : '',
        });
    } else {
        if (confirm(msg + (accion === 'aprobar' ? ' El stock se recuperará.' : ''))) ejecutar();
    }
}
</script>
