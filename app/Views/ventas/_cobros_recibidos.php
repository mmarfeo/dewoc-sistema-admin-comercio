<?php
/**
 * Aviso de cobros recibidos por Dewoc Cobros en el POS (ver docs/dewoc-cobros-checklist.md,
 * etapa 3). Solo se incluye con la licencia habilitada (3.0).
 *
 * Decisión del comercio (2026-11-02, cambia C6): el aviso se muestra unos segundos, los
 * suficientes para que el cajero lo vea, y se va solo. También se cierra con la ✕. Si el
 * mouse está encima, espera a que salga.
 *
 * No interrumpe la venta (C5): no es un modal, no toma el foco ni tiene atajos de teclado,
 * flota arriba al centro, sobre el header (pedido del comercio, 2026-11-02) y solo las
 * tarjetas reciben clics; el resto de la capa deja pasar los clics al POS.
 *
 * Usa esc() (definida en ventas/pos.php) y formatMoneda() (assets/js/app.js).
 *
 * Además, el pop-up de TRANSFERENCIAS SIN ASIGNAR (revisión del 2026-11-02 (4)): las del turno
 * abierto que no se validaron solas (monto distinto, pagó una hora después, dejó propina…).
 * Se abre solo cada una hora mientras haya alguna, y cuando el cajero quiera desde el botón
 * "💰 Transferencias sin asignar". Al cerrar el turno, el cajero que abre el siguiente ya no
 * las ve: quedan en Ventas → Transferencias recibidas.
 */
$turnoIdCobros = (int)($turno['id'] ?? 0);
?>
<div id="cobrosAvisos" aria-live="polite"></div>

<?php if ($turnoIdCobros): ?>
<div class="modal fade" id="modalTransfPendientes" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title fw-semibold mb-0">💰 Transferencias sin asignar de este turno</h6>
                    <div class="small text-muted">Elegí a qué venta corresponde cada una. Si no es de una venta (una seña, una devolución), marcala como "No corresponde".</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr><th>Hora</th><th class="text-end">Monto</th><th>De</th><th style="min-width:280px">Venta</th><th></th></tr>
                        </thead>
                        <tbody id="tbTransfPendientes"></tbody>
                    </table>
                </div>
                <div id="errTransfPendientes" class="alert alert-danger small m-3 d-none"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted">Mientras haya transferencias sin asignar, esta ventana vuelve a aparecer cada una hora.</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Más tarde</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Arriba al centro, flotando sobre el header (un poco más alto que él), para que el cajero
   lo vea sin buscarlo. Por debajo de los modales de Bootstrap (1055). */
#cobrosAvisos {
    position: fixed; top: 6px; left: 50%; transform: translateX(-50%); z-index: 1045;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    width: min(620px, calc(100vw - 32px)); pointer-events: none;
}
.cobro-aviso {
    pointer-events: auto; width: 100%; min-height: 88px; position: relative; overflow: hidden;
    display: flex; align-items: center; gap: 16px; padding: 14px 48px 16px 18px;
    background: linear-gradient(135deg, #047857 0%, #10b981 60%, #34d399 100%);
    color: #fff; border-radius: 14px;
    box-shadow: 0 12px 32px rgba(4, 120, 87, .45), 0 0 0 3px rgba(255, 255, 255, .9);
    animation: cobroEntra .35s cubic-bezier(.2, .9, .3, 1.2), cobroBrillo 1.6s ease-out .35s 2;
    transition: opacity .4s, transform .4s;
}
.cobro-aviso.saliendo { opacity: 0; transform: translateY(-24px); }
.cobro-aviso .cobro-icono {
    flex-shrink: 0; width: 56px; height: 56px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, .22); font-size: 1.9rem; line-height: 1;
}
.cobro-aviso .cobro-cuerpo { min-width: 0; flex: 1; }
.cobro-aviso .cobro-titulo { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
.cobro-aviso .cobro-monto  { font-size: 2rem; font-weight: 800; line-height: 1.05; text-shadow: 0 1px 2px rgba(0,0,0,.15); }
.cobro-aviso .cobro-nombre { font-size: 1.05rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cobro-aviso .cobro-meta   { font-size: .78rem; opacity: .85; white-space: nowrap; }
.cobro-aviso .cobro-lado   { text-align: right; flex-shrink: 0; }
.cobro-aviso .cobro-cerrar {
    position: absolute; top: 8px; right: 10px; border: 0; background: rgba(255,255,255,.18);
    width: 28px; height: 28px; border-radius: 50%;
    font-size: .95rem; line-height: 1; color: #fff; cursor: pointer;
}
.cobro-aviso .cobro-cerrar:hover { background: rgba(255,255,255,.32); }
.cobro-aviso .cobro-barra {
    position: absolute; left: 0; bottom: 0; height: 4px; background: rgba(255,255,255,.85);
    animation: cobroBarra linear forwards;
}
.cobro-aviso:hover .cobro-barra { animation-play-state: paused; }
@media (max-width: 575.98px) {
    .cobro-aviso { flex-wrap: wrap; gap: 10px; }
    .cobro-aviso .cobro-icono { width: 44px; height: 44px; font-size: 1.5rem; }
    .cobro-aviso .cobro-monto { font-size: 1.6rem; }
    .cobro-aviso .cobro-lado  { text-align: left; width: 100%; }
}
@keyframes cobroEntra { from { opacity: 0; transform: translateY(-40px) scale(.96); } to { opacity: 1; transform: none; } }
@keyframes cobroBrillo {
    0%   { box-shadow: 0 12px 32px rgba(4,120,87,.45), 0 0 0 3px rgba(255,255,255,.9), 0 0 0 0 rgba(16,185,129,.7); }
    100% { box-shadow: 0 12px 32px rgba(4,120,87,.45), 0 0 0 3px rgba(255,255,255,.9), 0 0 0 18px rgba(16,185,129,0); }
}
@keyframes cobroBarra { from { width: 100%; } to { width: 0; } }
</style>

<script>
(function () {
    /** Cuánto queda a la vista cada aviso. */
    const SEGUNDOS_VISIBLE = 12;
    const MAX_VISIBLES     = 3;

    const capa = document.getElementById('cobrosAvisos');
    let ultimoId  = 0;
    let enCurso   = false; // nunca dos consultas a la vez, para no apilarlas con la red lenta
    let apagado   = false; // 403: la licencia dejó de estar vigente → no se consulta más

    async function consultar() {
        if (enCurso || apagado) return;
        enCurso = true;
        try {
            const r = await fetch(`${BASE_URL}/cobros-recibidos/nuevos?desde_id=${ultimoId}`, { headers: { Accept: 'application/json' } });
            if (r.status === 403) { apagado = true; return; }
            const d = await r.json();
            if (!d.ok) return;
            d.cobros.forEach(mostrar);
            ultimoId = Math.max(ultimoId, d.ultimo_id || 0);
        } catch (_) {
            // En silencio, como las demás consultas del POS: si falla, el POS sigue igual.
        } finally {
            enCurso = false;
        }
    }

    function mostrar(c) {
        while (capa.children.length >= MAX_VISIBLES) capa.lastElementChild.remove();

        const monto = c.monto !== null ? formatMoneda(c.monto) : 'Monto oculto';
        const div = document.createElement('div');
        div.className = 'cobro-aviso';
        div.innerHTML = `
            <button type="button" class="cobro-cerrar" tabindex="-1" aria-label="Cerrar">✕</button>
            <div class="cobro-icono">💰</div>
            <div class="cobro-cuerpo">
                <div class="cobro-titulo">Transferencia recibida</div>
                <div class="cobro-monto">${esc(monto)}</div>
            </div>
            <div class="cobro-lado">
                <div class="cobro-nombre">${esc(c.nombre || 'Cobro recibido')}</div>
                <div class="cobro-meta">${esc(c.app)} · ${esc(c.hora)}</div>
            </div>
            <div class="cobro-barra" style="animation-duration:${SEGUNDOS_VISIBLE}s"></div>`;
        capa.prepend(div); // el más nuevo, arriba de todo

        div.querySelector('.cobro-cerrar').addEventListener('click', () => cerrar(div));
        // Se va cuando termina la barra; con el mouse encima la barra se pausa y el aviso espera.
        div.querySelector('.cobro-barra').addEventListener('animationend', () => cerrar(div));
    }

    function cerrar(div) {
        if (div.classList.contains('saliendo')) return;
        div.classList.add('saliendo');
        setTimeout(() => div.remove(), 400);
    }

    // Cada 5 s con la pestaña a la vista y cada 30 s cuando no (tarea 3.2).
    function programar() {
        setTimeout(async () => { await consultar(); programar(); },
            document.visibilityState === 'visible' ? 5000 : 30000);
    }
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') consultar(); });

    consultar();
    programar();
})();
</script>

<?php if ($turnoIdCobros): ?>
<script>
/*
 * Transferencias sin asignar del turno abierto. Cada 5 minutos se actualiza el contador del
 * botón; si pasó una hora desde la última vez que se mostró el pop-up y hay pendientes, se
 * abre solo. La hora de la última vez queda en localStorage por turno, así recargar el POS
 * no lo vuelve a abrir enseguida (y un turno nuevo empieza de cero).
 */
(function () {
    const TURNO_ID   = <?= $turnoIdCobros ?>;
    const CLAVE      = 'dewocCobros.pendientes.' + TURNO_ID;
    const CADA_MS    = 60 * 60 * 1000;     // una hora
    const REVISAR_MS = 5 * 60 * 1000;      // contador del botón
    const MEDIOS     = { efectivo: 'Efectivo', debito: 'Débito', credito: 'Crédito', transferencia: 'Transferencia',
                         mercadopago: 'Mercado Pago', cuenta_corriente: 'Cuenta corriente', combinado: 'Combinado' };
    let ultimo = null, enCurso = false, abierto = false;

    const leerUltimaVez = () => { try { return parseInt(localStorage.getItem(CLAVE) || '0', 10) || 0; } catch (_) { return 0; } };
    const guardarUltimaVez = (t) => { try { localStorage.setItem(CLAVE, String(t)); } catch (_) {} };
    // Primera vez en este turno: el primer pop-up sale a la hora, no apenas se abre el POS.
    if (!leerUltimaVez()) guardarUltimaVez(Date.now());

    async function traer() {
        if (enCurso) return null;
        enCurso = true;
        try {
            const r = await fetch(`${BASE_URL}/cobros-recibidos/pendientes-turno`, { headers: { Accept: 'application/json' } });
            if (!r.ok) return null;
            const d = await r.json();
            if (!d.ok) return null;
            ultimo = d;
            const n = d.cobros.length;
            document.getElementById('cntTransfPendientes').textContent = n;
            document.getElementById('wrapTransfPendientes').classList.toggle('d-none', n === 0);
            return d;
        } catch (_) {
            return null; // en silencio, como las demás consultas del POS
        } finally {
            enCurso = false;
        }
    }

    async function revisar() {
        const d = await traer();
        if (!d || abierto || !d.cobros.length) return;
        // Con una venta en curso no se abre: el pop-up toma el foco y el lector de códigos
        // escribiría adentro. Se vuelve a intentar en la próxima revisión (5 minutos).
        if (typeof carrito !== 'undefined' && carrito.length) return;
        if (document.querySelector('.modal.show')) return;
        if (Date.now() - leerUltimaVez() >= CADA_MS) mostrar();
    }

    function etiquetaVenta(v) {
        const medio = MEDIOS[v.metodo_pago] || v.metodo_pago;
        return `#${v.id} · ${v.hora} · ${formatMoneda(v.total)} · ${medio}` + (v.mesa ? ` · ${v.mesa}` : '');
    }

    function pintar() {
        const tb = document.getElementById('tbTransfPendientes');
        if (!ultimo || !ultimo.cobros.length) {
            tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No quedan transferencias sin asignar en este turno. 🎉</td></tr>';
            return;
        }
        tb.innerHTML = ultimo.cobros.map(c => {
            const opciones = c.ventas.map(v => `<option value="${v.id}">${esc(etiquetaVenta(v))}</option>`).join('');
            const medio = ultimo.puede_cambiar_medio
                ? `<select class="form-select form-select-sm mt-1" data-medio="${c.id}" title="Corrige el medio de pago de la venta">
                       <option value="">Medio de pago: no cambiar</option>
                       <option value="transferencia">Cambiar a Transferencia</option>
                       <option value="mercadopago">Cambiar a Mercado Pago</option>
                   </select>` : '';
            return `<tr>
                <td class="text-nowrap">${esc(c.hora)}</td>
                <td class="text-end fw-semibold text-nowrap">${c.monto !== null ? esc(formatMoneda(c.monto)) : '<span class="text-muted">oculto</span>'}</td>
                <td>${esc(c.nombre || '—')}<span class="d-block text-muted" style="font-size:.72rem">${esc(c.app || '')}</span></td>
                <td>
                    ${c.ventas.length
                        ? `<select class="form-select form-select-sm" data-venta="${c.id}"><option value="">— Elegí la venta —</option>${opciones}</select>${medio}`
                        : '<span class="text-muted">No hay ventas de este turno sin transferencia asignada.</span>'}
                </td>
                <td class="text-end text-nowrap">
                    ${c.ventas.length ? `<button type="button" class="btn btn-primary btn-sm" onclick="cobrosAsignar(${c.id})">Asignar</button>` : ''}
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cobrosNoCorresponde(${c.id})">No corresponde</button>
                </td>
            </tr>`;
        }).join('');
    }

    function mostrar() {
        pintar();
        document.getElementById('errTransfPendientes').classList.add('d-none');
        guardarUltimaVez(Date.now());
        abierto = true;
        _showModal('modalTransfPendientes');
    }

    async function postear(ruta, datos) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(datos)) fd.append(k, v);
        fd.append('_csrf_token', CSRF_TOKEN);
        const r = await fetch(`${BASE_URL}/cobros-recibidos/${ruta}`, { method: 'POST', body: fd });
        return r.json();
    }

    async function despues(d) {
        const err = document.getElementById('errTransfPendientes');
        if (!d.ok) { err.textContent = d.error || 'No se pudo guardar.'; err.classList.remove('d-none'); return; }
        err.classList.add('d-none');
        showToast(d.mensaje);
        await traer();
        pintar();
    }

    window.cobrosAbrirPendientes = async function () {
        await traer();
        mostrar();
    };

    window.cobrosAsignar = async function (cobroId) {
        const sel = document.querySelector(`[data-venta="${cobroId}"]`);
        if (!sel || !sel.value) {
            sel?.classList.add('is-invalid');
            despues({ ok: false, error: 'Elegí la venta a la que corresponde la transferencia.' });
            return;
        }
        const medio = document.querySelector(`[data-medio="${cobroId}"]`);
        try {
            despues(await postear('asignar', { cobro_id: cobroId, venta_id: sel.value, cambiar_medio: medio ? medio.value : '' }));
        } catch (_) { despues({ ok: false, error: 'Error de conexión.' }); }
    };

    window.cobrosNoCorresponde = function (cobroId) {
        // Se abre encima de este pop-up (showPrompt, assets/js/app.js).
        showPrompt('¿Por qué no corresponde a una venta?', async (motivo) => {
            try {
                despues(await postear('no-corresponde', { cobro_id: cobroId, motivo }));
            } catch (_) { despues({ ok: false, error: 'Error de conexión.' }); }
        }, { sub: 'Deja de aparecer en esta lista.', placeholder: 'Seña, devolución, transferencia personal…', label: 'Marcar' });
    };

    document.getElementById('modalTransfPendientes').addEventListener('hidden.bs.modal', () => { abierto = false; });

    revisar();
    setInterval(revisar, REVISAR_MS);
})();
</script>
<?php endif; ?>
