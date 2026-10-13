<!-- ===== DOCUMENTACIÓN DEL SISTEMA ===== -->
<style>
.doc-section { scroll-margin-top: 80px; }
.doc-nav .nav-link { color: #555; font-size: .85rem; padding: .3rem .75rem; border-left: 2px solid transparent; }
.doc-nav .nav-link:hover, .doc-nav .nav-link.active { color: var(--bs-primary); border-left-color: var(--bs-primary); background: transparent; }
.doc-nav .nav-group-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: #999; padding: .5rem .75rem .1rem; font-weight: 600; }
#docsSearch:focus { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
mark { background: #ffe066; padding: 0 2px; border-radius: 2px; }
.drawio-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.25rem; background: #fafafa; }
.env-key { font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: .85rem; }
.api-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .78rem; font-weight: 600; }
table.doc-table td, table.doc-table th { padding: .45rem .75rem; vertical-align: middle; font-size: .87rem; }
.section-icon { width: 28px; height: 28px; }
pre.code-block { background: #1e1e2e; color: #cdd6f4; border-radius: 6px; padding: 1rem; font-size: .82rem; overflow-x: auto; }
.search-highlight-bar { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: .4rem .75rem; font-size: .85rem; }
</style>

<div class="d-flex align-items-start justify-content-between mb-3">
    <div>
        <h1 class="h3 fw-bold mb-0">Documentación del Sistema</h1>
        <p class="text-muted small mb-0">Dewoc Admin Comercio · v1.0.0 · PHP 8.0+ MVC</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('docs/descargar?file=arquitectura.drawio') ?>" class="btn btn-sm btn-outline-secondary" title="Descargar diagrama de arquitectura">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            arquitectura.drawio
        </a>
        <a href="<?= url('docs/descargar?file=flujo_usuario.drawio') ?>" class="btn btn-sm btn-outline-secondary" title="Descargar diagrama de flujo">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            flujo_usuario.drawio
        </a>
    </div>
</div>

<!-- Search bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="flex-grow-1 position-relative">
                <svg class="position-absolute" style="left:12px;top:50%;transform:translateY(-50%);opacity:.5" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="docsSearch" class="form-control ps-5" placeholder="Buscar en la documentación… (módulos, archivos, APIs, configuración)">
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="btnClearSearch" style="display:none" onclick="clearSearch()">✕ Limpiar</button>
            <span id="searchCounter" class="text-muted small text-nowrap"></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- ===== LEFT NAV ===== -->
    <div class="col-lg-2 d-none d-lg-block">
        <nav class="doc-nav sticky-top" style="top:72px">
            <div class="doc-nav-group-label">Sistema</div>
            <a class="nav-link" href="#sistema">Vista General</a>
            <a class="nav-link" href="#modulos">Módulos</a>

            <div class="doc-nav-group-label mt-2">Guía de Usuario</div>
            <a class="nav-link" href="#guia-pos">POS / Ventas</a>
            <a class="nav-link" href="#guia-ecommerce">Ecommerce</a>
            <a class="nav-link" href="#guia-stock">Stock</a>
            <a class="nav-link" href="#guia-caja">Caja</a>
            <a class="nav-link" href="#guia-compras">Compras</a>
            <a class="nav-link" href="#guia-admin">Admin</a>

            <div class="doc-nav-group-label mt-2">Técnica</div>
            <a class="nav-link" href="#arquitectura">Arquitectura MVC</a>
            <a class="nav-link" href="#infraestructura">Infraestructura</a>
            <a class="nav-link" href="#herramientas">Herramientas</a>
            <a class="nav-link" href="#base-datos">Base de Datos</a>

            <div class="doc-nav-group-label mt-2">Integraciones</div>
            <a class="nav-link" href="#api-mp">Mercado Pago</a>
            <a class="nav-link" href="#api-afip">AFIP</a>
            <a class="nav-link" href="#api-claude">Chat IA</a>
            <a class="nav-link" href="#api-email">Email</a>
            <a class="nav-link" href="#api-env">Variables .env</a>

            <div class="doc-nav-group-label mt-2">Diagramas</div>
            <a class="nav-link" href="#diagramas">Ver diagramas</a>
        </nav>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="col-lg-10">
        <div id="noResultsMsg" class="alert alert-warning d-none">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Sin resultados para tu búsqueda. Probá con otras palabras.
        </div>

        <!-- ============================= -->
        <!-- SECCIÓN: SISTEMA              -->
        <!-- ============================= -->
        <section id="sistema" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-primary-soft text-primary me-2">1</span>Vista General del Sistema
            </h2>
            <p><strong>Dewoc Admin Comercio</strong> es un sistema web de administración integral para comercios argentinos. Incluye gestión de ventas (POS), stock, compras, caja, producción, ecommerce con Mercado Pago, facturación electrónica AFIP y un asistente de IA.</p>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold">Stack tecnológico</h6>
                            <ul class="small mb-0 ps-3">
                                <li>PHP 8.0+ (sin frameworks)</li>
                                <li>MySQL 5.7+ / 8.0</li>
                                <li>Apache (XAMPP)</li>
                                <li>HTML5 · CSS3 · JS (vanilla)</li>
                                <li>Bootstrap 5 (Dashly 1.6.0)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold">Integraciones externas</h6>
                            <ul class="small mb-0 ps-3">
                                <li>Mercado Pago (Checkout Pro + Bricks)</li>
                                <li>AFIP WSFE (factura electrónica)</li>
                                <li>Anthropic Claude (Chat IA + MCP)</li>
                                <li>Email SMTP / Sendmail</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold">Seguridad</h6>
                            <ul class="small mb-0 ps-3">
                                <li>PDO prepared statements</li>
                                <li>CSRF tokens en todos los POST</li>
                                <li>Bcrypt cost 12 (contraseñas)</li>
                                <li>Escape HTML con <code>e()</code></li>
                                <li>Permisos por módulo (rol)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MÓDULOS -->
        <section id="modulos" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-primary-soft text-primary me-2">2</span>Módulos del Sistema
            </h2>
            <div class="table-responsive">
                <table class="table table-hover doc-table">
                    <thead class="table-light">
                        <tr><th>#</th><th>Módulo</th><th>Descripción</th><th>URL</th><th>Rol mínimo</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>0</td><td><strong>Dashboard</strong></td><td>KPIs del día, ventas últimos 7 días, top productos, alertas stock</td><td><code>/</code> o <code>/dashboard</code></td><td>cualquier rol</td></tr>
                        <tr><td>1</td><td><strong>POS / Ventas</strong></td><td>Punto de venta fullscreen, carrito, descuentos, AFIP, ticket, comanda</td><td><code>/ventas/pos</code></td><td>vendedor</td></tr>
                        <tr><td>2</td><td><strong>Caja y Turnos</strong></td><td>Apertura/cierre de turno, movimientos, saldo, historial</td><td><code>/caja</code></td><td>cajero</td></tr>
                        <tr><td>3</td><td><strong>Facturas de Compra</strong></td><td>Carga de facturas, actualización de stock, CPP, PDF</td><td><code>/compras</code></td><td>deposito</td></tr>
                        <tr><td>4</td><td><strong>Proveedores</strong></td><td>ABM proveedores, CUIT, CBU, historial de compras</td><td><code>/proveedores</code></td><td>deposito</td></tr>
                        <tr><td>5</td><td><strong>Stock</strong></td><td>Ajustes, inventario físico, apertura inicial, reporte comparativo</td><td><code>/stock</code></td><td>deposito</td></tr>
                        <tr><td>6</td><td><strong>Recetas / Producción</strong></td><td>Recetas con ingredientes, producción, órdenes, versiones</td><td><code>/recetas</code></td><td>deposito</td></tr>
                        <tr><td>7</td><td><strong>Productos</strong></td><td>ABM, imagen, QR/barcode, stock mínimo, IVA, ecommerce</td><td><code>/productos</code></td><td>deposito</td></tr>
                        <tr><td>8</td><td><strong>Materias Primas</strong></td><td>ABM, historial de precios, stock mínimo</td><td><code>/materias-primas</code></td><td>deposito</td></tr>
                        <tr><td>9</td><td><strong>Insumos</strong></td><td>ABM de insumos (no llegan a recetas)</td><td><code>/insumos</code></td><td>deposito</td></tr>
                        <tr><td>10</td><td><strong>Clientes</strong></td><td>ABM, búsqueda AJAX, historial ventas, CUIT/DNI</td><td><code>/clientes</code></td><td>vendedor</td></tr>
                        <tr><td>11</td><td><strong>Reportes</strong></td><td>Ventas, compras, stock, caja, producción · Excel/PDF</td><td><code>/reportes/*</code></td><td>cualquier rol</td></tr>
                        <tr><td>12</td><td><strong>Ecommerce Admin</strong></td><td>Pedidos online, cambio de estado, detalle</td><td><code>/ecommerce/pedidos</code></td><td>vendedor</td></tr>
                        <tr><td>13</td><td><strong>Chat IA</strong></td><td>Preguntas en lenguaje natural → SQL → resultados</td><td><code>/chat</code></td><td>cualquier rol</td></tr>
                        <tr><td>14</td><td><strong>Usuarios</strong></td><td>ABM, foto de perfil, cambio de contraseña</td><td><code>/usuarios</code></td><td>admin</td></tr>
                        <tr><td>15</td><td><strong>Permisos</strong></td><td>Matriz Ver/Editar por rol × módulo</td><td><code>/permisos</code></td><td>admin</td></tr>
                        <tr><td>16</td><td><strong>Audit Log</strong></td><td>Registro de acciones críticas con usuario/fecha/IP</td><td><code>/audit-log</code></td><td>admin</td></tr>
                        <tr><td>17</td><td><strong>Backup BD</strong></td><td>Dump SQL descargable, sin exec(), 100% PHP</td><td><code>/admin/backup</code></td><td>admin</td></tr>
                        <tr><td>18</td><td><strong>Documentación</strong></td><td>Esta página (guía, arquitectura, APIs, diagramas)</td><td><code>/docs</code></td><td>cualquier rol</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: GUÍA DE USUARIO      -->
        <!-- ============================= -->

        <section id="guia-pos" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-success-soft text-success me-2">POS</span>Guía: Punto de Venta (POS)
            </h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-semibold">Flujo básico de una venta</h6>
                    <ol class="small">
                        <li>Ir a <strong>Ventas → Punto de Venta</strong></li>
                        <li>Buscar producto por nombre o código (también <kbd>F2</kbd> para enfocar el buscador)</li>
                        <li>Hacer clic en el producto o presionar <kbd>Enter</kbd> para agregarlo al carrito</li>
                        <li>Ajustar cantidad y descuento por ítem si corresponde</li>
                        <li>(Opcional) Seleccionar cliente por nombre/email/CUIT</li>
                        <li>(Opcional) Marcar <strong>"Facturar por AFIP"</strong> si se requiere factura electrónica</li>
                        <li>Seleccionar método de pago y presionar <kbd>F10</kbd> o <strong>Cobrar</strong></li>
                        <li>El modal post-venta muestra el CAE (si AFIP) y links a ticket/comanda</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold">Funciones disponibles en POS</h6>
                    <ul class="small">
                        <li><strong>Descuento global %</strong> — aplica sobre el total del carrito</li>
                        <li><strong>Mesa / Destino</strong> — campo para mesas o delivery, aparece en ticket y comanda</li>
                        <li><strong>Métodos de pago</strong>: efectivo (muestra vuelto), débito, crédito, transferencia, Mercado Pago</li>
                        <li><strong>Ticket 80mm</strong> — impresión automática con <code>window.print()</code></li>
                        <li><strong>Comanda</strong> — muestra cantidades grandes para cocina</li>
                        <li><strong>WhatsApp</strong> — comparte ticket como texto formateado con código de país Argentina</li>
                        <li><strong>AFIP CAE</strong> — si configurado, aparece en ticket, modal y vista de detalle</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="guia-ecommerce" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-primary-soft text-primary me-2">🛒</span>Guía: Ecommerce Público
            </h2>
            <p class="small text-muted">La tienda pública no requiere login. Está disponible en <code>/tienda</code>.</p>
            <div class="row g-3">
                <div class="col-md-5">
                    <h6 class="fw-semibold">Flujo del cliente</h6>
                    <ol class="small">
                        <li>Accede a <code>/tienda</code> y navega el catálogo</li>
                        <li>Filtra por categoría o búsqueda de texto</li>
                        <li>Agrega productos al carrito (persistido en <strong>localStorage</strong>)</li>
                        <li>En el carrito llena sus datos (nombre, email, teléfono, dirección)</li>
                        <li>Click <strong>"Confirmar y pagar"</strong> → crea pedido → redirige a página de pago</li>
                        <li>Paga con <strong>tarjeta inline (Bricks)</strong> o botón fallback <strong>Mercado Pago Pro</strong></li>
                        <li>MP envía Webhook → sistema descuenta stock y envía email de confirmación</li>
                    </ol>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-semibold">Gestión de pedidos (admin)</h6>
                    <p class="small">En <code>/ecommerce/pedidos</code> el admin puede:</p>
                    <ul class="small">
                        <li>Ver todos los pedidos con filtros de fecha y estado</li>
                        <li>Ver detalle: productos, datos del cliente, pago MP</li>
                        <li>Cambiar estado: <code>pendiente → en preparación → enviado → entregado</code></li>
                    </ul>
                    <h6 class="fw-semibold mt-2">Para que aparezcan productos en la tienda</h6>
                    <ul class="small">
                        <li>En <code>/productos/editar</code>: activar checkbox <strong>"Visible en ecommerce"</strong></li>
                        <li>El producto debe tener imagen, precio, categoría y <strong>activo = sí</strong></li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="guia-stock" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-warning-soft text-warning me-2">📦</span>Guía: Control de Stock
            </h2>
            <div class="row g-3">
                <div class="col-md-4"><h6 class="fw-semibold">Ajuste manual</h6><p class="small">En <code>/stock</code>, botón <strong>Ajuste</strong>: ingresar cantidad positiva (entrada) o negativa (salida) con motivo. El movimiento queda registrado en <code>stock_movimientos</code>.</p></div>
                <div class="col-md-4"><h6 class="fw-semibold">Inventario físico</h6><p class="small">En <code>/stock/inventario</code>: ingresar la cantidad física contada por ítem. El sistema calcula la diferencia vs. stock teórico y aplica el ajuste en lote al confirmar.</p></div>
                <div class="col-md-4"><h6 class="fw-semibold">Apertura inicial</h6><p class="small">En <code>/stock/apertura</code>: muestra solo ítems con stock ≤ 0. Ideal para primer uso del sistema. Ingresar stock inicial de cada ítem.</p></div>
                <div class="col-md-6"><h6 class="fw-semibold">Reporte comparativo</h6><p class="small">En <code>/stock/reporte</code>: compara stock teórico vs. físico con KPIs, DataTable exportable y gráfico de evolución por ítem (Chart.js, AJAX a <code>/stock/historial-item</code>).</p></div>
                <div class="col-md-6"><h6 class="fw-semibold">Movimientos automáticos</h6><p class="small">El stock se descuenta automáticamente al: registrar una venta, registrar producción (MP e insumos), confirmar un pedido ecommerce. Se suma al recibir facturas de compra.</p></div>
            </div>
        </section>

        <section id="guia-caja" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-success-soft text-success me-2">💰</span>Guía: Caja y Turnos
            </h2>
            <ol class="small">
                <li><strong>Abrir turno</strong> en <code>/caja</code>: ingresar monto inicial y observaciones. Solo puede haber 1 turno abierto por usuario.</li>
                <li>Las ventas en <strong>efectivo</strong> se registran automáticamente como ingresos del turno activo.</li>
                <li><strong>Movimientos manuales</strong>: ingresar o egresar dinero con descripción (ej: "retiro", "fondo de caja").</li>
                <li><strong>Cerrar turno</strong>: ingresar monto declarado → el sistema calcula la diferencia (sobrante/faltante) y archiva el turno.</li>
                <li><strong>Historial</strong> en <code>/caja/historial</code> con reporte filtrable por fecha.</li>
            </ol>
        </section>

        <section id="guia-compras" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-secondary-soft text-secondary me-2">🧾</span>Guía: Facturas de Compra
            </h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <ol class="small">
                        <li>Ir a <code>/compras/nueva</code></li>
                        <li>Seleccionar proveedor, nro. comprobante, fecha, tipo (A/B/C/ticket)</li>
                        <li>Buscar ítems: Materia Prima, Insumo o Producto para reventa</li>
                        <li>Ingresar cantidad, precio unitario e IVA por ítem</li>
                        <li>Guardar → el sistema suma stock y actualiza <strong>costo unitario promedio ponderado (CPP)</strong></li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <ul class="small">
                        <li><strong>Anulación</strong>: revierte el stock descontado; no recalcula CPP</li>
                        <li><strong>Validación de duplicado</strong>: misma factura (proveedor + nro.) no se puede cargar dos veces</li>
                        <li><strong>PDF individual</strong>: en detalle → botón "Imprimir / PDF" abre vista A4 standalone</li>
                        <li><strong>Historial por proveedor</strong>: en <code>/proveedores</code> → botón "Ver compras" filtra las facturas de ese proveedor</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="guia-admin" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-danger-soft text-danger me-2">⚙️</span>Guía: Administración
            </h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <h6 class="fw-semibold">Gestión de usuarios</h6>
                    <ul class="small">
                        <li>Crear usuario con rol: admin / cajero / deposito / vendedor</li>
                        <li>Activar / desactivar sin eliminar</li>
                        <li>Resetear contraseña por email</li>
                        <li>Foto de perfil (resize automático via GD)</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-semibold">Permisos por módulo</h6>
                    <p class="small">En <code>/permisos</code>: matriz Ver/Editar por rol × módulo. Si un rol no tiene ningún permiso configurado, tiene acceso total (backward compat). El rol admin siempre tiene acceso completo.</p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-semibold">Backup y auditoría</h6>
                    <ul class="small">
                        <li><code>/admin/backup</code>: genera dump SQL descargable (100% PHP, sin exec)</li>
                        <li><code>/audit-log</code>: log de acciones críticas con usuario, fecha e IP</li>
                        <li>Acciones auditadas: login, cambio de contraseña, guardar permisos, backup, etc.</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: ARQUITECTURA         -->
        <!-- ============================= -->
        <section id="arquitectura" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-info-soft text-info me-2">🏗</span>Arquitectura MVC
            </h2>
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-semibold">Patrón MVC (sin frameworks)</h6>
                    <p class="small">El flujo de una petición HTTP es:</p>
                    <pre class="code-block">HTTP Request
  ↓
.htaccess  →  RewriteRule → index.php?url=...
  ↓
index.php  →  Router::dispatch($url, $method)
  ↓
Controller →  requireAuth() / requireRole()
              CSRF::check() (en POST)
  ↓
Model      →  Database (PDO Singleton)
  ↓
View       →  render('modulo.vista', $data)
              → capture(view) → inject in layout</pre>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold">Estructura de directorios</h6>
                    <pre class="code-block">dewoc-admin/
├── app/
│   ├── Controllers/   # Un controller por módulo
│   ├── Models/        # Un model por entidad
│   └── Views/         # Carpetas por módulo
│       └── layouts/   # main.php, tienda.php
├── config/
│   └── config.php     # Constantes + parser .env
├── core/              # Router, Session, Csrf,
│   └── ...            # Database, AfipWsaa, AfipWsfe
├── database/
│   ├── schema.sql     # Schema completo
│   └── seed.sql       # Datos iniciales
├── docs/              # CHECKLIST, RUTAS, diagramas
├── public/ / assets/  # CSS, JS, imágenes
├── storage/
│   ├── afip/          # cert.pem, key.pem, ta_cache
│   └── logs/          # php_errors.log
├── tests/             # TestCase + suites
├── uploads/           # Imágenes subidas por usuarios
├── .env               # Credenciales (no en git)
├── .env.example       # Plantilla documentada
├── index.php          # Entry point + todas las rutas
└── sw.js              # Service Worker PWA</pre>
                </div>
            </div>

            <h6 class="fw-semibold mt-3">Clases core clave</h6>
            <div class="table-responsive">
                <table class="table table-sm doc-table">
                    <thead class="table-light"><tr><th>Archivo</th><th>Clase</th><th>Responsabilidad</th></tr></thead>
                    <tbody>
                        <tr><td><code>core/Database.php</code></td><td>Database</td><td>PDO Singleton — única conexión compartida en toda la request</td></tr>
                        <tr><td><code>core/Router.php</code></td><td>Router</td><td>Registro de rutas GET/POST, dispatch por URL + método HTTP</td></tr>
                        <tr><td><code>core/Session.php</code></td><td>Session</td><td>Inicio seguro, regenerate_id en login, datos de usuario en <code>$_SESSION</code></td></tr>
                        <tr><td><code>core/Csrf.php</code></td><td>Csrf</td><td>Genera y valida tokens anti-CSRF. <code>Csrf::check()</code> en todo POST</td></tr>
                        <tr><td><code>core/helpers.php</code></td><td>—</td><td><code>e()</code>, <code>url()</code>, <code>redirect()</code>, <code>can()</code>, <code>moneda()</code>, <code>logApp()</code></td></tr>
                        <tr><td><code>core/AfipWsaa.php</code></td><td>AfipWsaa</td><td>Firma TRA con openssl_pkcs7_sign(), obtiene TA de AFIP, caché en JSON</td></tr>
                        <tr><td><code>core/AfipWsfe.php</code></td><td>AfipWsfe</td><td>SoapClient WSFE v1 — FECompUltimoAutorizado + FECAESolicitar</td></tr>
                        <tr><td><code>core/Mailer.php</code></td><td>Mailer</td><td>Envío de emails: confirmación pedido y reset de contraseña</td></tr>
                        <tr><td><code>core/AuditLog.php</code></td><td>AuditLog</td><td>Inserta registros en <code>audit_log</code> con usuario, acción, IP</td></tr>
                        <tr><td><code>core/RememberMe.php</code></td><td>RememberMe</td><td>Cookie "recordarme" — token SHA-256, 30 días, rotación en uso</td></tr>
                        <tr><td><code>app/Models/Model.php</code></td><td>Model</td><td>Base: <code>queryAll()</code>, <code>queryOne()</code>, <code>insert()</code>, <code>update()</code></td></tr>
                        <tr><td><code>app/Controllers/Controller.php</code></td><td>Controller</td><td>Base: <code>render()</code>, <code>json()</code>, <code>requireAuth()</code>, <code>requireRole()</code></td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-semibold mt-3">Sistema de permisos</h6>
            <pre class="code-block">// can('modulo', 'accion') en controllers y vistas
can('ventas', 'ver')    // → true/false
canView('ventas')       // alias de can('ventas', 'ver')
canEdit('ventas')       // alias de can('ventas', 'editar')

// Lógica: admin → siempre true
//         permisos vacíos en DB → true (backward compat)
//         de lo contrario: $_SESSION['permisos'][modulo][accion]</pre>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: INFRAESTRUCTURA      -->
        <!-- ============================= -->
        <section id="infraestructura" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-secondary-soft text-secondary me-2">🖥</span>Infraestructura y Requisitos
            </h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-semibold">Requisitos del servidor</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td>Servidor web</td><td>Apache 2.4+ con <code>mod_rewrite</code> habilitado</td></tr>
                            <tr><td>PHP</td><td>8.0 o superior</td></tr>
                            <tr><td>Base de datos</td><td>MySQL 5.7+ o MariaDB 10.3+</td></tr>
                            <tr><td>Entorno dev</td><td>XAMPP (recomendado), Laragon, WAMP</td></tr>
                        </tbody>
                    </table>
                    <h6 class="fw-semibold mt-2">Extensiones PHP requeridas</h6>
                    <ul class="small ps-3">
                        <li><code>pdo</code> + <code>pdo_mysql</code> — conexión a BD</li>
                        <li><code>openssl</code> — firma TRA para AFIP</li>
                        <li><code>curl</code> — llamadas a APIs (MP, Claude)</li>
                        <li><code>json</code> — encode/decode responses</li>
                        <li><code>mbstring</code> — manejo de UTF-8</li>
                        <li><code>gd</code> — resize de imágenes y generación de íconos PWA</li>
                        <li><code>soap</code> — SoapClient para AFIP WSAA/WSFE</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold">Instalación inicial</h6>
                    <ol class="small">
                        <li>Clonar/copiar el proyecto en <code>htdocs/</code></li>
                        <li>Copiar <code>.env.example</code> a <code>.env</code> y completar variables</li>
                        <li>Acceder a <code>/install.php</code> — crea la BD, ejecuta <code>schema.sql</code> + <code>seed.sql</code>, genera íconos PWA</li>
                        <li>Eliminar <code>install.php</code> del servidor</li>
                        <li>Login inicial: <strong>admin / admin123</strong> (cambiar inmediatamente)</li>
                    </ol>
                    <h6 class="fw-semibold mt-2">Configuración Apache (.htaccess)</h6>
                    <pre class="code-block" style="font-size:.78rem">RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]</pre>
                    <p class="small text-muted mt-1">Requiere <code>AllowOverride All</code> en el VirtualHost de Apache.</p>
                </div>
            </div>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: HERRAMIENTAS         -->
        <!-- ============================= -->
        <section id="herramientas" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-warning-soft text-warning me-2">🔧</span>Herramientas y Librerías
            </h2>
            <div class="table-responsive">
                <table class="table table-hover doc-table">
                    <thead class="table-light">
                        <tr><th>Librería / Herramienta</th><th>Versión</th><th>Uso en el sistema</th><th>Carga</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Dashly</strong></td><td>1.6.0</td><td>Tema admin — Bootstrap 5 con sidebar, cards, toasts, modos oscuro/claro</td><td>Local (<code>assets/</code>)</td></tr>
                        <tr><td><strong>Bootstrap</strong></td><td>5.x (incluido en Dashly)</td><td>Grid, componentes UI, modales, alerts, badges</td><td>Local</td></tr>
                        <tr><td><strong>DataTables</strong></td><td>1.13+</td><td>Todas las tablas admin: paginado, búsqueda, ColVis, stateSave, Excel, PDF</td><td>Local</td></tr>
                        <tr><td><strong>Chart.js</strong></td><td>4.4.0</td><td>Gráficos: ventas por día (dashboard), evolución de stock, reportes</td><td>CDN + local</td></tr>
                        <tr><td><strong>JsBarcode</strong></td><td>latest</td><td>Generación de códigos de barra por producto (<code>/productos/qr</code>)</td><td>CDN</td></tr>
                        <tr><td><strong>qrcode.js</strong></td><td>latest</td><td>Generación de QR por producto</td><td>CDN</td></tr>
                        <tr><td><strong>MP Bricks SDK</strong></td><td>v2</td><td>Checkout API — CardPayment brick en la tienda pública</td><td>CDN (sdk.mercadopago.com)</td></tr>
                        <tr><td><strong>GD (PHP)</strong></td><td>builtin</td><td>Resize de imágenes subidas, generación de íconos PWA (192/512px)</td><td>Extensión PHP</td></tr>
                        <tr><td><strong>SoapClient (PHP)</strong></td><td>builtin</td><td>Comunicación con AFIP WSAA (loginCms) y WSFE (FECAESolicitar)</td><td>Extensión PHP</td></tr>
                        <tr><td><strong>PDO + pdo_mysql</strong></td><td>builtin</td><td>Todas las consultas a MySQL con prepared statements</td><td>Extensión PHP</td></tr>
                        <tr><td><strong>cURL (PHP)</strong></td><td>builtin</td><td>Llamadas REST a Mercado Pago API y Anthropic Claude API</td><td>Extensión PHP</td></tr>
                        <tr><td><strong>openssl (PHP)</strong></td><td>builtin</td><td>Firma PKCS7 del TRA para AFIP WSAA (<code>openssl_pkcs7_sign()</code>)</td><td>Extensión PHP</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: BASE DE DATOS        -->
        <!-- ============================= -->
        <section id="base-datos" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-warning-soft text-warning me-2">🗄</span>Base de Datos
            </h2>
            <p class="small text-muted">Base de datos: <code>u374453216_erp_comercio</code> · Charset: <code>utf8mb4</code> · Collation: <code>utf8mb4_unicode_ci</code></p>
            <div class="table-responsive">
                <table class="table table-sm doc-table">
                    <thead class="table-light"><tr><th>Tabla</th><th>Descripción</th><th>Columnas clave</th></tr></thead>
                    <tbody>
                        <tr><td><code>usuarios</code></td><td>Cuentas de acceso al sistema</td><td>id, nombre, email, password (bcrypt), rol_id, activo, foto</td></tr>
                        <tr><td><code>roles</code></td><td>Roles del sistema</td><td>id, nombre (admin/cajero/deposito/vendedor)</td></tr>
                        <tr><td><code>permisos</code></td><td>Permisos por rol × módulo</td><td>rol_id, modulo, ver, editar</td></tr>
                        <tr><td><code>productos</code></td><td>Catálogo de productos para venta</td><td>id, codigo, nombre, precio_venta, iva, stock_actual, ecommerce, activo</td></tr>
                        <tr><td><code>categorias</code></td><td>Árbol de categorías (padre/hijo)</td><td>id, nombre, categoria_padre_id, tipo, activo</td></tr>
                        <tr><td><code>materias_primas</code></td><td>Ingredientes para recetas</td><td>id, nombre, unidad, stock_actual, stock_minimo, costo_unitario</td></tr>
                        <tr><td><code>insumos</code></td><td>Insumos de uso interno (no van a recetas)</td><td>id, nombre, unidad, stock_actual, stock_minimo</td></tr>
                        <tr><td><code>recetas</code></td><td>Recetas de producción</td><td>id, nombre, producto_final_id, rendimiento, unidad</td></tr>
                        <tr><td><code>receta_detalle</code></td><td>Ingredientes por receta</td><td>receta_id, tipo_item, item_id, cantidad, merma_pct</td></tr>
                        <tr><td><code>ventas</code></td><td>Cabecera de ventas (incluye AFIP)</td><td>id, total_con_iva, metodo_pago, facturar_afip, afip_cae, afip_cae_vto, mesa</td></tr>
                        <tr><td><code>ventas_detalle</code></td><td>Ítems por venta</td><td>venta_id, producto_id, cantidad, precio_unitario, iva_porcentaje</td></tr>
                        <tr><td><code>clientes</code></td><td>Clientes del comercio</td><td>id, nombre, documento, tipo_doc, condicion_iva, email, telefono</td></tr>
                        <tr><td><code>proveedores</code></td><td>Proveedores de compra</td><td>id, razon_social, cuit, cbu (22 dígitos), condicion_iva</td></tr>
                        <tr><td><code>facturas_compra</code></td><td>Facturas de compra recibidas</td><td>id, proveedor_id, nro_comprobante, tipo, total_con_iva</td></tr>
                        <tr><td><code>stock_movimientos</code></td><td>Historial de todos los movimientos de stock</td><td>tipo_item, item_id, tipo_mov, cantidad, stock_antes, stock_despues, motivo</td></tr>
                        <tr><td><code>caja_turnos</code></td><td>Turnos de caja (apertura/cierre)</td><td>id, usuario_id, monto_apertura, monto_declarado, diferencia, estado</td></tr>
                        <tr><td><code>caja_movimientos</code></td><td>Movimientos dentro de un turno</td><td>turno_id, tipo (ingreso/egreso), monto, descripcion, origen</td></tr>
                        <tr><td><code>pedidos_ecommerce</code></td><td>Pedidos de la tienda pública</td><td>id, cliente_email, total_con_iva, estado, mp_preference_id, mp_payment_id</td></tr>
                        <tr><td><code>pagos_mercadopago</code></td><td>Detalle de pagos MP recibidos</td><td>pedido_id, payment_id, status, status_detail, amount</td></tr>
                        <tr><td><code>audit_log</code></td><td>Log de acciones críticas</td><td>usuario_id, accion, detalle, ip, created_at</td></tr>
                        <tr><td><code>chat_mensajes</code></td><td>Historial del chat IA</td><td>usuario_id, pregunta, respuesta, sql_ejecutado</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-1">Todas las tablas usan <strong>baja lógica</strong>: columna <code>eliminado TINYINT DEFAULT 0</code>. Nunca se ejecuta <code>DELETE</code> directo en producción.</p>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: APIs                 -->
        <!-- ============================= -->
        <section id="api-mp" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge" style="background:#00b1ea;color:#fff">MP</span>&nbsp;API: Mercado Pago
            </h2>
            <div class="row g-3">
                <div class="col-md-5">
                    <h6 class="fw-semibold">Credenciales necesarias</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td><span class="env-key">MP_ACCESS_TOKEN</span></td><td>Token privado para crear preferencias y pagos (backend)</td></tr>
                            <tr><td><span class="env-key">MP_PUBLIC_KEY</span></td><td>Clave pública para el SDK de Bricks en el frontend</td></tr>
                        </tbody>
                    </table>
                    <p class="small text-muted">Obtener en: <code>mercadopago.com/developers → Tus aplicaciones → Credenciales de prueba / producción</code></p>
                    <p class="small">Las credenciales de <strong>prueba</strong> empiezan con <code>TEST-</code>. Las de <strong>producción</strong> no tienen ese prefijo.</p>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-semibold">Endpoints que usa el sistema</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td><span class="api-badge" style="background:#e8f4fd;color:#0369a1">POST</span></td><td><code>api.mercadopago.com/checkout/preferences</code></td><td>Crea preferencia Checkout Pro</td></tr>
                            <tr><td><span class="api-badge" style="background:#e8f4fd;color:#0369a1">POST</span></td><td><code>api.mercadopago.com/v1/payments</code></td><td>Crea pago con Checkout API (Bricks)</td></tr>
                            <tr><td><span class="api-badge" style="background:#fef3c7;color:#92400e">GET</span></td><td><code>api.mercadopago.com/v1/payments/{id}</code></td><td>Consulta estado de un pago (webhook)</td></tr>
                            <tr><td><span class="api-badge" style="background:#fce7f3;color:#9d174d">IPN</span></td><td><code>nuestro-sitio/tienda/webhook/mp</code></td><td>Webhook que recibe MP (inbound)</td></tr>
                        </tbody>
                    </table>
                    <h6 class="fw-semibold mt-2">Flujo Checkout Pro vs Checkout API (Bricks)</h6>
                    <ul class="small">
                        <li><strong>Checkout Pro</strong>: redirect a página de MP → usuario paga ahí → MP llama webhook → sistema actualiza</li>
                        <li><strong>Checkout API (Bricks)</strong>: formulario de tarjeta inline en nuestro sitio → token vía SDK → POST a <code>/v1/payments</code> → resultado inmediato</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="api-afip" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-danger">AFIP</span>&nbsp;API: AFIP Facturación Electrónica
            </h2>
            <div class="row g-3">
                <div class="col-md-5">
                    <h6 class="fw-semibold">Variables de entorno AFIP</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td><span class="env-key">AFIP_MODO</span></td><td><code>homo</code> (testing) o <code>prod</code> (producción)</td></tr>
                            <tr><td><span class="env-key">AFIP_CUIT</span></td><td>CUIT del emisor, 11 dígitos sin guiones</td></tr>
                            <tr><td><span class="env-key">AFIP_CERT</span></td><td>Ruta al archivo <code>cert.pem</code> (certificado X.509)</td></tr>
                            <tr><td><span class="env-key">AFIP_KEY</span></td><td>Ruta al archivo <code>key.pem</code> (clave privada RSA)</td></tr>
                            <tr><td><span class="env-key">AFIP_KEY_PASS</span></td><td>Passphrase de la clave privada (si tiene)</td></tr>
                            <tr><td><span class="env-key">AFIP_PTO_VENTA</span></td><td>Número de punto de venta habilitado en AFIP</td></tr>
                            <tr><td><span class="env-key">NEGOCIO_CONDICION_IVA</span></td><td><code>monotributista</code> | <code>responsable_inscripto</code> | <code>exento</code> — define el tipo de comprobante (ver abajo)</td></tr>
                            <tr><td><span class="env-key">TICKET_ANCHO_MM</span></td><td>Ancho real del rollo de la impresora térmica (50, 58, 80, 100...)</td></tr>
                            <tr><td><span class="env-key">TICKET_ZOOM_PCT</span></td><td>Zoom del texto impreso (100 = normal, 130 = 30% más grande)</td></tr>
                        </tbody>
                    </table>
                    <h6 class="fw-semibold mt-3">Tipo de comprobante según condición IVA</h6>
                    <p class="small text-muted mb-1">Ya no se fija con una variable — se calcula por venta en <code>AfipWsfe::tipoComprobante()</code>:</p>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td>Negocio Monotributista o Exento</td><td>Factura C (11), cualquiera sea el cliente</td></tr>
                            <tr><td>Negocio Responsable Inscripto + cliente Responsable Inscripto</td><td>Factura A (1)</td></tr>
                            <tr><td>Negocio Responsable Inscripto + otro cliente</td><td>Factura B (6)</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-semibold">Pasos para obtener certificado AFIP</h6>
                    <ol class="small">
                        <li>Generar clave privada RSA: <code>openssl genrsa -out key.pem 2048</code></li>
                        <li>Generar CSR: <code>openssl req -new -key key.pem -out csr.pem -subj "/CN=DEWOC/serialNumber=CUIT 30-XXXXXXXX-X"</code></li>
                        <li>Ingresar a AFIP → Servicios Online → Administración de Certificados Digitales</li>
                        <li>Crear nuevo certificado → pegar el contenido del <code>csr.pem</code> → descargar <code>cert.pem</code></li>
                        <li>Colocar <code>cert.pem</code> y <code>key.pem</code> en <code>storage/afip/</code></li>
                        <li>Dar de alta el servicio <strong>wsfe</strong> en el CUIT emisor (Acceso a Servicios)</li>
                    </ol>
                    <h6 class="fw-semibold mt-2">Flujo interno AFIP en una venta</h6>
                    <pre class="code-block" style="font-size:.78rem">VentasController::guardar()
  → AfipWsfe::solicitarCae($venta, $detalle)
    → AfipWsaa::getTicket()          // chequea caché JSON
      → signTRA() + loginCms(SOAP)  // si caché vencido
    → FECompUltimoAutorizado(SOAP)  // obtiene último nro cbte
    → calcularIva()                 // agrupa por alícuota
    → FECAESolicitar(SOAP)          // solicita CAE
  → VentaModel::updateAfip($id, $result)</pre>
                </div>
            </div>
        </section>

        <section id="api-claude" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge" style="background:#6b21a8;color:#fff">IA</span>&nbsp;API: Anthropic Claude (Chat IA)
            </h2>
            <div class="row g-3">
                <div class="col-md-5">
                    <h6 class="fw-semibold">Configuración</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td><span class="env-key">CLAUDE_API_KEY</span></td><td>API Key de Anthropic (sk-ant-...)</td></tr>
                            <tr><td><span class="env-key">CLAUDE_MODEL</span></td><td>Modelo a usar (default: <code>claude-haiku-4-5-20251001</code>)</td></tr>
                        </tbody>
                    </table>
                    <p class="small text-muted">Obtener en: <code>console.anthropic.com → API Keys</code></p>
                    <p class="small">Si <code>CLAUDE_API_KEY</code> está vacía, el chat muestra un mensaje claro indicando que no está configurado.</p>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-semibold">Flujo del Chat IA (MCP MySQL)</h6>
                    <ol class="small">
                        <li>Usuario escribe pregunta en lenguaje natural (ej: "¿Cuánto vendí hoy?")</li>
                        <li>Sistema envía la pregunta a Claude con el schema de las tablas habilitadas</li>
                        <li>Claude genera una consulta <code>SELECT</code></li>
                        <li>Sistema valida el SQL: solo SELECT, sin keywords peligrosos, LIMIT automático</li>
                        <li>Ejecuta la consulta contra MySQL → obtiene resultados</li>
                        <li>Segunda llamada a Claude para interpretar y redactar la respuesta en español</li>
                        <li>El SQL ejecutado queda visible en un modal de la UI</li>
                    </ol>
                    <p class="small text-muted">Tablas habilitadas: controladas por <code>MCP_TABLAS_OK</code> en <code>config.php</code>.</p>
                </div>
            </div>
        </section>

        <section id="api-email" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-secondary">✉</span>&nbsp;Email
            </h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-semibold">Variables de entorno</h6>
                    <table class="table table-sm doc-table">
                        <tbody>
                            <tr><td><span class="env-key">MAIL_ENABLED</span></td><td><code>true</code> para activar el envío. <code>false</code> deshabilita sin errores.</td></tr>
                            <tr><td><span class="env-key">MAIL_FROM</span></td><td>Dirección de origen (ej: <code>noreply@micomercio.com</code>)</td></tr>
                            <tr><td><span class="env-key">MAIL_FROM_NAME</span></td><td>Nombre visible del remitente</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold">Cuándo se envían emails</h6>
                    <ul class="small">
                        <li><strong>Confirmación de pedido</strong> — <code>Mailer::confirmacionPedido()</code> al aprobar pago MP</li>
                        <li><strong>Reset de contraseña</strong> — <code>Mailer::resetPassword()</code> al solicitar recuperación</li>
                    </ul>
                    <p class="small text-muted">Usa <code>mail()</code> de PHP. En producción se recomienda un relay SMTP (SendGrid, SES, Postmark) configurado en el php.ini del servidor.</p>
                </div>
            </div>
        </section>

        <section id="api-env" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-dark">⚙</span>&nbsp;Variables de Entorno (.env)
            </h2>
            <p class="small">Copiar <code>.env.example</code> a <code>.env</code> en la raíz del proyecto. El archivo <code>.env</code> está en <code>.gitignore</code> — nunca se sube al repositorio.</p>
            <pre class="code-block"># ── Entorno ──────────────────────────────────────────────────────
APP_ENV=development          # development | production
APP_URL=http://localhost/01-Dewoc-Sist-Admin-Comercio

# ── Base de datos ────────────────────────────────────────────────
DB_HOST=localhost
DB_NAME=u374453216_erp_comercio
DB_USER=root
DB_PASS=

# ── Mercado Pago ─────────────────────────────────────────────────
MP_ACCESS_TOKEN=             # TEST-xxxx... o token de producción
MP_PUBLIC_KEY=               # TEST-xxxx... o key de producción

# ── Chat IA (Anthropic) ──────────────────────────────────────────
CLAUDE_API_KEY=              # sk-ant-api03-...

# ── AFIP (Facturación electrónica) ───────────────────────────────
AFIP_MODO=homo               # homo | prod
AFIP_CUIT=                   # 11 dígitos sin guiones
AFIP_CERT=storage/afip/cert.pem
AFIP_KEY=storage/afip/key.pem
AFIP_KEY_PASS=
AFIP_PTO_VENTA=1

# ── Datos fiscales del negocio (comprobante ARCA/AFIP) ────────────
NEGOCIO_CONDICION_IVA=monotributista  # monotributista | responsable_inscripto | exento
NEGOCIO_IIBB=
NEGOCIO_INICIO_ACTIVIDADES=
NEGOCIO_CONDICION_FISCAL=
NEGOCIO_ORIENTACION_CONSUMIDOR=

# ── Impresora térmica (ticket/comanda) ────────────────────────────
TICKET_ANCHO_MM=80    # 50, 58, 80, 100...
TICKET_ZOOM_PCT=130   # 100 = normal, 130 = 30% más grande

# ── Email ────────────────────────────────────────────────────────
MAIL_ENABLED=false
MAIL_FROM=noreply@tudominio.com
MAIL_FROM_NAME=Dewoc Admin</pre>
        </section>

        <!-- ============================= -->
        <!-- SECCIÓN: DIAGRAMAS            -->
        <!-- ============================= -->
        <section id="diagramas" class="doc-section mb-5">
            <h2 class="h4 fw-bold border-bottom pb-2 mb-3">
                <span class="badge bg-info-soft text-info me-2">📐</span>Diagramas del Sistema
            </h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="drawio-card h-100">
                        <h6 class="fw-semibold mb-1">Arquitectura del Sistema</h6>
                        <p class="small text-muted mb-3">Capas: Presentación → Aplicación PHP (MVC) → Persistencia. APIs externas: AFIP, Mercado Pago, Anthropic, Email.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= url('docs/descargar?file=arquitectura.drawio') ?>" class="btn btn-sm btn-primary">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                Descargar .drawio
                            </a>
                            <a href="https://app.diagrams.net/" target="_blank" class="btn btn-sm btn-outline-secondary">
                                Abrir en diagrams.net ↗
                            </a>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                            En diagrams.net: <em>File → Open from → Device</em> para cargar el archivo descargado.
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="drawio-card h-100">
                        <h6 class="fw-semibold mb-1">Flujo de Usuario</h6>
                        <p class="small text-muted mb-3">4 flujos: Venta POS (con AFIP), Ecommerce (Bricks + Checkout Pro), Control de Stock, Administración.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= url('docs/descargar?file=flujo_usuario.drawio') ?>" class="btn btn-sm btn-primary">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                Descargar .drawio
                            </a>
                            <a href="https://app.diagrams.net/" target="_blank" class="btn btn-sm btn-outline-secondary">
                                Abrir en diagrams.net ↗
                            </a>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                            En diagrams.net: <em>File → Open from → Device</em> para cargar el archivo descargado.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Vista previa SVG: Arquitectura simplificada -->
            <h6 class="fw-semibold mb-3">Vista previa: Arquitectura (simplificada)</h6>
            <div class="border rounded p-3 bg-white" style="overflow-x:auto">
                <svg viewBox="0 0 900 420" xmlns="http://www.w3.org/2000/svg" style="width:100%;min-width:600px;font-family:sans-serif;font-size:11px">
                    <!-- Zona Cliente -->
                    <rect x="10" y="10" width="560" height="90" rx="6" fill="#dae8fc" stroke="#6c8ebf" stroke-width="1.5"/>
                    <text x="18" y="26" fill="#23527c" font-weight="bold" font-size="10">PRESENTACIÓN (CLIENTE)</text>
                    <rect x="20" y="32" width="140" height="58" rx="4" fill="#fff" stroke="#6c8ebf"/>
                    <text x="90" y="57" text-anchor="middle" fill="#333">Navegador Web</text>
                    <text x="90" y="71" text-anchor="middle" fill="#666" font-size="9">Chrome · Firefox</text>
                    <rect x="170" y="32" width="140" height="58" rx="4" fill="#fff" stroke="#6c8ebf"/>
                    <text x="240" y="57" text-anchor="middle" fill="#333">PWA Instalada</text>
                    <text x="240" y="71" text-anchor="middle" fill="#666" font-size="9">Service Worker</text>
                    <rect x="320" y="32" width="240" height="58" rx="4" fill="#fff" stroke="#6c8ebf"/>
                    <text x="440" y="50" text-anchor="middle" fill="#333">Dashly · Bootstrap · DataTables</text>
                    <text x="440" y="64" text-anchor="middle" fill="#666" font-size="9">Chart.js · MP Bricks SDK v2 · JsBarcode</text>
                    <!-- Flechas cliente → app -->
                    <line x1="90" y1="90" x2="90" y2="115" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <line x1="240" y1="90" x2="240" y2="115" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Zona App -->
                    <rect x="10" y="115" width="560" height="230" rx="6" fill="#d5e8d4" stroke="#82b366" stroke-width="1.5"/>
                    <text x="18" y="130" fill="#2d662d" font-weight="bold" font-size="10">APLICACIÓN PHP — MVC</text>
                    <!-- Router -->
                    <rect x="20" y="135" width="140" height="50" rx="4" fill="#fff" stroke="#82b366"/>
                    <text x="90" y="157" text-anchor="middle" fill="#333" font-weight="bold">Router</text>
                    <text x="90" y="171" text-anchor="middle" fill="#666" font-size="9">index.php + .htaccess</text>
                    <!-- Arrow router → controllers -->
                    <line x1="160" y1="160" x2="190" y2="160" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Middleware -->
                    <rect x="190" y="135" width="170" height="50" rx="4" fill="#e1d5e7" stroke="#9673a6"/>
                    <text x="275" y="155" text-anchor="middle" fill="#333">Csrf · Session</text>
                    <text x="275" y="169" text-anchor="middle" fill="#666" font-size="9">requireAuth · can()</text>
                    <!-- Arrow middleware → ctr -->
                    <line x1="360" y1="160" x2="390" y2="160" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Controllers box -->
                    <rect x="390" y="135" width="170" height="50" rx="4" fill="#fff" stroke="#82b366"/>
                    <text x="475" y="155" text-anchor="middle" fill="#333" font-weight="bold">Controllers</text>
                    <text x="475" y="170" text-anchor="middle" fill="#666" font-size="9">Ventas · Stock · Tienda · Chat…</text>
                    <!-- Arrow ctr → models -->
                    <line x1="475" y1="185" x2="475" y2="215" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Models -->
                    <rect x="390" y="215" width="170" height="50" rx="4" fill="#f0f0f0" stroke="#aaa"/>
                    <text x="475" y="235" text-anchor="middle" fill="#333" font-weight="bold">Models</text>
                    <text x="475" y="249" text-anchor="middle" fill="#666" font-size="9">PDO prepared statements</text>
                    <!-- Arrow models → mysql -->
                    <line x1="475" y1="265" x2="475" y2="295" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Core -->
                    <rect x="20" y="205" width="340" height="50" rx="4" fill="#e1d5e7" stroke="#9673a6"/>
                    <text x="190" y="224" text-anchor="middle" fill="#333" font-weight="bold">Core Classes</text>
                    <text x="190" y="239" text-anchor="middle" fill="#555" font-size="9">AfipWsaa · AfipWsfe · Mailer · AuditLog · RememberMe · Database</text>
                    <!-- Arrow ctr → apis -->
                    <line x1="560" y1="160" x2="600" y2="160" stroke="#555" stroke-width="1.5" marker-end="url(#arrow)"/>
                    <!-- Zona DB -->
                    <rect x="10" y="355" width="560" height="60" rx="6" fill="#fff2cc" stroke="#d6b656" stroke-width="1.5"/>
                    <text x="18" y="370" fill="#7d5a00" font-weight="bold" font-size="10">PERSISTENCIA</text>
                    <rect x="20" y="375" width="200" height="32" rx="4" fill="#fff" stroke="#d6b656"/>
                    <text x="120" y="395" text-anchor="middle" fill="#333">MySQL — u374453216_erp_comercio</text>
                    <rect x="240" y="375" width="160" height="32" rx="4" fill="#fff" stroke="#d6b656"/>
                    <text x="320" y="395" text-anchor="middle" fill="#333">File System</text>
                    <!-- Arrow mysql area -->
                    <line x1="475" y1="295" x2="475" y2="355" stroke="#555" stroke-width="1.5" stroke-dasharray="4" marker-end="url(#arrow)"/>
                    <!-- Zona APIs -->
                    <rect x="600" y="10" width="290" height="405" rx="6" fill="#f8cecc" stroke="#b85450" stroke-width="1.5"/>
                    <text x="610" y="26" fill="#7d0000" font-weight="bold" font-size="10">SERVICIOS EXTERNOS</text>
                    <rect x="610" y="32" width="270" height="55" rx="4" fill="#fff" stroke="#b85450"/>
                    <text x="745" y="52" text-anchor="middle" fill="#333" font-weight="bold">AFIP WSAA</text>
                    <text x="745" y="66" text-anchor="middle" fill="#666" font-size="9">loginCms SOAP · Ticket de Acceso</text>
                    <rect x="610" y="100" width="270" height="55" rx="4" fill="#fff" stroke="#b85450"/>
                    <text x="745" y="120" text-anchor="middle" fill="#333" font-weight="bold">AFIP WSFE v1</text>
                    <text x="745" y="134" text-anchor="middle" fill="#666" font-size="9">FECAESolicitar · CAE electrónico</text>
                    <rect x="610" y="168" width="270" height="55" rx="4" fill="#fff" stroke="#b85450"/>
                    <text x="745" y="188" text-anchor="middle" fill="#333" font-weight="bold">Mercado Pago REST API</text>
                    <text x="745" y="202" text-anchor="middle" fill="#666" font-size="9">/checkout/preferences · /v1/payments</text>
                    <rect x="610" y="236" width="270" height="55" rx="4" fill="#fff" stroke="#b85450"/>
                    <text x="745" y="256" text-anchor="middle" fill="#333" font-weight="bold">Anthropic Claude API</text>
                    <text x="745" y="270" text-anchor="middle" fill="#666" font-size="9">/v1/messages · NL→SQL (MCP)</text>
                    <rect x="610" y="304" width="270" height="55" rx="4" fill="#fff" stroke="#b85450"/>
                    <text x="745" y="324" text-anchor="middle" fill="#333" font-weight="bold">Email SMTP</text>
                    <text x="745" y="338" text-anchor="middle" fill="#666" font-size="9">Confirmación pedido · Reset password</text>
                    <!-- Arrows controllers → APIs -->
                    <line x1="560" y1="148" x2="610" y2="59" stroke="#b85450" stroke-width="1" stroke-dasharray="4" marker-end="url(#arrowRed)"/>
                    <line x1="560" y1="165" x2="610" y2="195" stroke="#b85450" stroke-width="1" stroke-dasharray="4" marker-end="url(#arrowRed)"/>
                    <line x1="560" y1="175" x2="610" y2="263" stroke="#b85450" stroke-width="1" stroke-dasharray="4" marker-end="url(#arrowRed)"/>
                    <!-- Arrow defs -->
                    <defs>
                        <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                            <path d="M0,0 L0,6 L8,3 z" fill="#555"/>
                        </marker>
                        <marker id="arrowRed" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                            <path d="M0,0 L0,6 L8,3 z" fill="#b85450"/>
                        </marker>
                    </defs>
                </svg>
            </div>
        </section>

    </div><!-- /col main -->
</div><!-- /row -->

<script>
(function () {
    const sections  = Array.from(document.querySelectorAll('.doc-section'));
    const counter   = document.getElementById('searchCounter');
    const noResults = document.getElementById('noResultsMsg');
    const clearBtn  = document.getElementById('btnClearSearch');
    let   timer;

    // Store original HTML once
    sections.forEach(s => { s.dataset.orig = s.innerHTML; });

    function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    function highlight(section, q) {
        const re = new RegExp('(' + escapeRe(q) + ')', 'gi');
        const walk = document.createTreeWalker(section, NodeFilter.SHOW_TEXT);
        const nodes = [];
        let n;
        while ((n = walk.nextNode())) nodes.push(n);
        nodes.forEach(node => {
            if (!re.test(node.textContent)) return;
            const span = document.createElement('span');
            span.innerHTML = node.textContent.replace(re, '<mark>$1</mark>');
            node.parentNode.replaceChild(span, node);
        });
    }

    function doSearch(raw) {
        const q = raw.trim().toLowerCase();

        // Restore
        sections.forEach(s => { s.innerHTML = s.dataset.orig; s.style.display = ''; });

        if (!q) {
            counter.textContent = '';
            noResults.classList.add('d-none');
            clearBtn.style.display = 'none';
            return;
        }

        clearBtn.style.display = '';
        let hits = 0;
        sections.forEach(s => {
            if (s.textContent.toLowerCase().includes(q)) {
                hits++;
                highlight(s, q);
            } else {
                s.style.display = 'none';
            }
        });

        counter.textContent = hits
            ? hits + ' sección' + (hits > 1 ? 'es' : '') + ' encontrada' + (hits > 1 ? 's' : '')
            : '';
        noResults.classList.toggle('d-none', hits > 0);

        // Scroll to first visible section
        if (hits > 0) {
            const first = sections.find(s => s.style.display !== 'none');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.getElementById('docsSearch').addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => doSearch(this.value), 220);
    });

    window.clearSearch = function () {
        const inp = document.getElementById('docsSearch');
        inp.value = '';
        doSearch('');
        inp.focus();
    };

    // Scrollspy — highlight nav link based on scroll position
    const navLinks = document.querySelectorAll('.doc-nav .nav-link');
    function updateNav() {
        let current = '';
        sections.forEach(s => {
            if (window.scrollY >= s.offsetTop - 100) current = s.id;
        });
        navLinks.forEach(l => {
            l.classList.toggle('active', l.getAttribute('href') === '#' + current);
        });
    }
    window.addEventListener('scroll', updateNav, { passive: true });
    updateNav();
})();
</script>
