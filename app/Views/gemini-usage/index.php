<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Uso de Gemini API</h1>
        <p class="text-muted mb-0 small">Seguimiento separado de llamadas y costos: Chat IA (texto) vs. Galería (imagen/video).</p>
    </div>
    <span class="badge bg-light text-secondary border"><?= defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini' ?></span>
</div>

<?php if (!$tablaExiste): ?>
<div class="alert alert-warning d-flex gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" class="flex-shrink-0 mt-1"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <strong>Tablas no encontradas.</strong> Ejecutá el siguiente SQL en tu base de datos para activar el seguimiento:
        <pre class="bg-dark text-success rounded p-3 mt-2 small mb-0">SOURCE database/gemini_usage_setup.sql;</pre>
        <div class="mt-1 small text-muted">O copiá el contenido del archivo <code>database/gemini_usage_setup.sql</code> y ejecutalo en phpMyAdmin.</div>
    </div>
</div>
<?php return; endif; ?>

<?php
$labels = ['chat' => 'Chat IA (texto)', 'imagen' => 'Galería · Imagen', 'video' => 'Galería · Video'];
$iconos = [
    'chat'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'imagen' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    'video'  => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
];
?>

<!-- ── Tarjetas por categoría: Chat / Imagen / Video, sin mezclar entre sí ──── -->
<div class="row g-3 mb-4">
    <?php foreach (GeminiUsageModel::CATEGORIAS as $cat): $r = $resumen[$cat]; ?>
    <div class="col-md-4">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold small d-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><?= $iconos[$cat] ?></svg>
                        <?= $labels[$cat] ?>
                    </span>
                    <?php if ($cat === 'chat'): $pctHoy = $limiteGratDia > 0 ? min(100, round($r['llamadas_hoy'] / $limiteGratDia * 100)) : 0; ?>
                    <span class="badge <?= $r['llamadas_hoy'] >= $limiteGratDia ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?> small"><?= $r['llamadas_hoy'] >= $limiteGratDia ? 'Sin cuota' : 'En cuota gratis' ?></span>
                    <?php else: ?>
                    <span class="badge bg-info-soft text-info small">De pago</span>
                    <?php endif; ?>
                </div>

                <?php if ($cat === 'chat'): ?>
                <div class="h4 fw-bold mb-1"><?= number_format($r['llamadas_hoy']) ?><span class="fs-6 text-muted fw-normal"> / <?= number_format($limiteGratDia) ?> hoy</span></div>
                <div class="progress mb-2" style="height:5px">
                    <div class="progress-bar <?= $pctHoy >= 90 ? 'bg-danger' : ($pctHoy >= 60 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $pctHoy ?>%"></div>
                </div>
                <?php else: ?>
                <div class="h4 fw-bold mb-1"><?= number_format($r['llamadas_hoy']) ?><span class="fs-6 text-muted fw-normal"> hoy</span></div>
                <?php endif; ?>

                <div class="small text-muted"><?= number_format($r['llamadas_mes']) ?> llamadas este mes</div>
                <?php if ($cat === 'video'): ?>
                <div class="small text-muted"><?= number_format($r['segundos_mes']) ?> segundos generados (estimado)</div>
                <?php else: ?>
                <div class="small text-muted"><?= number_format($r['tokens_mes']) ?> tokens este mes</div>
                <?php endif; ?>

                <div class="mt-2 pt-2 border-top">
                    <div class="fw-semibold">USD <?= number_format($r['costo_usd'], 4) ?></div>
                    <div class="small text-primary">≈ ARS <?= number_format($r['costo_ars'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">

    <!-- ── Tabla diaria (filtrable por categoría) ───────────────────────────── -->
    <div class="col-lg-8">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-semibold mb-0">Uso diario — últimos 30 días</h6>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach (GeminiUsageModel::CATEGORIAS as $cat): ?>
                    <a href="<?= url('gemini-uso?categoria=' . $cat) ?>" class="btn <?= $categoriaFiltro === $cat ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $labels[$cat] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($statsXDia)): ?>
                <div class="text-muted small text-center py-5">Sin datos aún para "<?= $labels[$categoriaFiltro] ?>".</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-end">Llamadas</th>
                                <?php if ($categoriaFiltro === 'video'): ?>
                                <th class="text-end">Segundos</th>
                                <?php else: ?>
                                <th class="text-end">Tokens entrada</th>
                                <th class="text-end">Tokens salida</th>
                                <?php endif; ?>
                                <th class="text-end">Costo USD est.</th>
                                <?php if ($categoriaFiltro === 'chat'): ?>
                                <th>% cuota día</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $costoIn    = (float) $config['costo_input_por_millon'];
                        $costoOut   = (float) $config['costo_output_por_millon'];
                        $costoVideo = (float) $config['costo_video_por_segundo'];
                        foreach ($statsXDia as $row):
                            $costDia = $categoriaFiltro === 'video'
                                ? $row['video_segundos'] * $costoVideo
                                : (($row['tokens_prompt'] / 1_000_000) * $costoIn) + (($row['tokens_respuesta'] / 1_000_000) * $costoOut);
                            $pctDia   = $limiteGratDia > 0 ? min(100, round($row['llamadas'] / $limiteGratDia * 100)) : 0;
                            $colorPct = $pctDia >= 90 ? 'bg-danger' : ($pctDia >= 60 ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                            <td class="text-end"><?= number_format($row['llamadas']) ?></td>
                            <?php if ($categoriaFiltro === 'video'): ?>
                            <td class="text-end"><?= number_format($row['video_segundos']) ?></td>
                            <?php else: ?>
                            <td class="text-end"><?= number_format($row['tokens_prompt']) ?></td>
                            <td class="text-end"><?= number_format($row['tokens_respuesta']) ?></td>
                            <?php endif; ?>
                            <td class="text-end font-monospace">$<?= number_format($costDia, 4) ?></td>
                            <?php if ($categoriaFiltro === 'chat'): ?>
                            <td style="min-width:100px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:5px">
                                        <div class="progress-bar <?= $colorPct ?>" style="width:<?= $pctDia ?>%"></div>
                                    </div>
                                    <span class="text-muted" style="font-size:.7rem;min-width:28px"><?= $pctDia ?>%</span>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Configuración ─────────────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Configuración de precios</h6>
                <p class="text-muted small mb-0 mt-1">Chat: por token. Imagen: por token (igual mecanismo). Video: por segundo (Veo no reporta tokens).</p>
            </div>
            <div class="card-body">
                <form id="formConfig">
                    <?= Csrf::field() ?>

                    <div class="mb-3 p-2 rounded-3 border <?= $config['modo_solo_pago'] === '1' ? 'border-warning bg-warning-soft' : 'border-secondary-subtle' ?>">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="chkSoloPago" name="modo_solo_pago" value="1" <?= $config['modo_solo_pago'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="chkSoloPago">Usar SOLO la cuenta de pago</label>
                        </div>
                        <div class="form-text mb-0">
                            Apagado (default): el Chat usa la cuenta gratis y solo pasa a la de pago si se agota el cupo free.
                            Prendido: salta directo a <code>GEMINI_API_KEY_PAGO</code> siempre, sin tocar la cuota gratis.
                            Se aplica al toque, sin reiniciar nada.
                            <?php if (GEMINI_API_KEY_PAGO === ''): ?>
                            <br><span class="text-danger">⚠ Todavía no cargaste <code>GEMINI_API_KEY_PAGO</code> en el .env — si prendés esto sin esa key, el chat se queda sin forma de responder.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cuota gratuita diaria del Chat (llamadas)</label>
                        <input type="number" name="llamadas_gratuitas_dia" class="form-control form-control-sm"
                               value="<?= (int)$config['llamadas_gratuitas_dia'] ?>" min="0" step="1">
                        <div class="form-text">Solo aplica al Chat IA — imagen/video son de pago sin cuota diaria.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Costo por 1M tokens entrada (USD)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="costo_input_por_millon" class="form-control"
                                   value="<?= $config['costo_input_por_millon'] ?>" min="0" step="0.001">
                        </div>
                        <div class="form-text">Usado para Chat e Imagen.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Costo por 1M tokens salida (USD)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="costo_output_por_millon" class="form-control"
                                   value="<?= $config['costo_output_por_millon'] ?>" min="0" step="0.001">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Costo por segundo de video (USD)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="costo_video_por_segundo" class="form-control"
                                   value="<?= $config['costo_video_por_segundo'] ?>" min="0" step="0.01">
                        </div>
                        <div class="form-text">Estimado — verificá el precio real de Veo en <code>ai.google.dev/pricing</code>.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo de cambio USD → ARS</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" name="tipo_cambio_usd_ars" class="form-control"
                                   value="<?= (int)$config['tipo_cambio_usd_ars'] ?>" min="1" step="1">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">Guardar configuración</button>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- ── Límites por modelo (RPM/RPD) ───────────────────────────────────────── -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent">
                <h6 class="fw-semibold mb-0">Límites por modelo (RPM/RPD)</h6>
                <p class="text-muted small mb-0 mt-1">
                    Cadena de modelos configurada en <code>GEMINI_MODEL</code> / <code>GEMINI_MODEL_FALLBACK</code>.
                    Google no expone estos límites por API — cargalos a mano desde la sección
                    "Límite de frecuencia" de Google AI Studio. El chat elige, antes de cada mensaje
                    nuevo, el primer modelo de la lista que todavía tenga margen.
                </p>
            </div>
            <div class="card-body p-0">
                <?php if (!$modelosConUso): ?>
                <div class="text-muted small text-center py-4">No hay modelos configurados en <code>GEMINI_MODEL</code>.</div>
                <?php else: ?>
                <form id="formLimites">
                    <?= Csrf::field() ?>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Modelo</th>
                                    <th style="width:140px">RPM (límite)</th>
                                    <th style="width:140px">RPD (límite)</th>
                                    <th style="width:220px">Uso actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modelosConUso as $i => $m): ?>
                                <?php
                                    $rpmPct = $m['rpm'] ? min(100, round($m['uso']['rpm_usado'] / $m['rpm'] * 100)) : 0;
                                    $rpdPct = $m['rpd'] ? min(100, round($m['uso']['rpd_usado'] / $m['rpd'] * 100)) : 0;
                                    $colorPct = fn($p) => $p >= 90 ? 'bg-danger' : ($p >= 60 ? 'bg-warning' : 'bg-success');
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold font-monospace"><?= e($m['modelo']) ?></span>
                                        <?= $i === 0 ? '<span class="badge bg-primary-soft text-primary small ms-1">principal</span>' : '<span class="badge bg-secondary-soft text-secondary small ms-1">respaldo</span>' ?>
                                        <input type="hidden" name="modelo[]" value="<?= e($m['modelo']) ?>">
                                    </td>
                                    <td><input type="number" name="rpm[]" class="form-control form-control-sm" min="0" step="1" value="<?= $m['rpm'] !== null ? (int)$m['rpm'] : '' ?>" placeholder="sin límite"></td>
                                    <td><input type="number" name="rpd[]" class="form-control form-control-sm" min="0" step="1" value="<?= $m['rpd'] !== null ? (int)$m['rpd'] : '' ?>" placeholder="sin límite"></td>
                                    <td>
                                        <div class="small text-muted mb-1">
                                            <?= $m['uso']['rpm_usado'] ?><?= $m['rpm'] !== null ? ' / ' . $m['rpm'] . ' RPM' : ' RPM (sin límite)' ?>
                                            ·
                                            <?= $m['uso']['rpd_usado'] ?><?= $m['rpd'] !== null ? ' / ' . $m['rpd'] . ' RPD' : ' RPD (sin límite)' ?>
                                        </div>
                                        <?php if ($m['rpd'] !== null): ?>
                                        <div class="progress" style="height:5px">
                                            <div class="progress-bar <?= $colorPct($rpdPct) ?>" style="width:<?= $rpdPct ?>%"></div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar límites</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Errores recientes ──────────────────────────────────────────────────── -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                <h6 class="fw-semibold mb-0">Errores recientes (Chat, Galería imagen/video)</h6>
                <span class="text-muted small">Últimas <?= count($erroresRecientes) ?> fallas registradas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($erroresRecientes)): ?>
                <div class="text-muted small text-center py-4">Sin errores registrados recientemente.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:170px">Fecha</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($erroresRecientes as $err): ?>
                            <tr>
                                <td class="text-muted"><?= e($err['fecha']) ?></td>
                                <td style="word-break:break-all"><?= e($err['mensaje']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formConfig').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd  = new FormData(this);
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
        const r = await fetch('<?= url('gemini-uso/config') ?>', { method: 'POST', body: fd });
        const d = await r.json();
        showToast(d.ok ? d.mensaje : (d.error || 'Error'), d.ok ? 'success' : 'danger');
        if (d.ok) setTimeout(() => location.reload(), 800);
    } catch { showToast('Error de conexión.', 'danger'); }
    finally { btn.disabled = false; }
});

const formLimites = document.getElementById('formLimites');
if (formLimites) {
    formLimites.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd  = new FormData(this);
        const btn = this.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            const r = await fetch('<?= url('gemini-uso/limites') ?>', { method: 'POST', body: fd });
            const d = await r.json();
            showToast(d.ok ? d.mensaje : (d.error || 'Error'), d.ok ? 'success' : 'danger');
            if (d.ok) setTimeout(() => location.reload(), 800);
        } catch { showToast('Error de conexión.', 'danger'); }
        finally { btn.disabled = false; }
    });
}
</script>
