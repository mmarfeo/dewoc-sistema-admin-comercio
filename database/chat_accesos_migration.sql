-- Accesos directos configurables del Chat IA (los "chips" debajo del input del chat).
-- El admin administra el catálogo en /chat/accesos y asigna cuáles ve cada usuario
-- (asignación por USUARIO individual, no por rol).
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `chat_accesos_directos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(60)  NOT NULL COMMENT 'Texto del botón, ej: "📊 Ventas esta semana"',
  `texto`      VARCHAR(255) NOT NULL COMMENT 'Prompt que se envía al chat al hacer click',
  `orden`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_accesos_usuario` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `acceso_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cau_usuario_acceso` (`usuario_id`, `acceso_id`),
  CONSTRAINT `fk_cau_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cau_acceso`  FOREIGN KEY (`acceso_id`)  REFERENCES `chat_accesos_directos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo inicial: los mismos accesos que ya estaban fijos en el código.
INSERT INTO `chat_accesos_directos` (`label`, `texto`, `orden`) VALUES
('📊 Ventas esta semana',    '¿Cuánto generamos en ventas esta semana?', 1),
('💰 Ganancia bruta mensual', '¿Cuánto fue la ganancia bruta de este mes?', 2),
('🏆 Top 5 productos',        '¿Cuáles son los 5 productos más vendidos?', 3),
('📦 Stock bajo mínimo',      '¿Qué ítems tienen stock bajo el mínimo?', 4),
('🛒 Compras del mes',        '¿Cuánto compramos a proveedores este mes?', 5),
('🏧 Movimiento de caja hoy', '¿Cuál fue el movimiento de caja hoy?', 6),
('👥 Clientes nuevos',        '¿Cuántos clientes nuevos tuvimos esta semana?', 7),
('➕ Nuevo cliente',          'Creá un cliente nuevo', 8),
('🏢 Nuevo proveedor',        'Agregá un proveedor nuevo', 9),
('🧾 Registrar compra',       'Registrá una factura de compra', 10);

-- Asignar todo el catálogo inicial a los usuarios ya existentes, para no
-- "perder" los accesos que ya tenían todos por defecto antes de esta migración.
-- De acá en adelante, el admin asigna manualmente qué ve cada usuario.
INSERT INTO `chat_accesos_usuario` (`usuario_id`, `acceso_id`)
SELECT u.id, a.id
FROM `usuarios` u
JOIN `chat_accesos_directos` a
WHERE u.eliminado = 0;
