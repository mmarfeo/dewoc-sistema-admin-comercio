<?php
/**
 * Integraciones → MenuApp (ver docs/integracion-menuapp-checklist.md, tarea 0.4).
 * El token en claro ($tokenNuevo) solo llega acá una vez, justo después de generarlo.
 * El secreto del webhook nunca se imprime en el HTML: el campo va vacío y en blanco
 * significa "no cambiar".
 */
$activa = (int)($config['activa'] ?? 0) === 1;
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h5 class="fw-semibold mb-1">MenuApp</h5>
        <p class="text-muted small mb-0">
            Menú online, pedidos y cobro. Dewoc le pasa el catálogo, las imágenes y la disponibilidad;
            MenuApp le devuelve cada pedido aceptado como una venta.
        </p>
    </div>
    <span class="badge <?= $activa ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?> px-3 py-2">
        <?= $activa ? 'Integración activa' : 'Integración desactivada' ?>
    </span>
</div>

<?php if (!$activa): ?>
<!--
  Promoción cruzada (decisión D13): si el comercio todavía no conectó MenuApp, se le
  cuenta qué gana. Se oculta apenas la integración queda activa, para no dejar un aviso
  publicitario permanente en una pantalla de configuración que ya cumplió su función.
-->
<div class="card border-0 mb-4" style="background:linear-gradient(135deg,#6366f122,#8b5cf622)">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h6 class="fw-semibold mb-1">Vendé online con MenuApp</h6>
            <ul class="small text-muted mb-0 ps-3">
                <li>Menú online con fotos, siempre sincronizado con tus productos de Dewoc.</li>
                <li>Pedidos por delivery, retiro en el local y mesa.</li>
                <li>Cobro online con Mercado Pago.</li>
                <li>Cada pedido que aceptás entra como venta en Dewoc y descuenta el stock solo.</li>
            </ul>
        </div>
        <a href="https://menuapp.dewoc.com" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
            Conocer MenuApp
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($tokenNuevo): ?>
<div class="alert alert-warning">
    <div class="fw-semibold mb-1">Copiá el token ahora: no se vuelve a mostrar</div>
    <p class="small mb-2">Pegalo en MenuApp, en la configuración del conector "Dewoc". Dewoc solo guarda su huella, no el token.</p>
    <div class="input-group">
        <input type="text" class="form-control font-monospace" id="inpTokenNuevo" value="<?= e($tokenNuevo) ?>" readonly>
        <button class="btn btn-outline-dark" type="button" onclick="copiarToken()">Copiar</button>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($config['ultimo_error'])): ?>
<div class="alert alert-danger small">
    <strong>Último error:</strong> <?= e($config['ultimo_error']) ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Credenciales -->
    <div class="col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Token de la API</div>
            <div class="card-body">
                <p class="small text-muted">
                    MenuApp se autentica con <code>Authorization: Bearer &lt;token&gt;</code> contra
                    <code><?= e(rtrim(BASE_URL, '/')) ?>/api/menuapp/v1/…</code>
                </p>

                <dl class="row small mb-3">
                    <dt class="col-5 text-muted fw-normal">ID de instalación</dt>
                    <dd class="col-7">
                        <span class="font-monospace" style="font-size:.78rem"><?= e($instalacionId) ?></span>
                        <span class="d-block text-muted" style="font-size:.72rem">
                            Fijo: no cambia aunque regeneres el token. MenuApp lo usa para no conectar
                            esta instalación a dos comercios.
                        </span>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Comercio</dt>
                    <dd class="col-7">
                        <?= e(NEGOCIO_NOMBRE) ?>
                        <?= $cuit ? '<span class="text-muted">· CUIT ' . e($cuit) . '</span>' : '<span class="text-muted">· sin CUIT configurado</span>' ?>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Token actual</dt>
                    <dd class="col-7">
                        <?php if (!empty($config['token_ultimos4'])): ?>
                        <span class="font-monospace">••••••••<?= e($config['token_ultimos4']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">Sin generar</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Generado</dt>
                    <dd class="col-7"><?= !empty($config['token_generado_at']) ? fechaHora($config['token_generado_at']) : '—' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Último acceso de MenuApp</dt>
                    <dd class="col-7"><?= !empty($config['ultimo_acceso_at']) ? fechaHora($config['ultimo_acceso_at']) : 'Todavía no se conectó' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Usuario técnico</dt>
                    <dd class="col-7">
                        <?= $usuarioTecnico ? e($usuarioTecnico['nombre']) : '<span class="text-muted">Se crea al activar</span>' ?>
                    </dd>
                </dl>

                <button class="btn btn-primary btn-sm" onclick="generarToken()">
                    <?= !empty($config['token_hash']) ? 'Regenerar token' : 'Generar token' ?>
                </button>
                <?php if (!empty($config['token_hash'])): ?>
                <div class="small text-muted mt-2">Regenerar invalida el token anterior al instante: MenuApp deja de poder entrar hasta que le peguen el nuevo.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Webhook y parámetros -->
    <div class="col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Avisos de cambios (webhook)</div>
            <div class="card-body">
                <form id="formMenuApp">
                    <input type="hidden" name="_csrf_token" value="<?= Csrf::token() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">URL del webhook</label>
                        <input type="url" name="webhook_url" class="form-control form-control-sm"
                               placeholder="https://menuapp.dewoc.com/webhooks/dewoc/serendipia"
                               value="<?= e($config['webhook_url'] ?? '') ?>">
                        <small class="text-muted">Dewoc avisa acá cuando cambia el catálogo, y MenuApp vuelve a pedir el catálogo completo.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Secreto del webhook</label>
                        <input type="password" name="webhook_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="<?= !empty($config['webhook_secret']) ? 'Ya configurado — dejalo vacío para no cambiarlo' : 'Pegá el secreto que genera MenuApp' ?>">
                        <small class="text-muted">Lo genera MenuApp. Firma cada aviso con HMAC-SHA256 en el header <code>X-Signature</code>.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Producto con el que se factura el envío</label>
                        <select name="producto_envio_id" class="form-select form-select-sm">
                            <option value="">Sin configurar</option>
                            <?php foreach ($productos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)($config['producto_envio_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            Tiene que ser un producto sin control de stock y sin receta (ej. "Envío a domicilio").
                            Así el envío queda visible en los reportes en vez de perderse dentro del total.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Menú público del comercio en MenuApp</label>
                        <input type="url" name="menu_url" class="form-control form-control-sm"
                               placeholder="https://menuapp.dewoc.com/serendipia"
                               value="<?= e($config['menu_url'] ?? '') ?>">
                        <small class="text-muted">Se usa para redirigir la vieja tienda online de Dewoc cuando se la dé de baja.</small>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="probarConexion()">Probar conexión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Estado de la caja, tal como lo ve MenuApp (D10) -->
    <div class="col-12">
        <div class="card border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h6 class="fw-semibold mb-0">Caja — lo que ve MenuApp ahora</h6>
                    <span class="badge <?= $cajaAbierta ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?> px-3 py-2">
                        <?= $cajaAbierta ? 'Caja abierta · el menú toma pedidos' : 'Caja cerrada · el menú no toma pedidos' ?>
                    </span>
                </div>
                <p class="small text-muted mb-2">
                    <?php if ($cajaAbierta): ?>
                    Abierta desde <strong><?= fechaHora($cajaDesde) ?></strong>
                    (<?= (int)$turnosAbiertos ?> turno<?= $turnosAbiertos === 1 ? '' : 's' ?> abierto<?= $turnosAbiertos === 1 ? '' : 's' ?>).
                    MenuApp deja de tomar pedidos en cuanto se cierre el último.
                    <?php else: ?>
                    Con la caja cerrada el menú se ve, pero el cliente no puede pedir. Se reabre solo al abrir un turno en
                    <a href="<?= url('caja') ?>">Caja</a>.
                    <?php endif; ?>
                </p>
                <?php if ($cobrosPendientesMenuApp > 0): ?>
                <div class="alert alert-warning small mb-0">
                    Hay <strong><?= (int)$cobrosPendientesMenuApp ?></strong>
                    pedido<?= $cobrosPendientesMenuApp === 1 ? '' : 's' ?> de MenuApp cobrado<?= $cobrosPendientesMenuApp === 1 ? '' : 's' ?>
                    en mano sin imputar a ninguna caja. Se imputan desde <a href="<?= url('caja') ?>">Caja</a>.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Activación -->
    <div class="col-12">
        <div class="card border-0">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="fw-semibold mb-1"><?= $activa ? 'Desactivar la integración' : 'Activar la integración' ?></div>
                    <p class="small text-muted mb-0">
                        <?php if ($activa): ?>
                        Al desactivarla, la API responde 401 a todas las llamadas y Dewoc deja de enviar avisos.
                        El catálogo y las ventas ya registradas no se tocan.
                        <?php else: ?>
                        Al activarla se crea el usuario "MenuApp (sistema)" (sin acceso al login) con el que quedan
                        registradas las ventas que lleguen por la API.
                        <?php endif; ?>
                    </p>
                </div>
                <button class="btn btn-sm <?= $activa ? 'btn-outline-danger' : 'btn-success' ?>"
                        onclick="cambiarActiva(<?= $activa ? 0 : 1 ?>)">
                    <?= $activa ? 'Desactivar' : 'Activar' ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_MENUAPP = '<?= Csrf::token() ?>';

function copiarToken() {
    const inp = document.getElementById('inpTokenNuevo');
    inp.select();
    navigator.clipboard?.writeText(inp.value).then(
        () => alert('Token copiado al portapapeles.'),
        () => alert('Copialo a mano: Ctrl+C.')
    );
}

async function postMenuApp(ruta, extra = {}) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_MENUAPP);
    for (const [k, v] of Object.entries(extra)) fd.append(k, v);
    const r = await fetch(`<?= url('integraciones') ?>/${ruta}`, { method: 'POST', body: fd });
    return r.json();
}

async function generarToken() {
    if (!confirm('¿Generar un token nuevo? Si ya había uno, deja de funcionar al instante y hay que actualizarlo en MenuApp.')) return;
    const d = await postMenuApp('generar-token');
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo generar el token.');
}

async function cambiarActiva(activa) {
    const d = await postMenuApp('activar', { activa });
    if (d.ok) { alert(d.mensaje); location.reload(); }
    else alert(d.error || 'No se pudo cambiar el estado.');
}

async function probarConexion() {
    const d = await postMenuApp('probar');
    alert(d.mensaje);
}

document.getElementById('formMenuApp').addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const r = await fetch('<?= url('integraciones/guardar') ?>', { method: 'POST', body: new FormData(this) });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert(d.error || 'No se pudo guardar.');
});
</script>
