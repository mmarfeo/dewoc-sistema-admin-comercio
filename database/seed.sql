-- ── Seed inicial ────────────────────────────────────────────
USE `dewoc_comercio`;

-- Roles
INSERT IGNORE INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'admin',     'Acceso total al sistema'),
(2, 'cajero',    'Acceso a ventas y caja'),
(3, 'deposito',  'Acceso a stock y compras'),
(4, 'vendedor',  'Acceso solo a ventas');

-- Usuario administrador por defecto: admin@dewoc.com / admin1234
INSERT IGNORE INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `rol_id`) VALUES
(1, 'Administrador', 'admin@dewoc.com',
 '$2y$12$uScwT5MRBU0.kJnpBBzXHuNYJWxp7.hUxX0Tmt.A5d9v5IVXW8rWy', -- admin1234
 1);

-- Unidades de medida
INSERT IGNORE INTO `unidades_medida` (`id`, `nombre`, `abreviatura`) VALUES
(1,  'Kilogramo',   'kg'),
(2,  'Gramo',       'g'),
(3,  'Litro',       'L'),
(4,  'Mililitro',   'ml'),
(5,  'Unidad',      'u'),
(6,  'Docena',      'doc'),
(7,  'Paquete',     'paq'),
(8,  'Caja',        'caja'),
(9,  'Metro',       'm'),
(10, 'Centímetro',  'cm');

-- Categorías base
INSERT IGNORE INTO `categorias` (`id`, `nombre`, `tipo`, `activo`) VALUES
(1, 'Alimentos y Bebidas', 'producto',      1),
(2, 'Limpieza',           'producto',      1),
(3, 'Harinas y Almidones','materia_prima',  1),
(4, 'Lácteos',            'materia_prima',  1),
(5, 'Frutas y Verduras',  'materia_prima',  1),
(6, 'Packaging',          'insumo',        1),
(7, 'Descartables',       'insumo',        1);

-- Permisos por defecto para cajero
INSERT IGNORE INTO `permisos` (`rol_id`, `modulo`, `ver`, `editar`) VALUES
(2, 'ventas',   1, 1),
(2, 'caja',     1, 1),
(2, 'productos',1, 0),
(2, 'stock',    1, 0);

-- Permisos por defecto para depósito
INSERT IGNORE INTO `permisos` (`rol_id`, `modulo`, `ver`, `editar`) VALUES
(3, 'stock',    1, 1),
(3, 'compras',  1, 1),
(3, 'productos',1, 0),
(3, 'recetas',  1, 0);

-- Permisos por defecto para vendedor
INSERT IGNORE INTO `permisos` (`rol_id`, `modulo`, `ver`, `editar`) VALUES
(4, 'ventas',   1, 1),
(4, 'productos',1, 0),
(4, 'stock',    1, 0);
