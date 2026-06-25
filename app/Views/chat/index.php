<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Chat IA — MCP MySQL</h1>
        <p class="text-muted mb-0 small">Consultá los datos del sistema en lenguaje natural.</p>
    </div>
    <?php if (!$apiActiva): ?>
    <span class="badge bg-warning text-dark">API no configurada</span>
    <?php else: ?>
    <span class="badge bg-success">IA activa</span>
    <?php endif; ?>
</div>

<?php if (!$apiActiva): ?>
<div class="alert alert-warning d-flex gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <strong>Sin API key configurada.</strong>
        Para habilitar la IA, completá <code>CLAUDE_API_KEY</code> en <code>config/config.php</code>.
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Ventana de chat -->
        <div class="card border-0 d-flex flex-column" style="height:65vh">
            <div class="card-body overflow-auto p-3 d-flex flex-column gap-3" id="chatWindow">

                <!-- Mensaje de bienvenida -->
                <div class="d-flex gap-2 align-items-start">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/></svg>
                    </div>
                    <div class="bg-light rounded-3 p-3 flex-grow-1" style="max-width:85%">
                        <p class="mb-0 small">
                            ¡Hola! Soy tu asistente IA con acceso a la base de datos del sistema.
                            Podés preguntarme cosas como:<br>
                            <em>"¿Cuánto vendimos esta semana?"</em> · <em>"¿Qué productos tienen stock bajo?"</em> · <em>"¿Cuáles son los clientes con más compras?"</em>
                        </p>
                    </div>
                </div>

                <!-- Historial cargado desde servidor -->
                <?php foreach ($historial as $m): ?>
                <?php if ($m['rol'] === 'user'): ?>
                <div class="d-flex gap-2 align-items-start flex-row-reverse">
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div class="bg-primary text-white rounded-3 p-3" style="max-width:85%">
                        <p class="mb-0 small"><?= nl2br(e($m['contenido'])) ?></p>
                        <div class="text-white-50 mt-1" style="font-size:.7rem"><?= date('d/m H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="d-flex gap-2 align-items-start">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/></svg>
                    </div>
                    <div class="flex-grow-1" style="max-width:85%">
                        <?php if ($m['sql_ejecutado']): ?>
                        <div class="mb-2">
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 toggle-sql" type="button" data-sql="<?= e($m['sql_ejecutado']) ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                Ver SQL
                            </button>
                        </div>
                        <?php endif; ?>
                        <div class="bg-light rounded-3 p-3">
                            <p class="mb-0 small"><?= nl2br(e($m['contenido'])) ?></p>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.7rem"><?= date('d/m H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>

            </div>

            <!-- Input -->
            <div class="card-footer border-0 bg-white p-3">
                <form id="chatForm" class="d-flex gap-2">
                    <?= Csrf::field() ?>
                    <input
                        type="text"
                        id="chatInput"
                        name="mensaje"
                        class="form-control"
                        placeholder="Escribí tu pregunta... ej: ¿Cuánto vendimos este mes?"
                        autocomplete="off"
                        <?= !$apiActiva ? 'disabled' : '' ?>
                    >
                    <button type="submit" class="btn btn-primary px-3" id="btnEnviar" <?= !$apiActiva ? 'disabled' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel lateral: ejemplos + tablas -->
    <div class="col-lg-4">
        <div class="card border-0 mb-3">
            <div class="card-header border-0 bg-transparent fw-semibold">Preguntas de ejemplo</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $ejemplos = [
                        '¿Cuánto vendimos esta semana?',
                        '¿Cuáles son los 5 productos más vendidos este mes?',
                        '¿Qué productos tienen stock menor a 10 unidades?',
                        '¿Cuántos clientes tenemos activos?',
                        '¿Cuáles son las últimas 10 compras a proveedores?',
                        '¿Cuánto fue el total de caja hoy?',
                        '¿Qué pedidos online están pendientes de envío?',
                        '¿Cuál es el proveedor con más compras este año?',
                    ];
                    foreach ($ejemplos as $ej): ?>
                    <button type="button" class="list-group-item list-group-item-action small py-2 ejemplo-chat <?= !$apiActiva ? 'disabled' : '' ?>">
                        <?= e($ej) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card border-0">
            <div class="card-header border-0 bg-transparent fw-semibold">Tablas disponibles</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (MCP_TABLAS_OK as $tabla): ?>
                    <span class="badge bg-light text-dark border small"><?= e($tabla) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mt-2 mb-0">La IA solo puede hacer consultas SELECT sobre estas tablas.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal SQL -->
<div class="modal fade" id="modalSQL" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">SQL ejecutado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="modalSQLContent" class="bg-dark text-success p-3 rounded small mb-0" style="white-space:pre-wrap;word-break:break-all"></pre>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const BASE_URL    = '<?= BASE_URL ?>';
const chatWindow  = document.getElementById('chatWindow');
const chatForm    = document.getElementById('chatForm');
const chatInput   = document.getElementById('chatInput');
const btnEnviar   = document.getElementById('btnEnviar');
const modalSQL    = new bootstrap.Modal(document.getElementById('modalSQL'));
const modalSQLEl  = document.getElementById('modalSQLContent');

// Scroll al final al cargar
chatWindow.scrollTop = chatWindow.scrollHeight;

// Ejemplos de preguntas
document.querySelectorAll('.ejemplo-chat').forEach(btn => {
    btn.addEventListener('click', () => {
        chatInput.value = btn.textContent.trim();
        chatInput.focus();
    });
});

// Mostrar SQL en modal
document.addEventListener('click', e => {
    const btn = e.target.closest('.toggle-sql');
    if (!btn) return;
    modalSQLEl.textContent = btn.dataset.sql;
    modalSQL.show();
});

// Enviar mensaje
chatForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const texto = chatInput.value.trim();
    if (!texto) return;

    chatInput.value = '';
    btnEnviar.disabled = true;

    // Burbuja del usuario
    appendBurbuja('user', texto);

    // Indicador de "escribiendo"
    const typing = appendTyping();

    try {
        const fd = new FormData();
        fd.append('mensaje', texto);
        fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(`${BASE_URL}/chat/mensaje`, { method: 'POST', body: fd });
        const d   = await res.json();

        typing.remove();

        if (!d.ok) {
            appendBurbuja('assistant', d.error || 'Error desconocido.', null, null, true);
        } else if (d.tipo === 'sql') {
            appendBurbuja('assistant', d.respuesta, d.sql, d.resultados);
        } else {
            appendBurbuja('assistant', d.respuesta);
        }
    } catch (err) {
        typing.remove();
        appendBurbuja('assistant', 'Error de conexión. Intentá de nuevo.', null, null, true);
    } finally {
        btnEnviar.disabled = false;
        chatInput.focus();
    }
});

function appendBurbuja(rol, texto, sql, resultados, esError) {
    const isUser = rol === 'user';
    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex gap-2 align-items-start' + (isUser ? ' flex-row-reverse' : '');

    const avatar = document.createElement('div');
    avatar.className = 'rounded-circle d-flex align-items-center justify-content-center flex-shrink-0';
    avatar.style.cssText = 'width:32px;height:32px;background:' + (isUser ? '#6c757d' : '#0d6efd');
    avatar.innerHTML = isUser
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/></svg>';

    const content = document.createElement('div');
    content.style.maxWidth = '85%';

    if (!isUser) {
        content.className = 'flex-grow-1';

        if (sql) {
            const sqlBtn = document.createElement('button');
            sqlBtn.className = 'btn btn-sm btn-outline-secondary py-0 px-2 toggle-sql mb-2';
            sqlBtn.dataset.sql = sql;
            sqlBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> Ver SQL';
            content.appendChild(sqlBtn);
        }

        const bubble = document.createElement('div');
        bubble.className = 'rounded-3 p-3 ' + (esError ? 'bg-danger-subtle text-danger' : 'bg-light');
        bubble.innerHTML = '<p class="mb-0 small">' + escHtml(texto).replace(/\n/g, '<br>') + '</p>';
        content.appendChild(bubble);

        if (resultados && resultados.length > 0) {
            content.appendChild(buildResultTable(resultados));
        }
    } else {
        const bubble = document.createElement('div');
        bubble.className = 'bg-primary text-white rounded-3 p-3';
        bubble.innerHTML = '<p class="mb-0 small">' + escHtml(texto).replace(/\n/g, '<br>') + '</p>';
        content.appendChild(bubble);
    }

    wrapper.appendChild(avatar);
    wrapper.appendChild(content);
    chatWindow.appendChild(wrapper);
    chatWindow.scrollTop = chatWindow.scrollHeight;
}

function appendTyping() {
    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex gap-2 align-items-start';
    wrapper.innerHTML = `
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/></svg>
        </div>
        <div class="bg-light rounded-3 p-3">
            <div class="d-flex gap-1 align-items-center" style="height:18px">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        </div>`;
    chatWindow.appendChild(wrapper);
    chatWindow.scrollTop = chatWindow.scrollHeight;
    return wrapper;
}

function buildResultTable(rows) {
    if (!rows || rows.length === 0) return document.createElement('div');
    const cols = Object.keys(rows[0]);
    const wrap = document.createElement('div');
    wrap.className = 'mt-2 overflow-auto';
    wrap.style.maxHeight = '220px';
    const table = document.createElement('table');
    table.className = 'table table-sm table-bordered small mb-0';
    table.innerHTML = '<thead class="table-dark"><tr>' +
        cols.map(c => `<th>${escHtml(c)}</th>`).join('') +
        '</tr></thead>';
    const tbody = document.createElement('tbody');
    rows.slice(0, 20).forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = cols.map(c => `<td>${escHtml(String(row[c] ?? ''))}</td>`).join('');
        tbody.appendChild(tr);
    });
    if (rows.length > 20) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="${cols.length}" class="text-muted text-center">... y ${rows.length - 20} filas más</td>`;
        tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    wrap.appendChild(table);
    return wrap;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Enter para enviar, Shift+Enter para nueva línea no aplica (es input, no textarea)
chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.requestSubmit();
    }
});
</script>

<style>
.typing-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #6c757d;
    animation: typing-bounce 1.2s infinite ease-in-out;
}
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes typing-bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30%            { transform: translateY(-8px); }
}
</style>
