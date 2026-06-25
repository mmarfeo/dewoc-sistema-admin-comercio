<?php
$p           = $pedido;
$mpPublicKey = $mpPublicKey ?? '';
$mpProUrl    = $mpProUrl ?? null;
$total       = (float)$p['total_con_iva'];
?>
<div class="row justify-content-center py-4">
    <div class="col-md-7 col-lg-5">

        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Pagar pedido #<?= (int)$p['id'] ?></h1>
            <p class="text-muted mb-0">
                Total:
                <strong class="text-primary fs-5">
                    $<?= number_format($total, 2, ',', '.') ?>
                </strong>
            </p>
        </div>

        <?php if ($mpPublicKey): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Pagar con tarjeta</h6>
                <div id="cardPaymentBrick_container"></div>
                <div id="brickError" class="alert alert-danger d-none mt-3 small p-2 mb-0"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">Mercado Pago no está configurado en este sitio.</div>
        <?php endif; ?>

        <?php if ($mpProUrl): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center py-3">
                <p class="small text-muted mb-2">¿Preferís pagar desde la app de Mercado Pago?</p>
                <a href="<?= e($mpProUrl) ?>" class="btn btn-outline-primary btn-sm px-4">
                    Ir a Mercado Pago
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-2">
            <a href="<?= url('tienda') ?>" class="text-muted small">← Volver a la tienda</a>
        </div>
    </div>
</div>

<?php if ($mpPublicKey): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

const mp = new MercadoPago('<?= e($mpPublicKey) ?>', { locale: 'es-AR' });

mp.bricks().create('cardPayment', 'cardPaymentBrick_container', {
    initialization: {
        amount: <?= $total ?>,
        payer:  { email: '<?= e($p['cliente_email']) ?>' },
    },
    customization: {
        paymentMethods: { maxInstallments: 12 },
        visual: { style: { theme: 'default' } },
    },
    callbacks: {
        onReady: () => {},
        onSubmit: async (cardFormData) => {
            const errEl = document.getElementById('brickError');
            errEl.classList.add('d-none');
            try {
                const fd = new FormData();
                fd.append('_csrf_token',       CSRF_TOKEN);
                fd.append('pedido_id',         '<?= (int)$p['id'] ?>');
                fd.append('token',             cardFormData.token ?? '');
                fd.append('payment_method_id', cardFormData.payment_method_id ?? '');
                fd.append('issuer_id',         cardFormData.issuer_id ?? '');
                fd.append('installments',      cardFormData.installments ?? 1);
                fd.append('payer_email',       cardFormData.payer?.email ?? '<?= e($p['cliente_email']) ?>');

                const r = await fetch(`${BASE_URL}/tienda/pago/procesar`, {
                    method: 'POST',
                    body: fd,
                });
                const d = await r.json();
                if (d.ok) {
                    window.location.href = d.redirect;
                } else {
                    errEl.textContent = d.error || 'Error al procesar el pago.';
                    errEl.classList.remove('d-none');
                }
            } catch {
                errEl.textContent = 'Error de conexión. Intentá nuevamente.';
                errEl.classList.remove('d-none');
            }
        },
        onError: (error) => {
            console.error('MP Brick error', error);
        },
    },
});
</script>
<?php endif; ?>
