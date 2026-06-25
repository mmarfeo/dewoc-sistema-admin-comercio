<?php if (!$turno): ?>
<div class="alert alert-warning d-flex align-items-center gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <strong>No hay turno de caja abierto.</strong>
        Puede igualmente registrar ventas, pero no se registrarán en caja.
        <a href="<?= url('caja') ?>" class="alert-link ms-2">Abrir turno →</a>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 h-pos">
    <!-- Panel izquierdo: búsqueda + catálogo -->
    <div class="col-lg-5 col-xl-4">
        <div class="card border-0 h-100 d-flex flex-column">
            <div class="card-header border-0 bg-transparent">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input type="text" id="buscadorPos" class="form-control border-start-0" placeholder="Buscar producto por nombre o código...">
                </div>
            </div>
            <div class="card-body p-0 overflow-auto" id="catalogoContainer" style="max-height:calc(100vh - 200px)">
                <div id="catalogoResultados" class="list-group list-group-flush">
                    <div class="list-group-item text-muted small text-center py-4">
                        Empiece a escribir para buscar productos...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: carrito + cobro -->
    <div class="col-lg-7 col-xl-8 d-flex flex-column gap-3">
        <!-- Carrito -->
        <div class="card border-0 flex-grow-1">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0">Artículos en la venta</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarCarrito()">Limpiar</button>
            </div>
            <div class="card-body p-0 overflow-auto" style="max-height:340px">
                <table class="table table-sm mb-0" id="tblCarrito">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th><th style="width:90px">Cant.</th>
                            <th style="width:100px">Precio</th><th style="width:80px">Desc%</th>
                            <th style="width:90px" class="text-end">Subtotal</th>
                            <th style="width:35px"></th>
                        </tr>
                    </thead>
                    <tbody id="carritoBody"></tbody>
                    <tfoot>
                        <tr id="carritoVacio">
                            <td colspan="6" class="text-center text-muted py-4">El carrito está vacío</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Panel de cobro -->
        <div class="card border-0">
            <div class="card-body">
                <form id="formVenta" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="items_json" id="itemsJson" value="[]">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="row g-2">
                                <!-- Cliente -->
                                <div class="col-12 position-relative">
                                    <label class="form-label small fw-semibold">Cliente (opcional)</label>
                                    <input type="hidden" name="cliente_id" id="clienteId" value="">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="clienteBuscador" class="form-control form-control-sm"
                                               placeholder="Buscar cliente por nombre, email o CUIT..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarCliente" title="Quitar cliente" style="display:none">✕</button>
                                    </div>
                                    <div id="clienteDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none;top:100%;left:0"></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Descuento global %</label>
                                    <input type="number" name="descuento_global" id="descGlobal" class="form-control form-control-sm" min="0" max="100" step="0.5" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Método de pago</label>
                                    <select name="metodo_pago" id="metodoPago" class="form-select form-select-sm">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="debito">Débito</option>
                                        <option value="credito">Crédito</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="mercadopago">Mercado Pago</option>
                                    </select>
                                </div>
                                <!-- Vuelto (solo efectivo) -->
                                <div class="col-12" id="bloqueEfectivo" style="display:none">
                                    <label class="form-label small fw-semibold">Monto recibido $</label>
                                    <input type="number" id="montoRecibido" class="form-control form-control-sm" min="0" step="0.01" placeholder="0,00">
                                    <div class="d-flex justify-content-between small mt-1 px-1">
                                        <span class="text-muted">Vuelto:</span>
                                        <span id="vueltoTexto" class="fw-bold text-success">$ 0,00</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Mesa / Destino</label>
                                    <input type="text" name="mesa" id="mesaInput" list="listaMesas"
                                           class="form-control form-control-sm"
                                           placeholder="Mostrador, Mesa 1…" autocomplete="off">
                                    <datalist id="listaMesas">
                                        <option value="Mostrador">
                                        <option value="Mesa 1">
                                        <option value="Mesa 2">
                                        <option value="Mesa 3">
                                        <option value="Mesa 4">
                                        <option value="Mesa 5">
                                        <option value="Mesa 6">
                                        <option value="Mesa 7">
                                        <option value="Mesa 8">
                                        <option value="Para llevar">
                                        <option value="Delivery">
                                    </datalist>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Notas</label>
                                    <input type="text" name="notas" class="form-control form-control-sm" placeholder="Observaciones...">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="facturar_afip" id="facturarAfip" value="1">
                                        <label class="form-check-label small fw-semibold" for="facturarAfip">
                                            Facturar por AFIP
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="bg-light rounded p-3 h-100">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Subtotal sin IVA</span>
                                    <span id="resSubtotal" class="fw-semibold">$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">IVA</span>
                                    <span id="resIva">$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="text-muted">Descuento</span>
                                    <span id="resDescuento" class="text-danger">- $ 0,00</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">TOTAL</span>
                                    <span id="resTotal" class="fw-bold text-primary fs-3">$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="posError" class="alert alert-danger mt-3 d-none small"></div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success btn-lg flex-grow-1" id="btnCobrar">
                            ✓ Cobrar
                        </button>
                        <a href="<?= url('ventas') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal ticket post-venta -->
<div class="modal fade" id="modalTicket" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-success">✓ Venta registrada</h5>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-3">Venta <strong id="ventaIdModal">#—</strong> guardada correctamente.</p>
                <div id="afipCaeWrap" class="d-none alert py-2 mb-3 text-start small" style="font-size:11px"></div>
                <div class="d-grid gap-2">
                    <button id="btnTicket" class="btn btn-outline-primary" onclick="previewTicket()">🖨 Vista previa ticket</button>
                    <a id="btnWhatsApp" href="#" target="_blank" class="btn btn-outline-success d-none">📲 Enviar por WhatsApp</a>
                    <a id="btnComanda" href="#" target="_blank" class="btn btn-outline-secondary">📋 Imprimir comanda</a>
                    <button class="btn btn-success" onclick="nuevaVenta()">+ Nueva venta</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal vista previa de impresión -->
<div class="modal fade" id="modalPreviewTicket" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Vista previa — Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height:580px">
                <iframe id="iframeTicket" src="about:blank" style="width:100%;height:100%;border:0"></iframe>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="imprimirIframe()">
                    🖨 Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';

let carrito = [];  // [{producto_id, nombre, cantidad, precio_unitario, iva_porcentaje, descuento_pct, stock_actual}, ...]

// ── Búsqueda de productos ─────────────────────────────────────────────────────
let bTimeout;
document.getElementById('buscadorPos').addEventListener('input', function(){
    clearTimeout(bTimeout);
    const q=this.value.trim();
    if(!q){mostrarCatalogo([],'Empiece a escribir para buscar productos...');return;}
    bTimeout=setTimeout(()=>buscarProductos(q),200);
});

async function buscarProductos(q){
    const r=await fetch(`${BASE_URL}/ventas/buscar-productos?q=${encodeURIComponent(q)}`);
    const data=await r.json();
    mostrarCatalogo(data, data.length===0?'Sin resultados.':'');
}

function mostrarCatalogo(lista, emptyMsg=''){
    const cont=document.getElementById('catalogoResultados');
    cont.innerHTML='';
    if(!lista.length){
        cont.innerHTML=`<div class="list-group-item text-muted small text-center py-4">${emptyMsg}</div>`;
        return;
    }
    lista.forEach(p=>{
        const agotado=parseFloat(p.stock_actual)<=0;
        const item=document.createElement('button');
        item.type='button';
        item.className='list-group-item list-group-item-action py-2'+(agotado?' text-muted':'');
        item.innerHTML=`
            <div class="d-flex justify-content-between">
                <span class="fw-semibold">${esc(p.nombre)}</span>
                <span class="fw-bold text-primary">${formatMoneda(p.precio_venta)}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="small text-muted">${esc(p.codigo||'')} ${esc(p.categoria||'')}</span>
                <span class="small ${agotado?'text-danger':'text-success'}">Stock: ${parseFloat(p.stock_actual).toFixed(1)} ${esc(p.unidad||'')}</span>
            </div>`;
        if(!agotado) item.addEventListener('click',()=>agregarAlCarrito(p));
        cont.appendChild(item);
    });
}

// ── Carrito ───────────────────────────────────────────────────────────────────
function agregarAlCarrito(p){
    const idx=carrito.findIndex(i=>i.producto_id===p.id);
    if(idx>=0){
        carrito[idx].cantidad=Math.min(carrito[idx].cantidad+1, parseFloat(p.stock_actual));
    } else {
        carrito.push({
            producto_id:     p.id,
            nombre:          p.nombre,
            cantidad:        1,
            precio_unitario: parseFloat(p.precio_venta),
            iva_porcentaje:  parseFloat(p.iva_porcentaje)||21,
            descuento_pct:   0,
            stock_actual:    parseFloat(p.stock_actual),
        });
    }
    renderCarrito();
    document.getElementById('buscadorPos').value='';
    mostrarCatalogo([],'Empiece a escribir para buscar productos...');
    document.getElementById('buscadorPos').focus();
}

function renderCarrito(){
    const tbody=document.getElementById('carritoBody');
    const rowV=document.getElementById('carritoVacio');
    tbody.innerHTML='';
    if(!carrito.length){rowV.style.display='';actualizarTotales();sincronizar();return;}
    rowV.style.display='none';

    carrito.forEach((it,idx)=>{
        const sub=it.cantidad*it.precio_unitario*(1-it.descuento_pct/100);
        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td class="small fw-semibold">${esc(it.nombre)}</td>
            <td><input type="number" class="form-control form-control-sm" min="0.01" step="0.01" max="${it.stock_actual}"
                value="${it.cantidad}" onchange="updCarrito(${idx},'cantidad',this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" min="0" step="0.01"
                value="${it.precio_unitario}" onchange="updCarrito(${idx},'precio_unitario',this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" min="0" max="100" step="1"
                value="${it.descuento_pct}" onchange="updCarrito(${idx},'descuento_pct',this.value)"></td>
            <td class="text-end fw-semibold small">${formatMoneda(sub)}</td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="quitarDelCarrito(${idx})">✕</button></td>`;
        tbody.appendChild(tr);
    });
    actualizarTotales(); sincronizar();
}

function updCarrito(idx,campo,val){
    carrito[idx][campo]=parseFloat(val)||0;
    if(campo==='cantidad'&&carrito[idx].cantidad>carrito[idx].stock_actual)
        carrito[idx].cantidad=carrito[idx].stock_actual;
    renderCarrito();
}

function quitarDelCarrito(idx){carrito.splice(idx,1);renderCarrito();}
function limpiarCarrito(){if(confirm('¿Limpiar el carrito?')){carrito=[];renderCarrito();}}

function sincronizar(){document.getElementById('itemsJson').value=JSON.stringify(carrito);}

function actualizarTotales(){
    const descG=parseFloat(document.getElementById('descGlobal').value)||0;
    let sub=0, iva=0;
    carrito.forEach(it=>{
        const s=it.cantidad*it.precio_unitario*(1-it.descuento_pct/100);
        sub+=s; iva+=s*(it.iva_porcentaje/100);
    });
    const descMonto=((sub+iva)*descG/100);
    const total=sub+iva-descMonto;
    document.getElementById('resSubtotal').textContent=formatMoneda(sub);
    document.getElementById('resIva').textContent=formatMoneda(iva);
    document.getElementById('resDescuento').textContent='- '+formatMoneda(descMonto);
    document.getElementById('resTotal').textContent=formatMoneda(total);
}

document.getElementById('descGlobal').addEventListener('input', actualizarTotales);

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formVenta').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl=document.getElementById('posError'); errEl.classList.add('d-none');
    if(!carrito.length){errEl.textContent='Agregue productos al carrito.';errEl.classList.remove('d-none');return;}
    document.getElementById('btnCobrar').disabled=true;
    const fd=new FormData(this);
    try{
        const r=await fetch(`${BASE_URL}/ventas/guardar`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.ok){
            document.getElementById('ventaIdModal').textContent='#'+d.id;
            document.getElementById('btnTicket').dataset.url=d.ticket_url;
            document.getElementById('btnComanda').href=d.comanda_url;
            // WhatsApp share
            const waBtn = document.getElementById('btnWhatsApp');
            const waText = `🛒 *DEWOC — Ticket #${d.id}*\n*TOTAL: $${Number(d.total).toLocaleString('es-AR',{minimumFractionDigits:2})}*\n\n¡Gracias por su compra!`;
            let waPhone = (d.cliente_telefono||'').replace(/\D/g,'');
            if(waPhone.length===10) waPhone='54'+waPhone;
            waBtn.href = 'https://wa.me/'+waPhone+'?text='+encodeURIComponent(waText);
            waBtn.classList.toggle('d-none', !d.total);
            // AFIP CAE result
            const caeWrap = document.getElementById('afipCaeWrap');
            if (d.afip) {
                caeWrap.classList.remove('d-none');
                if (d.afip.ok) {
                    caeWrap.className = 'alert alert-success py-2 mb-3 text-start small';
                    caeWrap.innerHTML = `<strong>CAE obtenido</strong><br>
                        CAE: <code>${d.afip.cae}</code><br>
                        Vencimiento: ${d.afip.cae_vto}<br>
                        Comprobante: ${d.afip.tipo_cbte}/${String(d.afip.pto_vta).padStart(5,'0')}-${String(d.afip.nro_cbte).padStart(8,'0')}`;
                } else {
                    caeWrap.className = 'alert alert-warning py-2 mb-3 text-start small';
                    caeWrap.innerHTML = `<strong>AFIP: error al solicitar CAE</strong><br>${d.afip.error || d.afip.obs || 'Error desconocido'}`;
                }
            } else {
                caeWrap.classList.add('d-none');
            }
            new bootstrap.Modal(document.getElementById('modalTicket')).show();
        } else {errEl.textContent=d.error;errEl.classList.remove('d-none');}
    }catch{errEl.textContent='Error de conexión.';errEl.classList.remove('d-none');}
    finally{document.getElementById('btnCobrar').disabled=false;}
});

function nuevaVenta(){
    bootstrap.Modal.getInstance(document.getElementById('modalTicket'))?.hide();
    carrito=[]; renderCarrito();
    document.getElementById('formVenta').reset();
    document.getElementById('mesaInput').value = '';
    document.getElementById('buscadorPos').focus();
}

function previewTicket(){
    const url = document.getElementById('btnTicket').dataset.url;
    if (!url) return;
    document.getElementById('iframeTicket').src = url;
    new bootstrap.Modal(document.getElementById('modalPreviewTicket')).show();
}

function imprimirIframe(){
    const iframe = document.getElementById('iframeTicket');
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

function esc(v){const d=document.createElement('div');d.textContent=v||'';return d.innerHTML;}

// ── Selector de cliente ───────────────────────────────────────────────────────
let clienteTimeout;
const clienteBuscador = document.getElementById('clienteBuscador');
const clienteDropdown = document.getElementById('clienteDropdown');
const clienteIdInput  = document.getElementById('clienteId');
const btnLimpiarCliente = document.getElementById('btnLimpiarCliente');

clienteBuscador.addEventListener('input', function () {
    clearTimeout(clienteTimeout);
    const q = this.value.trim();
    if (!q) { clienteDropdown.style.display = 'none'; return; }
    clienteTimeout = setTimeout(() => buscarCliente(q), 250);
});

async function buscarCliente(q) {
    const r    = await fetch(`${BASE_URL}/clientes/buscar?q=${encodeURIComponent(q)}`);
    const data = await r.json();
    clienteDropdown.innerHTML = '';
    if (!data.length) {
        clienteDropdown.style.display = 'none';
        return;
    }
    data.forEach(c => {
        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'list-group-item list-group-item-action small py-2';
        btn.innerHTML = `<span class="fw-semibold">${esc(c.nombre)}</span>
            <span class="text-muted ms-2">${esc(c.email || '')} ${c.documento ? '· ' + esc(c.documento) : ''}</span>`;
        btn.addEventListener('click', () => seleccionarCliente(c));
        clienteDropdown.appendChild(btn);
    });
    clienteDropdown.style.display = 'block';
}

function seleccionarCliente(c) {
    clienteIdInput.value       = c.id;
    clienteBuscador.value      = c.nombre + (c.email ? ' — ' + c.email : '');
    clienteDropdown.style.display = 'none';
    btnLimpiarCliente.style.display = '';
}

btnLimpiarCliente.addEventListener('click', () => {
    clienteIdInput.value            = '';
    clienteBuscador.value           = '';
    btnLimpiarCliente.style.display = 'none';
    clienteBuscador.focus();
});

document.addEventListener('click', e => {
    if (!e.target.closest('#clienteBuscador') && !e.target.closest('#clienteDropdown')) {
        clienteDropdown.style.display = 'none';
    }
});

// ── Vuelto para efectivo ──────────────────────────────────────────────────────
const metodoPagoSel  = document.getElementById('metodoPago');
const bloqueEfectivo = document.getElementById('bloqueEfectivo');
const montoRecibido  = document.getElementById('montoRecibido');
const vueltoTexto    = document.getElementById('vueltoTexto');

function toggleEfectivo() {
    const esEfectivo = metodoPagoSel.value === 'efectivo';
    bloqueEfectivo.style.display = esEfectivo ? '' : 'none';
    calcularVuelto();
}

function calcularVuelto() {
    const recibido = parseFloat(montoRecibido.value) || 0;
    const totalEl  = document.getElementById('resTotal').textContent;
    const total    = parseFloat(totalEl.replace(/[^0-9,]/g, '').replace(',', '.')) || 0;
    const vuelto   = Math.max(0, recibido - total);
    vueltoTexto.textContent = formatMoneda(vuelto);
    vueltoTexto.className   = 'fw-bold ' + (recibido > 0 && recibido < total ? 'text-danger' : 'text-success');
}

metodoPagoSel.addEventListener('change', toggleEfectivo);
montoRecibido.addEventListener('input', calcularVuelto);
// Actualizar vuelto cuando cambia el total
const _origActTotales = actualizarTotales;
actualizarTotales = function () { _origActTotales(); calcularVuelto(); };

// Atajos de teclado
document.addEventListener('keydown', function(e){
    if(e.key==='F2') document.getElementById('buscadorPos').focus();
    if(e.key==='F10'&&carrito.length) document.getElementById('btnCobrar').click();
});
</script>

<style>
.h-pos { min-height: calc(100vh - 180px); }
#tblCarrito td { vertical-align: middle; }
</style>
