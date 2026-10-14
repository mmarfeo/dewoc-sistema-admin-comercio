<?php
/**
 * Pedidos de MenuApp en el POS (ver docs/integracion-menuapp-checklist.md, etapa 9,
 * tareas 9.4 a 9.7).
 *
 * Va como partial y no dentro de ventas/pos.php, que ya pasa las 2.800 líneas: todo lo de
 * MenuApp queda en un solo archivo que se puede leer y tocar aparte.
 *
 * A diferencia del modal de los pedidos mayoristas, este NO es bloqueante: tiene
 * "Lo veo después" porque el pedido queda en la lista y el cajero puede estar atendiendo a
 * alguien en el mostrador. Lo que no se puede es perderlo de vista, así que el aviso
 * vuelve en cada sondeo mientras siga esperando acción.
 */
?>
<!-- ── Alerta de pedido nuevo ─────────────────────────────────────────────── -->
<div class="modal fade" id="modalMenuAppPedido" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    🛎 Pedido nuevo de MenuApp
                    <span class="badge bg-light text-dark ms-2" id="mapCodigo"></span>
                </h5>
                <span class="ms-auto small" id="mapVence"></span>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="small text-muted">Canal</div>
                        <div class="fw-semibold" id="mapCanal"></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Pago</div>
                        <div class="fw-semibold" id="mapPago"></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Cliente</div>
                        <div class="fw-semibold" id="mapCliente"></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Dirección</div>
                        <div class="fw-semibold" id="mapDireccion"></div>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Producto</th><th class="text-center" style="width:70px">Cant.</th></tr>
                        </thead>
                        <tbody id="mapItems"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-2">
                    <span class="text-muted small" id="mapNotas"></span>
                    <span class="fs-5 fw-bold" id="mapTotal"></span>
                </div>

                <div id="mapError" class="alert alert-danger d-none py-2 small mt-3"></div>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="mapLoVeoDespues()">Lo veo después</button>
                <button type="button" class="btn btn-outline-danger" onclick="mapAbrirRechazo()">Rechazar</button>
                <button type="button" class="btn btn-outline-primary" onclick="mapAbrirPropuesta()">Proponer algo diferente</button>
                <button type="button" class="btn btn-success" id="btnMapAceptar" onclick="mapAceptar()">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Rechazar: motivo, con los frecuentes a un toque ───────────────────── -->
<div class="modal fade" id="modalMenuAppRechazo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header"><h5 class="modal-title">Rechazar el pedido</h5></div>
            <div class="modal-body">
                <p class="small text-muted">El cliente va a leer este motivo. Si ya pagó, MenuApp le reembolsa.</p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ([
                        'No tenemos stock de uno de los productos',
                        'Estamos con demora, no llegamos a prepararlo',
                        'No hacemos envíos a esa zona',
                        'Ya cerramos por hoy',
                    ] as $motivo): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="document.getElementById('mapMotivoRechazo').value = this.textContent.trim()">
                        <?= e($motivo) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <textarea id="mapMotivoRechazo" class="form-control" rows="2" placeholder="Motivo"></textarea>
                <div id="mapRechazoError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="mapRechazar()">Rechazar pedido</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Proponer algo diferente (9.5) ─────────────────────────────────────── -->
<div class="modal fade" id="modalMenuAppPropuesta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header"><h5 class="modal-title">Proponer algo diferente</h5></div>
            <div class="modal-body">
                <p class="small text-muted">
                    Armá el pedido como se lo podés preparar. El cliente lo acepta o lo rechaza desde su
                    seguimiento, y MenuApp cobra o reintegra la diferencia.
                </p>

                <div id="mapPropuestaItems" class="mb-3"></div>

                <div class="row g-2 align-items-end mb-3">
                    <div class="col-sm-8">
                        <label class="form-label small text-muted mb-1">Sumar un producto del menú</label>
                        <select id="mapPropuestaProducto" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-sm-4 d-grid">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mapSumarProducto()">Sumar</button>
                    </div>
                </div>

                <label class="form-label small fw-semibold mb-1">Motivo para el cliente</label>
                <textarea id="mapMotivoPropuesta" class="form-control" rows="2"
                          placeholder="Ej: Nos quedamos sin Frutilla, ¿te va Frutos rojos?"></textarea>

                <div id="mapPropuestaError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="mapEnviarPropuesta()">Enviar la propuesta</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Chat del pedido: dos conversaciones (9.7) ──────────────────────────── -->
<div class="modal fade" id="modalMenuAppChat" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header pb-0 d-block">
                <h5 class="modal-title mb-2">Pedido <span id="mapChatCodigo" class="text-muted"></span></h5>
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="mapTabCliente" onclick="mapCambiarConversacion('cliente')">
                            Cliente <span class="badge bg-danger d-none" id="mapBadgeCliente"></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="mapTabRepartidor" onclick="mapCambiarConversacion('repartidor')">
                            Repartidor <span class="badge bg-danger d-none" id="mapBadgeRepartidor"></span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="modal-body">
                <div id="mapChatMensajes" style="max-height:300px;overflow-y:auto"></div>
                <div id="mapChatAviso" class="alert alert-info d-none py-2 small mt-2"></div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small text-muted mb-0">Mensaje</label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                Respuestas rápidas
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" id="mapRespuestasRapidas"></ul>
                        </div>
                    </div>
                    <textarea id="mapChatTexto" class="form-control" rows="2" maxlength="500"
                              placeholder="Escribí tu mensaje"></textarea>
                    <div class="small text-muted mt-1">Se envía firmado como "Caja · tu nombre". Podés editar la respuesta rápida antes de mandarla.</div>
                </div>
                <div id="mapChatError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="mapEnviarMensaje()">Enviar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Lista de pedidos de MenuApp (9.6) ─────────────────────────────────── -->
<div class="modal fade" id="modalMenuAppLista" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header"><h5 class="modal-title">Pedidos de MenuApp</h5></div>
            <div class="modal-body" id="mapListaCuerpo">
                <div class="text-center text-muted py-4">Cargando…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// Pedidos de MenuApp (etapa 9). Mismo patrón que los pedidos mayoristas de más arriba:
// un sondeo cada 30 segundos que se CORTA SOLO si la ruta devuelve 404 (la etapa todavía no
// está desplegada en ese servidor), para no insistir y ensuciar la consola hasta el próximo
// F5, que la vuelve a intentar.
// ─────────────────────────────────────────────────────────────────────────────
let _mapPedidoActual   = null;
let _mapConversacion   = 'cliente';
let _mapPropuesta      = [];   // [{producto_id, nombre, cantidad, opciones:[...]}]
let _mapCatalogo       = null; // se pide una sola vez, al abrir la propuesta
let _mapVistoDespues   = new Set(); // referencias que el cajero postergó en esta sesión

let _mapIntervalPedido = setInterval(mapCargarPendiente, 30000);
let _mapIntervalChat   = setInterval(mapCargarSinLeer, 30000);

/**
 * Beep con la Web Audio API en vez de un archivo de audio: no agrega un asset que haya que
 * desplegar, funciona sin conexión y no depende de que el navegador precargue nada.
 */
function mapBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        osc.start(); osc.stop(ctx.currentTime + 0.5);
    } catch { /* el navegador puede bloquear el audio hasta la primera interacción */ }
}

async function mapCargarPendiente() {
    try {
        const r = await fetch(`${BASE_URL}/menuapp-pedidos/pendiente-para-mi`);
        if (r.status === 404) { clearInterval(_mapIntervalPedido); return; }
        const d = await r.json();
        if (!d.ok || !d.pedido) return;

        // "Lo veo después" solo silencia ESE pedido y solo en esta sesión del navegador: al
        // recargar vuelve a aparecer, para que no se pierda uno postergado y olvidado.
        if (_mapVistoDespues.has(d.pedido.referencia)) return;

        const esNuevo = !_mapPedidoActual || _mapPedidoActual.referencia !== d.pedido.referencia;
        _mapPedidoActual = d.pedido;
        mapRenderPedido(d.pedido);
        if (esNuevo) mapBeep();
        _showModal('modalMenuAppPedido');
    } catch { /* silencioso: el POS tiene que seguir funcionando */ }
}

async function mapCargarSinLeer() {
    try {
        const r = await fetch(`${BASE_URL}/menuapp-pedidos/hay-mensajes-sin-leer`);
        if (r.status === 404) { clearInterval(_mapIntervalChat); return; }
        const d = await r.json();
        const el = document.getElementById('avisoMensajeMenuApp');
        if (!el) return;

        if (d.ok && d.pedido) {
            const quien = d.pedido.conversacion === 'repartidor' ? 'El repartidor' : 'El cliente';
            el.textContent = `💬 ${quien} escribió sobre el pedido ${d.pedido.codigo || d.pedido.referencia} (${d.pedido.cantidad})`;
            el.dataset.referencia   = d.pedido.referencia;
            el.dataset.conversacion = d.pedido.conversacion;
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    } catch { /* silencioso */ }
}

function mapRenderPedido(p) {
    const datos = p.datos || {};
    document.getElementById('mapCodigo').textContent    = datos.codigo || p.referencia;
    document.getElementById('mapCanal').textContent     = (datos.canal || '—') + (datos.mesa ? ` · Mesa ${datos.mesa}` : '');
    document.getElementById('mapPago').textContent      = `${datos.metodo_pago || '—'}${datos.pagado_online ? ' · ya pagado' : ' · a cobrar'}`;
    document.getElementById('mapCliente').textContent   = [datos.cliente?.nombre, datos.cliente?.telefono].filter(Boolean).join(' · ') || '—';
    document.getElementById('mapDireccion').textContent = datos.direccion || '—';
    document.getElementById('mapTotal').textContent     = formatMoneda(parseFloat(datos.total) || 0);
    document.getElementById('mapNotas').textContent     = datos.notas ? `Nota: ${datos.notas}` : '';
    document.getElementById('mapError').classList.add('d-none');

    document.getElementById('mapItems').innerHTML = (datos.items || []).map(it => {
        const gustos = (it.opciones || []).map(o => (o.cantidad > 1 ? `${o.cantidad}x ` : '') + o.nombre).join(', ');
        return `<tr>
            <td class="small">
                <div class="fw-semibold">${esc(it.nombre || '')}</div>
                ${gustos ? `<div class="text-muted">${esc(gustos)}</div>` : ''}
                ${it.notas ? `<div class="text-muted fst-italic">${esc(it.notas)}</div>` : ''}
            </td>
            <td class="text-center small">${it.cantidad}</td>
        </tr>`;
    }).join('');

    mapRenderCuentaRegresiva(p.segundos_para_vencer);
}

/**
 * Cuenta regresiva hasta vence_en. Cuando llega a cero se cierra el modal sola: el pedido ya
 * venció del lado de MenuApp y aceptarlo daría error igual, así que es peor dejar el botón
 * disponible (ver 3.7).
 */
let _mapTimerVence = null;
function mapRenderCuentaRegresiva(segundos) {
    clearInterval(_mapTimerVence);
    const el = document.getElementById('mapVence');
    if (segundos === null || segundos === undefined) { el.textContent = ''; return; }

    let restante = parseInt(segundos, 10);
    const pintar = () => {
        if (restante <= 0) {
            el.textContent = 'Vencido';
            clearInterval(_mapTimerVence);
            _hideModal('modalMenuAppPedido');
            _mapPedidoActual = null;
            return;
        }
        const m = Math.floor(restante / 60), s = restante % 60;
        el.textContent = `Vence en ${m}:${String(s).padStart(2, '0')}`;
        restante--;
    };
    pintar();
    _mapTimerVence = setInterval(pintar, 1000);
}

function mapLoVeoDespues() {
    if (_mapPedidoActual) _mapVistoDespues.add(_mapPedidoActual.referencia);
    _hideModal('modalMenuAppPedido');
}

async function mapPost(ruta, datos = {}) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('referencia', _mapPedidoActual?.referencia || '');
    for (const [k, v] of Object.entries(datos)) fd.append(k, v);

    const r = await fetch(`${BASE_URL}/menuapp-pedidos/${ruta}`, { method: 'POST', body: fd });
    return r.json();
}

function mapMostrarError(idEl, texto) {
    const el = document.getElementById(idEl);
    el.textContent = texto;
    el.classList.remove('d-none');
}

async function mapAceptar() {
    const btn = document.getElementById('btnMapAceptar');
    btn.disabled = true; btn.textContent = 'Aceptando…';
    try {
        const d = await mapPost('aceptar');
        if (d.ok) {
            if (typeof showToast === 'function') showToast(d.mensaje, 'success');
            _hideModal('modalMenuAppPedido');
            _mapPedidoActual = null;
        } else {
            // sin_stock o caja_cerrada: el pedido sigue esperando, se muestra el motivo.
            mapMostrarError('mapError', d.error || 'No se pudo aceptar el pedido.');
        }
    } catch {
        mapMostrarError('mapError', 'Error de conexión con MenuApp.');
    }
    btn.disabled = false; btn.textContent = 'Aceptar';
}

function mapAbrirRechazo() {
    document.getElementById('mapMotivoRechazo').value = '';
    document.getElementById('mapRechazoError').classList.add('d-none');
    _showModal('modalMenuAppRechazo');
}

async function mapRechazar() {
    const motivo = document.getElementById('mapMotivoRechazo').value.trim();
    if (!motivo) { mapMostrarError('mapRechazoError', 'El motivo es obligatorio: el cliente lo va a leer.'); return; }

    const d = await mapPost('rechazar', { motivo });
    if (d.ok) {
        if (typeof showToast === 'function') showToast(d.mensaje, 'success');
        _hideModal('modalMenuAppRechazo');
        _hideModal('modalMenuAppPedido');
        _mapPedidoActual = null;
    } else {
        mapMostrarError('mapRechazoError', d.error || 'No se pudo rechazar.');
    }
}

// ── 9.5 · Proponer algo diferente ──────────────────────────────────────────

async function mapAbrirPropuesta() {
    document.getElementById('mapPropuestaError').classList.add('d-none');
    document.getElementById('mapMotivoPropuesta').value = '';

    if (!_mapCatalogo) {
        const r = await fetch(`${BASE_URL}/menuapp-pedidos/catalogo`);
        const d = await r.json();
        if (!d.ok) { mapMostrarError('mapError', 'No se pudo cargar el catálogo del menú.'); return; }
        _mapCatalogo = d.productos;
        document.getElementById('mapPropuestaProducto').innerHTML =
            _mapCatalogo.map(p => `<option value="${p.id}">${esc(p.nombre)} — ${formatMoneda(p.precio)}</option>`).join('');
    }

    // Arranca con el pedido original: casi siempre se cambia una sola cosa.
    _mapPropuesta = (_mapPedidoActual?.datos?.items || []).map(it => ({
        producto_id: it.producto_id,
        nombre: it.nombre,
        cantidad: it.cantidad,
        opciones: (it.opciones || []).map(o => ({ opcion_id: o.opcion_id, nombre: o.nombre, cantidad: o.cantidad || 1 })),
        notas: it.notas || '',
    }));
    mapRenderPropuesta();
    _showModal('modalMenuAppPropuesta');
}

function mapRenderPropuesta() {
    const cont = document.getElementById('mapPropuestaItems');
    if (!_mapPropuesta.length) {
        cont.innerHTML = '<p class="text-muted small mb-0">La propuesta quedó sin productos.</p>';
        return;
    }

    cont.innerHTML = _mapPropuesta.map((it, idx) => {
        const prod   = (_mapCatalogo || []).find(p => p.id === it.producto_id);
        const grupos = (prod?.grupos || []).map(g => {
            const elegidas = it.opciones.filter(o => (g.opciones || []).some(op => op.id === o.opcion_id));
            const options  = (g.opciones || []).map(op =>
                `<option value="${op.id}" ${elegidas.some(e => e.opcion_id === op.id) ? 'selected' : ''}>${esc(op.nombre)}</option>`
            ).join('');
            return `<div class="mt-1">
                <label class="small text-muted">${esc(g.nombre)} (elegí ${g.minimo}–${g.maximo})</label>
                <select class="form-select form-select-sm" multiple size="3"
                        onchange="mapCambiarGustos(${idx}, ${g.id}, this)">${options}</select>
            </div>`;
        }).join('');

        return `<div class="border rounded p-2 mb-2">
            <div class="d-flex align-items-center gap-2">
                <div class="fw-semibold small flex-grow-1">${esc(it.nombre || '')}</div>
                <input type="number" class="form-control form-control-sm" style="width:80px" min="1" step="1"
                       value="${it.cantidad}" onchange="mapCambiarCantidad(${idx}, this.value)">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="mapQuitarItem(${idx})">✕</button>
            </div>
            ${grupos}
        </div>`;
    }).join('');
}

function mapCambiarCantidad(idx, valor) {
    const n = parseFloat(valor) || 0;
    if (n <= 0) { mapQuitarItem(idx); return; }
    _mapPropuesta[idx].cantidad = n;
}

function mapQuitarItem(idx) {
    _mapPropuesta.splice(idx, 1);
    mapRenderPropuesta();
}

/**
 * Reemplaza las opciones del grupo tocado y deja intactas las de los otros grupos: un
 * producto puede tener varios grupos a la vez (ej. gustos y salsa), y pisar todo dejaría la
 * propuesta sin las selecciones de los demás.
 */
function mapCambiarGustos(idx, grupoId, select) {
    const prod  = (_mapCatalogo || []).find(p => p.id === _mapPropuesta[idx].producto_id);
    const grupo = (prod?.grupos || []).find(g => g.id === grupoId);
    const idsDelGrupo = (grupo?.opciones || []).map(o => o.id);

    const otras = _mapPropuesta[idx].opciones.filter(o => !idsDelGrupo.includes(o.opcion_id));
    const nuevas = [...select.selectedOptions].map(opt => ({
        opcion_id: parseInt(opt.value, 10),
        nombre: opt.textContent,
        cantidad: 1,
    }));
    _mapPropuesta[idx].opciones = [...otras, ...nuevas];
}

function mapSumarProducto() {
    const id   = parseInt(document.getElementById('mapPropuestaProducto').value, 10);
    const prod = (_mapCatalogo || []).find(p => p.id === id);
    if (!prod) return;
    _mapPropuesta.push({ producto_id: prod.id, nombre: prod.nombre, cantidad: 1, opciones: [], notas: '' });
    mapRenderPropuesta();
}

async function mapEnviarPropuesta() {
    const motivo = document.getElementById('mapMotivoPropuesta').value.trim();
    if (!motivo) { mapMostrarError('mapPropuestaError', 'Escribí el motivo: es lo que va a leer el cliente.'); return; }
    if (!_mapPropuesta.length) { mapMostrarError('mapPropuestaError', 'La propuesta no puede quedar sin productos.'); return; }

    const items = _mapPropuesta.map(it => ({
        producto_id: it.producto_id,
        cantidad: it.cantidad,
        opciones: it.opciones.map(o => ({ opcion_id: o.opcion_id, cantidad: o.cantidad || 1 })),
        notas: it.notas || null,
    }));

    const d = await mapPost('proponer', { motivo, items: JSON.stringify(items) });
    if (d.ok) {
        const extra = d.total_nuevo ? ` Total nuevo: ${formatMoneda(parseFloat(d.total_nuevo))}.` : '';
        if (typeof showToast === 'function') showToast(d.mensaje + extra, 'success');
        _hideModal('modalMenuAppPropuesta');
        _hideModal('modalMenuAppPedido');
        _mapPedidoActual = null;
    } else {
        // 422 típico: un gusto quedó fuera de los mínimos/máximos del grupo. El motivo lo
        // manda MenuApp, que es quien conoce esos límites.
        mapMostrarError('mapPropuestaError', d.error || 'MenuApp rechazó la propuesta.');
    }
}

// ── 9.7 · Las dos conversaciones ───────────────────────────────────────────

function mapAbrirChat(referencia, conversacion = 'cliente') {
    _mapPedidoActual = _mapPedidoActual?.referencia === referencia
        ? _mapPedidoActual
        : { referencia, datos: {} };
    _mapConversacion = conversacion;

    document.getElementById('mapChatCodigo').textContent = referencia;
    document.getElementById('mapChatError').classList.add('d-none');
    mapCambiarConversacion(conversacion);
    mapCargarRespuestasRapidas();
    _showModal('modalMenuAppChat');
}

function mapAbrirChatDesdeAviso() {
    const el = document.getElementById('avisoMensajeMenuApp');
    if (!el || !el.dataset.referencia) return;
    mapAbrirChat(el.dataset.referencia, el.dataset.conversacion || 'cliente');
}

async function mapCambiarConversacion(conversacion) {
    _mapConversacion = conversacion;
    document.getElementById('mapTabCliente').classList.toggle('active', conversacion === 'cliente');
    document.getElementById('mapTabRepartidor').classList.toggle('active', conversacion === 'repartidor');
    document.getElementById('mapChatAviso').classList.add('d-none');

    const cont = document.getElementById('mapChatMensajes');
    cont.innerHTML = '<p class="text-muted small">Cargando…</p>';

    try {
        const url = `${BASE_URL}/menuapp-pedidos/mensajes?referencia=${encodeURIComponent(_mapPedidoActual.referencia)}&conversacion=${conversacion}`;
        const d = await (await fetch(url)).json();
        if (!d.ok) { cont.innerHTML = `<p class="text-danger small">${esc(d.error || 'No se pudo leer la conversación.')}</p>`; return; }

        const mensajes = d.mensajes || [];
        cont.innerHTML = mensajes.length
            ? mensajes.map(m => `<div class="mb-2">
                   <div class="small fw-semibold">${esc(m.de || '')}</div>
                   <div class="small">${esc(m.texto || '')}</div>
                   <div class="text-muted" style="font-size:.72rem">${esc(m.enviado_en || '')}</div>
               </div>`).join('')
            : '<p class="text-muted small">Todavía no hay mensajes.</p>';
        cont.scrollTop = cont.scrollHeight;

        // El badge del aviso se recalcula en el próximo sondeo; acá se oculta al instante
        // porque el cajero ya está leyendo.
        document.getElementById('avisoMensajeMenuApp')?.classList.add('d-none');
    } catch {
        cont.innerHTML = '<p class="text-danger small">Error de conexión con MenuApp.</p>';
    }
}

async function mapCargarRespuestasRapidas() {
    try {
        const d = await (await fetch(`${BASE_URL}/menuapp-pedidos/respuestas-rapidas`)).json();
        const lista = (_mapConversacion === 'repartidor' ? d.repartidor : d.cliente) || [];
        const ul = document.getElementById('mapRespuestasRapidas');
        ul.innerHTML = lista.length
            ? lista.map(t => `<li><button class="dropdown-item small" onclick="mapUsarRespuesta(this)">${esc(t)}</button></li>`).join('')
            : '<li><span class="dropdown-item-text small text-muted">Sin respuestas rápidas cargadas en MenuApp.</span></li>';
    } catch { /* sin la lista se escribe a mano: no bloquea el chat */ }
}

/** La respuesta rápida queda EN EL CUADRO para retocarla, no se envía sola (9.7). */
function mapUsarRespuesta(btn) {
    document.getElementById('mapChatTexto').value = btn.textContent.trim();
    document.getElementById('mapChatTexto').focus();
}

async function mapEnviarMensaje() {
    const texto = document.getElementById('mapChatTexto').value.trim();
    if (!texto) return;

    const d = await mapPost('mensaje', { conversacion: _mapConversacion, texto });
    if (d.ok) {
        document.getElementById('mapChatTexto').value = '';
        mapCambiarConversacion(_mapConversacion);
    } else {
        mapMostrarError('mapChatError', d.error || 'No se pudo enviar el mensaje.');
    }
}

// ── 9.6 · Lista de pedidos ─────────────────────────────────────────────────

async function mapAbrirLista() {
    _showModal('modalMenuAppLista');
    const cont = document.getElementById('mapListaCuerpo');
    cont.innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';

    try {
        const d = await (await fetch(`${BASE_URL}/menuapp-pedidos/lista`)).json();
        if (!d.ok) { cont.innerHTML = '<p class="text-danger small">No se pudo cargar la lista.</p>'; return; }

        const seccion = (titulo, pedidos, vacio) => `
            <h6 class="fw-semibold mt-3">${titulo} <span class="badge bg-secondary">${pedidos.length}</span></h6>
            ${pedidos.length ? pedidos.map(mapFilaLista).join('') : `<p class="text-muted small mb-0">${vacio}</p>`}`;

        cont.innerHTML =
            seccion('Esperando acción', d.grupos.esperando || [], 'Nada esperando.') +
            seccion('En curso', d.grupos.en_curso || [], 'Ninguno en curso.') +
            seccion('Terminados hoy', d.grupos.terminados || [], 'Ninguno todavía.');
    } catch {
        cont.innerHTML = '<p class="text-danger small">Error de conexión.</p>';
    }
}

function mapFilaLista(p) {
    const datos = p.datos || {};
    const sinLeer = (p.no_leidos_cliente || 0) + (p.no_leidos_repartidor || 0);
    return `<div class="border rounded p-2 mb-2 d-flex align-items-center gap-2">
        <div class="flex-grow-1 small">
            <div class="fw-semibold">
                ${esc(datos.codigo || p.referencia)} · ${esc(datos.canal || '')}
                ${p.estado_texto ? `<span class="badge bg-secondary-soft text-secondary ms-1">${esc(p.estado_texto)}</span>` : ''}
            </div>
            <div class="text-muted">
                ${esc(datos.cliente?.nombre || 'Cliente')} · ${formatMoneda(parseFloat(datos.total) || 0)}
                ${p.repartidor ? ` · 🛵 ${esc(p.repartidor)}` : ''}
            </div>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="mapAbrirChat('${esc(p.referencia)}')">
            💬 ${sinLeer > 0 ? `<span class="badge bg-danger">${sinLeer}</span>` : ''}
        </button>
    </div>`;
}
</script>
