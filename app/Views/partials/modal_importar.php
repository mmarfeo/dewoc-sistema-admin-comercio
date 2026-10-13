<?php
/**
 * Modal reutilizable de importación CSV/XLSX con previsualización.
 *
 * Variables esperadas:
 *   $importUrl        URL POST donde se envía el archivo para importar
 *   $importPlantilla  URL GET para descargar la plantilla CSV
 *   $importModulo     Nombre del módulo (ej: "Proveedores")
 *   $importColumnas   Array de strings con columnas requeridas
 *   $importPreviewUrl (opcional) URL POST para previsualizar sin insertar
 */
$importColumnas   ??= [];
$importPreviewUrl ??= null;
?>
<!-- ===== MODAL IMPORTAR (<?= e($importModulo ?? '') ?>) ===== -->
<div class="modal fade" id="modalImportar" tabindex="-1" aria-labelledby="modalImportarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="modalImportarLabel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-2 text-success"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Importar <?= e($importModulo ?? '') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Progress bar de pasos -->
            <div class="px-4 pt-3 pb-0" id="importProgressBar">
                <div class="d-flex align-items-center gap-2 small text-muted mb-3">
                    <span id="stepDot1" class="badge rounded-pill bg-primary">1</span>
                    <span id="stepLbl1">Selección</span>
                    <div class="flex-grow-1 border-top" style="height:1px"></div>
                    <span id="stepDot2" class="badge rounded-pill bg-secondary">2</span>
                    <span id="stepLbl2">Previsualización</span>
                    <div class="flex-grow-1 border-top" style="height:1px"></div>
                    <span id="stepDot3" class="badge rounded-pill bg-secondary">3</span>
                    <span id="stepLbl3">Resultado</span>
                </div>
            </div>

            <div class="modal-body pt-2">

                <!-- ── Paso 1: Selección de archivo ─────────────────────── -->
                <div id="importPaso1">
                    <?php if (!empty($importColumnas)): ?>
                    <div class="alert alert-light border mb-3 py-2">
                        <p class="small fw-semibold mb-1">Columnas del archivo <small class="text-muted fw-normal">(primera fila como encabezado)</small>:</p>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($importColumnas as $col): ?>
                            <span class="badge bg-secondary-soft text-secondary" style="font-family:monospace;font-size:.78rem"><?= e($col) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Las columnas con <strong>*</strong> son obligatorias. Descargá la plantilla para ver datos de ejemplo.</p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <a href="<?= e($importPlantilla ?? '#') ?>" class="btn btn-sm btn-outline-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Descargar plantilla CSV
                        </a>
                    </div>

                    <div>
                        <label for="importArchivoInput" class="form-label small fw-semibold">Seleccionar archivo</label>
                        <input type="file" class="form-control" id="importArchivoInput" accept=".csv,.xlsx">
                        <div class="form-text">Formatos aceptados: <strong>.csv</strong> (separado por punto y coma) o <strong>.xlsx</strong>. Máx. 4 MB.</div>
                    </div>
                </div>

                <!-- ── Paso 2: Previsualización ─────────────────────────── -->
                <div id="importPaso2" style="display:none">
                    <!-- Resumen en cards -->
                    <div class="row g-2 mb-3" id="previewResumen"></div>

                    <!-- Tabla de filas -->
                    <div class="table-responsive rounded border" style="max-height:280px;overflow-y:auto">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light" style="position:sticky;top:0;z-index:1">
                                <tr>
                                    <th style="width:48px"># Fila</th>
                                    <th>Identificador</th>
                                    <th style="width:130px">Estado</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody id="previewTbody"></tbody>
                        </table>
                    </div>

                    <p class="small text-muted mt-2 mb-0" id="previewNota"></p>
                </div>

                <!-- ── Paso 3: Spinner + Resultado ─────────────────────── -->
                <div id="importPaso3" style="display:none">
                    <div id="importSpinner" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Importando…</span></div>
                        <p class="mt-2 small text-muted mb-0">Importando registros, por favor esperá…</p>
                    </div>
                    <div id="importResultado" style="display:none">
                        <div id="importAlerta"></div>
                        <div id="importDetalleErrores" style="display:none">
                            <p class="small fw-semibold mb-1 text-danger">Filas con error:</p>
                            <ul id="importListaErrores" class="small text-danger mb-0 ps-3" style="max-height:160px;overflow-y:auto"></ul>
                        </div>
                        <div id="importDetalleAvisos" style="display:none" class="mt-2">
                            <p class="small fw-semibold mb-1" style="color:#856404">⚠ Advertencias:</p>
                            <ul id="importListaAvisos" class="small mb-0 ps-3" style="color:#856404;max-height:120px;overflow-y:auto"></ul>
                        </div>
                    </div>
                </div>

            </div><!-- /modal-body -->

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-danger" id="btnExportarErrores" style="display:none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Exportar errores
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnVolverPaso1" style="display:none">
                    ← Volver
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnPrevisualizar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Previsualizar
                </button>
                <button type="button" class="btn btn-success" id="btnEjecutarImport" style="display:none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span id="btnImportLabel">Importar</span>
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const IMPORT_URL  = '<?= e($importUrl ?? '') ?>';
    const PREVIEW_URL = <?= $importPreviewUrl ? "'" . e($importPreviewUrl) . "'" : 'null' ?>;
    const csrfToken   = '<?= Csrf::token() ?>';

    // Elementos
    const modalEl     = document.getElementById('modalImportar');
    const paso1       = document.getElementById('importPaso1');
    const paso2       = document.getElementById('importPaso2');
    const paso3       = document.getElementById('importPaso3');
    const spinner     = document.getElementById('importSpinner');
    const resultado   = document.getElementById('importResultado');
    const alerta      = document.getElementById('importAlerta');
    const detalleErr  = document.getElementById('importDetalleErrores');
    const listaErr    = document.getElementById('importListaErrores');
    const detalleAdv  = document.getElementById('importDetalleAvisos');
    const listaAdv    = document.getElementById('importListaAvisos');
    const fileInput   = document.getElementById('importArchivoInput');
    const btnPrev     = document.getElementById('btnPrevisualizar');
    const btnImport   = document.getElementById('btnEjecutarImport');
    const btnVolver   = document.getElementById('btnVolverPaso1');
    const btnExport   = document.getElementById('btnExportarErrores');
    const btnLabel    = document.getElementById('btnImportLabel');
    const previewRes  = document.getElementById('previewResumen');
    const previewTb   = document.getElementById('previewTbody');
    const previewNota = document.getElementById('previewNota');
    const dot1 = document.getElementById('stepDot1');
    const dot2 = document.getElementById('stepDot2');
    const dot3 = document.getElementById('stepDot3');

    let lastPreviewData = null;
    let lastResultData  = null;

    // Sin previewUrl: flujo directo (paso 1 → paso 3)
    if (!PREVIEW_URL) {
        btnPrev.style.display   = 'none';
        btnImport.style.display = '';
        btnLabel.textContent    = 'Importar';
    }

    // Resetear al abrir modal
    modalEl.addEventListener('show.bs.modal', () => ir(1));

    function ir(paso) {
        paso1.style.display   = paso === 1 ? '' : 'none';
        paso2.style.display   = paso === 2 ? '' : 'none';
        paso3.style.display   = paso === 3 ? '' : 'none';

        // Botones footer
        btnVolver.style.display = paso === 2 ? '' : 'none';

        if (PREVIEW_URL) {
            btnPrev.style.display   = paso === 1 ? '' : 'none';
            btnImport.style.display = paso === 2 ? '' : 'none';
        } else {
            btnImport.style.display = paso === 1 ? '' : 'none';
        }

        // Progress dots
        const dotMap = [dot1, dot2, dot3];
        dotMap.forEach((d, i) => {
            d.className = 'badge rounded-pill ' + (i + 1 <= paso ? 'bg-primary' : 'bg-secondary');
        });

        // Reset estado cuando volvemos al paso 1
        if (paso === 1) {
            fileInput.value   = '';
            alerta.innerHTML  = '';
            listaErr.innerHTML = '';
            listaAdv.innerHTML = '';
            detalleErr.style.display = 'none';
            detalleAdv.style.display = 'none';
            resultado.style.display  = 'none';
            btnPrev.disabled    = false;
            btnImport.disabled  = false;
            lastPreviewData = null;
            lastResultData  = null;
        }

        if (paso === 3) {
            spinner.style.display   = '';
            resultado.style.display = 'none';
        }

        actualizarBotonExportar(paso);
    }

    // ── Exportar errores/advertencias a CSV (Excel) ───────────────
    function actualizarBotonExportar(paso) {
        if (paso === 2 && lastPreviewData && (lastPreviewData.filas || []).some(f => f.estado !== 'ok')) {
            btnExport.style.display = '';
        } else if (paso === 3 && lastResultData && ((lastResultData.errores || []).length || (lastResultData.advertencias || []).length)) {
            btnExport.style.display = '';
        } else {
            btnExport.style.display = 'none';
        }
    }

    function csvEscape(v) {
        v = String(v ?? '');
        return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
    }

    function descargarCsv(filename, filas) {
        const csv  = '﻿' + filas.map(f => f.map(csvEscape).join(';')).join('\r\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    btnExport.addEventListener('click', () => {
        const pasoActivo = paso3.style.display !== 'none' ? 3 : (paso2.style.display !== 'none' ? 2 : null);

        if (pasoActivo === 2 && lastPreviewData) {
            const filas = (lastPreviewData.filas || []).filter(f => f.estado !== 'ok');
            if (!filas.length) { showToast('No hay errores ni advertencias para exportar.', 'warning'); return; }
            const rows = [['Fila', 'Identificador', 'Estado', 'Detalle']];
            filas.forEach(f => rows.push([f.fila, f.titulo, f.estado === 'error' ? 'Error' : 'Advertencia', f.mensaje || '']));
            descargarCsv('errores_importacion.csv', rows);
        } else if (pasoActivo === 3 && lastResultData) {
            const rows = [['Tipo', 'Detalle']];
            (lastResultData.errores      || []).forEach(e => rows.push(['Error', e]));
            (lastResultData.advertencias || []).forEach(a => rows.push(['Advertencia', a]));
            if (rows.length === 1) { showToast('No hay errores ni advertencias para exportar.', 'warning'); return; }
            descargarCsv('errores_importacion.csv', rows);
        } else {
            showToast('No hay errores ni advertencias para exportar.', 'warning');
        }
    });

    // ── Previsualizar ────────────────────────────────────────────
    btnPrev.addEventListener('click', async () => {
        const file = fileInput.files[0];
        if (!file) { showToast('Seleccioná un archivo primero.', 'warning'); return; }

        btnPrev.disabled = true;
        const origHtml = btnPrev.innerHTML;
        btnPrev.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Analizando…';

        const fd = new FormData();
        fd.append('archivo', file);
        fd.append('_csrf_token', csrfToken);

        try {
            const res  = await fetch(PREVIEW_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.ok) {
                showToast(data.error ?? 'Error al previsualizar.', 'danger');
                return;
            }
            renderPreview(data);
            lastPreviewData = data;
            btnLabel.textContent = 'Confirmar importación';
            ir(2);
        } catch {
            showToast('Error de red al previsualizar.', 'danger');
        } finally {
            btnPrev.disabled  = false;
            btnPrev.innerHTML = origHtml;
        }
    });

    // ── Volver al paso 1 ─────────────────────────────────────────
    btnVolver.addEventListener('click', () => ir(1));

    // ── Renderizar previsualización ──────────────────────────────
    function renderPreview(data) {
        const { filas, resumen } = data;

        previewRes.innerHTML = `
            <div class="col-4">
                <div class="card border-0 h-100 text-center py-2" style="background:rgba(25,135,84,.08)">
                    <div class="fw-bold fs-3 text-success">${resumen.ok}</div>
                    <div class="small fw-semibold text-success">Correctas</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 h-100 text-center py-2" style="background:rgba(255,193,7,.12)">
                    <div class="fw-bold fs-3 text-warning">${resumen.warning}</div>
                    <div class="small fw-semibold text-warning">Advertencias</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 h-100 text-center py-2" style="background:rgba(220,53,69,.08)">
                    <div class="fw-bold fs-3 text-danger">${resumen.error}</div>
                    <div class="small fw-semibold text-danger">Con error</div>
                </div>
            </div>`;

        previewTb.innerHTML = filas.map(f => {
            const badge = f.estado === 'ok'
                ? '<span class="badge" style="background:rgba(25,135,84,.15);color:#198754">✓ Correcta</span>'
                : f.estado === 'warning'
                ? '<span class="badge" style="background:rgba(255,193,7,.2);color:#856404">⚠ Advertencia</span>'
                : '<span class="badge" style="background:rgba(220,53,69,.12);color:#dc3545">✗ Error</span>';
            const rowCls = f.estado === 'error' ? 'table-danger' : f.estado === 'warning' ? 'table-warning' : '';
            return `<tr class="${rowCls}">
                <td class="text-muted text-center">${f.fila}</td>
                <td class="fw-semibold">${esc(f.titulo)}</td>
                <td>${badge}</td>
                <td class="text-muted">${f.mensaje ? esc(f.mensaje) : '—'}</td>
            </tr>`;
        }).join('');

        const importables = resumen.ok + resumen.warning;
        if (importables === 0) {
            previewNota.innerHTML = '<span class="text-danger fw-semibold">No hay filas válidas para importar. Corregí los errores y volvé a intentar.</span>';
            btnImport.disabled = true;
        } else if (resumen.error > 0) {
            previewNota.textContent = `Se importarán ${importables} fila(s). Las ${resumen.error} con error serán omitidas.`;
            btnImport.disabled = false;
        } else {
            previewNota.textContent = `Se importarán las ${importables} fila(s) correctamente.`;
            btnImport.disabled = false;
        }
    }

    // ── Ejecutar importación ─────────────────────────────────────
    btnImport.addEventListener('click', async () => {
        const file = fileInput.files[0];
        if (!file) { showToast('Seleccioná un archivo primero.', 'warning'); return; }

        ir(3);
        btnImport.disabled = true;

        const fd = new FormData();
        fd.append('archivo', file);
        fd.append('_csrf_token', csrfToken);

        try {
            const res  = await fetch(IMPORT_URL, { method: 'POST', body: fd });
            const data = await res.json();

            spinner.style.display   = 'none';
            resultado.style.display = '';
            lastResultData = data;

            if (data.ok) {
                const total = data.total ?? 0;
                const imp   = data.importados ?? 0;
                const errN  = (data.errores      ?? []).length;
                const advN  = (data.advertencias ?? []).length;
                const tipo  = errN > 0 ? 'warning' : 'success';
                const icon  = errN > 0
                    ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                    : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><polyline points="20 6 9 17 4 12"/></svg>';

                // Si el controller distingue creados/actualizados (upsert por nombre),
                // mostrarlo desglosado; si no, el módulo solo crea registros nuevos.
                const resumenTexto = (data.creados !== undefined && data.actualizados !== undefined)
                    ? `${data.creados} creados, ${data.actualizados} actualizados (de ${total} filas).`
                    : `${imp} de ${total} registros importados correctamente.`;

                alerta.innerHTML = `<div class="alert alert-${tipo} d-flex align-items-start gap-2 mb-2">
                    ${icon}
                    <div>
                        <strong>${resumenTexto}</strong>
                        ${errN > 0 ? `<br><small class="d-block mt-1">${errN} fila(s) omitidas por error — ver detalle abajo.</small>` : ''}
                        ${advN > 0 ? `<br><small class="d-block mt-1" style="color:#856404">${advN} línea(s) con campos calculados automáticamente — ver detalle abajo.</small>` : ''}
                    </div>
                </div>`;

                if (errN > 0) {
                    detalleErr.style.display = '';
                    listaErr.innerHTML = data.errores.map(e => `<li>${esc(e)}</li>`).join('');
                }
                if (advN > 0) {
                    detalleAdv.style.display = '';
                    listaAdv.innerHTML = data.advertencias.map(a => `<li>${esc(a)}</li>`).join('');
                }

                actualizarBotonExportar(3);

                // Si hubo errores, no recargar solo: dejamos que la persona revise/exporte antes de cerrar.
                if (imp > 0 && errN === 0) setTimeout(() => location.reload(), 2500);
            } else {
                alerta.innerHTML = `<div class="alert alert-danger d-flex gap-2"><span>✗</span><span>${esc(data.error ?? 'Error desconocido.')}</span></div>`;
            }
        } catch {
            spinner.style.display   = 'none';
            resultado.style.display = '';
            alerta.innerHTML = '<div class="alert alert-danger">Error de red al procesar la importación.</div>';
        }
    });

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
