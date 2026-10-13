<!-- ── Título + filtros + subida ─────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0 fw-bold">Galería</h1>
    <div class="d-flex gap-2">
        <div class="btn-group" role="group">
            <a href="<?= url('galeria') ?>" class="btn btn-sm <?= $tipoFiltro === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
            <a href="<?= url('galeria?tipo=imagen') ?>" class="btn btn-sm <?= $tipoFiltro === 'imagen' ? 'btn-primary' : 'btn-outline-primary' ?>">Imágenes</a>
            <a href="<?= url('galeria?tipo=video') ?>" class="btn btn-sm <?= $tipoFiltro === 'video' ? 'btn-primary' : 'btn-outline-primary' ?>">Videos</a>
        </div>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('inputArchivo').click()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Subir archivo
        </button>
        <input type="file" id="inputArchivo" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" hidden>
    </div>
</div>

<div id="progresoSubida" class="alert alert-info py-2 small d-none">Subiendo archivo...</div>

<!-- ── Grilla de archivos ────────────────────────────────────────── -->
<?php if (empty($archivos)): ?>
<div class="card border-0">
    <div class="card-body text-center text-muted py-5">
        Todavía no subiste ninguna foto o video. Usá el botón "Subir archivo" para empezar tu galería.
    </div>
</div>
<?php else: ?>
<div class="row g-3" id="grillaGaleria">
    <?php foreach ($archivos as $a): ?>
    <div class="col-6 col-md-4 col-lg-3" data-id="<?= $a['id'] ?>" data-estado="<?= e($a['estado']) ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center">
                <?php if ($a['tipo'] === 'video' && $a['estado'] === 'generando'): ?>
                    <div class="text-center text-muted small p-2">
                        <div class="spinner-border spinner-border-sm mb-1" role="status"></div>
                        <div>Generando video...</div>
                    </div>
                <?php elseif ($a['tipo'] === 'video' && $a['estado'] === 'error'): ?>
                    <div class="text-center text-danger small p-2"><?= e($a['error_mensaje'] ?: 'No se pudo generar el video.') ?></div>
                <?php elseif ($a['tipo'] === 'video'): ?>
                    <video src="<?= url('galeria/ver?id=' . $a['id']) ?>" class="w-100 h-100" style="object-fit:cover" controls muted></video>
                <?php else: ?>
                    <img src="<?= url('galeria/ver?id=' . $a['id']) ?>" class="w-100 h-100" style="object-fit:cover" alt="<?= e($a['nombre_original'] ?? '') ?>">
                <?php endif; ?>
            </div>
            <div class="card-body p-2 d-flex align-items-center justify-content-between">
                <span class="badge <?= $a['origen'] === 'generado_ia' ? 'bg-info-soft text-info' : 'bg-secondary-soft text-secondary' ?>">
                    <?= $a['origen'] === 'generado_ia' ? 'IA' : 'Propio' ?>
                </span>
                <div class="d-flex gap-1">
                    <?php if ($a['tipo'] === 'imagen'): ?>
                    <button class="btn btn-sm btn-outline-primary py-0" onclick="abrirMiniChat(<?= $a['id'] ?>)" title="Pedirle a la IA">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger py-0" onclick="eliminarArchivo(<?= $a['id'] ?>)" title="Eliminar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Mini-chat: pedirle a la IA que trabaje sobre una foto real ──── -->
<div class="modal fade" id="modalMiniChat" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Pedirle a la IA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-1x1 bg-light mb-3" style="max-width:220px;margin:0 auto">
                    <img id="miniChatImg" src="" class="w-100 h-100" style="object-fit:cover" alt="">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Proveedor</label>
                    <select id="miniChatProveedor" class="form-select form-select-sm">
                        <option value="gemini" selected>Google (recomendado, de pago)</option>
                        <option value="nvidia">NVIDIA (alternativa, sin verificar todavía)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">¿Qué querés que la IA le haga a esta foto?</label>
                    <textarea id="miniChatInstruccion" class="form-control" rows="2" placeholder="Ej: mejorá la luz y el fondo para una publicación de Instagram"></textarea>
                </div>
                <div id="miniChatResultado" class="d-none">
                    <hr>
                    <p class="small text-muted mb-2">Resultado (ya se guardó en tu galería):</p>
                    <div class="ratio ratio-1x1 bg-light" style="max-width:220px;margin:0 auto">
                        <img id="miniChatImgResultado" src="" class="w-100 h-100 d-none" style="object-fit:cover" alt="Resultado generado por IA">
                        <video id="miniChatVideoResultado" class="w-100 h-100 d-none" style="object-fit:cover" controls muted></video>
                        <div id="miniChatGenerandoVideo" class="d-none text-center text-muted small p-2 align-self-center">
                            <div class="spinner-border spinner-border-sm mb-1" role="status"></div>
                            <div>Generando video... esto puede tardar unos minutos.</div>
                        </div>
                    </div>
                    <p id="miniChatTexto" class="small mt-2"></p>
                </div>
                <div id="miniChatError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-primary" id="btnMiniChatVideo" onclick="generarVideoConIa()">Generar video</button>
                <button type="button" class="btn btn-primary" id="btnMiniChatGenerar" onclick="generarConIa()">Generar imagen</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const BASE_URL   = '<?= BASE_URL ?>';

document.getElementById('inputArchivo').addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('archivo', file);
    fd.append('_csrf_token', CSRF_TOKEN);

    const progreso = document.getElementById('progresoSubida');
    progreso.classList.remove('d-none');

    try {
        const resp = await fetch(`${BASE_URL}/galeria/subir`, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            showToast(data.mensaje, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(data.error || 'No se pudo subir el archivo.', 'danger');
        }
    } catch {
        showToast('Error de conexión.', 'danger');
    } finally {
        progreso.classList.add('d-none');
        this.value = '';
    }
});

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos el modal haciendo clic en un trigger oculto (mismo patrón que Categorías).
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

let miniChatArchivoId = null;

function abrirMiniChat(id) {
    miniChatArchivoId = id;
    document.getElementById('miniChatImg').src = `${BASE_URL}/galeria/ver?id=${id}`;
    document.getElementById('miniChatInstruccion').value = '';
    document.getElementById('miniChatResultado').classList.add('d-none');
    document.getElementById('miniChatError').classList.add('d-none');
    _showModal('modalMiniChat');
}

async function generarConIa() {
    const instruccion = document.getElementById('miniChatInstruccion').value.trim();
    const errorEl      = document.getElementById('miniChatError');
    errorEl.classList.add('d-none');

    if (!instruccion) {
        errorEl.textContent = 'Escribí qué querés que la IA le haga a la foto.';
        errorEl.classList.remove('d-none');
        return;
    }

    const btn = document.getElementById('btnMiniChatGenerar');
    btn.disabled = true;
    btn.textContent = 'Generando...';

    const fd = new FormData();
    fd.append('archivo_id', miniChatArchivoId);
    fd.append('instruccion', instruccion);
    fd.append('proveedor', document.getElementById('miniChatProveedor').value);
    fd.append('_csrf_token', CSRF_TOKEN);

    try {
        const resp = await fetch(`${BASE_URL}/galeria/generar`, { method: 'POST', body: fd });
        const data = await resp.json();

        if (!data.ok) {
            errorEl.textContent = data.error || 'No se pudo generar la imagen.';
            errorEl.classList.remove('d-none');
        } else if (data.tipo === 'imagen') {
            document.getElementById('miniChatImgResultado').src = `${BASE_URL}/galeria/ver?id=${data.id}`;
            document.getElementById('miniChatTexto').textContent = data.respuesta || '';
            document.getElementById('miniChatResultado').classList.remove('d-none');
            showToast('Imagen generada y guardada en tu galería.', 'success');
        } else {
            errorEl.textContent = data.respuesta || 'La IA no generó ninguna imagen. Probá con otra instrucción.';
            errorEl.classList.remove('d-none');
        }
    } catch {
        errorEl.textContent = 'Error de conexión.';
        errorEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Generar';
    }
}

async function generarVideoConIa() {
    const instruccion = document.getElementById('miniChatInstruccion').value.trim();
    const errorEl      = document.getElementById('miniChatError');
    errorEl.classList.add('d-none');

    if (!instruccion) {
        errorEl.textContent = 'Escribí qué querés que la IA haga con el video.';
        errorEl.classList.remove('d-none');
        return;
    }

    const btn = document.getElementById('btnMiniChatVideo');
    btn.disabled = true;
    btn.textContent = 'Iniciando...';

    const fd = new FormData();
    fd.append('archivo_id', miniChatArchivoId);
    fd.append('instruccion', instruccion);
    fd.append('proveedor', document.getElementById('miniChatProveedor').value);
    fd.append('_csrf_token', CSRF_TOKEN);

    try {
        const resp = await fetch(`${BASE_URL}/galeria/generar-video`, { method: 'POST', body: fd });
        const data = await resp.json();

        if (!data.ok) {
            errorEl.textContent = data.error || 'No se pudo iniciar la generación del video.';
            errorEl.classList.remove('d-none');
            return;
        }

        document.getElementById('miniChatImgResultado').classList.add('d-none');
        document.getElementById('miniChatVideoResultado').classList.add('d-none');
        document.getElementById('miniChatGenerandoVideo').classList.remove('d-none');
        document.getElementById('miniChatTexto').textContent = '';
        document.getElementById('miniChatResultado').classList.remove('d-none');
        showToast('Generando video... vas a poder verlo acá y en la galería en unos minutos.', 'info');

        pollEstadoVideo(data.id, (estado, error) => {
            if (estado === 'listo') {
                document.getElementById('miniChatGenerandoVideo').classList.add('d-none');
                const videoEl = document.getElementById('miniChatVideoResultado');
                videoEl.src = `${BASE_URL}/galeria/ver?id=${data.id}`;
                videoEl.classList.remove('d-none');
                showToast('Video generado y guardado en tu galería.', 'success');
            } else if (estado === 'error') {
                document.getElementById('miniChatGenerandoVideo').classList.add('d-none');
                errorEl.textContent = error || 'No se pudo generar el video.';
                errorEl.classList.remove('d-none');
            }
        });
    } catch {
        errorEl.textContent = 'Error de conexión.';
        errorEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Generar video';
    }
}

// Consulta periódicamente galeria/estado hasta que el video quede listo o falle.
function pollEstadoVideo(id, onFinal) {
    const intervalo = setInterval(async () => {
        try {
            const resp = await fetch(`${BASE_URL}/galeria/estado?id=${id}`);
            const data = await resp.json();
            if (data.ok && (data.estado === 'listo' || data.estado === 'error')) {
                clearInterval(intervalo);
                onFinal(data.estado, data.error);
                const card = document.querySelector(`#grillaGaleria [data-id="${id}"]`);
                if (card) card.dataset.estado = data.estado;
                if (data.estado === 'listo') setTimeout(() => location.reload(), 1500);
            }
        } catch { /* reintenta en el próximo tick */ }
    }, 5000);
}

// Al cargar la página, retomar el polling de videos que ya estaban "generando".
document.querySelectorAll('#grillaGaleria [data-estado="generando"]').forEach(card => {
    pollEstadoVideo(card.dataset.id, () => location.reload());
});

function eliminarArchivo(id) {
    const ejecutar = async () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('_csrf_token', CSRF_TOKEN);

        try {
            const resp = await fetch(`${BASE_URL}/galeria/eliminar`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                document.querySelector(`#grillaGaleria [data-id="${id}"]`)?.remove();
                showToast(data.mensaje, 'success');
            } else {
                showToast(data.error || 'No se pudo eliminar.', 'danger');
            }
        } catch {
            showToast('Error de conexión.', 'danger');
        }
    };
    if (typeof showConfirm === 'function') {
        showConfirm('¿Eliminar este archivo de la galería?', ejecutar, { label: 'Eliminar' });
    } else {
        if (confirm('¿Eliminar este archivo de la galería?')) ejecutar();
    }
}
</script>
