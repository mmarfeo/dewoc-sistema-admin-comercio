<div class="text-center py-5">
    <div class="mb-4" style="font-size:5rem">✅</div>
    <h1 class="h2 fw-bold text-success mb-3">¡Pago confirmado!</h1>
    <p class="text-muted mb-4">Tu pedido fue recibido correctamente. Te enviaremos un email con los detalles.</p>
    <?php if ($payment_id): ?>
    <p class="text-muted small">ID de pago: <code><?= e($payment_id) ?></code></p>
    <?php endif; ?>
    <a href="<?= url('tienda') ?>" class="btn btn-primary btn-lg mt-3">Seguir comprando</a>
</div>
