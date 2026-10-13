<?php
/**
 * SOLO DESARROLLO — no requiere login ni toca la base de datos: arma una venta de
 * ejemplo (con una promo + sus componentes) y renderiza comanda.php/ticket.php tal
 * cual, para poder revisar el diseño del ticket sin tener que cargar una venta real.
 * Borrar este archivo cuando termines de revisar — no debe quedar en producción.
 *
 * Uso: http://localhost/01-Dewoc-Sist-Admin-Comercio/preview_ticket.php?tipo=comanda
 *      http://localhost/01-Dewoc-Sist-Admin-Comercio/preview_ticket.php?tipo=ticket
 */

require __DIR__ . '/config/config.php';
require __DIR__ . '/core/helpers.php';

$tipo = ($_GET['tipo'] ?? 'comanda') === 'ticket' ? 'ticket' : 'comanda';

$venta = [
    'id'               => 9999,
    'created_at'       => date('Y-m-d H:i:s'),
    'usuario_nombre'   => 'Vendedor Demo',
    'notas'            => 'Venta de ejemplo — solo para revisar el diseño, no existe en la base.',
    'mesa'             => 'Mostrador',
    'cliente_nombre'   => null,
    'cliente_doc'      => null,
    'cliente_tipo_doc' => null,
    'cliente_civa'     => null,
    'cliente_direccion'=> null,
    'cliente_telefono' => null,
    'cliente_email'    => null,
    'descuento_global' => 0,
    'metodo_pago'      => 'efectivo',
    'facturar_afip'    => 0,
    'afip_cae'         => null,
    'afip_cae_vto'     => null,
    'afip_tipo_cbte'   => null,
    'afip_pto_vta'     => null,
    'afip_nro_cbte'    => null,
    'total_con_iva'    => 8000,
    'detalle' => [
        // Cabecera de la promo — cantidad 1: no debe mostrar "1×".
        [
            'id' => 1, 'cantidad' => 1, 'producto_nombre' => 'Promo 2 x Wafflez Pezco Helado',
            'producto_codigo' => null, 'precio_unitario' => 5000, 'subtotal_con_iva' => 5000,
            'venta_detalle_padre_id' => null, 'opciones' => [],
        ],
        // Componentes de la promo — cada uno con su propia cantidad real.
        [
            'id' => 2, 'cantidad' => 1, 'producto_nombre' => 'Waffle Pez',
            'producto_codigo' => null, 'precio_unitario' => 0, 'subtotal_con_iva' => 0,
            'venta_detalle_padre_id' => 1,
            'opciones' => [['cantidad_elegida' => 1, 'nombre' => 'Relleno: Dulce de leche']],
        ],
        [
            'id' => 3, 'cantidad' => 1, 'producto_nombre' => 'Wafle Pez',
            'producto_codigo' => null, 'precio_unitario' => 0, 'subtotal_con_iva' => 0,
            'venta_detalle_padre_id' => 1,
            'opciones' => [['cantidad_elegida' => 1, 'nombre' => 'Relleno: Chocolate']],
        ],
        // Producto normal, sin relación con la promo.
        [
            'id' => 4, 'cantidad' => 2, 'producto_nombre' => 'Café con leche',
            'producto_codigo' => 'CAF-01', 'precio_unitario' => 1500, 'subtotal_con_iva' => 3000,
            'venta_detalle_padre_id' => null, 'opciones' => [],
        ],
    ],
];

require __DIR__ . '/app/Views/ventas/' . $tipo . '.php';
