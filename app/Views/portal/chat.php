<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 fw-bold mb-0">Pedido #<?= $pedido['id'] ?></h1>
    <a href="<?= url('portal/pedidos') ?>" class="btn btn-sm btn-outline-secondary">← Mis pedidos</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div id="chatMensajesPortal" class="mb-3" style="max-height:400px;overflow-y:auto">
            <?php foreach ($mensajes as $m): ?>
            <?php $propio = $m['autor_tipo'] === 'cliente'; ?>
            <div class="d-flex <?= $propio ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                <div class="p-2 rounded small <?= $propio ? 'bg-primary text-white' : 'bg-light' ?>" style="max-width:80%">
                    <?= nl2br(e($m['mensaje'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$mensajes): ?>
            <p class="text-muted small text-center py-3">Todavía no hay mensajes en este pedido.</p>
            <?php endif; ?>
        </div>
        <div class="input-group">
            <input type="text" id="chatInputPortal" class="form-control" placeholder="Escribir un mensaje...">
            <button type="button" class="btn btn-primary" onclick="enviarMensajePortal()">Enviar</button>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const PEDIDO_ID   = <?= (int) $pedido['id'] ?>;

function escChat(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

async function enviarMensajePortal() {
    const input = document.getElementById('chatInputPortal');
    const mensaje = input.value.trim();
    if (!mensaje) return;
    const fd = new FormData();
    fd.append('pedido_id', PEDIDO_ID);
    fd.append('mensaje', mensaje);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/portal/pedido/chat/enviar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            input.value = '';
            const wrap = document.getElementById('chatMensajesPortal');
            wrap.innerHTML += `<div class="d-flex justify-content-end mb-2">
                <div class="p-2 rounded small bg-primary text-white" style="max-width:80%">${escChat(mensaje)}</div>
            </div>`;
            wrap.scrollTop = wrap.scrollHeight;
        }
    } catch { /* el cliente puede reintentar a mano */ }
}
document.getElementById('chatInputPortal').addEventListener('keydown', e => { if (e.key === 'Enter') enviarMensajePortal(); });

const wrapInit = document.getElementById('chatMensajesPortal');
wrapInit.scrollTop = wrapInit.scrollHeight;
</script>
