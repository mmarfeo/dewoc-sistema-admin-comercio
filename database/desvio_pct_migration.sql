-- Desvío (pérdida no explicada por ventas ni proceso: robo, rotura, consumo interno,
-- regalos) — distinto de la merma de receta, que ya existe y es pérdida de proceso
-- conocida. El desvío ajusta el costo real de productos de reventa y materias primas.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `productos`
  ADD COLUMN `desvio_pct`
    DECIMAL(5,2) NOT NULL DEFAULT 0
    COMMENT 'Pérdida no explicada (robo/rotura/consumo interno), distinta de la merma de receta'
    AFTER `controla_stock`;

ALTER TABLE `materias_primas`
  ADD COLUMN `desvio_pct`
    DECIMAL(5,2) NOT NULL DEFAULT 0
    COMMENT 'Pérdida no explicada (robo/rotura/consumo interno), distinta de la merma de receta'
    AFTER `costo_unitario`;

-- Historial de cada evaluación de desvío (se genera al hacer un conteo físico de
-- inventario, no en cualquier ajuste manual suelto). item_tipo/item_id es polimórfico,
-- mismo criterio que ya usa el resto del sistema (ver StockModel::itemsBajoMinimo()).
CREATE TABLE IF NOT EXISTS `desvio_historial` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `item_tipo`                ENUM('producto','materia_prima') NOT NULL,
  `item_id`                  INT UNSIGNED  NOT NULL,
  `fecha`                    DATE          NOT NULL,
  `periodo_desde`            DATETIME      NOT NULL,
  `cantidad_comprada`        DECIMAL(14,3) NOT NULL DEFAULT 0,
  `cantidad_explicada_ventas` DECIMAL(14,3) NOT NULL DEFAULT 0,
  `stock_teorico`            DECIMAL(14,3) NOT NULL DEFAULT 0,
  `stock_real_contado`       DECIMAL(14,3) NOT NULL DEFAULT 0,
  `desvio_cantidad`          DECIMAL(14,3) NOT NULL DEFAULT 0,
  `desvio_pct`               DECIMAL(6,2)  NOT NULL DEFAULT 0,
  `costo_unitario_momento`   DECIMAL(12,4) NOT NULL DEFAULT 0,
  `desvio_monto`             DECIMAL(14,2) NOT NULL DEFAULT 0,
  `usuario_id`               INT UNSIGNED  DEFAULT NULL,
  `created_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dh_item` (`item_tipo`, `item_id`, `fecha`),
  CONSTRAINT `fk_dh_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
