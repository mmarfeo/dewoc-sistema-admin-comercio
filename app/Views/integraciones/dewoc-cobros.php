<?php
/**
 * Integraciones → Dewoc Cobros (ver docs/dewoc-cobros-checklist.md, sección 1 "Recorrido del
 * usuario"). Muestra el paso en el que está el comercio: 1. contratar, 2. cargar la licencia,
 * 3. vincular celulares, 4. uso diario.
 *
 * El token de un celular nunca llega en el HTML: lo devuelve generar-token por AJAX y solo
 * vive en el modal del QR mientras está abierto.
 */
$est       = $estado['estado'];
$conLic    = $est !== 'sin_licencia';
$incluidos = (int)$estado['dispositivos'];
$lleno     = $usados >= $incluidos;

$badge = [
    'activa'       => ['bg-success-soft text-success', 'Activo'],
    'por_vencer'   => ['bg-warning-soft text-warning', 'Por vencer'],
    'gracia'       => ['bg-danger-soft text-danger',   'Vencido · en gracia'],
    'vencida'      => ['bg-danger-soft text-danger',   'Pausado · licencia vencida'],
    'sin_licencia' => ['bg-secondary-soft text-secondary', 'Sin contratar'],
][$est];

$estadoCelular = [
    'activo'         => ['text-success',   'Activo'],
    'esperando'      => ['text-secondary', 'Esperando que escanee el QR'],
    'sin_contacto'   => ['text-danger',    'Sin conexión'],
    'pausado_limite' => ['text-warning',   'Pausado por límite'],
    'revocado'       => ['text-muted',     'Revocado'],
];
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h5 class="fw-semibold mb-1">💰 Dewoc Cobros</h5>
        <p class="text-muted small mb-0">
            Las transferencias que llegan al celular del negocio aparecen solas en el POS, a los pocos
            segundos, con el monto y el nombre de quien pagó.
        </p>
    </div>
    <span class="badge <?= $badge[0] ?> px-3 py-2"><?= e($badge[1]) ?></span>
</div>

<?php if ($faltaMigracion): ?>
<div class="alert alert-danger small">
    Falta correr <code>database/licencias_migration.sql</code> y <code>database/dewoc_cobros_migration.sql</code>
    en la base. Hasta entonces Dewoc Cobros queda apagado.
</div>
<?php endif; ?>

<?php if ($avisoRenovacion): ?>
<div class="alert alert-danger small">
    <strong>No se pudo renovar la licencia:</strong> <?= e($avisoRenovacion) ?>
</div>
<?php endif; ?>

<?php if ($est === 'por_vencer'): ?>
<div class="alert alert-warning small">
    La licencia vence el <strong><?= e(fecha($estado['hasta'])) ?></strong>
    (<?= $estado['dias_restantes'] === 0 ? 'hoy' : 'en ' . (int)$estado['dias_restantes'] . ' días' ?>).
    Se renueva sola: no hace falta hacer nada.
</div>
<?php elseif ($est === 'gracia'): ?>
<div class="alert alert-danger small">
    La licencia venció el <strong><?= e(fecha($estado['hasta'])) ?></strong>. Dewoc Cobros sigue funcionando
    unos días más y después se pausa. La renovación automática no pudo completarse: revisá el aviso de arriba.
</div>
<?php elseif ($est === 'vencida'): ?>
<div class="alert alert-danger small">
    <strong>Dewoc Cobros está pausado:</strong> la licencia venció el <?= e(fecha($estado['hasta'])) ?>.
    Los celulares dejan de mandar cobros. Lo ya recibido sigue visible. Cuando se renueve la licencia vuelve a
    funcionar solo, sin volver a vincular los celulares.
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Pasos 1 y 2: contratar y cargar la licencia -->
    <div class="col-lg-5">
        <?php if (!$conLic): ?>
        <div class="card border-0 mb-4" style="background:linear-gradient(135deg,#10b98122,#0ea5e922)">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">1. Contratar Dewoc Cobros</h6>
                <ul class="small text-muted ps-3 mb-3">
                    <li>Una app para el celular Android del negocio (el que recibe las transferencias).</li>
                    <li>Cuando llega un cobro de Mercado Pago, el aviso aparece en el POS con el monto y el nombre.</li>
                    <li>El cajero no tiene que buscar el celular mientras la fila espera.</li>
                    <li>Se cobra por celular vinculado.</li>
                </ul>
                <p class="small mb-3">
                    Al contratarlo queda habilitado al instante por un mes, para 2 celulares, y después
                    se renueva solo.
                </p>
                <button class="btn btn-success" type="button" id="btnContratar" onclick="contratar(this)">Contratar Dewoc Cobros</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0">
            <div class="card-header border-0 bg-transparent fw-semibold">
                <?= $conLic ? 'Licencia' : 'Licencia' ?>
            </div>
            <div class="card-body">
                <?php if ($conLic): ?>
                <dl class="row small mb-3">
                    <dt class="col-5 text-muted fw-normal">Vigente hasta</dt>
                    <dd class="col-7"><?= e(fecha($estado['hasta'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Celulares</dt>
                    <dd class="col-7"><?= (int)$usados ?> de <?= $incluidos ?></dd>
                    <dt class="col-5 text-muted fw-normal">Código de instalación</dt>
                    <dd class="col-7"><span class="font-monospace" style="font-size:.78rem"><?= e($instalacionId) ?></span></dd>
                </dl>
                <?php endif; ?>

                <?php if (!$conLic): ?>
                <p class="small text-muted mb-2">Todavía no hay licencia. Se carga sola al tocar "Contratar".</p>
                <?php endif; ?>

                <details class="small">
                <summary class="text-muted">Cargar una licencia a mano</summary>
                <form id="formLicencia" class="mt-2">
                    <label class="form-label small" for="txtLicencia">
                        Solo si el proveedor te pasó una licencia (por ejemplo, para más celulares)
                    </label>
                    <textarea class="form-control font-monospace small mb-2" id="txtLicencia" name="licencia" rows="3"
                              placeholder="DWC1.…" spellcheck="false"></textarea>
                    <button class="btn btn-primary btn-sm" type="submit">Cargar licencia</button>
                </form>
                </details>

                <?php if (count($licencias) > 1): ?>
                <details class="mt-3 small">
                    <summary class="text-muted">Licencias cargadas (<?= count($licencias) ?>)</summary>
                    <table class="table table-sm small mt-2 mb-0">
                        <thead><tr><th>Licencia</th><th>Celulares</th><th>Período</th></tr></thead>
                        <tbody>
                        <?php foreach ($licencias as $l): ?>
                            <tr>
                                <td class="font-monospace"><?= e($l['licencia_id']) ?></td>
                                <td><?= (int)$l['dispositivos'] ?></td>
                                <td><?= e(fecha($l['desde'])) ?> a <?= e(fecha($l['hasta'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pasos 3 y 4: celulares -->
    <?php if ($conLic || $dispositivos): ?>
    <div class="col-lg-7">
        <div class="card border-0">
            <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Celulares <span class="text-muted fw-normal small">(<?= (int)$usados ?> de <?= $incluidos ?>)</span></span>
                <?php if ($habilitado): ?>
                <button class="btn btn-primary btn-sm" type="button" onclick="vincular()" <?= $lleno ? 'disabled' : '' ?>
                        title="<?= $lleno ? e("Tu licencia incluye {$incluidos} " . ($incluidos === 1 ? 'celular' : 'celulares')) : '' ?>">
                    Vincular celular
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($habilitado): ?>
                <ol class="small text-muted ps-3">
                    <li>Descargá e instalá la app <strong>Dewoc Cobros</strong> en el celular Android del negocio.</li>
                    <li>Abrila y dale los permisos que pide (acceso a notificaciones y batería).</li>
                    <li>Tocá <strong>Vincular celular</strong> acá y escaneá el QR con la app.</li>
                </ol>
                <?php if ($lleno): ?>
                <p class="small text-warning mb-3">
                    Tu licencia incluye <?= $incluidos ?> <?= $incluidos === 1 ? 'celular' : 'celulares' ?>. Para vincular otro,
                    revocá uno o pedile al proveedor una licencia con más celulares.
                </p>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (!$dispositivos): ?>
                <p class="small text-muted mb-0">Todavía no hay celulares vinculados.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead>
                            <tr><th>Celular</th><th>Estado</th><th>Último contacto</th><th>Versión</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dispositivos as $d): [$cls, $txt] = $estadoCelular[$d['estado']]; ?>
                            <tr class="<?= $d['estado'] === 'revocado' ? 'text-muted' : '' ?>">
                                <td>
                                    <?= e($d['nombre']) ?>
                                    <span class="d-block text-muted font-monospace" style="font-size:.7rem">…<?= e($d['token_ultimos4']) ?></span>
                                </td>
                                <td class="<?= $cls ?>">
                                    <?= e($txt) ?>
                                    <?php if ((int)$d['activo'] && $d['permiso_notificaciones'] !== null && !(int)$d['permiso_notificaciones']): ?>
                                    <span class="d-block text-danger" style="font-size:.72rem">Sin acceso a notificaciones</span>
                                    <?php endif; ?>
                                    <?php if ((int)$d['activo'] && (int)$d['optimizacion_bateria']): ?>
                                    <span class="d-block text-warning" style="font-size:.72rem">La batería puede cerrar la app</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $d['ultimo_contacto_at'] ? e(fechaHora($d['ultimo_contacto_at'])) : '—' ?></td>
                                <td><?= e($d['version_app'] ?? '—') ?></td>
                                <td class="text-end">
                                    <?php if ((int)$d['activo']): ?>
                                    <button class="btn btn-outline-danger btn-sm" type="button"
                                            onclick="revocar(<?= (int)$d['id'] ?>, <?= e(json_encode($d['nombre'])) ?>)">Revocar</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($conLic || $notificaciones): ?>
<!-- Notificaciones recibidas: para depurar el lector y para la conciliación (1.6) -->
<?php
$etiquetaResultado = [
    'cobro'         => ['bg-success-soft text-success',     'Cobro'],
    'descartado'    => ['bg-secondary-soft text-secondary', 'Descartada'],
    'no_reconocido' => ['bg-warning-soft text-warning',     'No reconocida'],
    'sin_lector'    => ['bg-warning-soft text-warning',     'Sin lector'],
    'prueba'        => ['bg-info-soft text-info',           'Prueba'],
];
?>
<div class="card border-0 mt-4" id="notificaciones">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-semibold">Últimas notificaciones recibidas</span>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="<?= url('integraciones/dewoc-cobros') ?>#notificaciones">
                <select name="resultado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['' => 'Todas', 'cobro' => 'Cobros', 'sin_venta' => 'Cobros sin venta',
                                    'no_reconocido' => 'No reconocidas', 'sin_lector' => 'Sin lector',
                                    'descartado' => 'Descartadas', 'prueba' => 'Pruebas'] as $val => $txt): ?>
                    <option value="<?= e($val) ?>" <?= $filtroResultado === $val ? 'selected' : '' ?>><?= e($txt) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($habilitado): ?>
            <button class="btn btn-outline-primary btn-sm text-nowrap" type="button" onclick="volverALeer()"
                    title="Pasa de nuevo por el lector las no reconocidas y sin lector de los últimos 7 días">Volver a leer</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$notificaciones): ?>
        <p class="small text-muted p-3 mb-0">No hay notificaciones<?= $filtroResultado !== '' ? ' con ese filtro' : '' ?>.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle small mb-0">
                <thead class="table-light">
                    <tr><th>Recibida</th><th>App</th><th>Resultado</th><th class="text-end">Monto</th><th>Nombre</th><th>Venta</th><th>Texto original</th></tr>
                </thead>
                <tbody>
                <?php foreach ($notificaciones as $n): [$cls, $txt] = $etiquetaResultado[$n['resultado']]; ?>
                    <tr>
                        <td class="text-nowrap"><?= e(fechaHora($n['recibido_at'])) ?></td>
                        <td><?= e($n['app']) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= e($txt) ?></span></td>
                        <td class="text-end text-nowrap">
                            <?= $n['resultado'] === 'cobro' ? ($n['monto'] !== null ? e(moneda((float)$n['monto'])) : '<span class="text-muted">oculto</span>') : '' ?>
                        </td>
                        <td><?= e($n['nombre'] ?? '') ?></td>
                        <td>
                            <?php if ($n['venta_id']): ?>
                            <a href="<?= url('ventas/ver?id=' . (int)$n['venta_id']) ?>">#<?= (int)$n['venta_id'] ?></a>
                            <?php elseif ($n['resultado'] === 'cobro'): ?>
                            <span class="text-warning">Sin venta</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="max-width:380px">
                            <?php if ($n['titulo']): ?><strong><?= e($n['titulo']) ?></strong><br><?php endif; ?>
                            <?= e($n['texto_largo'] ?: ($n['texto'] ?? '')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- QR de vinculación: el token vive solo acá, mientras el modal está abierto -->
<div class="modal fade" id="modalQr" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Vincular celular</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <!-- Paso 1: nombre del celular -->
            <form class="modal-body" id="pasoNombre">
                <label class="form-label small fw-semibold" for="inpNombreCelular">Nombre del celular</label>
                <input type="text" class="form-control" id="inpNombreCelular" maxlength="80" placeholder='Por ejemplo "Moto G caja"' autocomplete="off">
                <div class="form-text">Para reconocerlo en la lista. Si lo dejás vacío, se llama "Celular N".</div>
                <div id="errVincular" class="alert alert-danger small py-2 mt-3 mb-0 d-none"></div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGenerarQr">Generar QR</button>
                </div>
            </form>
            <!-- Paso 2: el QR -->
            <div class="modal-body text-center d-none" id="pasoQr">
                <p class="small text-muted mb-2">Abrí Dewoc Cobros en el celular y escaneá este código.</p>
                <canvas id="qrCanvas" style="max-width:100%"></canvas>
                <div id="qrEstado" class="small fw-semibold text-secondary my-2">Esperando que el celular escanee el QR…</div>
                <details class="text-start small">
                    <summary class="text-muted">¿No podés escanear? Pegá el token en la app</summary>
                    <div class="input-group input-group-sm mt-2">
                        <input type="text" class="form-control font-monospace" id="inpToken" readonly>
                        <button class="btn btn-outline-dark" type="button" onclick="copiar('inpToken', this)">Copiar</button>
                    </div>
                </details>
                <p class="text-muted mt-3 mb-0" style="font-size:.72rem">
                    El código se muestra una sola vez. Si cerrás esta ventana sin escanearlo, revocá ese celular y generá otro.
                </p>
            </div>
        </div>
    </div>
</div>

<?php if ($esServidorLicencias): ?>
<!-- Servidor de licencias: solo en la instalación que las emite (LICENCIAS_SERVIDOR=1) -->
<div class="card border-0 mt-4">
    <div class="card-header border-0 bg-transparent fw-semibold">
        Servidor de licencias · comercios
        <span class="text-muted fw-normal small">— cada Dewoc pide y renueva su licencia solo; la baja frena la renovación</span>
    </div>
    <div class="card-body pt-0">
        <?php if (!$clientes): ?>
        <p class="small text-muted mb-0">Todavía ningún comercio pidió una licencia.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm small align-middle mb-0">
            <thead><tr><th>Comercio</th><th>Celulares</th><th>Vence</th><th>Emisiones</th><th>Último pedido</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <?= e($c['comercio']) ?>
                        <?php if ($c['url']): ?><div class="text-muted" style="font-size:.72rem"><?= e($c['url']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?= (int)$c['dispositivos'] ?>
                        <button class="btn btn-link btn-sm p-0 ms-1" type="button"
                                onclick="clienteDispositivos(<?= (int)$c['id'] ?>, <?= (int)$c['dispositivos'] ?>)">cambiar</button>
                    </td>
                    <td><?= $c['ultima_hasta'] ? e(fecha($c['ultima_hasta'])) : '—' ?></td>
                    <td><?= (int)$c['emisiones'] ?></td>
                    <td><?= $c['ultima_solicitud_at'] ? e(date('d/m H:i', strtotime($c['ultima_solicitud_at']))) : '—' ?></td>
                    <td>
                        <?php if ($c['estado'] === 'baja'): ?><span class="badge bg-danger-soft text-danger">Baja</span>
                        <?php else: ?><span class="badge bg-success-soft text-success">Activo</span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($c['estado'] === 'baja'): ?>
                        <button class="btn btn-outline-success btn-sm" type="button"
                                onclick="clienteEstado(<?= (int)$c['id'] ?>, 'activo', <?= e(json_encode($c['comercio'])) ?>)">Reactivar</button>
                        <?php else: ?>
                        <button class="btn btn-outline-danger btn-sm" type="button"
                                onclick="clienteEstado(<?= (int)$c['id'] ?>, 'baja', <?= e(json_encode($c['comercio'])) ?>)">Dar de baja</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script>
const CSRF_COBROS = '<?= Csrf::token() ?>';
const URL_COBROS  = '<?= url('integraciones/dewoc-cobros') ?>';
let consultaQr = null;

/** Copia el valor del campo y lo confirma en el mismo botón (y con un aviso). */
function copiar(id, boton) {
    const inp = document.getElementById(id);
    inp.select();
    const listo = () => {
        showToast('Copiado al portapapeles.');
        if (boton) {
            const txt = boton.textContent;
            boton.textContent = '✓ Copiado';
            boton.classList.replace('btn-outline-dark', 'btn-success');
            setTimeout(() => { boton.textContent = txt; boton.classList.replace('btn-success', 'btn-outline-dark'); }, 1600);
        }
    };
    // navigator.clipboard solo existe con https (o localhost); si no, el método viejo.
    if (navigator.clipboard) {
        navigator.clipboard.writeText(inp.value).then(listo, () => showToast('No se pudo copiar: copialo a mano con Ctrl+C.', 'warning'));
    } else {
        try { document.execCommand('copy') ? listo() : showToast('Copialo a mano con Ctrl+C.', 'warning'); }
        catch (_) { showToast('Copialo a mano con Ctrl+C.', 'warning'); }
    }
}

/** Pide la licencia al servidor y la carga: el comercio no copia ni pega nada (C22). */
async function contratar(boton) {
    boton.disabled = true;
    boton.textContent = 'Contratando…';
    try {
        resultado(await postCobros('contratar', {}), 'No se pudo contratar.');
    } catch (_) {
        showToast('Error de conexión.', 'danger');
    } finally {
        boton.disabled = false;
        boton.textContent = 'Contratar Dewoc Cobros';
    }
}

/** Panel del servidor de licencias. */
function clienteEstado(id, estado, comercio) {
    const baja = estado === 'baja';
    showConfirm(baja ? `¿Dar de baja a ${comercio}?` : `¿Reactivar a ${comercio}?`, async () => {
        try {
            resultado(await postCobros('cliente-estado', { id, estado }), 'No se pudo cambiar el estado.');
        } catch (_) {
            showToast('Error de conexión.', 'danger');
        }
    }, baja
        ? { type: 'danger', label: 'Dar de baja', sub: 'No se le renueva más la licencia. La que ya tiene sigue funcionando hasta que vence.' }
        : { type: 'primary', label: 'Reactivar', sub: 'Vuelve a recibir licencias nuevas automáticamente.' });
}

function clienteDispositivos(id, actual) {
    showPrompt('Celulares de las próximas licencias', async (cant) => {
        try {
            resultado(await postCobros('cliente-dispositivos', { id, dispositivos: cant }), 'No se pudo cambiar la cantidad.');
        } catch (_) {
            showToast('Error de conexión.', 'danger');
        }
    }, { sub: 'Entre 1 y 50. Se aplica desde la próxima renovación.', valor: String(actual), placeholder: '2', maxLength: 2, label: 'Guardar' });
}

async function postCobros(ruta, datos) {
    const fd = datos instanceof FormData ? datos : new FormData();
    if (!(datos instanceof FormData)) for (const [k, v] of Object.entries(datos || {})) fd.append(k, v);
    fd.append('_csrf_token', CSRF_COBROS);
    const r = await fetch(`${URL_COBROS}/${ruta}`, { method: 'POST', body: fd });
    return r.json();
}

/** Muestra el resultado y, si salió bien, recarga la pantalla un momento después. */
function resultado(d, error) {
    if (d.ok) { showToast(d.mensaje); setTimeout(() => location.reload(), 1200); }
    else showToast(d.error || error, 'danger');
}

document.getElementById('formLicencia').addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const boton = this.querySelector('button[type="submit"]');
    boton.disabled = true;
    try {
        resultado(await postCobros('cargar-licencia', new FormData(this)), 'No se pudo cargar la licencia.');
    } catch (_) {
        showToast('Error de conexión.', 'danger');
    } finally {
        boton.disabled = false;
    }
});

// ── Vincular celular: paso 1 (nombre) y paso 2 (QR) en el mismo modal ──────
function vincular() {
    document.getElementById('pasoNombre').classList.remove('d-none');
    document.getElementById('pasoQr').classList.add('d-none');
    document.getElementById('errVincular').classList.add('d-none');
    document.getElementById('inpNombreCelular').value = '';
    mostrarModal('modalQr');
}

document.getElementById('modalQr').addEventListener('shown.bs.modal', () => {
    if (!document.getElementById('pasoNombre').classList.contains('d-none')) document.getElementById('inpNombreCelular').focus();
});

document.getElementById('pasoNombre').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const boton = document.getElementById('btnGenerarQr');
    const err   = document.getElementById('errVincular');
    boton.disabled = true;
    let d;
    try {
        d = await postCobros('generar-token', { nombre: document.getElementById('inpNombreCelular').value });
    } catch (_) {
        d = { ok: false, error: 'Error de conexión.' };
    }
    boton.disabled = false;
    if (!d.ok) { err.textContent = d.error || 'No se pudo generar el código.'; err.classList.remove('d-none'); return; }

    document.getElementById('inpToken').value = d.token;
    QRCode.toCanvas(document.getElementById('qrCanvas'), d.qr, { width: 260, margin: 2 }, () => {});
    document.getElementById('pasoNombre').classList.add('d-none');
    document.getElementById('pasoQr').classList.remove('d-none');
    window._celularNuevo = true;

    // Mientras el QR está a la vista, se consulta si el celular ya se conectó (1.6).
    const estado = document.getElementById('qrEstado');
    consultaQr = setInterval(async () => {
        try {
            const r = await fetch(`${URL_COBROS}/dispositivo?id=${d.dispositivo_id}`, { headers: { Accept: 'application/json' } });
            const e = await r.json();
            if (e.ok && e.conectado) {
                clearInterval(consultaQr);
                estado.className = 'small fw-semibold text-success my-2';
                estado.textContent = '✅ Celular conectado: ya está activo.';
            }
        } catch (_) { /* en silencio: se reintenta en la próxima vuelta */ }
    }, 3000);
});

document.getElementById('modalQr').addEventListener('hidden.bs.modal', () => {
    clearInterval(consultaQr);
    document.getElementById('inpToken').value = '';
    // Solo si se llegó a generar un celular: si se canceló en el paso 1, no hay nada nuevo.
    if (window._celularNuevo) location.reload();
});

/**
 * Dashly bundlea Bootstrap sin exponer window.bootstrap (ver ventas/index.php): si no está,
 * el modal se abre con un botón oculto con data-bs-toggle, que Bootstrap sí escucha.
 */
function mostrarModal(id) {
    const el = document.getElementById(id);
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getOrCreateInstance(el).show(); return; }
    let btn = document.getElementById('_trigger_' + id);
    if (!btn) {
        btn = document.createElement('button');
        btn.id = '_trigger_' + id; btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-toggle', 'modal'); btn.setAttribute('data-bs-target', '#' + id);
        document.body.appendChild(btn);
    }
    btn.click();
}

async function volverALeer() {
    resultado(await postCobros('volver-a-leer'), 'No se pudo volver a leer.');
}

function revocar(id, nombre) {
    showConfirm(`¿Revocar "${nombre}"?`, async () => {
        resultado(await postCobros('revocar', { id }), 'No se pudo revocar.');
    }, { label: 'Revocar', sub: 'Deja de mandar cobros al instante y la app vuelve a la pantalla de vincular.' });
}
</script>
