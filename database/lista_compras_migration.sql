-- Lista de compras: candidatos de reposición (automáticos, por días de stock restante
-- calculados a partir del consumo real en stock_movimientos) + ítems agregados a mano
-- desde el Chat IA o la pantalla "Lista de compras". Ver core/NotificadorReportes.php
-- (mismo patrón de aviso) y app/Models/ListaComprasModel.php.
CREATE TABLE IF NOT EXISTS `lista_compras` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo_item`           ENUM('materia_prima','insumo','producto') NOT NULL,
  `item_id`             INT UNSIGNED  NOT NULL,
  `estado`              ENUM('sugerido','pendiente','comprado','descartado') NOT NULL DEFAULT 'pendiente',
  `origen`              ENUM('automatico','manual') NOT NULL DEFAULT 'manual',
  `dias_stock_restante` DECIMAL(6,1)  DEFAULT NULL COMMENT 'Foto del cálculo al momento de sugerirlo/agregarlo, no se actualiza sola',
  `proveedor_id`        INT UNSIGNED  DEFAULT NULL COMMENT 'Proveedor habitual detectado (materias_primas.proveedor_id o última compra)',
  `creado_por`          INT UNSIGNED  DEFAULT NULL COMMENT 'Usuario que lo agregó a mano; NULL = generado por el cálculo automático',
  `notas`               VARCHAR(255)  DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resuelto_at`         DATETIME      DEFAULT NULL COMMENT 'Cuándo pasó a comprado/descartado',
  PRIMARY KEY (`id`),
  KEY `idx_lc_item` (`tipo_item`, `item_id`),
  KEY `idx_lc_estado` (`estado`),
  KEY `idx_lc_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_lc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lc_usuario`   FOREIGN KEY (`creado_por`)   REFERENCES `usuarios` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
