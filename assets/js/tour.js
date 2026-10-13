// ── Tour guiado del help desk del Chat IA (ver docs/tareas-pendientes.md, sección
// Help Desk) — motor liviano hecho a mano, sin librerías externas: resalta elementos
// reales de la pantalla con un "spotlight" y un tooltip con Anterior/Siguiente/Cerrar.
// Se arranca solo si la URL trae ?tour=<id> (lo agrega el botón "Tour guiado" de la
// tarjeta de ayuda del chat). Los selectores apuntan a los mismos id/atributos que ya
// existen en cada pantalla — no hace falta marcar nada en esas vistas.
(function () {
    const TOURS = {
        pos_facturar: [
            { selector: '#buscadorPos', texto: 'Buscá el producto por nombre o código acá.' },
            { selector: '#carritoBody', texto: 'Los productos que vas agregando se suman al carrito. Podés ajustar cantidades acá.' },
            { selector: '#btnCobrar', texto: 'Cuando el carrito esté completo, tocá "Cobrar" para finalizar la venta.' },
        ],
        caja_abrir_turno: [
            { selector: '#formAbrir', texto: 'Cargá acá el monto inicial en efectivo con el que arrancás el turno.' },
            { selector: '#btnAbrir', texto: 'Tocá "Abrir turno" para empezar a vender.' },
        ],
        caja_cerrar_turno: [
            { selector: '[data-bs-target="#modalCerrar"]', texto: 'Tocá "Cerrar turno" para abrir el formulario de cierre.' },
            {
                selector: '#formCerrar',
                texto: 'Contá el efectivo real y cargá lo declarado por cada método de pago.',
                beforeShow: abrirModalSiHaceFalta('[data-bs-target="#modalCerrar"]', '#modalCerrar'),
            },
            { selector: '#btnCerrar', texto: 'Confirmá acá para cerrar el turno y ver si hubo diferencia.' },
        ],
        stock_ajustar: [
            { selector: '#tblStock', texto: 'Buscá el ítem que querés ajustar en esta tabla.' },
            { selector: '[title="Ajustar stock"]', texto: 'Tocá el ícono ± en su fila para ajustar el stock.' },
            {
                selector: '#formAjuste',
                texto: 'Cargá la cantidad a sumar o restar y el motivo del ajuste.',
                beforeShow: abrirModalSiHaceFalta('[title="Ajustar stock"]', '#modalAjuste'),
            },
            { selector: '#btnAjustar', texto: 'Confirmá para guardar el ajuste.' },
        ],
    };

    // Algunos pasos viven adentro de un modal Bootstrap que hay que abrir antes de poder
    // resaltarlos — clickear el trigger real (mismo mecanismo que usaría el usuario) en vez
    // de llamar a la API de bootstrap.Modal directo, porque el bundle Dashly no siempre la
    // expone en window (ver la misma nota en assets/js/app.js, función showConfirm()).
    function abrirModalSiHaceFalta(selectorTrigger, selectorModal) {
        return () => {
            const modal = document.querySelector(selectorModal);
            if (modal && modal.classList.contains('show')) return;
            const trigger = document.querySelector(selectorTrigger);
            if (trigger) trigger.click();
        };
    }

    let pasos = [];
    let idx = 0;
    let overlay = null, spot = null, tip = null;

    function crearOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'tourOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2000;pointer-events:none;';

        spot = document.createElement('div');
        spot.style.cssText = 'position:fixed;z-index:2001;border-radius:8px;'
            + 'box-shadow:0 0 0 9999px rgba(0,0,0,.6);transition:all .2s ease;pointer-events:none;';
        overlay.appendChild(spot);

        tip = document.createElement('div');
        tip.style.cssText = 'position:fixed;z-index:2002;max-width:280px;background:#fff;color:#212529;'
            + 'border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.3);padding:12px 14px;'
            + 'font-size:.85rem;pointer-events:auto;';
        overlay.appendChild(tip);

        document.body.appendChild(overlay);
        window.addEventListener('resize', reposicionarActual);
    }

    let elementoActual = null;
    function reposicionarActual() {
        if (elementoActual) posicionar(elementoActual);
    }

    function limpiarQueryTour() {
        const url = new URL(location.href);
        url.searchParams.delete('tour');
        history.replaceState({}, '', url.toString());
    }

    function cerrarTour() {
        if (overlay) overlay.remove();
        window.removeEventListener('resize', reposicionarActual);
        overlay = spot = tip = elementoActual = null;
        limpiarQueryTour();
    }

    function posicionar(el) {
        const r = el.getBoundingClientRect();
        const pad = 6;
        spot.style.left   = (r.left - pad) + 'px';
        spot.style.top    = (r.top - pad) + 'px';
        spot.style.width  = (r.width + pad * 2) + 'px';
        spot.style.height = (r.height + pad * 2) + 'px';

        let top = r.bottom + 14;
        if (top + 140 > window.innerHeight) top = Math.max(10, r.top - 150);
        const left = Math.min(Math.max(10, r.left), window.innerWidth - 296);
        tip.style.top  = top + 'px';
        tip.style.left = left + 'px';
    }

    function renderTip(paso) {
        const esUltimo  = idx === pasos.length - 1;
        const esPrimero = idx === 0;
        tip.innerHTML = `
            <div class="mb-2">${paso.texto}</div>
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="text-muted" style="font-size:.75rem">${idx + 1}/${pasos.length}</span>
                <div class="d-flex gap-1">
                    ${esPrimero ? '' : '<button type="button" class="btn btn-sm btn-outline-secondary" data-tour-btn="prev">Anterior</button>'}
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tour-btn="close">Cerrar</button>
                    <button type="button" class="btn btn-sm btn-primary" data-tour-btn="next">${esUltimo ? 'Listo' : 'Siguiente'}</button>
                </div>
            </div>`;
        tip.querySelector('[data-tour-btn="close"]').addEventListener('click', cerrarTour);
        tip.querySelector('[data-tour-btn="next"]').addEventListener('click', () => esUltimo ? cerrarTour() : mostrarPaso(idx + 1));
        const btnPrev = tip.querySelector('[data-tour-btn="prev"]');
        if (btnPrev) btnPrev.addEventListener('click', () => mostrarPaso(idx - 1));
    }

    function esperarElemento(selector, intentos = 20) {
        return new Promise(resolve => {
            const check = () => {
                const el = document.querySelector(selector);
                if (el) return resolve(el);
                if (--intentos <= 0) return resolve(null);
                setTimeout(check, 50);
            };
            check();
        });
    }

    async function mostrarPaso(n) {
        idx = n;
        const paso = pasos[idx];
        if (typeof paso.beforeShow === 'function') paso.beforeShow();

        const el = await esperarElemento(paso.selector);
        if (!el) {
            // El elemento no está en esta pantalla (ej. tour arrancado desde otra sección):
            // se salta al siguiente paso en vez de trabar el tour ahí.
            if (idx + 1 < pasos.length) return mostrarPaso(idx + 1);
            return cerrarTour();
        }

        elementoActual = el;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { posicionar(el); renderTip(paso); }, 220);
    }

    function iniciarTour(tourId) {
        pasos = TOURS[tourId];
        if (!pasos || !pasos.length) return;
        crearOverlay();
        mostrarPaso(0);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tourId = new URLSearchParams(location.search).get('tour');
        if (tourId && TOURS[tourId]) iniciarTour(tourId);
    });
})();
