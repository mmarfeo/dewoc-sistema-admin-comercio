<div class="row g-4">
    <!-- Tabla del carrito -->
    <div class="col-lg-8">
        <h1 class="h3 fw-bold mb-4">Tu carrito</h1>

        <div id="carritoVacio" class="text-center py-5 text-muted d-none">
            <div class="fs-1 mb-3">🛒</div>
            <h4>Tu carrito está vacío</h4>
            <a href="<?= url('tienda') ?>" class="btn btn-primary mt-3">Ir a comprar</a>
        </div>

        <div id="carritoTabla">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-center" style="width:120px">Cantidad</th>
                        <th class="text-end">Precio</th>
                        <th class="text-end">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="carritoBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Resumen y formulario de datos -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:80px">
            <div class="card-header bg-white fw-semibold">Resumen del pedido</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span id="resSubtotal">$0,00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">IVA</span>
                    <span id="resIva">$0,00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                    <span>Total</span>
                    <span id="resTotal">$0,00</span>
                </div>

                <!-- Datos del comprador -->
                <form id="formCheckout" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="items_json" id="itemsJson">
                    <h6 class="fw-semibold mb-3">Tus datos</h6>
                    <div class="mb-2">
                        <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre completo *" required>
                    </div>
                    <div class="mb-2">
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="Email *" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                    </div>
                    <div class="mb-3">
                        <textarea name="direccion" class="form-control form-control-sm" rows="2" placeholder="Dirección de entrega"></textarea>
                    </div>
                    <div id="checkoutError" class="alert alert-danger d-none small p-2 mb-2"></div>
                    <button type="submit" class="btn btn-success w-100" id="btnComprar">
                        💳 Confirmar y pagar
                    </button>
                    <p class="text-muted text-center small mt-2 mb-0">Pago seguro · SSL</p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

function fmt(v) { return '$' + parseFloat(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function renderCarrito() {
    const carrito = getCarrito();
    const tbody   = document.getElementById('carritoBody');
    const vacio   = document.getElementById('carritoVacio');
    const tabla   = document.getElementById('carritoTabla');

    if (!carrito.length) {
        vacio.classList.remove('d-none');
        tabla.classList.add('d-none');
        document.getElementById('btnComprar').disabled = true;
        return;
    }
    vacio.classList.add('d-none');
    tabla.classList.remove('d-none');
    document.getElementById('btnComprar').disabled = false;

    let subtotal = 0, totalIva = 0;
    tbody.innerHTML = '';

    carrito.forEach((item, idx) => {
        const sub    = item.cantidad * item.precio_unitario;
        const subIva = sub * (1 + item.iva_porcentaje / 100);
        subtotal += sub;
        totalIva += subIva - sub;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.nombre}</td>
            <td class="text-center">
                <div class="input-group input-group-sm justify-content-center" style="max-width:100px;margin:auto">
                    <button class="btn btn-outline-secondary btn-cant" data-idx="${idx}" data-delta="-1">−</button>
                    <span class="form-control text-center px-1">${item.cantidad}</span>
                    <button class="btn btn-outline-secondary btn-cant" data-idx="${idx}" data-delta="1">+</button>
                </div>
            </td>
            <td class="text-end">${fmt(item.precio_unitario)}</td>
            <td class="text-end fw-semibold">${fmt(subIva)}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-danger btn-quitar" data-idx="${idx}">✕</button>
            </td>`;
        tbody.appendChild(tr);
    });

    document.getElementById('resSubtotal').textContent = fmt(subtotal);
    document.getElementById('resIva').textContent      = fmt(totalIva);
    document.getElementById('resTotal').textContent    = fmt(subtotal + totalIva);
}

document.addEventListener('DOMContentLoaded', renderCarrito);

document.getElementById('carritoBody').addEventListener('click', function (e) {
    const c = getCarrito();
    if (e.target.classList.contains('btn-cant')) {
        const idx   = parseInt(e.target.dataset.idx);
        const delta = parseInt(e.target.dataset.delta);
        c[idx].cantidad = Math.max(1, c[idx].cantidad + delta);
        setCarrito(c);
        actualizarBadge();
        renderCarrito();
    }
    if (e.target.classList.contains('btn-quitar')) {
        c.splice(parseInt(e.target.dataset.idx), 1);
        setCarrito(c);
        actualizarBadge();
        renderCarrito();
    }
});

document.getElementById('formCheckout').addEventListener('submit', async function (e) {
    e.preventDefault();
    const carrito = getCarrito();
    if (!carrito.length) return;

    document.getElementById('itemsJson').value = JSON.stringify(carrito);
    document.getElementById('btnComprar').disabled = true;
    document.getElementById('checkoutError').classList.add('d-none');

    const fd = new FormData(this);
    try {
        const r = await fetch(`${BASE_URL}/tienda/checkout`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            setCarrito([]);
            actualizarBadge();
            window.location.href = `${BASE_URL}/tienda/pago/brick?id=${d.pedido_id}`;
        } else {
            const errEl = document.getElementById('checkoutError');
            errEl.textContent = d.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        document.getElementById('checkoutError').textContent = 'Error de conexión.';
        document.getElementById('checkoutError').classList.remove('d-none');
    } finally {
        document.getElementById('btnComprar').disabled = false;
    }
});
</script>
