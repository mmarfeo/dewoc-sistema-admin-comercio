<?php
declare(strict_types=1);

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

// Dashboard
$router->get('',             'DashboardController', 'index');
$router->get('dashboard',    'DashboardController', 'index');

// Usuarios
$router->get('usuarios',         'UsuariosController', 'index');
$router->get('usuarios/nuevo',   'UsuariosController', 'nuevo');
$router->post('usuarios/crear',  'UsuariosController', 'crear');
$router->get('usuarios/editar',  'UsuariosController', 'editar');
$router->post('usuarios/actualizar', 'UsuariosController', 'actualizar');
$router->post('usuarios/eliminar',         'UsuariosController', 'eliminar');
$router->get('usuarios/perfil',            'UsuariosController', 'perfil');
$router->post('usuarios/cambiar-password', 'UsuariosController', 'cambiarPassword');
$router->post('usuarios/subir-foto',       'UsuariosController', 'subirFoto');

// Categorías
$router->get('categorias',           'CategoriasController', 'index');
$router->post('categorias/crear',    'CategoriasController', 'crear');
$router->post('categorias/actualizar', 'CategoriasController', 'actualizar');
$router->post('categorias/eliminar', 'CategoriasController', 'eliminar');

// Productos
$router->get('productos',            'ProductosController', 'index');
$router->get('productos/nuevo',      'ProductosController', 'nuevo');
$router->post('productos/crear',     'ProductosController', 'crear');
$router->get('productos/editar',     'ProductosController', 'editar');
$router->post('productos/actualizar','ProductosController', 'actualizar');
$router->post('productos/eliminar',  'ProductosController', 'eliminar');
$router->get('productos/qr',         'ProductosController', 'qr');

// Materias Primas
$router->get('materias-primas',                    'MateriasPrimasController', 'index');
$router->get('materias-primas/nuevo',              'MateriasPrimasController', 'nuevo');
$router->post('materias-primas/crear',             'MateriasPrimasController', 'crear');
$router->get('materias-primas/editar',             'MateriasPrimasController', 'editar');
$router->post('materias-primas/actualizar',        'MateriasPrimasController', 'actualizar');
$router->post('materias-primas/eliminar',          'MateriasPrimasController', 'eliminar');
$router->get('materias-primas/historial-precios',  'MateriasPrimasController', 'historialPrecios');

// Insumos
$router->get('insumos',              'InsumosController', 'index');
$router->post('insumos/crear',       'InsumosController', 'crear');
$router->post('insumos/actualizar',  'InsumosController', 'actualizar');
$router->post('insumos/eliminar',    'InsumosController', 'eliminar');

// Clientes
$router->get('clientes',              'ClientesController', 'index');
$router->get('clientes/nuevo',        'ClientesController', 'nuevo');
$router->post('clientes/crear',       'ClientesController', 'crear');
$router->get('clientes/editar',       'ClientesController', 'editar');
$router->post('clientes/actualizar',  'ClientesController', 'actualizar');
$router->post('clientes/eliminar',    'ClientesController', 'eliminar');
$router->get('clientes/buscar',       'ClientesController', 'buscar');

// Proveedores
$router->get('proveedores',           'ProveedoresController', 'index');
$router->get('proveedores/nuevo',     'ProveedoresController', 'nuevo');
$router->post('proveedores/crear',    'ProveedoresController', 'crear');
$router->get('proveedores/editar',    'ProveedoresController', 'editar');
$router->post('proveedores/actualizar','ProveedoresController','actualizar');
$router->post('proveedores/eliminar', 'ProveedoresController', 'eliminar');

// Facturas de Compra
$router->get('compras',                  'ComprasController', 'index');
$router->get('compras/nueva',            'ComprasController', 'nueva');
$router->post('compras/guardar',         'ComprasController', 'guardar');
$router->get('compras/ver',              'ComprasController', 'ver');
$router->post('compras/anular',          'ComprasController', 'anular');
$router->get('compras/imprimir',         'ComprasController', 'imprimir');
$router->get('compras/buscar-items',     'ComprasController', 'buscarItems');

// Recetas
$router->get('recetas',                       'RecetasController', 'index');
$router->get('recetas/nueva',                 'RecetasController', 'nueva');
$router->post('recetas/guardar',              'RecetasController', 'guardar');
$router->get('recetas/editar',                'RecetasController', 'editar');
$router->post('recetas/actualizar',           'RecetasController', 'actualizar');
$router->post('recetas/eliminar',             'RecetasController', 'eliminar');
$router->get('recetas/produccion',            'RecetasController', 'produccion');
$router->post('recetas/producir',             'RecetasController', 'producir');
$router->get('recetas/buscar-ingredientes',   'RecetasController', 'buscarIngredientes');
$router->get('recetas/versiones',             'RecetasController', 'versiones');
$router->get('recetas/ordenes',              'RecetasController', 'ordenes');
$router->post('recetas/planificar',          'RecetasController', 'planificar');
$router->post('recetas/ordenes/estado',      'RecetasController', 'cambiarEstadoOrden');

// Ventas
$router->get('ventas',                      'VentasController', 'index');
$router->get('ventas/pos',                  'VentasController', 'pos');
$router->post('ventas/guardar',             'VentasController', 'guardar');
$router->get('ventas/ver',                  'VentasController', 'ver');
$router->post('ventas/anular',              'VentasController', 'anular');
$router->get('ventas/ticket',               'VentasController', 'ticket');
$router->get('ventas/comanda',              'VentasController', 'comanda');
$router->get('ventas/buscar-productos',     'VentasController', 'buscarProductos');

// Stock
$router->get('stock',                 'StockController', 'index');
$router->post('stock/ajuste',         'StockController', 'ajuste');
$router->get('stock/reporte',         'StockController', 'reporte');
$router->get('stock/inventario',      'StockController', 'inventario');
$router->post('stock/inventario/guardar', 'StockController', 'guardarInventario');
$router->get('stock/apertura',            'StockController', 'apertura');
$router->post('stock/apertura/guardar',   'StockController', 'guardarApertura');
$router->get('stock/historial-item',      'StockController', 'historialItem');

// Caja y Turnos
$router->get('caja',                  'CajaController', 'index');
$router->post('caja/abrir',           'CajaController', 'abrir');
$router->post('caja/cerrar',          'CajaController', 'cerrar');
$router->post('caja/movimiento',      'CajaController', 'movimiento');
$router->get('caja/historial',        'CajaController', 'historial');

// Reportes
$router->get('reportes/ventas',       'ReportesController', 'ventas');
$router->get('reportes/compras',      'ReportesController', 'compras');
$router->get('reportes/stock',        'ReportesController', 'stock');
$router->get('reportes/caja',         'ReportesController', 'caja');
$router->get('reportes/produccion',   'ReportesController', 'produccion');

// Chat IA
$router->get('chat',                  'ChatController', 'index');
$router->post('chat/mensaje',         'ChatController', 'mensaje');

// Log de auditoría
$router->get('audit-log',             'AuditLogController', 'index');

// Permisos por módulo
$router->get('permisos',         'PermisosController', 'index');
$router->post('permisos/guardar','PermisosController', 'guardar');

// Backup
$router->get('admin/backup',           'BackupController', 'index');
$router->post('admin/backup/download', 'BackupController', 'download');

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
$router->dispatch($url, $httpMethod);
