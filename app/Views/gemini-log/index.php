<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Log de llamadas a Gemini</h1>
        <p class="text-muted mb-0 small">Detalle de cada llamada real a la API (qué se envió y qué se recibió), para diagnosticar el límite de 20 solicitudes/minuto del plan gratuito.</p>
    </div>
    <a href="<?= url('gemini-uso') ?>" class="btn btn-outline-secondary btn-sm">Ver costos (Uso Gemini)</a>
</div>

<?php if (!$tablaExiste): ?>
<div class="alert alert-warning d-flex gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="flex-shrink-0 mt-1"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <strong>Tabla no encontrada.</strong> Ejecutá el siguiente SQL en tu base de datos para activar el log:
        <pre class="bg-dark text-success rounded p-3 mt-2 small mb-0">SOURCE database/gemini_llamadas_log_setup.sql;</pre>
        <div class="mt-1 small text-muted">O copiá el contenido del archivo <code>database/gemini_llamadas_log_setup.sql</code> y ejecutalo en phpMyAdmin.</div>
    </div>
</div>
<?php return; endif; ?>

<!-- Mensajes que más llamadas encadenaron -->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Mensajes que más llamadas encadenaron</h6>
        <p class="small text-muted mb-0">Un solo mensaje del chat puede disparar varias llamadas reales a Gemini (function-calling) — acá se ven los que más encadenaron, para detectar patrones ineficientes.</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Usuario</th><th>Mensaje</th><th class="text-end">Llamadas</th><th class="text-end">Errores</th><th>Última</th></tr>
                </thead>
                <tbody>
                    <?php if (!$topOfensores): ?>
                    <tr><td colspan="5" class="text-center text-muted small py-3">Sin datos todavía.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topOfensores as $t): ?>
                    <tr class="<?= (int)$t['total_llamadas'] >= 4 ? 'table-warning' : '' ?>">
                        <td class="small"><?= e($t['usuario_nombre'] ?? '—') ?></td>
                        <td class="small text-truncate" style="max-width:400px"><?= e(mb_substr((string)$t['mensaje_texto'], 0, 120)) ?></td>
                        <td class="text-end fw-semibold"><?= (int)$t['total_llamadas'] ?></td>
                        <td class="text-end <?= (int)$t['total_errores'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= (int)$t['total_errores'] ?></td>
                        <td class="small text-muted"><?= e(date('d/m H:i', strtotime($t['ultima']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= url('gemini-log') ?>" class="card border-0 mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>"></div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Resultado</label>
                <select name="exito" class="form-select">
                    <option value="" <?= $filtros['exito'] === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="1" <?= $filtros['exito'] === '1' ? 'selected' : '' ?>>Solo éxito</option>
                    <option value="0" <?= $filtros['exito'] === '0' ? 'selected' : '' ?>>Solo error</option>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Filtrar</button></div>
        </div>
    </div>
</form>

<!-- Detalle de llamadas -->
<div class="card border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblLlamadas" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha/Hora</th><th>Usuario</th><th class="text-end">Mensaje</th><th class="text-end">Iter.</th>
                        <th>Modelo</th><th>Enviado</th><th>Recibido / Error</th><th class="text-end">HTTP</th>
                        <th class="text-end">Duración</th><th class="text-end">Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    <?php /*
                      Sin filas NO se dibuja una fila con colspan: DataTables cuenta las
                      celdas del tbody para validar la tabla y un colspan le da 1 columna
                      contra las 10 del thead, lo que dispara "Incorrect column count" y
                      deja la tabla sin inicializar (se pierden el orden y el buscador). El
                      cartel de tabla vacía lo pone DataTables con language.emptyTable.
                    */ ?>
                    <?php foreach ($llamadas as $l): ?>
                    <tr class="<?= !$l['exito'] ? 'table-danger' : '' ?>">
                        <td class="small"><?= e(date('d/m H:i:s', strtotime($l['created_at']))) ?></td>
                        <td class="small"><?= e($l['usuario_nombre'] ?? '—') ?></td>
                        <td class="text-end small text-muted">#<?= (int)$l['mensaje_chat_id'] ?></td>
                        <td class="text-end small"><?= (int)$l['iteracion'] ?></td>
                        <td class="small text-muted"><?= e($l['modelo'] ?? '—') ?></td>
                        <td class="small"><?= e($l['resumen_enviado'] ?? '—') ?></td>
                        <td class="small <?= !$l['exito'] ? 'text-danger' : '' ?>">
                            <?= e($l['exito'] ? ($l['resumen_recibido'] ?? '—') : ($l['error_resumen'] ?? '—')) ?>
                        </td>
                        <td class="text-end small">
                            <span class="badge <?= $l['exito'] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>"><?= (int)$l['http_code'] ?></span>
                        </td>
                        <td class="text-end small text-muted"><?= (int)$l['duracion_ms'] ?> ms</td>
                        <td class="text-end small text-muted">
                            <?= $l['tokens_prompt'] !== null ? number_format((int)$l['tokens_prompt'] + (int)$l['tokens_respuesta'], 0, ',', '.') : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $('#tblLlamadas').DataTable({
        pageLength: 50,
        language: { emptyTable: 'Sin llamadas en el período' },
        dom: 'Bfrtip',
        buttons: ['colvis','excelHtml5'],
        order: [],
    });
});
</script>
