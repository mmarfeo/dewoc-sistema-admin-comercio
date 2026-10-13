<?php
/**
 * Vista previa de un producto, igual que en el menú online (ver
 * docs/integracion-menuapp-checklist.md, tareas 8.3 a 8.7).
 *
 * Los dos recortes están hechos con CSS propio que imita a MenuApp, NO con un iframe:
 *   - tarjeta del listado → 4:3, object-fit: cover;
 *   - detalle del producto → ancho completo, alto máximo 240 px, object-fit: cover.
 * Son recortes distintos a propósito: una foto puede verse bien en la tarjeta y cortar el
 * producto en el detalle, y la idea de esta pantalla es justamente que eso se vea ANTES de
 * publicar. Si en MenuApp cambian esas medidas, hay que cambiarlas acá también.
 */
$sinStock = (int)$producto['controla_stock'] === 1 && (float)$producto['stock_actual'] < 1;
$urlFoto  = fn(array $f): string => url('galeria/ver?id=' . (int)$f['id']);
$urlOficial = !empty($producto['imagen'])
    ? url('uploads/productos/' . rawurlencode($producto['imagen']))
    : null;
?>
<style>
/* ── Recortes que imitan al menú de MenuApp ───────────────────────────────── */
.mp-card         { max-width: 320px; border-radius: .6rem; overflow: hidden; background: #fff;
                   box-shadow: 0 1px 4px rgba(0,0,0,.12); }
.mp-card-foto    { aspect-ratio: 4 / 3; width: 100%; background: #eee; }
.mp-detalle-foto { width: 100%; height: 240px; background: #eee; }
.mp-card-foto img, .mp-detalle-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
/* Agotado: MenuApp muestra la tarjeta en gris y semitransparente. */
.mp-agotado      { filter: grayscale(1); opacity: .55; }
.mp-sinfoto      { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
                   color: #adb5bd; background: #f1f3f5; }
.mp-mini         { aspect-ratio: 4 / 3; background: #eee; border-radius: .4rem; overflow: hidden; }
.mp-mini-ancho   { height: 90px; background: #eee; border-radius: .4rem; overflow: hidden; }
.mp-mini img, .mp-mini-ancho img { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h5 mb-1 fw-bold"><?= e($producto['nombre']) ?></h1>
        <p class="text-muted small mb-0">Así se ve en el menú online</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($menuUrl): ?>
        <a href="<?= e(rtrim($menuUrl, '/')) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
            Ver en el menú real
        </a>
        <?php endif; ?>
        <a href="<?= url('galeria/fotos-menu') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<?php if (!$producto['ecommerce']): ?>
<div class="alert alert-warning small">
    Este producto <strong>no está marcado</strong> para el menú online, así que el cliente no lo ve.
    Se marca con "Mostrar en el menú online" en la ficha del producto.
</div>
<?php endif; ?>

<div id="fotosMenuAviso" class="alert d-none small"></div>

<div class="row g-4">
    <!-- ── Cómo se ve hoy ──────────────────────────────────────────────── -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Tarjeta del listado</div>
            <div class="card-body">
                <div class="mp-card <?= $sinStock ? 'mp-agotado' : '' ?>">
                    <div class="mp-card-foto">
                        <?php if ($urlOficial): ?>
                        <img src="<?= e($urlOficial) ?>" alt="<?= e($producto['nombre']) ?>">
                        <?php else: ?>
                        <div class="mp-sinfoto">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                            </svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-2">
                        <div class="fw-semibold small"><?= e($producto['nombre']) ?></div>
                        <?php if (!empty($producto['descripcion'])): ?>
                        <div class="text-muted" style="font-size:.78rem"><?= e(mb_strimwidth((string)$producto['descripcion'], 0, 70, '…')) ?></div>
                        <?php endif; ?>
                        <div class="fw-bold text-primary mt-1"><?= moneda($precioFinal) ?></div>
                    </div>
                </div>

                <?php if ($sinStock): ?>
                <div class="small text-muted mt-2">
                    Sin stock: en el menú la tarjeta se ve en gris y no se puede pedir.
                </div>
                <?php endif; ?>
                <div class="small text-muted mt-2">
                    El precio sale de la misma cuenta que usa el menú, así que es el que ve el cliente.
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card border-0 h-100">
            <div class="card-header border-0 bg-transparent fw-semibold">Detalle del producto</div>
            <div class="card-body">
                <div class="mp-detalle-foto rounded">
                    <?php if ($urlOficial): ?>
                    <img src="<?= e($urlOficial) ?>" alt="<?= e($producto['nombre']) ?>">
                    <?php else: ?>
                    <div class="mp-sinfoto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-2">
                    El detalle recorta más ancho que la tarjeta: fijate que no corte el producto.
                </div>

                <?php if ($anterior && $puedePublicar): ?>
                <button class="btn btn-outline-secondary btn-sm mt-3" onclick="volverAnterior()">
                    Volver a la foto anterior
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Sacar o elegir una foto (tarea 8.4) ──────────────────────────────── -->
<div class="card border-0 mt-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
            <h6 class="fw-semibold mb-0">
                Candidatas
                <span class="text-muted fw-normal small">
                    (<span id="contadorCandidatas"><?= count($candidatas) ?></span> de <?= (int)$maxCandidatas ?>)
                </span>
            </h6>
            <span class="small text-muted">Mejoras con IA que te quedan hoy: <?= (int)$mejorasRestantes ?></span>
        </div>

        <?php $lleno = count($candidatas) >= (int)$maxCandidatas; ?>
        <?php if ($lleno): ?>
        <div class="alert alert-info py-2 small mt-2 mb-3">Descartá una para sumar otra.</div>
        <?php endif; ?>

        <div class="row g-2 mt-2">
            <div class="col-6">
                <!-- capture="environment" abre directo la cámara trasera en el celular -->
                <button class="btn btn-primary w-100 py-3" id="btnSacarFoto" <?= $lleno ? 'disabled' : '' ?>
                        onclick="document.getElementById('inpCamara').click()">
                    📷 Sacar foto
                </button>
                <input type="file" id="inpCamara" accept="image/*" capture="environment" hidden>
            </div>
            <div class="col-6">
                <button class="btn btn-outline-primary w-100 py-3" id="btnElegirFoto" <?= $lleno ? 'disabled' : '' ?>
                        onclick="document.getElementById('inpArchivo').click()">
                    🖼 Elegir de la galería
                </button>
                <input type="file" id="inpArchivo" accept="image/*" hidden>
            </div>
        </div>

        <!-- Previsualización antes de subir, en los DOS recortes, para encuadrar bien -->
        <div id="previoSubida" class="d-none mt-3">
            <div class="small fw-semibold mb-2">Así va a quedar. ¿La subís?</div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="mp-mini"><img id="previoTarjeta" alt="Vista previa en la tarjeta"></div>
                    <div class="small text-muted text-center mt-1">Tarjeta</div>
                </div>
                <div class="col-6">
                    <div class="mp-mini-ancho"><img id="previoDetalle" alt="Vista previa en el detalle"></div>
                    <div class="small text-muted text-center mt-1">Detalle</div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-success btn-sm flex-grow-1" id="btnConfirmarSubida" onclick="subirCandidata()">Subir</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="cancelarPrevio()">Cancelar</button>
            </div>
            <div class="small text-muted mt-2">
                La foto se achica en tu celular antes de subir, así no falla con mala señal ni gasta datos de más.
            </div>
        </div>
    </div>
</div>

<!-- ── Candidatas cargadas ──────────────────────────────────────────────── -->
<?php if (empty($candidatas)): ?>
<div class="card border-0 mt-3">
    <div class="card-body text-center text-muted py-4 small">
        Todavía no hay candidatas. Saca una foto del producto para empezar.
    </div>
</div>
<?php else: ?>
<?php foreach ($candidatas as $c): ?>
<div class="card border-0 mt-3" data-foto="<?= (int)$c['id'] ?>">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="small text-muted">
                <?= fechaHora($c['created_at']) ?>
                <?php if ((int)$c['mejorada_ia'] === 1): ?>
                <span class="badge bg-info-soft text-info ms-1">Mejorada con IA</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Cada candidata dibujada en los dos recortes reales del menú -->
        <div class="row g-2">
            <div class="col-6">
                <div class="mp-mini"><img src="<?= $urlFoto($c) ?>" alt="Candidata en la tarjeta"></div>
                <div class="small text-muted text-center mt-1">Tarjeta</div>
            </div>
            <div class="col-6">
                <div class="mp-mini-ancho"><img src="<?= $urlFoto($c) ?>" alt="Candidata en el detalle"></div>
                <div class="small text-muted text-center mt-1">Detalle</div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if ($puedePublicar): ?>
            <button class="btn btn-success btn-sm" onclick="elegirOficial(<?= (int)$c['id'] ?>)">Elegir como oficial</button>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                        <?= ($lleno || (int)$mejorasRestantes <= 0) ? 'disabled' : '' ?>>
                    Mejorar con IA
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($mejorasIa as $clave => $label): ?>
                    <li>
                        <button class="dropdown-item small" onclick="mejorar(<?= (int)$c['id'] ?>, '<?= e($clave) ?>')">
                            <?= e($label) ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="btn btn-outline-danger btn-sm" onclick="descartar(<?= (int)$c['id'] ?>)">Descartar</button>
        </div>

        <?php if ((int)$mejorasRestantes <= 0): ?>
        <div class="small text-muted mt-2">Llegaste al límite de mejoras con IA por hoy.</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= Csrf::token() ?>';
const PRODUCTO_ID = <?= (int)$producto['id'] ?>;

// Lado mayor y calidad del achicado en el navegador. Tienen que acompañar a
// ImagenHelper::LADO_MAXIMO y CALIDAD_JPEG del servidor: si acá se sube algo más grande,
// el servidor lo vuelve a achicar y el trabajo se hace dos veces.
const LADO_MAXIMO = 1600;
const CALIDAD     = 0.85;

let _archivoListo = null; // Blob ya achicado, esperando confirmación

function aviso(texto, tipo = 'danger') {
    const el = document.getElementById('fotosMenuAviso');
    el.textContent = texto;
    el.className = 'alert alert-' + tipo + ' small';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Achica la foto en el navegador ANTES de subirla. Una foto de un celular actual pesa
 * entre 3 y 12 MB y el límite de subida son 5 MB: sin esto, la subida falla justo donde
 * más molesta (un empleado, en el local, con el producto en la mano).
 *
 * Se dibuja en un canvas, lo que de paso aplica la rotación EXIF que el navegador ya
 * conoce. El servidor igual la vuelve a normalizar: esto es comodidad, no validación.
 */
function achicarImagen(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);
            const mayor = Math.max(img.width, img.height);
            const escala = mayor > LADO_MAXIMO ? LADO_MAXIMO / mayor : 1;

            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(img.width * escala);
            canvas.height = Math.round(img.height * escala);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(
                blob => blob ? resolve(blob) : reject(new Error('No se pudo procesar la foto.')),
                'image/jpeg',
                CALIDAD
            );
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('El archivo no es una imagen válida.')); };
        img.src = url;
    });
}

async function alElegirFoto(ev) {
    const file = ev.target.files?.[0];
    ev.target.value = ''; // permite volver a elegir la misma foto
    if (!file) return;

    try {
        _archivoListo = await achicarImagen(file);
        const url = URL.createObjectURL(_archivoListo);
        document.getElementById('previoTarjeta').src = url;
        document.getElementById('previoDetalle').src = url;
        document.getElementById('previoSubida').classList.remove('d-none');
        document.getElementById('previoSubida').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) {
        aviso(e.message || 'No se pudo leer la foto.');
    }
}

document.getElementById('inpCamara').addEventListener('change', alElegirFoto);
document.getElementById('inpArchivo').addEventListener('change', alElegirFoto);

function cancelarPrevio() {
    _archivoListo = null;
    document.getElementById('previoSubida').classList.add('d-none');
}

async function postFotos(ruta, datos) {
    const fd = datos instanceof FormData ? datos : new FormData();
    if (!(datos instanceof FormData)) {
        for (const [k, v] of Object.entries(datos || {})) fd.append(k, v);
    }
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('producto_id', PRODUCTO_ID);

    const r = await fetch(`${BASE_URL}/galeria/fotos-menu/${ruta}`, { method: 'POST', body: fd });
    return r.json();
}

async function subirCandidata() {
    if (!_archivoListo) return;
    const btn = document.getElementById('btnConfirmarSubida');
    btn.disabled = true; btn.textContent = 'Subiendo...';

    const fd = new FormData();
    fd.append('archivo', _archivoListo, 'foto.jpg');

    try {
        const d = await postFotos('subir', fd);
        if (d.ok) location.reload();
        else { aviso(d.error || 'No se pudo subir la foto.'); btn.disabled = false; btn.textContent = 'Subir'; }
    } catch {
        aviso('Error de conexión. Probá de nuevo.');
        btn.disabled = false; btn.textContent = 'Subir';
    }
}

async function mejorar(fotoId, opcion) {
    aviso('Mejorando la foto con IA, puede tardar hasta un minuto...', 'info');
    const d = await postFotos('mejorar', { foto_id: fotoId, opcion });
    if (d.ok) location.reload();
    else aviso(d.error || 'No se pudo mejorar la foto.');
}

async function elegirOficial(fotoId) {
    if (!confirm('¿Publicar esta foto en el menú online? La van a ver tus clientes.')) return;
    const d = await postFotos('oficial', { foto_id: fotoId });
    if (d.ok) location.reload();
    else aviso(d.error || 'No se pudo publicar la foto.');
}

async function descartar(fotoId) {
    if (!confirm('¿Descartar esta candidata?')) return;
    const d = await postFotos('descartar', { foto_id: fotoId });
    if (d.ok) location.reload();
    else aviso(d.error || 'No se pudo descartar la foto.');
}

async function volverAnterior() {
    if (!confirm('¿Volver a la foto anterior del menú?')) return;
    const d = await postFotos('volver-anterior', {});
    if (d.ok) location.reload();
    else aviso(d.error || 'No se pudo volver a la foto anterior.');
}
</script>
