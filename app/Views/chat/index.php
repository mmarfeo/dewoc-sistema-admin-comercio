<div class="chat-page">
<?php if (!$apiActiva): ?>
<div class="alert alert-warning d-flex gap-2 mb-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div><strong>Sin API key.</strong> Completá <code>GEMINI_API_KEY</code> en el archivo <code>.env</code>.</div>
</div>
<?php endif; ?>
<?php if (!empty($soloLectura)): ?>
<div class="alert alert-info d-flex gap-2 mb-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div>Viendo el historial de <strong><?= e($usuarioObjetivo['nombre']) ?></strong> — modo solo lectura, no se puede escribir ni confirmar nada desde acá.</div>
</div>
<?php endif; ?>

<div class="chat-card-grow">
    <div class="card border-0 p-0 overflow-hidden">
        <div class="d-flex flex-column h-100">

            <!-- ── Ventana de mensajes: el buscador y los accesos directos ahora viven
                 arriba, en el header general (junto al toggle de modo oscuro) ── -->
            <div class="wa-chat-bg overflow-auto p-3 flex-grow-1 d-flex flex-column gap-2" id="chatWindow">

                    <!-- Bienvenida -->
                    <div class="wa-row">
                        <div class="wa-bubble wa-bubble-in">
                            <p class="mb-2 small fw-semibold">¡Hola! Soy tu asistente ERP. Puedo:</p>
                            <div class="small d-flex flex-column gap-1">
                                <div>📊 <strong>Consultar</strong> ventas, compras, stock, caja de cualquier período</div>
                                <div>✅ <strong>Crear</strong> clientes, proveedores y usuarios</div>
                                <div>🛒 <strong>Registrar</strong> facturas de compra (actualiza stock)</div>
                                <div>📦 <strong>Ajustar</strong> stock manualmente</div>
                            </div>
                        </div>
                    </div>

                    <!-- Historial -->
                    <?php foreach ($historial as $m): ?>
                    <?php
                        // Avisos de reportes cron (ver core/NotificadorReportes.php): vienen con
                        // datos_json={"tipo":"reporte_cron","reporte_url":"..."} para poder dibujar
                        // el botón "Ver reporte" en vez de dejar el link suelto en el texto.
                        $datosReporte  = !empty($m['datos_json']) ? json_decode($m['datos_json'], true) : null;
                        $esReporteCron = is_array($datosReporte) && ($datosReporte['tipo'] ?? null) === 'reporte_cron';
                    ?>
                    <?php if ($m['rol'] === 'user'): ?>
                    <div class="wa-row wa-row-out">
                        <div class="wa-bubble wa-bubble-out">
                            <?php if (!empty($m['archivo_adjunto'])): ?>
                            <a href="<?= url('chat/ver-adjunto?id=' . (int)$m['id']) ?>" target="_blank" class="d-block mb-2">
                                <?php if ($m['archivo_mime'] === 'application/pdf'): ?>
                                <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(0,0,0,.08)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/><line x1="9" y1="9" x2="10" y2="9"/></svg>
                                    <span class="small">Ver PDF adjunto</span>
                                </div>
                                <?php else: ?>
                                <img src="<?= url('chat/ver-adjunto?id=' . (int)$m['id']) ?>" class="rounded d-block" style="max-width:160px;max-height:100px;object-fit:cover">
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>
                            <p class="mb-0 small"><?= nl2br(e($m['contenido'])) ?></p>
                            <div class="wa-meta"><?= date('H:i', strtotime($m['created_at'])) ?> <span class="wa-ticks">✓✓</span></div>
                        </div>
                    </div>
                    <?php elseif ($esReporteCron): ?>
                    <div class="wa-row">
                        <div class="wa-bubble wa-bubble-in wa-bubble-accion">
                            <p class="mb-0 small"><?= nl2br(eLinkificado($m['contenido'])) ?></p>
                            <div class="d-flex gap-2 mt-2">
                                <a href="<?= url($datosReporte['reporte_url']) ?>" class="btn btn-sm btn-success">Ver reporte</a>
                                <a href="<?= url(str_replace('/ver?', '/exportar?', $datosReporte['reporte_url'])) ?>" class="btn btn-sm btn-outline-success">Descargar Excel</a>
                            </div>
                            <div class="wa-meta"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php elseif (!empty($m['datos_json'])): ?>
                    <div class="wa-row" data-confirmacion='<?= e($m['datos_json']) ?>' data-mensaje-id="<?= (int)$m['id'] ?>"></div>
                    <?php else: ?>
                    <div class="wa-row">
                        <div class="wa-bubble wa-bubble-in">
                            <?php if ($m['sql_ejecutado']): ?>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 toggle-sql mb-1" data-sql="<?= e($m['sql_ejecutado']) ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> SQL
                            </button>
                            <?php endif; ?>
                            <p class="mb-0 small"><?= nl2br(eLinkificado($m['contenido'])) ?></p>
                            <div class="wa-meta"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>

                </div><!-- /chatWindow -->

                <!-- ── Input estilo WhatsApp (oculto en el visor de solo lectura del admin) ── -->
                <?php if (empty($soloLectura)): ?>
                <div class="wa-input-bar p-2">

                    <!-- Preview imagen/PDF adjunto -->
                    <div id="imgPreviewWrap" class="d-none align-items-center gap-2 mb-2 p-2 rounded" style="background:rgba(0,0,0,.06)">
                        <div id="imgPreviewThumb" class="rounded border flex-shrink-0 d-flex align-items-center justify-content-center overflow-hidden" style="width:44px;height:44px;background:#fff">
                            <img id="imgPreviewThumbImg" src="" alt="" style="width:44px;height:44px;object-fit:cover;display:none">
                            <svg id="imgPreviewThumbPdf" xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" stroke="#dc3545" stroke-width="1.5" viewBox="0 0 24 24" style="display:none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/><line x1="9" y1="9" x2="10" y2="9"/></svg>
                        </div>
                        <span class="small text-muted flex-grow-1 text-truncate" id="imgPreviewNombre"></span>
                        <button type="button" id="btnQuitarImg" class="btn-close" style="font-size:.65rem" title="Quitar archivo"></button>
                    </div>

                    <!-- Inputs de archivo ocultos -->
                    <input type="file" id="inputImagen" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" class="d-none">
                    <input type="file" id="inputCamara" accept="image/*" capture="environment" class="d-none">

                    <form id="chatForm" class="d-flex align-items-center gap-2 mb-2">
                        <?= Csrf::field() ?>
                        <button type="button" id="btnAdjuntar" class="wa-icon-btn flex-shrink-0" title="Adjuntar imagen de factura" <?= !$apiActiva ? 'disabled' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <button type="button" id="btnCamara" class="wa-icon-btn flex-shrink-0 d-md-none" title="Sacar foto a la factura" <?= !$apiActiva ? 'disabled' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </button>
                        <input type="text" id="chatInput" name="mensaje" class="wa-input-pill flex-grow-1"
                            placeholder="Escribí un mensaje..."
                            autocomplete="off" <?= !$apiActiva ? 'disabled' : '' ?>>
                        <button type="submit" class="wa-send-btn flex-shrink-0" id="btnEnviar" <?= !$apiActiva ? 'disabled' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </form>

                    <!-- Acciones rápidas: el catálogo lo asigna el admin; cuáles se ven acá abajo lo elige el usuario
                         (el botón "Accesos directos" para elegirlas está arriba, junto al buscador) -->
                    <?php if ($accesosDirectos): ?>
                    <div class="d-flex flex-wrap gap-2" id="chipsAccesos">
                        <?php foreach ($accesosDirectos as $accion): ?>
                        <?php
                        // Un acceso con accion_interna se resuelve con una consulta PHP directa (ver
                        // ChatController::accionDirecta() / docs/chat-accesos-sin-llm-analisis.md), sin
                        // pasar por Gemini — por eso queda habilitado aunque no haya API key configurada.
                        $esDirecta = !empty($accion['accion_interna']);
                        ?>
                        <button type="button"
                            class="wa-chip ejemplo-chat <?= (!$apiActiva && !$esDirecta) ? 'disabled' : '' ?>"
                            data-id="<?= (int)$accion['id'] ?>"
                            data-texto="<?= e($accion['texto']) ?>"
                            data-accion-interna="<?= e($accion['accion_interna'] ?? '') ?>">
                            <?= e($accion['label']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div><!-- /chat-page -->

<!-- Modal: elegir accesos directos personales -->
<div class="modal fade" id="modalAccesosDirectos" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Accesos directos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Elegí cuáles de los accesos directos que te asignó el administrador querés ver debajo del chat.
                </p>
                <div class="d-flex flex-column gap-2" id="listaAccesosPersonal">
                    <?php foreach ($accesosDirectos as $accion): ?>
                    <div class="form-check">
                        <input class="form-check-input chk-acceso-visible" type="checkbox" value="<?= (int)$accion['id'] ?>" id="accVis<?= (int)$accion['id'] ?>" checked>
                        <label class="form-check-label small" for="accVis<?= (int)$accion['id'] ?>"><?= e($accion['label']) ?></label>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$accesosDirectos): ?>
                    <div class="text-muted small">Todavía no tenés accesos directos asignados. Pedile a un administrador que te asigne alguno desde tu ficha de usuario.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal SQL -->
<div class="modal fade" id="modalSQL" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title small fw-semibold">SQL ejecutado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <pre id="modalSQLContent" class="bg-dark text-success p-3 rounded-bottom small mb-0" style="white-space:pre-wrap;word-break:break-all;max-height:400px;overflow-y:auto"></pre>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN   = '<?= Csrf::token() ?>';
const BASE_URL     = '<?= BASE_URL ?>';
const SHARED_FILE  = <?= isset($archivoCompartido) && $archivoCompartido ? json_encode($archivoCompartido, JSON_HEX_TAG | JSON_HEX_AMP) : 'null' ?>;
const ES_ADMIN      = <?= json_encode((Session::user()['rol'] ?? '') === 'admin') ?>;
const SOLO_LECTURA  = <?= !empty($soloLectura) ? 'true' : 'false' ?>;
const UNIDADES_MEDIDA = <?= json_encode($unidadesMedida ?? [], JSON_UNESCAPED_UNICODE) ?>;
const SOCIOS        = <?= json_encode($socios ?? [], JSON_UNESCAPED_UNICODE) ?>;
const GENERICOS     = <?= json_encode($genericos ?? [], JSON_UNESCAPED_UNICODE) ?>;
const URL_ACCESOS   = '<?= url('chat/accesos') ?>';

// Fallback para navegadores que no soportan el selector CSS :has() (algunos
// Chrome/WebView de Android más viejos): sin esto, las reglas que acotan la
// altura de .main-content y .container-fluid al alto de la ventana no se
// aplican, la página entera vuelve a scrollear y el header queda tapado al
// llegar abajo del todo del chat. Corre apenas se parsea este <script>, sin
// esperar a 'load', porque .chat-page y .main-content ya existen en el DOM
// (este script va después de ellos en el HTML).
(function fijarLayoutChatSinHas() {
    const chatPage = document.querySelector('.chat-page');
    if (!chatPage) return;
    if (chatPage.parentElement) chatPage.parentElement.classList.add('dewoc-chat-container');
    const mainContent = document.querySelector('.main-content');
    if (mainContent) mainContent.classList.add('dewoc-chat-fullheight');
})();

// Esperar a que Bootstrap y jQuery estén cargados (los scripts del layout van DESPUÉS del contenido)
window.addEventListener('load', function () {

const chatWindow     = document.getElementById('chatWindow');
const chatInput      = document.getElementById('chatInput');
const btnEnviar      = document.getElementById('btnEnviar');
const inputImagen    = document.getElementById('inputImagen');
const inputCamara    = document.getElementById('inputCamara');
const btnAdjuntar    = document.getElementById('btnAdjuntar');
const btnCamara      = document.getElementById('btnCamara');
const imgPreviewWrap = document.getElementById('imgPreviewWrap');
let   archivoImagen  = null;

// ── Controles en el header general: buscador + accesos directos, junto al
// toggle de modo oscuro. Viven en layouts/header.php (compartido con toda la
// app), así el chat queda libre de header propio y la conversación ocupa
// toda la altura disponible entre el header general y el input de abajo. ──
(function construirControlesHeader() {
    const btnThemeToggle = document.getElementById('btnThemeToggle');
    if (!btnThemeToggle || document.getElementById('btnBuscarChatHeader')) return;

    // Accesos directos (abre el modal para elegir cuáles se ven abajo)
    const btnAccesos = document.createElement('button');
    btnAccesos.type = 'button';
    btnAccesos.id = 'btnAccesosDirectos';
    btnAccesos.className = 'btn btn-link text-body p-0';
    btnAccesos.title = 'Elegir accesos directos';
    btnAccesos.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    btnThemeToggle.insertAdjacentElement('beforebegin', btnAccesos);

    // Catálogo de accesos directos (solo admin)
    if (ES_ADMIN) {
        const linkCatalogo = document.createElement('a');
        linkCatalogo.href = URL_ACCESOS;
        linkCatalogo.className = 'btn btn-link text-body p-0';
        linkCatalogo.title = 'Administrar catálogo de accesos directos';
        linkCatalogo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
        btnThemeToggle.insertAdjacentElement('beforebegin', linkCatalogo);
    }

    // Buscador: mismo patrón de dropdown que notificaciones/usuario en este header
    const wrapBuscar = document.createElement('div');
    wrapBuscar.className = 'dropdown';
    wrapBuscar.innerHTML = `
        <button type="button" class="btn btn-link text-body p-0" id="btnBuscarChatHeader" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Buscar en la conversación">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width:260px">
            <div class="d-flex align-items-center gap-2">
                <input type="text" id="chatSearch" class="form-control form-control-sm" placeholder="Buscar en la conversación..." autocomplete="off">
                <button type="button" id="btnLimpiarBusqueda" class="btn btn-sm btn-link text-body p-0 d-none flex-shrink-0" title="Limpiar búsqueda">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="chatSearchCount" class="small text-muted mt-1"></div>
        </div>`;
    btnThemeToggle.insertAdjacentElement('beforebegin', wrapBuscar);
    wrapBuscar.addEventListener('shown.bs.dropdown', () => document.getElementById('chatSearch').focus());
})();

// Rehidratar tarjetas de confirmación del historial (quedaron como placeholders vacíos
// en el PHP — ver data-confirmacion) con el mismo renderer que usan los mensajes en vivo.
// Si el render falla (datos viejos/dañados, un ítem que la tarjeta referenciaba y ya no
// existe, etc.) el placeholder ANTES quedaba vacío e invisible pero la tarjeta seguía
// 'pendiente' en la base — bloqueando cualquier subida de comprobante nuevo sin que el
// usuario tuviera nada visible para cancelar (bug reportado: "no veo ninguna compra que
// cancelar"). Ahora, si falla, se muestra igual una tarjeta mínima con botón "Cancelar".
document.querySelectorAll('#chatWindow .wa-row[data-confirmacion]').forEach(row => {
    const mensajeId = parseInt(row.dataset.mensajeId, 10);
    try {
        const payload = JSON.parse(row.dataset.confirmacion);
        const tipo    = payload.tipo === 'guia_ayuda' ? 'guia_ayuda' : (payload.tipo === 'compra' ? 'confirmacion_compra' : 'confirmacion_accion');
        row.replaceWith(crearFilaConfirmacion(tipo, payload, mensajeId));
    } catch (e) {
        console.error('No se pudo rehidratar una tarjeta de confirmación:', e);
        if (!isNaN(mensajeId)) row.replaceWith(crearFilaConfirmacionRota(mensajeId));
    }
});

/** Tarjeta de emergencia cuando una confirmación no se pudo dibujar — solo ofrece Cancelar (nunca Confirmar, porque no sabemos qué acción real llevaba adentro). */
function crearFilaConfirmacionRota(mensajeId) {
    const wrap = document.createElement('div');
    wrap.className = 'wa-row';

    const bubble = document.createElement('div');
    bubble.className = 'wa-bubble wa-bubble-in wa-confirm-bubble';

    const cont = document.createElement('div');
    cont.className = 'wa-confirm-card';

    const p = document.createElement('p');
    p.className = 'mb-2 small text-danger';
    p.textContent = '⚠️ Esta tarjeta de confirmación no se pudo mostrar (datos dañados o de una versión anterior del sistema). Cancelala para poder seguir usando el chat normalmente.';
    cont.appendChild(p);

    const botones = document.createElement('div');
    botones.className = 'd-flex gap-2 wa-confirm-botones';
    const btnCancelar = document.createElement('button');
    btnCancelar.type = 'button';
    btnCancelar.className = 'btn btn-sm btn-outline-danger';
    btnCancelar.textContent = 'Cancelar esta tarjeta';
    btnCancelar.addEventListener('click', () => resolverConfirmacion('cancelar', mensajeId, null, cont, botones));
    botones.appendChild(btnCancelar);
    cont.appendChild(botones);

    bubble.appendChild(cont);
    wrap.appendChild(bubble);
    return wrap;
}

// Scroll al fondo al cargar
chatWindow.scrollTop = chatWindow.scrollHeight;

// ── Adjuntar imagen / cámara (no aplica en el visor de solo lectura del admin,
// donde ni siquiera existen estos elementos en el DOM) ──────────────────────
if (!SOLO_LECTURA) {
btnAdjuntar.addEventListener('click', () => inputImagen.click());
if (btnCamara) btnCamara.addEventListener('click', () => inputCamara.click());
}

function usarArchivo(file) {
    if (!file) return;
    archivoImagen = file;
    const thumbImg = document.getElementById('imgPreviewThumbImg');
    const thumbPdf = document.getElementById('imgPreviewThumbPdf');
    document.getElementById('imgPreviewNombre').textContent = file.name;
    if (file.type === 'application/pdf') {
        thumbImg.style.display = 'none';
        thumbPdf.style.display = '';
        imgPreviewWrap.classList.remove('d-none');
        imgPreviewWrap.classList.add('d-flex');
    } else {
        const reader = new FileReader();
        reader.onload = ev => {
            thumbImg.src = ev.target.result;
            thumbImg.style.display = '';
            thumbPdf.style.display = 'none';
            imgPreviewWrap.classList.remove('d-none');
            imgPreviewWrap.classList.add('d-flex');
        };
        reader.readAsDataURL(file);
    }
}

if (!SOLO_LECTURA) {
inputImagen.addEventListener('change', () => { usarArchivo(inputImagen.files[0]); inputImagen.value = ''; });
inputCamara.addEventListener('change', () => { usarArchivo(inputCamara.files[0]); inputCamara.value = ''; });

document.getElementById('btnQuitarImg').addEventListener('click', () => {
    archivoImagen = null;
    imgPreviewWrap.classList.add('d-none');
    imgPreviewWrap.classList.remove('d-flex');
});
}

// Función central de envío
async function enviar(texto) {
    texto = (texto || '').trim();
    const imagen = archivoImagen;
    if (!texto && !imagen) return;

    const esPdf    = imagen?.type === 'application/pdf';
    const textoUI  = texto
        || (esPdf
            ? '📎 PDF adjunto — analizá la factura y mostrá un resumen para confirmar el registro.'
            : '📎 Imagen adjunta — analizá la factura y mostrá un resumen para confirmar el registro.');

    chatInput.value = '';
    btnEnviar.disabled = true;

    // Capturar info del archivo antes de limpiar. OJO: si no hay imagen, el <img> de la
    // vista previa tiene src="" — el DOM resuelve eso a la URL de la página actual (no a
    // string vacío), así que hay que atar esto a que "imagen" exista, no solo a "no es PDF".
    const thumbSrc  = (imagen && !esPdf) ? document.getElementById('imgPreviewThumbImg').src : null;
    const pdfNombre = esPdf  ? (imagen?.name || 'archivo.pdf') : null;

    // Limpiar preview
    if (imagen) {
        archivoImagen = null;
        inputImagen.value = '';
        imgPreviewWrap.classList.add('d-none');
        imgPreviewWrap.classList.remove('d-flex');
    }

    appendBurbuja('user', textoUI, null, null, thumbSrc, pdfNombre);
    const typing = appendTyping();

    try {
        const fd = new FormData();
        fd.append('mensaje', textoUI);
        fd.append('_csrf_token', CSRF_TOKEN);
        if (imagen) fd.append('imagen', imagen);
        const res = await fetch(`${BASE_URL}/chat/mensaje`, { method: 'POST', body: fd });
        const raw = await res.text();
        let d;
        try { d = JSON.parse(raw); }
        catch {
            // El servidor devolvió algo que no es JSON (ej. una página de error 503/502 del
            // hosting en vez de una respuesta de la app) — se loguea el detalle técnico en la
            // consola del navegador para poder diagnosticarlo después, pero al usuario nunca
            // se le muestra ese HTML/texto crudo.
            console.error(`Respuesta no-JSON de /chat/mensaje (HTTP ${res.status}):`, raw.substring(0, 1000));
            throw new Error('no_json');
        }
        typing.remove();

        if (!d.ok) {
            appendBurbuja('error', d.error || 'Error desconocido.');
        } else if (d.tipo === 'confirmacion_compra' || d.tipo === 'confirmacion_accion' || d.tipo === 'guia_ayuda') {
            chatWindow.appendChild(crearFilaConfirmacion(d.tipo, d.payload, d.mensaje_id));
            chatWindow.scrollTop = chatWindow.scrollHeight;
        } else {
            appendBurbuja(d.tipo, d.respuesta, d.sql, d.resultados, null, null, d.reporte_url);
        }
    } catch(err) {
        typing.remove();
        console.error('Error en enviar() del chat:', err);
        appendBurbuja('error', 'No se pudo conectar con el servidor. Esperá unos segundos y volvé a intentar. Si el problema sigue, avisale al administrador.');
    } finally {
        btnEnviar.disabled = false;
        chatInput.focus();
    }
}

// Acciones rápidas — envían directamente sin pasar por el input.
// El texto de un acceso directo puede traer placeholders de fecha ({HOY}/{HACE_UN_MES})
// que se resuelven recién acá, al momento del clic — así el catálogo (editable en
// "Administrar catálogo de accesos directos") no queda con una fecha fija desactualizándose.
function _fmtFechaCorta(d) {
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}
function sustituirFechasAcceso(texto) {
    if (!texto.includes('{')) return texto;
    const hoy = new Date();
    const haceUnMes = new Date(hoy);
    haceUnMes.setMonth(haceUnMes.getMonth() - 1);
    return texto
        .replace(/\{HOY\}/g, _fmtFechaCorta(hoy))
        .replace(/\{HACE_UN_MES\}/g, _fmtFechaCorta(haceUnMes));
}

document.querySelectorAll('.ejemplo-chat').forEach(btn => {
    btn.addEventListener('click', () => {
        const texto = sustituirFechasAcceso(btn.dataset.texto || btn.textContent);
        if (btn.dataset.accionInterna) {
            enviarAccionDirecta(btn.dataset.id, texto);
        } else {
            enviar(texto);
        }
    });
});

// Accesos directos de solo lectura (accion_interna): resuelven con una consulta PHP directa
// en el backend, sin pasar por Gemini — mismo choreo visual que enviar() (burbuja del
// usuario + "escribiendo..." + burbuja de respuesta), pero pegándole a un endpoint distinto
// y sin mandar imagen/archivo (no aplica a este camino).
async function enviarAccionDirecta(accesoId, textoUI) {
    appendBurbuja('user', textoUI);
    const typing = appendTyping();

    try {
        const fd = new FormData();
        fd.append('acceso_id', accesoId);
        fd.append('_csrf_token', CSRF_TOKEN);
        const res = await fetch(`${BASE_URL}/chat/accion-directa`, { method: 'POST', body: fd });
        const raw = await res.text();
        let d;
        try { d = JSON.parse(raw); }
        catch {
            console.error(`Respuesta no-JSON de /chat/accion-directa (HTTP ${res.status}):`, raw.substring(0, 1000));
            throw new Error('no_json');
        }
        typing.remove();

        if (!d.ok) {
            appendBurbuja('error', d.error || 'Error desconocido.');
        } else {
            appendBurbuja(d.tipo, d.respuesta);
        }
    } catch (err) {
        typing.remove();
        console.error('Error en enviarAccionDirecta() del chat:', err);
        appendBurbuja('error', 'No se pudo conectar con el servidor. Esperá unos segundos y volvé a intentar. Si el problema sigue, avisale al administrador.');
    }
}

// ── Accesos directos personales: cuáles de los que asignó el admin se muestran ──
const LS_ACCESOS_VISIBLES = 'dewoc-chat-accesos-visibles';

function getAccesosOcultos() {
    try { return JSON.parse(localStorage.getItem(LS_ACCESOS_VISIBLES) || '[]'); }
    catch { return []; }
}

function aplicarVisibilidadAccesos() {
    const ocultos = getAccesosOcultos();
    document.querySelectorAll('#chipsAccesos .ejemplo-chat[data-id]').forEach(chip => {
        chip.classList.toggle('d-none', ocultos.includes(chip.dataset.id));
    });
}
aplicarVisibilidadAccesos();

document.getElementById('btnAccesosDirectos')?.addEventListener('click', () => {
    const ocultos = getAccesosOcultos();
    document.querySelectorAll('.chk-acceso-visible').forEach(chk => {
        chk.checked = !ocultos.includes(chk.value);
    });
    _showModal('modalAccesosDirectos');
});

document.querySelectorAll('.chk-acceso-visible').forEach(chk => {
    chk.addEventListener('change', () => {
        let ocultos = getAccesosOcultos();
        if (chk.checked) {
            ocultos = ocultos.filter(id => id !== chk.value);
        } else if (!ocultos.includes(chk.value)) {
            ocultos.push(chk.value);
        }
        localStorage.setItem(LS_ACCESOS_VISIBLES, JSON.stringify(ocultos));
        aplicarVisibilidadAccesos();
    });
});

// ── Buscador dentro de la conversación (el input vive en el dropdown de la
// lupa del header general, creado más arriba por construirControlesHeader) ──
const chatSearch      = document.getElementById('chatSearch');
const chatSearchCount = document.getElementById('chatSearchCount');
const btnLimpiarBusqueda = document.getElementById('btnLimpiarBusqueda');

function limpiarResaltado(el) {
    el.querySelectorAll('p[data-orig]').forEach(p => { p.innerHTML = p.dataset.orig; });
}

function resaltarTexto(el, termino) {
    const regex = new RegExp('(' + termino.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    el.querySelectorAll('p.mb-0, p.mb-2').forEach(p => {
        if (!p.dataset.orig) p.dataset.orig = p.innerHTML;
        const original  = p.dataset.orig;
        const soloTexto = original.replace(/<[^>]*>/g, '');
        if (!soloTexto.toLowerCase().includes(termino.toLowerCase())) return;
        p.innerHTML = original.replace(regex, '<mark class="wa-hl">$1</mark>');
    });
}

chatSearch.addEventListener('input', () => {
    const termino = chatSearch.value.trim();
    btnLimpiarBusqueda.classList.toggle('d-none', !termino);

    const filas = chatWindow.querySelectorAll('.wa-row');
    let coincidencias = 0;

    filas.forEach(fila => {
        limpiarResaltado(fila);
        if (!termino) {
            fila.classList.remove('d-none');
            return;
        }
        const texto = fila.textContent || '';
        const coincide = texto.toLowerCase().includes(termino.toLowerCase());
        fila.classList.toggle('d-none', !coincide);
        if (coincide) {
            coincidencias++;
            resaltarTexto(fila, termino);
        }
    });

    chatSearchCount.textContent = termino ? `${coincidencias} resultado${coincidencias === 1 ? '' : 's'}` : '';
});

btnLimpiarBusqueda.addEventListener('click', () => {
    chatSearch.value = '';
    chatSearch.dispatchEvent(new Event('input'));
    chatSearch.focus();
});

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto con data-bs-toggle
// (Bootstrap sí registra esa delegación de eventos al importarse).
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(el).show();
    } else {
        let btn = document.getElementById('_trigger_' + id);
        if (!btn) {
            btn = document.createElement('button');
            btn.id = '_trigger_' + id;
            btn.type = 'button';
            btn.hidden = true;
            btn.setAttribute('data-bs-toggle', 'modal');
            btn.setAttribute('data-bs-target', '#' + id);
            document.body.appendChild(btn);
        }
        btn.click();
    }
}

// Ver SQL en modal
document.addEventListener('click', e => {
    const btn = e.target.closest('.toggle-sql');
    if (!btn) return;
    document.getElementById('modalSQLContent').textContent = btn.dataset.sql;
    _showModal('modalSQL');
});

// Enviar desde el formulario (input manual) — no existe en el visor de solo lectura
if (!SOLO_LECTURA) {
document.getElementById('chatForm').addEventListener('submit', function (e) {
    e.preventDefault();
    enviar(chatInput.value);
});
}

function appendBurbuja(tipo, texto, sql, resultados, imgSrc, pdfNombre = null, reporteUrl = null) {
    const isUser = tipo === 'user';
    const wrap   = document.createElement('div');
    wrap.className = 'wa-row' + (isUser ? ' wa-row-out' : '');

    const bubble = document.createElement('div');
    const ahora = new Date();
    const hora  = String(ahora.getHours()).padStart(2,'0') + ':' + String(ahora.getMinutes()).padStart(2,'0');

    if (isUser) {
        bubble.className = 'wa-bubble wa-bubble-out';
        let html = '';
        if (pdfNombre) {
            html += '<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:rgba(0,0,0,.08)">'
                + '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/><line x1="9" y1="9" x2="10" y2="9"/></svg>'
                + '<span class="small text-truncate">' + escHtml(pdfNombre) + '</span>'
                + '</div>';
        } else if (imgSrc) {
            html += '<img src="' + imgSrc + '" class="rounded mb-2 d-block" style="max-width:160px;max-height:100px;object-fit:cover">';
        }
        html += '<p class="mb-0 small">' + escHtml(texto).replace(/\n/g, '<br>') + '</p>';
        html += '<div class="wa-meta">' + hora + ' <span class="wa-ticks">✓✓</span></div>';
        bubble.innerHTML = html;
        wrap.appendChild(bubble);
        chatWindow.appendChild(wrap);
    } else {
        bubble.className = 'wa-bubble wa-bubble-in';
        if (tipo === 'error') bubble.classList.add('wa-bubble-error');
        if (tipo === 'accion' || tipo === 'reporte') bubble.classList.add('wa-bubble-accion');

        let html = '';
        if (sql) {
            html += '<button class="btn btn-sm btn-outline-secondary py-0 px-2 toggle-sql mb-1" data-sql="' + escHtml(sql) + '">'
                + '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> SQL</button><br>';
        }
        html += '<p class="mb-0 small">' + escHtmlLinkificado(texto).replace(/\n/g, '<br>') + '</p>';
        if (tipo === 'reporte' && reporteUrl) {
            html += '<div class="d-flex gap-2 mt-2">'
                + '<a href="' + BASE_URL + reporteUrl + '" class="btn btn-sm btn-success">Ver reporte</a>'
                + '<a href="' + BASE_URL + reporteUrl.replace('/ver?', '/exportar?') + '" class="btn btn-sm btn-outline-success">Descargar Excel</a>'
                + '</div>';
        }
        html += '<div class="wa-meta">' + hora + '</div>';
        bubble.innerHTML = html;
        wrap.appendChild(bubble);
        chatWindow.appendChild(wrap);
    }

    chatWindow.scrollTop = chatWindow.scrollHeight;
}

function appendTyping() {
    const wrap = document.createElement('div');
    wrap.className = 'wa-row';
    wrap.innerHTML = `
        <div class="wa-bubble wa-bubble-in">
            <div class="d-flex gap-1 align-items-center" style="height:18px">
                <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
            </div>
        </div>`;
    chatWindow.appendChild(wrap);
    chatWindow.scrollTop = chatWindow.scrollHeight;
    return wrap;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/**
 * Como escHtml() pero además: convierte URLs http(s):// en links clickeables (ej. avisos
 * de duplicado del chat), y **negrita** de markdown en <strong> — el prompt le pide a
 * Gemini que resalte así los montos, cantidades y fechas de sus respuestas.
 */
function escHtmlLinkificado(str) {
    return escHtml(str)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
}

// ── Tarjetas de confirmación (compra con ítems ambiguos, o acción simple) ───────
// Reemplazan el flujo de "escribí sí/no" por botones: la acción real recién se
// ejecuta en el servidor cuando el usuario confirma (ver ChatController::confirmar()).

function crearFilaConfirmacion(tipo, payload, mensajeId) {
    const wrap = document.createElement('div');
    wrap.className = 'wa-row';

    const bubble = document.createElement('div');
    bubble.className = 'wa-bubble wa-bubble-in wa-confirm-bubble';
    bubble.appendChild(
        tipo === 'guia_ayuda' ? construirTarjetaGuia(payload)
            : (tipo === 'confirmacion_compra' ? construirTarjetaCompra(payload, mensajeId) : construirTarjetaAccion(payload, mensajeId))
    );

    const ahora = new Date();
    const meta = document.createElement('div');
    meta.className = 'wa-meta';
    meta.textContent = String(ahora.getHours()).padStart(2,'0') + ':' + String(ahora.getMinutes()).padStart(2,'0');
    bubble.appendChild(meta);

    wrap.appendChild(bubble);
    return wrap;
}

function crearEstadoBadge(estado) {
    const b = document.createElement('div');
    b.className = 'small fw-semibold mt-1';
    if (estado === 'confirmado') { b.textContent = '✅ Confirmado'; b.classList.add('text-success'); }
    else if (estado === 'cancelado') { b.textContent = '❌ Cancelado'; b.classList.add('text-muted'); }
    return b;
}

// Tarjeta del help desk (mostrar_guia_ayuda): solo informativa, sin estado
// pendiente/confirmado — dos botones de acción, "Tour guiado" solo si el tema tiene uno.
function construirTarjetaGuia(payload) {
    const cont = document.createElement('div');
    cont.className = 'wa-confirm-card';

    const p = document.createElement('p');
    p.className = 'mb-2 small';
    p.innerHTML = escHtml(payload.resumen_texto || payload.titulo || '').replace(/\n/g, '<br>');
    cont.appendChild(p);

    const botones = document.createElement('div');
    botones.className = 'd-flex gap-2 flex-wrap wa-confirm-botones';

    const btnIr = document.createElement('a');
    btnIr.className = 'btn btn-sm btn-outline-primary';
    btnIr.href = payload.url;
    btnIr.textContent = '🔗 Ir ahora';
    botones.appendChild(btnIr);

    if (payload.tour_id) {
        const btnTour = document.createElement('a');
        btnTour.className = 'btn btn-sm btn-primary';
        const sep = payload.url.includes('?') ? '&' : '?';
        btnTour.href = payload.url + sep + 'tour=' + encodeURIComponent(payload.tour_id);
        btnTour.textContent = '🧭 Tour guiado';
        botones.appendChild(btnTour);
    }

    cont.appendChild(botones);
    return cont;
}

function construirTarjetaAccion(payload, mensajeId) {
    // Caso especial: el aviso diario de Lista de Compras (cron/lista_compras_diaria.php)
    // trae payload.items (uno por ítem sugerido) — se arma como checklist en vez del
    // párrafo + Confirmar/Cancelar genérico, para poder elegir cuáles agregar en vez de
    // que sea todo-o-nada. El resto de las acciones (marcar_feriado_trabajado, etc.) no
    // traen payload.items y siguen cayendo en el renderer genérico de abajo.
    if (payload.accion === 'confirmar_lista_compras_sugeridos' && Array.isArray(payload.items)) {
        return construirTarjetaListaCompras(payload, mensajeId);
    }

    const cont = document.createElement('div');
    cont.className = 'wa-confirm-card';

    const p = document.createElement('p');
    p.className = 'mb-2 small';
    p.innerHTML = escHtml(payload.resumen_texto || '').replace(/\n/g, '<br>');
    cont.appendChild(p);

    if (SOLO_LECTURA || (payload.estado || 'pendiente') !== 'pendiente') {
        cont.appendChild(crearEstadoBadge(payload.estado));
        return cont;
    }

    const botones = document.createElement('div');
    botones.className = 'd-flex gap-2 wa-confirm-botones';

    const btnConfirmar = document.createElement('button');
    btnConfirmar.type = 'button';
    btnConfirmar.className = 'btn btn-sm btn-success';
    btnConfirmar.textContent = 'Confirmar';
    btnConfirmar.addEventListener('click', () => resolverConfirmacion('confirmar', mensajeId, null, cont, botones));

    const btnCancelar = document.createElement('button');
    btnCancelar.type = 'button';
    btnCancelar.className = 'btn btn-sm btn-outline-danger';
    btnCancelar.textContent = 'Cancelar';
    btnCancelar.addEventListener('click', () => resolverConfirmacion('cancelar', mensajeId, null, cont, botones));

    botones.appendChild(btnConfirmar);
    botones.appendChild(btnCancelar);
    cont.appendChild(botones);
    return cont;
}

/**
 * Tarjeta del aviso diario de Lista de Compras — un checkbox por ítem (nombre + días de
 * stock restante/"bajo el mínimo" + stock actual con su unidad) en vez del Confirmar/
 * Cancelar genérico de todo-o-nada. "Agregar seleccionados" manda solo los ids tildados
 * como 'resolucion' (ver resolverConfirmacion() y ChatController::confirmar(), que la
 * usa para pisar args.ids antes de ejecutar). Lo que quede sin tildar sigue 'sugerido' —
 * no desaparece, se puede confirmar/descartar después desde /lista-compras.
 */
function construirTarjetaListaCompras(payload, mensajeId) {
    const cont = document.createElement('div');
    cont.className = 'wa-confirm-card';

    const titulo = document.createElement('div');
    titulo.className = 'small fw-semibold mb-2';
    titulo.textContent = '🛒 Ítems para reponer — elegí cuáles agregar:';
    cont.appendChild(titulo);

    const soloLectura = SOLO_LECTURA || (payload.estado || 'pendiente') !== 'pendiente';
    const checks = [];

    payload.items.forEach(it => {
        const fila = document.createElement('div');
        fila.className = 'form-check small mb-1';

        const chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.className = 'form-check-input';
        chk.id = `alcSug_${mensajeId}_${it.id}`;
        chk.value = it.id;
        chk.checked = true;
        chk.disabled = soloLectura;
        fila.appendChild(chk);
        checks.push(chk);

        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.setAttribute('for', chk.id);
        label.innerHTML = `<span class="fw-semibold">${escHtml(it.nombre)}</span>`
            + `<span class="text-muted"> — ${escHtml(it.detalle)} · stock: ${escHtml(it.stock_texto)}</span>`;
        fila.appendChild(label);

        cont.appendChild(fila);
    });

    if (soloLectura) {
        cont.appendChild(crearEstadoBadge(payload.estado));
        return cont;
    }

    const botones = document.createElement('div');
    botones.className = 'd-flex gap-2 wa-confirm-botones mt-2';

    const btnConfirmar = document.createElement('button');
    btnConfirmar.type = 'button';
    btnConfirmar.className = 'btn btn-sm btn-success';
    btnConfirmar.textContent = 'Agregar seleccionados';
    btnConfirmar.addEventListener('click', () => {
        const idsElegidos = checks.filter(c => c.checked).map(c => c.value);
        if (!idsElegidos.length) { showToast('Tildá al menos un ítem, o usá "Descartar todos".', 'warning'); return; }
        resolverConfirmacion('confirmar', mensajeId, { ids: idsElegidos }, cont, botones);
    });

    const btnCancelar = document.createElement('button');
    btnCancelar.type = 'button';
    btnCancelar.className = 'btn btn-sm btn-outline-danger';
    btnCancelar.textContent = 'Descartar todos';
    btnCancelar.addEventListener('click', () => resolverConfirmacion('cancelar', mensajeId, null, cont, botones));

    botones.appendChild(btnConfirmar);
    botones.appendChild(btnCancelar);
    cont.appendChild(botones);
    return cont;
}

function construirTarjetaCompra(payload, mensajeId) {
    const cont = document.createElement('div');
    cont.className = 'wa-confirm-card';

    const soloLectura = SOLO_LECTURA || (payload.estado || 'pendiente') !== 'pendiente';

    const tituloEl = document.createElement('div');
    tituloEl.className = 'small fw-semibold mb-2';
    tituloEl.textContent = '📋 Compra a registrar';
    cont.appendChild(tituloEl);

    const combo = crearComboProveedor(payload);
    combo.setDisabled(soloLectura);
    cont.appendChild(combo.el);
    cont._comboProveedor = combo;

    const header = document.createElement('div');
    header.className = 'small mb-2';
    header.innerHTML = 'Fecha: ' + escHtml(payload.fecha || '') + ' — Comprobante: ' + escHtml(payload.tipo_comprobante || '') + ' ' + escHtml(payload.nro_comprobante || '');
    cont.appendChild(header);

    if (payload.descuento_info) {
        const desc = document.createElement('div');
        desc.className = 'small text-muted mb-2';
        desc.textContent = 'Descuentos: ' + payload.descuento_info;
        cont.appendChild(desc);
    }

    const tabla = document.createElement('div');
    tabla.className = 'd-flex flex-column gap-2 mb-2';
    const filas = [];

    (payload.items || []).forEach((item, indiceOriginal) => {
        const fila = document.createElement('div');
        fila.className = 'wa-confirm-item p-2 rounded';

        const nombreWrap = document.createElement('div');
        nombreWrap.className = 'd-flex justify-content-between align-items-start gap-2 mb-1';

        const nombreLinea = document.createElement('div');
        nombreLinea.className = 'small fw-semibold';
        nombreLinea.textContent = item.nombre_ticket;
        nombreWrap.appendChild(nombreLinea);

        // Cruz roja para descartar un ítem que la IA leyó mal (ej. un código de producto
        // interpretado como si fuera otro ítem) o que el usuario no quiere cargar al
        // sistema (compra personal, etc.) — no se muestra en tarjetas ya resueltas/solo lectura.
        let btnEliminarItem = null;
        if (!soloLectura) {
            btnEliminarItem = document.createElement('button');
            btnEliminarItem.type = 'button';
            btnEliminarItem.className = 'btn btn-sm btn-outline-danger py-0 px-1 flex-shrink-0';
            btnEliminarItem.title = 'Quitar este ítem de la compra';
            btnEliminarItem.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            nombreWrap.appendChild(btnEliminarItem);
        }
        fila.appendChild(nombreWrap);

        // Los 3 estados muestran el mismo combobox editable con búsqueda libre en toda la
        // base — antes un ítem "ok" (match automático con certeza) solo mostraba un badge
        // genérico "✅ materia prima" sin decir A CUÁL ítem se vinculó, así que un match
        // automático equivocado (ej. "CHOCOLATE" del ticket vinculado sin preguntar a "Helado
        // en balde de Chocolate con Almendras", un producto totalmente distinto) pasaba
        // desapercibido y no había forma de corregirlo desde acá. Ahora "ok" arranca con el
        // nombre real precargado (nombre_resuelto, calculado en el backend) y se puede
        // cambiar como cualquier otro ítem si el match automático no es el correcto.
        let seleccionInicial = null;
        if (item.estado === 'ok') {
            const hint = document.createElement('div');
            hint.className = 'small text-muted mb-1';
            hint.textContent = '✅ vinculado automáticamente — revisá que sea el correcto:';
            fila.appendChild(hint);
            seleccionInicial = {
                id: item.item_id,
                tipo_item: item.tipo_item,
                nombre: item.nombre_resuelto || item.nombre_ticket,
                cantidad_contenida: item.cantidad_contenida,
                unidad_medida_id: item.unidad_medida_id,
            };
        } else if (item.estado === 'nuevo') {
            seleccionInicial = { id: 0, nombre: item.nombre_ticket, nuevo: true };
        }
        const comboItem = crearComboItem(item, seleccionInicial, () => {
            refrescarDetalle();
            actualizarBotonConfirmar(cont);
        });
        comboItem.setDisabled(soloLectura);
        fila.appendChild(comboItem.el);

        const camposNum = document.createElement('div');
        // flex-wrap: en pantallas angostas (celular) las 4 columnas ya no se aprietan a un
        // ancho casi nulo (el select de IVA quedaba ilegible) — pasan a 2 filas de 2 en vez
        // de eso. min-width:0 de abajo sigue evitando que la fila se desborde del contenedor
        // cuando SÍ entran las 4 en una línea (pantallas anchas).
        camposNum.className = 'd-flex flex-wrap gap-2';

        const cantCampo    = crearCampoNumerico('Cant.', item.cantidad, soloLectura, 'sm',
            'Cantidad de unidades de COMPRA (ej: cajas, bultos, botellas). El stock que se suma es Cantidad × Contenido. Si compraste sueltas (sin caja/bulto), poné acá la cantidad de piezas y dejá Contenido en 1.');
        const esFacturaA   = payload.tipo_comprobante === 'factura_a';
        const precioCampo  = crearCampoNumerico('P. Unit. (sin IVA)', item.precio_unitario, soloLectura, 'lg',
            'Precio de cada unidad de Cantidad, sin IVA. El IVA se elige aparte y se suma en la columna Importe.');
        const ivaCampo     = crearCampoIvaSelect(item.iva_porcentaje, esFacturaA);
        const importeCampo = crearCampoNumerico('Importe (con IVA)', '', soloLectura, 'lg',
            'Importe = Cantidad × P. Unit. × (1 + IVA%).');

        const cantInput    = cantCampo.input;
        const precioInput  = precioCampo.input;
        const ivaInput     = ivaCampo.input;
        const importeInput = importeCampo.input;

        // Cant./P.Unit./IVA → recalculan Importe (cant × precio × (1+iva%)). Si en vez el
        // usuario edita Importe directamente (porque la IA leyó mal el número o quiere
        // corregirlo a mano), se recalcula P.Unit. = Importe / Cant. / (1+iva%), manteniendo
        // cantidad e IVA fijos.
        [cantInput, precioInput, ivaInput].forEach(inp => {
            const recalcular = () => {
                actualizarImporte(cantInput, precioInput, ivaInput, importeInput);
                actualizarTotal(cont);
                actualizarBotonConfirmar(cont);
            };
            inp.addEventListener('input', recalcular);
            inp.addEventListener('change', recalcular);
        });
        importeInput.addEventListener('input', () => {
            const cant    = parseFloat(cantInput.value)    || 0;
            const iva     = parseFloat(ivaInput.value)     || 0;
            const importe = parseFloat(importeInput.value) || 0;
            if (cant > 0) precioInput.value = (importe / cant / (1 + iva / 100)).toFixed(4);
            actualizarTotal(cont);
            actualizarBotonConfirmar(cont);
        });

        camposNum.appendChild(cantCampo.wrap);
        camposNum.appendChild(precioCampo.wrap);
        camposNum.appendChild(ivaCampo.wrap);
        camposNum.appendChild(importeCampo.wrap);
        actualizarImporte(cantInput, precioInput, ivaInput, importeInput);

        fila.appendChild(camposNum);

        // Datos del ítem: nombre a dar de alta (solo cuando se va a CREAR un ítem nuevo —
        // nombre_ticket puede ser críptico, ej. "DEPTO1", así que se deja editar) y contenido
        // por unidad comprada + su unidad de medida — esto último aplica tanto a ítems nuevos
        // como YA EXISTENTES (ej. "Salsa de Chocolate" ya tiene cargado 500 gr — se muestra
        // precargado acá mismo por si esta compra vino en un envase distinto al habitual, sin
        // tener que ir aparte a Materias Primas). Cantidad × Contenido es lo que suma al stock
        // (ya en la unidad base), así que ambos números tienen que estar en la MISMA unidad
        // (ej: bidón de 5 litros con unidad "Mililitro" → contenida = 5000).
        let nombreInput = null, contenidaInput = null, unidadSelect = null, tipoSelect = null, genericoSelect = null;

        const detalleWrap = document.createElement('div');
        detalleWrap.className = 'mt-1 d-flex flex-column gap-1';
        fila.appendChild(detalleWrap);

        const nombreBlock = document.createElement('div');
        const nombreLabel = document.createElement('label');
        nombreLabel.className = 'wa-confirm-campo-label d-block';
        nombreLabel.textContent = 'Nombre a dar de alta';
        nombreBlock.appendChild(nombreLabel);
        nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.className = 'form-control form-control-sm';
        nombreInput.value = item.nombre_ticket;
        nombreInput.disabled = soloLectura;
        nombreBlock.appendChild(nombreInput);
        detalleWrap.appendChild(nombreBlock);

        // Tipo del ítem a crear — antes quedaba fijo en "materia prima" sin poder elegir
        // insumo o producto.
        const tipoBlock = document.createElement('div');
        const tipoLabel = document.createElement('label');
        tipoLabel.className = 'wa-confirm-campo-label d-block';
        tipoLabel.textContent = 'Tipo';
        tipoBlock.appendChild(tipoLabel);
        tipoSelect = document.createElement('select');
        tipoSelect.className = 'form-select form-select-sm';
        tipoSelect.disabled = soloLectura;
        [['materia_prima', 'Materia prima'], ['insumo', 'Insumo'], ['producto', 'Producto']].forEach(([val, label]) => {
            const opt = document.createElement('option');
            opt.value = val; opt.textContent = label;
            tipoSelect.appendChild(opt);
        });
        tipoBlock.appendChild(tipoSelect);
        detalleWrap.appendChild(tipoBlock);

        const contCantWrap = document.createElement('div');
        contCantWrap.className = 'd-flex gap-2';

        const contenidaCampo = document.createElement('div');
        contenidaCampo.className = 'wa-confirm-campo';
        const contenidaLabel = document.createElement('label');
        contenidaLabel.className = 'wa-confirm-campo-label';
        contenidaLabel.textContent = 'Contenido x unidad comprada';
        contenidaLabel.title = 'Cuántas unidades base (gramos/ml/unidades sueltas) trae CADA unidad de Cantidad. '
            + 'El stock sube en Cantidad × Contenido, y el costo de receta queda expresado por unidad base automáticamente. '
            + 'Si compraste sueltas (sin envase/caja), dejalo en 1 — no afecta nada en ese caso.';
        contenidaCampo.appendChild(contenidaLabel);
        contenidaInput = document.createElement('input');
        contenidaInput.type = 'number';
        contenidaInput.step = 'any';
        contenidaInput.className = 'form-control form-control-sm';
        contenidaInput.placeholder = 'Ej: 5000';
        contenidaInput.title = contenidaLabel.title;
        contenidaInput.disabled = soloLectura;
        contenidaCampo.appendChild(contenidaInput);
        contCantWrap.appendChild(contenidaCampo);

        const unidadCampo = document.createElement('div');
        unidadCampo.className = 'wa-confirm-campo';
        const unidadLabel = document.createElement('label');
        unidadLabel.className = 'wa-confirm-campo-label';
        unidadLabel.textContent = 'Unidad';
        unidadCampo.appendChild(unidadLabel);
        unidadSelect = document.createElement('select');
        unidadSelect.className = 'form-select form-select-sm';
        unidadSelect.disabled = soloLectura;
        const optSinUnidad = document.createElement('option');
        optSinUnidad.value = '';
        optSinUnidad.textContent = '--';
        unidadSelect.appendChild(optSinUnidad);
        (UNIDADES_MEDIDA || []).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.abreviatura;
            opt.title = u.nombre;
            unidadSelect.appendChild(opt);
        });
        unidadCampo.appendChild(unidadSelect);
        contCantWrap.appendChild(unidadCampo);
        detalleWrap.appendChild(contCantWrap);

        const ayuda = document.createElement('div');
        ayuda.className = 'wa-confirm-campo-label';
        ayuda.textContent = 'Usá la unidad mínima que vayas a consumir en las recetas (ej: gramos o mililitros, no "bidón" ni "paquete"). '
            + 'El stock final que se registra es Cantidad × Contenido — con Cantidad=2 y Contenido=500 quedan 1000 en stock.';
        detalleWrap.appendChild(ayuda);

        // Ingrediente genérico (solo aplica a materia_prima): agrupa variantes de marca bajo
        // un mismo costo, para que las recetas no queden atadas a una marca puntual. Opcional
        // — se puede asignar después desde la ficha si no se elige acá.
        const genericoBlock = document.createElement('div');
        const genericoLabel = document.createElement('label');
        genericoLabel.className = 'wa-confirm-campo-label d-block';
        genericoLabel.textContent = 'Ingrediente genérico (opcional)';
        genericoBlock.appendChild(genericoLabel);
        genericoSelect = document.createElement('select');
        genericoSelect.className = 'form-select form-select-sm';
        genericoSelect.disabled = soloLectura;
        const optSinGenerico = document.createElement('option');
        optSinGenerico.value = '';
        optSinGenerico.textContent = '— Ninguno —';
        genericoSelect.appendChild(optSinGenerico);
        (GENERICOS || []).forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id;
            opt.textContent = g.nombre + (g.unidad ? ` (${g.unidad})` : '');
            genericoSelect.appendChild(opt);
        });
        genericoBlock.appendChild(genericoSelect);
        detalleWrap.appendChild(genericoBlock);

        // Decide qué mostrar y con qué valores precargar, según la selección actual del
        // combobox (item.estado "ambiguo"/"nuevo") o el estado fijo del ítem ("ok"): alta
        // nueva → nombre + tipo (elegible) + contenida/unidad/genérico si el tipo elegido es
        // materia_prima; ítem existente tipo materia_prima → solo contenida/unidad,
        // precargados desde la base; ítem existente insumo/producto → no aplica, se oculta
        // todo el bloque.
        function refrescarDetalle() {
            let esNueva, tipoActual, contenidaVal, unidadVal, sinResolver;

            if (comboItem) {
                const sel = comboItem.getSeleccion();
                sinResolver = !sel;
                esNueva     = !!(sel && sel.nuevo);
                tipoActual   = esNueva ? tipoSelect.value : (sel ? sel.tipo_item : null);
                contenidaVal = (sel && !sel.nuevo) ? sel.cantidad_contenida : null;
                unidadVal    = (sel && !sel.nuevo) ? sel.unidad_medida_id   : null;
                if (esNueva) nombreInput.value = sel.nombre;
            } else {
                sinResolver  = false;
                esNueva      = false; // "ok" nunca entra acá — siempre es un ítem ya existente
                tipoActual   = item.tipo_item;
                contenidaVal = item.cantidad_contenida;
                unidadVal    = item.unidad_medida_id;
            }

            nombreBlock.style.display = esNueva ? '' : 'none';
            tipoBlock.style.display   = esNueva ? '' : 'none';

            const aplicaContenida = tipoActual === 'materia_prima';
            contCantWrap.style.display    = (aplicaContenida && !sinResolver) ? '' : 'none';
            ayuda.style.display           = (aplicaContenida && !sinResolver) ? '' : 'none';
            genericoBlock.style.display   = (esNueva && aplicaContenida) ? '' : 'none';

            if (!esNueva && !sinResolver && aplicaContenida) {
                contenidaInput.value = (contenidaVal !== null && contenidaVal !== undefined) ? contenidaVal : '';
                unidadSelect.value   = (unidadVal !== null && unidadVal !== undefined) ? String(unidadVal) : '';
            } else if (esNueva) {
                // Alta nueva: se deja vacío para que el usuario lo complete (no hay historial).
                contenidaInput.value = '';
                unidadSelect.value = '';
            }
        }
        refrescarDetalle();

        // Marcan en rojo (clase is-invalid, ya estilizada por el tema) los campos
        // obligatorios para dar de alta un ítem nuevo si todavía están vacíos, y
        // dejan/sacan habilitado el botón "Confirmar" acorde.
        nombreInput.addEventListener('input', () => actualizarBotonConfirmar(cont));
        contenidaInput.addEventListener('input', () => actualizarBotonConfirmar(cont));
        unidadSelect.addEventListener('change', () => actualizarBotonConfirmar(cont));
        tipoSelect.addEventListener('change', () => { refrescarDetalle(); actualizarBotonConfirmar(cont); });

        tabla.appendChild(fila);
        const filaObj = { fila, item, indiceOriginal, comboItem, cantInput, precioInput, ivaInput, importeInput, nombreInput, contenidaInput, unidadSelect, tipoSelect, genericoSelect };
        filas.push(filaObj);

        if (btnEliminarItem) {
            btnEliminarItem.addEventListener('click', () => {
                fila.remove();
                const idx = filas.indexOf(filaObj);
                if (idx !== -1) filas.splice(idx, 1);
                actualizarTotal(cont);
                actualizarBotonConfirmar(cont);
            });
        }
    });

    cont.appendChild(tabla);
    cont._filasCompra = filas;

    const totalCalculadoEl = document.createElement('div');
    totalCalculadoEl.className = 'small wa-confirm-total-calc';
    totalCalculadoEl.title = 'Suma de (Cantidad × P. Unit. × (1 + IVA%)) de todos los ítems de la tarjeta.';
    cont.appendChild(totalCalculadoEl);

    let ajusteInput = null;
    let totalFinalEl = null;

    if (payload.total_comprobante !== null && payload.total_comprobante !== undefined) {
        const comprobanteEl = document.createElement('div');
        comprobanteEl.className = 'small text-muted';
        comprobanteEl.textContent = 'Total según comprobante: $' + fmtMoney(payload.total_comprobante);
        cont.appendChild(comprobanteEl);

        const ajusteWrap = document.createElement('div');
        ajusteWrap.className = 'd-flex align-items-center gap-2 my-1';
        const ajusteLabel = document.createElement('label');
        ajusteLabel.className = 'small text-muted mb-0';
        ajusteLabel.textContent = 'Ajuste (redondeo/descuento):';
        ajusteInput = document.createElement('input');
        ajusteInput.type = 'number';
        ajusteInput.step = 'any';
        ajusteInput.className = 'form-control form-control-sm';
        ajusteInput.style.maxWidth = '110px';
        ajusteInput.disabled = soloLectura;
        ajusteWrap.appendChild(ajusteLabel);
        ajusteWrap.appendChild(ajusteInput);
        cont.appendChild(ajusteWrap);

        totalFinalEl = document.createElement('div');
        totalFinalEl.className = 'small fw-semibold wa-confirm-total-final';
        cont.appendChild(totalFinalEl);

        ajusteInput.addEventListener('input', () => actualizarTotalFinal(cont));
    }

    cont._totalComprobante = payload.total_comprobante ?? null;
    cont._ajusteAplicado   = (payload.ajuste_aplicado === undefined) ? null : payload.ajuste_aplicado;
    cont._ajusteInput      = ajusteInput;
    cont._totalFinalEl     = totalFinalEl;
    actualizarTotal(cont);

    let metodoSelect = null;
    const metodoAplicado = payload.metodo_pago_aplicado !== undefined ? payload.metodo_pago_aplicado : payload.metodo_pago;

    let origenRadiosWrap = null, socioSelect = null, wrapSocio = null;

    if (soloLectura) {
        const pago = document.createElement('div');
        pago.className = 'small text-muted mb-2';
        let pagoTexto = 'Pago: ' + (metodoAplicado ? fmtMetodoPago(metodoAplicado) : 'sin especificar');
        const origenAplicado   = payload.origen_pago_aplicado  !== undefined ? payload.origen_pago_aplicado  : payload.origen_pago;
        const socioIdAplicado  = payload.socio_id_aplicado     !== undefined ? payload.socio_id_aplicado     : payload.socio_id;
        if (origenAplicado === 'socio' && socioIdAplicado) {
            const s = (SOCIOS || []).find(x => String(x.id) === String(socioIdAplicado));
            pagoTexto += ' — Aporte de ' + (s ? s.nombre : 'socio');
        }
        pago.textContent = pagoTexto;
        cont.appendChild(pago);
    } else {
        const pagoWrap = document.createElement('div');
        pagoWrap.className = 'mb-2';
        const pagoLabel = document.createElement('label');
        pagoLabel.className = 'wa-confirm-campo-label d-block';
        pagoLabel.textContent = 'Forma de pago';
        pagoWrap.appendChild(pagoLabel);

        metodoSelect = document.createElement('select');
        metodoSelect.className = 'form-select form-select-sm';
        [
            ['', 'Sin especificar (queda pendiente de pago)'],
            ['efectivo', 'Efectivo'],
            ['transferencia', 'Transferencia'],
            ['debito', 'Débito'],
            ['mercado_pago', 'Mercado Pago'],
            ['cheque', 'Cheque'],
            ['otro', 'Otro'],
        ].forEach(([val, texto]) => {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = texto;
            if (val === (payload.metodo_pago || '')) opt.selected = true;
            metodoSelect.appendChild(opt);
        });
        pagoWrap.appendChild(metodoSelect);
        cont.appendChild(pagoWrap);

        // Origen del pago: Empresa o Aporte de socio (mismo patrón que compras/form.php y
        // pagos-proveedores/form.php) — quién puso la plata para lo que se está registrando.
        const origenWrap = document.createElement('div');
        origenWrap.className = 'mb-2';
        const origenLabel = document.createElement('label');
        origenLabel.className = 'wa-confirm-campo-label d-block';
        origenLabel.textContent = 'Origen del pago';
        origenWrap.appendChild(origenLabel);

        origenRadiosWrap = document.createElement('div');
        origenRadiosWrap.className = 'd-flex gap-3';
        const nombreRadio  = 'origen_' + mensajeId;
        const origenActual = payload.origen_pago === 'socio' ? 'socio' : 'empresa';

        [['empresa', 'Empresa'], ['socio', 'Aporte de socio']].forEach(([val, texto]) => {
            const optWrap = document.createElement('div');
            optWrap.className = 'form-check form-check-inline mb-0';
            const input = document.createElement('input');
            input.type = 'radio';
            input.className = 'form-check-input';
            input.name = nombreRadio;
            input.value = val;
            input.id = nombreRadio + '_' + val;
            input.checked = origenActual === val;
            const lbl = document.createElement('label');
            lbl.className = 'form-check-label small';
            lbl.setAttribute('for', input.id);
            lbl.textContent = texto;
            optWrap.appendChild(input);
            optWrap.appendChild(lbl);
            origenRadiosWrap.appendChild(optWrap);
        });
        origenWrap.appendChild(origenRadiosWrap);

        wrapSocio = document.createElement('div');
        wrapSocio.className = 'mt-1' + (origenActual === 'socio' ? '' : ' d-none');
        socioSelect = document.createElement('select');
        socioSelect.className = 'form-select form-select-sm';
        const optNone = document.createElement('option');
        optNone.value = '';
        optNone.textContent = '— Seleccionar socio —';
        socioSelect.appendChild(optNone);
        (SOCIOS || []).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.nombre;
            if (String(payload.socio_id || '') === String(s.id)) opt.selected = true;
            socioSelect.appendChild(opt);
        });
        wrapSocio.appendChild(socioSelect);
        origenWrap.appendChild(wrapSocio);

        origenRadiosWrap.addEventListener('change', () => {
            const val = origenRadiosWrap.querySelector('input:checked')?.value;
            wrapSocio.classList.toggle('d-none', val !== 'socio');
        });

        cont.appendChild(origenWrap);
    }

    if (soloLectura) {
        cont.appendChild(crearEstadoBadge(payload.estado));
        return cont;
    }

    const acciones = document.createElement('div');
    acciones.className = 'd-flex gap-2 wa-confirm-botones';

    const btnEditar = document.createElement('button');
    btnEditar.type = 'button';
    btnEditar.className = 'btn btn-sm btn-outline-secondary';
    btnEditar.textContent = 'Editar';
    btnEditar.addEventListener('click', () => {
        const editando = btnEditar.classList.toggle('active');
        filas.forEach(f => {
            f.cantInput.readOnly = !editando;
            f.precioInput.readOnly = !editando;
            // <select> no tiene atributo readOnly — se bloquea con disabled. Los ítems que
            // no son Factura A quedan bloqueados siempre (dataset.bloqueadoSiempre), sin
            // importar el modo edición: no hay nada para elegir, el IVA ya está incluido.
            f.ivaInput.disabled = !editando || f.ivaInput.dataset.bloqueadoSiempre === '1';
            f.importeInput.readOnly = !editando;
        });
        btnEditar.textContent = editando ? 'Listo' : 'Editar';
    });

    const btnConfirmar = document.createElement('button');
    btnConfirmar.type = 'button';
    btnConfirmar.className = 'btn btn-sm btn-success';
    btnConfirmar.textContent = 'Confirmar';
    btnConfirmar.addEventListener('click', () => {
        const resolucion = filas.map(f => {
            const r = {
                // Índice del ítem en payload.items tal como lo mandó la IA — NO la posición
                // en este array (que se corre cuando se borra un ítem con la cruz roja). El
                // backend usa este índice para saber qué ítems quedaron y cuáles hay que
                // saltear, en vez de asumir alineación 1 a 1 por posición.
                indice_original: f.indiceOriginal,
                cantidad: parseFloat(f.cantInput.value) || 0,
                precio_unitario: parseFloat(f.precioInput.value) || 0,
                iva_porcentaje: parseFloat(f.ivaInput.value) || 0,
            };
            if (f.comboItem) {
                const sel = f.comboItem.getSeleccion();
                if (sel && sel.nuevo) {
                    r.nuevo = true;
                    if (f.tipoSelect) r.tipo_item = f.tipoSelect.value;
                    if (f.genericoSelect && f.genericoSelect.value !== '') {
                        r.ingrediente_generico_id = parseInt(f.genericoSelect.value, 10);
                    }
                } else if (sel) {
                    r.item_id = sel.id;
                    r.tipo_item = sel.tipo_item;
                }
            }
            if (f.contenidaInput && f.contenidaInput.value !== '') {
                r.cantidad_contenida = parseFloat(f.contenidaInput.value) || null;
            }
            if (f.unidadSelect && f.unidadSelect.value !== '') {
                r.unidad_medida_id = parseInt(f.unidadSelect.value, 10);
            }
            if (f.nombreInput && f.nombreInput.value.trim() !== '') {
                r.nombre_item = f.nombreInput.value.trim();
            }
            return r;
        });
        const ajusteVal = cont._ajusteInput ? (parseFloat(cont._ajusteInput.value) || 0) : 0;
        const metodoVal = metodoSelect ? metodoSelect.value : null;
        const origenVal = origenRadiosWrap ? (origenRadiosWrap.querySelector('input:checked')?.value || 'empresa') : null;
        const socioVal  = (origenVal === 'socio' && socioSelect) ? socioSelect.value : null;
        resolverConfirmacion('confirmar', mensajeId, resolucion, cont, acciones, combo.getSeleccion(), ajusteVal, metodoVal, origenVal, socioVal);
    });

    const btnCancelar = document.createElement('button');
    btnCancelar.type = 'button';
    btnCancelar.className = 'btn btn-sm btn-outline-danger';
    btnCancelar.textContent = 'Cancelar';
    btnCancelar.addEventListener('click', () => resolverConfirmacion('cancelar', mensajeId, null, cont, acciones));

    acciones.appendChild(btnEditar);
    acciones.appendChild(btnConfirmar);
    acciones.appendChild(btnCancelar);
    cont.appendChild(acciones);

    actualizarBotonConfirmar(cont);
    return cont;
}

function crearCampoNumerico(etiqueta, valor, soloLectura, tamano = '', tooltip = '') {
    const wrap = document.createElement('div');
    wrap.className = 'wa-confirm-campo' + (tamano ? ' wa-confirm-campo-' + tamano : '');

    const label = document.createElement('label');
    label.className = 'wa-confirm-campo-label';
    label.textContent = etiqueta;
    if (tooltip) label.title = tooltip;
    wrap.appendChild(label);

    const inp = document.createElement('input');
    inp.type = 'number';
    inp.step = 'any';
    inp.className = 'form-control form-control-sm';
    inp.value = valor;
    inp.title = tooltip || etiqueta;
    inp.readOnly = true;
    inp.disabled = soloLectura;
    wrap.appendChild(inp);

    return { wrap, input: inp };
}

// Alícuota de IVA para Factura A: dropdown con las alícuotas reales de AR (no un número
// libre) para que el usuario no pueda tipear un valor que no existe fiscalmente. Se
// precarga con la sugerencia calculada en el backend (armarPayloadConfirmacionCompra),
// pero queda editable por línea para los casos de facturas con alícuotas mezcladas.
// Para el resto de comprobantes (B/C, ticket, remito) el IVA ya viene incluido en el
// precio — se muestra bloqueado en "Incluido" en vez de dejar elegir una alícuota que no
// se puede discriminar.
function crearCampoIvaSelect(valorSugerido, esFacturaA) {
    const wrap = document.createElement('div');
    wrap.className = 'wa-confirm-campo wa-confirm-campo-sm';

    const label = document.createElement('label');
    label.className = 'wa-confirm-campo-label';
    label.textContent = 'IVA %';
    wrap.appendChild(label);

    const sel = document.createElement('select');
    sel.className = 'form-select form-select-sm';

    if (esFacturaA) {
        const alicuotas = [0, 10.5, 21, 27];
        alicuotas.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = (v % 1 === 0 ? v : v.toFixed(1)) + '%';
            sel.appendChild(opt);
        });
        const masCercana = alicuotas.reduce((a, b) => Math.abs(b - (valorSugerido || 0)) < Math.abs(a - (valorSugerido || 0)) ? b : a);
        sel.value = masCercana;
        sel.title = 'Alícuota discriminada de la factura — sugerida por el sistema a partir del total del comprobante. Revisala antes de confirmar (puede haber ítems a una alícuota distinta).';
        sel.dataset.bloqueadoSiempre = '0';
    } else {
        const opt = document.createElement('option');
        opt.value = '0';
        opt.textContent = 'Incluido';
        sel.appendChild(opt);
        sel.value = '0';
        sel.title = 'Este tipo de comprobante ya incluye el IVA en el precio — no se discrimina por separado.';
        sel.dataset.bloqueadoSiempre = '1';
    }
    // Arranca bloqueado igual que el resto de los campos (readOnly=true) hasta apretar
    // "Editar" — ver el toggle de btnEditar, que respeta bloqueadoSiempre.
    sel.disabled = true;
    wrap.appendChild(sel);

    return { wrap, input: sel };
}

function actualizarImporte(cantInput, precioInput, ivaInput, importeInput) {
    const cant   = parseFloat(cantInput.value)   || 0;
    const precio = parseFloat(precioInput.value) || 0;
    const iva    = parseFloat(ivaInput.value)    || 0;
    importeInput.value = (cant * precio * (1 + iva / 100)).toFixed(2);
}

// ── Combobox de proveedor: input con búsqueda + "Crear nuevo" ──────────────────
let _PROV_DD = null;

function _provDdEnsure() {
    if (_PROV_DD) return _PROV_DD;
    _PROV_DD = document.createElement('div');
    _PROV_DD.className = 'wa-prov-dd';
    document.body.appendChild(_PROV_DD);
    document.addEventListener('mousedown', e => {
        if (_PROV_DD.style.display === 'none' || !_PROV_DD.style.display) return;
        if (!_PROV_DD.contains(e.target) && !e.target.closest('.wa-prov-input') && !e.target.closest('.wa-prov-arrow')) {
            _PROV_DD.style.display = 'none';
        }
    });
    return _PROV_DD;
}

function crearComboProveedor(payload) {
    const wrap = document.createElement('div');
    wrap.className = 'mb-2';

    const label = document.createElement('label');
    label.className = 'wa-confirm-campo-label d-block';
    label.textContent = 'Proveedor';
    wrap.appendChild(label);

    const inputWrap = document.createElement('div');
    inputWrap.className = 'position-relative';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm wa-prov-input';
    input.autocomplete = 'off';
    input.value = payload.proveedor_nombre || '';
    inputWrap.appendChild(input);

    const arrow = document.createElement('button');
    arrow.type = 'button';
    arrow.className = 'btn btn-sm btn-link wa-prov-arrow';
    arrow.textContent = '▾';
    inputWrap.appendChild(arrow);

    wrap.appendChild(inputWrap);

    let seleccion = { id: payload.proveedor_id || 0, nombre: payload.proveedor_nombre || '' };
    let timer;

    function mostrarOpciones(items, textoActual) {
        const dd = _provDdEnsure();
        const rect = input.getBoundingClientRect();
        dd.style.top   = (rect.bottom + 2) + 'px';
        dd.style.left  = rect.left + 'px';
        dd.style.width = Math.max(rect.width, 260) + 'px';

        const q = (textoActual || '').trim();

        // "Crear nuevo" siempre va primero — con el texto tipeado si hay, genérico si no.
        let html = `<div class="wa-prov-opt wa-prov-opt-nuevo">➕ ${q ? `Crear nuevo: "${escHtml(q)}"` : 'Crear nuevo…'}</div>`;

        html += (items || []).map(p => `
            <div class="wa-prov-opt" data-id="${p.id}" data-nombre="${escHtml(p.razon_social)}">
                <div class="fw-semibold">${escHtml(p.razon_social)}</div>
                ${p.cuit ? `<div class="small opacity-75">${escHtml(p.cuit)}</div>` : ''}
            </div>`).join('');

        if (!items || !items.length) {
            html += '<div class="p-2 opacity-65">Sin proveedores que coincidan.</div>';
        }

        dd.innerHTML = html;
        dd.style.display = 'block';

        dd.querySelectorAll('.wa-prov-opt[data-id]').forEach(opt => {
            opt.addEventListener('mousedown', e => {
                e.preventDefault();
                seleccion = { id: parseInt(opt.dataset.id, 10), nombre: opt.dataset.nombre };
                input.value = seleccion.nombre;
                dd.style.display = 'none';
            });
        });
        dd.querySelector('.wa-prov-opt-nuevo').addEventListener('mousedown', e => {
            e.preventDefault();
            seleccion = { id: 0, nombre: q };
            dd.style.display = 'none';
            if (!q) input.focus();
        });
    }

    // Token de petición: evita que una búsqueda anterior más lenta pise el resultado de la
    // más reciente si llega tarde (puede pasar contra la base remota).
    let ultimaPeticion = 0;
    async function buscar(q) {
        const miPeticion = ++ultimaPeticion;
        try {
            const url = `${BASE_URL}/chat/buscar-proveedores?q=${encodeURIComponent(q)}`;
            const data = await fetch(url).then(r => r.json());
            if (miPeticion !== ultimaPeticion) return;
            mostrarOpciones(data, q);
        } catch {}
    }

    input.addEventListener('input', () => {
        seleccion = { id: 0, nombre: input.value.trim() };
        clearTimeout(timer);
        timer = setTimeout(() => buscar(input.value.trim()), 280);
    });
    input.addEventListener('focus', () => buscar(input.value.trim()));
    arrow.addEventListener('mousedown', e => {
        e.preventDefault();
        input.focus();
        buscar(input.value.trim());
    });

    return {
        el: wrap,
        getSeleccion: () => seleccion,
        setDisabled: v => { input.disabled = v; arrow.disabled = v; },
    };
}

/**
 * Combobox de ítem (materia prima / insumo / producto) para una línea de la tarjeta de
 * compra cuando el ítem no quedó resuelto con certeza ("ambiguo") o la IA no lo encontró
 * ("nuevo"): busca en TODA la base (no solo las 2-3 sugerencias de la IA), para que el
 * usuario pueda encontrar el ítem correcto en vez de verse obligado a elegir una sugerencia
 * equivocada o crear un duplicado. Arranca mostrando las sugerencias de la IA (item.opciones)
 * antes de tipear; al tipear, busca contra /chat/buscar-items.
 */
function crearComboItem(item, seleccionInicial, onCambiar) {
    const wrap = document.createElement('div');
    wrap.className = 'position-relative mb-1';

    const inputWrap = document.createElement('div');
    inputWrap.className = 'position-relative';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm wa-prov-input';
    input.autocomplete = 'off';
    input.placeholder = 'Buscar por nombre (materia prima, insumo o producto)...';
    input.value = seleccionInicial ? seleccionInicial.nombre : '';
    inputWrap.appendChild(input);

    const arrow = document.createElement('button');
    arrow.type = 'button';
    arrow.className = 'btn btn-sm btn-link wa-prov-arrow';
    arrow.textContent = '▾';
    inputWrap.appendChild(arrow);

    wrap.appendChild(inputWrap);

    let seleccion = seleccionInicial || null;
    let timer;

    function render(items, q) {
        const dd = _provDdEnsure();
        const rect = input.getBoundingClientRect();
        dd.style.top   = (rect.bottom + 2) + 'px';
        dd.style.left  = rect.left + 'px';
        dd.style.width = Math.max(rect.width, 280) + 'px';

        let html = `<div class="wa-prov-opt wa-prov-opt-nuevo">➕ ${q ? `Crear nuevo: "${escHtml(q)}"` : 'Crear nuevo…'}</div>`;
        html += (items || []).map(op => `
            <div class="wa-prov-opt" data-id="${op.id}" data-tipo="${op.tipo_item}" data-nombre="${escHtml(op.nombre)}"
                 data-contenida="${op.cantidad_contenida ?? ''}" data-unidad="${op.unidad_medida_id ?? ''}">
                <div class="fw-semibold">${escHtml(op.nombre)}</div>
                <div class="small opacity-75">${String(op.tipo_item).replace('_',' ')}</div>
            </div>`).join('');
        if (!items || !items.length) {
            html += '<div class="p-2 opacity-65">Sin coincidencias — probá con otra palabra o creá el ítem.</div>';
        }

        dd.innerHTML = html;
        dd.style.display = 'block';

        dd.querySelectorAll('.wa-prov-opt[data-id]').forEach(opt => {
            opt.addEventListener('mousedown', e => {
                e.preventDefault();
                seleccion = {
                    id: parseInt(opt.dataset.id, 10),
                    tipo_item: opt.dataset.tipo,
                    nombre: opt.dataset.nombre,
                    cantidad_contenida: opt.dataset.contenida !== '' ? parseFloat(opt.dataset.contenida) : null,
                    unidad_medida_id: opt.dataset.unidad !== '' ? parseInt(opt.dataset.unidad, 10) : null,
                };
                input.value = seleccion.nombre;
                dd.style.display = 'none';
                onCambiar && onCambiar();
            });
        });
        dd.querySelector('.wa-prov-opt-nuevo').addEventListener('mousedown', e => {
            e.preventDefault();
            seleccion = { id: 0, nombre: q || item.nombre_ticket, nuevo: true };
            input.value = seleccion.nombre;
            dd.style.display = 'none';
            onCambiar && onCambiar();
        });
    }

    // Token de petición: si el usuario tipeó de nuevo antes de que responda una búsqueda
    // anterior, esa respuesta llega tarde y no debe pisar el resultado de la más reciente
    // (con la base remota, una búsqueda puede tardar más que la siguiente y llegar después).
    let ultimaPeticion = 0;
    async function buscar(q) {
        const miPeticion = ++ultimaPeticion;
        if (!q) { render(item.opciones || [], ''); return; }
        // Aviso inmediato de que la búsqueda de ESTE combo está en curso — sin esto, mientras
        // responde el servidor el desplegable compartido sigue mostrando lo último que se
        // buscó en OTRO ítem de la tarjeta, y parece que no está pasando nada.
        const dd = _provDdEnsure();
        const rect = input.getBoundingClientRect();
        dd.style.top   = (rect.bottom + 2) + 'px';
        dd.style.left  = rect.left + 'px';
        dd.style.width = Math.max(rect.width, 280) + 'px';
        dd.innerHTML   = '<div class="p-2 opacity-65">Buscando…</div>';
        dd.style.display = 'block';
        try {
            const url = `${BASE_URL}/chat/buscar-items?q=${encodeURIComponent(q)}`;
            const data = await fetch(url).then(r => r.json());
            if (miPeticion !== ultimaPeticion) return;
            render(data, q);
        } catch {
            if (miPeticion === ultimaPeticion) render([], q);
        }
    }

    input.addEventListener('input', () => {
        seleccion = null;
        clearTimeout(timer);
        timer = setTimeout(() => buscar(input.value.trim()), 280);
        onCambiar && onCambiar();
    });
    input.addEventListener('focus', () => buscar(input.value.trim()));
    arrow.addEventListener('mousedown', e => {
        e.preventDefault();
        input.focus();
        buscar(input.value.trim());
    });

    return {
        el: wrap,
        getSeleccion: () => seleccion,
        setDisabled: v => { input.disabled = v; arrow.disabled = v; },
        marcarInvalido: v => input.classList.toggle('is-invalid', v),
    };
}

/**
 * Valida una fila de la confirmación de compra y marca en rojo (is-invalid) los
 * campos obligatorios que todavía faltan: la selección del ítem (si es ambiguo) y,
 * cuando la fila da de alta un ítem nuevo, su nombre, cantidad contenida y unidad
 * de medida. Devuelve true si la fila está completa.
 */
function validarFilaCompra(f) {
    let valida = true;

    // Precio en $0: casi siempre es la IA avisando que no pudo leer el precio en la foto
    // (ver system prompt: "si el precio es ilegible, usá 0 y avisalo") — se marca en rojo
    // para que no pase desapercibido entre varios ítems, y bloquea Confirmar hasta que se
    // corrija a mano (con "Editar") o se borre el ítem con la cruz roja.
    if (f.precioInput) {
        const precioEnCero = !(parseFloat(f.precioInput.value) > 0);
        f.precioInput.classList.toggle('is-invalid', precioEnCero);
        if (precioEnCero) valida = false;
    }

    let seleccionCombo = null;
    if (f.comboItem) {
        seleccionCombo = f.comboItem.getSeleccion();
        const faltaSeleccion = !seleccionCombo;
        f.comboItem.marcarInvalido(faltaSeleccion);
        if (faltaSeleccion) valida = false;
    }

    const esAltaNueva = f.comboItem ? !!(seleccionCombo && seleccionCombo.nuevo) : false;

    if (esAltaNueva) {
        if (f.nombreInput) {
            const falta = f.nombreInput.value.trim() === '';
            f.nombreInput.classList.toggle('is-invalid', falta);
            if (falta) valida = false;
        }
        // Contenido x unidad y Unidad solo son obligatorios si el tipo elegido es
        // materia_prima (insumo/producto no tienen ese concepto).
        const esMateriaPrima = !f.tipoSelect || f.tipoSelect.value === 'materia_prima';
        if (f.contenidaInput) {
            const falta = esMateriaPrima && !(parseFloat(f.contenidaInput.value) > 0);
            f.contenidaInput.classList.toggle('is-invalid', falta);
            if (falta) valida = false;
        }
        if (f.unidadSelect) {
            const falta = esMateriaPrima && f.unidadSelect.value === '';
            f.unidadSelect.classList.toggle('is-invalid', falta);
            if (falta) valida = false;
        }
    } else {
        // No es alta nueva (ítem existente ya resuelto): sacar cualquier marca roja
        // que haya quedado de un estado anterior (ej. el usuario probó "Crear nuevo" y volvió atrás).
        if (f.nombreInput)    f.nombreInput.classList.remove('is-invalid');
        if (f.contenidaInput) f.contenidaInput.classList.remove('is-invalid');
        if (f.unidadSelect)   f.unidadSelect.classList.remove('is-invalid');
    }

    // Resalta toda la fila (no solo el borde fino de cada campo) mientras necesite acción del
    // usuario, para que salte a la vista entre ítems ya resueltos.
    if (f.fila) f.fila.classList.toggle('wa-confirm-requiere-accion', !valida);

    return valida;
}

function actualizarBotonConfirmar(cont) {
    const btn = cont.querySelector('.wa-confirm-botones .btn-success');
    if (!btn) return;
    const filas = cont._filasCompra || [];
    // Sin ítems (se borraron todos con la cruz roja) no hay nada para registrar.
    btn.disabled = filas.length === 0 || !filas.map(validarFilaCompra).every(Boolean);
}

function fmtMoney(n) {
    return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtMetodoPago(m) {
    return ({
        efectivo: 'Efectivo', transferencia: 'Transferencia', debito: 'Débito',
        mercado_pago: 'Mercado Pago', cheque: 'Cheque', otro: 'Otro',
    })[m] || m;
}

function actualizarTotal(cont) {
    const calcEl = cont.querySelector('.wa-confirm-total-calc');
    if (!calcEl || !cont._filasCompra) return;
    let total = 0;
    cont._filasCompra.forEach(f => {
        const cant   = parseFloat(f.cantInput.value)   || 0;
        const precio = parseFloat(f.precioInput.value) || 0;
        const iva    = parseFloat(f.ivaInput.value)    || 0;
        total += cant * precio * (1 + iva / 100);
    });
    cont._totalCalculado = total;
    calcEl.textContent = 'Total calculado: $' + fmtMoney(total);

    if (cont._ajusteInput) {
        if (cont._ajusteAplicado !== null) {
            // Tarjeta ya resuelta: mostrar el ajuste que realmente se aplicó, no recalcular.
            cont._ajusteInput.value = Number(cont._ajusteAplicado).toFixed(2);
        } else if (document.activeElement !== cont._ajusteInput) {
            const diff = (cont._totalComprobante ?? total) - total;
            cont._ajusteInput.value = diff.toFixed(2);
        }
    }
    actualizarTotalFinal(cont);
}

function actualizarTotalFinal(cont) {
    if (!cont._totalFinalEl) return;
    const ajuste = cont._ajusteInput ? (parseFloat(cont._ajusteInput.value) || 0) : 0;
    const total  = (cont._totalCalculado || 0) + ajuste;
    cont._totalFinalEl.textContent = 'Total final a registrar: $' + fmtMoney(total);
}

async function resolverConfirmacion(accion, mensajeId, resolucion, cont, botonesEl, proveedor = null, ajuste = null, metodoPago = null, origenPago = null, socioId = null) {
    botonesEl.querySelectorAll('button').forEach(b => b.disabled = true);
    try {
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_TOKEN);
        fd.append('mensaje_id', mensajeId);
        if (resolucion) fd.append('resolucion', JSON.stringify(resolucion));
        if (ajuste !== null && ajuste !== undefined) fd.append('ajuste', ajuste);
        if (proveedor)  fd.append('proveedor', JSON.stringify(proveedor));
        if (metodoPago !== null && metodoPago !== undefined) fd.append('metodo_pago', metodoPago);
        if (origenPago === 'socio' && socioId) { fd.append('origen_pago', 'socio'); fd.append('socio_id', socioId); }
        const res = await fetch(`${BASE_URL}/chat/${accion}`, { method: 'POST', body: fd });
        const raw = await res.text();
        let d;
        try { d = JSON.parse(raw); }
        catch {
            console.error(`Respuesta no-JSON de /chat/${accion} (HTTP ${res.status}):`, raw.substring(0, 1000));
            throw new Error('no_json');
        }

        if (!d.ok) {
            appendBurbuja('error', d.error || 'No se pudo procesar la confirmación.');
            botonesEl.querySelectorAll('button').forEach(b => b.disabled = false);
            return;
        }

        if (accion === 'cancelar') {
            botonesEl.remove();
            cont.appendChild(crearEstadoBadge('cancelado'));
            return;
        }

        // confirmar
        if (d.exito) {
            botonesEl.remove();
            cont.appendChild(crearEstadoBadge('confirmado'));
        } else {
            botonesEl.querySelectorAll('button').forEach(b => b.disabled = false);
        }
        appendBurbuja(d.exito ? 'accion' : 'error', d.respuesta);
    } catch (err) {
        console.error('Error en resolverConfirmacion() del chat:', err);
        appendBurbuja('error', 'No se pudo conectar con el servidor. Esperá unos segundos y volvé a intentar. Si el problema sigue, avisale al administrador.');
        botonesEl.querySelectorAll('button').forEach(b => b.disabled = false);
    }
}

if (!SOLO_LECTURA) {
chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('chatForm').requestSubmit(); }
});
}

// ── Archivo compartido desde WhatsApp (Web Share Target) ────────────────────────
if (SHARED_FILE && SHARED_FILE.base64) {
    try {
        const raw  = atob(SHARED_FILE.base64);
        const arr  = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        const blob = new Blob([arr], { type: SHARED_FILE.mime });
        const file = new File([blob], SHARED_FILE.nombre, { type: SHARED_FILE.mime });
        usarArchivo(file);
        setTimeout(() => enviar(''), 500);
    } catch (e) {
        console.error('Error al cargar archivo compartido:', e);
    }
}

}); // fin window.addEventListener('load', ...)
</script>

<style>
/* ══════════════════════════════════════════════════════════════════════════
   Chat a pantalla completa: cancela el padding del <div class="container-fluid
   py-4"> del layout general (que en esta app es 0.9375rem, o 2.8125rem desde
   1200px) solo cuando contiene la página del chat, y hace que ocupe el resto
   de la altura disponible (sin números mágicos, con flexbox).
   ══════════════════════════════════════════════════════════════════════════ */
/* .main-content normalmente crece con el contenido (min-height:100vh) y deja que
   la página entera scrollee. Acá lo acotamos a la altura real de la ventana solo
   cuando contiene el chat, para que el único que scrollee sea #chatWindow, entre
   el header general (arriba) y el input de mensajes (abajo). Se aplica por
   :has() y, como respaldo en navegadores sin soporte, por una clase que agrega
   el <script> de esta misma vista (ver fijarLayoutChatSinHas). */
.main-content.dewoc-chat-fullheight,
.main-content:has(.chat-page) {
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
}
.container-fluid.dewoc-chat-container,
.container-fluid:has(> .chat-page) {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    padding-bottom: 0 !important;
}
.chat-page {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    margin: 0 -0.9375rem;
}
@media (min-width: 1200px) {
    .chat-page { margin: 0 -2.8125rem; }
}
.chat-card-grow { flex: 1; min-height: 0; display: flex; }
.chat-card-grow .card { flex: 1; min-height: 0; border-radius: 0; }
/* La tarjeta interna también necesita min-height:0 para poder pasarle la altura
   acotada al chatWindow — si no, el contenido de los mensajes "empuja" a toda la
   página hacia abajo en vez de scrollear solo adentro. */
.chat-card-grow .card > .d-flex { min-height: 0; }
.wa-input-bar { flex-shrink: 0; }
.wa-chat-bg { min-height: 0; }

/* ══════════════════════════════════════════════════════════════════════════
   Chat estilo WhatsApp — paleta clásica (claro) y WhatsApp Dark (oscuro).
   Todo queda dentro de clases .wa-* propias de esta vista, no afecta el resto
   de la app.
   ══════════════════════════════════════════════════════════════════════════ */
:root {
    --wa-chat-bg:      #E5DDD5;
    --wa-bubble-in:    #FFFFFF;
    --wa-bubble-in-fg: #111b21;
    --wa-bubble-out:   #DCF8C6;
    --wa-bubble-out-fg:#111b21;
    --wa-meta-fg:      rgba(0,0,0,.45);
    --wa-input-bar-bg: #F0F0F0;
    --wa-input-pill-bg:#fff;
    --wa-send-bg:      #25D366;
    --wa-send-fg:      #fff;
}
:root[data-theme="dark"] {
    --wa-chat-bg:      #0B141A;
    --wa-bubble-in:    #202C33;
    --wa-bubble-in-fg: #E9EDEF;
    --wa-bubble-out:   #005C4B;
    --wa-bubble-out-fg:#E9EDEF;
    --wa-meta-fg:      rgba(233,237,239,.5);
    --wa-input-bar-bg: #202C33;
    --wa-input-pill-bg:#2A3942;
    --wa-send-bg:      #00A884;
    --wa-send-fg:      #fff;
}

.wa-icon-btn {
    background: transparent; border: 0; color: inherit; opacity: .85;
    width: 34px; height: 34px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
}
.wa-icon-btn:hover:not(:disabled) { background: rgba(0,0,0,.08); opacity: 1; }
.wa-icon-btn:disabled { opacity: .35; }

/* ── Tarjetas de confirmación (compra con ítems / acción simple) ────────────── */
/* Selector compuesto (con .wa-bubble) a propósito: así gana por especificidad al
   max-width:78% de .wa-bubble sin importar el orden de las reglas en la hoja. */
.wa-bubble.wa-confirm-bubble { width: 85vw; max-width: 85vw; }
.wa-confirm-item {
    background: rgba(0,0,0,.035);
    border: 1px solid rgba(0,0,0,.08);
}
:root[data-theme="dark"] .wa-confirm-item {
    background: rgba(255,255,255,.04);
    border-color: rgba(255,255,255,.08);
}
.wa-confirm-item input[readonly] { background: transparent; opacity: .8; }
/* Ítem que todavía necesita que el usuario elija/complete algo (ambiguo sin
   resolver, o alta nueva con campos vacíos) — se ve a simple vista sin tener
   que fijarse en el borde fino de cada input individual. */
.wa-confirm-item.wa-confirm-requiere-accion {
    background: rgba(220,53,69,.07);
    border-color: rgba(220,53,69,.35);
}
:root[data-theme="dark"] .wa-confirm-item.wa-confirm-requiere-accion {
    background: rgba(220,53,69,.12);
    border-color: rgba(220,53,69,.4);
}
.wa-confirm-botones { margin-top: .25rem; }
.wa-confirm-botones .btn.active { background-color: var(--wa-send-bg); color: var(--wa-send-fg); border-color: var(--wa-send-bg); }

/* min-width:0 es lo que realmente evita el desborde: por defecto un flex item no encoge
   más allá del ancho intrínseco de su contenido (los <input> "piden" cierto mínimo de
   fábrica), así que con 4 columnas en una tarjeta angosta se salían del contenedor. */
.wa-confirm-campo { flex: 1 1 0; min-width: 0; }
.wa-confirm-campo-sm { flex: 0.6 1 0; min-width: 0; }
.wa-confirm-campo-lg { flex: 1.8 1 0; min-width: 0; }
.wa-confirm-campo input, .wa-confirm-campo select { width: 100%; min-width: 0; }
/* El <select> de IVA necesita un piso de ancho propio (a diferencia de un input de texto,
   que puede recortar su contenido sin romperse visualmente) para que la flecha nativa y el
   valor elegido no queden ilegibles al envolver en 2 columnas en pantallas angostas. */
.wa-confirm-campo select { min-width: 68px; }
.wa-confirm-campo-label { display: block; font-size: .68rem; opacity: .65; margin-bottom: 2px; }

.wa-prov-input { padding-right: 1.8rem; }
.wa-prov-arrow {
    position: absolute; right: 0; top: 0; bottom: 0;
    padding: 0 .5rem; line-height: 1; color: inherit; opacity: .6;
}
.wa-prov-dd {
    display: none; position: fixed; z-index: 2000;
    background: var(--wa-bubble-in); color: var(--wa-bubble-in-fg);
    border: 1px solid rgba(127,127,127,.3); border-radius: 6px;
    box-shadow: 0 6px 24px rgba(0,0,0,.2);
    min-width: 240px; max-height: 220px; overflow-y: auto; font-size: .82rem;
}
.wa-prov-opt { padding: 7px 12px; cursor: pointer; border-bottom: 1px solid rgba(127,127,127,.15); }
.wa-prov-opt:hover { background: rgba(127,127,127,.12); }
.wa-prov-opt-nuevo { color: var(--wa-send-bg); font-weight: 600; }

.wa-chat-bg {
    background-color: var(--wa-chat-bg);
    background-image:
        radial-gradient(rgba(0,0,0,.035) 1px, transparent 1px),
        radial-gradient(rgba(0,0,0,.035) 1px, transparent 1px);
    background-size: 26px 26px;
    background-position: 0 0, 13px 13px;
}

.wa-row { display: flex; }
.wa-row-out { justify-content: flex-end; }

.wa-bubble {
    max-width: 78%;
    padding: .5rem .65rem .4rem;
    border-radius: .55rem;
    box-shadow: 0 1px 1px rgba(0,0,0,.12);
    position: relative;
}
.wa-bubble-in {
    background: var(--wa-bubble-in);
    color: var(--wa-bubble-in-fg);
    border-top-left-radius: 0;
}
.wa-bubble-out {
    background: var(--wa-bubble-out);
    color: var(--wa-bubble-out-fg);
    border-top-right-radius: 0;
}
.wa-bubble-error { background: #ffebee !important; color: #b3261e !important; }
:root[data-theme="dark"] .wa-bubble-error { background: #3a2020 !important; color: #ff8a80 !important; }
.wa-bubble-accion { outline: 1px solid rgba(37,211,102,.4); }

.wa-meta {
    font-size: .68rem;
    color: var(--wa-meta-fg);
    text-align: right;
    margin-top: .15rem;
    line-height: 1;
}
.wa-ticks { color: #53bdeb; font-weight: bold; }

.wa-input-bar { background: var(--wa-input-bar-bg); }
.wa-input-pill {
    background: var(--wa-input-pill-bg);
    color: var(--wa-bubble-in-fg, inherit);
    border: 0;
    border-radius: 1.5rem;
    padding: .55rem 1rem;
    outline: none;
}
.wa-input-pill:focus { box-shadow: 0 0 0 2px rgba(37,211,102,.35); }
.wa-send-btn {
    background: var(--wa-send-bg);
    color: var(--wa-send-fg);
    border: 0; border-radius: 50%;
    width: 40px; height: 40px;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.wa-send-btn:disabled { opacity: .5; }

.wa-chip {
    background: var(--wa-input-pill-bg);
    color: var(--wa-bubble-in-fg, inherit);
    border: 1px solid rgba(0,0,0,.1);
    border-radius: 1.5rem;
    padding: .3rem .8rem;
    font-size: .75rem;
    white-space: nowrap;
}
.wa-chip:hover:not(.disabled) { border-color: var(--wa-send-bg); color: var(--wa-send-bg); }
.wa-chip.disabled { opacity: .5; pointer-events: none; }

mark.wa-hl { background: #ffe066; color: #111; padding: 0 .1em; border-radius: .15em; }

/* Encabezado de las tablas de resultados del chat: colores explícitos (no depender
   de las variables de Bootstrap, que quedaban con el mismo color de fondo y de letra) */
.wa-table-th {
    background-color: #075E54 !important;
    color: #fff !important;
    border-color: #075E54 !important;
}
:root[data-theme="dark"] .wa-table-th {
    background-color: #202C33 !important;
    color: #E9EDEF !important;
    border-color: #202C33 !important;
}

.typing-dot { width:7px;height:7px;border-radius:50%;background:var(--wa-meta-fg);animation:typing-bounce 1.2s infinite ease-in-out; }
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes typing-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-8px)}}
</style>
