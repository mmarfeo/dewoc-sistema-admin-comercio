<!-- ── Burbuja flotante del Chat IA (ver docs/tareas-pendientes.md, sección Help Desk) ──
     Panel liviano, global (se incluye desde main.php en toda pantalla salvo /chat, que ya
     tiene la experiencia completa). Comparte la MISMA conversación que /chat — el envío pega
     al mismo POST chat/mensaje de siempre; acá solo se agrega el botón/panel y el JS que lo
     abre, en assets/js/chat-flotante.js. Estilos propios y chicos a propósito: NO reutiliza
     el .wa-bubble de chat/index.php (pensado para la página completa, mucho más pesado). -->
<div id="cfWidget" class="cf-widget">
    <button type="button" id="cfBubble" class="cf-bubble" title="Asistente IA" aria-label="Abrir asistente">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    </button>

    <div id="cfPanel" class="cf-panel d-none">
        <div class="cf-header">
            <span class="cf-titulo">Asistente IA</span>
            <div class="cf-header-botones">
                <a href="<?= url('chat') ?>" class="cf-icon-btn" title="Abrir pantalla completa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
                </a>
                <button type="button" id="cfBtnCerrar" class="cf-icon-btn" title="Cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div id="cfMensajes" class="cf-mensajes">
            <div class="cf-vacio text-muted small">Preguntame algo como "¿cómo facturo?" o "¿cómo abro turno?"</div>
        </div>

        <form id="cfForm" class="cf-form">
            <input type="text" id="cfInput" class="cf-input" placeholder="Escribí tu pregunta..." autocomplete="off">
            <button type="submit" class="cf-enviar" title="Enviar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
    </div>
</div>

<style>
.cf-widget { position: fixed; right: 20px; bottom: 84px; z-index: 1500; }

.cf-bubble {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--bs-primary, #0d6efd); color: #fff; border: none;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 14px rgba(0,0,0,.25); cursor: pointer;
}
.cf-widget.cf-open .cf-bubble { display: none; }

.cf-panel {
    position: fixed; right: 20px; bottom: 84px;
    width: 340px; height: 460px;
    background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529);
    border-radius: 14px; box-shadow: 0 10px 32px rgba(0,0,0,.3);
    display: flex; flex-direction: column; overflow: hidden;
}

.cf-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px; background: var(--bs-primary, #0d6efd); color: #fff; flex-shrink: 0;
}
.cf-titulo { font-weight: 600; font-size: .9rem; }
.cf-header-botones { display: flex; gap: 4px; }
.cf-icon-btn {
    width: 26px; height: 26px; border-radius: 6px; border: none;
    background: rgba(255,255,255,.15); color: #fff;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.cf-icon-btn:hover { background: rgba(255,255,255,.3); color: #fff; }

.cf-mensajes { flex: 1 1 auto; overflow-y: auto; padding: 10px; font-size: .85rem; }
.cf-vacio { text-align: center; padding: 20px 8px; }

.cf-msg { margin-bottom: 10px; max-width: 88%; }
.cf-msg-user { margin-left: auto; }
.cf-msg-bubble { padding: 8px 10px; border-radius: 10px; white-space: pre-wrap; word-break: break-word; }
.cf-msg-user .cf-msg-bubble { background: var(--bs-primary, #0d6efd); color: #fff; border-bottom-right-radius: 2px; }
.cf-msg-bot .cf-msg-bubble { background: var(--bs-tertiary-bg, #f1f3f5); border-bottom-left-radius: 2px; }
.cf-msg-error .cf-msg-bubble { background: #ffebee; color: #b3261e; }

.cf-msg-botones { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }

.cf-form { display: flex; gap: 6px; padding: 10px; border-top: 1px solid var(--bs-border-color, #dee2e6); flex-shrink: 0; }
.cf-input {
    flex: 1 1 auto; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 20px;
    padding: 6px 12px; font-size: .85rem; background: var(--bs-body-bg, #fff); color: inherit;
}
.cf-enviar {
    width: 34px; height: 34px; border-radius: 50%; border: none; flex-shrink: 0;
    background: var(--bs-primary, #0d6efd); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.cf-enviar:disabled { opacity: .6; cursor: default; }

@media (max-width: 480px) {
    .cf-panel { width: calc(100vw - 24px); height: 70vh; right: 12px; }
    .cf-widget, .cf-panel { right: 12px; }
}
</style>
