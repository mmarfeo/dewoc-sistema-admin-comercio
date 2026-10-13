<?php
$esEdicion  = !empty($factura);
$volverUrl ??= url('compras');
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold"><?= $esEdicion ? 'Editar factura #' . $factura['id'] : 'Nueva factura de compra' ?></h1>
    <a href="<?= e($volverUrl) ?>" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form id="formCompra" novalidate>
    <?= Csrf::field() ?>
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $factura['id'] ?>"><?php endif; ?>

    <div class="row g-3">

        <!-- Fila 1: Datos del comprobante (horizontal) -->
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Datos del comprobante</h6></div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">Proveedor <span class="text-danger">*</span></label>
                            <select name="proveedor_id" id="fProveedor" class="form-select" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($esEdicion && $factura['proveedor_id'] == $p['id']) ? 'selected' : '' ?>><?= e($p['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-3">
                            <label class="form-label fw-semibold">Tipo comprobante</label>
                            <select name="tipo_comprobante" id="fTipo" class="form-select">
                                <?php foreach (['factura_a' => 'Factura A', 'factura_b' => 'Factura B', 'factura_c' => 'Factura C', 'ticket' => 'Ticket', 'remito' => 'Remito', 'otro' => 'Otro'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($esEdicion ? $factura['tipo_comprobante'] : 'factura_b') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-3">
                            <label class="form-label fw-semibold">Nro. comprobante</label>
                            <input type="text" name="nro_comprobante" class="form-control" placeholder="0001-00012345" value="<?= e($factura['nro_comprobante'] ?? '') ?>">
                        </div>
                        <div class="col-xl-2 col-md-3">
                            <label class="form-label fw-semibold">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="<?= e($esEdicion ? $factura['fecha'] : date('Y-m-d')) ?>">
                        </div>
                        <div class="col-xl-2 col-md-3">
                            <label class="form-label fw-semibold">Condición de pago</label>
                            <select name="condicion_pago" class="form-select">
                                <?php foreach (['contado' => 'Contado', 'cuenta_corriente' => 'Cuenta corriente', 'cheque' => 'Cheque', 'transferencia' => 'Transferencia'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($factura['condicion_pago'] ?? 'contado') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($esEdicion): ?>
                        <div class="col-xl-2 col-md-3">
                            <label class="form-label fw-semibold">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="pendiente" <?= $factura['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="pagada"    <?= $factura['estado'] === 'pagada'    ? 'selected' : '' ?>>Pagada</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label fw-semibold">Notas</label>
                            <input type="text" name="notas" class="form-control" placeholder="Observaciones..." value="<?= e($factura['notas'] ?? '') ?>">
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label fw-semibold">
                                Comprobante <span class="text-muted fw-normal small">(PDF/imagen<?= $esEdicion ? ' — hoja 1' : '' ?>)</span>
                            </label>
                            <?php if ($esEdicion && !empty($factura['comprobante_archivo'])): ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <a href="<?= url('compras/comprobante?id='.$factura['id']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline-secondary">Ver actual</a>
                                <span class="text-muted small">Seleccioná otro para reemplazar</span>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="comprobante_archivo" id="inputComprobanteForm"
                                   class="form-control form-control-sm"
                                   accept="image/jpeg,image/png,image/webp,application/pdf">
                            <div id="prevComp" class="d-none mt-1">
                                <img id="prevCompImg" src="" alt="" class="rounded border" style="max-height:80px;object-fit:contain">
                                <span id="prevCompPdf" class="small text-danger d-none">PDF listo para adjuntar</span>
                            </div>
                            <?php if ($esEdicion): ?>
                            <div class="form-text">¿Factura de varias hojas? Agregá el resto más abajo, en "Comprobantes adicionales".</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($esEdicion): ?>
        <!-- Comprobantes adicionales (factura de varias hojas) -->
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <h6 class="fw-semibold mb-0">Comprobantes adicionales <span class="text-muted fw-normal small">(otras hojas de esta factura)</span></h6>
                    <label class="btn btn-outline-secondary btn-sm mb-0" for="inputAdjuntosFactura">+ Agregar</label>
                    <input type="file" id="inputAdjuntosFactura" class="d-none" multiple
                           accept="image/jpeg,image/png,image/webp,application/pdf">
                </div>
                <div class="card-body">
                    <div id="listaAdjuntosFactura" class="d-flex flex-wrap gap-2">
                        <span class="text-muted small">Sin comprobantes adicionales.</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Fila 2: Ítems de la factura (ancho completo) -->
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-semibold mb-0">Ítems de la factura</h6>
                        <p class="text-muted small mb-0 mt-1">
                            Solo mercadería con stock (materias primas, insumos, productos). Para servicios como
                            Internet, alquiler, honorarios o sistemas, cargalos en <a href="<?= url('gastos') ?>" target="_blank">Gastos</a>.
                        </p>
                    </div>
                    <div class="d-flex gap-2 align-items-start position-relative">
                        <div class="position-relative">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnColumnas" onclick="_toggleMenuColumnas()">Columnas</button>
                            <div id="menuColumnas" class="shadow-sm rounded-3 p-2 d-none"
                                 style="position:absolute; top:100%; right:0; z-index:1050; background:#fff; border:1px solid #dee2e6; min-width:220px"></div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="_addRow()">+ Fila</button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <style>
                        #tblItems { border-collapse: collapse; }
                        #tblItems thead th {
                            padding: 6px 6px;
                            font-size: .75rem;
                            background: #f8f9fa;
                            border: 1px solid #dee2e6;
                            white-space: nowrap;
                        }
                        #tblItems tbody td {
                            padding: 0 !important;
                            border: 1px solid #dee2e6;
                            vertical-align: middle;
                        }
                        #tblItems tbody td input.form-control,
                        #tblItems tbody td select.form-select {
                            border: none !important;
                            border-radius: 0 !important;
                            box-shadow: none !important;
                            height: 30px;
                            padding: 2px 5px;
                            font-size: .82rem;
                            background: transparent;
                        }
                        #tblItems tbody td input.form-control:focus,
                        #tblItems tbody td select.form-select:focus {
                            outline: none;
                            box-shadow: inset 0 0 0 2px #0d6efd !important;
                            background: #fff;
                            position: relative; z-index: 1;
                        }
                        #tblItems tbody td._imp-dto,
                        #tblItems tbody td._subtotal { padding: 0 6px !important; font-size: .82rem; }
                        #tblItems tbody td.td-del { padding: 0 !important; text-align: center; }
                        /* combobox ítem */
                        .item-combo { position: relative; display: flex; align-items: center; }
                        .item-combo ._item-input { flex: 1; min-width: 0; cursor: text; padding-right: 22px !important; }
                        .item-combo ._combo-arrow {
                            position: absolute; right: 3px; top: 50%; transform: translateY(-50%);
                            color: #6c757d; font-size: 11px; cursor: pointer; user-select: none; line-height:1;
                        }
                    </style>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="tblItems">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:210px" data-col="item">Ítem</th>
                                    <th style="width:80px" data-col="cantcont">Cant. Cont.</th>
                                    <th style="width:75px" data-col="cant">Cant.</th>
                                    <th style="width:100px" data-col="precio">P. Unit. ($)</th>
                                    <th style="width:60px" data-col="dto">% Dto</th>
                                    <th style="width:90px" data-col="impdto">Imp. Dto</th>
                                    <th style="width:75px" data-col="iva">IVA %</th>
                                    <th style="width:90px" data-col="nograv">No Gravado</th>
                                    <th style="width:90px" data-col="subtotal">Subtotal</th>
                                    <th style="min-width:110px" data-col="obs">Obs.</th>
                                    <th style="width:60px" data-col="activo" title="Es un bien de uso (horno, mueble, herramienta) — se carga en Activos Fijos, además de quedar en esta factura para no perder la deuda con el proveedor">Activo fijo</th>
                                    <th style="width:28px"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoItems"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 3: Resumen horizontal -->
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap gap-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">Subtotal sin IVA</span>
                            <span id="resSubtotal" class="fw-semibold">$ 0,00</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">IVA</span>
                            <span id="resIva" class="fw-semibold">$ 0,00</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <span class="fw-bold">TOTAL</span>
                            <span id="resTotal" class="fw-bold text-primary fs-5">$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $pagos ??= [];
        if ($esEdicion):
            $totalConIva = (float)($factura['total_con_iva'] ?? 0);
            $totalPagado = 0.0;
            $porMetodo   = [];
            $metodosCols = [
                'monto_efectivo'      => 'Efectivo',
                'monto_transferencia' => 'Transferencia',
                'monto_mercado_pago'  => 'Mercado Pago',
                'monto_debito'        => 'Débito',
                'monto_cheque'        => 'Cheque',
                'monto_otro'          => 'Otro',
            ];
            foreach ($pagos as $pg) {
                if (($pg['estado'] ?? '') === 'anulado') continue;
                $totalPagado += (float)$pg['monto_aplicado'];
                foreach ($metodosCols as $col => $lbl) {
                    $m = (float)($pg[$col] ?? 0);
                    if ($m > 0) $porMetodo[$lbl] = ($porMetodo[$lbl] ?? 0) + $m;
                }
            }
            $saldo = $totalConIva - $totalPagado;
        ?>
        <!-- Fila 4: Pagos de la factura -->
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between py-2">
                    <h6 class="fw-semibold mb-0">Pagos</h6>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnTogglePagoRapido">
                        + Registrar pago
                    </button>
                </div>

                <!-- ── Formulario de pago rápido (oculto por defecto) ──────── -->
                <div id="wrapPagoRapido" class="d-none border-top border-bottom" style="background:#f8fffe">
                    <div class="p-3">

                        <!-- Filas de método de pago -->
                        <div class="mb-2">
                            <div class="d-flex gap-2 mb-1" style="padding-left:2px">
                                <span class="small fw-semibold text-muted" style="width:160px">Método</span>
                                <span class="small fw-semibold text-muted" style="width:145px">Monto</span>
                            </div>
                            <div id="prFilasPago"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnAgregarMetodoPago">
                                + Agregar método
                            </button>
                        </div>

                        <!-- Fecha · Concepto · Comprobante · Ref. MP -->
                        <div class="row g-2 mt-1 align-items-end">
                            <div class="col-auto">
                                <label class="form-label small fw-semibold mb-1">Fecha</label>
                                <input type="date" id="pr_fecha" class="form-control form-control-sm"
                                       value="<?= date('Y-m-d') ?>" style="width:145px">
                            </div>
                            <div class="col-xl-2 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Concepto</label>
                                <input type="text" id="pr_concepto" class="form-control form-control-sm"
                                       placeholder="Pago parcial, saldo…">
                            </div>
                            <div class="col-xl-2 col-md-3" id="wrapPrRefMp" style="display:none">
                                <label class="form-label small fw-semibold mb-1">Nro. operación MP</label>
                                <input type="text" id="pr_ref_mp" class="form-control form-control-sm"
                                       placeholder="Ej: 123456789">
                            </div>
                            <div class="col-xl-2 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Comprobante(s) de pago</label>
                                <input type="file" id="pr_comprobante" class="form-control form-control-sm" multiple
                                       accept="image/jpeg,image/png,image/webp,application/pdf">
                            </div>
                        </div>

                        <!-- Origen del pago -->
                        <div class="row g-2 mt-2 align-items-center">
                            <div class="col-auto">
                                <span class="small fw-semibold">Origen:</span>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="pr_origen"
                                           id="pr_origen_empresa" value="empresa" checked>
                                    <label class="form-check-label small" for="pr_origen_empresa">Empresa</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="pr_origen"
                                           id="pr_origen_socio" value="socio">
                                    <label class="form-check-label small" for="pr_origen_socio">Aporte de socio</label>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-3 d-none" id="wrapPrSocio">
                                <select id="pr_socio_select" class="form-select form-select-sm">
                                    <option value="">— Seleccionar socio —</option>
                                    <?php foreach ($socios ?? [] as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto ms-auto d-flex align-items-center gap-3">
                                <span class="small text-muted">Total: <strong id="pr_total_label">$ 0,00</strong></span>
                                <button type="button" class="btn btn-success btn-sm px-3" id="btnGuardarPago">
                                    Guardar pago
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelarPago">
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <div id="prError" class="alert alert-danger mt-2 py-2 d-none small"></div>
                    </div>
                </div>

                <!-- ── Tabla de pagos existentes ──────────────────────────── -->
                <div class="card-body p-0">
                    <?php if (empty($pagos)): ?>
                    <p class="text-muted small p-3 mb-0">Sin pagos registrados para esta factura.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="small">Fecha</th>
                                    <th class="small">Medios</th>
                                    <th class="small">Origen</th>
                                    <th class="small">Registrado por</th>
                                    <th class="text-end small">Aplicado</th>
                                    <th class="text-center small" style="width:60px">Comp.</th>
                                    <th class="text-center small" style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pagos as $pg):
                                $esAnulPg = ($pg['estado'] ?? '') === 'anulado';
                                $mediosTxt = [];
                                foreach ($metodosCols as $col => $lbl) {
                                    $m = (float)($pg[$col] ?? 0);
                                    if ($m > 0) $mediosTxt[] = "$lbl " . moneda($m);
                                }
                            ?>
                            <?php
                            $pgJson = htmlspecialchars(json_encode([
                                'id'                  => $pg['id'],
                                'fecha'               => $pg['fecha'],
                                'concepto'            => $pg['concepto'] ?? '',
                                'monto_efectivo'      => (float)($pg['monto_efectivo']      ?? 0),
                                'monto_transferencia' => (float)($pg['monto_transferencia'] ?? 0),
                                'monto_mercado_pago'  => (float)($pg['monto_mercado_pago']  ?? 0),
                                'monto_debito'        => (float)($pg['monto_debito']        ?? 0),
                                'monto_cheque'        => (float)($pg['monto_cheque']        ?? 0),
                                'monto_otro'          => (float)($pg['monto_otro']          ?? 0),
                                'origen_pago'         => $pg['origen_pago']  ?? 'empresa',
                                'nombre_socio'        => $pg['nombre_socio'] ?? '',
                                'socio_id'            => $pg['socio_id']     ?? null,
                                'comprobante_path'    => $pg['comprobante_path'] ?? null,
                                'adjuntos'            => $pg['adjuntos'] ?? [],
                            ]), ENT_QUOTES);
                            ?>
                            <tr class="<?= $esAnulPg ? 'text-muted text-decoration-line-through' : '' ?>">
                                <td class="small"><?= fecha($pg['fecha']) ?></td>
                                <td class="small"><?= e(implode(' · ', $mediosTxt) ?: '—') ?></td>
                                <td class="small">
                                    <?php if (($pg['origen_pago'] ?? 'empresa') === 'socio'): ?>
                                    <span class="badge bg-info-soft text-info">Socio<?= $pg['nombre_socio'] ? ': '.e($pg['nombre_socio']) : '' ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary-soft text-secondary">Empresa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= e($pg['usuario_nombre'] ?? '—') ?></td>
                                <td class="text-end fw-semibold small"><?= moneda($pg['monto_aplicado']) ?></td>
                                <td class="text-center">
                                    <?php $totalComprobantesPago = ($pg['comprobante_path'] ? 1 : 0) + count($pg['adjuntos'] ?? []); ?>
                                    <?php if ($totalComprobantesPago === 1 && $pg['comprobante_path']): ?>
                                    <a href="<?= url('pagos-proveedores/comprobante?id='.$pg['id']) ?>"
                                       target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Ver comprobante">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </a>
                                    <?php elseif ($totalComprobantesPago > 1): ?>
                                    <span class="badge bg-secondary-soft text-secondary" title="Abrí 'Editar pago' para verlos todos">📎 <?= $totalComprobantesPago ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!$esAnulPg): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 btn-editar-pago"
                                            data-pago="<?= $pgJson ?>"
                                            data-factura-id="<?= (int)$factura['id'] ?>"
                                            title="Editar pago">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Modal: editar pago existente -->
                    <!-- Trigger oculto para abrir el modal via data-bs-toggle (sin necesitar bootstrap global) -->
                    <button type="button" id="epModalTrigger" data-bs-toggle="modal" data-bs-target="#modalEditarPago" style="display:none" tabindex="-1" aria-hidden="true"></button>

                    <div class="modal fade" id="modalEditarPago" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header py-2">
                                    <h6 class="modal-title fw-semibold">Editar pago</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" id="ep_pago_id">
                                    <input type="hidden" id="ep_factura_id">

                                    <!-- Filas de método -->
                                    <div class="mb-3">
                                        <div class="d-flex gap-2 mb-1">
                                            <span class="small fw-semibold text-muted" style="width:160px">Método</span>
                                            <span class="small fw-semibold text-muted" style="width:145px">Monto</span>
                                        </div>
                                        <div id="epFilasPago"></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnEpAgregarMetodo">
                                            + Agregar método
                                        </button>
                                    </div>

                                    <div class="row g-2 align-items-end">
                                        <div class="col-auto">
                                            <label class="form-label small fw-semibold mb-1">Fecha</label>
                                            <input type="date" id="ep_fecha" class="form-control form-control-sm" style="width:145px">
                                        </div>
                                        <div class="col-xl-3 col-md-4">
                                            <label class="form-label small fw-semibold mb-1">Concepto</label>
                                            <input type="text" id="ep_concepto" class="form-control form-control-sm" placeholder="Pago parcial, saldo…">
                                        </div>
                                        <div class="col-xl-3 col-md-4" id="wrapEpRefMp" style="display:none">
                                            <label class="form-label small fw-semibold mb-1">Nro. operación MP</label>
                                            <input type="text" id="ep_ref_mp" class="form-control form-control-sm" placeholder="Ej: 123456789">
                                        </div>
                                    </div>

                                    <div class="row g-2 mt-2 align-items-center">
                                        <div class="col-auto">
                                            <span class="small fw-semibold">Origen:</span>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="radio" name="ep_origen" id="ep_origen_empresa" value="empresa" checked>
                                                <label class="form-check-label small" for="ep_origen_empresa">Empresa</label>
                                            </div>
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="radio" name="ep_origen" id="ep_origen_socio" value="socio">
                                                <label class="form-check-label small" for="ep_origen_socio">Aporte de socio</label>
                                            </div>
                                        </div>
                                        <div class="col-xl-2 col-md-3 d-none" id="wrapEpSocio">
                                            <select id="ep_socio_select" class="form-select form-select-sm">
                                                <option value="">— Seleccionar socio —</option>
                                                <?php foreach ($socios ?? [] as $s): ?>
                                                <option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-auto ms-auto">
                                            <span class="small text-muted">Total: <strong id="ep_total_label">$ 0,00</strong></span>
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="mb-1 d-flex align-items-center justify-content-between">
                                        <label class="form-label small fw-semibold mb-0">Comprobantes de pago</label>
                                        <label class="btn btn-outline-secondary btn-sm mb-0" for="epComprobantesInput">+ Agregar</label>
                                        <input type="file" id="epComprobantesInput" class="d-none" multiple
                                               accept="image/jpeg,image/png,image/webp,application/pdf">
                                    </div>
                                    <div id="epComprobantesLista" class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="text-muted small">Sin comprobantes.</span>
                                    </div>

                                    <div id="epError" class="alert alert-danger mt-3 py-2 d-none small"></div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary btn-sm px-4" id="btnEpGuardar">Guardar cambios</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de saldo -->
                    <div class="d-flex align-items-center flex-wrap gap-4 p-3 border-top"
                         style="background:<?= $saldo <= 0.01 ? '#f0fff4' : '#fff8f0' ?>">
                        <?php if (!empty($porMetodo)): ?>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="text-muted small">Pagado con:</span>
                            <?php foreach ($porMetodo as $lbl => $monto): ?>
                            <span class="badge bg-secondary-soft text-secondary"><?= e($lbl) ?> <?= moneda($monto) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-4 ms-auto">
                            <div>
                                <span class="text-muted small">Total factura: </span>
                                <span class="fw-semibold" id="resumenBarra_total"><?= moneda($totalConIva) ?></span>
                            </div>
                            <div>
                                <span class="text-muted small">Pagado: </span>
                                <span class="fw-semibold text-success"><?= moneda($totalPagado) ?></span>
                            </div>
                            <?php if ($saldo > 0.01): ?>
                            <div>
                                <span class="text-muted small">Saldo: </span>
                                <span class="fw-bold text-danger" id="resumenBarra_saldo"><?= moneda($saldo) ?></span>
                            </div>
                            <?php else: ?>
                            <div class="d-flex align-items-center gap-1 text-success fw-semibold small">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Pagado en su totalidad
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Input oculto con JSON de items -->
    <input type="hidden" name="items_json" id="itemsJson" value="[]">

    <div id="formError" class="alert alert-danger mt-3 d-none"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4" id="btnGuardar">
            <span class="spinner-border spinner-border-sm me-1 d-none" id="spinnerGuardar" role="status" aria-hidden="true"></span>
            <span id="btnGuardarTexto"><?= $esEdicion ? 'Actualizar factura' : 'Guardar factura' ?></span>
        </button>
        <a href="<?= e($volverUrl) ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<!-- ===== MODAL ALTA RÁPIDA DE ÍTEM ===== -->
<div class="modal fade" id="modalCrearItemRapido" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Crear ítem nuevo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearItemRapido">
                <?= Csrf::field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="ciNombre" name="nombre" class="form-control" required maxlength="150">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                            <select id="ciTipo" name="tipo" class="form-select" required>
                                <option value="materia_prima">Materia prima</option>
                                <option value="insumo">Insumo</option>
                                <option value="producto">Producto</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unidad de medida</label>
                            <select id="ciUnidad" name="unidad_medida_id" class="form-select">
                                <option value="">— —</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Categoría</label>
                            <select id="ciCategoria" name="categoria_id" class="form-select">
                                <option value="">— Sin categoría —</option>
                            </select>
                        </div>
                        <div class="col-12" id="ciGenericoWrap">
                            <label class="form-label fw-semibold">
                                Ingrediente genérico
                                <span class="text-muted fw-normal">(opcional, solo materia prima)</span>
                            </label>
                            <select id="ciGenerico" name="ingrediente_generico_id" class="form-select">
                                <option value="">— Ninguno —</option>
                            </select>
                            <div class="form-text">
                                Si es una variante de marca/paquete de un ingrediente que ya comprás con otro
                                nombre (ej. "Jamón Delicato" y "Jamón Estancia" son ambos "Jamón cocido"),
                                asignala acá para que las recetas reflejen el costo actualizado compre la marca
                                que compre.
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Se crea con costo y stock en 0 — el resto de los datos (proveedor habitual, cantidad
                        contenida, etc.) se puede completar después desde su ficha.
                    </div>
                    <div class="alert alert-info-soft border-0 py-2 small mt-2 mb-0">
                        ¿Es un servicio (Internet, alquiler, sistema, honorarios, etc.) y no mercadería con stock?
                        No se carga acá — registralo directo en <a href="<?= url('gastos') ?>" target="_blank">Gastos</a>.
                    </div>
                    <div id="ciError" class="alert alert-danger mt-3 d-none py-2 small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnCrearItemRapido">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Preview comprobante en el form ────────────────────────────────────────────
(function() {
    const inp = document.getElementById('inputComprobanteForm');
    if (!inp) return;
    inp.addEventListener('change', function() {
        const file = this.files[0];
        const wrap = document.getElementById('prevComp');
        const img  = document.getElementById('prevCompImg');
        const pdf  = document.getElementById('prevCompPdf');
        if (!file) { wrap.classList.add('d-none'); return; }
        wrap.classList.remove('d-none');
        if (file.type === 'application/pdf') {
            img.classList.add('d-none'); pdf.classList.remove('d-none');
        } else {
            pdf.classList.add('d-none');
            const reader = new FileReader();
            reader.onload = ev => { img.src = ev.target.result; img.classList.remove('d-none'); };
            reader.readAsDataURL(file);
        }
    });
})();

// ── Pago rápido ───────────────────────────────────────────────────────────────
<?php if ($esEdicion): ?>
(function() {
    const PROVEEDOR_ID  = <?= (int)$factura['proveedor_id'] ?>;
    const FACTURA_ID    = <?= (int)$factura['id'] ?>;
    const TOTAL_FACTURA = <?= (float)($factura['total_con_iva'] ?? 0) ?>;

    const METODOS = [
        { value: 'efectivo',      label: 'Efectivo' },
        { value: 'transferencia', label: 'Transferencia' },
        { value: 'mercado_pago',  label: 'Mercado Pago' },
        { value: 'debito',        label: 'Débito' },
        { value: 'cheque',        label: 'Cheque' },
        { value: 'otro',          label: 'Otro' },
    ];

    const btnToggle  = document.getElementById('btnTogglePagoRapido');
    const wrap       = document.getElementById('wrapPagoRapido');
    const btnSave    = document.getElementById('btnGuardarPago');
    const btnCancel  = document.getElementById('btnCancelarPago');
    const errEl      = document.getElementById('prError');
    const totalLabel = document.getElementById('pr_total_label');
    const filasWrap  = document.getElementById('prFilasPago');

    if (!btnToggle) return;

    // ── Toggle panel ───────────────────────────────────────────────────────────
    btnToggle.addEventListener('click', () => {
        const isOpening = wrap.classList.contains('d-none');
        wrap.classList.toggle('d-none');
        btnToggle.textContent = wrap.classList.contains('d-none') ? '+ Registrar pago' : '✕ Cerrar';
        if (isOpening) {
            const saldo = Math.max(0, (window._currentTotal ?? TOTAL_FACTURA) - PAGADO_TOTAL);
            const firstMonto = filasWrap.querySelector('.pr-monto-inp');
            if (firstMonto && !parseFloat(firstMonto.value)) {
                firstMonto.value = saldo.toFixed(2);
                _recalc();
            }
        }
    });
    btnCancel.addEventListener('click', () => {
        wrap.classList.add('d-none');
        btnToggle.textContent = '+ Registrar pago';
        errEl.classList.add('d-none');
    });

    // ── Filas dinámicas de método ──────────────────────────────────────────────
    let filaCtr = 0;

    function _optionsHtml(selected = 'efectivo') {
        return METODOS.map(m =>
            `<option value="${m.value}" ${m.value === selected ? 'selected' : ''}>${m.label}</option>`
        ).join('');
    }

    function _addFila(metodoDefault = 'efectivo', monto = '') {
        const fid = ++filaCtr;
        const div = document.createElement('div');
        div.className = 'd-flex align-items-center gap-2 mb-1 pr-fila';
        div.dataset.fid = fid;
        div.innerHTML = `
            <select class="form-select form-select-sm pr-sel" style="width:160px">
                ${_optionsHtml(metodoDefault)}
            </select>
            <div class="input-group input-group-sm" style="width:145px">
                <span class="input-group-text px-2">$</span>
                <input type="number" class="form-control pr-monto-inp"
                       min="0" step="0.01" value="${monto}" placeholder="0,00">
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 lh-1 pr-del"
                    style="font-size:1rem;width:20px" title="Quitar">✕</button>`;

        div.querySelector('.pr-monto-inp').addEventListener('input', _recalc);
        div.querySelector('.pr-sel').addEventListener('change', _checkMp);
        div.querySelector('.pr-del').addEventListener('click', () => {
            if (filasWrap.querySelectorAll('.pr-fila').length <= 1) return;
            div.remove();
            _recalc();
            _checkMp();
        });

        filasWrap.appendChild(div);
        _recalc();
    }

    document.getElementById('btnAgregarMetodoPago').addEventListener('click', () => _addFila());

    // ── Recalcular total ───────────────────────────────────────────────────────
    function _recalc() {
        let t = 0;
        filasWrap.querySelectorAll('.pr-monto-inp').forEach(i => { t += parseFloat(i.value || 0); });
        totalLabel.textContent = '$ ' + t.toLocaleString('es-AR', { minimumFractionDigits: 2 });
        return t;
    }

    // ── Mostrar campo Ref. MP cuando hay Mercado Pago ─────────────────────────
    function _checkMp() {
        const hasMp = [...filasWrap.querySelectorAll('.pr-sel')].some(s => s.value === 'mercado_pago');
        document.getElementById('wrapPrRefMp').style.display = hasMp ? '' : 'none';
    }

    // ── Origen del pago — mostrar selector de socio (usuarios) ───────────────
    document.querySelectorAll('input[name="pr_origen"]').forEach(r => {
        r.addEventListener('change', () => {
            document.getElementById('wrapPrSocio').classList.toggle('d-none', r.value !== 'socio');
        });
    });

    // ── Armar montos por método (suma si hay 2 filas con el mismo) ────────────
    function _getMontos() {
        const m = { efectivo:0, transferencia:0, mercado_pago:0, debito:0, cheque:0, otro:0 };
        filasWrap.querySelectorAll('.pr-fila').forEach(div => {
            const key  = div.querySelector('.pr-sel').value;
            const val  = parseFloat(div.querySelector('.pr-monto-inp').value || 0);
            if (key in m) m[key] += val;
        });
        return m;
    }

    // ── Guardar pago ───────────────────────────────────────────────────────────
    btnSave.addEventListener('click', async () => {
        errEl.classList.add('d-none');

        const total = _recalc();
        if (total <= 0) {
            errEl.textContent = 'Ingresá al menos un monto mayor a cero.';
            errEl.classList.remove('d-none'); return;
        }

        const origen = document.querySelector('input[name="pr_origen"]:checked')?.value ?? 'empresa';
        const socioEl = document.getElementById('pr_socio_select');
        const socio   = socioEl?.value?.trim() ?? '';
        if (origen === 'socio' && !socio) {
            errEl.textContent = 'Seleccioná el socio que realiza el aporte.';
            errEl.classList.remove('d-none'); return;
        }

        btnSave.disabled = true;
        btnSave.textContent = 'Guardando…';

        const montos = _getMontos();
        const fd = new FormData();
        fd.append('_csrf_token',          CSRF_TOKEN);
        fd.append('tipo',                 'proveedor');
        fd.append('proveedor_id',         PROVEEDOR_ID);
        fd.append('fecha',                document.getElementById('pr_fecha').value);
        fd.append('concepto',             document.getElementById('pr_concepto').value);
        fd.append('monto_efectivo',       montos.efectivo);
        fd.append('monto_transferencia',  montos.transferencia);
        fd.append('monto_mercado_pago',   montos.mercado_pago);
        fd.append('monto_debito',         montos.debito);
        fd.append('monto_cheque',         montos.cheque);
        fd.append('monto_otro',           montos.otro);
        fd.append('monto_total',          total);
        fd.append('referencia_mp',        document.getElementById('pr_ref_mp').value || '');
        fd.append('origen_pago',          origen);
        fd.append('socio_id',             socio);
        fd.append('factura_ids[]',        FACTURA_ID);
        fd.append('factura_montos[]',     Math.min(total, TOTAL_FACTURA));

        [...document.getElementById('pr_comprobante').files].forEach(f => fd.append('comprobantes[]', f));

        try {
            const r = await fetch(`${BASE_URL}/pagos-proveedores/guardar`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                showToast('Pago registrado correctamente.');
                setTimeout(() => location.reload(), 700);
            } else {
                errEl.textContent = d.error || 'Error al guardar.';
                errEl.classList.remove('d-none');
                btnSave.disabled = false;
                btnSave.textContent = 'Guardar pago';
            }
        } catch {
            errEl.textContent = 'Error de conexión.';
            errEl.classList.remove('d-none');
            btnSave.disabled = false;
            btnSave.textContent = 'Guardar pago';
        }
    });

    // ── Init: una fila vacía ───────────────────────────────────────────────────
    _addFila('efectivo', '');
})();
<?php endif; ?>

const BASE_URL    = '<?= BASE_URL ?>';
const CSRF_TOKEN  = '<?= Csrf::token() ?>';
const CATEGORIAS_CREAR_ITEM = <?= json_encode($categoriasCrearItem ?? [], JSON_UNESCAPED_UNICODE) ?>;
const UNIDADES_CREAR_ITEM   = <?= json_encode($unidadesCrearItem ?? [], JSON_UNESCAPED_UNICODE) ?>;
const GENERICOS_CREAR_ITEM  = <?= json_encode($genericosCrearItem ?? [], JSON_UNESCAPED_UNICODE) ?>;
const ES_EDICION   = <?= $esEdicion ? 'true' : 'false' ?>;
const SUBMIT_URL   = ES_EDICION ? `${BASE_URL}/compras/actualizar` : `${BASE_URL}/compras/guardar`;
const VOLVER_URL   = '<?= e($volverUrl) ?>';
const PAGADO_TOTAL = <?= $esEdicion ? (float)($totalPagado ?? 0) : 0 ?>;

// ── Comprobantes adicionales de la factura (varias hojas) ──────────────────────
<?php if ($esEdicion): ?>
(function () {
    const facturaId = <?= (int)$factura['id'] ?>;
    let adjuntos = <?= json_encode($adjuntosFactura ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const lista = document.getElementById('listaAdjuntosFactura');

    function render() {
        if (!adjuntos.length) { lista.innerHTML = '<span class="text-muted small">Sin comprobantes adicionales.</span>'; return; }
        lista.innerHTML = adjuntos.map((a, i) => `
            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-2">
                <a href="${BASE_URL}/compras/comprobante-adjunto?id=${a.id}" target="_blank" class="text-decoration-none">📎 Hoja ${i + 2}</a>
                <button type="button" class="btn-close" style="font-size:.55rem" data-id="${a.id}" aria-label="Quitar"></button>
            </span>`).join('');
        lista.querySelectorAll('.btn-close').forEach(b => b.addEventListener('click', async function () {
            const fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN);
            fd.append('id', this.dataset.id);
            try {
                const r = await fetch(`${BASE_URL}/compras/eliminar-comprobante-adjunto`, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) { adjuntos = adjuntos.filter(a => String(a.id) !== this.dataset.id); render(); }
                else if (typeof showToast === 'function') showToast(d.error || 'No se pudo eliminar.', 'danger');
            } catch { if (typeof showToast === 'function') showToast('Error de conexión.', 'danger'); }
        }));
    }

    document.getElementById('inputAdjuntosFactura').addEventListener('change', async function () {
        if (!this.files.length) return;
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_TOKEN);
        fd.append('factura_id', facturaId);
        [...this.files].forEach(f => fd.append('archivos[]', f));
        this.value = '';
        try {
            const r = await fetch(`${BASE_URL}/compras/subir-comprobantes-adjuntos`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                adjuntos = d.adjuntos;
                render();
                if (typeof showToast === 'function') showToast(d.mensaje, 'success');
            } else if (typeof showToast === 'function') showToast(d.error || 'No se pudo subir.', 'danger');
        } catch { if (typeof showToast === 'function') showToast('Error de conexión.', 'danger'); }
    });

    render();
})();
<?php endif; ?>

<?php if ($esEdicion): ?>
const FACTURA_EDIT = <?= json_encode([
    'detalle' => array_map(fn($d) => [
        'tipo_item'          => $d['tipo_item'],
        'materia_prima_id'   => $d['materia_prima_id'],
        'insumo_id'          => $d['insumo_id'],
        'producto_id'        => $d['producto_id'],
        'item_nombre'        => $d['item_nombre'],
        'cantidad'           => $d['cantidad'],
        'cantidad_contenida' => $d['cantidad_contenida'],
        'precio_unitario'    => $d['precio_unitario'],
        'descuento_pct'      => $d['descuento_pct'],
        'iva_porcentaje'     => $d['iva_porcentaje'],
        'monto_no_gravado'   => $d['monto_no_gravado'],
        'observaciones'      => $d['observaciones'],
    ], $factura['detalle']),
], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php else: ?>
const FACTURA_EDIT = null;
<?php endif; ?>

// ── Columnas visibles de la tabla de ítems (persistido, para que cada usuario elija
// las que le sirven — ej. ocultar IVA/No Gravado/Cant.Cont. le deja más ancho a "Ítem"
// para leer el nombre completo, que hoy queda cortado en la columna angosta) ─────────
const COLUMNAS_ITEMS = [
    { key: 'cantcont', label: 'Cant. Cont.' },
    { key: 'dto',      label: '% Dto' },
    { key: 'impdto',   label: 'Imp. Dto' },
    { key: 'iva',      label: 'IVA %' },
    { key: 'nograv',   label: 'No Gravado' },
    { key: 'obs',      label: 'Obs.' },
    { key: 'activo',   label: 'Activo fijo' },
];
const LS_KEY_COLUMNAS = 'compras_columnas_ocultas';

function _columnasOcultas() {
    try { return JSON.parse(localStorage.getItem(LS_KEY_COLUMNAS) || '[]'); } catch { return []; }
}

function _aplicarColumnasVisibles() {
    const ocultas = _columnasOcultas();
    COLUMNAS_ITEMS.forEach(c => {
        const visible = !ocultas.includes(c.key);
        document.querySelectorAll(`#tblItems [data-col="${c.key}"]`).forEach(el => el.classList.toggle('d-none', !visible));
    });
}

function _toggleMenuColumnas() {
    const menu = document.getElementById('menuColumnas');
    if (!menu.classList.contains('d-none')) { menu.classList.add('d-none'); return; }

    const ocultas = _columnasOcultas();
    menu.innerHTML = COLUMNAS_ITEMS.map(c => `
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="colChk_${c.key}" ${ocultas.includes(c.key) ? '' : 'checked'}>
            <label class="form-check-label small" for="colChk_${c.key}">${c.label}</label>
        </div>`).join('');

    COLUMNAS_ITEMS.forEach(c => {
        document.getElementById(`colChk_${c.key}`).addEventListener('change', function () {
            let ocultas = _columnasOcultas();
            ocultas = this.checked ? ocultas.filter(k => k !== c.key) : [...new Set([...ocultas, c.key])];
            localStorage.setItem(LS_KEY_COLUMNAS, JSON.stringify(ocultas));
            _aplicarColumnasVisibles();
        });
    });

    menu.classList.remove('d-none');
}

document.addEventListener('click', function (e) {
    const menu = document.getElementById('menuColumnas');
    if (menu && !menu.classList.contains('d-none') && !menu.contains(e.target) && e.target.id !== 'btnColumnas') {
        menu.classList.add('d-none');
    }
});

// ── Estado de filas ────────────────────────────────────────────────────────────
let _rows  = [];
let _rowCtr = 0;

function _mkRow() {
    return { id: 'r' + (++_rowCtr), tipo_item: '', item_id: null, nombre: '', tipo_label: '' };
}

// ── Crear un solo <tr> de ítem ────────────────────────────────────────────────
function _mkTr(r) {
    const tr = document.createElement('tr');
    tr.dataset.rid = r.id;
    tr.innerHTML = `
        <td data-col="item">
            <div class="item-combo">
                <input type="text" class="form-control form-control-sm _item-input"
                       value="${_esc(r.nombre)}" title="${_esc(r.nombre)}" placeholder="Buscar ítem…" autocomplete="off">
                <span class="_combo-arrow">▾</span>
            </div>
        </td>
        <td data-col="cantcont"><input type="number" class="form-control form-control-sm _cant-cont" min="0" step="0.001" value="" placeholder="—"></td>
        <td data-col="cant"><input type="number" class="form-control form-control-sm _cant" min="0.001" step="0.001" value="1"></td>
        <td data-col="precio"><input type="number" class="form-control form-control-sm _precio" min="0" step="0.01" value="0"></td>
        <td data-col="dto"><input type="number" class="form-control form-control-sm _dto" min="0" max="100" step="0.01" value="0"></td>
        <td data-col="impdto" class="text-end _imp-dto">$ 0,00</td>
        <td data-col="iva">
            <select class="form-select form-select-sm _iva" style="appearance:auto;-webkit-appearance:auto">
                <option value="0">0%</option>
                <option value="10.5">10,5%</option>
                <option value="21" selected>21%</option>
            </select>
        </td>
        <td data-col="nograv"><input type="number" class="form-control form-control-sm _nograv" min="0" step="0.01" value="0"></td>
        <td data-col="subtotal" class="text-end fw-semibold text-primary _subtotal">$ 0,00</td>
        <td data-col="obs"><input type="text" class="form-control form-control-sm _obs" placeholder="…"></td>
        <td data-col="activo" class="text-center"><input type="checkbox" class="form-check-input _es-activo" title="Es un activo fijo (bien de uso, no inventario)"></td>
        <td class="td-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 lh-1"
                    onclick="_delRow('${r.id}')">✕</button></td>`;
    _attachSearch(tr, r);
    _attachEsActivo(tr, r);
    return tr;
}

// ── Marcar/desmarcar una fila como "activo fijo" ────────────────────────────────
// Repurpone el buscador de ítem (autocompletado contra materias primas/insumos/productos) como
// campo de texto libre para el nombre del activo — no tiene sentido buscarlo en el catálogo de
// inventario porque no lo es. El resto de la fila (cantidad/precio/dto/iva) se sigue usando igual
// para calcular su "valor" (ver ComprasController::guardar/actualizar).
function _attachEsActivo(tr, r) {
    const chk   = tr.querySelector('._es-activo');
    const input = tr.querySelector('._item-input');
    chk.addEventListener('change', function () {
        r.es_activo_fijo = chk.checked;
        if (chk.checked) {
            r.tipo_item = ''; r.item_id = null; r.tipo_label = '';
            input.placeholder = 'Nombre del activo…';
            r.nombre = input.value;
        } else {
            input.placeholder = 'Buscar ítem…';
        }
        _syncJSON();
    });
}

// ── Render completo (solo en init de edición) ──────────────────────────────────
function _render() {
    const tbody = document.getElementById('cuerpoItems');
    tbody.innerHTML = '';
    _rows.forEach(r => tbody.appendChild(_mkTr(r)));
    _aplicarColumnasVisibles();
}

// ── Agregar fila sin destruir las existentes ───────────────────────────────────
function _addRow() {
    const r = _mkRow();
    _rows.push(r);
    document.getElementById('cuerpoItems').appendChild(_mkTr(r));
    _aplicarColumnasVisibles();
}

// ── Quitar fila por id sin re-renderizar las demás ────────────────────────────
function _delRow(id) {
    _rows = _rows.filter(r => r.id !== id);
    const tr = document.querySelector(`#cuerpoItems tr[data-rid="${id}"]`);
    if (tr) tr.remove();
    if (!_rows.length) _addRow();
    _calcTotals();
    _syncJSON();
}

// ── Dropdown portal global (escapa el overflow de table-responsive) ────────────
const _DD = document.createElement('div');
_DD.style.cssText = [
    'display:none', 'position:fixed', 'z-index:9999',
    'background:#fff', 'border:1px solid #c8ccd0',
    'border-radius:6px', 'box-shadow:0 6px 24px rgba(0,0,0,.18)',
    'min-width:340px', 'max-height:260px', 'overflow-y:auto',
    'font-size:.83rem',
].join(';');
document.body.appendChild(_DD);
let _DD_tr = null, _DD_r = null, _DD_input = null, _DD_q = '';

function _ddPos(input) {
    const rect = input.getBoundingClientRect();
    _DD.style.top   = (rect.bottom + 2) + 'px';
    _DD.style.left  = rect.left + 'px';
    _DD.style.width = Math.max(rect.width, 340) + 'px';
}

document.addEventListener('mousedown', function(e) {
    if (_DD.style.display === 'none') return;
    if (!_DD.contains(e.target) && !e.target.closest('._item-input') && !e.target.closest('._combo-arrow')) {
        _DD.style.display = 'none';
    }
});

// ── Búsqueda inline por fila ───────────────────────────────────────────────────
function _attachSearch(tr, r) {
    const input = tr.querySelector('._item-input');
    const arrow = tr.querySelector('._combo-arrow');
    let timer;

    async function _fetch(q) {
        const proveedorId = document.getElementById('fProveedor').value || 0;
        const url = `${BASE_URL}/compras/buscar-items?q=${encodeURIComponent(q)}&proveedor_id=${proveedorId}`;
        try {
            const data = await fetch(url).then(x => x.json());
            _DD_tr = tr; _DD_r = r; _DD_input = input; _DD_q = q.trim();
            _showDd(data);
        } catch {}
    }

    function _esActivo() {
        return tr.querySelector('._es-activo')?.checked;
    }

    input.addEventListener('input', () => {
        // Fila marcada como activo fijo: el input es el nombre libre del activo, no un buscador
        // de inventario — no dispara la búsqueda (ver _attachEsActivo).
        if (_esActivo()) { r.nombre = input.value; _syncJSON(); return; }
        clearTimeout(timer);
        const q = input.value.trim();
        if (!q) { _DD.style.display = 'none'; return; }
        timer = setTimeout(() => _fetch(q), 280);
    });

    input.addEventListener('focus', () => {
        if (_esActivo()) return;
        const q = input.value.trim();
        if (q) _fetch(q);
    });

    arrow.addEventListener('mousedown', e => {
        if (_esActivo()) return;
        e.preventDefault();
        if (_DD.style.display !== 'none' && _DD_input === input) {
            _DD.style.display = 'none'; return;
        }
        input.focus();
        _fetch(input.value.trim() || ' ');
    });
}

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible, se
// abre/cierra el modal haciendo clic en un trigger oculto con data-bs-toggle/dismiss.
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
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getInstance(el)?.hide();
    } else {
        document.querySelector('#' + id + ' [data-bs-dismiss="modal"]')?.click();
    }
}

// Aplica la selección de un ítem (existente o recién creado) a la fila que tenía
// el buscador abierto — mismo efecto sin importar de dónde vino el ítem.
function _seleccionarItemEnFila(tipo, id, nombre, cc, precio) {
    const tr = _DD_tr, r = _DD_r, input = _DD_input;
    if (!tr || !r || !input) return;
    r.tipo_item  = tipo;
    r.item_id    = id;
    r.nombre     = nombre;
    // Lo que el usuario tipeó para encontrarlo (antes de que el input se pise con el
    // nombre canónico abajo) — se manda al guardar para que el proveedor de esta
    // factura aprenda la equivalencia (ver FacturaCompraModel::buscarItems()).
    r.descripcion_buscada = _DD_q || '';
    r.tipo_label = tipo.replace(/_/g, ' ');
    input.value  = r.nombre;
    input.title  = r.nombre;
    if (cc)  tr.querySelector('._cant-cont').value = cc;
    if (precio && parseFloat(tr.querySelector('._precio').value) === 0) tr.querySelector('._precio').value = precio;
    _DD.style.display = 'none';
    _updateComputed(tr);
    _calcTotals();
    _syncJSON();
    _loadUltimaCompra(tr, r.tipo_item, r.item_id);
}

function _showDd(items) {
    _ddPos(_DD_input);

    const nuevoHtml = `
        <div class="_dd-opt-nuevo"
             style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f2f2f2;color:#0d6efd;font-weight:600">
            ➕ Crear nuevo${_DD_q ? `: "${_esc(_DD_q)}"` : '…'}
        </div>`;

    const itemsHtml = items.length ? items.map(it => `
        <div class="_dd-opt"
             style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f2f2f2;display:flex;align-items:center;gap:10px${it.via_alias ? ';background:#f0f8ff' : ''}"
             data-tipo="${_esc(it.tipo)}" data-id="${it.id}"
             data-nombre="${_esc(it.nombre)}"
             data-cc="${it.cantidad_contenida ?? ''}"
             data-precio="${it.costo_unitario ?? 0}"
             data-unidad="${_esc(it.unidad ?? '')}">
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${_esc(it.nombre)}</div>
                ${it.codigo ? `<div style="font-size:.75rem;color:#999">${_esc(it.codigo)}</div>` : ''}
            </div>
            ${it.via_alias ? '<span style="flex-shrink:0;font-size:.71rem;background:#0d6efd;border-radius:3px;padding:2px 7px;color:#fff;white-space:nowrap">✓ usado con este proveedor</span>' : ''}
            <span style="flex-shrink:0;font-size:.71rem;background:#e9ecef;border-radius:3px;padding:2px 7px;color:#555;white-space:nowrap">${_esc(it.tipo.replace(/_/g,' '))}</span>
        </div>`
    ).join('') : '<div style="padding:10px 16px;color:#888">Sin resultados.</div>';

    _DD.innerHTML = nuevoHtml + itemsHtml;

    _DD.querySelector('._dd-opt-nuevo').addEventListener('mousedown', e => {
        e.preventDefault();
        _DD.style.display = 'none';
        _abrirModalCrearItem(_DD_q);
    });

    _DD.querySelectorAll('._dd-opt').forEach(opt => {
        opt.addEventListener('mouseover',  () => opt.style.background = '#f0f4ff');
        opt.addEventListener('mouseout',   () => opt.style.background = '');
        opt.addEventListener('mousedown', e => {
            e.preventDefault();
            _seleccionarItemEnFila(opt.dataset.tipo, parseInt(opt.dataset.id), opt.dataset.nombre, opt.dataset.cc, opt.dataset.precio);
        });
    });

    _DD.style.display = '';
}

// ── Alta rápida de ítem desde el buscador de la línea ──────────────────────────
function _abrirModalCrearItem(nombreInicial) {
    document.getElementById('ciNombre').value = nombreInicial || '';
    document.getElementById('ciTipo').value = 'materia_prima';
    document.getElementById('ciGenerico').value = '';
    document.getElementById('ciError').classList.add('d-none');
    _refrescarSegunTipoCrearItem();
    _showModal('modalCrearItemRapido');
    setTimeout(() => document.getElementById('ciNombre').focus(), 200);
}

function _refrescarSegunTipoCrearItem() {
    const tipo = document.getElementById('ciTipo').value;
    const selCat = document.getElementById('ciCategoria');
    const opciones = CATEGORIAS_CREAR_ITEM.filter(c => c.tipo === tipo);
    selCat.innerHTML = '<option value="">— Sin categoría —</option>' +
        opciones.map(c => `<option value="${c.id}">${_esc(c.nombre)}</option>`).join('');

    // Ingrediente genérico: solo tiene sentido para materia_prima.
    const esMP = tipo === 'materia_prima';
    document.getElementById('ciGenericoWrap').style.display = esMP ? '' : 'none';
    if (!esMP) document.getElementById('ciGenerico').value = '';
}

document.getElementById('ciTipo')?.addEventListener('change', _refrescarSegunTipoCrearItem);

(function _initUnidadesYGenericosCrearItem() {
    const selU = document.getElementById('ciUnidad');
    if (selU) {
        selU.innerHTML = '<option value="">— —</option>' +
            UNIDADES_CREAR_ITEM.map(u => `<option value="${u.id}">${_esc(u.nombre)} (${_esc(u.abreviatura)})</option>`).join('');
    }
    const selG = document.getElementById('ciGenerico');
    if (selG) {
        selG.innerHTML = '<option value="">— Ninguno —</option>' +
            GENERICOS_CREAR_ITEM.map(g => `<option value="${g.id}">${_esc(g.nombre)}${g.unidad ? ` (${_esc(g.unidad)})` : ''}</option>`).join('');
    }
})();

document.getElementById('formCrearItemRapido')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const errEl = document.getElementById('ciError');
    errEl.classList.add('d-none');
    const btn = document.getElementById('btnCrearItemRapido');
    btn.disabled = true;
    try {
        const fd = new FormData(this);
        const r = await fetch(`${BASE_URL}/compras/crear-item`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            _hideModal('modalCrearItemRapido');
            showToast(`"${d.nombre}" creado.`);
            _seleccionarItemEnFila(d.tipo, d.id, d.nombre, null, null);
        } else {
            errEl.textContent = d.error; errEl.classList.remove('d-none');
        }
    } catch {
        errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
});

// ── Auto-rellenar fila con última compra del ítem ─────────────────────────────
async function _loadUltimaCompra(tr, tipo, itemId) {
    const proveedorId = document.getElementById('fProveedor').value;
    if (!itemId) return;
    try {
        const url = `${BASE_URL}/compras/ultima-compra?tipo=${encodeURIComponent(tipo)}&item_id=${itemId}&proveedor_id=${proveedorId || 0}`;
        const d   = await fetch(url).then(x => x.json());
        if (!d.ok || !d.data) { tr.querySelector('._cant')?.select(); return; }
        const uc  = d.data;
        const gEl = cls => tr.querySelector(cls);
        if (uc.cantidad_contenida != null && uc.cantidad_contenida !== '')
            gEl('._cant-cont').value = uc.cantidad_contenida;
        if (uc.cantidad && parseFloat(uc.cantidad) > 0)
            gEl('._cant').value = uc.cantidad;
        if (uc.precio_unitario && parseFloat(uc.precio_unitario) > 0)
            gEl('._precio').value = uc.precio_unitario;
        if (uc.descuento_pct != null)
            gEl('._dto').value = uc.descuento_pct;
        if (uc.iva_porcentaje != null) {
            const ivaEl = gEl('._iva');
            [...ivaEl.options].forEach(o => { o.selected = parseFloat(o.value) === parseFloat(uc.iva_porcentaje); });
        }
        if (uc.monto_no_gravado && parseFloat(uc.monto_no_gravado) !== 0)
            gEl('._nograv').value = uc.monto_no_gravado;
        if (uc.observaciones)
            gEl('._obs').value = uc.observaciones;
        _updateComputed(tr);
        _calcTotals();
        _syncJSON();
        tr.querySelector('._cant')?.select();
    } catch {}
}

// ── Actualizar celdas calculadas ──────────────────────────────────────────────
document.getElementById('cuerpoItems').addEventListener('change', function(e) {
    const tr = e.target.closest('tr[data-rid]');
    if (!tr) return;
    _updateComputed(tr);
    _calcTotals();
    _syncJSON();
});

function _updateComputed(tr) {
    const gN = cls => parseFloat(tr.querySelector(cls)?.value) || 0;
    const cant   = gN('._cant');
    const precio = gN('._precio');
    const dto    = gN('._dto');
    const iva    = gN('._iva');
    const ng     = gN('._nograv');
    const bruto  = cant * precio;
    const impDto = bruto * dto / 100;
    const neto   = bruto - impDto;
    const ivaImp = neto * iva / 100;
    tr.querySelector('._imp-dto').textContent  = _fmtM(impDto);
    tr.querySelector('._subtotal').textContent = _fmtM(neto + ivaImp + ng);
}

// ── Totales resumen ────────────────────────────────────────────────────────────
function _calcTotals() {
    let neto = 0, iva = 0, ng = 0;
    document.querySelectorAll('#cuerpoItems tr[data-rid]').forEach(tr => {
        // Un activo fijo SÍ suma al total de la factura (así la deuda con el proveedor queda
        // bien si se compró a crédito) — lo que no hace es entrar al detalle de inventario ni
        // contarse dos veces en Resumen Económico (eso se resuelve aparte, restando
        // monto_activos_fijos en GastoModel::estadoResultados, no acá).
        const gN = cls => parseFloat(tr.querySelector(cls)?.value) || 0;
        const cant = gN('._cant'), precio = gN('._precio'), dto = gN('._dto'),
              ivaPct = gN('._iva'), noGr = gN('._nograv');
        const bruto  = cant * precio;
        const impDto = bruto * dto / 100;
        const n      = bruto - impDto;
        neto += n;
        iva  += n * ivaPct / 100;
        ng   += noGr;
    });
    const total = neto + iva + ng;
    window._currentTotal = total;
    document.getElementById('resSubtotal').textContent = _fmtM(neto);
    document.getElementById('resIva').textContent      = _fmtM(iva);
    document.getElementById('resTotal').textContent    = _fmtM(total);
    const elBTotal = document.getElementById('resumenBarra_total');
    const elBSaldo = document.getElementById('resumenBarra_saldo');
    if (elBTotal) elBTotal.textContent = _fmtM(total);
    if (elBSaldo) elBSaldo.textContent = _fmtM(Math.max(0, total - PAGADO_TOTAL));
}

// ── Sincronizar JSON oculto ────────────────────────────────────────────────────
function _syncJSON() {
    const out = [];
    document.querySelectorAll('#cuerpoItems tr[data-rid]').forEach(tr => {
        const r = _rows.find(x => x.id === tr.dataset.rid);
        if (!r) return;
        const esActivo = tr.querySelector('._es-activo')?.checked;
        if (!esActivo && !r.item_id) return;
        if (esActivo && !r.nombre?.trim()) return;
        const gN   = cls => parseFloat(tr.querySelector(cls)?.value) || 0;
        const cant = gN('._cant');
        if (cant <= 0) return;
        const precio = gN('._precio'), dto = gN('._dto'),
              iva = gN('._iva'), ng = gN('._nograv');
        const ccRaw = tr.querySelector('._cant-cont')?.value;
        const bruto  = cant * precio;
        const impDto = _r4(bruto * dto / 100);
        const neto   = _r4(bruto - impDto);
        const ivaImp = _r4(neto * iva / 100);
        out.push({
            tipo_item:          esActivo ? 'activo_fijo' : r.tipo_item,
            item_id:            esActivo ? null : r.item_id,
            es_activo_fijo:     !!esActivo,
            nombre_activo:      esActivo ? r.nombre.trim() : undefined,
            cantidad_contenida: ccRaw !== '' && ccRaw != null ? (parseFloat(ccRaw) || null) : null,
            cantidad:           cant,
            precio_unitario:    precio,
            descuento_pct:      dto,
            importe_dto:        impDto,
            iva_porcentaje:     iva,
            monto_no_gravado:   ng,
            observaciones:      tr.querySelector('._obs')?.value || '',
            descripcion_buscada: esActivo ? '' : (r.descripcion_buscada || ''),
            subtotal:           neto,
            subtotal_con_iva:   _r4(neto + ivaImp + ng),
        });
    });
    document.getElementById('itemsJson').value = JSON.stringify(out);
}

function _r4(v)   { return Math.round(v * 10000) / 10000; }
function _fmtM(v) { return '$ ' + parseFloat(v || 0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function _esc(s)  { const d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; }

// ── Envío ─────────────────────────────────────────────────────────────────────
document.getElementById('formCompra').addEventListener('submit', async function(ev) {
    ev.preventDefault();
    _syncJSON();
    const errEl = document.getElementById('formError');
    errEl.classList.add('d-none');

    if (!document.getElementById('fProveedor').value) {
        errEl.textContent = 'Seleccione un proveedor.'; errEl.classList.remove('d-none'); return;
    }
    const parsedItems = JSON.parse(document.getElementById('itemsJson').value || '[]');
    if (!parsedItems.length) {
        errEl.textContent = 'Agregue al menos un ítem con cantidad válida.';
        errEl.classList.remove('d-none'); return;
    }

    const btnGuardar     = document.getElementById('btnGuardar');
    const spinnerGuardar = document.getElementById('spinnerGuardar');
    const btnGuardarTexto = document.getElementById('btnGuardarTexto');
    const textoOriginal  = btnGuardarTexto.textContent;

    btnGuardar.disabled = true;
    spinnerGuardar.classList.remove('d-none');
    // Al editar, ahora esto también recalcula el costo repasando el historial real de
    // compras del ítem — sigue siendo rápido, pero el spinner deja claro que está
    // procesando algo, no que quedó colgado.
    btnGuardarTexto.textContent = 'Guardando…';

    const fd = new FormData(this);
    try {
        const r = await fetch(SUBMIT_URL, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            showToast(d.mensaje);
            // Avisos no bloqueantes (ej. cantidad_contenida distinta a lo habitual para ese
            // ítem) — la factura ya se guardó igual; solo se le da tiempo al usuario de leerlos
            // antes de navegar a "Ver factura".
            const advertencias = Array.isArray(d.advertencias) ? d.advertencias : [];
            advertencias.forEach(a => showToast(a, 'warning'));
            const espera = advertencias.length ? 3500 : 800;
            // Al actualizar una factura existente, quedarse en esta misma pantalla de edición
            // en vez de saltar a "Ver factura" — así se puede seguir editando/adjuntando sin
            // perder el lugar. Al crear una nueva sí tiene sentido ir a verla.
            setTimeout(() => {
                if (ES_EDICION) window.location.reload();
                else window.location.href = `${BASE_URL}/compras/ver?id=${d.id}&volver=${encodeURIComponent(VOLVER_URL)}`;
            }, espera);
        } else {
            errEl.textContent = d.error; errEl.classList.remove('d-none');
            btnGuardar.disabled = false;
            spinnerGuardar.classList.add('d-none');
            btnGuardarTexto.textContent = textoOriginal;
        }
    } catch {
        errEl.textContent = 'Error de conexión.'; errEl.classList.remove('d-none');
        btnGuardar.disabled = false;
        spinnerGuardar.classList.add('d-none');
        btnGuardarTexto.textContent = textoOriginal;
    }
});

// ── Init ──────────────────────────────────────────────────────────────────────
if (FACTURA_EDIT && FACTURA_EDIT.detalle.length) {
    FACTURA_EDIT.detalle.forEach(item => {
        const r   = _mkRow();
        r.tipo_item = item.tipo_item;
        r.item_id   = parseInt(item.materia_prima_id || item.insumo_id || item.producto_id);
        r.nombre    = item.item_nombre;
        _rows.push(r);
    });
    _render();
    // Rellenar valores numéricos después del render
    document.querySelectorAll('#cuerpoItems tr[data-rid]').forEach((tr, i) => {
        const item = FACTURA_EDIT.detalle[i];
        if (!item) return;
        const s = cls => tr.querySelector(cls);
        if (item.cantidad_contenida != null && item.cantidad_contenida !== '') s('._cant-cont').value = item.cantidad_contenida;
        s('._cant').value   = item.cantidad;
        s('._precio').value = item.precio_unitario;
        s('._dto').value    = item.descuento_pct || 0;
        [...s('._iva').options].forEach(o => { o.selected = parseFloat(o.value) === parseFloat(item.iva_porcentaje); });
        s('._nograv').value = item.monto_no_gravado || 0;
        if (item.observaciones) s('._obs').value = item.observaciones;
        _updateComputed(tr);
    });
    _calcTotals();
    _syncJSON();
} else {
    _addRow();
}

// ── Modal editar pago ─────────────────────────────────────────────────────────
<?php if ($esEdicion): ?>
(function () {
    const EP_METODOS = [
        { value: 'efectivo',      label: 'Efectivo' },
        { value: 'transferencia', label: 'Transferencia' },
        { value: 'mercado_pago',  label: 'Mercado Pago' },
        { value: 'debito',        label: 'Débito' },
        { value: 'cheque',        label: 'Cheque' },
        { value: 'otro',          label: 'Otro' },
    ];

    const epModalEl   = document.getElementById('modalEditarPago');
    const epFilasWrap = document.getElementById('epFilasPago');
    const epErrEl     = document.getElementById('epError');
    let epCtr = 0;
    const _epShow = () => document.getElementById('epModalTrigger').click();
    const _epHide = () => epModalEl.querySelector('[data-bs-dismiss="modal"]').click();

    function _epOptHtml(sel) {
        return EP_METODOS.map(m =>
            `<option value="${m.value}"${m.value === sel ? ' selected' : ''}>${m.label}</option>`
        ).join('');
    }

    function _epAddFila(metodo = 'efectivo', monto = '') {
        const fid = ++epCtr;
        const div = document.createElement('div');
        div.className = 'd-flex align-items-center gap-2 mb-1 ep-fila';
        div.dataset.fid = fid;
        div.innerHTML = `
            <select class="form-select form-select-sm ep-sel" style="width:160px">${_epOptHtml(metodo)}</select>
            <div class="input-group input-group-sm" style="width:145px">
                <span class="input-group-text px-2">$</span>
                <input type="number" class="form-control ep-monto-inp" min="0" step="0.01" value="${monto}" placeholder="0,00">
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 lh-1 ep-del"
                    style="font-size:1rem;width:20px" title="Quitar">✕</button>`;
        div.querySelector('.ep-monto-inp').addEventListener('input', _epRecalc);
        div.querySelector('.ep-sel').addEventListener('change', _epCheckMp);
        div.querySelector('.ep-del').addEventListener('click', () => {
            if (epFilasWrap.querySelectorAll('.ep-fila').length <= 1) return;
            div.remove(); _epRecalc(); _epCheckMp();
        });
        epFilasWrap.appendChild(div);
        _epRecalc();
    }

    function _epRecalc() {
        let t = 0;
        epFilasWrap.querySelectorAll('.ep-monto-inp').forEach(i => { t += parseFloat(i.value || 0); });
        document.getElementById('ep_total_label').textContent = '$ ' + t.toLocaleString('es-AR', { minimumFractionDigits: 2 });
        return t;
    }

    function _epCheckMp() {
        const hasMp = [...epFilasWrap.querySelectorAll('.ep-sel')].some(s => s.value === 'mercado_pago');
        document.getElementById('wrapEpRefMp').style.display = hasMp ? '' : 'none';
    }

    function _epGetMontos() {
        const m = { efectivo: 0, transferencia: 0, mercado_pago: 0, debito: 0, cheque: 0, otro: 0 };
        epFilasWrap.querySelectorAll('.ep-fila').forEach(div => {
            const k = div.querySelector('.ep-sel').value;
            const v = parseFloat(div.querySelector('.ep-monto-inp').value || 0);
            if (k in m) m[k] += v;
        });
        return m;
    }

    document.getElementById('btnEpAgregarMetodo').addEventListener('click', () => _epAddFila());

    // Origen toggle
    document.querySelectorAll('input[name="ep_origen"]').forEach(r => {
        r.addEventListener('change', () => {
            document.getElementById('wrapEpSocio').classList.toggle('d-none', r.value !== 'socio');
        });
    });

    // Abrir modal con datos del pago
    // ── Comprobantes de pago (varios por pago) ──────────────────────────────────
    let _epPagoActual = null;

    function _epRenderComprobantes(pago) {
        _epPagoActual = pago;
        const wrap = document.getElementById('epComprobantesLista');
        const items = [];
        if (pago.comprobante_path) items.push({ tipo: 'legado', url: `${BASE_URL}/pagos-proveedores/comprobante?id=${pago.id}` });
        (pago.adjuntos || []).forEach(a => items.push({ tipo: 'adjunto', id: a.id, url: `${BASE_URL}/pagos-proveedores/comprobante-adjunto?id=${a.id}` }));

        if (!items.length) { wrap.innerHTML = '<span class="text-muted small">Sin comprobantes.</span>'; return; }

        wrap.innerHTML = items.map((it, i) => `
            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-2">
                <a href="${it.url}" target="_blank" class="text-decoration-none">📎 Comprobante ${i + 1}</a>
                ${it.tipo === 'adjunto' ? `<button type="button" class="btn-close" style="font-size:.55rem" data-adjunto-id="${it.id}" aria-label="Quitar"></button>` : ''}
            </span>`).join('');

        wrap.querySelectorAll('.btn-close').forEach(b => b.addEventListener('click', async function () {
            const fd = new FormData();
            fd.append('_csrf_token', CSRF_TOKEN);
            fd.append('id', this.dataset.adjuntoId);
            try {
                const r = await fetch(`${BASE_URL}/pagos-proveedores/eliminar-comprobante-adjunto`, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    _epPagoActual.adjuntos = (_epPagoActual.adjuntos || []).filter(a => String(a.id) !== this.dataset.adjuntoId);
                    _epRenderComprobantes(_epPagoActual);
                } else if (typeof showToast === 'function') showToast(d.error || 'No se pudo eliminar.', 'danger');
            } catch { if (typeof showToast === 'function') showToast('Error de conexión.', 'danger'); }
        }));
    }

    document.getElementById('epComprobantesInput').addEventListener('change', async function () {
        if (!this.files.length || !_epPagoActual) return;
        const fd = new FormData();
        fd.append('_csrf_token', CSRF_TOKEN);
        fd.append('pago_id', _epPagoActual.id);
        [...this.files].forEach(f => fd.append('archivos[]', f));
        this.value = '';
        try {
            const r = await fetch(`${BASE_URL}/pagos-proveedores/subir-comprobantes-adjuntos`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                _epPagoActual.adjuntos = d.adjuntos;
                _epRenderComprobantes(_epPagoActual);
                if (typeof showToast === 'function') showToast(d.mensaje, 'success');
            } else if (typeof showToast === 'function') showToast(d.error || 'No se pudo subir.', 'danger');
        } catch { if (typeof showToast === 'function') showToast('Error de conexión.', 'danger'); }
    });

    document.querySelectorAll('.btn-editar-pago').forEach(btn => {
        btn.addEventListener('click', () => {
            const pago = JSON.parse(btn.dataset.pago);
            document.getElementById('ep_pago_id').value    = pago.id;
            document.getElementById('ep_factura_id').value = btn.dataset.facturaId || '';
            document.getElementById('ep_fecha').value      = pago.fecha;
            document.getElementById('ep_concepto').value   = pago.concepto || '';
            _epRenderComprobantes(pago);

            // Limpiar filas y agregar las que tienen monto > 0
            epFilasWrap.innerHTML = '';
            epCtr = 0;
            let hasFila = false;
            EP_METODOS.forEach(({ value }) => {
                const key   = 'monto_' + value;
                const monto = parseFloat(pago[key] || 0);
                if (monto > 0) { _epAddFila(value, monto); hasFila = true; }
            });
            if (!hasFila) _epAddFila('efectivo', '');

            // Origen
            const origenId = 'ep_origen_' + (pago.origen_pago || 'empresa');
            const origenEl = document.getElementById(origenId);
            if (origenEl) origenEl.checked = true;
            document.getElementById('wrapEpSocio').classList.toggle('d-none', pago.origen_pago !== 'socio');
            const socioSel = document.getElementById('ep_socio_select');
            if (socioSel && pago.socio_id) socioSel.value = pago.socio_id;

            epErrEl.classList.add('d-none');
            _epRecalc();
            _epCheckMp();
            _epShow();
        });
    });

    // Guardar cambios
    document.getElementById('btnEpGuardar').addEventListener('click', async () => {
        epErrEl.classList.add('d-none');
        const total = _epRecalc();
        if (total <= 0) {
            epErrEl.textContent = 'Ingresá al menos un monto mayor a cero.';
            epErrEl.classList.remove('d-none'); return;
        }

        const btn = document.getElementById('btnEpGuardar');
        btn.disabled = true; btn.textContent = 'Guardando…';

        const origen   = document.querySelector('input[name="ep_origen"]:checked')?.value ?? 'empresa';
        const socioEl  = document.getElementById('ep_socio_select');
        const montos   = _epGetMontos();
        const fd       = new FormData();
        fd.append('_csrf_token',         CSRF_TOKEN);
        fd.append('pago_id',             document.getElementById('ep_pago_id').value);
        fd.append('factura_id',          document.getElementById('ep_factura_id').value);
        fd.append('fecha',               document.getElementById('ep_fecha').value);
        fd.append('concepto',            document.getElementById('ep_concepto').value);
        fd.append('monto_efectivo',      montos.efectivo);
        fd.append('monto_transferencia', montos.transferencia);
        fd.append('monto_mercado_pago',  montos.mercado_pago);
        fd.append('monto_debito',        montos.debito);
        fd.append('monto_cheque',        montos.cheque);
        fd.append('monto_otro',          montos.otro);
        fd.append('origen_pago',         origen);
        fd.append('socio_id',            origen === 'socio' ? (socioEl?.value || '') : '');
        if (document.getElementById('ep_ref_mp').value)
            fd.append('referencia_mp',   document.getElementById('ep_ref_mp').value);

        try {
            const r = await fetch(`${BASE_URL}/pagos-proveedores/actualizar`, { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                _epHide();
                showToast('Pago actualizado correctamente.');
                setTimeout(() => location.reload(), 600);
            } else {
                epErrEl.textContent = d.error || 'Error al guardar.';
                epErrEl.classList.remove('d-none');
            }
        } catch {
            epErrEl.textContent = 'Error de conexión.';
            epErrEl.classList.remove('d-none');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar cambios';
        }
    });
})();
<?php endif; ?>
</script>
