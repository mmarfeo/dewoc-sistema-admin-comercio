<?php
require_once BASE_PATH . '/app/Models/Model.php';

class PermisosModel extends Model
{
    protected string $table = 'permisos';

    const MODULOS = [
        'dashboard'         => 'Dashboard',
        // Junta en una sola pantalla lo último de cada reporte automático (semanal, margen
        // por producto, auditoría de compras/pagos) + el estado en vivo de lista de compras,
        // vencimientos de gastos y proyecciones — ver ResumenEjecutivoController. No exige
        // los permisos de cada uno de esos módulos por separado: cada sección se oculta sola
        // si el usuario no tiene el permiso de ese módulo puntual.
        'resumen_ejecutivo' => 'Resumen Ejecutivo',
        'ventas_pos'        => 'Punto de Venta (POS)',
        // "Editar" acá no habilita nada más en todo el sistema — es, literalmente, el único
        // permiso que existe para "Cambiar medio de pago" de una venta ya cobrada (ver
        // VentasController::cambiarMedioPago(), botón en /ventas y en el detalle de cada
        // venta). Aclarado en la etiqueta a propósito: antes decía solo "Ventas / Listado" y
        // un admin no tenía forma de adivinar, mirando /permisos, que ese checkbox era esto.
        'ventas'            => 'Ventas / Listado (Editar = cambiar medio de pago)',
        // Sin este permiso, el listado/detalle de Ventas y el historial de Caja quedan
        // filtrados a lo propio (usuario_id = quien está logueado) — pensado para roles
        // tipo Cajero, que no deberían poder ver lo que facturó/cobró otro cajero ni de
        // otra caja. El rol admin nunca se ve afectado (bypass total, como siempre).
        'ventas_ver_todas'  => 'Ventas: Ver ventas y turnos de caja de TODOS los usuarios (sin esto, cada uno ve solo lo propio)',
        'ventas_anulaciones'=> 'Solicitudes de Anulación',
        'alertas_facturacion_afip' => 'Alertas: Límite de facturación AFIP',
        'caja'              => 'Caja y Turnos',
        'resumen_economico' => 'Resumen Económico',
        'gastos'            => 'Gastos',
        'activos_fijos'     => 'Activos Fijos',
        'empleados'         => 'Empleados',
        'compras'           => 'Facturas de Compra',
        'proveedores'       => 'Proveedores',
        'pagos_proveedores' => 'Pagos / Cta. Cte.',
        'cobros_clientes'   => 'Cuenta Corriente de Clientes',
        'stock'             => 'Control de Stock',
        'lista_compras'     => 'Lista de Compras',
        'stock_inventario'  => 'Stock: Inventario físico (sin el resto de Control de Stock)',
        'stock_ver_teorico' => 'Stock: Ver "stock teórico" y ventas históricas',
        'stock_ver_costos'  => 'Stock: Ver columna "Costo unit." en el listado',
        'recetas'           => 'Recetas y Producción',
        'productos'         => 'Productos',
        'productos_ver_costos' => 'Productos: Ver costo de MP / recetas (columna "Recetas / Costo MP")',
        'materias_primas'   => 'Materias Primas',
        'insumos'           => 'Insumos',
        'categorias'        => 'Categorías',
        'opciones_productos'=> 'Opciones/Adicionales de Productos',
        'socios'            => 'Socios',
        'clientes'          => 'Clientes',
        'reportes'                     => 'Reportes',
        'reportes_semanal'             => 'Reportes: Resumen semanal',
        'reportes_ventas'              => 'Reportes: Ventas',
        'reportes_compras'             => 'Reportes: Compras',
        'reportes_pagos'               => 'Reportes: Pagos a proveedores',
        'reportes_stock'               => 'Reportes: Movimientos de stock',
        'reportes_caja'                => 'Reportes: Caja y turnos',
        'reportes_produccion'          => 'Reportes: Producción',
        'reportes_nomina'              => 'Reportes: Nómina',
        'reportes_mermas_desvios'      => 'Reportes: Mermas y desvíos',
        'reportes_compras_precio_cero' => 'Reportes: Compras a precio $0',
        'reportes_costos_desviados'    => 'Reportes: Costos desviados',
        'reportes_ventas_sin_stock'    => 'Reportes: Ventas sin stock',
        'reportes_proyecciones'        => 'Reportes: Proyecciones',
        'reportes_compras_pagos_usuario' => 'Reportes: Compras y pagos por usuario',
        'reportes_margen_producto'       => 'Reportes: Margen por producto',
        'reportes_cuentas_corrientes'    => 'Reportes: Cuentas corrientes (proveedores/clientes/socios)',
        'reportes_ia'       => 'Reportes IA',
        'ecommerce'         => 'Ecommerce',
        // Pantalla Integraciones → MenuApp: genera el token de la API y el secreto del
        // webhook, o sea las credenciales que le dan a otro sistema acceso al catálogo y a
        // las ventas. El controlador exige ADEMÁS rol admin (requireRole); este permiso es
        // solo para que el ítem aparezca en el menú y pueda bloquearse por plantilla.
        'integraciones'     => 'Integraciones (MenuApp)',
        'chat'              => 'Chat IA',
        'galeria'           => 'Galería',
        'solicitudes'       => 'Solicitudes',
        'tareas_asignar'    => 'Tareas: Asignar a trabajadores',
        'docs'              => 'Documentación',
    ];

    /**
     * Ruta real de cada módulo — la mayoría coincide con su clave, pero algunos
     * usan guiones en la URL (materias_primas→materias-primas) o no tienen un
     * index propio (reportes→reportes/ventas, ecommerce→ecommerce/pedidos).
     * La usa homeUrl() para resolver la "pantalla de inicio" de cada usuario.
     */
    const MODULOS_URL = [
        'resumen_ejecutivo'            => 'resumen-ejecutivo',
        'activos_fijos'                => 'activos',
        'ventas_pos'                   => 'ventas/pos',
        'materias_primas'              => 'materias-primas',
        'reportes_ia'                  => 'reportes-ia',
        'reportes'                     => 'reportes/ventas',
        'reportes_semanal'             => 'reportes/semanal',
        'reportes_ventas'              => 'reportes/ventas',
        'reportes_compras'             => 'reportes/compras',
        'reportes_pagos'               => 'reportes/pagos',
        'reportes_stock'               => 'reportes/stock',
        'reportes_caja'                => 'reportes/caja',
        'reportes_produccion'          => 'reportes/produccion',
        'reportes_nomina'              => 'reportes/nomina',
        'reportes_mermas_desvios'      => 'reportes/mermas-desvios',
        'reportes_compras_precio_cero' => 'reportes/compras-precio-cero',
        'reportes_costos_desviados'    => 'reportes/costos-desviados',
        'reportes_ventas_sin_stock'    => 'reportes/ventas-sin-stock',
        'reportes_margen_producto'     => 'reportes/margen-ventas',
        'reportes_proyecciones'        => 'proyecciones',
        'ecommerce'                    => 'ecommerce/pedidos',
        'integraciones'                => 'integraciones/menuapp',
        'pagos_proveedores'            => 'pagos-proveedores',
        'cobros_clientes'              => 'cobros-clientes',
        'resumen_economico'            => 'resumen-economico',
        'opciones_productos'           => 'opciones-productos',
        'tareas_asignar'               => 'solicitudes?vista=tareas',
        'lista_compras'                => 'lista-compras',
    ];

    /** Copia de MODULOS ordenada alfabéticamente por etiqueta (A-Z), para las tablas de selección. */
    public static function modulosOrdenados(): array
    {
        $modulos = self::MODULOS;
        asort($modulos);
        return $modulos;
    }

    /**
     * Non-admin roles. NO hace JOIN contra `plantillas_permisos` a propósito: esta tabla
     * (vieja, siempre existió) no puede depender de que la migración de
     * database/plantillas_permisos_migration.sql ya se haya corrido — si el código se
     * sube antes que esa migración (orden de deploy no atómico, ya pasó otras veces en
     * este proyecto), un JOIN roto acá tumbaría /permisos, una pantalla que se usa todo
     * el tiempo. PermisosController::index() resuelve el aviso de "este rol ya usa una
     * plantilla" del lado de PHP, con manejo de error si la tabla nueva no existe todavía.
     */
    public function getRoles(): array
    {
        return $this->queryAll(
            "SELECT id, nombre, descripcion FROM roles WHERE nombre != 'admin' ORDER BY id"
        );
    }

    /** Permissions indexed as [rol_id][modulo] = ['ver' => bool, 'editar' => bool] */
    public function getMatrix(): array
    {
        $matrix = [];
        foreach ($this->queryAll("SELECT rol_id, modulo, ver, editar FROM permisos") as $row) {
            $matrix[$row['rol_id']][$row['modulo']] = [
                'ver'    => (bool)$row['ver'],
                'editar' => (bool)$row['editar'],
            ];
        }
        return $matrix;
    }

    /**
     * Replace full permission matrix for all non-admin roles.
     * $data: [rol_id => [modulo => ['ver' => 0|1, 'editar' => 0|1]]]
     */
    public function saveAll(array $data): void
    {
        $conn = $this->db;
        $conn->beginTransaction();
        try {
            $conn->exec(
                "DELETE p FROM permisos p
                 JOIN roles r ON r.id = p.rol_id
                 WHERE r.nombre != 'admin'"
            );

            $stmt = $conn->prepare(
                "INSERT INTO permisos (rol_id, modulo, ver, editar) VALUES (?, ?, ?, ?)"
            );
            foreach ($data as $rolId => $modulos) {
                foreach ($modulos as $modulo => $flags) {
                    if (!array_key_exists($modulo, self::MODULOS)) continue;
                    $stmt->execute([
                        (int)$rolId,
                        $modulo,
                        (int)!empty($flags['ver']),
                        (int)!empty($flags['editar']),
                    ]);
                }
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
