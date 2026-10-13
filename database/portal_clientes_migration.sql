-- Portal de Clientes Mayoristas (ver docs/portal-clientes-mayoristas-checklist.md).
-- Login propio para `clientes` (separado de `usuarios`), catálogo asignado por cliente,
-- pedidos que requieren aceptación del cajero (modal bloqueante en ventas/pos), y un chat
-- por pedido entre cliente y staff. Ejecutar una sola vez desde phpMyAdmin.
--
-- Tipos verificados contra schema.sql antes de escribir esto (clientes.id, productos.id,
-- ventas.id, opciones.id, turnos.id, usuarios.id son todos INT UNSIGNED) — la migración de
-- Cuenta Corriente ya rompió una vez por un desajuste de tipos en una FK (error 150).
--
-- Si este script corta a mitad de camino y hay que reejecutarlo: los ADD COLUMN llevan
-- "IF NOT EXISTS" y los CREATE TABLE llevan "IF NOT EXISTS", así que se saltean solos lo
-- que ya se haya aplicado. Los ADD CONSTRAINT (fk_push_cliente, fk_ventas_pedido_cliente)
-- NO tienen ese resguardo — MySQL no soporta "IF NOT EXISTS" para constraints. Si al
-- reejecutar da error de nombre de constraint duplicado, es señal de que esa parte puntual
-- ya se había aplicado antes — se puede comentar esa línea y volver a correr el resto.

ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `password_hash` VARCHAR(255) NULL AFTER `limite_cuenta_corriente`,
  ADD COLUMN IF NOT EXISTS `portal_activo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`;

-- usuario_id pasa a nullable: una suscripción de push ahora puede ser de un cliente o de un
-- usuario de staff, nunca de los dos a la vez. El FK existente (fk_push_usuario) sigue
-- funcionando igual — una FK no valida filas con NULL.
ALTER TABLE `push_subscriptions`
  MODIFY COLUMN `usuario_id` INT UNSIGNED NULL;

ALTER TABLE `push_subscriptions`
  ADD COLUMN IF NOT EXISTS `cliente_id` INT UNSIGNED NULL AFTER `usuario_id`,
  ADD CONSTRAINT `fk_push_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

-- Catálogo habilitado por cliente (qué productos ve, con precio propio opcional)
CREATE TABLE IF NOT EXISTS `clientes_catalogo` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `cliente_id`      INT UNSIGNED   NOT NULL,
  `producto_id`     INT UNSIGNED   NOT NULL,
  `precio_especial` DECIMAL(12,2)  NULL COMMENT 'NULL = usa productos.precio_venta tal cual',
  `activo`          TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalogo_cliente_producto` (`cliente_id`, `producto_id`),
  CONSTRAINT `fk_catalogo_cliente`  FOREIGN KEY (`cliente_id`)  REFERENCES `clientes`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_catalogo_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pedido de un cliente mayorista, pendiente de aceptación del cajero
CREATE TABLE IF NOT EXISTS `pedidos_clientes` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cliente_id`    INT UNSIGNED  NOT NULL,
  `estado`        ENUM('pendiente','aceptado','rechazado','convertido') NOT NULL DEFAULT 'pendiente',
  `notas_cliente` TEXT          NULL,
  `turno_id`      INT UNSIGNED  NULL COMMENT 'Turno del cajero que lo atendió',
  `usuario_id`    INT UNSIGNED  NULL COMMENT 'Cajero que lo atendió',
  `venta_id`      INT UNSIGNED  NULL COMMENT 'Venta resultante, una vez convertido',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_at`   DATETIME      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pc_cliente` (`cliente_id`),
  KEY `idx_pc_estado`  (`estado`),
  CONSTRAINT `fk_pc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_pc_turno`   FOREIGN KEY (`turno_id`)   REFERENCES `turnos`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pc_venta`   FOREIGN KEY (`venta_id`)   REFERENCES `ventas`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detalle del pedido — precio_unitario es un snapshot al momento del pedido (no se
-- recalcula si cambia el precio_especial del cliente después).
CREATE TABLE IF NOT EXISTS `pedidos_clientes_detalle` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `pedido_id`       INT UNSIGNED  NOT NULL,
  `producto_id`     INT UNSIGNED  NOT NULL,
  `cantidad`        DECIMAL(12,3) NOT NULL,
  `precio_unitario` DECIMAL(12,4) NOT NULL,
  `iva_porcentaje`  DECIMAL(5,2)  NOT NULL DEFAULT 21,
  PRIMARY KEY (`id`),
  KEY `idx_pcd_pedido` (`pedido_id`),
  CONSTRAINT `fk_pcd_pedido`   FOREIGN KEY (`pedido_id`)   REFERENCES `pedidos_clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcd_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`        (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gustos/opciones elegidas por línea del pedido — mismo molde que ventas_detalle_opciones.
CREATE TABLE IF NOT EXISTS `pedidos_clientes_detalle_opciones` (
  `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_cliente_detalle_id` INT UNSIGNED NOT NULL,
  `opcion_id`                 INT UNSIGNED NOT NULL,
  `cantidad_elegida`          INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_pcdo_detalle` (`pedido_cliente_detalle_id`),
  CONSTRAINT `fk_pcdo_detalle` FOREIGN KEY (`pedido_cliente_detalle_id`) REFERENCES `pedidos_clientes_detalle` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcdo_opcion`  FOREIGN KEY (`opcion_id`)                 REFERENCES `opciones`                  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat del pedido — un solo hilo por pedido, entre el cliente y cualquier staff que atienda.
CREATE TABLE IF NOT EXISTS `pedidos_clientes_mensajes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id`   INT UNSIGNED NOT NULL,
  `autor_tipo`  ENUM('cliente','staff') NOT NULL,
  `autor_id`    INT UNSIGNED NOT NULL COMMENT 'cliente_id o usuario_id según autor_tipo',
  `mensaje`     TEXT         NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leido_at`    DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pcm_pedido` (`pedido_id`),
  CONSTRAINT `fk_pcm_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Venta resultante de un pedido de cliente, una vez que el cajero lo cobra.
ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `pedido_cliente_id` INT UNSIGNED NULL AFTER `cliente_id`,
  ADD CONSTRAINT `fk_ventas_pedido_cliente` FOREIGN KEY (`pedido_cliente_id`) REFERENCES `pedidos_clientes` (`id`) ON DELETE SET NULL;
