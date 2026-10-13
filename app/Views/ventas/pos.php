<?php if (!$turno): ?>
<div class="alert alert-warning d-flex align-items-center gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div>
        <strong>No hay turno de caja abierto.</strong>
        No se puede cobrar hasta que abras un turno.
        <a href="<?= url('caja') ?>" class="alert-link ms-2">Abrir turno →</a>
    </div>
</div>
<?php endif; ?>

<!-- Cartel de tarea asignada (ver docs/tareas-pendientes.md #2) — oculto hasta que
     cargarTareaPendiente() traiga algo por AJAX; se revisa con polling mientras esta
     pantalla siga abierta. -->
<div id="tareaBanner" class="alert alert-danger d-none shadow-sm">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="fw-bold text-white" id="tareaBannerTitulo">📋 Tarea</div>
            <div class="small text-white-50" id="tareaBannerAsignadaPor"></div>
            <div class="small mt-1 text-white" id="tareaBannerDescripcion"></div>
        </div>
        <div class="d-flex flex-column gap-1 align-items-end" style="min-width:260px">
            <div class="d-flex gap-2" id="tareaBannerAccionesPendiente">
                <button type="button" class="btn btn-success btn-sm" onclick="aceptarTarea()">✓ Aceptar</button>
                <button type="button" class="btn btn-outline-light btn-sm" onclick="abrirRechazoTarea()">Rechazar</button>
            </div>
            <div class="d-flex gap-2 d-none" id="tareaBannerAccionesAceptada">
                <button type="button" class="btn btn-success btn-sm" onclick="finalizarTarea()">✓ Finalizar</button>
            </div>
            <div class="d-flex gap-1 align-items-center mt-1">
                <span class="small text-white-50">Aplazar:</span>
                <button type="button" class="btn btn-outline-warning btn-sm py-0" onclick="aplazarTarea(15)">15m</button>
                <button type="button" class="btn btn-outline-warning btn-sm py-0" onclick="aplazarTarea(30)">30m</button>
                <button type="button" class="btn btn-outline-warning btn-sm py-0" onclick="aplazarTarea(60)">60m</button>
            </div>
        </div>
    </div>
</div>

<!-- Cartel de solicitud de inventario asignada (ver docs/solicitudes-inventario-checklist.md) —
     mismo patrón de aceptar/rechazar/aplazar/finalizar que tareaBanner (ver TareaAsignadaModel),
     aplicado a solicitudes tipo=inventario: pendiente → Aceptar/Rechazar; en_proceso → Ir a
     contar/Finalizar; aplazar disponible en ambos estados. -->
<div id="solicitudInventarioBanner" class="alert alert-warning d-none shadow-sm">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="fw-bold" id="solicitudInventarioBannerTitulo">📦 Solicitud de inventario</div>
            <div class="small" id="solicitudInventarioBannerAsignadaPor"></div>
            <div class="small mt-1" id="solicitudInventarioBannerDescripcion"></div>
        </div>
        <div class="d-flex flex-column gap-1 align-items-end" style="min-width:260px">
            <div class="d-flex gap-2" id="solicitudInventarioAccionesPendiente">
                <button type="button" class="btn btn-success btn-sm" onclick="aceptarSolicitudInventario()">✓ Aceptar</button>
                <button type="button" class="btn btn-outline-dark btn-sm" onclick="abrirRechazoSolicitudInventario()">Rechazar</button>
            </div>
            <div class="d-flex gap-2 d-none" id="solicitudInventarioAccionesEnProceso">
                <a href="#" id="solicitudInventarioBannerLink" class="btn btn-dark btn-sm">Ir a contar</a>
                <button type="button" class="btn btn-success btn-sm" onclick="finalizarSolicitudInventario()">✓ Finalizar</button>
            </div>
            <div class="d-flex gap-1 align-items-center mt-1">
                <span class="small">Aplazar:</span>
                <button type="button" class="btn btn-outline-dark btn-sm py-0" onclick="aplazarSolicitudInventario(15)">15m</button>
                <button type="button" class="btn btn-outline-dark btn-sm py-0" onclick="aplazarSolicitudInventario(30)">30m</button>
                <button type="button" class="btn btn-outline-dark btn-sm py-0" onclick="aplazarSolicitudInventario(60)">60m</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: motivo obligatorio para rechazar una solicitud de inventario -->
<div class="modal fade" id="modalRechazarSolicitudInventario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Rechazar solicitud de inventario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Motivo <span class="text-danger">*</span></label>
                <textarea id="solInvMotivoRechazo" class="form-control" rows="3" placeholder="¿Por qué no se puede hacer ahora?"></textarea>
                <div id="solInvRechazoError" class="alert alert-danger py-2 small mt-2 d-none"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarRechazoSolicitudInventario()">Rechazar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: motivo obligatorio para rechazar una tarea -->
<div class="modal fade" id="modalRechazarTarea" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Rechazar tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Motivo <span class="text-danger">*</span></label>
                <textarea id="tareaMotivoRechazo" class="form-control" rows="3" placeholder="¿Por qué no se puede hacer?"></textarea>
                <div id="tareaRechazoError" class="alert alert-danger py-2 small mt-2 d-none"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarRechazoTarea()">Rechazar tarea</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 h-pos">
    <!-- Panel izquierdo: categorías + búsqueda + catálogo -->
    <div class="col-lg-7 col-xl-7">
        <div class="card border-0 h-100 d-flex flex-column">
            <div class="card-header border-0 bg-transparent">
                <div class="input-group buscador-pos-llamativo">
                    <span class="input-group-text border-end-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input type="text" id="buscadorPos" class="form-control border-start-0" placeholder="Buscar producto por nombre o código...">
                </div>
            </div>
            <div class="card-body p-2 d-flex gap-2" id="catalogoContainer" style="max-height:calc(100vh - 200px)">
                <!-- Columna de categorías -->
                <div class="border rounded-3 overflow-auto flex-shrink-0 pos-categorias-panel" id="categoriasContainer" style="width:25%">
                    <div class="list-group list-group-flush" id="categoriasLista">
                        <div class="list-group-item text-muted small text-center py-3">Cargando...</div>
                    </div>
                </div>
                <!-- Columna de productos (a la derecha de las categorías) -->
                <div class="border rounded-3 overflow-auto flex-grow-1 pos-catalogo-panel" id="catalogoResultadosWrap">
                    <div id="catalogoResultados" class="p-2 d-flex flex-wrap gap-2">
                        <div class="text-muted small text-center py-4 w-100">
                            Elija una categoría o busque un producto...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: carrito + cobro. max-height + overflow-y propios (en vez de depender
         de que la página completa haga scroll) — en algunas pantallas/resoluciones o en la
         PWA el scroll del body no llegaba a activarse y el botón "Cobrar" quedaba tapado
         sin forma de alcanzarlo; con esto el panel se desplaza solo, igual que ya hace el
         catálogo de la izquierda (#catalogoContainer). -->
    <div class="col-lg-5 col-xl-5 d-flex flex-column gap-3 pos-col-cobro" id="posColumnaCobro">
        <!-- Carrito -->
        <div class="card border-0 flex-grow-1">
            <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-0">Artículos en la venta</h6>
                <div class="d-flex align-items-center gap-2">
                    <span id="posConexionBadge" class="badge bg-success-soft text-success">🟢 Conectado</span>
                    <button type="button" class="btn btn-sm btn-outline-warning position-relative" onclick="abrirModalPendientes()">
                        🕓 Pendientes
                        <span id="badgePendientes" class="badge rounded-pill bg-danger d-none" style="font-size:.65rem">0</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="crearNuevaMesa()" title="Dejar esta venta pendiente y empezar otra">🍽 Nueva mesa</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="limpiarCarrito()">Limpiar</button>
                </div>
            </div>
            <!-- Mesas/ventas pendientes: solo se muestra si hay más de una abierta (ver crearNuevaMesa()/renderTabsMesas()) -->
            <div class="px-3 pb-2 d-none flex-wrap gap-2 align-items-center" id="mesasTabsWrap">
                <span class="text-muted small me-1">Mesas abiertas:</span>
                <div class="d-flex flex-wrap gap-2" id="mesasTabs"></div>
            </div>
            <div class="card-body p-0 overflow-auto" style="max-height:340px">
                <table class="table table-sm mb-0" id="tblCarrito">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th><th style="width:90px">Cant.</th>
                            <th style="width:100px">Precio</th><th style="width:80px">Desc%</th>
                            <th style="width:90px" class="text-end">Subtotal</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="carritoBody"></tbody>
                    <tfoot>
                        <tr id="carritoVacio">
                            <td colspan="6" class="text-center text-muted py-4">El carrito está vacío</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Panel de cobro -->
        <div class="card border-0">
            <div class="card-body">
                <form id="formVenta" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="items_json" id="itemsJson" value="[]">
                    <input type="hidden" name="pedido_cliente_id" id="pedidoClienteIdInput" value="">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="row g-2">
                                <!-- Cliente -->
                                <div class="col-12 position-relative">
                                    <label class="form-label small fw-semibold">Cliente (opcional)</label>
                                    <input type="hidden" name="cliente_id" id="clienteId" value="">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="clienteBuscador" class="form-control form-control-sm"
                                               placeholder="Buscar cliente por nombre, email o CUIT..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarCliente" title="Quitar cliente" style="display:none">✕</button>
                                    </div>
                                    <div id="clienteDropdown" class="list-group position-absolute z-3 shadow-sm w-100" style="max-height:180px;overflow-y:auto;display:none;top:100%;left:0"></div>
                                </div>
                                <!-- Aviso no bloqueante: cliente escribió en el chat de un pedido ya aceptado y nadie contestó -->
                                <div class="col-12">
                                    <div id="avisoMensajePedido" class="alert alert-info py-1 px-2 small mb-0 d-none" style="cursor:pointer" onclick="abrirChatDesdeAviso()"></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Descuento global %</label>
                                    <input type="number" name="descuento_global" id="descGlobal" class="form-control form-control-sm" min="0" max="100" step="0.5" value="0">
                                </div>
                                <!-- Medios de pago: por defecto uno solo (radio); "Dividir pago" habilita elegir varios -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-semibold mb-0">Medios de pago</label>
                                        <span id="pagoResumenTexto" class="small fw-semibold"></span>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="chkDividirPago">
                                        <label class="form-check-label small text-muted" for="chkDividirPago">Dividir entre varios medios de pago</label>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="medio-card medio-card-efectivo">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkEfectivo" checked>
                                                    <label for="chkEfectivo" class="small mb-0 medio-card-label">💵 Efectivo</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_efectivo" id="montoEfectivo" class="form-control monto-medio" min="0" step="0.01" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="medio-card medio-card-debito">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkDebito">
                                                    <label for="chkDebito" class="small mb-0 medio-card-label">🏦 Débito</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_debito" id="montoDebito" class="form-control monto-medio" min="0" step="0.01" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="medio-card medio-card-credito">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkCredito">
                                                    <label for="chkCredito" class="small mb-0 medio-card-label">💳 Crédito</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_credito" id="montoCredito" class="form-control monto-medio" min="0" step="0.01" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="medio-card medio-card-transferencia">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkTransferencia">
                                                    <label for="chkTransferencia" class="small mb-0 medio-card-label">🔁 Transferencia</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_transferencia" id="montoTransferencia" class="form-control monto-medio" min="0" step="0.01" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="medio-card medio-card-mercadopago">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkMercadopago">
                                                    <label for="chkMercadopago" class="small mb-0 medio-card-label"><span class="badge-mp">MP</span> Mercado Pago</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_mercadopago" id="montoMercadopago" class="form-control monto-medio" min="0" step="0.01" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="medio-card medio-card-cuentacorriente">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input type="checkbox" class="medio-pago-chk" id="chkCuentaCorriente">
                                                    <label for="chkCuentaCorriente" class="small mb-0 medio-card-label">🧾 Cuenta Corriente</label>
                                                </div>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto_cuenta_corriente" id="montoCuentaCorriente" class="form-control monto-medio" min="0" step="0.01" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="avisoCuentaCorriente" class="small text-danger mt-1" style="display:none">
                                        Seleccioná un cliente para poder cobrar a cuenta corriente.
                                    </div>
                                    <div id="saldoClienteInfo" class="small text-muted mt-1" style="display:none"></div>
                                </div>
                                <!-- Vuelto (solo si hay efectivo asignado) -->
                                <div class="col-12" id="bloqueEfectivo" style="display:none">
                                    <label class="form-label small fw-semibold">Monto recibido en efectivo $</label>
                                    <input type="number" id="montoRecibido" class="form-control form-control-sm" min="0" step="0.01" placeholder="0,00">
                                    <div class="d-flex justify-content-between small mt-1 px-1">
                                        <span class="text-muted">Vuelto:</span>
                                        <span id="vueltoTexto" class="fw-bold text-success">$ 0,00</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Mesa / Destino</label>
                                    <input type="text" name="mesa" id="mesaInput" list="listaMesas"
                                           class="form-control form-control-sm"
                                           placeholder="Mostrador, Mesa 1…" autocomplete="off">
                                    <datalist id="listaMesas">
                                        <option value="Mostrador">
                                        <option value="Mesa 1">
                                        <option value="Mesa 2">
                                        <option value="Mesa 3">
                                        <option value="Mesa 4">
                                        <option value="Mesa 5">
                                        <option value="Mesa 6">
                                        <option value="Mesa 7">
                                        <option value="Mesa 8">
                                        <option value="Para llevar">
                                        <option value="Delivery">
                                    </datalist>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Notas</label>
                                    <input type="text" name="notas" class="form-control form-control-sm" placeholder="Observaciones...">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="facturar_afip" id="facturarAfip" value="1" <?= ($bloqueoFacturacionAfip || $limiteFacturacionAlcanzado) ? 'disabled' : '' ?> <?= $facturarAfipPorDefecto ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-semibold" for="facturarAfip">
                                            Facturar por AFIP
                                        </label>
                                    </div>
                                    <div id="afipAvisoBloqueado" class="small text-danger mt-1 <?= $bloqueoFacturacionAfip ? '' : 'd-none' ?>" style="font-size:.72rem">
                                        Bloqueado por el administrador (ver Resumen Económico).
                                    </div>
                                    <div id="afipAvisoLimite" class="small text-danger mt-1 <?= (!$bloqueoFacturacionAfip && $limiteFacturacionAlcanzado) ? '' : 'd-none' ?>" style="font-size:.72rem">
                                        Alcanzaste el límite diario de facturación AFIP. Se habilita de nuevo mañana.
                                    </div>
                                    <div id="afipAviso90" class="small text-warning mt-1 d-none" style="font-size:.72rem">
                                        ⚠️ Se destildó solo: facturar esta venta llevaría el uso de hoy al 90% o más del límite diario. Podés volver a tildarlo si igual querés facturarla.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="bg-light rounded p-3 h-100">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="pos-resumen-label">Subtotal sin IVA</span>
                                    <span id="resSubtotal" class="fw-semibold">$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="pos-resumen-label">IVA</span>
                                    <span id="resIva">$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span class="pos-resumen-label">Descuento</span>
                                    <span id="resDescuento" class="text-danger">- $ 0,00</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">TOTAL</span>
                                    <span id="resTotal" class="fw-bold text-primary fs-3">$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="posError" class="alert alert-danger mt-3 d-none small"></div>

                    <?php if ($prorateoVigente): ?>
                    <div id="afipProrrateoBox" class="mt-3 p-2 rounded <?= $disponibleHoy <= 0 ? 'bg-danger-soft' : 'bg-light' ?>" title="Configurado por el admin en Resumen Económico — no se puede editar desde acá">
                        <div class="d-flex justify-content-between align-items-center" style="font-size:.8rem">
                            <span class="text-muted">Podés facturar hoy</span>
                            <span id="afipDisponibleHoy" class="fw-bold <?= $disponibleHoy <= 0 ? 'text-danger' : 'text-success' ?>"><?= moneda($disponibleHoy) ?></span>
                        </div>
                        <!-- "Podés facturar hoy" reparte lo que queda del saldo entre TODOS los días
                             restantes del período — con un período largo, una venta puntual apenas lo
                             mueve. Esta línea muestra lo facturado HOY tal cual, sin dividir, para que
                             el efecto de cada venta se vea de verdad. -->
                        <div class="d-flex justify-content-between align-items-center text-muted" style="font-size:.7rem">
                            <span>Facturado hoy</span>
                            <span id="afipFacturadoHoy" class="fw-semibold"><?= moneda($facturadoHoy) ?></span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.68rem">
                            <span id="afipProrrateoMsg"><?= $disponibleHoy <= 0 ? '⚠️ Ya alcanzaste el límite diario — "Facturar por AFIP" queda bloqueado hasta mañana.' : 'Incluye lo no facturado de días anteriores.' ?></span>
                            Vence el <?= fecha($prorateoHasta) ?>.
                        </div>
                    </div>
                    <?php elseif ($limiteMonoMes): ?>
                    <?php
                        $colorMonoPos = 'bg-success';
                        if ($pctMonoMes >= 95) $colorMonoPos = 'bg-danger';
                        elseif ($pctMonoMes >= 80) $colorMonoPos = 'bg-warning';
                    ?>
                    <div id="afipMonoBox" class="mt-3 p-2 rounded bg-light" title="Configurado por el admin en Resumen Económico — no se puede editar desde acá">
                        <div class="d-flex justify-content-between mb-1" style="font-size:.72rem">
                            <span class="text-muted">Límite facturación (Cat. <?= e($catMono) ?>) — últimos 12 meses</span>
                            <span id="afipMonoTexto" class="fw-semibold"><?= moneda($facturadoAfipMes) ?> / <?= moneda($limiteMonoMes) ?> (<?= number_format($pctMonoMes, 1) ?>%)</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div id="afipMonoBar" class="progress-bar <?= $colorMonoPos ?>" style="width:<?= $pctMonoMes ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$turno): ?>
                    <div class="small text-danger mt-2 mb-0">
                        ⚠ No podés cobrar sin un turno de caja abierto.
                        <a href="<?= url('caja') ?>">Abrir turno →</a>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success btn-lg flex-grow-1" id="btnCobrar" <?= !$turno ? 'disabled title="Abrí un turno de caja para poder cobrar"' : '' ?>>
                            ✓ Cobrar
                        </button>
                        <a href="<?= url('ventas') ?>" class="btn btn-danger">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal ticket post-venta -->
<div class="modal fade" id="modalTicket" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-success">✓ Venta registrada</h5>
                <button type="button" class="btn-close" aria-label="Cerrar" onclick="nuevaVenta()"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-3">Venta <strong id="ventaIdModal">#—</strong> guardada correctamente.</p>
                <div id="afipCaeWrap" class="d-none alert py-2 mb-3 text-start small" style="font-size:11px"></div>
                <div id="offlineVentaWrap" class="d-none alert alert-warning-soft py-2 mb-3 text-start small">
                    <strong>⚠ Guardada sin conexión</strong><br>
                    Se guardó localmente en este dispositivo. Sincronizala desde "🕓 Pendientes" cuando vuelva la conexión.
                </div>
                <div class="d-grid gap-2">
                    <button id="btnTicket" class="btn btn-outline-primary" onclick="previewTicket()">🖨 Vista previa ticket</button>
                    <a id="btnWhatsApp" href="#" target="_blank" class="btn btn-outline-success d-none">📲 Enviar por WhatsApp</a>
                    <button id="btnComanda" class="btn btn-outline-secondary" onclick="imprimirComanda()">📋 Imprimir comanda</button>
                    <button id="btnImprimirOffline" class="btn btn-outline-primary d-none" onclick="previewTicketOffline()">🖨 Imprimir comprobante interno</button>
                    <button class="btn btn-success" onclick="nuevaVenta()">+ Nueva venta</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal vista previa de impresión -->
<div class="modal fade" id="modalPreviewTicket" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalPreviewTicketTitulo">Vista previa — Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height:580px">
                <iframe id="iframeTicket" src="about:blank" style="width:100%;height:100%;border:0"></iframe>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="imprimirIframe()">
                    🖨 Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal editar item del carrito -->
<div class="modal fade" id="modalEditarItem" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">✎ Editar producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editIdx" value="">
                <h6 class="fw-semibold mb-3" id="editNombreProducto">—</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cantidad</label>
                        <input type="number" id="editCantidad" class="form-control form-control-lg" min="0.01" step="0.01">
                        <div class="form-text" id="editStockInfo"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Precio unitario $</label>
                        <input type="number" id="editPrecio" class="form-control form-control-lg" min="0" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Descuento %</label>
                        <input type="number" id="editDescuento" class="form-control form-control-lg" min="0" max="100" step="1">
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Subtotal del ítem</span>
                    <span id="editSubtotalPreview" class="fw-bold text-primary fs-3">$ 0,00</span>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarEditorItem()">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de opciones/adicionales del producto (ver docs/tareas-pendientes.md #1) -->
<div class="modal fade" id="modalOpciones" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <!-- Aviso de "sin stock, ¿elegir igual?" DENTRO del propio modal (no un segundo
                 modal de Bootstrap encimado): los modales anidados de este tema terminan
                 cerrando el de atrás al abrir uno nuevo, así que en vez de pelear con eso se
                 resuelve con una capa propia por encima de todo el modal-content — ver
                 _elegirOpcionConStock()/_confirmarOpcionStock()/_cancelarOpcionStock(). -->
            <div id="opcionesConfirmStock" class="d-none"
                 style="position:absolute;inset:0;z-index:20;background:rgba(0,0,0,.75);border-radius:inherit;display:flex;align-items:center;justify-content:center;padding:1rem">
                <div class="bg-body p-4 rounded shadow text-center" style="max-width:320px">
                    <div class="mb-2" style="font-size:2rem">⚠️</div>
                    <p id="opcionesConfirmStockMsg" class="mb-1 fw-semibold"></p>
                    <p class="small text-muted mb-3">Se va a descontar stock igual, puede quedar en negativo.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="_cancelarOpcionStock()">Cancelar</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="_confirmarOpcionStock()">Elegir igual</button>
                    </div>
                </div>
            </div>
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="opcionesNombreProducto">—</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <input type="text" id="buscadorOpciones" class="form-control form-control-sm"
                        placeholder="Buscar..." oninput="_filtrarOpciones(this.value)">
                </div>
                <div class="d-flex gap-3">
                    <!-- Sidebar de unidades de la promo (ej. "1/4kg — Unidad 1", "Unidad 2"): solo
                         se muestra para promos — ver renderComponentesPromo(). Cada unidad se
                         elige por separado; clickear una la muestra en el panel de al lado sin
                         perder lo ya elegido en las demás. -->
                    <div id="opcionesPromoSidebar" class="d-none" style="width:190px;flex-shrink:0;max-height:65vh;overflow-y:auto"></div>
                    <div id="opcionesGruposWrap" class="flex-grow-1" style="min-width:0"><div class="text-center text-muted py-4">Cargando…</div></div>
                </div>
                <div id="opcionesError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarOpciones" onclick="confirmarOpciones()">Agregar al carrito</button>
            </div>
        </div>
    </div>
</div>

<!-- Pedido de cliente del portal — bloqueante a propósito (sin data-bs-dismiss, sin botón de
     cierre): no se puede seguir usando el POS hasta Aceptar, Rechazar o al menos abrir el
     chat, ver docs/portal-clientes-mayoristas-checklist.md -->
<div class="modal fade" id="modalPedidoCliente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-warning-soft">
                <h5 class="modal-title fw-bold">🛎️ Nuevo pedido de <span id="pcNombreCliente">—</span></h5>
            </div>
            <div class="modal-body">
                <div id="pcDetalle"></div>
                <div id="pcNotas" class="alert alert-info py-2 small d-none mt-2"></div>
                <div id="pcRechazoWrap" class="d-none mt-3">
                    <label class="form-label small fw-semibold">Mensaje para el cliente (obligatorio para rechazar)</label>
                    <textarea id="pcMensajeRechazo" class="form-control form-control-sm" rows="2"></textarea>
                </div>
                <div id="pcError" class="alert alert-danger d-none py-2 small mt-2"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" onclick="abrirChatPedido()">💬 Chat</button>
                <button type="button" class="btn btn-outline-danger" id="btnPcRechazar" onclick="rechazarPedidoCliente()">Rechazar</button>
                <button type="button" class="btn btn-primary" onclick="aceptarPedidoCliente()">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- Chat del pedido — dismissible, no resuelve el modal bloqueante de arriba -->
<div class="modal fade" id="modalChatPedido" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Chat del pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="chatMensajes" style="max-height:320px;overflow-y:auto"></div>
            </div>
            <div class="modal-footer border-0">
                <input type="text" id="chatInputPc" class="form-control form-control-sm" placeholder="Escribir...">
                <button type="button" class="btn btn-primary btn-sm" onclick="enviarChatPedido()">Enviar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal ventas pendientes de sincronizar (guardadas sin conexión) -->
<div class="modal fade" id="modalVentasPendientes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">🕓 Ventas pendientes de sincronizar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Ventas guardadas sin conexión en este dispositivo. No se facturan por AFIP —
                    se sincronizan solo cuando vos lo decidas, apretando "Sincronizar ahora".
                </p>
                <div id="listaPendientes" class="list-group mb-3"></div>
                <div id="syncResultado" class="alert d-none py-2 small"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnSincronizarAhora" onclick="sincronizarPendientes()">🔄 Sincronizar ahora</button>
            </div>
        </div>
    </div>
</div>

<!-- ?v=filemtime (mismo criterio que assets/js/app.js en layouts/main.php): sin esto, el
     Service Worker (sw.js) precachea estos dos archivos por su URL fija "cache first" y
     un cambio acá nunca se nota en un navegador que ya los tenía cacheados — ni con
     recarga forzada, porque Cache Storage del SW es aparte del caché HTTP normal. Bug
     real: el fix de confirmar_sin_stock en pos-offline.js no tomaba efecto en producción
     por esto — el navegador seguía ejecutando la versión vieja cacheada. -->
<script src="<?= BASE_URL ?>/assets/js/pos-offline.js?v=<?= @filemtime(BASE_PATH.'/assets/js/pos-offline.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/pos-mesas.js?v=<?= @filemtime(BASE_PATH.'/assets/js/pos-mesas.js') ?: 1 ?>"></script>
<script>
const BASE_URL   = '<?= BASE_URL ?>';
// Sin turno de caja abierto no se puede cobrar (ver VentasController::guardar(), que rechaza
// la venta igual del lado servidor aunque esto se saltee a mano) — btnCobrar queda
// deshabilitado siempre, sin importar el resto de las condiciones de actualizarResumenPago().
const TURNO_ABIERTO = <?= $turno ? 'true' : 'false' ?>;
const CSRF_TOKEN = '<?= Csrf::token() ?>';
// Dos cosas DISTINTAS, que antes iban pegadas a la misma constante (ver core/PrecioHelper.php
// y la decisión D9 de docs/integracion-menuapp-checklist.md):
//
// 1. ES_RESPONSABLE_INSCRIPTO: si el comprobante DISCRIMINA IVA. Factura A/B lo desglosa;
//    Monotributista/Exento emiten Factura C y muestran IVA $0 siempre.
// 2. PRECIOS_INCLUYEN_IVA: si el precio CARGADO del producto ya trae el IVA adentro. Si no
//    lo trae, se le suma para llegar al precio que paga el cliente.
//
// Un Responsable Inscripto con precios "con IVA incluido" cobra el precio tal cual y lo
// desglosa en neto + IVA; con precios "sin IVA" le suma el IVA encima.
const ES_RESPONSABLE_INSCRIPTO = <?= $esResponsableInscripto ? 'true' : 'false' ?>;
const PRECIOS_INCLUYEN_IVA     = <?= $preciosIncluyenIva ? 'true' : 'false' ?>;

/**
 * Precio final (lo que paga el cliente) de un precio cargado — espejo exacto de
 * PrecioHelper::precioFinal(). El servidor vuelve a calcularlo igual al guardar la venta,
 * así que esto solo define lo que se MUESTRA; nunca es la fuente de verdad del total.
 */
function precioFinalCliente(precioCargado, ivaPorcentaje) {
    return PRECIOS_INCLUYEN_IVA ? precioCargado : precioCargado * (1 + (ivaPorcentaje || 0) / 100);
}

// Dashly bundlea Bootstrap sin exponer window.bootstrap: si no está disponible,
// abrimos/cerramos el modal por atributos data-bs-* en vez de la API de JS
// (Bootstrap sí registra esa delegación de eventos al importarse). Si el modal
// no tiene un botón data-bs-dismiss propio (ej. modalTicket), se crea uno oculto.
function _showModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    // getOrCreateInstance (NO "new Modal()"): en esta página el mismo modal (ej.
    // #modalTicket) se abre una vez por cada venta cobrada durante todo el turno, sin
    // recargar. "new bootstrap.Modal(el)" crea una instancia de controlador NUEVA cada vez
    // para el MISMO elemento del DOM sin liberar la anterior — Bootstrap solo reemplaza la
    // referencia "actual", pero la instancia vieja (con sus propios listeners de teclado/
    // resize atados a ese elemento) queda viva. Con muchas ventas por turno esto se
    // acumula y termina disparando 'hidden.bs.modal' más de una vez por un solo cierre,
    // lo que en pos.php hacía descartar dos veces la mesa siguiente (bug real reportado:
    // "se vacían las mesas al cobrar"). getOrCreateInstance reutiliza siempre la misma.
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
function _hideModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (typeof bootstrap !== 'undefined') { bootstrap.Modal.getInstance(el)?.hide(); return; }
    let btn = el.querySelector('[data-bs-dismiss="modal"]');
    if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button'; btn.hidden = true;
        btn.setAttribute('data-bs-dismiss', 'modal');
        el.appendChild(btn);
    }
    btn.click();
}

let carrito = [];  // [{producto_id, nombre, cantidad, precio_unitario, iva_porcentaje, descuento_pct, stock_actual}, ...]
let categoriaActivaId = null;

// ── Mesas abiertas (ventas cargadas pero no cobradas todavía) ──────────────────
// `carrito` de arriba es siempre el de la mesa ACTIVA; `mesasAbiertas` guarda el
// resto (aparcadas), y mesaActivaId identifica cuál es cuál. Ver pos-mesas.js.
let mesaActivaId  = null;
let mesasAbiertas = [];
// Id de la mesa que efectivamente se acaba de cobrar (seteado en mostrarModalVentaOk()/
// mostrarModalVentaOffline()) — puede NO coincidir con mesaActivaId al momento de cerrar
// el modal si el cajero cambió de mesa mientras el cobro se procesaba. Ver limpiarVenta().
let mesaIdPagada  = null;

// ── Estado de conexión (offline-first) ──────────────────────────────────────────
// navigator.onLine es solo orientativo (puede haber red local sin internet real);
// el fallback de verdad se decide en cada fetch según si responde OK o no.
// "let" (no "const"): actualizarEstadoAfip() los reescribe después de cada venta —
// ver más abajo. Si no se refrescaran, el widget y el checkbox quedarían con el valor
// del momento en que se cargó la página, ignorando lo facturado en la sesión.
let AFIP_BLOQUEADO_ADMIN = <?= $bloqueoFacturacionAfip ? 'true' : 'false' ?>;
let AFIP_LIMITE_AGOTADO  = <?= $limiteFacturacionAlcanzado ? 'true' : 'false' ?>;
// Para proyectar en vivo si ESTA venta llevaría el uso del día al 90% (y destildar sola el
// checkbox si así fuera) — null cuando no hay prorrateo diario configurado/vigente.
let AFIP_CUOTA_HOY   = <?= ($prorateoVigente && $cuotaHoyInicial) ? json_encode($cuotaHoyInicial) : 'null' ?>;
let AFIP_PCT_HOY_INI = <?= ($prorateoVigente && $pctUsadoHoy !== null) ? json_encode($pctUsadoHoy) : 'null' ?>;
document.getElementById('facturarAfip').addEventListener('change', function(){
    if (this.checked) document.getElementById('afipAviso90').classList.add('d-none');
});

// Se llama con el 'afipEstado' que devuelve POST /ventas/guardar tras CADA venta (ver
// mostrarModalVentaOk) — recalculado fresco en el servidor, así el widget "Podés
// facturar hoy", el checkbox y la barra del acumulado mensual reflejan lo que se
// facturó en lo que va de la sesión sin tener que recargar la página.
function actualizarEstadoAfip(estado){
    if (!estado) return;
    AFIP_BLOQUEADO_ADMIN = !!estado.bloqueoFacturacionAfip;
    AFIP_LIMITE_AGOTADO  = !!estado.limiteFacturacionAlcanzado;
    AFIP_CUOTA_HOY   = (estado.prorateoVigente && estado.cuotaHoyInicial) ? estado.cuotaHoyInicial : null;
    AFIP_PCT_HOY_INI = (estado.prorateoVigente && estado.pctUsadoHoy !== null) ? estado.pctUsadoHoy : null;

    const afipCk = document.getElementById('facturarAfip');
    afipCk.checked  = !!estado.facturarAfipPorDefecto;
    afipCk.disabled = AFIP_BLOQUEADO_ADMIN || AFIP_LIMITE_AGOTADO;
    document.getElementById('afipAviso90').classList.add('d-none');

    document.getElementById('afipAvisoBloqueado')?.classList.toggle('d-none', !AFIP_BLOQUEADO_ADMIN);
    document.getElementById('afipAvisoLimite')?.classList.toggle('d-none', AFIP_BLOQUEADO_ADMIN || !AFIP_LIMITE_AGOTADO);

    const box = document.getElementById('afipProrrateoBox');
    if (box) {
        const agotado = estado.disponibleHoy !== null && estado.disponibleHoy <= 0;
        box.classList.toggle('bg-danger-soft', agotado);
        box.classList.toggle('bg-light', !agotado);
        document.getElementById('afipDisponibleHoy').textContent = formatMoneda(estado.disponibleHoy);
        document.getElementById('afipDisponibleHoy').classList.toggle('text-danger', agotado);
        document.getElementById('afipDisponibleHoy').classList.toggle('text-success', !agotado);
        const facturadoHoyEl = document.getElementById('afipFacturadoHoy');
        if (facturadoHoyEl) facturadoHoyEl.textContent = formatMoneda(estado.facturadoHoy);
        document.getElementById('afipProrrateoMsg').textContent = agotado
            ? '⚠️ Ya alcanzaste el límite diario — "Facturar por AFIP" queda bloqueado hasta mañana.'
            : 'Incluye lo no facturado de días anteriores.';
    }

    const monoBox = document.getElementById('afipMonoBox');
    if (monoBox && estado.limiteMonoMes) {
        document.getElementById('afipMonoTexto').textContent =
            formatMoneda(estado.facturadoAfipMes) + ' / ' + formatMoneda(estado.limiteMonoMes) + ' (' + estado.pctMonoMes.toFixed(1) + '%)';
        const bar = document.getElementById('afipMonoBar');
        bar.style.width = estado.pctMonoMes + '%';
        bar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        bar.classList.add(estado.pctMonoMes >= 95 ? 'bg-danger' : (estado.pctMonoMes >= 80 ? 'bg-warning' : 'bg-success'));
    }
}

function actualizarEstadoConexion(){
    const badge = document.getElementById('posConexionBadge');
    const afipCk = document.getElementById('facturarAfip');
    if (navigator.onLine) {
        badge.className = 'badge bg-success-soft text-success';
        badge.textContent = '🟢 Conectado';
        afipCk.disabled = AFIP_BLOQUEADO_ADMIN || AFIP_LIMITE_AGOTADO; // el bloqueo del admin o el tope diario mandan por sobre el estado de conexión
    } else {
        badge.className = 'badge bg-warning-soft text-warning';
        badge.textContent = '🔴 Sin conexión';
        afipCk.checked = false;
        afipCk.disabled = true;
    }
}
window.addEventListener('online', actualizarEstadoConexion);
window.addEventListener('offline', actualizarEstadoConexion);

// ── Categorías ─────────────────────────────────────────────────────────────────
function renderCategoriasLista(data){
    const cont=document.getElementById('categoriasLista');
    cont.innerHTML='';
    if(!data.length){
        cont.innerHTML=`<div class="list-group-item text-muted small text-center py-3">Sin categorías</div>`;
        return;
    }
    data.forEach((c,i)=>{
        const btn=document.createElement('button');
        btn.type='button';
        // cat-color-1..8 cíclico: cada categoría se distingue por color sin depender de
        // que el backend mande uno (los colores están definidos en el <style> de acá abajo).
        const colorClase = 'cat-color-' + ((i % 8) + 1);
        btn.className=`list-group-item list-group-item-action py-2 small fw-semibold ${colorClase}`;
        btn.dataset.categoriaId=c.id;
        btn.innerHTML=`${esc(c.nombre)} <span class="badge bg-secondary-soft text-secondary float-end">${c.total_productos}</span>`;
        btn.addEventListener('click',()=>seleccionarCategoria(c.id, btn));
        cont.appendChild(btn);
    });
}

async function cargarCategoriasPos(){
    const cont=document.getElementById('categoriasLista');
    try{
        const r=await fetch(`${BASE_URL}/ventas/categorias-pos`);
        if(!r.ok) throw new Error('respuesta no ok');
        const data=await r.json();
        renderCategoriasLista(data);
        // Refresco silencioso del catálogo local, para tenerlo listo si se corta la conexión
        PosOffline.actualizarCatalogo(BASE_URL).catch(()=>{});
    }catch{
        try{
            const data = await PosOffline.categoriasLocal();
            if(!data.length){
                cont.innerHTML=`<div class="list-group-item text-danger small text-center py-3">Sin conexión y sin catálogo guardado localmente.</div>`;
                return;
            }
            renderCategoriasLista(data);
        }catch{
            cont.innerHTML=`<div class="list-group-item text-danger small text-center py-3">Error al cargar categorías</div>`;
        }
    }
}

async function seleccionarCategoria(categoriaId, btnEl){
    categoriaActivaId=categoriaId;
    document.getElementById('buscadorPos').value='';
    document.querySelectorAll('#categoriasLista .list-group-item').forEach(b=>b.classList.remove('active'));
    if(btnEl) btnEl.classList.add('active');
    mostrarCatalogo([], 'Cargando productos...');
    try{
        const r=await fetch(`${BASE_URL}/ventas/productos-por-categoria?categoria_id=${categoriaId}`);
        if(!r.ok) throw new Error('respuesta no ok');
        const data=await r.json();
        mostrarCatalogo(data, data.length===0?'Sin productos en esta categoría.':'');
    }catch{
        const data = await PosOffline.productosPorCategoriaLocal(categoriaId);
        mostrarCatalogo(data, data.length===0?'Sin productos en esta categoría (modo sin conexión).':'');
    }
}

// ── Búsqueda de productos ─────────────────────────────────────────────────────
let bTimeout;
document.getElementById('buscadorPos').addEventListener('input', function(){
    clearTimeout(bTimeout);
    const q=this.value.trim();
    if(!q){
        document.querySelectorAll('#categoriasLista .list-group-item').forEach(b=>b.classList.remove('active'));
        categoriaActivaId=null;
        mostrarCatalogo([],'Elija una categoría o busque un producto...');
        return;
    }
    bTimeout=setTimeout(()=>buscarProductos(q),200);
});

async function buscarProductos(q){
    document.querySelectorAll('#categoriasLista .list-group-item').forEach(b=>b.classList.remove('active'));
    categoriaActivaId=null;
    try{
        const r=await fetch(`${BASE_URL}/ventas/buscar-productos?q=${encodeURIComponent(q)}`);
        if(!r.ok) throw new Error('respuesta no ok');
        const data=await r.json();
        mostrarCatalogo(data, data.length===0?'Sin resultados.':'');
    }catch{
        const data = await PosOffline.buscarProductosLocal(q);
        mostrarCatalogo(data, data.length===0?'Sin resultados (modo sin conexión).':'');
    }
}

// Muestra los productos como botones grandes (grilla) a la derecha de las categorías.
// Cada click suma 1 unidad al producto en la venta (agregarAlCarrito).
function mostrarCatalogo(lista, emptyMsg=''){
    const cont=document.getElementById('catalogoResultados');
    cont.innerHTML='';
    if(!lista.length){
        cont.innerHTML=`<div class="text-muted small text-center py-4 w-100">${emptyMsg}</div>`;
        return;
    }
    lista.forEach((p,i)=>{
        // Productos sin control de stock (ej. café) siempre están disponibles
        const controlaStock = p.controla_stock === undefined ? true : !!(+p.controla_stock);
        const agotado = controlaStock && parseFloat(p.stock_actual)<=0;
        const item=document.createElement('button');
        item.type='button';
        // prod-color-1..8 cíclico (agotado se deja gris/apagado como antes, sin color).
        // Ya no se usa .disabled: un agotado se puede seguir vendiendo, solo que pide
        // confirmación antes (ver el listener de click más abajo). .disabled de Bootstrap
        // bloquea los clicks (pointer-events:none), por eso no puede seguir usándose acá.
        const colorClase = agotado ? '' : ' prod-color-' + ((i % 8) + 1);
        item.className='btn catalogo-item text-start p-2'+(agotado?' text-muted':'')+colorClase;
        item.style.width='30%';
        item.style.minHeight='90px';
        const stockLinea = controlaStock
            ? `<div class="small ${agotado?'text-danger':'text-success'}">Stock: ${parseFloat(p.stock_actual).toFixed(1)} ${esc(p.unidad||'')}</div>`
            : `<div class="small text-info">Sin control de stock</div>`;
        item.innerHTML=`
            <div class="fw-semibold small catalogo-item-nombre">${esc(p.nombre)}</div>
            <div class="fw-bold text-primary small">${formatMoneda(p.precio_venta)}</div>
            <div class="small text-muted">${esc(p.codigo||'')}</div>
            ${stockLinea}`;
        const alAgregar = () => { (+p.tiene_opciones) ? abrirModalOpciones(p) : agregarAlCarrito(p); };
        item.addEventListener('click', () => {
            if (agotado) {
                showConfirm(`"${p.nombre}" no tiene stock disponible. ¿Vender igual?`, alAgregar, {
                    label: 'Vender igual',
                    sub: 'Se va a registrar la venta sin stock disponible.',
                });
            } else {
                alAgregar();
            }
        });
        cont.appendChild(item);
    });
}

cargarCategoriasPos();
actualizarEstadoConexion();
actualizarContadorPendientes();

// ── Carrito ───────────────────────────────────────────────────────────────────
function agregarAlCarrito(p){
    const controlaStock = p.controla_stock === undefined ? true : !!(+p.controla_stock);
    const idx=carrito.findIndex(i=>i.producto_id===p.id);
    if(idx>=0){
        // El tope solo aplica si de verdad queda stock (>0): si ya está en 0 (venta sin
        // stock ya confirmada más arriba), Math.min(cantidad+1, 0) lo volvería a dejar en
        // 0 en cada click siguiente — acá no hay techo real que respetar.
        carrito[idx].cantidad = (controlaStock && parseFloat(p.stock_actual) > 0)
            ? Math.min(carrito[idx].cantidad+1, parseFloat(p.stock_actual))
            : carrito[idx].cantidad+1;
    } else {
        carrito.push({
            producto_id:     p.id,
            nombre:          p.nombre,
            cantidad:        1,
            precio_unitario: parseFloat(p.precio_venta),
            iva_porcentaje:  parseFloat(p.iva_porcentaje)||21,
            descuento_pct:   0,
            stock_actual:    parseFloat(p.stock_actual),
            controla_stock:  controlaStock,
        });
    }
    renderCarrito();
    // La grilla de productos se mantiene visible tras el click (en categoría o en búsqueda)
    // para poder seguir sumando el mismo producto: 3 clicks = 3 unidades en el carrito.
}

// ── Opciones/adicionales del producto (ver docs/tareas-pendientes.md #1) ───────────────
// Cada confirmación agrega una línea NUEVA al carrito (nunca se suma a una línea existente
// del mismo producto): dos líneas del mismo producto pueden traer selecciones distintas
// (ej. dos heladados 1kg, cada uno con gustos diferentes), así que no se pueden combinar
// en una sola cantidad como sí pasa con los productos sin opciones.
let _opcionesProductoActual = null;
let _opcionesGruposActuales = [];
let _opcionesSeleccion = {};   // grupo_id -> [{opcion_id, nombre, cantidad_elegida}]
let _opcionesIdxEdicion = null; // idx en carrito si se está reeditando una línea ya agregada

// ── Selección de sabores para promos (ver docs/promos-diseno.md): cada componente que
// bundlea la promo (ej. "1/4kg" x2 dentro de "PROMO: 2 x 1/4") trae su PROPIO selector,
// repetido una vez por unidad, para elegir el sabor de cada unidad por separado — no un
// pool combinado a repartir entre todas. _opcionesSeleccionPromo se indexa por
// "componenteId_unidadIdx_grupoId" (a diferencia de _opcionesSeleccion, que es por grupoId
// nomás, porque en un producto simple no hay "unidades" que distinguir).
let _opcionesEsPromo = false;
let _opcionesPromoComponentes = [];
let _opcionesSeleccionPromo = {};

async function abrirModalOpciones(p, idxEdicion = null) {
    _opcionesProductoActual = p;
    _opcionesIdxEdicion = idxEdicion;
    _opcionesSeleccion = {};
    _opcionesEsPromo = false;
    _opcionesPromoComponentes = [];
    _opcionesSeleccionPromo = {};
    document.getElementById('opcionesNombreProducto').textContent = p.nombre;
    document.getElementById('opcionesGruposWrap').innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    document.getElementById('opcionesError').classList.add('d-none');
    document.getElementById('buscadorOpciones').value = '';
    document.getElementById('opcionesConfirmStock').classList.add('d-none');
    _opcionStockCb = null;
    _showModal('modalOpciones');
    try {
        const r = await fetch(`${BASE_URL}/ventas/opciones-producto?producto_id=${p.id}`);
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Error');

        _opcionesEsPromo = !!d.es_promo;

        if (_opcionesEsPromo) {
            // Opciones asignadas DIRECTO a la promo (ej. "elegí 1 cookie" en "PROMO: Café +
            // cookie") — usa el mismo estado/funciones que un producto simple (_opcionesGruposActuales/
            // _opcionesSeleccion/seleccionarUnica/etc.), NO el mecanismo por-unidad de componente.
            _opcionesGruposActuales = (d.grupos_promo || []).map(g => ({
                ...g, id: +g.id, minimo_selecciones: +g.minimo_selecciones, maximo_selecciones: +g.maximo_selecciones,
                obligatorio: +g.obligatorio, permite_repetir: +g.permite_repetir,
                opciones: g.opciones.map(o => ({ ...o, id: +o.id })),
            }));
            if (idxEdicion !== null && carrito[idxEdicion].opciones) {
                carrito[idxEdicion].opciones.forEach(sel => {
                    const grupo = _opcionesGruposActuales.find(g => g.opciones.some(o => o.id === +sel.opcion_id));
                    if (!grupo) return;
                    _opcionesSeleccion[grupo.id] = _opcionesSeleccion[grupo.id] || [];
                    _opcionesSeleccion[grupo.id].push({ opcion_id: +sel.opcion_id, nombre: sel.nombre, cantidad_elegida: +sel.cantidad_elegida });
                });
            }

            _opcionesPromoComponentes = d.componentes.map(c => ({
                ...c,
                grupos: c.grupos.map(g => ({
                    ...g, id: +g.id, minimo_selecciones: +g.minimo_selecciones, maximo_selecciones: +g.maximo_selecciones,
                    obligatorio: +g.obligatorio, permite_repetir: +g.permite_repetir,
                    opciones: g.opciones.map(o => ({ ...o, id: +o.id })),
                })),
            }));
            // Reeditar: precargar lo que ya se había elegido para cada unidad de cada componente.
            if (idxEdicion !== null && carrito[idxEdicion].opciones_por_componente) {
                Object.entries(carrito[idxEdicion].opciones_por_componente).forEach(([compId, unidades]) => {
                    const comp = _opcionesPromoComponentes.find(c => c.producto_id === +compId);
                    if (!comp) return;
                    unidades.forEach((seleccionUnidad, unidadIdx) => {
                        seleccionUnidad.forEach(sel => {
                            const grupo = comp.grupos.find(g => g.opciones.some(o => o.id === +sel.opcion_id));
                            if (!grupo) return;
                            const key = `${compId}_${unidadIdx}_${grupo.id}`;
                            _opcionesSeleccionPromo[key] = _opcionesSeleccionPromo[key] || [];
                            _opcionesSeleccionPromo[key].push({ opcion_id: +sel.opcion_id, nombre: sel.nombre, cantidad_elegida: +sel.cantidad_elegida });
                        });
                    });
                });
            }
            renderComponentesPromo();
            return;
        }

        _opcionesGruposActuales = d.grupos.map(g => ({
            ...g, id: +g.id, minimo_selecciones: +g.minimo_selecciones, maximo_selecciones: +g.maximo_selecciones,
            obligatorio: +g.obligatorio, permite_repetir: +g.permite_repetir,
            opciones: g.opciones.map(o => ({ ...o, id: +o.id })),
        }));
        // Si se está reeditando una línea ya agregada, precargar lo que tenía elegido.
        if (idxEdicion !== null && carrito[idxEdicion].opciones) {
            carrito[idxEdicion].opciones.forEach(sel => {
                const grupo = _opcionesGruposActuales.find(g => g.opciones.some(o => o.id === +sel.opcion_id));
                if (!grupo) return;
                _opcionesSeleccion[grupo.id] = _opcionesSeleccion[grupo.id] || [];
                _opcionesSeleccion[grupo.id].push({ opcion_id: +sel.opcion_id, nombre: sel.nombre, cantidad_elegida: +sel.cantidad_elegida });
            });
        }
        renderGruposOpciones();
    } catch {
        document.getElementById('opcionesGruposWrap').innerHTML = '<div class="text-danger small text-center py-3">No se pudieron cargar las opciones.</div>';
    }
}

/** Mismo criterio que un producto agotado del catálogo (ver mostrarCatalogo()): si la
 *  opción no tiene stock, pide confirmación antes de ejecutar la acción (elegirla/sumarle
 *  cantidad); si tiene stock, la ejecuta directo. */
let _opcionStockCb = null;

function _elegirOpcionConStock(agotado, nombre, ejecutar) {
    if (agotado) {
        document.getElementById('opcionesConfirmStockMsg').textContent = `"${nombre}" no tiene stock disponible. ¿Elegir igual?`;
        _opcionStockCb = ejecutar;
        document.getElementById('opcionesConfirmStock').classList.remove('d-none');
    } else {
        ejecutar();
    }
}
function _confirmarOpcionStock() {
    document.getElementById('opcionesConfirmStock').classList.add('d-none');
    if (_opcionStockCb) { _opcionStockCb(); _opcionStockCb = null; }
}
function _cancelarOpcionStock() {
    document.getElementById('opcionesConfirmStock').classList.add('d-none');
    _opcionStockCb = null;
}

function renderGruposOpciones() {
    const wrap = document.getElementById('opcionesGruposWrap');
    wrap.innerHTML = _opcionesGruposActuales.map(g => {
        _opcionesSeleccion[g.id] = _opcionesSeleccion[g.id] || [];
        const tipoTexto = g.tipo_seleccion === 'unica' ? 'Elegí 1' : `Elegí entre ${g.minimo_selecciones} y ${g.maximo_selecciones}`;
        const tarjetas = g.opciones.map((o, i) => {
            // Mismo estilo "cuadrado" que el catálogo de productos: color pastel cíclico
            // (prod-color-1..8) y estado elegido resaltado — ver CSS #opcionesGruposWrap
            // más abajo, que reusa las mismas reglas de #catalogoResultados. Una opción
            // sin materia_prima_id/insumo_id vinculado (ej. "sin azúcar") llega con
            // stock_actual=null y nunca se considera agotada — no hay stock que chequear.
            const tieneStock = o.stock_actual !== null && o.stock_actual !== undefined;
            const agotado    = tieneStock && parseFloat(o.stock_actual) <= 0;
            const colorClase = agotado ? '' : 'prod-color-' + ((i % 8) + 1);
            const nombreAttr = esc(o.nombre).toLowerCase();
            // Siempre informa el stock de la MP/insumo vinculado (si tiene uno vinculado):
            // rojo en 0 (o negativo), verde si hay — mismo criterio que el catálogo de
            // productos. Las opciones sin nada vinculado (ej. "sin azúcar") no muestran nada.
            const stockLinea = tieneStock
                ? `<div class="small ${agotado ? 'text-danger' : 'text-success'}">Stock: ${parseFloat(o.stock_actual).toFixed(1)}${o.unidad ? ' ' + esc(o.unidad) : ''}</div>`
                : '';

            if (g.tipo_seleccion === 'unica') {
                const elegida = _opcionesSeleccion[g.id].some(s => s.opcion_id === o.id);
                const accion  = `seleccionarUnica(${g.id},${o.id},'${escJs(o.nombre)}')`;
                return `<button type="button" data-nombre="${nombreAttr}"
                        class="btn catalogo-item text-start p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                        style="width:23%;min-height:70px"
                        onclick="_elegirOpcionConStock(${agotado}, '${escJs(o.nombre)}', () => ${accion})">
                    <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                    ${elegida ? '<div class="small">✓ Elegido</div>' : ''}
                    ${stockLinea}
                </button>`;
            }

            const sel  = _opcionesSeleccion[g.id].find(s => s.opcion_id === o.id);
            const cant = sel ? sel.cantidad_elegida : 0;

            if (g.permite_repetir) {
                const accionMas = `cambiarCantidadOpcion(${g.id},${o.id},'${escJs(o.nombre)}',1)`;
                return `<div data-nombre="${nombreAttr}"
                        class="catalogo-item p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${cant > 0 ? ' opcion-elegida' : ''}" style="width:23%;min-height:70px">
                    <div class="fw-semibold small catalogo-item-nombre mb-1">${esc(o.nombre)}</div>
                    ${stockLinea}
                    <div class="d-flex align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="cambiarCantidadOpcion(${g.id},${o.id},'${escJs(o.nombre)}',-1)">−</button>
                        <span class="fw-bold small">${cant}</span>
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="_elegirOpcionConStock(${agotado}, '${escJs(o.nombre)}', () => ${accionMas})">+</button>
                    </div>
                </div>`;
            }

            const elegida = cant > 0;
            const accion  = `toggleMultiple(${g.id},${o.id},'${escJs(o.nombre)}',${!elegida})`;
            // Solo hay que avisar al SELECCIONAR (pasar de no-elegida a elegida) — sacar una
            // opción ya elegida nunca necesita chequeo de stock, por eso "agotado && !elegida".
            return `<button type="button" data-nombre="${nombreAttr}"
                    class="btn catalogo-item text-start p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                    style="width:23%;min-height:70px"
                    onclick="_elegirOpcionConStock(${agotado && !elegida}, '${escJs(o.nombre)}', () => ${accion})">
                <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                ${elegida ? '<div class="small">✓ Elegido</div>' : ''}
                ${stockLinea}
            </button>`;
        }).join('');
        return `<div class="mb-3" data-grupo-wrap="${g.id}">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">${esc(g.nombre)} ${g.obligatorio ? '' : '<span class="text-muted small fw-normal">(opcional)</span>'}</h6>
                <span class="small text-muted">${tipoTexto}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">${tarjetas}</div>
        </div>`;
    }).join('');
    _filtrarOpciones(document.getElementById('buscadorOpciones').value);
}

/** Filtro client-side (ya está todo cargado, no hace falta ir al servidor): oculta las
 *  tarjetas que no matchean y, si un grupo entero queda sin ninguna visible, oculta
 *  también su encabezado — para no dejar un título "Gustos de helado" sin nada debajo. */
function _filtrarOpciones(q) {
    const texto = (q || '').trim().toLowerCase();
    document.querySelectorAll('#opcionesGruposWrap [data-grupo-wrap]').forEach(grupoEl => {
        let algunaVisible = false;
        grupoEl.querySelectorAll('[data-nombre]').forEach(card => {
            const visible = !texto || card.dataset.nombre.includes(texto);
            card.classList.toggle('d-none', !visible);
            if (visible) algunaVisible = true;
        });
        grupoEl.classList.toggle('d-none', !algunaVisible);
    });
}

/** Dentro del modal de una promo, el bloque "propio de la promo" vive en el sidebar de
 *  unidades (ver renderComponentesPromo()) — hay que refrescarlo entero para que el ✓ de
 *  esa tarjeta se actualice, no solo el panel de opciones. Fuera de una promo, es
 *  simplemente renderGruposOpciones() como siempre. */
function _refrescarGruposOpciones() {
    if (_opcionesEsPromo) renderComponentesPromo();
    else renderGruposOpciones();
}

function seleccionarUnica(grupoId, opcionId, nombre) {
    _opcionesSeleccion[grupoId] = [{ opcion_id: opcionId, nombre, cantidad_elegida: 1 }];
    _refrescarGruposOpciones();
}
function toggleMultiple(grupoId, opcionId, nombre, checked) {
    const grupo = _opcionesGruposActuales.find(g => g.id === grupoId);
    let lista = _opcionesSeleccion[grupoId] || [];
    if (checked) {
        if (lista.length >= grupo.maximo_selecciones) {
            showToast(`"${grupo.nombre}": como máximo podés elegir ${grupo.maximo_selecciones}.`, 'warning');
            _refrescarGruposOpciones();
            return;
        }
        lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else {
        lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opcionesSeleccion[grupoId] = lista;
    _refrescarGruposOpciones();
}
function cambiarCantidadOpcion(grupoId, opcionId, nombre, delta) {
    const grupo = _opcionesGruposActuales.find(g => g.id === grupoId);
    let lista = _opcionesSeleccion[grupoId] || [];
    const totalActual = lista.reduce((s, x) => s + x.cantidad_elegida, 0);
    let sel = lista.find(s => s.opcion_id === opcionId);
    if (delta > 0) {
        if (totalActual >= grupo.maximo_selecciones) {
            showToast(`"${grupo.nombre}": como máximo podés elegir ${grupo.maximo_selecciones} en total.`, 'warning');
            return;
        }
        if (sel) sel.cantidad_elegida++;
        else lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else if (sel) {
        sel.cantidad_elegida--;
        if (sel.cantidad_elegida <= 0) lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opcionesSeleccion[grupoId] = lista;
    _refrescarGruposOpciones();
}

// ── Render y selección de opciones para PROMOS (una unidad por vez) ─────────────────────
// Espeja seleccionarUnica/toggleMultiple/cambiarCantidadOpcion, pero indexado por
// "componenteId_unidadIdx_grupoId" en vez de solo grupoId, para que cada unidad del combo
// tenga su propia selección independiente. La UI es un sidebar con una tarjeta por unidad
// (foto y/o nombre del componente) + el selector de esa unidad nomás en el panel principal
// — evita mostrar todas las unidades apiladas de una (una promo de 2-3 unidades con 30+
// sabores cada una se vuelve un scroll interminable si se muestran todas juntas).
let _opcionesPromoUnidades = [];   // lista plana: [{tipo:'promo'} | {tipo:'componente', comp, unidadIdx}, ...]
let _opcionesPromoUnidadActiva = 0;

/** Cuántas opciones ya tiene elegidas una unidad (para el check ✓ en el sidebar). */
function _unidadTieneSeleccion(comp, unidadIdx) {
    return comp.grupos.some(g => (_opcionesSeleccionPromo[`${comp.producto_id}_${unidadIdx}_${g.id}`] || []).length > 0);
}

function renderComponentesPromo() {
    _opcionesPromoUnidades = [];
    // Opciones propias de la promo (ej. "elegí 1 cookie"): una sola tarjeta, no por unidad —
    // usa _opcionesGruposActuales/_opcionesSeleccion, igual que un producto simple.
    if (_opcionesGruposActuales.length) {
        _opcionesPromoUnidades.push({ tipo: 'promo' });
    }
    _opcionesPromoComponentes.forEach(comp => {
        for (let unidadIdx = 0; unidadIdx < comp.unidades; unidadIdx++) {
            _opcionesPromoUnidades.push({ tipo: 'componente', comp, unidadIdx });
        }
    });
    if (_opcionesPromoUnidadActiva >= _opcionesPromoUnidades.length) _opcionesPromoUnidadActiva = 0;

    const sidebar = document.getElementById('opcionesPromoSidebar');
    sidebar.classList.toggle('d-none', _opcionesPromoUnidades.length <= 1);
    sidebar.innerHTML = _opcionesPromoUnidades.map((u, idx) => {
        const activa = idx === _opcionesPromoUnidadActiva;
        let nombre, completa, imagenHtml = '', subtitulo = '';

        if (u.tipo === 'promo') {
            nombre    = _opcionesGruposActuales.map(g => g.nombre).join(' + ');
            completa  = _opcionesGruposActuales.every(g => (_opcionesSeleccion[g.id] || []).length > 0);
        } else {
            const { comp, unidadIdx } = u;
            nombre    = comp.nombre;
            completa  = _unidadTieneSeleccion(comp, unidadIdx);
            imagenHtml = comp.imagen
                ? `<img src="${esc(comp.imagen)}" alt="" style="width:100%;height:60px;object-fit:cover;border-radius:.25rem" class="mb-1">`
                : '';
            subtitulo = comp.unidades > 1 ? `<div class="small text-muted">Unidad ${unidadIdx + 1} de ${comp.unidades}</div>` : '';
        }

        return `<div class="card border-0 mb-2 ${activa ? 'border border-primary' : ''}" style="cursor:pointer" onclick="_irAUnidadPromo(${idx})">
            <div class="card-body p-2 ${activa ? 'bg-primary-soft' : ''}">
                ${imagenHtml}
                <div class="small fw-semibold d-flex align-items-center justify-content-between">
                    <span>${esc(nombre)}</span>
                    ${completa ? '<span class="text-success">✓</span>' : ''}
                </div>
                ${subtitulo}
            </div>
        </div>`;
    }).join('');

    renderUnidadActivaPromo();
}

function _irAUnidadPromo(idx) {
    _opcionesPromoUnidadActiva = idx;
    renderComponentesPromo();
}

function renderUnidadActivaPromo() {
    const wrap = document.getElementById('opcionesGruposWrap');
    const actual = _opcionesPromoUnidades[_opcionesPromoUnidadActiva];
    if (!actual) { wrap.innerHTML = ''; return; }
    if (actual.tipo === 'promo') {
        // Opciones propias de la promo: mismo render que un producto simple, reusando
        // _opcionesGruposActuales/_opcionesSeleccion (seleccionarUnica/toggleMultiple/
        // cambiarCantidadOpcion ya actualizan esas mismas variables).
        renderGruposOpciones();
        return;
    }
    const { comp, unidadIdx } = actual;

    const gruposHtml = comp.grupos.map(g => {
        const key = `${comp.producto_id}_${unidadIdx}_${g.id}`;
        _opcionesSeleccionPromo[key] = _opcionesSeleccionPromo[key] || [];
        const tipoTexto = g.tipo_seleccion === 'unica' ? 'Elegí 1' : `Elegí entre ${g.minimo_selecciones} y ${g.maximo_selecciones}`;
        const tarjetas = g.opciones.map((o, i) => {
            const tieneStock = o.stock_actual !== null && o.stock_actual !== undefined;
            const agotado    = tieneStock && parseFloat(o.stock_actual) <= 0;
            const colorClase = agotado ? '' : 'prod-color-' + ((i % 8) + 1);
            const nombreAttr = esc(o.nombre).toLowerCase();
            const stockLinea = tieneStock
                ? `<div class="small ${agotado ? 'text-danger' : 'text-success'}">Stock: ${parseFloat(o.stock_actual).toFixed(1)}${o.unidad ? ' ' + esc(o.unidad) : ''}</div>`
                : '';

            if (g.tipo_seleccion === 'unica') {
                const elegida = _opcionesSeleccionPromo[key].some(s => s.opcion_id === o.id);
                const accion  = `seleccionarUnicaPromo(${comp.producto_id},${unidadIdx},${g.id},${o.id},'${escJs(o.nombre)}')`;
                return `<button type="button" data-nombre="${nombreAttr}"
                        class="btn catalogo-item text-start p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                        style="width:23%;min-height:70px"
                        onclick="_elegirOpcionConStock(${agotado}, '${escJs(o.nombre)}', () => ${accion})">
                    <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                    ${elegida ? '<div class="small">✓ Elegido</div>' : ''}
                    ${stockLinea}
                </button>`;
            }

            const sel  = _opcionesSeleccionPromo[key].find(s => s.opcion_id === o.id);
            const cant = sel ? sel.cantidad_elegida : 0;

            if (g.permite_repetir) {
                const accionMas = `cambiarCantidadOpcionPromo(${comp.producto_id},${unidadIdx},${g.id},${o.id},'${escJs(o.nombre)}',1)`;
                return `<div data-nombre="${nombreAttr}"
                        class="catalogo-item p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${cant > 0 ? ' opcion-elegida' : ''}" style="width:23%;min-height:70px">
                    <div class="fw-semibold small catalogo-item-nombre mb-1">${esc(o.nombre)}</div>
                    ${stockLinea}
                    <div class="d-flex align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="cambiarCantidadOpcionPromo(${comp.producto_id},${unidadIdx},${g.id},${o.id},'${escJs(o.nombre)}',-1)">−</button>
                        <span class="fw-bold small">${cant}</span>
                        <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="_elegirOpcionConStock(${agotado}, '${escJs(o.nombre)}', () => ${accionMas})">+</button>
                    </div>
                </div>`;
            }

            const elegida = cant > 0;
            const accion  = `toggleMultiplePromo(${comp.producto_id},${unidadIdx},${g.id},${o.id},'${escJs(o.nombre)}',${!elegida})`;
            return `<button type="button" data-nombre="${nombreAttr}"
                    class="btn catalogo-item text-start p-2 ${colorClase}${agotado ? ' text-muted opcion-agotada' : ''}${elegida ? ' opcion-elegida' : ''}"
                    style="width:23%;min-height:70px"
                    onclick="_elegirOpcionConStock(${agotado && !elegida}, '${escJs(o.nombre)}', () => ${accion})">
                <div class="fw-semibold small catalogo-item-nombre">${esc(o.nombre)}</div>
                ${elegida ? '<div class="small">✓ Elegido</div>' : ''}
                ${stockLinea}
            </button>`;
        }).join('');
        return `<div class="mb-2" data-grupo-wrap="${key}">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-semibold mb-0">${esc(g.nombre)} ${g.obligatorio ? '' : '<span class="text-muted small fw-normal">(opcional)</span>'}</h6>
                <span class="small text-muted">${tipoTexto}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">${tarjetas}</div>
        </div>`;
    }).join('');

    wrap.innerHTML = `<div class="small fw-bold text-uppercase text-muted mb-2">${esc(comp.nombre)}${comp.unidades > 1 ? ` — Unidad ${unidadIdx + 1} de ${comp.unidades}` : ''}</div>${gruposHtml}`;
    _filtrarOpciones(document.getElementById('buscadorOpciones').value);
}

function seleccionarUnicaPromo(compId, unidadIdx, grupoId, opcionId, nombre) {
    const key = `${compId}_${unidadIdx}_${grupoId}`;
    _opcionesSeleccionPromo[key] = [{ opcion_id: opcionId, nombre, cantidad_elegida: 1 }];
    renderComponentesPromo();
}
function toggleMultiplePromo(compId, unidadIdx, grupoId, opcionId, nombre, checked) {
    const key = `${compId}_${unidadIdx}_${grupoId}`;
    const comp = _opcionesPromoComponentes.find(c => c.producto_id === compId);
    const grupo = comp.grupos.find(g => g.id === grupoId);
    let lista = _opcionesSeleccionPromo[key] || [];
    if (checked) {
        if (lista.length >= grupo.maximo_selecciones) {
            showToast(`"${grupo.nombre}": como máximo podés elegir ${grupo.maximo_selecciones}.`, 'warning');
            renderComponentesPromo();
            return;
        }
        lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else {
        lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opcionesSeleccionPromo[key] = lista;
    renderComponentesPromo();
}
function cambiarCantidadOpcionPromo(compId, unidadIdx, grupoId, opcionId, nombre, delta) {
    const key = `${compId}_${unidadIdx}_${grupoId}`;
    const comp = _opcionesPromoComponentes.find(c => c.producto_id === compId);
    const grupo = comp.grupos.find(g => g.id === grupoId);
    let lista = _opcionesSeleccionPromo[key] || [];
    const totalActual = lista.reduce((s, x) => s + x.cantidad_elegida, 0);
    let sel = lista.find(s => s.opcion_id === opcionId);
    if (delta > 0) {
        if (totalActual >= grupo.maximo_selecciones) {
            showToast(`"${grupo.nombre}": como máximo podés elegir ${grupo.maximo_selecciones} en total.`, 'warning');
            return;
        }
        if (sel) sel.cantidad_elegida++;
        else lista.push({ opcion_id: opcionId, nombre, cantidad_elegida: 1 });
    } else if (sel) {
        sel.cantidad_elegida--;
        if (sel.cantidad_elegida <= 0) lista = lista.filter(s => s.opcion_id !== opcionId);
    }
    _opcionesSeleccionPromo[key] = lista;
    renderComponentesPromo();
}

/** Resumen legible de una promo ya configurada, ej. "1/4kg U1: Chocolate — 1/4kg U2: Vainilla, Dulce de leche". */
function _resumenOpcionesPromo(opcionesPorComponente) {
    const partes = [];
    Object.entries(opcionesPorComponente).forEach(([compId, unidades]) => {
        const comp = _opcionesPromoComponentes.find(c => c.producto_id === +compId);
        const nombreComp = comp ? comp.nombre : '';
        unidades.forEach((seleccionUnidad, i) => {
            if (!seleccionUnidad.length) return;
            partes.push(`${nombreComp} U${i + 1}: ${_resumenOpciones(seleccionUnidad)}`);
        });
    });
    return partes.join(' — ');
}

function confirmarOpciones() {
    if (_opcionesEsPromo) {
        // Opciones propias de la promo (ej. "elegí 1 cookie") — mismas reglas de
        // min/max/obligatorio que un producto simple, sobre _opcionesGruposActuales.
        for (const g of _opcionesGruposActuales) {
            const elegidos = (_opcionesSeleccion[g.id] || []).reduce((s, x) => s + x.cantidad_elegida, 0);
            if (g.obligatorio && elegidos < g.minimo_selecciones) {
                mostrarErrorOpciones(`"${g.nombre}": elegí al menos ${g.minimo_selecciones}.`);
                return;
            }
            if (elegidos > g.maximo_selecciones) {
                mostrarErrorOpciones(`"${g.nombre}": como máximo ${g.maximo_selecciones}.`);
                return;
            }
        }
        const opciones = [];
        Object.values(_opcionesSeleccion).forEach(lista => lista.forEach(s => opciones.push({ ...s })));

        const opcionesPorComponente = {};
        for (const comp of _opcionesPromoComponentes) {
            const unidades = [];
            for (let unidadIdx = 0; unidadIdx < comp.unidades; unidadIdx++) {
                for (const g of comp.grupos) {
                    const key = `${comp.producto_id}_${unidadIdx}_${g.id}`;
                    const elegidos = (_opcionesSeleccionPromo[key] || []).reduce((s, x) => s + x.cantidad_elegida, 0);
                    if (g.obligatorio && elegidos < g.minimo_selecciones) {
                        mostrarErrorOpciones(`"${comp.nombre}" — Unidad ${unidadIdx + 1}, "${g.nombre}": elegí al menos ${g.minimo_selecciones}.`);
                        return;
                    }
                    if (elegidos > g.maximo_selecciones) {
                        mostrarErrorOpciones(`"${comp.nombre}" — Unidad ${unidadIdx + 1}, "${g.nombre}": como máximo ${g.maximo_selecciones}.`);
                        return;
                    }
                }
                const seleccionUnidad = [];
                comp.grupos.forEach(g => {
                    const key = `${comp.producto_id}_${unidadIdx}_${g.id}`;
                    (_opcionesSeleccionPromo[key] || []).forEach(s => seleccionUnidad.push({ ...s }));
                });
                unidades.push(seleccionUnidad);
            }
            opcionesPorComponente[comp.producto_id] = unidades;
        }

        const resumen = [_resumenOpciones(opciones), _resumenOpcionesPromo(opcionesPorComponente)].filter(Boolean).join(' — ');

        if (_opcionesIdxEdicion !== null) {
            carrito[_opcionesIdxEdicion].opciones = opciones;
            carrito[_opcionesIdxEdicion].opciones_por_componente = opcionesPorComponente;
            carrito[_opcionesIdxEdicion].opciones_resumen = resumen;
            renderCarrito();
        } else {
            agregarAlCarritoConOpciones(_opcionesProductoActual, opciones, opcionesPorComponente);
        }
        _hideModal('modalOpciones');
        return;
    }

    for (const g of _opcionesGruposActuales) {
        const elegidos = (_opcionesSeleccion[g.id] || []).reduce((s, x) => s + x.cantidad_elegida, 0);
        if (g.obligatorio && elegidos < g.minimo_selecciones) {
            mostrarErrorOpciones(`"${g.nombre}": elegí al menos ${g.minimo_selecciones}.`);
            return;
        }
        if (elegidos > g.maximo_selecciones) {
            mostrarErrorOpciones(`"${g.nombre}": como máximo ${g.maximo_selecciones}.`);
            return;
        }
    }
    const opciones = [];
    Object.values(_opcionesSeleccion).forEach(lista => lista.forEach(s => opciones.push({ ...s })));

    if (_opcionesIdxEdicion !== null) {
        carrito[_opcionesIdxEdicion].opciones = opciones;
        carrito[_opcionesIdxEdicion].opciones_resumen = _resumenOpciones(opciones);
        renderCarrito();
    } else {
        agregarAlCarritoConOpciones(_opcionesProductoActual, opciones);
    }
    _hideModal('modalOpciones');
}
function mostrarErrorOpciones(msg) {
    const el = document.getElementById('opcionesError');
    el.textContent = msg;
    el.classList.remove('d-none');
}
function _resumenOpciones(opciones) {
    return opciones.map(o => (o.cantidad_elegida > 1 ? `${o.cantidad_elegida}x ` : '') + o.nombre).join(', ');
}
function escJs(s) { return (s || '').toString().replace(/'/g, "\\'"); }

function agregarAlCarritoConOpciones(p, opciones, opcionesPorComponente = null) {
    const controlaStock = p.controla_stock === undefined ? true : !!(+p.controla_stock);
    const item = {
        producto_id:      p.id,
        nombre:           p.nombre,
        cantidad:         1,
        precio_unitario:  parseFloat(p.precio_venta),
        iva_porcentaje:   parseFloat(p.iva_porcentaje)||21,
        descuento_pct:    0,
        stock_actual:     parseFloat(p.stock_actual),
        controla_stock:   controlaStock,
        opciones:         opciones,
        opciones_resumen: opcionesPorComponente
            ? [_resumenOpciones(opciones), _resumenOpcionesPromo(opcionesPorComponente)].filter(Boolean).join(' — ')
            : _resumenOpciones(opciones),
    };
    if (opcionesPorComponente) item.opciones_por_componente = opcionesPorComponente;
    carrito.push(item);
    renderCarrito();
}

function renderCarrito(){
    const tbody=document.getElementById('carritoBody');
    const rowV=document.getElementById('carritoVacio');
    tbody.innerHTML='';
    if (typeof _dbgPos === 'function') _dbgPos('renderCarrito()', { carritoLen: carrito.length, mesaActivaId });
    if(!carrito.length){rowV.style.display='';actualizarTotales();sincronizar();persistirMesas();return;}
    rowV.style.display='none';

    carrito.forEach((it,idx)=>{
        const sub=it.cantidad*it.precio_unitario*(1-it.descuento_pct/100);
        const tieneOpciones = Array.isArray(it.opciones) || !!it.opciones_por_componente;
        const resumenHtml = (tieneOpciones && it.opciones_resumen)
            ? `<div class="small text-muted">${esc(it.opciones_resumen)}</div>` : '';
        // Líneas precargadas desde un pedido del portal quedan sin editar/quitar (tiene que
        // ser exactamente lo que pidió el cliente — decisión confirmada, ver
        // docs/portal-clientes-mayoristas-checklist.md).
        const acciones = it.desde_pedido
            ? `<span class="badge bg-secondary-soft text-secondary" title="Viene de un pedido del portal">🔒 Pedido</span>`
            : `${tieneOpciones ? `<button type="button" class="btn btn-sm btn-link text-secondary p-0 me-2" title="Opciones" onclick="abrirModalOpciones({id:${it.producto_id},nombre:'${escJs(it.nombre)}',precio_venta:${it.precio_unitario},iva_porcentaje:${it.iva_porcentaje},stock_actual:${it.stock_actual},controla_stock:${it.controla_stock?1:0}},${idx})">🔧</button>` : ''}
               <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2" title="Editar" onclick="abrirEditorItem(${idx})">✎</button>
               <button type="button" class="btn btn-sm btn-link text-danger p-0" title="Quitar" onclick="quitarDelCarrito(${idx})">✕</button>`;
        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td class="small fw-semibold">${esc(it.nombre)}${resumenHtml}</td>
            <td><input type="number" class="form-control form-control-sm pos-input-ro" readonly tabindex="-1"
                value="${it.cantidad}"></td>
            <td><input type="number" class="form-control form-control-sm pos-input-ro" readonly tabindex="-1"
                value="${it.precio_unitario}"></td>
            <td><input type="number" class="form-control form-control-sm pos-input-ro" readonly tabindex="-1"
                value="${it.descuento_pct}"></td>
            <td class="text-end fw-semibold small">${formatMoneda(sub)}</td>
            <td class="text-nowrap">${acciones}</td>`;
        tbody.appendChild(tr);
    });
    actualizarTotales(); sincronizar(); persistirMesas();
}

function quitarDelCarrito(idx){carrito.splice(idx,1);renderCarrito();}
function limpiarCarrito(){
    showConfirm('¿Limpiar el carrito?', () => { carrito=[]; renderCarrito(); }, {
        label: 'Limpiar',
        sub: 'Se van a quitar todos los productos cargados.',
    });
}

// ── Mesas abiertas: pausar una venta en curso y seguir vendiendo otra ──────────
// Un objeto "mesa" es una foto completa de todo lo que hoy se ve en el panel de
// cobro (carrito, cliente, descuento, notas, medios de pago, AFIP). Cambiar de
// mesa = guardar la foto de la activa + volcar la foto de la otra a los inputs.
function esMesaVacia(tab){
    return (!tab.carrito || !tab.carrito.length) && !tab.mesa && !tab.cliente_id && !tab.notas;
}

function snapshotMesaActiva(){
    return {
        id: mesaActivaId,
        mesa: document.getElementById('mesaInput').value,
        carrito: carrito,
        cliente_id: clienteIdInput.value,
        clienteTexto: clienteBuscador.value,
        descuento_global: document.getElementById('descGlobal').value,
        notas: document.querySelector('#formVenta [name="notas"]').value,
        montos: {
            efectivo:      montoInputs.efectivo.value,
            debito:        montoInputs.debito.value,
            credito:       montoInputs.credito.value,
            transferencia: montoInputs.transferencia.value,
            mercadopago:   montoInputs.mercadopago.value,
        },
        mediosActivos: mediosActivos(),
        pagoDividioManual: pagoDividioManual,
        montoRecibido: montoRecibido.value,
        facturar_afip: document.getElementById('facturarAfip').checked,
    };
}

function restaurarMesa(tab){
    // BUG REAL CORREGIDO (causa raíz de "cierro el ticket y no se vacía el carrito"):
    // si `tab.id` venía en null/undefined (una mesa guardada en localStorage con el id
    // corrupto — confirmado en producción vía _dbgPos: "mostrarModalVentaOk" mostraba
    // mesaActivaId: null), mesaActivaId quedaba en null para SIEMPRE, porque cada
    // renderCarrito() vuelve a persistir {activeId: mesaActivaId, ...} tal cual, así que
    // la próxima carga restauraba el mismo null de nuevo — se auto-perpetuaba. Con
    // mesaActivaId en null, limpiarVenta() corta en su primer "if (mesaIdPagada ===
    // null) return" (mesaIdPagada = mesaActivaId, o sea también null) y nunca llega a
    // vaciar el carrito visible — el modal se cierra pero la tabla de productos queda
    // intacta. `|| PosMesas.generarId()` genera un id válido la primera vez que se
    // detecta el problema, y como restaurarMesa() llama a renderCarrito() más abajo,
    // ese id corregido se persiste solo en el siguiente guardado — autorepara el
    // localStorage sin que el usuario tenga que hacer nada.
    mesaActivaId = tab.id || PosMesas.generarId();
    carrito = tab.carrito || [];
    document.getElementById('mesaInput').value = tab.mesa || '';
    clienteIdInput.value = tab.cliente_id || '';
    clienteBuscador.value = tab.clienteTexto || '';
    btnLimpiarCliente.style.display = tab.cliente_id ? '' : 'none';
    document.getElementById('descGlobal').value = tab.descuento_global || 0;
    document.querySelector('#formVenta [name="notas"]').value = tab.notas || '';
    const m = tab.montos || {};
    montoInputs.efectivo.value      = m.efectivo      ?? 0;
    montoInputs.debito.value        = m.debito        ?? 0;
    montoInputs.credito.value       = m.credito       ?? 0;
    montoInputs.transferencia.value = m.transferencia ?? 0;
    montoInputs.mercadopago.value   = m.mercadopago   ?? 0;
    const activos = tab.mediosActivos && tab.mediosActivos.length ? tab.mediosActivos : ['efectivo'];
    Object.entries(medioChks).forEach(([medio, chk]) => {
        chk.checked = activos.includes(medio);
        montoInputs[medio].disabled = !chk.checked;
    });
    // Si la mesa guardada tenía más de un medio tildado, se restaura con "Dividir pago"
    // ya prendido (si no, el modo radio de medioChkCambio() destildaría todos menos uno
    // apenas el cajero tocara cualquier checkbox).
    chkDividirPago.checked = activos.length > 1;
    pagoDividioManual = !!tab.pagoDividioManual;
    montoRecibido.value = tab.montoRecibido || '';
    // Una mesa vacía (sin carrito/cliente/notas) no es una venta en curso real — se guarda
    // igual por el beforeunload de persistirMesas(), pero no debe arrastrar un
    // facturar_afip viejo por encima del default recién calculado por el servidor (si no,
    // el toggle queda pegado en lo último que se guardó, aunque ya cambie el % de uso del
    // día o el prorrateo). Con carrito real sí se respeta lo que el cajero eligió.
    if (!esMesaVacia(tab)) {
        const afipCk = document.getElementById('facturarAfip');
        afipCk.checked = !!tab.facturar_afip && !afipCk.disabled;
    }
    renderCarrito();
    actualizarResumenPago();
    calcularVuelto();
}

// Deja la mesa activa en blanco (para "Nueva mesa" y para cuando no queda
// ninguna mesa aparcada a la que volver tras cobrar/cerrar la activa).
function iniciarMesaEnBlanco(){
    mesaActivaId = PosMesas.generarId();
    carrito = [];
    document.getElementById('formVenta').reset();
    document.getElementById('mesaInput').value = '';
    clienteIdInput.value = '';
    clienteBuscador.value = '';
    btnLimpiarCliente.style.display = 'none';
    resetearDivisionPago();
    renderCarrito();
    document.getElementById('buscadorPos').focus();
}

// Tras cobrar o cerrar la mesa activa: si hay otras aparcadas, pasa a la
// primera; si no, arranca una mesa en blanco.
function avanzarAMesaSiguiente(){
    if(mesasAbiertas.length){
        restaurarMesa(mesasAbiertas.shift());
        document.getElementById('buscadorPos').focus();
    } else {
        iniciarMesaEnBlanco();
    }
}

// Bug real corregido: mientras el fetch de "Cobrar" está en vuelo (o mientras se
// espera la confirmación de "vender sin stock"), el botón Cobrar queda disabled
// pero el resto de la UI (incluidas las pestañas de mesas) seguía interactiva. Si
// el cajero aprovechaba esos segundos para cambiar a OTRA mesa, al cerrarse el
// modal de "venta registrada" avanzarAMesaSiguiente() descartaba a ciegas lo que
// estuviera activo en ESE momento (la otra mesa, todavía sin cobrar) y traía de
// vuelta la que ya se había cobrado — se perdía por completo el trabajo cargado
// en la mesa que el cajero había seguido atendiendo. Ver limpiarVenta().
function _puedeCambiarDeMesa(){
    if (document.getElementById('btnCobrar').disabled) {
        if (typeof showToast === 'function') showToast('Esperá a que termine de procesarse el cobro actual antes de cambiar de mesa.', 'warning');
        return false;
    }
    return true;
}

function crearNuevaMesa(){
    if(!_puedeCambiarDeMesa()) return;
    const activaSnap = snapshotMesaActiva();
    if(!esMesaVacia(activaSnap)) mesasAbiertas.push(activaSnap);
    iniciarMesaEnBlanco();
}

function cambiarMesa(id){
    if(id === mesaActivaId) return;
    if(!_puedeCambiarDeMesa()) return;
    const idx = mesasAbiertas.findIndex(t=>t.id===id);
    if(idx===-1) return;
    const activaSnap = snapshotMesaActiva();
    if(!esMesaVacia(activaSnap)) mesasAbiertas.push(activaSnap);
    restaurarMesa(mesasAbiertas.splice(idx,1)[0]);
}

function cerrarMesa(id, ev){
    if(ev) ev.stopPropagation();
    if(!_puedeCambiarDeMesa()) return;
    if(id === mesaActivaId){
        const activaSnap = snapshotMesaActiva();
        if(!esMesaVacia(activaSnap) && !confirm('¿Descartar esta mesa/venta en curso? Se perderán los ítems cargados.')) return;
        avanzarAMesaSiguiente();
        return;
    }
    const idx = mesasAbiertas.findIndex(t=>t.id===id);
    if(idx===-1) return;
    const tab = mesasAbiertas[idx];
    if(!esMesaVacia(tab) && !confirm(`¿Descartar "${tab.mesa || 'esta mesa'}"? Se perderán los ítems cargados.`)) return;
    mesasAbiertas.splice(idx,1);
    persistirMesas();
}

function persistirMesas(){
    const activa = snapshotMesaActiva();
    const todas  = [activa, ...mesasAbiertas];
    PosMesas.guardarTodo({ activeId: mesaActivaId, tabs: todas });
    renderTabsMesas(todas);
}

function renderTabsMesas(todas){
    const wrap = document.getElementById('mesasTabsWrap');
    const cont = document.getElementById('mesasTabs');
    cont.innerHTML = '';
    if(todas.length < 2){ wrap.classList.add('d-none'); wrap.classList.remove('d-flex'); return; }
    wrap.classList.remove('d-none'); wrap.classList.add('d-flex');
    todas.forEach((t, i)=>{
        const activa    = t.id === mesaActivaId;
        const nItems    = (t.carrito||[]).length;
        const totalTab  = (t.carrito||[]).reduce((s,it)=>s+it.cantidad*it.precio_unitario*(1-it.descuento_pct/100), 0);
        const etiqueta  = t.mesa || ('Venta ' + (i+1));
        const pill = document.createElement('div');
        pill.className = 'btn btn-sm ' + (activa ? 'btn-primary' : 'btn-danger') + ' d-flex align-items-center gap-2 py-1';
        pill.style.cursor = 'pointer';
        pill.innerHTML = `<span>🍽 ${esc(etiqueta)}${nItems ? ' · ' + nItems + ' ít.' : ''}${totalTab ? ' · ' + formatMoneda(totalTab) : ''}</span>
            <span class="btnCerrarMesa" title="Descartar" style="opacity:.7">✕</span>`;
        pill.addEventListener('click', ()=>cambiarMesa(t.id));
        pill.querySelector('.btnCerrarMesa').addEventListener('click', (ev)=>cerrarMesa(t.id, ev));
        cont.appendChild(pill);
    });
}

// ── Modal de edición de item ───────────────────────────────────────────────────
const editCantidadInput  = document.getElementById('editCantidad');
const editPrecioInput    = document.getElementById('editPrecio');
const editDescuentoInput = document.getElementById('editDescuento');

function abrirEditorItem(idx){
    const it = carrito[idx];
    if(!it) return;
    document.getElementById('editIdx').value = idx;
    document.getElementById('editNombreProducto').textContent = it.nombre;
    editCantidadInput.value  = it.cantidad;
    editPrecioInput.value    = it.precio_unitario;
    editDescuentoInput.value = it.descuento_pct;
    document.getElementById('editStockInfo').textContent = (it.controla_stock === false)
        ? 'Sin control de stock'
        : 'Stock disponible: ' + it.stock_actual;
    actualizarPreviewEditor();
    _showModal('modalEditarItem');
}

function actualizarPreviewEditor(){
    const cant   = parseFloat(editCantidadInput.value)  || 0;
    const precio = parseFloat(editPrecioInput.value)    || 0;
    const desc   = parseFloat(editDescuentoInput.value) || 0;
    document.getElementById('editSubtotalPreview').textContent = formatMoneda(cant*precio*(1-desc/100));
}
editCantidadInput.addEventListener('input', actualizarPreviewEditor);
editPrecioInput.addEventListener('input', actualizarPreviewEditor);
editDescuentoInput.addEventListener('input', actualizarPreviewEditor);

function guardarEditorItem(){
    const idx = parseInt(document.getElementById('editIdx').value, 10);
    const it  = carrito[idx];
    if(!it) return;
    const cant = parseFloat(editCantidadInput.value) || 0;
    if(cant <= 0){ alert('La cantidad debe ser mayor a 0.'); return; }
    it.cantidad        = cant;
    it.precio_unitario = parseFloat(editPrecioInput.value) || 0;
    it.descuento_pct   = Math.min(100, Math.max(0, parseFloat(editDescuentoInput.value) || 0));
    renderCarrito();
    _hideModal('modalEditarItem');
}

function sincronizar(){document.getElementById('itemsJson').value=JSON.stringify(carrito);}

function actualizarTotales(){
    const descG=parseFloat(document.getElementById('descGlobal').value)||0;
    let sub=0, iva=0;
    carrito.forEach(it=>{
        // Se parte del total FINAL de la línea (lo que paga el cliente) y de ahí se separa
        // el neto, en vez de sumar el IVA sobre el neto: así la cuenta funciona igual con
        // precios cargados con IVA y sin IVA, y el total que ve el cajero siempre coincide
        // con el que guarda VentaModel::registrar().
        const finalLinea = precioFinalCliente(it.precio_unitario, it.iva_porcentaje)
                         * it.cantidad * (1 - it.descuento_pct/100);
        const ivaEf = ES_RESPONSABLE_INSCRIPTO ? (it.iva_porcentaje || 0) : 0;
        const neto  = ivaEf > 0 ? finalLinea / (1 + ivaEf/100) : finalLinea;
        sub += neto;
        iva += finalLinea - neto;
    });
    const descMonto=((sub+iva)*descG/100);
    const total=sub+iva-descMonto;
    document.getElementById('resSubtotal').textContent=formatMoneda(sub);
    document.getElementById('resIva').textContent=formatMoneda(iva);
    document.getElementById('resDescuento').textContent='- '+formatMoneda(descMonto);
    document.getElementById('resTotal').textContent=formatMoneda(total);

    // Si facturar ESTA venta llevaría el uso de hoy al 90% o más de la cuota diaria, se
    // destilda sola "Facturar por AFIP" (el cajero puede volver a tildarla si igual quiere).
    const afipCk = document.getElementById('facturarAfip');
    const avisoEl = document.getElementById('afipAviso90');
    if (AFIP_CUOTA_HOY && AFIP_PCT_HOY_INI !== null && afipCk.checked && !afipCk.disabled) {
        const pctProyectado = AFIP_PCT_HOY_INI + (total / AFIP_CUOTA_HOY) * 100;
        if (pctProyectado >= 90) {
            afipCk.checked = false;
            avisoEl.classList.remove('d-none');
        } else {
            avisoEl.classList.add('d-none');
        }
    } else {
        avisoEl.classList.add('d-none');
    }
}

document.getElementById('descGlobal').addEventListener('input', actualizarTotales);

// ── Envío ─────────────────────────────────────────────────────────────────────
let ventaOfflineActual = null; // referencia a la última venta guardada sin conexión, para poder imprimirla

// Intenta guardar la venta; si el servidor avisa que algún producto hecho al momento
// no tiene stock suficiente (requiereConfirmacion), le pregunta al usuario si quiere
// venderlo igual y, si confirma, reintenta una sola vez con confirmar_sin_stock=1.
async function _intentarGuardarVenta(fd, errEl, yaConfirmado) {
    const r = await fetch(`${BASE_URL}/ventas/guardar`, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
        mostrarModalVentaOk(d);
        return;
    }
    if (d.requiereConfirmacion && !yaConfirmado) {
        const msg = `No hay stock suficiente de: ${(d.faltantes || []).join(', ')}. ¿Vender igual?`;
        const confirmarYReintentar = async () => {
            fd.set('confirmar_sin_stock', '1');
            document.getElementById('btnCobrar').disabled = true;
            try {
                await _intentarGuardarVenta(fd, errEl, true);
            } finally {
                document.getElementById('btnCobrar').disabled = false;
            }
        };
        if (typeof showConfirm === 'function') {
            showConfirm(msg, confirmarYReintentar, { label: 'Vender igual', sub: 'Va a quedar registrado en el reporte de ventas sin stock.' });
        } else if (confirm(msg)) {
            await confirmarYReintentar();
        }
        return;
    }
    errEl.textContent = d.faltantes && d.faltantes.length ? `${d.error} Falta: ${d.faltantes.join(', ')}.` : d.error;
    errEl.classList.remove('d-none');
}

document.getElementById('formVenta').addEventListener('submit', async function(ev){
    ev.preventDefault();
    const errEl=document.getElementById('posError'); errEl.classList.add('d-none');
    if(!TURNO_ABIERTO){errEl.textContent='No podés cobrar sin un turno de caja abierto.';errEl.classList.remove('d-none');return;}
    if(!carrito.length){errEl.textContent='Agregue productos al carrito.';errEl.classList.remove('d-none');return;}
    if(Math.abs(sumaMontosPago() - totalVentaNum()) >= 0.01){errEl.textContent='La suma de los medios de pago no coincide con el total.';errEl.classList.remove('d-none');return;}
    document.getElementById('btnCobrar').disabled=true;
    const fd=new FormData(this);
    try{
        // OJO: acá NO se chequea r.ok antes de leer el JSON — a diferencia de los
        // fetch de catálogo (GET), este POST no pasa por el Service Worker, así que
        // un error de validación real del servidor (422/500, ej. stock inválido)
        // sigue respondiendo JSON normal y debe mostrarse como error, no encolarse
        // como si fuera un corte de conexión. Solo un fetch() que directamente
        // rechaza (sin poder llegar al servidor) cae en el catch de más abajo.
        await _intentarGuardarVenta(fd, errEl, false);
    }catch{
        await guardarVentaOffline();
    }
    finally{document.getElementById('btnCobrar').disabled=false;}
});

function mostrarModalVentaOk(d){
    mesaIdPagada = mesaActivaId; // ver limpiarVenta() — cuál mesa fue la que realmente se cobró
    if (typeof _dbgPos === 'function') _dbgPos('mostrarModalVentaOk', { ventaId: d.id, mesaIdPagada, mesaActivaId });
    actualizarEstadoAfip(d.afipEstado);
    document.getElementById('offlineVentaWrap').classList.add('d-none');
    document.getElementById('btnTicket').classList.remove('d-none');
    document.getElementById('btnComanda').classList.remove('d-none');
    document.getElementById('btnImprimirOffline').classList.add('d-none');

    document.getElementById('ventaIdModal').textContent='#'+d.id;
    document.getElementById('btnTicket').dataset.url=d.ticket_url;
    document.getElementById('btnComanda').dataset.url=d.comanda_url;
    // WhatsApp share: el link manda al mismo ticket que se ve/imprime acá (con CAE
    // y QR si se facturó por AFIP, o al comprobante interno si no se facturó) —
    // no un simple resumen de texto.
    const waBtn = document.getElementById('btnWhatsApp');
    const waText = `🛒 *DEWOC — Ticket #${d.id}*\n*TOTAL: $${Number(d.total).toLocaleString('es-AR',{minimumFractionDigits:2})}*\n\nVer tu comprobante: ${d.ticket_url}\n\n¡Gracias por su compra!`;
    let waPhone = (d.cliente_telefono||'').replace(/\D/g,'');
    if(waPhone.length===10) waPhone='54'+waPhone;
    waBtn.href = 'https://wa.me/'+waPhone+'?text='+encodeURIComponent(waText);
    waBtn.classList.toggle('d-none', !d.total);
    // AFIP CAE result
    const caeWrap = document.getElementById('afipCaeWrap');
    if (d.afip) {
        caeWrap.classList.remove('d-none');
        if (d.afip.ok) {
            caeWrap.className = 'alert alert-success-soft py-2 mb-3 text-start small';
            caeWrap.innerHTML = `<strong>✓ CAE obtenido</strong><br>
                CAE: <code>${d.afip.cae}</code><br>
                Vencimiento: ${d.afip.cae_vto}<br>
                Comprobante: ${d.afip.tipo_cbte}/${String(d.afip.pto_vta).padStart(5,'0')}-${String(d.afip.nro_cbte).padStart(8,'0')}`;
        } else {
            caeWrap.className = 'alert alert-warning-soft py-2 mb-3 text-start small';
            caeWrap.innerHTML = `<strong>AFIP: error al solicitar CAE</strong><br>${d.afip.error || d.afip.obs || 'Error desconocido'}`;
        }
    } else {
        caeWrap.classList.add('d-none');
    }
    _showModal('modalTicket');
}

// Venta guardada sin conexión: se encola en IndexedDB (nunca se pierde) y se
// descuenta el stock local de forma optimista para que la próxima venta offline
// en la misma sesión ya vea el stock correcto. No se factura por AFIP (requiere
// conexión sí o sí) ni se sincroniza sola — eso lo decide el usuario después.
async function guardarVentaOffline(){
    const errEl=document.getElementById('posError');
    try{
        const totalTexto = document.getElementById('resTotal').textContent;
        const total = parseFloat(totalTexto.replace(/[^0-9,]/g,'').replace(',','.')) || 0;

        const venta = await PosOffline.guardarVentaPendiente({
            items:            carrito,
            cliente_id:       document.getElementById('clienteId').value,
            descuento_global: document.getElementById('descGlobal').value,
            metodo_pago:      medioPagoDominante(),
            notas:            document.querySelector('#formVenta [name="notas"]').value,
            mesa:             document.getElementById('mesaInput').value,
            total,
        });

        for (const it of carrito) {
            await PosOffline.descontarStockLocal(it.producto_id, it.cantidad);
        }

        mostrarModalVentaOffline(venta);
        actualizarContadorPendientes();
    }catch(e){
        errEl.textContent = 'No se pudo guardar la venta (ni siquiera localmente): ' + e.message;
        errEl.classList.remove('d-none');
    }
}

function mostrarModalVentaOffline(venta){
    mesaIdPagada = mesaActivaId; // ver limpiarVenta() — cuál mesa fue la que realmente se cobró
    if (typeof _dbgPos === 'function') _dbgPos('mostrarModalVentaOffline', { mesaIdPagada, mesaActivaId });
    ventaOfflineActual = venta;
    document.getElementById('ventaIdModal').textContent = 'guardada sin conexión';
    document.getElementById('afipCaeWrap').classList.add('d-none');
    document.getElementById('offlineVentaWrap').classList.remove('d-none');
    document.getElementById('btnTicket').classList.add('d-none');
    document.getElementById('btnComanda').classList.add('d-none');
    document.getElementById('btnWhatsApp').classList.add('d-none');
    document.getElementById('btnImprimirOffline').classList.remove('d-none');
    _showModal('modalTicket');
}

/**
 * Log de diagnóstico para el bug "cierro el ticket con la X y no se vacía el carrito"
 * (reportado en algunos navegadores, no reproducible en todos). Además de la consola
 * (F12 → Console, filtrar por "[POS]"), cada entrada queda en localStorage porque en
 * el navegador/dispositivo donde SÍ falla puede no ser cómodo tener la consola abierta
 * en el momento justo — con la consola cerrada, después alcanza con ejecutar
 * `dewocVerLogPos()` (o revisar localStorage['dewocPosDebugLog']) para ver la secuencia
 * completa de lo que pasó en esa venta. Quitar esta instrumentación (y las llamadas a
 * _dbgPos) una vez identificada la causa real — es solo para diagnóstico temporal.
 */
function _dbgPos(evento, datos){
    try {
        console.log('[POS]', evento, datos ?? '');
        const key = 'dewocPosDebugLog';
        const log = JSON.parse(localStorage.getItem(key) || '[]');
        log.push({ t: new Date().toISOString(), evento, datos: datos ?? null });
        while (log.length > 300) log.shift();
        localStorage.setItem(key, JSON.stringify(log));
    } catch (e) { /* nunca debe romper el flujo real por un problema de logging */ }
}
window.dewocVerLogPos = function(){
    const log = JSON.parse(localStorage.getItem('dewocPosDebugLog') || '[]');
    console.table(log.map(l => ({ hora: l.t, evento: l.evento, datos: JSON.stringify(l.datos) })));
    return log;
};
window.dewocLimpiarLogPos = function(){ localStorage.removeItem('dewocPosDebugLog'); };

function nuevaVenta(){
    _dbgPos('nuevaVenta:click', {
        mesaIdPagada, mesaActivaId, carritoLen: carrito.length,
        bootstrapDefinido: typeof bootstrap !== 'undefined',
        modalVisible: document.getElementById('modalTicket')?.classList.contains('show'),
    });
    // El reset del carrito (limpiarVenta()) va ANTES de pedir que se cierre el modal y
    // en su propio try/catch: si _hideModal() llegara a tirar una excepción (ej. una
    // instancia de Bootstrap Modal inconsistente en algún navegador), antes esa
    // excepción cortaba la función acá mismo y la línea que reseteaba el carrito
    // nunca se ejecutaba — el modal quedaba "cerrado" (por lo que sea que lo cerró
    // visualmente) pero la tabla de productos intacta. Separado así, un fallo al
    // cerrar el modal ya no puede impedir el reset del carrito.
    try {
        limpiarVenta();
        _dbgPos('nuevaVenta:limpiarVenta OK', { carritoLen: carrito.length, mesaActivaId });
    } catch (e) {
        _dbgPos('nuevaVenta:ERROR en limpiarVenta', { mensaje: e.message, stack: e.stack });
    }
    try {
        _hideModal('modalTicket');
    } catch (e) {
        _dbgPos('nuevaVenta:ERROR en _hideModal', { mensaje: e.message, stack: e.stack });
    }
}

// Se llama tras cobrar (éxito u offline) o al cerrar el modal de ticket: la mesa que se
// cobró ya cumplió su ciclo, así que se descarta y se pasa a la siguiente mesa aparcada
// (si hay) o se arranca una en blanco.
//
// OJO: no asumir que "la mesa activa ahora" es la que se cobró — con _puedeCambiarDeMesa()
// ya no debería poder pasar, pero esto queda como red de seguridad: si por cualquier vía
// mesaActivaId cambió entre el cobro exitoso y el cierre de este modal, lo correcto es
// dejar en paz lo que el cajero esté viendo ahora (mesa sin cobrar, con datos reales) y
// solo sacar de "pendientes" la mesa YA cobrada si había quedado aparcada ahí en el medio
// — nunca descartar a ciegas la mesa activa actual.
//
// IDEMPOTENTE a propósito: 'hidden.bs.modal' puede llegar a disparar más de una vez para
// un solo cierre (bug real de Bootstrap corregido en _showModal(), ver su comentario) —
// mesaIdPagada solo queda seteado mientras hay un cobro recién hecho sin procesar todavía;
// si esta función ya corrió una vez para ese cobro, una segunda llamada no debe volver a
// "avanzar" (eso pisaba la mesa siguiente, que ya se había restaurado en la primera pasada).
function limpiarVenta(){
    document.getElementById('pedidoClienteIdInput').value = '';
    if (mesaIdPagada === null) {
        _dbgPos('limpiarVenta:abort — mesaIdPagada ya es null (llamada repetida, no hace nada)');
        return;
    }

    if (mesaIdPagada !== mesaActivaId) {
        // OJO — esta es la rama que NO toca el carrito/tabla visible (ver el comentario
        // grande de más arriba: existe a propósito, para no descartar una mesa distinta
        // a la que se cobró si el cajero cambió de mesa mientras tanto). Si el reporte de
        // "no se borra la tabla" coincide con "limpiarVenta:rama mesa distinta" en el log,
        // el problema NO es de timing del modal — es que mesaActivaId quedó apuntando a
        // otra mesa (o se duplicó/regeneró) sin que el cajero cambiara de mesa a propósito.
        _dbgPos('limpiarVenta:rama mesa distinta (NO limpia el carrito visible)', { mesaIdPagada, mesaActivaId });
        const idx = mesasAbiertas.findIndex(t => t.id === mesaIdPagada);
        if (idx !== -1) mesasAbiertas.splice(idx, 1);
        persistirMesas();
    } else {
        _dbgPos('limpiarVenta:rama misma mesa → avanzarAMesaSiguiente()', { mesaIdPagada, mesasAbiertasLen: mesasAbiertas.length });
        avanzarAMesaSiguiente();
    }
    mesaIdPagada = null;
}

document.getElementById('modalTicket').addEventListener('hidden.bs.modal', () => {
    _dbgPos('evento hidden.bs.modal disparado');
    limpiarVenta();
});

// Carga el documento (ticket o comanda) en el iframe del modal e imprime apenas
// termina de cargar — el vendedor no tiene que esperar ni tocar el botón
// "Imprimir" de abajo (que sigue ahí como respaldo manual por si el navegador
// bloqueara el auto-print).
function _cargarEImprimir(url, titulo){
    if (!url) return;
    document.getElementById('modalPreviewTicketTitulo').textContent = 'Vista previa — ' + titulo;
    const iframe = document.getElementById('iframeTicket');
    iframe.onload = function(){
        iframe.onload = null; // no reimprimir si el iframe se recarga después
        _cerrarModalAlTerminarImprimir(iframe);
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    };
    iframe.src = url;
    _showModal('modalPreviewTicket');
}

// Se engancha ANTES de llamar a print() (no después) para no perder el evento
// 'afterprint' si el navegador lo dispara enseguida. Cierra el modal de vista
// previa solo — el de "Venta registrada" (modalTicket) queda abierto atrás, así
// el cajero sigue viendo el resto de las opciones (WhatsApp, comanda, + Nueva
// venta) sin tener que reabrir nada.
//
// OJO: con --kiosk-printing (impresión silenciosa, ver conversación de soporte)
// Chrome NO dispara 'afterprint' de forma confiable en todas las versiones — es
// un comportamiento inconsistente de esa bandera, no un bug de esta app. Por
// eso se suma un timeout de respaldo: si el evento no llegó solo en 2s (tiempo
// de sobra para que un print silencioso ya haya salido a la cola de impresión),
// se cierra igual. Con diálogo normal (sin esa bandera) el afterprint real
// suele llegar antes de que este timeout se cumpla.
function _cerrarModalAlTerminarImprimir(iframe){
    let yaCerrado = false;
    const cerrar = () => {
        if (yaCerrado) return;
        yaCerrado = true;
        // Cierre a mano por DOM, sin pasar por la API de Bootstrap (ni .hide() ni clic en
        // data-bs-dismiss): con modalTicket y modalPreviewTicket abiertos al mismo tiempo
        // (dos modales de Bootstrap apilados — algo que Bootstrap no soporta oficialmente),
        // ninguna de las dos formas "correctas" cerraba esto de manera confiable. Ocultar el
        // elemento directo no depende de ningún estado interno de Bootstrap.
        const el = document.getElementById('modalPreviewTicket');
        if (el) {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            el.removeAttribute('aria-modal');
            el.removeAttribute('role');

            // Este cierre bypassea la API de Bootstrap a propósito (ver comentario de
            // arriba), así que su instancia interna nunca se entera de que ya se ocultó y
            // se queda con _isShown en true — desde que _showModal() reutiliza la misma
            // instancia (getOrCreateInstance, en vez de crear una nueva cada vez), eso hacía
            // que el PRÓXIMO _showModal('modalPreviewTicket') no hiciera nada (Bootstrap
            // ignora .show() si cree que ya está mostrado). Se corrige el flag a mano.
            //
            // BUG REAL CORREGIDO: acá decía "bootstrap?.Modal?.getInstance(el)". El "?."
            // solo protege contra null/undefined de un valor que ya existe — NO contra una
            // variable que directamente no está declarada. En este entorno "bootstrap" nunca
            // es global (Dashly lo empaqueta sin exponer window.bootstrap, ver _showModal()/
            // _hideModal() de más arriba), así que esa línea tiraba
            // "ReferenceError: bootstrap is not defined" SIEMPRE que se imprimía un ticket o
            // comanda, cortando la función acá mismo: nunca se llegaba a sacar el
            // .modal-backdrop sobrante ni a reagendar _reconciliarModales() de más abajo,
            // dejando el stack de modales corrompido para el resto del turno.
            const inst = (typeof bootstrap !== 'undefined') ? bootstrap.Modal.getInstance(el) : null;
            if (inst) inst._isShown = false;
        }
        // El backdrop de ESTE modal es el último que se agregó al body (Bootstrap los apila
        // en orden de apertura) — se saca solo ese, para no tocar el de modalTicket, que
        // sigue abierto atrás a propósito.
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length) backdrops[backdrops.length - 1].remove();

        // Red de seguridad (ver _reconciliarModales() en app.js): este cierre es manual, sin
        // pasar por la API de Bootstrap, así que 'hidden.bs.modal' nunca se dispara acá y la
        // limpieza automática de ese evento no corre. Si Bootstrap llegó a agregar un backdrop
        // de más al abrir este modal (una carrera real de Bootstrap 5 al mostrar uno rápido
        // justo después de otro — reportado como "queda la pantalla oscura/blureada, no se
        // puede hacer nada" después de facturar+imprimir), el remove() de arriba saca uno pero
        // puede quedar otro huérfano; esto lo destraba de encontrarse en ese estado.
        if (typeof _reconciliarModales === 'function') setTimeout(_reconciliarModales, 350);
    };
    iframe.contentWindow.addEventListener('afterprint', cerrar, { once: true });
    setTimeout(cerrar, 2000);
}

function previewTicket(){
    _cargarEImprimir(document.getElementById('btnTicket').dataset.url, 'Ticket');
}

function imprimirComanda(){
    _cargarEImprimir(document.getElementById('btnComanda').dataset.url, 'Comanda');
}

// Comprobante de una venta offline: se arma 100% en el navegador (sin pegarle
// al servidor) porque todavía no tiene un id real ni CAE — nunca puede tenerlo
// sin conexión. Se imprime como comprobante interno, sin validez fiscal.
function previewTicketOffline(){
    if (!ventaOfflineActual) return;
    const iframe = document.getElementById('iframeTicket');
    iframe.removeAttribute('src');
    iframe.onload = function(){
        iframe.onload = null;
        _cerrarModalAlTerminarImprimir(iframe);
    };
    iframe.srcdoc = construirHtmlTicketOffline(ventaOfflineActual);
    _showModal('modalPreviewTicket');
}

function construirHtmlTicketOffline(venta){
    const fechaStr = new Date(venta.creada_en).toLocaleString('es-AR');
    let filas = '';
    venta.items.forEach(it=>{
        const sub = it.cantidad*it.precio_unitario*(1-it.descuento_pct/100);
        filas += `<div>${Number(it.cantidad).toLocaleString('es-AR')}  ${esc(it.nombre)} — ${formatMoneda(sub)}</div>`;
    });
    return `<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Comprobante interno</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Courier New', monospace; font-size:11px; width:78mm; margin:0 auto; padding:4px; }
.center { text-align:center; } .bold { font-weight:bold; }
.line { border-top:1px dashed #000; margin:4px 0; }
@media print { @page { size:80mm auto; margin:0; } body { width:80mm; } }
</style></head>
<body onload="window.print()">
<div class="center bold" style="font-size:14px">COMPROBANTE INTERNO</div>
<div class="center" style="font-size:10px">Sin validez fiscal — pendiente de sincronizar</div>
<div class="line"></div>
<div>Fecha: ${esc(fechaStr)}</div>
${venta.mesa ? `<div class="bold">Mesa/Dest.: ${esc(venta.mesa)}</div>` : ''}
<div class="line"></div>
${filas}
<div class="line"></div>
<div class="bold" style="text-align:right">TOTAL: ${formatMoneda(venta.total)}</div>
${venta.notas ? `<div class="line"></div><div class="bold">NOTA:</div><div>${esc(venta.notas)}</div>` : ''}
</body></html>`;
}

function imprimirIframe(){
    const iframe = document.getElementById('iframeTicket');
    _cerrarModalAlTerminarImprimir(iframe);
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
}

// ── Ventas pendientes de sincronizar (guardadas sin conexión) ──────────────────
async function actualizarContadorPendientes(){
    const n = await PosOffline.contarVentasPendientes();
    const badge = document.getElementById('badgePendientes');
    if (n > 0) { badge.textContent = n; badge.classList.remove('d-none'); }
    else badge.classList.add('d-none');
}

async function renderListaPendientes(){
    const cont = document.getElementById('listaPendientes');
    const ventas = await PosOffline.listarVentasPendientes();
    cont.innerHTML = '';
    if (!ventas.length) {
        cont.innerHTML = `<div class="list-group-item text-muted small text-center py-3">No hay ventas pendientes.</div>`;
        return;
    }
    ventas.forEach(v=>{
        const fecha = new Date(v.creada_en).toLocaleString('es-AR');
        const cantItems = v.items.reduce((a,it)=>a+parseFloat(it.cantidad),0);
        const row = document.createElement('div');
        row.className = 'list-group-item small';
        row.dataset.localId = v.local_id;
        row.innerHTML = `<div class="d-flex justify-content-between align-items-center">
            <span>${esc(fecha)} — ${cantItems} ítem(s)</span>
            <span class="fw-bold">${formatMoneda(v.total)}</span>
        </div>`;
        cont.appendChild(row);
    });
}

async function abrirModalPendientes(){
    await renderListaPendientes();
    document.getElementById('syncResultado').classList.add('d-none');
    _showModal('modalVentasPendientes');
}

async function sincronizarPendientes(){
    const btn   = document.getElementById('btnSincronizarAhora');
    const resEl = document.getElementById('syncResultado');
    btn.disabled = true;
    resEl.classList.add('d-none');
    // CSRF_TOKEN se graba en el HTML al cargar la página (ver <?= Csrf::token() ?> más
    // arriba) y nunca se refresca solo. Si la pestaña del POS queda abierta más tiempo
    // del que dura la sesión en el servidor (SESSION_LIFETIME, o el garbage collection de
    // PHP si es más corto — típico si las ventas quedaron encoladas offline por horas/días
    // antes de sincronizar), el servidor termina con un _csrf_token distinto al que sigue
    // circulando en esta pestaña: Csrf::check() rechaza TODAS las ventas pendientes con el
    // mismo error de sesión — no es un problema de conexión, así que "revisá la conexión"
    // sería engañoso acá. Se detecta ese mensaje puntual para avisar lo que realmente hay
    // que hacer (recargar la página) en vez del genérico de conexión.
    let huboErrorSesion = false;
    try{
        const resultado = await PosOffline.sincronizarTodo(BASE_URL, CSRF_TOKEN, (venta, ok, error) => {
            const row = document.querySelector(`#listaPendientes [data-local-id="${venta.local_id}"]`);
            if (!ok && /sesión expiró/i.test(error || '')) huboErrorSesion = true;
            if (!row) return;
            if (ok) row.remove();
            else row.insertAdjacentHTML('beforeend', `<div class="text-danger small mt-1">Error: ${esc(error)}</div>`);
        });
        resEl.classList.remove('d-none');
        if (resultado.error === 0 && resultado.ok > 0) {
            resEl.className = 'alert alert-success-soft py-2 small';
            resEl.textContent = `✓ Se sincronizaron ${resultado.ok} venta(s).`;
        } else if (resultado.ok === 0 && resultado.error === 0) {
            resEl.className = 'alert alert-secondary py-2 small';
            resEl.textContent = 'No había ventas pendientes.';
        } else if (huboErrorSesion) {
            resEl.className = 'alert alert-warning-soft py-2 small';
            resEl.textContent = `Se sincronizaron ${resultado.ok}, quedaron ${resultado.error} pendiente(s): tu sesión venció mientras estaban encoladas. Recargá la página (F5) y volvé a sincronizar — las pendientes no se pierden.`;
        } else {
            resEl.className = 'alert alert-warning-soft py-2 small';
            resEl.textContent = `Se sincronizaron ${resultado.ok}, quedaron ${resultado.error} pendiente(s). Revisá la conexión e intentá de nuevo.`;
        }
        await actualizarContadorPendientes();
        await renderListaPendientes();
    } finally {
        btn.disabled = false;
    }
}

function esc(v){const d=document.createElement('div');d.textContent=v||'';return d.innerHTML;}

// ── Selector de cliente ───────────────────────────────────────────────────────
let clienteTimeout;
const clienteBuscador = document.getElementById('clienteBuscador');
const clienteDropdown = document.getElementById('clienteDropdown');
const clienteIdInput  = document.getElementById('clienteId');
const btnLimpiarCliente = document.getElementById('btnLimpiarCliente');

clienteBuscador.addEventListener('input', function () {
    clearTimeout(clienteTimeout);
    const q = this.value.trim();
    if (!q) { clienteDropdown.style.display = 'none'; return; }
    clienteTimeout = setTimeout(() => buscarCliente(q), 250);
});

async function buscarCliente(q) {
    let items;
    try {
        const r = await fetch(`${BASE_URL}/clientes/buscar?q=${encodeURIComponent(q)}`);
        if (!r.ok) throw new Error('respuesta no ok');
        const data = await r.json();
        items = data.items || [];
    } catch {
        // navigator.onLine solo confirma que hay red real caída — cualquier otra falla acá
        // (típicamente la sesión venció: requireAuth() redirige a /auth/login, el fetch sigue
        // esa redirección y r.json() explota al parsear el HTML del login) NO es "sin conexión".
        const msg = navigator.onLine
            ? 'Tu sesión expiró — recargá la página para volver a entrar.'
            : 'Sin conexión: no se puede buscar clientes ahora. La venta sigue como "Consumidor Final".';
        clienteDropdown.innerHTML = `<div class="list-group-item small text-muted">${msg}</div>`;
        clienteDropdown.style.display = 'block';
        return;
    }
    clienteDropdown.innerHTML = '';
    if (!items.length) {
        clienteDropdown.style.display = 'none';
        return;
    }
    items.forEach(c => {
        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'list-group-item list-group-item-action small py-2';
        btn.innerHTML = `<span class="fw-semibold">${esc(c.nombre)}</span>
            <span class="text-muted ms-2">${esc(c.email || '')} ${c.documento ? '· ' + esc(c.documento) : ''}</span>`;
        btn.addEventListener('click', () => seleccionarCliente(c));
        clienteDropdown.appendChild(btn);
    });
    clienteDropdown.style.display = 'block';
}

function seleccionarCliente(c) {
    clienteIdInput.value       = c.id;
    clienteBuscador.value      = c.nombre + (c.email ? ' — ' + c.email : '');
    clienteDropdown.style.display = 'none';
    btnLimpiarCliente.style.display = '';
    mostrarSaldoCuentaCorriente(c.id);
    actualizarResumenPago();
}

btnLimpiarCliente.addEventListener('click', () => {
    clienteIdInput.value            = '';
    clienteBuscador.value           = '';
    btnLimpiarCliente.style.display = 'none';
    document.getElementById('saldoClienteInfo').style.display = 'none';
    clienteBuscador.focus();
    actualizarResumenPago();
});

// Saldo actual de cuenta corriente + tope del cliente elegido — para que el cajero vea el
// margen disponible antes de intentar cobrar (la validación real igual la hace el backend).
async function mostrarSaldoCuentaCorriente(clienteId) {
    const info = document.getElementById('saldoClienteInfo');
    try {
        const r = await fetch(`${BASE_URL}/clientes/saldo-cuenta-corriente?id=${clienteId}`);
        if (!r.ok) throw new Error('respuesta no ok');
        const d = await r.json();
        if (!d.ok) { info.style.display = 'none'; return; }
        info.textContent = `Cta. Cte.: ${formatMoneda(d.saldo)} / tope ${formatMoneda(d.tope)}`;
        info.style.display = '';
    } catch {
        info.style.display = 'none';
    }
}

document.addEventListener('click', e => {
    if (!e.target.closest('#clienteBuscador') && !e.target.closest('#clienteDropdown')) {
        clienteDropdown.style.display = 'none';
    }
});

// ── Medios de pago (dividido entre varios) + vuelto ─────────────────────────────
const montoInputs = {
    efectivo:      document.getElementById('montoEfectivo'),
    debito:        document.getElementById('montoDebito'),
    credito:       document.getElementById('montoCredito'),
    transferencia: document.getElementById('montoTransferencia'),
    mercadopago:   document.getElementById('montoMercadopago'),
    cuenta_corriente: document.getElementById('montoCuentaCorriente'),
};
const medioChks = {
    efectivo:      document.getElementById('chkEfectivo'),
    debito:        document.getElementById('chkDebito'),
    credito:       document.getElementById('chkCredito'),
    transferencia: document.getElementById('chkTransferencia'),
    mercadopago:   document.getElementById('chkMercadopago'),
    cuenta_corriente: document.getElementById('chkCuentaCorriente'),
};
const bloqueEfectivo   = document.getElementById('bloqueEfectivo');
const montoRecibido    = document.getElementById('montoRecibido');
const vueltoTexto      = document.getElementById('vueltoTexto');
const pagoResumenTexto = document.getElementById('pagoResumenTexto');
const chkDividirPago   = document.getElementById('chkDividirPago');

// Mientras haya un solo medio de pago tildado, ese input se autocompleta con el total
// (mismo comportamiento de siempre para el caso simple de un solo medio: antes era
// siempre Efectivo, ahora es "el que esté tildado"). En cuanto se tilda un segundo medio
// (o se edita cualquier monto a mano), se deja de tocar la división automáticamente y el
// cajero reparte los importes como quiera.
let pagoDividioManual = false;

function mediosActivos() {
    return Object.keys(medioChks).filter(m => medioChks[m].checked);
}

// Los inputs deshabilitados no viajan en el FormData del submit (comportamiento nativo
// del navegador) — así que un medio destildado directamente no manda monto_x, sin
// necesidad de filtrarlo aparte en el backend.
function medioChkCambio(medio) {
    const chk = medioChks[medio];

    // Con "Dividir pago" apagado, los checkboxes se comportan como radios: tildar uno
    // destilda todos los demás, y no se permite destildar el único que queda tildado
    // (siempre hay que tener exactamente un medio elegido en el modo simple).
    if (!chkDividirPago.checked) {
        if (chk.checked) {
            Object.entries(medioChks).forEach(([m, c]) => {
                if (m !== medio && c.checked) {
                    c.checked = false;
                    montoInputs[m].disabled = true;
                    montoInputs[m].value = 0;
                }
            });
        } else {
            chk.checked = true;
        }
    }

    const inp = montoInputs[medio];
    inp.disabled = !chk.checked;
    if (!chk.checked) inp.value = 0;

    const activos = mediosActivos();
    if (activos.length === 1) {
        pagoDividioManual = false;
        montoInputs[activos[0]].value = totalVentaNum().toFixed(2);
    } else if (activos.length > 1) {
        pagoDividioManual = true;
    }
    actualizarResumenPago();
}
Object.entries(medioChks).forEach(([medio, chk]) => {
    chk.addEventListener('change', () => medioChkCambio(medio));
});

// El checkbox y su <label for="..."> ya activan/destildan el medio de forma nativa. Falta
// el campo de importe: mientras el medio no está activo, su input queda disabled (así no
// viaja en el submit) y un input disabled no dispara eventos de click/foco en el navegador
// — por eso se escucha en el .input-group que lo envuelve (nunca disabled) en vez de en el
// input, y en CSS el input disabled tiene pointer-events:none para que el click "atraviese"
// hasta ese contenedor. Un solo click ahí ya alcanza y selecciona/activa el medio.
Object.entries(montoInputs).forEach(([medio, inp]) => {
    inp.closest('.input-group').addEventListener('click', () => {
        if (!medioChks[medio].checked) {
            medioChks[medio].checked = true;
            medioChkCambio(medio);
        }
        inp.focus();
    });
});

// Al apagar "Dividir pago" se vuelve a un solo medio: se conserva el que tenga más monto
// cargado (el "dominante") y se apagan/limpian los demás.
chkDividirPago.addEventListener('change', () => {
    if (chkDividirPago.checked) return;
    const dominante = medioPagoDominante();
    Object.entries(medioChks).forEach(([m, c]) => {
        c.checked = m === dominante;
        montoInputs[m].disabled = m !== dominante;
        if (m !== dominante) montoInputs[m].value = 0;
    });
    pagoDividioManual = false;
    montoInputs[dominante].value = totalVentaNum().toFixed(2);
    actualizarResumenPago();
});

function totalVentaNum() {
    const totalEl = document.getElementById('resTotal').textContent;
    return parseFloat(totalEl.replace(/[^0-9,]/g, '').replace(',', '.')) || 0;
}

function sumaMontosPago() {
    return Object.values(montoInputs).reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
}

function medioPagoDominante() {
    let medio = 'efectivo', max = -1;
    Object.entries(montoInputs).forEach(([m, inp]) => {
        const v = parseFloat(inp.value) || 0;
        if (v > max) { max = v; medio = m; }
    });
    return medio;
}

function actualizarResumenPago() {
    const total = totalVentaNum();
    const suma  = Math.round(sumaMontosPago() * 100) / 100;
    const ok    = Math.abs(suma - total) < 0.01;

    pagoResumenTexto.textContent = ok
        ? 'Cargado: ' + formatMoneda(suma)
        : 'Cargado: ' + formatMoneda(suma) + ' / ' + formatMoneda(total);
    pagoResumenTexto.className = 'small fw-semibold ' + (ok ? 'text-success' : 'text-danger');

    // No se puede vender a cuenta corriente sin cliente (VentasController::guardar() lo
    // rechaza igual del lado servidor — esto es solo para no hacer esperar al cajero).
    const montoCC = parseFloat(montoInputs.cuenta_corriente.value) || 0;
    const avisoCC = document.getElementById('avisoCuentaCorriente');
    const faltaCliente = montoCC > 0 && !clienteIdInput.value;
    avisoCC.style.display = faltaCliente ? '' : 'none';

    document.getElementById('btnCobrar').disabled = !carrito.length || !ok || !TURNO_ABIERTO || faltaCliente;

    bloqueEfectivo.style.display = (parseFloat(montoInputs.efectivo.value) || 0) > 0 ? '' : 'none';
    calcularVuelto();
}

function calcularVuelto() {
    const asignadoEfectivo = parseFloat(montoInputs.efectivo.value) || 0;
    const recibido = parseFloat(montoRecibido.value) || 0;
    const vuelto   = Math.max(0, recibido - asignadoEfectivo);
    vueltoTexto.textContent = formatMoneda(vuelto);
    vueltoTexto.className   = 'fw-bold ' + (recibido > 0 && recibido < asignadoEfectivo ? 'text-danger' : 'text-success');
}

Object.values(montoInputs).forEach(inp => {
    inp.addEventListener('input', () => { pagoDividioManual = true; actualizarResumenPago(); });
});
montoRecibido.addEventListener('input', calcularVuelto);

function resetearDivisionPago() {
    pagoDividioManual = false;
    chkDividirPago.checked = false;
    Object.entries(medioChks).forEach(([medio, chk]) => {
        chk.checked = medio === 'efectivo';
        montoInputs[medio].disabled = medio !== 'efectivo';
    });
    montoInputs.efectivo.value = totalVentaNum().toFixed(2);
    Object.entries(montoInputs).forEach(([medio, inp]) => { if (medio !== 'efectivo') inp.value = 0; });
    actualizarResumenPago();
}

// Actualizar el medio de pago activo cuando cambia el total (mientras no se haya
// dividido a mano ni se hayan tildado dos o más medios a la vez).
const _origActTotales = actualizarTotales;
actualizarTotales = function () {
    _origActTotales();
    if (!pagoDividioManual) {
        const activos = mediosActivos();
        if (activos.length === 1) montoInputs[activos[0]].value = totalVentaNum().toFixed(2);
    }
    actualizarResumenPago();
};
// Restaura mesas abiertas guardadas (si las hay) — debe ir después de que
// existan montoInputs/clienteIdInput/etc. de más arriba, porque restaurarMesa()
// los usa. Un F5 a mitad de turno no debe perder ninguna mesa pendiente.
function inicializarMesas(){
    const guardado = PosMesas.cargar();
    if(guardado && guardado.tabs && guardado.tabs.length){
        const activa = guardado.tabs.find(t=>t.id===guardado.activeId) || guardado.tabs[0];
        mesasAbiertas = guardado.tabs.filter(t=>t.id!==activa.id);
        restaurarMesa(activa);
    } else {
        mesaActivaId = PosMesas.generarId();
        renderCarrito();
    }
}

// formatMoneda() vive en app.js, que en el layout se carga DESPUÉS del contenido de la
// página (ver app/Views/layouts/main.php) — llamar a estas inicializaciones en el momento
// en que el navegador parsea este <script> (antes de que app.js se haya ejecutado) tira
// "formatMoneda is not defined". DOMContentLoaded recién dispara una vez que todos los
// <script src> sincrónicos previos (incluido app.js) ya corrieron, así que para entonces
// formatMoneda ya existe.
document.addEventListener('DOMContentLoaded', function () {
    actualizarResumenPago();
    inicializarMesas();
    cargarTareaPendiente();
    cargarSolicitudInventarioPendiente();
    cargarPedidoPendiente();
    cargarMensajesSinLeer();
});

document.getElementById('mesaInput').addEventListener('input', persistirMesas);
window.addEventListener('beforeunload', persistirMesas);

// ── Cartel de tarea asignada (ver docs/tareas-pendientes.md #2) ────────────────────────
let _tareaActual = null;

async function cargarTareaPendiente() {
    try {
        const r = await fetch(`${BASE_URL}/tareas/pendiente-para-mi`);
        const d = await r.json();
        if (d.ok) renderTareaBanner(d.tarea);
    } catch { /* silencioso: no bloquea el POS si falla el polling */ }
}
setInterval(cargarTareaPendiente, 30000);

// ── Cartel de solicitud de inventario asignada ─────────────────────────────────────────
let _solicitudInventarioActual = null;

async function cargarSolicitudInventarioPendiente() {
    try {
        const r = await fetch(`${BASE_URL}/solicitudes/pendiente-para-mi`);
        const d = await r.json();
        if (d.ok) renderSolicitudInventarioBanner(d.solicitud);
    } catch { /* silencioso: no bloquea el POS si falla el polling */ }
}
setInterval(cargarSolicitudInventarioPendiente, 30000);

function renderSolicitudInventarioBanner(s) {
    _solicitudInventarioActual = s;
    const banner = document.getElementById('solicitudInventarioBanner');
    if (!s) { banner.classList.add('d-none'); return; }

    banner.classList.remove('d-none');
    document.getElementById('solicitudInventarioBannerTitulo').textContent = '📦 ' + s.item_nombre;
    document.getElementById('solicitudInventarioBannerAsignadaPor').textContent = 'Pedida por ' + (s.creador_nombre || '—');
    document.getElementById('solicitudInventarioBannerDescripcion').textContent = s.descripcion || '';
    document.getElementById('solicitudInventarioBannerLink').href = `${BASE_URL}/stock/inventario?solicitud_id=${s.id}`;

    const enProceso = s.estado === 'en_proceso';
    document.getElementById('solicitudInventarioAccionesPendiente').classList.toggle('d-none', enProceso);
    document.getElementById('solicitudInventarioAccionesEnProceso').classList.toggle('d-none', !enProceso);
}

async function _accionSolicitudInventario(url, extraParams = {}) {
    const fd = new FormData();
    fd.append('id', _solicitudInventarioActual.id);
    fd.append('_csrf_token', CSRF_TOKEN);
    Object.entries(extraParams).forEach(([k, v]) => fd.append(k, v));
    try {
        const r = await fetch(`${BASE_URL}/${url}`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            if (typeof showToast === 'function') showToast(d.mensaje, 'success');
            cargarSolicitudInventarioPendiente();
        } else {
            if (typeof showToast === 'function') showToast(d.error || 'No se pudo completar.', 'danger');
            else alert(d.error || 'No se pudo completar.');
        }
    } catch {
        if (typeof showToast === 'function') showToast('Error de conexión.', 'danger');
    }
}

function aceptarSolicitudInventario() { _accionSolicitudInventario('solicitudes/aceptar-inventario'); }
function finalizarSolicitudInventario() { _accionSolicitudInventario('solicitudes/finalizar-inventario'); }
function aplazarSolicitudInventario(minutos) { _accionSolicitudInventario('solicitudes/aplazar-inventario', { minutos }); }

function abrirRechazoSolicitudInventario() {
    document.getElementById('solInvMotivoRechazo').value = '';
    document.getElementById('solInvRechazoError').classList.add('d-none');
    _showModal('modalRechazarSolicitudInventario');
}
async function confirmarRechazoSolicitudInventario() {
    const motivo = document.getElementById('solInvMotivoRechazo').value.trim();
    if (!motivo) {
        const el = document.getElementById('solInvRechazoError');
        el.textContent = 'El motivo es obligatorio.';
        el.classList.remove('d-none');
        return;
    }
    await _accionSolicitudInventario('solicitudes/rechazar-inventario', { motivo });
    _hideModal('modalRechazarSolicitudInventario');
}

// ── Pedidos del Portal de Clientes Mayoristas (ver docs/portal-clientes-mayoristas-checklist.md) ──
// A diferencia del cartel de tareas (arriba), este modal es BLOQUEANTE: no tiene botón de
// cierre y el backdrop es fijo — no se puede seguir usando el POS hasta Aceptar, Rechazar,
// o al menos abrir el chat. Mismo poll cada 30s que cargarTareaPendiente().
let _pedidoClienteActual = null;

// Si el Portal de Clientes Mayoristas todavía no está desplegado en este servidor, estas dos
// rutas dan 404 — se corta el sondeo apenas se detecta (en vez de seguir insistiendo cada 30s
// y ensuciar la consola) hasta el próximo F5, que ya las vuelve a intentar solo. El día que se
// despliegue el Portal, esto vuelve a funcionar sin tocar nada acá.
let _intervalPedidoPendiente = setInterval(cargarPedidoPendiente, 30000);

async function cargarPedidoPendiente() {
    try {
        const r = await fetch(`${BASE_URL}/pedidos-clientes/pendiente-para-mi`);
        if (r.status === 404) { clearInterval(_intervalPedidoPendiente); return; }
        const d = await r.json();
        if (!d.ok) return;
        _pedidoClienteActual = d.pedido;
        if (d.pedido) renderPedidoClienteModal(d.pedido);
    } catch { /* silencioso: no bloquea el POS si falla el polling */ }
}

// Aviso NO bloqueante (a diferencia del modal de arriba): un pedido ya aceptado tiene un
// mensaje del cliente sin responder. Decisión confirmada — ver docs/portal-clientes-mayoristas-checklist.md.
let _pedidoConMensajeSinLeer = null;
let _intervalMensajesSinLeer = setInterval(cargarMensajesSinLeer, 30000);

async function cargarMensajesSinLeer() {
    try {
        const r = await fetch(`${BASE_URL}/pedidos-clientes/hay-mensajes-sin-leer`);
        if (r.status === 404) { clearInterval(_intervalMensajesSinLeer); return; }
        const d = await r.json();
        const el = document.getElementById('avisoMensajePedido');
        if (d.ok && d.pedido) {
            _pedidoConMensajeSinLeer = d.pedido;
            el.textContent = `💬 ${d.pedido.cliente_nombre} escribió sobre el pedido #${d.pedido.id} — sin responder`;
            el.classList.remove('d-none');
        } else {
            _pedidoConMensajeSinLeer = null;
            el.classList.add('d-none');
        }
    } catch { /* silencioso */ }
}

function abrirChatDesdeAviso() {
    if (!_pedidoConMensajeSinLeer) return;
    _pedidoClienteActual = _pedidoConMensajeSinLeer;
    abrirChatPedido();
}

function renderPedidoClienteModal(pedido) {
    document.getElementById('pcNombreCliente').textContent = pedido.cliente_nombre;
    document.getElementById('pcError').classList.add('d-none');
    document.getElementById('pcRechazoWrap').classList.add('d-none');
    document.getElementById('pcMensajeRechazo').value = '';

    document.getElementById('pcDetalle').innerHTML = pedido.detalle.map(it => {
        const opcionesTxt = (it.opciones || []).map(o => (o.cantidad_elegida > 1 ? o.cantidad_elegida + 'x ' : '') + o.nombre).join(', ');
        return `<div class="d-flex justify-content-between border-bottom py-2">
            <div>
                <div class="fw-bold" style="font-size:1.05rem">${esc(it.producto_nombre)}</div>
                ${opcionesTxt ? `<div class="fw-semibold text-primary" style="font-size:.95rem">🍦 ${esc(opcionesTxt)}</div>` : ''}
                <div class="fw-semibold text-body" style="font-size:.9rem">${it.cantidad} x ${formatMoneda(it.precio_unitario)}</div>
            </div>
        </div>`;
    }).join('');

    const notasEl = document.getElementById('pcNotas');
    if (pedido.notas_cliente) {
        notasEl.textContent = '📝 ' + pedido.notas_cliente;
        notasEl.classList.remove('d-none');
    } else {
        notasEl.classList.add('d-none');
    }

    _showModal('modalPedidoCliente');
}

async function aceptarPedidoCliente() {
    if (!_pedidoClienteActual) return;
    const fd = new FormData();
    fd.append('id', _pedidoClienteActual.id);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/pedidos-clientes/aceptar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.ok) { mostrarErrorPedidoCliente(d.error); return; }
        precargarCarritoDesdePedido(_pedidoClienteActual);
        _hideModal('modalPedidoCliente');
        _pedidoClienteActual = null;
    } catch { mostrarErrorPedidoCliente('Error de conexión.'); }
}

function precargarCarritoDesdePedido(pedido) {
    pedido.detalle.forEach(it => {
        const opciones = (it.opciones || []).map(o => ({ opcion_id: +o.opcion_id, nombre: o.nombre, cantidad_elegida: +o.cantidad_elegida }));
        carrito.push({
            producto_id:      it.producto_id,
            nombre:           it.producto_nombre,
            cantidad:         parseFloat(it.cantidad),
            precio_unitario:  parseFloat(it.precio_unitario),
            iva_porcentaje:   parseFloat(it.iva_porcentaje) || 21,
            descuento_pct:    0,
            stock_actual:     parseFloat(it.stock_actual),
            controla_stock:   !!(+it.controla_stock),
            opciones:         opciones.length ? opciones : undefined,
            opciones_resumen: opciones.length ? opciones.map(o => (o.cantidad_elegida > 1 ? o.cantidad_elegida + 'x ' : '') + o.nombre).join(', ') : undefined,
            desde_pedido:     true,
        });
    });
    renderCarrito();

    // Cliente + Cuenta Corriente preseleccionados — el cajero revisa y cobra con el flujo normal.
    document.getElementById('pedidoClienteIdInput').value = pedido.id;
    clienteIdInput.value = pedido.cliente_id;
    clienteBuscador.value = pedido.cliente_nombre;
    btnLimpiarCliente.style.display = '';
    mostrarSaldoCuentaCorriente(pedido.cliente_id);

    // Un solo medio por defecto: Cuenta Corriente con el total completo (antes quedaba
    // Efectivo tildado de una venta anterior/por default DEMÁS de Cuenta Corriente, así que
    // se cobraba dos veces el mismo total repartido en ambos). "Dividir pago" apagado hace
    // que los checkboxes se comporten como radios (ver medioChkCambio) — el cajero lo puede
    // prender después si de verdad quiere repartir el cobro en más de un medio.
    chkDividirPago.checked = false;
    medioChks.cuenta_corriente.checked = true;
    medioChkCambio('cuenta_corriente');
}

function rechazarPedidoCliente() {
    const wrap = document.getElementById('pcRechazoWrap');
    if (wrap.classList.contains('d-none')) { wrap.classList.remove('d-none'); return; }

    const mensaje = document.getElementById('pcMensajeRechazo').value.trim();
    if (!mensaje) { mostrarErrorPedidoCliente('Escribí un mensaje para el cliente antes de rechazar.'); return; }

    (async () => {
        const fd = new FormData();
        fd.append('id', _pedidoClienteActual.id);
        fd.append('mensaje', mensaje);
        fd.append('_csrf_token', CSRF_TOKEN);
        try {
            const r = await fetch(`${BASE_URL}/pedidos-clientes/rechazar`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { mostrarErrorPedidoCliente(d.error); return; }
            _hideModal('modalPedidoCliente');
            _pedidoClienteActual = null;
        } catch { mostrarErrorPedidoCliente('Error de conexión.'); }
    })();
}

function mostrarErrorPedidoCliente(msg) {
    const el = document.getElementById('pcError');
    el.textContent = msg;
    el.classList.remove('d-none');
}

// ── Chat del pedido — no resuelve el modal bloqueante, es aparte ───────────────
async function abrirChatPedido() {
    if (!_pedidoClienteActual) return;
    await cargarChatPedido();
    _showModal('modalChatPedido');
}

async function cargarChatPedido() {
    const wrap = document.getElementById('chatMensajes');
    wrap.innerHTML = '<p class="text-muted small text-center py-3">Cargando…</p>';
    try {
        const r = await fetch(`${BASE_URL}/pedidos-clientes/chat?pedido_id=${_pedidoClienteActual.id}`);
        const d = await r.json();
        if (!d.ok || !d.mensajes.length) { wrap.innerHTML = '<p class="text-muted small text-center py-3">Sin mensajes todavía.</p>'; return; }
        wrap.innerHTML = d.mensajes.map(m => {
            const propio = m.autor_tipo === 'staff';
            return `<div class="d-flex ${propio ? 'justify-content-end' : 'justify-content-start'} mb-2">
                <div class="p-2 rounded small ${propio ? 'bg-primary text-white' : 'bg-light'}" style="max-width:80%">${esc(m.mensaje)}</div>
            </div>`;
        }).join('');
        wrap.scrollTop = wrap.scrollHeight;
    } catch {
        wrap.innerHTML = '<p class="text-danger small text-center py-3">Error al cargar el chat.</p>';
    }
}

async function enviarChatPedido() {
    const input = document.getElementById('chatInputPc');
    const mensaje = input.value.trim();
    if (!mensaje || !_pedidoClienteActual) return;
    const fd = new FormData();
    fd.append('pedido_id', _pedidoClienteActual.id);
    fd.append('mensaje', mensaje);
    fd.append('_csrf_token', CSRF_TOKEN);
    try {
        const r = await fetch(`${BASE_URL}/pedidos-clientes/chat/enviar`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) { input.value = ''; cargarChatPedido(); }
    } catch { /* el cajero puede reintentar a mano */ }
}

function renderTareaBanner(tarea) {
    _tareaActual = tarea;
    const banner = document.getElementById('tareaBanner');
    if (!tarea) { banner.classList.add('d-none'); return; }

    banner.classList.remove('d-none');
    document.getElementById('tareaBannerTitulo').textContent = '📋 ' + tarea.titulo;
    document.getElementById('tareaBannerAsignadaPor').textContent = 'Asignada por ' + (tarea.crea_nombre || '—');
    document.getElementById('tareaBannerDescripcion').textContent = tarea.descripcion || '';

    const esAceptada = tarea.estado === 'aceptada';
    document.getElementById('tareaBannerAccionesPendiente').classList.toggle('d-none', esAceptada);
    document.getElementById('tareaBannerAccionesAceptada').classList.toggle('d-none', !esAceptada);
}

async function _accionTarea(url, extraParams = {}) {
    const fd = new FormData();
    fd.append('tarea_id', _tareaActual.id);
    fd.append('_csrf_token', CSRF_TOKEN);
    Object.entries(extraParams).forEach(([k, v]) => fd.append(k, v));
    try {
        const r = await fetch(`${BASE_URL}/${url}`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
            if (typeof showToast === 'function') showToast(d.mensaje, 'success');
            cargarTareaPendiente();
        } else {
            if (typeof showToast === 'function') showToast(d.error || 'No se pudo completar.', 'danger');
            else alert(d.error || 'No se pudo completar.');
        }
    } catch {
        if (typeof showToast === 'function') showToast('Error de conexión.', 'danger');
    }
}

function aceptarTarea() { _accionTarea('tareas/aceptar'); }
function finalizarTarea() { _accionTarea('tareas/finalizar'); }
function aplazarTarea(minutos) { _accionTarea('tareas/aplazar', { minutos }); }

function abrirRechazoTarea() {
    document.getElementById('tareaMotivoRechazo').value = '';
    document.getElementById('tareaRechazoError').classList.add('d-none');
    _showModal('modalRechazarTarea');
}
async function confirmarRechazoTarea() {
    const motivo = document.getElementById('tareaMotivoRechazo').value.trim();
    if (!motivo) {
        const el = document.getElementById('tareaRechazoError');
        el.textContent = 'El motivo es obligatorio.';
        el.classList.remove('d-none');
        return;
    }
    await _accionTarea('tareas/rechazar', { motivo });
    _hideModal('modalRechazarTarea');
}

// Atajos de teclado
document.addEventListener('keydown', function(e){
    if(e.key==='F2') document.getElementById('buscadorPos').focus();
    if(e.key==='F10'&&carrito.length) document.getElementById('btnCobrar').click();
});
</script>

<style>
.h-pos { min-height: calc(100vh - 180px); }

/* Panel derecho (carrito + cobro) con scroll propio, en vez de depender del scroll de
   toda la página — en algunas pantallas/resoluciones o en la PWA el scroll del body no
   llegaba a activarse y el botón "Cobrar" quedaba tapado sin forma de alcanzarlo. Solo
   desde el breakpoint lg (992px) hacia arriba, que es cuando este panel queda al lado del
   catálogo en vez de apilado debajo — apilado (mobile/tablet chico) ya funciona bien con
   el scroll normal de la página, meterle un scroll propio ahí sería un scroll anidado
   confuso para el dedo/mouse. */
@media (min-width: 992px) {
    .pos-col-cobro { max-height: calc(100vh - 180px); overflow-y: auto; }
}

/* Inputs del panel de cobro: más contraste que el gris clarito default de Bootstrap, y
   los valores (montos, cantidades) más grandes y en negrita para leerlos de un vistazo
   parado en el mostrador. Se excluyen los .pos-input-ro (cantidad/precio de la fila del
   carrito): esos son a propósito transparentes/de solo lectura, se editan desde el modal. */
#formVenta input.form-control:not(.pos-input-ro),
#formVenta select.form-select {
    background-color: #e9ecef;
    border: 1.5px solid #adb5bd;
    color: #000;
    font-weight: 700;
    font-size: 1.02rem;
}
#formVenta input.form-control:not(.pos-input-ro):focus,
#formVenta select.form-select:focus {
    background-color: #e9ecef;
    color: #000;
}
:root[data-theme="dark"] #formVenta input.form-control:not(.pos-input-ro),
:root[data-theme="dark"] #formVenta select.form-select {
    background-color: #343a40;
    border-color: #6c757d;
    color: #f1f1f1;
}
:root[data-theme="dark"] #formVenta input.form-control:not(.pos-input-ro):focus,
:root[data-theme="dark"] #formVenta select.form-select:focus {
    background-color: #343a40;
    color: #f1f1f1;
}

/* Botones de producto del catálogo: btn-outline-secondary quedaba casi transparente
   (borde tenue, sin fondo), con poco contraste contra el fondo de la vista. */
.catalogo-item {
    background-color: #eef0f3;
    border: 1.5px solid #adb5bd;
}
.catalogo-item .catalogo-item-nombre { color: #000; }
/* Los agotados (.text-muted en el botón) siguen viéndose apagados a propósito. */
.catalogo-item.text-muted .catalogo-item-nombre { color: inherit; }
:root[data-theme="dark"] .catalogo-item {
    background-color: #2b3035;
    border-color: #495057;
}
:root[data-theme="dark"] .catalogo-item .catalogo-item-nombre { color: #e9ecef; }
:root[data-theme="dark"] .catalogo-item.text-muted .catalogo-item-nombre { color: inherit; }

/* Cada producto disponible se pinta con un color pastel distinto (mismo criterio que las
   categorías: clase prod-color-1..8 cíclica asignada en mostrarCatalogo()), con hover
   llamativo en la versión saturada del mismo color. Los agotados se quedan sin color
   (siguen grises/apagados a propósito, ver reglas de arriba). */
.prod-color-1 { --prod-bg:#fbdce6; --prod-vivo:#e64980; --prod-text:#7a1f42; }
.prod-color-2 { --prod-bg:#fde3cf; --prod-vivo:#e8590c; --prod-text:#7a3d0f; }
.prod-color-3 { --prod-bg:#fdf3c7; --prod-vivo:#d4a017; --prod-text:#6b5a12; }
.prod-color-4 { --prod-bg:#d3f5dc; --prod-vivo:#2f9e44; --prod-text:#1f5c22; }
.prod-color-5 { --prod-bg:#cdf5f0; --prod-vivo:#0ca678; --prod-text:#0b5548; }
.prod-color-6 { --prod-bg:#d3ecfb; --prod-vivo:#1971c2; --prod-text:#123a52; }
.prod-color-7 { --prod-bg:#e5dbf8; --prod-vivo:#7048e8; --prod-text:#4b2e73; }
.prod-color-8 { --prod-bg:#f6d6f0; --prod-vivo:#a61e4d; --prod-text:#6b1a3d; }
#catalogoResultados .btn.catalogo-item[class*="prod-color-"],
#opcionesGruposWrap .catalogo-item[class*="prod-color-"] {
    background-color: var(--prod-bg);
    border: 1.5px solid var(--prod-vivo);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"] .catalogo-item-nombre,
#opcionesGruposWrap .catalogo-item[class*="prod-color-"] .catalogo-item-nombre {
    color: var(--prod-text);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:not(.disabled):hover,
#opcionesGruposWrap .btn.catalogo-item[class*="prod-color-"]:not(.opcion-elegida):hover {
    background-color: var(--prod-vivo);
    border-color: var(--prod-vivo);
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 .35rem .9rem rgba(0,0,0,.28);
}
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:not(.disabled):hover .catalogo-item-nombre,
#catalogoResultados .btn.catalogo-item[class*="prod-color-"]:not(.disabled):hover .text-muted,
#opcionesGruposWrap .btn.catalogo-item[class*="prod-color-"]:not(.opcion-elegida):hover .catalogo-item-nombre {
    color: #fff !important;
}

/* Opción elegida dentro del modal de opciones del POS (ver renderGruposOpciones()):
   queda en la versión saturada del color de forma persistente, no solo al pasar el
   mouse, para que se note de un vistazo cuáles están tildadas — incluye la variante
   "cantidad con repetición", que es un <div>, no un <button>. */
#opcionesGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] {
    background-color: var(--prod-vivo);
    border-color: var(--prod-vivo);
}
#opcionesGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] .catalogo-item-nombre,
#opcionesGruposWrap .catalogo-item.opcion-elegida[class*="prod-color-"] .small {
    color: #fff !important;
}

/* Opción sin stock dentro del modal de opciones: gris más oscuro que el default de
   .catalogo-item (que queda casi blanco/muy sutil), para que se distinga a simple
   vista de las que sí tienen color asignado. */
#opcionesGruposWrap .catalogo-item.opcion-agotada {
    background-color: #ced4da;
    border-color: #868e96;
}
:root[data-theme="dark"] #opcionesGruposWrap .catalogo-item.opcion-agotada {
    background-color: #16181a;
    border-color: #343a40;
}

/* Checkbox "circular" para elegir qué medios de pago participan de la venta (se puede
   tildar más de uno — no son radios). appearance:none + border-radius:50% en vez del
   checkbox cuadrado default del navegador. */
.medio-pago-chk {
    appearance: none; -webkit-appearance: none;
    width: 1rem; height: 1rem;
    border: 2px solid var(--bs-border-color, #adb5bd);
    border-radius: 50%;
    cursor: pointer;
    flex-shrink: 0;
    margin: 0;
    position: relative;
}
.medio-pago-chk:checked {
    background-color: var(--bs-primary, #0d6efd);
    border-color: var(--bs-primary, #0d6efd);
}
.medio-pago-chk:checked::after {
    content: '';
    position: absolute; top: 50%; left: 50%;
    width: .35rem; height: .35rem;
    background: #fff; border-radius: 50%;
    transform: translate(-50%, -50%);
}
/* Cada medio de pago es un bloque de un solo color pastel (checkbox+etiqueta+importe
   comparten el mismo fondo) en vez de colorear solo el input de abajo — se define una vez
   por variables CSS para que la tarjeta y el input queden exactamente iguales. Efectivo es
   verde franco (no verde-azulado) para no confundirse con el celeste de Mercado Pago. */
.medio-card-efectivo      { --medio-bg: #c9f2c0; --medio-border: #5fb864; --medio-text: #1f5c22; }
.medio-card-debito        { --medio-bg: #e5dbf8; --medio-border: #b092dd; --medio-text: #4b2e73; }
.medio-card-credito       { --medio-bg: #fdf3c7; --medio-border: #e3c34c; --medio-text: #6b5a12; }
.medio-card-transferencia { --medio-bg: #fde3cf; --medio-border: #e8a86c; --medio-text: #7a3d0f; }
.medio-card-mercadopago   { --medio-bg: #d2ecfb; --medio-border: #7ec2e8; --medio-text: #123a52; }

.medio-card {
    background-color: var(--medio-bg);
    border-radius: .6rem;
    padding: .4rem .5rem;
    /* Apagado cuando su checkbox no está tildado, para que se note cuál medio participa
       de la venta sin necesidad de JS extra (reacciona solo al :checked). */
    opacity: .55;
    transition: opacity .15s ease;
}
.medio-card:has(.medio-pago-chk:checked) { opacity: 1; }
.medio-card-label { font-weight: 600; color: var(--medio-text); }
#formVenta .medio-card .input-group-text,
#formVenta .medio-card .monto-medio,
#formVenta .medio-card .monto-medio:focus {
    background-color: var(--medio-bg);
    border-color: var(--medio-border);
    color: var(--medio-text);
}
/* Un input disabled no genera click/foco en el navegador — se lo "atraviesa" para que el
   click llegue igual al .input-group que lo envuelve y ese, con un solo click, active el
   medio (ver el listener de montoInputs en el <script>). */
#formVenta .medio-card .monto-medio:disabled { pointer-events: none; cursor: pointer; }
#formVenta .medio-card .input-group-text { cursor: pointer; }

/* Mercado Pago no tiene un emoji propio — se representa con una pastilla en su celeste
   de marca en vez de usar su isotipo real. */
.badge-mp {
    display: inline-block;
    background: #00aaff;
    color: #fff;
    font-size: .6rem;
    font-weight: 700;
    line-height: 1;
    padding: .2rem .3rem;
    border-radius: .25rem;
}

/* Resultado de CAE/AFIP en el modal de venta registrada: el CAE es un número largo
   que sin esto desborda el ancho angosto del modal-sm */
#afipCaeWrap { overflow-wrap: anywhere; word-break: break-word; }
#afipCaeWrap code { word-break: break-all; }
#tblCarrito td { vertical-align: middle; padding: .4rem .35rem; }
#tblCarrito input.form-control-sm { padding: .15rem .3rem; margin: 0; }
/* thead.table-light: la letra queda oscura en modo claro y gris claro en modo oscuro —
   se fija explícito en vez de depender de --bs-table-color (Dashly no siempre lo toma). */
#tblCarrito thead th {
    color: #1f2937;
    background-color: #eef1f4;
    border-color: #d5d9de;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    position: sticky;
    top: 0;
    z-index: 1;
}
:root[data-theme="dark"] #tblCarrito thead th {
    color: #e9edef;
    background-color: #2a323c;
    border-color: #3a4450;
}
#tblCarrito tbody tr:hover { background-color: rgba(13,110,253,.06); }
:root[data-theme="dark"] #tblCarrito tbody tr:hover { background-color: rgba(255,255,255,.05); }

/* Dropdown de búsqueda de cliente: fondo sólido para que no se mezcle con la página */
#clienteDropdown {
    background-color: var(--bs-dropdown-bg, #fff) !important;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: .5rem;
    overflow-x: hidden;
}
#clienteDropdown .list-group-item {
    background-color: var(--bs-dropdown-bg, #fff) !important;
    color: var(--bs-dropdown-color, #212529) !important;
    border-color: var(--bs-border-color, #dee2e6) !important;
}
#clienteDropdown .list-group-item * {
    color: inherit !important;
}
#clienteDropdown .list-group-item .text-muted {
    opacity: .75;
}
#clienteDropdown .list-group-item:hover,
#clienteDropdown .list-group-item:focus {
    background-color: var(--bs-dropdown-link-hover-bg, #f0f0f0) !important;
}

/* Cantidad, precio unitario y descuento: solo informativos, se editan desde el modal "Editar" */
.pos-input-ro {
    border-color: transparent;
    background-color: transparent;
    pointer-events: none;
}
.pos-input-ro::-webkit-outer-spin-button,
.pos-input-ro::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.pos-input-ro[type=number] { -moz-appearance: textfield; }

/* Buscador de productos: era un input-group transparente/gris que pasaba desapercibido
   arriba de todo — ahora tiene un ícono en un chip de color sólido y un anillo alrededor
   para que se note que es el punto de entrada principal para buscar. */
.buscador-pos-llamativo {
    border-radius: .6rem;
    overflow: hidden;
    box-shadow: 0 0 0 2px var(--bs-primary, #0d6efd);
}
.buscador-pos-llamativo .input-group-text {
    background-color: var(--bs-primary, #0d6efd);
    color: #fff;
    border: none;
}
.buscador-pos-llamativo .form-control {
    border: none;
    background-color: #eaf1ff;
    font-weight: 600;
    color: #0b2e6b;
}
.buscador-pos-llamativo .form-control:focus {
    background-color: #eaf1ff;
    color: #0b2e6b;
    box-shadow: none;
}
:root[data-theme="dark"] .buscador-pos-llamativo .form-control {
    background-color: #1c2b4a;
    color: #eaf1ff;
}
:root[data-theme="dark"] .buscador-pos-llamativo .form-control:focus {
    background-color: #1c2b4a;
    color: #eaf1ff;
}

/* Paneles de categorías y catálogo: fondos distintos entre sí, para que se distingan como
   dos zonas separadas en vez de una sola mancha gris continua. El de catálogo se deja
   neutro (ni pastel ni parecido a ninguno de los 8 colores de prod-color-N) a propósito:
   si tuviera un color de la misma familia, algún botón de producto podría terminar
   confundiéndose con el fondo que tiene detrás. */
.pos-categorias-panel { background-color: #f2edfa; }
.pos-catalogo-panel   { background-color: #f4f5f7; }
:root[data-theme="dark"] .pos-categorias-panel { background-color: #2a2440; }
:root[data-theme="dark"] .pos-catalogo-panel   { background-color: #26292d; }

/* Categorías como "píldoras" separadas en vez de una lista con líneas divisorias pegada
   borde a borde (look de list-group-flush por defecto). */
#categoriasLista { border: 0; background: transparent; }
#categoriasLista .list-group-item {
    transition: background-color .15s ease, color .15s ease, transform .1s ease;
    cursor: pointer;
    border: 0 !important;
    border-radius: .6rem !important;
    margin: .25rem .4rem;
    padding: .5rem .65rem;
}
/* Cada categoría se pinta con un color pastel distinto (asignado por índice en
   renderCategoriasLista(), clase cat-color-1..8, cíclica) — con hover y "activa" en la
   versión saturada del mismo color en vez del celeste genérico de antes, para que llame
   la atención y a la vez se note a qué categoría pertenece. */
#categoriasLista .list-group-item:hover {
    background-color: var(--bs-primary);
    color: #fff;
    transform: translateX(2px);
}
#categoriasLista .list-group-item:hover .badge {
    background-color: rgba(255,255,255,.25) !important;
    color: #fff !important;
}
#categoriasLista .list-group-item.active {
    border-radius: .6rem !important;
}
#categoriasLista .list-group-item.active:hover {
    background-color: var(--bs-primary);
    filter: brightness(1.1);
}
.cat-color-1 { --cat-bg:#fbdce6; --cat-vivo:#e64980; --cat-text:#7a1f42; }
.cat-color-2 { --cat-bg:#fde3cf; --cat-vivo:#e8590c; --cat-text:#7a3d0f; }
.cat-color-3 { --cat-bg:#fdf3c7; --cat-vivo:#d4a017; --cat-text:#6b5a12; }
.cat-color-4 { --cat-bg:#d3f5dc; --cat-vivo:#2f9e44; --cat-text:#1f5c22; }
.cat-color-5 { --cat-bg:#cdf5f0; --cat-vivo:#0ca678; --cat-text:#0b5548; }
.cat-color-6 { --cat-bg:#d3ecfb; --cat-vivo:#1971c2; --cat-text:#123a52; }
.cat-color-7 { --cat-bg:#e5dbf8; --cat-vivo:#7048e8; --cat-text:#4b2e73; }
.cat-color-8 { --cat-bg:#f6d6f0; --cat-vivo:#a61e4d; --cat-text:#6b1a3d; }
#categoriasLista .list-group-item[class*="cat-color-"] {
    background-color: var(--cat-bg);
    /* Texto parejo (no el --cat-text propio de cada color) — negro en claro, gris claro en
       oscuro, mismo criterio que .pos-resumen-label más abajo. El fondo pastel de la
       categoría sigue distinguiendo una de otra; hover/activa siguen en blanco (ver reglas
       de abajo), esto solo pisa el estado base sin interactuar. */
    color: #000;
}
:root[data-theme="dark"] #categoriasLista .list-group-item[class*="cat-color-"] {
    color: #dee2e6;
}
#categoriasLista .list-group-item[class*="cat-color-"]:hover {
    background-color: var(--cat-vivo);
    color: #fff;
    transform: translateX(2px) scale(1.03);
    box-shadow: 0 .2rem .5rem rgba(0,0,0,.2);
}
#categoriasLista .list-group-item[class*="cat-color-"].active,
#categoriasLista .list-group-item[class*="cat-color-"].active:hover {
    background-color: var(--cat-vivo);
    color: #fff;
    filter: none;
    box-shadow: 0 0 0 2px var(--cat-vivo), 0 .2rem .5rem rgba(0,0,0,.25);
}

#catalogoResultados .btn {
    transition: transform .1s ease, box-shadow .15s ease, border-color .15s ease;
}
#catalogoResultados .btn:not(.disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 .25rem .75rem rgba(0,0,0,.25);
    border-color: var(--bs-primary);
    z-index: 1;
}

/* "Subtotal sin IVA" / "IVA" / "Descuento": negro en claro, gris claro en oscuro (en vez
   del .text-muted default, que quedaba muy apagado). */
.pos-resumen-label { color: #000; }
:root[data-theme="dark"] .pos-resumen-label { color: #dee2e6; }
</style>
