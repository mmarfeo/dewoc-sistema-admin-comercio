-- ============================================================
--  DEWOC — Sistema de Administración de Comercios
--  Schema MySQL · UTF8MB4
-- ============================================================
CREATE DATABASE IF NOT EXISTS `dewoc_comercio`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dewoc_comercio`;

SET FOREIGN_KEY_CHECKS = 0;

-- ── Roles ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(50)  NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rol_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Usuarios ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nombre`           VARCHAR(100)    NOT NULL,
  `email`            VARCHAR(150)    NOT NULL,
  `password_hash`    VARCHAR(255)    NOT NULL,
  `rol_id`           TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `foto`             VARCHAR(255)    DEFAULT NULL,
  `activo`           TINYINT(1)      NOT NULL DEFAULT 1,
  `eliminado`        TINYINT(1)      NOT NULL DEFAULT 0,
  `intentos_login`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta`  DATETIME        DEFAULT NULL,
  `ultimo_login`     DATETIME        DEFAULT NULL,
  `remember_token`   VARCHAR(64)     DEFAULT NULL,
  `remember_expires` DATETIME        DEFAULT NULL,
  `reset_token`      VARCHAR(64)     DEFAULT NULL,
  `reset_expires`    DATETIME        DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_email` (`email`),
  KEY `fk_usuario_rol` (`rol_id`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Permisos por módulo ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `permisos` (
  `id`       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `rol_id`   TINYINT UNSIGNED NOT NULL,
  `modulo`   VARCHAR(50)     NOT NULL,
  `ver`      TINYINT(1)      NOT NULL DEFAULT 0,
  `editar`   TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permiso` (`rol_id`, `modulo`),
  CONSTRAINT `fk_permiso_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Categorías ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categorias` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100)   NOT NULL,
  `descripcion` VARCHAR(255)   DEFAULT NULL,
  `padre_id`    INT UNSIGNED   DEFAULT NULL,
  `tipo`        ENUM('producto','materia_prima','insumo') NOT NULL DEFAULT 'producto',
  `activo`      TINYINT(1)     NOT NULL DEFAULT 1,
  `eliminado`   TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_categoria_padre` (`padre_id`),
  CONSTRAINT `fk_categoria_padre` FOREIGN KEY (`padre_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Unidades de medida ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `unidades_medida` (
  `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(50)  NOT NULL,
  `abreviatura` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Proveedores ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `razon_social`  VARCHAR(150)  NOT NULL,
  `cuit`          VARCHAR(13)   DEFAULT NULL,
  `condicion_iva` ENUM('responsable_inscripto','monotributista','exento','consumidor_final') NOT NULL DEFAULT 'responsable_inscripto',
  `direccion`     VARCHAR(255)  DEFAULT NULL,
  `telefono`      VARCHAR(30)   DEFAULT NULL,
  `email`         VARCHAR(150)  DEFAULT NULL,
  `banco`         VARCHAR(100)  DEFAULT NULL,
  `cbu`           VARCHAR(22)   DEFAULT NULL,
  `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
  `eliminado`     TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Materias primas ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `materias_primas` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(30)      DEFAULT NULL,
  `nombre`          VARCHAR(150)     NOT NULL,
  `categoria_id`    INT UNSIGNED     DEFAULT NULL,
  `unidad_medida_id` TINYINT UNSIGNED DEFAULT NULL,
  `stock_minimo`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `stock_actual`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `costo_unitario`  DECIMAL(12,2)    NOT NULL DEFAULT 0,
  `proveedor_id`    INT UNSIGNED     DEFAULT NULL,
  `activo`          TINYINT(1)       NOT NULL DEFAULT 1,
  `eliminado`       TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mp_categoria` (`categoria_id`),
  KEY `fk_mp_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_mp_categoria`  FOREIGN KEY (`categoria_id`)    REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mp_proveedor`  FOREIGN KEY (`proveedor_id`)    REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mp_unidad`     FOREIGN KEY (`unidad_medida_id`) REFERENCES `unidades_medida` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Insumos ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `insumos` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(30)      DEFAULT NULL,
  `nombre`          VARCHAR(150)     NOT NULL,
  `categoria_id`    INT UNSIGNED     DEFAULT NULL,
  `unidad_medida_id` TINYINT UNSIGNED DEFAULT NULL,
  `stock_minimo`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `stock_actual`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `costo_unitario`  DECIMAL(12,2)    NOT NULL DEFAULT 0,
  `activo`          TINYINT(1)       NOT NULL DEFAULT 1,
  `eliminado`       TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_insumo_categoria` FOREIGN KEY (`categoria_id`)    REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_insumo_unidad`    FOREIGN KEY (`unidad_medida_id`) REFERENCES `unidades_medida` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Productos ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `productos` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `codigo`          VARCHAR(30)      DEFAULT NULL,
  `nombre`          VARCHAR(150)     NOT NULL,
  `descripcion`     TEXT             DEFAULT NULL,
  `categoria_id`    INT UNSIGNED     DEFAULT NULL,
  `tipo`            ENUM('elaborado','reventa') NOT NULL DEFAULT 'reventa',
  `precio_venta`    DECIMAL(12,2)    NOT NULL DEFAULT 0,
  `precio_costo`    DECIMAL(12,2)    NOT NULL DEFAULT 0,
  `iva`             DECIMAL(5,2)     NOT NULL DEFAULT 21.00 COMMENT '0, 10.5 o 21',
  `unidad_medida_id` TINYINT UNSIGNED DEFAULT NULL,
  `stock_minimo`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `stock_actual`    DECIMAL(12,3)    NOT NULL DEFAULT 0,
  `imagen`          VARCHAR(255)     DEFAULT NULL,
  `ecommerce`       TINYINT(1)       NOT NULL DEFAULT 0,
  `activo`          TINYINT(1)       NOT NULL DEFAULT 1,
  `eliminado`       TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_producto_codigo` (`codigo`),
  CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`categoria_id`)    REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_producto_unidad`    FOREIGN KEY (`unidad_medida_id`) REFERENCES `unidades_medida` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Recetas ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recetas` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(150)  NOT NULL,
  `producto_id`        INT UNSIGNED  DEFAULT NULL COMMENT 'Producto terminado que genera',
  `rendimiento`        DECIMAL(12,3) NOT NULL DEFAULT 1,
  `unidad_rendimiento` VARCHAR(30)   NOT NULL DEFAULT 'unidades',
  `notas`              TEXT          DEFAULT NULL,
  `activo`             TINYINT(1)    NOT NULL DEFAULT 1,
  `eliminado`          TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_receta_producto` (`producto_id`),
  CONSTRAINT `fk_receta_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `receta_detalle` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `receta_id`        INT UNSIGNED  NOT NULL,
  `tipo_ingrediente` ENUM('materia_prima','insumo') NOT NULL,
  `materia_prima_id` INT UNSIGNED  DEFAULT NULL,
  `insumo_id`        INT UNSIGNED  DEFAULT NULL,
  `cantidad`         DECIMAL(12,4) NOT NULL,
  `merma_pct`        DECIMAL(5,2)  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_rd_receta` (`receta_id`),
  CONSTRAINT `fk_rd_receta` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recetas_versiones` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `receta_id`          INT UNSIGNED  NOT NULL,
  `version`            INT UNSIGNED  NOT NULL DEFAULT 1,
  `nombre`             VARCHAR(150)  NOT NULL,
  `rendimiento`        DECIMAL(12,3) NOT NULL,
  `unidad_rendimiento` VARCHAR(30)   NOT NULL,
  `detalle_json`       TEXT          NOT NULL,
  `usuario_id`         INT UNSIGNED  DEFAULT NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rv_receta`  (`receta_id`),
  CONSTRAINT `fk_rv_receta`  FOREIGN KEY (`receta_id`)  REFERENCES `recetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Órdenes de producción ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ordenes_produccion` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `receta_id`         INT UNSIGNED  NOT NULL,
  `usuario_id`        INT UNSIGNED  DEFAULT NULL,
  `cantidad_producida` DECIMAL(12,3) NOT NULL,
  `estado`            ENUM('planificada','en_proceso','completada','anulada') NOT NULL DEFAULT 'completada',
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_op_receta` (`receta_id`),
  CONSTRAINT `fk_op_receta`      FOREIGN KEY (`receta_id`) REFERENCES `recetas`  (`id`),
  CONSTRAINT `fk_op_usuario`     FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Facturas de compra ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `facturas_compra` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `proveedor_id`     INT UNSIGNED  NOT NULL,
  `usuario_id`       INT UNSIGNED  DEFAULT NULL,
  `fecha`            DATE          NOT NULL,
  `tipo_comprobante` ENUM('factura_a','factura_b','factura_c','ticket','remito','otro') NOT NULL DEFAULT 'factura_b',
  `nro_comprobante`  VARCHAR(30)   DEFAULT NULL,
  `condicion_pago`   ENUM('contado','cuenta_corriente','cheque','transferencia') NOT NULL DEFAULT 'contado',
  `subtotal`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_iva`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_con_iva`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `estado`           ENUM('pagada','pendiente','anulada') NOT NULL DEFAULT 'pagada',
  `notas`            TEXT          DEFAULT NULL,
  `eliminado`        TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fc_proveedor` (`proveedor_id`),
  KEY `idx_fc_fecha`     (`fecha`),
  CONSTRAINT `fk_fc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `fk_fc_usuario`   FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `facturas_compra_detalle` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `factura_compra_id` INT UNSIGNED NOT NULL,
  `tipo_item`        ENUM('materia_prima','insumo','producto') NOT NULL,
  `materia_prima_id` INT UNSIGNED  DEFAULT NULL,
  `insumo_id`        INT UNSIGNED  DEFAULT NULL,
  `producto_id`      INT UNSIGNED  DEFAULT NULL,
  `cantidad`         DECIMAL(12,3) NOT NULL,
  `precio_unitario`  DECIMAL(12,4) NOT NULL,
  `subtotal`         DECIMAL(14,4) NOT NULL,
  `iva_porcentaje`   DECIMAL(5,2)  NOT NULL DEFAULT 21,
  `subtotal_con_iva` DECIMAL(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fcd_factura` (`factura_compra_id`),
  CONSTRAINT `fk_fcd_factura` FOREIGN KEY (`factura_compra_id`) REFERENCES `facturas_compra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Clientes ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `clientes` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(150)  NOT NULL,
  `documento`     VARCHAR(20)   DEFAULT NULL COMMENT 'DNI o CUIT',
  `tipo_doc`      ENUM('dni','cuit') DEFAULT 'dni',
  `condicion_iva` ENUM('responsable_inscripto','monotributista','exento','consumidor_final') NOT NULL DEFAULT 'consumidor_final',
  `telefono`      VARCHAR(30)   DEFAULT NULL,
  `email`         VARCHAR(150)  DEFAULT NULL,
  `direccion`     VARCHAR(255)  DEFAULT NULL,
  `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
  `eliminado`     TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Turnos de caja ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `turnos` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `numero`                 INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Número correlativo de turno',
  `usuario_id`             INT UNSIGNED  NOT NULL,
  `monto_inicial`          DECIMAL(14,2) NOT NULL DEFAULT 0,
  `monto_cierre_declarado` DECIMAL(14,2) DEFAULT NULL,
  `monto_cierre_calculado` DECIMAL(14,2) DEFAULT NULL,
  `diferencia`             DECIMAL(14,2) DEFAULT NULL,
  `estado`                 ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  `observaciones`          TEXT          DEFAULT NULL,
  `eliminado`              TINYINT(1)    NOT NULL DEFAULT 0,
  `apertura_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cierre_at`              DATETIME      DEFAULT NULL,
  `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_turno_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Ventas ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ventas` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id`       INT UNSIGNED  NOT NULL,
  `turno_id`         INT UNSIGNED  DEFAULT NULL,
  `cliente_id`       INT UNSIGNED  DEFAULT NULL,
  `subtotal`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_iva`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_con_iva`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `descuento_global` DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `metodo_pago`      ENUM('efectivo','debito','credito','transferencia','mercadopago') NOT NULL DEFAULT 'efectivo',
  `facturar_afip`    TINYINT(1)    NOT NULL DEFAULT 0,
  `afip_tipo_cbte`   TINYINT UNSIGNED DEFAULT NULL,
  `afip_pto_vta`     SMALLINT UNSIGNED DEFAULT NULL,
  `afip_nro_cbte`    INT UNSIGNED DEFAULT NULL,
  `afip_cae`         VARCHAR(14)   DEFAULT NULL,
  `afip_cae_vto`     DATE          DEFAULT NULL,
  `afip_resultado`   CHAR(1)       DEFAULT NULL,
  `afip_obs`         TEXT          DEFAULT NULL,
  `estado`           ENUM('completada','anulada') NOT NULL DEFAULT 'completada',
  `mesa`             VARCHAR(30)   DEFAULT NULL,
  `notas`            TEXT          DEFAULT NULL,
  `eliminado`        TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venta_fecha`   (`created_at`),
  KEY `idx_venta_turno`   (`turno_id`),
  CONSTRAINT `fk_venta_turno`   FOREIGN KEY (`turno_id`)   REFERENCES `turnos`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ventas_detalle` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `venta_id`         INT UNSIGNED  NOT NULL,
  `producto_id`      INT UNSIGNED  NOT NULL,
  `cantidad`         DECIMAL(12,3) NOT NULL,
  `precio_unitario`  DECIMAL(12,4) NOT NULL,
  `descuento_pct`    DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `subtotal`         DECIMAL(14,4) NOT NULL,
  `iva_porcentaje`   DECIMAL(5,2)  NOT NULL DEFAULT 21,
  `subtotal_con_iva` DECIMAL(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vd_venta` (`venta_id`),
  CONSTRAINT `fk_vd_venta`    FOREIGN KEY (`venta_id`)    REFERENCES `ventas`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vd_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Movimientos de stock ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_movimientos` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo_item`    ENUM('producto','materia_prima','insumo') NOT NULL,
  `item_id`      INT UNSIGNED  NOT NULL,
  `tipo_mov`     ENUM('entrada','salida','ajuste_pos','ajuste_neg','apertura') NOT NULL,
  `origen`       ENUM('compra','venta','produccion','ajuste_manual','inventario','apertura') NOT NULL,
  `origen_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'ID del documento origen',
  `cantidad`     DECIMAL(12,3) NOT NULL,
  `stock_antes`  DECIMAL(12,3) NOT NULL,
  `stock_despues` DECIMAL(12,3) NOT NULL,
  `motivo`       VARCHAR(255)  DEFAULT NULL,
  `usuario_id`   INT UNSIGNED  DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_item` (`tipo_item`, `item_id`),
  KEY `idx_sm_fecha` (`created_at`),
  CONSTRAINT `fk_sm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Inventarios físicos ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventarios` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `estado`      ENUM('en_proceso','aprobado','cancelado') NOT NULL DEFAULT 'en_proceso',
  `observaciones` TEXT       DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aprobado_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_inv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventarios_detalle` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `inventario_id`   INT UNSIGNED  NOT NULL,
  `tipo_item`       ENUM('producto','materia_prima','insumo') NOT NULL,
  `item_id`         INT UNSIGNED  NOT NULL,
  `stock_teorico`   DECIMAL(12,3) NOT NULL,
  `stock_fisico`    DECIMAL(12,3) NOT NULL,
  `diferencia`      DECIMAL(12,3) GENERATED ALWAYS AS (`stock_fisico` - `stock_teorico`) STORED,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_id_inventario` FOREIGN KEY (`inventario_id`) REFERENCES `inventarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Movimientos de caja ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `caja_movimientos` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `turno_id`    INT UNSIGNED  NOT NULL,
  `tipo`        ENUM('ingreso','egreso','apertura','cierre','ajuste') NOT NULL DEFAULT 'ingreso',
  `monto`       DECIMAL(14,2) NOT NULL,
  `descripcion` VARCHAR(255)  DEFAULT NULL,
  `venta_id`    INT UNSIGNED  DEFAULT NULL,
  `usuario_id`  INT UNSIGNED  NOT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cm_turno` (`turno_id`),
  CONSTRAINT `fk_cm_turno`   FOREIGN KEY (`turno_id`)   REFERENCES `turnos`   (`id`),
  CONSTRAINT `fk_cm_venta`   FOREIGN KEY (`venta_id`)   REFERENCES `ventas`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Pedidos ecommerce ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pedidos_ecommerce` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cliente_nombre`   VARCHAR(150)  NOT NULL,
  `cliente_email`    VARCHAR(150)  NOT NULL,
  `cliente_telefono` VARCHAR(30)   DEFAULT NULL,
  `cliente_direccion` VARCHAR(255)  DEFAULT NULL,
  `subtotal`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_iva`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total_con_iva`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `estado`           ENUM('pendiente','pagado','en_preparacion','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `notas`            TEXT          DEFAULT NULL,
  `mp_preference_id` VARCHAR(100)  DEFAULT NULL,
  `mp_payment_id`    VARCHAR(100)  DEFAULT NULL,
  `mp_status`        VARCHAR(50)   DEFAULT NULL,
  `eliminado`         TINYINT(1)    NOT NULL DEFAULT 0,
  `stock_descontado`  TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pe_estado` (`estado`),
  KEY `idx_pe_mp_pref` (`mp_preference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pedidos_ecommerce_detalle` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `pedido_id`       INT UNSIGNED  NOT NULL,
  `producto_id`     INT UNSIGNED  NOT NULL,
  `cantidad`        DECIMAL(12,3) NOT NULL,
  `precio_unitario` DECIMAL(12,4) NOT NULL,
  `iva_porcentaje`  DECIMAL(5,2)  NOT NULL DEFAULT 21,
  `subtotal_con_iva` DECIMAL(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ped_pedido` (`pedido_id`),
  CONSTRAINT `fk_ped_pedido`   FOREIGN KEY (`pedido_id`)   REFERENCES `pedidos_ecommerce` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ped_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Pagos Mercado Pago ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pagos_mercadopago` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `pedido_id`     INT UNSIGNED  DEFAULT NULL,
  `venta_id`      INT UNSIGNED  DEFAULT NULL,
  `payment_id`    VARCHAR(100)  NOT NULL,
  `status`        VARCHAR(50)   NOT NULL,
  `status_detail` VARCHAR(100)  DEFAULT NULL,
  `payer_email`   VARCHAR(150)  DEFAULT NULL,
  `amount`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_type`  VARCHAR(50)   DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_payment` (`payment_id`),
  CONSTRAINT `fk_mp_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_ecommerce` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mp_venta`  FOREIGN KEY (`venta_id`)  REFERENCES `ventas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Chat IA ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `chat_mensajes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `rol`         ENUM('user','assistant') NOT NULL,
  `contenido`   TEXT         NOT NULL,
  `sql_ejecutado` TEXT       DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_chat_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Log de auditoría ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      INT UNSIGNED    DEFAULT NULL,
  `usuario_nombre`  VARCHAR(100)    DEFAULT NULL,
  `accion`          VARCHAR(50)     NOT NULL,
  `modulo`          VARCHAR(50)     NOT NULL,
  `descripcion`     TEXT            DEFAULT NULL,
  `ip`              VARCHAR(45)     DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_usuario` (`usuario_id`),
  KEY `idx_al_modulo`  (`modulo`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Migraciones (para instalaciones existentes) ───────────────
ALTER TABLE `ordenes_produccion`
  MODIFY COLUMN `estado` ENUM('planificada','en_proceso','completada','anulada') NOT NULL DEFAULT 'completada';

ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `mesa`           VARCHAR(30)      DEFAULT NULL AFTER `notas`,
  ADD COLUMN IF NOT EXISTS `afip_tipo_cbte` TINYINT UNSIGNED DEFAULT NULL AFTER `facturar_afip`,
  ADD COLUMN IF NOT EXISTS `afip_pto_vta`   SMALLINT UNSIGNED DEFAULT NULL AFTER `afip_tipo_cbte`,
  ADD COLUMN IF NOT EXISTS `afip_nro_cbte`  INT UNSIGNED     DEFAULT NULL AFTER `afip_pto_vta`,
  ADD COLUMN IF NOT EXISTS `afip_cae`       VARCHAR(14)      DEFAULT NULL AFTER `afip_nro_cbte`,
  ADD COLUMN IF NOT EXISTS `afip_cae_vto`   DATE             DEFAULT NULL AFTER `afip_cae`,
  ADD COLUMN IF NOT EXISTS `afip_resultado` CHAR(1)          DEFAULT NULL AFTER `afip_cae_vto`,
  ADD COLUMN IF NOT EXISTS `afip_obs`       TEXT             DEFAULT NULL AFTER `afip_resultado`;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `remember_token`   VARCHAR(64)  DEFAULT NULL AFTER `ultimo_login`,
  ADD COLUMN IF NOT EXISTS `remember_expires` DATETIME     DEFAULT NULL AFTER `remember_token`,
  ADD COLUMN IF NOT EXISTS `reset_token`      VARCHAR(64)  DEFAULT NULL AFTER `remember_expires`,
  ADD COLUMN IF NOT EXISTS `reset_expires`    DATETIME     DEFAULT NULL AFTER `reset_token`;

CREATE TABLE IF NOT EXISTS `recetas_versiones` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `receta_id`          INT UNSIGNED  NOT NULL,
  `version`            INT UNSIGNED  NOT NULL DEFAULT 1,
  `nombre`             VARCHAR(150)  NOT NULL,
  `rendimiento`        DECIMAL(12,3) NOT NULL,
  `unidad_rendimiento` VARCHAR(30)   NOT NULL,
  `detalle_json`       TEXT          NOT NULL,
  `usuario_id`         INT UNSIGNED  DEFAULT NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rv_receta`  (`receta_id`),
  CONSTRAINT `fk_rv_receta`  FOREIGN KEY (`receta_id`)  REFERENCES `recetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
