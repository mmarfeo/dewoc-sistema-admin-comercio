<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Logs del Sistema</h1>
        <p class="text-muted small mb-0">Últimas <?= 300 ?> líneas, más reciente primero.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefrescar">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            Refrescar
        </button>
        <a href="<?= url('logs/descargar?archivo=' . $archivo) ?>" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Descargar
        </a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $archivo === 'app' ? 'active' : '' ?>" href="<?= url('logs?archivo=app') ?>">
            Aplicación (app.log)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $archivo === 'php' ? 'active' : '' ?>" href="<?= url('logs?archivo=php') ?>">
            Errores PHP (php_errors.log)
        </a>
    </li>
</ul>

<?php if ($archivo === 'php'): ?>
<div class="alert alert-light border small mb-3">
    Este archivo solo recibe contenido cuando el sitio corre en modo producción (<code>APP_ENV=production</code> en <code>.env</code>).
    Si el sitio está en modo <code>development</code>, los errores de PHP se muestran directo en pantalla en vez de loguearse acá.
</div>
<?php endif; ?>

<div class="card border-0">
    <div class="card-body p-0">
        <div id="logContenedor" style="max-height:70vh; overflow-y:auto; background:#1e1e1e; border-radius:.375rem">
            <?php if (!($archivo === 'app' ? $existeApp : $existePhp) || !$lineas): ?>
            <p class="text-center text-muted py-5 mb-0">Sin registros todavía.</p>
            <?php else: ?>
            <table class="table table-sm mb-0" id="tblLog" style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.8rem">
                <tbody id="logBody">
                    <?php foreach ($lineas as $linea): ?>
                    <?php
                        $esError = str_contains($linea, '[ERROR]');
                        $esWarn  = str_contains($linea, '[WARNING]') || str_contains($linea, '[WARN]');
                    ?>
                    <tr style="border-color:#333">
                        <td class="<?= $esError ? 'text-danger' : ($esWarn ? 'text-warning' : 'text-light') ?>" style="white-space:pre-wrap;word-break:break-word;padding:4px 12px">
                            <?= e($linea) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const ARCHIVO  = '<?= e($archivo) ?>';

function _escLog(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function _renderLineas(lineas) {
    const body = document.getElementById('logBody');
    const cont = document.getElementById('logContenedor');
    if (!lineas.length) {
        cont.innerHTML = '<p class="text-center text-muted py-5 mb-0">Sin registros todavía.</p>';
        return;
    }
    if (!body) {
        cont.innerHTML = '<table class="table table-sm mb-0" id="tblLog" style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.8rem"><tbody id="logBody"></tbody></table>';
    }
    document.getElementById('logBody').innerHTML = lineas.map(l => {
        const esError = l.includes('[ERROR]');
        const esWarn  = l.includes('[WARNING]') || l.includes('[WARN]');
        const cls = esError ? 'text-danger' : (esWarn ? 'text-warning' : 'text-light');
        return `<tr style="border-color:#333"><td class="${cls}" style="white-space:pre-wrap;word-break:break-word;padding:4px 12px">${_escLog(l)}</td></tr>`;
    }).join('');
}

document.getElementById('btnRefrescar').addEventListener('click', async function () {
    this.disabled = true;
    try {
        const r = await fetch(`${BASE_URL}/logs/contenido?archivo=${ARCHIVO}`);
        const d = await r.json();
        if (d.ok) { _renderLineas(d.lineas); showToast('Actualizado.'); }
    } catch { showToast('Error al refrescar.', 'danger'); }
    finally { this.disabled = false; }
});
</script>
