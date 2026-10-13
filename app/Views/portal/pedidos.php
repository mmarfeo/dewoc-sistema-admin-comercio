<h1 class="h4 fw-bold mb-3">Mis pedidos</h1>

<?php if (!$pedidos): ?>
<div class="alert alert-info">Todavía no hiciste ningún pedido — andá al <a href="<?= url('portal') ?>">catálogo</a> para armar uno.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Fecha</th><th>Estado</th><th>Notas</th><th class="text-center">Chat</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pedidos as $p): ?>
            <tr>
                <td>#<?= $p['id'] ?></td>
                <td class="small"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                <td>
                    <?php $badges = [
                        'pendiente'  => 'bg-warning-soft text-warning',
                        'aceptado'   => 'bg-info-soft text-info',
                        'rechazado'  => 'bg-danger-soft text-danger',
                        'convertido' => 'bg-success-soft text-success',
                        'anulado'    => 'bg-danger-soft text-danger',
                    ]; ?>
                    <span class="badge <?= $badges[$p['estado']] ?? 'bg-secondary-soft text-secondary' ?>"><?= ucfirst($p['estado']) ?></span>
                </td>
                <td class="small text-muted"><?= e($p['notas_cliente'] ?? '') ?></td>
                <td class="text-center">
                    <a href="<?= url('portal/pedido/chat?id=' . $p['id']) ?>" class="btn btn-sm py-0 <?= $p['tiene_no_leido'] ? 'btn-danger' : 'btn-outline-primary' ?>" title="<?= $p['tiene_no_leido'] ? 'Tenés una respuesta nueva' : '' ?>">
                        💬<?= $p['tiene_no_leido'] ? ' 1' : '' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
