<?php $esEdicion = !empty($producto); ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h1>
    <a href="<?= url('productos') ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formProducto" method="POST" enctype="multipart/form-data" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?>
    <input type="hidden" name="id" value="<?= $producto['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">

        <!-- Columna izquierda -->
        <div class="col-lg-8">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Información general</h6>
                </div>
                <div class="card-body">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required maxlength="150"
                                value="<?= e($producto['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Código</label>
                            <input type="text" name="codigo" class="form-control" maxlength="30"
                                value="<?= e($producto['codigo'] ?? '') ?>" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" maxlength="500"><?= e($producto['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Precios e impuestos</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Precio de venta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_venta" class="form-control" min="0" step="0.01" required
                                    value="<?= $producto['precio_venta'] ?? '0' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Precio de costo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio_costo" class="form-control" min="0" step="0.01"
                                    value="<?= $producto['precio_costo'] ?? '0' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">IVA <span class="text-danger">*</span></label>
                            <select name="iva" class="form-select" required>
                                <?php foreach ([0 => 'Exento (0%)', 10.5 => '10,5%', 21 => '21%'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= (float)($producto['iva'] ?? 21) === (float)$val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha -->
        <div class="col-lg-4">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Clasificación</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <option value="reventa"  <?= ($producto['tipo'] ?? '') === 'reventa'  ? 'selected' : '' ?>>Reventa</option>
                            <option value="elaborado" <?= ($producto['tipo'] ?? '') === 'elaborado' ? 'selected' : '' ?>>Elaborado (tiene receta)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoría</label>
                        <select name="categoria_id" class="form-select">
                            <option value="">— Sin categoría —</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($producto['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unidad de medida</label>
                        <select name="unidad_medida_id" class="form-select">
                            <option value="">— —</option>
                            <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($producto['unidad_medida_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['nombre']) ?> (<?= e($u['abreviatura']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Stock mínimo</label>
                        <input type="number" name="stock_minimo" class="form-control" min="0" step="0.001"
                            value="<?= $producto['stock_minimo'] ?? '0' ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-header border-0 bg-transparent">
                    <h6 class="fw-semibold mb-0">Imagen</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($producto['imagen'])): ?>
                        <img src="<?= url('uploads/productos/' . e($producto['imagen'])) ?>"
                            class="img-thumbnail mb-2" style="max-width:120px; max-height:120px; object-fit:cover">
                    <?php endif; ?>
                    <input type="file" name="imagen" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted d-block mt-1">JPG, PNG o WebP · Máx. 5MB</small>
                </div>
            </div>

            <div class="card border-0 mt-3">
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="ecommerce" value="1" id="chkEcommerce"
                            <?= ($producto['ecommerce'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkEcommerce">Publicar en Ecommerce</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="chkActivo"
                            <?= ($producto['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkActivo">Producto activo</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4">
            <?= $esEdicion ? 'Actualizar producto' : 'Crear producto' ?>
        </button>
        <a href="<?= url('productos') ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<script>
const BASE_URL = '<?= BASE_URL ?>';

document.getElementById('formProducto').addEventListener('submit', async function(e) {
    e.preventDefault();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');

    const isEdit = !!document.querySelector('input[name="id"]')?.value;
    const url    = isEdit ? `${BASE_URL}/productos/actualizar` : `${BASE_URL}/productos/crear`;
    const fd     = new FormData(this);

    // Convertir checkboxes no marcados a 0
    ['ecommerce', 'activo'].forEach(name => {
        if (!fd.has(name)) fd.set(name, '0');
    });

    try {
        const resp = await fetch(url, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            showToast(data.mensaje, 'success');
            setTimeout(() => window.location.href = `${BASE_URL}/productos`, 900);
        } else {
            errEl.textContent = data.error;
            errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.';
        errEl.classList.remove('d-none');
    }
});
</script>
