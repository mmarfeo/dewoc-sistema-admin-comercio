<?php
declare(strict_types=1);

// Charset HTTP explícito — tiene precedencia sobre la meta tag del HTML.
// Sin esto, algunos servidores de hosting envían Content-Type sin charset
// y el browser interpreta la respuesta como Latin-1, rompiendo los acentos.
header('Content-Type: text/html; charset=UTF-8');

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/app/Models/Model.php';
require_once __DIR__ . '/app/Controllers/Controller.php';

Session::start();

// ── Manejo global de excepciones no capturadas ──────────────────────────────
// Sin esto, un error de PHP (ej. columna faltante en la BD) devuelve HTML/nada
// en respuestas AJAX, y el JS del front lo interpreta como "Error de conexión"
// sin mostrar la causa real. Ahora se loguea siempre en storage/logs/app.log
// y se responde JSON en peticiones POST (que son casi todas las llamadas AJAX
// de esta app) para que el error real llegue al usuario.
set_exception_handler(function (\Throwable $e): void {
    logApp(
        'Excepción no capturada: ' . $e->getMessage()
            . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(),
        'ERROR'
    );

    if (!headers_sent()) {
        http_response_code(500);
    }

    $esPost      = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $aceptaJson  = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    // La API de MenuApp tiene que responder JSON también en los GET, sin depender de que
    // el cliente haya mandado el header Accept (ver el bloque de ruteo más abajo).
    $esApiJson   = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/menuapp/v1/');

    if ($esPost || $aceptaJson || $esApiJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => DEBUG ? $e->getMessage() : 'Error interno del servidor. Quedó registrado en el log.',
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (DEBUG) {
        echo '<pre style="padding:20px;color:#c00;font-family:monospace;white-space:pre-wrap">'
            . e($e->getMessage()) . "\n\n" . e($e->getFile() . ':' . $e->getLine())
            . "\n\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<div style="padding:60px;text-align:center;font-family:sans-serif;color:#555">'
            . '<h2>Ha ocurrido un error</h2><p>Quedó registrado en el log del sistema.</p></div>';
    }
});

// ── Enrutamiento ─────────────────────────────────────────────────────────────
$router = new Router();

// Auth
$router->get('auth/login',            'AuthController', 'loginForm');
$router->post('auth/login',           'AuthController', 'login');
$router->get('auth/logout',           'AuthController', 'logout');
$router->get('auth/recuperar',        'AuthController', 'recuperarForm');
$router->post('auth/recuperar',       'AuthController', 'recuperar');
$router->get('auth/reset-password',   'AuthController', 'resetPasswordForm');
$router->post('auth/reset-password',  'AuthController', 'resetPassword');
$router->post('auth/reconectar',      'AuthController', 'reconectar');

// WebAuthn (login biométrico: huella/Face ID/PIN del dispositivo)
$router->get('webauthn/registro-challenge',    'WebauthnController', 'registroChallenge');
$router->post('webauthn/registro-verificar',   'WebauthnController', 'registroVerificar');
$router->get('webauthn/login-challenge',       'WebauthnController', 'loginChallenge');
$router->post('webauthn/login-verificar',      'WebauthnController', 'loginVerificar');
$router->get('webauthn/dispositivos',          'WebauthnController', 'dispositivos');
$router->post('webauthn/eliminar-dispositivo', 'WebauthnController', 'eliminarDispositivo');

// Dashboard
$router->get('',             'ChatController', 'index');
$router->get('dashboard',    'DashboardController', 'index');

// Resumen Ejecutivo — junta en una sola pantalla lo último de cada reporte automático
// (cron/reporte_semanal.php, reporte_margen_producto.php, reporte_compras_pagos_usuario.php)
// + el estado en vivo de lista de compras, vencimientos de gastos y proyecciones.
$router->get('resumen-ejecutivo', 'ResumenEjecutivoController', 'index');

// Usuarios
$router->get('usuarios',         'UsuariosController', 'index');
$router->get('usuarios/nuevo',   'UsuariosController', 'nuevo');
$router->post('usuarios/crear',  'UsuariosController', 'crear');
$router->get('usuarios/editar',  'UsuariosController', 'editar');
$router->post('usuarios/actualizar', 'UsuariosController', 'actualizar');
$router->post('usuarios/guardar-excepcion-permiso', 'UsuariosController', 'guardarExcepcionPermiso');
$router->post('usuarios/quitar-excepcion-permiso',  'UsuariosController', 'quitarExcepcionPermiso');
$router->post('usuarios/asignar-plantilla',         'UsuariosController', 'asignarPlantilla');
$router->post('usuarios/guardar-accesos-chat', 'UsuariosController', 'guardarAccesosChat');
$router->post('usuarios/eliminar',         'UsuariosController', 'eliminar');
$router->get('usuarios/perfil',            'UsuariosController', 'perfil');
$router->post('usuarios/cambiar-password', 'UsuariosController', 'cambiarPassword');
$router->post('usuarios/subir-foto',       'UsuariosController', 'subirFoto');
$router->post('usuarios/importar',         'UsuariosController', 'importar');
$router->get('usuarios/plantilla',         'UsuariosController', 'plantilla');

// Socios
$router->get('socios',             'SociosController', 'index');
$router->post('socios/crear',      'SociosController', 'crear');
$router->post('socios/actualizar', 'SociosController', 'actualizar');
$router->post('socios/eliminar',   'SociosController', 'eliminar');

// Galería
$router->get('galeria',           'GaleriaController', 'index');
$router->post('galeria/subir',    'GaleriaController', 'subir');
$router->get('galeria/ver',       'GaleriaController', 'ver');
$router->post('galeria/eliminar', 'GaleriaController', 'eliminar');
$router->post('galeria/generar',       'GaleriaController', 'generar');
$router->post('galeria/generar-video', 'GaleriaController', 'generarVideo');
$router->get('galeria/estado',         'GaleriaController', 'estado');

// Fotos del menú online (ver docs/integracion-menuapp-checklist.md, etapa 8). Rutas
// exactas porque los nombres llevan guión: la resolución convencional del Router buscaría
// un método "fotos-menu", que no es un identificador PHP válido.
$router->get('galeria/fotos-menu',                   'FotosMenuController', 'index');
$router->get('galeria/fotos-menu/producto',          'FotosMenuController', 'producto');
$router->post('galeria/fotos-menu/subir',            'FotosMenuController', 'subirCandidata');
$router->post('galeria/fotos-menu/mejorar',          'FotosMenuController', 'mejorar');
$router->post('galeria/fotos-menu/oficial',          'FotosMenuController', 'elegirOficial');
$router->post('galeria/fotos-menu/descartar',        'FotosMenuController', 'descartar');
$router->post('galeria/fotos-menu/volver-anterior',  'FotosMenuController', 'volverAnterior');

// Categorías
$router->get('categorias',             'CategoriasController', 'index');
$router->post('categorias/crear',      'CategoriasController', 'crear');
$router->post('categorias/actualizar', 'CategoriasController', 'actualizar');
$router->post('categorias/eliminar',   'CategoriasController', 'eliminar');
$router->post('categorias/importar',   'CategoriasController', 'importar');
$router->get('categorias/plantilla',   'CategoriasController', 'plantilla');

// Productos
$router->get('productos',            'ProductosController', 'index');
$router->get('productos/nuevo',      'ProductosController', 'nuevo');
$router->post('productos/crear',     'ProductosController', 'crear');
$router->get('productos/editar',     'ProductosController', 'editar');
$router->post('productos/actualizar','ProductosController', 'actualizar');
$router->post('productos/eliminar',      'ProductosController', 'eliminar');
$router->get('productos/qr',             'ProductosController', 'qr');
$router->post('productos/importar',      'ProductosController', 'importar');
$router->post('productos/previsualizar', 'ProductosController', 'previsualizar');
$router->get('productos/plantilla',      'ProductosController', 'plantilla');
$router->get('productos/exportar',       'ProductosController', 'exportar');
$router->get('productos/recetas',        'ProductosController', 'getRecetas');
$router->post('productos/asignar-receta','ProductosController', 'asignarReceta');
$router->post('productos/quitar-receta', 'ProductosController', 'quitarReceta');
$router->post('productos/recalcular-costo', 'ProductosController', 'recalcularCosto');
$router->get('productos/opciones-grupos',         'ProductosController', 'getOpcionesGrupos');
$router->post('productos/asignar-opciones-grupo', 'ProductosController', 'asignarOpcionesGrupo');
$router->post('productos/quitar-opciones-grupo',  'ProductosController', 'quitarOpcionesGrupo');

// Promos: combos de composición fija de 2+ productos (ver docs/promos-diseno.md)
$router->get('productos/promo-componentes',        'ProductosController', 'getPromoComponentes');
$router->get('productos/buscar-componentes-promo', 'ProductosController', 'buscarComponentesPromo');
$router->post('productos/asignar-promo-componente','ProductosController', 'asignarPromoComponente');
$router->post('productos/quitar-promo-componente', 'ProductosController', 'quitarPromoComponente');

// Opciones/Adicionales de productos (grupos reutilizables, ver docs/tareas-pendientes.md #1)
$router->get('opciones-productos',            'OpcionesGrupoController', 'index');
$router->get('opciones-productos/nuevo',      'OpcionesGrupoController', 'nuevo');
$router->post('opciones-productos/guardar',   'OpcionesGrupoController', 'guardar');
$router->get('opciones-productos/editar',     'OpcionesGrupoController', 'editar');
$router->post('opciones-productos/actualizar','OpcionesGrupoController', 'actualizar');
$router->post('opciones-productos/eliminar',  'OpcionesGrupoController', 'eliminar');
$router->post('opciones-productos/activar',   'OpcionesGrupoController', 'activar');

// Materias Primas
$router->get('materias-primas',                    'MateriasPrimasController', 'index');
$router->get('materias-primas/nuevo',              'MateriasPrimasController', 'nuevo');
$router->post('materias-primas/crear',             'MateriasPrimasController', 'crear');
$router->get('materias-primas/editar',             'MateriasPrimasController', 'editar');
$router->post('materias-primas/actualizar',        'MateriasPrimasController', 'actualizar');
$router->post('materias-primas/eliminar',          'MateriasPrimasController', 'eliminar');
$router->post('materias-primas/recalcular-costo',  'MateriasPrimasController', 'recalcularCosto');
$router->get('materias-primas/historial-precios',  'MateriasPrimasController', 'historialPrecios');
$router->post('materias-primas/importar',          'MateriasPrimasController', 'importar');
$router->get('materias-primas/plantilla',          'MateriasPrimasController', 'plantilla');
$router->post('materias-primas/toggle-activo',     'MateriasPrimasController', 'toggleActivo');
$router->post('materias-primas/previsualizar',     'MateriasPrimasController', 'previsualizar');

// Ingredientes Genéricos
$router->get('ingredientes-genericos',             'IngredientesGenericosController', 'index');
$router->post('ingredientes-genericos/crear',      'IngredientesGenericosController', 'crear');
$router->post('ingredientes-genericos/actualizar', 'IngredientesGenericosController', 'actualizar');
$router->post('ingredientes-genericos/eliminar',   'IngredientesGenericosController', 'eliminar');
$router->get('ingredientes-genericos/buscar',      'IngredientesGenericosController', 'buscar');
$router->post('ingredientes-genericos/guardar-umbral-margen', 'IngredientesGenericosController', 'guardarUmbralMargen');
$router->get('ingredientes-genericos/variantes',          'IngredientesGenericosController', 'variantes');
$router->get('ingredientes-genericos/buscar-variantes',   'IngredientesGenericosController', 'buscarVariantes');
$router->post('ingredientes-genericos/asignar-variante',  'IngredientesGenericosController', 'asignarVariante');
$router->post('ingredientes-genericos/quitar-variante',   'IngredientesGenericosController', 'quitarVariante');

// Insumos
$router->get('insumos',              'InsumosController', 'index');
$router->post('insumos/crear',       'InsumosController', 'crear');
$router->post('insumos/actualizar',  'InsumosController', 'actualizar');
$router->post('insumos/eliminar',    'InsumosController', 'eliminar');
$router->post('insumos/importar',    'InsumosController', 'importar');
$router->get('insumos/plantilla',    'InsumosController', 'plantilla');

// Clientes
$router->get('clientes',              'ClientesController', 'index');
$router->get('clientes/nuevo',        'ClientesController', 'nuevo');
$router->post('clientes/crear',       'ClientesController', 'crear');
$router->get('clientes/editar',       'ClientesController', 'editar');
$router->post('clientes/actualizar',  'ClientesController', 'actualizar');
$router->post('clientes/eliminar',    'ClientesController', 'eliminar');
$router->get('clientes/buscar',       'ClientesController', 'buscar');
$router->get('clientes/saldo-cuenta-corriente', 'ClientesController', 'saldoCuentaCorriente');
$router->get('clientes/catalogo',          'ClientesController', 'catalogo');
$router->post('clientes/catalogo/asignar', 'ClientesController', 'catalogoAsignar');
$router->post('clientes/catalogo/quitar',  'ClientesController', 'catalogoQuitar');

// Portal de Clientes Mayoristas (ver docs/portal-clientes-mayoristas-checklist.md)
$router->get('portal/login',   'PortalController', 'loginForm');
$router->post('portal/login',  'PortalController', 'login');
$router->get('portal/logout',  'PortalController', 'logout');
$router->get('portal/recuperar',       'PortalController', 'recuperarForm');
$router->post('portal/recuperar',      'PortalController', 'recuperar');
$router->get('portal/reset-password',  'PortalController', 'resetPasswordForm');
$router->post('portal/reset-password', 'PortalController', 'resetPassword');
$router->get('portal/cambiar-password',  'PortalController', 'cambiarPasswordForm');
$router->post('portal/cambiar-password', 'PortalController', 'cambiarPassword');
$router->get('portal',                   'PortalController', 'index');
$router->get('portal/cuenta-corriente',  'PortalController', 'cuentaCorriente');
$router->get('portal/opciones-producto', 'PortalController', 'opcionesProducto');
$router->post('portal/pedido/crear',     'PortalController', 'crearPedido');
$router->get('portal/pedidos',           'PortalController', 'pedidos');
$router->get('portal/pedido/chat',       'PortalController', 'chat');
$router->post('portal/pedido/chat/enviar','PortalController', 'chatEnviar');
$router->get('portal/pedido/estado-poll', 'PortalController', 'estadoPoll');
$router->get('portal/vapid-public-key',  'PortalController', 'vapidPublicKey');
$router->post('portal/suscribir',        'PortalController', 'suscribir');
$router->post('portal/desuscribir',      'PortalController', 'desuscribir');

// Lado staff: aceptar/rechazar/chatear pedidos de clientes desde el POS
$router->get('pedidos-clientes/pendiente-para-mi',    'PedidosClientesController', 'pendienteParaMi');
$router->get('pedidos-clientes/hay-mensajes-sin-leer','PedidosClientesController', 'hayMensajesSinLeer');
$router->post('pedidos-clientes/aceptar',          'PedidosClientesController', 'aceptar');
$router->post('pedidos-clientes/rechazar',         'PedidosClientesController', 'rechazar');
$router->get('pedidos-clientes/chat',              'PedidosClientesController', 'chat');
$router->post('pedidos-clientes/chat/enviar',      'PedidosClientesController', 'chatEnviar');

// Cobros de Clientes (cuenta corriente del POS, ver docs/pos-cliente-cuenta-corriente-checklist.md)
$router->get('cobros-clientes',                  'CobrosClientesController', 'index');
$router->get('cobros-clientes/cuenta-corriente',  'CobrosClientesController', 'cuentaCorriente');
$router->get('cobros-clientes/nuevo',             'CobrosClientesController', 'nuevo');
$router->post('cobros-clientes/guardar',          'CobrosClientesController', 'guardar');
$router->get('cobros-clientes/ver',               'CobrosClientesController', 'ver');
$router->post('cobros-clientes/anular',           'CobrosClientesController', 'anular');
$router->get('cobros-clientes/ventas-pendientes', 'CobrosClientesController', 'ventasPendientes');
$router->post('clientes/importar',    'ClientesController',  'importar');
$router->get('clientes/plantilla',    'ClientesController',  'plantilla');
$router->post('clientes/guardar-config-portal', 'ClientesController', 'guardarConfigPortal');
$router->get('clientes/vista-portal',           'ClientesController', 'vistaPortal');
$router->get('clientes/opciones-producto-preview', 'ClientesController', 'opcionesProductoPreview');

// Proveedores
$router->get('proveedores',              'ProveedoresController', 'index');
$router->get('proveedores/nuevo',        'ProveedoresController', 'nuevo');
$router->post('proveedores/crear',       'ProveedoresController', 'crear');
$router->get('proveedores/editar',       'ProveedoresController', 'editar');
$router->post('proveedores/actualizar',  'ProveedoresController', 'actualizar');
$router->post('proveedores/eliminar',    'ProveedoresController', 'eliminar');
$router->post('proveedores/importar',       'ProveedoresController', 'importar');
$router->post('proveedores/previsualizar',  'ProveedoresController', 'previsualizar');
$router->get('proveedores/plantilla',       'ProveedoresController', 'plantilla');
$router->post('proveedores/toggle-activo',  'ProveedoresController', 'toggleActivo');
$router->get('proveedores/identidades',           'ProveedoresController', 'identidades');
$router->post('proveedores/identidades/agregar',  'ProveedoresController', 'agregarIdentidad');
$router->post('proveedores/identidades/eliminar', 'ProveedoresController', 'eliminarIdentidad');
$router->get('proveedores/alias-items',            'ProveedoresController', 'aliasItems');
$router->post('proveedores/alias-items/eliminar',  'ProveedoresController', 'eliminarAliasItem');
$router->post('proveedores/alias-items/reasignar', 'ProveedoresController', 'reasignarAliasItem');

// Facturas de Compra
$router->get('compras',                  'ComprasController', 'index');
$router->get('compras/nueva',            'ComprasController', 'nueva');
$router->post('compras/guardar',         'ComprasController', 'guardar');
$router->get('compras/ver',              'ComprasController', 'ver');
$router->post('compras/anular',          'ComprasController', 'anular');
$router->get('compras/imprimir',         'ComprasController', 'imprimir');
$router->get('compras/buscar-items',     'ComprasController', 'buscarItems');
$router->post('compras/crear-item',      'ComprasController', 'crearItem');
$router->get('compras/ultima-compra',    'ComprasController', 'ultimaCompra');
$router->get('compras/editar',           'ComprasController', 'editar');
$router->post('compras/actualizar',      'ComprasController', 'actualizar');
$router->post('compras/subir-comprobante', 'ComprasController', 'subirComprobante');
$router->get('compras/comprobante',      'ComprasController', 'verComprobante');
$router->post('compras/subir-comprobantes-adjuntos', 'ComprasController', 'subirComprobantesAdjuntos');
$router->post('compras/eliminar-comprobante-adjunto', 'ComprasController', 'eliminarComprobanteAdjunto');
$router->get('compras/comprobante-adjunto',           'ComprasController', 'verComprobanteAdjunto');
$router->post('compras/importar',        'ComprasController', 'importar');
$router->post('compras/previsualizar',   'ComprasController', 'previsualizar');
$router->get('compras/plantilla',        'ComprasController', 'plantilla');
$router->get('compras/exportar',         'ComprasController', 'exportar');

// Pagos a Proveedores / Cuenta Corriente
$router->get('pagos-proveedores',                          'PagosProveedoresController', 'index');
$router->get('pagos-proveedores/cuenta-corriente',         'PagosProveedoresController', 'cuentaCorriente');
$router->get('pagos-proveedores/nuevo',                    'PagosProveedoresController', 'nuevo');
$router->post('pagos-proveedores/guardar',                 'PagosProveedoresController', 'guardar');
$router->get('pagos-proveedores/ver',                      'PagosProveedoresController', 'ver');
$router->post('pagos-proveedores/anular',                  'PagosProveedoresController', 'anular');
$router->post('pagos-proveedores/actualizar',              'PagosProveedoresController', 'actualizar');
$router->post('pagos-proveedores/vincular',                'PagosProveedoresController', 'vincular');
$router->get('pagos-proveedores/facturas-pendientes',      'PagosProveedoresController', 'facturasPendientes');
$router->get('pagos-proveedores/gastos-pendientes',        'PagosProveedoresController', 'gastosPendientes');
$router->get('pagos-proveedores/pagos-de-socio',           'PagosProveedoresController', 'pagosDeSocio');
$router->get('pagos-proveedores/comprobante',              'PagosProveedoresController', 'comprobante');
$router->post('pagos-proveedores/subir-comprobantes-adjuntos', 'PagosProveedoresController', 'subirComprobantesAdjuntos');
$router->post('pagos-proveedores/eliminar-comprobante-adjunto','PagosProveedoresController', 'eliminarComprobanteAdjunto');
$router->get('pagos-proveedores/comprobante-adjunto',          'PagosProveedoresController', 'verComprobanteAdjunto');
// Reembolsos a socios (saldar la deuda que la empresa tiene con ellos, ver ReembolsoSocioModel)
$router->get('pagos-proveedores/aportes-pendientes',       'PagosProveedoresController', 'aportesPendientes');
$router->get('pagos-proveedores/cuenta-corriente-socio',   'PagosProveedoresController', 'cuentaCorrienteSocio');
$router->get('pagos-proveedores/reembolso-nuevo',          'PagosProveedoresController', 'reembolsoNuevo');
$router->post('pagos-proveedores/reembolso-guardar',       'PagosProveedoresController', 'reembolsoGuardar');
$router->get('pagos-proveedores/reembolso-ver',             'PagosProveedoresController', 'reembolsoVer');
$router->post('pagos-proveedores/reembolso-anular',         'PagosProveedoresController', 'reembolsoAnular');
$router->get('pagos-proveedores/reembolso-comprobante',     'PagosProveedoresController', 'reembolsoComprobante');
$router->post('pagos-proveedores/vincular-aporte',          'PagosProveedoresController', 'vincularAporte');

$router->get('empleados',                'EmpleadosController', 'index');
$router->get('empleados/nuevo',          'EmpleadosController', 'nuevo');
$router->post('empleados/crear',         'EmpleadosController', 'crear');
$router->get('empleados/editar',         'EmpleadosController', 'editar');
$router->post('empleados/actualizar',    'EmpleadosController', 'actualizar');
$router->post('empleados/eliminar',      'EmpleadosController', 'eliminar');
$router->post('empleados/toggle-activo', 'EmpleadosController', 'toggleActivo');
$router->get('empleados/ver',            'EmpleadosController', 'ver');
$router->post('empleados/jornadas/agregar',   'EmpleadosController', 'agregarJornada');
$router->post('empleados/jornadas/eliminar',  'EmpleadosController', 'eliminarJornada');
$router->post('empleados/conceptos/agregar',  'EmpleadosController', 'agregarConcepto');
$router->post('empleados/conceptos/eliminar', 'EmpleadosController', 'eliminarConcepto');
$router->get('empleados/conceptos-tipos',            'EmpleadosController', 'conceptosTipos');
$router->post('empleados/conceptos-tipos/guardar',   'EmpleadosController', 'guardarConceptoTipo');
$router->post('empleados/conceptos-tipos/actualizar','EmpleadosController', 'actualizarConceptoTipo');
$router->post('empleados/conceptos-tipos/eliminar',  'EmpleadosController', 'eliminarConceptoTipo');
$router->get('empleados/pendientes',          'EmpleadosController', 'pendientes');
$router->post('empleados/dia/guardar',        'EmpleadosController', 'guardarDia');
$router->post('empleados/dia/eliminar',       'EmpleadosController', 'eliminarDia');
$router->post('empleados/horas/recuperar',    'EmpleadosController', 'recuperarHoras');

$router->get('feriados',           'FeriadosController', 'index');
$router->post('feriados/crear',    'FeriadosController', 'crear');
$router->post('feriados/eliminar', 'FeriadosController', 'eliminar');

// Recetas
$router->get('recetas',                       'RecetasController', 'index');
$router->get('recetas/nueva',                 'RecetasController', 'nueva');
$router->post('recetas/guardar',              'RecetasController', 'guardar');
$router->get('recetas/editar',                'RecetasController', 'editar');
$router->post('recetas/actualizar',           'RecetasController', 'actualizar');
$router->get('recetas/imprimir',              'RecetasController', 'imprimir');
$router->get('recetas/libro',                 'RecetasController', 'libro');
$router->post('recetas/eliminar',             'RecetasController', 'eliminar');
$router->post('recetas/duplicar',             'RecetasController', 'duplicar');
$router->get('recetas/produccion',            'RecetasController', 'produccion');
$router->post('recetas/producir',             'RecetasController', 'producir');
$router->get('recetas/buscar-ingredientes',   'RecetasController', 'buscarIngredientes');
$router->get('recetas/detalle-costo',         'RecetasController', 'detalleCosto');
$router->get('recetas/versiones',             'RecetasController', 'versiones');
$router->get('recetas/historial-costo',       'RecetasController', 'historialCosto');
$router->get('recetas/ordenes',              'RecetasController', 'ordenes');
$router->post('recetas/planificar',          'RecetasController', 'planificar');
$router->post('recetas/ordenes/estado',      'RecetasController', 'cambiarEstadoOrden');
$router->post('recetas/importar',            'RecetasController', 'importar');
$router->post('recetas/previsualizar',       'RecetasController', 'previsualizar');
$router->get('recetas/plantilla',            'RecetasController', 'plantilla');
$router->get('recetas/exportar',             'RecetasController', 'exportar');
$router->post('recetas/asignar-producto',    'RecetasController', 'asignarProducto');
$router->post('recetas/quitar-producto',     'RecetasController', 'quitarProducto');

// Simulador de Recetas (recetas borrador para costear productos nuevos)
$router->get('recetas-borrador',                    'RecetasBorradorController', 'index');
$router->get('recetas-borrador/nuevo',               'RecetasBorradorController', 'nuevo');
$router->post('recetas-borrador/guardar',            'RecetasBorradorController', 'guardar');
$router->get('recetas-borrador/editar',               'RecetasBorradorController', 'editar');
$router->post('recetas-borrador/actualizar',          'RecetasBorradorController', 'actualizar');
$router->post('recetas-borrador/eliminar',            'RecetasBorradorController', 'eliminar');
$router->get('recetas-borrador/buscar-ingredientes',  'RecetasBorradorController', 'buscarIngredientes');
$router->post('recetas-borrador/promover',            'RecetasBorradorController', 'promover');

// Ventas
$router->get('ventas',                      'VentasController', 'index');
$router->get('ventas/pos',                  'VentasController', 'pos');
$router->post('ventas/guardar',             'VentasController', 'guardar');
$router->get('ventas/comprobantes-afip',    'VentasController', 'comprobantesAfip');
$router->get('ventas/verificar-afip',       'VentasController', 'verificarAfip');
$router->get('ventas/historico-afip',       'VentasController', 'historicoAfip');
$router->get('ventas/ver',                  'VentasController', 'ver');
$router->post('ventas/anular',              'VentasController', 'anular');
$router->post('ventas/cambiar-medio-pago',  'VentasController', 'cambiarMedioPago');
$router->post('ventas/solicitar-anulacion', 'VentasController', 'solicitarAnulacion');
$router->post('ventas/resolver-anulacion',  'VentasController', 'resolverAnulacion');
$router->get('ventas/solicitudes-anulacion','VentasController', 'solicitudesAnulacion');
$router->get('ventas/ticket',               'VentasController', 'ticket');
$router->get('ventas/comanda',              'VentasController', 'comanda');
$router->get('ventas/buscar-productos',     'VentasController', 'buscarProductos');
$router->get('ventas/categorias-pos',       'VentasController', 'categoriasPos');
$router->get('ventas/productos-por-categoria', 'VentasController', 'productosPorCategoria');
$router->get('ventas/opciones-producto',       'VentasController', 'opcionesProducto');
$router->get('ventas/catalogo-offline',     'VentasController', 'catalogoOffline');
$router->post('ventas/importar',            'VentasController', 'importar');
$router->post('ventas/previsualizar',       'VentasController', 'previsualizar');
$router->get('ventas/plantilla',            'VentasController', 'plantilla');

// Solicitudes (compras / tareas) + Web Push
$router->get('solicitudes',                    'SolicitudesController', 'index');
$router->post('solicitudes/crear',              'SolicitudesController', 'crear');
$router->post('solicitudes/actualizar',         'SolicitudesController', 'actualizar');
$router->post('solicitudes/convertir-a-tarea',  'SolicitudesController', 'convertirATarea');
$router->post('solicitudes/cambiar-estado',     'SolicitudesController', 'cambiarEstado');
$router->get('solicitudes/buscar-items',        'SolicitudesController', 'buscarItems');
$router->get('solicitudes/items-por-categoria', 'SolicitudesController', 'itemsPorCategoria');
$router->get('solicitudes/pendiente-para-mi',   'SolicitudesController', 'pendienteParaMi');
$router->post('solicitudes/aceptar-inventario',   'SolicitudesController', 'aceptarInventario');
$router->post('solicitudes/rechazar-inventario',  'SolicitudesController', 'rechazarInventario');
$router->post('solicitudes/aplazar-inventario',   'SolicitudesController', 'aplazarInventario');
$router->post('solicitudes/finalizar-inventario', 'SolicitudesController', 'finalizarInventario');
$router->get('solicitudes/vapid-public-key',    'SolicitudesController', 'vapidPublicKey');
$router->post('solicitudes/suscribir',          'SolicitudesController', 'suscribir');
$router->post('solicitudes/desuscribir',        'SolicitudesController', 'desuscribir');

// Solicitudes de inventario recurrentes (plantillas semanales, ver docs/solicitudes-inventario-checklist.md)
$router->post('solicitudes/recurrentes/crear',          'SolicitudesController', 'crearRecurrente');
$router->post('solicitudes/recurrentes/actualizar',     'SolicitudesController', 'actualizarRecurrente');
$router->post('solicitudes/recurrentes/toggle-activo',  'SolicitudesController', 'toggleActivoRecurrente');
$router->post('solicitudes/recurrentes/eliminar',       'SolicitudesController', 'eliminarRecurrente');

// Tareas asignadas a trabajadores (ver docs/tareas-pendientes.md #2)
$router->get('tareas/pendiente-para-mi', 'TareasController', 'pendienteParaMi');
$router->post('tareas/crear',            'TareasController', 'crear');
$router->post('tareas/actualizar',       'TareasController', 'actualizar');
$router->post('tareas/eliminar',         'TareasController', 'eliminar');
$router->post('tareas/aceptar',          'TareasController', 'aceptar');
$router->post('tareas/rechazar',         'TareasController', 'rechazar');
$router->post('tareas/aplazar',          'TareasController', 'aplazar');
$router->post('tareas/finalizar',        'TareasController', 'finalizar');

// Bandeja de notificaciones del header (ver docs/tareas-pendientes.md #3)
$router->get('notificaciones/listar',        'NotificacionesController', 'listar');
$router->post('notificaciones/marcar-leida', 'NotificacionesController', 'marcarLeida');

// Stock
$router->get('stock',                 'StockController', 'index');
$router->post('stock/ajuste',         'StockController', 'ajuste');
$router->get('stock/reporte',         'StockController', 'reporte');
$router->get('stock/inventario',      'StockController', 'inventario');
$router->post('stock/inventario/guardar', 'StockController', 'guardarInventario');
$router->get('stock/apertura',            'StockController', 'apertura');
$router->post('stock/apertura/guardar',   'StockController', 'guardarApertura');
$router->get('stock/historial-item',      'StockController', 'historialItem');
$router->post('stock/importar',           'StockController', 'importar');
$router->get('stock/plantilla',           'StockController', 'plantilla');

// Lista de Compras
$router->get('lista-compras',                       'ListaComprasController', 'index');
$router->post('lista-compras/confirmar-sugerido',    'ListaComprasController', 'confirmarSugerido');
$router->post('lista-compras/descartar-sugerido',    'ListaComprasController', 'descartarSugerido');
$router->post('lista-compras/marcar-comprado',       'ListaComprasController', 'marcarComprado');
$router->post('lista-compras/quitar',                'ListaComprasController', 'quitar');
$router->post('lista-compras/agregar',               'ListaComprasController', 'agregarManual');

// Resumen Económico
$router->get('resumen-economico', 'ResumenEconomicoController', 'index');
$router->post('resumen-economico/ingresos/agregar',  'ResumenEconomicoController', 'agregarIngreso');
$router->post('resumen-economico/ingresos/eliminar', 'ResumenEconomicoController', 'eliminarIngreso');

// Gastos
$router->get('gastos',                          'GastosController', 'index');
$router->post('gastos/crear',                   'GastosController', 'crear');
$router->post('gastos/actualizar',              'GastosController', 'actualizar');
$router->post('gastos/eliminar',                'GastosController', 'eliminar');
$router->get('gastos/comprobante',              'GastosController', 'verComprobante');
$router->get('gastos/categorias',               'GastosController', 'categorias');
$router->post('gastos/categorias/crear',        'GastosController', 'crearCategoria');
$router->post('gastos/categorias/actualizar',   'GastosController', 'actualizarCategoria');
$router->post('gastos/categorias/eliminar',     'GastosController', 'eliminarCategoria');

// Gastos Recurrentes (costos fijos que se generan solos cada mes)
$router->get('gastos-recurrentes',                'GastosRecurrentesController', 'index');
$router->post('gastos-recurrentes/crear',         'GastosRecurrentesController', 'crear');
$router->post('gastos-recurrentes/actualizar',    'GastosRecurrentesController', 'actualizar');
$router->post('gastos-recurrentes/toggle-activo', 'GastosRecurrentesController', 'toggleActivo');
$router->post('gastos-recurrentes/eliminar',      'GastosRecurrentesController', 'eliminar');

// Activos Fijos (ver docs/activos-fijos-diseno.md)
$router->get('activos',            'ActivosFijosController', 'index');
$router->get('activos/nuevo',      'ActivosFijosController', 'nuevo');
$router->post('activos/crear',     'ActivosFijosController', 'crear');
$router->get('activos/editar',     'ActivosFijosController', 'editar');
$router->post('activos/actualizar','ActivosFijosController', 'actualizar');
$router->post('activos/eliminar',  'ActivosFijosController', 'eliminar');
$router->get('activos/buscar-compras', 'ActivosFijosController', 'buscarCompras');

// Caja y Turnos
$router->get('caja',                  'CajaController', 'index');
$router->post('caja/abrir',           'CajaController', 'abrir');
// Imputa a la caja una venta cobrada en mano sin turno abierto (ventas.cobro_pendiente = 1,
// típicamente un pedido de MenuApp entregado fuera de turno).
$router->post('caja/imputar-cobro-pendiente', 'CajaController', 'imputarCobroPendiente');
$router->post('caja/prever-cierre',   'CajaController', 'preverCierre');
$router->post('caja/cerrar',          'CajaController', 'cerrar');
$router->post('caja/movimiento',      'CajaController', 'movimiento');
$router->get('caja/historial',        'CajaController', 'historial');
$router->get('caja/ticket',           'CajaController', 'ticket');
$router->get('caja/diferencias',      'CajaController', 'diferencias');

// Reportes
$router->get('reportes/ventas',       'ReportesController', 'ventas');
$router->get('reportes/semanal',      'ReportesController', 'semanal');
$router->post('reportes/guardar-categoria-monotributo', 'ReportesController', 'guardarCategoriaMonotributo');
$router->post('reportes/guardar-bloqueo-facturacion-afip', 'ReportesController', 'guardarBloqueoFacturacionAfip');
$router->post('reportes/guardar-limite-facturacion-manual', 'ReportesController', 'guardarLimiteFacturacionManual');
$router->post('reportes/guardar-prorateo-diario', 'ReportesController', 'guardarProrateoDiario');
// Opción D9: si los precios de venta cargados ya traen IVA (ver core/PrecioHelper.php).
$router->post('reportes/guardar-precios-incluyen-iva', 'ReportesController', 'guardarPreciosIncluyenIva');
$router->get('reportes/compras',      'ReportesController', 'compras');
$router->get('reportes/compras-precio-cero', 'ReportesController', 'comprasPrecioCero');
$router->get('reportes/costos-desviados',    'ReportesController', 'costosDesviados');
$router->post('reportes/recalcular-costos',  'ReportesController', 'recalcularCostos');
$router->get('reportes/ventas-sin-stock',    'ReportesController', 'ventasSinStock');
$router->get('reportes/stock',        'ReportesController', 'stock');
$router->get('reportes/caja',         'ReportesController', 'caja');
$router->get('reportes/produccion',   'ReportesController', 'produccion');
$router->get('reportes/nomina',       'ReportesController', 'nomina');
$router->get('reportes/pagos',        'ReportesController', 'pagos');
$router->get('reportes/exportar',     'ReportesController', 'exportarCsv');
$router->get('reportes/mermas-desvios',        'ReportesController', 'mermasDesvios');
$router->get('reportes/historial-desvio-item', 'ReportesController', 'historialDesvioItem');
$router->get('reportes/margen-ventas',                     'ReportesController', 'margenVentas');
$router->post('reportes/recalcular-costos-historicos',     'ReportesController', 'recalcularCostosHistoricos');

// Reportes IA (generados por el Chat IA, ver ChatController::generarReportePersonalizado/Predefinido)
$router->get('reportes-ia',            'ReportesIaController', 'index');
$router->get('reportes-ia/ver',        'ReportesIaController', 'ver');
$router->get('reportes-ia/exportar',   'ReportesIaController', 'exportarXlsx');
$router->post('reportes-ia/eliminar',  'ReportesIaController', 'eliminar');

// Proyecciones (ver docs/prediccion-ventas-compras.md — generadas por cron/proyecciones_generar.php)
$router->get('proyecciones',                  'ProyeccionesController', 'index');
$router->post('proyecciones/guardar-config',  'ProyeccionesController', 'guardarConfig');

// Chat IA
$router->get('chat',                  'ChatController', 'index');
$router->get('chat/historial-reciente', 'ChatController', 'historialReciente');
$router->post('chat/mensaje',         'ChatController', 'mensaje');
$router->post('chat/accion-directa',  'ChatController', 'accionDirecta');
$router->post('chat/confirmar',       'ChatController', 'confirmar');
$router->post('chat/cancelar',        'ChatController', 'cancelar');
$router->get('chat/buscar-proveedores', 'ChatController', 'buscarProveedores');
$router->get('chat/buscar-items',       'ChatController', 'buscarItems');
$router->post('chat/limpiar',         'ChatController', 'limpiar');
$router->get('chat/accesos',          'ChatAccesosController', 'index');
$router->post('chat/accesos/crear',      'ChatAccesosController', 'crear');
$router->post('chat/accesos/actualizar', 'ChatAccesosController', 'actualizar');
$router->post('chat/accesos/eliminar',   'ChatAccesosController', 'eliminar');
$router->post('chat/compartir',       'ChatController', 'compartir');
$router->get('chat/ver-usuario',      'ChatController', 'verUsuario');
$router->get('chat/ver-adjunto',      'ChatController', 'verAdjunto');

// Gemini Usage
$router->get('gemini-uso',            'GeminiUsageController', 'index');
$router->post('gemini-uso/config',    'GeminiUsageController', 'guardarConfig');
$router->post('gemini-uso/limites',   'GeminiUsageController', 'guardarLimitesModelos');
$router->get('gemini-log',            'GeminiLogController', 'index');

// Log de auditoría
$router->get('audit-log',             'AuditLogController', 'index');

// Analítica de uso (ver docs/auditoria-analitica-uso.md)
$router->get('analitica',             'AnaliticaController', 'index');
$router->get('analitica/detalle-modulo', 'AnaliticaController', 'detalleModulo');

// Permisos por módulo
$router->get('permisos',         'PermisosController', 'index');
$router->post('permisos/guardar','PermisosController', 'guardar');
$router->post('permisos/guardar-config-seguridad', 'PermisosController', 'guardarConfigSeguridad');

// Plantillas de permisos ("Permission Sets" — ver database/plantillas_permisos_migration.sql)
$router->get('permisos/plantillas',                    'PlantillaPermisosController', 'index');
$router->post('permisos/plantillas/crear',              'PlantillaPermisosController', 'crear');
$router->get('permisos/plantillas/editar',              'PlantillaPermisosController', 'editar');
$router->post('permisos/plantillas/actualizar-cabecera','PlantillaPermisosController', 'actualizarCabecera');
$router->post('permisos/plantillas/guardar-matriz',     'PlantillaPermisosController', 'guardarMatriz');
$router->post('permisos/plantillas/desactivar',         'PlantillaPermisosController', 'desactivar');
$router->post('permisos/plantillas/activar',            'PlantillaPermisosController', 'activar');
$router->post('permisos/plantillas/asignar-default-rol','PlantillaPermisosController', 'asignarDefaultRol');

// Guías del Help Desk del Chat IA (ver docs/tareas-pendientes.md, sección Help Desk)
$router->get('ayuda-temas',             'AyudaTemasController', 'index');
$router->get('ayuda-temas/nuevo',       'AyudaTemasController', 'nuevo');
$router->get('ayuda-temas/editar',      'AyudaTemasController', 'editar');
$router->post('ayuda-temas/guardar',    'AyudaTemasController', 'guardar');
$router->post('ayuda-temas/actualizar', 'AyudaTemasController', 'actualizar');
$router->post('ayuda-temas/activar',    'AyudaTemasController', 'activar');
$router->post('ayuda-temas/desactivar', 'AyudaTemasController', 'desactivar');

// Integraciones con sistemas externos (ver docs/integracion-menuapp-checklist.md).
// Rutas exactas porque los nombres llevan guión: la resolución convencional del Router
// buscaría un método "generar-token", que no es un identificador PHP válido.
$router->get('integraciones',                 'IntegracionesController', 'menuapp');
$router->get('integraciones/menuapp',         'IntegracionesController', 'menuapp');
$router->post('integraciones/generar-token',  'IntegracionesController', 'generarToken');
$router->post('integraciones/guardar',        'IntegracionesController', 'guardar');
$router->post('integraciones/activar',        'IntegracionesController', 'activar');
$router->post('integraciones/probar',         'IntegracionesController', 'probar');

// Backup
$router->get('admin/backup',           'BackupController', 'index');
$router->post('admin/backup/download', 'BackupController', 'download');

// Logs del sistema
$router->get('logs',            'LogsController', 'index');
$router->get('logs/contenido',  'LogsController', 'contenido');
$router->get('logs/descargar',  'LogsController', 'descargar');

// Documentación del sistema
$router->get('docs',              'DocsController', 'index');
$router->get('docs/descargar',    'DocsController', 'descargar');

// Ecommerce (admin)
$router->get('ecommerce/pedidos',        'EcommerceController', 'pedidos');
$router->get('ecommerce/pedido',         'EcommerceController', 'verPedido');
$router->post('ecommerce/estado',        'EcommerceController', 'cambiarEstado');

// Ecommerce (catálogo público — sin auth)
$router->get('tienda',                   'TiendaController', 'index');
$router->get('tienda/categoria',         'TiendaController', 'categoria');
$router->get('tienda/producto',          'TiendaController', 'producto');
$router->get('tienda/carrito',           'TiendaController', 'carrito');
$router->post('tienda/checkout',         'TiendaController', 'checkout');
$router->get('tienda/pago/brick',        'TiendaController', 'pagoBrick');
$router->post('tienda/pago/procesar',    'TiendaController', 'procesarPagoBrick');
$router->get('tienda/pago/pendiente',    'TiendaController', 'pagoPendiente');
$router->get('tienda/pago/exito',        'TiendaController', 'pagoExito');
$router->get('tienda/pago/fallo',        'TiendaController', 'pagoFallo');
$router->post('tienda/webhook/mp',       'TiendaController', 'webhookMercadoPago');

// ── Dispatch ─────────────────────────────────────────────────────────────────
$url        = $_GET['url'] ?? '';
$httpMethod = $_SERVER['REQUEST_METHOD'];

// ─────────────────────────────────────────────────────────────────────────────
// API de MenuApp (ver docs/integracion-menuapp-checklist.md, tarea 1.1)
//
// Se resuelve ACÁ, antes del dispatch, y no con $router->get()/post(), por dos razones:
//  1. El Router solo hace match exacto o convencional (/controlador/metodo/...), y
//     "api/menuapp/v1/catalogo" caería en un ApiController inexistente.
//  2. Dos de las rutas llevan la referencia del pedido en el medio de la URL
//     (ventas/{referencia}/cobrar), que el Router tampoco sabe extraer.
//
// Estas rutas NO usan sesión ni CSRF: se autentican con `Authorization: Bearer <token>`,
// validado dentro de cada acción con requireApiToken() (el router de esta app no tiene
// middleware, así que la llamada va explícita en cada una).
// ─────────────────────────────────────────────────────────────────────────────
if (str_starts_with(trim($url, '/'), 'api/menuapp/v1/')) {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/app/Controllers/ApiMenuAppController.php';

    $ruta = substr(trim($url, '/'), strlen('api/menuapp/v1/'));
    $api  = new ApiMenuAppController();

    if ($httpMethod === 'GET' && $ruta === 'estado') {
        $api->estado();
    } elseif ($httpMethod === 'GET' && $ruta === 'catalogo') {
        $api->catalogo();
    } elseif ($httpMethod === 'GET' && $ruta === 'disponibilidad') {
        $api->disponibilidad();
    } elseif ($httpMethod === 'POST' && $ruta === 'ventas') {
        $api->ventas();
    } elseif ($httpMethod === 'POST' && preg_match('#^ventas/([^/]+)/cobrar$#', $ruta, $m)) {
        $api->cobrar($m[1]);
    } elseif ($httpMethod === 'POST' && preg_match('#^ventas/([^/]+)/anular$#', $ruta, $m)) {
        $api->anular($m[1]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'ruta_inexistente', 'ruta' => $ruta], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$router->dispatch($url, $httpMethod);
